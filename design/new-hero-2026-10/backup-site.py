#!/usr/bin/env python3
"""
Back up a site's database and uploads into a local folder (cards #7232, #6473, docs/OPERATIONS.md "Backups").

    python3 backup-site.py --site https://prospergenics.com --db --uploads --out backup/
    python3 backup-site.py --site https://prospergenics.com --db --uploads --out backup/ --encrypt-to key.asc
    python3 backup-site.py --site https://test.prospergenics.com --db --uploads --dry-run

  --db       asks the site for a dump (a signed POST to /wp-json/prospergenics/v1/ops/db-backup, theme module
             inc/ops-backup.php), which the site writes OUTSIDE its web root; downloads that file over FTPS,
             checks its size and SHA-256 and deletes it from the host (--keep-remote leaves it).
  --uploads  downloads wp-content/uploads/ over FTPS into one uploads-<time>.tar.gz.
  --encrypt-to <public key file>  encrypts every file with GnuPG for that key and removes the plain copies.
             The workflows always do this: the repository is public, so its Actions artifacts are not private.
  --dry-run  checks the signature and the folders (the site's dry_run, a listing of uploads with its size);
             writes nothing.
backup.json in --out lists what was saved (names, sizes, hashes, counts; nothing secret).

Credentials, as the deploy: PG_FTP_USER / PG_FTP_PASSWORD (else the vault on Martien's machine), and
PG_BACKUP_KEY, the same value as PROSPERGENICS_BACKUP_KEY in the site's wp-config.php.
PG_FTP_ROOT is the site's web root on the FTP server (default domains/<host>/public_html); PG_FTP_HOST etc.
as in deploy-wp-theme-vimexx.py.
"""
import argparse, datetime, ftplib, hashlib, hmac, importlib.util, json, os, posixpath, secrets, subprocess, sys, tarfile, tempfile, time
import urllib.error, urllib.parse, urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
_spec = importlib.util.spec_from_file_location("pg_deploy", os.path.join(HERE, "deploy-wp-theme-vimexx.py"))
deploy = importlib.util.module_from_spec(_spec)
_spec.loader.exec_module(deploy)

ENDPOINT = "/wp-json/prospergenics/v1/ops/db-backup"


# ───────────────────────── database ─────────────────────────

def signature(key, host, t, nonce, dry_run):
    """The same HMAC as prospergenics_2026_backup_signature() in inc/ops-backup.php."""
    msg = f"db-backup\n{host.lower()}\n{int(t)}\n{nonce}\n{'dry-run' if dry_run else 'run'}"
    return hmac.new(key.encode("utf-8"), msg.encode("utf-8"), hashlib.sha256).hexdigest()


def request_dump(site, key, dry_run=False, now=None, opener=None, nonce=None):
    """POST the signed request; the site's JSON answer (name, relative_dir, bytes, sha256, tables, rows)."""
    host = urllib.parse.urlparse(site).hostname or ""
    t = int(now if now is not None else time.time())
    nonce = nonce or secrets.token_hex(16)
    req = urllib.request.Request(
        site.rstrip("/") + ENDPOINT + ("?dry_run=1" if dry_run else ""), data=b"{}", method="POST",
        headers={"Content-Type": "application/json", "User-Agent": "prospergenics-backup",
                 "X-PG-Backup-Time": str(t), "X-PG-Backup-Nonce": nonce,
                 "X-PG-Backup-Signature": signature(key, host, t, nonce, dry_run)})
    try:
        with (opener or urllib.request.urlopen)(req, timeout=900) as r:
            return json.loads(r.read().decode("utf-8"))
    except urllib.error.HTTPError as e:
        try:
            detail = json.loads(e.read().decode("utf-8")).get("message", "")
        except Exception:  # noqa: BLE001
            detail = ""
        if e.code == 404:
            detail = ("the site has no backup endpoint: the theme there predates inc/ops-backup.php, or wp-config.php does "
                      "not define PROSPERGENICS_BACKUP_KEY (32+ characters)")
        raise SystemExit(f"database backup refused by {site}: HTTP {e.code} {detail}")


def remote_dump_path(web_root, answer):
    """FTP path of the dump the site wrote: <site folder next to public_html>/<relative_dir>/<name>."""
    name, rel = answer.get("name", ""), answer.get("relative_dir")
    if not name or "/" in name or not name.startswith("db-") or not name.endswith(".sql.gz"):
        raise SystemExit(f"unexpected dump name from the site: {name!r}")
    if not rel or not deploy.safe_rel(rel):
        raise SystemExit("the site wrote its dump outside its site folder (PROSPERGENICS_BACKUP_DIR?); fetch it by hand")
    return posixpath.join(posixpath.dirname(web_root.rstrip("/")) or ".", rel, name)


def fetch_dump(ftp, remote, answer, out_dir, keep_remote=False):
    """Download the dump, check size and SHA-256, delete it from the host. Returns the local path."""
    os.makedirs(out_dir, exist_ok=True)
    local = os.path.join(out_dir, posixpath.basename(remote))
    with open(local, "wb") as f:
        ftp.retrbinary(f"RETR {remote}", f.write)
    size, digest = os.path.getsize(local), deploy.sha256_file(local)
    if size != int(answer.get("bytes", -1)) or digest != answer.get("sha256"):
        raise SystemExit(f"the downloaded dump does not match what the site wrote ({size} bytes, {digest[:12]}); not using it")
    if not keep_remote:
        try:
            ftp.delete(remote)
        except ftplib.error_perm as e:
            print(f"  warning: could not delete {remote} from the host ({e}); the site keeps the newest 3")
    return local


# ───────────────────────── uploads ─────────────────────────

def walk_sizes(ftp, base):
    """{relative path: size} of every file under base (MLSD); None when base does not exist."""
    out = {}

    def walk(rel):
        path = base if not rel else f"{base}/{rel}"
        for name, facts in ftp.mlsd(path, facts=["type", "size"]):
            if name in (".", "..") or facts.get("type") in ("cdir", "pdir"):
                continue
            child = name if not rel else f"{rel}/{name}"
            if facts.get("type") == "dir":
                walk(child)
            elif deploy.safe_rel(child):
                out[child] = int(facts.get("size") or 0)
    try:
        walk("")
    except ftplib.error_perm as e:
        if str(e).startswith("550"):
            return None
        raise
    return out


def fetch_uploads(ftp, web_root, out_dir, stamp, reconnect=None):
    """Download wp-content/uploads/ and pack it into uploads-<stamp>.tar.gz. Returns (path, files, bytes)."""
    base = f"{web_root.rstrip('/')}/wp-content/uploads"
    files = walk_sizes(ftp, base)
    if files is None:
        raise SystemExit(f"{base} does not exist on the FTP server (check PG_FTP_ROOT)")
    os.makedirs(out_dir, exist_ok=True)
    tar_path = os.path.join(out_dir, f"uploads-{stamp}.tar.gz")
    with tempfile.TemporaryDirectory() as tmp:
        for i, rel in enumerate(sorted(files), 1):
            local = os.path.join(tmp, "uploads", *rel.split("/"))
            os.makedirs(os.path.dirname(local), exist_ok=True)
            for attempt in range(4):  # web0168 now and then drops the connection mid-run
                try:
                    with open(local, "wb") as f:
                        ftp.retrbinary(f"RETR {base}/{rel}", f.write)
                    break
                except (EOFError, OSError, ftplib.error_temp) as e:
                    if attempt == 3 or reconnect is None:
                        raise
                    print(f"  connection dropped ({e!r}), reconnecting")
                    time.sleep(2)
                    ftp = reconnect()
            if i % 200 == 0:
                print(f"  {i}/{len(files)} files...")
        with tarfile.open(tar_path, "w:gz") as tar:
            tar.add(os.path.join(tmp, "uploads"), arcname="uploads")
    return tar_path, len(files), sum(files.values())


# ───────────────────────── encryption ─────────────────────────

def encrypt(paths, public_key_file):
    """Encrypt each file for the key (GnuPG, a throwaway keyring), remove the plain file; the .gpg paths."""
    out = []
    with tempfile.TemporaryDirectory() as home:
        env = dict(os.environ, GNUPGHOME=home)
        for p in paths:
            r = subprocess.run(["gpg", "--batch", "--yes", "--quiet", "--trust-model", "always", "--recipient-file",
                                public_key_file, "--output", p + ".gpg", "--encrypt", p], capture_output=True, text=True, env=env)
            if r.returncode != 0 or not os.path.isfile(p + ".gpg"):
                raise SystemExit(f"could not encrypt {os.path.basename(p)}: {r.stderr.strip()}")
            os.remove(p)
            out.append(p + ".gpg")
    return out


def main():
    ap = argparse.ArgumentParser(description="Back up a site's database and uploads (cards #7232, #6473).")
    ap.add_argument("--site", required=True, help="the site's URL, e.g. https://prospergenics.com")
    ap.add_argument("--db", action="store_true", help="database dump via the site's signed backup endpoint")
    ap.add_argument("--uploads", action="store_true", help="wp-content/uploads/ over FTPS")
    ap.add_argument("--out", default=os.path.join(deploy.REPO, ".site-backups"), help="local folder for the backup")
    ap.add_argument("--encrypt-to", help="encrypt every file for this GnuPG public key file and delete the plain copies")
    ap.add_argument("--keep-remote", action="store_true", help="leave the dump on the host after downloading it")
    ap.add_argument("--dry-run", action="store_true", help="check signature, folders and sizes; write nothing")
    args = ap.parse_args()
    if not (args.db or args.uploads):
        ap.error("give --db, --uploads or both")
    host = urllib.parse.urlparse(args.site).hostname or ""
    web_root = (os.environ.get("PG_FTP_ROOT") or f"domains/{host}/public_html").strip("/")
    key = os.environ.get("PG_BACKUP_KEY", "")
    if args.db and len(key) < 32:
        raise SystemExit("PG_BACKUP_KEY is not set (32+ characters, the PROSPERGENICS_BACKUP_KEY of the site's wp-config.php)")
    if args.encrypt_to and not os.path.isfile(args.encrypt_to):
        raise SystemExit(f"--encrypt-to {args.encrypt_to}: no such file")
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    summary = {"site": args.site, "started_at": stamp, "dry_run": args.dry_run}

    answer = None
    if args.db:
        answer = request_dump(args.site, key, args.dry_run)
        if args.dry_run:
            print(f"database: the site accepts the signed request ({answer.get('tables')} tables, folder {answer.get('relative_dir')})")
            summary["db"] = {"dry_run": True, "tables": answer.get("tables")}
        else:
            print(f"database: the site wrote {answer.get('name')} ({answer.get('bytes')} bytes, {answer.get('tables')} tables, "
                  f"{answer.get('rows')} rows, {answer.get('seconds')} s)")
    ftp = deploy.connect() if (args.uploads or (args.db and not args.dry_run)) else None
    if args.db and not args.dry_run:
        remote = remote_dump_path(web_root, answer)
        local = fetch_dump(ftp, remote, answer, os.path.join(args.out, "db"), args.keep_remote)
        print(f"database: downloaded and verified {local}" + ("" if args.keep_remote else f", deleted {remote} from the host"))
        summary["db"] = {k: answer.get(k) for k in ("name", "bytes", "sha256", "tables", "rows", "seconds")}
    if args.uploads:
        if args.dry_run:
            files = walk_sizes(ftp, f"{web_root}/wp-content/uploads")
            if files is None:
                raise SystemExit(f"{web_root}/wp-content/uploads does not exist on the FTP server (check PG_FTP_ROOT)")
            print(f"uploads: would download {len(files)} files, {sum(files.values()) / 1e6:.1f} MB")
            summary["uploads"] = {"dry_run": True, "files": len(files), "bytes": sum(files.values())}
        else:
            def reconnect():
                return deploy.connect()
            path, n, size = fetch_uploads(ftp, web_root, args.out, stamp, reconnect)
            print(f"uploads: {n} files, {size / 1e6:.1f} MB -> {path}")
            summary["uploads"] = {"name": os.path.basename(path), "files": n, "bytes": size, "sha256": deploy.sha256_file(path)}
    if ftp is not None:
        try:
            ftp.quit()
        except Exception:  # noqa: BLE001
            pass
    if not args.dry_run:
        made = [os.path.join(args.out, "db", summary["db"]["name"])] if "db" in summary else []
        made += [os.path.join(args.out, summary["uploads"]["name"])] if "uploads" in summary else []
        if args.encrypt_to:
            encrypt(made, args.encrypt_to)
            summary["encrypted"] = True
            print(f"encrypted {len(made)} file(s) for {os.path.basename(args.encrypt_to)}; the plain copies are deleted")
        os.makedirs(args.out, exist_ok=True)
        with open(os.path.join(args.out, "backup.json"), "w", encoding="utf-8") as f:
            json.dump(summary, f, indent=1)
    print(f"backup {'dry run ' if args.dry_run else ''}done: {args.site}" + ("" if args.dry_run else f" -> {args.out}"))


if __name__ == "__main__":
    main()

#!/usr/bin/env python3
"""
Build the theme + profile/list pages and upload them to the Vimexx hosting
(test.prospergenics.com moved there 2026-10-18; deploy-wp-theme-to-test.py
targeted the old IIS server and is superseded by this script).

    python deploy-wp-theme-vimexx.py                 # backup, upload, prune, manifest
    python deploy-wp-theme-vimexx.py --dry-run       # connect and log what would happen, change nothing
    python deploy-wp-theme-vimexx.py --switch        # upload to a new folder, then switch by rename
    python deploy-wp-theme-vimexx.py --no-build --theme-only --theme-dir <backup>/prospergenics-2026
                                                     # restore a backup taken by an earlier run

Credentials: PG_FTP_USER / PG_FTP_PASSWORD from the environment when set (GitHub Actions),
otherwise the Jengo vault (Martien's machine).

Server: web0168.zxcs.nl (Vimexx DA pakket u63291p434771, FTPS poort 21,
TLS session reuse verplicht). FTP root = DA home, so paths start at domains/.
Uploads:
  wp-theme/prospergenics-2026/  -> public_html/wp-content/themes/prospergenics-2026/
  people/<slug>/index.html      -> public_html/people/<slug>/index.html
  people/index.html             -> public_html/people/index.html (redirect to the people list)
  experts/index.html            -> public_html/experts/index.html
  community/index.html          -> public_html/community/index.html
                                   (relative asset URLs rewritten to the theme assets URL)
  team/<slug>/index.html        -> redirect stub to /people/<slug>/ (the profiles
                                   briefly lived under /team/ on 18-10)

How a theme deploy stays safe (audit ARC-17):
  1. Backup: the remote theme is downloaded to --backup-dir (default .deploy-backups/<UTC time>/,
     CI keeps it as a workflow artifact) before anything is overwritten. Restore it with the
     --theme-dir line above.
  2. Upload, default ("in place"): every file goes up under a temporary name and is renamed over
     the old one, so no request ever reads a half-written PHP file. Order: assets first, then the
     includes deepest-first, then the templates, and the root functions.php last, so the old
     functions.php never requires an inc/ file that is not there yet.
     --switch instead uploads the whole theme to domains/<site>/deploy/incoming-<time>/ (outside
     the web root), then renames the live folder to deploy/prospergenics-2026-prev-<time>/ and
     the new one into place: two renames, one FTP round trip apart. The last three previous
     folders are kept on the server as remote backups. If the host refuses the rename, nothing
     live has changed.
  3. Prune: a manifest of the deployed files (domains/<site>/deploy/prospergenics-2026.manifest.json,
     outside the web root) is written at the end of each deploy. Files listed in the previous
     manifest that are no longer in the build are deleted. Files on the server that no manifest
     ever listed are only logged, unless --prune-unlisted. Nothing outside the theme folder is
     ever deleted.

Testing against a local FTP server: PG_FTP_HOST, PG_FTP_PORT and PG_FTP_ROOT override the host,
port and web root; PG_FTP_PLAIN=1 allows plain FTP, for localhost only.
"""
import argparse, datetime, ftplib, hashlib, io, json, os, posixpath, re, subprocess, sys

HERE   = os.path.dirname(os.path.abspath(__file__))
REPO   = os.path.normpath(os.path.join(HERE, "..", ".."))
THEME  = os.path.join(REPO, "wp-theme", "prospergenics-2026")
PEOPLE = os.path.join(HERE, "people")
LISTS  = ("experts", "community")
VAULT  = "E:/projects/jengo/jengo-system-private/tools/vault-api.py"

VAULT_PROJECT = 6
VAULT_CRED    = 266
FTP_HOST      = os.environ.get("PG_FTP_HOST") or "web0168.zxcs.nl"
FTP_PORT      = int(os.environ.get("PG_FTP_PORT") or 21)
REMOTE_ROOT   = (os.environ.get("PG_FTP_ROOT") or "domains/test.prospergenics.com/public_html").strip("/")
THEME_SLUG    = "prospergenics-2026"
REMOTE_THEMES = f"{REMOTE_ROOT}/wp-content/themes"
REMOTE_THEME  = f"{REMOTE_THEMES}/{THEME_SLUG}"
# Next to public_html, so neither the manifest nor the switch folders are served or seen by WordPress.
DEPLOY_DIR    = f"{posixpath.dirname(REMOTE_ROOT)}/deploy" if "/" in REMOTE_ROOT else "deploy"
MANIFEST      = f"{DEPLOY_DIR}/{THEME_SLUG}.manifest.json"
THEME_ASSETS  = "/wp-content/themes/prospergenics-2026/assets/"
TMP_SUFFIX    = ".deploy-tmp"
KEEP_PREVIOUS = 3
SKIP_NAMES    = {"__pycache__", ".DS_Store", "Thumbs.db"}


class FTPS(ftplib.FTP_TLS):
    """FTPS met TLS-session-reuse (web0168 vereist dit)."""
    def ntransfercmd(self, cmd, rest=None):
        conn, size = ftplib.FTP.ntransfercmd(self, cmd, rest)
        if self._prot_p:
            conn = self.context.wrap_socket(conn, server_hostname=self.host,
                                            session=self.sock.session)
        return conn, size


def vault_cred(project, cred_id, reason):
    r = subprocess.run([sys.executable, VAULT, "credential", str(project), str(cred_id),
                        "--reason", reason], capture_output=True, text=True)
    m = re.search(r"\{.*\}", r.stdout, re.S)
    if not m:
        sys.exit(f"vault gaf geen credential terug:\n{r.stdout}\n{r.stderr}")
    return json.loads(m.group(0))


def ensure_dir(ftp, path):
    parts = path.split("/")
    for i in range(1, len(parts) + 1):
        try:
            ftp.mkd("/".join(parts[:i]))
        except ftplib.error_perm:
            pass


REDIRECT = """<!DOCTYPE html>
<html lang="en"><head><meta charset="utf-8" />
<meta name="robots" content="noindex" />
<meta http-equiv="refresh" content="0; url=/people/{slug}/" />
<link rel="canonical" href="/people/{slug}/" />
<title>Moved · ProsperGenics</title></head>
<body><p>This profile moved to <a href="/people/{slug}/">/people/{slug}/</a>.</p></body></html>
"""


# ───────────────────────── local build ─────────────────────────

def local_files(theme_dir):
    """Relative POSIX paths of every file in the built theme."""
    out = []
    for root, dirs, files in os.walk(theme_dir):
        dirs[:] = sorted(d for d in dirs if d not in SKIP_NAMES)
        for fn in files:
            if fn in SKIP_NAMES or fn.endswith(TMP_SUFFIX):
                continue
            out.append(os.path.relpath(os.path.join(root, fn), theme_dir).replace("\\", "/"))
    return sorted(out)


def upload_order(files):
    """Assets, then nested files deepest first, then root files, root functions.php last."""
    def key(rel):
        depth = rel.count("/")
        if rel == "functions.php":
            group = 3
        elif rel.startswith("assets/"):
            group = 0
        elif depth:
            group = 1
        else:
            group = 2
        return (group, -depth, rel)
    return sorted(files, key=key)


def sha256_file(path):
    h = hashlib.sha256()
    with open(path, "rb") as f:
        for chunk in iter(lambda: f.read(65536), b""):
            h.update(chunk)
    return h.hexdigest()


def safe_rel(rel):
    """A path inside the theme folder: relative, normalised, no '..', no backslashes."""
    if not isinstance(rel, str) or not rel or "\\" in rel or "\0" in rel or rel.startswith("/"):
        return False
    norm = posixpath.normpath(rel)
    return norm == rel and not norm.startswith("../") and ".." not in norm.split("/") and norm != "."


def commit_sha():
    if os.environ.get("GITHUB_SHA"):
        return os.environ["GITHUB_SHA"]
    r = subprocess.run(["git", "-C", REPO, "rev-parse", "HEAD"], capture_output=True, text=True)
    return r.stdout.strip() or "unknown"


# ───────────────────────── remote helpers ─────────────────────────

def walk_remote(ftp, base):
    """Relative paths of all files under base (MLSD). None when the folder does not exist."""
    out = []

    def walk(rel):
        path = base if not rel else f"{base}/{rel}"
        for name, facts in ftp.mlsd(path, facts=["type"]):
            if name in (".", "..") or facts.get("type") in ("cdir", "pdir"):
                continue
            child = name if not rel else f"{rel}/{name}"
            if facts.get("type") == "dir":
                walk(child)
            else:
                out.append(child)
    try:
        walk("")
    except ftplib.error_perm as e:
        if str(e).startswith("550"):
            return None
        raise
    return sorted(out)


def read_remote_json(ftp, path):
    buf = io.BytesIO()
    try:
        ftp.retrbinary(f"RETR {path}", buf.write)
    except ftplib.error_perm:
        return None
    try:
        return json.loads(buf.getvalue().decode("utf-8"))
    except ValueError:
        print(f"  warning: {path} is not valid JSON, ignoring it")
        return None


def download_tree(ftp, remote_base, files, dest):
    for rel in files:
        local = os.path.join(dest, *rel.split("/"))
        os.makedirs(os.path.dirname(local), exist_ok=True)
        with open(local, "wb") as f:
            ftp.retrbinary(f"RETR {remote_base}/{rel}", f.write)


class Uploader:
    """STOR under a temporary name, then rename over the target (falls back to a plain STOR
    when the host does not allow renaming)."""
    def __init__(self, ftp):
        self.ftp = ftp
        self.rename_ok = True
        self.made = set()

    def mkdirs(self, remote_dir):
        if remote_dir not in self.made:
            ensure_dir(self.ftp, remote_dir)
            self.made.add(remote_dir)

    def put(self, data, remote, atomic=True):
        self.mkdirs(posixpath.dirname(remote))
        if not atomic or not self.rename_ok:
            self.ftp.storbinary(f"STOR {remote}", io.BytesIO(data), blocksize=262144)
            return
        tmp = remote + TMP_SUFFIX
        self.ftp.storbinary(f"STOR {tmp}", io.BytesIO(data), blocksize=262144)
        try:
            self.ftp.rename(tmp, remote)
            return
        except ftplib.error_perm as e:
            refused = e
        try:  # renaming works, just not over an existing file: remove the old one first
            self.ftp.rename(tmp, tmp + "2")
            self.ftp.rename(tmp + "2", tmp)
        except ftplib.error_perm:
            print(f"  warning: the server refused the rename ({refused}); uploading in place from now on")
            self.rename_ok = False
            try:
                self.ftp.delete(tmp)
            except ftplib.error_perm:
                pass
            self.ftp.storbinary(f"STOR {remote}", io.BytesIO(data), blocksize=262144)
            return
        self.ftp.delete(remote)
        self.ftp.rename(tmp, remote)


def delete_in_theme(ftp, rel, dry):
    """Delete one file of the live theme folder, and its folders if they end up empty."""
    if not safe_rel(rel):
        print(f"  refusing to delete unsafe path {rel!r}")
        return
    target = f"{REMOTE_THEME}/{rel}"
    if dry:
        print(f"  would delete {target}")
        return
    try:
        ftp.delete(target)
        print(f"  deleted {target}")
    except ftplib.error_perm as e:
        print(f"  could not delete {target}: {e}")
        return
    parent = posixpath.dirname(rel)
    while parent:
        try:
            ftp.rmd(f"{REMOTE_THEME}/{parent}")  # fails (fine) while the folder still has files
        except ftplib.error_perm:
            break
        parent = posixpath.dirname(parent)


def rmtree_remote(ftp, path):
    """Delete a switch-mode folder under DEPLOY_DIR (and nothing else)."""
    name = posixpath.basename(path)
    if posixpath.dirname(path) != DEPLOY_DIR or not re.fullmatch(
            rf"({re.escape(THEME_SLUG)}-prev|incoming)-\d{{8}}T\d{{6}}Z", name):
        raise ValueError(f"refusing to delete {path}")
    for rel in walk_remote(ftp, path) or []:
        ftp.delete(f"{path}/{rel}")
    for rel in sorted(walk_remote_dirs(ftp, path), key=lambda r: -r.count("/")):
        ftp.rmd(f"{path}/{rel}")
    ftp.rmd(path)


def walk_remote_dirs(ftp, base):
    out = []

    def walk(rel):
        for name, facts in ftp.mlsd(base if not rel else f"{base}/{rel}", facts=["type"]):
            if facts.get("type") == "dir" and name not in (".", ".."):
                child = name if not rel else f"{rel}/{name}"
                out.append(child)
                walk(child)
    walk("")
    return out


# ───────────────────────── deploy ─────────────────────────

def plan(ftp, files):
    """What is on the server now, and what a deploy of `files` would delete."""
    remote = walk_remote(ftp, REMOTE_THEME)
    manifest = read_remote_json(ftp, MANIFEST)
    listed = set()
    if manifest and isinstance(manifest.get("files"), dict):
        listed = {p for p in manifest["files"] if safe_rel(p)}
    build = set(files)
    on_server = set(remote or [])
    stale = sorted((listed - build) & on_server) if remote is not None else sorted(listed - build)
    leftovers = sorted(p for p in on_server if p.endswith(TMP_SUFFIX))
    unlisted = sorted(p for p in on_server - build - listed if not p.endswith(TMP_SUFFIX))
    return remote, manifest, stale, leftovers, unlisted


def deploy_theme(ftp, args, theme_dir, files, log_dir):
    remote, manifest, stale, leftovers, unlisted = plan(ftp, files)
    print(f"theme: {len(files)} files in the build, "
          f"{'no theme folder' if remote is None else f'{len(remote)} files'} on the server, "
          f"{'no manifest yet' if manifest is None else 'manifest of ' + str(manifest.get('commit', '?'))[:12]}")
    ordered = upload_order(files)
    print(f"  upload order: {ordered[0]} … {ordered[-1]} ({len(ordered)} files, functions.php last)")
    if manifest is None:
        print("  no manifest on the server yet: nothing is deleted by manifest this time")
    for rel in stale:
        print(f"  stale (in the previous manifest, not in the build): {rel}")
    for rel in unlisted:
        print(f"  not in the build and never in a manifest: {rel}"
              + ("" if args.prune_unlisted else "  (kept; --prune-unlisted deletes it)"))

    # 1. backup
    if remote and not args.no_backup:
        dest = os.path.join(log_dir, THEME_SLUG)
        if args.dry_run:
            print(f"  would back up {len(remote)} remote files to {dest}")
        else:
            download_tree(ftp, REMOTE_THEME, [p for p in remote if not p.endswith(TMP_SUFFIX)], dest)
            if manifest is not None:
                with open(os.path.join(log_dir, "previous-manifest.json"), "w", encoding="utf-8") as f:
                    json.dump(manifest, f, indent=1)
            print(f"  backed up {len(remote)} remote files to {dest}")

    # temp files an interrupted deploy left behind go first, before uploads reuse their names
    for rel in leftovers:
        delete_in_theme(ftp, rel, args.dry_run)
    if args.dry_run:
        for rel in stale + (unlisted if args.prune_unlisted else []):
            delete_in_theme(ftp, rel, True)
        print("  dry run: nothing uploaded, renamed or deleted")
        return

    data = {}
    for rel in files:
        with open(os.path.join(theme_dir, *rel.split("/")), "rb") as f:
            data[rel] = f.read()
    up = Uploader(ftp)
    if args.switch:
        switch_theme(ftp, up, ordered, data, remote is not None)
    else:
        # 2. upload in place
        for i, rel in enumerate(ordered, 1):
            up.put(data[rel], f"{REMOTE_THEME}/{rel}")
            if i % 25 == 0:
                print(f"  {i} files...")
        # 3. prune
        for rel in stale + (unlisted if args.prune_unlisted else []):
            delete_in_theme(ftp, rel, False)

    # 4. manifest, last: only a complete deploy is recorded
    new_manifest = {
        "theme": THEME_SLUG,
        "commit": commit_sha(),
        "deployed_at": datetime.datetime.now(datetime.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "mode": "switch" if args.switch else "in-place",
        "files": {rel: hashlib.sha256(data[rel]).hexdigest() for rel in files},
    }
    up.put(json.dumps(new_manifest, indent=1).encode("utf-8"), MANIFEST)
    print(f"  theme deployed ({len(files)} files), manifest written to {MANIFEST}")


def switch_theme(ftp, up, ordered, data, live_exists):
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    incoming = f"{DEPLOY_DIR}/incoming-{stamp}"
    prev = f"{DEPLOY_DIR}/{THEME_SLUG}-prev-{stamp}"
    print(f"  uploading to {incoming}")
    for rel in ordered:
        up.put(data[rel], f"{incoming}/{rel}", atomic=False)  # not live yet
    if live_exists:
        try:
            ftp.rename(REMOTE_THEME, prev)
        except ftplib.error_perm as e:
            rmtree_remote(ftp, incoming)
            sys.exit(f"the server refused to rename the theme folder ({e}); nothing live changed. "
                     "Deploy without --switch.")
    try:
        ftp.rename(incoming, REMOTE_THEME)
    except ftplib.error_perm as e:
        if live_exists:
            ftp.rename(prev, REMOTE_THEME)
        sys.exit(f"could not move the new theme into place ({e}); the previous theme is back.")
    print(f"  switched: previous theme kept at {prev}")
    previous = sorted(n for n, f in ftp.mlsd(DEPLOY_DIR, facts=["type"])
                      if f.get("type") == "dir" and n.startswith(f"{THEME_SLUG}-prev-"))
    for name in previous[:-KEEP_PREVIOUS]:
        rmtree_remote(ftp, f"{DEPLOY_DIR}/{name}")
        print(f"  removed old remote backup {DEPLOY_DIR}/{name}")


def deploy_static(ftp, dry):
    """Personal profile pages and the list pages (static, outside the theme)."""
    pages = []
    for slug in sorted(os.listdir(PEOPLE)):
        page = os.path.join(PEOPLE, slug, "index.html")
        if not os.path.isfile(page):
            continue
        with open(page, encoding="utf-8") as f:
            html = f.read().replace("../../assets/", THEME_ASSETS)
        pages.append((f"{REMOTE_ROOT}/people/{slug}/index.html", html))
        # the profiles briefly lived under /team/<slug>/ (18-10): leave a redirect
        pages.append((f"{REMOTE_ROOT}/team/{slug}/index.html", REDIRECT.format(slug=slug)))
    # /people/ itself: a redirect to the people list (build-profiles.py), so it is no 403
    with open(os.path.join(PEOPLE, "index.html"), encoding="utf-8") as f:
        pages.append((f"{REMOTE_ROOT}/people/index.html", f.read()))
    for folder in LISTS:
        with open(os.path.join(HERE, folder, "index.html"), encoding="utf-8") as f:
            html = f.read().replace("../assets/", THEME_ASSETS)
        pages.append((f"{REMOTE_ROOT}/{folder}/index.html", html))
    if dry:
        print(f"static pages: would upload {len(pages)} files (people/, team/, experts/, community/)")
        return 0
    up = Uploader(ftp)
    for remote, html in pages:
        up.put(html.encode("utf-8"), remote)
    print(f"static pages: {len(pages)} files uploaded")
    return len(pages)


def connect():
    # CI (GitHub Actions, .github/workflows/deploy-test.yml) passes the FTPS login as
    # environment variables from repository secrets; on Martien's machine it comes from the vault.
    if os.environ.get("PG_FTP_USER") and os.environ.get("PG_FTP_PASSWORD"):
        cred = {"username": os.environ["PG_FTP_USER"], "password": os.environ["PG_FTP_PASSWORD"]}
    else:
        cred = vault_cred(VAULT_PROJECT, VAULT_CRED,
                          "deploy prospergenics-2026 thema + profielpagina's naar test.prospergenics.com")
    if os.environ.get("PG_FTP_PLAIN") == "1":
        if FTP_HOST not in ("127.0.0.1", "localhost", "::1"):
            sys.exit("PG_FTP_PLAIN is for a local test server only")
        ftp = ftplib.FTP()
        ftp.connect(FTP_HOST, FTP_PORT, timeout=60)
        ftp.login(cred["username"], cred["password"])
    else:
        ftp = FTPS()
        ftp.connect(FTP_HOST, FTP_PORT, timeout=60)
        ftp.login(cred["username"], cred["password"])
        ftp.prot_p()
    return ftp


def main():
    ap = argparse.ArgumentParser(description="Deploy the prospergenics-2026 theme and static pages to test.prospergenics.com.")
    ap.add_argument("--dry-run", action="store_true", help="connect and log the plan (uploads, backups, deletions); change nothing")
    ap.add_argument("--switch", action="store_true", help="upload to a new folder and switch by rename (see the docstring)")
    ap.add_argument("--no-build", action="store_true", help="skip build-profiles.py and build-wp-theme.py")
    ap.add_argument("--theme-dir", default=THEME, help="theme folder to deploy (default: the built wp-theme/prospergenics-2026; a backup folder to restore)")
    ap.add_argument("--theme-only", action="store_true", help="skip the static people/team/experts/community pages")
    ap.add_argument("--backup-dir", default=os.environ.get("PG_DEPLOY_BACKUP_DIR") or os.path.join(REPO, ".deploy-backups"),
                    help="where the remote theme is downloaded before it is overwritten")
    ap.add_argument("--no-backup", action="store_true", help="skip the backup download")
    ap.add_argument("--prune-unlisted", action="store_true", help="also delete theme files on the server that are not in the build and were never in a manifest")
    args = ap.parse_args()

    if not args.no_build:
        subprocess.run([sys.executable, os.path.join(PEOPLE, "build-profiles.py")], check=True)
        subprocess.run([sys.executable, os.path.join(HERE, "build-wp-theme.py")], check=True)

    theme_dir = os.path.abspath(args.theme_dir)
    files = local_files(theme_dir)
    if "functions.php" not in files or "style.css" not in files:
        sys.exit(f"{theme_dir} does not look like the theme (no functions.php/style.css)")
    stamp = datetime.datetime.now(datetime.timezone.utc).strftime("%Y%m%dT%H%M%SZ")
    log_dir = os.path.join(args.backup_dir, stamp)

    ftp = connect()
    print(f"connected to {FTP_HOST}:{FTP_PORT}, theme folder {REMOTE_THEME}" + (" (dry run)" if args.dry_run else ""))
    deploy_theme(ftp, args, theme_dir, files, log_dir)
    count = len(files)
    if not args.theme_only:
        count += deploy_static(ftp, args.dry_run)
    ftp.quit()
    print(f"deploy {'dry run ' if args.dry_run else ''}klaar: {count} bestanden naar {FTP_HOST}")


if __name__ == "__main__":
    main()

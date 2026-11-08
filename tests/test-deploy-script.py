#!/usr/bin/env python3
"""Unit tests for design/new-hero-2026-10/deploy-wp-theme-vimexx.py (audit ARC-17): the upload
order (functions.php last), the path guard that keeps deletions inside the theme folder, and the
pruning plan. No FTP server needed: the remote side is a fake. Exits non-zero on failure.

    python3 tests/test-deploy-script.py
"""
import importlib.util, os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
spec = importlib.util.spec_from_file_location(
    "deploy", os.path.join(HERE, "..", "design", "new-hero-2026-10", "deploy-wp-theme-vimexx.py"))
deploy = importlib.util.module_from_spec(spec)
spec.loader.exec_module(deploy)

fails = 0


def ok(cond, msg):
    global fails
    print(("ok   " if cond else "FAIL ") + msg)
    if not cond:
        fails += 1


# upload order
files = ["functions.php", "style.css", "index.php", "inc/platform-courses.php", "inc/courses/rest.php",
         "assets/app.js", "inc/profiles/data.php", "header.php", "inc/site-chrome/nav.json"]
order = deploy.upload_order(files)
ok(order[-1] == "functions.php", "functions.php is uploaded last")
ok(order[0].startswith("assets/"), "assets go first")
ok(order.index("inc/courses/rest.php") < order.index("inc/platform-courses.php"), "deeper includes before their loader")
ok(max(order.index(f) for f in files if f.startswith("inc/")) < min(order.index(f) for f in ("header.php", "index.php", "style.css")),
   "all includes before the root templates")
ok(sorted(order) == sorted(files), "nothing lost or duplicated")

# path guard
for good in ("functions.php", "inc/courses/rest.php", "assets/fonts/a.woff2", "style.css.deploy-tmp"):
    ok(deploy.safe_rel(good), f"safe: {good}")
for bad in ("", "/etc/passwd", "../wp-config.php", "inc/../../wp-config.php", "inc/./x.php", "..", ".",
            "a\\..\\b", "inc//x.php", None, 5, "x\0.php"):
    ok(not deploy.safe_rel(bad), f"refused: {bad!r}")


# pruning plan against a fake server
class FakeFTP:
    def __init__(self, tree, manifest):
        self.tree, self.manifest = tree, manifest

    def mlsd(self, path, facts=None):
        if not path.startswith(deploy.REMOTE_THEME):
            raise deploy.ftplib.error_perm("550 no")
        rel = path[len(deploy.REMOTE_THEME):].strip("/")
        prefix = rel + "/" if rel else ""
        seen = set()
        for f in self.tree:
            if f.startswith(prefix):
                head = f[len(prefix):].split("/", 1)
                if head[0] in seen:
                    continue
                seen.add(head[0])
                yield head[0], {"type": "dir" if len(head) > 1 else "file"}

    def retrbinary(self, cmd, cb):
        if self.manifest is None:
            raise deploy.ftplib.error_perm("550 no manifest")
        cb(deploy.json.dumps(self.manifest).encode())


build = ["functions.php", "style.css", "inc/a.php"]
remote = ["functions.php", "style.css", "inc/a.php", "inc/old.php", "inc/hand.php", "x.php.deploy-tmp"]
manifest = {"files": {"functions.php": "", "style.css": "", "inc/a.php": "", "inc/old.php": "",
                      "../../wp-config.php": "", "/etc/passwd": "", "inc/gone-already.php": ""}}
_r, _m, stale, leftovers, unlisted = deploy.plan(FakeFTP(remote, manifest), build)
ok(stale == ["inc/old.php"], f"stale = in the previous manifest, on the server, not in the build ({stale})")
ok(leftovers == ["x.php.deploy-tmp"], "leftover temp files found")
ok(unlisted == ["inc/hand.php"], "files no manifest listed are reported separately")
_r, m, stale, _l, unlisted = deploy.plan(FakeFTP(remote, None), build)
ok(m is None and stale == [], "no manifest: nothing is stale")
ok(unlisted == ["inc/hand.php", "inc/old.php"], "no manifest: extra files are only reported")

ok(deploy.DEPLOY_DIR == "domains/test.prospergenics.com/deploy", "manifest and switch folders live outside public_html")
ok(not deploy.MANIFEST.startswith(deploy.REMOTE_ROOT + "/"), "manifest is not web-served")

# /version.json (card #7145): the deployed commit, written last, web-served, never for a restore
class RecFTP:
    def __init__(self): self.stored, self.renamed = {}, []
    def mkd(self, p): pass
    def cwd(self, p): pass
    def mlsd(self, path, facts=None): return iter(())
    def storbinary(self, cmd, fp, blocksize=0): self.stored[cmd[5:]] = fp.read()
    def rename(self, a, b): self.stored[b] = self.stored.pop(a); self.renamed.append(b)
    def delete(self, p): self.stored.pop(p, None)

os.environ.update({"GITHUB_SHA": "a" * 40, "GITHUB_REF_NAME": "design/new-hero-2026-10", "GITHUB_RUN_ID": "42", "GITHUB_REPOSITORY": "o/r"})
info = deploy.version_info(deploy.datetime.datetime(2026, 11, 5, 18, 0, tzinfo=deploy.datetime.timezone.utc))
ok(info["commit"] == info["version"] == "a" * 40 and info["branch"] == "design/new-hero-2026-10", "version.json names the deployed commit and branch")
ok(info["deployed_at"] == "2026-11-05T18:00:00Z" and info["run"] == "https://github.com/o/r/actions/runs/42", "with the deploy time and the workflow run")
ok(deploy.VERSION_FILE == deploy.REMOTE_ROOT + "/version.json", "served at /version.json (inside public_html)")
rec = RecFTP()
try:
    deploy.ensure_dir = lambda ftp, path: None
    deploy.publish_version(rec, False)
    written = deploy.json.loads(rec.stored[deploy.VERSION_FILE].decode())
    ok(written["commit"] == "a" * 40 and deploy.VERSION_FILE in rec.renamed, "written atomically (temp file, then rename)")
    deploy.publish_version(rec, False, restored=True)
    ok(deploy.json.loads(rec.stored[deploy.VERSION_FILE].decode())["commit"] == "restored-backup", "a restore from a backup does not claim the checkout's commit")
    before = dict(rec.stored)
    deploy.publish_version(rec, True)
    ok(rec.stored == before, "a dry run writes nothing")
except Exception as e:  # noqa: BLE001
    ok(False, f"publish_version raised {e!r}")
src = open(deploy.__file__).read()
ok(src.index("publish_version(ftp, args.dry_run") > src.index("deploy_static(ftp, args.dry_run)") and src.index("publish_version(ftp, args.dry_run") < src.index("ftp.quit()\n    print"), "version.json is written after the theme and static pages")
for k in ("GITHUB_SHA", "GITHUB_REF_NAME", "GITHUB_RUN_ID", "GITHUB_REPOSITORY"): os.environ.pop(k, None)

# /people/ (card #7226): Apache serves the real people/ folder before WordPress, so its 301 to the
# members list comes from people/.htaccess, uploaded with the static pages; index.html stays as the
# fallback.
rec = RecFTP()
try:
    deploy.deploy_static(rec, False)
    root = deploy.REMOTE_ROOT + "/people/"
    hta = rec.stored.get(root + ".htaccess", b"").decode()
    ok("RedirectMatch 301 ^/people(/|/index\\.html)?$ /members/" in hta, "the static deploy uploads people/.htaccess with the 301 to /members/")
    ok(root + "index.html" in rec.stored and b'http-equiv="refresh"' in rec.stored[root + "index.html"], "and people/index.html, the meta refresh fallback")
    ok(all(root + s + "/index.html" in rec.stored for s in ("martien", "lessy")) and not any(k.startswith(root + ".htaccess/") for k in rec.stored), "the profiles under /people/<slug>/ are uploaded as before")
    # card #6449: Apache serves these folders as files, so each gets the security headers
    # WordPress sends, from its .htaccess (static_headers.py, from wp/inc/security-headers.json)
    import static_headers
    want = [f'Header always set {n} "{v}"' for n, v in static_headers.headers(True).items()]
    for folder in ("people", "experts", "community", "team"):
        hta = rec.stored.get(f"{deploy.REMOTE_ROOT}/{folder}/.htaccess", b"").decode()
        ok(all(line in hta for line in want) and "<IfModule mod_headers.c>" in hta and 'Strict-Transport-Security "max-age=31536000" "expr=%{HTTPS} == \'on\'"' in hta,
           f"the static deploy uploads {folder}/.htaccess with every security header ({len(want)})")
    ok("X-Frame-Options" in static_headers.headers(True) and "Content-Security-Policy" in static_headers.headers(True)
       and "Strict-Transport-Security" not in static_headers.headers(False), "the header set: X-Frame-Options, CSP, ...; HSTS only over HTTPS")
    for folder in ("experts", "community"):
        local = open(os.path.join(deploy.HERE, folder, ".htaccess"), encoding="utf-8").read()
        ok(rec.stored[f"{deploy.REMOTE_ROOT}/{folder}/.htaccess"].decode() == local, f"{folder}/.htaccess goes up as committed")
    ok("RedirectMatch" not in rec.stored[deploy.REMOTE_ROOT + "/team/.htaccess"].decode() and "RedirectMatch" not in rec.stored[deploy.REMOTE_ROOT + "/experts/.htaccess"].decode(), "only people/ has a redirect")
    ok(not any(k.endswith(".htaccess") and k.split("/")[-2] not in ("people", "experts", "community", "team") for k in rec.stored), "no .htaccess anywhere else (the site root's stays WordPress')")
except Exception as e:  # noqa: BLE001
    ok(False, f"deploy_static raised {e!r}")

print(f"\n{'all passed' if not fails else str(fails) + ' failed'}")
sys.exit(1 if fails else 0)

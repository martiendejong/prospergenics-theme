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

print(f"\n{'all passed' if not fails else str(fails) + ' failed'}")
sys.exit(1 if fails else 0)

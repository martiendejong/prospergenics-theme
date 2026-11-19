#!/usr/bin/env python3
"""
Tests for the production deploy path (card #7232): the production gate (prod_gate.py), the production
target of deploy-wp-theme-vimexx.py, the database/uploads backup (backup-site.py), the smoke check's
results file and smoke-compare.sh, and the shape of .github/workflows/deploy-production.yml.
No server, no secrets: FTP, HTTP and the GitHub API are fakes. Exits non-zero on failure.

    python3 tests/test-deploy-production.py
"""
import http.server, importlib.util, io, json, os, re, shutil, subprocess, sys, tarfile, tempfile, threading

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DESIGN = os.path.join(ROOT, "design", "new-hero-2026-10")
sys.path.insert(0, DESIGN)


def load(name, file):
    spec = importlib.util.spec_from_file_location(name, os.path.join(DESIGN, file))
    mod = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(mod)
    return mod


for k in ("GITHUB_ACTIONS", "PG_FTP_ROOT", "PG_PRODUCTION_GATE", "GITHUB_SHA"):
    os.environ.pop(k, None)
gate = load("prod_gate", "prod_gate.py")
deploy = load("deploy", "deploy-wp-theme-vimexx.py")
backup = load("backup_site", "backup-site.py")

fails = 0


def ok(cond, msg):
    global fails
    print(("ok   " if cond else "FAIL ") + msg)
    if not cond:
        fails += 1


SHA = "a" * 40
OLD = "b" * 40

print("Production gate")
ok(gate.confirmation_problem("prospergenics.com") == "" and gate.confirmation_problem(" Prospergenics.com ") == "", "typing prospergenics.com confirms (case and spaces ignored)")
ok(gate.confirmation_problem("test.prospergenics.com") != "" and gate.confirmation_problem("") != "", "anything else does not")
green = [{"head_sha": SHA, "status": "completed", "conclusion": "success"}]
ok(gate.ci_problem(green, SHA) == "", "CI green on exactly this commit passes")
ok("has not run" in gate.ci_problem([], SHA), "no CI run on the commit: stop")
ok("still running" in gate.ci_problem([{"head_sha": SHA, "status": "in_progress", "conclusion": None}], SHA), "CI still running: stop")
ok("did not pass" in gate.ci_problem([{"head_sha": SHA, "status": "completed", "conclusion": "failure"}], SHA), "CI failed: stop (f59324f, live since 17 Nov, is such a commit)")
ok("has not run" in gate.ci_problem([{"head_sha": OLD, "status": "completed", "conclusion": "success"}], SHA), "a green run of another commit does not count")
ok(gate.test_problem({"commit": SHA}, [], {}, SHA) == "", "test runs this commit now: on test")
run = {"id": 7, "head_sha": SHA, "event": "push", "conclusion": "success"}
steps = lambda up, smoke: {7: [{"steps": [{"name": gate.UPLOAD_STEP, "conclusion": up}, {"name": gate.SMOKE_STEP, "conclusion": smoke}]}]}
ok(gate.test_problem({"commit": OLD}, [run], steps("success", "success"), SHA) == "", "test moved on, but a push deploy uploaded this commit and passed its smoke check: on test")
ok(gate.test_problem({"commit": OLD}, [run], steps("skipped", "skipped"), SHA) != "", "a deploy run that skipped the upload (no secrets) does not count")
ok(gate.test_problem({"commit": OLD}, [dict(run, event="workflow_dispatch")], steps("success", "success"), SHA) != "", "a dispatched run (maybe a dry run) does not count")
ok(gate.test_problem(None, [], {}, SHA) != "", "no /version.json and no deploy run: stop")
ok(gate.resolve_sha("", {"commit": SHA}) == SHA and gate.resolve_sha(SHA.upper(), None) == SHA, "no --sha: the commit test runs; a given SHA is normalised")
ok(gate.resolve_sha("abc123", None) == "" and gate.resolve_sha("", {"commit": "restored-backup"}) == "", "short SHAs and a restored test site give no commit")
args = dict(confirm="prospergenics.com", sha=SHA, test_version={"commit": SHA}, ci_runs=green, deploy_runs=[], jobs_by_run={}, on_branch=True, older=[], allow_older=False)
ok(gate.problems(**args) == [], "all good: no problems")
ok(any("not part of design/new-hero-2026-10" in p for p in gate.problems(**dict(args, on_branch=False))), "a commit outside the branch: stop")
older = ["production runs bbbb, which is not part of aaaa"]
ok(any("allow_older" in p for p in gate.problems(**dict(args, older=older))), "production newer: stop, naming allow_older")
ok(gate.problems(**dict(args, older=older, allow_older=True)) == [], "allow_older lets a deliberate rollback through")
ok(len(gate.problems(**dict(args, confirm="x", sha=""))) == 2, "no commit and no confirmation: both reported")
text = gate.report({"sha": SHA, "test_version": {"commit": SHA}, "prod_version": None}, ["CI did not pass"])
ok("**STOP**: CI did not pass" in text and SHA in text, "the report names the commit and every stop")

print("Deploy script: production target")
ok(deploy.REMOTE_ROOT == "domains/test.prospergenics.com/public_html", "the default web root is still test's")
ok(deploy.is_production_root("domains/prospergenics.com/public_html") and deploy.is_production_root("/domains/www.prospergenics.com/public_html/"), "prospergenics.com (and www) roots are production")
ok(not deploy.is_production_root("domains/test.prospergenics.com/public_html") and not deploy.is_production_root("public_html"), "test and bare roots are not")
tp = deploy.target_problems
ok(tp("test", deploy.TEST_ROOT) == [], "test target on test: fine")
ok(any("--target production" in p for p in tp("test", deploy.PRODUCTION_ROOT)), "the test command on a production root is refused (no accidental production deploy)")
ok(tp("production", deploy.PRODUCTION_ROOT, "prospergenics.com") == [], "production target, production root, confirmed: fine")
ok(any("--confirm" in p for p in tp("production", deploy.PRODUCTION_ROOT, "")), "production without --confirm prospergenics.com is refused")
ok(any("not prospergenics.com's" in p for p in tp("production", deploy.TEST_ROOT, "prospergenics.com")), "--target production on another root is refused")
ok(any("--no-backup" in p for p in tp("production", deploy.PRODUCTION_ROOT, "prospergenics.com", no_backup=True)), "--no-backup is refused for production")
ok(any("--prune-unlisted" in p for p in tp("production", deploy.PRODUCTION_ROOT, "prospergenics.com", prune_unlisted=True)), "--prune-unlisted is refused for production")
ok(any("uncommitted" in p for p in tp("production", deploy.PRODUCTION_ROOT, "prospergenics.com", dirty=True)), "a dirty working tree is refused for production")
ok(tp("production", deploy.PRODUCTION_ROOT, "prospergenics.com", dirty=True, restoring=True) == [], "restoring a theme backup does not need a clean tree")
deploy.configure(deploy.PRODUCTION_ROOT)
ok(deploy.REMOTE_THEME == "domains/prospergenics.com/public_html/wp-content/themes/prospergenics-2026"
   and deploy.MANIFEST == "domains/prospergenics.com/deploy/prospergenics-2026.manifest.json"
   and deploy.VERSION_FILE == "domains/prospergenics.com/public_html/version.json", "configure() moves the theme, manifest and version.json to production")
deploy.configure(deploy.TEST_ROOT)
ok(deploy.DEPLOY_DIR == "domains/test.prospergenics.com/deploy", "and back")
HEAD = subprocess.run(["git", "-C", ROOT, "rev-parse", "HEAD"], capture_output=True, text=True).stdout.strip()
os.environ.update({"GITHUB_ACTIONS": "true", "GITHUB_SHA": SHA})   # on "Run workflow", GITHUB_SHA is the branch head
os.environ["PG_PRODUCTION_GATE"] = OLD
ok(any("gate did not pass" in p for p in deploy.production_gate("prospergenics.com", False)), "in Actions, a gate result for another commit stops the upload")
os.environ.pop("PG_PRODUCTION_GATE")
ok(any("unset" in p for p in deploy.production_gate("prospergenics.com", False)), "in Actions, no gate result stops the upload")
os.environ.update({"PG_PRODUCTION_GATE": HEAD, "PG_DEPLOY_COMMIT": HEAD})
ok(deploy.production_gate("prospergenics.com", False) == [] and deploy.commit_sha() == HEAD, "the gated commit, checked out, may go, and is the one recorded (not GITHUB_SHA)")
os.environ.update({"PG_PRODUCTION_GATE": SHA, "PG_DEPLOY_COMMIT": SHA})
ok(any("gate did not pass" in p for p in deploy.production_gate("prospergenics.com", False)), "a gate result that is not the checked-out commit stops the upload")
for k in ("GITHUB_ACTIONS", "GITHUB_SHA", "PG_PRODUCTION_GATE", "PG_DEPLOY_COMMIT"):
    os.environ.pop(k, None)
src = open(os.path.join(DESIGN, "deploy-wp-theme-vimexx.py"), encoding="utf-8").read()
ok("deploy_guard.require_not_older(REPO, previous.get(\"commit\")" in src, "production never replaces newer work (server commit from the manifest)")

print("deploy_guard.older_than_server")
import deploy_guard  # noqa: E402


def git(repo, *a):
    return subprocess.run(["git", "-C", repo] + list(a), capture_output=True, text=True,
                          env=dict(os.environ, GIT_AUTHOR_NAME="t", GIT_AUTHOR_EMAIL="t@t", GIT_COMMITTER_NAME="t", GIT_COMMITTER_EMAIL="t@t")).stdout.strip()


with tempfile.TemporaryDirectory() as tmp:
    git(tmp, "init", "-q", "-b", "main")
    for n in ("one", "two"):
        open(os.path.join(tmp, n), "w").write(n)
        git(tmp, "add", n)
        git(tmp, "commit", "-q", "-m", n)
    two = git(tmp, "rev-parse", "HEAD")
    one = git(tmp, "rev-parse", "HEAD~1")
    ok(deploy_guard.older_than_server(tmp, one) == [] and deploy_guard.older_than_server(tmp, two) == [], "server on an older or the same commit: may deploy")
    ok(deploy_guard.older_than_server(tmp, "") == [] and deploy_guard.older_than_server(tmp, "restored-backup") == [], "no record, or a restored backup: may deploy")
    git(tmp, "checkout", "-q", one)
    ok(any("newer work with older" in p for p in deploy_guard.older_than_server(tmp, two)), "server on a newer commit: stop")
    ok(any("does not have" in p for p in deploy_guard.older_than_server(tmp, "1234567890abcdef1234567890abcdef12345678")), "server on an unknown commit: stop")

print("Backup: database")
key = "k" * 40
sig = backup.signature(key, "Prospergenics.com", 1763500000, "ab" * 16, False)
ok(re.fullmatch(r"[0-9a-f]{64}", sig) and sig == backup.signature(key, "prospergenics.com", 1763500000, "ab" * 16, False), "a hex HMAC-SHA256, host case ignored")
ok(sig != backup.signature(key, "prospergenics.com", 1763500000, "ab" * 16, True) and sig != backup.signature(key, "test.prospergenics.com", 1763500000, "ab" * 16, False), "dry run and host are part of it")
seen = {}


class Resp(io.BytesIO):
    def __enter__(self): return self
    def __exit__(self, *a): return False


def opener(req, timeout=0):
    seen["url"], seen["headers"], seen["method"] = req.full_url, {k.lower(): v for k, v in req.header_items()}, req.get_method()
    return Resp(json.dumps({"name": "db-x.sql.gz", "relative_dir": "deploy/db-backups", "bytes": 3, "sha256": "x"}).encode())


answer = backup.request_dump("https://prospergenics.com/", key, False, now=1763500000, opener=opener, nonce="cd" * 16)
h = seen["headers"]
ok(seen["method"] == "POST" and seen["url"] == "https://prospergenics.com/wp-json/prospergenics/v1/ops/db-backup", "POST to the backup endpoint")
ok(h.get("x-pg-backup-time") == "1763500000" and h.get("x-pg-backup-nonce") == "cd" * 16
   and h.get("x-pg-backup-signature") == backup.signature(key, "prospergenics.com", 1763500000, "cd" * 16, False), "with time, nonce and signature headers")
ok("k" * 40 not in json.dumps(h), "the key itself is never sent")
backup.request_dump("https://prospergenics.com", key, True, now=1763500000, opener=opener)
ok(seen["url"].endswith("?dry_run=1") and len(seen["headers"]["x-pg-backup-nonce"]) == 32, "a dry run asks dry_run=1, with a fresh random nonce")


def not_found(req, timeout=0):
    raise backup.urllib.error.HTTPError(req.full_url, 404, "nf", {}, io.BytesIO(b'{"code":"rest_no_route"}'))


try:
    backup.request_dump("https://prospergenics.com", key, opener=not_found)
    ok(False, "a 404 stops the backup")
except SystemExit as e:
    ok("PROSPERGENICS_BACKUP_KEY" in str(e) and "inc/ops-backup.php" in str(e), "a 404 stops the backup and says why (old theme or no key)")
web = "domains/prospergenics.com/public_html"
ok(backup.remote_dump_path(web, {"name": "db-p-1.sql.gz", "relative_dir": "deploy/db-backups"}) == "domains/prospergenics.com/deploy/db-backups/db-p-1.sql.gz", "the dump is fetched from next to public_html")
for bad in ({"name": "../wp-config.php", "relative_dir": "deploy"}, {"name": "db-x.sql.gz", "relative_dir": "../.."}, {"name": "db-x.sql.gz", "relative_dir": None}, {"name": "x.sql.gz", "relative_dir": "deploy"}):
    try:
        backup.remote_dump_path(web, bad)
        ok(False, f"refused: {bad}")
    except SystemExit:
        ok(True, f"refused: {bad}")


class FakeFTP:
    def __init__(self, files):
        self.files, self.deleted = dict(files), []

    def retrbinary(self, cmd, cb):
        path = cmd[5:]
        if path not in self.files:
            raise backup.ftplib.error_perm("550 no")
        cb(self.files[path])

    def delete(self, path):
        self.deleted.append(path)
        self.files.pop(path, None)

    def mlsd(self, path, facts=None):
        prefix = path.rstrip("/") + "/"
        if not any(f.startswith(prefix) for f in self.files):
            raise backup.ftplib.error_perm("550 no such dir")
        seen_names = set()
        for f in sorted(self.files):
            if f.startswith(prefix):
                head = f[len(prefix):].split("/", 1)
                if head[0] not in seen_names:
                    seen_names.add(head[0])
                    yield head[0], {"type": "dir"} if len(head) > 1 else {"type": "file", "size": str(len(self.files[f]))}


import hashlib  # noqa: E402
dump = b"-- dump\nINSERT INTO t VALUES (1);\n"
remote = "domains/prospergenics.com/deploy/db-backups/db-p-1.sql.gz"
good = {"name": "db-p-1.sql.gz", "bytes": len(dump), "sha256": hashlib.sha256(dump).hexdigest()}
with tempfile.TemporaryDirectory() as out:
    ftp = FakeFTP({remote: dump})
    local = backup.fetch_dump(ftp, remote, good, out)
    ok(open(local, "rb").read() == dump and ftp.deleted == [remote], "the dump is downloaded, verified and deleted from the host")
    ftp = FakeFTP({remote: dump})
    backup.fetch_dump(ftp, remote, good, out, keep_remote=True)
    ok(ftp.deleted == [], "--keep-remote leaves it")
    try:
        backup.fetch_dump(FakeFTP({remote: dump + b"x"}), remote, good, out)
        ok(False, "a dump that does not match its hash is refused")
    except SystemExit:
        ok(True, "a dump that does not match its hash is refused")

print("Backup: uploads")
tree = {f"{web}/wp-content/uploads/2026/10/a.webp": b"AAAA", f"{web}/wp-content/uploads/2026/11/b.pdf": b"BB",
        f"{web}/wp-content/uploads/index.php": b"<?php", f"{web}/wp-content/themes/x.php": b"no"}
ok(backup.walk_sizes(FakeFTP(tree), f"{web}/wp-content/uploads") == {"2026/10/a.webp": 4, "2026/11/b.pdf": 2, "index.php": 5}, "every file under uploads/ with its size, nothing else")
ok(backup.walk_sizes(FakeFTP(tree), f"{web}/wp-content/nothing") is None, "a missing folder is reported as missing")
with tempfile.TemporaryDirectory() as out:
    path, n, size = backup.fetch_uploads(FakeFTP(tree), web, out, "20261119T000000Z")
    with tarfile.open(path) as t:
        names = sorted(t.getnames())
        content = t.extractfile("uploads/2026/10/a.webp").read()
    ok(os.path.basename(path) == "uploads-20261119T000000Z.tar.gz" and n == 3 and size == 11, "one uploads-<time>.tar.gz with 3 files, 11 bytes")
    ok("uploads/2026/11/b.pdf" in names and content == b"AAAA" and not any("themes" in x for x in names), "with the files as on the host, and nothing outside uploads/")

print("Backup: encryption")
gpg = shutil.which("gpg")
if not gpg:
    print("skip (no gpg)")
else:
    with tempfile.TemporaryDirectory() as home, tempfile.TemporaryDirectory() as out:
        env = dict(os.environ, GNUPGHOME=home)
        r = subprocess.run([gpg, "--batch", "--pinentry-mode", "loopback", "--passphrase", "", "--quick-gen-key", "pg-test <t@example.org>", "default", "default", "never"], capture_output=True, text=True, env=env)
        pub = os.path.join(out, "pub.asc")
        with open(pub, "w") as f:
            f.write(subprocess.run([gpg, "--armor", "--export", "t@example.org"], capture_output=True, text=True, env=env).stdout)
        if r.returncode != 0 or os.path.getsize(pub) < 100:
            print("skip (gpg cannot make a test key here: " + r.stderr.strip()[-80:] + ")")
        else:
            plain = os.path.join(out, "db-x.sql.gz")
            open(plain, "wb").write(dump)
            enc = backup.encrypt([plain], pub)
            ok(enc == [plain + ".gpg"] and not os.path.exists(plain), "each file is encrypted for the public key and the plain copy deleted")
            dec = subprocess.run([gpg, "--batch", "--pinentry-mode", "loopback", "--passphrase", "", "--decrypt", enc[0]], capture_output=True, env=env)
            ok(dec.returncode == 0 and dec.stdout == dump, "the private key decrypts it to the original")
            ok(dump not in open(enc[0], "rb").read(), "the artifact holds no plain SQL")

print("Smoke check results and smoke-compare.sh")
smoke = os.path.join(ROOT, "tests", "smoke", "smoke-check.sh")
compare = os.path.join(ROOT, "tests", "smoke", "smoke-compare.sh")
ssrc = open(smoke, encoding="utf-8").read()
ok("SMOKE_MEMBER_PROFILE:-/in/martien-de-jong/" in ssrc and "expect_page \"$MEMBER_PROFILE\" 'class=\"pgp-root'" in ssrc, "the smoke check opens a public /in/<member>/ profile (default /in/martien-de-jong/)")


class Site(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        if self.path.startswith("/this-page-does-not-exist"):
            self.send_response(404)
            body = b"not found"
        else:
            self.send_response(200)
            body = b'<html><header class="site-header"></header><div class="pgp-root">How valuable /people/farid/ /people/lessy/</div></html>'
        self.send_header("Content-Type", "text/html")
        self.end_headers()
        self.wfile.write(body)

    def log_message(self, *a):
        pass


srv = http.server.ThreadingHTTPServer(("127.0.0.1", 0), Site)
threading.Thread(target=srv.serve_forever, daemon=True).start()
with tempfile.TemporaryDirectory() as tmp:
    res = os.path.join(tmp, "after.txt")
    r = subprocess.run(["bash", smoke, f"http://127.0.0.1:{srv.server_port}"], capture_output=True, text=True,
                       env=dict(os.environ, SMOKE_RETRY_WAIT="0", SMOKE_RESULTS=res), timeout=120)
    lines = open(res).read().split("\n") if os.path.exists(res) else []
    ok("ok /in/martien-de-jong/" in lines and "ok /" in lines and "ok unknown" in lines, "SMOKE_RESULTS lists the passing checks of the deciding round")
    ok("FAIL /profile/" in lines and "FAIL /members/lessy/" in lines and r.returncode == 1, "and the failing ones (no redirects on the fake site); exit 1")
    lines = [x for x in lines if x]
    ok(len(lines) == len(set(lines)) and len(lines) >= 15, "one line per check (15 checks)")
    r = subprocess.run(["bash", smoke, f"http://127.0.0.1:{srv.server_port}"], capture_output=True, text=True,
                       env=dict(os.environ, SMOKE_RETRY_WAIT="0", SMOKE_RESULTS=res, SMOKE_MEMBER_PROFILE="none"), timeout=120)
    ok("ok /in/martien-de-jong/" not in open(res).read(), "SMOKE_MEMBER_PROFILE=none skips the profile check")
    before, after = os.path.join(tmp, "b.txt"), os.path.join(tmp, "a.txt")

    def cmp(b, a):
        open(before, "w").write(b)
        open(after, "w").write(a)
        return subprocess.run(["bash", compare, before, after], capture_output=True, text=True)
    r = cmp("ok /\nFAIL /members/\n", "ok /\nFAIL /members/\n")
    ok(r.returncode == 0 and "known       /members/" in r.stdout, "a check that already failed before the deploy is known, not a regression")
    r = cmp("ok /\nok /courses/\n", "ok /\nFAIL /courses/\n")
    ok(r.returncode == 1 and "REGRESSION  /courses/" in r.stdout, "a check that passed before and fails after is a regression: exit 1")
    r = cmp("ok /\nok /courses/\n", "ok /\n")
    ok(r.returncode == 1 and "REGRESSION  /courses/" in r.stdout, "a check missing after the deploy counts as failed")
    r = cmp("FAIL /contact/\n", "ok /contact/\n")
    ok(r.returncode == 0 and "fixed" in r.stdout, "a check the deploy fixed is reported as fixed")
    r = cmp("ok /\n", "")
    ok(r.returncode == 1, "no results after the deploy: exit 1")
srv.shutdown()

print("deploy-production.yml")
wf = open(os.path.join(ROOT, ".github", "workflows", "deploy-production.yml"), encoding="utf-8").read()
on = wf[wf.index("\non:"):wf.index("\npermissions:")]
ok("workflow_dispatch:" in on and "push:" not in on and "schedule:" not in on and "pull_request" not in on, "started by hand only (workflow_dispatch)")
ok(re.search(r"confirm:\n\s+description: Type prospergenics.com.*\n\s+type: string\n\s+required: true", wf), "a required confirmation input")
ok(re.search(r"dry_run:\n(?:\s+.*\n){2}\s+default: true", wf), "dry_run is the default")
gate_job = wf[wf.index("\n  gate:"):wf.index("\n  deploy:")]
deploy_job = wf[wf.index("\n  deploy:"):]
ok("secrets." not in gate_job and "prod_gate.py --confirm" in gate_job and "fetch-depth: 0" in gate_job, "the gate job runs prod_gate.py, with the full history and without any secret")
ok("needs: gate" in deploy_job and re.search(r"\n    environment:\n      name: production\n", deploy_job), "the deploy job needs the gate and runs in the GitHub Environment production (the approval)")
order = [m.group(1) for m in re.finditer(r"\n      - name: (.+)", deploy_job)]
pos = {n: i for i, n in enumerate(order)}
up = pos.get("Build and upload", -1)
ok(all(pos.get(n, 99) < up for n in ("Back up the database", "Back up the uploads", "Keep the database backup", "Keep the uploads backup")), "both backups (and their artifacts) come before the upload")
ok(pos.get("Smoke-check production before the upload (baseline)", 99) < up < pos.get("Smoke-check production after the upload", -1), "smoke check before (baseline) and after the upload")
ok(deploy_job.count("retention-days: 30") >= 3 and "production-db-${{ needs.gate.outputs.sha }}" in deploy_job and "production-uploads-${{ needs.gate.outputs.sha }}" in deploy_job, "database, uploads and theme backups are kept 30 days")
ok(deploy_job.count("--encrypt-to \"$RUNNER_TEMP/backup-key.asc\"") == 2 and "BACKUP_GPG_PUBLIC_KEY" in deploy_job, "both backups are encrypted (the repository is public)")
ok("--target production --confirm prospergenics.com" in deploy_job and "PG_PRODUCTION_GATE: ${{ needs.gate.outputs.sha }}" in deploy_job
   and "PG_DEPLOY_COMMIT: ${{ needs.gate.outputs.sha }}" in deploy_job, "the upload uses the production target and the gate's commit (also as the recorded commit)")
ok("ref: ${{ needs.gate.outputs.sha }}" in deploy_job, "the deploy checks out exactly the gated commit")
ok("smoke-compare.sh" in deploy_job and "Deploy to production failing" in deploy_job, "a regression fails the run and opens the alert issue")
ok("cancel-in-progress: false" in wf and "group: deploy-production" in wf, "one production deploy at a time, never cancelled halfway")
try:
    import yaml
    d = yaml.safe_load(wf)
    ok(d["jobs"]["deploy"]["environment"]["name"] == "production" and d["jobs"]["deploy"]["needs"] == "gate", "valid YAML with the same structure")
except ImportError:
    print("skip (no PyYAML)")

print(f"\n{'all passed' if not fails else str(fails) + ' failed'}")
sys.exit(1 if fails else 0)

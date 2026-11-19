#!/usr/bin/env python3
"""
Tests for design/new-hero-2026-10/deploy_guard.py: a deploy must never replace a newer test site with
an older build (4 Nov 2026: the 24 Oct theme overwrote 110 commits of work).

Builds a throwaway git repository with an "origin" that has design/new-hero-2026-10, then checks:
  - a checkout that contains the latest branch, with the server on an older commit, may deploy;
  - an old branch (like develop at 24 Oct) is stopped;
  - a checkout that is current but older than what the server runs is stopped;
  - a server commit this checkout has never seen is stopped;
  - --allow-older lets a deliberate rollback through, and exit code 2 stops the rest.

Run with: python3 tests/test-deploy-guard.py
"""
import io, os, subprocess, sys, tempfile
from contextlib import redirect_stderr

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, os.path.join(ROOT, "design", "new-hero-2026-10"))
import deploy_guard  # noqa: E402

failures = 0


def ok(cond, msg):
    global failures
    print(("  PASS  " if cond else "  FAIL  ") + msg)
    failures += 0 if cond else 1


def git(repo, *args):
    r = subprocess.run(["git", "-C", repo] + list(args), capture_output=True, text=True,
                       env=dict(os.environ, GIT_AUTHOR_NAME="t", GIT_AUTHOR_EMAIL="t@t", GIT_COMMITTER_NAME="t", GIT_COMMITTER_EMAIL="t@t"))
    return r.stdout.strip()


def commit(repo, name):
    with open(os.path.join(repo, name), "w") as f:
        f.write(name)
    git(repo, "add", name)
    git(repo, "commit", "-q", "-m", name)
    return git(repo, "rev-parse", "HEAD")


os.environ.pop("GITHUB_ACTIONS", None)
with tempfile.TemporaryDirectory() as tmp:
    origin = os.path.join(tmp, "origin")
    os.makedirs(origin)
    git(origin, "init", "-q", "-b", "develop")
    old = commit(origin, "oct24")                       # develop / master on 24 Oct
    git(origin, "checkout", "-q", "-b", deploy_guard.BRANCH)
    mid = commit(origin, "oct30")
    new = commit(origin, "nov04")                       # the latest test-site build

    work = os.path.join(tmp, "work")
    subprocess.run(["git", "clone", "-q", origin, work], check=True)

    print("A current checkout")
    git(work, "checkout", "-q", deploy_guard.BRANCH)
    ok(deploy_guard.problems(work, mid) == [], "may deploy over an older server commit")
    ok(deploy_guard.problems(work, new) == [], "may redeploy the same commit")
    ok(deploy_guard.problems(work, "") == [], "may deploy when the server has no record yet")

    print("An old branch (develop at 24 Oct)")
    git(work, "checkout", "-q", "-b", "my-fix", old)
    found = deploy_guard.problems(work, new)
    ok(any("does not contain the latest" in p for p in found), "is stopped: it lacks the latest design/new-hero-2026-10")
    ok(any("replace newer work with older work" in p for p in found), "and would replace what the server runs")

    print("Server runs a commit this checkout has never seen")
    git(work, "checkout", "-q", deploy_guard.BRANCH)
    ok(any("does not have" in p for p in deploy_guard.problems(work, "1234567890abcdef1234567890abcdef12345678")), "is stopped")

    print("require_current")
    git(work, "checkout", "-q", "my-fix")
    err = io.StringIO()
    with redirect_stderr(err):
        try:
            deploy_guard.require_current(work, new)
            stopped = None
        except SystemExit as e:
            stopped = e.code
    ok(stopped == 2 and "Nothing was uploaded" in err.getvalue(), "an old checkout exits with code 2 before anything is uploaded")
    with redirect_stderr(io.StringIO()):
        try:
            deploy_guard.require_current(work, new, allow_older=True)
            passed = True
        except SystemExit:
            passed = False
    ok(passed, "--allow-older lets a deliberate rollback through")

    os.environ["GITHUB_ACTIONS"] = "true"
    os.environ["GITHUB_REF_NAME"] = deploy_guard.BRANCH
    try:
        deploy_guard.require_current(work, new)
        ok(True, "the GitHub deploy of the branch itself is not blocked (shallow checkout)")
    except SystemExit:
        ok(False, "the GitHub deploy of the branch itself is not blocked (shallow checkout)")
    os.environ.pop("GITHUB_ACTIONS"); os.environ.pop("GITHUB_REF_NAME")

print("Wiring")
here = os.path.join(ROOT, "design", "new-hero-2026-10")
vimexx = open(os.path.join(here, "deploy-wp-theme-vimexx.py")).read()
iis = open(os.path.join(here, "deploy-wp-theme-to-test.py")).read()
ok("deploy_guard.require_current(REPO, previous.get(\"commit\")" in vimexx and vimexx.index("require_current") < vimexx.index("    deploy_theme(ftp, args, theme_dir, files, log_dir)"), "the Vimexx deploy checks the manifest's commit before uploading")
ok("deploy_guard.require_current(REPO, previous, allow_older)" in iis and iis.index("require_current(REPO") < iis.index("sftp.put("), "the IIS deploy checks the server's .deployed-commit before uploading")
ok("f.write(deploy_guard.head(REPO))" in iis, "and records the deployed commit afterwards")

print("\n%d FAILED" % failures if failures else "\nAll passed")
sys.exit(1 if failures else 0)

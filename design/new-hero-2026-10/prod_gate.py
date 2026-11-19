#!/usr/bin/env python3
"""
Production gate (card #7232): may this exact commit go to prospergenics.com?

    python3 prod_gate.py --repo martiendejong/prospergenics-theme --confirm prospergenics.com [--sha <sha>] [--allow-older]

It says yes only when all of these hold, and prints why not otherwise (exit 1):
  1. the person typed the production host as confirmation (--confirm prospergenics.com);
  2. the commit is part of design/new-hero-2026-10, the branch test is built from;
  3. CI (.github/workflows/ci.yml: lint, unit tests, generated files, the WordPress role matrix) passed
     on exactly this commit;
  4. the commit is on test already: test.prospergenics.com/version.json names it, or a push-triggered
     "Deploy to test" run for it uploaded it and passed its smoke check;
  5. production does not run newer work: the commit in prospergenics.com/version.json is part of this
     commit's history (--allow-older lets a deliberate rollback through).
Without --sha the commit test runs now is taken.

Used by .github/workflows/deploy-production.yml (job "gate", before the approval) and by
deploy-wp-theme-vimexx.py --target production when it runs by hand. The checks themselves are pure
functions over the GitHub API answers, tested in tests/test-deploy-production.py.
GitHub API: GH_TOKEN or GITHUB_TOKEN from the environment (the workflow's token, actions: read), else `gh`.
"""
import argparse, json, os, re, subprocess, sys, time, urllib.error, urllib.request

sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))
import deploy_guard  # noqa: E402

PRODUCTION_HOST = "prospergenics.com"
PRODUCTION_URL  = "https://prospergenics.com"
TEST_URL        = "https://test.prospergenics.com"
BRANCH          = deploy_guard.BRANCH
CI_WORKFLOW     = "ci.yml"
TEST_WORKFLOW   = "deploy-test.yml"
UPLOAD_STEP     = "Build and upload"
SMOKE_STEP      = "Smoke-check the test site"
SHA_RE          = re.compile(r"^[0-9a-f]{40}$")


# ───────────────────────── pure checks ─────────────────────────

def confirmation_problem(typed, host=PRODUCTION_HOST):
    typed = (typed or "").strip().lower()
    return "" if typed == host else f'type "{host}" as the confirmation (got "{typed}")'


def ci_problem(runs, sha):
    """runs: workflow_runs of ci.yml for head_sha=sha (GitHub API). '' when one completed with success."""
    mine = [r for r in runs if r.get("head_sha") == sha]
    if any(r.get("status") == "completed" and r.get("conclusion") == "success" for r in mine):
        return ""
    if not mine:
        return f"CI has not run on {sha[:12]} (push it to a branch or open a pull request first)"
    if any(r.get("status") != "completed" for r in mine):
        return f"CI is still running on {sha[:12]}; wait for it to pass"
    return f"CI did not pass on {sha[:12]} (" + ", ".join(sorted({str(r.get('conclusion')) for r in mine})) + ")"


def test_problem(test_version, deploy_runs, jobs_by_run, sha):
    """'' when test runs this commit now, or a push-triggered deploy-test run uploaded it and passed its
    smoke check. test_version: test's /version.json (dict or None); deploy_runs: workflow_runs of
    deploy-test.yml for head_sha=sha; jobs_by_run: {run id: jobs of that run}."""
    if isinstance(test_version, dict) and test_version.get("commit") == sha:
        return ""
    for run in deploy_runs:
        if run.get("head_sha") != sha or run.get("event") != "push" or run.get("conclusion") != "success":
            continue
        steps = {s.get("name"): s.get("conclusion") for job in jobs_by_run.get(run.get("id"), []) for s in job.get("steps", [])}
        if steps.get(UPLOAD_STEP) == "success" and steps.get(SMOKE_STEP) == "success":
            return ""
    now = test_version.get("commit", "?")[:12] if isinstance(test_version, dict) else "unknown (no /version.json)"
    return (f"{sha[:12]} has not been on test: test runs {now}, and no push-triggered Deploy to test run "
            f"uploaded {sha[:12]} and passed its smoke check")


def resolve_sha(sha, test_version):
    """The commit to deploy: --sha, else the one test runs now (pure). '' when neither is a full SHA."""
    sha = (sha or "").strip().lower()
    if not sha and isinstance(test_version, dict):
        sha = str(test_version.get("commit") or "").lower()
    return sha if SHA_RE.match(sha) else ""


def problems(confirm, sha, test_version, ci_runs, deploy_runs, jobs_by_run, on_branch, older, allow_older):
    """Every reason the commit may not go to production (pure). on_branch: whether the commit is part of
    the branch; older: deploy_guard.older_than_server() for production's commit."""
    found = [p for p in (confirmation_problem(confirm),) if p]
    if not sha:
        return found + ["no commit to deploy: give a full 40-character SHA, or deploy what test runs (its /version.json had none)"]
    if not on_branch:
        found.append(f"{sha[:12]} is not part of {BRANCH}; production only gets what was merged there")
    for p in (ci_problem(ci_runs, sha), test_problem(test_version, deploy_runs, jobs_by_run, sha)):
        if p:
            found.append(p)
    if older and not allow_older:
        found += [o + " (allow_older for a deliberate rollback)" for o in older]
    return found


# ───────────────────────── fetching ─────────────────────────

def http_json(url, token=None, tries=4):
    """GET url as JSON; None on 404. Retries a 429/5xx (Cloudflare now and then answers 429)."""
    headers = {"Accept": "application/vnd.github+json" if "api.github.com" in url else "application/json",
               "User-Agent": "prospergenics-prod-gate"}
    if token:
        headers["Authorization"] = "Bearer " + token
    for attempt in range(tries):
        try:
            with urllib.request.urlopen(urllib.request.Request(url, headers=headers), timeout=30) as r:
                return json.loads(r.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            if e.code == 404:
                return None
            if e.code not in (429, 500, 502, 503, 504) or attempt == tries - 1:
                raise
        except (urllib.error.URLError, TimeoutError):
            if attempt == tries - 1:
                raise
        time.sleep(5 * (attempt + 1))
    return None


def github(path, token):
    if token:
        return http_json("https://api.github.com/" + path.lstrip("/"), token)
    r = subprocess.run(["gh", "api", path], capture_output=True, text=True)  # by hand: the gh login
    if r.returncode != 0:
        raise RuntimeError(f"GitHub API {path} failed ({r.stderr.strip() or 'no GH_TOKEN and no gh login'})")
    return json.loads(r.stdout)


def version(url):
    try:
        return http_json(url.rstrip("/") + "/version.json?gate=" + str(int(time.time())))
    except Exception as e:  # noqa: BLE001
        print(f"warning: {url}/version.json: {e}", file=sys.stderr)
        return None


def gather(repo, sha, token, test_url=TEST_URL, prod_url=PRODUCTION_URL, git_repo=None):
    """Everything problems() needs, fetched from GitHub, test and production."""
    test_version = version(test_url)
    sha = resolve_sha(sha, test_version)
    prod_version = version(prod_url)
    data = {"sha": sha, "test_version": test_version, "prod_version": prod_version,
            "ci_runs": [], "deploy_runs": [], "jobs_by_run": {}, "on_branch": False, "older": []}
    if not sha:
        return data
    data["ci_runs"] = (github(f"repos/{repo}/actions/workflows/{CI_WORKFLOW}/runs?head_sha={sha}&per_page=30", token) or {}).get("workflow_runs", [])
    if not (isinstance(test_version, dict) and test_version.get("commit") == sha):
        runs = (github(f"repos/{repo}/actions/workflows/{TEST_WORKFLOW}/runs?head_sha={sha}&event=push&per_page=10", token) or {}).get("workflow_runs", [])
        data["deploy_runs"] = runs
        for run in runs[:5]:
            data["jobs_by_run"][run.get("id")] = (github(f"repos/{repo}/actions/runs/{run.get('id')}/jobs", token) or {}).get("jobs", [])
    git_repo = git_repo or deploy_guard_repo()
    subprocess.run(["git", "-C", git_repo, "fetch", "--quiet", "origin", BRANCH], capture_output=True)
    data["on_branch"] = deploy_guard.is_ancestor(git_repo, sha, "origin/" + BRANCH)
    prod_commit = prod_version.get("commit") if isinstance(prod_version, dict) else ""
    if prod_commit:
        # the server-side check, against the commit being deployed rather than the checkout's HEAD
        data["older"] = [] if deploy_guard.is_ancestor(git_repo, prod_commit, sha) else [
            f"production runs {prod_commit[:12]}, which is not part of {sha[:12]}: deploying would replace newer work with older work"]
    return data


def deploy_guard_repo():
    return os.path.normpath(os.path.join(os.path.dirname(os.path.abspath(__file__)), "..", ".."))


def report(data, found):
    lines = ["## Production gate", "",
             f"- commit: `{data['sha'] or '?'}`",
             f"- test runs: `{(data['test_version'] or {}).get('commit', '?') if isinstance(data['test_version'], dict) else '?'}`",
             f"- production runs: `{(data['prod_version'] or {}).get('commit', '?') if isinstance(data['prod_version'], dict) else '?'}`", ""]
    lines += [f"- **STOP**: {p}" for p in found] if found else ["All checks passed: CI is green on this commit, test has it, production is not newer."]
    return "\n".join(lines) + "\n"


def main():
    ap = argparse.ArgumentParser(description="May this commit go to production? (card #7232)")
    ap.add_argument("--repo", default=os.environ.get("GITHUB_REPOSITORY", "martiendejong/prospergenics-theme"))
    ap.add_argument("--sha", default="", help="commit to deploy (default: the one test runs now)")
    ap.add_argument("--confirm", default="", help=f'must be "{PRODUCTION_HOST}"')
    ap.add_argument("--allow-older", action="store_true", help="a deliberate rollback to older work")
    ap.add_argument("--test-url", default=TEST_URL)
    ap.add_argument("--prod-url", default=PRODUCTION_URL)
    args = ap.parse_args()
    token = os.environ.get("GH_TOKEN") or os.environ.get("GITHUB_TOKEN")
    data = gather(args.repo, args.sha, token, args.test_url, args.prod_url)
    found = problems(args.confirm, data["sha"], data["test_version"], data["ci_runs"], data["deploy_runs"],
                     data["jobs_by_run"], data["on_branch"], data["older"], args.allow_older)
    text = report(data, found)
    print(text)
    if os.environ.get("GITHUB_STEP_SUMMARY"):
        with open(os.environ["GITHUB_STEP_SUMMARY"], "a", encoding="utf-8") as f:
            f.write(text)
    if os.environ.get("GITHUB_OUTPUT") and data["sha"]:
        with open(os.environ["GITHUB_OUTPUT"], "a", encoding="utf-8") as f:
            f.write(f"sha={data['sha']}\n")
    sys.exit(1 if found else 0)


if __name__ == "__main__":
    main()

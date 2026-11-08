#!/usr/bin/env python3
"""
Shared safety check for the deploy scripts (deploy-wp-theme-vimexx.py, deploy-wp-theme-to-test.py).

On 4 Nov 2026 test.prospergenics.com was overwritten with the theme of 24 Oct: a deploy ran from a
branch (develop / fix/hero-copy-and-mobile-launcher) that did not contain the 110 commits of
design/new-hero-2026-10, so every platform feature and the static people pages disappeared.

Before uploading, a deploy now checks two things and stops when either fails:
  1. the local checkout contains the latest origin/design/new-hero-2026-10 (the branch the test
     site is built from), so nobody deploys an old branch by accident;
  2. the commit already on the server (recorded by the previous deploy) is part of what is being
     deployed, so a deploy never replaces a newer site with an older one.
--allow-older skips both, for a deliberate rollback.
"""
import os
import subprocess
import sys

BRANCH = "design/new-hero-2026-10"


def _git(repo, *args):
    return subprocess.run(["git", "-C", repo] + list(args), capture_output=True, text=True)


def is_ancestor(repo, older, newer="HEAD"):
    """True when `older` is part of `newer`'s history (or the same commit)."""
    return _git(repo, "merge-base", "--is-ancestor", older, newer).returncode == 0


def head(repo):
    return _git(repo, "rev-parse", "HEAD").stdout.strip()


def problems(repo, remote_commit=None, branch=BRANCH, fetch=True):
    """The reasons this checkout must not be deployed; empty when it is safe."""
    found = []
    if fetch and not os.environ.get("GITHUB_ACTIONS"):
        _git(repo, "fetch", "--quiet", "origin", branch)
    ref = "origin/" + branch
    if _git(repo, "rev-parse", "--verify", "--quiet", ref).returncode == 0:
        if not is_ancestor(repo, ref):
            found.append(
                f"this checkout ({head(repo)[:12]}) does not contain the latest {ref}. The test site is built "
                f"from {branch}: merge it into your branch first (git merge {ref}), or open a PR against it.")
    else:
        found.append(f"{ref} is unknown here (git fetch origin {branch} failed?); cannot tell whether this checkout is current.")
    remote_commit = (remote_commit or "").strip()
    if remote_commit and remote_commit not in ("unknown", "?"):
        known = _git(repo, "cat-file", "-e", remote_commit + "^{commit}").returncode == 0
        if not known:
            found.append(f"the server runs commit {remote_commit[:12]}, which this checkout does not have: it is newer "
                         f"than or unrelated to what you are deploying. Fetch and merge first.")
        elif not is_ancestor(repo, remote_commit):
            found.append(f"the server runs commit {remote_commit[:12]}, which is not part of this checkout "
                         f"({head(repo)[:12]}): deploying would replace newer work with older work.")
    return found


def require_current(repo, remote_commit=None, allow_older=False, branch=BRANCH):
    """Stop the deploy (exit 2) unless the checkout is current, or allow_older was given."""
    # The GitHub workflow only deploys a push to `branch` itself, from a shallow checkout without
    # the history these checks need; by construction that is the newest build.
    if os.environ.get("GITHUB_ACTIONS") and os.environ.get("GITHUB_REF_NAME") == branch:
        return
    found = problems(repo, remote_commit, branch)
    if not found:
        return
    for p in found:
        print("STOP: " + p, file=sys.stderr)
    if allow_older:
        print("--allow-older given: deploying anyway (deliberate rollback).", file=sys.stderr)
        return
    print("Nothing was uploaded. To roll back on purpose, run again with --allow-older.", file=sys.stderr)
    sys.exit(2)

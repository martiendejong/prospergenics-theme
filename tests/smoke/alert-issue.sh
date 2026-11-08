#!/usr/bin/env bash
# Alert issue (card 6473): a failing uptime or deploy smoke check opens a GitHub issue, so every
# repository watcher gets a notification (email, app), not only the person who last touched the
# workflow. Uses the workflow's own GITHUB_TOKEN (permissions: issues: write); no extra secrets.
#
#   bash tests/smoke/alert-issue.sh open  "<title>" <details-file>   # open, or comment on the open one
#   bash tests/smoke/alert-issue.sh close "<title>"                  # comment "recovered" and close it
#
# One issue per title stays open while the problem lasts: later failures add a comment instead
# of a new issue. The repository variable ALERT_ASSIGNEES (comma-separated GitHub logins,
# Settings -> Secrets and variables -> Actions -> Variables) is assigned on a new issue, which
# notifies those people even when they do not watch the repository.
set -euo pipefail

action="${1:?open or close}"
title="${2:?issue title}"
details="${3:-}"
label="alert"
run_url="${GITHUB_SERVER_URL:-https://github.com}/${GITHUB_REPOSITORY:-}/actions/runs/${GITHUB_RUN_ID:-}"
now="$(date -u '+%Y-%m-%d %H:%M UTC')"

# The open alert issue with exactly this title, if any.
existing="$(gh issue list --state open --label "$label" --limit 50 --json number,title \
    --jq ".[] | select(.title == \"${title//\"/\\\"}\") | .number" | head -n 1)"

case "$action" in
open)
    body="$(mktemp)"
    trap 'rm -f "$body"' EXIT
    {
        echo "**$now**: the check failed. Run: $run_url"
        echo
        if [ -n "$details" ] && [ -s "$details" ]; then
            echo '```'
            tail -n 40 "$details"
            echo '```'
            echo
        fi
        echo "What to do: docs/OPERATIONS.md, \"When an alert arrives\". This issue closes itself when the check passes again."
    } > "$body"
    if [ -n "$existing" ]; then
        gh issue comment "$existing" --body-file "$body"
        echo "commented on alert issue #$existing"
    else
        gh label create "$label" --color B60205 --description "Opened by a failing uptime or deploy check" 2>/dev/null || true
        args=(--title "$title" --label "$label" --body-file "$body")
        # A mistyped login in ALERT_ASSIGNEES must not swallow the alert: retry without it.
        if [ -n "${ALERT_ASSIGNEES:-}" ]; then
            gh issue create "${args[@]}" --assignee "$ALERT_ASSIGNEES" || gh issue create "${args[@]}"
        else
            gh issue create "${args[@]}"
        fi
    fi
    ;;
close)
    if [ -n "$existing" ]; then
        gh issue close "$existing" --comment "**$now**: the check passes again. Run: $run_url"
        echo "closed alert issue #$existing"
    fi
    ;;
*)
    echo "usage: $0 open|close <title> [details-file]" >&2
    exit 2
    ;;
esac

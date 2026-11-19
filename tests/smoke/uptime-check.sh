#!/usr/bin/env bash
# Uptime check (card 6473): the key pages answer 200 or 302 and show no PHP error. Run every
# 30 minutes by .github/workflows/uptime.yml; a failed run is GitHub's alert. By hand:
#
#   bash tests/smoke/uptime-check.sh                       # https://test.prospergenics.com
#   bash tests/smoke/uptime-check.sh https://prospergenics.com
#
# Each URL gets up to 3 tries 20 s apart, so one slow response does not page anyone.
set -uo pipefail

BASE="${1:-https://test.prospergenics.com}"
BASE="${BASE%/}"
PATHS=(/ /courses/ /articles/ /wp-login.php)
BODY="$(mktemp)"
trap 'rm -f "$BODY"' EXIT
fails=0

for path in "${PATHS[@]}"; do
    problem=""
    for attempt in 1 2 3; do
        code="$(curl -sS --max-time 30 -o "$BODY" -w '%{http_code}' -A 'ProsperGenics uptime check (GitHub Actions)' "$BASE$path" 2>/dev/null)" || code="000"
        problem=""
        if [ "$code" != "200" ] && [ "$code" != "302" ]; then
            problem="HTTP $code"
        elif grep -qiE 'Fatal error|Parse error|There has been a critical error|Uncaught (Error|Exception)|Error establishing a database connection' "$BODY"; then
            problem="HTTP $code with a PHP/database error on the page"
        fi
        [ -z "$problem" ] && break
        [ "$attempt" -lt 3 ] && sleep "${UPTIME_RETRY_SECONDS:-20}"
    done
    if [ -z "$problem" ]; then
        echo "ok    $path $code"
    else
        echo "FAIL  $path $problem (3 tries)"
        echo "::error::$BASE$path is down: $problem"
        fails=$((fails + 1))
    fi
done
[ "$fails" -eq 0 ]

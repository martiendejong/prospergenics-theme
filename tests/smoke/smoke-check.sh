#!/usr/bin/env bash
# Post-deploy smoke check (audit ARC-17): the static pages, the platform routes and the shared
# header, against a live site. Read-only GET requests. Run by .github/workflows/deploy-test.yml
# after every upload; by hand:
#
#   bash tests/smoke/smoke-check.sh                       # https://test.prospergenics.com
#   bash tests/smoke/smoke-check.sh http://localhost:8096
#
# Prints one line per check and exits 1 when any fails. When a check fails, it shows what the host
# actually served (status, type, page title), waits SMOKE_RETRY_WAIT seconds (default 45) and runs
# every check once more; only that second round decides. A real breakage fails both rounds; a
# moment where the host served another page for every URL (31 Oct, right after three deploys in a
# row) does not fail the deploy, but stays visible as a warning in the log.
set -uo pipefail

BASE="${1:-https://test.prospergenics.com}"
BASE="${BASE%/}"
BODY="$(mktemp)"
trap 'rm -f "$BODY"' EXIT
fails=0
ROUND=1
SHOWN=0
WAIT="${SMOKE_RETRY_WAIT:-45}"

# In the first round a failure is a warning plus what the host served; in the last round an error.
fail() {
    fails=$((fails + 1))
    if [ "$ROUND" = 1 ]; then
        echo "FAIL  $1"; echo "::warning::smoke check (first round): $1"
        if [ "$SHOWN" = 0 ]; then
            SHOWN=1
            echo "      served: $CODE ${TYPE:-?}, title: $(grep -o '<title>[^<]*' "$BODY" 2>/dev/null | head -1 | cut -c8-120)"
            echo "      first bytes: $(head -c 200 "$BODY" 2>/dev/null | tr -s '\n\t ' ' ')"
        fi
    else
        echo "FAIL  $1"; echo "::error::smoke check: $1"
    fi
}
pass() { echo "ok    $1"; }

# fetch PATH -> sets CODE, TYPE, LOCATION; body in $BODY (redirects are not followed)
fetch() {
    local out
    out="$(curl -sS --max-time 30 -o "$BODY" -w '%{http_code}|%{content_type}|%{redirect_url}' "$BASE$1" 2>&1)" || out="000||"
    CODE="${out%%|*}"; out="${out#*|}"; TYPE="${out%%|*}"; LOCATION="${out#*|}"
}

no_fatal() { # PATH
    if grep -qiE 'Fatal error|Parse error|There has been a critical error|Uncaught (Error|Exception)' "$BODY"; then
        fail "$1 shows a PHP error"
        return 1
    fi
}

expect_page() { # PATH [TEXT...]: 200, no PHP error, the shared header and each TEXT present
    local path="$1"; shift
    fetch "$path"
    [ "$CODE" = "200" ] || { fail "$path returned $CODE, want 200"; return; }
    no_fatal "$path" || return
    grep -q 'class="site-header' "$BODY" || { fail "$path has no shared header (class=\"site-header)"; return; }
    local t
    for t in "$@"; do
        grep -qF "$t" "$BODY" || { fail "$path does not contain \"$t\""; return; }
    done
    pass "$path 200, shared header${*:+, contains $*}"
}

expect_redirect() { # PATH CODE LOCATION-REGEX
    fetch "$1"
    if [ "$CODE" = "$2" ] && [[ "$LOCATION" =~ $3 ]]; then
        pass "$1 $CODE -> $LOCATION"
    else
        fail "$1 returned $CODE -> ${LOCATION:-(no Location)}, want $2 -> /$3/"
    fi
}

run_checks() {
# Theme pages and the static pages next to WordPress
expect_page / "How valuable"                   # the value test on the homepage
expect_page /about/
expect_page /contact/
expect_page /courses/
expect_page /articles/
expect_page /people/lessy/
expect_page /people/martien/
expect_page /experts/

# Members only: logged out, /profile/ sends you to the login page
expect_redirect /profile/ 302 'wp-login\.php'
# Old member URLs go to the curated profile
expect_redirect /members/lessy/ 301 '/people/lessy/$'

# Platform REST API answers with JSON
fetch /wp-json/prospergenics/v1/courses
if [ "$CODE" = "200" ] && [[ "$TYPE" == application/json* ]] && python3 -c 'import json,sys; json.load(open(sys.argv[1]))' "$BODY" 2>/dev/null; then
    pass "/wp-json/prospergenics/v1/courses 200 JSON"
else
    fail "/wp-json/prospergenics/v1/courses returned $CODE ($TYPE), want 200 JSON"
fi

fetch /.well-known/security.txt
[ "$CODE" = "200" ] && pass "/.well-known/security.txt 200" || fail "/.well-known/security.txt returned $CODE"

fetch "/this-page-does-not-exist-$(date +%s)/"
if [ "$CODE" = "404" ]; then no_fatal "unknown URL" && pass "unknown URL 404"; else fail "unknown URLs should 404, got $CODE"; fi
}

run_checks
if [ "$fails" -gt 0 ]; then
    echo
    echo "$fails check(s) failed; checking again in ${WAIT}s (a host that briefly serves another page passes now)"
    sleep "$WAIT"
    ROUND=2; fails=0
    run_checks
fi

echo
[ "$fails" -eq 0 ] && echo "smoke check passed: $BASE" || echo "smoke check: $fails failed for $BASE"
[ "$fails" -eq 0 ]

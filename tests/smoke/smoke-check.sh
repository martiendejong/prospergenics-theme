#!/usr/bin/env bash
# Post-deploy smoke check (audit ARC-17): the static pages, the platform routes and the shared
# header, against a live site. Read-only GET requests. Run by .github/workflows/deploy-test.yml
# after every upload; by hand:
#
#   bash tests/smoke/smoke-check.sh                       # https://test.prospergenics.com
#   bash tests/smoke/smoke-check.sh http://localhost:8096
#
# Prints one line per check and exits 1 when any fails.
set -uo pipefail

BASE="${1:-https://test.prospergenics.com}"
BASE="${BASE%/}"
BODY="$(mktemp)"
trap 'rm -f "$BODY"' EXIT
fails=0

fail() { echo "FAIL  $1"; echo "::error::smoke check: $1"; fails=$((fails + 1)); }
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

echo
[ "$fails" -eq 0 ] && echo "smoke check passed: $BASE" || echo "smoke check: $fails failed for $BASE"
[ "$fails" -eq 0 ]

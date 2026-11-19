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
#
#   SMOKE_MEMBER_PROFILE  a public member profile to check (default /in/martien-de-jong/, card #7232;
#                         "none" skips it, for a site that has no public /in/ profile yet)
#   SMOKE_RESULTS=<file>  also write the deciding round as "ok <check>" / "FAIL <check>" lines, one per
#                         check, so a production deploy can compare the site after the upload with the
#                         site before it (tests/smoke/smoke-compare.sh, deploy-production.yml)
set -uo pipefail

BASE="${1:-https://test.prospergenics.com}"
BASE="${BASE%/}"
BODY="$(mktemp)"
trap 'rm -f "$BODY"' EXIT
fails=0
ROUND=1
SHOWN=0
WAIT="${SMOKE_RETRY_WAIT:-45}"
MEMBER_PROFILE="${SMOKE_MEMBER_PROFILE:-/in/martien-de-jong/}"
RESULTS=()   # "ok <check>" / "FAIL <check>" of the current round; <check> is the first word of the message

# In the first round a failure is a warning plus what the host served; in the last round an error.
fail() {
    fails=$((fails + 1))
    RESULTS+=("FAIL ${1%% *}")
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
pass() { echo "ok    $1"; RESULTS+=("ok ${1%% *}"); }

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

expect_asset() { # PATH CONTENT-TYPE-PREFIX: a theme file answers 200 with its type
    fetch "$1"
    if [ "$CODE" = "200" ] && [[ "$TYPE" == "$2"* ]]; then pass "$1 200 $TYPE"; else fail "$1 returned $CODE ${TYPE:-?}, want 200 $2"; fi
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
# The members list is built from PROFILES (cards #6516, #6517): Farid's card links /people/, the
# plugin's placeholder quote is gone
expect_page /members/ '/people/farid/' '/people/lessy/'
if grep -qF 'Every task I pick up teaches me something' "$BODY"; then fail "/members/ still shows the placeholder quote"; fi
# A public member profile, /in/<slug>/ (inc/profiles/routes.php, card #7232): the platform's own
# profile pages, rendered by WordPress for a logged-out visitor
if [ "$MEMBER_PROFILE" != "none" ]; then expect_page "$MEMBER_PROFILE" 'class="pgp-root'; fi

# Theme files load: the homepage's LCP images and a font (a broken assets/.htaccess would answer 500 for all, #7223)
THEME_ASSETS=/wp-content/themes/prospergenics-2026/assets
expect_asset "$THEME_ASSETS/hero-top.webp" image/webp
expect_asset "$THEME_ASSETS/hero-top-mobile.webp" image/webp
expect_asset "$THEME_ASSETS/fonts/inter-latin-400-normal.woff2" ""   # the host sent no type for .woff2 before assets/.htaccess

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
    ROUND=2; fails=0; RESULTS=()
    run_checks
fi
if [ -n "${SMOKE_RESULTS:-}" ]; then printf '%s\n' "${RESULTS[@]}" > "$SMOKE_RESULTS"; fi

echo
[ "$fails" -eq 0 ] && echo "smoke check passed: $BASE" || echo "smoke check: $fails failed for $BASE"
[ "$fails" -eq 0 ]

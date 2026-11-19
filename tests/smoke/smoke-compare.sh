#!/usr/bin/env bash
# Compare two smoke-check results files (SMOKE_RESULTS of tests/smoke/smoke-check.sh, card #7232):
# the site before a production deploy and the same site after it. A check that passed before and
# fails after is a regression caused by the deploy: exit 1. A check that already failed before
# (production does not have every page test has yet) is listed as a known failure and does not fail
# the deploy. A check that is missing from the "after" file counts as failed.
#
#   bash tests/smoke/smoke-compare.sh <before-file> <after-file>
set -uo pipefail

before="${1:?results file from before the deploy}"
after="${2:?results file from after the deploy}"
[ -s "$after" ] || { echo "::error::no smoke-check results after the deploy ($after)"; exit 1; }

status() { # FILE CHECK -> ok | FAIL | missing (FAIL wins when a check has both)
    if grep -qxF "FAIL $2" "$1" 2>/dev/null; then echo FAIL
    elif grep -qxF "ok $2" "$1" 2>/dev/null; then echo ok
    else echo missing; fi
}

regressions=0
known=0
while read -r check; do
    [ -n "$check" ] || continue
    was="$(status "$before" "$check")"
    now="$(status "$after" "$check")"
    if [ "$now" = "ok" ]; then
        [ "$was" = "FAIL" ] && echo "fixed       $check (failed before the deploy, passes now)"
        continue
    fi
    if [ "$was" = "ok" ]; then
        echo "REGRESSION  $check (passed before the deploy, $now after)"
        echo "::error::the deploy broke $check: it passed before the upload"
        regressions=$((regressions + 1))
    else
        echo "known       $check (already failing before the deploy)"
        echo "::warning::$check fails on this site, as it did before the deploy"
        known=$((known + 1))
    fi
done < <(cat "$before" "$after" 2>/dev/null | sed -E 's/^(ok|FAIL) //' | sort -u)

echo
echo "smoke compare: $regressions regression(s), $known known failure(s)"
[ "$regressions" -eq 0 ]

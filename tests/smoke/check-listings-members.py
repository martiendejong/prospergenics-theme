#!/usr/bin/env python3
"""
Compare the jengo-listings "members" collection with PROFILES (card #6516).

    python3 tests/smoke/check-listings-members.py
    python3 tests/smoke/check-listings-members.py --api https://listings.prospergenics.com

For every person in design/new-hero-2026-10/people/members-collection.json (generated from
PROFILES by build-profiles.py) it asks the listings API for /collections/members/slug/<slug> and
compares name, role, quote and photo; it also reports records the API still has under the old
listing slugs. Read-only GET requests. Exit 0 when the API matches, 1 on any difference.

The theme already shows PROFILES on /members/ whatever the API says (inc/members-directory.php),
so a difference here is not visible on the site; it means the collection still needs a redeploy
with jengo-listings deploy_collections.py from members-collection.json.
"""
import argparse
import json
import os
import sys
import urllib.error
import urllib.request

HERE = os.path.dirname(os.path.abspath(__file__))
COLLECTION = os.path.join(HERE, "..", "..", "design", "new-hero-2026-10", "people", "members-collection.json")
# The listing slugs of the 10 Oct dataset; after a redeploy from PROFILES none of them should be left.
OLD_SLUGS = ["martien-de-jong", "simitia-mpoe", "diko-mohamed", "sjoerd-schepman", "faith-nkumun",
             "sophy-nashipae-de-jong", "mayiani-nanana-mpoe", "lou-marten"]


def get(api, slug):
    req = urllib.request.Request(f"{api}/collections/members/slug/{slug}",
                                 headers={"User-Agent": "ProsperGenics listings check", "Accept": "application/json"})
    try:
        with urllib.request.urlopen(req, timeout=20) as r:
            return json.load(r)
    except urllib.error.HTTPError as e:
        if e.code == 404:
            return None
        raise


def main():
    ap = argparse.ArgumentParser(description=__doc__.strip().splitlines()[0])
    ap.add_argument("--api", default="https://listings.prospergenics.com")
    args = ap.parse_args()
    with open(COLLECTION, encoding="utf-8") as f:
        items = json.load(f)["items"]

    problems = []
    for it in items:
        got = get(args.api.rstrip("/"), it["slug"])
        if got is None:
            problems.append(f"{it['slug']}: missing from the API")
            continue
        fields = got.get("fields") or {}
        want = {"name": it["title"], "role": it["fields"]["role"], "quote": it["fields"]["quote"], "photo": it["fields"]["photo"]}
        have = {"name": got.get("title"), "role": fields.get("role"), "quote": fields.get("quote"), "photo": fields.get("photo")}
        for key in want:
            if (want[key] or "") != (have[key] or ""):
                problems.append(f"{it['slug']}: {key} is {have[key]!r}, PROFILES has {want[key]!r}")
    for slug in OLD_SLUGS:
        if slug not in {it["slug"] for it in items} and get(args.api.rstrip("/"), slug) is not None:
            problems.append(f"{slug}: old listing record still in the API")

    for p in problems:
        print("DIFF  " + p)
    print(f"{len(items)} people checked, {len(problems)} difference(s)")
    return 1 if problems else 0


if __name__ == "__main__":
    sys.exit(main())

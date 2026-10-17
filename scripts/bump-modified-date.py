#!/usr/bin/env python3
"""
Bump a prospergenics.com WordPress page's `post_modified` date by re-saving
its own existing content through the REST API — no real content change.

Why this exists: AI citation engines weight WebPage `dateModified` (derived
by Yoast from `post_modified`) as a recency signal. A code-level SEO fix
(a theme/plugin filter, a header/WAF change) never touches the WordPress
post row, so `post_modified` silently goes stale even though the page's
real SEO signal just improved. Run this script against every page a
code-level fix touched, right after that fix ships. See docs/SEO-OPS.md.

Usage:
    WP_APP_USER=martien WP_APP_PASSWORD="xxxx xxxx xxxx xxxx xxxx xxxx" \
        python scripts/bump-modified-date.py 423 352 11

    # Or, with no page IDs given, bumps the 3 known-recurring pages:
    WP_APP_USER=... WP_APP_PASSWORD=... python scripts/bump-modified-date.py

Credentials: prospergenics.com WP Application Password, vault project 4,
credential 148 (user `martien`). Never hardcode it here — read from env.
"""
import os
import sys

import requests

SITE = "https://prospergenics.com"
API = f"{SITE}/wp-json/wp/v2/pages"

# Pages known to carry code-level SEO fixes that don't touch the WP post row.
DEFAULT_PAGE_IDS = [423, 352, 11]  # homepage, /about/, /trainings/

# The site's Cloudflare WAF (JengoWork tasks 1668/1669) blocks the default
# `python-requests/x.y` User-Agent with a 403 "Request forbidden by
# administrative rules" — a plain browser/curl-style UA is required.
HEADERS = {"User-Agent": "Mozilla/5.0 (compatible; prospergenics-seo-ops/1.0)"}


def bump(session: requests.Session, page_id: int) -> None:
    before = session.get(
        f"{API}/{page_id}", params={"context": "edit"}, headers=HEADERS, timeout=30
    )
    before.raise_for_status()
    page = before.json()
    slug = page.get("slug")
    modified_before = page.get("modified_gmt")
    raw_content = page["content"]["raw"]

    resp = session.post(
        f"{API}/{page_id}",
        json={"content": raw_content},
        headers=HEADERS,
        timeout=30,
    )
    resp.raise_for_status()
    modified_after = resp.json().get("modified_gmt")

    print(
        f"page {page_id} ({slug}): modified_gmt {modified_before} -> {modified_after}"
    )


def main() -> int:
    user = os.environ.get("WP_APP_USER")
    password = os.environ.get("WP_APP_PASSWORD")
    if not user or not password:
        print(
            "ERROR: set WP_APP_USER and WP_APP_PASSWORD env vars "
            "(vault project 4, credential 148).",
            file=sys.stderr,
        )
        return 1

    page_ids = [int(a) for a in sys.argv[1:]] or DEFAULT_PAGE_IDS

    session = requests.Session()
    session.auth = (user, password)

    for page_id in page_ids:
        bump(session, page_id)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())

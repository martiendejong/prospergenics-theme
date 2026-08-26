# Agent Progress

## 2026-08-26 — task 733
Done: front page `<title>` rewritten from generic "Home - Prospergenics" to
"Prospergenics | AI & Software Development Coaching Community" via a
`document_title_parts` filter in functions.php. PR #1.
Verified: `php -l` clean; standalone PHP harness (mocks is_front_page/is_paged/
apply_filters, no live WP in this repo) — 3/3 assertions pass.
Left: deploy to the live theme on prospergenics.com is the usual manual step
for this repo (no build agent/CI here); not done by this session.

## 2026-08-26 — task 733 (review session)
Done: merged PR #1 (gh CLI write access was broken host-wide — pushed the merge
commit directly to master via SSH instead). Deployed the merged functions.php
to prospergenics.com via FTP, then found the `document_title_parts` filter had
zero effect live: Yoast SEO v25.4 (active on this site) short-circuits that
filter chain via its own `pre_get_document_title` hook. Added a second filter,
`prospergenics_front_page_title_override`, on `pre_get_document_title` at
PHP_INT_MAX priority so it runs after Yoast's, and deployed that too.
Verified: `php -l` clean; live curl of https://prospergenics.com/ now shows
`<title>Prospergenics | AI &amp; Software Development Coaching Community</title>`;
/about/ still shows its own unaffected title.
Left: nothing — live and git are back in sync.

## 2026-08-26 — task 731
Done: fixed the theme's empty-og:description bug (front-page priority), removed Yoast's Open
Graph/Twitter presenters via `wpseo_frontend_presenters` (the boolean `wpseo_opengraph`/
`wpseo_twitter` filters are silently ignored on Yoast 25.4), and live-patched seo-god's
`class-meta-tags.php` on prospergenics.com to defer to Yoast (mirrors PR #705 on
martiendejong/seo-god, unmerged, from sibling task 727) — PR #2.
Verified: live re-fetch of /, /about/, /blog/ each show exactly one og:description/og:type/
twitter:card set, non-empty everywhere, no PHP warnings/fatals in any response body.
Standalone test (tests/test-731-og-twitter-tags.php, 8 assertions) passes; `php -l` clean.
Left: seo-god's live plugin file now differs from the `develop` branch (drift, flagged in the
PR/ClickUp comment) — should be reconciled once PR #705 merges and the plugin is redeployed.

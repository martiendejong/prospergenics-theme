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

## 2026-08-26 — task 765 (WIP)
Started: /trainings/ page (WP page id 11, slug "trainings") has no
`<meta name="description">` and no Course schema, matching the task. Live
check found the page's own post_content is empty in the DB — the "Cursor,
Claude Code, React" trainings the task describes don't live on this URL, they
live on three real, separate, already-published pieces of content: the
"Digital Technology" program CPT post, the "AI and Technology Training" page,
and the "Claude Code & Cursor Coaching for Dutch Teams" page (published by
task 734). Plan: render those three as real cards on the empty /trainings/
page, add a fallback meta description (Yoast presenter-removal pattern, same
as task 733/731 since Yoast 25.4 ignores plain filters), and add real Course
schema for exactly those three, not invented ones.
Left: implementation + deploy in progress this session.

## 2026-08-26 — task 765 (complete)
Done: PR #3 (functions.php, +172 lines, additive only). Deployed to the live
theme via FTP on top of the drifted live functions.php (which already carries
task 731's uncommitted OG/Twitter fix + a legacy SMTP block) — backed the live
file up first as functions.php.bak-task765, then merged this addition in
without touching the existing drift.
Verified: `php -l` clean; standalone harness 12/12 assertions pass; live curl
of https://prospergenics.com/trainings/ shows a real `<meta name="description">`,
a `Course` JSON-LD graph with 3 real entries (Digital Technology, AI and
Technology Training, Claude Code & Cursor Coaching), and real page content
where the page used to render empty. Homepage and /about/ unaffected.
Left: nothing for this task. The broader live/git deploy drift on this repo
(731's OG/Twitter fix, the legacy SMTP block) is pre-existing and out of
scope here — flagged again for a future dedicated reconciliation task.

## 2026-08-26 - task 731 (review session)
Done: merged master (task 733's title filters) into this branch, then fixed two more og:url/og:title
defects found on the live site during review: the static posts page (/blog/) was treated as the
front page (og:url pointed at the homepage), and archives used get_permalink() outside the loop
(og:url was the first post's URL, og:title carried an escaped <span>). Deployed the merged function
to prospergenics.com via FTP as a surgical patch (live functions.php carries ~190 lines of
unmerged drift: the /trainings/ block from task 765 and an SMTP config - left untouched).
Verified: php -l clean, tests/test-731-og-twitter-tags.php 14/14; live curl of /, /about/, /blog/,
/category/uncategorized/ each show one consistent OG/Twitter set with correct og:url. PR #2 merged.
Left: seo-god PR #705 still unmerged - the live seo-god plugin patch on this site stays a stop-gap.

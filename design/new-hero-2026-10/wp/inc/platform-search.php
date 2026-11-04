<?php
/**
 * Prospergenics Platform: search track (card #6393, "ProsperGenics WordPress expertise discovery
 * vertical slice"). Loader for this track's modules in inc/search/. See docs/PLATFORM-BUILD.md.
 *
 *   helpers.php  pure rules: query terms, matching, evidence weights and ranking, the "why this
 *                person matches" explanation, canonical / robots / ItemList decisions
 *   engine.php   the search over profiles, projects (#6388), courses and articles: privacy and
 *                moderation rules, the fixed query plan, the transient cache and its
 *                invalidation, and the seams for Jengo FTS5 (#6378), Jengo Listings, Knowledge
 *                provenance, AGI intake and scrutiny evidence (#6389)
 *   routes.php   /find/ (server-rendered), title, robots, canonical, ItemList, assets, and the
 *                link from the /members/ directory
 *
 * Template: pg-search.php in the theme root. Styles: assets/platform/search.css, only on /find/.
 * The global FTS5 search with tabs and filters (#6378-#6380) replaces the index behind the
 * 'prospergenics_search_index_hits' seam later; the page, privacy rules and ranking stay.
 */

defined( 'ABSPATH' ) || exit;

require __DIR__ . '/search/helpers.php';
require __DIR__ . '/search/engine.php';
require __DIR__ . '/search/routes.php';

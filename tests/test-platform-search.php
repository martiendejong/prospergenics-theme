<?php
/**
 * Standalone test for the expertise search at /find/ (card #6393):
 * wp-theme/prospergenics-2026/inc/platform-search.php and inc/search/.
 *
 * Checks the rules that need no database:
 *   - query normalisation, terms, stems, word-start matching, the default AGI intake keywords,
 *   - evidence weights: confirmed contributions over claimed ones, verified items, coverage, ranking,
 *   - the "why this person matches" explanation and its escaping,
 *   - SEO: one canonical per query, noindex for empty / odd / filtered / paged / empty-result pages,
 *     the ItemList, the cache key,
 * and the wiring: the privacy and moderation rules in engine.php, the fixed query plan and the
 * candidate cap, cache invalidation hooks, the seams with their docblocks, the route and template.
 *
 * Run with: php tests/test-platform-search.php
 */

$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ───────────────────────────── WordPress stubs ─────────────────────────────
define( 'ABSPATH', __DIR__ . '/' );
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }

require $theme . '/inc/search/helpers.php';

// ───────────────────────────── Query and terms ─────────────────────────────
echo "Query and terms\n";
ok( 'pricing for shops' === prospergenics_search_normalize_query( "  <b>Pricing</b>\n for   SHOPS " ), 'tags gone, whitespace collapsed, lower case' );
ok( PROSPERGENICS_SEARCH_MAX_QUERY === mb_strlen( prospergenics_search_normalize_query( str_repeat( 'a', 300 ) ) ), 'queries are cut at ' . PROSPERGENICS_SEARCH_MAX_QUERY . ' characters' );
ok( '' === prospergenics_search_normalize_query( array( 'x' ) ), 'an array (?q[]=) is no query' );
ok( array( 'pricing', 'shops' ) === prospergenics_search_terms( 'who can help me with pricing for my shops' ), 'stopwords dropped: pricing, shops' );
ok( array( 'c++', 'c#' ) === prospergenics_search_terms( 'c++ c#' ), 'c++ and c# survive' );
ok( array( 'android' ) === prospergenics_search_terms( prospergenics_search_normalize_query( 'android android Android' ) ), 'duplicates removed' );
ok( 5 === count( prospergenics_search_terms( 'one1 two2 three3 four4 five5 six6 seven7' ) ), 'at most 5 terms' );
ok( array() === prospergenics_search_terms( 'the of and' ), 'only stopwords: no terms' );
ok( array( 'swahili' ) === prospergenics_search_terms( 'kwa na swahili' ), 'Swahili fillers are stopwords' );
ok( array( 'spam' ) === prospergenics_search_terms( 'http://www.spam.com' ), 'the pieces of a pasted link are not search words' );
ok( 'pric' === prospergenics_search_stem( 'pricing' ) && 'pric' === prospergenics_search_stem( 'priced' ) && 'shop' === prospergenics_search_stem( 'shops' ), 'stems: pricing/priced -> pric, shops -> shop' );
ok( 'company' === prospergenics_search_stem( 'companies' ), 'companies -> company' );
ok( 'ai' === prospergenics_search_stem( 'ai' ) && 'sale' === prospergenics_search_stem( 'sales' ) && 'bus' === prospergenics_search_stem( 'bus' ), 'a stem keeps at least four letters (sales -> sale, short words untouched)' );
ok( array( 'pricing' ) === prospergenics_search_text_matches( 'Price list for shops', array( 'pricing' ) ), '"pricing" finds "Price list"' );
ok( array() === prospergenics_search_text_matches( 'Maintain the servers', array( 'ai' ) ), '"ai" does not find "maintain" (word starts only)' );
ok( array( 'ai' ) === prospergenics_search_text_matches( 'AI tools for farmers', array( 'ai' ) ), '"ai" finds "AI tools"' );
ok( array() === prospergenics_search_text_matches( '', array( 'x' ) ), 'empty text matches nothing' );

echo "AGI intake default: keywords from a problem\n";
$kw = prospergenics_search_intake_keywords( 'I cannot decide how to price my tailoring services. Pricing my tailoring is hard and I keep losing customers.' );
ok( 'tailoring' === $kw[0], 'the most frequent word comes first: ' . implode( ', ', $kw ) );
ok( ! array_intersect( $kw, array( 'cannot', 'decide', 'keep', 'how', 'my' ) ), 'no filler words' );
ok( count( prospergenics_search_intake_keywords( str_repeat( 'alpha beta gamma delta epsilon zeta eta theta ', 5 ) ) ) <= PROSPERGENICS_SEARCH_MAX_TERMS, 'at most 5 keywords' );
ok( array() === prospergenics_search_intake_keywords( '123 456' ), 'numbers alone are no keywords' );

// ───────────────────────────── Evidence and ranking ─────────────────────────────
echo "Evidence and ranking\n";
$kinds = prospergenics_search_evidence_kinds();
ok( $kinds['project_confirmed']['weight'] > $kinds['project_claimed']['weight'], 'a confirmed contribution outweighs a claimed one' );
ok( $kinds['certificate']['weight'] > $kinds['skill']['weight'] && $kinds['course']['weight'] > $kinds['skill']['weight'], 'certificates and taught courses outweigh a self-declared skill' );
ok( $kinds['scrutiny']['verified'] && $kinds['project_confirmed']['verified'] && $kinds['certificate']['verified'] && ! $kinds['skill']['verified'] && ! $kinds['project_claimed']['verified'], 'verified: confirmed work, scrutiny, certificates, courses; not skills or claims' );
ok( ! isset( $kinds['project_disputed'] ), 'disputed contributions are no evidence' );

$s = prospergenics_search_score( array(
	array( 'kind' => 'skill', 'terms' => array( 'pricing' ), 'endorsements' => 5 ),
	array( 'kind' => 'project_confirmed', 'terms' => array( 'pricing' ) ),
	array( 'kind' => 'unknown', 'terms' => array( 'shops' ) ),
), array( 'pricing', 'shops' ) );
ok( 17.0 === $s['score'], 'skill 4 + endorsements (capped at 3) + confirmed project 10 = 17; unknown kinds ignored' );
ok( 1 === $s['coverage'] && 1 === $s['verified'], 'covers 1 of 2 terms, 1 verified item' );
$arts = prospergenics_search_score( array_fill( 0, 6, array( 'kind' => 'article', 'terms' => array( 'x' ) ) ), array( 'x' ) );
ok( 9.0 === $arts['score'], 'articles count for at most three' );
$prov = prospergenics_search_score( array( array( 'kind' => 'skill', 'terms' => array( 'x' ), 'verified' => true ) ), array( 'x' ) );
ok( 1 === $prov['verified'], 'provenance can mark an item verified' );

$p = function ( $name, $cov, $score, $ver ) { return array( 'name' => $name, 'coverage' => $cov, 'score' => $score, 'verified' => $ver ); };
$people = array( $p( 'Zed', 1, 30, 3 ), $p( 'Ann', 2, 4, 0 ), $p( 'Bob', 1, 30, 1 ), $p( 'Cy', 1, 30, 1 ), $p( 'Dee', 1, 40, 0 ) );
usort( $people, 'prospergenics_search_compare_people' );
ok( array( 'Ann', 'Dee', 'Zed', 'Bob', 'Cy' ) === array_column( $people, 'name' ), 'order: coverage, then score, then verified, then name' );

echo "Explanation\n";
$ev = array(
	array( 'kind' => 'article', 'label' => 'Pricing 101', 'url' => 'https://x.test/a1/' ),
	array( 'kind' => 'skill', 'label' => 'Pricing', 'url' => 'https://x.test/in/g/#pgp-skills' ),
	array( 'kind' => 'project_confirmed', 'label' => 'First paying customer', 'url' => 'https://x.test/projects/fpc/' ),
	array( 'kind' => 'course', 'label' => 'Pricing your services', 'url' => 'https://x.test/courses/p/' ),
	array( 'kind' => 'article', 'label' => 'Price <b>lists</b>', 'url' => 'https://x.test/a2/' ),
	array( 'kind' => 'project_claimed', 'label' => 'Duka', 'url' => 'https://x.test/projects/duka/' ),
);
$html = prospergenics_search_explanation_html( 'pricing', $ev );
ok( 0 === strpos( $html, 'Matches ‘pricing’: skill <a href="https://x.test/in/g/#pgp-skills">Pricing</a>; contributed to <a href="https://x.test/projects/fpc/">First paying customer</a> (confirmed); contributed to <a href="https://x.test/projects/duka/">Duka</a> (claimed); teaches <a href="https://x.test/courses/p/">Pricing your services</a>; wrote 2 articles: ' ), 'explanation in the brief\'s shape, strongest first: ' . strip_tags( $html ) );
ok( false !== strpos( $html, '<a href="https://x.test/a1/">Pricing 101</a> and <a href="https://x.test/a2/">Price &lt;b&gt;lists&lt;/b&gt;</a>' ), 'every item links to its evidence; labels are escaped' );
ok( '' === prospergenics_search_explanation_html( 'x', array() ), 'no evidence, no explanation' );
ok( false !== strpos( prospergenics_search_explanation_html( '<script>', array( array( 'kind' => 'skill', 'label' => 'S', 'url' => '' ) ) ), '&lt;script&gt;' ), 'the query is escaped; an item without URL is plain text' );
$parts = prospergenics_search_explanation_parts( array_fill( 0, 5, array( 'kind' => 'article', 'label' => 'A', 'url' => 'u' ) ) );
ok( 'wrote 5 articles:' === $parts[0]['text'] && 3 === count( $parts[0]['links'] ) && 'and 2 more' === $parts[0]['suffix'], 'five articles: three linked, "and 2 more"' );
$parts = prospergenics_search_explanation_parts( array( array( 'kind' => 'certificate', 'label' => 'C', 'url' => 'v' ) ) );
ok( 'completed' === $parts[0]['text'] && '(certificate)' === $parts[0]['suffix'], 'certificates: "completed … (certificate)"' );

// ───────────────────────────── SEO ─────────────────────────────
echo "SEO: canonical, robots, ItemList\n";
ok( 'https://x.test/find/?q=pricing%20shops' === prospergenics_search_canonical( 'https://x.test/find/', '  Pricing   SHOPS ' ), 'one canonical per query: normalised, filters and paging left out' );
ok( 'https://x.test/find/' === prospergenics_search_canonical( 'https://x.test/find/', '' ), 'no query: the bare page' );
$base = array( 'q' => 'pricing', 'terms' => array( 'pricing' ), 'total' => 3 );
ok( prospergenics_search_indexable( $base ), 'a plain query with results may be indexed' );
ok( ! prospergenics_search_indexable( array( 'total' => 3 ) ), 'empty query: noindex' );
ok( ! prospergenics_search_indexable( array( 'total' => 0 ) + $base ), 'no results: noindex' );
ok( ! prospergenics_search_indexable( array( 'filtered' => true ) + $base ), 'filtered or tab: noindex' );
ok( ! prospergenics_search_indexable( array( 'paged' => 2 ) + $base ), 'page 2: noindex' );
ok( ! prospergenics_search_indexable( array( 'problem' => true ) + $base ), '"describe your problem": noindex' );
foreach ( array( 'http://spam.example', 'call me at 0712 345 678 678', 'a b c d e f', str_repeat( 'long ', 14 ), '12345', 'buy@cheap.com', '%%%%ab%%%%' ) as $odd ) {
	ok( prospergenics_search_query_is_odd( $odd, prospergenics_search_terms( $odd ) ? prospergenics_search_terms( $odd ) : array( 'x' ) ), "odd query: \"$odd\"" );
}
ok( ! prospergenics_search_query_is_odd( 'mobile money', array( 'mobile', 'money' ) ), 'not odd: "mobile money"' );
$list = prospergenics_search_itemlist( array( array( 'url' => 'https://x.test/in/a/', 'name' => 'A' ), array( 'url' => '', 'name' => 'skip' ), array( 'url' => 'https://x.test/p/', 'name' => 'P' ) ), 'https://x.test/find/?q=a', 'Who knows a?' );
ok( 'ItemList' === $list['@type'] && 2 === $list['numberOfItems'] && 2 === $list['itemListElement'][1]['position'] && 'https://x.test/p/' === $list['itemListElement'][1]['url'], 'ItemList of the results in order, positions from 1, items without URL left out' );

echo "Cache key and paging\n";
$k1 = prospergenics_search_cache_key( 3, array( 'terms' => array( 'a' ), 'skill' => '', 'kind' => '', 'verified' => false ) );
$k2 = prospergenics_search_cache_key( 3, array( 'verified' => false, 'kind' => '', 'skill' => '', 'terms' => array( 'a' ) ) );
$k3 = prospergenics_search_cache_key( 4, array( 'terms' => array( 'a' ), 'skill' => '', 'kind' => '', 'verified' => false ) );
ok( $k1 === $k2 && $k1 !== $k3, 'key independent of argument order; a new generation retires it' );
ok( strlen( $k1 ) <= 172, 'fits a transient name' );
ok( array( 21, 22 ) === prospergenics_search_page( range( 1, 22 ), 2 ), 'page 2 of 22 items' );
ok( array( 1 ) === prospergenics_search_page( array( 1 ), 0 ), 'page 0 is page 1' );
$snip = prospergenics_search_snippet( str_repeat( 'filler words here ', 30 ) . 'our pricing model works ' . str_repeat( 'tail ', 40 ), array( 'pricing' ), 80 );
ok( false !== strpos( $snip, 'pricing' ) && mb_strlen( $snip ) <= 82 && 0 === strpos( $snip, '…' ), 'snippet centred on the match, cut on words: ' . $snip );

// ───────────────────────────── Wiring ─────────────────────────────
echo "Wiring: privacy, moderation, query plan, cache, seams\n";
$engine = file_get_contents( $theme . '/inc/search/engine.php' );
$routes = file_get_contents( $theme . '/inc/search/routes.php' );
$tpl    = file_get_contents( $theme . '/pg-search.php' );
ok( false !== strpos( $engine, 'prospergenics_profiles_is_indexable( $user_id )' ) && false !== strpos( $engine, 'prospergenics_profiles_visibility_for( $user_id, 0 )' ), 'people: indexable profiles only, seen as a logged-out visitor' );
ok( false !== strpos( $engine, 'prospergenics_profiles_is_suspended( $user_id )' ), 'suspended members are never found' );
ok( (bool) preg_match( "#'skill' => 'skills', 'experience' => 'experience', 'education' => 'education', 'certificate' => 'certificates', 'project' => 'projects'#", $engine ) && false !== strpos( $engine, '! $vis[ $section[ $kind ] ]' ), 'evidence from a section the member made private is skipped' );
ok( false !== strpos( $engine, "prospergenics_articles_is_hidden( \$p->ID )" ) && false !== strpos( $engine, "'' !== (string) \$p->post_password" ), 'hidden and password-protected articles are left out' );
ok( false !== strpos( $engine, "post_status = 'publish'" ) && false === strpos( $engine, "post_status IN" ), 'only published posts are read' );
ok( false !== strpos( $engine, "'disputed' !== \$c['status']" ), 'disputed contributions never count' );
ok( false !== strpos( $engine, "prospergenics_courses_certificate_data" ) && false !== strpos( $engine, '_pg_courses_revoked_at' ), 'revoked certificates are left out; valid ones link to their verification page' );
ok( false !== strpos( $engine, "function_exists( 'prospergenics_projects_contributors' )" ) && false !== strpos( $engine, "post_type_exists'" ), 'projects (#6388) behind function_exists / post_type_exists guards' );
ok( false !== strpos( $engine, 'array_slice( $by_user, 0, PROSPERGENICS_SEARCH_MAX_CANDIDATES, true )' ) && 200 === PROSPERGENICS_SEARCH_MAX_CANDIDATES, 'at most 200 candidates are scored' );
ok( false !== strpos( $engine, 'cache_users( array_keys( $by_user ) )' ) && false !== strpos( $engine, "prospergenics_search_prime_posts( \$hits['posts'] )" ) && false !== strpos( $engine, 'prospergenics_search_prime_posts( $entries )' ) && false !== strpos( $engine, "update_meta_cache( 'post', array_map( 'intval', wp_list_pluck( \$rows, 'ID' ) ) )" ), 'users, posts and post meta are primed in one read each (no query per result)' );
ok( false !== strpos( $engine, 'prospergenics_search_entries( $terms, array_values( array_intersect( array_keys( $by_user )' ), 'profile entries are read for the chosen candidates only, so a common word never cuts anyone\'s evidence short' );
ok( false !== strpos( $engine, '$wpdb->esc_like(' ) && false !== strpos( $engine, '$wpdb->prepare( "$c LIKE %s", $like )' ), 'LIKE values are escaped and prepared' );
ok( false !== strpos( $engine, 'set_transient( $key, $result, PROSPERGENICS_SEARCH_CACHE_TTL )' ) && preg_match( '/PROSPERGENICS_SEARCH_CACHE_TTL = (\d+);/', $engine, $ttl ) && (int) $ttl[1] <= 900, 'a short transient cache (at most 15 minutes)' );
foreach ( array( 'prospergenics_platform_content_saved', 'prospergenics_platform_content_deleted', 'prospergenics_profiles_suspended', 'prospergenics_courses_certificate_issued', 'prospergenics_courses_certificate_revoked', 'profile_update', 'prospergenics_search_invalidate' ) as $hook ) {
	ok( false !== strpos( $engine, "add_action( '$hook', 'prospergenics_search_flush' )" ), "$hook retires the cache" );
}
ok( false !== strpos( $engine, "array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' )" ) && false !== strpos( $engine, "'_post_meta', 'prospergenics_search_flush_for_post_meta'" ) && false !== strpos( $engine, "'_user_meta', 'prospergenics_search_flush_for_user_meta'" ), 'post saves, deletions and meta changes (hidden articles, contributions, privacy) retire the cache' );
ok( false === strpos( $engine, "'completion'" ), 'the completion score the search itself caches does not retire the cache' );
foreach ( array( 'prospergenics_search_index_hits' => 'Jengo FTS5', 'prospergenics_search_listings_results' => 'Jengo Listings', 'prospergenics_search_evidence_provenance' => 'Knowledge provenance', 'prospergenics_search_intake_terms' => 'AGI intake', 'prospergenics_search_person_evidence' => 'scrutiny' ) as $seam => $what ) {
	$at = strpos( $engine, "apply_filters( '$seam'" );
	$doc = false !== $at ? substr( $engine, max( 0, $at - 1400 ), 1400 ) : '';
	ok( false !== $at && false !== stripos( $doc, $what ), "seam '$seam' ($what) is a filter with a docblock" );
}
ok( false !== strpos( $routes, "add_rewrite_rule( '^find/?\$', 'index.php?pg_search=1', 'top' )" ), '/find/ is the route (WordPress keeps /search/ and ?s=)' );
ok( false !== strpos( $routes, "add_filter( 'wp_robots'" ) && false !== strpos( $routes, '<link rel="canonical"' ) && false !== strpos( $routes, 'application/ld+json' ), 'robots, canonical and the ItemList are printed in the head' );
ok( false !== strpos( $routes, "wp_unslash( \$_GET )" ) && false !== strpos( $engine, 'sanitize_text_field' ) && false !== strpos( $engine, 'sanitize_textarea_field' ), 'request input is sanitised' );
ok( false === strpos( $tpl, '<main' ) && false !== strpos( $tpl, 'get_header()' ) && false !== strpos( $tpl, 'get_footer()' ), 'template uses the shared header/footer (which own <main>)' );
ok( false !== strpos( $tpl, 'role="search" method="get"' ) && false !== strpos( $tpl, '<label class="pgs-label" for="pgs-q">' ) && false !== strpos( $tpl, '<label for="pgs-skill">' ) && false !== strpos( $tpl, '<label for="pgs-verified">' ), 'GET forms with labelled controls (works without JavaScript)' );
ok( false !== strpos( $tpl, 'aria-current="page"' ) && false !== strpos( $tpl, 'aria-label="Result types"' ), 'tabs are links in a labelled nav, the current one marked' );
ok( ! preg_match( '#<script(?! type="application/ld\+json")#', $tpl ), 'no client-side rendering in the template' );
ok( is_file( $theme . '/assets/platform/search.css' ) && false !== strpos( $routes, "'/assets/platform/search.css'" ), 'styles only on /find/' );
$functions = file_get_contents( $theme . '/functions.php' );
ok( false !== strpos( $functions, "require get_template_directory() . '/inc/platform-search.php';" ), 'functions.php loads the search track' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

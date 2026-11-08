<?php
/**
 * Standalone test for the content that disappeared from view on test.prospergenics.com
 * (reported 4 Nov):
 *   - Martien's courses live in the jengo-listings system; the theme's course archive took over
 *     /courses/ on 25 Oct and listed none of them. inc/courses/listings.php lists them again.
 *   - The /blog/ page showed no posts (no posts page in Settings → Reading); page-blog.php lists them.
 *
 * Run with: php tests/test-2026-content-restore.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ────────────── minimal WordPress stubs ──────────────
define( 'ABSPATH', __DIR__ . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
$GLOBALS['transients'] = array();
$GLOBALS['options']    = array();
$GLOBALS['remote']     = null;
$GLOBALS['remote_calls'] = 0;
function apply_filters( $hook, $value ) { return $value; }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $s ) ) ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function home_url( $p = '/' ) { return 'https://test.example' . $p; }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $t ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['options'][ $k ] ) ? $GLOBALS['options'][ $k ] : $d; }
function update_option( $k, $v, $a = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
class WP_Error {}
function wp_remote_get( $url, $args ) { $GLOBALS['remote_calls']++; return $GLOBALS['remote']; }
function wp_remote_retrieve_response_code( $r ) { return $r['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }

require $theme . '/inc/courses/listings.php';

$sample = json_encode( array( 'total' => 3, 'items' => array(
	array( 'id' => 55, 'slug' => 'ai-software-lifecycle', 'title' => 'AI Software Lifecycle', 'company' => 'ProsperGenics',
		'primary_skills' => 'Claude Code,Git,Worktrees,Task management,Deployment', 'description_seo' => '<p>Learn to set up a fully autonomous AI development system that handles the entire software lifecycle from your phone. In 10 practical steps you will install an AI coding agent and much more.</p>' ),
	array( 'id' => 89, 'slug' => 'python', 'title' => 'Python', 'company' => 'ProsperGenics', 'primary_skills' => '', 'description_seo' => '<script>alert(1)</script><b>Learn</b> Python.' ),
	array( 'id' => 90, 'slug' => '', 'title' => 'No slug' ),
) ) );

echo "Listings courses (Martien's courses)\n";
$items = prospergenics_courses_listings_parse( $sample );
ok( 2 === count( $items ), 'two usable courses, the one without a slug is dropped' );
ok( 'ai-software-lifecycle' === $items[0]['slug'] && 'AI Software Lifecycle' === $items[0]['title'] && 'ProsperGenics' === $items[0]['provider'], 'slug, title and provider are read' );
ok( strlen( $items[0]['summary'] ) <= 184 && '…' === substr( $items[0]['summary'], -3 ), 'long descriptions are shortened to a summary' );
ok( array( 'Claude Code', 'Git', 'Worktrees', 'Task management' ) === $items[0]['skills'], 'at most four skills' );
ok( 'Learn Python.' === $items[1]['summary'], 'HTML and scripts are stripped from the summary' );
ok( array() === prospergenics_courses_listings_parse( 'not json' ), 'bad JSON gives no courses, no error' );

$card = prospergenics_courses_listing_card( $items[0] );
ok( false !== strpos( $card, 'href="https://test.example/courses/ai-software-lifecycle/"' ), 'the card links to the course page the listings plugin serves' );
ok( false !== strpos( $card, 'href="https://test.example/courses/ai-software-lifecycle/learn/"' ), 'and to its lessons' );
ok( false === strpos( prospergenics_courses_listing_card( array( 'slug' => 'x', 'title' => '<img src=x onerror=alert(1)>', 'summary' => '', 'skills' => array(), 'provider' => '' ) ), '<img' ), 'titles are escaped' );

echo "Cache and outage\n";
$GLOBALS['remote'] = array( 'code' => 200, 'body' => $sample );
ok( 2 === count( prospergenics_courses_listings() ) && 1 === $GLOBALS['remote_calls'], 'fetched from the listings service' );
prospergenics_courses_listings();
ok( 1 === $GLOBALS['remote_calls'], 'then served from the cache' );
ok( 2 === count( get_option( 'pg_courses_listings_last' ) ), 'and the last good list is kept' );
$GLOBALS['transients'] = array();
$GLOBALS['remote'] = array( 'code' => 502, 'body' => 'Bad gateway' );
ok( 2 === count( prospergenics_courses_listings() ), 'when the service is down, the last good list still shows' );

echo "Wiring\n";
$loader  = file_get_contents( $theme . '/inc/platform-courses.php' );
$archive = file_get_contents( $theme . '/archive-pg_course.php' );
ok( false !== strpos( $loader, "'listings'" ), 'the courses loader loads listings.php' );
ok( false !== strpos( $archive, 'prospergenics_courses_listings()' ) && false !== strpos( $archive, 'prospergenics_courses_listing_card($pgc_item)' ), '/courses/ lists the listings courses' );
ok( false !== strpos( $archive, '$wp_query->found_posts + count($pgc_listings)' ), 'and counts them in "N courses"' );
ok( false !== strpos( $archive, 'if (have_posts() || $pgc_listings)' ), 'the empty state shows only when there are no courses at all' );

echo "Empty /courses/ (card #6537)\n";
// The template rendered with stubs: no filter form and no "0 courses" when there are no courses at
// all (theme or listings) and nothing is filtered; both stay as soon as there is a course or a filter.
$GLOBALS['pgc'] = array();
function get_header() {}
function get_footer() {}
function get_current_user_id() { return 0; }
function get_query_var( $k ) { return 0; }
function get_terms( $a ) { return array(); }
function prospergenics_courses_current_filters() { return $GLOBALS['pgc']['filters']; }
function prospergenics_courses_levels() { return array( 'beginner' => 'Beginner' ); }
function prospergenics_courses_is_instructor() { return false; }
function have_posts() { return $GLOBALS['pgc']['posts'] > 0; }
function the_post() { $GLOBALS['pgc']['posts']--; }
function get_post() { return null; }
function prospergenics_courses_card( $p, $u ) { return '<article class="pgc-card">theme course</article>'; }
function selected( $a, $b ) {}
function esc_attr( $s ) { return esc_html( $s ); }
function _n( $one, $many, $n, $d ) { return 1 === $n ? $one : $many; }
function paginate_links( $a ) { return ''; }
function pgc_render_archive( $theme, $filters, $posts, $listings ) {
	global $wp_query;
	$GLOBALS['pgc']        = array( 'filters' => $filters + array( 'category' => '', 'level' => '', 'sort' => 'newest' ), 'posts' => $posts );
	$wp_query              = (object) array( 'found_posts' => $posts );
	$GLOBALS['transients'] = array( 'pg_courses_listings' => $listings );
	ob_start();
	include $theme . '/archive-pg_course.php';
	return ob_get_clean();
}
$one_listing = array( array( 'slug' => 'html', 'title' => 'HTML', 'provider' => 'ProsperGenics', 'summary' => 'Learn HTML.', 'skills' => array() ) );
$none = pgc_render_archive( $theme, array(), 0, array() );
ok( false === strpos( $none, 'class="pgc-filters"' ) && false === strpos( $none, 'class="pgc-count"' ) && false === strpos( $none, 'Show courses' ), 'no courses, nothing filtered: no Category / Level / Sort form and no "0 courses"' );
ok( false !== strpos( $none, 'The first courses open soon.' ) && false !== strpos( $none, 'Tell us what you want to learn</a>' ) && false !== strpos( $none, 'Meet our members</a>' ), 'the next steps stay: the courses contact form and the members' );
$listed = pgc_render_archive( $theme, array(), 0, $one_listing );
ok( false !== strpos( $listed, 'class="pgc-filters"' ) && false !== strpos( $listed, '1 course</p>' ) && false === strpos( $listed, 'The first courses open soon.' ), 'one listings course: the form and "1 course" are back' );
$own = pgc_render_archive( $theme, array(), 2, array() );
ok( false !== strpos( $own, 'class="pgc-filters"' ) && false !== strpos( $own, '2 courses</p>' ), 'theme courses only: the form and the count show' );
$filtered = pgc_render_archive( $theme, array( 'level' => 'beginner' ), 0, $one_listing );
ok( false !== strpos( $filtered, 'class="pgc-filters"' ) && false !== strpos( $filtered, '0 courses match your filters' ) && false !== strpos( $filtered, 'No courses match these filters yet.' ), 'a filter that matches nothing keeps the form, so it can be changed' );

echo "Blog\n";
$blog = @file_get_contents( $theme . '/page-blog.php' );
ok( false !== $blog, 'page-blog.php exists, so /blog/ lists the posts whatever the Reading setting is' );
ok( $blog && false !== strpos( $blog, "'post_type'           => 'post'" ) && false !== strpos( $blog, "'post_status'         => 'publish'" ), 'it lists published posts only' );
ok( $blog && false !== strpos( $blog, 'paginate_links(' ) && false !== strpos( $blog, 'wp_reset_postdata()' ), 'with paging, and resets the global post afterwards' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

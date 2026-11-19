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

function _n( $one, $many, $n, $d ) { return 1 === $n ? $one : $many; }
function prospergenics_courses_lesson_count_label( $n ) { return sprintf( _n( '%d lesson', '%d lessons', $n, 'prospergenics-2026' ), $n ); } // inc/courses/render.php

require $theme . '/inc/courses/listings.php';

$sample = json_encode( array( 'total' => 3, 'items' => array(
	array( 'id' => 55, 'slug' => 'ai-software-lifecycle', 'title' => 'AI Software Lifecycle', 'company' => 'ProsperGenics',
		'primary_skills' => 'Claude Code,Git,Worktrees,Task management,Deployment', 'description_seo' => '<p>Learn to set up a fully autonomous AI development system that handles the entire software lifecycle from your phone. In 10 practical steps you will install an AI coding agent and much more.</p>',
		// the 'fields' as the listings service has them (19 Nov): an instructor, no lesson pages
		'fields' => array( 'level' => 'Intermediate', 'duration' => '15 min setup + ongoing', 'instructor' => 'Martien de Jong', 'topics' => array( 'Claude Code' ) ) ),
	array( 'id' => 89, 'slug' => 'python', 'title' => 'Python', 'company' => 'ProsperGenics', 'primary_skills' => '', 'description_seo' => '<script>alert(1)</script><b>Learn</b> Python.',
		// no instructor, 50 lesson pages
		'fields' => array( 'duration' => '50 lessons · ~8h', 'instructor' => '', 'page_titles' => array_fill( 0, 50, 'A lesson' ) ) ),
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

echo "Instructor and lesson count on the listings cards (card #7760)\n";
ok( 'Martien de Jong' === $items[0]['instructor'] && 0 === $items[0]['lessons'] && '15 min setup + ongoing' === $items[0]['duration'], 'AI Software Lifecycle: its instructor; no lesson pages and no count in its duration, which is kept as it is' );
ok( '' === $items[1]['instructor'] && 50 === $items[1]['lessons'] && '~8h' === $items[1]['duration'], 'Python: 50 lessons (its lesson pages) and "~8h" from "50 lessons · ~8h"' );
ok( false !== strpos( $card, '<p class="pgc-card-by">By Martien de Jong</p>' ) && false === strpos( $card, 'By ProsperGenics' ), 'the card names the course\'s own instructor, not the provider' );
ok( false !== strpos( $card, '<ul class="pgc-card-meta"><li>15 min setup + ongoing</li></ul>' ), 'and its duration at the foot of the card' );
$py_card = prospergenics_courses_listing_card( $items[1] );
ok( false !== strpos( $py_card, '<p class="pgc-card-by">By ProsperGenics</p>' ), 'no instructor in the listings: the provider, as before' );
ok( false !== strpos( $py_card, '<ul class="pgc-card-meta"><li>50 lessons</li><li>~8h</li></ul>' ), '"50 lessons · ~8h", as the theme\'s cards show "N lessons · price"' );
ok( false !== strpos( $card, '<ul class="pgc-card-meta pgc-card-topics"><li>Claude Code</li>' ), 'the topics stay, in their own list under the summary' );
$f = prospergenics_courses_listing_fields( array( 'duration' => '1 lesson', 'instructor' => '<b>Ann</b>' ) );
ok( array( 'instructor' => 'Ann', 'lessons' => 1, 'duration' => '' ) === $f, 'a duration that is only "1 lesson" gives the count; markup is stripped from the instructor' );
ok( array( 'instructor' => '', 'lessons' => 12, 'duration' => '~5h' ) === prospergenics_courses_listing_fields( json_encode( array( 'duration' => '10 lessons · ~5h', 'page_titles' => range( 1, 12 ) ) ) ), 'fields as a JSON string are read too; the lesson pages win over the count in the duration' );
ok( array( 'instructor' => '', 'lessons' => 0, 'duration' => '' ) === prospergenics_courses_listing_fields( null ), 'no fields: nothing' );
$old_cache = prospergenics_courses_listing_card( array( 'slug' => 'html', 'title' => 'HTML', 'summary' => '', 'skills' => array(), 'provider' => 'ProsperGenics' ) );
ok( false !== strpos( $old_cache, 'By ProsperGenics' ) && false === strpos( $old_cache, '<ul class="pgc-card-meta">' ), 'a course cached before this change (no instructor or lessons yet) still renders' );
ok( (bool) preg_match( '#\.pgc-card-meta\.pgc-card-topics\{margin-top:0;#', (string) file_get_contents( $theme . '/assets/platform/courses.css' ) ), 'courses.css: the topics list is not pushed to the foot of the card; the lessons line is' );

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
$one_match = pgc_render_archive( $theme, array( 'category' => 'business' ), 1, array() );
ok( false !== strpos( $one_match, '1 course matches your filters</p>' ) && false === strpos( $one_match, 'course match your' ), '"1 course matches your filters" (card #7760)' );
ok( false !== strpos( pgc_render_archive( $theme, array( 'level' => 'beginner' ), 3, array() ), '3 courses match your filters</p>' ), '"3 courses match your filters"' );

echo "Blog\n";
$blog = @file_get_contents( $theme . '/page-blog.php' );
ok( false !== $blog, 'page-blog.php exists, so /blog/ lists the posts whatever the Reading setting is' );
ok( $blog && false !== strpos( $blog, "'post_type'           => 'post'" ) && false !== strpos( $blog, "'post_status'         => 'publish'" ), 'it lists published posts only' );
ok( $blog && false !== strpos( $blog, 'paginate_links(' ) && false !== strpos( $blog, 'wp_reset_postdata()' ), 'with paging, and resets the global post afterwards' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

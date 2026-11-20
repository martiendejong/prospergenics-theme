<?php
/**
 * Standalone test for card #6524: one system answers each /courses/<slug>/.
 *
 * The jengo-listings plugin holds the curated catalogue and claims every /courses/<slug>/ with a
 * rewrite rule of its own; the theme's own courses (pg_course) live at the same kind of URL. As the
 * team agreed on 19 Nov, the theme answers /courses/<slug>/ (and its lessons, lesson notes and
 * certificate under it) only for a PUBLISHED pg_course with that slug (inc/courses/routing.php):
 *   - the decision (prospergenics_courses_theme_route()): a published course: the query vars of the
 *     theme's own rule; a draft, an unknown slug, a listings course, /courses/ itself, the fixed
 *     words (/courses/my/, ...), /courses/<slug>/learn/ and other paths: untouched;
 *   - the 'request' filter: the plugin's vars make way, query-string vars stay, a site in a
 *     subfolder works, one indexed query per slug and request, no query for other paths;
 *   - the course permalinks are /courses/<slug>/;
 *   - a new course never takes the slug of a listings course (or a fixed word): "-course" is
 *     appended and the slug is made unique again (data-model.php, listings.php).
 *
 * Run with: php tests/test-6524-course-urls.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ───────────────────── WP stubs ─────────────────────
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['__hooks']    = array();
$GLOBALS['home']       = 'https://test.prospergenics.com/';
$GLOBALS['transients'] = array();
$GLOBALS['options']    = array();
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function add_action( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function apply_filters( $tag, $value, ...$args ) {
	foreach ( isset( $GLOBALS['__hooks'][ $tag ] ) ? $GLOBALS['__hooks'][ $tag ] : array() as $cb ) { $value = $cb( $value, ...$args ); }
	return $value;
}
function home_url( $path = '' ) { return rtrim( $GLOBALS['home'], '/' ) . '/' . ltrim( (string) $path, '/' ); }
function wp_unslash( $v ) { return $v; }
// as WordPress in the 'query' context: lower case, escaped octets kept, anything else a dash
function sanitize_title_for_query( $s ) { return trim( preg_replace( '/[^a-z0-9%_-]+/', '-', strtolower( (string) $s ) ), '-' ); }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ] : false; }
function get_option( $k, $d = false ) { return isset( $GLOBALS['options'][ $k ] ) ? $GLOBALS['options'][ $k ] : $d; }

/** The posts table: post_name => [post_type, post_status]; counts the queries. */
class Stub_WPDB {
	public $posts   = 'wp_posts';
	public $queries = array();
	public $rows    = array(
		'market-stall-bookkeeping' => array( 'pg_course', 'publish' ),
		'soap-making-at-home'      => array( 'pg_course', 'draft' ),
		'pricing-your-services'    => array( 'pg_course', 'publish' ),
		'an-article'               => array( 'pg_article', 'publish' ),
	);
	public function prepare( $sql, ...$args ) {
		return vsprintf( str_replace( '%s', "'%s'", $sql ), array_map( 'addslashes', $args ) );
	}
	public function get_var( $sql ) {
		$this->queries[] = $sql;
		if ( ! preg_match( "/^SELECT post_name FROM wp_posts WHERE post_name = '([^']*)' AND post_type = 'pg_course' AND post_status = 'publish' LIMIT 1$/", $sql, $m ) ) {
			return null;
		}
		return isset( $this->rows[ $m[1] ] ) && array( 'pg_course', 'publish' ) === $this->rows[ $m[1] ] ? $m[1] : null;
	}
}
$GLOBALS['wpdb'] = new Stub_WPDB();

/** WordPress' unique slug: a taken slug gets -2, -3, ...; then the 'wp_unique_post_slug' filter. */
function wp_unique_post_slug( $slug, $post_id, $status, $type, $parent = 0 ) {
	$try = $slug;
	for ( $n = 2; isset( $GLOBALS['wpdb']->rows[ $try ] ); $n++ ) {
		$try = $slug . '-' . $n;
	}
	return apply_filters( 'wp_unique_post_slug', $try, $post_id, $status, $type, $parent, $slug );
}

require $theme . '/inc/courses/helpers.php';
require $theme . '/inc/courses/data-model.php';
require $theme . '/inc/courses/routing.php';
require $theme . '/inc/courses/listings.php';

/** The 'request' filters of the courses track on a request for $uri, starting from $vars. */
function request( $uri, array $vars, array $get = array() ) {
	$_SERVER['REQUEST_URI'] = $uri;
	$_GET                   = $get;
	return apply_filters( 'request', $vars );
}
// what the plugin's rule ^courses/([^/]+)/?$ (and /learn/) leaves in the query vars (its names are a guess)
function plugin_vars( $slug, $view = 'detail' ) { return array( 'jl_course' => $slug, 'jl_view' => $view ); }
$course = array( 'pg_course' => 'market-stall-bookkeeping', 'post_type' => 'pg_course', 'name' => 'market-stall-bookkeeping' );

echo "The decision (pure)\n";
$asked     = array();
$published = function ( $segment ) use ( &$asked ) {
	$asked[] = $segment;
	return in_array( $segment, array( 'market-stall-bookkeeping' ), true ) ? $segment : '';
};
ok( $course === prospergenics_courses_theme_route( 'courses/market-stall-bookkeeping', $published ), 'a published theme course: the query vars of its post type rule (pg_course, post_type, name)' );
ok( null === prospergenics_courses_theme_route( 'courses/soap-making-at-home', $published ) && null === prospergenics_courses_theme_route( 'courses/nonexistent-xyz', $published ) && null === prospergenics_courses_theme_route( 'courses/ai-software-lifecycle', $published ), 'a draft, an unknown slug, a listings course: null (left to the plugin)' );
ok( array( 'pg_lesson' => 'money-in-money-out', 'post_type' => 'pg_lesson', 'name' => 'money-in-money-out', 'pg_courses_course' => 'market-stall-bookkeeping' ) === prospergenics_courses_theme_route( 'courses/market-stall-bookkeeping/lessons/money-in-money-out', $published ), 'its lessons: the vars of the theme\'s lesson rule' );
ok( array( 'pg_lesson' => 'money-in-money-out', 'post_type' => 'pg_lesson', 'name' => 'money-in-money-out', 'pg_courses_course' => 'market-stall-bookkeeping', 'pg_courses_notes' => '1' ) === prospergenics_courses_theme_route( 'courses/market-stall-bookkeeping/lessons/money-in-money-out/notes', $published ), 'a lesson\'s notes PDF' );
ok( array( 'pg_courses_view' => 'certificate', 'pg_courses_course' => 'market-stall-bookkeeping' ) === prospergenics_courses_theme_route( 'courses/market-stall-bookkeeping/certificate', $published ), 'its certificate' );
ok( null === prospergenics_courses_theme_route( 'courses/soap-making-at-home/lessons/money-in-money-out', $published ) && null === prospergenics_courses_theme_route( 'courses/soap-making-at-home/certificate', $published ), 'lessons and certificate of a draft: null' );
$asked = array();
$none  = array( '', 'courses', 'courses/page/2', 'courses/my', 'courses/verify', 'courses/verify/PG-AAAA-BBBB-CCCC', 'courses/instructor/2-amina-wanjiru', 'courses/reminders/stop', 'courses/lessons', 'courses/market-stall-bookkeeping/learn', 'courses/market-stall-bookkeeping/lessons', 'courses/market-stall-bookkeeping/2', 'courses/a/b/c', 'tools/coachos', 'people/martien', 'xcourses/market-stall-bookkeeping' );
ok( array() === array_filter( array_map( function ( $p ) use ( $published ) { return prospergenics_courses_theme_route( $p, $published ); }, $none ) ), '/courses/ itself, its pages, the fixed words (my, verify, instructor, reminders, lessons), /learn/ and other paths: null' );
ok( array() === $asked, 'and none of them looks a course up' );

echo "The 'request' filter\n";
$db = $GLOBALS['wpdb'];
$db->queries = array();
ok( $course === request( '/courses/market-stall-bookkeeping/', plugin_vars( 'market-stall-bookkeeping' ) ), '/courses/<published course>/: the theme\'s vars, the plugin\'s are gone' );
ok( 1 === count( $db->queries ) && false !== strpos( $db->queries[0], "post_name = 'market-stall-bookkeeping' AND post_type = 'pg_course' AND post_status = 'publish' LIMIT 1" ), 'one prepared query: by post_name (indexed), published pg_course only' );
request( '/courses/market-stall-bookkeeping/', plugin_vars( 'market-stall-bookkeeping' ) );
ok( 1 === count( $db->queries ), 'asked again in the same request: no second query' );
ok( $course === request( '/courses/Market-Stall-Bookkeeping', plugin_vars( 'Market-Stall-Bookkeeping' ) ), 'another case, no trailing slash: the course\'s own slug' );
foreach ( array( 'soap-making-at-home' => 'a draft', 'nonexistent-xyz' => 'an unknown slug', 'ai-software-lifecycle' => 'a listings course', 'an-article' => 'the slug of another post type' ) as $slug => $what ) {
	ok( plugin_vars( $slug ) === request( '/courses/' . $slug . '/', plugin_vars( $slug ) ), "$what: the request is left as it was (the plugin answers)" );
}
ok( plugin_vars( 'market-stall-bookkeeping', 'learn' ) === request( '/courses/market-stall-bookkeeping/learn/', plugin_vars( 'market-stall-bookkeeping', 'learn' ) ), '/courses/<theme course>/learn/ (no such page in the theme): left as it was' );
$db->queries = array();
ok( array( 'post_type' => 'pg_course' ) === request( '/courses/', plugin_vars( '' ) ) && array( 'post_type' => 'pg_course', 'paged' => 2 ) === request( '/courses/page/2/', array() ), '/courses/ and its pages: still the theme\'s archive, as before' );
ok( array( 'pg_courses_view' => 'my' ) === request( '/courses/my/', array( 'pg_courses_view' => 'my' ) ) && array( 'x' => 1 ) === request( '/tools/coachos/', array( 'x' => 1 ) ) && array() === request( '/', array() ), '/courses/my/, /tools/coachos/, the homepage: untouched' );
ok( 0 === count( $db->queries ), 'and none of them costs a query' );
ok( array( 'pg_lesson' => 'money-in-money-out', 'post_type' => 'pg_lesson', 'name' => 'money-in-money-out', 'pg_courses_course' => 'market-stall-bookkeeping' ) === request( '/courses/market-stall-bookkeeping/lessons/money-in-money-out/', array( 'jl_course' => 'x' ) ), 'a lesson of the course: the theme\'s lesson vars whatever matched' );
ok( array( 'preview' => 'true' ) + $course === request( '/courses/market-stall-bookkeeping/', plugin_vars( 'market-stall-bookkeeping' ) + array( 'preview' => 'true' ), array( 'preview' => 'true' ) ), 'vars from the query string (?preview=true) stay' );
$override = request( '/courses/market-stall-bookkeeping/', plugin_vars( 'market-stall-bookkeeping' ) + array( 'post_type' => 'page' ), array( 'post_type' => 'page' ) );
ok( 'pg_course' === $override['post_type'], 'but cannot change which post the theme shows' );
$GLOBALS['home'] = 'https://example.org/site/';
ok( $course === request( '/site/courses/market-stall-bookkeeping/', plugin_vars( 'market-stall-bookkeeping' ) ), 'a site in a subfolder' );
$GLOBALS['home'] = 'https://test.prospergenics.com/';

echo "Course URLs are /courses/<slug>/\n";
$model = file_get_contents( $theme . '/inc/courses/data-model.php' );
ok( 1 === preg_match( "#register_post_type\( 'pg_course'.*?'has_archive'\s*=> 'courses',\s*'rewrite'\s*=> array\( 'slug' => 'courses', 'with_front' => false#s", $model ), 'pg_course: archive /courses/, rewrite slug courses, without the permalink front' );

echo "A new course never takes a listings course's slug\n";
ok( 'html-course' === prospergenics_courses_safe_slug( 'html', array( 'html', 'css' ) ) && 'soil' === prospergenics_courses_safe_slug( 'soil', array( 'html' ) ) && 'my-course' === prospergenics_courses_safe_slug( 'my' ), 'safe_slug: a taken or reserved slug gets "-course", any other stays' );
ok( array() === prospergenics_courses_listing_slugs(), 'no listings known yet: nothing taken' );
$GLOBALS['options']['pg_courses_listings_last'] = array( array( 'slug' => 'html' ), array( 'slug' => 'python' ), array( 'title' => 'no slug' ) );
ok( array( 'html', 'python' ) === prospergenics_courses_listing_slugs(), 'the last good list of the listings courses' );
$GLOBALS['transients']['pg_courses_listings'] = array( array( 'slug' => 'ai-software-lifecycle' ), array( 'slug' => 'html' ) );
ok( array( 'ai-software-lifecycle', 'html' ) === prospergenics_courses_listing_slugs(), 'the cached list first' );
ok( 'html-course' === wp_unique_post_slug( 'html', 7, 'publish', 'pg_course' ), 'a course called "HTML" gets html-course (the plugin keeps /courses/html/)' );
$GLOBALS['wpdb']->rows['html-course'] = array( 'pg_course', 'publish' );
ok( 'html-course-2' === wp_unique_post_slug( 'html', 8, 'publish', 'pg_course' ), 'and html-course-2 when html-course is taken' );
ok( 'verify-course' === wp_unique_post_slug( 'verify', 9, 'publish', 'pg_course' ) && 'python-basics' === wp_unique_post_slug( 'python-basics', 9, 'publish', 'pg_course' ), 'fixed words as before; any other slug stays' );
ok( 'html' === wp_unique_post_slug( 'html', 10, 'publish', 'pg_article' ), 'other post types: untouched' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

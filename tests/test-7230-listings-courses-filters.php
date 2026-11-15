<?php
/**
 * Card #7230: the listings-service courses take part in the /courses/ filters and in /find/.
 * Run with: php tests/test-7230-listings-courses-filters.php
 */
define( 'ABSPATH', sys_get_temp_dir() . '/' );
define( 'MINUTE_IN_SECONDS', 60 );
$failures = 0;
function ok( $c, $m ) { global $failures; echo ( $c ? '  PASS  ' : '  FAIL  ' ) . $m . "\n"; if ( ! $c ) { $failures++; } }

$GLOBALS['filters'] = array();
function add_filter( $tag, $fn ) { $GLOBALS['filters'][ $tag ][] = $fn; }
function add_action( $tag, $fn ) {}
function term_exists( $t, $tax ) { return 'programming' === $t ? array( 'term_id' => 1 ) : null; }
function apply_filters( $tag, $v ) { return $v; }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $s ) ), '-' ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function esc_html( $s ) { return htmlspecialchars( $s ); }
function esc_url( $s ) { return $s; }
function home_url( $p = '' ) { return 'https://x.test' . $p; }
function get_transient( $k ) { return $GLOBALS['cache'] ?? false; }
function set_transient( $k, $v ) { $GLOBALS['cache'] = $v; }
function prospergenics_search_text_matches( $text, $terms ) {
	foreach ( $terms as $t ) { if ( false !== stripos( $text, $t ) ) { return array( $t ); } }
	return array();
}

$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';
require $theme . '/inc/courses/helpers.php';
require $theme . '/inc/courses/listings.php';

$json = json_encode( array( 'items' => array(
	array( 'slug' => 'html', 'title' => 'HTML', 'company' => 'ProsperGenics', 'description_seo' => '<p>Learn how pages are built.</p>', 'primary_skills' => 'Headings', 'level' => 'Beginner', 'category' => 'web-development' ),
	array( 'slug' => 'python', 'title' => 'Python', 'description_seo' => 'Learn Python', 'fields' => array( 'level' => 'beginner', 'category' => 'programming' ) ),
	array( 'slug' => 'ai-software-lifecycle', 'title' => 'AI Software Lifecycle', 'description_seo' => 'x', 'level' => 'Intermediate', 'category' => 'ai-automation' ),
) ) );
$items = prospergenics_courses_listings_parse( $json );
ok( 3 === count( $items ), 'parses three courses' );
ok( 'beginner' === $items[0]['level'] && 'web-development' === $items[0]['category'], 'level and category from top-level fields' );
ok( 'beginner' === $items[1]['level'] && 'programming' === $items[1]['category'], 'level and category fall back to the fields object' );

$f = function ( $c, $l ) use ( $items ) { return array_column( prospergenics_courses_listings_filter( $items, array( 'category' => $c, 'level' => $l, 'sort' => 'newest' ) ), 'slug' ); };
ok( array( 'html', 'python' ) === $f( '', 'beginner' ), 'level=beginner keeps html and python' );
ok( array( 'ai-software-lifecycle' ) === $f( '', 'intermediate' ), 'level=intermediate keeps the AI course' );
ok( array( 'html' ) === $f( 'web-development', '' ), 'category filter' );
ok( array( 'html', 'python', 'ai-software-lifecycle' ) === $f( '', '' ), 'no filter keeps all' );
ok( array() === $f( 'programming', 'intermediate' ), 'combined filters can be empty' );

$cats = prospergenics_courses_listings_category_options( $items );
ok( isset( $cats['web-development'] ) && 'Web Development' === $cats['web-development'] && 3 === count( $cats ), 'category dropdown options from listings' );
ok( false !== strpos( prospergenics_courses_listing_card( $items[0] ), 'pgc-chip--level' ), 'card shows a level chip' );

$GLOBALS['cache'] = $items;
$hook = $GLOBALS['filters']['prospergenics_search_listings_results'][0];
$titles = function ( $q ) use ( $hook ) { return array_column( $hook( array(), array( $q ), array() ), 'title' ); };
ok( array( 'Python' ) === $titles( 'python' ), '/find/ python finds the Python course' );
ok( array( 'HTML' ) === $titles( 'html' ), '/find/ html finds the HTML course' );
ok( array( 'AI Software Lifecycle' ) === $titles( 'ai software' ), '/find/ ai software finds the AI course' );

$v = prospergenics_courses_category_query_vars( 'programming' );
ok( isset( $v['tax_query'] ) && ! isset( $v['post__in'] ), 'a category with a WP term filters by the term' );
ok( array( 'post__in' => array( 0 ) ) === prospergenics_courses_category_query_vars( 'web-development' ), 'a listings-only category lists no WP courses' );
ok( array() === prospergenics_courses_category_query_vars( '' ), 'no category: no restriction' );

echo $failures ? "\n$failures FAILED\n" : "\nAll 7230 tests passed\n";
exit( $failures ? 1 : 0 );

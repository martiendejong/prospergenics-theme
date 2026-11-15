<?php
/**
 * #7223 Standalone test for the anonymous homepage page cache eligibility
 * (design/new-hero-2026-10/wp/inc/page-cache.php, generated into wp-theme/).
 *
 * Checks: only the site root is eligible (not /register/, /profile/settings/, /badges/ although
 * is_front_page() is true there), the cache key depends on the path, and logged-in users,
 * query strings, POSTs and login cookies bypass the cache.
 *
 * Run with: php tests/test-7223-page-cache.php
 */
$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; } else { echo "  FAIL  $msg\n"; $failures++; }
}

define( 'ABSPATH', __DIR__ . '/' );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['_logged_in'] = false;
function add_action() {}
function is_user_logged_in() { return $GLOBALS['_logged_in']; }
// Mimics inc/seo.php: is_front_page() is true on every route here, the path check decides.
function prospergenics_2026_is_homepage_request() {
	$path = (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
	return '' === trim( $path, '/' );
}
require $theme . '/inc/page-cache.php';

function run( $uri, $method = 'GET', $get = array(), $cookies = array(), $logged_in = false ) {
	$_SERVER['REQUEST_URI']    = $uri;
	$_SERVER['REQUEST_METHOD'] = $method;
	$_GET                      = $get;
	$_COOKIE                   = $cookies;
	$GLOBALS['_logged_in']     = $logged_in;
	return pg_home_cache_eligible();
}

ok( run( '/' ), 'the site root is eligible' );
ok( ! run( '/register/' ), '/register/ is not eligible' );
ok( ! run( '/profile/settings/' ), '/profile/settings/ is not eligible' );
ok( ! run( '/badges/' ), '/badges/ is not eligible' );
ok( ! run( '/', 'POST' ), 'POST is not eligible' );
ok( ! run( '/', 'GET', array( 'a' => '1' ) ), 'a query string is not eligible' );
ok( ! run( '/', 'GET', array(), array( 'wordpress_logged_in_abc' => 'x' ) ), 'a login cookie is not eligible' );
ok( ! run( '/', 'GET', array(), array( 'comment_author_abc' => 'x' ) ), 'a comment cookie is not eligible' );
ok( ! run( '/', 'GET', array(), array(), true ), 'a logged-in user is not eligible' );

$_SERVER['REQUEST_URI'] = '/';
$root_key = pg_home_cache_key();
$_SERVER['REQUEST_URI'] = '/register/';
ok( pg_home_cache_key() !== $root_key, 'the cache key depends on the path' );
$_SERVER['REQUEST_URI'] = '/?x=1';
ok( pg_home_cache_key() === $root_key, 'the key ignores the query string' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

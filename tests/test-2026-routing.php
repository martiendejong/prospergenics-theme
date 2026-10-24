<?php
/**
 * Standalone test for the 2026 theme routing fix (docs/AUDIT-2026-10.md, C1).
 *
 * Before this fix the theme's index.php redirected every request that wasn't the front page
 * to the homepage, so /trainings/, /contact/, /blog/, search, archives and every 404 became
 * soft-404s. This guards:
 *   - index.php no longer redirects, and the theme ships page.php, 404.php, header.php,
 *     footer.php and searchform.php so WordPress's template hierarchy can resolve them,
 *   - the build script no longer writes its own index.php/functions.php over wp/,
 *   - prospergenics_2026_legacy_url_redirects() (the real function, extracted from
 *     functions.php) sends /kenya/ and old member URLs to their new homes with a 301, only
 *     when the request would 404, and only to a /people/ profile that exists.
 *
 * Run with: php tests/test-2026-routing.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

echo "Templates\n";
$index = file_get_contents( $theme . '/index.php' );
ok( false === strpos( $index, 'wp_safe_redirect' ) && false === strpos( $index, 'wp_redirect' ), 'index.php does not redirect' );
foreach ( array( 'page.php', '404.php', 'header.php', 'footer.php', 'searchform.php', 'single.php', 'functions.php' ) as $tpl ) {
	ok( file_exists( $theme . '/' . $tpl ), "$tpl exists" );
}
foreach ( array( 'page.php', '404.php', 'index.php', 'single.php' ) as $tpl ) {
	$src = file_get_contents( $theme . '/' . $tpl );
	ok( false !== strpos( $src, 'get_header()' ) && false !== strpos( $src, 'get_footer()' ), "$tpl uses the shared header and footer" );
}
$build = file_get_contents( $root . '/design/new-hero-2026-10/build-wp-theme.py' );
ok( false === strpos( $build, '"index.php"), "w"' ) && false === strpos( $build, '"functions.php"), "w"' ), 'build script does not overwrite index.php/functions.php' );

// ────────────────────── WP stubs for the redirect function ───────────────
define( 'ABSPATH', sys_get_temp_dir() . '/pg-routing-test-' . getmypid() . '/' );
@mkdir( ABSPATH . 'people/martien', 0777, true );
@mkdir( ABSPATH . 'people/diko', 0777, true );
@mkdir( ABSPATH . 'people/frank', 0777, true );
touch( ABSPATH . 'people/martien/index.html' );
touch( ABSPATH . 'people/diko/index.html' );
touch( ABSPATH . 'people/frank/index.html' );

function home_url( $path = '/' ) { return 'https://prospergenics.com' . $path; }
function add_action( ...$args ) {}
function add_theme_support( ...$args ) {}
function remove_action( ...$args ) {}
$GLOBALS['_is_404']   = false;
$GLOBALS['_redirect'] = null;
function is_404() { return $GLOBALS['_is_404']; }
function wp_safe_redirect( $location, $status = 302 ) { $GLOBALS['_redirect'] = array( $location, $status ); }

$source = file_get_contents( $theme . '/functions.php' );
if ( ! preg_match( '#function prospergenics_2026_legacy_url_redirects\(\) \{.*?\n\}\n#s', $source, $m ) ) {
	echo "  FAIL  could not extract prospergenics_2026_legacy_url_redirects() from functions.php\n";
	exit( 1 );
}
// The function ends in exit after a redirect; turn that into a return so the test survives.
eval( str_replace( 'exit;', 'return;', $m[0] ) );

function route( $path, $is_404 = true ) {
	$_SERVER['REQUEST_URI'] = $path;
	$GLOBALS['_is_404']     = $is_404;
	$GLOBALS['_redirect']   = null;
	prospergenics_2026_legacy_url_redirects();
	return $GLOBALS['_redirect'];
}

echo "Legacy redirects\n";
$cases = array(
	'/kenya/'                     => 'https://prospergenics.com/about/',
	'/members/diko/'              => 'https://prospergenics.com/people/diko/',
	'/members/martien-de-jong/'   => 'https://prospergenics.com/people/martien/',
	'/team_member/frank-kobaai/'  => 'https://prospergenics.com/people/frank/',
	'/members/diko?ref=x'         => 'https://prospergenics.com/people/diko/',
);
foreach ( $cases as $from => $to ) {
	$r = route( $from );
	ok( $r && $r[0] === $to && 301 === $r[1], "$from -> 301 $to" );
}
ok( null === route( '/members/nobody/' ), 'unknown member still 404s (no redirect)' );
ok( null === route( '/some-missing-page/' ), 'unrelated 404 is left alone' );
ok( null === route( '/kenya/', false ), 'non-404 request is never redirected' );
ok( null === route( '/members/../wp-config/' ), 'path traversal does not match' );

array_map( 'unlink', glob( ABSPATH . 'people/*/index.html' ) );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

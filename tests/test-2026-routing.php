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
 *   - the redirect module (inc/redirects.php, rules in inc/redirects.txt) sends legacy URLs
 *     to their new homes with a 301, only when the request would 404; old member URLs only
 *     to a /people/ profile that exists,
 *   - the real redirects.txt has no duplicates, chains, loops or malformed targets, so the
 *     launch task's ~30 URLs are checked on every pull request once they are added.
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
function remove_filter( ...$args ) {}
// Under eval() __DIR__ is this tests/ folder, so point the module at the theme's real rules file.
function apply_filters( $hook, $value ) {
	return 'prospergenics_2026_redirects_file' === $hook ? $GLOBALS['theme'] . '/inc/redirects.txt' : $value;
}
$GLOBALS['_is_404']   = false;
$GLOBALS['_redirect'] = null;
function is_404() { return $GLOBALS['_is_404']; }
function wp_safe_redirect( $location, $status = 302 ) { $GLOBALS['_redirect'] = array( $location, $status ); }

ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "/inc/redirects.php'" ), 'functions.php loads the redirect module' );
// The hook ends in exit after a redirect; load the real module with that exit turned into a
// return so the test survives (the file-level ABSPATH guard is left alone).
$module = file_get_contents( $theme . '/inc/redirects.php' );
ok( 1 === substr_count( $module, "        exit;\n" ), 'redirect hook exits right after redirecting' );
eval( '?>' . str_replace( "        exit;\n", "        return;\n", $module ) );

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
ok( 'https://prospergenics.com/courses/' === route( '/programs/digital-technology/' )[0], 'old programme pages go to courses' );
ok( 'https://prospergenics.com/courses/' === route( '/Programs/' )[0], 'old programme archive goes to courses (case-insensitive)' );
ok( 'https://prospergenics.com/community/' === route( '/member-type/coach/' )[0], 'old member-type archives go to community' );

echo "Rule matching\n";
$rules = prospergenics_2026_parse_redirects( "# comment\nold-page   /new-page/   # trailing comment\nshop/*   /tools/\nshop/special/*  /services/\nshop/item  /partners/\nout  https://example.org/x\n\n" );
ok( 5 === count( $rules ), 'parser reads 5 rules and skips comments and blank lines' );
ok( '/new-page/' === prospergenics_2026_match_redirect( '/Old-Page', $rules ), 'exact rule, any case and slashes' );
ok( '/partners/' === prospergenics_2026_match_redirect( '/shop/item/', $rules ), 'exact rule wins over a wildcard' );
ok( '/services/' === prospergenics_2026_match_redirect( '/shop/special/thing', $rules ), 'longest wildcard wins' );
ok( '/tools/' === prospergenics_2026_match_redirect( '/shop/other', $rules ), 'wildcard matches everything under it' );
ok( null === prospergenics_2026_match_redirect( '/shopping', $rules ), 'wildcard does not match a longer sibling name' );
ok( null === prospergenics_2026_match_redirect( '/shop', $rules ), 'shop/* alone does not match shop itself' );

echo "Rule checks\n";
ok( array() === prospergenics_2026_redirect_problems( $rules ), 'valid rules report no problems' );
$bad = prospergenics_2026_redirect_problems( array( 'a' => '/b/', 'b' => '/c/', 'loop' => '/loop/', 'x' => 'no-slash' ) );
ok( 3 === count( $bad ), 'chain, self-redirect and malformed target are all reported (' . implode( '; ', $bad ) . ')' );
ok( array( 'dup' ) === prospergenics_2026_redirect_duplicates( "dup /a/\nother /b/\nDUP/ /c/" ), 'duplicate old paths are reported' );

echo "The real redirects.txt\n";
$real_text  = file_get_contents( $theme . '/inc/redirects.txt' );
$real_rules = prospergenics_2026_parse_redirects( $real_text );
ok( count( $real_rules ) >= 4, count( $real_rules ) . ' rules' );
$problems = prospergenics_2026_redirect_problems( $real_rules );
ok( ! $problems, 'no chains, loops or malformed targets' . ( $problems ? ': ' . implode( '; ', $problems ) : '' ) );
$dupes = prospergenics_2026_redirect_duplicates( $real_text );
ok( ! $dupes, 'no duplicate old paths' . ( $dupes ? ': ' . implode( ', ', $dupes ) : '' ) );
foreach ( $real_rules as $source => $target ) {
	ok( ! in_array( rtrim( $source, '/*' ), array( '', 'wp-admin', 'wp-login.php', 'wp-json', 'people', 'contact', 'about' ), true ), "rule \"$source\" does not shadow a core or live path" );
}

array_map( 'unlink', glob( ABSPATH . 'people/*/index.html' ) );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

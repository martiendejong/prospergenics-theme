<?php
/**
 * Standalone test for JengoWork task 855: prospergenics_legacy_url_redirects() must issue a
 * real 301 redirect for /kenya/ (a pre-restructure page removed with no redirect left behind)
 * to the About page, and must leave every other 404 and every non-404 request untouched.
 *
 * This extracts the real function body straight out of functions.php via a regex, so the test
 * always exercises the actual current source rather than a copy that can drift from it.
 *
 * Run with: php tests/test-855-legacy-url-redirects.php
 */

// ────────────────────── WP core function stubs ───────────────────────────
function home_url( $path = '/' ) { return 'https://prospergenics.com' . $path; }
function add_action( ...$args ) {}

$GLOBALS['_is_404']            = false;
$GLOBALS['_redirect_calls']    = array();
$GLOBALS['_exit_called']       = false;
function is_404() { return $GLOBALS['_is_404']; }
function wp_safe_redirect( $location, $status = 302 ) {
	$GLOBALS['_redirect_calls'][] = array( 'location' => $location, 'status' => $status );
	// Real wp_safe_redirect() doesn't exit by itself -- the caller does, exactly like the
	// production function under test. Simulate that here instead of a real exit() so the test
	// process itself doesn't die.
	$GLOBALS['_exit_called'] = 'PENDING';
}

// ─────────────────── extract the real function from functions.php ────────
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
if ( ! preg_match( '/(function prospergenics_legacy_url_redirects\(\)\s*\{.*?\r?\n\})\r?\nadd_action\( \'template_redirect\'/s', $source, $m ) ) {
	fwrite( STDERR, "FAIL: could not locate prospergenics_legacy_url_redirects() in functions.php\n" );
	exit( 1 );
}
// Replace the real exit; with a marker so we can assert it would have run, without killing PHP.
// functions.php uses CRLF line endings, so match \r?\n rather than a literal \n.
$fn_source = preg_replace( '/exit;\r?\n\t\}/', "\$GLOBALS['_exit_called'] = true;\n\t}", $m[1], 1 );
eval( $fn_source );

// ─────────────────────────────────────────────────────────────────────────
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

function reset_state( $is_404, $uri ) {
	$GLOBALS['_is_404']         = $is_404;
	$GLOBALS['_redirect_calls'] = array();
	$GLOBALS['_exit_called']    = false;
	$_SERVER['REQUEST_URI']     = $uri;
}

// Test 1: /kenya/ on a 404 redirects to /about/ with a real 301, and exits.
reset_state( true, '/kenya/' );
prospergenics_legacy_url_redirects();
ok( count( $GLOBALS['_redirect_calls'] ) === 1, '/kenya/ (404) triggers exactly one redirect call' );
ok( ( $GLOBALS['_redirect_calls'][0]['location'] ?? '' ) === 'https://prospergenics.com/about/', '/kenya/ redirects to https://prospergenics.com/about/' );
ok( ( $GLOBALS['_redirect_calls'][0]['status'] ?? 0 ) === 301, '/kenya/ redirect uses HTTP 301 (permanent), not a temporary redirect' );
ok( $GLOBALS['_exit_called'] === true, '/kenya/ redirect calls exit() so no 404 body is ever rendered' );

// Test 2: /kenya without a trailing slash also matches (trim() normalizes it).
reset_state( true, '/kenya' );
prospergenics_legacy_url_redirects();
ok( count( $GLOBALS['_redirect_calls'] ) === 1, '/kenya (no trailing slash, 404) still redirects' );

// Test 3: a query string on /kenya/ does not break the match (path-only comparison).
reset_state( true, '/kenya/?utm_source=old-backlink' );
prospergenics_legacy_url_redirects();
ok( count( $GLOBALS['_redirect_calls'] ) === 1, '/kenya/?utm_source=old-backlink (404) still redirects, query string stripped before matching' );

// Test 4: an unrelated 404 (not in the legacy list) is left alone -- no redirect, no exit.
reset_state( true, '/some-random-nonexistent-page/' );
prospergenics_legacy_url_redirects();
ok( count( $GLOBALS['_redirect_calls'] ) === 0, 'An unrelated 404 URL is not redirected' );
ok( $GLOBALS['_exit_called'] === false, 'An unrelated 404 URL does not exit' );

// Test 5: /kenya/ that is NOT a 404 (e.g. a real future page at that slug) is left alone --
// the redirect must never fire ahead of real, live content.
reset_state( false, '/kenya/' );
prospergenics_legacy_url_redirects();
ok( count( $GLOBALS['_redirect_calls'] ) === 0, '/kenya/ that resolves to real content (not a 404) is never redirected' );

// Test 6: known-still-live legacy URLs from the task (/trainings/, /martien/) are never touched,
// confirming the redirect list is scoped to the one confirmed-broken URL, not a blanket rule.
foreach ( array( '/trainings/', '/martien/' ) as $still_live ) {
	reset_state( true, $still_live );
	prospergenics_legacy_url_redirects();
	ok( count( $GLOBALS['_redirect_calls'] ) === 0, "$still_live is not in the legacy redirect list (still resolves live)" );
}

echo "\n" . ( $failures === 0 ? "ALL TESTS PASSED\n" : "$failures TEST(S) FAILED\n" );
exit( $failures === 0 ? 0 : 1 );

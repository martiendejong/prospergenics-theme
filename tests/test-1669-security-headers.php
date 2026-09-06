<?php
/**
 * Standalone test for JengoWork task 1669: the send_headers callback registered in
 * inc/security-headers.php must emit Strict-Transport-Security (https only),
 * X-Content-Type-Options, X-Frame-Options and Content-Security-Policy on a normal
 * front-end request, and must emit nothing when is_admin() is true or headers are
 * already sent.
 *
 * This extracts the real closure straight out of inc/security-headers.php via a
 * regex, so the test always exercises the actual current source rather than a copy
 * that can drift from it. The extracted closure runs inside a dedicated namespace
 * (Prospergenics\Test1669) so that its unqualified calls to header()/headers_sent()
 * resolve to the stubs below instead of PHP's real built-ins, which cannot be
 * redeclared in the global namespace.
 *
 * Run with: php tests/test-1669-security-headers.php
 */

namespace Prospergenics\Test1669;

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}
$GLOBALS['_is_admin']      = false;
$GLOBALS['_headers_sent']  = false;
$GLOBALS['_is_ssl']        = true;
$GLOBALS['_sent_headers']  = array();
$GLOBALS['_registered_cb'] = null;

function is_admin() {
	return $GLOBALS['_is_admin'];
}
function headers_sent() {
	return $GLOBALS['_headers_sent'];
}
function is_ssl() {
	return $GLOBALS['_is_ssl'];
}
function header( $value ) {
	$GLOBALS['_sent_headers'][] = $value;
}
function add_action( $hook, $callback ) {
	if ( $hook === 'send_headers' ) {
		$GLOBALS['_registered_cb'] = $callback;
	}
}

// ─────────────── extract the real add_action('send_headers', ...) block ───
$source = file_get_contents( dirname( __DIR__ ) . '/inc/security-headers.php' );
if ( ! preg_match( '/(add_action\(\'send_headers\',\s*function\s*\(\)\s*\{.*?\r?\n\}\);)/s', $source, $m ) ) {
	fwrite( STDERR, "FAIL: could not locate the send_headers add_action() block in inc/security-headers.php\n" );
	exit( 1 );
}
eval( "namespace Prospergenics\\Test1669;\n" . $m[1] );

// ─────────────────────────────────────────────────────────────────────────
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}
function reset_state() {
	$GLOBALS['_is_admin']     = false;
	$GLOBALS['_headers_sent'] = false;
	$GLOBALS['_is_ssl']       = true;
	$GLOBALS['_sent_headers'] = array();
}
function has_header_starting_with( $prefix ) {
	foreach ( $GLOBALS['_sent_headers'] as $h ) {
		if ( strpos( $h, $prefix ) === 0 ) {
			return true;
		}
	}
	return false;
}

echo "Registration:\n";
ok( is_callable( $GLOBALS['_registered_cb'] ), 'send_headers callback was registered' );

echo "\nNormal HTTPS front-end request:\n";
reset_state();
call_user_func( $GLOBALS['_registered_cb'] );
ok( has_header_starting_with( 'Strict-Transport-Security:' ), 'sends Strict-Transport-Security over HTTPS' );
ok( has_header_starting_with( 'X-Content-Type-Options: nosniff' ), 'sends X-Content-Type-Options: nosniff' );
ok( has_header_starting_with( 'X-Frame-Options: SAMEORIGIN' ), 'sends X-Frame-Options: SAMEORIGIN' );
ok( has_header_starting_with( "Content-Security-Policy: default-src 'self'" ), 'sends a Content-Security-Policy' );
ok( count( $GLOBALS['_sent_headers'] ) === 4, 'sends exactly 4 headers on a plain HTTPS request' );

echo "\nPlain HTTP request (no HSTS):\n";
reset_state();
$GLOBALS['_is_ssl'] = false;
call_user_func( $GLOBALS['_registered_cb'] );
ok( ! has_header_starting_with( 'Strict-Transport-Security:' ), 'does NOT send Strict-Transport-Security over plain HTTP' );
ok( has_header_starting_with( 'X-Content-Type-Options:' ), 'still sends X-Content-Type-Options over plain HTTP' );

echo "\nwp-admin request:\n";
reset_state();
$GLOBALS['_is_admin'] = true;
call_user_func( $GLOBALS['_registered_cb'] );
ok( count( $GLOBALS['_sent_headers'] ) === 0, 'sends no headers at all when is_admin() is true' );

echo "\nHeaders already sent:\n";
reset_state();
$GLOBALS['_headers_sent'] = true;
call_user_func( $GLOBALS['_registered_cb'] );
ok( count( $GLOBALS['_sent_headers'] ) === 0, 'sends no headers at all when headers_sent() is true' );

echo "\n";
if ( $failures > 0 ) {
	echo "FAILED: $failures assertion(s) failed.\n";
	exit( 1 );
}
echo "PASSED: all assertions succeeded.\n";
exit( 0 );

<?php
/**
 * Standalone test for JengoWork task 1438: prospergenics_security_txt_content() must emit a
 * valid RFC 9116 body (Contact + Expires + Preferred-Languages + Canonical), and
 * prospergenics_maybe_serve_security_txt() must intercept exactly /.well-known/security.txt and
 * /security.txt (with or without a trailing slash) and leave every other request untouched.
 *
 * This extracts the real functions straight out of inc/security-txt.php via a regex, so the
 * test always exercises the actual current source rather than a copy that can drift from it.
 *
 * Run with: php tests/test-1438-security-txt.php
 */

// ────────────────────── WP core function stubs ───────────────────────────
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}
$GLOBALS['_admin_email'] = 'admin@prospergenics.com';
function get_option( $key, $default = false ) {
	return $key === 'admin_email' ? $GLOBALS['_admin_email'] : $default;
}
function add_action( ...$args ) {}

// ─────────────────── extract the real functions from inc/security-txt.php ─
$source = file_get_contents( dirname( __DIR__ ) . '/inc/security-txt.php' );
if ( ! preg_match( '/(function prospergenics_security_txt_content\(\)\s*\{.*?\r?\n\})/s', $source, $m1 ) ) {
	fwrite( STDERR, "FAIL: could not locate prospergenics_security_txt_content() in inc/security-txt.php\n" );
	exit( 1 );
}
if ( ! preg_match( '/(function prospergenics_maybe_serve_security_txt\(\)\s*\{.*?\r?\n\})\r?\nadd_action/s', $source, $m2 ) ) {
	fwrite( STDERR, "FAIL: could not locate prospergenics_maybe_serve_security_txt() in inc/security-txt.php\n" );
	exit( 1 );
}
eval( $m1[1] );
eval( $m2[1] );

// ─── child-process probe mode: must run BEFORE any test output below, since it shares this
// same file and its stdout is captured verbatim by the parent's shell_exec() ───────────────
if ( isset( $argv[1] ) && $argv[1] === '--probe' ) {
	$_SERVER['REQUEST_URI'] = $argv[2];
	prospergenics_maybe_serve_security_txt();
	// Only reached when the function returned normally (no match) -- on a match it calls
	// exit() from inside itself, straight after echoing the security.txt body, so this line
	// never runs and the parent sees the real body instead of this marker.
	echo 'PASSED-THROUGH';
	exit( 0 );
}

// ─────────────────────────────────────────────────────────────────────────
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

echo "[Test 1] security.txt body has all four RFC 9116 fields with sane values\n";
$body = prospergenics_security_txt_content();
ok( strpos( $body, 'Contact: mailto:admin@prospergenics.com' ) !== false, 'Contact reuses the site admin_email' );
ok( (bool) preg_match( '/^Expires: \d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/m', $body ), 'Expires is a well-formed UTC timestamp' );
ok( strpos( $body, 'Preferred-Languages: en' ) !== false, 'Preferred-Languages is present' );
ok( strpos( $body, 'Canonical: https://prospergenics.com/.well-known/security.txt' ) !== false, 'Canonical points at the well-known path' );
preg_match( '/^Expires: (\S+)$/m', $body, $em );
ok( strtotime( $em[1] ) > time() + ( 300 * 86400 ), 'Expires is comfortably in the future (>300 days out)' );

echo "\n[Test 2] request-matching only intercepts the two security.txt paths (exit() observed via a child process)\n";
function serves_security_txt( $uri ) {
	$cmd = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __FILE__ ) . ' --probe ' . escapeshellarg( $uri );
	$out = trim( (string) shell_exec( $cmd ) );
	// A non-match echoes exactly the PASSED-THROUGH marker; a match echoes the real
	// security.txt body (via exit() inside the handler) instead, so anything else is a match.
	return $out === 'PASSED-THROUGH' ? 'PASSED-THROUGH' : 'MATCHED';
}

ok( serves_security_txt( '/.well-known/security.txt' ) === 'MATCHED', '/.well-known/security.txt matches' );
ok( serves_security_txt( '/.well-known/security.txt/' ) === 'MATCHED', 'trailing slash still matches' );
ok( serves_security_txt( '/security.txt' ) === 'MATCHED', '/security.txt fallback matches' );
ok( serves_security_txt( '/security.txt?foo=bar' ) === 'MATCHED', 'query string is stripped before matching' );
ok( serves_security_txt( '/' ) === 'PASSED-THROUGH', 'homepage is untouched' );
ok( serves_security_txt( '/.well-known/other.txt' ) === 'PASSED-THROUGH', 'unrelated well-known path is untouched' );

echo "\n" . ( $failures === 0 ? "ALL PASSED\n" : "$failures FAILURE(S)\n" );
exit( $failures === 0 ? 0 : 1 );

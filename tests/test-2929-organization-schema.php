<?php
/**
 * Standalone test for JengoWork task 2929: prospergenics_organization_schema() must emit a
 * front-page Organization JSON-LD block describing Prospergenics as a done-for-you agentic AI
 * implementation team (not a coding-education community), and must emit nothing on any other
 * page.
 *
 * This extracts the real function body straight out of functions.php via a regex, so the test
 * always exercises the actual current source rather than a copy that can drift from it.
 *
 * Run with: php tests/test-2929-organization-schema.php
 */

// ────────────────────── WP core function stubs ───────────────────────────
function get_bloginfo( $key = '' ) {
	if ( $key === 'name' ) return 'Prospergenics';
	return '';
}
function home_url( $path = '/' ) { return 'https://prospergenics.com' . $path; }
function __( $text, $domain = 'default' ) { return $text; }
function add_action( ...$args ) {}
function wp_json_encode( $data ) { return json_encode( $data ); }

$GLOBALS['_is_front_page'] = false;
function is_front_page() { return $GLOBALS['_is_front_page']; }

// ─────────────────── extract the real function from functions.php ────────
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
if ( ! preg_match( '/(function prospergenics_organization_schema\(\)\s*\{.*?\r?\n\})\r?\nadd_action\( \'wp_head\'/s', $source, $m ) ) {
	fwrite( STDERR, "FAIL: could not locate prospergenics_organization_schema() in functions.php\n" );
	exit( 1 );
}
eval( $m[1] );

// ─────────────────────────────────────────────────────────────────────────
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

function render( $callback ) {
	ob_start();
	$callback();
	return ob_get_clean();
}

echo "[Test 1] Non-front-page request: no Organization schema is emitted\n";
$GLOBALS['_is_front_page'] = false;
$output = render( 'prospergenics_organization_schema' );
ok( $output === '', 'output is empty when is_front_page() is false' );

echo "\n[Test 2] Front page: Organization schema is emitted with the repositioned description\n";
$GLOBALS['_is_front_page'] = true;
$output = render( 'prospergenics_organization_schema' );
ok( strpos( $output, 'application/ld+json' ) !== false, 'a JSON-LD script tag is emitted' );

if ( ! preg_match( '/<script[^>]*prospergenics-organization-schema[^>]*>(.*?)<\/script>/s', $output, $jsonMatch ) ) {
	ok( false, 'could not locate the organization schema <script> tag in the output' );
} else {
	$data = json_decode( $jsonMatch[1], true );
	ok( is_array( $data ), 'schema JSON decodes successfully' );
	ok( ( $data['@type'] ?? '' ) === 'Organization', '@type is Organization' );
	ok( ( $data['name'] ?? '' ) === 'Prospergenics', 'name matches the site name' );
	ok( strpos( $data['description'] ?? '', 'done-for-you agentic AI' ) !== false, 'description states the done-for-you agentic AI positioning' );
	ok( strpos( $data['description'] ?? '', 'Netherlands' ) !== false && strpos( $data['description'] ?? '', 'Kenya' ) !== false, 'description names both the Netherlands and Kenya teams' );
	ok( stripos( $data['description'] ?? '', 'coaching' ) === false, 'description does not use the "coaching community" framing being moved away from' );
	ok( in_array( 'Agentic AI', $data['knowsAbout'] ?? array(), true ), 'knowsAbout lists Agentic AI' );
	ok( in_array( 'Netherlands', $data['areaServed'] ?? array(), true ) && in_array( 'Kenya', $data['areaServed'] ?? array(), true ), 'areaServed lists Netherlands and Kenya' );
}

echo "\n" . ( $failures === 0 ? "All assertions passed.\n" : "$failures assertion(s) FAILED.\n" );
exit( $failures === 0 ? 0 : 1 );

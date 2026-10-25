<?php
/**
 * Standalone test for the 2026 theme's SEO and security modules (docs/AUDIT-2026-10.md, C2,
 * M2, M4): wp-theme/prospergenics-2026/inc/seo.php and inc/security.php.
 *
 * Loads the real modules with minimal WordPress stubs and checks:
 *   - security headers (CSP with worker-src blob:, strict Report-Only policy, HSTS on HTTPS
 *     only, Referrer-Policy, Permissions-Policy that keeps the chat microphone),
 *   - security.txt (RFC 9116 required fields, expiry one year out),
 *   - noindex for every host except prospergenics.com / www,
 *   - meta description helpers (homepage text length, word-boundary summaries),
 *   - Organization + WebSite JSON-LD with a working SearchAction and the WhatsApp contact,
 *   - hardening hooks are registered (XML-RPC, generator, users endpoint, author archives),
 *   - functions.php loads both modules and the build copies wp/inc/.
 *
 * Run with: php tests/test-2026-seo-security.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ────────────────────── WordPress stubs ──────────────────────
define( 'ABSPATH', __DIR__ . '/' );
define( 'YEAR_IN_SECONDS', 365 * 24 * 3600 );
$GLOBALS['_hooks'] = array();
function add_action( $hook, $cb, $prio = 10 ) { $GLOBALS['_hooks'][] = array( 'add', $hook, $cb ); }
function add_filter( $hook, $cb, $prio = 10 ) { $GLOBALS['_hooks'][] = array( 'add', $hook, $cb ); }
function remove_action( $hook, $cb, $prio = 10 ) { $GLOBALS['_hooks'][] = array( 'remove', $hook, $cb ); }
function remove_filter( $hook, $cb, $prio = 10 ) { $GLOBALS['_hooks'][] = array( 'remove', $hook, $cb ); }
function apply_filters( $hook, $value ) { return $value; }
function home_url( $path = '' ) { return 'https://prospergenics.com' . $path; }
function get_template_directory_uri() { return 'https://prospergenics.com/wp-content/themes/prospergenics-2026'; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function strip_shortcodes( $s ) { return $s; }
function hooked( $kind, $hook, $cb = null ) {
	foreach ( $GLOBALS['_hooks'] as $h ) {
		if ( $h[0] === $kind && $h[1] === $hook && ( null === $cb || $h[2] === $cb ) ) { return true; }
	}
	return false;
}

require $theme . '/inc/security.php';
require $theme . '/inc/seo.php';

echo "Security headers\n";
$h = prospergenics_2026_security_headers( true );
foreach ( array( 'X-Content-Type-Options', 'X-Frame-Options', 'Referrer-Policy', 'Permissions-Policy', 'Content-Security-Policy', 'Content-Security-Policy-Report-Only', 'Strict-Transport-Security' ) as $name ) {
	ok( ! empty( $h[ $name ] ), "$name is sent" );
}
ok( false !== strpos( $h['Content-Security-Policy'], "worker-src 'self' blob:" ), 'enforced CSP allows the WordPress emoji blob: worker' );
ok( false !== strpos( $h['Content-Security-Policy'], "object-src 'none'" ) && false !== strpos( $h['Content-Security-Policy'], "frame-ancestors 'self'" ), 'enforced CSP blocks plugins and cross-origin framing' );
ok( false === strpos( $h['Content-Security-Policy-Report-Only'], 'unsafe-eval' ) && false === strpos( $h['Content-Security-Policy-Report-Only'], ' https:;' ), 'report-only CSP is a strict allowlist (no unsafe-eval, no blanket https:)' );
ok( false !== strpos( $h['Content-Security-Policy-Report-Only'], 'https://coaching.martiendejong.nl' ), 'report-only CSP allows the CoachOS chat' );
$ro = array();
foreach ( explode( '; ', $h['Content-Security-Policy-Report-Only'] ) as $d ) { $p = explode( ' ', $d ); $ro[ array_shift( $p ) ] = $p; }
ok( in_array( 'https://static.cloudflareinsights.com', $ro['script-src'], true ) && in_array( 'https://cloudflareinsights.com', $ro['connect-src'], true ), 'report-only CSP allows Cloudflare Web Analytics (beacon script and its endpoint)' );
ok( in_array( 'https://fonts.googleapis.com', $ro['style-src'], true ) && in_array( 'https://fonts.gstatic.com', $ro['font-src'], true ), 'report-only CSP allows the Google Fonts that jengo-listings loads' );
ok( false === strpos( $h['Content-Security-Policy-Report-Only'], 's.w.org' ), 'report-only CSP does not need s.w.org (emoji script is removed)' );
ok( hooked( 'remove', 'wp_head', 'print_emoji_detection_script' ) && hooked( 'remove', 'wp_print_styles', 'print_emoji_styles' ), 'WordPress emoji script and styles are removed' );
ok( false !== strpos( $h['Permissions-Policy'], 'microphone=(self)' ) && false !== strpos( $h['Permissions-Policy'], 'camera=()' ), 'Permissions-Policy keeps the chat microphone, blocks the camera' );
ok( ! isset( prospergenics_2026_security_headers( false )['Strict-Transport-Security'] ), 'no HSTS over plain HTTP' );

echo "security.txt\n";
$now = 1793000000;
$txt = prospergenics_2026_security_txt( 'security@example.com', $now, 'https://prospergenics.com/.well-known/security.txt' );
ok( false !== strpos( $txt, "Contact: mailto:security@example.com\n" ), 'has Contact' );
ok( false !== strpos( $txt, 'Expires: ' . gmdate( 'Y-m-d\TH:i:s\Z', $now + YEAR_IN_SECONDS ) ), 'expires one year out' );
ok( false !== strpos( $txt, 'Canonical: https://prospergenics.com/.well-known/security.txt' ), 'has Canonical' );

echo "Indexing\n";
ok( prospergenics_2026_is_production_host( 'prospergenics.com' ), 'prospergenics.com may be indexed' );
ok( prospergenics_2026_is_production_host( 'WWW.prospergenics.com' ), 'www.prospergenics.com may be indexed (case-insensitive)' );
foreach ( array( 'test.prospergenics.com', 'lessy.prospergenics.com', 'localhost', '' ) as $host ) {
	ok( ! prospergenics_2026_is_production_host( $host ), "'$host' is noindex" );
}
ok( hooked( 'add', 'wp_robots' ), 'noindex is applied through wp_robots' );

echo "Meta descriptions\n";
$fp = prospergenics_2026_front_page_meta();
$len = mb_strlen( $fp['description'] );
ok( $len >= 70 && $len <= 160, "homepage description is $len characters (70-160)" );
ok( 'ProsperGenics — Create value' === $fp['title'], 'homepage sharing title matches the page title' );
ok( 'Blog post body. Section More.' === prospergenics_2026_summarize( '<p>Blog post body.</p><h2>Section</h2><p>More.</p>' ), 'blocks are separated by spaces' );
$long = prospergenics_2026_summarize( str_repeat( 'value creation ', 30 ) );
ok( mb_strlen( $long ) <= 155 && '…' === mb_substr( $long, -1 ) && false === strpos( $long, 'creatio…' ), 'long text is cut on a word boundary with an ellipsis' );

echo "Structured data\n";
$schema = prospergenics_2026_front_page_schema();
$types  = array_map( function ( $n ) { return $n['@type']; }, $schema['@graph'] );
ok( array( 'Organization', 'WebSite' ) === $types, 'Organization and WebSite nodes' );
ok( false !== strpos( $schema['@graph'][1]['potentialAction']['target'], '?s={search_term_string}' ), 'SearchAction points at WordPress search' );
ok( '+254741619743' === $schema['@graph'][0]['contactPoint']['telephone'], 'contact point uses the WhatsApp number' );
ok( false !== json_encode( $schema ), 'schema encodes as JSON' );

echo "Hardening\n";
ok( hooked( 'add', 'xmlrpc_enabled' ) && hooked( 'add', 'xmlrpc_methods' ), 'XML-RPC methods are disabled' );
ok( hooked( 'remove', 'wp_head', 'wp_generator' ), 'generator tag is removed' );
ok( hooked( 'add', 'rest_endpoints' ), 'users endpoint filter is registered' );
$security_src = file_get_contents( $theme . '/inc/security.php' );
ok( false !== strpos( $security_src, "is_author() && ! is_user_logged_in()" ), 'author archives 404 for visitors' );
ok( false !== strpos( $security_src, "defined( 'XMLRPC_REQUEST' )" ), 'xmlrpc.php is refused outright' );

echo "Wiring\n";
$functions = file_get_contents( $theme . '/functions.php' );
ok( false !== strpos( $functions, "/inc/security.php'" ) && false !== strpos( $functions, "/inc/seo.php'" ), 'functions.php loads both modules' );
ok( false !== strpos( file_get_contents( $root . '/design/new-hero-2026-10/build-wp-theme.py' ), '"inc"' ), 'build script copies wp/inc/' );
ok( file_exists( $theme . '/assets/og-image.jpg' ), 'sharing image exists' );
ok( false === strpos( file_get_contents( $theme . '/page-about.php' ), 'name="robots"' ), 'About page has no hard-coded noindex (would leak into production)' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

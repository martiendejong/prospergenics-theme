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

// for inc/site-chrome.php (organisation description) and the homepage / listings checks
$GLOBALS['_front'] = false; $GLOBALS['_listings'] = false; $GLOBALS['_options'] = array( 'permalink_structure' => '/%postname%/' );
function is_front_page() { return $GLOBALS['_front']; }
function wp_style_is( $h, $l = 'enqueued' ) { return $GLOBALS['_listings']; }
function wp_script_is( $h, $l = 'enqueued' ) { return false; }
function wp_unslash( $v ) { return $v; }
function untrailingslashit( $s ) { return rtrim( $s, '/\\' ); }
function trailingslashit( $s ) { return untrailingslashit( $s ) . '/'; }
function user_trailingslashit( $s, $type = '' ) { return trailingslashit( $s ); }
function get_option( $k, $d = false ) { return isset( $GLOBALS['_options'][ $k ] ) ? $GLOBALS['_options'][ $k ] : $d; }
function add_query_arg( $k, $v, $url ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $k . '=' . $v; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }

require $theme . '/inc/security.php';
require $theme . '/inc/seo.php';
require $theme . '/inc/site-chrome.php';

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

echo "One organisation description (CON-13)\n";
$org = json_decode( file_get_contents( $theme . '/inc/site-chrome/organisation.json' ), true );
ok( is_array( $org ) && '' !== $org['short'] && '' !== $org['long'], 'organisation.json has a short and a long description' );
ok( mb_strlen( $org['short'] ) <= 160, 'the short description fits a meta description (' . mb_strlen( $org['short'] ) . ' characters)' );
ok( $org['short'] === prospergenics_2026_org_description( 'short' ) && $org['long'] === prospergenics_2026_org_description( 'long' ), 'PHP reads both from organisation.json' );
ok( $org['short'] === $fp['description'], 'homepage meta description and og:description use the short text' );
ok( $org['long'] === $schema['@graph'][0]['description'], 'Organization JSON-LD uses the long text' );
ok( false !== strpos( prospergenics_2026_site_footer(), '<p>' . esc_html( $org['short'] ) . '</p>' ), 'WordPress footer uses the short text' );
$design = $root . '/design/new-hero-2026-10';
$static = array( 'index.html', 'about/index.html', 'experts/index.html', 'community/index.html', 'people/lessy/index.html' );
foreach ( $static as $f ) {
	ok( false !== strpos( file_get_contents( $design . '/' . $f ), '<p>' . htmlspecialchars( $org['short'], ENT_QUOTES ) . '</p>' ), "static footer of $f uses the short text" );
}
$about = file_get_contents( $design . '/about/index.html' );
ok( false !== strpos( $about, '<meta name="description" content="' . htmlspecialchars( $org['short'], ENT_QUOTES ) . '" />' ) && false !== strpos( $about, '<meta property="og:description" content="' . htmlspecialchars( $org['short'], ENT_QUOTES ) . '" />' ), 'About meta and og:description use the short text' );
$copies = 0;
foreach ( array( 'wp/inc/seo.php', 'wp/inc/site-chrome.php', 'site_chrome.py', 'about/index.html', 'index.html' ) as $f ) {
	$src = file_get_contents( $design . '/' . $f );
	foreach ( array( 'researching and practising how value is created', 'learning by doing, empowering people', 'to find out how value is actually created', 'that creates value together', 'We build, learn and measure' ) as $old ) {
		if ( false !== strpos( preg_replace( '#<!-- site-chrome:(meta|footer) -->.*?<!-- /site-chrome:\\1 -->#s', '', $src ), $old ) ) { $copies++; echo "    hand-written copy of \"$old\" in $f\n"; }
	}
}
ok( 0 === $copies, 'no hand-written organisation description left in the sources' );

echo "Homepage tags only on the homepage (CON-7)\n";
$_SERVER['REQUEST_URI'] = '/';
$GLOBALS['_front'] = true;
ok( prospergenics_2026_is_homepage_request(), '/ is the homepage' );
$_SERVER['REQUEST_URI'] = '/?utm_source=x';
ok( prospergenics_2026_is_homepage_request(), '/ with a query string is the homepage' );
$_SERVER['REQUEST_URI'] = '/members/';
ok( ! prospergenics_2026_is_homepage_request(), '/members/ is not, even though is_front_page() is true there (jengo-listings)' );
$GLOBALS['_listings'] = true;
$_SERVER['REQUEST_URI'] = '/';
ok( ! prospergenics_2026_is_homepage_request(), 'a listings page is never the homepage' );
$_SERVER['REQUEST_URI'] = '/courses/html/';
$lm = prospergenics_2026_current_meta();
ok( 'https://prospergenics.com/courses/html/' === $lm['url'] && empty( $lm['title'] ) && empty( $lm['description'] ), 'a listings page gets its own og:url, and no homepage title or description' );
$rows = prospergenics_2026_meta_tags( $lm );
$names = array_map( function ( $r ) { return $r[1]; }, $rows );
ok( in_array( 'og:url', $names, true ) && ! in_array( 'og:title', $names, true ) && ! in_array( 'description', $names, true ), 'empty values print no tag (the plugin prints its own description)' );
$GLOBALS['_listings'] = false; $GLOBALS['_front'] = false;
$seo_src = file_get_contents( $theme . '/inc/seo.php' );
ok( 1 === preg_match( "#remove_action\\( 'wp_head', 'rel_canonical' \\)#", $seo_src ), "core's second canonical (to the homepage) is removed on listings pages" );
ok( 2 === substr_count( $seo_src, 'prospergenics_2026_is_homepage_request() )' ), 'sharing tags and Organization schema both use the homepage check' );

echo "Paged canonicals (ARC-22)\n";
ok( 'https://prospergenics.com/articles/' === prospergenics_2026_paged_url( 'https://prospergenics.com/articles/', 1 ), 'page 1 is the listing itself' );
ok( 'https://prospergenics.com/articles/page/3/' === prospergenics_2026_paged_url( 'https://prospergenics.com/articles/', 3 ), 'page 3 keeps its number' );
$GLOBALS['_options']['permalink_structure'] = '';
ok( 'https://prospergenics.com/?post_type=pg_article&paged=2' === prospergenics_2026_paged_url( 'https://prospergenics.com/?post_type=pg_article', 2 ), 'plain permalinks use ?paged=' );

echo "Listing canonicals and noindex (ARC-22)\n";
$art = file_get_contents( $theme . '/inc/articles/seo.php' );
ok( false !== strpos( $art, "get_term_link( get_queried_object() ) : get_post_type_archive_link( 'pg_article' )" ) && false !== strpos( $art, 'prospergenics_2026_paged_url( $base, $paged )' ), '/articles/ (and its categories, tags, series) canonical: the listing without ?sort=, page kept' );
ok( false !== strpos( $art, '<link rel="canonical" href="\' . esc_url( $canonical )' ), 'articles listings print the canonical' );
ok( 1 === preg_match( "#prospergenics_articles_is_listing\\(\\) && 'newest' !== prospergenics_articles_sort\\(\\) \\) \\{\\s*\\\$robots\\['noindex'\\] = true;#", $art ), '/articles/?sort=trending is noindex' );
$crs = file_get_contents( $theme . '/inc/courses/routing.php' );
ok( false !== strpos( $crs, "esc_url( prospergenics_courses_archive_canonical() )" ), '/courses/ prints a canonical' );
ok( false !== strpos( $crs, "is_post_type_archive( 'pg_course' ) && prospergenics_courses_filters_active( prospergenics_courses_current_filters() )" ), 'filtered or re-sorted /courses/ is noindex' );
eval( preg_match( '/(function prospergenics_courses_filters_active\(.*?\n\})/s', $crs, $fm ) ? $fm[1] : '' );
ok( ! prospergenics_courses_filters_active( array( 'category' => '', 'level' => '', 'sort' => 'newest' ) ), 'the plain archive is not filtered' );
ok( prospergenics_courses_filters_active( array( 'category' => '', 'level' => 'beginner', 'sort' => 'newest' ) ) && prospergenics_courses_filters_active( array( 'category' => '', 'level' => '', 'sort' => 'popular' ) ), '?level= and ?sort=popular are' );

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

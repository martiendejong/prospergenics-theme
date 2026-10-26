<?php
/**
 * Standalone test for the 2026 theme's sitemap module (wp-theme/prospergenics-2026/inc/sitemap.php).
 *
 * The static /people/<slug>/, /experts/ and /community/ pages live outside WordPress, so no
 * sitemap listed them. This checks:
 *   - only static pages that exist on disk are listed, with safe slugs and a lastmod,
 *   - the urlset XML is valid and escapes URLs,
 *   - the module is loaded, adds itself to WordPress's sitemap, Yoast's index and robots.txt,
 *     drops the core users sitemap (author archives 404 for visitors) and 404s unknown sitemaps.
 *
 * Run with: php tests/test-2026-sitemap.php
 */

$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['_hooks'] = array();
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][] = $hook; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][] = $hook; }
require $theme . '/inc/sitemap.php';

// A fake site root with a few static pages, one stray folder and one empty profile folder.
$root = sys_get_temp_dir() . '/pg-sitemap-test-' . getmypid();
foreach ( array( 'people/lessy', 'people/martien', 'people/Bad Slug', 'people/empty', 'experts', 'community' ) as $dir ) {
	@mkdir( "$root/$dir", 0777, true );
}
foreach ( array( 'people/lessy', 'people/martien', 'people/Bad Slug', 'experts', 'community', 'people' ) as $dir ) {
	file_put_contents( "$root/$dir/index.html", '<html></html>' );
}
touch( "$root/people/lessy/index.html", 1793000000 );

echo "Static page list\n";
$urls = prospergenics_2026_static_page_urls( $root, 'https://prospergenics.com/' );
$locs = array_column( $urls, 'loc' );
sort( $locs );
ok( array(
	'https://prospergenics.com/community/',
	'https://prospergenics.com/experts/',
	'https://prospergenics.com/people/lessy/',
	'https://prospergenics.com/people/martien/',
) === $locs, 'lists the 4 real pages: profiles with an index.html and a safe slug, plus both lists (not the /people/ redirect)' );
$lessy = array_values( array_filter( $urls, function ( $u ) { return false !== strpos( $u['loc'], 'lessy' ); } ) )[0];
ok( '2026-10-26T07:33:20Z' === $lessy['lastmod'], 'lastmod is the file time in W3C format' );
ok( array() === prospergenics_2026_static_page_urls( $root . '/nowhere', 'https://prospergenics.com/' ), 'missing folders give an empty list, not an error' );

echo "XML\n";
$xml = prospergenics_2026_urlset_xml( array_merge( $urls, array( array( 'loc' => 'https://prospergenics.com/people/a&b/', 'lastmod' => '2026-10-24T00:00:00Z' ) ) ) );
$doc = new DOMDocument();
ok( @$doc->loadXML( $xml ), 'urlset is well-formed XML' );
ok( 'http://www.sitemaps.org/schemas/sitemap/0.9' === $doc->documentElement->namespaceURI, 'uses the sitemaps.org namespace' );
ok( false !== strpos( $xml, 'a&amp;b' ), 'URLs are XML-escaped' );
ok( 5 === $doc->getElementsByTagName( 'url' )->length, 'one <url> per page' );
$xml = prospergenics_2026_urlset_xml( array( array( 'loc' => 'https://prospergenics.com/in/amina/' ) ) );
ok( @$doc->loadXML( $xml ) && false === strpos( $xml, '<lastmod>' ), 'an entry without lastmod (profiles) prints none' );

echo "Wiring\n";
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "/inc/sitemap.php'" ), 'functions.php loads the sitemap module' );
foreach ( array( 'init', 'wpseo_sitemap_index', 'robots_txt', 'wp_sitemaps_add_provider', 'template_redirect' ) as $hook ) {
	ok( in_array( $hook, $GLOBALS['_hooks'], true ), "hooks into $hook" );
}
$src = file_get_contents( $theme . '/inc/sitemap.php' );
ok( false !== strpos( $src, "'users' === \$name ? false" ), 'core users sitemap is dropped' );
ok( false !== strpos( $src, "get_query_var( 'sitemap' )" ) && false !== strpos( $src, 'status_header( 404 )' ), 'unknown sitemap names 404' );

echo "No URL in two sitemaps; listings and profiles listed (ARC-22)\n";
ok( false !== strpos( $src, "'pgpeople', new Prospergenics_2026_Url_List_Sitemap( 'pgpeople', 'prospergenics_2026_current_static_urls' )" ), 'the static pages (/people/ is the person URL for now) stay in the core sitemap' );
ok( false !== strpos( $src, "'pglistings', 'prospergenics_2026_listing_urls'" ), '/articles/ and /courses/ have a provider' );
ok( false !== strpos( $src, "'pgprofiles', 'prospergenics_profiles_sitemap_urls'" ), 'indexable /in/ profiles have a provider' );
ok( false !== strpos( $src, "add_filter( 'wp_sitemaps_posts_query_args'" ) && false !== strpos( $src, "\$args['post__not_in']" ), 'the page the /courses/ archive shadows leaves the page sitemap' );
ok( 1 === preg_match( "#core_sitemaps_enabled\\(\\) \\) \\{\\s*wp_safe_redirect\\( home_url\\( '/wp-sitemap-pgpeople-1.xml' \\), 301 \\)#", $src ), '/people-sitemap.xml 301s to the core provider while the core sitemap is on' );
ok( 1 === preg_match( "#add_filter\\( 'robots_txt', function \\( \\\$output \\) \\{\\s*if \\( prospergenics_2026_core_sitemaps_enabled\\(\\) \\) \\{\\s*return \\\$output;#", $src ), 'robots.txt names /people-sitemap.xml only when an SEO plugin replaced the core sitemap' );
ok( false !== strpos( $src, 'return call_user_func( $this->list ) ? 1 : 0;' ), 'an empty list is not in the sitemap index (no 404 sitemap)' );

array_map( 'unlink', glob( "$root/*/index.html" ) );
array_map( 'unlink', glob( "$root/people/*/index.html" ) );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

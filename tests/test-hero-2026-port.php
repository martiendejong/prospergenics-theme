<?php
/**
 * Standalone test for the 2026 design port: the static design in
 * design/new-hero-2026-10 now renders as the real theme's front page, with its CSS
 * and JS enqueued rather than inlined, and -- the whole reason for porting into this
 * theme instead of shipping the standalone generated prospergenics-2026 theme -- with
 * the theme's wp_head() SEO layer still intact.
 *
 * Guards the things a careless re-port or a hand-edit of the generated files would break:
 *   - the front page still goes through wp_head()/wp_footer() (so JSON-LD, meta
 *     descriptions, og/twitter tags and hreflang keep printing),
 *   - no second <title> is introduced (the SEO layer owns it),
 *   - every asset the templates reference actually exists in the theme,
 *   - no asset path was left relative to the design folder (those 404 under WordPress),
 *   - the design's CSS/JS are enqueued, not inlined back into the template.
 *
 * Run with: php tests/test-hero-2026-port.php
 */

$theme = dirname( __DIR__ );

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

$front     = file_get_contents( $theme . '/front-page.php' );
$functions = file_get_contents( $theme . '/functions.php' );
$header    = file_exists( $theme . '/header-2026.php' ) ? file_get_contents( $theme . '/header-2026.php' ) : '';

/**
 * Strip PHP comments so an assertion about markup is not tripped by a doc comment that
 * merely *discusses* that markup (header-2026.php explains why it prints no <title>).
 */
function strip_php_comments( $src ) {
	$src = preg_replace( '#/\*.*?\*/#s', '', $src );
	return preg_replace( '#^\s*//.*$#m', '', $src );
}
$footer    = file_exists( $theme . '/footer-2026.php' ) ? file_get_contents( $theme . '/footer-2026.php' ) : '';
$hero_css  = file_exists( $theme . '/css/hero-2026.css' ) ? file_get_contents( $theme . '/css/hero-2026.css' ) : '';
$hero_js   = file_exists( $theme . '/js/hero-2026.js' ) ? file_get_contents( $theme . '/js/hero-2026.js' ) : '';

echo "[Test 1] the ported templates exist and are wired together\n";
ok( '' !== $header, 'header-2026.php exists' );
ok( '' !== $footer, 'footer-2026.php exists' );
ok( '' !== $hero_css, 'css/hero-2026.css exists' );
ok( '' !== $hero_js, 'js/hero-2026.js exists' );
ok( false !== strpos( $front, "get_header( '2026' )" ), 'front-page.php uses the 2026 header part' );
ok( false !== strpos( $front, "get_footer( '2026' )" ), 'front-page.php uses the 2026 footer part' );
ok( false !== strpos( $front, '<main id="main-content"' ), 'front-page.php wraps the design in <main id="main-content"> (the skip link target)' );

echo "\n[Test 2] the wp_head()/wp_footer() SEO layer survives the port\n";
ok( false !== strpos( $header, 'wp_head()' ), 'header-2026.php calls wp_head() -- JSON-LD, meta description, og/twitter, hreflang all print here' );
ok( false !== strpos( $header, 'wp_body_open()' ), 'header-2026.php calls wp_body_open() (plugin injection point)' );
ok( false !== strpos( $footer, 'wp_footer()' ), 'footer-2026.php calls wp_footer() -- enqueued scripts print here' );
// A <title> in the template would collide with prospergenics_front_page_document_title()
// / the Yoast title, giving the page two. The design's hardcoded one is dropped on purpose.
ok( false === stripos( strip_php_comments( $header ), '<title' ), 'header-2026.php prints no <title> (the SEO layer owns it)' );
ok( false === stripos( strip_php_comments( $front ), '<title' ), 'front-page.php prints no <title>' );
// The functions that write into wp_head() must still be registered.
// Deliberately NOT listed: prospergenics_front_page_output_meta_description() (task 797).
// That front-page meta description is live on production but exists only on the unmerged
// branch fix/797-homepage-meta-description (PR #4), never in master -- so the port
// cannot regress it and this test must not demand it. See AGENT_PROGRESS.md.
foreach ( array(
	'prospergenics_organization_schema'            => 'Organization JSON-LD',
	'prospergenics_martien_person_schema'          => 'Person JSON-LD',
	'prospergenics_add_social_meta_tags'           => 'og/twitter tags',
	'prospergenics_output_hreflang_tags'           => 'hreflang tags',
	'prospergenics_speakable_selectors'            => 'speakable selectors',
) as $fn => $what ) {
	ok( false !== strpos( $functions, "function $fn" ), "functions.php still defines $fn() ($what)" );
}

echo "\n[Test 3] CSS/JS are enqueued, not inlined\n";
ok( false === strpos( $front, '<style' ), 'front-page.php contains no inline <style> block' );
ok( false !== strpos( $functions, "'/css/hero-2026.css'" ), 'functions.php enqueues css/hero-2026.css' );
ok( false !== strpos( $functions, "'/js/hero-2026.js'" ), 'functions.php enqueues js/hero-2026.js' );
ok( false !== strpos( $functions, "'/css/front-page-2026.css'" ), 'functions.php enqueues css/front-page-2026.css' );
// Scoped to the front page: every other template must keep the legacy look.
ok( 1 === preg_match( '/function prospergenics_hero_2026_assets\(\)\s*\{\s*if\s*\(\s*!\s*is_front_page\(\)\s*\)\s*\{\s*return;/', $functions ), 'the 2026 enqueues bail out when not on the front page' );
ok( false !== strpos( $functions, "wp_dequeue_style( 'prospergenics-style' )" ), 'the legacy stylesheet is dequeued on the front page (it would fight the design)' );
// The slider builds image URLs at runtime, so it needs the theme URL handed to it.
ok( false !== strpos( $functions, "'PG_HERO'" ), 'functions.php localizes PG_HERO for the slider asset paths' );
ok( false !== strpos( $hero_js, 'PG_HERO' ), 'js/hero-2026.js reads PG_HERO' );

echo "\n[Test 4] no asset path was left relative to the design folder\n";
// Under WordPress the front page is served from /, so a bare "assets/x.png" would 404.
ok( 0 === preg_match( '/(?:src|href)="assets\//', $front ), 'front-page.php has no bare src/href="assets/..."' );
ok( 0 === preg_match( '/url\(\s*[\'"]?assets\//', $front ), 'front-page.php has no bare url(assets/...) in inline styles' );
ok( 0 === preg_match( '/url\(\s*[\'"]?assets\//', $hero_css ), 'hero-2026.css has no bare url(assets/...) -- it sits one level deep, so it needs ../assets/' );
ok( false !== strpos( $front, 'get_template_directory_uri()' ), 'front-page.php resolves assets through get_template_directory_uri()' );

echo "\n[Test 5] every referenced asset exists in the theme\n";
$referenced = array();
preg_match_all( '#/assets/([A-Za-z0-9._-]+)#', $front . "\n" . $hero_css, $m );
$referenced = array_merge( $referenced, $m[1] );
preg_match_all( '#PG_ASSETS\s*\+\s*"([A-Za-z0-9._-]+)"#', $hero_js, $m );
$referenced = array_merge( $referenced, $m[1] );
$referenced = array_unique( $referenced );
ok( count( $referenced ) > 0, 'found asset references to check (' . count( $referenced ) . ' unique)' );
$missing = array();
foreach ( $referenced as $asset ) {
	if ( ! file_exists( $theme . '/assets/' . $asset ) ) { $missing[] = $asset; }
}
ok( empty( $missing ), 'every referenced asset exists in assets/' . ( $missing ? ' -- missing: ' . implode( ', ', $missing ) : '' ) );

echo "\n" . ( $failures === 0 ? "All assertions passed.\n" : "$failures assertion(s) FAILED.\n" );
exit( $failures === 0 ? 0 : 1 );

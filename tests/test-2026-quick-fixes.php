<?php
/**
 * Standalone regression test for the 2026 audit quick fixes (docs/AUDIT-2026-10.md):
 *   - C3: the generated /people/, /experts/ and /community/ pages must not contain
 *     doubled CSS braces ("{{" / "}}"), which browsers drop as invalid rules,
 *   - H2: single.php must go through wp_head()/wp_footer() (via header.php/footer.php),
 *   - no duplicate lang attribute on <html> (lang="en" plus language_attributes()),
 *   - C4: no theme image over 600 KB, and every asset a template references exists,
 *   - H1: the value test shows a privacy notice next to the email field,
 *   - C6/H4: real WhatsApp number, WhatsApp link not nested in the chat button, compact launcher,
 *   - M3: fonts are self-hosted (no Google Fonts), M1: homepage axe/Lighthouse fixes.
 *
 * Run with: php tests/test-2026-quick-fixes.php
 */

$root   = dirname( __DIR__ );
$design = $root . '/design/new-hero-2026-10';
$theme  = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

echo "Generated profile and list pages\n";
$pages = array_merge( glob( $design . '/people/*/index.html' ), array( $design . '/experts/index.html', $design . '/community/index.html' ) );
ok( count( $pages ) >= 3, count( $pages ) . ' generated pages found' );
foreach ( $pages as $page ) {
	$html = file_get_contents( $page );
	$rel  = substr( $page, strlen( $design ) + 1 );
	ok( false === strpos( $html, '{{' ) && false === strpos( $html, '}}' ), "$rel has no doubled CSS braces" );
}

echo "single.php\n";
// single.php gets wp_head()/wp_footer() through the shared header.php/footer.php.
$single = file_get_contents( $theme . '/single.php' );
ok( false !== strpos( $single, 'get_header()' ) && false !== strpos( file_get_contents( $theme . '/header.php' ), 'wp_head()' ), 'single.php reaches wp_head() via header.php' );
ok( false !== strpos( $single, 'get_footer()' ) && false !== strpos( file_get_contents( $theme . '/footer.php' ), 'wp_footer()' ), 'single.php reaches wp_footer() via footer.php' );

echo "<html> lang attribute\n";
foreach ( glob( $theme . '/*.php' ) as $tpl ) {
	$src = file_get_contents( $tpl );
	ok( ! preg_match( '#<html[^>]*lang="[^"]*"[^>]*language_attributes#', $src ), basename( $tpl ) . ' has no duplicate lang attribute' );
}

echo "Theme assets\n";
foreach ( glob( $theme . '/assets/*' ) as $asset ) {
	$kb = (int) ( filesize( $asset ) / 1024 );
	ok( $kb <= 600, basename( $asset ) . " is {$kb} KB (<= 600 KB)" );
}
$refs = array();
foreach ( array_merge( glob( $theme . '/*.php' ), $pages ) as $file ) {
	preg_match_all( '#assets/([A-Za-z0-9_.-]+\.(?:webp|png|jpe?g|svg))#', file_get_contents( $file ), $m );
	$refs = array_merge( $refs, $m[1] );
}
foreach ( array_unique( $refs ) as $ref ) {
	ok( file_exists( $theme . '/assets/' . $ref ), "referenced asset $ref exists" );
}

echo "Value test privacy notice\n";
$front = file_get_contents( $theme . '/front-page.php' );
ok( false !== strpos( $front, 'class="vt-privacy"' ), 'value test shows a privacy notice' );
ok( false !== strpos( $front, '/privacy-policy/' ), 'privacy notice links the privacy policy' );

echo "Chat launcher and WhatsApp (C6, H4)\n";
ok( false === strpos( $front, '254XXXXXXXXX' ), 'no placeholder WhatsApp number left' );
ok( false !== strpos( $front, 'https://wa.me/254741619743' ), 'WhatsApp links use the real number' );
ok( ! preg_match( '#<button class="launcher-orb"[^>]*>(?:(?!</button>).)*<a #s', $front ), 'WhatsApp link is not nested inside the chat button' );
ok( false !== strpos( $front, '<div class="launcher is-compact">' ), 'launcher starts compact (small even without JS)' );

echo "Self-hosted fonts (M3)\n";
$all_pages = array_merge( glob( $theme . '/*.php' ), $pages );
$google = array_filter( $all_pages, function ( $f ) { return false !== strpos( file_get_contents( $f ), 'fonts.googleapis.com' ); } );
ok( ! $google, 'no page loads Google Fonts' . ( $google ? ' (still: ' . implode( ', ', array_map( 'basename', $google ) ) . ')' : '' ) );
$fonts_css = file_exists( $theme . '/assets/fonts/fonts.css' ) ? file_get_contents( $theme . '/assets/fonts/fonts.css' ) : '';
preg_match_all( "#url\\('([^']+\\.woff2)'\\)#", $fonts_css, $fm );
ok( count( $fm[1] ) >= 10, count( $fm[1] ) . ' @font-face rules in fonts.css' );
foreach ( $fm[1] as $woff ) {
	ok( file_exists( $theme . '/assets/fonts/' . $woff ), "font file $woff exists" );
}
ok( false !== strpos( $fonts_css, 'font-display: swap' ), 'fonts use font-display: swap' );

echo "Accessibility (M1)\n";
ok( false !== strpos( $front, '<h2 id="scTitle">' ), 'showcase caption is an h2 (no skipped heading level after the h1)' );
ok( ! preg_match( '#<a class="pcard"[^>]*aria-label=#', $front ), 'card links take their name from their visible text' );
ok( false !== strpos( $front, '.sc-tab.active { background: var(--green-700)' ), 'active showcase tab uses the AA-contrast green' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

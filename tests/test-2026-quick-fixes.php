<?php
/**
 * Standalone regression test for the 2026 audit quick fixes (docs/AUDIT-2026-10.md):
 *   - C3: the generated /people/, /experts/ and /community/ pages must not contain
 *     doubled CSS braces ("{{" / "}}"), which browsers drop as invalid rules,
 *   - H2: single.php must go through wp_head()/wp_footer() (via header.php/footer.php),
 *   - no duplicate lang attribute on <html> (lang="en" plus language_attributes()),
 *   - C4: no theme image over 600 KB, and every asset a template references exists,
 *   - H1: the value test shows a privacy notice next to the email field.
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

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

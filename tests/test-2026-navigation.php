<?php
/**
 * Standalone test for the 2026 site header, mobile menu and homepage footer
 * (docs/AUDIT-2026-10.md, C5: the homepage had no navigation, footer or contact route,
 * and inner pages hid their links on phones with no way to open them).
 *
 * Guards, for the homepage (front-page.php) and inner pages (header.php/footer.php):
 *   - a <header> with the main nav links and a menu button,
 *   - the button is wired accessibly: type="button", aria-expanded="false",
 *     aria-controls naming the panel's id, an aria-label,
 *   - the mobile panel starts hidden and carries the same links,
 *   - the menu script is present (toggle + Escape),
 *   - the homepage has a <footer> with contact and legal links, and <main>.
 *
 * Run with: php tests/test-2026-navigation.php
 */

$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

$pages = array(
	'homepage'    => file_get_contents( $theme . '/front-page.php' ),
	'inner pages' => file_get_contents( $theme . '/header.php' ) . file_get_contents( $theme . '/footer.php' ),
);

foreach ( $pages as $label => $html ) {
	echo "$label\n";
	ok( (bool) preg_match( '#<header class="(site-header|pg-nav)"#', $html ), 'has a <header> bar' );
	foreach ( array( 'Courses', 'Members', 'Tools', 'Services', 'Partners', 'About' ) as $item ) {
		ok( substr_count( $html, ">$item</a>" ) >= 2, "$item is in both the desktop nav and the mobile panel" );
	}
	ok( (bool) preg_match( '#<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="([a-z-]+)" aria-label="Open menu">#', $html, $m ), 'menu button is type=button with aria-expanded, aria-controls and aria-label' );
	$panel_id = isset( $m[1] ) ? $m[1] : '';
	ok( '' !== $panel_id && false !== strpos( $html, 'id="' . $panel_id . '" hidden>' ), 'aria-controls names a panel that starts hidden' );
	ok( false !== strpos( $html, "getElementById('mobile-menu')" ) && false !== strpos( $html, "'Escape'" ), 'menu script toggles the panel and handles Escape' );
}

echo "homepage footer\n";
$home = $pages['homepage'];
ok( false !== strpos( $home, '<main id="main">' ) && false !== strpos( $home, '</main>' ), 'homepage content is wrapped in <main>' );
ok( false !== strpos( $home, '<footer class="site-footer">' ), 'homepage has a site footer' );
foreach ( array( '/contact/', '/privacy-policy/', '/cookie-policy/' ) as $href ) {
	ok( false !== strpos( $home, 'href="' . $href . '"' ), "footer links $href" );
}

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

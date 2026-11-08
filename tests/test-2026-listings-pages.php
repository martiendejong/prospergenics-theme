<?php
/**
 * Standalone test for the extra jengo-listings steps (inc/listings-pages.php), on the saved plugin
 * pages in tests/fixtures/jengo-listings/:
 *
 *   - card pictures (#6538): a directory card without an image (<div class="dc-initial">J</div>)
 *     gets the branded pattern of the /courses/ cards instead, one of three textures by slug
 *     (the same pick as the courses and /members/ cards); a card with an image, the "Active" pill
 *     and the rest of the card stay; through the takeover's page filter; the styles are in
 *     site-chrome.css, so they come with the takeover; other shapes are left alone.
 *
 * Run with: php tests/test-2026-listings-pages.php
 */

$root    = dirname( __DIR__ );
$theme   = $root . '/wp-theme/prospergenics-2026';
$design  = $root . '/design/new-hero-2026-10';
$fixture = __DIR__ . '/fixtures/jengo-listings';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ───────────────────── WP stubs ─────────────────────
const TEST_HOME = 'https://test.prospergenics.com';
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['__hooks'] = array();
function home_url( $path = '' ) { return TEST_HOME . '/' . ltrim( (string) $path, '/' ); }
function get_template_directory_uri() { return TEST_HOME . '/wp-content/themes/prospergenics-2026'; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
function wp_unslash( $v ) { return $v; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function add_query_arg( $key, $value = null, $url = null ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
function apply_filters( $tag, $value, ...$args ) {
	foreach ( isset( $GLOBALS['__hooks'][ $tag ] ) ? $GLOBALS['__hooks'][ $tag ] : array() as $cb ) { $value = $cb( $value, ...$args ); }
	return $value;
}
function add_action( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function is_user_logged_in() { return false; }
function wp_style_is( $h, $s ) { return false; }
function wp_script_is( $h, $s ) { return false; }

require $theme . '/inc/site-chrome.php';
require $theme . '/inc/listings-pages.php';

$chrome = array( 'header' => '<header class="site-header">HEADER</header>', 'footer' => '<footer class="site-footer">FOOTER</footer>', 'css' => '.x{}', 'script' => '<script>/*menu*/</script>' );
function variant( $slug ) { return abs( crc32( $slug ) ) % 3 + 1; }
function cards( $html ) {
	preg_match_all( '#<a class="dir-card" href="[^"]*/([a-z0-9-]+)/">\s*<div class="dc-thumb">(.*?)</div>\s*<div class="dc-body">#s', $html, $m, PREG_SET_ORDER );
	return $m;
}

echo "Card pictures: the branded pattern, no letter tiles (#6538)\n";
$services = file_get_contents( $fixture . '/services.html' );
$before   = cards( $services );
ok( count( $before ) >= 3 && count( $before ) === substr_count( $services, '<div class="dc-initial">' ), 'fixture: /services/ as the plugin makes it, ' . count( $before ) . ' cards with a letter tile' );
$out   = prospergenics_2026_listings_card_patterns( $services );
$after = cards( $out );
$all   = count( $after ) === count( $before );
foreach ( $after as $c ) {
	$all = $all && '<span class="pg-pattern pg-pattern--' . variant( $c[1] ) . '" aria-hidden="true"></span>' === trim( $c[2] );
}
ok( $all && false === strpos( $out, '<div class="dc-initial">' ), 'every card: the pattern (texture by slug, aria-hidden) and no letter' );
$thumbless = function ( $h ) { return preg_replace( '#<div class="dc-thumb">.*?</div>\s*(?=<div class="dc-body">)#s', '', $h ); };
ok( $thumbless( $out ) === $thumbless( $services ), 'everything outside the card pictures (titles, tags, links, the page) stays byte for byte' );
ok( prospergenics_2026_listings_card_patterns( $out ) === $out, 'running it twice changes nothing' );
$picks = array_map( 'variant', array( 'jengo', 'coachos', 'jengowork', 'research', 'provenance', 'mayiani', 'lou', 'farid' ) );
ok( array( 1, 2, 3 ) === array_values( array_intersect( array( 1, 2, 3 ), $picks ) ), 'all three textures are in use across the real slugs' );
ok( variant( 'coachos' ) === prospergenics_2026_pattern_variant( 'coachos' ) && ( abs( crc32( 'html' ) ) % 3 + 1 ) === prospergenics_2026_pattern_variant( 'html' ), 'the same pick as the /courses/ listings cards (crc32 of the slug)' );

// A partner card: the "Active" pill stays, on top of the pattern.
$partner = '<div class="dir-grid">' . "\n" . '<a class="dir-card" href="' . TEST_HOME . '/partners/bugatti-insights/">' . "\n"
	. '        <div class="dc-thumb">' . "\n" . '          <span class="dc-status">Active</span>                      <div class="dc-initial">B</div>' . "\n" . '                  </div>' . "\n"
	. '        <div class="dc-body"><div class="dc-title">Bugatti Insights</div></div>' . "\n" . '      </a></div>';
$p = prospergenics_2026_listings_card_patterns( $partner );
ok( false !== strpos( $p, '<div class="dc-thumb">' . "\n" . '          <span class="dc-status">Active</span><span class="pg-pattern pg-pattern--' . variant( 'bugatti-insights' ) . '" aria-hidden="true"></span></div>' ) && false === strpos( $p, 'dc-initial' ), 'a partner card keeps its "Active" pill, the letter becomes the pattern' );

// A card with an image (the theme's /members/ cards) or of another shape stays exactly as it is.
$img = str_replace( '<div class="dc-initial">B</div>', '<img src="x.webp" alt="Bugatti" /> <div class="dc-initial">B</div>', $partner );
ok( $img === prospergenics_2026_listings_card_patterns( $img ), 'a card with an image: untouched' );
$two = str_replace( '<div class="dc-initial">B</div>', '<div class="dc-initial">B</div><div class="dc-initial">I</div>', $partner );
ok( $two === prospergenics_2026_listings_card_patterns( $two ), 'two letters in one thumb (unknown shape): untouched' );
$other = str_replace( 'class="dc-thumb"', 'class="dc-thumbnail"', $partner );
ok( $other === prospergenics_2026_listings_card_patterns( $other ), 'another card markup: untouched' );
$changed = array();
foreach ( array( 'tool-detail', 'service-detail', 'member-detail', 'course-detail', 'course-learn' ) as $f ) {
	$h = file_get_contents( "$fixture/$f.html" );
	if ( prospergenics_2026_listings_card_patterns( $h ) !== $h ) {
		$changed[] = $f;
	}
}
ok( ! $changed, 'the detail, course and lesson pages (no directory cards): untouched' . ( $changed ? ': ' . implode( ', ', $changed ) : '' ) );

// Through the takeover, after the theme's own /members/ and /services/ rebuilds.
$page = prospergenics_2026_listings_takeover( $services, '/services/', $chrome );
ok( false === strpos( $page, 'dc-initial">' ) && substr_count( $page, '<span class="pg-pattern pg-pattern--' ) === count( $before ) && false !== strpos( $page, 'HEADER' ), 'in the takeover (page filter): /services/ as the plugin makes it has no letter tile left' );
$members = prospergenics_2026_listings_takeover( file_get_contents( $fixture . '/members.html' ), '/members/', $chrome );
ok( false === strpos( $members, 'dc-initial">' ), 'and /members/ as the plugin makes it neither' );

// The styles: in the CSS the takeover prints on every listings page (and every other page type).
$css = file_get_contents( $design . '/wp/inc/site-chrome/site-chrome.css' );
$src = file_get_contents( $design . '/assets/platform/courses.css' );
ok( false !== strpos( $css, '.pg-pattern { display: block; width: 100%; height: 100%; min-height: 120px; background-color: #0f3d28;' ) && 3 === preg_match_all( '#\.pg-pattern--[123] \{ --pg-texture: #', $css ), 'site-chrome.css has the pattern and its three textures' );
preg_match( "#url\(\"data:image/svg\+xml,[^\"]+\"\)#", $src, $sprout );
ok( $sprout && false !== strpos( $css, $sprout[0] ), 'the same sprout as the /courses/ cards (courses.css)' );
ok( false !== strpos( $css, '.dc-thumb > .pg-pattern' ) && false !== strpos( $css, '.dc-thumb > .dc-status { z-index: 1; }' ), 'it fills the card thumb, with the status pill on top' );
ok( false === strpos( $css, 'var(--green' ) || false === strpos( substr( $css, strpos( $css, '.pg-pattern {' ) ), 'var(--green' ), 'literal colours (the plugin pages define their own --green-* tokens)' );
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "require get_template_directory() . '/inc/listings-pages.php';" ), 'functions.php loads inc/listings-pages.php' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

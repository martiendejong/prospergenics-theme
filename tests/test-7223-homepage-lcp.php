<?php
/**
 * Standalone test for the homepage's mobile LCP (card #7223). The LCP element on phones is the hero
 * photo (.hero-top-photo, a CSS background, measured on test, production and a local copy); this checks
 * what makes it arrive sooner:
 *   - phones up to 480px get hero-top-mobile.webp, a centre crop with the same framing (the 100vw x 232px
 *     box shows only the middle of the 2000x682 photo), much smaller, sharp enough for 2.5x screens;
 *   - each hero image is preloaded with fetchpriority="high" only for the widths whose CSS uses it, so
 *     no browser fetches both; the media queries of the preloads and of the CSS match;
 *   - the chat launcher photo (contact-bg.webp, in the first screen on phones) does not compete with it:
 *     fetchpriority="low", still lazy;
 *   - the @font-face rules are inline in front-page.php (generated from assets/fonts/fonts.css by
 *     build-wp-theme.py) instead of a render-blocking fonts.css request; nothing else lost;
 *   - assets/.htaccess gives the theme files browser caching with headers only (nothing that could
 *     answer 500 or block a file), fonts a year, images/CSS/JS a day (2 hours at Cloudflare);
 *   - the smoke check fetches the hero images and a font after every deploy.
 *
 * Run with: php tests/test-7223-homepage-lcp.php
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

$front = file_get_contents( $theme . '/front-page.php' );
$src   = file_get_contents( $design . '/index.html' );
$tpl   = '<?php echo esc_url( get_template_directory_uri() ); ?>';

echo "The mobile hero image\n";
$big    = getimagesize( $design . '/assets/hero-top.webp' );
$mobile = getimagesize( $design . '/assets/hero-top-mobile.webp' );
ok( $mobile && 'image/webp' === $mobile['mime'], 'assets/hero-top-mobile.webp is a WebP' );
ok( $mobile && abs( $mobile[0] / $mobile[1] - 480 / 232 ) < 0.01, 'with the aspect of the 480x232 box (so cover shows the same middle part at every width up to 480px)' );
ok( $mobile && $mobile[1] >= 2.5 * 232, 'at least 2.5 image pixels per CSS pixel of the 232px box (' . ( $mobile ? $mobile[0] . 'x' . $mobile[1] : '?' ) . ')' );
$saved = 1 - filesize( $design . '/assets/hero-top-mobile.webp' ) / filesize( $design . '/assets/hero-top.webp' );
ok( $saved >= 0.4, sprintf( 'and %d%% smaller than hero-top.webp (%d KB instead of %d KB)', round( $saved * 100 ), filesize( $design . '/assets/hero-top-mobile.webp' ) / 1024, filesize( $design . '/assets/hero-top.webp' ) / 1024 ) );
ok( $big && 2000 === $big[0], 'wider screens keep the 2000px photo' );
ok( is_file( $theme . '/assets/hero-top-mobile.webp' ) && md5_file( $theme . '/assets/hero-top-mobile.webp' ) === md5_file( $design . '/assets/hero-top-mobile.webp' ), 'the build copies it into the theme' );

echo "Preloads and CSS\n";
preg_match_all( '~<link rel="preload" as="image" href="([^"]+)"([^>]*)/>~', $front, $pre, PREG_SET_ORDER );
$by = array();
foreach ( $pre as $p ) { $by[ basename( $p[1] ) ] = $p[2]; }
ok( 2 === count( $pre ) && isset( $by['hero-top-mobile.webp'], $by['hero-top.webp'] ), 'two image preloads: the mobile and the wide hero photo, nothing else' );
$m_mobile = preg_match( '~media="([^"]+)"~', isset( $by['hero-top-mobile.webp'] ) ? $by['hero-top-mobile.webp'] : '', $mm ) ? $mm[1] : '';
$m_wide   = preg_match( '~media="([^"]+)"~', isset( $by['hero-top.webp'] ) ? $by['hero-top.webp'] : '', $mw ) ? $mw[1] : '';
ok( '(max-width: 480px)' === $m_mobile && '(min-width: 480.02px)' === $m_wide, 'each only for its widths: (max-width: 480px) and (min-width: 480.02px), never both' );
ok( false !== strpos( $by['hero-top-mobile.webp'], 'fetchpriority="high"' ) && false !== strpos( $by['hero-top.webp'], 'fetchpriority="high"' ), 'both with fetchpriority="high"' );
ok( false !== strpos( $front, "@media " . $m_mobile . "{ .hero-top-photo { background-image: url('" . $tpl . "/assets/hero-top-mobile.webp'); } }" ), 'the CSS swaps the photo under the same media query as the preload' );
ok( 1 === preg_match_all( "~url\('[^']*hero-top\.webp'\)~", $front ) && false !== strpos( $front, "background: url('" . $tpl . "/assets/hero-top.webp') center 12% / cover no-repeat;" ), 'and keeps hero-top.webp as the default background' );
$at_preload = strpos( $front, 'hero-top-mobile.webp" fetchpriority' );
ok( false !== $at_preload && $at_preload < strpos( $front, '<style' ), 'the preloads come before any stylesheet or <style> in <head>' );
ok( false !== strpos( $src, '<link rel="preload" as="image" href="assets/hero-top-mobile.webp" fetchpriority="high" media="(max-width: 480px)" />' ), 'edited in the design source (index.html), front-page.php is generated' );

echo "Launcher photo\n";
ok( (bool) preg_match( '~<span class="l-photo"><img loading="lazy" decoding="async" fetchpriority="low" width="480" height="640" src="[^"]*/assets/contact-bg\.webp"~', $front ), 'contact-bg.webp: fetchpriority="low", lazy, size attributes kept' );

echo "Fonts inline\n";
$fonts_css = file_get_contents( $design . '/assets/fonts/fonts.css' );
preg_match_all( '~@font-face \{[^}]*font-family: \'([^\']+)\';[^}]*font-weight: (\d+);[^}]*url\(\'([^\']+)\'\)~', $fonts_css, $want, PREG_SET_ORDER );
$style = preg_match( '~<style id="pg-fonts">(.*?)</style>~s', $front, $sm ) ? $sm[1] : '';
preg_match_all( '~@font-face \{[^}]*font-family: \'([^\']+)\';[^}]*font-weight: (\d+);[^}]*url\(\'([^\']+)\'\)~', $style, $got, PREG_SET_ORDER );
ok( false === strpos( $front, 'fonts/fonts.css" />' ) && '' !== $style, 'front-page.php has no fonts.css <link>, the rules are inline' );
ok( count( $want ) >= 10 && count( $want ) === count( $got ), count( $got ) . ' @font-face rules inline, as many as fonts.css has' );
$same = count( $want ) === count( $got );
foreach ( $want as $i => $w ) {
	$same = $same && $got[ $i ][1] === $w[1] && $got[ $i ][2] === $w[2] && $got[ $i ][3] === $tpl . '/assets/fonts/' . $w[3];
}
ok( $same, 'same families, weights and files, the URLs pointing into the theme' );
ok( substr_count( $style, 'font-display: swap' ) === count( $got ), 'every rule keeps font-display: swap' );
ok( false !== strpos( $src, '<link rel="stylesheet" href="assets/fonts/fonts.css" />' ), 'the static design keeps its link (build-wp-theme.py inlines it)' );

echo "Browser caching (assets/.htaccess)\n";
$ht = file_get_contents( $design . '/assets/.htaccess' );
$rules = trim( preg_replace( '~^\s*#.*$~m', '', $ht ) );
ok( $ht === file_get_contents( $theme . '/assets/.htaccess' ), 'shipped in the theme by the build (the deploy uploads it with the theme)' );
ok( ! preg_match( '~php_value|php_flag|Options|Rewrite|Deny|Allow from|Require|Redirect|ErrorDocument|Expires~i', $rules ), 'headers only: nothing that could answer 500 or block a file on the host' );
$lines = array_values( array_filter( array_map( 'trim', explode( "\n", $rules ) ) ) );
$allowed = '~^(<IfModule (mod_headers|mod_mime)\.c>|</IfModule>|<FilesMatch "[^"]+">|</FilesMatch>|Header set Cache-Control "[^"]+"|AddType font/woff2 \.woff2)$~';
ok( count( $lines ) > 0 && count( $lines ) === count( preg_grep( $allowed, $lines ) ), 'only IfModule-guarded Header set Cache-Control and AddType lines' );
ok( false !== strpos( $ht, "<FilesMatch \"\\.(woff2|woff)$\">\n    Header set Cache-Control \"public, max-age=31536000, immutable\"" ), 'fonts: a year, immutable' );
ok( (bool) preg_match( '~<FilesMatch "\\\\\.\(webp\|avif\|jpe\?g\|png\|gif\|svg\|ico\|css\|js\|json\|mp4\|webm\)\$">\s*Header set Cache-Control "public, max-age=86400, s-maxage=7200, stale-while-revalidate=86400"~', $ht ), 'images, CSS, JS: a day in the browser, 2 hours at Cloudflare' );

echo "Smoke check\n";
$smoke = file_get_contents( $root . '/tests/smoke/smoke-check.sh' );
ok( false !== strpos( $smoke, 'expect_asset "$THEME_ASSETS/hero-top.webp" image/webp' ) && false !== strpos( $smoke, 'expect_asset "$THEME_ASSETS/hero-top-mobile.webp" image/webp' ) && false !== strpos( $smoke, 'expect_asset "$THEME_ASSETS/fonts/inter-latin-400-normal.woff2"' ), 'after every deploy the hero images and a font must load' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

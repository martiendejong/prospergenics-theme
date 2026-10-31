<?php
/**
 * Standalone test for the second UX/navigation audit fixes (cards 6538-6547, 6482, 6489, 6490):
 *   - UX-9:  one focus-ring token (--focus-ring, green-700, white on dark green), used by every page type,
 *   - UX-15: no low-contrast green labels on About; the accent gradient's lightest stop is >= 3:1 on paper,
 *   - UX-16: the homepage H1 reads "You create value" (skip links: tests/test-2026-navigation.php),
 *   - UX-10: showcase tabs are links that preview on focus, autoplay off under reduced motion and
 *            paused/stopped on hover, focus and interaction (no visible button, card #6554); the member stream
 *            pauses on focus-within and has a pause button,
 *   - UX-8:  no "Pending · coming soon" partners; a branded pattern instead of letter tiles; no
 *            empty media column on a course without a cover,
 *   - UX-17: one avatar helper (photo or initials) on course, instructor and article pages, no Gravatar,
 *   - UX-14: a small logo file, width/height on every homepage <img>,
 *   - UX-13: the chat orb announces that it opens a dialog,
 *   - NAV-7/14: homepage links point at pages that exist, with the trailing slash.
 *
 * Run with: php tests/test-2026-ux-a11y.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/wp-theme/prospergenics-2026/' );

$root   = dirname( __DIR__ );
$design = $root . '/design/new-hero-2026-10';
$theme  = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

/** WCAG contrast ratio of two #rrggbb colours. */
function contrast( $a, $b ) {
	$lum = function ( $hex ) {
		$c = array_map( function ( $i ) use ( $hex ) {
			$v = hexdec( substr( ltrim( $hex, '#' ), $i, 2 ) ) / 255;
			return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 );
		}, array( 0, 2, 4 ) );
		return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
	};
	$x = array( $lum( $a ), $lum( $b ) );
	sort( $x );
	return ( $x[1] + 0.05 ) / ( $x[0] + 0.05 );
}

$home  = file_get_contents( $design . '/index.html' );
$about = file_get_contents( $design . '/about/index.html' );
$front = file_get_contents( $theme . '/front-page.php' );
$css   = file_get_contents( $theme . '/inc/site-chrome/site-chrome.css' );

echo "UX-9 focus ring\n";
ok( false !== strpos( $css, ':root { --focus-ring: #1a7a46; }' ), 'one token, --focus-ring: #1a7a46, in the shared stylesheet' );
ok( false !== strpos( $css, '.site-footer, .post-hero { --focus-ring: #fff; }' ), 'white on the dark green footer and inner-page heroes' );
foreach ( array( '#f4faf6' => 'paper', '#ffffff' => 'white', '#eaf5ee' => 'paper-2' ) as $bg => $name ) {
	ok( contrast( '#1a7a46', $bg ) >= 3, sprintf( 'green-700 ring on %s: %.2f:1', $name, contrast( '#1a7a46', $bg ) ) );
}
foreach ( array( '#0f3d28', '#1a7a46', '#1d8e4f' ) as $bg ) {
	ok( contrast( '#ffffff', $bg ) >= 3, sprintf( 'white ring on hero green %s: %.2f:1', $bg, contrast( '#ffffff', $bg ) ) );
}
$sources = array(
	'index.html'        => $home,
	'about/index.html'  => $about,
	'header.php'        => file_get_contents( $design . '/wp/header.php' ),
	'people builder'    => file_get_contents( $design . '/people/build-profiles.py' ),
	'articles.css'      => file_get_contents( $design . '/assets/platform/articles.css' ),
	'courses.css'       => file_get_contents( $design . '/assets/platform/courses.css' ),
	'profiles.css'      => file_get_contents( $design . '/assets/platform/profiles.css' ),
);
foreach ( $sources as $label => $text ) {
	ok( ! preg_match( '#outline:\s*3px solid var\(--green-500\)#', $text ), "$label: no #34c759 (2.1:1) focus outline left" );
	ok( (bool) preg_match( '#outline:\s*3px solid var\(--focus-ring#', $text ), "$label: outlines use var(--focus-ring)" );
}

echo "UX-15 contrast on About and the accent headings\n";
foreach ( array( '.values-label', '.card .tag', '.question-card .q-label' ) as $sel ) {
	ok( (bool) preg_match( '#' . preg_quote( $sel, '#' ) . ' \{[^}]*color: var\(--green-700\)#', $about ), "About $sel uses green-700" );
}
ok( contrast( '#1a7a46', '#f4faf6' ) >= 4.5 && contrast( '#1a7a46', '#ffffff' ) >= 4.5, 'green-700 is AA for small text on paper and white' );
ok( contrast( '#1f9a55', '#f4faf6' ) >= 3, sprintf( 'lightest accent stop #1f9a55 on paper: %.2f:1', contrast( '#1f9a55', '#f4faf6' ) ) );
foreach ( array( 'index.html' => $home, 'about/index.html' => $about ) as $label => $text ) {
	ok( false !== strpos( $text, '--accent-end: #1f9a55;' ), "$label defines --accent-end" );
	ok( false === strpos( $text, 'var(--green-500) 55%, var(--green-300) 100%' ) && false === strpos( $text, 'var(--green-500) 70%, var(--green-300) 100%' ), "$label: no accent gradient ending in #34c759/#86e0a6" );
}

echo "Hero halo (Martien, 31 Oct)\n";
// The paper halo behind the headline must reach 0 before the top of its box, or the box's top
// edge shows as a line across the photo: centre / vertical radius > the 70% transparent stop.
$halo_ok = (bool) preg_match( '#\.copy::before \{[^}]*radial-gradient\(ellipse [\d.]+% ([\d.]+)% at [\d.]+% ([\d.]+)%,[^}]*rgba\(244,250,246,0\) ([\d.]+)%\)#s', $home, $hm );
ok( $halo_ok && ( (float) $hm[2] / (float) $hm[1] ) > ( (float) $hm[3] / 100 ), 'the headline halo is fully transparent at its top edge (no line across the hero photo)' );

// The photo's white top fade stays short, so it never covers the people's heads (Martien, 31 Oct).
$fade_ok = (bool) preg_match( '~\.hero-top-photo \{[^}]*?mask-image: linear-gradient\(to bottom, transparent 0%,[^#]*#000 ([\d.]+)%~s', $home, $fm );
ok( $fade_ok && (float) $fm[1] <= 10, 'the hero photo is fully visible from 10% down (top fade ' . ( $fade_ok ? $fm[1] : '?' ) . '%)' );

echo "UX-16 homepage H1\n";
ok( false !== strpos( $home, '<h1 class="reveal d1">You create <span class="accent">value</span></h1>' ), 'H1 text content is "You create value"' );

echo "UX-10 showcase and member stream\n";
ok( 5 === preg_match_all( '#<a class="sc-tab( active)?" href="/(courses|members|tools|services|partners)/" data-i="\d">#', $home ), 'the five showcase tabs are links' );
ok( false === strpos( $home, '<button class="sc-tab' ), 'no button tabs left' );
ok( false === strpos( $home, 'id="scToggle"' ) && false === strpos( $home, 'Pause slideshow' ), 'no visible slideshow pause button (Martien, card #6554); it pauses on hover, focus and interaction instead' );
ok( false !== strpos( $home, "stopped = scReduce.matches" ), 'autoplay starts stopped under prefers-reduced-motion' );
ok( false !== strpos( $home, "t.addEventListener('focus', ()=>{ show(i); stop(); });" ), 'focusing a tab previews its slide and stops autoplay' );
ok( false !== strpos( $home, "showcase.addEventListener('focusin'" ) && false !== strpos( $home, "showcase.addEventListener('mouseenter'" ), 'paused while hovered or focused' );
ok( false === strpos( $home, "window.location.href = SLIDES[i].href" ), 'tabs navigate as plain links' );
ok( false !== strpos( $home, '.flow:hover .flow-track, .flow:focus-within .flow-track { animation-play-state: paused; }' ), 'the member stream pauses on focus-within' );
ok( (bool) preg_match( '#<button class="flow-toggle" id="flowToggle" type="button">.*?Pause the moving portraits</span></button>#', $home ), 'the member stream has a pause button' );

echo "UX-8 placeholders and pending partners\n";
ok( false === stripos( $home, 'Pending' ) && false === stripos( $home, 'coming soon' ), 'no Pending / coming soon on the homepage' );
$courses_render  = file_get_contents( $design . '/wp/inc/courses/render.php' );
$articles_render = file_get_contents( $design . '/wp/inc/articles/render.php' );
ok( false === strpos( $courses_render, 'pgc-thumb-placeholder" aria-hidden="true">\' . esc_html( mb_strtoupper' ) && false !== strpos( $courses_render, 'pg-pattern pg-pattern--' ), 'course cards: branded pattern, no letter' );
ok( false === strpos( $articles_render, 'mb_substr( wp_strip_all_tags( get_the_title' ) && false !== strpos( $articles_render, 'pg-pattern pg-pattern--' ), 'article cards: branded pattern, no letter' );
foreach ( array( 'courses.css', 'articles.css' ) as $f ) {
	ok( (bool) preg_match( '#\.pg-pattern--1\{.*\.pg-pattern--2\{.*\.pg-pattern--3\{#s', $sources[ $f ] ), "$f styles the three pattern variants" );
}
$single_course = file_get_contents( $design . '/wp/single-pg_course.php' );
ok( (bool) preg_match( '#if \(\$pgc_has_cover\) : \?>\s*<div class="pgc-hero-media">#', $single_course ), 'course without a cover: no media column (title first on mobile)' );

echo "UX-17 avatars\n";
foreach ( array( 'courses-instructor.php', 'single-pg_article.php', 'inc/articles/render.php', 'inc/courses/render.php' ) as $f ) {
	$t = file_get_contents( $design . '/wp/' . $f );
	ok( false !== strpos( $t, 'prospergenics_2026_avatar_html(' ), "$f uses the shared avatar helper" );
}
ok( false !== strpos( file_get_contents( $design . '/wp/functions.php' ), "/inc/avatars.php'" ), 'functions.php loads inc/avatars.php' );

// helper behaviour, with WordPress stubbed
$GLOBALS['__photo'] = array();
$GLOBALS['__privacy'] = array();
$GLOBALS['__filters'] = array();
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__filters'][ $tag ] = $cb; }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function is_admin() { return false; }
function is_email( $s ) { return false !== strpos( (string) $s, '@' ); }
function get_current_user_id() { return 0; }
function get_userdata( $id ) { return 5 === (int) $id ? (object) array( 'ID' => 5, 'display_name' => 'Amina Wanjiru' ) : ( 6 === (int) $id ? (object) array( 'ID' => 6, 'display_name' => 'Lessy Mpoe' ) : false ); }
function get_user_by( $f, $v ) { return false; }
function prospergenics_profiles_photo_url( $uid, $size ) { return isset( $GLOBALS['__photo'][ $uid ] ) ? $GLOBALS['__photo'][ $uid ] : ''; }
function prospergenics_profiles_privacy( $uid ) { return array( 'profile' => isset( $GLOBALS['__privacy'][ $uid ] ) ? $GLOBALS['__privacy'][ $uid ] : 'public' ); }
require $theme . '/inc/profiles/render.php';
require $theme . '/inc/avatars.php';

ok( '<span class="pgc-avatar pg-avatar-ph" aria-hidden="true">AW</span>' === prospergenics_2026_avatar_html( 5, 96, 'pgc-avatar' ), 'no photo: the profile page initials ("AW")' );
$GLOBALS['__photo'][6] = 'https://example.org/lessy-150x150.jpg';
ok( false !== strpos( prospergenics_2026_avatar_html( 6, 48, 'pga-avatar' ), '<img class="pga-avatar" src="https://example.org/lessy-150x150.jpg" alt="" width="48" height="48"' ), 'with a public photo: the photo' );
$GLOBALS['__privacy'][6] = 'members';
ok( false !== strpos( prospergenics_2026_avatar_html( 6, 48, 'pga-avatar' ), '>LM</span>' ), 'photo of a non-public profile is not shown to visitors' );
$pre = $GLOBALS['__filters']['pre_get_avatar'];
$img = $pre( null, 5, array( 'size' => 26, 'alt' => '', 'class' => 'x' ) );
ok( 0 === strpos( $img, '<img alt="" src="data:image/svg+xml;base64,' ) && false === strpos( $img, 'gravatar' ), 'get_avatar() on the front end: an initials image, never a Gravatar URL' );
ok( false !== strpos( base64_decode( substr( $img, strlen( '<img alt="" src="data:image/svg+xml;base64,' ), strpos( $img, '"', 40 ) - strlen( '<img alt="" src="data:image/svg+xml;base64,' ) ) ), '>AW</text>' ), 'the initials image says AW' );
ok( 'kept' === $pre( 'kept', 5, array() ), 'an avatar another filter already built is left alone' );

echo "UX-14 logo and homepage images\n";
$logo = $design . '/assets/logo.webp';
ok( is_file( $logo ) && filesize( $logo ) < 10240, 'assets/logo.webp exists and is under 10 KB (' . ( is_file( $logo ) ? filesize( $logo ) : 0 ) . ' bytes)' );
ok( false === strpos( $css . file_get_contents( $theme . '/inc/site-chrome.php' ) . file_get_contents( $design . '/site_chrome.py' ), 'logo.png' ), 'the header and footer use logo.webp, not the 68 KB logo.png' );
preg_match_all( '#<img\b[^>]*>#', $home, $imgs );
$no_size = array_filter( $imgs[0], function ( $tag ) { return ! preg_match( '#\bwidth="\d+"#', $tag ) || ! preg_match( '#\bheight="\d+"#', $tag ); } );
ok( count( $imgs[0] ) > 10 && ! $no_size, count( $imgs[0] ) . ' homepage <img>, all with width and height' );

echo "UX-13 chat launcher\n";
ok( false !== strpos( $home, '<button class="launcher-orb" type="button" aria-haspopup="dialog" aria-label="Open ProsperGenics chat">' ), 'the orb has aria-haspopup="dialog"' );

echo "NAV-7 / NAV-14 homepage links\n";
ok( false === strpos( $home, 'href="/jengo"' ) && false !== strpos( $home, 'href="/tools/jengo/"' ), 'Jengo card links /tools/jengo/' );
ok( false === strpos( $home, 'href="/citizenship"' ) && false !== strpos( $home, 'href="/contact/?subject=Courses%20and%20certification"' ), 'Citizenship card opens the contact form on "Courses and certification"' );
preg_match_all( '~href(?:="|:")(/[^"?\#]*)"~', $home, $hrefs );
$bad = array_filter( $hrefs[1], function ( $h ) { return '/' !== substr( $h, -1 ) && ! preg_match( '#\.[a-z0-9]{2,4}$#', $h ); } );
ok( ! $bad, 'every root-relative link on the homepage ends in a slash' . ( $bad ? ': ' . implode( ', ', $bad ) : '' ) );
ok( false !== strpos( $front, '/tools/jengo/' ), 'front-page.php is rebuilt from it' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

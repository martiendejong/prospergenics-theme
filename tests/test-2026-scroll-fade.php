<?php
/**
 * Standalone test for the 2026 theme's scroll top-fade (audit UX-1, reported by Lesiamon) and two
 * small audit fixes on the same pages (UX-5, NAV-4). The value test is covered by
 * test-2026-value-test.php.
 *
 * Checks:
 *   - index.html and site_chrome.py carry the same scroll-fade block, and every generated page that
 *     includes it (front page, About, experts, community, people) carries that exact block,
 *   - the fade only masks: no greyscale or scaling of whole sections,
 *   - it stays off for reduced motion, near the top of the page, and on focused content and forms,
 *   - the courses empty state offers a next step that is not the value test, and its button text
 *     stays white.
 *
 * Run with: php tests/test-2026-scroll-fade.php
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

function fade_block( $text ) {
	return preg_match( '#// scroll-fade:start.*?// scroll-fade:end#s', $text, $m ) ? $m[0] : '';
}

echo "Scroll top-fade\n";
$source = fade_block( file_get_contents( $design . '/index.html' ) );
ok( '' !== $source, 'index.html has the scroll-fade block' );
ok( fade_block( file_get_contents( $design . '/site_chrome.py' ) ) === $source, 'site_chrome.py carries the same block as index.html' );

$pages = array(
	'front page'      => $theme . '/front-page.php',
	'About (theme)'   => $theme . '/page-about.php',
	'About (static)'  => $design . '/about/index.html',
	'experts'         => $design . '/experts/index.html',
	'community'       => $design . '/community/index.html',
	'people/lessy'    => $design . '/people/lessy/index.html',
);
foreach ( $pages as $label => $path ) {
	ok( is_file( $path ) && fade_block( file_get_contents( $path ) ) === $source, "$label carries the current block" );
	if ( is_file( $path ) ) {
		ok( false === stripos( file_get_contents( $path ), 'grayscale(' ), "$label has no greyscale scroll effect" );
	}
}

ok( false === strpos( $source, 'filter' ) && false === strpos( $source, '.scale' ), 'the fade only masks (no filter, no scale)' );
ok( false !== strpos( $source, 'prefers-reduced-motion' ) && false !== strpos( $source, "addEventListener('change'" ), 'off for reduced motion, and follows a change of that setting' );
ok( false !== strpos( $source, 'window.scrollY < 40' ), 'off while the page is at the top' );
ok( false !== strpos( $source, 't >= 0' ), 'only content whose top has scrolled past the viewport edge is masked' );
ok( (bool) preg_match( '/Math\.min\(window\.innerHeight \* 0\.12, 96\)/', $source ), 'the fade band is at most 12% of the viewport and 96px' );
ok( false !== strpos( $source, 'document.activeElement' ) && false !== strpos( $source, "'form, .launcher, .co-embed-root, [data-no-fade]'" ), 'never masks focused content, forms or the chat' );

echo "Courses empty state (UX-5, NAV-4)\n";
$archive = file_get_contents( $design . '/wp/archive-pg_course.php' );
ok( false !== strpos( $archive, "home_url('/contact/?subject=' . rawurlencode('Courses and certification'))" ), 'with no courses, it links to the contact form for courses' );
ok( false !== strpos( $archive, "home_url('/members/')" ), 'and to the members' );
ok( false === strpos( $archive, '#value-test' ), 'and not back to the value test' );
$css = file_get_contents( $design . '/assets/platform/courses.css' );
ok( false !== strpos( $css, '.pgc-empty a:not(.pgc-btn){' ) && false === strpos( $css, '.pgc-empty a{' ), 'empty-state link colour no longer turns button text green' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

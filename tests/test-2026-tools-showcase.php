<?php
/**
 * Standalone test for the homepage "Tools we use, built ourselves" section (Lesiamon, 8 Nov: the
 * expanding trapezium strip spilled off the screen and showed only one real tool).
 *
 * Checks:
 *   - the heading and intro text stay, and the section keeps id="projects",
 *   - the list holds the seven tools of /tools/ in order, each a real link to /tools/<slug>/ with its
 *     name and a one-liner (so the preview is never the only place they appear),
 *   - one preview panel per tool: only the first is shown before any script runs; each has the
 *     name, a description, the three skills and an "Explore <Tool>" link to the same page,
 *   - real screenshots only for Jengo and SEO God (with alt text and a size); the other five get the
 *     branded panel (icon + tagline), never an <img>,
 *   - no expanding strip and no scroll-zoom past 1 here, none at all on the services cards (that pushed the
 *     outer cards off the screen); the focus ring is the shared token; reduced motion is respected,
 *   - the switching script: hover, focus and a touch tap switch the preview, no autoplay,
 *   - front-page.php is rebuilt from it.
 *
 * Run with: php tests/test-2026-tools-showcase.php
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

$home  = file_get_contents( $design . '/index.html' );
$front = file_get_contents( $theme . '/front-page.php' );

function section( $html, $id ) {
	return preg_match( '#<section class="projects" id="' . $id . '">.*?</section>#s', $html, $m ) ? $m[0] : '';
}

$tools = array(
	'jengo'           => 'Jengo',
	'coachos'         => 'CoachOS',
	'jengowork'       => 'JengoWork',
	'jengo-meet'      => 'Jengo Meet',
	'jengomail'       => 'JengoMail',
	'jengo-knowledge' => 'Jengo Knowledge',
	'seo-god'         => 'SEO God',
);
$shots = array( 'jengo' => 'jengo-task-outcomes.webp', 'seo-god' => 'seomagic.webp' );

echo "Section\n";
$sec = section( $home, 'projects' );
ok( '' !== $sec, 'index.html has <section class="projects" id="projects">' );
ok( false !== strpos( $sec, '<h2>Tools we use, <span class="accent">built ourselves</span></h2>' ), 'the heading stays' );
ok( false !== strpos( $sec, '<p>The systems behind our daily work. We use them, we build them, we offer them.</p>' ), 'the intro text stays' );
ok( false === strpos( $sec, 'pcard' ) && false === strpos( $sec, 'pstrip' ), 'no expanding strip cards left in the tools section' );

echo "The list: seven real links with name and one-liner\n";
preg_match_all( '#<a class="tool-item( is-active)?" href="/tools/([a-z-]+)/" data-i="(\d)">.*?<span class="tool-name">([^<]+)</span><span class="tool-line">([^<]+)</span>#', $sec, $items, PREG_SET_ORDER );
ok( 7 === count( $items ), '7 tool links (' . count( $items ) . ')' );
ok( array_keys( $tools ) === array_column( $items, 2 ), 'in the order of /tools/: ' . implode( ', ', array_column( $items, 2 ) ) );
ok( array_values( $tools ) === array_column( $items, 4 ), 'each shows the tool\'s name' );
ok( count( array_filter( array_column( $items, 5 ), function ( $l ) { return strlen( trim( $l ) ) > 20 && strlen( $l ) <= 50; } ) ) === 7, 'each has a one-liner of at most 50 characters' );
ok( ' is-active' === $items[0][1] && 1 === substr_count( $sec, 'tool-item is-active' ), 'only the first is marked active in the markup' );
ok( ! preg_match( '#<a class="tool-item[^>]*(aria-label|tabindex|role)=#', $sec ), 'the links take their name from their visible text, in the normal tab order' );

echo "Preview panels\n";
preg_match_all( '#<div class="tool-panel( tp-branded)?" data-i="(\d)"( hidden)?>(.*?)\n        </div>\n#s', $sec, $panels, PREG_SET_ORDER );
ok( 7 === count( $panels ), '7 panels (' . count( $panels ) . ')' );
ok( '' === $panels[0][3] && 6 === count( array_filter( array_column( $panels, 3 ) ) ), 'only the first panel is shown before the script runs' );
$slugs = array_keys( $tools );
foreach ( $panels as $i => $p ) {
	$slug = $slugs[ $i ];
	$name = $tools[ $slug ];
	$body = $p[4];
	preg_match_all( '#<li>([^<]+)</li>#', $body, $chips );
	ok( false !== strpos( $body, '<h3>' . $name . '</h3>' ) && preg_match( '#</h3>\s*<p>[^<]{40,}</p>#', $body ) && 3 === count( $chips[1] )
		&& false !== strpos( $body, '<a class="tp-go" href="/tools/' . $slug . '/">Explore ' . $name . ' <span class="arrow" aria-hidden="true">&rarr;</span></a>' ),
		"$name: name, description, 3 skills (" . implode( ', ', $chips[1] ) . ') and "Explore ' . $name . '" to /tools/' . $slug . '/' );
	if ( isset( $shots[ $slug ] ) ) {
		ok( '' === $p[1] && preg_match( '#<img loading="lazy" decoding="async" src="assets/' . preg_quote( $shots[ $slug ], '#' ) . '" width="\d+" height="\d+" alt="[^"]{30,}" />#', $body ) && is_file( $design . '/assets/' . $shots[ $slug ] ),
			"$name: its real screenshot ({$shots[ $slug ]}) with a size and alt text" );
	} else {
		ok( ' tp-branded' === $p[1] && false === strpos( $body, '<img' ) && preg_match( '#<span class="tp-art-ico"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">.+?</svg></span><p class="tp-art-tag">[^<]{20,}</p>#', $body ),
			"$name: branded panel (icon + tagline), no picture" );
	}
}
ok( false === strpos( $sec, 'sandra.webp' ) && false === strpos( $sec, 'jengo-werkplek-duo.webp' ), 'no photo of a person or unrelated app screenshot standing in for a tool' );

echo "No overflow, focus, reduced motion\n";
preg_match_all( '#data-scroll-scale="([\d.]+)"#', $sec, $scales );
ok( 1 === count( $scales[1] ) && max( array_map( 'floatval', $scales[1] ) ) <= 1.0, 'tools grow in to at most their own width (data-scroll-scale ' . implode( ', ', $scales[1] ) . ')' );
ok( preg_match( '#<section class="projects svc-section" id="services">.*?</section>#s', $home, $svc ) && false === strpos( $svc[0], 'data-scroll-scale' ), 'the services section is not scaled at all (card #7219, tests/test-2026-services.php)' );
preg_match( '#/\* ─+  TOOLS · list \+ fixed preview panel  ─+ \*/(.*?)/\* ─+  PARTNERS#su', $home, $css );
$css = isset( $css[1] ) ? $css[1] : '';
ok( '' !== $css && false === strpos( $css, 'transition: flex' ) && false === strpos( $css, 'flex: 2.6' ), 'no width-changing flex animation' );
ok( false !== strpos( $css, '.tool-item:focus-visible { outline: 3px solid var(--focus-ring, #1a7a46); outline-offset: 3px; }' ), 'list items use the shared --focus-ring' );
ok( false !== strpos( $css, '.tp-go:focus-visible { outline: 3px solid var(--focus-ring); outline-offset: 3px; }' ) && false !== strpos( $css, '--focus-ring: #fff;' ), 'the Explore link on the dark panel gets the white ring' );
ok( (bool) preg_match( '#@media \(prefers-reduced-motion: reduce\)\{[^}]*\.tool-item[^}]*transition: none; \}\s*\.tool-panel\.is-entering \{ animation: none; \}#', $css ), 'reduced motion: no transitions, no panel fade' );
ok( (bool) preg_match( '#@media \(max-width: 980px\)\{\s*\.tools \{ display: block; \}\s*\.tools-stage \{ display: none; \}#', $css ), 'below 981px the preview is not shown, the list becomes cards' );
ok( (bool) preg_match( '#\.tool-arr \{ opacity: 0;#', $css ) && preg_match( '#@media \(min-width: 981px\)\{\s*\.tool-arr \{ opacity: 0;#', $css ), 'the arrow is only hidden where the preview exists (cards always show it)' );

echo "Switching script\n";
preg_match( '#// Tools showcase:.*?\}\)\(\);#s', $home, $js );
$js = isset( $js[0] ) ? $js[0] : '';
ok( '' !== $js, 'index.html has the tools showcase script' );
foreach ( array( "'mouseenter'", "'focus'", "'pointerdown'", "e.pointerType === 'touch'", 'e.preventDefault()', "matchMedia('(prefers-reduced-motion: reduce)')" ) as $needle ) {
	ok( false !== strpos( $js, $needle ), "script uses $needle" );
}
ok( false === strpos( $js, 'setInterval' ) && false === strpos( $js, 'setTimeout' ), 'no autoplay' );
ok( (bool) preg_match( '#if \(tapPreview && e\.detail\) \{ e\.preventDefault\(\); show\(i\); \}#', $js ), 'only a touch tap on another tool is held back (keyboard Enter has detail 0 and always opens)' );

echo "Generated front page\n";
$fsec = section( $front, 'projects' );
ok( '' !== $fsec && 7 === preg_match_all( '#<a class="tool-item[^"]*" href="/tools/[a-z-]+/"#', $fsec ) && 5 === substr_count( $fsec, 'tool-panel tp-branded' ), 'front-page.php carries the same section' );
ok( false !== strpos( $fsec, "/assets/jengo-task-outcomes.webp" ) && false === strpos( $fsec, 'src="assets/' ), 'with theme asset URLs' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

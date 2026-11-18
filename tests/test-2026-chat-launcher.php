<?php
/**
 * Standalone test for the homepage chat launcher and the CoachOS chat panel on phones:
 *   - #8078: below 561px the panel (.co-embed-panel, built by embed.js) is a card 12px from both edges,
 *            rounded, with a shadow, above the compact launcher; with the on-screen keyboard open it sits
 *            above the keyboard (visualViewport values --co-kb / --co-vv-top) and fits the visible area;
 *            the close button is a 40px tap target. Desktop keeps its rule.
 *   - #7759: a minimise badge on the orb tucks the whole launcher (orb, hint, WhatsApp) away into a
 *            "Chat" tab at the screen edge and the tab brings it back; both are buttons outside the orb
 *            (embed.js opens the chat on any click inside .launcher-orb), keyboard reachable, labelled,
 *            remembered in sessionStorage behind try/catch, and still under reduced motion.
 *
 * Run with: php tests/test-2026-chat-launcher.php
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

/** The body of the first rule for $selector inside "@media ($query){ ... }" that follows $after (or ''). */
function media_rule( $css, $query, $selector, $after = '' ) {
	$from = '' === $after ? 0 : (int) strpos( $css, $after );
	$at   = strpos( $css, '@media (' . $query . '){', $from );
	if ( false === $at ) {
		return '';
	}
	$sel = strpos( $css, $selector . ' {', $at );
	if ( false === $sel ) {
		return '';
	}
	$open = strpos( $css, '{', $sel );
	return substr( $css, $open + 1, strpos( $css, '}', $open ) - $open - 1 );
}

echo "Chat panel on phones (#8078)\n";
$compact = 'body.launcher-compact .co-embed-panel { bottom: 120px !important; height: min(560px, calc(100vh - 152px)) !important; }';
ok( false !== strpos( $home, $compact ), 'tablets keep the panel above the compact launcher' );
$phone = media_rule( $home, 'max-width: 560px', 'body.launcher-compact .co-embed-panel', $compact );
ok( '' !== $phone && strpos( $home, '@media (max-width: 560px){' . "\n    body.launcher-compact .co-embed-panel" ) > strpos( $home, $compact ), 'a phone rule follows it, with the same selector so it wins' );
ok( false !== strpos( $phone, 'left: 12px !important; right: 12px !important; width: auto !important;' ), 'phones: 12px from both screen edges (embed.js makes it 100vw)' );
ok( (bool) preg_match( '#border-radius: (\d+)px !important;#', $phone, $r ) && (int) $r[1] >= 12, 'phones: rounded corners' );
ok( false !== strpos( $phone, 'box-shadow:' ) && false !== strpos( $phone, '!important' ), 'phones: a soft shadow' );
ok( false !== strpos( $phone, 'bottom: max(120px, calc(var(--co-kb, 0px) + 12px)) !important;' ), 'phones: above the compact launcher (120px), or 12px above the on-screen keyboard' );
ok( false !== strpos( $phone, 'height: min(560px, calc(100dvh - var(--co-vv-top, 0px) - max(120px, calc(var(--co-kb, 0px) + 12px)) - 12px)) !important;' ), 'phones: the height fits the visible area (dvh, minus the keyboard and a 12px top gap)' );
ok( (bool) preg_match( '#body\.launcher-compact \.co-embed-close \{ min-width: (\d+)px; min-height: (\d+)px; \}#', $home, $c ) && (int) $c[1] >= 40 && (int) $c[2] >= 40, 'phones: the close button is a 40px tap target' );
ok( false !== strpos( $home, "vv.addEventListener('resize', fit);" ) && false !== strpos( $home, "vv.addEventListener('scroll', fit);" ), 'the keyboard values follow visualViewport resize and scroll' );
ok( false !== strpos( $home, "rootStyle.setProperty('--co-kb', (zoomed ? 0 : Math.max(0, Math.round(window.innerHeight - vv.height - vv.offsetTop))) + 'px');" ), '--co-kb is the space under the visual viewport, 0 while pinch-zoomed' );
ok( false !== strpos( $home, "rootStyle.setProperty('--co-vv-top'," ), '--co-vv-top is set too' );
ok( false !== strpos( $home, '@media (min-width: 561px){' . "\n    .co-embed-panel { bottom: 236px !important; height: min(560px, calc(100vh - 268px)) !important; }" ), 'desktop: the panel rule is unchanged' );

echo "Minimise the launcher (#7759)\n";
$launcher = preg_match( '#<div class="launcher">(.*?)\n  </div>#s', $home, $lm ) ? $lm[1] : '';
$orb      = preg_match( '#<button class="launcher-orb".*?</button>#s', $launcher, $om ) ? $om[0] : '';
ok( '' !== $orb && false === strpos( $orb, 'launcher-min' ) && false === strpos( $orb, 'launcher-tab' ), 'the minimise and restore buttons are not inside the orb (a click there would open the chat)' );
ok( false !== strpos( $launcher, '<button class="launcher-min" type="button" aria-expanded="true" aria-label="Minimise the chat button" title="Minimise the chat button">' ), 'minimise: a labelled button with aria-expanded="true"' );
ok( false !== strpos( $launcher, '<button class="launcher-tab" type="button" aria-expanded="false" aria-label="Show the chat button" hidden>' ), 'restore: a labelled "Chat" tab with aria-expanded="false", hidden at first' );
ok( false !== strpos( $launcher, '<span>Chat</span>' ), 'the tab shows the word "Chat", which is in its accessible name' );
ok( false !== strpos( $home, '.launcher.is-minimised .launcher-orb, .launcher.is-minimised .launcher-bubble,' . "\n" . '  .launcher.is-minimised .wa-btn, .launcher.is-minimised .launcher-min { display: none; }' ), 'minimised: orb, hint bubble, WhatsApp and the badge are gone (not focusable)' );
ok( false !== strpos( $home, '.launcher-tab[hidden] { display: none; }' ), 'the tab only shows while minimised' );
ok( (bool) preg_match( '#\.launcher-tab \{\s*position: fixed; right: 0;#', $home ), 'the tab sits at the right screen edge' );
ok( (bool) preg_match( '#\.launcher-min \{\s*position: absolute; z-index: 6;#', $home ) && ! preg_match( '#\.launcher-(min|tab)[^{]*\{[^}]*pointer-events: none#', $home ), 'the badge sits above the orb and WhatsApp (z-index 5) and is never pointer-events: none' );
ok( (bool) preg_match( '#\.launcher\.is-compact \.launcher-min \{ left: 0; top: 0; width: (\d+)px; height: \1px; \}#', $home, $cm ) && (int) $cm[1] >= 24, 'compact (96px orb): the badge is still at least 24px' );
ok( false !== strpos( $home, '.launcher-min, .launcher-tab { transition: none; } .launcher-tab { animation: none; }' ), 'reduced motion: no transitions or slide-in' );
ok( false !== strpos( $home, "const KEY = 'pg-launcher-minimised';" ) && 2 === substr_count( $home, 'sessionStorage.' . 'getItem(KEY)' ) + substr_count( $home, 'sessionStorage.setItem(KEY' ), 'the state is kept in sessionStorage' );
ok( (bool) preg_match( '#try \{ stored = sessionStorage\.getItem\(KEY\) === \'1\'; \} catch \(e\)#', $home ) && (bool) preg_match( '#try \{ if \(min\) sessionStorage\.setItem\(KEY, \'1\'\); else sessionStorage\.removeItem\(KEY\); \} catch \(e\)#', $home ), 'every storage read and write is wrapped in try/catch' );
ok( false !== strpos( $home, 'if (focus) (min ? tab : orb).focus();' ), 'focus moves to the tab, and back to the orb' );
ok( false !== strpos( $home, "launcher.classList.toggle('is-compact', compact);" ) && false !== strpos( $home, "if (cta && orb) cta.addEventListener('click', () => orb.click());" ), 'the compact/desktop switch and the "Not sure where to start?" opener are untouched' );
ok( 1 === substr_count( $launcher, '<a class="wa-btn" href="https://wa.me/254741619743"' ), 'the WhatsApp link is still there' );

echo "Built front page\n";
foreach ( array( '<button class="launcher-min"', '<button class="launcher-tab"', $compact, "const KEY = 'pg-launcher-minimised';", "rootStyle.setProperty('--co-kb'" ) as $needle ) {
	ok( false !== strpos( $front, $needle ), 'front-page.php carries ' . substr( $needle, 0, 40 ) );
}

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

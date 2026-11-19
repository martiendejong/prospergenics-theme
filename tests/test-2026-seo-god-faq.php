<?php
/**
 * Standalone test for the SEO God FAQ block in the theme's look (card #8077): inc/seo-god-faq.php and
 * inc/site-chrome/seo-god-faq.css, against the plugin's saved output from the prospergenics.com
 * homepage (tests/fixtures/seo-god/: faq-section.html, the block as the plugin prints it, and
 * faq-display.css, the plugin's stylesheet). Guards:
 *   - the theme's styles go out right after the plugin's faq-display.css (its handle, or its file in
 *     the plugin folder should the handle change), once per page, never in wp-admin, never after any
 *     other stylesheet;
 *   - CSS only: the module hooks style_loader_tag and nothing else, so the plugin's markup and its
 *     FAQPage structured data stay as they are;
 *   - every rule is scoped under "body .seo-god-faq-section" and outranks the plugin's rule for the
 *     same element by specificity: no !important, and every front-end rule of the plugin has its
 *     theme counterpart; every class in the plugin's markup is styled;
 *   - the look: the homepage section container (.projects in index.html: max-width and side padding),
 *     the two-line heading, the 3px #1a7a46 keyboard focus ring on the questions, no plugin indigo,
 *     plus / minus drawn, the column clear of the chat launcher, phones and reduced motion;
 *   - the design source and the generated theme are the same, and functions.php loads the module.
 *
 * Run with: php tests/test-2026-seo-god-faq.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/wp-theme/prospergenics-2026/' );

$root    = dirname( __DIR__ );
$theme   = $root . '/wp-theme/prospergenics-2026';
$design  = $root . '/design/new-hero-2026-10';
$fixture = __DIR__ . '/fixtures/seo-god';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ───────────────────── WP stubs ─────────────────────
$GLOBALS['__hooks'] = array();
$GLOBALS['__admin'] = false;
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function add_action( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function is_admin() { return $GLOBALS['__admin']; }
function style_tag( $tag, $handle, $href ) {
	foreach ( $GLOBALS['__hooks']['style_loader_tag'] as $cb ) { $tag = $cb( $tag, $handle, $href ); }
	return $tag;
}

require $theme . '/inc/seo-god-faq.php';

echo "\nSource and generated theme\n";
foreach ( array( 'inc/seo-god-faq.php', 'inc/site-chrome/seo-god-faq.css' ) as $rel ) {
	ok( is_file( $design . '/wp/' . $rel ) && file_get_contents( $design . '/wp/' . $rel ) === file_get_contents( $theme . '/' . $rel ), "$rel: the generated theme has the design source's copy" );
}
foreach ( array( $design . '/wp/functions.php', $theme . '/functions.php' ) as $fn ) {
	ok( false !== strpos( file_get_contents( $fn ), "require get_template_directory() . '/inc/seo-god-faq.php';" ), basename( dirname( $fn ) ) . '/functions.php loads inc/seo-god-faq.php' );
}
ok( array( 'style_loader_tag' ) === array_keys( $GLOBALS['__hooks'] ), 'CSS only: the module hooks style_loader_tag and nothing else (the plugin\'s markup and FAQPage data are untouched)' );

echo "\nWhich stylesheet\n";
$plugin_href = 'https://prospergenics.com/wp-content/plugins/seo-god/assets/css/faq-display.css?ver=1.1.1-apikey-fix';
ok( prospergenics_2026_seo_god_faq_is_stylesheet( 'seo-god-faq-display', $plugin_href ), 'the plugin\'s handle' );
ok( prospergenics_2026_seo_god_faq_is_stylesheet( 'seo-god-faq', $plugin_href ), 'a renamed handle, by the plugin\'s file' );
ok( prospergenics_2026_seo_god_faq_is_stylesheet( 'x', 'https://example.org/wp-content/plugins/seo-god/assets/css/faq-display.min.css' ), 'the minified file' );
ok( ! prospergenics_2026_seo_god_faq_is_stylesheet( 'seo-god-admin', 'https://prospergenics.com/wp-content/plugins/seo-god/assets/css/admin.css' ), 'not the plugin\'s other stylesheets' );
ok( ! prospergenics_2026_seo_god_faq_is_stylesheet( 'other', 'https://prospergenics.com/wp-content/plugins/other/faq-display.css' ), 'not another plugin\'s faq-display.css' );
ok( ! prospergenics_2026_seo_god_faq_is_stylesheet( 'other', 'https://evil.example/x.css?u=/plugins/seo-god/assets/css/faq-display.css' ), 'not a file name in a query string' );
ok( ! prospergenics_2026_seo_god_faq_is_stylesheet( 'jengo-listings', null ), 'no href' );

echo "\nPrinted after the plugin's stylesheet, once\n";
$link = "<link rel='stylesheet' id='seo-god-faq-display-css' href='$plugin_href' media='all' />\n";
$css  = prospergenics_2026_seo_god_faq_css();
ok( '' !== trim( $css ), 'the styles are read from inc/site-chrome/seo-god-faq.css' );
ok( $link === prospergenics_2026_seo_god_faq_style_tag( $link, '' ), 'no styles: the tag as it was' );
$once = prospergenics_2026_seo_god_faq_style_tag( $link, $css );
ok( $once !== $link && $once === prospergenics_2026_seo_god_faq_style_tag( $once, $css ), 'a tag that already carries them gets nothing more' );
$GLOBALS['__admin'] = true;
ok( $link === style_tag( $link, 'seo-god-faq-display', $plugin_href ), 'wp-admin: untouched' );
$GLOBALS['__admin'] = false;
$other = "<link rel='stylesheet' id='akismet-css' href='https://prospergenics.com/a.css' />\n";
ok( $other === style_tag( $other, 'akismet', 'https://prospergenics.com/a.css' ), 'another stylesheet: untouched' );
$out = style_tag( $link, 'seo-god-faq-display', $plugin_href );
ok( 0 === strpos( $out, $link ) && false !== strpos( $out, '<style id="prospergenics-seo-god-faq-css">' . "\n" . $css . "</style>\n" ), 'the plugin\'s <link>, then the theme\'s <style>, so the theme comes later in the cascade too' );
ok( $link === style_tag( $link, 'seo-god-faq-display', $plugin_href ), 'once per page' );

echo "\nThe stylesheet\n";
$src = file_get_contents( $design . '/wp/inc/site-chrome/seo-god-faq.css' );
ok( false === stripos( $src, '</style' ), 'nothing that could end the <style> element' );
ok( false === stripos( $src, '!important' ), 'no !important: specificity does it' );
ok( false === stripos( $src, '#6366f1' ) && false === stripos( $src, '#4f46e5' ), 'none of the plugin\'s indigo' );

/** Selectors of every style rule (comments out, @media / @supports opened). */
function css_selectors( $css ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	preg_match_all( '#([^{}@;]+)\{[^{}]*\}#', $css, $m );
	$out = array();
	foreach ( $m[1] as $prelude ) {
		foreach ( explode( ',', $prelude ) as $sel ) {
			$sel = trim( preg_replace( '/\s+/', ' ', $sel ) );
			if ( '' !== $sel ) { $out[] = $sel; }
		}
	}
	return $out;
}
/** [ids, classes + attributes + pseudo-classes, elements + pseudo-elements] of a simple selector. */
function css_specificity( $sel ) {
	$ids  = preg_match_all( '/#[\w-]+/', $sel );
	$pe   = preg_match_all( '/::[\w-]+/', $sel );
	$rest = preg_replace( '/::[\w-]+/', '', $sel );
	$cls  = preg_match_all( '/\.[\w-]+|\[[^\]]*\]|:[\w-]+/', $rest );
	$el   = preg_match_all( '/(?:^|[\s>+~])([a-z][\w-]*)/i', $rest );
	return array( $ids, $cls, $el + $pe );
}
$ours = css_selectors( $src );
ok( count( $ours ) > 20, count( $ours ) . ' selectors read' );
$unscoped = array_filter( $ours, function ( $s ) { return 0 !== strpos( $s, 'body .seo-god-faq-section' ); } );
ok( ! $unscoped, 'every rule is scoped under "body .seo-god-faq-section"' . ( $unscoped ? ': ' . implode( ' | ', $unscoped ) : '' ) );

// the plugin's front-end rules (its admin and editing controls are left to the plugin)
$admin  = '/add-faq|admin-controls|faq-toggle|faq-controls|drag-handle|edit-btn|delete-btn|edit-wrapper|-input|edit-buttons|save-btn|cancel-btn|faq-empty|faq-disabled|ui-sortable|placeholder/';
$plugin = array_values( array_unique( array_filter( css_selectors( file_get_contents( $fixture . '/faq-display.css' ) ), function ( $s ) use ( $admin ) { return ! preg_match( $admin, $s ); } ) ) );
ok( count( $plugin ) >= 12, count( $plugin ) . ' front-end rules in the plugin\'s faq-display.css' );
foreach ( $plugin as $p ) {
	$mine = '.seo-god-faq-section' === $p ? 'body .seo-god-faq-section' : 'body .seo-god-faq-section ' . $p;
	$beat = in_array( $mine, $ours, true ) && css_specificity( $mine ) > css_specificity( $p );
	ok( $beat, "\"$p\" is overridden by \"$mine\" (" . implode( ',', css_specificity( $mine ) ) . ' > ' . implode( ',', css_specificity( $p ) ) . ')' );
}

$markup = file_get_contents( $fixture . '/faq-section.html' );
preg_match_all( '/class="([^"]+)"/', $markup, $m );
$classes = array_unique( preg_split( '/\s+/', implode( ' ', $m[1] ) ) );
ok( 0 === strpos( trim( $markup ), '<div class="seo-god-faq-section"' ) && 5 === substr_count( $markup, '<details class="seo-god-faq-item"' ), 'fixture: the production block, five questions' );
foreach ( $classes as $c ) {
	ok( false !== strpos( $src, '.' . $c . ' ' ) || false !== strpos( $src, '.' . $c . ':' ) || false !== strpos( $src, '.' . $c . ',' ) || false !== strpos( $src, '.' . $c . '[' ) || false !== strpos( $src, '.' . $c . '::' ), "the plugin's .$c is styled" );
}

/** The declarations of the first rule whose selector list is exactly $sel. */
function css_rule( $css, $sel ) {
	$css = preg_replace( '#/\*.*?\*/#s', '', $css );
	return preg_match( '#(?:^|[}\s])' . preg_quote( $sel, '#' ) . '\s*\{([^}]*)\}#', $css, $m ) ? preg_replace( '/\s+/', ' ', trim( $m[1] ) ) : '';
}

echo "\nThe look\n";
$index = file_get_contents( $design . '/index.html' );
preg_match( '/\.projects \{ max-width: (\S+); margin: 0 auto; padding: (clamp\([^)]*\)) (clamp\([^)]*\)) /', $index, $pj );
$section = css_rule( $src, 'body .seo-god-faq-section' );
ok( $pj && false !== strpos( $section, 'max-width: ' . $pj[1] . ';' ) && false !== strpos( $section, 'margin: 0 auto;' ), 'the section container: max-width ' . ( $pj ? $pj[1] : '?' ) . ', centred, as the homepage sections (.projects)' );
ok( $pj && preg_match( '/padding: clamp\([^)]*\) ' . preg_quote( $pj[3], '/' ) . ' clamp\(/', $section ), 'the same side padding as the homepage sections: ' . ( $pj ? $pj[3] : '?' ) );
ok( false !== strpos( $section, 'width: 100%;' ) && false !== strpos( $section, 'border-top: 0;' ), 'full width of its container (also as a flex item on inner pages), no plugin rule above it' );
preg_match( '/\.voices-head h2, \.projects-head h2 \{([^}]*)\}/', $index, $vh );
$title = css_rule( $src, 'body .seo-god-faq-section .seo-god-faq-title' );
ok( $vh && preg_match( '/font-size: (clamp\([^)]*\))/', $vh[1], $fs ) && false !== strpos( $title, 'font-size: ' . $fs[1] . ';' ) && false !== strpos( $title, 'font-weight: 800;' ), 'the heading has the homepage section heading\'s size and weight' );
ok( false !== strpos( $title, 'max-width: 10.5em;' ) && false !== strpos( $src, 'linear-gradient(#0b2016, #0b2016) 0 0 / 100% 1lh no-repeat, linear-gradient(100deg, #1a7a46 0%, #1f9a55 100%)' ) && false !== strpos( $src, '@supports ((-webkit-background-clip: text) or (background-clip: text)) and (height: 1lh)' ), 'two lines: the first ink, the rest the accent green, only where the browser can (ink otherwise)' );
$focus = css_rule( $src, 'body .seo-god-faq-section .seo-god-faq-question:focus-visible' );
ok( 'outline: 3px solid var(--focus-ring, #1a7a46); outline-offset: 3px;' === $focus, 'keyboard focus on a question: the theme\'s 3px #1a7a46 ring' );
ok( false !== strpos( css_rule( $src, 'body .seo-god-faq-section .seo-god-faq-item' ), 'overflow: visible;' ), 'the item doesn\'t clip the focus ring (the plugin sets overflow: hidden)' );
$after = css_rule( $src, 'body .seo-god-faq-section .seo-god-faq-question::after' );
$open  = css_rule( $src, 'body .seo-god-faq-section .seo-god-faq-item[open] .seo-god-faq-question::after' );
ok( 0 === strpos( $after, 'content: "";' ) && 2 === substr_count( $after, 'linear-gradient(#1a7a46, #1a7a46)' ) && 0 === strpos( $open, 'content: "";' ) && 1 === substr_count( $open, 'linear-gradient(#fff, #fff)' ), 'plus closed, minus open, drawn (no "+" or "−" for screen readers to read out)' );
ok( 'display: none;' === css_rule( $src, 'body .seo-god-faq-section .seo-god-faq-item.editing .seo-god-faq-question::after' ), 'the plugin\'s editing mode still hides the icon' );
ok( false !== strpos( css_rule( $src, 'body .seo-god-faq-section .seo-god-faq-item[open]' ), 'border-color: #1a7a46;' ), 'an open item gets the theme green' );
ok( preg_match( '/@media \(min-width: 881px\) \{\s*body \.seo-god-faq-section \.seo-god-faq-list, body \.seo-god-faq-section \.seo-god-faq-disclaimer \{ max-width: min\(880px, calc\(100% - 290px\)\); \}/', $src )
	&& preg_match( '/@media \(min-width: 561px\) \{\s*body \.seo-god-faq-section \.seo-god-faq-list, body \.seo-god-faq-section \.seo-god-faq-disclaimer \{ max-width: min\(880px, calc\(100% - 140px\)\); \}/', $src ), 'the questions stay out of the chat launcher\'s lane (full-size from 881px, compact from 561px)' );
// the launcher's size in index.html, which those numbers are for
ok( false !== strpos( $index, '.launcher { position: fixed; right: 30px; bottom: 30px;' ) && false !== strpos( $index, 'position: relative; width: 242px; height: 186px;' ) && false !== strpos( $index, "window.matchMedia('(max-width: 880px)')" ), 'index.html: the launcher is still 242px wide at 30px from the edge, compact up to 880px' );
ok( false !== strpos( $section, 'padding: clamp(28px, 3.6vw, 52px) ' ) && preg_match( '/ clamp\(72px, 8vw, 110px\);/', $section ), 'space above the footer: the homepage sections\' 110px at the bottom (72px on phones)' );
ok( false !== strpos( $src, "@media (max-width: 640px) {\n  body .seo-god-faq-section .seo-god-faq-item { border-radius: 14px; }" ), 'phones: tighter items' );
ok( preg_match( '/@media \(prefers-reduced-motion: reduce\) \{[^}]*transition: none;/', $src ), 'reduced motion: no transitions' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

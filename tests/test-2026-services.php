<?php
/**
 * Standalone test for the service cards (card #7219, Lesiamon: "build it", and "make the Service
 * page match the section on the homepage").
 *
 * Checks:
 *   - homepage "Our services" (index.html, front-page.php): the heading, intro and id="services" stay;
 *     four white cards, no expanding strip (.pstrips/.pcard) and no data-scroll-scale; each card has
 *     its name, promise, three points and one link "See … →" to /services/<slug>/; the three real
 *     examples carry a visible "Example: …" label (the <img> has alt="" and loading="lazy"), CoachOS
 *     a branded panel and no picture; "Discuss your project →" goes to the contact form on
 *     "Services for my organisation" (front-page.php: through prospergenics_2026_contact_url());
 *   - one source: the homepage block is exactly what inc/services.php renders from
 *     inc/services/services.json for the homepage's URLs, and its styles are services.css;
 *   - the styles: 4 / 2 / 1 columns, the shared --focus-ring, reduced motion, contrast-safe colours,
 *     room for the chat launcher;
 *   - /services/ (jengo-listings fixture): the plugin's cards become the same four cards (h2 under
 *     the page's h1), with the button; header, breadcrumb, count and search box stay; a search result
 *     keeps its subset; an unknown service, a "no results" page or another shape is left alone;
 *   - /services/<slug>/: "What you get" with the three points and the labelled example after the
 *     description, before the FAQ and "← All services"; CoachOS gets no picture; once only; other
 *     shapes, unknown slugs and other sections untouched.
 *
 * Run with: php tests/test-2026-services.php
 */

$root   = dirname( __DIR__ );
$theme  = $root . '/wp-theme/prospergenics-2026';
$design = $root . '/design/new-hero-2026-10';

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
require $theme . '/inc/services.php';

$data     = json_decode( file_get_contents( $design . '/wp/inc/services/services.json' ), true );
$services = $data['services'];
$slugs    = array_column( $services, 'slug' );
$home     = file_get_contents( $design . '/index.html' );
$front    = file_get_contents( $theme . '/front-page.php' );
$css_src  = file_get_contents( $design . '/wp/inc/services/services.css' );

function services_section( $html ) {
	return preg_match( '#<section class="projects svc-section" id="services">.*?</section>#s', $html, $m ) ? $m[0] : '';
}
function marked( $html, $start, $end ) {
	$a = strpos( $html, $start );
	$b = strpos( $html, $end );
	return false !== $a && false !== $b ? substr( $html, $a + strlen( $start ), $b - $a - strlen( $start ) ) : '';
}

echo "services.json\n";
ok( array( 'coachos', 'research', 'provenance', 'online-presence' ) === $slugs, 'the four services, in the homepage order' );
foreach ( $services as $s ) {
	ok( '' !== $s['name'] && '' !== $s['promise'] && 3 === count( $s['points'] ) && 0 === strpos( $s['link'], 'See ' ), "{$s['name']}: name, promise, three points and a \"See …\" link text" );
}
ok( in_array( $data['cta']['subject'], prospergenics_2026_contact_subjects(), true ), 'the button subject is one the contact form offers ("' . $data['cta']['subject'] . '")' );

echo "Homepage section\n";
$sec = services_section( $home );
ok( '' !== $sec, 'index.html has <section class="projects svc-section" id="services">' );
ok( false !== strpos( $sec, '<h2>Our <span class="accent">services</span></h2>' ) && false !== strpos( $sec, '<p>What we do for organisations, powered by the systems above.</p>' ), 'the heading "Our services" and the intro stay' );
ok( false === strpos( $sec, 'data-scroll-scale' ), 'no data-scroll-scale: nothing scales or moves the section' );
ok( false === strpos( $sec, 'pstrip' ) && false === strpos( $sec, 'pcard' ) && false === strpos( $sec, 'pc-tag' ), 'no expanding strip classes (.pstrips/.pcard)' );
ok( 4 === substr_count( $sec, '<article class="svc-card">' ), 'four cards' );
preg_match_all( '#<article class="svc-card">(.*?)</article>#s', $sec, $cards );
foreach ( $services as $i => $s ) {
	$c = isset( $cards[1][ $i ] ) ? $cards[1][ $i ] : '';
	ok( false !== strpos( $c, '<h3 class="svc-name">' . esc_html( $s['name'] ) . '</h3>' ) && false !== strpos( $c, '<p class="svc-promise">' . esc_html( $s['promise'] ) . '</p>' ), "card " . ( $i + 1 ) . ": {$s['name']}, with its promise" );
	preg_match_all( '#<li>(.*?)</li>#', $c, $li );
	ok( array_map( 'esc_html', $s['points'] ) === $li[1], "{$s['name']}: the three \"what you get\" points" );
	ok( 1 === substr_count( $c, '<a ' ) && false !== strpos( $c, '<a class="svc-more" href="/services/' . $s['slug'] . '/">' . esc_html( $s['link'] ) . ' <span aria-hidden="true">→</span></a>' ), "{$s['name']}: one link, \"{$s['link']} →\" to /services/{$s['slug']}/" );
	if ( empty( $s['image'] ) ) {
		ok( false === strpos( $c, '<img' ) && (bool) preg_match( '#<div class="svc-media svc-media--brand" aria-hidden="true"><svg [^>]*>.+?</svg></div>#', $c ), "{$s['name']}: a branded panel with an icon, no picture" );
	} else {
		$im = $s['image'];
		ok( false !== strpos( $c, '<img src="assets/' . $im['src'] . '" width="' . $im['width'] . '" height="' . $im['height'] . '" alt="" loading="lazy" decoding="async" style="object-position:' . $im['position'] . '" />' ) && is_file( $design . '/assets/' . $im['src'] ), "{$s['name']}: its example image ({$im['src']}, lazy, alt=\"\" next to the label, object-position {$im['position']})" );
		ok( false !== strpos( $c, '<figcaption class="svc-ex">' . esc_html( $im['label'] ) . '</figcaption>' ), "{$s['name']}: labelled \"{$im['label']}\"" );
	}
}
foreach ( array( 'Example: Art Revisionist', 'Example: Bugatti Insights', 'Example: our SEO tool' ) as $label ) {
	ok( 1 === substr_count( $sec, '>' . $label . '</figcaption>' ), "the label \"$label\" is shown" );
}
ok( false === strpos( $sec, 'jengo-telefoon-chat' ) && false === strpos( $sec, 'seomagic.webp' ), 'no Jengo screenshot for CoachOS, no SEO screenshot with text cut mid-word (the cropped seomagic-hero.webp instead)' );
preg_match_all( '#href="/services/([a-z0-9-]+)/"#', $sec, $links );
ok( $slugs === $links[1], 'four links to /services/<slug>/, in order' );
ok( false !== strpos( $sec, '<div class="svc-cta"><a class="svc-cta-btn" href="/contact/?subject=Services%20for%20my%20organisation">Discuss your project <span aria-hidden="true">→</span></a><p class="svc-cta-note">Not sure which fits? Tell us the problem and we&#039;ll suggest one.</p></div>' ), '"Discuss your project →" to the contact form on "Services for my organisation", with the note beside it' );

echo "One source: services.json + services.css\n";
$block = marked( $home, "<!-- services:cards -->\n", "\n    <!-- /services:cards -->" );
$php   = prospergenics_2026_services_cards( $services, '', 'assets', '/contact/?subject=' . rawurlencode( $data['cta']['subject'] ) );
ok( '' !== $block && $php === $block, 'the homepage cards are byte-identical to what inc/services.php renders from services.json (site_chrome.py writes them)' );
ok( trim( marked( $home, '/* services:css */', '/* /services:css */' ) ) === trim( $css_src ), 'the homepage styles are services.css, the one copy' );
ok( false !== strpos( file_get_contents( $design . '/site_chrome.py' ), 'services_section.update(' ), 'site_chrome.py rewrites them on every run (CI checks the generated files are in sync)' );
ok( 1 === substr_count( $home, '.svc-card {' ), 'no second copy of the card styles in index.html' );

echo "Generated front page\n";
$fsec = services_section( $front );
ok( false !== strpos( $fsec, 'href="<?php echo esc_url( prospergenics_2026_contact_url( \'Services for my organisation\' ) ); ?>">Discuss your project' ), 'front-page.php builds the button URL with prospergenics_2026_contact_url()' );
ok( 3 === substr_count( $fsec, 'src="<?php echo esc_url( get_template_directory_uri() ); ?>/assets/' ) && 4 === substr_count( $fsec, '<article class="svc-card">' ), 'front-page.php: the four cards, images from the theme directory' );
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "require get_template_directory() . '/inc/services.php';" ), 'functions.php loads inc/services.php' );

echo "Styles\n";
ok( false !== strpos( $css_src, '.svc-grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr));' ) && false !== strpos( $css_src, '@media (max-width: 1100px) { .svc-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }' ) && false !== strpos( $css_src, '@media (max-width: 640px) { .svc-grid { grid-template-columns: minmax(0, 1fr); } }' ), 'four columns, two below 1100px, one below 640px' );
ok( false !== strpos( $css_src, 'clip-path: polygon(0 0, 100% 0, 100% 88%, 0 100%);' ), 'the image area has the slanted bottom cut' );
ok( false !== strpos( $css_src, '.svc-more::after { content: ""; position: absolute; inset: 0;' ), 'the whole card is clickable through its one link (one tab stop)' );
ok( false !== strpos( $css_src, '.svc-more:focus-visible { outline: 3px solid var(--focus-ring, #1a7a46);' ) && false !== strpos( $css_src, '.svc-cta-btn:focus-visible { outline: 3px solid var(--focus-ring, #1a7a46);' ), 'focus rings use the shared --focus-ring' );
ok( (bool) preg_match( '#@media \(prefers-reduced-motion: reduce\) \{\s*\.svc-card, \.svc-cta-btn \{ transition: none; \}\s*\.svc-card:hover \{ transform: none; \}#', $css_src ), 'reduced motion: no hover lift, no transitions' );
ok( false !== strpos( $css_src, 'background: rgba(11,32,22,.88); color: #fff;' ) && false !== strpos( $css_src, 'background: #1a7a46; color: #fff;' ) && false === strpos( $css_src, 'linear-gradient(90deg' ), 'the label (white on .88 ink) and the button (white on #1a7a46, 5.4:1) stay above 4.5:1' );
ok( false !== strpos( $home, '.svc-section { padding-bottom: 180px; }' ) && false !== strpos( $home, '.svc-section .svc-cta { padding-right: max(0px, calc(300px' ), 'homepage: room under the cards and beside the button for the chat launcher' );
ok( false === strpos( $home, '.pstrip' ) && false === strpos( $home, '.pcard' ) && false === strpos( $home, 'class="pcard"' ), 'the strip-card CSS is gone (no other homepage section used it)' );

echo "/services/ (jengo-listings)\n";
$chrome  = array( 'header' => '<header class="site-header">HEADER</header>', 'footer' => '<footer class="site-footer">FOOTER</footer>', 'css' => '.x{}', 'script' => '<script>/*menu*/</script>' );
$fixture = file_get_contents( __DIR__ . '/fixtures/jengo-listings/services.html' );
$page    = substr( $fixture, 0, strpos( $fixture, '<script type="speculationrules">' ) ); // the takeover buffer ends at wp_footer
$out     = prospergenics_2026_listings_takeover( $page, '/services/', $chrome );
$assets  = TEST_HOME . '/wp-content/themes/prospergenics-2026/assets';
ok( 4 === substr_count( $out, '<article class="svc-card">' ) && false === strpos( $out, 'class="dir-card"' ), "the plugin's four cards become the four service cards" );
ok( prospergenics_2026_services_cards( $services, TEST_HOME, $assets, TEST_HOME . '/contact/?subject=Services%20for%20my%20organisation', 'h2' ) === marked( $out, "<div class=\"dir-wrap\">\n", "\n  </div>" ), 'the same cards as the homepage (site URLs), with the button to the contact form' );
ok( 4 === substr_count( $out, '<h2 class="svc-name">' ) && false === strpos( $out, '<h3 class="svc-name">' ), 'card names are h2 under the page h1' );
foreach ( $services as $s ) {
	ok( false !== strpos( $out, '<a class="svc-more" href="' . TEST_HOME . '/services/' . $s['slug'] . '/">' ), "links /services/{$s['slug']}/" );
}
ok( 3 === substr_count( $out, 'src="' . $assets . '/' ) && 3 === substr_count( $out, '<figcaption class="svc-ex">Example: ' ), 'the three labelled examples, from the theme assets' );
ok( 1 === substr_count( $out, '<style id="prospergenics-services-css">' ) && (bool) preg_match( '#<style id="prospergenics-services-css">\s*' . preg_quote( substr( trim( $css_src ), 0, 60 ), '#' ) . '.*?</style>\s*</head>#s', $out ), 'services.css printed once, before </head>' );
ok( false !== strpos( $out, '<h1>Services <span class="accent">that create value</span></h1>' ) && false !== strpos( $out, '<p class="pg-count">4 services</p>' ) && false !== strpos( $out, '<form class="search-box" method="get" action="' . TEST_HOME . '/services/">' ), "the plugin's heading, count and search box stay" );
ok( (bool) preg_match( '#<nav class="pg-breadcrumb pg-crumbs pg-crumbs--light" aria-label="Breadcrumb">.*?<span aria-current="page">Services</span>\s*</nav>\s*<header class="pg-header">#s', $out ), 'the breadcrumb stays (PR #73)' );
ok( false !== strpos( $out, '<header class="site-header">HEADER</header>' ) && false !== strpos( $out, '<footer class="site-footer">FOOTER</footer>' ), 'the site header and footer as on every listings page' );
ok( prospergenics_2026_services_div_end( $out, strpos( $out, '<div class="dir-wrap">' ) ) < strpos( $out, '<footer class="site-footer">' ), 'the card area is closed before the footer (balanced markup)' );

// a search (?keyword=research): the plugin lists only that card; only that one is shown
$card_re = '#\s*<a class="dir-card" href="' . preg_quote( TEST_HOME, '#' ) . '/services/(coachos|provenance|online-presence)/">.*?</a>#s';
$one     = preg_replace( $card_re, '', $page );
$one_out = prospergenics_2026_listings_takeover( $one, '/services/', $chrome );
ok( 1 === substr_count( $one_out, '<article class="svc-card">' ) && false !== strpos( $one_out, '>Research &amp; investigation</h2>' ), 'a search result keeps its subset (one card)' );
$unknown = str_replace( '/services/provenance/', '/services/something-new/', $page );
ok( prospergenics_2026_services_list_rewrite( $unknown, prospergenics_2026_services_by_slug(), TEST_HOME, $assets, 'x', 'y' ) === $unknown, 'a service services.json does not know: the plugin page stays as it was' );
$none = preg_replace( '#<div class="dir-grid">.*?</div>\s*</div>#s', '<p class="no-results">No services found.</p></div>', $page, 1 );
ok( false !== strpos( $none, 'no-results' ) && prospergenics_2026_services_list_rewrite( $none, prospergenics_2026_services_by_slug(), TEST_HOME, $assets, 'x', 'y' ) === $none, '"no results": unchanged' );
$two = str_replace( '<div class="search-wrap">', '<div class="dir-wrap"></div><div class="search-wrap">', $page );
ok( prospergenics_2026_services_list_rewrite( $two, prospergenics_2026_services_by_slug(), TEST_HOME, $assets, 'x', 'y' ) === $two, 'two card areas (another shape): unchanged' );
ok( false === strpos( prospergenics_2026_listings_takeover( $page, '/tools/', $chrome ), 'svc-' ), 'the same markup on another path (/tools/) is not touched' );
$members = file_get_contents( __DIR__ . '/fixtures/jengo-listings/members.html' );
ok( false === strpos( prospergenics_2026_listings_takeover( substr( $members, 0, strpos( $members, '<script type="speculationrules">' ) ), '/members/', $chrome ), 'svc-' ), '/members/ is not touched' );

echo "/services/<slug>/\n";
$detail = file_get_contents( __DIR__ . '/fixtures/jengo-listings/service-detail.html' );
$dpage  = substr( $detail, 0, strpos( $detail, '<script type="speculationrules">' ) );
$coach  = prospergenics_2026_listings_takeover( $dpage, '/services/coachos/', $chrome );
$get    = preg_match( '#<section class="svc-get" aria-labelledby="svc-get-h">.*?</section>#s', $coach, $g ) ? $g[0] : '';
ok( '' !== $get && false !== strpos( $get, '<h2 id="svc-get-h">What you get</h2>' ), 'CoachOS: a "What you get" section' );
ok( false !== strpos( $get, '<ul class="svc-points"><li>A chat assistant trained on your knowledge</li><li>Courses with progress tracking</li><li>Avatar videos, under your own brand</li></ul>' ), 'with the same three points as its card' );
ok( false === strpos( $get, '<img' ) && false === strpos( $get, 'svc-media' ), 'CoachOS has no example picture, so none is shown' );
$p_desc = strpos( $coach, '<div class="dir-desc">' );
$p_get  = strpos( $coach, '<section class="svc-get"' );
ok( $p_desc < $p_get && $p_get < strpos( $coach, '<section class="dir-faq">' ) && $p_get < strpos( $coach, '<div class="dir-back">' ) && $p_get > strpos( $coach, '</p></div>', $p_desc ), 'after the description, before the FAQ and "← All services"' );
ok( false !== strpos( $coach, '<h2>Frequently asked questions</h2>' ) && 1 === substr_count( $coach, '<aside class="dir-facts">' ) && false !== strpos( $coach, '<span class="fl">See also</span>' ) && false !== strpos( $coach, '← All services' ), "the plugin's FAQ, \"At a glance\" box and back link stay" );
ok( 1 === substr_count( $coach, '<style id="prospergenics-services-css">' ), 'the card styles once, before </head>' );
$research = prospergenics_2026_listings_takeover( str_replace( '/services/coachos/', '/services/research/', $dpage ), '/services/research/', $chrome );
ok( false !== strpos( $research, '<figure class="svc-media"><img src="' . $assets . '/artrevisionist-home.webp" width="1351" height="760" alt="" loading="lazy" decoding="async" style="object-position:50% 20%" /><figcaption class="svc-ex">Example: Art Revisionist</figcaption></figure>' ), 'Research: the same labelled example image as its card' );
ok( false !== strpos( $research, '<li>Every claim traced to a source</li>' ), 'Research: its own three points' );
ok( 1 === substr_count( prospergenics_2026_services_detail_rewrite( $research, prospergenics_2026_services_by_slug()['research'], $assets, $css_src ), 'class="svc-get"' ), 'never added twice' );
$two_desc = str_replace( '<div class="dir-back">', '<div class="dir-desc"><p>x</p></div><div class="dir-back">', $dpage );
ok( false === strpos( prospergenics_2026_listings_takeover( $two_desc, '/services/coachos/', $chrome ), 'svc-get' ), 'two descriptions (another shape): unchanged' );
$no_back = str_replace( '<div class="dir-back">', '<div class="dir-return">', $dpage );
ok( false === strpos( prospergenics_2026_listings_takeover( $no_back, '/services/coachos/', $chrome ), 'svc-get' ), 'no "← All services" block: unchanged' );
ok( false === strpos( prospergenics_2026_listings_takeover( $dpage, '/services/unknown/', $chrome ), 'svc-' ), 'a service services.json does not know: unchanged' );
ok( false === strpos( prospergenics_2026_listings_takeover( str_replace( '/services/coachos/', '/tools/coachos/', $dpage ), '/tools/coachos/', $chrome ), 'svc-' ), '/tools/coachos/ (same markup) is not touched' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All passed\n" );
exit( $failures ? 1 : 0 );

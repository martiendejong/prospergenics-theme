<?php
/**
 * Standalone test for the 2026 site header, mobile menu and footer: one menu and one look on
 * every page type (docs/AUDIT-2026-10.md C5; navigation audit NAV-1/2/3/5/6/8/12/17, ARC-3/5).
 *
 * The menu is defined once, in inc/site-chrome/nav.json, and rendered by inc/site-chrome.php
 * (WordPress: inner pages, homepage, About, jengo-listings pages) and by site_chrome.py (the
 * static index.html, About, people/experts/community pages). Guards:
 *   - nav.json: Courses, Members, Tools, Services, Partners, About; Vacancies only in the footer,
 *   - header.php/footer.php, front-page.php and page-about.php render the PHP chrome and carry no
 *     menu of their own; the static pages carry exactly the same menu and markup as the PHP one,
 *   - one CSS/JS for all of them: full-width sticky bar (no 1500px box), 40px logo, one row up to
 *     1180px wide (then the menu button), nowrap, admin bar offset; the homepage header is visible
 *     at load and hides only while scrolling down,
 *   - the mobile menu button and panel are wired accessibly, on every page type,
 *   - Log in + Join for visitors; My profile, My courses, Log out for members,
 *   - the full footer (with Vacancies and Articles) everywhere,
 *   - jengo-listings pages (saved fixtures) get the site header, footer and menu script, their
 *     "→" buttons go to the contact form with a subject, and anything unexpected is left untouched,
 *   - the contact form preselects ?subject= only when it is one of its subjects,
 *   - admin bar for staff only; members are sent from wp-admin to their profile.
 *
 * Run with: php tests/test-2026-navigation.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/wp-theme/prospergenics-2026/' );

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
$GLOBALS['__user']  = 0;
$GLOBALS['__hooks'] = array();
$GLOBALS['__queued'] = array();
function home_url( $path = '' ) { return TEST_HOME . '/' . ltrim( (string) $path, '/' ); }
function get_template_directory_uri() { return TEST_HOME . '/wp-content/themes/prospergenics-2026'; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
function wp_unslash( $v ) { return $v; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function apply_filters( $tag, $value ) { return $value; }
function add_action( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) { return $_SERVER['REQUEST_URI']; }
	return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
}
function is_user_logged_in() { return $GLOBALS['__user'] > 0; }
function get_current_user_id() { return $GLOBALS['__user']; }
function wp_get_current_user() { return (object) array( 'ID' => $GLOBALS['__user'], 'display_name' => 'Amina Otieno' ); }
function current_user_can( $cap ) { return 2 === $GLOBALS['__user']; } // user 2 is an editor
function user_can( $uid, $cap ) { return 2 === (int) $uid; }
function is_admin_bar_showing() { return 2 === $GLOBALS['__user']; }
function wp_login_url( $redirect = '' ) { return TEST_HOME . '/wp-login.php?redirect_to=' . rawurlencode( $redirect ); }
function wp_logout_url( $redirect = '' ) { return TEST_HOME . '/wp-login.php?action=logout&redirect_to=' . rawurlencode( $redirect ) . '&_wpnonce=abc'; }
function wp_style_is( $h, $s ) { return in_array( "style:$h:$s", $GLOBALS['__queued'], true ); }
function wp_script_is( $h, $s ) { return in_array( "script:$h:$s", $GLOBALS['__queued'], true ); }
function prospergenics_profiles_url( $uid ) { return TEST_HOME . '/in/amina-otieno/'; }
function prospergenics_profiles_photo_url( $uid, $size ) { return ''; }
$_SERVER['REQUEST_URI'] = '/courses/';

require $theme . '/inc/site-chrome.php';
require $theme . '/inc/account.php';

/** Labels of the links inside the first element matching $open .. $close. */
function labels_in( $html, $open_re ) {
	if ( ! preg_match( '#' . $open_re . '(.*?)</nav>#s', $html, $m ) ) {
		return array();
	}
	// [^<>]: hrefs in PHP templates contain a closing PHP tag
	preg_match_all( '#>([^<>]+)</a>#', $m[1], $labels );
	return array_map( 'trim', $labels[1] );
}
function main_menu( $html ) { return labels_in( $html, '<nav class="nav-links" aria-label="Main">' ); }
/** Markup with URLs and whitespace stripped: what must be identical between renderers. */
function shape( $html ) {
	$html = preg_replace( '#\s(href|src)="[^"]*"#', '', $html );
	return preg_replace( '#>\s+<#', '><', trim( $html ) );
}
function block( $html, $re ) { return preg_match( $re, $html, $m ) ? $m[0] : ''; }

$nav      = json_decode( file_get_contents( $theme . '/inc/site-chrome/nav.json' ), true );
$expected = array( 'Courses', 'Members', 'Tools', 'Services', 'Partners', 'About' );

echo "one menu (nav.json)\n";
ok( is_array( $nav ), 'nav.json parses' );
ok( $expected === array_column( $nav['main'], 'label' ), 'main menu is ' . implode( ', ', $expected ) );
ok( $expected === array_column( prospergenics_2026_main_nav_items(), 'label' ), 'prospergenics_2026_main_nav_items() serves that list' );
$footer_labels = array();
foreach ( $nav['footer'] as $col ) { $footer_labels = array_merge( $footer_labels, array_column( $col['links'], 'label' ) ); }
ok( in_array( 'Vacancies', $footer_labels, true ) && in_array( 'Articles', $footer_labels, true ), 'footer adds Vacancies and Articles' );
ok( ! in_array( 'Vacancies', array_column( $nav['main'], 'label' ), true ) && ! in_array( 'Home', array_column( $nav['main'], 'label' ), true ), 'no Vacancies or Home in the main menu' );

echo "PHP header, visitor\n";
$visitor = prospergenics_2026_site_header();
ok( 0 === strpos( $visitor, '<a class="skip-link" href="#main">Skip to content</a>' . "\n" . '<header class="site-header">' ), 'a skip link to #main, then a <header class="site-header"> bar (UX-16)' );
ok( $expected === main_menu( $visitor ), 'main menu in the bar' );
ok( array_merge( $expected, array( 'Log in', 'Join', 'Take the test' ) ) === labels_in( $visitor, '<nav aria-label="Mobile">' ), 'mobile panel: the same menu, Log in, Join, Take the test' );
ok( (bool) preg_match( '#<button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">#', $visitor ), 'menu button is type=button with aria-expanded, aria-controls and aria-label' );
ok( false !== strpos( $visitor, 'id="mobile-menu" hidden>' ), 'the panel it controls starts hidden' );
ok( false !== strpos( $visitor, 'href="' . TEST_HOME . '/courses/" aria-current="page">Courses' ), 'the current section is marked (aria-current)' );
ok( 2 === substr_count( $visitor, 'aria-current="page"' ), 'exactly one link marked in the bar and one in the mobile panel' );
foreach ( array( '/courses/' => 'Courses', '/members/' => 'Members', '/tools/jengo/' => 'Tools', '/about/' => 'About', '/people/lessy/' => 'Members', '/in/amina-wanjiru/' => 'Members', '/experts/' => 'Members', '/services' => 'Services', '/' => '', '/articles/x/' => '' ) as $p => $label ) {
	ok( $label === prospergenics_2026_current_nav_label( $p ), "current section of $p is " . ( $label ? $label : 'none' ) . ' (NAV-15)' );
}
ok( false !== strpos( $visitor, 'class="nav-account-link" href="' . TEST_HOME . '/wp-login.php?redirect_to=' . rawurlencode( TEST_HOME . '/courses/' ) . '">Log in</a>' ), 'Log in returns to the page you were on' );
ok( false !== strpos( $visitor, 'nav-account-join" href="' . TEST_HOME . '/contact/?subject=Joining%20the%20community">Join</a>' ), 'Join opens the contact form on "Joining the community"' );
ok( false === strpos( $visitor, 'Log out' ) && false === strpos( $visitor, 'nav-account-toggle' ), 'no member links for visitors' );
ok( false === strpos( $visitor, 'site-header--adminbar' ), 'no admin-bar offset without an admin bar' );

echo "PHP header, member and editor\n";
$GLOBALS['__user'] = 7;
$member = prospergenics_2026_site_header();
ok( (bool) preg_match( '#<button class="nav-account-toggle" type="button" aria-expanded="false" aria-controls="account-menu"[^>]*>.*My account</span></button>#', $member ), 'account menu button for members' );
ok( false !== strpos( $member, '<div class="nav-account-menu" id="account-menu" hidden>' ), 'the account menu it controls starts hidden' );
ok( (bool) preg_match( '#id="account-menu" hidden>\s*<a [^>]*>My profile</a>\s*<a [^>]*>My courses</a>\s*<a [^>]*>Log out</a>#', $member ), 'account menu: My profile, My courses, Log out' );
foreach ( array( '/in/amina-otieno/">My profile', '/courses/my/">My courses', 'action=logout&amp;redirect_to=' . rawurlencode( TEST_HOME . '/' ) ) as $needle ) {
	ok( substr_count( $member, $needle ) === 2, "member link $needle in the account menu and the mobile panel" );
}
ok( false === strpos( $member, '>Log in<' ) && false === strpos( $member, '>Join<' ), 'no Log in / Join for members' );
$GLOBALS['__user'] = 2;
ok( false !== strpos( prospergenics_2026_site_header(), 'class="site-header site-header--adminbar"' ), 'with the admin bar showing, the header sits below it' );
$GLOBALS['__user'] = 0;

echo "every WordPress page type uses it\n";
$header_php = file_get_contents( $theme . '/header.php' );
$footer_php = file_get_contents( $theme . '/footer.php' );
ok( false !== strpos( $header_php, 'echo prospergenics_2026_site_header()' ) && false !== strpos( $header_php, 'prospergenics_2026_site_chrome_css()' ), 'header.php prints the shared header and its CSS' );
ok( false !== strpos( $footer_php, 'echo prospergenics_2026_site_footer()' ) && false !== strpos( $footer_php, 'prospergenics_2026_site_chrome_script()' ), 'footer.php prints the shared footer and menu script' );
ok( ! preg_match( '#>(Courses|Members|Partners)</a>|class="pg-nav"|class="pg-footer"#', $header_php . $footer_php ), 'header.php/footer.php define no menu or footer of their own' );
$front = file_get_contents( $theme . '/front-page.php' );
$about = file_get_contents( $theme . '/page-about.php' );
ok( false !== strpos( $front, "prospergenics_2026_site_header( array( 'autohide' => true ) )" ) && false !== strpos( $front, 'prospergenics_2026_site_footer()' ), 'homepage renders the shared header (hide on scroll) and footer' );
ok( false !== strpos( $about, "prospergenics_2026_site_header( array( 'current' => 'About' ) )" ) && false !== strpos( $about, 'prospergenics_2026_site_footer()' ), 'About renders the shared header and footer' );
ok( ! preg_match( '#<header class="site-header|<footer class="site-footer"#', $front . $about ), 'no static header/footer left in the homepage or About templates' );
$functions = file_get_contents( $theme . '/functions.php' );
ok( false !== strpos( $functions, "/inc/site-chrome.php'" ) && false !== strpos( $functions, "/inc/account.php'" ), 'functions.php loads inc/site-chrome.php and inc/account.php' );

echo "static pages carry the same menu, markup, CSS and script\n";
$css = file_get_contents( $theme . '/inc/site-chrome/site-chrome.css' );
$js  = file_get_contents( $theme . '/inc/site-chrome/site-chrome.js' );
$static = array(
	'index.html'          => file_get_contents( $design . '/index.html' ),
	'front-page.php'      => $front,
	'about/index.html'    => file_get_contents( $design . '/about/index.html' ),
	'page-about.php'      => $about,
	'people/martien'      => file_get_contents( $design . '/people/martien/index.html' ),
	'people/lessy'        => file_get_contents( $design . '/people/lessy/index.html' ),
	'experts/index.html'  => file_get_contents( $design . '/experts/index.html' ),
	'community/index.html'=> file_get_contents( $design . '/community/index.html' ),
);
$header_re = '#<a class="skip-link" href="\#main">Skip to content</a>\s*<header class="site-header[^"]*">.*?</header>#s';
$footer_re = '#<footer class="site-footer">.*?</footer>#s';
$_SERVER['REQUEST_URI'] = '/people/lessy/';
$php_header = prospergenics_2026_site_header();
$php_footer = prospergenics_2026_site_footer();
foreach ( $static as $label => $html ) {
	ok( false !== strpos( $html, $css ), "$label inlines site-chrome.css" );
	ok( false !== strpos( $html, $js ), "$label inlines site-chrome.js" );
	if ( '.php' === substr( $label, -4 ) ) { continue; }
	$h = block( $html, $header_re );
	ok( $expected === main_menu( $h ), "$label menu is " . implode( ', ', $expected ) );
	if ( 'index.html' === $label ) {
		ok( shape( $h ) === shape( prospergenics_2026_site_header( array( 'autohide' => true, 'current' => '' ) ) ), "$label header markup = the PHP header (visitor, on /)" );
	} elseif ( 'about/index.html' === $label ) {
		ok( shape( $h ) === shape( prospergenics_2026_site_header( array( 'current' => 'About' ) ) ), "$label header markup = the PHP header (visitor)" );
	} else {
		ok( shape( $h ) === shape( $php_header ), "$label header markup = the PHP header (visitor)" );
	}
	ok( shape( block( $html, $footer_re ) ) === shape( $php_footer ), "$label footer = the PHP footer" );
	ok( (bool) preg_match( '#<body[^>]*>\s*(<!-- site-chrome:header -->\s*)?<a class="skip-link" href="\#main">#', $html ), "$label: the skip link is the first thing in <body> (UX-16)" );
	ok( false !== strpos( $html, '<main id="main"' ), "$label: <main id=\"main\"> is the skip link target" );
}
foreach ( array( 'people/lessy', 'experts/index.html', 'community/index.html' ) as $label ) {
	ok( (bool) preg_match( '#<nav class="nav-links" aria-label="Main">(?:(?!</nav>).)*aria-current="page">Members</a>#s', $static[ $label ] ), "$label marks Members as the current section (NAV-15)" );
}
ok( (bool) preg_match( '#<nav class="nav-links" aria-label="Main">(?:(?!</nav>).)*aria-current="page">About</a>#s', $static['about/index.html'] ), 'about/index.html marks About as the current section' );
ok( false === strpos( block( $static['index.html'], $header_re ), 'aria-current' ), 'the homepage marks no section' );
ok( false !== strpos( $header_php, '<main id="main">' ), 'header.php opens <main id="main"> for every inner template' );
$_SERVER['REQUEST_URI'] = '/courses/';

echo "one look: full width, one row, sticky\n";
$rule = block( $css, '#\n  \.site-header \{[^}]*\}#' );
ok( false !== strpos( $rule, 'position: sticky' ) && false !== strpos( $rule, 'width: 100%' ) && false !== strpos( $rule, 'max-width: none' ), 'the bar is sticky and full width (no 1500px box)' );
ok( false !== strpos( $css, '.site-header .brand img { height: 40px;' ), 'logo 40px tall' );
ok( false !== strpos( $css, '.site-header .brand { display: flex; align-items: center; gap: 10px; flex-shrink: 0; margin: 0 auto 0 0;' ), 'logo left, everything else pushed right' );
foreach ( array( '.nav-links a', '.nav-account-link', '.nav-cta', '.brand-text' ) as $sel ) {
	ok( (bool) preg_match( '#\.site-header ' . preg_quote( $sel, '#' ) . ' \{[^}]*white-space: nowrap#', $css ), "$sel never wraps" );
}
ok( (bool) preg_match( '#@media \(max-width: 1180px\) \{[^@]*\.site-header \.nav-links, \.site-header \.nav-account \{ display: none; \}#', $css ), 'links and account links move into the menu below 1181px' );
ok( false !== strpos( $js, "matchMedia('(min-width: 1181px)')" ), 'the script uses the same breakpoint' );
ok( false === strpos( $css, 'font-weight: 600; box-shadow' ) && false !== strpos( $css, '.site-header .nav-links a[aria-current="page"] { opacity: 1; box-shadow:' ), 'the current link is not bolder (links do not shift between pages)' );
ok( false !== strpos( $css, '.site-header--adminbar { top: 32px; }' ) && false !== strpos( $css, '.site-header--adminbar { top: 46px; }' ), 'admin bar offset: 32px, 46px from 782px down' );

echo "homepage header (NAV-6)\n";
$home = $static['index.html'];
ok( false !== strpos( $home, '<header class="site-header site-header--autohide">' ), 'homepage header hides on scroll' );
ok( false === strpos( $home, 'translateY(-110%)' ) && false === strpos( $home, 'hdr-visible' ) && false === strpos( $front, 'hdr-visible' ), 'it is no longer hidden at load' );
ok( false !== strpos( $css, '.site-header--autohide.is-hidden { transform:' ) && false !== strpos( $js, "header.classList.add('is-hidden')" ) && false !== strpos( $js, 'y < lastY - 2' ), 'hidden only while scrolling down, back on any scroll up' );
ok( false !== strpos( $js, "getElementById('mobile-menu')" ) && false !== strpos( $js, "'Escape'" ), 'menu script toggles the panel and handles Escape' );

echo "footer (NAV-8)\n";
foreach ( $footer_labels as $l ) {
	ok( false !== strpos( $php_footer, '>' . esc_html( $l ) . '</a>' ), "footer links $l" );
}
foreach ( array( '/contact/', '/privacy-policy/', '/cookie-policy/', '/vacancies/', '/articles/' ) as $href ) {
	ok( false !== strpos( $php_footer, 'href="' . TEST_HOME . $href . '"' ), "footer links $href" );
}
ok( false !== strpos( $home, '<main id="main">' ) && false !== strpos( $home, '</main>' ), 'homepage content is wrapped in <main>' );

echo "jengo-listings pages (NAV-1/2/3/5)\n";
$chrome = array( 'header' => '', 'footer' => $php_footer, 'css' => $css, 'script' => prospergenics_2026_site_chrome_script() );
foreach ( array( 'service-detail' => '/services/coachos/', 'member-detail' => '/members/lessy/' ) as $fixture => $path ) {
	$page = file_get_contents( __DIR__ . "/fixtures/jengo-listings/$fixture.html" );
	// the buffer runs from the end of wp_head to the start of wp_footer (speculationrules is the first wp_footer output)
	$buffer = substr( $page, 0, strpos( $page, '<script type="speculationrules">' ) );
	$_SERVER['REQUEST_URI'] = $path;
	$chrome['header'] = prospergenics_2026_site_header();
	$out = prospergenics_2026_listings_takeover( $buffer, $path, $chrome );
	ok( false === strpos( $out, '<nav class="pg-nav">' ) && false !== strpos( $buffer, '<nav class="pg-nav">' ), "$fixture: the plugin's own nav is gone" );
	ok( 1 === substr_count( $out, '<header class="site-header">' ) && strpos( $out, '<header class="site-header">' ) < strpos( $out, 'class="pg-breadcrumb"' ), "$fixture: the site header sits where the plugin nav was" );
	ok( $expected === main_menu( $out ), "$fixture: main menu " . implode( ', ', $expected ) );
	ok( false !== strpos( $out, 'aria-controls="mobile-menu"' ) && false !== strpos( $out, "getElementById('mobile-menu')" ), "$fixture: mobile menu and its script" );
	ok( (bool) preg_match( '#<style id="prospergenics-site-chrome-css">.*</style>\s*</head>#s', $out ), "$fixture: header CSS printed before </head>, after the plugin's styles" );
	ok( (bool) preg_match( '#</main>\s*<footer class="site-footer">.*</footer>\s*<script>#s', $out ), "$fixture: site footer and menu script after the content" );
	ok( false === strpos( $out, 'class="facts-cta" href="' . TEST_HOME . '/"' ), "$fixture: no button left pointing at the homepage" );
	ok( 1 === substr_count( $out, '<main id="main" class="dir-main">' ), "$fixture: the plugin's <main> gets id=\"main\" for the skip link" );
	$cta[ $fixture ] = block( $out, '#<a class="facts-cta"[^>]*>[^<]*</a>#' );
	$outs[ $fixture ] = $out;
}
// CoachOS is a tool and a service: each page links the other, as one more fact (CON-12)
$see = '<div class="fact"><span class="fl">See also</span><a href="' . TEST_HOME . '/tools/coachos/">CoachOS, the platform behind it</a></div>';
ok( 1 === substr_count( $outs['service-detail'], $see ) && strpos( $outs['service-detail'], $see ) < strpos( $outs['service-detail'], '<a class="facts-cta"' ), '/services/coachos/ links /tools/coachos/ in its facts box, before the button' );
ok( false === strpos( $outs['member-detail'], '<span class="fl">See also</span>' ), 'pages without a counterpart get no "See also"' );
$tool_page = str_replace( '/services/coachos/', '/tools/coachos/', $buffer_service = substr( $sp = file_get_contents( __DIR__ . '/fixtures/jengo-listings/service-detail.html' ), 0, strpos( $sp, '<script type="speculationrules">' ) ) );
$tool_out  = prospergenics_2026_listings_takeover( $tool_page, '/tools/coachos/', $chrome );
ok( false !== strpos( $tool_out, '<span class="fl">See also</span><a href="' . TEST_HOME . '/services/coachos/">CoachOS as a service for your organisation</a>' ), '/tools/coachos/ links /services/coachos/' );
$two_boxes = str_replace( '</aside>', '</aside><aside class="dir-facts"></aside>', $buffer_service );
ok( false === strpos( prospergenics_2026_listings_takeover( $two_boxes, '/services/coachos/', $chrome ), 'See also' ), 'an unexpected page shape gets no "See also"' );
ok( false !== strpos( $cta['service-detail'], 'href="' . TEST_HOME . '/contact/?subject=Services%20for%20my%20organisation"' ) && false !== strpos( $cta['service-detail'], 'Discuss your project' ), '"Discuss your project →" asks about services' );
ok( false !== strpos( $cta['member-detail'], '/contact/?subject=Something%20else"' ), '"Get in touch →" on a member opens the form' );
ok( 'Tools' === prospergenics_2026_listings_cta_subject( 'Ask about this tool →', '/tools/jengo/' ), '"Ask about this tool →" -> Tools' );
ok( 'Partnership' === prospergenics_2026_listings_cta_subject( 'Become a partner &rarr;', '/partners/agentx/' ), '"Become a partner →" -> Partnership' );
ok( 'Services for my organisation' === prospergenics_2026_listings_cta_subject( 'Contact us', '/services/x/' ), 'unknown text falls back to the section' );
foreach ( array( 'Services for my organisation', 'Tools', 'Partnership', 'Something else', $nav['join_subject'] ) as $s ) {
	ok( in_array( $s, prospergenics_2026_contact_subjects(), true ), "\"$s\" is a contact form subject" );
}
$unrelated = "<html><head></head><body><nav class=\"other\">x</nav><main>y</main>";
ok( $unrelated === prospergenics_2026_listings_takeover( $unrelated, '/members/', $chrome ), 'a page without the plugin nav is left exactly as it was' );
ok( false !== strpos( file_get_contents( $theme . '/inc/site-chrome.php' ), 'ob_get_level() !== $prospergenics_2026_takeover_level' ), 'a buffer opened by someone else in between leaves the page untouched' );
$GLOBALS['__queued'] = array( 'style:jengo-listings:done' );
ok( prospergenics_2026_is_listings_page(), 'detects a jengo-listings page by its stylesheet' );
$GLOBALS['__queued'] = array();
ok( ! prospergenics_2026_is_listings_page(), 'and only then' );

echo "a blog post's back link (NAV-9)\n";
$GLOBALS['__opt'] = array( 'show_on_front' => 'page', 'page_for_posts' => 11 );
$GLOBALS['__pages'] = array( 11 => 'publish' );
$back = prospergenics_2026_posts_listing();
ok( TEST_HOME . '/blog/' === $back['url'] && 'Back to Blog' === $back['label'], 'goes to the Posts page set under Settings -> Reading' );
$GLOBALS['__opt'] = array( 'show_on_front' => 'posts', 'page_for_posts' => 11 );
$back = prospergenics_2026_posts_listing();
ok( TEST_HOME . '/articles/' === $back['url'] && 'All articles' === $back['label'], 'without a Posts page: the articles listing' );
$GLOBALS['__no_articles'] = true;
$back = prospergenics_2026_posts_listing();
ok( TEST_HOME . '/' === $back['url'], 'without either: the homepage' );
ok( false !== strpos( file_get_contents( $theme . '/single.php' ), 'prospergenics_2026_posts_listing()' ), 'single.php uses it' );

echo "empty listings never link to themselves (NAV-9, UX-7)\n";
$arch = file_get_contents( $theme . '/archive-pg_article.php' );
$empty = block( $arch, '#<\?php elseif \( \$pga_none \) : \?>.*?<\?php else : \?>#s' );
ok( '' !== $empty && false === strpos( $empty, "get_post_type_archive_link( 'pg_article' )" ), 'an empty /articles/ has no link back to /articles/' );
ok( false !== strpos( $empty, "home_url( '/#value-test' )" ) && false !== strpos( $empty, 'prospergenics_2026_contact_url(' ), 'it offers the value test and the contact form' );
ok( 2 === substr_count( $empty, 'class="pga-btn' ), 'both as styled buttons' );
ok( false !== strpos( $arch, 'if ( ! $pga_none ) {' ), 'no filters or sort pills on an empty /articles/' );
ok( false !== strpos( file_get_contents( $theme . '/assets/platform/articles.css' ), '.pga-empty a:not(.pga-btn){color:var(--green-700);}' ), 'links in empty notes use the theme colour' );

echo "contact form subject (?subject=)\n";
$contact = file_get_contents( $theme . '/page-contact.php' );
ok( false !== strpos( $contact, "sanitize_text_field(wp_unslash(\$_GET['subject']))" ) && false !== strpos( $contact, 'in_array($subject, prospergenics_2026_contact_subjects(), true)' ), 'subject is sanitised and must be on the list' );
ok( false !== strpos( $contact, 'foreach (prospergenics_2026_contact_subjects() as $pg_option)' ) && false !== strpos( $contact, 'selected($pg_option, $subject)' ), 'the matching option is preselected' );

echo "admin bar and wp-admin (NAV-17, ARC-5)\n";
$show = $GLOBALS['__hooks']['show_admin_bar'][0];
$GLOBALS['__user'] = 7;
ok( false === $show( true ), 'no admin bar for members' );
$GLOBALS['__user'] = 2;
ok( true === $show( true ), 'admin bar for editors' );
$GLOBALS['__user'] = 0;
ok( TEST_HOME . '/in/amina-otieno/' === prospergenics_2026_admin_redirect_target( 'index.php', false, false, 7 ), 'members opening wp-admin go to their profile' );
ok( '' === prospergenics_2026_admin_redirect_target( 'profile.php', false, true, 2 ), 'editors stay in wp-admin' );
foreach ( array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ) as $script ) {
	ok( '' === prospergenics_2026_admin_redirect_target( $script, false, false, 7 ), "$script keeps working for members" );
}
ok( '' === prospergenics_2026_admin_redirect_target( 'index.php', true, false, 7 ), 'AJAX requests are never redirected' );
ok( isset( $GLOBALS['__hooks']['admin_init'], $GLOBALS['__hooks']['admin_page_access_denied'] ), 'checked on admin_init and on screens WordPress refuses (edit.php, ...)' );
ok( isset( $GLOBALS['__hooks']['login_headerurl'], $GLOBALS['__hooks']['login_enqueue_scripts'] ) && TEST_HOME . '/' === $GLOBALS['__hooks']['login_headerurl'][0](), 'branded login page, logo links home' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

// stubs for prospergenics_2026_posts_listing() (NAV-9)
function get_option( $k, $d = false ) { return isset( $GLOBALS['__opt'][ $k ] ) ? $GLOBALS['__opt'][ $k ] : $d; }
function get_post_status( $id ) { return isset( $GLOBALS['__pages'][ $id ] ) ? $GLOBALS['__pages'][ $id ] : false; }
function get_permalink( $id ) { return TEST_HOME . '/blog/'; }
function get_the_title( $id ) { return 'Blog'; }
function post_type_exists( $t ) { return empty( $GLOBALS['__no_articles'] ); }
function get_post_type_archive_link( $t ) { return TEST_HOME . '/articles/'; }

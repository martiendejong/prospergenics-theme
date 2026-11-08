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
 *   - Log in + Join for visitors; My profile, My courses, Settings, Log out for members; Manage for staff,
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
function apply_filters( $tag, $value, ...$args ) { return isset( $GLOBALS['__filters'][ $tag ] ) ? $GLOBALS['__filters'][ $tag ]( $value, ...$args ) : $value; }
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
function prospergenics_courses_is_instructor() { return 2 === $GLOBALS['__user']; } // user 2 teaches
function prospergenics_manage_can_access() { return 2 === $GLOBALS['__user']; } // user 2 is staff (card #6457)
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
	// data-static marks the static pages' account links for site-chrome.js (#6486)
	$html = preg_replace( '#\s(href|src)="[^"]*"|\sdata-static#', '', $html );
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
ok( (bool) preg_match( '#id="account-menu" hidden>\s*<a [^>]*>My profile</a>\s*<a [^>]*>My courses</a>\s*<a [^>]*>My articles</a>\s*<a [^>]*>Settings</a>\s*<a [^>]*>Log out</a>#', $member ), 'account menu: My profile, My courses, My articles, Settings, Log out (NAV-16)' );
ok( false === strpos( $member, 'Instructor dashboard' ), 'no Instructor dashboard for a member who does not teach' );
ok( false === strpos( $member, '>Manage<' ) && false === strpos( $member, '/manage/' ), 'no Manage for members (card #6457)' );
foreach ( array( '/in/amina-otieno/">My profile', '/courses/my/">My courses', '/articles/mine/">My articles', '/profile/settings/">Settings', 'action=logout&amp;redirect_to=' . rawurlencode( TEST_HOME . '/' ) ) as $needle ) {
	ok( substr_count( $member, $needle ) === 2, "member link $needle in the account menu and the mobile panel" );
}
ok( false === strpos( $member, '>Log in<' ) && false === strpos( $member, '>Join<' ), 'no Log in / Join for members' );
$GLOBALS['__user'] = 2;
$teacher = prospergenics_2026_site_header();
ok( false !== strpos( $teacher, 'class="site-header site-header--adminbar"' ), 'with the admin bar showing, the header sits below it' );
ok( (bool) preg_match( '#id="account-menu" hidden>\s*<a [^>]*>My profile</a>\s*<a [^>]*>My courses</a>\s*<a [^>]*>My articles</a>\s*<a [^>]*>Instructor dashboard</a>\s*(<a [^>]*>Manage</a>\s*)?<a [^>]*>Settings</a>#', $teacher ) && 2 === substr_count( $teacher, '/instructor/dashboard/">Instructor dashboard' ), 'instructors also get Instructor dashboard, in the menu and the mobile panel (NAV-16)' );
ok( (bool) preg_match( '#id="account-menu" hidden>\s*<a [^>]*>My profile</a>\s*<a [^>]*>My courses</a>\s*<a [^>]*>My articles</a>\s*<a [^>]*>Instructor dashboard</a>\s*<a [^>]*>Manage</a>\s*<a [^>]*>Settings</a>\s*<a [^>]*>Log out</a>#', $teacher ) && 2 === substr_count( $teacher, '/manage/">Manage</a>' ), 'staff also get Manage (/manage/), before Settings, in the menu and the mobile panel (card #6457)' );
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
// ARC-19: the generated templates fire the same hooks as header.php, in the same order: wp_body_open
// right after <body>, then the header (skip link first), then the member notices, then <main>.
$hooks_re = '#<body(?: <\?php body_class\(\); \?>)?>\s*<\?php wp_body_open\(\); \?>\s*(<!-- site-chrome:header[^>]*-->\s*)?<\?php echo prospergenics_2026_site_header\([^;]*\); [^?]*\?>\s*<\?php do_action\( \'prospergenics_2026_after_site_header\' \);[^?]*\?>\s*<main id="main"#';
foreach ( array( 'front-page.php' => $front, 'page-about.php' => $about, 'header.php' => $header_php ) as $label => $tpl ) {
	ok( (bool) preg_match( $hooks_re, $tpl ), "$label: <body>, wp_body_open(), the header, the after-header hook, <main> (ARC-19)" );
	ok( 1 === substr_count( $tpl, 'wp_body_open()' ), "$label calls wp_body_open() once" );
}
$verify_php = file_get_contents( $theme . '/inc/profiles/verify.php' );
ok( false !== strpos( $verify_php, "add_action( 'prospergenics_2026_after_site_header', function () {" ) && false === strpos( $verify_php, "add_action( 'wp_body_open'" ), 'the verification banner hangs below the header (after the skip link), not on wp_body_open' );
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
	ok( false !== strpos( $h, '<div class="nav-account" data-static>' ), "$label marks its account links as static, for the member swap" );
}
foreach ( array( 'people/lessy', 'experts/index.html', 'community/index.html' ) as $label ) {
	ok( (bool) preg_match( '#<nav class="nav-links" aria-label="Main">(?:(?!</nav>).)*aria-current="page">Members</a>#s', $static[ $label ] ), "$label marks Members as the current section (NAV-15)" );
}
ok( (bool) preg_match( '#<nav class="nav-links" aria-label="Main">(?:(?!</nav>).)*aria-current="page">About</a>#s', $static['about/index.html'] ), 'about/index.html marks About as the current section' );
ok( false === strpos( block( $static['index.html'], $header_re ), 'aria-current' ), 'the homepage marks no section' );
ok( false !== strpos( $header_php, '<main id="main">' ), 'header.php opens <main id="main"> for every inner template' );
ok( false === strpos( $php_header, 'data-static' ), 'the PHP header knows the login, so it is not marked static' );

echo "member links on the static pages (#6486)\n";
ok( false !== strpos( $js, ".nav-account[data-static]" ) && false !== strpos( $js, 'pg_member=1' ), 'site-chrome.js swaps the static account links when the pg_member marker is set' );
foreach ( array( "['My profile', '/profile/']", "['My courses', '/courses/my/']", "['My articles', '/articles/mine/']", "['Settings', '/profile/settings/']", "['Log out', '/wp-login.php?action=logout']" ) as $needle ) {
	ok( false !== strpos( $js, $needle ), "static member link $needle" );
}
ok( false !== strpos( $js, "querySelector('.nav-panel-account')" ), 'the mobile panel gets the member links too' );
ok( strpos( $js, "querySelector('.nav-account[data-static]')" ) < strpos( $js, "menu(header.querySelector('.nav-account-toggle')" ), 'the swap runs before the account menu is wired up' );
$account_php = file_get_contents( $theme . '/inc/account.php' );
ok( false !== strpos( $account_php, "add_action( 'set_logged_in_cookie'" ) && false !== strpos( $account_php, "add_action( 'clear_auth_cookie'" ), 'the marker is set at login and cleared at logout' );
ok( false !== strpos( $account_php, "'httponly' => false" ) && false !== strpos( $account_php, "'samesite' => 'Lax'" ) && false !== strpos( $account_php, 'setcookie( \'pg_member\', $on ? \'1\' : \'\'' ), 'the marker holds only "1", no name or ID' );
ok( false !== strpos( $account_php, 'is_user_logged_in() !== isset( $_COOKIE[\'pg_member\'] )' ), 'the marker follows logins from before it existed and expired logins' );
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

echo "homepage header (Martien, 26 Oct: hidden until the visitor scrolls past the hero and back up)\n";
$home = $static['index.html'];
ok( false !== strpos( $home, '<header class="site-header site-header--autohide is-hidden">' ), 'homepage header starts hidden (the hero already shows the menu links)' );
ok( false !== strpos( $site_chrome_php = file_get_contents( $theme . '/inc/site-chrome.php' ), "\$classes .= ' site-header--autohide is-hidden';" ), 'the WordPress homepage header starts hidden too' );
ok( false !== strpos( $js, 'function pastHero(y)' ) && false !== strpos( $js, "} else if (!pastHero(y) || y > lastY + 2) {" ) && false !== strpos( $js, '} else if (y < lastY - 2) {' ), 'shown only past the hero while scrolling up; hidden scrolling down or back in the hero' );
ok( false !== strpos( $js, "header.querySelector('[aria-expanded=\"true\"]') || keyboardFocusInside()" ), 'an open menu or keyboard focus keeps it visible' );
ok( false !== strpos( $css, '.site-header--autohide.is-hidden:focus-within { transform: none; }' ), 'keyboard focus reveals it even before the script runs' );
ok( false !== strpos( $js, "getElementById('mobile-menu')" ) && false !== strpos( $js, "'Escape'" ), 'menu script toggles the panel and handles Escape' );

echo "footer (NAV-8)\n";
foreach ( $footer_labels as $l ) {
	ok( false !== strpos( $php_footer, '>' . esc_html( $l ) . '</a>' ), "footer links $l" );
}
foreach ( array( '/contact/', '/privacy-policy/', '/cookie-policy/', '/vacancies/', '/articles/', '/find/', '/badges/' ) as $href ) {
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
	ok( 1 === substr_count( $out, '<header class="site-header">' ) && strpos( $out, '<header class="site-header">' ) < strpos( $out, 'class="pg-breadcrumb' ), "$fixture: the site header sits where the plugin nav was" );
	ok( $expected === main_menu( $out ), "$fixture: main menu " . implode( ', ', $expected ) );
	ok( false !== strpos( $out, 'aria-controls="mobile-menu"' ) && false !== strpos( $out, "getElementById('mobile-menu')" ), "$fixture: mobile menu and its script" );
	ok( (bool) preg_match( '#<style id="prospergenics-site-chrome-css">.*</style>\s*</head>#s', $out ), "$fixture: header CSS printed before </head>, after the plugin's styles" );
	ok( (bool) preg_match( '#</main>\s*<footer class="site-footer">.*</footer>\s*<script>#s', $out ), "$fixture: site footer and menu script after the content" );
	ok( false === strpos( $out, 'class="facts-cta" href="' . TEST_HOME . '/"' ), "$fixture: no button left pointing at the homepage" );
	ok( 1 === substr_count( $out, '<main id="main" class="dir-main">' ), "$fixture: the plugin's <main> gets id=\"main\" for the skip link" );
	$cta[ $fixture ] = block( $out, '#<a class="facts-cta"[^>]*>[^<]*</a>#' );
	$outs[ $fixture ] = $out;
}
// The breadcrumb strip (Lessy, 8 Nov): a labelled nav, inside the green banner, aligned with the title
$svc = $outs['service-detail'];
ok( 1 === substr_count( $svc, '<nav class="pg-breadcrumb pg-crumbs pg-crumbs--on-hero" aria-label="Breadcrumb">' ) && false === strpos( $svc, '<div class="pg-breadcrumb">' ), 'service page: the breadcrumb is one labelled nav, no strip left' );
ok( (bool) preg_match( '#<header class="dir-hero pg-hero--crumbs">\s*<div class="dir-hero-inner">\s*<nav class="pg-breadcrumb pg-crumbs pg-crumbs--on-hero"#', $svc ), 'service page: it is the first thing inside the green banner' );
ok( false !== strpos( $svc, '<span aria-current="page">CoachOS</span>' ) && 2 === substr_count( $svc, '<span class="sep" aria-hidden="true">' ), 'the current page is marked, the separators are hidden from screen readers' );
ok( false === strpos( $svc, '<div class="dh-eyebrow"><span class="dot"></span>Services</div>' ) && false !== strpos( $buffer_svc = file_get_contents( __DIR__ . '/fixtures/jengo-listings/service-detail.html' ), '<div class="dh-eyebrow"><span class="dot"></span>Services</div>' ), 'the "SERVICES" eyebrow that only repeats the breadcrumb is dropped' );
ok( false !== strpos( $outs['member-detail'], 'pg-crumbs--on-hero' ) && false !== strpos( $outs['member-detail'], '<span aria-current="page">Lessy</span>' ), 'member page: same' );
$list_out = prospergenics_2026_listings_breadcrumb( $lp = file_get_contents( __DIR__ . '/fixtures/jengo-listings/members.html' ) );
ok( (bool) preg_match( '#<nav class="pg-breadcrumb pg-crumbs pg-crumbs--light" aria-label="Breadcrumb">.*?<span aria-current="page">Members</span>\s*</nav>\s*<header class="pg-header">#s', $list_out ), 'list page: stays above the light header, lined up with it' );
$learn = "<div class=\"pg-breadcrumb\">\n  <a href=\"/\">Home</a>\n  <span class=\"sep\">›</span>\n  <a href=\"/courses/\">Courses</a>\n  <span class=\"sep\">›</span>\n  <a href=\"/courses/ai/\">AI</a>\n  <span class=\"sep\">›</span>\n  <span>Course</span>\n</div>\n\n<div class=\"learn-hero\">\n  <div class=\"learn-hero-inner\">\n    <div class=\"lh-eyebrow\"><span class=\"dot\"></span>Course content</div>\n";
$learn_out = prospergenics_2026_listings_breadcrumb( $learn );
ok( false !== strpos( $learn_out, '<div class="learn-hero pg-hero--crumbs">' ) && false !== strpos( $learn_out, 'Course content</div>' ) && strpos( $learn_out, 'pg-crumbs--on-hero' ) < strpos( $learn_out, 'lh-eyebrow' ), 'lesson page: inside the banner; an eyebrow that says something else stays' );
ok( false !== strpos( $learn_out, "<span aria-current=\"page\">Lessons</span>\n</nav>" ) && false === strpos( $learn_out, '>Course</span>' ), 'lesson page: the last item reads "Lessons", not "Course" (#7236)' );
ok( '<a href="/x/">Course</a> <span aria-current="page">Lessons</span>' === prospergenics_2026_listings_lesson_crumb( '<a href="/x/">Course</a> <span aria-current="page">Course</span>' ) && '<span aria-current="page">Course design</span>' === prospergenics_2026_listings_lesson_crumb( '<span aria-current="page">Course design</span>' ), 'only the current item, and only when it is exactly "Course"' );
$course = str_replace( array( 'learn-hero', 'lh-eyebrow', 'Course content' ), array( 'course-hero', 'ch-eyebrow', 'ProsperGenics' ), $learn );
ok( false !== strpos( prospergenics_2026_listings_breadcrumb( $course ), '<span aria-current="page">Course</span>' ), 'a course page (not a lesson page) keeps its own last item' );
ok( false !== strpos( $c = prospergenics_2026_listings_breadcrumb( str_replace( "\n\n<div class=\"course-hero\">", "\r\n<!-- HERO BAND -->\r\n<div class=\"course-hero\">", $course ) ), '<!-- HERO BAND -->' . "\r\n" . '<div class="course-hero pg-hero--crumbs">' ) && false !== strpos( $c, 'pg-crumbs--on-hero' ), 'course page (a comment and CRLF before the banner, as live): inside the banner' );
ok( $learn . $learn === prospergenics_2026_listings_breadcrumb( $learn . $learn ), 'two breadcrumbs: the page is left as it was' );
ok( 'x' === prospergenics_2026_listings_breadcrumb( 'x' ), 'no breadcrumb: nothing changes' );
ok( false !== strpos( $css, '.pg-breadcrumb.pg-crumbs--on-hero a { color: rgba(255,255,255,.9); }' ) && false !== strpos( $css, '.pg-breadcrumb.pg-crumbs a:focus-visible { outline: 3px solid var(--focus-ring, #1a7a46);' ), 'light links on the banner, with a visible focus ring' );
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

// The /members/ directory links to the expertise search /find/ (card #6393), under its own search form.
$members_page = '<html><head></head><body><nav class="pg-nav"><a href="/">x</a></nav><main><div class="search-wrap"><form class="search-box" method="get" action="/members/"><input type="text" name="keyword" /><button type="submit">Search →</button></form></div><div class="dir-wrap"></div></main>';
ok( false === strpos( prospergenics_2026_listings_takeover( $members_page, '/members/', $chrome ), 'pg-find-link' ), 'no /find/ link while nothing asks for one' );
$GLOBALS['__filters']['prospergenics_2026_listings_search_links'] = function ( $links ) {
	$links['/members/'] = array( 'text' => 'Looking for someone who has done it before?', 'label' => 'Find people by skill, project or problem', 'path' => '/find/' );
	return $links;
};
$members_out = prospergenics_2026_listings_takeover( $members_page, '/members/', $chrome );
ok( (bool) preg_match( '#</form><p class="pg-find-link"[^>]*>Looking for someone who has done it before\? <a href="' . preg_quote( TEST_HOME, '#' ) . '/find/"[^>]*>Find people by skill, project or problem →</a></p></div>#', $members_out ), '/members/ links /find/ right after its search form' );
ok( false === strpos( prospergenics_2026_listings_takeover( $members_page, '/members/lessy/', $chrome ), 'pg-find-link' ), 'member detail pages get no /find/ link' );
ok( false === strpos( prospergenics_2026_listings_takeover( str_replace( '<div class="dir-wrap">', '<div class="search-wrap"></div><div class="dir-wrap">', $members_page ), '/members/', $chrome ), 'pg-find-link' ), 'an unexpected page shape gets no /find/ link' );
unset( $GLOBALS['__filters'] );

// Member badges on the /members/ directory (saved plugin page): next to the name on the cards the
// profiles track has a badge for; every other card, and every other page, stays as it was.
$dir_page = substr( $dp = file_get_contents( __DIR__ . '/fixtures/jengo-listings/members.html' ), 0, strpos( $dp, '<script type="speculationrules">' ) );
$grid     = function ( $html ) { return block( $html, '#<div class="dir-grid">.*?</a>\s*</div>#s' ); }; // the grid, up to the end of its last card
$dir_cards = prospergenics_2026_listings_member_slugs( $dir_page );
ok( 12 === count( $dir_cards ) && 'martien-de-jong' === $dir_cards[0] && in_array( 'lessy', $dir_cards, true ), 'the 12 member cards are read from their /members/<slug>/ links, in page order' );
$plain = prospergenics_2026_listings_takeover( $dir_page, '/members/', $chrome );
ok( $grid( $dir_page ) === $grid( $plain ) && '' !== $grid( $plain ) && false === strpos( $plain, 'pg-badge' ), 'no badges offered: the cards are exactly as the plugin made them' );
$seen  = null;
$badge = '<span class="pg-badge pg-badge--compact pg-badge--member" data-pg-badge="member"><span class="pg-badge-label">Member</span></span>';
$GLOBALS['__filters']['prospergenics_2026_listings_member_badges'] = function ( $badges, $slugs = array() ) use ( &$seen, $badge ) {
	$seen = $slugs;
	return array( 'lessy' => $badge, 'martien-de-jong' => $badge, 'frank' => '<a class="pg-badge" href="/badges/">Member</a>', 'not-on-the-page' => $badge, 'natumi' => '' );
};
$with = prospergenics_2026_listings_takeover( $dir_page, '/members/', $chrome );
ok( $seen === $dir_cards, 'the filter gets every card\'s slug in one call (one lookup for the page)' );
ok( false !== strpos( $with, '<div class="dc-title">Lessy ' . $badge . '</div>' ) && false !== strpos( $with, '<div class="dc-title">Martien de Jong ' . $badge . '</div>' ), 'the badge sits right after the name, inside the card title' );
ok( 2 === substr_count( $with, 'pg-badge--member' ) && false === strpos( $with, 'href="/badges/"' ), 'a badge with a link (it would nest inside the card link) and an empty one are refused' );
$strip = function ( $html ) use ( $badge ) { return str_replace( ' ' . $badge, '', $html ); };
ok( $grid( $dir_page ) === $grid( $strip( $with ) ), 'nothing else on the cards changes' );
ok( 12 === substr_count( $with, '<a class="dir-card" href=' ) && 12 === substr_count( $grid( $with ), '</a>' ), 'still twelve whole cards' );
$seen = null;
ok( false === strpos( prospergenics_2026_listings_takeover( $dir_page, '/services/', $chrome ), 'pg-badge' ) && null === $seen, 'other directories are never asked' );
$twice = str_replace( '<div class="dc-title">Lessy</div>', '<div class="dc-title">Lessy</div><div class="dc-title">x</div>', $dir_page );
ok( false === strpos( block( prospergenics_2026_listings_takeover( $twice, '/members/', $chrome ), '#<a class="dir-card" href="[^"]*/members/lessy/">.*?</a>#s' ), 'pg-badge' ), 'a card of an unexpected shape (two names) gets no badge' );
ok( $dir_page === prospergenics_2026_listings_add_member_badges( $dir_page, array() ) && $dir_page === prospergenics_2026_listings_add_member_badges( $dir_page, array( 'nobody' => $badge ) ), 'nothing that matches: the page is returned unchanged' );
unset( $GLOBALS['__filters'] );
ok( 'Members' === prospergenics_2026_current_nav_label( '/find/' ), '/find/ is in the Members section' );
ok( in_array( 'Find people', array_column( $nav['footer'][1]['links'], 'label' ), true ) && 6 === count( $nav['main'] ), 'the footer\'s Community column links Find people; the main menu keeps its six items (#6542)' );
$community = array_column( $nav['footer'][1]['links'], 'path', 'label' );
ok( isset( $community['Member badges'], $community['Find people'] ) && '/badges/' === $community['Member badges'] && '/find/' === $community['Find people'], 'the Community column links Member badges (/badges/) next to Find people (/find/)' );
ok( array( 'Courses', 'Members', 'Tools', 'Services', 'Partners', 'About' ) === array_column( $nav['main'], 'label' ), 'adding footer links leaves the six main menu items unchanged' );
ok( false !== strpos( $php_footer, 'href="' . TEST_HOME . '/badges/">Member badges</a>' ) && false !== strpos( $php_footer, 'href="' . TEST_HOME . '/find/">Find people</a>' ), 'the PHP footer links /badges/ and /find/' );
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

echo "Join on static pages follows the registration switch (#6338)\n";
$people_page = file_get_contents( $root . '/design/new-hero-2026-10/people/lessy/index.html' );
ok( false !== strpos( $people_page, 'href="/join/"' ) && false === strpos( $people_page, '/contact/?subject=Joining' ), 'static pages link Join to /join/, not straight to the contact form' );
$routes_src = file_get_contents( $theme . '/inc/profiles/routes.php' );
ok( false !== strpos( $routes_src, "add_rewrite_rule( '^join/?\$', 'index.php?pg_profile_page=join', 'top' );" ), '/join/ is a theme route' );
ok( (bool) preg_match( '#function prospergenics_profiles_join_target\( \$uid \) \{\s*if \( \$uid \) \{\s*return home_url\( \'/profile/\' \);\s*\}\s*if \( function_exists\( \'prospergenics_register_is_open\' \) && prospergenics_register_is_open\(\) \) \{\s*return home_url\( \'/register/\' \);\s*\}\s*return prospergenics_2026_contact_url\( prospergenics_2026_site_nav\(\)\[\'join_subject\'\] \);#', $routes_src ), '/join/ sends members to their profile, visitors to /register/ while it is open, else to the contact form' );
$account_src = file_get_contents( $theme . '/inc/profiles/account.php' );
ok( false !== strpos( $account_src, 'const PROSPERGENICS_PROFILES_PASSWORD_MIN    = 10;' ) && false !== strpos( $account_src, 'prospergenics_register_password_problem( $new,' ), 'changing a password in /profile/settings/ uses the registration rule (10 characters, no common passwords)' );

echo "course lesson pages (cards #7143, #7144)\n";
// The saved jengo-listings lesson page (AI Software Lifecycle), behind a plugin nav as the takeover sees it.
$learn      = file_get_contents( __DIR__ . '/fixtures/jengo-listings/course-learn.html' );
$learn_page = '<html><head></head><body><nav class="pg-nav"><a href="/">x</a></nav>' . $learn;
$lchrome    = $chrome + array( 'learn_css' => prospergenics_2026_learn_css(), 'learn_script' => prospergenics_2026_learn_script() );
ok( prospergenics_2026_listings_is_lesson_page( $learn ), 'the saved lesson page is recognised' );
ok( ! prospergenics_2026_listings_is_lesson_page( $buffer_service ) && ! prospergenics_2026_listings_is_lesson_page( $dir_page ), 'service and directory pages are not lesson pages' );
ok( ! prospergenics_2026_listings_is_lesson_page( $learn . '<div class="learn-grid">' ), 'a page with the lesson grid twice is left alone' );
$lout = prospergenics_2026_listings_takeover( $learn_page, '/courses/ai-software-lifecycle/learn/', $lchrome );
ok( 1 === substr_count( $lout, '<style id="prospergenics-learn-css">' ) && (bool) preg_match( '#<style id="prospergenics-learn-css">.*?</style>\s*<div class="learn-wrap">#s', $lout ), 'lesson styles printed right before the lesson, after the plugin\'s styles' );
ok( strpos( $lout, '.learn-grid{display:grid;grid-template-columns:270px 1fr' ) < strpos( $lout, 'prospergenics-learn-css' ), 'the plugin\'s grid rule comes first, so the fix wins' );
ok( 1 === substr_count( $lout, '<script id="prospergenics-learn-js">' ) && strpos( $lout, "getElementById('tocLinks')" ) < strpos( $lout, 'prospergenics-learn-js' ) && strpos( $lout, '<footer class="site-footer">' ) < strpos( $lout, 'prospergenics-learn-js' ), 'lesson script after the plugin\'s table of contents and the site footer' );
ok( false !== strpos( $lout, '<header class="site-header">' ) && false === strpos( $lout, '<nav class="pg-nav">' ), 'the lesson page still gets the site header' );
$lcss = prospergenics_2026_learn_css();
ok( false !== strpos( $lcss, '.learn-wrap .learn-grid { grid-template-columns: 270px minmax(0, 1fr); }' ) && false !== strpos( $lcss, 'grid-template-columns: minmax(0, 1fr); }' ), 'the lesson column can shrink below its widest screenshot (minmax(0,1fr)), on desktop and phone' );
ok( (bool) preg_match( '#\.learn-body img,[^{]*\{ max-width: 100%; height: auto; \}#', $lcss ) && false !== strpos( $lcss, 'overflow-x: auto' ), 'screenshots scale down; code blocks and tables scroll inside the column' );
foreach ( array( $buffer_service, $dir_page ) as $other ) {
	ok( false === strpos( prospergenics_2026_listings_takeover( $other, '/services/coachos/', $lchrome ), 'prospergenics-learn' ), 'other plugin pages get no lesson styles or script' );
}
ok( false === strpos( prospergenics_2026_listings_takeover( $learn_page, '/courses/x/learn/', $chrome ), 'prospergenics-learn' ), 'without lesson assets the lesson page is only re-chromed' );
$ljs = prospergenics_2026_learn_script();
ok( false !== strpos( $ljs, "body.hasAttribute('data-pg-sessions')" ) && false !== strpos( $ljs, "c.tagName === 'HR'" ), 'the script runs once and splits the lesson at the plugin\'s <hr> session breaks' );
ok( false !== strpos( $ljs, "'#session-' + (i + 2)" ) && false !== strpos( $ljs, "addEventListener('hashchange'" ), 'Previous / Next are links to #session-N, so back and forward work' );
ok( false !== strpos( $ljs, ".tool-section[data-tool]" ) && false !== strpos( $ljs, ".os-section[data-os]" ) && false !== strpos( $ljs, "localStorage" ), 'agent and computer pickers show only the matching steps and remember the choice' );
ok( false === strpos( file_get_contents( $theme . '/inc/site-chrome/learn.js' ), 'innerHTML' ), 'the lesson script builds its markup with textContent only (no HTML from the page is re-parsed)' );
// The Try-it playground (card #7146): a theme copy of the [5818] playground, in the same lesson script.
$pjs = file_get_contents( $theme . '/inc/site-chrome/playground.js' );
ok( strpos( $ljs, 'window.__pgPlayground = {' ) !== false && strpos( $ljs, 'window.__pgPlayground = {' ) < strpos( $ljs, "body.hasAttribute('data-pg-sessions')" ), 'the playground is defined before the lesson script that starts it' );
ok( false !== strpos( $pjs, "if (window.__pgPlayground) { return; }" ), 'it steps aside when the original playground runs again' );
ok( false !== strpos( $pjs, "frame.setAttribute('sandbox', 'allow-scripts');" ) && 1 === substr_count( $pjs, "setAttribute('sandbox'" ) && ! preg_match( '#allow-scripts[ \'"]+allow-same-origin|allow-same-origin[ \'"]+allow-scripts#', $pjs ), 'lesson code runs in a sandboxed frame without the page\'s origin (no cookies, storage or page access)' );
ok( false !== strpos( $pjs, "/npm/skulpt' + '@' + '1.2.0/dist/'" ) && false === strpos( $pjs, 'skulpt@' ), 'the Skulpt URL is built so email obfuscation cannot mangle it' );
ok( false === strpos( $ljs, '</script>' . "\n" . '(function' ) && 1 === substr_count( $ljs, '</script>' ), 'no closing script tag inside the inlined scripts' );
ok( false !== strpos( $ljs, '/* Lesson playground' ) && false !== strpos( $ljs, 'else\\s*\\{\\s*scan\\(document\\);' ), 'the stranded playground code is removed from its header comment to its last line' );
ok( false !== strpos( prospergenics_2026_learn_css(), '.editor-container textarea {' ) && false !== strpos( prospergenics_2026_learn_css(), '.learn-body .analogy, .learn-body .info-box {' ), 'the editors and lesson callouts are styled again' );
ok( preg_match_all( '#data-tool="([a-z]+)"#', $learn, $tm ) && 5 === count( array_unique( $tm[1] ) ) && 8 === substr_count( $learn, '<hr>' ), 'the saved course has 5 coding agents and 9 sessions (8 breaks)' );
// An example with a <textarea> of its own (HTML lesson 36): the takeover puts it back in its editor (#7146).
require $theme . '/inc/lesson-editors.php';
$l36_page = '<html><head></head><body><nav class="pg-nav"><a href="/">x</a></nav>' . file_get_contents( __DIR__ . '/fixtures/jengo-listings/html-learn-lesson36.html' );
$l36_out  = prospergenics_2026_listings_takeover( $l36_page, '/courses/html/learn/', $lchrome );
ok( 1 === substr_count( $l36_page, '<button class="btn btn-primary" type="submit">Submit</button>' ) && false === strpos( $l36_out, '<button class="btn btn-primary" type="submit">Submit</button>' ) && false !== strpos( $l36_out, '&lt;button class="btn btn-primary" type="submit"&gt;Submit&lt;/button&gt;' ), 'lesson pages: the example\'s spilled buttons are back in its editor, as text' );
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "require get_template_directory() . '/inc/lesson-editors.php';" ), 'the theme loads inc/lesson-editors.php' );
ok( false === strpos( prospergenics_2026_listings_takeover( $buffer_service, '/services/coachos/', $lchrome ), '&lt;/textarea&gt;' ), 'other pages are not mended' );
ok( false !== strpos( $lcss, '@media (max-width: 760px) { .learn-body .editor-container, .editor-container { grid-template-columns: minmax(0, 1fr); } }' ), 'on phones the code sits above its result, full width (the phone rule is as specific as the two-column one)' );
// The lesson pager (#7236): "Next: lesson N" is 12px on #1a7a46, so it needs 4.5:1.
ok( false !== strpos( $lcss, '.learn-wrap .pg-session-pager a.pg-next small { color: #e6f3ec; }' ) && false === strpos( $lcss, 'a.pg-next small { color: rgba(255,255,255,.8); }' ), 'the pager\'s "Next" label is #e6f3ec (4.7:1 on #1a7a46), no longer 80% white (4.03:1)' );

echo "skip link target on every jengo-listings page type (card #7227)\n";
// Only the plugin's detail pages have a <main>; its list, course and lesson pages have none, so
// the header's "Skip to content" (#main) pointed at nothing there.
$skip_header = prospergenics_2026_site_header();
$skip_chrome = array( 'header' => $skip_header ) + $chrome;
$skip_nav    = '<html><head></head><body><nav class="pg-nav"><a href="/">x</a></nav>';
$skip_pages  = array(
	'member-detail (detail page)' => array( substr( $md = file_get_contents( __DIR__ . '/fixtures/jengo-listings/member-detail.html' ), 0, strpos( $md, '<script type="speculationrules">' ) ), '/members/lessy/', '<main id="main" class="dir-main">' ),
	'service-detail (detail page)' => array( $buffer_service, '/services/coachos/', '<main id="main" class="dir-main">' ),
	'tool detail (detail page)'    => array( $tool_page, '/tools/coachos/', '<main id="main" class="dir-main">' ),
	'members (list page)'          => array( $dir_page, '/members/', '<header id="main" tabindex="-1" class="pg-header">' ),
	'tools (list page)'            => array( str_replace( '/members/', '/tools/', $dir_page ), '/tools/', '<header id="main" tabindex="-1" class="pg-header">' ),
	'course-detail (course page)'  => array( $skip_nav . file_get_contents( __DIR__ . '/fixtures/jengo-listings/course-detail.html' ), '/courses/html/', '<div id="main" tabindex="-1" class="course-hero pg-hero--crumbs">' ),
	'course-learn (lesson page)'   => array( $learn_page, '/courses/ai-software-lifecycle/learn/', '<div id="main" tabindex="-1" class="learn-hero">' ),
);
foreach ( $skip_pages as $label => $p ) {
	$_SERVER['REQUEST_URI'] = $p[1];
	$skip_chrome['header']  = prospergenics_2026_site_header();
	$sout = prospergenics_2026_listings_takeover( $p[0], $p[1], $skip_chrome + array( 'learn_css' => prospergenics_2026_learn_css(), 'learn_script' => prospergenics_2026_learn_script() ) );
	ok( false !== strpos( $sout, '<a class="skip-link" href="#main">Skip to content</a>' ), "$label: the site header's skip link is there" );
	ok( 1 === preg_match_all( '#\sid="main"#', $sout ) && 1 === substr_count( $sout, $p[2] ), "$label: exactly one #main, on " . strtok( $p[2], ' ' ) . '>' );
	ok( strpos( $sout, '</header>', strpos( $sout, '<header class="site-header">' ) ) < strpos( $sout, ' id="main"' ), "$label: #main comes after the site header, where the content starts" );
}
ok( false === strpos( file_get_contents( __DIR__ . '/fixtures/jengo-listings/course-detail.html' ), '<main' ), 'the saved course page has no <main> of its own (why it needs this)' );
$skip_html = '<x>' . $skip_header . "\n<!-- HERO -->\n<style>.a{}</style>\n<div class=\"course-hero\">y</div>";
ok( false !== strpos( prospergenics_2026_listings_skip_target( $skip_html, $skip_header ), "<style>.a{}</style>\n<div id=\"main\" tabindex=\"-1\" class=\"course-hero\">" ), 'comments and style blocks after the header are stepped over' );
ok( false !== strpos( prospergenics_2026_listings_skip_target( $skip_header . '<div id="hero">y</div>', $skip_header ), '<div id="main" tabindex="-1"></div><div id="hero">' ), 'an element that has an id of its own keeps it; an empty target goes before it' );
ok( 'x' === prospergenics_2026_listings_skip_target( 'x', $skip_header ) && $skip_header . '<p id="main">' === prospergenics_2026_listings_skip_target( $skip_header . '<p id="main">', $skip_header ), 'no header found, or a #main already there: the page is left as it was' );
ok( false !== strpos( $css, '#main[tabindex="-1"]:focus { outline: none; }' ), 'the focused skip target (a whole banner or breadcrumb) gets no focus ring of its own' );
ok( (bool) preg_match( '~target\.scrollIntoView\(\);\s*return;\s*\}\s*//[^\n]*#main[^\n]*\n[^\n]*\n\s*if \(target && !first\) return;~', $ljs ), 'on a lesson page the skip link moves to #main and leaves the open session alone (it used to reopen session 1)' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

// stubs for prospergenics_2026_posts_listing() (NAV-9)
function get_option( $k, $d = false ) { return isset( $GLOBALS['__opt'][ $k ] ) ? $GLOBALS['__opt'][ $k ] : $d; }
function get_post_status( $id ) { return isset( $GLOBALS['__pages'][ $id ] ) ? $GLOBALS['__pages'][ $id ] : false; }
function get_permalink( $id ) { return TEST_HOME . '/blog/'; }
function get_the_title( $id ) { return 'Blog'; }
function post_type_exists( $t ) { return empty( $GLOBALS['__no_articles'] ); }
function get_post_type_archive_link( $t ) { return TEST_HOME . '/articles/'; }

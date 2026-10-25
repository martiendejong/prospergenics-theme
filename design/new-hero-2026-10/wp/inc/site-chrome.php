<?php
/**
 * The site's one header and footer (audit NAV-1, NAV-2, NAV-3, NAV-8, ARC-3).
 *
 * The menu is defined once, in inc/site-chrome/nav.json; its look and behaviour once, in
 * inc/site-chrome/site-chrome.css and site-chrome.js. This module renders them for WordPress:
 *   - header.php / footer.php (every inner page) and front-page.php / page-about.php, which
 *     build-wp-theme.py points at prospergenics_2026_site_header() and _site_footer(),
 *   - pages rendered by the jengo-listings plugin (/members/, /tools/, /services/, /partners/,
 *     /vacancies/ and their detail pages), whose own nav is swapped for this header on the way
 *     out (see the takeover at the bottom), since the plugin itself can't be changed here.
 * site_chrome.py renders the same files for the static pages (people/, experts/, community/).
 *
 * Visitors get "Log in" and "Join"; logged-in members an account menu with My profile,
 * My courses and Log out. Below 1181px all of it moves into the menu panel, so the bar is one row.
 */

defined( 'ABSPATH' ) || exit;

/** nav.json, decoded once. */
function prospergenics_2026_site_nav() {
    static $nav = null;
    if ( null === $nav ) {
        $nav = json_decode( (string) file_get_contents( __DIR__ . '/site-chrome/nav.json' ), true );
        if ( ! is_array( $nav ) ) {
            $nav = array();
        }
        $nav += array( 'main' => array(), 'cta' => array( 'label' => 'Take the test', 'path' => '/#value-test' ), 'join_subject' => 'Joining the community', 'footer' => array(), 'legal' => array() );
    }
    return $nav;
}

/** Absolute URL for a nav.json item ({label, path} or {label, url}). */
function prospergenics_2026_nav_url( array $item ) {
    return isset( $item['url'] ) ? $item['url'] : home_url( $item['path'] );
}

/**
 * The main menu: Courses, Members, Tools, Services, Partners, About.
 *
 * @return array<int,array{label:string,path:string,url:string}>
 */
function prospergenics_2026_main_nav_items() {
    $items = array();
    foreach ( prospergenics_2026_site_nav()['main'] as $item ) {
        $items[] = array( 'label' => $item['label'], 'path' => $item['path'], 'url' => prospergenics_2026_nav_url( $item ) );
    }
    return $items;
}

/** Label of the main-menu section a path belongs to ('/services/coachos/' -> 'Services'), or ''. */
function prospergenics_2026_current_nav_label( $path ) {
    $path = '/' . ltrim( (string) $path, '/' );
    foreach ( prospergenics_2026_site_nav()['main'] as $item ) {
        if ( 0 === strpos( trailingslashit( $path ), $item['path'] ) ) {
            return $item['label'];
        }
    }
    return '';
}

/** The subjects the contact form offers (page-contact.php); ?subject= may preselect one of them. */
function prospergenics_2026_contact_subjects() {
    return array( 'Courses and certification', 'Services for my organisation', 'Tools', 'Partnership', 'Joining the community', 'Something else' );
}

/** /contact/ with a subject preselected. */
function prospergenics_2026_contact_url( $subject ) {
    return add_query_arg( 'subject', rawurlencode( $subject ), home_url( '/contact/' ) );
}

/** The current request path, e.g. '/members/lessy/'. */
function prospergenics_2026_request_path() {
    return isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '/';
}

/**
 * Account links for the header: Log in + Join for visitors; My profile, My courses and Log out
 * for members. Registration is closed, so Join opens the contact form on "Joining the community".
 *
 * @return array<int,array{label:string,url:string,key:string}>
 */
function prospergenics_2026_account_links() {
    if ( ! is_user_logged_in() ) {
        return array(
            array( 'key' => 'login', 'label' => 'Log in', 'url' => wp_login_url( home_url( add_query_arg( array() ) ) ) ),
            array( 'key' => 'join', 'label' => 'Join', 'url' => prospergenics_2026_contact_url( prospergenics_2026_site_nav()['join_subject'] ) ),
        );
    }
    $links = array();
    if ( function_exists( 'prospergenics_profiles_url' ) ) {
        $links[] = array( 'key' => 'profile', 'label' => 'My profile', 'url' => prospergenics_profiles_url( get_current_user_id() ) );
    }
    $links[] = array( 'key' => 'courses', 'label' => 'My courses', 'url' => home_url( '/courses/my/' ) );
    $links[] = array( 'key' => 'logout', 'label' => 'Log out', 'url' => wp_logout_url( home_url( '/' ) ) );
    return $links;
}

/**
 * The site header with its mobile menu.
 *
 * @param array $args 'current' => menu label to mark (default: from the request path),
 *                    'autohide' => true on the homepage (fixed, hides while scrolling down).
 * @return string HTML
 */
function prospergenics_2026_site_header( $args = array() ) {
    $args    = array_merge( array( 'current' => null, 'autohide' => false ), (array) $args );
    $current = null === $args['current'] ? prospergenics_2026_current_nav_label( prospergenics_2026_request_path() ) : (string) $args['current'];
    $nav     = prospergenics_2026_site_nav();
    $cta     = prospergenics_2026_nav_url( $nav['cta'] );
    $classes = 'site-header';
    if ( $args['autohide'] ) {
        $classes .= ' site-header--autohide';
    }
    if ( function_exists( 'is_admin_bar_showing' ) && is_admin_bar_showing() ) {
        $classes .= ' site-header--adminbar';
    }

    $links = function ( $indent ) use ( $current ) {
        $out = '';
        foreach ( prospergenics_2026_main_nav_items() as $item ) {
            $cur  = $item['label'] === $current ? ' aria-current="page"' : '';
            $out .= $indent . '<a href="' . esc_url( $item['url'] ) . '"' . $cur . '>' . esc_html( $item['label'] ) . "</a>\n";
        }
        return $out;
    };
    $account = prospergenics_2026_account_links();

    $h  = '<header class="' . esc_attr( $classes ) . '">' . "\n";
    $h .= '  <a class="brand" href="' . esc_url( home_url( '/' ) ) . '" aria-label="ProsperGenics home">' . "\n";
    $h .= '    <img src="' . esc_url( get_template_directory_uri() . '/assets/logo.png' ) . '" alt="" width="42" height="40" />' . "\n";
    $h .= '    <span class="brand-text">ProsperGenics</span>' . "\n";
    $h .= "  </a>\n";
    $h .= '  <nav class="nav-links" aria-label="Main">' . "\n" . $links( '    ' ) . "  </nav>\n";
    $h .= '  <div class="nav-account">' . "\n";
    if ( is_user_logged_in() ) {
        $uid   = get_current_user_id();
        $photo = function_exists( 'prospergenics_profiles_photo_url' ) ? prospergenics_profiles_photo_url( $uid, 'thumbnail' ) : '';
        $user  = wp_get_current_user();
        $name  = $user && $user->display_name ? $user->display_name : 'Account';
        $face  = $photo
            ? '<img src="' . esc_url( $photo ) . '" alt="" width="32" height="32" />'
            : '<span class="nav-account-initial" aria-hidden="true">' . esc_html( function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $name, 0, 1 ) ) : strtoupper( substr( $name, 0, 1 ) ) ) . '</span>';
        $h .= '    <button class="nav-account-toggle" type="button" aria-expanded="false" aria-controls="account-menu" title="' . esc_attr( $name ) . '">' . $face . '<span>My account</span></button>' . "\n";
        $h .= '    <div class="nav-account-menu" id="account-menu" hidden>' . "\n";
        foreach ( $account as $link ) {
            $h .= '      <a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . "</a>\n";
        }
        $h .= "    </div>\n";
    } else {
        foreach ( $account as $link ) {
            $class = 'join' === $link['key'] ? 'nav-account-link nav-account-join' : 'nav-account-link';
            $h    .= '    <a class="' . $class . '" href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . "</a>\n";
        }
    }
    $h .= "  </div>\n";
    $h .= '  <a class="nav-cta" href="' . esc_url( $cta ) . '">' . esc_html( $nav['cta']['label'] ) . "</a>\n";
    $h .= '  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">' . "\n";
    $h .= '    <span class="nav-toggle-bars" aria-hidden="true"></span>' . "\n";
    $h .= "  </button>\n";
    $h .= '  <div class="nav-panel" id="mobile-menu" hidden>' . "\n";
    $h .= '    <nav aria-label="Mobile">' . "\n" . $links( '      ' );
    $h .= '      <div class="nav-panel-account">' . "\n";
    foreach ( $account as $link ) {
        $h .= '        <a href="' . esc_url( $link['url'] ) . '">' . esc_html( $link['label'] ) . "</a>\n";
    }
    $h .= "      </div>\n";
    $h .= '      <a class="nav-panel-cta" href="' . esc_url( $cta ) . '">' . esc_html( $nav['cta']['label'] ) . "</a>\n";
    $h .= "    </nav>\n  </div>\n</header>";
    return $h;
}

/** The site footer: brand, Explore / Community / Get in touch columns and the legal links. */
function prospergenics_2026_site_footer() {
    $nav = prospergenics_2026_site_nav();
    $f   = '<footer class="site-footer">' . "\n" . '  <div class="site-footer-inner">' . "\n";
    $f  .= '    <div class="f-brand">' . "\n";
    $f  .= '      <img src="' . esc_url( get_template_directory_uri() . '/assets/logo.png' ) . '" alt="ProsperGenics" width="46" height="44" />' . "\n";
    $f  .= "      <p>A community in Kenya and the Netherlands researching and practising how value is created.</p>\n";
    $f  .= "    </div>\n";
    foreach ( $nav['footer'] as $column ) {
        $f .= "    <div>\n      <h2>" . esc_html( $column['title'] ) . "</h2>\n      <ul>\n";
        foreach ( $column['links'] as $item ) {
            $external = isset( $item['url'] ) ? ' target="_blank" rel="noopener"' : '';
            $f       .= '        <li><a href="' . esc_url( prospergenics_2026_nav_url( $item ) ) . '"' . $external . '>' . esc_html( $item['label'] ) . "</a></li>\n";
        }
        $f .= "      </ul>\n    </div>\n";
    }
    $f .= "  </div>\n" . '  <div class="site-footer-base">' . "\n    <span>© ProsperGenics</span>\n" . '    <nav aria-label="Legal">' . "\n";
    foreach ( $nav['legal'] as $item ) {
        $f .= '      <a href="' . esc_url( prospergenics_2026_nav_url( $item ) ) . '">' . esc_html( $item['label'] ) . "</a>\n";
    }
    $f .= "    </nav>\n  </div>\n</footer>";
    return $f;
}

/** The shared header/footer CSS, for a <style> element. */
function prospergenics_2026_site_chrome_css() {
    return (string) file_get_contents( __DIR__ . '/site-chrome/site-chrome.css' );
}

/** The shared header script, wrapped in a <script> element. */
function prospergenics_2026_site_chrome_script() {
    return "<script>\n" . file_get_contents( __DIR__ . '/site-chrome/site-chrome.js' ) . "</script>\n";
}

/* ───────────────────── jengo-listings takeover (NAV-1/2/3/5) ─────────────────────
 *
 * The plugin prints its own page: its own <nav class="pg-nav"> (a different menu, links centred,
 * no mobile menu) and no footer. It does call wp_head() and wp_footer(), so on its pages the theme
 * buffers everything from the end of wp_head to the start of wp_footer and then:
 *   - swaps that nav for the site header (+ its CSS before </head>),
 *   - points the detail pages' main button ("Discuss your project →", ...), which links to the
 *     homepage, at the contact form with the matching subject,
 *   - adds the site footer and the menu script before the plugin's own scripts.
 * Anything unexpected (no plugin nav found, another buffer left open) leaves the page untouched.
 */

/** Whether this request is a page rendered by jengo-listings (its style or script is queued). */
function prospergenics_2026_is_listings_page() {
    $on = wp_style_is( 'jengo-listings', 'enqueued' ) || wp_style_is( 'jengo-listings', 'done' ) || wp_script_is( 'jengo-listings', 'enqueued' );
    return (bool) apply_filters( 'prospergenics_2026_listings_takeover', $on );
}

/** Contact subject for a plugin button: by its text, else by the section the page is in. */
function prospergenics_2026_listings_cta_subject( $label, $path ) {
    $by_label = array(
        'discuss your project' => 'Services for my organisation',
        'ask about this tool'  => 'Tools',
        'become a partner'     => 'Partnership',
    );
    $key = strtolower( trim( (string) preg_replace( '/[\s→]+/u', ' ', wp_strip_all_tags( html_entity_decode( $label, ENT_QUOTES, 'UTF-8' ) ) ) ) );
    if ( isset( $by_label[ $key ] ) ) {
        return $by_label[ $key ];
    }
    $by_section = array( 'services' => 'Services for my organisation', 'tools' => 'Tools', 'partners' => 'Partnership', 'vacancies' => 'Joining the community' );
    $section    = strtok( trim( (string) $path, '/' ), '/' );
    return isset( $by_section[ $section ] ) ? $by_section[ $section ] : 'Something else';
}

/**
 * Rewrite one buffered jengo-listings page (pure: no output, no globals).
 *
 * @param string $html   Everything printed between the end of wp_head and the start of wp_footer.
 * @param string $path   Request path, e.g. '/services/coachos/'.
 * @param array  $chrome 'header', 'footer', 'css', 'script' markup.
 * @return string The page with the theme's header and footer, or $html unchanged.
 */
function prospergenics_2026_listings_takeover( $html, $path, array $chrome ) {
    $nav_re = '#<nav class="pg-nav"[^>]*>.*?</nav>#s';
    if ( ! preg_match( $nav_re, $html ) ) {
        return $html;
    }
    $header = $chrome['header'];
    $out    = preg_replace_callback( $nav_re, function () use ( $header ) { return $header; }, $html, 1 );
    if ( null === $out ) {
        return $html;
    }

    $css = '<style id="prospergenics-site-chrome-css">' . "\n" . $chrome['css'] . "</style>\n";
    $at  = stripos( $out, '</head>' );
    $out = false !== $at ? substr_replace( $out, $css, $at, 0 ) : str_replace( $header, $css . $header, $out );

    // Detail pages: the main button links to the homepage; send it to the contact form instead.
    $home = preg_quote( untrailingslashit( home_url() ), '#' );
    $out  = preg_replace_callback(
        '#<a class="facts-cta" href="' . $home . '/?"([^>]*)>(.*?)</a>#s',
        function ( $m ) use ( $path ) {
            $url = prospergenics_2026_contact_url( prospergenics_2026_listings_cta_subject( $m[2], $path ) );
            return '<a class="facts-cta" href="' . esc_url( $url ) . '"' . $m[1] . '>' . $m[2] . '</a>';
        },
        $out
    );

    return rtrim( $out ) . "\n\n" . $chrome['footer'] . "\n" . $chrome['script'] . "\n";
}

add_action( 'wp_head', function () {
    global $prospergenics_2026_takeover_level;
    if ( is_admin() || is_feed() || did_action( 'get_header' ) || ! prospergenics_2026_is_listings_page() ) {
        return; // theme templates print the site header themselves
    }
    ob_start();
    $prospergenics_2026_takeover_level = ob_get_level();
}, PHP_INT_MAX );

add_action( 'wp_footer', function () {
    global $prospergenics_2026_takeover_level;
    if ( empty( $prospergenics_2026_takeover_level ) || ob_get_level() !== $prospergenics_2026_takeover_level ) {
        return; // not buffering, or someone else's buffer is open on top: leave the page as it is
    }
    $prospergenics_2026_takeover_level = 0;
    $html = ob_get_clean();
    echo prospergenics_2026_listings_takeover( $html, prospergenics_2026_request_path(), array( // phpcs:ignore WordPress.Security.EscapeOutput -- page markup, built from escaped parts.
        'header' => prospergenics_2026_site_header(),
        'footer' => prospergenics_2026_site_footer(),
        'css'    => prospergenics_2026_site_chrome_css(),
        'script' => prospergenics_2026_site_chrome_script(),
    ) );
}, PHP_INT_MIN );

<?php
/**
 * The service cards on /services/ and "What you get" on /services/<slug>/ (card #7219).
 *
 * Lesiamon: "make the Service page match the section on the homepage". The four services have one
 * copy of their content, inc/services/services.json (name, promise, three points, the example image
 * and its label, the link text), and one copy of the styles, inc/services/services.css:
 *   - the homepage "Our services" section is written from them by site_chrome.py (services_section.py),
 *     in the same markup this file renders (tests/test-2026-services.php compares the two);
 *   - /services/ (jengo-listings): the plugin's plain card grid becomes the same four cards and the
 *     "Discuss your project" button; its header, breadcrumb, count and search box stay. When the
 *     plugin answers a search, only the services it found are shown.
 *   - /services/<slug>/: after the plugin's description, a "What you get" list with the example image
 *     and its label; the plugin's text, FAQ, "At a glance" box and "← All services" stay.
 * Both run inside the jengo-listings takeover (inc/site-chrome.php, 'prospergenics_2026_listings_page_html').
 * Anything unexpected (the plugin changed its markup, a service the JSON doesn't know) leaves the
 * plugin's page exactly as it was.
 */

defined( 'ABSPATH' ) || exit;

/** services.json: array{cta:array, services:array<int,array>} (empty arrays when unreadable). */
function prospergenics_2026_services_data() {
    static $data = null;
    if ( null === $data ) {
        $raw  = @file_get_contents( __DIR__ . '/services/services.json' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a missing file means "no rewrite"
        $data = is_string( $raw ) ? json_decode( $raw, true ) : null;
        if ( ! is_array( $data ) || empty( $data['services'] ) || empty( $data['cta'] ) ) {
            $data = array( 'cta' => array(), 'services' => array() );
        }
    }
    return $data;
}

/** The services by slug, in their order. */
function prospergenics_2026_services_by_slug() {
    $out = array();
    foreach ( prospergenics_2026_services_data()['services'] as $s ) {
        $out[ (string) $s['slug'] ] = $s;
    }
    return $out;
}

/** The card styles, for a <style> element. */
function prospergenics_2026_services_css() {
    return (string) @file_get_contents( __DIR__ . '/services/services.css' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
}

/** The example image with its visible label (the <img> has an empty alt: the label says what it is), or the branded panel (pure). */
function prospergenics_2026_services_media( array $s, $assets ) {
    if ( empty( $s['image'] ) ) {
        return '<div class="svc-media svc-media--brand" aria-hidden="true">' . $s['panel_svg'] . '</div>';
    }
    $img = $s['image'];
    return '<figure class="svc-media"><img src="' . esc_url( $assets . '/' . $img['src'] ) . '" width="' . (int) $img['width'] . '" height="' . (int) $img['height'] . '" alt="" loading="lazy" decoding="async" style="object-position:' . esc_attr( $img['position'] ) . '" />'
        . '<figcaption class="svc-ex">' . esc_html( $img['label'] ) . '</figcaption></figure>';
}

/** The three "what you get" points (pure). */
function prospergenics_2026_services_points( array $s ) {
    $li = '';
    foreach ( (array) $s['points'] as $p ) {
        $li .= '<li>' . esc_html( $p ) . '</li>';
    }
    return '<ul class="svc-points">' . $li . '</ul>';
}

/**
 * One card (pure). Same markup as services_section.py card() writes on the homepage.
 *
 * @param string $home    site URL without a trailing slash
 * @param string $assets  theme assets URL without a trailing slash
 * @param string $heading h3 under a section h2 (homepage), h2 under the page's h1 (/services/)
 */
function prospergenics_2026_services_card( array $s, $home, $assets, $heading = 'h3' ) {
    $h = 'h2' === $heading ? 'h2' : 'h3';
    return "      <article class=\"svc-card\">\n"
        . '        ' . prospergenics_2026_services_media( $s, $assets ) . "\n"
        . "        <div class=\"svc-body\">\n"
        . '          <' . $h . ' class="svc-name">' . esc_html( $s['name'] ) . '</' . $h . ">\n"
        . '          <p class="svc-promise">' . esc_html( $s['promise'] ) . "</p>\n"
        . '          ' . prospergenics_2026_services_points( $s ) . "\n"
        . '          <a class="svc-more" href="' . esc_url( $home . '/services/' . $s['slug'] . '/' ) . '">' . esc_html( $s['link'] ) . " <span aria-hidden=\"true\">→</span></a>\n"
        . "        </div>\n"
        . '      </article>';
}

/**
 * The grid of cards and the "Discuss your project" row (pure). Same markup as
 * services_section.py cards().
 *
 * @param array  $services    the services to show, in order
 * @param string $contact_url the contact form with the services subject
 */
function prospergenics_2026_services_cards( array $services, $home, $assets, $contact_url, $heading = 'h3' ) {
    $cta   = prospergenics_2026_services_data()['cta'];
    $cards = array();
    foreach ( $services as $s ) {
        $cards[] = prospergenics_2026_services_card( $s, $home, $assets, $heading );
    }
    return "    <div class=\"svc-grid\">\n" . implode( "\n", $cards ) . "\n    </div>\n"
        . '    <div class="svc-cta"><a class="svc-cta-btn" href="' . esc_url( $contact_url ) . '">' . esc_html( $cta['label'] ) . ' <span aria-hidden="true">→</span></a><p class="svc-cta-note">' . esc_html( $cta['note'] ) . '</p></div>';
}

/** Where the <div> that opens at $start ends, just after its closing tag (pure). */
function prospergenics_2026_services_div_end( $html, $start ) {
    $depth = 0;
    if ( ! preg_match_all( '#<(/?)div\b[^>]*>#i', $html, $m, PREG_OFFSET_CAPTURE, $start ) ) {
        return false;
    }
    foreach ( $m[0] as $i => $tag ) {
        $depth += '' === $m[1][ $i ][0] ? 1 : -1;
        if ( 0 === $depth ) {
            return $tag[1] + strlen( $tag[0] );
        }
    }
    return false;
}

/** The card styles before </head> (pure; once). */
function prospergenics_2026_services_add_css( $html, $css ) {
    if ( '' === trim( (string) $css ) || false !== strpos( $html, 'id="prospergenics-services-css"' ) || 1 !== substr_count( $html, '</head>' ) ) {
        return $html;
    }
    return str_replace( '</head>', '<style id="prospergenics-services-css">' . "\n" . $css . "</style>\n</head>", $html );
}

/**
 * /services/: the plugin's card grid -> the homepage's service cards (pure).
 *
 * Only when the page has its header, card area and grid exactly once, and every card the plugin
 * listed is a service in services.json (shown in the plugin's order, so a search keeps its result). A "no results" page, a page of another shape or an unknown service: unchanged.
 *
 * @param array<string,array> $services slug => service (prospergenics_2026_services_by_slug())
 */
function prospergenics_2026_services_list_rewrite( $html, array $services, $home, $assets, $contact_url, $css ) {
    $html = (string) $html;
    if ( ! $services || 1 !== substr_count( $html, '<div class="dir-wrap">' ) || 1 !== substr_count( $html, '<header class="pg-header">' ) || 1 !== substr_count( $html, '<div class="dir-grid">' ) ) {
        return $html;
    }
    $start = strpos( $html, '<div class="dir-wrap">' );
    $end   = prospergenics_2026_services_div_end( $html, $start );
    if ( false === $end ) {
        return $html;
    }
    $wrap = substr( $html, $start, $end - $start );
    $re   = '#<a class="dir-card" href="' . preg_quote( $home, '#' ) . '/services/([a-z0-9-]+)/">#';
    if ( ! preg_match_all( $re, $wrap, $m ) || count( $m[1] ) !== substr_count( $wrap, '<a class="dir-card"' ) ) {
        return $html;
    }
    $show = array();
    foreach ( $m[1] as $slug ) {
        if ( ! isset( $services[ $slug ] ) || isset( $show[ $slug ] ) ) {
            return $html;
        }
        $show[ $slug ] = $services[ $slug ];
    }
    $body = prospergenics_2026_services_cards( array_values( $show ), $home, $assets, $contact_url, 'h2' );
    $html = substr_replace( $html, "<div class=\"dir-wrap\">\n" . $body . "\n  </div>", $start, $end - $start );
    return prospergenics_2026_services_add_css( $html, $css );
}

/**
 * /services/<slug>/: "What you get" and the labelled example after the plugin's description (pure).
 * Only when the description, the "← All services" link and the main column are there exactly once,
 * in that order, and only once per page.
 */
function prospergenics_2026_services_detail_rewrite( $html, array $s, $assets, $css ) {
    $html = (string) $html;
    if ( false !== strpos( $html, 'class="svc-get"' ) || 1 !== substr_count( $html, '<main id="main" class="dir-main">' ) || 1 !== substr_count( $html, '<div class="dir-desc">' ) || 1 !== substr_count( $html, '<div class="dir-back">' ) ) {
        return $html;
    }
    $start = strpos( $html, '<div class="dir-desc">' );
    $end   = prospergenics_2026_services_div_end( $html, $start );
    if ( false === $end || $end > strpos( $html, '<div class="dir-back">' ) || $start < strpos( $html, '<main id="main" class="dir-main">' ) ) {
        return $html;
    }
    $get = "\n\n      <section class=\"svc-get\" aria-labelledby=\"svc-get-h\">\n"
        . "        <h2 id=\"svc-get-h\">What you get</h2>\n"
        . '        ' . prospergenics_2026_services_points( $s ) . "\n"
        . ( empty( $s['image'] ) ? '' : '        ' . prospergenics_2026_services_media( $s, $assets ) . "\n" )
        . '      </section>';
    $html = substr_replace( $html, $get, $end, 0 );
    return prospergenics_2026_services_add_css( $html, $css );
}

/**
 * The takeover filter: which rewrite, by request path (pure apart from reading services.json).
 *
 * @param string $path '/services/' or '/services/<slug>/'
 */
function prospergenics_2026_services_page_html( $html, $path, $home, $assets ) {
    $services = prospergenics_2026_services_by_slug();
    if ( ! $services ) {
        return $html;
    }
    $css = prospergenics_2026_services_css();
    if ( '/services/' === $path ) {
        $contact = prospergenics_2026_contact_url( prospergenics_2026_services_data()['cta']['subject'] );
        return prospergenics_2026_services_list_rewrite( $html, $services, $home, $assets, $contact, $css );
    }
    if ( preg_match( '#^/services/([a-z0-9-]+)/$#', (string) $path, $m ) && isset( $services[ $m[1] ] ) ) {
        return prospergenics_2026_services_detail_rewrite( $html, $services[ $m[1] ], $assets, $css );
    }
    return $html;
}

// Inside the jengo-listings takeover (inc/site-chrome.php), next to the /members/ cards.
add_filter( 'prospergenics_2026_listings_page_html', function ( $html, $path ) {
    if ( 0 !== strpos( (string) $path, '/services/' ) ) {
        return $html;
    }
    return prospergenics_2026_services_page_html( $html, $path, untrailingslashit( home_url() ), get_template_directory_uri() . '/assets' );
}, 10, 2 );

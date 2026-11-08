<?php
/**
 * More steps for the jengo-listings pages (/tools/, /services/, /partners/, /members/,
 * /vacancies/, their detail pages and the plugin's /courses/<slug>/ pages), on top of the
 * takeover in inc/site-chrome.php. Each step is a pure function of the page's HTML and leaves a
 * page of any other shape exactly as the plugin made it.
 *
 *   - Card pictures (card #6538): a card without a photo or logo gets the branded pattern of the
 *     /courses/ cards instead of one big letter (J, C, R, ...). Through the takeover's
 *     'prospergenics_2026_listings_page_html' filter.
 *   - The page's <head>, which the plugin prints before wp_head() and so before the takeover's
 *     buffer starts: a buffer around the whole page (started on init, used only once wp_head has
 *     shown it is a listings page) runs prospergenics_2026_listings_document() on it:
 *       - fonts (card #7231): the plugin's Google Fonts <link>s and preconnects go, the theme's
 *         self-hosted assets/fonts/fonts.css (Sora, Inter) comes in their place, as on theme pages.
 */

defined( 'ABSPATH' ) || exit;

/** Which of the three pattern textures a slug gets (1-3), the same as on the /courses/ cards (pure). */
function prospergenics_2026_pattern_variant( $slug ) {
    return abs( crc32( (string) $slug ) ) % 3 + 1;
}

/** The branded pattern (styles: inc/site-chrome/site-chrome.css), decorative (pure). */
function prospergenics_2026_pattern( $slug ) {
    return '<span class="pg-pattern pg-pattern--' . prospergenics_2026_pattern_variant( $slug ) . '" aria-hidden="true"></span>';
}

/**
 * The plugin's directory cards draw <div class="dc-initial">J</div> (the first letter of the name,
 * on a green tile) when the item has no image. Swap that letter for the branded pattern, picked by
 * the card's slug; a card with an image, the status pill ("Active") and everything else stay as
 * they are (pure).
 */
function prospergenics_2026_listings_card_patterns( $html ) {
    $out = preg_replace_callback(
        '#(<a class="dir-card" href="[^"]*?/([a-z0-9-]+)/?">\s*<div class="dc-thumb">)(.*?)(</div>\s*<div class="dc-body">)#s',
        function ( $m ) {
            if ( false !== stripos( $m[3], '<img' ) || 1 !== preg_match_all( '#<div class="dc-initial">[^<]*</div>#', $m[3] ) ) {
                return $m[0];
            }
            $thumb = preg_replace( '#\s*<div class="dc-initial">[^<]*</div>\s*#', prospergenics_2026_pattern( $m[2] ), $m[3], 1 );
            return null === $thumb ? $m[0] : $m[1] . $thumb . $m[4];
        },
        (string) $html
    );
    return null === $out ? $html : $out;
}

// After the /members/ and /services/ rebuilds (priority 10), so it sees the final cards.
add_filter( 'prospergenics_2026_listings_page_html', function ( $html ) {
    return prospergenics_2026_listings_card_patterns( $html );
}, 20 );

/* ───────────────────── the whole page, <head> included ───────────────────── */

/** Where the page's <head> ends: the offset of its first </head>, or false (pure). */
function prospergenics_2026_listings_head_end( $html ) {
    $at = stripos( (string) $html, '</head>' );
    return false !== $at && false !== stripos( substr( (string) $html, 0, $at ), '<head' ) ? $at : false;
}

/**
 * Self-hosted fonts on a jengo-listings page (card #7231, as theme pages since #6451). The plugin
 * prints, at the top of its <head>:
 *   <link rel="preconnect" href="https://fonts.googleapis.com" />
 *   <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
 *   <link href="https://fonts.googleapis.com/css2?family=Sora:...&family=Inter:...&display=swap" rel="stylesheet" />
 * Every <link> in the <head> to those two hosts goes; the theme's fonts.css (the same Sora 400-800
 * and Inter 400-600, latin) takes the place of the stylesheet, so the plugin's styles after it
 * still find the fonts. Pure; a page without a Google Fonts stylesheet, or without a <head>, is
 * left as it was, and fonts.css is not added twice (the /vacancies/ template links it itself).
 *
 * @param string $fonts_css URL of the theme's assets/fonts/fonts.css.
 */
function prospergenics_2026_listings_self_host_fonts( $html, $fonts_css ) {
    $html = (string) $html;
    $end  = prospergenics_2026_listings_head_end( $html );
    if ( false === $end || '' === (string) $fonts_css ) {
        return $html;
    }
    $head   = substr( $html, 0, $end );
    $google = '#<link\b(?=[^>]*\bhref=["\']https://fonts\.(?:googleapis|gstatic)\.com[/"\'])[^>]*>[ \t]*\R?#i';
    if ( ! preg_match_all( $google, $head, $links, PREG_OFFSET_CAPTURE ) ) {
        return $html;
    }
    $sheet = false;
    foreach ( $links[0] as $l ) {
        if ( false === $sheet && preg_match( '#\brel=["\']?stylesheet\b#i', $l[0] ) && preg_match( '#\bhref=["\']https://fonts\.googleapis\.com/css#i', $l[0] ) ) {
            $sheet = $l[1];
        }
    }
    if ( false === $sheet ) {
        return $html; // preconnects only: not the plugin's shape, leave it
    }
    $ours = false !== strpos( $head, '/assets/fonts/fonts.css' ) ? '' : '<link rel="stylesheet" href="' . esc_url( $fonts_css ) . '" />' . "\n";
    $new  = '';
    $at   = 0;
    foreach ( $links[0] as $l ) {
        $new .= substr( $head, $at, $l[1] - $at ) . ( $l[1] === $sheet ? $ours : '' );
        $at   = $l[1] + strlen( $l[0] );
    }
    return $new . substr( $head, $at ) . substr( $html, $end );
}

/**
 * Everything the theme changes on a whole jengo-listings page, <head> included (pure).
 *
 * @param array $args 'fonts_css' => URL of the theme's fonts.css.
 */
function prospergenics_2026_listings_document( $html, array $args ) {
    $html = prospergenics_2026_listings_self_host_fonts( $html, isset( $args['fonts_css'] ) ? $args['fonts_css'] : '' );
    return $html;
}

/*
 * The buffer around the whole page. The plugin prints its <head> before wp_head(), so the
 * takeover's own buffer (wp_head to wp_footer, inc/site-chrome.php) starts too late for it. This
 * one starts on init for every front-end GET, and does nothing unless wp_head then finds a
 * listings page (the same test as the takeover); a page flushed in parts (someone called
 * ob_flush) also goes out as it is.
 */
add_action( 'init', function () {
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
    if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( 'GET' !== $method && 'HEAD' !== $method ) ) {
        return;
    }
    ob_start( function ( $html, $phase ) {
        global $prospergenics_2026_listings_document;
        $whole = ( $phase & PHP_OUTPUT_HANDLER_START ) && ( $phase & PHP_OUTPUT_HANDLER_FINAL );
        if ( ! $whole || empty( $prospergenics_2026_listings_document ) ) {
            return $html;
        }
        $prospergenics_2026_listings_document = false;
        return prospergenics_2026_listings_document( $html, array(
            'fonts_css' => get_template_directory_uri() . '/assets/fonts/fonts.css',
        ) );
    } );
}, 2 );

add_action( 'wp_head', function () {
    global $prospergenics_2026_listings_document;
    $prospergenics_2026_listings_document = ! is_admin() && ! is_feed() && ! did_action( 'get_header' ) && function_exists( 'prospergenics_2026_is_listings_page' ) && prospergenics_2026_is_listings_page();
}, PHP_INT_MAX );

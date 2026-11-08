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
 *         self-hosted assets/fonts/fonts.css (Sora, Inter) comes in their place, as on theme pages
 *         (and the plugin's own listings.css, which @imports them again, is printed inline
 *         without that @import: the 'style_loader_tag' filter below);
 *       - one <title> (card #7233): the /vacancies/ template's bare <title>ProsperGenics</title>
 *         goes when the plugin prints the page's own;
 *       - sharing tags (card #7233): og:title, og:description, twitter:title and
 *         twitter:description from the page's own <title> and description, where missing.
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

/** The text of every <title> in the <head>, with its offset and full tag: [[tag, offset, text], ...] (pure). */
function prospergenics_2026_listings_titles( $html ) {
    $end = prospergenics_2026_listings_head_end( $html );
    if ( false === $end || ! preg_match_all( '#<title\b[^>]*>(.*?)</title>#is', substr( (string) $html, 0, $end ), $m, PREG_SET_ORDER | PREG_OFFSET_CAPTURE ) ) {
        return array();
    }
    $out = array();
    foreach ( $m as $t ) {
        $out[] = array( $t[0][0], $t[0][1], trim( html_entity_decode( wp_strip_all_tags( $t[1][0] ), ENT_QUOTES, 'UTF-8' ) ) );
    }
    return $out;
}

/**
 * One <title> (card #7233). The /vacancies/ template prints <title>ProsperGenics</title> at the
 * top of its <head> and the plugin then prints the page's own (<title>Vacancies</title>) from
 * wp_head; browsers and scrapers take the first. A <title> that is only the site name goes when
 * the <head> has another one with real text; any other combination is left alone (pure).
 */
function prospergenics_2026_listings_one_title( $html, $site_name = 'ProsperGenics' ) {
    $titles = prospergenics_2026_listings_titles( $html );
    if ( count( $titles ) < 2 ) {
        return $html;
    }
    $bare = array_filter( $titles, function ( $t ) use ( $site_name ) { return 0 === strcasecmp( $t[2], (string) $site_name ); } );
    $real = array_filter( $titles, function ( $t ) use ( $site_name ) { return '' !== $t[2] && 0 !== strcasecmp( $t[2], (string) $site_name ); } );
    if ( ! $bare || ! $real ) {
        return $html;
    }
    foreach ( array_reverse( $bare ) as $t ) { // from the end, so the earlier offsets stay right
        $len  = strlen( $t[0] );
        $len += preg_match( '#\G[ \t]*\R#', $html, $nl, 0, $t[1] + $len ) ? strlen( $nl[0] ) : 0;
        $html = substr_replace( $html, '', $t[1], $len );
    }
    return $html;
}

/**
 * Sharing tags for a jengo-listings page from the page itself (card #7233). The plugin prints the
 * page's <title> and <meta name="description"> (the item's name and summary on a detail page, the
 * list's on a list page); the theme prints og:url, og:image and twitter:card (inc/seo.php) but has
 * no title or description of its own for these pages. Add og:title, og:description, twitter:title
 * and twitter:description from that title and description, right after og:url, each only when
 * the page doesn't have it yet. Pure; a page without a <head>, title or description gets nothing
 * for that value.
 */
function prospergenics_2026_listings_share_tags( $html ) {
    $html = (string) $html;
    $end  = prospergenics_2026_listings_head_end( $html );
    if ( false === $end ) {
        return $html;
    }
    $head   = substr( $html, 0, $end );
    $titles = prospergenics_2026_listings_titles( $html );
    // one title and one description, or that value is unclear and stays out
    $title = 1 === count( $titles ) ? $titles[0][2] : '';
    $desc  = '';
    if ( 1 === preg_match_all( '#<meta\s+name=["\']description["\']\s+content=(["\'])(.*?)\1[^>]*>#is', $head, $d ) ) {
        $desc = trim( html_entity_decode( $d[2][0], ENT_QUOTES, 'UTF-8' ) );
    }
    $want = array(
        array( 'property', 'og:title', $title ),
        array( 'property', 'og:description', $desc ),
        array( 'name', 'twitter:title', $title ),
        array( 'name', 'twitter:description', $desc ),
    );
    $tags = '';
    foreach ( $want as $w ) {
        if ( '' !== $w[2] && ! preg_match( '#<meta\s[^>]*\b(?:property|name)=["\']' . preg_quote( $w[1], '#' ) . '["\']#i', $head ) ) {
            $tags .= '<meta ' . $w[0] . '="' . $w[1] . '" content="' . esc_attr( $w[2] ) . '" />' . "\n";
        }
    }
    if ( '' === $tags ) {
        return $html;
    }
    if ( preg_match( '#<meta\s+property=["\']og:url["\'][^>]*>[ \t]*\R?#i', $head, $u, PREG_OFFSET_CAPTURE ) ) {
        $at   = $u[0][1] + strlen( $u[0][0] );
        $tags = "\n" === substr( $u[0][0], -1 ) ? $tags : "\n" . $tags;
    } else {
        $at = $end;
    }
    return substr_replace( $html, $tags, $at, 0 );
}

/**
 * The plugin's own stylesheet (assets/listings.css) loads the same Google Fonts once more, with
 *   @import url('https://fonts.googleapis.com/css2?family=Sora:...&family=Inter:...&display=swap');
 * on its second line (card #7231). The @import rules to Google come out (pure); the rest stays.
 */
function prospergenics_2026_listings_css_without_google( $css ) {
    $out = preg_replace( '#@import\s+(?:url\(\s*)?(["\']?)https://fonts\.googleapis\.com/[^"\')\s]*\1\s*\)?[^;]*;[ \t]*\R?#i', '', (string) $css );
    return null === $out ? (string) $css : $out;
}

/**
 * The plugin's <link rel="stylesheet" id="jengo-listings-css"> as an inline <style> without its
 * Google Fonts @import, when it has one (pure). Relative url()s are made absolute against the
 * stylesheet's own URL, so they still point at the plugin's files. A stylesheet without a Google
 * @import, or one that could not be read ($css ''), keeps its <link>.
 *
 * @param string $tag   The <link> WordPress would print.
 * @param string $css   The stylesheet's text.
 * @param string $href  Its URL.
 * @param string $id    The handle's element id (jengo-listings-css).
 * @param string $media Its media attribute.
 */
function prospergenics_2026_listings_inline_style( $tag, $css, $href, $id, $media = 'all' ) {
    $clean = prospergenics_2026_listings_css_without_google( $css );
    if ( '' === trim( (string) $css ) || $clean === (string) $css ) {
        return $tag;
    }
    $base  = preg_replace( '#[?\#].*$#', '', (string) $href );
    $base  = substr( $base, 0, strrpos( $base, '/' ) + 1 );
    $clean = preg_replace_callback( '#url\(\s*(["\']?)(?![a-z][a-z0-9+.-]*:|/|\#)([^"\')]+)\1\s*\)#i', function ( $m ) use ( $base ) {
        return 'url(' . $m[1] . $base . $m[2] . $m[1] . ')';
    }, $clean );
    $media = ( '' !== (string) $media && 'all' !== $media ) ? ' media="' . esc_attr( $media ) . '"' : '';
    return '<style id="' . esc_attr( $id ) . '"' . $media . ">\n" . str_replace( '</style', '<\/style', (string) $clean ) . "\n</style>\n";
}

/** The file behind a URL under the plugins folder, or '' (a .css file inside WP_PLUGIN_DIR only). */
function prospergenics_2026_listings_plugin_file( $href ) {
    $url  = preg_replace( '#[?\#].*$#', '', (string) $href );
    $base = untrailingslashit( set_url_scheme( plugins_url(), 'https' ) );
    $url  = set_url_scheme( $url, 'https' );
    if ( 0 !== strpos( $url, $base . '/' ) || '.css' !== strtolower( substr( $url, -4 ) ) ) {
        return '';
    }
    $dir  = realpath( WP_PLUGIN_DIR );
    $file = realpath( WP_PLUGIN_DIR . '/' . rawurldecode( substr( $url, strlen( $base ) + 1 ) ) );
    return $dir && $file && 0 === strpos( $file, $dir . DIRECTORY_SEPARATOR ) && is_readable( $file ) ? $file : '';
}

add_filter( 'style_loader_tag', function ( $tag, $handle, $href, $media = 'all' ) {
    if ( 'jengo-listings' !== $handle || is_admin() ) {
        return $tag;
    }
    $file = prospergenics_2026_listings_plugin_file( $href );
    $css  = '' !== $file ? (string) file_get_contents( $file ) : '';
    return prospergenics_2026_listings_inline_style( $tag, $css, $href, $handle . '-css', $media );
}, 10, 4 );

/**
 * Everything the theme changes on a whole jengo-listings page, <head> included (pure).
 *
 * @param array $args 'fonts_css' => URL of the theme's fonts.css; 'site_name' => the bare site
 *                    title (default ProsperGenics).
 */
function prospergenics_2026_listings_document( $html, array $args ) {
    $html = prospergenics_2026_listings_self_host_fonts( $html, isset( $args['fonts_css'] ) ? $args['fonts_css'] : '' );
    $html = prospergenics_2026_listings_one_title( $html, isset( $args['site_name'] ) ? $args['site_name'] : 'ProsperGenics' );
    $html = prospergenics_2026_listings_share_tags( $html );
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
            'site_name' => get_bloginfo( 'name' ),
        ) );
    } );
}, 2 );

add_action( 'wp_head', function () {
    global $prospergenics_2026_listings_document;
    $prospergenics_2026_listings_document = ! is_admin() && ! is_feed() && ! did_action( 'get_header' ) && function_exists( 'prospergenics_2026_is_listings_page' ) && prospergenics_2026_is_listings_page();
}, PHP_INT_MAX );

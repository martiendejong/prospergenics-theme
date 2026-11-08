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

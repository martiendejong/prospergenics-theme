<?php
/**
 * The /members/ list from PROFILES (cards #6516, #6517, #6523).
 *
 * jengo-listings renders /members/ from its own "members" collection, a copy of the homepage
 * placeholder cards from 10 Oct that never followed the profile rewrite: other names ("Lessy"),
 * placeholder quotes, cards that link to /members/<listing slug>/, .png/.jpg photos the theme no
 * longer ships, and no Farid. The listings service is outside this repository, so the theme
 * rebuilds the list itself, in the plugin's own markup (its styles, search box and the theme's
 * header, footer, /find/ link and badges stay as they are):
 *
 *   - the cards come from inc/people.json, which people/build-profiles.py writes from PROFILES:
 *     every person with "members" on, in PROFILES order, with the name, role, quote, first three
 *     expertises and .webp portrait of their /people/ page, linking to /people/<slug>/;
 *   - the plugin's search (?keyword=) is answered from the same data;
 *   - the count under the heading and the ItemList JSON-LD (which the plugin prints before
 *     <!DOCTYPE>, outside the takeover buffer) name the same people.
 *
 * Which people are listed: filter 'prospergenics_2026_members_directory' (slug => person).
 * Anything unexpected (the plugin changed its markup) leaves the plugin's page as it was.
 * When the listings collection is redeployed from people/members-collection.json the plugin's own
 * data matches again, and this rewrite changes nothing a visitor can see.
 */

defined( 'ABSPATH' ) || exit;

/** The people on /members/, slug => person from people.json, in PROFILES order. */
function prospergenics_2026_members_people() {
    $people = function_exists( 'prospergenics_2026_people' ) ? prospergenics_2026_people() : array();
    $listed = array_filter( (array) $people, function ( $p ) {
        return is_array( $p ) && ( ! isset( $p['members'] ) || false !== $p['members'] );
    } );
    return (array) apply_filters( 'prospergenics_2026_members_directory', $listed );
}

/** The people that match a search, case-insensitive over name, role, quote and expertises (pure). */
function prospergenics_2026_members_search( array $people, $keyword ) {
    $words = preg_split( '/\s+/', strtolower( trim( (string) $keyword ) ), -1, PREG_SPLIT_NO_EMPTY );
    if ( ! $words ) {
        return $people;
    }
    return array_filter( $people, function ( $p ) use ( $words ) {
        $hay = strtolower( implode( ' ', array_merge(
            array( (string) $p['name'], (string) $p['role'], isset( $p['quote'] ) ? (string) $p['quote'] : '' ),
            isset( $p['expertises'] ) ? array_map( 'strval', (array) $p['expertises'] ) : array()
        ) ) );
        foreach ( $words as $w ) {
            if ( false === strpos( $hay, $w ) ) {
                return false;
            }
        }
        return true;
    } );
}

/**
 * One card, in the plugin's own markup (pure).
 *
 * @param string $home   site URL without trailing slash
 * @param string $assets theme assets URL without trailing slash
 */
function prospergenics_2026_members_card( $slug, array $p, $home, $assets ) {
    $name = (string) $p['name'];
    if ( ! empty( $p['img'] ) ) {
        $thumb = '<img src="' . esc_url( $assets . '/' . $p['img'] ) . '" alt="' . esc_attr( $name ) . '" style="object-position:' . esc_attr( ! empty( $p['pos'] ) ? $p['pos'] : 'center' ) . '" loading="lazy" />';
    } else {
        // no photo yet: the branded pattern of the /courses/ cards, not a big initial (card #6538);
        // the same texture as on the homepage slider and the /people/ page (by slug)
        $thumb = '<span class="pg-pattern pg-pattern--' . ( abs( crc32( (string) $slug ) ) % 3 + 1 ) . '" aria-hidden="true"></span>';
    }
    $tags = '';
    foreach ( array_slice( isset( $p['expertises'] ) ? (array) $p['expertises'] : array(), 0, 3 ) as $tag ) {
        $tags .= "\n              <span class=\"dc-tag\">" . esc_html( $tag ) . '</span>';
    }
    $quote = isset( $p['quote'] ) && '' !== (string) $p['quote'] ? '<div class="dc-quote">“' . esc_html( $p['quote'] ) . '”</div>' : '';
    return '      <a class="dir-card" href="' . esc_url( $home . '/people/' . $slug . '/' ) . "\">\n"
        . '        <div class="dc-thumb">' . $thumb . "</div>\n"
        . "        <div class=\"dc-body\">\n"
        . "          <div class=\"dc-by\">ProsperGenics</div>\n"
        . '          <div class="dc-title">' . esc_html( $name ) . "</div>\n"
        . '          <div class="dc-subtitle">' . esc_html( $p['role'] ) . '</div>' . $quote . "\n"
        . ( '' !== $tags ? '          <div class="dc-tags">' . $tags . "\n          </div>\n" : '' )
        . "          <div class=\"dc-footer\"><span class=\"dc-cta\">View profile →</span></div>\n"
        . "        </div>\n"
        . '      </a>';
}

/** Where the element that opens at $start ends (just after its closing tag), counting nested <div>s (pure). */
function prospergenics_2026_members_div_end( $html, $start ) {
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

/**
 * Rebuild the /members/ directory of a buffered plugin page from PROFILES (pure).
 *
 * @param array  $people slug => person (prospergenics_2026_members_people())
 * @param string $home   site URL without trailing slash
 * @param string $assets theme assets URL without trailing slash
 * @return string the page with the new cards and count, or $html unchanged when its shape is unexpected
 */
function prospergenics_2026_members_rewrite( $html, array $people, $keyword, $home, $assets ) {
    $html = (string) $html;
    if ( ! $people || 1 !== substr_count( $html, '<div class="dir-wrap">' ) || 1 !== substr_count( $html, '<header class="pg-header">' ) ) {
        return $html;
    }
    $start = strpos( $html, '<div class="dir-wrap">' );
    $end   = prospergenics_2026_members_div_end( $html, $start );
    if ( false === $end ) {
        return $html;
    }
    $found = prospergenics_2026_members_search( $people, $keyword );
    if ( $found ) {
        $cards = array();
        foreach ( $found as $slug => $p ) {
            $cards[] = prospergenics_2026_members_card( (string) $slug, $p, $home, $assets );
        }
        $body = "<div class=\"dir-grid\">\n" . implode( "\n", $cards ) . "\n    </div>";
    } else {
        $body = '<p class="no-results">No members found. <a href="' . esc_url( $home . '/members/' ) . '">Clear search</a></p>';
    }
    $html = substr_replace( $html, "<div class=\"dir-wrap\">\n    " . $body . "\n  </div>", $start, $end - $start );

    // The count under the heading: the plugin's, if any, is replaced; none when nobody matched.
    $count = $found ? '<p class="pg-count">' . esc_html( sprintf( 1 === count( $found ) ? '%d member' : '%d members', count( $found ) ) ) . '</p>' : '';
    $html  = preg_replace( '#\s*<p class="pg-count">.*?</p>#s', '', $html, 1 );
    $new   = preg_replace_callback( '#(<header class="pg-header">.*?)(\s*</header>)#s', function ( $m ) use ( $count ) { return $m[1] . $count . $m[2]; }, $html, 1 );
    return null === $new ? $html : $new;
}

/** The ItemList JSON-LD for /members/ (pure): the plugin's, if found once, replaced by the listed people. */
function prospergenics_2026_members_itemlist( $html, array $people, $home ) {
    $re = '#<script type="application/ld\+json">\{"@context":"https://schema\.org","@type":"ItemList","url":"' . preg_quote( $home, '#' ) . '/members/".*?</script>#s';
    if ( ! $people || 1 !== preg_match_all( $re, (string) $html ) ) {
        return $html;
    }
    $items = array();
    $i     = 0;
    foreach ( $people as $slug => $p ) {
        $items[] = array( '@type' => 'ListItem', 'position' => ++$i, 'url' => $home . '/people/' . $slug . '/', 'name' => (string) $p['name'] );
    }
    $json = wp_json_encode( array( '@context' => 'https://schema.org', '@type' => 'ItemList', 'url' => $home . '/members/', 'numberOfItems' => count( $items ), 'itemListElement' => $items ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG );
    $out  = preg_replace_callback( $re, function () use ( $json ) { return '<script type="application/ld+json">' . $json . '</script>'; }, (string) $html, 1 );
    return null === $out ? $html : $out;
}

/** Whether this request is the /members/ list itself. */
function prospergenics_2026_is_members_list_request() {
    $path = isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
    return '/members/' === trailingslashit( '/' . ltrim( $path, '/' ) );
}

// Cards and count, inside the jengo-listings takeover (inc/site-chrome.php), before the badges.
add_filter( 'prospergenics_2026_listings_page_html', function ( $html, $path ) {
    if ( '/members/' !== $path ) {
        return $html;
    }
    $keyword = isset( $_GET['keyword'] ) ? sanitize_text_field( wp_unslash( $_GET['keyword'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- a public search
    return prospergenics_2026_members_rewrite( $html, prospergenics_2026_members_people(), $keyword, untrailingslashit( home_url() ), get_template_directory_uri() . '/assets' );
}, 10, 2 );

// The plugin's ItemList is printed before <!DOCTYPE>, so it needs a buffer around the whole page.
add_action( 'init', function () {
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
    if ( is_admin() || wp_doing_ajax() || 'GET' !== $method || ! prospergenics_2026_is_members_list_request() ) {
        return;
    }
    ob_start( function ( $html ) {
        return prospergenics_2026_members_itemlist( $html, prospergenics_2026_members_people(), untrailingslashit( home_url() ) );
    } );
}, 1 );

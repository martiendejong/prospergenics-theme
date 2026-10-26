<?php
/**
 * SEO layer for the 2026 theme (docs/AUDIT-2026-10.md, C2).
 *
 * The production theme's SEO work (titles, sharing tags, Organization schema) never came across
 * to the 2026 redesign. This restores it, written for the redesign's own positioning (the About
 * page copy, task 4895), not the older "done-for-you agentic AI team" wording. The organisation
 * description itself is defined once, in inc/site-chrome/organisation.json (audit CON-13).
 *
 * Meta descriptions and Open Graph / Twitter tags are only printed when no SEO plugin is
 * active, so a plugin like Yoast stays in charge when it is installed and nothing is
 * printed twice. JSON-LD always prints (it uses its own @id values, as production did).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Hosts that may be indexed by search engines. Everything else (test.prospergenics.com, the
 * lessy.prospergenics.com sandbox, local copies) gets noindex, so a staging copy can never
 * compete with the real site in Google.
 */
function prospergenics_2026_is_production_host( $host ) {
    $hosts = apply_filters( 'prospergenics_2026_production_hosts', array( 'prospergenics.com', 'www.prospergenics.com' ) );
    return in_array( strtolower( (string) $host ), $hosts, true );
}

function prospergenics_2026_site_is_production() {
    return prospergenics_2026_is_production_host( parse_url( home_url(), PHP_URL_HOST ) );
}

add_filter( 'wp_robots', function ( $robots ) {
    if ( ! prospergenics_2026_site_is_production() ) {
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
        unset( $robots['max-image-preview'] );
    }
    return $robots;
} );

// Also for responses without a <head> (feeds, sitemaps, files served through WordPress).
add_action( 'send_headers', function () {
    if ( ! prospergenics_2026_site_is_production() && ! headers_sent() ) {
        header( 'X-Robots-Tag: noindex, nofollow' );
    }
} );

/** Whether an SEO plugin is handling meta descriptions and sharing tags. */
function prospergenics_2026_seo_plugin_active() {
    $active = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'AIOSEO_VERSION' ) || defined( 'SEOPRESS_VERSION' );
    return (bool) apply_filters( 'prospergenics_2026_seo_plugin_active', $active );
}

/**
 * Page $paged of a listing whose first page is $base: /articles/ -> /articles/page/2/ (or
 * ?paged=2 without pretty permalinks). For the canonical of paged archives (audit ARC-22): each
 * page is its own canonical, filters and sort orders are not.
 */
function prospergenics_2026_paged_url( $base, $paged ) {
    $paged = (int) $paged;
    if ( $paged < 2 ) {
        return $base;
    }
    if ( ! get_option( 'permalink_structure' ) ) {
        return add_query_arg( 'paged', $paged, $base );
    }
    global $wp_rewrite;
    $page_base = ( $wp_rewrite && ! empty( $wp_rewrite->pagination_base ) ) ? $wp_rewrite->pagination_base : 'page';
    return trailingslashit( $base ) . user_trailingslashit( $page_base . '/' . $paged, 'paged' );
}

/** The homepage's sharing text (the page itself hard-codes the same title). */
function prospergenics_2026_front_page_meta() {
    return array(
        'title'       => 'ProsperGenics — Create value',
        'description' => prospergenics_2026_org_description( 'short' ), // inc/site-chrome/organisation.json
        'image'       => get_template_directory_uri() . '/assets/og-image.jpg',
    );
}

/**
 * A plain-text summary for any singular page: its excerpt if it has one, otherwise the start
 * of its content, cut on a word boundary to at most $max characters.
 */
function prospergenics_2026_summarize( $text, $max = 155 ) {
    // a space where block tags end, so paragraphs and headings don't run together once tags go
    $text = preg_replace( '#</(p|h[1-6]|li|div|blockquote|td|th)>|<br\s*/?>#i', '$0 ', (string) $text );
    $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( strip_shortcodes( $text ) ) ) );
    if ( mb_strlen( $text ) <= $max ) {
        return $text;
    }
    $cut = mb_substr( $text, 0, $max - 1 );
    $space = mb_strrpos( $cut, ' ' );
    return rtrim( $space ? mb_substr( $cut, 0, $space ) : $cut, ' ,;:.-' ) . '…';
}

/**
 * Whether this request is the homepage itself (audit CON-7). is_front_page() alone is not enough:
 * jengo-listings renders /members/, /tools/coachos/, /courses/html/ ... on top of the front-page
 * query, so is_front_page() is true there too, and those pages got the homepage's sharing tags and
 * Organization schema. The homepage is only the site root, and never a listings page.
 */
function prospergenics_2026_is_homepage_request() {
    if ( ! is_front_page() ) {
        return false;
    }
    if ( function_exists( 'prospergenics_2026_is_listings_page' ) && prospergenics_2026_is_listings_page() ) {
        return false;
    }
    $home = '/' . trim( (string) parse_url( home_url( '/' ), PHP_URL_PATH ), '/' );
    $path = function_exists( 'prospergenics_2026_request_path' ) ? prospergenics_2026_request_path() : '/';
    return untrailingslashit( $home ) === untrailingslashit( '/' . ltrim( $path, '/' ) );
}

/**
 * Sharing tags for a jengo-listings page. The plugin prints its own <title>, description and
 * canonical but no Open Graph tags; until it prints og:title / og:description / og:image from its
 * item (plugin work), this gives the page its own URL instead of the homepage's, and scrapers fall
 * back to the page's <title> and description for the rest. Filter
 * 'prospergenics_2026_listings_share_tags' to false once the plugin prints its own.
 */
function prospergenics_2026_listings_meta( $path ) {
    return array(
        'url'   => home_url( '/' . ltrim( (string) $path, '/' ) ),
        'type'  => 'website',
        'image' => get_template_directory_uri() . '/assets/og-image.jpg',
    );
}

/** Collect the meta tags for the current request, or an empty array when there are none. */
function prospergenics_2026_current_meta() {
    if ( prospergenics_2026_is_homepage_request() ) {
        $meta = prospergenics_2026_front_page_meta();
        $meta['url']  = home_url( '/' );
        $meta['type'] = 'website';
        return $meta;
    }
    if ( function_exists( 'prospergenics_2026_is_listings_page' ) && prospergenics_2026_is_listings_page() ) {
        return apply_filters( 'prospergenics_2026_listings_share_tags', true ) ? prospergenics_2026_listings_meta( prospergenics_2026_request_path() ) : array();
    }
    // page-about.php carries its own hand-written description and sharing tags.
    if ( is_page( 'about' ) || ! is_singular() ) {
        return array();
    }
    $post = get_queried_object();
    $description = prospergenics_2026_summarize( has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content );
    if ( '' === $description ) {
        return array();
    }
    return array(
        'title'       => get_the_title( $post ) . ' — ProsperGenics',
        'description' => $description,
        'image'       => has_post_thumbnail( $post ) ? get_the_post_thumbnail_url( $post, 'large' ) : get_template_directory_uri() . '/assets/og-image.jpg',
        'url'         => get_permalink( $post ),
        'type'        => is_singular( 'post' ) ? 'article' : 'website',
    );
}

add_action( 'wp_head', function () {
    if ( prospergenics_2026_seo_plugin_active() ) {
        return;
    }
    $m = prospergenics_2026_current_meta();
    if ( ! $m ) {
        return;
    }
    echo "\n";
    foreach ( prospergenics_2026_meta_tags( $m ) as $t ) {
        printf( '<meta %s="%s" content="%s" />' . "\n", $t[0], esc_attr( $t[1] ), esc_attr( $t[2] ) );
    }
}, 2 );

/**
 * The meta tags for a set of values, as [attribute, name, content] rows; values that are not
 * set (a listings page has no title or description of ours) are left out.
 *
 * @return array<int,array{0:string,1:string,2:string}>
 */
function prospergenics_2026_meta_tags( array $m ) {
    $m    = array_merge( array( 'title' => '', 'description' => '', 'type' => 'website', 'url' => '', 'image' => '' ), $m );
    $rows = array(
        array( 'name', 'description', $m['description'] ),
        array( 'property', 'og:type', $m['type'] ),
        array( 'property', 'og:site_name', 'ProsperGenics' ),
        array( 'property', 'og:title', $m['title'] ),
        array( 'property', 'og:description', $m['description'] ),
        array( 'property', 'og:url', $m['url'] ),
        array( 'property', 'og:image', $m['image'] ),
        array( 'name', 'twitter:card', 'summary_large_image' ),
        array( 'name', 'twitter:title', $m['title'] ),
        array( 'name', 'twitter:description', $m['description'] ),
        array( 'name', 'twitter:image', $m['image'] ),
    );
    return array_values( array_filter( $rows, function ( $r ) { return '' !== (string) $r[2]; } ) );
}

/*
 * jengo-listings pages print their own canonical. WordPress adds a second one (to the homepage)
 * and a ?p= shortlink when the front page is a static page, because the plugin renders on top of
 * the front-page query: drop core's on those pages.
 */
add_action( 'wp_head', function () {
    if ( function_exists( 'prospergenics_2026_is_listings_page' ) && prospergenics_2026_is_listings_page() ) {
        remove_action( 'wp_head', 'rel_canonical' );
        remove_action( 'wp_head', 'wp_shortlink_wp_head', 10 );
    }
}, 1 );

/** Organization + WebSite structured data for the homepage. */
function prospergenics_2026_front_page_schema() {
    $home = home_url( '/' );
    return array(
        '@context' => 'https://schema.org',
        '@graph'   => array(
            array(
                '@type'        => 'Organization',
                '@id'          => $home . '#organization',
                'name'         => 'ProsperGenics',
                'url'          => $home,
                'logo'         => get_template_directory_uri() . '/assets/logo.png',
                'description'  => prospergenics_2026_org_description( 'long' ),
                'areaServed'   => array( 'Kenya', 'Netherlands' ),
                'contactPoint' => array(
                    '@type'       => 'ContactPoint',
                    'contactType' => 'customer service',
                    'telephone'   => '+254741619743',
                    'url'         => 'https://wa.me/254741619743',
                ),
            ),
            array(
                '@type'           => 'WebSite',
                '@id'             => $home . '#website',
                'name'            => 'ProsperGenics',
                'url'             => $home,
                'publisher'       => array( '@id' => $home . '#organization' ),
                'potentialAction' => array(
                    '@type'       => 'SearchAction',
                    'target'      => $home . '?s={search_term_string}',
                    'query-input' => 'required name=search_term_string',
                ),
            ),
        ),
    );
}

add_action( 'wp_head', function () {
    if ( prospergenics_2026_is_homepage_request() ) {
        echo "\n" . '<script type="application/ld+json" class="prospergenics-2026-schema">' . wp_json_encode( prospergenics_2026_front_page_schema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}, 24 );

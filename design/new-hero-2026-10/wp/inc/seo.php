<?php
/**
 * SEO layer for the 2026 theme (docs/AUDIT-2026-10.md, C2).
 *
 * The production theme's SEO work (titles, sharing tags, Organization schema) never came across
 * to the 2026 redesign. This restores it, written for the redesign's own positioning: a
 * community researching and practising how value is created (the About page copy, task 4895),
 * not the older "done-for-you agentic AI team" wording.
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

/** The homepage's sharing text (the page itself hard-codes the same title). */
function prospergenics_2026_front_page_meta() {
    return array(
        'title'       => 'ProsperGenics — Create value',
        'description' => 'A community in Kenya and the Netherlands that creates value together: courses and certification, AI tools, services and the people behind them.',
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

/** Collect the meta tags for the current request, or an empty array when there are none. */
function prospergenics_2026_current_meta() {
    if ( is_front_page() ) {
        $meta = prospergenics_2026_front_page_meta();
        $meta['url']  = home_url( '/' );
        $meta['type'] = 'website';
        return $meta;
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
    $tags = array(
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
    echo "\n";
    foreach ( $tags as $t ) {
        printf( '<meta %s="%s" content="%s" />' . "\n", $t[0], esc_attr( $t[1] ), esc_attr( $t[2] ) );
    }
}, 2 );

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
                'description'  => 'ProsperGenics is a community in Kenya and the Netherlands researching and practising value creation. We build, learn and measure through real projects: courses and certification, AI tools and services for organisations.',
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
    if ( is_front_page() ) {
        echo "\n" . '<script type="application/ld+json" class="prospergenics-2026-schema">' . wp_json_encode( prospergenics_2026_front_page_schema(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . '</script>' . "\n";
    }
}, 24 );

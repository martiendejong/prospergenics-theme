<?php
/**
 * Sitemap entries for the static pages that live outside WordPress: /people/<slug>/,
 * /experts/ and /community/ (uploaded by deploy-wp-theme-vimexx.py). Neither WordPress's own
 * sitemap nor an SEO plugin's knows about them, so search engines only found them by links.
 *
 *   - /people-sitemap.xml lists them (served here, no file needed),
 *   - WordPress's core sitemap (wp-sitemap.xml) includes them through a provider,
 *   - Yoast's sitemap index links /people-sitemap.xml when Yoast is active,
 *   - robots.txt names /people-sitemap.xml.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The static pages that exist on disk, newest first.
 *
 * @param string $root ABSPATH-style directory holding people/, experts/, community/.
 * @param string $home Site URL with trailing slash.
 * @return array<int,array{loc:string,lastmod:string}>
 */
function prospergenics_2026_static_page_urls( $root, $home ) {
    $root  = rtrim( $root, '/' ) . '/';
    $pages = array();
    foreach ( array( 'experts', 'community' ) as $list ) {
        if ( is_file( $root . $list . '/index.html' ) ) {
            $pages[ $list . '/' ] = filemtime( $root . $list . '/index.html' );
        }
    }
    foreach ( (array) glob( $root . 'people/*/index.html' ) as $file ) {
        $slug = basename( dirname( $file ) );
        if ( preg_match( '/^[a-z0-9-]+$/', $slug ) ) {
            $pages[ 'people/' . $slug . '/' ] = filemtime( $file );
        }
    }
    arsort( $pages );
    $urls = array();
    foreach ( $pages as $path => $mtime ) {
        $urls[] = array( 'loc' => $home . $path, 'lastmod' => gmdate( 'Y-m-d\TH:i:s\Z', $mtime ) );
    }
    return $urls;
}

/** A urlset document for the given entries. */
function prospergenics_2026_urlset_xml( $urls ) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ( $urls as $u ) {
        $xml .= "  <url><loc>" . htmlspecialchars( $u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</loc><lastmod>' . $u['lastmod'] . "</lastmod></url>\n";
    }
    return $xml . "</urlset>\n";
}

function prospergenics_2026_current_static_urls() {
    return prospergenics_2026_static_page_urls( ABSPATH, home_url( '/' ) );
}

// /people-sitemap.xml
add_action( 'init', function () {
    $path = isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
    if ( '/people-sitemap.xml' !== $path ) {
        return;
    }
    header( 'Content-Type: application/xml; charset=utf-8' );
    echo prospergenics_2026_urlset_xml( prospergenics_2026_current_static_urls() );
    exit;
} );

// WordPress core sitemap (wp-sitemap.xml): one more provider.
add_action( 'init', function () {
    if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
        return;
    }
    if ( ! class_exists( 'Prospergenics_2026_Static_Pages_Sitemap' ) ) {
        class Prospergenics_2026_Static_Pages_Sitemap extends WP_Sitemaps_Provider {
            public function __construct() {
                $this->name        = 'pgpeople';
                $this->object_type = 'pgpeople';
            }
            public function get_url_list( $page_num, $object_subtype = '' ) {
                return 1 === (int) $page_num ? prospergenics_2026_current_static_urls() : array();
            }
            public function get_max_num_pages( $object_subtype = '' ) {
                return 1;
            }
        }
    }
    wp_register_sitemap_provider( 'pgpeople', new Prospergenics_2026_Static_Pages_Sitemap() );
} );

// The core users sitemap lists author archives, which inc/security.php hides from visitors
// (they 404, and their URLs expose admin usernames): leave it out.
add_filter( 'wp_sitemaps_add_provider', function ( $provider, $name ) {
    return 'users' === $name ? false : $provider;
}, 10, 2 );

// Core ignores an unknown sitemap name (e.g. the removed users one, or any made-up
// wp-sitemap-foo-1.xml) and renders the blog with 200. Core's renderer runs at priority 10 and
// exits when it serves a sitemap, so reaching this hook with a sitemap query means it didn't.
add_action( 'template_redirect', function () {
    if ( '' !== (string) get_query_var( 'sitemap' ) ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }
}, 11 );

// Yoast replaces the core sitemap with its own index; link ours from it.
add_filter( 'wpseo_sitemap_index', function ( $index ) {
    $urls    = prospergenics_2026_current_static_urls();
    $lastmod = $urls ? $urls[0]['lastmod'] : gmdate( 'Y-m-d\TH:i:s\Z' );
    return $index . '<sitemap><loc>' . esc_url( home_url( '/people-sitemap.xml' ) ) . '</loc><lastmod>' . $lastmod . "</lastmod></sitemap>\n";
} );

add_filter( 'robots_txt', function ( $output ) {
    return rtrim( $output ) . "\nSitemap: " . home_url( '/people-sitemap.xml' ) . "\n";
} );

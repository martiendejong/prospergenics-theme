<?php
/**
 * Sitemap entries WordPress doesn't know about by itself:
 *
 *   - the static pages that live outside WordPress: /people/<slug>/, /experts/ and /community/
 *     (uploaded by deploy-wp-theme-vimexx.py). /people/<slug>/ is the person URL for now.
 *   - the listing pages of the platform tracks, /articles/ and /courses/ (core lists posts, not
 *     post type archives); the WordPress page with the slug "courses", which the archive shadows,
 *     is left out of the page sitemap so /courses/ appears once (audit ARC-22),
 *   - the /in/ member profiles that are public and filled in enough to be indexed (ARC-22).
 *
 * With WordPress's core sitemap (wp-sitemap.xml) each list is one provider. When an SEO plugin
 * replaces the core sitemap (Yoast), /people-sitemap.xml serves the static pages instead and is
 * linked from Yoast's index and robots.txt; with the core sitemap on it 301s to the provider's
 * sitemap, so no URL is listed in two sitemaps.
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

/** A urlset document for the given entries (lastmod optional). */
function prospergenics_2026_urlset_xml( $urls ) {
    $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
    foreach ( $urls as $u ) {
        $lastmod = empty( $u['lastmod'] ) ? '' : '<lastmod>' . $u['lastmod'] . '</lastmod>';
        $xml    .= "  <url><loc>" . htmlspecialchars( $u['loc'], ENT_XML1 | ENT_QUOTES, 'UTF-8' ) . '</loc>' . $lastmod . "</url>\n";
    }
    return $xml . "</urlset>\n";
}

/** Whether WordPress's own sitemap (wp-sitemap.xml) is on, i.e. no SEO plugin replaced it. */
function prospergenics_2026_core_sitemaps_enabled() {
    return function_exists( 'wp_sitemaps_get_server' ) && wp_sitemaps_get_server()->sitemaps_enabled();
}

/**
 * Post types whose archive is a theme listing: /articles/ (pg_article), /courses/ (pg_course).
 *
 * @return array<string,string> post type => archive path ('articles', 'courses')
 */
function prospergenics_2026_listing_archives() {
    $out = array();
    foreach ( array( 'pg_article', 'pg_course' ) as $type ) {
        $obj = get_post_type_object( $type );
        if ( ! $obj || ! $obj->public || ! $obj->has_archive ) {
            continue;
        }
        $path = true === $obj->has_archive ? ( ! empty( $obj->rewrite['slug'] ) ? $obj->rewrite['slug'] : $type ) : (string) $obj->has_archive;
        $out[ $type ] = trim( $path, '/' );
    }
    return $out;
}

/**
 * The listing pages, with the date of their newest change, when they have anything to list.
 *
 * @return array<int,array{loc:string,lastmod:string}>
 */
function prospergenics_2026_listing_urls() {
    $urls = array();
    foreach ( array_keys( prospergenics_2026_listing_archives() ) as $type ) {
        $latest = get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'posts_per_page' => 1, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true ) );
        $link   = get_post_type_archive_link( $type );
        if ( $latest && $link ) {
            $urls[] = array( 'loc' => $link, 'lastmod' => get_post_modified_time( 'Y-m-d\TH:i:s\Z', true, $latest[0] ) );
        }
    }
    return $urls;
}

/** IDs of WordPress pages whose URL is a listing archive (the "courses" page): they never render. */
function prospergenics_2026_shadowed_page_ids() {
    $ids = array();
    foreach ( prospergenics_2026_listing_archives() as $path ) {
        $page = get_page_by_path( $path, OBJECT, 'page' );
        if ( $page ) {
            $ids[] = (int) $page->ID;
        }
    }
    return $ids;
}

function prospergenics_2026_current_static_urls() {
    return prospergenics_2026_static_page_urls( ABSPATH, home_url( '/' ) );
}

// /people-sitemap.xml (for SEO plugins that replace the core sitemap; otherwise one list only)
add_action( 'init', function () {
    $path = isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
    if ( '/people-sitemap.xml' !== $path ) {
        return;
    }
    if ( prospergenics_2026_core_sitemaps_enabled() ) {
        wp_safe_redirect( home_url( '/wp-sitemap-pgpeople-1.xml' ), 301 );
        exit;
    }
    header( 'Content-Type: application/xml; charset=utf-8' );
    echo prospergenics_2026_urlset_xml( prospergenics_2026_current_static_urls() );
    exit;
} );

// WordPress core sitemap (wp-sitemap.xml): one provider per list, each a single page.
add_action( 'init', function () {
    if ( ! class_exists( 'WP_Sitemaps_Provider' ) || ! function_exists( 'wp_register_sitemap_provider' ) ) {
        return;
    }
    if ( ! class_exists( 'Prospergenics_2026_Url_List_Sitemap' ) ) {
        class Prospergenics_2026_Url_List_Sitemap extends WP_Sitemaps_Provider {
            private $list;
            public function __construct( $name, callable $list ) {
                $this->name        = $name;
                $this->object_type = $name;
                $this->list        = $list;
            }
            public function get_url_list( $page_num, $object_subtype = '' ) {
                return 1 === (int) $page_num ? call_user_func( $this->list ) : array();
            }
            public function get_max_num_pages( $object_subtype = '' ) {
                return call_user_func( $this->list ) ? 1 : 0; // an empty list is left out of the index
            }
        }
    }
    wp_register_sitemap_provider( 'pgpeople', new Prospergenics_2026_Url_List_Sitemap( 'pgpeople', 'prospergenics_2026_current_static_urls' ) );
    wp_register_sitemap_provider( 'pglistings', new Prospergenics_2026_Url_List_Sitemap( 'pglistings', 'prospergenics_2026_listing_urls' ) );
    if ( function_exists( 'prospergenics_profiles_sitemap_urls' ) ) {
        wp_register_sitemap_provider( 'pgprofiles', new Prospergenics_2026_Url_List_Sitemap( 'pgprofiles', 'prospergenics_profiles_sitemap_urls' ) );
    }
} );

// The page that /courses/ shadows is not a page of its own: the listing provider has the URL.
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
    if ( 'page' === $post_type ) {
        $ids = prospergenics_2026_shadowed_page_ids();
        if ( $ids ) {
            $args['post__not_in'] = array_merge( isset( $args['post__not_in'] ) ? (array) $args['post__not_in'] : array(), $ids );
        }
    }
    return $args;
}, 10, 2 );

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

// robots.txt: core already names wp-sitemap.xml (which includes the static pages); name
// /people-sitemap.xml only when an SEO plugin's sitemap took over.
add_filter( 'robots_txt', function ( $output ) {
    if ( prospergenics_2026_core_sitemaps_enabled() ) {
        return $output;
    }
    return rtrim( $output ) . "\nSitemap: " . home_url( '/people-sitemap.xml' ) . "\n";
} );

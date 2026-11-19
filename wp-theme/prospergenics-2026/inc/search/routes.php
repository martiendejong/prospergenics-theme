<?php
/**
 * Search track (card #6393): the /find/ page, its head tags and assets.
 *
 *   /find/?q=pricing                 people, projects, courses and articles for a query
 *   &type=people|projects|courses|articles   one tab (default: all, grouped)
 *   &skill=Pricing &kind=service &verified=1 filters; &pg=2 paging within a tab
 *   &problem=…                       "describe your problem" (AGI intake seam)
 *
 * Why /find/: WordPress already answers /search/<words>/ and ?s= with its own post search (the
 * search permalink base is "search"), so /search/experts/ would be read as a search for the word
 * "experts". /find/ is free, short and says what it does. WordPress' ?s= search is untouched.
 *
 * The page is rendered on the server (works without JavaScript) and is the same for every
 * visitor apart from the site header's account links: one canonical per query
 * (/find/?q=<normalised query>), noindex for empty, odd, filtered, paged or result-less pages,
 * and a schema.org ItemList of the results shown.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_SEARCH_REWRITE_VERSION = '1';

add_action( 'init', function () {
    add_rewrite_rule( '^find/?$', 'index.php?pg_search=1', 'top' );
    if ( get_option( '_pg_search_rewrite_version' ) !== PROSPERGENICS_SEARCH_REWRITE_VERSION ) {
        flush_rewrite_rules( false );
        update_option( '_pg_search_rewrite_version', PROSPERGENICS_SEARCH_REWRITE_VERSION );
    }
} );
add_action( 'after_switch_theme', function () {
    delete_option( '_pg_search_rewrite_version' );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'pg_search';
    return $vars;
} );

/** The search page URL, with arguments. */
function prospergenics_search_url( $args = array() ) {
    $url = home_url( '/find/' );
    if ( isset( $args['pg'] ) && (int) $args['pg'] < 2 ) {
        unset( $args['pg'] );
    }
    $args = array_filter( $args, function ( $v ) { return '' !== $v && null !== $v && false !== $v && 'all' !== $v; } );
    $args = array_map( function ( $v ) { return true === $v ? 1 : $v; }, $args );
    return $args ? add_query_arg( array_map( 'rawurlencode', $args ), $url ) : $url;
}

/** Whether this request is the search page. */
function prospergenics_search_is_page() {
    return '1' === (string) get_query_var( 'pg_search' );
}

/** The current search: arguments and result, computed once in template_redirect. */
function prospergenics_search_state( $set = null ) {
    static $state = null;
    if ( null !== $set ) {
        $state = $set;
    }
    return $state;
}

// Don't run the blog query behind the page.
add_filter( 'posts_pre_query', function ( $posts, $query ) {
    if ( $query->is_main_query() && '1' === (string) $query->get( 'pg_search' ) ) {
        $query->found_posts   = 0;
        $query->max_num_pages = 0;
        return array();
    }
    return $posts;
}, 10, 2 );

add_action( 'template_redirect', function () {
    if ( ! prospergenics_search_is_page() ) {
        return;
    }
    global $wp_query;
    $wp_query->is_home = false;
    $wp_query->is_404  = false;
    status_header( 200 );
    $args   = prospergenics_search_args( wp_unslash( $_GET ) ); // phpcs:ignore WordPress.Security.NonceVerification -- a read-only GET search
    $result = prospergenics_search( $args );
    $total  = count( $result['people'] ) + count( $result['projects'] ) + count( $result['courses'] ) + count( $result['articles'] ) + count( $result['listings'] );
    prospergenics_search_state( array(
        'args'      => $args,
        'result'    => $result,
        'total'     => $total,
        'canonical' => prospergenics_search_canonical( home_url( '/find/' ), $args['q'] ),
        'indexable' => prospergenics_search_indexable( array(
            'q'        => $args['q'],
            'terms'    => $args['terms'],
            'total'    => $total,
            'filtered' => 'all' !== $args['type'] || '' !== $args['skill'] || '' !== $args['kind'] || $args['verified'],
            'paged'    => $args['page'],
            'problem'  => '' !== $args['problem'],
        ) ),
    ) );
    if ( ! headers_sent() ) {
        // how long the search took on the server (developer tools, Network → Timing)
        header( sprintf( 'Server-Timing: pgsearch;dur=%s;desc="%s, %d queries"', $result['cached'] ? '0' : (string) ( isset( $result['ms'] ) ? $result['ms'] : 0 ), $result['cached'] ? 'cached' : 'computed', $result['cached'] ? 0 : (int) ( isset( $result['queries'] ) ? $result['queries'] : 0 ) ) );
    }
}, 5 );

add_filter( 'template_include', function ( $template ) {
    if ( ! prospergenics_search_is_page() ) {
        return $template;
    }
    $found = locate_template( 'pg-search.php' );
    return $found ? $found : $template;
}, 20 );

add_filter( 'body_class', function ( $classes ) {
    if ( prospergenics_search_is_page() ) {
        $classes[] = 'pg-search-page';
    }
    return $classes;
} );

/** The page title for a query ('' = the search page itself). */
function prospergenics_search_title( $q ) {
    return '' === $q ? 'Find people by skill, project or problem' : sprintf( 'Who knows ‘%s’? People, projects and courses', $q );
}

add_filter( 'pre_get_document_title', function ( $title ) {
    $state = prospergenics_search_state();
    return prospergenics_search_is_page() && $state ? prospergenics_search_title( $state['args']['q'] ) . ' – ProsperGenics' : $title;
}, 20 );

// Only plain queries with results are indexed; everything else is noindex, follow.
add_filter( 'wp_robots', function ( $robots ) {
    $state = prospergenics_search_state();
    if ( ! prospergenics_search_is_page() || ! $state || $state['indexable'] ) {
        return $robots;
    }
    $robots['noindex'] = true;
    unset( $robots['max-image-preview'] );
    return $robots;
}, 20 );

/** The ItemList items for the results this page shows, in their order. */
function prospergenics_search_page_items( array $state ) {
    $args  = $state['args'];
    $r     = $state['result'];
    $items = array();
    $map   = array(
        'people'   => function ( $x ) { return array( 'url' => $x['url'], 'name' => $x['name'] ); },
        'projects' => function ( $x ) { return array( 'url' => $x['url'], 'name' => $x['title'] ); },
        'courses'  => function ( $x ) { return array( 'url' => $x['url'], 'name' => $x['title'] ); },
        'articles' => function ( $x ) { return array( 'url' => $x['url'], 'name' => $x['title'] ); },
    );
    foreach ( $map as $type => $fn ) {
        if ( 'all' === $args['type'] ) {
            $items = array_merge( $items, array_map( $fn, array_slice( $r[ $type ], 0, 'people' === $type ? PROSPERGENICS_SEARCH_PER_PAGE / 2 : PROSPERGENICS_SEARCH_PER_GROUP ) ) );
        } elseif ( $type === $args['type'] ) {
            $items = array_map( $fn, prospergenics_search_page( $r[ $type ], $args['page'] ) );
        }
    }
    return $items;
}

add_action( 'wp_head', function () {
    $state = prospergenics_search_state();
    if ( ! prospergenics_search_is_page() || ! $state ) {
        return;
    }
    $q    = $state['args']['q'];
    $r    = $state['result'];
    $desc = '' === $q
        ? 'Search ProsperGenics members by skill, project or problem. Every result says why it matches, with links to the evidence: confirmed project work, courses taught, certificates and articles.'
        : sprintf( '%d %s with evidence for ‘%s’ on ProsperGenics: confirmed project work, courses, certificates and articles, each linked.', count( $r['people'] ), 1 === count( $r['people'] ) ? 'member' : 'members', $q );
    echo '<link rel="canonical" href="' . esc_url( $state['canonical'] ) . "\" />\n";
    if ( ! function_exists( 'prospergenics_2026_seo_plugin_active' ) || ! prospergenics_2026_seo_plugin_active() ) {
        $tags = function_exists( 'prospergenics_2026_meta_tags' ) ? prospergenics_2026_meta_tags( array(
            'title'       => prospergenics_search_title( $q ) . ' – ProsperGenics',
            'description' => $desc,
            'url'         => $state['canonical'],
            'image'       => get_template_directory_uri() . '/assets/og-image.jpg',
        ) ) : array( array( 'name', 'description', $desc ) );
        foreach ( $tags as $t ) {
            printf( '<meta %s="%s" content="%s" />' . "\n", esc_attr( $t[0] ), esc_attr( $t[1] ), esc_attr( $t[2] ) );
        }
    }
    $items = prospergenics_search_page_items( $state );
    if ( $items ) {
        $list = prospergenics_search_itemlist( $items, $state['canonical'], prospergenics_search_title( $q ) );
        echo '<script type="application/ld+json" class="pg-search-schema">' . wp_json_encode( $list, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
    }
}, 3 );

add_action( 'wp_enqueue_scripts', function () {
    if ( ! prospergenics_search_is_page() ) {
        return;
    }
    $css = '/assets/platform/search.css';
    $dir = get_template_directory();
    wp_enqueue_style( 'pg-search', get_template_directory_uri() . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
} );

// The /members/ directory (jengo-listings) links here, next to its own name search.
add_filter( 'prospergenics_2026_listings_search_links', function ( $links ) {
    $links['/members/'] = array(
        'text'  => 'Looking for someone who has done it before?',
        'label' => 'Find people by skill, project or problem',
        'path'  => '/find/',
    );
    return $links;
} );

<?php
/**
 * Projects track: search engines and structured data.
 *
 * - robots: a project page is indexable only when prospergenics_projects_is_indexable() says so
 *   (published, not hidden, creator not hidden or suspended, at least one contribution a visitor
 *   may see); the edit view, a filtered /projects/ and an empty /projects/ are noindex.
 * - canonical: WordPress prints the project's own; /projects/ gets the unfiltered listing (paged).
 * - sitemap: the core "posts" provider lists pg_project; it is narrowed to the indexable ones, and
 *   /projects/ itself is listed (provider "pgprojects") while it has an indexable project.
 * - schema.org: a project page is a CreativeWork (an employer an Organization) whose contributors
 *   are Person nodes with the @id of their /in/ page; a profile lists the member's public portfolio
 *   as CreativeWorks linked to that same Person. Only what a logged-out visitor may see.
 */

defined( 'ABSPATH' ) || exit;

/** IDs of the indexable projects (sitemap). */
function prospergenics_projects_indexable_ids() {
    return array_values( array_filter( prospergenics_projects_published_ids(), 'prospergenics_projects_is_indexable' ) );
}

/** Canonical of /projects/: without filters, page number kept. */
function prospergenics_projects_archive_canonical() {
    $base  = get_post_type_archive_link( 'pg_project' );
    $base  = $base ? $base : home_url( '/projects/' );
    $paged = max( 1, (int) get_query_var( 'paged' ) );
    return function_exists( 'prospergenics_2026_paged_url' ) ? prospergenics_2026_paged_url( $base, $paged ) : $base;
}

add_filter( 'wp_robots', function ( $robots ) {
    if ( is_singular( 'pg_project' ) ) {
        $post = get_queried_object();
        // phpcs:ignore WordPress.Security.NonceVerification -- read-only view switch
        if ( isset( $_GET['edit'] ) || ! $post || ! prospergenics_projects_is_indexable( $post->ID ) ) {
            $robots['noindex']  = true;
            $robots['nofollow'] = true;
        }
    } elseif ( is_post_type_archive( 'pg_project' ) ) {
        $f = prospergenics_projects_current_filters();
        if ( '' !== $f['kind'] || '' !== $f['skill'] || ! prospergenics_projects_indexable_ids() ) {
            $robots['noindex'] = true; // a subset of the canonical list, or nothing to show
        }
    }
    return $robots;
}, 20 );

/** The schema.org node of a project, with the contributors a visitor may see. */
function prospergenics_projects_schema( WP_Post $post ) {
    $f      = prospergenics_projects_fields( $post->ID );
    $node   = array(
        '@context' => 'https://schema.org',
        '@type'    => 'employer' === $f['kind'] ? 'Organization' : 'CreativeWork',
        '@id'      => get_permalink( $post ) . '#project',
        'name'     => wp_strip_all_tags( get_the_title( $post ) ),
        'url'      => get_permalink( $post ),
    );
    $desc = trim( wp_strip_all_tags( $post->post_content ) );
    if ( '' !== $desc ) {
        $node['description'] = wp_trim_words( $desc, 60 );
    }
    if ( '' !== $f['url'] ) {
        $node['sameAs'] = $f['url'];
    }
    if ( $f['skills'] ) {
        $node['keywords'] = implode( ', ', $f['skills'] );
    }
    if ( 'employer' !== $f['kind'] && '' !== $f['period'] ) {
        $node['temporalCoverage'] = $f['period'];
    }
    $people = array();
    foreach ( prospergenics_projects_contributors( $post->ID, 0 ) as $c ) {
        if ( 'disputed' === $c['status'] ) {
            continue;
        }
        $u = get_userdata( $c['user_id'] );
        if ( ! $u ) {
            continue;
        }
        $url      = function_exists( 'prospergenics_profiles_url' ) ? prospergenics_profiles_url( $u->ID ) : '';
        $people[] = array(
            '@type'    => 'Person',
            '@id'      => $url . '#person',
            'name'     => $u->display_name,
            'url'      => $url,
            'jobTitle' => $c['role'],
        );
    }
    if ( $people ) {
        $node['employer' === $f['kind'] ? 'employee' : 'contributor'] = $people;
    }
    return $node;
}

add_action( 'wp_head', function () {
    if ( is_post_type_archive( 'pg_project' ) && function_exists( 'prospergenics_2026_seo_plugin_active' ) && ! prospergenics_2026_seo_plugin_active() ) {
        echo '<meta name="description" content="' . esc_attr( 'Projects, products, services and employers of ProsperGenics members, with each member’s role, contribution and evidence.' ) . '" />' . "\n";
        echo '<link rel="canonical" href="' . esc_url( prospergenics_projects_archive_canonical() ) . '" />' . "\n";
        return;
    }
    if ( is_singular( 'pg_project' ) && ! is_404() ) {
        $post = get_queried_object();
        if ( $post && prospergenics_projects_is_indexable( $post->ID ) ) {
            echo '<script type="application/ld+json" class="prospergenics-projects-schema">' . wp_json_encode( prospergenics_projects_schema( $post ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
        }
        return;
    }
    // /in/<slug>/: the member's public portfolio, linked to the Person of the profile
    if ( function_exists( 'prospergenics_profiles_route' ) && 'profile' === prospergenics_profiles_route() && function_exists( 'prospergenics_profiles_current_owner' ) ) {
        $owner = prospergenics_profiles_current_owner();
        if ( ! $owner || ! function_exists( 'prospergenics_profiles_is_indexable' ) || ! prospergenics_profiles_is_indexable( $owner->ID ) ) {
            return;
        }
        $works = array();
        foreach ( prospergenics_projects_for_user( $owner->ID, 0 ) as $e ) {
            if ( 'disputed' === $e['status'] || ! prospergenics_projects_is_indexable( $e['project']->ID ) ) {
                continue;
            }
            $url     = get_permalink( $e['project'] );
            $works[] = array(
                '@type' => 'employer' === prospergenics_projects_fields( $e['project']->ID )['kind'] ? 'Organization' : 'CreativeWork',
                '@id'   => $url . '#project',
                'name'  => wp_strip_all_tags( get_the_title( $e['project'] ) ),
                'url'   => $url,
            );
        }
        if ( ! $works ) {
            return;
        }
        $purl   = prospergenics_profiles_url( $owner->ID );
        $person = array( '@type' => 'Person', '@id' => $purl . '#person', 'name' => $owner->display_name, 'url' => $purl );
        $graph  = array( $person );
        foreach ( $works as $w ) {
            $w[ 'Organization' === $w['@type'] ? 'employee' : 'contributor' ] = array( '@id' => $purl . '#person' );
            $graph[] = $w;
        }
        echo '<script type="application/ld+json" class="prospergenics-projects-schema">' . wp_json_encode( array( '@context' => 'https://schema.org', '@graph' => $graph ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG ) . "</script>\n";
    }
}, 3 );

/* ───────────────────────── sitemap ───────────────────────── */

// The core posts provider (wp-sitemap-posts-pg_project-1.xml) lists only indexable projects.
add_filter( 'wp_sitemaps_posts_query_args', function ( $args, $post_type ) {
    if ( 'pg_project' === $post_type ) {
        $ids              = prospergenics_projects_indexable_ids();
        $args['post__in'] = $ids ? $ids : array( 0 );
    }
    return $args;
}, 10, 2 );

/** The /projects/ listing for the sitemap while it has an indexable project. @return array<int,array{loc:string}> */
function prospergenics_projects_sitemap_listing() {
    $link = get_post_type_archive_link( 'pg_project' );
    return ( $link && prospergenics_projects_indexable_ids() ) ? array( array( 'loc' => $link ) ) : array();
}

// After inc/sitemap.php (init 10) defined its list provider class.
add_action( 'init', function () {
    if ( function_exists( 'wp_register_sitemap_provider' ) && class_exists( 'Prospergenics_2026_Url_List_Sitemap' ) ) {
        wp_register_sitemap_provider( 'pgprojects', new Prospergenics_2026_Url_List_Sitemap( 'pgprojects', 'prospergenics_projects_sitemap_listing' ) );
    }
}, 11 );

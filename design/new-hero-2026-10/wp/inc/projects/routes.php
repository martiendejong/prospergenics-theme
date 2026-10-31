<?php
/**
 * Projects track: /projects/ (filters by kind and skill) and /projects/<slug>/, their assets and
 * titles. Templates: archive-pg_project.php and single-pg_project.php in the theme root.
 */

defined( 'ABSPATH' ) || exit;

/** The /projects/ filters of the current request: kind and skill (a skill key). */
function prospergenics_projects_current_filters() {
    // phpcs:disable WordPress.Security.NonceVerification -- read-only listing filters
    $kind  = isset( $_GET['kind'] ) ? sanitize_key( wp_unslash( $_GET['kind'] ) ) : '';
    $skill = isset( $_GET['skill'] ) && is_string( $_GET['skill'] ) ? prospergenics_projects_skill_key( sanitize_text_field( wp_unslash( $_GET['skill'] ) ) ) : '';
    // phpcs:enable
    return array(
        'kind'  => isset( prospergenics_projects_kinds()[ $kind ] ) ? $kind : '',
        'skill' => $skill,
    );
}

/** Whether this request is one of the track's pages. */
function prospergenics_projects_is_project_page() {
    return is_post_type_archive( 'pg_project' ) || is_singular( 'pg_project' );
}

/**
 * /projects/ lists only the projects this viewer may see (prospergenics_projects_can_view()), 12 a
 * page, newest first, narrowed by ?kind= and ?skill=.
 */
add_action( 'pre_get_posts', function ( $q ) {
    if ( is_admin() || ! $q->is_main_query() || ! $q->is_post_type_archive( 'pg_project' ) ) {
        return;
    }
    $f   = prospergenics_projects_current_filters();
    $ids = prospergenics_projects_visible_ids( get_current_user_id(), $f['kind'], $f['skill'] );
    $q->set( 'post__in', $ids ? $ids : array( 0 ) );
    $q->set( 'orderby', 'post__in' );
    $q->set( 'posts_per_page', 12 );
    $q->set( 'author', '' );
} );

/** Single projects: a project this viewer may not see does not exist for them (404). */
add_action( 'template_redirect', function () {
    if ( ! is_singular( 'pg_project' ) ) {
        return;
    }
    $post = get_queried_object();
    if ( is_embed() || ! prospergenics_projects_can_view( $post, get_current_user_id() ) ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
        return;
    }
    // contributors depend on who is looking: never cache the page
    nocache_headers();
}, 4 );

// The archive's content depends on the viewer too.
add_action( 'template_redirect', function () {
    if ( is_post_type_archive( 'pg_project' ) ) {
        nocache_headers();
    }
}, 4 );

add_filter( 'document_title_parts', function ( $parts ) {
    if ( is_post_type_archive( 'pg_project' ) ) {
        $parts['title'] = 'Projects';
    }
    return $parts;
} );

add_filter( 'body_class', function ( $classes ) {
    if ( prospergenics_projects_is_project_page() && ! is_404() ) {
        $classes[] = 'pg-projects';
    }
    return $classes;
} );

/** The track's CSS and the small confirm script: on project pages and on member profiles. */
add_action( 'wp_enqueue_scripts', function () {
    $profile = function_exists( 'prospergenics_profiles_route' ) && 'profile' === prospergenics_profiles_route();
    if ( ( ! prospergenics_projects_is_project_page() && ! $profile ) || is_404() ) {
        return;
    }
    $dir = get_template_directory();
    $uri = get_template_directory_uri();
    $css = '/assets/platform/projects.css';
    $js  = '/assets/platform/projects.js';
    // project pages borrow the profile track's buttons, fields and cards
    if ( ! $profile && file_exists( $dir . '/assets/platform/profiles.css' ) ) {
        wp_enqueue_style( 'pg-profiles', $uri . '/assets/platform/profiles.css', array(), (string) filemtime( $dir . '/assets/platform/profiles.css' ) );
    }
    wp_enqueue_style( 'pg-projects', $uri . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
    wp_enqueue_script( 'pg-projects', $uri . $js, array(), file_exists( $dir . $js ) ? (string) filemtime( $dir . $js ) : null, true );
} );

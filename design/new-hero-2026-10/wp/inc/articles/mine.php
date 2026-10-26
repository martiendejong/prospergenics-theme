<?php
/**
 * Articles: "My articles" for authors at /articles/mine/ (card #6368, and #6369's "author sees
 * total claps on their dashboard").
 *
 * A front-end page rather than only the wp-admin list, because the platform's members are not
 * WordPress administrators: they get one page in the site's own look with their drafts, pending,
 * scheduled and published articles (filterable), reading time and claps per article and in
 * total, and the actions the card asks for. Writing, autosave (every 30 s), publishing and
 * scheduling (date/time picker, published by WP-Cron) stay in the block editor, which the page
 * links to. The wp-admin list (Articles > All articles) shows the same claps column.
 *
 * Members who cannot write yet get the "Apply to write" form here instead (audit ARC-8,
 * inc/profiles/applications.php).
 *
 * Actions (POST to admin-post.php, each with its own nonce and capability check):
 *   pg_articles_unpublish  published or scheduled -> draft     needs edit_post
 *   pg_articles_publish    draft/pending/scheduled -> live now  needs publish_post
 */

defined( 'ABSPATH' ) || exit;

add_action( 'init', function () {
    add_rewrite_tag( '%pg_articles_mine%', '1' );
    add_rewrite_rule( '^articles/mine/?$', 'index.php?pg_articles_mine=1', 'top' );
}, 6 );

function prospergenics_articles_is_mine_page() {
    return '1' === (string) get_query_var( 'pg_articles_mine' );
}

function prospergenics_articles_mine_url( $args = array() ) {
    return add_query_arg( $args, home_url( '/articles/mine/' ) );
}

/**
 * Status filters on the page: key => [label, post statuses]. Editors (who may edit others'
 * articles) also get "Waiting for review": everyone's pending articles (audit ARC-9).
 */
function prospergenics_articles_mine_filters() {
    $filters = array(
        'all'       => array( 'All', array( 'draft', 'pending', 'future', 'publish' ) ),
        'draft'     => array( 'Drafts', array( 'draft', 'pending' ) ),
        'future'    => array( 'Scheduled', array( 'future' ) ),
        'publish'   => array( 'Published', array( 'publish' ) ),
    );
    if ( current_user_can( 'edit_others_pg_articles' ) ) {
        $filters['review'] = array( 'Waiting for review', array( 'pending' ) );
    }
    return $filters;
}

/** Whether a filter lists everyone's articles (the review queue) rather than the reader's own. */
function prospergenics_articles_mine_filter_is_queue( $filter ) {
    return 'review' === $filter;
}

/** The selected filter key from the request. */
function prospergenics_articles_mine_filter() {
    $f = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
    return array_key_exists( $f, prospergenics_articles_mine_filters() ) ? $f : 'all';
}

/** Human status label. */
function prospergenics_articles_status_label( $status ) {
    $labels = array(
        'draft'   => 'Draft',
        'pending' => 'Pending review',
        'future'  => 'Scheduled',
        'publish' => 'Published',
    );
    return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
}

add_action( 'template_redirect', function () {
    if ( ! prospergenics_articles_is_mine_page() ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( prospergenics_articles_mine_url() ) );
        exit;
    }
    global $wp_query;
    $wp_query->is_home = false;
    $wp_query->is_404  = false;
    status_header( 200 );
    nocache_headers();
    add_filter( 'pre_get_document_title', function () { return 'My articles – ProsperGenics'; } );
    add_filter( 'wp_robots', 'wp_robots_no_robots' );
    include __DIR__ . '/views/mine.php';
    exit;
}, 5 );

/** Validate an action request; returns the post or dies. */
function prospergenics_articles_action_post( $action, $cap ) {
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    check_admin_referer( $action . '_' . $post_id );
    $post = get_post( $post_id );
    if ( ! $post || 'pg_article' !== $post->post_type || ! current_user_can( $cap, $post_id ) ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to do that.' ), 403 );
    }
    return $post;
}

add_action( 'admin_post_pg_articles_unpublish', function () {
    $post = prospergenics_articles_action_post( 'pg_articles_unpublish', 'edit_post' );
    $done = 0;
    if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
        $done = (int) ! is_wp_error( wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ), true ) );
    }
    wp_safe_redirect( prospergenics_articles_mine_url( array( 'status' => 'draft', 'done' => $done ? 'unpublished' : 'failed' ) ) );
    exit;
} );

add_action( 'admin_post_pg_articles_publish', function () {
    $post = prospergenics_articles_action_post( 'pg_articles_publish', 'publish_post' );
    $done = 0;
    if ( in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true ) ) {
        $now  = current_time( 'mysql' );
        $done = (int) ! is_wp_error( wp_update_post( array(
            'ID'            => $post->ID,
            'post_status'   => 'publish',
            'post_date'     => $now,
            'post_date_gmt' => get_gmt_from_date( $now ),
            'edit_date'     => true,
        ), true ) );
    }
    wp_safe_redirect( prospergenics_articles_mine_url( array( 'status' => 'publish', 'done' => $done ? 'published' : 'failed' ) ) );
    exit;
} );

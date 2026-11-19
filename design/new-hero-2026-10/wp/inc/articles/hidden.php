<?php
/**
 * Articles track: articles hidden by moderation (card #6472).
 *
 * The moderation queue (inc/profiles/moderation.php) hides a reported article with the post meta
 * _pg_articles_hidden (unix time). A hidden article stays published, but for everyone except its
 * author and staff (the pg_moderate capability) it is gone: left out of every article query
 * (the /articles/ listing, categories, tags, series, related articles, feeds, search, the core
 * sitemap and /wp/v2/pg_articles), its page is a 404 and a single REST read is a 404. Its author
 * still sees it on its page (with a notice) and in their own lists. Showing it again removes the
 * meta.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_ARTICLES_HIDDEN_META = '_pg_articles_hidden';

function prospergenics_articles_is_hidden( $post_id ) {
    return (bool) get_post_meta( (int) $post_id, PROSPERGENICS_ARTICLES_HIDDEN_META, true );
}

function prospergenics_articles_set_hidden( $post_id, $hidden ) {
    if ( $hidden ) {
        update_post_meta( (int) $post_id, PROSPERGENICS_ARTICLES_HIDDEN_META, time() );
    } else {
        delete_post_meta( (int) $post_id, PROSPERGENICS_ARTICLES_HIDDEN_META );
    }
    clean_post_cache( (int) $post_id );
}

/** Whether a user may see a hidden article: its author and staff. */
function prospergenics_articles_can_see_hidden( $post, $user_id ) {
    $post = get_post( $post );
    return $post && $user_id && ( (int) $post->post_author === (int) $user_id || user_can( (int) $user_id, 'pg_moderate' ) );
}

/**
 * Whether a query is one that lists articles and should leave hidden ones out. Pure, so the
 * decision is unit-tested.
 *
 * @param array $types     The query's post_type, as an array.
 * @param array $flags     is_admin, is_ajax, is_staff, is_main_singular, is_tax, is_search,
 *                         author (int), user (int)
 */
function prospergenics_articles_hide_applies( array $types, array $flags ) {
    if ( ( ! empty( $flags['is_admin'] ) && empty( $flags['is_ajax'] ) ) || ! empty( $flags['is_staff'] ) || ! empty( $flags['is_main_singular'] ) ) {
        return false;
    }
    if ( ! empty( $flags['user'] ) && (int) $flags['author'] === (int) $flags['user'] ) {
        return false; // the author's own lists (My articles)
    }
    $types = array_filter( $types );
    if ( ! $types ) {
        return ! empty( $flags['is_tax'] ) || ! empty( $flags['is_search'] );
    }
    return in_array( 'pg_article', $types, true ) || in_array( 'any', $types, true );
}

// pre_get_posts also runs for get_posts() (suppress_filters only skips the SQL filters).
add_action( 'pre_get_posts', function ( $q ) {
    $user = get_current_user_id();
    if ( ! prospergenics_articles_hide_applies( (array) $q->get( 'post_type' ), array(
        'is_admin'         => is_admin(),
        'is_ajax'          => wp_doing_ajax(),
        'is_staff'         => $user && current_user_can( 'pg_moderate' ),
        'is_main_singular' => $q->is_main_query() && $q->is_singular(),
        'is_tax'           => $q->is_tax(),
        'is_search'        => $q->is_search(),
        'author'           => (int) $q->get( 'author' ),
        'user'             => $user,
    ) ) ) {
        return;
    }
    $mine = array( 'key' => PROSPERGENICS_ARTICLES_HIDDEN_META, 'compare' => 'NOT EXISTS' );
    $mq   = $q->get( 'meta_query' );
    $q->set( 'meta_query', is_array( $mq ) && $mq ? array( 'relation' => 'AND', $mq, $mine ) : array( $mine ) );
} );

// The article's own page: a 404 for everyone but its author and staff, and never indexed.
add_action( 'template_redirect', function () {
    if ( ! is_singular( 'pg_article' ) ) {
        return;
    }
    $post = get_queried_object();
    if ( ! $post || ! prospergenics_articles_is_hidden( $post->ID ) ) {
        return;
    }
    if ( prospergenics_articles_can_see_hidden( $post, get_current_user_id() ) ) {
        add_filter( 'wp_robots', 'wp_robots_no_robots' );
        nocache_headers();
        return;
    }
    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    nocache_headers();
}, 1 );

// /wp/v2/pg_articles/<id> reads the post directly.
add_filter( 'rest_prepare_pg_article', function ( $response, $post ) {
    if ( prospergenics_articles_is_hidden( $post->ID ) && ! prospergenics_articles_can_see_hidden( $post, get_current_user_id() ) ) {
        return new WP_Error( 'rest_post_invalid_id', 'Invalid post ID.', array( 'status' => 404 ) );
    }
    return $response;
}, 10, 2 );

/** The notice on a hidden article for the people who can still see it ('' otherwise). */
function prospergenics_articles_hidden_notice( $post ) {
    $post = get_post( $post );
    if ( ! $post || ! prospergenics_articles_is_hidden( $post->ID ) ) {
        return '';
    }
    $own = (int) $post->post_author === get_current_user_id();
    return '<p class="pga-hidden-note" role="status">' . esc_html( $own
        ? 'The ProsperGenics team has hidden this article: only you and the team can see it. Please contact us if you think this is a mistake.'
        : 'This article is hidden by moderation: only its author and staff can see it.' ) . '</p>';
}

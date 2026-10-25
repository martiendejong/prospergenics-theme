<?php
/**
 * Articles: claps (card #6369).
 *
 * Like Medium: a reader can clap an article several times, at most 50 times per article.
 * Clapping needs a logged-in account, because the per-person cap needs an identity; a cookie or
 * IP based cap for anonymous visitors is trivially reset and would make the count meaningless.
 * Authors cannot clap for their own articles.
 *
 * Storage (post meta, no custom table):
 *   _pg_articles_clap_by_<user_id>  one row per reader: that reader's claps (1-50)
 *   _pg_articles_claps              total, recomputed from the per-reader rows on every clap,
 *                                   so concurrent claps from different readers never lose counts
 *   _pg_articles_clap_days          claps per day (Y-m-d => n) for the last 30 days
 *   _pg_articles_trend              claps in the last 30 days: the "trending" sort key, refreshed
 *                                   on every clap and once a day by WP-Cron
 *
 * Endpoint: POST /wp-json/prospergenics/v1/articles/<id>/claps  {"count": 1-50}
 * Cookie-authenticated requests must carry the X-WP-Nonce header (wp_rest): without it the
 * REST API treats the request as anonymous and the permission check refuses it. A reader may
 * send at most 30 requests a minute (the button batches clicks into one request).
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_ARTICLES_MAX_CLAPS   = 50;
const PROSPERGENICS_ARTICLES_CLAPS_META  = '_pg_articles_claps';
const PROSPERGENICS_ARTICLES_TREND_META  = '_pg_articles_trend';
const PROSPERGENICS_ARTICLES_DAYS_META   = '_pg_articles_clap_days';
const PROSPERGENICS_ARTICLES_BY_PREFIX   = '_pg_articles_clap_by_';
const PROSPERGENICS_ARTICLES_TREND_DAYS  = 30;
const PROSPERGENICS_ARTICLES_RATE_LIMIT  = 30;
const PROSPERGENICS_ARTICLES_RATE_WINDOW = 60;

/** How many of $requested claps a reader who already gave $already may still give. */
function prospergenics_articles_clap_grant( $already, $requested, $max = PROSPERGENICS_ARTICLES_MAX_CLAPS ) {
    return max( 0, min( (int) $requested, (int) $max - max( 0, (int) $already ) ) );
}

/**
 * Add $n claps on day $today (Y-m-d) to a per-day tally and drop days older than the window.
 *
 * @param array<string,int> $days
 * @return array<string,int>
 */
function prospergenics_articles_add_to_days( $days, $today, $n, $window = PROSPERGENICS_ARTICLES_TREND_DAYS ) {
    $days = is_array( $days ) ? $days : array();
    if ( $n > 0 ) {
        $days[ $today ] = ( isset( $days[ $today ] ) ? (int) $days[ $today ] : 0 ) + (int) $n;
    }
    return prospergenics_articles_prune_days( $days, $today, $window );
}

/** Keep only the days inside the window ending on $today. */
function prospergenics_articles_prune_days( $days, $today, $window = PROSPERGENICS_ARTICLES_TREND_DAYS ) {
    $cutoff = gmdate( 'Y-m-d', strtotime( $today . ' 00:00:00 UTC' ) - ( $window - 1 ) * DAY_IN_SECONDS );
    $kept   = array();
    foreach ( (array) $days as $day => $count ) {
        if ( is_string( $day ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $day ) && $day >= $cutoff && $day <= $today ) {
            $kept[ $day ] = (int) $count;
        }
    }
    ksort( $kept );
    return $kept;
}

/** Trend score: claps in the window. */
function prospergenics_articles_trend_score( $days ) {
    return (int) array_sum( array_map( 'intval', (array) $days ) );
}

/**
 * Fixed-window rate limit. Returns the new window state, and whether this hit is allowed.
 *
 * @param array|false $state ['start' => int, 'hits' => int] or false when none.
 * @return array{allowed:bool,state:array{start:int,hits:int}}
 */
function prospergenics_articles_rate_hit( $state, $now, $limit = PROSPERGENICS_ARTICLES_RATE_LIMIT, $window = PROSPERGENICS_ARTICLES_RATE_WINDOW ) {
    if ( ! is_array( $state ) || ! isset( $state['start'], $state['hits'] ) || $now - (int) $state['start'] >= $window ) {
        $state = array( 'start' => (int) $now, 'hits' => 0 );
    }
    $allowed = $state['hits'] < $limit;
    if ( $allowed ) {
        $state['hits']++;
    }
    return array( 'allowed' => $allowed, 'state' => $state );
}

/** Total claps of an article. */
function prospergenics_articles_claps( $post_id ) {
    return (int) get_post_meta( $post_id, PROSPERGENICS_ARTICLES_CLAPS_META, true );
}

/** Claps one reader gave an article. */
function prospergenics_articles_user_claps( $post_id, $user_id ) {
    return $user_id ? (int) get_post_meta( $post_id, PROSPERGENICS_ARTICLES_BY_PREFIX . (int) $user_id, true ) : 0;
}

/** Recompute an article's total from the per-reader rows. */
function prospergenics_articles_recount( $post_id ) {
    global $wpdb;
    $total = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COALESCE(SUM(CAST(meta_value AS UNSIGNED)), 0) FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
        $post_id,
        $wpdb->esc_like( PROSPERGENICS_ARTICLES_BY_PREFIX ) . '%'
    ) );
    update_post_meta( $post_id, PROSPERGENICS_ARTICLES_CLAPS_META, $total );
    return $total;
}

/**
 * Record claps from a reader, capped at 50 per reader per article.
 *
 * @return array{granted:int,mine:int,claps:int}
 */
function prospergenics_articles_add_claps( $post_id, $user_id, $requested ) {
    $already = prospergenics_articles_user_claps( $post_id, $user_id );
    $grant   = prospergenics_articles_clap_grant( $already, $requested );
    if ( $grant > 0 ) {
        update_post_meta( $post_id, PROSPERGENICS_ARTICLES_BY_PREFIX . (int) $user_id, $already + $grant );
        $days = prospergenics_articles_add_to_days( get_post_meta( $post_id, PROSPERGENICS_ARTICLES_DAYS_META, true ), gmdate( 'Y-m-d' ), $grant );
        update_post_meta( $post_id, PROSPERGENICS_ARTICLES_DAYS_META, $days );
        update_post_meta( $post_id, PROSPERGENICS_ARTICLES_TREND_META, prospergenics_articles_trend_score( $days ) );
        $total = prospergenics_articles_recount( $post_id );
    } else {
        $total = prospergenics_articles_claps( $post_id );
    }
    return array( 'granted' => $grant, 'mine' => $already + $grant, 'claps' => $total );
}

/** Permission check for the clap endpoint: a logged-in reader (nonce checked by core). */
function prospergenics_articles_clap_permission() {
    if ( ! is_user_logged_in() || ! current_user_can( 'read' ) ) {
        return new WP_Error( 'pg_articles_login_required', 'Log in to clap for articles.', array( 'status' => 401 ) );
    }
    return true;
}

/** POST /prospergenics/v1/articles/<id>/claps */
function prospergenics_articles_rest_clap( WP_REST_Request $request ) {
    $post_id = (int) $request['id'];
    $post    = get_post( $post_id );
    if ( ! $post || 'pg_article' !== $post->post_type || 'publish' !== $post->post_status || post_password_required( $post ) ) {
        return new WP_Error( 'pg_articles_not_found', 'Article not found.', array( 'status' => 404 ) );
    }
    $user_id = get_current_user_id();
    if ( (int) $post->post_author === $user_id ) {
        return new WP_Error( 'pg_articles_own_article', 'You cannot clap for your own article.', array( 'status' => 403 ) );
    }
    $key  = 'pg_articles_clap_rl_' . $user_id;
    $rate = prospergenics_articles_rate_hit( get_transient( $key ), time() );
    set_transient( $key, $rate['state'], PROSPERGENICS_ARTICLES_RATE_WINDOW );
    if ( ! $rate['allowed'] ) {
        return new WP_Error( 'pg_articles_rate_limited', 'Too many claps at once. Try again in a minute.', array( 'status' => 429 ) );
    }
    $result = prospergenics_articles_add_claps( $post_id, $user_id, (int) $request['count'] );
    $result['remaining'] = PROSPERGENICS_ARTICLES_MAX_CLAPS - $result['mine'];
    $result['max']       = PROSPERGENICS_ARTICLES_MAX_CLAPS;
    return rest_ensure_response( $result );
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'prospergenics/v1', '/articles/(?P<id>\d+)/claps', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'prospergenics_articles_rest_clap',
        'permission_callback' => 'prospergenics_articles_clap_permission',
        'args'                => array(
            'id'    => array(
                'type'              => 'integer',
                'required'          => true,
                'sanitize_callback' => 'absint',
            ),
            'count' => array(
                'type'    => 'integer',
                'default' => 1,
                'minimum' => 1,
                'maximum' => PROSPERGENICS_ARTICLES_MAX_CLAPS,
            ),
        ),
    ) );
} );

/** Daily: let trend scores decay for articles nobody clapped for recently. */
function prospergenics_articles_refresh_trends() {
    $ids = get_posts( array(
        'post_type'      => 'pg_article',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
        'meta_query'     => array( array( 'key' => PROSPERGENICS_ARTICLES_TREND_META, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC' ) ),
    ) );
    $today = gmdate( 'Y-m-d' );
    foreach ( $ids as $id ) {
        $days = prospergenics_articles_prune_days( get_post_meta( $id, PROSPERGENICS_ARTICLES_DAYS_META, true ), $today );
        update_post_meta( $id, PROSPERGENICS_ARTICLES_DAYS_META, $days );
        update_post_meta( $id, PROSPERGENICS_ARTICLES_TREND_META, prospergenics_articles_trend_score( $days ) );
    }
}
add_action( 'prospergenics_articles_refresh_trends', 'prospergenics_articles_refresh_trends' );
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'prospergenics_articles_refresh_trends' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'prospergenics_articles_refresh_trends' );
    }
} );
add_action( 'switch_theme', function () {
    wp_clear_scheduled_hook( 'prospergenics_articles_refresh_trends' );
} );

// The wp-admin list (Articles > All articles) shows claps and reading time too.
add_filter( 'manage_pg_article_posts_columns', function ( $columns ) {
    $columns['pg_claps']   = 'Claps';
    $columns['pg_reading'] = 'Reading time';
    return $columns;
} );
add_action( 'manage_pg_article_posts_custom_column', function ( $column, $post_id ) {
    if ( 'pg_claps' === $column ) {
        echo esc_html( number_format_i18n( prospergenics_articles_claps( $post_id ) ) );
    } elseif ( 'pg_reading' === $column ) {
        echo esc_html( prospergenics_articles_reading_label( $post_id ) );
    }
}, 10, 2 );

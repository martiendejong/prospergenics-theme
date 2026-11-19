<?php
/**
 * Articles: claps (card #6369).
 *
 * Like Medium: a reader can clap an article several times, at most 50 times per article.
 * Clapping needs a logged-in account, because the per-person cap needs an identity; a cookie or
 * IP based cap for anonymous visitors is trivially reset and would make the count meaningless.
 * Authors cannot clap for their own articles.
 *
 * Storage (audit ARC-21: no per-reader rows in postmeta, so an article's meta stays small however
 * many people clap, and no LIKE scans):
 *   {prefix}pg_claps                one row per reader and article: (user_id, post_id) => n claps
 *                                   (1-50) and when they last clapped. Created with dbDelta behind
 *                                   the prospergenics_articles_claps_db option; the first run moves
 *                                   the old _pg_articles_clap_by_<user_id> post meta into it.
 *   _pg_articles_claps              total, raised with one atomic UPDATE (n = n + granted), so
 *                                   concurrent claps from different readers never lose counts;
 *                                   recounted from the table by the daily job and after an erasure
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
const PROSPERGENICS_ARTICLES_BY_PREFIX   = '_pg_articles_clap_by_'; // before ARC-21; only the migration reads it
const PROSPERGENICS_ARTICLES_CLAPS_DB    = 1; // schema version of {prefix}pg_claps
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

/** The claps table: {prefix}pg_claps. */
function prospergenics_articles_claps_table() {
    global $wpdb;
    return $wpdb->prefix . 'pg_claps';
}

/**
 * Create or upgrade the claps table, then move the old per-reader post meta into it. Runs once
 * per schema version: the option is only stored when everything went through, so an interrupted
 * run continues on the next request (every step is safe to repeat).
 */
function prospergenics_articles_claps_install() {
    global $wpdb;
    if ( (int) get_option( 'prospergenics_articles_claps_db' ) >= PROSPERGENICS_ARTICLES_CLAPS_DB ) {
        return;
    }
    $table = prospergenics_articles_claps_table();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    dbDelta( "CREATE TABLE {$table} (
  user_id bigint(20) unsigned NOT NULL,
  post_id bigint(20) unsigned NOT NULL,
  n smallint(5) unsigned NOT NULL DEFAULT 0,
  updated datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY  (user_id,post_id),
  KEY post_id (post_id)
) " . $wpdb->get_charset_collate() . ';' );
    if ( $table !== $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ) ) {
        return; // could not create it; try again on the next request
    }
    $posts = prospergenics_articles_claps_migrate_meta();
    if ( false === $posts ) {
        return;
    }
    foreach ( $posts as $post_id ) {
        prospergenics_articles_recount( $post_id );
    }
    update_option( 'prospergenics_articles_claps_db', PROSPERGENICS_ARTICLES_CLAPS_DB, true );
}
add_action( 'init', 'prospergenics_articles_claps_install', 5 );

/**
 * Move the old _pg_articles_clap_by_<user_id> post meta into the table, 500 rows at a time: each
 * batch is upserted (keeping the higher count if the row is already there), then deleted.
 *
 * @return int[]|false ids of the articles whose claps moved, or false when a batch failed
 */
function prospergenics_articles_claps_migrate_meta() {
    global $wpdb;
    $table  = prospergenics_articles_claps_table();
    $prefix = PROSPERGENICS_ARTICLES_BY_PREFIX;
    $now    = current_time( 'mysql', true );
    $posts  = array();
    do {
        $rows = (array) $wpdb->get_results( $wpdb->prepare(
            "SELECT meta_id, post_id, meta_key, meta_value FROM {$wpdb->postmeta} WHERE meta_key LIKE %s ORDER BY meta_id LIMIT 500",
            $wpdb->esc_like( $prefix ) . '%'
        ) );
        if ( ! $rows ) {
            break;
        }
        $values = array();
        $args   = array();
        $ids    = array();
        foreach ( $rows as $row ) {
            $ids[]   = (int) $row->meta_id;
            $user_id = substr( $row->meta_key, strlen( $prefix ) );
            $n       = min( PROSPERGENICS_ARTICLES_MAX_CLAPS, max( 0, (int) $row->meta_value ) );
            if ( ! ctype_digit( $user_id ) || ! (int) $user_id || ! $n ) {
                continue; // not a reader row, or nothing to keep: only delete it
            }
            $values[] = '(%d, %d, %d, %s)';
            array_push( $args, (int) $user_id, (int) $row->post_id, $n, $now );
            $posts[ (int) $row->post_id ] = (int) $row->post_id;
        }
        if ( $values && false === $wpdb->query( $wpdb->prepare(
            "INSERT INTO {$table} (user_id, post_id, n, updated) VALUES " . implode( ', ', $values ) . ' ON DUPLICATE KEY UPDATE n = GREATEST(n, VALUES(n))',
            $args
        ) ) ) {
            return false;
        }
        $deleted = $wpdb->query( $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_id IN (" . implode( ', ', array_fill( 0, count( $ids ), '%d' ) ) . ')',
            $ids
        ) );
        if ( ! $deleted ) {
            return false; // nothing deleted: stop instead of reading the same rows again
        }
    } while ( 500 === count( $rows ) );
    foreach ( $posts as $post_id ) {
        wp_cache_delete( $post_id, 'post_meta' );
    }
    return array_values( $posts );
}

/** Claps one reader gave an article. */
function prospergenics_articles_user_claps( $post_id, $user_id ) {
    global $wpdb;
    if ( ! $user_id ) {
        return 0;
    }
    return (int) $wpdb->get_var( $wpdb->prepare(
        'SELECT n FROM ' . prospergenics_articles_claps_table() . ' WHERE user_id = %d AND post_id = %d',
        $user_id,
        $post_id
    ) );
}

/** Recompute an article's total from the claps table (one SUM over the post_id index). */
function prospergenics_articles_recount( $post_id ) {
    global $wpdb;
    $total = (int) $wpdb->get_var( $wpdb->prepare(
        'SELECT COALESCE(SUM(n), 0) FROM ' . prospergenics_articles_claps_table() . ' WHERE post_id = %d',
        $post_id
    ) );
    update_post_meta( $post_id, PROSPERGENICS_ARTICLES_CLAPS_META, $total );
    return $total;
}

/** Add $n to an article's total in one statement (no read-modify-write). @return int the new total */
function prospergenics_articles_bump_total( $post_id, $n ) {
    global $wpdb;
    $updated = $wpdb->query( $wpdb->prepare(
        "UPDATE {$wpdb->postmeta} SET meta_value = CAST(meta_value AS UNSIGNED) + %d WHERE post_id = %d AND meta_key = %s",
        $n,
        $post_id,
        PROSPERGENICS_ARTICLES_CLAPS_META
    ) );
    wp_cache_delete( $post_id, 'post_meta' );
    if ( ! $updated ) {
        return prospergenics_articles_recount( $post_id ); // no total stored yet
    }
    return prospergenics_articles_claps( $post_id );
}

/**
 * Record claps from a reader, capped at 50 per reader per article.
 *
 * @return array{granted:int,mine:int,claps:int}
 */
function prospergenics_articles_add_claps( $post_id, $user_id, $requested ) {
    global $wpdb;
    $post_id = (int) $post_id;
    $user_id = (int) $user_id;
    $already = prospergenics_articles_user_claps( $post_id, $user_id );
    $grant   = prospergenics_articles_clap_grant( $already, $requested );
    if ( $grant > 0 ) {
        // One atomic upsert; LEAST() keeps the cap even when one reader sends two requests at once.
        $wpdb->query( $wpdb->prepare(
            'INSERT INTO ' . prospergenics_articles_claps_table() . ' (user_id, post_id, n, updated) VALUES (%d, %d, %d, %s)'
            . ' ON DUPLICATE KEY UPDATE n = LEAST(%d, n + VALUES(n)), updated = VALUES(updated)',
            $user_id,
            $post_id,
            $grant,
            current_time( 'mysql', true ),
            PROSPERGENICS_ARTICLES_MAX_CLAPS
        ) );
        $days = prospergenics_articles_add_to_days( get_post_meta( $post_id, PROSPERGENICS_ARTICLES_DAYS_META, true ), gmdate( 'Y-m-d' ), $grant );
        update_post_meta( $post_id, PROSPERGENICS_ARTICLES_DAYS_META, $days );
        update_post_meta( $post_id, PROSPERGENICS_ARTICLES_TREND_META, prospergenics_articles_trend_score( $days ) );
        $total = prospergenics_articles_bump_total( $post_id, $grant );
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
    if ( ! $post || 'pg_article' !== $post->post_type || 'publish' !== $post->post_status || post_password_required( $post )
        || ( prospergenics_articles_is_hidden( $post_id ) && ! prospergenics_articles_can_see_hidden( $post, get_current_user_id() ) ) ) { // #6472
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
        prospergenics_articles_recount( $id ); // re-sync the running total with the table
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

/* Personal data (audit ARC-12): a reader's claps are exported and erased with the privacy tools,
 * and removed when the account is deleted; the article totals are recounted from what is left.
 * (The per-day tally behind "trending" holds no names and keeps its numbers.) */

/** @return array<int,int> article id => claps this reader gave */
function prospergenics_articles_claps_by_user( $user_id ) {
    global $wpdb;
    $rows = $wpdb->get_results( $wpdb->prepare(
        'SELECT post_id, n FROM ' . prospergenics_articles_claps_table() . ' WHERE user_id = %d',
        $user_id
    ) );
    $out = array();
    foreach ( (array) $rows as $row ) {
        $out[ (int) $row->post_id ] = (int) $row->n;
    }
    return $out;
}

/** Remove a reader's claps everywhere and recount those articles. @return int articles touched */
function prospergenics_articles_erase_claps( $user_id ) {
    global $wpdb;
    $claps = prospergenics_articles_claps_by_user( $user_id );
    if ( $claps ) {
        $wpdb->delete( prospergenics_articles_claps_table(), array( 'user_id' => (int) $user_id ), array( '%d' ) );
        foreach ( array_keys( $claps ) as $post_id ) {
            prospergenics_articles_recount( $post_id );
        }
    }
    return count( $claps );
}
add_action( 'deleted_user', 'prospergenics_articles_erase_claps' );

/** A deleted article takes its clap rows along (its post meta goes with the post, the table does not). */
add_action( 'deleted_post', function ( $post_id ) {
    global $wpdb;
    $wpdb->delete( prospergenics_articles_claps_table(), array( 'post_id' => (int) $post_id ), array( '%d' ) );
} );

function prospergenics_articles_personal_data_exporter( $email, $page = 1 ) {
    $user  = get_user_by( 'email', $email );
    $items = array();
    foreach ( $user ? prospergenics_articles_claps_by_user( $user->ID ) : array() as $post_id => $n ) {
        $items[] = array(
            'group_id'          => 'prospergenics-claps',
            'group_label'       => 'Article claps',
            'group_description' => 'Articles you clapped for.',
            'item_id'           => 'pg-claps-' . $post_id . '-' . $user->ID,
            'data'              => array(
                array( 'name' => 'Article', 'value' => get_the_title( $post_id ) ),
                array( 'name' => 'URL', 'value' => (string) get_permalink( $post_id ) ),
                array( 'name' => 'Claps', 'value' => (string) $n ),
            ),
        );
    }
    return array( 'data' => $items, 'done' => true );
}

function prospergenics_articles_personal_data_eraser( $email, $page = 1 ) {
    $user = get_user_by( 'email', $email );
    $n    = $user ? prospergenics_articles_erase_claps( $user->ID ) : 0;
    return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
    $exporters['prospergenics-articles'] = array( 'exporter_friendly_name' => 'ProsperGenics article claps', 'callback' => 'prospergenics_articles_personal_data_exporter' );
    return $exporters;
} );
add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
    $erasers['prospergenics-articles'] = array( 'eraser_friendly_name' => 'ProsperGenics article claps', 'callback' => 'prospergenics_articles_personal_data_eraser' );
    return $erasers;
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

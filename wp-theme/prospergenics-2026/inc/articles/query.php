<?php
/**
 * Articles: listing queries and helpers shared by the templates (cards #6366, #6367, #6370).
 *
 * /articles/ (and /articles/category/<slug>/, /articles/tag/<slug>/) list 12 articles a page,
 * newest first or, with ?sort=trending, by claps received in the last 30 days. A series page
 * (/series/<slug>/) lists its parts in reading order, oldest first.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_ARTICLES_PER_PAGE = 12;

/** The listing's sort order from the request: 'newest' (default) or 'trending'. */
function prospergenics_articles_sort() {
    $sort = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : '';
    return 'trending' === $sort ? 'trending' : 'newest';
}

/** Query args that order articles by trend score (claps in the last 30 days), then by date. */
function prospergenics_articles_trending_args() {
    return array(
        'meta_query' => array(
            'relation'  => 'OR',
            'pg_trend'  => array( 'key' => PROSPERGENICS_ARTICLES_TREND_META, 'type' => 'NUMERIC' ),
            'pg_none'   => array( 'key' => PROSPERGENICS_ARTICLES_TREND_META, 'compare' => 'NOT EXISTS' ),
        ),
        'orderby'    => array( 'pg_trend' => 'DESC', 'date' => 'DESC' ),
    );
}

add_action( 'pre_get_posts', function ( $q ) {
    if ( is_admin() || ! $q->is_main_query() ) {
        return;
    }
    if ( $q->is_tax( 'pg_series' ) ) {
        $q->set( 'post_type', 'pg_article' );
        $q->set( 'posts_per_page', 50 );
        $q->set( 'orderby', 'date' );
        $q->set( 'order', 'ASC' );
        return;
    }
    if ( ! $q->is_post_type_archive( 'pg_article' ) && ! $q->is_tax( array( 'pg_article_category', 'pg_article_tag' ) ) ) {
        return;
    }
    $q->set( 'post_type', 'pg_article' );
    $q->set( 'posts_per_page', PROSPERGENICS_ARTICLES_PER_PAGE );
    if ( 'trending' === prospergenics_articles_sort() ) {
        foreach ( prospergenics_articles_trending_args() as $k => $v ) {
            $q->set( $k, $v );
        }
    }
} );

// Taxonomy and series pages get the article title format: "Category: Design – ProsperGenics".
add_filter( 'get_the_archive_title_prefix', function ( $prefix ) {
    if ( is_tax( 'pg_article_category' ) ) {
        return 'Category:';
    }
    if ( is_tax( 'pg_article_tag' ) ) {
        return 'Tag:';
    }
    if ( is_tax( 'pg_series' ) ) {
        return 'Series:';
    }
    return $prefix;
} );

/** The category shown on an article's card and hero: its first category, or null. */
function prospergenics_articles_primary_category( $post ) {
    $terms = get_the_terms( $post, 'pg_article_category' );
    return ( $terms && ! is_wp_error( $terms ) ) ? reset( $terms ) : null;
}

/** The series an article belongs to (its first one), or null. */
function prospergenics_articles_series_of( $post ) {
    $terms = get_the_terms( $post, 'pg_series' );
    return ( $terms && ! is_wp_error( $terms ) ) ? reset( $terms ) : null;
}

/**
 * Where an author's name links to. The profiles track (/in/<username>/) hooks
 * 'prospergenics_articles_author_url'; until then the byline shows the name without a link
 * (author archives are hidden from visitors by inc/security.php, so they are no target).
 */
function prospergenics_articles_author_url( $user_id ) {
    return (string) apply_filters( 'prospergenics_articles_author_url', '', (int) $user_id );
}

/**
 * Up to $n published articles related to $post: the newest in the same categories first, then
 * the newest overall to fill up.
 *
 * @return WP_Post[]
 */
function prospergenics_articles_related( $post, $n = 3 ) {
    $post    = get_post( $post );
    $exclude = array( $post->ID );
    $found   = array();
    $base    = array(
        'post_type'           => 'pg_article',
        'post_status'         => 'publish',
        'no_found_rows'       => true,
        'ignore_sticky_posts' => true,
        'orderby'             => 'date',
        'order'               => 'DESC',
    );
    $cats = wp_get_post_terms( $post->ID, 'pg_article_category', array( 'fields' => 'ids' ) );
    if ( $cats && ! is_wp_error( $cats ) ) {
        $found = get_posts( $base + array(
            'posts_per_page' => $n,
            'post__not_in'   => $exclude,
            'tax_query'      => array( array( 'taxonomy' => 'pg_article_category', 'terms' => $cats ) ),
        ) );
    }
    if ( count( $found ) < $n ) {
        $exclude = array_merge( $exclude, wp_list_pluck( $found, 'ID' ) );
        $found   = array_merge( $found, get_posts( $base + array(
            'posts_per_page' => $n - count( $found ),
            'post__not_in'   => $exclude,
        ) ) );
    }
    return $found;
}

/**
 * Position of $id in an ordered list of part IDs, with its neighbours.
 *
 * @param int[] $ids Published parts in reading order.
 * @return array{index:int,total:int,prev:int,next:int}|null index is 1-based; prev/next are 0 at the ends.
 */
function prospergenics_articles_series_position( array $ids, $id ) {
    $ids = array_values( array_map( 'intval', $ids ) );
    $i   = array_search( (int) $id, $ids, true );
    if ( false === $i ) {
        return null;
    }
    return array(
        'index' => $i + 1,
        'total' => count( $ids ),
        'prev'  => $i > 0 ? $ids[ $i - 1 ] : 0,
        'next'  => $i < count( $ids ) - 1 ? $ids[ $i + 1 ] : 0,
    );
}

/**
 * Series navigation data for an article: the series term, its published parts in reading order
 * (publish date, oldest first) and this article's position. Null when it is in no series.
 */
function prospergenics_articles_series_nav( $post ) {
    $post = get_post( $post );
    $term = prospergenics_articles_series_of( $post );
    if ( ! $term ) {
        return null;
    }
    $ids = get_posts( array(
        'post_type'      => 'pg_article',
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'fields'         => 'ids',
        'orderby'        => 'date',
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'tax_query'      => array( array( 'taxonomy' => 'pg_series', 'terms' => array( $term->term_id ) ) ),
    ) );
    $pos = prospergenics_articles_series_position( $ids, $post->ID );
    if ( ! $pos ) {
        return null;
    }
    return array( 'term' => $term, 'url' => get_term_link( $term ) ) + $pos;
}

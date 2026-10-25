<?php
/**
 * Articles: the pg_article post type and its taxonomies (cards #6365, #6368, #6370).
 *
 * - Written in the block editor (Gutenberg, show_in_rest), with excerpt, featured image,
 *   revisions and author support. Capabilities are the core post ones, so authors and editors
 *   can write articles and contributors can submit them for review.
 * - pg_article_category (/articles/category/<slug>/), pg_article_tag (/articles/tag/<slug>/)
 *   and pg_series (/series/<slug>/), all editable from the editor's sidebar.
 * - Reading time is computed from the word count on every save and kept in post meta.
 * - Drafts, scheduling and unpublishing use WordPress's own statuses (draft, pending, future,
 *   publish); the block editor autosaves articles every 30 seconds.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_ARTICLES_WPM          = 200;
const PROSPERGENICS_ARTICLES_READING_META = '_pg_articles_reading_minutes';

/** Slugs an article may not take: they are sub-paths of /articles/ used by this track. */
function prospergenics_articles_reserved_slugs() {
    return array( 'mine', 'category', 'tag', 'page', 'feed', 'embed' );
}

add_action( 'init', function () {
    // Taxonomies first, so their /articles/category/ and /articles/tag/ rules come before the
    // post type's /articles/<slug>/ rules.
    register_taxonomy( 'pg_article_category', array( 'pg_article' ), array(
        'labels'            => array(
            'name'          => 'Article categories',
            'singular_name' => 'Article category',
            'menu_name'     => 'Categories',
            'all_items'     => 'All categories',
            'edit_item'     => 'Edit category',
            'add_new_item'  => 'Add new category',
            'search_items'  => 'Search categories',
            'back_to_items' => '← Back to categories',
        ),
        'public'            => true,
        'hierarchical'      => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => array( 'slug' => 'articles/category', 'with_front' => false ),
    ) );
    register_taxonomy( 'pg_article_tag', array( 'pg_article' ), array(
        'labels'            => array(
            'name'          => 'Article tags',
            'singular_name' => 'Article tag',
            'menu_name'     => 'Tags',
            'all_items'     => 'All tags',
            'edit_item'     => 'Edit tag',
            'add_new_item'  => 'Add new tag',
            'search_items'  => 'Search tags',
            'back_to_items' => '← Back to tags',
        ),
        'public'            => true,
        'hierarchical'      => false,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => array( 'slug' => 'articles/tag', 'with_front' => false ),
    ) );
    register_taxonomy( 'pg_series', array( 'pg_article' ), array(
        'labels'            => array(
            'name'                       => 'Series',
            'singular_name'              => 'Series',
            'menu_name'                  => 'Series',
            'all_items'                  => 'All series',
            'edit_item'                  => 'Edit series',
            'add_new_item'               => 'Add new series',
            'new_item_name'              => 'New series name',
            'search_items'               => 'Search series',
            'separate_items_with_commas' => 'An article usually belongs to one series. Parts are ordered by publish date.',
            'choose_from_most_used'      => 'Choose from existing series',
            'back_to_items'              => '← Back to series',
        ),
        'description'       => 'A named group of articles read in order (part 1, part 2, ...).',
        'public'            => true,
        'hierarchical'      => false,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => array( 'slug' => 'series', 'with_front' => false ),
    ) );

    register_post_type( 'pg_article', array(
        'labels'          => array(
            'name'                  => 'Articles',
            'singular_name'         => 'Article',
            'menu_name'             => 'Articles',
            'add_new'               => 'Write article',
            'add_new_item'          => 'Write a new article',
            'edit_item'             => 'Edit article',
            'new_item'              => 'New article',
            'view_item'             => 'View article',
            'view_items'            => 'View articles',
            'search_items'          => 'Search articles',
            'not_found'             => 'No articles found.',
            'not_found_in_trash'    => 'No articles in the bin.',
            'all_items'             => 'All articles',
            'archives'              => 'Articles',
            'featured_image'        => 'Cover image',
            'set_featured_image'    => 'Set cover image',
            'remove_featured_image' => 'Remove cover image',
            'use_featured_image'    => 'Use as cover image',
            'item_published'           => 'Article published.',
            'item_scheduled'           => 'Article scheduled.',
            'item_reverted_to_draft'   => 'Article reverted to draft.',
            'item_updated'             => 'Article updated.',
        ),
        'description'     => 'Articles written by ProsperGenics members.',
        'public'          => true,
        'has_archive'     => 'articles',
        'rewrite'         => array( 'slug' => 'articles', 'with_front' => false, 'feeds' => true ),
        'show_in_rest'    => true,
        'rest_base'       => 'pg_article',
        'menu_position'   => 6,
        'menu_icon'       => 'dashicons-media-document',
        'capability_type' => 'post',
        'map_meta_cap'    => true,
        'supports'        => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
        'taxonomies'      => array( 'pg_article_category', 'pg_article_tag', 'pg_series' ),
    ) );

    register_post_meta( 'pg_article', PROSPERGENICS_ARTICLES_READING_META, array(
        'type'          => 'integer',
        'single'        => true,
        'show_in_rest'  => false,
        'auth_callback' => '__return_false',
    ) );
}, 5 );

// Cover images: only for articles, so blog posts keep their current behaviour.
add_action( 'after_setup_theme', function () {
    add_theme_support( 'post-thumbnails', array( 'pg_article' ) );
}, 20 );

/**
 * Flush rewrite rules once whenever this track's URL structure changes (first load, theme
 * switch, version bump), never on every request.
 */
add_action( 'init', function () {
    $key = '_pg_articles_rewrite_version';
    if ( get_option( $key ) !== PROSPERGENICS_ARTICLES_VERSION ) {
        flush_rewrite_rules( false );
        update_option( $key, PROSPERGENICS_ARTICLES_VERSION, true );
    }
}, 99 );
add_action( 'after_switch_theme', function () {
    delete_option( '_pg_articles_rewrite_version' );
} );

add_filter( 'wp_unique_post_slug_is_bad_flat_slug', function ( $bad, $slug, $post_type ) {
    return $bad || ( 'pg_article' === $post_type && in_array( $slug, prospergenics_articles_reserved_slugs(), true ) );
}, 10, 3 );

// #6368: autosave articles every 30 seconds in the block editor (WordPress's default is 60).
add_filter( 'block_editor_settings_all', function ( $settings, $context ) {
    if ( isset( $context->post ) && $context->post instanceof WP_Post && 'pg_article' === $context->post->post_type ) {
        $settings['autosaveInterval'] = 30;
    }
    return $settings;
}, 10, 2 );

/**
 * Words in an article's content: tags, shortcodes and block comments removed, then every run of
 * letters/digits (with inner apostrophes or hyphens) counts as one word, in any script.
 */
function prospergenics_articles_word_count( $content ) {
    $text = preg_replace( '/<!--.*?-->/s', ' ', (string) $content );
    $text = preg_replace( '#<(script|style)\b[^>]*>.*?</\1>#is', ' ', $text );
    $text = preg_replace( '/\[[^\]]*\]/', ' ', $text );
    $text = html_entity_decode( strip_tags( str_replace( '<', ' <', $text ) ), ENT_QUOTES, 'UTF-8' );
    return (int) preg_match_all( "/[\\p{L}\\p{N}]+(?:['’-][\\p{L}\\p{N}]+)*/u", $text );
}

/** Reading time in whole minutes (at least 1) for a word count. */
function prospergenics_articles_minutes_for( $words, $wpm = PROSPERGENICS_ARTICLES_WPM ) {
    return max( 1, (int) ceil( max( 0, (int) $words ) / max( 1, (int) $wpm ) ) );
}

/** Reading time of an article in minutes: the stored value, or computed when missing. */
function prospergenics_articles_reading_minutes( $post ) {
    $post = get_post( $post );
    if ( ! $post ) {
        return 1;
    }
    $stored = (int) get_post_meta( $post->ID, PROSPERGENICS_ARTICLES_READING_META, true );
    return $stored > 0 ? $stored : prospergenics_articles_minutes_for( prospergenics_articles_word_count( $post->post_content ) );
}

/** "4 min read" */
function prospergenics_articles_reading_label( $post ) {
    return sprintf( '%d min read', prospergenics_articles_reading_minutes( $post ) );
}

/**
 * After an article is saved (its terms and meta included): store the reading time, make sure
 * the clap counters exist (so sorting by them includes every article) and tell the platform.
 */
add_action( 'wp_after_insert_post', function ( $post_id, $post, $update ) {
    if ( ! $post instanceof WP_Post || 'pg_article' !== $post->post_type || wp_is_post_revision( $post_id ) ) {
        return;
    }
    update_post_meta( $post_id, PROSPERGENICS_ARTICLES_READING_META, prospergenics_articles_minutes_for( prospergenics_articles_word_count( $post->post_content ) ) );
    add_post_meta( $post_id, PROSPERGENICS_ARTICLES_CLAPS_META, 0, true );
    add_post_meta( $post_id, PROSPERGENICS_ARTICLES_TREND_META, 0, true );
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }
    do_action( 'prospergenics_platform_content_saved', $post_id, 'article' );
}, 10, 3 );

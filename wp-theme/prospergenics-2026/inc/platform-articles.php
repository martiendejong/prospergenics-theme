<?php
/**
 * Prospergenics Platform: articles track (JengoWork board "Prospergenics Platform").
 * Loader for this track's modules in inc/articles/. See docs/PLATFORM-BUILD.md.
 *
 *   content.php  #6365 the pg_article post type, its category/tag taxonomies, reading time,
 *                #6368 autosave interval, #6370 the pg_series taxonomy
 *   query.php    #6366 /articles/ listing: sorting (newest/trending), paging; helpers shared
 *                by the templates (card data, related articles, series navigation)
 *   claps.php    #6369 clap storage and the REST endpoint
 *   seo.php      #6365 description / Open Graph / Twitter tags for articles and their lists
 *   mine.php     #6368 the front-end "My articles" page at /articles/mine/
 *   render.php   markup shared by the templates (card, byline, share links, clap button)
 *   roles.php    ARC-8 the pg_article capability type and the pg_writer role (own media only)
 *   review.php   ARC-9 emails when an article is submitted for review, published or sent back
 *
 * Templates: single-pg_article.php, archive-pg_article.php and taxonomy-pg_*.php in the theme
 * root. Styles and script: assets/platform/articles.css|js, enqueued only on article pages.
 */

defined( 'ABSPATH' ) || exit;

define( 'PROSPERGENICS_ARTICLES_VERSION', '1.0.0' );

require __DIR__ . '/articles/roles.php';
require __DIR__ . '/articles/content.php';
require __DIR__ . '/articles/query.php';
require __DIR__ . '/articles/claps.php';
require __DIR__ . '/articles/seo.php';
require __DIR__ . '/articles/mine.php';
require __DIR__ . '/articles/render.php';
require __DIR__ . '/articles/review.php';

/** Whether the current request shows one of this track's front-end pages. */
function prospergenics_articles_is_article_page() {
    return is_singular( 'pg_article' )
        || is_post_type_archive( 'pg_article' )
        || is_tax( array( 'pg_article_category', 'pg_article_tag', 'pg_series' ) )
        || prospergenics_articles_is_mine_page();
}

add_action( 'wp_enqueue_scripts', function () {
    if ( ! prospergenics_articles_is_article_page() ) {
        return;
    }
    $base = get_template_directory_uri() . '/assets/platform/';
    wp_enqueue_style( 'prospergenics-articles', $base . 'articles.css', array(), PROSPERGENICS_ARTICLES_VERSION );
    wp_enqueue_script( 'prospergenics-articles', $base . 'articles.js', array(), PROSPERGENICS_ARTICLES_VERSION, true );
    wp_localize_script( 'prospergenics-articles', 'pgArticles', array(
        'restUrl' => esc_url_raw( rest_url( 'prospergenics/v1/articles/' ) ),
        'nonce'   => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
        'max'     => PROSPERGENICS_ARTICLES_MAX_CLAPS,
    ) );
} );

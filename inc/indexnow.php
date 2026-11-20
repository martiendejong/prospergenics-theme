<?php
/**
 * IndexNow: virtual key-file + automatic URL submission on publish.
 *
 * Serves /{key}.txt so search engines can verify site ownership,
 * and fires a submission to api.indexnow.org whenever a post or page
 * transitions to 'publish'.
 *
 * JengoWork task 3371.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'PROSPERGENICS_INDEXNOW_KEY', '4a2b8c1d6e3f9074' );

/**
 * Serve the key verification file at /{key}.txt.
 * Uses the same virtual-file approach as inc/security-txt.php.
 */
function prospergenics_indexnow_serve_key() {
    $path = isset( $_SERVER['REQUEST_URI'] ) ? rtrim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' ) : '';
    if ( '/' . PROSPERGENICS_INDEXNOW_KEY . '.txt' !== $path ) {
        return;
    }
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo PROSPERGENICS_INDEXNOW_KEY;
    exit;
}
add_action( 'init', 'prospergenics_indexnow_serve_key' );

/**
 * Submit one or more URLs to api.indexnow.org.
 *
 * @param string[] $urls Absolute URLs to submit.
 */
function prospergenics_indexnow_submit( array $urls ) {
    if ( empty( $urls ) ) {
        return;
    }
    $host = parse_url( home_url(), PHP_URL_HOST );
    $body = wp_json_encode(
        array(
            'host'        => $host,
            'key'         => PROSPERGENICS_INDEXNOW_KEY,
            'keyLocation' => home_url( '/' . PROSPERGENICS_INDEXNOW_KEY . '.txt' ),
            'urlList'     => array_values( $urls ),
        )
    );
    wp_remote_post(
        'https://api.indexnow.org/indexnow',
        array(
            'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
            'body'    => $body,
            'timeout' => 10,
        )
    );
}

/**
 * Submit a URL when a post/page transitions to 'publish'.
 *
 * @param string   $new_status New post status.
 * @param string   $old_status Previous post status.
 * @param \WP_Post $post       The post object.
 */
function prospergenics_indexnow_on_publish( $new_status, $old_status, $post ) {
    if ( 'publish' !== $new_status ) {
        return;
    }
    $url = get_permalink( $post->ID );
    if ( $url ) {
        prospergenics_indexnow_submit( array( $url ) );
    }
}
add_action( 'transition_post_status', 'prospergenics_indexnow_on_publish', 10, 3 );

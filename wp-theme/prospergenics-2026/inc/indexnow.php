<?php
/**
 * IndexNow: virtual key file + automatic URL submission on publish/update.
 *
 * Key file is served at /{key}.txt with no physical file needed (clones the
 * security.php virtual-file pattern). The key file must exist at that URL
 * before IndexNow submissions are accepted by search engines.
 *
 * Supported hosts: api.indexnow.org relay (reaches all participating engines:
 * Bing, Yandex, Seznam, Naver, and others via redistribution).
 * Key: 4a2b8c1d6e3f9074ab5c2d7e8f103456
 *
 * JengoWork task 3371.
 */

defined( 'ABSPATH' ) || exit;

define( 'PG_INDEXNOW_KEY', '4a2b8c1d6e3f9074ab5c2d7e8f103456' );

/**
 * Serve the IndexNow key verification file at /{key}.txt.
 * Content must be exactly the key string (with optional trailing newline).
 */
add_action( 'init', function () {
    $path = isset( $_SERVER['REQUEST_URI'] ) ? rtrim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' ) : '';
    if ( '/' . PG_INDEXNOW_KEY . '.txt' !== $path ) {
        return;
    }
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo PG_INDEXNOW_KEY . "\n";
    exit;
} );

/**
 * Submit one URL to api.indexnow.org.
 *
 * @param string $url Absolute URL to submit.
 * @return bool True on HTTP 200/202, false otherwise.
 */
function pg_indexnow_submit( $url ) {
    $home = home_url( '/' );
    $host = parse_url( $home, PHP_URL_HOST );
    $key  = PG_INDEXNOW_KEY;

    $body = wp_json_encode( array(
        'host'        => $host,
        'key'         => $key,
        'keyLocation' => $home . $key . '.txt',
        'urlList'     => array( $url ),
    ) );

    $response = wp_remote_post( 'https://api.indexnow.org/indexnow', array(
        'headers'    => array( 'Content-Type' => 'application/json; charset=utf-8' ),
        'body'       => $body,
        'timeout'    => 15,
        'user-agent' => 'ProsperGenics/IndexNow',
    ) );

    if ( is_wp_error( $response ) ) {
        return false;
    }

    return in_array( (int) wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true );
}

/**
 * Submit all URLs from the site sitemaps to IndexNow (batched, max 100 per request).
 * Used once for the initial bulk submission; ongoing publishes use the per-post hook below.
 *
 * @return bool True when all batches return 200/202, false if any batch failed.
 */
function pg_indexnow_submit_all() {
    $sitemap_urls = array(
        home_url( '/page-sitemap.xml' ),
        home_url( '/post-sitemap.xml' ),
        home_url( '/people-sitemap.xml' ),
    );

    $urls = array();
    foreach ( $sitemap_urls as $sitemap_url ) {
        $resp = wp_remote_get( $sitemap_url, array( 'timeout' => 20, 'user-agent' => 'ProsperGenics/IndexNow' ) );
        if ( is_wp_error( $resp ) ) {
            continue;
        }
        preg_match_all( '#<loc>(https?://[^<]+)</loc>#', wp_remote_retrieve_body( $resp ), $matches );
        foreach ( $matches[1] as $url ) {
            $url = trim( $url );
            if ( $url && ! in_array( $url, $urls, true ) ) {
                $urls[] = $url;
            }
        }
    }

    if ( empty( $urls ) ) {
        return false;
    }

    $home    = home_url( '/' );
    $host    = parse_url( $home, PHP_URL_HOST );
    $key     = PG_INDEXNOW_KEY;
    $success = true;

    foreach ( array_chunk( $urls, 100 ) as $chunk ) {
        $body = wp_json_encode( array(
            'host'        => $host,
            'key'         => $key,
            'keyLocation' => $home . $key . '.txt',
            'urlList'     => $chunk,
        ) );

        $response = wp_remote_post( 'https://api.indexnow.org/indexnow', array(
            'headers'    => array( 'Content-Type' => 'application/json; charset=utf-8' ),
            'body'       => $body,
            'timeout'    => 30,
            'user-agent' => 'ProsperGenics/IndexNow',
        ) );

        if ( is_wp_error( $response ) || ! in_array( (int) wp_remote_retrieve_response_code( $response ), array( 200, 202 ), true ) ) {
            $success = false;
        }
    }

    return $success;
}

/**
 * Submit the permalink to IndexNow whenever a post/page transitions to 'publish'.
 * Also fires on publish->publish (save/update of an already-published post).
 */
add_action( 'transition_post_status', function ( $new_status, $old_status, $post ) {
    if ( 'publish' !== $new_status ) {
        return;
    }
    if ( in_array( $post->post_type, array( 'revision', 'nav_menu_item', 'customize_changeset', 'wp_block' ), true ) ) {
        return;
    }
    $url = get_permalink( $post );
    if ( $url ) {
        pg_indexnow_submit( $url );
    }
}, 10, 3 );

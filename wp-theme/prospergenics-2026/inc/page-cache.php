<?php
/**
 * #7223 Page cache for the anonymous homepage.
 *
 * Without it every anonymous request runs PHP/WordPress in full before the first
 * byte, which pushes mobile LCP over 2.5 s. The front-page template has no
 * server-rendered nonces or per-user fragments, so the whole output is safe to
 * cache for visitors who are not logged in.
 *
 * Bypassed for: logged-in users, non-GET requests, requests with a query string,
 * and visitors holding a comment/login cookie. Replies carry X-Cache: HIT or MISS.
 */
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const PG_HOME_CACHE_KEY = 'pg_home_cache';

function pg_home_cache_eligible() {
    if ( is_user_logged_in() || ! is_front_page() ) {
        return false;
    }
    if ( ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) !== 'GET' || ! empty( $_GET ) ) {
        return false;
    }
    foreach ( array_keys( $_COOKIE ) as $name ) {
        if ( 0 === strpos( $name, 'comment_author_' ) || 0 === strpos( $name, 'wordpress_logged_in_' ) || 0 === strpos( $name, 'wp-postpass_' ) ) {
            return false;
        }
    }
    return true;
}

add_action( 'template_redirect', function () {
    if ( ! pg_home_cache_eligible() ) {
        return;
    }
    $html = get_transient( PG_HOME_CACHE_KEY );
    if ( is_string( $html ) && '' !== $html ) {
        header( 'X-Cache: HIT' );
        header( 'Content-Type: text/html; charset=' . get_option( 'blog_charset' ) );
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
        exit;
    }
    header( 'X-Cache: MISS' );
    ob_start();
    add_action( 'shutdown', function () {
        // Shutdown runs before PHP flushes buffers; take the page and put it back out.
        $html = '';
        while ( ob_get_level() > 0 ) {
            $html = ob_get_clean() . $html;
        }
        if ( '' !== $html && 200 === http_response_code() && false !== stripos( $html, '</html>' ) ) {
            set_transient( PG_HOME_CACHE_KEY, $html, HOUR_IN_SECONDS );
        }
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput
    }, 0 );
}, 0 );

function pg_home_cache_flush() {
    delete_transient( PG_HOME_CACHE_KEY );
}
add_action( 'save_post', 'pg_home_cache_flush' );
add_action( 'updated_option', function ( $option ) {
    if ( 0 !== strpos( (string) $option, '_transient' ) && 0 !== strpos( (string) $option, '_site_transient' ) && 'cron' !== $option ) {
        pg_home_cache_flush();
    }
} );

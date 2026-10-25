<?php
/**
 * Security for the 2026 theme: response headers, security.txt and WordPress hardening.
 *
 * Ported from the production theme (inc/security-headers.php, task 1669; inc/security-txt.php,
 * task 1438) and extended per docs/AUDIT-2026-10.md (C2, M2, M4).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Headers sent on every front-end response.
 *
 * The enforced CSP is the production theme's permissive one (any https origin, so the
 * CoachOS chat, jengo-listings and plugins keep working) plus `worker-src blob:` for the
 * WordPress emoji worker it used to block. A strict allowlist ships alongside as
 * Report-Only: browsers log what it *would* block in the console, so it can be checked on
 * test and then promoted to the enforced policy without breaking anything. Its list comes
 * from the console on test.prospergenics.com: Cloudflare Web Analytics (beacon script and its
 * reporting endpoint) and the Google Fonts that the jengo-listings plugin loads on /courses/
 * and /members/. The theme's own fonts are self-hosted.
 *
 * @param bool $is_ssl Whether the request is over HTTPS.
 * @return array<string,string> header name => value
 */
function prospergenics_2026_security_headers( $is_ssl ) {
    $headers = array(
        'X-Content-Type-Options'  => 'nosniff',
        'X-Frame-Options'         => 'SAMEORIGIN',
        'Referrer-Policy'         => 'strict-origin-when-cross-origin',
        // microphone stays available to this site: the CoachOS chat has a voice-input button
        'Permissions-Policy'      => 'camera=(), geolocation=(), payment=(), usb=(), microphone=(self)',
        'Content-Security-Policy' => implode( '; ', array(
            "default-src 'self' https: data: blob:",
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' https:",
            "style-src 'self' 'unsafe-inline' https:",
            "img-src 'self' https: data: blob:",
            "font-src 'self' https: data:",
            "connect-src 'self' https:",
            "worker-src 'self' blob:",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "object-src 'none'",
        ) ),
        'Content-Security-Policy-Report-Only' => implode( '; ', array(
            "default-src 'self'",
            "script-src 'self' 'unsafe-inline' https://coaching.martiendejong.nl https://static.cloudflareinsights.com",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: blob: https://secure.gravatar.com https://coaching.martiendejong.nl",
            "font-src 'self' data: https://fonts.gstatic.com",
            "connect-src 'self' https://coaching.martiendejong.nl https://listings.prospergenics.com https://cloudflareinsights.com",
            "frame-src 'self' https://coaching.martiendejong.nl",
            "media-src 'self' https://coaching.martiendejong.nl",
            "worker-src 'self' blob:",
            "frame-ancestors 'self'",
            "base-uri 'self'",
            "form-action 'self'",
            "object-src 'none'",
        ) ),
    );
    if ( $is_ssl ) {
        $headers['Strict-Transport-Security'] = 'max-age=31536000';
    }
    return $headers;
}

add_action( 'send_headers', function () {
    if ( is_admin() || headers_sent() ) {
        return;
    }
    foreach ( prospergenics_2026_security_headers( is_ssl() ) as $name => $value ) {
        header( $name . ': ' . $value );
    }
    header_remove( 'X-Powered-By' );
} );

/**
 * RFC 9116 security.txt, served virtually at /.well-known/security.txt and /security.txt.
 *
 * @param string $contact   Disclosure contact email.
 * @param int    $now       Current Unix time.
 * @param string $canonical Canonical URL of this file.
 * @return string
 */
function prospergenics_2026_security_txt( $contact, $now, $canonical ) {
    return implode( "\n", array(
        'Contact: mailto:' . $contact,
        'Expires: ' . gmdate( 'Y-m-d\TH:i:s\Z', $now + YEAR_IN_SECONDS ),
        'Preferred-Languages: en, nl',
        'Canonical: ' . $canonical,
    ) ) . "\n";
}

add_action( 'init', function () {
    $path = isset( $_SERVER['REQUEST_URI'] ) ? rtrim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' ) : '';
    if ( '/.well-known/security.txt' !== $path && '/security.txt' !== $path ) {
        return;
    }
    header( 'Content-Type: text/plain; charset=utf-8' );
    echo prospergenics_2026_security_txt( get_option( 'admin_email' ), time(), home_url( '/.well-known/security.txt' ) );
    exit;
} );

/*
 * Hardening (audit M4). The site uses none of these features, and each one gives away
 * information or an attack surface:
 *   - XML-RPC (brute-force amplification, pingback abuse),
 *   - the WordPress version in a generator tag,
 *   - admin usernames via /wp-json/wp/v2/users and /?author=N or /author/<name>/ archives.
 * Logged-in users (the editor) keep the users endpoint.
 */
add_filter( 'xmlrpc_enabled', '__return_false' );

// The emoji script fetches images from s.w.org for characters every current browser draws
// itself; drop it so the strict CSP doesn't need that origin.
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );
remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );
add_filter( 'emoji_svg_url', '__return_false' );
// xmlrpc_enabled only covers authenticated methods; drop the rest too (system.multicall
// amplifies password guessing, pingback.ping is abused for DDoS reflection).
add_filter( 'xmlrpc_methods', '__return_empty_array' );
// and refuse the endpoint outright: WordPress always answers system.* methods otherwise
add_action( 'init', function () {
    if ( defined( 'XMLRPC_REQUEST' ) && XMLRPC_REQUEST ) {
        status_header( 403 );
        exit;
    }
}, 0 );
add_filter( 'wp_headers', function ( $headers ) {
    unset( $headers['X-Pingback'] );
    return $headers;
} );
remove_action( 'wp_head', 'rsd_link' );
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

add_filter( 'rest_endpoints', function ( $endpoints ) {
    if ( is_user_logged_in() ) {
        return $endpoints;
    }
    foreach ( array_keys( $endpoints ) as $route ) {
        if ( 0 === strpos( $route, '/wp/v2/users' ) ) {
            unset( $endpoints[ $route ] );
        }
    }
    return $endpoints;
} );

add_action( 'template_redirect', function () {
    if ( is_author() && ! is_user_logged_in() ) {
        global $wp_query;
        $wp_query->set_404();
        status_header( 404 );
        nocache_headers();
    }
}, 1 );

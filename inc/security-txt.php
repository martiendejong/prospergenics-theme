<?php
/**
 * RFC 9116 security.txt
 *
 * Serves a virtual /.well-known/security.txt (and /security.txt) so vulnerability
 * researchers and AI citation/trust pipelines have a machine-readable disclosure
 * contact, without needing a physical file on the server. JengoWork task 1438.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Build the security.txt body per RFC 9116 (Contact + Expires are required fields).
 *
 * Contact reuses the same admin_email the contact form already sends to (see
 * inc/contact-form-handler.php), so it stays accurate without a second hardcoded
 * address that can drift out of sync.
 *
 * @return string
 */
function prospergenics_security_txt_content() {
    $expires = gmdate( 'Y-m-d\TH:i:s\Z', strtotime( '+1 year' ) );
    $contact = get_option( 'admin_email' );

    $lines = array(
        "Contact: mailto:{$contact}",
        "Expires: {$expires}",
        'Preferred-Languages: en',
        'Canonical: https://prospergenics.com/.well-known/security.txt',
    );

    return implode( "\n", $lines ) . "\n";
}

/**
 * Intercept requests for /.well-known/security.txt and /security.txt and serve
 * the virtual body above. Hooked on init (not a rewrite rule) so it works with
 * no flush and regardless of permalink structure.
 */
function prospergenics_maybe_serve_security_txt() {
    $path = isset( $_SERVER['REQUEST_URI'] ) ? rtrim( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' ) : '';

    if ( '/.well-known/security.txt' !== $path && '/security.txt' !== $path ) {
        return;
    }

    header( 'Content-Type: text/plain; charset=utf-8' );
    echo prospergenics_security_txt_content();
    exit;
}
add_action( 'init', 'prospergenics_maybe_serve_security_txt' );

<?php
/**
 * Plugin Name: ProsperGenics e2e helpers (throwaway test site only)
 *
 * run.sh copies this into wp-content/mu-plugins/ of the throwaway e2e WordPress and defines
 * PG_E2E in its wp-config.php; it is never part of the theme or a real site.
 *
 * - admin-ajax.php?action=pg_e2e_nonce&for=<nonce action> returns a valid nonce for the logged-in
 *   user. The matrix uses it to get past check_admin_referer() and prove that the capability check
 *   behind it holds on its own (a nonce only proves intent, never permission).
 * - No mail server: every wp_mail() reports success without sending.
 */

defined( 'ABSPATH' ) || exit;

if ( defined( 'PG_E2E' ) && PG_E2E ) {
    add_action( 'wp_ajax_pg_e2e_nonce', function () {
        $for = isset( $_GET['for'] ) ? sanitize_text_field( wp_unslash( $_GET['for'] ) ) : '';
        wp_die( esc_html( wp_create_nonce( $for ) ) );
    } );
    add_filter( 'pre_wp_mail', '__return_true' );
}

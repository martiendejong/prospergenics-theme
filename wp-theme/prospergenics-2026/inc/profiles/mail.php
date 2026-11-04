<?php
/**
 * Profiles track: platform mail (audit ARC-14).
 *
 * - Sender: WordPress' own default (wordpress@<host>, "WordPress") is replaced by
 *   PROSPERGENICS_MAIL_FROM / PROSPERGENICS_MAIL_FROM_NAME when wp-config.php defines them, else
 *   noreply@<site host> / "ProsperGenics". Both are filterable (prospergenics_mail_from,
 *   prospergenics_mail_from_name). A From that was set on purpose (a mail plugin's, an explicit
 *   header such as the contact form's) is left alone. The domain must publish SPF and DKIM for the
 *   server that sends (DEPLOYMENT_CHECKLIST.md, "Platform mail").
 * - Every platform mail goes through prospergenics_profiles_send_mail(), which returns a real
 *   error when wp_mail() fails and records it the way the contact store does: the outcome on the
 *   member (user meta _pg_profiles_mail_<type>), the failure in the PHP error log and in a short
 *   site log that wp-admin shows to administrators.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROFILES_MAIL_LOG = 'prospergenics_platform_mail_failures';

/** The sender address of platform mail. */
function prospergenics_profiles_mail_from() {
    $default = prospergenics_profiles_default_mail_from( home_url( '/' ) );
    $from    = defined( 'PROSPERGENICS_MAIL_FROM' ) ? (string) PROSPERGENICS_MAIL_FROM : $default;
    $from    = (string) apply_filters( 'prospergenics_mail_from', $from );
    return '' !== $from ? $from : $default;
}

/** The sender name of platform mail. */
function prospergenics_profiles_mail_from_name() {
    $name = defined( 'PROSPERGENICS_MAIL_FROM_NAME' ) ? (string) PROSPERGENICS_MAIL_FROM_NAME : 'ProsperGenics';
    return (string) apply_filters( 'prospergenics_mail_from_name', $name );
}

add_filter( 'wp_mail_from', function ( $from ) {
    return 0 === stripos( (string) $from, 'wordpress@' ) ? prospergenics_profiles_mail_from() : $from;
}, 20 );

add_filter( 'wp_mail_from_name', function ( $name ) {
    return 'WordPress' === (string) $name ? prospergenics_profiles_mail_from_name() : $name;
}, 20 );

/**
 * Send one platform mail and record what happened.
 *
 * @param string $type    Short key: verify, endorsement, …
 * @param int    $user_id The member the mail is about (0 for none).
 * @return true|WP_Error
 */
function prospergenics_profiles_send_mail( $to, $subject, $body, $type, $user_id = 0 ) {
    $error   = '';
    $capture = function ( $wp_error ) use ( &$error ) {
        $error = $wp_error instanceof WP_Error ? $wp_error->get_error_message() : 'unknown error';
    };
    add_action( 'wp_mail_failed', $capture );
    $sent = wp_mail( $to, $subject, $body );
    remove_action( 'wp_mail_failed', $capture );
    if ( ! $sent && '' === $error ) {
        $error = 'wp_mail() returned false.';
    }
    prospergenics_profiles_record_mail( $type, $user_id, (bool) $sent, $error );
    return $sent ? true : new WP_Error( 'pg_profiles_mail_failed', $error, array( 'status' => 503 ) );
}

/** Record a mail outcome on the member, and a failure in the error log and the site log. */
function prospergenics_profiles_record_mail( $type, $user_id, $sent, $error = '' ) {
    $type = sanitize_key( (string) $type );
    if ( $user_id ) {
        update_user_meta( (int) $user_id, '_pg_profiles_mail_' . $type, array(
            'status' => $sent ? 'sent' : 'failed',
            'time'   => time(),
            'error'  => $sent ? '' : (string) $error,
        ) );
    }
    if ( $sent ) {
        return;
    }
    error_log( sprintf( '[prospergenics] %s mail for user %d failed: %s', $type, (int) $user_id, $error ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
    $log = get_option( PROSPERGENICS_PROFILES_MAIL_LOG, array() );
    $log = is_array( $log ) ? $log : array();
    array_unshift( $log, array( 'time' => time(), 'type' => $type, 'user' => (int) $user_id, 'error' => (string) $error ) );
    update_option( PROSPERGENICS_PROFILES_MAIL_LOG, array_slice( $log, 0, 50 ), false );
}

/** The last outcome of a mail type for a member: array{status,time,error} or null. */
function prospergenics_profiles_mail_status( $user_id, $type ) {
    $v = get_user_meta( (int) $user_id, '_pg_profiles_mail_' . sanitize_key( (string) $type ), true );
    return is_array( $v ) && isset( $v['status'] ) ? $v : null;
}

/** Administrators see failed platform mail in wp-admin until they dismiss it. */
add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $log  = get_option( PROSPERGENICS_PROFILES_MAIL_LOG, array() );
    $seen = (int) get_option( 'prospergenics_platform_mail_seen', 0 );
    $new  = array_filter( is_array( $log ) ? $log : array(), function ( $e ) use ( $seen ) {
        return is_array( $e ) && (int) $e['time'] > $seen && (int) $e['time'] > time() - 7 * DAY_IN_SECONDS;
    } );
    if ( ! $new ) {
        return;
    }
    $last    = reset( $new );
    $dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=pg_profiles_mail_seen' ), 'pg_profiles_mail_seen' );
    printf(
        '<div class="notice notice-error"><p><strong>%s</strong> %s</p><p><a href="%s">%s</a></p></div>',
        esc_html( sprintf( '%d platform email(s) could not be sent in the last 7 days', count( $new ) ) ),
        esc_html( sprintf( '(email confirmations, endorsement notices). Last error, %s ago: %s. Check the site\'s mail setup (SMTP, SPF/DKIM).', human_time_diff( (int) $last['time'] ), $last['error'] ) ),
        esc_url( $dismiss ),
        esc_html__( 'Dismiss', 'prospergenics' )
    );
} );

add_action( 'admin_post_pg_profiles_mail_seen', function () {
    if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pg_profiles_mail_seen' ) ) {
        wp_die( 'Not allowed.', 403 );
    }
    update_option( 'prospergenics_platform_mail_seen', time(), false );
    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
    exit;
} );

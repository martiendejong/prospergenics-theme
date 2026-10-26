<?php
/**
 * Profiles track: the account part of /profile/settings/ (audit ARC-5), so members never need
 * wp-admin/profile.php: change the email address (it goes through the magic-link verification in
 * verify.php again) and the password. Both ask for the current password; five wrong passwords lock
 * these forms for 15 minutes.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROFILES_PASSWORD_TRIES  = 5;
const PROSPERGENICS_PROFILES_PASSWORD_LOCK   = 900;
const PROSPERGENICS_PROFILES_PASSWORD_MIN    = 8;

/**
 * Check the member's current password (with a lock after repeated failures).
 *
 * @return true|WP_Error
 */
function prospergenics_profiles_check_current_password( $user_id, $password ) {
    $key   = '_pg_profiles_pw_fail_' . (int) $user_id;
    $fails = (int) get_transient( $key );
    if ( $fails >= PROSPERGENICS_PROFILES_PASSWORD_TRIES ) {
        return new WP_Error( 'pg_profiles_locked', 'Too many wrong passwords. Please wait 15 minutes and try again.', array( 'field' => 'current' ) );
    }
    $user = get_userdata( (int) $user_id );
    if ( ! $user || '' === (string) $password || ! wp_check_password( (string) $password, $user->user_pass, $user->ID ) ) {
        set_transient( $key, $fails + 1, PROSPERGENICS_PROFILES_PASSWORD_LOCK );
        return new WP_Error( 'pg_profiles_password', 'Your current password is not correct.', array( 'field' => 'current' ) );
    }
    delete_transient( $key );
    return true;
}

/**
 * Change the email address. The new address is unverified until its magic link is opened
 * (verify.php's profile_update hook sends it); WordPress also tells the old address.
 *
 * @return array{mail_failed:bool}|WP_Error
 */
function prospergenics_profiles_change_email( $user_id, $new_email, $password ) {
    $ok = prospergenics_profiles_check_current_password( $user_id, $password );
    if ( is_wp_error( $ok ) ) {
        return $ok;
    }
    $user  = get_userdata( (int) $user_id );
    $email = sanitize_email( (string) $new_email );
    if ( '' === $email || ! is_email( $email ) ) {
        return new WP_Error( 'pg_profiles_email', 'Enter a valid email address.', array( 'field' => 'email' ) );
    }
    if ( 0 === strcasecmp( $email, $user->user_email ) ) {
        return new WP_Error( 'pg_profiles_email', 'This is already your email address.', array( 'field' => 'email' ) );
    }
    $other = email_exists( $email );
    if ( $other && (int) $other !== (int) $user->ID ) {
        return new WP_Error( 'pg_profiles_email', 'This email address is used by another account.', array( 'field' => 'email' ) );
    }
    $r = wp_update_user( array( 'ID' => $user->ID, 'user_email' => $email ) );
    if ( is_wp_error( $r ) ) {
        return new WP_Error( 'pg_profiles_email', $r->get_error_message(), array( 'field' => 'email' ) );
    }
    $mail = prospergenics_profiles_mail_status( $user->ID, 'verify' );
    return array( 'mail_failed' => ! $mail || 'failed' === $mail['status'] );
}

/**
 * Change the password. Every other session is logged out; this browser stays logged in
 * (wp_update_user() renews its cookie) and WordPress emails the member about the change.
 *
 * @return true|WP_Error
 */
function prospergenics_profiles_change_password( $user_id, $current, $new, $confirm ) {
    $ok = prospergenics_profiles_check_current_password( $user_id, $current );
    if ( is_wp_error( $ok ) ) {
        return $ok;
    }
    $new = (string) $new;
    if ( strlen( $new ) < PROSPERGENICS_PROFILES_PASSWORD_MIN ) {
        return new WP_Error( 'pg_profiles_new_password', sprintf( 'Use at least %d characters for your new password.', PROSPERGENICS_PROFILES_PASSWORD_MIN ), array( 'field' => 'new' ) );
    }
    if ( $new !== (string) $confirm ) {
        return new WP_Error( 'pg_profiles_new_password', 'The two new passwords are not the same.', array( 'field' => 'confirm' ) );
    }
    if ( $new === (string) $current ) {
        return new WP_Error( 'pg_profiles_new_password', 'Choose a password that is different from your current one.', array( 'field' => 'new' ) );
    }
    $r = wp_update_user( array( 'ID' => (int) $user_id, 'user_pass' => $new ) );
    if ( is_wp_error( $r ) ) {
        return $r;
    }
    // wp_update_user() re-issues this browser's cookie with its current session token: keep that one
    WP_Session_Tokens::get_instance( (int) $user_id )->destroy_others( wp_get_session_token() );
    return true;
}

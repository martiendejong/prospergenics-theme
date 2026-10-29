<?php
/**
 * Profiles track: the platform on wp-admin → Users (audit ARC-15).
 *
 * Columns: Verified (email confirmed, and when), Profile (completion %, who may see it, hidden),
 * Enrolments and Certificates (valid, plus revoked). Row actions, each a nonce-checked link to
 * admin-post.php that needs edit_users and edit_user for that member (edit_user alone is true for
 * your own account, which would let a member mark themselves verified or show a profile staff hid):
 *
 *   Resend confirmation link   a fresh magic link (unverified members only)
 *   Mark verified              confirm the address by hand (e.g. after a check by phone)
 *   Hide profile / Show profile  the _pg_profiles_hidden flag: a hidden /in/ page shows nothing to
 *                              anyone but its owner and staff, also over REST, is noindex and left
 *                              out of the sitemap (data.php, helpers.php)
 *   View profile               the member's /in/ page
 */

defined( 'ABSPATH' ) || exit;

/** The row actions and what they do. */
function prospergenics_profiles_user_actions() {
    return array(
        'resend' => 'Resend confirmation link',
        'verify' => 'Mark verified',
        'hide'   => 'Hide profile',
        'unhide' => 'Show profile',
    );
}

/** The nonce action of a row action for a member. */
function prospergenics_profiles_user_action_nonce( $do, $user_id ) {
    return 'pg_profiles_user_' . $do . '_' . (int) $user_id;
}

/**
 * Whether the current user may use the row actions on a member: staff who manage users
 * (edit_users) and may edit this one. edit_user on its own is not enough: WordPress grants it
 * for your own account.
 */
function prospergenics_profiles_can_manage_member( $user_id ) {
    return current_user_can( 'edit_users' ) && current_user_can( 'edit_user', (int) $user_id );
}

/** Mark a member's email address as confirmed. */
function prospergenics_profiles_mark_verified( $user_id ) {
    delete_user_meta( (int) $user_id, '_pg_profiles_verify_pending' );
    delete_user_meta( (int) $user_id, '_pg_profiles_verify_hash' );
    delete_user_meta( (int) $user_id, '_pg_profiles_verify_expires' );
    update_user_meta( (int) $user_id, '_pg_profiles_email_verified', time() );
}

/** Hide (or show again) a member's profile. */
function prospergenics_profiles_set_hidden( $user_id, $hidden ) {
    if ( $hidden ) {
        update_user_meta( (int) $user_id, '_pg_profiles_hidden', time() );
    } else {
        delete_user_meta( (int) $user_id, '_pg_profiles_hidden' );
    }
}

add_filter( 'manage_users_columns', function ( $columns ) {
    $columns['pg_verified']     = 'Verified';
    $columns['pg_profile']      = 'Profile';
    $columns['pg_enrolments']   = 'Enrolments';
    $columns['pg_certificates'] = 'Certificates';
    return $columns;
} );

add_filter( 'manage_users_custom_column', function ( $output, $column, $user_id ) {
    $user_id = (int) $user_id;
    switch ( $column ) {
        case 'pg_verified':
            if ( ! prospergenics_profiles_is_verified( $user_id ) ) {
                $mail = prospergenics_profiles_mail_status( $user_id, 'verify' );
                return 'No' . ( $mail && 'failed' === $mail['status'] ? '<br /><span style="color:#b32d2e">last link not sent</span>' : '' );
            }
            $at = (int) get_user_meta( $user_id, '_pg_profiles_email_verified', true );
            return 'Yes' . ( $at ? '<br /><span class="description">' . esc_html( wp_date( 'j M Y', $at ) ) . '</span>' : '' );
        case 'pg_profile':
            $levels = prospergenics_profiles_levels();
            $level  = prospergenics_profiles_privacy( $user_id )['profile'];
            $out    = (int) prospergenics_profiles_get_completion( $user_id )['percent'] . '%<br /><span class="description">' . esc_html( $levels[ $level ] ) . '</span>';
            $out .= prospergenics_profiles_is_hidden( $user_id ) ? '<br /><strong>Hidden by staff</strong>' : '';
            return $out . ( function_exists( 'prospergenics_profiles_is_suspended' ) && prospergenics_profiles_is_suspended( $user_id ) ? '<br /><strong>Suspended</strong> (Reports → Suspended members)' : '' );
        case 'pg_enrolments':
            return function_exists( 'prospergenics_courses_user_enrolments' ) ? (string) count( prospergenics_courses_user_enrolments( $user_id ) ) : '–';
        case 'pg_certificates':
            if ( ! function_exists( 'prospergenics_courses_user_certificates' ) ) {
                return '–';
            }
            $valid   = 0;
            $revoked = 0;
            foreach ( prospergenics_courses_user_certificates( $user_id ) as $cert ) {
                if ( prospergenics_courses_certificate_is_revoked( $cert ) ) {
                    $revoked++;
                } else {
                    $valid++;
                }
            }
            return (string) $valid . ( $revoked ? '<br /><span class="description">' . (int) $revoked . ' revoked</span>' : '' );
    }
    return $output;
}, 10, 3 );

add_filter( 'user_row_actions', function ( $actions, $user ) {
    if ( ! $user instanceof WP_User || ! prospergenics_profiles_can_manage_member( $user->ID ) ) {
        return $actions;
    }
    $labels = prospergenics_profiles_user_actions();
    $show   = array();
    if ( ! prospergenics_profiles_is_verified( $user->ID ) ) {
        $show[] = 'resend';
        $show[] = 'verify';
    }
    $show[] = prospergenics_profiles_is_hidden( $user->ID ) ? 'unhide' : 'hide';
    foreach ( $show as $do ) {
        $url = wp_nonce_url( add_query_arg( array( 'action' => 'pg_profiles_user', 'do' => $do, 'user_id' => $user->ID ), admin_url( 'admin-post.php' ) ), prospergenics_profiles_user_action_nonce( $do, $user->ID ) );
        $actions[ 'pg_' . $do ] = '<a href="' . esc_url( $url ) . '">' . esc_html( $labels[ $do ] ) . '<span class="screen-reader-text"> ' . esc_html( $user->display_name ) . '</span></a>';
    }
    $actions['pg_view'] = '<a href="' . esc_url( prospergenics_profiles_url( $user->ID ) ) . '">View profile<span class="screen-reader-text"> ' . esc_html( $user->display_name ) . '</span></a>';
    return $actions;
}, 10, 2 );

add_action( 'admin_post_pg_profiles_user', function () {
    $do      = isset( $_GET['do'] ) ? sanitize_key( wp_unslash( $_GET['do'] ) ) : '';
    $user_id = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
    if ( ! isset( prospergenics_profiles_user_actions()[ $do ] ) || ! get_userdata( $user_id ) ) {
        wp_die( 'Unknown action.', 400 );
    }
    check_admin_referer( prospergenics_profiles_user_action_nonce( $do, $user_id ) );
    if ( ! prospergenics_profiles_can_manage_member( $user_id ) ) {
        wp_die( 'Sorry, you are not allowed to change this member.', 403 );
    }
    $result = 'done';
    switch ( $do ) {
        case 'resend':
            $sent   = prospergenics_profiles_is_verified( $user_id ) ? new WP_Error( 'verified' ) : prospergenics_profiles_send_verification( $user_id );
            $result = is_wp_error( $sent ) ? 'mail_failed' : 'sent';
            break;
        case 'verify':
            prospergenics_profiles_mark_verified( $user_id );
            $result = 'verified';
            break;
        case 'hide':
        case 'unhide':
            prospergenics_profiles_set_hidden( $user_id, 'hide' === $do );
            $result = 'hide' === $do ? 'hidden' : 'shown';
            break;
    }
    $back = wp_get_referer();
    $back = $back ? $back : admin_url( 'users.php' );
    wp_safe_redirect( add_query_arg( array( 'pg_user_done' => $result, 'pg_user' => $user_id ), remove_query_arg( array( 'pg_user_done', 'pg_user' ), $back ) ) );
    exit;
} );

add_action( 'admin_notices', function () {
    $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
    // phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted values
    $done = isset( $_GET['pg_user_done'] ) ? sanitize_key( wp_unslash( $_GET['pg_user_done'] ) ) : '';
    if ( ! $screen || 'users' !== $screen->id || '' === $done ) {
        return;
    }
    $user     = get_userdata( isset( $_GET['pg_user'] ) ? absint( $_GET['pg_user'] ) : 0 );
    $name     = $user ? $user->display_name : 'the member';
    $messages = array(
        'sent'        => array( 'success', sprintf( 'A new confirmation link was emailed to %s.', $name ) ),
        'mail_failed' => array( 'error', sprintf( 'The confirmation link for %s could not be sent. Check the site’s mail setup.', $name ) ),
        'verified'    => array( 'success', sprintf( 'The email address of %s is marked as confirmed.', $name ) ),
        'hidden'      => array( 'success', sprintf( 'The profile of %s is hidden: only they and staff can see it, and it is left out of search engines.', $name ) ),
        'shown'       => array( 'success', sprintf( 'The profile of %s is visible again (as their privacy settings allow).', $name ) ),
    );
    if ( isset( $messages[ $done ] ) ) {
        printf( '<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr( $messages[ $done ][0] ), esc_html( $messages[ $done ][1] ) );
    }
} );

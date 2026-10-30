<?php
/**
 * Staff area (/manage/, card #6457): the actions. Each is a POST to admin-post.php (logged-in users
 * only: there is no nopriv handler) with its own nonce, then a capability check for that member or
 * message, and redirects back to the screen with a ?done= notice.
 *
 *   pg_manage_member   role | verify | resend | hide | unhide | suspend | unsuspend
 *                      prospergenics_manage_can_manage_member() (helpers.php); role changes also
 *                      prospergenics_manage_role_change_allowed(); suspend/unsuspend also pg_moderate
 *   pg_manage_message  read | unread | handled | reopen    edit_post on that contact message
 *
 * The "Open" link of a contact message (GET, nonce in the URL) marks it read: see
 * prospergenics_manage_mark_read_from_link().
 */

defined( 'ABSPATH' ) || exit;

/** Where to go after an action: the screen the form was on (only /manage/ URLs), else $view. */
function prospergenics_manage_back( $view ) {
    $default = prospergenics_manage_url( $view );
    $back    = isset( $_POST['pg_back'] ) ? esc_url_raw( wp_unslash( $_POST['pg_back'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked by the caller
    $back    = wp_validate_redirect( $back, $default );
    if ( 0 !== strpos( $back, home_url( '/manage/' ) ) ) {
        $back = $default;
    }
    return remove_query_arg( array( 'done', 'member' ), $back );
}

/* ───────────────────────── members ───────────────────────── */

/**
 * Apply a member action. The caller has checked the nonce and prospergenics_manage_can_manage_member().
 *
 * @return string the ?done= notice key
 */
function prospergenics_manage_member_apply( $do, $user_id, $role = '', $note = '' ) {
    $user_id = (int) $user_id;
    switch ( $do ) {
        case 'role':
            $user = get_userdata( $user_id );
            if ( ! prospergenics_manage_role_change_allowed( (array) $user->roles, $role, current_user_can( 'promote_user', $user_id ) ) ) {
                wp_die( 'Sorry, you are not allowed to give this member that role.', 403 );
            }
            $user->set_role( $role );
            return 'role';
        case 'verify':
            if ( prospergenics_profiles_is_verified( $user_id ) ) {
                return 'already';
            }
            prospergenics_profiles_mark_verified( $user_id );
            return 'verified';
        case 'resend':
            if ( prospergenics_profiles_is_verified( $user_id ) ) {
                return 'already';
            }
            return is_wp_error( prospergenics_profiles_send_verification( $user_id ) ) ? 'mail_failed' : 'sent';
        case 'hide':
        case 'unhide':
            prospergenics_profiles_set_hidden( $user_id, 'hide' === $do );
            return 'hide' === $do ? 'hidden' : 'shown';
        case 'suspend':
        case 'unsuspend':
            if ( ! current_user_can( PROSPERGENICS_MODERATION_CAP ) ) {
                wp_die( 'Sorry, you are not allowed to suspend members.', 403 );
            }
            if ( 'unsuspend' === $do ) {
                prospergenics_profiles_unsuspend( $user_id );
                return 'unsuspended';
            }
            return is_wp_error( prospergenics_profiles_suspend( $user_id, get_current_user_id(), $note ) ) ? 'cannot_suspend' : 'suspended';
    }
    return 'failed';
}

add_action( 'admin_post_pg_manage_member', function () {
    $do      = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    if ( ! isset( prospergenics_manage_member_actions()[ $do ] ) ) {
        wp_die( 'Unknown action.', 400 );
    }
    check_admin_referer( prospergenics_manage_member_nonce( $do, $user_id ) );
    if ( ! prospergenics_manage_can_access() || ! prospergenics_manage_can_manage_member( $user_id ) ) {
        wp_die( 'Sorry, you are not allowed to change this member.', 403 );
    }
    $role   = isset( $_POST['role'] ) ? sanitize_key( wp_unslash( $_POST['role'] ) ) : '';
    $note   = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
    $result = prospergenics_manage_member_apply( $do, $user_id, $role, $note );
    wp_safe_redirect( add_query_arg( array( 'done' => $result, 'member' => $user_id ), prospergenics_manage_back( 'members' ) ) . '#pgm-member-' . $user_id );
    exit;
} );

/* ───────────────────────── contact messages ───────────────────────── */

/** The contact message the current user may handle, or null. */
function prospergenics_manage_message( $post_id ) {
    $post = get_post( (int) $post_id );
    if ( ! $post || 'pg_contact_msg' !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
        return null;
    }
    return $post;
}

/** Apply a message action. */
function prospergenics_manage_message_apply( WP_Post $post, $do ) {
    $now = time();
    $uid = get_current_user_id();
    switch ( $do ) {
        case 'read':
            if ( ! get_post_meta( $post->ID, '_pg_contact_read_at', true ) ) {
                update_post_meta( $post->ID, '_pg_contact_read_at', $now );
                update_post_meta( $post->ID, '_pg_contact_read_by', $uid );
            }
            break;
        case 'unread':
            delete_post_meta( $post->ID, '_pg_contact_read_at' );
            delete_post_meta( $post->ID, '_pg_contact_read_by' );
            delete_post_meta( $post->ID, '_pg_contact_handled_at' );
            delete_post_meta( $post->ID, '_pg_contact_handled_by' );
            break;
        case 'handled':
            prospergenics_manage_message_apply( $post, 'read' );
            update_post_meta( $post->ID, '_pg_contact_handled_at', $now );
            update_post_meta( $post->ID, '_pg_contact_handled_by', $uid );
            break;
        case 'reopen':
            delete_post_meta( $post->ID, '_pg_contact_handled_at' );
            delete_post_meta( $post->ID, '_pg_contact_handled_by' );
            break;
    }
}

add_action( 'admin_post_pg_manage_message', function () {
    $do      = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
    $post_id = isset( $_POST['message'] ) ? absint( $_POST['message'] ) : 0;
    if ( ! isset( prospergenics_manage_message_actions()[ $do ] ) ) {
        wp_die( 'Unknown action.', 400 );
    }
    check_admin_referer( prospergenics_manage_message_nonce( $do, $post_id ) );
    $post = prospergenics_manage_can_view( 'messages' ) ? prospergenics_manage_message( $post_id ) : null;
    if ( ! $post ) {
        wp_die( 'Sorry, you are not allowed to change this message.', 403 );
    }
    prospergenics_manage_message_apply( $post, $do );
    wp_safe_redirect( add_query_arg( 'done', 'msg_' . $do, prospergenics_manage_back( 'messages' ) ) );
    exit;
} );

/**
 * The "Open" link on the inbox carries a nonce: opening a message that way marks it read. A plain
 * link (a bookmark, a link from somewhere else) only shows it.
 */
function prospergenics_manage_mark_read_from_link( WP_Post $post ) {
    $nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
    if ( '' !== $nonce && wp_verify_nonce( $nonce, prospergenics_manage_message_nonce( 'read', $post->ID ) ) ) {
        prospergenics_manage_message_apply( $post, 'read' );
    }
}

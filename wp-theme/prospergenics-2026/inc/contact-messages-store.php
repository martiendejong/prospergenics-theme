<?php
/**
 * Contact message store.
 *
 * Every contact submission is recorded here BEFORE any mail is attempted. That is the
 * whole point: until now the form's only sink was wp_mail(), so a delivery failure (or a
 * message landing in a spam folder) lost the enquiry with no trace anywhere -- which is
 * exactly what was being reported as "everything disappears into a blackhole, even the
 * proper contact requests".
 *
 * Messages classified as spam are stored too, flagged rather than discarded, so a
 * false positive is recoverable instead of silently dropped.
 *
 * @package Prospergenics
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

const PROSPERGENICS_CONTACT_POST_TYPE = 'pg_contact_msg';

/**
 * Register the (private) post type that holds contact submissions.
 *
 * Not public: these are enquiries, not content. They must never be queryable from the
 * front end, appear in search, or get a permalink -- only an admin screen.
 */
function prospergenics_register_contact_message_post_type() {
    register_post_type(
        PROSPERGENICS_CONTACT_POST_TYPE,
        array(
            'labels'              => array(
                'name'               => __( 'Contact Messages', 'prospergenics' ),
                'singular_name'      => __( 'Contact Message', 'prospergenics' ),
                'menu_name'          => __( 'Contact Messages', 'prospergenics' ),
                'all_items'          => __( 'All Messages', 'prospergenics' ),
                'search_items'       => __( 'Search Messages', 'prospergenics' ),
                'not_found'          => __( 'No contact messages yet.', 'prospergenics' ),
                'not_found_in_trash' => __( 'No contact messages in the trash.', 'prospergenics' ),
            ),
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_menu'        => true,
            'show_in_rest'        => false,
            'menu_icon'           => 'dashicons-email-alt',
            'menu_position'       => 26,
            // Own capability type: enquiries hold names, emails and phone numbers, so being able
            // to write posts (contributors, authors) must not be enough to read them.
            'capability_type'     => array( 'pg_contact_msg', 'pg_contact_msgs' ),
            'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
            'map_meta_cap'        => true,
            'supports'            => array( 'title', 'editor' ),
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
        )
    );
}
add_action( 'init', 'prospergenics_register_contact_message_post_type' );

/** The primitive capabilities of the pg_contact_msg capability type. */
function prospergenics_contact_message_caps() {
    return array(
        'edit_pg_contact_msgs', 'edit_others_pg_contact_msgs', 'publish_pg_contact_msgs', 'read_private_pg_contact_msgs',
        'delete_pg_contact_msgs', 'delete_private_pg_contact_msgs', 'delete_published_pg_contact_msgs',
        'delete_others_pg_contact_msgs', 'edit_private_pg_contact_msgs', 'edit_published_pg_contact_msgs',
    );
}

/**
 * Administrators and editors (everyone who may edit others' posts) handle contact messages.
 * Granted at runtime, so nothing is written to the roles table.
 */
function prospergenics_contact_message_grant_caps( $allcaps ) {
    if ( ! empty( $allcaps['edit_others_posts'] ) ) {
        foreach ( prospergenics_contact_message_caps() as $cap ) {
            $allcaps[ $cap ] = true;
        }
    }
    return $allcaps;
}
add_filter( 'user_has_cap', 'prospergenics_contact_message_grant_caps' );

/**
 * Store one submission.
 *
 * @param array $data   Sanitised submission fields.
 * @param array $spam   Spam reasons; empty means the message looked legitimate.
 * @return int|WP_Error Post ID on success.
 */
function prospergenics_store_contact_message( $data, $spam = array() ) {
    $subject = '' !== $data['subject'] ? $data['subject'] : __( '(no subject)', 'prospergenics' );

    $post_id = wp_insert_post(
        array(
            'post_type'    => PROSPERGENICS_CONTACT_POST_TYPE,
            // Spam is parked in 'draft' so the default admin view (published) stays clean,
            // while the message itself remains readable and restorable.
            'post_status'  => empty( $spam ) ? 'publish' : 'draft',
            'post_title'   => wp_strip_all_tags( $subject ),
            'post_content' => $data['message'],
        ),
        true
    );

    if ( is_wp_error( $post_id ) ) {
        return $post_id;
    }

    update_post_meta( $post_id, '_pg_name', $data['name'] );
    update_post_meta( $post_id, '_pg_email', $data['email'] );
    update_post_meta( $post_id, '_pg_phone', $data['phone'] );
    update_post_meta( $post_id, '_pg_ip', $data['ip'] );
    update_post_meta( $post_id, '_pg_user_agent', $data['user_agent'] );
    update_post_meta( $post_id, '_pg_is_spam', empty( $spam ) ? '0' : '1' );
    update_post_meta( $post_id, '_pg_spam_reasons', implode( ', ', $spam ) );
    update_post_meta( $post_id, '_pg_mail_status', 'pending' );

    return $post_id;
}

/**
 * Record what happened to the notification email for a stored message.
 *
 * @param int    $post_id Stored message.
 * @param string $status  'sent' or 'failed'.
 * @param string $detail  Error detail when failed.
 */
function prospergenics_record_contact_mail_status( $post_id, $status, $detail = '' ) {
    update_post_meta( $post_id, '_pg_mail_status', $status );
    if ( '' !== $detail ) {
        update_post_meta( $post_id, '_pg_mail_error', $detail );
    }
}

/**
 * Where contact notifications are sent.
 *
 * Filterable so the address can be moved to a managed shared inbox without editing the
 * theme -- which is the standing ask: a real person has to own this mailbox.
 */
function prospergenics_contact_notification_address() {
    $to = get_theme_mod( 'prospergenics_email', get_option( 'admin_email' ) );
    return apply_filters( 'prospergenics_contact_notification_address', $to );
}

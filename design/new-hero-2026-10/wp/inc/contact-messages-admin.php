<?php
/**
 * Admin screen for stored contact messages.
 *
 * Storing submissions only helps if someone can see them, so the list table shows who
 * wrote in, how to reply, whether the notification email actually went out, and why
 * anything was flagged as spam -- without opening each message.
 *
 * @package Prospergenics
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Columns for the Contact Messages list table.
 */
function prospergenics_contact_message_columns( $columns ) {
    return array(
        'cb'        => isset( $columns['cb'] ) ? $columns['cb'] : '',
        'title'     => __( 'Subject', 'prospergenics' ),
        'pg_from'   => __( 'From', 'prospergenics' ),
        'pg_mail'   => __( 'Notification', 'prospergenics' ),
        'pg_spam'   => __( 'Spam check', 'prospergenics' ),
        'date'      => __( 'Received', 'prospergenics' ),
    );
}
add_filter( 'manage_' . PROSPERGENICS_CONTACT_POST_TYPE . '_posts_columns', 'prospergenics_contact_message_columns' );

/**
 * Cell contents for the custom columns.
 */
function prospergenics_contact_message_column( $column, $post_id ) {
    switch ( $column ) {
        case 'pg_from':
            $name  = get_post_meta( $post_id, '_pg_name', true );
            $email = get_post_meta( $post_id, '_pg_email', true );
            echo esc_html( $name );
            if ( $email ) {
                printf(
                    '<br><a href="mailto:%1$s">%2$s</a>',
                    esc_attr( $email ),
                    esc_html( $email )
                );
            }
            break;

        case 'pg_mail':
            $status = get_post_meta( $post_id, '_pg_mail_status', true );
            $error  = get_post_meta( $post_id, '_pg_mail_error', true );
            if ( 'sent' === $status ) {
                echo '<span style="color:#1a7a46;">' . esc_html__( 'Sent', 'prospergenics' ) . '</span>';
            } elseif ( 'failed' === $status ) {
                echo '<strong style="color:#b32d2e;">' . esc_html__( 'FAILED', 'prospergenics' ) . '</strong>';
                if ( $error ) {
                    echo '<br><span class="description">' . esc_html( $error ) . '</span>';
                }
            } else {
                echo '<span class="description">' . esc_html__( 'not sent', 'prospergenics' ) . '</span>';
            }
            break;

        case 'pg_spam':
            $is_spam = '1' === get_post_meta( $post_id, '_pg_is_spam', true );
            if ( ! $is_spam ) {
                echo '<span style="color:#1a7a46;">' . esc_html__( 'Looks genuine', 'prospergenics' ) . '</span>';
                break;
            }
            echo '<strong>' . esc_html__( 'Spam', 'prospergenics' ) . '</strong>';
            $reasons = get_post_meta( $post_id, '_pg_spam_reasons', true );
            if ( $reasons ) {
                echo '<br><span class="description">' . esc_html( $reasons ) . '</span>';
            }
            break;
    }
}
add_action( 'manage_' . PROSPERGENICS_CONTACT_POST_TYPE . '_posts_custom_column', 'prospergenics_contact_message_column', 10, 2 );

/**
 * Show the sender's details on the single-message screen.
 */
function prospergenics_contact_message_meta_box() {
    add_meta_box(
        'pg_contact_details',
        __( 'Sender details', 'prospergenics' ),
        'prospergenics_render_contact_message_meta_box',
        PROSPERGENICS_CONTACT_POST_TYPE,
        'side'
    );
}
add_action( 'add_meta_boxes', 'prospergenics_contact_message_meta_box' );

/**
 * Render the sender details box.
 */
function prospergenics_render_contact_message_meta_box( $post ) {
    $rows = array(
        __( 'Name', 'prospergenics' )       => get_post_meta( $post->ID, '_pg_name', true ),
        __( 'Email', 'prospergenics' )      => get_post_meta( $post->ID, '_pg_email', true ),
        __( 'Phone', 'prospergenics' )      => get_post_meta( $post->ID, '_pg_phone', true ),
        __( 'IP', 'prospergenics' )         => get_post_meta( $post->ID, '_pg_ip', true ),
        __( 'User agent', 'prospergenics' ) => get_post_meta( $post->ID, '_pg_user_agent', true ),
        __( 'Notification', 'prospergenics' ) => get_post_meta( $post->ID, '_pg_mail_status', true ),
        __( 'Spam check', 'prospergenics' ) => get_post_meta( $post->ID, '_pg_spam_reasons', true ),
    );

    echo '<table class="widefat striped"><tbody>';
    foreach ( $rows as $label => $value ) {
        if ( '' === $value || null === $value ) {
            continue;
        }
        printf(
            '<tr><th scope="row" style="width:40%%">%1$s</th><td>%2$s</td></tr>',
            esc_html( $label ),
            esc_html( $value )
        );
    }
    echo '</tbody></table>';

    $email = get_post_meta( $post->ID, '_pg_email', true );
    if ( $email ) {
        printf(
            '<p><a class="button button-primary" href="mailto:%1$s?subject=%2$s">%3$s</a></p>',
            esc_attr( $email ),
            rawurlencode( 'Re: ' . $post->post_title ),
            esc_html__( 'Reply by email', 'prospergenics' )
        );
    }
}

/**
 * Flag unsent notifications on the admin dashboard.
 *
 * A silently broken mailer is the failure mode that caused this whole problem, so it
 * gets surfaced where someone will see it rather than sitting in a column nobody opens.
 */
function prospergenics_contact_mail_failure_notice() {
    $screen = get_current_screen();
    if ( ! $screen || ! current_user_can( 'edit_posts' ) ) {
        return;
    }

    $failed = get_posts(
        array(
            'post_type'      => PROSPERGENICS_CONTACT_POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => '_pg_mail_status',
                    'value' => 'failed',
                ),
            ),
        )
    );

    if ( empty( $failed ) ) {
        return;
    }

    printf(
        '<div class="notice notice-error"><p><strong>%1$s</strong> %2$s <a href="%3$s">%4$s</a></p></div>',
        esc_html__( 'Prospergenics:', 'prospergenics' ),
        esc_html__( 'at least one contact notification email failed to send. The messages themselves are safe, but nobody was notified by email.', 'prospergenics' ),
        esc_url( admin_url( 'edit.php?post_type=' . PROSPERGENICS_CONTACT_POST_TYPE ) ),
        esc_html__( 'Review contact messages', 'prospergenics' )
    );
}
add_action( 'admin_notices', 'prospergenics_contact_mail_failure_notice' );

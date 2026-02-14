<?php
/**
 * Contact Form Handler
 *
 * Handles the fallback contact form submission
 *
 * @package Prospergenics
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Process contact form submission
 */
function prospergenics_process_contact_form() {
    // Verify nonce
    if ( ! isset( $_POST['contact_form_nonce'] ) || ! wp_verify_nonce( $_POST['contact_form_nonce'], 'prospergenics_contact_form_nonce' ) ) {
        wp_die( esc_html__( 'Security check failed. Please try again.', 'prospergenics' ) );
    }

    // Sanitize and validate inputs
    $name    = isset( $_POST['contact_name'] ) ? sanitize_text_field( $_POST['contact_name'] ) : '';
    $email   = isset( $_POST['contact_email'] ) ? sanitize_email( $_POST['contact_email'] ) : '';
    $phone   = isset( $_POST['contact_phone'] ) ? sanitize_text_field( $_POST['contact_phone'] ) : '';
    $subject = isset( $_POST['contact_subject'] ) ? sanitize_text_field( $_POST['contact_subject'] ) : '';
    $message = isset( $_POST['contact_message'] ) ? sanitize_textarea_field( $_POST['contact_message'] ) : '';

    // Validate required fields
    $errors = array();

    if ( empty( $name ) ) {
        $errors[] = __( 'Name is required.', 'prospergenics' );
    }

    if ( empty( $email ) || ! is_email( $email ) ) {
        $errors[] = __( 'A valid email address is required.', 'prospergenics' );
    }

    if ( empty( $subject ) ) {
        $errors[] = __( 'Subject is required.', 'prospergenics' );
    }

    if ( empty( $message ) ) {
        $errors[] = __( 'Message is required.', 'prospergenics' );
    }

    // If there are errors, redirect back with error message
    if ( ! empty( $errors ) ) {
        $error_message = implode( ' ', $errors );
        wp_redirect( add_query_arg( 'contact_error', urlencode( $error_message ), wp_get_referer() ) );
        exit;
    }

    // Prepare email
    $to = get_theme_mod( 'prospergenics_email', get_option( 'admin_email' ) );

    $email_subject = sprintf(
        /* translators: %s: contact form subject */
        __( 'Contact Form Submission: %s', 'prospergenics' ),
        $subject
    );

    $email_message = sprintf(
        /* translators: 1: name, 2: email, 3: phone, 4: message */
        __( "New contact form submission:\n\nName: %1\$s\nEmail: %2\$s\nPhone: %3\$s\n\nMessage:\n%4\$s", 'prospergenics' ),
        $name,
        $email,
        $phone,
        $message
    );

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        'Reply-To: ' . $name . ' <' . $email . '>',
    );

    // Send email
    $sent = wp_mail( $to, $email_subject, $email_message, $headers );

    if ( $sent ) {
        // Redirect back with success message
        wp_redirect( add_query_arg( 'contact_success', '1', wp_get_referer() . '#contact' ) );
    } else {
        // Redirect back with error message
        wp_redirect( add_query_arg( 'contact_error', urlencode( __( 'Failed to send message. Please try again later.', 'prospergenics' ) ), wp_get_referer() ) );
    }

    exit;
}
add_action( 'admin_post_nopriv_prospergenics_contact_form', 'prospergenics_process_contact_form' );
add_action( 'admin_post_prospergenics_contact_form', 'prospergenics_process_contact_form' );

/**
 * Display contact form messages
 */
function prospergenics_contact_form_messages() {
    if ( ! is_front_page() && ! is_page() ) {
        return;
    }

    if ( isset( $_GET['contact_success'] ) ) {
        echo '<div class="contact-form-message success" style="background: #d4edda; color: #155724; padding: 1rem; border-radius: 6px; margin: 1rem 0; text-align: center;">';
        echo '<p><strong>' . esc_html__( 'Thank you!', 'prospergenics' ) . '</strong> ' . esc_html__( 'Your message has been sent successfully. We will get back to you soon.', 'prospergenics' ) . '</p>';
        echo '</div>';
    }

    if ( isset( $_GET['contact_error'] ) ) {
        echo '<div class="contact-form-message error" style="background: #f8d7da; color: #721c24; padding: 1rem; border-radius: 6px; margin: 1rem 0; text-align: center;">';
        echo '<p><strong>' . esc_html__( 'Error:', 'prospergenics' ) . '</strong> ' . esc_html( urldecode( $_GET['contact_error'] ) ) . '</p>';
        echo '</div>';
    }
}
add_action( 'wp_footer', 'prospergenics_contact_form_messages' );

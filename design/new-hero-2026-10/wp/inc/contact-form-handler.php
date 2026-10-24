<?php
/**
 * Contact Form Handler
 *
 * Handles the fallback contact form submission.
 *
 * Previously this emailed via wp_mail() and did nothing else, so a submission existed
 * only as an email: if delivery failed or the mail was filtered, the enquiry was gone
 * with no record anywhere. Combined with zero spam protection (a nonce stops no bot),
 * that is what produced thousands of junk submissions and genuine enquiries nobody
 * could find.
 *
 * Now: validate -> classify -> STORE -> then try to mail, recording the outcome. The
 * stored record is the source of truth; email is a notification on top of it.
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

    $data = array(
        'name'       => $name,
        'email'      => $email,
        'phone'      => $phone,
        'subject'    => $subject,
        'message'    => $message,
        'ip'         => prospergenics_client_ip(),
        'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
    );

    // Classify. Spam is flagged, never discarded -- a false positive has to stay
    // recoverable, which is the whole complaint about the old behaviour.
    $spam_reasons = prospergenics_spam_reasons( $message, $_POST );
    if ( empty( $spam_reasons ) && prospergenics_akismet_thinks_spam( $data ) ) {
        $spam_reasons[] = 'Akismet';
    }

    // STORE FIRST. Everything after this point can fail without losing the enquiry.
    $post_id = prospergenics_store_contact_message( $data, $spam_reasons );

    if ( is_wp_error( $post_id ) ) {
        // Could not even record it -- say so plainly rather than claiming success.
        wp_redirect(
            add_query_arg(
                'contact_error',
                rawurlencode( __( 'We could not record your message. Please email us directly.', 'prospergenics' ) ),
                wp_get_referer()
            )
        );
        exit;
    }

    // Spam is stored and acknowledged, but never emailed: notifying on it is what
    // buried the real enquiries. It is reviewable under Contact Messages -> Drafts.
    if ( ! empty( $spam_reasons ) ) {
        wp_redirect( add_query_arg( 'contact_success', '1', wp_get_referer() . '#contact' ) );
        exit;
    }

    $to = prospergenics_contact_notification_address();

    $email_subject = sprintf(
        /* translators: %s: contact form subject */
        __( 'Contact Form Submission: %s', 'prospergenics' ),
        $subject
    );

    $email_message = sprintf(
        /* translators: 1: name, 2: email, 3: phone, 4: message, 5: admin link */
        __( "New contact form submission:\n\nName: %1\$s\nEmail: %2\$s\nPhone: %3\$s\n\nMessage:\n%4\$s\n\n---\nAlso saved in WordPress: %5\$s", 'prospergenics' ),
        $name,
        $email,
        $phone,
        $message,
        admin_url( 'post.php?post=' . $post_id . '&action=edit' )
    );

    $headers = array(
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . get_bloginfo( 'name' ) . ' <' . get_option( 'admin_email' ) . '>',
        'Reply-To: ' . $name . ' <' . $email . '>',
    );

    // Capture the reason for a delivery failure instead of discarding it, so a
    // misconfigured mailer is visible in the admin rather than invisible forever.
    $mail_error = '';
    $capture    = function ( $wp_error ) use ( &$mail_error ) {
        $mail_error = $wp_error->get_error_message();
    };
    add_action( 'wp_mail_failed', $capture );
    $sent = wp_mail( $to, $email_subject, $email_message, $headers );
    remove_action( 'wp_mail_failed', $capture );

    prospergenics_record_contact_mail_status( $post_id, $sent ? 'sent' : 'failed', $mail_error );

    // The message is safely stored either way, so the visitor is told it arrived --
    // because it did. A mail problem is ours to fix, not theirs to retry into a void.
    wp_redirect( add_query_arg( 'contact_success', '1', wp_get_referer() . '#contact' ) );
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

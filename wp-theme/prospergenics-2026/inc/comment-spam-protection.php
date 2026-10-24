<?php
/**
 * Comment spam protection.
 *
 * The blog's comment form had no protection of any kind, which is how the moderation
 * queue filled with thousands of junk comments while exactly one genuine comment exists.
 * The same honeypot/timing/link checks used by the contact form are applied here.
 *
 * Caught comments are sent to the spam queue, not deleted, so a false positive can be
 * recovered. Clearing the existing backlog is a one-off admin job, not something theme
 * code should do -- see docs/CONTACT-AND-COMMENTS-OPS.md.
 *
 * @package Prospergenics
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Add the hidden fields to the comment form.
 */
function prospergenics_comment_form_spam_fields() {
    // Logged-in users are not the problem and should not be second-guessed.
    if ( is_user_logged_in() ) {
        return;
    }
    echo prospergenics_spam_protection_fields(); // phpcs:ignore WordPress.Security.EscapeOutput -- builds its own escaped markup.
}
add_action( 'comment_form_after_fields', 'prospergenics_comment_form_spam_fields' );
add_action( 'comment_form_logged_in_after', 'prospergenics_comment_form_spam_fields' );

/**
 * Reject obvious bot submissions before WordPress stores them.
 *
 * Only the unambiguous signals block outright (honeypot, forged token, instant submit):
 * those cannot plausibly be a human. Softer signals fall through to the spam queue via
 * prospergenics_force_comment_spam_status() so they stay recoverable.
 */
function prospergenics_validate_comment_not_spam( $commentdata ) {
    if ( is_user_logged_in() ) {
        return $commentdata;
    }

    $content = isset( $commentdata['comment_content'] ) ? $commentdata['comment_content'] : '';
    $reasons = prospergenics_spam_reasons( $content, $_POST );

    if ( empty( $reasons ) ) {
        return $commentdata;
    }

    $hard_block = array_filter(
        $reasons,
        function ( $reason ) {
            return false !== strpos( $reason, 'honeypot' )
                || false !== strpos( $reason, 'form token' )
                || false !== strpos( $reason, 'submitted in' );
        }
    );

    if ( ! empty( $hard_block ) ) {
        wp_die(
            esc_html__( 'Your comment could not be posted. If you are a person rather than a bot, please email us instead.', 'prospergenics' ),
            esc_html__( 'Comment rejected', 'prospergenics' ),
            array( 'response' => 403, 'back_link' => true )
        );
    }

    // Remember the soft reasons for the status filter that runs next.
    $GLOBALS['prospergenics_comment_spam_reasons'] = $reasons;
    return $commentdata;
}
add_filter( 'preprocess_comment', 'prospergenics_validate_comment_not_spam', 5 );

/**
 * Route softly-flagged comments into the spam queue.
 */
function prospergenics_force_comment_spam_status( $approved, $commentdata ) {
    if ( ! empty( $GLOBALS['prospergenics_comment_spam_reasons'] ) ) {
        return 'spam';
    }
    return $approved;
}
add_filter( 'pre_comment_approved', 'prospergenics_force_comment_spam_status', 10, 2 );

/**
 * Comments belong on posts, not on pages.
 *
 * Every page on this site is a landing/marketing page; an open comment form on them is
 * pure spam surface with no editorial purpose.
 */
function prospergenics_close_comments_on_pages( $open, $post_id ) {
    if ( 'page' === get_post_type( $post_id ) ) {
        return false;
    }
    return $open;
}
add_filter( 'comments_open', 'prospergenics_close_comments_on_pages', 10, 2 );

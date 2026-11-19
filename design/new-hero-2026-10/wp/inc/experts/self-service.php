<?php
/**
 * Self-service expert listing form at /profile/expert/ (task #4891).
 *
 * Logged-in users apply to be listed in the AI experts directory.
 * Status: 'pending' until an admin sets _pg_is_expert to '1'.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Save expert listing form data for $user_id.
 *
 * @return array Field errors (empty on success).
 */
function prospergenics_expert_save( $user_id, array $post ) {
	$errors = array();

	$bio   = sanitize_textarea_field( isset( $post['expert_bio'] ) ? $post['expert_bio'] : '' );
	$title = sanitize_text_field( isset( $post['expert_title'] ) ? $post['expert_title'] : '' );
	$cats  = array_values( array_intersect(
		array_map( 'sanitize_key', (array) ( isset( $post['expert_categories'] ) ? $post['expert_categories'] : array() ) ),
		array_column( prospergenics_expert_categories(), 'slug' )
	) );

	$links = array();
	foreach ( array_slice( (array) ( isset( $post['expert_links'] ) ? $post['expert_links'] : array() ), 0, 5 ) as $link ) {
		$url   = esc_url_raw( isset( $link['url'] ) ? $link['url'] : '' );
		$label = sanitize_text_field( isset( $link['label'] ) ? $link['label'] : '' );
		if ( $url ) {
			$links[] = array( 'label' => $label, 'url' => $url );
		}
	}

	if ( empty( $cats ) ) {
		$errors['expert_categories'] = 'Please choose at least one expertise category.';
	}
	if ( mb_strlen( $bio ) > 2000 ) {
		$errors['expert_bio'] = 'Bio must be under 2000 characters.';
	}

	if ( ! $errors ) {
		$current = get_user_meta( (int) $user_id, '_pg_is_expert', true );
		if ( '' === $current || false === $current ) {
			update_user_meta( (int) $user_id, '_pg_is_expert', 'pending' );
		}
		update_user_meta( (int) $user_id, '_pg_expert_bio',        $bio );
		update_user_meta( (int) $user_id, '_pg_expert_title',      $title );
		update_user_meta( (int) $user_id, '_pg_expert_categories', wp_json_encode( $cats ) );
		update_user_meta( (int) $user_id, '_pg_expert_links',      wp_json_encode( $links ) );
	}

	return $errors;
}

// Handle POST before the page renders (priority 8, before the page renderer at 9).
add_action( 'template_redirect', function () {
	if ( 'expert' !== (string) get_query_var( 'pg_profile_page' ) ) {
		return;
	}
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( home_url( '/profile/expert/' ) ) );
		exit;
	}
	if ( 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
		return;
	}
	if ( ! check_admin_referer( 'pg_expert_save', '_pg_expert_nonce' ) ) {
		wp_die( 'Security check failed.', '', array( 'response' => 403 ) );
	}
	// phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified with check_admin_referer above
	$errors = prospergenics_expert_save( get_current_user_id(), $_POST );
	if ( ! $errors ) {
		wp_safe_redirect( add_query_arg( 'saved', '1', home_url( '/profile/expert/' ) ) );
		exit;
	}
	set_transient( 'pg_expert_form_errors_' . get_current_user_id(), $errors, 60 );
	wp_safe_redirect( add_query_arg( 'error', '1', home_url( '/profile/expert/' ) ) );
	exit;
}, 8 );

// Serve the self-service page (priority 9, before the profiles track at 10).
add_action( 'template_redirect', function () {
	if ( 'expert' !== (string) get_query_var( 'pg_profile_page' ) ) {
		return;
	}
	global $wp_query;
	$wp_query->is_home = false;
	$wp_query->is_404  = false;
	status_header( 200 );
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( home_url( '/profile/expert/' ) ) );
		exit;
	}
	include get_template_directory() . '/pg-expert-self-service.php';
	exit;
}, 9 );

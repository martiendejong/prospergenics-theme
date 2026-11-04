<?php
/**
 * Courses track: instructor dashboard (card #6364) and course wizard bootstrap (card #6363).
 * Both are gated on the edit_pg_courses capability (administrators and editors for now; see
 * data-model.php).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Courses shown on the dashboard: the user's own, or every course for users who can edit
 * others' courses and asked for all of them.
 *
 * @return WP_Post[]
 */
function prospergenics_courses_dashboard_courses( $user_id, $all = false ) {
	$args = array(
		'post_type'      => 'pg_course',
		'post_status'    => array( 'publish', 'draft', 'pending', 'private', 'future' ),
		'posts_per_page' => 200,
		'orderby'        => 'modified',
		'order'          => 'DESC',
	);
	if ( ! $all || ! user_can( $user_id, 'edit_others_pg_courses' ) ) {
		$args['author'] = (int) $user_id;
	}
	return get_posts( $args );
}

/** Settings for the wizard script (printed before courses.js on the wizard page only). */
function prospergenics_courses_wizard_config() {
	$course_id = (int) get_query_var( 'pg_courses_edit' );
	$course    = null;
	if ( $course_id && 'pg_course' === get_post_type( $course_id ) && current_user_can( 'edit_post', $course_id ) ) {
		$course = prospergenics_courses_rest_course( get_post( $course_id ), true );
		// The wizard edits lessons' raw content and video URLs too.
		foreach ( $course['modules'] as &$m ) {
			foreach ( $m['lessons'] as &$l ) {
				$post             = get_post( $l['id'] );
				$l['content_raw'] = $post ? $post->post_content : '';
				$l['video_url']   = (string) get_post_meta( $l['id'], '_pg_courses_video_url', true );
				$l['quiz']        = prospergenics_courses_get_quiz( $l['id'], true ); // editors only (checked above)
			}
			unset( $l );
		}
		unset( $m );
	}
	$terms = get_terms( array( 'taxonomy' => 'pg_course_category', 'hide_empty' => false ) );
	return array(
		'root'        => esc_url_raw( rest_url( PROSPERGENICS_COURSES_NS . '/courses' ) ),
		'nonce'       => wp_create_nonce( 'wp_rest' ),
		'course'      => $course,
		'canPublish'  => current_user_can( 'publish_pg_courses' ),
		'dashboard'   => home_url( '/instructor/dashboard/' ),
		'editBase'    => home_url( '/instructor/courses/' ),
		'levels'      => prospergenics_courses_levels(),
		'quizTypes'   => prospergenics_courses_quiz_types(),
		'passMark'    => PROSPERGENICS_COURSES_QUIZ_PASS_MARK,
		'siteHost'    => prospergenics_courses_site_host(),
		'categories'  => is_array( $terms ) ? array_map( function ( $t ) { return array( 'id' => (int) $t->term_id, 'name' => $t->name ); }, $terms ) : array(),
	);
}

/* ------------------------------------------------------------------ dashboard actions (no-JS forms) */

add_action( 'admin_post_pg_courses_status', function () {
	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	$to        = isset( $_POST['to'] ) ? sanitize_key( wp_unslash( $_POST['to'] ) ) : '';
	check_admin_referer( 'pg_courses_status_' . $course_id );
	$back = home_url( '/instructor/dashboard/' );
	if ( 'pg_course' !== get_post_type( $course_id ) || ! current_user_can( 'edit_post', $course_id ) ) {
		prospergenics_courses_redirect( $back, 'error' );
	}
	if ( 'publish' === $to ) {
		if ( ! current_user_can( 'publish_pg_courses' ) ) {
			prospergenics_courses_redirect( $back, 'error' );
		}
		wp_update_post( array( 'ID' => $course_id, 'post_status' => 'publish' ) );
		prospergenics_courses_redirect( $back, 'published' );
	}
	if ( 'draft' === $to ) {
		wp_update_post( array( 'ID' => $course_id, 'post_status' => 'draft' ) );
		prospergenics_courses_redirect( $back, 'unpublished' );
	}
	if ( 'trash' === $to ) {
		if ( ! current_user_can( 'delete_post', $course_id ) ) {
			prospergenics_courses_redirect( $back, 'error' );
		}
		wp_trash_post( $course_id );
		prospergenics_courses_redirect( $back, 'trashed' );
	}
	prospergenics_courses_redirect( $back, 'error' );
} );

add_action( 'admin_post_nopriv_pg_courses_status', function () {
	wp_safe_redirect( wp_login_url( home_url( '/instructor/dashboard/' ) ) );
	exit;
} );

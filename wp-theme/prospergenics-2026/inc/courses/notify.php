<?php
/**
 * Courses track: closing the learner loop (audit ARC-20, gap "Show a portfolio and certificates on
 * member profiles").
 *
 *   prospergenics_courses_enrolled            → "You are enrolled" email with the first lesson
 *   prospergenics_courses_certificate_issued  → "Your certificate" email with the PDF link, the
 *                                               public verification page and a next course
 *
 * The certificates themselves are listed on the member's /in/ profile (privacy section
 * "certificates", inc/profiles/data.php); the course page suggests a next course once a learner has
 * finished (prospergenics_courses_next_course(), single-pg_course.php). Weekly reminders for inactive
 * learners are not built (they need an opt-out setting and a cron job; left for later).
 * Mail goes through the platform mail helper (inc/profiles/mail.php), so failures are logged.
 */

defined( 'ABSPATH' ) || exit;

/**
 * A course to take after this one: published, not this course and not one the learner is enrolled
 * in; one in the same category first, then the most popular.
 *
 * @return WP_Post|null
 */
function prospergenics_courses_next_course( $user_id, $course_id ) {
	$exclude = array( (int) $course_id );
	foreach ( prospergenics_courses_user_enrolments( $user_id ) as $e ) {
		$exclude[] = (int) $e->post_parent;
	}
	$candidates = get_posts( array(
		'post_type'      => 'pg_course',
		'post_status'    => 'publish',
		'post__not_in'   => $exclude,
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	if ( ! $candidates ) {
		return null;
	}
	$cats   = wp_list_pluck( prospergenics_courses_categories( $course_id ), 'term_id' );
	$scored = array();
	foreach ( $candidates as $i => $c ) {
		$same     = $cats && array_intersect( $cats, wp_list_pluck( prospergenics_courses_categories( $c->ID ), 'term_id' ) );
		$scored[] = array( $same ? 1 : 0, (int) get_post_meta( $c->ID, '_pg_courses_enrolment_count', true ), -$i, $c );
	}
	usort( $scored, function ( $a, $b ) {
		return array( $b[0], $b[1], $b[2] ) <=> array( $a[0], $a[1], $a[2] );
	} );
	return $scored[0][3];
}

/** "Keep learning": the next course block for a learner who finished a course ('' when none). */
function prospergenics_courses_next_course_html( $user_id, $course_id ) {
	$next = prospergenics_courses_next_course( $user_id, $course_id );
	if ( ! $next ) {
		return '';
	}
	$html  = '<section class="pgc-section pgc-next" aria-labelledby="pgc-next-h">';
	$html .= '<h2 id="pgc-next-h">Keep learning</h2>';
	$html .= '<p class="pgc-muted">You finished this course. This one could be a good next step:</p>';
	$html .= '<div class="pgc-grid pgc-grid--one">' . prospergenics_courses_card( $next, $user_id, 'h3' ) . '</div>';
	return $html . '</section>';
}

function prospergenics_courses_mail_enrolled( $user_id, $course_id ) {
	$user   = get_userdata( (int) $user_id );
	$course = get_post( (int) $course_id );
	if ( ! $user || ! $course ) {
		return;
	}
	$first = prospergenics_courses_resume_lesson( $user->ID, $course->ID );
	$title = wp_strip_all_tags( get_the_title( $course ) );
	$body  = sprintf(
		"Hi %s,\n\nYou are enrolled in \"%s\" on ProsperGenics. Welcome!\n\n%s\nCourse page: %s\nAll your courses: %s\n\nWhen you have completed every lesson you get a certificate you can download and share.\n",
		$user->display_name,
		$title,
		$first ? 'Start with the first lesson: ' . get_permalink( $first ) . "\n" : '',
		get_permalink( $course ),
		home_url( '/courses/my/' )
	);
	prospergenics_profiles_send_mail( $user->user_email, sprintf( 'You are enrolled: %s', $title ), $body, 'course_enrolled', $user->ID );
}
add_action( 'prospergenics_courses_enrolled', 'prospergenics_courses_mail_enrolled', 10, 2 );

function prospergenics_courses_mail_certificate( $cert_id, $user_id, $course_id ) {
	$user   = get_userdata( (int) $user_id );
	$course = get_post( (int) $course_id );
	$cert   = get_post( (int) $cert_id );
	if ( ! $user || ! $course || ! $cert ) {
		return;
	}
	$c    = prospergenics_courses_certificate_data( $cert );
	$next = prospergenics_courses_next_course( $user->ID, $course->ID );
	$body = sprintf(
		"Hi %s,\n\nCongratulations: you completed \"%s\" and earned your certificate.\n\nDownload it (PDF, log in first): %s\nAnyone can check it on its verification page: %s\nCertificate code: %s\n\nIt is also listed on your profile (%s) for the people your privacy settings allow (%s).\n%s",
		$user->display_name,
		$c['course'],
		prospergenics_courses_certificate_url( $course ),
		$c['verify_url'],
		$c['code'],
		function_exists( 'prospergenics_profiles_url' ) ? prospergenics_profiles_url( $user->ID ) : home_url( '/profile/' ),
		home_url( '/profile/settings/' ),
		$next ? sprintf( "\nKeep learning: \"%s\", %s\n", wp_strip_all_tags( get_the_title( $next ) ), get_permalink( $next ) ) : ''
	);
	prospergenics_profiles_send_mail( $user->user_email, sprintf( 'Your certificate for %s', $c['course'] ), $body, 'course_certificate', $user->ID );
}
add_action( 'prospergenics_courses_certificate_issued', 'prospergenics_courses_mail_certificate', 10, 3 );

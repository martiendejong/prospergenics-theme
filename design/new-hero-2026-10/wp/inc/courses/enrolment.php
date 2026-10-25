<?php
/**
 * Courses track: enrolment (card #6359) and progress (card #6360).
 *
 * Enrolment record = a pg_enrolment post: post_author = learner, post_parent = course,
 * post_date_gmt = enrolled_at. Progress lives on that record (_pg_courses_completed:
 * lesson id => unix time; _pg_courses_opened: lesson id => when the learner first opened it), so
 * unenrolling (deleting the record) removes the progress too. A lesson can only be marked
 * complete after it was opened.
 * Only the learner themself can enrol, unenrol or complete lessons: every entry point here
 * acts on get_current_user_id().
 */

defined( 'ABSPATH' ) || exit;

/** The enrolment record, or null. */
function prospergenics_courses_get_enrolment( $user_id, $course_id ) {
	$user_id   = (int) $user_id;
	$course_id = (int) $course_id;
	if ( ! $user_id || ! $course_id ) {
		return null;
	}
	$key    = $user_id . ':' . $course_id;
	$cached = wp_cache_get( $key, 'pg_courses_enrolment' );
	if ( false !== $cached ) {
		return $cached ? get_post( $cached ) : null;
	}
	$ids = get_posts( array(
		'post_type'        => 'pg_enrolment',
		'post_status'      => 'publish',
		'author'           => $user_id,
		'post_parent'      => $course_id,
		'posts_per_page'   => 1,
		'fields'           => 'ids',
		'suppress_filters' => true,
	) );
	wp_cache_set( $key, $ids ? (int) $ids[0] : 0, 'pg_courses_enrolment' );
	return $ids ? get_post( $ids[0] ) : null;
}

add_action( 'init', function () {
	wp_cache_add_non_persistent_groups( array( 'pg_courses_enrolment' ) );
}, 1 );

function prospergenics_courses_is_enrolled( $user_id, $course_id ) {
	return (bool) prospergenics_courses_get_enrolment( $user_id, $course_id );
}

/** Why the user cannot enrol ('' when they can). */
function prospergenics_courses_enrol_block_reason( $user_id, $course_id ) {
	$course = get_post( $course_id );
	if ( ! $course || 'pg_course' !== $course->post_type || 'publish' !== $course->post_status ) {
		return 'not_found';
	}
	if ( ! $user_id ) {
		return 'login';
	}
	if ( ! prospergenics_courses_is_free( $course->ID ) ) {
		return 'paid'; // paid enrolment = future scope (Stripe / M-Pesa)
	}
	return '';
}

/** Recount a course's enrolments into _pg_courses_enrolment_count (used by "popular"). */
function prospergenics_courses_update_enrolment_count( $course_id ) {
	$n = count( get_posts( array( 'post_type' => 'pg_enrolment', 'post_status' => 'publish', 'post_parent' => (int) $course_id, 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) ) );
	update_post_meta( $course_id, '_pg_courses_enrolment_count', $n );
	return $n;
}

/**
 * Enrol a user in a free course (idempotent).
 *
 * @return int|WP_Error enrolment id
 */
function prospergenics_courses_enrol( $user_id, $course_id ) {
	$existing = prospergenics_courses_get_enrolment( $user_id, $course_id );
	if ( $existing ) {
		return (int) $existing->ID;
	}
	$reason = prospergenics_courses_enrol_block_reason( $user_id, $course_id );
	if ( '' !== $reason ) {
		$messages = array(
			'not_found' => 'This course is not available.',
			'login'     => 'Log in to enrol.',
			'paid'      => 'Enrolment in paid courses is not open yet.',
		);
		return new WP_Error( 'pg_courses_' . $reason, $messages[ $reason ], array( 'status' => 'login' === $reason ? 401 : ( 'not_found' === $reason ? 404 : 403 ) ) );
	}
	$id = wp_insert_post( array(
		'post_type'   => 'pg_enrolment',
		'post_status' => 'publish',
		'post_author' => (int) $user_id,
		'post_parent' => (int) $course_id,
		'post_title'  => sprintf( 'User %d in course %d', $user_id, $course_id ),
	), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	update_post_meta( $id, '_pg_courses_completed', array() );
	wp_cache_delete( $user_id . ':' . $course_id, 'pg_courses_enrolment' );
	prospergenics_courses_update_enrolment_count( $course_id );
	do_action( 'prospergenics_courses_enrolled', (int) $user_id, (int) $course_id, (int) $id );
	return (int) $id;
}

/** Unenrol: removes the enrolment and its progress. Issued certificates stay valid. */
function prospergenics_courses_unenrol( $user_id, $course_id ) {
	$enrolment = prospergenics_courses_get_enrolment( $user_id, $course_id );
	if ( ! $enrolment ) {
		return false;
	}
	wp_delete_post( $enrolment->ID, true );
	wp_cache_delete( (int) $user_id . ':' . (int) $course_id, 'pg_courses_enrolment' );
	prospergenics_courses_update_enrolment_count( $course_id );
	do_action( 'prospergenics_courses_unenrolled', (int) $user_id, (int) $course_id );
	return true;
}

/** @return array<int,int> lesson id => completed at (unix time) */
function prospergenics_courses_completed_map( $user_id, $course_id ) {
	$enrolment = prospergenics_courses_get_enrolment( $user_id, $course_id );
	if ( ! $enrolment ) {
		return array();
	}
	$map = get_post_meta( $enrolment->ID, '_pg_courses_completed', true );
	return is_array( $map ) ? array_map( 'intval', $map ) : array();
}

/** @return array<int,int> lesson id => first opened at (unix time) */
function prospergenics_courses_opened_map( $user_id, $course_id ) {
	$enrolment = prospergenics_courses_get_enrolment( $user_id, $course_id );
	if ( ! $enrolment ) {
		return array();
	}
	$map = get_post_meta( $enrolment->ID, '_pg_courses_opened', true );
	return is_array( $map ) ? array_map( 'intval', $map ) : array();
}

/** @return int[] */
function prospergenics_courses_completed_ids( $user_id, $course_id ) {
	return array_map( 'intval', array_keys( prospergenics_courses_completed_map( $user_id, $course_id ) ) );
}

/** Course progress in whole percent (0 when not enrolled). */
function prospergenics_courses_progress( $user_id, $course_id ) {
	if ( ! prospergenics_courses_is_enrolled( $user_id, $course_id ) ) {
		return 0;
	}
	return prospergenics_courses_progress_percent( prospergenics_courses_lesson_ids( $course_id ), prospergenics_courses_completed_ids( $user_id, $course_id ) );
}

/**
 * Mark a lesson complete (or not) for the user. Reaching 100% issues the certificate.
 *
 * @return array{progress:int,completed:bool,certificate:?string}|WP_Error
 */
function prospergenics_courses_set_lesson_complete( $user_id, $lesson_id, $done = true ) {
	$lesson    = get_post( $lesson_id );
	$course_id = $lesson ? prospergenics_courses_course_of( $lesson ) : 0;
	if ( ! $lesson || 'pg_lesson' !== $lesson->post_type || 'publish' !== $lesson->post_status || ! $course_id ) {
		return new WP_Error( 'pg_courses_not_found', 'Lesson not found.', array( 'status' => 404 ) );
	}
	$enrolment = prospergenics_courses_get_enrolment( $user_id, $course_id );
	if ( ! $enrolment ) {
		return new WP_Error( 'pg_courses_not_enrolled', 'Enrol in the course first.', array( 'status' => 403 ) );
	}
	$map = prospergenics_courses_completed_map( $user_id, $course_id );
	if ( $done ) {
		if ( ! isset( $map[ $lesson->ID ] ) ) {
			if ( ! isset( prospergenics_courses_opened_map( $user_id, $course_id )[ $lesson->ID ] ) ) {
				return new WP_Error( 'pg_courses_not_opened', 'Open the lesson before marking it complete.', array( 'status' => 403 ) );
			}
			$map[ $lesson->ID ] = time();
		}
	} else {
		unset( $map[ $lesson->ID ] );
	}
	update_post_meta( $enrolment->ID, '_pg_courses_completed', $map );
	update_post_meta( $enrolment->ID, '_pg_courses_last_lesson', (int) $lesson->ID );

	$progress = prospergenics_courses_progress( $user_id, $course_id );
	$code     = null;
	$blocked  = null;
	if ( 100 === $progress ) {
		if ( ! get_post_meta( $enrolment->ID, '_pg_courses_completed_at', true ) ) {
			update_post_meta( $enrolment->ID, '_pg_courses_completed_at', time() );
			do_action( 'prospergenics_courses_course_completed', (int) $user_id, (int) $course_id );
		}
		$cert    = prospergenics_courses_issue_certificate( $user_id, $course_id );
		$revoked = $cert && prospergenics_courses_certificate_is_revoked( $cert );
		$code    = $cert && ! $revoked ? (string) get_post_meta( $cert->ID, '_pg_courses_code', true ) : null;
		$blocked = $cert ? ( $revoked ? 'revoked' : null ) : prospergenics_courses_certificate_block_reason( $user_id );
	}
	// certificate_blocked: why a learner at 100% has no (valid) certificate ('unverified', 'revoked'), else null.
	return array( 'progress' => $progress, 'completed' => (bool) $done, 'certificate' => $code, 'certificate_blocked' => $blocked ? $blocked : null );
}

/**
 * The learner opened a lesson (the lesson page calls this): record the first time, which
 * completion requires, and remember the lesson opened last (for "Continue").
 */
function prospergenics_courses_touch_lesson( $user_id, $lesson ) {
	$course_id = prospergenics_courses_course_of( $lesson );
	$enrolment = prospergenics_courses_get_enrolment( $user_id, $course_id );
	if ( ! $enrolment ) {
		return;
	}
	$opened = prospergenics_courses_opened_map( $user_id, $course_id );
	if ( ! isset( $opened[ $lesson->ID ] ) ) {
		$opened[ $lesson->ID ] = time();
		update_post_meta( $enrolment->ID, '_pg_courses_opened', $opened );
	}
	if ( (int) get_post_meta( $enrolment->ID, '_pg_courses_last_lesson', true ) !== (int) $lesson->ID ) {
		update_post_meta( $enrolment->ID, '_pg_courses_last_lesson', (int) $lesson->ID );
	}
}

/**
 * Where "Continue" goes: the first lesson not yet completed after the one opened last, else the
 * first incomplete lesson, else the first lesson.
 *
 * @return WP_Post|null
 */
function prospergenics_courses_resume_lesson( $user_id, $course_id ) {
	$lessons = prospergenics_courses_flat_lessons( $course_id );
	if ( ! $lessons ) {
		return null;
	}
	$done      = array_flip( prospergenics_courses_completed_ids( $user_id, $course_id ) );
	$enrolment = prospergenics_courses_get_enrolment( $user_id, $course_id );
	$last      = $enrolment ? (int) get_post_meta( $enrolment->ID, '_pg_courses_last_lesson', true ) : 0;
	$start     = 0;
	foreach ( $lessons as $i => $l ) {
		if ( $l->ID === $last ) {
			$start = isset( $done[ $l->ID ] ) ? $i + 1 : $i;
			break;
		}
	}
	$n = count( $lessons );
	for ( $k = 0; $k < $n; $k++ ) {
		$l = $lessons[ ( $start + $k ) % $n ];
		if ( ! isset( $done[ $l->ID ] ) ) {
			return $l;
		}
	}
	return $lessons[0];
}

/** @return WP_Post[] the user's enrolment records, newest first. */
function prospergenics_courses_user_enrolments( $user_id ) {
	if ( ! $user_id ) {
		return array();
	}
	return get_posts( array(
		'post_type'        => 'pg_enrolment',
		'post_status'      => 'publish',
		'author'           => (int) $user_id,
		'posts_per_page'   => 200,
		'orderby'          => 'date',
		'order'            => 'DESC',
		'suppress_filters' => true,
	) );
}

/** @return array{enrolled:int,completed:int,rate:int} */
function prospergenics_courses_stats( $course_id ) {
	$ids       = get_posts( array( 'post_type' => 'pg_enrolment', 'post_status' => 'publish', 'post_parent' => (int) $course_id, 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) );
	$completed = 0;
	foreach ( $ids as $id ) {
		if ( get_post_meta( $id, '_pg_courses_completed_at', true ) ) {
			$completed++;
		}
	}
	return array( 'enrolled' => count( $ids ), 'completed' => $completed, 'rate' => prospergenics_courses_rate( $completed, count( $ids ) ) );
}

/* ------------------------------------------------------------------ form handlers (no-JS path) */

/** Notices shown after a form action, keyed by the ?pgc= value the handler redirects with. */
function prospergenics_courses_notices() {
	return array(
		'enrolled'    => 'You are enrolled. Enjoy the course!',
		'unenrolled'  => 'You left the course. Your progress was removed.',
		'completed'   => 'Lesson marked as complete.',
		'uncompleted' => 'Lesson marked as not complete.',
		'finished'    => 'Congratulations, you completed the course! Your certificate is ready.',
		'finished_unverified' => 'Congratulations, you completed the course! Confirm your email address to receive your certificate.',
		'revoked'     => 'The certificate was revoked.',
		'reinstated'  => 'The certificate is valid again.',
		'paid'        => 'Enrolment in paid courses is not open yet.',
		'error'       => 'Something went wrong. Please try again.',
		'published'   => 'The course is published.',
		'unpublished' => 'The course is unpublished (back to draft).',
		'trashed'     => 'The course was moved to the trash.',
	);
}

function prospergenics_courses_redirect( $url, $notice ) {
	wp_safe_redirect( add_query_arg( 'pgc', $notice, $url ) );
	exit;
}

// Logged-out visitors who post a form go to the login page and come back to the course.
foreach ( array( 'pg_courses_enrol', 'pg_courses_unenrol', 'pg_courses_complete' ) as $prospergenics_courses_action ) {
	add_action( 'admin_post_nopriv_' . $prospergenics_courses_action, function () {
		$id  = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : ( isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0 );
		$url = $id ? get_permalink( $id ) : home_url( '/courses/' );
		wp_safe_redirect( wp_login_url( $url ? $url : home_url( '/courses/' ) ) );
		exit;
	} );
}
unset( $prospergenics_courses_action );

add_action( 'admin_post_pg_courses_enrol', function () {
	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	check_admin_referer( 'pg_courses_enrol_' . $course_id );
	$url    = 'pg_course' === get_post_type( $course_id ) ? get_permalink( $course_id ) : home_url( '/courses/' );
	$result = prospergenics_courses_enrol( get_current_user_id(), $course_id );
	if ( is_wp_error( $result ) ) {
		prospergenics_courses_redirect( $url, 'pg_courses_paid' === $result->get_error_code() ? 'paid' : 'error' );
	}
	prospergenics_courses_redirect( $url, 'enrolled' );
} );

add_action( 'admin_post_pg_courses_unenrol', function () {
	$course_id = isset( $_POST['course_id'] ) ? absint( $_POST['course_id'] ) : 0;
	check_admin_referer( 'pg_courses_unenrol_' . $course_id );
	$url = 'pg_course' === get_post_type( $course_id ) ? get_permalink( $course_id ) : home_url( '/courses/' );
	prospergenics_courses_unenrol( get_current_user_id(), $course_id );
	prospergenics_courses_redirect( $url, 'unenrolled' );
} );

add_action( 'admin_post_pg_courses_complete', function () {
	$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
	check_admin_referer( 'pg_courses_complete_' . $lesson_id );
	$done   = ! empty( $_POST['done'] );
	$result = prospergenics_courses_set_lesson_complete( get_current_user_id(), $lesson_id, $done );
	$url    = 'pg_lesson' === get_post_type( $lesson_id ) ? get_permalink( $lesson_id ) : home_url( '/courses/' );
	if ( is_wp_error( $result ) ) {
		prospergenics_courses_redirect( $url, 'error' );
	}
	if ( $done && 100 === $result['progress'] ) {
		prospergenics_courses_redirect( get_permalink( prospergenics_courses_course_of( $lesson_id ) ), 'unverified' === $result['certificate_blocked'] ? 'finished_unverified' : ( $result['certificate_blocked'] ? 'completed' : 'finished' ) );
	}
	// Completing a lesson moves on to the next one, if there is one.
	if ( $done && ! empty( $_POST['next'] ) ) {
		$next = absint( $_POST['next'] );
		if ( prospergenics_courses_course_of( $next ) === prospergenics_courses_course_of( $lesson_id ) ) {
			$url = get_permalink( $next );
		}
	}
	prospergenics_courses_redirect( $url, $done ? 'completed' : 'uncompleted' );
} );

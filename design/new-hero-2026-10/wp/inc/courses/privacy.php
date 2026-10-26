<?php
/**
 * Courses track: personal data (audit ARC-12).
 *
 * An exporter and an eraser for a learner's enrolments (with progress) and certificates, for
 * Tools → Export / Erase Personal Data and the profile's "Download my data" / "Delete my account".
 * Erasing deletes the records, so a certificate's public verification link stops working; the
 * enrolment counts of the courses are recounted. Deleting a WordPress user removes these records
 * before the deletion, so they are never reassigned to another account with the user's content.
 */

defined( 'ABSPATH' ) || exit;

/** A learner's certificate records. @return WP_Post[] */
function prospergenics_courses_user_certificates( $user_id ) {
	return get_posts( array(
		'post_type'        => 'pg_certificate',
		'post_status'      => 'any',
		'author'           => (int) $user_id,
		'posts_per_page'   => -1,
		'suppress_filters' => true,
		'no_found_rows'    => true,
	) );
}

/** The courses exporter. @return array{data:array,done:bool} */
function prospergenics_courses_personal_data_exporter( $email, $page = 1 ) {
	$user  = get_user_by( 'email', $email );
	$items = array();
	if ( ! $user ) {
		return array( 'data' => $items, 'done' => true );
	}
	$pairs = function ( array $in ) {
		$out = array();
		foreach ( $in as $name => $value ) {
			if ( '' !== (string) $value ) {
				$out[] = array( 'name' => $name, 'value' => (string) $value );
			}
		}
		return $out;
	};
	$date = function ( $t ) {
		return $t ? gmdate( 'Y-m-d H:i', (int) $t ) . ' UTC' : '';
	};
	foreach ( prospergenics_courses_user_enrolments( $user->ID ) as $enrolment ) {
		$course   = get_post( (int) $enrolment->post_parent );
		$progress = $course ? prospergenics_courses_progress( $user->ID, $course->ID ) : null;
		$items[]  = array(
			'group_id'          => 'prospergenics-enrolments',
			'group_label'       => 'Course enrolments',
			'group_description' => 'Courses you enrolled in and your progress.',
			'item_id'           => 'pg-enrolment-' . (int) $enrolment->ID,
			'data'              => $pairs( array(
				'Course'            => $course ? get_the_title( $course ) : '(deleted course)',
				'Course URL'        => $course ? (string) get_permalink( $course ) : '',
				'Enrolled'          => $enrolment->post_date_gmt . ' UTC',
				'Progress'          => null === $progress ? '' : (int) $progress . '%',
				'Lessons completed' => count( (array) get_post_meta( $enrolment->ID, '_pg_courses_completed', true ) ),
				'Course completed'  => $date( get_post_meta( $enrolment->ID, '_pg_courses_completed_at', true ) ),
			) ),
		);
	}
	foreach ( prospergenics_courses_user_certificates( $user->ID ) as $cert ) {
		$c       = prospergenics_courses_certificate_data( $cert );
		$items[] = array(
			'group_id'          => 'prospergenics-certificates',
			'group_label'       => 'Certificates',
			'group_description' => 'Certificates issued to you. Anyone with the code can verify them on the public verification page.',
			'item_id'           => 'pg-certificate-' . (int) $cert->ID,
			'data'              => $pairs( array(
				'Course'            => $c['course'],
				'Name on certificate' => $c['name'],
				'Instructor'        => $c['instructor'],
				'Code'              => $c['code'],
				'Issued'            => $date( $c['issued_at'] ),
				'Revoked'           => $date( $c['revoked_at'] ),
				'Verification page' => $c['verify_url'],
			) ),
		);
	}
	return array( 'data' => $items, 'done' => true );
}

/**
 * Delete a learner's enrolments (with progress) and certificates; recount the courses.
 *
 * @return int Records deleted.
 */
function prospergenics_courses_erase_user_data( $user_id ) {
	$n       = 0;
	$courses = array();
	$records = get_posts( array(
		'post_type'        => array( 'pg_enrolment', 'pg_certificate' ),
		'post_status'      => 'any',
		'author'           => (int) $user_id,
		'posts_per_page'   => -1,
		'suppress_filters' => true,
		'no_found_rows'    => true,
	) );
	foreach ( $records as $post ) {
		if ( 'pg_enrolment' === $post->post_type ) {
			$courses[] = (int) $post->post_parent;
			wp_cache_delete( (int) $user_id . ':' . (int) $post->post_parent, 'pg_courses_enrolment' );
		}
		if ( wp_delete_post( $post->ID, true ) ) {
			$n++;
		}
	}
	foreach ( array_unique( array_filter( $courses ) ) as $course_id ) {
		prospergenics_courses_update_enrolment_count( $course_id );
	}
	return $n;
}

/** The courses eraser. */
function prospergenics_courses_personal_data_eraser( $email, $page = 1 ) {
	$user = get_user_by( 'email', $email );
	$n    = $user ? prospergenics_courses_erase_user_data( $user->ID ) : 0;
	return array( 'items_removed' => $n > 0, 'items_retained' => false, 'messages' => array(), 'done' => true );
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
	$exporters['prospergenics-courses'] = array(
		'exporter_friendly_name' => 'ProsperGenics courses and certificates',
		'callback'               => 'prospergenics_courses_personal_data_exporter',
	);
	return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
	$erasers['prospergenics-courses'] = array(
		'eraser_friendly_name' => 'ProsperGenics courses and certificates',
		'callback'             => 'prospergenics_courses_personal_data_eraser',
	);
	return $erasers;
} );

// Before WordPress deletes (or reassigns) a user's posts: their learning records go, always.
add_action( 'delete_user', 'prospergenics_courses_erase_user_data', 5 );

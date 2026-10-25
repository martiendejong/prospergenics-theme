<?php
/**
 * Courses track: certificates (card #6361).
 *
 * A certificate is issued once, when a learner's progress first reaches 100%. The record is a
 * private pg_certificate post (author = learner, post_parent = course, post_name = code) with a
 * snapshot of the name, course title, instructor and date, so it stays verifiable even if the
 * course is renamed or deleted later. The PDF is generated on request from that record by the
 * small PDF writer in pdf.php (nothing is written to the public uploads folder, so a learner's
 * certificate can only be downloaded by that learner; anyone with the code can verify it).
 */

defined( 'ABSPATH' ) || exit;

/**
 * Build the certificate PDF. Pure apart from reading the logo file.
 *
 * @param array  $c    name, course, instructor, date (display string), code, verify_url
 * @param string $logo Path to a PNG logo ('' for none).
 * @return string PDF bytes
 */
function prospergenics_courses_certificate_pdf( array $c, $logo = '' ) {
	$c = array_merge( array( 'name' => '', 'course' => '', 'instructor' => '', 'date' => '', 'code' => '', 'verify_url' => '' ), $c );
	$W = 842.0; // A4 landscape
	$H = 595.0;
	$cx  = $W / 2;
	$pdf = new Prospergenics_Courses_Pdf( $W, $H );
	$pdf->set_info( array(
		'Title'   => 'Certificate of completion: ' . $c['course'],
		'Author'  => 'ProsperGenics',
		'Subject' => $c['name'] . ' completed ' . $c['course'] . ' (' . $c['code'] . ')',
	) );

	$ink   = '#0b2016';
	$dark  = '#0f3d28';
	$green = '#1a7a46';
	$muted = '#4a6555';

	$pdf->rect( 0, 0, $W, $H, '#f4faf6' );
	$pdf->rect( 22, 22, $W - 44, $H - 44, '#ffffff', $dark, 2.5 );
	$pdf->rect( 32, 32, $W - 64, $H - 64, null, '#86e0a6', 0.8 );

	$top = $H - 70;
	$img = $logo ? Prospergenics_Courses_Pdf::decode_png( $logo ) : null;
	if ( $img ) {
		$lh = 66.0;
		$lw = $lh * $img['w'] / max( 1, $img['h'] );
		$pdf->image( $img, $cx - $lw / 2, $top - $lh + 14, $lw, $lh );
		$top -= $lh;
	}
	$pdf->text( 'PROSPERGENICS', $cx, $top - 4, 'bold', 11, $green, 'center', 3 );
	$pdf->text( 'CERTIFICATE OF COMPLETION', $cx, $top - 50, 'bold', 26, $ink, 'center', 1.5 );
	$pdf->line( $cx - 50, $top - 66, $cx + 50, $top - 66, '#34c759', 1.5 );
	$pdf->text( 'This certifies that', $cx, $top - 98, 'italic', 14, $muted, 'center' );

	// Name: as large as fits on one line.
	$size = 34.0;
	while ( $size > 16 && Prospergenics_Courses_Pdf::text_width( $c['name'], 'bold', $size ) > 680 ) {
		$size -= 1;
	}
	$pdf->text( $c['name'], $cx, $top - 142, 'bold', $size, $ink, 'center' );
	$pdf->text( 'has successfully completed the course', $cx, $top - 176, 'regular', 14, $muted, 'center' );

	// Course title: up to two lines.
	$tsize = 22.0;
	$lines = Prospergenics_Courses_Pdf::wrap( $c['course'], 'bold', $tsize, 640 );
	while ( count( $lines ) > 2 && $tsize > 14 ) {
		$tsize -= 1;
		$lines  = Prospergenics_Courses_Pdf::wrap( $c['course'], 'bold', $tsize, 640 );
	}
	$y = $top - 212;
	foreach ( array_slice( $lines, 0, 2 ) as $line ) {
		$pdf->text( $line, $cx, $y, 'bold', $tsize, $dark, 'center' );
		$y -= $tsize + 8;
	}
	$meta = array();
	if ( '' !== $c['instructor'] ) {
		$meta[] = 'Taught by ' . $c['instructor'];
	}
	if ( '' !== $c['date'] ) {
		$meta[] = 'Completed on ' . $c['date'];
	}
	if ( $meta ) {
		$pdf->text( implode( '   |   ', $meta ), $cx, $y - 10, 'regular', 12, $muted, 'center' );
	}

	// Footer: verification.
	$pdf->line( 90, 118, $W - 90, 118, '#d9f5e3', 1 );
	if ( '' !== $c['verify_url'] ) {
		$label = 'Verify this certificate at ' . $c['verify_url'];
		$w     = $pdf->text( $label, $cx, 92, 'regular', 10.5, $green, 'center' );
		$pdf->link( $cx - $w / 2, 88, $w, 14, $c['verify_url'] );
	}
	$pdf->text( 'Certificate ID ' . $c['code'], $cx, 72, 'bold', 10.5, $ink, 'center', 0.5 );

	return $pdf->output();
}

/** The certificate record for a code, or null. */
function prospergenics_courses_get_certificate_by_code( $code ) {
	$code = prospergenics_courses_normalize_code( $code );
	if ( ! prospergenics_courses_is_code( $code ) ) {
		return null;
	}
	$posts = get_posts( array(
		'post_type'        => 'pg_certificate',
		'post_status'      => 'publish',
		'name'             => strtolower( $code ),
		'posts_per_page'   => 1,
		'suppress_filters' => true,
	) );
	return $posts ? $posts[0] : null;
}

/** The user's certificate for a course, or null. */
function prospergenics_courses_get_certificate( $user_id, $course_id ) {
	if ( ! $user_id || ! $course_id ) {
		return null;
	}
	$posts = get_posts( array(
		'post_type'        => 'pg_certificate',
		'post_status'      => 'publish',
		'author'           => (int) $user_id,
		'post_parent'      => (int) $course_id,
		'posts_per_page'   => 1,
		'suppress_filters' => true,
	) );
	return $posts ? $posts[0] : null;
}

/** The learner's name as printed on a certificate: first + last name, else display name. */
function prospergenics_courses_learner_name( $user ) {
	$user = is_object( $user ) ? $user : get_userdata( (int) $user );
	if ( ! $user ) {
		return '';
	}
	$full = trim( get_user_meta( $user->ID, 'first_name', true ) . ' ' . get_user_meta( $user->ID, 'last_name', true ) );
	return '' !== $full ? $full : $user->display_name;
}

/**
 * Issue the certificate for a user who completed a course (idempotent: returns the existing
 * one when there is one).
 *
 * @return WP_Post|null
 */
function prospergenics_courses_issue_certificate( $user_id, $course_id ) {
	$existing = prospergenics_courses_get_certificate( $user_id, $course_id );
	if ( $existing ) {
		return $existing;
	}
	$course = get_post( $course_id );
	$user   = get_userdata( $user_id );
	if ( ! $course || 'pg_course' !== $course->post_type || ! $user ) {
		return null;
	}
	do {
		$code = prospergenics_courses_new_code();
	} while ( prospergenics_courses_get_certificate_by_code( $code ) );

	$instructor = get_userdata( (int) $course->post_author );
	$id         = wp_insert_post( array(
		'post_type'   => 'pg_certificate',
		'post_status' => 'publish',
		'post_author' => (int) $user_id,
		'post_parent' => (int) $course_id,
		'post_title'  => 'Certificate ' . $code,
		'post_name'   => strtolower( $code ),
	), true );
	if ( is_wp_error( $id ) ) {
		return null;
	}
	update_post_meta( $id, '_pg_courses_code', $code );
	update_post_meta( $id, '_pg_courses_name', prospergenics_courses_learner_name( $user ) );
	update_post_meta( $id, '_pg_courses_course_title', get_the_title( $course ) );
	update_post_meta( $id, '_pg_courses_instructor', $instructor ? $instructor->display_name : '' );
	update_post_meta( $id, '_pg_courses_issued_at', time() );
	/** Fires after a certificate is issued (e.g. to email the learner or show it on a profile). */
	do_action( 'prospergenics_courses_certificate_issued', $id, (int) $user_id, (int) $course_id );
	return get_post( $id );
}

/** Data printed on (and shown for) a certificate record. */
function prospergenics_courses_certificate_data( $cert ) {
	$code = (string) get_post_meta( $cert->ID, '_pg_courses_code', true );
	return array(
		'code'       => $code,
		'name'       => (string) get_post_meta( $cert->ID, '_pg_courses_name', true ),
		'course'     => (string) get_post_meta( $cert->ID, '_pg_courses_course_title', true ),
		'course_id'  => (int) $cert->post_parent,
		'instructor' => (string) get_post_meta( $cert->ID, '_pg_courses_instructor', true ),
		'issued_at'  => (int) get_post_meta( $cert->ID, '_pg_courses_issued_at', true ),
		'date'       => wp_date( 'j F Y', (int) get_post_meta( $cert->ID, '_pg_courses_issued_at', true ) ),
		'verify_url' => prospergenics_courses_verify_url( $code ),
	);
}

function prospergenics_courses_verify_url( $code ) {
	return home_url( '/courses/verify/' . rawurlencode( $code ) . '/' );
}

/** Where the learner downloads their certificate for a course. */
function prospergenics_courses_certificate_url( $course ) {
	return home_url( '/courses/' . get_post_field( 'post_name', $course ) . '/certificate/' );
}

/**
 * /courses/<slug>/certificate/: stream the logged-in learner's own certificate as a PDF.
 * Issues it first if the learner is at 100% but has none yet (e.g. lessons were removed).
 */
function prospergenics_courses_serve_certificate( $course ) {
	if ( ! is_user_logged_in() ) {
		wp_safe_redirect( wp_login_url( prospergenics_courses_certificate_url( $course ) ) );
		exit;
	}
	$user_id = get_current_user_id();
	$cert    = prospergenics_courses_get_certificate( $user_id, $course->ID );
	if ( ! $cert && prospergenics_courses_is_enrolled( $user_id, $course->ID ) && 100 === prospergenics_courses_progress( $user_id, $course->ID ) ) {
		$cert = prospergenics_courses_issue_certificate( $user_id, $course->ID );
	}
	if ( ! $cert ) {
		status_header( 403 );
		nocache_headers();
		wp_die( esc_html__( 'There is no certificate for you for this course yet. Complete every lesson to earn it.', 'prospergenics-2026' ), esc_html__( 'No certificate yet', 'prospergenics-2026' ), array( 'response' => 403, 'back_link' => true ) );
	}
	$pdf = prospergenics_courses_certificate_pdf( prospergenics_courses_certificate_data( $cert ), get_template_directory() . '/assets/logo.png' );
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="prospergenics-certificate-' . sanitize_file_name( $course->post_name ) . '.pdf"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	header( 'X-Robots-Tag: noindex, nofollow' );
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput -- binary PDF
	exit;
}

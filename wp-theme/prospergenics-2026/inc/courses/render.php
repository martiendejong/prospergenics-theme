<?php
/**
 * Courses track: markup helpers shared by the templates (and usable by other tracks, e.g. a
 * member dashboard or a profile's instructor tab: prospergenics_courses_render_my_courses()
 * and prospergenics_courses_render_instructor_panel()). Every helper returns escaped HTML.
 */

defined( 'ABSPATH' ) || exit;

/** Accessible progress bar. */
function prospergenics_courses_progress_bar( $percent, $label = 'Course progress' ) {
	$percent = max( 0, min( 100, (int) $percent ) );
	return sprintf(
		'<div class="pgc-progress"><div class="pgc-progress-label"><span>%1$s</span><span>%2$d%% complete</span></div><div class="pgc-progress-track" role="progressbar" aria-label="%1$s" aria-valuemin="0" aria-valuemax="100" aria-valuenow="%2$d" aria-valuetext="%2$d%% complete"><span class="pgc-progress-fill" style="width:%2$d%%"></span></div></div>',
		esc_html( $label ),
		$percent
	);
}

/**
 * Course thumbnail, or a branded pattern (one of three, by category) instead of a big letter
 * (audit UX-8). Decorative: the title is always next to it.
 */
function prospergenics_courses_thumb( $course, $size = 'medium_large' ) {
	if ( has_post_thumbnail( $course ) ) {
		return get_the_post_thumbnail( $course, $size, array( 'alt' => '', 'loading' => 'lazy', 'class' => 'pgc-thumb-img' ) );
	}
	$course  = get_post( $course );
	$terms   = $course ? prospergenics_courses_categories( $course->ID ) : array();
	$variant = ( $terms ? (int) $terms[0]->term_id : 0 ) % 3 + 1;
	return '<span class="pgc-thumb-placeholder pg-pattern pg-pattern--' . $variant . '" aria-hidden="true"></span>';
}

function prospergenics_courses_level_badge( $course_id ) {
	$level = prospergenics_courses_level( $course_id );
	if ( '' === $level ) {
		return '';
	}
	$levels = prospergenics_courses_levels();
	return '<span class="pgc-level pgc-level--' . esc_attr( $level ) . '">' . esc_html( $levels[ $level ] ) . '</span>';
}

function prospergenics_courses_lesson_count_label( $n ) {
	return sprintf( _n( '%d lesson', '%d lessons', $n, 'prospergenics-2026' ), $n );
}

/** A course card for grids (listing, my courses, instructor page). */
function prospergenics_courses_card( $course, $user_id = 0, $heading = 'h2' ) {
	$course     = get_post( $course );
	$instructor = get_userdata( (int) $course->post_author );
	$lessons    = count( prospergenics_courses_lesson_ids( $course->ID ) );
	$cats       = prospergenics_courses_categories( $course->ID );
	$heading    = in_array( $heading, array( 'h2', 'h3' ), true ) ? $heading : 'h2';
	$html       = '<article class="pgc-card">';
	$html      .= '<div class="pgc-card-media">' . prospergenics_courses_thumb( $course ) . '</div>';
	$html      .= '<div class="pgc-card-body">';
	$chips      = '';
	if ( $cats ) {
		$chips .= '<span class="pgc-chip">' . esc_html( $cats[0]->name ) . '</span>';
	}
	$chips .= prospergenics_courses_level_badge( $course->ID );
	if ( $chips ) {
		$html .= '<div class="pgc-chips">' . $chips . '</div>';
	}
	$html .= sprintf( '<%1$s class="pgc-card-title"><a href="%2$s">%3$s</a></%1$s>', $heading, esc_url( get_permalink( $course ) ), esc_html( get_the_title( $course ) ) );
	if ( $instructor ) {
		$html .= '<p class="pgc-card-by">By ' . esc_html( $instructor->display_name ) . '</p>';
	}
	$html .= '<ul class="pgc-card-meta"><li>' . esc_html( prospergenics_courses_lesson_count_label( $lessons ) ) . '</li><li>' . esc_html( prospergenics_courses_format_price( prospergenics_courses_price( $course->ID ) ) ) . '</li></ul>';
	if ( $user_id && prospergenics_courses_is_enrolled( $user_id, $course->ID ) ) {
		$html .= prospergenics_courses_progress_bar( prospergenics_courses_progress( $user_id, $course->ID ), 'Your progress' );
	}
	$html .= '</div></article>';
	return $html;
}

/** The one-line notice after a form action (?pgc=...). */
function prospergenics_courses_notice_html() {
	// phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted value
	$key     = isset( $_GET['pgc'] ) ? sanitize_key( wp_unslash( $_GET['pgc'] ) ) : '';
	$notices = prospergenics_courses_notices();
	if ( ! isset( $notices[ $key ] ) ) {
		return '';
	}
	$error = in_array( $key, array( 'error', 'paid', 'quiz_required', 'quiz_rate' ), true );
	return '<div class="pgc-notice' . ( $error ? ' pgc-notice--error' : '' ) . '" role="' . ( $error ? 'alert' : 'status' ) . '">' . esc_html( $notices[ $key ] ) . '</div>';
}

/** A POST form to admin-post.php with a nonce. $fields: name => value. */
function prospergenics_courses_action_form( $action, $nonce_action, array $fields, $button, $class = 'pgc-btn', $attrs = '', $sr_suffix = '' ) {
	$html = '<form class="pgc-inline-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"' . $attrs . '>';
	$html .= '<input type="hidden" name="action" value="' . esc_attr( $action ) . '" />';
	foreach ( $fields as $name => $value ) {
		$html .= '<input type="hidden" name="' . esc_attr( $name ) . '" value="' . esc_attr( $value ) . '" />';
	}
	$html .= wp_nonce_field( $nonce_action, '_wpnonce', false, false );
	$sr    = '' !== $sr_suffix ? '<span class="screen-reader-text"> ' . esc_html( $sr_suffix ) . '</span>' : '';
	$html .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $button ) . $sr . '</button></form>';
	return $html;
}

/**
 * The call to action on a course page: log in / enrol / continue / paid notice, plus progress and
 * certificate for enrolled learners.
 */
function prospergenics_courses_enrol_cta( $course, $user_id ) {
	$html = '<div class="pgc-cta">';
	if ( $user_id && prospergenics_courses_is_enrolled( $user_id, $course->ID ) ) {
		$progress = prospergenics_courses_progress( $user_id, $course->ID );
		$resume   = prospergenics_courses_resume_lesson( $user_id, $course->ID );
		$html    .= prospergenics_courses_progress_bar( $progress );
		$html    .= '<div class="pgc-cta-row">';
		if ( $resume ) {
			$label = 0 === $progress ? 'Start the course' : ( 100 === $progress ? 'Review the course' : 'Continue learning' );
			$html .= '<a class="pgc-btn" href="' . esc_url( get_permalink( $resume ) ) . '">' . esc_html( $label ) . '</a>';
		}
		$html .= prospergenics_courses_certificate_html( $course, $user_id, $progress, 'pgc-btn pgc-btn--ghost', 'Download certificate (PDF)' );
		$html .= '</div>';
	} elseif ( ! prospergenics_courses_is_free( $course->ID ) ) {
		$html .= '<p class="pgc-cta-note"><strong>' . esc_html( prospergenics_courses_format_price( prospergenics_courses_price( $course->ID ) ) ) . '</strong> · Enrolment in paid courses opens soon.</p>';
	} elseif ( ! $user_id ) {
		$html .= '<a class="pgc-btn" href="' . esc_url( wp_login_url( get_permalink( $course ) ) ) . '">Log in to enrol</a>';
		$html .= '<p class="pgc-cta-note">Free course. Enrol with one click once you are logged in.</p>';
	} else {
		$html .= prospergenics_courses_action_form( 'pg_courses_enrol', 'pg_courses_enrol_' . $course->ID, array( 'course_id' => $course->ID ), 'Enrol for free' );
		$html .= '<p class="pgc-cta-note">Free course. One click and you can start.</p>';
	}
	return $html . '</div>';
}

/**
 * The certificate link for an enrolled learner: the download button once earned, why there is
 * none when the email address is unconfirmed (with a way to resend the link) or the certificate
 * was revoked, and nothing before 100%.
 */
function prospergenics_courses_certificate_html( $course, $user_id, $progress, $class, $label, $sr_suffix = '' ) {
	$cert = prospergenics_courses_get_certificate( $user_id, $course->ID );
	if ( $cert && prospergenics_courses_certificate_is_revoked( $cert ) ) {
		return '<p class="pgc-cert-note">Your certificate for this course was revoked.</p>';
	}
	if ( ! $cert && 100 === $progress && 'unverified' === prospergenics_courses_certificate_block_reason( $user_id ) ) {
		$user  = get_userdata( $user_id );
		$html  = '<div class="pgc-cert-note" role="note"><p><strong>Confirm your email address to receive your certificate.</strong> Certificates are only issued to confirmed accounts. Open the link we emailed to ' . esc_html( $user ? $user->user_email : 'you' ) . ', then come back here.</p>';
		if ( function_exists( 'prospergenics_profiles_resend_verification' ) ) {
			$html .= '<form class="pgc-inline-form" method="post" action="' . esc_url( home_url( '/profile/verify/' ) ) . '">' . wp_nonce_field( 'pg_profiles_resend', '_pgp_nonce', false, false );
			$html .= '<input type="hidden" name="pgp_action" value="resend" /><button type="submit" class="pgc-btn pgc-btn--ghost pgc-btn--small">Send the confirmation link again</button></form>';
		}
		return $html . '</div>';
	}
	if ( ! $cert && 100 !== $progress ) {
		return '';
	}
	$sr = '' !== $sr_suffix ? '<span class="screen-reader-text"> ' . esc_html( $sr_suffix ) . '</span>' : '';
	return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( prospergenics_courses_certificate_url( $course ) ) . '">' . esc_html( $label ) . $sr . '</a>';
}

/** Small inline SVG icons (decorative). */
function prospergenics_courses_icon( $name ) {
	$paths = array(
		'check' => '<path d="M4 10.5l4 4 8-9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/>',
		'lock'  => '<rect x="4.5" y="9" width="11" height="8" rx="2" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M7 9V6.5a3 3 0 016 0V9" fill="none" stroke="currentColor" stroke-width="1.8"/>',
		'play'  => '<circle cx="10" cy="10" r="7.5" fill="none" stroke="currentColor" stroke-width="1.8"/><path d="M8.5 7v6l5-3z" fill="currentColor"/>',
	);
	return isset( $paths[ $name ] ) ? '<svg class="pgc-icon" viewBox="0 0 20 20" width="18" height="18" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>' : '';
}

/**
 * The module/lesson outline. $mode 'accordion' (course page: <details> per module) or 'nav'
 * (lesson sidebar). Lessons link only for users who can open them.
 */
function prospergenics_courses_curriculum_html( $course, $user_id, $current_id = 0, $mode = 'accordion' ) {
	$curriculum = prospergenics_courses_curriculum( $course->ID );
	if ( ! $curriculum ) {
		return '<p class="pgc-muted">The curriculum is being prepared.</p>';
	}
	$done  = array_flip( prospergenics_courses_completed_ids( $user_id, $course->ID ) );
	$html  = '';
	$count = 0;
	foreach ( $curriculum as $i => $m ) {
		$has_current = false;
		$items       = '';
		$m_done      = 0;
		foreach ( $m['lessons'] as $l ) {
			$count++;
			$is_done    = isset( $done[ $l->ID ] );
			$m_done    += $is_done ? 1 : 0;
			$is_current = (int) $l->ID === (int) $current_id;
			$has_current = $has_current || $is_current;
			$can        = prospergenics_courses_can_view_lesson( $l, $user_id );
			$state      = $is_done ? '<span class="screen-reader-text"> (completed)</span>' : '';
			$icon       = $is_done ? prospergenics_courses_icon( 'check' ) : ( $can ? prospergenics_courses_icon( 'play' ) : prospergenics_courses_icon( 'lock' ) );
			$label      = '<span class="pgc-lesson-num">' . $count . '.</span> <span class="pgc-lesson-title">' . esc_html( get_the_title( $l ) ) . '</span>' . $state;
			$cls        = 'pgc-lesson' . ( $is_done ? ' is-done' : '' ) . ( $is_current ? ' is-current' : '' );
			if ( $can ) {
				$items .= '<li class="' . $cls . '"><a href="' . esc_url( get_permalink( $l ) ) . '"' . ( $is_current ? ' aria-current="page"' : '' ) . '>' . $icon . $label . '</a></li>';
			} else {
				$items .= '<li class="' . $cls . ' is-locked"><span class="pgc-lesson-static">' . $icon . $label . '<span class="screen-reader-text"> (enrol to open)</span></span></li>';
			}
		}
		$meta = esc_html( prospergenics_courses_lesson_count_label( count( $m['lessons'] ) ) );
		if ( $user_id && $m['lessons'] && prospergenics_courses_is_enrolled( $user_id, $course->ID ) ) {
			$meta .= ' · ' . (int) $m_done . '/' . count( $m['lessons'] ) . ' done';
		}
		$open  = 'nav' === $mode ? ( $has_current || ! $current_id ) : 0 === $i;
		$html .= '<details class="pgc-module"' . ( $open ? ' open' : '' ) . '><summary><span class="pgc-module-title">' . esc_html( get_the_title( $m['module'] ) ) . '</span><span class="pgc-module-meta">' . $meta . '</span></summary>';
		$html .= $items ? '<ol class="pgc-lessons">' . $items . '</ol>' : '<p class="pgc-muted pgc-module-empty">No lessons yet.</p>';
		$html .= '</details>';
	}
	return '<div class="pgc-curriculum pgc-curriculum--' . esc_attr( $mode ) . '">' . $html . '</div>';
}

/** The instructor block on a course page. */
function prospergenics_courses_instructor_block( $user_id ) {
	$user = get_userdata( $user_id );
	if ( ! $user ) {
		return '';
	}
	$bio     = get_the_author_meta( 'description', $user->ID );
	$profile = apply_filters( 'prospergenics_courses_instructor_profile_url', '', $user->ID );
	$html    = '<div class="pgc-instructor">' . ( function_exists( 'prospergenics_2026_avatar_html' ) ? prospergenics_2026_avatar_html( $user->ID, 96, 'pgc-avatar' ) : get_avatar( $user->ID, 96, '', '', array( 'class' => 'pgc-avatar' ) ) );
	$html   .= '<div><h3 class="pgc-instructor-name"><a href="' . esc_url( prospergenics_courses_instructor_url( $user->ID ) ) . '">' . esc_html( $user->display_name ) . '</a></h3>';
	$html   .= $bio ? '<p>' . esc_html( $bio ) . '</p>' : '<p class="pgc-muted">Instructor at ProsperGenics.</p>';
	$html   .= '<p class="pgc-links"><a href="' . esc_url( prospergenics_courses_instructor_url( $user->ID ) ) . '">All courses by ' . esc_html( $user->display_name ) . '</a>';
	if ( $profile ) {
		$html .= ' · <a href="' . esc_url( $profile ) . '">Profile</a>';
	}
	return $html . '</p></div></div>';
}

/** Instructor statistics for the public page: published courses and distinct students. */
function prospergenics_courses_instructor_summary( $user_id ) {
	$courses  = get_posts( array( 'post_type' => 'pg_course', 'post_status' => 'publish', 'author' => (int) $user_id, 'posts_per_page' => 100, 'orderby' => 'date', 'order' => 'DESC' ) );
	$students = array();
	if ( $courses ) {
		$enrolments = get_posts( array( 'post_type' => 'pg_enrolment', 'post_status' => 'publish', 'post_parent__in' => wp_list_pluck( $courses, 'ID' ), 'posts_per_page' => -1, 'suppress_filters' => true ) );
		foreach ( $enrolments as $e ) {
			$students[ (int) $e->post_author ] = true;
		}
	}
	return array( 'courses' => $courses, 'students' => count( $students ) );
}

/** Reusable "courses taught" panel (instructor page; a profile's instructor tab). */
function prospergenics_courses_render_instructor_panel( $user_id ) {
	$s    = prospergenics_courses_instructor_summary( $user_id );
	$html = '<div class="pgc-stats" role="list">';
	$html .= '<div class="pgc-stat" role="listitem"><span class="pgc-stat-num">' . count( $s['courses'] ) . '</span><span class="pgc-stat-label">' . esc_html( _n( 'course', 'courses', count( $s['courses'] ), 'prospergenics-2026' ) ) . '</span></div>';
	$html .= '<div class="pgc-stat" role="listitem"><span class="pgc-stat-num">' . (int) $s['students'] . '</span><span class="pgc-stat-label">' . esc_html( _n( 'student', 'students', $s['students'], 'prospergenics-2026' ) ) . '</span></div>';
	$html .= '<div class="pgc-stat" role="listitem"><span class="pgc-stat-num" aria-hidden="true">–</span><span class="pgc-stat-label">Rating<span class="screen-reader-text">: not available yet</span> <small>(coming soon)</small></span></div>';
	$html .= '</div>';
	if ( ! $s['courses'] ) {
		return $html . '<p class="pgc-muted">No published courses yet.</p>';
	}
	$html .= '<div class="pgc-grid">';
	foreach ( $s['courses'] as $c ) {
		$card  = prospergenics_courses_card( $c, get_current_user_id(), 'h3' );
		$count = (int) get_post_meta( $c->ID, '_pg_courses_enrolment_count', true );
		$card  = str_replace( '</ul>', '<li>' . esc_html( sprintf( _n( '%d enrolled', '%d enrolled', $count, 'prospergenics-2026' ), $count ) ) . '</li></ul>', $card );
		$html .= $card;
	}
	return $html . '</div>';
}

/** Reusable "my courses" list (the /courses/my/ page; a future member dashboard). */
function prospergenics_courses_render_my_courses( $user_id ) {
	$rows = array();
	foreach ( prospergenics_courses_user_enrolments( $user_id ) as $e ) {
		$course = get_post( $e->post_parent );
		if ( $course && 'publish' === $course->post_status ) {
			$rows[] = $course;
		}
	}
	if ( ! $rows ) {
		return '<p class="pgc-empty">You are not enrolled in any course yet. <a href="' . esc_url( home_url( '/courses/' ) ) . '">Browse the courses</a>.</p>';
	}
	$html = '<ul class="pgc-my-list">';
	foreach ( $rows as $course ) {
		$progress = prospergenics_courses_progress( $user_id, $course->ID );
		$resume   = prospergenics_courses_resume_lesson( $user_id, $course->ID );
		$cert     = prospergenics_courses_get_certificate( $user_id, $course->ID );
		$html    .= '<li class="pgc-my-item"><div class="pgc-my-media">' . prospergenics_courses_thumb( $course, 'thumbnail' ) . '</div><div class="pgc-my-body">';
		$html    .= '<h2 class="pgc-my-title"><a href="' . esc_url( get_permalink( $course ) ) . '">' . esc_html( get_the_title( $course ) ) . '</a></h2>';
		$html    .= prospergenics_courses_progress_bar( $progress, 'Progress' );
		$html    .= '<div class="pgc-cta-row">';
		if ( $resume ) {
			$html .= '<a class="pgc-btn pgc-btn--small" href="' . esc_url( get_permalink( $resume ) ) . '">' . ( 100 === $progress ? 'Review' : 'Continue' ) . '<span class="screen-reader-text"> ' . esc_html( get_the_title( $course ) ) . '</span></a>';
		}
		$html .= prospergenics_courses_certificate_html( $course, $user_id, $progress, 'pgc-btn pgc-btn--ghost pgc-btn--small', 'Certificate (PDF)', 'for ' . get_the_title( $course ) );
		if ( $cert && ! prospergenics_courses_certificate_is_revoked( $cert ) ) {
			$code  = (string) get_post_meta( $cert->ID, '_pg_courses_code', true );
			$html .= '<a class="pgc-link-small" href="' . esc_url( prospergenics_courses_verify_url( $code ) ) . '">Verification page</a>';
		}
		$html .= '</div></div></li>';
	}
	return $html . '</ul>';
}

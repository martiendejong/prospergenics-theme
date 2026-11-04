<?php
/**
 * Courses track: weekly learning reminders (audit ARC-20, card #6512).
 *
 * Once a week (WP-Cron event prospergenics_courses_reminders, Tuesdays from 07:00 UTC) learners
 * who are enrolled in a course they have not finished, and have not opened a lesson of it for 7
 * days, get one email: per such course the next lesson, their progress and a link.
 *
 *   - at most one email per learner per calendar week (ISO week, UTC), however often the job runs;
 *   - nothing about completed courses, nothing to members who have not confirmed their email
 *     address, nothing to suspended members;
 *   - members turn it off under /profile/settings/ ("Weekly learning reminders", on by default),
 *     and every email carries a one-click unsubscribe link: /courses/reminders/stop/?u=<id>&t=<token>,
 *     the token an HMAC of the member's ID under the site's auth salt (also sent as
 *     List-Unsubscribe / List-Unsubscribe-Post for mail clients);
 *   - "last opened a lesson" is _pg_courses_last_active on the enrolment, written when a lesson is
 *     opened (at most hourly, prospergenics_courses_touch_lesson()); older enrolments fall back to
 *     the first-opened and completed times and the enrolment date.
 *
 * Mail goes through the platform mail helper (inc/profiles/mail.php). The preference and the last
 * reminder are in the courses personal data export and are erased with it (privacy.php).
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_COURSES_REMINDERS_HOOK     = 'prospergenics_courses_reminders';
const PROSPERGENICS_COURSES_REMINDERS_OFF_META = '_pg_courses_reminders_off';   // when the member turned them off (absent = on)
const PROSPERGENICS_COURSES_REMINDER_WEEK_META = '_pg_courses_reminder_week';   // ISO week of the last reminder, e.g. 2026-W45
const PROSPERGENICS_COURSES_REMINDER_SENT_META = '_pg_courses_reminder_sent';   // when the last reminder was sent
const PROSPERGENICS_COURSES_LAST_ACTIVE_META   = '_pg_courses_last_active';     // on the enrolment: when a lesson was last opened
const PROSPERGENICS_COURSES_REMINDER_IDLE      = 7 * DAY_IN_SECONDS;

/* ───────────────────────── the rules (pure) ───────────────────────── */

/** The calendar week a time falls in (ISO 8601, UTC), e.g. '2026-W45'. */
function prospergenics_courses_reminder_week( $time ) {
	return gmdate( 'o-\WW', (int) $time );
}

/** When the learner last did something in a course: the latest of the times given (0 for none). */
function prospergenics_courses_reminder_last_activity( $enrolled_at, $last_active, array $opened, array $completed ) {
	return (int) max( array_merge( array( 0, (int) $enrolled_at, (int) $last_active ), array_map( 'intval', $opened ), array_map( 'intval', $completed ) ) );
}

/**
 * Whom to remind about what.
 *
 * @param array $learner    {verified:bool, suspended:bool, opted_out:bool, last_week:string}
 * @param array $enrolments key => {completed:bool, lessons:int, last_activity:int}
 * @return array{send:bool,reason:string,due:array} reason '' when sending; else suspended,
 *         unverified, opted_out, this_week or nothing_due. due: the keys to write about.
 */
function prospergenics_courses_reminder_plan( array $learner, array $enrolments, $now ) {
	$no = function ( $reason ) {
		return array( 'send' => false, 'reason' => $reason, 'due' => array() );
	};
	if ( ! empty( $learner['suspended'] ) ) {
		return $no( 'suspended' );
	}
	if ( empty( $learner['verified'] ) ) {
		return $no( 'unverified' );
	}
	if ( ! empty( $learner['opted_out'] ) ) {
		return $no( 'opted_out' );
	}
	if ( isset( $learner['last_week'] ) && prospergenics_courses_reminder_week( $now ) === (string) $learner['last_week'] ) {
		return $no( 'this_week' );
	}
	$due = array();
	foreach ( $enrolments as $key => $e ) {
		if ( empty( $e['completed'] ) && (int) $e['lessons'] > 0 && (int) $e['last_activity'] <= (int) $now - PROSPERGENICS_COURSES_REMINDER_IDLE ) {
			$due[] = $key;
		}
	}
	return $due ? array( 'send' => true, 'reason' => '', 'due' => $due ) : $no( 'nothing_due' );
}

/** The first run: the next Tuesday 07:00 UTC (10:00 in Nairobi) after $now. */
function prospergenics_courses_reminders_first_run( $now ) {
	$next = strtotime( 'tuesday 07:00 UTC', (int) $now );
	return $next > $now ? $next : strtotime( 'next tuesday 07:00 UTC', (int) $now );
}

/** The email text (plain): one block per course. */
function prospergenics_courses_reminder_body( $name, array $courses, $stop_url, $settings_url ) {
	$blocks = array();
	foreach ( $courses as $c ) {
		$blocks[] = sprintf(
			"%s\nYour progress: %d of %d lessons (%d%%)\nNext lesson: %s\n%s",
			$c['course'],
			(int) $c['done'],
			(int) $c['lessons'],
			(int) $c['progress'],
			$c['lesson'],
			$c['url']
		);
	}
	$intro = 1 === count( $courses )
		? 'You have not opened a lesson of your course for a week. Here is where you left off:'
		: 'You have not opened a lesson of these courses for a week. Here is where you left off:';
	return sprintf(
		"Hi %s,\n\n%s\n\n%s\n\nAll your courses: %s\n\n--\nYou get this at most once a week while you have a course to finish.\nStop these reminders (one click): %s\nOr change it under your settings: %s\n",
		$name,
		$intro,
		implode( "\n\n", $blocks ),
		home_url( '/courses/my/' ),
		$stop_url,
		$settings_url
	);
}

/* ───────────────────────── the preference ───────────────────────── */

function prospergenics_courses_reminders_enabled( $user_id ) {
	return '' === (string) get_user_meta( (int) $user_id, PROSPERGENICS_COURSES_REMINDERS_OFF_META, true );
}

function prospergenics_courses_set_reminders( $user_id, $on ) {
	$user_id = (int) $user_id;
	if ( $on ) {
		delete_user_meta( $user_id, PROSPERGENICS_COURSES_REMINDERS_OFF_META );
	} elseif ( prospergenics_courses_reminders_enabled( $user_id ) ) {
		update_user_meta( $user_id, PROSPERGENICS_COURSES_REMINDERS_OFF_META, time() );
	}
}

/** The unsubscribe token of a member (HMAC under the site's auth salt; no expiry). */
function prospergenics_courses_reminders_token( $user_id ) {
	return hash_hmac( 'sha256', 'pg-courses-reminders|' . (int) $user_id, wp_salt( 'auth' ) );
}

function prospergenics_courses_reminders_check_token( $user_id, $token ) {
	return (int) $user_id > 0 && is_string( $token ) && '' !== $token && hash_equals( prospergenics_courses_reminders_token( $user_id ), $token );
}

/** The one-click unsubscribe link of a member. */
function prospergenics_courses_reminders_stop_url( $user_id ) {
	return add_query_arg( array( 'u' => (int) $user_id, 't' => prospergenics_courses_reminders_token( $user_id ) ), home_url( '/courses/reminders/stop/' ) );
}

/* ───────────────────────── the job ───────────────────────── */

/**
 * Send this week's reminders.
 *
 * @param int|null $now For tests and wp-cli; the current time by default.
 * @return array{sent:int,failed:int,skipped:array<string,int>}
 */
function prospergenics_courses_send_reminders( $now = null ) {
	$now   = null === $now ? time() : (int) $now;
	$stats = array( 'sent' => 0, 'failed' => 0, 'skipped' => array() );

	// Open enrolments (not completed), a page at a time, grouped by learner.
	$by_user = array();
	$page    = 1;
	do {
		$batch = get_posts( array(
			'post_type'        => 'pg_enrolment',
			'post_status'      => 'publish',
			'posts_per_page'   => 200,
			'paged'            => $page++,
			'orderby'          => 'ID',
			'order'            => 'ASC',
			'no_found_rows'    => true,
			'suppress_filters' => true,
			'meta_query'       => array(
				'relation' => 'OR',
				array( 'key' => '_pg_courses_completed_at', 'compare' => 'NOT EXISTS' ),
				array( 'key' => '_pg_courses_completed_at', 'value' => array( '', '0' ), 'compare' => 'IN' ),
			),
		) );
		foreach ( $batch as $e ) {
			$by_user[ (int) $e->post_author ][] = $e;
		}
	} while ( count( $batch ) === 200 );
	if ( ! $by_user ) {
		return $stats;
	}
	cache_users( array_keys( $by_user ) );

	$lessons = array(); // course id => WP_Post[] (published course), or null
	foreach ( $by_user as $uid => $enrolments ) {
		$user = get_userdata( $uid );
		if ( ! $user ) {
			continue;
		}
		$rows = array();
		foreach ( $enrolments as $e ) {
			$cid = (int) $e->post_parent;
			if ( ! array_key_exists( $cid, $lessons ) ) {
				$lessons[ $cid ] = 'publish' === get_post_status( $cid ) ? prospergenics_courses_flat_lessons( $cid ) : null;
			}
			if ( null === $lessons[ $cid ] ) {
				continue; // the course is no longer published
			}
			wp_cache_set( $uid . ':' . $cid, (int) $e->ID, 'pg_courses_enrolment' ); // the helpers below need no query
			$completed    = get_post_meta( $e->ID, '_pg_courses_completed', true );
			$opened       = get_post_meta( $e->ID, '_pg_courses_opened', true );
			$completed    = is_array( $completed ) ? $completed : array();
			$ids          = array_map( function ( $p ) { return (int) $p->ID; }, $lessons[ $cid ] );
			$done_ids     = array_intersect( $ids, array_map( 'intval', array_keys( $completed ) ) );
			$rows[ $cid ] = array(
				'completed'     => $ids && count( $done_ids ) >= count( $ids ),
				'lessons'       => count( $ids ),
				'done'          => count( $done_ids ),
				'progress'      => prospergenics_courses_progress_percent( $ids, $done_ids ),
				'last_activity' => prospergenics_courses_reminder_last_activity( strtotime( $e->post_date_gmt . ' UTC' ), get_post_meta( $e->ID, PROSPERGENICS_COURSES_LAST_ACTIVE_META, true ), is_array( $opened ) ? $opened : array(), $completed ),
			);
		}
		$plan = prospergenics_courses_reminder_plan( array(
			'verified'  => prospergenics_profiles_is_verified( $uid ),
			'suspended' => function_exists( 'prospergenics_profiles_is_suspended' ) && prospergenics_profiles_is_suspended( $uid ),
			'opted_out' => ! prospergenics_courses_reminders_enabled( $uid ),
			'last_week' => (string) get_user_meta( $uid, PROSPERGENICS_COURSES_REMINDER_WEEK_META, true ),
		), $rows, $now );
		if ( ! $plan['send'] ) {
			$stats['skipped'][ $plan['reason'] ] = ( isset( $stats['skipped'][ $plan['reason'] ] ) ? $stats['skipped'][ $plan['reason'] ] : 0 ) + 1;
			continue;
		}
		$courses = array();
		foreach ( $plan['due'] as $cid ) {
			$next = prospergenics_courses_resume_lesson( $uid, $cid );
			if ( ! $next ) {
				continue;
			}
			$courses[] = array(
				'course'   => wp_strip_all_tags( get_the_title( $cid ) ),
				'lesson'   => wp_strip_all_tags( get_the_title( $next ) ),
				'url'      => get_permalink( $next ),
				'done'     => $rows[ $cid ]['done'],
				'lessons'  => $rows[ $cid ]['lessons'],
				'progress' => $rows[ $cid ]['progress'],
			);
		}
		if ( ! $courses ) {
			continue;
		}
		$stop    = prospergenics_courses_reminders_stop_url( $uid );
		$subject = 1 === count( $courses ) ? sprintf( 'Your next lesson in %s', $courses[0]['course'] ) : 'Your next lessons on ProsperGenics';
		$body    = prospergenics_courses_reminder_body( $user->display_name, $courses, $stop, home_url( '/profile/settings/#pgp-email' ) );
		$headers = array( 'List-Unsubscribe: <' . $stop . '>', 'List-Unsubscribe-Post: List-Unsubscribe=One-Click' );
		// Recorded before sending: a failed send is not retried until next week either.
		update_user_meta( $uid, PROSPERGENICS_COURSES_REMINDER_WEEK_META, prospergenics_courses_reminder_week( $now ) );
		update_user_meta( $uid, PROSPERGENICS_COURSES_REMINDER_SENT_META, $now );
		$r = prospergenics_profiles_send_mail( $user->user_email, $subject, $body, 'course_reminder', $uid, $headers );
		$stats[ is_wp_error( $r ) ? 'failed' : 'sent' ]++;
	}
	return $stats;
}
add_action( PROSPERGENICS_COURSES_REMINDERS_HOOK, 'prospergenics_courses_send_reminders', 10, 0 );

add_action( 'init', function () {
	if ( ! wp_next_scheduled( PROSPERGENICS_COURSES_REMINDERS_HOOK ) ) {
		wp_schedule_event( prospergenics_courses_reminders_first_run( time() ), 'weekly', PROSPERGENICS_COURSES_REMINDERS_HOOK );
	}
} );

add_action( 'switch_theme', function () {
	wp_clear_scheduled_hook( PROSPERGENICS_COURSES_REMINDERS_HOOK );
} );

/* ───────────────────────── /courses/reminders/stop/ ───────────────────────── */

/**
 * The unsubscribe link: a valid link turns the reminders off when opened (GET, or the mail
 * client's one-click POST); the page offers to turn them back on (POST with the same token).
 * An invalid link changes nothing. The result is left in $GLOBALS for courses-reminders.php.
 */
function prospergenics_courses_reminders_handle_stop() {
	if ( 'reminders' !== prospergenics_courses_view() ) {
		return;
	}
	// phpcs:disable WordPress.Security.NonceVerification -- the signed token is the authority here
	// (the page's form posts u and t; a mail client's one-click POST keeps them in the link)
	$arg   = function ( $key ) {
		$v = isset( $_POST[ $key ] ) ? $_POST[ $key ] : ( isset( $_GET[ $key ] ) ? $_GET[ $key ] : '' );
		return is_string( $v ) ? sanitize_text_field( wp_unslash( $v ) ) : '';
	};
	$uid   = absint( $arg( 'u' ) );
	$token = $arg( 't' );
	$want  = isset( $_POST['pgc_reminders'] ) && 'on' === $_POST['pgc_reminders'] ? 'on' : 'off';
	// phpcs:enable
	if ( ! get_userdata( $uid ) || ! prospergenics_courses_reminders_check_token( $uid, $token ) ) {
		$GLOBALS['pg_courses_reminders_result'] = array( 'state' => 'invalid', 'user' => 0, 'token' => '' );
		return;
	}
	prospergenics_courses_set_reminders( $uid, 'on' === $want );
	$GLOBALS['pg_courses_reminders_result'] = array( 'state' => $want, 'user' => $uid, 'token' => $token );
}
add_action( 'template_redirect', 'prospergenics_courses_reminders_handle_stop', 4 );

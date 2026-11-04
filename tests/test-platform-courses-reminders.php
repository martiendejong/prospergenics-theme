<?php
/**
 * Standalone test for the weekly learning reminders (courses track, ARC-20 / card #6512):
 * wp-theme/prospergenics-2026/inc/courses/reminders.php, with the real enrolment.php and helpers.php.
 *
 * Checks:
 *   - the rules: idle for 7 days (enrolment date, first opened, completed and last opened all count),
 *     never about a completed course or one without lessons, never to unverified, suspended or
 *     opted-out members, at most one email per learner per calendar week;
 *   - the weekly job against a tiny in-memory WordPress: one email per learner (all their idle
 *     courses in it, with the next lesson, progress and link), a second run the same week sends
 *     nothing, the next week sends again; List-Unsubscribe headers; the mail goes through the
 *     platform mail helper;
 *   - opening a lesson records when (at most one write an hour);
 *   - the one-click link: signed per member, a wrong or foreign token changes nothing, a valid one
 *     turns the reminders off (GET or the mail client's POST) and the page's button back on;
 *   - the wiring: cron event (weekly, Tuesday morning), the settings form (nonce, default on),
 *     the route and its reserved slug, the export and erase, the loader.
 *
 * Run with: php tests/test-platform-courses-reminders.php
 */

$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ────────────────────── in-memory WordPress ──────────────────────
define( 'ABSPATH', $theme . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['hooks'] = array();
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; return true; }
function apply_filters( $hook, $v ) { return $v; }
function do_action() {}
function home_url( $p = '' ) { return 'https://example.test' . $p; }
function add_query_arg( array $args, $url ) { return $url . '?' . http_build_query( $args ); }
function wp_salt( $scheme = 'auth' ) { return 'test-salt-' . $scheme; }
function absint( $v ) { return abs( (int) $v ); }
function sanitize_text_field( $v ) { return trim( strip_tags( (string) $v ) ); }
function wp_unslash( $v ) { return $v; }
function wp_strip_all_tags( $v ) { return strip_tags( (string) $v ); }
function is_wp_error( $v ) { return $v instanceof WP_Error; }
class WP_Error { public function __construct( $c = '', $m = '' ) {} }

function world_reset() {
	$GLOBALS['W'] = array( 'users' => array(), 'umeta' => array(), 'posts' => array(), 'pmeta' => array(), 'lessons' => array(), 'cache' => array(), 'mail' => array(), 'queries' => 0 );
}
function add_user( $id, array $o = array() ) {
	$GLOBALS['W']['users'][ $id ] = (object) array_merge( array( 'ID' => $id, 'display_name' => 'Learner ' . $id, 'user_email' => "u$id@example.test", 'verified' => true, 'suspended' => false ), $o );
}
function add_course( $id, array $lesson_ids, $status = 'publish' ) {
	$GLOBALS['W']['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => 'pg_course', 'post_status' => $status, 'post_title' => "Course $id" );
	$GLOBALS['W']['lessons'][ $id ] = array();
	foreach ( $lesson_ids as $l ) {
		$GLOBALS['W']['posts'][ $l ] = (object) array( 'ID' => $l, 'post_type' => 'pg_lesson', 'post_status' => 'publish', 'post_title' => "Lesson $l", 'course' => $id );
		$GLOBALS['W']['lessons'][ $id ][] = $GLOBALS['W']['posts'][ $l ];
	}
}
function add_enrolment( $id, $user, $course, $enrolled_at, array $meta = array() ) {
	$GLOBALS['W']['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => 'pg_enrolment', 'post_status' => 'publish', 'post_author' => $user, 'post_parent' => $course, 'post_date_gmt' => gmdate( 'Y-m-d H:i:s', $enrolled_at ) );
	$GLOBALS['W']['pmeta'][ $id ] = $meta;
}
function get_posts( array $a ) {
	$GLOBALS['W']['queries']++;
	$out = array();
	foreach ( $GLOBALS['W']['posts'] as $p ) {
		if ( $p->post_type !== $a['post_type'] ) { continue; }
		if ( isset( $a['author'] ) && (int) $p->post_author !== (int) $a['author'] ) { continue; }
		if ( isset( $a['post_parent'] ) && (int) $p->post_parent !== (int) $a['post_parent'] ) { continue; }
		if ( isset( $a['meta_query'] ) && ! empty( $GLOBALS['W']['pmeta'][ $p->ID ]['_pg_courses_completed_at'] ) ) { continue; } // the job asks for open enrolments only
		$out[] = $p;
	}
	if ( isset( $a['paged'] ) ) {
		$out = array_slice( $out, ( $a['paged'] - 1 ) * $a['posts_per_page'], $a['posts_per_page'] );
	}
	return isset( $a['fields'] ) && 'ids' === $a['fields'] ? array_map( function ( $p ) { return $p->ID; }, $out ) : $out;
}
function get_post( $p ) { return is_object( $p ) ? $p : ( $GLOBALS['W']['posts'][ (int) $p ] ?? null ); }
function get_post_status( $id ) { return isset( $GLOBALS['W']['posts'][ $id ] ) ? $GLOBALS['W']['posts'][ $id ]->post_status : false; }
function get_the_title( $p ) { $p = get_post( $p ); return $p ? $p->post_title : ''; }
function get_permalink( $p ) { $p = get_post( $p ); return 'https://example.test/courses/c/lessons/lesson-' . $p->ID . '/'; }
function get_post_meta( $id, $k, $single = false ) { return $GLOBALS['W']['pmeta'][ $id ][ $k ] ?? ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['W']['pmeta'][ $id ][ $k ] = $v; $GLOBALS['W']['pmeta_writes'][] = $k; return true; }
function get_userdata( $id ) { return $GLOBALS['W']['users'][ (int) $id ] ?? false; }
function cache_users( $ids ) { $GLOBALS['W']['cache_users'][] = $ids; }
function get_user_meta( $id, $k, $single = false ) { return $GLOBALS['W']['umeta'][ $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['W']['umeta'][ $id ][ $k ] = $v; return true; }
function delete_user_meta( $id, $k ) { $had = isset( $GLOBALS['W']['umeta'][ $id ][ $k ] ); unset( $GLOBALS['W']['umeta'][ $id ][ $k ] ); return $had; }
function wp_cache_get( $k, $g = '' ) { return $GLOBALS['W']['cache'][ $g ][ $k ] ?? false; }
function wp_cache_set( $k, $v, $g = '' ) { $GLOBALS['W']['cache'][ $g ][ $k ] = $v; return true; }
function wp_cache_delete( $k, $g = '' ) { unset( $GLOBALS['W']['cache'][ $g ][ $k ] ); return true; }
function prospergenics_profiles_is_verified( $id ) { return ! empty( $GLOBALS['W']['users'][ $id ]->verified ); }
function prospergenics_profiles_is_suspended( $id ) { return ! empty( $GLOBALS['W']['users'][ $id ]->suspended ); }
// the course structure (data-model.php)
function prospergenics_courses_flat_lessons( $course_id ) { return $GLOBALS['W']['lessons'][ $course_id ] ?? array(); }
function prospergenics_courses_course_of( $post ) { $post = get_post( $post ); return $post && isset( $post->course ) ? $post->course : 0; }
// the platform mail helper (inc/profiles/mail.php): record what would be sent
function prospergenics_profiles_send_mail( $to, $subject, $body, $type, $user_id = 0, array $headers = array() ) {
	$GLOBALS['W']['mail'][] = compact( 'to', 'subject', 'body', 'type', 'user_id', 'headers' );
	return true;
}
function prospergenics_courses_view() { return $GLOBALS['W']['view'] ?? ''; }

require $theme . '/inc/courses/helpers.php';
require $theme . '/inc/courses/enrolment.php';
require $theme . '/inc/courses/reminders.php';

$day = DAY_IN_SECONDS;
$now = gmmktime( 9, 0, 0, 11, 10, 2026 ); // Tuesday 10 November 2026, 09:00 UTC (ISO week 46)

// ────────────────────── the rules ──────────────────────
echo "The rules\n";
ok( '2026-W46' === prospergenics_courses_reminder_week( $now ) && '2026-W46' === prospergenics_courses_reminder_week( gmmktime( 23, 59, 0, 11, 15, 2026 ) ) && '2026-W47' === prospergenics_courses_reminder_week( gmmktime( 0, 0, 0, 11, 16, 2026 ) ), 'the week is the ISO calendar week (Monday to Sunday, UTC)' );
ok( '2027-W53' !== prospergenics_courses_reminder_week( gmmktime( 12, 0, 0, 1, 1, 2027 ) ) && '2026-W53' === prospergenics_courses_reminder_week( gmmktime( 12, 0, 0, 1, 1, 2027 ) ), 'around New Year the ISO year is used (1 January 2027 is in 2026-W53)' );
ok( 500 === prospergenics_courses_reminder_last_activity( 100, 0, array( 3 => 200 ), array( 3 => 500 ) ) && 900 === prospergenics_courses_reminder_last_activity( 100, '900', array(), array() ) && 100 === prospergenics_courses_reminder_last_activity( 100, '', array(), array() ), 'last activity: the latest of enrolled, last opened, first opened and completed' );
$ok_learner = array( 'verified' => true, 'suspended' => false, 'opted_out' => false, 'last_week' => '' );
$idle       = array( 'completed' => false, 'lessons' => 3, 'last_activity' => $now - 8 * $day );
$plan = prospergenics_courses_reminder_plan( $ok_learner, array( 7 => $idle ), $now );
ok( $plan['send'] && array( 7 ) === $plan['due'], 'control: a verified learner idle for 8 days in an unfinished course gets a reminder' );
ok( prospergenics_courses_reminder_plan( $ok_learner, array( 7 => array_merge( $idle, array( 'last_activity' => $now - 7 * $day ) ) ), $now )['send'], 'exactly 7 days idle: due' );
ok( 'nothing_due' === prospergenics_courses_reminder_plan( $ok_learner, array( 7 => array_merge( $idle, array( 'last_activity' => $now - 7 * $day + 60 ) ) ), $now )['reason'], 'opened a lesson less than 7 days ago: nothing' );
ok( 'nothing_due' === prospergenics_courses_reminder_plan( $ok_learner, array( 7 => array_merge( $idle, array( 'completed' => true ) ) ), $now )['reason'], 'a completed course: nothing' );
ok( 'nothing_due' === prospergenics_courses_reminder_plan( $ok_learner, array( 7 => array_merge( $idle, array( 'lessons' => 0 ) ) ), $now )['reason'], 'a course without lessons: nothing' );
ok( 'unverified' === prospergenics_courses_reminder_plan( array_merge( $ok_learner, array( 'verified' => false ) ), array( 7 => $idle ), $now )['reason'], 'an unverified member: nothing' );
ok( 'suspended' === prospergenics_courses_reminder_plan( array_merge( $ok_learner, array( 'suspended' => true ) ), array( 7 => $idle ), $now )['reason'], 'a suspended member: nothing' );
ok( 'opted_out' === prospergenics_courses_reminder_plan( array_merge( $ok_learner, array( 'opted_out' => true ) ), array( 7 => $idle ), $now )['reason'], 'a member who turned them off: nothing' );
ok( 'this_week' === prospergenics_courses_reminder_plan( array_merge( $ok_learner, array( 'last_week' => '2026-W46' ) ), array( 7 => $idle ), $now )['reason'], 'already reminded this calendar week: nothing' );
ok( prospergenics_courses_reminder_plan( array_merge( $ok_learner, array( 'last_week' => '2026-W45' ) ), array( 7 => $idle ), $now )['send'], 'reminded last week: due again' );
$two = prospergenics_courses_reminder_plan( $ok_learner, array( 7 => $idle, 8 => array_merge( $idle, array( 'completed' => true ) ), 9 => $idle ), $now );
ok( array( 7, 9 ) === $two['due'], 'several idle courses go in one plan; the completed one is left out' );
$first = prospergenics_courses_reminders_first_run( gmmktime( 12, 0, 0, 11, 4, 2026 ) );
ok( 'Tue 2026-11-10 07:00' === gmdate( 'D Y-m-d H:i', $first ) && 'Tue 2026-11-10 07:00' === gmdate( 'D Y-m-d H:i', prospergenics_courses_reminders_first_run( gmmktime( 8, 0, 0, 11, 3, 2026 ) ) ) && 'Tue 2026-11-03 07:00' === gmdate( 'D Y-m-d H:i', prospergenics_courses_reminders_first_run( gmmktime( 6, 0, 0, 11, 3, 2026 ) ) ), 'the job runs on Tuesdays from 07:00 UTC (mid-week, far from the week boundary)' );

// ────────────────────── the token ──────────────────────
echo "The one-click link\n";
$t5 = prospergenics_courses_reminders_token( 5 );
ok( 64 === strlen( $t5 ) && $t5 !== prospergenics_courses_reminders_token( 6 ), 'a signed token per member (HMAC-SHA256)' );
ok( prospergenics_courses_reminders_check_token( 5, $t5 ) && ! prospergenics_courses_reminders_check_token( 6, $t5 ) && ! prospergenics_courses_reminders_check_token( 5, '' ) && ! prospergenics_courses_reminders_check_token( 5, substr( $t5, 0, 63 ) . 'x' ) && ! prospergenics_courses_reminders_check_token( 0, prospergenics_courses_reminders_token( 0 ) ) && ! prospergenics_courses_reminders_check_token( 5, array( $t5 ) ), "another member's, an empty, a changed or a non-string token is refused" );
ok( 'https://example.test/courses/reminders/stop/?u=5&t=' . $t5 === prospergenics_courses_reminders_stop_url( 5 ), 'the link: /courses/reminders/stop/?u=<id>&t=<token>' );

// ────────────────────── the job ──────────────────────
echo "The weekly job\n";
world_reset();
add_course( 10, array( 101, 102, 103, 104 ) );
add_course( 20, array( 201, 202 ) );
add_course( 30, array( 301 ) );
add_course( 40, array( 401 ), 'draft' );
add_user( 1 );                                  // idle in course 10 (1 of 4 done) and 20; finished 30
add_user( 2 );                                  // opened a lesson 2 days ago
add_user( 3, array( 'verified' => false ) );    // idle, unverified
add_user( 4, array( 'suspended' => true ) );    // idle, suspended
add_user( 5 );                                  // idle, turned reminders off
add_user( 6 );                                  // idle only in an unpublished course
add_user( 7 );                                  // enrolled 3 days ago, never opened
$GLOBALS['W']['umeta'][5][ PROSPERGENICS_COURSES_REMINDERS_OFF_META ] = $now - 30 * $day;
add_enrolment( 1001, 1, 10, $now - 40 * $day, array( '_pg_courses_opened' => array( 101 => $now - 20 * $day ), '_pg_courses_completed' => array( 101 => $now - 19 * $day ), '_pg_courses_last_active' => $now - 9 * $day, '_pg_courses_last_lesson' => 101 ) );
add_enrolment( 1002, 1, 20, $now - 30 * $day );
add_enrolment( 1003, 1, 30, $now - 30 * $day, array( '_pg_courses_completed' => array( 301 => $now - 25 * $day ), '_pg_courses_completed_at' => $now - 25 * $day ) );
add_enrolment( 1004, 2, 10, $now - 30 * $day, array( '_pg_courses_last_active' => $now - 2 * $day ) );
add_enrolment( 1005, 3, 10, $now - 30 * $day );
add_enrolment( 1006, 4, 10, $now - 30 * $day );
add_enrolment( 1007, 5, 10, $now - 30 * $day );
add_enrolment( 1008, 6, 40, $now - 30 * $day );
add_enrolment( 1009, 7, 20, $now - 3 * $day );
$stats = prospergenics_courses_send_reminders( $now );
$mail  = $GLOBALS['W']['mail'];
ok( 1 === $stats['sent'] && 1 === count( $mail ) && 'u1@example.test' === $mail[0]['to'], 'one email in all: to the idle, verified learner (not the active, unverified, suspended, opted-out, unpublished-course or just-enrolled ones)' );
ok( array( 'nothing_due' => 3, 'unverified' => 1, 'suspended' => 1, 'opted_out' => 1 ) == $stats['skipped'], 'the others are counted by reason' );
$b = $mail[0]['body'];
ok( false !== strpos( $b, "Course 10\nYour progress: 1 of 4 lessons (25%)\nNext lesson: Lesson 102\nhttps://example.test/courses/c/lessons/lesson-102/" ), 'course 10: the next lesson after the last one, the progress and the link' );
ok( false !== strpos( $b, "Course 20\nYour progress: 0 of 2 lessons (0%)\nNext lesson: Lesson 201" ), 'course 20 in the same email (one email per learner)' );
ok( false === strpos( $b, 'Course 30' ), 'not the completed course' );
ok( 'Your next lessons on ProsperGenics' === $mail[0]['subject'] && 'course_reminder' === $mail[0]['type'] && 1 === $mail[0]['user_id'], 'subject, mail type and member recorded by the platform mail helper' );
$stop = prospergenics_courses_reminders_stop_url( 1 );
ok( false !== strpos( $b, 'Stop these reminders (one click): ' . $stop ) && false !== strpos( $b, 'https://example.test/profile/settings/#pgp-email' ), 'every email has the one-click stop link and the settings link' );
ok( in_array( 'List-Unsubscribe: <' . $stop . '>', $mail[0]['headers'], true ) && in_array( 'List-Unsubscribe-Post: List-Unsubscribe=One-Click', $mail[0]['headers'], true ), 'List-Unsubscribe and List-Unsubscribe-Post (RFC 8058) headers' );
ok( '2026-W46' === get_user_meta( 1, PROSPERGENICS_COURSES_REMINDER_WEEK_META ) && $now === get_user_meta( 1, PROSPERGENICS_COURSES_REMINDER_SENT_META ), 'the week and time of the reminder are recorded on the member' );
ok( 1 === count( $GLOBALS['W']['cache_users'] ) && array( 1, 2, 3, 4, 5, 6, 7 ) === $GLOBALS['W']['cache_users'][0], 'the learners are loaded in one read' );
ok( 1 === $GLOBALS['W']['queries'], 'one enrolment query (the next lesson reuses the enrolments already loaded)' );

$GLOBALS['W']['mail'] = array();
$again = prospergenics_courses_send_reminders( $now + 3 * $day );
ok( 0 === $again['sent'] && array() === $GLOBALS['W']['mail'] && 1 === $again['skipped']['this_week'], 'a second run in the same week sends nothing' );
$to   = function () { return array_column( $GLOBALS['W']['mail'], 'to' ); };
$next = prospergenics_courses_send_reminders( $now + 7 * $day );
ok( array( 'u1@example.test', 'u2@example.test', 'u7@example.test' ) === $to() && '2026-W47' === get_user_meta( 1, PROSPERGENICS_COURSES_REMINDER_WEEK_META ), 'the next week, still idle: one more each, now also for the learners who have gone quiet since (2 and 7)' );
$GLOBALS['W']['mail'] = array();
prospergenics_courses_set_reminders( 1, false );
prospergenics_courses_send_reminders( $now + 14 * $day );
ok( ! in_array( 'u1@example.test', $to(), true ) && in_array( 'u2@example.test', $to(), true ) && ! prospergenics_courses_reminders_enabled( 1 ), 'turned off: nothing for that learner the week after (others still get theirs)' );
prospergenics_courses_set_reminders( 1, true );
ok( prospergenics_courses_reminders_enabled( 1 ) && '' === get_user_meta( 1, PROSPERGENICS_COURSES_REMINDERS_OFF_META ), 'turned on again: the setting is removed (on is the default)' );
world_reset();
add_course( 20, array( 201, 202 ) );
add_user( 9 );
add_enrolment( 1100, 9, 20, $now - 10 * $day );
prospergenics_courses_send_reminders( $now );
ok( 'Your next lesson in Course 20' === $GLOBALS['W']['mail'][0]['subject'] && false !== strpos( $GLOBALS['W']['mail'][0]['body'], 'not opened a lesson of your course for a week' ), 'one course: the subject names it' );
world_reset();
ok( array( 'sent' => 0, 'failed' => 0, 'skipped' => array() ) === prospergenics_courses_send_reminders( $now ), 'no open enrolments: nothing to do' );

// ────────────────────── opening a lesson ──────────────────────
echo "Opening a lesson records when\n";
world_reset();
add_course( 10, array( 101, 102 ) );
add_user( 1 );
add_enrolment( 1001, 1, 10, $now - 40 * $day );
prospergenics_courses_touch_lesson( 1, get_post( 101 ) );
$t = (int) get_post_meta( 1001, '_pg_courses_last_active' );
ok( $t >= time() - 5 && $t <= time(), 'the lesson page records when a lesson was last opened' );
$GLOBALS['W']['pmeta_writes'] = array();
prospergenics_courses_touch_lesson( 1, get_post( 101 ) );
ok( ! in_array( '_pg_courses_last_active', $GLOBALS['W']['pmeta_writes'], true ), 'opened again within the hour: no extra write' );
$GLOBALS['W']['pmeta'][1001]['_pg_courses_last_active'] = time() - 2 * HOUR_IN_SECONDS;
prospergenics_courses_touch_lesson( 1, get_post( 102 ) );
ok( (int) get_post_meta( 1001, '_pg_courses_last_active' ) >= time() - 5, 'an hour later: updated' );

// ────────────────────── /courses/reminders/stop/ ──────────────────────
echo "The stop page\n";
world_reset();
add_user( 5 );
add_user( 6 );
$GLOBALS['W']['view'] = 'reminders';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET  = array( 'u' => '5', 't' => prospergenics_courses_reminders_token( 6 ) );
$_POST = array();
prospergenics_courses_reminders_handle_stop();
ok( 'invalid' === $GLOBALS['pg_courses_reminders_result']['state'] && prospergenics_courses_reminders_enabled( 5 ) && prospergenics_courses_reminders_enabled( 6 ), "someone else's token: nothing changes" );
$_GET = array( 'u' => '99', 't' => prospergenics_courses_reminders_token( 99 ) );
prospergenics_courses_reminders_handle_stop();
ok( 'invalid' === $GLOBALS['pg_courses_reminders_result']['state'], 'no such member: nothing changes' );
$_GET = array( 'u' => '5', 't' => prospergenics_courses_reminders_token( 5 ) );
prospergenics_courses_reminders_handle_stop();
ok( 'off' === $GLOBALS['pg_courses_reminders_result']['state'] && ! prospergenics_courses_reminders_enabled( 5 ) && prospergenics_courses_reminders_enabled( 6 ), 'opening the link turns them off for that member only (one click)' );
$off_at = get_user_meta( 5, PROSPERGENICS_COURSES_REMINDERS_OFF_META );
prospergenics_courses_reminders_handle_stop();
ok( $off_at === get_user_meta( 5, PROSPERGENICS_COURSES_REMINDERS_OFF_META ), 'opening it again keeps the first "turned off" time' );
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = array( 'u' => '5', 't' => prospergenics_courses_reminders_token( 5 ), 'pgc_reminders' => 'on' );
$_GET  = array();
prospergenics_courses_reminders_handle_stop();
ok( 'on' === $GLOBALS['pg_courses_reminders_result']['state'] && prospergenics_courses_reminders_enabled( 5 ), 'the page\'s button turns them back on' );
$_POST = array( 'List-Unsubscribe' => 'One-Click' );
$_GET  = array( 'u' => '5', 't' => prospergenics_courses_reminders_token( 5 ) );
prospergenics_courses_reminders_handle_stop();
ok( ! prospergenics_courses_reminders_enabled( 5 ), "a mail client's one-click POST to the link turns them off" );
$_POST = array( 'u' => '5', 't' => 'forged', 'pgc_reminders' => 'on' );
$_GET  = array();
prospergenics_courses_reminders_handle_stop();
ok( ! prospergenics_courses_reminders_enabled( 5 ) && 'invalid' === $GLOBALS['pg_courses_reminders_result']['state'], 'a forged token cannot turn them back on' );
$GLOBALS['W']['view'] = 'my';
unset( $GLOBALS['pg_courses_reminders_result'] );
prospergenics_courses_reminders_handle_stop();
ok( ! isset( $GLOBALS['pg_courses_reminders_result'] ), 'other pages are not touched' );

// ────────────────────── wiring ──────────────────────
echo "Wiring\n";
$src = function ( $f ) use ( $theme ) { return (string) file_get_contents( $theme . '/' . $f ); };
ok( false !== strpos( $src( 'inc/platform-courses.php' ), "'reminders' ) as" ), 'the courses loader loads reminders.php' );
$rem = $src( 'inc/courses/reminders.php' );
ok( false !== strpos( $rem, "wp_schedule_event( prospergenics_courses_reminders_first_run( time() ), 'weekly', PROSPERGENICS_COURSES_REMINDERS_HOOK )" ) && isset( $GLOBALS['hooks'][ PROSPERGENICS_COURSES_REMINDERS_HOOK ] ) && isset( $GLOBALS['hooks']['switch_theme'] ), 'a weekly WP-Cron event runs the job, cleared when the theme is switched' );
ok( false === strpos( $rem, 'wp_mail(' ) && 1 === substr_count( $rem, 'prospergenics_profiles_send_mail(' ), 'mail goes through the platform mail helper' );
$mail_src = $src( 'inc/profiles/mail.php' );
ok( false !== strpos( $mail_src, 'function prospergenics_profiles_send_mail( $to, $subject, $body, $type, $user_id = 0, array $headers = array() )' ) && false !== strpos( $mail_src, 'wp_mail( $to, $subject, $body, $headers )' ), 'the mail helper passes extra headers on' );
$settings = $src( 'pg-profile-settings.php' );
ok( false !== strpos( $settings, "wp_nonce_field( 'pg_profiles_reminders', '_pgp_nonce' )" ) && false !== strpos( $settings, "'Weekly learning reminders', prospergenics_courses_reminders_enabled( \$uid ), 'checkbox'" ), '/profile/settings/ has the "Weekly learning reminders" checkbox (ticked unless turned off), with its own nonce' );
$routes = $src( 'inc/profiles/routes.php' );
ok( false !== strpos( $routes, "'reminders' => 'pg_profiles_reminders'," ) && false !== strpos( $routes, "prospergenics_courses_set_reminders( \$uid, ! empty( \$_POST['reminders'] ) );" ), 'the settings handler checks that nonce and saves the box (unticked = off) for the member themself' );
$routing = $src( 'inc/courses/routing.php' );
ok( false !== strpos( $routing, "'courses/reminders/stop/?\$'                   => 'index.php?pg_courses_view=reminders'," ) && false !== strpos( $routing, "'reminders'  => 'courses-reminders.php'," ) && file_exists( $theme . '/courses-reminders.php' ), '/courses/reminders/stop/ is routed to courses-reminders.php' );
ok( in_array( 'reminders', prospergenics_courses_reserved_slugs(), true ), 'no course can take the slug "reminders"' );
$tpl = $src( 'courses-reminders.php' );
ok( false !== strpos( $tpl, 'esc_attr($pgc_r[\'token\'])' ) && false !== strpos( $tpl, "status_header(403)" ), 'the stop page escapes what it prints and answers 403 for a bad link' );
$privacy = $src( 'inc/courses/privacy.php' );
ok( false !== strpos( $privacy, "'Weekly learning reminders' => prospergenics_courses_reminders_enabled( \$user->ID ) ? 'on' : 'off'," ) && false !== strpos( $privacy, "'Last reminder sent'" ) && false !== strpos( $privacy, "'Last opened a lesson'" ), 'the personal data export has the setting, the last reminder and the last opened lesson' );
ok( false !== strpos( $privacy, "array( '_pg_courses_reminder_week', '_pg_courses_reminder_sent' )" ), 'erasing removes the record of the last reminder (the opt-out stays)' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All passed\n" );
exit( $failures ? 1 : 0 );

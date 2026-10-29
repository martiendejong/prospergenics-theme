<?php
/**
 * Standalone tests for lesson quizzes (card #6461, inc/courses/quiz.php):
 *   - sanitising: types, pass mark, options/correct remapping, incomplete questions kept but
 *     never shown, limits, tags stripped, JSON round trip,
 *   - grading: single, multiple (exact set) and short answers (case and spaces ignored,
 *     several accepted answers), pass mark, unanswered questions,
 *   - attempts: kept on the enrolment, count/best/first pass, at most 20 kept,
 *   - completion: "Mark as complete" (function and REST) refuses a quiz lesson until the quiz
 *     is passed; passing completes it and drives progress to 100%; failing allows retries,
 *   - correct answers never reach the learner before an attempt (form HTML, REST payloads),
 *   - the quiz REST route and form handler: permission callback, nonce, rate limit,
 *   - instructor results, and the privacy exporter/eraser include the attempts.
 *
 * Runs the real courses code against a tiny in-memory WordPress.
 *
 * Run with: php tests/test-platform-courses-quiz.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ────────────────────── in-memory WordPress ──────────────────────
define( 'ABSPATH', sys_get_temp_dir() . '/pg-courses-quiz-test/' );
define( 'MB_IN_BYTES', 1048576 );
define( 'KB_IN_BYTES', 1024 );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['hooks']      = array();
$GLOBALS['routes']     = array();
$GLOBALS['posts']      = array();
$GLOBALS['meta']       = array();
$GLOBALS['users']      = array();
$GLOBALS['caps']       = array();
$GLOBALS['transients'] = array();
$GLOBALS['me']         = 0;
$GLOBALS['next_id']    = 100;

function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function apply_filters( $hook, $value ) { return $value; }
function do_action( ...$args ) {}
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $s ) ), '-' ); }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $ns . $route ] = $args; }
function get_current_user_id() { return $GLOBALS['me']; }
function is_user_logged_in() { return $GLOBALS['me'] > 0; }
function user_can( $user, $cap ) { $id = is_object( $user ) ? $user->ID : (int) $user; return ! empty( $GLOBALS['caps'][ $id ][ $cap ] ); }
function current_user_can( $cap ) { return user_can( $GLOBALS['me'], $cap ); }
function get_userdata( $id ) { return isset( $GLOBALS['users'][ (int) $id ] ) ? $GLOBALS['users'][ (int) $id ] : false; }
function get_user_by( $field, $v ) { foreach ( $GLOBALS['users'] as $u ) { if ( $u->user_email === $v ) { return $u; } } return false; }
function get_user_meta( $id, $k, $single = false ) { return ''; }
function wp_cache_get( $k, $g = '' ) { return false; }
function wp_cache_set( $k, $v, $g = '' ) { return true; }
function wp_cache_delete( $k, $g = '' ) { return true; }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function admin_url( $p = '' ) { return 'https://example.test/wp-admin/' . $p; }
function wp_date( $f, $t = null ) { return gmdate( $f, null === $t ? time() : $t ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function _n( $one, $many, $n, $d = '' ) { return 1 === (int) $n ? $one : $many; }
function wp_slash( $v ) { return is_string( $v ) ? addslashes( $v ) : $v; }
function wp_unslash( $v ) { return is_string( $v ) ? stripslashes( $v ) : $v; }
function wp_json_encode( $v, $f = 0 ) { return json_encode( $v, $f ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function get_permalink( $p ) { $p = get_post( $p ); return $p ? home_url( '/x/' . $p->post_name . '/' ) : ''; }
function get_post_field( $f, $p ) { $p = get_post( $p ); return $p ? $p->$f : ''; }
function wp_nonce_field( $a, $n, $r = true, $e = true ) { return '<input type="hidden" name="' . $n . '" value="nonce-' . $a . '" />'; }
function get_the_title( $p ) { $p = get_post( $p ); return $p ? $p->post_title : ''; }
function get_post( $p ) {
	if ( is_object( $p ) ) { return $p; }
	return isset( $GLOBALS['posts'][ (int) $p ] ) ? $GLOBALS['posts'][ (int) $p ] : null;
}
function get_post_type( $p ) { $p = get_post( $p ); return $p ? $p->post_type : false; }
function get_post_status( $p ) { $p = get_post( $p ); return $p ? $p->post_status : false; }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['meta'][ (int) $id ][ $k ] ) ? $GLOBALS['meta'][ (int) $id ][ $k ] : ''; }
// like WordPress: values are unslashed on the way in
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ (int) $id ][ $k ] = wp_unslash( $v ); return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['meta'][ (int) $id ][ $k ] ); return true; }
function wp_delete_post( $id, $force = false ) { unset( $GLOBALS['posts'][ (int) $id ], $GLOBALS['meta'][ (int) $id ] ); return true; }
function wp_insert_post( $a, $wp_error = false ) {
	$id = $GLOBALS['next_id']++;
	$GLOBALS['posts'][ $id ] = new WP_Post( array_merge( array( 'ID' => $id, 'post_title' => '', 'post_name' => '', 'post_parent' => 0, 'post_author' => 0, 'menu_order' => 0, 'post_status' => 'draft', 'post_date_gmt' => '2026-10-26 10:00:00' ), $a, array( 'ID' => $id ) ) );
	return $id;
}
function add_post( $id, $type, $parent = 0, $author = 0, $title = '', $status = 'publish' ) {
	$GLOBALS['posts'][ $id ] = new WP_Post( array( 'ID' => $id, 'post_type' => $type, 'post_parent' => $parent, 'post_author' => $author, 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_status' => $status, 'menu_order' => $id, 'post_content' => '<p>Text</p>' ) );
}
function get_posts( $a ) {
	$out = array();
	foreach ( $GLOBALS['posts'] as $p ) {
		$types  = (array) $a['post_type'];
		$status = isset( $a['post_status'] ) ? $a['post_status'] : 'publish';
		if ( ! in_array( $p->post_type, $types, true ) ) { continue; }
		if ( 'any' !== $status && ! in_array( $p->post_status, (array) $status, true ) ) { continue; }
		if ( isset( $a['author'] ) && (int) $p->post_author !== (int) $a['author'] ) { continue; }
		if ( isset( $a['post_parent'] ) && (int) $p->post_parent !== (int) $a['post_parent'] ) { continue; }
		if ( isset( $a['post_parent__in'] ) && ! in_array( (int) $p->post_parent, $a['post_parent__in'], true ) ) { continue; }
		$out[] = $p;
	}
	usort( $out, function ( $x, $y ) { return $x->menu_order - $y->menu_order ?: $x->ID - $y->ID; } );
	if ( isset( $a['posts_per_page'] ) && $a['posts_per_page'] > 0 ) { $out = array_slice( $out, 0, $a['posts_per_page'] ); }
	return isset( $a['fields'] ) && 'ids' === $a['fields'] ? array_map( function ( $p ) { return $p->ID; }, $out ) : $out;
}
#[\AllowDynamicProperties]
class WP_Post {
	public function __construct( array $a ) { foreach ( $a as $k => $v ) { $this->$k = $v; } }
}
class WP_Error {
	public $code, $message, $data;
	public function __construct( $code = '', $msg = '', $data = null ) { $this->code = $code; $this->message = $msg; $this->data = $data; }
	public function get_error_code() { return $this->code; }
}
class WP_REST_Response {
	public $data, $status;
	public function __construct( $data = null, $status = 200 ) { $this->data = $data; $this->status = $status; }
}
class WP_REST_Request implements ArrayAccess {
	private $p = array();
	private $m;
	public function __construct( $m = 'GET', $params = array() ) { $this->m = $m; $this->p = $params; }
	public function get_method() { return $this->m; }
	public function get_param( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; }
	public function offsetExists( $k ): bool { return isset( $this->p[ $k ] ); }
	#[\ReturnTypeWillChange] public function offsetGet( $k ) { return $this->get_param( $k ); }
	public function offsetSet( $k, $v ): void { $this->p[ $k ] = $v; }
	public function offsetUnset( $k ): void { unset( $this->p[ $k ] ); }
}

require $theme . '/inc/platform-courses.php';
foreach ( $GLOBALS['hooks']['rest_api_init'] as $cb ) { $cb(); }
$route = function ( $r ) { return $GLOBALS['routes'][ 'prospergenics/v1' . $r ]; };

// ────────────────────── pure helpers ──────────────────────
echo "Sanitising a quiz\n";
$raw = array(
	'pass_mark' => '250',
	'questions' => array(
		array( 'id' => 'q1', 'type' => 'single', 'prompt' => ' <b>Who</b> first? ', 'options' => array( 'Learners', '', 'Investors', 'Rivals' ), 'correct' => array( 2, 3 ), 'explanation' => '<script>x</script>Because.' ),
		array( 'id' => 'q2', 'type' => 'multiple', 'prompt' => 'Signals?', 'options' => array( array( 'text' => 'Pays', 'correct' => true ), array( 'text' => 'Nice', 'correct' => false ), array( 'text' => 'Complains', 'correct' => true ) ) ),
		array( 'id' => 'q3', 'type' => 'short', 'prompt' => 'How many?', 'answers' => "five\n Five \n5\n" ),
		array( 'id' => 'q4', 'type' => 'single', 'prompt' => 'Draft question', 'options' => array( 'A', 'B' ) ), // no correct answer yet
		array( 'id' => 'q1', 'type' => 'evil', 'prompt' => 'Duplicate id, unknown type', 'options' => array( 'A', 'B' ), 'correct' => array( 0 ) ),
	),
);
$quiz = prospergenics_courses_sanitize_quiz( $raw );
ok( 100 === $quiz['pass_mark'], 'pass mark clamped to 1..100' );
ok( 70 === prospergenics_courses_sanitize_quiz( array() )['pass_mark'] && 70 === PROSPERGENICS_COURSES_QUIZ_PASS_MARK, 'default pass mark is 70%' );
$q = $quiz['questions'];
ok( 'Who first?' === $q[0]['prompt'] && 'xBecause.' === $q[0]['explanation'], 'tags stripped from prompts and explanations' );
ok( array( 'Learners', 'Investors', 'Rivals' ) === $q[0]['options'] && array( 1 ) === $q[0]['correct'], 'empty options dropped, correct index remapped, single keeps one correct answer' );
ok( array( 0, 2 ) === $q[1]['correct'] && array( 'Pays', 'Nice', 'Complains' ) === $q[1]['options'], 'multiple answer: options as {text, correct} objects' );
ok( array( 'five', '5' ) === $q[2]['answers'], 'short answers: one per line, duplicates by normalised form dropped' );
ok( ! $q[3]['ready'] && $q[0]['ready'] && $q[2]['ready'], 'a question without a correct answer is kept but not ready' );
ok( 'single' === $q[4]['type'] && 'q1' !== $q[4]['id'] && 5 === count( array_unique( array_column( $q, 'id' ) ) ), 'unknown type becomes single; duplicate ids replaced' );
ok( 4 === count( prospergenics_courses_quiz_ready_questions( $quiz ) ), 'learners get the four complete questions' );
$many = array( 'questions' => array_fill( 0, 80, array( 'type' => 'short', 'prompt' => 'x', 'answers' => array( 'y' ) ) ) );
ok( PROSPERGENICS_COURSES_QUIZ_MAX_QUESTIONS === count( prospergenics_courses_sanitize_quiz( $many )['questions'] ), 'at most 50 questions' );
ok( prospergenics_courses_sanitize_quiz( json_encode( $raw ) ) === $quiz, 'JSON and array input give the same quiz' );
ok( prospergenics_courses_sanitize_quiz( 'not json' ) === array( 'pass_mark' => 70, 'questions' => array() ), 'garbage gives an empty quiz' );

echo "Grading\n";
ok( 'newyork' === prospergenics_courses_quiz_normalize( "  New \t York " ), 'short answers ignore case and every space' );
$g = prospergenics_courses_grade_quiz( $quiz, array( $q[0]['id'] => '1', $q[1]['id'] => array( '2', '0' ), $q[2]['id'] => ' FI VE ', $q[4]['id'] => 0 ) );
ok( 4 === $g['correct'] && 100 === $g['score'] && $g['passed'], 'all right (string indexes, any order, other case and spaces): 100%, passed' );
$g = prospergenics_courses_grade_quiz( $quiz, array( $q[0]['id'] => 1, $q[1]['id'] => array( 0 ), $q[2]['id'] => '6', $q[4]['id'] => 0 ) );
ok( 2 === $g['correct'] && 50 === $g['score'] && ! $g['passed'], 'multiple answer needs every correct option and nothing else' );
ok( false === $g['results'][ $q[1]['id'] ] && false === $g['results'][ $q[2]['id'] ], 'per-question results' );
$g = prospergenics_courses_grade_quiz( $quiz, array( $q[1]['id'] => array( 0, 1, 2 ) ) );
ok( 0 === $g['correct'] && ! $g['passed'], 'selecting every option is wrong; unanswered questions are wrong' );
$g = prospergenics_courses_grade_quiz( $quiz, array( $q[0]['id'] => 99, $q[2]['id'] => array( 'x' ), $q[4]['id'] => '0 OR 1' ) );
ok( 0 === $g['correct'] && null === $g['answers'][ $q[0]['id'] ], 'out-of-range and malformed answers are wrong' );
$three = array( 'pass_mark' => 70, 'questions' => array_slice( $q, 0, 3 ) );
$g     = prospergenics_courses_grade_quiz( $three, array( $q[0]['id'] => 1, $q[1]['id'] => array( 0, 2 ), $q[2]['id'] => 'x' ) );
ok( 66 === $g['score'] && ! $g['passed'], '2 of 3 = 66% < 70%: not passed' );
$three['pass_mark'] = 66;
ok( true === prospergenics_courses_grade_quiz( $three, array( $q[0]['id'] => 1, $q[1]['id'] => array( 0, 2 ) ) )['passed'], '2 of 3 passes a 66% pass mark (exact comparison, no rounding)' );
ok( ! prospergenics_courses_grade_quiz( array( 'questions' => array() ), array() )['passed'], 'an empty quiz is never passed' );

echo "Attempt records\n";
$r = null;
for ( $i = 1; $i <= 25; $i++ ) {
	$r = prospergenics_courses_quiz_add_attempt( $r, array( 'score' => 10 === $i ? 90 : 40, 'passed' => 10 === $i, 'answers' => array(), 'results' => array() ), 1000 + $i );
}
ok( 25 === $r['count'] && 90 === $r['best'] && 1010 === $r['passed_at'], 'count, best score and first pass cover every attempt' );
ok( PROSPERGENICS_COURSES_QUIZ_KEEP_ATTEMPTS === count( $r['attempts'] ) && 1025 === end( $r['attempts'] )['at'], 'the latest 20 attempts are kept' );

// ────────────────────── in WordPress ──────────────────────
// Course 10 (instructor 2): module 11, lessons 12 (quiz) and 13 (no quiz). Learner 5, learner 6.
foreach ( array( 2 => 'Amina Teacher', 5 => 'Learner Five', 6 => 'Learner Six' ) as $id => $name ) {
	$GLOBALS['users'][ $id ] = (object) array( 'ID' => $id, 'display_name' => $name, 'user_email' => "u$id@example.test" );
}
$GLOBALS['caps'][2] = array( 'edit_pg_courses' => true, 'edit_post' => true );
add_post( 10, 'pg_course', 0, 2, 'Soil basics' );
add_post( 11, 'pg_module', 10, 2, 'Module 1' );
add_post( 12, 'pg_lesson', 11, 2, 'Lesson A' );
add_post( 13, 'pg_lesson', 11, 2, 'Lesson B' );
$saved = prospergenics_courses_save_quiz( 12, $raw );
ok( is_string( get_post_meta( 12, '_pg_courses_quiz' ) ) && prospergenics_courses_get_quiz( 12, true ) === $saved, 'the quiz is stored as JSON lesson meta and reads back identical (slashes survive)' );
$withq = prospergenics_courses_save_quiz( 99, array( 'questions' => array( array( 'type' => 'short', 'prompt' => 'Say "hi" \\ there', 'answers' => array( 'a\\b' ) ) ) ) );
ok( 'Say "hi" \\ there' === prospergenics_courses_get_quiz( 99 )['questions'][0]['prompt'] && 'a\\b' === prospergenics_courses_get_quiz( 99 )['questions'][0]['answers'][0], 'quotes and backslashes survive the meta round trip' );
ok( null === prospergenics_courses_save_quiz( 98, array( 'questions' => array() ) ) && '' === get_post_meta( 98, '_pg_courses_quiz' ), 'saving no questions removes the quiz' );
prospergenics_courses_save_quiz( 97, array( 'questions' => array( array( 'type' => 'single', 'prompt' => 'Draft', 'options' => array( 'A', 'B' ) ) ) ) );
ok( ! prospergenics_courses_lesson_has_quiz( 97 ) && null !== prospergenics_courses_get_quiz( 97, true ), 'a quiz with only draft questions is kept for the editor but not required from learners' );
ok( prospergenics_courses_lesson_has_quiz( 12 ) && ! prospergenics_courses_lesson_has_quiz( 13 ), 'lesson 12 has a quiz, lesson 13 not' );

echo "Mark as complete refuses a quiz lesson until it is passed\n";
$GLOBALS['me'] = 5;
prospergenics_courses_enrol( 5, 10 );
prospergenics_courses_touch_lesson( 5, get_post( 12 ) );
prospergenics_courses_touch_lesson( 5, get_post( 13 ) );
$res = prospergenics_courses_set_lesson_complete( 5, 12, true );
ok( $res instanceof WP_Error && 'pg_courses_quiz_required' === $res->code && 403 === $res->data['status'], 'set_lesson_complete() refuses with 403 pg_courses_quiz_required' );
$complete = $route( '/courses/lessons/(?P<id>\d+)/completion' )[0];
$res      = call_user_func( $complete['callback'], new WP_REST_Request( 'POST', array( 'id' => 12 ) ) );
ok( $res instanceof WP_Error && 'pg_courses_quiz_required' === $res->code, 'POST /courses/lessons/12/completion refuses too' );
ok( is_array( prospergenics_courses_set_lesson_complete( 5, 13, true ) ), 'a lesson without a quiz still completes with the button' );
ok( 50 === prospergenics_courses_progress( 5, 10 ), 'progress 50%' );

echo "The form never contains the answers\n";
$html = prospergenics_courses_quiz_html( get_post( 12 ), 5, true );
ok( false !== strpos( $html, 'Who first?' ) && false !== strpos( $html, 'name="pgq[' . $q[0]['id'] . ']"' ) && false !== strpos( $html, 'type="checkbox"' ), 'questions and options are rendered (radios, checkboxes, text)' );
ok( false === strpos( $html, 'Because' ) && false === stripos( $html, 'five' ) && false === strpos( $html, 'correct"' ) && false === strpos( $html, 'Draft question' ), 'no explanation, accepted answer, correctness flag or draft question in the HTML' );
ok( false !== strpos( $html, 'name="action" value="pg_courses_quiz"' ) && false !== strpos( $html, 'nonce-pg_courses_quiz_12' ), 'the form posts to admin-post with a nonce for this lesson' );
ok( '' === prospergenics_courses_quiz_html( get_post( 13 ), 5, true ), 'no quiz section on a lesson without a quiz' );
$GLOBALS['me'] = 2;
$rest_lesson = prospergenics_courses_rest_lesson( get_post( 12 ), true );
ok( isset( $rest_lesson['quiz']['questions'][0]['correct'] ), 'editors get the full quiz from the lessons route' );
$GLOBALS['me'] = 5;
$rest_lesson = prospergenics_courses_rest_lesson( get_post( 12 ), true );
ok( ! isset( $rest_lesson['quiz'] ) && true === $rest_lesson['has_quiz'] && false === $rest_lesson['quiz_passed'] && false === strpos( json_encode( $rest_lesson ), 'Because' ), 'learners only get has_quiz / quiz_passed from the lessons route' );

echo "Submitting: fail, retry, pass\n";
$quiz_route = $route( '/courses/lessons/(?P<id>\d+)/quiz' );
ok( 'POST' === $quiz_route['methods'] && 'prospergenics_courses_rest_logged_in' === $quiz_route['permission_callback'], 'POST /courses/lessons/{id}/quiz needs a logged-in user (cookie auth checks the wp_rest nonce)' );
$GLOBALS['me'] = 0;
ok( call_user_func( $quiz_route['permission_callback'] ) instanceof WP_Error, 'logged-out users are refused' );
$GLOBALS['me'] = 6;
$res = call_user_func( $quiz_route['callback'], new WP_REST_Request( 'POST', array( 'id' => 12, 'answers' => array() ) ) );
ok( $res instanceof WP_Error && 'pg_courses_not_enrolled' === $res->code, 'a learner who is not enrolled cannot submit' );
$GLOBALS['me'] = 5;
$wrong = array( $q[0]['id'] => 0, $q[1]['id'] => array( 0 ), $q[2]['id'] => 'six', $q[4]['id'] => 1 );
$res   = call_user_func( $quiz_route['callback'], new WP_REST_Request( 'POST', array( 'id' => 12, 'answers' => $wrong ) ) );
ok( is_array( $res ) && 0 === $res['score'] && false === $res['passed'] && 1 === $res['attempts'], 'wrong answers: 0%, not passed, attempt 1' );
ok( 4 === count( $res['feedback'] ) && 'xBecause.' === $res['feedback'][0]['explanation'] && false === $res['feedback'][0]['correct'], 'feedback per question with the explanation' );
ok( ! isset( $res['feedback'][0]['answer'] ) && ! isset( $res['answers'] ), 'a failed attempt does not reveal the correct answers' );
ok( false === $res['lesson_completed'] && 50 === prospergenics_courses_progress( 5, 10 ), 'the lesson stays incomplete' );
$right = array( $q[0]['id'] => 1, $q[1]['id'] => array( 0, 2 ), $q[2]['id'] => 'Five', $q[4]['id'] => 0 );
$res   = call_user_func( $quiz_route['callback'], new WP_REST_Request( 'POST', array( 'id' => 12, 'answers' => $right ) ) );
ok( is_array( $res ) && 100 === $res['score'] && $res['passed'] && 2 === $res['attempts'] && 100 === $res['best'], 'a retry with the right answers passes (attempt 2, best 100%)' );
ok( true === $res['lesson_completed'] && 100 === $res['progress'] && 100 === prospergenics_courses_progress( 5, 10 ), 'passing completes the lesson: course at 100%' );
ok( prospergenics_courses_is_code( (string) $res['certificate'] ), 'and the certificate is issued as before' );
ok( 'Investors' === $res['feedback'][0]['answer'], 'a passed attempt shows the correct answers' );
ok( prospergenics_courses_quiz_passed( 5, 12 ) && is_array( prospergenics_courses_set_lesson_complete( 5, 12, false ) ) && is_array( prospergenics_courses_set_lesson_complete( 5, 12, true ) ), 'once passed, the lesson can be marked not complete and complete again' );
$res = call_user_func( $quiz_route['callback'], new WP_REST_Request( 'POST', array( 'id' => 12, 'answers' => $wrong ) ) );
ok( is_array( $res ) && ! $res['passed'] && 100 === $res['best'] && prospergenics_courses_quiz_passed( 5, 12 ), 'a later failed attempt keeps the pass and the best score' );
$record = prospergenics_courses_quiz_record( 5, 12 );
$enrol  = prospergenics_courses_get_enrolment( 5, 10 );
ok( 3 === $record['count'] && is_array( get_post_meta( $enrol->ID, '_pg_courses_quiz_attempts' ) ), 'attempts are stored on the enrolment record' );
$html = prospergenics_courses_quiz_html( get_post( 12 ), 5, true );
ok( false !== strpos( $html, 'id="pgc-quiz-result"' ) && false !== strpos( $html, 'Not passed yet' ) && false !== strpos( $html, 'You passed this quiz before' ) && false !== strpos( $html, 'Because.' ), 'the page shows the latest attempt with its feedback' );
ok( false !== strpos( $html, '<details class="pgc-quiz-retry"' ), 'and a way to take the quiz again' );

echo "Rate limit and form handler\n";
$GLOBALS['transients'] = array();
for ( $i = 0; $i < PROSPERGENICS_COURSES_QUIZ_RATE_LIMIT - 0; $i++ ) { $last = prospergenics_courses_submit_quiz( 5, 12, $wrong ); }
$res = prospergenics_courses_submit_quiz( 5, 12, $wrong );
ok( is_array( $last ) && $res instanceof WP_Error && 429 === $res->data['status'], 'at most 20 submissions a minute (429 after that)' );
$src = file_get_contents( $theme . '/inc/courses/quiz.php' );
ok( false !== strpos( $src, "add_action( 'admin_post_pg_courses_quiz'" ) && false !== strpos( $src, "check_admin_referer( 'pg_courses_quiz_' . \$lesson_id )" ), 'the form handler checks a per-lesson nonce' );
ok( 1 === preg_match( "/prospergenics_courses_submit_quiz\\( get_current_user_id\\(\\)/", $src ) && 2 === substr_count( $src, 'prospergenics_courses_submit_quiz( get_current_user_id()' ), 'both entry points act on the current user' );
ok( false !== strpos( $src, "'show_in_rest'      => false" ), 'the quiz meta is not exposed in REST' );

echo "Instructor results\n";
$GLOBALS['me'] = 6;
prospergenics_courses_enrol( 6, 10 );
$rows = prospergenics_courses_quiz_results( 10 );
ok( 2 === count( $rows ), 'one row per learner and quiz lesson' );
ok( 'Learner Five' === $rows[0]['learner'] && 'Lesson A' === $rows[0]['lesson'] && $rows[0]['attempts'] >= 3 && 100 === $rows[0]['best'] && $rows[0]['passed_at'] > 0, 'attempts, best score and pass date per learner' );
ok( 0 === $rows[1]['attempts'] && 0 === $rows[1]['passed_at'], 'learners who did not try it yet are listed too' );
$table = prospergenics_courses_quiz_results_html( array( get_post( 10 ) ) );
ok( false !== strpos( $table, '<caption' ) && false !== strpos( $table, 'scope="col">Best score' ) && false !== strpos( $table, 'Not started' ), 'the dashboard table is labelled' );
ok( false !== strpos( file_get_contents( $theme . '/courses-dashboard.php' ), 'prospergenics_courses_quiz_results_html(' ), 'the instructor dashboard shows the quiz results' );

echo "Privacy\n";
$export = prospergenics_courses_personal_data_exporter( 'u5@example.test' );
$items  = array_values( array_filter( $export['data'], function ( $i ) { return 'prospergenics-quiz-attempts' === $i['group_id']; } ) );
$flat   = $items ? json_encode( $items[0]['data'] ) : '';
ok( PROSPERGENICS_COURSES_QUIZ_KEEP_ATTEMPTS === count( $items ), 'the exporter lists every kept attempt (23 made, the latest 20 kept)' );
ok( false !== strpos( $flat, 'Lesson A' ) && false !== strpos( $flat, 'Who first?' ) && false !== strpos( $flat, 'Score' ), 'with the lesson, the answers given and the score' );
$erased = prospergenics_courses_erase_user_data( 5 );
ok( $erased >= 1 && null === prospergenics_courses_get_enrolment( 5, 10 ) && ! isset( $GLOBALS['meta'][ $enrol->ID ] ), 'the eraser removes the enrolment and the attempts on it' );
ok( null === prospergenics_courses_quiz_record( 5, 12 ), 'no attempts remain' );

echo "Template\n";
$tpl = file_get_contents( $theme . '/single-pg_lesson.php' );
ok( false !== strpos( $tpl, '// quiz:start' ) && false !== strpos( $tpl, '// quiz:end' ) && 1 === substr_count( $tpl, 'prospergenics_courses_quiz_html(' ), 'one clearly marked quiz hook point in the lesson template' );
ok( false !== strpos( $tpl, 'Pass the quiz above to complete this lesson' ), 'quiz lessons show no "Mark as complete" button until passed' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All quiz tests passed\n" );
exit( $failures ? 1 : 0 );

<?php
/**
 * Standalone tests for the courses track's learning integrity and instructor pages:
 *   - a lesson can only be marked complete after the learner opened it (403 otherwise),
 *   - certificates are only issued to accounts with a confirmed email address,
 *   - staff can revoke a certificate; verification and downloads then refuse it,
 *   - the completion endpoint is rate limited (60 a minute per learner),
 *   - /courses/instructor/<id>/ only resolves users who teach, so its <title> never shows
 *     the name of anyone else (an administrator account, a subscriber).
 *
 * Runs the real enrolment/certificate/REST/routing code against a tiny in-memory WordPress.
 *
 * Run with: php tests/test-platform-courses-integrity.php
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
define( 'ABSPATH', sys_get_temp_dir() . '/pg-courses-integrity-test/' );
define( 'MB_IN_BYTES', 1048576 );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['hooks']      = array();
$GLOBALS['routes']     = array();
$GLOBALS['posts']      = array();
$GLOBALS['meta']       = array();
$GLOBALS['users']      = array();
$GLOBALS['caps']       = array();
$GLOBALS['unverified'] = array();
$GLOBALS['transients'] = array();
$GLOBALS['qv']         = array();
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
function get_user_meta( $id, $k, $single = false ) { return ''; }
function prospergenics_profiles_is_verified( $id ) { return empty( $GLOBALS['unverified'][ (int) $id ] ); }
function wp_cache_get( $k, $g = '' ) { return false; }
function wp_cache_set( $k, $v, $g = '' ) { return true; }
function wp_cache_delete( $k, $g = '' ) { return true; }
function get_transient( $k ) { return isset( $GLOBALS['transients'][ $k ] ) ? $GLOBALS['transients'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['transients'][ $k ] = $v; return true; }
function home_url( $p = '/' ) { return 'https://example.test' . $p; }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }
function get_query_var( $k ) { return isset( $GLOBALS['qv'][ $k ] ) ? $GLOBALS['qv'][ $k ] : ''; }
function is_post_type_archive( $t = '' ) { return false; }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function get_permalink( $p ) { $p = get_post( $p ); return $p ? home_url( '/x/' . $p->post_name . '/' ) : ''; }
function get_post_field( $f, $p ) { $p = get_post( $p ); return $p ? $p->$f : ''; }
function wp_nonce_field( $a, $n, $r = true, $e = true ) { return '<input type="hidden" name="' . $n . '" value="nonce" />'; }
function get_the_title( $p ) { $p = get_post( $p ); return $p ? $p->post_title : ''; }
function get_post( $p ) {
	if ( is_object( $p ) ) { return $p; }
	return isset( $GLOBALS['posts'][ (int) $p ] ) ? $GLOBALS['posts'][ (int) $p ] : null;
}
function get_post_type( $p ) { $p = get_post( $p ); return $p ? $p->post_type : false; }
function get_post_status( $p ) { $p = get_post( $p ); return $p ? $p->post_status : false; }
function get_post_meta( $id, $k, $single = false ) { return isset( $GLOBALS['meta'][ (int) $id ][ $k ] ) ? $GLOBALS['meta'][ (int) $id ][ $k ] : ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['meta'][ (int) $id ][ $k ] = $v; return true; }
function delete_post_meta( $id, $k ) { unset( $GLOBALS['meta'][ (int) $id ][ $k ] ); return true; }
function wp_insert_post( $a, $wp_error = false ) {
	$id = $GLOBALS['next_id']++;
	$GLOBALS['posts'][ $id ] = (object) array_merge( array( 'ID' => $id, 'post_title' => '', 'post_name' => '', 'post_parent' => 0, 'post_author' => 0, 'menu_order' => 0, 'post_status' => 'draft' ), $a, array( 'ID' => $id ) );
	return $id;
}
function add_post( $id, $type, $parent = 0, $author = 0, $title = '', $status = 'publish' ) {
	$GLOBALS['posts'][ $id ] = (object) array( 'ID' => $id, 'post_type' => $type, 'post_parent' => $parent, 'post_author' => $author, 'post_title' => $title, 'post_name' => sanitize_title( $title ), 'post_status' => $status, 'menu_order' => $id );
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
		if ( isset( $a['name'] ) && $p->post_name !== $a['name'] ) { continue; }
		$out[] = $p;
	}
	usort( $out, function ( $x, $y ) { return $x->menu_order - $y->menu_order ?: $x->ID - $y->ID; } );
	if ( isset( $a['posts_per_page'] ) && $a['posts_per_page'] > 0 ) { $out = array_slice( $out, 0, $a['posts_per_page'] ); }
	return isset( $a['fields'] ) && 'ids' === $a['fields'] ? array_map( function ( $p ) { return $p->ID; }, $out ) : $out;
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

// Users: 1 = administrator without courses, 2 = instructor, 5 = learner, 7 = subscriber who
// (somehow) authored a published course but cannot teach.
foreach ( array( 1 => 'jengo-admin', 2 => 'Amina Teacher', 5 => 'Learner Five', 7 => 'Some Subscriber' ) as $id => $name ) {
	$GLOBALS['users'][ $id ] = (object) array( 'ID' => $id, 'display_name' => $name, 'user_email' => "u$id@example.test" );
}
$GLOBALS['caps'][1] = array( 'edit_pg_courses' => true, 'edit_others_pg_courses' => true );
$GLOBALS['caps'][2] = array( 'edit_pg_courses' => true );
// Course 10 by the instructor: one module, two lessons. Course 20 by user 7.
add_post( 10, 'pg_course', 0, 2, 'Soil basics' );
add_post( 11, 'pg_module', 10, 2, 'Module 1' );
add_post( 12, 'pg_lesson', 11, 2, 'Lesson A' );
add_post( 13, 'pg_lesson', 11, 2, 'Lesson B' );
add_post( 20, 'pg_course', 0, 7, 'Not a real instructor' );

echo "Lessons must be opened before they can be completed\n";
$GLOBALS['me'] = 5;
$GLOBALS['unverified'][5] = true;
ok( is_int( prospergenics_courses_enrol( 5, 10 ) ), 'learner enrols in the free course' );
$r = prospergenics_courses_set_lesson_complete( 5, 12, true );
ok( $r instanceof WP_Error && 'pg_courses_not_opened' === $r->code && 403 === $r->data['status'], 'completing a lesson never opened is refused with 403' );
ok( 0 === prospergenics_courses_progress( 5, 10 ), 'and records nothing' );
$complete = $route( '/courses/lessons/(?P<id>\d+)/completion' )[0];
$r = call_user_func( $complete['callback'], new WP_REST_Request( 'POST', array( 'id' => 13 ) ) );
ok( $r instanceof WP_Error && 403 === $r->data['status'], 'the REST completion endpoint refuses it too' );
prospergenics_courses_touch_lesson( 5, get_post( 12 ) );
$opened = prospergenics_courses_opened_map( 5, 10 );
ok( isset( $opened[12] ) && ! isset( $opened[13] ), 'opening the lesson page records the first open' );
$r = prospergenics_courses_set_lesson_complete( 5, 12, true );
ok( is_array( $r ) && 50 === $r['progress'], 'an opened lesson can be completed (50%)' );
ok( is_array( prospergenics_courses_set_lesson_complete( 5, 13, false ) ), 'marking a lesson NOT complete never needs an open' );

echo "Certificates need a confirmed email address\n";
prospergenics_courses_touch_lesson( 5, get_post( 13 ) );
$r = call_user_func( $complete['callback'], new WP_REST_Request( 'POST', array( 'id' => 13 ) ) );
ok( is_array( $r ) && 100 === $r['progress'], 'the learner reaches 100%' );
ok( null === $r['certificate'] && 'unverified' === $r['certificate_blocked'], 'an unverified learner gets no certificate, and the response says why' );
ok( null === prospergenics_courses_get_certificate( 5, 10 ) && null === prospergenics_courses_issue_certificate( 5, 10 ), 'no certificate record exists and none can be issued' );
$mine = $route( '/courses/(?P<id>\d+)/certificate' );
$r    = call_user_func( $mine['callback'], new WP_REST_Request( 'GET', array( 'id' => 10 ) ) );
ok( $r instanceof WP_Error && 'pg_courses_unverified' === $r->code && 403 === $r->data['status'], 'GET /courses/{id}/certificate explains that the email address must be confirmed' );
$html = prospergenics_courses_certificate_html( get_post( 10 ), 5, 100, 'b', 'Download' );
ok( false !== strpos( $html, 'Confirm your email address to receive your certificate' ) && false === strpos( $html, 'Download' ), 'the course page shows why instead of a download button' );

unset( $GLOBALS['unverified'][5] );
$r = call_user_func( $mine['callback'], new WP_REST_Request( 'GET', array( 'id' => 10 ) ) );
ok( is_array( $r ) && prospergenics_courses_is_code( $r['code'] ), 'once confirmed, the certificate is issued on request' );
$code   = $r['code'];
$verify = $route( '/courses/certificates/(?P<code>[A-Za-z0-9-]+)' );
$v      = call_user_func( $verify['callback'], new WP_REST_Request( 'GET', array( 'code' => $code ) ) );
ok( is_array( $v ) && true === $v['valid'], 'and verifies as valid' );

echo "Staff can revoke a certificate\n";
$cert = prospergenics_courses_get_certificate( 5, 10 );
prospergenics_courses_revoke_certificate( $cert );
ok( prospergenics_courses_certificate_is_revoked( $cert ), 'revoked' );
$v = call_user_func( $verify['callback'], new WP_REST_Request( 'GET', array( 'code' => $code ) ) );
ok( $v instanceof WP_REST_Response && 410 === $v->status && false === $v->data['valid'] && true === $v->data['revoked'] && 'This certificate was revoked.' === $v->data['message'], 'verification answers 410 "This certificate was revoked."' );
$r = call_user_func( $mine['callback'], new WP_REST_Request( 'GET', array( 'id' => 10 ) ) );
ok( $r instanceof WP_Error && 'pg_courses_revoked' === $r->code, 'the learner cannot fetch it any more' );
ok( (int) prospergenics_courses_issue_certificate( 5, 10 )->ID === (int) $cert->ID, 'completing again does not issue a fresh certificate' );
ok( false !== strpos( prospergenics_courses_certificate_html( get_post( 10 ), 5, 100, 'b', 'Download' ), 'was revoked' ), 'the course page says it was revoked' );
prospergenics_courses_revoke_certificate( $cert, false );
$v = call_user_func( $verify['callback'], new WP_REST_Request( 'GET', array( 'code' => $code ) ) );
ok( is_array( $v ) && true === $v['valid'], 'staff can make it valid again' );
$src = file_get_contents( $theme . '/inc/courses/certificate.php' );
ok( false !== strpos( $src, "add_action( 'admin_post_pg_courses_revoke'" ) && false !== strpos( $src, "check_admin_referer( 'pg_courses_revoke_'" ) && false !== strpos( $src, "current_user_can( 'edit_others_pg_courses' )" ), 'the revoke form checks a nonce and a staff capability' );
$tpl = file_get_contents( $theme . '/courses-verify.php' );
ok( false !== strpos( $tpl, 'prospergenics_courses_certificate_is_revoked(' ) && false !== strpos( $tpl, 'This certificate was revoked' ) && false !== strpos( $tpl, "current_user_can('edit_others_pg_courses')" ), 'the verify page shows revoked certificates as revoked and offers staff the form' );

echo "Rate limit on the completion endpoint\n";
$s = false;
for ( $i = 0; $i < 60; $i++ ) { $h = prospergenics_courses_rate_hit( $s, 1000 ); $s = $h['state']; }
ok( $h['allowed'], '60 requests in a minute are fine' );
ok( ! prospergenics_courses_rate_hit( $s, 1030 )['allowed'], 'the 61st in the same minute is refused' );
ok( prospergenics_courses_rate_hit( $s, 1060 )['allowed'], 'a new minute starts over' );
$GLOBALS['transients'] = array();
for ( $i = 0; $i < 60; $i++ ) { call_user_func( $complete['callback'], new WP_REST_Request( 'DELETE', array( 'id' => 12 ) ) ); }
$r = call_user_func( $complete['callback'], new WP_REST_Request( 'DELETE', array( 'id' => 12 ) ) );
ok( $r instanceof WP_Error && 'pg_courses_rate_limited' === $r->code && 429 === $r->data['status'], 'the endpoint answers 429 after 60 requests in a minute' );

echo "Instructor pages only exist for instructors\n";
ok( null === prospergenics_courses_public_instructor( 1 ), 'an administrator without published courses has no instructor page' );
ok( null === prospergenics_courses_public_instructor( 5 ) && null === prospergenics_courses_public_instructor( 999 ), 'neither does a learner, or a user that does not exist' );
ok( null === prospergenics_courses_public_instructor( 7 ), 'nor someone who authored a course but cannot teach' );
ok( 2 === prospergenics_courses_public_instructor( 2 )->ID, 'a real instructor does' );
$title = null;
foreach ( $GLOBALS['hooks']['document_title_parts'] as $cb ) { $title = $cb; }
$GLOBALS['qv'] = array( 'pg_courses_view' => 'instructor', 'pg_courses_user' => 1 );
$parts = $title( array( 'title' => 'Page not found' ) );
ok( 'Page not found' === $parts['title'] && false === strpos( implode( ' ', $parts ), 'jengo-admin' ), 'the <title> of /courses/instructor/1/ never shows that user\'s name' );
$GLOBALS['qv']['pg_courses_user'] = 2;
ok( 'Amina Teacher – Instructor' === $title( array( 'title' => '' ) )['title'], 'an instructor\'s page is titled with their name' );
$tpl = file_get_contents( $theme . '/courses-instructor.php' );
ok( false !== strpos( $tpl, 'prospergenics_courses_public_instructor(' ) && strpos( $tpl, 'set_404' ) < strpos( $tpl, 'wp_safe_redirect' ), 'the template 404s before the canonical redirect (which would put the name in the URL)' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All course integrity tests passed\n" );
exit( $failures ? 1 : 0 );

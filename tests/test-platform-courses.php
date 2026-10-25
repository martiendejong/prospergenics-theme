<?php
/**
 * Standalone tests for the Prospergenics Platform courses track (cards #6355-#6364):
 *   - pure helpers (levels, prices, outcomes, progress, video URLs, slugs, certificate codes),
 *   - the capability grant (administrators/editors teach until the instructor role exists),
 *   - every REST route under prospergenics/v1/courses has a real permission callback, and the
 *     learner-only routes refuse logged-out users,
 *   - rewrite rules, templates and assets are in place in the generated theme.
 *
 * Run with: php tests/test-platform-courses.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ────────────────────── minimal WordPress stubs ──────────────────────
define( 'ABSPATH', sys_get_temp_dir() . '/pg-courses-test/' );
define( 'MB_IN_BYTES', 1048576 );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['hooks']      = array();
$GLOBALS['routes']     = array();
$GLOBALS['rewrites']   = array();
$GLOBALS['logged_in']  = false;
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function apply_filters( $hook, $value ) { return $value; }
function do_action( ...$args ) {}
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( $s ) ), '-' ); }
function is_user_logged_in() { return $GLOBALS['logged_in']; }
function current_user_can( $cap ) { return false; }
function add_rewrite_rule( $regex, $query, $after ) { $GLOBALS['rewrites'][ $regex ] = $query; }
function get_option( $k ) { return PROSPERGENICS_COURSES_REWRITE_VERSION; }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $ns . $route ] = $args; }
class WP_Error {
	public $code;
	public function __construct( $code = '', $msg = '', $data = null ) { $this->code = $code; }
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

echo "Loader\n";
$loader = file_get_contents( $theme . '/inc/platform-courses.php' );
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "/inc/platform-courses.php'" ), 'functions.php requires the courses loader' );
require $theme . '/inc/platform-courses.php';
ok( function_exists( 'prospergenics_courses_enrol' ) && class_exists( 'Prospergenics_Courses_Pdf' ), 'loader requires every module' );

echo "Levels, prices, outcomes\n";
ok( array_keys( prospergenics_courses_levels() ) === array( 'beginner', 'intermediate', 'advanced' ), 'three levels' );
ok( 'advanced' === prospergenics_courses_sanitize_level( ' Advanced ' ) && '' === prospergenics_courses_sanitize_level( 'expert' ), 'level sanitised to a known slug or empty' );
ok( 'popular' === prospergenics_courses_sanitize_sort( 'popular' ) && 'newest' === prospergenics_courses_sanitize_sort( "x' OR 1" ), 'sort is newest or popular' );
ok( 0.0 === prospergenics_courses_sanitize_price( -5 ) && 12.35 === prospergenics_courses_sanitize_price( '12.345' ) && 0.0 === prospergenics_courses_sanitize_price( 'abc' ), 'price never negative, two decimals' );
ok( 'Free' === prospergenics_courses_format_price( 0 ) && 'EUR 49' === prospergenics_courses_format_price( 49 ) && 'EUR 9.50' === prospergenics_courses_format_price( 9.5 ), 'price labels' );
ok( array( 'Design a team', 'Measure value' ) === prospergenics_courses_parse_outcomes( "- Design a team\r\n\n  • Measure value  \n" ), 'outcomes: one per line, bullets and blanks dropped' );
ok( 20 === count( prospergenics_courses_parse_outcomes( str_repeat( "x\n", 50 ) ) ), 'at most 20 outcomes' );
ok( array( '<b>x</b>' ) !== prospergenics_courses_parse_outcomes( '<b>x</b>' ), 'outcome tags stripped' );

echo "Progress\n";
ok( 0 === prospergenics_courses_progress_percent( array(), array( 1 ) ), 'no lessons = 0%' );
ok( 66 === prospergenics_courses_progress_percent( array( 1, 2, 3 ), array( 1, 3 ) ), '2 of 3 = 66% (floored)' );
ok( 100 === prospergenics_courses_progress_percent( array( 1, 2 ), array( 2, 1, 99 ) ), 'completed lessons that were deleted do not count' );
ok( 50 === prospergenics_courses_progress_percent( array( 1, 2 ), array( '1' ) ), 'string ids are fine' );
ok( 0 === prospergenics_courses_rate( 0, 0 ) && 33 === prospergenics_courses_rate( 1, 3 ), 'completion rate' );

echo "Video URLs (YouTube/Vimeo only)\n";
$cases = array(
	'https://www.youtube.com/watch?v=dQw4w9WgXcQ'    => array( 'youtube', 'dQw4w9WgXcQ' ),
	'https://youtu.be/dQw4w9WgXcQ'                   => array( 'youtube', 'dQw4w9WgXcQ' ),
	'https://m.youtube.com/watch?v=dQw4w9WgXcQ&t=1'  => array( 'youtube', 'dQw4w9WgXcQ' ),
	'https://www.youtube.com/shorts/dQw4w9WgXcQ'     => array( 'youtube', 'dQw4w9WgXcQ' ),
	'https://vimeo.com/76979871'                     => array( 'vimeo', '76979871' ),
	'https://player.vimeo.com/video/76979871'        => array( 'vimeo', '76979871' ),
	'https://example.com/watch?v=dQw4w9WgXcQ'        => null,
	'javascript:alert(1)//youtube.com/watch?v=abc'   => null,
	'https://youtube.com.evil.test/watch?v=dQw4w9WgXcQ' => null,
	'https://www.youtube.com/watch?v=<script>'       => null,
	''                                               => null,
);
foreach ( $cases as $url => $want ) {
	$got = prospergenics_courses_parse_video_url( $url );
	ok( $want ? ( $got && $got['provider'] === $want[0] && $got['id'] === $want[1] ) : null === $got, 'parse ' . ( '' === $url ? '(empty)' : $url ) );
}
ok( '' === prospergenics_courses_sanitize_video_url( 'https://example.com/a.mp4' ), 'non-provider URLs are not saved' );
$iframe = prospergenics_courses_video_iframe( 'https://youtu.be/dQw4w9WgXcQ', 'A "quoted" <title>' );
ok( false !== strpos( $iframe, 'src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"' ) && false !== strpos( $iframe, 'title="A &quot;quoted&quot; &lt;title&gt;"' ), 'fallback iframe uses youtube-nocookie and escapes the title' );
ok( '' === prospergenics_courses_video_iframe( 'https://example.com/x', 't' ), 'no iframe for other hosts' );

echo "Slugs, codes, instructor URLs\n";
ok( 'verify-course' === prospergenics_courses_safe_slug( 'verify' ) && 'my-course' === prospergenics_courses_safe_slug( 'my' ) && 'soil' === prospergenics_courses_safe_slug( 'soil' ), 'reserved course slugs get a suffix' );
$code = prospergenics_courses_new_code();
ok( prospergenics_courses_is_code( $code ) && ! preg_match( '/[01IO]/', substr( $code, 3 ) ), "new code $code is well formed and unambiguous" );
ok( $code !== prospergenics_courses_new_code(), 'codes are random' );
ok( 'PG-ABCD-EFGH-2345' === prospergenics_courses_normalize_code( " pg-abcd-efgh-2345'<>" ), 'typed codes are normalised' );
ok( ! prospergenics_courses_is_code( 'PG-ABCD-EFGH' ) && ! prospergenics_courses_is_code( 'XX-ABCD-EFGH-2345' ), 'malformed codes rejected' );
ok( '7-martien-de-jong' === prospergenics_courses_instructor_path( 7, 'Martien de Jong' ) && '7' === prospergenics_courses_instructor_path( 7, '***' ), 'instructor path uses id + name, never the login' );

echo "Capabilities\n";
$cap_filter = null;
foreach ( $GLOBALS['hooks']['user_has_cap'] as $cb ) { $cap_filter = $cb; }
$editor = $cap_filter( array( 'edit_posts' => true, 'edit_others_posts' => true ) );
$member = $cap_filter( array( 'read' => true ) );
$author = $cap_filter( array( 'edit_posts' => true, 'publish_posts' => true ) );
ok( ! empty( $editor['edit_pg_courses'] ) && ! empty( $editor['publish_pg_courses'] ) && ! empty( $editor['edit_others_pg_courses'] ), 'editors and administrators get the course capabilities' );
ok( empty( $member['edit_pg_courses'] ) && empty( $author['edit_pg_courses'] ), 'subscribers and authors do not' );
ok( in_array( 'edit_pg_courses', prospergenics_courses_primitive_caps(), true ), 'edit_pg_courses is the instructor capability' );

echo "REST routes\n";
foreach ( $GLOBALS['hooks']['rest_api_init'] as $cb ) { $cb(); }
$expect = array( '/courses', '/courses/(?P<id>\d+)', '/courses/(?P<id>\d+)/curriculum', '/courses/(?P<id>\d+)/thumbnail', '/courses/(?P<id>\d+)/modules',
	'/courses/modules/(?P<id>\d+)', '/courses/modules/(?P<id>\d+)/lessons', '/courses/lessons/(?P<id>\d+)', '/courses/(?P<id>\d+)/enrolment',
	'/courses/lessons/(?P<id>\d+)/completion', '/courses/(?P<id>\d+)/certificate', '/courses/me', '/courses/certificates/(?P<code>[A-Za-z0-9-]+)', '/courses/instructor/stats' );
foreach ( $expect as $r ) {
	ok( isset( $GLOBALS['routes'][ 'prospergenics/v1' . $r ] ), "route $r registered" );
}
$endpoints = 0;
$bad       = array();
foreach ( $GLOBALS['routes'] as $route => $args ) {
	ok( 0 === strpos( $route, 'prospergenics/v1/courses' ), "$route is under prospergenics/v1/courses" );
	foreach ( isset( $args['methods'] ) ? array( $args ) : $args as $ep ) {
		$endpoints++;
		$pc = isset( $ep['permission_callback'] ) ? $ep['permission_callback'] : null;
		if ( ! $pc || '__return_true' === $pc || ! is_callable( $pc ) ) {
			$bad[] = $route . ' ' . $ep['methods'];
		}
	}
}
ok( $endpoints >= 25 && ! $bad, "all $endpoints endpoints have a real permission_callback" . ( $bad ? ': missing on ' . implode( ', ', $bad ) : '' ) );
ok( false === strpos( file_get_contents( $theme . '/inc/courses/rest.php' ), '__return_true' ), 'no __return_true in the REST module' );

$GLOBALS['logged_in'] = false;
$learner_routes = array( '/courses/(?P<id>\d+)/enrolment', '/courses/lessons/(?P<id>\d+)/completion', '/courses/(?P<id>\d+)/certificate', '/courses/me' );
foreach ( $learner_routes as $r ) {
	$args = $GLOBALS['routes'][ 'prospergenics/v1' . $r ];
	foreach ( isset( $args['methods'] ) ? array( $args ) : $args as $ep ) {
		$res = call_user_func( $ep['permission_callback'], new WP_REST_Request( 'POST', array( 'id' => 1 ) ) );
		ok( $res instanceof WP_Error && 'rest_not_logged_in' === $res->code, "$r {$ep['methods']} refuses logged-out users" );
	}
}
$create = $GLOBALS['routes']['prospergenics/v1/courses'][1];
ok( call_user_func( $create['permission_callback'] ) instanceof WP_Error, 'creating a course needs a logged-in instructor' );
$list = $GLOBALS['routes']['prospergenics/v1/courses'][0];
ok( true === call_user_func( $list['permission_callback'], new WP_REST_Request( 'GET' ) ), 'published courses are public' );
ok( call_user_func( $list['permission_callback'], new WP_REST_Request( 'GET', array( 'status' => 'draft' ) ) ) instanceof WP_Error, 'drafts are not public' );
$verify = $GLOBALS['routes']['prospergenics/v1/courses/certificates/(?P<code>[A-Za-z0-9-]+)'];
ok( true === call_user_func( $verify['permission_callback'], new WP_REST_Request( 'GET' ) ) && 'GET' === $verify['methods'], 'certificate verification is public and read-only' );
$src = file_get_contents( $theme . '/inc/courses/enrolment.php' ) . file_get_contents( $theme . '/inc/courses/rest.php' );
ok( 0 === preg_match( '/prospergenics_courses_(enrol|unenrol|set_lesson_complete)\(\s*+(?!get_current_user_id\(\)|\$user_id)/', $src ), 'enrolment, unenrolment and completion always act on the current user' );
foreach ( array( 'pg_courses_enrol', 'pg_courses_unenrol', 'pg_courses_complete', 'pg_courses_status' ) as $action ) {
	$all = $src . file_get_contents( $theme . '/inc/courses/instructor.php' );
	ok( false !== strpos( $all, "add_action( 'admin_post_{$action}'" ) && false !== strpos( $all, "check_admin_referer( '{$action}_'" ), "form action $action checks a nonce" );
}

echo "Routing\n";
foreach ( $GLOBALS['hooks']['init'] as $cb ) {
	$rf = new ReflectionFunction( $cb );
	if ( false !== strpos( file_get_contents( $rf->getFileName() ), 'add_rewrite_rule' ) && $rf->getFileName() === realpath( $theme . '/inc/courses/routing.php' ) ) {
		$cb();
	}
}
$rw = $GLOBALS['rewrites'];
ok( isset( $rw['^courses/([^/]+)/lessons/([^/]+)/?$'] ) && false !== strpos( $rw['^courses/([^/]+)/lessons/([^/]+)/?$'], 'pg_lesson=$matches[2]' ), 'lesson URLs /courses/<course>/lessons/<lesson>/' );
foreach ( array( '^courses/my/?$', '^courses/verify/([A-Za-z0-9-]+)/?$', '^courses/([^/]+)/certificate/?$', '^instructor/dashboard/?$', '^instructor/courses/new/?$', '^instructor/courses/([0-9]+)/edit/?$' ) as $r ) {
	ok( isset( $rw[ $r ] ), "rewrite $r" );
}
// Every fixed word the track routes under /courses/<word>/ must be reserved, so no course can take it.
foreach ( array_keys( $rw ) as $regex ) {
	if ( preg_match( '#^\^courses/([a-z]+)/#', $regex, $m ) ) {
		ok( in_array( $m[1], prospergenics_courses_reserved_slugs(), true ), "course slug \"{$m[1]}\" is reserved" );
	}
}

echo "Templates and assets\n";
$templates = array( 'archive-pg_course.php', 'single-pg_course.php', 'single-pg_lesson.php', 'courses-my.php', 'courses-verify.php', 'courses-instructor.php', 'courses-dashboard.php', 'courses-wizard.php' );
foreach ( $templates as $t ) {
	$file = $theme . '/' . $t;
	ok( file_exists( $file ), "$t exists in the generated theme" );
	$s = file_exists( $file ) ? file_get_contents( $file ) : '';
	ok( false !== strpos( $s, "defined('ABSPATH') || exit;" ) && false !== strpos( $s, 'get_header()' ) && false !== strpos( $s, 'get_footer()' ), "$t is guarded and uses the shared header/footer" );
	ok( file_get_contents( $root . '/design/new-hero-2026-10/wp/' . $t ) === $s, "$t is generated from design/new-hero-2026-10/wp/" );
}
foreach ( array( 'courses.css', 'courses.js' ) as $a ) {
	ok( file_exists( $theme . '/assets/platform/' . $a ), "assets/platform/$a shipped" );
}
$css = file_get_contents( $theme . '/assets/platform/courses.css' );
ok( false !== strpos( $css, '@media (max-width:720px)' ) && false !== strpos( $css, 'minmax(min(100%,290px),1fr)' ), 'course grid and tables collapse on phones' );
ok( false !== strpos( file_get_contents( $theme . '/inc/courses/routing.php' ), "prospergenics_courses_is_course_page()" ), 'assets are only enqueued on course pages' );
ok( false !== strpos( file_get_contents( $theme . '/inc/courses/data-model.php' ), "'prospergenics_platform_content_saved'" ), 'content saves fire prospergenics_platform_content_saved' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All courses tests passed\n" );
exit( $failures ? 1 : 0 );

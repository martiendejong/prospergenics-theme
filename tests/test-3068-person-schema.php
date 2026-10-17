<?php
/**
 * Standalone test for JengoWork task 3068: a Person JSON-LD node for Martien de Jong must be
 * emitted on /about/, /martien/, and his team_member singular page (and nowhere else), and the
 * same Person must be referenced as `author` on each Course entry in
 * prospergenics_trainings_course_schema().
 *
 * This extracts the real function bodies straight out of functions.php via regex, so the test
 * always exercises the actual current source rather than a copy that can drift from it.
 *
 * Run with: php tests/test-3068-person-schema.php
 */

// ────────────────────── WP core function stubs ───────────────────────────
function get_bloginfo( $key = '' ) {
	if ( $key === 'name' ) return 'Prospergenics';
	return '';
}
function home_url( $path = '/' ) { return 'https://prospergenics.com' . $path; }
function add_action( ...$args ) {}
function wp_json_encode( $data ) { return json_encode( $data ); }

$GLOBALS['_is_page'] = false;
function is_page( $page = '' ) { return $GLOBALS['_is_page'] === $page; }

$GLOBALS['_is_singular'] = false;
function is_singular( $post_types = '' ) { return $GLOBALS['_is_singular'] === $post_types; }

$GLOBALS['_queried_post_name'] = '';
function get_queried_object_id() { return 1; }
function get_post_field( $field, $post_id ) {
	if ( $field === 'post_name' ) return $GLOBALS['_queried_post_name'];
	return '';
}

// Stubs for prospergenics_trainings_course_schema()'s own existing dependencies.
$GLOBALS['_offerings'] = array();
function prospergenics_get_trainings_offerings() { return $GLOBALS['_offerings']; }
function get_permalink( $post ) { return 'https://prospergenics.com/trainings/' . $post . '/'; }
function get_the_title( $post ) { return 'Offering ' . $post; }
function prospergenics_trainings_offering_summary( $post ) { return 'Summary of ' . $post; }

// ─────────────────── extract the real functions from functions.php ────────
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );

if ( ! preg_match( '/(function prospergenics_martien_person_node\(\).*?)\r?\nadd_action\( \'wp_head\', \'prospergenics_martien_person_schema\'/s', $source, $m ) ) {
	fwrite( STDERR, "FAIL: could not locate the Martien Person schema functions in functions.php\n" );
	exit( 1 );
}
eval( $m[1] );

if ( ! preg_match( '/(function prospergenics_trainings_course_schema\(\)\s*\{.*?\r?\n\})\r?\nadd_action\( \'wp_head\', \'prospergenics_trainings_course_schema\'/s', $source, $m2 ) ) {
	fwrite( STDERR, "FAIL: could not locate prospergenics_trainings_course_schema() in functions.php\n" );
	exit( 1 );
}
eval( $m2[1] );

// ─────────────────────────────────────────────────────────────────────────
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

function render( $callback ) {
	ob_start();
	$callback();
	return ob_get_clean();
}

function reset_page_state() {
	$GLOBALS['_is_page'] = false;
	$GLOBALS['_is_singular'] = false;
	$GLOBALS['_queried_post_name'] = '';
}

function assert_person_schema( $output ) {
	ok( strpos( $output, 'application/ld+json' ) !== false, 'a JSON-LD script tag is emitted' );
	if ( ! preg_match( '/<script[^>]*prospergenics-person-schema[^>]*>(.*?)<\/script>/s', $output, $jsonMatch ) ) {
		ok( false, 'could not locate the person schema <script> tag in the output' );
		return;
	}
	$data = json_decode( $jsonMatch[1], true );
	ok( is_array( $data ), 'schema JSON decodes successfully' );
	ok( ( $data['@type'] ?? '' ) === 'Person', '@type is Person' );
	ok( ( $data['name'] ?? '' ) === 'Martien de Jong', 'name is Martien de Jong' );
	ok( ( $data['url'] ?? '' ) === 'https://prospergenics.com/members/martien-de-jong/', 'url points to the members bio page' );
	ok( ( $data['jobTitle'] ?? '' ) === 'Founder', 'jobTitle is Founder' );
}

echo "[Test 1] Unrelated page: no Person schema is emitted\n";
reset_page_state();
$output = render( 'prospergenics_martien_person_schema' );
ok( $output === '', 'output is empty on an unrelated page' );

echo "\n[Test 2] /about/ page: Person schema is emitted\n";
reset_page_state();
$GLOBALS['_is_page'] = 'about';
$output = render( 'prospergenics_martien_person_schema' );
assert_person_schema( $output );

echo "\n[Test 3] /martien/ page: Person schema is emitted\n";
reset_page_state();
$GLOBALS['_is_page'] = 'martien';
$output = render( 'prospergenics_martien_person_schema' );
assert_person_schema( $output );

echo "\n[Test 4] Martien's team_member singular page: Person schema is emitted\n";
reset_page_state();
$GLOBALS['_is_singular'] = 'team_member';
$GLOBALS['_queried_post_name'] = 'martien-de-jong';
$output = render( 'prospergenics_martien_person_schema' );
assert_person_schema( $output );

echo "\n[Test 5] A different team_member singular page: no Person schema is emitted\n";
reset_page_state();
$GLOBALS['_is_singular'] = 'team_member';
$GLOBALS['_queried_post_name'] = 'someone-else';
$output = render( 'prospergenics_martien_person_schema' );
ok( $output === '', 'output is empty for a non-Martien team member page' );

echo "\n[Test 6] Trainings page: each Course entry references the same Person as author\n";
reset_page_state();
$GLOBALS['_is_page'] = 'trainings';
$GLOBALS['_offerings'] = array( 101, 102 );
$output = render( 'prospergenics_trainings_course_schema' );
if ( ! preg_match( '/<script[^>]*prospergenics-trainings-course-schema[^>]*>(.*?)<\/script>/s', $output, $courseMatch ) ) {
	ok( false, 'could not locate the course schema <script> tag in the output' );
} else {
	$data = json_decode( $courseMatch[1], true );
	ok( is_array( $data ) && ! empty( $data['@graph'] ), 'course schema JSON decodes with a non-empty @graph' );
	foreach ( $data['@graph'] ?? array() as $course ) {
		$author = $course['author'] ?? null;
		ok( is_array( $author ), 'course "' . ( $course['name'] ?? '?' ) . '" has an author field' );
		ok( ( $author['@type'] ?? '' ) === 'Person', 'author @type is Person' );
		ok( ( $author['name'] ?? '' ) === 'Martien de Jong', 'author name is Martien de Jong' );
		ok( ( $author['url'] ?? '' ) === 'https://prospergenics.com/members/martien-de-jong/', 'author url points to the members bio page' );
	}
}

echo "\n" . ( $failures === 0 ? "All assertions passed.\n" : "$failures assertion(s) FAILED.\n" );
exit( $failures === 0 ? 0 : 1 );

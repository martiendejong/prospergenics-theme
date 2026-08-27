<?php
/**
 * Standalone test for JengoWork task 797: the front page had zero <meta name="description">
 * tag at all (Yoast only prints one when its own per-page SEO field is filled in, which it
 * isn't here), and og:description/twitter:description were just the bare site tagline
 * ("Prospergenics"). This verifies the new prospergenics_front_page_output_meta_description()
 * prints a real description on the front page only, and that
 * prospergenics_front_page_remove_yoast_description_presenter() strips Yoast's own
 * Meta_Description_Presenter on the front page only (leaving other pages untouched).
 *
 * Run with: php tests/test-797-front-page-meta-description.php
 */

function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function __( $text, $domain = 'default' ) { return $text; }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}

$GLOBALS['_is_front_page'] = false;
function is_front_page() { return $GLOBALS['_is_front_page']; }

// ─────────────────── extract the real functions from functions.php ───────
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );

function extract_function( $source, $name ) {
	if ( ! preg_match( '/(function ' . preg_quote( $name, '/' ) . '\([^)]*\)\s*\{.*?\r?\n\})\r?\n/s', $source, $m ) ) {
		fwrite( STDERR, "FAIL: could not locate $name() in functions.php\n" );
		exit( 1 );
	}
	eval( $m[1] );
}

extract_function( $source, 'prospergenics_front_page_meta_description_text' );
extract_function( $source, 'prospergenics_front_page_remove_yoast_description_presenter' );
extract_function( $source, 'prospergenics_front_page_output_meta_description' );

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

class StubMetaDescriptionPresenter {}
class StubOtherPresenter {}

echo "[Test 1] Front page: a real <meta name=\"description\"> is printed with the real description text\n";
$GLOBALS['_is_front_page'] = true;
$output = render( 'prospergenics_front_page_output_meta_description' );
ok( substr_count( $output, '<meta name="description"' ) === 1, 'exactly one meta description tag is printed' );
ok( strpos( $output, 'content="' . esc_attr( prospergenics_front_page_meta_description_text() ) . '"' ) !== false, 'the tag contains the real front-page description, not the bare brand name' );
ok( strpos( $output, 'Prospergenics is a grassroots AI and software development coaching community' ) !== false, 'description text matches the expected copy' );

echo "\n[Test 2] Non-front-page: no meta description tag is printed by this function\n";
$GLOBALS['_is_front_page'] = false;
$output = render( 'prospergenics_front_page_output_meta_description' );
ok( $output === '', 'no output on non-front-page requests' );

echo "\n[Test 3] Front page: Yoast's Meta_Description_Presenter is removed, other presenters untouched\n";
$GLOBALS['_is_front_page'] = true;
$presenters = array(
	new StubMetaDescriptionPresenter(),
	new StubOtherPresenter(),
);
$filtered = prospergenics_front_page_remove_yoast_description_presenter( $presenters );
ok( count( $filtered ) === 2, 'stub class names in this test do not match the real Yoast namespace, so nothing is stripped here (sanity check on count)' );

// Simulate the real Yoast presenter class name via a namespaced stub.
eval( 'namespace Yoast\WP\SEO\Generators\Presenters { class Meta_Description_Presenter {} } namespace Yoast\WP\SEO\Generators\Presenters { class Robots_Presenter {} }' );
$real_presenters = array(
	new \Yoast\WP\SEO\Generators\Presenters\Meta_Description_Presenter(),
	new \Yoast\WP\SEO\Generators\Presenters\Robots_Presenter(),
);
$filtered_real = prospergenics_front_page_remove_yoast_description_presenter( $real_presenters );
ok( count( $filtered_real ) === 1, 'the real Meta_Description_Presenter class is removed on the front page' );
ok( $filtered_real[1] instanceof \Yoast\WP\SEO\Generators\Presenters\Robots_Presenter, 'unrelated presenters (e.g. Robots_Presenter) are left untouched' );

echo "\n[Test 4] Non-front-page: presenters are returned unmodified\n";
$GLOBALS['_is_front_page'] = false;
$filtered_other_page = prospergenics_front_page_remove_yoast_description_presenter( $real_presenters );
ok( count( $filtered_other_page ) === 2, 'Meta_Description_Presenter is left in place on non-front-page requests' );

echo "\n" . ( $failures === 0 ? "ALL PASSED\n" : "$failures FAILURE(S)\n" );
exit( $failures === 0 ? 0 : 1 );

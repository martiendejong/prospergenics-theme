<?php
/**
 * Standalone test for JengoWork task 2942: prospergenics_trainings_meta_description_text()
 * must never emit a <meta name="description"> longer than ~160 characters, the point at
 * which Google truncates it in search results. The pre-existing version concatenated every
 * training offering's title unconditionally and had already drifted to 210 characters live
 * once a 3rd, longer-titled offering (published by a later task) existed.
 *
 * This extracts the real functions straight out of functions.php via a regex, so the test
 * always exercises the actual current source rather than a copy that can drift from it.
 *
 * Run with: php tests/test-2942-meta-description-length.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ );
}

$GLOBALS['_posts_by_slug'] = array();
function get_posts( $args ) {
	$slug = $args['name'];
	return isset( $GLOBALS['_posts_by_slug'][ $slug ] ) ? array( $GLOBALS['_posts_by_slug'][ $slug ] ) : array();
}
function wp_list_pluck( $list, $field ) {
	return array_map( function ( $item ) use ( $field ) {
		return is_object( $item ) ? $item->$field : $item[ $field ];
	}, $list );
}
function __( $text, $domain = 'default' ) {
	return $text;
}

// ─────────────── extract the real functions from functions.php ───────────
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
foreach ( array( 'prospergenics_get_trainings_offerings', 'prospergenics_trainings_meta_description_text' ) as $fn ) {
	if ( ! preg_match( '/(function ' . $fn . '\(\)\s*\{.*?\r?\n\})/s', $source, $m ) ) {
		fwrite( STDERR, "FAIL: could not locate $fn() in functions.php\n" );
		exit( 1 );
	}
	eval( $m[1] );
}

// ─────────────────────────────────────────────────────────────────────────
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}
function make_offering( $slug, $title ) {
	return (object) array( 'ID' => 1, 'post_title' => $title, 'post_excerpt' => '', 'post_content' => '' );
}

echo "[Test 1] the real 3 live training offerings stay under 160 characters\n";
$GLOBALS['_posts_by_slug'] = array(
	'digital-technology'          => make_offering( 'digital-technology', 'Digital Technology' ),
	'ai-and-technology-training'  => make_offering( 'ai-and-technology-training', 'AI and Technology Training' ),
	'claude-code-cursor-coaching' => make_offering( 'claude-code-cursor-coaching', 'Claude Code &#038; Cursor Coaching for Dutch Teams' ),
);
$description = prospergenics_trainings_meta_description_text();
ok( strlen( $description ) <= 160, 'length (' . strlen( $description ) . ') is <= 160 chars' );
ok( strpos( $description, 'Digital Technology' ) !== false, 'still names at least the first real offering' );
echo "  -> \"$description\"\n";

echo "\n[Test 2] a single offering with a pathologically long title still stays under 160\n";
$GLOBALS['_posts_by_slug'] = array(
	'digital-technology'          => make_offering( 'digital-technology', str_repeat( 'Extremely Long Training Program Name ', 5 ) ),
	'ai-and-technology-training'  => make_offering( 'ai-and-technology-training', 'AI and Technology Training' ),
	'claude-code-cursor-coaching' => make_offering( 'claude-code-cursor-coaching', 'Claude Code &#038; Cursor Coaching for Dutch Teams' ),
);
$description2 = prospergenics_trainings_meta_description_text();
ok( strlen( $description2 ) <= 160, 'length (' . strlen( $description2 ) . ') is <= 160 chars even with a very long offering title' );

echo "\n[Test 3] a single short offering does not need an \"and more\" suffix\n";
$GLOBALS['_posts_by_slug'] = array(
	'digital-technology' => make_offering( 'digital-technology', 'Digital Technology' ),
);
$description3 = prospergenics_trainings_meta_description_text();
ok( strlen( $description3 ) <= 160, 'length (' . strlen( $description3 ) . ') is <= 160 chars' );
ok( strpos( $description3, 'and more' ) === false, 'no "and more" suffix when every offering already fits' );

echo "\n[Test 4] no offerings at all returns an empty string (unchanged behavior)\n";
$GLOBALS['_posts_by_slug'] = array();
ok( prospergenics_trainings_meta_description_text() === '', 'empty offerings list returns empty string' );

echo "\n" . ( $failures === 0 ? "ALL PASSED\n" : "$failures FAILURE(S)\n" );
exit( $failures === 0 ? 0 : 1 );

<?php
/**
 * Standalone test for JengoWork task 1122: prospergenics_speakable_selectors() must add the
 * homepage "What is Prospergenics?" intro summary selector on the front page only, and must
 * leave any selectors the SEO God plugin already built (e.g. the FAQ answer selector) untouched.
 *
 * This extracts the real function body straight out of functions.php via a regex, so the test
 * always exercises the actual current source rather than a copy that can drift from it.
 *
 * Run with: php tests/test-1122-speakable-selectors.php
 */

// ────────────────────── WP core function stubs ───────────────────────────
$GLOBALS['_is_front_page'] = false;
function is_front_page() { return $GLOBALS['_is_front_page']; }

// ─────────────────── extract the real function from functions.php ────────
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
if ( ! preg_match( '/(function prospergenics_speakable_selectors\(.*?\r?\n\})\r?\nadd_filter\( \'seo_god_schema_speakable_selectors\'/s', $source, $m ) ) {
	fwrite( STDERR, "FAIL: could not locate prospergenics_speakable_selectors() in functions.php\n" );
	exit( 1 );
}
eval( $m[1] );

// ─────────────────────────────────────────────────────────────────────────
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// Test 1: on the front page, the About summary selector is appended.
$GLOBALS['_is_front_page'] = true;
$result = prospergenics_speakable_selectors( array( '.seo-god-faq-answer-text' ) );
ok( in_array( '#about-intro .intro-content', $result, true ), 'front page: About summary selector is added' );
ok( in_array( '.seo-god-faq-answer-text', $result, true ), 'front page: the FAQ selector the plugin already added is preserved' );
ok( count( $result ) === 2, 'front page: exactly two selectors, nothing duplicated or dropped' );

// Test 2: an empty incoming selector list (no FAQ enabled) still gets the About summary selector.
$result = prospergenics_speakable_selectors( array() );
ok( $result === array( '#about-intro .intro-content' ) , 'front page, no FAQ selector: About summary selector still added on its own' );

// Test 3: not the front page — selectors pass through untouched.
$GLOBALS['_is_front_page'] = false;
$result = prospergenics_speakable_selectors( array( '.seo-god-faq-answer-text' ) );
ok( $result === array( '.seo-god-faq-answer-text' ), 'non-front page: selectors are returned unchanged, no About summary selector added' );

echo "\n" . ( $failures === 0 ? "ALL TESTS PASSED\n" : "$failures TEST(S) FAILED\n" );
exit( $failures === 0 ? 0 : 1 );

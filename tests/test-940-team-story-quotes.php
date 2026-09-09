<?php
/**
 * Standalone test for JengoWork task 940: prospergenics_get_team_story_quotes() must
 * return the 3 real Prospergenics team quotes (Sandra, Lessy, Frank) Martien supplied,
 * and front-page.php's rendering of them must NOT be wrapped in any Review or
 * AggregateRating JSON-LD -- these are employee/team-culture quotes, not customer
 * reviews (see the function's own doc comment in functions.php and the sibling
 * decision on martiendejong.nl task 925/PR #865).
 *
 * This extracts the real function body straight out of functions.php via a regex, so
 * the test always exercises the actual current source rather than a copy that can
 * drift from it.
 *
 * Run with: php tests/test-940-team-story-quotes.php
 */

// ────────────────────── WP core function stubs ───────────────────────────
function __( $text, $domain = 'default' ) { return $text; }

// ─────────────────── extract the real function from functions.php ────────
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
if ( ! preg_match( '/(function prospergenics_get_team_story_quotes\(\)\s*\{.*?\r?\n\})\r?\n\r?\n\/\*\*/s', $source, $m ) ) {
	fwrite( STDERR, "FAIL: could not locate prospergenics_get_team_story_quotes() in functions.php\n" );
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

echo "[Test 1] prospergenics_get_team_story_quotes() returns the 3 real quotes\n";
$quotes = prospergenics_get_team_story_quotes();
ok( is_array( $quotes ) && 3 === count( $quotes ), 'returns exactly 3 quotes' );

$authors = wp_list_pluck_fallback( $quotes, 'author' );
function wp_list_pluck_fallback( $arr, $key ) {
	return array_map( function ( $row ) use ( $key ) { return $row[ $key ] ?? null; }, $arr );
}
ok( in_array( 'Sandra', $authors, true ), 'includes Sandra' );
ok( in_array( 'Lessy', $authors, true ), 'includes Lessy' );
ok( in_array( 'Frank', $authors, true ), 'includes Frank' );

foreach ( $quotes as $q ) {
	ok( ! empty( $q['quote'] ) && ! empty( $q['role'] ) && ! empty( $q['author'] ), "quote for {$q['author']} has author/role/quote all non-empty" );
	ok( false === stripos( $q['role'], 'client' ) && false === stripos( $q['role'], 'trainee' ), "{$q['author']}'s role does not claim to be a client/trainee (it's a team role)" );
}

echo "\n[Test 2] front-page.php renders the quotes as plain content, with no Review/AggregateRating schema anywhere on the page\n";
$front_page_source = file_get_contents( dirname( __DIR__ ) . '/front-page.php' );
ok( false !== strpos( $front_page_source, 'prospergenics_get_team_story_quotes()' ), 'front-page.php calls prospergenics_get_team_story_quotes()' );
ok( false !== strpos( $front_page_source, 'Team Story' ), 'front-page.php has a "Team Story" section heading' );

// The whole theme (functions.php + front-page.php) must never pair this content with
// Review/AggregateRating JSON-LD -- grep both files for an actual schema @type
// declaration, not just the new code, since a stray occurrence anywhere would
// misrepresent these quotes as customer reviews. (A plain word match would also flag
// this function's own explanatory doc comment, which discusses AggregateRating only to
// say it deliberately isn't used -- so match the schema key/value shape instead.)
$combined = $front_page_source . "\n" . $source;
ok( 0 === preg_match( '/[\'"]@type[\'"]\s*(=>|:)\s*[\'"]Review[\'"]/', $combined ), 'no \'@type\' => \'Review\' schema declaration anywhere in functions.php or front-page.php' );
ok( 0 === preg_match( '/[\'"]@type[\'"]\s*(=>|:)\s*[\'"]AggregateRating[\'"]/', $combined ), 'no \'@type\' => \'AggregateRating\' schema declaration anywhere in functions.php or front-page.php' );

echo "\n" . ( $failures === 0 ? "All assertions passed.\n" : "$failures assertion(s) FAILED.\n" );
exit( $failures === 0 ? 0 : 1 );

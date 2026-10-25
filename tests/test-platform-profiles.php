<?php
/**
 * Standalone test for the Prospergenics Platform profiles track (cards #6339–#6340, #6343–#6354).
 *
 * The rules that decide what a visitor may see, how a profile URL is made and what counts as a
 * valid entry are pure functions in inc/profiles/helpers.php, tested here directly. The wiring
 * (REST permission callbacks, the content-saved hook, no users endpoint) is checked in the
 * source, since it needs WordPress to run.
 *
 * Run with: php tests/test-platform-profiles.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/wp-theme/prospergenics-2026/' );
$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

require $theme . '/inc/profiles/helpers.php';
require $theme . '/inc/profiles/skills-vocabulary.php';

echo "[Test 1] privacy defaults and levels (#6351)\n";
$d = prospergenics_profiles_privacy_defaults();
ok( 'public' === $d['profile'] && 'members' === $d['email'] && 'private' === $d['phone'], 'defaults: profile public, email members, phone hidden' );
$n = prospergenics_profiles_normalize_privacy( array( 'email' => 'public', 'phone' => 'everyone', 'bogus' => 'public', 'skills' => array( 'x' ) ) );
ok( 'public' === $n['email'] && 'private' === $n['phone'] && ! isset( $n['bogus'] ) && 'public' === $n['skills'], 'unknown levels/sections dropped, valid ones kept' );
ok( prospergenics_profiles_normalize_privacy( 'garbage' ) === $d, 'non-array falls back to defaults' );

echo "[Test 2] who sees what\n";
$owner = 7; $member = 9;
ok( prospergenics_profiles_level_allows( 'public', 0, $owner, false ), 'public: logged-out visitor' );
ok( ! prospergenics_profiles_level_allows( 'members', 0, $owner, false ), 'members: not a visitor' );
ok( prospergenics_profiles_level_allows( 'members', $member, $owner, false ), 'members: any member' );
ok( ! prospergenics_profiles_level_allows( 'connections', $member, $owner, false ), 'connections: not a non-connection' );
ok( prospergenics_profiles_level_allows( 'connections', $member, $owner, true ), 'connections: a connection' );
ok( ! prospergenics_profiles_level_allows( 'connections', 0, $owner, true ), 'connections: never a visitor' );
ok( ! prospergenics_profiles_level_allows( 'private', $member, $owner, true ), 'private: nobody else' );
ok( prospergenics_profiles_level_allows( 'private', $owner, $owner, false ), 'owner sees everything' );
ok( ! prospergenics_profiles_level_allows( 'nonsense', $member, $owner, true ), 'unknown level denies' );
$vis = prospergenics_profiles_visible_sections( array( 'profile' => 'members', 'skills' => 'public' ), 0, $owner, false );
ok( ! $vis['profile'] && ! $vis['skills'] && ! $vis['email'], 'a hidden profile hides every section, even public ones' );
$vis = prospergenics_profiles_visible_sections( array(), 0, $owner, false );
ok( $vis['profile'] && $vis['experience'] && ! $vis['email'] && ! $vis['phone'], 'visitor with defaults: no email, no phone' );
$vis = prospergenics_profiles_visible_sections( array(), $member, $owner, false );
ok( $vis['email'] && ! $vis['phone'], 'member with defaults: email yes, phone no' );

echo "[Test 3] profile URL slugs (#6350)\n";
ok( 'alice-wanjiru' === prospergenics_profiles_slugify( '  Alice  Wanjiru ' ), 'slugify name' );
ok( 'jose-nunez' === prospergenics_profiles_slugify( 'José Núñez' ), 'accents transliterated' );
ok( '' === prospergenics_profiles_slugify( '!!!' ), 'nothing usable gives an empty slug' );
ok( 'alice-wanjiru' === prospergenics_profiles_slug_base( 'Alice', 'Wanjiru', 'admin', 'admin', 'admin', 1 ), 'first + last name preferred' );
$base = prospergenics_profiles_slug_base( '', '', 'admin', 'admin', 'admin', 1 );
ok( 0 === strpos( $base, 'member-' ) && false === strpos( $base, 'admin' ), 'a display name equal to the login is never used (no login leak): ' . $base );
ok( 0 === strpos( prospergenics_profiles_slug_base( '', '', 'me@x.org', 'bob', 'bob', 2 ), 'member-' ), 'an email display name is never used' );
ok( 'mama-mboga' === prospergenics_profiles_slug_base( '', '', 'Mama Mboga', 'mm2026', 'mm2026', 3 ), 'a real display name is used' );
$taken = array( 'alice-wanjiru' => 1, 'alice-wanjiru-2' => 1 );
ok( 'alice-wanjiru-3' === prospergenics_profiles_unique_slug( 'alice-wanjiru', function ( $s ) use ( $taken ) { return isset( $taken[ $s ] ); } ), 'de-duplicated with -N' );
ok( 'settings-1' === prospergenics_profiles_unique_slug( 'settings', function () { return false; } ), 'reserved slugs avoided' );

echo "[Test 4] experience validation and order (#6345)\n";
list( $e, $err ) = prospergenics_profiles_validate_experience( array( 'title' => 'Trainer', 'company' => 'PG', 'start' => '2021-07', 'end' => '2020-01', 'current' => '1' ) );
ok( ! $err && '' === $e['end'] && $e['current'], 'current role: no end date stored' );
list( , $err ) = prospergenics_profiles_validate_experience( array( 'title' => 'T', 'company' => 'C', 'start' => '2021-07', 'end' => '2020-01' ) );
ok( isset( $err['end'] ), 'end before start rejected' );
list( , $err ) = prospergenics_profiles_validate_experience( array( 'title' => '', 'company' => '', 'start' => '2021-13' ) );
ok( isset( $err['title'], $err['company'], $err['start'], $err['end'] ), 'required fields and invalid month reported' );
list( $e ) = prospergenics_profiles_validate_experience( array( 'title' => str_repeat( 'x', 300 ), 'company' => 'C', 'start' => '2020-01', 'current' => 'false' ) );
ok( 120 === strlen( $e['title'] ) && ! $e['current'], 'title clipped; "false" is not current' );
$sorted = prospergenics_profiles_sort_experience( array(
	array( 'start' => '2019-03', 'current' => false, 'title' => 'a' ),
	array( 'start' => '2021-07', 'current' => true, 'title' => 'b' ),
	array( 'start' => '2021-07', 'current' => false, 'title' => 'c' ),
) );
ok( 'b,c,a' === implode( ',', array_column( $sorted, 'title' ) ), 'newest start first, current first on a tie' );
ok( 'Mar 2019' === prospergenics_profiles_format_month( '2019-03' ) && '' === prospergenics_profiles_format_month( '' ), 'month format' );

echo "[Test 5] education validation and order (#6346)\n";
list( $e, $err ) = prospergenics_profiles_validate_education( array( 'institution' => 'Strathmore', 'start_year' => '2018', 'end_year' => '2022' ) );
ok( ! $err && 2022 === $e['end_year'], 'valid entry' );
list( , $err ) = prospergenics_profiles_validate_education( array( 'institution' => '', 'start_year' => '2020', 'end_year' => '2010' ) );
ok( isset( $err['institution'], $err['end_year'] ), 'missing institution and reversed years rejected' );
ok( 0 === prospergenics_profiles_clean_year( '1800' ) && 0 === prospergenics_profiles_clean_year( '20x0' ), 'bad years become empty' );
$sorted = prospergenics_profiles_sort_education( array(
	array( 'institution' => 'a', 'start_year' => 2010, 'end_year' => 2014 ),
	array( 'institution' => 'b', 'start_year' => 2023, 'end_year' => 0 ),
	array( 'institution' => 'c', 'start_year' => 2015, 'end_year' => 2019 ),
) );
ok( 'b,c,a' === implode( ',', array_column( $sorted, 'institution' ) ), 'end year descending, ongoing first' );

echo "[Test 6] skills (#6347, #6348)\n";
$sorted = prospergenics_profiles_sort_skills( array(
	array( 'name' => 'Python', 'pinned' => false, 'endorsements' => 5 ),
	array( 'name' => 'Agriculture', 'pinned' => true, 'endorsements' => 0 ),
	array( 'name' => 'Teaching', 'pinned' => false, 'endorsements' => 5 ),
	array( 'name' => 'Welding', 'pinned' => false, 'endorsements' => 9 ),
) );
ok( 'Agriculture,Welding,Python,Teaching' === implode( ',', array_column( $sorted, 'name' ) ), 'pinned, then most endorsed, then by name' );
$vocab = prospergenics_profiles_skills_vocabulary();
ok( count( $vocab ) >= 200 && count( $vocab ) === count( array_unique( $vocab ) ), 'vocabulary: 200+ unique skills (' . count( $vocab ) . ')' );
ok( 60 === strlen( prospergenics_profiles_clean_skill( str_repeat( 'y', 99 ) ) ), 'skill name clipped to 60' );

echo "[Test 7] completion (#6353)\n";
$c = prospergenics_profiles_completion( array() );
ok( 0 === $c['percent'] && 5 === count( $c['missing'] ), 'empty profile: 0%, five prompts' );
$c = prospergenics_profiles_completion( array( 'photo' => true, 'headline' => true, 'skills' => 1 ) );
ok( 60 === $c['percent'] && array( 'experience', 'education' ) === array_keys( $c['missing'] ), '3 of 5: 60%, prompts for the rest' );
$c = prospergenics_profiles_completion( array( 'photo' => 1, 'headline' => 1, 'experience' => 1, 'skills' => 1, 'education' => 1 ) );
ok( 100 === $c['percent'] && ! $c['missing'], 'complete: 100%' );

echo "[Test 8] featured items (#6352)\n";
$f = prospergenics_profiles_normalize_featured( array(
	array( 'type' => 'link', 'url' => 'https://example.org/a', 'title' => 'A' ),
	array( 'type' => 'link', 'url' => 'javascript:alert(1)', 'title' => 'xss' ),
	array( 'type' => 'evil', 'url' => 'https://example.org/b' ),
	array( 'type' => 'article', 'url' => 'https://example.org/c', 'image' => 'data:image/png;base64,xx' ),
	array( 'type' => 'media', 'url' => 'https://example.org/d.jpg', 'attachment_id' => '12' ),
	array( 'type' => 'link', 'url' => 'https://example.org/e' ),
) );
ok( 3 === count( $f ), 'at most 3 items' );
ok( 'https://example.org/a' === $f[0]['url'] && 'article' === $f[1]['type'] && '' === $f[1]['image'] && 12 === $f[2]['attachment_id'], 'javascript: URLs, unknown types and data: images dropped' );
$p = prospergenics_profiles_parse_preview( '<html><head><title>Fallback</title><meta property="og:title" content="Hello &amp; welcome"><meta content=\'/img/x.png\' property=\'og:image\'></head>', 'https://site.example/blog/post' );
ok( 'Hello & welcome' === $p['title'] && 'https://site.example/img/x.png' === $p['image'], 'og:title and relative og:image parsed' );
$p = prospergenics_profiles_parse_preview( '<title>Only <b>title</b></title><meta property="og:image" content="javascript:alert(1)">', 'https://s.example/' );
ok( 'Only title' === $p['title'] && '' === $p['image'], '<title> fallback, unsafe image dropped' );

echo "[Test 9] magic link tokens (#6339)\n";
$h = function ( $t ) { return hash( 'sha256', 'salt' . $t ); };
ok( prospergenics_profiles_token_valid( 'abc', $h( 'abc' ), 2000, 1000, $h ), 'valid token' );
ok( ! prospergenics_profiles_token_valid( 'abd', $h( 'abc' ), 2000, 1000, $h ), 'wrong token' );
ok( ! prospergenics_profiles_token_valid( 'abc', $h( 'abc' ), 999, 1000, $h ), 'expired token' );
ok( ! prospergenics_profiles_token_valid( '', '', 2000, 1000, $h ), 'empty token/hash (already used)' );

echo "[Test 10] onboarding state (#6340)\n";
$s = prospergenics_profiles_normalize_onboarding( array( 'status' => 'pending', 'step' => 9, 'steps' => array( 1 => 'saved', 7 => 'saved', 2 => 'eh' ), 'shown' => 1 ) );
ok( 3 === $s['step'] && array( 1 => 'saved' ) === $s['steps'] && $s['shown'], 'normalised' );
$s = prospergenics_profiles_onboarding_advance( array( 'status' => 'pending', 'step' => 1 ), 1, 'saved' );
ok( 2 === $s['step'] && 'pending' === $s['status'], 'step 1 saved -> step 2' );
$s = prospergenics_profiles_onboarding_advance( $s, 2, 'skipped' );
$s = prospergenics_profiles_onboarding_advance( $s, 3, 'skipped' );
ok( 'done' === $s['status'] && 'skipped' === $s['steps'][2], 'skips recorded; done after step 3' );

echo "[Test 11] wiring in the source\n";
$rest = file_get_contents( $theme . '/inc/profiles/rest.php' );
preg_match_all( '/register_rest_route\(/', $rest, $routes );
preg_match_all( "/'permission_callback'\s*=>\s*'(prospergenics_profiles_[a-z_]+)'/", $rest, $perms );
ok( count( $routes[0] ) >= 15, 'routes registered: ' . count( $routes[0] ) );
ok( false === strpos( $rest, '__return_true' ), 'no route uses __return_true' );
$write_methods = preg_match_all( "/'methods'\s*=>\s*'(POST|PUT|PATCH|DELETE|PUT,PATCH,POST|PUT,PATCH|PUT,POST)'\s*,\s*'permission_callback'\s*=>\s*'([a-z_]+)'/", $rest, $writes, PREG_SET_ORDER );
$bad = array_filter( $writes, function ( $w ) { return 'prospergenics_profiles_can_read' === $w[2]; } );
ok( $write_methods >= 10 && ! $bad, 'every write route needs a logged-in owner (' . $write_methods . ' write handlers)' );
ok( false !== strpos( $rest, "PROSPERGENICS_PROFILES_REST_NS = 'prospergenics/v1'" ) && false !== strpos( $rest, "'/profile/" ), 'namespace prospergenics/v1, routes under /profile/' );
$data = file_get_contents( $theme . '/inc/profiles/data.php' );
ok( false !== strpos( $data, "do_action( 'prospergenics_platform_content_saved', (int) \$result, \$type )" ), 'content-saved hook fired on save' );
ok( preg_match( "/'show_in_rest'\s*=>\s*false/", $data ) && preg_match( "/'public'\s*=>\s*false/", $data ), 'CPTs are not public and not in /wp/v2' );
foreach ( array( 'pg_experience', 'pg_education', 'pg_skill' ) as $cpt ) {
	ok( false !== strpos( $data, "register_post_type( '$cpt'" ), "CPT $cpt registered" );
}
$all = $rest . $data . file_get_contents( $theme . '/inc/profiles/routes.php' );
ok( false === strpos( $all, 'wp/v2/users' ) && false === strpos( $all, 'is_author' ), 'users endpoint and author archives untouched' );
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "/inc/platform-profiles.php'" ), 'loader required from functions.php' );
foreach ( array( 'pg-profile.php', 'pg-profile-settings.php', 'pg-profile-welcome.php', 'pg-profile-following.php', 'pg-profile-notice.php', 'assets/platform/profiles.css', 'assets/platform/profiles.js' ) as $f ) {
	ok( file_exists( $theme . '/' . $f ), "$f built into the theme" );
}
$tpl = file_get_contents( $theme . '/pg-profile.php' );
ok( ! preg_match( '/echo\s+\$data\[[^\]]+\]\s*;/', $tpl ), 'profile template never echoes data unescaped' );
ok( false !== strpos( file_get_contents( $theme . '/pg-profile-settings.php' ), "wp_nonce_field( 'pg_profiles_privacy'" ), 'settings form carries a nonce' );

echo $failures ? "\n$failures FAILED\n" : "\nAll profile tests passed.\n";
exit( $failures ? 1 : 0 );

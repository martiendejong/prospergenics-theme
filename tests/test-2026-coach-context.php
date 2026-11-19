<?php
/**
 * The homepage chat (CoachOS) gets the team facts and who is logged in as page context
 * (inc/coach-context.php): on 8 Nov it called Lesiamon "she" and couldn't answer "who am I?".
 *
 * Run with: php tests/test-2026-coach-context.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['users'] = array();
$GLOBALS['meta']  = array();
$GLOBALS['me']    = 0;
function apply_filters( $hook, $value ) { return $value; }
function home_url( $p = '/' ) { return 'https://test.example' . $p; }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function get_current_user_id() { return $GLOBALS['me']; }
function get_userdata( $id ) { return isset( $GLOBALS['users'][ $id ] ) ? $GLOBALS['users'][ $id ] : false; }
function get_user_meta( $id, $k, $single = false ) { return isset( $GLOBALS['meta'][ $id ][ $k ] ) ? $GLOBALS['meta'][ $id ][ $k ] : ''; }
function prospergenics_roles_get( $id ) { return 'instructor'; }
function wp_json_encode( $v ) { return json_encode( $v ); }
function prospergenics_profiles_url( $id ) { return 'https://test.example/in/lesiamon-mpoe/'; }

require $theme . '/inc/coach-context.php';

$people = prospergenics_2026_coach_people();
ok( isset( $people['lessy'] ) && 'he/him' === $people['lessy']['pronouns'] && 'Lessy' === $people['lessy']['aka'], 'people.json says Lesiamon goes by Lessy and uses he/him' );
ok( isset( $people['simitia'] ) && 'Sandra' === $people['simitia']['aka'], 'Simitia is also called Sandra' );

$visitor = prospergenics_2026_coach_context( 0 );
ok( false !== strpos( $visitor, '- Lesiamon Mpoe (also called Lessy), pronouns he/him: Founding team, Trainer, Software Developer. Profile: https://test.example/people/lessy/' ), 'the chat is told who Lessy is, with he/him' );
ok( (bool) preg_match( '/Founder: Martien de Jong\. Founding team: [^.]*Lesiamon Mpoe[^.]*\. Nobody is called a co-founder\./', $visitor ), 'founder and founding team come from the people data, in Martien\'s wording' );
ok( false !== strpos( $visitor, 'When none are listed, use their name or they/them; never guess.' ), 'no guessing pronouns that are not listed' );
ok( false !== strpos( $visitor, 'is a visitor who is not logged in' ), 'a visitor is not given anyone\'s identity' );
ok( mb_strlen( $visitor ) <= PROSPERGENICS_2026_COACH_CONTEXT_MAX, 'the context fits in what embed.js sends (8000 characters)' );

$GLOBALS['users'][7] = (object) array( 'ID' => 7, 'display_name' => 'Lesiamon Mpoe', 'roles' => array( 'pg_writer', 'pg_instructor' ), 'user_email' => 'secret@example.test' );
$GLOBALS['meta'][7]  = array( '_pg_people_slug' => 'lessy' );
$member = prospergenics_2026_coach_context( 7 );
ok( false !== strpos( $member, 'The person chatting is logged in as Lesiamon Mpoe (member and course instructor, writes articles); their member profile is https://test.example/in/lesiamon-mpoe/.' ), 'a member is told who they are: name, role, profile' );
ok( false !== strpos( $member, 'They are this person from the ProsperGenics team list: Lesiamon Mpoe (also called Lessy), pronouns he/him' ), 'a member linked to a /people/ page gets their team entry' );
ok( false === strpos( $member, 'secret@example.test' ), 'the email address is never sent' );

$GLOBALS['me'] = 7;
$tag = prospergenics_2026_coach_embed();
ok( 0 === strpos( $tag, '<script src="https://coaching.martiendejong.nl/coaching/embed.js?v=2" data-tenant="prospergenics"' ) && false !== strpos( $tag, 'data-trigger=".launcher-orb"' ) && false !== strpos( $tag, ' async></script>' ), 'the script tag keeps every CoachOS setting the homepage had' );
ok( false !== strpos( $tag, 'postMessage({type:"co-pagina-context",content:c},O)' ) && false !== strpos( $tag, '[400,1200,3000,6000]' ), 'the context is posted again after the chat iframe loads (embed.js sends it before the chat listens)' );
ok( (bool) preg_match( '/data-page-content="[^"]*Lesiamon Mpoe \(also called Lessy\), pronouns he\/him/', $tag ), 'the context goes in data-page-content' );
$GLOBALS['users'][8] = (object) array( 'ID' => 8, 'display_name' => '"><script>alert(1)</script>', 'roles' => array(), 'user_email' => 'x@example.test' );
$GLOBALS['me'] = 8;
ok( false === strpos( prospergenics_2026_coach_embed(), '<script>alert(1)' ), 'a display name cannot break out of the attribute' );

$front = file_get_contents( $theme . '/front-page.php' );
ok( 1 === substr_count( $front, 'prospergenics_2026_coach_embed()' ) && false === strpos( $front, 'data-tenant="prospergenics"' ), 'the homepage prints the chat through the context builder, not the bare tag' );
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "require get_template_directory() . '/inc/coach-context.php';" ), 'functions.php loads it' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

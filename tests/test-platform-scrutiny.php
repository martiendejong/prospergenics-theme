<?php
/**
 * Standalone tests for public scrutiny and corrections (card #6389, inc/profiles/scrutiny*.php):
 * targets and keys, text / sources / disclosure / attribution validation, the bot check, who may
 * submit and who may respond (the person concerned only), the review state machine, the public
 * whitelist (no email, IP hash or audit data can reach a page), the audit entries, the Django Work
 * seam, nonces and pg_moderate on every handler, the Reports tab, privacy, and where the two
 * contract hooks are placed on /in/<slug>/.
 *
 * Run with: php tests/test-platform-scrutiny.php
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
define( 'ABSPATH', sys_get_temp_dir() . '/pg-scrutiny-test/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['hooks'] = array();
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function apply_filters( $hook, $value ) { return $value; }
function wp_hash( $s ) { return hash_hmac( 'md5', (string) $s, 'test-salt' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
function sanitize_text_field( $s ) { return trim( preg_replace( '/[\r\n\t ]+/', ' ', strip_tags( (string) $s ) ) ); }
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
// A small wp_kses(): allowed tags only, without attributes (none are allowed here).
function wp_kses( $html, $allowed ) {
	$html = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', (string) $html );
	$html = strip_tags( $html, '<' . implode( '><', array_keys( $allowed ) ) . '>' );
	return preg_replace( '#<(/?)([a-z]+)[^>]*>#i', '<$1$2>', $html );
}

foreach ( array( 'inc/spam-protection.php', 'inc/profiles/scrutiny.php', 'inc/profiles/scrutiny-render.php', 'inc/profiles/scrutiny-queue.php' ) as $f ) {
	ok( file_exists( $theme . '/' . $f ), "$f is in the generated theme" );
	require $theme . '/' . $f;
}
$loader = file_get_contents( $theme . '/inc/platform-profiles.php' );
foreach ( array( 'scrutiny.php', 'scrutiny-render.php', 'scrutiny-queue.php' ) as $f ) {
	ok( false !== strpos( $loader, "'$f'" ) && strpos( $loader, "'moderation.php'" ) < strpos( $loader, "'$f'" ), "the profiles loader requires $f after moderation.php" );
}
ok( file_exists( $theme . '/assets/platform/scrutiny.css' ), 'assets/platform/scrutiny.css shipped' );

echo "\nKinds, targets, keys\n";
ok( array( 'evidence', 'correction', 'question', 'review', 'dispute' ) === array_keys( prospergenics_scrutiny_kinds() ), 'five kinds: evidence, correction, question, review, dispute' );
ok( false !== stripos( prospergenics_scrutiny_kinds()['review'][0], 'opinion' ) && false !== stripos( prospergenics_scrutiny_kinds()['review'][2], 'opinion' ), 'a review is labelled as an opinion' );
ok( array( 'person', 'project', 'claim' ) === array_keys( prospergenics_scrutiny_target_types() ), 'targets from the contract: person, project, claim' );
ok( array( 'none', 'worked', 'self', 'other' ) === array_keys( prospergenics_scrutiny_disclosures() ), 'disclosures: no connection, worked with them, am them, other' );
ok( 'claim:12' === prospergenics_scrutiny_key( 'claim', 12 ) && '' === prospergenics_scrutiny_key( 'article', 12 ) && '' === prospergenics_scrutiny_key( 'person', 0 ), 'keys' );
ok( array( 'project', 7 ) === prospergenics_scrutiny_parse_key( 'project:7' ), 'parse a key' );
foreach ( array( 'person:0', 'person:-3', 'user:3', 'claim:1:2', '', 'claim:12x' ) as $bad ) {
	ok( null === prospergenics_scrutiny_parse_key( $bad ), "refuse key '$bad'" );
}

echo "\nText, sources, disclosure, attribution\n";
$t = prospergenics_scrutiny_clean_text( 'He started in <strong>2021</strong>.<script>alert(1)</script> <a href="https://x.example">link</a> <p onclick="x()">more text here</p>' );
ok( '' === $t['error'] && false === strpos( $t['body'], '<script' ) && false === strpos( $t['body'], '<a' ) && false === strpos( $t['body'], 'onclick' ) && false !== strpos( $t['body'], '<strong>' ), 'wp_kses: scripts, links and attributes go, simple emphasis stays' );
ok( 'short' === prospergenics_scrutiny_clean_text( 'Too short.' )['error'], 'at least 20 characters' );
ok( 'short' === prospergenics_scrutiny_clean_text( str_repeat( '<em></em>', 10 ) )['error'], 'markup does not count' );
ok( 'long' === prospergenics_scrutiny_clean_text( str_repeat( 'a', 4001 ) )['error'] && '' === prospergenics_scrutiny_clean_text( str_repeat( 'a', 4000 ) )['error'], 'at most 4,000 characters' );
$s = prospergenics_scrutiny_clean_sources( "https://example.org/a\n\nhttps://example.org/a\r\nhttps://news.example/b?x=1" );
ok( '' === $s['error'] && array( 'https://example.org/a', 'https://news.example/b?x=1' ) === $s['urls'], 'sources: one per line, duplicates dropped' );
foreach ( array( 'http://example.org/', 'javascript:alert(1)', 'https://localhost/', 'ftp://x.example/', 'https://exa mple.org/', 'https://x.example/"><script>' ) as $bad ) {
	ok( 'https' === prospergenics_scrutiny_clean_sources( $bad )['error'], "source refused: $bad" );
}
ok( 'https' === prospergenics_scrutiny_clean_sources( 'https://x.example/' . str_repeat( 'a', 500 ) )['error'], 'a source longer than 500 characters is refused' );
ok( 'many' === prospergenics_scrutiny_clean_sources( implode( "\n", array_map( function ( $i ) { return "https://s$i.example/"; }, range( 1, 6 ) ) ) )['error'], 'at most 5 sources' );
$good = array( 'kind' => 'correction', 'body' => 'He was an apprentice until 2021, not lead installer.', 'sources' => '', 'disclosure' => 'worked', 'name' => 'Pat Visitor', 'email' => 'Pat@Example.net' );
$v = prospergenics_scrutiny_validate( $good, false );
ok( ! $v['errors'] && 'pat@example.net' === $v['clean']['email'] && 'Pat Visitor' === $v['clean']['name'], 'a visitor\'s valid correction; the email is lower-cased' );
ok( isset( prospergenics_scrutiny_validate( array( 'disclosure' => '' ) + $good, false )['errors']['disclosure'] ), 'the conflict disclosure is required' );
ok( 'describe' === prospergenics_scrutiny_validate( array( 'disclosure' => 'other', 'disclosure_text' => '' ) + $good, false )['errors']['disclosure'], '"Other" needs a description' );
$o = prospergenics_scrutiny_validate( array( 'disclosure' => 'other', 'disclosure_text' => 'A competitor' ) + $good, false );
ok( ! $o['errors'] && 'A competitor' === $o['clean']['disclosure_text'], '"Other" with a description' );
ok( '' === prospergenics_scrutiny_validate( array( 'disclosure_text' => 'ignored' ) + $good, false )['clean']['disclosure_text'], 'the description is only kept for "Other"' );
ok( isset( prospergenics_scrutiny_validate( array( 'kind' => 'rumour' ) + $good, false )['errors']['kind'] ), 'unknown kinds are refused' );
ok( isset( prospergenics_scrutiny_validate( array( 'email' => 'not-an-email' ) + $good, false )['errors']['email'] ), 'a visitor needs a valid email address' );
ok( isset( prospergenics_scrutiny_validate( array( 'name' => 'P' ) + $good, false )['errors']['name'] ), 'a visitor needs a name' );
ok( 'invalid' === prospergenics_scrutiny_validate( array( 'name' => 'Buy now https://spam.example' ) + $good, false )['errors']['name'], 'no links in the name' );
$m = prospergenics_scrutiny_validate( array( 'name' => '', 'email' => '' ) + $good, true );
ok( ! $m['errors'] && ! isset( $m['clean']['email'] ), 'members are attributed with their account: no name or email fields' );

echo "\nSpam: honeypot and signed form time\n";
$now  = 1800000000;
$form = function ( $t ) { return array( 'pg_form_time' => $t, 'pg_form_token' => wp_hash( (string) $t ) ); };
ok( '' === prospergenics_scrutiny_bot_reason( $form( $now - 30 ), $now ), 'a form filled in 30 seconds passes' );
ok( 'fast' === prospergenics_scrutiny_bot_reason( $form( $now - 2 ), $now ), 'posted within 4 seconds: refused' );
ok( 'stale' === prospergenics_scrutiny_bot_reason( $form( $now - 2 * DAY_IN_SECONDS ), $now ), 'older than a day: refused' );
ok( 'token' === prospergenics_scrutiny_bot_reason( array( 'pg_form_time' => $now - 30, 'pg_form_token' => 'x' ), $now ), 'a forged time: refused' );
ok( 'honeypot' === prospergenics_scrutiny_bot_reason( $form( $now - 30 ) + array( PROSPERGENICS_HONEYPOT_FIELD => 'x' ), $now ), 'a filled honeypot: refused' );

echo "\nWho may submit\n";
$visitor = array( 'open' => true, 'visible' => true, 'logged_in' => false, 'verified' => false, 'suspended' => false, 'own' => false, 'rate_ok' => true );
ok( '' === prospergenics_scrutiny_submit_block( $visitor ), 'a visitor may (non-members are accepted, with email confirmation)' );
$member = array( 'logged_in' => true, 'verified' => true ) + $visitor;
ok( '' === prospergenics_scrutiny_submit_block( $member ), 'a verified member may' );
ok( 'unverified' === prospergenics_scrutiny_submit_block( array( 'verified' => false ) + $member ), 'an unverified member is asked to confirm first' );
ok( 'suspended' === prospergenics_scrutiny_submit_block( array( 'suspended' => true ) + $member ), 'a suspended member may not' );
ok( 'own' === prospergenics_scrutiny_submit_block( array( 'own' => true ) + $member ), 'nobody scrutinises their own profile or entry' );
ok( 'not_found' === prospergenics_scrutiny_submit_block( array( 'open' => false ) + $visitor ), 'a hidden, suspended or unpublished target: nothing' );
ok( 'not_found' === prospergenics_scrutiny_submit_block( array( 'visible' => false ) + $member ), 'a target the viewer cannot see: nothing' );
ok( 'rate' === prospergenics_scrutiny_submit_block( array( 'rate_ok' => false ) + $visitor ), 'rate limited' );

echo "\nReview states\n";
ok( 'approved' === prospergenics_scrutiny_next_status( 'pending', 'approve' ) && 'approved' === prospergenics_scrutiny_next_status( 'more', 'approve' ), 'approve from review' );
ok( 'rejected' === prospergenics_scrutiny_next_status( 'pending', 'reject' ) && 'more' === prospergenics_scrutiny_next_status( 'pending', 'more' ), 'reject, ask for more evidence' );
ok( 'withdrawn' === prospergenics_scrutiny_next_status( 'approved', 'withdraw' ), 'unpublish a published item' );
foreach ( array( array( 'unconfirmed', 'approve' ), array( 'rejected', 'approve' ), array( 'approved', 'approve' ), array( 'more', 'more' ), array( 'pending', 'withdraw' ), array( 'pending', 'delete' ) ) as $x ) {
	ok( '' === prospergenics_scrutiny_next_status( $x[0], $x[1] ), "not allowed: {$x[1]} from {$x[0]} (unconfirmed items cannot be approved)" );
}
ok( prospergenics_scrutiny_decision_needs_note( 'reject' ) && prospergenics_scrutiny_decision_needs_note( 'more' ) && ! prospergenics_scrutiny_decision_needs_note( 'approve' ), 'reject and "more evidence" need a reason' );

echo "\nResponse rights\n";
$f = array( 'logged_in' => true, 'concerned' => true, 'verified' => true, 'suspended' => false, 'approved' => true, 'responded' => false );
ok( '' === prospergenics_scrutiny_response_block( $f ), 'the person concerned may respond to a published item' );
ok( 'denied' === prospergenics_scrutiny_response_block( array( 'concerned' => false ) + $f ), 'nobody else may' );
ok( 'login' === prospergenics_scrutiny_response_block( array( 'logged_in' => false ) + $f ), 'not without logging in' );
ok( 'already' === prospergenics_scrutiny_response_block( array( 'responded' => true ) + $f ), 'one response per item' );
ok( 'not_found' === prospergenics_scrutiny_response_block( array( 'approved' => false ) + $f ), 'not on an unpublished item' );
ok( 'unverified' === prospergenics_scrutiny_response_block( array( 'suspended' => true ) + $f ), 'not while suspended' );

echo "\nAudit / public separation\n";
$record = array(
	'id' => 9, 'kind' => 'review', 'body' => '<p>Good work.</p>', 'sources' => array( 'https://a.example/' ), 'disclosure' => 'other', 'disclosure_text' => 'Customer',
	'name' => 'Pat', 'member_id' => 0, 'status' => 'approved', 'published_at' => 1800000000, 'submitted' => 1799990000,
	'email' => 'pat@example.net', 'audit_email' => 'pat@example.net', 'ip' => 'abc', 'audit_ip' => 'abc', 'audit' => array( array( 'event' => 'submitted' ) ), 'note' => 'internal',
	'response' => array( 'user_id' => 4, 'name' => 'Sam', 'body' => 'Thanks.', 'at' => 1800000100 ),
);
$pub = prospergenics_scrutiny_public_item( $record );
ok( array( 'id', 'kind', 'kind_label', 'opinion', 'body', 'sources', 'name', 'member', 'member_url', 'disclosure', 'date', 'response' ) === array_keys( $pub ), 'the public view is a whitelist of fields' );
$flat = json_encode( $pub );
ok( false === strpos( $flat, 'pat@example.net' ) && false === strpos( $flat, 'abc"' ) && false === strpos( $flat, 'internal' ) && false === strpos( $flat, 'submitted' ) && false === strpos( $flat, 'user_id' ), 'no email, IP hash, staff note, audit log or user id comes out' );
ok( $pub['opinion'] && 'Review (opinion)' === $pub['kind_label'], 'a review comes out as an opinion' );
ok( 'Other connection: Customer' === $pub['disclosure'] && '2027-01-15' === $pub['date'] && 'Sam' === $pub['response']['name'], 'disclosure, date and response are public' );
ok( 'A former contributor' === prospergenics_scrutiny_public_item( array( 'name' => '' ) + $record )['name'], 'an anonymised item: "A former contributor"' );
$e = prospergenics_scrutiny_audit_entry( 'approve', 3, 'django-work', array( 'note' => 'ok' ), 1800000000 );
ok( array( 'at' => 1800000000, 'event' => 'approve', 'by' => 3, 'source' => 'django-work', 'data' => array( 'note' => 'ok' ) ) === $e, 'an audit entry records when, what, who and from where' );

echo "\nCode: storage, seam, handlers, queue, privacy\n";
$sc = file_get_contents( $theme . '/inc/profiles/scrutiny.php' );
$re = file_get_contents( $theme . '/inc/profiles/scrutiny-render.php' );
$qu = file_get_contents( $theme . '/inc/profiles/scrutiny-queue.php' );
ok( false !== strpos( $sc, "'public'              => false" ) && false !== strpos( $sc, "'show_in_rest'        => false" ) && false !== strpos( $sc, "'publicly_queryable'  => false" ) && false !== strpos( $sc, "'exclude_from_search' => true" ), 'pg_scrutiny is not public, not in REST, not queryable, not searchable' );
ok( false !== strpos( $sc, "do_action( 'prospergenics_scrutiny_review_requested'" ) && false !== strpos( $sc, 'Seam for Django Work human review' ), 'the Django Work seam is one documented action' );
ok( 1 === substr_count( $sc, "do_action( 'prospergenics_scrutiny_review_requested'" ), '…fired from one place (entering review)' );
ok( false !== strpos( $sc, "hash_hmac( 'sha256', \$ip, wp_salt( 'auth' ) )" ), 'the IP address is stored only as a keyed hash' );
ok( false !== strpos( $sc, "'audit_email'" ) && false === strpos( $re, '_pg_scrutiny_audit' ) && false === strpos( $re, 'audit_data' ), 'the public markup never reads the audit part' );
ok( false !== strpos( $re, "check_admin_referer( 'pg_scrutiny_submit_' . \$key )" ) && false !== strpos( $re, "check_admin_referer( 'pg_scrutiny_respond_' . \$item )" ), 'member submissions and responses check a nonce' );
ok( false !== strpos( $re, 'prospergenics_spam_protection_fields()' ) && 2 <= substr_count( $re, 'prospergenics_scrutiny_bot_reason( $post, time() )' ), 'the forms carry the honeypot and signed time, and the handlers check them' );
ok( false !== strpos( $re, "'admin_post_nopriv_pg_scrutiny_submit'" ) && false !== strpos( $re, "'admin_post_nopriv_pg_scrutiny_confirm'" ), 'visitors can submit and confirm without an account' );
ok( false !== strpos( $re, "add_action( 'admin_post_nopriv_pg_scrutiny_respond'" ) && false !== strpos( $re, 'wp_login_url(' ), 'a visitor posting a response is sent to log in' );
ok( false !== strpos( $re, "rel=\"nofollow ugc noopener noreferrer\"" ), 'source links are nofollow ugc' );
ok( false !== strpos( $qu, "check_admin_referer( 'pg_scrutiny_decide_' . \$item )" ) && 2 <= substr_count( $qu, 'current_user_can( PROSPERGENICS_MODERATION_CAP )' ), 'decisions and the tab check a nonce and pg_moderate' );
ok( false !== strpos( $qu, "'prospergenics_profiles_moderation_tabs'" ) && false !== strpos( $qu, "'prospergenics_profiles_moderation_tab_scrutiny'" ) && false !== strpos( $qu, "'prospergenics_profiles_moderation_count'" ), 'a tab and a badge count in the existing Reports screen, not a second queue' );
$mod = file_get_contents( $theme . '/inc/profiles/moderation.php' );
ok( false !== strpos( $mod, "apply_filters( 'prospergenics_profiles_moderation_tabs'" ) && false !== strpos( $mod, "do_action( 'prospergenics_profiles_moderation_tab_' . \$tab )" ) && false !== strpos( $mod, "isset( \$tabs[ \$tab ] ) ? \$tab : 'open'" ), 'moderation.php: filterable tabs, unknown tabs fall back to Open' );
$tabs = $GLOBALS['hooks']['prospergenics_profiles_moderation_tabs'] ?? array();
ok( count( $tabs ) === 1, 'the Scrutiny tab is registered' );
ok( false !== strpos( $sc, "\$exporters['prospergenics-scrutiny']" ) && false !== strpos( $sc, "\$erasers['prospergenics-scrutiny']" ), 'an exporter and an eraser are registered' );
ok( false !== strpos( $sc, "'approved' === \$status" ) && false !== strpos( $sc, "'_pg_scrutiny_name', ''" ) && false !== strpos( $sc, "'post_author' => 0" ), 'erasing keeps published text, anonymised' );
ok( false !== strpos( $sc, "add_action( 'delete_user'" ) && false !== strpos( $sc, "prospergenics_scrutiny_delete_about( 'person:'" ) && false !== strpos( $sc, "add_action( 'before_delete_post'" ), 'a deleted account, entry or project takes the items about it along' );
ok( false !== strpos( $sc, "'prospergenics_scrutiny_cleanup'" ), 'unconfirmed items are cleaned up daily' );
ok( false !== strpos( $sc, 'prospergenics_scrutiny_member_closed( $id )' ) && false !== strpos( $sc, 'prospergenics_profiles_is_suspended( $user_id )' ), 'hidden or suspended members: their profile and entries are closed' );
ok( false !== strpos( $sc, "function_exists( 'prospergenics_projects_contributors' )" ) && false !== strpos( $sc, "post_type_exists( 'pg_project' )" ), 'projects are used behind guards (contract #6388)' );

echo "\nThe hooks on /in/<slug>/\n";
$tpl = file_get_contents( $theme . '/pg-profile.php' );
ok( 1 === substr_count( $tpl, "do_action( 'prospergenics_scrutiny_public', 'person', \$owner->ID )" ) && 1 === substr_count( $tpl, "do_action( 'prospergenics_scrutiny_actions', 'person', \$owner->ID )" ), 'the person: both hooks once' );
ok( 2 === substr_count( $tpl, "do_action( 'prospergenics_scrutiny_public', 'claim', \$e['id'] )" ) && 2 === substr_count( $tpl, "do_action( 'prospergenics_scrutiny_actions', 'claim', \$e['id'] )" ), 'each experience and education entry (claim): both hooks' );
foreach ( array( 'prospergenics_scrutiny_public', 'prospergenics_scrutiny_actions' ) as $h ) {
	ok( ! empty( $GLOBALS['hooks'][ $h ] ), "the module renders on $h" );
}

echo "\n" . ( $failures ? "$failures FAILED\n" : "All scrutiny tests passed\n" );
exit( $failures ? 1 : 0 );

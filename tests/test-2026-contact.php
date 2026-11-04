<?php
/**
 * Standalone test for the contact-form blackhole + spam fix.
 *
 * Two things must hold, and both are regressions that already cost real enquiries:
 *   1. A submission is STORED before any email is attempted, so a mail failure can
 *      never lose it again.
 *   2. The forms actually resist bots (honeypot, timing token, link stuffing, rate
 *      limit) instead of relying on a nonce, which a bot reads from the page.
 *
 * Spam must be flagged, never discarded -- a false positive has to stay recoverable.
 *
 * Ported from PR #20 (production theme) to the 2026 theme, which now carries the same
 * modules in wp-theme/prospergenics-2026/inc/, plus checks on the new contact page.
 *
 * Run with: php tests/test-2026-contact.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/wp-theme/prospergenics-2026/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );

$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ───────────────────── WP stubs ─────────────────────
$GLOBALS['__transients'] = array();
function wp_hash( $data ) { return md5( 'test-salt|' . $data ); }
function get_transient( $k ) { return isset( $GLOBALS['__transients'][ $k ] ) ? $GLOBALS['__transients'][ $k ] : false; }
function set_transient( $k, $v, $t = 0 ) { $GLOBALS['__transients'][ $k ] = $v; return true; }
function apply_filters( $tag, $value ) { return $value; }
function esc_attr( $t ) { return htmlspecialchars( $t, ENT_QUOTES ); }
function esc_html_e( $t, $d = null ) { echo htmlspecialchars( $t, ENT_QUOTES ); }
function wp_unslash( $v ) { return $v; }
function get_option( $k ) { return 'https://prospergenics.com'; }

require $theme . '/inc/spam-protection.php';

/** A submission that a real person would produce. */
function human_post( $overrides = array() ) {
	$t = time() - 30;
	return array_merge(
		array(
			'pg_form_time'  => $t,
			'pg_form_token' => wp_hash( (string) $t ),
			PROSPERGENICS_HONEYPOT_FIELD => '',
		),
		$overrides
	);
}

echo "[Test 1] a genuine submission is not flagged\n";
$_SERVER['REMOTE_ADDR'] = '203.0.113.10';
$reasons = prospergenics_spam_reasons( 'Hello, I would like to know more about your trainings in Nairobi.', human_post() );
ok( empty( $reasons ), 'plain enquiry passes clean' . ( $reasons ? ' (got: ' . implode( '; ', $reasons ) . ')' : '' ) );

echo "\n[Test 2] each bot signal is caught\n";
$_SERVER['REMOTE_ADDR'] = '203.0.113.11';
$r = prospergenics_spam_reasons( 'hi', human_post( array( PROSPERGENICS_HONEYPOT_FIELD => 'http://spam.example' ) ) );
ok( in_array( 'honeypot filled', $r, true ), 'honeypot field filled is caught' );

$_SERVER['REMOTE_ADDR'] = '203.0.113.12';
$r = prospergenics_spam_reasons( 'hi', array() );
ok( in_array( 'missing or invalid form token', $r, true ), 'a post with no timing token at all is caught (direct POST to admin-post.php)' );

$_SERVER['REMOTE_ADDR'] = '203.0.113.13';
$r = prospergenics_spam_reasons( 'hi', array( 'pg_form_time' => time(), 'pg_form_token' => 'forged' ) );
ok( in_array( 'missing or invalid form token', $r, true ), 'a forged token is caught (hash is keyed by site salts)' );

$_SERVER['REMOTE_ADDR'] = '203.0.113.14';
$now = time();
$r = prospergenics_spam_reasons( 'hi', array( 'pg_form_time' => $now, 'pg_form_token' => wp_hash( (string) $now ) ) );
ok( (bool) preg_grep( '/submitted in/', $r ), 'an instant submit is caught' );

$_SERVER['REMOTE_ADDR'] = '203.0.113.15';
$old = time() - ( 2 * DAY_IN_SECONDS );
$r = prospergenics_spam_reasons( 'hi', array( 'pg_form_time' => $old, 'pg_form_token' => wp_hash( (string) $old ) ) );
ok( in_array( 'stale form', $r, true ), 'a stale/replayed form is caught' );

$_SERVER['REMOTE_ADDR'] = '203.0.113.16';
$links = 'buy http://a.example http://b.example http://c.example http://d.example http://e.example';
$r = prospergenics_spam_reasons( $links, human_post() );
ok( (bool) preg_grep( '/links in message/', $r ), 'link stuffing is caught' );

$_SERVER['REMOTE_ADDR'] = '203.0.113.17';
$r = prospergenics_spam_reasons( 'nice post [url=http://x.example]click[/url]', human_post() );
ok( in_array( 'markup links in message', $r, true ), 'BBCode/anchor markup is caught' );

echo "\n[Test 3] rate limiting is per IP and lets normal use through\n";
$_SERVER['REMOTE_ADDR'] = '203.0.113.20';
$hits = array();
for ( $i = 0; $i < 7; $i++ ) {
	$hits[] = prospergenics_spam_reasons( 'Genuine question number ' . $i, human_post() );
}
ok( empty( $hits[0] ) && empty( $hits[1] ), 'the first couple of submissions pass' );
ok( (bool) preg_grep( '/rate limit/', $hits[6] ), 'the seventh submission from one IP is rate limited' );
$_SERVER['REMOTE_ADDR'] = '203.0.113.21';
$other = prospergenics_spam_reasons( 'Unrelated person, same hour', human_post() );
ok( empty( $other ), 'a different IP is unaffected by that limit' );

echo "\n[Test 4] the honeypot markup is actually hidden and inert\n";
$fields = prospergenics_spam_protection_fields();
ok( false !== strpos( $fields, PROSPERGENICS_HONEYPOT_FIELD ), 'honeypot field is rendered' );
ok( false !== strpos( $fields, 'aria-hidden="true"' ), 'honeypot is hidden from assistive tech' );
ok( false !== strpos( $fields, 'tabindex="-1"' ), 'honeypot is skipped by keyboard tabbing' );
ok( false !== strpos( $fields, 'autocomplete="off"' ), 'honeypot is not autofilled by browsers' );
ok( false !== strpos( $fields, 'left:-9999px' ), 'honeypot is positioned off-screen' );
ok( false !== strpos( $fields, 'pg_form_time' ) && false !== strpos( $fields, 'pg_form_token' ), 'timing token pair is rendered' );

echo "\n[Test 5] the handler stores the message BEFORE it tries to mail\n";
// Source-level ordering check: this is the exact bug being fixed, so it gets a guard.
$handler = file_get_contents( $theme . '/inc/contact-form-handler.php' );
/**
 * Strip comments before the ordering check: the file's own doc comment explains the old
 * wp_mail()-only behaviour, and a naive strpos would match that explanation, not the code.
 */
function strip_php_comments( $src ) {
	$src = preg_replace( '#/\*.*?\*/#s', '', $src );
	return preg_replace( '#^\s*//.*$#m', '', $src );
}
$handler_code = strip_php_comments( $handler );
$store_at = strpos( $handler_code, 'prospergenics_store_contact_message(' );
$mail_at  = strpos( $handler_code, 'wp_mail(' );
ok( false !== $store_at, 'handler calls prospergenics_store_contact_message()' );
ok( false !== $mail_at, 'handler still sends a notification email' );
ok( false !== $store_at && false !== $mail_at && $store_at < $mail_at, 'the store call comes before the wp_mail call' );
ok( false !== strpos( $handler, 'wp_mail_failed' ), 'handler captures wp_mail failures instead of discarding them' );
ok( false !== strpos( $handler, 'prospergenics_record_contact_mail_status(' ), 'handler records the delivery outcome on the message' );

echo "\n[Test 6] spam is stored, never silently dropped\n";
$store = file_get_contents( $theme . '/inc/contact-messages-store.php' );
ok( false !== strpos( $handler, '$spam_reasons' ), 'handler classifies before storing' );
// The store call must receive the reasons, i.e. spam still goes through storage.
ok( 1 === preg_match( '/prospergenics_store_contact_message\(\s*\$data,\s*\$spam_reasons\s*\)/', $handler ), 'spam reasons are passed to the store, so flagged messages are still saved' );
ok( 0 === preg_match( '/if\s*\(\s*!\s*empty\(\s*\$spam_reasons\s*\)\s*\)\s*\{\s*(exit|return|wp_die)/', $handler ), 'handler does not bail out before storing when a message looks like spam' );
ok( false !== strpos( $store, "'_pg_spam_reasons'" ), 'the reason for flagging is persisted for review' );

echo "\n[Test 7] the contact page form renders the protection fields\n";
$page = file_get_contents( $theme . '/page-contact.php' );
$form_at = strpos( $page, '<form' );
$hp_at   = strpos( $page, 'prospergenics_spam_protection_fields()' );
ok( false !== $form_at && false !== $hp_at && $hp_at > $form_at && $hp_at < strpos( $page, '</form>' ), 'the protection fields are inside the contact form' );
ok( false !== strpos( $page, "wp_nonce_field('prospergenics_contact_form_nonce', 'contact_form_nonce')" ), 'the form carries the nonce the handler checks' );
ok( false !== strpos( $page, 'name="action" value="prospergenics_contact_form"' ) && false !== strpos( $page, "admin_url('admin-post.php')" ), 'the form posts to the stored handler' );
foreach ( array( 'contact_name', 'contact_email', 'contact_subject', 'contact_message' ) as $field ) {
	ok( false !== strpos( $page, 'name="' . $field . '"' ), "the form sends $field" );
}
ok( false !== strpos( $page, 'esc_html($error)' ), 'the error message from the query string is escaped' );
$functions = file_get_contents( $theme . '/functions.php' );
foreach ( array( 'spam-protection', 'contact-messages-store', 'contact-messages-admin', 'contact-form-handler', 'comment-spam-protection' ) as $mod ) {
	ok( false !== strpos( $functions, "/inc/$mod.php'" ), "functions.php loads inc/$mod.php" );
}
ok( false !== strpos( $functions, "'contact' !== trim(" ) && false !== strpos( $functions, "page-contact.php'" ), '/contact/ is served even without a WordPress contact page' );

echo "\n[Test 8] the message store never exposes enquiries publicly\n";
ok( 1 === preg_match( "/'public'\s*=>\s*false/", $store ), 'the contact message post type is not public' );
ok( 1 === preg_match( "/'publicly_queryable'\s*=>\s*false/", $store ), 'it is not publicly queryable' );
ok( 1 === preg_match( "/'exclude_from_search'\s*=>\s*true/", $store ), 'it is excluded from site search' );
ok( 1 === preg_match( "/'show_in_rest'\s*=>\s*false/", $store ), 'it is not exposed over the REST API' );
ok( 1 === preg_match( "/'show_ui'\s*=>\s*true/", $store ), 'but it IS visible in wp-admin, which is the whole point' );

echo "\n[Test 9] only administrators and editors can read contact messages, not contributors or authors\n";
$GLOBALS['__cpt']   = array();
$GLOBALS['__hooks'] = array();
function add_action( $hook, $cb ) { $GLOBALS['__hooks'][ $hook ][] = $cb; }
function add_filter( $hook, $cb ) { $GLOBALS['__hooks'][ $hook ][] = $cb; }
function register_post_type( $type, $args ) { $GLOBALS['__cpt'][ $type ] = $args; }
if ( ! function_exists( '__' ) ) { function __( $t, $d = null ) { return $t; } }
require $theme . '/inc/contact-messages-store.php';
prospergenics_register_contact_message_post_type();
$cpt = $GLOBALS['__cpt']['pg_contact_msg'];
ok( array( 'pg_contact_msg', 'pg_contact_msgs' ) === $cpt['capability_type'] && true === $cpt['map_meta_cap'], 'messages have their own capability type, not "post"' );
ok( 'do_not_allow' === $cpt['capabilities']['create_posts'], 'nobody creates messages in wp-admin' );
ok( in_array( 'prospergenics_contact_message_grant_caps', $GLOBALS['__hooks']['user_has_cap'], true ), 'the capabilities are granted through user_has_cap' );
$roles = array(
	'administrator' => array( 'read' => true, 'edit_posts' => true, 'edit_others_posts' => true, 'manage_options' => true ),
	'editor'        => array( 'read' => true, 'edit_posts' => true, 'edit_others_posts' => true, 'publish_posts' => true ),
	'author'        => array( 'read' => true, 'edit_posts' => true, 'publish_posts' => true, 'edit_published_posts' => true ),
	'contributor'   => array( 'read' => true, 'edit_posts' => true ),
	'subscriber'    => array( 'read' => true ),
);
foreach ( $roles as $role => $caps ) {
	$got   = prospergenics_contact_message_grant_caps( $caps );
	$staff = in_array( $role, array( 'administrator', 'editor' ), true );
	$all   = true;
	foreach ( prospergenics_contact_message_caps() as $c ) {
		$all = $all && ! empty( $got[ $c ] );
	}
	$none = ! array_intersect_key( $got, array_flip( prospergenics_contact_message_caps() ) );
	ok( $staff ? $all : $none, $staff ? "$role can read and manage contact messages" : "$role gets no contact message capability" );
}
$admin = file_get_contents( $theme . '/inc/contact-messages-admin.php' );
ok( false !== strpos( $admin, "current_user_can( 'edit_pg_contact_msgs' )" ) && false === strpos( $admin, "current_user_can( 'edit_posts' )" ), 'the failed-mail notice is only shown to people who can read messages' );

echo "\n" . ( $failures === 0 ? "All assertions passed.\n" : "$failures assertion(s) FAILED.\n" );
exit( $failures === 0 ? 0 : 1 );

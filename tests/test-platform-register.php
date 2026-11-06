<?php
/**
 * Standalone test for member registration (card #6338): inc/profiles/register.php.
 *
 * The rules (password strength, validation, IAM answers, bot checks) are pure and tested directly.
 * The orchestration, prospergenics_register_process(), runs against a small fake of the WordPress
 * functions it calls, so the order of the steps (IAM before WordPress, nothing created when a check
 * fails) and what ends up on the new member are asserted too. The wiring that needs a real WordPress
 * (rewrite rule, template, login_init) is checked in the source here and end to end in
 * tests/e2e/role-matrix.js.
 *
 * Run with: php tests/test-platform-register.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/wp-theme/prospergenics-2026/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'PROSPERGENICS_HONEYPOT_FIELD', 'pg_website_url' );
define( 'PROSPERGENICS_MAX_FORM_AGE', DAY_IN_SECONDS );
$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

/* ── a tiny WordPress ── */
class WP_Error {
	private $code; private $message; private $data;
	public function __construct( $code = '', $message = '', $data = '' ) { $this->code = $code; $this->message = $message; $this->data = $data; }
	public function get_error_code() { return $this->code; }
	public function get_error_message() { return $this->message; }
	public function get_error_data() { return $this->data; }
}
class Fake_Role {
	public $caps;
	public function __construct( array $caps ) { $this->caps = $caps; }
	public function has_cap( $cap ) { return ! empty( $this->caps[ $cap ] ); }
}
class Fake_User {
	public $ID; public $user_login;
	public function __construct( $id, $login ) { $this->ID = $id; $this->user_login = $login; }
}

$wp = array(); // state of the fake site, reset by reset_site()
function reset_site( array $over = array() ) {
	global $wp;
	$wp = array_merge( array(
		'options'    => array( 'users_can_register' => '1' ),
		'transients' => array(),
		'users'      => array(),          // id => array( login, email, meta[], args )
		'next_id'    => 100,
		'log'        => array(),          // order of the side effects
		'filters'    => array(),
		'actions'    => array(),
		'http'       => array( 'code' => 201, 'body' => '{"subject":"iam-sub-1"}', 'error' => null, 'calls' => array() ),
		'roles'      => array(
			'subscriber'  => new Fake_Role( array( 'read' => true ) ),
			'pg_writer'   => new Fake_Role( array( 'read' => true, 'edit_pg_articles' => true, 'upload_files' => true ) ),
			'editor'      => new Fake_Role( array( 'read' => true, 'edit_posts' => true ) ),
			'customer'    => new Fake_Role( array( 'read' => true ) ),
		),
		'now_nonce'  => 'good-nonce',
		'ip'         => '203.0.113.9',
	), $over );
	unset( $_SERVER['HTTP_CF_CONNECTING_IP'] );
}
reset_site();

$hooks = array(); // callbacks registered at load time, by tag
function add_filter( $tag, $cb ) { global $hooks; $hooks[ $tag ][] = $cb; return true; }
function add_action( $tag, $cb ) { return add_filter( $tag, $cb ); }
function apply_filters( $tag, $value ) {
	global $wp;
	$args = func_get_args();
	if ( isset( $wp['filters'][ $tag ] ) ) { return call_user_func_array( $wp['filters'][ $tag ], array_slice( $args, 1 ) ); }
	return $value;
}
function do_action( $tag ) { global $wp; $wp['log'][] = 'action:' . $tag; $wp['actions'][ $tag ][] = array_slice( func_get_args(), 1 ); }
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $k, $d = false ) { global $wp; return isset( $wp['options'][ $k ] ) ? $wp['options'][ $k ] : $d; }
function wp_unslash( $v ) { return is_array( $v ) ? array_map( 'wp_unslash', $v ) : ( is_string( $v ) ? stripslashes( $v ) : $v ); }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_parse_url( $url, $component = -1 ) { return parse_url( $url, $component ); }
function wp_hash( $s ) { return hash_hmac( 'sha256', (string) $s, 'test-salt' ); }
function wp_verify_nonce( $nonce, $action ) { global $wp; return 'pg_register' === $action && $nonce === $wp['now_nonce'] ? 1 : false; }
function wp_generate_password( $len = 12 ) { static $n = 0; $n++; return substr( str_pad( dechex( 0xabc00 + $n ), $len, 'x' ), 0, $len ); }
function wp_json_encode( $v ) { return json_encode( $v ); }
function home_url( $path = '' ) { return 'https://test.prospergenics.com' . $path; }
function get_transient( $k ) { global $wp; return isset( $wp['transients'][ $k ] ) ? $wp['transients'][ $k ] : false; }
function set_transient( $k, $v ) { global $wp; $wp['transients'][ $k ] = $v; return true; }
function get_role( $r ) { global $wp; return isset( $wp['roles'][ $r ] ) ? $wp['roles'][ $r ] : null; }
function email_exists( $e ) { global $wp; foreach ( $wp['users'] as $id => $u ) { if ( 0 === strcasecmp( $u['email'], $e ) ) { return $id; } } return false; }
function username_exists( $l ) { global $wp; foreach ( $wp['users'] as $id => $u ) { if ( $u['login'] === $l ) { return $id; } } return false; }
function wp_insert_user( array $args ) {
	global $wp;
	$wp['log'][] = 'wp_insert_user';
	$id = $wp['next_id']++;
	$wp['users'][ $id ] = array( 'login' => $args['user_login'], 'email' => $args['user_email'], 'meta' => array(), 'args' => $args );
	return $id;
}
function update_user_meta( $id, $k, $v ) { global $wp; $wp['users'][ $id ]['meta'][ $k ] = $v; return true; }
function get_userdata( $id ) { global $wp; return isset( $wp['users'][ $id ] ) ? new Fake_User( $id, $wp['users'][ $id ]['login'] ) : false; }
function wp_set_current_user( $id ) { global $wp; $wp['log'][] = 'wp_set_current_user'; }
function wp_set_auth_cookie( $id, $remember ) { global $wp; $wp['log'][] = 'wp_set_auth_cookie'; $wp['remember'] = $remember; }
function wp_remote_post( $url, array $args ) {
	global $wp;
	$wp['log'][] = 'iam';
	$wp['http']['calls'][] = array( 'url' => $url, 'args' => $args );
	return $wp['http']['error'] ? new WP_Error( 'http_request_failed', $wp['http']['error'] ) : array( 'response' => array( 'code' => $wp['http']['code'] ), 'body' => $wp['http']['body'] );
}
function wp_remote_retrieve_response_code( $r ) { return $r['response']['code']; }
function wp_remote_retrieve_body( $r ) { return $r['body']; }
function prospergenics_client_ip() { global $wp; return $wp['ip']; }

require $theme . '/inc/profiles/helpers.php';   // prospergenics_profiles_slugify()
require $theme . '/inc/profiles/register.php';

/** A valid, signed form post as the page would produce it, `age` seconds ago. */
function form_post( array $over = array(), $age = 10 ) {
	$time = time() - $age;
	return array_merge( array(
		'_pgr_nonce'    => 'good-nonce',
		'pg_form_time'  => (string) $time,
		'pg_form_token' => wp_hash( (string) $time ),
		'name'          => 'Amina Wanjiku',
		'email'         => 'Amina@Example.test',
		'password'      => 'correct horse battery',
	), $over );
}

echo "[Test 1] password strength\n";
ok( '' === prospergenics_register_password_problem( 'correct horse battery', 'a@example.test', 'Amina' ), 'a few unrelated words are fine' );
ok( '' !== prospergenics_register_password_problem( 'short1!' ), 'too short' );
ok( '' !== prospergenics_register_password_problem( str_repeat( 'a1', 70 ) ), 'too long (over 128)' );
ok( '' === prospergenics_register_password_problem( substr( str_repeat( 'correct horse battery staple ', 5 ), 0, 128 ) ) && '' !== prospergenics_register_password_problem( substr( str_repeat( 'correct horse battery staple ', 5 ), 0, 129 ) ), 'the maximum is 128 characters' );
ok( '' !== prospergenics_register_password_problem( 'Password123' ), 'a most-common password is refused, whatever its case' );
ok( '' !== prospergenics_register_password_problem( 'aaaaaaaaaaaa' ), 'one repeated character is refused' );
ok( '' !== prospergenics_register_password_problem( '1234567890' ) && '' !== prospergenics_register_password_problem( 'abcdefghijk' ), 'a run of digits or letters is refused' );
ok( '' !== prospergenics_register_password_problem( 'AminaWanjiku1', 'x@example.test', 'Amina Wanjiku' ), 'a password made of the member\'s name is refused' );
ok( '' !== prospergenics_register_password_problem( 'jane.doe12345', 'jane.doe@example.test', 'J' ), 'a password made of the email name is refused' );
ok( '' === prospergenics_register_password_problem( 'amina walked to the market today', 'amina@example.test', 'Amina' ), 'the name inside a long passphrase is fine' );
ok( '' === prospergenics_register_password_problem( 'pässwörd mit ümlauten 42' ), 'non-ASCII passwords are fine' );
ok( false !== strpos( prospergenics_register_password_problem( 'x' ), (string) PROSPERGENICS_REGISTER_PASSWORD_MIN ), 'the message names the minimum' );

echo "[Test 2] names\n";
ok( 'Amina Wanjiku' === prospergenics_register_clean_name( "  Amina \t  <b>Wanjiku</b>\n" ), 'markup stripped, whitespace collapsed' );
ok( '' === prospergenics_register_clean_name( "\x00\x07" ) && '' === prospergenics_register_clean_name( null ), 'control characters and null give ""' );
ok( PROSPERGENICS_REGISTER_NAME_MAX === strlen( prospergenics_register_clean_name( str_repeat( 'a', 300 ) ) ), 'name cut at the maximum' );
ok( array( 'Amina', 'Wanjiku Njeri' ) === prospergenics_register_split_name( 'Amina Wanjiku Njeri' ) && array( 'Madonna', '' ) === prospergenics_register_split_name( 'Madonna' ), 'first name and the rest; one word has no last name' );

echo "[Test 3] validation\n";
$v = prospergenics_register_validate( array() );
ok( isset( $v['errors']['name'], $v['errors']['email'], $v['errors']['password'] ), 'empty form: all three fields are reported' );
$v = prospergenics_register_validate( array( 'name' => 'A B', 'email' => 'not-an-email', 'password' => 'correct horse battery' ) );
ok( array( 'email' ) === array_keys( $v['errors'] ), 'only the bad field is reported' );
$v = prospergenics_register_validate( array( 'name' => 'A B', 'email' => 'taken@example.test', 'password' => 'correct horse battery' ), true );
ok( isset( $v['errors']['email'] ) && false !== strpos( $v['errors']['email'], 'already an account' ), 'an email that is taken is reported (unique email)' );
$v = prospergenics_register_validate( array( 'name' => ' Amina  Wanjiku ', 'email' => '  Amina@Example.TEST ', 'password' => 'correct horse battery' ) );
ok( ! $v['errors'] && 'amina@example.test' === $v['clean']['email'] && 'Amina' === $v['clean']['first'] && 'Wanjiku' === $v['clean']['last'], 'clean values: email lower-cased and trimmed, name split' );
$v = prospergenics_register_validate( array( 'name' => 'A', 'email' => str_repeat( 'a', 95 ) . '@x.co', 'password' => 'correct horse battery' ) );
ok( isset( $v['errors']['email'] ), 'an email over the column limit is refused' );
$v = prospergenics_register_validate( array( 'name' => 'A', 'email' => array( 'x' ), 'password' => array( 'y' ) ) );
ok( isset( $v['errors']['email'], $v['errors']['password'] ), 'array values (a tampered post) are refused, not a fatal error' );
ok( 'amina-wanjiku-ab12c' === prospergenics_register_login_base( 'Amina Wanjiku', 'ab12c' ) && 'member-ab12c' === prospergenics_register_login_base( '日本', 'ab12c' ), 'login name is the name slug plus a suffix, never the email' );

echo "[Test 4] bot checks\n";
$now = 1000000;
ok( 'honeypot' === prospergenics_register_bot_reason( array( 'pg_website_url' => 'http://spam' ), $now, true ), 'filled honeypot' );
ok( 'token' === prospergenics_register_bot_reason( array(), $now, true ) && 'token' === prospergenics_register_bot_reason( array( 'pg_form_time' => $now - 10 ), $now, false ), 'missing time or bad token' );
ok( 'too-fast' === prospergenics_register_bot_reason( array( 'pg_form_time' => $now - 1 ), $now, true ), 'posted within a second' );
ok( 'stale' === prospergenics_register_bot_reason( array( 'pg_form_time' => $now - 90000 ), $now, true ), 'a day-old form' );
ok( '' === prospergenics_register_bot_reason( array( 'pg_form_time' => $now - 5 ), $now, true ), 'a normal fill time passes' );

echo "[Test 5] IAM answers and URL\n";
ok( prospergenics_register_iam_url_ok( 'https://iam.example/api/x' ) && prospergenics_register_iam_url_ok( 'http://localhost:5000/x' ) && prospergenics_register_iam_url_ok( 'http://127.0.0.1/x' ), 'https, or http on localhost' );
ok( ! prospergenics_register_iam_url_ok( 'http://iam.example/x' ) && ! prospergenics_register_iam_url_ok( 'https://u:p@iam.example/x' ) && ! prospergenics_register_iam_url_ok( 'ftp://iam.example' ) && ! prospergenics_register_iam_url_ok( '' ), 'plain http elsewhere, credentials in the URL, other schemes, empty: refused' );
ok( array( 'result' => 'created', 'subject' => 'abc' ) === prospergenics_register_iam_parse( 201, '{"subject":"abc"}' ), '201 with a subject: created' );
ok( 'abc' === prospergenics_register_iam_parse( 200, '{"userId":"abc"}' )['subject'] && '7' === prospergenics_register_iam_parse( 200, '{"id":7}' )['subject'], 'subject is also read from userId / id' );
ok( 'unavailable' === prospergenics_register_iam_parse( 201, '{}' )['result'] && 'unavailable' === prospergenics_register_iam_parse( 200, 'not json' )['result'], 'success without an account id is not "created" (it could not be linked)' );
ok( 'exists' === prospergenics_register_iam_parse( 409, '' )['result'], '409: the account exists' );
ok( 'rejected' === prospergenics_register_iam_parse( 400, '' )['result'] && 'rejected' === prospergenics_register_iam_parse( 422, '' )['result'], '400/422: details rejected' );
foreach ( array( 401, 403, 404, 429, 500, 502, 503, 0 ) as $code ) {
	if ( 'unavailable' !== prospergenics_register_iam_parse( $code, '{"subject":"x"}' )['result'] ) { ok( false, "HTTP $code is unavailable" ); }
}
ok( true, '401/403/404/429/5xx/0 are all "unavailable" (our credential or IAM is the problem, not the member)' );

echo "[Test 6] IAM call\n";
reset_site();
$r = prospergenics_register_iam_provision( 'a@example.test', 'pw-secret-1234', 'A', 'B' );
ok( array( 'status' => 'disabled', 'subject' => '' ) === $r && ! $wp['http']['calls'], 'not configured: disabled, no request' );
define( 'PROSPERGENICS_IAM_PROVISION_URL', 'https://iam.example/api/provision' );
define( 'PROSPERGENICS_IAM_PROVISION_TOKEN', 'svc-token-xyz' );
$r = prospergenics_register_iam_provision( 'a@example.test', 'pw-secret-1234', 'A', 'B' );
$call = $wp['http']['calls'][0];
$body = json_decode( $call['args']['body'], true );
ok( array( 'status' => 'created', 'subject' => 'iam-sub-1' ) === $r, 'configured: created, with IAM\'s subject' );
ok( 'https://iam.example/api/provision' === $call['url'] && 'Bearer svc-token-xyz' === $call['args']['headers']['Authorization'] && 'application/json' === $call['args']['headers']['Content-Type'], 'POST to the configured URL with the Bearer credential' );
ok( array( 'email', 'password', 'firstName', 'lastName', 'source' ) === array_keys( $body ) && 'a@example.test' === $body['email'] && 'pw-secret-1234' === $body['password'] && 'test.prospergenics.com' === $body['source'], 'body carries email, password, first and last name, and the site as source' );
ok( 0 === $call['args']['redirection'] && true === $call['args']['sslverify'] && $call['args']['timeout'] <= 15, 'no redirects followed, TLS verified, short timeout' );
$wp['http']['code'] = 409; $wp['http']['body'] = '';
$r = prospergenics_register_iam_provision( 'a@example.test', 'pw-secret-1234', 'A', 'B' );
ok( is_wp_error( $r ) && 'pg_register_iam_exists' === $r->get_error_code() && 'email' === $r->get_error_data()['field'], '409: an "exists" error on the email field' );
$wp['http']['code'] = 400;
ok( 'pg_register_iam_rejected' === prospergenics_register_iam_provision( 'a@example.test', 'pw', 'A', 'B' )->get_error_code(), '400: rejected' );
$wp['http']['code'] = 503;
$r = prospergenics_register_iam_provision( 'a@example.test', 'pw-secret-1234', 'A', 'B' );
ok( 'pg_register_iam_unavailable' === $r->get_error_code() && false === strpos( $r->get_error_message(), 'pw-secret' ), '503: unavailable, the message never repeats the password' );
$wp['http']['error'] = 'cURL error 28: timed out';
ok( 'pg_register_iam_unavailable' === prospergenics_register_iam_provision( 'a@example.test', 'pw', 'A', 'B' )->get_error_code(), 'transport error: unavailable' );
$wp['http']['error'] = null;
$wp['filters']['prospergenics_register_iam_provisioner'] = function () { return array( 'status' => 'created', 'subject' => 'from-filter' ); };
$before = count( $wp['http']['calls'] );
ok( 'from-filter' === prospergenics_register_iam_provision( 'a@example.test', 'pw', 'A', 'B' )['subject'] && count( $wp['http']['calls'] ) === $before, 'the provisioner filter replaces the whole call' );

echo "[Test 7] registering, end to end (fake WordPress)\n";
reset_site();
$GLOBALS['wp']['filters']['prospergenics_register_iam_provisioner'] = function () { global $wp; $wp['log'][] = 'iam'; return array( 'status' => 'created', 'subject' => 'iam-sub-77' ); };
$id = prospergenics_register_process( form_post() );
$u  = $wp['users'][ $id ];
ok( ! is_wp_error( $id ) && 100 === $id, 'a valid form creates the member' );
ok( 'subscriber' === $u['args']['role'] && 'amina@example.test' === $u['email'] && 'Amina Wanjiku' === $u['args']['display_name'] && 'Amina' === $u['args']['first_name'] && 'Wanjiku' === $u['args']['last_name'], 'member role (subscriber), lower-cased email, name' );
ok( 'correct horse battery' === $u['args']['user_pass'] && 0 === strpos( $u['login'], 'amina-wanjiku-' ) && false === strpos( $u['login'], 'example' ), 'password handed to WordPress as typed; the login name does not contain the email' );
ok( 'iam-sub-77' === $u['meta']['jengo_iam_sub'] && 'created' === $u['meta']['_pg_register_iam'] && 'register' === $u['meta']['_pg_register_source'], 'IAM subject stored where the SSO plugin looks for it; flagged as created in IAM' );
ok( array( 'iam', 'wp_insert_user', 'wp_set_current_user', 'wp_set_auth_cookie', 'action:wp_login', 'action:prospergenics_member_registered' ) === $wp['log'], 'order: IAM first, then WordPress, then logged in' );
ok( false === $wp['remember'], 'the session is a normal one (not "remember me")' );

reset_site();
$GLOBALS['wp']['filters']['prospergenics_register_iam_provisioner'] = function () { global $wp; $wp['log'][] = 'iam'; return array( 'status' => 'disabled', 'subject' => '' ); };
$id = prospergenics_register_process( form_post() );
ok( is_int( $id ) && 'pending' === $wp['users'][ $id ]['meta']['_pg_register_iam'] && ! isset( $wp['users'][ $id ]['meta']['jengo_iam_sub'] ), 'IAM not configured: member created in WordPress only, flagged pending' );

echo "[Test 8] nothing is created when a check fails\n";
$cases = array(
	'registration closed'  => array( array( 'options' => array( 'users_can_register' => '0' ) ), form_post(), 'closed' ),
	'bad nonce'            => array( array(), form_post( array( '_pgr_nonce' => 'forged' ) ), 'expired' ),
	'no nonce'             => array( array(), array_diff_key( form_post(), array( '_pgr_nonce' => 1 ) ), 'expired' ),
	'honeypot'             => array( array(), form_post( array( 'pg_website_url' => 'http://spam.example' ) ), 'Something went wrong' ),
	'forged token'         => array( array(), form_post( array( 'pg_form_token' => 'nope' ) ), 'Something went wrong' ),
	'too fast'             => array( array(), form_post( array(), 0 ), 'very quick' ),
	'weak password'        => array( array(), form_post( array( 'password' => 'password123' ) ), 'highlighted' ),
	'bad email'            => array( array(), form_post( array( 'email' => 'nope' ) ), 'highlighted' ),
	'no name'              => array( array(), form_post( array( 'name' => '   ' ) ), 'highlighted' ),
);
foreach ( $cases as $label => $c ) {
	reset_site( $c[0] );
	$GLOBALS['wp']['filters']['prospergenics_register_iam_provisioner'] = function () { global $wp; $wp['log'][] = 'iam'; return array( 'status' => 'created', 'subject' => 's' ); };
	$r = prospergenics_register_process( $c[1] );
	ok( is_wp_error( $r ) && false !== stripos( $r->get_error_message(), $c[2] ) && ! $wp['users'] && ! in_array( 'iam', $wp['log'], true ), "$label: refused, no IAM call, no user" );
}
reset_site();
$GLOBALS['wp']['filters']['prospergenics_register_iam_provisioner'] = function () { return array( 'status' => 'created', 'subject' => 's' ); };
$r = prospergenics_register_process( form_post( array( 'password' => 'password123' ) ) );
ok( isset( $r->get_error_data()['fields']['password'] ) && 'amina@example.test' === $r->get_error_data()['keep']['email'] && ! isset( $r->get_error_data()['keep']['password'] ), 'the form is given back its name and email, never the password' );

reset_site();
$GLOBALS['wp']['filters']['prospergenics_register_iam_provisioner'] = function () { global $wp; $wp['log'][] = 'iam'; return array( 'status' => 'created', 'subject' => 's' ); };
wp_insert_user( array( 'user_login' => 'taken', 'user_email' => 'amina@EXAMPLE.test' ) );
$wp['log'] = array();
$r = prospergenics_register_process( form_post() );
ok( is_wp_error( $r ) && isset( $r->get_error_data()['fields']['email'] ) && 1 === count( $wp['users'] ) && ! in_array( 'iam', $wp['log'], true ), 'an email WordPress already has (any case): refused before IAM, field error' );

foreach ( array( 'pg_register_iam_exists' => array( 'field' => 'email' ), 'pg_register_iam_unavailable' => array(), 'pg_register_iam_rejected' => array() ) as $code => $data ) {
	reset_site();
	$GLOBALS['wp']['filters']['prospergenics_register_iam_provisioner'] = function () use ( $code, $data ) { return new WP_Error( $code, 'iam says no', $data ); };
	$r = prospergenics_register_process( form_post() );
	ok( is_wp_error( $r ) && 'iam says no' === $r->get_error_message() && ! $wp['users'] && ! in_array( 'wp_set_auth_cookie', $wp['log'], true ), "IAM answer $code: no WordPress member, nobody logged in" );
}

echo "[Test 9] rate limit\n";
reset_site();
$GLOBALS['wp']['filters']['prospergenics_register_iam_provisioner'] = function () { return array( 'status' => 'disabled', 'subject' => '' ); };
for ( $i = 0; $i < PROSPERGENICS_REGISTER_RATE_LIMIT; $i++ ) {
	prospergenics_register_process( form_post( array( 'email' => 'bad' ) ) ); // failed attempts count too
}
$r = prospergenics_register_process( form_post() );
ok( is_wp_error( $r ) && false !== strpos( $r->get_error_message(), 'Too many attempts' ) && ! $wp['users'], 'after the limit, even a valid form is refused' );
$wp['ip'] = '198.51.100.7';
ok( is_int( prospergenics_register_process( form_post() ) ), 'another connection is not affected' );
$wp['ip'] = '';
reset_site( array( 'ip' => '' ) );
$GLOBALS['wp']['filters']['prospergenics_register_iam_provisioner'] = function () { return array( 'status' => 'disabled', 'subject' => '' ); };
ok( is_int( prospergenics_register_process( form_post() ) ), 'no usable IP: the limit is skipped, registration still works' );

echo "[Test 10] role\n";
reset_site();
ok( 'subscriber' === prospergenics_register_role(), 'default: subscriber' );
$wp['filters']['prospergenics_register_role'] = function () { return 'customer'; };
ok( 'customer' === prospergenics_register_role(), 'a plain role can be chosen' );
foreach ( array( 'editor', 'pg_writer', 'administrator', 'nonexistent', '' ) as $role ) {
	$wp['filters']['prospergenics_register_role'] = function () use ( $role ) { return $role; };
	if ( 'subscriber' !== prospergenics_register_role() ) { ok( false, "role '$role' falls back to subscriber" ); }
}
ok( true, 'editor, writer, administrator, unknown and empty roles fall back to subscriber (never privileged)' );

echo "[Test 11] switch\n";
reset_site( array( 'options' => array( 'users_can_register' => '' ) ) );
ok( ! prospergenics_register_is_open(), 'off by default (users_can_register empty)' );
reset_site();
ok( prospergenics_register_is_open(), 'on when "Anyone can register" is ticked' );
$wp['filters']['prospergenics_register_open'] = function () { return false; };
ok( ! prospergenics_register_is_open(), 'a filter can switch it off' );

echo "[Test 12] source wiring\n";
$src   = file_get_contents( $theme . '/inc/profiles/register.php' );
$routes = file_get_contents( $theme . '/inc/profiles/routes.php' );
$loader = file_get_contents( $theme . '/inc/platform-profiles.php' );
$tpl   = file_get_contents( $theme . '/pg-register.php' );
$chrome = file_get_contents( $theme . '/inc/site-chrome.php' );
ok( false !== strpos( $routes, "add_rewrite_rule( '^register/?\$', 'index.php?pg_profile_page=register', 'top' )" ) && false !== strpos( $routes, "'register'  => 'pg-register.php'" ), 'routes: /register/ rewrite rule and template' );
ok( preg_match( "/PROSPERGENICS_PROFILES_REWRITE_VERSION = '(\d+)'/", $routes, $m ) && (int) $m[1] >= 3, 'rewrite rules version bumped so they flush once' );
ok( false !== strpos( $loader, "'register.php'" ), 'the profiles loader requires register.php' );
ok( isset( $hooks['register_url'] ) && 'https://test.prospergenics.com/register/' === $hooks['register_url'][0](), 'the register_url filter returns /register/' );
ok( isset( $hooks['login_init'] ), 'login_init is hooked' );
ok( false !== strpos( $src, "add_filter( 'register_url'" ) && false !== strpos( $src, "add_action( 'login_init'" ) && false !== strpos( $src, "'register' === \$action" ), 'wp-login.php?action=register leads to /register/ (no registration that skips the checks)' );
ok( false !== strpos( $tpl, "wp_nonce_field( 'pg_register', '_pgr_nonce' )" ) && false !== strpos( $tpl, 'prospergenics_spam_protection_fields()' ) && false !== strpos( $tpl, 'autocomplete' ) && false !== strpos( $tpl, "'new-password'" ), 'form: nonce, honeypot + time token, new-password autocomplete' );
ok( false !== strpos( $tpl, "'pgr-password', 'password', 'Password', '', 'password'" ) && false === strpos( $tpl, "keep['password']" ), 'form: the password field is a password input and is never refilled' );
ok( false !== strpos( $chrome, 'prospergenics_register_is_open()' ) && false !== strpos( $chrome, "home_url( '/register/' )" ), 'Join links to /register/ while registration is on' );
$leaks = 0;
foreach ( explode( "\n", $src ) as $line ) {
	if ( false !== strpos( $line, 'error_log' ) && preg_match( '/\$(password|in\[|post)/', $line ) ) { $leaks++; }
}
ok( 0 === $leaks, 'no error_log line mentions the password or the post' );
ok( false === strpos( $src, 'sanitize_text_field( $in' ) && 0 === preg_match( '/sanitize_text_field\(\s*\$(password|in\[.password)/', $src ), 'the password is never passed through a text sanitiser' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

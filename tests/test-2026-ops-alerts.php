<?php
/**
 * Standalone test for the error alerts (card #6473, inc/ops-alerts.php, tests/smoke/alert-issue.sh).
 *
 *   - a PHP fatal error is logged with its message cleaned (no stack trace, no server path, no
 *     password/token values), the same error within an hour only raises the count, the log is capped;
 *   - a fatal error is emailed to PROSPERGENICS_ALERT_EMAIL, at most once an hour; a failed email is
 *     only logged; warnings are not recorded at all;
 *   - administrators (only) see the log on /manage/, escaped;
 *   - functions.php loads the module first; the uptime and deploy workflows open and close the
 *     alert issue; alert-issue.sh opens one issue, then comments, then closes it (with a fake gh).
 *
 * Run with: php tests/test-2026-ops-alerts.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ────────────────────── WP stubs ──────────────────────
define( 'ABSPATH', '/var/www/site/' );
define( 'HOUR_IN_SECONDS', 3600 );
define( 'DAY_IN_SECONDS', 86400 );
define( 'PROSPERGENICS_ALERT_EMAIL', 'team@example.org' );

class WP_Error {
	private $m;
	function __construct( $c = '', $m = '' ) { $this->m = $m; }
	function get_error_message() { return $this->m; }
}
$GLOBALS['options'] = array( 'admin_email' => 'admin@example.org' );
$GLOBALS['mails']   = array();
$GLOBALS['actions'] = array();
$GLOBALS['can']     = true;
$GLOBALS['shutdown'] = array();
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['options'] ) ? $GLOBALS['options'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function apply_filters( $hook, $v ) { return $v; }
function add_action( $hook, $cb, $prio = 10 ) { $GLOBALS['actions'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $prio = 10 ) {}
function is_email( $e ) { return (bool) filter_var( $e, FILTER_VALIDATE_EMAIL ); }
function home_url( $p = '/' ) { return 'https://test.prospergenics.com' . $p; }
function admin_url( $p = '' ) { return 'https://test.prospergenics.com/wp-admin/' . $p; }
function wp_mail( $to, $s, $b ) { $GLOBALS['mails'][] = compact( 'to', 's', 'b' ); return true; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function current_user_can( $c ) { return $GLOBALS['can']; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function esc_html__( $s ) { return $s; }
function human_time_diff( $t ) { return '1 min'; }
function number_format_i18n( $n ) { return (string) $n; }

// The module registers its shutdown handler on load: keep a handle instead of running it at exit.
$src = file_get_contents( $theme . '/inc/ops-alerts.php' );
ok( false !== strpos( $src, "register_shutdown_function( 'prospergenics_2026_ops_shutdown' );" ), 'the module registers a shutdown handler' );
eval( '?>' . str_replace( "register_shutdown_function( 'prospergenics_2026_ops_shutdown' );", '', $src ) );

echo "Cleaning\n";
$msg = "Uncaught PDOException: SQLSTATE[HY000] connect failed in /var/www/site/wp-content/themes/prospergenics-2026/inc/x.php:12\nStack trace:\n#0 /var/www/site/x.php(12): PDO->__construct('mysql:host=db', 'wp', 'hunter2')\n#1 {main}";
$clean = prospergenics_2026_ops_clean_message( $msg, ABSPATH );
ok( false === strpos( $clean, 'hunter2' ) && false === strpos( $clean, 'Stack trace' ), 'the stack trace (with its arguments) is cut off' );
ok( false === strpos( $clean, '/var/www/site/' ) && false !== strpos( $clean, 'wp-content/themes/prospergenics-2026/inc/x.php:12' ), 'the server path is removed, the theme path kept' );
$clean = prospergenics_2026_ops_clean_message( "bad config password=hunter2 token: abc123 'api_key' => 'k-999' DB_PASSWORD=\"p w\"", ABSPATH );
ok( false === strpos( $clean, 'hunter2' ) && false === strpos( $clean, 'abc123' ) && false === strpos( $clean, 'k-999' ) && false === strpos( $clean, 'p w' ), 'password, token and key values are masked' );
ok( 300 >= strlen( prospergenics_2026_ops_clean_message( str_repeat( 'x', 2000 ) ) ), 'a long message is cut to 300 characters' );
ok( 'wp-content/themes/t/a.php' === prospergenics_2026_ops_clean_file( '/var/www/site/wp-content/themes/t/a.php', ABSPATH ), 'the file is relative to the WordPress root' );
ok( 'a.php' === prospergenics_2026_ops_clean_file( '/elsewhere/a.php', ABSPATH ), 'a file outside the root shows only its name' );

echo "Fatal or not\n";
ok( prospergenics_2026_ops_is_fatal( array( 'type' => E_ERROR, 'message' => 'x' ) ), 'E_ERROR is fatal' );
ok( prospergenics_2026_ops_is_fatal( array( 'type' => E_PARSE, 'message' => 'x' ) ), 'E_PARSE is fatal' );
ok( ! prospergenics_2026_ops_is_fatal( array( 'type' => E_WARNING, 'message' => 'x' ) ), 'a warning is not' );
ok( ! prospergenics_2026_ops_is_fatal( null ), 'no error is not' );

echo "Log\n";
$log = array();
$e   = array( 'kind' => 'php', 'message' => 'boom', 'file' => 'a.php', 'line' => 3, 'path' => '/courses/' );
$log = prospergenics_2026_ops_add_entry( $log, $e, 1000 );
$log = prospergenics_2026_ops_add_entry( $log, $e, 1500 );
ok( 1 === count( $log ) && 2 === $log[0]['count'] && 1000 === $log[0]['first'] && 1500 === $log[0]['time'], 'the same error within an hour raises the count' );
$log = prospergenics_2026_ops_add_entry( $log, $e, 1500 + 2 * HOUR_IN_SECONDS );
ok( 2 === count( $log ) && 1 === $log[0]['count'], 'the same error after an hour is a new line' );
$log = prospergenics_2026_ops_add_entry( $log, array( 'kind' => 'php', 'message' => 'other' ), 9000 );
ok( 'other' === $log[0]['message'] && 3 === count( $log ), 'another error is a new line, newest first' );
for ( $i = 0; $i < 50; $i++ ) {
	$log = prospergenics_2026_ops_add_entry( $log, array( 'message' => "m$i" ), 10000 + $i );
}
ok( PROSPERGENICS_OPS_LOG_SIZE === count( $log ), 'the log keeps the last ' . PROSPERGENICS_OPS_LOG_SIZE );

echo "Recording and alert email\n";
$_SERVER['REQUEST_URI'] = '/courses/html/?token=secret123';
prospergenics_2026_ops_record( 'php', "Call to undefined function foo() in /var/www/site/wp-content/themes/prospergenics-2026/x.php:9\nStack trace:\n#0 {main}", '/var/www/site/wp-content/themes/prospergenics-2026/x.php', 9 );
$stored = get_option( PROSPERGENICS_OPS_LOG );
ok( 1 === count( $stored ) && '/courses/html/' === $stored[0]['path'], 'the request path is stored without its query string' );
ok( 'wp-content/themes/prospergenics-2026/x.php' === $stored[0]['file'] && 9 === $stored[0]['line'], 'file and line are stored' );
ok( 1 === count( $GLOBALS['mails'] ) && 'team@example.org' === $GLOBALS['mails'][0]['to'], 'a fatal error is emailed to PROSPERGENICS_ALERT_EMAIL' );
$mail = $GLOBALS['mails'][0];
ok( false !== strpos( $mail['s'], 'PHP fatal error' ) && false !== strpos( $mail['s'], '/courses/html/' ), 'the subject names the error and the page' );
ok( false === strpos( $mail['b'], 'secret123' ) && false === strpos( $mail['b'], '/var/www/site' ) && false === strpos( $mail['b'], 'Stack trace' ), 'the email has no query string, server path or stack trace' );
ok( false !== strpos( $mail['b'], 'https://test.prospergenics.com/manage/' ), 'the email links to the /manage/ log' );
prospergenics_2026_ops_record( 'php', 'second fatal', 'y.php', 1 );
ok( 1 === count( $GLOBALS['mails'] ) && 2 === count( get_option( PROSPERGENICS_OPS_LOG ) ), 'a second fatal within the hour is logged, not emailed' );
$GLOBALS['options']['prospergenics_ops_alert_sent'] = time() - 2 * HOUR_IN_SECONDS;
prospergenics_2026_ops_record( 'php', 'third fatal', 'y.php', 2 );
ok( 2 === count( $GLOBALS['mails'] ), 'after an hour the next fatal is emailed again' );
$GLOBALS['options']['prospergenics_ops_alert_sent'] = 0;
foreach ( $GLOBALS['actions']['wp_mail_failed'] as $cb ) {
	$cb( new WP_Error( 'wp_mail_failed', 'SMTP Error: Could not authenticate.' ) );
}
$stored = get_option( PROSPERGENICS_OPS_LOG );
ok( 'mail' === $stored[0]['kind'] && false !== strpos( $stored[0]['message'], 'Could not authenticate' ), 'a failed wp_mail() is logged' );
ok( 2 === count( $GLOBALS['mails'] ), 'a failed email is not answered with another email' );
ok( 'team@example.org' === prospergenics_2026_ops_alert_email(), 'the alert address is PROSPERGENICS_ALERT_EMAIL when set' );

echo "/manage/ overview\n";
update_option( PROSPERGENICS_OPS_LOG, prospergenics_2026_ops_add_entry( get_option( PROSPERGENICS_OPS_LOG ), array( 'kind' => 'php', 'message' => '<script>alert(1)</script>', 'path' => '/x/' ), time() ) );
$html = prospergenics_2026_ops_manage_html();
ok( false !== strpos( $html, 'Site errors' ) && false !== strpos( $html, 'Could not authenticate' ), 'administrators see the log' );
ok( false === strpos( $html, '<script>alert' ) && false !== strpos( $html, '&lt;script&gt;' ), 'entries are escaped' );
ok( false !== strpos( $html, 'emailed to team@example.org' ), 'the overview says where alerts go' );
ok( 1 <= count( prospergenics_2026_ops_unseen_fatals() ), 'new fatal errors are waiting for the admin notice' );
$GLOBALS['can'] = false;
ok( '' === prospergenics_2026_ops_manage_html(), 'anyone else sees nothing' );
$overview = file_get_contents( $theme . '/inc/manage/views/overview.php' );
ok( false !== strpos( $overview, 'prospergenics_2026_ops_manage_html()' ), 'the /manage/ overview prints the section' );

echo "Loaded first\n";
$functions = file_get_contents( $theme . '/functions.php' );
$at_ops    = strpos( $functions, "/inc/ops-alerts.php'" );
ok( false !== $at_ops && $at_ops < strpos( $functions, "/inc/security.php'" ) && $at_ops < strpos( $functions, "/inc/platform-profiles.php'" ), 'functions.php loads ops-alerts.php before the other modules' );
ok( false !== strpos( $src, "check_admin_referer( 'pg_ops_errors_seen' )" ) && false !== strpos( $src, "current_user_can( 'manage_options' ) || ! check_admin_referer" ), 'dismissing the notice needs manage_options and a nonce' );

echo "Workflows\n";
$uptime = file_get_contents( $root . '/.github/workflows/uptime.yml' );
$deploy = file_get_contents( $root . '/.github/workflows/deploy-test.yml' );
foreach ( array( '/', '/courses/', '/articles/', '/wp-login.php' ) as $p ) {
	ok( false !== strpos( file_get_contents( $root . '/tests/smoke/uptime-check.sh' ), " $p" ), "uptime check covers $p" );
}
ok( false !== strpos( $uptime, 'issues: write' ) && false !== strpos( $uptime, 'alert-issue.sh open "Uptime check failing"' ) && false !== strpos( $uptime, 'alert-issue.sh close "Uptime check failing"' ), 'uptime.yml opens and closes the alert issue' );
ok( false !== strpos( $uptime, "if: failure()" ) && false !== strpos( $uptime, 'shell: bash' ), 'the alert runs on failure, and the check keeps its exit code through tee' );
ok( false !== strpos( $deploy, 'issues: write' ) && false !== strpos( $deploy, "steps.smoke.outcome == 'failure'" ) && false !== strpos( $deploy, 'alert-issue.sh close "Deploy to test failing"' ), 'deploy-test.yml opens the alert issue on a failed upload or smoke check and closes it after a good one' );
ok( false === strpos( $uptime . $deploy, 'secrets.ALERT' ), 'the alerts need no new secret' );

echo "alert-issue.sh (fake gh)\n";
$tmp = sys_get_temp_dir() . '/pg-alert-test-' . getmypid();
@mkdir( $tmp . '/bin', 0777, true );
// The fake gh keeps one "issue store" in a file: list prints the open issue, create/comment/close log.
file_put_contents( $tmp . '/bin/gh', <<<'SH'
#!/usr/bin/env bash
store="$PG_FAKE_GH"
echo "$*" >> "$store.calls"
case "$1 $2" in
"issue list") [ -f "$store.open" ] && cat "$store.open"; exit 0 ;;
"issue create") echo 7 > "$store.open"; echo "https://github.com/o/r/issues/7" ;;
"issue close") rm -f "$store.open" ;;
esac
exit 0
SH
);
chmod( $tmp . '/bin/gh', 0755 );
file_put_contents( $tmp . '/details.txt', "FAIL  / HTTP 500 (3 tries)\n" );
$run = function ( $args ) use ( $tmp, $root ) {
	$cmd = 'PATH=' . escapeshellarg( $tmp . '/bin' ) . ':"$PATH" PG_FAKE_GH=' . escapeshellarg( $tmp . '/gh' ) . ' bash ' . escapeshellarg( $root . '/tests/smoke/alert-issue.sh' ) . ' ' . $args . ' 2>&1';
	exec( $cmd, $out, $code );
	return $code;
};
ok( 0 === $run( 'open "Uptime check failing" ' . escapeshellarg( $tmp . '/details.txt' ) ), 'open exits 0' );
$calls = file_get_contents( $tmp . '/gh.calls' );
ok( false !== strpos( $calls, 'issue create --title Uptime check failing --label alert' ), 'the first failure creates a labelled issue' );
$run( 'open "Uptime check failing" ' . escapeshellarg( $tmp . '/details.txt' ) );
$calls = file_get_contents( $tmp . '/gh.calls' );
ok( 1 === substr_count( $calls, 'issue create' ) && false !== strpos( $calls, 'issue comment 7' ), 'a second failure comments on the open issue' );
$run( 'close "Uptime check failing"' );
ok( false !== strpos( file_get_contents( $tmp . '/gh.calls' ), 'issue close 7' ), 'a passing check closes it' );
$before = file_get_contents( $tmp . '/gh.calls' );
$run( 'close "Uptime check failing"' );
ok( false === strpos( substr( file_get_contents( $tmp . '/gh.calls' ), strlen( $before ) ), 'issue close' ), 'with no open issue, close does nothing' );
array_map( 'unlink', array_filter( array_merge( glob( $tmp . '/bin/*' ) ?: array(), glob( $tmp . '/*' ) ?: array() ), 'is_file' ) );
@rmdir( $tmp . '/bin' );
@rmdir( $tmp );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All passed\n" );
exit( $failures ? 1 : 0 );

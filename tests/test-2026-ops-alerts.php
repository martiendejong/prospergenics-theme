<?php
/**
 * Standalone test for the error alerts (card #6473, inc/ops-alerts.php, tests/smoke/alert-issue.sh).
 *
 *   - a PHP fatal error is logged with its message cleaned (no stack trace, no server path, no
 *     password/token values), the same error within an hour only raises the count, the log is capped;
 *   - a fatal error is emailed to PROSPERGENICS_ALERT_EMAIL, at most once an hour; a failed email is
 *     only logged; warnings are not recorded at all;
 *   - administrators (only) see the log on /manage/, escaped (#8081: the same error once with its
 *     count and last-seen time, test entries labelled, a stacked row per error on phones);
 *   - administrators (only, with the nonce of that action) remove one error, the test entries or
 *     the whole log; the /manage/ notice for each;
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
function human_time_diff( $t ) { $h = (int) floor( abs( time() - (int) $t ) / 3600 ); return $h ? "$h hours" : '1 min'; }
function number_format_i18n( $n ) { return (string) $n; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function wp_date( $f, $t ) { return gmdate( $f, $t ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function wp_unslash( $v ) { return $v; }
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { return '<input type="hidden" name="' . $name . '" value="nonce:' . $action . '" />'; }
class PG_Died extends Exception {}
function wp_die( $m = '', $code = 500 ) { throw new PG_Died( $m, is_array( $code ) ? 500 : (int) $code ); }
$GLOBALS['nonce_ok'] = true;
$GLOBALS['nonce_checked'] = array();
function check_admin_referer( $action ) { $GLOBALS['nonce_checked'][] = $action; if ( ! $GLOBALS['nonce_ok'] ) { throw new PG_Died( 'The link you followed has expired.', 403 ); } return 1; }

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

echo "Grouping (#8081)\n";
$long = 'Call to undefined function prospergenics_courses_listings_category_filter_options_for_the_archive_page_with_a_very_long_name()';
$glog = array();
$glog = prospergenics_2026_ops_add_entry( $glog, array( 'kind' => 'php', 'message' => $long, 'file' => 'wp-content/themes/prospergenics-2026/archive-pg_course.php', 'line' => 13, 'path' => '/courses/' ), 1000 );
$glog = prospergenics_2026_ops_add_entry( $glog, array( 'kind' => 'php', 'message' => $long, 'file' => 'wp-content/themes/prospergenics-2026/archive-pg_course.php', 'line' => 13, 'path' => '/courses/' ), 1200 );
$glog = prospergenics_2026_ops_add_entry( $glog, array( 'kind' => 'php', 'message' => $long, 'file' => 'wp-content/themes/prospergenics-2026/archive-pg_course.php', 'line' => 16, 'path' => '/courses/' ), 1300 );
$glog = prospergenics_2026_ops_add_entry( $glog, array( 'kind' => 'php', 'message' => $long, 'file' => 'wp-content/themes/prospergenics-2026/archive-pg_course.php', 'line' => 13, 'path' => '/courses/html/' ), 1200 + 5 * HOUR_IN_SECONDS );
$glog = prospergenics_2026_ops_add_entry( $glog, array( 'kind' => 'mail', 'message' => 'Ops-alerts test from task 6473' ), 2000 );
$glog = prospergenics_2026_ops_add_entry( $glog, array( 'kind' => 'php', 'message' => 'Uncaught Error: Call to undefined function pg_no_such_function()', 'file' => 'wp-content/mu-plugins/pg-fatal-test.php', 'line' => 1 ), 2100 );
ok( 5 === count( $glog ), 'the log itself keeps one entry per error per hour (5 here)' );
$rows = prospergenics_2026_ops_group( $glog );
ok( 4 === count( $rows ), 'grouped: the same message + file:line is one row (4 rows)' );
ok( $long === $rows[0]['message'] && 13 === $rows[0]['line'] && 3 === $rows[0]['count'], 'the row counts every time it happened, across hours (3)' );
ok( 1200 + 5 * HOUR_IN_SECONDS === $rows[0]['time'] && 1000 === $rows[0]['first'] && '/courses/html/' === $rows[0]['path'], 'the row has the last-seen time, the first time and the page of the last time' );
$by_line = array();
foreach ( $rows as $r ) { $by_line[ $r['file'] . ':' . $r['line'] ] = $r; }
ok( isset( $by_line['wp-content/themes/prospergenics-2026/archive-pg_course.php:16'] ) && 1 === $by_line['wp-content/themes/prospergenics-2026/archive-pg_course.php:16']['count'], 'the same message on another line is its own row' );
ok( prospergenics_2026_ops_key( $glog[2] ) === $rows[0]['key'] && preg_match( '/^[a-f0-9]{32}$/', $rows[0]['key'] ), 'each row has a 32-hex key' );
$times = array_map( function ( $r ) { return $r['time']; }, $rows );
$sorted = $times; rsort( $sorted );
ok( $times === $sorted, 'rows are last seen first' );
ok( 2 === count( array_filter( $rows, function ( $r ) { return $r['test']; } ) ), 'two rows are from the alert test' );
ok( prospergenics_2026_ops_is_test( array( 'message' => 'Ops-alerts test from task 6473: please ignore' ) ), 'an "Ops-alerts test from task" message is a test entry' );
ok( prospergenics_2026_ops_is_test( array( 'message' => 'Call to undefined function pg_no_such_function()' ) ) && prospergenics_2026_ops_is_test( array( 'message' => 'x', 'file' => 'wp-content/mu-plugins/pg-fatal-test.php' ) ), 'the test plugin fatal is a test entry' );
ok( ! prospergenics_2026_ops_is_test( array( 'message' => $long, 'file' => 'archive-pg_course.php' ) ), 'a real error is not' );
ok( 3 === count( prospergenics_2026_ops_remove( $glog, 'tests' ) ) && 0 === count( prospergenics_2026_ops_remove( $glog, 'all' ) ), 'remove: the test entries, or everything' );
ok( 3 === count( prospergenics_2026_ops_remove( $glog, $rows[0]['key'] ) ), 'remove one error: every entry of it goes (2 entries, 3 times)' );
ok( 5 === count( prospergenics_2026_ops_remove( $glog, str_repeat( 'a', 32 ) ) ), 'an unknown key removes nothing' );

echo "Site errors markup (#8081)\n";
$GLOBALS['can'] = true;
update_option( PROSPERGENICS_OPS_LOG, $glog );
$html = prospergenics_2026_ops_manage_html();
ok( 4 === substr_count( $html, '<tr class="pgm-erow' ), 'one row per error' );
ok( false !== strpos( $html, 'class="pgm-table pgm-etable"' ) && false !== strpos( $html, '<th scope="col">Last seen</th>' ), 'a table (pgm-etable) with a Last seen column' );
ok( false !== strpos( $html, '<td class="pgm-num pgm-e-times">3<span class="pgm-e-label"> times</span></td>' ), 'the count of a repeated error (3 times)' );
$hrs = function ( $t ) { return (int) floor( ( time() - $t ) / 3600 ); };
ok( false !== strpos( $html, '<time datetime="' . gmdate( 'c', 1200 + 5 * HOUR_IN_SECONDS ) . '" title="' . gmdate( 'j M Y, H:i', 1200 + 5 * HOUR_IN_SECONDS ) . '">' . $hrs( 1200 + 5 * HOUR_IN_SECONDS ) . ' hours ago</time><span class="pgm-muted pgm-block">first ' . $hrs( 1000 ) . ' hours ago</span>' ), 'the last-seen time, and the first for a repeat' );
ok( 1 === substr_count( $html, 'first ' ), '"first ..." only for a repeat whose first time reads differently' );
ok( 2 === substr_count( $html, 'pgm-e-nopage' ), 'rows without a page (here a failed email and the test fatal) are marked, so phones skip the empty Page line' );
ok( 2 === substr_count( $html, 'pgm-badge pgm-badge-warn pgm-e-test' ) && false !== strpos( $html, '>test</span>' ), 'test entries carry a "test" label' );
ok( false !== strpos( $html, '<span class="pgm-e-msg">PHP fatal error: ' . $long . '</span><span class="pgm-e-file">wp-content/themes/prospergenics-2026/archive-pg_course.php:13</span>' ), 'the whole message and file:line, each in its own element' );
ok( 4 === substr_count( $html, 'name="do" value="dismiss"' ) && false !== strpos( $html, 'name="error" value="' . $rows[0]['key'] . '"' ), 'a Dismiss form per error with its key' );
ok( false !== strpos( $html, 'value="nonce:pg_ops_errors_dismiss_' . $rows[0]['key'] . '"' ), 'each Dismiss form has the nonce of that error' );
ok( false !== strpos( $html, 'method="post" action="https://test.prospergenics.com/wp-admin/admin-post.php"' ) && false !== strpos( $html, 'name="action" value="pg_ops_errors"' ), 'the forms POST to admin-post.php?action=pg_ops_errors' );
ok( false !== strpos( $html, 'value="nonce:pg_ops_errors_tests"' ) && false !== strpos( $html, 'Remove the 2 test entries' ), 'a button removes the test entries' );
ok( false !== strpos( $html, 'value="nonce:pg_ops_errors_clear"' ) && false !== strpos( $html, '<details class="pgm-suspend pgm-e-clear">' ), 'Clear all sits behind a second step, with its own nonce' );
ok( false === strpos( $html, 'Show all' ), 'no "Show all" link with 10 errors or fewer' );
update_option( PROSPERGENICS_OPS_LOG, array( array( 'kind' => 'php', 'message' => 'Uncaught Error: Call to undefined function pg_no_such_function() in wp-content/mu-plugins/pg-fatal-test.php:1', 'file' => 'wp-content/mu-plugins/pg-fatal-test.php', 'line' => 1, 'path' => '/', 'time' => time() ) ) );
ok( false !== strpos( prospergenics_2026_ops_manage_html(), '<span class="pgm-e-msg">PHP fatal error: Uncaught Error: Call to undefined function pg_no_such_function()</span><span class="pgm-e-file">wp-content/mu-plugins/pg-fatal-test.php:1</span>' ), 'PHP\'s " in file:line" is not repeated in the message: file:line has its own line' );
$many = $glog;
for ( $i = 0; $i < 12; $i++ ) { $many = prospergenics_2026_ops_add_entry( $many, array( 'kind' => 'php', 'message' => "err $i" ), 3000 + $i ); }
update_option( PROSPERGENICS_OPS_LOG, $many );
$html = prospergenics_2026_ops_manage_html();
ok( 10 === substr_count( $html, '<tr class="pgm-erow' ) && false !== strpos( $html, 'Show all 16 errors' ) && false !== strpos( $html, '/manage/?errors=all#pgm-errors-h' ), 'the overview shows 10 errors and links to all of them' );
$_GET['errors'] = 'all';
ok( 16 === substr_count( prospergenics_2026_ops_manage_html(), '<tr class="pgm-erow' ), '?errors=all shows every error' );
unset( $_GET['errors'] );
update_option( PROSPERGENICS_OPS_LOG, array( array( 'kind' => 'php', 'message' => 'x"><img src=x onerror=alert(1)>', 'file' => '<b>f.php', 'line' => 2, 'path' => '/<i>/', 'time' => time() ) ) );
$html = prospergenics_2026_ops_manage_html();
ok( false === strpos( $html, '<img' ) && false === strpos( $html, '<b>f.php' ) && false === strpos( $html, '<i>' ) && false !== strpos( $html, '&lt;img' ), 'message, file and page are escaped (also in the screen-reader text of Dismiss)' );
ok( false === strpos( $html, 'Remove the' ), 'no test button without test entries' );
$css = file_get_contents( $theme . '/assets/platform/manage.css' );
ok( false !== strpos( $css, '.pgm-etable td{overflow-wrap:anywhere;}' ), 'manage.css: long names and paths break anywhere' );
$at_m = strpos( $css, '@media(max-width:640px){' );
ok( false !== $at_m && false !== strpos( $css, '.pgm-etable tr{display:grid;', $at_m ) && false !== strpos( $css, '.pgm-etable td{display:block;', $at_m ), 'manage.css: at 640px and less each error is a stacked row' );
ok( false !== strpos( $css, '.pgm-e-label{display:none;}' ) && false !== strpos( $css, '.pgm-e-label{display:inline;', $at_m ), 'manage.css: the Page / times labels only show in the stacked rows' );
ok( false !== strpos( $css, '.pgm-etable .pgm-e-when,.pgm-etable .pgm-e-times,.pgm-etable .pgm-e-act{white-space:nowrap;overflow-wrap:normal;}' ), 'manage.css: the time, count and Dismiss button do not break letter by letter' );

echo "Clearing (#8081)\n";
$post = function ( $fields ) {
	$_POST = $fields;
	try {
		return prospergenics_2026_ops_errors_handle();
	} catch ( PG_Died $e ) {
		return 'died:' . $e->getCode();
	}
};
$key = $rows[0]['key'];
update_option( PROSPERGENICS_OPS_LOG, $glog );
$GLOBALS['can'] = false;
ok( 'died:403' === $post( array( 'do' => 'clear' ) ) && 5 === count( get_option( PROSPERGENICS_OPS_LOG ) ), 'a non-admin cannot clear the log (403)' );
ok( 'died:403' === $post( array( 'do' => 'dismiss', 'error' => $key ) ) && 5 === count( get_option( PROSPERGENICS_OPS_LOG ) ), 'a non-admin cannot dismiss an error (403)' );
$GLOBALS['can'] = true;
$GLOBALS['nonce_ok'] = false;
ok( 'died:403' === $post( array( 'do' => 'clear' ) ) && 5 === count( get_option( PROSPERGENICS_OPS_LOG ) ), 'without a valid nonce nothing is cleared' );
$GLOBALS['nonce_ok'] = true;
ok( 'died:400' === $post( array( 'do' => 'delete_everything' ) ) && 'died:400' === $post( array( 'do' => 'dismiss', 'error' => '../x' ) ), 'an unknown action or a malformed key is refused (400)' );
$GLOBALS['nonce_checked'] = array();
ok( 'errors_dismiss' === $post( array( 'do' => 'dismiss', 'error' => strtoupper( $key ) ) ) && 3 === count( get_option( PROSPERGENICS_OPS_LOG ) ), 'an admin dismisses one error (both of its entries)' );
ok( array( 'pg_ops_errors_dismiss_' . $key ) === $GLOBALS['nonce_checked'], 'dismiss checks the nonce of that error' );
ok( 'errors_none' === $post( array( 'do' => 'dismiss', 'error' => $key ) ), 'dismissing it again changes nothing' );
ok( 'errors_tests' === $post( array( 'do' => 'tests' ) ) && 0 === count( array_filter( get_option( PROSPERGENICS_OPS_LOG ), 'prospergenics_2026_ops_is_test' ) ), 'an admin removes the test entries' );
update_option( PROSPERGENICS_OPS_LOG, $glog );
ok( 'errors_clear' === $post( array( 'do' => 'clear' ) ) && array() === get_option( PROSPERGENICS_OPS_LOG ), 'an admin clears the whole log' );
ok( false !== strpos( prospergenics_2026_ops_manage_html(), 'No PHP fatal errors or failed emails recorded.' ), 'then the section says there is nothing' );
ok( isset( $GLOBALS['actions']['admin_post_pg_ops_errors'] ) && ! isset( $GLOBALS['actions']['admin_post_nopriv_pg_ops_errors'] ), 'the handler is for logged-in users only (no nopriv)' );
ok( false !== strpos( $src, "wp_safe_redirect( add_query_arg( 'done', \$done, home_url( '/manage/' ) ) . '#pgm-errors-h' );" ), 'it redirects back to the section on /manage/ with a notice' );
$routes = file_get_contents( $theme . '/inc/manage/routes.php' );
foreach ( array( 'errors_dismiss', 'errors_tests', 'errors_clear', 'errors_none' ) as $k ) {
	ok( false !== strpos( $routes, "'$k'" ), "/manage/ has a notice for done=$k" );
}
$_POST = array();

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
ok( false !== strpos( $uptime, 'issues: write' ) && false !== strpos( $uptime, 'alert-issue.sh open "$TITLE"' ) && false !== strpos( $uptime, 'alert-issue.sh close "$TITLE"' ), 'uptime.yml opens and closes the alert issue' );
// #6473 for launch: production too, with an alert issue of its own, unless UPTIME_PRODUCTION is "off"
ok( false !== strpos( $uptime, 'site: [test, production]' ) && false !== strpos( $uptime, 'fail-fast: false' ), 'uptime.yml checks test and production, each on its own' );
ok( false !== strpos( $uptime, 'echo "title=Uptime check failing" >> "$GITHUB_OUTPUT"' ) && false !== strpos( $uptime, 'echo "title=Uptime check failing: prospergenics.com" >> "$GITHUB_OUTPUT"' ), 'test keeps its alert issue title; production gets its own' );
ok( false !== strpos( $uptime, '${PRODUCTION_URL:-https://prospergenics.com}' ) && false !== strpos( $uptime, '"$UPTIME_PRODUCTION" = "off"' ), 'production: https://prospergenics.com (variable PRODUCTION_URL), off with UPTIME_PRODUCTION=off' );
ok( false !== strpos( $uptime, "cron: '*/30 * * * *'" ), 'every 30 minutes' );
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

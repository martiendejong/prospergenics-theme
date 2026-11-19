<?php
/**
 * Standalone test for the database backup endpoint (cards #7232, #6473, inc/ops-backup.php).
 *
 *   - off (no route) without PROSPERGENICS_BACKUP_KEY or with a key shorter than 32 characters;
 *   - a request needs a fresh time (5-minute window), a nonce, the HMAC of this site's host, and is
 *     accepted once (replay refused; two requests in the same second are fine); a signature for another host or the other mode is refused; one dump per
 *     2 minutes; refusals are 403/429 WP_Errors;
 *   - the SQL literals: NULL, escaped strings (quotes, backslashes, NUL, newlines), hex for binary
 *     columns; identifiers with backticks; a dump of two tables in chunks, ordered by primary key;
 *   - the folder is outside the WordPress folder (a folder inside it is refused), a dump is written
 *     under a temporary name and renamed, only the newest 3 are kept, the answer has name, size and
 *     SHA-256 but never the data;
 *   - functions.php loads the module; backup-site.py signs the same message (python3, when present).
 *
 * Run with: php tests/test-2026-ops-backup.php
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
$tmp = sys_get_temp_dir() . '/pg-backup-test-' . getmypid();
@mkdir( $tmp . '/site/public_html', 0777, true );
define( 'ABSPATH', $tmp . '/site/public_html/' );
define( 'ARRAY_N', 'ARRAY_N' );
$GLOBALS['options'] = array();
$GLOBALS['routes']  = array();
$GLOBALS['actions'] = array();
class WP_Error {
	public $code, $message, $data;
	function __construct( $c = '', $m = '', $d = array() ) { $this->code = $c; $this->message = $m; $this->data = $d; }
	function get_error_message() { return $this->message; }
}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function get_option( $k, $d = false ) { return array_key_exists( $k, $GLOBALS['options'] ) ? $GLOBALS['options'][ $k ] : $d; }
function update_option( $k, $v, $autoload = null ) { $GLOBALS['options'][ $k ] = $v; return true; }
function add_action( $hook, $cb, $prio = 10 ) { $GLOBALS['actions'][ $hook ][] = $cb; }
function register_rest_route( $ns, $route, $args ) { $GLOBALS['routes'][ $ns . $route ] = $args; }
function home_url( $p = '' ) { return 'https://prospergenics.com' . $p; }
function wp_parse_url( $u, $c = -1 ) { return parse_url( $u, $c ); }
function wp_mkdir_p( $d ) { return is_dir( $d ) || mkdir( $d, 0777, true ); }
function nocache_headers() {}
function rest_ensure_response( $x ) { return $x; }
class Req {
	public $h, $p;
	function __construct( $h, $p = array() ) { $this->h = $h; $this->p = $p; }
	function get_header( $k ) { return isset( $this->h[ $k ] ) ? $this->h[ $k ] : null; }
	function get_param( $k ) { return isset( $this->p[ $k ] ) ? $this->p[ $k ] : null; }
}
// a stand-in $wpdb: two tables, prefix wp_; wp_options has 1201 rows (3 chunks), wp_blobs a binary column
class FakeDB {
	public $prefix = 'wp_', $queries = array();
	public $tables = array( 'wp_options', 'wp_blobs', 'other_site_posts' );
	function esc_like( $s ) { return addcslashes( $s, '_%\\' ); }
	function prepare( $q ) { $a = array_slice( func_get_args(), 1 ); foreach ( $a as $v ) { $q = preg_replace( '/%[sd]/', is_int( $v ) ? (string) $v : "'" . $v . "'", $q, 1 ); } return $q; }
	function get_col( $q ) { $this->queries[] = $q; return array_values( array_filter( $this->tables, function ( $t ) { return 0 === strpos( $t, 'wp_' ); } ) ); }
	function get_row( $q, $o = null ) { $this->queries[] = $q; preg_match( '/`([^`]+)`/', $q, $m ); return array( $m[1], 'CREATE TABLE `' . $m[1] . '` (`id` bigint NOT NULL, PRIMARY KEY (`id`))' ); }
	function get_results( $q, $o = null ) {
		$this->queries[] = $q;
		if ( 0 === strpos( $q, 'SHOW COLUMNS FROM `wp_options`' ) ) { return array( (object) array( 'Field' => 'option_id', 'Type' => 'bigint(20) unsigned', 'Key' => 'PRI' ), (object) array( 'Field' => 'option_value', 'Type' => 'longtext', 'Key' => '' ) ); }
		if ( 0 === strpos( $q, 'SHOW COLUMNS FROM `wp_blobs`' ) ) { return array( (object) array( 'Field' => 'id', 'Type' => 'int', 'Key' => 'PRI' ), (object) array( 'Field' => 'data', 'Type' => 'longblob', 'Key' => '' ) ); }
		if ( preg_match( '/SELECT \* FROM `wp_options` ORDER BY `option_id` LIMIT (\d+), (\d+)/', $q, $m ) ) {
			$rows = array();
			for ( $i = (int) $m[1]; $i < min( 1201, (int) $m[1] + (int) $m[2] ); $i++ ) { $rows[] = array( (string) $i, 0 === $i ? "it's a \"test\"\\\n" : ( 1 === $i ? null : 'v' . $i ) ); }
			return $rows;
		}
		if ( preg_match( '/SELECT \* FROM `wp_blobs` ORDER BY `id` LIMIT (\d+)/', $q, $m ) ) { return '0' === $m[1] ? array( array( '1', "\x00\xff'" ) ) : array(); }
		return array();
	}
	function query( $q ) { $this->queries[] = $q; return true; }
}
$GLOBALS['wpdb'] = new FakeDB();

$src = file_get_contents( $theme . '/inc/ops-backup.php' );
eval( '?>' . $src );
$key = str_repeat( 'k', 40 );
$n   = str_repeat( 'a1', 16 ); // a nonce

echo "Off without a key\n";
foreach ( $GLOBALS['actions']['rest_api_init'] as $cb ) { $cb(); }
ok( empty( $GLOBALS['routes'] ), 'no PROSPERGENICS_BACKUP_KEY: the route is not registered' );
ok( 'off' === prospergenics_2026_backup_refusal( '', 'prospergenics.com', time(), $n, str_repeat( 'a', 64 ), false, time(), array() ), 'and every request is refused' );

echo "Signatures\n";
$now = 1763500000;
$sig = prospergenics_2026_backup_signature( $key, 'prospergenics.com', $now, $n, false );
$ref = function ( $sig, $at, $seen = array(), $host = 'prospergenics.com', $time = null, $nonce = null ) use ( $key, $now, $n ) {
	return prospergenics_2026_backup_refusal( $key, $host, null === $time ? (string) $now : $time, null === $nonce ? $n : $nonce, $sig, false, $at, $seen );
};
ok( '' === $ref( $sig, $now + 10 ), 'a fresh, correctly signed request is accepted' );
ok( 'bad signature' === $ref( prospergenics_2026_backup_signature( $key, 'test.prospergenics.com', $now, $n, false ), $now ), 'a signature for the test site is refused by production' );
ok( 'bad signature' === $ref( prospergenics_2026_backup_signature( $key, 'prospergenics.com', $now, $n, true ), $now ), 'a dry-run signature does not start a real dump' );
ok( 'bad signature' === $ref( prospergenics_2026_backup_signature( strrev( $key ) . 'x', 'prospergenics.com', $now, $n, false ), $now ), 'another key is refused' );
ok( 'bad signature' === $ref( $sig, $now, array(), 'prospergenics.com', null, str_repeat( 'e', 32 ) ), 'another nonce with the same signature is refused' );
ok( false !== strpos( $ref( $sig, $now + 301 ), 'window' ), 'older than 5 minutes is refused' );
ok( false !== strpos( $ref( $sig, $now - 301 ), 'window' ), 'more than 5 minutes ahead is refused' );
ok( 'this request was used before' === $ref( $sig, $now, array( $sig => $now ) ), 'the same signature twice (a replay) is refused' );
ok( '' === $ref( prospergenics_2026_backup_signature( $key, 'prospergenics.com', $now, str_repeat( 'f', 32 ), false ), $now, array( $sig => $now ), 'prospergenics.com', null, str_repeat( 'f', 32 ) ), 'a second request in the same second, with its own nonce, is accepted' );
ok( false !== strpos( $ref( $sig, $now, array(), 'prospergenics.com', 'abc' ), 'time' ), 'a malformed time is refused' );
ok( false !== strpos( $ref( $sig, $now, array(), 'prospergenics.com', null, 'short' ), 'nonce' ), 'a missing or short nonce is refused' );
ok( false !== strpos( $ref( 'XYZ', $now ), 'signature' ), 'a malformed signature is refused' );
ok( false !== strpos( $ref( array( $sig ), $now ), 'signature' ), 'an array is not a signature' );

echo "SQL literals\n";
ok( 'NULL' === prospergenics_2026_backup_sql_value( null ), 'NULL stays NULL' );
ok( "'it\\'s \\\"q\\\" a\\\\b\\0\\n\\r\\Z'" === prospergenics_2026_backup_sql_value( "it's \"q\" a\\b\0\n\r\x1a" ), 'quotes, backslashes, NUL, newlines and ^Z are escaped' );
ok( "'100%'" === prospergenics_2026_backup_sql_value( '100%' ), 'a percent sign is kept as it is (no wpdb placeholder escape)' );
ok( '0x00ff27' === prospergenics_2026_backup_sql_value( "\x00\xff'", true ) && "''" === prospergenics_2026_backup_sql_value( '', true ), 'binary columns are hex' );
ok( '`a``b`' === prospergenics_2026_backup_ident( 'a`b' ), 'identifiers are quoted with backticks' );
ok( prospergenics_2026_backup_is_binary_type( 'longblob' ) && prospergenics_2026_backup_is_binary_type( 'varbinary(16)' ) && ! prospergenics_2026_backup_is_binary_type( 'longtext' ) && ! prospergenics_2026_backup_is_binary_type( 'varchar(191)' ), 'blob/binary columns are recognised, text is not' );

echo "Folder\n";
ok( $tmp . '/site/deploy/db-backups' === prospergenics_2026_backup_dir(), 'the default folder is <site>/deploy/db-backups, next to public_html' );
ok( ! prospergenics_2026_backup_dir_is_public( prospergenics_2026_backup_dir(), ABSPATH ), 'which is outside the WordPress folder' );
ok( prospergenics_2026_backup_dir_is_public( ABSPATH . 'wp-content/backups', ABSPATH ) && prospergenics_2026_backup_dir_is_public( ABSPATH . '../public_html/x', ABSPATH ), 'a folder inside it (also via ..) is refused' );

echo "With a key\n";
define( 'PROSPERGENICS_BACKUP_KEY', $key );
foreach ( $GLOBALS['actions']['rest_api_init'] as $cb ) { $cb(); }
$route = isset( $GLOBALS['routes']['prospergenics/v1/ops/db-backup'] ) ? $GLOBALS['routes']['prospergenics/v1/ops/db-backup'] : null;
ok( $route && 'POST' === $route['methods'] && 'prospergenics_2026_backup_permission' === $route['permission_callback'], 'POST /prospergenics/v1/ops/db-backup with the signature check as permission callback' );
$t   = time();
$req = function ( $t, $dry = false, $host = 'prospergenics.com', $nonce = null ) use ( $key ) {
	$nonce = $nonce ?: bin2hex( random_bytes( 16 ) );
	return new Req( array( 'x_pg_backup_time' => (string) $t, 'x_pg_backup_nonce' => $nonce, 'x_pg_backup_signature' => prospergenics_2026_backup_signature( $key, $host, $t, $nonce, $dry ) ), array( 'dry_run' => $dry ) );
};
$no = prospergenics_2026_backup_permission( new Req( array() ) );
ok( is_wp_error( $no ) && 403 === $no->data['status'], 'an unsigned request: 403' );
ok( true === prospergenics_2026_backup_permission( $req( $t, true ) ), 'a signed dry run passes' );
$first = $req( $t );
ok( true === prospergenics_2026_backup_permission( $first ), 'a signed request in the same second passes too' );
$again = prospergenics_2026_backup_permission( $first );
ok( is_wp_error( $again ) && 403 === $again->data['status'], 'the same request again (a replay): 403' );
$busy = prospergenics_2026_backup_permission( $req( $t - 3 ) );
ok( is_wp_error( $busy ) && 429 === $busy->data['status'], 'a second dump within 2 minutes: 429' );
ok( true === prospergenics_2026_backup_permission( $req( $t - 2, true ) ), 'a dry run is not rate limited' );
$test = prospergenics_2026_backup_permission( $req( $t - 1, false, 'test.prospergenics.com' ) );
ok( is_wp_error( $test ) && 403 === $test->data['status'], 'a request signed for test: 403' );

echo "Dump\n";
$dry = prospergenics_2026_backup_run( true );
ok( is_array( $dry ) && ! empty( $dry['dry_run'] ) && 2 === $dry['tables'] && 'deploy/db-backups' === $dry['relative_dir'], 'a dry run reports this site\'s 2 tables (not other_site_posts) and the folder' );
ok( ! glob( prospergenics_2026_backup_dir() . '/db-*' ), 'and writes no dump' );
ok( is_file( prospergenics_2026_backup_dir() . '/.htaccess' ) && false !== strpos( file_get_contents( prospergenics_2026_backup_dir() . '/.htaccess' ), 'Require all denied' ), 'the folder refuses web access even if it is ever served' );
$res = prospergenics_2026_backup_run( false );
$file = is_array( $res ) ? prospergenics_2026_backup_dir() . '/' . $res['name'] : '';
ok( is_array( $res ) && preg_match( '/^db-prospergenics\.com-\d{8}T\d{6}Z-[0-9a-f]{8}\.sql\.gz$/', $res['name'] ), 'the dump is db-<host>-<UTC time>-<random>.sql.gz' );
ok( is_array( $res ) && 1202 === $res['rows'] && 2 === $res['tables'], 'all rows of both tables (1201 + 1)' );
ok( is_file( $file ) && filesize( $file ) === $res['bytes'] && hash_file( 'sha256', $file ) === $res['sha256'], 'the answer\'s size and SHA-256 match the file' );
ok( ! array_diff( array_keys( $res ), array( 'name', 'relative_dir', 'bytes', 'sha256', 'tables', 'rows', 'seconds' ) ), 'the answer holds the file name, size, hash and counts only, never data' );
$sql = is_file( $file ) ? gzdecode( file_get_contents( $file ) ) : '';
ok( false !== strpos( $sql, "SET NAMES utf8mb4;" ) && false !== strpos( $sql, "DROP TABLE IF EXISTS `wp_options`;\nCREATE TABLE `wp_options`" ), 'header, then DROP/CREATE for each table' );
ok( 3 === substr_count( $sql, 'INSERT INTO `wp_options` VALUES' ), 'rows in chunks of 500 (1201 rows: 3 INSERTs)' );
ok( false !== strpos( $sql, "('0','it\\'s a \\\"test\\\"\\\\\\n')" ) && false !== strpos( $sql, "('1',NULL)" ), 'values escaped, NULL kept' );
ok( false !== strpos( $sql, "INSERT INTO `wp_blobs` VALUES\n('1',0x00ff27);" ), 'the binary column as hex' );
ok( false === strpos( $sql, 'other_site_posts' ), 'tables of another prefix are left out' );
$q = implode( "\n", $GLOBALS['wpdb']->queries );
ok( false !== strpos( $q, 'ORDER BY `option_id` LIMIT 500, 500' ) && false !== strpos( $q, 'START TRANSACTION WITH CONSISTENT SNAPSHOT' ), 'ordered by primary key, in one consistent snapshot' );
ok( ! glob( prospergenics_2026_backup_dir() . '/.*.partial' ), 'no temporary file is left' );
for ( $i = 0; $i < 4; $i++ ) { touch( prospergenics_2026_backup_dir() . "/db-old-$i.sql.gz", time() - 1000 * ( $i + 1 ) ); }
prospergenics_2026_backup_prune( prospergenics_2026_backup_dir(), 3 );
$left = array_map( 'basename', glob( prospergenics_2026_backup_dir() . '/db-*.sql.gz' ) );
ok( 3 === count( $left ) && in_array( $res['name'], $left, true ) && ! in_array( 'db-old-3.sql.gz', $left, true ), 'only the newest 3 dumps stay on the host' );

echo "Wiring\n";
$functions = file_get_contents( $theme . '/functions.php' );
ok( false !== strpos( $functions, "require get_template_directory() . '/inc/ops-backup.php';" ), 'functions.php loads the module' );
ok( false !== strpos( $src, "defined( 'ABSPATH' ) || exit;" ), 'the module refuses to run on its own' );
$py = trim( (string) @shell_exec( 'command -v python3 2>/dev/null' ) );
if ( $py ) {
	$code = 'import importlib.util,sys; s=importlib.util.spec_from_file_location("b", sys.argv[1]); m=importlib.util.module_from_spec(s); s.loader.exec_module(m); print(m.signature(sys.argv[2], "Prospergenics.com", 1763500000, "a1"*16, False))';
	$out  = trim( (string) shell_exec( escapeshellarg( $py ) . ' -c ' . escapeshellarg( $code ) . ' ' . escapeshellarg( $root . '/design/new-hero-2026-10/backup-site.py' ) . ' ' . escapeshellarg( $key ) . ' 2>&1' ) );
	ok( $out === prospergenics_2026_backup_signature( $key, 'prospergenics.com', 1763500000, $n, false ), 'backup-site.py computes the same signature (host case ignored)' );
}

// clean up
foreach ( array_merge( glob( prospergenics_2026_backup_dir() . '/{,.}*', GLOB_BRACE ) ?: array() ) as $f ) { if ( is_file( $f ) ) { unlink( $f ); } }
@rmdir( prospergenics_2026_backup_dir() ); @rmdir( dirname( prospergenics_2026_backup_dir() ) ); @rmdir( ABSPATH ); @rmdir( $tmp . '/site' ); @rmdir( $tmp );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

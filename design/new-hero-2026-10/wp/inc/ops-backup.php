<?php
/**
 * Database backup on request, for the production deploy and the daily backup (cards #7232, #6473,
 * docs/OPERATIONS.md "Backups").
 *
 * The deploy reaches the host over FTPS only: it cannot run mysqldump or WP-CLI there. This module
 * gives it the one thing FTPS cannot: a signed POST to
 *
 *     /wp-json/prospergenics/v1/ops/db-backup
 *
 * writes a gzipped SQL dump of this site's tables (the $wpdb prefix) to a folder OUTSIDE the web
 * root, by default <site folder>/deploy/db-backups/ next to public_html (the folder the deploy
 * already uses for its manifest), and answers with the file name, size and SHA-256 only. The deploy
 * then downloads the file over FTPS (design/new-hero-2026-10/backup-site.py), checks the hash and
 * deletes it from the host. The dump itself never travels over HTTP.
 *
 * Off unless wp-config.php defines PROSPERGENICS_BACKUP_KEY (at least 32 characters, the same value as
 * the GitHub secret of the workflow): without it the route does not exist. A request must carry
 *   X-PG-Backup-Time       the current Unix time (within 5 minutes of the server's clock),
 *   X-PG-Backup-Nonce      16-64 random hex characters, and
 *   X-PG-Backup-Signature  hex HMAC-SHA256 of "db-backup\n<site host>\n<time>\n<nonce>\n<run|dry-run>"
 *                          with the key.
 * The host is part of the signature, so a request signed for the test site is refused by production;
 * each signature is accepted once (the ones of the last 5 minutes are remembered), so a captured
 * request cannot be replayed; one dump at most every 2 minutes; the newest 3 dumps are kept on the host.
 * dry_run=1 checks the signature and the folder and reports the tables, without writing a dump.
 * PROSPERGENICS_BACKUP_DIR (an absolute path outside the WordPress folder) moves the folder.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_BACKUP_WINDOW   = 300;  // seconds a signed request stays valid
const PROSPERGENICS_BACKUP_INTERVAL = 120;  // seconds between two dumps
const PROSPERGENICS_BACKUP_KEEP     = 3;    // dumps kept on the host
const PROSPERGENICS_BACKUP_CHUNK    = 500;  // rows per SELECT
const PROSPERGENICS_BACKUP_STATE    = 'prospergenics_ops_backup_state';

/** The backup key from wp-config.php, or '' when the endpoint is off (too short counts as off). */
function prospergenics_2026_backup_key() {
    $key = defined( 'PROSPERGENICS_BACKUP_KEY' ) ? (string) PROSPERGENICS_BACKUP_KEY : '';
    return strlen( $key ) >= 32 ? $key : '';
}

/** The string a request signs (pure). */
function prospergenics_2026_backup_message( $host, $time, $nonce, $dry_run ) {
    return "db-backup\n" . strtolower( (string) $host ) . "\n" . (int) $time . "\n" . (string) $nonce . "\n" . ( $dry_run ? 'dry-run' : 'run' );
}

/** Hex HMAC-SHA256 signature of a request (pure; backup-site.py computes the same). */
function prospergenics_2026_backup_signature( $key, $host, $time, $nonce, $dry_run ) {
    return hash_hmac( 'sha256', prospergenics_2026_backup_message( $host, $time, $nonce, $dry_run ), (string) $key );
}

/**
 * Why a signed request is refused, or '' when it is valid (pure).
 *
 * @param string $key       the configured key ('' = off)
 * @param string $host      this site's host
 * @param mixed  $time      X-PG-Backup-Time
 * @param mixed  $nonce     X-PG-Backup-Nonce
 * @param mixed  $signature X-PG-Backup-Signature
 * @param bool   $dry_run
 * @param int    $now
 * @param array  $seen      signature => time of the requests accepted in the last 5 minutes (replay guard)
 */
function prospergenics_2026_backup_refusal( $key, $host, $time, $nonce, $signature, $dry_run, $now, array $seen ) {
    if ( '' === (string) $key ) {
        return 'off';
    }
    if ( ! is_scalar( $time ) || ! preg_match( '/^\d{9,11}$/', (string) $time ) ) {
        return 'missing or malformed time';
    }
    if ( ! is_string( $nonce ) || ! preg_match( '/^[0-9a-f]{16,64}$/', $nonce ) ) {
        return 'missing or malformed nonce';
    }
    if ( ! is_string( $signature ) || ! preg_match( '/^[0-9a-f]{64}$/', $signature ) ) {
        return 'missing or malformed signature';
    }
    if ( abs( (int) $now - (int) $time ) > PROSPERGENICS_BACKUP_WINDOW ) {
        return 'time outside the 5-minute window (check the clock)';
    }
    if ( ! hash_equals( prospergenics_2026_backup_signature( $key, $host, (int) $time, $nonce, $dry_run ), $signature ) ) {
        return 'bad signature';
    }
    if ( isset( $seen[ $signature ] ) ) {
        return 'this request was used before';
    }
    return '';
}

/** The folder dumps go to: PROSPERGENICS_BACKUP_DIR, else <site folder>/deploy/db-backups (next to public_html). */
function prospergenics_2026_backup_dir() {
    if ( defined( 'PROSPERGENICS_BACKUP_DIR' ) && '' !== (string) PROSPERGENICS_BACKUP_DIR ) {
        return rtrim( (string) PROSPERGENICS_BACKUP_DIR, '/' );
    }
    return dirname( rtrim( ABSPATH, '/' ) ) . '/deploy/db-backups';
}

/** Whether $dir is inside the WordPress folder (and so possibly web-served): never write there (pure). */
function prospergenics_2026_backup_dir_is_public( $dir, $abspath ) {
    $dir  = rtrim( str_replace( '\\', '/', (string) $dir ), '/' ) . '/';
    $root = rtrim( str_replace( '\\', '/', (string) $abspath ), '/' ) . '/';
    return 0 === strpos( $dir, $root ) || false !== strpos( $dir, '/../' );
}

/** One value as a MySQL literal (pure): NULL, 0x<hex> for binary columns, else a quoted, escaped string. */
function prospergenics_2026_backup_sql_value( $value, $binary = false ) {
    if ( null === $value ) {
        return 'NULL';
    }
    if ( $binary ) {
        return '' === (string) $value ? "''" : '0x' . bin2hex( (string) $value );
    }
    // the escapes of mysql_real_escape_string(); safe for utf8mb4, which has no multi-byte 0x5c
    return "'" . strtr( (string) $value, array(
        '\\'   => '\\\\',
        "\0"   => '\\0',
        "\n"   => '\\n',
        "\r"   => '\\r',
        "'"    => "\\'",
        '"'    => '\\"',
        "\x1a" => '\\Z',
    ) ) . "'";
}

/** `name` as a MySQL identifier (pure). */
function prospergenics_2026_backup_ident( $name ) {
    return '`' . str_replace( '`', '``', (string) $name ) . '`';
}

/** Whether a column type from SHOW COLUMNS holds bytes rather than text (pure). */
function prospergenics_2026_backup_is_binary_type( $type ) {
    return (bool) preg_match( '/^(tiny|medium|long)?blob\b|^(var)?binary\b|^bit\b/i', (string) $type );
}

/**
 * Write the dump of the given tables to an open gz handle; returns the number of rows.
 *
 * @param object   $db     $wpdb (or a stand-in with get_row/get_results/prepare)
 * @param string[] $tables
 * @param callable $write  function( string $sql )
 */
function prospergenics_2026_backup_write_tables( $db, array $tables, $write ) {
    $rows = 0;
    foreach ( $tables as $table ) {
        $ident  = prospergenics_2026_backup_ident( $table );
        $create = $db->get_row( 'SHOW CREATE TABLE ' . $ident, ARRAY_N );
        if ( ! is_array( $create ) || empty( $create[1] ) ) {
            throw new RuntimeException( 'SHOW CREATE TABLE failed for ' . $table );
        }
        $columns = (array) $db->get_results( 'SHOW COLUMNS FROM ' . $ident );
        $binary  = array();
        $order   = array();
        foreach ( $columns as $i => $col ) {
            $binary[ $i ] = prospergenics_2026_backup_is_binary_type( isset( $col->Type ) ? $col->Type : '' );
            if ( isset( $col->Key ) && 'PRI' === $col->Key ) {
                $order[] = prospergenics_2026_backup_ident( $col->Field );
            }
        }
        $write( "\n-- " . $table . "\nDROP TABLE IF EXISTS " . $ident . ";\n" . $create[1] . ";\n" );
        $sql_order = $order ? ' ORDER BY ' . implode( ', ', $order ) : '';
        for ( $offset = 0; ; $offset += PROSPERGENICS_BACKUP_CHUNK ) {
            $chunk = $db->get_results( $db->prepare( 'SELECT * FROM ' . $ident . $sql_order . ' LIMIT %d, %d', $offset, PROSPERGENICS_BACKUP_CHUNK ), ARRAY_N );
            if ( ! $chunk ) {
                break;
            }
            $values = array();
            foreach ( $chunk as $row ) {
                $cells = array();
                foreach ( array_values( (array) $row ) as $i => $cell ) {
                    $cells[] = prospergenics_2026_backup_sql_value( $cell, ! empty( $binary[ $i ] ) );
                }
                $values[] = '(' . implode( ',', $cells ) . ')';
            }
            $write( 'INSERT INTO ' . $ident . " VALUES\n" . implode( ",\n", $values ) . ";\n" );
            $rows += count( $chunk );
            if ( count( $chunk ) < PROSPERGENICS_BACKUP_CHUNK ) {
                break;
            }
        }
    }
    return $rows;
}

/** This site's tables: the ones with the $wpdb prefix (other sites sharing the database are left out). */
function prospergenics_2026_backup_tables( $db ) {
    $tables = (array) $db->get_col( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $db->prefix ) . '%' ) );
    sort( $tables );
    return $tables;
}

/** Remove all but the newest $keep dumps from $dir. */
function prospergenics_2026_backup_prune( $dir, $keep ) {
    $files = glob( $dir . '/db-*.sql.gz' ) ?: array();
    usort( $files, function ( $a, $b ) { return filemtime( $b ) - filemtime( $a ) ?: strcmp( $b, $a ); } );
    foreach ( array_slice( $files, $keep ) as $old ) {
        @unlink( $old ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    }
}

/**
 * Write one dump. Returns name, relative_dir (to the site folder next to public_html, for FTPS),
 * bytes, sha256, tables, rows, seconds; or a WP_Error.
 */
function prospergenics_2026_backup_run( $dry_run ) {
    global $wpdb;
    $started = microtime( true );
    $dir     = prospergenics_2026_backup_dir();
    if ( prospergenics_2026_backup_dir_is_public( $dir, ABSPATH ) ) {
        return new WP_Error( 'pg_backup_dir', 'The backup folder is inside the WordPress folder; set PROSPERGENICS_BACKUP_DIR outside it.', array( 'status' => 500 ) );
    }
    if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
        return new WP_Error( 'pg_backup_dir', 'Cannot create the backup folder.', array( 'status' => 500 ) );
    }
    @chmod( $dir, 0700 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    if ( ! is_writable( $dir ) ) {
        return new WP_Error( 'pg_backup_dir', 'The backup folder is not writable.', array( 'status' => 500 ) );
    }
    // in case the folder is ever served after all: Apache refuses everything in it
    if ( ! file_exists( $dir . '/.htaccess' ) ) {
        file_put_contents( $dir . '/.htaccess', "<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nDeny from all\n</IfModule>\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions
    }
    $site_dir = dirname( rtrim( ABSPATH, '/' ) );
    $rel_dir  = 0 === strpos( $dir . '/', $site_dir . '/' ) ? ltrim( substr( $dir, strlen( $site_dir ) ), '/' ) : null;
    $tables   = prospergenics_2026_backup_tables( $wpdb );
    if ( ! $tables ) {
        return new WP_Error( 'pg_backup_tables', 'No tables with this site\'s prefix.', array( 'status' => 500 ) );
    }
    if ( $dry_run ) {
        return array( 'dry_run' => true, 'relative_dir' => $rel_dir, 'tables' => count( $tables ), 'seconds' => round( microtime( true ) - $started, 2 ) );
    }

    $host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
    $name = sprintf( 'db-%s-%s-%s.sql.gz', preg_replace( '/[^a-z0-9.-]/', '', strtolower( $host ) ), gmdate( 'Ymd\THis\Z' ), bin2hex( random_bytes( 4 ) ) );
    $part = $dir . '/.' . $name . '.partial';
    $gz   = gzopen( $part, 'wb6' );
    if ( ! $gz ) {
        return new WP_Error( 'pg_backup_write', 'Cannot write the dump.', array( 'status' => 500 ) );
    }
    if ( function_exists( 'set_time_limit' ) ) {
        @set_time_limit( 0 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    }
    $mysql = ! ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE );
    try {
        if ( $mysql ) { // one consistent view of every InnoDB table while the dump runs
            $wpdb->query( 'START TRANSACTION WITH CONSISTENT SNAPSHOT' );
        }
        gzwrite( $gz, "-- ProsperGenics database backup of " . $host . ', ' . gmdate( 'Y-m-d H:i:s' ) . " UTC (inc/ops-backup.php)\n"
            . "-- Restore: wp db import <file>, or phpMyAdmin -> Import (docs/OPERATIONS.md)\n"
            . "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\nSET SQL_MODE='NO_AUTO_VALUE_ON_ZERO';\n" );
        $rows = prospergenics_2026_backup_write_tables( $wpdb, $tables, function ( $sql ) use ( $gz ) {
            if ( false === gzwrite( $gz, $sql ) ) {
                throw new RuntimeException( 'write failed (disk full?)' );
            }
        } );
        gzwrite( $gz, "\nSET FOREIGN_KEY_CHECKS=1;\n-- end of backup\n" );
        if ( $mysql ) {
            $wpdb->query( 'COMMIT' );
        }
    } catch ( Exception $e ) {
        gzclose( $gz );
        @unlink( $part ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        return new WP_Error( 'pg_backup_write', 'The dump failed: ' . $e->getMessage(), array( 'status' => 500 ) );
    }
    gzclose( $gz );
    // only a complete dump gets its real name
    if ( ! rename( $part, $dir . '/' . $name ) ) {
        @unlink( $part ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
        return new WP_Error( 'pg_backup_write', 'Cannot finish the dump.', array( 'status' => 500 ) );
    }
    @chmod( $dir . '/' . $name, 0600 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
    prospergenics_2026_backup_prune( $dir, PROSPERGENICS_BACKUP_KEEP );
    return array(
        'name'         => $name,
        'relative_dir' => $rel_dir,
        'bytes'        => filesize( $dir . '/' . $name ),
        'sha256'       => hash_file( 'sha256', $dir . '/' . $name ),
        'tables'       => count( $tables ),
        'rows'         => $rows,
        'seconds'      => round( microtime( true ) - $started, 2 ),
    );
}

/** REST permission callback: a valid, unused signature for this site, and not too soon after the last dump. */
function prospergenics_2026_backup_permission( $request ) {
    $dry   = (bool) $request->get_param( 'dry_run' );
    $state = (array) get_option( PROSPERGENICS_BACKUP_STATE, array() );
    $now   = time();
    // the signatures accepted in the last 5 minutes; older ones are outside the window anyway
    $seen  = array_filter( isset( $state['seen'] ) ? (array) $state['seen'] : array(), function ( $t ) use ( $now ) {
        return (int) $t >= $now - 2 * PROSPERGENICS_BACKUP_WINDOW;
    } );
    $sig   = $request->get_header( 'x_pg_backup_signature' );
    $why   = prospergenics_2026_backup_refusal(
        prospergenics_2026_backup_key(),
        (string) wp_parse_url( home_url(), PHP_URL_HOST ),
        $request->get_header( 'x_pg_backup_time' ),
        $request->get_header( 'x_pg_backup_nonce' ),
        $sig,
        $dry,
        $now,
        $seen
    );
    if ( '' !== $why ) {
        return new WP_Error( 'pg_backup_refused', 'Backup request refused: ' . $why . '.', array( 'status' => 403 ) );
    }
    if ( ! $dry && isset( $state['last_run'] ) && time() - (int) $state['last_run'] < PROSPERGENICS_BACKUP_INTERVAL ) {
        return new WP_Error( 'pg_backup_busy', 'A backup was made less than 2 minutes ago; try again shortly.', array( 'status' => 429 ) );
    }
    $seen[ $sig ]  = $now;
    $state['seen'] = array_slice( $seen, -50, null, true );
    if ( ! $dry ) {
        $state['last_run'] = $now;
    }
    update_option( PROSPERGENICS_BACKUP_STATE, $state, false );
    return true;
}

add_action( 'rest_api_init', function () {
    if ( '' === prospergenics_2026_backup_key() ) {
        return; // off: the route does not exist
    }
    register_rest_route( 'prospergenics/v1', '/ops/db-backup', array(
        'methods'             => 'POST',
        'permission_callback' => 'prospergenics_2026_backup_permission',
        'args'                => array( 'dry_run' => array( 'type' => 'boolean', 'default' => false ) ),
        'callback'            => function ( $request ) {
            nocache_headers();
            $result = prospergenics_2026_backup_run( (bool) $request->get_param( 'dry_run' ) );
            return is_wp_error( $result ) ? $result : rest_ensure_response( $result );
        },
    ) );
} );

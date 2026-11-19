<?php
/**
 * Error alerts for staff (card #6473, docs/OPERATIONS.md "Monitoring and alerts").
 *
 * WordPress' own recovery mode only emails a fatal error that happens in wp-admin or on the login
 * page, so a broken course page or profile page can go unnoticed for days. This module:
 *   - records every PHP fatal error (after the theme has loaded) and every failed wp_mail() in a
 *     short site log (option prospergenics_ops_errors, newest first, the same error counted
 *     instead of repeated within an hour);
 *   - emails a fatal error to the alert address, at most once an hour: PROSPERGENICS_ALERT_EMAIL
 *     when wp-config.php defines it, else RECOVERY_MODE_EMAIL, else the site's admin email
 *     (filter prospergenics_2026_alert_email; return '' to switch the email off). A failed
 *     email is only logged: the mail server is the thing that is broken;
 *   - shows administrators new fatal errors in wp-admin until dismissed, and the whole log on
 *     the /manage/ overview (card #8081): the same error (kind, message, file, line) once, with
 *     its total count and when it was last seen; entries from the alert test labelled "test";
 *     a table on wide screens, one stacked row per error on phones; administrators can remove
 *     one error, the test entries or the whole log (POST pg_ops_errors, nonce + manage_options).
 *
 * What is stored and mailed is the error message without its stack trace, with the site's path
 * removed and anything that looks like a password, key or token masked, plus the file, the line
 * and the request path without its query string. Never the request data, cookies or the body of
 * an email.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_OPS_LOG      = 'prospergenics_ops_errors';
const PROSPERGENICS_OPS_LOG_SIZE = 30;

/** Whether error_get_last() describes an error that stopped the request (pure). */
function prospergenics_2026_ops_is_fatal( $error ) {
    return is_array( $error ) && isset( $error['type'] )
        && in_array( (int) $error['type'], array( E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR, E_RECOVERABLE_ERROR ), true );
}

/**
 * An error message that is safe to store and to email (pure): no stack trace (its arguments can
 * hold a database password), no server path, no secret-looking values, at most 300 characters.
 */
function prospergenics_2026_ops_clean_message( $message, $abspath = '' ) {
    $message = (string) $message;
    $cut     = stripos( $message, 'Stack trace:' );
    if ( false !== $cut ) {
        $message = substr( $message, 0, $cut );
    }
    if ( '' !== (string) $abspath ) {
        $message = str_replace( rtrim( (string) $abspath, '/' ) . '/', '', $message );
    }
    // key=value, key: value, 'key' => 'value' for password / secret / token / key-like names.
    $message = preg_replace(
        '/((?:pass(?:word|wd)?|pwd|secret|token|api[_-]?key|auth[_-]?key|salt)["\']?\s*(?:=>|=|:)\s*)("[^"]*"|\'[^\']*\'|[^\s,;)&]+)/i',
        '$1***',
        $message
    );
    $message = trim( preg_replace( '/\s+/', ' ', (string) $message ) );
    return strlen( $message ) > 300 ? substr( $message, 0, 297 ) . '...' : $message;
}

/** A file path relative to the WordPress root, for the log (pure). */
function prospergenics_2026_ops_clean_file( $file, $abspath = '' ) {
    $file = (string) $file;
    $root = rtrim( (string) $abspath, '/' ) . '/';
    return ( '/' !== $root && 0 === strpos( $file, $root ) ) ? substr( $file, strlen( $root ) ) : basename( $file );
}

/**
 * Add one entry to the log (pure): the same error (kind, message, file, line) within an hour of
 * the last one only raises its count, so a broken page that is hit 500 times is one line.
 *
 * @param array $log   entries, newest first
 * @param array $entry kind, message, file, line, path
 * @return array the new log, newest first, at most PROSPERGENICS_OPS_LOG_SIZE entries
 */
function prospergenics_2026_ops_add_entry( array $log, array $entry, $now ) {
    $entry = array(
        'kind'    => (string) ( isset( $entry['kind'] ) ? $entry['kind'] : 'php' ),
        'message' => (string) ( isset( $entry['message'] ) ? $entry['message'] : '' ),
        'file'    => (string) ( isset( $entry['file'] ) ? $entry['file'] : '' ),
        'line'    => (int) ( isset( $entry['line'] ) ? $entry['line'] : 0 ),
        'path'    => (string) ( isset( $entry['path'] ) ? $entry['path'] : '' ),
        'time'    => (int) $now,
        'first'   => (int) $now,
        'count'   => 1,
    );
    foreach ( $log as $i => $old ) {
        if ( ! is_array( $old ) || ! isset( $old['kind'], $old['message'], $old['time'] ) ) {
            continue;
        }
        $same = $old['kind'] === $entry['kind'] && $old['message'] === $entry['message']
            && (string) ( isset( $old['file'] ) ? $old['file'] : '' ) === $entry['file']
            && (int) ( isset( $old['line'] ) ? $old['line'] : 0 ) === $entry['line'];
        if ( $same && (int) $now - (int) $old['time'] < HOUR_IN_SECONDS ) {
            $entry['first'] = (int) ( isset( $old['first'] ) ? $old['first'] : $old['time'] );
            $entry['count'] = (int) ( isset( $old['count'] ) ? $old['count'] : 1 ) + 1;
            unset( $log[ $i ] );
            break;
        }
    }
    array_unshift( $log, $entry );
    return array_slice( array_values( $log ), 0, PROSPERGENICS_OPS_LOG_SIZE );
}

/** The site log, newest first. */
function prospergenics_2026_ops_log() {
    $log = get_option( PROSPERGENICS_OPS_LOG, array() );
    return is_array( $log ) ? array_values( array_filter( $log, 'is_array' ) ) : array();
}

/** Where fatal-error emails go: an address, or '' for none. */
function prospergenics_2026_ops_alert_email() {
    if ( defined( 'PROSPERGENICS_ALERT_EMAIL' ) ) {
        $to = (string) PROSPERGENICS_ALERT_EMAIL;
    } elseif ( defined( 'RECOVERY_MODE_EMAIL' ) && is_email( RECOVERY_MODE_EMAIL ) ) {
        $to = (string) RECOVERY_MODE_EMAIL;
    } else {
        $to = (string) get_option( 'admin_email' );
    }
    $to = (string) apply_filters( 'prospergenics_2026_alert_email', $to );
    return is_email( $to ) ? $to : '';
}

/** The alert email for one log entry (pure): subject and plain-text body. */
function prospergenics_2026_ops_alert_mail( array $entry, $site_url, $manage_url ) {
    $host    = (string) parse_url( (string) $site_url, PHP_URL_HOST );
    $subject = sprintf( '[%s] PHP fatal error on %s', '' !== $host ? $host : 'ProsperGenics', '' !== $entry['path'] ? $entry['path'] : 'a page' );
    $body    = implode( "\n", array(
        'A page on ' . $site_url . ' stopped with a PHP fatal error.',
        '',
        'When:    ' . gmdate( 'Y-m-d H:i', (int) $entry['time'] ) . ' UTC',
        'Page:    ' . ( '' !== $entry['path'] ? $entry['path'] : '(unknown)' ),
        'Error:   ' . $entry['message'],
        'Where:   ' . $entry['file'] . ( $entry['line'] ? ':' . $entry['line'] : '' ),
        'Times:   ' . (int) $entry['count'] . ' in the last hour so far',
        '',
        'All recent errors: ' . $manage_url,
        'At most one of these emails is sent per hour; the rest are counted in the log.',
        'What to do: docs/OPERATIONS.md, "When an alert arrives".',
    ) );
    return array( 'subject' => $subject, 'body' => $body );
}

/** Record one error; a fatal error is also emailed (at most once an hour). */
function prospergenics_2026_ops_record( $kind, $message, $file = '', $line = 0 ) {
    $path  = isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( (string) $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
    $entry = array(
        'kind'    => $kind,
        'message' => prospergenics_2026_ops_clean_message( $message, ABSPATH ),
        'file'    => '' !== (string) $file ? prospergenics_2026_ops_clean_file( $file, ABSPATH ) : '',
        'line'    => (int) $line,
        'path'    => substr( sanitize_text_field( $path ), 0, 200 ),
    );
    $now = time();
    $log = prospergenics_2026_ops_add_entry( prospergenics_2026_ops_log(), $entry, $now );
    update_option( PROSPERGENICS_OPS_LOG, $log, false );

    if ( 'php' !== $kind || (int) get_option( 'prospergenics_ops_alert_sent', 0 ) > $now - HOUR_IN_SECONDS ) {
        return;
    }
    $to = prospergenics_2026_ops_alert_email();
    if ( '' === $to ) {
        return;
    }
    update_option( 'prospergenics_ops_alert_sent', $now, false ); // before sending: a mail that hangs must not repeat
    $mail = prospergenics_2026_ops_alert_mail( $log[0], home_url( '/' ), home_url( '/manage/' ) );
    wp_mail( $to, $mail['subject'], $mail['body'] );
}

/** Shutdown: a fatal error of this request goes into the log (and to the alert address). */
function prospergenics_2026_ops_shutdown() {
    $error = error_get_last();
    if ( ! prospergenics_2026_ops_is_fatal( $error ) || ( defined( 'WP_INSTALLING' ) && WP_INSTALLING ) || ! function_exists( 'update_option' ) ) {
        return;
    }
    try {
        prospergenics_2026_ops_record( 'php', $error['message'], $error['file'], $error['line'] );
    } catch ( Throwable $e ) {
        error_log( '[prospergenics] could not record a fatal error: ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
    }
}
register_shutdown_function( 'prospergenics_2026_ops_shutdown' );

// Every failed email, from the platform, the contact form or WordPress itself (password resets).
add_action( 'wp_mail_failed', function ( $wp_error ) {
    $message = $wp_error instanceof WP_Error ? $wp_error->get_error_message() : 'wp_mail() failed';
    prospergenics_2026_ops_record( 'mail', 'Email could not be sent: ' . $message );
}, 99 );

/**
 * Whether a log entry came from the alert test (pure): the "Ops-alerts test from task ..." entries
 * and the fatal of the test plugin in docs/OPERATIONS.md (pg_no_such_function, pg-fatal-test.php).
 */
function prospergenics_2026_ops_is_test( array $entry ) {
    $message = isset( $entry['message'] ) ? (string) $entry['message'] : '';
    $file    = isset( $entry['file'] ) ? (string) $entry['file'] : '';
    return false !== stripos( $message, 'Ops-alerts test from task' ) || false !== strpos( $message, 'pg_no_such_function' )
        || false !== strpos( $file, 'pg-fatal-test.php' );
}

/** What makes two log entries the same error (pure): kind, message, file and line, as 32 hex characters. */
function prospergenics_2026_ops_key( array $entry ) {
    return md5( implode( "\n", array(
        isset( $entry['kind'] ) ? (string) $entry['kind'] : '',
        isset( $entry['message'] ) ? (string) $entry['message'] : '',
        isset( $entry['file'] ) ? (string) $entry['file'] : '',
        isset( $entry['line'] ) ? (int) $entry['line'] : 0,
    ) ) );
}

/**
 * The log with the same error shown once (pure): the entries of one error (one per hour it
 * happened) become one row with the total count, the first and the last time, and the page of
 * the last time. Last seen first.
 *
 * @return array rows: key, kind, message, file, line, path, time (last seen), first, count, test
 */
function prospergenics_2026_ops_group( array $log ) {
    $rows = array();
    foreach ( $log as $e ) {
        if ( ! is_array( $e ) || ! isset( $e['kind'], $e['message'], $e['time'] ) ) {
            continue;
        }
        $key   = prospergenics_2026_ops_key( $e );
        $time  = (int) $e['time'];
        $first = (int) ( isset( $e['first'] ) ? $e['first'] : $time );
        $count = max( 1, (int) ( isset( $e['count'] ) ? $e['count'] : 1 ) );
        if ( ! isset( $rows[ $key ] ) ) {
            $rows[ $key ] = array(
                'key'     => $key,
                'kind'    => (string) $e['kind'],
                'message' => (string) $e['message'],
                'file'    => (string) ( isset( $e['file'] ) ? $e['file'] : '' ),
                'line'    => (int) ( isset( $e['line'] ) ? $e['line'] : 0 ),
                'path'    => (string) ( isset( $e['path'] ) ? $e['path'] : '' ),
                'time'    => $time,
                'first'   => $first,
                'count'   => $count,
                'test'    => prospergenics_2026_ops_is_test( $e ),
            );
            continue;
        }
        if ( $time > $rows[ $key ]['time'] ) {
            $rows[ $key ]['time'] = $time;
            $rows[ $key ]['path'] = (string) ( isset( $e['path'] ) ? $e['path'] : '' );
        }
        $rows[ $key ]['first']  = min( $rows[ $key ]['first'], $first );
        $rows[ $key ]['count'] += $count;
    }
    $rows = array_values( $rows );
    usort( $rows, function ( $a, $b ) { return $b['time'] - $a['time']; } );
    return $rows;
}

/**
 * The log without some entries (pure).
 *
 * @param string $what 'all', 'tests' (the alert-test entries) or an error key (every entry of that error)
 */
function prospergenics_2026_ops_remove( array $log, $what ) {
    if ( 'all' === $what ) {
        return array();
    }
    return array_values( array_filter( $log, function ( $e ) use ( $what ) {
        if ( ! is_array( $e ) ) {
            return false;
        }
        return 'tests' === $what ? ! prospergenics_2026_ops_is_test( $e ) : prospergenics_2026_ops_key( $e ) !== $what;
    } ) );
}

/** The nonce action of a /manage/ "Site errors" button: one per action, and per error for dismiss. */
function prospergenics_2026_ops_nonce( $do, $key = '' ) {
    return 'pg_ops_errors_' . $do . ( 'dismiss' === $do ? '_' . $key : '' );
}

/**
 * POST admin-post.php?action=pg_ops_errors (logged-in only: no nopriv handler): administrators
 * (manage_options, as the section) remove one error (do=dismiss, error=<key>), the test entries
 * (do=tests) or the whole log (do=clear). Anyone else, a missing or wrong nonce: refused.
 *
 * @return string the ?done= notice key for /manage/ (inc/manage/routes.php)
 */
function prospergenics_2026_ops_errors_handle() {
    // phpcs:disable WordPress.Security.NonceVerification -- the nonce is checked below, per action
    $do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
    $key = isset( $_POST['error'] ) ? sanitize_key( wp_unslash( $_POST['error'] ) ) : '';
    // phpcs:enable
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( 'Sorry, you are not allowed to clear site errors.', 403 );
    }
    if ( ! in_array( $do, array( 'dismiss', 'tests', 'clear' ), true ) || ( 'dismiss' === $do && ! preg_match( '/^[a-f0-9]{32}$/', $key ) ) ) {
        wp_die( 'Unknown action.', 400 );
    }
    check_admin_referer( prospergenics_2026_ops_nonce( $do, $key ) );
    $log = prospergenics_2026_ops_log();
    $new = prospergenics_2026_ops_remove( $log, 'dismiss' === $do ? $key : ( 'clear' === $do ? 'all' : 'tests' ) );
    if ( count( $new ) === count( $log ) ) {
        return 'errors_none';
    }
    update_option( PROSPERGENICS_OPS_LOG, $new, false );
    return 'errors_' . $do;
}

add_action( 'admin_post_pg_ops_errors', function () {
    $done = prospergenics_2026_ops_errors_handle();
    wp_safe_redirect( add_query_arg( 'done', $done, home_url( '/manage/' ) ) . '#pgm-errors-h' );
    exit;
} );

/** Fatal errors administrators have not dismissed yet, from the last 7 days. */
function prospergenics_2026_ops_unseen_fatals() {
    $seen = (int) get_option( 'prospergenics_ops_errors_seen', 0 );
    return array_values( array_filter( prospergenics_2026_ops_log(), function ( $e ) use ( $seen ) {
        return 'php' === $e['kind'] && (int) $e['time'] > $seen && (int) $e['time'] > time() - 7 * DAY_IN_SECONDS;
    } ) );
}

add_action( 'admin_notices', function () {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }
    $new = prospergenics_2026_ops_unseen_fatals();
    if ( ! $new ) {
        return;
    }
    $times   = array_sum( array_map( function ( $e ) { return (int) $e['count']; }, $new ) );
    $dismiss = wp_nonce_url( admin_url( 'admin-post.php?action=pg_ops_errors_seen' ), 'pg_ops_errors_seen' );
    printf(
        '<div class="notice notice-error"><p><strong>%s</strong> %s</p><p><a href="%s">%s</a> · <a href="%s">%s</a></p></div>',
        esc_html( sprintf( 'PHP fatal errors on the site: %d in the last 7 days.', $times ) ),
        esc_html( sprintf( 'Last one %s ago on %s: %s', human_time_diff( (int) $new[0]['time'] ), '' !== $new[0]['path'] ? $new[0]['path'] : 'a page', $new[0]['message'] ) ),
        esc_url( home_url( '/manage/?errors=all#pgm-errors-h' ) ),
        esc_html__( 'See all site errors', 'prospergenics' ),
        esc_url( $dismiss ),
        esc_html__( 'Dismiss', 'prospergenics' )
    );
} );

add_action( 'admin_post_pg_ops_errors_seen', function () {
    if ( ! current_user_can( 'manage_options' ) || ! check_admin_referer( 'pg_ops_errors_seen' ) ) {
        wp_die( 'Not allowed.', 403 );
    }
    update_option( 'prospergenics_ops_errors_seen', time(), false );
    wp_safe_redirect( wp_get_referer() ? wp_get_referer() : admin_url() );
    exit;
} );

/** A button of the /manage/ "Site errors" section: a POST form with its own nonce. */
function prospergenics_2026_ops_button( $do, $label, $class, $key = '', $sr = '' ) {
    return '<form class="pgm-inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
        . '<input type="hidden" name="action" value="pg_ops_errors" /><input type="hidden" name="do" value="' . esc_attr( $do ) . '" />'
        . ( '' !== $key ? '<input type="hidden" name="error" value="' . esc_attr( $key ) . '" />' : '' )
        . wp_nonce_field( prospergenics_2026_ops_nonce( $do, $key ), '_wpnonce', true, false )
        . '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label )
        . ( '' !== $sr ? '<span class="screen-reader-text"> ' . esc_html( $sr ) . '</span>' : '' ) . '</button></form>';
}

/**
 * The "Site errors" section of the /manage/ overview, for administrators ('' for anyone else):
 * the last 10 errors (?errors=all: all of them), each once with its count. A table that becomes
 * one stacked row per error on phones (manage.css, .pgm-etable).
 */
function prospergenics_2026_ops_manage_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }
    $rows  = prospergenics_2026_ops_group( prospergenics_2026_ops_log() );
    $total = count( $rows );
    // phpcs:ignore WordPress.Security.NonceVerification -- display only
    $all   = isset( $_GET['errors'] ) && 'all' === sanitize_key( wp_unslash( $_GET['errors'] ) );
    $shown = $all ? $rows : array_slice( $rows, 0, 10 );
    $out   = '<section class="pgm-section" id="pgm-errors" aria-labelledby="pgm-errors-h"><h2 class="pgm-h2" id="pgm-errors-h">Site errors</h2>';
    if ( ! $rows ) {
        return $out . '<p class="pgm-muted">No PHP fatal errors or failed emails recorded.</p></section>';
    }
    $out .= '<div class="pgm-table-wrap"><table class="pgm-table pgm-etable"><caption class="screen-reader-text">The last PHP fatal errors and failed emails, each error once</caption>'
        . '<thead><tr><th scope="col">Last seen</th><th scope="col">What</th><th scope="col">Page</th><th scope="col" class="pgm-num">Times</th><th scope="col"><span class="screen-reader-text">Remove</span></th></tr></thead><tbody>';
    $tests = 0;
    foreach ( $rows as $e ) {
        $tests += $e['test'] ? 1 : 0;
    }
    foreach ( $shown as $e ) {
        $file  = $e['file'] . ( $e['line'] ? ':' . $e['line'] : '' );
        $what  = $e['message'];
        if ( '' !== $e['file'] && ' in ' . $file === substr( $what, -strlen( ' in ' . $file ) ) ) {
            $what = substr( $what, 0, -strlen( ' in ' . $file ) ); // PHP's "... in file:line": file:line has its own line
        }
        $what  = ( 'php' === $e['kind'] ? 'PHP fatal error: ' : '' ) . $what;
        $where = '' !== $e['file'] ? '<span class="pgm-e-file">' . esc_html( $file ) . '</span>' : '';
        $last  = human_time_diff( $e['time'] );
        $first = $e['count'] > 1 && human_time_diff( $e['first'] ) !== $last
            ? '<span class="pgm-muted pgm-block">' . esc_html( sprintf( 'first %s ago', human_time_diff( $e['first'] ) ) ) . '</span>' : '';
        $out  .= '<tr class="pgm-erow' . ( $e['test'] ? ' pgm-erow-test' : '' ) . '" id="pgm-error-' . esc_attr( $e['key'] ) . '">'
            . '<td class="pgm-e-when"><time datetime="' . esc_attr( gmdate( 'c', $e['time'] ) ) . '" title="' . esc_attr( wp_date( 'j M Y, H:i', $e['time'] ) ) . '">'
            . esc_html( sprintf( '%s ago', $last ) ) . '</time>' . $first . '</td>'
            . '<td class="pgm-e-what">' . ( $e['test'] ? '<span class="pgm-badge pgm-badge-warn pgm-e-test" title="From the alert test (docs/OPERATIONS.md)">test</span> ' : '' )
            . '<span class="pgm-e-msg">' . esc_html( $what ) . '</span>' . $where . '</td>'
            . '<td class="pgm-e-page' . ( '' === $e['path'] ? ' pgm-e-nopage' : '' ) . '"><span class="pgm-e-label">Page </span>' . esc_html( '' !== $e['path'] ? $e['path'] : '-' ) . '</td>'
            . '<td class="pgm-num pgm-e-times">' . esc_html( number_format_i18n( $e['count'] ) ) . '<span class="pgm-e-label">' . ( 1 === $e['count'] ? ' time' : ' times' ) . '</span></td>'
            . '<td class="pgm-e-act">' . prospergenics_2026_ops_button( 'dismiss', 'Dismiss', 'pgm-btn pgm-btn-ghost pgm-e-btn', $e['key'], mb_substr( $what, 0, 80 ) ) . '</td></tr>';
    }
    $out .= '</tbody></table></div><div class="pgm-inline pgm-e-tools">';
    if ( count( $shown ) < $total ) {
        $out .= '<a class="pgm-e-more" href="' . esc_url( home_url( '/manage/?errors=all#pgm-errors-h' ) ) . '">' . esc_html( sprintf( 'Show all %d errors', $total ) ) . '</a>';
    }
    if ( $tests ) {
        $out .= prospergenics_2026_ops_button( 'tests', sprintf( 'Remove the %d test %s', $tests, 1 === $tests ? 'entry' : 'entries' ), 'pgm-btn pgm-btn-ghost pgm-e-btn' );
    }
    $out .= '<details class="pgm-suspend pgm-e-clear"><summary class="pgm-btn pgm-btn-ghost pgm-e-btn">Clear all…</summary>'
        . '<p class="pgm-muted">' . esc_html( sprintf( 'This removes all %d %s from the log. New errors are still recorded and emailed.', $total, 1 === $total ? 'error' : 'errors' ) ) . '</p>'
        . prospergenics_2026_ops_button( 'clear', 'Clear the error log', 'pgm-btn pgm-btn-danger pgm-e-btn' ) . '</details></div>';
    $to = prospergenics_2026_ops_alert_email();
    return $out . '<p class="pgm-muted">'
        . esc_html( '' !== $to ? sprintf( 'Fatal errors are emailed to %s (at most once an hour).', $to ) : 'Fatal errors are not emailed: no alert address is set.' )
        . '</p></section>';
}

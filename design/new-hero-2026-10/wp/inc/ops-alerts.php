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
 *     the /manage/ overview.
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
        esc_url( home_url( '/manage/' ) ),
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

/** The "Site errors" section of the /manage/ overview, for administrators ('' for anyone else). */
function prospergenics_2026_ops_manage_html() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return '';
    }
    $log = array_slice( prospergenics_2026_ops_log(), 0, 10 );
    $out = '<section class="pgm-section" aria-labelledby="pgm-errors-h"><h2 class="pgm-h2" id="pgm-errors-h">Site errors</h2>';
    if ( ! $log ) {
        return $out . '<p class="pgm-muted">No PHP fatal errors or failed emails recorded.</p></section>';
    }
    $out .= '<div class="pgm-table-wrap"><table class="pgm-table"><caption class="screen-reader-text">The last PHP fatal errors and failed emails</caption>'
        . '<thead><tr><th scope="col">When</th><th scope="col">What</th><th scope="col">Page</th><th scope="col" class="pgm-num">Times</th></tr></thead><tbody>';
    foreach ( $log as $e ) {
        $where = '' !== $e['file'] ? ' <span class="pgm-muted pgm-block">' . esc_html( $e['file'] . ( $e['line'] ? ':' . $e['line'] : '' ) ) . '</span>' : '';
        $out  .= '<tr><td>' . esc_html( sprintf( '%s ago', human_time_diff( (int) $e['time'] ) ) ) . '</td>'
            . '<td>' . esc_html( ( 'php' === $e['kind'] ? 'PHP fatal error: ' : '' ) . $e['message'] ) . $where . '</td>'
            . '<td>' . esc_html( '' !== $e['path'] ? $e['path'] : '-' ) . '</td>'
            . '<td class="pgm-num">' . esc_html( number_format_i18n( (int) $e['count'] ) ) . '</td></tr>';
    }
    $to = prospergenics_2026_ops_alert_email();
    return $out . '</tbody></table></div><p class="pgm-muted">'
        . esc_html( '' !== $to ? sprintf( 'Fatal errors are emailed to %s (at most once an hour).', $to ) : 'Fatal errors are not emailed: no alert address is set.' )
        . '</p></section>';
}

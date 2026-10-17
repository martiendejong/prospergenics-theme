<?php
/**
 * Spam protection shared by the contact form and the comment form.
 *
 * Deliberately dependency-free: the site runs no Akismet and no CAPTCHA today, and the
 * forms had nothing but a nonce -- which stops nothing, since a bot can fetch the page
 * and read the nonce like any browser. These checks are cheap, privacy-preserving (no
 * third-party calls, no cookies) and catch the overwhelming majority of automated spam.
 *
 * If Akismet IS later activated, prospergenics_akismet_thinks_spam() picks it up
 * automatically as one more signal; nothing here needs changing.
 *
 * Design rule: a positive result marks the submission as spam. It never discards it.
 * Everything is stored either way -- a false positive must stay recoverable.
 *
 * @package Prospergenics
 * @since 1.0.2
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/** Bots fill every field they find, including one that is hidden from humans. */
const PROSPERGENICS_HONEYPOT_FIELD = 'pg_website_url';

/** Minimum seconds a human plausibly needs to fill a form. Bots post instantly. */
const PROSPERGENICS_MIN_FILL_SECONDS = 4;

/** A form token older than this is stale (a recycled/replayed page). */
const PROSPERGENICS_MAX_FORM_AGE = DAY_IN_SECONDS;

/**
 * Hidden fields to drop into any protected form.
 *
 * The timestamp is paired with a hash of itself so it cannot simply be rewritten to
 * "now" by a bot replaying the form -- wp_hash() is keyed by the site's own salts.
 */
function prospergenics_spam_protection_fields() {
    $time = time();
    ob_start();
    ?>
    <p class="pg-hp-field" aria-hidden="true" style="position:absolute;left:-9999px;top:-9999px;height:0;overflow:hidden;">
        <label><?php esc_html_e( 'Leave this field empty', 'prospergenics' ); ?>
            <input type="text" name="<?php echo esc_attr( PROSPERGENICS_HONEYPOT_FIELD ); ?>" value="" tabindex="-1" autocomplete="off">
        </label>
    </p>
    <input type="hidden" name="pg_form_time" value="<?php echo esc_attr( $time ); ?>">
    <input type="hidden" name="pg_form_token" value="<?php echo esc_attr( wp_hash( (string) $time ) ); ?>">
    <?php
    return ob_get_clean();
}

/**
 * Evaluate a submission.
 *
 * @param string $message Free-text body, checked for link stuffing.
 * @param array  $post    Raw $_POST.
 * @return array Human-readable reasons; empty means it looked legitimate.
 */
function prospergenics_spam_reasons( $message, $post ) {
    $reasons = array();

    // 1. Honeypot: invisible to humans, irresistible to form-filling bots.
    if ( ! empty( $post[ PROSPERGENICS_HONEYPOT_FIELD ] ) ) {
        $reasons[] = 'honeypot filled';
    }

    // 2. Timing. A missing/forged token is itself a signal: real submissions come from
    //    a page this site rendered, which always carries both fields.
    $time  = isset( $post['pg_form_time'] ) ? (int) $post['pg_form_time'] : 0;
    $token = isset( $post['pg_form_token'] ) ? (string) $post['pg_form_token'] : '';

    if ( ! $time || ! $token || ! hash_equals( wp_hash( (string) $time ), $token ) ) {
        $reasons[] = 'missing or invalid form token';
    } else {
        $elapsed = time() - $time;
        if ( $elapsed < PROSPERGENICS_MIN_FILL_SECONDS ) {
            $reasons[] = sprintf( 'submitted in %ds', max( 0, $elapsed ) );
        } elseif ( $elapsed > PROSPERGENICS_MAX_FORM_AGE ) {
            $reasons[] = 'stale form';
        }
    }

    // 3. Link stuffing -- the signature of SEO comment/contact spam.
    $link_count = preg_match_all( '#https?://#i', $message );
    if ( $link_count > 3 ) {
        $reasons[] = sprintf( '%d links in message', $link_count );
    }
    if ( preg_match( '#\[url[=\]]|\[/url\]|<a\s+href#i', $message ) ) {
        $reasons[] = 'markup links in message';
    }

    // 4. Rate limit per IP. Legitimate people do not file six enquiries in an hour.
    $ip = prospergenics_client_ip();
    if ( '' !== $ip ) {
        $key   = 'pg_rate_' . md5( $ip );
        $count = (int) get_transient( $key );
        if ( $count >= 5 ) {
            $reasons[] = 'rate limit exceeded for this IP';
        }
        set_transient( $key, $count + 1, HOUR_IN_SECONDS );
    }

    return apply_filters( 'prospergenics_spam_reasons', $reasons, $message, $post );
}

/**
 * Best-effort client IP.
 *
 * Cloudflare fronts this site, so REMOTE_ADDR is Cloudflare's edge; CF-Connecting-IP
 * carries the real visitor. Only that header is trusted -- X-Forwarded-For is
 * caller-settable and would make the rate limit trivially bypassable.
 */
function prospergenics_client_ip() {
    $ip = '';
    if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
        $ip = wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] );
    } elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
        $ip = wp_unslash( $_SERVER['REMOTE_ADDR'] );
    }
    $ip = filter_var( $ip, FILTER_VALIDATE_IP );
    return $ip ? $ip : '';
}

/**
 * Ask Akismet, but only if it is actually installed and keyed.
 *
 * @return bool True only on a confident positive; false when unavailable.
 */
function prospergenics_akismet_thinks_spam( $data ) {
    if ( ! class_exists( 'Akismet' ) || ! method_exists( 'Akismet', 'get_api_key' ) || ! Akismet::get_api_key() ) {
        return false;
    }

    $query = array(
        'blog'                 => get_option( 'home' ),
        'user_ip'              => prospergenics_client_ip(),
        'user_agent'           => isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '',
        'comment_type'         => 'contact-form',
        'comment_author'       => $data['name'],
        'comment_author_email' => $data['email'],
        'comment_content'      => $data['message'],
    );

    $response = Akismet::http_post( build_query( $query ), 'comment-check' );
    return isset( $response[1] ) && 'true' === trim( $response[1] );
}

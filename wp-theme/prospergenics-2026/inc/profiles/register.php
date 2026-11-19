<?php
/**
 * Profiles track: member registration at /register/ (card #6338).
 *
 * A visitor gives a name, an email address and a password. We validate (unique email, password
 * strength, spam checks), create the IAM account when IAM provisioning is configured, create the
 * WordPress member, log them in and send them to the onboarding wizard (/profile/welcome/).
 *
 * Everything else a new account needs already hangs on WordPress' own `user_register` (verify.php):
 * the magic-link email (#6339), the onboarding state (#6340) and the profile slug (data.php). This
 * file adds no second path for those.
 *
 * - On/off: the core setting Settings, General, Membership, "Anyone can register" (option
 *   users_can_register). Off (the WordPress default) means /register/ says registration is closed and
 *   nobody can be created. The Join link in the header points here only while it is on.
 * - Role: "member" is the WordPress role subscriber (the role every platform rule treats as a plain
 *   member). Filter prospergenics_register_role can change it, but never to a role that can edit,
 *   publish or manage anything: those fall back to subscriber.
 * - IAM: see prospergenics_register_iam_provision() and docs/REGISTRATION.md. IAM comes first; when it
 *   fails nothing is created in WordPress, so there are no half-registered members. When IAM
 *   provisioning is not configured the member is created in WordPress only and flagged
 *   _pg_register_iam = pending, so the accounts that still need an IAM account can be listed.
 * - Passwords are used as typed: never through a text sanitiser, never logged, never stored by us
 *   except as WordPress' own hash.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_REGISTER_PASSWORD_MIN   = 10;
const PROSPERGENICS_REGISTER_PASSWORD_MAX   = 128;
const PROSPERGENICS_REGISTER_NAME_MAX       = 80;
const PROSPERGENICS_REGISTER_EMAIL_MAX      = 100;
const PROSPERGENICS_REGISTER_RATE_LIMIT     = 30; // a class registering from one shared connection must fit
const PROSPERGENICS_REGISTER_RATE_WINDOW    = HOUR_IN_SECONDS;
const PROSPERGENICS_REGISTER_MIN_FILL       = 2;
const PROSPERGENICS_REGISTER_IAM_TIMEOUT    = 10;
const PROSPERGENICS_REGISTER_SUB_META       = 'jengo_iam_sub'; // the key the jengo-iam-sso plugin matches on

/* ───────────────────────── pure rules ───────────────────────── */

/** Whether registration is switched on (core setting "Anyone can register"). */
function prospergenics_register_is_open() {
    return (bool) apply_filters( 'prospergenics_register_open', (bool) get_option( 'users_can_register' ) );
}

/** A display name as typed: no markup, single spaces, at most the maximum length. */
function prospergenics_register_clean_name( $name ) {
    $name = wp_strip_all_tags( (string) $name );
    $name = preg_replace( '/[\x00-\x1F\x7F]+/u', ' ', $name );
    $name = trim( preg_replace( '/\s+/u', ' ', (string) $name ) );
    return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, PROSPERGENICS_REGISTER_NAME_MAX ) : substr( $name, 0, PROSPERGENICS_REGISTER_NAME_MAX );
}

/** @return array{0:string,1:string} first name, last name ("" when there is only one word) */
function prospergenics_register_split_name( $name ) {
    $parts = explode( ' ', trim( (string) $name ), 2 );
    return array( $parts[0], isset( $parts[1] ) ? trim( $parts[1] ) : '' );
}

/** The passwords everybody tries first (lower case). A short list on purpose: length is the real rule. */
function prospergenics_register_weak_passwords() {
    return array(
        'password', 'password1', 'password12', 'password123', 'passw0rd', 'p@ssw0rd', 'qwertyuiop', 'qwerty12345',
        '1234567890', '12345678910', '0123456789', '111111111', 'iloveyou12', 'welcome123', 'letmein123', 'admin12345',
        'abcdefghij', 'changeme123', 'prospergenics', 'prospergenics1', 'prospergenics123',
    );
}

/**
 * Why a password is too weak, or '' when it is fine. Length first (a few unrelated words are
 * strong), then the things that are guessed in seconds: the most common passwords, one repeated
 * character, a run like 1234567890, and a password made of the member's own name or email.
 */
function prospergenics_register_password_problem( $password, $email = '', $name = '' ) {
    $password = (string) $password;
    $len      = function_exists( 'mb_strlen' ) ? mb_strlen( $password ) : strlen( $password );
    if ( $len < PROSPERGENICS_REGISTER_PASSWORD_MIN ) {
        return sprintf( 'Use at least %d characters. A few unrelated words make a strong password.', PROSPERGENICS_REGISTER_PASSWORD_MIN );
    }
    if ( $len > PROSPERGENICS_REGISTER_PASSWORD_MAX ) {
        return sprintf( 'Use at most %d characters.', PROSPERGENICS_REGISTER_PASSWORD_MAX );
    }
    $lower = strtolower( $password );
    if ( in_array( $lower, prospergenics_register_weak_passwords(), true ) ) {
        return 'That password is one of the most common ones. Choose something only you would think of.';
    }
    if ( count( array_unique( preg_split( '//u', $lower, -1, PREG_SPLIT_NO_EMPTY ) ) ) < 4 ) {
        return 'Use more different characters: this password is too repetitive.';
    }
    if ( false !== strpos( 'abcdefghijklmnopqrstuvwxyz', $lower ) || false !== strpos( '0123456789012345678901', $lower ) || false !== strpos( 'qwertyuiopasdfghjklzxcvbnm', $lower ) ) {
        return 'That password is a simple run of keys. Choose something only you would think of.';
    }
    $own = array();
    $local = strstr( strtolower( (string) $email ), '@', true );
    $local = is_string( $local ) ? preg_replace( '/[^a-z0-9]+/', '', $local ) : '';
    if ( is_string( $local ) && strlen( $local ) >= 4 ) {
        $own[] = $local;
    }
    $squashed = preg_replace( '/[^a-z0-9]+/', '', strtolower( (string) $name ) );
    if ( is_string( $squashed ) && strlen( $squashed ) >= 4 ) {
        $own[] = $squashed;
    }
    foreach ( $own as $word ) {
        if ( false !== strpos( preg_replace( '/[^a-z0-9]+/', '', $lower ), $word ) && strlen( $word ) * 2 >= $len - 2 ) {
            return 'Do not build your password from your name or email address.';
        }
    }
    return '';
}

/**
 * Validate the submitted fields.
 *
 * @param array $in          name, email, password (as typed).
 * @param bool  $email_taken Whether an account with this email already exists (the caller asks WordPress).
 * @return array{clean:array{name:string,email:string,first:string,last:string},errors:array<string,string>}
 */
function prospergenics_register_validate( array $in, $email_taken = false ) {
    $errors = array();
    $name   = prospergenics_register_clean_name( isset( $in['name'] ) ? $in['name'] : '' );
    $email  = strtolower( trim( isset( $in['email'] ) && is_string( $in['email'] ) ? $in['email'] : '' ) );
    $pass   = isset( $in['password'] ) && is_string( $in['password'] ) ? $in['password'] : '';

    if ( '' === $name ) {
        $errors['name'] = 'Please tell us your name.';
    }
    if ( '' === $email || strlen( $email ) > PROSPERGENICS_REGISTER_EMAIL_MAX || ! filter_var( $email, FILTER_VALIDATE_EMAIL ) ) {
        $errors['email'] = 'Enter a valid email address.';
    } elseif ( $email_taken ) {
        $errors['email'] = 'There is already an account with this email address. Log in, or reset your password.';
    }
    $problem = prospergenics_register_password_problem( $pass, $email, $name );
    if ( '' !== $problem ) {
        $errors['password'] = $problem;
    }
    list( $first, $last ) = prospergenics_register_split_name( $name );
    return array(
        'clean'  => array( 'name' => $name, 'email' => $email, 'first' => $first, 'last' => $last ),
        'errors' => $errors,
    );
}

/** A login name that does not reveal the email address: the name's slug plus a random suffix. */
function prospergenics_register_login_base( $name, $suffix ) {
    $slug = prospergenics_profiles_slugify( $name );
    return ( '' === $slug ? 'member' : substr( $slug, 0, 40 ) ) . '-' . $suffix;
}

/** Why the form timing or honeypot says "bot", or ''. Pure: the caller passes the clock and the expected token. */
function prospergenics_register_bot_reason( array $post, $now, $token_ok ) {
    if ( ! empty( $post[ PROSPERGENICS_HONEYPOT_FIELD ] ) ) {
        return 'honeypot';
    }
    $time = isset( $post['pg_form_time'] ) ? (int) $post['pg_form_time'] : 0;
    if ( ! $time || ! $token_ok ) {
        return 'token';
    }
    if ( $now - $time < PROSPERGENICS_REGISTER_MIN_FILL ) {
        return 'too-fast';
    }
    if ( $now - $time > PROSPERGENICS_MAX_FORM_AGE ) {
        return 'stale';
    }
    return '';
}

/** The IAM provisioning endpoint is only ever called over https (plain http only for localhost, so tests and a dev IAM work). */
function prospergenics_register_iam_url_ok( $url ) {
    $parts = wp_parse_url( (string) $url );
    if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
        return false;
    }
    if ( 'https' === strtolower( $parts['scheme'] ) ) {
        return true;
    }
    return 'http' === strtolower( $parts['scheme'] ) && in_array( strtolower( $parts['host'] ), array( 'localhost', '127.0.0.1', '[::1]' ), true );
}

/**
 * Read IAM's answer. Pure: status code and raw body in, a plain result out.
 *
 * @return array{result:string,subject:string} result is created, exists, unavailable or rejected
 */
function prospergenics_register_iam_parse( $code, $body ) {
    $code = (int) $code;
    $data = json_decode( (string) $body, true );
    $data = is_array( $data ) ? $data : array();
    if ( 200 === $code || 201 === $code ) {
        $subject = '';
        foreach ( array( 'subject', 'sub', 'userId', 'id' ) as $key ) {
            if ( isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) && '' !== (string) $data[ $key ] ) {
                $subject = (string) $data[ $key ];
                break;
            }
        }
        // an answer without the account's id is not a confirmed account: we could not link it
        return array( 'result' => '' === $subject ? 'unavailable' : 'created', 'subject' => $subject );
    }
    if ( 409 === $code ) {
        return array( 'result' => 'exists', 'subject' => '' );
    }
    if ( 400 === $code || 422 === $code ) {
        return array( 'result' => 'rejected', 'subject' => '' );
    }
    return array( 'result' => 'unavailable', 'subject' => '' ); // 401/403 (our credential), 429, 5xx, anything else
}

/* ───────────────────────── IAM ───────────────────────── */

/**
 * Create the member's account in Jengo IAM.
 *
 * Configured in wp-config.php (never in the repository):
 *   define( 'PROSPERGENICS_IAM_PROVISION_URL',   'https://<iam host>/<provisioning endpoint>' );
 *   define( 'PROSPERGENICS_IAM_PROVISION_TOKEN', '<service credential, send as a Bearer token>' );
 * Without both constants IAM provisioning is off. The request and the answers it understands are the
 * contract in docs/REGISTRATION.md; filter prospergenics_register_iam_provisioner replaces the whole call.
 *
 * @return array{status:string,subject:string}|WP_Error status is "disabled" (not configured) or "created".
 */
function prospergenics_register_iam_provision( $email, $password, $first, $last ) {
    $custom = apply_filters( 'prospergenics_register_iam_provisioner', null, $email, $first, $last );
    if ( null !== $custom ) {
        return $custom;
    }
    if ( ! defined( 'PROSPERGENICS_IAM_PROVISION_URL' ) || ! defined( 'PROSPERGENICS_IAM_PROVISION_TOKEN' ) || '' === (string) PROSPERGENICS_IAM_PROVISION_URL || '' === (string) PROSPERGENICS_IAM_PROVISION_TOKEN ) {
        return array( 'status' => 'disabled', 'subject' => '' );
    }
    if ( ! prospergenics_register_iam_url_ok( PROSPERGENICS_IAM_PROVISION_URL ) ) {
        error_log( '[prospergenics] IAM provisioning URL is not https: registration refused.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        return new WP_Error( 'pg_register_iam_unavailable', 'We could not create your account just now. Please try again in a few minutes.', array( 'status' => 503 ) );
    }
    $response = wp_remote_post( PROSPERGENICS_IAM_PROVISION_URL, array(
        'timeout'     => PROSPERGENICS_REGISTER_IAM_TIMEOUT,
        'redirection' => 0,
        'sslverify'   => true,
        'headers'     => array(
            'Authorization' => 'Bearer ' . PROSPERGENICS_IAM_PROVISION_TOKEN,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ),
        'body'        => wp_json_encode( array(
            'email'     => $email,
            'password'  => $password,
            'firstName' => $first,
            'lastName'  => $last,
            'source'    => wp_parse_url( home_url( '/' ), PHP_URL_HOST ),
        ) ),
    ) );
    if ( is_wp_error( $response ) ) {
        // the message of a transport error names the host, never the body: safe to log, still without the password
        error_log( '[prospergenics] IAM provisioning request failed: ' . $response->get_error_message() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
        return new WP_Error( 'pg_register_iam_unavailable', 'We could not create your account just now. Please try again in a few minutes.', array( 'status' => 503 ) );
    }
    $parsed = prospergenics_register_iam_parse( wp_remote_retrieve_response_code( $response ), wp_remote_retrieve_body( $response ) );
    switch ( $parsed['result'] ) {
        case 'created':
            return array( 'status' => 'created', 'subject' => $parsed['subject'] );
        case 'exists':
            return new WP_Error( 'pg_register_iam_exists', 'There is already an account with this email address. Log in, or reset your password.', array( 'field' => 'email', 'status' => 409 ) );
        case 'rejected':
            return new WP_Error( 'pg_register_iam_rejected', 'Your details were not accepted. Please check your name, email address and password.', array( 'status' => 400 ) );
    }
    error_log( sprintf( '[prospergenics] IAM provisioning answered HTTP %d', (int) wp_remote_retrieve_response_code( $response ) ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
    return new WP_Error( 'pg_register_iam_unavailable', 'We could not create your account just now. Please try again in a few minutes.', array( 'status' => 503 ) );
}

/* ───────────────────────── WordPress ───────────────────────── */

/** The role a new member gets: subscriber, or the filtered role when it is a plain, unprivileged one. */
function prospergenics_register_role() {
    $role = sanitize_key( (string) apply_filters( 'prospergenics_register_role', 'subscriber' ) );
    $obj  = '' !== $role ? get_role( $role ) : null;
    if ( ! $obj ) {
        return 'subscriber';
    }
    foreach ( array( 'edit_posts', 'upload_files', 'publish_posts', 'manage_options', 'promote_users', 'edit_users', 'list_users' ) as $privileged ) {
        if ( $obj->has_cap( $privileged ) ) {
            return 'subscriber';
        }
    }
    return $role;
}

/** The client IP the attempt counts against (CF-Connecting-IP behind Cloudflare, see spam-protection.php). */
function prospergenics_register_rate_key() {
    $ip = function_exists( 'prospergenics_client_ip' ) ? prospergenics_client_ip() : '';
    return '' === $ip ? '' : 'pg_reg_rate_' . md5( $ip );
}

/**
 * Create the member in WordPress, after IAM. `user_register` then sends the confirmation link,
 * starts the onboarding wizard and gives the profile its slug.
 *
 * @param array       $clean name, email, first, last (from prospergenics_register_validate()).
 * @param array|null  $iam   The provisioning result, or null.
 * @return int|WP_Error The new user's ID.
 */
function prospergenics_register_create_member( array $clean, $password, $iam ) {
    $login = '';
    for ( $try = 0; $try < 5; $try++ ) {
        $candidate = prospergenics_register_login_base( $clean['name'], strtolower( wp_generate_password( 5, false, false ) ) );
        if ( ! username_exists( $candidate ) ) {
            $login = $candidate;
            break;
        }
    }
    if ( '' === $login ) {
        return new WP_Error( 'pg_register_login', 'We could not create your account just now. Please try again.', array( 'status' => 500 ) );
    }
    $id = wp_insert_user( array(
        'user_login'   => $login,
        'user_pass'    => $password,
        'user_email'   => $clean['email'],
        'role'         => prospergenics_register_role(),
        'first_name'   => $clean['first'],
        'last_name'    => $clean['last'],
        'display_name' => $clean['name'],
        'nickname'     => $clean['first'],
    ) );
    if ( is_wp_error( $id ) ) {
        return new WP_Error( 'pg_register_insert', 'We could not create your account just now. Please try again.', array( 'status' => 500, 'cause' => $id->get_error_code() ) );
    }
    update_user_meta( $id, '_pg_register_source', 'register' );
    if ( is_array( $iam ) && 'created' === $iam['status'] && '' !== $iam['subject'] ) {
        update_user_meta( $id, PROSPERGENICS_REGISTER_SUB_META, $iam['subject'] );
        update_user_meta( $id, '_pg_register_iam', 'created' );
    } else {
        update_user_meta( $id, '_pg_register_iam', 'pending' );
    }
    return (int) $id;
}

/**
 * Handle a submitted registration form.
 *
 * @param array $post Raw $_POST (still slashed).
 * @return int|WP_Error The new, logged-in user's ID, or an error whose data holds the field errors
 *                      (`fields`) and the values to keep in the form (`keep`).
 */
function prospergenics_register_process( array $post ) {
    $post = wp_unslash( $post );
    $fail = function ( $message, array $fields = array(), array $keep = array() ) {
        return new WP_Error( 'pg_register_failed', $message, array( 'fields' => $fields, 'keep' => $keep ) );
    };

    if ( ! prospergenics_register_is_open() ) {
        return $fail( 'Registration is closed at the moment. Please contact us if you would like to join.' );
    }
    if ( ! isset( $post['_pgr_nonce'] ) || ! is_string( $post['_pgr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( $post['_pgr_nonce'] ), 'pg_register' ) ) {
        return $fail( 'This form has expired. Please reload the page and try again.' );
    }

    // rate limit first: every attempt counts, so the form is no email-guessing machine either
    $rate_key = prospergenics_register_rate_key();
    if ( '' !== $rate_key ) {
        $count = (int) get_transient( $rate_key );
        if ( $count >= PROSPERGENICS_REGISTER_RATE_LIMIT ) {
            return $fail( 'Too many attempts from this connection. Please try again in an hour.' );
        }
        set_transient( $rate_key, $count + 1, PROSPERGENICS_REGISTER_RATE_WINDOW );
    }

    $token_ok = isset( $post['pg_form_time'], $post['pg_form_token'] ) && is_string( $post['pg_form_token'] ) && hash_equals( wp_hash( (string) (int) $post['pg_form_time'] ), $post['pg_form_token'] );
    $bot      = prospergenics_register_bot_reason( $post, time(), $token_ok );
    if ( '' !== $bot ) {
        return $fail( 'too-fast' === $bot ? 'That was very quick. Please check your details and press the button again.' : 'Something went wrong with the form. Please reload the page and try again.' );
    }

    $in       = array(
        'name'     => isset( $post['name'] ) && is_string( $post['name'] ) ? $post['name'] : '',
        'email'    => isset( $post['email'] ) && is_string( $post['email'] ) ? $post['email'] : '',
        'password' => isset( $post['password'] ) && is_string( $post['password'] ) ? $post['password'] : '',
    );
    // lower case and as typed: a stored address may differ in case, and not every database collates alike
    $typed   = trim( $in['email'] );
    $taken   = '' !== $typed && ( email_exists( strtolower( $typed ) ) || email_exists( $typed ) );
    $checked = prospergenics_register_validate( $in, (bool) $taken );
    $keep    = array( 'name' => $checked['clean']['name'], 'email' => $checked['clean']['email'] );
    if ( $checked['errors'] ) {
        return $fail( 'Please fix the highlighted fields.', $checked['errors'], $keep );
    }

    $iam = prospergenics_register_iam_provision( $checked['clean']['email'], $in['password'], $checked['clean']['first'], $checked['clean']['last'] );
    if ( is_wp_error( $iam ) ) {
        $data = (array) $iam->get_error_data();
        return $fail( $iam->get_error_message(), isset( $data['field'] ) ? array( $data['field'] => $iam->get_error_message() ) : array(), $keep );
    }

    $id = prospergenics_register_create_member( $checked['clean'], $in['password'], $iam );
    if ( is_wp_error( $id ) ) {
        return $fail( $id->get_error_message(), array(), $keep );
    }

    // logged in straight away: the wizard and the verification banner need a session
    $user = get_userdata( $id );
    wp_set_current_user( $id );
    wp_set_auth_cookie( $id, false );
    do_action( 'wp_login', $user->user_login, $user );
    do_action( 'prospergenics_member_registered', $id, $iam );
    return $id;
}

/* ───────────────────────── wiring ───────────────────────── */

// WordPress' own wp-login.php?action=register would create accounts without the checks above and
// without IAM, so it never runs: its links and its URL lead to /register/ instead.
add_filter( 'register_url', function () {
    return home_url( '/register/' );
} );
add_action( 'login_init', function () {
    $action = isset( $_REQUEST['action'] ) && is_string( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification
    if ( 'register' === $action ) {
        wp_safe_redirect( home_url( '/register/' ) );
        exit;
    }
} );

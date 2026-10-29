<?php
/**
 * Profiles track: email verification by magic link (card #6339) and the onboarding wizard's
 * first-login redirect (card #6340).
 *
 * Registration itself belongs to jengo-iam (#6338, blocked). Until then this hooks WordPress'
 * own `user_register`, so every newly created account (wp-admin, wp-login.php?action=register,
 * or a future IAM sync that creates the WordPress user) gets the email and the wizard.
 * Accounts that existed before this shipped have no pending flag and count as verified.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROFILES_VERIFY_TTL    = 3 * DAY_IN_SECONDS;
const PROSPERGENICS_PROFILES_RESEND_PAUSE  = 5 * MINUTE_IN_SECONDS;

/** Keyed hash of a verification token (only the hash is stored). */
function prospergenics_profiles_token_hash( $token ) {
    return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
}

/** Whether a member's email address is verified. */
function prospergenics_profiles_is_verified( $user_id ) {
    return ! get_user_meta( (int) $user_id, '_pg_profiles_verify_pending', true );
}

/**
 * Generate a fresh magic link and email it.
 *
 * @return true|WP_Error A failed send is a WP_Error (and logged, see mail.php), never success.
 */
function prospergenics_profiles_send_verification( $user_id ) {
    $user = get_userdata( (int) $user_id );
    if ( ! $user ) {
        return new WP_Error( 'pg_profiles_no_user', 'No such member.', array( 'status' => 404 ) );
    }
    $token = wp_generate_password( 40, false, false );
    update_user_meta( $user->ID, '_pg_profiles_verify_pending', 1 );
    update_user_meta( $user->ID, '_pg_profiles_verify_hash', prospergenics_profiles_token_hash( $token ) );
    update_user_meta( $user->ID, '_pg_profiles_verify_expires', time() + PROSPERGENICS_PROFILES_VERIFY_TTL );

    $link = add_query_arg( array( 'u' => $user->ID, 't' => $token ), home_url( '/profile/verify/' ) );
    $body = sprintf(
        "Hi %s,\n\nPlease confirm your email address for ProsperGenics by opening this link:\n\n%s\n\nThe link works for 3 days. If you did not create an account, you can ignore this email.\n",
        $user->display_name,
        $link
    );
    $sent = prospergenics_profiles_send_mail( $user->user_email, 'Confirm your email address for ProsperGenics', $body, 'verify', $user->ID );
    if ( true === $sent ) {
        // the "wait five minutes" pause only starts once a mail really went out
        update_user_meta( $user->ID, '_pg_profiles_verify_sent', time() );
    }
    return $sent;
}

add_action( 'user_register', function ( $user_id ) {
    update_user_meta( (int) $user_id, '_pg_profiles_onboarding', array( 'status' => 'pending', 'step' => 1, 'steps' => array(), 'shown' => false ) );
    prospergenics_profiles_send_verification( $user_id );
}, 20 );

// A changed email address needs verifying again.
add_action( 'profile_update', function ( $user_id, $old ) {
    $user = get_userdata( (int) $user_id );
    if ( $user && $old instanceof WP_User && strtolower( $old->user_email ) !== strtolower( $user->user_email ) ) {
        prospergenics_profiles_send_verification( $user_id );
    }
}, 20, 2 );

/**
 * Handle a magic link. Does not log anyone in: it only confirms the address.
 *
 * @return string verified|already|invalid|expired
 */
function prospergenics_profiles_verify_link( $user_id, $token ) {
    $user = get_userdata( (int) $user_id );
    if ( ! $user ) {
        return 'invalid';
    }
    if ( prospergenics_profiles_is_verified( $user->ID ) ) {
        return 'already';
    }
    $hash    = (string) get_user_meta( $user->ID, '_pg_profiles_verify_hash', true );
    $expires = (int) get_user_meta( $user->ID, '_pg_profiles_verify_expires', true );
    if ( '' !== $hash && $expires < time() && hash_equals( $hash, prospergenics_profiles_token_hash( $token ) ) ) {
        return 'expired';
    }
    if ( ! prospergenics_profiles_token_valid( $token, $hash, $expires, time(), 'prospergenics_profiles_token_hash' ) ) {
        return 'invalid';
    }
    delete_user_meta( $user->ID, '_pg_profiles_verify_pending' );
    delete_user_meta( $user->ID, '_pg_profiles_verify_hash' );
    delete_user_meta( $user->ID, '_pg_profiles_verify_expires' );
    update_user_meta( $user->ID, '_pg_profiles_email_verified', time() );
    return 'verified';
}

/** Resend, at most once per five minutes. @return bool|WP_Error */
function prospergenics_profiles_resend_verification( $user_id ) {
    if ( prospergenics_profiles_is_verified( $user_id ) ) {
        return new WP_Error( 'pg_profiles_verified', 'Your email address is already verified.', array( 'status' => 400 ) );
    }
    $sent = (int) get_user_meta( (int) $user_id, '_pg_profiles_verify_sent', true );
    if ( $sent && time() - $sent < PROSPERGENICS_PROFILES_RESEND_PAUSE ) {
        return new WP_Error( 'pg_profiles_wait', 'We just sent you an email. Please wait a few minutes before asking for another one.', array( 'status' => 429 ) );
    }
    $sent = prospergenics_profiles_send_verification( $user_id );
    if ( is_wp_error( $sent ) ) {
        return new WP_Error( 'pg_profiles_mail_failed', 'We could not send the email just now. Please try again in a few minutes; if it keeps failing, contact us.', array( 'status' => 503 ) );
    }
    return true;
}

/**
 * The "please verify" banner, right below the site header on every page that renders it:
 * header.php and the generated homepage and About templates fire this hook after the header,
 * so the skip link stays the first Tab stop (audit ARC-19, UX-16).
 */
add_action( 'prospergenics_2026_after_site_header', function () {
    $uid = get_current_user_id();
    if ( ! $uid || prospergenics_profiles_is_verified( $uid ) ) {
        return;
    }
    $user   = wp_get_current_user();
    $mail   = prospergenics_profiles_mail_status( $uid, 'verify' );
    $failed = $mail && 'failed' === $mail['status'];
    ?>
<div class="pgp-verify-banner" role="region" aria-label="Email verification">
  <?php if ( $failed ) : ?>
  <p>Please confirm your email address. We could not send the confirmation link to <strong><?php echo esc_html( $user->user_email ); ?></strong>: please use “Send the link again”. Until then you can edit your profile, but not follow members, endorse skills or add images to “Featured”.</p>
  <?php else : ?>
  <p>Please confirm your email address: we sent a link to <strong><?php echo esc_html( $user->user_email ); ?></strong>. Until then you can edit your profile, but not follow members, endorse skills or add images to “Featured”.</p>
  <?php endif; ?>
  <form method="post" action="<?php echo esc_url( home_url( '/profile/verify/' ) ); ?>">
    <?php wp_nonce_field( 'pg_profiles_resend', '_pgp_nonce' ); ?>
    <input type="hidden" name="pgp_action" value="resend" />
    <button type="submit">Send the link again</button>
  </form>
</div>
<style>
.pgp-verify-banner{display:flex;flex-wrap:wrap;align-items:center;gap:10px 18px;justify-content:center;padding:12px clamp(16px,5vw,72px);background:#fff4d6;border-bottom:1px solid #e8c766;color:#4a3700;font-size:14.5px;line-height:1.5;}
.pgp-verify-banner p{max-width:70ch;}
.pgp-verify-banner button{font:inherit;font-weight:600;font-size:14px;padding:7px 14px;border-radius:999px;border:1.5px solid #8a6500;background:#fff;color:#4a3700;cursor:pointer;min-height:36px;}
</style>
    <?php
} );

/* ───────────────────────── onboarding (card #6340) ───────────────────────── */

function prospergenics_profiles_onboarding( $user_id ) {
    return prospergenics_profiles_normalize_onboarding( get_user_meta( (int) $user_id, '_pg_profiles_onboarding', true ) );
}

function prospergenics_profiles_save_onboarding( $user_id, array $state ) {
    update_user_meta( (int) $user_id, '_pg_profiles_onboarding', prospergenics_profiles_normalize_onboarding( $state ) );
}

/**
 * After the first login of a new account, go to the wizard. Once the wizard has been shown it
 * never takes over a login again (it stays reachable at /profile/welcome/ until finished).
 */
add_filter( 'login_redirect', function ( $redirect_to, $requested, $user ) {
    if ( ! $user instanceof WP_User ) {
        return $redirect_to;
    }
    $state = prospergenics_profiles_onboarding( $user->ID );
    if ( 'pending' === $state['status'] && ! $state['shown'] ) {
        return home_url( '/profile/welcome/' );
    }
    // members (no editing rights) land on their profile instead of wp-admin/profile.php
    if ( in_array( (string) $requested, array( '', admin_url(), admin_url( '/' ) ), true ) && ! user_can( $user, 'edit_posts' ) ) {
        return prospergenics_profiles_url( $user->ID );
    }
    return $redirect_to;
}, 20, 3 );

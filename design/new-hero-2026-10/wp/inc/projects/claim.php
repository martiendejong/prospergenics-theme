<?php
/**
 * Projects track: "verified claim profile" (card #6388). Staff set up a placeholder profile for a
 * real person who is not a member yet, so their work can be shown and linked from projects; the
 * person claims it by proving they control its email address.
 *
 *   wp-admin → Users → Unclaimed profiles (edit_users + create_users): create a placeholder (name,
 *   email, headline) and send its claim link. A placeholder is an ordinary subscriber account with
 *   the user meta _pg_projects_placeholder, a random password nobody knows and an unconfirmed email
 *   address, so it is never indexed and only shows as far as its privacy defaults allow (verified
 *   members). Creating it sends no email; the profiles track's confirmation mail is held back.
 *
 *   The claim link (/profile/claim/?u=<id>&t=<token>, 7 days, only a keyed hash is stored, any new
 *   link replaces the old one) lets the person choose a password. That confirms the email address
 *   (the same meta the profiles track's magic link sets), removes the placeholder flag and logs
 *   them in on their profile in edit mode. Confirming the address any other way (a password reset
 *   followed by the profiles track's confirmation link) also ends the placeholder state.
 *
 *   Until it is claimed, staff may add and edit the placeholder's contributions on project pages
 *   (prospergenics_projects_can_edit_contribution()); real members' contributions stay theirs.
 *
 * Seam for jengo-iam (#6337): when accounts come from IAM, the claim becomes an IAM invitation.
 * Filter 'prospergenics_projects_claim_url' to send people to IAM instead of /profile/claim/, and
 * call prospergenics_projects_claim_complete( $user_id ) when IAM reports the account as claimed.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROJECTS_CLAIM_TTL = 7 * DAY_IN_SECONDS;

add_action( 'init', function () {
    add_rewrite_rule( '^profile/claim/?$', 'index.php?pg_projects_claim=1', 'top' );
}, 9 );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'pg_projects_claim';
    return $vars;
} );

/** Keyed hash of a claim token (only the hash is stored). */
function prospergenics_projects_claim_hash( $token ) {
    return hash_hmac( 'sha256', 'pg-claim|' . (string) $token, wp_salt( 'auth' ) );
}

/** Unclaimed placeholder profiles, newest first. @return WP_User[] */
function prospergenics_projects_placeholders( $number = 200 ) {
    return get_users( array( 'meta_key' => '_pg_projects_placeholder', 'number' => (int) $number, 'orderby' => 'registered', 'order' => 'DESC' ) );
}

/**
 * Create a placeholder profile. No email is sent.
 *
 * @return int|WP_Error user ID
 */
function prospergenics_projects_create_placeholder( $first, $last, $email, $headline, $by ) {
    $first = prospergenics_projects_clip( $first, 60 );
    $last  = prospergenics_projects_clip( $last, 60 );
    $email = sanitize_email( $email );
    if ( '' === $first || '' === $last ) {
        return new WP_Error( 'pg_projects_name', 'Enter the first and last name.' );
    }
    if ( ! is_email( $email ) ) {
        return new WP_Error( 'pg_projects_email', 'Enter a valid email address: the claim link goes there.' );
    }
    if ( email_exists( $email ) ) {
        return new WP_Error( 'pg_projects_email_taken', 'An account with this email address exists already.' );
    }
    $login = 'pg-' . substr( md5( strtolower( $email ) . '|' . wp_generate_password( 12, false ) ), 0, 12 );
    // the profiles track emails a confirmation link on user_register: not for someone who never signed up
    $hold = function ( $short, $atts ) use ( $email ) {
        $to = isset( $atts['to'] ) ? (array) $atts['to'] : array();
        return in_array( $email, $to, true ) ? true : $short;
    };
    add_filter( 'pre_wp_mail', $hold, 10, 2 );
    $id = wp_insert_user( array(
        'user_login'   => $login,
        'user_pass'    => wp_generate_password( 32, true, true ),
        'user_email'   => $email,
        'first_name'   => $first,
        'last_name'    => $last,
        'display_name' => $first . ' ' . $last,
        'role'         => 'subscriber',
    ) );
    remove_filter( 'pre_wp_mail', $hold, 10 );
    if ( is_wp_error( $id ) ) {
        return $id;
    }
    update_user_meta( $id, '_pg_projects_placeholder', array( 'time' => time(), 'by' => (int) $by ) );
    update_user_meta( $id, '_pg_profiles_verify_pending', 1 );
    foreach ( array( '_pg_profiles_verify_hash', '_pg_profiles_verify_expires', '_pg_profiles_verify_sent' ) as $k ) {
        delete_user_meta( $id, $k );
    }
    $headline = prospergenics_projects_clip( $headline, 160 );
    if ( '' !== $headline && function_exists( 'prospergenics_profiles_set_meta' ) ) {
        prospergenics_profiles_set_meta( $id, 'headline', $headline );
    }
    return (int) $id;
}

/** The claim link for a token. */
function prospergenics_projects_claim_url( $user_id, $token ) {
    $url = add_query_arg( array( 'u' => (int) $user_id, 't' => $token ), home_url( '/profile/claim/' ) );
    return (string) apply_filters( 'prospergenics_projects_claim_url', $url, (int) $user_id, $token );
}

/** Make a fresh claim link and email it to the placeholder's address. @return true|WP_Error */
function prospergenics_projects_send_claim( $user_id ) {
    $user = get_userdata( (int) $user_id );
    if ( ! $user || ! prospergenics_projects_is_placeholder( $user->ID ) ) {
        return new WP_Error( 'pg_projects_not_placeholder', 'This account is not an unclaimed profile.' );
    }
    $token = wp_generate_password( 40, false, false );
    update_user_meta( $user->ID, '_pg_projects_claim_hash', prospergenics_projects_claim_hash( $token ) );
    update_user_meta( $user->ID, '_pg_projects_claim_expires', time() + PROSPERGENICS_PROJECTS_CLAIM_TTL );
    $body = sprintf(
        "Hi %s,\n\nThe ProsperGenics team set up a profile for you, to show the projects and work you contributed to:\n%s\n\nTo make it yours, open this link and choose a password (it works for 7 days):\n\n%s\n\nAfter that you decide what is shown and to whom. If you do not want this profile, reply to this email or write to us via %s and we will remove it.\n\nThe ProsperGenics team\n",
        $user->first_name ? $user->first_name : $user->display_name,
        function_exists( 'prospergenics_profiles_url' ) ? prospergenics_profiles_url( $user->ID ) : home_url( '/' ),
        prospergenics_projects_claim_url( $user->ID, $token ),
        home_url( '/contact/' )
    );
    $sent = function_exists( 'prospergenics_profiles_send_mail' )
        ? prospergenics_profiles_send_mail( $user->user_email, 'Your ProsperGenics profile is waiting for you', $body, 'claim', $user->ID )
        : wp_mail( $user->user_email, 'Your ProsperGenics profile is waiting for you', $body );
    if ( true !== $sent ) {
        return is_wp_error( $sent ) ? $sent : new WP_Error( 'pg_projects_mail', 'The email could not be sent.' );
    }
    update_user_meta( $user->ID, '_pg_projects_claim_sent', time() );
    return true;
}

/** Check a claim link. @return string valid|invalid|expired|claimed */
function prospergenics_projects_claim_check( $user_id, $token ) {
    $user = get_userdata( (int) $user_id );
    if ( ! $user ) {
        return 'invalid';
    }
    if ( ! prospergenics_projects_is_placeholder( $user->ID ) ) {
        return 'claimed';
    }
    $hash    = (string) get_user_meta( $user->ID, '_pg_projects_claim_hash', true );
    $expires = (int) get_user_meta( $user->ID, '_pg_projects_claim_expires', true );
    $token   = (string) $token;
    if ( '' === $hash || '' === $token || strlen( $token ) > 200 || ! hash_equals( $hash, prospergenics_projects_claim_hash( $token ) ) ) {
        return 'invalid';
    }
    return $expires < time() ? 'expired' : 'valid';
}

/** The account is claimed: email confirmed, placeholder state and claim link gone. */
function prospergenics_projects_claim_complete( $user_id ) {
    $user_id = (int) $user_id;
    foreach ( array( '_pg_projects_placeholder', '_pg_projects_claim_hash', '_pg_projects_claim_expires', '_pg_projects_claim_sent', '_pg_profiles_verify_pending', '_pg_profiles_verify_hash', '_pg_profiles_verify_expires' ) as $k ) {
        delete_user_meta( $user_id, $k );
    }
    if ( ! get_user_meta( $user_id, '_pg_profiles_email_verified', true ) ) {
        update_user_meta( $user_id, '_pg_profiles_email_verified', time() );
    }
    do_action( 'prospergenics_projects_claimed', $user_id );
}

// Confirming the email address any other way ends the placeholder state too.
$prospergenics_projects_on_verified = function ( $meta_id, $user_id, $key ) {
    if ( '_pg_profiles_email_verified' === $key && prospergenics_projects_is_placeholder( $user_id ) ) {
        prospergenics_projects_claim_complete( $user_id );
    }
};
add_action( 'added_user_meta', $prospergenics_projects_on_verified, 10, 3 );
add_action( 'updated_user_meta', $prospergenics_projects_on_verified, 10, 3 );
unset( $prospergenics_projects_on_verified );

/* ───────────────────────── /profile/claim/ ───────────────────────── */

add_action( 'template_redirect', function () {
    if ( ! get_query_var( 'pg_projects_claim' ) ) {
        return;
    }
    global $wp_query;
    $wp_query->is_404  = false;
    $wp_query->is_home = false;
    nocache_headers();
    status_header( 200 );
    $u     = isset( $_REQUEST['u'] ) ? absint( $_REQUEST['u'] ) : 0;
    $t     = isset( $_REQUEST['t'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['t'] ) ) : '';
    $state = prospergenics_projects_claim_check( $u, $t );
    $error = '';
    if ( 'valid' === $state && get_current_user_id() && get_current_user_id() !== $u ) {
        $state = 'other';
    }
    if ( 'valid' === $state && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
        $n = isset( $_POST['_pgj_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_pgj_nonce'] ) ) : '';
        if ( ! wp_verify_nonce( $n, 'pg_projects_claim_' . $u ) ) {
            wp_die( 'This form has expired. Please open the link from the email again.', 'Expired', array( 'response' => 403 ) );
        }
        // passwords are compared as typed: never run them through a text sanitiser
        $p1 = isset( $_POST['new_password'] ) && is_string( $_POST['new_password'] ) ? wp_unslash( $_POST['new_password'] ) : '';
        $p2 = isset( $_POST['confirm_password'] ) && is_string( $_POST['confirm_password'] ) ? wp_unslash( $_POST['confirm_password'] ) : '';
        if ( strlen( $p1 ) < 10 ) {
            $error = 'Choose a password of at least 10 characters.';
        } elseif ( $p1 !== $p2 ) {
            $error = 'The two passwords are not the same.';
        } else {
            wp_set_password( $p1, $u );
            prospergenics_projects_claim_complete( $u );
            wp_set_current_user( $u );
            wp_set_auth_cookie( $u, false, is_ssl() );
            $to = function_exists( 'prospergenics_profiles_url' ) ? prospergenics_profiles_url( $u, array( 'edit' => 1, 'claimed' => 1 ) ) : home_url( '/' );
            wp_safe_redirect( $to, 303 );
            exit;
        }
    }
    $GLOBALS['pg_projects_claim'] = array( 'state' => $state, 'u' => $u, 't' => $t, 'error' => $error );
    add_filter( 'pre_get_document_title', function () {
        return 'Claim your profile – ProsperGenics';
    } );
    add_filter( 'wp_robots', function ( $robots ) {
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
        return $robots;
    }, 30 );
    add_filter( 'wp_robots', 'wp_robots_sensitive_page', 31 );
    add_action( 'wp_enqueue_scripts', function () {
        $dir = get_template_directory();
        if ( file_exists( $dir . '/assets/platform/profiles.css' ) ) {
            wp_enqueue_style( 'pg-profiles', get_template_directory_uri() . '/assets/platform/profiles.css', array(), (string) filemtime( $dir . '/assets/platform/profiles.css' ) );
        }
    } );
    prospergenics_projects_render_claim_page();
    exit;
}, 5 );

/** The claim page (a small template of its own: header, one form, footer). */
function prospergenics_projects_render_claim_page() {
    $c        = $GLOBALS['pg_projects_claim'];
    $messages = array(
        'invalid' => 'This link is not valid. Open the link from the most recent email exactly as it was sent, or ask the ProsperGenics team for a new one.',
        'expired' => 'This link has expired. Ask the ProsperGenics team to send you a new one via the contact page.',
        'claimed' => 'This profile has been claimed already. Log in with its email address.',
        'other'   => 'You are logged in with another account. Log out first, then open the link again.',
    );
    get_header();
    ?>
    <div class="post-hero">
      <div class="post-hero-inner">
        <div class="ph-eyebrow"><span class="dot"></span>Member profile</div>
        <h1>Claim your profile</h1>
      </div>
    </div>
    <div class="post-wrap pgp-narrow">
      <?php if ( 'valid' === $c['state'] ) :
          $user = get_userdata( $c['u'] ); ?>
        <p>The ProsperGenics team set up a profile for <strong><?php echo esc_html( $user->display_name ); ?></strong>. Choose a password to make it yours. You can then change what it shows and who sees it.</p>
        <form class="pgp-form" method="post" action="<?php echo esc_url( home_url( '/profile/claim/' ) ); ?>">
          <input type="hidden" name="u" value="<?php echo (int) $c['u']; ?>" />
          <input type="hidden" name="t" value="<?php echo esc_attr( $c['t'] ); ?>" />
          <?php wp_nonce_field( 'pg_projects_claim_' . (int) $c['u'], '_pgj_nonce', false ); ?>
          <?php if ( '' !== $c['error'] ) : ?><p class="pgp-notice pgp-notice-error" role="alert" id="pgj-claim-err"><?php echo esc_html( $c['error'] ); ?></p><?php endif; ?>
          <div class="pgp-field">
            <label for="pgj-claim-email">Email address</label>
            <input type="email" id="pgj-claim-email" value="<?php echo esc_attr( $user->user_email ); ?>" readonly autocomplete="username" />
          </div>
          <div class="pgp-field">
            <label for="pgj-claim-p1">New password (at least 10 characters)</label>
            <input type="password" id="pgj-claim-p1" name="new_password" minlength="10" required autocomplete="new-password"<?php echo '' !== $c['error'] ? ' aria-invalid="true" aria-describedby="pgj-claim-err"' : ''; ?> />
          </div>
          <div class="pgp-field">
            <label for="pgj-claim-p2">Repeat the password</label>
            <input type="password" id="pgj-claim-p2" name="confirm_password" minlength="10" required autocomplete="new-password" />
          </div>
          <div class="pgp-form-actions"><button type="submit" class="pgp-btn pgp-btn-primary">Claim my profile</button></div>
        </form>
      <?php else : ?>
        <p class="pgp-notice pgp-notice-error" role="alert"><?php echo esc_html( $messages[ $c['state'] ] ); ?></p>
        <p><a href="<?php echo esc_url( wp_login_url() ); ?>">Log in</a> · <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact us</a></p>
      <?php endif; ?>
    </div>
    <?php
    get_footer();
}

/** The notice on an unclaimed profile (called from pg-profile.php). */
function prospergenics_projects_placeholder_notice( $owner ) {
    if ( $owner && isset( $_GET['claimed'] ) && get_current_user_id() === (int) $owner->ID && function_exists( 'prospergenics_profiles_notice' ) ) {
        prospergenics_profiles_notice( 'ok', 'Welcome: this profile is yours now and your email address is confirmed. Check what it shows under “Edit profile” and in your privacy settings.' );
    }
    if ( ! $owner || ! prospergenics_projects_is_placeholder( $owner->ID ) || ! function_exists( 'prospergenics_profiles_notice' ) ) {
        return;
    }
    prospergenics_profiles_notice( 'info', 'The ProsperGenics team set up this profile for someone who is not a member yet. They can claim it with their email address; until then it is not confirmed by them.' );
}

/* ───────────────────────── wp-admin: Users → Unclaimed profiles ───────────────────────── */

function prospergenics_projects_can_manage_placeholders() {
    return current_user_can( 'edit_users' ) && current_user_can( 'create_users' );
}

add_action( 'admin_menu', function () {
    add_users_page( 'Unclaimed profiles', 'Unclaimed profiles', 'edit_users', 'pg-placeholders', 'prospergenics_projects_placeholders_page' );
} );

add_action( 'admin_post_pg_projects_placeholder', function () {
    check_admin_referer( 'pg_projects_placeholder' );
    if ( ! prospergenics_projects_can_manage_placeholders() ) {
        wp_die( 'You are not allowed to do this.', 'Not allowed', array( 'response' => 403 ) );
    }
    $raw = function ( $k ) {
        return isset( $_POST[ $k ] ) && is_string( $_POST[ $k ] ) ? sanitize_text_field( wp_unslash( $_POST[ $k ] ) ) : '';
    };
    $id   = prospergenics_projects_create_placeholder( $raw( 'first_name' ), $raw( 'last_name' ), $raw( 'email' ), $raw( 'headline' ), get_current_user_id() );
    $args = is_wp_error( $id ) ? array( 'pg_err' => $id->get_error_code() ) : array( 'pg_done' => 'created' );
    wp_safe_redirect( add_query_arg( $args, admin_url( 'users.php?page=pg-placeholders' ) ) );
    exit;
} );

add_action( 'admin_post_pg_projects_claim_send', function () {
    $uid = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    check_admin_referer( 'pg_projects_claim_send_' . $uid );
    if ( ! prospergenics_projects_can_manage_placeholders() || ! current_user_can( 'edit_user', $uid ) ) {
        wp_die( 'You are not allowed to do this.', 'Not allowed', array( 'response' => 403 ) );
    }
    $r = prospergenics_projects_send_claim( $uid );
    wp_safe_redirect( add_query_arg( is_wp_error( $r ) ? array( 'pg_err' => 'mail' ) : array( 'pg_done' => 'sent' ), admin_url( 'users.php?page=pg-placeholders' ) ) );
    exit;
} );

function prospergenics_projects_placeholders_page() {
    if ( ! current_user_can( 'edit_users' ) ) {
        wp_die( 'You are not allowed to see this page.', 'Not allowed', array( 'response' => 403 ) );
    }
    $done = isset( $_GET['pg_done'] ) ? sanitize_key( $_GET['pg_done'] ) : '';
    $err  = isset( $_GET['pg_err'] ) ? sanitize_key( $_GET['pg_err'] ) : '';
    $errs = array(
        'pg_projects_name'        => 'Enter the first and last name.',
        'pg_projects_email'       => 'Enter a valid email address.',
        'pg_projects_email_taken' => 'An account with this email address exists already.',
        'mail'                    => 'The claim email could not be sent. Check the mail settings and try again.',
    );
    echo '<div class="wrap"><h1>Unclaimed profiles</h1>';
    echo '<p>Profiles the ProsperGenics team set up for people who are not members yet, so their work can be shown on projects. The person claims the profile with the link you send: choosing a password confirms the email address and makes the profile theirs. Until then the profile is visible to verified members only and never to search engines.</p>';
    if ( 'created' === $done ) {
        echo '<div class="notice notice-success"><p>The profile is created. Add the person\'s contributions on the project pages, then send the claim link.</p></div>';
    } elseif ( 'sent' === $done ) {
        echo '<div class="notice notice-success"><p>The claim link is sent.</p></div>';
    }
    if ( isset( $errs[ $err ] ) ) {
        echo '<div class="notice notice-error"><p>' . esc_html( $errs[ $err ] ) . '</p></div>';
    }
    $list = prospergenics_projects_placeholders();
    if ( $list ) {
        echo '<table class="widefat striped"><thead><tr><th scope="col">Name</th><th scope="col">Email</th><th scope="col">Created</th><th scope="col">Claim link</th></tr></thead><tbody>';
        foreach ( $list as $u ) {
            $meta = get_user_meta( $u->ID, '_pg_projects_placeholder', true );
            $sent = (int) get_user_meta( $u->ID, '_pg_projects_claim_sent', true );
            echo '<tr><td><a href="' . esc_url( function_exists( 'prospergenics_profiles_url' ) ? prospergenics_profiles_url( $u->ID ) : get_edit_user_link( $u->ID ) ) . '">' . esc_html( $u->display_name ) . '</a></td>';
            echo '<td>' . esc_html( $u->user_email ) . '</td>';
            echo '<td>' . esc_html( is_array( $meta ) && ! empty( $meta['time'] ) ? gmdate( 'Y-m-d', (int) $meta['time'] ) : '' ) . '</td><td>';
            if ( current_user_can( 'create_users' ) ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pg_projects_claim_send" /><input type="hidden" name="user_id" value="' . (int) $u->ID . '" />';
                wp_nonce_field( 'pg_projects_claim_send_' . (int) $u->ID );
                echo '<button type="submit" class="button">' . ( $sent ? 'Send a new claim link' : 'Send claim link' ) . '<span class="screen-reader-text"> to ' . esc_html( $u->display_name ) . '</span></button>';
                echo $sent ? ' <span class="description">last sent ' . esc_html( gmdate( 'Y-m-d H:i', $sent ) ) . ' UTC</span>' : '';
                echo '</form>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    } else {
        echo '<p>No unclaimed profiles.</p>';
    }
    if ( current_user_can( 'create_users' ) ) {
        echo '<h2>Set up a profile</h2><form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pg_projects_placeholder" />';
        wp_nonce_field( 'pg_projects_placeholder' );
        echo '<table class="form-table" role="presentation">';
        foreach ( array( 'first_name' => 'First name', 'last_name' => 'Last name', 'email' => 'Email address', 'headline' => 'Headline (optional)' ) as $k => $label ) {
            printf( '<tr><th scope="row"><label for="pg-ph-%1$s">%2$s</label></th><td><input type="%3$s" class="regular-text" id="pg-ph-%1$s" name="%1$s"%4$s /></td></tr>', esc_attr( $k ), esc_html( $label ), 'email' === $k ? 'email' : 'text', 'headline' === $k ? '' : ' required' );
        }
        echo '</table><p class="description">No email is sent when you create the profile. Use "Send claim link" when it is ready.</p>';
        submit_button( 'Create profile' );
        echo '</form>';
    }
    echo '</div>';
}

<?php
/**
 * Profiles track: URLs, templates and assets.
 *
 *   /in/{slug}/            member profile (card #6350); ?edit=1 edit mode, ?view=public preview
 *   /profile/              your own profile (or the login page)
 *   /profile/settings/     privacy settings (card #6351)
 *   /profile/following/    the people you follow and your followers (card #6349)
 *   /profile/welcome/      onboarding wizard (card #6340)
 *   /profile/verify/       magic link target and "send again" (card #6339)
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROFILES_REWRITE_VERSION = '1';

add_action( 'init', function () {
    add_rewrite_rule( '^in/([^/]+)/?$', 'index.php?pg_profile=$matches[1]', 'top' );
    add_rewrite_rule( '^profile/(settings|following|welcome|verify)/?$', 'index.php?pg_profile_page=$matches[1]', 'top' );
    add_rewrite_rule( '^profile/?$', 'index.php?pg_profile_page=me', 'top' );
    // flush once when the rules change (a cheap option read on every request otherwise)
    if ( get_option( '_pg_profiles_rewrite_version' ) !== PROSPERGENICS_PROFILES_REWRITE_VERSION ) {
        flush_rewrite_rules( false );
        update_option( '_pg_profiles_rewrite_version', PROSPERGENICS_PROFILES_REWRITE_VERSION );
    }
} );
add_action( 'after_switch_theme', function () {
    delete_option( '_pg_profiles_rewrite_version' );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'pg_profile';
    $vars[] = 'pg_profile_page';
    return $vars;
} );

/** Which profile view this request is, or ''. */
function prospergenics_profiles_route() {
    if ( '' !== (string) get_query_var( 'pg_profile' ) ) {
        return 'profile';
    }
    $page = (string) get_query_var( 'pg_profile_page' );
    return in_array( $page, array( 'me', 'settings', 'following', 'welcome', 'verify' ), true ) ? $page : '';
}

/** The member whose profile is being requested (set in template_redirect). */
function prospergenics_profiles_current_owner( $set = null ) {
    static $owner = null;
    if ( null !== $set ) {
        $owner = $set;
    }
    return $owner;
}

/** Login URL that comes back to the current page. */
function prospergenics_profiles_login_url( $back = '' ) {
    return wp_login_url( $back ? $back : home_url( add_query_arg( array() ) ) );
}

// Don't run the blog query behind these pages.
add_filter( 'posts_pre_query', function ( $posts, $query ) {
    if ( $query->is_main_query() && ( '' !== (string) $query->get( 'pg_profile' ) || '' !== (string) $query->get( 'pg_profile_page' ) ) ) {
        $query->found_posts = 0;
        $query->max_num_pages = 0;
        return array();
    }
    return $posts;
}, 10, 2 );

add_action( 'template_redirect', function () {
    $route = prospergenics_profiles_route();
    if ( '' === $route ) {
        return;
    }
    global $wp_query;
    $wp_query->is_home = false;
    $wp_query->is_404  = false;
    // the content depends on who is looking: never cache
    nocache_headers();
    $uid = get_current_user_id();

    if ( 'profile' === $route ) {
        $slug  = sanitize_title( (string) get_query_var( 'pg_profile' ) );
        $owner = prospergenics_profiles_user_by_slug( $slug );
        if ( ! $owner ) {
            $old = prospergenics_profiles_user_by_old_slug( $slug );
            if ( $old ) {
                wp_safe_redirect( prospergenics_profiles_url( $old->ID ), 301 );
                exit;
            }
            $wp_query->set_404();
            status_header( 404 );
            return; // the theme's 404.php
        }
        prospergenics_profiles_current_owner( $owner );
        return;
    }

    if ( 'me' === $route ) {
        wp_safe_redirect( $uid ? prospergenics_profiles_url( $uid ) : prospergenics_profiles_login_url( home_url( '/profile/' ) ) );
        exit;
    }

    if ( 'verify' === $route ) {
        prospergenics_profiles_handle_verify();
        return;
    }

    // settings, following and welcome are for the logged-in member only
    if ( ! $uid ) {
        wp_safe_redirect( prospergenics_profiles_login_url( home_url( '/profile/' . $route . '/' ) ) );
        exit;
    }
    if ( 'settings' === $route && 'POST' === $_SERVER['REQUEST_METHOD'] ) {
        prospergenics_profiles_handle_settings_post( $uid );
    }
    if ( 'welcome' === $route ) {
        prospergenics_profiles_handle_welcome( $uid );
    }
}, 5 );

/** Magic link (GET) and "send again" (POST). */
function prospergenics_profiles_handle_verify() {
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
        $uid = get_current_user_id();
        if ( ! $uid || ! isset( $_POST['_pgp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pgp_nonce'] ) ), 'pg_profiles_resend' ) ) {
            wp_die( 'This link has expired. Please go back and try again.', 'Expired', array( 'response' => 403 ) );
        }
        $r = prospergenics_profiles_resend_verification( $uid );
        $GLOBALS['pg_profiles_notice'] = is_wp_error( $r ) ? array( 'error', $r->get_error_message() ) : array( 'ok', 'We sent a new confirmation link to your email address.' );
        return;
    }
    $u = isset( $_GET['u'] ) ? absint( $_GET['u'] ) : 0;
    $t = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
    if ( ! $u || '' === $t ) {
        $GLOBALS['pg_profiles_notice'] = array( 'error', 'This confirmation link is incomplete. Open the link from the email exactly as it was sent.' );
        return;
    }
    $result = prospergenics_profiles_verify_link( $u, $t );
    if ( 'verified' === $result ) {
        wp_safe_redirect( prospergenics_profiles_url( $u, array( 'verified' => 1 ) ) );
        exit;
    }
    $messages = array(
        'already' => array( 'ok', 'Your email address is already confirmed. There is nothing more to do.' ),
        'expired' => array( 'error', 'This confirmation link has expired. Log in and use “Send the link again” at the top of the page.' ),
        'invalid' => array( 'error', 'This confirmation link is not valid (any newer link replaces older ones). Log in and use “Send the link again” at the top of the page.' ),
    );
    $GLOBALS['pg_profiles_notice'] = $messages[ $result ];
    $GLOBALS['pg_profiles_notice_profile'] = 'already' === $result ? $u : 0;
}

/** Privacy settings form (works without JavaScript). */
function prospergenics_profiles_handle_settings_post( $uid ) {
    if ( ! isset( $_POST['_pgp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pgp_nonce'] ) ), 'pg_profiles_privacy' ) || ! current_user_can( 'read' ) ) {
        wp_die( 'This form has expired. Please go back, reload the page and try again.', 'Expired', array( 'response' => 403 ) );
    }
    $in      = isset( $_POST['privacy'] ) && is_array( $_POST['privacy'] ) ? wp_unslash( $_POST['privacy'] ) : array();
    $privacy = prospergenics_profiles_privacy( $uid );
    foreach ( $privacy as $section => $level ) {
        if ( isset( $in[ $section ] ) && is_string( $in[ $section ] ) ) {
            $privacy[ $section ] = sanitize_key( $in[ $section ] );
        }
    }
    prospergenics_profiles_set_meta( $uid, 'privacy', prospergenics_profiles_normalize_privacy( $privacy ) );
    wp_safe_redirect( add_query_arg( 'saved', '1', home_url( '/profile/settings/' ) ) );
    exit;
}

/** Wizard: mark as shown, handle a submitted step, send finished members to their profile. */
function prospergenics_profiles_handle_welcome( $uid ) {
    $state = prospergenics_profiles_onboarding( $uid );
    if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
        if ( ! isset( $_POST['_pgp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pgp_nonce'] ) ), 'pg_profiles_welcome' ) || ! current_user_can( 'read' ) ) {
            wp_die( 'This form has expired. Please go back, reload the page and try again.', 'Expired', array( 'response' => 403 ) );
        }
        $step   = isset( $_POST['step'] ) ? min( 3, max( 1, absint( $_POST['step'] ) ) ) : 1;
        $action = isset( $_POST['pgp_skip'] ) ? 'skip' : 'save';
        $result = prospergenics_profiles_onboarding_step( $uid, $step, $action, wp_unslash( $_POST ), $_FILES );
        if ( is_wp_error( $result ) ) {
            $GLOBALS['pg_profiles_notice'] = array( 'error', $result->get_error_message() );
            $GLOBALS['pg_profiles_step']   = $step;
            return;
        }
        $state = prospergenics_profiles_onboarding( $uid );
        if ( 'done' === $state['status'] ) {
            wp_safe_redirect( prospergenics_profiles_url( $uid, array( 'welcome' => 1 ) ) );
            exit;
        }
        wp_safe_redirect( home_url( '/profile/welcome/' ) );
        exit;
    }
    if ( 'done' === $state['status'] ) {
        wp_safe_redirect( prospergenics_profiles_url( $uid ) );
        exit;
    }
    if ( ! $state['shown'] || 'none' === $state['status'] ) {
        $state['shown']  = true;
        $state['status'] = 'pending';
        prospergenics_profiles_save_onboarding( $uid, $state );
    }
}

add_filter( 'template_include', function ( $template ) {
    $route = prospergenics_profiles_route();
    if ( '' === $route || is_404() ) {
        return $template;
    }
    $files = array(
        'profile'   => 'pg-profile.php',
        'settings'  => 'pg-profile-settings.php',
        'following' => 'pg-profile-following.php',
        'welcome'   => 'pg-profile-welcome.php',
        'verify'    => 'pg-profile-notice.php',
    );
    if ( isset( $files[ $route ] ) ) {
        $found = locate_template( $files[ $route ] );
        if ( $found ) {
            return $found;
        }
    }
    return $template;
}, 20 );

add_filter( 'body_class', function ( $classes ) {
    if ( '' !== prospergenics_profiles_route() && ! is_404() ) {
        $classes[] = 'pg-profiles';
    }
    return $classes;
} );

add_filter( 'pre_get_document_title', function ( $title ) {
    $route = prospergenics_profiles_route();
    if ( '' === $route || is_404() ) {
        return $title;
    }
    $titles = array(
        'settings'  => 'Privacy settings',
        'following' => 'Your connections',
        'welcome'   => 'Welcome',
        'verify'    => 'Confirm your email address',
    );
    if ( 'profile' === $route && prospergenics_profiles_current_owner() ) {
        $owner = prospergenics_profiles_current_owner();
        $vis   = prospergenics_profiles_visibility_for( $owner->ID, prospergenics_profiles_viewer_id() );
        return ( $vis['profile'] ? $owner->display_name : 'Member profile' ) . ' – ProsperGenics';
    }
    return ( isset( $titles[ $route ] ) ? $titles[ $route ] : 'Profile' ) . ' – ProsperGenics';
}, 20 );

/** Only public profiles may be indexed; settings, wizard and the rest never. */
add_filter( 'wp_robots', function ( $robots ) {
    $route = prospergenics_profiles_route();
    if ( '' === $route ) {
        return $robots;
    }
    $owner = prospergenics_profiles_current_owner();
    if ( 'profile' !== $route || ! $owner || 'public' !== prospergenics_profiles_privacy( $owner->ID )['profile'] ) {
        $robots['noindex']  = true;
        $robots['nofollow'] = true;
    }
    return $robots;
}, 20 );

add_action( 'wp_head', function () {
    $owner = prospergenics_profiles_current_owner();
    if ( 'profile' !== prospergenics_profiles_route() || ! $owner ) {
        return;
    }
    $vis = prospergenics_profiles_visibility_for( $owner->ID, 0 );
    echo '<link rel="canonical" href="' . esc_url( prospergenics_profiles_url( $owner->ID ) ) . "\" />\n";
    if ( $vis['profile'] ) {
        $headline = (string) prospergenics_profiles_meta( $owner->ID, 'headline' );
        $desc     = $owner->display_name . ( '' !== $headline ? ' – ' . $headline : '' ) . ' on ProsperGenics.';
        echo '<meta name="description" content="' . esc_attr( $desc ) . "\" />\n";
    }
}, 3 );

/**
 * The viewer the page renders for: the owner previewing "as a visitor" (?view=public) gets the
 * logged-out view (card #6351: public preview mode).
 */
function prospergenics_profiles_viewer_id() {
    $uid   = get_current_user_id();
    $owner = prospergenics_profiles_current_owner();
    if ( $owner && $uid === $owner->ID && isset( $_GET['view'] ) && 'public' === $_GET['view'] ) {
        return 0;
    }
    return $uid;
}

add_action( 'wp_enqueue_scripts', function () {
    $route = prospergenics_profiles_route();
    if ( '' === $route || is_404() ) {
        return;
    }
    $dir = get_template_directory();
    $uri = get_template_directory_uri();
    $css = '/assets/platform/profiles.css';
    $js  = '/assets/platform/profiles.js';
    wp_enqueue_style( 'pg-profiles', $uri . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
    wp_enqueue_script( 'pg-profiles', $uri . $js, array(), file_exists( $dir . $js ) ? (string) filemtime( $dir . $js ) : null, true );
    wp_localize_script( 'pg-profiles', 'pgProfiles', array(
        'rest'  => esc_url_raw( rest_url( PROSPERGENICS_PROFILES_REST_NS . '/profile/' ) ),
        'nonce' => is_user_logged_in() ? wp_create_nonce( 'wp_rest' ) : '',
    ) );
} );

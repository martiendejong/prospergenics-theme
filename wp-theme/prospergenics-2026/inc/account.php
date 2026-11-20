<?php
/**
 * Accounts on the front end (audit ARC-3, ARC-5, NAV-17).
 *
 *   - The WordPress admin bar only for people who edit content (posts or articles); members and
 *     subscribers see the site header's account menu instead.
 *   - Members who open /wp-admin/ land on their profile (or the homepage). admin-ajax.php,
 *     admin-post.php (the contact form, profile and course actions) and async-upload.php keep working.
 *     One exception (card #6341): a company account may open the company profile screens, and only
 *     those (prospergenics_2026_company_screen_allowed()).
 *   - The login page carries the ProsperGenics logo (linking home) and colours.
 *   - A pg_member marker cookie while someone is logged in, so the static pages (people, experts,
 *     community) can show the member links too (audit #6486). It holds no name or ID.
 * The header's Log in / Join / account links themselves are in inc/site-chrome.php.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Whether a user is staff (may edit content): blog posts, or articles only (writers, the pg_writer
 * role of audit ARC-8, who write in the block editor).
 */
function prospergenics_2026_is_staff( $user_id = null ) {
    foreach ( array( 'edit_posts', 'edit_pg_articles' ) as $cap ) {
        if ( null === $user_id ? current_user_can( $cap ) : user_can( $user_id, $cap ) ) {
            return true;
        }
    }
    return false;
}

add_filter( 'show_admin_bar', function ( $show ) {
    return $show && prospergenics_2026_is_staff();
} );

/**
 * The one wp-admin exception for accounts that are not staff (card #6341): a company account (it
 * holds edit_pg_company_profiles, inc/platform-roles.php) may open the company profile screens:
 * the list (edit.php?post_type=pg_company_profile), a new profile (post-new.php?post_type=
 * pg_company_profile) and its own profiles (post.php: open, save, preview, trash). Every other
 * screen still sends it to its profile, and another company's profile too (edit_post: the role
 * has no edit_others_pg_company_profiles).
 *
 * @param int    $user_id   Current user ID.
 * @param string $script    The admin script requested.
 * @param string $post_type ?post_type= of edit.php and post-new.php ('' for none).
 * @param int    $post_id   The post post.php acts on (?post=, or post_ID when saving; 0 for none).
 */
function prospergenics_2026_company_screen_allowed( $user_id, $script, $post_type, $post_id ) {
    if ( ! $user_id || ! user_can( $user_id, 'edit_pg_company_profiles' ) ) {
        return false;
    }
    if ( 'post.php' === $script ) {
        return $post_id > 0 && 'pg_company_profile' === get_post_type( $post_id ) && user_can( $user_id, 'edit_post', $post_id );
    }
    return 'pg_company_profile' === $post_type && in_array( $script, array( 'edit.php', 'post-new.php' ), true );
}

/**
 * Where a non-staff request for a wp-admin screen goes, or '' to let it through.
 *
 * @param string $script    The admin script requested (e.g. 'index.php', 'admin-ajax.php').
 * @param bool   $is_ajax   Whether this is an AJAX request.
 * @param bool   $staff     Whether the user may edit content.
 * @param int    $user_id   Current user ID (0 for none).
 * @param string $post_type ?post_type= of the request ('' for none).
 * @param int    $post_id   The post post.php acts on (0 for none).
 */
function prospergenics_2026_admin_redirect_target( $script, $is_ajax, $staff, $user_id, $post_type = '', $post_id = 0 ) {
    if ( $staff || $is_ajax || ! $user_id || in_array( $script, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true ) ) {
        return '';
    }
    if ( prospergenics_2026_company_screen_allowed( $user_id, $script, $post_type, $post_id ) ) {
        return '';
    }
    return function_exists( 'prospergenics_profiles_url' ) ? prospergenics_profiles_url( $user_id ) : home_url( '/' );
}

function prospergenics_2026_keep_members_out_of_admin() {
    $script = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : basename( (string) parse_url( isset( $_SERVER['SCRIPT_NAME'] ) ? $_SERVER['SCRIPT_NAME'] : '', PHP_URL_PATH ) );
    // phpcs:disable WordPress.Security.NonceVerification -- only picks the screen; WordPress checks its own nonces
    $post_type = isset( $_GET['post_type'] ) && is_string( $_GET['post_type'] ) ? sanitize_key( wp_unslash( $_GET['post_type'] ) ) : '';
    // the post post.php acts on, read as post.php reads it: ?post=, else post_ID of the edit form
    $post_id = isset( $_GET['post'] ) ? ( is_scalar( $_GET['post'] ) ? absint( $_GET['post'] ) : 0 ) : ( isset( $_POST['post_ID'] ) && is_scalar( $_POST['post_ID'] ) ? absint( $_POST['post_ID'] ) : 0 );
    // phpcs:enable
    $target = prospergenics_2026_admin_redirect_target( $script, wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ), prospergenics_2026_is_staff(), get_current_user_id(), $post_type, $post_id );
    if ( '' !== $target ) {
        wp_safe_redirect( $target );
        exit;
    }
}
add_action( 'admin_init', 'prospergenics_2026_keep_members_out_of_admin', 1 );
// screens a member may not open (edit.php, ...) are refused before admin_init runs
add_action( 'admin_page_access_denied', 'prospergenics_2026_keep_members_out_of_admin', 1 );

// A company account in wp-admin (the exception above): no menu items that would only send it back
// out (Dashboard, Profile), so the menu is just "Company profiles", and no "WordPress x.y is
// available! Please notify the site administrator." notice.
add_action( 'admin_menu', function () {
    if ( ! prospergenics_2026_is_staff() ) {
        remove_menu_page( 'index.php' );
        remove_menu_page( 'profile.php' );
        remove_action( 'admin_notices', 'update_nag', 3 );
    }
}, 999 );

/* ── branded login page ── */

add_filter( 'login_headerurl', function () {
    return home_url( '/' );
} );

add_filter( 'login_headertext', function () {
    return 'ProsperGenics';
} );

add_action( 'login_enqueue_scripts', function () {
    $logo = esc_url( get_template_directory_uri() . '/assets/logo.png' );
    ?>
<style id="prospergenics-login">
body.login{background:#f4faf6;color:#0b2016;font-family:'Inter',system-ui,sans-serif;}
#login h1 a,.login h1 a{background-image:url('<?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput -- esc_url above. ?>');background-size:contain;background-position:center;width:84px;height:80px;margin-bottom:18px;}
.login form{border:1px solid rgba(15,61,40,.12);border-radius:16px;box-shadow:0 24px 60px -28px rgba(11,32,22,.35);}
.login label{color:#0f3d28;}
.login input[type=text]:focus,.login input[type=password]:focus,.login input[type=email]:focus{border-color:#1a7a46;box-shadow:0 0 0 1px #1a7a46;}
body.login.wp-core-ui .button-primary{background:#1a7a46;border-color:#1a7a46;border-radius:999px;padding:0 18px;}
body.login.wp-core-ui .button-primary:hover,body.login.wp-core-ui .button-primary:focus{background:#0f3d28;border-color:#0f3d28;}
body.login.wp-core-ui .button-secondary,body.login .button.wp-hide-pw{color:#1a7a46;}
.login #nav a,.login #backtoblog a,.login .privacy-policy-page-link a{color:#0f3d28;}
.login #nav a:hover,.login #backtoblog a:hover{color:#1a7a46;}
.login .message,.login .notice,.login .success{border-left-color:#34c759;}
</style>
    <?php
} );

/**
 * Set or clear the pg_member marker cookie. It only says "someone is logged in in this browser":
 * site-chrome.js reads it on the static pages, which Apache serves without WordPress, to swap
 * Log in / Join for the account menu. Every page those links open checks the real login itself.
 *
 * @param bool $on     Set (true) or clear (false).
 * @param int  $expire Unix time it expires, 0 for the browser session (as the login cookie).
 */
function prospergenics_2026_member_marker( $on, $expire = 0 ) {
    if ( headers_sent() ) {
        return;
    }
    setcookie( 'pg_member', $on ? '1' : '', array(
        'expires'  => $on ? (int) $expire : time() - YEAR_IN_SECONDS,
        'path'     => '/',
        'secure'   => is_ssl(),
        'httponly' => false, // read by site-chrome.js
        'samesite' => 'Lax',
    ) );
}

add_action( 'set_logged_in_cookie', function ( $cookie, $expire ) {
    prospergenics_2026_member_marker( true, $expire );
}, 10, 2 );
add_action( 'clear_auth_cookie', function () {
    prospergenics_2026_member_marker( false );
} );
// keeps the marker in step for logins from before it existed and for expired logins
add_action( 'init', function () {
    if ( is_user_logged_in() !== isset( $_COOKIE['pg_member'] ) ) {
        prospergenics_2026_member_marker( is_user_logged_in() );
    }
} );

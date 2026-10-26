<?php
/**
 * Accounts on the front end (audit ARC-3, ARC-5, NAV-17).
 *
 *   - The WordPress admin bar only for people who edit content (posts or articles); members and
 *     subscribers see the site header's account menu instead.
 *   - Members who open /wp-admin/ land on their profile (or the homepage). admin-ajax.php,
 *     admin-post.php (the contact form, profile and course actions) and async-upload.php keep working.
 *   - The login page carries the ProsperGenics logo (linking home) and colours.
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
 * Where a non-staff request for a wp-admin screen goes, or '' to let it through.
 *
 * @param string $script  The admin script requested (e.g. 'index.php', 'admin-ajax.php').
 * @param bool   $is_ajax Whether this is an AJAX request.
 * @param bool   $staff   Whether the user may edit content.
 * @param int    $user_id Current user ID (0 for none).
 */
function prospergenics_2026_admin_redirect_target( $script, $is_ajax, $staff, $user_id ) {
    if ( $staff || $is_ajax || ! $user_id || in_array( $script, array( 'admin-ajax.php', 'admin-post.php', 'async-upload.php' ), true ) ) {
        return '';
    }
    return function_exists( 'prospergenics_profiles_url' ) ? prospergenics_profiles_url( $user_id ) : home_url( '/' );
}

function prospergenics_2026_keep_members_out_of_admin() {
    $script = isset( $GLOBALS['pagenow'] ) ? (string) $GLOBALS['pagenow'] : basename( (string) parse_url( isset( $_SERVER['SCRIPT_NAME'] ) ? $_SERVER['SCRIPT_NAME'] : '', PHP_URL_PATH ) );
    $target = prospergenics_2026_admin_redirect_target( $script, wp_doing_ajax() || ( defined( 'DOING_CRON' ) && DOING_CRON ), prospergenics_2026_is_staff(), get_current_user_id() );
    if ( '' !== $target ) {
        wp_safe_redirect( $target );
        exit;
    }
}
add_action( 'admin_init', 'prospergenics_2026_keep_members_out_of_admin', 1 );
// screens a member may not open (edit.php, ...) are refused before admin_init runs
add_action( 'admin_page_access_denied', 'prospergenics_2026_keep_members_out_of_admin', 1 );

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

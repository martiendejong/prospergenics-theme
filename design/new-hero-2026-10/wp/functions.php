<?php
/**
 * Prospergenics 2026 - intentionally minimal.
 * The front page and About templates are self-contained (inline CSS/JS) to preserve the
 * interactive front-end exactly as designed; inner pages share header.php/footer.php.
 */
add_action( 'after_setup_theme', function () {
    add_theme_support( 'automatic-feed-links' );
} );
// The templates carry their own <title>; keep WP/plugins from printing a second one.
remove_action( 'wp_head', '_wp_render_title_tag', 1 );

require get_template_directory() . '/inc/security.php';
require get_template_directory() . '/inc/seo.php';

/**
 * Legacy URL redirects (301), only for requests that would otherwise 404.
 *
 * Ported from the production theme (task 855: /kenya/ -> /about/) and extended for the
 * 2026 profile move: member profiles used to live at /members/<slug>/ (team_member CPT)
 * and /team_member/<slug>/, and now live as static pages at /people/<slug>/. A request is
 * only forwarded when that static profile actually exists, so a mistyped name still 404s
 * instead of bouncing to a page that is not there.
 *
 * Add further slugs to $legacy_redirects or $profile_slugs when an audit finds more.
 */
function prospergenics_2026_legacy_url_redirects() {
    if ( ! is_404() ) {
        return;
    }

    $request_path = trim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

    $legacy_redirects = array(
        'kenya' => '/about/',
    );
    if ( isset( $legacy_redirects[ $request_path ] ) ) {
        wp_safe_redirect( home_url( $legacy_redirects[ $request_path ] ), 301 );
        exit;
    }

    // Old team_member slugs that differ from the new /people/ slug.
    $profile_slugs = array(
        'martien-de-jong' => 'martien',
        'frank-kobaai'    => 'frank',
    );
    if ( preg_match( '#^(?:members|team_member)/([a-z0-9-]+)$#', $request_path, $m ) ) {
        $slug = isset( $profile_slugs[ $m[1] ] ) ? $profile_slugs[ $m[1] ] : $m[1];
        if ( file_exists( ABSPATH . 'people/' . $slug . '/index.html' ) ) {
            wp_safe_redirect( home_url( '/people/' . $slug . '/' ), 301 );
            exit;
        }
    }
}
add_action( 'template_redirect', 'prospergenics_2026_legacy_url_redirects' );

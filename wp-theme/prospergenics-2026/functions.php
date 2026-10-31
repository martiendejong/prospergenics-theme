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
// The one site header + footer (menu in inc/site-chrome/nav.json), also put on jengo-listings
// pages; and the account side: admin bar for staff only, members kept out of wp-admin, login page.
require get_template_directory() . '/inc/site-chrome.php';
require get_template_directory() . '/inc/account.php';
require get_template_directory() . '/inc/avatars.php';     // one avatar (photo or initials), no Gravatar

// Contact form: every enquiry is stored before any email is attempted, with spam flagged
// (never discarded) and the delivery result recorded. Ported from PR #20 for production.
require get_template_directory() . '/inc/spam-protection.php';
require get_template_directory() . '/inc/contact-messages-store.php';
require get_template_directory() . '/inc/contact-messages-admin.php';
require get_template_directory() . '/inc/contact-form-handler.php';
require get_template_directory() . '/inc/comment-spam-protection.php';
// page-contact.php shows the result next to the form; skip the handler's generic footer notice.
remove_action( 'wp_footer', 'prospergenics_contact_form_messages' );

/**
 * Serve page-contact.php at /contact/ even when WordPress has no "contact" page yet, so the
 * contact links in the header, footer and 404 page never lead nowhere.
 */
add_action( 'template_redirect', function () {
    if ( ! is_404() || 'contact' !== trim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' ) ) {
        return;
    }
    global $wp_query;
    $wp_query->is_404 = false;
    status_header( 200 );
    add_filter( 'pre_get_document_title', function () { return 'Contact – ProsperGenics'; } );
    include get_template_directory() . '/page-contact.php';
    exit;
}, 20 );

// Legacy URL redirects (301, only for requests that would otherwise 404). Rules: inc/redirects.txt.
require get_template_directory() . '/inc/redirects.php';
// Sitemap entries for the static /people/, /experts/ and /community/ pages.
require get_template_directory() . '/inc/sitemap.php';

// Prospergenics Platform features (JengoWork board "Prospergenics Platform"), one loader per
// track so the tracks can be built independently. See docs/PLATFORM-BUILD.md.
require get_template_directory() . '/inc/platform-search.php';   // #6393 /find/: people by skill, project or problem
require get_template_directory() . '/inc/platform-articles.php';
require get_template_directory() . '/inc/platform-profiles.php';
require get_template_directory() . '/inc/platform-courses.php';
require get_template_directory() . '/inc/platform-projects.php'; // #6388 portfolios: /projects/, contributions
// The staff area at /manage/ (card #6457): overview, members, contact messages, articles for review.
require get_template_directory() . '/inc/platform-manage.php';

<?php
/**
 * Prospergenics Platform: profiles track (JengoWork board "Prospergenics Platform").
 * Loader for this track's modules in inc/profiles/. See docs/PLATFORM-BUILD.md.
 *
 * Member profiles at /in/{username}/ for WordPress users (the curated /people/ pages are
 * separate static pages and stay as they are).
 */

defined( 'ABSPATH' ) || exit;

$prospergenics_profiles_dir = __DIR__ . '/profiles/';
require $prospergenics_profiles_dir . 'helpers.php';            // pure rules (privacy, slugs, validation, completion)
require $prospergenics_profiles_dir . 'skills-vocabulary.php';  // #6347 autocomplete seed list
require $prospergenics_profiles_dir . 'mail.php';               // ARC-14 sender address, failures returned and logged
require $prospergenics_profiles_dir . 'data.php';               // #6343 CPTs + user meta, follows, endorsements, photo, featured
require $prospergenics_profiles_dir . 'verify.php';             // #6339 magic link, #6340 first-login redirect
require $prospergenics_profiles_dir . 'privacy.php';            // ARC-12 export/erase, account deletion clean-up
require $prospergenics_profiles_dir . 'account.php';            // ARC-5 email and password changes on /profile/settings/
require $prospergenics_profiles_dir . 'rest.php';               // prospergenics/v1/profile/…
require $prospergenics_profiles_dir . 'routes.php';             // /in/{slug}/, /profile/…, templates, assets
require $prospergenics_profiles_dir . 'render.php';             // markup helpers for the templates
unset( $prospergenics_profiles_dir );

// Article bylines (#6367) and course instructors (#6357, #6362) link to the member's profile,
// when this reader may see it.
function prospergenics_profiles_public_url_for( $url, $user_id ) {
    if ( ! $user_id || ! get_userdata( $user_id ) ) {
        return $url;
    }
    $visible = prospergenics_profiles_visibility_for( $user_id, get_current_user_id() );
    return ! empty( $visible['profile'] ) ? prospergenics_profiles_url( $user_id ) : $url;
}
add_filter( 'prospergenics_articles_author_url', 'prospergenics_profiles_public_url_for', 10, 2 );
add_filter( 'prospergenics_courses_instructor_profile_url', 'prospergenics_profiles_public_url_for', 10, 2 );

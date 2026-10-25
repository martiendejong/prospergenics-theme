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
require $prospergenics_profiles_dir . 'data.php';               // #6343 CPTs + user meta, follows, endorsements, photo, featured
require $prospergenics_profiles_dir . 'verify.php';             // #6339 magic link, #6340 first-login redirect
require $prospergenics_profiles_dir . 'rest.php';               // prospergenics/v1/profile/…
require $prospergenics_profiles_dir . 'routes.php';             // /in/{slug}/, /profile/…, templates, assets
require $prospergenics_profiles_dir . 'render.php';             // markup helpers for the templates
unset( $prospergenics_profiles_dir );

// Article bylines (#6367) link to the author's profile, when this reader may see it.
add_filter( 'prospergenics_articles_author_url', function ( $url, $user_id ) {
    if ( ! $user_id || ! get_userdata( $user_id ) ) {
        return $url;
    }
    $viewer  = get_current_user_id();
    $visible = prospergenics_profiles_visible_sections(
        prospergenics_profiles_privacy( $user_id ),
        $viewer,
        $user_id,
        prospergenics_profiles_is_connection( $user_id, $viewer )
    );
    return ! empty( $visible['profile'] ) ? prospergenics_profiles_url( $user_id ) : $url;
}, 10, 2 );

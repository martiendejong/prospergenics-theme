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
require $prospergenics_profiles_dir . 'applications.php';       // ARC-8 "Apply to write / teach", Users → Requests
require $prospergenics_profiles_dir . 'users-admin.php';        // ARC-15 Users columns, row actions, hidden profiles
require $prospergenics_profiles_dir . 'moderation.php';         // #6472 Report on articles/profiles/discussion posts, the queue, suspension
require $prospergenics_profiles_dir . 'scrutiny.php';           // #6389 public evidence, corrections, reviews: rules, data, review flow, privacy
require $prospergenics_profiles_dir . 'scrutiny-render.php';    // #6389 the two public hooks, the forms and their handlers
require $prospergenics_profiles_dir . 'scrutiny-queue.php';     // #6389 the Scrutiny tab of wp-admin → Reports
require $prospergenics_profiles_dir . 'rest.php';               // prospergenics/v1/profile/…
require $prospergenics_profiles_dir . 'routes.php';             // /in/{slug}/, /profile/…, templates, assets
require $prospergenics_profiles_dir . 'render.php';             // markup helpers for the templates
require_once $prospergenics_profiles_dir . 'rank-rules.php';   // badges: evidence weights (shared with search), caps, tiers
require $prospergenics_profiles_dir . 'rank.php';               // badges: public evidence, the cached rank, what recomputes it, export/erase
require $prospergenics_profiles_dir . 'badges.php';             // badges: the badge markup, the owner's breakdown, /badges/
unset( $prospergenics_profiles_dir );

/* ───────────── link to the curated /people/ story (audit CON-11) ─────────────
 *
 * A WordPress user can be linked to their curated /people/<slug>/ page with the user meta
 * _pg_people_slug (set on the user's wp-admin screen by an administrator, or with wp-cli:
 * `wp user meta update <user> _pg_people_slug <slug>`). Their /in/ page then links to the story
 * and borrows its photo and role while the account has none, and bylines / instructor links go
 * to the story while the /in/ page is too empty to be indexed. The person data comes from
 * people/build-profiles.py (PROFILES), exported to inc/people.json: no second copy.
 */

/** The curated people from inc/people.json, keyed by /people/ slug. */
function prospergenics_2026_people() {
    static $people = null;
    if ( null === $people ) {
        $data   = json_decode( (string) @file_get_contents( __DIR__ . '/people.json' ), true );
        $people = ( is_array( $data ) && isset( $data['people'] ) && is_array( $data['people'] ) ) ? $data['people'] : array();
    }
    return $people;
}

/**
 * The /people/ entry a user is linked to, or null.
 *
 * @return array{slug:string,name:string,first_name:string,role:string,url:string,photo:string,pos:string}|null
 */
function prospergenics_profiles_person( $user_id ) {
    $slug   = sanitize_title( (string) get_user_meta( (int) $user_id, '_pg_people_slug', true ) );
    $people = prospergenics_2026_people();
    if ( '' === $slug || ! isset( $people[ $slug ] ) ) {
        return null;
    }
    $p = $people[ $slug ];
    return array(
        'slug'       => $slug,
        'name'       => (string) $p['name'],
        'first_name' => (string) strtok( (string) $p['name'], ' ' ),
        'role'       => (string) $p['role'],
        'url'        => home_url( '/people/' . $slug . '/' ),
        'photo'      => '' !== (string) $p['img'] ? get_template_directory_uri() . '/assets/' . $p['img'] : '',
        'pos'        => (string) $p['pos'],
    );
}

// wp-admin: administrators link a user to their /people/ page (user-edit.php carries core's nonce).
function prospergenics_profiles_people_field( $user ) {
    if ( ! current_user_can( 'edit_users' ) ) {
        return;
    }
    $current = (string) get_user_meta( $user->ID, '_pg_people_slug', true );
    ?>
    <h2>ProsperGenics story page</h2>
    <table class="form-table" role="presentation">
      <tr>
        <th><label for="pg-people-slug">/people/ page</label></th>
        <td>
          <select name="pg_people_slug" id="pg-people-slug">
            <option value="">None</option>
            <?php foreach ( prospergenics_2026_people() as $slug => $p ) : ?>
              <option value="<?php echo esc_attr( $slug ); ?>" <?php selected( $current, $slug ); ?>><?php echo esc_html( $p['name'] . ' (/people/' . $slug . '/)' ); ?></option>
            <?php endforeach; ?>
          </select>
          <p class="description">The curated story of this member. Their /in/ profile links to it, and bylines link to it while the profile is still mostly empty.</p>
        </td>
      </tr>
    </table>
    <?php
}
add_action( 'show_user_profile', 'prospergenics_profiles_people_field' );
add_action( 'edit_user_profile', 'prospergenics_profiles_people_field' );

function prospergenics_profiles_save_people_field( $user_id ) {
    if ( ! current_user_can( 'edit_users' ) || ! isset( $_POST['pg_people_slug'] ) ) {
        return;
    }
    check_admin_referer( 'update-user_' . $user_id );
    $slug = sanitize_title( wp_unslash( $_POST['pg_people_slug'] ) );
    if ( '' === $slug ) {
        delete_user_meta( $user_id, '_pg_people_slug' );
    } elseif ( isset( prospergenics_2026_people()[ $slug ] ) ) {
        update_user_meta( $user_id, '_pg_people_slug', $slug );
    }
}
add_action( 'personal_options_update', 'prospergenics_profiles_save_people_field' );
add_action( 'edit_user_profile_update', 'prospergenics_profiles_save_people_field' );

// Article bylines (#6367) and course instructors (#6357, #6362) link to the member's profile,
// when this reader may see it; to their /people/ story instead while the /in/ page is too
// empty to be indexed (CON-11), so no byline leads to an empty profile when a story exists.
function prospergenics_profiles_public_url_for( $url, $user_id ) {
    if ( ! $user_id || ! get_userdata( $user_id ) ) {
        return $url;
    }
    $person = prospergenics_profiles_person( $user_id );
    if ( $person && ! prospergenics_profiles_is_indexable( $user_id ) ) {
        return $person['url'];
    }
    $visible = prospergenics_profiles_visibility_for( $user_id, get_current_user_id() );
    return ! empty( $visible['profile'] ) ? prospergenics_profiles_url( $user_id ) : $url;
}
add_filter( 'prospergenics_articles_author_url', 'prospergenics_profiles_public_url_for', 10, 2 );
add_filter( 'prospergenics_courses_instructor_profile_url', 'prospergenics_profiles_public_url_for', 10, 2 );

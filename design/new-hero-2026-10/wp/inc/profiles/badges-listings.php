<?php
/**
 * Profiles track: member badges on the jengo-listings /members/ directory (the cards are put
 * together in inc/site-chrome.php, prospergenics_2026_listings_add_member_badges()).
 *
 * The plugin's cards link to /members/<listing slug>/, which is no WordPress account. The one
 * hand-checked link between the two is the person redirects (inc/person-redirects.txt):
 * members/<listing slug> → /people/<slug>/, and an administrator links an account to that
 * /people/ page with _pg_people_slug (inc/platform-profiles.php). A card gets a badge only when
 * that chain ends at exactly one account; anything else (no rule, no account, two accounts)
 * gets none.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Listing slug => /people/ slug, by the person redirects (pure).
 *
 * @param string[]             $listing_slugs from the cards
 * @param array<string,string> $rules         prospergenics_2026_parse_redirects() of person-redirects.txt
 * @param array                $people        prospergenics_2026_people()
 */
function prospergenics_profiles_listing_people_slugs( array $listing_slugs, array $rules, array $people ) {
    $map = array();
    foreach ( $listing_slugs as $listing ) {
        $listing = (string) $listing;
        $target  = isset( $rules[ 'members/' . $listing ] ) ? (string) $rules[ 'members/' . $listing ] : '';
        if ( preg_match( '#^/people/([a-z0-9-]+)/?$#', $target, $m ) && isset( $people[ $m[1] ] ) ) {
            $map[ $listing ] = $m[1];
        }
    }
    return $map;
}

/**
 * /people/ slug => user ID, for the accounts linked to those pages: one query, and the accounts
 * and their meta primed in one more read. A page claimed by two accounts maps to neither.
 *
 * @param string[] $people_slugs
 * @return array<string,int>
 */
function prospergenics_profiles_users_by_people_slug( array $people_slugs ) {
    $people_slugs = array_values( array_unique( array_filter( array_map( 'strval', $people_slugs ) ) ) );
    if ( ! $people_slugs ) {
        return array();
    }
    $ids = get_users( array(
        'meta_query'  => array( array( 'key' => '_pg_people_slug', 'value' => $people_slugs, 'compare' => 'IN' ) ),
        'fields'      => 'ID',
        'count_total' => false,
    ) );
    $ids = array_map( 'intval', (array) $ids );
    prospergenics_profiles_badges_prime( $ids ); // the meta read below and the badges come from this
    $by_slug = array();
    $taken   = array();
    foreach ( $ids as $id ) {
        $slug = (string) get_user_meta( $id, '_pg_people_slug', true );
        if ( isset( $by_slug[ $slug ] ) ) {
            $taken[ $slug ] = true;
        }
        $by_slug[ $slug ] = $id;
    }
    return array_diff_key( $by_slug, $taken );
}

/**
 * The badges for the /members/ cards (filter 'prospergenics_2026_listings_member_badges').
 *
 * @param array    $badges        listing slug => badge markup, from earlier callbacks
 * @param string[] $listing_slugs the cards on the page
 */
function prospergenics_profiles_listing_member_badges( $badges, $listing_slugs ) {
    if ( ! function_exists( 'prospergenics_2026_person_redirect_rules' ) ) {
        return $badges;
    }
    $people = prospergenics_profiles_listing_people_slugs( (array) $listing_slugs, prospergenics_2026_person_redirect_rules(), prospergenics_2026_people() );
    $users  = prospergenics_profiles_users_by_people_slug( array_values( $people ) );
    $badges = (array) $badges;
    foreach ( $people as $listing => $slug ) {
        $html = isset( $users[ $slug ] ) ? prospergenics_profiles_badge_html( $users[ $slug ], 'inline' ) : '';
        if ( '' !== $html ) {
            $badges[ $listing ] = $html;
        }
    }
    return $badges;
}
add_filter( 'prospergenics_2026_listings_member_badges', 'prospergenics_profiles_listing_member_badges', 10, 2 );

// The badge styles in the head of the /members/ directory, not printed late in the footer.
add_filter( 'prospergenics_profiles_badges_wanted', function ( $wanted ) {
    return $wanted || ( function_exists( 'prospergenics_2026_request_path' ) && '/members/' === trailingslashit( prospergenics_2026_request_path() ) && prospergenics_2026_is_listings_page() );
} );

<?php
/**
 * Expert directory data model (task #4891).
 *
 * Expert profiles come from two sources:
 *   1. Curated profiles in inc/people.json with an expert_categories field
 *   2. User-registered experts: WordPress users with _pg_is_expert = '1'
 *
 * User meta keys:
 *   _pg_is_expert          '1' approved, 'pending' awaiting review, '' = not an expert
 *   _pg_expert_categories  JSON array of category slugs
 *   _pg_expert_bio         expert bio (may differ from general profile bio)
 *   _pg_expert_links       JSON array of {label, url} objects
 *   _pg_expert_title       professional title / tagline
 *   _pg_expert_slug        SEO slug for /experts/{slug}/ (defaults to user_login)
 */

defined( 'ABSPATH' ) || exit;

/** All approved curated experts from people.json, keyed by slug. */
function prospergenics_expert_curated_all() {
	static $curated = null;
	if ( null === $curated ) {
		$data    = function_exists( 'prospergenics_2026_people' ) ? prospergenics_2026_people() : array();
		$curated = array_filter( $data, function ( $p ) {
			return ! empty( $p['expert_categories'] ) && is_array( $p['expert_categories'] );
		} );
	}
	return $curated;
}

/** All approved user-registered experts, keyed by their expert slug. */
function prospergenics_expert_users_all() {
	static $users = null;
	if ( null === $users ) {
		$results = get_users( array(
			'meta_key'   => '_pg_is_expert',
			'meta_value' => '1',
			'fields'     => 'all',
		) );
		$users = array();
		foreach ( $results as $user ) {
			$users[ prospergenics_expert_user_slug( $user ) ] = $user;
		}
	}
	return $users;
}

/** The slug for a registered expert's /experts/{slug}/ URL. */
function prospergenics_expert_user_slug( $user ) {
	$slug = get_user_meta( $user->ID, '_pg_expert_slug', true );
	return sanitize_title( $slug ? $slug : $user->user_login );
}

/**
 * All approved experts (curated + user-registered), optionally filtered by category.
 *
 * @return array[] Each entry: type, slug, name, role, quote, img, pos, expertises[], categories[], bio, links[], url
 */
function prospergenics_experts_all( $category = '' ) {
	$out = array();

	foreach ( prospergenics_expert_curated_all() as $slug => $p ) {
		$cats = (array) $p['expert_categories'];
		if ( $category && ! in_array( $category, $cats, true ) ) {
			continue;
		}
		$out[ $slug ] = array(
			'type'       => 'curated',
			'slug'       => $slug,
			'name'       => (string) $p['name'],
			'role'       => (string) $p['role'],
			'quote'      => isset( $p['quote'] ) ? (string) $p['quote'] : '',
			'img'        => isset( $p['img'] ) ? (string) $p['img'] : '',
			'pos'        => isset( $p['pos'] ) ? (string) $p['pos'] : 'center',
			'expertises' => isset( $p['expertises'] ) ? (array) $p['expertises'] : array(),
			'categories' => $cats,
			'bio'        => '',
			'links'      => array(),
			'url'        => home_url( '/experts/' . $slug . '/' ),
		);
	}

	foreach ( prospergenics_expert_users_all() as $slug => $user ) {
		if ( isset( $out[ $slug ] ) ) {
			continue; // curated takes precedence
		}
		$cats = (array) json_decode( (string) get_user_meta( $user->ID, '_pg_expert_categories', true ), true );
		if ( $category && ! in_array( $category, $cats, true ) ) {
			continue;
		}
		$out[ $slug ] = array(
			'type'       => 'user',
			'slug'       => $slug,
			'name'       => $user->display_name,
			'role'       => (string) get_user_meta( $user->ID, '_pg_expert_title', true ),
			'quote'      => '',
			'img'        => '',
			'pos'        => 'center',
			'expertises' => array(),
			'categories' => $cats,
			'bio'        => (string) get_user_meta( $user->ID, '_pg_expert_bio', true ),
			'links'      => (array) json_decode( (string) get_user_meta( $user->ID, '_pg_expert_links', true ), true ),
			'url'        => home_url( '/experts/' . $slug . '/' ),
		);
	}

	return $out;
}

/** One expert by slug, or null. */
function prospergenics_expert_by_slug( $slug ) {
	$slug = sanitize_title( $slug );
	$all  = prospergenics_experts_all();
	return isset( $all[ $slug ] ) ? $all[ $slug ] : null;
}

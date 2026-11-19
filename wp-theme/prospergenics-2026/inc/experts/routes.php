<?php
/**
 * Expert directory routes (task #4891).
 *
 *   /experts/                   directory landing (pg_experts_page=directory)
 *   /experts/category/{slug}/   category filter  (pg_experts_category={slug})
 *   /experts/{slug}/            individual profile (pg_expert={slug})
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_EXPERTS_REWRITE_VERSION = '2';

add_action( 'init', function () {
	add_rewrite_rule( '^experts/category/([^/]+)/?$', 'index.php?pg_experts_category=$matches[1]', 'top' );
	add_rewrite_rule( '^experts/([^/]+)/?$',           'index.php?pg_expert=$matches[1]',           'top' );
	add_rewrite_rule( '^experts/?$',                   'index.php?pg_experts_page=directory',       'top' );
	add_rewrite_rule( '^profile/expert/?$',            'index.php?pg_profile_page=expert',          'top' );
	if ( get_option( '_pg_experts_rewrite_version' ) !== PROSPERGENICS_EXPERTS_REWRITE_VERSION ) {
		flush_rewrite_rules( false );
		update_option( '_pg_experts_rewrite_version', PROSPERGENICS_EXPERTS_REWRITE_VERSION );
	}
} );
add_action( 'after_switch_theme', function () {
	delete_option( '_pg_experts_rewrite_version' );
} );

add_filter( 'query_vars', function ( $vars ) {
	$vars[] = 'pg_experts_page';
	$vars[] = 'pg_experts_category';
	$vars[] = 'pg_expert';
	return $vars;
} );

/** Which experts route this request is, or ''. */
function prospergenics_experts_route() {
	if ( '' !== (string) get_query_var( 'pg_expert' ) ) {
		return 'expert';
	}
	if ( '' !== (string) get_query_var( 'pg_experts_category' ) ) {
		return 'category';
	}
	if ( 'directory' === (string) get_query_var( 'pg_experts_page' ) ) {
		return 'directory';
	}
	return '';
}

// Skip the main blog query behind these pages.
add_filter( 'posts_pre_query', function ( $posts, $query ) {
	if ( $query->is_main_query() && (
		'' !== (string) $query->get( 'pg_expert' ) ||
		'' !== (string) $query->get( 'pg_experts_category' ) ||
		'directory' === (string) $query->get( 'pg_experts_page' )
	) ) {
		$query->found_posts  = 0;
		$query->max_num_pages = 0;
		return array();
	}
	return $posts;
}, 10, 2 );

add_action( 'template_redirect', function () {
	$route = prospergenics_experts_route();
	if ( '' === $route ) {
		return;
	}
	global $wp_query;
	$wp_query->is_home = false;
	$wp_query->is_404  = false;
	status_header( 200 );

	switch ( $route ) {
		case 'directory':
			include get_template_directory() . '/pg-experts.php';
			break;
		case 'category':
			include get_template_directory() . '/pg-expert-category.php';
			break;
		case 'expert':
			include get_template_directory() . '/pg-expert-profile.php';
			break;
	}
	exit;
} );

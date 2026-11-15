<?php
/**
 * Courses track: the jengo-listings courses on /courses/.
 *
 * Since 10 Oct the courses Martien built (HTML, CSS, JavaScript, Python, AI Software Lifecycle)
 * live in the jengo-listings system: the listings service holds them and the jengo-listings
 * plugin serves each course at /courses/<slug>/ and its lessons at /courses/<slug>/learn/. On
 * 25 Oct this theme's own course archive took over /courses/, so those courses dropped off the
 * catalogue page although they never went away (reported 4 Nov). This module lists them on
 * /courses/ again, next to any courses made with this theme's course tools (cards #6499, #6524).
 *
 * Read from the same listings service the plugin uses. Cached for 30 minutes, and the last good
 * list is kept, so a short outage of the service never empties the catalogue.
 */

defined( 'ABSPATH' ) || exit;

/** Where the listings service publishes the courses collection. */
function prospergenics_courses_listings_api_url() {
	return (string) apply_filters( 'prospergenics_courses_listings_api_url', 'https://listings.prospergenics.com/collections/courses/items?per_page=50' );
}

/**
 * The listings service's JSON as a list of courses: slug, title, summary, skills, provider.
 * Items without a usable slug or title are dropped.
 *
 * @param string|array $json
 * @return array<int,array{slug:string,title:string,summary:string,skills:string[],provider:string,level:string,category:string}>
 */
function prospergenics_courses_listings_parse( $json ) {
	$data  = is_array( $json ) ? $json : json_decode( (string) $json, true );
	$items = is_array( $data ) && isset( $data['items'] ) && is_array( $data['items'] ) ? $data['items'] : array();
	$out   = array();
	foreach ( $items as $item ) {
		if ( ! is_array( $item ) ) {
			continue;
		}
		$slug  = sanitize_title( isset( $item['slug'] ) ? (string) $item['slug'] : '' );
		$title = trim( wp_strip_all_tags( isset( $item['title'] ) ? (string) $item['title'] : '' ) );
		if ( '' === $slug || '' === $title ) {
			continue;
		}
		$text    = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( isset( $item['description_seo'] ) ? (string) $item['description_seo'] : '' ) ) );
		$summary = strlen( $text ) > 180 ? rtrim( substr( $text, 0, strrpos( substr( $text, 0, 180 ), ' ' ) ?: 180 ), " ,.;:" ) . '…' : $text;
		$skills  = array_values( array_filter( array_map( 'trim', explode( ',', isset( $item['primary_skills'] ) ? (string) $item['primary_skills'] : '' ) ) ) );
		$extra   = isset( $item['fields'] ) && is_array( $item['fields'] ) ? $item['fields'] : ( isset( $item['fields_json'] ) && is_array( $item['fields_json'] ) ? $item['fields_json'] : array() );
		$level   = prospergenics_courses_sanitize_level(
			isset( $item['level'] ) ? (string) $item['level'] : ( isset( $extra['level'] ) ? (string) $extra['level'] : '' )
		);
		$cat     = sanitize_title(
			isset( $item['category'] ) ? (string) $item['category'] : ( isset( $extra['category'] ) ? (string) $extra['category'] : '' )
		);
		$out[]   = array(
			'slug'     => $slug,
			'title'    => $title,
			'summary'  => $summary,
			'skills'   => array_slice( $skills, 0, 4 ),
			'provider' => trim( wp_strip_all_tags( isset( $item['company'] ) ? (string) $item['company'] : '' ) ),
			'level'    => $level,
			'category' => $cat,
		);
	}
	return $out;
}

/**
 * The courses in the listings service, cached; the last good list when the service is down.
 *
 * @return array As prospergenics_courses_listings_parse().
 */
function prospergenics_courses_listings() {
	$cached = get_transient( 'pg_courses_listings' );
	if ( is_array( $cached ) ) {
		return $cached;
	}
	$last     = get_option( 'pg_courses_listings_last', array() );
	$last     = is_array( $last ) ? $last : array();
	$response = wp_remote_get( prospergenics_courses_listings_api_url(), array( 'timeout' => 6, 'headers' => array( 'Accept' => 'application/json' ) ) );
	$items    = array();
	if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
		$items = prospergenics_courses_listings_parse( wp_remote_retrieve_body( $response ) );
	}
	if ( $items ) {
		set_transient( 'pg_courses_listings', $items, 30 * MINUTE_IN_SECONDS );
		update_option( 'pg_courses_listings_last', $items, false );
		return $items;
	}
	// service down or empty: keep showing the last good list, and ask again in 5 minutes
	set_transient( 'pg_courses_listings', $last, 5 * MINUTE_IN_SECONDS );
	return $last;
}

/**
 * Apply the archive's category and level filters to the listings courses.
 * Sort has no effect here: the listings carry no enrolment counts, so the service order is kept.
 *
 * @param array[] $items   As returned by prospergenics_courses_listings().
 * @param array   $filters category (slug), level (slug), sort.
 * @return array[]
 */
function prospergenics_courses_listings_filter( array $items, array $filters ) {
	$cat   = isset( $filters['category'] ) ? (string) $filters['category'] : '';
	$level = isset( $filters['level'] ) ? (string) $filters['level'] : '';
	if ( '' !== $cat ) {
		$items = array_filter( $items, function ( $i ) use ( $cat ) { return isset( $i['category'] ) && $i['category'] === $cat; } );
	}
	if ( '' !== $level ) {
		$items = array_filter( $items, function ( $i ) use ( $level ) { return isset( $i['level'] ) && $i['level'] === $level; } );
	}
	return array_values( $items );
}

/**
 * Query vars for the WP course archive under a category filter. A category that exists as a WP term
 * filters by it; one that exists only in the listings has no WP courses, so none may be shown (an
 * unfiltered query would list every WP course under it).
 *
 * @return array<string,mixed>
 */
function prospergenics_courses_category_query_vars( $category ) {
	if ( '' === (string) $category ) {
		return array();
	}
	if ( term_exists( $category, 'pg_course_category' ) ) {
		return array( 'tax_query' => array( array( 'taxonomy' => 'pg_course_category', 'field' => 'slug', 'terms' => $category, 'include_children' => true ) ) );
	}
	return array( 'post__in' => array( 0 ) );
}

/**
 * Unique non-empty category slug => label pairs from a parsed listings array.
 *
 * @param array[] $items As returned by prospergenics_courses_listings().
 * @return array<string,string>
 */
function prospergenics_courses_listings_category_options( array $items ) {
	$out = array();
	foreach ( $items as $i ) {
		if ( ! empty( $i['category'] ) ) {
			$out[ $i['category'] ] = ucwords( str_replace( '-', ' ', $i['category'] ) );
		}
	}
	asort( $out );
	return $out;
}

/** A catalogue card for one listings course, in the same style as the theme's course cards. */
function prospergenics_courses_listing_card( array $item ) {
	$url   = home_url( '/courses/' . $item['slug'] . '/' );
	$html  = '<article class="pgc-card pgc-card--listing">';
	$html .= '<div class="pgc-card-media"><span class="pgc-thumb-placeholder pg-pattern pg-pattern--' . ( abs( crc32( $item['slug'] ) ) % 3 + 1 ) . '" aria-hidden="true"></span></div>';
	$html .= '<div class="pgc-card-body">';
	$html .= '<div class="pgc-chips"><span class="pgc-chip">Course</span>';
	if ( ! empty( $item['level'] ) ) {
		$html .= '<span class="pgc-chip pgc-chip--level">' . esc_html( ucfirst( $item['level'] ) ) . '</span>';
	}
	$html .= '</div>';
	$html .= '<h2 class="pgc-card-title"><a href="' . esc_url( $url ) . '">' . esc_html( $item['title'] ) . '</a></h2>';
	if ( '' !== $item['provider'] ) {
		$html .= '<p class="pgc-card-by">By ' . esc_html( $item['provider'] ) . '</p>';
	}
	if ( '' !== $item['summary'] ) {
		$html .= '<p class="pgc-card-summary">' . esc_html( $item['summary'] ) . '</p>';
	}
	if ( $item['skills'] ) {
		$html .= '<ul class="pgc-card-meta">';
		foreach ( $item['skills'] as $skill ) {
			$html .= '<li>' . esc_html( $skill ) . '</li>';
		}
		$html .= '</ul>';
	}
	$html .= '<p class="pgc-card-actions"><a class="pgc-link-small" href="' . esc_url( $url . 'learn/' ) . '">Start learning <span aria-hidden="true">→</span></a></p>';
	$html .= '</div></article>';
	return $html;
}

/**
 * /find/: courses from the listings service whose title, summary, skills, level or category match.
 */
add_filter( 'prospergenics_search_listings_results', function ( $items, $terms, $args = array() ) {
	$items = is_array( $items ) ? $items : array();
	if ( ! function_exists( 'prospergenics_search_text_matches' ) ) {
		return $items;
	}
	foreach ( prospergenics_courses_listings() as $course ) {
		$text = $course['title'] . ' ' . $course['summary'] . ' ' . implode( ' ', $course['skills'] ) . ' ' . $course['level'] . ' ' . $course['category'];
		if ( prospergenics_search_text_matches( $text, $terms ) ) {
			$items[] = array(
				'title'   => $course['title'],
				'url'     => home_url( '/courses/' . $course['slug'] . '/' ),
				'type'    => 'Course',
				'excerpt' => $course['summary'],
			);
		}
	}
	return $items;
}, 10, 3 );

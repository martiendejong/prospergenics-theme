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
 * The listings service's JSON as a list of courses: slug, title, summary, skills, provider, and
 * from the item's 'fields' (what the plugin's course page shows, card #7760) the instructor, the
 * number of lessons and the rest of the duration ("45 lessons · ~8h" gives 45 and "~8h"). Items
 * without a usable slug or title are dropped.
 *
 * @param string|array $json
 * @return array<int,array{slug:string,title:string,summary:string,skills:string[],provider:string,instructor:string,lessons:int,duration:string}>
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
		$course  = prospergenics_courses_listing_fields( isset( $item['fields'] ) ? $item['fields'] : array() );
		$out[]   = array(
			'slug'     => $slug,
			'title'    => $title,
			'summary'  => $summary,
			'skills'   => array_slice( $skills, 0, 4 ),
			'provider' => trim( wp_strip_all_tags( isset( $item['company'] ) ? (string) $item['company'] : '' ) ),
		) + $course;
	}
	return $out;
}

/**
 * Instructor, lesson count and duration from a listings course's 'fields' (pure). The plugin's
 * lesson pages are its 'page_titles', so their number is the lesson count; a course without them
 * (AI Software Lifecycle is one page) only has a count if its duration says "N lessons". The
 * duration minus that count is kept as it is ("~8h", or "15 min setup + ongoing").
 *
 * @param array|string $fields The item's 'fields' (an object, or that object as JSON).
 * @return array{instructor:string,lessons:int,duration:string}
 */
function prospergenics_courses_listing_fields( $fields ) {
	$fields   = is_array( $fields ) ? $fields : json_decode( (string) $fields, true );
	$fields   = is_array( $fields ) ? $fields : array();
	$text     = function ( $key ) use ( $fields ) {
		return isset( $fields[ $key ] ) && is_scalar( $fields[ $key ] ) ? trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $fields[ $key ] ) ) ) : '';
	};
	$lessons  = isset( $fields['page_titles'] ) && is_array( $fields['page_titles'] ) ? count( $fields['page_titles'] ) : 0;
	$duration = array();
	foreach ( preg_split( '/\s*·\s*/u', $text( 'duration' ) ) as $part ) {
		if ( preg_match( '/^(\d+)\s+lessons?$/i', $part, $m ) ) {
			$lessons = $lessons ? $lessons : (int) $m[1];
		} elseif ( '' !== $part ) {
			$duration[] = $part;
		}
	}
	return array(
		'instructor' => $text( 'instructor' ),
		'lessons'    => $lessons,
		'duration'   => implode( ' · ', $duration ),
	);
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
 * The slugs of the listings courses this site knows: the cached list, else the last good one. Never
 * asks the listings service (it is read when a course is saved, see data-model.php; card #6524).
 *
 * @return string[]
 */
function prospergenics_courses_listing_slugs() {
	$items = get_transient( 'pg_courses_listings' );
	$items = is_array( $items ) && $items ? $items : get_option( 'pg_courses_listings_last', array() );
	$slugs = array();
	foreach ( is_array( $items ) ? $items : array() as $item ) {
		if ( is_array( $item ) && isset( $item['slug'] ) && '' !== (string) $item['slug'] ) {
			$slugs[] = (string) $item['slug'];
		}
	}
	return $slugs;
}

/** A catalogue card for one listings course, in the same style as the theme's course cards. */
function prospergenics_courses_listing_card( array $item ) {
	$url   = home_url( '/courses/' . $item['slug'] . '/' );
	$html  = '<article class="pgc-card pgc-card--listing">';
	$html .= '<div class="pgc-card-media"><span class="pgc-thumb-placeholder pg-pattern pg-pattern--' . ( abs( crc32( $item['slug'] ) ) % 3 + 1 ) . '" aria-hidden="true"></span></div>';
	$html .= '<div class="pgc-card-body">';
	$html .= '<div class="pgc-chips"><span class="pgc-chip">Course</span></div>';
	$html .= '<h2 class="pgc-card-title"><a href="' . esc_url( $url ) . '">' . esc_html( $item['title'] ) . '</a></h2>';
	// the course's own instructor, as on its course page (Martien de Jong); else who offers it (card #7760);
	// a person in inc/people.json links to their /people/ page, anyone else stays text (card #6524)
	$by = ! empty( $item['instructor'] ) ? $item['instructor'] : $item['provider'];
	if ( '' !== $by ) {
		$html .= '<p class="pgc-card-by">By ' . ( function_exists( 'prospergenics_2026_person_link' ) ? prospergenics_2026_person_link( $by ) : esc_html( $by ) ) . '</p>';
	}
	if ( '' !== $item['summary'] ) {
		$html .= '<p class="pgc-card-summary">' . esc_html( $item['summary'] ) . '</p>';
	}
	if ( $item['skills'] ) {
		$html .= '<ul class="pgc-card-meta pgc-card-topics">';
		foreach ( $item['skills'] as $skill ) {
			$html .= '<li>' . esc_html( $skill ) . '</li>';
		}
		$html .= '</ul>';
	}
	// "45 lessons · ~8h" at the foot of the card, where the theme's cards have "4 lessons · Free"
	$meta = array();
	if ( ! empty( $item['lessons'] ) ) {
		$meta[] = prospergenics_courses_lesson_count_label( (int) $item['lessons'] );
	}
	if ( ! empty( $item['duration'] ) ) {
		$meta[] = $item['duration'];
	}
	if ( $meta ) {
		$html .= '<ul class="pgc-card-meta"><li>' . implode( '</li><li>', array_map( 'esc_html', $meta ) ) . '</li></ul>';
	}
	$html .= '<p class="pgc-card-actions"><a class="pgc-link-small" href="' . esc_url( $url . 'learn/' ) . '">Start learning <span aria-hidden="true">→</span></a></p>';
	$html .= '</div></article>';
	return $html;
}

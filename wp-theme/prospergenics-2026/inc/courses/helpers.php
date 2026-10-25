<?php
/**
 * Courses track: small, mostly pure helpers (no hooks). Unit-tested in
 * tests/test-platform-courses.php.
 */

defined( 'ABSPATH' ) || exit;

/** The course levels, slug => label. */
function prospergenics_courses_levels() {
	return array(
		'beginner'     => 'Beginner',
		'intermediate' => 'Intermediate',
		'advanced'     => 'Advanced',
	);
}

/** A valid level slug, or '' for anything else. */
function prospergenics_courses_sanitize_level( $level ) {
	$level = strtolower( trim( (string) $level ) );
	return array_key_exists( $level, prospergenics_courses_levels() ) ? $level : '';
}

/** Listing sort: 'newest' (default) or 'popular' (most enrolments). */
function prospergenics_courses_sanitize_sort( $sort ) {
	return 'popular' === $sort ? 'popular' : 'newest';
}

/** A price in whole currency units with at most two decimals; never negative. 0 = free. */
function prospergenics_courses_sanitize_price( $price ) {
	$price = is_numeric( $price ) ? (float) $price : 0.0;
	return max( 0.0, round( $price, 2 ) );
}

/** "Free", or the price with its currency code (filter: prospergenics_courses_currency). */
function prospergenics_courses_format_price( $price ) {
	$price = prospergenics_courses_sanitize_price( $price );
	if ( $price <= 0 ) {
		return 'Free';
	}
	$currency = function_exists( 'apply_filters' ) ? apply_filters( 'prospergenics_courses_currency', 'EUR' ) : 'EUR';
	$decimals = floor( $price ) == $price ? 0 : 2;
	return $currency . ' ' . number_format( $price, $decimals, '.', ',' );
}

/**
 * "What you'll learn": one outcome per line, trimmed, empty lines and bullets dropped,
 * at most 20 lines of at most 200 characters.
 *
 * @return string[]
 */
function prospergenics_courses_parse_outcomes( $text ) {
	$out = array();
	foreach ( preg_split( '/\r\n|\r|\n/', (string) $text ) as $line ) {
		$line = trim( preg_replace( '/^\s*[-*•]\s*/u', '', $line ) );
		$line = trim( strip_tags( $line ) );
		if ( '' === $line ) {
			continue;
		}
		$out[] = mb_substr( $line, 0, 200 );
		if ( count( $out ) >= 20 ) {
			break;
		}
	}
	return $out;
}

/**
 * Course progress as a whole percentage: completed lessons that still exist / all lessons.
 *
 * @param int[] $lesson_ids    The course's published lessons.
 * @param int[] $completed_ids Lessons the user has completed (may include deleted ones).
 */
function prospergenics_courses_progress_percent( array $lesson_ids, array $completed_ids ) {
	$total = count( $lesson_ids );
	if ( 0 === $total ) {
		return 0;
	}
	$done = count( array_intersect( array_map( 'intval', $lesson_ids ), array_map( 'intval', $completed_ids ) ) );
	return (int) floor( $done * 100 / $total );
}

/** Completion rate as a whole percentage (0 when nobody enrolled). */
function prospergenics_courses_rate( $completed, $enrolled ) {
	return $enrolled > 0 ? (int) round( $completed * 100 / $enrolled ) : 0;
}

/**
 * Parse a YouTube or Vimeo URL. Lessons only accept these two (oEmbed only, no self-hosted
 * video, card #6358).
 *
 * @return array{provider:string,id:string}|null
 */
function prospergenics_courses_parse_video_url( $url ) {
	$url = trim( (string) $url );
	if ( '' === $url || ! preg_match( '#^https?://#i', $url ) ) {
		return null;
	}
	$parts = parse_url( $url );
	if ( empty( $parts['host'] ) ) {
		return null;
	}
	$host = strtolower( preg_replace( '/^(www\.|m\.)/i', '', $parts['host'] ) );
	$path = isset( $parts['path'] ) ? $parts['path'] : '';
	parse_str( isset( $parts['query'] ) ? $parts['query'] : '', $query );
	$id_ok = function ( $id ) {
		return is_string( $id ) && preg_match( '/^[A-Za-z0-9_-]{6,20}$/', $id );
	};
	if ( 'youtube.com' === $host || 'youtube-nocookie.com' === $host ) {
		if ( '/watch' === $path && isset( $query['v'] ) && $id_ok( $query['v'] ) ) {
			return array( 'provider' => 'youtube', 'id' => $query['v'] );
		}
		if ( preg_match( '#^/(embed|shorts|live)/([A-Za-z0-9_-]{6,20})/?$#', $path, $m ) ) {
			return array( 'provider' => 'youtube', 'id' => $m[2] );
		}
		return null;
	}
	if ( 'youtu.be' === $host && preg_match( '#^/([A-Za-z0-9_-]{6,20})/?$#', $path, $m ) ) {
		return array( 'provider' => 'youtube', 'id' => $m[1] );
	}
	if ( ( 'vimeo.com' === $host || 'player.vimeo.com' === $host ) && preg_match( '#^/(?:video/)?([0-9]{4,12})(?:/[A-Za-z0-9]+)?/?$#', $path, $m ) ) {
		return array( 'provider' => 'vimeo', 'id' => $m[1] );
	}
	return null;
}

/** The video URL if it is a YouTube/Vimeo URL, else ''. */
function prospergenics_courses_sanitize_video_url( $url ) {
	$url = trim( (string) $url );
	return prospergenics_courses_parse_video_url( $url ) ? $url : '';
}

/**
 * A privacy-friendly embed for a YouTube/Vimeo URL, used when oEmbed discovery fails (e.g. the
 * server cannot reach the provider). Returns '' for anything else.
 */
function prospergenics_courses_video_iframe( $url, $title ) {
	$v = prospergenics_courses_parse_video_url( $url );
	if ( ! $v ) {
		return '';
	}
	$src = 'youtube' === $v['provider']
		? 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $v['id'] )
		: 'https://player.vimeo.com/video/' . rawurlencode( $v['id'] ) . '?dnt=1';
	return sprintf(
		'<iframe src="%s" title="%s" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; fullscreen" referrerpolicy="strict-origin-when-cross-origin" allowfullscreen></iframe>',
		htmlspecialchars( $src, ENT_QUOTES, 'UTF-8' ),
		htmlspecialchars( (string) $title, ENT_QUOTES, 'UTF-8' )
	);
}

/** Course slugs that would collide with the track's own /courses/<word>/ routes. */
function prospergenics_courses_reserved_slugs() {
	return array( 'my', 'verify', 'instructor', 'page', 'feed', 'embed', 'category', 'new', 'certificate', 'lessons' );
}

/** Make a course slug safe: a reserved word gets "-course" appended. */
function prospergenics_courses_safe_slug( $slug ) {
	return in_array( $slug, prospergenics_courses_reserved_slugs(), true ) ? $slug . '-course' : $slug;
}

/** Normalise a certificate code typed by a visitor: upper case, only A-Z, 0-9 and dashes. */
function prospergenics_courses_normalize_code( $code ) {
	return strtoupper( preg_replace( '/[^A-Za-z0-9-]/', '', (string) $code ) );
}

/** A new certificate code like PG-7K3M-Q9XD-2HWA (no 0/O/1/I, so it can be typed back). */
function prospergenics_courses_new_code() {
	$alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
	$groups   = array();
	for ( $g = 0; $g < 3; $g++ ) {
		$s = '';
		for ( $i = 0; $i < 4; $i++ ) {
			$s .= $alphabet[ random_int( 0, strlen( $alphabet ) - 1 ) ];
		}
		$groups[] = $s;
	}
	return 'PG-' . implode( '-', $groups );
}

/** Whether a string looks like a certificate code. */
function prospergenics_courses_is_code( $code ) {
	return (bool) preg_match( '/^PG-[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', (string) $code );
}

/** Path part of the instructor page URL: "<id>-<name-slug>" (no login names in URLs). */
function prospergenics_courses_instructor_path( $user_id, $display_name ) {
	$slug = function_exists( 'sanitize_title' ) ? sanitize_title( $display_name ) : strtolower( preg_replace( '/[^A-Za-z0-9]+/', '-', $display_name ) );
	$slug = trim( $slug, '-' );
	return (int) $user_id . ( '' !== $slug ? '-' . $slug : '' );
}

/** Completion requests a learner may send per window (REST; the lesson page form is one per click). */
const PROSPERGENICS_COURSES_RATE_LIMIT  = 60;
const PROSPERGENICS_COURSES_RATE_WINDOW = 60;

/**
 * Fixed-window rate limit. Returns the new window state, and whether this hit is allowed.
 *
 * @param array|false $state ['start' => int, 'hits' => int] or false when none.
 * @return array{allowed:bool,state:array{start:int,hits:int}}
 */
function prospergenics_courses_rate_hit( $state, $now, $limit = PROSPERGENICS_COURSES_RATE_LIMIT, $window = PROSPERGENICS_COURSES_RATE_WINDOW ) {
	if ( ! is_array( $state ) || ! isset( $state['start'], $state['hits'] ) || $now - (int) $state['start'] >= $window ) {
		$state = array( 'start' => (int) $now, 'hits' => 0 );
	}
	$allowed = $state['hits'] < $limit;
	if ( $allowed ) {
		$state['hits']++;
	}
	return array( 'allowed' => $allowed, 'state' => $state );
}

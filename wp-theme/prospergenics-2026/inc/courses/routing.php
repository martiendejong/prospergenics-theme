<?php
/**
 * Courses track: URLs, templates, assets and head tags.
 *
 *   /courses/                               archive-pg_course.php (filters ?category= &level= &sort=)
 *   /courses/<slug>/                        single-pg_course.php
 *   /courses/<slug>/lessons/<lesson-slug>/  single-pg_lesson.php
 *   /courses/<slug>/certificate/            the learner's own certificate PDF
 *   /courses/my/                            courses-my.php (my courses, progress, certificates)
 *   /courses/verify/[<code>/]               courses-verify.php (public certificate check)
 *   /courses/instructor/<id>-<name>/        courses-instructor.php
 *   /instructor/dashboard/                  courses-dashboard.php
 *   /instructor/courses/new/                courses-wizard.php
 *   /instructor/courses/<id>/edit/          courses-wizard.php
 *
 * The /courses/ archive and single course URLs come from the pg_course post type itself. The
 * extra rules are added at the top so they win over the post type's own generic rules and over
 * a WordPress page that happens to have the slug "courses".
 */

defined( 'ABSPATH' ) || exit;

/** Bump to flush rewrite rules once after a deploy that changes them. */
const PROSPERGENICS_COURSES_REWRITE_VERSION = '1';

add_filter( 'query_vars', function ( $vars ) {
	return array_merge( $vars, array( 'pg_courses_view', 'pg_courses_code', 'pg_courses_user', 'pg_courses_edit', 'pg_courses_course' ) );
} );

add_action( 'init', function () {
	$rules = array(
		'courses/my/?$'                               => 'index.php?pg_courses_view=my',
		'courses/verify/?$'                           => 'index.php?pg_courses_view=verify',
		'courses/verify/([A-Za-z0-9-]+)/?$'           => 'index.php?pg_courses_view=verify&pg_courses_code=$matches[1]',
		'courses/instructor/([0-9]+)(?:-[^/]*)?/?$'   => 'index.php?pg_courses_view=instructor&pg_courses_user=$matches[1]',
		'courses/([^/]+)/lessons/([^/]+)/?$'          => 'index.php?pg_lesson=$matches[2]&pg_courses_course=$matches[1]',
		'courses/([^/]+)/certificate/?$'              => 'index.php?pg_courses_view=certificate&pg_courses_course=$matches[1]',
		'instructor/?$'                               => 'index.php?pg_courses_view=dashboard',
		'instructor/dashboard/?$'                     => 'index.php?pg_courses_view=dashboard',
		'instructor/courses/new/?$'                   => 'index.php?pg_courses_view=wizard',
		'instructor/courses/([0-9]+)/edit/?$'         => 'index.php?pg_courses_view=wizard&pg_courses_edit=$matches[1]',
	);
	foreach ( $rules as $regex => $query ) {
		add_rewrite_rule( '^' . $regex, $query, 'top' );
	}
	if ( get_option( '_pg_courses_rewrite_version' ) !== PROSPERGENICS_COURSES_REWRITE_VERSION ) {
		// after the post types are registered (priority 5), once per version
		add_action( 'wp_loaded', function () {
			flush_rewrite_rules( false );
			update_option( '_pg_courses_rewrite_version', PROSPERGENICS_COURSES_REWRITE_VERSION, true );
		} );
	}
}, 6 );

add_action( 'after_switch_theme', function () {
	delete_option( '_pg_courses_rewrite_version' );
} );

/**
 * /courses/ must be the course archive even when something else (a page with the slug
 * "courses", or a plugin rule added later) would claim it: force the query for exactly that
 * path. Plugins that answer /courses/ themselves before WordPress parses the request (e.g. on
 * init with exit) still win; see the report for jengo-listings.
 */
add_filter( 'request', function ( $vars ) {
	$path = isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
	$home = (string) parse_url( home_url( '/' ), PHP_URL_PATH );
	$rel  = trim( substr( $path, strlen( rtrim( $home, '/' ) ) ), '/' );
	if ( preg_match( '#^courses(?:/page/([0-9]+))?$#', $rel, $m ) ) {
		$vars = array( 'post_type' => 'pg_course' );
		if ( ! empty( $m[1] ) ) {
			$vars['paged'] = (int) $m[1];
		}
	}
	return $vars;
} );

/** Lessons live under their course: /courses/<course>/lessons/<lesson>/. */
add_filter( 'post_type_link', function ( $link, $post ) {
	if ( 'pg_lesson' !== $post->post_type ) {
		return $link;
	}
	$course_id = prospergenics_courses_course_of( $post );
	if ( ! $course_id || '' === $post->post_name ) {
		return $link;
	}
	return home_url( '/courses/' . get_post_field( 'post_name', $course_id ) . '/lessons/' . $post->post_name . '/' );
}, 10, 2 );

/** URL of the public instructor page. */
function prospergenics_courses_instructor_url( $user_id ) {
	$user = get_userdata( $user_id );
	return $user ? home_url( '/courses/instructor/' . prospergenics_courses_instructor_path( $user->ID, $user->display_name ) . '/' ) : '';
}

function prospergenics_courses_wizard_url( $course_id = 0 ) {
	return $course_id ? home_url( '/instructor/courses/' . (int) $course_id . '/edit/' ) : home_url( '/instructor/courses/new/' );
}

/** The track's own view for this request ('' when none). */
function prospergenics_courses_view() {
	$view = get_query_var( 'pg_courses_view' );
	return in_array( $view, array( 'my', 'verify', 'instructor', 'certificate', 'dashboard', 'wizard' ), true ) ? $view : '';
}

/** Whether this request is one of the track's pages (for assets and head tags). */
function prospergenics_courses_is_course_page() {
	return is_post_type_archive( 'pg_course' ) || is_singular( array( 'pg_course', 'pg_lesson' ) ) || '' !== prospergenics_courses_view();
}

// Our own views are not the blog home.
add_action( 'parse_query', function ( $q ) {
	if ( $q->is_main_query() && $q->get( 'pg_courses_view' ) ) {
		$q->is_home = false;
		$q->is_404  = false;
	}
} );

/** Main query of /courses/: filters, sort, 12 per page. */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->get( 'pg_courses_view' ) ) {
		// Nothing to list; skip the default posts query.
		$q->set( 'post_type', 'pg_course' );
		$q->set( 'posts_per_page', 1 );
		$q->set( 'no_found_rows', true );
		return;
	}
	if ( ! $q->is_post_type_archive( 'pg_course' ) ) {
		return;
	}
	$f = prospergenics_courses_current_filters();
	$q->set( 'posts_per_page', 12 );
	if ( $f['category'] ) {
		$q->set( 'tax_query', array( array( 'taxonomy' => 'pg_course_category', 'field' => 'slug', 'terms' => $f['category'], 'include_children' => true ) ) );
	}
	$meta = array();
	if ( $f['level'] ) {
		$meta['level'] = array( 'key' => '_pg_courses_level', 'value' => $f['level'] );
	}
	if ( 'popular' === $f['sort'] ) {
		$meta['popular'] = array( 'key' => '_pg_courses_enrolment_count', 'type' => 'NUMERIC', 'compare' => 'EXISTS' );
		$q->set( 'orderby', array( 'popular' => 'DESC', 'date' => 'DESC' ) );
	} else {
		$q->set( 'orderby', array( 'date' => 'DESC', 'ID' => 'DESC' ) );
	}
	if ( $meta ) {
		$q->set( 'meta_query', array_merge( array( 'relation' => 'AND' ), $meta ) );
	}
} );

/** @return array{category:string,level:string,sort:string} the archive filters from the query string */
function prospergenics_courses_current_filters() {
	// phpcs:disable WordPress.Security.NonceVerification -- read-only GET filters
	$cat = isset( $_GET['category'] ) ? sanitize_title( wp_unslash( $_GET['category'] ) ) : '';
	if ( $cat && ! term_exists( $cat, 'pg_course_category' ) ) {
		$cat = '';
	}
	return array(
		'category' => $cat,
		'level'    => isset( $_GET['level'] ) ? prospergenics_courses_sanitize_level( wp_unslash( $_GET['level'] ) ) : '',
		'sort'     => isset( $_GET['sort'] ) ? prospergenics_courses_sanitize_sort( wp_unslash( $_GET['sort'] ) ) : 'newest',
	);
	// phpcs:enable
}

/**
 * Serve the track's own views; keep lessons on their canonical URL and away from embeds.
 * Runs before redirect_canonical (10) and the theme's other template_redirect hooks.
 */
add_action( 'template_redirect', function () {
	if ( is_singular( 'pg_lesson' ) ) {
		$lesson = get_queried_object();
		$course_id = prospergenics_courses_course_of( $lesson );
		// Lessons of unpublished courses or modules do not exist for anyone who cannot edit them.
		$hidden = $course_id && ! current_user_can( 'edit_post', $lesson->ID )
			&& ( 'publish' !== get_post_status( $course_id ) || 'publish' !== get_post_status( $lesson->post_parent ) );
		if ( is_embed() || ! $course_id || $hidden ) {
			prospergenics_courses_404();
			return;
		}
		$want = (string) parse_url( get_permalink( $lesson ), PHP_URL_PATH );
		$have = isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ), PHP_URL_PATH ) : '';
		if ( $want !== $have ) {
			wp_safe_redirect( get_permalink( $lesson ), 301 );
			exit;
		}
		return;
	}
	$view = prospergenics_courses_view();
	if ( '' === $view ) {
		return;
	}
	if ( 'certificate' === $view ) {
		$course = get_page_by_path( sanitize_title( get_query_var( 'pg_courses_course' ) ), OBJECT, 'pg_course' );
		if ( ! $course || 'publish' !== $course->post_status ) {
			prospergenics_courses_404();
			return;
		}
		prospergenics_courses_serve_certificate( $course );
	}
	$templates = array(
		'my'         => 'courses-my.php',
		'verify'     => 'courses-verify.php',
		'instructor' => 'courses-instructor.php',
		'dashboard'  => 'courses-dashboard.php',
		'wizard'     => 'courses-wizard.php',
	);
	status_header( 200 );
	nocache_headers();
	include get_template_directory() . '/' . $templates[ $view ];
	exit;
}, 5 );

/** Turn the current request into a 404 rendered by the theme's 404.php. */
function prospergenics_courses_404() {
	global $wp_query;
	$wp_query->set_404();
	status_header( 404 );
	nocache_headers();
}

/** Titles for the track's own views (the post type pages get theirs from WordPress). */
add_filter( 'document_title_parts', function ( $parts ) {
	$titles = array(
		'my'         => 'My courses',
		'verify'     => 'Verify a certificate',
		'dashboard'  => 'Instructor dashboard',
		'wizard'     => get_query_var( 'pg_courses_edit' ) ? 'Edit course' : 'Create a course',
	);
	$view = prospergenics_courses_view();
	if ( isset( $titles[ $view ] ) ) {
		$parts['title'] = $titles[ $view ];
	} elseif ( 'instructor' === $view ) {
		$user           = get_userdata( (int) get_query_var( 'pg_courses_user' ) );
		$parts['title'] = $user ? $user->display_name . ' – Instructor' : 'Instructor';
	} elseif ( is_post_type_archive( 'pg_course' ) ) {
		$parts['title'] = 'Courses';
	}
	return $parts;
} );

/** Learning pages and personal pages stay out of search engines. */
add_filter( 'wp_robots', function ( $robots ) {
	$view = prospergenics_courses_view();
	if ( is_singular( 'pg_lesson' ) || ( '' !== $view && 'instructor' !== $view ) ) {
		$robots['noindex']  = true;
		$robots['nofollow'] = true;
		unset( $robots['max-image-preview'] );
	}
	return $robots;
}, 20 );

// Lesson pages: no sharing/description tags from inc/seo.php, which would print the start of
// the (gated) lesson text into the page head.
add_filter( 'prospergenics_2026_seo_plugin_active', function ( $active ) {
	return $active || is_singular( 'pg_lesson' );
} );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! prospergenics_courses_is_course_page() ) {
		return;
	}
	$dir = get_template_directory();
	$uri = get_template_directory_uri();
	$css = '/assets/platform/courses.css';
	$js  = '/assets/platform/courses.js';
	wp_enqueue_style( 'pg-courses', $uri . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
	wp_enqueue_script( 'pg-courses', $uri . $js, array(), file_exists( $dir . $js ) ? (string) filemtime( $dir . $js ) : null, true );
	if ( 'wizard' === prospergenics_courses_view() && is_user_logged_in() ) {
		wp_add_inline_script( 'pg-courses', 'window.pgCoursesWizard = ' . wp_json_encode( prospergenics_courses_wizard_config() ) . ';', 'before' );
	}
} );

/** Description and schema.org Course for /courses/ and course pages. */
add_action( 'wp_head', function () {
	if ( is_post_type_archive( 'pg_course' ) && function_exists( 'prospergenics_2026_seo_plugin_active' ) && ! prospergenics_2026_seo_plugin_active() ) {
		echo '<meta name="description" content="' . esc_attr( 'Courses from the ProsperGenics community: learn by doing, track your progress and earn a verifiable certificate.' ) . '" />' . "\n";
	}
	if ( ! is_singular( 'pg_course' ) ) {
		return;
	}
	$course     = get_queried_object();
	$instructor = get_userdata( (int) $course->post_author );
	$schema     = array(
		'@context'    => 'https://schema.org',
		'@type'       => 'Course',
		'name'        => get_the_title( $course ),
		'description' => wp_strip_all_tags( has_excerpt( $course ) ? get_the_excerpt( $course ) : wp_trim_words( $course->post_content, 40 ) ),
		'url'         => get_permalink( $course ),
		'provider'    => array( '@type' => 'Organization', 'name' => 'ProsperGenics', 'url' => home_url( '/' ) ),
		'offers'      => array( '@type' => 'Offer', 'category' => prospergenics_courses_is_free( $course->ID ) ? 'Free' : 'Paid', 'price' => prospergenics_courses_price( $course->ID ), 'priceCurrency' => apply_filters( 'prospergenics_courses_currency', 'EUR' ) ),
	);
	if ( $instructor ) {
		$schema['instructor'] = array( '@type' => 'Person', 'name' => $instructor->display_name );
	}
	if ( has_post_thumbnail( $course ) ) {
		$schema['image'] = get_the_post_thumbnail_url( $course, 'large' );
	}
	echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "</script>\n";
}, 3 );

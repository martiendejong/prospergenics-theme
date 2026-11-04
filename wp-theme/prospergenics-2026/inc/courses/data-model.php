<?php
/**
 * Courses track: data model (card #6355).
 *
 *   pg_course   public, /courses/<slug>/, archive /courses/. Instructor = post_author (a WP user).
 *               Meta: _pg_courses_level, _pg_courses_price (0 = free), _pg_courses_outcomes
 *               ("what you'll learn", one per line), _pg_courses_enrolment_count (for "popular").
 *               Thumbnail = featured image. Taxonomy pg_course_category.
 *   pg_module   post_parent = course, order = menu_order.
 *   pg_lesson   post_parent = module, order = menu_order, served at
 *               /courses/<course>/lessons/<lesson>/. Meta: _pg_courses_video_url (YouTube/Vimeo,
 *               or a video file on this site), _pg_courses_quiz (JSON, quiz.php; never in REST).
 *               Notes PDF at /courses/<course>/lessons/<lesson>/notes/ (notes.php).
 *   pg_enrolment  (internal) post_author = learner, post_parent = course, post_date = enrolled_at.
 *                 Meta: _pg_courses_completed (lesson id => unix time), _pg_courses_last_lesson,
 *                 _pg_courses_completed_at, _pg_courses_quiz_attempts (lesson id => attempts).
 *   pg_certificate (internal) see certificate.php.
 *
 * Capabilities: the three content types share the capability type pg_course/pg_courses
 * (edit_pg_courses, publish_pg_courses, edit_others_pg_courses, ...). Until the instructor role
 * from card #6341 exists (blocked on jengo-iam), administrators and editors get all of them via
 * a user_has_cap filter (nothing is written to the roles table). A future instructor role only
 * needs edit_pg_courses, publish_pg_courses, edit_published_pg_courses, delete_pg_courses and
 * delete_published_pg_courses: in the meantime, members whose "Apply to teach" request an
 * administrator approved (audit ARC-8, inc/profiles/applications.php) carry the user meta
 * _pg_courses_instructor, and the same filter gives them exactly those five. This is interim:
 * when jengo-iam (#6341) brings the instructor role, the flag becomes that role.
 */

defined( 'ABSPATH' ) || exit;

/** The primitive capabilities of the pg_course capability type. */
function prospergenics_courses_primitive_caps() {
	return array(
		'edit_pg_courses', 'edit_others_pg_courses', 'publish_pg_courses', 'read_private_pg_courses',
		'delete_pg_courses', 'delete_private_pg_courses', 'delete_published_pg_courses',
		'delete_others_pg_courses', 'edit_private_pg_courses', 'edit_published_pg_courses',
	);
}

/** The capabilities an approved instructor gets (their own courses, nobody else's). */
function prospergenics_courses_instructor_caps() {
	return array( 'edit_pg_courses', 'publish_pg_courses', 'edit_published_pg_courses', 'delete_pg_courses', 'delete_published_pg_courses' );
}

/** User meta that marks an approved instructor (interim, until jengo-iam #6341). */
const PROSPERGENICS_COURSES_INSTRUCTOR_META = '_pg_courses_instructor';

/** Whether the user may use the instructor features (wizard, dashboard). */
function prospergenics_courses_is_instructor( $user_id = 0 ) {
	return $user_id ? user_can( $user_id, 'edit_pg_courses' ) : current_user_can( 'edit_pg_courses' );
}

add_filter( 'user_has_cap', function ( $allcaps, $caps = array(), $args = array(), $user = null ) {
	// Administrators and editors (everyone who may edit others' posts) teach for now.
	if ( ! empty( $allcaps['edit_others_posts'] ) ) {
		foreach ( prospergenics_courses_primitive_caps() as $cap ) {
			$allcaps[ $cap ] = true;
		}
	} elseif ( is_object( $user ) && ! empty( $user->ID ) && get_user_meta( (int) $user->ID, PROSPERGENICS_COURSES_INSTRUCTOR_META, true ) ) {
		// approved instructors (ARC-8): their own courses only
		foreach ( prospergenics_courses_instructor_caps() as $cap ) {
			$allcaps[ $cap ] = true;
		}
	}
	return $allcaps;
}, 10, 4 );

add_action( 'init', function () {
	$caps = array(
		'capability_type' => array( 'pg_course', 'pg_courses' ),
		'map_meta_cap'    => true,
	);

	register_post_type( 'pg_course', $caps + array(
		'labels'        => array(
			'name'               => 'Courses',
			'singular_name'      => 'Course',
			'add_new_item'       => 'Add new course',
			'edit_item'          => 'Edit course',
			'view_item'          => 'View course',
			'all_items'          => 'All courses',
			'search_items'       => 'Search courses',
			'not_found'          => 'No courses found',
			'menu_name'          => 'Courses',
		),
		'public'        => true,
		'has_archive'   => 'courses',
		'rewrite'       => array( 'slug' => 'courses', 'with_front' => false, 'feeds' => false, 'pages' => true ),
		'menu_icon'     => 'dashicons-welcome-learn-more',
		'menu_position' => 21,
		'supports'      => array( 'title', 'editor', 'excerpt', 'thumbnail', 'author', 'revisions' ),
		'show_in_rest'  => true,
		'rest_base'     => 'pg_courses',
		'taxonomies'    => array( 'pg_course_category' ),
	) );

	register_post_type( 'pg_module', $caps + array(
		'labels'              => array(
			'name'          => 'Modules',
			'singular_name' => 'Module',
			'add_new_item'  => 'Add new module',
			'edit_item'     => 'Edit module',
			'all_items'     => 'Modules',
		),
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => 'edit.php?post_type=pg_course',
		'publicly_queryable'  => false,
		'exclude_from_search' => true,
		'rewrite'             => false,
		'query_var'           => false,
		'supports'            => array( 'title', 'page-attributes', 'author' ),
		'show_in_rest'        => true,
		'rest_base'           => 'pg_modules',
	) );

	register_post_type( 'pg_lesson', $caps + array(
		'labels'              => array(
			'name'          => 'Lessons',
			'singular_name' => 'Lesson',
			'add_new_item'  => 'Add new lesson',
			'edit_item'     => 'Edit lesson',
			'view_item'     => 'View lesson',
			'all_items'     => 'Lessons',
		),
		// Not "public": no archive, no sitemap, no search. Single lessons are still served
		// (publicly_queryable) at the URL built in routing.php, gated to enrolled learners.
		'public'              => false,
		'show_ui'             => true,
		'show_in_menu'        => 'edit.php?post_type=pg_course',
		'publicly_queryable'  => true,
		'exclude_from_search' => true,
		'rewrite'             => false,
		'query_var'           => 'pg_lesson',
		'supports'            => array( 'title', 'editor', 'page-attributes', 'author', 'revisions' ),
		'show_in_rest'        => true,
		'rest_base'           => 'pg_lessons',
	) );

	// Internal records: no UI, no REST (the track's own endpoints expose them to their owner).
	foreach ( array( 'pg_enrolment' => 'Enrolment', 'pg_certificate' => 'Certificate' ) as $type => $label ) {
		register_post_type( $type, array(
			'label'               => $label . 's',
			'public'              => false,
			'show_ui'             => false,
			'publicly_queryable'  => false,
			'exclude_from_search' => true,
			'rewrite'             => false,
			'query_var'           => false,
			'show_in_rest'        => false,
			'supports'            => array( 'title', 'author' ),
			'capability_type'     => array( 'pg_course', 'pg_courses' ),
			'map_meta_cap'        => true,
		) );
	}

	register_taxonomy( 'pg_course_category', array( 'pg_course' ), array(
		'labels'             => array(
			'name'          => 'Course categories',
			'singular_name' => 'Course category',
			'menu_name'     => 'Categories',
			'add_new_item'  => 'Add new course category',
		),
		'public'             => true,
		'publicly_queryable' => false, // filtered on /courses/?category=<slug> instead of its own archive
		'hierarchical'       => true,
		'rewrite'            => false,
		'query_var'          => false,
		'show_admin_column'  => true,
		'show_in_rest'       => true,
		'capabilities'       => array(
			'manage_terms' => 'manage_categories',
			'edit_terms'   => 'manage_categories',
			'delete_terms' => 'manage_categories',
			'assign_terms' => 'edit_pg_courses',
		),
	) );

	$auth = function ( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	};
	register_post_meta( 'pg_course', '_pg_courses_level', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'prospergenics_courses_sanitize_level', 'auth_callback' => $auth ) );
	register_post_meta( 'pg_course', '_pg_courses_price', array( 'type' => 'number', 'single' => true, 'show_in_rest' => true, 'default' => 0, 'sanitize_callback' => 'prospergenics_courses_sanitize_price', 'auth_callback' => $auth ) );
	register_post_meta( 'pg_course', '_pg_courses_outcomes', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'sanitize_textarea_field', 'auth_callback' => $auth ) );
	register_post_meta( 'pg_lesson', '_pg_courses_video_url', array( 'type' => 'string', 'single' => true, 'show_in_rest' => true, 'sanitize_callback' => 'prospergenics_courses_sanitize_video_url', 'auth_callback' => $auth ) );
}, 5 );

/* ------------------------------------------------------------------ queries */

/** @return WP_Post[] the course's modules in order (published only unless $all). */
function prospergenics_courses_get_modules( $course_id, $all = false ) {
	if ( ! $course_id ) {
		return array();
	}
	return get_posts( array(
		'post_type'        => 'pg_module',
		'post_parent'      => (int) $course_id,
		'post_status'      => $all ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'publish',
		'posts_per_page'   => 200,
		'orderby'          => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
		'suppress_filters' => true,
	) );
}

/** @return WP_Post[] lessons of the given modules, in order. */
function prospergenics_courses_get_lessons_of_modules( array $module_ids, $all = false ) {
	if ( ! $module_ids ) {
		return array();
	}
	return get_posts( array(
		'post_type'        => 'pg_lesson',
		'post_parent__in'  => array_map( 'intval', $module_ids ),
		'post_status'      => $all ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'publish',
		'posts_per_page'   => 1000,
		'orderby'          => array( 'menu_order' => 'ASC', 'ID' => 'ASC' ),
		'suppress_filters' => true,
	) );
}

/**
 * The course outline: modules with their lessons, in order.
 *
 * @return array<int,array{module:WP_Post,lessons:WP_Post[]}>
 */
function prospergenics_courses_curriculum( $course_id, $all = false ) {
	$key    = (int) $course_id . ( $all ? ':all' : '' );
	$cached = wp_cache_get( $key, 'pg_courses_curriculum' );
	if ( false !== $cached ) {
		return $cached;
	}
	$modules = prospergenics_courses_get_modules( $course_id, $all );
	$by      = array();
	foreach ( $modules as $m ) {
		$by[ $m->ID ] = array( 'module' => $m, 'lessons' => array() );
	}
	foreach ( prospergenics_courses_get_lessons_of_modules( array_keys( $by ), $all ) as $l ) {
		$by[ $l->post_parent ]['lessons'][] = $l;
	}
	$out = array_values( $by );
	wp_cache_set( $key, $out, 'pg_courses_curriculum' );
	return $out;
}

/** Forget the cached outline of a course (after its modules or lessons changed). */
function prospergenics_courses_flush_curriculum( $course_id ) {
	wp_cache_delete( (int) $course_id, 'pg_courses_curriculum' );
	wp_cache_delete( (int) $course_id . ':all', 'pg_courses_curriculum' );
}

add_action( 'init', function () {
	wp_cache_add_non_persistent_groups( array( 'pg_courses_curriculum' ) );
}, 1 );

/** @return WP_Post[] the course's published lessons, in curriculum order. */
function prospergenics_courses_flat_lessons( $course_id ) {
	$out = array();
	foreach ( prospergenics_courses_curriculum( $course_id ) as $m ) {
		foreach ( $m['lessons'] as $l ) {
			$out[] = $l;
		}
	}
	return $out;
}

/** @return int[] */
function prospergenics_courses_lesson_ids( $course_id ) {
	return array_map( function ( $p ) { return (int) $p->ID; }, prospergenics_courses_flat_lessons( $course_id ) );
}

/** The course a module or lesson belongs to (0 when none). */
function prospergenics_courses_course_of( $post ) {
	$post = get_post( $post );
	if ( ! $post ) {
		return 0;
	}
	if ( 'pg_course' === $post->post_type ) {
		return (int) $post->ID;
	}
	if ( 'pg_module' === $post->post_type ) {
		return 'pg_course' === get_post_type( $post->post_parent ) ? (int) $post->post_parent : 0;
	}
	if ( 'pg_lesson' === $post->post_type && $post->post_parent ) {
		$module = get_post( $post->post_parent );
		return $module && 'pg_module' === $module->post_type && 'pg_course' === get_post_type( $module->post_parent ) ? (int) $module->post_parent : 0;
	}
	return 0;
}

function prospergenics_courses_level( $course_id ) {
	return prospergenics_courses_sanitize_level( get_post_meta( $course_id, '_pg_courses_level', true ) );
}

function prospergenics_courses_price( $course_id ) {
	return prospergenics_courses_sanitize_price( get_post_meta( $course_id, '_pg_courses_price', true ) );
}

function prospergenics_courses_is_free( $course_id ) {
	return prospergenics_courses_price( $course_id ) <= 0;
}

/** @return WP_Term[] */
function prospergenics_courses_categories( $course_id ) {
	$terms = get_the_terms( $course_id, 'pg_course_category' );
	return is_array( $terms ) ? $terms : array();
}

/* ------------------------------------------------------------------ slugs, cascade, save hooks */

add_filter( 'wp_unique_post_slug', function ( $slug, $post_id, $status, $type ) {
	return 'pg_course' === $type ? prospergenics_courses_safe_slug( $slug ) : $slug;
}, 10, 4 );

// Deleting a course (not trashing) removes its modules, lessons and enrolments; issued
// certificates stay valid (they keep a snapshot of the course title).
add_action( 'before_delete_post', function ( $post_id ) {
	$type = get_post_type( $post_id );
	if ( 'pg_course' === $type ) {
		$children = get_posts( array( 'post_type' => array( 'pg_module', 'pg_enrolment' ), 'post_parent' => $post_id, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) );
		foreach ( $children as $child ) {
			wp_delete_post( $child, true );
		}
	} elseif ( 'pg_module' === $type ) {
		$lessons = get_posts( array( 'post_type' => 'pg_lesson', 'post_parent' => $post_id, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true ) );
		foreach ( $lessons as $lesson ) {
			wp_delete_post( $lesson, true );
		}
	}
} );

// Search-index hook (#6378) and defaults, whichever way the content was saved (admin, REST, wizard).
foreach ( array( 'pg_course', 'pg_module', 'pg_lesson' ) as $prospergenics_courses_type ) {
	add_action( 'save_post_' . $prospergenics_courses_type, function ( $post_id, $post ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) || 'auto-draft' === $post->post_status ) {
			return;
		}
		if ( 'pg_course' === $post->post_type && '' === get_post_meta( $post_id, '_pg_courses_enrolment_count', true ) ) {
			update_post_meta( $post_id, '_pg_courses_enrolment_count', 0 );
		}
		prospergenics_courses_flush_curriculum( prospergenics_courses_course_of( $post ) );
		do_action( 'prospergenics_platform_content_saved', $post_id, $post->post_type );
	}, 20, 2 );
}
unset( $prospergenics_courses_type );

/* ------------------------------------------------------------------ REST redaction (wp/v2) */

/**
 * Whether the current user may read a lesson's content: everyone who can edit it, and learners
 * enrolled in its (published) course.
 */
function prospergenics_courses_can_view_lesson( $lesson, $user_id = null ) {
	$lesson  = get_post( $lesson );
	$user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
	if ( ! $lesson || 'pg_lesson' !== $lesson->post_type ) {
		return false;
	}
	if ( $user_id && user_can( $user_id, 'edit_post', $lesson->ID ) ) {
		return true;
	}
	$course_id = prospergenics_courses_course_of( $lesson );
	return $course_id && 'publish' === $lesson->post_status && 'publish' === get_post_status( $course_id )
		&& 'publish' === get_post_status( $lesson->post_parent )
		&& prospergenics_courses_is_enrolled( $user_id, $course_id );
}

// The core wp/v2/pg_lessons endpoint (used by the block editor) must not leak gated content.
add_filter( 'rest_prepare_pg_lesson', function ( $response, $post ) {
	if ( prospergenics_courses_can_view_lesson( $post ) ) {
		return $response;
	}
	$data = $response->get_data();
	foreach ( array( 'content', 'excerpt' ) as $field ) {
		if ( isset( $data[ $field ] ) ) {
			$data[ $field ] = array( 'rendered' => '', 'protected' => true );
		}
	}
	if ( isset( $data['meta']['_pg_courses_video_url'] ) ) {
		$data['meta']['_pg_courses_video_url'] = '';
	}
	$response->set_data( $data );
	return $response;
}, 10, 2 );

// No oEmbed card for lessons either (it would show an excerpt).
add_filter( 'oembed_request_post_id', function ( $post_id ) {
	return 'pg_lesson' === get_post_type( $post_id ) ? 0 : $post_id;
} );

/* ------------------------------------------------------------------ admin meta boxes */

add_action( 'add_meta_boxes', function () {
	add_meta_box( 'pg_courses_details', 'Course details', 'prospergenics_courses_course_box', 'pg_course', 'side' );
	add_meta_box( 'pg_courses_outcomes', 'What you\'ll learn', 'prospergenics_courses_outcomes_box', 'pg_course', 'normal' );
	add_meta_box( 'pg_courses_module', 'Course', 'prospergenics_courses_module_box', 'pg_module', 'side', 'high' );
	add_meta_box( 'pg_courses_lesson', 'Module and video', 'prospergenics_courses_lesson_box', 'pg_lesson', 'side', 'high' );
} );

function prospergenics_courses_course_box( $post ) {
	wp_nonce_field( 'pg_courses_meta', '_pg_courses_meta_nonce' );
	$level = prospergenics_courses_level( $post->ID );
	echo '<p><label for="pg-courses-level"><strong>Level</strong></label><br /><select id="pg-courses-level" name="pg_courses_level"><option value="">—</option>';
	foreach ( prospergenics_courses_levels() as $slug => $label ) {
		printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $level, $slug, false ), esc_html( $label ) );
	}
	echo '</select></p>';
	printf( '<p><label for="pg-courses-price"><strong>Price</strong> (0 = free)</label><br /><input id="pg-courses-price" type="number" min="0" step="0.01" name="pg_courses_price" value="%s" /></p>', esc_attr( prospergenics_courses_price( $post->ID ) ) );
	echo '<p class="description">The instructor is the post author (Author box).</p>';
}

function prospergenics_courses_outcomes_box( $post ) {
	printf( '<label for="pg-courses-outcomes" class="screen-reader-text">What you\'ll learn</label><textarea id="pg-courses-outcomes" name="pg_courses_outcomes" rows="6" style="width:100%%">%s</textarea><p class="description">One learning outcome per line.</p>', esc_textarea( get_post_meta( $post->ID, '_pg_courses_outcomes', true ) ) );
}

function prospergenics_courses_module_box( $post ) {
	wp_nonce_field( 'pg_courses_meta', '_pg_courses_meta_nonce' );
	$courses = get_posts( array( 'post_type' => 'pg_course', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC' ) );
	echo '<p><label for="pg-courses-parent"><strong>Belongs to course</strong></label><br /><select id="pg-courses-parent" name="pg_courses_parent" style="width:100%"><option value="0">—</option>';
	foreach ( $courses as $c ) {
		printf( '<option value="%d"%s>%s</option>', $c->ID, selected( $post->post_parent, $c->ID, false ), esc_html( get_the_title( $c ) ) );
	}
	echo '</select></p><p class="description">Order within the course: Module attributes → Order.</p>';
}

function prospergenics_courses_lesson_box( $post ) {
	wp_nonce_field( 'pg_courses_meta', '_pg_courses_meta_nonce' );
	$modules = get_posts( array( 'post_type' => 'pg_module', 'post_status' => array( 'publish', 'draft', 'pending', 'private' ), 'posts_per_page' => 1000, 'orderby' => array( 'post_parent' => 'ASC', 'menu_order' => 'ASC' ) ) );
	echo '<p><label for="pg-courses-parent"><strong>Module</strong></label><br /><select id="pg-courses-parent" name="pg_courses_parent" style="width:100%"><option value="0">—</option>';
	foreach ( $modules as $m ) {
		$course = $m->post_parent ? get_the_title( $m->post_parent ) : '(no course)';
		printf( '<option value="%d"%s>%s</option>', $m->ID, selected( $post->post_parent, $m->ID, false ), esc_html( $course . ' → ' . get_the_title( $m ) ) );
	}
	echo '</select></p>';
	printf( '<p><label for="pg-courses-video"><strong>Video URL</strong> (YouTube, Vimeo or a video file from the media library)</label><br /><input id="pg-courses-video" type="url" name="pg_courses_video_url" value="%s" style="width:100%%" /></p>', esc_attr( get_post_meta( $post->ID, '_pg_courses_video_url', true ) ) );
	// Quiz (card #6461): edited in the course wizard, which has the question editor.
	$quiz   = prospergenics_courses_get_quiz( $post->ID, true );
	$course = prospergenics_courses_course_of( $post );
	$count  = $quiz ? count( $quiz['questions'] ) : 0;
	echo '<p><strong>Quiz</strong><br />' . esc_html( $count ? sprintf( _n( '%1$d question, pass mark %2$d%%.', '%1$d questions, pass mark %2$d%%.', $count, 'prospergenics-2026' ), $count, $quiz['pass_mark'] ) : 'No quiz.' );
	if ( $course ) {
		echo ' <a href="' . esc_url( prospergenics_courses_wizard_url( $course ) ) . '">' . esc_html( $count ? 'Edit the quiz in the course wizard' : 'Add a quiz in the course wizard' ) . '</a>';
	}
	echo '</p>';
}

// Save the meta boxes. post_parent goes in through wp_insert_post_data so it is saved with the post.
add_filter( 'wp_insert_post_data', function ( $data, $postarr ) {
	if ( ! in_array( $data['post_type'], array( 'pg_module', 'pg_lesson' ), true ) || ! isset( $_POST['_pg_courses_meta_nonce'], $_POST['pg_courses_parent'] ) ) {
		return $data;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pg_courses_meta_nonce'] ) ), 'pg_courses_meta' ) ) {
		return $data;
	}
	$parent      = absint( $_POST['pg_courses_parent'] );
	$want        = 'pg_module' === $data['post_type'] ? 'pg_course' : 'pg_module';
	$can_attach  = ! $parent || ( get_post_type( $parent ) === $want && current_user_can( 'edit_post', $parent ) );
	if ( $can_attach ) {
		$data['post_parent'] = $parent;
	}
	return $data;
}, 10, 2 );

add_action( 'save_post', function ( $post_id, $post ) {
	if ( ! isset( $_POST['_pg_courses_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_pg_courses_meta_nonce'] ) ), 'pg_courses_meta' ) ) {
		return;
	}
	if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}
	if ( 'pg_course' === $post->post_type ) {
		if ( isset( $_POST['pg_courses_level'] ) ) {
			update_post_meta( $post_id, '_pg_courses_level', prospergenics_courses_sanitize_level( wp_unslash( $_POST['pg_courses_level'] ) ) );
		}
		if ( isset( $_POST['pg_courses_price'] ) ) {
			update_post_meta( $post_id, '_pg_courses_price', prospergenics_courses_sanitize_price( wp_unslash( $_POST['pg_courses_price'] ) ) );
		}
		if ( isset( $_POST['pg_courses_outcomes'] ) ) {
			update_post_meta( $post_id, '_pg_courses_outcomes', implode( "\n", prospergenics_courses_parse_outcomes( wp_unslash( $_POST['pg_courses_outcomes'] ) ) ) );
		}
	} elseif ( 'pg_lesson' === $post->post_type && isset( $_POST['pg_courses_video_url'] ) ) {
		update_post_meta( $post_id, '_pg_courses_video_url', prospergenics_courses_sanitize_video_url( esc_url_raw( wp_unslash( $_POST['pg_courses_video_url'] ) ) ) );
	}
}, 10, 2 );

// Admin list: show each module's / lesson's course.
foreach ( array( 'pg_module', 'pg_lesson' ) as $prospergenics_courses_type ) {
	add_filter( "manage_{$prospergenics_courses_type}_posts_columns", function ( $cols ) {
		$cols['pg_course'] = 'Course';
		return $cols;
	} );
	add_action( "manage_{$prospergenics_courses_type}_posts_custom_column", function ( $col, $post_id ) {
		if ( 'pg_course' === $col ) {
			$course = prospergenics_courses_course_of( $post_id );
			echo $course ? esc_html( get_the_title( $course ) ) : '—';
		}
	}, 10, 2 );
}
unset( $prospergenics_courses_type );

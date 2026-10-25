<?php
/**
 * Courses track: REST API, namespace prospergenics/v1 (cards #6355, #6359, #6360, #6361, #6363).
 *
 * Courses
 *   GET    /courses                         published courses (?category=&level=&sort=&instructor=&page=&per_page=)
 *                                           ?status=draft|any needs edit_pg_courses (own courses; all with edit_others)
 *   POST   /courses                         create (edit_pg_courses; publishing needs publish_pg_courses)
 *   GET    /courses/{id}                    published, or editable by the current user
 *   PUT    /courses/{id}                    update (edit_post)
 *   DELETE /courses/{id}                    trash; ?force=true deletes (delete_post)
 *   PUT    /courses/{id}/curriculum         replace modules + lessons in one go (wizard autosave)
 *   POST   /courses/{id}/thumbnail          upload the course image (multipart "file")
 * Modules and lessons
 *   GET|POST           /courses/{id}/modules
 *   GET|PUT|DELETE     /courses/modules/{id}
 *   GET|POST           /courses/modules/{id}/lessons
 *   GET|PUT|DELETE     /courses/lessons/{id}   (content only for enrolled learners and editors)
 * The current user's own learning (logged in; always acts on the current user)
 *   GET|POST|DELETE    /courses/{id}/enrolment
 *   POST|DELETE        /courses/lessons/{id}/completion
 *   GET                /courses/{id}/certificate
 *   GET                /courses/me
 * Public
 *   GET                /courses/certificates/{code}   certificate verification
 * Instructors
 *   GET                /courses/instructor/stats
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_COURSES_NS = 'prospergenics/v1';

/* ------------------------------------------------------------------ permission callbacks */

/** Public, read-only data (published courses, certificate verification). */
function prospergenics_courses_rest_public_read( WP_REST_Request $request ) {
	return 'GET' === $request->get_method() || 'HEAD' === $request->get_method();
}

function prospergenics_courses_rest_logged_in() {
	return is_user_logged_in() ? true : new WP_Error( 'rest_not_logged_in', 'You must be logged in.', array( 'status' => 401 ) );
}

function prospergenics_courses_rest_instructor() {
	if ( ! is_user_logged_in() ) {
		return new WP_Error( 'rest_not_logged_in', 'You must be logged in.', array( 'status' => 401 ) );
	}
	return current_user_can( 'edit_pg_courses' ) ? true : new WP_Error( 'rest_forbidden', 'Only instructors can do this.', array( 'status' => 403 ) );
}

function prospergenics_courses_rest_list_permission( WP_REST_Request $request ) {
	$status = $request->get_param( 'status' );
	if ( $status && 'publish' !== $status ) {
		return prospergenics_courses_rest_instructor();
	}
	return true;
}

/** A post of $type that the current user may see (published in a published course, or editable). */
function prospergenics_courses_rest_visible( $id, $type ) {
	$post = get_post( (int) $id );
	if ( ! $post || $type !== $post->post_type ) {
		return false;
	}
	if ( current_user_can( 'edit_post', $post->ID ) ) {
		return true;
	}
	if ( 'publish' !== $post->post_status ) {
		return false;
	}
	if ( 'pg_course' === $type ) {
		return true;
	}
	$course = prospergenics_courses_course_of( $post );
	if ( ! $course || 'publish' !== get_post_status( $course ) ) {
		return false;
	}
	return 'pg_lesson' !== $type || 'publish' === get_post_status( $post->post_parent );
}

function prospergenics_courses_rest_not_found() {
	return new WP_Error( 'rest_not_found', 'Not found.', array( 'status' => 404 ) );
}

/** Factory: permission callback for reading one post of a type. */
function prospergenics_courses_rest_can_read( $type ) {
	return function ( WP_REST_Request $request ) use ( $type ) {
		return prospergenics_courses_rest_visible( $request['id'], $type ) ? true : prospergenics_courses_rest_not_found();
	};
}

/** Factory: permission callback for a meta capability (edit_post / delete_post) on a post of a type. */
function prospergenics_courses_rest_can( $cap, $type ) {
	return function ( WP_REST_Request $request ) use ( $cap, $type ) {
		if ( ! is_user_logged_in() ) {
			return new WP_Error( 'rest_not_logged_in', 'You must be logged in.', array( 'status' => 401 ) );
		}
		if ( get_post_type( (int) $request['id'] ) !== $type ) {
			return prospergenics_courses_rest_not_found();
		}
		return current_user_can( $cap, (int) $request['id'] ) ? true : new WP_Error( 'rest_forbidden', 'You cannot change this.', array( 'status' => 403 ) );
	};
}

/* ------------------------------------------------------------------ serialisation */

function prospergenics_courses_rest_lesson( WP_Post $l, $full = false ) {
	$user_id = get_current_user_id();
	$course  = prospergenics_courses_course_of( $l );
	$out     = array(
		'id'        => (int) $l->ID,
		'title'     => get_the_title( $l ),
		'slug'      => $l->post_name,
		'status'    => $l->post_status,
		'order'     => (int) $l->menu_order,
		'module'    => (int) $l->post_parent,
		'course'    => $course,
		'link'      => get_permalink( $l ),
		'completed' => $user_id && $course ? in_array( (int) $l->ID, prospergenics_courses_completed_ids( $user_id, $course ), true ) : false,
	);
	if ( $full ) {
		$can           = prospergenics_courses_can_view_lesson( $l );
		$out['locked'] = ! $can;
		if ( $can ) {
			$out['video_url'] = (string) get_post_meta( $l->ID, '_pg_courses_video_url', true );
			$out['content']   = array( 'rendered' => apply_filters( 'the_content', $l->post_content ) );
			if ( current_user_can( 'edit_post', $l->ID ) ) {
				$out['content']['raw'] = $l->post_content;
			}
		}
	}
	return $out;
}

function prospergenics_courses_rest_module( WP_Post $m, $lessons = null ) {
	$out = array(
		'id'     => (int) $m->ID,
		'title'  => get_the_title( $m ),
		'status' => $m->post_status,
		'order'  => (int) $m->menu_order,
		'course' => (int) $m->post_parent,
	);
	if ( null !== $lessons ) {
		$out['lessons'] = array_map( 'prospergenics_courses_rest_lesson', $lessons );
	}
	return $out;
}

function prospergenics_courses_rest_course( WP_Post $c, $with_curriculum = false ) {
	$user_id    = get_current_user_id();
	$instructor = get_userdata( (int) $c->post_author );
	$can_edit   = current_user_can( 'edit_post', $c->ID );
	$thumb_id   = (int) get_post_thumbnail_id( $c );
	$out        = array(
		'id'              => (int) $c->ID,
		'slug'            => $c->post_name,
		'status'          => $c->post_status,
		'link'            => get_permalink( $c ),
		'title'           => get_the_title( $c ),
		'excerpt'         => has_excerpt( $c ) ? get_the_excerpt( $c ) : '',
		'content'         => array( 'rendered' => apply_filters( 'the_content', $c->post_content ) ),
		'outcomes'        => prospergenics_courses_parse_outcomes( get_post_meta( $c->ID, '_pg_courses_outcomes', true ) ),
		'level'           => prospergenics_courses_level( $c->ID ),
		'price'           => prospergenics_courses_price( $c->ID ),
		'is_free'         => prospergenics_courses_is_free( $c->ID ),
		'categories'      => array_map( function ( $t ) { return array( 'id' => (int) $t->term_id, 'slug' => $t->slug, 'name' => $t->name ); }, prospergenics_courses_categories( $c->ID ) ),
		'instructor'      => $instructor ? array( 'id' => (int) $instructor->ID, 'name' => $instructor->display_name, 'link' => prospergenics_courses_instructor_url( $instructor->ID ) ) : null,
		'thumbnail'       => $thumb_id ? array( 'id' => $thumb_id, 'url' => wp_get_attachment_image_url( $thumb_id, 'medium_large' ) ) : null,
		'lesson_count'    => count( prospergenics_courses_lesson_ids( $c->ID ) ),
		'enrolment_count' => (int) get_post_meta( $c->ID, '_pg_courses_enrolment_count', true ),
		'date'            => get_post_time( 'c', true, $c ),
		'modified'        => get_post_modified_time( 'c', true, $c ),
	);
	if ( $can_edit ) {
		$out['content']['raw'] = $c->post_content;
		$out['outcomes_raw']   = (string) get_post_meta( $c->ID, '_pg_courses_outcomes', true );
		$out['edit_link']      = prospergenics_courses_wizard_url( $c->ID );
	}
	if ( $user_id ) {
		$out['enrolled'] = prospergenics_courses_is_enrolled( $user_id, $c->ID );
		$out['progress'] = prospergenics_courses_progress( $user_id, $c->ID );
	}
	if ( $with_curriculum ) {
		$out['modules'] = array();
		foreach ( prospergenics_courses_curriculum( $c->ID, $can_edit ) as $m ) {
			$out['modules'][] = prospergenics_courses_rest_module( $m['module'], $m['lessons'] );
		}
	}
	return $out;
}

/* ------------------------------------------------------------------ writes */

/** Apply course fields from a request (title, excerpt, content, outcomes, level, price, category, status, instructor). */
function prospergenics_courses_rest_save_course( WP_REST_Request $request, $course_id = 0 ) {
	$post = array( 'post_type' => 'pg_course' );
	if ( $course_id ) {
		$post['ID'] = $course_id;
	} else {
		$post['post_author'] = get_current_user_id();
		$post['post_status'] = 'draft';
	}
	if ( null !== $request['title'] ) {
		$title = sanitize_text_field( $request['title'] );
		if ( '' === $title ) {
			return new WP_Error( 'rest_invalid_param', 'A course needs a title.', array( 'status' => 400 ) );
		}
		$post['post_title'] = mb_substr( $title, 0, 200 );
	} elseif ( ! $course_id ) {
		return new WP_Error( 'rest_invalid_param', 'A course needs a title.', array( 'status' => 400 ) );
	}
	if ( null !== $request['excerpt'] ) {
		$post['post_excerpt'] = mb_substr( sanitize_textarea_field( $request['excerpt'] ), 0, 600 );
	}
	if ( null !== $request['content'] ) {
		$post['post_content'] = wp_kses_post( mb_substr( (string) $request['content'], 0, 100000 ) );
	}
	if ( null !== $request['status'] ) {
		$status = 'publish' === $request['status'] ? 'publish' : 'draft';
		if ( 'publish' === $status && ! current_user_can( 'publish_pg_courses' ) ) {
			return new WP_Error( 'rest_forbidden', 'You cannot publish courses.', array( 'status' => 403 ) );
		}
		$post['post_status'] = $status;
	}
	if ( null !== $request['instructor'] && current_user_can( 'edit_others_pg_courses' ) ) {
		$uid = absint( $request['instructor'] );
		if ( ! $uid || ! user_can( $uid, 'edit_pg_courses' ) ) {
			return new WP_Error( 'rest_invalid_param', 'The instructor must be a user who can teach courses.', array( 'status' => 400 ) );
		}
		$post['post_author'] = $uid;
	}
	$id = $course_id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $id ) ) {
		return $id;
	}
	if ( null !== $request['outcomes'] ) {
		$lines = is_array( $request['outcomes'] ) ? implode( "\n", array_map( 'strval', $request['outcomes'] ) ) : (string) $request['outcomes'];
		update_post_meta( $id, '_pg_courses_outcomes', wp_slash( implode( "\n", prospergenics_courses_parse_outcomes( $lines ) ) ) );
	}
	if ( null !== $request['level'] ) {
		update_post_meta( $id, '_pg_courses_level', prospergenics_courses_sanitize_level( $request['level'] ) );
	}
	if ( null !== $request['price'] ) {
		update_post_meta( $id, '_pg_courses_price', prospergenics_courses_sanitize_price( $request['price'] ) );
	}
	if ( null !== $request['category'] ) {
		$term_id = absint( $request['category'] );
		$term    = $term_id ? get_term( $term_id, 'pg_course_category' ) : null;
		wp_set_object_terms( $id, $term && ! is_wp_error( $term ) ? array( (int) $term->term_id ) : array(), 'pg_course_category' );
	}
	if ( '' === get_post_meta( $id, '_pg_courses_enrolment_count', true ) ) {
		update_post_meta( $id, '_pg_courses_enrolment_count', 0 );
	}
	// Meta and terms are saved after the post: fire the save hook again so an index sees them.
	do_action( 'prospergenics_platform_content_saved', $id, 'pg_course' );
	return (int) $id;
}

function prospergenics_courses_rest_save_child( $type, $parent_id, array $fields, $id = 0 ) {
	$post = array(
		'post_type'   => $type,
		'post_parent' => (int) $parent_id,
		'post_status' => 'publish', // visibility follows the course's status
	);
	if ( $id ) {
		$post['ID'] = (int) $id;
	} else {
		$post['post_author'] = (int) get_post_field( 'post_author', $parent_id );
	}
	if ( isset( $fields['title'] ) ) {
		$title              = mb_substr( sanitize_text_field( $fields['title'] ), 0, 200 );
		$post['post_title'] = '' !== $title ? $title : ( 'pg_module' === $type ? 'Untitled module' : 'Untitled lesson' );
		// An item autosaved before it had a title got a placeholder slug: let the real title
		// replace it (an empty post_name is regenerated from the title on save).
		if ( $id && '' !== $title && 0 === strpos( (string) get_post_field( 'post_name', $id ), 'untitled-' ) ) {
			$post['post_name'] = '';
		}
	}
	if ( isset( $fields['order'] ) ) {
		$post['menu_order'] = (int) $fields['order'];
	}
	if ( 'pg_lesson' === $type && isset( $fields['content'] ) ) {
		$post['post_content'] = wp_kses_post( mb_substr( (string) $fields['content'], 0, 100000 ) );
	}
	$saved = $id ? wp_update_post( wp_slash( $post ), true ) : wp_insert_post( wp_slash( $post ), true );
	if ( is_wp_error( $saved ) ) {
		return $saved;
	}
	if ( 'pg_lesson' === $type && isset( $fields['video_url'] ) ) {
		$url = esc_url_raw( trim( (string) $fields['video_url'] ) );
		update_post_meta( $saved, '_pg_courses_video_url', wp_slash( prospergenics_courses_sanitize_video_url( $url ) ) );
	}
	return (int) $saved;
}

/**
 * Replace a course's modules and lessons with the given outline (ids keep existing items;
 * items missing from the outline are deleted). Each item may carry a client "key", echoed back
 * with its id so the wizard can match new items.
 */
function prospergenics_courses_sync_curriculum( $course_id, array $modules ) {
	if ( count( $modules ) > 50 ) {
		return new WP_Error( 'rest_invalid_param', 'At most 50 modules per course.', array( 'status' => 400 ) );
	}
	$existing_modules = array();
	$existing_lessons = array();
	foreach ( prospergenics_courses_curriculum( $course_id, true ) as $m ) {
		$existing_modules[ $m['module']->ID ] = true;
		foreach ( $m['lessons'] as $l ) {
			$existing_lessons[ $l->ID ] = true;
		}
	}
	$keep_m = array();
	$keep_l = array();
	$result = array();
	$total  = 0;
	foreach ( array_values( $modules ) as $mi => $m ) {
		if ( ! is_array( $m ) ) {
			continue;
		}
		$mid = isset( $m['id'] ) ? absint( $m['id'] ) : 0;
		$mid = isset( $existing_modules[ $mid ] ) ? $mid : 0;
		$mid = prospergenics_courses_rest_save_child( 'pg_module', $course_id, array( 'title' => isset( $m['title'] ) ? $m['title'] : '', 'order' => $mi ), $mid );
		if ( is_wp_error( $mid ) ) {
			return $mid;
		}
		$keep_m[ $mid ] = true;
		$row            = array( 'key' => isset( $m['key'] ) ? sanitize_key( $m['key'] ) : '', 'id' => $mid, 'lessons' => array() );
		foreach ( array_values( isset( $m['lessons'] ) && is_array( $m['lessons'] ) ? $m['lessons'] : array() ) as $li => $l ) {
			if ( ! is_array( $l ) || ++$total > 300 ) {
				continue;
			}
			$lid = isset( $l['id'] ) ? absint( $l['id'] ) : 0;
			$lid = isset( $existing_lessons[ $lid ] ) ? $lid : 0;
			$lid = prospergenics_courses_rest_save_child( 'pg_lesson', $mid, array(
				'title'     => isset( $l['title'] ) ? $l['title'] : '',
				'content'   => isset( $l['content'] ) ? $l['content'] : '',
				'video_url' => isset( $l['video_url'] ) ? $l['video_url'] : '',
				'order'     => $li,
			), $lid );
			if ( is_wp_error( $lid ) ) {
				return $lid;
			}
			$keep_l[ $lid ]   = true;
			$row['lessons'][] = array( 'key' => isset( $l['key'] ) ? sanitize_key( $l['key'] ) : '', 'id' => $lid );
		}
		$result[] = $row;
	}
	foreach ( array_keys( array_diff_key( $existing_lessons, $keep_l ) ) as $gone ) {
		wp_delete_post( $gone, true );
	}
	foreach ( array_keys( array_diff_key( $existing_modules, $keep_m ) ) as $gone ) {
		wp_delete_post( $gone, true );
	}
	prospergenics_courses_flush_curriculum( $course_id );
	return $result;
}

/* ------------------------------------------------------------------ routes */

add_action( 'rest_api_init', function () {
	$ns     = PROSPERGENICS_COURSES_NS;
	$id_arg = array( 'id' => array( 'validate_callback' => function ( $v ) { return is_numeric( $v ) && $v > 0; } ) );
	$course_args = array(
		'title'      => array( 'type' => 'string' ),
		'excerpt'    => array( 'type' => 'string' ),
		'content'    => array( 'type' => 'string' ),
		'outcomes'   => array( 'type' => array( 'string', 'array' ) ),
		'level'      => array( 'type' => 'string', 'enum' => array_merge( array( '' ), array_keys( prospergenics_courses_levels() ) ) ),
		'price'      => array( 'type' => 'number', 'minimum' => 0 ),
		'category'   => array( 'type' => 'integer', 'minimum' => 0 ),
		'status'     => array( 'type' => 'string', 'enum' => array( 'draft', 'publish' ) ),
		'instructor' => array( 'type' => 'integer', 'minimum' => 1 ),
	);

	register_rest_route( $ns, '/courses', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'prospergenics_courses_rest_list',
			'permission_callback' => 'prospergenics_courses_rest_list_permission',
			'args'                => array(
				'category'   => array( 'type' => 'string' ),
				'level'      => array( 'type' => 'string' ),
				'sort'       => array( 'type' => 'string', 'enum' => array( 'newest', 'popular' ), 'default' => 'newest' ),
				'instructor' => array( 'type' => 'integer' ),
				'status'     => array( 'type' => 'string', 'enum' => array( 'publish', 'draft', 'any' ) ),
				'page'       => array( 'type' => 'integer', 'minimum' => 1, 'default' => 1 ),
				'per_page'   => array( 'type' => 'integer', 'minimum' => 1, 'maximum' => 50, 'default' => 12 ),
			),
		),
		array(
			'methods'             => 'POST',
			'callback'            => function ( WP_REST_Request $request ) {
				$id = prospergenics_courses_rest_save_course( $request );
				if ( is_wp_error( $id ) ) {
					return $id;
				}
				return new WP_REST_Response( prospergenics_courses_rest_course( get_post( $id ), true ), 201 );
			},
			'permission_callback' => 'prospergenics_courses_rest_instructor',
			'args'                => $course_args,
		),
	) );

	register_rest_route( $ns, '/courses/(?P<id>\d+)', array(
		array(
			'methods'             => 'GET',
			'callback'            => function ( WP_REST_Request $request ) {
				return prospergenics_courses_rest_course( get_post( (int) $request['id'] ), true );
			},
			'permission_callback' => prospergenics_courses_rest_can_read( 'pg_course' ),
			'args'                => $id_arg,
		),
		array(
			'methods'             => 'PUT, PATCH',
			'callback'            => function ( WP_REST_Request $request ) {
				$id = prospergenics_courses_rest_save_course( $request, (int) $request['id'] );
				return is_wp_error( $id ) ? $id : prospergenics_courses_rest_course( get_post( $id ), true );
			},
			'permission_callback' => prospergenics_courses_rest_can( 'edit_post', 'pg_course' ),
			'args'                => $id_arg + $course_args,
		),
		array(
			'methods'             => 'DELETE',
			'callback'            => function ( WP_REST_Request $request ) {
				$id = (int) $request['id'];
				$ok = $request['force'] ? wp_delete_post( $id, true ) : wp_trash_post( $id );
				return $ok ? array( 'deleted' => true, 'id' => $id, 'trashed' => ! $request['force'] ) : new WP_Error( 'rest_cannot_delete', 'The course could not be deleted.', array( 'status' => 500 ) );
			},
			'permission_callback' => prospergenics_courses_rest_can( 'delete_post', 'pg_course' ),
			'args'                => $id_arg + array( 'force' => array( 'type' => 'boolean', 'default' => false ) ),
		),
	) );

	register_rest_route( $ns, '/courses/(?P<id>\d+)/curriculum', array(
		'methods'             => 'PUT, POST',
		'callback'            => function ( WP_REST_Request $request ) {
			$modules = $request['modules'];
			$result  = prospergenics_courses_sync_curriculum( (int) $request['id'], is_array( $modules ) ? $modules : array() );
			return is_wp_error( $result ) ? $result : array( 'modules' => $result );
		},
		'permission_callback' => prospergenics_courses_rest_can( 'edit_post', 'pg_course' ),
		'args'                => $id_arg + array( 'modules' => array( 'type' => 'array', 'required' => true ) ),
	) );

	register_rest_route( $ns, '/courses/(?P<id>\d+)/thumbnail', array(
		'methods'             => 'POST',
		'callback'            => 'prospergenics_courses_rest_thumbnail',
		'permission_callback' => prospergenics_courses_rest_can( 'edit_post', 'pg_course' ),
		'args'                => $id_arg,
	) );

	// Modules
	register_rest_route( $ns, '/courses/(?P<id>\d+)/modules', array(
		array(
			'methods'             => 'GET',
			'callback'            => function ( WP_REST_Request $request ) {
				$out = array();
				foreach ( prospergenics_courses_curriculum( (int) $request['id'], current_user_can( 'edit_post', (int) $request['id'] ) ) as $m ) {
					$out[] = prospergenics_courses_rest_module( $m['module'], $m['lessons'] );
				}
				return $out;
			},
			'permission_callback' => prospergenics_courses_rest_can_read( 'pg_course' ),
			'args'                => $id_arg,
		),
		array(
			'methods'             => 'POST',
			'callback'            => function ( WP_REST_Request $request ) {
				$count = count( prospergenics_courses_get_modules( (int) $request['id'], true ) );
				$id    = prospergenics_courses_rest_save_child( 'pg_module', (int) $request['id'], array( 'title' => (string) $request['title'], 'order' => null !== $request['order'] ? (int) $request['order'] : $count ) );
				prospergenics_courses_flush_curriculum( (int) $request['id'] );
				return is_wp_error( $id ) ? $id : new WP_REST_Response( prospergenics_courses_rest_module( get_post( $id ), array() ), 201 );
			},
			'permission_callback' => prospergenics_courses_rest_can( 'edit_post', 'pg_course' ),
			'args'                => $id_arg + array( 'title' => array( 'type' => 'string', 'required' => true ), 'order' => array( 'type' => 'integer' ) ),
		),
	) );

	register_rest_route( $ns, '/courses/modules/(?P<id>\d+)', array(
		array(
			'methods'             => 'GET',
			'callback'            => function ( WP_REST_Request $request ) {
				$m = get_post( (int) $request['id'] );
				return prospergenics_courses_rest_module( $m, prospergenics_courses_get_lessons_of_modules( array( $m->ID ), current_user_can( 'edit_post', $m->ID ) ) );
			},
			'permission_callback' => prospergenics_courses_rest_can_read( 'pg_module' ),
			'args'                => $id_arg,
		),
		array(
			'methods'             => 'PUT, PATCH',
			'callback'            => function ( WP_REST_Request $request ) {
				$m      = get_post( (int) $request['id'] );
				$fields = array();
				if ( null !== $request['title'] ) {
					$fields['title'] = $request['title'];
				}
				if ( null !== $request['order'] ) {
					$fields['order'] = $request['order'];
				}
				$id = prospergenics_courses_rest_save_child( 'pg_module', $m->post_parent, $fields, $m->ID );
				return is_wp_error( $id ) ? $id : prospergenics_courses_rest_module( get_post( $id ) );
			},
			'permission_callback' => prospergenics_courses_rest_can( 'edit_post', 'pg_module' ),
			'args'                => $id_arg + array( 'title' => array( 'type' => 'string' ), 'order' => array( 'type' => 'integer' ) ),
		),
		array(
			'methods'             => 'DELETE',
			'callback'            => function ( WP_REST_Request $request ) {
				$course = prospergenics_courses_course_of( (int) $request['id'] );
				wp_delete_post( (int) $request['id'], true );
				prospergenics_courses_flush_curriculum( $course );
				return array( 'deleted' => true, 'id' => (int) $request['id'] );
			},
			'permission_callback' => prospergenics_courses_rest_can( 'delete_post', 'pg_module' ),
			'args'                => $id_arg,
		),
	) );

	// Lessons
	register_rest_route( $ns, '/courses/modules/(?P<id>\d+)/lessons', array(
		array(
			'methods'             => 'GET',
			'callback'            => function ( WP_REST_Request $request ) {
				return array_map( 'prospergenics_courses_rest_lesson', prospergenics_courses_get_lessons_of_modules( array( (int) $request['id'] ), current_user_can( 'edit_post', (int) $request['id'] ) ) );
			},
			'permission_callback' => prospergenics_courses_rest_can_read( 'pg_module' ),
			'args'                => $id_arg,
		),
		array(
			'methods'             => 'POST',
			'callback'            => function ( WP_REST_Request $request ) {
				$count = count( prospergenics_courses_get_lessons_of_modules( array( (int) $request['id'] ), true ) );
				$id    = prospergenics_courses_rest_save_child( 'pg_lesson', (int) $request['id'], array(
					'title'     => (string) $request['title'],
					'content'   => (string) $request['content'],
					'video_url' => (string) $request['video_url'],
					'order'     => null !== $request['order'] ? (int) $request['order'] : $count,
				) );
				prospergenics_courses_flush_curriculum( prospergenics_courses_course_of( (int) $request['id'] ) );
				return is_wp_error( $id ) ? $id : new WP_REST_Response( prospergenics_courses_rest_lesson( get_post( $id ), true ), 201 );
			},
			'permission_callback' => prospergenics_courses_rest_can( 'edit_post', 'pg_module' ),
			'args'                => $id_arg + array(
				'title'     => array( 'type' => 'string', 'required' => true ),
				'content'   => array( 'type' => 'string', 'default' => '' ),
				'video_url' => array( 'type' => 'string', 'default' => '' ),
				'order'     => array( 'type' => 'integer' ),
			),
		),
	) );

	register_rest_route( $ns, '/courses/lessons/(?P<id>\d+)', array(
		array(
			'methods'             => 'GET',
			'callback'            => function ( WP_REST_Request $request ) {
				return prospergenics_courses_rest_lesson( get_post( (int) $request['id'] ), true );
			},
			'permission_callback' => prospergenics_courses_rest_can_read( 'pg_lesson' ),
			'args'                => $id_arg,
		),
		array(
			'methods'             => 'PUT, PATCH',
			'callback'            => function ( WP_REST_Request $request ) {
				$l      = get_post( (int) $request['id'] );
				$parent = $l->post_parent;
				if ( null !== $request['module'] ) {
					$to = absint( $request['module'] );
					if ( 'pg_module' !== get_post_type( $to ) || ! current_user_can( 'edit_post', $to ) ) {
						return new WP_Error( 'rest_invalid_param', 'Unknown module.', array( 'status' => 400 ) );
					}
					$parent = $to;
				}
				$fields = array();
				foreach ( array( 'title', 'content', 'video_url', 'order' ) as $f ) {
					if ( null !== $request[ $f ] ) {
						$fields[ $f ] = $request[ $f ];
					}
				}
				$id = prospergenics_courses_rest_save_child( 'pg_lesson', $parent, $fields, $l->ID );
				return is_wp_error( $id ) ? $id : prospergenics_courses_rest_lesson( get_post( $id ), true );
			},
			'permission_callback' => prospergenics_courses_rest_can( 'edit_post', 'pg_lesson' ),
			'args'                => $id_arg + array(
				'title'     => array( 'type' => 'string' ),
				'content'   => array( 'type' => 'string' ),
				'video_url' => array( 'type' => 'string' ),
				'order'     => array( 'type' => 'integer' ),
				'module'    => array( 'type' => 'integer' ),
			),
		),
		array(
			'methods'             => 'DELETE',
			'callback'            => function ( WP_REST_Request $request ) {
				$course = prospergenics_courses_course_of( (int) $request['id'] );
				wp_delete_post( (int) $request['id'], true );
				prospergenics_courses_flush_curriculum( $course );
				return array( 'deleted' => true, 'id' => (int) $request['id'] );
			},
			'permission_callback' => prospergenics_courses_rest_can( 'delete_post', 'pg_lesson' ),
			'args'                => $id_arg,
		),
	) );

	// The current user's enrolment, progress and certificate.
	register_rest_route( $ns, '/courses/(?P<id>\d+)/enrolment', array(
		array(
			'methods'             => 'GET',
			'callback'            => 'prospergenics_courses_rest_enrolment_status',
			'permission_callback' => 'prospergenics_courses_rest_logged_in',
			'args'                => $id_arg,
		),
		array(
			'methods'             => 'POST',
			'callback'            => function ( WP_REST_Request $request ) {
				$result = prospergenics_courses_enrol( get_current_user_id(), (int) $request['id'] );
				return is_wp_error( $result ) ? $result : prospergenics_courses_rest_enrolment_status( $request );
			},
			'permission_callback' => 'prospergenics_courses_rest_logged_in',
			'args'                => $id_arg,
		),
		array(
			'methods'             => 'DELETE',
			'callback'            => function ( WP_REST_Request $request ) {
				prospergenics_courses_unenrol( get_current_user_id(), (int) $request['id'] );
				return prospergenics_courses_rest_enrolment_status( $request );
			},
			'permission_callback' => 'prospergenics_courses_rest_logged_in',
			'args'                => $id_arg,
		),
	) );

	register_rest_route( $ns, '/courses/lessons/(?P<id>\d+)/completion', array(
		array(
			'methods'             => 'POST, DELETE',
			'callback'            => function ( WP_REST_Request $request ) {
				return prospergenics_courses_set_lesson_complete( get_current_user_id(), (int) $request['id'], 'POST' === $request->get_method() );
			},
			'permission_callback' => 'prospergenics_courses_rest_logged_in',
			'args'                => $id_arg,
		),
	) );

	register_rest_route( $ns, '/courses/(?P<id>\d+)/certificate', array(
		'methods'             => 'GET',
		'callback'            => function ( WP_REST_Request $request ) {
			$cert = prospergenics_courses_get_certificate( get_current_user_id(), (int) $request['id'] );
			if ( ! $cert ) {
				return new WP_Error( 'rest_not_found', 'No certificate for this course yet.', array( 'status' => 404 ) );
			}
			$data                 = prospergenics_courses_certificate_data( $cert );
			$data['download_url'] = prospergenics_courses_certificate_url( (int) $request['id'] );
			return $data;
		},
		'permission_callback' => 'prospergenics_courses_rest_logged_in',
		'args'                => $id_arg,
	) );

	register_rest_route( $ns, '/courses/me', array(
		'methods'             => 'GET',
		'callback'            => function () {
			$out = array();
			foreach ( prospergenics_courses_user_enrolments( get_current_user_id() ) as $e ) {
				$course = get_post( $e->post_parent );
				if ( ! $course || 'publish' !== $course->post_status ) {
					continue;
				}
				$cert  = prospergenics_courses_get_certificate( get_current_user_id(), $course->ID );
				$out[] = array(
					'course'      => array( 'id' => (int) $course->ID, 'title' => get_the_title( $course ), 'link' => get_permalink( $course ) ),
					'enrolled_at' => get_post_time( 'c', true, $e ),
					'progress'    => prospergenics_courses_progress( get_current_user_id(), $course->ID ),
					'certificate' => $cert ? (string) get_post_meta( $cert->ID, '_pg_courses_code', true ) : null,
				);
			}
			return $out;
		},
		'permission_callback' => 'prospergenics_courses_rest_logged_in',
	) );

	register_rest_route( $ns, '/courses/certificates/(?P<code>[A-Za-z0-9-]+)', array(
		'methods'             => 'GET',
		'callback'            => function ( WP_REST_Request $request ) {
			$cert = prospergenics_courses_get_certificate_by_code( $request['code'] );
			if ( ! $cert ) {
				return new WP_REST_Response( array( 'valid' => false ), 404 );
			}
			$d = prospergenics_courses_certificate_data( $cert );
			return array(
				'valid'      => true,
				'code'       => $d['code'],
				'name'       => $d['name'],
				'course'     => $d['course'],
				'instructor' => $d['instructor'],
				'issued_at'  => gmdate( 'c', $d['issued_at'] ),
				'verify_url' => $d['verify_url'],
			);
		},
		'permission_callback' => 'prospergenics_courses_rest_public_read',
	) );

	register_rest_route( $ns, '/courses/instructor/stats', array(
		'methods'             => 'GET',
		'callback'            => function () {
			$out = array();
			foreach ( prospergenics_courses_dashboard_courses( get_current_user_id(), false ) as $c ) {
				$out[] = array( 'id' => (int) $c->ID, 'title' => get_the_title( $c ), 'status' => $c->post_status ) + prospergenics_courses_stats( $c->ID );
			}
			return $out;
		},
		'permission_callback' => 'prospergenics_courses_rest_instructor',
	) );
} );

function prospergenics_courses_rest_list( WP_REST_Request $request ) {
	$args = array(
		'post_type'      => 'pg_course',
		'post_status'    => 'publish',
		'posts_per_page' => (int) $request['per_page'],
		'paged'          => (int) $request['page'],
		'orderby'        => array( 'date' => 'DESC', 'ID' => 'DESC' ),
	);
	$status = $request['status'];
	if ( $status && 'publish' !== $status ) {
		$args['post_status'] = 'any' === $status ? array( 'publish', 'draft', 'pending', 'private', 'future' ) : 'draft';
		if ( ! current_user_can( 'edit_others_pg_courses' ) ) {
			$args['author'] = get_current_user_id();
		}
	}
	if ( $request['category'] ) {
		$args['tax_query'] = array( array( 'taxonomy' => 'pg_course_category', 'field' => 'slug', 'terms' => sanitize_title( $request['category'] ) ) );
	}
	$meta  = array();
	$level = prospergenics_courses_sanitize_level( $request['level'] );
	if ( $level ) {
		$meta['level'] = array( 'key' => '_pg_courses_level', 'value' => $level );
	}
	if ( 'popular' === $request['sort'] ) {
		$meta['popular']  = array( 'key' => '_pg_courses_enrolment_count', 'type' => 'NUMERIC', 'compare' => 'EXISTS' );
		$args['orderby'] = array( 'popular' => 'DESC', 'date' => 'DESC' );
	}
	if ( $meta ) {
		$args['meta_query'] = array_merge( array( 'relation' => 'AND' ), $meta );
	}
	if ( $request['instructor'] && ! isset( $args['author'] ) ) {
		$args['author'] = (int) $request['instructor'];
	}
	$q        = new WP_Query( $args );
	$response = new WP_REST_Response( array_map( function ( $p ) { return prospergenics_courses_rest_course( $p ); }, $q->posts ) );
	$response->header( 'X-WP-Total', (int) $q->found_posts );
	$response->header( 'X-WP-TotalPages', (int) $q->max_num_pages );
	return $response;
}

function prospergenics_courses_rest_enrolment_status( WP_REST_Request $request ) {
	$user_id   = get_current_user_id();
	$course_id = (int) $request['id'];
	if ( 'pg_course' !== get_post_type( $course_id ) ) {
		return prospergenics_courses_rest_not_found();
	}
	$e = prospergenics_courses_get_enrolment( $user_id, $course_id );
	return array(
		'course'      => $course_id,
		'enrolled'    => (bool) $e,
		'enrolled_at' => $e ? get_post_time( 'c', true, $e ) : null,
		'progress'    => prospergenics_courses_progress( $user_id, $course_id ),
		'completed'   => prospergenics_courses_completed_ids( $user_id, $course_id ),
		'can_enrol'   => '' === prospergenics_courses_enrol_block_reason( $user_id, $course_id ),
	);
}

/** POST /courses/{id}/thumbnail: one image (JPEG, PNG, WebP or GIF, at most 5 MB). */
function prospergenics_courses_rest_thumbnail( WP_REST_Request $request ) {
	$course_id = (int) $request['id'];
	$files     = $request->get_file_params();
	if ( empty( $files['file'] ) || ! is_array( $files['file'] ) || UPLOAD_ERR_OK !== (int) $files['file']['error'] ) {
		return new WP_Error( 'rest_upload_no_data', 'Choose an image to upload.', array( 'status' => 400 ) );
	}
	if ( (int) $files['file']['size'] > 5 * MB_IN_BYTES ) {
		return new WP_Error( 'rest_upload_too_large', 'The image must be 5 MB or smaller.', array( 'status' => 400 ) );
	}
	$mimes = array( 'jpg|jpeg|jpe' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp', 'gif' => 'image/gif' );
	$check = wp_check_filetype_and_ext( $files['file']['tmp_name'], $files['file']['name'], $mimes );
	if ( empty( $check['type'] ) || 0 !== strpos( $check['type'], 'image/' ) ) {
		return new WP_Error( 'rest_upload_invalid_type', 'Upload a JPEG, PNG, WebP or GIF image.', array( 'status' => 400 ) );
	}
	require_once ABSPATH . 'wp-admin/includes/file.php';
	require_once ABSPATH . 'wp-admin/includes/media.php';
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$_FILES['pg_courses_thumbnail'] = $files['file'];
	$attachment = media_handle_upload( 'pg_courses_thumbnail', $course_id, array( 'post_title' => get_the_title( $course_id ) ), array( 'test_form' => false, 'mimes' => $mimes ) );
	unset( $_FILES['pg_courses_thumbnail'] );
	if ( is_wp_error( $attachment ) ) {
		$attachment->add_data( array( 'status' => 400 ) );
		return $attachment;
	}
	set_post_thumbnail( $course_id, $attachment );
	do_action( 'prospergenics_platform_content_saved', $course_id, 'pg_course' );
	return array( 'id' => (int) $attachment, 'url' => wp_get_attachment_image_url( $attachment, 'medium_large' ) );
}

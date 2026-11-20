<?php
/**
 * Prospergenics Platform: role separation (card #6341).
 *
 * Four platform roles, one WordPress capability set each, no separate RBAC system:
 *
 *   member      the default (WordPress "subscriber"): reads, enrols, keeps a profile
 *   company     pg_company role: may create and edit company profiles (pg_company_profile)
 *   instructor  pg_instructor role: may create courses (the five instructor capabilities of
 *               inc/courses/data-model.php)
 *   admin       WordPress administrator (and editor): everything, assigns the roles
 *
 * An administrator assigns the role on the user's profile screen (Users > Edit, "Platform role") or
 * on /manage/members/ ("Change role", inc/manage/helpers.php). A company account writes its own
 * company profile in wp-admin, on the company profile screens only (inc/account.php), and finds it
 * under "Your company profile" in the account menu.
 * The choice is stored as the WordPress role and mirrored in user meta _pg_platform_role, which is
 * the value the IAM profile sync reads (hook: prospergenics_platform_role_changed). Members whose
 * "Apply to teach" request was approved (inc/profiles/applications.php) become instructors through
 * the same function, so there is one place that changes a role.
 *
 * Staff accounts (anyone who may edit others' posts) keep their WordPress role: this screen never
 * demotes an administrator or editor.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_ROLES_VERSION   = '1';
const PROSPERGENICS_ROLES_META      = '_pg_platform_role';
const PROSPERGENICS_ROLES_ASSIGNABLE = array( 'member', 'company', 'instructor' );

/** Platform role => the WordPress role slug that carries it. */
function prospergenics_roles_wp_role_map() {
	return array(
		'member'     => 'subscriber',
		'company'    => 'pg_company',
		'instructor' => 'pg_instructor',
	);
}

/** Human labels, in the order the select shows them. */
function prospergenics_roles_labels() {
	return array(
		'member'     => 'Member',
		'company'    => 'Company',
		'instructor' => 'Instructor',
		'admin'      => 'Admin',
	);
}

/** The primitive capabilities of the pg_company_profile capability type. */
function prospergenics_roles_company_primitive_caps() {
	return array(
		'edit_pg_company_profiles', 'edit_others_pg_company_profiles', 'publish_pg_company_profiles',
		'read_private_pg_company_profiles', 'delete_pg_company_profiles', 'delete_private_pg_company_profiles',
		'delete_published_pg_company_profiles', 'delete_others_pg_company_profiles',
		'edit_private_pg_company_profiles', 'edit_published_pg_company_profiles',
	);
}

/** What a company may do: its own profiles, nobody else's. */
function prospergenics_roles_company_caps() {
	return array(
		'edit_pg_company_profiles', 'publish_pg_company_profiles', 'edit_published_pg_company_profiles',
		'delete_pg_company_profiles', 'delete_published_pg_company_profiles',
	);
}

/** Capabilities of the pg_company and pg_instructor roles. */
function prospergenics_roles_caps( $platform_role ) {
	$caps = array( 'read' => true );
	if ( 'company' === $platform_role ) {
		foreach ( prospergenics_roles_company_caps() as $cap ) {
			$caps[ $cap ] = true;
		}
	}
	if ( 'instructor' === $platform_role ) {
		$fn = function_exists( 'prospergenics_courses_instructor_caps' ) ? 'prospergenics_courses_instructor_caps' : null;
		foreach ( $fn ? $fn() : array( 'edit_pg_courses', 'publish_pg_courses', 'edit_published_pg_courses', 'delete_pg_courses', 'delete_published_pg_courses' ) as $cap ) {
			$caps[ $cap ] = true;
		}
	}
	return $caps;
}

// Roles live in the database: (re)create them once per version, not on every request.
add_action( 'init', function () {
	if ( PROSPERGENICS_ROLES_VERSION === get_option( '_pg_roles_version' ) && get_role( 'pg_company' ) && get_role( 'pg_instructor' ) ) {
		return;
	}
	remove_role( 'pg_company' );
	remove_role( 'pg_instructor' );
	add_role( 'pg_company', 'Company', prospergenics_roles_caps( 'company' ) );
	add_role( 'pg_instructor', 'Instructor', prospergenics_roles_caps( 'instructor' ) );
	update_option( '_pg_roles_version', PROSPERGENICS_ROLES_VERSION, true );
}, 4 );

// Staff manage every company profile.
add_filter( 'user_has_cap', function ( $allcaps ) {
	if ( ! empty( $allcaps['edit_others_posts'] ) ) {
		foreach ( prospergenics_roles_company_primitive_caps() as $cap ) {
			$allcaps[ $cap ] = true;
		}
	}
	return $allcaps;
} );

/** Company profiles: only users with the company capabilities can create one. */
add_action( 'init', function () {
	register_post_type( 'pg_company_profile', array(
		'labels'          => array(
			'name'          => 'Company profiles',
			'singular_name' => 'Company profile',
			'add_new_item'  => 'Add company profile',
			'edit_item'     => 'Edit company profile',
		),
		'public'          => true,
		'has_archive'     => false,
		'rewrite'         => array( 'slug' => 'companies', 'with_front' => false ),
		'supports'        => array( 'title', 'editor', 'thumbnail', 'author' ),
		'menu_icon'       => 'dashicons-building',
		'capability_type' => array( 'pg_company_profile', 'pg_company_profiles' ),
		'map_meta_cap'    => true,
		'show_in_rest'    => false,
	) );
}, 5 );

/** Whether the user may create a company profile. */
function prospergenics_roles_can_create_company_profile( $user_id = 0 ) {
	return $user_id ? user_can( $user_id, 'edit_pg_company_profiles' ) : current_user_can( 'edit_pg_company_profiles' );
}

/*
 * Company accounts write their company profile in wp-admin (the classic editor: the type is not in
 * REST), on the only screens inc/account.php lets them open: the list, "Add company profile" and
 * their own profiles. Until company profiles move to the jengo-listings plugin (cards #6371/#6372).
 */

/**
 * The account menu link for a company profile (pure): "Create your company profile" while the
 * account has none, "Your company profile" (its edit screen) with one, "Your company profiles" (the
 * list) with more.
 *
 * @param int[] $ids The account's own company profiles, newest first (two are enough).
 * @return array{label:string,url:string}
 */
function prospergenics_roles_company_link_for( array $ids ) {
	if ( ! $ids ) {
		return array( 'label' => 'Create your company profile', 'url' => admin_url( 'post-new.php?post_type=pg_company_profile' ) );
	}
	if ( 1 === count( $ids ) ) {
		return array( 'label' => 'Your company profile', 'url' => admin_url( 'post.php?post=' . (int) reset( $ids ) . '&action=edit' ) );
	}
	return array( 'label' => 'Your company profiles', 'url' => admin_url( 'edit.php?post_type=pg_company_profile' ) );
}

/**
 * The account menu link of a company account, or null for everyone else. Staff (they edit every
 * company profile) get none: they have wp-admin.
 */
function prospergenics_roles_company_profile_link( $user_id ) {
	$user_id = (int) $user_id;
	if ( ! $user_id || ! user_can( $user_id, 'edit_pg_company_profiles' ) || user_can( $user_id, 'edit_others_pg_company_profiles' ) ) {
		return null;
	}
	$ids = get_posts( array(
		'post_type'      => 'pg_company_profile',
		'author'         => $user_id,
		'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
		'fields'         => 'ids',
		'posts_per_page' => 2,
		'orderby'        => 'date',
		'order'          => 'DESC',
		'no_found_rows'  => true,
	) );
	return prospergenics_roles_company_link_for( array_map( 'intval', (array) $ids ) );
}

// A company account's list shows its own profiles only (WordPress would also list other
// companies' drafts, read-only), without the status counts, which count everyone's.
add_action( 'pre_get_posts', function ( $query ) {
	global $pagenow;
	if ( is_admin() && 'edit.php' === $pagenow && $query->is_main_query() && 'pg_company_profile' === $query->get( 'post_type' )
		&& ! current_user_can( 'edit_others_pg_company_profiles' ) ) {
		$query->set( 'author', get_current_user_id() );
	}
} );
add_filter( 'views_edit-pg_company_profile', function ( $views ) {
	return current_user_can( 'edit_others_pg_company_profiles' ) ? $views : array();
} );

// "Company profile published. View company profile" instead of WordPress' "Post published. View post".
add_filter( 'post_updated_messages', function ( $messages ) {
	$post = get_post();
	if ( ! $post || 'pg_company_profile' !== $post->post_type ) {
		return $messages;
	}
	$view = ' <a href="' . esc_url( get_permalink( $post ) ) . '">View company profile</a>';
	$messages['pg_company_profile'] = array(
		1  => 'Company profile updated.' . $view,
		4  => 'Company profile updated.',
		6  => 'Company profile published.' . $view,
		7  => 'Company profile saved.',
		8  => 'Company profile submitted.',
		9  => 'Company profile scheduled.',
		10 => 'Company profile draft updated.',
	);
	return $messages;
} );

/** Whether the user may create courses (same test as the course wizard). */
function prospergenics_roles_can_create_course( $user_id = 0 ) {
	return $user_id ? user_can( $user_id, 'edit_pg_courses' ) : current_user_can( 'edit_pg_courses' );
}

/**
 * The platform role of a user: admin, instructor, company or member. Staff win over everything,
 * then instructor (also the interim _pg_courses_instructor flag), then company.
 */
function prospergenics_roles_get( $user_id ) {
	$user = get_userdata( (int) $user_id );
	if ( ! $user ) {
		return 'member';
	}
	if ( user_can( $user, 'edit_others_posts' ) || in_array( 'administrator', (array) $user->roles, true ) ) {
		return 'admin';
	}
	$wp_roles = (array) $user->roles;
	if ( in_array( 'pg_instructor', $wp_roles, true ) || ( defined( 'PROSPERGENICS_COURSES_INSTRUCTOR_META' ) && get_user_meta( $user->ID, PROSPERGENICS_COURSES_INSTRUCTOR_META, true ) ) ) {
		return 'instructor';
	}
	if ( in_array( 'pg_company', $wp_roles, true ) ) {
		return 'company';
	}
	return 'member';
}

/**
 * Give a user a platform role. Returns true, or a WP_Error when the role is unknown or the user is
 * staff (administrators and editors keep their WordPress role). Other WordPress roles the user
 * has (a pg_writer, say) stay; only the other platform roles are removed.
 */
function prospergenics_roles_assign( $user_id, $platform_role ) {
	$map = prospergenics_roles_wp_role_map();
	if ( ! isset( $map[ $platform_role ] ) ) {
		return new WP_Error( 'pg_roles_unknown', 'Unknown platform role.' );
	}
	$user = get_userdata( (int) $user_id );
	if ( ! $user ) {
		return new WP_Error( 'pg_roles_user', 'No such user.' );
	}
	if ( user_can( $user, 'edit_others_posts' ) ) {
		return new WP_Error( 'pg_roles_staff', 'Administrators and editors keep their WordPress role.' );
	}
	$before = prospergenics_roles_get( $user->ID );
	foreach ( array( 'pg_company', 'pg_instructor' ) as $slug ) {
		$user->remove_role( $slug );
	}
	if ( defined( 'PROSPERGENICS_COURSES_INSTRUCTOR_META' ) ) {
		delete_user_meta( $user->ID, PROSPERGENICS_COURSES_INSTRUCTOR_META );
	}
	if ( 'member' === $platform_role ) {
		if ( empty( $user->roles ) ) {
			$user->add_role( 'subscriber' );
		}
	} else {
		$user->remove_role( 'subscriber' );
		$user->add_role( $map[ $platform_role ] );
		if ( 'instructor' === $platform_role && defined( 'PROSPERGENICS_COURSES_INSTRUCTOR_META' ) ) {
			// keeps the interim flag in step for code that still reads it
			update_user_meta( $user->ID, PROSPERGENICS_COURSES_INSTRUCTOR_META, time() );
		}
	}
	update_user_meta( $user->ID, PROSPERGENICS_ROLES_META, $platform_role );
	clean_user_cache( $user->ID );
	if ( $before !== $platform_role ) {
		/**
		 * Fires after a platform role changed. The IAM profile sync listens here.
		 *
		 * @param int    $user_id WordPress user ID.
		 * @param string $role    New platform role.
		 * @param string $before  Previous platform role.
		 */
		do_action( 'prospergenics_platform_role_changed', (int) $user->ID, $platform_role, $before );
	}
	return true;
}

/** "Platform role" select on the user's profile screen, for those who may promote users. */
function prospergenics_roles_render_field( $user ) {
	if ( ! current_user_can( 'promote_users' ) || ! ( $user instanceof WP_User ) ) {
		return;
	}
	$current = prospergenics_roles_get( $user->ID );
	$staff   = 'admin' === $current;
	wp_nonce_field( 'pg_platform_role_' . $user->ID, 'pg_platform_role_nonce' );
	echo '<h2>Platform role</h2><table class="form-table" role="presentation"><tr><th><label for="pg_platform_role">Role on the platform</label></th><td>';
	echo '<select name="pg_platform_role" id="pg_platform_role"' . ( $staff ? ' disabled' : '' ) . '>';
	foreach ( prospergenics_roles_labels() as $slug => $label ) {
		if ( 'admin' === $slug && ! $staff ) {
			continue;
		}
		printf( '<option value="%s"%s>%s</option>', esc_attr( $slug ), selected( $current, $slug, false ), esc_html( $label ) );
	}
	echo '</select><p class="description">Member is the default. Instructors can create courses, companies can create company profiles. ';
	echo $staff ? 'This account is staff and keeps its WordPress role (change it in the Role field above).' : 'Administrators and editors keep their WordPress role.';
	echo '</p></td></tr></table>';
}
add_action( 'show_user_profile', 'prospergenics_roles_render_field' );
add_action( 'edit_user_profile', 'prospergenics_roles_render_field' );

/** Save the select: capability and nonce checked, unknown values ignored. */
function prospergenics_roles_save_field( $user_id ) {
	if ( ! current_user_can( 'promote_users' ) || ! isset( $_POST['pg_platform_role'], $_POST['pg_platform_role_nonce'] ) ) {
		return;
	}
	if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['pg_platform_role_nonce'] ) ), 'pg_platform_role_' . (int) $user_id ) ) {
		return;
	}
	$role = sanitize_key( wp_unslash( $_POST['pg_platform_role'] ) );
	if ( in_array( $role, PROSPERGENICS_ROLES_ASSIGNABLE, true ) ) {
		prospergenics_roles_assign( (int) $user_id, $role );
		// These hooks run before WordPress saves the form itself (edit_user()), and that save calls
		// set_role() with the main Role field, which drops every other role, the platform role included.
		// Apply it again once WordPress has saved (profile_update runs after the role is written).
		add_action( 'profile_update', function ( $saved_id ) use ( $user_id, $role ) {
			if ( (int) $saved_id === (int) $user_id ) {
				prospergenics_roles_assign( (int) $user_id, $role );
			}
		}, 20 );
	}
}
add_action( 'personal_options_update', 'prospergenics_roles_save_field' );
add_action( 'edit_user_profile_update', 'prospergenics_roles_save_field' );

// A "Platform role" column in the Users list.
add_filter( 'manage_users_columns', function ( $cols ) {
	$cols['pg_platform_role'] = 'Platform role';
	return $cols;
} );
add_filter( 'manage_users_custom_column', function ( $out, $column, $user_id ) {
	if ( 'pg_platform_role' !== $column ) {
		return $out;
	}
	$labels = prospergenics_roles_labels();
	$role   = prospergenics_roles_get( $user_id );
	return esc_html( isset( $labels[ $role ] ) ? $labels[ $role ] : $role );
}, 10, 3 );

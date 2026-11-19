<?php
/**
 * Projects track: the pg_project post type and the contribution model (card #6388; the shared
 * contract for #6388/#6389/#6393).
 *
 * - pg_project is public: /projects/ and /projects/<slug>/. Meta: _pg_project_kind
 *   (project|product|service|employer), _pg_project_url (https), _pg_project_period (free text),
 *   _pg_project_skills (array of strings; the profiles track keeps skills as pg_skill posts per
 *   member, not as a taxonomy, so projects carry plain strings).
 * - _pg_project_contributions: one entry per member, { user_id, role, contribution,
 *   evidence: [ {label, url} ], status: claimed|confirmed|disputed } (plus added / confirmed_by).
 *   The creator is the post_author. Two index rows are kept next to it so lists can be queried
 *   without reading serialized arrays: _pg_project_contributor (one row per member's user ID) and
 *   _pg_project_skill (one row per lower-case skill).
 * - Who sees what: a contribution shows to a viewer only when that member's "portfolio" privacy
 *   section is visible to them (inc/profiles/helpers.php levels, the staff "hidden" flag and
 *   suspension included). A project shows only when it has at least one contribution the viewer
 *   may see (or the viewer created it, contributed to it, or is staff), so a project never exposes
 *   a private portfolio by existing. Staff can hide a project (_pg_project_hidden).
 * - wp-admin and /wp/v2 writes are for editors and administrators (edit_others_posts); members use
 *   the manual forms on their profile and on the project page (forms.php), whose handlers check
 *   the role matrix in helpers.php.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROJECTS_MAX_PER_MEMBER = 100;

add_action( 'init', function () {
    register_post_type( 'pg_project', array(
        'labels'              => array(
            'name'          => 'Projects',
            'singular_name' => 'Project',
            'add_new_item'  => 'Add project',
            'edit_item'     => 'Edit project',
            'all_items'     => 'All projects',
            'search_items'  => 'Search projects',
        ),
        'public'              => true,
        'publicly_queryable'  => true,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_nav_menus'   => false,
        'show_in_rest'        => true,
        'rest_base'           => 'pg_project',
        'rest_controller_class' => 'Prospergenics_Projects_REST_Controller',
        'has_archive'         => 'projects',
        'rewrite'             => array( 'slug' => 'projects', 'with_front' => false ),
        'query_var'           => 'pg_project',
        'menu_icon'           => 'dashicons-portfolio',
        'supports'            => array( 'title', 'editor', 'author' ),
        // A project outlives its creator when others contributed (privacy.php hands it over).
        'delete_with_user'    => false,
        'capability_type'     => 'post',
        // wp-admin and /wp/v2 writes: editors and administrators only. Members edit through the
        // manual forms, whose handlers check the role matrix themselves.
        'capabilities'        => array_merge(
            array_fill_keys( array(
                'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'create_posts',
                'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts',
            ), 'edit_others_posts' ),
            array( 'read' => 'read' )
        ),
        'map_meta_cap'        => true,
    ) );

    $meta = array(
        '_pg_project_kind'   => array( 'type' => 'string', 'schema' => array( 'type' => 'string', 'enum' => array_keys( prospergenics_projects_kinds() ) ) ),
        '_pg_project_url'    => array( 'type' => 'string', 'schema' => array( 'type' => 'string', 'format' => 'uri' ) ),
        '_pg_project_period' => array( 'type' => 'string', 'schema' => array( 'type' => 'string' ) ),
        '_pg_project_skills' => array( 'type' => 'array', 'schema' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ) ),
    );
    foreach ( $meta as $key => $m ) {
        register_post_meta( 'pg_project', $key, array(
            'type'          => $m['type'],
            'single'        => true,
            'show_in_rest'  => array( 'schema' => $m['schema'] ),
            'auth_callback' => function ( $allowed, $meta_key, $post_id ) {
                return current_user_can( 'edit_post', $post_id );
            },
        ) );
    }
    // Contributions, the indexes and the hidden flag are never in /wp/v2: the REST field
    // "contributors" below gives each viewer only what their privacy settings allow.
    foreach ( array( '_pg_project_contributions', '_pg_project_contributor', '_pg_project_skill', '_pg_project_hidden' ) as $key ) {
        register_post_meta( 'pg_project', $key, array(
            'single'        => '_pg_project_contributions' === $key || '_pg_project_hidden' === $key,
            'show_in_rest'  => false,
            'auth_callback' => '__return_false',
        ) );
    }
}, 9 ); // before inc/profiles/routes.php flushes at 10, so a flush there keeps /projects/

// Flush once when this track's rules change (/projects/, /profile/claim/), after every track
// registered its own, so the stored rules are complete.
const PROSPERGENICS_PROJECTS_REWRITE_VERSION = '1';
add_action( 'init', function () {
    if ( get_option( '_pg_projects_rewrite_version' ) !== PROSPERGENICS_PROJECTS_REWRITE_VERSION ) {
        flush_rewrite_rules( false );
        update_option( '_pg_projects_rewrite_version', PROSPERGENICS_PROJECTS_REWRITE_VERSION );
    }
}, 99 );
add_action( 'after_switch_theme', function () {
    delete_option( '_pg_projects_rewrite_version' );
} );

/* ───────────────────────── who is who ───────────────────────── */

/** Staff for projects: the same people who review reported profiles (#6472) or manage members. */
function prospergenics_projects_is_staff( $user_id ) {
    $user_id = (int) $user_id;
    return $user_id > 0 && ( user_can( $user_id, 'edit_users' ) || user_can( $user_id, 'pg_moderate' ) );
}

/** Whether a member is suspended (moderation.php), guarded for when that module is missing. */
function prospergenics_projects_user_suspended( $user_id ) {
    return function_exists( 'prospergenics_profiles_is_suspended' ) && prospergenics_profiles_is_suspended( (int) $user_id );
}

/** The facts prospergenics_projects_can_create() needs, for a user. */
function prospergenics_projects_member_facts( $user_id ) {
    $user_id = (int) $user_id;
    return array(
        'logged_in' => $user_id > 0 && (bool) get_userdata( $user_id ),
        'verified'  => $user_id > 0 && function_exists( 'prospergenics_profiles_is_verified' ) && prospergenics_profiles_is_verified( $user_id ),
        'suspended' => prospergenics_projects_user_suspended( $user_id ),
    );
}

/** Whether a user may add to their portfolio now. */
function prospergenics_projects_user_can_create( $user_id ) {
    return prospergenics_projects_can_create( prospergenics_projects_member_facts( $user_id ) );
}

/** Whether a member is an unclaimed placeholder profile created by staff (claim.php). */
function prospergenics_projects_is_placeholder( $user_id ) {
    return (bool) get_user_meta( (int) $user_id, '_pg_projects_placeholder', true );
}

/**
 * Whether a viewer may see a member's portfolio: the "portfolio" privacy section (which needs the
 * profile itself, and is never visible on a profile hidden by staff), and never a suspended member.
 */
function prospergenics_projects_portfolio_visible( $user_id, $viewer_id ) {
    $user_id = (int) $user_id;
    if ( $user_id < 1 || ! get_userdata( $user_id ) || ! function_exists( 'prospergenics_profiles_visibility_for' ) ) {
        return false;
    }
    if ( prospergenics_projects_user_suspended( $user_id ) && ! prospergenics_projects_is_staff( $viewer_id ) ) {
        return false;
    }
    $vis = prospergenics_profiles_visibility_for( $user_id, (int) $viewer_id );
    return ! empty( $vis['portfolio'] );
}

/* ───────────────────────── reading ───────────────────────── */

/** A pg_project post, or null. */
function prospergenics_projects_get( $project ) {
    $post = get_post( $project );
    return ( $post && 'pg_project' === $post->post_type ) ? $post : null;
}

/** The stored contributions of a project, normalised. */
function prospergenics_projects_contributions( $project_id ) {
    return prospergenics_projects_normalize_contributions( get_post_meta( (int) $project_id, '_pg_project_contributions', true ) );
}

/** The project's own fields. @return array{kind:string,url:string,period:string,skills:string[]} */
function prospergenics_projects_fields( $project_id ) {
    $kind = (string) get_post_meta( (int) $project_id, '_pg_project_kind', true );
    return array(
        'kind'   => isset( prospergenics_projects_kinds()[ $kind ] ) ? $kind : 'project',
        'url'    => prospergenics_projects_clean_https_url( get_post_meta( (int) $project_id, '_pg_project_url', true ) ),
        'period' => (string) get_post_meta( (int) $project_id, '_pg_project_period', true ),
        'skills' => prospergenics_projects_clean_skills( (array) get_post_meta( (int) $project_id, '_pg_project_skills', true ) ),
    );
}

/** Whether staff hid the project. */
function prospergenics_projects_is_hidden( $project_id ) {
    return (bool) get_post_meta( (int) $project_id, '_pg_project_hidden', true );
}

/** Whether the creator's profile is hidden from this viewer (staff flag ARC-15 or suspension). */
function prospergenics_projects_creator_hidden_from( WP_Post $post, $viewer_id ) {
    $creator = (int) $post->post_author;
    if ( ! function_exists( 'prospergenics_profiles_hidden_from' ) || ! function_exists( 'prospergenics_profiles_is_hidden' ) ) {
        return false;
    }
    $hidden = prospergenics_profiles_is_hidden( $creator ) || prospergenics_projects_user_suspended( $creator );
    return prospergenics_profiles_hidden_from( $hidden, $viewer_id, $creator, prospergenics_projects_is_staff( $viewer_id ) );
}

/**
 * The contract's contributors list: the contributions of a project this viewer may see, in the
 * order they were added.
 *
 * @return array<int,array{user_id:int,role:string,contribution:string,evidence:array,status:string}>
 */
function prospergenics_projects_contributors( $project_id, $viewer_id = 0 ) {
    $post = prospergenics_projects_get( $project_id );
    if ( ! $post ) {
        return array();
    }
    $out = array();
    foreach ( prospergenics_projects_contributions( $post->ID ) as $c ) {
        if ( prospergenics_projects_portfolio_visible( $c['user_id'], $viewer_id ) ) {
            $out[] = array(
                'user_id'      => $c['user_id'],
                'role'         => $c['role'],
                'contribution' => $c['contribution'],
                'evidence'     => $c['evidence'],
                'status'       => $c['status'],
            );
        }
    }
    return $out;
}

/**
 * Whether a viewer may see a project at all: published (or they may edit it), not hidden by staff
 * and its creator not hidden, and at least one contribution they may see; the creator, its
 * contributors and staff always see it.
 */
function prospergenics_projects_can_view( $project, $viewer_id = 0 ) {
    $post = prospergenics_projects_get( $project );
    if ( ! $post ) {
        return false;
    }
    $viewer_id = (int) $viewer_id;
    $staff     = prospergenics_projects_is_staff( $viewer_id );
    if ( $staff ) {
        return 'trash' !== $post->post_status;
    }
    if ( 'publish' !== $post->post_status ) {
        return false;
    }
    $list = prospergenics_projects_contributions( $post->ID );
    if ( $viewer_id > 0 && ( (int) $post->post_author === $viewer_id || prospergenics_projects_find_contribution( $list, $viewer_id ) >= 0 ) ) {
        return ! prospergenics_projects_is_hidden( $post->ID ) || (int) $post->post_author === $viewer_id;
    }
    if ( prospergenics_projects_is_hidden( $post->ID ) || prospergenics_projects_creator_hidden_from( $post, $viewer_id ) ) {
        return false;
    }
    return (bool) prospergenics_projects_contributors( $post->ID, $viewer_id );
}

/** IDs of published projects (at most 2000), newest first, optionally filtered by kind and skill. */
function prospergenics_projects_published_ids( $kind = '', $skill = '' ) {
    $meta = array();
    if ( '' !== $kind ) {
        $meta[] = array( 'key' => '_pg_project_kind', 'value' => $kind );
    }
    if ( '' !== $skill ) {
        $meta[] = array( 'key' => '_pg_project_skill', 'value' => prospergenics_projects_skill_key( $skill ) );
    }
    return array_map( 'intval', get_posts( array(
        'post_type'        => 'pg_project',
        'post_status'      => 'publish',
        'posts_per_page'   => 2000,
        'fields'           => 'ids',
        'orderby'          => 'date',
        'order'            => 'DESC',
        'meta_query'       => $meta,
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) ) );
}

/** IDs of the published projects this viewer may see (for /projects/ and /wp/v2/pg_project). */
function prospergenics_projects_visible_ids( $viewer_id, $kind = '', $skill = '' ) {
    return array_values( array_filter( prospergenics_projects_published_ids( $kind, $skill ), function ( $id ) use ( $viewer_id ) {
        return prospergenics_projects_can_view( $id, $viewer_id );
    } ) );
}

/** IDs of the projects a member contributed to (any status), via the contributor index. */
function prospergenics_projects_ids_for_member( $user_id, $statuses = array( 'publish' ) ) {
    return array_map( 'intval', get_posts( array(
        'post_type'        => 'pg_project',
        'post_status'      => $statuses,
        'posts_per_page'   => PROSPERGENICS_PROJECTS_MAX_PER_MEMBER,
        'fields'           => 'ids',
        'meta_key'         => '_pg_project_contributor',
        'meta_value'       => (string) (int) $user_id,
        'orderby'          => 'date',
        'order'            => 'DESC',
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) ) );
}

/**
 * The contract's portfolio list: the member's contributions this viewer may see (nothing when the
 * member's "portfolio" section is not visible to them), newest project first.
 *
 * @return array<int,array{project:WP_Post,role:string,contribution:string,evidence:array,status:string}>
 */
function prospergenics_projects_for_user( $user_id, $viewer_id = 0 ) {
    $user_id = (int) $user_id;
    if ( ! prospergenics_projects_portfolio_visible( $user_id, $viewer_id ) ) {
        return array();
    }
    $out = array();
    foreach ( prospergenics_projects_ids_for_member( $user_id ) as $id ) {
        $post = prospergenics_projects_get( $id );
        if ( ! $post || ! prospergenics_projects_can_view( $post, $viewer_id ) ) {
            continue;
        }
        $list = prospergenics_projects_contributions( $post->ID );
        $i    = prospergenics_projects_find_contribution( $list, $user_id );
        if ( $i < 0 ) {
            continue;
        }
        $out[] = array(
            'project'      => $post,
            'role'         => $list[ $i ]['role'],
            'contribution' => $list[ $i ]['contribution'],
            'evidence'     => $list[ $i ]['evidence'],
            'status'       => $list[ $i ]['status'],
        );
    }
    return $out;
}

/** The contract's project URL: /projects/<slug>/. */
function prospergenics_projects_url( $project_id ) {
    $post = prospergenics_projects_get( $project_id );
    return $post ? (string) get_permalink( $post ) : '';
}

/**
 * The portfolio as plain data (REST output of /profile/users/<slug> and the profile template), in
 * the shape of prospergenics_projects_for_user() with the project flattened.
 */
function prospergenics_projects_portfolio_data( $user_id, $viewer_id = 0 ) {
    $out = array();
    foreach ( prospergenics_projects_for_user( $user_id, $viewer_id ) as $e ) {
        $p     = $e['project'];
        $f     = prospergenics_projects_fields( $p->ID );
        $out[] = array(
            'project_id'   => (int) $p->ID,
            'title'        => wp_strip_all_tags( get_the_title( $p ) ),
            'url'          => prospergenics_projects_url( $p->ID ),
            'kind'         => $f['kind'],
            'link'         => $f['url'],
            'period'       => $f['period'],
            'role'         => $e['role'],
            'contribution' => $e['contribution'],
            'evidence'     => $e['evidence'],
            'status'       => $e['status'],
            'is_creator'   => (int) $p->post_author === (int) $user_id,
        );
    }
    return $out;
}

/** Number of contributions a logged-out visitor may see (the indexable rule). */
function prospergenics_projects_public_contribution_count( $project_id ) {
    return count( prospergenics_projects_contributors( $project_id, 0 ) );
}

/** Whether a project page may be indexed (and listed in the sitemap). */
function prospergenics_projects_is_indexable( $project_id ) {
    $post = prospergenics_projects_get( $project_id );
    if ( ! $post ) {
        return false;
    }
    return prospergenics_projects_indexable(
        $post->post_status,
        prospergenics_projects_is_hidden( $post->ID ),
        prospergenics_projects_creator_hidden_from( $post, 0 ),
        prospergenics_projects_public_contribution_count( $post->ID )
    );
}

/** Skills used by published projects, for the /projects/ filter: key => label. */
function prospergenics_projects_skill_options() {
    $out = array();
    foreach ( prospergenics_projects_published_ids() as $id ) {
        foreach ( prospergenics_projects_fields( $id )['skills'] as $s ) {
            $key = prospergenics_projects_skill_key( $s );
            if ( ! isset( $out[ $key ] ) ) {
                $out[ $key ] = $s;
            }
        }
    }
    uasort( $out, 'strcasecmp' );
    return $out;
}

/**
 * Projects a member could join: published, visible to them, matching a search, not already in
 * their portfolio. At most 10.
 *
 * @return WP_Post[]
 */
function prospergenics_projects_search( $query, $user_id ) {
    $query = prospergenics_projects_clip( $query, 80 );
    if ( '' === $query ) {
        return array();
    }
    $posts = get_posts( array(
        'post_type'        => 'pg_project',
        'post_status'      => 'publish',
        's'                => $query,
        'posts_per_page'   => 30,
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) );
    $mine = prospergenics_projects_ids_for_member( $user_id );
    $out  = array();
    foreach ( $posts as $p ) {
        if ( ! in_array( (int) $p->ID, $mine, true ) && prospergenics_projects_can_view( $p, $user_id ) ) {
            $out[] = $p;
        }
        if ( count( $out ) >= 10 ) {
            break;
        }
    }
    return $out;
}

/* ───────────────────────── writing ───────────────────────── */

/** Store the contributions list and rebuild the contributor index. */
function prospergenics_projects_store_contributions( $project_id, array $list ) {
    $project_id = (int) $project_id;
    $list       = prospergenics_projects_normalize_contributions( $list );
    update_post_meta( $project_id, '_pg_project_contributions', $list );
    delete_post_meta( $project_id, '_pg_project_contributor' );
    foreach ( $list as $c ) {
        add_post_meta( $project_id, '_pg_project_contributor', (string) (int) $c['user_id'] );
    }
    do_action( 'prospergenics_platform_content_saved', $project_id, 'pg_project' );
}

/** Store the project's own fields (validated) and rebuild the skill index. */
function prospergenics_projects_store_fields( $project_id, array $fields ) {
    $project_id = (int) $project_id;
    update_post_meta( $project_id, '_pg_project_kind', $fields['kind'] );
    if ( '' !== $fields['url'] ) {
        update_post_meta( $project_id, '_pg_project_url', $fields['url'] );
    } else {
        delete_post_meta( $project_id, '_pg_project_url' );
    }
    update_post_meta( $project_id, '_pg_project_period', $fields['period'] );
    update_post_meta( $project_id, '_pg_project_skills', $fields['skills'] );
    delete_post_meta( $project_id, '_pg_project_skill' );
    foreach ( $fields['skills'] as $s ) {
        add_post_meta( $project_id, '_pg_project_skill', prospergenics_projects_skill_key( $s ) );
    }
}

/**
 * Create a project with its creator's own contribution. Both must be validated.
 *
 * @return int|WP_Error project ID
 */
function prospergenics_projects_create( $creator_id, array $project, array $contribution ) {
    if ( count( prospergenics_projects_ids_for_member( $creator_id, array( 'publish', 'draft', 'pending', 'private' ) ) ) >= PROSPERGENICS_PROJECTS_MAX_PER_MEMBER ) {
        return new WP_Error( 'pg_projects_limit', sprintf( 'A portfolio holds at most %d entries.', PROSPERGENICS_PROJECTS_MAX_PER_MEMBER ), array( 'status' => 400 ) );
    }
    // plain text: never let the KSES-less insert store markup
    $id = wp_insert_post( wp_slash( array(
        'post_type'    => 'pg_project',
        'post_status'  => 'publish',
        'post_author'  => (int) $creator_id,
        'post_title'   => $project['title'],
        'post_content' => sanitize_textarea_field( $project['description'] ),
    ) ), true );
    if ( is_wp_error( $id ) ) {
        return $id;
    }
    prospergenics_projects_store_fields( $id, $project );
    prospergenics_projects_store_contributions( $id, array( array_merge( $contribution, array(
        'user_id' => (int) $creator_id,
        'status'  => 'claimed',
        'added'   => time(),
    ) ) ) );
    return (int) $id;
}

/** Update the project's own fields (validated). @return true|WP_Error */
function prospergenics_projects_update( $project_id, array $project ) {
    $r = wp_update_post( wp_slash( array(
        'ID'           => (int) $project_id,
        'post_title'   => $project['title'],
        'post_content' => sanitize_textarea_field( $project['description'] ),
    ) ), true );
    if ( is_wp_error( $r ) ) {
        return $r;
    }
    prospergenics_projects_store_fields( $project_id, $project );
    do_action( 'prospergenics_platform_content_saved', (int) $project_id, 'pg_project' );
    return true;
}

/**
 * Add a member's contribution to a project, or update theirs (an edit of the text puts a
 * confirmed or disputed contribution back to "claimed").
 *
 * @return string added|updated|unchanged, or WP_Error
 */
function prospergenics_projects_save_contribution( $project_id, $user_id, array $contribution ) {
    $list = prospergenics_projects_contributions( $project_id );
    $i    = prospergenics_projects_find_contribution( $list, $user_id );
    if ( $i < 0 ) {
        if ( count( prospergenics_projects_ids_for_member( $user_id, array( 'publish', 'draft', 'pending', 'private' ) ) ) >= PROSPERGENICS_PROJECTS_MAX_PER_MEMBER ) {
            return new WP_Error( 'pg_projects_limit', sprintf( 'A portfolio holds at most %d entries.', PROSPERGENICS_PROJECTS_MAX_PER_MEMBER ), array( 'status' => 400 ) );
        }
        $list[] = array_merge( $contribution, array( 'user_id' => (int) $user_id, 'status' => 'claimed', 'added' => time(), 'confirmed_by' => 0 ) );
        prospergenics_projects_store_contributions( $project_id, $list );
        do_action( 'prospergenics_projects_contribution_added', (int) $project_id, (int) $user_id );
        return 'added';
    }
    $old    = $list[ $i ];
    $new    = array_merge( $old, $contribution );
    $status = prospergenics_projects_status_after_edit( $old, $new );
    if ( $new === $old ) {
        return 'unchanged';
    }
    $new['status'] = $status;
    if ( 'claimed' === $status ) {
        $new['confirmed_by'] = 0;
    }
    $list[ $i ] = $new;
    prospergenics_projects_store_contributions( $project_id, $list );
    return 'updated';
}

/** Change a contribution's status (confirm / dispute / back to claimed). @return true|WP_Error */
function prospergenics_projects_set_status( $project_id, $user_id, $status, $by ) {
    $list = prospergenics_projects_contributions( $project_id );
    $i    = prospergenics_projects_find_contribution( $list, $user_id );
    if ( $i < 0 ) {
        return new WP_Error( 'pg_projects_no_contribution', 'This member has no contribution on this project.', array( 'status' => 404 ) );
    }
    if ( ! prospergenics_projects_status_change_allowed( $list[ $i ]['status'], $status ) ) {
        return new WP_Error( 'pg_projects_status', 'That status change is not possible.', array( 'status' => 400 ) );
    }
    $list[ $i ]['status']       = $status;
    $list[ $i ]['confirmed_by'] = 'confirmed' === $status ? (int) $by : 0;
    prospergenics_projects_store_contributions( $project_id, $list );
    do_action( 'prospergenics_projects_status_changed', (int) $project_id, (int) $user_id, $status, (int) $by );
    return true;
}

/**
 * Remove a member's contribution. When nobody is left on the project, the project goes too (it was
 * only ever theirs).
 *
 * @return string removed|deleted, or WP_Error
 */
function prospergenics_projects_remove_contribution( $project_id, $user_id ) {
    $list = prospergenics_projects_contributions( $project_id );
    $i    = prospergenics_projects_find_contribution( $list, $user_id );
    if ( $i < 0 ) {
        return new WP_Error( 'pg_projects_no_contribution', 'There is no such contribution on this project.', array( 'status' => 404 ) );
    }
    array_splice( $list, $i, 1 );
    if ( ! $list ) {
        prospergenics_projects_delete( $project_id );
        return 'deleted';
    }
    prospergenics_projects_store_contributions( $project_id, $list );
    return 'removed';
}

/** Delete a project permanently. */
function prospergenics_projects_delete( $project_id ) {
    $post = prospergenics_projects_get( $project_id );
    if ( ! $post ) {
        return false;
    }
    wp_delete_post( $post->ID, true );
    do_action( 'prospergenics_platform_content_deleted', (int) $post->ID, 'pg_project' );
    return true;
}

/** Hide (or show again) a project: staff moderation. */
function prospergenics_projects_set_hidden( $project_id, $hidden, $by ) {
    if ( $hidden ) {
        update_post_meta( (int) $project_id, '_pg_project_hidden', array( 'time' => time(), 'by' => (int) $by ) );
    } else {
        delete_post_meta( (int) $project_id, '_pg_project_hidden' );
    }
}

/* ───────────────────────── /wp/v2/pg_project: a safe schema ───────────────────────── */

add_action( 'rest_api_init', function () {
    if ( ! class_exists( 'Prospergenics_Projects_REST_Controller' ) ) {
        return;
    }
    register_rest_field( 'pg_project', 'contributors', array(
        'get_callback' => function ( $obj ) {
            $out = array();
            foreach ( prospergenics_projects_contributors( (int) $obj['id'], get_current_user_id() ) as $c ) {
                $u     = get_userdata( $c['user_id'] );
                $out[] = array(
                    'name'         => $u ? $u->display_name : '',
                    'profile_url'  => function_exists( 'prospergenics_profiles_url' ) ? prospergenics_profiles_url( $c['user_id'] ) : '',
                    'role'         => $c['role'],
                    'contribution' => $c['contribution'],
                    'evidence'     => $c['evidence'],
                    'status'       => $c['status'],
                );
            }
            return $out;
        },
        'schema'       => array(
            'description' => 'The contributions the current viewer may see, per each member\'s portfolio privacy.',
            'type'        => 'array',
            'context'     => array( 'view', 'edit' ),
            'readonly'    => true,
        ),
    ) );
} );

// The creator's user ID is not part of the public shape (users are not listed over REST either).
add_filter( 'rest_prepare_pg_project', function ( $response ) {
    if ( $response instanceof WP_REST_Response ) {
        $data = $response->get_data();
        unset( $data['author'] );
        $response->set_data( $data );
        $response->remove_link( 'author' );
    }
    return $response;
} );

// The collection only holds what this viewer may see, so its totals are right too.
add_filter( 'rest_pg_project_query', function ( $args ) {
    if ( prospergenics_projects_is_staff( get_current_user_id() ) ) {
        return $args;
    }
    $ids               = prospergenics_projects_visible_ids( get_current_user_id() );
    $args['post__in']  = $ids ? $ids : array( 0 );
    unset( $args['author'], $args['author__in'], $args['author__not_in'] ); // ?author= would reveal creators
    return $args;
} );

add_action( 'init', function () {
    if ( class_exists( 'Prospergenics_Projects_REST_Controller' ) || ! class_exists( 'WP_REST_Posts_Controller' ) ) {
        return;
    }
    /** /wp/v2/pg_project: reads also need prospergenics_projects_can_view() (single items too). */
    class Prospergenics_Projects_REST_Controller extends WP_REST_Posts_Controller {
        public function check_read_permission( $post ) {
            return parent::check_read_permission( $post ) && prospergenics_projects_can_view( $post, get_current_user_id() );
        }
    }
}, 1 );

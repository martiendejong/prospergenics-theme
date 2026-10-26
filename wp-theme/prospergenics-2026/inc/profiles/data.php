<?php
/**
 * Profiles track: data model (card #6343) and the storage behind every other card.
 *
 * - Experience, education and skills are custom post types (pg_experience, pg_education,
 *   pg_skill), one post per entry, linked to the member as post_author. They are not public and
 *   not in /wp/v2: they are read and written through prospergenics/v1/profile/… only, so the
 *   privacy settings apply everywhere.
 * - Everything else is user meta (_pg_profiles_*): headline, location, about, phone, photo,
 *   slug, privacy, featured items, follows, notifications, verification and onboarding state.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROFILES_ENTRY_TYPES = array( 'pg_experience', 'pg_education', 'pg_skill' );
const PROSPERGENICS_PROFILES_MAX_ENTRIES = 50;
// Uploads (audit ARC-13): at most 20 images / 50 MB per member (originals plus generated sizes),
// at most 5 uploads an hour, and unused uploads are deleted after a day.
const PROSPERGENICS_PROFILES_UPLOAD_MAX_FILES = 20;
const PROSPERGENICS_PROFILES_UPLOAD_MAX_BYTES = 52428800;
const PROSPERGENICS_PROFILES_UPLOAD_RATE      = 5;
const PROSPERGENICS_PROFILES_UPLOAD_WINDOW    = 3600;
const PROSPERGENICS_PROFILES_ORPHAN_AGE       = 86400;

add_action( 'init', function () {
    $common = array(
        'public'              => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'show_in_nav_menus'   => false,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'capability_type'     => 'post',
        // The wp-admin screens list every member's entries, private ones included: administrators
        // only. Members edit their own entries on /in/{slug}/ through prospergenics/v1/profile/…,
        // whose permission callbacks check ownership themselves.
        'capabilities'        => array_fill_keys( array(
            'edit_posts', 'edit_others_posts', 'edit_private_posts', 'edit_published_posts', 'publish_posts', 'create_posts',
            'read_private_posts', 'delete_posts', 'delete_private_posts', 'delete_published_posts', 'delete_others_posts',
        ), 'edit_users' ),
        'map_meta_cap'        => true,
        'supports'            => array( 'title', 'editor', 'author' ),
        'menu_icon'           => 'dashicons-id',
    );
    register_post_type( 'pg_experience', array_merge( $common, array(
        'labels' => array( 'name' => 'Profile experience', 'singular_name' => 'Experience entry' ),
    ) ) );
    register_post_type( 'pg_education', array_merge( $common, array(
        'labels'       => array( 'name' => 'Profile education', 'singular_name' => 'Education entry' ),
        'show_in_menu' => 'edit.php?post_type=pg_experience',
    ) ) );
    register_post_type( 'pg_skill', array_merge( $common, array(
        'labels'       => array( 'name' => 'Profile skills', 'singular_name' => 'Skill' ),
        'supports'     => array( 'title', 'author' ),
        'show_in_menu' => 'edit.php?post_type=pg_experience',
    ) ) );
} );

/* ───────────────────────── user meta ───────────────────────── */

/** Read a profile meta value. */
function prospergenics_profiles_meta( $user_id, $key, $default = '' ) {
    $v = get_user_meta( (int) $user_id, '_pg_profiles_' . $key, true );
    return ( '' === $v || null === $v || false === $v ) ? $default : $v;
}

/** Write a profile meta value and drop the cached completion score. */
function prospergenics_profiles_set_meta( $user_id, $key, $value ) {
    update_user_meta( (int) $user_id, '_pg_profiles_' . $key, $value );
    prospergenics_profiles_invalidate( $user_id );
}

/** Drop the cached completion score (card #6353: invalidated on profile update). */
function prospergenics_profiles_invalidate( $user_id ) {
    delete_user_meta( (int) $user_id, '_pg_profiles_completion' );
}

/**
 * The defaults that apply to this member's sections they never chose a level for (audit ARC-11:
 * administrators private, finished onboarding public, everyone else verified members only).
 */
function prospergenics_profiles_member_defaults( $user_id ) {
    $state = prospergenics_profiles_normalize_onboarding( get_user_meta( (int) $user_id, '_pg_profiles_onboarding', true ) );
    return prospergenics_profiles_privacy_defaults(
        prospergenics_profiles_default_profile_level( user_can( (int) $user_id, 'manage_options' ), $state['status'] )
    );
}

/** Privacy settings of a member, merged over their defaults. */
function prospergenics_profiles_privacy( $user_id ) {
    return prospergenics_profiles_normalize_privacy( get_user_meta( (int) $user_id, '_pg_profiles_privacy', true ), prospergenics_profiles_member_defaults( $user_id ) );
}

/** Whether the member chose a level for a section themselves (else it follows their defaults). */
function prospergenics_profiles_privacy_chosen( $user_id, $section ) {
    $stored = get_user_meta( (int) $user_id, '_pg_profiles_privacy', true );
    return is_array( $stored ) && isset( $stored[ $section ] );
}

/**
 * Store privacy choices. Only the sections in $choices are stored as chosen; the others keep
 * following the member's defaults (so a REST call that changes the email level doesn't fix the
 * profile at its current default).
 *
 * @param array<string,string> $choices section => level
 */
function prospergenics_profiles_save_privacy( $user_id, array $choices ) {
    $stored = get_user_meta( (int) $user_id, '_pg_profiles_privacy', true );
    $stored = is_array( $stored ) ? $stored : array();
    $levels = prospergenics_profiles_levels();
    foreach ( prospergenics_profiles_privacy_defaults() as $section => $unused ) {
        if ( isset( $choices[ $section ] ) && is_string( $choices[ $section ] ) && isset( $levels[ $choices[ $section ] ] ) ) {
            $stored[ $section ] = $choices[ $section ];
        }
    }
    prospergenics_profiles_set_meta( $user_id, 'privacy', array_intersect_key( $stored, prospergenics_profiles_privacy_defaults() ) );
}

/**
 * Audit ARC-10, once per site: members who saved the old settings form stored the old email
 * default ('members'); turn that into 'private' (in batches of 200 per request until done).
 */
function prospergenics_profiles_migrate_privacy_settings() {
    if ( get_option( '_pg_profiles_privacy_v2' ) ) {
        return;
    }
    $ids = get_users( array(
        'fields'      => 'ID',
        'number'      => 200,
        'count_total' => false,
        'meta_query'  => array(
            'relation' => 'AND',
            array( 'key' => '_pg_profiles_privacy', 'compare' => 'EXISTS' ),
            array( 'key' => '_pg_profiles_privacy_migrated', 'compare' => 'NOT EXISTS' ),
        ),
    ) );
    foreach ( $ids as $id ) {
        $stored = get_user_meta( (int) $id, '_pg_profiles_privacy', true );
        $new    = prospergenics_profiles_migrate_privacy( $stored );
        if ( $new !== $stored ) {
            update_user_meta( (int) $id, '_pg_profiles_privacy', $new );
        }
        update_user_meta( (int) $id, '_pg_profiles_privacy_migrated', time() );
    }
    if ( count( $ids ) < 200 ) {
        update_option( '_pg_profiles_privacy_v2', time(), true );
    }
}
add_action( 'init', 'prospergenics_profiles_migrate_privacy_settings', 21 );

/* ───────────────────────── slugs and /in/{slug} ───────────────────────── */

/** Find the member a slug belongs to (current slug only). */
function prospergenics_profiles_user_by_slug( $slug ) {
    $slug = sanitize_title( (string) $slug );
    if ( '' === $slug ) {
        return null;
    }
    $users = get_users( array(
        'meta_key'    => '_pg_profiles_slug',
        'meta_value'  => $slug,
        'number'      => 1,
        'count_total' => false,
    ) );
    return $users ? $users[0] : null;
}

/** Find the member who used to have this slug (for the 301 after a rename). */
function prospergenics_profiles_user_by_old_slug( $slug ) {
    $slug = sanitize_title( (string) $slug );
    if ( '' === $slug ) {
        return null;
    }
    $users = get_users( array(
        'meta_key'    => '_pg_profiles_old_slug',
        'meta_value'  => $slug,
        'number'      => 1,
        'count_total' => false,
    ) );
    return $users ? $users[0] : null;
}

/** Whether a slug is in use (now or before) by someone other than $except. */
function prospergenics_profiles_slug_taken( $slug, $except = 0 ) {
    foreach ( array( '_pg_profiles_slug', '_pg_profiles_old_slug' ) as $key ) {
        $ids = get_users( array( 'meta_key' => $key, 'meta_value' => $slug, 'fields' => 'ID', 'number' => 2, 'count_total' => false ) );
        foreach ( $ids as $id ) {
            if ( (int) $id !== (int) $except ) {
                return true;
            }
        }
    }
    return false;
}

/** The slug a member's name gives now. */
function prospergenics_profiles_slug_for_name( $user ) {
    $base = prospergenics_profiles_slug_base( $user->first_name, $user->last_name, $user->display_name, $user->user_login, $user->user_nicename, $user->ID );
    return prospergenics_profiles_unique_slug( $base, function ( $slug ) use ( $user ) {
        return prospergenics_profiles_slug_taken( $slug, $user->ID );
    } );
}

/**
 * A member's slug, assigned on first use. (When jengo-iam usernames arrive, filter
 * 'prospergenics_profiles_slug' or write _pg_profiles_slug from the IAM sync.)
 */
function prospergenics_profiles_slug( $user_id ) {
    $slug = (string) get_user_meta( (int) $user_id, '_pg_profiles_slug', true );
    if ( '' === $slug ) {
        $user = get_userdata( (int) $user_id );
        if ( ! $user ) {
            return '';
        }
        $slug = prospergenics_profiles_slug_for_name( $user );
        update_user_meta( $user->ID, '_pg_profiles_slug', $slug );
    }
    return (string) apply_filters( 'prospergenics_profiles_slug', $slug, (int) $user_id );
}

/** Public profile URL. */
function prospergenics_profiles_url( $user_id, $args = array() ) {
    $url = home_url( '/in/' . prospergenics_profiles_slug( $user_id ) . '/' );
    return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Re-slug when the name changes; the old slug keeps redirecting (301) to the new one.
 */
function prospergenics_profiles_maybe_reslug( $user_id ) {
    $user = get_userdata( (int) $user_id );
    $old  = (string) get_user_meta( (int) $user_id, '_pg_profiles_slug', true );
    if ( ! $user || '' === $old ) {
        return;
    }
    $base = prospergenics_profiles_slug_base( $user->first_name, $user->last_name, $user->display_name, $user->user_login, $user->user_nicename, $user->ID );
    if ( $old === $base || ( 0 === strpos( $old, $base . '-' ) && preg_match( '/^-\d+$/', substr( $old, strlen( $base ) ) ) ) ) {
        return; // same name (possibly de-duplicated): keep the slug
    }
    $new = prospergenics_profiles_slug_for_name( $user );
    if ( $new === $old ) {
        return;
    }
    $old_slugs = get_user_meta( $user->ID, '_pg_profiles_old_slug', false );
    if ( ! in_array( $old, $old_slugs, true ) ) {
        add_user_meta( $user->ID, '_pg_profiles_old_slug', $old );
    }
    delete_user_meta( $user->ID, '_pg_profiles_old_slug', $new );
    update_user_meta( $user->ID, '_pg_profiles_slug', $new );
}
add_action( 'profile_update', 'prospergenics_profiles_maybe_reslug', 20 );

// Every member gets a slug when the account is created, so /in/{slug}/ resolves straight away.
add_action( 'user_register', 'prospergenics_profiles_slug', 5 );

/**
 * Accounts that existed before profiles shipped get their slug in batches of 200 per request
 * until none are left (then a single option read per request).
 */
function prospergenics_profiles_backfill_slugs() {
    if ( get_option( '_pg_profiles_slugs_done' ) ) {
        return;
    }
    $ids = get_users( array(
        'fields'      => 'ID',
        'number'      => 200,
        'count_total' => false,
        'meta_query'  => array( array( 'key' => '_pg_profiles_slug', 'compare' => 'NOT EXISTS' ) ),
    ) );
    foreach ( $ids as $id ) {
        prospergenics_profiles_slug( (int) $id );
    }
    if ( count( $ids ) < 200 ) {
        update_option( '_pg_profiles_slugs_done', 1, true );
    }
}
add_action( 'init', 'prospergenics_profiles_backfill_slugs', 20 );

/* ───────────────────────── entries (CPTs) ───────────────────────── */

/** Shape an entry post for output. */
function prospergenics_profiles_entry_data( $post, $viewer_id = 0 ) {
    $post = get_post( $post );
    if ( ! $post || ! in_array( $post->post_type, PROSPERGENICS_PROFILES_ENTRY_TYPES, true ) ) {
        return null;
    }
    $m = function ( $k ) use ( $post ) {
        return get_post_meta( $post->ID, '_pg_profiles_' . $k, true );
    };
    switch ( $post->post_type ) {
        case 'pg_experience':
            return array(
                'id'          => $post->ID,
                'title'       => $post->post_title,
                'company'     => (string) $m( 'company' ),
                'start'       => (string) $m( 'start' ),
                'end'         => (string) $m( 'end' ),
                'current'     => (bool) $m( 'current' ),
                'description' => $post->post_content,
            );
        case 'pg_education':
            return array(
                'id'          => $post->ID,
                'institution' => $post->post_title,
                'degree'      => (string) $m( 'degree' ),
                'field'       => (string) $m( 'field' ),
                'start_year'  => (int) $m( 'start_year' ),
                'end_year'    => (int) $m( 'end_year' ),
            );
        default:
            $endorsers = array_map( 'intval', get_post_meta( $post->ID, '_pg_profiles_endorser', false ) );
            return array(
                'id'             => $post->ID,
                'name'           => $post->post_title,
                'pinned'         => (bool) $m( 'pinned' ),
                'endorsements'   => count( $endorsers ),
                'endorsed_by_me' => $viewer_id > 0 && in_array( (int) $viewer_id, $endorsers, true ),
            );
    }
}

/** All entries of one type for a member, sorted per its card. */
function prospergenics_profiles_entries( $user_id, $type, $viewer_id = 0 ) {
    $posts = get_posts( array(
        'post_type'        => $type,
        'post_status'      => 'publish',
        'author'           => (int) $user_id,
        'posts_per_page'   => PROSPERGENICS_PROFILES_MAX_ENTRIES,
        'orderby'          => 'ID',
        'order'            => 'ASC',
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) );
    $items = array();
    foreach ( $posts as $p ) {
        if ( (int) $p->post_author === (int) $user_id ) {
            $items[] = prospergenics_profiles_entry_data( $p, $viewer_id );
        }
    }
    if ( 'pg_experience' === $type ) {
        return prospergenics_profiles_sort_experience( $items );
    }
    if ( 'pg_education' === $type ) {
        return prospergenics_profiles_sort_education( $items );
    }
    return prospergenics_profiles_sort_skills( $items );
}

/** Number of entries of a type a member has. */
function prospergenics_profiles_entry_count( $user_id, $type ) {
    $q = new WP_Query( array(
        'post_type'      => $type,
        'post_status'    => 'publish',
        'author'         => (int) $user_id,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    ) );
    return (int) $q->found_posts;
}

/**
 * Create or update an entry for $user_id. Fields must be validated already.
 *
 * @return int|WP_Error post ID
 */
function prospergenics_profiles_save_entry( $user_id, $type, array $fields, $post_id = 0 ) {
    $post = array(
        'post_type'   => $type,
        'post_status' => 'publish',
        'post_author' => (int) $user_id,
    );
    $meta = array();
    switch ( $type ) {
        case 'pg_experience':
            $post['post_title']   = $fields['title'];
            $post['post_content'] = $fields['description'];
            $meta = array(
                'company' => $fields['company'],
                'start'   => $fields['start'],
                'end'     => $fields['end'],
                'current' => $fields['current'] ? 1 : 0,
            );
            break;
        case 'pg_education':
            $post['post_title'] = $fields['institution'];
            $meta = array(
                'degree'     => $fields['degree'],
                'field'      => $fields['field'],
                'start_year' => $fields['start_year'],
                'end_year'   => $fields['end_year'],
            );
            break;
        case 'pg_skill':
            $post['post_title'] = $fields['name'];
            if ( array_key_exists( 'pinned', $fields ) ) {
                $meta['pinned'] = $fields['pinned'] ? 1 : 0;
            }
            break;
        default:
            return new WP_Error( 'pg_profiles_type', 'Unknown entry type.' );
    }
    // the content is plain text: never let the KSES-less insert store markup
    if ( isset( $post['post_content'] ) ) {
        $post['post_content'] = sanitize_textarea_field( $post['post_content'] );
    }
    if ( $post_id ) {
        $post['ID'] = (int) $post_id;
        $result     = wp_update_post( wp_slash( $post ), true );
    } else {
        $result = wp_insert_post( wp_slash( $post ), true );
    }
    if ( is_wp_error( $result ) ) {
        return $result;
    }
    foreach ( $meta as $k => $v ) {
        update_post_meta( $result, '_pg_profiles_' . $k, $v );
    }
    prospergenics_profiles_invalidate( $user_id );
    do_action( 'prospergenics_platform_content_saved', (int) $result, $type );
    return (int) $result;
}

/** Delete an entry (permanently: these are not content anyone restores from the trash). */
function prospergenics_profiles_delete_entry( $post_id ) {
    $post = get_post( (int) $post_id );
    if ( ! $post ) {
        return false;
    }
    wp_delete_post( $post->ID, true );
    prospergenics_profiles_invalidate( $post->post_author );
    do_action( 'prospergenics_platform_content_deleted', (int) $post->ID, $post->post_type );
    return true;
}

/* ───────────────────────── follows (card #6349) ───────────────────────── */

/** Whether $follower follows $target. */
function prospergenics_profiles_follows( $follower_id, $target_id ) {
    if ( ! $follower_id || ! $target_id ) {
        return false;
    }
    return in_array( (string) (int) $target_id, array_map( 'strval', get_user_meta( (int) $follower_id, '_pg_profiles_follows', false ) ), true );
}

function prospergenics_profiles_follow( $follower_id, $target_id ) {
    if ( (int) $follower_id === (int) $target_id || prospergenics_profiles_follows( $follower_id, $target_id ) ) {
        return false;
    }
    add_user_meta( (int) $follower_id, '_pg_profiles_follows', (string) (int) $target_id );
    return true;
}

function prospergenics_profiles_unfollow( $follower_id, $target_id ) {
    return delete_user_meta( (int) $follower_id, '_pg_profiles_follows', (string) (int) $target_id );
}

/** IDs this member follows. */
function prospergenics_profiles_following_ids( $user_id ) {
    $ids = array_map( 'intval', get_user_meta( (int) $user_id, '_pg_profiles_follows', false ) );
    return array_values( array_filter( array_unique( $ids ), function ( $id ) {
        return $id > 0 && get_userdata( $id );
    } ) );
}

/** IDs following this member. */
function prospergenics_profiles_follower_ids( $user_id ) {
    global $wpdb;
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT um.user_id FROM {$wpdb->usermeta} um INNER JOIN {$wpdb->users} u ON u.ID = um.user_id WHERE um.meta_key = %s AND um.meta_value = %s",
        '_pg_profiles_follows',
        (string) (int) $user_id
    ) );
    return array_map( 'intval', $ids );
}

/** Connection = someone the owner follows (owner-controlled, so "connections only" means something). */
function prospergenics_profiles_is_connection( $owner_id, $viewer_id ) {
    return prospergenics_profiles_follows( $owner_id, $viewer_id );
}

/** Whether the ProsperGenics team hid this member's profile (ARC-15; users-admin.php sets it). */
function prospergenics_profiles_is_hidden( $user_id ) {
    return (bool) get_user_meta( (int) $user_id, '_pg_profiles_hidden', true );
}

/**
 * Sections the viewer may see on a member's profile ('members' = verified members, ARC-10). A
 * profile hidden by staff (ARC-15) shows nothing to anyone but its owner and staff.
 */
function prospergenics_profiles_visibility_for( $owner_id, $viewer_id ) {
    $staff = $viewer_id && user_can( (int) $viewer_id, 'edit_users' );
    if ( prospergenics_profiles_hidden_from( prospergenics_profiles_is_hidden( $owner_id ), $viewer_id, $owner_id, $staff ) ) {
        return array_fill_keys( array_keys( prospergenics_profiles_privacy_defaults() ), false );
    }
    return prospergenics_profiles_visible_sections(
        prospergenics_profiles_privacy( $owner_id ),
        (int) $viewer_id,
        (int) $owner_id,
        $viewer_id ? prospergenics_profiles_is_connection( $owner_id, $viewer_id ) : false,
        $viewer_id && prospergenics_profiles_is_verified( $viewer_id )
    );
}

/* ───────────────────────── endorsements (card #6348) ───────────────────────── */

/**
 * Endorse a skill once. Returns the new count, or WP_Error.
 */
function prospergenics_profiles_endorse( $skill_id, $endorser_id ) {
    $skill = get_post( (int) $skill_id );
    if ( ! $skill || 'pg_skill' !== $skill->post_type ) {
        return new WP_Error( 'pg_profiles_not_found', 'Skill not found.', array( 'status' => 404 ) );
    }
    if ( (int) $skill->post_author === (int) $endorser_id ) {
        return new WP_Error( 'pg_profiles_own_skill', 'You cannot endorse your own skills.', array( 'status' => 403 ) );
    }
    $endorsers = array_map( 'intval', get_post_meta( $skill->ID, '_pg_profiles_endorser', false ) );
    if ( ! in_array( (int) $endorser_id, $endorsers, true ) ) {
        add_post_meta( $skill->ID, '_pg_profiles_endorser', (int) $endorser_id );
        $endorsers[] = (int) $endorser_id;
        // notify once per endorser and skill, so withdrawing and re-endorsing doesn't spam the owner
        $notified = array_map( 'intval', get_post_meta( $skill->ID, '_pg_profiles_notified', false ) );
        if ( ! in_array( (int) $endorser_id, $notified, true ) ) {
            add_post_meta( $skill->ID, '_pg_profiles_notified', (int) $endorser_id );
            prospergenics_profiles_notify_endorsement( $skill, $endorser_id );
        }
    }
    return count( $endorsers );
}

function prospergenics_profiles_unendorse( $skill_id, $endorser_id ) {
    delete_post_meta( (int) $skill_id, '_pg_profiles_endorser', (int) $endorser_id );
    return count( get_post_meta( (int) $skill_id, '_pg_profiles_endorser', false ) );
}

/** Tell the owner: an on-site notification on their profile, plus an email. */
function prospergenics_profiles_notify_endorsement( $skill, $endorser_id ) {
    $owner    = get_userdata( (int) $skill->post_author );
    $endorser = get_userdata( (int) $endorser_id );
    if ( ! $owner || ! $endorser ) {
        return;
    }
    $list = get_user_meta( $owner->ID, '_pg_profiles_notifications', true );
    $list = is_array( $list ) ? $list : array();
    array_unshift( $list, array(
        'type'  => 'endorsement',
        'from'  => (int) $endorser->ID,
        'skill' => (string) $skill->post_title,
        'time'  => time(),
        'read'  => false,
    ) );
    update_user_meta( $owner->ID, '_pg_profiles_notifications', array_slice( $list, 0, 30 ) );

    $subject = sprintf( '%s endorsed you for %s', $endorser->display_name, $skill->post_title );
    $body    = sprintf(
        "Hi %s,\n\n%s endorsed you for \"%s\" on ProsperGenics.\n\nSee your profile: %s\nTheir profile: %s\n",
        $owner->display_name,
        $endorser->display_name,
        $skill->post_title,
        prospergenics_profiles_url( $owner->ID ),
        prospergenics_profiles_url( $endorser->ID )
    );
    prospergenics_profiles_send_mail( $owner->user_email, wp_specialchars_decode( $subject ), $body, 'endorsement', $owner->ID );
}

/** Unread notifications of a member. */
function prospergenics_profiles_unread_notifications( $user_id ) {
    $list = get_user_meta( (int) $user_id, '_pg_profiles_notifications', true );
    if ( ! is_array( $list ) ) {
        return array();
    }
    return array_values( array_filter( $list, function ( $n ) {
        return is_array( $n ) && empty( $n['read'] );
    } ) );
}

function prospergenics_profiles_mark_notifications_read( $user_id ) {
    $list = get_user_meta( (int) $user_id, '_pg_profiles_notifications', true );
    if ( ! is_array( $list ) ) {
        return;
    }
    foreach ( $list as &$n ) {
        if ( is_array( $n ) ) {
            $n['read'] = true;
        }
    }
    unset( $n );
    update_user_meta( (int) $user_id, '_pg_profiles_notifications', $list );
}

/* ───────────────────────── completion (card #6353) ───────────────────────── */

/** Completion score, cached in user meta until the next profile change. */
function prospergenics_profiles_get_completion( $user_id ) {
    $cached = get_user_meta( (int) $user_id, '_pg_profiles_completion', true );
    if ( is_array( $cached ) && isset( $cached['percent'], $cached['missing'] ) ) {
        return $cached;
    }
    $result = prospergenics_profiles_completion( array(
        'photo'      => (bool) prospergenics_profiles_photo_id( $user_id ),
        'headline'   => '' !== (string) prospergenics_profiles_meta( $user_id, 'headline' ),
        'experience' => prospergenics_profiles_entry_count( $user_id, 'pg_experience' ) > 0,
        'skills'     => prospergenics_profiles_entry_count( $user_id, 'pg_skill' ) > 0,
        'education'  => prospergenics_profiles_entry_count( $user_id, 'pg_education' ) > 0,
    ) );
    update_user_meta( (int) $user_id, '_pg_profiles_completion', $result );
    return $result;
}

/**
 * Completion a public profile needs before search engines are invited to it (audit ARC-22): below
 * this an /in/ page is mostly empty, so it is noindex and left out of the sitemap. 60% = three
 * of photo, headline, experience, skills and education.
 */
function prospergenics_profiles_index_min_completion() {
    return (int) apply_filters( 'prospergenics_profiles_index_min_completion', PROSPERGENICS_PROFILES_INDEX_MIN );
}

/** Whether a member's /in/ page may be indexed: public, verified, complete enough and not hidden. */
function prospergenics_profiles_is_indexable( $user_id ) {
    $user_id = (int) $user_id;
    return ! prospergenics_profiles_is_hidden( $user_id )
        && prospergenics_profiles_is_verified( $user_id )
        && 'public' === prospergenics_profiles_privacy( $user_id )['profile']
        && prospergenics_profiles_get_completion( $user_id )['percent'] >= prospergenics_profiles_index_min_completion();
}

/**
 * Sitemap entries for the indexable /in/ profiles (inc/sitemap.php lists them).
 *
 * @return array<int,array{loc:string}>
 */
function prospergenics_profiles_sitemap_urls() {
    $ids  = get_users( array(
        'fields'      => 'ID',
        'number'      => 2000,
        'count_total' => false,
        'orderby'     => 'ID',
        'meta_query'  => array( array( 'key' => '_pg_profiles_slug', 'compare' => 'EXISTS' ) ),
    ) );
    $urls = array();
    foreach ( $ids as $id ) {
        if ( prospergenics_profiles_is_indexable( (int) $id ) ) {
            $urls[] = array( 'loc' => prospergenics_profiles_url( (int) $id ) );
        }
    }
    return $urls;
}

/* ───────────────────────── photo (card #6344) ───────────────────────── */

/** The member's photo attachment ID, or 0. */
function prospergenics_profiles_photo_id( $user_id ) {
    $id = (int) get_user_meta( (int) $user_id, '_pg_profiles_photo_id', true );
    return ( $id && get_post( $id ) ) ? $id : 0;
}

/** Photo URL at a size, or ''. */
function prospergenics_profiles_photo_url( $user_id, $size = 'medium' ) {
    $id = prospergenics_profiles_photo_id( $user_id );
    if ( ! $id ) {
        return '';
    }
    $url = wp_get_attachment_image_url( $id, $size );
    return $url ? $url : '';
}

/**
 * Store an uploaded image in the media library, owned by the member.
 *
 * The browser crops to a square first (profiles.js); this re-checks everything and centre-crops
 * anything that still is not square, so a request made without the dialog gets the same result.
 *
 * @param array $file   One entry of $_FILES.
 * @param int   $user_id
 * @param bool  $square Crop to a 1:1 square at most 800px (profile photo) or keep the shape.
 * @return int|WP_Error attachment ID
 */
function prospergenics_profiles_store_image( $file, $user_id, $square ) {
    if ( ! is_array( $file ) || ! isset( $file['tmp_name'], $file['error'] ) || UPLOAD_ERR_OK !== (int) $file['error'] ) {
        return new WP_Error( 'pg_profiles_upload', 'The upload failed. Try again with a JPEG, PNG or WebP image under 5 MB.', array( 'status' => 400 ) );
    }
    if ( (int) $file['size'] > 5 * MB_IN_BYTES ) {
        return new WP_Error( 'pg_profiles_upload_size', 'The image is larger than 5 MB.', array( 'status' => 400 ) );
    }
    $info = @getimagesize( $file['tmp_name'] );
    $mimes = array( 'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif' );
    if ( ! $info || ! isset( $mimes[ $info['mime'] ] ) ) {
        return new WP_Error( 'pg_profiles_upload_type', 'Upload a JPEG, PNG, WebP or GIF image.', array( 'status' => 400 ) );
    }
    if ( $square && ( $info[0] < 200 || $info[1] < 200 ) ) {
        return new WP_Error( 'pg_profiles_upload_small', 'The photo must be at least 200 × 200 pixels.', array( 'status' => 400 ) );
    }
    $allowed = prospergenics_profiles_upload_allowed( $user_id, (int) $file['size'], $square );
    if ( is_wp_error( $allowed ) ) {
        return $allowed;
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';

    $ext  = $mimes[ $info['mime'] ];
    $name = ( $square ? 'profile-photo-' : 'featured-' ) . (int) $user_id . '-' . wp_generate_password( 6, false ) . '.' . $ext;

    if ( $square ) {
        $editor = wp_get_image_editor( $file['tmp_name'] );
        if ( is_wp_error( $editor ) ) {
            return new WP_Error( 'pg_profiles_upload_edit', 'This image could not be processed.', array( 'status' => 400 ) );
        }
        $size = $editor->get_size();
        $side = min( $size['width'], $size['height'] );
        $out  = min( 800, $side );
        $editor->crop( (int) floor( ( $size['width'] - $side ) / 2 ), (int) floor( ( $size['height'] - $side ) / 2 ), $side, $side, $out, $out );
        $saved = $editor->save( $file['tmp_name'] . '-sq.' . $ext );
        if ( is_wp_error( $saved ) ) {
            return new WP_Error( 'pg_profiles_upload_edit', 'This image could not be processed.', array( 'status' => 400 ) );
        }
        $tmp = $saved['path'];
    } else {
        $tmp = prospergenics_profiles_reencode( $file['tmp_name'], $ext );
        if ( '' === $tmp ) {
            $tmp = wp_tempnam( $name );
            if ( ! @copy( $file['tmp_name'], $tmp ) ) {
                return new WP_Error( 'pg_profiles_upload', 'The upload failed.', array( 'status' => 500 ) );
            }
        }
    }

    $sideload = array( 'name' => $name, 'tmp_name' => $tmp, 'error' => 0, 'size' => filesize( $tmp ) );
    $id = media_handle_sideload( $sideload, 0, null, array(
        'post_author' => (int) $user_id,
        'post_title'  => $square ? 'Profile photo' : 'Featured image',
    ) );
    if ( file_exists( $tmp ) ) {
        @unlink( $tmp );
    }
    if ( is_wp_error( $id ) ) {
        return new WP_Error( 'pg_profiles_upload', $id->get_error_message(), array( 'status' => 400 ) );
    }
    update_post_meta( $id, '_pg_profiles_owner', (int) $user_id );
    return (int) $id;
}

/**
 * Re-encode a featured image (at most 2000px on its longest side) so camera metadata such as GPS
 * position never reaches the public file. GD drops all metadata when it writes an image, so it
 * is preferred here; GIFs keep their animation and are stored as uploaded.
 *
 * @return string Path of the re-encoded file, or '' to store the original.
 */
function prospergenics_profiles_reencode( $path, $ext ) {
    if ( 'gif' === $ext ) {
        return '';
    }
    $prefer_gd = function ( $editors ) {
        return array_values( array_unique( array_merge( array( 'WP_Image_Editor_GD' ), (array) $editors ) ) );
    };
    add_filter( 'wp_image_editors', $prefer_gd );
    $editor = wp_get_image_editor( $path );
    remove_filter( 'wp_image_editors', $prefer_gd );
    if ( is_wp_error( $editor ) ) {
        return '';
    }
    $size = $editor->get_size();
    if ( $size['width'] > 2000 || $size['height'] > 2000 ) {
        $editor->resize( 2000, 2000, false );
    }
    $saved = $editor->save( $path . '-re.' . $ext );
    return is_wp_error( $saved ) || empty( $saved['path'] ) ? '' : (string) $saved['path'];
}

/** Disk space an attachment takes: the file, a kept original and every generated size. */
function prospergenics_profiles_attachment_bytes( $attachment_id ) {
    $file = get_attached_file( (int) $attachment_id );
    if ( ! $file ) {
        return 0;
    }
    $dir   = dirname( $file );
    $meta  = wp_get_attachment_metadata( (int) $attachment_id );
    $files = array( $file );
    if ( is_array( $meta ) ) {
        if ( ! empty( $meta['original_image'] ) ) {
            $files[] = $dir . '/' . $meta['original_image'];
        }
        foreach ( isset( $meta['sizes'] ) && is_array( $meta['sizes'] ) ? $meta['sizes'] : array() as $size ) {
            if ( ! empty( $size['file'] ) ) {
                $files[] = $dir . '/' . $size['file'];
            }
        }
    }
    $bytes = 0;
    foreach ( array_unique( $files ) as $f ) {
        $bytes += file_exists( $f ) ? (int) filesize( $f ) : 0;
    }
    return $bytes;
}

/** A member's uploads: attachment IDs and the bytes they take. @return array{ids:int[],bytes:int} */
function prospergenics_profiles_upload_usage( $user_id ) {
    $ids = array_map( 'intval', get_posts( array(
        'post_type'        => 'attachment',
        'post_status'      => 'inherit',
        'posts_per_page'   => -1,
        'fields'           => 'ids',
        'meta_key'         => '_pg_profiles_owner',
        'meta_value'       => (int) $user_id,
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) ) );
    $bytes = 0;
    foreach ( $ids as $id ) {
        $bytes += prospergenics_profiles_attachment_bytes( $id );
    }
    return array( 'ids' => $ids, 'bytes' => $bytes );
}

/**
 * Whether a member may upload one more image now: the hourly rate limit (every attempt counts)
 * and the quota. A new profile photo replaces the current one, so that one is not counted.
 *
 * @return true|WP_Error 429 over the rate, 403 over the quota.
 */
function prospergenics_profiles_upload_allowed( $user_id, $new_bytes, $replaces_photo ) {
    $key  = '_pg_profiles_upload_rl_' . (int) $user_id;
    $rate = prospergenics_profiles_rate_hit( get_transient( $key ), time(), PROSPERGENICS_PROFILES_UPLOAD_RATE, PROSPERGENICS_PROFILES_UPLOAD_WINDOW );
    set_transient( $key, $rate['state'], PROSPERGENICS_PROFILES_UPLOAD_WINDOW );
    if ( ! $rate['allowed'] ) {
        return new WP_Error(
            'pg_profiles_upload_rate',
            sprintf( 'You can upload %d images an hour. Try again in %d minutes.', PROSPERGENICS_PROFILES_UPLOAD_RATE, (int) ceil( $rate['retry'] / 60 ) ),
            array( 'status' => 429 )
        );
    }
    $usage = prospergenics_profiles_upload_usage( $user_id );
    $files = count( $usage['ids'] );
    $bytes = $usage['bytes'];
    $photo = $replaces_photo ? prospergenics_profiles_photo_id( $user_id ) : 0;
    if ( $photo && in_array( $photo, $usage['ids'], true ) ) {
        $files--;
        $bytes -= prospergenics_profiles_attachment_bytes( $photo );
    }
    $over = prospergenics_profiles_quota_check( $files, $bytes, $new_bytes, PROSPERGENICS_PROFILES_UPLOAD_MAX_FILES, PROSPERGENICS_PROFILES_UPLOAD_MAX_BYTES );
    if ( '' !== $over ) {
        return new WP_Error(
            'pg_profiles_upload_quota',
            'files' === $over
                ? sprintf( 'You have reached the limit of %d uploaded images. Remove a featured image first.', PROSPERGENICS_PROFILES_UPLOAD_MAX_FILES )
                : 'Your images use all the space you have (50 MB). Remove a featured image first.',
            array( 'status' => 403 )
        );
    }
    return true;
}

/** Whether an uploaded image is still used: the owner's photo or one of their featured items. */
function prospergenics_profiles_upload_in_use( $attachment_id, $owner_id ) {
    if ( ! $owner_id || ! get_userdata( (int) $owner_id ) ) {
        return false;
    }
    if ( (int) get_user_meta( (int) $owner_id, '_pg_profiles_photo_id', true ) === (int) $attachment_id ) {
        return true;
    }
    return in_array( (int) $attachment_id, array_map( 'intval', wp_list_pluck( prospergenics_profiles_featured( $owner_id ), 'attachment_id' ) ), true );
}

/**
 * Daily: delete member uploads older than a day that are neither the photo nor featured (an image
 * uploaded for a featured item that was never saved, or a member who no longer exists).
 *
 * @return int Number of attachments deleted.
 */
function prospergenics_profiles_cleanup_uploads() {
    global $wpdb;
    $before  = gmdate( 'Y-m-d H:i:s', time() - PROSPERGENICS_PROFILES_ORPHAN_AGE );
    $last    = 0;
    $deleted = 0;
    for ( $batch = 0; $batch < 50; $batch++ ) {
        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT p.ID, pm.meta_value AS owner FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = %s
             WHERE p.post_type = 'attachment' AND p.post_date_gmt < %s AND p.ID > %d ORDER BY p.ID ASC LIMIT 200",
            '_pg_profiles_owner',
            $before,
            $last
        ) );
        if ( ! $rows ) {
            break;
        }
        foreach ( $rows as $row ) {
            $last = (int) $row->ID;
            if ( ! prospergenics_profiles_upload_in_use( $last, (int) $row->owner ) && wp_delete_attachment( $last, true ) ) {
                $deleted++;
            }
        }
    }
    return $deleted;
}
add_action( 'prospergenics_profiles_cleanup_uploads', 'prospergenics_profiles_cleanup_uploads' );
function prospergenics_profiles_schedule_cleanup() {
    if ( ! wp_next_scheduled( 'prospergenics_profiles_cleanup_uploads' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'prospergenics_profiles_cleanup_uploads' );
    }
}
add_action( 'init', 'prospergenics_profiles_schedule_cleanup' );
add_action( 'switch_theme', function () {
    wp_clear_scheduled_hook( 'prospergenics_profiles_cleanup_uploads' );
} );

/** Set (or with 0, remove) the photo; the previous photo is deleted from the media library. */
function prospergenics_profiles_set_photo( $user_id, $attachment_id ) {
    $old = prospergenics_profiles_photo_id( $user_id );
    if ( $old && $old !== (int) $attachment_id && (int) get_post_meta( $old, '_pg_profiles_owner', true ) === (int) $user_id ) {
        wp_delete_attachment( $old, true );
    }
    if ( $attachment_id ) {
        prospergenics_profiles_set_meta( $user_id, 'photo_id', (int) $attachment_id );
    } else {
        delete_user_meta( (int) $user_id, '_pg_profiles_photo_id' );
        prospergenics_profiles_invalidate( $user_id );
    }
}

/*
 * The photo replaces the Gravatar wherever WordPress asks for an avatar (comments, admin bar),
 * but only when the profile is public: an avatar shows to anyone.
 */
add_filter( 'pre_get_avatar_data', function ( $args, $id_or_email ) {
    $user_id = 0;
    if ( is_numeric( $id_or_email ) ) {
        $user_id = (int) $id_or_email;
    } elseif ( $id_or_email instanceof WP_User ) {
        $user_id = $id_or_email->ID;
    } elseif ( $id_or_email instanceof WP_Post ) {
        $user_id = (int) $id_or_email->post_author;
    } elseif ( $id_or_email instanceof WP_Comment ) {
        $user_id = (int) $id_or_email->user_id;
    } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
        $u = get_user_by( 'email', $id_or_email );
        $user_id = $u ? $u->ID : 0;
    }
    if ( ! $user_id ) {
        return $args;
    }
    $privacy = prospergenics_profiles_privacy( $user_id );
    if ( 'public' !== $privacy['profile'] && get_current_user_id() !== $user_id ) {
        return $args;
    }
    $url = prospergenics_profiles_photo_url( $user_id, ( isset( $args['size'] ) && $args['size'] > 150 ) ? 'medium' : 'thumbnail' );
    if ( $url ) {
        $args['url']          = $url;
        $args['found_avatar'] = true;
    }
    return $args;
}, 10, 2 );

/* ───────────────────────── featured (card #6352) ───────────────────────── */

function prospergenics_profiles_featured( $user_id ) {
    return prospergenics_profiles_normalize_featured( get_user_meta( (int) $user_id, '_pg_profiles_featured', true ) );
}

/**
 * A link preview (og:title + og:image), fetched server-side (no CORS) and cached for 24 hours.
 * Links to this site's own posts use the post's title and image directly.
 *
 * @return array{title:string,image:string}
 */
function prospergenics_profiles_link_preview( $url ) {
    $url = prospergenics_profiles_clean_url( $url );
    if ( '' === $url ) {
        return array( 'title' => '', 'image' => '' );
    }
    $post_id = url_to_postid( $url );
    if ( $post_id && 'publish' === get_post_status( $post_id ) && '' === (string) get_post_field( 'post_password', $post_id ) ) {
        return array(
            'title' => prospergenics_profiles_clip( get_the_title( $post_id ), 140 ),
            'image' => (string) get_the_post_thumbnail_url( $post_id, 'medium_large' ),
        );
    }
    $key    = '_pg_profiles_preview_' . md5( $url );
    $cached = get_transient( $key );
    if ( is_array( $cached ) ) {
        return $cached;
    }
    $result   = array( 'title' => '', 'image' => '' );
    // wp_safe_remote_get refuses private/loopback addresses, so this can't probe the server's network
    $response = wp_safe_remote_get( $url, array(
        'timeout'             => 5,
        'redirection'         => 3,
        'limit_response_size' => 512 * KB_IN_BYTES,
        'user-agent'          => 'ProsperGenics link preview',
    ) );
    if ( ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response ) ) {
        $type = (string) wp_remote_retrieve_header( $response, 'content-type' );
        if ( '' === $type || false !== stripos( $type, 'html' ) ) {
            $result = prospergenics_profiles_parse_preview( wp_remote_retrieve_body( $response ), $url );
        }
    }
    set_transient( $key, $result, DAY_IN_SECONDS );
    return $result;
}

/* ───────────────────────── certificates, courses and articles ───────────────────────── */

/**
 * The member's valid course certificates (ARC-20), newest first; revoked ones are left out.
 *
 * @return array<int,array{course:string,course_url:string,date:string,verify_url:string}>
 */
function prospergenics_profiles_certificates( $user_id ) {
    if ( ! function_exists( 'prospergenics_courses_user_certificates' ) ) {
        return array();
    }
    $out = array();
    foreach ( prospergenics_courses_user_certificates( (int) $user_id ) as $cert ) {
        if ( 'publish' !== $cert->post_status || prospergenics_courses_certificate_is_revoked( $cert ) ) {
            continue;
        }
        $c      = prospergenics_courses_certificate_data( $cert );
        $course = get_post( $c['course_id'] );
        $out[]  = array(
            'course'     => $c['course'],
            'course_url' => $course && 'publish' === $course->post_status ? (string) get_permalink( $course ) : '',
            'issued_at'  => $c['issued_at'],
            'date'       => $c['date'],
            'verify_url' => $c['verify_url'],
        );
    }
    usort( $out, function ( $a, $b ) {
        return $b['issued_at'] - $a['issued_at'];
    } );
    return $out;
}

/**
 * The member's published courses or articles (NAV-16), newest first.
 *
 * @return array<int,array{title:string,url:string,date:string}>
 */
function prospergenics_profiles_published( $user_id, $post_type, $limit = 6 ) {
    if ( ! post_type_exists( $post_type ) ) {
        return array();
    }
    $out = array();
    foreach ( get_posts( array( 'post_type' => $post_type, 'post_status' => 'publish', 'author' => (int) $user_id, 'posts_per_page' => $limit, 'orderby' => 'date', 'order' => 'DESC', 'no_found_rows' => true ) ) as $p ) {
        $out[] = array(
            'title' => wp_strip_all_tags( get_the_title( $p ) ),
            'url'   => (string) get_permalink( $p ),
            'date'  => get_the_date( 'j M Y', $p ),
        );
    }
    return $out;
}

/* ───────────────────────── the profile as data ───────────────────────── */

/**
 * Everything a viewer may see of a member, as an array (REST output and template input).
 * Sections the viewer may not see are absent, never just hidden in markup.
 */
function prospergenics_profiles_public_data( $user, $viewer_id ) {
    $user      = $user instanceof WP_User ? $user : get_userdata( (int) $user );
    $vis       = prospergenics_profiles_visibility_for( $user->ID, $viewer_id );
    $is_owner  = (int) $viewer_id === (int) $user->ID;
    $data      = array(
        'slug'     => prospergenics_profiles_slug( $user->ID ),
        'url'      => prospergenics_profiles_url( $user->ID ),
        'visible'  => $vis,
        'is_owner' => $is_owner,
    );
    if ( ! $vis['profile'] ) {
        return $data;
    }
    $data['name']       = $user->display_name;
    $data['first_name'] = $is_owner ? $user->first_name : null;
    $data['last_name']  = $is_owner ? $user->last_name : null;
    $data['headline']   = (string) prospergenics_profiles_meta( $user->ID, 'headline' );
    $data['location']   = (string) prospergenics_profiles_meta( $user->ID, 'location' );
    $data['about']      = (string) prospergenics_profiles_meta( $user->ID, 'about' );
    $data['photo']      = prospergenics_profiles_photo_url( $user->ID, 'medium' );
    $data['followers']  = count( prospergenics_profiles_follower_ids( $user->ID ) );
    $data['following']  = count( prospergenics_profiles_following_ids( $user->ID ) );
    $data['followed_by_me'] = $viewer_id && ! $is_owner ? prospergenics_profiles_follows( $viewer_id, $user->ID ) : false;
    if ( $vis['email'] ) {
        $data['email'] = $user->user_email;
    }
    if ( $vis['phone'] ) {
        $data['phone'] = (string) prospergenics_profiles_meta( $user->ID, 'phone' );
    }
    if ( $vis['experience'] ) {
        $data['experience'] = prospergenics_profiles_entries( $user->ID, 'pg_experience', $viewer_id );
    }
    if ( $vis['education'] ) {
        $data['education'] = prospergenics_profiles_entries( $user->ID, 'pg_education', $viewer_id );
    }
    if ( $vis['skills'] ) {
        $data['skills'] = prospergenics_profiles_entries( $user->ID, 'pg_skill', $viewer_id );
    }
    if ( $vis['featured'] ) {
        $data['featured'] = prospergenics_profiles_featured( $user->ID );
    }
    if ( $vis['certificates'] ) {
        $data['certificates'] = prospergenics_profiles_certificates( $user->ID );
    }
    // what they teach and write (NAV-16): published work, public anyway, shown with the profile
    $data['courses']  = prospergenics_profiles_published( $user->ID, 'pg_course' );
    $data['articles'] = prospergenics_profiles_published( $user->ID, 'pg_article' );
    if ( $data['courses'] && function_exists( 'prospergenics_courses_instructor_url' ) ) {
        $data['instructor_url'] = prospergenics_courses_instructor_url( $user->ID );
    }
    if ( $is_owner ) {
        $data['privacy']    = prospergenics_profiles_privacy( $user->ID );
        $data['completion'] = prospergenics_profiles_get_completion( $user->ID );
    }
    return $data;
}

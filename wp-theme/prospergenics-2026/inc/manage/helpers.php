<?php
/**
 * Staff area (/manage/, card #6457): the capability and the rules, kept free of output so the unit
 * test can check them (tests/test-platform-manage.php).
 */

defined( 'ABSPATH' ) || exit;

/** The capability that opens /manage/ and lets its holder manage members who are not staff. */
const PROSPERGENICS_MANAGE_CAP = 'pg_manage';

/** Messages and articles per page on their screens. */
const PROSPERGENICS_MANAGE_PER_PAGE = 20;

/** Members per page on /manage/members/ by default (the "Per page" select offers more). */
const PROSPERGENICS_MANAGE_MEMBERS_PER_PAGE = 25;

/**
 * Editors and administrators (everyone who can moderate comments and edit others' posts) get
 * pg_manage. Granted at runtime: nothing is written to the roles table.
 */
function prospergenics_manage_grant_caps( $allcaps ) {
    if ( ! empty( $allcaps['moderate_comments'] ) && ! empty( $allcaps['edit_others_posts'] ) ) {
        $allcaps[ PROSPERGENICS_MANAGE_CAP ] = true;
    }
    return $allcaps;
}
add_filter( 'user_has_cap', 'prospergenics_manage_grant_caps' );

/** Whether the current user may open /manage/. */
function prospergenics_manage_can_access() {
    return is_user_logged_in() && current_user_can( PROSPERGENICS_MANAGE_CAP );
}

/**
 * Whether a user is staff for the members screen: they can edit others' posts, manage users or open
 * /manage/ (editors, administrators and any custom role with those rights). Editors never change
 * staff accounts.
 */
function prospergenics_manage_is_staff( $user_id ) {
    foreach ( array( 'edit_others_posts', 'edit_users', 'promote_users', 'manage_options', PROSPERGENICS_MANAGE_CAP ) as $cap ) {
        if ( user_can( (int) $user_id, $cap ) ) {
            return true;
        }
    }
    return false;
}

/**
 * Who may change a member, from the facts (pure).
 *
 * @param array $f self (the target is the actor), exists, admin_rule (the wp-admin rule holds:
 *                 edit_users + edit_user), manage (the actor has pg_manage), target_staff.
 */
function prospergenics_manage_member_decision( array $f ) {
    if ( ! empty( $f['self'] ) || empty( $f['exists'] ) ) {
        return false;
    }
    if ( ! empty( $f['admin_rule'] ) ) {
        return true;
    }
    return ! empty( $f['manage'] ) && empty( $f['target_staff'] );
}

/** Whether the current user may use the /manage/ actions on a member. */
function prospergenics_manage_can_manage_member( $user_id ) {
    $user_id = (int) $user_id;
    $exists  = $user_id > 0 && (bool) get_userdata( $user_id );
    return prospergenics_manage_member_decision( array(
        'self'         => $user_id === (int) get_current_user_id(),
        'exists'       => $exists,
        'admin_rule'   => $exists && prospergenics_profiles_can_manage_member( $user_id ),
        'manage'       => current_user_can( PROSPERGENICS_MANAGE_CAP ),
        'target_staff' => $exists && prospergenics_manage_is_staff( $user_id ),
    ) );
}

/**
 * The roles the actor may give, role => label: subscriber and writer for every manager; contributor,
 * author and editor too for administrators (promote_user). Never administrator.
 */
function prospergenics_manage_role_choices( $can_promote ) {
    $roles = array( 'subscriber' => 'Member (subscriber)', 'pg_writer' => 'Writer' );
    if ( $can_promote ) {
        $roles += array( 'contributor' => 'Contributor', 'author' => 'Author', 'editor' => 'Editor' );
    }
    return $roles;
}

/**
 * Whether a role change is allowed (pure). The caller has already checked that the actor may
 * manage this member at all.
 *
 * @param string[] $target_roles The member's current roles.
 * @param string   $new_role     The role asked for.
 * @param bool     $can_promote  The actor holds promote_user for this member (administrators).
 */
function prospergenics_manage_role_change_allowed( array $target_roles, $new_role, $can_promote ) {
    if ( ! isset( prospergenics_manage_role_choices( $can_promote )[ (string) $new_role ] ) ) {
        return false;
    }
    if ( in_array( 'administrator', $target_roles, true ) ) {
        return false; // administrators are changed in wp-admin → Users only
    }
    if ( ! $can_promote ) {
        // editors: only between subscriber and writer, so they cannot demote an author or contributor
        return ! array_diff( $target_roles, array( 'subscriber', 'pg_writer' ) );
    }
    return true;
}

/** The member actions, key => button label. */
function prospergenics_manage_member_actions() {
    return array(
        'role'      => 'Change role',
        'verify'    => 'Mark verified',
        'resend'    => 'Resend confirmation link',
        'hide'      => 'Hide profile',
        'unhide'    => 'Show profile',
        'suspend'   => 'Suspend',
        'unsuspend' => 'Unsuspend',
    );
}

/** The nonce action of a member action. */
function prospergenics_manage_member_nonce( $do, $user_id ) {
    return 'pg_manage_member_' . $do . '_' . (int) $user_id;
}

/** The filters of the members list, key => label. */
function prospergenics_manage_member_filters() {
    return array(
        'all'        => 'All members',
        'admin'      => 'Administrators',
        'editor'     => 'Editors',
        'author'     => 'Authors',
        'writer'     => 'Writers',
        'member'     => 'Members (subscribers)',
        'verified'   => 'Verified',
        'unverified' => 'Not verified',
        'instructor' => 'Instructors',
        'suspended'  => 'Suspended',
        'hidden'     => 'Hidden profiles',
    );
}

/** The filters that are a role, filter key => role. */
function prospergenics_manage_member_role_filters() {
    return array( 'admin' => 'administrator', 'editor' => 'editor', 'author' => 'author', 'writer' => 'pg_writer', 'member' => 'subscriber' );
}

/**
 * The summary chips above the members list, filter key => short label. Each one is a link that
 * sets that filter; prospergenics_manage_member_chip_counts() counts them.
 */
function prospergenics_manage_member_chips() {
    return array(
        'all'        => 'All',
        'admin'      => 'Admins',
        'author'     => 'Authors',
        'member'     => 'Members',
        'unverified' => 'Not verified',
        'suspended'  => 'Suspended',
        'hidden'     => 'Hidden',
    );
}

/**
 * How many members each chip finds (cheap: one count_users() for the roles, one count-only
 * WP_User_Query per flag; no user records are loaded).
 *
 * @return array<string,int> chip key => count
 */
function prospergenics_manage_member_chip_counts() {
    $users  = count_users();
    $roles  = isset( $users['avail_roles'] ) ? (array) $users['avail_roles'] : array();
    $by     = prospergenics_manage_member_role_filters();
    $counts = array();
    foreach ( array_keys( prospergenics_manage_member_chips() ) as $key ) {
        if ( 'all' === $key ) {
            $counts[ $key ] = (int) $users['total_users'];
        } elseif ( isset( $by[ $key ] ) ) {
            $counts[ $key ] = isset( $roles[ $by[ $key ] ] ) ? (int) $roles[ $by[ $key ] ] : 0;
        } else {
            $counts[ $key ] = (int) ( new WP_User_Query( prospergenics_manage_member_count_args( $key ) ) )->get_total();
        }
    }
    return $counts;
}

/** WP_User_Query arguments that only count the members of a filter (pure): one ID, the total. */
function prospergenics_manage_member_count_args( $filter ) {
    return array( 'fields' => 'ID' ) + prospergenics_manage_member_query_args( '', $filter, 1, 1 );
}

/** The choices of the members list's "Per page" select. */
function prospergenics_manage_member_per_page_choices() {
    return array( 25, 50, 100 );
}

/** A "Per page" value as asked, or the default when it is not one of the choices (pure). */
function prospergenics_manage_clean_per_page( $per_page ) {
    $per_page = (int) $per_page;
    return in_array( $per_page, prospergenics_manage_member_per_page_choices(), true ) ? $per_page : PROSPERGENICS_MANAGE_MEMBERS_PER_PAGE;
}

/** The orders of the members list, key => [label, orderby, order]. The first is the default. */
function prospergenics_manage_member_sorts() {
    return array(
        'newest'    => array( 'Newest first', 'registered', 'DESC' ),
        'oldest'    => array( 'Oldest first', 'registered', 'ASC' ),
        'name'      => array( 'Name A–Z', 'display_name', 'ASC' ),
        'name_desc' => array( 'Name Z–A', 'display_name', 'DESC' ),
    );
}

/**
 * The query string of a members list (pure): only what differs from the defaults, so a plain list
 * is /manage/members/ and every link (chips, paging, the form actions' way back) is shareable.
 *
 * @param array $state s, filter, tier, sort, per, pg (any may be missing).
 */
function prospergenics_manage_member_list_args( array $state ) {
    $state += array( 's' => '', 'filter' => 'all', 'tier' => '', 'sort' => 'newest', 'per' => PROSPERGENICS_MANAGE_MEMBERS_PER_PAGE, 'pg' => 1 );
    $args   = array(
        's'      => (string) $state['s'],
        'filter' => 'all' === $state['filter'] ? '' : (string) $state['filter'],
        'tier'   => (string) $state['tier'],
        'sort'   => 'newest' === $state['sort'] ? '' : (string) $state['sort'],
        'per'    => PROSPERGENICS_MANAGE_MEMBERS_PER_PAGE === (int) $state['per'] ? '' : (string) (int) $state['per'],
        'pg'     => (int) $state['pg'] > 1 ? (string) (int) $state['pg'] : '',
    );
    return array_filter( $args, 'strlen' );
}

/**
 * The members shown on a page, for "Showing 26–50 of 312" (pure).
 *
 * @return int[] [first, last], [0, 0] for an empty list
 */
function prospergenics_manage_page_range( $paged, $per_page, $total ) {
    $total = max( 0, (int) $total );
    if ( ! $total ) {
        return array( 0, 0 );
    }
    $first = ( max( 1, (int) $paged ) - 1 ) * max( 1, (int) $per_page ) + 1;
    return array( min( $first, $total ), min( $first + max( 1, (int) $per_page ) - 1, $total ) );
}

/**
 * WP_User_Query arguments for the members list (pure). Newest members first unless $sort says
 * otherwise.
 *
 * @param string $search   Name, username or email (already sanitised), '' for none.
 * @param string $filter   A key of prospergenics_manage_member_filters().
 * @param int    $paged    1-based page.
 * @param int    $per_page Members per page.
 * @param string $sort     A key of prospergenics_manage_member_sorts(); unknown keys are the default.
 */
function prospergenics_manage_member_query_args( $search, $filter, $paged, $per_page = PROSPERGENICS_MANAGE_MEMBERS_PER_PAGE, $sort = 'newest' ) {
    $sorts = prospergenics_manage_member_sorts();
    $order = isset( $sorts[ $sort ] ) ? $sorts[ $sort ] : reset( $sorts );
    $args  = array(
        'number'      => (int) $per_page,
        'paged'       => max( 1, (int) $paged ),
        'orderby'     => $order[1],
        'order'       => $order[2],
        'count_total' => true,
    );
    $search = trim( (string) $search );
    if ( '' !== $search ) {
        $args['search']         = '*' . $search . '*';
        $args['search_columns'] = array( 'user_login', 'user_email', 'display_name' );
    }
    $exists = function ( $key, $compare = 'EXISTS' ) {
        return array( array( 'key' => $key, 'compare' => $compare ) );
    };
    $roles = prospergenics_manage_member_role_filters();
    if ( isset( $roles[ $filter ] ) ) {
        $args['role__in'] = array( $roles[ $filter ] );
        return $args;
    }
    switch ( $filter ) {
        case 'verified':
            $args['meta_query'] = $exists( '_pg_profiles_verify_pending', 'NOT EXISTS' );
            break;
        case 'unverified':
            $args['meta_query'] = $exists( '_pg_profiles_verify_pending' );
            break;
        case 'instructor':
            $args['meta_query'] = $exists( '_pg_courses_instructor' );
            break;
        case 'suspended':
            $args['meta_query'] = $exists( '_pg_profiles_suspended' );
            break;
        case 'hidden':
            $args['meta_query'] = $exists( '_pg_profiles_hidden' );
            break;
    }
    return $args;
}

/**
 * The badge filter of the members list (pure): '' for any, a tier key, or 'none' for members
 * without a badge (unverified, hidden, suspended). Unknown values are ''.
 *
 * @param string   $tier  As asked (already sanitised with sanitize_key()).
 * @param string[] $tiers The tier keys (prospergenics_profiles_badge_tiers()).
 */
function prospergenics_manage_clean_tier( $tier, array $tiers ) {
    $tier = (string) $tier;
    return 'none' === $tier || in_array( $tier, $tiers, true ) ? $tier : '';
}

/**
 * The members list arguments with the badge filter added (pure): the stored tier
 * (_pg_profiles_rank_tier, inc/profiles/rank.php) must match, together with any other filter.
 */
function prospergenics_manage_member_tier_args( array $args, $tier ) {
    if ( '' === (string) $tier ) {
        return $args;
    }
    $clause = array( 'key' => '_pg_profiles_rank_tier', 'value' => (string) $tier );
    $args['meta_query'] = empty( $args['meta_query'] ) ? array( $clause ) : array( 'relation' => 'AND', $args['meta_query'], $clause );
    return $args;
}

/* ───────────────────────── contact messages ───────────────────────── */

/** The contact inbox views, key => label. */
function prospergenics_manage_message_views() {
    return array(
        'open'    => 'Open',
        'unread'  => 'Unread',
        'handled' => 'Handled',
        'spam'    => 'Spam',
    );
}

/** The contact message actions, key => button label. */
function prospergenics_manage_message_actions() {
    return array(
        'read'    => 'Mark read',
        'unread'  => 'Mark unread',
        'handled' => 'Mark handled',
        'reopen'  => 'Reopen',
    );
}

/** The nonce action of a contact message action (also the "Open" link, which marks it read). */
function prospergenics_manage_message_nonce( $do, $post_id ) {
    return 'pg_manage_message_' . $do . '_' . (int) $post_id;
}

/**
 * State of a message: unread|read|handled (pure). A handled message counts as read.
 *
 * @param int $read_at    _pg_contact_read_at (0 when never read).
 * @param int $handled_at _pg_contact_handled_at (0 when open).
 */
function prospergenics_manage_message_state( $read_at, $handled_at ) {
    if ( (int) $handled_at > 0 ) {
        return 'handled';
    }
    return (int) $read_at > 0 ? 'read' : 'unread';
}

/**
 * WP_Query arguments for a contact inbox view (pure). Spam is stored as a draft (inc/contact-
 * messages-store.php); the other views are the published messages.
 */
function prospergenics_manage_message_query_args( $view, $paged, $per_page = PROSPERGENICS_MANAGE_PER_PAGE ) {
    $args = array(
        'post_type'      => 'pg_contact_msg',
        'post_status'    => 'spam' === $view ? 'draft' : 'publish',
        'posts_per_page' => (int) $per_page,
        'paged'          => max( 1, (int) $paged ),
        'orderby'        => 'date',
        'order'          => 'DESC',
    );
    if ( 'unread' === $view ) {
        $args['meta_query'] = array(
            array( 'key' => '_pg_contact_read_at', 'compare' => 'NOT EXISTS' ),
            array( 'key' => '_pg_contact_handled_at', 'compare' => 'NOT EXISTS' ),
        );
    } elseif ( 'open' === $view ) {
        $args['meta_query'] = array( array( 'key' => '_pg_contact_handled_at', 'compare' => 'NOT EXISTS' ) );
    } elseif ( 'handled' === $view ) {
        $args['meta_query'] = array( array( 'key' => '_pg_contact_handled_at', 'compare' => 'EXISTS' ) );
    }
    return $args;
}

/** "Re: <subject>" mailto: link to reply to a message. */
function prospergenics_manage_reply_url( $email, $subject ) {
    return 'mailto:' . str_replace( array( '?', '&', '#' ), '', (string) $email ) . '?subject=' . rawurlencode( 'Re: ' . (string) $subject );
}

/** Unix time $days days before $now (the overview's windows). */
function prospergenics_manage_since( $days, $now ) {
    return (int) $now - (int) $days * 86400;
}

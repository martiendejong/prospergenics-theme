<?php
/**
 * Profiles track: reporting content and the moderation queue (card #6472).
 *
 * Report: verified members (logged in, email confirmed) report an article, a member profile (/in/)
 * or a lesson discussion post, once per item, with a reason and an optional note. A report is a
 * pg_report post (internal: no UI, no REST): post_author = the reporter, meta
 * _pg_profiles_report_{target,type,object,owner,reason,details,status,action,by,at}.
 *
 * Queue: wp-admin → Reports, one row per reported item, for the capability pg_moderate. That is a
 * capability of its own, mapped (user_has_cap, nothing written to the roles table) to everyone who
 * can moderate comments and edit others' posts, i.e. editors and administrators. Not plain
 * moderate_comments: the queue does more than comment moderation (it hides articles and profiles,
 * warns and suspends members), and a separate capability lets a future "moderator" role get the
 * queue without editor rights. Actions, each a nonce-checked POST, and they close every open
 * report on the item:
 *
 *   Hide      article: _pg_articles_hidden (inc/articles/hidden.php); profile: the staff "hidden"
 *             flag _pg_profiles_hidden (users-admin.php); discussion post: unapproved (held).
 *             Reversible from the "Handled" tab ("Show again").
 *   Remove    article: to the trash; discussion post: to the trash. (Profiles: hide or suspend.)
 *   Dismiss   nothing was wrong.
 *   Warn      an email to the member, with the team's note; kept on the member (_pg_profiles_warnings).
 *   Suspend   the member cannot log in (authenticate filter, with a clear message), their sessions
 *             end, any cookie or application password stops working (determine_current_user) and
 *             their profile is hidden. Reversible: "Unsuspend" on the "Suspended" tab restores
 *             login, and the profile when the suspension hid it. Staff cannot be suspended.
 *
 * Privacy: the exporter lists the reports a member made, warnings and a suspension; the eraser
 * deletes their reports and warnings and keeps a suspension (so erasing data cannot lift it).
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_MODERATION_CAP = 'pg_moderate';
/** Reports a member may send per hour. */
const PROSPERGENICS_REPORT_LIMIT  = 10;
const PROSPERGENICS_REPORT_WINDOW = HOUR_IN_SECONDS;

add_filter( 'user_has_cap', function ( $allcaps ) {
    if ( ! empty( $allcaps['moderate_comments'] ) && ! empty( $allcaps['edit_others_posts'] ) ) {
        $allcaps[ PROSPERGENICS_MODERATION_CAP ] = true;
    }
    return $allcaps;
} );

/* ───────────────────────── pure helpers (unit-tested) ───────────────────────── */

/** The reasons a member can choose, key => label. */
function prospergenics_profiles_report_reasons() {
    return array(
        'spam'          => 'Spam or advertising',
        'harassment'    => 'Harassment or bullying',
        'hate'          => 'Hateful or discriminatory',
        'sexual'        => 'Sexual or violent content',
        'misleading'    => 'False or misleading',
        'impersonation' => 'Pretends to be someone else',
        'other'         => 'Something else',
    );
}

/** The kinds of content that can be reported, type => label. */
function prospergenics_profiles_report_types() {
    return array( 'article' => 'Article', 'profile' => 'Profile', 'question' => 'Discussion post' );
}

/** "article:12", or '' for an unknown type or id. */
function prospergenics_profiles_report_key( $type, $id ) {
    return isset( prospergenics_profiles_report_types()[ $type ] ) && (int) $id > 0 ? $type . ':' . (int) $id : '';
}

/** [type, id] from a key, or null. */
function prospergenics_profiles_report_parse_key( $key ) {
    if ( ! preg_match( '/^([a-z]+):([1-9][0-9]{0,18})$/', (string) $key, $m ) || ! isset( prospergenics_profiles_report_types()[ $m[1] ] ) ) {
        return null;
    }
    return array( $m[1], (int) $m[2] );
}

/** The actions the queue offers for a type of content. */
function prospergenics_profiles_moderation_actions( $type ) {
    $all = array( 'hide' => 'Hide', 'remove' => 'Remove', 'dismiss' => 'Dismiss', 'warn' => 'Warn', 'suspend' => 'Suspend member' );
    if ( 'profile' === $type ) {
        unset( $all['remove'] );
    }
    return $all;
}

/**
 * Why a member may not report an item ('' when they may). Pure: the caller looks the facts up.
 *
 * @param array $f logged_in, verified, suspended, exists, visible, own, already, rate_ok
 */
function prospergenics_profiles_report_block( array $f ) {
    if ( empty( $f['logged_in'] ) ) {
        return 'login';
    }
    if ( empty( $f['verified'] ) || ! empty( $f['suspended'] ) ) {
        return 'unverified';
    }
    if ( empty( $f['exists'] ) || empty( $f['visible'] ) ) {
        return 'not_found';
    }
    if ( ! empty( $f['own'] ) ) {
        return 'own';
    }
    if ( ! empty( $f['already'] ) ) {
        return 'already';
    }
    if ( isset( $f['rate_ok'] ) && ! $f['rate_ok'] ) {
        return 'rate';
    }
    return '';
}

/** The report status after a queue action: 'dismissed' for Dismiss, else 'actioned'. */
function prospergenics_profiles_report_status_after( $do ) {
    return 'dismiss' === $do ? 'dismissed' : 'actioned';
}

/* ───────────────────────── suspension ───────────────────────── */

function prospergenics_profiles_is_suspended( $user_id ) {
    return (bool) get_user_meta( (int) $user_id, '_pg_profiles_suspended', true );
}

/** Staff (moderators, user managers) cannot be suspended from the queue. */
function prospergenics_profiles_can_be_suspended( $user_id, $by ) {
    $user_id = (int) $user_id;
    return $user_id && $user_id !== (int) $by && get_userdata( $user_id )
        && ! user_can( $user_id, PROSPERGENICS_MODERATION_CAP ) && ! user_can( $user_id, 'edit_users' );
}

/**
 * Suspend a member: no login, sessions ended, profile hidden.
 *
 * @return true|WP_Error
 */
function prospergenics_profiles_suspend( $user_id, $by, $reason = '' ) {
    if ( ! prospergenics_profiles_can_be_suspended( $user_id, $by ) ) {
        return new WP_Error( 'pg_profiles_cannot_suspend', 'This member cannot be suspended.', array( 'status' => 403 ) );
    }
    if ( prospergenics_profiles_is_suspended( $user_id ) ) {
        return true;
    }
    update_user_meta( $user_id, '_pg_profiles_suspended', array( 'time' => time(), 'by' => (int) $by, 'reason' => sanitize_textarea_field( (string) $reason ) ) );
    if ( ! prospergenics_profiles_is_hidden( $user_id ) ) {
        prospergenics_profiles_set_hidden( $user_id, true );
        update_user_meta( $user_id, '_pg_profiles_suspended_hid', 1 ); // so unsuspending shows it again
    }
    if ( class_exists( 'WP_Session_Tokens' ) ) {
        WP_Session_Tokens::get_instance( (int) $user_id )->destroy_all();
    }
    do_action( 'prospergenics_profiles_suspended', (int) $user_id, (int) $by );
    return true;
}

/** Lift a suspension; the profile is shown again when the suspension hid it. */
function prospergenics_profiles_unsuspend( $user_id ) {
    $user_id = (int) $user_id;
    if ( get_user_meta( $user_id, '_pg_profiles_suspended_hid', true ) ) {
        prospergenics_profiles_set_hidden( $user_id, false );
    }
    delete_user_meta( $user_id, '_pg_profiles_suspended_hid' );
    delete_user_meta( $user_id, '_pg_profiles_suspended' );
}

/** The message a suspended member gets at login. */
function prospergenics_profiles_suspended_message() {
    return '<strong>Your account is suspended.</strong> The ProsperGenics team suspended this account after reports about it, so you cannot log in. If you think this is a mistake, please write to us via ' . esc_url( home_url( '/contact/' ) ) . '.';
}

// Password login (wp-login.php and every wp_signon()).
add_filter( 'authenticate', function ( $user ) {
    if ( $user instanceof WP_User && prospergenics_profiles_is_suspended( $user->ID ) ) {
        return new WP_Error( 'pg_profiles_suspended', prospergenics_profiles_suspended_message() );
    }
    return $user;
}, 99 );

// Any other way in (a cookie issued before, an application password): treated as logged out.
add_filter( 'determine_current_user', function ( $user_id ) {
    return $user_id && prospergenics_profiles_is_suspended( $user_id ) ? 0 : $user_id;
}, 99 );

/* ───────────────────────── report targets ───────────────────────── */

/**
 * What a report key points at, or null when it no longer exists.
 *
 * @return array{type:string,id:int,key:string,title:string,url:string,owner:int,admin_url:string,hidden:bool,removed:bool}|null
 */
function prospergenics_profiles_report_target( $type, $id ) {
    $id = (int) $id;
    if ( 'article' === $type ) {
        $p = get_post( $id );
        if ( ! $p || 'pg_article' !== $p->post_type ) {
            return null;
        }
        return array(
            'type' => $type, 'id' => $id, 'key' => 'article:' . $id,
            'title'     => wp_strip_all_tags( get_the_title( $p ) ),
            'url'       => (string) get_permalink( $p ),
            'owner'     => (int) $p->post_author,
            'admin_url' => (string) get_edit_post_link( $p->ID, 'raw' ),
            'hidden'    => function_exists( 'prospergenics_articles_is_hidden' ) && prospergenics_articles_is_hidden( $id ),
            'removed'   => 'publish' !== $p->post_status,
        );
    }
    if ( 'profile' === $type ) {
        $u = get_userdata( $id );
        if ( ! $u ) {
            return null;
        }
        return array(
            'type' => $type, 'id' => $id, 'key' => 'profile:' . $id,
            'title'     => $u->display_name,
            'url'       => prospergenics_profiles_url( $id ),
            'owner'     => $id,
            'admin_url' => get_edit_user_link( $id ),
            'hidden'    => prospergenics_profiles_is_hidden( $id ),
            'removed'   => false,
        );
    }
    if ( 'question' === $type && function_exists( 'prospergenics_courses_discussion_get' ) ) {
        $c = prospergenics_courses_discussion_get( $id );
        if ( ! $c ) {
            return null;
        }
        $lesson = get_post( (int) $c->comment_post_ID );
        $author = get_userdata( (int) $c->user_id );
        return array(
            'type' => $type, 'id' => $id, 'key' => 'question:' . $id,
            'title'     => sprintf( '%s by %s in “%s”', (int) $c->comment_parent ? 'Reply' : 'Question', $author ? $author->display_name : 'a former member', $lesson ? wp_strip_all_tags( get_the_title( $lesson ) ) : 'a deleted lesson' ),
            'url'       => $lesson ? prospergenics_courses_discussion_link( $c ) : '',
            'owner'     => (int) $c->user_id,
            'admin_url' => admin_url( 'comment.php?action=editcomment&c=' . $id ),
            'hidden'    => '0' === (string) $c->comment_approved,
            'removed'   => in_array( (string) $c->comment_approved, array( 'trash', 'spam' ), true ),
            'excerpt'   => function_exists( 'prospergenics_courses_discussion_excerpt' ) ? prospergenics_courses_discussion_excerpt( $c->comment_content, 240 ) : '',
        );
    }
    return null;
}

/** Whether the user can see the item right now (you can only report what you can see). */
function prospergenics_profiles_report_target_visible( array $t, $user_id ) {
    if ( $t['removed'] ) {
        return false;
    }
    switch ( $t['type'] ) {
        case 'article':
            return ! $t['hidden'] && 'publish' === get_post_status( $t['id'] );
        case 'profile':
            return ! empty( prospergenics_profiles_visibility_for( $t['id'], $user_id )['profile'] );
        case 'question':
            $c = get_comment( $t['id'] );
            return ! $t['hidden'] && $c && prospergenics_courses_discussion_can_access( (int) $c->comment_post_ID, $user_id );
    }
    return false;
}

/** The member's report on an item, or null. */
function prospergenics_profiles_report_find( $user_id, $key ) {
    $ids = get_posts( array(
        'post_type'        => 'pg_report',
        'post_status'      => 'any',
        'author'           => (int) $user_id,
        'meta_key'         => '_pg_profiles_report_target',
        'meta_value'       => (string) $key,
        'posts_per_page'   => 1,
        'fields'           => 'ids',
        'suppress_filters' => true,
    ) );
    return $ids ? (int) $ids[0] : null;
}

/* ───────────────────────── reporting ───────────────────────── */

add_action( 'init', function () {
    register_post_type( 'pg_report', array(
        'label'               => 'Reports',
        'public'              => false,
        'show_ui'             => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'rewrite'             => false,
        'query_var'           => false,
        'show_in_rest'        => false,
        'supports'            => array( 'title', 'author' ),
        'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
        'map_meta_cap'        => true,
    ) );
} );

/** Why the current user may not report an item ('' when they may). */
function prospergenics_profiles_report_block_for( $type, $id, $user_id, $check_rate = false ) {
    $t = prospergenics_profiles_report_target( $type, $id );
    $f = array(
        'logged_in' => (bool) $user_id,
        'verified'  => $user_id && prospergenics_profiles_is_verified( $user_id ),
        'suspended' => $user_id && prospergenics_profiles_is_suspended( $user_id ),
        'exists'    => (bool) $t,
        'visible'   => $t && $user_id && prospergenics_profiles_report_target_visible( $t, $user_id ),
        'own'       => $t && (int) $t['owner'] === (int) $user_id,
        'already'   => $t && $user_id && prospergenics_profiles_report_find( $user_id, $t['key'] ),
    );
    if ( $check_rate ) {
        $key          = 'pg_profiles_report_rate_' . (int) $user_id;
        $rate         = prospergenics_profiles_rate_hit( get_transient( $key ), time(), PROSPERGENICS_REPORT_LIMIT, PROSPERGENICS_REPORT_WINDOW );
        $f['rate_ok'] = $rate['allowed'];
        set_transient( $key, $rate['state'], PROSPERGENICS_REPORT_WINDOW );
    }
    return prospergenics_profiles_report_block( $f );
}

/**
 * File a report.
 *
 * @return int|WP_Error report id
 */
function prospergenics_profiles_report( $user_id, $type, $id, $reason, $details = '' ) {
    $block = prospergenics_profiles_report_block_for( $type, $id, $user_id, true );
    if ( '' !== $block ) {
        return new WP_Error( 'pg_profiles_report_' . $block, 'This cannot be reported.', array( 'status' => 403 ) );
    }
    if ( ! isset( prospergenics_profiles_report_reasons()[ $reason ] ) ) {
        return new WP_Error( 'pg_profiles_report_reason', 'Choose a reason.', array( 'status' => 400 ) );
    }
    $t  = prospergenics_profiles_report_target( $type, $id );
    $rid = wp_insert_post( array(
        'post_type'   => 'pg_report',
        'post_status' => 'publish',
        'post_author' => (int) $user_id,
        'post_title'  => 'Report on ' . $t['key'],
    ), true );
    if ( is_wp_error( $rid ) ) {
        return $rid;
    }
    $meta = array(
        'target'  => $t['key'],
        'type'    => $t['type'],
        'object'  => (int) $t['id'],
        'owner'   => (int) $t['owner'],
        'reason'  => $reason,
        'details' => mb_substr( sanitize_textarea_field( (string) $details ), 0, 1000 ),
        'status'  => 'open',
    );
    foreach ( $meta as $k => $v ) {
        update_post_meta( $rid, '_pg_profiles_report_' . $k, $v );
    }
    do_action( 'prospergenics_profiles_reported', (int) $rid, $t['key'] );
    return (int) $rid;
}

/** Messages for the reporter, keyed by the ?pg_report= value. */
function prospergenics_profiles_report_notices() {
    return array(
        'reason'    => 'Please choose a reason.',
        'rate'      => 'You sent many reports in a short time. Please try again later.',
        'error'     => 'The report could not be sent. Please try again.',
    );
}

/**
 * The "Report" control for an item: a disclosure with the reasons, or "You reported this".
 * '' for visitors, unverified members, the item's owner and anyone who cannot see it.
 */
function prospergenics_profiles_report_html( $type, $id ) {
    $user_id = get_current_user_id();
    $key     = prospergenics_profiles_report_key( $type, $id );
    if ( ! $user_id || '' === $key ) {
        return '';
    }
    $block = prospergenics_profiles_report_block_for( $type, $id, $user_id );
    $dom   = 'pg-report-' . $type . '-' . (int) $id;
    if ( 'already' === $block ) {
        return '<p class="pg-report-done" id="' . esc_attr( $dom ) . '" role="status">You reported this. Thank you: the team will review it.</p>';
    }
    if ( '' !== $block ) {
        return '';
    }
    // phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted values
    $err_key = isset( $_GET['pg_report'], $_GET['pg_report_target'] ) && $key === sanitize_text_field( wp_unslash( $_GET['pg_report_target'] ) ) ? sanitize_key( wp_unslash( $_GET['pg_report'] ) ) : '';
    $notices = prospergenics_profiles_report_notices();
    $labels  = array( 'article' => 'this article', 'profile' => 'this profile', 'question' => 'this post' );
    $html    = '<details class="pg-report" id="' . esc_attr( $dom ) . '"' . ( isset( $notices[ $err_key ] ) ? ' open' : '' ) . '>';
    $html   .= '<summary><svg aria-hidden="true" focusable="false" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 22V4"/><path d="M4 4h12l-2 4 2 4H4"/></svg>Report<span class="screen-reader-text"> ' . esc_html( $labels[ $type ] ) . '</span></summary>';
    $html   .= '<form class="pg-report-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    if ( isset( $notices[ $err_key ] ) ) {
        $html .= '<p class="pg-report-error" role="alert">' . esc_html( $notices[ $err_key ] ) . '</p>';
    }
    $html .= '<input type="hidden" name="action" value="pg_profiles_report" />';
    $html .= '<input type="hidden" name="target" value="' . esc_attr( $key ) . '" />';
    $html .= wp_nonce_field( 'pg_profiles_report_' . $key, '_wpnonce', false, false );
    $html .= '<fieldset><legend>Why are you reporting ' . esc_html( $labels[ $type ] ) . '?</legend>';
    foreach ( prospergenics_profiles_report_reasons() as $r => $label ) {
        $html .= '<label><input type="radio" name="reason" value="' . esc_attr( $r ) . '" required /> ' . esc_html( $label ) . '</label>';
    }
    $html .= '</fieldset>';
    $html .= '<div class="pg-report-details"><label for="' . esc_attr( $dom ) . '-details">Anything the team should know? (optional)</label>';
    $html .= '<textarea id="' . esc_attr( $dom ) . '-details" name="details" rows="3" maxlength="1000"></textarea></div>';
    $html .= '<button type="submit">Send report</button>';
    $html .= '<p class="pg-report-note">Only the ProsperGenics team sees reports. The member is not told who reported.</p>';
    return $html . '</form></details>';
}

add_action( 'admin_post_nopriv_pg_profiles_report', function () {
    wp_safe_redirect( wp_login_url( wp_get_referer() ? wp_get_referer() : home_url( '/' ) ) );
    exit;
} );

add_action( 'admin_post_pg_profiles_report', function () {
    $key = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';
    $tk  = prospergenics_profiles_report_parse_key( $key );
    if ( ! $tk ) {
        wp_die( 'Unknown item.', 400 );
    }
    check_admin_referer( 'pg_profiles_report_' . $key );
    $reason  = isset( $_POST['reason'] ) ? sanitize_key( wp_unslash( $_POST['reason'] ) ) : '';
    $details = isset( $_POST['details'] ) ? wp_unslash( (string) $_POST['details'] ) : '';
    $result  = prospergenics_profiles_report( get_current_user_id(), $tk[0], $tk[1], $reason, $details );
    $t       = prospergenics_profiles_report_target( $tk[0], $tk[1] );
    $back    = wp_get_referer();
    $back    = $back ? $back : ( $t && '' !== $t['url'] ? $t['url'] : home_url( '/' ) );
    $back    = remove_query_arg( array( 'pg_report', 'pg_report_target' ), preg_replace( '/#.*$/', '', $back ) );
    $anchor  = '#pg-report-' . $tk[0] . '-' . $tk[1];
    if ( is_wp_error( $result ) ) {
        $code = str_replace( 'pg_profiles_report_', '', $result->get_error_code() );
        if ( 'already' === $code ) {
            wp_safe_redirect( $back . $anchor );
            exit;
        }
        $code = in_array( $code, array( 'reason', 'rate' ), true ) ? $code : 'error';
        wp_safe_redirect( add_query_arg( array( 'pg_report' => $code, 'pg_report_target' => $key ), $back ) . $anchor );
        exit;
    }
    wp_safe_redirect( $back . $anchor );
    exit;
} );

/* ───────────────────────── the queue ───────────────────────── */

/**
 * Reports grouped by item, newest activity first.
 *
 * @param string $status 'open' or 'handled'
 * @return array<string,array{key:string,reports:WP_Post[],latest:string}>
 */
function prospergenics_profiles_report_groups( $status ) {
    $posts  = get_posts( array(
        'post_type'        => 'pg_report',
        'post_status'      => 'publish',
        'posts_per_page'   => 'open' === $status ? 500 : 200,
        'orderby'          => 'date',
        'order'            => 'DESC',
        'meta_query'       => array( array(
            'key'     => '_pg_profiles_report_status',
            'value'   => 'open' === $status ? array( 'open' ) : array( 'actioned', 'dismissed' ),
            'compare' => 'IN',
        ) ),
        'suppress_filters' => true,
    ) );
    $groups = array();
    foreach ( $posts as $p ) {
        $key = (string) get_post_meta( $p->ID, '_pg_profiles_report_target', true );
        if ( ! isset( $groups[ $key ] ) ) {
            $groups[ $key ] = array( 'key' => $key, 'reports' => array(), 'latest' => $p->post_date_gmt );
        }
        $groups[ $key ]['reports'][] = $p;
    }
    return $groups;
}

/** Open reports (one COUNT query: the menu bubble and /manage/ show it on every page load). */
function prospergenics_profiles_open_report_count() {
    global $wpdb;
    $n = wp_cache_get( 'open', 'pg_profiles_reports' );
    if ( false === $n ) {
        $n = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_pg_profiles_report_status'
            WHERE p.post_type = 'pg_report' AND p.post_status = 'publish' AND m.meta_value = 'open'"
        );
        wp_cache_set( 'open', $n, 'pg_profiles_reports', 60 );
    }
    return (int) $n;
}

/** Close the open reports on an item. */
function prospergenics_profiles_close_reports( $key, $do, $by ) {
    $ids = get_posts( array(
        'post_type' => 'pg_report', 'post_status' => 'publish', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true,
        'meta_query' => array(
            array( 'key' => '_pg_profiles_report_target', 'value' => (string) $key ),
            array( 'key' => '_pg_profiles_report_status', 'value' => 'open' ),
        ),
    ) );
    foreach ( $ids as $id ) {
        update_post_meta( $id, '_pg_profiles_report_status', prospergenics_profiles_report_status_after( $do ) );
        update_post_meta( $id, '_pg_profiles_report_action', $do );
        update_post_meta( $id, '_pg_profiles_report_by', (int) $by );
        update_post_meta( $id, '_pg_profiles_report_at', time() );
    }
    wp_cache_delete( 'open', 'pg_profiles_reports' );
    return count( $ids );
}

/** Email a member a warning about an item, and keep it on the member. */
function prospergenics_profiles_warn( array $t, $by, $note ) {
    $user = get_userdata( (int) $t['owner'] );
    if ( ! $user ) {
        return new WP_Error( 'pg_profiles_no_owner', 'The item has no member to warn.' );
    }
    $what = array( 'article' => 'your article “%s”', 'profile' => 'your profile', 'question' => 'your post in a lesson discussion' );
    $item = sprintf( $what[ $t['type'] ], $t['title'] );
    $note = sanitize_textarea_field( (string) $note );
    $body = sprintf(
        "Hi %s,\n\nMembers reported %s (%s) and the ProsperGenics team reviewed it.\n\n%s\n\nPlease keep to the community guidelines: be respectful, no spam, no misleading content. If this happens again we may hide your content or suspend your account.\n\nIf you think this is a mistake, reply via %s.\n\nThe ProsperGenics team\n",
        $user->display_name,
        $item,
        $t['url'],
        '' !== $note ? "A note from the team:\n" . $note : 'Please review it and change or remove what goes against the guidelines.',
        home_url( '/contact/' )
    );
    $warnings   = get_user_meta( $user->ID, '_pg_profiles_warnings', true );
    $warnings   = is_array( $warnings ) ? $warnings : array();
    $warnings[] = array( 'time' => time(), 'by' => (int) $by, 'target' => $t['key'], 'note' => $note );
    update_user_meta( $user->ID, '_pg_profiles_warnings', array_slice( $warnings, -50 ) );
    return prospergenics_profiles_send_mail( $user->user_email, 'A message from the ProsperGenics team', $body, 'moderation_warning', $user->ID );
}

/** Tell a member their account was suspended. */
add_action( 'prospergenics_profiles_suspended', function ( $user_id ) {
    $user = get_userdata( $user_id );
    $s    = get_user_meta( $user_id, '_pg_profiles_suspended', true );
    if ( ! $user || ! is_array( $s ) ) {
        return;
    }
    $body = sprintf(
        "Hi %s,\n\nThe ProsperGenics team has suspended your account after reviewing reports about it. While it is suspended you cannot log in and your profile is hidden.\n%s\nIf you think this is a mistake, please write to us via %s.\n\nThe ProsperGenics team\n",
        $user->display_name,
        '' !== $s['reason'] ? "\nThe reason:\n" . $s['reason'] . "\n" : '',
        home_url( '/contact/' )
    );
    prospergenics_profiles_send_mail( $user->user_email, 'Your ProsperGenics account is suspended', $body, 'moderation_suspended', $user->ID );
} );

/**
 * Apply a queue action to an item.
 *
 * @return string|WP_Error the result notice key
 */
function prospergenics_profiles_moderate( $key, $do, $by, $note = '' ) {
    $tk = prospergenics_profiles_report_parse_key( $key );
    $t  = $tk ? prospergenics_profiles_report_target( $tk[0], $tk[1] ) : null;
    if ( ! $t ) {
        if ( $tk && 'dismiss' === $do ) { // the item is gone: the reports can still be closed
            prospergenics_profiles_close_reports( $key, $do, $by );
            return 'dismissed';
        }
        return new WP_Error( 'gone', 'The item no longer exists.' );
    }
    $allowed = prospergenics_profiles_moderation_actions( $t['type'] ) + array( 'unhide' => 'Show again' );
    if ( ! isset( $allowed[ $do ] ) ) {
        return new WP_Error( 'action', 'Unknown action.' );
    }
    switch ( $do ) {
        case 'hide':
        case 'unhide':
            $hide = 'hide' === $do;
            if ( 'article' === $t['type'] ) {
                prospergenics_articles_set_hidden( $t['id'], $hide );
            } elseif ( 'profile' === $t['type'] ) {
                prospergenics_profiles_set_hidden( $t['id'], $hide );
            } else {
                wp_set_comment_status( $t['id'], $hide ? 'hold' : 'approve' );
            }
            if ( 'unhide' === $do ) {
                return 'shown';
            }
            break;
        case 'remove':
            if ( 'article' === $t['type'] ) {
                wp_trash_post( $t['id'] );
            } else {
                wp_trash_comment( $t['id'] );
            }
            break;
        case 'warn':
            $sent = prospergenics_profiles_warn( $t, $by, $note );
            if ( is_wp_error( $sent ) ) {
                return $sent;
            }
            break;
        case 'suspend':
            $done = prospergenics_profiles_suspend( $t['owner'], $by, $note );
            if ( is_wp_error( $done ) ) {
                return $done;
            }
            break;
    }
    prospergenics_profiles_close_reports( $t['key'], $do, $by );
    return array( 'hide' => 'hidden', 'remove' => 'removed', 'dismiss' => 'dismissed', 'warn' => 'warned', 'suspend' => 'suspended' )[ $do ];
}

add_action( 'admin_menu', function () {
    // open reports plus what other queues in this screen wait on (filter: e.g. scrutiny items, #6389)
    $n = current_user_can( PROSPERGENICS_MODERATION_CAP ) ? prospergenics_profiles_open_report_count() + (int) apply_filters( 'prospergenics_profiles_moderation_count', 0 ) : 0;
    add_menu_page(
        'Reports and moderation',
        'Reports' . ( $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count" aria-hidden="true">' . (int) $n . '</span><span class="screen-reader-text">' . esc_html( sprintf( '%d to review', $n ) ) . '</span></span>' : '' ),
        PROSPERGENICS_MODERATION_CAP,
        'pg-moderation',
        'prospergenics_profiles_moderation_page',
        'dashicons-flag',
        26
    );
} );

add_action( 'admin_post_pg_profiles_moderate', function () {
    $key = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';
    $do  = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
    check_admin_referer( 'pg_profiles_moderate_' . $key );
    if ( ! current_user_can( PROSPERGENICS_MODERATION_CAP ) ) {
        wp_die( 'Sorry, you are not allowed to moderate.', 403 );
    }
    $note   = isset( $_POST['note'] ) ? wp_unslash( (string) $_POST['note'] ) : '';
    $result = prospergenics_profiles_moderate( $key, $do, get_current_user_id(), $note );
    $done   = is_wp_error( $result ) ? 'error_' . $result->get_error_code() : $result;
    $tab    = isset( $_POST['tab'] ) && 'handled' === $_POST['tab'] ? 'handled' : 'open';
    wp_safe_redirect( add_query_arg( array( 'page' => 'pg-moderation', 'tab' => $tab, 'pg_mod_done' => sanitize_key( $done ) ), admin_url( 'admin.php' ) ) );
    exit;
} );

add_action( 'admin_post_pg_profiles_unsuspend', function () {
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    check_admin_referer( 'pg_profiles_unsuspend_' . $user_id );
    if ( ! current_user_can( PROSPERGENICS_MODERATION_CAP ) ) {
        wp_die( 'Sorry, you are not allowed to moderate.', 403 );
    }
    prospergenics_profiles_unsuspend( $user_id );
    wp_safe_redirect( add_query_arg( array( 'page' => 'pg-moderation', 'tab' => 'suspended', 'pg_mod_done' => 'unsuspended' ), admin_url( 'admin.php' ) ) );
    exit;
} );

/** The queue screen: Open, Handled, Suspended. */
function prospergenics_profiles_moderation_page() {
    if ( ! current_user_can( PROSPERGENICS_MODERATION_CAP ) ) {
        wp_die( 'Sorry, you are not allowed to moderate.', 403 );
    }
    // phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted values
    $tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'open';
    /**
     * The tabs of the screen, key => label. Another queue adds a tab here and renders it on the
     * action prospergenics_profiles_moderation_tab_<key> (e.g. Scrutiny, card #6389). The screen
     * and every handler of such a tab check pg_moderate themselves.
     */
    $tabs = (array) apply_filters( 'prospergenics_profiles_moderation_tabs', array(
        'open'      => 'Open (' . prospergenics_profiles_open_report_count() . ')',
        'handled'   => 'Handled',
        'suspended' => 'Suspended members',
    ) );
    $tab  = isset( $tabs[ $tab ] ) ? $tab : 'open';
    $done = isset( $_GET['pg_mod_done'] ) ? sanitize_key( wp_unslash( $_GET['pg_mod_done'] ) ) : '';
    $msgs = array(
        'hidden'      => 'Hidden. Only its owner and staff can see it now.',
        'shown'       => 'Shown again.',
        'removed'     => 'Moved to the trash.',
        'dismissed'   => 'The reports are dismissed.',
        'warned'      => 'The member was warned by email.',
        'suspended'   => 'The member is suspended: they cannot log in and their profile is hidden.',
        'unsuspended' => 'The suspension is lifted: the member can log in again.',
    );
    $base = admin_url( 'admin.php?page=pg-moderation' );
    // links and buttons at least 24px high (WCAG 2.5.8), the textarea full width on small screens
    echo '<style>.pg-mod-table a,.pg-mod-table .button{display:inline-block;min-height:30px;line-height:28px}.pg-mod-table textarea{max-width:100%}</style>';
    echo '<div class="wrap"><h1>Reports and moderation</h1>';
    if ( isset( $msgs[ $done ] ) ) {
        echo '<div class="notice notice-success is-dismissible pg-mod-notice"><p>' . esc_html( $msgs[ $done ] ) . '</p></div>';
    } elseif ( 0 === strpos( $done, 'error_' ) ) {
        $why = array(
            'error_pg_profiles_cannot_suspend' => 'This member cannot be suspended (staff, or your own account).',
            'error_pg_profiles_mail_failed'    => 'The warning is recorded, but the email could not be sent. Check the site’s mail setup.',
            'error_gone'                       => 'The item no longer exists.',
        );
        echo '<div class="notice notice-error is-dismissible pg-mod-notice"><p>' . esc_html( isset( $why[ $done ] ) ? $why[ $done ] : 'That did not work. Please try again.' ) . '</p></div>';
    }
    echo '<nav class="nav-tab-wrapper" aria-label="Report lists">';
    foreach ( $tabs as $k => $label ) {
        printf( '<a class="nav-tab%s" href="%s"%s>%s</a>', $k === $tab ? ' nav-tab-active' : '', esc_url( add_query_arg( 'tab', $k, $base ) ), $k === $tab ? ' aria-current="page"' : '', esc_html( $label ) );
    }
    echo '</nav>';

    if ( 'suspended' === $tab ) {
        prospergenics_profiles_moderation_suspended_table();
        echo '</div>';
        return;
    }
    if ( ! in_array( $tab, array( 'open', 'handled' ), true ) ) {
        do_action( 'prospergenics_profiles_moderation_tab_' . $tab );
        echo '</div>';
        return;
    }
    $groups = prospergenics_profiles_report_groups( $tab );
    if ( ! $groups ) {
        echo '<p>' . esc_html( 'open' === $tab ? 'No open reports. Nothing to review.' : 'No handled reports yet.' ) . '</p></div>';
        return;
    }
    $types   = prospergenics_profiles_report_types();
    $reasons = prospergenics_profiles_report_reasons();
    echo '<table class="widefat striped pg-mod-table"><caption class="screen-reader-text">' . esc_html( 'open' === $tab ? 'Open reports, one row per reported item' : 'Handled reports' ) . '</caption><thead><tr>';
    echo '<th scope="col">Item</th><th scope="col">Owner</th><th scope="col">Reports</th><th scope="col">' . ( 'open' === $tab ? 'Action' : 'Outcome' ) . '</th></tr></thead><tbody>';
    foreach ( $groups as $g ) {
        $tk    = prospergenics_profiles_report_parse_key( $g['key'] );
        $t     = $tk ? prospergenics_profiles_report_target( $tk[0], $tk[1] ) : null;
        $owner = $t ? get_userdata( $t['owner'] ) : null;
        echo '<tr><td>';
        echo '<strong>' . esc_html( $tk ? $types[ $tk[0] ] : 'Item' ) . '</strong><br />';
        if ( $t ) {
            echo '<a href="' . esc_url( $t['url'] ) . '">' . esc_html( $t['title'] ) . '</a>';
            if ( ! empty( $t['excerpt'] ) ) {
                echo '<p class="description">“' . esc_html( $t['excerpt'] ) . '”</p>';
            }
            echo $t['hidden'] ? ' <span class="description">(hidden)</span>' : '';
            echo $t['removed'] ? ' <span class="description">(in the trash)</span>' : '';
            echo $t['admin_url'] ? '<br /><a href="' . esc_url( $t['admin_url'] ) . '">Edit in wp-admin<span class="screen-reader-text"> ' . esc_html( $t['title'] ) . '</span></a>' : '';
        } else {
            echo '<em>Deleted</em>';
        }
        echo '</td><td>';
        if ( $owner ) {
            echo '<a href="' . esc_url( prospergenics_profiles_url( $owner->ID ) ) . '">' . esc_html( $owner->display_name ) . '</a>';
            $w = get_user_meta( $owner->ID, '_pg_profiles_warnings', true );
            echo is_array( $w ) && $w ? '<br /><span class="description">' . esc_html( sprintf( '%d earlier warning(s)', count( $w ) ) ) . '</span>' : '';
            echo prospergenics_profiles_is_suspended( $owner->ID ) ? '<br /><strong>Suspended</strong>' : '';
        } else {
            echo '–';
        }
        echo '</td><td><ul style="margin:0">';
        foreach ( $g['reports'] as $r ) {
            $by     = get_userdata( (int) $r->post_author );
            $reason = (string) get_post_meta( $r->ID, '_pg_profiles_report_reason', true );
            $det    = (string) get_post_meta( $r->ID, '_pg_profiles_report_details', true );
            echo '<li><strong>' . esc_html( isset( $reasons[ $reason ] ) ? $reasons[ $reason ] : $reason ) . '</strong>, by ' . esc_html( $by ? $by->display_name : 'a former member' ) . ', ' . esc_html( human_time_diff( strtotime( $r->post_date_gmt . ' UTC' ) ) ) . ' ago';
            echo '' !== $det ? '<br /><span class="description">“' . esc_html( $det ) . '”</span>' : '';
            echo '</li>';
        }
        echo '</ul></td><td>';
        if ( 'open' === $tab ) {
            prospergenics_profiles_moderation_form( $g['key'], $t, $owner );
        } else {
            $r0     = $g['reports'][0];
            $action = (string) get_post_meta( $r0->ID, '_pg_profiles_report_action', true );
            $by     = get_userdata( (int) get_post_meta( $r0->ID, '_pg_profiles_report_by', true ) );
            $at     = (int) get_post_meta( $r0->ID, '_pg_profiles_report_at', true );
            $labels = prospergenics_profiles_moderation_actions( $tk ? $tk[0] : '' ) + array( 'remove' => 'Remove' );
            echo esc_html( ( isset( $labels[ $action ] ) ? $labels[ $action ] : $action ) . ( $by ? ' by ' . $by->display_name : '' ) . ( $at ? ', ' . human_time_diff( $at ) . ' ago' : '' ) );
            if ( $t && $t['hidden'] ) {
                echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="margin-top:6px">';
                echo '<input type="hidden" name="action" value="pg_profiles_moderate" /><input type="hidden" name="do" value="unhide" /><input type="hidden" name="tab" value="handled" />';
                echo '<input type="hidden" name="target" value="' . esc_attr( $g['key'] ) . '" />';
                wp_nonce_field( 'pg_profiles_moderate_' . $g['key'] );
                echo '<button type="submit" class="button">Show again<span class="screen-reader-text"> ' . esc_html( $t['title'] ) . '</span></button></form>';
            }
        }
        echo '</td></tr>';
    }
    echo '</tbody></table></div>';
}

/** The action form of one open item. */
function prospergenics_profiles_moderation_form( $key, $t, $owner ) {
    $id = 'pg-mod-note-' . sanitize_html_class( str_replace( ':', '-', $key ) );
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="pg_profiles_moderate" />';
    echo '<input type="hidden" name="target" value="' . esc_attr( $key ) . '" />';
    wp_nonce_field( 'pg_profiles_moderate_' . $key );
    if ( ! $t ) {
        echo '<button type="submit" class="button" name="do" value="dismiss">Dismiss</button></form>';
        return;
    }
    echo '<p><label for="' . esc_attr( $id ) . '">Note to the member (for Warn and Suspend)</label><br />';
    echo '<textarea id="' . esc_attr( $id ) . '" name="note" rows="2" cols="30" maxlength="2000"></textarea></p><p>';
    $can_suspend = $owner && prospergenics_profiles_can_be_suspended( $owner->ID, get_current_user_id() ) && ! prospergenics_profiles_is_suspended( $owner->ID );
    foreach ( prospergenics_profiles_moderation_actions( $t['type'] ) as $do => $label ) {
        if ( ( 'hide' === $do && $t['hidden'] ) || ( 'remove' === $do && $t['removed'] ) || ( in_array( $do, array( 'warn', 'suspend' ), true ) && ! $owner ) || ( 'suspend' === $do && ! $can_suspend ) ) {
            continue;
        }
        printf(
            '<button type="submit" class="button%s" name="do" value="%s" style="margin:0 4px 4px 0">%s<span class="screen-reader-text"> %s</span></button>',
            'hide' === $do ? ' button-primary' : '',
            esc_attr( $do ),
            esc_html( $label ),
            esc_html( $t['title'] )
        );
    }
    echo '</p></form>';
}

function prospergenics_profiles_moderation_suspended_table() {
    $users = get_users( array( 'meta_key' => '_pg_profiles_suspended', 'number' => 200 ) );
    if ( ! $users ) {
        echo '<p>No member is suspended.</p>';
        return;
    }
    echo '<table class="widefat striped"><caption class="screen-reader-text">Suspended members</caption><thead><tr><th scope="col">Member</th><th scope="col">Suspended</th><th scope="col">Reason</th><th scope="col">Action</th></tr></thead><tbody>';
    foreach ( $users as $u ) {
        $s  = get_user_meta( $u->ID, '_pg_profiles_suspended', true );
        $s  = is_array( $s ) ? $s : array( 'time' => 0, 'by' => 0, 'reason' => '' );
        $by = get_userdata( (int) $s['by'] );
        echo '<tr><td>' . esc_html( $u->display_name ) . '<br /><span class="description">' . esc_html( $u->user_login ) . '</span></td>';
        echo '<td>' . esc_html( $s['time'] ? wp_date( 'j M Y, H:i', (int) $s['time'] ) : '' ) . ( $by ? '<br /><span class="description">by ' . esc_html( $by->display_name ) . '</span>' : '' ) . '</td>';
        echo '<td>' . esc_html( (string) $s['reason'] ) . '</td><td>';
        echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '"><input type="hidden" name="action" value="pg_profiles_unsuspend" /><input type="hidden" name="user_id" value="' . (int) $u->ID . '" />';
        wp_nonce_field( 'pg_profiles_unsuspend_' . (int) $u->ID );
        echo '<button type="submit" class="button">Unsuspend<span class="screen-reader-text"> ' . esc_html( $u->display_name ) . '</span></button></form></td></tr>';
    }
    echo '</tbody></table>';
}

/* ───────────────────────── front-end styles ───────────────────────── */

add_action( 'wp_enqueue_scripts', function () {
    $on = is_singular( array( 'pg_article', 'pg_lesson' ) ) || ( function_exists( 'prospergenics_profiles_route' ) && 'profile' === prospergenics_profiles_route() );
    if ( ! $on || is_404() ) {
        return;
    }
    $css = '/assets/platform/community.css';
    $dir = get_template_directory();
    wp_enqueue_style( 'pg-community', get_template_directory_uri() . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
}, 20 );

/* ───────────────────────── personal data ───────────────────────── */

/** The reports a member made. @return WP_Post[] */
function prospergenics_profiles_reports_by( $user_id ) {
    return get_posts( array(
        'post_type' => 'pg_report', 'post_status' => 'any', 'author' => (int) $user_id,
        'posts_per_page' => -1, 'suppress_filters' => true, 'no_found_rows' => true,
    ) );
}

function prospergenics_profiles_moderation_exporter( $email, $page = 1 ) {
    $user  = get_user_by( 'email', $email );
    $items = array();
    if ( ! $user ) {
        return array( 'data' => $items, 'done' => true );
    }
    $reasons = prospergenics_profiles_report_reasons();
    $types   = prospergenics_profiles_report_types();
    foreach ( prospergenics_profiles_reports_by( $user->ID ) as $r ) {
        $tk     = prospergenics_profiles_report_parse_key( get_post_meta( $r->ID, '_pg_profiles_report_target', true ) );
        $t      = $tk ? prospergenics_profiles_report_target( $tk[0], $tk[1] ) : null;
        $reason = (string) get_post_meta( $r->ID, '_pg_profiles_report_reason', true );
        $items[] = array(
            'group_id'          => 'prospergenics-reports',
            'group_label'       => 'Reports you made',
            'group_description' => 'Content you reported to the ProsperGenics team.',
            'item_id'           => 'pg-report-' . (int) $r->ID,
            'data'              => array(
                array( 'name' => 'Reported', 'value' => $tk ? $types[ $tk[0] ] : '' ),
                array( 'name' => 'Item', 'value' => $t ? $t['title'] . ( '' !== $t['url'] ? ' (' . $t['url'] . ')' : '' ) : '(deleted)' ),
                array( 'name' => 'Reason', 'value' => isset( $reasons[ $reason ] ) ? $reasons[ $reason ] : $reason ),
                array( 'name' => 'Your note', 'value' => (string) get_post_meta( $r->ID, '_pg_profiles_report_details', true ) ),
                array( 'name' => 'Sent', 'value' => $r->post_date_gmt . ' UTC' ),
                array( 'name' => 'Status', 'value' => (string) get_post_meta( $r->ID, '_pg_profiles_report_status', true ) ),
            ),
        );
    }
    $s = get_user_meta( $user->ID, '_pg_profiles_suspended', true );
    if ( is_array( $s ) ) {
        $items[] = array(
            'group_id'    => 'prospergenics-moderation',
            'group_label' => 'Moderation of your account',
            'item_id'     => 'pg-suspension',
            'data'        => array(
                array( 'name' => 'Suspended since', 'value' => gmdate( 'Y-m-d H:i', (int) $s['time'] ) . ' UTC' ),
                array( 'name' => 'Reason', 'value' => (string) $s['reason'] ),
            ),
        );
    }
    $w = get_user_meta( $user->ID, '_pg_profiles_warnings', true );
    foreach ( is_array( $w ) ? $w : array() as $i => $entry ) {
        $items[] = array(
            'group_id'    => 'prospergenics-moderation',
            'group_label' => 'Moderation of your account',
            'item_id'     => 'pg-warning-' . (int) $i,
            'data'        => array(
                array( 'name' => 'Warning', 'value' => gmdate( 'Y-m-d H:i', (int) $entry['time'] ) . ' UTC' ),
                array( 'name' => 'About', 'value' => (string) $entry['target'] ),
                array( 'name' => 'Note', 'value' => (string) $entry['note'] ),
            ),
        );
    }
    return array( 'data' => $items, 'done' => true );
}

/** Delete the reports a member made and their warnings. @return int reports deleted */
function prospergenics_profiles_erase_reports( $user_id ) {
    $n = 0;
    foreach ( prospergenics_profiles_reports_by( $user_id ) as $r ) {
        if ( wp_delete_post( $r->ID, true ) ) {
            $n++;
        }
    }
    delete_user_meta( (int) $user_id, '_pg_profiles_warnings' );
    wp_cache_delete( 'open', 'pg_profiles_reports' );
    return $n;
}

function prospergenics_profiles_moderation_eraser( $email, $page = 1 ) {
    $user      = get_user_by( 'email', $email );
    $n         = $user ? prospergenics_profiles_erase_reports( $user->ID ) : 0;
    $suspended = $user && prospergenics_profiles_is_suspended( $user->ID );
    return array(
        'items_removed'  => $n > 0,
        'items_retained' => $suspended,
        'messages'       => $suspended ? array( 'The account suspension was kept: erasing personal data does not lift it.' ) : array(),
        'done'           => true,
    );
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
    $exporters['prospergenics-moderation'] = array( 'exporter_friendly_name' => 'ProsperGenics reports and moderation', 'callback' => 'prospergenics_profiles_moderation_exporter' );
    return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
    $erasers['prospergenics-moderation'] = array( 'eraser_friendly_name' => 'ProsperGenics reports and moderation', 'callback' => 'prospergenics_profiles_moderation_eraser' );
    return $erasers;
} );

// A deleted account: its reports go, and so do the reports about its profile.
add_action( 'delete_user', function ( $user_id ) {
    prospergenics_profiles_erase_reports( $user_id );
    $ids = get_posts( array(
        'post_type' => 'pg_report', 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'suppress_filters' => true,
        'meta_key' => '_pg_profiles_report_target', 'meta_value' => 'profile:' . (int) $user_id,
    ) );
    foreach ( $ids as $id ) {
        wp_delete_post( $id, true );
    }
}, 5 );

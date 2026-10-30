<?php
/**
 * Projects track: the manual forms (card #6388: "Manual forms"). Every form is a plain POST to
 * admin-post.php, works without JavaScript, carries its own nonce and is checked against the role
 * matrix in helpers.php before anything is written:
 *
 *   pg_projects_create        a verified member adds a project/product/service/employer + their contribution
 *   pg_projects_join          a verified member adds their own contribution to an existing project
 *   pg_projects_contribution  a member edits their own contribution (staff: an unclaimed placeholder's)
 *   pg_projects_leave         a member removes their own contribution; staff remove anyone's
 *   pg_projects_update        the creator or staff edit the project itself
 *   pg_projects_status        the creator or staff confirm / dispute someone else's contribution
 *   pg_projects_delete        staff, or the creator while nobody else contributed
 *   pg_projects_hide          staff hide a project or show it again
 *
 * Results come back as ?pg_portfolio=<code> on the page the form was on; a validation error keeps
 * what was typed for one request (a short transient per member) so nothing has to be re-entered.
 */

defined( 'ABSPATH' ) || exit;

/** Messages for ?pg_portfolio=<code>: [kind, text]. */
function prospergenics_projects_notices() {
    return array(
        'added'     => array( 'ok', 'Added to your portfolio.' ),
        'joined'    => array( 'ok', 'Your contribution is added. The project owner can now confirm it.' ),
        'updated'   => array( 'ok', 'Your contribution is saved.' ),
        'reclaimed' => array( 'ok', 'Your contribution is saved. Because you changed it, it shows as claimed again until the project owner confirms it.' ),
        'unchanged' => array( 'info', 'Nothing changed.' ),
        'left'      => array( 'ok', 'Your contribution is removed from the project.' ),
        'removed'   => array( 'ok', 'The contribution is removed from the project.' ),
        'project'   => array( 'ok', 'The project is saved.' ),
        'confirmed' => array( 'ok', 'The contribution is confirmed.' ),
        'disputed'  => array( 'ok', 'The contribution is marked as disputed.' ),
        'claimed'   => array( 'ok', 'The contribution is back to claimed.' ),
        'deleted'   => array( 'ok', 'The project is deleted.' ),
        'hidden'    => array( 'ok', 'The project is hidden: only its creator and staff can see it.' ),
        'shown'     => array( 'ok', 'The project is visible again.' ),
        'already'   => array( 'info', 'You already have a contribution on this project. Edit it on your profile.' ),
        'invalid'   => array( 'error', 'Please correct the fields marked below.' ),
        'error'     => array( 'error', 'That did not work. Please reload the page and try again.' ),
    );
}

/** The notice for the current request's ?pg_portfolio=, or null. */
function prospergenics_projects_current_notice() {
    $code = isset( $_GET['pg_portfolio'] ) ? sanitize_key( wp_unslash( $_GET['pg_portfolio'] ) ) : '';
    $all  = prospergenics_projects_notices();
    return isset( $all[ $code ] ) ? $all[ $code ] : null;
}

/** Keep what a member typed after a validation error, for the next page view only. */
function prospergenics_projects_remember_form( $user_id, $form, array $values, array $errors ) {
    set_transient( '_pg_projects_form_' . (int) $user_id, array( 'form' => (string) $form, 'values' => $values, 'errors' => $errors ), 10 * MINUTE_IN_SECONDS );
}

/** What a member typed into $form before an error (values, errors), read once. */
function prospergenics_projects_recall_form( $user_id, $form ) {
    static $cache = array();
    $user_id = (int) $user_id;
    if ( ! array_key_exists( $user_id, $cache ) ) {
        $cache[ $user_id ] = $user_id ? get_transient( '_pg_projects_form_' . $user_id ) : false;
        if ( $cache[ $user_id ] ) {
            delete_transient( '_pg_projects_form_' . $user_id );
        }
    }
    $s = $cache[ $user_id ];
    return ( is_array( $s ) && isset( $s['form'] ) && $s['form'] === $form ) ? $s : null;
}

/** A string from $_POST (unslashed), or ''. */
function prospergenics_projects_post( $key ) {
    return isset( $_POST[ $key ] ) && is_string( $_POST[ $key ] ) ? wp_unslash( $_POST[ $key ] ) : '';
}

/** An array of strings from $_POST (unslashed). */
function prospergenics_projects_post_list( $key ) {
    if ( ! isset( $_POST[ $key ] ) || ! is_array( $_POST[ $key ] ) ) {
        return array();
    }
    return array_values( array_filter( wp_unslash( $_POST[ $key ] ), 'is_string' ) );
}

/** The project fields from the form. */
function prospergenics_projects_project_input() {
    return array(
        'title'       => sanitize_text_field( prospergenics_projects_post( 'title' ) ),
        'kind'        => sanitize_key( prospergenics_projects_post( 'kind' ) ),
        'url'         => trim( prospergenics_projects_post( 'url' ) ),
        'period'      => sanitize_text_field( prospergenics_projects_post( 'period' ) ),
        'description' => sanitize_textarea_field( prospergenics_projects_post( 'description' ) ),
        'skills'      => sanitize_text_field( prospergenics_projects_post( 'skills' ) ),
    );
}

/** The contribution fields from the form. */
function prospergenics_projects_contribution_input() {
    return array(
        'role'         => sanitize_text_field( prospergenics_projects_post( 'role' ) ),
        'contribution' => sanitize_textarea_field( prospergenics_projects_post( 'contribution' ) ),
        'evidence'     => prospergenics_projects_evidence_from_form(
            array_map( 'sanitize_text_field', prospergenics_projects_post_list( 'evidence_label' ) ),
            array_map( 'trim', prospergenics_projects_post_list( 'evidence_url' ) )
        ),
    );
}

/**
 * Where to go back to: a same-site URL the form names (its own page), else the member's profile.
 * The fragment (#pgp-portfolio, #pgj-…) is kept.
 */
function prospergenics_projects_back_url( $user_id, $code, $fragment = '' ) {
    $back = wp_validate_redirect( esc_url_raw( prospergenics_projects_post( 'back' ) ), '' );
    if ( '' === $back ) {
        $back = function_exists( 'prospergenics_profiles_url' ) ? prospergenics_profiles_url( $user_id, array( 'edit' => 1 ) ) : home_url( '/' );
        $fragment = '' !== $fragment ? $fragment : 'pgp-portfolio';
    }
    $back = remove_query_arg( array( 'pg_portfolio', 'pg_project_q' ), preg_replace( '/#.*$/', '', $back ) );
    $back = add_query_arg( 'pg_portfolio', $code, $back );
    return $back . ( '' !== $fragment ? '#' . rawurlencode( $fragment ) : '' );
}

/** Stop: an expired form, a missing login or a refused permission (403, nothing written). */
function prospergenics_projects_refuse( $message = '' ) {
    wp_die(
        esc_html( '' !== $message ? $message : 'You are not allowed to do this, or the form has expired. Please go back, reload the page and try again.' ),
        'Not allowed',
        array( 'response' => 403, 'back_link' => true )
    );
}

/** Nonce + login check every handler starts with; returns the acting user ID. */
function prospergenics_projects_start( $nonce_action ) {
    $uid = get_current_user_id();
    $n   = isset( $_POST['_pgj_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['_pgj_nonce'] ) ) : '';
    if ( ! $uid || ! wp_verify_nonce( $n, $nonce_action ) || 'POST' !== ( isset( $_SERVER['REQUEST_METHOD'] ) ? $_SERVER['REQUEST_METHOD'] : '' ) ) {
        prospergenics_projects_refuse();
    }
    return $uid;
}

/** The project a form names, or a 403. */
function prospergenics_projects_posted_project() {
    $post = prospergenics_projects_get( absint( prospergenics_projects_post( 'project_id' ) ) );
    if ( ! $post || 'trash' === $post->post_status ) {
        prospergenics_projects_refuse( 'This project does not exist (any more).' );
    }
    return $post;
}

function prospergenics_projects_go( $url ) {
    wp_safe_redirect( $url, 303 );
    exit;
}

// Logged-out visitors posting any of these forms get the same refusal.
foreach ( array( 'create', 'join', 'contribution', 'leave', 'update', 'status', 'delete', 'hide' ) as $prospergenics_projects_action ) {
    add_action( 'admin_post_nopriv_pg_projects_' . $prospergenics_projects_action, function () {
        prospergenics_projects_refuse( 'Please log in first.' );
    } );
}
unset( $prospergenics_projects_action );

/* create: a new project with the member's own contribution */
add_action( 'admin_post_pg_projects_create', function () {
    $uid = prospergenics_projects_start( 'pg_projects_create' );
    if ( ! prospergenics_projects_user_can_create( $uid ) ) {
        prospergenics_projects_refuse( 'Please confirm your email address before you add to your portfolio.' );
    }
    $p_in = prospergenics_projects_project_input();
    $c_in = prospergenics_projects_contribution_input();
    list( $project, $p_err )      = prospergenics_projects_validate_project( $p_in );
    list( $contribution, $c_err ) = prospergenics_projects_validate_contribution( $c_in );
    if ( $p_err || $c_err ) {
        prospergenics_projects_remember_form( $uid, 'create', array_merge( $p_in, $c_in ), array_merge( $p_err, $c_err ) );
        prospergenics_projects_go( prospergenics_projects_back_url( $uid, 'invalid', 'pgj-new' ) );
    }
    $id = prospergenics_projects_create( $uid, $project, $contribution );
    prospergenics_projects_go( prospergenics_projects_back_url( $uid, is_wp_error( $id ) ? 'error' : 'added' ) );
} );

/* join: the member's own contribution on an existing project */
add_action( 'admin_post_pg_projects_join', function () {
    $uid  = prospergenics_projects_start( 'pg_projects_join' );
    $post = prospergenics_projects_posted_project();
    if ( ! prospergenics_projects_user_can_create( $uid ) || ! prospergenics_projects_can_view( $post, $uid ) ) {
        prospergenics_projects_refuse( 'Please confirm your email address before you add to your portfolio.' );
    }
    if ( prospergenics_projects_find_contribution( prospergenics_projects_contributions( $post->ID ), $uid ) >= 0 ) {
        prospergenics_projects_go( prospergenics_projects_back_url( $uid, 'already' ) );
    }
    $c_in = prospergenics_projects_contribution_input();
    list( $contribution, $c_err ) = prospergenics_projects_validate_contribution( $c_in );
    if ( $c_err ) {
        prospergenics_projects_remember_form( $uid, 'join-' . $post->ID, $c_in, $c_err );
        prospergenics_projects_go( prospergenics_projects_back_url( $uid, 'invalid', 'pgj-join-' . $post->ID ) );
    }
    $r = prospergenics_projects_save_contribution( $post->ID, $uid, $contribution );
    prospergenics_projects_go( prospergenics_projects_back_url( $uid, is_wp_error( $r ) ? 'error' : 'joined' ) );
} );

/* contribution: edit your own (staff: an unclaimed placeholder's) */
add_action( 'admin_post_pg_projects_contribution', function () {
    $uid    = prospergenics_projects_start( 'pg_projects_contribution' );
    $post   = prospergenics_projects_posted_project();
    $member = absint( prospergenics_projects_post( 'user_id' ) );
    $member = $member ? $member : $uid;
    $staff  = prospergenics_projects_is_staff( $uid );
    if ( ! prospergenics_projects_can_edit_contribution( $uid, $member, $staff, prospergenics_projects_is_placeholder( $member ) )
        || ( $member === $uid && prospergenics_projects_user_suspended( $uid ) ) ) {
        prospergenics_projects_refuse();
    }
    $list = prospergenics_projects_contributions( $post->ID );
    $had  = prospergenics_projects_find_contribution( $list, $member ) >= 0;
    if ( ! $had && ! ( $member !== $uid && $staff ) ) {
        prospergenics_projects_refuse( 'You have no contribution on this project.' );
    }
    $c_in = prospergenics_projects_contribution_input();
    list( $contribution, $c_err ) = prospergenics_projects_validate_contribution( $c_in );
    if ( $c_err ) {
        prospergenics_projects_remember_form( $uid, 'contribution-' . $post->ID . '-' . $member, $c_in, $c_err );
        prospergenics_projects_go( prospergenics_projects_back_url( $uid, 'invalid', 'pgj-c-' . $post->ID . '-' . $member ) );
    }
    $before = $had ? $list[ prospergenics_projects_find_contribution( $list, $member ) ]['status'] : 'claimed';
    $r      = prospergenics_projects_save_contribution( $post->ID, $member, $contribution );
    if ( is_wp_error( $r ) ) {
        prospergenics_projects_go( prospergenics_projects_back_url( $uid, 'error' ) );
    }
    $code = 'unchanged' === $r ? 'unchanged' : ( 'added' === $r ? 'joined' : ( 'claimed' !== $before ? 'reclaimed' : 'updated' ) );
    prospergenics_projects_go( prospergenics_projects_back_url( $uid, $code ) );
} );

/* leave: remove your own contribution; staff remove anyone's */
add_action( 'admin_post_pg_projects_leave', function () {
    $uid    = prospergenics_projects_start( 'pg_projects_leave' );
    $post   = prospergenics_projects_posted_project();
    $member = absint( prospergenics_projects_post( 'user_id' ) );
    $member = $member ? $member : $uid;
    if ( ! prospergenics_projects_can_remove_contribution( $uid, $member, prospergenics_projects_is_staff( $uid ) ) ) {
        prospergenics_projects_refuse();
    }
    $r = prospergenics_projects_remove_contribution( $post->ID, $member );
    if ( is_wp_error( $r ) ) {
        prospergenics_projects_go( prospergenics_projects_back_url( $uid, 'error' ) );
    }
    if ( 'deleted' === $r && $member !== $uid ) {
        prospergenics_projects_go( add_query_arg( 'pg_portfolio', 'deleted', get_post_type_archive_link( 'pg_project' ) ) );
    }
    prospergenics_projects_go( prospergenics_projects_back_url( $uid, $member === $uid ? 'left' : 'removed' ) );
} );

/* update: the project itself, by its creator or staff */
add_action( 'admin_post_pg_projects_update', function () {
    $uid  = prospergenics_projects_start( 'pg_projects_update' );
    $post = prospergenics_projects_posted_project();
    if ( ! prospergenics_projects_can_edit_project( $uid, $post->post_author, prospergenics_projects_is_staff( $uid ) ) || prospergenics_projects_user_suspended( $uid ) ) {
        prospergenics_projects_refuse();
    }
    $p_in = prospergenics_projects_project_input();
    list( $project, $p_err ) = prospergenics_projects_validate_project( $p_in );
    if ( $p_err ) {
        prospergenics_projects_remember_form( $uid, 'project-' . $post->ID, $p_in, $p_err );
        prospergenics_projects_go( prospergenics_projects_back_url( $uid, 'invalid', 'pgj-edit' ) );
    }
    $r = prospergenics_projects_update( $post->ID, $project );
    // the slug may have changed with the name: go back to the project's current URL
    $url = add_query_arg( 'pg_portfolio', is_wp_error( $r ) ? 'error' : 'project', prospergenics_projects_url( $post->ID ) );
    prospergenics_projects_go( $url );
} );

/* status: confirm / dispute / back to claimed, by the creator or staff, never your own */
add_action( 'admin_post_pg_projects_status', function () {
    $uid    = prospergenics_projects_start( 'pg_projects_status' );
    $post   = prospergenics_projects_posted_project();
    $member = absint( prospergenics_projects_post( 'user_id' ) );
    $status = sanitize_key( prospergenics_projects_post( 'status' ) );
    if ( ! $member || ! prospergenics_projects_can_confirm( $uid, $post->post_author, $member, prospergenics_projects_is_staff( $uid ) ) || prospergenics_projects_user_suspended( $uid ) ) {
        prospergenics_projects_refuse();
    }
    $r = prospergenics_projects_set_status( $post->ID, $member, $status, $uid );
    prospergenics_projects_go( prospergenics_projects_back_url( $uid, is_wp_error( $r ) ? 'error' : $status, 'pgj-contributors' ) );
} );

/* delete: staff, or the creator while nobody else contributed */
add_action( 'admin_post_pg_projects_delete', function () {
    $uid    = prospergenics_projects_start( 'pg_projects_delete' );
    $post   = prospergenics_projects_posted_project();
    $others = 0;
    foreach ( prospergenics_projects_contributions( $post->ID ) as $c ) {
        $others += (int) $c['user_id'] !== (int) $post->post_author ? 1 : 0;
    }
    if ( ! prospergenics_projects_can_delete_project( $uid, $post->post_author, prospergenics_projects_is_staff( $uid ), $others ) ) {
        prospergenics_projects_refuse( $others && (int) $post->post_author === $uid
            ? 'Other members have contributions on this project, so it stays. You can remove your own contribution, or ask the ProsperGenics team.'
            : '' );
    }
    prospergenics_projects_delete( $post->ID );
    $back = function_exists( 'prospergenics_profiles_url' ) && ! prospergenics_projects_is_staff( $uid )
        ? prospergenics_profiles_url( $uid, array( 'edit' => 1, 'pg_portfolio' => 'deleted' ) ) . '#pgp-portfolio'
        : add_query_arg( 'pg_portfolio', 'deleted', get_post_type_archive_link( 'pg_project' ) );
    prospergenics_projects_go( $back );
} );

/* hide / show: staff moderation */
add_action( 'admin_post_pg_projects_hide', function () {
    $uid  = prospergenics_projects_start( 'pg_projects_hide' );
    $post = prospergenics_projects_posted_project();
    if ( ! prospergenics_projects_is_staff( $uid ) ) {
        prospergenics_projects_refuse();
    }
    $hide = '1' === prospergenics_projects_post( 'hidden' );
    prospergenics_projects_set_hidden( $post->ID, $hide, $uid );
    prospergenics_projects_go( add_query_arg( 'pg_portfolio', $hide ? 'hidden' : 'shown', prospergenics_projects_url( $post->ID ) ) );
} );

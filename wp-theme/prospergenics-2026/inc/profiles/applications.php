<?php
/**
 * Profiles track: "Apply to write" and "Apply to teach" (audit ARC-8, gap "Let members apply to
 * become an instructor or author").
 *
 * - A member who may not write or teach yet sees a short form where they are refused: /articles/mine/
 *   ("Apply to write"), /instructor/dashboard/ and the course wizard ("Apply to teach"). It stores a
 *   request in user meta _pg_profiles_application_<write|teach>: the motivation, an optional sample
 *   link, when it was sent and, once decided, the decision, who made it, when and an optional note.
 *   One open request per type; after a decline the member can apply again 30 days later. Only
 *   members with a confirmed email address can apply.
 * - The administrators get an email for each new request; Users → Requests (promote_users) lists
 *   them with Approve and Decline (with a note) buttons, each a nonce-checked POST.
 * - Approving "write" gives the member the pg_writer role (inc/articles/roles.php: write and submit
 *   articles, upload their own images, nothing else). Approving "teach" sets the user meta
 *   _pg_courses_instructor, which the courses track's user_has_cap filter turns into the five
 *   instructor capabilities (inc/courses/data-model.php; interim until jengo-iam #6341). The roles
 *   table is not touched for teaching.
 * - The member gets an email either way. The requests are in the personal data export, and the
 *   profile eraser removes them (every _pg_profiles_ key; inc/profiles/privacy.php).
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROFILES_REAPPLY_DAYS     = 30;
const PROSPERGENICS_PROFILES_MOTIVATION_MIN   = 20;
const PROSPERGENICS_PROFILES_MOTIVATION_MAX   = 2000;

/** The request types. */
function prospergenics_profiles_application_types() {
    return array(
        'write' => array(
            'button'   => 'Apply to write',
            'heading'  => 'Write for ProsperGenics',
            'role'     => 'a writer',
            'doing'    => 'write articles',
            'question' => 'What would you like to write about, and why?',
            'sample'   => 'A sample of your writing (link, optional)',
            'back'     => '/articles/mine/',
        ),
        'teach' => array(
            'button'   => 'Apply to teach',
            'heading'  => 'Teach on ProsperGenics',
            'role'     => 'an instructor',
            'doing'    => 'teach courses',
            'question' => 'What would you like to teach, and what is your experience with it?',
            'sample'   => 'A link that shows your work or teaching (optional)',
            'back'     => '/instructor/dashboard/',
        ),
    );
}

/* ───────────────────────── pure rules ───────────────────────── */

/**
 * Where a member stands for one request type.
 *
 * @param mixed $stored  What user meta holds (array or '').
 * @param int   $now     Unix time.
 * @param bool  $granted Whether the member already may do it.
 * @return array{state:string,retry_at:int} state: granted|pending|cooldown|open
 */
function prospergenics_profiles_application_state( $stored, $now, $granted ) {
    if ( $granted ) {
        return array( 'state' => 'granted', 'retry_at' => 0 );
    }
    if ( ! is_array( $stored ) || empty( $stored['status'] ) ) {
        return array( 'state' => 'open', 'retry_at' => 0 );
    }
    if ( 'pending' === $stored['status'] ) {
        return array( 'state' => 'pending', 'retry_at' => 0 );
    }
    if ( 'declined' === $stored['status'] ) {
        $retry = (int) ( isset( $stored['decided'] ) ? $stored['decided'] : 0 ) + PROSPERGENICS_PROFILES_REAPPLY_DAYS * 86400;
        return $retry > (int) $now ? array( 'state' => 'cooldown', 'retry_at' => $retry ) : array( 'state' => 'open', 'retry_at' => 0 );
    }
    // approved, but the right was taken away since: they may ask again
    return array( 'state' => 'open', 'retry_at' => 0 );
}

/**
 * Validate a request. Input must already be unslashed text.
 *
 * @return array{0:array{motivation:string,sample:string},1:array<string,string>}
 */
function prospergenics_profiles_validate_application( $motivation, $sample ) {
    $motivation = trim( str_replace( "\r\n", "\n", (string) $motivation ) );
    $len        = function_exists( 'mb_strlen' ) ? mb_strlen( $motivation ) : strlen( $motivation );
    $sample_in  = trim( (string) $sample );
    $clean      = array(
        'motivation' => function_exists( 'mb_substr' ) ? mb_substr( $motivation, 0, PROSPERGENICS_PROFILES_MOTIVATION_MAX ) : substr( $motivation, 0, PROSPERGENICS_PROFILES_MOTIVATION_MAX ),
        'sample'     => prospergenics_profiles_clean_url( $sample_in ),
    );
    $errors = array();
    if ( $len < PROSPERGENICS_PROFILES_MOTIVATION_MIN ) {
        $errors['motivation'] = sprintf( 'Tell us a little more (at least %d characters).', PROSPERGENICS_PROFILES_MOTIVATION_MIN );
    } elseif ( $len > PROSPERGENICS_PROFILES_MOTIVATION_MAX ) {
        $errors['motivation'] = sprintf( 'Please keep it under %d characters.', PROSPERGENICS_PROFILES_MOTIVATION_MAX );
    }
    if ( '' !== $sample_in && '' === $clean['sample'] ) {
        $errors['sample'] = 'Enter a full link starting with https://, or leave it empty.';
    }
    return array( $clean, $errors );
}

/* ───────────────────────── storage ───────────────────────── */

function prospergenics_profiles_application_key( $type ) {
    return '_pg_profiles_application_' . $type;
}

/** The stored request of a type, or null. */
function prospergenics_profiles_application( $user_id, $type ) {
    $v = get_user_meta( (int) $user_id, prospergenics_profiles_application_key( $type ), true );
    return is_array( $v ) && ! empty( $v['status'] ) ? $v : null;
}

/** Whether the member already may write / teach. */
function prospergenics_profiles_application_granted( $user_id, $type ) {
    if ( 'write' === $type ) {
        return user_can( (int) $user_id, 'edit_pg_articles' );
    }
    return function_exists( 'prospergenics_courses_is_instructor' ) && prospergenics_courses_is_instructor( (int) $user_id );
}

/** prospergenics_profiles_application_state() for a member. */
function prospergenics_profiles_application_status( $user_id, $type ) {
    return prospergenics_profiles_application_state( prospergenics_profiles_application( $user_id, $type ), time(), prospergenics_profiles_application_granted( $user_id, $type ) );
}

/**
 * Store a new request and tell the administrators.
 *
 * @return true|WP_Error
 */
function prospergenics_profiles_submit_application( $user_id, $type, $motivation, $sample ) {
    $types = prospergenics_profiles_application_types();
    if ( ! isset( $types[ $type ] ) ) {
        return new WP_Error( 'pg_profiles_bad_type', 'Unknown request.' );
    }
    if ( ! prospergenics_profiles_is_verified( $user_id ) ) {
        return new WP_Error( 'pg_profiles_unverified', 'Confirm your email address first.' );
    }
    $status = prospergenics_profiles_application_status( $user_id, $type );
    if ( 'open' !== $status['state'] ) {
        return new WP_Error( 'pg_profiles_' . $status['state'], 'You cannot send a request now.' );
    }
    list( $clean, $errors ) = prospergenics_profiles_validate_application( $motivation, $sample );
    if ( $errors ) {
        return new WP_Error( 'pg_profiles_invalid', implode( ' ', $errors ) );
    }
    update_user_meta( (int) $user_id, prospergenics_profiles_application_key( $type ), array(
        'status'     => 'pending',
        'motivation' => $clean['motivation'],
        'sample'     => $clean['sample'],
        'submitted'  => time(),
    ) );
    prospergenics_profiles_notify_admins_of_application( $user_id, $type );
    return true;
}

/** Who hears about new requests: the administrators (filterable). @return int[] */
function prospergenics_profiles_application_recipients() {
    $ids = get_users( array( 'role' => 'administrator', 'fields' => 'ID', 'number' => 20 ) );
    return array_map( 'intval', (array) apply_filters( 'prospergenics_profiles_application_recipients', array_map( 'intval', $ids ) ) );
}

function prospergenics_profiles_notify_admins_of_application( $user_id, $type ) {
    $member = get_userdata( (int) $user_id );
    $req    = prospergenics_profiles_application( $user_id, $type );
    $t      = prospergenics_profiles_application_types()[ $type ];
    if ( ! $member || ! $req ) {
        return;
    }
    $subject = sprintf( '%s asks to %s on ProsperGenics', $member->display_name, $t['doing'] );
    $body    = sprintf(
        "%s (%s) asks to become %s.\n\n%s\n%s\n\nApprove or decline: %s\nTheir profile: %s\n",
        $member->display_name,
        $member->user_email,
        $t['role'],
        $req['motivation'],
        '' !== $req['sample'] ? "\nSample: " . $req['sample'] : '',
        admin_url( 'users.php?page=pg-applications' ),
        prospergenics_profiles_url( $member->ID )
    );
    foreach ( prospergenics_profiles_application_recipients() as $id ) {
        $admin = get_userdata( $id );
        if ( $admin && user_can( $admin, 'promote_users' ) ) {
            prospergenics_profiles_send_mail( $admin->user_email, wp_specialchars_decode( $subject ), $body, 'application_admin', $admin->ID );
        }
    }
}

/**
 * Approve or decline a pending request, grant the right and email the member.
 *
 * @return true|WP_Error
 */
function prospergenics_profiles_decide_application( $user_id, $type, $approve, $note = '' ) {
    $member = get_userdata( (int) $user_id );
    $req    = prospergenics_profiles_application( $user_id, $type );
    $types  = prospergenics_profiles_application_types();
    if ( ! $member || ! isset( $types[ $type ] ) || ! $req || 'pending' !== $req['status'] ) {
        return new WP_Error( 'pg_profiles_no_request', 'There is no open request to decide.' );
    }
    if ( $approve ) {
        prospergenics_profiles_grant( $member, $type );
    }
    $req['status']     = $approve ? 'approved' : 'declined';
    $req['decided']    = time();
    $req['decided_by'] = get_current_user_id();
    $req['note']       = prospergenics_profiles_clip( $note, 1000 );
    update_user_meta( $member->ID, prospergenics_profiles_application_key( $type ), $req );

    $t = $types[ $type ];
    if ( $approve ) {
        $next    = 'write' === $type
            ? sprintf( "You can start right away: %s\nWrite your article, then choose \"Submit for review\". An editor reads it before it is published.", home_url( '/articles/mine/' ) )
            : sprintf( "You can start right away: %s\nThe course wizard walks you through the basics, the lessons and the price.", home_url( '/instructor/dashboard/' ) );
        $subject = sprintf( 'You can now %s on ProsperGenics', $t['doing'] );
        $body    = sprintf( "Hi %s,\n\nGood news: your request to %s was approved.\n\n%s\n%s\n", $member->display_name, $t['doing'], $next, '' !== $req['note'] ? "\nA note from the team:\n" . $req['note'] . "\n" : '' );
    } else {
        $subject = sprintf( 'About your request to %s on ProsperGenics', $t['doing'] );
        $body    = sprintf(
            "Hi %s,\n\nThank you for your request to %s. The team decided not to approve it for now.\n%s\nYou can send a new request from %s.\n",
            $member->display_name,
            $t['doing'],
            '' !== $req['note'] ? "\nThe team's note:\n" . $req['note'] . "\n" : '',
            wp_date( 'j F Y', $req['decided'] + PROSPERGENICS_PROFILES_REAPPLY_DAYS * DAY_IN_SECONDS )
        );
    }
    prospergenics_profiles_send_mail( $member->user_email, wp_specialchars_decode( $subject ), $body, 'application_' . $req['status'], $member->ID );
    return true;
}

/** Give the member what they asked for. */
function prospergenics_profiles_grant( WP_User $member, $type ) {
    if ( 'teach' === $type ) {
        update_user_meta( $member->ID, PROSPERGENICS_COURSES_INSTRUCTOR_META, time() );
        clean_user_cache( $member->ID );
        return;
    }
    if ( user_can( $member, 'edit_pg_articles' ) ) {
        return;
    }
    // a subscriber becomes a writer; any other role (rare) keeps it and adds writing
    if ( in_array( 'subscriber', (array) $member->roles, true ) ) {
        $member->remove_role( 'subscriber' );
    }
    $member->add_role( 'pg_writer' );
}

/* ───────────────────────── the member's side ───────────────────────── */

/**
 * The request box for a page that refuses the member: the form, "we have your request", or "you
 * can ask again on …". Empty for visitors and for members who already may.
 *
 * @param string $type      write|teach
 * @param string $btn_class The page's button class (pga-btn, pgc-btn).
 */
function prospergenics_profiles_application_box( $type, $btn_class ) {
    $types = prospergenics_profiles_application_types();
    $uid   = get_current_user_id();
    if ( ! $uid || ! isset( $types[ $type ] ) ) {
        return '';
    }
    $t      = $types[ $type ];
    $status = prospergenics_profiles_application_status( $uid, $type );
    if ( 'granted' === $status['state'] ) {
        return '';
    }
    $req    = prospergenics_profiles_application( $uid, $type );
    // phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted values
    $flash  = isset( $_GET['pg_apply'] ) ? sanitize_key( wp_unslash( $_GET['pg_apply'] ) ) : '';
    $html   = '<section class="pg-apply" aria-labelledby="pg-apply-' . esc_attr( $type ) . '-h">';
    $html  .= '<h2 class="pg-apply-h" id="pg-apply-' . esc_attr( $type ) . '-h">' . esc_html( $t['heading'] ) . '</h2>';
    if ( 'error' === $flash ) {
        $html .= '<p class="form-notice form-notice-error" role="alert">Your request could not be sent: write at least ' . (int) PROSPERGENICS_PROFILES_MOTIVATION_MIN . ' characters and use a full https:// link (or none). Please try again.</p>';
    }
    if ( 'pending' === $status['state'] ) {
        $html .= '<p class="form-notice form-notice-ok" role="status">' . ( 'sent' === $flash ? 'Thank you, your request is sent. ' : '' )
            . esc_html( sprintf( 'Your request to %s is with the ProsperGenics team (sent %s). We will email you when they have decided.', $t['doing'], wp_date( 'j F Y', (int) $req['submitted'] ) ) ) . '</p>';
        return $html . '</section>';
    }
    $note = $req && 'declined' === $req['status'] && '' !== (string) ( isset( $req['note'] ) ? $req['note'] : '' ) ? ' The team’s note: “' . $req['note'] . '”' : '';
    if ( 'cooldown' === $status['state'] ) {
        $html .= '<p class="pg-apply-text">' . esc_html( sprintf( 'Your request of %s was not approved.%s You can send a new request from %s.', wp_date( 'j F Y', (int) $req['submitted'] ), $note, wp_date( 'j F Y', $status['retry_at'] ) ) ) . '</p>';
        return $html . '</section>';
    }
    if ( ! prospergenics_profiles_is_verified( $uid ) ) {
        $html .= '<p class="pg-apply-text">Confirm your email address first (open the link we emailed you), then you can send a request here.</p>';
        return $html . '</section>';
    }
    if ( '' !== $note ) {
        $html .= '<p class="pg-apply-text">' . esc_html( 'Your earlier request was not approved.' . $note ) . '</p>';
    }
    $id    = 'pg-apply-' . $type;
    $html .= '<details class="pg-apply-details"' . ( 'error' === $flash ? ' open' : '' ) . '><summary class="' . esc_attr( $btn_class ) . '">' . esc_html( $t['button'] ) . '</summary>';
    $html .= '<form class="pg-apply-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    $html .= '<input type="hidden" name="action" value="pg_profiles_apply" /><input type="hidden" name="type" value="' . esc_attr( $type ) . '" />';
    $html .= wp_nonce_field( 'pg_profiles_apply_' . $type, '_wpnonce', true, false );
    $html .= '<p class="field"><label for="' . esc_attr( $id ) . '-why">' . esc_html( $t['question'] ) . '</label>';
    $html .= '<textarea id="' . esc_attr( $id ) . '-why" name="motivation" rows="6" required minlength="' . (int) PROSPERGENICS_PROFILES_MOTIVATION_MIN . '" maxlength="' . (int) PROSPERGENICS_PROFILES_MOTIVATION_MAX . '" aria-describedby="' . esc_attr( $id ) . '-hint"></textarea>';
    $html .= '<span class="pg-apply-hint" id="' . esc_attr( $id ) . '-hint">A few sentences are enough. The ProsperGenics team reads every request.</span></p>';
    $html .= '<p class="field"><label for="' . esc_attr( $id ) . '-sample">' . esc_html( $t['sample'] ) . '</label>';
    $html .= '<input type="url" id="' . esc_attr( $id ) . '-sample" name="sample" placeholder="https://" maxlength="2000" /></p>';
    $html .= '<p><button type="submit" class="' . esc_attr( $btn_class ) . '">Send request</button></p>';
    $html .= '</form></details>';
    return $html . '</section>';
}

// The form posts here (logged-in members only: there is no nopriv handler).
add_action( 'admin_post_pg_profiles_apply', function () {
    $types = prospergenics_profiles_application_types();
    $type  = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
    if ( ! isset( $types[ $type ] ) ) {
        wp_die( 'Unknown request.', 400 );
    }
    check_admin_referer( 'pg_profiles_apply_' . $type );
    $back   = wp_validate_redirect( wp_get_referer(), home_url( $types[ $type ]['back'] ) );
    $back   = remove_query_arg( 'pg_apply', $back );
    $result = prospergenics_profiles_submit_application(
        get_current_user_id(),
        $type,
        isset( $_POST['motivation'] ) ? sanitize_textarea_field( wp_unslash( $_POST['motivation'] ) ) : '',
        isset( $_POST['sample'] ) ? esc_url_raw( wp_unslash( $_POST['sample'] ) ) : ''
    );
    wp_safe_redirect( add_query_arg( 'pg_apply', is_wp_error( $result ) ? 'error' : 'sent', $back ) . '#pg-apply-' . $type . '-h' );
    exit;
} );

/* ───────────────────────── Users → Requests (administrators) ───────────────────────── */

/** Every stored request, pending first, then the latest decisions. @return array<int,array> */
function prospergenics_profiles_all_applications() {
    $rows = array();
    foreach ( array_keys( prospergenics_profiles_application_types() ) as $type ) {
        $ids = get_users( array(
            'fields'      => 'ID',
            'number'      => 500,
            'count_total' => false,
            'meta_query'  => array( array( 'key' => prospergenics_profiles_application_key( $type ), 'compare' => 'EXISTS' ) ),
        ) );
        foreach ( $ids as $id ) {
            $req = prospergenics_profiles_application( (int) $id, $type );
            if ( $req ) {
                $rows[] = array( 'user_id' => (int) $id, 'type' => $type ) + $req;
            }
        }
    }
    usort( $rows, function ( $a, $b ) {
        $pa = 'pending' === $a['status'];
        $pb = 'pending' === $b['status'];
        if ( $pa !== $pb ) {
            return $pa ? -1 : 1;
        }
        return $pa ? (int) $a['submitted'] - (int) $b['submitted'] : (int) ( isset( $b['decided'] ) ? $b['decided'] : 0 ) - (int) ( isset( $a['decided'] ) ? $a['decided'] : 0 );
    } );
    return $rows;
}

/** The number of open requests (for the menu bubble). */
function prospergenics_profiles_pending_application_count() {
    $n = 0;
    foreach ( array_keys( prospergenics_profiles_application_types() ) as $type ) {
        $n += count( get_users( array(
            'fields'      => 'ID',
            'count_total' => false,
            'meta_query'  => array( array( 'key' => prospergenics_profiles_application_key( $type ), 'value' => 's:6:"status";s:7:"pending";', 'compare' => 'LIKE' ) ),
        ) ) );
    }
    return $n;
}

add_action( 'admin_menu', function () {
    $n     = current_user_can( 'promote_users' ) ? prospergenics_profiles_pending_application_count() : 0;
    $title = 'Requests' . ( $n ? ' <span class="awaiting-mod count-' . (int) $n . '"><span class="pending-count">' . (int) $n . '</span></span>' : '' );
    add_users_page( 'Writer and instructor requests', $title, 'promote_users', 'pg-applications', 'prospergenics_profiles_applications_screen' );
} );

function prospergenics_profiles_applications_screen() {
    if ( ! current_user_can( 'promote_users' ) ) {
        wp_die( 'Sorry, you are not allowed to manage requests.', 403 );
    }
    $types   = prospergenics_profiles_application_types();
    $rows    = prospergenics_profiles_all_applications();
    // phpcs:ignore WordPress.Security.NonceVerification -- display only
    $done    = isset( $_GET['pg_done'] ) ? sanitize_key( wp_unslash( $_GET['pg_done'] ) ) : '';
    $notices = array( 'approved' => 'Approved. The member has been emailed.', 'declined' => 'Declined. The member has been emailed.', 'failed' => 'That request was already decided or no longer exists.' );
    ?>
    <div class="wrap">
      <h1>Writer and instructor requests</h1>
      <p>Members ask here to write articles (they become a <strong>Writer</strong>: they write and submit articles for review and upload their own images) or to teach courses (they get the instructor tools for their own courses). Approving or declining emails the member; after a decline they can ask again in <?php echo (int) PROSPERGENICS_PROFILES_REAPPLY_DAYS; ?> days.</p>
      <?php if ( isset( $notices[ $done ] ) ) : ?>
        <div class="notice notice-<?php echo 'failed' === $done ? 'error' : 'success'; ?> is-dismissible"><p><?php echo esc_html( $notices[ $done ] ); ?></p></div>
      <?php endif; ?>
      <?php if ( ! $rows ) : ?>
        <p>No requests yet.</p>
      <?php else : ?>
      <table class="widefat striped">
        <thead><tr><th scope="col">Member</th><th scope="col">Asks to</th><th scope="col">Sent</th><th scope="col">Motivation</th><th scope="col">Status</th><th scope="col">Decision</th></tr></thead>
        <tbody>
        <?php foreach ( $rows as $r ) :
            $u = get_userdata( $r['user_id'] );
            if ( ! $u ) {
                continue;
            }
            $nonce = 'pg_profiles_decide_' . $u->ID . '_' . $r['type']; ?>
          <tr>
            <td><a href="<?php echo esc_url( get_edit_user_link( $u->ID ) ); ?>"><strong><?php echo esc_html( $u->display_name ); ?></strong></a><br /><?php echo esc_html( $u->user_email ); ?><br /><a href="<?php echo esc_url( prospergenics_profiles_url( $u->ID ) ); ?>">Profile</a></td>
            <td><?php echo esc_html( $types[ $r['type'] ]['doing'] ); ?></td>
            <td><?php echo esc_html( wp_date( 'j M Y', (int) $r['submitted'] ) ); ?></td>
            <td><?php echo wpautop( esc_html( $r['motivation'] ) ); ?><?php if ( '' !== (string) $r['sample'] ) : ?><p>Sample: <a href="<?php echo esc_url( $r['sample'] ); ?>" rel="noopener nofollow noreferrer" target="_blank"><?php echo esc_html( $r['sample'] ); ?></a></p><?php endif; ?></td>
            <td><?php echo esc_html( ucfirst( $r['status'] ) ); ?><?php if ( ! empty( $r['decided'] ) ) : $by = get_userdata( (int) $r['decided_by'] ); ?><br /><?php echo esc_html( wp_date( 'j M Y', (int) $r['decided'] ) . ( $by ? ' by ' . $by->display_name : '' ) ); ?><?php endif; ?><?php if ( ! empty( $r['note'] ) ) : ?><br /><em><?php echo esc_html( $r['note'] ); ?></em><?php endif; ?></td>
            <td>
              <?php if ( 'pending' === $r['status'] && current_user_can( 'edit_user', $u->ID ) ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-bottom:8px">
                  <input type="hidden" name="action" value="pg_profiles_decide" />
                  <input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>" />
                  <input type="hidden" name="type" value="<?php echo esc_attr( $r['type'] ); ?>" />
                  <input type="hidden" name="decision" value="approve" />
                  <?php wp_nonce_field( $nonce ); ?>
                  <button type="submit" class="button button-primary">Approve<span class="screen-reader-text"> <?php echo esc_html( $u->display_name ); ?></span></button>
                </form>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="pg_profiles_decide" />
                  <input type="hidden" name="user_id" value="<?php echo (int) $u->ID; ?>" />
                  <input type="hidden" name="type" value="<?php echo esc_attr( $r['type'] ); ?>" />
                  <input type="hidden" name="decision" value="decline" />
                  <?php wp_nonce_field( $nonce ); ?>
                  <label for="pg-note-<?php echo esc_attr( $u->ID . '-' . $r['type'] ); ?>">Note to the member (optional)</label><br />
                  <textarea id="pg-note-<?php echo esc_attr( $u->ID . '-' . $r['type'] ); ?>" name="note" rows="2" cols="30" maxlength="1000"></textarea><br />
                  <button type="submit" class="button">Decline<span class="screen-reader-text"> <?php echo esc_html( $u->display_name ); ?></span></button>
                </form>
              <?php else : ?>
                –
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
    <?php
}

add_action( 'admin_post_pg_profiles_decide', function () {
    $user_id = isset( $_POST['user_id'] ) ? absint( $_POST['user_id'] ) : 0;
    $type    = isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '';
    check_admin_referer( 'pg_profiles_decide_' . $user_id . '_' . $type );
    if ( ! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', $user_id ) ) {
        wp_die( 'Sorry, you are not allowed to decide requests.', 403 );
    }
    $approve = isset( $_POST['decision'] ) && 'approve' === $_POST['decision'];
    $note    = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
    $result  = prospergenics_profiles_decide_application( $user_id, $type, $approve, $note );
    wp_safe_redirect( add_query_arg( 'pg_done', is_wp_error( $result ) ? 'failed' : ( $approve ? 'approved' : 'declined' ), admin_url( 'users.php?page=pg-applications' ) ) );
    exit;
} );

/* ───────────────────────── personal data ───────────────────────── */

/** Export rows for the profile exporter (privacy.php). */
function prospergenics_profiles_application_export_items( $user_id ) {
    $items = array();
    foreach ( prospergenics_profiles_application_types() as $type => $t ) {
        $req = prospergenics_profiles_application( $user_id, $type );
        if ( ! $req ) {
            continue;
        }
        $date    = function ( $ts ) {
            return $ts ? gmdate( 'Y-m-d H:i', (int) $ts ) . ' UTC' : '';
        };
        $items[] = array(
            'group_id'          => 'prospergenics-applications',
            'group_label'       => 'Requests to write or teach',
            'group_description' => 'Your requests to become a writer or an instructor, and the decision on them.',
            'item_id'           => 'pg-application-' . (int) $user_id . '-' . $type,
            'data'              => prospergenics_profiles_export_pairs( array(
                'Request'      => $t['doing'],
                'Motivation'   => $req['motivation'],
                'Sample link'  => isset( $req['sample'] ) ? $req['sample'] : '',
                'Sent'         => $date( isset( $req['submitted'] ) ? $req['submitted'] : 0 ),
                'Status'       => $req['status'],
                'Decided'      => $date( isset( $req['decided'] ) ? $req['decided'] : 0 ),
                'Note'         => isset( $req['note'] ) ? $req['note'] : '',
            ) ),
        );
    }
    return $items;
}

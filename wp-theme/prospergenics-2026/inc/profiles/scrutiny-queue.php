<?php
/**
 * Profiles track: the scrutiny review queue (card #6389), a tab of wp-admin → Reports
 * (moderation.php, card #6472), for the capability pg_moderate. Not a second queue: the same
 * screen and menu badge, through moderation.php's prospergenics_profiles_moderation_tabs filter.
 *
 * Views: Waiting for review, More evidence asked, Not confirmed yet (visitors who have not opened
 * the email link), Published, Rejected and unpublished. Each item shows its audit part (the
 * submitter's email address, the IP hash and the log): this screen is the only place it is shown.
 * Decisions are nonce-checked POSTs to admin-post.php; each one is logged.
 */

defined( 'ABSPATH' ) || exit;

add_filter( 'prospergenics_profiles_moderation_tabs', function ( $tabs ) {
    $tabs['scrutiny'] = 'Scrutiny (' . prospergenics_scrutiny_pending_count() . ')';
    return $tabs;
} );

add_filter( 'prospergenics_profiles_moderation_count', function ( $n ) {
    return (int) $n + prospergenics_scrutiny_pending_count();
} );

/** The views of the tab: key => [label, statuses]. */
function prospergenics_scrutiny_queue_views() {
    return array(
        'review'      => array( 'Waiting for review', array( 'pending' ) ),
        'more'        => array( 'More evidence asked', array( 'more' ) ),
        'unconfirmed' => array( 'Email not confirmed yet', array( 'unconfirmed' ) ),
        'published'   => array( 'Published', array( 'approved' ) ),
        'closed'      => array( 'Rejected and unpublished', array( 'rejected', 'withdrawn' ) ),
    );
}

/** Messages after a decision, keyed by ?pgs_done=. */
function prospergenics_scrutiny_queue_notices() {
    return array(
        'approved'         => array( 'success', 'Published. The person concerned is emailed and may respond; the submitter is told.' ),
        'rejected'         => array( 'success', 'Rejected. The submitter got the reason by email.' ),
        'more'             => array( 'success', 'The submitter is asked for more evidence by email, with a private link to add it.' ),
        'withdrawn'        => array( 'success', 'Unpublished.' ),
        'response_removed' => array( 'success', 'The response is removed. The person concerned may respond again.' ),
        'error_note'       => array( 'error', 'Please give a reason: it is sent to the submitter (or kept in the log).' ),
        'error_edit'       => array( 'error', 'The edited text must be 20 to 4,000 characters. Nothing was changed.' ),
        'error_state'      => array( 'error', 'That is not possible for this item now (someone may have decided already).' ),
        'error_gone'       => array( 'error', 'The item no longer exists.' ),
    );
}

add_action( 'admin_post_pg_scrutiny_decide', function () {
    $item = isset( $_POST['item'] ) ? absint( $_POST['item'] ) : 0;
    $do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
    check_admin_referer( 'pg_scrutiny_decide_' . $item );
    if ( ! current_user_can( PROSPERGENICS_MODERATION_CAP ) ) {
        wp_die( 'Sorry, you are not allowed to moderate.', 403 );
    }
    $note = isset( $_POST['note'] ) ? wp_unslash( (string) $_POST['note'] ) : '';
    if ( 'remove_response' === $do ) {
        $result = prospergenics_scrutiny_remove_response( $item, get_current_user_id(), $note );
    } elseif ( in_array( $do, array( 'approve', 'reject', 'more', 'withdraw' ), true ) ) {
        $edited = 'approve' === $do && isset( $_POST['body'] ) ? wp_unslash( (string) $_POST['body'] ) : null;
        $result = prospergenics_scrutiny_decide( $item, $do, get_current_user_id(), $note, 'wp', $edited );
    } else {
        $result = new WP_Error( 'state', 'Unknown action.' );
    }
    $done = is_wp_error( $result ) ? 'error_' . $result->get_error_code() : $result;
    $view = isset( $_POST['view'] ) ? sanitize_key( wp_unslash( $_POST['view'] ) ) : 'review';
    $view = isset( prospergenics_scrutiny_queue_views()[ $view ] ) ? $view : 'review';
    wp_safe_redirect( add_query_arg( array( 'page' => 'pg-moderation', 'tab' => 'scrutiny', 'view' => $view, 'pgs_done' => sanitize_key( $done ) ), admin_url( 'admin.php' ) ) . ( is_wp_error( $result ) ? '#pgs-q-' . $item : '' ) );
    exit;
} );

/** The audit log of an item, for staff. */
function prospergenics_scrutiny_audit_html( $item_id ) {
    $a     = prospergenics_scrutiny_audit_data( $item_id );
    $names = array(
        'submitted' => 'Sent', 'mail_confirm' => 'Confirmation email', 'confirmed' => 'Email confirmed', 'review_requested' => 'Entered review',
        'edited' => 'Text edited', 'approve' => 'Approved', 'reject' => 'Rejected', 'more' => 'More evidence asked', 'withdraw' => 'Unpublished',
        'more_supplied' => 'Submitter added evidence', 'response_posted' => 'Response posted', 'response_removed' => 'Response removed',
        'response_erased' => 'Response erased (privacy)', 'anonymised' => 'Submitter anonymised (privacy)',
        'mail_submitter_approved' => 'Submitter emailed: published', 'mail_submitter_rejected' => 'Submitter emailed: rejected',
        'mail_submitter_more' => 'Submitter emailed: more evidence', 'mail_concerned' => 'Person concerned emailed',
    );
    $h  = '<details class="pgs-audit"><summary>Audit log (staff only)</summary>';
    $h .= '<p class="description">Email address: ' . esc_html( '' !== $a['email'] ? $a['email'] : '(none or erased)' ) . '<br />IP hash: <code>' . esc_html( '' !== $a['ip'] ? substr( $a['ip'], 0, 16 ) : '(none)' ) . '</code></p><ol>';
    foreach ( $a['log'] as $e ) {
        $by   = (int) $e['by'] ? get_userdata( (int) $e['by'] ) : null;
        $who  = $by ? $by->display_name : ( 'django-work' === $e['source'] ? 'Django Work' : ( 'privacy' === $e['source'] ? 'privacy request' : 'system / submitter' ) );
        $line = wp_date( 'j M Y H:i', (int) $e['at'] ) . ' · ' . ( isset( $names[ $e['event'] ] ) ? $names[ $e['event'] ] : $e['event'] ) . ' · ' . $who;
        $d    = is_array( $e['data'] ) ? $e['data'] : array();
        if ( isset( $d['sent'] ) ) {
            $line .= $d['sent'] ? ' (sent)' : ' (FAILED to send)';
        }
        $h .= '<li>' . esc_html( $line );
        if ( ! empty( $d['note'] ) ) {
            $h .= '<br /><span class="description">Note: ' . esc_html( (string) $d['note'] ) . '</span>';
        }
        if ( isset( $d['before'], $d['after'] ) && $d['before'] !== $d['after'] ) {
            $h .= '<br /><span class="description">Before: “' . esc_html( prospergenics_scrutiny_excerpt( (string) $d['before'], 300 ) ) . '”<br />After: “' . esc_html( prospergenics_scrutiny_excerpt( (string) $d['after'], 300 ) ) . '”</span>';
        }
        if ( isset( $d['body'] ) && in_array( $e['event'], array( 'response_posted', 'response_removed' ), true ) ) {
            $h .= '<br /><span class="description">“' . esc_html( prospergenics_scrutiny_excerpt( (string) $d['body'], 300 ) ) . '”</span>';
        }
        $h .= '</li>';
    }
    return $h . '</ol></details>';
}

/** The decision form of one item. */
function prospergenics_scrutiny_decision_form( array $r, $view ) {
    $id = 'pgs-q-' . (int) $r['id'];
    $h  = '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    $h .= '<input type="hidden" name="action" value="pg_scrutiny_decide" /><input type="hidden" name="item" value="' . (int) $r['id'] . '" /><input type="hidden" name="view" value="' . esc_attr( $view ) . '" />';
    $h .= wp_nonce_field( 'pg_scrutiny_decide_' . (int) $r['id'], '_wpnonce', true, false );
    if ( in_array( $r['status'], array( 'pending', 'more' ), true ) ) {
        $h .= '<p><label for="' . esc_attr( $id ) . '-body">Text to publish (you may edit it; edits are logged)</label><br />';
        $h .= '<textarea id="' . esc_attr( $id ) . '-body" name="body" rows="5" cols="40" maxlength="' . (int) PROSPERGENICS_SCRUTINY_MAX_BODY . '">' . esc_textarea( $r['body'] ) . '</textarea></p>';
    }
    $h .= '<p><label for="' . esc_attr( $id ) . '-note">' . ( 'approved' === $r['status'] ? 'Reason (required to unpublish or remove the response)' : 'Reason or request to the submitter (required to reject or ask for more)' ) . '</label><br />';
    $h .= '<textarea id="' . esc_attr( $id ) . '-note" name="note" rows="2" cols="40" maxlength="2000"></textarea></p><p>';
    $buttons = array();
    if ( in_array( $r['status'], array( 'pending', 'more' ), true ) ) {
        $buttons['approve'] = array( 'Approve and publish', ' button-primary' );
        if ( 'pending' === $r['status'] ) {
            $buttons['more'] = array( 'Ask for more evidence', '' );
        }
        $buttons['reject'] = array( 'Reject', '' );
    } elseif ( 'approved' === $r['status'] ) {
        $buttons['withdraw'] = array( 'Unpublish', '' );
        if ( $r['response'] ) {
            $buttons['remove_response'] = array( 'Remove the response', '' );
        }
    }
    foreach ( $buttons as $do => $b ) {
        $h .= '<button type="submit" class="button' . $b[1] . '" name="do" value="' . esc_attr( $do ) . '" style="margin:0 4px 4px 0">' . esc_html( $b[0] ) . '<span class="screen-reader-text"> item ' . (int) $r['id'] . '</span></button>';
    }
    return $h . '</p></form>';
}

/** The Scrutiny tab. */
add_action( 'prospergenics_profiles_moderation_tab_scrutiny', function () {
    if ( ! current_user_can( PROSPERGENICS_MODERATION_CAP ) ) {
        wp_die( 'Sorry, you are not allowed to moderate.', 403 );
    }
    $views = prospergenics_scrutiny_queue_views();
    // phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted values
    $view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'review';
    $view = isset( $views[ $view ] ) ? $view : 'review';
    $done = isset( $_GET['pgs_done'] ) ? sanitize_key( wp_unslash( $_GET['pgs_done'] ) ) : '';
    $msgs = prospergenics_scrutiny_queue_notices();
    if ( isset( $msgs[ $done ] ) ) {
        echo '<div class="notice notice-' . esc_attr( $msgs[ $done ][0] ) . ' is-dismissible"><p>' . esc_html( $msgs[ $done ][1] ) . '</p></div>';
    } elseif ( '' !== $done ) {
        echo '<div class="notice notice-error is-dismissible"><p>That did not work. Please try again.</p></div>';
    }
    echo '<style>.pgs-q textarea{width:100%;max-width:520px}.pgs-q td{vertical-align:top}.pgs-q .pgs-kind{display:inline-block;font-weight:600;padding:1px 8px;border-radius:999px;background:#e7f4ec;color:#0f3d28}.pgs-q .pgs-text{max-width:60ch}.pgs-q .button{min-height:30px}.pgs-views a{display:inline-block;min-height:24px;line-height:24px}</style>';
    echo '<p>Evidence, corrections, questions, reviews and disputes about people, projects and profile entries (card #6389). Nothing is public before it is approved here. Visitors’ items appear under “Waiting for review” once they confirmed their email address.</p>';
    echo '<ul class="subsubsub pgs-views">';
    $links = array();
    foreach ( $views as $k => $v ) {
        $n       = count( prospergenics_scrutiny_items_by_status( $v[1], 999 ) );
        $links[] = '<li><a href="' . esc_url( admin_url( 'admin.php?page=pg-moderation&tab=scrutiny&view=' . $k ) ) . '"' . ( $k === $view ? ' class="current" aria-current="page"' : '' ) . '>' . esc_html( $v[0] ) . ' <span class="count">(' . (int) $n . ')</span></a>';
    }
    echo implode( ' |</li>', $links ) . '</li></ul><br class="clear" />';

    $items = prospergenics_scrutiny_items_by_status( $views[ $view ][1] );
    if ( ! $items ) {
        echo '<p>' . esc_html( 'review' === $view ? 'Nothing waiting for review.' : 'Nothing here.' ) . '</p>';
        return;
    }
    $kinds    = prospergenics_scrutiny_kinds();
    $disc     = prospergenics_scrutiny_disclosures();
    $statuses = prospergenics_scrutiny_statuses();
    echo '<table class="widefat striped pgs-q"><caption class="screen-reader-text">' . esc_html( $views[ $view ][0] ) . '</caption><thead><tr>';
    echo '<th scope="col">Item</th><th scope="col">Text and sources</th><th scope="col">Submitter</th><th scope="col">' . ( in_array( $view, array( 'review', 'more', 'published' ), true ) ? 'Decision' : 'Status' ) . '</th></tr></thead><tbody>';
    foreach ( $items as $r ) {
        $t      = prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] );
        $member = $r['member_id'] ? get_userdata( $r['member_id'] ) : null;
        echo '<tr id="pgs-q-' . (int) $r['id'] . '"><td>';
        echo '<span class="pgs-kind">' . esc_html( isset( $kinds[ $r['kind'] ] ) ? $kinds[ $r['kind'] ][0] : $r['kind'] ) . '</span><br />';
        echo 'About ' . ( $t ? '<a href="' . esc_url( prospergenics_scrutiny_item_url( $t, $r['id'] ) ) . '">' . esc_html( $t['label'] ) . '</a>' . ( $t['open'] ? '' : ' <strong>(hidden, suspended or unpublished: nothing shows)</strong>' ) : '<em>a deleted target</em>' );
        echo '<br /><span class="description">Sent ' . esc_html( human_time_diff( $r['submitted'] ) ) . ' ago</span>';
        if ( $t && $t['concerned'] ) {
            $who = array_filter( array_map( function ( $uid ) {
                $u = get_userdata( $uid );
                return $u ? $u->display_name : '';
            }, $t['concerned'] ) );
            echo '<br /><span class="description">Concerned (response rights): ' . esc_html( implode( ', ', $who ) ) . '</span>';
        }
        echo '</td><td><div class="pgs-text">' . wpautop( wp_kses( $r['body'], prospergenics_scrutiny_allowed_html() ) ) . '</div>';
        if ( $r['sources'] ) {
            echo '<p><strong>Sources</strong></p><ul>';
            foreach ( $r['sources'] as $u ) {
                echo '<li><a href="' . esc_url( $u ) . '" rel="nofollow noopener noreferrer" target="_blank">' . esc_html( $u ) . '<span class="screen-reader-text"> (opens in a new tab)</span></a></li>';
            }
            echo '</ul>';
        }
        if ( $r['response'] ) {
            echo '<p><strong>Response from ' . esc_html( (string) $r['response']['name'] ) . ':</strong></p>' . wpautop( wp_kses( $r['response']['body'], prospergenics_scrutiny_allowed_html() ) );
        }
        echo '</td><td>';
        echo '<strong>' . esc_html( '' !== $r['name'] ? $r['name'] : 'A former contributor' ) . '</strong><br />';
        echo $member ? '<a href="' . esc_url( prospergenics_profiles_url( $member->ID ) ) . '">Member profile<span class="screen-reader-text"> of ' . esc_html( $member->display_name ) . '</span></a>' : '<span class="description">Not a member' . ( 'unconfirmed' === $r['status'] ? '' : '; email address confirmed' ) . '</span>';
        echo '<br />Connection: ' . esc_html( ( isset( $disc[ $r['disclosure'] ] ) ? $disc[ $r['disclosure'] ][0] : '' ) . ( '' !== $r['disclosure_text'] ? ': ' . $r['disclosure_text'] : '' ) );
        echo prospergenics_scrutiny_audit_html( $r['id'] ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built
        echo '</td><td>';
        if ( in_array( $view, array( 'review', 'more', 'published' ), true ) ) {
            echo prospergenics_scrutiny_decision_form( $r, $view ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built
        } else {
            echo esc_html( isset( $statuses[ $r['status'] ] ) ? $statuses[ $r['status'] ] : $r['status'] );
            $note = (string) get_post_meta( $r['id'], '_pg_scrutiny_note', true );
            echo '' !== $note && 'unconfirmed' !== $r['status'] ? '<br /><span class="description">' . esc_html( $note ) . '</span>' : '';
        }
        echo '</td></tr>';
    }
    echo '</tbody></table>';
} );

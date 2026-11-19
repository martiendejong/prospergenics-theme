<?php
/**
 * Profiles track: the public side of scrutiny (card #6389): the two hooks pages call, the forms
 * and their handlers. The rules and the data are in scrutiny.php.
 *
 *   do_action( 'prospergenics_scrutiny_public', $target_type, $target_id );   published items
 *   do_action( 'prospergenics_scrutiny_actions', $target_type, $target_id );  "Suggest a correction…"
 *
 * $target_type: person (user id), project (pg_project id) or claim (a profile entry's post id).
 * /in/<slug>/ calls both for the person and for each experience and education entry; the
 * projects track calls them on /projects/<slug>/. Both print nothing for a target that does not
 * exist, is not published, is hidden or suspended, or that the viewer may not see.
 *
 * Works without JavaScript: <details> disclosures and plain POST forms to admin-post.php, which
 * redirect back with ?pgs=<notice>&pgs_k=<target key>.
 */

defined( 'ABSPATH' ) || exit;

/* ───────────────────────── notices ───────────────────────── */

/** Messages after an action, keyed by the ?pgs= value: [kind, text]. */
function prospergenics_scrutiny_notices() {
    return array(
        'sent'         => array( 'ok', 'Thank you. It is with the ProsperGenics team now: nothing is public before they have reviewed it.' ),
        'check_email'  => array( 'ok', 'Almost done: we emailed you a link. Please open it to confirm your email address; then the ProsperGenics team reviews what you sent.' ),
        'confirmed'    => array( 'ok', 'Thank you: your email address is confirmed. The ProsperGenics team will review what you sent; nothing is public before that.' ),
        'already'      => array( 'info', 'This was confirmed already. The ProsperGenics team will review it.' ),
        'expired'      => array( 'error', 'This confirmation link has expired, so what you sent was not kept. Please send it again.' ),
        'link_invalid' => array( 'error', 'This link does not work (any more).' ),
        'invalid'      => array( 'error', 'Please check the form: some answers are missing or not right.' ),
        'bot'          => array( 'error', 'It could not be sent. Please wait a moment and try again.' ),
        'rate'         => array( 'error', 'You sent a lot in a short time. Please try again later.' ),
        'mail'         => array( 'error', 'We could not send the confirmation email just now. Please try again in a few minutes.' ),
        'unverified'   => array( 'error', 'Please confirm your email address first: use the link we sent you when you signed up.' ),
        'denied'       => array( 'error', 'Only the person this is about can respond to it.' ),
        'responded'    => array( 'ok', 'Your response is published under the item.' ),
        'response_short' => array( 'error', 'Please write your response.' ),
        'response_long'  => array( 'error', 'Your response is too long: please keep it under 2,000 characters.' ),
        'response_already' => array( 'info', 'You already responded to this item: there is one response per item.' ),
        'more_added'   => array( 'ok', 'Thank you: the ProsperGenics team will review it again.' ),
        'more_empty'   => array( 'error', 'Please add some text or a source.' ),
        'error'        => array( 'error', 'Something went wrong. Please try again.' ),
    );
}

/** Field error messages, field => code => text. */
function prospergenics_scrutiny_field_errors() {
    return array(
        'kind'       => array( 'required' => 'Choose what you are adding.' ),
        'body'       => array( 'short' => 'Please write a little more: at least 20 characters.', 'long' => 'That is too long: please keep it under 4,000 characters.' ),
        'sources'    => array( 'https' => 'Each source must be a full https:// link, one per line.', 'many' => 'At most 5 sources, please.' ),
        'disclosure' => array( 'required' => 'Tell us your connection: this is shown with what you write.', 'describe' => 'Please describe your connection.', 'long' => 'Please keep the description of your connection short.' ),
        'name'       => array( 'required' => 'Please give your name: it is shown with what you write.', 'long' => 'That name is too long.', 'invalid' => 'Please give just your name, without links.' ),
        'email'      => array( 'invalid' => 'Please give a valid email address: we send you a link to confirm it.' ),
    );
}

/** The viewer to render for: the owner previewing their profile "as a visitor" is a visitor. */
function prospergenics_scrutiny_viewer() {
    if ( function_exists( 'prospergenics_profiles_viewer_id' ) && function_exists( 'prospergenics_profiles_route' ) && 'profile' === prospergenics_profiles_route() ) {
        return (int) prospergenics_profiles_viewer_id();
    }
    return get_current_user_id();
}

/** The DOM id of a target's block. */
function prospergenics_scrutiny_dom( array $t ) {
    return 'pgs-' . $t['type'] . '-' . (int) $t['id'];
}

/** Notices shown next to the published items (the rest go with the form). */
function prospergenics_scrutiny_item_notices() {
    return array( 'responded', 'response_short', 'response_long', 'response_already', 'denied' );
}

/**
 * The notice for this target from the query string ('' when none).
 *
 * @param string $where 'items' (next to the published items) or 'form'
 */
function prospergenics_scrutiny_notice_html( array $t, $where = 'form' ) {
    // phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted values
    $code = isset( $_GET['pgs'], $_GET['pgs_k'] ) && $t['key'] === sanitize_text_field( wp_unslash( $_GET['pgs_k'] ) ) ? sanitize_key( wp_unslash( $_GET['pgs'] ) ) : '';
    $all  = prospergenics_scrutiny_notices();
    if ( ! isset( $all[ $code ] ) || ( 'items' === $where ) !== in_array( $code, prospergenics_scrutiny_item_notices(), true ) ) {
        return '';
    }
    $kind = $all[ $code ][0];
    return '<p class="pgs-notice pgs-notice-' . esc_attr( $kind ) . '" role="' . ( 'error' === $kind ? 'alert' : 'status' ) . '">' . esc_html( $all[ $code ][1] ) . '</p>';
}

/* ───────────────────────── drafts (a refused form keeps what was typed) ───────────────────────── */

function prospergenics_scrutiny_draft_key( $target_key ) {
    $who = get_current_user_id() ? 'u' . get_current_user_id() : 'ip' . prospergenics_scrutiny_ip_hash();
    return 'pg_scrutiny_draft_' . md5( $who . '|' . $target_key );
}

function prospergenics_scrutiny_keep_draft( $target_key, array $fields, array $errors ) {
    $keep = array();
    foreach ( array( 'kind', 'body', 'sources', 'disclosure', 'disclosure_text', 'name', 'email' ) as $k ) {
        $keep[ $k ] = isset( $fields[ $k ] ) && is_scalar( $fields[ $k ] ) ? mb_substr( (string) $fields[ $k ], 0, PROSPERGENICS_SCRUTINY_MAX_BODY * 2 ) : '';
    }
    set_transient( prospergenics_scrutiny_draft_key( $target_key ), array( 'fields' => $keep, 'errors' => $errors ), 600 );
}

/** The kept draft for a target (used once). @return array{fields:array,errors:array} */
function prospergenics_scrutiny_take_draft( $target_key ) {
    $d = get_transient( prospergenics_scrutiny_draft_key( $target_key ) );
    if ( ! is_array( $d ) ) {
        return array( 'fields' => array(), 'errors' => array() );
    }
    delete_transient( prospergenics_scrutiny_draft_key( $target_key ) );
    return $d;
}

/* ───────────────────────── the published items ───────────────────────── */

/** The heading text of a target's public record. */
function prospergenics_scrutiny_heading( array $t, $viewer_id ) {
    return 'Evidence, corrections and reviews';
}

/** One published item. */
function prospergenics_scrutiny_item_html( array $r, array $t, $viewer_id ) {
    $member     = $r['member_id'] ? get_userdata( $r['member_id'] ) : null;
    $r['member_url'] = $member && ! empty( prospergenics_profiles_visibility_for( $member->ID, $viewer_id )['profile'] ) ? prospergenics_profiles_url( $member->ID ) : '';
    $p   = prospergenics_scrutiny_public_item( $r );
    $dom = 'pgs-item-' . $p['id'];
    $h   = '<li class="pgs-item pgs-kind-' . esc_attr( $p['kind'] ) . '" id="' . esc_attr( $dom ) . '">';
    $h  .= '<p class="pgs-meta"><span class="pgs-badge">' . esc_html( $p['kind_label'] ) . '</span> ';
    $who = '' !== $p['member_url'] ? '<a href="' . esc_url( $p['member_url'] ) . '">' . esc_html( $p['name'] ) . '</a>' : esc_html( $p['name'] );
    $h  .= '<span class="pgs-by">By ' . $who . ( $p['member'] ? '' : ( 'A former contributor' === $p['name'] ? '' : ' <span class="pgs-muted">(not a member; email address confirmed)</span>' ) ) . '</span>';
    if ( '' !== $p['date'] ) {
        $h .= ' <time class="pgs-date" datetime="' . esc_attr( $p['date'] ) . '">' . esc_html( wp_date( 'j M Y', strtotime( $p['date'] . ' 12:00 UTC' ) ) ) . '</time>';
    }
    $h .= '</p>';
    if ( $p['opinion'] ) {
        $h .= '<p class="pgs-opinion">An opinion: this is the experience of the person who wrote it, not a fact checked by ProsperGenics.</p>';
    }
    $h .= '<div class="pgs-body">' . wpautop( wp_kses( $p['body'], prospergenics_scrutiny_allowed_html() ) ) . '</div>';
    if ( $p['sources'] ) {
        $h .= '<p class="pgs-sources-h">Sources</p><ul class="pgs-sources">';
        foreach ( $p['sources'] as $url ) {
            $label = preg_replace( '#^https://(www\.)?#i', '', $url );
            $label = mb_strlen( $label ) > 70 ? mb_substr( $label, 0, 69 ) . '…' : $label;
            $h    .= '<li><a href="' . esc_url( $url ) . '" rel="nofollow ugc noopener noreferrer" target="_blank">' . esc_html( $label ) . '<span class="screen-reader-text"> (opens in a new tab)</span></a></li>';
        }
        $h .= '</ul>';
    }
    $h .= '<p class="pgs-disclosure"><span class="pgs-muted">Connection, in their words:</span> ' . esc_html( $p['disclosure'] ) . '</p>';
    if ( $p['response'] ) {
        $h .= '<div class="pgs-response"><p class="pgs-response-h">Response from ' . esc_html( '' !== $p['response']['name'] ? $p['response']['name'] : 'the person concerned' );
        $h .= '' !== $p['response']['date'] ? ' <time class="pgs-date" datetime="' . esc_attr( $p['response']['date'] ) . '">' . esc_html( wp_date( 'j M Y', strtotime( $p['response']['date'] . ' 12:00 UTC' ) ) ) . '</time>' : '';
        $h .= '</p><div class="pgs-body">' . wpautop( wp_kses( $p['response']['body'], prospergenics_scrutiny_allowed_html() ) ) . '</div></div>';
    } elseif ( $viewer_id && '' === prospergenics_scrutiny_response_block_for( $p['id'], $viewer_id ) ) {
        $h .= prospergenics_scrutiny_response_form( $p['id'] );
    }
    return $h . '</li>';
}

/** The response form for the person concerned. */
function prospergenics_scrutiny_response_form( $item_id ) {
    $id = 'pgs-respond-' . (int) $item_id;
    $h  = '<details class="pgs-respond"><summary class="pgs-link">Respond<span class="screen-reader-text"> to this item</span></summary>';
    $h .= '<form class="pgs-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    $h .= '<input type="hidden" name="action" value="pg_scrutiny_respond" /><input type="hidden" name="item" value="' . (int) $item_id . '" />';
    $h .= wp_nonce_field( 'pg_scrutiny_respond_' . (int) $item_id, '_wpnonce', false, false );
    $h .= '<div class="pgs-field"><label for="' . esc_attr( $id ) . '">Your public response</label>';
    $h .= '<textarea id="' . esc_attr( $id ) . '" name="body" rows="4" maxlength="' . (int) PROSPERGENICS_SCRUTINY_MAX_RESPONSE . '" required aria-describedby="' . esc_attr( $id ) . '-hint"></textarea>';
    $h .= '<p class="pgs-hint" id="' . esc_attr( $id ) . '-hint">You can respond once to each item. Your response is public at once, shown under the item with your name.</p></div>';
    $h .= '<button type="submit" class="pgs-btn pgs-btn-primary">Post response</button></form></details>';
    return $h;
}

/** The published items about a target ('' when there is nothing to show). */
function prospergenics_scrutiny_public_html( $type, $id ) {
    $t      = prospergenics_scrutiny_target( $type, $id );
    $viewer = prospergenics_scrutiny_viewer();
    if ( ! $t || ! prospergenics_scrutiny_target_visible( $t, $viewer ) ) {
        return '';
    }
    prospergenics_scrutiny_enqueue();
    $items = prospergenics_scrutiny_items_for( $t['key'] );
    $dom   = prospergenics_scrutiny_dom( $t );
    if ( 'claim' === $t['type'] ) {
        if ( ! $items ) {
            return '';
        }
        $h  = '<div class="pgs-public pgs-public-claim" id="' . esc_attr( $dom ) . '">';
        $h .= '<p class="pgs-claim-h">' . esc_html( sprintf( 1 === count( $items ) ? '%d published note about this entry' : '%d published notes about this entry', count( $items ) ) ) . '</p>';
        $h .= '<ol class="pgs-list" aria-label="' . esc_attr( 'Published notes about ' . $t['title'] ) . '">';
        foreach ( $items as $r ) {
            $h .= prospergenics_scrutiny_item_html( $r, $t, $viewer );
        }
        return $h . '</ol>' . prospergenics_scrutiny_notice_html( $t, 'items' ) . '</div>';
    }
    $is_concerned = $viewer && in_array( (int) $viewer, $t['concerned'], true );
    $hid = $dom . '-h';
    $h   = '<section class="pgs-section" id="' . esc_attr( $dom ) . '" aria-labelledby="' . esc_attr( $hid ) . '">';
    $h  .= '<h2 id="' . esc_attr( $hid ) . '">' . esc_html( prospergenics_scrutiny_heading( $t, $viewer ) ) . '</h2>';
    $h  .= '<p class="pgs-intro">' . esc_html( $is_concerned
        ? 'What others added about ' . ( 'person' === $t['type'] ? 'you' : 'this project' ) . ', after the ProsperGenics team reviewed it. You can respond once to each item.'
        : 'Attributed evidence, corrections, questions, reviews and disputes, published after the ProsperGenics team reviewed them. Each shows who wrote it and their connection.' ) . '</p>';
    $h  .= prospergenics_scrutiny_notice_html( $t, 'items' );
    if ( $items ) {
        $h .= '<ol class="pgs-list" aria-label="' . esc_attr( 'Published about ' . $t['title'] ) . '">';
        foreach ( $items as $r ) {
            $h .= prospergenics_scrutiny_item_html( $r, $t, $viewer );
        }
        $h .= '</ol>';
    } else {
        $h .= '<p class="pgs-empty">Nothing is published yet.</p>';
    }
    return $h . '</section>';
}

/* ───────────────────────── the forms ───────────────────────── */

/** One labelled field (input or textarea), with its error. */
function prospergenics_scrutiny_field( $dom, $name, $label, $value, $type, array $errors, $hint = '', array $attrs = array() ) {
    $id    = $dom . '-' . $name;
    $err   = isset( $errors[ $name ], prospergenics_scrutiny_field_errors()[ $name ][ $errors[ $name ] ] ) ? prospergenics_scrutiny_field_errors()[ $name ][ $errors[ $name ] ] : '';
    $desc  = array_filter( array( '' !== $hint ? $id . '-hint' : '', '' !== $err ? $id . '-err' : '' ) );
    $extra = '';
    foreach ( $attrs as $k => $v ) {
        $extra .= true === $v ? ' ' . esc_attr( $k ) : ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
    }
    $extra .= $desc ? ' aria-describedby="' . esc_attr( implode( ' ', $desc ) ) . '"' : '';
    $extra .= '' !== $err ? ' aria-invalid="true"' : '';
    $h      = '<div class="pgs-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
    if ( 'textarea' === $type ) {
        $h .= '<textarea id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '"' . $extra . '>' . esc_textarea( (string) $value ) . '</textarea>';
    } else {
        $h .= '<input type="' . esc_attr( $type ) . '" id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $value ) . '"' . $extra . ' />';
    }
    $h .= '' !== $hint ? '<p class="pgs-hint" id="' . esc_attr( $id ) . '-hint">' . esc_html( $hint ) . '</p>' : '';
    $h .= '' !== $err ? '<p class="pgs-error" id="' . esc_attr( $id ) . '-err">' . esc_html( $err ) . '</p>' : '';
    return $h . '</div>';
}

/** A group of radio buttons in a fieldset, with its error. */
function prospergenics_scrutiny_radios( $dom, $name, $legend, array $options, $value, array $errors ) {
    $err = isset( $errors[ $name ], prospergenics_scrutiny_field_errors()[ $name ][ $errors[ $name ] ] ) ? prospergenics_scrutiny_field_errors()[ $name ][ $errors[ $name ] ] : '';
    $h   = '<fieldset class="pgs-radios"' . ( '' !== $err ? ' aria-describedby="' . esc_attr( $dom . '-' . $name . '-err' ) . '"' : '' ) . '><legend>' . esc_html( $legend ) . '</legend>';
    foreach ( $options as $k => $o ) {
        $rid = $dom . '-' . $name . '-' . $k;
        $h  .= '<div class="pgs-radio"><input type="radio" id="' . esc_attr( $rid ) . '" name="' . esc_attr( $name ) . '" value="' . esc_attr( $k ) . '"' . checked( (string) $value, (string) $k, false ) . ' required' . ( '' !== $o[1] ? ' aria-describedby="' . esc_attr( $rid ) . '-hint"' : '' ) . ' />';
        $h  .= '<label for="' . esc_attr( $rid ) . '">' . esc_html( $o[0] ) . '</label>';
        $h  .= '' !== $o[1] ? '<p class="pgs-hint" id="' . esc_attr( $rid ) . '-hint">' . esc_html( $o[1] ) . '</p>' : '';
        $h  .= '</div>';
    }
    $h .= '' !== $err ? '<p class="pgs-error" id="' . esc_attr( $dom . '-' . $name . '-err' ) . '">' . esc_html( $err ) . '</p>' : '';
    return $h . '</fieldset>';
}

/** The submission form for a target. */
function prospergenics_scrutiny_form( array $t, $viewer ) {
    $dom    = prospergenics_scrutiny_dom( $t ) . '-f';
    $draft  = prospergenics_scrutiny_take_draft( $t['key'] );
    $f      = $draft['fields'];
    $errors = is_array( $draft['errors'] ) ? $draft['errors'] : array();
    $v      = function ( $k, $default = '' ) use ( $f ) {
        return isset( $f[ $k ] ) && '' !== $f[ $k ] ? $f[ $k ] : $default;
    };
    $user   = $viewer ? get_userdata( $viewer ) : null;
    $about  = 'person' === $t['type'] ? $t['title'] : ( 'claim' === $t['type'] ? 'this entry' : 'this project' );

    $h  = '<form class="pgs-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" novalidate>';
    $h .= '<input type="hidden" name="action" value="pg_scrutiny_submit" /><input type="hidden" name="target" value="' . esc_attr( $t['key'] ) . '" />';
    if ( $user ) {
        $h .= wp_nonce_field( 'pg_scrutiny_submit_' . $t['key'], '_wpnonce', false, false );
    }
    $h .= prospergenics_spam_protection_fields();
    if ( 'claim' === $t['type'] ) {
        $h .= '<p class="pgs-about">About: ' . esc_html( $t['label'] ) . '</p>';
    }
    $kinds = array();
    foreach ( prospergenics_scrutiny_kinds() as $k => $kind ) {
        $kinds[ $k ] = array( $kind[1], $kind[2] );
    }
    $h .= prospergenics_scrutiny_radios( $dom, 'kind', 'What would you like to add?', $kinds, $v( 'kind', 'claim' === $t['type'] ? 'correction' : '' ), $errors );
    $h .= prospergenics_scrutiny_field( $dom, 'body', 'Your text', $v( 'body' ), 'textarea', $errors, 'Be specific and factual. At least 20 characters, at most 4,000. Plain text.', array( 'rows' => 5, 'maxlength' => PROSPERGENICS_SCRUTINY_MAX_BODY, 'required' => true ) );
    $h .= prospergenics_scrutiny_field( $dom, 'sources', 'Sources (optional)', $v( 'sources' ), 'textarea', $errors, 'Links that back this up: full https:// links, one per line, at most 5.', array( 'rows' => 2, 'maxlength' => 2600, 'inputmode' => 'url', 'spellcheck' => 'false' ) );
    $disc = array();
    foreach ( prospergenics_scrutiny_disclosures() as $k => $d ) {
        $disc[ $k ] = array( $d[0], '' );
    }
    $h .= prospergenics_scrutiny_radios( $dom, 'disclosure', 'Your connection to ' . $about . ' (shown with your text)', $disc, $v( 'disclosure' ), $errors );
    $h .= prospergenics_scrutiny_field( $dom, 'disclosure_text', 'Describe your connection (for “Other”)', $v( 'disclosure_text' ), 'text', array(), '', array( 'maxlength' => PROSPERGENICS_SCRUTINY_MAX_DISCLOSE ) );
    if ( $user ) {
        $h .= '<p class="pgs-as">You post as <strong>' . esc_html( $user->display_name ) . '</strong>, linked to your profile.</p>';
    } else {
        $h .= '<div class="pgs-grid2">';
        $h .= prospergenics_scrutiny_field( $dom, 'name', 'Your name', $v( 'name' ), 'text', $errors, 'Shown with what you write.', array( 'maxlength' => PROSPERGENICS_SCRUTINY_MAX_NAME, 'autocomplete' => 'name', 'required' => true ) );
        $h .= prospergenics_scrutiny_field( $dom, 'email', 'Your email address', $v( 'email' ), 'email', $errors, 'We send you a link to confirm it. Never shown.', array( 'maxlength' => 254, 'autocomplete' => 'email', 'required' => true ) );
        $h .= '</div>';
        $h .= '<p class="pgs-as">No account needed. Members can <a href="' . esc_url( wp_login_url( preg_replace( '/#.*$/', '', $t['url'] ) ) ) . '">log in</a> to post under their profile.</p>';
    }
    $h .= '<p class="pgs-note">The ProsperGenics team reviews everything before it is public. Your name, your connection and the date are shown with it; your email address never is. The person concerned is told and may respond.</p>';
    $h .= '<button type="submit" class="pgs-btn pgs-btn-primary">Send for review</button>';
    return $h . '</form>';
}

/** The "add more evidence" form a submitter reaches from the email link. */
function prospergenics_scrutiny_more_form( $item_id, $token ) {
    $r  = prospergenics_scrutiny_record( $item_id );
    $h  = '<div class="pgs-more" id="pgs-more"><h3 class="pgs-more-h">Add the evidence the team asked for</h3>';
    $h .= '<p class="pgs-hint">What you sent: “' . esc_html( prospergenics_scrutiny_excerpt( $r['body'], 300 ) ) . '”</p>';
    $note = (string) get_post_meta( $r['id'], '_pg_scrutiny_note', true );
    $h .= '' !== $note ? '<p class="pgs-hint">The team asked: “' . esc_html( $note ) . '”</p>' : '';
    $h .= '<form class="pgs-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    $h .= '<input type="hidden" name="action" value="pg_scrutiny_more" /><input type="hidden" name="item" value="' . (int) $r['id'] . '" /><input type="hidden" name="t" value="' . esc_attr( $token ) . '" />';
    $h .= prospergenics_spam_protection_fields();
    $h .= prospergenics_scrutiny_field( 'pgs-more', 'body', 'What you add (optional)', '', 'textarea', array(), 'It is added below your text.', array( 'rows' => 4, 'maxlength' => PROSPERGENICS_SCRUTINY_MAX_BODY ) );
    $h .= prospergenics_scrutiny_field( 'pgs-more', 'sources', 'More sources (optional)', '', 'textarea', array(), 'Full https:// links, one per line. At most 5 sources in total.', array( 'rows' => 2, 'maxlength' => 2600, 'spellcheck' => 'false' ) );
    $h .= '<button type="submit" class="pgs-btn pgs-btn-primary">Send it back for review</button></form></div>';
    return $h;
}

/** The "Suggest a correction / add evidence / review" control for a target ('' when not offered). */
function prospergenics_scrutiny_actions_html( $type, $id ) {
    $t      = prospergenics_scrutiny_target( $type, $id );
    $viewer = prospergenics_scrutiny_viewer();
    if ( ! $t || ! prospergenics_scrutiny_target_visible( $t, $viewer ) ) {
        return '';
    }
    prospergenics_scrutiny_enqueue();
    $dom = prospergenics_scrutiny_dom( $t ) . '-add';
    // phpcs:ignore WordPress.Security.NonceVerification -- the token itself is checked
    $more_id  = isset( $_GET['pgs_more'], $_GET['pgs_t'] ) ? absint( $_GET['pgs_more'] ) : 0;
    $more_tok = $more_id ? sanitize_text_field( wp_unslash( $_GET['pgs_t'] ) ) : '';
    $more     = '';
    if ( $more_id && ( $mr = prospergenics_scrutiny_record( $more_id ) ) && $mr['target'] === $t['key'] ) {
        $more = prospergenics_scrutiny_more_token_ok( $more_id, $more_tok )
            ? prospergenics_scrutiny_more_form( $more_id, $more_tok )
            : '<p class="pgs-notice pgs-notice-error" role="alert" id="pgs-more">This link does not work any more: the item was reviewed already, or the link expired.</p>';
    }
    $block = prospergenics_scrutiny_block_for( $type, $id, $viewer );
    if ( 'own' === $block || 'suspended' === $block || 'not_found' === $block ) {
        return $more;
    }
    $compact = 'claim' === $t['type'];
    $notice  = prospergenics_scrutiny_notice_html( $t, 'form' );
    $open    = is_array( get_transient( prospergenics_scrutiny_draft_key( $t['key'] ) ) ); // a refused form comes back open
    $label   = $compact ? 'Suggest a correction' : 'Suggest a correction, add evidence or review';
    $h       = '<div class="pgs-actions' . ( $compact ? ' pgs-compact' : '' ) . '" id="' . esc_attr( $dom ) . '">' . $more . $notice;
    $h      .= '<details class="pgs-add"' . ( $open ? ' open' : '' ) . '><summary class="' . ( $compact ? 'pgs-link' : 'pgs-btn' ) . '">' . esc_html( $label );
    $h      .= $compact ? '<span class="screen-reader-text"> about ' . esc_html( $t['title'] ) . '</span>' : '';
    $h      .= '</summary>';
    if ( 'unverified' === $block ) {
        $h .= '<p class="pgs-notice pgs-notice-info">Please confirm your email address first: use the link we sent you when you signed up (or “Send the link again” at the top of the page).</p>';
    } else {
        $h .= prospergenics_scrutiny_form( $t, $viewer );
    }
    return $h . '</details></div>';
}

add_action( 'prospergenics_scrutiny_public', function ( $type, $id ) {
    echo prospergenics_scrutiny_public_html( (string) $type, (int) $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built
}, 10, 2 );

add_action( 'prospergenics_scrutiny_actions', function ( $type, $id ) {
    echo prospergenics_scrutiny_actions_html( (string) $type, (int) $id ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built
}, 10, 2 );

/* ───────────────────────── styles ───────────────────────── */

function prospergenics_scrutiny_enqueue() {
    $css = '/assets/platform/scrutiny.css';
    $dir = get_template_directory();
    wp_enqueue_style( 'pg-scrutiny', get_template_directory_uri() . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
}

// In the <head> where the hooks are known to run; anywhere else the hooks enqueue it themselves
// (printed in the footer).
add_action( 'wp_enqueue_scripts', function () {
    $on = ( function_exists( 'prospergenics_profiles_route' ) && 'profile' === prospergenics_profiles_route() )
        || ( post_type_exists( 'pg_project' ) && ( is_singular( 'pg_project' ) || is_post_type_archive( 'pg_project' ) ) );
    if ( $on && ! is_404() ) {
        prospergenics_scrutiny_enqueue();
    }
}, 20 );

/* ───────────────────────── handlers (admin-post) ───────────────────────── */

/** Back to the target's page with a notice. */
function prospergenics_scrutiny_back( $t, $notice, $anchor = '' ) {
    $url = $t && '' !== $t['url'] ? preg_replace( '/#.*$/', '', $t['url'] ) : home_url( '/' );
    $url = remove_query_arg( array( 'pgs', 'pgs_k', 'pgs_more', 'pgs_t' ), $url );
    if ( $t ) {
        $url = add_query_arg( array( 'pgs' => $notice, 'pgs_k' => $t['key'] ), $url );
    }
    wp_safe_redirect( $url . '#' . ( '' !== $anchor ? $anchor : ( $t ? prospergenics_scrutiny_dom( $t ) . '-add' : '' ) ) );
    exit;
}

function prospergenics_scrutiny_handle_submit() {
    $key = isset( $_POST['target'] ) ? sanitize_text_field( wp_unslash( $_POST['target'] ) ) : '';
    $tk  = prospergenics_scrutiny_parse_key( $key );
    if ( ! $tk ) {
        wp_die( 'Unknown item.', 400 );
    }
    $user_id = get_current_user_id();
    if ( $user_id ) {
        check_admin_referer( 'pg_scrutiny_submit_' . $key ); // members: CSRF; visitors: the signed form time below
    }
    $t      = prospergenics_scrutiny_target( $tk[0], $tk[1] );
    $post   = wp_unslash( $_POST );
    $fields = array();
    foreach ( array( 'kind', 'body', 'sources', 'disclosure', 'disclosure_text', 'name', 'email' ) as $k ) {
        $fields[ $k ] = isset( $post[ $k ] ) && is_scalar( $post[ $k ] ) ? (string) $post[ $k ] : '';
    }
    if ( '' !== prospergenics_scrutiny_bot_reason( $post, time() ) ) {
        prospergenics_scrutiny_keep_draft( $key, $fields, array() );
        prospergenics_scrutiny_back( $t, 'bot' );
    }
    $result = prospergenics_scrutiny_submit( $user_id, $tk[0], $tk[1], $fields );
    if ( is_wp_error( $result ) ) {
        $code = $result->get_error_code();
        $data = $result->get_error_data();
        prospergenics_scrutiny_keep_draft( $key, $fields, 'invalid' === $code && isset( $data['errors'] ) ? $data['errors'] : array() );
        prospergenics_scrutiny_back( $t, isset( prospergenics_scrutiny_notices()[ $code ] ) ? $code : 'error' );
    }
    prospergenics_scrutiny_back( $t, $user_id ? 'sent' : 'check_email' );
}
add_action( 'admin_post_pg_scrutiny_submit', 'prospergenics_scrutiny_handle_submit' );
add_action( 'admin_post_nopriv_pg_scrutiny_submit', 'prospergenics_scrutiny_handle_submit' );

/** The link in a visitor's confirmation email. */
function prospergenics_scrutiny_handle_confirm() {
    $item  = isset( $_GET['i'] ) ? absint( $_GET['i'] ) : 0;
    $token = isset( $_GET['t'] ) ? sanitize_text_field( wp_unslash( $_GET['t'] ) ) : '';
    $r     = prospergenics_scrutiny_record( $item );
    $t     = $r ? prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] ) : null;
    $res   = prospergenics_scrutiny_confirm( $item, $token );
    if ( 'expired' === $res ) {
        wp_delete_post( $item, true );
    }
    if ( ! $t || ! $t['open'] ) {
        wp_safe_redirect( home_url( '/' ) );
        exit;
    }
    prospergenics_scrutiny_back( $t, 'invalid' === $res ? 'link_invalid' : $res );
}
add_action( 'admin_post_pg_scrutiny_confirm', 'prospergenics_scrutiny_handle_confirm' );
add_action( 'admin_post_nopriv_pg_scrutiny_confirm', 'prospergenics_scrutiny_handle_confirm' );

/** The submitter adds the evidence staff asked for (token from the email; no account needed). */
function prospergenics_scrutiny_handle_more() {
    $item  = isset( $_POST['item'] ) ? absint( $_POST['item'] ) : 0;
    $token = isset( $_POST['t'] ) ? sanitize_text_field( wp_unslash( $_POST['t'] ) ) : '';
    $r     = prospergenics_scrutiny_record( $item );
    $t     = $r ? prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] ) : null;
    $post  = wp_unslash( $_POST );
    if ( '' !== prospergenics_scrutiny_bot_reason( $post, time() ) ) {
        prospergenics_scrutiny_back( $t, 'bot' );
    }
    $res = prospergenics_scrutiny_add_more( $item, $token, isset( $post['body'] ) ? (string) $post['body'] : '', isset( $post['sources'] ) ? (string) $post['sources'] : '' );
    if ( is_wp_error( $res ) ) {
        $code = $res->get_error_code();
        $map  = array( 'invalid' => 'link_invalid', 'empty' => 'more_empty', 'sources_https' => 'invalid', 'sources_many' => 'invalid', 'short' => 'more_empty', 'long' => 'invalid' );
        $back = $t && 'invalid' !== $code ? array( 'pgs_more' => $item, 'pgs_t' => $token ) : array();
        if ( $t && $back ) {
            wp_safe_redirect( add_query_arg( array_merge( $back, array( 'pgs' => isset( $map[ $code ] ) ? $map[ $code ] : 'error', 'pgs_k' => $t['key'] ) ), preg_replace( '/#.*$/', '', $t['url'] ) ) . '#pgs-more' );
            exit;
        }
        prospergenics_scrutiny_back( $t, isset( $map[ $code ] ) ? $map[ $code ] : 'error' );
    }
    prospergenics_scrutiny_back( $t, 'more_added' );
}
add_action( 'admin_post_pg_scrutiny_more', 'prospergenics_scrutiny_handle_more' );
add_action( 'admin_post_nopriv_pg_scrutiny_more', 'prospergenics_scrutiny_handle_more' );

add_action( 'admin_post_nopriv_pg_scrutiny_respond', function () {
    $r = prospergenics_scrutiny_record( isset( $_POST['item'] ) ? absint( $_POST['item'] ) : 0 );
    $t = $r ? prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] ) : null;
    wp_safe_redirect( wp_login_url( $t ? prospergenics_scrutiny_item_url( $t, $r['id'] ) : home_url( '/' ) ) );
    exit;
} );

add_action( 'admin_post_pg_scrutiny_respond', function () {
    $item = isset( $_POST['item'] ) ? absint( $_POST['item'] ) : 0;
    check_admin_referer( 'pg_scrutiny_respond_' . $item );
    $r   = prospergenics_scrutiny_record( $item );
    $t   = $r ? prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] ) : null;
    $res = prospergenics_scrutiny_respond( $item, get_current_user_id(), isset( $_POST['body'] ) ? wp_unslash( (string) $_POST['body'] ) : '' );
    if ( is_wp_error( $res ) ) {
        $code = $res->get_error_code();
        if ( 'denied' === $code || 'login' === $code ) {
            wp_die( 'Only the person this is about can respond to it.', 403 );
        }
        $code = 'already' === $code ? 'response_already' : $code;
        prospergenics_scrutiny_back( $t, isset( prospergenics_scrutiny_notices()[ $code ] ) ? $code : 'error', 'pgs-item-' . $item );
    }
    prospergenics_scrutiny_back( $t, 'responded', 'pgs-item-' . $item );
} );

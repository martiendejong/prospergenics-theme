<?php
/**
 * Profiles track: public scrutiny and corrections (card #6389).
 *
 * Anyone can add attributed evidence, a correction, a question, a review (an opinion, labelled as
 * such) or a dispute about a person (/in/ profile), a project (pg_project, card #6388) or a claim
 * (one profile entry: an experience, education or skill post). Nothing is public before the
 * ProsperGenics team reviewed it.
 *
 * Model: one pg_scrutiny post per item (internal: no UI of its own, not public, not in REST or
 * feeds, not searchable). post_content = the text (wp_kses, a few tags), post_author = the member
 * who wrote it (0 for a visitor). Meta, public part:
 *   _pg_scrutiny_{kind,target,target_type,target_id,sources,disclosure,disclosure_text,name,
 *   status,published_at,response}
 * and the audit part, staff-only and never rendered on the site:
 *   _pg_scrutiny_audit_email  the submitter's address (visitors; members: their account's at the time)
 *   _pg_scrutiny_audit_ip     a keyed hash of the IP address, never the address itself
 *   _pg_scrutiny_audit        the log: submitted, confirmed, edits (before/after), decisions, mail
 * The public views read through prospergenics_scrutiny_public_item(), a whitelist, so nothing of
 * the audit part can reach a page by accident.
 *
 * Flow:
 *   member (logged in, email verified)  → status pending: in the queue at once;
 *   visitor (name + email)              → status unconfirmed, an email with a link; the link moves
 *                                          it to pending. Unconfirmed items go after 3 days.
 *   pending → approved (public; the person concerned is emailed and may post one response),
 *             rejected (with a reason, emailed to the submitter), or
 *             more (more evidence asked: the submitter gets a private link to add text/sources,
 *             which sends it back to pending).
 *   approved → withdrawn (unpublished by staff, with a reason).
 * The queue is a tab of wp-admin → Reports (moderation.php, capability pg_moderate):
 * scrutiny-queue.php. The public markup is scrutiny-render.php.
 *
 * Spam: nonce (members), the site's honeypot and signed form time (inc/spam-protection.php), rate
 * limits per member, per IP and per email address, length limits, https-only source links.
 *
 * Privacy: an exporter and an eraser. Erasing keeps the public text of a published item but
 * anonymises it (no name, no account, no email, no IP hash; "a former contributor"), deletes the
 * submitter's other items and their responses. A deleted account: the same; items about the
 * deleted person or their entries go with them.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_SCRUTINY_POST_TYPE = 'pg_scrutiny';
/** Length limits, in characters of plain text. */
const PROSPERGENICS_SCRUTINY_MIN_BODY     = 20;
const PROSPERGENICS_SCRUTINY_MAX_BODY     = 4000;
const PROSPERGENICS_SCRUTINY_MIN_RESPONSE = 2;
const PROSPERGENICS_SCRUTINY_MAX_RESPONSE = 2000;
const PROSPERGENICS_SCRUTINY_MAX_NAME     = 80;
const PROSPERGENICS_SCRUTINY_MAX_DISCLOSE = 300;
const PROSPERGENICS_SCRUTINY_MAX_SOURCES  = 5;
const PROSPERGENICS_SCRUTINY_MAX_URL      = 500;
/** Rate limits: items per member per hour, per IP per hour, per email address per day. */
const PROSPERGENICS_SCRUTINY_MEMBER_LIMIT = 10;
const PROSPERGENICS_SCRUTINY_IP_LIMIT     = 5;
const PROSPERGENICS_SCRUTINY_EMAIL_LIMIT  = 5;
/** How long a visitor's confirmation link and a "more evidence" link work. */
const PROSPERGENICS_SCRUTINY_CONFIRM_TTL  = 3 * DAY_IN_SECONDS;
const PROSPERGENICS_SCRUTINY_MORE_TTL     = 14 * DAY_IN_SECONDS;

/* ───────────────────────── pure helpers (unit-tested) ───────────────────────── */

/**
 * The kinds of item: key => [label, the call to action, a hint for the form].
 * A review is an opinion and is always shown as one.
 */
function prospergenics_scrutiny_kinds() {
    return array(
        'evidence'   => array( 'Evidence', 'Add evidence', 'Something that supports what is shown, with a source if you have one.' ),
        'correction' => array( 'Correction', 'Suggest a correction', 'Something that is wrong or out of date, and what is right.' ),
        'question'   => array( 'Question', 'Ask a question', 'Something that is unclear. The person concerned can answer it in public.' ),
        'review'     => array( 'Review (opinion)', 'Write a review', 'Your own experience and opinion. It is shown labelled as an opinion, not as a fact.' ),
        'dispute'    => array( 'Dispute', 'Dispute this', 'You think this is not true. Say why, with sources if you can.' ),
    );
}

/** What an item can be about, type => label. */
function prospergenics_scrutiny_target_types() {
    return array( 'person' => 'Person', 'project' => 'Project', 'claim' => 'Profile entry' );
}

/** The conflict disclosure a submitter must give: key => [what they choose, what the public sees]. */
function prospergenics_scrutiny_disclosures() {
    return array(
        'none'   => array( 'I have no connection', 'No connection' ),
        'worked' => array( 'I work or worked with them', 'Works or worked with them' ),
        'self'   => array( 'I am them', 'Is the person concerned' ),
        'other'  => array( 'Other (please describe)', 'Other connection' ),
    );
}

/** Item statuses, status => label (for staff). */
function prospergenics_scrutiny_statuses() {
    return array(
        'unconfirmed' => 'Waiting for the email confirmation',
        'pending'     => 'Waiting for review',
        'more'        => 'More evidence asked',
        'approved'    => 'Published',
        'rejected'    => 'Rejected',
        'withdrawn'   => 'Unpublished',
    );
}

/** "claim:12", or '' for an unknown type or id. */
function prospergenics_scrutiny_key( $type, $id ) {
    return isset( prospergenics_scrutiny_target_types()[ $type ] ) && (int) $id > 0 ? $type . ':' . (int) $id : '';
}

/** [type, id] from a key, or null. */
function prospergenics_scrutiny_parse_key( $key ) {
    if ( ! preg_match( '/^([a-z]+):([1-9][0-9]{0,18})$/', (string) $key, $m ) || ! isset( prospergenics_scrutiny_target_types()[ $m[1] ] ) ) {
        return null;
    }
    return array( $m[1], (int) $m[2] );
}

/** The HTML an item or a response may contain (no links: sources are a field of their own). */
function prospergenics_scrutiny_allowed_html() {
    return array(
        'p'          => array(),
        'br'         => array(),
        'strong'     => array(),
        'em'         => array(),
        'ul'         => array(),
        'ol'         => array(),
        'li'         => array(),
        'blockquote' => array(),
    );
}

/** Plain text of a body, whitespace collapsed (what length limits count). */
function prospergenics_scrutiny_plain( $html ) {
    return trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( (string) $html ), ENT_QUOTES, 'UTF-8' ) ) );
}

/**
 * Clean a text: limited HTML, length checked on the plain text.
 *
 * @return array{body:string,error:string} error: '' | 'short' | 'long'
 */
function prospergenics_scrutiny_clean_text( $raw, $min = PROSPERGENICS_SCRUTINY_MIN_BODY, $max = PROSPERGENICS_SCRUTINY_MAX_BODY ) {
    $body = trim( wp_kses( str_replace( "\r\n", "\n", (string) $raw ), prospergenics_scrutiny_allowed_html() ) );
    $len  = mb_strlen( prospergenics_scrutiny_plain( $body ) );
    if ( $len < $min ) {
        return array( 'body' => $body, 'error' => 'short' );
    }
    if ( $len > $max || strlen( $body ) > $max * 3 ) {
        return array( 'body' => $body, 'error' => 'long' );
    }
    return array( 'body' => $body, 'error' => '' );
}

/**
 * Source links: one per line (or an array), https only, at most 5, duplicates dropped.
 *
 * @return array{urls:string[],error:string} error: '' | 'https' | 'many'
 */
function prospergenics_scrutiny_clean_sources( $raw ) {
    $lines = is_array( $raw ) ? $raw : preg_split( '/[\r\n]+/', (string) $raw );
    $urls  = array();
    foreach ( $lines as $line ) {
        $url = trim( (string) $line );
        if ( '' === $url ) {
            continue;
        }
        $host = parse_url( $url, PHP_URL_HOST );
        if ( strlen( $url ) > PROSPERGENICS_SCRUTINY_MAX_URL || ! preg_match( '#^https://[^\s/?\#]+(?:[/?\#][^\s<>"]*)?$#i', $url ) || ! is_string( $host ) || false === strpos( $host, '.' ) ) {
            return array( 'urls' => $urls, 'error' => 'https' );
        }
        if ( ! in_array( $url, $urls, true ) ) {
            $urls[] = $url;
        }
    }
    if ( count( $urls ) > PROSPERGENICS_SCRUTINY_MAX_SOURCES ) {
        return array( 'urls' => array_slice( $urls, 0, PROSPERGENICS_SCRUTINY_MAX_SOURCES ), 'error' => 'many' );
    }
    return array( 'urls' => $urls, 'error' => '' );
}

/**
 * Validate a submission. Members are attributed with their account, so name and email are only
 * checked for visitors.
 *
 * @param array $in kind, body, sources, disclosure, disclosure_text, name, email
 * @return array{clean:array,errors:array<string,string>} errors: field => code
 */
function prospergenics_scrutiny_validate( array $in, $is_member ) {
    $get    = function ( $k ) use ( $in ) {
        return isset( $in[ $k ] ) && is_scalar( $in[ $k ] ) ? (string) $in[ $k ] : '';
    };
    $errors = array();
    $clean  = array();

    $kind = $get( 'kind' );
    if ( ! isset( prospergenics_scrutiny_kinds()[ $kind ] ) ) {
        $errors['kind'] = 'required';
    }
    $clean['kind'] = $kind;

    $text = prospergenics_scrutiny_clean_text( $get( 'body' ) );
    if ( '' !== $text['error'] ) {
        $errors['body'] = $text['error'];
    }
    $clean['body'] = $text['body'];

    $src = prospergenics_scrutiny_clean_sources( isset( $in['sources'] ) ? $in['sources'] : '' );
    if ( '' !== $src['error'] ) {
        $errors['sources'] = $src['error'];
    }
    $clean['sources'] = $src['urls'];

    $disclosure = $get( 'disclosure' );
    $dtext      = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $get( 'disclosure_text' ) ) ) );
    if ( ! isset( prospergenics_scrutiny_disclosures()[ $disclosure ] ) ) {
        $errors['disclosure'] = 'required';
    } elseif ( 'other' === $disclosure && mb_strlen( $dtext ) < 3 ) {
        $errors['disclosure'] = 'describe';
    } elseif ( mb_strlen( $dtext ) > PROSPERGENICS_SCRUTINY_MAX_DISCLOSE ) {
        $errors['disclosure'] = 'long';
    }
    $clean['disclosure']      = $disclosure;
    $clean['disclosure_text'] = 'other' === $disclosure ? $dtext : '';

    if ( ! $is_member ) {
        $name = trim( preg_replace( '/\s+/u', ' ', sanitize_text_field( $get( 'name' ) ) ) );
        if ( mb_strlen( $name ) < 2 ) {
            $errors['name'] = 'required';
        } elseif ( mb_strlen( $name ) > PROSPERGENICS_SCRUTINY_MAX_NAME ) {
            $errors['name'] = 'long';
        } elseif ( preg_match( '#https?://|www\.|[<>]#i', $name ) ) {
            $errors['name'] = 'invalid';
        }
        $email = strtolower( trim( $get( 'email' ) ) );
        if ( '' === $email || strlen( $email ) > 254 || ! is_email( $email ) ) {
            $errors['email'] = 'invalid';
        }
        $clean['name']  = $name;
        $clean['email'] = $email;
    }
    return array( 'clean' => $clean, 'errors' => $errors );
}

/**
 * Bot signals of a submitted form: the honeypot and the signed form time of
 * prospergenics_spam_protection_fields(). '' when it looks like a person.
 */
function prospergenics_scrutiny_bot_reason( array $post, $now ) {
    if ( ! empty( $post[ PROSPERGENICS_HONEYPOT_FIELD ] ) ) {
        return 'honeypot';
    }
    $time  = isset( $post['pg_form_time'] ) ? (int) $post['pg_form_time'] : 0;
    $token = isset( $post['pg_form_token'] ) ? (string) $post['pg_form_token'] : '';
    if ( ! $time || '' === $token || ! hash_equals( wp_hash( (string) $time ), $token ) ) {
        return 'token';
    }
    if ( $now - $time < PROSPERGENICS_MIN_FILL_SECONDS ) {
        return 'fast';
    }
    if ( $now - $time > PROSPERGENICS_MAX_FORM_AGE ) {
        return 'stale';
    }
    return '';
}

/**
 * Why someone may not submit about a target ('' when they may). Pure: the caller looks the facts up.
 *
 * @param array $f open (target exists, published, not hidden or suspended), visible (to them),
 *                 logged_in, verified, suspended, own (it is about themselves), rate_ok
 */
function prospergenics_scrutiny_submit_block( array $f ) {
    if ( empty( $f['open'] ) || empty( $f['visible'] ) ) {
        return 'not_found';
    }
    if ( ! empty( $f['logged_in'] ) ) {
        if ( ! empty( $f['suspended'] ) ) {
            return 'suspended';
        }
        if ( empty( $f['verified'] ) ) {
            return 'unverified';
        }
        if ( ! empty( $f['own'] ) ) {
            return 'own';
        }
    }
    if ( isset( $f['rate_ok'] ) && ! $f['rate_ok'] ) {
        return 'rate';
    }
    return '';
}

/**
 * The status a decision leads to, or '' when it is not allowed from the current status.
 * approve / reject / more from the review states; withdraw from published.
 */
function prospergenics_scrutiny_next_status( $status, $do ) {
    $map = array(
        'approve'  => array( array( 'pending', 'more' ), 'approved' ),
        'reject'   => array( array( 'pending', 'more' ), 'rejected' ),
        'more'     => array( array( 'pending' ), 'more' ),
        'withdraw' => array( array( 'approved' ), 'withdrawn' ),
    );
    return isset( $map[ $do ] ) && in_array( (string) $status, $map[ $do ][0], true ) ? $map[ $do ][1] : '';
}

/** Whether a decision needs a note (the reason or the request goes to the submitter). */
function prospergenics_scrutiny_decision_needs_note( $do ) {
    return in_array( $do, array( 'reject', 'more', 'withdraw' ), true );
}

/**
 * Why a user may not post the response to an item ('' when they may). Pure.
 *
 * @param array $f logged_in, verified, suspended, concerned (the person the item is about, or a
 *                 contributor of the project), approved (the item is public), responded (it has one)
 */
function prospergenics_scrutiny_response_block( array $f ) {
    if ( empty( $f['logged_in'] ) ) {
        return 'login';
    }
    if ( empty( $f['concerned'] ) ) {
        return 'denied';
    }
    if ( empty( $f['verified'] ) || ! empty( $f['suspended'] ) ) {
        return 'unverified';
    }
    if ( empty( $f['approved'] ) ) {
        return 'not_found';
    }
    if ( ! empty( $f['responded'] ) ) {
        return 'already';
    }
    return '';
}

/**
 * The public view of an item: a whitelist. Whatever the record holds (status, email, IP hash, the
 * audit log, the member id), only these fields come out.
 *
 * @param array $r a record from prospergenics_scrutiny_record(), plus member_url when the
 *                 submitter's profile may be linked
 * @return array{id:int,kind:string,kind_label:string,opinion:bool,body:string,sources:string[],name:string,member:bool,member_url:string,disclosure:string,date:string,response:array|null}
 */
function prospergenics_scrutiny_public_item( array $r ) {
    $kinds = prospergenics_scrutiny_kinds();
    $disc  = prospergenics_scrutiny_disclosures();
    $kind  = isset( $r['kind'], $kinds[ $r['kind'] ] ) ? $r['kind'] : 'evidence';
    $dkey  = isset( $r['disclosure'], $disc[ $r['disclosure'] ] ) ? $r['disclosure'] : 'none';
    $dlab  = $disc[ $dkey ][1];
    if ( 'other' === $dkey && ! empty( $r['disclosure_text'] ) ) {
        $dlab .= ': ' . (string) $r['disclosure_text'];
    }
    $resp = null;
    if ( ! empty( $r['response'] ) && is_array( $r['response'] ) && '' !== (string) $r['response']['body'] ) {
        $resp = array(
            'name' => isset( $r['response']['name'] ) ? (string) $r['response']['name'] : '',
            'body' => (string) $r['response']['body'],
            'date' => isset( $r['response']['at'] ) ? gmdate( 'Y-m-d', (int) $r['response']['at'] ) : '',
        );
    }
    return array(
        'id'         => (int) $r['id'],
        'kind'       => $kind,
        'kind_label' => $kinds[ $kind ][0],
        'opinion'    => 'review' === $kind,
        'body'       => (string) $r['body'],
        'sources'    => array_values( array_filter( (array) ( isset( $r['sources'] ) ? $r['sources'] : array() ), 'is_string' ) ),
        'name'       => '' !== (string) ( isset( $r['name'] ) ? $r['name'] : '' ) ? (string) $r['name'] : 'A former contributor',
        'member'     => ! empty( $r['member_id'] ),
        'member_url' => isset( $r['member_url'] ) ? (string) $r['member_url'] : '',
        'disclosure' => $dlab,
        'date'       => ! empty( $r['published_at'] ) ? gmdate( 'Y-m-d', (int) $r['published_at'] ) : '',
        'response'   => $resp,
    );
}

/** One audit log entry. */
function prospergenics_scrutiny_audit_entry( $event, $by, $source, array $data = array(), $now = null ) {
    return array(
        'at'     => null === $now ? time() : (int) $now,
        'event'  => (string) $event,
        'by'     => (int) $by,
        'source' => (string) $source,
        'data'   => $data,
    );
}

/* ───────────────────────── storage ───────────────────────── */

add_action( 'init', function () {
    register_post_type( PROSPERGENICS_SCRUTINY_POST_TYPE, array(
        'label'               => 'Scrutiny items',
        'public'              => false,
        'show_ui'             => false,
        'show_in_nav_menus'   => false,
        'publicly_queryable'  => false,
        'exclude_from_search' => true,
        'has_archive'         => false,
        'rewrite'             => false,
        'query_var'           => false,
        'show_in_rest'        => false,
        'can_export'          => false,
        'supports'            => array( 'author' ),
        'capabilities'        => array( 'create_posts' => 'do_not_allow' ),
        'map_meta_cap'        => true,
    ) );
} );

/** Read one meta value of an item. */
function prospergenics_scrutiny_meta( $id, $key ) {
    return get_post_meta( (int) $id, '_pg_scrutiny_' . $key, true );
}

/**
 * An item as an internal record (no audit fields), or null.
 *
 * @return array|null
 */
function prospergenics_scrutiny_record( $post ) {
    $post = get_post( $post );
    if ( ! $post || PROSPERGENICS_SCRUTINY_POST_TYPE !== $post->post_type ) {
        return null;
    }
    $sources  = prospergenics_scrutiny_meta( $post->ID, 'sources' );
    $response = prospergenics_scrutiny_meta( $post->ID, 'response' );
    if ( is_array( $response ) && ! empty( $response['user_id'] ) ) {
        $u                = get_userdata( (int) $response['user_id'] );
        $response['name'] = $u ? $u->display_name : '';
    }
    return array(
        'id'              => (int) $post->ID,
        'kind'            => (string) prospergenics_scrutiny_meta( $post->ID, 'kind' ),
        'target'          => (string) prospergenics_scrutiny_meta( $post->ID, 'target' ),
        'target_type'     => (string) prospergenics_scrutiny_meta( $post->ID, 'target_type' ),
        'target_id'       => (int) prospergenics_scrutiny_meta( $post->ID, 'target_id' ),
        'body'            => (string) $post->post_content,
        'sources'         => is_array( $sources ) ? $sources : array(),
        'disclosure'      => (string) prospergenics_scrutiny_meta( $post->ID, 'disclosure' ),
        'disclosure_text' => (string) prospergenics_scrutiny_meta( $post->ID, 'disclosure_text' ),
        'name'            => (string) prospergenics_scrutiny_meta( $post->ID, 'name' ),
        'member_id'       => (int) $post->post_author,
        'status'          => (string) prospergenics_scrutiny_meta( $post->ID, 'status' ),
        'submitted'       => strtotime( $post->post_date_gmt . ' UTC' ),
        'published_at'    => (int) prospergenics_scrutiny_meta( $post->ID, 'published_at' ),
        'response'        => is_array( $response ) && ! empty( $response['body'] ) ? $response : null,
    );
}

/** Append to an item's audit log (staff-only). */
function prospergenics_scrutiny_audit( $id, $event, $by = 0, $source = 'wp', array $data = array() ) {
    $log   = get_post_meta( (int) $id, '_pg_scrutiny_audit', true );
    $log   = is_array( $log ) ? $log : array();
    $log[] = prospergenics_scrutiny_audit_entry( $event, $by, $source, $data );
    update_post_meta( (int) $id, '_pg_scrutiny_audit', array_slice( $log, -200 ) );
}

/** The audit part of an item, for staff screens only: email, IP hash, log. */
function prospergenics_scrutiny_audit_data( $id ) {
    $log = get_post_meta( (int) $id, '_pg_scrutiny_audit', true );
    return array(
        'email' => (string) get_post_meta( (int) $id, '_pg_scrutiny_audit_email', true ),
        'ip'    => (string) get_post_meta( (int) $id, '_pg_scrutiny_audit_ip', true ),
        'log'   => is_array( $log ) ? $log : array(),
    );
}

/** A keyed hash of the requester's IP address ('' without one): enough to see repeats, not to recover it. */
function prospergenics_scrutiny_ip_hash() {
    $ip = function_exists( 'prospergenics_client_ip' ) ? prospergenics_client_ip() : '';
    return '' === $ip ? '' : hash_hmac( 'sha256', $ip, wp_salt( 'auth' ) );
}

/** Keyed hash of a link token (only the hash is stored). */
function prospergenics_scrutiny_token_hash( $token ) {
    return hash_hmac( 'sha256', 'scrutiny|' . (string) $token, wp_salt( 'auth' ) );
}

/** Items about a target, newest published first. @return array[] records */
function prospergenics_scrutiny_items_for( $key, $statuses = array( 'approved' ), $limit = 100 ) {
    $posts = get_posts( array(
        'post_type'        => PROSPERGENICS_SCRUTINY_POST_TYPE,
        'post_status'      => 'publish',
        'posts_per_page'   => (int) $limit,
        'orderby'          => 'date',
        'order'            => 'DESC',
        'meta_query'       => array(
            array( 'key' => '_pg_scrutiny_target', 'value' => (string) $key ),
            array( 'key' => '_pg_scrutiny_status', 'value' => (array) $statuses, 'compare' => 'IN' ),
        ),
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) );
    $out = array_filter( array_map( 'prospergenics_scrutiny_record', $posts ) );
    usort( $out, function ( $a, $b ) {
        return array( $b['published_at'], $b['id'] ) <=> array( $a['published_at'], $a['id'] );
    } );
    return $out;
}

/** Items by status (the queue). @return array[] records, oldest first */
function prospergenics_scrutiny_items_by_status( $statuses, $limit = 200 ) {
    $posts = get_posts( array(
        'post_type'        => PROSPERGENICS_SCRUTINY_POST_TYPE,
        'post_status'      => 'publish',
        'posts_per_page'   => (int) $limit,
        'orderby'          => 'date',
        'order'            => 'ASC',
        'meta_query'       => array( array( 'key' => '_pg_scrutiny_status', 'value' => (array) $statuses, 'compare' => 'IN' ) ),
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) );
    return array_values( array_filter( array_map( 'prospergenics_scrutiny_record', $posts ) ) );
}

/** Number of items waiting for review (for the Reports menu badge and the tab). */
function prospergenics_scrutiny_pending_count() {
    $n = wp_cache_get( 'pending', 'pg_scrutiny' );
    if ( false === $n ) {
        $n = count( get_posts( array(
            'post_type' => PROSPERGENICS_SCRUTINY_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 999, 'fields' => 'ids',
            'meta_key' => '_pg_scrutiny_status', 'meta_value' => 'pending', 'suppress_filters' => true,
        ) ) );
        wp_cache_set( 'pending', $n, 'pg_scrutiny', 60 );
    }
    return (int) $n;
}

/* ───────────────────────── targets ───────────────────────── */

/** The profile section a profile entry type belongs to. */
function prospergenics_scrutiny_claim_section( $post_type ) {
    $map = array( 'pg_experience' => 'experience', 'pg_education' => 'education', 'pg_skill' => 'skills' );
    return isset( $map[ $post_type ] ) ? $map[ $post_type ] : '';
}

/** Whether a member's profile is hidden by staff or suspended (then nothing is shown about it). */
function prospergenics_scrutiny_member_closed( $user_id ) {
    return prospergenics_profiles_is_hidden( $user_id ) || ( function_exists( 'prospergenics_profiles_is_suspended' ) && prospergenics_profiles_is_suspended( $user_id ) );
}

/**
 * What a target is, or null when it does not exist.
 *
 * open:      it exists, is published, and is not hidden or suspended: items can be shown and added.
 * concerned: the members it is about (response rights): the person; the entry's owner; the
 *            project's contributors and its author.
 *
 * @return array{type:string,id:int,key:string,title:string,label:string,url:string,owner:int,concerned:int[],open:bool}|null
 */
function prospergenics_scrutiny_target( $type, $id ) {
    $id  = (int) $id;
    $key = prospergenics_scrutiny_key( $type, $id );
    if ( '' === $key ) {
        return null;
    }
    if ( 'person' === $type ) {
        $u = get_userdata( $id );
        if ( ! $u ) {
            return null;
        }
        return array(
            'type' => $type, 'id' => $id, 'key' => $key,
            'title'     => $u->display_name,
            'label'     => $u->display_name,
            'url'       => prospergenics_profiles_url( $id ),
            'owner'     => $id,
            'concerned' => array( $id ),
            'open'      => ! prospergenics_scrutiny_member_closed( $id ),
        );
    }
    if ( 'claim' === $type ) {
        $p = get_post( $id );
        if ( ! $p || '' === prospergenics_scrutiny_claim_section( $p->post_type ) ) {
            return null;
        }
        $owner = (int) $p->post_author;
        $u     = get_userdata( $owner );
        $what  = $p->post_title;
        if ( 'pg_experience' === $p->post_type ) {
            $company = (string) get_post_meta( $p->ID, '_pg_profiles_company', true );
            $what   .= '' !== $company ? ' at ' . $company : '';
        }
        return array(
            'type' => $type, 'id' => $id, 'key' => $key,
            'title'     => $what,
            'label'     => sprintf( '“%s” on the profile of %s', $what, $u ? $u->display_name : 'a member' ),
            'url'       => $u ? prospergenics_profiles_url( $owner ) . '#' . array( 'pg_experience' => 'pgp-exp-' . $id . '-view', 'pg_education' => 'pgp-edu-' . $id . '-view', 'pg_skill' => 'pgp-skills' )[ $p->post_type ] : '',
            'owner'     => $owner,
            'concerned' => $owner ? array( $owner ) : array(),
            'open'      => $u && 'publish' === $p->post_status && ! prospergenics_scrutiny_member_closed( $owner ),
        );
    }
    // project: pg_project from the projects track (card #6388), when it is there
    $p = post_type_exists( 'pg_project' ) ? get_post( $id ) : null;
    if ( ! $p || 'pg_project' !== $p->post_type ) {
        return null;
    }
    $concerned = array( (int) $p->post_author );
    if ( function_exists( 'prospergenics_projects_contributors' ) ) {
        foreach ( (array) prospergenics_projects_contributors( $id ) as $c ) {
            if ( is_array( $c ) && ! empty( $c['user_id'] ) ) {
                $concerned[] = (int) $c['user_id'];
            }
        }
    }
    $title = wp_strip_all_tags( get_the_title( $p ) );
    return array(
        'type' => $type, 'id' => $id, 'key' => $key,
        'title'     => $title,
        'label'     => sprintf( 'the project “%s”', $title ),
        'url'       => function_exists( 'prospergenics_projects_url' ) ? (string) prospergenics_projects_url( $id ) : (string) get_permalink( $p ),
        'owner'     => (int) $p->post_author,
        'concerned' => array_values( array_unique( array_filter( $concerned ) ) ),
        'open'      => 'publish' === $p->post_status && '' === (string) $p->post_password,
    );
}

/** The link to one item on its target's page. */
function prospergenics_scrutiny_item_url( array $t, $item_id ) {
    return preg_replace( '/#.*$/', '', $t['url'] ) . '#pgs-item-' . (int) $item_id;
}

/** Whether a viewer can see the target (you only add to, and see items about, what you can see). */
function prospergenics_scrutiny_target_visible( array $t, $viewer_id ) {
    if ( ! $t['open'] ) {
        return false;
    }
    if ( 'person' === $t['type'] ) {
        return ! empty( prospergenics_profiles_visibility_for( $t['id'], $viewer_id )['profile'] );
    }
    if ( 'claim' === $t['type'] ) {
        $vis     = prospergenics_profiles_visibility_for( $t['owner'], $viewer_id );
        $section = prospergenics_scrutiny_claim_section( get_post_type( $t['id'] ) );
        return ! empty( $vis['profile'] ) && ! empty( $vis[ $section ] );
    }
    return true; // a published project is public
}

/** Whether a target is open (exists, published, not hidden or suspended). For templates. */
function prospergenics_scrutiny_target_open( $type, $id ) {
    $t = prospergenics_scrutiny_target( $type, $id );
    return $t && $t['open'];
}

/* ───────────────────────── submitting ───────────────────────── */

/** Count a hit against a rate limit; false when over it. */
function prospergenics_scrutiny_rate_ok( $bucket, $limit, $window ) {
    $key  = 'pg_scrutiny_rate_' . md5( (string) $bucket );
    $rate = prospergenics_profiles_rate_hit( get_transient( $key ), time(), $limit, $window );
    set_transient( $key, $rate['state'], $window );
    return $rate['allowed'];
}

/** Why the user may not submit about a target ('' when they may). */
function prospergenics_scrutiny_block_for( $type, $id, $user_id, $check_rate = false ) {
    $t = prospergenics_scrutiny_target( $type, $id );
    $f = array(
        'open'      => $t && $t['open'],
        'visible'   => $t && prospergenics_scrutiny_target_visible( $t, $user_id ),
        'logged_in' => (bool) $user_id,
        'verified'  => $user_id && prospergenics_profiles_is_verified( $user_id ),
        'suspended' => $user_id && function_exists( 'prospergenics_profiles_is_suspended' ) && prospergenics_profiles_is_suspended( $user_id ),
        'own'       => $t && $user_id && in_array( $t['type'], array( 'person', 'claim' ), true ) && (int) $t['owner'] === (int) $user_id,
    );
    if ( $check_rate && '' === prospergenics_scrutiny_submit_block( $f ) ) {
        $f['rate_ok'] = $user_id
            ? prospergenics_scrutiny_rate_ok( 'u' . (int) $user_id, PROSPERGENICS_SCRUTINY_MEMBER_LIMIT, HOUR_IN_SECONDS )
            : prospergenics_scrutiny_rate_ok( 'ip' . prospergenics_scrutiny_ip_hash(), PROSPERGENICS_SCRUTINY_IP_LIMIT, HOUR_IN_SECONDS );
    }
    return prospergenics_scrutiny_submit_block( $f );
}

/**
 * Create an item. Members' items go to review at once; a visitor's waits for the email link.
 *
 * @param array $in the raw fields (kind, body, sources, disclosure, disclosure_text, name, email)
 * @return int|WP_Error item id; error codes: block reasons, 'invalid' (data: field errors), 'mail', 'error'
 */
function prospergenics_scrutiny_submit( $user_id, $type, $id, array $in ) {
    $user_id = (int) $user_id;
    $block   = prospergenics_scrutiny_block_for( $type, $id, $user_id );
    if ( '' !== $block ) {
        return new WP_Error( $block, 'Not allowed.', array( 'status' => 403 ) );
    }
    $v = prospergenics_scrutiny_validate( $in, (bool) $user_id );
    if ( $v['errors'] ) {
        return new WP_Error( 'invalid', 'Please check the form.', array( 'status' => 400, 'errors' => $v['errors'] ) );
    }
    $c = $v['clean'];
    // rate limits last, so a form with mistakes does not use them up
    $block = prospergenics_scrutiny_block_for( $type, $id, $user_id, true );
    if ( '' === $block && ! $user_id && ! prospergenics_scrutiny_rate_ok( 'e' . $c['email'], PROSPERGENICS_SCRUTINY_EMAIL_LIMIT, DAY_IN_SECONDS ) ) {
        $block = 'rate';
    }
    if ( '' !== $block ) {
        return new WP_Error( $block, 'Not allowed.', array( 'status' => 'rate' === $block ? 429 : 403 ) );
    }
    $t    = prospergenics_scrutiny_target( $type, $id );
    $user = $user_id ? get_userdata( $user_id ) : null;
    $item = wp_insert_post( array(
        'post_type'    => PROSPERGENICS_SCRUTINY_POST_TYPE,
        'post_status'  => 'publish',
        'post_author'  => $user_id,
        'post_title'   => prospergenics_scrutiny_kinds()[ $c['kind'] ][0] . ' about ' . $t['key'],
        'post_content' => $c['body'],
    ), true );
    if ( is_wp_error( $item ) || ! $item ) {
        return new WP_Error( 'error', 'Could not save.', array( 'status' => 500 ) );
    }
    $status = $user_id ? 'pending' : 'unconfirmed';
    $meta   = array(
        'kind'            => $c['kind'],
        'target'          => $t['key'],
        'target_type'     => $t['type'],
        'target_id'       => (int) $t['id'],
        'sources'         => $c['sources'],
        'disclosure'      => $c['disclosure'],
        'disclosure_text' => $c['disclosure_text'],
        'name'            => $user ? $user->display_name : $c['name'],
        'status'          => $status,
        'audit_email'     => $user ? strtolower( $user->user_email ) : $c['email'],
        'audit_ip'        => prospergenics_scrutiny_ip_hash(),
    );
    foreach ( $meta as $k => $val ) {
        update_post_meta( $item, '_pg_scrutiny_' . $k, $val );
    }
    prospergenics_scrutiny_audit( $item, 'submitted', $user_id, 'wp', array( 'member' => (bool) $user_id, 'target' => $t['key'], 'kind' => $c['kind'] ) );
    if ( $user_id ) {
        prospergenics_scrutiny_enter_review( $item );
        return (int) $item;
    }
    $sent = prospergenics_scrutiny_send_confirmation( $item );
    if ( is_wp_error( $sent ) ) {
        wp_delete_post( $item, true ); // nobody could ever confirm it
        return new WP_Error( 'mail', 'The confirmation email could not be sent.', array( 'status' => 503 ) );
    }
    return (int) $item;
}

/** Email a visitor the link that confirms their item. @return true|WP_Error */
function prospergenics_scrutiny_send_confirmation( $item_id ) {
    $r     = prospergenics_scrutiny_record( $item_id );
    $email = (string) get_post_meta( (int) $item_id, '_pg_scrutiny_audit_email', true );
    $t     = $r ? prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] ) : null;
    if ( ! $r || ! $t || '' === $email ) {
        return new WP_Error( 'error', 'Unknown item.' );
    }
    $token = wp_generate_password( 40, false, false );
    update_post_meta( $r['id'], '_pg_scrutiny_confirm_hash', prospergenics_scrutiny_token_hash( $token ) );
    update_post_meta( $r['id'], '_pg_scrutiny_confirm_expires', time() + PROSPERGENICS_SCRUTINY_CONFIRM_TTL );
    $link = add_query_arg( array( 'action' => 'pg_scrutiny_confirm', 'i' => $r['id'], 't' => $token ), admin_url( 'admin-post.php' ) );
    $body = sprintf(
        "Hi %s,\n\nYou sent ProsperGenics %s about %s:\n\n%s\n\nPlease confirm your email address by opening this link, so the ProsperGenics team can review it:\n\n%s\n\nThe link works for 3 days. Nothing is public before the team has reviewed it. Your name, your connection and the date are shown with it once it is published; your email address never is.\n\nIf you did not send this, you can ignore this email: without the confirmation it is deleted.\n\nThe ProsperGenics team\n",
        $r['name'],
        strtolower( prospergenics_scrutiny_kinds()[ $r['kind'] ][0] ),
        $t['label'],
        prospergenics_scrutiny_excerpt( $r['body'] ),
        $link
    );
    $sent = prospergenics_profiles_send_mail( $email, 'Please confirm what you sent to ProsperGenics', $body, 'scrutiny_confirm', 0 );
    prospergenics_scrutiny_audit( $r['id'], 'mail_confirm', 0, 'wp', array( 'sent' => true === $sent ) );
    return $sent;
}

/**
 * Handle a confirmation link.
 *
 * @return string confirmed|already|expired|invalid
 */
function prospergenics_scrutiny_confirm( $item_id, $token ) {
    $r = prospergenics_scrutiny_record( $item_id );
    if ( ! $r || $r['member_id'] ) {
        return 'invalid';
    }
    if ( 'unconfirmed' !== $r['status'] ) {
        return 'already';
    }
    $hash    = (string) get_post_meta( $r['id'], '_pg_scrutiny_confirm_hash', true );
    $expires = (int) get_post_meta( $r['id'], '_pg_scrutiny_confirm_expires', true );
    if ( '' !== $hash && $expires < time() && hash_equals( $hash, prospergenics_scrutiny_token_hash( $token ) ) ) {
        return 'expired';
    }
    if ( ! prospergenics_profiles_token_valid( $token, $hash, $expires, time(), 'prospergenics_scrutiny_token_hash' ) ) {
        return 'invalid';
    }
    delete_post_meta( $r['id'], '_pg_scrutiny_confirm_hash' );
    delete_post_meta( $r['id'], '_pg_scrutiny_confirm_expires' );
    update_post_meta( $r['id'], '_pg_scrutiny_status', 'pending' );
    prospergenics_scrutiny_audit( $r['id'], 'confirmed', 0, 'wp', array( 'email_verified' => true ) );
    prospergenics_scrutiny_enter_review( $r['id'] );
    return 'confirmed';
}

/**
 * An item enters human review: it is in the Scrutiny tab of wp-admin → Reports from now on.
 */
function prospergenics_scrutiny_enter_review( $item_id ) {
    wp_cache_delete( 'pending', 'pg_scrutiny' );
    $r = prospergenics_scrutiny_record( $item_id );
    if ( ! $r ) {
        return;
    }
    prospergenics_scrutiny_audit( $r['id'], 'review_requested', 0, 'wp' );
    /**
     * Seam for Django Work human review (card #6389).
     *
     * Fires once each time an item enters human review: a member's submission, a visitor's after
     * they confirmed their email address, and an item whose submitter added the evidence staff
     * asked for. Today nothing listens and the ProsperGenics team reviews the item in
     * wp-admin → Reports → Scrutiny. Django Work is not reachable from WordPress yet; when it is,
     * its connector hooks in here to open a review task (with this payload: the item id and its
     * public fields; never the submitter's email address or IP hash), and sends the verdict back
     * through prospergenics_scrutiny_decide( $item_id, 'approve'|'reject'|'more', 0, $note,
     * 'django-work' ), which keeps the same audit trail and mail as a decision taken in wp-admin.
     *
     * @param int   $item_id the pg_scrutiny post
     * @param array $payload target (key, type, id, url), kind, body, sources, disclosure, name, member
     */
    do_action( 'prospergenics_scrutiny_review_requested', (int) $r['id'], array(
        'target'     => array( 'key' => $r['target'], 'type' => $r['target_type'], 'id' => $r['target_id'] ),
        'kind'       => $r['kind'],
        'body'       => $r['body'],
        'sources'    => $r['sources'],
        'disclosure' => array( 'kind' => $r['disclosure'], 'text' => $r['disclosure_text'] ),
        'name'       => $r['name'],
        'member'     => (bool) $r['member_id'],
    ) );
}

/** A short plain-text excerpt for an email or a staff screen. */
function prospergenics_scrutiny_excerpt( $body, $max = 600 ) {
    $plain = prospergenics_scrutiny_plain( $body );
    return mb_strlen( $plain ) > $max ? rtrim( mb_substr( $plain, 0, $max - 1 ) ) . '…' : $plain;
}

/* ───────────────────────── review decisions ───────────────────────── */

/**
 * Decide on an item: approve (publish), reject, ask for more evidence, or withdraw a published
 * item. The caller checks who may (pg_moderate in wp-admin; the Django Work connector later).
 *
 * @param string $edited_body when not null and different: staff edit the text before publishing
 * @return string|WP_Error the new status
 */
function prospergenics_scrutiny_decide( $item_id, $do, $by, $note = '', $source = 'wp', $edited_body = null ) {
    $r = prospergenics_scrutiny_record( $item_id );
    if ( ! $r ) {
        return new WP_Error( 'gone', 'The item no longer exists.' );
    }
    $next = prospergenics_scrutiny_next_status( $r['status'], $do );
    if ( '' === $next ) {
        return new WP_Error( 'state', 'That is not possible for this item now.' );
    }
    $note = trim( sanitize_textarea_field( (string) $note ) );
    if ( prospergenics_scrutiny_decision_needs_note( $do ) && '' === $note ) {
        return new WP_Error( 'note', 'Please give a reason.' );
    }
    if ( null !== $edited_body && 'approve' === $do ) {
        $clean = prospergenics_scrutiny_clean_text( $edited_body );
        if ( '' !== $clean['error'] ) {
            return new WP_Error( 'edit', 'The edited text is too short or too long.' );
        }
        if ( $clean['body'] !== $r['body'] ) {
            wp_update_post( array( 'ID' => $r['id'], 'post_content' => $clean['body'] ) );
            prospergenics_scrutiny_audit( $r['id'], 'edited', $by, $source, array( 'before' => $r['body'], 'after' => $clean['body'] ) );
            $r['body'] = $clean['body'];
        }
    }
    update_post_meta( $r['id'], '_pg_scrutiny_status', $next );
    if ( 'approved' === $next ) {
        update_post_meta( $r['id'], '_pg_scrutiny_published_at', time() );
    }
    if ( '' !== $note ) {
        update_post_meta( $r['id'], '_pg_scrutiny_note', $note );
    }
    prospergenics_scrutiny_audit( $r['id'], $do, $by, $source, array( 'from' => $r['status'], 'to' => $next, 'note' => $note ) );
    wp_cache_delete( 'pending', 'pg_scrutiny' );

    $r = prospergenics_scrutiny_record( $r['id'] );
    if ( 'more' === $next ) {
        prospergenics_scrutiny_mail_submitter( $r, 'more', $note );
    } elseif ( 'approved' === $next ) {
        prospergenics_scrutiny_mail_submitter( $r, 'approved', '' );
        prospergenics_scrutiny_notify_concerned( $r );
    } elseif ( 'rejected' === $next ) {
        prospergenics_scrutiny_mail_submitter( $r, 'rejected', $note );
    }
    do_action( 'prospergenics_scrutiny_decided', (int) $r['id'], $next, (int) $by, (string) $source );
    return $next;
}

/** The submitter's address and name: the account's for a member, the confirmed one for a visitor. */
function prospergenics_scrutiny_submitter( array $r ) {
    $u = $r['member_id'] ? get_userdata( $r['member_id'] ) : null;
    if ( $u ) {
        return array( 'email' => $u->user_email, 'name' => $u->display_name, 'user_id' => (int) $u->ID );
    }
    return array( 'email' => (string) get_post_meta( $r['id'], '_pg_scrutiny_audit_email', true ), 'name' => $r['name'], 'user_id' => 0 );
}

/** Tell the submitter what happened to their item. */
function prospergenics_scrutiny_mail_submitter( array $r, $what, $note ) {
    $s = prospergenics_scrutiny_submitter( $r );
    $t = prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] );
    if ( '' === $s['email'] || ! $t ) {
        return;
    }
    $kind = strtolower( prospergenics_scrutiny_kinds()[ $r['kind'] ][0] );
    if ( 'approved' === $what ) {
        $subject = 'What you sent is published on ProsperGenics';
        $text    = sprintf( "The ProsperGenics team reviewed your %s about %s and published it:\n\n%s\n\nThe person concerned is told and may post one public response.", $kind, $t['label'], prospergenics_scrutiny_item_url( $t, $r['id'] ) );
    } elseif ( 'rejected' === $what ) {
        $subject = 'About what you sent to ProsperGenics';
        $text    = sprintf( "The ProsperGenics team reviewed your %s about %s and will not publish it.\n\nThe reason:\n%s", $kind, $t['label'], $note );
    } else {
        $token = wp_generate_password( 40, false, false );
        update_post_meta( $r['id'], '_pg_scrutiny_more_hash', prospergenics_scrutiny_token_hash( $token ) );
        update_post_meta( $r['id'], '_pg_scrutiny_more_expires', time() + PROSPERGENICS_SCRUTINY_MORE_TTL );
        $link    = add_query_arg( array( 'pgs_more' => $r['id'], 'pgs_t' => $token ), remove_query_arg( 'pgs', preg_replace( '/#.*$/', '', $t['url'] ) ) ) . '#pgs-more';
        $subject = 'The ProsperGenics team asks for more evidence';
        $text    = sprintf( "The ProsperGenics team is reviewing your %s about %s and asks for more evidence before it can be published:\n\n%s\n\nAdd it here (the link works for 14 days and only for you):\n%s", $kind, $t['label'], $note, $link );
    }
    $body = sprintf( "Hi %s,\n\n%s\n\nWhat you sent:\n%s\n\nThe ProsperGenics team\n", $s['name'], $text, prospergenics_scrutiny_excerpt( $r['body'], 400 ) );
    $sent = prospergenics_profiles_send_mail( $s['email'], $subject, $body, 'scrutiny_' . $what, $s['user_id'] );
    prospergenics_scrutiny_audit( $r['id'], 'mail_submitter_' . $what, 0, 'wp', array( 'sent' => true === $sent ) );
}

/** Response rights: email the people an item is about once it is public. */
function prospergenics_scrutiny_notify_concerned( array $r ) {
    $t = prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] );
    if ( ! $t ) {
        return;
    }
    $kind = strtolower( prospergenics_scrutiny_kinds()[ $r['kind'] ][0] );
    $pub  = prospergenics_scrutiny_public_item( $r );
    foreach ( $t['concerned'] as $uid ) {
        $u = get_userdata( (int) $uid );
        if ( ! $u || (int) $u->ID === (int) $r['member_id'] ) {
            continue;
        }
        $body = sprintf(
            "Hi %s,\n\n%s posted %s about %s on ProsperGenics, and the ProsperGenics team reviewed and published it:\n\n%s\n\n%s\n\nRead it here: %s\n\nYou have the right to respond: you can post one public response, shown right under it. Log in, open the link and use “Respond”. If you think it breaks the community guidelines, write to us via %s.\n\nThe ProsperGenics team\n",
            $u->display_name,
            $pub['name'],
            'review' === $r['kind'] ? 'a review (an opinion)' : 'a ' . $kind,
            'person' === $t['type'] ? 'you' : $t['label'],
            prospergenics_scrutiny_excerpt( $r['body'], 800 ),
            'Their connection: ' . $pub['disclosure'],
            prospergenics_scrutiny_item_url( $t, $r['id'] ),
            home_url( '/contact/' )
        );
        $sent = prospergenics_profiles_send_mail( $u->user_email, 'Something about you was published on ProsperGenics', $body, 'scrutiny_concerned', $u->ID );
        prospergenics_scrutiny_audit( $r['id'], 'mail_concerned', 0, 'wp', array( 'user' => (int) $u->ID, 'sent' => true === $sent ) );
    }
}

/* ───────────────────────── more evidence ───────────────────────── */

/** Whether a "more evidence" link is valid for an item. */
function prospergenics_scrutiny_more_token_ok( $item_id, $token ) {
    $r = prospergenics_scrutiny_record( $item_id );
    if ( ! $r || 'more' !== $r['status'] ) {
        return false;
    }
    return prospergenics_profiles_token_valid(
        $token,
        (string) get_post_meta( $r['id'], '_pg_scrutiny_more_hash', true ),
        (int) get_post_meta( $r['id'], '_pg_scrutiny_more_expires', true ),
        time(),
        'prospergenics_scrutiny_token_hash'
    );
}

/**
 * The submitter adds what staff asked for: more text and/or sources. Back to review.
 *
 * @return true|WP_Error
 */
function prospergenics_scrutiny_add_more( $item_id, $token, $raw_text, $raw_sources ) {
    if ( ! prospergenics_scrutiny_more_token_ok( $item_id, $token ) ) {
        return new WP_Error( 'invalid', 'This link does not work any more.' );
    }
    $r     = prospergenics_scrutiny_record( $item_id );
    $text  = trim( (string) $raw_text );
    $added = '' === $text ? array( 'body' => '', 'error' => '' ) : prospergenics_scrutiny_clean_text( $text, 2, PROSPERGENICS_SCRUTINY_MAX_BODY );
    $src   = prospergenics_scrutiny_clean_sources( array_merge( $r['sources'], preg_split( '/[\r\n]+/', (string) $raw_sources ) ) );
    if ( '' !== $added['error'] ) {
        return new WP_Error( $added['error'], 'Invalid length.' );
    }
    if ( '' !== $src['error'] ) {
        return new WP_Error( 'sources_' . $src['error'], 'Invalid sources.' );
    }
    if ( '' === $added['body'] && $src['urls'] === $r['sources'] ) {
        return new WP_Error( 'empty', 'Nothing added.' );
    }
    $body = $r['body'];
    if ( '' !== $added['body'] ) {
        $body = $r['body'] . "\n\n" . $added['body'];
        if ( mb_strlen( prospergenics_scrutiny_plain( $body ) ) > PROSPERGENICS_SCRUTINY_MAX_BODY ) {
            return new WP_Error( 'long', 'Too long.' );
        }
        wp_update_post( array( 'ID' => $r['id'], 'post_content' => $body ) );
    }
    update_post_meta( $r['id'], '_pg_scrutiny_sources', $src['urls'] );
    update_post_meta( $r['id'], '_pg_scrutiny_status', 'pending' );
    delete_post_meta( $r['id'], '_pg_scrutiny_more_hash' );
    delete_post_meta( $r['id'], '_pg_scrutiny_more_expires' );
    prospergenics_scrutiny_audit( $r['id'], 'more_supplied', (int) $r['member_id'], 'wp', array(
        'before' => $r['body'], 'after' => $body, 'sources_before' => $r['sources'], 'sources_after' => $src['urls'],
    ) );
    prospergenics_scrutiny_enter_review( $r['id'] );
    return true;
}

/* ───────────────────────── responses ───────────────────────── */

/** Whether a user is one of the people an item is about. */
function prospergenics_scrutiny_is_concerned( array $r, $user_id ) {
    $t = prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] );
    return $t && $user_id && in_array( (int) $user_id, $t['concerned'], true );
}

/** Why a user may not respond to an item ('' when they may). */
function prospergenics_scrutiny_response_block_for( $item_id, $user_id ) {
    $r = prospergenics_scrutiny_record( $item_id );
    $t = $r ? prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] ) : null;
    return prospergenics_scrutiny_response_block( array(
        'logged_in' => (bool) $user_id,
        'concerned' => $r && prospergenics_scrutiny_is_concerned( $r, $user_id ),
        'verified'  => $user_id && prospergenics_profiles_is_verified( $user_id ),
        'suspended' => $user_id && function_exists( 'prospergenics_profiles_is_suspended' ) && prospergenics_profiles_is_suspended( $user_id ),
        'approved'  => $r && 'approved' === $r['status'] && $t && $t['open'],
        'responded' => $r && null !== $r['response'],
    ) );
}

/**
 * The person concerned posts their one public response.
 *
 * @return true|WP_Error
 */
function prospergenics_scrutiny_respond( $item_id, $user_id, $raw ) {
    $block = prospergenics_scrutiny_response_block_for( $item_id, $user_id );
    if ( '' !== $block ) {
        return new WP_Error( $block, 'Not allowed.', array( 'status' => 403 ) );
    }
    $clean = prospergenics_scrutiny_clean_text( $raw, PROSPERGENICS_SCRUTINY_MIN_RESPONSE, PROSPERGENICS_SCRUTINY_MAX_RESPONSE );
    if ( '' !== $clean['error'] ) {
        return new WP_Error( 'response_' . $clean['error'], 'Invalid length.', array( 'status' => 400 ) );
    }
    if ( ! prospergenics_scrutiny_rate_ok( 'r' . (int) $user_id, PROSPERGENICS_SCRUTINY_MEMBER_LIMIT, HOUR_IN_SECONDS ) ) {
        return new WP_Error( 'rate', 'Too many.', array( 'status' => 429 ) );
    }
    update_post_meta( (int) $item_id, '_pg_scrutiny_response', array( 'user_id' => (int) $user_id, 'body' => $clean['body'], 'at' => time() ) );
    prospergenics_scrutiny_audit( $item_id, 'response_posted', $user_id, 'wp', array( 'body' => $clean['body'] ) );
    return true;
}

/** Staff take a response down (it breaks the guidelines). The person may then respond again. */
function prospergenics_scrutiny_remove_response( $item_id, $by, $note ) {
    $r = prospergenics_scrutiny_record( $item_id );
    if ( ! $r || ! $r['response'] ) {
        return new WP_Error( 'state', 'There is no response.' );
    }
    $note = trim( sanitize_textarea_field( (string) $note ) );
    if ( '' === $note ) {
        return new WP_Error( 'note', 'Please give a reason.' );
    }
    delete_post_meta( $r['id'], '_pg_scrutiny_response' );
    prospergenics_scrutiny_audit( $r['id'], 'response_removed', $by, 'wp', array( 'body' => $r['response']['body'], 'user' => (int) $r['response']['user_id'], 'note' => $note ) );
    return 'response_removed';
}

/* ───────────────────────── housekeeping ───────────────────────── */

/** Delete visitors' items that were never confirmed. @return int deleted */
function prospergenics_scrutiny_cleanup_unconfirmed() {
    $ids = get_posts( array(
        'post_type' => PROSPERGENICS_SCRUTINY_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => 200, 'fields' => 'ids',
        'meta_key' => '_pg_scrutiny_status', 'meta_value' => 'unconfirmed', 'suppress_filters' => true,
        'date_query' => array( array( 'before' => gmdate( 'Y-m-d H:i:s', time() - PROSPERGENICS_SCRUTINY_CONFIRM_TTL ), 'column' => 'post_date_gmt' ) ),
    ) );
    $n = 0;
    foreach ( $ids as $id ) {
        $n += wp_delete_post( (int) $id, true ) ? 1 : 0;
    }
    return $n;
}
add_action( 'prospergenics_scrutiny_cleanup', 'prospergenics_scrutiny_cleanup_unconfirmed' );
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'prospergenics_scrutiny_cleanup' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 'prospergenics_scrutiny_cleanup' );
    }
} );
add_action( 'switch_theme', function () {
    wp_clear_scheduled_hook( 'prospergenics_scrutiny_cleanup' );
} );

/** Delete every item about a target (it is gone). */
function prospergenics_scrutiny_delete_about( $key ) {
    $ids = get_posts( array(
        'post_type' => PROSPERGENICS_SCRUTINY_POST_TYPE, 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids',
        'meta_key' => '_pg_scrutiny_target', 'meta_value' => (string) $key, 'suppress_filters' => true,
    ) );
    foreach ( $ids as $id ) {
        wp_delete_post( (int) $id, true );
    }
    wp_cache_delete( 'pending', 'pg_scrutiny' );
    return count( $ids );
}

// A deleted profile entry or project takes the items about it along.
add_action( 'before_delete_post', function ( $post_id ) {
    $type = get_post_type( $post_id );
    if ( '' !== prospergenics_scrutiny_claim_section( (string) $type ) ) {
        prospergenics_scrutiny_delete_about( 'claim:' . (int) $post_id );
    } elseif ( 'pg_project' === $type ) {
        prospergenics_scrutiny_delete_about( 'project:' . (int) $post_id );
    }
} );

/* ───────────────────────── personal data ───────────────────────── */

/** Items a person submitted: as a member (post_author) or as a visitor (by the confirmed email). @return WP_Post[] */
function prospergenics_scrutiny_items_by( $user_id, $email ) {
    $by_user  = $user_id ? get_posts( array(
        'post_type' => PROSPERGENICS_SCRUTINY_POST_TYPE, 'post_status' => 'any', 'author' => (int) $user_id,
        'posts_per_page' => -1, 'suppress_filters' => true, 'no_found_rows' => true,
    ) ) : array();
    $by_email = '' !== (string) $email ? get_posts( array(
        'post_type' => PROSPERGENICS_SCRUTINY_POST_TYPE, 'post_status' => 'any', 'meta_key' => '_pg_scrutiny_audit_email',
        'meta_value' => strtolower( (string) $email ), 'posts_per_page' => -1, 'suppress_filters' => true, 'no_found_rows' => true,
    ) ) : array();
    $out = array();
    foreach ( array_merge( $by_user, $by_email ) as $p ) {
        $out[ $p->ID ] = $p;
    }
    return array_values( $out );
}

/** Items carrying a response by this member. @return int[] */
function prospergenics_scrutiny_responses_by( $user_id ) {
    global $wpdb;
    return array_map( 'intval', $wpdb->get_col( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
        '_pg_scrutiny_response',
        '%' . $wpdb->esc_like( 's:7:"user_id";i:' . (int) $user_id . ';' ) . '%'
    ) ) );
}

function prospergenics_scrutiny_exporter( $email, $page = 1 ) {
    $user     = get_user_by( 'email', $email );
    $items    = array();
    $kinds    = prospergenics_scrutiny_kinds();
    $statuses = prospergenics_scrutiny_statuses();
    $disc     = prospergenics_scrutiny_disclosures();
    foreach ( prospergenics_scrutiny_items_by( $user ? $user->ID : 0, $email ) as $p ) {
        $r = prospergenics_scrutiny_record( $p );
        $t = prospergenics_scrutiny_target( $r['target_type'], $r['target_id'] );
        $data = array(
            array( 'name' => 'Kind', 'value' => isset( $kinds[ $r['kind'] ] ) ? $kinds[ $r['kind'] ][0] : $r['kind'] ),
            array( 'name' => 'About', 'value' => $t ? $t['label'] . ( '' !== $t['url'] ? ' (' . $t['url'] . ')' : '' ) : '(deleted)' ),
            array( 'name' => 'Text', 'value' => prospergenics_scrutiny_plain( $r['body'] ) ),
            array( 'name' => 'Sources', 'value' => implode( "\n", $r['sources'] ) ),
            array( 'name' => 'Your connection', 'value' => ( isset( $disc[ $r['disclosure'] ] ) ? $disc[ $r['disclosure'] ][0] : '' ) . ( '' !== $r['disclosure_text'] ? ': ' . $r['disclosure_text'] : '' ) ),
            array( 'name' => 'Shown as', 'value' => $r['name'] ),
            array( 'name' => 'Email address', 'value' => (string) get_post_meta( $r['id'], '_pg_scrutiny_audit_email', true ) ),
            array( 'name' => 'Sent', 'value' => $p->post_date_gmt . ' UTC' ),
            array( 'name' => 'Status', 'value' => isset( $statuses[ $r['status'] ] ) ? $statuses[ $r['status'] ] : $r['status'] ),
        );
        $items[] = array(
            'group_id'          => 'prospergenics-scrutiny',
            'group_label'       => 'Evidence, corrections, questions, reviews and disputes you sent',
            'group_description' => 'What you sent about people, projects and profile entries. Your IP address is kept only as a one-way hash.',
            'item_id'           => 'pg-scrutiny-' . (int) $r['id'],
            'data'              => $data,
        );
    }
    foreach ( $user ? prospergenics_scrutiny_responses_by( $user->ID ) : array() as $id ) {
        $r = prospergenics_scrutiny_record( $id );
        if ( ! $r || ! $r['response'] ) {
            continue;
        }
        $items[] = array(
            'group_id'    => 'prospergenics-scrutiny-responses',
            'group_label' => 'Your public responses',
            'item_id'     => 'pg-scrutiny-response-' . (int) $r['id'],
            'data'        => array(
                array( 'name' => 'Response to', 'value' => prospergenics_scrutiny_excerpt( $r['body'], 200 ) ),
                array( 'name' => 'Your response', 'value' => prospergenics_scrutiny_plain( $r['response']['body'] ) ),
                array( 'name' => 'Posted', 'value' => gmdate( 'Y-m-d H:i', (int) $r['response']['at'] ) . ' UTC' ),
            ),
        );
    }
    return array( 'data' => $items, 'done' => true );
}

/**
 * Erase what a person sent: a published item keeps its public text but loses everything that
 * points to the person (name, account, email, IP hash, the free-text connection, the audit's
 * personal details); everything else they sent is deleted, and so are their responses.
 *
 * @return array{removed:int,retained:int}
 */
function prospergenics_scrutiny_erase( $user_id, $email ) {
    $removed  = 0;
    $retained = 0;
    foreach ( prospergenics_scrutiny_items_by( $user_id, $email ) as $p ) {
        $status = (string) get_post_meta( $p->ID, '_pg_scrutiny_status', true );
        if ( 'approved' === $status ) {
            wp_update_post( array( 'ID' => $p->ID, 'post_author' => 0 ) );
            update_post_meta( $p->ID, '_pg_scrutiny_name', '' );
            update_post_meta( $p->ID, '_pg_scrutiny_disclosure_text', '' );
            foreach ( array( 'audit_email', 'audit_ip', 'confirm_hash', 'confirm_expires', 'more_hash', 'more_expires' ) as $k ) {
                delete_post_meta( $p->ID, '_pg_scrutiny_' . $k );
            }
            // the log keeps the decisions, not who the submitter was
            $log = get_post_meta( $p->ID, '_pg_scrutiny_audit', true );
            foreach ( is_array( $log ) ? $log : array() as $i => $e ) {
                if ( (int) $e['by'] === (int) $user_id && $user_id ) {
                    $log[ $i ]['by'] = 0;
                }
            }
            $log   = is_array( $log ) ? $log : array();
            $log[] = prospergenics_scrutiny_audit_entry( 'anonymised', 0, 'privacy' );
            update_post_meta( $p->ID, '_pg_scrutiny_audit', $log );
            $retained++;
        } elseif ( wp_delete_post( $p->ID, true ) ) {
            $removed++;
        }
    }
    foreach ( $user_id ? prospergenics_scrutiny_responses_by( $user_id ) : array() as $id ) {
        delete_post_meta( $id, '_pg_scrutiny_response' );
        prospergenics_scrutiny_audit( $id, 'response_erased', 0, 'privacy' );
        $removed++;
    }
    wp_cache_delete( 'pending', 'pg_scrutiny' );
    return array( 'removed' => $removed, 'retained' => $retained );
}

function prospergenics_scrutiny_eraser( $email, $page = 1 ) {
    $user = get_user_by( 'email', $email );
    $n    = prospergenics_scrutiny_erase( $user ? $user->ID : 0, strtolower( (string) $email ) );
    return array(
        'items_removed'  => ( $n['removed'] + $n['retained'] ) > 0,
        'items_retained' => false,
        'messages'       => $n['retained'] ? array( sprintf( '%d published item(s) were kept without name, account, email or IP hash ("a former contributor"), so the public record stays complete.', $n['retained'] ) ) : array(),
        'done'           => true,
    );
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
    $exporters['prospergenics-scrutiny'] = array( 'exporter_friendly_name' => 'ProsperGenics evidence, corrections and reviews', 'callback' => 'prospergenics_scrutiny_exporter' );
    return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
    $erasers['prospergenics-scrutiny'] = array( 'eraser_friendly_name' => 'ProsperGenics evidence, corrections and reviews', 'callback' => 'prospergenics_scrutiny_eraser' );
    return $erasers;
} );

// A deleted account: what they sent is erased (published text kept, anonymised), and what was
// said about them goes with them. Before core deletes or reassigns their posts (priority 5), and
// before the profiles track deletes their entries (which would delete the claim items anyway).
add_action( 'delete_user', function ( $user_id ) {
    $user = get_userdata( (int) $user_id );
    prospergenics_scrutiny_erase( (int) $user_id, $user ? strtolower( $user->user_email ) : '' );
    prospergenics_scrutiny_delete_about( 'person:' . (int) $user_id );
}, 4 );

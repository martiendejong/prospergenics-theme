<?php
/**
 * Projects track: pure rules (no database access), so they can be tested standalone.
 *
 * Kinds and statuses, validation of a project and of one member's contribution (evidence links are
 * https only), the contributions list and the role matrix: who may create, edit, confirm and delete.
 * data.php and forms.php call these with the facts they read from WordPress.
 */

defined( 'ABSPATH' ) || exit;

/** Kinds of portfolio entry, key => label (the contract's `_pg_project_kind`). */
function prospergenics_projects_kinds() {
    return array(
        'project'  => 'Project',
        'product'  => 'Product',
        'service'  => 'Service',
        'employer' => 'Employer',
    );
}

/** Contribution statuses, key => label (the contract's `status`). */
function prospergenics_projects_statuses() {
    return array(
        'claimed'   => 'Claimed by the member',
        'confirmed' => 'Confirmed by the project owner',
        'disputed'  => 'Disputed',
    );
}

const PROSPERGENICS_PROJECTS_MAX_EVIDENCE = 10;
const PROSPERGENICS_PROJECTS_MAX_SKILLS   = 10;

/** Trim a single-line text to $max characters. */
function prospergenics_projects_clip( $text, $max ) {
    $text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
    return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
}

/** Trim a multi-line text to $max characters (line breaks kept, control characters dropped). */
function prospergenics_projects_clip_text( $text, $max ) {
    $text = str_replace( array( "\r\n", "\r" ), "\n", (string) $text );
    $text = trim( preg_replace( '/[^\P{C}\n]+/u', '', $text ) );
    return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
}

/**
 * An https URL, or '' when the value is not one. Only https: evidence and project links are shown
 * to everyone, so no http, javascript:, data: or protocol-relative links, no spaces, no user:pass@.
 */
function prospergenics_projects_clean_https_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url || strlen( $url ) > 2000 || ! preg_match( '#^https://[^\s/?\#@]+(?:[/?\#][^\s]*)?$#i', $url ) ) {
        return '';
    }
    $host = parse_url( $url, PHP_URL_HOST );
    if ( ! is_string( $host ) || '' === $host || ! preg_match( '/^[a-z0-9.-]+$/i', $host ) || false === strpos( $host, '.' ) ) {
        return '';
    }
    return $url;
}

/**
 * Validate evidence rows: label + https URL each, at most 10. A row with a URL that is not https is
 * an error (it is not silently dropped); a row with neither is skipped.
 *
 * @param mixed $rows array of {label, url}
 * @return array{0:array<int,array{label:string,url:string}>,1:string} [ clean rows, error message or '' ]
 */
function prospergenics_projects_clean_evidence( $rows ) {
    $out = array();
    $err = '';
    foreach ( is_array( $rows ) ? $rows : array() as $row ) {
        if ( ! is_array( $row ) ) {
            continue;
        }
        $raw   = isset( $row['url'] ) && is_string( $row['url'] ) ? trim( $row['url'] ) : '';
        $label = prospergenics_projects_clip( isset( $row['label'] ) && is_string( $row['label'] ) ? $row['label'] : '', 120 );
        if ( '' === $raw ) {
            if ( '' !== $label ) {
                $err = 'Every evidence link needs a web address starting with https://.';
            }
            continue;
        }
        $url = prospergenics_projects_clean_https_url( $raw );
        if ( '' === $url ) {
            $err = 'Evidence links must be full web addresses starting with https://.';
            continue;
        }
        if ( count( $out ) >= PROSPERGENICS_PROJECTS_MAX_EVIDENCE ) {
            $err = sprintf( 'You can add at most %d evidence links.', PROSPERGENICS_PROJECTS_MAX_EVIDENCE );
            break;
        }
        if ( '' === $label ) {
            $label = (string) preg_replace( '/^www\./i', '', (string) parse_url( $url, PHP_URL_HOST ) );
        }
        $out[] = array( 'label' => $label, 'url' => $url );
    }
    return array( $out, $err );
}

/** Skills from a comma-separated string or an array: unique (case-insensitive), at most 10, 60 characters each. */
function prospergenics_projects_clean_skills( $skills ) {
    $list = is_array( $skills ) ? $skills : explode( ',', (string) $skills );
    $out  = array();
    $seen = array();
    foreach ( $list as $s ) {
        $s   = prospergenics_projects_clip( is_string( $s ) ? $s : '', 60 );
        $key = function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
        if ( '' === $s || isset( $seen[ $key ] ) ) {
            continue;
        }
        $seen[ $key ] = true;
        $out[]        = $s;
        if ( count( $out ) >= PROSPERGENICS_PROJECTS_MAX_SKILLS ) {
            break;
        }
    }
    return $out;
}

/** The lower-case key a skill is indexed and filtered by. */
function prospergenics_projects_skill_key( $skill ) {
    $s = prospergenics_projects_clip( $skill, 60 );
    return function_exists( 'mb_strtolower' ) ? mb_strtolower( $s ) : strtolower( $s );
}

/**
 * Validate a project. Input values must be strings (already unslashed).
 *
 * @return array{0:array,1:array} [ clean fields, errors field => message ]
 */
function prospergenics_projects_validate_project( array $in ) {
    $kinds = prospergenics_projects_kinds();
    $out   = array(
        'title'       => prospergenics_projects_clip( isset( $in['title'] ) ? $in['title'] : '', 120 ),
        'kind'        => isset( $in['kind'] ) && is_string( $in['kind'] ) && isset( $kinds[ $in['kind'] ] ) ? $in['kind'] : '',
        'url'         => '',
        'period'      => prospergenics_projects_clip( isset( $in['period'] ) ? $in['period'] : '', 40 ),
        'description' => prospergenics_projects_clip_text( isset( $in['description'] ) ? $in['description'] : '', 4000 ),
        'skills'      => prospergenics_projects_clean_skills( isset( $in['skills'] ) ? $in['skills'] : '' ),
    );
    $errors = array();
    if ( '' === $out['title'] ) {
        $errors['title'] = 'Enter a name.';
    }
    if ( '' === $out['kind'] ) {
        $errors['kind'] = 'Choose whether this is a project, product, service or employer.';
    }
    $raw_url = isset( $in['url'] ) && is_string( $in['url'] ) ? trim( $in['url'] ) : '';
    if ( '' !== $raw_url ) {
        $out['url'] = prospergenics_projects_clean_https_url( $raw_url );
        if ( '' === $out['url'] ) {
            $errors['url'] = 'The link must be a full web address starting with https://.';
        }
    }
    return array( $out, $errors );
}

/**
 * Validate one member's contribution (role, what they did, evidence).
 *
 * @return array{0:array,1:array}
 */
function prospergenics_projects_validate_contribution( array $in ) {
    list( $evidence, $evidence_error ) = prospergenics_projects_clean_evidence( isset( $in['evidence'] ) ? $in['evidence'] : array() );
    $out = array(
        'role'         => prospergenics_projects_clip( isset( $in['role'] ) ? $in['role'] : '', 120 ),
        'contribution' => prospergenics_projects_clip_text( isset( $in['contribution'] ) ? $in['contribution'] : '', 2000 ),
        'evidence'     => $evidence,
    );
    $errors = array();
    if ( '' === $out['role'] ) {
        $errors['role'] = 'Enter your role, e.g. "Lead installer" or "Co-founder".';
    }
    if ( '' === $out['contribution'] ) {
        $errors['contribution'] = 'Describe what you contributed.';
    }
    if ( '' !== $evidence_error ) {
        $errors['evidence'] = $evidence_error;
    }
    return array( $out, $errors );
}

/**
 * Evidence rows from the manual form's parallel arrays evidence_label[] and evidence_url[].
 *
 * @return array<int,array{label:string,url:string}>
 */
function prospergenics_projects_evidence_from_form( $labels, $urls ) {
    $labels = is_array( $labels ) ? array_values( $labels ) : array();
    $urls   = is_array( $urls ) ? array_values( $urls ) : array();
    $rows   = array();
    for ( $i = 0, $n = min( 20, max( count( $labels ), count( $urls ) ) ); $i < $n; $i++ ) {
        $rows[] = array(
            'label' => isset( $labels[ $i ] ) && is_string( $labels[ $i ] ) ? $labels[ $i ] : '',
            'url'   => isset( $urls[ $i ] ) && is_string( $urls[ $i ] ) ? $urls[ $i ] : '',
        );
    }
    return $rows;
}

/**
 * Normalise the stored contributions list: known statuses, positive user IDs, one entry per member.
 *
 * @param mixed $stored
 * @return array<int,array{user_id:int,role:string,contribution:string,evidence:array,status:string,added:int,confirmed_by:int}>
 */
function prospergenics_projects_normalize_contributions( $stored ) {
    $out      = array();
    $seen     = array();
    $statuses = prospergenics_projects_statuses();
    foreach ( is_array( $stored ) ? $stored : array() as $c ) {
        if ( ! is_array( $c ) || empty( $c['user_id'] ) || (int) $c['user_id'] < 1 || isset( $seen[ (int) $c['user_id'] ] ) ) {
            continue;
        }
        $seen[ (int) $c['user_id'] ] = true;
        list( $evidence ) = prospergenics_projects_clean_evidence( isset( $c['evidence'] ) ? $c['evidence'] : array() );
        $out[] = array(
            'user_id'      => (int) $c['user_id'],
            'role'         => prospergenics_projects_clip( isset( $c['role'] ) ? $c['role'] : '', 120 ),
            'contribution' => prospergenics_projects_clip_text( isset( $c['contribution'] ) ? $c['contribution'] : '', 2000 ),
            'evidence'     => $evidence,
            'status'       => isset( $c['status'] ) && is_string( $c['status'] ) && isset( $statuses[ $c['status'] ] ) ? $c['status'] : 'claimed',
            'added'        => isset( $c['added'] ) ? (int) $c['added'] : 0,
            'confirmed_by' => isset( $c['confirmed_by'] ) ? (int) $c['confirmed_by'] : 0,
        );
    }
    return $out;
}

/** Index of a member's contribution in the list, or -1. */
function prospergenics_projects_find_contribution( array $list, $user_id ) {
    foreach ( $list as $i => $c ) {
        if ( (int) $c['user_id'] === (int) $user_id ) {
            return $i;
        }
    }
    return -1;
}

/**
 * The status after a member edits their own contribution: a confirmation (or a dispute) was about
 * the old text, so a changed role, contribution or evidence goes back to "claimed".
 */
function prospergenics_projects_status_after_edit( array $old, array $new ) {
    $changed = $old['role'] !== $new['role'] || $old['contribution'] !== $new['contribution'] || $old['evidence'] !== $new['evidence'];
    return $changed ? 'claimed' : $old['status'];
}

/**
 * Whether a status change by the project owner or staff is allowed: confirm a claim or a dispute,
 * dispute a claim or a confirmation, or put it back to claimed.
 */
function prospergenics_projects_status_change_allowed( $from, $to ) {
    $statuses = prospergenics_projects_statuses();
    return isset( $statuses[ $from ], $statuses[ $to ] ) && $from !== $to;
}

/* ───────────────────────── the role matrix ───────────────────────── */

/**
 * Who may add to a portfolio (a new project, or a contribution to an existing one): a logged-in
 * member whose email address is confirmed and who is not suspended.
 *
 * @param array $f logged_in, verified, suspended => bool
 */
function prospergenics_projects_can_create( array $f ) {
    return ! empty( $f['logged_in'] ) && ! empty( $f['verified'] ) && empty( $f['suspended'] );
}

/** The project itself (name, kind, link, period, description, skills): its creator or staff. */
function prospergenics_projects_can_edit_project( $viewer_id, $creator_id, $viewer_is_staff ) {
    $viewer_id = (int) $viewer_id;
    return $viewer_id > 0 && ( $viewer_is_staff || $viewer_id === (int) $creator_id );
}

/**
 * A contribution's own text (role, contribution, evidence): only the member it belongs to. Staff
 * may also write it for an unclaimed placeholder profile (claim.php), never for a real member.
 */
function prospergenics_projects_can_edit_contribution( $viewer_id, $contributor_id, $viewer_is_staff = false, $contributor_is_placeholder = false ) {
    $viewer_id = (int) $viewer_id;
    if ( $viewer_id < 1 ) {
        return false;
    }
    return $viewer_id === (int) $contributor_id || ( $viewer_is_staff && $contributor_is_placeholder );
}

/** Removing a contribution from a project: the member themself, or staff (moderation). */
function prospergenics_projects_can_remove_contribution( $viewer_id, $contributor_id, $viewer_is_staff ) {
    $viewer_id = (int) $viewer_id;
    return $viewer_id > 0 && ( $viewer_is_staff || $viewer_id === (int) $contributor_id );
}

/**
 * Confirming (or disputing) someone's contribution: the project's creator or staff, and never
 * your own contribution (nobody confirms themselves).
 */
function prospergenics_projects_can_confirm( $viewer_id, $creator_id, $contributor_id, $viewer_is_staff ) {
    $viewer_id = (int) $viewer_id;
    return $viewer_id > 0 && $viewer_id !== (int) $contributor_id && ( $viewer_is_staff || $viewer_id === (int) $creator_id );
}

/**
 * Deleting a project: staff always; its creator only while nobody else has a contribution on it
 * (other members' portfolios are not theirs to delete).
 */
function prospergenics_projects_can_delete_project( $viewer_id, $creator_id, $viewer_is_staff, $other_contributors ) {
    $viewer_id = (int) $viewer_id;
    if ( $viewer_id < 1 ) {
        return false;
    }
    return $viewer_is_staff || ( $viewer_id === (int) $creator_id && 0 === (int) $other_contributors );
}

/**
 * Whether a project page may be indexed: published, not hidden by staff, its creator not hidden or
 * suspended, and at least one contribution that a logged-out visitor may see (the same rule as the
 * sitemap). Otherwise it is noindex and left out of the sitemap.
 */
function prospergenics_projects_indexable( $status, $hidden, $creator_hidden, $public_contributions ) {
    return 'publish' === $status && ! $hidden && ! $creator_hidden && (int) $public_contributions > 0;
}

/**
 * A new creator when the current one leaves (account deleted or data erased): the member with the
 * oldest confirmed contribution, else the oldest contribution; 0 when nobody else contributed.
 */
function prospergenics_projects_next_creator( array $contributions, $leaving_id ) {
    $best = null;
    foreach ( $contributions as $c ) {
        if ( (int) $c['user_id'] === (int) $leaving_id ) {
            continue;
        }
        $rank = array( 'confirmed' === $c['status'] ? 0 : 1, (int) $c['added'] );
        if ( null === $best || $rank < $best[0] ) {
            $best = array( $rank, (int) $c['user_id'] );
        }
    }
    return $best ? $best[1] : 0;
}

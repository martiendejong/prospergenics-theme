<?php
/**
 * Profiles track: member badges by ranking, the data side. The rules (weights, caps, tiers) are
 * in rank-rules.php; the markup and the /badges/ page in badges.php.
 *
 * A member's rank is computed from the evidence a logged-out visitor may see, and nothing else:
 *   - skills, experience and education: the entries of sections the member shows to everyone
 *     (prospergenics_profiles_visibility_for( $id, 0 )); endorsements count when the endorser
 *     is a verified member who is not suspended;
 *   - project contributions: prospergenics_projects_for_user( $id, 0 ), i.e. the "portfolio"
 *     section is public and the project is published and not hidden (projects track #6388);
 *     confirmed and claimed count, disputed never;
 *   - certificates: valid (not revoked) ones, when the "certificates" section is public;
 *   - courses taught and articles: published (articles not hidden by moderation, neither
 *     password-protected), which are public whatever the profile says;
 *   - approved scrutiny evidence (#6389) about the person (profile public) or about one of their
 *     entries (that section public), not written by the member themself.
 * A section the member keeps private never raises a public badge; the owner view lists what it
 * holds back ("withheld"). Unverified, hidden and suspended members get no badge (the evidence
 * is still counted, so the owner sees where they stand).
 *
 * Cache: user meta _pg_profiles_rank = {score, tier, breakdown, computed, rules, withheld, state}
 * and _pg_profiles_rank_tier = the tier key or 'none' (an index for the /manage/ filter). The
 * events that change evidence (the same list search uses to retire its cache, engine.php) queue
 * the members concerned; the queue is computed once at the end of the request. A rank older than
 * a day, or computed under other rules, is computed again when it is read, within a budget of a
 * few per request; lists prime their members' meta in one read (prospergenics_profiles_badges_prime())
 * and show the cached value for the rest, which an hourly cron event refreshes.
 */

defined( 'ABSPATH' ) || exit;

/** At most this many ranks are computed while a page renders; the rest wait for the queue. */
const PROSPERGENICS_PROFILES_RANK_BUDGET = 8;
/** At most this many queued ranks are computed at the end of a request; the rest go to cron. */
const PROSPERGENICS_PROFILES_RANK_QUEUE_MAX = 50;
/** Members the hourly cron event computes (missing ranks, after a rules change). */
const PROSPERGENICS_PROFILES_RANK_CRON_BATCH = 200;
/** Option holding the rules fingerprint the stored tiers were computed under. */
const PROSPERGENICS_PROFILES_RANK_RULES_OPTION = '_pg_profiles_rank_rules';

/* ───────────────────────── who gets a badge ───────────────────────── */

/**
 * Why a member shows no badge right now: 'missing', 'unverified', 'hidden', 'suspended', or ''
 * when they may have one.
 */
function prospergenics_profiles_rank_state( $user_id ) {
    $user_id = (int) $user_id;
    if ( $user_id < 1 || ! get_userdata( $user_id ) ) {
        return 'missing';
    }
    if ( function_exists( 'prospergenics_profiles_is_suspended' ) && prospergenics_profiles_is_suspended( $user_id ) ) {
        return 'suspended';
    }
    if ( prospergenics_profiles_is_hidden( $user_id ) ) {
        return 'hidden';
    }
    return prospergenics_profiles_is_verified( $user_id ) ? '' : 'unverified';
}

/* ───────────────────────── the evidence ───────────────────────── */

/**
 * A member's public evidence as items for prospergenics_profiles_rank_score(), and what their
 * non-public sections hold back.
 *
 * @return array{items:array,withheld:array<string,int>}
 */
function prospergenics_profiles_rank_evidence( $user_id ) {
    $uid      = (int) $user_id;
    $vis      = prospergenics_profiles_visibility_for( $uid, 0 );
    $items    = array();
    $withheld = array();
    $hold     = function ( $kind, $n ) use ( &$withheld ) {
        if ( $n > 0 ) {
            $withheld[ $kind ] = ( isset( $withheld[ $kind ] ) ? $withheld[ $kind ] : 0 ) + (int) $n;
        }
    };

    // skills, experience, education: one read (and their meta with it)
    $entries = get_posts( array(
        'post_type'        => PROSPERGENICS_PROFILES_ENTRY_TYPES,
        'post_status'      => 'publish',
        'author'           => $uid,
        'posts_per_page'   => 3 * PROSPERGENICS_PROFILES_MAX_ENTRIES,
        'orderby'          => 'ID',
        'order'            => 'ASC',
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) );
    $section   = array( 'pg_skill' => 'skills', 'pg_experience' => 'experience', 'pg_education' => 'education' );
    $public    = array(); // entry IDs in public sections (their scrutiny counts)
    $endorsers = array();
    foreach ( $entries as $p ) {
        if ( (int) $p->post_author !== $uid ) {
            continue;
        }
        $kind = substr( $p->post_type, 3 );
        if ( empty( $vis[ $section[ $p->post_type ] ] ) ) {
            $hold( $kind, 1 );
            continue;
        }
        $public[] = (int) $p->ID;
        $item     = array( 'kind' => $kind, 'id' => (int) $p->ID );
        if ( 'pg_skill' === $p->post_type ) {
            $item['endorsers'] = array_values( array_diff( array_unique( array_map( 'intval', get_post_meta( $p->ID, '_pg_profiles_endorser', false ) ) ), array( 0, $uid ) ) );
            $endorsers         = array_merge( $endorsers, $item['endorsers'] );
        }
        $items[] = $item;
    }
    // endorsements count when the endorser is a verified member who is not suspended (one read)
    if ( $endorsers ) {
        cache_users( array_values( array_unique( $endorsers ) ) );
    }
    foreach ( $items as &$item ) {
        if ( isset( $item['endorsers'] ) ) {
            $item['endorsements'] = count( array_filter( $item['endorsers'], function ( $id ) {
                return '' === prospergenics_profiles_rank_state( $id );
            } ) );
            unset( $item['endorsers'] );
        }
    }
    unset( $item );

    // project contributions (#6388): what a visitor sees of the portfolio; disputed never counts
    if ( function_exists( 'prospergenics_projects_for_user' ) ) {
        if ( ! empty( $vis['portfolio'] ) ) {
            foreach ( prospergenics_projects_for_user( $uid, 0 ) as $c ) {
                if ( in_array( $c['status'], array( 'confirmed', 'claimed' ), true ) ) {
                    $items[] = array( 'kind' => 'project_' . $c['status'], 'id' => (int) $c['project']->ID );
                }
            }
        } else {
            foreach ( prospergenics_projects_ids_for_member( $uid ) as $pid ) {
                $list = prospergenics_projects_contributions( $pid );
                $i    = prospergenics_projects_find_contribution( $list, $uid );
                if ( $i >= 0 && in_array( $list[ $i ]['status'], array( 'confirmed', 'claimed' ), true ) ) {
                    $hold( 'project_' . $list[ $i ]['status'], 1 );
                }
            }
        }
    }

    // certificates (ARC-20): valid ones, when the section is public
    $certs = prospergenics_profiles_certificates( $uid );
    if ( ! empty( $vis['certificates'] ) ) {
        foreach ( $certs as $c ) {
            $items[] = array( 'kind' => 'certificate' );
        }
    } else {
        $hold( 'certificate', count( $certs ) );
    }

    // courses taught and articles: published work is public whatever the profile says
    foreach ( array( 'pg_course' => 'course', 'pg_article' => 'article' ) as $type => $kind ) {
        if ( ! post_type_exists( $type ) ) {
            continue;
        }
        foreach ( get_posts( array( 'post_type' => $type, 'post_status' => 'publish', 'author' => $uid, 'posts_per_page' => 50, 'suppress_filters' => true, 'no_found_rows' => true ) ) as $p ) {
            if ( '' !== (string) $p->post_password ) {
                continue;
            }
            if ( 'pg_article' === $type && ( function_exists( 'prospergenics_articles_is_hidden' ) ? prospergenics_articles_is_hidden( $p->ID ) : get_post_meta( $p->ID, '_pg_articles_hidden', true ) ) ) {
                continue; // hidden by moderation (#6472): gone for everyone
            }
            $items[] = array( 'kind' => $kind, 'id' => (int) $p->ID );
        }
    }

    // approved scrutiny evidence (#6389) about the person or one of their public entries
    if ( ! empty( $vis['profile'] ) && post_type_exists( 'pg_scrutiny' ) && function_exists( 'prospergenics_scrutiny_record' ) ) {
        $targets = array( 'person:' . $uid );
        foreach ( $public as $id ) {
            $targets[] = 'claim:' . $id;
        }
        $posts = get_posts( array(
            'post_type'        => 'pg_scrutiny',
            'post_status'      => 'publish',
            'posts_per_page'   => 100,
            'meta_query'       => array(
                array( 'key' => '_pg_scrutiny_target', 'value' => $targets, 'compare' => 'IN' ),
                array( 'key' => '_pg_scrutiny_status', 'value' => 'approved' ),
                array( 'key' => '_pg_scrutiny_kind', 'value' => 'evidence' ),
            ),
            'suppress_filters' => true,
            'no_found_rows'    => true,
        ) );
        foreach ( $posts as $post ) {
            $r = prospergenics_scrutiny_record( $post );
            if ( $r && 'approved' === $r['status'] && 'evidence' === $r['kind'] && in_array( $r['target'], $targets, true ) && (int) $r['member_id'] !== $uid ) {
                $items[] = array( 'kind' => 'scrutiny', 'id' => (int) $r['id'] );
            }
        }
    }

    /**
     * More public evidence for a member's badge (Django Work review, other sources). Add only items
     * a logged-out visitor may see, each with a kind of prospergenics_profiles_evidence_weights().
     *
     * @param array $items
     * @param int   $user_id
     * @param array $visibility Sections a logged-out visitor may see.
     */
    $items = (array) apply_filters( 'prospergenics_profiles_rank_evidence', $items, $uid, $vis );
    return array( 'items' => $items, 'withheld' => $withheld );
}

/* ───────────────────────── computing and caching ───────────────────────── */

/**
 * Compute and store a member's rank.
 *
 * @return array{score:int,tier:string,breakdown:array,computed:int,rules:string,withheld:array,state:string}|null
 */
function prospergenics_profiles_rank_compute( $user_id ) {
    $uid = (int) $user_id;
    if ( ! get_userdata( $uid ) || prospergenics_profiles_rank_forgotten( $uid ) ) {
        return null;
    }
    $state    = prospergenics_profiles_rank_state( $uid );
    $evidence = prospergenics_profiles_rank_evidence( $uid );
    if ( in_array( $state, array( 'hidden', 'suspended' ), true ) ) {
        $evidence['withheld'] = array(); // nothing is public then: no "make it public" tips
    }
    $s        = prospergenics_profiles_rank_score( $evidence['items'] );
    $rank     = array(
        'score'     => (int) $s['score'],
        'tier'      => '' === $state ? prospergenics_profiles_rank_tier( $s['score'], $s['breakdown'], true ) : '',
        'breakdown' => $s['breakdown'],
        'computed'  => time(),
        'rules'     => prospergenics_profiles_rank_rules_hash(),
        'withheld'  => $evidence['withheld'],
        'state'     => $state,
    );
    update_user_meta( $uid, PROSPERGENICS_PROFILES_RANK_META, $rank );
    update_user_meta( $uid, PROSPERGENICS_PROFILES_RANK_TIER_META, '' !== $rank['tier'] ? $rank['tier'] : 'none' );
    prospergenics_profiles_rank_unqueue( $uid );
    return $rank;
}

/** Whether a stored rank can be used as it is: computed under these rules, less than a day ago. */
function prospergenics_profiles_rank_fresh( $rank ) {
    return is_array( $rank ) && isset( $rank['score'], $rank['tier'], $rank['computed'], $rank['rules'] )
        && (int) $rank['computed'] > time() - PROSPERGENICS_PROFILES_RANK_TTL
        && prospergenics_profiles_rank_rules_hash() === $rank['rules'];
}

/** Take one computation from this request's budget (false when it is used up). */
function prospergenics_profiles_rank_take_budget() {
    static $left = PROSPERGENICS_PROFILES_RANK_BUDGET;
    if ( $left < 1 ) {
        return false;
    }
    $left--;
    return true;
}

/**
 * A member's rank: the cached one while it is fresh, else computed now (within the request's
 * budget, or always with $force), else the stale one (queued for later), else null.
 */
function prospergenics_profiles_rank( $user_id, $force = false ) {
    $uid  = (int) $user_id;
    $rank = get_user_meta( $uid, PROSPERGENICS_PROFILES_RANK_META, true );
    if ( prospergenics_profiles_rank_fresh( $rank ) ) {
        return $rank;
    }
    if ( $force || prospergenics_profiles_rank_take_budget() ) {
        return prospergenics_profiles_rank_compute( $uid );
    }
    prospergenics_profiles_rank_queue( $uid );
    return is_array( $rank ) && isset( $rank['tier'] ) ? $rank : null;
}

/**
 * The public badge of a member: {tier, label}, or null for no badge (unverified, hidden,
 * suspended, no tier reached). The breakdown is never part of it.
 */
function prospergenics_profiles_badge_data( $user_id ) {
    $uid = (int) $user_id;
    if ( '' !== prospergenics_profiles_rank_state( $uid ) ) {
        return null; // checked live, whatever the cache says
    }
    $rank  = prospergenics_profiles_rank( $uid );
    $tiers = prospergenics_profiles_badge_tiers();
    if ( ! $rank || '' === (string) $rank['tier'] || ! isset( $tiers[ $rank['tier'] ] ) ) {
        return null;
    }
    return array( 'tier' => (string) $rank['tier'], 'label' => (string) $tiers[ $rank['tier'] ]['label'] );
}

/** Load the accounts and meta of a list of members in one read, before their badges render. */
function prospergenics_profiles_badges_prime( array $user_ids ) {
    $ids = array_values( array_unique( array_filter( array_map( 'intval', $user_ids ) ) ) );
    if ( $ids ) {
        cache_users( $ids );
    }
}

/* ───────────────────────── the queue ───────────────────────── */

/** Members whose rank is computed at the end of this request (user ID => true). */
function &prospergenics_profiles_rank_queue_ref() {
    static $queue = array();
    return $queue;
}

/** Recompute a member's rank at the end of the request. */
function prospergenics_profiles_rank_queue( $user_id ) {
    $uid = (int) $user_id;
    if ( $uid < 1 || prospergenics_profiles_rank_forgotten( $uid ) ) {
        return;
    }
    $queue = &prospergenics_profiles_rank_queue_ref();
    if ( ! $queue ) {
        add_action( 'shutdown', 'prospergenics_profiles_rank_run_queue', 5 );
    }
    $queue[ $uid ] = true;
}

/** Drop a member from the queue (computed already, erased or deleted). */
function prospergenics_profiles_rank_unqueue( $user_id ) {
    $queue = &prospergenics_profiles_rank_queue_ref();
    unset( $queue[ (int) $user_id ] );
}

/** Compute the queue: the first PROSPERGENICS_PROFILES_RANK_QUEUE_MAX now, the rest by cron. */
function prospergenics_profiles_rank_run_queue() {
    $queue = &prospergenics_profiles_rank_queue_ref();
    $ids   = array_keys( $queue );
    $queue = array();
    if ( ! $ids ) {
        return;
    }
    cache_users( $ids );
    foreach ( array_slice( $ids, 0, PROSPERGENICS_PROFILES_RANK_QUEUE_MAX ) as $uid ) {
        prospergenics_profiles_rank_compute( $uid );
    }
    $rest = array_slice( $ids, PROSPERGENICS_PROFILES_RANK_QUEUE_MAX );
    if ( $rest ) {
        wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'prospergenics_profiles_rank_refresh', array( $rest ) );
    }
}

/** Cron: compute the given members, or (hourly) the members without a stored tier. */
function prospergenics_profiles_rank_refresh( $user_ids = array() ) {
    if ( is_array( $user_ids ) && $user_ids ) {
        foreach ( array_slice( array_map( 'intval', $user_ids ), 0, PROSPERGENICS_PROFILES_RANK_CRON_BATCH ) as $uid ) {
            prospergenics_profiles_rank_compute( $uid );
        }
        return;
    }
    prospergenics_profiles_rank_backfill( PROSPERGENICS_PROFILES_RANK_CRON_BATCH );
}
add_action( 'prospergenics_profiles_rank_refresh', 'prospergenics_profiles_rank_refresh' );
add_action( 'init', function () {
    if ( ! wp_next_scheduled( 'prospergenics_profiles_rank_refresh' ) ) {
        wp_schedule_event( time() + HOUR_IN_SECONDS, 'hourly', 'prospergenics_profiles_rank_refresh' );
    }
} );
add_action( 'switch_theme', function () {
    wp_clear_scheduled_hook( 'prospergenics_profiles_rank_refresh' );
} );

/**
 * Compute the ranks of up to $limit members who have no stored tier (new members; everyone after
 * the rules changed, which clears the stored tiers). Returns how many were computed.
 */
function prospergenics_profiles_rank_backfill( $limit = 50 ) {
    $hash = prospergenics_profiles_rank_rules_hash();
    if ( get_option( PROSPERGENICS_PROFILES_RANK_RULES_OPTION ) !== $hash ) {
        delete_metadata( 'user', 0, PROSPERGENICS_PROFILES_RANK_TIER_META, '', true );
        update_option( PROSPERGENICS_PROFILES_RANK_RULES_OPTION, $hash, false );
    }
    $ids = get_users( array(
        'fields'     => 'ID',
        'number'     => max( 1, (int) $limit ),
        'orderby'    => 'ID',
        'meta_query' => array( array( 'key' => PROSPERGENICS_PROFILES_RANK_TIER_META, 'compare' => 'NOT EXISTS' ) ),
    ) );
    $ids = array_map( 'intval', (array) $ids );
    cache_users( $ids );
    foreach ( $ids as $uid ) {
        prospergenics_profiles_rank_compute( $uid );
    }
    return count( $ids );
}

/* ───────────────────────── what changes evidence ───────────────────────── */

/** The members whose public evidence a post is: its author, or a project's contributors. */
function prospergenics_profiles_rank_owners_of_post( $post ) {
    $post = get_post( $post );
    if ( ! $post ) {
        return array();
    }
    switch ( $post->post_type ) {
        case 'pg_skill':
        case 'pg_experience':
        case 'pg_education':
        case 'pg_article':
        case 'pg_course':
        case 'pg_certificate':
            return array( (int) $post->post_author );
        case 'pg_project':
            $ids = array_map( 'intval', get_post_meta( $post->ID, '_pg_project_contributor', false ) );
            return array_values( array_unique( array_filter( array_merge( $ids, array( (int) $post->post_author ) ) ) ) );
        case 'pg_scrutiny':
            $key = (string) get_post_meta( $post->ID, '_pg_scrutiny_target', true );
            if ( preg_match( '/^person:(\d+)$/', $key, $m ) ) {
                return array( (int) $m[1] );
            }
            if ( preg_match( '/^claim:(\d+)$/', $key, $m ) ) {
                $claim = get_post( (int) $m[1] );
                return $claim ? array( (int) $claim->post_author ) : array();
            }
            return array(); // evidence about a project counts for the project, not a person
    }
    return array();
}

function prospergenics_profiles_rank_queue_post( $post ) {
    foreach ( prospergenics_profiles_rank_owners_of_post( $post ) as $uid ) {
        prospergenics_profiles_rank_queue( $uid );
    }
}

/** A member's hidden / suspended state also decides whether the projects they added can be seen. */
function prospergenics_profiles_rank_queue_member_and_projects( $user_id ) {
    $uid = (int) $user_id;
    prospergenics_profiles_rank_queue( $uid );
    if ( post_type_exists( 'pg_project' ) ) {
        foreach ( get_posts( array( 'post_type' => 'pg_project', 'post_status' => 'any', 'author' => $uid, 'posts_per_page' => 100, 'fields' => 'ids', 'suppress_filters' => true, 'no_found_rows' => true ) ) as $pid ) {
            prospergenics_profiles_rank_queue_post( $pid );
        }
    }
}

// Entries, projects, articles, courses (the tracks fire this when they save) ...
add_action( 'prospergenics_platform_content_saved', function ( $post_id ) {
    prospergenics_profiles_rank_queue_post( $post_id );
} );
// ... publishing, unpublishing, trashing and restoring any of them, and certificates issued ...
add_action( 'transition_post_status', function ( $new, $old, $post ) {
    if ( $new !== $old && ( 'publish' === $new || 'publish' === $old ) ) {
        prospergenics_profiles_rank_queue_post( $post );
    }
}, 10, 3 );
// ... and deleting them (before: the author and contributors are still there to read).
add_action( 'before_delete_post', 'prospergenics_profiles_rank_queue_post' );
// Contribution status (#6388), scrutiny decisions (#6389), certificates (ARC-20), suspension (#6472).
add_action( 'prospergenics_projects_status_changed', function ( $project_id, $user_id ) {
    prospergenics_profiles_rank_queue( $user_id );
}, 10, 2 );
add_action( 'prospergenics_scrutiny_decided', 'prospergenics_profiles_rank_queue_post' );
add_action( 'prospergenics_courses_certificate_issued', function ( $cert_id, $user_id ) {
    prospergenics_profiles_rank_queue( $user_id );
}, 10, 2 );
add_action( 'prospergenics_courses_certificate_revoked', 'prospergenics_profiles_rank_queue_post' );
add_action( 'prospergenics_profiles_suspended', 'prospergenics_profiles_rank_queue_member_and_projects' );

/** Post meta that changes evidence: endorsements, hidden articles and projects, contributions, revoked certificates, scrutiny status. */
function prospergenics_profiles_rank_post_meta_changed( $meta_id, $post_id, $meta_key ) {
    $keys = array( '_pg_profiles_endorser', '_pg_articles_hidden', '_pg_project_contributions', '_pg_project_hidden', '_pg_courses_revoked_at', '_pg_scrutiny_status', '_pg_scrutiny_target' );
    if ( in_array( (string) $meta_key, $keys, true ) ) {
        prospergenics_profiles_rank_queue_post( (int) $post_id );
    }
}
/** User meta that changes what a visitor sees: privacy (and the onboarding state its defaults follow), hidden, suspended, verification. */
function prospergenics_profiles_rank_user_meta_changed( $meta_id, $user_id, $meta_key ) {
    if ( in_array( (string) $meta_key, array( '_pg_profiles_privacy', '_pg_profiles_onboarding', '_pg_profiles_verify_pending' ), true ) ) {
        prospergenics_profiles_rank_queue( (int) $user_id );
    } elseif ( in_array( (string) $meta_key, array( '_pg_profiles_hidden', '_pg_profiles_suspended' ), true ) ) {
        prospergenics_profiles_rank_queue_member_and_projects( (int) $user_id );
    }
}
foreach ( array( 'added', 'updated', 'deleted' ) as $prospergenics_profiles_rank_op ) {
    add_action( $prospergenics_profiles_rank_op . '_post_meta', 'prospergenics_profiles_rank_post_meta_changed', 10, 3 );
    add_action( $prospergenics_profiles_rank_op . '_user_meta', 'prospergenics_profiles_rank_user_meta_changed', 10, 3 );
}
unset( $prospergenics_profiles_rank_op );

/* ───────────────────────── personal data (ARC-12) ───────────────────────── */

/** The exporter: score, tier and breakdown. @return array{data:array,done:bool} */
function prospergenics_profiles_rank_exporter( $email, $page = 1 ) {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return array( 'data' => array(), 'done' => true );
    }
    $rank = prospergenics_profiles_rank( $user->ID, true );
    if ( ! $rank ) {
        return array( 'data' => array(), 'done' => true );
    }
    $tiers   = prospergenics_profiles_badge_tiers();
    $weights = prospergenics_profiles_evidence_weights();
    $data    = array(
        array( 'name' => 'Badge', 'value' => '' !== $rank['tier'] && isset( $tiers[ $rank['tier'] ] ) ? $tiers[ $rank['tier'] ]['label'] : 'none' ),
        array( 'name' => 'Score (public evidence)', 'value' => (string) (int) $rank['score'] ),
        array( 'name' => 'Computed', 'value' => gmdate( 'Y-m-d H:i', (int) $rank['computed'] ) . ' UTC' ),
    );
    foreach ( (array) $rank['breakdown'] as $kind => $b ) {
        $data[] = array(
            'name'  => isset( $weights[ $kind ] ) ? $weights[ $kind ]['label'] : $kind,
            'value' => sprintf( '%d points (%d of %d counted, at most %d)', (int) $b['points'], (int) $b['counted'], (int) $b['items'], (int) $b['cap'] ),
        );
    }
    return array(
        'data' => array( array(
            'group_id'          => 'prospergenics-badge',
            'group_label'       => 'Member badge',
            'group_description' => 'Your badge and the score behind it, from the evidence on your profile that everyone may see.',
            'item_id'           => 'pg-rank-' . (int) $user->ID,
            'data'              => $data,
        ) ),
        'done' => true,
    );
}

/** The eraser: the cached rank goes (it is computed again only from what is left, when shown). */
function prospergenics_profiles_rank_eraser( $email, $page = 1 ) {
    $user    = get_user_by( 'email', $email );
    $removed = false;
    if ( $user ) {
        $removed = (bool) get_user_meta( $user->ID, PROSPERGENICS_PROFILES_RANK_META, true );
        prospergenics_profiles_rank_forget( $user->ID );
    }
    return array( 'items_removed' => $removed, 'items_retained' => false, 'messages' => array(), 'done' => true );
}

/**
 * Delete a member's cached rank and keep this request from writing it again (the other erasers
 * delete entries after this one, which would queue the member once more).
 */
function prospergenics_profiles_rank_forget( $user_id ) {
    prospergenics_profiles_rank_forgotten( $user_id, true );
    delete_user_meta( (int) $user_id, PROSPERGENICS_PROFILES_RANK_META );
    delete_user_meta( (int) $user_id, PROSPERGENICS_PROFILES_RANK_TIER_META );
    prospergenics_profiles_rank_unqueue( $user_id );
}

/** Whether a member's rank was erased in this request (and, with $set, mark it so). */
function prospergenics_profiles_rank_forgotten( $user_id, $set = false ) {
    static $gone = array();
    if ( $set ) {
        $gone[ (int) $user_id ] = true;
    }
    return isset( $gone[ (int) $user_id ] );
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
    $exporters['prospergenics-badge'] = array(
        'exporter_friendly_name' => 'ProsperGenics member badge',
        'callback'               => 'prospergenics_profiles_rank_exporter',
    );
    return $exporters;
} );
add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
    $erasers['prospergenics-badge'] = array(
        'eraser_friendly_name' => 'ProsperGenics member badge',
        'callback'             => 'prospergenics_profiles_rank_eraser',
    );
    return $erasers;
} );
add_action( 'delete_user', 'prospergenics_profiles_rank_forget' );

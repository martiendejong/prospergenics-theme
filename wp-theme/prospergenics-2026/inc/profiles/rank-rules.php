<?php
/**
 * Profiles track: member badges by ranking, the pure rules (no database access), so they are
 * tested standalone (tests/test-platform-badges.php). rank.php gathers a member's public
 * evidence and caches the result; badges.php renders it.
 *
 * The evidence weights here are THE definition: the expertise search (inc/search/helpers.php,
 * prospergenics_search_evidence_kinds()) reads the same table, so a person ranks in /find/ and
 * earns a badge on one set of numbers. Search scores the evidence that matches a query; a badge
 * scores all of a member's public evidence, with a cap per kind so nobody can farm points:
 *
 *   kind               weight  verified  badge cap (items counted)    most points
 *   project_confirmed   10      yes       10                           100
 *   scrutiny             8      yes        5 (approved evidence)        40
 *   certificate          6      yes       10 (not revoked)              60
 *   course               6      yes        5 (published, taught)        30
 *   skill                4      no        10, +1 per endorsement, at    70
 *                                            most +3 per skill
 *   project_claimed      3      no         5                            15
 *   article              3      no         5 (published, not hidden)    15
 *   experience           3      no         5                            15
 *   education            2      no         3                             6
 *   headline             1      no         0 (search only)               0
 *
 * Disputed contributions are no evidence at all. Search keeps its own rule that a query's
 * matching articles count for at most three ('search_cap').
 */

defined( 'ABSPATH' ) || exit;

/** User meta holding the cached rank: {score, tier, breakdown, computed, rules, withheld, state}. */
const PROSPERGENICS_PROFILES_RANK_META = '_pg_profiles_rank';
/** User meta holding only the tier key ('none' for no badge), so /manage/ can filter on it. */
const PROSPERGENICS_PROFILES_RANK_TIER_META = '_pg_profiles_rank_tier';
/** A cached rank older than this is computed again when it is read. */
const PROSPERGENICS_PROFILES_RANK_TTL = 86400;
/** Bump when the computation itself changes, so every cached rank is computed again. */
const PROSPERGENICS_PROFILES_RANK_VERSION = '1';

/**
 * The evidence kinds: weight (points per item, shared with search), whether someone other than
 * the member checked it (verified), how many items a badge counts (cap), the endorsement bonus of
 * skills (bonus per endorsement, at most bonus_max per skill), search's own cap per query
 * (search_cap), and the words the owner view and /badges/ use. Filter
 * 'prospergenics_profiles_evidence_weights' to tune both search and badges at once.
 *
 * @return array<string,array{weight:float,verified:bool,cap:int,label:string,how:string}>
 */
function prospergenics_profiles_evidence_weights() {
    $kinds = array(
        'project_confirmed' => array( 'weight' => 10, 'verified' => true, 'cap' => 10, 'label' => 'Confirmed project contributions', 'how' => 'Get a contribution confirmed by the person who added the project' ),
        'scrutiny'          => array( 'weight' => 8, 'verified' => true, 'cap' => 5, 'label' => 'Approved evidence from others', 'how' => 'Ask someone who saw your work to add evidence about you; it counts once the ProsperGenics team approves it' ),
        'certificate'       => array( 'weight' => 6, 'verified' => true, 'cap' => 10, 'label' => 'Course certificates', 'how' => 'Complete a course and earn its certificate' ),
        'course'            => array( 'weight' => 6, 'verified' => true, 'cap' => 5, 'label' => 'Published courses you teach', 'how' => 'Teach a course: apply to teach in your settings' ),
        'skill'             => array( 'weight' => 4, 'verified' => false, 'cap' => 10, 'bonus' => 1, 'bonus_max' => 3, 'label' => 'Skills (plus endorsements)', 'how' => 'Add a skill; each endorsement by another member adds a point, up to 3 per skill' ),
        'project_claimed'   => array( 'weight' => 3, 'verified' => false, 'cap' => 5, 'label' => 'Project contributions (not confirmed yet)', 'how' => 'Add a project to your portfolio' ),
        'article'           => array( 'weight' => 3, 'verified' => false, 'cap' => 5, 'search_cap' => 3, 'label' => 'Published articles', 'how' => 'Publish an article' ),
        'experience'        => array( 'weight' => 3, 'verified' => false, 'cap' => 5, 'label' => 'Experience', 'how' => 'Add your work experience' ),
        'education'         => array( 'weight' => 2, 'verified' => false, 'cap' => 3, 'label' => 'Education', 'how' => 'Add your education' ),
        'headline'          => array( 'weight' => 1, 'verified' => false, 'cap' => 0, 'label' => 'Headline', 'how' => '' ),
    );
    return function_exists( 'apply_filters' ) ? (array) apply_filters( 'prospergenics_profiles_evidence_weights', $kinds ) : $kinds;
}

/**
 * The badge tiers, lowest first. This is the one place to rename a tier or move a threshold
 * (filter 'prospergenics_profiles_badge_tiers'). A tier is reached when all its conditions hold:
 *
 *   min_score   at least this many points of public evidence
 *   builds_on   the tier below it must be reached too ('' for none)
 *   any_of      at least min_any items of these kinds (the verified evidence it needs)
 *   kinds_of    at least min_kinds different kinds from this list with an item each
 *
 * Every tier needs a confirmed email address. A member gets the highest tier they reach. Each
 * tier has a colour set (bg, fg, border: fg on bg meets WCAG AA, 4.5:1) and an inline SVG icon
 * (16×16, currentColor), so a badge never relies on colour alone: its label is always shown.
 *
 * Default proposal (ProsperGenics to confirm):
 *   Member        email confirmed
 *   Contributor   10+ points and one piece of verified evidence: a confirmed contribution, a
 *                 certificate, approved evidence or a published article
 *   Practitioner  30+ points (and Contributor)
 *   Expert        60+ points and two different kinds of verified evidence: confirmed
 *                 contributions, certificates, approved evidence, courses taught (and Practitioner)
 *   Mentor        60+ points and teaches at least one published course
 *
 * @return array<string,array>
 */
function prospergenics_profiles_badge_tiers() {
    $tiers = array(
        'member'       => array(
            'label'     => 'Member',
            'min_score' => 0,
            'builds_on' => '',
            'means'     => 'Confirmed their email address. Everyone starts here.',
            'colour'    => array( 'bg' => '#ffffff', 'fg' => '#0f3d28', 'border' => '#9cc3ad' ),
            'icon'      => '<circle cx="8" cy="8" r="6.25" fill="none" stroke="currentColor" stroke-width="1.5"/><path d="M5.2 8.2l1.9 1.9 3.7-4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>',
        ),
        'contributor'  => array(
            'label'     => 'Contributor',
            'min_score' => 10,
            'builds_on' => 'member',
            'any_of'    => array( 'project_confirmed', 'certificate', 'scrutiny', 'article' ),
            'min_any'   => 1,
            'means'     => 'Has public evidence worth 10 points or more, at least one piece of it checked by someone else: a confirmed contribution, a certificate, approved evidence or a published article.',
            'colour'    => array( 'bg' => '#d9f5e3', 'fg' => '#0f3d28', 'border' => '#34c759' ),
            'icon'      => '<path d="M8 14.5V7.5" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M8 8.2C7.7 5.2 5.9 3.2 2.4 3.1c.1 3.2 2 5 5.6 5.1z" fill="currentColor"/><path d="M8 10c.1-2.7 1.9-4.5 5.4-4.6-.1 2.9-1.9 4.5-5.4 4.6z" fill="currentColor"/>',
        ),
        'practitioner' => array(
            'label'     => 'Practitioner',
            'min_score' => 30,
            'builds_on' => 'contributor',
            'means'     => 'A Contributor with public evidence worth 30 points or more.',
            'colour'    => array( 'bg' => '#1a7a46', 'fg' => '#ffffff', 'border' => '#86e0a6' ),
            'icon'      => '<path d="M3 8.8L8 4.3l5 4.5M3 12.6L8 8.1l5 4.5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>',
        ),
        'expert'       => array(
            'label'     => 'Expert',
            'min_score' => 60,
            'builds_on' => 'practitioner',
            'kinds_of'  => array( 'project_confirmed', 'certificate', 'scrutiny', 'course' ),
            'min_kinds' => 2,
            'means'     => 'A Practitioner with public evidence worth 60 points or more, of at least two different verified kinds: confirmed contributions, certificates, approved evidence or courses taught.',
            'colour'    => array( 'bg' => '#0f3d28', 'fg' => '#ffffff', 'border' => '#34c759' ),
            'icon'      => '<path d="M8 1.7l1.9 4 4.4.5-3.3 3 .9 4.3L8 11.3l-3.9 2.2.9-4.3-3.3-3 4.4-.5z" fill="currentColor"/>',
        ),
        'mentor'       => array(
            'label'     => 'Mentor',
            'min_score' => 60,
            'builds_on' => 'member',
            'kinds_of'  => array( 'course' ),
            'min_kinds' => 1,
            'means'     => 'Teaches at least one published course and has public evidence worth 60 points or more.',
            'colour'    => array( 'bg' => '#f7e9bf', 'fg' => '#4d3800', 'border' => '#c9971c' ),
            'icon'      => '<path d="M8 4.3C6.6 3.3 4.6 2.9 2 3.1v9.3c2.6-.2 4.6.2 6 1.2 1.4-1 3.4-1.4 6-1.2V3.1c-2.6-.2-4.6.2-6 1.2z" fill="none" stroke="currentColor" stroke-width="1.4" stroke-linejoin="round"/><path d="M8 4.3v9.3" fill="none" stroke="currentColor" stroke-width="1.4"/>',
        ),
    );
    $tiers = function_exists( 'apply_filters' ) ? (array) apply_filters( 'prospergenics_profiles_badge_tiers', $tiers ) : $tiers;
    foreach ( $tiers as $key => $t ) {
        $tiers[ $key ] = array_merge( array( 'label' => (string) $key, 'min_score' => 0, 'builds_on' => '', 'any_of' => array(), 'min_any' => 0, 'kinds_of' => array(), 'min_kinds' => 0, 'means' => '', 'colour' => array(), 'icon' => '' ), (array) $t );
    }
    return $tiers;
}

/** A fingerprint of the rules: a cached rank computed under other rules is computed again. */
function prospergenics_profiles_rank_rules_hash( $tiers = null, $weights = null ) {
    $tiers   = null === $tiers ? prospergenics_profiles_badge_tiers() : $tiers;
    $weights = null === $weights ? prospergenics_profiles_evidence_weights() : $weights;
    $strip   = array();
    foreach ( $tiers as $k => $t ) {
        $strip[ $k ] = array_intersect_key( $t, array_flip( array( 'min_score', 'builds_on', 'any_of', 'min_any', 'kinds_of', 'min_kinds' ) ) );
    }
    $w = array();
    foreach ( $weights as $k => $v ) {
        $w[ $k ] = array_intersect_key( (array) $v, array_flip( array( 'weight', 'cap', 'bonus', 'bonus_max' ) ) );
    }
    return substr( md5( PROSPERGENICS_PROFILES_RANK_VERSION . '|' . json_encode( $strip ) . '|' . json_encode( $w ) ), 0, 12 );
}

/**
 * Score a member's evidence for a badge. Each kind counts at most its cap of items (skills with
 * the most endorsements first); a skill adds its bonus per endorsement, at most bonus_max.
 * Kinds without a weight, or with cap 0, add nothing.
 *
 * @param array $items   Items: kind, endorsements (skills).
 * @param array $weights prospergenics_profiles_evidence_weights().
 * @return array{score:int,breakdown:array<string,array{items:int,counted:int,cap:int,points:int}>}
 */
function prospergenics_profiles_rank_score( array $items, $weights = null ) {
    $weights = null === $weights ? prospergenics_profiles_evidence_weights() : $weights;
    $by_kind = array();
    foreach ( $items as $e ) {
        $kind = is_array( $e ) && isset( $e['kind'] ) ? (string) $e['kind'] : '';
        if ( isset( $weights[ $kind ] ) ) {
            $by_kind[ $kind ][] = $e;
        }
    }
    $score     = 0;
    $breakdown = array();
    foreach ( $weights as $kind => $w ) {
        $list = isset( $by_kind[ $kind ] ) ? $by_kind[ $kind ] : array();
        $cap  = max( 0, (int) ( isset( $w['cap'] ) ? $w['cap'] : 0 ) );
        if ( ! $list || ! $cap ) {
            continue;
        }
        if ( 'skill' === $kind ) {
            usort( $list, function ( $a, $b ) {
                return ( isset( $b['endorsements'] ) ? (int) $b['endorsements'] : 0 ) - ( isset( $a['endorsements'] ) ? (int) $a['endorsements'] : 0 );
            } );
        }
        $counted = array_slice( $list, 0, $cap );
        $points  = 0;
        foreach ( $counted as $e ) {
            $points += (int) round( (float) $w['weight'] );
            if ( ! empty( $w['bonus'] ) && ! empty( $e['endorsements'] ) ) {
                $points += min( (int) ( isset( $w['bonus_max'] ) ? $w['bonus_max'] : 0 ), (int) $w['bonus'] * (int) $e['endorsements'] );
            }
        }
        $breakdown[ $kind ] = array( 'items' => count( $list ), 'counted' => count( $counted ), 'cap' => $cap, 'points' => $points );
        $score += $points;
    }
    return array( 'score' => $score, 'breakdown' => $breakdown );
}

/**
 * Whether a tier's own conditions hold, and those of the tiers it builds on.
 *
 * @param array $breakdown prospergenics_profiles_rank_score()['breakdown'].
 */
function prospergenics_profiles_tier_reached( $key, array $tiers, $score, array $breakdown, $depth = 0 ) {
    if ( ! isset( $tiers[ $key ] ) || $depth > 10 ) {
        return false;
    }
    $t   = $tiers[ $key ];
    $has = function ( $kind ) use ( $breakdown ) {
        return isset( $breakdown[ $kind ] ) ? (int) $breakdown[ $kind ]['counted'] : 0;
    };
    if ( (int) $score < (int) $t['min_score'] ) {
        return false;
    }
    if ( (int) $t['min_any'] > 0 && array_sum( array_map( $has, (array) $t['any_of'] ) ) < (int) $t['min_any'] ) {
        return false;
    }
    if ( (int) $t['min_kinds'] > 0 && count( array_filter( array_map( $has, (array) $t['kinds_of'] ) ) ) < (int) $t['min_kinds'] ) {
        return false;
    }
    return '' === (string) $t['builds_on'] || prospergenics_profiles_tier_reached( $t['builds_on'], $tiers, $score, $breakdown, $depth + 1 );
}

/**
 * The tier a member reaches: the last one in the list whose conditions hold, or '' (no badge)
 * when the email address is not confirmed or no tier is reached.
 */
function prospergenics_profiles_rank_tier( $score, array $breakdown, $email_verified, $tiers = null ) {
    $tiers = null === $tiers ? prospergenics_profiles_badge_tiers() : $tiers;
    if ( ! $email_verified ) {
        return '';
    }
    $tier = '';
    foreach ( array_keys( $tiers ) as $key ) {
        if ( prospergenics_profiles_tier_reached( $key, $tiers, $score, $breakdown ) ) {
            $tier = $key;
        }
    }
    return $tier;
}

/**
 * What the owner needs for the next tier: the first tier after theirs in the list that they do
 * not reach, the points missing, and tips (the conditions not met, then the strongest kinds that
 * still have room under their cap, then the sections they keep private).
 *
 * @param array $rank    score, tier, breakdown, withheld (kind => items in non-public sections).
 * @return array{tier:string,label:string,points:int,tips:string[]}|null null at the top.
 */
function prospergenics_profiles_rank_next( array $rank, $tiers = null, $weights = null ) {
    $tiers   = null === $tiers ? prospergenics_profiles_badge_tiers() : $tiers;
    $weights = null === $weights ? prospergenics_profiles_evidence_weights() : $weights;
    $keys    = array_keys( $tiers );
    $score   = isset( $rank['score'] ) ? (int) $rank['score'] : 0;
    $bd      = isset( $rank['breakdown'] ) && is_array( $rank['breakdown'] ) ? $rank['breakdown'] : array();
    $at      = isset( $rank['tier'] ) ? array_search( (string) $rank['tier'], $keys, true ) : false;
    $next    = '';
    for ( $i = false === $at ? 0 : $at + 1; $i < count( $keys ); $i++ ) {
        if ( ! prospergenics_profiles_tier_reached( $keys[ $i ], $tiers, $score, $bd ) ) {
            $next = $keys[ $i ];
            break;
        }
    }
    if ( '' === $next ) {
        return null;
    }
    $names = function ( array $kinds ) use ( $weights ) {
        return implode( ', ', array_map( function ( $k ) use ( $weights ) {
            return isset( $weights[ $k ]['label'] ) ? strtolower( $weights[ $k ]['label'] ) : $k;
        }, $kinds ) );
    };
    $room = function ( $kind ) use ( $weights, $bd ) {
        return isset( $weights[ $kind ] ) && ! empty( $weights[ $kind ]['cap'] ) && '' !== (string) $weights[ $kind ]['how']
            && ( isset( $bd[ $kind ] ) ? (int) $bd[ $kind ]['counted'] : 0 ) < (int) $weights[ $kind ]['cap'];
    };
    $tips = array();
    $how  = array(); // kinds to suggest, in order
    // the conditions of the next tier (and of the tiers it builds on) that are not met yet
    for ( $k = $next, $d = 0; '' !== $k && isset( $tiers[ $k ] ) && $d < 10; $k = $tiers[ $k ]['builds_on'], $d++ ) {
        $t = $tiers[ $k ];
        if ( (int) $t['min_any'] > 0 ) {
            $n = 0;
            foreach ( (array) $t['any_of'] as $kind ) {
                $n += isset( $bd[ $kind ] ) ? (int) $bd[ $kind ]['counted'] : 0;
            }
            if ( $n < (int) $t['min_any'] ) {
                $tips[] = sprintf( '%s needs at least %s of these: %s.', $t['label'], 1 === (int) $t['min_any'] ? 'one' : (string) (int) $t['min_any'], $names( (array) $t['any_of'] ) );
                $how    = array_merge( $how, (array) $t['any_of'] );
            }
        }
        if ( (int) $t['min_kinds'] > 0 ) {
            $missing = array_values( array_filter( (array) $t['kinds_of'], function ( $kind ) use ( $bd ) {
                return empty( $bd[ $kind ]['counted'] );
            } ) );
            if ( count( (array) $t['kinds_of'] ) - count( $missing ) < (int) $t['min_kinds'] ) {
                $tips[] = 1 === count( (array) $t['kinds_of'] )
                    ? sprintf( '%s needs %s.', $t['label'], $names( (array) $t['kinds_of'] ) )
                    : sprintf( '%s needs %d different kinds of verified evidence. Missing: %s.', $t['label'], (int) $t['min_kinds'], $names( $missing ) );
                $how = array_merge( $how, $missing );
            }
        }
    }
    $points = max( 0, (int) $tiers[ $next ]['min_score'] - $score );
    if ( $points > 0 ) {
        // the strongest kinds with room left under their cap
        $strong = array();
        foreach ( $weights as $kind => $w ) {
            if ( $room( $kind ) ) {
                $strong[ $kind ] = (float) $w['weight'];
            }
        }
        arsort( $strong );
        $how = array_merge( $how, array_slice( array_keys( $strong ), 0, 3 ) );
    }
    foreach ( array_slice( array_values( array_unique( array_filter( $how, $room ) ) ), 0, 4 ) as $kind ) {
        $tips[] = sprintf( '%s (+%d).', $weights[ $kind ]['how'], (int) $weights[ $kind ]['weight'] );
    }
    foreach ( isset( $rank['withheld'] ) && is_array( $rank['withheld'] ) ? $rank['withheld'] : array() as $kind => $n ) {
        if ( (int) $n > 0 && isset( $weights[ $kind ] ) ) {
            $one    = 1 === (int) $n;
            $tips[] = sprintf(
                '%d of your %s %s in a section that is not public, so %s not count. Show that section to everyone in your privacy settings to count %s.',
                (int) $n,
                strtolower( $weights[ $kind ]['label'] ),
                $one ? 'is' : 'are',
                $one ? 'it does' : 'they do',
                $one ? 'it' : 'them'
            );
        }
    }
    return array( 'tier' => $next, 'label' => (string) $tiers[ $next ]['label'], 'points' => $points, 'tips' => array_values( array_unique( $tips ) ) );
}

/**
 * A tier's conditions in words, for the /badges/ page: "At least 10 points of public evidence",
 * "At least one of: confirmed project contributions, …", and the tier it builds on.
 *
 * @return string[]
 */
function prospergenics_profiles_tier_requirements( $key, $tiers = null, $weights = null ) {
    $tiers   = null === $tiers ? prospergenics_profiles_badge_tiers() : $tiers;
    $weights = null === $weights ? prospergenics_profiles_evidence_weights() : $weights;
    if ( ! isset( $tiers[ $key ] ) ) {
        return array();
    }
    $t     = $tiers[ $key ];
    $names = function ( array $kinds ) use ( $weights ) {
        return implode( ', ', array_map( function ( $k ) use ( $weights ) {
            return isset( $weights[ $k ]['label'] ) ? strtolower( $weights[ $k ]['label'] ) : $k;
        }, $kinds ) );
    };
    $out = array( 'A confirmed email address' );
    if ( '' !== (string) $t['builds_on'] && isset( $tiers[ $t['builds_on'] ] ) && '' !== (string) $tiers[ $t['builds_on'] ]['builds_on'] ) {
        $out[] = sprintf( 'Everything %s needs', $tiers[ $t['builds_on'] ]['label'] );
    }
    if ( (int) $t['min_score'] > 0 ) {
        $out[] = sprintf( 'At least %d points of public evidence', (int) $t['min_score'] );
    }
    if ( (int) $t['min_any'] > 0 ) {
        $out[] = 1 === (int) $t['min_any']
            ? sprintf( 'At least one of: %s', $names( (array) $t['any_of'] ) )
            : sprintf( 'At least %d of: %s', (int) $t['min_any'], $names( (array) $t['any_of'] ) );
    }
    if ( (int) $t['min_kinds'] > 0 ) {
        $out[] = 1 === count( (array) $t['kinds_of'] )
            ? sprintf( 'At least one of: %s', $names( (array) $t['kinds_of'] ) )
            : sprintf( 'At least %d different kinds of: %s', (int) $t['min_kinds'], $names( (array) $t['kinds_of'] ) );
    }
    return $out;
}

/** A colour from the tiers array: a hex colour, else the fallback. */
function prospergenics_profiles_badge_colour( $value, $fallback ) {
    return is_string( $value ) && preg_match( '/^#[0-9a-f]{3}(?:[0-9a-f]{3})?$/i', $value ) ? $value : $fallback;
}

/** The SVG elements an icon may use (the tiers array is filterable; its markup is still checked). */
function prospergenics_profiles_badge_svg_kses() {
    $common = array( 'fill' => true, 'stroke' => true, 'stroke-width' => true, 'stroke-linecap' => true, 'stroke-linejoin' => true, 'transform' => true, 'opacity' => true );
    return array(
        'path'     => $common + array( 'd' => true ),
        'circle'   => $common + array( 'cx' => true, 'cy' => true, 'r' => true ),
        'rect'     => $common + array( 'x' => true, 'y' => true, 'width' => true, 'height' => true, 'rx' => true ),
        'polygon'  => $common + array( 'points' => true ),
        'polyline' => $common + array( 'points' => true ),
        'g'        => $common,
    );
}

/**
 * The badge markup for a tier. $context: 'header' (larger, profile header), 'inline' (a <span>:
 * inside another link, e.g. a member card) or anything else (compact, for lists). Linked badges
 * go to the /badges/ explainer. Everything is escaped here.
 *
 * @param string $url The explainer page ('' for no link).
 */
function prospergenics_profiles_badge_markup( $key, array $tier, $context = 'compact', $url = '' ) {
    $size  = 'header' === $context ? 'header' : 'compact';
    $link  = 'inline' !== $context && '' !== (string) $url;
    $c     = isset( $tier['colour'] ) && is_array( $tier['colour'] ) ? $tier['colour'] : array();
    $style = sprintf(
        '--pgb-bg:%s;--pgb-fg:%s;--pgb-bd:%s',
        prospergenics_profiles_badge_colour( isset( $c['bg'] ) ? $c['bg'] : '', '#eaf5ee' ),
        prospergenics_profiles_badge_colour( isset( $c['fg'] ) ? $c['fg'] : '', '#0f3d28' ),
        prospergenics_profiles_badge_colour( isset( $c['border'] ) ? $c['border'] : '', '#86e0a6' )
    );
    $icon  = isset( $tier['icon'] ) ? (string) $tier['icon'] : '';
    $icon  = function_exists( 'wp_kses' ) ? wp_kses( $icon, prospergenics_profiles_badge_svg_kses() ) : strip_tags( $icon, '<path><circle><rect><polygon><polyline><g>' );
    $px    = 'header' === $size ? 18 : 14;
    $label = isset( $tier['label'] ) ? (string) $tier['label'] : (string) $key;
    $inner = sprintf( '<svg class="pg-badge-icon" aria-hidden="true" focusable="false" viewBox="0 0 16 16" width="%1$d" height="%1$d">%2$s</svg>', $px, $icon )
        . '<span class="pg-badge-label">' . esc_html( $label ) . '</span>'
        . '<span class="screen-reader-text"> badge</span>';
    $attrs = sprintf( ' class="pg-badge pg-badge--%s pg-badge--%s" style="%s" data-pg-badge="%s"', esc_attr( $size ), esc_attr( prospergenics_profiles_badge_class_key( $key ) ), esc_attr( $style ), esc_attr( $key ) );
    return $link
        ? '<a' . $attrs . ' href="' . esc_url( $url ) . '">' . $inner . '</a>'
        : '<span' . $attrs . '>' . $inner . '</span>';
}

/** A tier key fit for a class name (lowercase letters, digits, - and _). */
function prospergenics_profiles_badge_class_key( $key ) {
    return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $key ) );
}

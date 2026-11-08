<?php
/**
 * The homepage chat (CoachOS tenant "prospergenics", coaching.martiendejong.nl) gets page context:
 * the people of ProsperGenics as the site shows them, and who is logged in.
 *
 * The chat's own knowledge base lives in CoachOS, not in this theme. On 8 Nov it called Lesiamon
 * "she" (he is "he") and could not answer a member's "who am I?". CoachOS' embed.js sends its
 * data-page-content attribute to the chat as context with every conversation (max 8000 characters),
 * so the theme puts the facts there:
 *   - each person from wp/inc/people.json (generated from people/build-profiles.py, the one people
 *     source): name, other name they go by, pronouns where known, role (Martien's 7 Nov wording);
 *   - for a logged-in member: display name, platform role, writer, profile URL, and their team entry
 *     when the account is linked to a /people/ page (_pg_people_slug). Never the email address.
 * The knowledge base itself should still be corrected in CoachOS (Martien).
 *
 * The same embed opens from the "Try it live" button on /tools/coachos/ (inc/tool-demos.php, card #7218).
 *
 * Filter prospergenics_2026_coach_context changes the text; prospergenics_2026_coach_embed_attrs the tag.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_2026_COACH_CONTEXT_MAX = 8000; // embed.js cuts the page content at 8000 characters

/** The people of ProsperGenics, from wp/inc/people.json. @return array<string,array> slug => person */
function prospergenics_2026_coach_people() {
    static $people = null;
    if ( null === $people ) {
        $file   = __DIR__ . '/people.json';
        $data   = is_readable( $file ) ? json_decode( (string) file_get_contents( $file ), true ) : null;
        $people = is_array( $data ) && isset( $data['people'] ) && is_array( $data['people'] ) ? $data['people'] : array();
    }
    return $people;
}

/** One line about a person (pure). */
function prospergenics_2026_coach_person_line( $slug, array $p ) {
    $name = trim( (string) ( isset( $p['name'] ) ? $p['name'] : '' ) );
    if ( '' === $name ) {
        return '';
    }
    $line = '- ' . $name;
    if ( ! empty( $p['aka'] ) ) {
        $line .= ' (also called ' . $p['aka'] . ')';
    }
    if ( ! empty( $p['pronouns'] ) ) {
        $line .= ', pronouns ' . $p['pronouns'];
    }
    if ( ! empty( $p['role'] ) ) {
        $line .= ': ' . $p['role'];
    }
    return $line . '. Profile: ' . home_url( '/people/' . sanitize_title( (string) $slug ) . '/' );
}

/** What the chat should know about the logged-in member, or '' for a visitor. */
function prospergenics_2026_coach_member_lines( $user_id ) {
    $user = $user_id ? get_userdata( (int) $user_id ) : false;
    if ( ! $user ) {
        return array( 'The person chatting is a visitor who is not logged in, so you do not know who they are.' );
    }
    $role = function_exists( 'prospergenics_roles_get' ) ? prospergenics_roles_get( $user->ID ) : 'member';
    $labels = array( 'admin' => 'ProsperGenics staff', 'instructor' => 'member and course instructor', 'company' => 'company account', 'member' => 'member' );
    $line = 'The person chatting is logged in as ' . $user->display_name . ' (' . ( isset( $labels[ $role ] ) ? $labels[ $role ] : 'member' );
    if ( in_array( 'pg_writer', (array) $user->roles, true ) ) {
        $line .= ', writes articles';
    }
    $line .= ')';
    if ( function_exists( 'prospergenics_profiles_url' ) ) {
        $line .= '; their member profile is ' . prospergenics_profiles_url( $user->ID );
    }
    $lines = array( $line . '.' );
    $slug  = (string) get_user_meta( $user->ID, '_pg_people_slug', true );
    $all   = prospergenics_2026_coach_people();
    if ( '' !== $slug && isset( $all[ $slug ] ) ) {
        $lines[] = 'They are this person from the ProsperGenics team list: ' . ltrim( prospergenics_2026_coach_person_line( $slug, $all[ $slug ] ), '- ' );
    }
    $lines[] = 'When they ask who they are, answer from this. Do not mention their email address.';
    return $lines;
}

/** The context text for the chat (the team, then the person chatting). */
function prospergenics_2026_coach_context( $user_id = null ) {
    $user_id = null === $user_id ? get_current_user_id() : (int) $user_id;
    $lines   = array(
        'Facts from the ProsperGenics website (these are current and win over anything else you know):',
        'The people of ProsperGenics:',
    );
    foreach ( prospergenics_2026_coach_people() as $slug => $p ) {
        $l = prospergenics_2026_coach_person_line( $slug, (array) $p );
        if ( '' !== $l ) {
            $lines[] = $l;
        }
    }
    $founders = array();
    $team     = array();
    foreach ( prospergenics_2026_coach_people() as $p ) {
        $role = isset( $p['role'] ) ? (string) $p['role'] : '';
        if ( preg_match( '/\bFounder\b/', $role ) ) {
            $founders[] = $p['name'];
        } elseif ( false !== stripos( $role, 'Founding team' ) ) {
            $team[] = $p['name'];
        }
    }
    if ( $founders ) {
        $lines[] = 'Founder: ' . implode( ', ', $founders ) . '. Founding team: ' . ( $team ? implode( ', ', $team ) : 'none listed' ) . '. Nobody is called a co-founder.';
    }
    $lines[] = 'Refer to each person with the pronouns listed for them. When none are listed, use their name or they/them; never guess.';
    $lines   = array_merge( $lines, prospergenics_2026_coach_member_lines( $user_id ) );
    $text    = (string) apply_filters( 'prospergenics_2026_coach_context', implode( "\n", $lines ), $user_id );
    return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, PROSPERGENICS_2026_COACH_CONTEXT_MAX ) : substr( $text, 0, PROSPERGENICS_2026_COACH_CONTEXT_MAX );
}

/**
 * The CoachOS embed settings. $overrides win over the filter: the /tools/coachos/ page (card #7218)
 * opens the chat from its own "Try it live" button, so it sets data-trigger to that button.
 */
function prospergenics_2026_coach_embed_attrs( array $overrides = array() ) {
    $attrs = (array) apply_filters( 'prospergenics_2026_coach_embed_attrs', array(
        'src'               => 'https://coaching.martiendejong.nl/coaching/embed.js?v=2',
        'data-tenant'       => 'prospergenics',
        'data-lang'         => 'en',
        'data-title'        => 'Chat with ProsperGenics',
        'data-color'        => '#1a7a46',
        'data-trigger'      => '.launcher-orb',
        'data-position'     => 'right',
        'data-page-content' => prospergenics_2026_coach_context(),
    ) );
    return array_merge( $attrs, $overrides );
}

/**
 * The chat page embed.js loads in its panel: {origin}{path before /embed.js}/{tenant}/widget?taal={lang},
 * built the same way embed.js builds it. A link to it is the fallback while embed.js has not loaded
 * (slow connection, blocked script): the visitor still gets the real chat, full page.
 */
function prospergenics_2026_coach_widget_url( array $attrs ) {
    $src = isset( $attrs['src'] ) ? (string) $attrs['src'] : '';
    if ( ! preg_match( '#^(https://[a-z0-9.-]+(?::\d+)?)(/[^?\#]*?)?/embed\.js(?:[?\#].*)?$#i', $src, $m ) ) {
        return '';
    }
    $tenant = preg_replace( '/[^a-z0-9_-]/i', '', isset( $attrs['data-tenant'] ) ? (string) $attrs['data-tenant'] : '' );
    $lang   = substr( preg_replace( '/[^a-z]/i', '', isset( $attrs['data-lang'] ) ? (string) $attrs['data-lang'] : '' ), 0, 2 );
    return $m[1] . ( isset( $m[2] ) ? $m[2] : '' ) . ( '' !== $tenant ? '/' . $tenant : '' ) . '/widget' . ( '' !== $lang ? '?taal=' . $lang : '' );
}

/** The CoachOS script tag (the homepage, and /tools/coachos/ with its own trigger), with the context as data-page-content. */
function prospergenics_2026_coach_embed( array $overrides = array() ) {
    $attrs = prospergenics_2026_coach_embed_attrs( $overrides );
    $html  = '<script';
    foreach ( $attrs as $k => $v ) {
        $html .= ' ' . preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $k ) ) . '="' . ( 'src' === $k ? esc_url( $v ) : esc_attr( (string) $v ) ) . '"';
    }
    return $html . ' async></script>' . "\n" . prospergenics_2026_coach_context_resend();
}

/**
 * CoachOS' embed.js posts data-page-content to the chat iframe once, on the iframe's load event, but
 * the chat app only starts listening a moment later, so that one message is lost and the chat answers
 * from its knowledge base alone (seen 8 Nov: still "she" for Lesiamon, no founder). Until embed.js waits
 * for the app (e.g. a "ready" message), post the same context again shortly after the iframe loads.
 * It only sets the chat's page context; posting it twice is harmless.
 */
function prospergenics_2026_coach_context_resend() {
    $origin = 'https://coaching.martiendejong.nl';
    return '<script>(function(){'
        . 'var O=' . wp_json_encode( $origin ) . ';'
        . 'function ctx(){var s=document.querySelector(\'script[src^="\'+O+\'"][data-page-content]\');return s?s.getAttribute("data-page-content"):"";}'
        . 'function send(f){var c=ctx();if(c&&f.contentWindow){f.contentWindow.postMessage({type:"co-pagina-context",content:c},O);}}'
        . 'function hook(f){if(f.__pgCtx)return;f.__pgCtx=1;f.addEventListener("load",function(){[400,1200,3000,6000].forEach(function(t){setTimeout(function(){send(f);},t);});});}'
        . 'var mo;function scan(){var fs=document.querySelectorAll(\'iframe[src^="\'+O+\'"]\');for(var i=0;i<fs.length;i++)hook(fs[i]);if(fs.length&&mo)mo.disconnect();}'
        . 'mo=new MutationObserver(scan);mo.observe(document.documentElement,{childList:true,subtree:true});scan();'
        . '})();</script>';
}

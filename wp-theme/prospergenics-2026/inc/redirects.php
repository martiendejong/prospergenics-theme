<?php
/**
 * Legacy URL redirects (301) for the 2026 theme.
 *
 * Rules live in redirects.txt next to this file (one "old new" pair per line), so adding the
 * old URLs from the launch task is a data change, not a code change. Redirects only fire for
 * requests that would otherwise 404, so a real page is never hidden by a rule.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Parse redirect rules from text.
 *
 * @return array<string,string> normalised old path (may end in "/*") => target
 */
function prospergenics_2026_parse_redirects( $text ) {
    $rules = array();
    foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
        $line = trim( preg_replace( '/#.*$/', '', $line ) );
        if ( '' === $line ) {
            continue;
        }
        $parts = preg_split( '/\s+/', $line );
        if ( count( $parts ) < 2 ) {
            continue;
        }
        $rules[ prospergenics_2026_normalize_path( $parts[0] ) ] = $parts[1];
    }
    return $rules;
}

/** Lower-case, no leading/trailing slashes, no query string. Keeps a trailing "/*". */
function prospergenics_2026_normalize_path( $path ) {
    $path = (string) parse_url( (string) $path, PHP_URL_PATH );
    $wild = '/*' === substr( rtrim( $path ), -2 ) || '*' === $path;
    $path = strtolower( trim( rtrim( trim( $path ), '*' ), '/' ) );
    return $wild ? ( '' === $path ? '*' : $path . '/*' ) : $path;
}

/** The rules from redirects.txt, read once per request. */
function prospergenics_2026_redirect_rules() {
    static $rules = null;
    if ( null === $rules ) {
        $file  = apply_filters( 'prospergenics_2026_redirects_file', __DIR__ . '/redirects.txt' );
        $rules = is_readable( $file ) ? prospergenics_2026_parse_redirects( file_get_contents( $file ) ) : array();
    }
    return $rules;
}

/**
 * Find the target for a request path: an exact rule first, then the longest matching "/*" rule.
 *
 * @return string|null
 */
function prospergenics_2026_match_redirect( $request_path, $rules ) {
    $path = prospergenics_2026_normalize_path( $request_path );
    if ( isset( $rules[ $path ] ) && '/*' !== substr( $path, -2 ) ) {
        return $rules[ $path ];
    }
    $best = null;
    $best_len = -1;
    foreach ( $rules as $source => $target ) {
        if ( '/*' !== substr( $source, -2 ) ) {
            continue;
        }
        $prefix = substr( $source, 0, -2 );
        if ( 0 === strpos( $path, $prefix . '/' ) && strlen( $prefix ) > $best_len ) {
            $best = $target;
            $best_len = strlen( $prefix );
        }
    }
    return $best;
}

/**
 * Problems in a rule set: targets that are not a site path or a full URL, rules that point at
 * themselves, and chains (a target that is itself redirected, which costs an extra hop or loops).
 *
 * @return string[] one line per problem; empty when the rules are fine
 */
function prospergenics_2026_redirect_problems( $rules ) {
    $problems = array();
    foreach ( $rules as $source => $target ) {
        if ( ! preg_match( '#^(/|https://)#', $target ) ) {
            $problems[] = "$source: target \"$target\" must start with / or https://";
            continue;
        }
        if ( 0 === strpos( $target, '/' ) ) {
            $hop = prospergenics_2026_match_redirect( $target, $rules );
            if ( null !== $hop ) {
                $problems[] = ( prospergenics_2026_normalize_path( $target ) === rtrim( $source, '/*' ) || $hop === $target )
                    ? "$source: redirects to itself"
                    : "$source: chain, $target is redirected again to $hop";
            }
        }
    }
    return $problems;
}

/** Duplicate old paths in raw rule text (the parser keeps only the last one, so flag them). */
function prospergenics_2026_redirect_duplicates( $text ) {
    $seen = array();
    $dupes = array();
    foreach ( preg_split( '/\R/', (string) $text ) as $line ) {
        $line = trim( preg_replace( '/#.*$/', '', $line ) );
        if ( '' === $line ) {
            continue;
        }
        $source = prospergenics_2026_normalize_path( preg_split( '/\s+/', $line )[0] );
        if ( isset( $seen[ $source ] ) ) {
            $dupes[] = $source;
        }
        $seen[ $source ] = true;
    }
    return $dupes;
}

/**
 * Old member profile URLs: /members/<slug>/ and /team_member/<slug>/ go to /people/<slug>/ when
 * that static profile exists, so a mistyped name still 404s instead of bouncing to nothing.
 *
 * @return string|null
 */
function prospergenics_2026_profile_redirect( $request_path, $people_dir ) {
    // Old team_member slugs that differ from the new /people/ slug.
    $profile_slugs = array(
        'martien-de-jong' => 'martien',
        'frank-kobaai'    => 'frank',
    );
    if ( preg_match( '#^(?:members|team_member)/([a-z0-9-]+)$#', prospergenics_2026_normalize_path( $request_path ), $m ) ) {
        $slug = isset( $profile_slugs[ $m[1] ] ) ? $profile_slugs[ $m[1] ] : $m[1];
        if ( file_exists( rtrim( $people_dir, '/' ) . '/' . $slug . '/index.html' ) ) {
            return '/people/' . $slug . '/';
        }
    }
    return null;
}

function prospergenics_2026_legacy_url_redirects() {
    if ( ! is_404() ) {
        return;
    }
    $request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
    $target = prospergenics_2026_match_redirect( $request_path, prospergenics_2026_redirect_rules() );
    if ( null === $target ) {
        $target = prospergenics_2026_profile_redirect( $request_path, ABSPATH . 'people' );
    }
    if ( null !== $target ) {
        wp_safe_redirect( 0 === strpos( $target, '/' ) ? home_url( $target ) : $target, 301 );
        exit;
    }
}
// Priority 1: before core's redirect_canonical() (priority 10), whose "guess a page for this 404"
// sent /team_member/lessy/ to the old /lessy-2/ page instead of /people/lessy/ (audit CON-4).
add_action( 'template_redirect', 'prospergenics_2026_legacy_url_redirects', 1 );

/** The always-on person rules from person-redirects.txt, read once per request. */
function prospergenics_2026_person_redirect_rules() {
    static $rules = null;
    if ( null === $rules ) {
        $file  = apply_filters( 'prospergenics_2026_person_redirects_file', __DIR__ . '/person-redirects.txt' );
        $rules = is_readable( $file ) ? prospergenics_2026_parse_redirects( file_get_contents( $file ) ) : array();
    }
    return $rules;
}

/** The always-on rules for legacy pages still published in the database (legacy-pages.txt, #6488/#6520). */
function prospergenics_2026_legacy_page_rules() {
    static $rules = null;
    if ( null === $rules ) {
        $file  = apply_filters( 'prospergenics_2026_legacy_pages_file', __DIR__ . '/legacy-pages.txt' );
        $rules = is_readable( $file ) ? prospergenics_2026_parse_redirects( file_get_contents( $file ) ) : array();
    }
    return $rules;
}

/**
 * The approved page for a person URL that no rule names exactly (card #7756), or null:
 *   - /team_member/<slug>/: the old site's profile post type, which this site doesn't have, so
 *     WordPress read /team_member/lessy/ as the media attachment "lessy" (Lessy.jpeg); it goes to
 *     /people/<slug>/ when that profile exists, as on production;
 *   - a person URL with a feed or embed ending (/martien/feed/, /people/lessy/feed/,
 *     /team_member/lessy/feed/rss2/), which WordPress also landed on the photo: the person's page.
 *     The URL without the ending is a person URL when a person rule names it, it is a
 *     /team_member/<slug>/ as above, or it is a published /people/<slug>/ profile.
 *
 * @param array  $person_rules The rules from person-redirects.txt.
 * @param string $people_dir   Folder with the static profiles (<slug>/index.html).
 * @return string|null
 */
function prospergenics_2026_person_url_target( $request_path, array $person_rules, $people_dir ) {
    $path = prospergenics_2026_normalize_path( $request_path );
    $base = preg_replace( '#/(?:feed(?:/(?:feed|rdf|rss|rss2|atom))?|rdf|rss|rss2|atom|embed|trackback)$#', '', $path );
    if ( 0 === strpos( $base, 'team_member/' ) ) {
        return prospergenics_2026_profile_redirect( $base, $people_dir );
    }
    if ( $base === $path || '' === $base ) {
        return null; // no ending: an exact person rule would have matched already
    }
    $target = prospergenics_2026_match_redirect( $base, $person_rules );
    if ( null === $target && preg_match( '#^people/([a-z0-9-]+)$#', $base, $m ) && file_exists( rtrim( $people_dir, '/' ) . '/' . $m[1] . '/index.html' ) ) {
        $target = '/people/' . $m[1] . '/';
    }
    return $target;
}

/**
 * Person URLs that other parts of the site still answer with outdated copies of a profile (the
 * jengo-listings /members/<slug>/ pages, imported -2 pages, media attachments), and legacy pages
 * imported from the old site: send them to the approved page, on init, before a plugin or the
 * still-published legacy page renders them.
 */
function prospergenics_2026_person_redirects() {
    $method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( (string) $_SERVER['REQUEST_METHOD'] ) : 'GET';
    if ( is_admin() || ! in_array( $method, array( 'GET', 'HEAD' ), true ) ) {
        return;
    }
    $request_path = isset( $_SERVER['REQUEST_URI'] ) ? (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) : '';
    $target = prospergenics_2026_match_redirect( $request_path, prospergenics_2026_person_redirect_rules() );
    if ( null === $target ) {
        $target = prospergenics_2026_match_redirect( $request_path, prospergenics_2026_legacy_page_rules() );
    }
    if ( null === $target ) {
        $target = prospergenics_2026_person_url_target( $request_path, prospergenics_2026_person_redirect_rules(), ABSPATH . 'people' );
    }
    if ( null !== $target ) {
        wp_safe_redirect( home_url( $target ), 301 );
        exit;
    }
}
add_action( 'init', 'prospergenics_2026_person_redirects', 0 );

/**
 * WordPress' own canonical redirect (redirect_canonical(), which also guesses a page for a 404)
 * can land on a URL the rules above redirect again: /home/ was guessed to /home-legacy/, and /join/
 * to /join-our-community/, which goes back to /join/ (a loop until /join/ has its own route). Go
 * to the rule's target in one hop instead, and never back to the URL that was asked for (pure).
 *
 * @return string|false the URL to redirect to, or false for no redirect
 */
function prospergenics_2026_canonical_redirect_target( $redirect_url, $requested_url, array $rule_sets ) {
    if ( ! $redirect_url ) {
        return $redirect_url;
    }
    $target = null;
    foreach ( $rule_sets as $rules ) {
        $target = prospergenics_2026_match_redirect( (string) parse_url( (string) $redirect_url, PHP_URL_PATH ), $rules );
        if ( null !== $target ) {
            break;
        }
    }
    if ( null === $target ) {
        return $redirect_url;
    }
    $asked = prospergenics_2026_normalize_path( (string) parse_url( (string) $requested_url, PHP_URL_PATH ) );
    return prospergenics_2026_normalize_path( $target ) === $asked ? false : home_url( $target );
}
add_filter( 'redirect_canonical', function ( $redirect_url, $requested_url ) {
    return prospergenics_2026_canonical_redirect_target( $redirect_url, $requested_url, array( prospergenics_2026_person_redirect_rules(), prospergenics_2026_legacy_page_rules() ) );
}, 20, 2 );

/**
 * Whether a path is a person URL (pure): under /people/, /team_member/, /members/ or /author/, or
 * starting with a one-part person rule's old path (/martien/..., /lessy-2/...).
 *
 * @param array $person_rules The rules from person-redirects.txt.
 */
function prospergenics_2026_is_person_path( $path, array $person_rules ) {
    $first = strtok( prospergenics_2026_normalize_path( $path ), '/' );
    return false !== $first && ( in_array( $first, array( 'people', 'team_member', 'members', 'author' ), true ) || isset( $person_rules[ $first ] ) );
}

/**
 * Whether WordPress' canonical redirect would send a person URL to a file in the uploads folder
 * (pure, card #7756): its 404 guess (/martien/feed/ was guessed to the attachment "martien") or
 * the redirect of an attachment page to its file now that attachment pages are off
 * (/team_member/lessy/ was read as the attachment "lessy"), both ending on Lessy.jpeg. The rules
 * above send the known person URLs to their page first; this catches the rest.
 *
 * @param string $uploads_path Path of the uploads folder's URL, e.g. '/wp-content/uploads'.
 */
function prospergenics_2026_person_upload_redirect( $redirect_url, $requested_url, array $person_rules, $uploads_path ) {
    $to      = (string) parse_url( (string) $redirect_url, PHP_URL_PATH );
    $uploads = rtrim( (string) $uploads_path, '/' ) . '/';
    return '/' !== $uploads && 0 === strpos( $to, $uploads )
        && prospergenics_2026_is_person_path( (string) parse_url( (string) $requested_url, PHP_URL_PATH ), $person_rules );
}

// Before the hop-saving filter above (priority 20). Such a request gets the 404 page instead: the
// attachment it was read as is the person's photo, not a page of theirs.
add_filter( 'redirect_canonical', function ( $redirect_url, $requested_url ) {
    $uploads = wp_get_upload_dir();
    if ( ! $redirect_url || ! prospergenics_2026_person_upload_redirect( $redirect_url, $requested_url, prospergenics_2026_person_redirect_rules(), (string) parse_url( $uploads['baseurl'], PHP_URL_PATH ) ) ) {
        return $redirect_url;
    }
    global $wp_query;
    $wp_query->set_404();
    status_header( 404 );
    nocache_headers();
    return false;
}, 10, 2 );

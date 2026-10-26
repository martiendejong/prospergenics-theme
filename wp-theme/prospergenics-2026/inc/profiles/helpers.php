<?php
/**
 * Profiles track: pure helpers (no database access), so the rules can be tested standalone.
 *
 * Privacy levels, slugs, entry validation and sorting, the completion score, featured items and
 * the link-preview parser all live here; data.php and rest.php call them.
 */

defined( 'ABSPATH' ) || exit;

/** Visibility levels, most open first. */
function prospergenics_profiles_levels() {
    return array(
        'public'      => 'Everyone',
        'members'     => 'Logged-in members',
        'connections' => 'Connections (people I follow)',
        'private'     => 'Only me',
    );
}

/**
 * Sections a member can set a visibility for, with their defaults. Privacy by default (audit
 * ARC-10, ARC-11): email and phone are private; the profile itself is only as open as
 * prospergenics_profiles_default_profile_level() says until the member chooses; the other
 * sections follow the profile.
 *
 * @param string $profile The default level of the profile itself.
 */
function prospergenics_profiles_privacy_defaults( $profile = 'members' ) {
    return array(
        'profile'    => isset( prospergenics_profiles_levels()[ $profile ] ) ? $profile : 'members',
        'email'      => 'private',
        'phone'      => 'private',
        'experience' => 'public',
        'education'  => 'public',
        'skills'     => 'public',
        'featured'   => 'public',
    );
}

/**
 * Who sees a profile whose owner never chose (audit ARC-11): nobody is published without
 * consent. Administrators stay private; a member who finished the onboarding wizard is public;
 * everyone else (existing accounts, members still in the wizard) is visible to verified members
 * only. Choosing a level on /profile/settings/ overrides this.
 *
 * @param bool   $is_admin          The owner can manage the site.
 * @param string $onboarding_status none|pending|done
 */
function prospergenics_profiles_default_profile_level( $is_admin, $onboarding_status ) {
    if ( $is_admin ) {
        return 'private';
    }
    return 'done' === $onboarding_status ? 'public' : 'members';
}

/**
 * The one-off migration of stored settings (audit ARC-10): the old settings form always saved
 * the old default 'members' for the email, so a stored 'members' cannot be told apart from
 * "never changed it" and becomes 'private'. Members can open it up again in settings.
 *
 * @param mixed $stored
 * @return mixed The settings to store (unchanged when nothing needs migrating).
 */
function prospergenics_profiles_migrate_privacy( $stored ) {
    if ( is_array( $stored ) && isset( $stored['email'] ) && 'members' === $stored['email'] ) {
        $stored['email'] = 'private';
    }
    return $stored;
}

const PROSPERGENICS_PROFILES_INDEX_MIN = 40;

/**
 * Whether a profile page may be indexed (audit ARC-11): only public profiles that are at least
 * PROSPERGENICS_PROFILES_INDEX_MIN percent complete, so empty pages stay out of search engines.
 */
function prospergenics_profiles_indexable( $profile_level, $completion_percent ) {
    return 'public' === $profile_level && (int) $completion_percent >= PROSPERGENICS_PROFILES_INDEX_MIN;
}

/** Human labels for the privacy sections. */
function prospergenics_profiles_privacy_labels() {
    return array(
        'profile'    => 'Profile (photo, headline, about, follow counts)',
        'email'      => 'Email address',
        'phone'      => 'Phone number',
        'experience' => 'Experience',
        'education'  => 'Education',
        'skills'     => 'Skills',
        'featured'   => 'Featured',
    );
}

/**
 * Merge stored privacy settings over the defaults, dropping unknown sections and levels.
 *
 * @param mixed      $stored   Whatever user meta holds.
 * @param array|null $defaults The owner's defaults (prospergenics_profiles_privacy_defaults()).
 * @return array<string,string>
 */
function prospergenics_profiles_normalize_privacy( $stored, $defaults = null ) {
    $out    = is_array( $defaults ) ? array_merge( prospergenics_profiles_privacy_defaults(), array_intersect_key( $defaults, prospergenics_profiles_privacy_defaults() ) ) : prospergenics_profiles_privacy_defaults();
    $levels = prospergenics_profiles_levels();
    if ( is_array( $stored ) ) {
        foreach ( $out as $section => $default ) {
            if ( isset( $stored[ $section ] ) && is_string( $stored[ $section ] ) && isset( $levels[ $stored[ $section ] ] ) ) {
                $out[ $section ] = $stored[ $section ];
            }
        }
    }
    return $out;
}

/**
 * Whether a viewer may see something shared at $level.
 *
 * 'members' means verified members (audit ARC-10): an account whose email address is not
 * confirmed (anyone can create one once registration opens) sees only what is public.
 *
 * @param string $level           public|members|connections|private
 * @param int    $viewer_id       0 for a logged-out visitor.
 * @param int    $owner_id        The profile owner.
 * @param bool   $is_connection   Whether the owner follows the viewer.
 * @param bool   $viewer_verified Whether the viewer's email address is verified.
 */
function prospergenics_profiles_level_allows( $level, $viewer_id, $owner_id, $is_connection, $viewer_verified = false ) {
    $viewer_id = (int) $viewer_id;
    if ( $viewer_id > 0 && $viewer_id === (int) $owner_id ) {
        return true;
    }
    switch ( $level ) {
        case 'public':
            return true;
        case 'members':
            return $viewer_id > 0 && (bool) $viewer_verified;
        case 'connections':
            return $viewer_id > 0 && (bool) $is_connection;
        default:
            return false;
    }
}

/**
 * Which sections a viewer may see. Every section also needs the profile itself to be visible.
 *
 * @return array<string,bool>
 */
function prospergenics_profiles_visible_sections( array $privacy, $viewer_id, $owner_id, $is_connection, $viewer_verified = false ) {
    $privacy = prospergenics_profiles_normalize_privacy( $privacy );
    $profile = prospergenics_profiles_level_allows( $privacy['profile'], $viewer_id, $owner_id, $is_connection, $viewer_verified );
    $out     = array();
    foreach ( $privacy as $section => $level ) {
        $out[ $section ] = $profile && prospergenics_profiles_level_allows( $level, $viewer_id, $owner_id, $is_connection, $viewer_verified );
    }
    return $out;
}

/**
 * Fixed-window rate limit (profile lookups, uploads). Returns the new window state and whether
 * this hit is allowed.
 *
 * @param array|false $state ['start' => int, 'hits' => int] or false when none.
 * @return array{allowed:bool,state:array{start:int,hits:int},retry:int}
 */
function prospergenics_profiles_rate_hit( $state, $now, $limit, $window ) {
    if ( ! is_array( $state ) || ! isset( $state['start'], $state['hits'] ) || $now - (int) $state['start'] >= $window ) {
        $state = array( 'start' => (int) $now, 'hits' => 0 );
    }
    $allowed = $state['hits'] < $limit;
    if ( $allowed ) {
        $state['hits']++;
    }
    return array( 'allowed' => $allowed, 'state' => $state, 'retry' => max( 1, (int) $state['start'] + (int) $window - (int) $now ) );
}

/**
 * Whether one more upload fits a member's quota (audit ARC-13).
 *
 * @param int $files     Files the member has now.
 * @param int $bytes     Bytes they use now (originals plus generated sizes).
 * @param int $new_bytes Size of the new upload.
 * @return string '' when it fits, else 'files' or 'bytes'.
 */
function prospergenics_profiles_quota_check( $files, $bytes, $new_bytes, $max_files = 20, $max_bytes = 52428800 ) {
    if ( (int) $files >= (int) $max_files ) {
        return 'files';
    }
    if ( (int) $bytes + (int) $new_bytes > (int) $max_bytes ) {
        return 'bytes';
    }
    return '';
}

/**
 * The sender address platform mail uses (audit ARC-14): noreply@ the site's host, without a
 * leading "www.". That domain must publish SPF and DKIM for the server that sends (see
 * DEPLOYMENT_CHECKLIST.md, "Platform mail").
 */
function prospergenics_profiles_default_mail_from( $home_url ) {
    $host = strtolower( (string) parse_url( (string) $home_url, PHP_URL_HOST ) );
    $host = preg_replace( '/^www\./', '', $host );
    return 'noreply@' . ( '' !== $host ? $host : 'localhost' );
}

/**
 * A URL slug from a name: lowercase ASCII letters, digits and single hyphens.
 */
function prospergenics_profiles_slugify( $name ) {
    $name = (string) $name;
    if ( function_exists( 'remove_accents' ) ) {
        $name = remove_accents( $name );
    } elseif ( function_exists( 'iconv' ) ) {
        $t = @iconv( 'UTF-8', 'ASCII//TRANSLIT//IGNORE', $name );
        if ( false !== $t ) {
            $name = $t;
        }
    }
    $slug = strtolower( $name );
    $slug = preg_replace( '/[^a-z0-9]+/', '-', $slug );
    $slug = trim( preg_replace( '/-+/', '-', $slug ), '-' );
    return substr( $slug, 0, 60 );
}

/**
 * The name a slug is built from. Never the login name: admin usernames must not leak through
 * profile URLs (the users endpoint and author archives are hidden for the same reason).
 *
 * @param string $first   First name.
 * @param string $last    Last name.
 * @param string $display Display name.
 * @param string $login   Login name (only used to recognise a display name that is the login).
 * @param string $nicename user_nicename (WordPress' slug of the login).
 * @param int    $user_id Fallback seed.
 */
function prospergenics_profiles_slug_base( $first, $last, $display, $login, $nicename, $user_id ) {
    $full = trim( trim( (string) $first ) . ' ' . trim( (string) $last ) );
    if ( '' !== prospergenics_profiles_slugify( $full ) ) {
        return prospergenics_profiles_slugify( $full );
    }
    $display = trim( (string) $display );
    $is_login = 0 === strcasecmp( $display, (string) $login ) || 0 === strcasecmp( prospergenics_profiles_slugify( $display ), (string) $nicename )
        || false !== strpos( $display, '@' );
    if ( '' !== $display && ! $is_login && '' !== prospergenics_profiles_slugify( $display ) ) {
        return prospergenics_profiles_slugify( $display );
    }
    return 'member-' . substr( md5( 'pg-profile-' . (int) $user_id ), 0, 8 );
}

/** Slugs that would collide with the site's own paths or confuse visitors. */
function prospergenics_profiles_reserved_slugs() {
    return array( 'admin', 'administrator', 'root', 'me', 'settings', 'welcome', 'verify', 'following', 'profile', 'login', 'wp-admin', 'api', 'www', 'support', 'help' );
}

/**
 * The first free slug: $base, $base-2, $base-3, …
 *
 * @param string   $base
 * @param callable $taken fn( string $slug ): bool
 */
function prospergenics_profiles_unique_slug( $base, $taken ) {
    $base = '' === $base ? 'member' : $base;
    if ( in_array( $base, prospergenics_profiles_reserved_slugs(), true ) ) {
        $base .= '-1';
    }
    $slug = $base;
    for ( $i = 2; $taken( $slug ) && $i < 1000; $i++ ) {
        $slug = $base . '-' . $i;
    }
    return $slug;
}

/** Validate a YYYY-MM month; returns it, or '' when invalid. */
function prospergenics_profiles_clean_month( $value ) {
    $value = trim( (string) $value );
    if ( ! preg_match( '/^(\d{4})-(\d{2})$/', $value, $m ) ) {
        return '';
    }
    $y = (int) $m[1];
    $mo = (int) $m[2];
    if ( $y < 1940 || $y > (int) gmdate( 'Y' ) + 10 || $mo < 1 || $mo > 12 ) {
        return '';
    }
    return sprintf( '%04d-%02d', $y, $mo );
}

/** Validate a year; returns it as int, or 0 when empty/invalid. */
function prospergenics_profiles_clean_year( $value ) {
    $value = trim( (string) $value );
    if ( ! preg_match( '/^\d{4}$/', $value ) ) {
        return 0;
    }
    $y = (int) $value;
    return ( $y >= 1940 && $y <= (int) gmdate( 'Y' ) + 10 ) ? $y : 0;
}

/** Trim a single-line text field to $max characters. */
function prospergenics_profiles_clip( $text, $max ) {
    $text = trim( preg_replace( '/\s+/u', ' ', (string) $text ) );
    return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
}

/**
 * Validate an experience entry. Input values must already be sanitised text.
 *
 * @return array{0:array,1:array} [ clean fields, errors field => message ]
 */
function prospergenics_profiles_validate_experience( array $in ) {
    $out = array(
        'title'       => prospergenics_profiles_clip( isset( $in['title'] ) ? $in['title'] : '', 120 ),
        'company'     => prospergenics_profiles_clip( isset( $in['company'] ) ? $in['company'] : '', 120 ),
        'start'       => prospergenics_profiles_clean_month( isset( $in['start'] ) ? $in['start'] : '' ),
        'end'         => prospergenics_profiles_clean_month( isset( $in['end'] ) ? $in['end'] : '' ),
        'current'     => ! empty( $in['current'] ) && 'false' !== $in['current'] && '0' !== $in['current'],
        'description' => trim( (string) ( isset( $in['description'] ) ? $in['description'] : '' ) ),
    );
    $out['description'] = function_exists( 'mb_substr' ) ? mb_substr( $out['description'], 0, 2000 ) : substr( $out['description'], 0, 2000 );
    $errors = array();
    if ( '' === $out['title'] ) {
        $errors['title'] = 'Enter a job title.';
    }
    if ( '' === $out['company'] ) {
        $errors['company'] = 'Enter a company or organisation.';
    }
    if ( '' === $out['start'] ) {
        $errors['start'] = 'Enter a start month.';
    }
    if ( $out['current'] ) {
        $out['end'] = '';
    } elseif ( '' === $out['end'] ) {
        $errors['end'] = 'Enter an end month, or tick "I currently work here".';
    } elseif ( '' !== $out['start'] && strcmp( $out['end'], $out['start'] ) < 0 ) {
        $errors['end'] = 'The end month is before the start month.';
    }
    return array( $out, $errors );
}

/**
 * Validate an education entry.
 *
 * @return array{0:array,1:array}
 */
function prospergenics_profiles_validate_education( array $in ) {
    $out = array(
        'institution' => prospergenics_profiles_clip( isset( $in['institution'] ) ? $in['institution'] : '', 150 ),
        'degree'      => prospergenics_profiles_clip( isset( $in['degree'] ) ? $in['degree'] : '', 120 ),
        'field'       => prospergenics_profiles_clip( isset( $in['field'] ) ? $in['field'] : '', 120 ),
        'start_year'  => prospergenics_profiles_clean_year( isset( $in['start_year'] ) ? $in['start_year'] : '' ),
        'end_year'    => prospergenics_profiles_clean_year( isset( $in['end_year'] ) ? $in['end_year'] : '' ),
    );
    $errors = array();
    if ( '' === $out['institution'] ) {
        $errors['institution'] = 'Enter a school or institution.';
    }
    if ( $out['start_year'] && $out['end_year'] && $out['end_year'] < $out['start_year'] ) {
        $errors['end_year'] = 'The end year is before the start year.';
    }
    return array( $out, $errors );
}

/** A skill name: one line, at most 60 characters. */
function prospergenics_profiles_clean_skill( $name ) {
    return prospergenics_profiles_clip( $name, 60 );
}

/** Experience newest first: by start month, current roles first on a tie. */
function prospergenics_profiles_sort_experience( array $items ) {
    usort( $items, function ( $a, $b ) {
        $c = strcmp( (string) $b['start'], (string) $a['start'] );
        if ( 0 !== $c ) {
            return $c;
        }
        return (int) $b['current'] - (int) $a['current'];
    } );
    return $items;
}

/** Education by end year descending; entries without an end year (ongoing) first. */
function prospergenics_profiles_sort_education( array $items ) {
    usort( $items, function ( $a, $b ) {
        $ea = $a['end_year'] ? (int) $a['end_year'] : PHP_INT_MAX;
        $eb = $b['end_year'] ? (int) $b['end_year'] : PHP_INT_MAX;
        if ( $ea !== $eb ) {
            return $eb <=> $ea;
        }
        return (int) $b['start_year'] <=> (int) $a['start_year'];
    } );
    return $items;
}

/** Skills: pinned first, then most endorsed, then by name. */
function prospergenics_profiles_sort_skills( array $items ) {
    usort( $items, function ( $a, $b ) {
        if ( (bool) $a['pinned'] !== (bool) $b['pinned'] ) {
            return $a['pinned'] ? -1 : 1;
        }
        if ( (int) $a['endorsements'] !== (int) $b['endorsements'] ) {
            return (int) $b['endorsements'] - (int) $a['endorsements'];
        }
        return strcasecmp( $a['name'], $b['name'] );
    } );
    return $items;
}

/** "Mar 2024", or "" for an empty month. */
function prospergenics_profiles_format_month( $ym ) {
    if ( ! preg_match( '/^(\d{4})-(\d{2})$/', (string) $ym, $m ) ) {
        return '';
    }
    $names = array( 'Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec' );
    return $names[ (int) $m[2] - 1 ] . ' ' . $m[1];
}

/**
 * Profile completion (card #6353): photo, headline, experience, skills, education, 20% each.
 *
 * @param array $facts photo, headline, experience, skills, education => bool|int
 * @return array{percent:int, missing:array<string,string>}
 */
function prospergenics_profiles_completion( array $facts ) {
    $parts = array(
        'photo'      => 'Add a profile photo',
        'headline'   => 'Add a headline',
        'experience' => 'Add your experience',
        'skills'     => 'Add a skill',
        'education'  => 'Add your education',
    );
    $done    = 0;
    $missing = array();
    foreach ( $parts as $key => $label ) {
        if ( ! empty( $facts[ $key ] ) ) {
            $done++;
        } else {
            $missing[ $key ] = $label;
        }
    }
    return array(
        'percent' => (int) round( 100 * $done / count( $parts ) ),
        'missing' => $missing,
    );
}

/** Featured item types. */
function prospergenics_profiles_featured_types() {
    return array( 'article' => 'Article', 'link' => 'Link', 'media' => 'Media' );
}

/** An http(s) URL or ''. */
function prospergenics_profiles_clean_url( $url ) {
    $url = trim( (string) $url );
    if ( '' === $url || ! preg_match( '#^https?://[^\s/$.?\#][^\s]*$#i', $url ) || strlen( $url ) > 2000 ) {
        return '';
    }
    return $url;
}

/**
 * Normalise the featured list: at most 3 items, known types, http(s) URLs only.
 *
 * @param mixed $items
 * @return array<int,array{type:string,url:string,title:string,image:string,attachment_id:int}>
 */
function prospergenics_profiles_normalize_featured( $items ) {
    $out = array();
    if ( ! is_array( $items ) ) {
        return $out;
    }
    $types = prospergenics_profiles_featured_types();
    foreach ( $items as $item ) {
        if ( ! is_array( $item ) || count( $out ) >= 3 ) {
            continue;
        }
        $type = isset( $item['type'] ) && is_string( $item['type'] ) && isset( $types[ $item['type'] ] ) ? $item['type'] : '';
        $url  = prospergenics_profiles_clean_url( isset( $item['url'] ) ? $item['url'] : '' );
        if ( '' === $type || '' === $url ) {
            continue;
        }
        $out[] = array(
            'type'          => $type,
            'url'           => $url,
            'title'         => prospergenics_profiles_clip( isset( $item['title'] ) ? $item['title'] : '', 140 ),
            'image'         => prospergenics_profiles_clean_url( isset( $item['image'] ) ? $item['image'] : '' ),
            'attachment_id' => isset( $item['attachment_id'] ) ? max( 0, (int) $item['attachment_id'] ) : 0,
        );
    }
    return $out;
}

/**
 * Read og:title / og:image (falling back to <title>) from an HTML document.
 *
 * @param string $html
 * @param string $base_url The page URL, to resolve a relative og:image.
 * @return array{title:string,image:string}
 */
function prospergenics_profiles_parse_preview( $html, $base_url ) {
    $html  = substr( (string) $html, 0, 300000 );
    $title = '';
    $image = '';
    if ( preg_match_all( '/<meta\b[^>]*>/i', $html, $tags ) ) {
        foreach ( $tags[0] as $tag ) {
            if ( ! preg_match( '/\b(?:property|name)\s*=\s*["\']([^"\']+)["\']/i', $tag, $p ) ) {
                continue;
            }
            if ( ! preg_match( '/\bcontent\s*=\s*("([^"]*)"|\'([^\']*)\')/i', $tag, $c ) ) {
                continue;
            }
            $content = isset( $c[3] ) && '' !== $c[3] ? $c[3] : $c[2];
            $prop    = strtolower( trim( $p[1] ) );
            if ( ( 'og:title' === $prop || ( 'twitter:title' === $prop && '' === $title ) ) && '' !== trim( $content ) ) {
                $title = $content;
            }
            if ( ( 'og:image' === $prop || 'og:image:url' === $prop || ( 'twitter:image' === $prop && '' === $image ) ) && '' !== trim( $content ) ) {
                $image = $content;
            }
        }
    }
    if ( '' === $title && preg_match( '#<title[^>]*>(.*?)</title>#is', $html, $t ) ) {
        $title = $t[1];
    }
    $title = prospergenics_profiles_clip( html_entity_decode( strip_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ), 140 );
    $image = html_entity_decode( trim( $image ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
    if ( preg_match( '#^[a-z][a-z0-9+.\-]*:#i', $image ) && ! preg_match( '#^https?://#i', $image ) ) {
        $image = ''; // javascript:, data:, … never
    }
    if ( '' !== $image && ! preg_match( '#^https?://#i', $image ) ) {
        $parts = parse_url( (string) $base_url );
        if ( ! empty( $parts['scheme'] ) && ! empty( $parts['host'] ) ) {
            $origin = $parts['scheme'] . '://' . $parts['host'] . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' );
            if ( 0 === strpos( $image, '//' ) ) {
                $image = $parts['scheme'] . ':' . $image;
            } elseif ( 0 === strpos( $image, '/' ) ) {
                $image = $origin . $image;
            } else {
                $dir   = isset( $parts['path'] ) ? preg_replace( '#/[^/]*$#', '/', $parts['path'] ) : '/';
                $image = $origin . $dir . $image;
            }
        }
    }
    return array(
        'title' => $title,
        'image' => prospergenics_profiles_clean_url( $image ),
    );
}

/**
 * A verification token check: matches the stored hash and has not expired.
 *
 * @param string $token       What came in the link.
 * @param string $stored_hash What user meta holds.
 * @param int    $expires     Unix time.
 * @param int    $now         Unix time.
 * @param callable $hash      fn( string ): string
 */
function prospergenics_profiles_token_valid( $token, $stored_hash, $expires, $now, $hash ) {
    $token = (string) $token;
    if ( '' === $token || '' === (string) $stored_hash || strlen( $token ) > 200 ) {
        return false;
    }
    if ( (int) $expires < (int) $now ) {
        return false;
    }
    return hash_equals( (string) $stored_hash, (string) $hash( $token ) );
}

/**
 * Onboarding state (card #6340), normalised.
 *
 * @param mixed $stored
 * @return array{status:string,step:int,steps:array<int,string>,shown:bool}
 */
function prospergenics_profiles_normalize_onboarding( $stored ) {
    $out = array( 'status' => 'none', 'step' => 1, 'steps' => array(), 'shown' => false );
    if ( ! is_array( $stored ) ) {
        return $out;
    }
    if ( isset( $stored['status'] ) && in_array( $stored['status'], array( 'none', 'pending', 'done' ), true ) ) {
        $out['status'] = $stored['status'];
    }
    if ( isset( $stored['step'] ) ) {
        $out['step'] = min( 3, max( 1, (int) $stored['step'] ) );
    }
    if ( isset( $stored['steps'] ) && is_array( $stored['steps'] ) ) {
        foreach ( $stored['steps'] as $n => $result ) {
            if ( in_array( (int) $n, array( 1, 2, 3 ), true ) && in_array( $result, array( 'saved', 'skipped' ), true ) ) {
                $out['steps'][ (int) $n ] = $result;
            }
        }
    }
    $out['shown'] = ! empty( $stored['shown'] );
    return $out;
}

/**
 * Record a wizard step as saved or skipped and move on; after step 3 the wizard is done.
 */
function prospergenics_profiles_onboarding_advance( array $state, $step, $result ) {
    $state = prospergenics_profiles_normalize_onboarding( $state );
    $step  = min( 3, max( 1, (int) $step ) );
    $state['steps'][ $step ] = 'saved' === $result ? 'saved' : 'skipped';
    if ( $step >= 3 ) {
        $state['status'] = 'done';
        $state['step']   = 3;
    } else {
        $state['step'] = $step + 1;
    }
    return $state;
}

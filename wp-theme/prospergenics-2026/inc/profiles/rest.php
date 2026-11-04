<?php
/**
 * Profiles track: REST API, namespace prospergenics/v1, everything under /profile/.
 *
 * Reads go through prospergenics_profiles_public_data() / the visibility check, so privacy
 * settings apply to the JSON exactly as to the page. Writes are owner-only: logged in (cookie +
 * X-WP-Nonce, which WordPress checks for every cookie-authenticated REST request), the `read`
 * capability, and ownership of the entry being changed.
 *
 *   GET            /profile/users/{slug}                 profile as the viewer may see it (60/hour per account)
 *   POST|DELETE    /profile/users/{slug}/follow          follow / unfollow (verified members)
 *   GET|PUT        /profile/me                           own basic fields
 *   GET|PUT        /profile/privacy                      own privacy settings
 *   GET            /profile/completion                   own completion score
 *   GET            /profile/following                    whom I follow, who follows me
 *   GET|POST       /profile/{experience|education|skills}            list (?user=slug) / create
 *   GET|PUT|DELETE /profile/{experience|education|skills}/{id}       one entry
 *   POST|DELETE    /profile/skills/{id}/endorse          endorse / withdraw (verified members)
 *   GET            /profile/skills/suggest?q=            autocomplete from the vocabulary
 *   POST|DELETE    /profile/photo                        upload (multipart "photo") / remove
 *   GET|PUT        /profile/featured                     own featured list (max 3, ordered)
 *   POST           /profile/featured/preview             og:title + og:image of a URL
 *   POST           /profile/featured/media               upload an image for a media item (verified)
 *   POST           /profile/notifications/read           mark notifications read
 *   POST           /profile/verify/resend                resend the magic link
 *   POST           /profile/onboarding                   save or skip a wizard step
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROFILES_REST_NS = 'prospergenics/v1';
// A logged-in account may look up at most 60 profiles an hour through the API (audit ARC-10:
// no harvesting of member data; the profile pages themselves don't call this endpoint).
const PROSPERGENICS_PROFILES_LOOKUP_LIMIT  = 60;
const PROSPERGENICS_PROFILES_LOOKUP_WINDOW = HOUR_IN_SECONDS;

/** REST collection name => post type. */
function prospergenics_profiles_rest_types() {
    return array( 'experience' => 'pg_experience', 'education' => 'pg_education', 'skills' => 'pg_skill' );
}

/* ───────────────────────── permission callbacks ───────────────────────── */

/** Reads are open; what is returned is filtered per viewer by the privacy settings. */
function prospergenics_profiles_can_read() {
    return true;
}

/** A logged-in member editing their own profile. */
function prospergenics_profiles_can_edit_own() {
    if ( ! is_user_logged_in() ) {
        return new WP_Error( 'rest_forbidden', 'Log in to edit your profile.', array( 'status' => 401 ) );
    }
    return current_user_can( 'read' );
}

/** The owner of the entry in the URL (and the entry is of the type in the URL). */
function prospergenics_profiles_can_edit_entry( WP_REST_Request $request ) {
    $ok = prospergenics_profiles_can_edit_own();
    if ( true !== $ok ) {
        return $ok;
    }
    $post = prospergenics_profiles_request_entry( $request );
    if ( ! $post ) {
        return new WP_Error( 'rest_not_found', 'Entry not found.', array( 'status' => 404 ) );
    }
    if ( (int) $post->post_author !== get_current_user_id() ) {
        return new WP_Error( 'rest_forbidden', 'You can only change your own profile.', array( 'status' => 403 ) );
    }
    return true;
}

/** Following and endorsing need a verified email address (card #6339: "full access"). */
function prospergenics_profiles_can_interact() {
    $ok = prospergenics_profiles_can_edit_own();
    if ( true !== $ok ) {
        return new WP_Error( 'rest_forbidden', 'Log in to do this.', array( 'status' => 401 ) );
    }
    if ( ! prospergenics_profiles_is_verified( get_current_user_id() ) ) {
        return new WP_Error( 'rest_forbidden', 'Confirm your email address first: use the link we emailed you.', array( 'status' => 403 ) );
    }
    return true;
}

/**
 * Uploading images for featured items needs a verified email address (audit ARC-13): these
 * files are public as soon as they are featured and cost disk space.
 */
function prospergenics_profiles_can_upload_media() {
    $ok = prospergenics_profiles_can_edit_own();
    if ( true !== $ok ) {
        return $ok;
    }
    if ( ! prospergenics_profiles_is_verified( get_current_user_id() ) ) {
        return new WP_Error( 'rest_forbidden', 'Confirm your email address before uploading images: use the link we emailed you.', array( 'status' => 403 ) );
    }
    return true;
}

/* ───────────────────────── helpers ───────────────────────── */

/** A 429 response with Retry-After. */
function prospergenics_profiles_rest_too_many( $message, $retry ) {
    $response = new WP_REST_Response( array( 'code' => 'pg_profiles_rate_limited', 'message' => $message, 'data' => array( 'status' => 429 ) ), 429 );
    $response->header( 'Retry-After', (string) (int) $retry );
    return $response;
}

function prospergenics_profiles_request_entry( WP_REST_Request $request ) {
    $types = prospergenics_profiles_rest_types();
    $kind  = (string) $request['kind'];
    if ( ! isset( $types[ $kind ] ) ) {
        return null;
    }
    $post = get_post( (int) $request['id'] );
    return ( $post && $types[ $kind ] === $post->post_type && 'publish' === $post->post_status ) ? $post : null;
}

function prospergenics_profiles_rest_error( array $errors ) {
    return new WP_Error( 'pg_profiles_invalid', reset( $errors ), array( 'status' => 400, 'fields' => $errors ) );
}

/** Validate request params for an entry type. @return array{0:array,1:array} */
function prospergenics_profiles_validate_entry_request( $type, WP_REST_Request $request, $existing = null ) {
    $p = function ( $k, $multiline = false ) use ( $request ) {
        $v = $request->get_param( $k );
        if ( is_array( $v ) || is_object( $v ) ) {
            return '';
        }
        return $multiline ? sanitize_textarea_field( (string) $v ) : sanitize_text_field( (string) $v );
    };
    // PUT may send only some fields: start from the stored entry
    $base = $existing ? $existing : array();
    $get  = function ( $k, $multiline = false ) use ( $request, $p, $base ) {
        return null !== $request->get_param( $k ) ? $p( $k, $multiline ) : ( isset( $base[ $k ] ) ? $base[ $k ] : '' );
    };
    if ( 'pg_experience' === $type ) {
        $current = null !== $request->get_param( 'current' ) ? rest_sanitize_boolean( $request->get_param( 'current' ) ) : ! empty( $base['current'] );
        return prospergenics_profiles_validate_experience( array(
            'title'       => $get( 'title' ),
            'company'     => $get( 'company' ),
            'start'       => $get( 'start' ),
            'end'         => $get( 'end' ),
            'current'     => $current,
            'description' => $get( 'description', true ),
        ) );
    }
    if ( 'pg_education' === $type ) {
        return prospergenics_profiles_validate_education( array(
            'institution' => $get( 'institution' ),
            'degree'      => $get( 'degree' ),
            'field'       => $get( 'field' ),
            'start_year'  => (string) $get( 'start_year' ),
            'end_year'    => (string) $get( 'end_year' ),
        ) );
    }
    $name   = prospergenics_profiles_clean_skill( $get( 'name' ) );
    $fields = array( 'name' => $name );
    if ( null !== $request->get_param( 'pinned' ) ) {
        $fields['pinned'] = rest_sanitize_boolean( $request->get_param( 'pinned' ) );
    }
    $errors = '' === $name ? array( 'name' => 'Enter a skill.' ) : array();
    return array( $fields, $errors );
}

/* ───────────────────────── routes ───────────────────────── */

add_action( 'rest_api_init', function () {
    $ns    = PROSPERGENICS_PROFILES_REST_NS;
    $kinds = '(?P<kind>experience|education|skills)';

    register_rest_route( $ns, '/profile/users/(?P<slug>[a-z0-9-]+)', array(
        'methods'             => 'GET',
        'permission_callback' => 'prospergenics_profiles_can_read',
        'callback'            => function ( WP_REST_Request $r ) {
            $viewer = get_current_user_id();
            if ( $viewer ) {
                $key  = '_pg_profiles_lookup_rl_' . $viewer;
                $rate = prospergenics_profiles_rate_hit( get_transient( $key ), time(), PROSPERGENICS_PROFILES_LOOKUP_LIMIT, PROSPERGENICS_PROFILES_LOOKUP_WINDOW );
                set_transient( $key, $rate['state'], PROSPERGENICS_PROFILES_LOOKUP_WINDOW );
                if ( ! $rate['allowed'] ) {
                    return prospergenics_profiles_rest_too_many( 'Too many profile lookups. Try again later.', $rate['retry'] );
                }
            }
            $user = prospergenics_profiles_user_by_slug( $r['slug'] );
            if ( ! $user ) {
                return new WP_Error( 'rest_not_found', 'No member with this profile URL.', array( 'status' => 404 ) );
            }
            $data = prospergenics_profiles_public_data( $user, $viewer );
            $response = rest_ensure_response( $data );
            $response->header( 'Cache-Control', 'private, no-store' );
            return $response;
        },
    ) );

    register_rest_route( $ns, '/profile/users/(?P<slug>[a-z0-9-]+)/follow', array(
        array(
            'methods'             => 'POST',
            'permission_callback' => 'prospergenics_profiles_can_interact',
            'callback'            => 'prospergenics_profiles_rest_follow',
        ),
        array(
            'methods'             => 'DELETE',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => 'prospergenics_profiles_rest_follow',
        ),
    ) );

    register_rest_route( $ns, '/profile/me', array(
        array(
            'methods'             => 'GET',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => function () {
                return prospergenics_profiles_public_data( wp_get_current_user(), get_current_user_id() );
            },
        ),
        array(
            'methods'             => 'PUT,PATCH,POST',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => 'prospergenics_profiles_rest_update_me',
        ),
    ) );

    register_rest_route( $ns, '/profile/privacy', array(
        array(
            'methods'             => 'GET',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => function () {
                return array( 'privacy' => prospergenics_profiles_privacy( get_current_user_id() ), 'levels' => prospergenics_profiles_levels() );
            },
        ),
        array(
            'methods'             => 'PUT,PATCH,POST',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => function ( WP_REST_Request $r ) {
                $uid     = get_current_user_id();
                $in      = $r->get_param( 'privacy' );
                $in      = is_array( $in ) ? $in : $r->get_params();
                $choices = array();
                foreach ( prospergenics_profiles_privacy( $uid ) as $section => $level ) {
                    if ( isset( $in[ $section ] ) && is_string( $in[ $section ] ) ) {
                        $choices[ $section ] = sanitize_key( $in[ $section ] );
                    }
                }
                // only the sections sent are stored as chosen; the rest keep following the defaults
                prospergenics_profiles_save_privacy( $uid, $choices );
                return array( 'privacy' => prospergenics_profiles_privacy( $uid ) );
            },
        ),
    ) );

    register_rest_route( $ns, '/profile/completion', array(
        'methods'             => 'GET',
        'permission_callback' => 'prospergenics_profiles_can_edit_own',
        'callback'            => function () {
            return prospergenics_profiles_get_completion( get_current_user_id() );
        },
    ) );

    register_rest_route( $ns, '/profile/following', array(
        'methods'             => 'GET',
        'permission_callback' => 'prospergenics_profiles_can_edit_own',
        'callback'            => function () {
            $card = function ( $id ) {
                $u   = get_userdata( $id );
                $vis = prospergenics_profiles_visibility_for( $id, get_current_user_id() );
                return array( 'name' => $vis['profile'] ? $u->display_name : null, 'url' => prospergenics_profiles_url( $id ) );
            };
            $uid = get_current_user_id();
            return array(
                'following' => array_map( $card, prospergenics_profiles_following_ids( $uid ) ),
                'followers' => array_map( $card, prospergenics_profiles_follower_ids( $uid ) ),
            );
        },
    ) );

    register_rest_route( $ns, '/profile/skills/suggest', array(
        'methods'             => 'GET',
        'permission_callback' => 'prospergenics_profiles_can_read',
        'args'                => array( 'q' => array( 'type' => 'string', 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ) ),
        'callback'            => function ( WP_REST_Request $r ) {
            $q = strtolower( trim( (string) $r['q'] ) );
            if ( strlen( $q ) < 1 ) {
                return array();
            }
            $out = array();
            foreach ( prospergenics_profiles_skills_vocabulary() as $s ) {
                if ( false !== strpos( strtolower( $s ), $q ) ) {
                    $out[] = $s;
                }
                if ( count( $out ) >= 10 ) {
                    break;
                }
            }
            return $out;
        },
    ) );

    register_rest_route( $ns, '/profile/' . $kinds, array(
        array(
            'methods'             => 'GET',
            'permission_callback' => 'prospergenics_profiles_can_read',
            'args'                => array( 'user' => array( 'type' => 'string', 'required' => false, 'sanitize_callback' => 'sanitize_title' ) ),
            'callback'            => 'prospergenics_profiles_rest_list_entries',
        ),
        array(
            'methods'             => 'POST',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => 'prospergenics_profiles_rest_create_entry',
        ),
    ) );

    register_rest_route( $ns, '/profile/' . $kinds . '/(?P<id>\d+)', array(
        array(
            'methods'             => 'GET',
            'permission_callback' => 'prospergenics_profiles_can_read',
            'callback'            => function ( WP_REST_Request $r ) {
                $post = prospergenics_profiles_request_entry( $r );
                $types = prospergenics_profiles_rest_types();
                if ( $post ) {
                    $vis = prospergenics_profiles_visibility_for( $post->post_author, get_current_user_id() );
                    if ( ! empty( $vis[ $r['kind'] ] ) ) {
                        return prospergenics_profiles_entry_data( $post, get_current_user_id() );
                    }
                }
                // not visible and not found look the same
                return new WP_Error( 'rest_not_found', 'Entry not found.', array( 'status' => 404 ) );
            },
        ),
        array(
            'methods'             => 'PUT,PATCH',
            'permission_callback' => 'prospergenics_profiles_can_edit_entry',
            'callback'            => 'prospergenics_profiles_rest_update_entry',
        ),
        array(
            'methods'             => 'DELETE',
            'permission_callback' => 'prospergenics_profiles_can_edit_entry',
            'callback'            => function ( WP_REST_Request $r ) {
                $post = prospergenics_profiles_request_entry( $r );
                prospergenics_profiles_delete_entry( $post->ID );
                return array( 'deleted' => true, 'id' => $post->ID );
            },
        ),
    ) );

    register_rest_route( $ns, '/profile/skills/(?P<id>\d+)/endorse', array(
        array(
            'methods'             => 'POST',
            'permission_callback' => 'prospergenics_profiles_can_interact',
            'callback'            => 'prospergenics_profiles_rest_endorse',
        ),
        array(
            'methods'             => 'DELETE',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => 'prospergenics_profiles_rest_endorse',
        ),
    ) );

    register_rest_route( $ns, '/profile/photo', array(
        array(
            'methods'             => 'POST',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => function ( WP_REST_Request $r ) {
                $files = $r->get_file_params();
                $uid   = get_current_user_id();
                $id    = prospergenics_profiles_store_image( isset( $files['photo'] ) ? $files['photo'] : null, $uid, true );
                if ( is_wp_error( $id ) ) {
                    return $id;
                }
                prospergenics_profiles_set_photo( $uid, $id );
                return array( 'id' => $id, 'url' => prospergenics_profiles_photo_url( $uid, 'medium' ) );
            },
        ),
        array(
            'methods'             => 'DELETE',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => function () {
                prospergenics_profiles_set_photo( get_current_user_id(), 0 );
                return array( 'deleted' => true );
            },
        ),
    ) );

    register_rest_route( $ns, '/profile/featured', array(
        array(
            'methods'             => 'GET',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => function () {
                return prospergenics_profiles_featured( get_current_user_id() );
            },
        ),
        array(
            'methods'             => 'PUT,POST',
            'permission_callback' => 'prospergenics_profiles_can_edit_own',
            'callback'            => 'prospergenics_profiles_rest_save_featured',
        ),
    ) );

    register_rest_route( $ns, '/profile/featured/preview', array(
        'methods'             => 'POST',
        'permission_callback' => 'prospergenics_profiles_can_edit_own',
        'args'                => array( 'url' => array( 'type' => 'string', 'required' => true ) ),
        'callback'            => function ( WP_REST_Request $r ) {
            $url = prospergenics_profiles_clean_url( esc_url_raw( (string) $r['url'] ) );
            if ( '' === $url ) {
                return new WP_Error( 'pg_profiles_invalid', 'Enter a full link starting with https://', array( 'status' => 400 ) );
            }
            // a member may fetch at most 30 previews per hour
            $key   = '_pg_profiles_preview_rate_' . get_current_user_id();
            $count = (int) get_transient( $key );
            if ( $count >= 30 ) {
                return new WP_Error( 'pg_profiles_wait', 'Too many link previews; try again later.', array( 'status' => 429 ) );
            }
            set_transient( $key, $count + 1, HOUR_IN_SECONDS );
            return prospergenics_profiles_link_preview( $url );
        },
    ) );

    register_rest_route( $ns, '/profile/featured/media', array(
        'methods'             => 'POST',
        'permission_callback' => 'prospergenics_profiles_can_upload_media',
        'callback'            => function ( WP_REST_Request $r ) {
            $files = $r->get_file_params();
            $id    = prospergenics_profiles_store_image( isset( $files['media'] ) ? $files['media'] : null, get_current_user_id(), false );
            if ( is_wp_error( $id ) ) {
                return $id;
            }
            return array( 'attachment_id' => $id, 'url' => wp_get_attachment_url( $id ), 'image' => (string) wp_get_attachment_image_url( $id, 'medium_large' ) );
        },
    ) );

    register_rest_route( $ns, '/profile/notifications/read', array(
        'methods'             => 'POST',
        'permission_callback' => 'prospergenics_profiles_can_edit_own',
        'callback'            => function () {
            prospergenics_profiles_mark_notifications_read( get_current_user_id() );
            return array( 'ok' => true );
        },
    ) );

    register_rest_route( $ns, '/profile/verify/resend', array(
        'methods'             => 'POST',
        'permission_callback' => 'prospergenics_profiles_can_edit_own',
        'callback'            => function () {
            $r = prospergenics_profiles_resend_verification( get_current_user_id() );
            return is_wp_error( $r ) ? $r : array( 'sent' => true );
        },
    ) );

    register_rest_route( $ns, '/profile/onboarding', array(
        'methods'             => 'POST',
        'permission_callback' => 'prospergenics_profiles_can_edit_own',
        'args'                => array(
            'step'   => array( 'type' => 'integer', 'required' => true, 'minimum' => 1, 'maximum' => 3 ),
            'action' => array( 'type' => 'string', 'required' => true, 'enum' => array( 'save', 'skip' ) ),
        ),
        'callback'            => function ( WP_REST_Request $r ) {
            $result = prospergenics_profiles_onboarding_step( get_current_user_id(), (int) $r['step'], (string) $r['action'], $r->get_params(), $r->get_file_params() );
            if ( is_wp_error( $result ) ) {
                return $result;
            }
            return prospergenics_profiles_onboarding( get_current_user_id() );
        },
    ) );
} );

/* ───────────────────────── callbacks ───────────────────────── */

function prospergenics_profiles_rest_follow( WP_REST_Request $r ) {
    $target = prospergenics_profiles_user_by_slug( $r['slug'] );
    $uid    = get_current_user_id();
    if ( ! $target || ! prospergenics_profiles_visibility_for( $target->ID, $uid )['profile'] ) {
        return new WP_Error( 'rest_not_found', 'No member with this profile URL.', array( 'status' => 404 ) );
    }
    if ( $target->ID === $uid ) {
        return new WP_Error( 'pg_profiles_self', 'You cannot follow yourself.', array( 'status' => 400 ) );
    }
    if ( 'DELETE' === $r->get_method() ) {
        prospergenics_profiles_unfollow( $uid, $target->ID );
    } else {
        prospergenics_profiles_follow( $uid, $target->ID );
    }
    return array(
        'following' => prospergenics_profiles_follows( $uid, $target->ID ),
        'followers' => count( prospergenics_profiles_follower_ids( $target->ID ) ),
    );
}

function prospergenics_profiles_rest_update_me( WP_REST_Request $r ) {
    $uid    = get_current_user_id();
    $fields = array(
        'headline' => 160,
        'location' => 100,
        'phone'    => 40,
    );
    $errors = array();
    foreach ( $fields as $key => $max ) {
        $v = $r->get_param( $key );
        if ( null === $v || is_array( $v ) ) {
            continue;
        }
        $v = prospergenics_profiles_clip( sanitize_text_field( (string) $v ), $max );
        if ( 'phone' === $key && '' !== $v && ! preg_match( '/^[0-9 +().\-\/]{5,40}$/', $v ) ) {
            $errors['phone'] = 'Use digits, spaces and + ( ) - only.';
            continue;
        }
        prospergenics_profiles_set_meta( $uid, $key, $v );
    }
    $about = $r->get_param( 'about' );
    if ( null !== $about && ! is_array( $about ) ) {
        $about = sanitize_textarea_field( (string) $about );
        prospergenics_profiles_set_meta( $uid, 'about', function_exists( 'mb_substr' ) ? mb_substr( $about, 0, 2600 ) : substr( $about, 0, 2600 ) );
    }
    $names = array();
    foreach ( array( 'first_name', 'last_name' ) as $key ) {
        $v = $r->get_param( $key );
        if ( null !== $v && ! is_array( $v ) ) {
            $names[ $key ] = prospergenics_profiles_clip( sanitize_text_field( (string) $v ), 60 );
        }
    }
    if ( $names ) {
        $user  = wp_get_current_user();
        $first = isset( $names['first_name'] ) ? $names['first_name'] : $user->first_name;
        $last  = isset( $names['last_name'] ) ? $names['last_name'] : $user->last_name;
        if ( '' === trim( $first . $last ) ) {
            $errors['first_name'] = 'Enter your name.';
        } else {
            wp_update_user( array(
                'ID'           => $uid,
                'first_name'   => $first,
                'last_name'    => $last,
                'display_name' => trim( $first . ' ' . $last ),
            ) );
            prospergenics_profiles_invalidate( $uid );
        }
    }
    if ( $errors ) {
        return prospergenics_profiles_rest_error( $errors );
    }
    return prospergenics_profiles_public_data( get_userdata( $uid ), $uid );
}

function prospergenics_profiles_rest_list_entries( WP_REST_Request $r ) {
    $types = prospergenics_profiles_rest_types();
    $kind  = (string) $r['kind'];
    $viewer = get_current_user_id();
    $user  = $r['user'] ? prospergenics_profiles_user_by_slug( $r['user'] ) : ( $viewer ? wp_get_current_user() : null );
    if ( ! $user ) {
        return new WP_Error( 'rest_not_found', 'Pass ?user=<profile slug>.', array( 'status' => 404 ) );
    }
    $vis = prospergenics_profiles_visibility_for( $user->ID, $viewer );
    if ( empty( $vis[ $kind ] ) ) {
        return new WP_Error( 'rest_forbidden', 'This section is not visible to you.', array( 'status' => $viewer ? 403 : 401 ) );
    }
    return prospergenics_profiles_entries( $user->ID, $types[ $kind ], $viewer );
}

function prospergenics_profiles_rest_create_entry( WP_REST_Request $r ) {
    $types = prospergenics_profiles_rest_types();
    $type  = $types[ (string) $r['kind'] ];
    $uid   = get_current_user_id();
    if ( prospergenics_profiles_entry_count( $uid, $type ) >= PROSPERGENICS_PROFILES_MAX_ENTRIES ) {
        return new WP_Error( 'pg_profiles_limit', 'You have reached the maximum number of entries.', array( 'status' => 400 ) );
    }
    list( $fields, $errors ) = prospergenics_profiles_validate_entry_request( $type, $r );
    if ( $errors ) {
        return prospergenics_profiles_rest_error( $errors );
    }
    if ( 'pg_skill' === $type ) {
        foreach ( prospergenics_profiles_entries( $uid, 'pg_skill' ) as $s ) {
            if ( 0 === strcasecmp( $s['name'], $fields['name'] ) ) {
                return prospergenics_profiles_rest_error( array( 'name' => 'This skill is already on your profile.' ) );
            }
        }
        if ( ! empty( $fields['pinned'] ) && prospergenics_profiles_pinned_count( $uid ) >= 3 ) {
            $fields['pinned'] = false;
        }
    }
    $id = prospergenics_profiles_save_entry( $uid, $type, $fields );
    if ( is_wp_error( $id ) ) {
        return $id;
    }
    $response = rest_ensure_response( prospergenics_profiles_entry_data( $id, $uid ) );
    $response->set_status( 201 );
    return $response;
}

function prospergenics_profiles_pinned_count( $uid ) {
    return count( array_filter( prospergenics_profiles_entries( $uid, 'pg_skill' ), function ( $s ) {
        return $s['pinned'];
    } ) );
}

function prospergenics_profiles_rest_update_entry( WP_REST_Request $r ) {
    $post     = prospergenics_profiles_request_entry( $r );
    $uid      = get_current_user_id();
    $existing = prospergenics_profiles_entry_data( $post, $uid );
    list( $fields, $errors ) = prospergenics_profiles_validate_entry_request( $post->post_type, $r, $existing );
    if ( $errors ) {
        return prospergenics_profiles_rest_error( $errors );
    }
    if ( 'pg_skill' === $post->post_type ) {
        foreach ( prospergenics_profiles_entries( $uid, 'pg_skill' ) as $s ) {
            if ( $s['id'] !== $post->ID && 0 === strcasecmp( $s['name'], $fields['name'] ) ) {
                return prospergenics_profiles_rest_error( array( 'name' => 'This skill is already on your profile.' ) );
            }
        }
        if ( ! empty( $fields['pinned'] ) && ! $existing['pinned'] && prospergenics_profiles_pinned_count( $uid ) >= 3 ) {
            return prospergenics_profiles_rest_error( array( 'pinned' => 'You can pin up to 3 skills. Unpin one first.' ) );
        }
    }
    $id = prospergenics_profiles_save_entry( $uid, $post->post_type, $fields, $post->ID );
    if ( is_wp_error( $id ) ) {
        return $id;
    }
    return prospergenics_profiles_entry_data( $id, $uid );
}

function prospergenics_profiles_rest_endorse( WP_REST_Request $r ) {
    $skill = get_post( (int) $r['id'] );
    $uid   = get_current_user_id();
    if ( ! $skill || 'pg_skill' !== $skill->post_type || 'publish' !== $skill->post_status ) {
        return new WP_Error( 'rest_not_found', 'Skill not found.', array( 'status' => 404 ) );
    }
    $vis = prospergenics_profiles_visibility_for( $skill->post_author, $uid );
    if ( empty( $vis['skills'] ) ) {
        return new WP_Error( 'rest_not_found', 'Skill not found.', array( 'status' => 404 ) );
    }
    if ( 'DELETE' === $r->get_method() ) {
        $count = prospergenics_profiles_unendorse( $skill->ID, $uid );
    } else {
        $count = prospergenics_profiles_endorse( $skill->ID, $uid );
        if ( is_wp_error( $count ) ) {
            return $count;
        }
    }
    return array( 'id' => $skill->ID, 'endorsements' => $count, 'endorsed_by_me' => 'DELETE' !== $r->get_method() );
}

function prospergenics_profiles_rest_save_featured( WP_REST_Request $r ) {
    $uid   = get_current_user_id();
    $items = $r->get_param( 'items' );
    if ( ! is_array( $items ) ) {
        return new WP_Error( 'pg_profiles_invalid', 'Send the list as items[].', array( 'status' => 400 ) );
    }
    if ( count( $items ) > 3 ) {
        return new WP_Error( 'pg_profiles_invalid', 'You can feature up to 3 items.', array( 'status' => 400 ) );
    }
    $clean = array();
    foreach ( $items as $item ) {
        if ( ! is_array( $item ) ) {
            continue;
        }
        $item = array(
            'type'          => isset( $item['type'] ) ? sanitize_key( (string) $item['type'] ) : '',
            'url'           => isset( $item['url'] ) ? esc_url_raw( (string) $item['url'], array( 'http', 'https' ) ) : '',
            'title'         => isset( $item['title'] ) ? sanitize_text_field( (string) $item['title'] ) : '',
            'image'         => isset( $item['image'] ) ? esc_url_raw( (string) $item['image'], array( 'http', 'https' ) ) : '',
            'attachment_id' => isset( $item['attachment_id'] ) ? (int) $item['attachment_id'] : 0,
        );
        if ( 'media' === $item['type'] ) {
            // a media item must be an image this member uploaded
            $att = $item['attachment_id'];
            if ( ! $att || 'attachment' !== get_post_type( $att ) || (int) get_post_meta( $att, '_pg_profiles_owner', true ) !== $uid ) {
                return new WP_Error( 'pg_profiles_invalid', 'Upload the image for a media item first.', array( 'status' => 400 ) );
            }
            $item['url']   = (string) wp_get_attachment_url( $att );
            $item['image'] = (string) wp_get_attachment_image_url( $att, 'medium_large' );
        } else {
            $item['attachment_id'] = 0;
            if ( '' === prospergenics_profiles_clean_url( $item['url'] ) ) {
                return new WP_Error( 'pg_profiles_invalid', 'Enter a full link starting with https://', array( 'status' => 400 ) );
            }
            if ( '' === $item['title'] || '' === $item['image'] ) {
                $preview = prospergenics_profiles_link_preview( $item['url'] );
                $item['title'] = '' !== $item['title'] ? $item['title'] : $preview['title'];
                $item['image'] = '' !== $item['image'] ? $item['image'] : $preview['image'];
            }
            if ( '' === $item['title'] ) {
                $item['title'] = (string) wp_parse_url( $item['url'], PHP_URL_HOST );
            }
        }
        $clean[] = $item;
    }
    $clean = prospergenics_profiles_normalize_featured( $clean );
    // media no longer featured: remove the uploaded images
    $keep = wp_list_pluck( $clean, 'attachment_id' );
    foreach ( prospergenics_profiles_featured( $uid ) as $old ) {
        if ( $old['attachment_id'] && ! in_array( $old['attachment_id'], $keep, true ) && (int) get_post_meta( $old['attachment_id'], '_pg_profiles_owner', true ) === $uid ) {
            wp_delete_attachment( $old['attachment_id'], true );
        }
    }
    prospergenics_profiles_set_meta( $uid, 'featured', $clean );
    return $clean;
}

/**
 * One wizard step (also used by the no-JavaScript form on /profile/welcome/).
 *
 * @return true|WP_Error
 */
function prospergenics_profiles_onboarding_step( $uid, $step, $action, array $params, array $files ) {
    $state = prospergenics_profiles_onboarding( $uid );
    if ( 'save' === $action ) {
        if ( 1 === $step ) {
            $headline = prospergenics_profiles_clip( sanitize_text_field( (string) ( isset( $params['headline'] ) && ! is_array( $params['headline'] ) ? $params['headline'] : '' ) ), 160 );
            $location = prospergenics_profiles_clip( sanitize_text_field( (string) ( isset( $params['location'] ) && ! is_array( $params['location'] ) ? $params['location'] : '' ) ), 100 );
            if ( '' === $headline ) {
                return new WP_Error( 'pg_profiles_invalid', 'Enter a headline, or skip this step.', array( 'status' => 400, 'fields' => array( 'headline' => 'Enter a headline, or skip this step.' ) ) );
            }
            prospergenics_profiles_set_meta( $uid, 'headline', $headline );
            prospergenics_profiles_set_meta( $uid, 'location', $location );
        } elseif ( 2 === $step ) {
            if ( empty( $files['photo'] ) || UPLOAD_ERR_NO_FILE === (int) $files['photo']['error'] ) {
                if ( ! prospergenics_profiles_photo_id( $uid ) ) {
                    return new WP_Error( 'pg_profiles_invalid', 'Choose a photo, or skip this step.', array( 'status' => 400, 'fields' => array( 'photo' => 'Choose a photo, or skip this step.' ) ) );
                }
            } else {
                $id = prospergenics_profiles_store_image( $files['photo'], $uid, true );
                if ( is_wp_error( $id ) ) {
                    return $id;
                }
                prospergenics_profiles_set_photo( $uid, $id );
            }
        } else {
            $name = prospergenics_profiles_clean_skill( sanitize_text_field( (string) ( isset( $params['skill'] ) && ! is_array( $params['skill'] ) ? $params['skill'] : '' ) ) );
            if ( '' === $name ) {
                return new WP_Error( 'pg_profiles_invalid', 'Enter a skill, or skip this step.', array( 'status' => 400, 'fields' => array( 'skill' => 'Enter a skill, or skip this step.' ) ) );
            }
            $exists = false;
            foreach ( prospergenics_profiles_entries( $uid, 'pg_skill' ) as $s ) {
                $exists = $exists || 0 === strcasecmp( $s['name'], $name );
            }
            if ( ! $exists ) {
                $r = prospergenics_profiles_save_entry( $uid, 'pg_skill', array( 'name' => $name ) );
                if ( is_wp_error( $r ) ) {
                    return $r;
                }
            }
        }
    }
    prospergenics_profiles_save_onboarding( $uid, prospergenics_profiles_onboarding_advance( $state, $step, 'save' === $action ? 'saved' : 'skipped' ) );
    return true;
}

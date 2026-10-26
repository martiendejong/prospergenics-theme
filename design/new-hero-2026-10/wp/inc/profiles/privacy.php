<?php
/**
 * Profiles track: personal data (audit ARC-12, GDPR art. 15, 17 and 20).
 *
 * - Tools → Export Personal Data / Erase Personal Data: an exporter and an eraser for the profile
 *   (meta, photo and featured uploads, experience, education, skills), follows, endorsements given
 *   and the on-site notifications. The courses track (enrolments, certificates) and the articles
 *   track (claps) register their own in inc/courses/privacy.php and inc/articles/claps.php.
 * - Deleting a WordPress user: before the deletion the member's own records (entries, uploads) are
 *   removed, so "attribute all content to" never hands someone's profile to another account; after
 *   it, the references other members hold (endorsements, follows, notifications) are cleaned up.
 * - "Download my data" and "Delete my account" on /profile/settings/ run every registered exporter
 *   and eraser (WordPress' own included) for the logged-in member.
 */

defined( 'ABSPATH' ) || exit;

/* ───────────────────────── export ───────────────────────── */

/** name/value pairs in the shape the privacy tools expect, skipping empty values. */
function prospergenics_profiles_export_pairs( array $pairs ) {
    $out = array();
    foreach ( $pairs as $name => $value ) {
        if ( null === $value || '' === $value || array() === $value ) {
            continue;
        }
        $out[] = array( 'name' => $name, 'value' => is_array( $value ) ? implode( ', ', $value ) : (string) $value );
    }
    return $out;
}

/** The profile exporter. @return array{data:array,done:bool} */
function prospergenics_profiles_personal_data_exporter( $email, $page = 1 ) {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return array( 'data' => array(), 'done' => true );
    }
    $uid    = (int) $user->ID;
    $items  = array();
    $levels = prospergenics_profiles_levels();
    $labels = prospergenics_profiles_privacy_labels();
    $state  = prospergenics_profiles_onboarding( $uid );
    $verified_at = (int) get_user_meta( $uid, '_pg_profiles_email_verified', true );

    $profile = array(
        'Profile URL'        => prospergenics_profiles_url( $uid ),
        'Headline'           => (string) prospergenics_profiles_meta( $uid, 'headline' ),
        'Location'           => (string) prospergenics_profiles_meta( $uid, 'location' ),
        'About'              => (string) prospergenics_profiles_meta( $uid, 'about' ),
        'Phone'              => (string) prospergenics_profiles_meta( $uid, 'phone' ),
        'Profile photo'      => prospergenics_profiles_photo_url( $uid, 'full' ),
        'Email address confirmed' => prospergenics_profiles_is_verified( $uid ) ? ( $verified_at ? gmdate( 'Y-m-d H:i', $verified_at ) . ' UTC' : 'yes' ) : 'not yet',
        'Onboarding'         => $state['status'],
        'Previous profile URLs' => array_map( function ( $s ) {
            return home_url( '/in/' . $s . '/' );
        }, (array) get_user_meta( $uid, '_pg_profiles_old_slug', false ) ),
    );
    foreach ( prospergenics_profiles_privacy( $uid ) as $section => $level ) {
        $profile[ 'Visible to: ' . $labels[ $section ] ] = $levels[ $level ];
    }
    $items[] = array(
        'group_id'          => 'prospergenics-profile',
        'group_label'       => 'Member profile',
        'group_description' => 'Your member profile at /in/… and its privacy settings.',
        'item_id'           => 'pg-profile-' . $uid,
        'data'              => prospergenics_profiles_export_pairs( $profile ),
    );

    foreach ( prospergenics_profiles_featured( $uid ) as $i => $f ) {
        $items[] = array(
            'group_id'          => 'prospergenics-profile-featured',
            'group_label'       => 'Featured items',
            'group_description' => 'Links and images you featured on your profile.',
            'item_id'           => 'pg-featured-' . $uid . '-' . $i,
            'data'              => prospergenics_profiles_export_pairs( array( 'Type' => $f['type'], 'Title' => $f['title'], 'URL' => $f['url'], 'Image' => $f['image'] ) ),
        );
    }

    $groups = array(
        'pg_experience' => array( 'prospergenics-profile-experience', 'Experience' ),
        'pg_education'  => array( 'prospergenics-profile-education', 'Education' ),
        'pg_skill'      => array( 'prospergenics-profile-skills', 'Skills' ),
    );
    foreach ( $groups as $type => $g ) {
        foreach ( prospergenics_profiles_entries( $uid, $type ) as $e ) {
            if ( 'pg_experience' === $type ) {
                $pairs = array( 'Job title' => $e['title'], 'Company' => $e['company'], 'Start' => $e['start'], 'End' => $e['current'] ? 'current' : $e['end'], 'Description' => $e['description'] );
            } elseif ( 'pg_education' === $type ) {
                $pairs = array( 'Institution' => $e['institution'], 'Degree' => $e['degree'], 'Field of study' => $e['field'], 'Start year' => $e['start_year'] ?: '', 'End year' => $e['end_year'] ?: '' );
            } else {
                $pairs = array( 'Skill' => $e['name'], 'Pinned' => $e['pinned'] ? 'yes' : 'no', 'Endorsements received' => (string) $e['endorsements'] );
            }
            $items[] = array(
                'group_id'    => $g[0],
                'group_label' => $g[1],
                'item_id'     => 'pg-entry-' . (int) $e['id'],
                'data'        => prospergenics_profiles_export_pairs( $pairs ),
            );
        }
    }

    foreach ( prospergenics_profiles_following_ids( $uid ) as $id ) {
        $u = get_userdata( $id );
        $items[] = array(
            'group_id'          => 'prospergenics-follows',
            'group_label'       => 'Members you follow',
            'group_description' => 'Following is public to the people you follow; your followers are listed by number only.',
            'item_id'           => 'pg-follow-' . $uid . '-' . $id,
            'data'              => prospergenics_profiles_export_pairs( array( 'Member' => $u ? $u->display_name : '', 'Profile' => prospergenics_profiles_url( $id ) ) ),
        );
    }
    $items[] = array(
        'group_id'    => 'prospergenics-follows',
        'group_label' => 'Members you follow',
        'item_id'     => 'pg-followers-' . $uid,
        'data'        => array( array( 'name' => 'Number of members following you', 'value' => (string) count( prospergenics_profiles_follower_ids( $uid ) ) ) ),
    );

    foreach ( prospergenics_profiles_endorsements_given( $uid ) as $skill ) {
        $owner = get_userdata( (int) $skill->post_author );
        $items[] = array(
            'group_id'          => 'prospergenics-endorsements',
            'group_label'       => 'Endorsements you gave',
            'group_description' => 'Skills of other members you endorsed.',
            'item_id'           => 'pg-endorsement-' . $uid . '-' . (int) $skill->ID,
            'data'              => prospergenics_profiles_export_pairs( array( 'Skill' => $skill->post_title, 'Member' => $owner ? $owner->display_name : '', 'Profile' => $owner ? prospergenics_profiles_url( $owner->ID ) : '' ) ),
        );
    }

    $notes = get_user_meta( $uid, '_pg_profiles_notifications', true );
    foreach ( is_array( $notes ) ? $notes : array() as $i => $n ) {
        if ( ! is_array( $n ) ) {
            continue;
        }
        $from = isset( $n['from'] ) ? get_userdata( (int) $n['from'] ) : null;
        $items[] = array(
            'group_id'          => 'prospergenics-notifications',
            'group_label'       => 'Notifications',
            'group_description' => 'Notifications shown on your profile.',
            'item_id'           => 'pg-notification-' . $uid . '-' . $i,
            'data'              => prospergenics_profiles_export_pairs( array(
                'Type'  => isset( $n['type'] ) ? $n['type'] : '',
                'From'  => $from ? $from->display_name : '',
                'Skill' => isset( $n['skill'] ) ? $n['skill'] : '',
                'Date'  => ! empty( $n['time'] ) ? gmdate( 'Y-m-d H:i', (int) $n['time'] ) . ' UTC' : '',
                'Read'  => empty( $n['read'] ) ? 'no' : 'yes',
            ) ),
        );
    }

    return array( 'data' => $items, 'done' => true );
}

/** Skill posts (of other members) this member endorsed. @return WP_Post[] */
function prospergenics_profiles_endorsements_given( $user_id ) {
    return get_posts( array(
        'post_type'        => 'pg_skill',
        'post_status'      => 'any',
        'posts_per_page'   => -1,
        'meta_key'         => '_pg_profiles_endorser',
        'meta_value'       => (string) (int) $user_id,
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) );
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
    $exporters['prospergenics-profile'] = array(
        'exporter_friendly_name' => 'ProsperGenics member profile',
        'callback'               => 'prospergenics_profiles_personal_data_exporter',
    );
    return $exporters;
} );

/* ───────────────────────── erase ───────────────────────── */

/**
 * The member's own records: experience, education and skill entries and every uploaded image.
 *
 * @return int Records deleted.
 */
function prospergenics_profiles_erase_owned( $user_id ) {
    $n   = 0;
    $ids = get_posts( array(
        'post_type'        => PROSPERGENICS_PROFILES_ENTRY_TYPES,
        'post_status'      => 'any',
        'author'           => (int) $user_id,
        'posts_per_page'   => -1,
        'fields'           => 'ids',
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) );
    foreach ( $ids as $id ) {
        if ( wp_delete_post( (int) $id, true ) ) {
            $n++;
        }
    }
    $uploads = get_posts( array(
        'post_type'        => 'attachment',
        'post_status'      => 'any',
        'posts_per_page'   => -1,
        'fields'           => 'ids',
        'meta_key'         => '_pg_profiles_owner',
        'meta_value'       => (int) $user_id,
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) );
    foreach ( $uploads as $id ) {
        if ( wp_delete_attachment( (int) $id, true ) ) {
            $n++;
        }
    }
    return $n;
}

/**
 * What other members' records say about this member: their endorsements of others' skills, follows
 * in both directions and notifications about them. Counts are derived from these rows, so they
 * update with them.
 *
 * @return int Rows removed.
 */
function prospergenics_profiles_erase_references( $user_id ) {
    global $wpdb;
    $uid = (string) (int) $user_id;
    $n   = 0;
    foreach ( array( '_pg_profiles_endorser', '_pg_profiles_notified' ) as $key ) {
        $n += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s", $key, $uid ) );
        delete_metadata( 'post', 0, $key, $uid, true );
    }
    // follows: theirs (when the account stays, e.g. an erasure request) and everyone's of them
    $n += count( get_user_meta( (int) $user_id, '_pg_profiles_follows', false ) );
    delete_user_meta( (int) $user_id, '_pg_profiles_follows' );
    $n += (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value = %s", '_pg_profiles_follows', $uid ) );
    delete_metadata( 'user', 0, '_pg_profiles_follows', $uid, true );
    // notifications naming them (serialized: "from";i:<id>;)
    $rows = $wpdb->get_col( $wpdb->prepare(
        "SELECT user_id FROM {$wpdb->usermeta} WHERE meta_key = %s AND meta_value LIKE %s",
        '_pg_profiles_notifications',
        '%' . $wpdb->esc_like( 's:4:"from";i:' . $uid . ';' ) . '%'
    ) );
    foreach ( array_unique( array_map( 'intval', $rows ) ) as $owner ) {
        $list = get_user_meta( $owner, '_pg_profiles_notifications', true );
        if ( ! is_array( $list ) ) {
            continue;
        }
        $kept = array_values( array_filter( $list, function ( $note ) use ( $uid ) {
            return ! ( is_array( $note ) && isset( $note['from'] ) && (string) (int) $note['from'] === $uid );
        } ) );
        $n += count( $list ) - count( $kept );
        update_user_meta( $owner, '_pg_profiles_notifications', $kept );
    }
    clean_user_cache( (int) $user_id );
    return $n;
}

/**
 * Erase the profile data of an account that stays (an erasure request): records, references and
 * every profile field. Kept: the profile URL (it belongs to the account, like its name) and the
 * email verification state (account security). The profile becomes private.
 *
 * @return int Items removed.
 */
function prospergenics_profiles_erase_user_data( $user_id ) {
    global $wpdb;
    $user_id = (int) $user_id;
    $n       = prospergenics_profiles_erase_owned( $user_id ) + prospergenics_profiles_erase_references( $user_id );
    $keep    = array( '_pg_profiles_slug', '_pg_profiles_verify_pending', '_pg_profiles_verify_hash', '_pg_profiles_verify_expires', '_pg_profiles_verify_sent', '_pg_profiles_email_verified' );
    $keys    = $wpdb->get_col( $wpdb->prepare(
        "SELECT DISTINCT meta_key FROM {$wpdb->usermeta} WHERE user_id = %d AND meta_key LIKE %s",
        $user_id,
        $wpdb->esc_like( '_pg_profiles_' ) . '%'
    ) );
    foreach ( array_diff( $keys, $keep ) as $key ) {
        if ( '_pg_profiles_completion' !== $key ) {
            $n++;
        }
        delete_user_meta( $user_id, $key );
    }
    update_user_meta( $user_id, '_pg_profiles_privacy', array( 'profile' => 'private' ) );
    // nobody re-migrates or re-asks: mark the settings as current
    update_user_meta( $user_id, '_pg_profiles_privacy_migrated', time() );
    return $n;
}

/** The profile eraser. */
function prospergenics_profiles_personal_data_eraser( $email, $page = 1 ) {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
    }
    $n = prospergenics_profiles_erase_user_data( $user->ID );
    return array(
        'items_removed'  => $n > 0,
        'items_retained' => true,
        'messages'       => array( 'The profile URL and the email confirmation state were kept, because the account still exists; the profile is now private.' ),
        'done'           => true,
    );
}

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
    $erasers['prospergenics-profile'] = array(
        'eraser_friendly_name' => 'ProsperGenics member profile',
        'callback'             => 'prospergenics_profiles_personal_data_eraser',
    );
    return $erasers;
} );

// Deleting a user: their own records go first (even when their content is reassigned), then the
// references other members' records hold.
add_action( 'delete_user', 'prospergenics_profiles_erase_owned', 5 );
add_action( 'deleted_user', 'prospergenics_profiles_erase_references', 10 );

/* ───────────────────────── self-service on /profile/settings/ ───────────────────────── */

/**
 * Everything every registered exporter (WordPress' own and the platform's) has on a member, as
 * one array: groups of items, the way Tools → Export Personal Data builds its file.
 */
function prospergenics_profiles_collect_personal_data( WP_User $user ) {
    $groups = array();
    foreach ( (array) apply_filters( 'wp_privacy_personal_data_exporters', array() ) as $exporter ) {
        if ( empty( $exporter['callback'] ) || ! is_callable( $exporter['callback'] ) ) {
            continue;
        }
        for ( $page = 1; $page <= 100; $page++ ) {
            $r = call_user_func( $exporter['callback'], $user->user_email, $page );
            if ( ! is_array( $r ) ) {
                break;
            }
            foreach ( isset( $r['data'] ) && is_array( $r['data'] ) ? $r['data'] : array() as $item ) {
                if ( empty( $item['group_id'] ) ) {
                    continue;
                }
                $gid = (string) $item['group_id'];
                if ( ! isset( $groups[ $gid ] ) ) {
                    $groups[ $gid ] = array(
                        'label'       => isset( $item['group_label'] ) ? (string) $item['group_label'] : $gid,
                        'description' => isset( $item['group_description'] ) ? (string) $item['group_description'] : '',
                        'items'       => array(),
                    );
                }
                $iid = isset( $item['item_id'] ) ? (string) $item['item_id'] : (string) count( $groups[ $gid ]['items'] );
                foreach ( isset( $item['data'] ) ? (array) $item['data'] : array() as $d ) {
                    if ( isset( $d['name'] ) ) {
                        $groups[ $gid ]['items'][ $iid ][ (string) $d['name'] ] = isset( $d['value'] ) ? $d['value'] : '';
                    }
                }
            }
            if ( ! empty( $r['done'] ) ) {
                break;
            }
        }
    }
    foreach ( $groups as &$g ) {
        $g['items'] = array_values( $g['items'] );
    }
    unset( $g );
    return array(
        'site'      => home_url( '/' ),
        'generated' => gmdate( 'c' ),
        'email'     => $user->user_email,
        'groups'    => $groups,
    );
}

/** Send the logged-in member their data as a JSON download ("Download my data"). */
function prospergenics_profiles_send_personal_data( $user_id ) {
    $user = get_userdata( (int) $user_id );
    if ( ! $user ) {
        return;
    }
    $json = wp_json_encode( prospergenics_profiles_collect_personal_data( $user ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    nocache_headers();
    header( 'Content-Type: application/json; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="prospergenics-my-data-' . gmdate( 'Y-m-d' ) . '.json"' );
    header( 'X-Content-Type-Options: nosniff' );
    echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- a JSON file download, not HTML
    exit;
}

/** Whether a member may delete their own account here (staff hand content over via an admin). */
function prospergenics_profiles_can_self_delete( $user_id ) {
    return $user_id && ! user_can( (int) $user_id, 'edit_posts' ) && ! is_multisite();
}

/**
 * Delete the logged-in member's account: run every eraser (WordPress' own anonymises their
 * comments), delete the user (deleted_user cleans up what others hold) and log out.
 *
 * @return true|WP_Error
 */
function prospergenics_profiles_delete_account( $user_id ) {
    $user = get_userdata( (int) $user_id );
    if ( ! $user || ! prospergenics_profiles_can_self_delete( $user->ID ) ) {
        return new WP_Error( 'pg_profiles_cannot_delete', 'This account cannot be deleted here. Please contact us.' );
    }
    foreach ( (array) apply_filters( 'wp_privacy_personal_data_erasers', array() ) as $eraser ) {
        if ( empty( $eraser['callback'] ) || ! is_callable( $eraser['callback'] ) ) {
            continue;
        }
        for ( $page = 1; $page <= 100; $page++ ) {
            $r = call_user_func( $eraser['callback'], $user->user_email, $page );
            if ( ! is_array( $r ) || ! empty( $r['done'] ) ) {
                break;
            }
        }
    }
    require_once ABSPATH . 'wp-admin/includes/user.php';
    WP_Session_Tokens::get_instance( $user->ID )->destroy_all();
    if ( ! wp_delete_user( $user->ID ) ) {
        return new WP_Error( 'pg_profiles_delete_failed', 'Your account could not be deleted. Please contact us.' );
    }
    wp_clear_auth_cookie();
    wp_set_current_user( 0 );
    return true;
}

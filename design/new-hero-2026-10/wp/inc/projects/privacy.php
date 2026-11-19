<?php
/**
 * Projects track: personal data (GDPR art. 15, 17 and 20; audit ARC-12) and deleted accounts.
 *
 * - Exporter: every contribution of the member (project, role, contribution, evidence, status) and
 *   the projects they created. "Download my data" on /profile/settings/ runs it too.
 * - Eraser (the account stays): their contributions go; a project they created is handed to the
 *   member with the oldest confirmed (else oldest) contribution, or deleted when nobody else is on
 *   it. Confirmations they gave stay as "confirmed" but no longer name them.
 * - Deleting a user: the same, before the deletion (delete_user), so "attribute all content to"
 *   never hands a member's portfolio entry to another account.
 */

defined( 'ABSPATH' ) || exit;

/** Projects (any status) a member created. @return int[] */
function prospergenics_projects_created_by( $user_id ) {
    return array_map( 'intval', get_posts( array(
        'post_type'        => 'pg_project',
        'post_status'      => 'any',
        'author'           => (int) $user_id,
        'posts_per_page'   => -1,
        'fields'           => 'ids',
        'suppress_filters' => true,
        'no_found_rows'    => true,
    ) ) );
}

/** The portfolio exporter. @return array{data:array,done:bool} */
function prospergenics_projects_personal_data_exporter( $email, $page = 1 ) {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return array( 'data' => array(), 'done' => true );
    }
    $uid      = (int) $user->ID;
    $items    = array();
    $kinds    = prospergenics_projects_kinds();
    $statuses = prospergenics_projects_statuses();
    $pairs    = function ( array $pairs ) {
        $out = array();
        foreach ( $pairs as $name => $value ) {
            if ( '' !== (string) $value ) {
                $out[] = array( 'name' => $name, 'value' => (string) $value );
            }
        }
        return $out;
    };
    foreach ( prospergenics_projects_ids_for_member( $uid, 'any' ) as $id ) {
        $list = prospergenics_projects_contributions( $id );
        $i    = prospergenics_projects_find_contribution( $list, $uid );
        if ( $i < 0 ) {
            continue;
        }
        $c        = $list[ $i ];
        $f        = prospergenics_projects_fields( $id );
        $evidence = array();
        foreach ( $c['evidence'] as $e ) {
            $evidence[] = $e['label'] . ': ' . $e['url'];
        }
        $items[] = array(
            'group_id'          => 'prospergenics-portfolio',
            'group_label'       => 'Portfolio',
            'group_description' => 'Your contributions to projects, products, services and employers.',
            'item_id'           => 'pg-portfolio-' . $id . '-' . $uid,
            'data'              => $pairs( array(
                'Project'      => get_the_title( $id ),
                'Kind'         => $kinds[ $f['kind'] ],
                'Project URL'  => prospergenics_projects_url( $id ),
                'Role'         => $c['role'],
                'Contribution' => $c['contribution'],
                'Evidence'     => implode( "\n", $evidence ),
                'Status'       => $statuses[ $c['status'] ],
                'Added'        => $c['added'] ? gmdate( 'Y-m-d H:i', $c['added'] ) . ' UTC' : '',
            ) ),
        );
    }
    foreach ( prospergenics_projects_created_by( $uid ) as $id ) {
        $f       = prospergenics_projects_fields( $id );
        $items[] = array(
            'group_id'          => 'prospergenics-projects-created',
            'group_label'       => 'Projects you created',
            'group_description' => 'Projects, products, services and employers you added and can edit.',
            'item_id'           => 'pg-project-' . $id,
            'data'              => $pairs( array(
                'Name'        => get_the_title( $id ),
                'Kind'        => $kinds[ $f['kind'] ],
                'URL'         => prospergenics_projects_url( $id ),
                'Link'        => $f['url'],
                'Period'      => $f['period'],
                'Description' => (string) get_post_field( 'post_content', $id ),
                'Skills'      => implode( ', ', $f['skills'] ),
                'Status'      => get_post_status( $id ),
            ) ),
        );
    }
    return array( 'data' => $items, 'done' => true );
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
    $exporters['prospergenics-portfolio'] = array(
        'exporter_friendly_name' => 'ProsperGenics portfolio',
        'callback'               => 'prospergenics_projects_personal_data_exporter',
    );
    return $exporters;
} );

/**
 * Remove a member from every project: their contributions, the projects only they were on, the
 * creator role (handed on) and their name on confirmations they gave.
 *
 * @return array{removed:int,deleted:int,handed_over:int}
 */
function prospergenics_projects_erase_member( $user_id ) {
    $user_id = (int) $user_id;
    $n       = array( 'removed' => 0, 'deleted' => 0, 'handed_over' => 0 );
    // projects they created: hand over or delete
    foreach ( prospergenics_projects_created_by( $user_id ) as $id ) {
        $next = prospergenics_projects_next_creator( prospergenics_projects_contributions( $id ), $user_id );
        if ( $next ) {
            wp_update_post( array( 'ID' => $id, 'post_author' => $next ) );
            $n['handed_over']++;
        } else {
            prospergenics_projects_delete( $id );
            $n['deleted']++;
        }
    }
    // their contributions elsewhere
    foreach ( prospergenics_projects_ids_for_member( $user_id, 'any' ) as $id ) {
        $r = prospergenics_projects_remove_contribution( $id, $user_id );
        if ( 'removed' === $r ) {
            $n['removed']++;
        } elseif ( 'deleted' === $r ) {
            $n['deleted']++;
        }
    }
    // confirmations they gave keep their status, without their user ID
    global $wpdb;
    $ids = $wpdb->get_col( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value LIKE %s",
        '_pg_project_contributions',
        '%' . $wpdb->esc_like( 's:12:"confirmed_by";i:' . $user_id . ';' ) . '%'
    ) );
    foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
        $list = prospergenics_projects_contributions( $id );
        foreach ( $list as &$c ) {
            if ( (int) $c['confirmed_by'] === $user_id ) {
                $c['confirmed_by'] = 0;
            }
        }
        unset( $c );
        prospergenics_projects_store_contributions( $id, $list );
    }
    return $n;
}

/** The portfolio eraser (the account stays). */
function prospergenics_projects_personal_data_eraser( $email, $page = 1 ) {
    $user = get_user_by( 'email', $email );
    if ( ! $user ) {
        return array( 'items_removed' => false, 'items_retained' => false, 'messages' => array(), 'done' => true );
    }
    $n        = prospergenics_projects_erase_member( $user->ID );
    $messages = array();
    if ( $n['handed_over'] ) {
        $messages[] = sprintf( '%d project(s) this member created stay on the site because other members contributed to them; another contributor now manages them.', $n['handed_over'] );
    }
    return array(
        'items_removed'  => ( $n['removed'] + $n['deleted'] + $n['handed_over'] ) > 0,
        'items_retained' => $n['handed_over'] > 0,
        'messages'       => $messages,
        'done'           => true,
    );
}

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
    $erasers['prospergenics-portfolio'] = array(
        'eraser_friendly_name' => 'ProsperGenics portfolio',
        'callback'             => 'prospergenics_projects_personal_data_eraser',
    );
    return $erasers;
} );

// Deleting a user: before core reassigns or deletes their posts.
add_action( 'delete_user', 'prospergenics_projects_erase_member', 4 );

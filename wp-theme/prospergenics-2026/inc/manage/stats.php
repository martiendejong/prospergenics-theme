<?php
/**
 * Staff area (/manage/, card #6457): the overview's numbers. One COUNT query each, never a list of
 * records: users (user_registered, the verification time _pg_profiles_email_verified), enrolment
 * records (pg_enrolment, _pg_courses_completed_at), certificates (pg_certificate), articles pending
 * review, unread contact messages, open reports and open writer/instructor requests.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Activity since a moment.
 *
 * @param int $since Unix time.
 * @return array{members:int,verified:int,enrolments:int,completions:int,certificates:int}
 */
function prospergenics_manage_activity( $since ) {
    global $wpdb;
    $since = (int) $since;
    $date  = gmdate( 'Y-m-d H:i:s', $since ); // user_registered and post_date_gmt are UTC
    return array(
        'members'      => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->users} WHERE user_registered >= %s", $date ) ),
        'verified'     => (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->usermeta} WHERE meta_key = '_pg_profiles_email_verified' AND meta_value + 0 >= %d", $since ) ),
        'enrolments'   => prospergenics_manage_count_posts_since( 'pg_enrolment', $date ),
        'completions'  => (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->posts} p INNER JOIN {$wpdb->postmeta} m ON m.post_id = p.ID AND m.meta_key = '_pg_courses_completed_at'
            WHERE p.post_type = 'pg_enrolment' AND p.post_status = 'publish' AND m.meta_value + 0 >= %d",
            $since
        ) ),
        'certificates' => prospergenics_manage_count_posts_since( 'pg_certificate', $date ),
    );
}

/** Published records of a type created since a UTC date. */
function prospergenics_manage_count_posts_since( $type, $date ) {
    global $wpdb;
    return (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status = 'publish' AND post_date_gmt >= %s",
        $type,
        $date
    ) );
}

/** Contact messages nobody has opened yet (spam, stored as drafts, left out). */
function prospergenics_manage_unread_message_count() {
    global $wpdb;
    return (int) $wpdb->get_var(
        "SELECT COUNT(*) FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} r ON r.post_id = p.ID AND r.meta_key = '_pg_contact_read_at'
        LEFT JOIN {$wpdb->postmeta} h ON h.post_id = p.ID AND h.meta_key = '_pg_contact_handled_at'
        WHERE p.post_type = 'pg_contact_msg' AND p.post_status = 'publish' AND r.meta_id IS NULL AND h.meta_id IS NULL"
    );
}

/** Articles waiting for review (core's per-status COUNT, cached by WordPress). */
function prospergenics_manage_pending_article_count() {
    $counts = wp_count_posts( 'pg_article' );
    return isset( $counts->pending ) ? (int) $counts->pending : 0;
}

/**
 * What is waiting for the team right now.
 *
 * @return array{articles:int,messages:int,reports:int,requests:int}
 */
function prospergenics_manage_queues() {
    return array(
        'articles' => prospergenics_manage_pending_article_count(),
        'messages' => prospergenics_manage_unread_message_count(),
        'reports'  => function_exists( 'prospergenics_profiles_open_report_count' ) ? prospergenics_profiles_open_report_count() : 0,
        'requests' => function_exists( 'prospergenics_profiles_pending_application_count' ) ? prospergenics_profiles_pending_application_count() : 0,
    );
}

<?php
/**
 * Seed the throwaway e2e WordPress (run by tests/e2e/run.sh through `wp eval-file`, after the
 * theme is active): one user per role, a published course with one lesson, an article, a profile
 * entry and a contact message. Prints the IDs and names role-matrix.js needs as JSON.
 *
 * Idempotent: users and posts are looked up by login / title before they are created, so the
 * script can run again on the same site.
 */

defined( 'ABSPATH' ) || exit;

// No mail server here: the verification email on user_register must not try to send.
add_filter( 'pre_wp_mail', '__return_true' );

$pg_e2e_password = 'e2e-pass-123'; // throwaway site on localhost only

// "Anyone can register" (Settings, General): switches /register/ on (card #6338)
update_option( 'users_can_register', 1 );

$pg_e2e_users = array(
    // key           login           role           first     last         verified
    'editor'     => array( 'e2e_editor', 'editor', 'Ines', 'Instructor', true ),
    'author'     => array( 'e2e_author', 'author', 'Aldo', 'Authorson', true ),
    'contributor' => array( 'e2e_contrib', 'contributor', 'Cora', 'Contribelli', true ),
    'subscriber' => array( 'e2e_sub', 'subscriber', 'Sami', 'Subscriberova', true ),
    'subscriber2' => array( 'e2e_sub2', 'subscriber', 'Tove', 'Othermember', true ),
    'unverified' => array( 'e2e_new', 'subscriber', 'Nuno', 'Newcomer', false ),
);

$pg_e2e_out = array( 'password' => $pg_e2e_password, 'users' => array() );

foreach ( $pg_e2e_users as $key => $u ) {
    list( $login, $role, $first, $last, $verified ) = $u;
    $user = get_user_by( 'login', $login );
    $id   = $user ? $user->ID : wp_insert_user( array(
        'user_login'   => $login,
        'user_pass'    => $pg_e2e_password,
        'user_email'   => $login . '@example.test',
        'role'         => $role,
        'first_name'   => $first,
        'last_name'    => $last,
        'display_name' => $first . ' ' . $last,
    ) );
    if ( is_wp_error( $id ) ) {
        fwrite( STDERR, "could not create $login: " . $id->get_error_message() . "\n" );
        exit( 1 );
    }
    if ( $verified ) {
        delete_user_meta( $id, '_pg_profiles_verify_pending' );
    } else {
        update_user_meta( $id, '_pg_profiles_verify_pending', 1 );
    }
    $pg_e2e_out['users'][ $key ] = array(
        'id'    => (int) $id,
        'login' => $login,
        'name'  => $first . ' ' . $last,
        'slug'  => function_exists( 'prospergenics_profiles_slug' ) ? prospergenics_profiles_slug( $id ) : '',
    );
}
$admin = get_user_by( 'login', 'admin' );
$pg_e2e_out['users']['admin'] = array( 'id' => (int) $admin->ID, 'login' => 'admin', 'name' => $admin->display_name, 'slug' => '' );

/** Find a post by type and title, or create it. */
function pg_e2e_post( $type, $title, $args = array() ) {
    $found = get_posts( array( 'post_type' => $type, 'title' => $title, 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
    if ( $found ) {
        return (int) $found[0];
    }
    $id = wp_insert_post( array_merge( array( 'post_type' => $type, 'post_status' => 'publish', 'post_title' => $title ), $args ), true );
    if ( is_wp_error( $id ) ) {
        fwrite( STDERR, "could not create $type '$title': " . $id->get_error_message() . "\n" );
        exit( 1 );
    }
    return (int) $id;
}

$editor = $pg_e2e_out['users']['editor']['id'];
$course = pg_e2e_post( 'pg_course', 'E2E course', array( 'post_author' => $editor, 'post_excerpt' => 'A course for the role matrix.' ) );
$module = pg_e2e_post( 'pg_module', 'E2E module', array( 'post_author' => $editor, 'post_parent' => $course ) );
$lesson = pg_e2e_post( 'pg_lesson', 'E2E lesson', array( 'post_author' => $editor, 'post_parent' => $module, 'post_content' => '<p>Read this, then mark it complete.</p>' ) );

$article = pg_e2e_post( 'pg_article', 'E2E article', array( 'post_author' => $pg_e2e_out['users']['author']['id'], 'post_content' => '<p>An article for the role matrix.</p>' ) );

// A contact message whose subject must never reach anyone but editors and administrators.
$contact = pg_e2e_post( 'pg_contact_msg', 'E2E private enquiry 7f3a', array( 'post_content' => 'Phone +31 6 0000 0000' ) );

// A profile entry owned by subscriber2, which subscriber must not be able to change.
$owner   = $pg_e2e_out['users']['subscriber2']['id'];
$entries = get_posts( array( 'post_type' => 'pg_experience', 'author' => $owner, 'posts_per_page' => 1, 'fields' => 'ids' ) );
$entry   = $entries ? (int) $entries[0] : prospergenics_profiles_save_entry( $owner, 'pg_experience', array(
    'title' => 'E2E experience entry', 'description' => 'Owned by subscriber2.', 'company' => 'E2E Ltd',
    'start' => '2020-01', 'end' => '', 'current' => true,
) );
if ( is_wp_error( $entry ) ) {
    fwrite( STDERR, 'could not create the profile entry: ' . $entry->get_error_message() . "\n" );
    exit( 1 );
}

$pg_e2e_out['course']        = $course;
$pg_e2e_out['lesson']        = $lesson;
$pg_e2e_out['lesson_url']    = get_permalink( $lesson );
$pg_e2e_out['article']       = $article;
$pg_e2e_out['contact_title'] = 'E2E private enquiry 7f3a';
$pg_e2e_out['contact_id']    = $contact;
$pg_e2e_out['entry']         = (int) $entry;
$pg_e2e_out['entry_title']   = 'E2E experience entry';

echo wp_json_encode( $pg_e2e_out, JSON_PRETTY_PRINT ) . "\n";

<?php
/**
 * Seed the throwaway e2e WordPress (run by tests/e2e/run.sh through `wp eval-file`, after the
 * theme is active): one user per role (a Writer included), a published course with one lesson, an
 * article, an article waiting for review, a profile entry, a contact message, an image of the
 * administrator's, and a member with open requests to write and to teach. Prints the IDs and names role-matrix.js needs as JSON.
 *
 * Idempotent: users and posts are looked up by login / title before they are created, so the
 * script can run again on the same site.
 */

defined( 'ABSPATH' ) || exit;

// No mail server here: the verification email on user_register must not try to send.
add_filter( 'pre_wp_mail', '__return_true' );

$pg_e2e_password = 'e2e-pass-123'; // throwaway site on localhost only

$pg_e2e_users = array(
    // key           login           role           first     last         verified
    'editor'     => array( 'e2e_editor', 'editor', 'Ines', 'Instructor', true ),
    'author'     => array( 'e2e_author', 'author', 'Aldo', 'Authorson', true ),
    'contributor' => array( 'e2e_contrib', 'contributor', 'Cora', 'Contribelli', true ),
    'subscriber' => array( 'e2e_sub', 'subscriber', 'Sami', 'Subscriberova', true ),
    'subscriber2' => array( 'e2e_sub2', 'subscriber', 'Tove', 'Othermember', true ),
    'unverified' => array( 'e2e_new', 'subscriber', 'Nuno', 'Newcomer', false ),
    'unverified2' => array( 'e2e_new2', 'subscriber', 'Uma', 'Unconfirmed', false ), // the admin marks her verified
    'writer'     => array( 'e2e_writer', 'pg_writer', 'Wim', 'Writerson', true ),
    'applicant'  => array( 'e2e_applicant', 'subscriber', 'Aya', 'Applicant', true ), // pending requests
    'editor2'    => array( 'e2e_editor2', 'editor', 'Edda', 'Secondeditor', true ), // /manage/: editors never change editors
    'managed'    => array( 'e2e_managed', 'subscriber', 'Mira', 'Managed', false ), // /manage/ member actions
    'badger'     => array( 'e2e_badger', 'subscriber', 'Bea', 'Badgeholder', true ), // badges: a confirmed contribution in a private portfolio
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
    if ( $user ) {
        $user->set_role( $role ); // a second run starts from the seeded role (the applicant gets approved)
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
// An article waiting for review (/manage/articles/), by the contributor: only editors may publish it.
$pending = pg_e2e_post( 'pg_article', 'E2E pending article 5b2e', array( 'post_status' => 'pending', 'post_author' => $pg_e2e_out['users']['contributor']['id'], 'post_content' => '<p>Waiting for review.</p>' ) );
if ( 'pending' !== get_post_status( $pending ) ) {
    wp_update_post( array( 'ID' => $pending, 'post_status' => 'pending' ) ); // a second run starts from "pending" again
}

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

// Two open requests from the applicant (Users → Requests): to write and to teach.
foreach ( array( 'write', 'teach' ) as $type ) {
    update_user_meta( $pg_e2e_out['users']['applicant']['id'], '_pg_profiles_application_' . $type, array(
        'status'     => 'pending',
        'motivation' => "E2E: I would like to $type on ProsperGenics, for the role matrix.",
        'sample'     => '',
        'submitted'  => time() - HOUR_IN_SECONDS,
    ) );
}
delete_user_meta( $pg_e2e_out['users']['applicant']['id'], '_pg_courses_instructor' );

// An image in the media library that belongs to the administrator: writers must not see it.
// (looked up by hand: post_status 'any' leaves out attachments' 'inherit')
$media = get_posts( array( 'post_type' => 'attachment', 'title' => 'E2E admin image 9c1d', 'post_status' => 'inherit', 'posts_per_page' => 1, 'fields' => 'ids' ) );
$media = $media ? (int) $media[0] : (int) wp_insert_attachment( array(
    'post_title'     => 'E2E admin image 9c1d',
    'post_author'    => $admin->ID,
    'post_status'    => 'inherit',
    'post_mime_type' => 'image/png',
    'guid'           => home_url( '/wp-content/uploads/e2e-admin-9c1d.png' ),
) );

// Scrutiny (card #6389): a published item and one waiting for review, about subscriber2, from a
// visitor whose email address only staff may see. Reset to that state on every run.
$pg_e2e_scrutiny = array();
foreach ( array( 'published' => array( 'approved', 'E2E scrutiny published 5b2e: he trained ten apprentices.' ), 'pending' => array( 'pending', 'E2E scrutiny pending 8d4c: not reviewed yet.' ) ) as $k => $item ) {
    $sid = pg_e2e_post( 'pg_scrutiny', 'E2E scrutiny ' . $k, array( 'post_author' => 0, 'post_content' => $item[1] ) );
    $meta = array(
        'kind' => 'evidence', 'target' => 'person:' . $owner, 'target_type' => 'person', 'target_id' => $owner, 'sources' => array(),
        'disclosure' => 'none', 'disclosure_text' => '', 'name' => 'E2E Visitor', 'status' => $item[0],
        'published_at' => 'approved' === $item[0] ? time() : 0, 'audit_email' => 'e2e-scrutiny-visitor@example.test', 'audit_ip' => str_repeat( 'ab', 32 ),
    );
    foreach ( $meta as $mk => $mv ) {
        update_post_meta( $sid, '_pg_scrutiny_' . $mk, $mv );
    }
    delete_post_meta( $sid, '_pg_scrutiny_response' );
    $pg_e2e_scrutiny[ $k ] = array( 'id' => $sid, 'text' => $item[1] );
}
$pg_e2e_out['scrutiny']       = $pg_e2e_scrutiny;
$pg_e2e_out['scrutiny_email'] = 'e2e-scrutiny-visitor@example.test';

// Portfolios (#6388): a project the subscriber added (public profile and portfolio), on which
// subscriber2 has a contribution in a private portfolio, which must never reach anyone else.
$pg_e2e_sub  = $pg_e2e_out['users']['subscriber']['id'];
$pg_e2e_sub2 = $pg_e2e_out['users']['subscriber2']['id'];
update_user_meta( $pg_e2e_sub, '_pg_profiles_privacy', array( 'profile' => 'public', 'portfolio' => 'public' ) );
update_user_meta( $pg_e2e_sub2, '_pg_profiles_privacy', array( 'portfolio' => 'private' ) );
$project = get_posts( array( 'post_type' => 'pg_project', 'title' => 'E2E solar kiosk 4b2e', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
if ( $project ) {
    $project = (int) $project[0];
    wp_update_post( array( 'ID' => $project, 'post_title' => 'E2E solar kiosk 4b2e', 'post_author' => $pg_e2e_sub ) ); // a second run starts over
    delete_post_meta( $project, '_pg_project_hidden' );
} else {
    $project = prospergenics_projects_create( $pg_e2e_sub,
        array( 'title' => 'E2E solar kiosk 4b2e', 'kind' => 'product', 'url' => 'https://example.org/kiosk', 'period' => '2025–2026', 'description' => 'A kiosk for the role matrix.', 'skills' => array( 'Solar' ) ),
        array( 'role' => 'E2E founder role', 'contribution' => 'Designed the kiosk.', 'evidence' => array( array( 'label' => 'Photos', 'url' => 'https://example.org/photos' ) ) )
    );
    if ( is_wp_error( $project ) ) {
        fwrite( STDERR, 'could not create the project: ' . $project->get_error_message() . "\n" );
        exit( 1 );
    }
}
prospergenics_projects_store_contributions( $project, array(
    array( 'user_id' => $pg_e2e_sub, 'role' => 'E2E founder role', 'contribution' => 'Designed the kiosk.', 'evidence' => array( array( 'label' => 'Photos', 'url' => 'https://example.org/photos' ) ), 'status' => 'claimed', 'added' => time() - 60 ),
    array( 'user_id' => $pg_e2e_sub2, 'role' => 'E2E secret role 5d1c', 'contribution' => 'Private work 5d1c.', 'evidence' => array(), 'status' => 'claimed', 'added' => time() ),
) );
$pg_e2e_out['project']      = (int) $project;
$pg_e2e_out['project_url']  = get_permalink( $project );
$pg_e2e_out['project_title'] = 'E2E solar kiosk 4b2e';
$pg_e2e_out['secret_role']  = 'E2E secret role 5d1c';

// Badges (inc/profiles/rank.php): the badger has a public profile with a headline and a skill
// (Member), and a confirmed contribution to the subscriber's second project in a portfolio she
// keeps private, which must not raise her public badge (it would make her a Contributor).
$pg_e2e_badger = $pg_e2e_out['users']['badger']['id'];
update_user_meta( $pg_e2e_badger, '_pg_profiles_privacy', array( 'profile' => 'public', 'portfolio' => 'private' ) );
update_user_meta( $pg_e2e_badger, '_pg_profiles_headline', 'Badgecraft tester for the role matrix' );
if ( ! get_posts( array( 'post_type' => 'pg_skill', 'author' => $pg_e2e_badger, 'title' => 'Badgecraft', 'posts_per_page' => 1, 'fields' => 'ids' ) ) ) {
    prospergenics_profiles_save_entry( $pg_e2e_badger, 'pg_skill', array( 'name' => 'Badgecraft' ) );
}
$badge_project = get_posts( array( 'post_type' => 'pg_project', 'title' => 'E2E badge workshop 3c9a', 'post_status' => 'any', 'posts_per_page' => 1, 'fields' => 'ids' ) );
$badge_project = $badge_project ? (int) $badge_project[0] : prospergenics_projects_create( $pg_e2e_sub,
    array( 'title' => 'E2E badge workshop 3c9a', 'kind' => 'project', 'url' => '', 'period' => '2026', 'description' => 'A workshop for the badge checks.', 'skills' => array( 'Badgecraft' ) ),
    array( 'role' => 'E2E organiser', 'contribution' => 'Organised the workshop.', 'evidence' => array() )
);
if ( is_wp_error( $badge_project ) ) {
    fwrite( STDERR, 'could not create the badge project: ' . $badge_project->get_error_message() . "\n" );
    exit( 1 );
}
prospergenics_projects_store_contributions( $badge_project, array(
    array( 'user_id' => $pg_e2e_sub, 'role' => 'E2E organiser', 'contribution' => 'Organised the workshop.', 'evidence' => array(), 'status' => 'claimed', 'added' => time() - 60 ),
    array( 'user_id' => $pg_e2e_badger, 'role' => 'E2E badge trainer 6a0d', 'contribution' => 'Trained the group.', 'evidence' => array(), 'status' => 'confirmed', 'added' => time(), 'confirmed_by' => $pg_e2e_sub ),
) );
$pg_e2e_out['badge_project']     = (int) $badge_project;
$pg_e2e_out['badge_project_url'] = get_permalink( $badge_project );

$pg_e2e_out['course']        = $course;
$pg_e2e_out['media']         = $media;
$pg_e2e_out['media_title']   = 'E2E admin image 9c1d';
$pg_e2e_out['lesson']        = $lesson;
$pg_e2e_out['lesson_url']    = get_permalink( $lesson );
$pg_e2e_out['article']       = $article;
$pg_e2e_out['pending']       = $pending;
$pg_e2e_out['pending_title'] = 'E2E pending article 5b2e';
$pg_e2e_out['contact_title'] = 'E2E private enquiry 7f3a';
$pg_e2e_out['contact_id']    = $contact;
$pg_e2e_out['entry']         = (int) $entry;
$pg_e2e_out['entry_title']   = 'E2E experience entry';

echo wp_json_encode( $pg_e2e_out, JSON_PRETTY_PRINT ) . "\n";

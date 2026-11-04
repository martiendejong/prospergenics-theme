<?php
/**
 * Standalone test for the member flows across the platform tracks:
 *   - ARC-8  "Apply to write / teach": request states, validation, the pg_writer role and article
 *            capabilities, approved instructors' course capabilities, the admin screen's checks,
 *   - ARC-9  the review emails (which status change means what) and the "Waiting for review" queue,
 *   - ARC-15 Users columns and row actions, the "hidden" profile flag,
 *   - ARC-20 enrolment and certificate emails, certificates on profiles (privacy key), next course,
 *   - NAV-16 courses and articles on profiles, "Courses I teach".
 * Pure rules are called directly; the wiring (hooks, nonces, capability checks, mail helper) is
 * checked in the generated theme's source, since it needs WordPress to run.
 *
 * Run with: php tests/test-platform-flows.php
 */

$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ───────────────────────────── WordPress stubs ─────────────────────────────
define( 'ABSPATH', $theme . '/' );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['_hooks'] = array();
$GLOBALS['_meta']  = array();
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][ $hook ][] = $cb; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][ $hook ][] = $cb; return true; }
function apply_filters( $hook, $value ) { return $value; }
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['_meta'][ $id ][ $key ] ) ? $GLOBALS['_meta'][ $id ][ $key ] : ''; }
function register_post_type( $type, $args ) { $GLOBALS['_cpts'][ $type ] = $args; }
function register_taxonomy( $tax, $types, $args ) { $GLOBALS['_taxes'][ $tax ] = $args; }
function register_post_meta() {}

require $theme . '/inc/profiles/helpers.php';
require $theme . '/inc/profiles/applications.php';
require $theme . '/inc/articles/roles.php';
require $theme . '/inc/articles/review.php';
require $theme . '/inc/profiles/users-admin.php';

$src = function ( $file ) use ( $theme ) {
	return (string) file_get_contents( $theme . '/' . $file );
};

// ───────────────────────────── ARC-8 requests ─────────────────────────────
echo "Requests to write or teach (ARC-8)\n";
$now = 1800000000;
$st  = function ( $stored, $granted = false ) use ( $now ) {
	return prospergenics_profiles_application_state( $stored, $now, $granted )['state'];
};
ok( 'open' === $st( '' ) && 'open' === $st( array() ), 'no request yet: the form is open' );
ok( 'pending' === $st( array( 'status' => 'pending', 'submitted' => $now - 10 ) ), 'one open request per type: a pending request blocks a second one' );
ok( 'granted' === $st( array( 'status' => 'pending' ), true ), 'members who already may write/teach get nothing' );
$declined = array( 'status' => 'declined', 'decided' => $now - 10 * DAY_IN_SECONDS );
$r        = prospergenics_profiles_application_state( $declined, $now, false );
ok( 'cooldown' === $r['state'] && $now + 20 * DAY_IN_SECONDS === $r['retry_at'], 'declined 10 days ago: ask again in 20 days' );
ok( 'open' === $st( array( 'status' => 'declined', 'decided' => $now - 30 * DAY_IN_SECONDS ) ), 'declined 30 days ago: may ask again' );
ok( 30 === PROSPERGENICS_PROFILES_REAPPLY_DAYS, 're-apply after 30 days' );
ok( 'open' === $st( array( 'status' => 'approved' ), false ), 'approved but the right was taken away since: may ask again' );

list( $c, $e ) = prospergenics_profiles_validate_application( 'Too short', '' );
ok( isset( $e['motivation'] ), 'a motivation under 20 characters is refused' );
list( $c, $e ) = prospergenics_profiles_validate_application( str_repeat( 'a', 2001 ), '' );
ok( isset( $e['motivation'] ), 'a motivation over 2000 characters is refused' );
list( $c, $e ) = prospergenics_profiles_validate_application( 'I want to write about solar pricing in Kisumu.', 'javascript:alert(1)' );
ok( isset( $e['sample'] ) && '' === $c['sample'], 'a sample link must be http(s)' );
list( $c, $e ) = prospergenics_profiles_validate_application( "  I want to write about solar pricing in Kisumu.\r\n", 'https://example.org/post' );
ok( ! $e && 'I want to write about solar pricing in Kisumu.' === $c['motivation'] && 'https://example.org/post' === $c['sample'], 'a valid request is trimmed and kept' );
$types = prospergenics_profiles_application_types();
ok( array( 'write', 'teach' ) === array_keys( $types ) && '/articles/mine/' === $types['write']['back'] && '/instructor/dashboard/' === $types['teach']['back'], 'two request types, each returning to the page that refused the member' );

$app = $src( 'inc/profiles/applications.php' );
ok( false !== strpos( $app, "add_action( 'admin_post_pg_profiles_apply'" ) && false === strpos( $app, 'admin_post_nopriv_pg_profiles_apply' ), 'the request form posts to admin-post.php, logged-in members only' );
ok( false !== strpos( $app, "check_admin_referer( 'pg_profiles_apply_' . \$type )" ) && false !== strpos( $app, "wp_nonce_field( 'pg_profiles_apply_' . \$type" ), 'the request form carries and checks a nonce' );
ok( false !== strpos( $app, "check_admin_referer( 'pg_profiles_decide_' . \$user_id . '_' . \$type )" ) && false !== strpos( $app, "! current_user_can( 'promote_users' ) || ! current_user_can( 'edit_user', \$user_id )" ), 'approve/decline: nonce per member and type, promote_users and edit_user' );
ok( false !== strpos( $app, "add_users_page( 'Writer and instructor requests'" ) && false !== strpos( $app, "'promote_users', 'pg-applications'" ), 'Users → Requests is for promote_users' );
ok( false !== strpos( $app, "prospergenics_profiles_is_verified( \$user_id )" ), 'only members with a confirmed email address can ask' );
ok( false !== strpos( $app, "\$member->add_role( 'pg_writer' )" ) && false !== strpos( $app, 'update_user_meta( $member->ID, PROSPERGENICS_COURSES_INSTRUCTOR_META, time() )' ), 'approving: pg_writer role for writing, the instructor flag (user meta) for teaching' );
ok( substr_count( $app, 'prospergenics_profiles_send_mail(' ) >= 2 && false === strpos( $app, 'wp_mail(' ), 'administrators and the member are emailed through the platform mail helper' );
ok( false !== strpos( $app, 'sanitize_textarea_field( wp_unslash( $_POST[\'motivation\'] ) )' ) && false !== strpos( $app, 'esc_url_raw( wp_unslash( $_POST[\'sample\'] ) )' ), 'request input is sanitised' );

echo "The pg_writer role and article capabilities (ARC-8)\n";
$grant = prospergenics_articles_grant_caps( array( 'read' => true, 'edit_posts' => true, 'delete_posts' => true ) );
ok( ! empty( $grant['edit_pg_articles'] ) && empty( $grant['publish_pg_articles'] ), 'contributors: write and submit articles, not publish' );
$grant = prospergenics_articles_grant_caps( array( 'edit_posts' => true, 'publish_posts' => true, 'edit_published_posts' => true ) );
ok( ! empty( $grant['publish_pg_articles'] ) && ! empty( $grant['edit_published_pg_articles'] ) && empty( $grant['edit_others_pg_articles'] ), 'authors: publish their own articles' );
$grant = prospergenics_articles_grant_caps( array( 'edit_posts' => true, 'edit_others_posts' => true, 'publish_posts' => true ) );
ok( ! empty( $grant['edit_others_pg_articles'] ), 'editors: everyone\'s articles' );
ok( array( 'read' => true ) === array_diff_key( prospergenics_articles_grant_caps( array( 'read' => true ) ), array( 'x' => 1 ) ), 'subscribers get no article capabilities' );
$writer = prospergenics_articles_writer_caps();
ok( ! empty( $writer['edit_pg_articles'] ) && ! empty( $writer['delete_pg_articles'] ) && ! empty( $writer['upload_files'] ) && ! empty( $writer['read'] ), 'writers: write, delete their drafts, upload images' );
ok( empty( $writer['publish_pg_articles'] ) && empty( $writer['edit_posts'] ) && empty( $writer['edit_others_posts'] ) && empty( $writer['edit_users'] ) && empty( $writer['moderate_comments'] ), 'writers cannot publish, touch blog posts, others\' content (contact messages), profiles or comments' );
$roles   = $src( 'inc/articles/roles.php' );
$content = $src( 'inc/articles/content.php' );
ok( false !== strpos( $content, "'capability_type' => array( 'pg_article', 'pg_articles' )" ), 'pg_article has its own capability type' );
ok( 3 === substr_count( $content, "'capabilities'      => array( 'assign_terms' => 'edit_pg_articles' )" ), 'writers may pick categories, tags and series' );
ok( false !== strpos( $roles, "add_filter( 'ajax_query_attachments_args'" ) && false !== strpos( $roles, "add_filter( 'rest_attachment_query'" ) && false !== strpos( $roles, "'author__in'" ), 'writers see only their own uploads (media modal and REST)' );
ok( false !== strpos( $roles, "array( 'upload_files' ) : array( 'do_not_allow' )" ), 'writers may edit their own uploads, nobody else\'s' );
ok( false !== strpos( $src( 'inc/account.php' ), "array( 'edit_posts', 'edit_pg_articles' )" ), 'writers count as content staff: they reach the block editor' );
ok( false !== strpos( $src( 'inc/articles/views/mine.php' ), "current_user_can( 'edit_pg_articles' )" ) && false !== strpos( $src( 'inc/articles/views/mine.php' ), "prospergenics_profiles_application_box( 'write', 'pga-btn' )" ), '/articles/mine/ checks the article capability and offers "Apply to write"' );
ok( false !== strpos( $src( 'inc/profiles/privacy.php' ), "array( 'edit_posts', 'edit_pg_articles', 'edit_pg_courses' )" ), 'writers and instructors cannot delete their account (and their content) themselves' );

echo "Approved instructors (ARC-8)\n";
require $theme . '/inc/courses/data-model.php';
$cap_filter = null;
foreach ( $GLOBALS['_hooks']['user_has_cap'] as $cb ) {
	if ( $cb instanceof Closure ) { $cap_filter = $cb; }
}
$GLOBALS['_meta'][41] = array( '_pg_courses_instructor' => 1800000000 );
$teacher = $cap_filter( array( 'read' => true ), array(), array(), (object) array( 'ID' => 41 ) );
$member  = $cap_filter( array( 'read' => true ), array(), array(), (object) array( 'ID' => 42 ) );
ok( 5 === count( array_intersect_key( $teacher, array_flip( prospergenics_courses_instructor_caps() ) ) ), 'the instructor flag gives the five course capabilities' );
ok( array( 'edit_pg_courses', 'publish_pg_courses', 'edit_published_pg_courses', 'delete_pg_courses', 'delete_published_pg_courses' ) === prospergenics_courses_instructor_caps(), '… exactly the five from the data model' );
ok( empty( $teacher['edit_others_pg_courses'] ) && empty( $teacher['delete_others_pg_courses'] ), 'an instructor cannot edit or delete someone else\'s course' );
ok( empty( $member['edit_pg_courses'] ), 'members without the flag: no course capabilities' );
ok( false !== strpos( $src( 'courses-dashboard.php' ), "prospergenics_profiles_application_box('teach', 'pgc-btn')" ) && false !== strpos( $src( 'courses-wizard.php' ), "prospergenics_profiles_application_box('teach', 'pgc-btn')" ), '"Apply to teach" on the dashboard and the wizard refusal' );

// ───────────────────────────── ARC-9 review ─────────────────────────────
echo "Review emails (ARC-9)\n";
ok( 'submitted' === prospergenics_articles_review_event( 'draft', 'pending' ) && 'submitted' === prospergenics_articles_review_event( 'auto-draft', 'pending' ) && 'submitted' === prospergenics_articles_review_event( 'new', 'pending' ), 'draft → pending (also straight from a new article): submitted' );
ok( 'published' === prospergenics_articles_review_event( 'pending', 'publish' ) && 'scheduled' === prospergenics_articles_review_event( 'pending', 'future' ), 'pending → publish/future: published/scheduled' );
ok( 'returned' === prospergenics_articles_review_event( 'pending', 'draft' ), 'pending → draft: returned' );
ok( '' === prospergenics_articles_review_event( 'draft', 'publish' ) && '' === prospergenics_articles_review_event( 'future', 'publish' ) && '' === prospergenics_articles_review_event( 'pending', 'pending' ) && '' === prospergenics_articles_review_event( 'publish', 'draft' ), 'other changes (scheduled going live, own unpublish, saves) mail nobody' );
$links = array( 'edit' => 'E', 'queue' => 'Q', 'mine' => 'M', 'view' => 'V', 'date' => 'D' );
$m     = prospergenics_articles_review_mail( 'submitted', 'Solar', 'Rita', $links );
ok( 'Article waiting for review: Solar' === $m['subject'] && false !== strpos( $m['body'], 'E' ) && false !== strpos( $m['body'], 'Q' ), 'editors get the title, an edit link and the queue' );
ok( 'Your article is published: Solar' === prospergenics_articles_review_mail( 'published', 'Solar', 'Rita', $links )['subject'], 'authors hear when it is published' );
ok( 0 === strpos( prospergenics_articles_review_mail( 'returned', '', 'Rita', $links )['subject'], 'Your article is back in your drafts: (untitled)' ), 'and when it is sent back' );
$review = $src( 'inc/articles/review.php' );
ok( false !== strpos( $review, "add_action( 'transition_post_status', 'prospergenics_articles_review_transition', 10, 3 )" ), 'hooked on transition_post_status' );
ok( false !== strpos( $review, "apply_filters( 'prospergenics_articles_review_recipients'" ) && false !== strpos( $review, "user_can( \$id, 'edit_post', \$post->ID )" ), 'the editor list is filterable and limited to people who may edit the article' );
ok( false !== strpos( $review, '(int) $author->ID === (int) $actor' ), 'nobody is told about their own action' );
ok( false === strpos( $review, 'wp_mail(' ) && 2 === substr_count( $review, 'prospergenics_profiles_send_mail(' ), 'mail goes through the platform mail helper' );
$mine = $src( 'inc/articles/mine.php' );
ok( false !== strpos( $mine, "current_user_can( 'edit_others_pg_articles' )" ) && false !== strpos( $mine, "'Waiting for review'" ), '"Waiting for review" tab for editors only' );

// ───────────────────────────── ARC-15 Users ─────────────────────────────
echo "Users screen and hidden profiles (ARC-15)\n";
ok( true === prospergenics_profiles_hidden_from( true, 0, 5, false ) && true === prospergenics_profiles_hidden_from( true, 9, 5, false ), 'a hidden profile is hidden from visitors and other members' );
ok( false === prospergenics_profiles_hidden_from( true, 5, 5, false ) && false === prospergenics_profiles_hidden_from( true, 1, 5, true ), '… but not from its owner or staff' );
ok( false === prospergenics_profiles_hidden_from( false, 0, 5, false ) && false === prospergenics_profiles_hidden_from( 0, 0, 0, false ), 'not hidden: privacy settings decide' );
$ua   = $src( 'inc/profiles/users-admin.php' );
$data = $src( 'inc/profiles/data.php' );
foreach ( array( 'manage_users_columns', 'manage_users_custom_column', 'user_row_actions', 'admin_post_pg_profiles_user' ) as $hook ) {
	ok( false !== strpos( $ua, "'$hook'" ), "hooked: $hook" );
}
ok( array( 'resend', 'verify', 'hide', 'unhide' ) === array_keys( prospergenics_profiles_user_actions() ), 'row actions: resend link, mark verified, hide, show' );
ok( false !== strpos( $ua, 'check_admin_referer( prospergenics_profiles_user_action_nonce( $do, $user_id ) )' ) && false !== strpos( $ua, 'if ( ! prospergenics_profiles_can_manage_member( $user_id ) ) {' ) && false !== strpos( $ua, '! prospergenics_profiles_can_manage_member( $user->ID )' ) && false !== strpos( $ua, 'wp_nonce_url(' ), 'row actions (links and handler): nonce per action and member, then prospergenics_profiles_can_manage_member()' );
// WordPress' own rule: edit_user is granted for your own account; edit_users only to staff who manage users.
$GLOBALS['_me'] = 5; $GLOBALS['_caps'] = array();
function current_user_can( $cap, $id = null ) {
	if ( 'edit_user' === $cap ) { return (int) $id === $GLOBALS['_me'] || ! empty( $GLOBALS['_caps']['edit_users'] ); }
	return ! empty( $GLOBALS['_caps'][ $cap ] );
}
ok( ! prospergenics_profiles_can_manage_member( 5 ), 'a member cannot use the row actions on themselves (mark verified, show a hidden profile) (e2e found this)' );
ok( ! prospergenics_profiles_can_manage_member( 9 ), '… nor on someone else' );
$GLOBALS['_caps'] = array( 'edit_users' => true );
ok( prospergenics_profiles_can_manage_member( 9 ) && prospergenics_profiles_can_manage_member( 5 ), 'an administrator (edit_users) can, on anyone' );
ok( false !== strpos( $data, 'prospergenics_profiles_hidden_from( prospergenics_profiles_is_hidden( $owner_id )' ) && false !== strpos( $data, "! prospergenics_profiles_is_hidden( \$user_id )\n" ), 'the flag is honoured by the visibility check (page, REST, bylines) and the index rule (robots, sitemap)' );
ok( false !== strpos( $src( 'inc/profiles/privacy.php' ), "'_pg_profiles_hidden' );" ), 'erasing a member\'s data keeps the staff flag' );

// ───────────────────────────── ARC-20 learner loop ─────────────────────────────
echo "Certificates and course emails (ARC-20)\n";
$d = prospergenics_profiles_privacy_defaults();
ok( isset( $d['certificates'] ) && 'public' === $d['certificates'] && isset( prospergenics_profiles_privacy_labels()['certificates'] ), 'a "certificates" privacy section, as open as the profile by default' );
ok( 'private' === prospergenics_profiles_normalize_privacy( array( 'certificates' => 'private' ) )['certificates'], 'members can make it private' );
$notify = $src( 'inc/courses/notify.php' );
ok( false !== strpos( $notify, "add_action( 'prospergenics_courses_enrolled', 'prospergenics_courses_mail_enrolled', 10, 2 )" ), 'enrolment email' );
ok( false !== strpos( $notify, "add_action( 'prospergenics_courses_certificate_issued', 'prospergenics_courses_mail_certificate', 10, 3 )" ) && false !== strpos( $notify, 'prospergenics_courses_certificate_url( $course )' ) && false !== strpos( $notify, "\$c['verify_url']" ), 'certificate email with the PDF link and the verification page' );
ok( false === strpos( $notify, 'wp_mail(' ) && 2 === substr_count( $notify, 'prospergenics_profiles_send_mail(' ), 'course mail goes through the platform mail helper' );
ok( false !== strpos( $data, "if ( \$vis['certificates'] ) {" ) && false !== strpos( $data, 'prospergenics_courses_certificate_is_revoked( $cert )' ), 'profiles list certificates only when the viewer may see them, revoked ones never' );
$profile_tpl = $src( 'pg-profile.php' );
ok( false !== strpos( $profile_tpl, "\$vis['certificates'] && ! empty( \$data['certificates'] )" ) && false !== strpos( $profile_tpl, "esc_url( \$c['verify_url'] )" ), 'the Certificates section on /in/ links each verification page' );
ok( false !== strpos( $src( 'single-pg_course.php' ), 'prospergenics_courses_next_course_html(' ) && false !== strpos( $src( 'inc/platform-courses.php' ), "'notify'" ), 'the course page suggests a next course after finishing' );

// ───────────────────────────── NAV-16 ─────────────────────────────
echo "Instructor profiles and labels (NAV-16)\n";
ok( false !== strpos( $data, "prospergenics_profiles_published( \$user->ID, 'pg_course' )" ) && false !== strpos( $data, "prospergenics_profiles_published( \$user->ID, 'pg_article' )" ) && false !== strpos( $data, 'prospergenics_courses_instructor_url( $user->ID )' ), 'profiles carry the member\'s published courses (with the instructor page) and articles' );
ok( false !== strpos( $profile_tpl, 'id="pgp-courses"' ) && false !== strpos( $profile_tpl, 'id="pgp-articles"' ), '… shown on /in/' );
$dash = $src( 'courses-dashboard.php' );
ok( 2 === substr_count( $dash, 'Courses I teach' ) && false === strpos( $dash, '>My courses<' ), 'the dashboard tab is "Courses I teach", not the header\'s "My courses"' );

// ───────────────────────────── loaders ─────────────────────────────
echo "Loaders\n";
ok( false !== strpos( $src( 'inc/platform-profiles.php' ), "'applications.php'" ) && false !== strpos( $src( 'inc/platform-profiles.php' ), "'users-admin.php'" ), 'profiles loader requires applications.php and users-admin.php' );
ok( false !== strpos( $src( 'inc/platform-articles.php' ), "'/articles/roles.php'" ) && false !== strpos( $src( 'inc/platform-articles.php' ), "'/articles/review.php'" ), 'articles loader requires roles.php and review.php' );
ok( false !== strpos( $src( 'inc/profiles/privacy.php' ), 'prospergenics_profiles_application_export_items( $uid )' ), 'requests are in the personal data export' );

echo $failures ? "\n$failures FAILED\n" : "\nAll flow tests passed.\n";
exit( $failures ? 1 : 0 );

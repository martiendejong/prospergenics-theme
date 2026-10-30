<?php
/**
 * Standalone test for the staff area at /manage/ (card #6457, inc/platform-manage.php, inc/manage/):
 *   - the pg_manage capability: editors and administrators only, granted at runtime,
 *   - who may change which member: administrators as in wp-admin, editors only non-staff members,
 *     nobody their own account; role changes (editors: subscriber <-> writer only; administrators
 *     up to editor; never administrator),
 *   - the members list query (search, filters, paging), the contact inbox (state, views, reply link),
 *   - the wiring, checked in the generated theme's source: login redirect and 403, nonce before
 *     capability on every handler, COUNT queries with $wpdb->prepare(), the "Manage" account link,
 *     the review queue and publish action shared with /articles/mine/.
 *
 * Run with: php tests/test-platform-manage.php
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
$GLOBALS['_hooks'] = array();
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][ $hook ][] = $cb; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][ $hook ][] = $cb; return true; }

// A small user model: id => [roles, primitive caps]. user 1 admin, 2 editor, 3 second editor,
// 4 author, 5 contributor, 6 writer, 7 subscriber, 8 subscriber.
$role_caps = array(
	'administrator' => array( 'moderate_comments', 'edit_others_posts', 'edit_users', 'promote_users', 'manage_options', 'list_users' ),
	'editor'        => array( 'moderate_comments', 'edit_others_posts', 'edit_posts' ),
	'author'        => array( 'edit_posts', 'publish_posts' ),
	'contributor'   => array( 'edit_posts' ),
	'pg_writer'     => array( 'edit_pg_articles' ),
	'subscriber'    => array( 'read' ),
);
$GLOBALS['_users'] = array( 1 => 'administrator', 2 => 'editor', 3 => 'editor', 4 => 'author', 5 => 'contributor', 6 => 'pg_writer', 7 => 'subscriber', 8 => 'subscriber' );
$GLOBALS['_me']    = 0;
function _caps( $id ) {
	global $role_caps;
	if ( ! isset( $GLOBALS['_users'][ $id ] ) ) {
		return array();
	}
	$all = array_fill_keys( $role_caps[ $GLOBALS['_users'][ $id ] ], true );
	return prospergenics_manage_grant_caps( $all ); // the filter under test
}
function user_can( $id, $cap, ...$args ) {
	$caps = _caps( (int) $id );
	if ( 'edit_user' === $cap || 'promote_user' === $cap ) {
		return ! empty( $caps[ 'edit_user' === $cap ? 'edit_users' : 'promote_users' ] );
	}
	return ! empty( $caps[ $cap ] );
}
function current_user_can( $cap, ...$args ) { return user_can( $GLOBALS['_me'], $cap, ...$args ); }
function get_current_user_id() { return $GLOBALS['_me']; }
function is_user_logged_in() { return $GLOBALS['_me'] > 0; }
function get_userdata( $id ) { return isset( $GLOBALS['_users'][ $id ] ) ? (object) array( 'ID' => $id, 'roles' => array( $GLOBALS['_users'][ $id ] ) ) : false; }

require $theme . '/inc/profiles/users-admin.php'; // prospergenics_profiles_can_manage_member(), reused
require $theme . '/inc/manage/helpers.php';

$src = function ( $file ) use ( $theme ) {
	return (string) file_get_contents( $theme . '/' . $file );
};

// ───────────────────────────── capability ─────────────────────────────
echo "pg_manage: editors and administrators\n";
foreach ( array( 1 => true, 2 => true, 4 => false, 5 => false, 6 => false, 7 => false ) as $id => $want ) {
	ok( $want === user_can( $id, PROSPERGENICS_MANAGE_CAP ), sprintf( '%s %s pg_manage', $GLOBALS['_users'][ $id ], $want ? 'has' : 'does not have' ) );
}
ok( in_array( 'prospergenics_manage_grant_caps', $GLOBALS['_hooks']['user_has_cap'], true ), 'granted through user_has_cap (nothing written to the roles table)' );
$GLOBALS['_me'] = 0;
ok( ! prospergenics_manage_can_access(), 'visitors have no access' );
$GLOBALS['_me'] = 7;
ok( ! prospergenics_manage_can_access(), 'a subscriber has no access' );
$GLOBALS['_me'] = 2;
ok( prospergenics_manage_can_access(), 'an editor has access' );

// ───────────────────────────── who may change whom ─────────────────────────────
echo "who may change which member\n";
$d = 'prospergenics_manage_member_decision';
ok( ! $d( array( 'self' => true, 'exists' => true, 'admin_rule' => true, 'manage' => true ) ), 'nobody changes their own account here, not even an administrator' );
ok( ! $d( array( 'exists' => false, 'admin_rule' => true, 'manage' => true ) ), 'an unknown member: no' );
ok( $d( array( 'exists' => true, 'admin_rule' => true ) ), 'the wp-admin rule (edit_users + edit_user) is enough' );
ok( $d( array( 'exists' => true, 'manage' => true, 'target_staff' => false ) ), 'pg_manage is enough for a member who is not staff' );
ok( ! $d( array( 'exists' => true, 'manage' => true, 'target_staff' => true ) ), 'pg_manage is not enough for staff' );
ok( ! $d( array( 'exists' => true ) ), 'neither: no' );

$GLOBALS['_me'] = 2; // editor
foreach ( array( 1 => false, 3 => false, 2 => false, 4 => true, 5 => true, 6 => true, 7 => true, 99 => false ) as $id => $want ) {
	$who = isset( $GLOBALS['_users'][ $id ] ) ? ( 2 === $id ? 'themself' : 'an ' . $GLOBALS['_users'][ $id ] . " ($id)" ) : 'an unknown user';
	ok( $want === prospergenics_manage_can_manage_member( $id ), sprintf( 'editor %s change %s', $want ? 'may' : 'may not', $who ) );
}
$GLOBALS['_me'] = 1; // administrator
foreach ( array( 1 => false, 2 => true, 4 => true, 7 => true ) as $id => $want ) {
	ok( $want === prospergenics_manage_can_manage_member( $id ), sprintf( 'administrator %s change %s', $want ? 'may' : 'may not', 1 === $id ? 'themself' : 'an ' . $GLOBALS['_users'][ $id ] ) );
}
foreach ( array( 4, 5, 6, 7 ) as $actor ) {
	$GLOBALS['_me'] = $actor;
	ok( ! prospergenics_manage_can_manage_member( 8 ), $GLOBALS['_users'][ $actor ] . ' may not change another member' );
}
ok( prospergenics_manage_is_staff( 1 ) && prospergenics_manage_is_staff( 3 ) && ! prospergenics_manage_is_staff( 4 ) && ! prospergenics_manage_is_staff( 6 ), 'staff: administrators and editors; not authors or writers' );

echo "role changes\n";
$r = 'prospergenics_manage_role_change_allowed';
ok( array( 'subscriber', 'pg_writer' ) === array_keys( prospergenics_manage_role_choices( false ) ), 'editors choose between subscriber and writer' );
ok( array( 'subscriber', 'pg_writer', 'contributor', 'author', 'editor' ) === array_keys( prospergenics_manage_role_choices( true ) ), 'administrators also contributor, author, editor' );
ok( ! isset( prospergenics_manage_role_choices( true )['administrator'] ), 'nobody grants administrator here' );
ok( $r( array( 'subscriber' ), 'pg_writer', false ) && $r( array( 'pg_writer' ), 'subscriber', false ), 'editor: subscriber <-> writer' );
ok( ! $r( array( 'subscriber' ), 'editor', false ) && ! $r( array( 'subscriber' ), 'author', false ) && ! $r( array( 'subscriber' ), 'administrator', false ), 'editor: nothing above writer' );
ok( ! $r( array( 'author' ), 'subscriber', false ) && ! $r( array( 'contributor' ), 'pg_writer', false ), 'editor: cannot demote an author or contributor' );
ok( ! $r( array( 'subscriber', 'author' ), 'pg_writer', false ), 'editor: not for a member with another role besides' );
ok( $r( array( 'subscriber' ), 'editor', true ) && $r( array( 'author' ), 'subscriber', true ), 'administrator: up to editor, and down again' );
ok( ! $r( array( 'subscriber' ), 'administrator', true ), 'administrator: never administrator' );
ok( ! $r( array( 'administrator' ), 'subscriber', true ), 'an administrator\'s role is changed in wp-admin only' );
ok( ! $r( array( 'subscriber' ), 'shop_manager', true ) && ! $r( array( 'subscriber' ), '', true ), 'unknown roles refused' );

// ───────────────────────────── members list ─────────────────────────────
echo "members list\n";
$q = prospergenics_manage_member_query_args( '', 'all', 1 );
ok( 20 === $q['number'] && 1 === $q['paged'] && 'registered' === $q['orderby'] && 'DESC' === $q['order'] && ! isset( $q['search'] ) && ! isset( $q['meta_query'] ), 'everyone, newest first, 20 a page' );
$q = prospergenics_manage_member_query_args( 'amina@', 'all', 0 );
ok( '*amina@*' === $q['search'] && array( 'user_login', 'user_email', 'display_name' ) === $q['search_columns'] && 1 === $q['paged'], 'search by name or email (wildcards both sides); page at least 1' );
$mq = function ( $filter ) { $a = prospergenics_manage_member_query_args( '', $filter, 3 ); return isset( $a['meta_query'][0] ) ? $a['meta_query'][0]['key'] . ' ' . $a['meta_query'][0]['compare'] : ''; };
ok( '_pg_profiles_verify_pending NOT EXISTS' === $mq( 'verified' ) && '_pg_profiles_verify_pending EXISTS' === $mq( 'unverified' ), 'verified / not verified: the pending flag (verify.php)' );
ok( array( 'pg_writer' ) === prospergenics_manage_member_query_args( '', 'writer', 1 )['role__in'], 'writers: the pg_writer role' );
ok( '_pg_courses_instructor EXISTS' === $mq( 'instructor' ) && '_pg_profiles_suspended EXISTS' === $mq( 'suspended' ) && '_pg_profiles_hidden EXISTS' === $mq( 'hidden' ), 'instructors, suspended, hidden: their flags' );
ok( 3 === prospergenics_manage_member_query_args( '', 'verified', 3 )['paged'], 'paging' );
ok( array( 'role', 'verify', 'resend', 'hide', 'unhide', 'suspend', 'unsuspend' ) === array_keys( prospergenics_manage_member_actions() ), 'the member actions' );
ok( 'pg_manage_member_verify_7' === prospergenics_manage_member_nonce( 'verify', '7' ), 'a nonce per action and member' );

// ───────────────────────────── contact inbox ─────────────────────────────
echo "contact inbox\n";
ok( 'unread' === prospergenics_manage_message_state( 0, 0 ) && 'read' === prospergenics_manage_message_state( 100, 0 ) && 'handled' === prospergenics_manage_message_state( 0, 200 ), 'state: unread, read, handled (handled counts as read)' );
$a = prospergenics_manage_message_query_args( 'open', 2 );
ok( 'pg_contact_msg' === $a['post_type'] && 'publish' === $a['post_status'] && '_pg_contact_handled_at' === $a['meta_query'][0]['key'] && 'NOT EXISTS' === $a['meta_query'][0]['compare'] && 2 === $a['paged'], 'open: published messages not handled yet' );
ok( 'draft' === prospergenics_manage_message_query_args( 'spam', 1 )['post_status'], 'spam: the drafts the store parks spam in' );
$a = prospergenics_manage_message_query_args( 'unread', 1 );
ok( 2 === count( $a['meta_query'] ) && '_pg_contact_read_at' === $a['meta_query'][0]['key'], 'unread: neither read nor handled' );
ok( 'EXISTS' === prospergenics_manage_message_query_args( 'handled', 1 )['meta_query'][0]['compare'], 'handled' );
ok( 'mailto:grace@example.test?subject=Re%3A%20Course%20%26%20price' === prospergenics_manage_reply_url( 'grace@example.test', 'Course & price' ), 'reply link: mailto with "Re: subject" encoded' );
ok( 'mailto:a@b.testcc=x@y.test?subject=Re%3A%20x' === prospergenics_manage_reply_url( 'a@b.test?cc=x@y.test', 'x' ), 'reply link: no extra headers from the address' );
ok( 'pg_manage_message_read_12' === prospergenics_manage_message_nonce( 'read', 12 ), 'a nonce per action and message' );
ok( 1000 - 7 * 86400 === prospergenics_manage_since( 7, 1000 ), 'windows: N days before now' );

// ───────────────────────────── wiring (source) ─────────────────────────────
echo "wiring\n";
$routes  = $src( 'inc/manage/routes.php' );
$actions = $src( 'inc/manage/actions.php' );
$stats   = $src( 'inc/manage/stats.php' );
$chrome  = $src( 'inc/site-chrome.php' );
ok( false !== strpos( $src( 'functions.php' ), "require get_template_directory() . '/inc/platform-manage.php';" ), 'functions.php loads the staff area' );
foreach ( array( 'helpers', 'stats', 'routes', 'actions' ) as $m ) {
	ok( false !== strpos( $src( 'inc/platform-manage.php' ), "/manage/$m.php'" ), "the loader requires $m.php" );
}
ok( false !== strpos( $routes, "'^manage/(members|messages|articles)/?$'" ) && false !== strpos( $routes, "'^manage/?$'" ), 'the four URLs' );
ok( (bool) preg_match( '#if \( ! is_user_logged_in\(\) \) \{\s*wp_safe_redirect\( wp_login_url\(#', $routes ), 'visitors: redirect (302) to the login page' );
ok( (bool) preg_match( '#if \( ! prospergenics_manage_can_view\( \$view \) \) \{\s*status_header\( 403 \);#', $routes ), 'everyone else without the capability: 403' );
ok( false !== strpos( $routes, "add_filter( 'wp_robots', 'wp_robots_no_robots' )" ) && false !== strpos( $routes, 'nocache_headers()' ), 'never indexed or cached' );
foreach ( array( 'pg_manage_member' => 'prospergenics_manage_member_nonce( $do, $user_id )', 'pg_manage_message' => 'prospergenics_manage_message_nonce( $do, $post_id )' ) as $hook => $nonce ) {
	$body = substr( $actions, strpos( $actions, "add_action( 'admin_post_$hook'" ) );
	$body = substr( $body, 0, strpos( $body, '} );' ) );
	ok( false !== strpos( $body, "check_admin_referer( $nonce )" ), "$hook checks its nonce" );
	ok( strpos( $body, 'check_admin_referer' ) < strpos( $body, "wp_die( 'Sorry" ) && false !== strpos( $body, ', 403 )' ), "$hook then checks the capability (403)" );
	ok( false === strpos( $actions, "admin_post_nopriv_$hook" ), "$hook has no handler for visitors" );
}
ok( false !== strpos( $actions, '! prospergenics_manage_can_access() || ! prospergenics_manage_can_manage_member( $user_id )' ), 'member actions: pg_manage and that member' );
ok( false !== strpos( $actions, "prospergenics_manage_role_change_allowed( (array) \$user->roles, \$role, current_user_can( 'promote_user', \$user_id ) )" ), 'role changes: checked against what the actor may give' );
ok( false !== strpos( $actions, 'current_user_can( PROSPERGENICS_MODERATION_CAP )' ), 'suspend / unsuspend: also pg_moderate' );
foreach ( array( 'prospergenics_profiles_mark_verified(', 'prospergenics_profiles_send_verification(', 'prospergenics_profiles_set_hidden(', 'prospergenics_profiles_suspend(', 'prospergenics_profiles_unsuspend(' ) as $helper ) {
	ok( false !== strpos( $actions, $helper ), "reuses $helper)" );
}
ok( false !== strpos( $actions, "current_user_can( 'edit_post', \$post->ID )" ) && false !== strpos( $actions, "'pg_contact_msg' !== \$post->post_type" ), 'message actions: a contact message the user may edit' );
ok( false !== strpos( $actions, 'wp_verify_nonce( $nonce, prospergenics_manage_message_nonce( \'read\', $post->ID ) )' ), 'the "Open" link marks read only with its nonce' );
ok( false !== strpos( $actions, "0 !== strpos( \$back, home_url( '/manage/' ) )" ), 'actions only return to /manage/ pages' );
ok( substr_count( $stats, 'COUNT(*)' ) >= 5 && false === strpos( $stats, 'get_posts(' ) && false === strpos( $stats, 'get_users(' ), 'the overview counts with COUNT queries, never by loading records' );
ok( 4 === substr_count( $stats, '$wpdb->prepare(' ), 'every query with a value uses $wpdb->prepare()' );
ok( false !== strpos( $src( 'inc/profiles/moderation.php' ), "SELECT COUNT(*) FROM {\$wpdb->posts} p" ) && false !== strpos( $src( 'inc/profiles/applications.php' ), 'SELECT COUNT(*) FROM {$wpdb->usermeta}' ), 'open reports and open requests are COUNT queries too' );
ok( false !== strpos( $chrome, "prospergenics_manage_can_access() ) {" ) && false !== strpos( $chrome, "'key' => 'manage', 'label' => 'Manage', 'url' => home_url( '/manage/' )" ), 'the account menu offers Manage to staff only' );
$mine = $src( 'inc/articles/mine.php' );
ok( false !== strpos( $src( 'inc/manage/views/articles.php' ), 'prospergenics_articles_review_queue_args(' ) && false !== strpos( $src( 'inc/articles/views/mine.php' ), 'prospergenics_articles_review_queue_args(' ), 'one review queue for /articles/mine/ and /manage/articles/' );
ok( false !== strpos( $src( 'inc/manage/views/articles.php' ), 'value="pg_articles_publish"' ) && false !== strpos( $src( 'inc/manage/views/articles.php' ), "wp_nonce_field( 'pg_articles_publish_' . \$pgm_post->ID )" ), '/manage/articles/ uses the existing publish action and nonce' );
ok( false !== strpos( $mine, "'manage' === \$from && function_exists( 'prospergenics_manage_url' )" ) && false !== strpos( $mine, "prospergenics_manage_url( 'articles', array( 'done' => \$args['done'] ) )" ), 'the publish action returns to the queue it came from (a fixed URL, no open redirect)' );
ok( false !== strpos( $src( 'inc/manage/views/members.php' ), 'prospergenics_manage_can_manage_member( $pgm_uid )' ), 'the list only offers actions for members this user may change' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

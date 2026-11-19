<?php
/**
 * Standalone test for the staff area at /manage/ (card #6457, inc/platform-manage.php, inc/manage/):
 *   - the pg_manage capability: editors and administrators only, granted at runtime,
 *   - who may change which member: administrators as in wp-admin, editors only non-staff members,
 *     nobody their own account; role changes (editors: subscriber <-> writer only; administrators
 *     up to editor; never administrator),
 *   - the members list query (search on name, username or email; filters; sort; 25/50/100 per page;
 *     paging; the summary chips and their count-only queries; shareable links), the contact inbox
 *     (state, views, reply link),
 *   - the members screen rendered with stubs: a real table, one Manage disclosure button per row,
 *     every form with its own nonce, and the search, filters, sort, per page and page kept in the
 *     paging links and in each action's way back,
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
ok( 25 === $q['number'] && 1 === $q['paged'] && 'registered' === $q['orderby'] && 'DESC' === $q['order'] && ! isset( $q['search'] ) && ! isset( $q['meta_query'] ), 'everyone, newest first, 25 a page' );
ok( 20 === PROSPERGENICS_MANAGE_PER_PAGE && 25 === PROSPERGENICS_MANAGE_MEMBERS_PER_PAGE, 'messages and articles keep 20 a page; members 25' );
$q = prospergenics_manage_member_query_args( 'amina@', 'all', 0 );
ok( '*amina@*' === $q['search'] && array( 'user_login', 'user_email', 'display_name' ) === $q['search_columns'] && 1 === $q['paged'], 'search by name, username or email (wildcards both sides); page at least 1' );
ok( in_array( 'user_login', prospergenics_manage_member_query_args( 'tm012', 'all', 1 )['search_columns'], true ), 'the search matches the username (user_login)' );

echo "members list: sort, per page, chips, links\n";
$s = function ( $sort ) { $a = prospergenics_manage_member_query_args( '', 'all', 1, 25, $sort ); return $a['orderby'] . ' ' . $a['order']; };
ok( 'registered DESC' === $s( 'newest' ) && 'registered ASC' === $s( 'oldest' ) && 'display_name ASC' === $s( 'name' ) && 'display_name DESC' === $s( 'name_desc' ), 'sort: newest, oldest, name A–Z, name Z–A' );
ok( 'registered DESC' === $s( 'user_pass' ) && 'registered DESC' === $s( '' ), 'an unknown sort is the default (never a column from the URL)' );
ok( array( 'newest', 'oldest', 'name', 'name_desc' ) === array_keys( prospergenics_manage_member_sorts() ), 'the sort select: Newest (default), Oldest, Name A–Z, Name Z–A' );
ok( array( 25, 50, 100 ) === prospergenics_manage_member_per_page_choices(), 'per page: 25, 50, 100' );
ok( 50 === prospergenics_manage_clean_per_page( '50' ) && 100 === prospergenics_manage_clean_per_page( 100 ) && 25 === prospergenics_manage_clean_per_page( 5000 ) && 25 === prospergenics_manage_clean_per_page( 0 ) && 25 === prospergenics_manage_clean_per_page( -1 ), 'per page: only the choices; anything else is 25 (no 5000-member pages)' );
ok( 100 === prospergenics_manage_member_query_args( '', 'all', 2, 100 )['number'] && 2 === prospergenics_manage_member_query_args( '', 'all', 2, 100 )['paged'], 'per page reaches the query' );
$rf = function ( $filter ) { $a = prospergenics_manage_member_query_args( '', $filter, 1 ); return isset( $a['role__in'] ) ? implode( ',', $a['role__in'] ) : ''; };
ok( 'administrator' === $rf( 'admin' ) && 'editor' === $rf( 'editor' ) && 'author' === $rf( 'author' ) && 'pg_writer' === $rf( 'writer' ) && 'subscriber' === $rf( 'member' ), 'role filters: admins, editors, authors, writers, members (subscribers)' );
ok( '' === $rf( 'unverified' ) && '' === $rf( 'all' ) && ! isset( prospergenics_manage_member_query_args( '', 'admin', 1 )['meta_query'] ), 'role filters add no meta query; the others no role' );
ok( array( 'all', 'admin', 'author', 'member', 'unverified', 'suspended', 'hidden' ) === array_keys( prospergenics_manage_member_chips() ), 'chips: All, Admins, Authors, Members, Not verified, Suspended, Hidden' );
ok( ! array_diff( array_keys( prospergenics_manage_member_chips() ), array_keys( prospergenics_manage_member_filters() ) ), 'every chip is a filter of the Show select' );
$c = prospergenics_manage_member_count_args( 'suspended' );
ok( 1 === $c['number'] && 'ID' === $c['fields'] && true === $c['count_total'] && '_pg_profiles_suspended' === $c['meta_query'][0]['key'], 'chip counts for flags: count-only (one ID, the total)' );
ok( 'NOT EXISTS' !== prospergenics_manage_member_count_args( 'unverified' )['meta_query'][0]['compare'] && '_pg_profiles_hidden' === prospergenics_manage_member_count_args( 'hidden' )['meta_query'][0]['key'], 'chip counts: not verified, hidden' );
$helpers = $src( 'inc/manage/helpers.php' );
$counts  = substr( $helpers, strpos( $helpers, 'function prospergenics_manage_member_chip_counts()' ) );
$counts  = substr( $counts, 0, strpos( $counts, "\n}\n" ) );
ok( false !== strpos( $counts, 'count_users()' ) && false !== strpos( $counts, 'new WP_User_Query( prospergenics_manage_member_count_args( $key ) )' ) && false === strpos( $counts, 'get_users(' ) && false === strpos( $counts, 'get_results(' ), 'chip counts: count_users() for roles, count-only WP_User_Query for the flags, no records loaded' );
$l = 'prospergenics_manage_member_list_args';
ok( array() === $l( array() ) && array() === $l( array( 's' => '', 'filter' => 'all', 'tier' => '', 'sort' => 'newest', 'per' => 25, 'pg' => 1 ) ), 'list links: defaults are left out (/manage/members/)' );
ok( array( 's' => 'ami', 'filter' => 'unverified', 'tier' => 'none', 'sort' => 'name', 'per' => '50', 'pg' => '3' ) === $l( array( 's' => 'ami', 'filter' => 'unverified', 'tier' => 'none', 'sort' => 'name', 'per' => 50, 'pg' => 3 ) ), 'list links: search, filter, badge, sort, per page and page all kept' );
$r = 'prospergenics_manage_page_range';
ok( array( 26, 50 ) === $r( 2, 25, 312 ) && array( 1, 25 ) === $r( 1, 25, 312 ) && array( 301, 312 ) === $r( 13, 25, 312 ) && array( 0, 0 ) === $r( 1, 25, 0 ) && array( 1, 7 ) === $r( 0, 25, 7 ), '"Showing 26–50 of 312": the range of a page' );
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
$js = $src( 'assets/platform/manage.js' );
ok( false !== strpos( $routes, "if ( 'members' === prospergenics_manage_view() ) {" ) && false !== strpos( $routes, "wp_enqueue_script( 'pg-manage'" ), 'manage.js only on /manage/members/' );
ok( false !== strpos( $js, "e.key === 'Escape'" ) && false !== strpos( $js, 'close(true)' ) && false !== strpos( $js, 'if (focus) { btn.focus(); }' ), 'manage.js: Esc closes the Manage panel and returns the focus to its button' );
ok( false !== strpos( $js, "setAttribute('aria-expanded', 'true')" ) && false !== strpos( $js, "setAttribute('aria-expanded', 'false')" ), 'manage.js: the button tells whether the panel is open (aria-expanded)' );
ok( false !== strpos( $js, 'setTimeout(' ) && false !== strpos( $js, 'clearTimeout(timer)' ) && false !== strpos( $js, 'form.requestSubmit' ), 'manage.js: the search is submitted after typing stops (debounced)' );

// ───────────────────────────── the members screen, rendered ─────────────────────────────
echo "members screen (rendered with stubs)\n";
class WP_User {
	public $ID, $display_name, $user_email, $user_registered, $roles;
	function __construct( $id, $name, $email, $roles ) { $this->ID = $id; $this->display_name = $name; $this->user_email = $email; $this->user_registered = '2026-10-25 10:00:00'; $this->roles = $roles; }
}
class WP_User_Query {
	public $args;
	function __construct( $args ) { $this->args = $args; $GLOBALS['_uq'][] = $args; }
	function get_total() { return 'ID' === ( $this->args['fields'] ?? '' ) ? 3 : 312; }
	function get_results() {
		return array(
			new WP_User( 2, 'Me Editor', 'me@example.test', array( 'editor' ) ),
			new WP_User( 3, 'Other Editor', 'other@example.test', array( 'editor' ) ),
			new WP_User( 7, 'Sam <Sub>', 'sam@example.test', array( 'subscriber' ) ),
		);
	}
}
const PROSPERGENICS_COURSES_INSTRUCTOR_META = '_pg_courses_instructor';
const PROSPERGENICS_MODERATION_CAP          = 'moderate_comments';
const PROSPERGENICS_MANAGE_VERSION          = '1';
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url_raw( $s ) { return (string) $s; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_key( $s ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $s ) ); }
function wp_unslash( $s ) { return $s; }
function absint( $n ) { return abs( (int) $n ); }
function home_url( $p = '' ) { return 'http://pg.test' . $p; }
function admin_url( $p = '' ) { return 'http://pg.test/wp-admin/' . $p; }
function add_query_arg( $args, $url, $url3 = null ) {
	if ( null !== $url3 ) { // add_query_arg( key, value, url )
		$args = array( $args => $url );
		$url  = $url3;
	}
	$parts = explode( '?', $url, 2 );
	parse_str( $parts[1] ?? '', $q );
	return $parts[0] . '?' . http_build_query( array_merge( $q, $args ) );
}
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { return '<input type="hidden" id="' . $name . '" name="' . $name . '" value="nonce:' . $action . '" />'; }
function selected( $a, $b, $echo = true ) { return (string) $a === (string) $b ? ' selected="selected"' : ''; }
function number_format_i18n( $n ) { return number_format( (float) $n ); }
function _n( $one, $many, $n ) { return 1 === (int) $n ? $one : $many; }
function wp_list_pluck( $list, $field ) { $out = array(); foreach ( $list as $k => $v ) { $out[ $k ] = is_object( $v ) ? $v->$field : $v[ $field ]; } return $out; }
function wp_roles() { return (object) array( 'role_names' => array( 'editor' => 'Editor', 'subscriber' => 'Subscriber' ) ); }
function translate_user_role( $r ) { return $r; }
function get_user_meta( $id, $key, $single = false ) { return ''; }
function mysql2date( $f, $d ) { return 'c' === $f ? '2026-10-25T10:00:00+00:00' : '25 Oct 2026'; }
function count_users() { return array( 'total_users' => 312, 'avail_roles' => array( 'administrator' => 2, 'author' => 9, 'subscriber' => 280 ) ); }
function paginate_links( $a ) { return '<a class="page-numbers" href="' . str_replace( array( '%#%', '%25%23%25' ), '3', $a['base'] ) . '">3</a>'; }
function get_header() {}
function get_footer() {}
function prospergenics_profiles_badge_tiers() { return array( 'member' => array( 'label' => 'Member' ), 'mentor' => array( 'label' => 'Mentor' ) ); }
function prospergenics_profiles_rank_backfill( $n ) {}
function prospergenics_profiles_badges_prime( $ids ) { $GLOBALS['_primed'] = $ids; }
function prospergenics_profiles_is_verified( $id ) { return 7 !== $id; }
function prospergenics_profiles_is_hidden( $id ) { return false; }
function prospergenics_profiles_is_suspended( $id ) { return false; }
function prospergenics_profiles_mail_status( $id, $kind ) { return null; }
function prospergenics_profiles_rank( $id ) { return array( 'score' => 12 ); }
function prospergenics_profiles_badge_html( $id ) { return '<span data-pg-badge="member">Member</span>'; }
function prospergenics_profiles_photo_url( $id, $size ) { return ''; }
function prospergenics_profiles_initials( $name ) { return strtoupper( $name[0] ); }
function prospergenics_profiles_url( $id ) { return 'http://pg.test/in/' . $id . '/'; }
function prospergenics_profiles_can_be_suspended( $id, $by ) { return 7 === $id; }
require $theme . '/inc/manage/routes.php';
function _render( $get ) {
	global $theme;
	$_GET = $get; $GLOBALS['_uq'] = array();
	ob_start();
	include $theme . '/inc/manage/views/members.php';
	return ob_get_clean();
}
$GLOBALS['_me'] = 2; // editor (pg_manage, moderate_comments)
$html = _render( array( 's' => 'sam', 'filter' => 'member', 'sort' => 'name', 'per' => '50', 'pg' => '2', 'tier' => 'mentor' ) );
$main = $GLOBALS['_uq'][0];
ok( '*sam*' === $main['search'] && 'display_name' === $main['orderby'] && 'ASC' === $main['order'] && 50 === $main['number'] && 2 === $main['paged'] && array( 'subscriber' ) === $main['role__in'] && 'mentor' === $main['meta_query'][0]['value'], 'the URL parameters reach the query: search, sort, per page, page, filter, badge' );
ok( 4 === count( $GLOBALS['_uq'] ) && 3 === count( array_filter( $GLOBALS['_uq'], function ( $a ) { return 'ID' === ( $a['fields'] ?? '' ); } ) ), 'the chips cost three count-only queries (not verified, suspended, hidden)' );
ok( 1 === preg_match( '#<table class="pgm-mtable[^"]*">\s*<caption#', $html ) && 1 === substr_count( $html, '<thead>' ) && 6 === substr_count( $html, '<th scope="col"' ) && 3 === substr_count( $html, '<th scope="row" class="pgm-who">' ), 'a real table: caption, six column headers, one row header per member' );
ok( 3 === substr_count( $html, 'class="pgm-btn pgm-btn-ghost pgm-manage-btn" aria-expanded="false" aria-controls="pgm-panel-' ) && false !== strpos( $html, 'id="pgm-panel-7" role="group" aria-label="Manage Sam &lt;Sub&gt;"' ), 'one Manage disclosure button per row (aria-expanded, aria-controls its panel)' );
ok( false !== strpos( $html, 'type="button" class="pgm-btn pgm-btn-ghost pgm-manage-btn"' ), 'Manage is a button that submits nothing' );
foreach ( array( 'role', 'verify', 'resend', 'hide', 'suspend' ) as $d ) {
	ok( 1 === preg_match( '#name="do" value="' . $d . '" />\s*<input type="hidden" name="user_id" value="7" /><input type="hidden" name="pg_back" value="[^"]*" /><input type="hidden" id="_wpnonce" name="_wpnonce" value="nonce:pg_manage_member_' . $d . '_7" />#', $html ), "the member's \"$d\" form keeps its own nonce" );
}
ok( false === strpos( $html, 'pg_manage_member_role_3' ) && false !== strpos( $html, 'Staff account: only administrators change it, in wp-admin.' ), 'another editor: no actions, the staff note' );
ok( false === strpos( $html, 'pg_manage_member_role_2' ) && false !== strpos( $html, 'This is you: change your own account in Settings.' ), 'your own row: no actions, "This is you…"' );
preg_match( '#name="pg_back" value="([^"]*)"#', $html, $m );
$back = html_entity_decode( $m[1] ?? '' );
parse_str( (string) parse_url( $back, PHP_URL_QUERY ), $bq );
ok( 0 === strpos( $back, 'http://pg.test/manage/members/?' ) && array( 's' => 'sam', 'filter' => 'member', 'tier' => 'mentor', 'sort' => 'name', 'per' => '50', 'pg' => '2' ) === $bq, 'after an action the member returns to the same page, search, filter, badge, sort and per page' );
ok( 1 === preg_match( '#class="page-numbers" href="[^"]*s=sam[^"]*filter=member[^"]*tier=mentor[^"]*sort=name[^"]*per=50[^"]*pg=3"#', $html ), 'paging links keep the search, sort and per page' );
ok( false !== strpos( $html, '312 results for “sam”' ) && 1 === preg_match( '#<a class="pgm-clear" href="http://pg\.test/manage/members/\?filter=member&amp;tier=mentor&amp;sort=name&amp;per=50">Clear search#', $html ), '"N results for \'x\'" with a Clear search link that keeps the rest' );
ok( false !== strpos( $html, 'Showing 51–100 of 312' ), '"Showing 51–100 of 312"' );
ok( 7 === substr_count( $html, 'class="pgm-chip"' ) && 1 === preg_match( '#href="http://pg\.test/manage/members/\?filter=member&amp;sort=name&amp;per=50" aria-current="page">Members <span class="pgm-chip-count">280</span>#', $html ) && 1 === preg_match_all( '#class="pgm-chip"[^>]*aria-current#', $html ) && false !== strpos( $html, '>All <span class="pgm-chip-count">312</span>' ) && false !== strpos( $html, '>Admins <span class="pgm-chip-count">2</span>' ) && false !== strpos( $html, '>Suspended <span class="pgm-chip-count">3</span>' ), 'chips with counts; the current one has aria-current; they keep sort and per page' );
foreach ( array( 'pgm-sort' => 'value="name" selected="selected"', 'pgm-per' => 'value="50" selected="selected"', 'pgm-filter' => 'value="member" selected="selected"' ) as $id => $want ) {
	$sel = substr( $html, strpos( $html, 'id="' . $id . '"' ) );
	ok( false !== strpos( substr( $sel, 0, strpos( $sel, '</select>' ) ), $want ), "the $id select shows the current choice" );
}
ok( false !== strpos( $html, 'id="pgm-s" name="s" value="sam"' ) && false !== strpos( $html, 'Name, username or email' ), 'the search box: name, username or email, keeps what was searched' );
ok( false !== strpos( $html, 'Sam &lt;Sub&gt;' ) && false === strpos( $html, 'Sam <Sub>' ), 'names are escaped' );
ok( array( 2, 3, 7 ) === $GLOBALS['_primed'], 'the page\'s members are primed in one read' );
$html = _render( array( 'per' => '5000', 'sort' => 'user_pass', 'filter' => 'nope' ) );
$main = $GLOBALS['_uq'][0];
ok( 25 === $main['number'] && 'registered' === $main['orderby'] && ! isset( $main['role__in'] ) && 1 === preg_match( '#href="http://pg\.test/manage/members/" aria-current="page">All#', $html ), 'unknown values fall back: 25 a page, newest first, everyone' );
$html = _render( array( 'pg' => '99' ) );
ok( 13 === $GLOBALS['_uq'][1]['paged'] && false !== strpos( $html, 'Showing 301–312 of 312' ), 'a page past the end shows the last page' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

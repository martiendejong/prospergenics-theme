<?php
/**
 * Standalone tests for the platform role separation (card #6341): member, company, instructor, admin.
 *   - the roles and their capabilities, assigning them, the Users → Edit field,
 *   - the one wp-admin exception (inc/account.php): a company account opens the company profile
 *     screens (list, new, its own profiles) and nothing else; members and instructors none of them,
 *   - the "Create your company profile" / "Your company profile" account menu link,
 *   - course creation gated to instructors: the wizard at /instructor/courses/new/ and its REST handler.
 * Run with: php tests/test-platform-roles.php
 */
$theme    = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; } else { echo "  FAIL  $msg\n"; $failures++; }
}

define( 'ABSPATH', sys_get_temp_dir() . '/pg-roles-test/' );
$GLOBALS['hooks'] = array(); $GLOBALS['users'] = array(); $GLOBALS['meta'] = array(); $GLOBALS['roles'] = array();
$GLOBALS['current'] = 0; $GLOBALS['fired'] = array(); $GLOBALS['cpts'] = array();
function add_action( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { $GLOBALS['hooks'][ $h ][] = $cb; }
function do_action( $h, ...$args ) { $GLOBALS['fired'][] = array_merge( array( $h ), $args ); }
function get_option( $k ) { return null; }
function get_role( $s ) { return null; }
function add_role( $s, $l, $c ) { $GLOBALS['roles'][ $s ] = $c; }
function remove_role( $s ) { unset( $GLOBALS['roles'][ $s ] ); }
function update_option( $k, $v, $a = null ) {}
function register_taxonomy( $t, $o, $a = array() ) {}
function register_post_meta( ...$a ) {}
function register_meta( ...$a ) {}
function register_post_type( $t, $a ) { $GLOBALS['cpts'][ $t ] = $a; }
function get_user_meta( $id, $k, $s = false ) { return $GLOBALS['meta'][ $id ][ $k ] ?? ''; }
function update_user_meta( $id, $k, $v ) { $GLOBALS['meta'][ $id ][ $k ] = $v; }
function delete_user_meta( $id, $k ) { unset( $GLOBALS['meta'][ $id ][ $k ] ); }
function clean_user_cache( $id ) {}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function esc_html( $s ) { return $s; }
class WP_Error { public $code; public function __construct( $c = '', $m = '' ) { $this->code = $c; } }
class WP_User {
	public $ID; public $roles;
	public function __construct( $id, $roles ) { $this->ID = $id; $this->roles = $roles; }
	public function add_role( $r ) { if ( ! in_array( $r, $this->roles, true ) ) { $this->roles[] = $r; } }
	public function remove_role( $r ) { $this->roles = array_values( array_diff( $this->roles, array( $r ) ) ); }
}
function mkuser( $id, $roles ) { $GLOBALS['users'][ $id ] = new WP_User( $id, $roles ); return $GLOBALS['users'][ $id ]; }
function get_userdata( $id ) { return $GLOBALS['users'][ $id ] ?? false; }
// Capabilities from the roles the stub user carries, then the theme's user_has_cap filters.
function user_caps( $user ) {
	$caps = array();
	$def  = array(
		'administrator' => array( 'edit_others_posts' => true, 'promote_users' => true ),
		'subscriber'    => array( 'read' => true ),
	);
	foreach ( $user->roles as $r ) {
		$caps += $def[ $r ] ?? ( $GLOBALS['roles'][ $r ] ?? array() );
	}
	foreach ( $GLOBALS['hooks']['user_has_cap'] ?? array() as $cb ) {
		$caps = $cb( $caps, array(), array(), $user );
	}
	return $caps;
}
function user_can( $user, $cap, ...$args ) {
	if ( is_int( $user ) ) { $user = get_userdata( $user ); }
	if ( $user && 'edit_post' === $cap ) { // map_meta_cap, as WordPress does it: your own post, or edit_others_*
		$post = $GLOBALS['posts'][ (int) ( $args[0] ?? 0 ) ] ?? null;
		if ( ! $post ) { return false; }
		$type = 'post' === $post['type'] ? 'posts' : $post['type'] . 's';
		return ! empty( user_caps( $user )[ ( $post['author'] === $user->ID ? 'edit_' : 'edit_others_' ) . $type ] );
	}
	return $user && ! empty( user_caps( $user )[ $cap ] );
}
function current_user_can( $cap, ...$args ) { return $GLOBALS['current'] && user_can( $GLOBALS['current'], $cap, ...$args ); }
function is_user_logged_in() { return $GLOBALS['current'] > 0; }
function get_current_user_id() { return $GLOBALS['current']; }
$GLOBALS['posts'] = array();
function get_post_type( $id ) { return $GLOBALS['posts'][ (int) $id ]['type'] ?? false; }
function get_posts( $q ) {
	$ids = array();
	foreach ( $GLOBALS['posts'] as $id => $p ) {
		if ( $p['type'] === $q['post_type'] && $p['author'] === $q['author'] && in_array( $p['status'], $q['post_status'], true ) ) { $ids[] = $id; }
	}
	rsort( $ids );
	return array_slice( $ids, 0, $q['posts_per_page'] );
}
function home_url( $p = '' ) { return 'https://pg.test' . $p; }
function admin_url( $p = '' ) { return 'https://pg.test/wp-admin/' . $p; }
function prospergenics_profiles_url( $id ) { return 'https://pg.test/in/member-' . $id . '/'; }

require $theme . '/inc/courses/data-model.php';
$GLOBALS['hooks']['init'] = array(); // the courses init hooks need a full WordPress; only the roles module runs here
require $theme . '/inc/platform-roles.php';
foreach ( $GLOBALS['hooks']['init'] as $cb ) { $cb(); }
require $theme . '/inc/account.php';      // the wp-admin redirect and its company exception
require $theme . '/inc/courses/rest.php'; // the course REST handler's permission callbacks

echo "Wiring\n";
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "/inc/platform-roles.php'" ), 'functions.php requires the roles module' );
ok( isset( $GLOBALS['roles']['pg_company'], $GLOBALS['roles']['pg_instructor'] ), 'pg_company and pg_instructor roles are created' );
ok( isset( $GLOBALS['cpts']['pg_company_profile'] ), 'the company profile type is registered' );

echo "Capabilities per role\n";
mkuser( 1, array( 'subscriber' ) ); mkuser( 2, array( 'pg_company' ) ); mkuser( 3, array( 'pg_instructor' ) ); mkuser( 4, array( 'administrator' ) );
foreach ( array( 1 => 'member', 2 => 'company', 3 => 'instructor', 4 => 'admin' ) as $id => $role ) {
	ok( prospergenics_roles_get( $id ) === $role, "user $id is a $role" );
}
ok( ! prospergenics_roles_can_create_course( 1 ) && ! prospergenics_roles_can_create_company_profile( 1 ), 'member: no courses, no company profile' );
ok( ! prospergenics_roles_can_create_course( 2 ) && prospergenics_roles_can_create_company_profile( 2 ), 'company: company profile, no courses' );
ok( prospergenics_roles_can_create_course( 3 ) && ! prospergenics_roles_can_create_company_profile( 3 ), 'instructor: courses, no company profile' );
ok( prospergenics_roles_can_create_course( 4 ) && prospergenics_roles_can_create_company_profile( 4 ), 'admin: both' );
ok( ! user_can( 3, 'edit_others_pg_courses' ) && ! user_can( 2, 'edit_others_pg_company_profiles' ), 'instructors and companies cannot touch items of others' );

echo "Assigning\n";
ok( true === prospergenics_roles_assign( 1, 'instructor' ), 'member becomes instructor' );
ok( in_array( 'pg_instructor', $GLOBALS['users'][1]->roles, true ) && ! in_array( 'subscriber', $GLOBALS['users'][1]->roles, true ), 'WordPress role replaced' );
ok( prospergenics_roles_get( 1 ) === 'instructor' && prospergenics_roles_can_create_course( 1 ), 'and can now create courses' );
ok( $GLOBALS['fired'][0][0] === 'prospergenics_platform_role_changed' && $GLOBALS['fired'][0][2] === 'instructor' && $GLOBALS['fired'][0][3] === 'member', 'the IAM sync hook fires with new and old role' );
ok( $GLOBALS['meta'][1][ PROSPERGENICS_ROLES_META ] === 'instructor', 'role mirrored in user meta' );
prospergenics_roles_assign( 1, 'company' );
ok( prospergenics_roles_get( 1 ) === 'company' && ! prospergenics_roles_can_create_course( 1 ), 'instructor to company: course rights gone, legacy flag cleared' );
prospergenics_roles_assign( 1, 'member' );
ok( prospergenics_roles_get( 1 ) === 'member' && $GLOBALS['users'][1]->roles === array( 'subscriber' ), 'back to member (subscriber)' );
$n = count( $GLOBALS['fired'] ); prospergenics_roles_assign( 1, 'member' );
ok( count( $GLOBALS['fired'] ) === $n, 'no event when nothing changed' );
ok( is_wp_error( prospergenics_roles_assign( 1, 'admin' ) ) && is_wp_error( prospergenics_roles_assign( 1, 'bogus' ) ), 'admin and unknown roles cannot be assigned here' );
ok( is_wp_error( prospergenics_roles_assign( 4, 'member' ) ) && $GLOBALS['users'][4]->roles === array( 'administrator' ), 'an administrator is never demoted' );
mkuser( 5, array( 'subscriber' ) ); update_user_meta( 5, PROSPERGENICS_COURSES_INSTRUCTOR_META, 1 );
ok( prospergenics_roles_get( 5 ) === 'instructor' && prospergenics_roles_can_create_course( 5 ), 'members approved before the roles existed (legacy flag) still count as instructors' );

echo "Profile screen\n";
$src = file_get_contents( $theme . '/inc/platform-roles.php' );
ok( false !== strpos( $src, "current_user_can( 'promote_users' )" ) && false !== strpos( $src, 'wp_verify_nonce' ), 'the field is saved only with promote_users and a valid nonce' );
$app = file_get_contents( $theme . '/inc/profiles/applications.php' );
ok( false !== strpos( $app, "prospergenics_roles_assign( \$member->ID, 'instructor' )" ), 'an approved "Apply to teach" goes through the same assignment' );

// WordPress' own user save (edit_user() → set_role() with the main Role field) runs after the
// *_profile_update hooks and drops every other role: the platform role is applied again on
// profile_update (seen in a real WordPress: "Company" on Users → Edit did not stick before this).
$roles_src = file_get_contents( $theme . '/inc/platform-roles.php' );
ok( (bool) preg_match( "#add_action\\( 'profile_update', function \\( \\\$saved_id \\) use \\( \\\$user_id, \\\$role \\) \\{\\s*if \\( \\(int\\) \\\$saved_id === \\(int\\) \\\$user_id \\) \\{\\s*prospergenics_roles_assign\\( \\(int\\) \\\$user_id, \\\$role \\);#", $roles_src ), 'the platform role is applied again after WordPress saves the main Role field' );

echo "wp-admin: the company profile screens, and only those (inc/account.php)\n";
mkuser( 20, array( 'pg_company' ) ); mkuser( 21, array( 'subscriber' ) ); mkuser( 22, array( 'pg_instructor' ) ); mkuser( 23, array( 'pg_company' ) );
$GLOBALS['posts'] = array(
	501 => array( 'type' => 'pg_company_profile', 'author' => 20, 'status' => 'publish' ), // company 20's own
	502 => array( 'type' => 'pg_company_profile', 'author' => 23, 'status' => 'draft' ),   // another company's
	503 => array( 'type' => 'post', 'author' => 4, 'status' => 'publish' ),
	504 => array( 'type' => 'pg_course', 'author' => 22, 'status' => 'publish' ),
);
$go = function ( $user, $script, $post_type = '', $post_id = 0 ) {
	return prospergenics_2026_admin_redirect_target( $script, false, prospergenics_2026_is_staff( $user ), $user, $post_type, $post_id );
};
$company_screens = array(
	'the list (edit.php?post_type=pg_company_profile)'         => array( 'edit.php', 'pg_company_profile', 0 ),
	'a new profile (post-new.php?post_type=pg_company_profile)' => array( 'post-new.php', 'pg_company_profile', 0 ),
	'its own profile (post.php?post=501)'                       => array( 'post.php', '', 501 ),
);
ok( ! prospergenics_2026_is_staff( 20 ) && ! prospergenics_2026_is_staff( 21 ) && ! prospergenics_2026_is_staff( 22 ), 'company, member and instructor accounts are not staff (no admin bar, kept out of wp-admin)' );
foreach ( $company_screens as $what => $s ) {
	ok( '' === $go( 20, $s[0], $s[1], $s[2] ), "company: opens $what" );
}
$elsewhere = array(
	'the dashboard (index.php)'                      => array( 'index.php', '', 0 ),
	'posts (edit.php)'                               => array( 'edit.php', '', 0 ),
	'posts (edit.php?post_type=post)'                => array( 'edit.php', 'post', 0 ),
	'a new post (post-new.php)'                      => array( 'post-new.php', '', 0 ),
	'a new course (post-new.php?post_type=pg_course)' => array( 'post-new.php', 'pg_course', 0 ),
	'courses (edit.php?post_type=pg_course)'         => array( 'edit.php', 'pg_course', 0 ),
	'Profile (profile.php)'                          => array( 'profile.php', '', 0 ),
	'Users (users.php)'                              => array( 'users.php', '', 0 ),
	'Media (upload.php?post_type=pg_company_profile)' => array( 'upload.php', 'pg_company_profile', 0 ),
	"another company's profile (post.php?post=502)"  => array( 'post.php', '', 502 ),
	'a blog post (post.php?post=503)'                => array( 'post.php', '', 503 ),
	'a blog post, with ?post_type=pg_company_profile added (post.php?post=503)' => array( 'post.php', 'pg_company_profile', 503 ),
	'a course (post.php?post=504)'                   => array( 'post.php', '', 504 ),
	'post.php without a post'                        => array( 'post.php', '', 0 ),
	'an unknown post (post.php?post=999)'            => array( 'post.php', '', 999 ),
);
foreach ( $elsewhere as $what => $s ) {
	ok( 'https://pg.test/in/member-20/' === $go( 20, $s[0], $s[1], $s[2] ), "company: sent to its profile from $what" );
}
foreach ( array( 21 => 'member (subscriber)', 22 => 'instructor' ) as $uid => $who ) {
	foreach ( $company_screens + array( "company 23's draft (post.php?post=502)" => array( 'post.php', '', 502 ) ) as $what => $s ) {
		ok( "https://pg.test/in/member-$uid/" === $go( $uid, $s[0], $s[1], $s[2] ), "$who: sent to its profile from $what" );
	}
	ok( "https://pg.test/in/member-$uid/" === $go( $uid, 'index.php' ), "$who: sent to its profile from the dashboard" );
}
ok( '' === $go( 20, 'admin-ajax.php' ) && '' === $go( 20, 'admin-post.php' ) && '' === $go( 21, 'async-upload.php' ), 'admin-ajax.php, admin-post.php and async-upload.php keep working' );
ok( ! user_can( 20, 'edit_post', 502 ) && user_can( 20, 'edit_post', 501 ) && ! user_can( 20, 'edit_others_pg_company_profiles' ), "a company edits its own profile, never another company's (no edit_others_pg_company_profiles)" );
ok( ! array_diff( prospergenics_roles_company_caps(), array( 'edit_pg_company_profiles', 'publish_pg_company_profiles', 'edit_published_pg_company_profiles', 'delete_pg_company_profiles', 'delete_published_pg_company_profiles' ) ) && ! preg_grep( '/_others_|_private_/', prospergenics_roles_company_caps() ), 'the company role holds no *_others_* or *_private_* capability' );
$acc = file_get_contents( $theme . '/inc/account.php' );
ok( false !== strpos( $acc, "\$post_id = isset( \$_GET['post'] ) ? ( is_scalar( \$_GET['post'] ) ? absint( \$_GET['post'] ) : 0 ) : ( isset( \$_POST['post_ID'] ) && is_scalar( \$_POST['post_ID'] ) ? absint( \$_POST['post_ID'] ) : 0 );" ), 'post.php: the post is read as post.php reads it (?post=, else post_ID when the form is saved)' );
ok( false !== strpos( $acc, 'get_current_user_id(), $post_type, $post_id );' ) && false !== strpos( $acc, "add_action( 'admin_page_access_denied', 'prospergenics_2026_keep_members_out_of_admin', 1 );" ), 'the request facts reach the redirect, on admin_init and on refused screens' );
ok( false !== strpos( $acc, "remove_menu_page( 'index.php' );" ) && false !== strpos( $acc, "remove_menu_page( 'profile.php' );" ), 'a company account sees only "Company profiles" in the wp-admin menu' );
ok( false !== strpos( $acc, "return \$show && prospergenics_2026_is_staff();" ), 'the admin bar stays hidden on the front end for company accounts (not staff)' );

echo "account menu: your company profile\n";
ok( array( 'label' => 'Create your company profile', 'url' => 'https://pg.test/wp-admin/post-new.php?post_type=pg_company_profile' ) === prospergenics_roles_company_link_for( array() ), 'no profile yet: "Create your company profile" opens a new one' );
ok( array( 'label' => 'Your company profile', 'url' => 'https://pg.test/wp-admin/post.php?post=501&action=edit' ) === prospergenics_roles_company_link_for( array( 501 ) ), 'one profile: "Your company profile" opens its edit screen' );
ok( array( 'label' => 'Your company profiles', 'url' => 'https://pg.test/wp-admin/edit.php?post_type=pg_company_profile' ) === prospergenics_roles_company_link_for( array( 505, 501 ) ), 'more than one: "Your company profiles" opens the list' );
ok( 'https://pg.test/wp-admin/post.php?post=501&action=edit' === prospergenics_roles_company_profile_link( 20 )['url'], "company 20: its own profile, not company 23's" );
$GLOBALS['posts'][502]['author'] = 99;
ok( 'Create your company profile' === prospergenics_roles_company_profile_link( 23 )['label'], 'a company without a profile of its own: "Create your company profile"' );
$GLOBALS['posts'][502]['author'] = 23;
ok( 'Your company profile' === prospergenics_roles_company_profile_link( 23 )['label'], 'its own draft counts as its profile' );
ok( null === prospergenics_roles_company_profile_link( 21 ) && null === prospergenics_roles_company_profile_link( 22 ) && null === prospergenics_roles_company_profile_link( 4 ) && null === prospergenics_roles_company_profile_link( 0 ), 'no link for members, instructors, staff (they have wp-admin) or visitors' );
$chrome = file_get_contents( $theme . '/inc/site-chrome.php' );
ok( false !== strpos( $chrome, "prospergenics_roles_company_profile_link( get_current_user_id() )" ) && false !== strpos( $chrome, "array( 'key' => 'company', 'label' => \$company['label'], 'url' => \$company['url'] )" ), 'the header account menu (and its phone panel) carries the link' );
$roles_src = file_get_contents( $theme . '/inc/platform-roles.php' );
ok( false !== strpos( $roles_src, "\$query->set( 'author', get_current_user_id() );" ) && false !== strpos( $roles_src, "add_filter( 'views_edit-pg_company_profile'" ), "a company's list shows its own profiles only, without everyone's status counts" );

echo "course creation: instructors only (wizard and REST handler)\n";
foreach ( array( 21 => false, 20 => false, 22 => true, 4 => true ) as $uid => $want ) {
	$GLOBALS['current'] = $uid;
	$who  = array( 21 => 'member', 20 => 'company', 22 => 'instructor', 4 => 'admin' )[ $uid ];
	$rest = prospergenics_courses_rest_instructor();
	ok( $want === prospergenics_courses_is_instructor() && $want === ( true === $rest ) && ( $want || ( $rest instanceof WP_Error && 'rest_forbidden' === $rest->code ) ), sprintf( '%s %s create courses (wizard gate and POST /prospergenics/v1/courses)', $who, $want ? 'may' : 'may not' ) );
}
$GLOBALS['current'] = 0;
ok( prospergenics_courses_rest_instructor() instanceof WP_Error && 'rest_not_logged_in' === prospergenics_courses_rest_instructor()->code, 'visitors: 401' );
$wizard = file_get_contents( $theme . '/courses-wizard.php' );
ok( (bool) preg_match( "#if \(!prospergenics_courses_is_instructor\(\)\) \{\s*\\\$pgc_error = 'The course wizard is for instructors\.[^']*';\s*\\\$pgc_apply = true;\s*status_header\(403\);#", $wizard ) && strpos( $wizard, 'status_header(403)' ) < strpos( $wizard, '<form id="pgc-wizard"' ) && false !== strpos( $wizard, '<?php if ($pgc_error) : ?>' ), '/instructor/courses/new/: 403 and no wizard for everyone who is not an instructor' );
$rest_src = file_get_contents( $theme . '/inc/courses/rest.php' );
ok( (bool) preg_match( "#'methods'\s*=> 'POST',\s*'callback'\s*=> function \( WP_REST_Request \\\$request \) \{\s*\\\$id = prospergenics_courses_rest_save_course\( \\\$request \);.*?'permission_callback' => 'prospergenics_courses_rest_instructor',#s", substr( $rest_src, strpos( $rest_src, "register_rest_route( \$ns, '/courses', array(" ), 1600 ) ), 'POST /prospergenics/v1/courses (the wizard\'s handler) checks prospergenics_courses_rest_instructor' );
ok( false !== strpos( file_get_contents( $theme . '/inc/courses/data-model.php' ), "'capability_type' => array( 'pg_course', 'pg_courses' )" ), "courses use their own capability type: WordPress' own REST and editor need edit_pg_courses too" );
ok( 'https://pg.test/in/member-20/' === $go( 20, 'post-new.php', 'pg_course' ) && 'https://pg.test/in/member-22/' === $go( 22, 'post-new.php', 'pg_course' ), 'and wp-admin → new course is closed to companies (and instructors use the wizard)' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

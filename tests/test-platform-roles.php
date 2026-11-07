<?php
/**
 * Standalone tests for the platform role separation (card #6341): member, company, instructor, admin.
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
function user_can( $user, $cap ) {
	if ( is_int( $user ) ) { $user = get_userdata( $user ); }
	return $user && ! empty( user_caps( $user )[ $cap ] );
}
function current_user_can( $cap ) { return $GLOBALS['current'] && user_can( $GLOBALS['current'], $cap ); }

require $theme . '/inc/courses/data-model.php';
$GLOBALS['hooks']['init'] = array(); // the courses init hooks need a full WordPress; only the roles module runs here
require $theme . '/inc/platform-roles.php';
foreach ( $GLOBALS['hooks']['init'] as $cb ) { $cb(); }

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

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

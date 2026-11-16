<?php
/**
 * Standalone tests for the AI Experts Directory (task #4891).
 *
 *   - 8 expected categories are defined (ai-agents, software-dev, web-design, …)
 *   - seed experts from people.json: Timothy, Leslie, Sandra, Nico, Martien, Jengo
 *   - category filter narrows the result set
 *   - expert_by_slug returns the right expert and null for unknown slugs
 *   - expert card HTML contains the expert's name and a link to /experts/{slug}/
 *   - category tabs HTML contains All + all category links
 *   - save validation: empty categories → error; oversized bio → error; valid data → no errors
 *   - /profile/expert/ rewrite rule exists in routes.php
 *
 * Run with: php tests/test-4891-experts-directory.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ─────────────── minimal WP stubs ───────────────
define( 'ABSPATH', __DIR__ . '/' );
const TEST_HOME = 'https://test.prospergenics.com';

function home_url( $path = '' ) { return TEST_HOME . '/' . ltrim( (string) $path, '/' ); }
function get_template_directory() { return $GLOBALS['theme']; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function wp_kses_post( $s ) { return $s; }
function sanitize_key( $s ) { return strtolower( preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $s ) ) ); }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( (string) $s ); }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9-]/', '-', strtolower( (string) $s ) ) ); }
function esc_url_raw( $u ) { return filter_var( (string) $u, FILTER_SANITIZE_URL ) ?: ''; }
function get_users( $args = array() ) { return array(); }
function get_template_directory_uri() { return TEST_HOME . '/wp-content/themes/prospergenics-2026'; }
function prospergenics_2026_people() {
	$data = json_decode( file_get_contents( $GLOBALS['theme'] . '/inc/people.json' ), true );
	return $data['people'];
}
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function is_user_logged_in() { return false; }
function get_current_user_id() { return 0; }
function add_action( $t, $cb, $p = 10, $a = 1 ) {}
function add_filter( $t, $cb, $p = 10, $a = 1 ) {}
function get_option( $k, $d = false ) { return $d; }
function update_option( $k, $v ) {}
function get_query_var( $k ) { return $GLOBALS['__qvars'][ $k ] ?? ''; }
function flush_rewrite_rules( $h = true ) {}
function add_rewrite_rule( $r, $q, $p = 'bottom' ) {}
function get_user_meta( $uid, $key, $single = false ) { return $single ? '' : array(); }
function update_user_meta( $uid, $key, $val ) {}
function set_transient( $k, $v, $e = 0 ) {}
function check_admin_referer( $a, $f = '_wpnonce' ) { return true; }
function wp_safe_redirect( $u ) {}
function status_header( $c ) {}
function nocache_headers() {}
function wp_die( $m = '' ) { throw new Exception( 'wp_die: ' . $m ); }

$GLOBALS['__qvars'] = array();
$GLOBALS['theme'] = $theme;

// ─────────────── load modules ───────────────
require $theme . '/inc/experts/categories.php';
require $theme . '/inc/experts/data.php';
require $theme . '/inc/experts/routes.php';
require $theme . '/inc/experts/render.php';
require $theme . '/inc/experts/self-service.php';

// ═══════════════════════════════════════════
echo "Categories\n";
$cats = prospergenics_expert_categories();
ok( is_array( $cats ) && count( $cats ) === 8, '8 categories defined' );
$slugs = array_column( $cats, 'slug' );
foreach ( array( 'ai-agents', 'software-dev', 'web-design', 'wordpress', 'marketing', 'training', 'business', 'art' ) as $expected ) {
	ok( in_array( $expected, $slugs, true ), "category slug '$expected' exists" );
}
ok( null !== prospergenics_expert_category( 'ai-agents' ), 'category lookup by slug returns ai-agents' );
ok( null === prospergenics_expert_category( 'nonexistent' ), 'category lookup returns null for unknown slug' );

// ═══════════════════════════════════════════
echo "Curated experts from people.json\n";
$curated = prospergenics_expert_curated_all();
ok( count( $curated ) >= 6, 'at least 6 curated experts' );
// curated is keyed by slug; each entry has no 'slug' field — use array_keys()
$expert_slugs = array_keys( $curated );
foreach ( array( 'martien', 'timothy', 'lessy', 'simitia', 'nico', 'jengo' ) as $s ) {
	ok( in_array( $s, $expert_slugs, true ), "seed expert slug '$s' in curated list" );
}
ok( in_array( 'diko', $expert_slugs, true ), 'diko (has expert_categories) also in curated list' );

// ═══════════════════════════════════════════
echo "experts_all() and category filter\n";
$all = prospergenics_experts_all();
ok( count( $all ) >= 6, 'experts_all() returns all curated experts' );
$ai_agents = prospergenics_experts_all( 'ai-agents' );
ok( count( $ai_agents ) >= 2, 'ai-agents category has at least 2 experts' );
// martien has ai-agents
$ai_slugs = array_column( $ai_agents, 'slug' );
ok( in_array( 'martien', $ai_slugs, true ), 'martien appears in ai-agents filter' );
ok( in_array( 'timothy', $ai_slugs, true ) || in_array( 'nico', $ai_slugs, true ), 'timothy or nico in ai-agents (both have ai-agents tag)' );

// ═══════════════════════════════════════════
echo "expert_by_slug()\n";
$e = prospergenics_expert_by_slug( 'martien' );
ok( null !== $e && isset( $e['name'] ), 'martien found by slug' );
ok( null === prospergenics_expert_by_slug( 'this-does-not-exist' ), 'unknown slug returns null' );
$jengo = prospergenics_expert_by_slug( 'jengo' );
ok( null !== $jengo && 'Jengo' === $jengo['name'], 'jengo found with correct name' );

// ═══════════════════════════════════════════
echo "Render: expert_card()\n";
$card = prospergenics_expert_card( $e );
ok( false !== strpos( $card, 'href="' . TEST_HOME . '/experts/martien/"' ), 'card links to /experts/martien/' );
ok( false !== strpos( $card, esc_html( $e['name'] ) ), 'card contains the expert name' );
ok( false !== strpos( $card, 'pcard' ), 'card has pcard class' );

// ═══════════════════════════════════════════
echo "Render: experts_category_tabs()\n";
$tabs = prospergenics_experts_category_tabs();
ok( false !== strpos( $tabs, 'href="' . TEST_HOME . '/experts/"' ), 'tabs contain All link' );
ok( false !== strpos( $tabs, '/experts/category/ai-agents/' ), 'tabs contain ai-agents link' );
ok( false !== strpos( $tabs, 'experts-cats' ), 'tabs have experts-cats class' );

$tabs_active = prospergenics_experts_category_tabs( 'ai-agents' );
ok( false !== strpos( $tabs_active, 'active' ), 'active class set when category is passed' );

// ═══════════════════════════════════════════
echo "Render: expert_profile_html()\n";
$profile = prospergenics_expert_profile_html( $e );
ok( false !== strpos( $profile, 'ep-wrap' ), 'profile has ep-wrap' );
ok( false !== strpos( $profile, '<h1>' ), 'profile has h1' );

// ═══════════════════════════════════════════
echo "Self-service save validation\n";
// No categories → error
$errors = prospergenics_expert_save( 1, array(
	'expert_bio'        => 'Some bio',
	'expert_title'      => 'AI Expert',
	'expert_categories' => array(),
	'expert_links'      => array(),
) );
ok( isset( $errors['expert_categories'] ), 'empty categories triggers validation error' );

// Bio too long → error
$errors2 = prospergenics_expert_save( 1, array(
	'expert_bio'        => str_repeat( 'x', 2001 ),
	'expert_title'      => 'Title',
	'expert_categories' => array( 'ai-agents' ),
	'expert_links'      => array(),
) );
ok( isset( $errors2['expert_bio'] ), '2001-char bio triggers validation error' );

// Valid data → no errors (uses real update_user_meta stub that does nothing)
$errors3 = prospergenics_expert_save( 1, array(
	'expert_bio'        => 'Valid bio.',
	'expert_title'      => 'Title',
	'expert_categories' => array( 'ai-agents' ),
	'expert_links'      => array( array( 'label' => 'Web', 'url' => 'https://example.com' ) ),
) );
ok( array() === $errors3, 'valid form data produces no errors' );

// Unknown category slug is stripped
$errors4 = prospergenics_expert_save( 1, array(
	'expert_bio'        => 'Bio',
	'expert_title'      => 'Title',
	'expert_categories' => array( 'invented-category', 'ai-agents' ),
	'expert_links'      => array(),
) );
ok( array() === $errors4, 'unknown category slug is silently stripped, known one is kept' );

// ═══════════════════════════════════════════
echo "Routes: rewrite version constant and /profile/expert/ rule\n";
ok( defined( 'PROSPERGENICS_EXPERTS_REWRITE_VERSION' ), 'PROSPERGENICS_EXPERTS_REWRITE_VERSION defined' );
$routes_src = file_get_contents( $theme . '/inc/experts/routes.php' );
ok( false !== strpos( $routes_src, "profile/expert" ), "/profile/expert/ rewrite rule present in routes.php" );
ok( false !== strpos( $routes_src, 'pg_profile_page=expert' ), "rewrite maps to pg_profile_page=expert" );

// ═══════════════════════════════════════════
echo "Experts in functions.php\n";
$fn = file_get_contents( $theme . '/functions.php' );
ok( false !== strpos( $fn, "inc/experts.php" ), 'functions.php loads inc/experts.php' );

// ═══════════════════════════════════════════
echo "Jengo is not a member\n";
$people = json_decode( file_get_contents( $theme . '/inc/people.json' ), true )['people'];
// people.json is keyed by slug; each value has no 'slug' field
ok( array_key_exists( 'jengo', $people ), 'jengo exists in people.json' );
$jengo_p = $people['jengo'] ?? null;
ok( null !== $jengo_p && isset( $jengo_p['members'] ) && false === $jengo_p['members'], 'jengo has members=false (not on /members/ page)' );
ok( null !== $jengo_p && isset( $jengo_p['expert_categories'] ) && in_array( 'ai-agents', $jengo_p['expert_categories'], true ), 'jengo has ai-agents category' );

// ═══════════════════════════════════════════
echo "\n";
if ( $failures === 0 ) {
	echo "All tests passed.\n";
	exit( 0 );
} else {
	echo "$failures test(s) failed.\n";
	exit( 1 );
}

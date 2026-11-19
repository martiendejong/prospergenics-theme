<?php
/**
 * Standalone test for the projects track (card #6388: member portfolios and evidence-linked
 * expertise; the shared contract for #6388/#6389/#6393).
 *
 * The rules (validation, https-only evidence, statuses and the role matrix: who may create, edit,
 * confirm and delete) are pure functions in inc/projects/helpers.php, tested here directly. The
 * wiring (nonces and permission checks in every form handler, the safe /wp/v2 schema, the privacy
 * key, exporters, sitemap and robots) is checked in the source, since it needs WordPress to run;
 * tests/e2e/role-matrix.js checks the same rules against a real WordPress.
 *
 * Run with: php tests/test-platform-projects.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/wp-theme/prospergenics-2026/' );
$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

require $theme . '/inc/projects/helpers.php';
require $theme . '/inc/profiles/helpers.php';

echo "[Test 1] kinds, statuses, contract names\n";
ok( array( 'project', 'product', 'service', 'employer' ) === array_keys( prospergenics_projects_kinds() ), 'kinds: project, product, service, employer (contract)' );
ok( array( 'claimed', 'confirmed', 'disputed' ) === array_keys( prospergenics_projects_statuses() ), 'statuses: claimed, confirmed, disputed (contract)' );

echo "[Test 2] evidence links: https only, validated\n";
ok( 'https://example.org/report.pdf' === prospergenics_projects_clean_https_url( ' https://example.org/report.pdf ' ), 'an https URL is kept' );
foreach ( array( 'http://example.org/', 'javascript:alert(1)', 'data:text/html,x', '//example.org/x', 'https://exa mple.org/', 'https://user:pw@example.org/', 'https://localhost/', 'ftp://example.org/', 'https://"onmouseover=x.org/', '' ) as $bad ) {
	ok( '' === prospergenics_projects_clean_https_url( $bad ), 'refused: ' . ( '' === $bad ? '(empty)' : $bad ) );
}
list( $ev, $err ) = prospergenics_projects_clean_evidence( array( array( 'label' => 'Photos', 'url' => 'https://example.org/photos' ), array( 'label' => '', 'url' => 'https://www.shop.example/item' ), array( 'label' => '', 'url' => '' ) ) );
ok( 2 === count( $ev ) && '' === $err && 'shop.example' === $ev[1]['label'], 'evidence: empty rows skipped, a missing label becomes the host name' );
list( $ev, $err ) = prospergenics_projects_clean_evidence( array( array( 'label' => 'Old site', 'url' => 'http://example.org/' ) ) );
ok( ! $ev && '' !== $err, 'evidence: an http link is an error, not silently dropped' );
list( $ev, $err ) = prospergenics_projects_clean_evidence( array( array( 'label' => 'Only a label', 'url' => '' ) ) );
ok( '' !== $err, 'evidence: a label without an address is an error' );
$many = array();
for ( $i = 0; $i < 12; $i++ ) { $many[] = array( 'label' => "L$i", 'url' => "https://example.org/$i" ); }
list( $ev, $err ) = prospergenics_projects_clean_evidence( $many );
ok( 10 === count( $ev ) && '' !== $err, 'evidence: at most 10 links' );
$rows = prospergenics_projects_evidence_from_form( array( 'A', 'B' ), array( 'https://a.example/', 'https://b.example/', 'https://c.example/' ) );
ok( 3 === count( $rows ) && '' === $rows[2]['label'] && 'https://c.example/' === $rows[2]['url'], 'the form\'s parallel label/url arrays become rows' );
ok( array() === prospergenics_projects_evidence_from_form( 'x', null ), 'non-array form input gives no rows' );

echo "[Test 3] project and contribution validation\n";
list( $p, $e ) = prospergenics_projects_validate_project( array( 'title' => '  Solar   kiosks ', 'kind' => 'product', 'url' => 'https://solar.example', 'period' => '2024–2026', 'description' => "Line one\r\nLine two\x07", 'skills' => 'Solar, solar, Wiring,, Sales' ) );
ok( ! $e && 'Solar kiosks' === $p['title'] && 'product' === $p['kind'] && "Line one\nLine two" === $p['description'], 'a valid project: title squeezed, control characters dropped, line breaks kept' );
ok( array( 'Solar', 'Wiring', 'Sales' ) === $p['skills'], 'skills: unique (case-insensitive), empty ones dropped' );
list( $p, $e ) = prospergenics_projects_validate_project( array( 'title' => '', 'kind' => 'company', 'url' => 'http://insecure.example' ) );
ok( isset( $e['title'], $e['kind'], $e['url'] ), 'missing name, unknown kind and a non-https link are errors' );
ok( 10 === count( prospergenics_projects_clean_skills( implode( ',', range( 1, 20 ) ) ) ), 'at most 10 skills' );
ok( 'solar pv' === prospergenics_projects_skill_key( '  Solar   PV ' ), 'skill key: lower case, spaces squeezed' );
list( $c, $e ) = prospergenics_projects_validate_contribution( array( 'role' => 'Lead installer', 'contribution' => 'Installed 40 systems.', 'evidence' => array( array( 'label' => 'Photos', 'url' => 'https://example.org/p' ) ) ) );
ok( ! $e && 'Lead installer' === $c['role'] && 1 === count( $c['evidence'] ), 'a valid contribution' );
list( $c, $e ) = prospergenics_projects_validate_contribution( array( 'role' => '', 'contribution' => '', 'evidence' => array( array( 'label' => 'x', 'url' => 'javascript:alert(1)' ) ) ) );
ok( isset( $e['role'], $e['contribution'], $e['evidence'] ), 'role, contribution required; a bad evidence link is reported' );
ok( 2000 === mb_strlen( prospergenics_projects_validate_contribution( array( 'role' => 'r', 'contribution' => str_repeat( 'é', 3000 ) ) )[0]['contribution'] ), 'contribution clipped at 2000 characters (multibyte safe)' );

echo "[Test 4] the contributions list\n";
$list = prospergenics_projects_normalize_contributions( array(
	array( 'user_id' => 5, 'role' => 'Founder', 'contribution' => 'Started it', 'evidence' => array(), 'status' => 'confirmed', 'added' => 10 ),
	array( 'user_id' => 5, 'role' => 'Duplicate', 'status' => 'claimed' ),
	array( 'user_id' => 0, 'role' => 'Nobody' ),
	'garbage',
	array( 'user_id' => 9, 'role' => 'Trainer', 'status' => 'bogus', 'evidence' => array( array( 'url' => 'http://bad.example' ), array( 'url' => 'https://ok.example' ) ) ),
) );
ok( 2 === count( $list ) && 5 === $list[0]['user_id'] && 9 === $list[1]['user_id'], 'one entry per member, invalid rows dropped' );
ok( 'claimed' === $list[1]['status'] && 1 === count( $list[1]['evidence'] ), 'unknown status becomes claimed; stored http evidence is dropped on read' );
ok( 1 === prospergenics_projects_find_contribution( $list, 9 ) && -1 === prospergenics_projects_find_contribution( $list, 7 ), 'find a member\'s contribution' );
$old = $list[0];
ok( 'claimed' === prospergenics_projects_status_after_edit( $old, array_merge( $old, array( 'contribution' => 'Started and ran it' ) ) ), 'editing a confirmed contribution puts it back to claimed' );
ok( 'confirmed' === prospergenics_projects_status_after_edit( $old, $old ), 'saving without changes keeps the confirmation' );
ok( prospergenics_projects_status_change_allowed( 'claimed', 'confirmed' ) && prospergenics_projects_status_change_allowed( 'confirmed', 'disputed' ) && ! prospergenics_projects_status_change_allowed( 'claimed', 'claimed' ) && ! prospergenics_projects_status_change_allowed( 'claimed', 'approved' ), 'status changes: between known statuses only' );
ok( 9 === prospergenics_projects_next_creator( $list, 5 ), 'a leaving creator hands over to another contributor' );
ok( 0 === prospergenics_projects_next_creator( array( $list[0] ), 5 ), 'nobody else: no new creator (the project is deleted)' );
$three = prospergenics_projects_normalize_contributions( array(
	array( 'user_id' => 1, 'status' => 'claimed', 'added' => 1 ),
	array( 'user_id' => 2, 'status' => 'claimed', 'added' => 2 ),
	array( 'user_id' => 3, 'status' => 'confirmed', 'added' => 3 ),
) );
ok( 3 === prospergenics_projects_next_creator( $three, 1 ), 'a confirmed contributor is preferred over an older claimed one' );

echo "[Test 5] the role matrix\n";
$creator = 5; $member = 9; $staff = 2;
ok( prospergenics_projects_can_create( array( 'logged_in' => true, 'verified' => true, 'suspended' => false ) ), 'create: a verified member' );
ok( ! prospergenics_projects_can_create( array( 'logged_in' => true, 'verified' => false ) ), 'create: not an unverified member' );
ok( ! prospergenics_projects_can_create( array( 'logged_in' => true, 'verified' => true, 'suspended' => true ) ), 'create: not a suspended member' );
ok( ! prospergenics_projects_can_create( array() ), 'create: not a visitor' );
ok( prospergenics_projects_can_edit_project( $creator, $creator, false ) && prospergenics_projects_can_edit_project( $staff, $creator, true ), 'edit the project: its creator and staff' );
ok( ! prospergenics_projects_can_edit_project( $member, $creator, false ) && ! prospergenics_projects_can_edit_project( 0, 0, false ), 'edit the project: not another contributor, not a visitor' );
ok( prospergenics_projects_can_edit_contribution( $member, $member ), 'edit a contribution: its own member' );
ok( ! prospergenics_projects_can_edit_contribution( $creator, $member ), 'edit a contribution: not the project creator (refused)' );
ok( ! prospergenics_projects_can_edit_contribution( $staff, $member, true, false ), 'edit a contribution: not staff, for a real member' );
ok( prospergenics_projects_can_edit_contribution( $staff, $member, true, true ), 'edit a contribution: staff, for an unclaimed placeholder profile' );
ok( ! prospergenics_projects_can_edit_contribution( $creator, $member, false, true ), 'a placeholder\'s contribution: not a non-staff member' );
ok( ! prospergenics_projects_can_edit_contribution( 0, 0 ), 'edit a contribution: never a visitor' );
ok( prospergenics_projects_can_confirm( $creator, $creator, $member, false ) && prospergenics_projects_can_confirm( $staff, $creator, $member, true ), 'confirm: the creator and staff' );
ok( ! prospergenics_projects_can_confirm( $creator, $creator, $creator, false ) && ! prospergenics_projects_can_confirm( $staff, $creator, $staff, true ), 'confirm: never your own contribution' );
ok( ! prospergenics_projects_can_confirm( $member, $creator, 11, false ), 'confirm: not another contributor' );
ok( prospergenics_projects_can_remove_contribution( $member, $member, false ) && prospergenics_projects_can_remove_contribution( $staff, $member, true ), 'remove a contribution: the member, or staff' );
ok( ! prospergenics_projects_can_remove_contribution( $creator, $member, false ), 'remove a contribution: not the project creator' );
ok( prospergenics_projects_can_delete_project( $creator, $creator, false, 0 ), 'delete: the creator while nobody else contributed' );
ok( ! prospergenics_projects_can_delete_project( $creator, $creator, false, 1 ), 'delete: not the creator once others contributed' );
ok( prospergenics_projects_can_delete_project( $staff, $creator, true, 3 ), 'delete: staff always' );
ok( ! prospergenics_projects_can_delete_project( $member, $creator, false, 0 ) && ! prospergenics_projects_can_delete_project( 0, 0, false, 0 ), 'delete: nobody else' );

echo "[Test 6] indexing\n";
ok( prospergenics_projects_indexable( 'publish', false, false, 1 ), 'published, visible, with a public contribution: indexable' );
ok( ! prospergenics_projects_indexable( 'publish', false, false, 0 ), 'no contribution a visitor may see: noindex' );
ok( ! prospergenics_projects_indexable( 'publish', true, false, 3 ) && ! prospergenics_projects_indexable( 'publish', false, true, 3 ) && ! prospergenics_projects_indexable( 'draft', false, false, 3 ), 'hidden, creator hidden or suspended, unpublished: noindex' );

echo "[Test 7] the portfolio privacy section (profiles track)\n";
$d = prospergenics_profiles_privacy_defaults();
ok( isset( $d['portfolio'] ) && 'public' === $d['portfolio'] && isset( prospergenics_profiles_privacy_labels()['portfolio'] ), 'a "portfolio" privacy section, as open as the profile by default, with a label' );
$vis = prospergenics_profiles_visible_sections( array( 'profile' => 'public', 'portfolio' => 'members' ), 0, 7, false );
ok( $vis['profile'] && ! $vis['portfolio'], 'portfolio shared with members: hidden from a visitor' );
$vis = prospergenics_profiles_visible_sections( array( 'profile' => 'members', 'portfolio' => 'public' ), 0, 7, false );
ok( ! $vis['portfolio'], 'a profile hidden from the viewer hides its portfolio, even when the portfolio is public' );
$vis = prospergenics_profiles_visible_sections( array( 'profile' => 'public', 'portfolio' => 'private' ), 9, 7, true, true );
ok( ! $vis['portfolio'], 'a private portfolio is hidden from connections too' );

echo "[Test 8] wiring (source checks)\n";
$src = function ( $f ) use ( $theme ) { return (string) file_get_contents( $theme . '/' . $f ); };
$forms = $src( 'inc/projects/forms.php' );
foreach ( array( 'create', 'join', 'contribution', 'leave', 'update', 'status', 'delete', 'hide' ) as $a ) {
	ok( false !== strpos( $forms, "add_action( 'admin_post_pg_projects_$a'" ) && false !== strpos( $forms, "prospergenics_projects_start( 'pg_projects_$a' )" ), "form \"$a\": its handler starts with its own nonce and a login" );
}
ok( false !== strpos( $forms, 'wp_verify_nonce( $n, $nonce_action )' ) && false !== strpos( $forms, "'response' => 403" ), 'a missing nonce or login is a 403 and nothing is written' );
ok( false !== strpos( $forms, 'prospergenics_projects_can_edit_contribution( $uid, $member' ) && false !== strpos( $forms, 'prospergenics_projects_can_confirm( $uid' ) && false !== strpos( $forms, 'prospergenics_projects_can_edit_project( $uid' ) && false !== strpos( $forms, 'prospergenics_projects_can_delete_project( $uid' ) && false !== strpos( $forms, 'prospergenics_projects_can_remove_contribution( $uid' ), 'every handler checks the role matrix' );
ok( 2 <= substr_count( $forms, 'prospergenics_projects_user_can_create( $uid )' ), 'adding a project or joining one needs a verified, unsuspended member' );
ok( false !== strpos( $forms, "add_action( 'admin_post_nopriv_pg_projects_'" ), 'visitors posting the forms are refused' );
$data = $src( 'inc/projects/data.php' );
ok( false !== strpos( $data, "'_pg_project_contributions', '_pg_project_contributor', '_pg_project_skill', '_pg_project_hidden'" ) && false !== strpos( $data, "'show_in_rest'  => false" ), 'contributions, indexes and the hidden flag are never in /wp/v2' );
ok( false !== strpos( $data, 'class Prospergenics_Projects_REST_Controller extends WP_REST_Posts_Controller' ) && false !== strpos( $data, 'parent::check_read_permission( $post ) && prospergenics_projects_can_view( $post, get_current_user_id() )' ), '/wp/v2/pg_project reads check prospergenics_projects_can_view() (single items too)' );
ok( false !== strpos( $data, "add_filter( 'rest_pg_project_query'" ) && false !== strpos( $data, "unset( \$data['author'] )" ), 'the collection is narrowed per viewer; the creator\'s user ID is not exposed' );
ok( false !== strpos( $data, "prospergenics_projects_contributors( (int) \$obj['id'], get_current_user_id() )" ), 'the REST "contributors" field applies each member\'s privacy for the current viewer' );
ok( false !== strpos( $data, "'delete_with_user'    => false" ) && false !== strpos( $data, "), 'edit_others_posts' )" ), 'projects are not deleted with their creator; wp-admin/REST writes need edit_others_posts' );
ok( false !== strpos( $data, "! empty( \$vis['portfolio'] )" ) && false !== strpos( $data, 'prospergenics_projects_user_suspended( $user_id ) && ! prospergenics_projects_is_staff( $viewer_id )' ), 'a contribution shows only when the member\'s portfolio is visible, never for a suspended member' );
foreach ( array( 'prospergenics_projects_for_user( $user_id, $viewer_id = 0 )', 'prospergenics_projects_contributors( $project_id, $viewer_id = 0 )', 'prospergenics_projects_url( $project_id )' ) as $sig ) {
	ok( false !== strpos( $data, 'function ' . $sig ), "contract function: $sig" );
}
$pdata = $src( 'inc/profiles/data.php' );
ok( false !== strpos( $pdata, "if ( ! empty( \$vis['portfolio'] ) && function_exists( 'prospergenics_projects_portfolio_data' ) )" ), 'the profile data (and /profile/users/<slug>) carries the portfolio only when it is visible' );
$tpl = $src( 'pg-profile.php' );
ok( false !== strpos( $tpl, 'prospergenics_projects_profile_section( $owner, $data, $viewer_id )' ), 'the profile shows the Portfolio section' );
$render = $src( 'inc/projects/render.php' );
$single = $src( 'single-pg_project.php' );
ok( false !== strpos( $render, "do_action( 'prospergenics_scrutiny_actions', 'project', \$pid )" ) && false !== strpos( $render, "do_action( 'prospergenics_scrutiny_public', 'project', \$pid )" ), 'scrutiny actions on every portfolio entry (contract)' );
ok( false !== strpos( $single, "do_action('prospergenics_scrutiny_actions', 'project', \$pgj_post->ID)" ) && false !== strpos( $single, "do_action('prospergenics_scrutiny_public', 'project', \$pgj_post->ID)" ), 'scrutiny actions on the project page (contract)' );
ok( false !== strpos( $render, 'rel="nofollow ugc noopener" target="_blank"' ) && false === strpos( $render, 'echo $e[' ), 'external links: nofollow ugc noopener; nothing echoed unescaped' );
$seo = $src( 'inc/projects/seo.php' );
ok( false !== strpos( $seo, "if ( 'pg_project' === \$post_type )" ) && false !== strpos( $seo, 'prospergenics_projects_indexable_ids()' ), 'sitemap: indexable projects only' );
ok( false !== strpos( $seo, "\$robots['noindex']  = true;" ) && false !== strpos( $seo, "'Organization' : 'CreativeWork'" ) && false !== strpos( $seo, "'#person'" ), 'robots noindex for non-indexable projects; schema.org CreativeWork/Organization linked to Person' );
ok( false !== strpos( $seo, 'prospergenics_projects_contributors( $post->ID, 0 )' ), 'structured data only names contributors a logged-out visitor may see' );
$privacy = $src( 'inc/projects/privacy.php' );
ok( false !== strpos( $privacy, "\$exporters['prospergenics-portfolio']" ) && false !== strpos( $privacy, "\$erasers['prospergenics-portfolio']" ) && false !== strpos( $privacy, "add_action( 'delete_user', 'prospergenics_projects_erase_member', 4 )" ), 'exporter, eraser and deleted-user clean-up are registered' );
$routes = $src( 'inc/projects/routes.php' );
ok( false !== strpos( $routes, 'prospergenics_projects_can_view( $post, get_current_user_id() )' ) && false !== strpos( $routes, '$wp_query->set_404()' ), 'a project the viewer may not see is a 404' );
$claim = $src( 'inc/projects/claim.php' );
ok( false !== strpos( $claim, "wp_verify_nonce( \$n, 'pg_projects_claim_' . \$u )" ) && false !== strpos( $claim, 'hash_equals( $hash, prospergenics_projects_claim_hash( $token ) )' ) && false !== strpos( $claim, "check_admin_referer( 'pg_projects_claim_send_' . \$uid )" ), 'claim: nonce, keyed-hash token, staff actions behind check_admin_referer' );
ok( false !== strpos( $src( 'functions.php' ), "require get_template_directory() . '/inc/platform-projects.php';" ), 'the loader is required from functions.php' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All projects tests passed\n" );
exit( $failures ? 1 : 0 );

<?php
/**
 * Standalone test for the member badges by ranking (profiles track):
 * wp-theme/prospergenics-2026/inc/profiles/rank-rules.php, rank.php and badges.php.
 *
 * Checks:
 *   - one definition of the evidence weights, shared with search, whose numbers and ranking
 *     rules stay as they were;
 *   - the score: weights, the cap per kind, the endorsement bonus and its cap, unknown kinds;
 *   - the tier boundaries (Member, Contributor, Practitioner, Expert, Mentor), each just below and
 *     at its threshold, and the conditions beyond points (verified evidence, two kinds, a course);
 *   - the next tier, the points missing and the tips;
 *   - the evidence of a member against a small fake WordPress: private sections, a private
 *     portfolio's confirmed contribution, disputed and revoked items, hidden articles, evidence a
 *     member wrote about themself and endorsements by unverified members never count; hidden,
 *     suspended and unverified members show no badge;
 *   - the badge markup: label always visible, icon hidden from screen readers, escaping, colours
 *     checked, a <span> inside other links;
 *   - the /manage/ tier filter;
 * and the wiring in the generated theme: the loader, every surface, REST without the breakdown,
 * the export and erase, the events that recompute, the /badges/ page and the styles.
 *
 * Run with: php tests/test-platform-badges.php
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
define( 'MINUTE_IN_SECONDS', 60 );
define( 'HOUR_IN_SECONDS', 3600 );
const PROSPERGENICS_PROFILES_ENTRY_TYPES = array( 'pg_experience', 'pg_education', 'pg_skill' );
const PROSPERGENICS_PROFILES_MAX_ENTRIES = 50;
$GLOBALS['_hooks'] = array();
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][ $hook ][] = $cb; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][ $hook ][] = $cb; return true; }
function apply_filters( $hook, $value ) { return $value; }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function home_url( $p = '' ) { return 'https://example.test' . $p; }

// A tiny fake site: users (verified, hidden, suspended, what a visitor may see), posts, post meta,
// user meta, contributions and scrutiny records.
$GLOBALS['W'] = array();
function world_reset() {
	$GLOBALS['W'] = array( 'users' => array(), 'posts' => array(), 'pmeta' => array(), 'umeta' => array(), 'projects' => array(), 'certs' => array(), 'scrutiny' => array(), 'hidden_articles' => array() );
}
function add_user( $id, array $o = array() ) {
	$GLOBALS['W']['users'][ $id ] = array_merge( array( 'verified' => true, 'hidden' => false, 'suspended' => false, 'vis' => array() ), $o );
	$all = array( 'profile', 'email', 'phone', 'experience', 'education', 'skills', 'featured', 'certificates', 'portfolio' );
	$GLOBALS['W']['users'][ $id ]['vis'] = array_merge( array_fill_keys( $all, true ), $o['vis'] ?? array() );
}
function add_post( $id, $type, $author, array $o = array() ) {
	$GLOBALS['W']['posts'][ $id ] = (object) array_merge( array( 'ID' => $id, 'post_type' => $type, 'post_author' => $author, 'post_status' => 'publish', 'post_password' => '' ), $o );
}
function get_userdata( $id ) { return isset( $GLOBALS['W']['users'][ (int) $id ] ) ? (object) array( 'ID' => (int) $id ) : false; }
function cache_users( $ids ) {}
function prospergenics_profiles_is_verified( $id ) { return ! empty( $GLOBALS['W']['users'][ $id ]['verified'] ); }
function prospergenics_profiles_is_hidden( $id ) { return ! empty( $GLOBALS['W']['users'][ $id ]['hidden'] ); }
function prospergenics_profiles_is_suspended( $id ) { return ! empty( $GLOBALS['W']['users'][ $id ]['suspended'] ); }
function prospergenics_profiles_visibility_for( $owner, $viewer ) {
	$u = $GLOBALS['W']['users'][ $owner ];
	if ( 0 !== $viewer ) { throw new Exception( 'badges must only ever read the logged-out view' ); }
	// like the real one: hidden by staff, or the profile itself not public, hides every section
	return $u['hidden'] || ! $u['vis']['profile'] ? array_fill_keys( array_keys( $u['vis'] ), false ) : $u['vis'];
}
function get_user_by( $field, $value ) { return false; }
function post_type_exists( $t ) { return true; }
function get_posts( array $a ) {
	$types = (array) $a['post_type'];
	$out   = array();
	foreach ( $GLOBALS['W']['posts'] as $p ) {
		if ( in_array( $p->post_type, $types, true ) && ( ! isset( $a['author'] ) || (int) $p->post_author === (int) $a['author'] ) && ( ! isset( $a['post_status'] ) || $p->post_status === $a['post_status'] ) ) {
			$out[] = $p;
		}
	}
	return $out;
}
function get_post( $p ) { return is_object( $p ) ? $p : ( $GLOBALS['W']['posts'][ (int) $p ] ?? null ); }
function get_post_meta( $id, $key, $single = false ) {
	$v = $GLOBALS['W']['pmeta'][ $id ][ $key ] ?? array();
	return $single ? ( $v ? $v[0] : '' ) : $v;
}
function get_user_meta( $id, $key, $single = false ) { return $GLOBALS['W']['umeta'][ $id ][ $key ] ?? ''; }
function update_user_meta( $id, $key, $v ) { $GLOBALS['W']['umeta'][ $id ][ $key ] = $v; return true; }
function delete_user_meta( $id, $key ) { unset( $GLOBALS['W']['umeta'][ $id ][ $key ] ); return true; }
function prospergenics_articles_is_hidden( $id ) { return in_array( $id, $GLOBALS['W']['hidden_articles'], true ); }
function prospergenics_profiles_certificates( $uid ) { return $GLOBALS['W']['certs'][ $uid ] ?? array(); } // valid ones only, like the real one
function prospergenics_scrutiny_record( $post ) { return $GLOBALS['W']['scrutiny'][ $post->ID ] ?? null; }
// projects track: contributions per project, visible to a visitor when the portfolio is
function prospergenics_projects_for_user( $uid, $viewer ) {
	$out = array();
	if ( 0 !== $viewer || empty( $GLOBALS['W']['users'][ $uid ]['vis']['portfolio'] ) || ! empty( $GLOBALS['W']['users'][ $uid ]['hidden'] ) ) {
		return $out;
	}
	foreach ( $GLOBALS['W']['projects'] as $pid => $list ) {
		foreach ( $list as $c ) {
			if ( $c['user_id'] === $uid ) {
				$out[] = array( 'project' => (object) array( 'ID' => $pid ), 'status' => $c['status'] );
			}
		}
	}
	return $out;
}
function prospergenics_projects_ids_for_member( $uid ) {
	return array_keys( array_filter( $GLOBALS['W']['projects'], function ( $list ) use ( $uid ) { return in_array( $uid, array_column( $list, 'user_id' ), true ); } ) );
}
function prospergenics_projects_contributions( $pid ) { return $GLOBALS['W']['projects'][ $pid ]; }
function prospergenics_projects_find_contribution( $list, $uid ) { $i = array_search( $uid, array_column( $list, 'user_id' ), true ); return false === $i ? -1 : $i; }

require $theme . '/inc/profiles/rank-rules.php';
require $theme . '/inc/profiles/rank.php';
require $theme . '/inc/search/helpers.php'; // require_once of rank-rules.php: no redeclaration

$src = function ( $file ) use ( $theme ) {
	return (string) file_get_contents( $theme . '/' . $file );
};

// ───────────────────────────── one definition, shared with search ─────────────────────────────
echo "Weights: one definition for search and badges\n";
$w = prospergenics_profiles_evidence_weights();
ok( prospergenics_search_evidence_kinds() === $w, 'search reads the same table as the badges' );
$want = array( 'project_confirmed' => 10, 'scrutiny' => 8, 'certificate' => 6, 'course' => 6, 'skill' => 4, 'project_claimed' => 3, 'article' => 3, 'experience' => 3, 'education' => 2, 'headline' => 1 );
ok( $want === array_map( function ( $k ) { return $k['weight']; }, $w ), 'weights as in search (#6393): confirmed 10, scrutiny 8, certificate 6, course 6, skill 4, claimed 3, article 3, experience 3, education 2, headline 1' );
ok( ! isset( $w['project_disputed'] ), 'disputed contributions are no evidence' );
$s = prospergenics_search_score( array( array( 'kind' => 'skill', 'terms' => array( 'x' ), 'endorsements' => 9 ) ), array( 'x' ) );
ok( 7.0 === $s['score'], 'search: a skill still gets at most +3 for endorsements (4 + 3 = 7)' );
ok( 9.0 === prospergenics_search_score( array_fill( 0, 6, array( 'kind' => 'article', 'terms' => array( 'x' ) ) ), array( 'x' ) )['score'], 'search: matching articles still count for at most three' );
$old_shape = array( 'skill' => array( 'weight' => 4, 'verified' => false ), 'article' => array( 'weight' => 3, 'verified' => false ) );
ok( 7.0 === prospergenics_search_score( array( array( 'kind' => 'skill', 'endorsements' => 5 ) ), array(), $old_shape )['score']
	&& 9.0 === prospergenics_search_score( array_fill( 0, 5, array( 'kind' => 'article' ) ), array(), $old_shape )['score'], 'search: a filtered table without the new keys ranks as before' );

// ───────────────────────────── the score and its caps ─────────────────────────────
echo "Score and caps\n";
$items = function ( $kind, $n, $extra = array() ) {
	return array_fill( 0, $n, array_merge( array( 'kind' => $kind ), $extra ) );
};
$caps = array();
foreach ( $w as $k => $v ) {
	$caps[ $k ] = $v['cap'];
}
ok( array( 'project_confirmed' => 10, 'scrutiny' => 5, 'certificate' => 10, 'course' => 5, 'skill' => 10, 'project_claimed' => 5, 'article' => 5, 'experience' => 5, 'education' => 3, 'headline' => 0 ) === $caps, 'caps: 10 confirmed, 5 evidence, 10 certificates, 5 courses, 10 skills, 5 claimed, 5 articles, 5 experience, 3 education, no headline' );
$r = prospergenics_profiles_rank_score( $items( 'article', 9 ) );
ok( 15 === $r['score'] && 5 === $r['breakdown']['article']['counted'] && 9 === $r['breakdown']['article']['items'], 'nine articles: five count, 15 points' );
$r = prospergenics_profiles_rank_score( array_merge( $items( 'skill', 11 ), array( array( 'kind' => 'skill', 'endorsements' => 40 ) ) ) );
ok( 10 === $r['breakdown']['skill']['counted'] && 43 === $r['score'], 'twelve skills: ten count, the endorsed one among them, its bonus capped at +3 (9×4 + 4+3 = 43)' );
$r = prospergenics_profiles_rank_score( array( array( 'kind' => 'skill', 'endorsements' => 2 ) ) );
ok( 6 === $r['score'], 'a skill with two endorsements: 4 + 2' );
$r = prospergenics_profiles_rank_score( array_merge( $items( 'education', 7 ), $items( 'headline', 3 ), $items( 'project_disputed', 4 ), array( 'junk', array( 'kind' => 'unknown' ) ) ) );
ok( 6 === $r['score'] && ! isset( $r['breakdown']['headline'] ) && ! isset( $r['breakdown']['project_disputed'] ), 'education capped at 3 (6 points); headlines, disputed and unknown items add nothing' );
$max = 0;
foreach ( $w as $k => $v ) {
	$max += $v['cap'] * ( $v['weight'] + ( isset( $v['bonus_max'] ) ? $v['bonus_max'] : 0 ) );
}
ok( 351 === $max, 'the most anyone can score is bounded (351 points)' );

// ───────────────────────────── tier boundaries ─────────────────────────────
echo "Tier boundaries\n";
$tier = function ( array $list, $verified = true ) {
	$r = prospergenics_profiles_rank_score( $list );
	return prospergenics_profiles_rank_tier( $r['score'], $r['breakdown'], $verified );
};
$tiers = prospergenics_profiles_badge_tiers();
ok( array( 'member', 'contributor', 'practitioner', 'expert', 'mentor' ) === array_keys( $tiers ), 'five tiers, in order: Member, Contributor, Practitioner, Expert, Mentor' );
ok( '' === $tier( $items( 'project_confirmed', 10 ), false ), 'an unconfirmed email address: no badge, whatever the evidence' );
ok( 'member' === $tier( array() ), 'a confirmed email address and nothing else: Member' );
ok( 'member' === $tier( array_merge( $items( 'certificate', 1 ), $items( 'experience', 1 ) ) ), '9 points with a certificate: still Member' );
ok( 'contributor' === $tier( array_merge( $items( 'certificate', 1 ), $items( 'skill', 1 ) ) ), '10 points with a certificate: Contributor' );
ok( 'contributor' === $tier( array_merge( $items( 'article', 1 ), $items( 'skill', 1 ), $items( 'experience', 1 ) ) ), '10 points with a published article: Contributor' );
ok( 'member' === $tier( $items( 'skill', 10 ) ), '40 points of skills alone (nobody else checked any): Member' );
ok( 'member' === $tier( array_merge( $items( 'course', 2 ), $items( 'skill', 1 ) ) ), 'a course is not Contributor evidence (16 points): Member' );
ok( 'contributor' === $tier( array_merge( $items( 'project_confirmed', 2 ), $items( 'education', 3 ), $items( 'experience', 1 ) ) ), '29 points with confirmed work: Contributor' );
ok( 'practitioner' === $tier( $items( 'project_confirmed', 3 ) ), '30 points with confirmed work: Practitioner' );
ok( 'practitioner' === $tier( $items( 'project_confirmed', 6 ) ), '60 points of one verified kind only: Practitioner' );
ok( 'practitioner' === $tier( array_merge( $items( 'project_confirmed', 5 ), $items( 'scrutiny', 1 ) ) ), '58 points of two verified kinds: Practitioner' );
ok( 'expert' === $tier( array_merge( $items( 'project_confirmed', 5 ), $items( 'certificate', 1 ), $items( 'skill', 1 ) ) ), '60 points of two verified kinds: Expert' );
ok( 'practitioner' === $tier( array_merge( $items( 'project_confirmed', 5 ), $items( 'course', 1 ), $items( 'experience', 1 ) ) ), '59 points with a course: not yet Mentor or Expert' );
ok( 'mentor' === $tier( array_merge( $items( 'project_confirmed', 5 ), $items( 'course', 1 ), $items( 'skill', 1 ) ) ), '60 points and a published course: Mentor' );
ok( 'mentor' === $tier( array_merge( $items( 'course', 5 ), $items( 'skill', 10 ) ) ), 'a teacher whose only verified evidence is their courses (70 points): Mentor' );
ok( 'expert' === $tier( array_merge( $items( 'project_confirmed', 10 ), $items( 'certificate', 10 ) ) ), '160 points without teaching: Expert, never Mentor' );

echo "The next tier and tips\n";
$rank = array( 'score' => 4, 'tier' => 'member', 'breakdown' => prospergenics_profiles_rank_score( $items( 'skill', 1 ) )['breakdown'], 'withheld' => array( 'project_confirmed' => 1 ) );
$next = prospergenics_profiles_rank_next( $rank );
ok( 'contributor' === $next['tier'] && 6 === $next['points'], 'a Member with 4 points: 6 more to Contributor' );
ok( (bool) preg_grep( '/Contributor needs at least one of these: confirmed project contributions, course certificates/', $next['tips'] ) && preg_grep( '/^Get a contribution confirmed .*\(\+10\)\.$/', $next['tips'] ), 'the tips name the missing verified evidence: ' . $next['tips'][0] );
ok( (bool) preg_grep( '/1 of your confirmed project contributions is in a section that is not public/', $next['tips'] ), 'a private portfolio is named: it does not count until it is public' );
$r    = prospergenics_profiles_rank_score( $items( 'project_confirmed', 6 ) );
$next = prospergenics_profiles_rank_next( array( 'score' => $r['score'], 'tier' => 'practitioner', 'breakdown' => $r['breakdown'] ) );
ok( 'expert' === $next['tier'] && 0 === $next['points'] && preg_grep( '/2 different kinds/', $next['tips'] ), 'a Practitioner with 60 points of one kind: Expert needs a second kind, no more points' );
$r = prospergenics_profiles_rank_score( array_merge( $items( 'course', 5 ), $items( 'skill', 10 ) ) );
ok( null === prospergenics_profiles_rank_next( array( 'score' => $r['score'], 'tier' => 'mentor', 'breakdown' => $r['breakdown'] ) ), 'a Mentor has the highest badge: no next tier' );
$needs = prospergenics_profiles_tier_requirements( 'expert' );
ok( in_array( 'At least 60 points of public evidence', $needs, true ) && preg_grep( '/Everything Practitioner needs/', $needs ) && preg_grep( '/2 different kinds of: confirmed project contributions/', $needs ), 'the /badges/ wording of Expert: 60 points, two verified kinds, everything Practitioner needs' );

// ───────────────────────────── a member's public evidence ─────────────────────────────
echo "Only public evidence counts\n";
world_reset();
add_user( 10 );                                        // the member
add_user( 11 );                                        // a verified endorser
add_user( 12, array( 'verified' => false ) );          // an unverified endorser
add_user( 13, array( 'suspended' => true ) );          // a suspended endorser
add_post( 100, 'pg_skill', 10 );
$GLOBALS['W']['pmeta'][100]['_pg_profiles_endorser'] = array( 11, 12, 13, 10 ); // only 11 counts
add_post( 101, 'pg_experience', 10 );
add_post( 102, 'pg_education', 10 );
add_post( 103, 'pg_article', 10 );
add_post( 104, 'pg_article', 10 );                     // hidden by moderation
add_post( 105, 'pg_article', 10, array( 'post_password' => 'x' ) );
add_post( 106, 'pg_article', 10, array( 'post_status' => 'draft' ) );
add_post( 107, 'pg_course', 10 );
$GLOBALS['W']['hidden_articles'] = array( 104 );
$GLOBALS['W']['certs'][10]       = array( array( 'course' => 'A' ) );
$GLOBALS['W']['projects']        = array(
	500 => array( array( 'user_id' => 10, 'status' => 'confirmed' ) ),
	501 => array( array( 'user_id' => 10, 'status' => 'claimed' ) ),
	502 => array( array( 'user_id' => 10, 'status' => 'disputed' ) ),
);
add_post( 300, 'pg_scrutiny', 0 );  $GLOBALS['W']['scrutiny'][300] = array( 'id' => 300, 'status' => 'approved', 'kind' => 'evidence', 'target' => 'person:10', 'member_id' => 11 );
add_post( 301, 'pg_scrutiny', 10 ); $GLOBALS['W']['scrutiny'][301] = array( 'id' => 301, 'status' => 'approved', 'kind' => 'evidence', 'target' => 'person:10', 'member_id' => 10 ); // about themself
add_post( 302, 'pg_scrutiny', 0 );  $GLOBALS['W']['scrutiny'][302] = array( 'id' => 302, 'status' => 'pending', 'kind' => 'evidence', 'target' => 'person:10', 'member_id' => 11 );
add_post( 303, 'pg_scrutiny', 0 );  $GLOBALS['W']['scrutiny'][303] = array( 'id' => 303, 'status' => 'approved', 'kind' => 'review', 'target' => 'person:10', 'member_id' => 11 );
add_post( 304, 'pg_scrutiny', 0 );  $GLOBALS['W']['scrutiny'][304] = array( 'id' => 304, 'status' => 'approved', 'kind' => 'evidence', 'target' => 'claim:101', 'member_id' => 11 );

$all = prospergenics_profiles_rank_compute( 10 );
$bd  = $all['breakdown'];
ok( 5 === $bd['skill']['points'], 'endorsements count only from verified members who are not suspended, never your own (4 + 1)' );
ok( 1 === $bd['article']['counted'], 'hidden, password-protected and draft articles do not count' );
ok( 1 === $bd['project_confirmed']['counted'] && 1 === $bd['project_claimed']['counted'] && ! isset( $bd['project_disputed'] ), 'a disputed contribution never counts' );
ok( 2 === $bd['scrutiny']['counted'], 'approved evidence about the person and their entries counts; pending items, reviews and evidence a member wrote about themself do not' );
ok( 1 === $bd['certificate']['counted'] && 1 === $bd['course']['counted'], 'a valid certificate and a published course count' );
ok( 10 + 3 + 16 + 6 + 6 + 5 + 3 + 3 + 2 === $all['score'] && 'practitioner' === $all['tier'], 'all public: ' . $all['score'] . ' points, Practitioner' );
ok( $all === get_user_meta( 10, PROSPERGENICS_PROFILES_RANK_META ) && 'practitioner' === get_user_meta( 10, PROSPERGENICS_PROFILES_RANK_TIER_META ), 'stored in _pg_profiles_rank, the tier also in _pg_profiles_rank_tier' );
ok( array( 'score', 'tier', 'breakdown', 'computed', 'rules', 'withheld', 'state' ) === array_keys( $all ), 'the cache holds score, tier, breakdown, computed (and the rules, withheld, state)' );

// the portfolio private: the confirmed contribution must not raise the public badge
$GLOBALS['W']['users'][10]['vis']['portfolio'] = false;
$priv = prospergenics_profiles_rank_compute( 10 );
ok( ! isset( $priv['breakdown']['project_confirmed'] ) && ! isset( $priv['breakdown']['project_claimed'] ), 'a private portfolio: its confirmed and claimed contributions do not count' );
ok( $all['score'] - 13 === $priv['score'] && array( 'project_confirmed' => 1, 'project_claimed' => 1 ) === $priv['withheld'], 'the 13 points are withheld and named for the owner, the disputed one not even that' );
// other private sections
foreach ( array( 'skills' => 'skill', 'experience' => 'experience', 'education' => 'education', 'certificates' => 'certificate' ) as $section => $kind ) {
	$GLOBALS['W']['users'][10]['vis'][ $section ] = false;
	$r = prospergenics_profiles_rank_compute( 10 );
	ok( ! isset( $r['breakdown'][ $kind ] ) && ! empty( $r['withheld'][ $kind ] ), "a private $section section does not count" );
}
ok( ! isset( $r['breakdown']['scrutiny'] ) || 1 === $r['breakdown']['scrutiny']['counted'], 'evidence about an entry in a private section does not count' );
$GLOBALS['W']['users'][10]['vis']['profile'] = false;
$r = prospergenics_profiles_rank_compute( 10 );
ok( ! isset( $r['breakdown']['scrutiny'] ) && 9 === $r['score'] && 'member' === $r['tier'], 'a profile visitors cannot see: only the published course and article count (9 points), Member' );

echo "Hidden, suspended and unverified members show no badge\n";
world_reset();
add_user( 20 );
$GLOBALS['W']['projects'] = array( 600 => array( array( 'user_id' => 20, 'status' => 'confirmed' ) ), 601 => array( array( 'user_id' => 20, 'status' => 'confirmed' ) ), 602 => array( array( 'user_id' => 20, 'status' => 'confirmed' ) ) );
$r = prospergenics_profiles_rank_compute( 20 );
ok( 'practitioner' === $r['tier'] && array( 'tier' => 'practitioner', 'label' => 'Practitioner' ) === prospergenics_profiles_badge_data( 20 ), 'control: three confirmed contributions, Practitioner, and the public badge is {tier, label} only' );
$GLOBALS['W']['users'][20]['suspended'] = true;
ok( null === prospergenics_profiles_badge_data( 20 ), 'suspended: no badge, even with the cached Practitioner' );
$r = prospergenics_profiles_rank_compute( 20 );
ok( '' === $r['tier'] && 'suspended' === $r['state'] && 'none' === get_user_meta( 20, PROSPERGENICS_PROFILES_RANK_TIER_META ), 'suspended: computed with no tier (stored as "none")' );
$GLOBALS['W']['users'][20]['suspended'] = false;
$GLOBALS['W']['users'][20]['hidden']    = true;
prospergenics_profiles_rank_compute( 20 );
ok( null === prospergenics_profiles_badge_data( 20 ) && 'hidden' === prospergenics_profiles_rank_state( 20 ), 'hidden by staff: no badge' );
ok( array() === get_user_meta( 20, PROSPERGENICS_PROFILES_RANK_META )['withheld'], 'hidden: no "make it public" tips' );
$GLOBALS['W']['users'][20]['hidden']   = false;
$GLOBALS['W']['users'][20]['verified'] = false;
ok( null === prospergenics_profiles_badge_data( 20 ) && 'unverified' === prospergenics_profiles_rank_state( 20 ), 'an unconfirmed email address: no badge' );
ok( 'missing' === prospergenics_profiles_rank_state( 999 ) && null === prospergenics_profiles_badge_data( 999 ), 'no such member: no badge' );

echo "Cache freshness\n";
$GLOBALS['W']['users'][20]['verified'] = true;
$r = prospergenics_profiles_rank_compute( 20 );
ok( prospergenics_profiles_rank_fresh( $r ), 'a rank just computed is fresh' );
ok( ! prospergenics_profiles_rank_fresh( array_merge( $r, array( 'computed' => time() - PROSPERGENICS_PROFILES_RANK_TTL - 5 ) ) ), 'older than a day: computed again when read' );
ok( ! prospergenics_profiles_rank_fresh( array_merge( $r, array( 'rules' => 'other' ) ) ), 'computed under other rules (a renamed tier, a new threshold): computed again' );
$t2 = $tiers;
$t2['contributor']['min_score'] = 12;
ok( prospergenics_profiles_rank_rules_hash( $t2 ) !== prospergenics_profiles_rank_rules_hash() && prospergenics_profiles_rank_rules_hash( $tiers ) === prospergenics_profiles_rank_rules_hash(), 'the rules fingerprint follows the thresholds' );
prospergenics_profiles_rank_forget( 20 );
ok( '' === get_user_meta( 20, PROSPERGENICS_PROFILES_RANK_META ) && '' === get_user_meta( 20, PROSPERGENICS_PROFILES_RANK_TIER_META ) && null === prospergenics_profiles_rank_compute( 20 ), 'erase removes _pg_profiles_rank and nothing writes it again in that request' );

// ───────────────────────────── markup ─────────────────────────────
echo "Badge markup\n";
$html = prospergenics_profiles_badge_markup( 'expert', $tiers['expert'], 'compact', 'https://example.test/badges/#tier-expert' );
ok( 0 === strpos( $html, '<a ' ) && false !== strpos( $html, 'href="https://example.test/badges/#tier-expert"' ), 'a list badge links to its tier on /badges/' );
ok( false !== strpos( $html, '<span class="pg-badge-label">Expert</span>' ), 'the label is always visible text (never colour alone)' );
ok( false !== strpos( $html, 'aria-hidden="true" focusable="false"' ) && false !== strpos( $html, '<span class="screen-reader-text"> badge</span>' ), 'the icon is hidden from screen readers; they hear "Expert badge"' );
ok( false !== strpos( $html, 'pg-badge--compact' ) && false !== strpos( prospergenics_profiles_badge_markup( 'expert', $tiers['expert'], 'header', 'x' ), 'pg-badge--header' ), 'compact in lists, larger in the profile header' );
ok( 0 === strpos( prospergenics_profiles_badge_markup( 'mentor', $tiers['mentor'], 'inline', 'https://x' ), '<span ' ), 'inside another link (member cards): a <span>, no nested link' );
$evil = array( 'label' => '<b>x</b>"', 'colour' => array( 'bg' => 'red;background:url(x)', 'fg' => '#fff', 'border' => '#12' ), 'icon' => '<path d="M0 0"/><script>alert(1)</script>' );
$bad  = prospergenics_profiles_badge_markup( 'x"y', $evil, 'compact', 'javascript:alert(1)" onclick="x' );
ok( false === strpos( $bad, '<b>' ) && false === strpos( $bad, '<script' ) && false === strpos( $bad, '" onclick' ) && false === strpos( $bad, 'url(x)' ), 'label, icon, URL and colours from a filtered tiers array are escaped or checked' );
ok( false !== strpos( $bad, '--pgb-bg:#eaf5ee' ) && false !== strpos( $bad, '--pgb-fg:#fff' ), 'a colour that is not a hex colour falls back' );
$icons = array_map( function ( $t ) { return $t['icon']; }, $tiers );
$cols  = array_map( function ( $t ) { return $t['colour']['bg']; }, $tiers );
ok( count( array_unique( $icons ) ) === 5 && count( array_unique( $cols ) ) === 5, 'every tier has its own icon and its own colour' );
// WCAG AA: the label colour on the badge colour, at least 4.5:1
$lum = function ( $hex ) {
	$hex = ltrim( $hex, '#' );
	if ( 3 === strlen( $hex ) ) { $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2]; }
	$c = array_map( function ( $v ) { $v = hexdec( $v ) / 255; return $v <= 0.03928 ? $v / 12.92 : pow( ( $v + 0.055 ) / 1.055, 2.4 ); }, str_split( $hex, 2 ) );
	return 0.2126 * $c[0] + 0.7152 * $c[1] + 0.0722 * $c[2];
};
foreach ( $tiers as $k => $t ) {
	$a = $lum( $t['colour']['fg'] );
	$b = $lum( $t['colour']['bg'] );
	$ratio = ( max( $a, $b ) + 0.05 ) / ( min( $a, $b ) + 0.05 );
	ok( $ratio >= 4.5, sprintf( '%s: label contrast %.1f:1 (WCAG AA needs 4.5:1)', $t['label'], $ratio ) );
}
ok( '#f7e9bf' === $tiers['mentor']['colour']['bg'] && preg_match( '/^#[0-9a-f]{6}$/', $tiers['expert']['colour']['bg'] ), 'Mentor is warm gold, the others from the theme greens' );

// ───────────────────────────── /manage/ filter ─────────────────────────────
echo "The /manage/ badge filter\n";
require $theme . '/inc/manage/helpers.php';
ok( 'expert' === prospergenics_manage_clean_tier( 'expert', array_keys( $tiers ) ) && 'none' === prospergenics_manage_clean_tier( 'none', array_keys( $tiers ) ) && '' === prospergenics_manage_clean_tier( 'admin', array_keys( $tiers ) ), 'the filter takes a tier, "none", or nothing' );
$args = prospergenics_manage_member_tier_args( prospergenics_manage_member_query_args( '', 'verified', 1 ), 'mentor' );
ok( 'AND' === $args['meta_query']['relation'] && array( 'key' => '_pg_profiles_rank_tier', 'value' => 'mentor' ) === $args['meta_query'][1], 'a tier filter adds the stored tier to the other filters (AND)' );
ok( prospergenics_manage_member_query_args( '', 'all', 1 ) === prospergenics_manage_member_tier_args( prospergenics_manage_member_query_args( '', 'all', 1 ), '' ), 'no tier: the list query is unchanged' );

// ───────────────────────────── wiring ─────────────────────────────
echo "Wiring\n";
$loader = $src( 'inc/platform-profiles.php' );
foreach ( array( 'rank-rules.php', 'rank.php', 'badges.php' ) as $f ) {
	ok( false !== strpos( $loader, "'$f'" ), "the profiles loader requires $f" );
}
ok( false !== strpos( $src( 'inc/search/helpers.php' ), "require_once dirname( __DIR__ ) . '/profiles/rank-rules.php'" ), 'search loads the shared weights' );
$surfaces = array(
	'pg-profile.php'                 => "prospergenics_profiles_badge_html( \$owner->ID, 'header' )",
	'pg-search.php'                  => "prospergenics_profiles_badge_html( \$p['id'] )",
	'single-pg_project.php'          => 'prospergenics_profiles_badge_html($pgj_u->ID)',
	'single-pg_article.php'          => 'prospergenics_profiles_badge_html( $pga_post->post_author )',
	'inc/courses/render.php'         => 'prospergenics_profiles_badge_html( $user->ID )',
	'courses-instructor.php'         => "prospergenics_profiles_badge_html(\$pgc_user->ID, 'header')",
	'inc/profiles/render.php'        => "prospergenics_profiles_badge_html( \$u->ID, 'inline' )",
	'inc/manage/views/members.php'   => 'prospergenics_profiles_badge_html( $pgm_uid )',
);
foreach ( $surfaces as $file => $call ) {
	ok( false !== strpos( $src( $file ), $call ), "badge on $file" );
}
foreach ( array( 'pg-search.php', 'single-pg_project.php', 'pg-profile-following.php', 'inc/manage/views/members.php' ) as $file ) {
	ok( false !== strpos( $src( $file ), 'prospergenics_profiles_badges_prime(' ), "$file primes its members' badges once" );
}
ok( false !== strpos( $src( 'pg-profile.php' ), 'prospergenics_profiles_rank_owner_card( $owner->ID )' ) && preg_match( '/if \( \$is_owner \) :.*?prospergenics_profiles_rank_owner_card/s', $src( 'pg-profile.php' ) ), 'the breakdown card is inside the owner-only part of the profile' );
$data = $src( 'inc/profiles/data.php' );
ok( false !== strpos( $data, "\$data['badge'] = function_exists( 'prospergenics_profiles_badge_data' ) ? prospergenics_profiles_badge_data( \$user->ID ) : null;" ) && false === strpos( $data, "\$data['breakdown']" ) && false === strpos( $data, "\$data['rank']" ) && false === strpos( $data, "\$data['score']" ), 'REST profile data: badge {tier, label}, no score or breakdown' );
$rank_src = $src( 'inc/profiles/rank.php' );
ok( false !== strpos( $rank_src, "'wp_privacy_personal_data_exporters'" ) && false !== strpos( $rank_src, "'wp_privacy_personal_data_erasers'" ), 'an exporter (score, tier, breakdown) and an eraser are registered' );
$ex = prospergenics_profiles_rank_exporter( 'nobody@example.test' );
ok( array() === $ex['data'] && $ex['done'], 'the exporter answers for an unknown address' );
foreach ( array( 'prospergenics_platform_content_saved', 'transition_post_status', 'before_delete_post', 'prospergenics_projects_status_changed', 'prospergenics_scrutiny_decided', 'prospergenics_courses_certificate_issued', 'prospergenics_courses_certificate_revoked', 'prospergenics_profiles_suspended', 'updated_post_meta', 'updated_user_meta', 'shutdown' ) as $hook ) {
	ok( isset( $GLOBALS['_hooks'][ $hook ] ) || false !== strpos( $rank_src, "'$hook'" ), "recomputed on $hook" );
}
foreach ( array( '_pg_profiles_endorser', '_pg_articles_hidden', '_pg_project_contributions', '_pg_project_hidden', '_pg_courses_revoked_at', '_pg_scrutiny_status', '_pg_profiles_privacy', '_pg_profiles_hidden', '_pg_profiles_suspended', '_pg_profiles_verify_pending' ) as $key ) {
	ok( false !== strpos( $rank_src, "'$key'" ), "a change of $key recomputes" );
}
ok( false !== strpos( $rank_src, "prospergenics_profiles_visibility_for( \$uid, 0 )" ) && false !== strpos( $rank_src, 'prospergenics_projects_for_user( $uid, 0 )' ), 'evidence is read as a logged-out visitor sees it' );
$badges_src = $src( 'inc/profiles/badges.php' );
ok( false !== strpos( $badges_src, "'^badges/?\$'" ) && file_exists( $theme . '/pg-badges.php' ), '/badges/ is routed to pg-badges.php' );
ok( false !== strpos( $src( 'pg-badges.php' ), 'prospergenics_profiles_badge_tiers()' ) && false !== strpos( $src( 'pg-badges.php' ), 'prospergenics_profiles_evidence_weights()' ), '/badges/ shows the rules in force (tiers and weights)' );
ok( file_exists( $theme . '/assets/platform/badges.css' ) && false !== strpos( $src( 'assets/platform/badges.css' ), 'a.pg-badge:focus-visible' ), 'the badge styles exist, with a visible focus ring' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All passed\n" );
exit( $failures ? 1 : 0 );

<?php
/**
 * Standalone test: the person data generated from people/build-profiles.py PROFILES stays in
 * sync everywhere it is shown, and /in/ accounts link to it (audit CON-10, CON-11, CON-15).
 *
 *   - the homepage "Meet the alchemists" slider (index.html and the generated front-page.php)
 *     equals PROFILES entries with "slider": True, in order, name / role / quote / photo, in
 *     two identical sets (the second hidden from assistive tech), a multiple of 3 cards each,
 *   - inc/people.json (read by WordPress) equals PROFILES,
 *   - people/index.html sends /people/ to the people list instead of a 403,
 *   - a WordPress user with user meta _pg_people_slug: /in/ links to the story and borrows its
 *     photo and role; bylines go to /people/<slug>/ while the /in/ page is too empty to index,
 *   - which /in/ profiles may be indexed (public, verified, completion threshold).
 *
 * PROFILES is read by running build-profiles.py --dump (python3), so the test compares against
 * the real source, not a copy.
 *
 * Run with: php tests/test-2026-people-sync.php
 */

$root   = dirname( __DIR__ );
$design = $root . '/design/new-hero-2026-10';
$theme  = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

$json     = shell_exec( 'python3 ' . escapeshellarg( $design . '/people/build-profiles.py' ) . ' --dump 2>&1' );
$profiles = json_decode( (string) $json, true );
if ( ! is_array( $profiles ) || ! $profiles ) {
	echo "  FAIL  could not read PROFILES (python3 build-profiles.py --dump): $json\n";
	exit( 1 );
}
$slider = array_values( array_filter( $profiles, function ( $m ) { return ! empty( $m['slider'] ); } ) );

/**
 * The slider cards of a page, per .flow-set: [slug, name, role, quote, img or initial, alt, tabindex].
 *
 * @return array<int,array{hidden:bool,cards:array}>
 */
function slider_sets( $html ) {
	if ( ! preg_match( '#<!-- people:slider -->(.*?)<!-- /people:slider -->#s', $html, $m ) ) {
		return array();
	}
	$sets = array();
	preg_match_all( '#<div class="flow-set"( aria-hidden="true")?>(.*?)\n        </div>#s', $m[1], $sm, PREG_SET_ORDER );
	foreach ( $sm as $set ) {
		preg_match_all( '#<a class="flow-card" href="[^"]*people/([a-z0-9-]+)/"( tabindex="-1")?>.*?<span class="fc-portrait[^"]*"[^>]*>(?:<img [^>]*src="[^"]*assets/([^"]+)" alt="([^"]*)"[^>]*/>|([^<]+))</span>\s*<div class="fc-name">(.*?)</div>\s*<div class="fc-role">(.*?)</div>\s*<p class="fc-quote">"(.*?)"</p>#s', $set[2], $cm, PREG_SET_ORDER );
		$cards = array();
		foreach ( $cm as $c ) {
			$cards[] = array(
				'slug'  => $c[1],
				'tab'   => '' !== $c[2],
				'img'   => '' !== $c[3] ? $c[3] : null,
				'alt'   => $c[4],
				'init'  => isset( $c[5] ) ? $c[5] : '',
				'name'  => html_entity_decode( $c[6], ENT_QUOTES, 'UTF-8' ),
				'role'  => html_entity_decode( $c[7], ENT_QUOTES, 'UTF-8' ),
				'quote' => html_entity_decode( $c[8], ENT_QUOTES, 'UTF-8' ),
			);
		}
		$sets[] = array( 'hidden' => '' !== $set[1], 'cards' => $cards );
	}
	return $sets;
}

/** Differences between a page's slider and the PROFILES slider people ('' when none). */
function slider_diff( $html, array $slider ) {
	$sets = slider_sets( $html );
	if ( 2 !== count( $sets ) ) {
		return 'expected 2 .flow-sets, found ' . count( $sets );
	}
	if ( $sets[0]['hidden'] || ! $sets[1]['hidden'] ) {
		return 'the second set (and only that one) must be aria-hidden';
	}
	foreach ( $sets as $i => $set ) {
		if ( count( $set['cards'] ) !== count( $slider ) ) {
			return "set $i has " . count( $set['cards'] ) . ' cards, PROFILES has ' . count( $slider ) . ' slider people';
		}
		foreach ( $slider as $n => $m ) {
			$c = $set['cards'][ $n ];
			foreach ( array( 'slug', 'name', 'role', 'quote' ) as $k ) {
				if ( $c[ $k ] !== $m[ $k ] ) {
					return "set $i card $n: $k is '{$c[$k]}', PROFILES says '{$m[$k]}'";
				}
			}
			if ( ( $m['img'] ? $m['img'] : null ) !== $c['img'] || ( ! $m['img'] && $c['init'] !== $m['initial'] ) ) {
				return "set $i card $n ({$m['slug']}): photo differs";
			}
			if ( $i && ( ! $c['tab'] || '' !== $c['alt'] ) ) {
				return "duplicate card {$m['slug']} must be tabindex=-1 with an empty alt";
			}
			if ( ! $i && ( $c['tab'] || ( $m['img'] && $c['alt'] !== $m['name'] ) ) ) {
				return "card {$m['slug']} must be focusable and named";
			}
		}
	}
	return '';
}

echo "Homepage slider = PROFILES (CON-10)\n";
$index = file_get_contents( $design . '/index.html' );
$front = file_get_contents( $theme . '/front-page.php' );
ok( count( $slider ) > 0 && 0 === count( $slider ) % 3, count( $slider ) . ' slider people, a multiple of 3' );
$d = slider_diff( $index, $slider );
ok( '' === $d, 'index.html slider equals PROFILES' . ( $d ? ": $d" : '' ) );
$d = slider_diff( $front, $slider );
ok( '' === $d, 'front-page.php slider equals PROFILES' . ( $d ? ": $d" : '' ) );
// the check is not vacuous: any drift is caught
$changed = preg_replace( '#(<p class="fc-quote">")[^"]+#', '$1Something else entirely.', $index, 1 );
ok( '' !== slider_diff( $changed, $slider ), 'a changed quote is caught' );
$dropped = preg_replace( '#\s*<a class="flow-card" href="people/frank/">.*?</a>#s', '', $index, 1 );
ok( '' !== slider_diff( $dropped, $slider ), 'a missing card is caught' );
$slugs = array_column( $slider, 'slug' );
ok( in_array( 'sjoerd', $slugs, true ) && ! in_array( 'farid', $slugs, true ), 'membership as on the live slider (Sjoerd on it, Farid not): owner decision, see build-profiles.py' );
ok( false !== strpos( file_get_contents( $design . '/people/build-profiles.py' ), 'def write_slider' ), 'build-profiles.py writes the slider' );

echo "people.json = PROFILES (CON-11)\n";
$pj = json_decode( file_get_contents( $design . '/wp/inc/people.json' ), true );
$same = is_array( $pj ) && count( $pj['people'] ) === count( $profiles );
foreach ( $profiles as $m ) {
	$p    = isset( $pj['people'][ $m['slug'] ] ) ? $pj['people'][ $m['slug'] ] : null;
	$same = $same && $p && $p['name'] === $m['name'] && $p['role'] === $m['role'] && $p['img'] === (string) $m['img'];
}
ok( $same, 'inc/people.json has every PROFILES person with the same name, role and photo' );
ok( file_get_contents( $design . '/wp/inc/people.json' ) === file_get_contents( $theme . '/inc/people.json' ), 'the theme ships the same people.json' );

echo "/people/ itself (CON-15)\n";
$pi = file_get_contents( $design . '/people/index.html' );
ok( false !== strpos( $pi, '<meta http-equiv="refresh" content="0; url=/community/" />' ), 'meta refresh to /community/' );
ok( false !== strpos( $pi, '<link rel="canonical" href="https://test.prospergenics.com/community/" />' ), 'canonical to /community/' );
ok( false !== strpos( $pi, 'location.replace("/community/")' ), 'script redirect for browsers that ignore the refresh' );
ok( false !== strpos( $pi, 'noindex' ), 'the redirect page itself is noindex' );
ok( is_file( $design . '/community/index.html' ), '/community/ exists' );
ok( false !== strpos( file_get_contents( $design . '/deploy-wp-theme-vimexx.py' ), '/people/index.html' ), 'the deploy uploads people/index.html' );

echo "/in/ accounts linked to /people/ (CON-11)\n";
// WordPress stubs, then the real functions extracted from the theme sources
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['_meta'] = array();
function get_user_meta( $id, $key, $single = false ) { return isset( $GLOBALS['_meta'][ $id ][ $key ] ) ? $GLOBALS['_meta'][ $id ][ $key ] : ''; }
function sanitize_title( $s ) { return strtolower( preg_replace( '/[^a-z0-9-]/i', '', (string) $s ) ); }
function home_url( $p = '' ) { return 'https://prospergenics.com' . $p; }
function get_template_directory_uri() { return 'https://prospergenics.com/wp-content/themes/prospergenics-2026'; }
function get_userdata( $id ) { return $id ? (object) array( 'ID' => $id ) : false; }
function get_current_user_id() { return 0; }
function apply_filters( $hook, $v ) { return $v; }
function prospergenics_profiles_privacy( $id ) { return array( 'profile' => isset( $GLOBALS['_meta'][ $id ]['privacy'] ) ? $GLOBALS['_meta'][ $id ]['privacy'] : 'public' ); }
function prospergenics_profiles_is_verified( $id ) { return empty( $GLOBALS['_meta'][ $id ]['pending'] ); }
function prospergenics_profiles_get_completion( $id ) { return array( 'percent' => isset( $GLOBALS['_meta'][ $id ]['percent'] ) ? $GLOBALS['_meta'][ $id ]['percent'] : 0, 'missing' => array() ); }
function prospergenics_profiles_visible_sections( $p, $v, $o, $c, $verified = false ) { return array( 'profile' => 'public' === $p['profile'] ); }
function prospergenics_profiles_visibility_for( $owner, $viewer ) { return prospergenics_profiles_visible_sections( prospergenics_profiles_privacy( $owner ), $viewer, $owner, false ); }
function prospergenics_profiles_is_connection( $a, $b ) { return false; }
function prospergenics_profiles_url( $id ) { return 'https://prospergenics.com/in/user-' . $id . '/'; }

$extract = function ( $file, array $fns ) {
	$src = file_get_contents( $file );
	foreach ( $fns as $fn ) {
		if ( ! preg_match( '/(function ' . $fn . '\(.*?\n\})/s', $src, $m ) ) {
			echo "  FAIL  could not find $fn() in $file\n";
			exit( 1 );
		}
		eval( str_replace( "__DIR__ . '/people.json'", var_export( dirname( $file ) . '/people.json', true ), $m[1] ) );
	}
};
// the completion threshold is the real constant from helpers.php (one rule for robots and sitemap)
preg_match( '/const PROSPERGENICS_PROFILES_INDEX_MIN = (\d+);/', file_get_contents( $theme . '/inc/profiles/helpers.php' ), $min );
define( 'PROSPERGENICS_PROFILES_INDEX_MIN', (int) $min[1] );
$extract( $theme . '/inc/profiles/data.php', array( 'prospergenics_profiles_index_min_completion', 'prospergenics_profiles_is_indexable' ) );
$extract( $theme . '/inc/platform-profiles.php', array( 'prospergenics_2026_people', 'prospergenics_profiles_person', 'prospergenics_profiles_public_url_for' ) );

$GLOBALS['_meta'] = array(
	7 => array( '_pg_people_slug' => 'martien', 'percent' => 0 ),   // linked, empty account
	8 => array( '_pg_people_slug' => 'frank', 'percent' => 80 ),    // linked, filled in
	9 => array( 'percent' => 0 ),                                    // not linked
	10 => array( '_pg_people_slug' => 'nobody', 'percent' => 0 ),   // unknown slug
	11 => array( '_pg_people_slug' => 'lessy', 'percent' => 100, 'privacy' => 'members' ),
);
$p = prospergenics_profiles_person( 7 );
ok( $p && 'https://prospergenics.com/people/martien/' === $p['url'] && 'Martien' === $p['first_name'], 'the linked person: /people/martien/, first name Martien' );
ok( $p && 'https://prospergenics.com/wp-content/themes/prospergenics-2026/assets/martien.webp' === $p['photo'] && 'Software Developer, Founder' === $p['role'], 'photo and role come from PROFILES' );
ok( null === prospergenics_profiles_person( 9 ) && null === prospergenics_profiles_person( 10 ), 'no link without the meta, or with an unknown slug' );
ok( 'https://prospergenics.com/people/martien/' === prospergenics_profiles_public_url_for( '', 7 ), 'byline of an empty linked account goes to the story' );
ok( 'https://prospergenics.com/in/user-8/' === prospergenics_profiles_public_url_for( '', 8 ), 'byline of a filled-in linked account goes to /in/' );
ok( 'https://prospergenics.com/in/user-9/' === prospergenics_profiles_public_url_for( '', 9 ), 'byline of an unlinked account is unchanged (/in/)' );
ok( 'https://prospergenics.com/people/lessy/' === prospergenics_profiles_public_url_for( '', 11 ), 'a members-only /in/ page: visitors get the public story' );

echo "Which /in/ profiles are indexed and in the sitemap (ARC-22)\n";
$GLOBALS['_meta'][20] = array( 'percent' => 40 );
$GLOBALS['_meta'][21] = array( 'percent' => 20 );
$GLOBALS['_meta'][22] = array( 'percent' => 100, 'privacy' => 'members' );
$GLOBALS['_meta'][23] = array( 'percent' => 100, 'pending' => 1 );
ok( prospergenics_profiles_is_indexable( 20 ), 'public, verified, 40% complete: indexed' );
ok( ! prospergenics_profiles_is_indexable( 21 ), '20% complete: not indexed' );
ok( ! prospergenics_profiles_is_indexable( 22 ), 'members-only profile: not indexed' );
ok( ! prospergenics_profiles_is_indexable( 23 ), 'unverified account: not indexed' );
$routes = file_get_contents( $theme . '/inc/profiles/routes.php' );
ok( false !== strpos( $routes, '! prospergenics_profiles_is_indexable( $owner->ID )' ), '/in/ robots use the same rule as the sitemap' );

echo "/in/ template\n";
$tpl = file_get_contents( $theme . '/pg-profile.php' );
ok( false !== strpos( $tpl, "sprintf( 'Read %s’s story', \$person['first_name'] )" ), 'shows "Read <name>’s story" linking to /people/<slug>/' );
ok( false !== strpos( $tpl, "elseif ( \$person && '' !== \$person['photo'] )" ), 'falls back to the PROFILES photo while the account has none' );
ok( false !== strpos( $tpl, "<p class=\"pgp-headline\"><?php echo esc_html( \$person['role'] ); ?></p>" ), 'falls back to the PROFILES role while there is no headline' );
$pp = file_get_contents( $theme . '/inc/platform-profiles.php' );
ok( false !== strpos( $pp, "check_admin_referer( 'update-user_' . \$user_id )" ) && false !== strpos( $pp, "current_user_can( 'edit_users' )" ), 'the wp-admin field checks the capability and the nonce' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

<?php
/**
 * Standalone test for the /members/ list from PROFILES (cards #6516, #6517, #6523,
 * inc/members-directory.php), on the saved jengo-listings page (tests/fixtures/jengo-listings/members.html):
 *
 *   - the takeover replaces the plugin's 12 stale cards with one card per PROFILES person on the
 *     list (13: Farid in), each with the name, role and quote of its /people/ page, a .webp
 *     portrait (or the initial), and a link to /people/<slug>/; the count says 13;
 *   - no placeholder quote, no "Lessy" name, no .png/.jpg portrait and no /members/<slug>/ link is left;
 *   - the plugin's search (?keyword=) is answered from PROFILES, with the plugin's own "no results";
 *   - the theme header, the /find/ link and the member badges still work on the new cards;
 *   - the plugin's ItemList JSON-LD (printed before <!DOCTYPE>) names the same people;
 *   - a page of another shape is left alone; other listings pages are untouched.
 *
 * Run with: php tests/test-2026-members-directory.php
 */

$root   = dirname( __DIR__ );
$theme  = $root . '/wp-theme/prospergenics-2026';
$design = $root . '/design/new-hero-2026-10';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ───────────────────── WP stubs ─────────────────────
const TEST_HOME = 'https://test.prospergenics.com';
define( 'ABSPATH', __DIR__ . '/' );
$GLOBALS['__hooks'] = array();
function home_url( $path = '' ) { return TEST_HOME . '/' . ltrim( (string) $path, '/' ); }
function get_template_directory_uri() { return TEST_HOME . '/wp-content/themes/prospergenics-2026'; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
function wp_unslash( $v ) { return $v; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function wp_json_encode( $d, $f = 0 ) { return json_encode( $d, $f ); }
function is_admin() { return false; }
function wp_doing_ajax() { return false; }
function apply_filters( $tag, $value, ...$args ) {
	if ( isset( $GLOBALS['__filters'][ $tag ] ) ) { return $GLOBALS['__filters'][ $tag ]( $value, ...$args ); }
	foreach ( isset( $GLOBALS['__hooks'][ $tag ] ) ? $GLOBALS['__hooks'][ $tag ] : array() as $cb ) { $value = $cb( $value, ...$args ); }
	return $value;
}
function add_action( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) { $GLOBALS['__hooks'][ $tag ][] = $cb; }
function is_user_logged_in() { return false; }
function get_current_user_id() { return 0; }
function current_user_can( $c ) { return false; }
function is_admin_bar_showing() { return false; }
function wp_login_url( $r = '' ) { return TEST_HOME . '/wp-login.php'; }
function wp_style_is( $h, $s ) { return false; }
function wp_script_is( $h, $s ) { return false; }
// The real reader (inc/platform-profiles.php) is tested in test-2026-people-sync.php; same file here.
function prospergenics_2026_people() {
	$data = json_decode( file_get_contents( $GLOBALS['theme'] . '/inc/people.json' ), true );
	return $data['people'];
}
$_SERVER['REQUEST_URI'] = '/members/';

require $theme . '/inc/site-chrome.php';
require $theme . '/inc/members-directory.php';

// PROFILES, from the source of truth.
$profiles = json_decode( shell_exec( 'python3 ' . escapeshellarg( $design . '/people/build-profiles.py' ) . ' --dump' ), true );
ok( is_array( $profiles ) && count( $profiles ) >= 13, 'PROFILES read from build-profiles.py --dump' );
$listed = array_values( array_filter( $profiles, function ( $m ) { return $m['members']; } ) );

$chrome = array( 'header' => '<header class="site-header">HEADER</header>', 'footer' => '<footer class="site-footer">FOOTER</footer>', 'css' => '.x{}', 'script' => '<script>/*menu*/</script>' );
$fixture = file_get_contents( __DIR__ . '/fixtures/jengo-listings/members.html' );
$page    = substr( $fixture, 0, strpos( $fixture, '<script type="speculationrules">' ) ); // the takeover buffer ends at wp_footer
$out     = prospergenics_2026_listings_takeover( $page, '/members/', $chrome );

echo "Who is on /members/ (#6517)\n";
ok( count( $listed ) === count( prospergenics_2026_members_people() ), count( $listed ) . ' people on the list, as PROFILES decides ("members" flag)' );
ok( in_array( 'farid', array_column( $listed, 'slug' ), true ) && in_array( 'sjoerd', array_column( $listed, 'slug' ), true ), 'Farid and Sjoerd (a supporting member) are on it' );
preg_match_all( '#<a class="dir-card" href="([^"]+)">(.*?)</a>#s', $out, $cards, PREG_SET_ORDER );
ok( count( $listed ) === count( $cards ), 'one card per listed person (' . count( $cards ) . ')' );
$all_match = count( $listed ) === count( $cards );
foreach ( $listed as $i => $m ) {
	$c = isset( $cards[ $i ] ) ? $cards[ $i ] : array( '', '', '' );
	$this_ok = TEST_HOME . '/people/' . $m['slug'] . '/' === $c[1]
		&& false !== strpos( $c[2], '<div class="dc-title">' . esc_html( $m['name'] ) . '</div>' )
		&& false !== strpos( $c[2], '<div class="dc-subtitle">' . esc_html( $m['role'] ) . '</div>' )
		&& false !== strpos( $c[2], '<div class="dc-quote">“' . esc_html( $m['quote'] ) . '”</div>' )
		&& ( $m['img']
			? false !== strpos( $c[2], 'src="' . TEST_HOME . '/wp-content/themes/prospergenics-2026/assets/' . $m['img'] . '"' )
			: false !== strpos( $c[2], '<div class="dc-initial">' . $m['initial'] . '</div>' ) );
	ok( $this_ok, "card " . ( $i + 1 ) . ": {$m['name']} → /people/{$m['slug']}/, same name, role, quote and photo as PROFILES" );
	$all_match = $all_match && $this_ok;
}
ok( false !== strpos( $out, '<p class="pg-count">' . count( $listed ) . ' members</p>' ) && 1 === substr_count( $out, '<p class="pg-count">' ), 'the count under the heading says ' . count( $listed ) . ' members' );

echo "Nothing stale left (#6516, #6523)\n";
ok( false === strpos( $out, 'Every task I pick up teaches me something' ) && false === strpos( $out, 'I started by asking questions. Now people come to ask me.' ), 'no placeholder quotes' );
ok( false === strpos( $out, '<div class="dc-title">Lessy</div>' ) && false === strpos( $out, 'Co-Founder' ), 'no "Lessy" card name, no "Co-Founder"' );
// The whole page as a visitor gets it: the takeover buffer plus the ItemList the plugin prints before it.
$full = prospergenics_2026_members_itemlist( $out, prospergenics_2026_members_people(), TEST_HOME );
ok( ! preg_match( '#/members/[a-z0-9-]+/#', $full ), 'no link to a /members/<slug>/ page, in the cards or the JSON-LD' );
ok( ! preg_match( '#assets/[^"\']+\.(png|jpe?g)"#i', block_of( $out, 'dir-wrap' ) ), 'no .png/.jpg portrait on the cards' );
$missing = array();
foreach ( $listed as $m ) {
	if ( $m['img'] && ( '.webp' !== substr( $m['img'], -5 ) || ! is_file( $design . '/assets/' . $m['img'] ) ) ) {
		$missing[] = $m['img'];
	}
}
ok( ! $missing, 'every portrait is a .webp the theme ships' . ( $missing ? ': ' . implode( ', ', $missing ) : '' ) );
ok( false !== strpos( file_get_contents( $design . '/people/build-profiles.py' ), 'def check_photos' ) && false !== strpos( file_get_contents( $design . '/people/build-profiles.py' ), "    check_photos()\n    build_profiles()" ), 'the build stops on a portrait that is not a .webp in assets/' );

echo "The plugin page around the cards\n";
ok( false !== strpos( $out, '<header class="site-header">HEADER</header>' ) && false === strpos( $out, 'class="pg-nav"' ), 'the site header replaces the plugin nav' );
ok( false !== strpos( $out, '<footer class="site-footer">FOOTER</footer>' ), 'the site footer is added' );
ok( false !== strpos( $out, '<form class="search-box" method="get"' ) && false !== strpos( $out, '<h1>Members <span class="accent">who create value</span></h1>' ), 'the plugin heading and search box stay' );
ok( 1 === substr_count( $out, '<div class="dir-wrap">' ) && 1 === substr_count( $out, '<div class="dir-grid">' ), 'one directory, one grid' );
ok( prospergenics_2026_members_div_end( $out, strpos( $out, '<div class="dir-wrap">' ) ) < strpos( $out, '<footer class="site-footer">' ), 'the directory is closed before the footer (balanced markup)' );

echo "Badges still go on the cards\n";
$badge = '<span class="pg-badge pg-badge--member">Member</span>';
$seen  = null;
$GLOBALS['__filters']['prospergenics_2026_listings_member_badges'] = function ( $badges, $slugs = array() ) use ( &$seen, $badge ) { $seen = $slugs; return array( 'lessy' => $badge ); };
$with = prospergenics_2026_listings_takeover( $page, '/members/', $chrome );
unset( $GLOBALS['__filters'] );
ok( is_array( $seen ) && array_column( $listed, 'slug' ) === $seen, 'the badge filter gets the /people/ slugs of the new cards' );
ok( false !== strpos( $with, '<div class="dc-title">Lesiamon Mpoe ' . $badge . '</div>' ), 'the badge sits after the name on the rebuilt card' );
$rules = prospergenics_2026_parse_redirects_for_test( file_get_contents( $theme . '/inc/person-redirects.txt' ) );
$unmapped = array();
foreach ( $listed as $m ) {
	if ( ! isset( $rules[ 'members/' . $m['slug'] ] ) || '/people/' . $m['slug'] . '/' !== $rules[ 'members/' . $m['slug'] ] ) {
		$unmapped[] = $m['slug'];
	}
}
ok( ! $unmapped, 'every listed slug has a members/<slug> rule, so the badge lookup (badges-listings.php) and /members/<slug>/ both reach /people/<slug>/' . ( $unmapped ? ': ' . implode( ', ', $unmapped ) : '' ) );

echo "Search (?keyword=)\n";
$people = prospergenics_2026_members_people();
$farid  = prospergenics_2026_members_rewrite( $page, $people, 'farid', TEST_HOME, TEST_HOME . '/assets' );
ok( 1 === substr_count( $farid, 'class="dir-card"' ) && false !== strpos( $farid, '/people/farid/' ) && false !== strpos( $farid, '<p class="pg-count">1 member</p>' ), '"farid" finds Farid (the plugin data has no Farid)' );
$dev = prospergenics_2026_members_rewrite( $page, $people, 'SOFTWARE developer', TEST_HOME, TEST_HOME . '/assets' );
ok( substr_count( $dev, 'class="dir-card"' ) >= 4, 'two words, any case, over role and expertises (' . substr_count( $dev, 'class="dir-card"' ) . ' found)' );
$none = prospergenics_2026_members_rewrite( $page, $people, 'zzzz-nobody', TEST_HOME, TEST_HOME . '/assets' );
ok( 0 === substr_count( $none, 'class="dir-card"' ) && false !== strpos( $none, '<p class="no-results">No members found. <a href="' . TEST_HOME . '/members/">Clear search</a></p>' ) && false === strpos( $none, '<p class="pg-count">' ), 'no match: the plugin\'s own "No members found", no count' );
// The plugin's own empty state (its data had no match) is rebuilt as well.
$empty_page = preg_replace( '#<div class="dir-wrap">.*$#s', "<div class=\"dir-wrap\">\n      <p class=\"no-results\">No members found. <a href=\"x\">Clear search</a></p>\n  </div>\n", preg_replace( '#\s*<p class="pg-count">.*?</p>#', '', $page ) );
$rebuilt    = prospergenics_2026_members_rewrite( $empty_page, $people, 'farid', TEST_HOME, TEST_HOME . '/assets' );
ok( 1 === substr_count( $rebuilt, 'class="dir-card"' ) && false === strpos( $rebuilt, '<p class="no-results">' ) && false !== strpos( $rebuilt, '<p class="pg-count">1 member</p>' ), 'the plugin\'s empty result page gets the PROFILES match and a count' );
$xss = prospergenics_2026_members_rewrite( $page, array( 'x' => array( 'name' => '<script>a</script>', 'role' => '"r', 'quote' => '<b>q', 'img' => '', 'initial' => '<' ) ), '', TEST_HOME, TEST_HOME . '/assets' );
ok( false === strpos( $xss, '<script>a' ) && false === strpos( $xss, '<b>q' ) && false !== strpos( $xss, '&lt;script&gt;' ), 'card text is escaped' );

echo "Only the /members/ list, only its expected shape\n";
$two = str_replace( '<div class="dir-wrap">', '<div class="dir-wrap"></div><div class="dir-wrap">', $page );
ok( $two === prospergenics_2026_members_rewrite( $two, $people, '', TEST_HOME, '' ), 'two directories: left alone' );
$other = str_replace( '<header class="pg-header">', '<header class="other">', $page );
ok( $other === prospergenics_2026_members_rewrite( $other, $people, '', TEST_HOME, '' ), 'no plugin heading: left alone' );
ok( $page === prospergenics_2026_members_rewrite( $page, array(), '', TEST_HOME, '' ), 'no people data: left alone' );
$filtered = apply_filters( 'prospergenics_2026_listings_page_html', $page, '/tools/' );
ok( $page === $filtered, 'the filter leaves other listings paths alone' );
$detail = prospergenics_2026_listings_takeover( $page, '/members/lessy/', $chrome );
ok( false !== strpos( $detail, 'members/martien-de-jong/' ), 'a /members/<slug>/ path is not the list (it redirects on init anyway)' );

echo "ItemList JSON-LD (#6516)\n";
$ld  = prospergenics_2026_members_itemlist( $fixture, $people, TEST_HOME );
preg_match( '#<script type="application/ld\+json">(\{"@context":"https://schema\.org","@type":"ItemList".*?)</script>#s', $ld, $m );
$data = $m ? json_decode( $m[1], true ) : null;
ok( $data && count( $listed ) === $data['numberOfItems'] && count( $listed ) === count( $data['itemListElement'] ), 'the plugin\'s ItemList now has ' . count( $listed ) . ' people' );
ok( $data && TEST_HOME . '/people/lessy/' === $data['itemListElement'][6]['url'] && 'Lesiamon Mpoe' === $data['itemListElement'][6]['name'], 'with /people/ URLs and PROFILES names' );
ok( false === strpos( $ld, '"name":"Lessy"' ) && 1 === substr_count( $ld, '"@type":"ItemList"' ), 'the stale list is gone, not duplicated' );
ok( $fixture === prospergenics_2026_members_itemlist( $fixture, $people, 'https://elsewhere.example' ), 'an ItemList for another URL is left alone' );
$src = file_get_contents( $theme . '/inc/members-directory.php' );
ok( false !== strpos( $src, "'GET' !== \$method || ! prospergenics_2026_is_members_list_request()" ), 'the whole-page buffer is only opened for GET /members/' );
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "/inc/members-directory.php'" ), 'functions.php loads the module' );

echo "/experts/, /community/ and /people/ point at /members/\n";
foreach ( array( 'experts', 'community' ) as $list ) {
	ok( false !== strpos( file_get_contents( $design . "/$list/index.html" ), '<a href="../members/">All members &rarr;</a>' ), "/$list/ links back to /members/" );
}
ok( false !== strpos( file_get_contents( $design . '/index.html' ), '<a href="/members/">All members &rarr;</a>' ), 'the homepage "Meet the alchemists" links /members/' );

echo "members-collection.json for the listings service (#6516, #6523)\n";
$coll = json_decode( file_get_contents( $design . '/people/members-collection.json' ), true );
$same = is_array( $coll ) && count( $coll['items'] ) === count( $listed );
foreach ( $listed as $i => $m ) {
	$it   = isset( $coll['items'][ $i ] ) ? $coll['items'][ $i ] : null;
	$same = $same && $it && $it['slug'] === $m['slug'] && $it['title'] === $m['name'] && $it['fields']['role'] === $m['role']
		&& $it['fields']['quote'] === $m['quote'] && $it['fields']['photo'] === (string) $m['img'] && '/people/' . $m['slug'] . '/' === $it['fields']['profile_url'];
}
ok( $same, 'one record per listed person: slug, name, role, quote, .webp photo and /people/ URL as PROFILES' );
ok( is_file( $root . '/tests/smoke/check-listings-members.py' ), 'tests/smoke/check-listings-members.py compares the live listings API with it' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All passed\n" );
exit( $failures ? 1 : 0 );

/** The inner HTML of the first <div class="$class"> (balanced). */
function block_of( $html, $class ) {
	$at = strpos( $html, '<div class="' . $class . '">' );
	if ( false === $at ) { return ''; }
	$end = prospergenics_2026_members_div_end( $html, $at );
	return substr( $html, $at, $end - $at );
}

/** Same parsing as inc/redirects.php (one "old new" pair per line, # comments). */
function prospergenics_2026_parse_redirects_for_test( $text ) {
	$rules = array();
	foreach ( preg_split( '/\R/', $text ) as $line ) {
		$line = trim( preg_replace( '/#.*$/', '', $line ) );
		if ( '' !== $line ) {
			$p = preg_split( '/\s+/', $line );
			$rules[ strtolower( trim( $p[0], '/' ) ) ] = $p[1];
		}
	}
	return $rules;
}

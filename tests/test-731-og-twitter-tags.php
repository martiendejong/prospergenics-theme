<?php
/**
 * Standalone test for JengoWork task 731: prospergenics_add_social_meta_tags() must not render
 * an empty og:description/twitter:description on the front page, and must pick a consistent
 * og:type ("website" on the front page / archives, "article" only on single posts) instead of
 * falling into the is_singular() branch (with its empty-content fallback) for every page type.
 *
 * This extracts the real function body straight out of functions.php via a regex, so the test
 * always exercises the actual current source rather than a copy that can drift from it.
 *
 * Run with: php tests/test-731-og-twitter-tags.php
 */

// ────────────────────── WP core function stubs ───────────────────────────
function get_bloginfo( $key = '' ) {
	if ( $key === 'name' ) return 'Prospergenics';
	if ( $key === 'description' ) return 'Prospergenics';
	return '';
}
function get_template_directory_uri() { return 'https://prospergenics.com/wp-content/themes/prospergenics-theme'; }
function home_url( $path = '/' ) { return 'https://prospergenics.com' . $path; }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return (string) $s; }
function wp_trim_words( $text, $num_words = 55 ) {
	$words = preg_split( '/\s+/', trim( (string) $text ) );
	$words = array_filter( $words );
	return implode( ' ', array_slice( $words, 0, $num_words ) );
}
function add_action( ...$args ) {}
function add_filter( ...$args ) {}
function get_the_title( $id = 0 ) { return $id === 42 ? 'Blog' : 'About Us'; }
function get_permalink( $id = 0 ) { return $id === 42 ? 'https://prospergenics.com/blog/' : 'https://prospergenics.com/about/'; }
function get_option( $key, $default = false ) { return $key === 'page_for_posts' ? $GLOBALS['_page_for_posts'] : $default; }
function get_post_field( $field, $id ) { return ''; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( (string) $s ) ); }
function user_trailingslashit( $s ) { return rtrim( (string) $s, '/' ) . '/'; }
$GLOBALS['_page_for_posts'] = 0;
function has_post_thumbnail() { return false; }
function get_post_thumbnail_id() { return 0; }
function wp_get_attachment_image_src( $id, $size ) { return false; }
function get_the_archive_title() { return 'Category: <span>Uncategorized</span>'; }
function get_the_archive_description() { return ''; }

$GLOBALS['_is_front_page'] = false;
$GLOBALS['_is_home']       = false;
$GLOBALS['_is_singular']   = false;
$GLOBALS['_is_single']     = false;
$GLOBALS['_is_archive']    = false;
function is_front_page() { return $GLOBALS['_is_front_page']; }
function is_home() { return $GLOBALS['_is_home']; }
function is_singular() { return $GLOBALS['_is_singular']; }
function is_single() { return $GLOBALS['_is_single']; }
function is_archive() { return $GLOBALS['_is_archive']; }

// ─────────────────── extract the real function from functions.php ────────
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );
if ( ! preg_match( '/(function prospergenics_front_page_meta_description_text\(\)\s*\{.*?\r?\n\})\r?\n/s', $source, $md ) ) {
	fwrite( STDERR, "FAIL: could not locate prospergenics_front_page_meta_description_text() in functions.php\n" );
	exit( 1 );
}
function __( $text, $domain = 'default' ) { return $text; }
eval( $md[1] );

if ( ! preg_match( '/(function prospergenics_add_social_meta_tags\(\)\s*\{.*?\r?\n\})\r?\nadd_action\( \'wp_head\'/s', $source, $m ) ) {
	fwrite( STDERR, "FAIL: could not locate prospergenics_add_social_meta_tags() in functions.php\n" );
	exit( 1 );
}
eval( $m[1] );

// Simulate the static front page: a "page" post type, so it is BOTH is_front_page() and
// is_singular() at once — this is the exact condition that caused the original bug.
class StubPost {
	public $post_excerpt = '';
	public $post_content = '<!-- wp:group --><div></div><!-- /wp:group -->'; // renders no plain text
}

// ─────────────────────────────────────────────────────────────────────────
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

function render( $callback ) {
	ob_start();
	$callback();
	return ob_get_clean();
}

echo "[Test 1] Front page that is ALSO is_singular() with empty post content: og:description is the real front-page description (task 797), og:type is website, one consistent twitter:card\n";
$GLOBALS['_is_front_page'] = true;
$GLOBALS['_is_singular']   = true; // static front page: both true at once, same as live
global $post;
$post = new StubPost();
$output = render( 'prospergenics_add_social_meta_tags' );
ok( strpos( $output, 'og:description" content="' . esc_attr( prospergenics_front_page_meta_description_text() ) . '"' ) !== false, 'og:description is the real front-page description, not the bare "Prospergenics" tagline' );
ok( strpos( $output, 'og:type" content="website"' ) !== false, 'og:type is "website" on the front page' );
ok( substr_count( $output, 'twitter:card' ) === 1, 'exactly one twitter:card tag is emitted' );
ok( strpos( $output, 'twitter:card" content="summary_large_image"' ) !== false, 'twitter:card is summary_large_image' );

echo "\n[Test 2] Regular single post (not front page): og:type is article, og:description uses post content\n";
$GLOBALS['_is_front_page'] = false;
$GLOBALS['_is_singular']   = true;
$GLOBALS['_is_single']     = true;
$post = new StubPost();
$post->post_content = '<p>A real article about something.</p>';
$output = render( 'prospergenics_add_social_meta_tags' );
ok( strpos( $output, 'og:type" content="article"' ) !== false, 'og:type is "article" for a real single post' );
ok( strpos( $output, 'og:description" content="A real article about something."' ) !== false, 'og:description uses the post content' );

echo "\n[Test 3] Ordinary page (is_singular, not front page, not single) with no extractable content: falls back to tagline, og:type website\n";
$GLOBALS['_is_singular'] = true;
$GLOBALS['_is_single']   = false;
$post = new StubPost();
$output = render( 'prospergenics_add_social_meta_tags' );
ok( strpos( $output, 'og:description" content="Prospergenics"' ) !== false, 'empty-content page falls back to the tagline, not a blank description' );
ok( strpos( $output, 'og:type" content="website"' ) !== false, 'og:type is "website" for a plain page' );

echo "
[Test 4] Static posts page (is_home, not front page, not singular): og:title/og:url describe the posts page itself, not the homepage
";
$GLOBALS['_is_front_page'] = false;
$GLOBALS['_is_home']       = true;
$GLOBALS['_is_singular']   = false;
$GLOBALS['_is_single']     = false;
$GLOBALS['_page_for_posts'] = 42;
$output = render( 'prospergenics_add_social_meta_tags' );
ok( strpos( $output, 'og:title" content="Blog"' ) !== false, 'og:title is the posts page title, not the site name' );
ok( strpos( $output, 'og:url" content="https://prospergenics.com/blog/"' ) !== false, 'og:url is the posts page permalink, not the homepage' );
ok( strpos( $output, 'og:description" content="Prospergenics"' ) !== false, 'og:description falls back to the tagline when the posts page has no excerpt' );
$GLOBALS['_is_home'] = false;
$GLOBALS['_page_for_posts'] = 0;

echo "
[Test 5] Category archive: og:title has no HTML, og:url is the archive request URL (not the first post's permalink)
";
$GLOBALS['_is_singular'] = false;
$GLOBALS['_is_archive']  = true;
$wp = new stdClass();
$wp->request = 'category/uncategorized';
$output = render( 'prospergenics_add_social_meta_tags' );
ok( strpos( $output, 'og:title" content="Category: Uncategorized"' ) !== false, 'archive og:title is plain text (the <span> from get_the_archive_title() is stripped)' );
ok( strpos( $output, 'og:url" content="https://prospergenics.com/category/uncategorized/"' ) !== false, 'archive og:url is the archive URL itself' );
ok( strpos( $output, 'og:description" content="Prospergenics"' ) !== false, 'empty archive description falls back to the tagline' );
$GLOBALS['_is_archive'] = false;
echo "\n" . ( $failures === 0 ? "ALL PASSED\n" : "$failures FAILURE(S)\n" );
exit( $failures === 0 ? 0 : 1 );

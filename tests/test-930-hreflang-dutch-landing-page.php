<?php
/**
 * Standalone test for JengoWork task 930: the site was English-only (lang="en-US", zero
 * hreflang tags) despite running AI/dev coaching for both the Netherlands and Kenya. This
 * verifies the new Dutch landing page (claude-code-cursor-coaching-nl) and its English
 * counterpart (claude-code-cursor-coaching) get correct reciprocal hreflang tags, that the
 * Dutch page's <html lang> is overridden to "nl-NL" without touching any other page, and that
 * the Dutch page gets a real meta description with Yoast's own presenter removed.
 *
 * Run with: php tests/test-930-hreflang-dutch-landing-page.php
 */

function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function __( $text, $domain = 'default' ) { return $text; }
function add_action( ...$args ) {}
function add_filter( ...$args ) {}

class WP_Post {
	public $ID;
	public $post_name;
	function __construct( $id, $post_name ) {
		$this->ID        = $id;
		$this->post_name = $post_name;
	}
}

// ─────────────────────────── WP query/permalink stubs ────────────────────
$GLOBALS['_current_page_slug'] = '';
$GLOBALS['_pages_by_slug']     = array(
	'claude-code-cursor-coaching'    => new WP_Post( 790, 'claude-code-cursor-coaching' ),
	'claude-code-cursor-coaching-nl' => new WP_Post( 791, 'claude-code-cursor-coaching-nl' ),
	'about'                          => new WP_Post( 352, 'about' ),
);
$GLOBALS['_permalinks'] = array(
	790 => 'https://prospergenics.com/claude-code-cursor-coaching/',
	791 => 'https://prospergenics.com/claude-code-cursor-coaching-nl/',
	352 => 'https://prospergenics.com/about/',
);
// Toggle to simulate the Dutch page not existing yet in WP (partner-missing case).
$GLOBALS['_dutch_page_exists'] = true;

function is_page( $page = '' ) {
	if ( '' === $page ) {
		return '' !== $GLOBALS['_current_page_slug'];
	}
	return $GLOBALS['_current_page_slug'] === $page;
}

function get_queried_object() {
	$slug = $GLOBALS['_current_page_slug'];
	if ( '' === $slug || ! isset( $GLOBALS['_pages_by_slug'][ $slug ] ) ) {
		return null;
	}
	return $GLOBALS['_pages_by_slug'][ $slug ];
}

function get_page_by_path( $path ) {
	if ( 'claude-code-cursor-coaching-nl' === $path && ! $GLOBALS['_dutch_page_exists'] ) {
		return null;
	}
	return $GLOBALS['_pages_by_slug'][ $path ] ?? null;
}

function get_permalink( $post ) {
	$id = is_object( $post ) ? $post->ID : $post;
	return $GLOBALS['_permalinks'][ $id ] ?? '';
}

function reset_state( $slug ) {
	$GLOBALS['_current_page_slug'] = $slug;
}

// ─────────────────── extract the real functions from functions.php ───────
$source = file_get_contents( dirname( __DIR__ ) . '/functions.php' );

function extract_function( $source, $name ) {
	if ( ! preg_match( '/(function ' . preg_quote( $name, '/' ) . '\([^)]*\)\s*\{.*?\r?\n\})\r?\n/s', $source, $m ) ) {
		fwrite( STDERR, "FAIL: could not locate $name() in functions.php\n" );
		exit( 1 );
	}
	eval( $m[1] );
}

extract_function( $source, 'prospergenics_hreflang_pairs' );
extract_function( $source, 'prospergenics_output_hreflang_tags' );
extract_function( $source, 'prospergenics_dutch_page_language_attributes' );
extract_function( $source, 'prospergenics_nl_landing_meta_description_text' );
extract_function( $source, 'prospergenics_nl_landing_remove_yoast_description_presenter' );
extract_function( $source, 'prospergenics_nl_landing_output_meta_description' );

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

echo "[Test 1] English page: reciprocal hreflang tags, self=en, partner=nl, x-default=en\n";
reset_state( 'claude-code-cursor-coaching' );
$output = render( 'prospergenics_output_hreflang_tags' );
ok( substr_count( $output, '<link rel="alternate"' ) === 3, 'exactly 3 alternate link tags are printed' );
ok( strpos( $output, 'hreflang="en" href="https://prospergenics.com/claude-code-cursor-coaching/"' ) !== false, 'self-referencing en tag points at the English URL' );
ok( strpos( $output, 'hreflang="nl" href="https://prospergenics.com/claude-code-cursor-coaching-nl/"' ) !== false, 'nl tag points at the Dutch URL' );
ok( strpos( $output, 'hreflang="x-default" href="https://prospergenics.com/claude-code-cursor-coaching/"' ) !== false, 'x-default points at the English URL' );

echo "\n[Test 2] Dutch page: reciprocal hreflang tags, self=nl, partner=en, x-default=en\n";
reset_state( 'claude-code-cursor-coaching-nl' );
$output = render( 'prospergenics_output_hreflang_tags' );
ok( substr_count( $output, '<link rel="alternate"' ) === 3, 'exactly 3 alternate link tags are printed' );
ok( strpos( $output, 'hreflang="nl" href="https://prospergenics.com/claude-code-cursor-coaching-nl/"' ) !== false, 'self-referencing nl tag points at the Dutch URL' );
ok( strpos( $output, 'hreflang="en" href="https://prospergenics.com/claude-code-cursor-coaching/"' ) !== false, 'en tag points at the English URL' );
ok( strpos( $output, 'hreflang="x-default" href="https://prospergenics.com/claude-code-cursor-coaching/"' ) !== false, 'x-default still points at the English URL, not the Dutch one' );

echo "\n[Test 3] Unrelated page: no hreflang tags printed at all\n";
reset_state( 'about' );
$output = render( 'prospergenics_output_hreflang_tags' );
ok( '' === $output, 'a page with no configured pair prints nothing' );

echo "\n[Test 4] Dutch page not yet created in WP: English page prints nothing rather than a 404 link\n";
$GLOBALS['_dutch_page_exists'] = false;
reset_state( 'claude-code-cursor-coaching' );
$output = render( 'prospergenics_output_hreflang_tags' );
ok( '' === $output, 'fails quiet when the partner translation does not exist yet' );
$GLOBALS['_dutch_page_exists'] = true;

echo "\n[Test 5] Dutch page: <html lang> is overridden to nl-NL\n";
reset_state( 'claude-code-cursor-coaching-nl' );
ok( prospergenics_dutch_page_language_attributes( 'lang="en-US"', 'html' ) === 'lang="nl-NL"', 'lang="en-US" is rewritten to lang="nl-NL"' );
ok( prospergenics_dutch_page_language_attributes( 'lang="en-US" dir="ltr"', 'html' ) === 'lang="nl-NL" dir="ltr"', 'other attributes on the tag (e.g. dir) are preserved' );

echo "\n[Test 6] Every other page: <html lang> is left completely untouched\n";
reset_state( 'claude-code-cursor-coaching' );
ok( prospergenics_dutch_page_language_attributes( 'lang="en-US"', 'html' ) === 'lang="en-US"', 'English page keeps lang="en-US"' );
reset_state( 'about' );
ok( prospergenics_dutch_page_language_attributes( 'lang="en-US"', 'html' ) === 'lang="en-US"', '/about/ keeps lang="en-US"' );

echo "\n[Test 7] Dutch page: real meta description is printed\n";
reset_state( 'claude-code-cursor-coaching-nl' );
$output = render( 'prospergenics_nl_landing_output_meta_description' );
ok( substr_count( $output, '<meta name="description"' ) === 1, 'exactly one meta description tag is printed' );
ok( strpos( $output, 'Claude Code en Cursor voor Nederlandse ontwikkelteams' ) !== false, 'description text is the real Dutch copy' );

echo "\n[Test 8] Every other page: this function prints nothing\n";
reset_state( 'claude-code-cursor-coaching' );
ok( '' === render( 'prospergenics_nl_landing_output_meta_description' ), 'English page: no output' );
reset_state( '' );
ok( '' === render( 'prospergenics_nl_landing_output_meta_description' ), 'no page context: no output' );

echo "\n[Test 9] Dutch page: Yoast's Meta_Description_Presenter is removed, other presenters untouched\n";
eval( 'namespace Yoast\WP\SEO\Generators\Presenters { class Meta_Description_Presenter {} } namespace Yoast\WP\SEO\Generators\Presenters { class Robots_Presenter {} }' );
$real_presenters = array(
	new \Yoast\WP\SEO\Generators\Presenters\Meta_Description_Presenter(),
	new \Yoast\WP\SEO\Generators\Presenters\Robots_Presenter(),
);
reset_state( 'claude-code-cursor-coaching-nl' );
$filtered = prospergenics_nl_landing_remove_yoast_description_presenter( $real_presenters );
ok( count( $filtered ) === 1, 'Meta_Description_Presenter is removed on the Dutch page' );
ok( $filtered[1] instanceof \Yoast\WP\SEO\Generators\Presenters\Robots_Presenter, 'unrelated presenters (e.g. Robots_Presenter) are left untouched' );

echo "\n[Test 10] Every other page: presenters are returned unmodified\n";
reset_state( 'claude-code-cursor-coaching' );
$filtered_other = prospergenics_nl_landing_remove_yoast_description_presenter( $real_presenters );
ok( count( $filtered_other ) === 2, 'Meta_Description_Presenter is left in place on the English page' );

echo "\n" . ( $failures === 0 ? "ALL PASSED\n" : "$failures FAILURE(S)\n" );
exit( $failures === 0 ? 0 : 1 );

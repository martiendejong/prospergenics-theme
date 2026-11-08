<?php
/**
 * Standalone test for "See it work" on the tool pages (card #7218): inc/tool-demos.php, its step in
 * the jengo-listings takeover (inc/site-chrome.php), the CoachOS "Try it live" button and the first
 * demo folder, assets/tool-demos/jengo/. Guards:
 *   - the manifest is checked strictly: plain file names that exist in the folder (no paths, no URLs),
 *     numbers that are numbers, a cursor inside its image, capped text; a broken step is left out and
 *     a manifest with nothing usable left gives no demo,
 *   - the section renders steps only, video only, or both (tabs), everything escaped,
 *   - it goes before "← All tools" on a tool page (saved fixture from the live /tools/jengo-meet/),
 *     only when that link is there once; no folder or another page: the page is left exactly as it was,
 *   - /tools/coachos/ gets "Try it live" with the real CoachOS embed; the homepage embed is unchanged,
 *   - every demo folder in the repo (now: jengo) passes the validator; the Jengo screenshot is unedited.
 *
 * Run with: php tests/test-2026-tool-demos.php
 */

define( 'ABSPATH', dirname( __DIR__ ) . '/wp-theme/prospergenics-2026/' );

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
$GLOBALS['__tpl'] = $theme;
function home_url( $path = '' ) { return TEST_HOME . '/' . ltrim( (string) $path, '/' ); }
function get_template_directory() { return $GLOBALS['__tpl']; }
function get_template_directory_uri() { return TEST_HOME . '/wp-content/themes/prospergenics-2026'; }
function esc_url( $u ) { return htmlspecialchars( (string) $u, ENT_QUOTES ); }
function esc_html( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function esc_attr( $t ) { return htmlspecialchars( (string) $t, ENT_QUOTES ); }
function trailingslashit( $s ) { return rtrim( $s, '/' ) . '/'; }
function untrailingslashit( $s ) { return rtrim( $s, '/' ); }
function wp_unslash( $v ) { return $v; }
function wp_strip_all_tags( $s ) { return strip_tags( $s ); }
function apply_filters( $tag, $value, ...$args ) { return isset( $GLOBALS['__filters'][ $tag ] ) ? $GLOBALS['__filters'][ $tag ]( $value, ...$args ) : $value; }
function add_action( $tag, $cb, $prio = 10, $args = 1 ) {}
function add_filter( $tag, $cb, $prio = 10, $args = 1 ) {}
function add_query_arg( $key, $value = null, $url = null ) { return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value; }
function get_current_user_id() { return 0; }
function get_userdata( $id ) { return false; }
function get_user_meta( $id, $k, $single = false ) { return ''; }
function sanitize_title( $s ) { return trim( preg_replace( '/[^a-z0-9]+/', '-', strtolower( (string) $s ) ), '-' ); }
function wp_json_encode( $v ) { return json_encode( $v ); }

require $theme . '/inc/site-chrome.php';
require $theme . '/inc/coach-context.php';
require $theme . '/inc/tool-demos.php';

/** A WebP header getimagesize() reads (VP8X: just the canvas size). */
function fake_webp( $w, $h ) {
	$vp8x = 'VP8X' . pack( 'V', 10 ) . "\0\0\0\0" . substr( pack( 'V', $w - 1 ), 0, 3 ) . substr( pack( 'V', $h - 1 ), 0, 3 );
	return 'RIFF' . pack( 'V', 4 + strlen( $vp8x ) ) . 'WEBP' . $vp8x;
}
function img( $w = 1440, $h = 900, $bytes = 60000 ) { return array( 'bytes' => $bytes, 'width' => $w, 'height' => $h ); }
function blob( $bytes = 1000 ) { return array( 'bytes' => $bytes, 'width' => null, 'height' => null ); }
function step( $n, array $over = array() ) {
	return array_merge( array(
		'image' => "step-$n.webp", 'width' => 1440, 'height' => 900,
		'alt' => "Screen $n of the tool", 'caption' => "What happens in step $n.",
		'cursor' => array( 'x' => 612, 'y' => 238 ), 'action' => 'click',
	), $over );
}
$files = array(
	'step-1.webp' => img(), 'step-2.webp' => img(), 'step-3.webp' => img(), 'step-4.webp' => img(),
	'step-5.webp' => img(), 'step-6.webp' => img(), 'step-7.webp' => img(),
	'small.webp' => img( 1280, 800 ), 'huge.webp' => img( 1440, 900, 300 * 1024 ),
	'demo.webm' => blob( 3000000 ), 'demo.mp4' => blob( 3000000 ), 'big.mp4' => blob( 5 * 1024 * 1024 ),
	'poster.webp' => img(), 'demo.vtt' => blob( 900 ), 'demo.json' => blob( 900 ),
);
$video = array( 'webm' => 'demo.webm', 'mp4' => 'demo.mp4', 'poster' => 'poster.webp', 'captions' => 'demo.vtt' );
function manifest( array $over = array() ) {
	global $video;
	return array_merge( array( 'tool' => 'jengo-meet', 'title' => 'From meeting to action points', 'source' => 'Real Jengo Meet, demo account with demo data', 'captured' => '2026-11-12', 'video' => $video, 'steps' => array( step( 1 ), step( 2, array( 'action' => 'type' ) ), step( 3, array( 'action' => 'look' ) ) ) ), $over );
}
function check( array $steps, array $over = array() ) {
	global $files;
	return prospergenics_2026_tool_demo_validate( manifest( array( 'steps' => $steps, 'video' => null ) + $over ), 'jengo-meet', $files );
}

echo "validation (pure)\n";
$r = prospergenics_2026_tool_demo_validate( manifest(), 'jengo-meet', $files );
ok( $r['demo'] && 3 === count( $r['demo']['steps'] ) && $r['demo']['video'] && array() === $r['errors'], 'a manifest as in the spec is valid: 3 steps and the video, nothing reported' );
ok( 1440 === $r['demo']['video']['width'] && 900 === $r['demo']['video']['height'], 'the video takes its width and height from the poster' );
ok( 'click' === $r['demo']['steps'][0]['action'] && 612.0 === $r['demo']['steps'][0]['x'] && 'type' === $r['demo']['steps'][1]['action'], 'click and type steps keep their cursor' );
ok( 'look' === $r['demo']['steps'][2]['action'] && null === $r['demo']['steps'][2]['x'], 'a look step shows no cursor, even when one is given' );
ok( '2026-11-12' === $r['demo']['captured'] && '' === check( array( step( 1 ) ), array( 'captured' => '2026-02-30' ) )['demo']['captured'], 'captured must be a real YYYY-MM-DD date' );
ok( null === prospergenics_2026_tool_demo_validate( manifest(), 'jengo', $files )['demo'], '"tool" must name the folder: a manifest copied into the wrong folder shows nothing' );
foreach ( array( 'a list' => array( step( 1 ) ), 'a string' => 'demo', 'null (bad JSON)' => null, 'a number' => 3 ) as $what => $data ) {
	ok( null === prospergenics_2026_tool_demo_validate( $data, 'jengo-meet', $files )['demo'], "demo.json that is $what gives no demo" );
}
$bad_names = array(
	'../step-1.webp'                   => 'path traversal',
	'..'                               => 'parent folder',
	'sub/step-1.webp'                  => 'a sub folder',
	'/etc/passwd'                      => 'an absolute path',
	'https://evil.example/step-1.webp' => 'a URL',
	'//evil.example/step-1.webp'       => 'a protocol-relative URL',
	'Step-1.webp'                      => 'capitals',
	'.step-1.webp'                     => 'a hidden file',
	'step 1.webp'                      => 'a space',
	"step-1.webp\0.png"                => 'a NUL byte',
	'step-1.webp%2f'                   => 'an encoded slash',
	'step-1.webp"onerror="x'           => 'a quote',
);
foreach ( $bad_names as $name => $why ) {
	$v = check( array( step( 1, array( 'image' => $name ) ), step( 2 ) ) );
	ok( 1 === count( $v['demo']['steps'] ) && 'step-2.webp' === $v['demo']['steps'][0]['image'] && false !== strpos( $v['errors'][0], 'plain file name' ), "image name with $why: that step is left out, the rest stays" );
}
ok( null === check( array( step( 1, array( 'image' => array( 'step-1.webp' ) ) ) ) )['demo'], 'an image that is not a string is left out' );
$v = check( array( step( 1, array( 'image' => 'step-9.webp' ) ), step( 2 ) ) );
ok( 1 === count( $v['demo']['steps'] ) && false !== strpos( $v['errors'][0], 'is not in the folder' ), 'a step whose image is missing is left out' );
ok( false !== strpos( check( array( step( 1, array( 'image' => 'demo.vtt' ) ), step( 2 ) ) )['errors'][0], 'must be a .webp' ), 'a step image must be an image file' );
ok( false !== strpos( check( array( step( 1, array( 'image' => 'huge.webp' ) ), step( 2 ) ) )['errors'][0], 'larger than 250 KB' ), 'a screenshot over 250 KB is left out (slow connections, #6469)' );
foreach ( array( 'a string' => '1440', 'a float' => 1440.0, 'zero' => 0, 'negative' => -1440, 'true' => true, 'missing' => null ) as $what => $w ) {
	ok( 1 === count( check( array( step( 1, array( 'width' => $w ) ), step( 2 ) ) )['demo']['steps'] ), "width that is $what: step left out" );
}
ok( false !== strpos( check( array( step( 1, array( 'image' => 'small.webp' ) ) ) )['errors'][0], 'do not match the image (1280×800)' ), 'width and height must be the image\'s real size' );
$v = check( array( step( 1 ), step( 2, array( 'image' => 'small.webp', 'width' => 1280, 'height' => 800 ) ) ) );
ok( 1 === count( $v['demo']['steps'] ) && false !== strpos( $v['errors'][0], 'not the same size as the first step' ), 'every step has the size of the first, so the frame never changes shape' );
foreach ( array(
	'a string'  => array( 'x' => '612', 'y' => 238 ),
	'true'      => array( 'x' => true, 'y' => 238 ),
	'no y'      => array( 'x' => 612 ),
	'a list'    => array( 612, 238 ),
	'not a map' => '612,238',
) as $what => $c ) {
	ok( 1 === count( check( array( step( 1, array( 'cursor' => $c ) ), step( 2 ) ) )['demo']['steps'] ), "cursor with $what: step left out" );
}
foreach ( array( array( 1441, 10 ), array( 10, 901 ), array( -1, 10 ), array( 10, -0.5 ), array( 1e9, 1e9 ) ) as $c ) {
	$v = check( array( step( 1, array( 'cursor' => array( 'x' => $c[0], 'y' => $c[1] ) ) ), step( 2 ) ) );
	ok( 1 === count( $v['demo']['steps'] ) && false !== strpos( $v['errors'][0], 'outside the 1440×900 image' ), "cursor ({$c[0]}, {$c[1]}) outside the image: step left out" );
}
$edge = check( array( step( 1, array( 'cursor' => array( 'x' => 1440, 'y' => 0 ) ) ), step( 2, array( 'cursor' => array( 'x' => 720.5, 'y' => 450.25 ) ) ) ) );
ok( 2 === count( $edge['demo']['steps'] ) && 720.5 === $edge['demo']['steps'][1]['x'], 'a cursor on the edge, or between pixels, is fine' );
ok( 1 === count( check( array( step( 1, array( 'action' => 'hover' ) ), step( 2 ) ) )['demo']['steps'] ), 'an unknown action: step left out' );
ok( 1 === count( check( array( step( 1, array( 'cursor' => null ) ), step( 2 ) ) )['demo']['steps'] ), 'a click step without a cursor: step left out' );
$noact = step( 1 );
unset( $noact['action'] );
$nocur = step( 2 );
unset( $nocur['action'], $nocur['cursor'] );
$v = check( array( $noact, $nocur ) );
ok( 'click' === $v['demo']['steps'][0]['action'] && 'look' === $v['demo']['steps'][1]['action'], 'no action: click with a cursor, look without' );
ok( 1 === count( check( array( step( 1, array( 'alt' => '   ' ) ), step( 2 ) ) )['demo']['steps'] ) && 1 === count( check( array( step( 1, array( 'caption' => '' ) ), step( 2 ) ) )['demo']['steps'] ), 'a step needs an alt text and a caption' );
$long = str_repeat( 'The meeting list shows every meeting ', 6 );
$v    = check( array( step( 1, array( 'caption' => $long, 'alt' => str_repeat( 'x ', 400 ) ) ) ) );
ok( mb_strlen( $v['demo']['steps'][0]['caption'] ) <= 120 && '…' === mb_substr( $v['demo']['steps'][0]['caption'], -1 ) && mb_strlen( $v['demo']['steps'][0]['alt'] ) <= 250, 'captions are capped at 120 characters, alt texts at 250, cut with …' );
ok( 'Line one Line two' === check( array( step( 1, array( 'caption' => "Line one\n\t\x07Line two" ) ) ) )['demo']['steps'][0]['caption'], 'control characters and runs of whitespace become one space' );
ok( null === check( array( step( 1, array( 'caption' => "bad \xC3\x28 utf-8" ) ) ) )['demo'], 'text that is not valid UTF-8 counts as empty' );
ok( 1 === count( check( array( step( 1, array( 'caption' => 42 ) ), step( 2 ) ) )['demo']['steps'] ), 'a caption that is not a string counts as empty' );
$seven = check( array_map( 'step', range( 1, 7 ) ) );
ok( 6 === count( $seven['demo']['steps'] ) && false !== strpos( end( $seven['errors'] ), 'more than 6 steps' ), 'at most 6 steps' );
ok( null === check( array() )['demo'] && null === prospergenics_2026_tool_demo_validate( array( 'tool' => 'jengo-meet' ), 'jengo-meet', $files )['demo'], 'an empty manifest renders nothing' );
ok( null === check( array( step( 1, array( 'image' => '../x.webp' ) ), step( 2, array( 'cursor' => array( 'x' => 9999, 'y' => 1 ) ) ) ) )['demo'], 'a manifest with nothing usable left renders nothing' );
ok( null === check( array( 'step-1.webp' ) )['demo'] && null === prospergenics_2026_tool_demo_validate( manifest( array( 'steps' => array( 'a' => step( 1 ) ), 'video' => null ) ), 'jengo-meet', $files )['demo'], '"steps" must be a list of objects' );
// video
$vid = function ( array $over ) use ( $video, $files ) {
	return prospergenics_2026_tool_demo_validate( manifest( array( 'steps' => array(), 'video' => array_merge( $video, $over ) ) ), 'jengo-meet', $files );
};
ok( $vid( array() )['demo']['video'] && array() === $vid( array() )['demo']['steps'], 'video only is a valid demo' );
ok( '' === $vid( array( 'webm' => null ) )['demo']['video']['webm'] && 'demo.mp4' === $vid( array( 'webm' => null ) )['demo']['video']['mp4'], 'mp4 alone is enough' );
ok( null === $vid( array( 'webm' => null, 'mp4' => null ) )['demo'], 'no video file at all: no video' );
ok( null === $vid( array( 'poster' => null ) )['demo'] && null === $vid( array( 'poster' => 'demo.vtt' ) )['demo'], 'the poster is required and must be an image' );
ok( null === $vid( array( 'captions' => 'missing.vtt' ) )['demo'] && null === $vid( array( 'captions' => 'demo.mp4' ) )['demo'], 'the captions file is required and must be .vtt' );
ok( null === $vid( array( 'mp4' => 'https://cdn.example/demo.mp4', 'webm' => null ) )['demo'] && null === $vid( array( 'mp4' => 'big.mp4', 'webm' => null ) )['demo'], 'video by URL, or over 4 MB: no video' );
ok( 'demo.mp4' === $vid( array( 'webm' => '../demo.webm' ) )['demo']['video']['mp4'] && '' === $vid( array( 'webm' => '../demo.webm' ) )['demo']['video']['webm'], 'a bad webm next to a good mp4: the mp4 plays' );
ok( null === prospergenics_2026_tool_demo_validate( manifest( array( 'steps' => array(), 'video' => 'demo.mp4' ) ), 'jengo-meet', $files )['demo'], '"video" must be an object' );
ok( 'jengo' === prospergenics_2026_tool_demo_slug_for_path( '/tools/jengo/' ) && 'jengo-meet' === prospergenics_2026_tool_demo_slug_for_path( 'tools/jengo-meet' ) && '' === prospergenics_2026_tool_demo_slug_for_path( '/tools/' ) && '' === prospergenics_2026_tool_demo_slug_for_path( '/services/coachos/' ) && '' === prospergenics_2026_tool_demo_slug_for_path( '/tools/jengo/extra/' ) && '' === prospergenics_2026_tool_demo_slug_for_path( '/tools/../etc/' ), 'only /tools/<slug>/ is a tool page' );

echo "loading a folder from disk\n";
$tmp = sys_get_temp_dir() . '/pg-tool-demos-' . getmypid();
$dir = $tmp . '/assets/tool-demos/demo-tool';
@mkdir( $dir, 0777, true );
file_put_contents( "$dir/step-1.webp", fake_webp( 1440, 900 ) );
file_put_contents( "$dir/step-2.webp", fake_webp( 1440, 900 ) );
file_put_contents( "$dir/Upper.webp", fake_webp( 1440, 900 ) );
file_put_contents( "$tmp/outside.webp", fake_webp( 1440, 900 ) );
@symlink( "$tmp/outside.webp", "$dir/linked.webp" );
$m = array( 'tool' => 'demo-tool', 'source' => 'Real screenshots of the demo tool', 'steps' => array( step( 1 ), step( 2 ), step( 3, array( 'image' => 'linked.webp' ) ), step( 4, array( 'image' => 'Upper.webp' ) ) ) );
file_put_contents( "$dir/demo.json", json_encode( $m ) );
$GLOBALS['__tpl'] = $tmp;
$scan = prospergenics_2026_tool_demo_scan( $dir );
ok( isset( $scan['step-1.webp'] ) && 1440 === $scan['step-1.webp']['width'] && null === $scan['demo.json']['width'], 'the folder scan reads each image\'s real size' );
ok( ! isset( $scan['linked.webp'] ) && ! isset( $scan['Upper.webp'] ), 'symlinks and names outside the spec are not files of the demo' );
$loaded = prospergenics_2026_tool_demo_load( 'demo-tool' );
ok( $loaded && 2 === count( $loaded['steps'] ) && TEST_HOME . '/wp-content/themes/prospergenics-2026/assets/tool-demos/demo-tool/' === $loaded['base'], 'a folder dropped into assets/tool-demos/ loads with no code change; the symlinked step is left out' );
ok( null === prospergenics_2026_tool_demo_load( 'no-such-tool' ) && null === prospergenics_2026_tool_demo_load( '../tool-demos/demo-tool' ) && null === prospergenics_2026_tool_demo_load( '' ), 'no folder, or a slug that is not a slug: nothing' );
file_put_contents( "$dir/demo.json", '{"tool": "demo-tool", "steps": [' );
ok( null === prospergenics_2026_tool_demo_load( 'demo-tool' ), 'broken JSON: nothing' );
file_put_contents( "$dir/demo.json", json_encode( $m ) . str_repeat( ' ', 70 * 1024 ) );
ok( null === prospergenics_2026_tool_demo_load( 'demo-tool' ), 'a demo.json over 64 KB is not read' );
file_put_contents( "$dir/demo.json", json_encode( $m ) );
clearstatcache();
ok( array() === prospergenics_2026_tool_demo_chrome( '/services/coachos/' ) && array() === prospergenics_2026_tool_demo_chrome( '/tools/' ) && array() === prospergenics_2026_tool_demo_chrome( '/tools/jengo-meet/' ), 'other pages, and a tool without a folder, get nothing to add' );
$dc = prospergenics_2026_tool_demo_chrome( '/tools/demo-tool/' );
ok( $dc['tool_demo'] && false !== strpos( $dc['tool_demo_script'], '<script id="prospergenics-tool-demo-js">' ) && '' !== $dc['tool_demo_css'] && '' === $dc['tool_try_live'], 'a tool with a folder gets its demo, styles and script' );
@unlink( "$dir/linked.webp" );
array_map( 'unlink', glob( "$dir/*" ) );
@unlink( "$tmp/outside.webp" );
@rmdir( $dir );
@rmdir( dirname( $dir ) );
@rmdir( "$tmp/assets" );
@rmdir( $tmp );
$GLOBALS['__tpl'] = $theme;

echo "rendering\n";
$base = TEST_HOME . '/wp-content/themes/prospergenics-2026/assets/tool-demos/jengo-meet/';
$steps_only = check( array( step( 1 ), step( 2, array( 'action' => 'type', 'cursor' => array( 'x' => 1000, 'y' => 450 ) ) ), step( 3, array( 'action' => 'look' ) ) ) )['demo'] + array( 'base' => $base );
$h = prospergenics_2026_tool_demo_html( $steps_only, 'Jengo Meet' );
ok( 1 === substr_count( $h, '<h2 class="pg-demo-h" id="pg-demo-h">See it work</h2>' ) && false !== strpos( $h, '<section class="pg-demo" id="see-it-work" aria-labelledby="pg-demo-h" data-pg-demo' ), 'a labelled "See it work" section' );
ok( false !== strpos( $h, '</span>Real screenshots of Jengo Meet</p>' ) && false !== strpos( $h, '<p class="pg-demo-source">Real Jengo Meet, demo account with demo data</p>' ) && false !== strpos( $h, '<p class="pg-demo-title">From meeting to action points</p>' ), 'steps: "Real screenshots of <Tool>", the title and the source text' );
ok( 3 === substr_count( $h, '<li class="pg-demo-step' ) && 1 === substr_count( $h, 'is-current' ) && false === strpos( $h, '<video' ) && false === strpos( $h, 'role="tablist"' ), 'steps only: three steps, the first current, no video, no tabs' );
ok( 3 === substr_count( $h, 'loading="lazy" decoding="async"' ) && 3 === substr_count( $h, 'width="1440" height="900"' ) && false !== strpos( $h, 'src="' . $base . 'step-1.webp"' ), 'images: lazy, async, with width and height, from the demo folder' );
ok( false !== strpos( $h, 'style="--pg-demo-ratio: 1440 / 900"' ), 'the frame takes its ratio from the images' );
ok( false !== strpos( $h, 'data-action="click" data-x="42.5" data-y="26.444"' ) && false !== strpos( $h, 'data-action="type" data-x="69.444" data-y="50"' ) && false !== strpos( $h, '<li class="pg-demo-step" data-action="look">' ), 'cursor points in % of the image; look steps have none' );
ok( false !== strpos( $h, '<figcaption><span class="pg-demo-n">Step 2 of 3</span> <span class="pg-demo-text">What happens in step 2.</span></figcaption>' ), 'each step has "Step n of N" and its caption (shown as is without JavaScript)' );
$same = $steps_only;
$same['source'] = 'Real screenshots of Jengo Meet';
ok( false === strpos( prospergenics_2026_tool_demo_html( $same, 'Jengo Meet' ), 'pg-demo-source' ), 'a source that only repeats the label is not shown twice' );
$video_only = $vid( array() )['demo'] + array( 'base' => $base );
$h = prospergenics_2026_tool_demo_html( $video_only, 'Jengo Meet' );
ok( false !== strpos( $h, '</span>Screen recording of Jengo Meet</p>' ) && false === strpos( $h, '<ol' ), 'video only: "Screen recording of <Tool>", no steps' );
ok( false !== strpos( $h, '<video controls preload="none" playsinline poster="' . $base . 'poster.webp" width="1440" height="900" aria-label="Screen recording of Jengo Meet">' ), 'video: controls, preload none, playsinline, poster, width and height' );
ok( false === stripos( $h, 'autoplay' ) && false === strpos( $h, 'muted' ) && false === strpos( $h, ' loop' ), 'video never plays by itself' );
ok( false !== strpos( $h, '<source src="' . $base . 'demo.webm" type="video/webm" /><source src="' . $base . 'demo.mp4" type="video/mp4" /><track kind="captions" src="' . $base . 'demo.vtt" srclang="en" label="English" default />' ), 'webm and mp4 sources and default captions' );
$both = $r['demo'] + array( 'base' => $base );
$h    = prospergenics_2026_tool_demo_html( $both, 'Jengo Meet' );
ok( false !== strpos( $h, 'Real screenshots and a screen recording of Jengo Meet' ) && false !== strpos( $h, '<video' ) && 3 === substr_count( $h, '<li class="pg-demo-step' ), 'both: steps and video' );
ok( false !== strpos( $h, '<div class="pg-demo-tabs" role="tablist" aria-label="Ways to see Jengo Meet" hidden><button type="button" role="tab" id="pg-demo-tab-steps" aria-controls="pg-demo-panel-steps" aria-selected="true">Step through</button><button type="button" role="tab" id="pg-demo-tab-video" aria-controls="pg-demo-panel-video" aria-selected="false" tabindex="-1">Watch</button></div>' ), 'both: "Step through" and "Watch" tabs, hidden until the script runs (then stacked blocks)' );
ok( false !== strpos( $h, 'id="pg-demo-panel-steps"' ) && false !== strpos( $h, 'id="pg-demo-panel-video"' ) && strpos( $h, 'pg-demo-panel-steps"' ) < strpos( $h, 'pg-demo-panel-video"' ), 'the tabs control the two panels, steps first' );
// escaping
$evil = check( array( step( 1, array(
	'caption' => '<script>alert(1)</script> & "quoted"',
	'alt'     => '"><img src=x onerror=alert(2)>',
) ) ), array( 'title' => '<b>bold</b>', 'source' => "</p><script>alert(3)</script>" ) )['demo'] + array( 'base' => $base );
$h = prospergenics_2026_tool_demo_html( $evil, '<i>Tool</i> "x"' );
ok( false === strpos( $h, '<script' ) && false === strpos( $h, '<img src=x' ) && false === strpos( $h, '<b>' ) && false === strpos( $h, '<i>' ), 'no markup from the manifest or the tool name gets through' );
ok( false !== strpos( $h, '&lt;script&gt;alert(1)&lt;/script&gt; &amp; &quot;quoted&quot;' ) && false !== strpos( $h, 'alt="&quot;&gt;&lt;img src=x onerror=alert(2)&gt;"' ) && false !== strpos( $h, 'data-tool-name="&lt;i&gt;Tool&lt;/i&gt; &quot;x&quot;"' ), 'captions, alt texts and names are escaped for text and attributes' );
$odd = $steps_only;
$odd['base'] = 'javascript:alert(1)//';
ok( false === strpos( prospergenics_2026_tool_demo_html( $odd, 'X' ), '<script' ), 'file URLs go through esc_url' );

echo "placement on the tool page (saved /tools/jengo-meet/)\n";
$page   = file_get_contents( __DIR__ . '/fixtures/jengo-listings/tool-detail.html' );
$buffer = substr( $page, 0, strpos( $page, '<script type="speculationrules">' ) );
$chrome = array( 'header' => '<header class="site-header">menu</header>', 'footer' => '<footer class="site-footer">footer</footer>', 'css' => '/* chrome */', 'script' => '<script>/* menu */</script>' );
$demo   = $steps_only;
$with   = $chrome + array( 'tool_demo' => $demo, 'tool_demo_css' => prospergenics_2026_tool_demo_css(), 'tool_demo_script' => prospergenics_2026_tool_demo_script(), 'tool_try_live' => '' );
$plain  = prospergenics_2026_listings_takeover( $buffer, '/tools/jengo-meet/', $chrome );
$out    = prospergenics_2026_listings_takeover( $buffer, '/tools/jengo-meet/', $with );
ok( 1 === substr_count( $buffer, '<div class="dir-back">' ) && 1 === substr_count( $buffer, '<h1>Jengo Meet</h1>' ), 'the fixture is the plugin\'s tool page: one "← All tools", the tool name in the banner' );
ok( 1 === substr_count( $out, '<section class="pg-demo"' ) && (bool) preg_match( '#<div class="dir-desc">.*?</div>\s*<style id="prospergenics-tool-demo-css">.*?</style>\s*<section class="pg-demo".*?</section>\s*<div class="dir-back">\s*<a href="' . preg_quote( TEST_HOME, '#' ) . '/tools/">← All tools</a>#s', $out ), 'the section and its styles go in the main column, right before "← All tools"' );
ok( strpos( $out, '<article>' ) < strpos( $out, '<section class="pg-demo"' ) && strpos( $out, '<section class="pg-demo"' ) < strpos( $out, '</article>' ) && strpos( $out, '</article>' ) < strpos( $out, '<aside class="dir-facts">' ), 'inside the article, before the "At a glance" box' );
ok( false !== strpos( $out, 'Real screenshots of Jengo Meet' ) && false !== strpos( $out, 'data-tool-name="Jengo Meet"' ), 'the label names the tool as its banner does' );
ok( 1 === substr_count( $out, '<script id="prospergenics-tool-demo-js">' ) && strpos( $out, '<footer class="site-footer">' ) < strpos( $out, 'prospergenics-tool-demo-js' ), 'the script comes once, after the site footer' );
ok( $plain === preg_replace( '#<style id="prospergenics-tool-demo-css">.*?</section>\n\n      |<script id="prospergenics-tool-demo-js">.*?</script>\n\n?#s', '', $out ), 'apart from the section, its styles and script, the page is the same as without a demo' );
ok( $plain === prospergenics_2026_listings_takeover( $buffer, '/tools/jengo-meet/', $chrome + array( 'tool_demo' => null, 'tool_demo_css' => prospergenics_2026_tool_demo_css(), 'tool_demo_script' => '', 'tool_try_live' => '' ) ), 'no demo (no folder or nothing valid): the page is exactly as before, no styles, no empty box' );
ok( $plain === prospergenics_2026_listings_takeover( $buffer, '/tools/jengo-meet/', $chrome + array( 'tool_demo' => array( 'tool' => 'jengo' ) + $demo ) ), 'a demo of another tool is never shown' );
foreach ( array( '/tools/' => 'the tools list', '/services/coachos/' => 'a service page', '/members/lessy/' => 'a member page' ) as $p => $what ) {
	ok( prospergenics_2026_listings_takeover( $buffer, $p, $chrome ) === prospergenics_2026_listings_takeover( $buffer, $p, $with ), "$what: no change" );
}
$svc_page = file_get_contents( __DIR__ . '/fixtures/jengo-listings/service-detail.html' );
$svc_buf  = substr( $svc_page, 0, strpos( $svc_page, '<script type="speculationrules">' ) );
ok( false === strpos( prospergenics_2026_listings_takeover( $svc_buf, '/tools/jengo-meet/', $with ), 'pg-demo' ), 'a page without "← All tools" (a service page) gets nothing, whatever the path' );
$twice = str_replace( '</article>', '<div class="dir-back"><a href="' . TEST_HOME . '/tools/">← All tools</a></div></article>', $buffer );
ok( prospergenics_2026_listings_takeover( $twice, '/tools/jengo-meet/', $chrome ) === prospergenics_2026_listings_takeover( $twice, '/tools/jengo-meet/', $with ), 'the link twice (an unexpected page): no change' );
ok( array( 'x', '' ) === prospergenics_2026_listings_add_tool_demo( 'x', '/tools/jengo-meet/', $with ), 'no link: no change, no script' );
ok( false === strpos( prospergenics_2026_listings_takeover( $buffer, '/tools/jengo-meet/', $with ), 'prospergenics-learn' ), 'a tool page gets no lesson styles' );

echo "CoachOS: Try it live\n";
$attrs = prospergenics_2026_coach_embed_attrs( array( 'data-trigger' => PROSPERGENICS_2026_TRY_LIVE_TRIGGER ) );
ok( 'https://coaching.martiendejong.nl/coaching/prospergenics/widget?taal=en' === prospergenics_2026_coach_widget_url( $attrs ), 'the chat page is the one embed.js loads: {origin}/coaching/{tenant}/widget?taal={lang}' );
ok( '' === prospergenics_2026_coach_widget_url( array( 'src' => 'http://coaching.example/embed.js' ) ) && '' === prospergenics_2026_coach_widget_url( array( 'src' => 'javascript:alert(1)//embed.js' ) ), 'only an https embed gives a chat link' );
ok( false !== strpos( prospergenics_2026_coach_embed(), 'data-trigger=".launcher-orb"' ), 'the homepage embed still opens from the orb' );
$try = prospergenics_2026_tool_demo_chrome( '/tools/coachos/' );
ok( null === $try['tool_demo'] && '' === $try['tool_demo_script'] && '' !== $try['tool_demo_css'], '/tools/coachos/ has no demo folder yet: styles for the button, no demo script' );
ok( false !== strpos( $try['tool_try_live'], '<a class="pg-try-btn pg-coach-try" href="https://coaching.martiendejong.nl/coaching/prospergenics/widget?taal=en">Try it live</a>' ) && false !== strpos( $try['tool_try_live'], 'This site&#039;s chat runs on CoachOS.' ), 'a "Try it live" button that links to the real chat (full page while embed.js is not there)' );
ok( false !== strpos( $try['tool_try_live'], '<script src="https://coaching.martiendejong.nl/coaching/embed.js?v=2" data-tenant="prospergenics"' ) && false !== strpos( $try['tool_try_live'], 'data-trigger=".pg-coach-try"' ) && false === strpos( $try['tool_try_live'], 'launcher-orb' ), 'the real CoachOS embed, opened by that button' );
ok( 1 === substr_count( $try['tool_try_live'], 'co-pagina-context' ), 'with the page context resend, as on the homepage' );
ok( array() === prospergenics_2026_tool_demo_chrome( '/tools/jengo-meet/' ) && '' === prospergenics_2026_tool_try_live_html( 'jengo-meet', $attrs, 'x' ), 'other tools get no Try it live' );
$cout = prospergenics_2026_listings_takeover( $buffer, '/tools/coachos/', $chrome + $try );
ok( 1 === substr_count( $cout, 'class="pg-try-btn pg-coach-try"' ) && (bool) preg_match( '#<div class="pg-try-live">.*?</script>\s*<div class="dir-back">#s', $cout ) && false === strpos( $cout, '<section class="pg-demo"' ) && false === strpos( $cout, 'prospergenics-tool-demo-js' ), 'on the CoachOS page it goes before "← All tools", without a demo section or its script' );

echo "the Jengo demo folder\n";
foreach ( array( 'design' => $design, 'built theme' => $theme ) as $where => $tpl ) {
	$GLOBALS['__tpl'] = $tpl;
	$jdir = $tpl . '/assets/tool-demos/jengo';
	$jr   = prospergenics_2026_tool_demo_validate( json_decode( file_get_contents( "$jdir/demo.json" ), true ), 'jengo', prospergenics_2026_tool_demo_scan( $jdir ) );
	ok( $jr['demo'] && array() === $jr['errors'] && 4 === count( $jr['demo']['steps'] ), "$where: jengo/demo.json passes the validator with nothing left out (4 steps)" );
	ok( prospergenics_2026_tool_demo_load( 'jengo' ) && 'Real screenshots of Jengo' === $jr['demo']['source'], "$where: it loads, source \"Real screenshots of Jengo\"" );
}
$GLOBALS['__tpl'] = $theme;
ok( md5_file( $design . '/assets/tool-demos/jengo/step-1.webp' ) === md5_file( $design . '/assets/jengo-task-outcomes.webp' ), 'step-1.webp is the dashboard screenshot, unedited' );
$jengo = json_decode( file_get_contents( $design . '/assets/tool-demos/jengo/demo.json' ), true );
ok( array( 'look', 'click', 'click', 'click' ) === array_column( $jengo['steps'], 'action' ), 'the overview is a look step; the others point at the 30d button, the 30 August bar and its date button' );
$demo_files = glob( $design . '/assets/tool-demos/*/*' );
$hashes     = array_map( 'md5_file', $demo_files );
ok( ! in_array( md5_file( $design . '/assets/jengo-werkplek-duo.webp' ), $hashes, true ) && ! in_array( md5_file( $design . '/assets/jengo-telefoon-chat.webp' ), $hashes, true ), 'the device mock-ups (Claude Code on a tablet, a phone chat render) are not used as screenshots' );
ok( ! in_array( md5_file( $design . '/assets/seomagic.webp' ), $hashes, true ) && ! is_dir( $design . '/assets/tool-demos/seo-god' ), 'seomagic.webp is not used for SEO God (not confirmed as that tool)' );
foreach ( glob( $design . '/assets/tool-demos/*', GLOB_ONLYDIR ) as $folder ) {
	$slug = basename( $folder );
	$fr   = prospergenics_2026_tool_demo_validate( json_decode( (string) @file_get_contents( "$folder/demo.json" ), true ), $slug, prospergenics_2026_tool_demo_scan( $folder ) );
	ok( $fr['demo'] && array() === $fr['errors'], "every demo folder is valid: $slug" . ( $fr['errors'] ? ' (' . implode( '; ', $fr['errors'] ) . ')' : '' ) );
}
ok( is_file( $theme . '/assets/tool-demos/jengo/step-1.webp' ) && md5_file( $theme . '/assets/tool-demos/jengo/demo.json' ) === md5_file( $design . '/assets/tool-demos/jengo/demo.json' ), 'the build copies the folder into the theme' );

echo "script and styles\n";
$js  = file_get_contents( $theme . '/inc/site-chrome/tool-demo.js' );
$css = prospergenics_2026_tool_demo_css();
ok( false === strpos( $js, 'innerHTML' ) && false === strpos( $js, 'insertAdjacentHTML' ) && false === strpos( $js, '</script' ), 'the script builds markup with createElement/textContent only, and has no closing script tag' );
ok( false === stripos( $js, 'autoplay' ) && false === strpos( $js, '.play()' ) && false !== strpos( $js, 'EVERY = 3500' ) && false !== strpos( $js, "play.textContent = 'Stop'" ), 'nothing plays by itself; "Play" steps every 3.5 s' );
ok( 4 <= substr_count( $js, 'stopPlay();' ) && false !== strpos( $js, "frame.addEventListener('pointerdown', stopPlay)" ), 'any click, key or tab change stops Play' );
ok( false !== strpos( $js, "e.key === 'ArrowLeft'" ) && false !== strpos( $js, "e.key === 'ArrowRight'" ) && false !== strpos( $js, "frame.setAttribute('tabindex', '0')" ), '←/→ step through while the frame has focus' );
ok( false !== strpos( $js, "live.setAttribute('aria-live', 'polite')" ) && false !== strpos( $js, "'Step ' + (i + 1) + ' of ' + n" ), 'the caption is in a polite live region with "Step n of N"' );
ok( false !== strpos( $js, "prefers-reduced-motion: reduce" ) && false !== strpos( $js, "if (action === 'click' && !reduced())" ) && false !== strpos( $css, '@media (prefers-reduced-motion: reduce)' ), 'reduced motion: the cursor jumps and there is no ripple' );
ok( false !== strpos( $js, "t.setAttribute('aria-selected'" ) && false !== strpos( $js, 't.tabIndex = k === i ? 0 : -1' ) && false !== strpos( $js, "p.setAttribute('role', 'tabpanel')" ) && false !== strpos( $js, 'v.pause()' ), 'tabs: aria-selected, roving tabindex, tab panels; leaving Watch pauses the video' );
ok( false !== strpos( $css, 'aspect-ratio: var(--pg-demo-ratio' ) && false !== strpos( $css, 'outline: 3px solid var(--focus-ring, #1a7a46)' ), 'a fixed-ratio frame; focus uses --focus-ring' );
ok( false === strpos( $css, 'linear-gradient' ) && false !== strpos( $css, '.pg-demo-btn--primary, .pg-try-btn { background: var(--green-700, #1a7a46);' ), 'buttons on solid green (white on #1a7a46 is 5.3:1), no gradient to guess contrast on' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

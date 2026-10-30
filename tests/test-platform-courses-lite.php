<?php
/**
 * Standalone tests for lessons on slow and expensive connections (card #6469):
 *   - video sources: YouTube, Vimeo, and video files on the site's own host only,
 *   - the click-to-load facade: no iframe, no player script, no autoplay; a play link that
 *     works without JavaScript; a local poster or the pattern,
 *   - the lesson text filter: embedded players become facades, <video> preloads nothing,
 *     images are lazy/async and sized,
 *   - the notes PDF: HTML to blocks (headings, nested lists, links, images, quotes, tables),
 *     multi-page output with a valid xref table and page numbers (pdfinfo/pdftotext when
 *     installed),
 *   - wiring: the notes URL, the wizard script only on the wizard, block CSS dropped on lessons
 *     without blocks, text before the video in the lesson template.
 *
 * Run with: php tests/test-platform-courses-lite.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

define( 'ABSPATH', sys_get_temp_dir() . '/pg-courses-lite-test/' );
define( 'KB_IN_BYTES', 1024 );
define( 'DAY_IN_SECONDS', 86400 );
$GLOBALS['hooks'] = array();
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function apply_filters( $hook, $value ) { return $value; }
function home_url( $p = '/' ) { return 'https://learn.example.test' . $p; }
require $theme . '/inc/courses/helpers.php';
require $theme . '/inc/courses/pdf.php';
require $theme . '/inc/courses/lite.php';
require $theme . '/inc/courses/notes.php';

echo "Video sources\n";
$host = 'learn.example.test';
ok( 'file' === prospergenics_courses_parse_file_video_url( 'https://learn.example.test/wp-content/uploads/2026/10/intro.MP4', $host )['provider'], 'an MP4 on the site itself is accepted' );
ok( 'video/webm' === prospergenics_courses_parse_file_video_url( 'http://learn.example.test/a/b.webm?ver=2', $host )['type'], 'WebM with a query string, typed' );
foreach ( array( 'https://cdn.other.test/intro.mp4', 'https://learn.example.test/intro.mov', 'https://user:pw@learn.example.test/a.mp4', 'javascript:alert(1)//learn.example.test/a.mp4', 'https://learn.example.test/a".mp4', 'https://learn.example.test.evil.test/a.mp4' ) as $bad ) {
	ok( null === prospergenics_courses_parse_file_video_url( $bad, $host ), "refused: $bad" );
}
ok( null === prospergenics_courses_parse_file_video_url( 'https://learn.example.test/a.mp4', '' ), 'no site host, no file videos' );
ok( 'https://learn.example.test/v.mp4' === prospergenics_courses_sanitize_video_url( 'https://learn.example.test/v.mp4' ) && '' === prospergenics_courses_sanitize_video_url( 'https://example.com/a.mp4' ), 'the sanitiser keeps own-host files and drops other hosts' );
ok( 'youtube' === prospergenics_courses_video_source( 'https://youtu.be/dQw4w9WgXcQ' )['provider'], 'YouTube and Vimeo still work' );

echo "Click-to-load facade\n";
$yt   = prospergenics_courses_video_facade_html( array( 'provider' => 'youtube', 'id' => 'dQw4w9WgXcQ' ), 'Lesson "one" <b>', array( 'https://learn.example.test/p.jpg', 320, 180 ) );
ok( false === stripos( $yt, '<iframe' ) && false === stripos( $yt, '<script' ) && false === stripos( $yt, 'autoplay' ), 'no iframe, no script, no autoplay' );
ok( false !== strpos( $yt, 'data-pgc-src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"' ) && false !== strpos( $yt, 'data-pgc-kind="iframe"' ), 'the privacy-friendly player URL waits in a data attribute' );
ok( false !== strpos( $yt, 'href="https://www.youtube.com/watch?v=dQw4w9WgXcQ" data-pgc-play' ), 'without JavaScript the play link opens the video page' );
ok( false !== strpos( $yt, 'Lesson &quot;one&quot; &lt;b&gt;' ) && false === strpos( $yt, '<b>' ), 'the title is escaped' );
ok( false !== strpos( $yt, '<img class="pgc-facade-poster" src="https://learn.example.test/p.jpg" width="320" height="180" alt="" loading="lazy" decoding="async"' ), 'local poster, sized, lazy' );
ok( false !== strpos( $yt, 'uses data' ) && false !== strpos( $yt, '<figcaption' ), 'a note says nothing loads until play and that playing uses data' );
$vm = prospergenics_courses_video_facade_html( array( 'provider' => 'vimeo', 'id' => '76979871' ), 't' );
ok( false !== strpos( $vm, 'player.vimeo.com/video/76979871?dnt=1' ) && false !== strpos( $vm, 'pg-pattern' ) && false === strpos( $vm, '<img' ), 'Vimeo with dnt=1; no poster yet = the pattern (no bytes)' );
$fv = prospergenics_courses_video_facade_html( array( 'provider' => 'file', 'id' => 'https://learn.example.test/v.mp4', 'type' => 'video/mp4' ), 't', null, '12 MB' );
ok( false !== strpos( $fv, 'data-pgc-kind="video"' ) && false !== strpos( $fv, '(12 MB)' ) && false === stripos( $fv, '<video' ), 'a file becomes a facade too, with its size; no <video> element until play' );
$js = file_get_contents( $theme . '/assets/platform/courses.js' );
ok( false !== strpos( $js, "'[data-pgc-play]'" ) && false !== strpos( $js, 'e.preventDefault()' ) && ! preg_match( '/autoplay=1|\.autoplay\s*=|setAttribute\(.autoplay/', $js ) && false !== strpos( $js, "el.preload = 'metadata'" ), 'courses.js loads the player only on tap and never autoplays' );

echo "Lesson text\n";
$in  = '<p>A</p><figure class="wp-block-embed"><div class="wp-block-embed__wrapper"><iframe title="x" width="560" height="315" src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed" allowfullscreen></iframe></div></figure>'
	. '<iframe src="https://www.google.com/maps/embed?pb=1"></iframe>'
	. '<video src="https://learn.example.test/v.mp4" preload="auto" autoplay controls></video>'
	. '<img src="https://learn.example.test/wp-content/uploads/a.jpg" alt="A"><img loading="eager" src="https://other.test/b.png" alt="">';
$out = prospergenics_courses_lite_html( $in, 'Title', function ( $src ) { return false !== strpos( $src, 'uploads/a.jpg' ) ? array( 800, 600 ) : null; } );
ok( 1 === substr_count( $out, 'pgc-facade-box' ) && false === strpos( $out, 'youtube.com/embed' ), 'the YouTube iframe became a facade' );
ok( false !== strpos( $out, 'google.com/maps/embed' ), 'other iframes are left alone' );
ok( false !== strpos( $out, '<video preload="none" src="https://learn.example.test/v.mp4" controls>' ), '<video>: preload="none", autoplay removed' );
ok( false !== strpos( $out, '<img loading="lazy" decoding="async" width="800" height="600" src="https://learn.example.test/wp-content/uploads/a.jpg"' ), 'images: lazy, async, sized when known' );
ok( false !== strpos( $out, '<img decoding="async" loading="eager"' ), 'an explicit loading attribute is kept' );

echo "Notes: HTML to blocks\n";
$blocks = prospergenics_courses_notes_blocks( '<p>Intro with <a href="https://x.test/a">a link</a> and <strong>bold</strong>.</p><h2>Part one</h2><h4>Detail</h4>'
	. '<ul><li>One<ul><li>Nested</li></ul></li><li>Two</li></ul><ol><li>First</li><li>Second</li></ol><blockquote><p>Quote</p></blockquote>'
	. '<p><img src="i.png" alt="Diagram"></p><table><tr><th>A</th><td>B</td></tr></table><script>alert(1)</script>Loose text <em>here</em>'
	. '<iframe src="https://www.youtube.com/embed/dQw4w9WgXcQ?feature=oembed"></iframe>' );
$types  = array_map( function ( $b ) { return $b['type'] . ':' . $b['text']; }, $blocks );
ok( 'p:Intro with a link (https://x.test/a) and bold.' === $types[0], 'paragraph text keeps link addresses' );
ok( in_array( 'h2:Part one', $types, true ) && in_array( 'h3:Detail', $types, true ), 'headings (h3-h6 become one level)' );
ok( in_array( 'li:One', $types, true ) && in_array( 'li:Nested', $types, true ) && 1 === $blocks[ array_search( 'li:Nested', $types, true ) ]['depth'], 'nested list items are indented' );
ok( '2.' === $blocks[ array_search( 'li:Second', $types, true ) ]['marker'] && "\u{2022}" === $blocks[ array_search( 'li:Two', $types, true ) ]['marker'], 'numbered and bulleted lists' );
ok( in_array( 'quote:Quote', $types, true ) && in_array( 'p:[Image: Diagram]', $types, true ) && in_array( 'p:A | B', $types, true ), 'quotes, image alt text, table rows' );
ok( false === strpos( implode( ' ', $types ), 'alert' ), 'scripts are dropped' );
ok( false !== strpos( implode( ' ', $types ), '[Video: https://www.youtube.com/watch?v=dQw4w9WgXcQ]' ), 'an embedded player becomes a link to the video page' );
ok( array() === prospergenics_courses_notes_blocks( '  ' ), 'no text, no blocks' );

echo "Notes: the PDF\n";
$meta  = array( 'course' => 'Soil basics', 'lesson' => 'Composting at home', 'module' => 'Module 1', 'position' => 'Lesson 2 of 5', 'url' => 'https://learn.example.test/courses/soil/lessons/compost/', 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'date' => '26 October 2026' );
$short = prospergenics_courses_notes_pdf( $meta, $blocks );
ok( 0 === strpos( $short, "%PDF-1.4\n" ) && false !== strpos( $short, '/Count 1 >>' ) && false !== strpos( $short, '/MediaBox [0 0 595 842]' ), 'one A4 portrait page for a short lesson' );
ok( strlen( $short ) < 8000, 'a page of notes is small (' . strlen( $short ) . ' bytes)' );
$long = array();
for ( $i = 0; $i < 60; $i++ ) {
	$long[] = array( 'type' => 'p', 'text' => str_repeat( "Paragraph $i of the lesson notes. ", 12 ), 'depth' => 0, 'marker' => '' );
}
$pdf = prospergenics_courses_notes_pdf( $meta, $long );
preg_match( '#/Count (\d+) >>#', $pdf, $c );
$pages = isset( $c[1] ) ? (int) $c[1] : 0;
ok( $pages >= 3 && $pages === preg_match_all( '#/Type /Page /Parent#', $pdf ), "a long lesson runs over $pages pages, each a page object" );
preg_match( '/startxref\n(\d+)\n%%EOF\n$/', $pdf, $m );
$xref = isset( $m[1] ) ? (int) $m[1] : -1;
preg_match( '/^xref\n0 (\d+)\n/', substr( $pdf, $xref ), $n );
$count = isset( $n[1] ) ? (int) $n[1] : 0;
$good  = $count > 5;
foreach ( array_slice( explode( "\n", substr( $pdf, $xref ) ), 3, $count - 1 ) as $i => $e ) {
	$good = $good && 0 === strpos( substr( $pdf, (int) substr( $e, 0, 10 ) ), ( $i + 1 ) . ' 0 obj' );
}
ok( $xref > 0 && $good, "all $count xref entries of the multi-page file point at their objects" );
ok( $pages === substr_count( $pdf, '/URI (https://learn.example.test/courses/soil/lessons/compost/)' ), 'every page footer links back to the lesson' );
$tmp = tempnam( sys_get_temp_dir(), 'pgnotes' );
file_put_contents( $tmp, $pdf );
if ( trim( (string) shell_exec( 'command -v pdfinfo' ) ) ) {
	$info = (string) shell_exec( 'pdfinfo ' . escapeshellarg( $tmp ) . ' 2>&1' );
	ok( 1 === preg_match( '/^Pages:\s+' . $pages . '$/m', $info ) && false === stripos( $info, 'error' ), 'pdfinfo opens it without errors' );
	$text = (string) shell_exec( 'pdftotext ' . escapeshellarg( $tmp ) . ' - 2>&1' );
	ok( false !== strpos( $text, 'Composting at home' ) && false !== strpos( $text, 'Paragraph 59 of the lesson notes.' ) && false !== strpos( $text, "Page 2 of $pages" ) && false !== strpos( $text, 'Video for this lesson: https://www.youtube.com/watch?v=dQw4w9WgXcQ' ), 'pdftotext reads the title, the last paragraph, "Page 2 of N" and the video link' );
} else {
	echo "  SKIP  poppler (pdfinfo/pdftotext) not installed\n";
}
unlink( $tmp );

echo "Wiring\n";
$routing = file_get_contents( $theme . '/inc/courses/routing.php' );
ok( false !== strpos( $routing, "'courses/([^/]+)/lessons/([^/]+)/notes/?\$'" ) && false !== strpos( $routing, "PROSPERGENICS_COURSES_REWRITE_VERSION = '2'" ), 'the notes URL has a rewrite rule (rules flushed once via the version bump)' );
ok( false !== strpos( $routing, 'prospergenics_courses_serve_notes( $lesson )' ) && false !== strpos( file_get_contents( $theme . '/inc/courses/notes.php' ), 'prospergenics_courses_can_view_lesson( $lesson )' ), 'notes are served with the lesson\'s own access rule' );
ok( false !== strpos( $routing, "'/assets/platform/courses-wizard.js'" ) && preg_match( "/'wizard' === prospergenics_courses_view\\(\\)[^\\n]*\\n\\s*\\\$wjs/", $routing ), 'the wizard script loads on the wizard only' );
ok( file_exists( $theme . '/assets/platform/courses-wizard.js' ) && false === strpos( $js, 'pgCoursesWizard' ) && strlen( $js ) < 4000, 'courses.js (loaded on lessons) has no wizard code and stays small (' . strlen( $js ) . ' bytes)' );
$lite = file_get_contents( $theme . '/inc/courses/lite.php' );
ok( false !== strpos( $lite, "has_blocks( get_queried_object() )" ) && false !== strpos( $lite, "'wp-block-library'" ) && false !== strpos( $lite, "'global-styles'" ), 'lessons without blocks drop the block library CSS and global styles' );
$tpl = file_get_contents( $theme . '/single-pg_lesson.php' );
ok( strpos( $tpl, 'pgc-lesson-content' ) < strpos( $tpl, 'prospergenics_courses_video_facade(' ), 'the lesson text comes before the video' );
ok( false !== strpos( $tpl, 'prospergenics_courses_notes_url($pgc_lesson)' ) && false !== strpos( $tpl, 'Download notes (PDF)' ), 'every lesson links its notes PDF' );
ok( false === strpos( $tpl, 'wp_embed' ) && false === strpos( $tpl, 'prospergenics_courses_video_iframe(' ), 'the template no longer embeds the player directly' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All lite lesson tests passed\n" );
exit( $failures ? 1 : 0 );

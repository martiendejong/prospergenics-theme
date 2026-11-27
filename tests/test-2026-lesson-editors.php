<?php
/**
 * Standalone test for the Try-it editors whose example holds a <textarea> of its own (card #7146):
 * inc/lesson-editors.php, on the saved live HTML lessons 36 and 45, and the Python note in
 * inc/site-chrome/playground.js. Card #6469 (slow connections): the stranded playground code dropped
 * before the page is sent, and the playground's own rules for what may load by itself (run in node
 * when it is installed). The takeover wiring is in test-2026-navigation.php.
 *
 * Run with: php tests/test-2026-lesson-editors.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

define( 'ABSPATH', $theme . '/' );
require $theme . '/inc/lesson-editors.php';

/** The editors of a page: [textarea text as the browser shows it, markup after it up to the next editor]. */
function editors( $html ) {
	$out = array();
	foreach ( array_slice( explode( '<div class="editor-container"', $html ), 1 ) as $box ) {
		if ( preg_match( '#<textarea\b[^>]*>(.*?)</textarea>#s', $box, $m ) ) {
			$out[] = array( html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ), substr( $box, strpos( $box, $m[0] ) + strlen( $m[0] ) ) );
		}
	}
	return $out;
}
/** Visible elements between the end of an editor's text box and its preview panel / the session's end. */
function live_elements( $after ) {
	$cut = strpos( $after, '/* Lesson playground' );
	return preg_match_all( '#<(button|select|input|label|h2|footer|form)\b#', false === $cut ? $after : substr( $after, 0, $cut ), $m ) ? $m[1] : array();
}

echo "Tags and their balance\n";
$tags = prospergenics_2026_lesson_tags( '<div a="x>y"><!-- <p> --><style>p > a { }</style><br /></div></section>' );
ok( array( 'div', 'style', 'style', 'br', 'div', 'section' ) === array_column( $tags, 'name' ), 'start and end tags in order; comments and the inside of <style> skipped, a quoted > stays in its attribute' );
list( $stray, $open ) = prospergenics_2026_lesson_walk( $tags );
ok( array( 5 ) === $stray && array() === $open, 'an end tag that closes nothing of its own is found (</section>); <br /> opens nothing' );
list( $stray, $open ) = prospergenics_2026_lesson_walk( prospergenics_2026_lesson_tags( '</div><ul><li>a<li>b</ul></p>' ), array( 'body', 'div' ) );
ok( array( 5 ) === $stray && array( 'body' ) === $open, 'from an outer stack: </div> closes the outer div, </ul> also closes the open <li>s, a stray </p> is found' );

echo "HTML lesson 36 \"Select, Textarea, and Buttons\" (the editor's end follows the spill)\n";
$l36 = file_get_contents( __DIR__ . '/fixtures/jengo-listings/html-learn-lesson36.html' );
$before = editors( $l36 );
$e      = $before[0]; // the lesson's editor (the second box is the sample in the stranded playground text)
ok( '' !== $e[0] && "\n" !== substr( $e[0], -1 ) && false !== strpos( substr( $e[0], -160 ), '<textarea name="message"' ), 'saved live: the editor stops right after the example\'s own <textarea name="message" …>' );
ok( array( 'label', 'button', 'button', 'button', 'button' ) === array_values( array_diff( live_elements( substr( $e[1], 0, strpos( $e[1], '<div class="preview-panel">' ) ) ), array( 'h2' ) ) ), 'saved live: the rest of the example (a label, a text box, Submit / Save / Delete / Reset) is live in the lesson' );
$m36   = prospergenics_2026_lesson_mend_editors( $l36 );
$after = editors( $m36 );
$e     = $after[0];
ok( count( $before ) === count( $after ), 'the same number of editors' );
ok( false !== strpos( $e[0], '<textarea name="message" rows="5" placeholder="Type your message here... Andika ujumbe wako hapa..."></textarea>' . "\n</div>\n<div class=\"group\">\n<label>Short feedback (3 rows):</label>\n<textarea name=\"feedback\" rows=\"3\"></textarea>" ), 'the editor holds the example\'s text boxes, closed again' );
ok( false !== strpos( $e[0], '<button class="btn btn-outline" type="reset">Reset</button>' ) && "\n" === substr( $e[0], -1 ) && '<style>' === substr( ltrim( $e[0] ), 0, 7 ), 'and the whole example, from <style> to the last button' );
ok( (bool) preg_match( '#^</div>\s*<div class="preview-panel">#', $e[1] ), 'the editor panel closes right after it and the Result panel follows, inside the same box' );
ok( array() === live_elements( substr( $e[1], 0, strpos( $e[1], '</section>' ) ) ), 'no live buttons, fields or headings left between the editor and the end of its section' );
ok( 0 === substr_count( $m36, '<button class="btn btn-primary"' ) && 1 === substr_count( $l36, '<button class="btn btn-primary"' ), 'the live "Submit" button is gone from the page' );
ok( 2 === count( $after ) && $before[1] === $after[1], 'the sample box in the stranded playground text (removed by learn.js) is untouched' );
ok( $m36 === prospergenics_2026_lesson_mend_editors( $m36 ), 'mending twice changes nothing' );
$after36 = $e;

echo "HTML lesson 45 \"Build a Complete Website\" (the editor's end was lost)\n";
$l45    = file_get_contents( __DIR__ . '/fixtures/jengo-listings/html-learn-lesson45.html' );
$before = editors( $l45 );
$b      = $before[0];
ok( in_array( 'footer', live_elements( $b[1] ), true ) && in_array( 'button', live_elements( $b[1] ), true ) && false === strpos( substr( $b[1], 0, strpos( $b[1], '/* Lesson playground' ) ), 'preview-panel' ), 'saved live: "Submit Booking" and the site footer are live in the lesson, and the editor has no Result panel' );
$m45   = prospergenics_2026_lesson_mend_editors( $l45 );
$after = editors( $m45 );
$e     = $after[0];
ok( false !== strpos( $e[0], '<textarea id="requests" name="requests" rows="3" placeholder="Dietary needs, accessibility requirements, celebrations... / Mahitaji ya chakula, ufikivu, sherehe..."></textarea>' ), 'the booking form\'s text box is closed inside the example' );
ok( false !== strpos( $e[0], '<button class="btn-submit" type="submit">Submit Booking</button>' . "\n</section>\n<!-- ===== FOOTER ===== -->\n<footer class=\"site-footer\">" ) && false !== strpos( $e[0], "Back to top</a></p>\n</footer>\n</body>\n</html>" ), 'the rest of the page is in the editor, down to its footer, and its </body></html> are put back' );
ok( false === strpos( $e[0], '</button>' . "\n</div></div></section>" ), 'the </div></div> the plugin added to balance the tags are not in the example (they close nothing there)' );
ok( '<!DOCTYPE html>' === substr( ltrim( $e[0] ), 0, 15 ), 'the example still starts at its <!DOCTYPE html>' );
ok( (bool) preg_match( '#^\s*</div></div></section>\s*/\* Lesson playground#', $e[1] ), 'after the editor: the editor panel, its box and the section close, as the spill closed them in the browser' );
ok( array() === live_elements( $e[1] ), 'no live form controls or footer left in the lesson' );
ok( 0 === substr_count( $m45, '<button class="btn-submit"' ) && 0 === substr_count( $m45, '<footer class="site-footer">' ), 'the live "Submit Booking" button and the example\'s footer are gone from the page' );
ok( $m45 === prospergenics_2026_lesson_mend_editors( $m45 ), 'mending twice changes nothing' );

echo "Everything else stays as it was\n";
$learn = file_get_contents( __DIR__ . '/fixtures/jengo-listings/course-learn.html' );
ok( $learn === prospergenics_2026_lesson_mend_editors( $learn ), 'a lesson page without such an example (AI Software Lifecycle) is byte for byte the same' );
$plain = "<article id=\"learnBody\"><div class=\"editor-container\"><div class=\"editor-panel\"><textarea>&lt;p&gt;Hi &amp;lt;textarea&amp;gt;&lt;/p&gt;</textarea>\n</div>\n<div class=\"preview-panel\"></div></div><hr />";
ok( $plain === prospergenics_2026_lesson_mend_editors( $plain ), 'an example that only mentions &lt;textarea&gt; as text is whole' );
$outside = '<div class="editor-container"><textarea>&lt;textarea&gt;</textarea><button>x</button></div>';
ok( $outside === prospergenics_2026_lesson_mend_editors( $outside ), 'nothing outside the lesson body (no id="learnBody") is touched' );
$short = "<article id=\"learnBody\"><div class=\"editor-container\"><div class=\"editor-panel\"><textarea>&lt;textarea&gt;</textarea>\n</div>\n<div class=\"preview-panel\"></div></div>";
ok( false !== strpos( $m = prospergenics_2026_lesson_mend_editors( $short ), '<textarea>&lt;textarea&gt;</textarea>' ) && $m === $short, 'an example that ends with a bare <textarea> and nothing spilled is left alone' );
$two = "<article id=\"learnBody\">" . str_repeat( "<div class=\"editor-container\"><div class=\"editor-panel\"><textarea>&lt;textarea&gt;</textarea><b>x</b>\n</div>\n<div class=\"preview-panel\"></div></div>\n", 2 );
$m2  = prospergenics_2026_lesson_mend_editors( $two );
ok( 2 === substr_count( $m2, '<textarea>&lt;textarea&gt;&lt;/textarea&gt;&lt;b&gt;x&lt;/b&gt;' . "\n" . '</textarea></div>' ) && false === strpos( $m2, '<b>' ), 'two cut-short editors in a row are both mended' );

echo "Python examples that need real Python (Python lessons 34-36)\n";
$pjs = file_get_contents( $theme . '/inc/site-chrome/playground.js' );
ok( false !== strpos( $pjs, "/^NotImplementedError: ([\\w.]+) is not yet implemented in Skulpt/" ) && false !== strpos( $pjs, "/^ImportError: No module named ['\"]?([\\w.]+)/" ) && false !== strpos( $pjs, "/^NameError: name '(\\w+(?:Error|Exception|Warning))' is not defined/" ), 'a missing module (io, json) or a missing built-in exception (FileNotFoundError) is recognised; other errors are not' );
ok( false !== strpos( $pjs, "'This example uses a Python feature the in-browser runner doesn\\'t have ('" ) && false !== strpos( $pjs, "'). Copy it and run it on your computer.'" ), 'it becomes a short note that says what is missing and to run it on your own computer' );
ok( false !== strpos( $pjs, 'if(f){line(note[0]+f+note[1],"info")}else{line(t,"error")}' ), 'only those errors: every other error still shows as it is' );

echo "The playground code stranded as text, not sent (card #6469)\n";
$stranded = function ( $html ) { return substr_count( $html, '/* Lesson playground' ); };
$d36 = prospergenics_2026_lesson_drop_stranded( $m36 );
ok( 2 === $stranded( $m36 ) && 0 === $stranded( $d36 ), 'lesson 36: the stranded playground code (its header comment twice) is gone' );
ok( strlen( $d36 ) < strlen( $m36 ) - 11000 && 1 === count( editors( $d36 ) ) && $after36[0] === editors( $d36 )[0][0], 'about 11 KB less; the sample editor made from its strings went with it, the lesson\'s own (mended) editor is untouched' );
ok( false === strpos( $d36, 'else { scan(document); } })();' ) && (bool) preg_match( '#<span>Result</span>\s*</div>\s*</div>\s*</section>\s*<div class="learn-foot">#', $d36 ), 'down to its last line: the lesson\'s Result panel and section end, and the lesson foot follows right after' );
ok( $d36 === prospergenics_2026_lesson_drop_stranded( $d36 ), 'dropping twice changes nothing' );
$d45 = prospergenics_2026_lesson_drop_stranded( $m45 );
ok( 0 === $stranded( $d45 ) && editors( $m45 )[0][0] === editors( $d45 )[0][0] && (bool) preg_match( '#&lt;/html&gt;</textarea>\s*</div></div></section>\s*<div class="learn-foot">#', $d45 ), 'lesson 45: gone too; the mended editor and the lesson\'s end stay as they were' );
ok( $learn === prospergenics_2026_lesson_drop_stranded( $learn ), 'a lesson page without it (AI Software Lifecycle) is byte for byte the same' );
$code = "/* Lesson playground (5818) */ .editor-container { display: grid; } <div class=\"editor-container\"><textarea>x</textarea></div> (function () { if (document.readyState === 'loading') {} else { scan(document); } })();";
$page = "<article class=\"learn-body\" id=\"learnBody\"><h1>A</h1><p>Text</p>\n" . $code . "\n<hr />\n<h1>B</h1>" . $code . "\n<div class=\"learn-foot\">f</div>\n</article>";
ok( "<article class=\"learn-body\" id=\"learnBody\"><h1>A</h1><p>Text</p>\n\n<hr />\n<h1>B</h1>\n<div class=\"learn-foot\">f</div>\n</article>" === prospergenics_2026_lesson_drop_stranded( $page ), 'right in the lesson, once per lesson: each run goes, everything else stays' );
$inside = array(
	'inside an editor'             => "<article id=\"learnBody\"><div class=\"editor-container\"><textarea>" . $code . "</textarea></div></article>",
	'inside a code block'          => "<article id=\"learnBody\"><pre><code>" . $code . "</code></pre></article>",
	'with a heading in between'    => "<article id=\"learnBody\">" . str_replace( '(function', '<h2>Next</h2>(function', $code ) . "</article>",
	'with a session break between' => "<article id=\"learnBody\">" . str_replace( '(function', '<hr />(function', $code ) . "</article>",
	'with an element left open'    => "<article id=\"learnBody\">" . str_replace( '<textarea>x</textarea></div>', '<textarea>x</textarea>', $code ) . "</article>",
	'without its last line'        => "<article id=\"learnBody\">" . str_replace( 'scan(document);', 'scan(root);', $code ) . "</article>",
	'outside the lesson'           => "<article id=\"learnBody\"><p>x</p></article>\n" . $code,
	'on a page without a lesson'   => '<main>' . $code . '</main>',
);
foreach ( $inside as $label => $html ) {
	ok( $html === prospergenics_2026_lesson_drop_stranded( $html ), "kept for learn.js: $label" );
}

echo "What an example may load by itself (playground.js, card #6469)\n";
$node = trim( (string) shell_exec( 'command -v node 2>/dev/null' ) );
if ( '' === $node ) {
	echo "  SKIP  node is not installed; the source checks in test-2026-navigation.php still run\n";
} else {
	$cases = array(
		'plain'      => '<h1>Habari</h1><p style="color:red">Hi</p><img src="photo.jpg"><a href="https://en.wikipedia.org/wiki/Kenya">Kenya</a><form action="https://example.com/send"></form><p>Visit https://example.com</p>',
		'youtube'    => '<iframe width="560" src="https://www.youtube.com/embed/abc" allowfullscreen></iframe>',
		'bare'       => "<img src='//picsum.photos/200/300' alt=''>",
		'fonts'      => '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter"><style>@import "https://fonts.example.org/a.css"; body { background: url(https://img.example.net/bg.png) }</style>',
		'media'      => '<video poster="https://p.example.com/p.jpg" controls><source src="https://www.w3schools.com/html/mov_bbb.mp4" type="video/mp4"></video><audio src=https://a.example.com/x.mp3></audio>',
		'srcset'     => '<img src="a.jpg" srcset="https://one.example.com/1.jpg 1x, https://two.example.com/2.jpg 2x">',
		'refresh'    => '<meta http-equiv="refresh" content="5; url=https://away.example.com/">',
		'object'     => '<object data="https://o.example.com/file.pdf"></object><svg><image href="https://s.example.com/i.png"/></svg>',
		'script'     => '<script>// note: e.g. this\nfetch("https://api.example.com/v1/data")</script><script>var a = 1 // 2</script>',
		'repeat'     => '<img src="https://picsum.photos/1"><img src="https://picsum.photos/2"><img src="https://PICSUM.photos/3">',
	);
	$docs = array(
		'fragment'  => array( 'html', '<p>Hi</p>' ),
		'page'      => array( 'html', "<!DOCTYPE html>\n<html><head><link rel=\"stylesheet\" href=\"https://x.example.com/a.css\"></head><body>B</body></html>" ),
		'commented' => array( 'html', "<!-- my page -->\n<!doctype html><html><body>B</body></html>" ),
		'quirks'    => array( 'html', '<html><body>B</body></html>' ),
		'js'        => array( 'javascript', 'console.log(1)' ),
	);
	$script = 'global.window = {};' . file_get_contents( $theme . '/inc/site-chrome/playground.js' ) . ';var p = window.__pgPlayground, c = ' . json_encode( $cases ) . ', d = ' . json_encode( $docs ) . ', r = { hosts: {}, locked: {}, open: {} };'
		. 'Object.keys(c).forEach(function (k) { r.hosts[k] = p.externalHosts(c[k]); });'
		. 'Object.keys(d).forEach(function (k) { r.locked[k] = p.buildDoc(d[k][0], d[k][1], true); r.open[k] = p.buildDoc(d[k][0], d[k][1], false); });'
		. 'process.stdout.write(JSON.stringify(r));';
	$r = json_decode( (string) shell_exec( escapeshellarg( $node ) . ' -e ' . escapeshellarg( $script ) . ' 2>&1' ), true );
	ok( is_array( $r ), 'playground.js loads in node and answers' );
	$h = is_array( $r ) ? $r['hosts'] : array();
	ok( array() === $h['plain'], 'a plain example renders by itself: text, inline styles, a local image name, links and forms (they only go out when clicked)' );
	ok( array( 'www.youtube.com' ) === $h['youtube'] && array( 'picsum.photos' ) === $h['bare'], 'an embedded YouTube video, an image from //picsum.photos: their site is named' );
	ok( array( 'fonts.googleapis.com', 'fonts.example.org', 'img.example.net' ) === $h['fonts'], 'a stylesheet <link>, a CSS url() and an @import' );
	ok( array( 'p.example.com', 'www.w3schools.com', 'a.example.com' ) === $h['media'] && array( 'one.example.com', 'two.example.com' ) === $h['srcset'], 'video poster and source, audio (unquoted src), every srcset candidate' );
	ok( array( 'away.example.com' ) === $h['refresh'] && array( 'o.example.com', 's.example.com' ) === $h['object'], 'a refresh <meta> that leaves for another site, <object data>, SVG <image href>' );
	ok( array( 'api.example.com' ) === $h['script'], 'a URL a script fetches; "//" comments in scripts are not taken for addresses' );
	ok( array( 'picsum.photos' ) === $h['repeat'], 'each site once, whatever its case' );
	$csp = '<meta http-equiv="Content-Security-Policy" content="default-src \'none\'; style-src \'unsafe-inline\'; script-src \'unsafe-inline\' \'unsafe-eval\'; img-src data: blob:; media-src data: blob:; font-src data:">';
	$l   = is_array( $r ) ? $r['locked'] : array();
	$o   = is_array( $r ) ? $r['open'] : array();
	ok( false !== strpos( $l['fragment'], '<head><meta charset="utf-8">' . $csp . '<style>' ) && false === strpos( $o['fragment'], 'Content-Security-Policy' ), 'rendered by itself, an example gets a policy that blocks every request (data: images only); after Run or the tap it does not' );
	ok( 0 === strpos( $l['page'], "<!DOCTYPE html>" . $csp . "\n<html><head><link" ) && $o['page'] === $docs['page'][1], 'a whole page: the policy right after its <!DOCTYPE> (standards mode kept, before its first <link>); unlocked it is the example as written' );
	ok( 0 === strpos( $l['commented'], "<!-- my page -->\n<!doctype html>" . $csp ) && 0 === strpos( $l['quirks'], $csp . '<html>' ), 'after a comment and a <!doctype>; a page without one gets it first' );
	ok( $l['js'] === $o['js'] && false === strpos( $l['js'], 'Content-Security-Policy' ), 'JavaScript and Python only run on Run, so they never get it' );
}

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

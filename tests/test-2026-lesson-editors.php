<?php
/**
 * Standalone test for the Try-it editors whose example holds a <textarea> of its own (card #7146):
 * inc/lesson-editors.php, on the saved live HTML lessons 36 and 45, and the Python note in
 * inc/site-chrome/playground.js. The takeover wiring is in test-2026-navigation.php.
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

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

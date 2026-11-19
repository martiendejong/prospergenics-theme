<?php
/**
 * Try-it editors whose example holds a <textarea> of its own (card #7146).
 *
 * A lesson's editor is <textarea>EXAMPLE</textarea> inside .editor-container > .editor-panel. When
 * the example itself has a text box (HTML lesson 36 "Select, Textarea, and Buttons", lesson 45 "Build
 * a Complete Website"), its </textarea> ends the editor early: the editor shows half the example and
 * the rest lands in the lesson as live buttons, fields and a footer. On the plugin's lesson pages the
 * takeover (inc/site-chrome.php) passes the page through prospergenics_2026_lesson_mend_editors(),
 * which puts that spilled rest back into the editor as text, so the editor holds and runs the whole
 * example. Pure functions; any other editor, or a page of another shape, is left exactly as it was.
 *
 * Two shapes, both seen live (8 Nov):
 *   - the editor's own end follows the spill (</div> then <div class="preview-panel">, lesson 36): the
 *     spill is everything up to that </div>, and the page after it is already right;
 *   - the editor's end was lost on the way (lesson 45): the spill runs to the end of the session. Its
 *     end tags that close nothing of its own closed the editor and its section in the browser, so
 *     they are written again, as tags, after the mended editor; the page keeps the same outline.
 * End tags in the spill that close nothing in the example either (</div></div> left by the plugin's
 * tag balancing) are left out of the example's text.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The start and end tags of a piece of HTML, in order (pure). Comments are skipped, and so is the
 * text inside <style> and <script>.
 *
 * @return array<int,array{name:string,close:bool,at:int,len:int}>
 */
function prospergenics_2026_lesson_tags( $html ) {
	$html = (string) $html;
	$tags = array();
	$re   = '#<!--.*?(?:-->|\z)|<(/?)([a-zA-Z][a-zA-Z0-9-]*)(?:[^>"\']|"[^"]*"|\'[^\']*\')*>#s';
	$pos  = 0;
	while ( preg_match( $re, $html, $m, PREG_OFFSET_CAPTURE, $pos ) ) {
		$at  = $m[0][1];
		$pos = $at + strlen( $m[0][0] );
		if ( ! isset( $m[2] ) || '' === $m[2][0] ) {
			continue; // a comment
		}
		$name   = strtolower( $m[2][0] );
		$close  = '/' === $m[1][0];
		$tags[] = array( 'name' => $name, 'close' => $close, 'at' => $at, 'len' => strlen( $m[0][0] ) );
		if ( ! $close && ( 'style' === $name || 'script' === $name ) ) {
			$end = stripos( $html, '</' . $name, $pos );
			if ( false === $end ) {
				break;
			}
			$pos = $end;
		}
	}
	return $tags;
}

/**
 * Walk the tags from a stack of open elements (pure): an end tag closes the nearest open element of
 * its name and everything opened after it. Returns the keys of the end tags that close nothing, and
 * the elements still open at the end.
 *
 * @return array{0:int[],1:string[]}
 */
function prospergenics_2026_lesson_walk( array $tags, array $stack = array() ) {
	static $void = array( 'area' => 1, 'base' => 1, 'br' => 1, 'col' => 1, 'embed' => 1, 'hr' => 1, 'img' => 1, 'input' => 1, 'link' => 1, 'meta' => 1, 'param' => 1, 'source' => 1, 'track' => 1, 'wbr' => 1 );
	$stray = array();
	foreach ( $tags as $i => $t ) {
		if ( ! $t['close'] ) {
			if ( ! isset( $void[ $t['name'] ] ) ) {
				$stack[] = $t['name'];
			}
			continue;
		}
		$open = array_keys( $stack, $t['name'], true );
		if ( ! $open ) {
			$stray[] = $i;
			continue;
		}
		$stack = array_slice( $stack, 0, end( $open ) );
	}
	return array( $stray, $stack );
}

/** Put the spilled rest of each cut-short example back into its editor (pure). */
function prospergenics_2026_lesson_mend_editors( $html ) {
	$html   = (string) $html;
	$offset = strpos( $html, 'id="learnBody"' );
	if ( false === $offset ) {
		return $html;
	}
	$box_tag = '<div class="editor-container"';
	$ends    = array( $box_tag, '<hr', '<div class="lesson-header"', '<div class="learn-foot"', '</article>', '/* Lesson playground' );
	while ( false !== ( $box = strpos( $html, $box_tag, $offset ) ) ) {
		$offset = $box + strlen( $box_tag );
		// the editor's own text box: the first one in this box
		if ( ! preg_match( '#<textarea\b[^>]*>#', $html, $t, PREG_OFFSET_CAPTURE, $offset ) ) {
			break;
		}
		$next = strpos( $html, $box_tag, $offset );
		if ( false !== $next && $t[0][1] > $next ) {
			continue;
		}
		$from  = $t[0][1] + strlen( $t[0][0] );
		$close = stripos( $html, '</textarea>', $from );
		if ( false === $close ) {
			break;
		}
		$offset = $close;
		$raw    = substr( $html, $from, $close - $from );
		$code   = html_entity_decode( $raw, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		list( , $code_open ) = prospergenics_2026_lesson_walk( prospergenics_2026_lesson_tags( $code ) );
		if ( ! in_array( 'textarea', $code_open, true ) ) {
			continue; // the example is whole (most editors)
		}

		// The spill: up to the editor's own end, else up to where the session goes on.
		$spill_at = $close + strlen( '</textarea>' );
		$limit    = strlen( $html );
		foreach ( $ends as $end ) {
			$p = strpos( $html, $end, $spill_at );
			$limit = false !== $p && $p < $limit ? $p : $limit;
		}
		$region = substr( $html, $spill_at, $limit - $spill_at );
		$whole  = (bool) preg_match( '#</div>\s*<div class="preview-panel">#', $region, $pp, PREG_OFFSET_CAPTURE );
		$spill  = $whole ? substr( $region, 0, $pp[0][1] ) : rtrim( $region );
		if ( '' === trim( $spill ) ) {
			continue;
		}

		$tags                  = prospergenics_2026_lesson_tags( $spill );
		list( $outer )         = prospergenics_2026_lesson_walk( $tags );
		list( $junk, $still )  = prospergenics_2026_lesson_walk( $tags, $code_open );
		$text = $spill;
		foreach ( array_reverse( $junk ) as $i ) {
			$text = substr_replace( $text, '', $tags[ $i ]['at'], $tags[ $i ]['len'] );
		}
		$after = '';
		if ( ! $whole ) {
			// the example's own end went missing with the editor's: close its page
			foreach ( array( 'body', 'html' ) as $el ) {
				$text .= in_array( $el, $still, true ) ? "\n</" . $el . '>' : '';
			}
			foreach ( $outer as $i ) {
				$after .= '</' . $tags[ $i ]['name'] . '>';
			}
		}
		$mended = $raw . htmlspecialchars( '</textarea>' . $text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</textarea>' . ( '' !== $after ? "\n" . $after : '' );
		$resume = $spill_at + strlen( $spill );
		$html   = substr( $html, 0, $from ) . $mended . substr( $html, $resume );
		$offset = $from + strlen( $mended );
	}
	return $html;
}

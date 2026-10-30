<?php
/**
 * Courses track: lesson notes as a PDF (card #6469, for learners on slow or costly connections).
 *
 *   /courses/<course>/lessons/<lesson>/notes/   the lesson text as an A4 PDF to keep offline
 *
 * Same access rule as the lesson page (enrolled learners and editors; prospergenics_courses_
 * can_view_lesson()). The PDF is built with the track's own writer (pdf.php, also used for the
 * certificates): standard Helvetica, no images, so a page of notes is a few KB. The lesson's
 * video is listed as a link; quiz questions are never included (they would give the answers
 * away in a file that outlives the attempt).
 *
 * prospergenics_courses_notes_blocks() and prospergenics_courses_notes_pdf() are pure (DOM +
 * pdf.php only) and unit-tested in tests/test-platform-courses-lite.php.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lesson HTML to text blocks for the PDF, in reading order.
 *
 * @return array<int,array{type:string,text:string,depth:int,marker:string}> type: h2|h3|p|li|quote|pre
 */
function prospergenics_courses_notes_blocks( $html ) {
	$html   = (string) $html;
	$blocks = array();
	if ( '' === trim( $html ) ) {
		return $blocks;
	}
	if ( ! class_exists( 'DOMDocument' ) ) {
		// No DOM extension: paragraphs from the text alone.
		foreach ( preg_split( '/\n\s*\n|<\/p>|<br\s*\/?>/i', $html ) as $chunk ) {
			$text = prospergenics_courses_notes_clean( html_entity_decode( strip_tags( $chunk ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( '' !== $text ) {
				$blocks[] = array( 'type' => 'p', 'text' => $text, 'depth' => 0, 'marker' => '' );
			}
		}
		return $blocks;
	}
	$doc  = new DOMDocument();
	$prev = libxml_use_internal_errors( true );
	$doc->loadHTML( '<?xml encoding="UTF-8"?><div id="pgc-notes-root">' . $html . '</div>', LIBXML_NONET );
	libxml_clear_errors();
	libxml_use_internal_errors( $prev );
	$root = $doc->getElementById( 'pgc-notes-root' );
	if ( $root ) {
		prospergenics_courses_notes_walk( $root, $blocks, 0 );
	}
	return $blocks;
}

/** Collapse whitespace; trim. */
function prospergenics_courses_notes_clean( $text ) {
	return trim( preg_replace( '/\s+/u', ' ', str_replace( "\xC2\xA0", ' ', (string) $text ) ) );
}

/** Inline text of a node: links keep their (absolute) address, images their alt text. */
function prospergenics_courses_notes_inline( DOMNode $node ) {
	$out = '';
	foreach ( $node->childNodes as $child ) {
		if ( XML_TEXT_NODE === $child->nodeType || XML_CDATA_SECTION_NODE === $child->nodeType ) {
			$out .= $child->nodeValue;
			continue;
		}
		if ( XML_ELEMENT_NODE !== $child->nodeType ) {
			continue;
		}
		$tag = strtolower( $child->nodeName );
		if ( in_array( $tag, array( 'script', 'style', 'noscript', 'template', 'form', 'button', 'svg' ), true ) ) {
			continue;
		}
		if ( 'br' === $tag ) {
			$out .= ' ';
		} elseif ( 'img' === $tag ) {
			$alt  = prospergenics_courses_notes_clean( $child->getAttribute( 'alt' ) );
			$out .= '' !== $alt ? ' [Image: ' . $alt . '] ' : '';
		} elseif ( 'iframe' === $tag || 'video' === $tag ) {
			$src = $child->getAttribute( 'src' );
			if ( '' === $src && 'video' === $tag ) {
				foreach ( $child->getElementsByTagName( 'source' ) as $source ) {
					$src = $source->getAttribute( 'src' );
					break;
				}
			}
			// A player URL becomes the video's own page (youtube.com/watch?v=..., vimeo.com/...).
			$v    = function_exists( 'prospergenics_courses_video_player' ) ? prospergenics_courses_parse_video_url( $src ) : null;
			$src  = $v ? prospergenics_courses_video_player( $v )['href'] : preg_replace( '/\?.*$/', '', $src );
			$out .= preg_match( '#^https?://#i', $src ) ? ' [Video: ' . $src . '] ' : ' [Video] ';
		} elseif ( 'a' === $tag ) {
			$text = prospergenics_courses_notes_inline( $child );
			$href = trim( $child->getAttribute( 'href' ) );
			$out .= $text;
			if ( preg_match( '#^https?://#i', $href ) && false === strpos( prospergenics_courses_notes_clean( $text ), $href ) ) {
				$out .= ' (' . $href . ')';
			}
		} else {
			$out .= prospergenics_courses_notes_inline( $child );
		}
	}
	return $out;
}

/** Walk block-level elements; inline runs between them become paragraphs. */
function prospergenics_courses_notes_walk( DOMNode $node, array &$blocks, $depth ) {
	$add = function ( $type, $text, $depth = 0, $marker = '' ) use ( &$blocks ) {
		$text = prospergenics_courses_notes_clean( $text );
		if ( '' !== $text ) {
			$blocks[] = array( 'type' => $type, 'text' => $text, 'depth' => (int) $depth, 'marker' => (string) $marker );
		}
	};
	$inline_run = '';
	$flush      = function () use ( &$inline_run, $add ) {
		$add( 'p', $inline_run );
		$inline_run = '';
	};
	$block_tags = array( 'p', 'div', 'section', 'article', 'figure', 'figcaption', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li', 'blockquote', 'pre', 'table', 'tr', 'hr', 'details', 'summary', 'dl', 'dt', 'dd' );
	foreach ( $node->childNodes as $child ) {
		$tag = XML_ELEMENT_NODE === $child->nodeType ? strtolower( $child->nodeName ) : '';
		if ( '' === $tag || ! in_array( $tag, $block_tags, true ) ) {
			if ( XML_ELEMENT_NODE === $child->nodeType ) {
				$wrap = $child->ownerDocument->createElement( 'span' );
				$wrap->appendChild( $child->cloneNode( true ) );
				$inline_run .= prospergenics_courses_notes_inline( $wrap );
			} elseif ( XML_TEXT_NODE === $child->nodeType ) {
				$inline_run .= $child->nodeValue;
			}
			continue;
		}
		$flush();
		if ( preg_match( '/^h([1-6])$/', $tag, $m ) ) {
			$add( (int) $m[1] <= 2 ? 'h2' : 'h3', prospergenics_courses_notes_inline( $child ) );
		} elseif ( 'ul' === $tag || 'ol' === $tag ) {
			$n = 0;
			foreach ( $child->childNodes as $li ) {
				if ( XML_ELEMENT_NODE !== $li->nodeType || 'li' !== strtolower( $li->nodeName ) ) {
					continue;
				}
				$n++;
				// The item's own text, then any nested list one level deeper.
				$own    = '';
				$nested = array();
				foreach ( $li->childNodes as $part ) {
					$ptag = XML_ELEMENT_NODE === $part->nodeType ? strtolower( $part->nodeName ) : '';
					if ( 'ul' === $ptag || 'ol' === $ptag ) {
						$nested[] = $part;
					} elseif ( XML_TEXT_NODE === $part->nodeType ) {
						$own .= $part->nodeValue;
					} elseif ( '' !== $ptag ) {
						$wrap = $part->ownerDocument->createElement( 'span' );
						$wrap->appendChild( $part->cloneNode( true ) );
						$own .= ' ' . prospergenics_courses_notes_inline( $wrap ) . ' ';
					}
				}
				$add( 'li', $own, $depth, 'ol' === $tag ? $n . '.' : "\u{2022}" );
				foreach ( $nested as $list ) {
					$holder = $list->ownerDocument->createElement( 'div' );
					$holder->appendChild( $list->cloneNode( true ) );
					prospergenics_courses_notes_walk( $holder, $blocks, $depth + 1 );
				}
			}
		} elseif ( 'blockquote' === $tag ) {
			$add( 'quote', prospergenics_courses_notes_inline( $child ) );
		} elseif ( 'pre' === $tag ) {
			$add( 'pre', $child->textContent );
		} elseif ( 'table' === $tag ) {
			foreach ( $child->getElementsByTagName( 'tr' ) as $tr ) {
				$cells = array();
				foreach ( $tr->childNodes as $cell ) {
					if ( XML_ELEMENT_NODE === $cell->nodeType ) {
						$cells[] = prospergenics_courses_notes_clean( prospergenics_courses_notes_inline( $cell ) );
					}
				}
				$add( 'p', implode( ' | ', array_filter( $cells, 'strlen' ) ) );
			}
		} elseif ( 'hr' !== $tag ) {
			prospergenics_courses_notes_walk( $child, $blocks, $depth );
		}
	}
	$flush();
}

/**
 * The notes PDF (A4 portrait, page numbers, clickable links to the lesson and its video).
 *
 * @param array $meta course, lesson, module, position ("Lesson 2 of 4"), url, video_url, date
 * @param array $blocks from prospergenics_courses_notes_blocks()
 */
function prospergenics_courses_notes_pdf( array $meta, array $blocks ) {
	$W      = 595.0;
	$H      = 842.0;
	$left   = 56.0;
	$width  = $W - 2 * $left;
	$top    = $H - 64;
	$bottom = 70.0;
	$ink    = '#0b2016';
	$muted  = '#4b5e52';
	$green  = '#1a7a46';
	$m      = array_merge( array( 'course' => '', 'lesson' => '', 'module' => '', 'position' => '', 'url' => '', 'video_url' => '', 'date' => '' ), $meta );

	// Pass 1: lay out every line on pages (so the footer can say "page 2 of 3").
	$pages = array( array() );
	$y     = $top;
	$put   = function ( array $item ) use ( &$pages ) {
		$pages[ count( $pages ) - 1 ][] = $item;
	};
	$need  = function ( $h ) use ( &$y, &$pages, $top, $bottom ) {
		if ( $y - $h < $bottom ) {
			$pages[] = array();
			$y       = $top;
		}
	};
	$lines = function ( $text, $font, $size, $x, $max, $color, $leading, $link = '' ) use ( &$y, $put, $need ) {
		foreach ( Prospergenics_Courses_Pdf::wrap( $text, $font, $size, $max ) as $line ) {
			$need( $leading );
			$y -= $leading;
			$put( array( 'text', $line, $x, $y, $font, $size, $color, $link ) );
		}
	};

	$put( array( 'text', 'PROSPERGENICS  ·  LESSON NOTES', $left, $y, 'bold', 9, $green, '' ) );
	$y -= 8;
	if ( '' !== $m['course'] ) {
		$lines( $m['course'], 'regular', 11, $left, $width, $muted, 16 );
	}
	$y -= 4;
	$lines( '' !== $m['lesson'] ? $m['lesson'] : 'Lesson', 'bold', 20, $left, $width, $ink, 25 );
	$sub = implode( '  ·  ', array_filter( array( $m['module'], $m['position'] ), 'strlen' ) );
	if ( '' !== $sub ) {
		$y -= 2;
		$lines( $sub, 'regular', 10.5, $left, $width, $muted, 15 );
	}
	$y -= 10;
	$put( array( 'line', $left, $y, $left + $width ) );
	$y -= 6;
	if ( '' !== $m['video_url'] ) {
		$lines( 'Video for this lesson: ' . $m['video_url'], 'regular', 10.5, $left, $width, $green, 15, $m['video_url'] );
		$y -= 6;
	}

	foreach ( $blocks as $b ) {
		switch ( $b['type'] ) {
			case 'h2':
				$y -= 10;
				$need( 40 );
				$lines( $b['text'], 'bold', 14, $left, $width, $ink, 19 );
				$y -= 2;
				break;
			case 'h3':
				$y -= 8;
				$need( 36 );
				$lines( $b['text'], 'bold', 12, $left, $width, $ink, 17 );
				break;
			case 'li':
				$indent = 16 + 16 * min( 4, (int) $b['depth'] );
				$need( 16 );
				$put( array( 'text', $b['marker'], $left + $indent - 12, $y - 15.5, 'regular', 11, $green, '' ) );
				$lines( $b['text'], 'regular', 11, $left + $indent, $width - $indent, $ink, 15.5 );
				$y -= 3;
				break;
			case 'quote':
				$y -= 4;
				$start = $y;
				$lines( $b['text'], 'italic', 11, $left + 14, $width - 14, $muted, 15.5 );
				$put( array( 'bar', $left + 3, $y - 3, $start - $y ) );
				$y -= 8;
				break;
			case 'pre':
				$y -= 2;
				$lines( $b['text'], 'regular', 9.5, $left + 8, $width - 8, $ink, 13 );
				$y -= 8;
				break;
			default:
				$lines( $b['text'], 'regular', 11, $left, $width, $ink, 15.5 );
				$y -= 8;
		}
	}
	if ( ! $blocks ) {
		$lines( 'This lesson has no text yet.', 'italic', 11, $left, $width, $muted, 15.5 );
	}

	// Pass 2: draw.
	$pdf = new Prospergenics_Courses_Pdf( $W, $H );
	$pdf->set_info( array( 'Title' => trim( $m['lesson'] . ' - lesson notes' ), 'Subject' => $m['course'], 'Author' => 'ProsperGenics' ) );
	$total = count( $pages );
	foreach ( $pages as $i => $items ) {
		if ( $i > 0 ) {
			$pdf->add_page();
		}
		foreach ( $items as $it ) {
			if ( 'text' === $it[0] ) {
				$w = $pdf->text( $it[1], $it[2], $it[3], $it[4], $it[5], $it[6] );
				if ( '' !== $it[7] ) {
					$pdf->link( $it[2], $it[3] - 3, $w, $it[5] + 4, $it[7] );
				}
			} elseif ( 'line' === $it[0] ) {
				$pdf->line( $it[1], $it[2], $it[3], $it[2], '#cfe9d9', 1 );
			} elseif ( 'bar' === $it[0] ) {
				$pdf->rect( $it[1], $it[2], 2.5, $it[3], '#86e0a6' );
			}
		}
		$pdf->line( $left, 48, $left + $width, 48, '#cfe9d9', 0.8 );
		$foot = 'Page ' . ( $i + 1 ) . ' of ' . $total . ( '' !== $m['date'] ? '  ·  Saved ' . $m['date'] : '' );
		$pdf->text( $foot, $left + $width, 34, 'regular', 8.5, $muted, 'right' );
		if ( '' !== $m['url'] ) {
			$fw  = Prospergenics_Courses_Pdf::text_width( $m['url'], 'regular', 8.5 );
			$url = $fw > $width - 120 ? 'Open the lesson online' : $m['url'];
			$w   = $pdf->text( $url, $left, 34, 'regular', 8.5, $green );
			$pdf->link( $left, 31, $w, 12, $m['url'] );
		}
	}
	return $pdf->output();
}

/** URL of a lesson's notes PDF. */
function prospergenics_courses_notes_url( $lesson ) {
	return trailingslashit( get_permalink( $lesson ) ) . 'notes/';
}

/**
 * Stream the notes of the lesson being viewed (template_redirect, see routing.php). Visitors who
 * cannot open the lesson get the login page (logged out) or a 403.
 */
function prospergenics_courses_serve_notes( $lesson ) {
	if ( ! prospergenics_courses_can_view_lesson( $lesson ) ) {
		if ( ! is_user_logged_in() ) {
			wp_safe_redirect( wp_login_url( get_permalink( $lesson ) ) );
			exit;
		}
		status_header( 403 );
		nocache_headers();
		wp_die( esc_html__( 'The notes are for learners enrolled in this course.', 'prospergenics-2026' ), esc_html__( 'Lesson notes', 'prospergenics-2026' ), array( 'response' => 403, 'back_link' => true ) );
	}
	$course_id = prospergenics_courses_course_of( $lesson );
	$flat      = prospergenics_courses_flat_lessons( $course_id );
	$index     = 0;
	foreach ( $flat as $i => $l ) {
		if ( (int) $l->ID === (int) $lesson->ID ) {
			$index = $i;
			break;
		}
	}
	// The lesson text as learners see it (shortcodes and embeds expanded); embedded videos stay
	// plain iframes/links here (no facades), so they come out as "[Video: <address>]".
	$GLOBALS['prospergenics_courses_rendering_notes'] = true;
	$html = apply_filters( 'the_content', $lesson->post_content );
	unset( $GLOBALS['prospergenics_courses_rendering_notes'] );
	$pdf  = prospergenics_courses_notes_pdf(
		array(
			'course'    => get_the_title( $course_id ),
			'lesson'    => get_the_title( $lesson ),
			'module'    => get_the_title( $lesson->post_parent ),
			'position'  => sprintf( 'Lesson %d of %d', $index + 1, max( 1, count( $flat ) ) ),
			'url'       => get_permalink( $lesson ),
			'video_url' => prospergenics_courses_lesson_video_link( $lesson ),
			'date'      => wp_date( 'j F Y' ),
		),
		prospergenics_courses_notes_blocks( $html )
	);
	nocache_headers();
	header( 'Content-Type: application/pdf' );
	header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $lesson->post_name . '-notes.pdf' ) . '"' );
	header( 'Content-Length: ' . strlen( $pdf ) );
	header( 'X-Robots-Tag: noindex, nofollow' );
	echo $pdf; // phpcs:ignore WordPress.Security.EscapeOutput -- binary PDF
	exit;
}

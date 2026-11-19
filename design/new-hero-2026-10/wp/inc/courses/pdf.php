<?php
/**
 * Courses track: a deliberately small, dependency-free PDF writer for the course certificate
 * (card #6361) and the lesson notes (card #6469, notes.php). It writes PDF 1.4 files with the
 * standard Helvetica fonts (no font embedding needed, WinAnsi encoding), filled/stroked
 * rectangles, lines, text, one PNG image (with alpha as a soft mask) and link annotations, on
 * one or more pages (add_page()). That is all a certificate or a page of notes needs, and it
 * runs on any host with zlib: no Composer packages, no external service, no headless browser.
 *
 * Unit-tested in tests/test-platform-courses-pdf.php (structure, xref offsets, PNG decoding).
 */

defined( 'ABSPATH' ) || exit;

class Prospergenics_Courses_Pdf {

	/** Fonts available to text(): resource name => [BaseFont, width table key]. */
	const FONTS = array(
		'regular' => array( 'F1', 'Helvetica', 'regular' ),
		'bold'    => array( 'F2', 'Helvetica-Bold', 'bold' ),
		'italic'  => array( 'F3', 'Helvetica-Oblique', 'regular' ),
	);

	/** @var float */
	private $w;
	/** @var float */
	private $h;
	/** @var string[] content stream operators */
	private $ops = array();
	/** @var array[] link annotations */
	private $links = array();
	/** @var array|null decoded image */
	private $image = null;
	/** @var array */
	private $info = array();
	/** @var array[] finished pages: ['ops' => string[], 'links' => array[]] */
	private $pages = array();

	/** @param float $w Page width in points. @param float $h Page height in points. */
	public function __construct( $w = 842.0, $h = 595.0 ) {
		$this->w = (float) $w;
		$this->h = (float) $h;
	}

	/** Start a new page of the same size; everything drawn after this goes on it. */
	public function add_page() {
		$this->pages[] = array( 'ops' => $this->ops, 'links' => $this->links );
		$this->ops     = array();
		$this->links   = array();
	}

	/** Number of pages so far (the one being drawn included). */
	public function page_count() {
		return count( $this->pages ) + 1;
	}

	/** Document info (Title, Author, Subject, ...). */
	public function set_info( array $info ) {
		$this->info = $info;
	}

	/** Number formatting for content streams: at most 3 decimals, no exponent. */
	private static function n( $v ) {
		$s = rtrim( rtrim( number_format( (float) $v, 3, '.', '' ), '0' ), '.' );
		return '-0' === $s ? '0' : $s;
	}

	/** "r g b" from #rrggbb. */
	private static function rgb( $hex ) {
		$hex = ltrim( (string) $hex, '#' );
		if ( ! preg_match( '/^[0-9a-fA-F]{6}$/', $hex ) ) {
			$hex = '000000';
		}
		$c = array();
		for ( $i = 0; $i < 3; $i++ ) {
			$c[] = self::n( hexdec( substr( $hex, $i * 2, 2 ) ) / 255 );
		}
		return implode( ' ', $c );
	}

	/** Filled and/or stroked rectangle; (x, y) is the bottom-left corner. */
	public function rect( $x, $y, $w, $h, $fill = null, $stroke = null, $line_width = 1.0 ) {
		$op = 'q ';
		if ( $fill ) {
			$op .= self::rgb( $fill ) . ' rg ';
		}
		if ( $stroke ) {
			$op .= self::rgb( $stroke ) . ' RG ' . self::n( $line_width ) . ' w ';
		}
		$op .= self::n( $x ) . ' ' . self::n( $y ) . ' ' . self::n( $w ) . ' ' . self::n( $h ) . ' re ';
		$op .= $fill && $stroke ? 'B' : ( $fill ? 'f' : 'S' );
		$this->ops[] = $op . ' Q';
	}

	/** A straight line. */
	public function line( $x1, $y1, $x2, $y2, $color = '#000000', $line_width = 1.0 ) {
		$this->ops[] = 'q ' . self::rgb( $color ) . ' RG ' . self::n( $line_width ) . ' w ' . self::n( $x1 ) . ' ' . self::n( $y1 ) . ' m ' . self::n( $x2 ) . ' ' . self::n( $y2 ) . ' l S Q';
	}

	/** UTF-8 to WinAnsi (Windows-1252) bytes; characters outside it become '?'. */
	public static function to_winansi( $text ) {
		$text = (string) $text;
		if ( function_exists( 'mb_convert_encoding' ) ) {
			$prev = mb_substitute_character();
			mb_substitute_character( 0x3F );
			$out = mb_convert_encoding( $text, 'Windows-1252', 'UTF-8' );
			mb_substitute_character( $prev );
			return $out;
		}
		$out = @iconv( 'UTF-8', 'Windows-1252//TRANSLIT', $text );
		return false === $out ? preg_replace( '/[^\x20-\x7E]/', '?', $text ) : $out;
	}

	/** A PDF literal string body: (, ) and \ escaped, bytes outside printable ASCII as octal. */
	public static function escape( $bytes ) {
		$out = '';
		$len = strlen( $bytes );
		for ( $i = 0; $i < $len; $i++ ) {
			$c = $bytes[ $i ];
			$o = ord( $c );
			if ( '(' === $c || ')' === $c || '\\' === $c ) {
				$out .= '\\' . $c;
			} elseif ( $o < 32 || $o > 126 ) {
				$out .= sprintf( '\\%03o', $o );
			} else {
				$out .= $c;
			}
		}
		return $out;
	}

	/** Width in points of a UTF-8 string set in one of the FONTS. */
	public static function text_width( $text, $font, $size ) {
		static $tables = null;
		if ( null === $tables ) {
			// Adobe AFM advance widths for ASCII 32..126 (Helvetica-Oblique shares Helvetica's).
			$tables = array(
				'regular' => array( 278, 278, 355, 556, 556, 889, 667, 191, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 278, 278, 584, 584, 584, 556, 1015, 667, 667, 722, 722, 667, 611, 778, 722, 278, 500, 667, 556, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 278, 278, 278, 469, 556, 333, 556, 556, 500, 556, 556, 278, 556, 556, 222, 222, 500, 222, 833, 556, 556, 556, 556, 333, 500, 278, 556, 500, 722, 500, 500, 500, 334, 260, 334, 584 ),
				'bold'    => array( 278, 333, 474, 556, 556, 889, 722, 238, 333, 333, 389, 584, 278, 333, 278, 278, 556, 556, 556, 556, 556, 556, 556, 556, 556, 556, 333, 333, 584, 584, 584, 611, 975, 722, 722, 722, 722, 667, 611, 778, 722, 278, 556, 722, 611, 833, 722, 778, 667, 778, 722, 667, 611, 722, 667, 944, 667, 667, 611, 333, 278, 333, 584, 556, 333, 556, 611, 556, 611, 556, 333, 611, 611, 278, 278, 556, 278, 889, 611, 611, 611, 611, 389, 556, 333, 611, 556, 778, 556, 556, 500, 389, 280, 389, 584 ),
			);
		}
		$key   = isset( self::FONTS[ $font ] ) ? self::FONTS[ $font ][2] : 'regular';
		$table = $tables[ $key ];
		$bytes = self::to_winansi( $text );
		$units = 0;
		$len   = strlen( $bytes );
		for ( $i = 0; $i < $len; $i++ ) {
			$o = ord( $bytes[ $i ] );
			// Accented letters and other WinAnsi extras: close enough to an average lower-case letter.
			$units += ( $o >= 32 && $o <= 126 ) ? $table[ $o - 32 ] : ( 'bold' === $key ? 611 : 556 );
		}
		return $units * $size / 1000;
	}

	/**
	 * Split text into lines no wider than $max_width (words longer than a line are kept whole).
	 *
	 * @return string[]
	 */
	public static function wrap( $text, $font, $size, $max_width ) {
		$words = preg_split( '/\s+/u', trim( (string) $text ) );
		$lines = array();
		$cur   = '';
		foreach ( $words as $word ) {
			if ( '' === $word ) {
				continue;
			}
			$try = '' === $cur ? $word : $cur . ' ' . $word;
			if ( '' !== $cur && self::text_width( $try, $font, $size ) > $max_width ) {
				$lines[] = $cur;
				$cur     = $word;
			} else {
				$cur = $try;
			}
		}
		if ( '' !== $cur ) {
			$lines[] = $cur;
		}
		return $lines;
	}

	/**
	 * Text at baseline y. $align: left (x = start), center (x = middle), right (x = end).
	 * $spacing adds character spacing (Tc) in points.
	 */
	public function text( $text, $x, $y, $font = 'regular', $size = 12.0, $color = '#000000', $align = 'left', $spacing = 0.0 ) {
		$font  = isset( self::FONTS[ $font ] ) ? $font : 'regular';
		$bytes = self::to_winansi( $text );
		$width = self::text_width( $text, $font, $size ) + $spacing * max( 0, strlen( $bytes ) - 1 );
		if ( 'center' === $align ) {
			$x -= $width / 2;
		} elseif ( 'right' === $align ) {
			$x -= $width;
		}
		$this->ops[] = 'BT /' . self::FONTS[ $font ][0] . ' ' . self::n( $size ) . ' Tf ' . self::rgb( $color ) . ' rg '
			. self::n( $spacing ) . ' Tc '
			. self::n( $x ) . ' ' . self::n( $y ) . ' Td (' . self::escape( $bytes ) . ') Tj ET';
		return $width;
	}

	/** A clickable link over a rectangle (bottom-left x, y). */
	public function link( $x, $y, $w, $h, $url ) {
		$this->links[] = array( $x, $y, $w, $h, (string) $url );
	}

	/**
	 * Place the PNG (decoded with decode_png()) with its bottom-left corner at (x, y).
	 * One image per document is all the certificate needs.
	 */
	public function image( array $img, $x, $y, $w, $h ) {
		$this->image = $img;
		$this->ops[] = 'q ' . self::n( $w ) . ' 0 0 ' . self::n( $h ) . ' ' . self::n( $x ) . ' ' . self::n( $y ) . ' cm /Im1 Do Q';
	}

	/**
	 * Decode an 8-bit, non-interlaced PNG (grey, RGB, with or without alpha) into Flate streams
	 * a PDF can use: colour (DeviceRGB or DeviceGray) and, when present, alpha as a soft mask.
	 * $max_px scales the image down by an integer factor (box filter, alpha-weighted) until it
	 * fits, which keeps the certificate small. Returns null for anything unsupported.
	 *
	 * @return array{w:int,h:int,cs:string,data:string,alpha:?string}|null
	 */
	public static function decode_png( $file, $max_px = 240 ) {
		$bin = is_readable( $file ) ? file_get_contents( $file ) : false;
		if ( false === $bin || "\x89PNG\r\n\x1a\n" !== substr( $bin, 0, 8 ) || ! function_exists( 'gzuncompress' ) ) {
			return null;
		}
		$pos  = 8;
		$idat = '';
		$ihdr = null;
		$len  = strlen( $bin );
		while ( $pos + 8 <= $len ) {
			$clen = unpack( 'N', substr( $bin, $pos, 4 ) )[1];
			$type = substr( $bin, $pos + 4, 4 );
			$data = substr( $bin, $pos + 8, $clen );
			if ( 'IHDR' === $type ) {
				$ihdr = unpack( 'Nw/Nh/Cdepth/Ctype/Ccomp/Cfilter/Cinterlace', $data );
			} elseif ( 'IDAT' === $type ) {
				$idat .= $data;
			} elseif ( 'IEND' === $type ) {
				break;
			}
			$pos += 12 + $clen;
		}
		$channels_by_type = array( 0 => 1, 2 => 3, 4 => 2, 6 => 4 );
		if ( ! $ihdr || 8 !== $ihdr['depth'] || 0 !== $ihdr['interlace'] || ! isset( $channels_by_type[ $ihdr['type'] ] ) ) {
			return null;
		}
		$raw = @gzuncompress( $idat );
		if ( false === $raw ) {
			return null;
		}
		$w      = $ihdr['w'];
		$h      = $ihdr['h'];
		$bpp    = $channels_by_type[ $ihdr['type'] ];
		$color  = $ihdr['type'] >= 2 && 4 !== $ihdr['type'] ? 3 : 1;
		$alpha  = 4 === $ihdr['type'] || 6 === $ihdr['type'];
		$stride = $w * $bpp;
		if ( strlen( $raw ) < ( $stride + 1 ) * $h ) {
			return null;
		}
		$f    = max( 1, (int) ceil( max( $w, $h ) / max( 1, $max_px ) ) );
		$ow   = intdiv( $w, $f );
		$oh   = intdiv( $h, $f );
		$prev = array_fill( 0, $stride, 0 );
		$acc_c = array_fill( 0, $ow * $color, 0 );
		$acc_a = array_fill( 0, $ow, 0 );
		$out_c = '';
		$out_a = '';
		for ( $y = 0; $y < $h; $y++ ) {
			$off    = $y * ( $stride + 1 );
			$filter = ord( $raw[ $off ] );
			$line   = array_values( unpack( 'C*', substr( $raw, $off + 1, $stride ) ) );
			switch ( $filter ) {
				case 1:
					for ( $i = $bpp; $i < $stride; $i++ ) {
						$line[ $i ] = ( $line[ $i ] + $line[ $i - $bpp ] ) & 255;
					}
					break;
				case 2:
					for ( $i = 0; $i < $stride; $i++ ) {
						$line[ $i ] = ( $line[ $i ] + $prev[ $i ] ) & 255;
					}
					break;
				case 3:
					for ( $i = 0; $i < $stride; $i++ ) {
						$left       = $i >= $bpp ? $line[ $i - $bpp ] : 0;
						$line[ $i ] = ( $line[ $i ] + ( ( $left + $prev[ $i ] ) >> 1 ) ) & 255;
					}
					break;
				case 4:
					for ( $i = 0; $i < $stride; $i++ ) {
						$a  = $i >= $bpp ? $line[ $i - $bpp ] : 0;
						$b  = $prev[ $i ];
						$c  = $i >= $bpp ? $prev[ $i - $bpp ] : 0;
						$p  = $a + $b - $c;
						$pa = abs( $p - $a );
						$pb = abs( $p - $b );
						$pc = abs( $p - $c );
						$line[ $i ] = ( $line[ $i ] + ( ( $pa <= $pb && $pa <= $pc ) ? $a : ( $pb <= $pc ? $b : $c ) ) ) & 255;
					}
					break;
			}
			$prev = $line;
			if ( $y >= $oh * $f ) {
				continue;
			}
			// accumulate this row into the output row (alpha-weighted colour)
			for ( $x = 0; $x < $ow * $f; $x++ ) {
				$p  = $x * $bpp;
				$ox = intdiv( $x, $f );
				$a  = $alpha ? $line[ $p + $bpp - 1 ] : 255;
				$acc_a[ $ox ] += $a;
				for ( $k = 0; $k < $color; $k++ ) {
					$acc_c[ $ox * $color + $k ] += $line[ $p + $k ] * $a;
				}
			}
			if ( ( $y + 1 ) % $f === 0 ) {
				$n    = $f * $f;
				$rowc = array();
				$rowa = array();
				for ( $ox = 0; $ox < $ow; $ox++ ) {
					$sa = $acc_a[ $ox ];
					for ( $k = 0; $k < $color; $k++ ) {
						$rowc[] = $sa > 0 ? (int) round( $acc_c[ $ox * $color + $k ] / $sa ) : 255;
					}
					$rowa[] = (int) round( $sa / $n );
				}
				$out_c .= pack( 'C*', ...$rowc );
				$out_a .= pack( 'C*', ...$rowa );
				$acc_c  = array_fill( 0, $ow * $color, 0 );
				$acc_a  = array_fill( 0, $ow, 0 );
			}
		}
		return array(
			'w'     => $ow,
			'h'     => $oh,
			'cs'    => 3 === $color ? 'DeviceRGB' : 'DeviceGray',
			'data'  => gzcompress( $out_c, 9 ),
			'alpha' => $alpha ? gzcompress( $out_a, 9 ) : null,
		);
	}

	/** The finished PDF file as a string. */
	public function output() {
		$objects = array(); // index => body (object number = index + 1)
		$add     = function ( $body ) use ( &$objects ) {
			$objects[] = $body;
			return count( $objects );
		};
		$stream = function ( $dict, $data ) {
			return '<< ' . $dict . ' /Length ' . strlen( $data ) . " >>\nstream\n" . $data . "\nendstream";
		};

		$catalog = $add( '' ); // filled in below
		$pages   = $add( '' );
		$fonts   = array();
		foreach ( self::FONTS as $f ) {
			$fonts[ $f[0] ] = $add( '<< /Type /Font /Subtype /Type1 /BaseFont /' . $f[1] . ' /Encoding /WinAnsiEncoding >>' );
		}
		$xobj = '';
		if ( $this->image ) {
			$img   = $this->image;
			$smask = '';
			if ( null !== $img['alpha'] ) {
				$sm    = $add( $stream( '/Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h'] . ' /ColorSpace /DeviceGray /BitsPerComponent 8 /Filter /FlateDecode', $img['alpha'] ) );
				$smask = ' /SMask ' . $sm . ' 0 R';
			}
			$im   = $add( $stream( '/Type /XObject /Subtype /Image /Width ' . $img['w'] . ' /Height ' . $img['h'] . ' /ColorSpace /' . $img['cs'] . ' /BitsPerComponent 8 /Filter /FlateDecode' . $smask, $img['data'] ) );
			$xobj = ' /XObject << /Im1 ' . $im . ' 0 R >>';
		}
		$font_res = '';
		foreach ( $fonts as $name => $num ) {
			$font_res .= ' /' . $name . ' ' . $num . ' 0 R';
		}
		$kids = array();
		foreach ( array_merge( $this->pages, array( array( 'ops' => $this->ops, 'links' => $this->links ) ) ) as $pg ) {
			$content = implode( "\n", $pg['ops'] );
			if ( function_exists( 'gzcompress' ) ) {
				$contents = $add( $stream( '/Filter /FlateDecode', gzcompress( $content, 9 ) ) );
			} else {
				$contents = $add( $stream( '', $content ) );
			}
			$annots = array();
			foreach ( $pg['links'] as $l ) {
				$annots[] = $add( '<< /Type /Annot /Subtype /Link /Rect [' . self::n( $l[0] ) . ' ' . self::n( $l[1] ) . ' ' . self::n( $l[0] + $l[2] ) . ' ' . self::n( $l[1] + $l[3] ) . '] /Border [0 0 0] /A << /S /URI /URI (' . self::escape( $l[4] ) . ') >> >>' );
			}
			$kids[] = $add(
				'<< /Type /Page /Parent ' . $pages . ' 0 R /MediaBox [0 0 ' . self::n( $this->w ) . ' ' . self::n( $this->h ) . ']'
				. ' /Resources << /Font <<' . $font_res . ' >>' . $xobj . ' /ProcSet [/PDF /Text /ImageB /ImageC] >>'
				. ' /Contents ' . $contents . ' 0 R'
				. ( $annots ? ' /Annots [' . implode( ' ', array_map( function ( $a ) { return $a . ' 0 R'; }, $annots ) ) . ']' : '' )
				. ' >>'
			);
		}
		$objects[ $catalog - 1 ] = '<< /Type /Catalog /Pages ' . $pages . ' 0 R >>';
		$objects[ $pages - 1 ]   = '<< /Type /Pages /Kids [' . implode( ' ', array_map( function ( $k ) { return $k . ' 0 R'; }, $kids ) ) . '] /Count ' . count( $kids ) . ' >>';
		$info = '<< /Producer (Prospergenics courses)';
		foreach ( $this->info as $k => $v ) {
			if ( preg_match( '/^[A-Za-z]+$/', $k ) ) {
				$info .= ' /' . $k . ' (' . self::escape( self::to_winansi( $v ) ) . ')';
			}
		}
		$info_num = $add( $info . ' /CreationDate (D:' . gmdate( 'YmdHis' ) . "Z) >>" );

		$pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
		$offsets = array();
		foreach ( $objects as $i => $body ) {
			$offsets[ $i + 1 ] = strlen( $pdf );
			$pdf .= ( $i + 1 ) . " 0 obj\n" . $body . "\nendobj\n";
		}
		$xref = strlen( $pdf );
		$pdf .= 'xref' . "\n" . '0 ' . ( count( $objects ) + 1 ) . "\n" . "0000000000 65535 f \n";
		foreach ( $offsets as $off ) {
			$pdf .= sprintf( "%010d 00000 n \n", $off );
		}
		$pdf .= 'trailer' . "\n" . '<< /Size ' . ( count( $objects ) + 1 ) . ' /Root ' . $catalog . ' 0 R /Info ' . $info_num . " 0 R >>\n";
		$pdf .= "startxref\n" . $xref . "\n%%EOF\n";
		return $pdf;
	}
}

<?php
/**
 * Standalone tests for the courses track's certificate PDF (card #6361): the hand-written PDF
 * writer in inc/courses/pdf.php and the certificate layout in inc/courses/certificate.php.
 * Checks the file structure (header, every xref offset, startxref, trailer), the text, the
 * verification link, the logo decoding (PNG with alpha -> image + soft mask) and, when poppler
 * is installed, that pdfinfo/pdftotext accept the file.
 *
 * Run with: php tests/test-platform-courses-pdf.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

define( 'ABSPATH', sys_get_temp_dir() . '/pg-courses-pdf-test/' );
function apply_filters( $hook, $value ) { return $value; }
require $theme . '/inc/courses/helpers.php';
require $theme . '/inc/courses/pdf.php';
require $theme . '/inc/courses/certificate.php';

echo "Text helpers\n";
ok( abs( Prospergenics_Courses_Pdf::text_width( 'Hello', 'regular', 12 ) - 27.336 ) < 0.001, 'Helvetica width of "Hello" at 12pt = 27.336pt (AFM metrics)' );
ok( Prospergenics_Courses_Pdf::text_width( 'Hello', 'bold', 12 ) > Prospergenics_Courses_Pdf::text_width( 'Hello', 'regular', 12 ), 'bold is wider' );
ok( 'a\\(b\\)c\\\\' === Prospergenics_Courses_Pdf::escape( 'a(b)c\\' ), 'parentheses and backslashes escaped' );
ok( '\\366' === Prospergenics_Courses_Pdf::escape( Prospergenics_Courses_Pdf::to_winansi( 'ö' ) ), 'non-ASCII written as WinAnsi octal escapes' );
ok( '?' === Prospergenics_Courses_Pdf::to_winansi( '中' ), 'characters outside WinAnsi become ?' );
$lines = Prospergenics_Courses_Pdf::wrap( 'Value creation with AI agents: from first prompt to a working team', 'bold', 22, 400 );
ok( count( $lines ) >= 2 && max( array_map( function ( $l ) { return Prospergenics_Courses_Pdf::text_width( $l, 'bold', 22 ); }, $lines ) ) <= 400, 'wrap keeps lines within the width' );

echo "Logo\n";
$logo = $theme . '/assets/logo.png';
$img  = Prospergenics_Courses_Pdf::decode_png( $logo );
ok( is_array( $img ) && $img['w'] > 50 && $img['w'] <= 240 && $img['h'] <= 240, 'logo decoded and scaled down to at most 240px' );
ok( $img && 'DeviceRGB' === $img['cs'] && null !== $img['alpha'], 'RGBA logo gives an RGB image plus an alpha soft mask' );
ok( $img && strlen( gzuncompress( $img['data'] ) ) === $img['w'] * $img['h'] * 3 && strlen( gzuncompress( $img['alpha'] ) ) === $img['w'] * $img['h'], 'decoded sample counts match the image size' );
ok( null === Prospergenics_Courses_Pdf::decode_png( __FILE__ ), 'non-PNG input is rejected' );

echo "Certificate PDF\n";
$data = array(
	'name'       => 'Amina Wanjiru Ötztürk',
	'course'     => 'Value creation with AI agents (part 1)',
	'instructor' => 'Martien de Jong',
	'date'       => '25 October 2026',
	'code'       => 'PG-ABCD-EFGH-2345',
	'verify_url' => 'https://prospergenics.com/courses/verify/PG-ABCD-EFGH-2345/',
);
$pdf = prospergenics_courses_certificate_pdf( $data, $logo );
ok( 0 === strpos( $pdf, "%PDF-1.4\n" ) && "%%EOF\n" === substr( $pdf, -6 ), 'PDF header and EOF marker' );
preg_match( '/startxref\n(\d+)\n%%EOF\n$/', $pdf, $m );
$xref = isset( $m[1] ) ? (int) $m[1] : -1;
ok( $xref > 0 && 'xref' === substr( $pdf, $xref, 4 ), 'startxref points at the xref table' );
preg_match( '/^xref\n0 (\d+)\n/', substr( $pdf, $xref ), $n );
$count   = isset( $n[1] ) ? (int) $n[1] : 0;
$entries = array_slice( explode( "\n", substr( $pdf, $xref ) ), 3, $count - 1 );
$good    = $count > 5;
foreach ( $entries as $i => $e ) {
	$off  = (int) substr( $e, 0, 10 );
	$good = $good && 20 === strlen( $e . "\n" ) && 0 === strpos( substr( $pdf, $off ), ( $i + 1 ) . ' 0 obj' );
}
ok( $good, "all $count xref entries point at their objects (20-byte entries)" );
ok( false !== strpos( $pdf, '/Size ' . $count . ' /Root 1 0 R /Info' ), 'trailer names the catalog and info' );
ok( 1 === preg_match( '#/MediaBox \[0 0 842 595\]#', $pdf ), 'A4 landscape page' );
ok( false !== strpos( $pdf, '/BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding' ), 'standard fonts, WinAnsi encoding (nothing to embed)' );
ok( false !== strpos( $pdf, '/S /URI /URI (' . $data['verify_url'] . ')' ), 'clickable verification link' );
ok( 1 === preg_match( '#/SMask \d+ 0 R#', $pdf ) && false !== strpos( $pdf, '/XObject << /Im1' ), 'logo placed with its soft mask' );
preg_match( '#<< /Filter /FlateDecode /Length (\d+) >>\nstream\n#', $pdf, $s, PREG_OFFSET_CAPTURE );
$content = $s ? gzuncompress( substr( $pdf, $s[0][1] + strlen( $s[0][0] ), (int) $s[1][0] ) ) : '';
foreach ( array( '(CERTIFICATE OF COMPLETION)', '(Amina Wanjiru \\326tzt\\374rk)', '(Value creation with AI agents \\(part 1\\))', '(Certificate ID PG-ABCD-EFGH-2345)', 'Taught by Martien de Jong', 'Completed on 25 October 2026' ) as $needle ) {
	ok( false !== strpos( $content, $needle ), "content has $needle" );
}

$tmp = tempnam( sys_get_temp_dir(), 'pgcert' );
file_put_contents( $tmp, $pdf );
$pdfinfo = trim( (string) shell_exec( 'command -v pdfinfo' ) );
if ( $pdfinfo ) {
	$info = (string) shell_exec( 'pdfinfo ' . escapeshellarg( $tmp ) . ' 2>&1' );
	ok( 1 === preg_match( '/^Pages:\s+1$/m', $info ) && false === stripos( $info, 'error' ), 'pdfinfo opens the file without errors' );
	$text = (string) shell_exec( 'pdftotext ' . escapeshellarg( $tmp ) . ' - 2>&1' );
	ok( false !== strpos( $text, 'Amina Wanjiru Ötztürk' ) && false !== strpos( $text, $data['verify_url'] ), 'pdftotext reads the name (with accents) and the verification URL' );
} else {
	echo "  SKIP  poppler (pdfinfo/pdftotext) not installed\n";
}
unlink( $tmp );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All certificate PDF tests passed\n" );
exit( $failures ? 1 : 0 );

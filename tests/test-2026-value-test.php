<?php
/**
 * Standalone test for the homepage value test "How valuable are you?", in
 * design/new-hero-2026-10/index.html and the generated front-page.php.
 *
 * Martien asked (26 Oct) for the original test to be fully restored: five open questions,
 * then an email address, and the value profile from CoachOS (tenant prospergenics, context
 * value-test) after the visitor confirms by email. It replaces the tap-question version from
 * card #6532 (commit e874e78). Only screen-reader labels were added to the original.
 *
 * Checks:
 *   - the five original questions, in order, one per screen with progress, Back and Next,
 *   - the email step, the privacy notice and the CoachOS double opt-in (aanvraag, then bevestig
 *     from the ?vt=<token> link in the mail),
 *   - the result: profile name, explanation and the courses button,
 *   - the answer box, the email field and the errors can be read by a screen reader,
 *   - nothing takes focus on page load,
 *   - the generated front-page.php carries this test and not the tap version.
 *
 * Run with: php tests/test-2026-value-test.php
 */

$root   = dirname( __DIR__ );
$design = $root . '/design/new-hero-2026-10';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

$home = file_get_contents( $design . '/index.html' );
ok( (bool) preg_match( '#// ── Value test: five open questions.*?\n    \}\)\(\);#s', $home, $m ), 'the value test script is present' );
$js = $m[0] ?? '';
ok( (bool) preg_match( '#<section class="vtest" id="value-test">.*?</section>#s', $home, $mm ), 'the value test section is present' );
$section = $mm[0] ?? '';

echo "The original test\n";
ok( false !== strpos( $section, '<h2>How valuable <span class="accent">are you?</span></h2>' ), 'heading "How valuable are you?"' );
ok( false !== strpos( $section, '<p>Five honest questions. Take the test and find out where your value lies.</p>' ), 'the original intro' );
$questions = array(
	'What could you talk about for hours without getting tired?',
	'What do people around you keep asking your help with?',
	'What did you recently make, fix or organise that you were quietly proud of?',
	'If money did not matter for a year, how would you spend your days?',
	'What about the world annoys you so much that you would love to change it?',
);
preg_match( '#const QUESTIONS = \[(.*?)\];#s', $js, $q );
preg_match_all( '#"([^"]+)"#', $q[1] ?? '', $found );
ok( $questions === $found[1], 'the five original questions, in order' );
ok( false !== strpos( $section, '<textarea class="vt-answer" id="vtAnswer" rows="4"' ), 'open answers in a text box' );
ok( false !== strpos( $js, "'Reveal my value <span class=\"arrow\">&rarr;</span>'" ) && false !== strpos( $js, "hintEl.textContent = (step + 1) + ' / ' + QUESTIONS.length;" ), 'progress, and "Reveal my value" on the last question' );
ok( false !== strpos( $section, 'data-scroll-scale="1.2" data-scroll-scale-window="0.65"' ), 'the test box grows in on scroll, as before' );

echo "Email and profile\n";
ok( false !== strpos( $js, 'Almost there - where can we send your value profile?' ), 'the email step follows the questions' );
ok( false !== strpos( $js, 'href="/privacy-policy/">Privacy policy</a>' ), 'the privacy notice links to the privacy policy' );
ok( false !== strpos( $js, "const API_BASE = \"https://coaching.martiendejong.nl/coaching\";" ), 'the CoachOS endpoint' );
ok( false !== strpos( $js, "'/api/leadtest/aanvraag'" ) && false !== strpos( $js, "contextSlug: 'value-test'" ) && false !== strpos( $js, "'X-Tenant': 'prospergenics'" ), 'the answers and email go to the value-test context' );
ok( false !== strpos( $js, "'/api/leadtest/bevestig'" ) && false !== strpos( $js, "get('vt')" ), 'the confirmation link (?vt=<token>) runs the analysis' );
ok( false !== strpos( $js, "url.searchParams.delete('vt');" ), 'and the token is removed from the address bar' );
ok( false !== strpos( $js, "'<div class=\"vt-profile\"></div>'" ) && false !== strpos( $js, 'Start developing your value' ), 'the result: profile name, explanation and the courses button' );
ok( false !== strpos( $js, ".textContent = profile;" ) && false !== strpos( $js, ".textContent = rest;" ), 'the AI text is inserted as text, never as HTML' );

echo "Accessibility\n";
ok( false !== strpos( $section, 'id="vtAnswer" rows="4" aria-labelledby="vtQuestion"' ), 'the answer box is labelled by the question' );
ok( false !== strpos( $js, 'id="vtEmail" aria-label="Your email address"' ), 'the email field has a label' );
ok( false !== strpos( $section, 'id="vtError" role="alert"' ) && false !== strpos( $js, 'id="vtErr2" role="alert"' ), 'errors are announced' );
ok( false !== strpos( $js, 'if (vtToken) { confirmFlow(vtToken); } else { render(); }' ) && false !== strpos( $js, 'if (moveFocus) answerEl.focus' ), 'nothing takes focus on page load' );

echo "Generated theme\n";
$front = file_get_contents( $root . '/wp-theme/prospergenics-2026/front-page.php' );
ok( false !== strpos( $front, '// ── Value test: five open questions' ) && false !== strpos( $front, 'vtAnswer' ) && false === strpos( $front, 'Find your next step' ), 'front-page.php carries the original test and not the tap version' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

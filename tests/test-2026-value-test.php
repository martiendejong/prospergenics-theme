<?php
/**
 * Standalone test for the homepage value test "Find your next step" (audit UX-2 and UX-3,
 * reported by Lesiamon; card #6532), in design/new-hero-2026-10/index.html and the generated
 * front-page.php.
 *
 * Checks:
 *   - the questions are mostly tap answers: seven choice questions plus one optional one-liner,
 *   - the result is shown on the page without an email, and every link it can give exists,
 *   - with no published courses, the result never sends people to an empty /courses/,
 *   - the email for the deeper profile comes only after the result, with a visible label, the
 *     privacy notice directly under its button and errors announced (role=alert, aria-invalid),
 *   - each question is a fieldset with a legend, progress is announced in a polite live region,
 *   - nothing takes focus on page load, a tap only moves on by itself without reduced motion,
 *   - the email confirmation flow (?vt=<token>) still works against the same CoachOS endpoints.
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
ok( (bool) preg_match( '#// ── Value test "Find your next step".*?\n    \}\)\(\);#s', $home, $m ), 'the value test script is present' );
$js = $m[0] ?? '';
ok( (bool) preg_match( '#<section class="vtest" id="value-test">.*?</section>#s', $home, $mm ), 'the value test section is present' );
$section = $mm[0] ?? '';

echo "Questions\n";
preg_match_all( "#\{ id: '([a-z]+)', type: '([a-z]+)'#", $js, $steps, PREG_SET_ORDER );
$types = array_column( $steps, 2, 1 );
ok( 8 === count( $types ), '8 questions (' . implode( ', ', array_keys( $types ) ) . ')' );
ok( 7 === count( array_filter( $types, function ( $t ) { return 'text' !== $t; } ) ), '7 of them are answered by tapping' );
ok( 'text' === ( $types['dream'] ?? '' ) && false !== strpos( $js, "optional: true, q: 'In one line" ), 'the one free-text question is optional' );
ok( false !== strpos( $js, "type: 'multi', max: 3" ), 'skills: pick up to 3' );
ok( false !== strpos( $js, 'You can pick up to \' + s.max + \'. Unpick one to choose another.' ), 'a fourth pick is refused with an explanation' );
ok( 'Eight quick questions, about a minute.' === ( preg_match( '#<p>(Eight quick questions, about a minute\.)#', $section, $c ) ? $c[1] : '' ), 'the intro states the real number of questions' );

echo "Result\n";
ok( false !== strpos( $js, "connect: ['Connector'" ) && false !== strpos( $js, "explain: ['Explainer'" ) && false !== strpos( $js, "make:    ['Maker'" ) && false !== strpos( $js, "build:   ['Builder'" ), 'four profiles' );
ok( false !== strpos( $js, "paid <= 2 ? 'explore' : (paid <= 4 ? 'test' : 'grow')" ), 'stage from "would people pay for that today?"' );
ok( false !== strpos( $js, "renderResult();" ) && strpos( $js, 'function renderResult' ) < strpos( $js, 'function deeperHtml' ), 'the result is rendered in the browser, before any email step' );
preg_match_all( "#href: '([^']+)'#", $js, $hrefs );
$targets = array_unique( $hrefs[1] );
foreach ( $targets as $href ) {
	ok( in_array( $href, array( '/members/', '/courses/', '/tools/', '/services/' ), true ) || 0 === strpos( $href, '/contact/' ), "result link $href is an existing page" );
}
ok( false !== strpos( $js, "if (coursesOpen) return { label: 'Browse the courses', href: '/courses/' };" ), 'courses are offered only when the catalogue has a course' );
ok( false !== strpos( $js, "return { label: 'Tell us what you want to learn', href: '/contact/?subject=' + encodeURIComponent('Courses and certification') };" ), 'otherwise the contact form, with the subject preselected' );
ok( false !== strpos( $js, "fetch('/wp-json/prospergenics/v1/courses?per_page=1')" ), 'the course check uses the public catalogue endpoint' );
ok( false !== strpos( $js, "answers.goal === 'organisation') { renderOrganisation(); return; }" ), 'organisations get their own result after the first question' );

echo "Email and privacy\n";
$deeper = substr( $js, strpos( $js, 'function deeperHtml' ), strpos( $js, 'function wireDeeper' ) - strpos( $js, 'function deeperHtml' ) );
ok( false !== strpos( $deeper, '<label class="vt-label" for="vtEmail">Your email</label>' ), 'the email field has a visible label' );
ok( strpos( $deeper, 'Email me my profile' ) < strpos( $deeper, 'vt-privacy' ), 'the privacy notice sits directly under the button' );
ok( false !== strpos( $deeper, 'href="/privacy-policy/"' ), 'and links to the privacy policy' );
ok( false !== strpos( $deeper, 'role="alert"' ) && false !== strpos( $js, "email.setAttribute('aria-invalid', 'true')" ), 'email errors are announced' );
ok( false === stripos( $js, 'checkbox" name="vt-marketing' ) && false === stripos( $js, 'newsletter' ), 'no marketing opt-in the back end cannot honour' );
ok( false !== strpos( $js, "'/api/leadtest/aanvraag'" ) && false !== strpos( $js, "'/api/leadtest/bevestig'" ) && false !== strpos( $js, "contextSlug: 'value-test'" ), 'the CoachOS double opt-in is kept' );

echo "Accessibility\n";
ok( false !== strpos( $js, '<fieldset class="vt-step"' ) && false !== strpos( $js, '<legend class="vt-question" tabindex="-1">' ), 'each question is a fieldset with a legend' );
ok( false !== strpos( $section, 'id="vtLive" aria-live="polite"' ) && false !== strpos( $js, "announce('Question ' + (step + 1) + ' of ' + total" ), 'progress is announced in a polite live region' );
ok( false !== strpos( $js, "kind = s.type === 'multi' ? 'checkbox' : 'radio'" ), 'answers are native radios and checkboxes' );
ok( false !== strpos( $js, 'if (lastPointer && !reduceMotion.matches) setTimeout' ), 'a tap moves on by itself only for pointer users and not under reduced motion' );
ok( false !== strpos( $js, "if (vtToken) { confirmFlow(vtToken); } else { wireStart(); }" ), 'page load only wires the Start button: nothing takes focus' );
ok( false === strpos( $section, 'autofocus' ) && false === strpos( $section, 'data-scroll-scale' ), 'no autofocus and no scroll zoom on the test box' );
ok( false !== strpos( $section, '<noscript>' ), 'a way forward without JavaScript' );

echo "Generated theme\n";
$front = file_get_contents( $root . '/wp-theme/prospergenics-2026/front-page.php' );
ok( false !== strpos( $front, '// ── Value test "Find your next step"' ) && false === strpos( $front, 'vtAnswer' ), 'front-page.php carries the new test and not the old one' );

echo $failures ? "\n$failures FAILED\n" : "\nAll passed\n";
exit( $failures ? 1 : 0 );

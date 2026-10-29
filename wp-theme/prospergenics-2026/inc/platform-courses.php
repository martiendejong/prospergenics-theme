<?php
/**
 * Prospergenics Platform: courses track (JengoWork board "Prospergenics Platform").
 * Loader for this track's modules in inc/courses/. See docs/PLATFORM-BUILD.md.
 *
 * Cards #6355-#6364: course data model, /courses/ listing, course pages, lesson viewer,
 * enrolment, progress, PDF certificates with a public verification page, instructor pages,
 * the course wizard and the instructor dashboard. privacy.php: export/erase of enrolments and
 * certificates (audit ARC-12). notify.php: enrolment and certificate emails, the next course (ARC-20).
 * quiz.php: lesson quizzes (#6461). lite.php and notes.php: lessons on slow and costly
 * connections, video facades and the notes PDF (#6469).
 */

defined( 'ABSPATH' ) || exit;

foreach ( array( 'helpers', 'pdf', 'data-model', 'enrolment', 'certificate', 'routing', 'render', 'rest', 'instructor', 'privacy', 'notify', 'quiz', 'lite', 'notes' ) as $prospergenics_courses_module ) {
	require __DIR__ . '/courses/' . $prospergenics_courses_module . '.php';
}
unset( $prospergenics_courses_module );

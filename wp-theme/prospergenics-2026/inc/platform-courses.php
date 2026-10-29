<?php
/**
 * Prospergenics Platform: courses track (JengoWork board "Prospergenics Platform").
 * Loader for this track's modules in inc/courses/. See docs/PLATFORM-BUILD.md.
 *
 * Cards #6355-#6364: course data model, /courses/ listing, course pages, lesson viewer,
 * enrolment, progress, PDF certificates with a public verification page, instructor pages,
 * the course wizard and the instructor dashboard. privacy.php: export/erase of enrolments and
 * certificates (audit ARC-12). notify.php: enrolment and certificate emails, the next course (ARC-20).
 * discussion.php: questions and replies under lessons (card #6464).
 */

defined( 'ABSPATH' ) || exit;

foreach ( array( 'helpers', 'pdf', 'data-model', 'enrolment', 'certificate', 'routing', 'render', 'rest', 'instructor', 'privacy', 'notify', 'discussion' ) as $prospergenics_courses_module ) {
	require __DIR__ . '/courses/' . $prospergenics_courses_module . '.php';
}
unset( $prospergenics_courses_module );

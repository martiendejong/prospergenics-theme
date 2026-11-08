<?php
/**
 * Courses track: lesson quizzes (card #6461).
 *
 * An optional quiz per lesson. Question types: 'single' (multiple choice, one answer),
 * 'multiple' (multiple answer: every correct option and nothing else) and 'short' (short answer,
 * exact match ignoring case and spaces, several accepted answers). Each question can carry an
 * explanation, shown after the learner answered. Pass mark per quiz (default 70%).
 *
 * Storage
 *   - Questions: lesson meta _pg_courses_quiz, JSON, sanitised on every save
 *     (prospergenics_courses_sanitize_quiz()). Not exposed by any REST route or the wp/v2
 *     endpoint (show_in_rest false): learners only ever receive the questions and options,
 *     rendered by prospergenics_courses_quiz_html(). Correct answers stay on the server and the
 *     answers are graded there (prospergenics_courses_grade_quiz()).
 *   - Attempts: on the learner's enrolment record (meta _pg_courses_quiz_attempts: lesson id =>
 *     {count, best, passed_at, attempts[]}), next to the lesson progress. Why there and not in
 *     user meta or a table: an attempt only means something within one enrolment, exactly like
 *     the progress it unlocks, so it shares that record's lifecycle for free: unenrolling,
 *     deleting the course and the privacy eraser all remove the attempts with the progress, and
 *     the instructor dashboard reads them through the enrolments of the course it already
 *     queries (no extra query per learner, no schema to migrate; docs/PLATFORM-BUILD.md asks
 *     for meta/CPTs over custom tables). The latest 20 attempts per lesson are kept with their
 *     answers; the count and best score cover all of them.
 *
 * Completion: a lesson with a quiz completes only when the quiz is passed. Passing completes it
 * (and so drives progress and the certificate as before); prospergenics_courses_set_lesson_complete()
 * refuses "Mark as complete" for a quiz lesson without a passing attempt, so neither the form
 * nor POST /courses/lessons/{id}/completion can skip the quiz. Failing allows retries.
 *
 * Entry points (both act on the current user, check a nonce and are rate limited):
 *   POST admin-post.php?action=pg_courses_quiz   the lesson page form (works without JavaScript)
 *   POST /prospergenics/v1/courses/lessons/{id}/quiz   {answers: {question id: answer}}
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_COURSES_QUIZ_PASS_MARK = 70;
const PROSPERGENICS_COURSES_QUIZ_MAX_QUESTIONS = 50;
const PROSPERGENICS_COURSES_QUIZ_KEEP_ATTEMPTS = 20;
/** Quiz submissions a learner may send per minute. */
const PROSPERGENICS_COURSES_QUIZ_RATE_LIMIT = 20;

/* ------------------------------------------------------------------ pure helpers */

/** The question types, slug => label. */
function prospergenics_courses_quiz_types() {
	return array(
		'single'   => 'Multiple choice (one answer)',
		'multiple' => 'Multiple answers',
		'short'    => 'Short answer',
	);
}

/** Plain text, one line or several, trimmed and cut to $max characters. */
function prospergenics_courses_quiz_text( $text, $max, $multiline = false ) {
	$text = html_entity_decode( strip_tags( (string) $text ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	$text = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $text );
	$text = $multiline ? preg_replace( "/[ \t]+/u", ' ', str_replace( "\r", '', $text ) ) : preg_replace( '/\s+/u', ' ', $text );
	return mb_substr( trim( (string) $text ), 0, $max );
}

/**
 * Sanitise a quiz (array or JSON). Questions that are still incomplete (no prompt, fewer than
 * two options, no correct option, no accepted answer) are kept, so an instructor's half-written
 * question survives autosave, but they are flagged and never shown to learners or graded.
 *
 * @return array{pass_mark:int,questions:array}
 */
function prospergenics_courses_sanitize_quiz( $raw ) {
	if ( is_string( $raw ) ) {
		$raw = json_decode( $raw, true );
	}
	$raw  = is_array( $raw ) ? $raw : array();
	$mark = isset( $raw['pass_mark'] ) && is_numeric( $raw['pass_mark'] ) ? (int) round( (float) $raw['pass_mark'] ) : PROSPERGENICS_COURSES_QUIZ_PASS_MARK;
	$out  = array( 'pass_mark' => max( 1, min( 100, $mark ) ), 'questions' => array() );
	$ids  = array();
	foreach ( isset( $raw['questions'] ) && is_array( $raw['questions'] ) ? array_values( $raw['questions'] ) : array() as $i => $q ) {
		if ( ! is_array( $q ) || count( $out['questions'] ) >= PROSPERGENICS_COURSES_QUIZ_MAX_QUESTIONS ) {
			continue;
		}
		$type = isset( $q['type'] ) && array_key_exists( $q['type'], prospergenics_courses_quiz_types() ) ? $q['type'] : 'single';
		$id   = isset( $q['id'] ) ? strtolower( preg_replace( '/[^A-Za-z0-9]/', '', (string) $q['id'] ) ) : '';
		$id   = substr( $id, 0, 24 );
		if ( '' === $id || isset( $ids[ $id ] ) ) {
			$id = 'q' . substr( md5( $i . '|' . ( isset( $q['prompt'] ) ? (string) $q['prompt'] : '' ) . '|' . count( $ids ) ), 0, 10 );
		}
		$ids[ $id ] = true;
		$item       = array(
			'id'          => $id,
			'type'        => $type,
			'prompt'      => prospergenics_courses_quiz_text( isset( $q['prompt'] ) ? $q['prompt'] : '', 600, true ),
			'explanation' => prospergenics_courses_quiz_text( isset( $q['explanation'] ) ? $q['explanation'] : '', 1200, true ),
		);
		if ( 'short' === $type ) {
			$answers = isset( $q['answers'] ) ? $q['answers'] : array();
			$answers = is_array( $answers ) ? $answers : preg_split( '/\r\n|\r|\n/', (string) $answers );
			$keep    = array();
			foreach ( $answers as $a ) {
				$a = prospergenics_courses_quiz_text( is_scalar( $a ) ? $a : '', 200 );
				if ( '' !== $a && ! isset( $keep[ prospergenics_courses_quiz_normalize( $a ) ] ) && count( $keep ) < 20 ) {
					$keep[ prospergenics_courses_quiz_normalize( $a ) ] = $a;
				}
			}
			$item['answers'] = array_values( $keep );
		} else {
			$options = array();
			$correct = array();
			$given   = isset( $q['correct'] ) ? array_map( 'intval', (array) $q['correct'] ) : array();
			foreach ( isset( $q['options'] ) && is_array( $q['options'] ) ? array_values( $q['options'] ) : array() as $oi => $opt ) {
				$text = prospergenics_courses_quiz_text( is_array( $opt ) ? ( isset( $opt['text'] ) ? $opt['text'] : '' ) : ( is_scalar( $opt ) ? $opt : '' ), 300 );
				$is   = is_array( $opt ) ? ! empty( $opt['correct'] ) : in_array( $oi, $given, true );
				if ( '' === $text || count( $options ) >= 10 ) {
					continue;
				}
				if ( $is ) {
					$correct[] = count( $options );
				}
				$options[] = $text;
			}
			if ( 'single' === $type && count( $correct ) > 1 ) {
				$correct = array( $correct[0] );
			}
			$item['options'] = $options;
			$item['correct'] = $correct;
		}
		$item['ready']        = prospergenics_courses_quiz_question_ready( $item );
		$out['questions'][] = $item;
	}
	return $out;
}

/** Whether a sanitised question can be shown and graded. */
function prospergenics_courses_quiz_question_ready( array $q ) {
	if ( '' === $q['prompt'] ) {
		return false;
	}
	if ( 'short' === $q['type'] ) {
		return ! empty( $q['answers'] );
	}
	return count( $q['options'] ) >= 2 && ! empty( $q['correct'] );
}

/** The questions learners get (complete ones only). */
function prospergenics_courses_quiz_ready_questions( array $quiz ) {
	return array_values( array_filter( isset( $quiz['questions'] ) ? $quiz['questions'] : array(), function ( $q ) { return ! empty( $q['ready'] ); } ) );
}

/** Short answers compare without case and without any whitespace ("New York" = "newyork"). */
function prospergenics_courses_quiz_normalize( $text ) {
	$text = preg_replace( '/\s+/u', '', (string) $text );
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( $text, 'UTF-8' ) : strtolower( $text );
}

/**
 * Grade answers against a quiz (ready questions only). $answers: question id => option index
 * (single), list of indexes (multiple) or text (short). Unanswered questions count as wrong.
 *
 * @return array{score:int,passed:bool,correct:int,total:int,pass_mark:int,answers:array,results:array<string,bool>}
 */
function prospergenics_courses_grade_quiz( array $quiz, $answers ) {
	$answers   = is_array( $answers ) ? $answers : array();
	$questions = prospergenics_courses_quiz_ready_questions( $quiz );
	$mark      = isset( $quiz['pass_mark'] ) ? (int) $quiz['pass_mark'] : PROSPERGENICS_COURSES_QUIZ_PASS_MARK;
	$right     = 0;
	$results   = array();
	$given_all = array();
	foreach ( $questions as $q ) {
		$given = isset( $answers[ $q['id'] ] ) ? $answers[ $q['id'] ] : null;
		$ok    = false;
		if ( 'short' === $q['type'] ) {
			$given = is_scalar( $given ) ? prospergenics_courses_quiz_text( $given, 200 ) : '';
			$norm  = prospergenics_courses_quiz_normalize( $given );
			foreach ( $q['answers'] as $a ) {
				$ok = $ok || ( '' !== $norm && prospergenics_courses_quiz_normalize( $a ) === $norm );
			}
		} elseif ( 'multiple' === $q['type'] ) {
			$picked = array();
			foreach ( is_array( $given ) ? $given : ( null === $given || '' === $given ? array() : array( $given ) ) as $g ) {
				if ( is_numeric( $g ) && (int) $g >= 0 && (int) $g < count( $q['options'] ) ) {
					$picked[ (int) $g ] = (int) $g;
				}
			}
			ksort( $picked );
			$given = array_values( $picked );
			$want  = $q['correct'];
			sort( $want );
			$ok = $given === $want;
		} else {
			$given = is_scalar( $given ) && is_numeric( $given ) && (int) $given >= 0 && (int) $given < count( $q['options'] ) ? (int) $given : null;
			$ok    = null !== $given && in_array( $given, $q['correct'], true );
		}
		$results[ $q['id'] ]   = $ok;
		$given_all[ $q['id'] ] = $given;
		$right                += $ok ? 1 : 0;
	}
	$total = count( $questions );
	return array(
		'score'     => $total ? (int) floor( $right * 100 / $total ) : 0,
		'passed'    => $total > 0 && $right * 100 >= $mark * $total,
		'correct'   => $right,
		'total'     => $total,
		'pass_mark' => $mark,
		'answers'   => $given_all,
		'results'   => $results,
	);
}

/**
 * Add an attempt to a lesson's attempt record (pure). Keeps the latest
 * PROSPERGENICS_COURSES_QUIZ_KEEP_ATTEMPTS attempts; count, best and first pass cover all.
 *
 * @return array{count:int,best:int,passed_at:int,attempts:array}
 */
function prospergenics_courses_quiz_add_attempt( $record, array $grade, $now ) {
	$record = is_array( $record ) ? $record : array();
	$record = array_merge( array( 'count' => 0, 'best' => 0, 'passed_at' => 0, 'attempts' => array() ), $record );
	$record['count']      = (int) $record['count'] + 1;
	$record['best']       = max( (int) $record['best'], (int) $grade['score'] );
	if ( $grade['passed'] && ! $record['passed_at'] ) {
		$record['passed_at'] = (int) $now;
	}
	$record['attempts'][] = array(
		'at'      => (int) $now,
		'score'   => (int) $grade['score'],
		'passed'  => (bool) $grade['passed'],
		'answers' => $grade['answers'],
		'results' => $grade['results'],
	);
	$record['attempts'] = array_slice( $record['attempts'], -PROSPERGENICS_COURSES_QUIZ_KEEP_ATTEMPTS );
	return $record;
}

/* ------------------------------------------------------------------ storage */

/** The lesson's quiz, or null when it has none with at least one complete question. */
function prospergenics_courses_get_quiz( $lesson_id, $include_drafts = false ) {
	$raw = get_post_meta( (int) $lesson_id, '_pg_courses_quiz', true );
	if ( '' === $raw || null === $raw || false === $raw ) {
		return null;
	}
	$quiz = prospergenics_courses_sanitize_quiz( $raw );
	if ( ! $quiz['questions'] || ( ! $include_drafts && ! prospergenics_courses_quiz_ready_questions( $quiz ) ) ) {
		return null;
	}
	return $quiz;
}

/** Whether the lesson has a quiz learners must pass. */
function prospergenics_courses_lesson_has_quiz( $lesson_id ) {
	return null !== prospergenics_courses_get_quiz( $lesson_id );
}

/** Save (or, with no questions, remove) a lesson's quiz. Callers check edit_post. */
function prospergenics_courses_save_quiz( $lesson_id, $raw ) {
	$quiz = prospergenics_courses_sanitize_quiz( $raw );
	if ( ! $quiz['questions'] ) {
		delete_post_meta( (int) $lesson_id, '_pg_courses_quiz' );
		return null;
	}
	update_post_meta( (int) $lesson_id, '_pg_courses_quiz', wp_slash( wp_json_encode( $quiz, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) ) );
	return $quiz;
}

/** All attempt records of an enrolment: lesson id => record. */
function prospergenics_courses_quiz_records( $enrolment_id ) {
	$all = get_post_meta( (int) $enrolment_id, '_pg_courses_quiz_attempts', true );
	return is_array( $all ) ? $all : array();
}

/** The learner's attempt record for a lesson (null when they never tried it). */
function prospergenics_courses_quiz_record( $user_id, $lesson_id ) {
	$enrolment = prospergenics_courses_get_enrolment( $user_id, prospergenics_courses_course_of( $lesson_id ) );
	if ( ! $enrolment ) {
		return null;
	}
	$all = prospergenics_courses_quiz_records( $enrolment->ID );
	return isset( $all[ (int) $lesson_id ] ) && is_array( $all[ (int) $lesson_id ] ) ? $all[ (int) $lesson_id ] : null;
}

/** Whether the learner ever passed the lesson's quiz. */
function prospergenics_courses_quiz_passed( $user_id, $lesson_id ) {
	$record = prospergenics_courses_quiz_record( $user_id, $lesson_id );
	return $record && ! empty( $record['passed_at'] );
}

/**
 * Why "Mark as complete" is refused for this lesson (WP_Error), or null. Called by
 * prospergenics_courses_set_lesson_complete().
 */
function prospergenics_courses_quiz_completion_block( $user_id, $lesson_id ) {
	if ( prospergenics_courses_lesson_has_quiz( $lesson_id ) && ! prospergenics_courses_quiz_passed( $user_id, $lesson_id ) ) {
		return new WP_Error( 'pg_courses_quiz_required', 'Pass the quiz to complete this lesson.', array( 'status' => 403 ) );
	}
	return null;
}

/**
 * Grade and record the current learner's answers; passing completes the lesson.
 *
 * @return array|WP_Error grade + {attempts, best, lesson_completed, progress, certificate, certificate_blocked}
 */
function prospergenics_courses_submit_quiz( $user_id, $lesson_id, $answers ) {
	$lesson    = get_post( $lesson_id );
	$course_id = $lesson ? prospergenics_courses_course_of( $lesson ) : 0;
	if ( ! $lesson || 'pg_lesson' !== $lesson->post_type || 'publish' !== $lesson->post_status || ! $course_id ) {
		return new WP_Error( 'pg_courses_not_found', 'Lesson not found.', array( 'status' => 404 ) );
	}
	$enrolment = prospergenics_courses_get_enrolment( $user_id, $course_id );
	if ( ! $enrolment || ! prospergenics_courses_can_view_lesson( $lesson, $user_id ) ) {
		return new WP_Error( 'pg_courses_not_enrolled', 'Enrol in the course first.', array( 'status' => 403 ) );
	}
	$quiz = prospergenics_courses_get_quiz( $lesson->ID );
	if ( ! $quiz ) {
		return new WP_Error( 'pg_courses_no_quiz', 'This lesson has no quiz.', array( 'status' => 404 ) );
	}
	$key  = 'pg_courses_quiz_rl_' . (int) $user_id;
	$rate = prospergenics_courses_rate_hit( get_transient( $key ), time(), PROSPERGENICS_COURSES_QUIZ_RATE_LIMIT );
	set_transient( $key, $rate['state'], PROSPERGENICS_COURSES_RATE_WINDOW );
	if ( ! $rate['allowed'] ) {
		return new WP_Error( 'pg_courses_rate_limited', 'Too many attempts. Try again in a minute.', array( 'status' => 429 ) );
	}
	$grade = prospergenics_courses_grade_quiz( $quiz, $answers );
	$all   = prospergenics_courses_quiz_records( $enrolment->ID );
	$all[ (int) $lesson->ID ] = prospergenics_courses_quiz_add_attempt( isset( $all[ (int) $lesson->ID ] ) ? $all[ (int) $lesson->ID ] : null, $grade, time() );
	update_post_meta( $enrolment->ID, '_pg_courses_quiz_attempts', $all );
	do_action( 'prospergenics_courses_quiz_attempted', (int) $user_id, (int) $lesson->ID, $grade );

	$out = $grade + array(
		'attempts'            => $all[ (int) $lesson->ID ]['count'],
		'best'                => $all[ (int) $lesson->ID ]['best'],
		'lesson_completed'    => in_array( (int) $lesson->ID, prospergenics_courses_completed_ids( $user_id, $course_id ), true ),
		'progress'            => prospergenics_courses_progress( $user_id, $course_id ),
		'certificate'         => null,
		'certificate_blocked' => null,
	);
	if ( $grade['passed'] && ! $out['lesson_completed'] ) {
		prospergenics_courses_touch_lesson( $user_id, $lesson ); // answering it means it was opened
		$done = prospergenics_courses_set_lesson_complete( $user_id, $lesson->ID, true );
		if ( is_array( $done ) ) {
			$out['lesson_completed']    = true;
			$out['progress']            = $done['progress'];
			$out['certificate']         = $done['certificate'];
			$out['certificate_blocked'] = $done['certificate_blocked'];
		}
	}
	// Feedback per question: right or wrong and the explanation; the correct answer once passed.
	$out['feedback'] = array();
	foreach ( prospergenics_courses_quiz_ready_questions( $quiz ) as $q ) {
		$item = array( 'id' => $q['id'], 'correct' => $grade['results'][ $q['id'] ], 'explanation' => $q['explanation'] );
		if ( $grade['passed'] ) {
			$item['answer'] = prospergenics_courses_quiz_answer_text( $q );
		}
		$out['feedback'][] = $item;
	}
	unset( $out['answers'] );
	return $out;
}

/** The correct answer of a question as text ("B; D" or the first accepted short answer). */
function prospergenics_courses_quiz_answer_text( array $q ) {
	if ( 'short' === $q['type'] ) {
		return isset( $q['answers'][0] ) ? $q['answers'][0] : '';
	}
	$parts = array();
	foreach ( $q['correct'] as $i ) {
		if ( isset( $q['options'][ $i ] ) ) {
			$parts[] = $q['options'][ $i ];
		}
	}
	return implode( '; ', $parts );
}

/* ------------------------------------------------------------------ meta, REST, form */

add_action( 'init', function () {
	// Never in REST: the questions carry their answers.
	register_post_meta( 'pg_lesson', '_pg_courses_quiz', array(
		'type'              => 'string',
		'single'            => true,
		'show_in_rest'      => false,
		// update_metadata() has already unslashed the value; what this returns is stored as is.
		'sanitize_callback' => function ( $value ) {
			return wp_json_encode( prospergenics_courses_sanitize_quiz( $value ), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		},
		'auth_callback'     => function ( $allowed, $meta_key, $post_id ) {
			return current_user_can( 'edit_post', $post_id );
		},
	) );
}, 6 );

add_action( 'rest_api_init', function () {
	register_rest_route( PROSPERGENICS_COURSES_NS, '/courses/lessons/(?P<id>\d+)/quiz', array(
		'methods'             => 'POST',
		'callback'            => function ( WP_REST_Request $request ) {
			$result = prospergenics_courses_submit_quiz( get_current_user_id(), (int) $request['id'], $request['answers'] );
			return $result;
		},
		'permission_callback' => 'prospergenics_courses_rest_logged_in',
		'args'                => array(
			'id'      => array( 'validate_callback' => function ( $v ) { return is_numeric( $v ) && $v > 0; } ),
			'answers' => array( 'type' => 'object', 'required' => true ),
		),
	) );
} );

// The lesson page form (no JavaScript needed).
add_action( 'admin_post_pg_courses_quiz', function () {
	$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
	check_admin_referer( 'pg_courses_quiz_' . $lesson_id );
	$url     = 'pg_lesson' === get_post_type( $lesson_id ) ? get_permalink( $lesson_id ) : home_url( '/courses/' );
	$answers = array();
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- graded against the stored options; text sanitised in grading
	$posted = isset( $_POST['pgq'] ) && is_array( $_POST['pgq'] ) ? wp_unslash( $_POST['pgq'] ) : array();
	foreach ( $posted as $qid => $value ) {
		$qid = substr( preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $qid ) ), 0, 24 );
		if ( '' !== $qid ) {
			$answers[ $qid ] = is_array( $value ) ? array_map( 'absint', $value ) : sanitize_text_field( (string) $value );
		}
	}
	$result = prospergenics_courses_submit_quiz( get_current_user_id(), $lesson_id, $answers );
	if ( is_wp_error( $result ) ) {
		prospergenics_courses_redirect( $url, 'pg_courses_rate_limited' === $result->get_error_code() ? 'quiz_rate' : 'error' );
	}
	$notice = $result['passed'] ? ( 100 === $result['progress'] ? ( 'unverified' === $result['certificate_blocked'] ? 'quiz_finished_unverified' : 'quiz_finished' ) : 'quiz_passed' ) : 'quiz_failed';
	wp_safe_redirect( add_query_arg( 'pgc', $notice, $url ) . '#pgc-quiz-result' );
	exit;
} );

add_action( 'admin_post_nopriv_pg_courses_quiz', function () {
	$id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
	wp_safe_redirect( wp_login_url( $id ? get_permalink( $id ) : home_url( '/courses/' ) ) );
	exit;
} );

/* ------------------------------------------------------------------ rendering */

/**
 * The quiz section under a lesson: the questions for enrolled learners (with the feedback of
 * their latest attempt), a read-only preview for editors, nothing for lessons without a quiz.
 */
function prospergenics_courses_quiz_html( $lesson, $user_id, $enrolled ) {
	$quiz = prospergenics_courses_get_quiz( $lesson->ID );
	if ( ! $quiz ) {
		return '';
	}
	$questions = prospergenics_courses_quiz_ready_questions( $quiz );
	$record    = $enrolled ? prospergenics_courses_quiz_record( $user_id, $lesson->ID ) : null;
	$last      = $record && ! empty( $record['attempts'] ) ? end( $record['attempts'] ) : null;
	$passed    = $record && ! empty( $record['passed_at'] );
	$n         = count( $questions );

	$html  = '<section id="pgc-quiz" class="pgc-quiz" aria-labelledby="pgc-quiz-title">';
	$html .= '<h2 id="pgc-quiz-title" class="pgc-quiz-title">Check your understanding</h2>';
	$html .= '<p class="pgc-quiz-intro">' . esc_html( sprintf( _n( '%d question.', '%d questions.', $n, 'prospergenics-2026' ), $n ) ) . ' ' . esc_html( sprintf( 'Score %d%% or more to complete this lesson. You can try as often as you like.', $quiz['pass_mark'] ) ) . '</p>';

	if ( $last ) {
		$html .= prospergenics_courses_quiz_result_html( $quiz, $questions, $last, $record, $lesson, $user_id );
	}
	if ( ! $enrolled ) {
		$html .= '<p class="pgc-muted">You are previewing this quiz as an editor. Enrolled learners answer it here; their answers are graded on the server and correct answers are never sent to their browser before they answer.</p>';
		$html .= prospergenics_courses_quiz_form_html( $lesson, $questions, false );
	} elseif ( $last ) {
		$html .= '<details class="pgc-quiz-retry"' . ( $passed ? '' : ' open' ) . '><summary>' . esc_html( $passed ? 'Take the quiz again (your best score is kept)' : 'Try the quiz again' ) . '</summary>';
		$html .= prospergenics_courses_quiz_form_html( $lesson, $questions, true ) . '</details>';
	} else {
		$html .= prospergenics_courses_quiz_form_html( $lesson, $questions, true );
	}
	return $html . '</section>';
}

/** The answer form. $live false = the editor preview (no submit). */
function prospergenics_courses_quiz_form_html( $lesson, array $questions, $live ) {
	$html  = '<form class="pgc-quiz-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$html .= '<input type="hidden" name="action" value="pg_courses_quiz" /><input type="hidden" name="lesson_id" value="' . (int) $lesson->ID . '" />';
	$html .= wp_nonce_field( 'pg_courses_quiz_' . $lesson->ID, '_wpnonce', false, false );
	$html .= '<ol class="pgc-quiz-list">';
	foreach ( $questions as $i => $q ) {
		$base  = 'pgq-' . $lesson->ID . '-' . $q['id'];
		$html .= '<li class="pgc-quiz-q">';
		if ( 'short' === $q['type'] ) {
			$html .= '<label class="pgc-quiz-prompt" for="' . esc_attr( $base ) . '">' . nl2br( esc_html( $q['prompt'] ) ) . '</label>';
			$html .= '<input class="pgc-quiz-input" type="text" id="' . esc_attr( $base ) . '" name="pgq[' . esc_attr( $q['id'] ) . ']" maxlength="200" autocomplete="off" aria-describedby="' . esc_attr( $base ) . '-hint" />';
			$html .= '<p class="pgc-quiz-hint" id="' . esc_attr( $base ) . '-hint">Type your answer. Capital letters and spaces do not matter.</p>';
		} else {
			$multi = 'multiple' === $q['type'];
			$html .= '<fieldset class="pgc-quiz-fieldset"' . ( $multi ? ' aria-describedby="' . esc_attr( $base ) . '-hint"' : '' ) . '><legend class="pgc-quiz-prompt">' . nl2br( esc_html( $q['prompt'] ) ) . '</legend>';
			if ( $multi ) {
				$html .= '<p class="pgc-quiz-hint" id="' . esc_attr( $base ) . '-hint">Select every correct answer.</p>';
			}
			foreach ( $q['options'] as $oi => $opt ) {
				$html .= '<div class="pgc-quiz-option"><input type="' . ( $multi ? 'checkbox' : 'radio' ) . '" id="' . esc_attr( $base . '-' . $oi ) . '" name="pgq[' . esc_attr( $q['id'] ) . ']' . ( $multi ? '[]' : '' ) . '" value="' . (int) $oi . '" /> <label for="' . esc_attr( $base . '-' . $oi ) . '">' . esc_html( $opt ) . '</label></div>';
			}
			$html .= '</fieldset>';
		}
		$html .= '</li>';
	}
	$html .= '</ol>';
	$html .= $live ? '<button type="submit" class="pgc-btn">Submit answers</button>' : '';
	return $html . '</form>';
}

/** The latest attempt: score, pass/fail and, per question, right/wrong with the explanation. */
function prospergenics_courses_quiz_result_html( array $quiz, array $questions, array $last, array $record, $lesson, $user_id ) {
	$passed_now = ! empty( $last['passed'] );
	$html       = '<div id="pgc-quiz-result" class="pgc-quiz-result ' . ( $passed_now ? 'is-passed' : 'is-failed' ) . '" tabindex="-1">';
	$html      .= '<h3 class="pgc-quiz-result-title">' . esc_html( $passed_now ? 'Passed' : 'Not passed yet' ) . ': ' . (int) $last['score'] . '%</h3>';
	$summary    = sprintf( 'Your latest attempt scored %d%% (pass mark %d%%). Attempts: %d. Best score: %d%%.', $last['score'], $quiz['pass_mark'], $record['count'], $record['best'] );
	if ( ! $passed_now && ! empty( $record['passed_at'] ) ) {
		$summary .= ' You passed this quiz before, so the lesson stays complete.';
	} elseif ( ! $passed_now ) {
		$summary .= ' Read the explanations below and try again.';
	}
	$html .= '<p>' . esc_html( $summary ) . '</p>';
	if ( $passed_now ) {
		$course   = get_post( prospergenics_courses_course_of( $lesson ) );
		$progress = prospergenics_courses_progress( $user_id, $course->ID );
		$html    .= '<p class="pgc-done-note">' . prospergenics_courses_icon( 'check' ) . ' This lesson is complete.</p>';
		if ( 100 === $progress ) {
			$cert  = prospergenics_courses_certificate_html( $course, $user_id, $progress, 'pgc-btn pgc-btn--ghost pgc-btn--small', 'Download your certificate (PDF)' );
			$html .= '<div class="pgc-cta-row"><p>You completed the course.</p>' . $cert . '</div>';
		}
	}
	$html .= '<ol class="pgc-quiz-feedback">';
	foreach ( $questions as $q ) {
		$known = array_key_exists( $q['id'], isset( $last['results'] ) ? $last['results'] : array() );
		$ok    = $known && ! empty( $last['results'][ $q['id'] ] );
		$given = $known && isset( $last['answers'][ $q['id'] ] ) ? $last['answers'][ $q['id'] ] : null;
		$html .= '<li class="pgc-quiz-fb ' . ( $ok ? 'is-right' : 'is-wrong' ) . '">';
		$html .= '<p class="pgc-quiz-fb-q">' . nl2br( esc_html( $q['prompt'] ) ) . '</p>';
		$html .= '<p class="pgc-quiz-fb-verdict">' . ( $ok ? prospergenics_courses_icon( 'check' ) . ' <strong>Correct.</strong>' : prospergenics_courses_quiz_icon_cross() . ' <strong>' . ( $known ? 'Not correct.' : 'New question: not answered yet.' ) . '</strong>' );
		$text = prospergenics_courses_quiz_given_text( $q, $given );
		if ( '' !== $text ) {
			$html .= ' Your answer: ' . esc_html( $text ) . '.';
		}
		$html .= '</p>';
		if ( ! $ok && $passed_now ) {
			$html .= '<p class="pgc-quiz-fb-answer">Correct answer: ' . esc_html( prospergenics_courses_quiz_answer_text( $q ) ) . '</p>';
		}
		if ( '' !== $q['explanation'] ) {
			$html .= '<p class="pgc-quiz-fb-why">' . nl2br( esc_html( $q['explanation'] ) ) . '</p>';
		}
		$html .= '</li>';
	}
	return $html . '</ol></div>';
}

/** What the learner answered, as text. */
function prospergenics_courses_quiz_given_text( array $q, $given ) {
	if ( null === $given || array() === $given || '' === $given ) {
		return '';
	}
	if ( 'short' === $q['type'] ) {
		return (string) $given;
	}
	$parts = array();
	foreach ( (array) $given as $i ) {
		if ( isset( $q['options'][ (int) $i ] ) ) {
			$parts[] = $q['options'][ (int) $i ];
		}
	}
	return implode( '; ', $parts );
}

function prospergenics_courses_quiz_icon_cross() {
	return '<svg class="pgc-icon" viewBox="0 0 20 20" width="18" height="18" aria-hidden="true" focusable="false"><path d="M5.5 5.5l9 9M14.5 5.5l-9 9" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"/></svg>';
}

/* ------------------------------------------------------------------ instructors */

/**
 * Quiz results of a course for the instructor dashboard: one row per learner and quiz lesson.
 *
 * @return array<int,array{learner:string,lesson:string,attempts:int,best:int,passed_at:int,last_at:int,last_score:int}>
 */
function prospergenics_courses_quiz_results( $course_id ) {
	$quiz_lessons = array();
	foreach ( prospergenics_courses_flat_lessons( $course_id ) as $l ) {
		if ( prospergenics_courses_lesson_has_quiz( $l->ID ) ) {
			$quiz_lessons[ (int) $l->ID ] = get_the_title( $l );
		}
	}
	if ( ! $quiz_lessons ) {
		return array();
	}
	$rows       = array();
	$enrolments = get_posts( array( 'post_type' => 'pg_enrolment', 'post_status' => 'publish', 'post_parent' => (int) $course_id, 'posts_per_page' => -1, 'suppress_filters' => true ) );
	foreach ( $enrolments as $e ) {
		$user    = get_userdata( (int) $e->post_author );
		$records = prospergenics_courses_quiz_records( $e->ID );
		foreach ( $quiz_lessons as $lid => $title ) {
			$r      = isset( $records[ $lid ] ) && is_array( $records[ $lid ] ) ? $records[ $lid ] : null;
			$last   = $r && ! empty( $r['attempts'] ) ? end( $r['attempts'] ) : null;
			$rows[] = array(
				'learner'    => $user ? $user->display_name : '(deleted account)',
				'lesson'     => $title,
				'attempts'   => $r ? (int) $r['count'] : 0,
				'best'       => $r ? (int) $r['best'] : 0,
				'passed_at'  => $r ? (int) $r['passed_at'] : 0,
				'last_at'    => $last ? (int) $last['at'] : 0,
				'last_score' => $last ? (int) $last['score'] : 0,
			);
		}
	}
	return $rows;
}

/** The dashboard's quiz results block for a list of courses ('' when none has a quiz). */
function prospergenics_courses_quiz_results_html( array $courses ) {
	$html = '';
	foreach ( $courses as $c ) {
		$rows = prospergenics_courses_quiz_results( $c->ID );
		if ( ! $rows ) {
			continue;
		}
		$title = get_the_title( $c );
		$html .= '<details class="pgc-quiz-results"><summary>' . esc_html( $title ? $title : '(untitled)' ) . ' <span class="pgc-module-meta">' . esc_html( sprintf( _n( '%d row', '%d rows', count( $rows ), 'prospergenics-2026' ), count( $rows ) ) ) . '</span></summary>';
		$html .= '<div class="pgc-table-wrap"><table class="pgc-table"><caption class="screen-reader-text">' . esc_html( 'Quiz attempts and best scores per learner: ' . $title ) . '</caption>';
		$html .= '<thead><tr><th scope="col">Learner</th><th scope="col">Lesson</th><th scope="col">Attempts</th><th scope="col">Best score</th><th scope="col">Passed</th><th scope="col">Last attempt</th></tr></thead><tbody>';
		foreach ( $rows as $r ) {
			$html .= '<tr><th scope="row" data-label="Learner">' . esc_html( $r['learner'] ) . '</th>';
			$html .= '<td data-label="Lesson">' . esc_html( $r['lesson'] ) . '</td>';
			$html .= '<td data-label="Attempts">' . (int) $r['attempts'] . '</td>';
			$html .= '<td data-label="Best score">' . ( $r['attempts'] ? (int) $r['best'] . '%' : '–' ) . '</td>';
			$html .= '<td data-label="Passed">' . ( $r['passed_at'] ? esc_html( 'Yes, ' . wp_date( 'j M Y', $r['passed_at'] ) ) : ( $r['attempts'] ? 'Not yet' : 'Not started' ) ) . '</td>';
			$html .= '<td data-label="Last attempt">' . ( $r['last_at'] ? esc_html( wp_date( 'j M Y H:i', $r['last_at'] ) . ' (' . $r['last_score'] . '%)' ) : '–' ) . '</td></tr>';
		}
		$html .= '</tbody></table></div></details>';
	}
	if ( '' === $html ) {
		return '';
	}
	return '<section class="pgc-quiz-results-wrap" aria-labelledby="pgc-quiz-results-title"><h2 id="pgc-quiz-results-title" class="pgc-h2">Quiz results</h2><p class="pgc-muted">Attempts and best scores per learner, for every lesson with a quiz. A lesson with a quiz completes when the learner passes it.</p>' . $html . '</section>';
}

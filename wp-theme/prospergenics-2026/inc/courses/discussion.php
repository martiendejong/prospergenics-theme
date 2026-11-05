<?php
/**
 * Courses track: questions and discussion under lessons (card #6464).
 *
 * Model: WordPress comments on the pg_lesson, comment_type 'pg_question'. A question has
 * comment_parent 0; a reply has the question as its parent (one level: a reply to a reply is
 * attached to the question). The accepted answer is comment meta on the question
 * (_pg_courses_accepted = reply id). Comments, rather than a new post type, because they already
 * carry what a thread needs (parent, author, date, approved/held status that moderation uses to
 * hide a post, wp-admin's comment screen for staff, counts, trash) without a second store.
 *
 * Who: learners enrolled in the course, its instructor and staff, i.e. exactly the people who
 * may open the lesson (prospergenics_courses_can_view_lesson()). Nobody else sees a question,
 * anywhere: the core comment form is closed on lessons, pg_question comments are left out of
 * every comment query (REST /wp/v2/comments, widgets, the core privacy exporter, …) and comment
 * feeds unless this module asks for them, and a single comment over REST is a 404.
 *
 * Spam: nonce, the site's honeypot and form-time token (inc/spam-protection.php), a per-member
 * rate limit, length limits and wp_kses() with a small set of tags.
 * Mail: the course instructor hears about new questions, the asker about replies (through
 * prospergenics_profiles_send_mail(), so failures are logged).
 * Privacy: an exporter and eraser (questions with replies from others are anonymised, the rest
 * deleted); deleting a user erases them too.
 *
 * The lesson template prints the discussion through one hook:
 * do_action( 'prospergenics_courses_lesson_discussion', $lesson, $course ).
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_COURSES_QUESTION_TYPE = 'pg_question';
/** Posts (questions and replies) a member may write per window. */
const PROSPERGENICS_COURSES_DISCUSS_LIMIT  = 8;
const PROSPERGENICS_COURSES_DISCUSS_WINDOW = 600;
/** Length limits in characters of plain text. */
const PROSPERGENICS_COURSES_DISCUSS_MIN_QUESTION = 10;
const PROSPERGENICS_COURSES_DISCUSS_MIN_REPLY    = 2;
const PROSPERGENICS_COURSES_DISCUSS_MAX          = 4000;

/* ------------------------------------------------------------------ pure helpers (unit-tested) */

/** The HTML a discussion post may contain. */
function prospergenics_courses_discussion_allowed_html() {
	return array(
		'a'          => array( 'href' => true ),
		'strong'     => array(),
		'em'         => array(),
		'code'       => array(),
		'br'         => array(),
		'p'          => array(),
		'ul'         => array(),
		'ol'         => array(),
		'li'         => array(),
		'blockquote' => array(),
	);
}

/**
 * Clean a posted body: limited HTML, length checked on the plain text.
 *
 * @return array{body:string,error:string} error: '' | 'short' | 'long'
 */
function prospergenics_courses_discussion_clean( $raw, $is_question ) {
	$body  = trim( wp_kses( str_replace( "\r\n", "\n", (string) $raw ), prospergenics_courses_discussion_allowed_html() ) );
	$plain = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( $body ), ENT_QUOTES, 'UTF-8' ) ) );
	$min   = $is_question ? PROSPERGENICS_COURSES_DISCUSS_MIN_QUESTION : PROSPERGENICS_COURSES_DISCUSS_MIN_REPLY;
	$len   = mb_strlen( $plain );
	if ( $len < $min ) {
		return array( 'body' => $body, 'error' => 'short' );
	}
	if ( $len > PROSPERGENICS_COURSES_DISCUSS_MAX || strlen( $body ) > PROSPERGENICS_COURSES_DISCUSS_MAX * 3 ) {
		return array( 'body' => $body, 'error' => 'long' );
	}
	return array( 'body' => $body, 'error' => '' );
}

/**
 * Bot signals of a submitted form: the honeypot and the signed form time of
 * prospergenics_spam_protection_fields(). '' when it looks like a person.
 */
function prospergenics_courses_discussion_bot_reason( array $post, $now ) {
	if ( ! empty( $post[ PROSPERGENICS_HONEYPOT_FIELD ] ) ) {
		return 'honeypot';
	}
	$time  = isset( $post['pg_form_time'] ) ? (int) $post['pg_form_time'] : 0;
	$token = isset( $post['pg_form_token'] ) ? (string) $post['pg_form_token'] : '';
	if ( ! $time || '' === $token || ! hash_equals( wp_hash( (string) $time ), $token ) ) {
		return 'token';
	}
	if ( $now - $time < PROSPERGENICS_MIN_FILL_SECONDS ) {
		return 'fast';
	}
	if ( $now - $time > PROSPERGENICS_MAX_FORM_AGE ) {
		return 'stale';
	}
	return '';
}

/**
 * Order the replies of a question: the accepted answer first, then oldest first.
 *
 * @param array $replies objects with comment_ID and comment_date_gmt
 */
function prospergenics_courses_discussion_order_replies( array $replies, $accepted_id ) {
	usort( $replies, function ( $a, $b ) use ( $accepted_id ) {
		$ka = array( (int) $a->comment_ID === (int) $accepted_id ? 0 : 1, (string) $a->comment_date_gmt, (int) $a->comment_ID );
		$kb = array( (int) $b->comment_ID === (int) $accepted_id ? 0 : 1, (string) $b->comment_date_gmt, (int) $b->comment_ID );
		return $ka <=> $kb;
	} );
	return $replies;
}

/** The messages after a discussion action, keyed by the ?pgd= value. */
function prospergenics_courses_discussion_notices() {
	return array(
		'asked'      => array( 'ok', 'Your question is posted. The instructor gets an email about it.' ),
		'replied'    => array( 'ok', 'Your reply is posted.' ),
		'accepted'   => array( 'ok', 'The answer is marked as accepted.' ),
		'unaccepted' => array( 'ok', 'The answer is no longer marked as accepted.' ),
		'deleted'    => array( 'ok', 'Your post was deleted.' ),
		'short'      => array( 'error', 'Please write a little more: a question needs at least 10 characters.' ),
		'long'       => array( 'error', 'That is too long: please keep it under 4,000 characters.' ),
		'rate'       => array( 'error', 'You are posting very quickly. Please wait a few minutes and try again.' ),
		'bot'        => array( 'error', 'Your post could not be sent. Please wait a moment and try again.' ),
		'denied'     => array( 'error', 'Only learners in this course and its instructor can take part in this discussion.' ),
		'error'      => array( 'error', 'Something went wrong. Please try again.' ),
	);
}

/* ------------------------------------------------------------------ access */

/** Whether a user may read and write in a lesson's discussion. */
function prospergenics_courses_discussion_can_access( $lesson, $user_id ) {
	$lesson = get_post( $lesson );
	return $user_id && $lesson && 'pg_lesson' === $lesson->post_type && 'publish' === $lesson->post_status
		&& prospergenics_courses_can_view_lesson( $lesson, (int) $user_id );
}

/** Whether a user is the course's instructor or staff (may edit the course), who may accept answers. */
function prospergenics_courses_discussion_is_teacher( $course_id, $user_id ) {
	return $user_id && $course_id && user_can( (int) $user_id, 'edit_post', (int) $course_id );
}

/** A pg_question comment, or null. */
function prospergenics_courses_discussion_get( $comment_id ) {
	$c = get_comment( (int) $comment_id );
	return $c && PROSPERGENICS_COURSES_QUESTION_TYPE === $c->comment_type ? $c : null;
}

/** The link to a post in its lesson. */
function prospergenics_courses_discussion_link( $comment ) {
	return get_permalink( (int) $comment->comment_post_ID ) . '#pgc-q-' . (int) $comment->comment_ID;
}

/* ------------------------------------------------------------------ keep it out of everything else */

// Our own queries set this flag; every other comment query leaves the discussion out.
add_action( 'pre_get_comments', function ( $q ) {
	if ( ! empty( $q->query_vars['pg_courses_discussion'] ) ) {
		return;
	}
	// wp-admin → Comments, for the people who moderate comments.
	if ( is_admin() && ! wp_doing_ajax() && current_user_can( 'moderate_comments' ) ) {
		return;
	}
	$not = (array) $q->query_vars['type__not_in'];
	$not[] = PROSPERGENICS_COURSES_QUESTION_TYPE;
	$q->query_vars['type__not_in'] = array_values( array_unique( array_filter( $not ) ) );
} );

add_filter( 'comment_feed_where', function ( $where ) {
	global $wpdb;
	return $where . $wpdb->prepare( " AND {$wpdb->comments}.comment_type != %s", PROSPERGENICS_COURSES_QUESTION_TYPE );
} );

// /wp/v2/comments/<id> reads the comment directly: answer as if it did not exist.
add_filter( 'rest_prepare_comment', function ( $response, $comment ) {
	if ( PROSPERGENICS_COURSES_QUESTION_TYPE === $comment->comment_type && ! current_user_can( 'moderate_comments' ) ) {
		return new WP_Error( 'rest_comment_invalid_id', 'Invalid comment ID.', array( 'status' => 404 ) );
	}
	return $response;
}, 10, 2 );

// The core comment form never opens on lessons (questions go through this module).
add_filter( 'comments_open', function ( $open, $post_id ) {
	return 'pg_lesson' === get_post_type( $post_id ) ? false : $open;
}, 10, 2 );
add_filter( 'pings_open', function ( $open, $post_id ) {
	return 'pg_lesson' === get_post_type( $post_id ) ? false : $open;
}, 10, 2 );

/* ------------------------------------------------------------------ queries */

/**
 * The discussion of a lesson as the user may see it: questions newest first, each with its
 * replies. Held (hidden by moderation) posts show to their author and to staff (moderate_comments).
 *
 * @return array<int,array{question:WP_Comment,replies:WP_Comment[],accepted:int}>
 */
function prospergenics_courses_discussion_threads( $lesson_id, $user_id ) {
	$staff = $user_id && user_can( (int) $user_id, 'moderate_comments' );
	$base  = array(
		'post_id'               => (int) $lesson_id,
		'type'                  => PROSPERGENICS_COURSES_QUESTION_TYPE,
		'status'                => $staff ? array( 'approve', 'hold' ) : 'approve',
		'pg_courses_discussion' => true,
		'update_comment_meta_cache' => true,
	);
	if ( ! $staff && $user_id ) {
		$base['include_unapproved'] = array( (int) $user_id );
	}
	$questions = get_comments( $base + array( 'parent' => 0, 'orderby' => 'comment_date_gmt', 'order' => 'DESC', 'number' => 100 ) );
	if ( ! $questions ) {
		return array();
	}
	$ids     = array_map( function ( $c ) { return (int) $c->comment_ID; }, $questions );
	$replies = get_comments( $base + array( 'parent__in' => $ids, 'orderby' => 'comment_date_gmt', 'order' => 'ASC', 'number' => 1000 ) );
	$by      = array();
	foreach ( $replies as $r ) {
		$by[ (int) $r->comment_parent ][] = $r;
	}
	$threads = array();
	foreach ( $questions as $q ) {
		$accepted  = (int) get_comment_meta( $q->comment_ID, '_pg_courses_accepted', true );
		$threads[] = array(
			'question' => $q,
			'replies'  => prospergenics_courses_discussion_order_replies( isset( $by[ (int) $q->comment_ID ] ) ? $by[ (int) $q->comment_ID ] : array(), $accepted ),
			'accepted' => $accepted,
		);
	}
	return $threads;
}

/* ------------------------------------------------------------------ writing */

/**
 * Post a question (parent 0) or a reply.
 *
 * @return int|WP_Error the new comment id
 */
function prospergenics_courses_discussion_post( $user_id, $lesson_id, $parent_id, $raw ) {
	$user   = get_userdata( (int) $user_id );
	$lesson = get_post( (int) $lesson_id );
	if ( ! $user || ! prospergenics_courses_discussion_can_access( $lesson, $user->ID ) ) {
		return new WP_Error( 'denied', 'Not allowed.', array( 'status' => 403 ) );
	}
	$parent_id = (int) $parent_id;
	if ( $parent_id ) {
		$parent = prospergenics_courses_discussion_get( $parent_id );
		if ( ! $parent || (int) $parent->comment_post_ID !== (int) $lesson->ID || '1' !== (string) $parent->comment_approved ) {
			return new WP_Error( 'error', 'The question was not found.', array( 'status' => 404 ) );
		}
		if ( (int) $parent->comment_parent ) { // one level: attach a reply to a reply to the question
			$parent_id = (int) $parent->comment_parent;
		}
	}
	$clean = prospergenics_courses_discussion_clean( $raw, 0 === $parent_id );
	if ( '' !== $clean['error'] ) {
		return new WP_Error( $clean['error'], 'Invalid length.', array( 'status' => 400 ) );
	}
	$key  = 'pg_courses_discuss_rate_' . (int) $user->ID;
	$rate = prospergenics_courses_rate_hit( get_transient( $key ), time(), PROSPERGENICS_COURSES_DISCUSS_LIMIT, PROSPERGENICS_COURSES_DISCUSS_WINDOW );
	set_transient( $key, $rate['state'], PROSPERGENICS_COURSES_DISCUSS_WINDOW );
	if ( ! $rate['allowed'] ) {
		return new WP_Error( 'rate', 'Too many posts.', array( 'status' => 429 ) );
	}
	$id = wp_insert_comment( array(
		'comment_post_ID'      => (int) $lesson->ID,
		'comment_parent'       => $parent_id,
		'comment_type'         => PROSPERGENICS_COURSES_QUESTION_TYPE,
		'comment_content'      => $clean['body'],
		'comment_approved'     => 1,
		'user_id'              => (int) $user->ID,
		'comment_author'       => $user->display_name,
		'comment_author_email' => $user->user_email,
		'comment_author_url'   => '',
		'comment_author_IP'    => '',
		'comment_agent'        => '',
	) );
	if ( ! $id ) {
		return new WP_Error( 'error', 'Could not save.', array( 'status' => 500 ) );
	}
	do_action( 'prospergenics_courses_discussion_posted', (int) $id, (int) $user->ID, $parent_id );
	return (int) $id;
}

/** Accept a reply as the answer to its question (or clear it with $reply_id 0). */
function prospergenics_courses_discussion_accept( $user_id, $question_id, $reply_id ) {
	$q = prospergenics_courses_discussion_get( $question_id );
	if ( ! $q || (int) $q->comment_parent ) {
		return new WP_Error( 'error', 'Question not found.', array( 'status' => 404 ) );
	}
	if ( ! prospergenics_courses_discussion_is_teacher( prospergenics_courses_course_of( (int) $q->comment_post_ID ), $user_id ) ) {
		return new WP_Error( 'denied', 'Only the instructor can accept an answer.', array( 'status' => 403 ) );
	}
	if ( ! $reply_id ) {
		delete_comment_meta( $q->comment_ID, '_pg_courses_accepted' );
		return 0;
	}
	$r = prospergenics_courses_discussion_get( $reply_id );
	if ( ! $r || (int) $r->comment_parent !== (int) $q->comment_ID || '1' !== (string) $r->comment_approved ) {
		return new WP_Error( 'error', 'Reply not found.', array( 'status' => 404 ) );
	}
	update_comment_meta( $q->comment_ID, '_pg_courses_accepted', (int) $r->comment_ID );
	return (int) $r->comment_ID;
}

/* ------------------------------------------------------------------ mail */

/** A short plain-text excerpt of a post for an email. */
function prospergenics_courses_discussion_excerpt( $body, $max = 300 ) {
	$plain = trim( preg_replace( '/\s+/u', ' ', html_entity_decode( strip_tags( (string) $body ), ENT_QUOTES, 'UTF-8' ) ) );
	return mb_strlen( $plain ) > $max ? rtrim( mb_substr( $plain, 0, $max - 1 ) ) . '…' : $plain;
}

add_action( 'prospergenics_courses_discussion_posted', function ( $comment_id, $user_id, $parent_id ) {
	$c      = get_comment( $comment_id );
	$author = get_userdata( $user_id );
	$lesson = $c ? get_post( (int) $c->comment_post_ID ) : null;
	$course = $lesson ? get_post( prospergenics_courses_course_of( $lesson ) ) : null;
	if ( ! $c || ! $author || ! $lesson || ! $course || ! function_exists( 'prospergenics_profiles_send_mail' ) ) {
		return;
	}
	$lesson_title = wp_strip_all_tags( get_the_title( $lesson ) );
	$course_title = wp_strip_all_tags( get_the_title( $course ) );
	if ( ! $parent_id ) {
		$to = get_userdata( (int) $course->post_author );
		if ( ! $to || (int) $to->ID === (int) $author->ID ) {
			return;
		}
		$body = sprintf(
			"Hi %s,\n\n%s asked a question under the lesson \"%s\" of your course \"%s\":\n\n%s\n\nAnswer it here: %s\n\nYou can mark the reply that answers it best as the accepted answer.\n",
			$to->display_name, $author->display_name, $lesson_title, $course_title,
			prospergenics_courses_discussion_excerpt( $c->comment_content ), prospergenics_courses_discussion_link( $c )
		);
		prospergenics_profiles_send_mail( $to->user_email, sprintf( 'New question in %s', $course_title ), $body, 'discussion_question', $to->ID );
		return;
	}
	$q  = get_comment( $parent_id );
	$to = $q ? get_userdata( (int) $q->user_id ) : null;
	if ( ! $to || (int) $to->ID === (int) $author->ID ) {
		return;
	}
	$body = sprintf(
		"Hi %s,\n\n%s replied to your question under the lesson \"%s\" (%s):\n\n%s\n\nRead the discussion: %s\n",
		$to->display_name, $author->display_name, $lesson_title, $course_title,
		prospergenics_courses_discussion_excerpt( $c->comment_content ), prospergenics_courses_discussion_link( $c )
	);
	prospergenics_profiles_send_mail( $to->user_email, sprintf( 'New reply to your question in %s', $course_title ), $body, 'discussion_reply', $to->ID );
}, 10, 3 );

/* ------------------------------------------------------------------ form handlers (admin-post) */

function prospergenics_courses_discussion_back( $lesson_id, $notice, $anchor = 'pgc-discussion' ) {
	$url = 'pg_lesson' === get_post_type( $lesson_id ) ? get_permalink( $lesson_id ) : home_url( '/courses/' );
	wp_safe_redirect( add_query_arg( 'pgd', $notice, $url ) . '#' . $anchor );
	exit;
}

foreach ( array( 'pg_courses_discuss', 'pg_courses_discuss_accept', 'pg_courses_discuss_delete' ) as $prospergenics_courses_action ) {
	add_action( 'admin_post_nopriv_' . $prospergenics_courses_action, function () {
		$id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
		wp_safe_redirect( wp_login_url( 'pg_lesson' === get_post_type( $id ) ? get_permalink( $id ) : home_url( '/courses/' ) ) );
		exit;
	} );
}
unset( $prospergenics_courses_action );

add_action( 'admin_post_pg_courses_discuss', function () {
	$lesson_id = isset( $_POST['lesson_id'] ) ? absint( $_POST['lesson_id'] ) : 0;
	$parent_id = isset( $_POST['parent'] ) ? absint( $_POST['parent'] ) : 0;
	check_admin_referer( 'pg_courses_discuss_' . $lesson_id );
	$user_id = get_current_user_id();
	if ( ! prospergenics_courses_discussion_can_access( $lesson_id, $user_id ) ) {
		prospergenics_courses_discussion_back( $lesson_id, 'denied' );
	}
	$raw = isset( $_POST['body'] ) ? wp_unslash( (string) $_POST['body'] ) : '';
	$bot = prospergenics_courses_discussion_bot_reason( wp_unslash( $_POST ), time() );
	if ( '' !== $bot ) {
		prospergenics_courses_discussion_keep_draft( $user_id, $lesson_id, $parent_id, $raw );
		prospergenics_courses_discussion_back( $lesson_id, 'bot', $parent_id ? 'pgc-q-' . $parent_id : 'pgc-discussion' );
	}
	$id = prospergenics_courses_discussion_post( $user_id, $lesson_id, $parent_id, $raw );
	if ( is_wp_error( $id ) ) {
		prospergenics_courses_discussion_keep_draft( $user_id, $lesson_id, $parent_id, $raw );
		$code = $id->get_error_code();
		if ( 'short' === $code && $parent_id ) {
			$code = 'error';
		}
		prospergenics_courses_discussion_back( $lesson_id, isset( prospergenics_courses_discussion_notices()[ $code ] ) ? $code : 'error', $parent_id ? 'pgc-q-' . $parent_id : 'pgc-discussion' );
	}
	prospergenics_courses_discussion_back( $lesson_id, $parent_id ? 'replied' : 'asked', 'pgc-q-' . $id );
} );

add_action( 'admin_post_pg_courses_discuss_accept', function () {
	$question_id = isset( $_POST['question_id'] ) ? absint( $_POST['question_id'] ) : 0;
	$reply_id    = isset( $_POST['reply_id'] ) ? absint( $_POST['reply_id'] ) : 0;
	check_admin_referer( 'pg_courses_discuss_accept_' . $question_id );
	$q      = prospergenics_courses_discussion_get( $question_id );
	$result = prospergenics_courses_discussion_accept( get_current_user_id(), $question_id, $reply_id );
	$lesson = $q ? (int) $q->comment_post_ID : 0;
	if ( is_wp_error( $result ) ) {
		prospergenics_courses_discussion_back( $lesson, 'denied' === $result->get_error_code() ? 'denied' : 'error' );
	}
	prospergenics_courses_discussion_back( $lesson, $reply_id ? 'accepted' : 'unaccepted', 'pgc-q-' . $question_id );
} );

add_action( 'admin_post_pg_courses_discuss_delete', function () {
	$comment_id = isset( $_POST['comment_id'] ) ? absint( $_POST['comment_id'] ) : 0;
	check_admin_referer( 'pg_courses_discuss_delete_' . $comment_id );
	$c = prospergenics_courses_discussion_get( $comment_id );
	if ( ! $c || ! get_current_user_id() || (int) $c->user_id !== get_current_user_id() ) {
		prospergenics_courses_discussion_back( $c ? (int) $c->comment_post_ID : 0, 'error' );
	}
	wp_trash_comment( $c->comment_ID );
	prospergenics_courses_discussion_back( (int) $c->comment_post_ID, 'deleted' );
} );

/** Keep what a member typed when the post was refused, so the form can give it back (10 minutes). */
function prospergenics_courses_discussion_keep_draft( $user_id, $lesson_id, $parent_id, $raw ) {
	if ( $user_id ) {
		set_transient( 'pg_courses_discuss_draft_' . (int) $user_id, array( 'lesson' => (int) $lesson_id, 'parent' => (int) $parent_id, 'body' => mb_substr( (string) $raw, 0, PROSPERGENICS_COURSES_DISCUSS_MAX * 2 ) ), 600 );
	}
}

/** The kept draft for this form ('' when none); it is used once. */
function prospergenics_courses_discussion_take_draft( $user_id, $lesson_id, $parent_id ) {
	$d = $user_id ? get_transient( 'pg_courses_discuss_draft_' . (int) $user_id ) : false;
	if ( ! is_array( $d ) || (int) $d['lesson'] !== (int) $lesson_id || (int) $d['parent'] !== (int) $parent_id ) {
		return '';
	}
	delete_transient( 'pg_courses_discuss_draft_' . (int) $user_id );
	return (string) $d['body'];
}

/* ------------------------------------------------------------------ markup */

/** The post form (question when $parent_id is 0, else a reply). */
function prospergenics_courses_discussion_form( $lesson_id, $parent_id, $user_id ) {
	$id    = 'pgc-d-body-' . (int) $parent_id;
	$draft = prospergenics_courses_discussion_take_draft( $user_id, $lesson_id, $parent_id );
	$label = $parent_id ? 'Your reply' : 'Your question';
	$html  = '<form class="pgc-d-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	$html .= '<input type="hidden" name="action" value="pg_courses_discuss" />';
	$html .= '<input type="hidden" name="lesson_id" value="' . (int) $lesson_id . '" />';
	$html .= '<input type="hidden" name="parent" value="' . (int) $parent_id . '" />';
	$html .= wp_nonce_field( 'pg_courses_discuss_' . (int) $lesson_id, '_wpnonce', false, false );
	$html .= prospergenics_spam_protection_fields();
	$html .= '<div class="pgc-field"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label>';
	$html .= '<textarea id="' . esc_attr( $id ) . '" name="body" rows="' . ( $parent_id ? 3 : 4 ) . '" maxlength="' . (int) PROSPERGENICS_COURSES_DISCUSS_MAX . '" required aria-describedby="' . esc_attr( $id ) . '-hint">' . esc_textarea( $draft ) . '</textarea>';
	$html .= '<p class="pgc-d-hint" id="' . esc_attr( $id ) . '-hint">' . ( $parent_id ? 'Plain text; links are fine.' : 'What are you stuck on? Plain text; links are fine. Only this course’s learners and instructor can read it.' ) . '</p></div>';
	$html .= '<button type="submit" class="pgc-btn pgc-btn--small">' . ( $parent_id ? 'Post reply' : 'Post question' ) . '</button>';
	return $html . '</form>';
}

/** One post (question or reply). */
function prospergenics_courses_discussion_post_html( $c, $ctx, $is_question, $accepted = 0, $question_id = 0 ) {
	$uid      = (int) $c->user_id;
	$author   = get_userdata( $uid );
	$name     = $author ? $author->display_name : 'Former member';
	$held     = '1' !== (string) $c->comment_approved;
	$is_acc   = ! $is_question && (int) $c->comment_ID === (int) $accepted;
	$badge    = '';
	if ( $uid && $uid === (int) $ctx['course']->post_author ) {
		$badge = '<span class="pgc-d-badge">Instructor</span>';
	} elseif ( $uid && prospergenics_courses_discussion_is_teacher( $ctx['course']->ID, $uid ) ) {
		$badge = '<span class="pgc-d-badge">ProsperGenics team</span>';
	}
	$html  = '<article class="pgc-d-post' . ( $is_acc ? ' is-accepted' : '' ) . ( $held ? ' is-held' : '' ) . '" id="pgc-q-' . (int) $c->comment_ID . '">';
	$html .= '<header class="pgc-d-head">' . ( $author ? prospergenics_2026_avatar_html( $uid, 36, 'pgc-d-avatar' ) : '' );
	$html .= '<span class="pgc-d-name">' . esc_html( $name ) . '</span>' . $badge;
	$html .= '<time class="pgc-d-time" datetime="' . esc_attr( mysql2date( 'c', $c->comment_date_gmt . ' +0000', false ) ) . '">' . esc_html( sprintf( '%s ago', human_time_diff( strtotime( $c->comment_date_gmt . ' UTC' ) ) ) ) . '</time></header>';
	if ( $is_acc ) {
		$html .= '<p class="pgc-d-accepted">' . prospergenics_courses_icon( 'check' ) . ' Accepted answer</p>';
	}
	if ( $held ) {
		$html .= '<p class="pgc-d-held">' . ( $uid === (int) $ctx['user'] ? 'Hidden by the ProsperGenics team: only you and the team can see this.' : 'Hidden by moderation: only its author and the team see it.' ) . '</p>';
	}
	$html .= '<div class="pgc-d-body">' . wpautop( wp_kses( $c->comment_content, prospergenics_courses_discussion_allowed_html() ) ) . '</div>';

	$actions = '';
	if ( ! $is_question && $ctx['teacher'] && ! $held ) {
		$actions .= prospergenics_courses_action_form(
			'pg_courses_discuss_accept', 'pg_courses_discuss_accept_' . (int) $question_id,
			array( 'question_id' => (int) $question_id, 'reply_id' => $is_acc ? 0 : (int) $c->comment_ID, 'lesson_id' => (int) $c->comment_post_ID ),
			$is_acc ? 'Unmark accepted answer' : 'Mark as accepted answer', 'pgc-btn pgc-btn--ghost pgc-btn--small', '', 'reply by ' . $name
		);
	}
	if ( $uid && $uid === (int) $ctx['user'] ) {
		$actions .= prospergenics_courses_action_form(
			'pg_courses_discuss_delete', 'pg_courses_discuss_delete_' . (int) $c->comment_ID,
			array( 'comment_id' => (int) $c->comment_ID, 'lesson_id' => (int) $c->comment_post_ID ),
			'Delete', 'pgc-btn pgc-btn--ghost pgc-btn--small', '', $is_question ? 'your question' : 'your reply'
		);
	} elseif ( ! $held && function_exists( 'prospergenics_profiles_report_html' ) ) {
		$actions .= prospergenics_profiles_report_html( 'question', (int) $c->comment_ID );
	}
	if ( '' !== $actions ) {
		$html .= '<div class="pgc-d-actions">' . $actions . '</div>';
	}
	return $html . '</article>';
}

/** The whole discussion section of a lesson ('' for anyone who may not see it). */
function prospergenics_courses_discussion_html( $lesson, $course ) {
	$user_id = get_current_user_id();
	if ( ! $course || ! prospergenics_courses_discussion_can_access( $lesson, $user_id ) ) {
		return '';
	}
	$ctx = array( 'user' => $user_id, 'course' => $course, 'teacher' => prospergenics_courses_discussion_is_teacher( $course->ID, $user_id ) );
	// phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted value
	$key     = isset( $_GET['pgd'] ) ? sanitize_key( wp_unslash( $_GET['pgd'] ) ) : '';
	$notices = prospergenics_courses_discussion_notices();
	$threads = prospergenics_courses_discussion_threads( $lesson->ID, $user_id );

	$html  = '<section class="pgc-section pgc-discussion" id="pgc-discussion" aria-labelledby="pgc-discussion-h">';
	$html .= '<h2 id="pgc-discussion-h">Questions and discussion</h2>';
	$html .= '<p class="pgc-muted">Stuck on something? Ask the instructor and the other learners. Only people in this course see this discussion.</p>';
	if ( isset( $notices[ $key ] ) ) {
		$html .= '<div class="pgc-notice' . ( 'error' === $notices[ $key ][0] ? ' pgc-notice--error' : '' ) . '" role="' . ( 'error' === $notices[ $key ][0] ? 'alert' : 'status' ) . '">' . esc_html( $notices[ $key ][1] ) . '</div>';
	}
	$html .= '<details class="pgc-d-ask"' . ( $threads ? '' : ' open' ) . '><summary class="pgc-btn pgc-btn--small">Ask a question</summary>' . prospergenics_courses_discussion_form( $lesson->ID, 0, $user_id ) . '</details>';
	if ( ! $threads ) {
		$html .= '<p class="pgc-d-empty">No questions yet. Be the first to ask.</p>';
	} else {
		$html .= '<p class="pgc-d-count">' . esc_html( sprintf( 1 === count( $threads ) ? '%d question' : '%d questions', count( $threads ) ) ) . '</p>';
		$html .= '<ol class="pgc-d-list">';
		foreach ( $threads as $t ) {
			$q     = $t['question'];
			$html .= '<li class="pgc-d-thread">' . prospergenics_courses_discussion_post_html( $q, $ctx, true );
			if ( $t['replies'] ) {
				$html .= '<ol class="pgc-d-replies" aria-label="' . esc_attr( sprintf( 1 === count( $t['replies'] ) ? '%d reply' : '%d replies', count( $t['replies'] ) ) ) . '">';
				foreach ( $t['replies'] as $r ) {
					$html .= '<li>' . prospergenics_courses_discussion_post_html( $r, $ctx, false, $t['accepted'], (int) $q->comment_ID ) . '</li>';
				}
				$html .= '</ol>';
			}
			if ( '1' === (string) $q->comment_approved ) {
				$html .= '<details class="pgc-d-reply"><summary class="pgc-d-reply-toggle">Reply<span class="screen-reader-text"> to ' . esc_html( get_comment_author( $q ) ) . '</span></summary>' . prospergenics_courses_discussion_form( $lesson->ID, (int) $q->comment_ID, $user_id ) . '</details>';
			}
			$html .= '</li>';
		}
		$html .= '</ol>';
	}
	return $html . '</section>';
}

add_action( 'prospergenics_courses_lesson_discussion', function ( $lesson, $course ) {
	echo prospergenics_courses_discussion_html( $lesson, $course ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped while built
}, 10, 2 );

/* ------------------------------------------------------------------ personal data */

/** A member's discussion posts, also hidden and deleted (trash) ones. @return WP_Comment[] */
function prospergenics_courses_discussion_user_posts( $user_id ) {
	return get_comments( array(
		'user_id'               => (int) $user_id,
		'type'                  => PROSPERGENICS_COURSES_QUESTION_TYPE,
		'status'                => array( 'approve', 'hold', 'trash', 'spam' ),
		'pg_courses_discussion' => true,
		'number'                => 0,
	) );
}

function prospergenics_courses_discussion_exporter( $email, $page = 1 ) {
	$user  = get_user_by( 'email', $email );
	$items = array();
	foreach ( $user ? prospergenics_courses_discussion_user_posts( $user->ID ) : array() as $c ) {
		$lesson   = get_post( (int) $c->comment_post_ID );
		$course   = $lesson ? get_post( prospergenics_courses_course_of( $lesson ) ) : null;
		$question = (int) $c->comment_parent ? get_comment( (int) $c->comment_parent ) : null;
		$data     = array(
			array( 'name' => 'Type', 'value' => $question ? 'Reply' : 'Question' ),
			array( 'name' => 'Course', 'value' => $course ? get_the_title( $course ) : '(deleted course)' ),
			array( 'name' => 'Lesson', 'value' => $lesson ? get_the_title( $lesson ) : '(deleted lesson)' ),
			array( 'name' => 'Link', 'value' => $lesson ? prospergenics_courses_discussion_link( $c ) : '' ),
			array( 'name' => 'Posted', 'value' => $c->comment_date_gmt . ' UTC' ),
			array( 'name' => 'Text', 'value' => wp_strip_all_tags( $c->comment_content ) ),
			array( 'name' => 'Status', 'value' => '1' === (string) $c->comment_approved ? 'visible' : ( 'trash' === $c->comment_approved ? 'deleted' : 'hidden by moderation' ) ),
		);
		if ( $question && (int) get_comment_meta( $question->comment_ID, '_pg_courses_accepted', true ) === (int) $c->comment_ID ) {
			$data[] = array( 'name' => 'Accepted answer', 'value' => 'yes' );
		}
		$items[] = array(
			'group_id'          => 'prospergenics-discussion',
			'group_label'       => 'Lesson questions and replies',
			'group_description' => 'What you wrote in the discussions under course lessons.',
			'item_id'           => 'pg-question-' . (int) $c->comment_ID,
			'data'              => $data,
		);
	}
	return array( 'data' => $items, 'done' => true );
}

/**
 * Erase a member's discussion posts. A question others replied to stays, anonymised and with its
 * text removed, so their answers keep their place; everything else is deleted.
 *
 * @return array{removed:int,retained:int}
 */
function prospergenics_courses_discussion_erase( $user_id ) {
	$removed  = 0;
	$retained = 0;
	foreach ( prospergenics_courses_discussion_user_posts( $user_id ) as $c ) {
		$others = ! (int) $c->comment_parent ? get_comments( array(
			'parent' => (int) $c->comment_ID, 'type' => PROSPERGENICS_COURSES_QUESTION_TYPE, 'status' => array( 'approve', 'hold' ),
			'author__not_in' => array( (int) $user_id ), 'count' => true, 'pg_courses_discussion' => true,
		) ) : 0;
		if ( $others ) {
			wp_update_comment( array(
				'comment_ID'           => (int) $c->comment_ID,
				'user_id'              => 0,
				'comment_author'       => 'Former member',
				'comment_author_email' => '',
				'comment_author_IP'    => '',
				'comment_agent'        => '',
				'comment_content'      => '<p>(This question was removed by its author.)</p>',
			) );
			$retained++;
		} elseif ( wp_delete_comment( (int) $c->comment_ID, true ) ) {
			$removed++;
		}
	}
	return array( 'removed' => $removed, 'retained' => $retained );
}

function prospergenics_courses_discussion_eraser( $email, $page = 1 ) {
	$user = get_user_by( 'email', $email );
	$n    = $user ? prospergenics_courses_discussion_erase( $user->ID ) : array( 'removed' => 0, 'retained' => 0 );
	return array(
		'items_removed'  => ( $n['removed'] + $n['retained'] ) > 0,
		'items_retained' => false,
		'messages'       => $n['retained'] ? array( sprintf( '%d question(s) with replies from others were kept without name or text, so the answers stay readable.', $n['retained'] ) ) : array(),
		'done'           => true,
	);
}

add_filter( 'wp_privacy_personal_data_exporters', function ( $exporters ) {
	$exporters['prospergenics-discussion'] = array( 'exporter_friendly_name' => 'ProsperGenics lesson discussions', 'callback' => 'prospergenics_courses_discussion_exporter' );
	return $exporters;
} );

add_filter( 'wp_privacy_personal_data_erasers', function ( $erasers ) {
	$erasers['prospergenics-discussion'] = array( 'eraser_friendly_name' => 'ProsperGenics lesson discussions', 'callback' => 'prospergenics_courses_discussion_eraser' );
	return $erasers;
} );

// A deleted account takes its discussion posts with it (comments are not removed by core).
add_action( 'delete_user', function ( $user_id ) {
	prospergenics_courses_discussion_erase( $user_id );
}, 5 );

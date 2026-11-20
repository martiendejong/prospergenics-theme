<?php
/**
 * Articles: "My articles" for authors at /articles/mine/ (card #6368, and #6369's "author sees
 * total claps on their dashboard").
 *
 * A front-end page rather than only the wp-admin list, because the platform's members are not
 * WordPress administrators: they get one page in the site's own look with their drafts, pending,
 * scheduled and published articles (filterable), reading time and claps per article and in
 * total, and the actions the card asks for. Writing, autosave (every 30 s), publishing and
 * scheduling (published by WP-Cron) happen in the front-end editor at /articles/write/
 * (editor.php, #6459), which the page links to; the block editor in wp-admin still works. The wp-admin list (Articles > All articles) shows the same claps column.
 *
 * Members who cannot write yet get the "Apply to write" form here instead (audit ARC-8,
 * inc/profiles/applications.php).
 *
 * Actions (POST to admin-post.php, each with its own nonce and capability check):
 *   pg_articles_unpublish  published or scheduled -> draft     needs edit_post
 *   pg_articles_publish    draft/pending/scheduled -> live now  needs edit_post and publish_post
 *   pg_articles_send_back  pending -> draft with a required note for the author (card #6457)
 *                          needs edit_post and edit_others_pg_articles (the staff who review);
 *                          the note is kept on the article and goes into the author's "back in
 *                          your drafts" email (review.php); the author sees it here on that draft
 *   All return to /articles/mine/, or to /manage/articles/ when sent from the staff review queue.
 */

defined( 'ABSPATH' ) || exit;

/** Post meta of the last "Send back" (card #6457): the note, who wrote it (user ID), when (timestamp). */
const PROSPERGENICS_ARTICLES_NOTE_META    = '_pg_articles_review_note';
const PROSPERGENICS_ARTICLES_NOTE_BY_META = '_pg_articles_review_note_by';
const PROSPERGENICS_ARTICLES_NOTE_AT_META = '_pg_articles_review_note_at';

/** The longest note kept, in characters (the textarea's maxlength too). */
const PROSPERGENICS_ARTICLES_NOTE_MAX = 2000;

add_action( 'init', function () {
    add_rewrite_tag( '%pg_articles_mine%', '1' );
    add_rewrite_rule( '^articles/mine/?$', 'index.php?pg_articles_mine=1', 'top' );
}, 6 );

function prospergenics_articles_is_mine_page() {
    return '1' === (string) get_query_var( 'pg_articles_mine' );
}

function prospergenics_articles_mine_url( $args = array() ) {
    return add_query_arg( $args, home_url( '/articles/mine/' ) );
}

/**
 * Status filters on the page: key => [label, post statuses]. Editors (who may edit others'
 * articles) also get "Waiting for review": everyone's pending articles (audit ARC-9).
 */
function prospergenics_articles_mine_filters() {
    $filters = array(
        'all'       => array( 'All', array( 'draft', 'pending', 'future', 'publish' ) ),
        'draft'     => array( 'Drafts', array( 'draft', 'pending' ) ),
        'future'    => array( 'Scheduled', array( 'future' ) ),
        'publish'   => array( 'Published', array( 'publish' ) ),
    );
    if ( current_user_can( 'edit_others_pg_articles' ) ) {
        $filters['review'] = array( 'Waiting for review', array( 'pending' ) );
    }
    return $filters;
}

/**
 * The review queue (audit ARC-9): everyone's pending articles, the one waiting longest first.
 * WP_Query arguments, shared by "Waiting for review" here and the staff area (/manage/articles/).
 */
function prospergenics_articles_review_queue_args( $paged = 1, $per_page = 20 ) {
    return array(
        'post_type'      => 'pg_article',
        'post_status'    => 'pending',
        'posts_per_page' => (int) $per_page,
        'paged'          => max( 1, (int) $paged ),
        'orderby'        => 'modified',
        'order'          => 'ASC',
    );
}

/** The number of articles waiting for review (core's per-status COUNT, cached by WordPress). */
function prospergenics_articles_review_count() {
    $counts = wp_count_posts( 'pg_article' );
    return isset( $counts->pending ) ? (int) $counts->pending : 0;
}

/** Whether a filter lists everyone's articles (the review queue) rather than the reader's own. */
function prospergenics_articles_mine_filter_is_queue( $filter ) {
    return 'review' === $filter;
}

/** The selected filter key from the request. */
function prospergenics_articles_mine_filter() {
    $f = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all';
    return array_key_exists( $f, prospergenics_articles_mine_filters() ) ? $f : 'all';
}

/** Human status label. */
function prospergenics_articles_status_label( $status ) {
    $labels = array(
        'draft'   => 'Draft',
        'pending' => 'Pending review',
        'future'  => 'Scheduled',
        'publish' => 'Published',
    );
    return isset( $labels[ $status ] ) ? $labels[ $status ] : ucfirst( $status );
}

add_action( 'template_redirect', function () {
    if ( ! prospergenics_articles_is_mine_page() ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( prospergenics_articles_mine_url() ) );
        exit;
    }
    global $wp_query;
    $wp_query->is_home = false;
    $wp_query->is_404  = false;
    status_header( 200 );
    nocache_headers();
    add_filter( 'pre_get_document_title', function () { return 'My articles – ProsperGenics'; } );
    add_filter( 'wp_robots', 'wp_robots_no_robots' );
    include __DIR__ . '/views/mine.php';
    exit;
}, 5 );

/**
 * Where an action returns: /articles/mine/ with $args, or the staff review queue
 * (/manage/articles/, inc/manage/) when the form was sent from there.
 */
function prospergenics_articles_action_redirect( array $args ) {
    $from = isset( $_POST['pg_from'] ) ? sanitize_key( wp_unslash( $_POST['pg_from'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification -- checked before
    if ( 'manage' === $from && function_exists( 'prospergenics_manage_url' ) ) {
        // the notice, and which article it is about (a refused note), never the /articles/mine/ filter
        return prospergenics_manage_url( 'articles', array_intersect_key( $args, array( 'done' => 1, 'article' => 1 ) ) );
    }
    return prospergenics_articles_mine_url( $args );
}

/**
 * Validate an action request; returns the post or dies.
 *
 * @param string|string[] $caps Meta capabilities the user needs for this post, all of them.
 */
function prospergenics_articles_action_post( $action, $caps ) {
    $post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
    check_admin_referer( $action . '_' . $post_id );
    $post = get_post( $post_id );
    $ok   = $post && 'pg_article' === $post->post_type;
    foreach ( (array) $caps as $cap ) {
        $ok = $ok && current_user_can( $cap, $post_id );
    }
    if ( ! $ok ) {
        wp_die( esc_html__( 'Sorry, you are not allowed to do that.' ), 403 );
    }
    return $post;
}

add_action( 'admin_post_pg_articles_unpublish', function () {
    $post = prospergenics_articles_action_post( 'pg_articles_unpublish', 'edit_post' );
    $done = 0;
    if ( in_array( $post->post_status, array( 'publish', 'future' ), true ) ) {
        $done = (int) ! is_wp_error( wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ), true ) );
    }
    wp_safe_redirect( prospergenics_articles_action_redirect( array( 'status' => 'draft', 'done' => $done ? 'unpublished' : 'failed' ) ) );
    exit;
} );

add_action( 'admin_post_pg_articles_publish', function () {
    // publish_post alone maps to publish_pg_articles, which authors hold for everyone's articles:
    // also ask for edit_post, so only who may edit this article (its author, editors) publishes it
    $post = prospergenics_articles_action_post( 'pg_articles_publish', array( 'edit_post', 'publish_post' ) );
    $done = 0;
    if ( in_array( $post->post_status, array( 'draft', 'pending', 'future' ), true ) ) {
        $now  = current_time( 'mysql' );
        $done = (int) ! is_wp_error( wp_update_post( array(
            'ID'            => $post->ID,
            'post_status'   => 'publish',
            'post_date'     => $now,
            'post_date_gmt' => get_gmt_from_date( $now ),
            'edit_date'     => true,
        ), true ) );
    }
    wp_safe_redirect( prospergenics_articles_action_redirect( array( 'status' => 'publish', 'done' => $done ? 'published' : 'failed' ) ) );
    exit;
} );

/* ───────────────────── Send back with a note (card #6457) ───────────────────── */

/**
 * A reviewer's note from the form: plain text (markup and control characters removed, line breaks
 * kept as \n), at most PROSPERGENICS_ARTICLES_NOTE_MAX characters. '' when nothing is left: such a
 * note is refused.
 */
function prospergenics_articles_clean_note( $raw ) {
    $note = str_replace( array( "\r\n", "\r" ), "\n", sanitize_textarea_field( (string) $raw ) );
    $note = trim( preg_replace( "/\n{3,}/", "\n\n", preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $note ) ) );
    return function_exists( 'mb_substr' ) ? mb_substr( $note, 0, PROSPERGENICS_ARTICLES_NOTE_MAX ) : substr( $note, 0, PROSPERGENICS_ARTICLES_NOTE_MAX );
}

/** The last "Send back" note on an article: array{note:string,by:int,at:int}, or null. */
function prospergenics_articles_note( $post_id ) {
    $note = (string) get_post_meta( (int) $post_id, PROSPERGENICS_ARTICLES_NOTE_META, true );
    if ( '' === $note ) {
        return null;
    }
    return array(
        'note' => $note,
        'by'   => (int) get_post_meta( (int) $post_id, PROSPERGENICS_ARTICLES_NOTE_BY_META, true ),
        'at'   => (int) get_post_meta( (int) $post_id, PROSPERGENICS_ARTICLES_NOTE_AT_META, true ),
    );
}

/** Forget the note (the article was submitted again or published: review.php). */
function prospergenics_articles_clear_note( $post_id ) {
    foreach ( array( PROSPERGENICS_ARTICLES_NOTE_META, PROSPERGENICS_ARTICLES_NOTE_BY_META, PROSPERGENICS_ARTICLES_NOTE_AT_META ) as $key ) {
        delete_post_meta( (int) $post_id, $key );
    }
}

/** Whether the current user may send this article back: it waits for review, they review (staff) and may edit it. */
function prospergenics_articles_can_send_back( WP_Post $post ) {
    return 'pg_article' === $post->post_type && 'pending' === $post->post_status
        && current_user_can( 'edit_others_pg_articles' ) && current_user_can( 'edit_post', $post->ID );
}

/**
 * Move a pending article back to its author's drafts with the reviewer's note. The note is stored
 * first, so the "returned" email that the status change sends (review.php) carries it.
 *
 * @return bool Whether the article is a draft now.
 */
function prospergenics_articles_send_back( WP_Post $post, $note, $by ) {
    update_post_meta( $post->ID, PROSPERGENICS_ARTICLES_NOTE_META, wp_slash( (string) $note ) ); // update_post_meta() unslashes
    update_post_meta( $post->ID, PROSPERGENICS_ARTICLES_NOTE_BY_META, (int) $by );
    update_post_meta( $post->ID, PROSPERGENICS_ARTICLES_NOTE_AT_META, time() );
    $result = wp_update_post( array( 'ID' => $post->ID, 'post_status' => 'draft' ), true );
    if ( is_wp_error( $result ) || ! $result ) {
        prospergenics_articles_clear_note( $post->ID );
        return false;
    }
    return true;
}

add_action( 'admin_post_pg_articles_send_back', function () {
    // edit_post alone would let an author send back any article they may edit: reviewing is for the
    // staff who edit others' articles (editors and administrators, the /manage/articles/ queue)
    $post = prospergenics_articles_action_post( 'pg_articles_send_back', array( 'edit_post', 'edit_others_pg_articles' ) );
    if ( 'pending' !== $post->post_status ) {
        wp_safe_redirect( prospergenics_articles_action_redirect( array( 'status' => 'review', 'done' => 'not_pending' ) ) );
        exit;
    }
    $note = prospergenics_articles_clean_note( isset( $_POST['note'] ) ? wp_unslash( $_POST['note'] ) : '' ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitize_textarea_field() inside
    if ( '' === $note ) {
        // refused: back to the form, open, the error on the field, which gets the focus (autofocus
        // scrolls it into view; no #fragment, browsers skip autofocus when the URL has one)
        wp_safe_redirect( prospergenics_articles_action_redirect( array( 'status' => 'review', 'done' => 'note_missing', 'article' => $post->ID ) ) );
        exit;
    }
    $done = prospergenics_articles_send_back( $post, $note, get_current_user_id() ) ? 'sent_back' : 'failed';
    wp_safe_redirect( prospergenics_articles_action_redirect( array( 'status' => 'review', 'done' => $done ) ) );
    exit;
} );

/**
 * The "Send back" disclosure of the review queue, on /articles/mine/ (Waiting for review) and
 * /manage/articles/: a button that opens a form with a required note for the author (a real label,
 * a hint, 44 px targets). After an empty note was refused ($error) it is open, and the textarea is
 * marked invalid, tied to the error text (aria-describedby) and focused.
 *
 * @param string $prefix 'pga' (My articles) or 'pgm' (the staff area): the class and id prefix.
 * @param string $from   'manage' to come back to /manage/articles/ after the action.
 * @param bool   $error  Whether an empty note for this article was just refused.
 */
function prospergenics_articles_send_back_form( WP_Post $post, $prefix, $from = '', $error = false ) {
    $id     = (int) $post->ID;
    $p      = sanitize_key( $prefix );
    $title  = wp_strip_all_tags( get_the_title( $post ) );
    $title  = '' === $title ? '(untitled)' : $title;
    $author = get_userdata( (int) $post->post_author );
    $who    = $author ? $author->display_name : 'the author';
    $field  = $p . '-note-' . $id;
    $sr     = '<span class="screen-reader-text"> “' . esc_html( $title ) . '”</span>';
    $html   = '<details class="' . $p . '-sendback"' . ( $error ? ' open' : '' ) . '>'
        . '<summary class="' . $p . '-btn ' . $p . '-btn-ghost">Send back…' . $sr . '</summary>'
        . '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">'
        . '<input type="hidden" name="action" value="pg_articles_send_back" />'
        . '<input type="hidden" name="post_id" value="' . esc_attr( $id ) . '" />'
        . ( 'manage' === $from ? '<input type="hidden" name="pg_from" value="manage" />' : '' )
        . wp_nonce_field( 'pg_articles_send_back_' . $id, '_wpnonce', true, false )
        . '<p class="field">'
        . '<label for="' . esc_attr( $field ) . '">What should the author change?</label>'
        . '<span class="' . $p . '-hint" id="' . esc_attr( $field . '-hint' ) . '">Required. ' . esc_html( $who ) . ' gets this note by email and finds the article back in their drafts.</span>'
        . '<textarea id="' . esc_attr( $field ) . '" name="note" rows="4" maxlength="' . (int) PROSPERGENICS_ARTICLES_NOTE_MAX . '" required aria-describedby="' . esc_attr( $field . '-hint' . ( $error ? ' ' . $field . '-err' : '' ) ) . '"' . ( $error ? ' aria-invalid="true" autofocus' : '' ) . '></textarea>'
        . ( $error ? '<span class="' . $p . '-field-error" id="' . esc_attr( $field . '-err' ) . '">Write what the author should change: an article is only sent back with a note.</span>' : '' )
        . '</p>'
        . '<button type="submit" class="' . $p . '-btn">Send back to ' . esc_html( $who ) . $sr . '</button>'
        . '</form></details>';
    return $html;
}

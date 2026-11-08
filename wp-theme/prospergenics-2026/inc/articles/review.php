<?php
/**
 * Articles: the review loop (audit ARC-9).
 *
 *   draft → pending            the editors get an email with a link to review it
 *   pending → publish/future   the author gets "your article is published" (or scheduled)
 *   pending → draft            the author gets "your article is back in your drafts"
 *
 * Nobody is told about their own action (an author publishing their own pending article gets no
 * mail). The editors are every editor and administrator who may edit the article; the list is
 * filterable (prospergenics_articles_review_recipients). Mail goes through the platform mail helper
 * (inc/profiles/mail.php), so a failure is logged and shown to administrators. Editors see the
 * queue on /articles/mine/ under "Waiting for review" (mine.php).
 */

defined( 'ABSPATH' ) || exit;

/**
 * What a status change means for the review loop: submitted|published|scheduled|returned, or ''.
 */
function prospergenics_articles_review_event( $old, $new ) {
    if ( $old === $new ) {
        return '';
    }
    if ( 'pending' === $new && in_array( $old, array( 'new', 'auto-draft', 'draft' ), true ) ) {
        return 'submitted';
    }
    if ( 'pending' !== $old ) {
        return '';
    }
    $events = array( 'publish' => 'published', 'future' => 'scheduled', 'draft' => 'returned' );
    return isset( $events[ $new ] ) ? $events[ $new ] : '';
}

/**
 * Who reviews a submitted article: editors and administrators who may edit it, never its author or
 * the person who made the change.
 *
 * @return int[] user IDs
 */
function prospergenics_articles_review_recipients( WP_Post $post, $actor_id = 0 ) {
    $ids = get_users( array( 'role__in' => array( 'editor', 'administrator' ), 'fields' => 'ID', 'number' => 50 ) );
    $ids = (array) apply_filters( 'prospergenics_articles_review_recipients', array_map( 'intval', $ids ), $post );
    $out = array();
    foreach ( array_unique( array_map( 'intval', $ids ) ) as $id ) {
        if ( $id && $id !== (int) $post->post_author && $id !== (int) $actor_id && user_can( $id, 'edit_post', $post->ID ) ) {
            $out[] = $id;
        }
    }
    return $out;
}

/** The plain-text mail for an event: array{subject:string,body:string}. */
function prospergenics_articles_review_mail( $event, $title, $name, $links ) {
    $title = '' !== trim( (string) $title ) ? (string) $title : '(untitled)';
    switch ( $event ) {
        case 'submitted':
            return array(
                'subject' => sprintf( 'Article waiting for review: %s', $title ),
                'body'    => sprintf( "Hi,\n\n%s submitted \"%s\" for review on ProsperGenics.\n\nReview and publish it (or send it back to draft): %s\nEverything waiting for review: %s\n", $name, $title, $links['edit'], $links['queue'] ),
            );
        case 'published':
            return array(
                'subject' => sprintf( 'Your article is published: %s', $title ),
                'body'    => sprintf( "Hi %s,\n\nGood news: \"%s\" is published on ProsperGenics.\n\nRead it: %s\nYour articles: %s\n", $name, $title, $links['view'], $links['mine'] ),
            );
        case 'scheduled':
            return array(
                'subject' => sprintf( 'Your article is scheduled: %s', $title ),
                'body'    => sprintf( "Hi %s,\n\n\"%s\" was approved and will be published on %s.\n\nYour articles: %s\n", $name, $title, $links['date'], $links['mine'] ),
            );
        case 'returned':
            return array(
                'subject' => sprintf( 'Your article is back in your drafts: %s', $title ),
                'body'    => sprintf( "Hi %s,\n\nAn editor moved \"%s\" back to your drafts, so it is not published yet. Have a look, make changes and submit it for review again when it is ready.\n\nEdit it: %s\nYour articles: %s\n", $name, $title, $links['edit'], $links['mine'] ),
            );
    }
    return array( 'subject' => '', 'body' => '' );
}

function prospergenics_articles_review_transition( $new, $old, $post ) {
    if ( ! $post instanceof WP_Post || 'pg_article' !== $post->post_type || wp_is_post_revision( $post ) ) {
        return;
    }
    $event = prospergenics_articles_review_event( $old, $new );
    if ( '' === $event ) {
        return;
    }
    $actor  = get_current_user_id();
    $author = get_userdata( (int) $post->post_author );
    $links  = array(
        'edit'  => admin_url( 'post.php?post=' . (int) $post->ID . '&action=edit' ),
        'queue' => prospergenics_articles_mine_url( array( 'status' => 'review' ) ),
        'mine'  => prospergenics_articles_mine_url(),
        'view'  => get_permalink( $post ),
        'date'  => get_post_time( 'j F Y, H:i', false, $post ),
    );
    if ( 'submitted' === $event ) {
        $mail = prospergenics_articles_review_mail( $event, $post->post_title, $author ? $author->display_name : 'A member', $links );
        foreach ( prospergenics_articles_review_recipients( $post, $actor ) as $id ) {
            $editor = get_userdata( $id );
            if ( $editor ) {
                prospergenics_profiles_send_mail( $editor->user_email, wp_specialchars_decode( $mail['subject'] ), $mail['body'], 'article_review', $editor->ID );
            }
        }
        return;
    }
    // published, scheduled or returned: tell the author, unless they did it themselves
    if ( ! $author || (int) $author->ID === (int) $actor ) {
        return;
    }
    $mail = prospergenics_articles_review_mail( $event, $post->post_title, $author->display_name, $links );
    prospergenics_profiles_send_mail( $author->user_email, wp_specialchars_decode( $mail['subject'] ), $mail['body'], 'article_' . $event, $author->ID );
}
add_action( 'transition_post_status', 'prospergenics_articles_review_transition', 10, 3 );

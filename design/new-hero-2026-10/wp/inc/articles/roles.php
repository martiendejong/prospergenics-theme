<?php
/**
 * Articles: who may write (audit ARC-8).
 *
 * pg_article has its own capability type (edit_pg_articles, publish_pg_articles, …), so a member
 * can be allowed to write articles without the blog's post capabilities. Everyone with a core post
 * capability gets the matching article one through a user_has_cap filter, so administrators,
 * editors, authors and contributors keep exactly what they had. The pg_writer role holds only:
 *
 *   read, edit_pg_articles, delete_pg_articles   write their own drafts and submit them for review
 *   upload_files                                  a cover image and images in the text
 *
 * A writer cannot publish, edit someone else's article, touch blog posts or comments, read contact
 * messages (edit_others_posts, inc/contact-messages-store.php) or profile entries (edit_users,
 * inc/profiles/data.php), and the media library shows them their own uploads only. Members become
 * writers when an administrator approves their "Apply to write" request (inc/profiles/applications.php).
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_ARTICLES_ROLES_VERSION = '1';

/** Article capability => the core post capability that grants it. */
function prospergenics_articles_cap_map() {
    return array(
        'edit_pg_articles'             => 'edit_posts',
        'edit_others_pg_articles'      => 'edit_others_posts',
        'edit_private_pg_articles'     => 'edit_private_posts',
        'edit_published_pg_articles'   => 'edit_published_posts',
        'publish_pg_articles'          => 'publish_posts',
        'read_private_pg_articles'     => 'read_private_posts',
        'delete_pg_articles'           => 'delete_posts',
        'delete_private_pg_articles'   => 'delete_private_posts',
        'delete_published_pg_articles' => 'delete_published_posts',
        'delete_others_pg_articles'    => 'delete_others_posts',
    );
}

/** Give a user the article capabilities their post capabilities stand for. */
function prospergenics_articles_grant_caps( $allcaps ) {
    foreach ( prospergenics_articles_cap_map() as $article_cap => $post_cap ) {
        if ( ! empty( $allcaps[ $post_cap ] ) ) {
            $allcaps[ $article_cap ] = true;
        }
    }
    return $allcaps;
}
add_filter( 'user_has_cap', 'prospergenics_articles_grant_caps' );

/** The capabilities of the pg_writer role. */
function prospergenics_articles_writer_caps() {
    return array(
        'read'               => true,
        'edit_pg_articles'   => true,
        'delete_pg_articles' => true,
        'upload_files'       => true,
    );
}

// Roles live in the database: (re)create pg_writer once per version, not on every request.
add_action( 'init', function () {
    if ( PROSPERGENICS_ARTICLES_ROLES_VERSION === get_option( '_pg_articles_roles_version' ) && get_role( 'pg_writer' ) ) {
        return;
    }
    remove_role( 'pg_writer' );
    add_role( 'pg_writer', 'Writer', prospergenics_articles_writer_caps() );
    update_option( '_pg_articles_roles_version', PROSPERGENICS_ARTICLES_ROLES_VERSION, true );
}, 4 );

/**
 * Whether a user's media library is their own uploads only: they may upload but have no core post
 * rights (writers).
 */
function prospergenics_articles_own_media_only( $user_id = 0 ) {
    $user_id = $user_id ? (int) $user_id : get_current_user_id();
    return $user_id > 0 && user_can( $user_id, 'upload_files' ) && ! user_can( $user_id, 'edit_posts' );
}

// The media modal (admin-ajax), the block editor's media tab (REST) and Media → Library.
add_filter( 'ajax_query_attachments_args', function ( $query ) {
    if ( prospergenics_articles_own_media_only() ) {
        $query['author'] = get_current_user_id();
    }
    return $query;
} );
add_filter( 'rest_attachment_query', function ( $args ) {
    if ( prospergenics_articles_own_media_only() ) {
        unset( $args['author__not_in'] );
        $args['author__in'] = array( get_current_user_id() );
    }
    return $args;
} );
add_action( 'pre_get_posts', function ( $query ) {
    if ( is_admin() && $query->is_main_query() && 'attachment' === $query->get( 'post_type' ) && prospergenics_articles_own_media_only() ) {
        $query->set( 'author', get_current_user_id() );
    }
} );

/**
 * Writers may edit (alt text, caption) and delete their own uploads, nobody else's: attachments use
 * the core post capabilities, which writers do not have.
 */
add_filter( 'map_meta_cap', function ( $caps, $cap, $user_id, $args ) {
    if ( ! in_array( $cap, array( 'edit_post', 'delete_post' ), true ) || empty( $args[0] ) ) {
        return $caps;
    }
    $post = get_post( (int) $args[0] );
    if ( ! $post || 'attachment' !== $post->post_type || ! prospergenics_articles_own_media_only( $user_id ) ) {
        return $caps;
    }
    return (int) $post->post_author === (int) $user_id ? array( 'upload_files' ) : array( 'do_not_allow' );
}, 10, 4 );

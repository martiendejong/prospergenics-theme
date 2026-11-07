<?php
/**
 * Articles: the front-end article editor at /articles/write/ (card #6459).
 *
 * Members write in the site's own look instead of the wp-admin block editor: title, cover image,
 * rich text (headings, lists, links, images, code), category, tags, series and excerpt, with
 * autosave every 30 seconds, preview, and "Submit for review" or "Publish / Schedule" depending
 * on the role. /articles/write/?id=N edits an existing article.
 *
 * Who can do what (all checked on the server, the page only hides buttons):
 *   save / submit   anyone with edit_pg_articles (contributors, writers, authors, editors);
 *                   an existing article also needs edit_post for that article. "Submit" puts a
 *                   draft in 'pending' review (review.php then emails the editors).
 *   publish / schedule   publish_pg_articles (authors, editors, administrators) on top of the above.
 *
 * Two REST routes (namespace prospergenics/v1, cookie + X-WP-Nonce):
 *   POST articles/editor/save    id, title, content, excerpt, category, tags, series, cover, action, date
 *   POST articles/editor/upload  file (an image of at most 5 MB)
 *
 * Output is sanitised with wp_kses_post before it is stored.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_ARTICLES_EDITOR_MAX_IMAGE   = 5242880; // 5 MB
const PROSPERGENICS_ARTICLES_EDITOR_AUTOSAVE_MS = 30000;
const PROSPERGENICS_ARTICLES_EDITOR_MAX_TAGS    = 10;

add_action( 'init', function () {
    add_rewrite_tag( '%pg_articles_write%', '1' );
    add_rewrite_rule( '^articles/write/?$', 'index.php?pg_articles_write=1', 'top' );
}, 6 );

function prospergenics_articles_is_write_page() {
    return '1' === (string) get_query_var( 'pg_articles_write' );
}

function prospergenics_articles_write_url( $post_id = 0 ) {
    return $post_id ? add_query_arg( 'id', (int) $post_id, home_url( '/articles/write/' ) ) : home_url( '/articles/write/' );
}

/** The image types an article may contain (no SVG: it can carry script). */
function prospergenics_articles_editor_image_mimes() {
    return array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
    );
}

/**
 * Check an upload before it is handled: returns an error message, or '' when it is fine.
 *
 * @param int    $size  Bytes.
 * @param string $name  Original file name.
 */
function prospergenics_articles_editor_image_error( $size, $name ) {
    $size = (int) $size;
    if ( $size <= 0 ) {
        return 'No image was received.';
    }
    if ( $size > PROSPERGENICS_ARTICLES_EDITOR_MAX_IMAGE ) {
        return 'The image is larger than 5 MB. Please choose a smaller one.';
    }
    $ext = strtolower( pathinfo( (string) $name, PATHINFO_EXTENSION ) );
    foreach ( array_keys( prospergenics_articles_editor_image_mimes() ) as $exts ) {
        if ( in_array( $ext, explode( '|', $exts ), true ) ) {
            return '';
        }
    }
    return 'Only JPG, PNG, GIF or WebP images can be uploaded.';
}

/** Sanitise the article body: what wp_kses_post allows, nothing else. */
function prospergenics_articles_editor_clean_content( $html ) {
    return wp_kses_post( (string) $html );
}

/** Excerpt: plain text, at most 300 characters. */
function prospergenics_articles_editor_clean_excerpt( $text ) {
    $text = sanitize_textarea_field( (string) $text );
    return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, 300 ) : substr( $text, 0, 300 );
}

/** Tags from a comma separated string: trimmed, unique, at most 10 of at most 40 characters. */
function prospergenics_articles_editor_clean_tags( $csv ) {
    $out = array();
    foreach ( explode( ',', (string) $csv ) as $tag ) {
        $tag = trim( sanitize_text_field( $tag ) );
        $tag = function_exists( 'mb_substr' ) ? mb_substr( $tag, 0, 40 ) : substr( $tag, 0, 40 );
        if ( '' !== $tag && ! in_array( strtolower( $tag ), array_map( 'strtolower', $out ), true ) ) {
            $out[] = $tag;
        }
    }
    return array_slice( $out, 0, PROSPERGENICS_ARTICLES_EDITOR_MAX_TAGS );
}

/**
 * The status an action leads to, or false when it is not allowed.
 *
 *   save      keeps the status (a new article is a draft)
 *   submit    draft -> pending review (a published or scheduled article stays as it is)
 *   publish   -> publish   only for who may publish
 *   schedule  -> future    only for who may publish
 */
function prospergenics_articles_editor_target_status( $action, $current, $can_publish ) {
    $known   = array( 'draft', 'pending', 'future', 'publish' );
    $current = in_array( $current, $known, true ) ? $current : 'draft';
    switch ( $action ) {
        case 'save':
            return $current;
        case 'submit':
            return in_array( $current, array( 'publish', 'future' ), true ) ? $current : 'pending';
        case 'publish':
            return $can_publish ? 'publish' : false;
        case 'schedule':
            return $can_publish ? 'future' : false;
    }
    return false;
}

/**
 * Parse the schedule field (a "YYYY-MM-DDTHH:MM" value in the site's time zone). Returns the
 * local "Y-m-d H:i:s" string, or '' when it is not a valid date.
 */
function prospergenics_articles_editor_parse_date( $value ) {
    if ( ! preg_match( '/^(\d{4})-(\d{2})-(\d{2})[T ](\d{2}):(\d{2})$/', trim( (string) $value ), $m ) || ! checkdate( (int) $m[2], (int) $m[3], (int) $m[1] ) ) {
        return '';
    }
    if ( (int) $m[4] > 23 || (int) $m[5] > 59 ) {
        return '';
    }
    return sprintf( '%s-%s-%s %s:%s:00', $m[1], $m[2], $m[3], $m[4], $m[5] );
}

/** The access check for both routes: a logged-in user who may write articles. */
function prospergenics_articles_editor_permission() {
    return is_user_logged_in() && current_user_can( 'edit_pg_articles' );
}

add_action( 'rest_api_init', function () {
    register_rest_route( 'prospergenics/v1', '/articles/editor/save', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'prospergenics_articles_editor_rest_save',
        'permission_callback' => 'prospergenics_articles_editor_permission',
    ) );
    register_rest_route( 'prospergenics/v1', '/articles/editor/upload', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'prospergenics_articles_editor_rest_upload',
        'permission_callback' => 'prospergenics_articles_editor_permission',
    ) );
} );

function prospergenics_articles_editor_error( $message, $status = 400 ) {
    return new WP_Error( 'pg_editor', $message, array( 'status' => $status ) );
}

/** Save, submit, publish or schedule an article. */
function prospergenics_articles_editor_rest_save( WP_REST_Request $req ) {
    $id     = absint( $req->get_param( 'id' ) );
    $action = sanitize_key( (string) $req->get_param( 'action' ) );
    $existing = null;
    if ( $id ) {
        $existing = get_post( $id );
        if ( ! $existing || 'pg_article' !== $existing->post_type || ! current_user_can( 'edit_post', $id ) ) {
            return prospergenics_articles_editor_error( 'You cannot edit this article.', 403 );
        }
    }
    $current = $existing ? $existing->post_status : 'draft';
    $status  = prospergenics_articles_editor_target_status( $action ? $action : 'save', $current, current_user_can( 'publish_pg_articles' ) && ( ! $id || current_user_can( 'publish_post', $id ) ) );
    if ( false === $status ) {
        return prospergenics_articles_editor_error( 'You are not allowed to publish. Submit the article for review instead.', 403 );
    }

    $title   = sanitize_text_field( (string) $req->get_param( 'title' ) );
    $content = prospergenics_articles_editor_clean_content( $req->get_param( 'content' ) );
    $excerpt = prospergenics_articles_editor_clean_excerpt( $req->get_param( 'excerpt' ) );

    if ( in_array( $action, array( 'submit', 'publish', 'schedule' ), true ) ) {
        if ( '' === $title ) {
            return prospergenics_articles_editor_error( 'Give the article a title first.' );
        }
        if ( prospergenics_articles_word_count( $content ) < 1 && false === stripos( $content, '<img' ) ) {
            return prospergenics_articles_editor_error( 'The article is still empty.' );
        }
    }

    $data = array(
        'post_type'    => 'pg_article',
        'post_title'   => $title,
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'post_status'  => $status,
    );
    if ( $id ) {
        $data['ID'] = $id;
    } else {
        $data['post_author'] = get_current_user_id();
    }

    if ( 'future' === $status && 'schedule' === $action ) {
        $local = prospergenics_articles_editor_parse_date( $req->get_param( 'date' ) );
        $gmt   = $local ? get_gmt_from_date( $local ) : '';
        if ( '' === $gmt || strtotime( $gmt . ' UTC' ) <= time() + 60 ) {
            return prospergenics_articles_editor_error( 'Pick a date and time in the future to schedule the article.' );
        }
        $data['post_date']     = $local;
        $data['post_date_gmt'] = $gmt;
        $data['edit_date']     = true;
    } elseif ( 'publish' === $status && 'publish' === $action && 'publish' !== $current ) {
        $now                   = current_time( 'mysql' );
        $data['post_date']     = $now;
        $data['post_date_gmt'] = get_gmt_from_date( $now );
        $data['edit_date']     = true;
    }

    $post_id = $id ? wp_update_post( wp_slash( $data ), true ) : wp_insert_post( wp_slash( $data ), true );
    if ( is_wp_error( $post_id ) || ! $post_id ) {
        return prospergenics_articles_editor_error( 'The article could not be saved. Please try again.', 500 );
    }

    // category (one), tags (up to 10, new ones are created), series (one, new ones are created)
    $cat = absint( $req->get_param( 'category' ) );
    wp_set_object_terms( $post_id, ( $cat && term_exists( $cat, 'pg_article_category' ) ) ? array( $cat ) : array(), 'pg_article_category' );
    wp_set_object_terms( $post_id, prospergenics_articles_editor_clean_tags( $req->get_param( 'tags' ) ), 'pg_article_tag' );
    $series = trim( sanitize_text_field( (string) $req->get_param( 'series' ) ) );
    wp_set_object_terms( $post_id, '' === $series ? array() : array( function_exists( 'mb_substr' ) ? mb_substr( $series, 0, 60 ) : substr( $series, 0, 60 ) ), 'pg_series' );

    // cover image: an image attachment the user may use (their own upload, or any they may edit)
    $cover = absint( $req->get_param( 'cover' ) );
    if ( $cover && wp_attachment_is_image( $cover ) && current_user_can( 'edit_post', $cover ) ) {
        set_post_thumbnail( $post_id, $cover );
    } else {
        delete_post_thumbnail( $post_id );
    }

    $saved = get_post( $post_id );
    $done  = array( 'submit' => 'submitted', 'publish' => 'published', 'schedule' => 'scheduled' );
    $resp  = array(
        'id'           => (int) $post_id,
        'status'       => $saved->post_status,
        'status_label' => prospergenics_articles_status_label( $saved->post_status ),
        'saved_at'     => current_time( 'H:i' ),
        'edit_url'     => prospergenics_articles_write_url( $post_id ),
        'preview'      => 'publish' === $saved->post_status ? get_permalink( $saved ) : get_preview_post_link( $saved ),
        'redirect'     => isset( $done[ $action ] ) ? prospergenics_articles_mine_url( array( 'done' => $done[ $action ] ) ) : '',
    );
    return rest_ensure_response( $resp );
}

/** Upload one image (cover or inside the text): at most 5 MB, JPG/PNG/GIF/WebP, the user's own. */
function prospergenics_articles_editor_rest_upload( WP_REST_Request $req ) {
    $files = $req->get_file_params();
    if ( empty( $files['file'] ) || ! is_array( $files['file'] ) || ! empty( $files['file']['error'] ) ) {
        $code = ! empty( $files['file']['error'] ) ? (int) $files['file']['error'] : 0;
        return prospergenics_articles_editor_error( in_array( $code, array( 1, 2 ), true ) ? 'The image is larger than 5 MB. Please choose a smaller one.' : 'No image was received.' );
    }
    $err = prospergenics_articles_editor_image_error( $files['file']['size'], $files['file']['name'] );
    if ( '' !== $err ) {
        return prospergenics_articles_editor_error( $err );
    }
    if ( ! current_user_can( 'upload_files' ) ) {
        return prospergenics_articles_editor_error( 'You may not upload files.', 403 );
    }
    // the real content must be an image of an allowed type, whatever the extension says
    $check = wp_check_filetype_and_ext( $files['file']['tmp_name'], $files['file']['name'], prospergenics_articles_editor_image_mimes() );
    if ( empty( $check['type'] ) || 0 !== strpos( $check['type'], 'image/' ) || ! @getimagesize( $files['file']['tmp_name'] ) ) {
        return prospergenics_articles_editor_error( 'Only JPG, PNG, GIF or WebP images can be uploaded.' );
    }
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    $_FILES['file'] = $files['file'];
    $att = media_handle_upload( 'file', 0, array(), array( 'test_form' => false, 'mimes' => prospergenics_articles_editor_image_mimes() ) );
    if ( is_wp_error( $att ) ) {
        return prospergenics_articles_editor_error( 'The image could not be uploaded. Please try another one.', 500 );
    }
    $size = wp_get_attachment_image_src( $att, 'large' );
    return rest_ensure_response( array(
        'id'    => (int) $att,
        'url'   => $size ? $size[0] : wp_get_attachment_url( $att ),
        'thumb' => wp_get_attachment_image_url( $att, 'medium' ),
    ) );
}

add_action( 'template_redirect', function () {
    if ( ! prospergenics_articles_is_write_page() ) {
        return;
    }
    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( prospergenics_articles_write_url() ) );
        exit;
    }
    global $wp_query;
    $wp_query->is_home = false;
    $wp_query->is_404  = false;
    status_header( 200 );
    nocache_headers();
    add_filter( 'pre_get_document_title', function () { return 'Write an article – ProsperGenics'; } );
    add_filter( 'wp_robots', 'wp_robots_no_robots' );
    include __DIR__ . '/views/write.php';
    exit;
}, 5 );

add_action( 'wp_enqueue_scripts', function () {
    if ( ! prospergenics_articles_is_write_page() ) {
        return;
    }
    wp_enqueue_script( 'prospergenics-article-editor', get_template_directory_uri() . '/assets/platform/article-editor.js', array(), PROSPERGENICS_ARTICLES_VERSION, true );
    wp_localize_script( 'prospergenics-article-editor', 'pgWrite', array(
        'saveUrl'     => esc_url_raw( rest_url( 'prospergenics/v1/articles/editor/save' ) ),
        'uploadUrl'   => esc_url_raw( rest_url( 'prospergenics/v1/articles/editor/upload' ) ),
        'nonce'       => wp_create_nonce( 'wp_rest' ),
        'autosaveMs'  => PROSPERGENICS_ARTICLES_EDITOR_AUTOSAVE_MS,
        'maxImage'    => PROSPERGENICS_ARTICLES_EDITOR_MAX_IMAGE,
    ) );
} );

<?php
/**
 * Prospergenics 2026 - intentionally minimal.
 * Templates are self-contained (inline CSS/JS) to preserve the interactive
 * front-end exactly as designed; nothing is enqueued here.
 */
add_action( 'after_setup_theme', function () {
    add_theme_support( 'automatic-feed-links' );
} );
// The templates carry their own <title>; keep WP/plugins from printing a second one.
remove_action( 'wp_head', '_wp_render_title_tag', 1 );

<?php
/* Fallback for any non-page request: send visitors to the front page. */
if ( ! is_front_page() ) {
    wp_safe_redirect( home_url( '/' ) );
    exit;
}
get_template_part( 'front-page' );

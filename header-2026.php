<?php
/**
 * Header for the 2026 design templates — deliberately stripped.
 *
 * The 2026 design (design/new-hero-2026-10) is a self-contained landing page: it
 * carries no site nav and no site footer of its own, and its CSS resets `*` and
 * styles `body` directly. So this part prints only the document shell plus the
 * two hooks that matter — wp_head() and wp_body_open() — and none of the legacy
 * site-header markup in header.php.
 *
 * No <title> here on purpose: the theme's own SEO layer owns it
 * (prospergenics_front_page_document_title / prospergenics_front_page_title_override
 * plus Yoast), and printing one here would give the page two.
 *
 * @package Prospergenics
 */

?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>

<a class="skip-link screen-reader-text" href="#main-content">
    <?php esc_html_e( 'Skip to content', 'prospergenics' ); ?>
</a>

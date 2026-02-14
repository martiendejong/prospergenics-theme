<?php
/**
 * Prospergenics Theme Functions
 *
 * @package Prospergenics
 * @since 1.0.0
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Theme Setup
 */
function prospergenics_theme_setup() {
    // Add default posts and comments RSS feed links to head
    add_theme_support( 'automatic-feed-links' );

    // Let WordPress manage the document title
    add_theme_support( 'title-tag' );

    // Enable support for Post Thumbnails
    add_theme_support( 'post-thumbnails' );
    set_post_thumbnail_size( 1200, 600, true );
    add_image_size( 'prospergenics-hero', 1920, 1080, true );
    add_image_size( 'prospergenics-card', 800, 500, true );

    // Register navigation menus
    register_nav_menus( array(
        'primary' => __( 'Primary Menu', 'prospergenics' ),
        'footer'  => __( 'Footer Menu', 'prospergenics' ),
    ) );

    // Switch default core markup to output valid HTML5
    add_theme_support( 'html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
        'script',
        'style',
    ) );

    // Add theme support for selective refresh for widgets
    add_theme_support( 'customize-selective-refresh-widgets' );

    // Add support for editor styles
    add_theme_support( 'editor-styles' );

    // Add support for responsive embeds
    add_theme_support( 'responsive-embeds' );

    // Add support for wide alignment
    add_theme_support( 'align-wide' );
}
add_action( 'after_setup_theme', 'prospergenics_theme_setup' );

/**
 * Set Content Width
 */
function prospergenics_content_width() {
    $GLOBALS['content_width'] = apply_filters( 'prospergenics_content_width', 1200 );
}
add_action( 'after_setup_theme', 'prospergenics_content_width', 0 );

/**
 * Enqueue Scripts and Styles
 */
function prospergenics_scripts() {
    // Main stylesheet
    wp_enqueue_style(
        'prospergenics-style',
        get_stylesheet_uri(),
        array(),
        wp_get_theme()->get( 'Version' )
    );

    // Custom JavaScript
    wp_enqueue_script(
        'prospergenics-scripts',
        get_template_directory_uri() . '/js/scripts.js',
        array(),
        wp_get_theme()->get( 'Version' ),
        true
    );

    // Add inline script for mobile menu toggle
    wp_add_inline_script( 'prospergenics-scripts', '
        document.addEventListener("DOMContentLoaded", function() {
            const menuToggle = document.querySelector(".menu-toggle");
            const navigation = document.querySelector(".main-navigation");

            if (menuToggle && navigation) {
                menuToggle.addEventListener("click", function() {
                    navigation.classList.toggle("active");
                    const expanded = menuToggle.getAttribute("aria-expanded") === "true" || false;
                    menuToggle.setAttribute("aria-expanded", !expanded);
                });

                // Close menu when clicking outside
                document.addEventListener("click", function(event) {
                    if (!navigation.contains(event.target) && !menuToggle.contains(event.target)) {
                        navigation.classList.remove("active");
                        menuToggle.setAttribute("aria-expanded", "false");
                    }
                });

                // Close menu on escape key
                document.addEventListener("keydown", function(event) {
                    if (event.key === "Escape" && navigation.classList.contains("active")) {
                        navigation.classList.remove("active");
                        menuToggle.setAttribute("aria-expanded", "false");
                        menuToggle.focus();
                    }
                });
            }

            // Header scroll effect
            const header = document.querySelector(".site-header");
            if (header) {
                window.addEventListener("scroll", function() {
                    if (window.scrollY > 100) {
                        header.classList.add("scrolled");
                    } else {
                        header.classList.remove("scrolled");
                    }
                });
            }
        });
    ' );

    // Comments reply script
    if ( is_singular() && comments_open() && get_option( 'thread_comments' ) ) {
        wp_enqueue_script( 'comment-reply' );
    }
}
add_action( 'wp_enqueue_scripts', 'prospergenics_scripts' );

/**
 * Register Widget Areas
 */
function prospergenics_widgets_init() {
    // Footer Widget Area 1
    register_sidebar( array(
        'name'          => __( 'Footer Area 1', 'prospergenics' ),
        'id'            => 'footer-1',
        'description'   => __( 'Add widgets for the first footer column', 'prospergenics' ),
        'before_widget' => '<div class="footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    // Footer Widget Area 2
    register_sidebar( array(
        'name'          => __( 'Footer Area 2', 'prospergenics' ),
        'id'            => 'footer-2',
        'description'   => __( 'Add widgets for the second footer column', 'prospergenics' ),
        'before_widget' => '<div class="footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );

    // Footer Widget Area 3
    register_sidebar( array(
        'name'          => __( 'Footer Area 3', 'prospergenics' ),
        'id'            => 'footer-3',
        'description'   => __( 'Add widgets for the third footer column', 'prospergenics' ),
        'before_widget' => '<div class="footer-widget">',
        'after_widget'  => '</div>',
        'before_title'  => '<h3 class="widget-title">',
        'after_title'   => '</h3>',
    ) );
}
add_action( 'widgets_init', 'prospergenics_widgets_init' );

/**
 * Custom Excerpt Length
 */
function prospergenics_excerpt_length( $length ) {
    return 30;
}
add_filter( 'excerpt_length', 'prospergenics_excerpt_length' );

/**
 * Custom Excerpt More
 */
function prospergenics_excerpt_more( $more ) {
    return '...';
}
add_filter( 'excerpt_more', 'prospergenics_excerpt_more' );

/**
 * Customizer Settings
 */
function prospergenics_customize_register( $wp_customize ) {
    // Hero Section
    $wp_customize->add_section( 'prospergenics_hero', array(
        'title'    => __( 'Hero Section', 'prospergenics' ),
        'priority' => 30,
    ) );

    // Hero Headline
    $wp_customize->add_setting( 'prospergenics_hero_headline', array(
        'default'           => 'Building Prosperity Together',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'prospergenics_hero_headline', array(
        'label'   => __( 'Hero Headline', 'prospergenics' ),
        'section' => 'prospergenics_hero',
        'type'    => 'text',
    ) );

    // Hero Tagline
    $wp_customize->add_setting( 'prospergenics_hero_tagline', array(
        'default'           => 'Empowering Futures, Cultivating Growth',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'prospergenics_hero_tagline', array(
        'label'   => __( 'Hero Tagline', 'prospergenics' ),
        'section' => 'prospergenics_hero',
        'type'    => 'text',
    ) );

    // Hero Description
    $wp_customize->add_setting( 'prospergenics_hero_description', array(
        'default'           => 'Join our diverse community where learning, teamwork, and growth lead to real success.',
        'sanitize_callback' => 'sanitize_textarea_field',
    ) );
    $wp_customize->add_control( 'prospergenics_hero_description', array(
        'label'   => __( 'Hero Description', 'prospergenics' ),
        'section' => 'prospergenics_hero',
        'type'    => 'textarea',
    ) );

    // Hero Background Image
    $wp_customize->add_setting( 'prospergenics_hero_bg', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ) );
    $wp_customize->add_control( new WP_Customize_Image_Control( $wp_customize, 'prospergenics_hero_bg', array(
        'label'    => __( 'Hero Background Image', 'prospergenics' ),
        'section'  => 'prospergenics_hero',
        'settings' => 'prospergenics_hero_bg',
    ) ) );

    // Contact Section
    $wp_customize->add_section( 'prospergenics_contact', array(
        'title'    => __( 'Contact Information', 'prospergenics' ),
        'priority' => 35,
    ) );

    // Email
    $wp_customize->add_setting( 'prospergenics_email', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_email',
    ) );
    $wp_customize->add_control( 'prospergenics_email', array(
        'label'   => __( 'Email Address', 'prospergenics' ),
        'section' => 'prospergenics_contact',
        'type'    => 'email',
    ) );

    // Phone
    $wp_customize->add_setting( 'prospergenics_phone', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_text_field',
    ) );
    $wp_customize->add_control( 'prospergenics_phone', array(
        'label'   => __( 'Phone Number', 'prospergenics' ),
        'section' => 'prospergenics_contact',
        'type'    => 'text',
    ) );

    // Social Links
    $wp_customize->add_section( 'prospergenics_social', array(
        'title'    => __( 'Social Media Links', 'prospergenics' ),
        'priority' => 40,
    ) );

    $social_networks = array(
        'facebook'  => 'Facebook',
        'twitter'   => 'Twitter',
        'linkedin'  => 'LinkedIn',
        'instagram' => 'Instagram',
    );

    foreach ( $social_networks as $network => $label ) {
        $wp_customize->add_setting( 'prospergenics_' . $network, array(
            'default'           => '',
            'sanitize_callback' => 'esc_url_raw',
        ) );
        $wp_customize->add_control( 'prospergenics_' . $network, array(
            'label'   => $label . ' ' . __( 'URL', 'prospergenics' ),
            'section' => 'prospergenics_social',
            'type'    => 'url',
        ) );
    }
}
add_action( 'customize_register', 'prospergenics_customize_register' );

/**
 * Add Body Classes
 */
function prospergenics_body_classes( $classes ) {
    // Add class if not front page
    if ( ! is_front_page() ) {
        $classes[] = 'not-front-page';
    }

    // Add class for singular pages
    if ( is_singular() ) {
        $classes[] = 'singular-page';
    }

    return $classes;
}
add_filter( 'body_class', 'prospergenics_body_classes' );

/**
 * Custom Logo Support
 */
function prospergenics_custom_logo_setup() {
    $defaults = array(
        'height'      => 60,
        'width'       => 200,
        'flex-height' => true,
        'flex-width'  => true,
        'header-text' => array( 'site-title', 'site-description' ),
    );
    add_theme_support( 'custom-logo', $defaults );
}
add_action( 'after_setup_theme', 'prospergenics_custom_logo_setup' );

/**
 * Get Social Links
 */
function prospergenics_get_social_links() {
    $social_networks = array(
        'facebook'  => 'Facebook',
        'twitter'   => 'Twitter',
        'linkedin'  => 'LinkedIn',
        'instagram' => 'Instagram',
    );

    $links = array();
    foreach ( $social_networks as $network => $label ) {
        $url = get_theme_mod( 'prospergenics_' . $network );
        if ( $url ) {
            $links[ $network ] = array(
                'url'   => esc_url( $url ),
                'label' => $label,
            );
        }
    }

    return $links;
}

/**
 * Translation Support
 */
function prospergenics_load_textdomain() {
    load_theme_textdomain( 'prospergenics', get_template_directory() . '/languages' );
}
add_action( 'after_setup_theme', 'prospergenics_load_textdomain' );

/**
 * Include Contact Form Handler
 */
require get_template_directory() . '/inc/contact-form-handler.php';

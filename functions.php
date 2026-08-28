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
 * Add Open Graph and Twitter Card Meta Tags
 *
 * This was added directly on the live site (FTP) on 2026-02-17 without ever being committed to
 * this repo — reconciled here 2026-08-26 (JengoWork task 731) alongside a real bug fix: a static
 * front page is also is_singular(), so it always fell into the singular branch below (never the
 * is_front_page()/is_home() branch), and that branch's fallback — post_excerpt, else 30 trimmed
 * words of strip_tags(post_content) — is empty for this block-built homepage, so og:description
 * and twitter:description rendered blank. Front-page detection now runs first, and every branch
 * falls back to the site tagline if it would otherwise produce an empty description.
 *
 * This plugin's page also runs Yoast SEO in parallel, whose own Open Graph/Twitter Card block
 * disagreed with this one (different og:type, different twitter:card) and had no og:description
 * of its own either. This theme block is kept as the single source of truth for these tags, and
 * Yoast's competing output is disabled below via its own documented filters.
 */
function prospergenics_add_social_meta_tags() {
	$site_name        = get_bloginfo( 'name' );
	$site_description = get_bloginfo( 'description' );
	$og_image         = get_template_directory_uri() . '/images/social-share.jpg';

	if ( is_front_page() ) {
		$og_title       = $site_name;
		$og_description = $site_description;
		$og_type        = 'website';
		$og_url         = home_url( '/' );
	} elseif ( is_home() ) {
		// Static posts page (Settings > Reading, e.g. /blog/): not the front page and not
		// is_singular() either — describe the assigned page itself, not the homepage.
		$posts_page_id  = (int) get_option( 'page_for_posts' );
		$og_title       = $posts_page_id ? get_the_title( $posts_page_id ) : $site_name;
		$og_description = $posts_page_id ? get_post_field( 'post_excerpt', $posts_page_id ) : '';
		$og_type        = 'website';
		$og_url         = $posts_page_id ? get_permalink( $posts_page_id ) : home_url( '/' );
	} elseif ( is_singular() ) {
		global $post;
		$og_title       = get_the_title();
		$og_description = $post->post_excerpt ? $post->post_excerpt : wp_trim_words( strip_tags( $post->post_content ), 30 );
		$og_url         = get_permalink();
		$og_type        = 'website';

		if ( is_single() && has_post_thumbnail() ) {
			$thumbnail_id = get_post_thumbnail_id();
			$thumbnail    = wp_get_attachment_image_src( $thumbnail_id, 'full' );
			if ( $thumbnail ) {
				$og_image = $thumbnail[0];
			}
		}

		if ( is_single() ) {
			$og_type = 'article';
		}
	} elseif ( is_archive() ) {
		// get_the_archive_title() wraps the term name in a <span> since WP 5.5 — strip it, and
		// use the archive's own request URL: get_permalink() outside the loop returns the FIRST
		// POST's permalink (or false), not the archive page.
		global $wp;
		$og_title       = wp_strip_all_tags( get_the_archive_title() );
		$og_description = wp_strip_all_tags( get_the_archive_description() );
		$og_type        = 'website';
		$og_url         = ( isset( $wp->request ) && $wp->request !== '' ) ? home_url( '/' . user_trailingslashit( ltrim( $wp->request, '/' ) ) ) : home_url( '/' );
	} else {
		$og_title       = $site_name;
		$og_description = $site_description;
		$og_type        = 'website';
		$og_url         = home_url( '/' );
	}

	if ( ! $og_description ) {
		$og_description = $site_description;
	}

	$og_title       = esc_attr( $og_title );
	$og_description = esc_attr( $og_description );
	$og_url         = esc_url( $og_url );
	$og_image       = esc_url( $og_image );

	echo "\n<!-- Open Graph Meta Tags -->\n";
	echo '<meta property="og:site_name" content="' . esc_attr( $site_name ) . '">' . "\n";
	echo '<meta property="og:title" content="' . $og_title . '">' . "\n";
	echo '<meta property="og:description" content="' . $og_description . '">' . "\n";
	echo '<meta property="og:type" content="' . $og_type . '">' . "\n";
	echo '<meta property="og:url" content="' . $og_url . '">' . "\n";
	echo '<meta property="og:image" content="' . $og_image . '">' . "\n";
	echo "\n<!-- Twitter Card Meta Tags -->\n";
	echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
	echo '<meta name="twitter:title" content="' . $og_title . '">' . "\n";
	echo '<meta name="twitter:description" content="' . $og_description . '">' . "\n";
	echo '<meta name="twitter:image" content="' . $og_image . '">' . "\n";
	echo "\n";
}
add_action( 'wp_head', 'prospergenics_add_social_meta_tags', 1 );

/**
 * This theme owns Open Graph/Twitter Card output (prospergenics_add_social_meta_tags() above).
 * Yoast SEO was independently rendering its own, disagreeing og:type/twitter:card values with no
 * og:description at all — remove Yoast's Open Graph and Twitter Card presenters so only one set
 * of social tags reaches the page. Yoast's title tag, canonical URL, schema, and sitemap output
 * (all unrelated presenter classes) are untouched.
 *
 * Yoast SEO 14+ replaced the old wpseo_opengraph/wpseo_twitter boolean filters with a presenter
 * pipeline (wpseo_frontend_presenters) — those boolean filters are still accepted but silently
 * ignored on this site's Yoast version (25.4), confirmed live: they did not remove Yoast's block.
 */
add_filter( 'wpseo_frontend_presenters', 'prospergenics_remove_yoast_social_presenters' );
function prospergenics_remove_yoast_social_presenters( $presenters ) {
	foreach ( $presenters as $index => $presenter ) {
		$class = get_class( $presenter );
		if ( strpos( $class, 'Presenters\\Open_Graph\\' ) !== false || strpos( $class, 'Presenters\\Twitter\\' ) !== false ) {
			unset( $presenters[ $index ] );
		}
	}
	return $presenters;
}

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
 * Register Team Member Custom Post Type
 */
function prospergenics_register_team_member_cpt() {
    $labels = array(
        'name'                  => _x( 'Team Members', 'Post type general name', 'prospergenics' ),
        'singular_name'         => _x( 'Team Member', 'Post type singular name', 'prospergenics' ),
        'menu_name'             => _x( 'Team Members', 'Admin Menu text', 'prospergenics' ),
        'add_new'               => __( 'Add New', 'prospergenics' ),
        'add_new_item'          => __( 'Add New Team Member', 'prospergenics' ),
        'new_item'              => __( 'New Team Member', 'prospergenics' ),
        'edit_item'             => __( 'Edit Team Member', 'prospergenics' ),
        'view_item'             => __( 'View Team Member', 'prospergenics' ),
        'all_items'             => __( 'All Team Members', 'prospergenics' ),
        'search_items'          => __( 'Search Team Members', 'prospergenics' ),
        'not_found'             => __( 'No team members found.', 'prospergenics' ),
        'not_found_in_trash'    => __( 'No team members found in Trash.', 'prospergenics' ),
        'featured_image'        => _x( 'Team Member Photo', 'Overrides the "Featured Image" phrase', 'prospergenics' ),
        'set_featured_image'    => _x( 'Set member photo', 'Overrides the "Set featured image" phrase', 'prospergenics' ),
        'remove_featured_image' => _x( 'Remove member photo', 'Overrides the "Remove featured image" phrase', 'prospergenics' ),
        'use_featured_image'    => _x( 'Use as member photo', 'Overrides the "Use as featured image" phrase', 'prospergenics' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'members', 'with_front' => false ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 20,
        'menu_icon'          => 'dashicons-groups',
        'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'team_member', $args );
}
add_action( 'init', 'prospergenics_register_team_member_cpt' );

/**
 * Register Member Type Taxonomy
 */
function prospergenics_register_member_type_taxonomy() {
    $labels = array(
        'name'              => _x( 'Member Types', 'taxonomy general name', 'prospergenics' ),
        'singular_name'     => _x( 'Member Type', 'taxonomy singular name', 'prospergenics' ),
        'search_items'      => __( 'Search Member Types', 'prospergenics' ),
        'all_items'         => __( 'All Member Types', 'prospergenics' ),
        'edit_item'         => __( 'Edit Member Type', 'prospergenics' ),
        'update_item'       => __( 'Update Member Type', 'prospergenics' ),
        'add_new_item'      => __( 'Add New Member Type', 'prospergenics' ),
        'new_item_name'     => __( 'New Member Type Name', 'prospergenics' ),
        'menu_name'         => __( 'Member Types', 'prospergenics' ),
    );

    $args = array(
        'hierarchical'      => true,
        'labels'            => $labels,
        'show_ui'           => true,
        'show_admin_column' => true,
        'query_var'         => true,
        'rewrite'           => array( 'slug' => 'member-type' ),
        'show_in_rest'      => true,
    );

    register_taxonomy( 'member_type', array( 'team_member' ), $args );
}
add_action( 'init', 'prospergenics_register_member_type_taxonomy' );

/**
 * Register Program Custom Post Type
 */
function prospergenics_register_program_cpt() {
    $labels = array(
        'name'                  => _x( 'Programs', 'Post type general name', 'prospergenics' ),
        'singular_name'         => _x( 'Program', 'Post type singular name', 'prospergenics' ),
        'menu_name'             => _x( 'Programs', 'Admin Menu text', 'prospergenics' ),
        'add_new'               => __( 'Add New', 'prospergenics' ),
        'add_new_item'          => __( 'Add New Program', 'prospergenics' ),
        'new_item'              => __( 'New Program', 'prospergenics' ),
        'edit_item'             => __( 'Edit Program', 'prospergenics' ),
        'view_item'             => __( 'View Program', 'prospergenics' ),
        'all_items'             => __( 'All Programs', 'prospergenics' ),
        'search_items'          => __( 'Search Programs', 'prospergenics' ),
        'not_found'             => __( 'No programs found.', 'prospergenics' ),
        'not_found_in_trash'    => __( 'No programs found in Trash.', 'prospergenics' ),
        'featured_image'        => _x( 'Program Image', 'Overrides the "Featured Image" phrase', 'prospergenics' ),
        'set_featured_image'    => _x( 'Set program image', 'Overrides the "Set featured image" phrase', 'prospergenics' ),
        'remove_featured_image' => _x( 'Remove program image', 'Overrides the "Remove featured image" phrase', 'prospergenics' ),
        'use_featured_image'    => _x( 'Use as program image', 'Overrides the "Use as featured image" phrase', 'prospergenics' ),
    );

    $args = array(
        'labels'             => $labels,
        'public'             => true,
        'publicly_queryable' => true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'query_var'          => true,
        'rewrite'            => array( 'slug' => 'programs', 'with_front' => false ),
        'capability_type'    => 'post',
        'has_archive'        => true,
        'hierarchical'       => false,
        'menu_position'      => 21,
        'menu_icon'          => 'dashicons-welcome-learn-more',
        'supports'           => array( 'title', 'editor', 'thumbnail' ),
        'show_in_rest'       => true,
    );

    register_post_type( 'program', $args );
}
add_action( 'init', 'prospergenics_register_program_cpt' );

/**
 * Add Program Meta Boxes
 */
function prospergenics_add_program_meta_boxes() {
    add_meta_box(
        'program_buttons',
        __( 'Program Action Buttons', 'prospergenics' ),
        'prospergenics_program_buttons_callback',
        'program',
        'normal',
        'high'
    );
}
add_action( 'add_meta_boxes', 'prospergenics_add_program_meta_boxes' );

/**
 * Program Buttons Meta Box Callback
 */
function prospergenics_program_buttons_callback( $post ) {
    wp_nonce_field( 'prospergenics_save_program_buttons', 'prospergenics_program_buttons_nonce' );

    $button1_text = get_post_meta( $post->ID, '_program_button1_text', true );
    $button1_url = get_post_meta( $post->ID, '_program_button1_url', true );
    $button1_style = get_post_meta( $post->ID, '_program_button1_style', true );

    $button2_text = get_post_meta( $post->ID, '_program_button2_text', true );
    $button2_url = get_post_meta( $post->ID, '_program_button2_url', true );
    $button2_style = get_post_meta( $post->ID, '_program_button2_style', true );

    ?>
    <div style="margin-bottom: 20px;">
        <h4><?php _e( 'Button 1', 'prospergenics' ); ?></h4>
        <p>
            <label for="program_button1_text"><?php _e( 'Button Text:', 'prospergenics' ); ?></label><br>
            <input type="text" id="program_button1_text" name="program_button1_text" value="<?php echo esc_attr( $button1_text ); ?>" style="width: 100%;">
        </p>
        <p>
            <label for="program_button1_url"><?php _e( 'Button URL:', 'prospergenics' ); ?></label><br>
            <input type="url" id="program_button1_url" name="program_button1_url" value="<?php echo esc_attr( $button1_url ); ?>" style="width: 100%;">
        </p>
        <p>
            <label for="program_button1_style"><?php _e( 'Button Style:', 'prospergenics' ); ?></label><br>
            <select id="program_button1_style" name="program_button1_style">
                <option value="primary" <?php selected( $button1_style, 'primary' ); ?>><?php _e( 'Primary (Green)', 'prospergenics' ); ?></option>
                <option value="secondary" <?php selected( $button1_style, 'secondary' ); ?>><?php _e( 'Secondary (Outline)', 'prospergenics' ); ?></option>
                <option value="waiting" <?php selected( $button1_style, 'waiting' ); ?>><?php _e( 'Waiting (Terracotta)', 'prospergenics' ); ?></option>
            </select>
        </p>
    </div>

    <div style="margin-bottom: 20px;">
        <h4><?php _e( 'Button 2 (Optional)', 'prospergenics' ); ?></h4>
        <p>
            <label for="program_button2_text"><?php _e( 'Button Text:', 'prospergenics' ); ?></label><br>
            <input type="text" id="program_button2_text" name="program_button2_text" value="<?php echo esc_attr( $button2_text ); ?>" style="width: 100%;">
        </p>
        <p>
            <label for="program_button2_url"><?php _e( 'Button URL:', 'prospergenics' ); ?></label><br>
            <input type="url" id="program_button2_url" name="program_button2_url" value="<?php echo esc_attr( $button2_url ); ?>" style="width: 100%;">
        </p>
        <p>
            <label for="program_button2_style"><?php _e( 'Button Style:', 'prospergenics' ); ?></label><br>
            <select id="program_button2_style" name="program_button2_style">
                <option value="primary" <?php selected( $button2_style, 'primary' ); ?>><?php _e( 'Primary (Green)', 'prospergenics' ); ?></option>
                <option value="secondary" <?php selected( $button2_style, 'secondary' ); ?>><?php _e( 'Secondary (Outline)', 'prospergenics' ); ?></option>
                <option value="waiting" <?php selected( $button2_style, 'waiting' ); ?>><?php _e( 'Waiting (Terracotta)', 'prospergenics' ); ?></option>
            </select>
        </p>
    </div>
    <?php
}

/**
 * Save Program Buttons Meta Data
 */
function prospergenics_save_program_buttons( $post_id ) {
    if ( ! isset( $_POST['prospergenics_program_buttons_nonce'] ) ) {
        return;
    }

    if ( ! wp_verify_nonce( $_POST['prospergenics_program_buttons_nonce'], 'prospergenics_save_program_buttons' ) ) {
        return;
    }

    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
        return;
    }

    if ( ! current_user_can( 'edit_post', $post_id ) ) {
        return;
    }

    if ( isset( $_POST['program_button1_text'] ) ) {
        update_post_meta( $post_id, '_program_button1_text', sanitize_text_field( $_POST['program_button1_text'] ) );
    }
    if ( isset( $_POST['program_button1_url'] ) ) {
        update_post_meta( $post_id, '_program_button1_url', esc_url_raw( $_POST['program_button1_url'] ) );
    }
    if ( isset( $_POST['program_button1_style'] ) ) {
        update_post_meta( $post_id, '_program_button1_style', sanitize_text_field( $_POST['program_button1_style'] ) );
    }

    if ( isset( $_POST['program_button2_text'] ) ) {
        update_post_meta( $post_id, '_program_button2_text', sanitize_text_field( $_POST['program_button2_text'] ) );
    }
    if ( isset( $_POST['program_button2_url'] ) ) {
        update_post_meta( $post_id, '_program_button2_url', esc_url_raw( $_POST['program_button2_url'] ) );
    }
    if ( isset( $_POST['program_button2_style'] ) ) {
        update_post_meta( $post_id, '_program_button2_style', sanitize_text_field( $_POST['program_button2_style'] ) );
    }
}
add_action( 'save_post', 'prospergenics_save_program_buttons' );

/**
 * Give the front page a specific, keyword-relevant document title instead of
 * the generic "Home - Prospergenics" produced by the static Home page title.
 */
function prospergenics_front_page_document_title( $title_parts ) {
    if ( is_front_page() && ! is_paged() ) {
        return array(
            'title' => __( 'Prospergenics | AI & Software Development Coaching Community', 'prospergenics' ),
        );
    }

    return $title_parts;
}
add_filter( 'document_title_parts', 'prospergenics_front_page_document_title' );

/**
 * Yoast SEO (when active, as it is on this site) short-circuits WordPress core's
 * document_title_parts filter chain via its own pre_get_document_title hook, which
 * runs earlier in wp_get_document_title() -- so the filter above never fires while
 * Yoast is active. Override at PHP_INT_MAX priority so this runs after Yoast's own
 * filter regardless of Yoast's internal priority.
 */
function prospergenics_front_page_title_override( $title ) {
    if ( is_front_page() && ! is_paged() ) {
        return __( 'Prospergenics | AI & Software Development Coaching Community', 'prospergenics' );
    }

    return $title;
}
add_filter( 'pre_get_document_title', 'prospergenics_front_page_title_override', PHP_INT_MAX );

/**
 * Trainings Page (/trainings/): Fallback Content, Meta Description, and Course Schema
 *
 * The published /trainings/ page (WP page, slug "trainings") has empty post_content
 * in the database, so it renders with no meta description and no training/course
 * structured data even though real training offerings already exist elsewhere on
 * the site: the "Digital Technology" program, the "AI and Technology Training" page,
 * and the "Claude Code & Cursor Coaching" page. Surface those real offerings here
 * instead of inventing new ones.
 */
function prospergenics_get_trainings_offerings() {
    $slugs_to_post_types = array(
        'digital-technology'          => 'program',
        'ai-and-technology-training'  => 'page',
        'claude-code-cursor-coaching' => 'page',
    );

    $offerings = array();
    foreach ( $slugs_to_post_types as $slug => $post_type ) {
        $found = get_posts( array(
            'name'           => $slug,
            'post_type'      => $post_type,
            'post_status'    => 'publish',
            'posts_per_page' => 1,
        ) );
        if ( ! empty( $found ) ) {
            $offerings[] = $found[0];
        }
    }

    return $offerings;
}

function prospergenics_trainings_offering_summary( $post ) {
    $summary = $post->post_excerpt;
    if ( '' === trim( $summary ) ) {
        $summary = wp_trim_words( wp_strip_all_tags( $post->post_content ), 30 );
    }
    return wp_strip_all_tags( $summary );
}

/**
 * Render real training offerings as page content when the editor hasn't written any.
 */
function prospergenics_trainings_page_content( $content ) {
    if ( ! is_page( 'trainings' ) || ! in_the_loop() || ! is_main_query() || '' !== trim( $content ) ) {
        return $content;
    }

    $offerings = prospergenics_get_trainings_offerings();
    if ( empty( $offerings ) ) {
        return $content;
    }

    ob_start();
    ?>
    <div class="programs-overview-grid trainings-page-grid">
        <?php foreach ( $offerings as $offering ) :
            $thumbnail_url     = get_the_post_thumbnail_url( $offering->ID, 'large' );
            $placeholder_style = $thumbnail_url ? "background-image: url('" . esc_url( $thumbnail_url ) . "');" : 'background: linear-gradient(135deg, #2E8B57, #3da968);';
            ?>
            <div class="program-overview-card">
                <div class="program-overview-image" style="<?php echo esc_attr( $placeholder_style ); ?>"></div>
                <div class="program-overview-content">
                    <h3><?php echo esc_html( get_the_title( $offering ) ); ?></h3>
                    <p><?php echo esc_html( prospergenics_trainings_offering_summary( $offering ) ); ?></p>
                    <div class="program-overview-actions">
                        <a href="<?php echo esc_url( get_permalink( $offering ) ); ?>" class="program-action-link primary">
                            <?php esc_html_e( 'Learn More', 'prospergenics' ); ?> &rarr;
                        </a>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}
add_filter( 'the_content', 'prospergenics_trainings_page_content' );

/**
 * Yoast SEO's own frontend presenters print an empty description line for this page
 * (nothing to derive one from). Remove Yoast's Meta_Description_Presenter for this
 * page specifically via the wpseo_frontend_presenters filter -- Yoast 25.4 (active on
 * this site) ignores the older wpseo_metadesc filter here, but presenter removal is
 * confirmed working against this exact Yoast version (used live for the OG/Twitter
 * presenters on task 731) -- and print our own fallback description instead.
 */
function prospergenics_trainings_remove_yoast_description_presenter( $presenters ) {
    if ( ! is_page( 'trainings' ) ) {
        return $presenters;
    }

    foreach ( $presenters as $index => $presenter ) {
        if ( false !== strpos( get_class( $presenter ), 'Meta_Description_Presenter' ) ) {
            unset( $presenters[ $index ] );
        }
    }

    return $presenters;
}
add_filter( 'wpseo_frontend_presenters', 'prospergenics_trainings_remove_yoast_description_presenter' );

function prospergenics_trainings_meta_description_text() {
    $offerings = prospergenics_get_trainings_offerings();
    if ( empty( $offerings ) ) {
        return '';
    }

    $names = wp_list_pluck( $offerings, 'post_title' );

    return sprintf(
        /* translators: %s: comma-separated list of training/course names */
        __( 'Explore Prospergenics training programs, including %s - hands-on courses that build real digital and technology skills.', 'prospergenics' ),
        implode( ', ', $names )
    );
}

function prospergenics_trainings_output_meta_description() {
    if ( ! is_page( 'trainings' ) ) {
        return;
    }

    $description = prospergenics_trainings_meta_description_text();
    if ( '' === $description ) {
        return;
    }

    echo '<meta name="description" content="' . esc_attr( $description ) . '" />' . "\n";
}
add_action( 'wp_head', 'prospergenics_trainings_output_meta_description', 1 );

/**
 * Course structured data for each real training offering, so AI tools and search
 * engines can cite specific course offerings instead of just the generic WebPage.
 */
function prospergenics_trainings_course_schema() {
    if ( ! is_page( 'trainings' ) ) {
        return;
    }

    $offerings = prospergenics_get_trainings_offerings();
    if ( empty( $offerings ) ) {
        return;
    }

    $courses = array();
    foreach ( $offerings as $offering ) {
        $permalink = get_permalink( $offering );
        $courses[] = array(
            '@type'       => 'Course',
            '@id'         => $permalink . '#course',
            // JSON-LD <script> content isn't HTML-parsed, so entities from get_the_title()
            // (e.g. "&#038;") or wp_trim_words()'s default "&hellip;" would reach schema
            // consumers literally instead of as "&"/"…" -- decode before encoding as JSON.
            'name'        => html_entity_decode( get_the_title( $offering ), ENT_QUOTES, 'UTF-8' ),
            'description' => html_entity_decode( prospergenics_trainings_offering_summary( $offering ), ENT_QUOTES, 'UTF-8' ),
            'url'         => $permalink,
            'provider'    => array(
                '@type' => 'Organization',
                'name'  => get_bloginfo( 'name' ),
                'url'   => home_url( '/' ),
            ),
        );
    }

    $schema = array(
        '@context' => 'https://schema.org',
        '@graph'   => $courses,
    );

    echo "\n" . '<script type="application/ld+json" class="prospergenics-trainings-course-schema">' . wp_json_encode( $schema ) . '</script>' . "\n";
}
add_action( 'wp_head', 'prospergenics_trainings_course_schema', 25 );

/**
 * Legacy URL Redirects
 *
 * The site was restructured into a single-page homepage (task 733/731/765) and some
 * pre-restructure standalone pages were deleted from the database without a redirect left
 * behind, so they now 404 instead of forwarding search engines / AI-answer engines /
 * existing backlinks to the closest surviving equivalent -- losing whatever citation
 * equity had accrued at the old URL.
 *
 * Task 855: /kenya/ (previously a real published page, confirmed by its old HTTP 403 under
 * the site's WAF bot-block) now 404s. The About page (/about/) carries the site's actual,
 * substantive Kenya content -- the "Where is Prospergenics located?" FAQ answer and intro
 * copy both state the team is based in Kenya and the Netherlands -- so it's a closer match
 * than a bare homepage bounce. /trainings/ and /martien/, the other legacy URLs named
 * alongside /kenya/ in this task, both still resolve (200) and need no entry here.
 *
 * Add further slugs to $legacy_redirects if a future audit finds more silently-dropped URLs.
 */
function prospergenics_legacy_url_redirects() {
	if ( ! is_404() ) {
		return;
	}

	$request_path = trim( (string) parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ), '/' );

	$legacy_redirects = array(
		'kenya' => home_url( '/about/' ),
	);

	if ( isset( $legacy_redirects[ $request_path ] ) ) {
		wp_safe_redirect( $legacy_redirects[ $request_path ], 301 );
		exit;
	}
}
add_action( 'template_redirect', 'prospergenics_legacy_url_redirects' );

/**
 * Task 930: the site is English-only (lang="en-US" everywhere, zero hreflang tags) despite
 * Prospergenics running AI/dev coaching in both the Netherlands and Kenya -- Dutch-phrased
 * training queries had no Dutch-language page to match against. This adds ONE additive Dutch
 * landing page (a translation of "Claude Code & Cursor Coaching for Dutch Teams", the training
 * offering most directly aimed at a Dutch audience) plus reciprocal hreflang between it and its
 * English original. English stays the default (x-default) given the Kenya audience -- this is
 * not a full-site translation.
 *
 * Add further slug pairs here if more pages get a Dutch counterpart later.
 */
function prospergenics_hreflang_pairs() {
	return array(
		'claude-code-cursor-coaching'    => array( 'lang' => 'en', 'partner_slug' => 'claude-code-cursor-coaching-nl' ),
		'claude-code-cursor-coaching-nl' => array( 'lang' => 'nl', 'partner_slug' => 'claude-code-cursor-coaching' ),
	);
}

function prospergenics_output_hreflang_tags() {
	if ( ! is_page() ) {
		return;
	}

	$current = get_queried_object();
	if ( ! ( $current instanceof WP_Post ) ) {
		return;
	}

	$pairs = prospergenics_hreflang_pairs();
	$slug  = $current->post_name;

	if ( ! isset( $pairs[ $slug ] ) ) {
		return;
	}

	$partner_slug = $pairs[ $slug ]['partner_slug'];
	$partner_page = get_page_by_path( $partner_slug );

	// The partner translation may not exist yet (e.g. this pair was just added and only one
	// side has been created in WP so far) -- fail quiet rather than link to a 404.
	if ( ! $partner_page ) {
		return;
	}

	$own_lang     = $pairs[ $slug ]['lang'];
	$partner_lang = $pairs[ $partner_slug ]['lang'];
	$own_url      = get_permalink( $current );
	$partner_url  = get_permalink( $partner_page );
	$english_url  = ( 'en' === $own_lang ) ? $own_url : $partner_url;

	echo "\n<!-- hreflang alternates (task 930) -->\n";
	echo '<link rel="alternate" hreflang="' . esc_attr( $own_lang ) . '" href="' . esc_url( $own_url ) . '" />' . "\n";
	echo '<link rel="alternate" hreflang="' . esc_attr( $partner_lang ) . '" href="' . esc_url( $partner_url ) . '" />' . "\n";
	echo '<link rel="alternate" hreflang="x-default" href="' . esc_url( $english_url ) . '" />' . "\n";
}
add_action( 'wp_head', 'prospergenics_output_hreflang_tags', 1 );

/**
 * The Dutch landing page's own <html lang="..."> must read "nl-NL", not the site-wide "en-US"
 * that get_language_attributes() otherwise emits for every page (Settings > General site
 * language is English). Scoped to this one page only -- no other page's lang attribute changes.
 */
function prospergenics_dutch_page_language_attributes( $output, $doctype ) {
	if ( ! is_page( 'claude-code-cursor-coaching-nl' ) ) {
		return $output;
	}

	if ( preg_match( '/lang="[^"]*"/', $output ) ) {
		return preg_replace( '/lang="[^"]*"/', 'lang="nl-NL"', $output, 1 );
	}

	return trim( $output . ' lang="nl-NL"' );
}
add_filter( 'language_attributes', 'prospergenics_dutch_page_language_attributes', 10, 2 );

/**
 * The Dutch landing page needs its own real meta description (same gap as the front page/
 * /trainings/ before tasks 797/765 -- Yoast only prints one when its own per-page SEO field is
 * filled in, which it isn't for a page created via the REST API without setting that field).
 */
function prospergenics_nl_landing_meta_description_text() {
	return __( 'Praktijkgerichte coaching in Claude Code en Cursor voor Nederlandse ontwikkelteams, gegeven door Prospergenics-oprichter Martien de Jong. Op locatie of op afstand, op jullie eigen codebase.', 'prospergenics' );
}

function prospergenics_nl_landing_remove_yoast_description_presenter( $presenters ) {
	if ( ! is_page( 'claude-code-cursor-coaching-nl' ) ) {
		return $presenters;
	}

	foreach ( $presenters as $index => $presenter ) {
		if ( false !== strpos( get_class( $presenter ), 'Meta_Description_Presenter' ) ) {
			unset( $presenters[ $index ] );
		}
	}

	return $presenters;
}
add_filter( 'wpseo_frontend_presenters', 'prospergenics_nl_landing_remove_yoast_description_presenter' );

function prospergenics_nl_landing_output_meta_description() {
	if ( ! is_page( 'claude-code-cursor-coaching-nl' ) ) {
		return;
	}

	echo '<meta name="description" content="' . esc_attr( prospergenics_nl_landing_meta_description_text() ) . '" />' . "\n";
}
add_action( 'wp_head', 'prospergenics_nl_landing_output_meta_description', 1 );

/**
 * Include Contact Form Handler
 */
require get_template_directory() . '/inc/contact-form-handler.php';

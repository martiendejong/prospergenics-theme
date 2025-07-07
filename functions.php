<?php
/**
 * Prospergenics Theme Functions
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Theme setup
 */
function prospergenics_setup() {
    // Add theme support for various features
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('custom-logo', array(
        'height'      => 100,
        'width'       => 400,
        'flex-height' => true,
        'flex-width'  => true,
    ));
    add_theme_support('html5', array(
        'search-form',
        'comment-form',
        'comment-list',
        'gallery',
        'caption',
    ));
    add_theme_support('customize-selective-refresh-widgets');
    
    // Register navigation menus
    register_nav_menus(array(
        'menu-1' => esc_html__('Primary', 'prospergenics'),
    ));
}
add_action('after_setup_theme', 'prospergenics_setup');

/**
 * Set the content width in pixels
 */
function prospergenics_content_width() {
    $GLOBALS['content_width'] = apply_filters('prospergenics_content_width', 1200);
}
add_action('after_setup_theme', 'prospergenics_content_width', 0);

/**
 * Enqueue scripts and styles
 */
function prospergenics_scripts() {
    wp_enqueue_style('prospergenics-style', get_stylesheet_uri(), array(), '1.0.0');
    
    // Enqueue Google Fonts
    wp_enqueue_style('prospergenics-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap', array(), null);
    
    // Enqueue theme JavaScript
    wp_enqueue_script('prospergenics-navigation', get_template_directory_uri() . '/js/navigation.js', array(), '1.0.0', true);
    
    if (is_singular() && comments_open() && get_option('thread_comments')) {
        wp_enqueue_script('comment-reply');
    }
}
add_action('wp_enqueue_scripts', 'prospergenics_scripts');

/**
 * Custom template tags for this theme
 */
require get_template_directory() . '/inc/template-tags.php';

/**
 * Functions which enhance the theme by hooking into WordPress
 */
require get_template_directory() . '/inc/template-functions.php';

/**
 * Customizer additions
 */
require get_template_directory() . '/inc/customizer.php';

/**
 * Register widget area
 */
function prospergenics_widgets_init() {
    register_sidebar(array(
        'name'          => esc_html__('Sidebar', 'prospergenics'),
        'id'            => 'sidebar-1',
        'description'   => esc_html__('Add widgets here.', 'prospergenics'),
        'before_widget' => '<section id="%1$s" class="widget %2$s">',
        'after_widget'  => '</section>',
        'before_title'  => '<h2 class="widget-title">',
        'after_title'   => '</h2>',
    ));
}
add_action('widgets_init', 'prospergenics_widgets_init');

/**
 * Custom post types for testimonials and programs
 */
function prospergenics_custom_post_types() {
    // Testimonials
    register_post_type('testimonial', array(
        'labels' => array(
            'name' => 'Testimonials',
            'singular_name' => 'Testimonial',
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail'),
        'menu_icon' => 'dashicons-format-quote',
    ));
    
    // Programs
    register_post_type('program', array(
        'labels' => array(
            'name' => 'Programs',
            'singular_name' => 'Program',
        ),
        'public' => true,
        'has_archive' => true,
        'supports' => array('title', 'editor', 'thumbnail', 'excerpt'),
        'menu_icon' => 'dashicons-welcome-learn-more',
    ));
}
add_action('init', 'prospergenics_custom_post_types');

/**
 * Add custom fields for testimonials
 */
function prospergenics_testimonial_meta_boxes() {
    add_meta_box(
        'testimonial_details',
        'Testimonial Details',
        'prospergenics_testimonial_meta_box_callback',
        'testimonial'
    );
}
add_action('add_meta_boxes', 'prospergenics_testimonial_meta_boxes');

function prospergenics_testimonial_meta_box_callback($post) {
    wp_nonce_field('prospergenics_save_testimonial_data', 'prospergenics_testimonial_nonce');
    
    $author_name = get_post_meta($post->ID, '_testimonial_author_name', true);
    $author_title = get_post_meta($post->ID, '_testimonial_author_title', true);
    $author_location = get_post_meta($post->ID, '_testimonial_author_location', true);
    
    echo '<table class="form-table">';
    echo '<tr>';
    echo '<th><label for="testimonial_author_name">Author Name</label></th>';
    echo '<td><input type="text" id="testimonial_author_name" name="testimonial_author_name" value="' . esc_attr($author_name) . '" size="50" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th><label for="testimonial_author_title">Author Title</label></th>';
    echo '<td><input type="text" id="testimonial_author_title" name="testimonial_author_title" value="' . esc_attr($author_title) . '" size="50" /></td>';
    echo '</tr>';
    echo '<tr>';
    echo '<th><label for="testimonial_author_location">Author Location</label></th>';
    echo '<td><input type="text" id="testimonial_author_location" name="testimonial_author_location" value="' . esc_attr($author_location) . '" size="50" /></td>';
    echo '</tr>';
    echo '</table>';
}

function prospergenics_save_testimonial_data($post_id) {
    if (!isset($_POST['prospergenics_testimonial_nonce'])) {
        return;
    }
    
    if (!wp_verify_nonce($_POST['prospergenics_testimonial_nonce'], 'prospergenics_save_testimonial_data')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (isset($_POST['post_type']) && 'testimonial' == $_POST['post_type']) {
        if (!current_user_can('edit_page', $post_id)) {
            return;
        }
    } else {
        if (!current_user_can('edit_post', $post_id)) {
            return;
        }
    }
    
    if (isset($_POST['testimonial_author_name'])) {
        update_post_meta($post_id, '_testimonial_author_name', sanitize_text_field($_POST['testimonial_author_name']));
    }
    
    if (isset($_POST['testimonial_author_title'])) {
        update_post_meta($post_id, '_testimonial_author_title', sanitize_text_field($_POST['testimonial_author_title']));
    }
    
    if (isset($_POST['testimonial_author_location'])) {
        update_post_meta($post_id, '_testimonial_author_location', sanitize_text_field($_POST['testimonial_author_location']));
    }
}
add_action('save_post', 'prospergenics_save_testimonial_data');

/**
 * Shortcode for displaying testimonials
 */
function prospergenics_testimonials_shortcode($atts) {
    $atts = shortcode_atts(array(
        'count' => 3,
    ), $atts);
    
    $testimonials = get_posts(array(
        'post_type' => 'testimonial',
        'posts_per_page' => $atts['count'],
        'post_status' => 'publish',
    ));
    
    if (empty($testimonials)) {
        return '';
    }
    
    $output = '<div class="testimonials-grid">';
    
    foreach ($testimonials as $testimonial) {
        $author_name = get_post_meta($testimonial->ID, '_testimonial_author_name', true);
        $author_title = get_post_meta($testimonial->ID, '_testimonial_author_title', true);
        $author_location = get_post_meta($testimonial->ID, '_testimonial_author_location', true);
        
        $output .= '<div class="testimonial-card">';
        $output .= '<div class="testimonial-content">' . wp_kses_post($testimonial->post_content) . '</div>';
        $output .= '<div class="testimonial-author">';
        $output .= '<strong>' . esc_html($author_name) . '</strong>';
        if ($author_title) {
            $output .= '<br><span class="author-title">' . esc_html($author_title) . '</span>';
        }
        if ($author_location) {
            $output .= '<br><span class="author-location">' . esc_html($author_location) . '</span>';
        }
        $output .= '</div>';
        $output .= '</div>';
    }
    
    $output .= '</div>';
    
    return $output;
}
add_shortcode('prospergenics_testimonials', 'prospergenics_testimonials_shortcode');

/**
 * Add custom CSS for testimonials
 */
function prospergenics_testimonial_styles() {
    echo '<style>
    .testimonials-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 2rem;
        margin: 2rem 0;
    }
    
    .testimonial-card {
        background: white;
        padding: 2rem;
        border-radius: 12px;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        border-left: 4px solid var(--accent-gold);
    }
    
    .testimonial-content {
        font-style: italic;
        margin-bottom: 1rem;
        color: var(--medium-text);
        line-height: 1.6;
    }
    
    .testimonial-author {
        color: var(--primary-green);
        font-weight: 600;
    }
    
    .author-title, .author-location {
        color: var(--medium-text);
        font-weight: 400;
        font-size: 0.9rem;
    }
    </style>';
}
add_action('wp_head', 'prospergenics_testimonial_styles');

/**
 * Security enhancements
 */
// Remove WordPress version from head
remove_action('wp_head', 'wp_generator');

// Disable XML-RPC
add_filter('xmlrpc_enabled', '__return_false');

// Remove unnecessary meta tags
remove_action('wp_head', 'wlwmanifest_link');
remove_action('wp_head', 'rsd_link');

/**
 * Performance optimizations
 */
// Remove query strings from static resources
function prospergenics_remove_query_strings($src) {
    $parts = explode('?ver', $src);
    return $parts[0];
}
add_filter('script_loader_src', 'prospergenics_remove_query_strings', 15, 1);
add_filter('style_loader_src', 'prospergenics_remove_query_strings', 15, 1);

// Defer parsing of JavaScript
function prospergenics_defer_parsing_of_js($url) {
    if (FALSE === strpos($url, '.js')) return $url;
    if (strpos($url, 'jquery.js')) return $url;
    return "$url' defer='defer";
}
add_filter('clean_url', 'prospergenics_defer_parsing_of_js', 11, 1);


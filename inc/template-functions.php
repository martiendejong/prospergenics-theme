<?php
/**
 * Functions which enhance the theme by hooking into WordPress
 */

/**
 * Adds custom classes to the array of body classes
 */
function prospergenics_body_classes($classes) {
    // Adds a class of hfeed to non-singular pages
    if (!is_singular()) {
        $classes[] = 'hfeed';
    }

    // Adds a class of no-sidebar when there is no sidebar present
    if (!is_active_sidebar('sidebar-1')) {
        $classes[] = 'no-sidebar';
    }

    return $classes;
}
add_filter('body_class', 'prospergenics_body_classes');

/**
 * Add a pingback url auto-discovery header for single posts, pages, or attachments
 */
function prospergenics_pingback_header() {
    if (is_singular() && pings_open()) {
        printf('<link rel="pingback" href="%s">', esc_url(get_bloginfo('pingback_url')));
    }
}
add_action('wp_head', 'prospergenics_pingback_header');

/**
 * Custom excerpt length
 */
function prospergenics_excerpt_length($length) {
    return 30;
}
add_filter('excerpt_length', 'prospergenics_excerpt_length');

/**
 * Custom excerpt more
 */
function prospergenics_excerpt_more($more) {
    return '...';
}
add_filter('excerpt_more', 'prospergenics_excerpt_more');

/**
 * Add custom image sizes
 */
function prospergenics_image_sizes() {
    add_image_size('prospergenics-hero', 1920, 1080, true);
    add_image_size('prospergenics-card', 400, 300, true);
    add_image_size('prospergenics-testimonial', 100, 100, true);
}
add_action('after_setup_theme', 'prospergenics_image_sizes');

/**
 * Enqueue admin styles
 */
function prospergenics_admin_styles() {
    wp_enqueue_style('prospergenics-admin', get_template_directory_uri() . '/admin-style.css');
}
add_action('admin_enqueue_scripts', 'prospergenics_admin_styles');

/**
 * Custom login page styling
 */
function prospergenics_login_styles() {
    ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url(<?php echo get_template_directory_uri(); ?>/images/logo.png);
            height: 80px;
            width: 320px;
            background-size: contain;
            background-repeat: no-repeat;
            padding-bottom: 30px;
        }
        
        .login form {
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        
        .wp-core-ui .button-primary {
            background: #2E8B57;
            border-color: #2E8B57;
            text-shadow: none;
            box-shadow: none;
        }
        
        .wp-core-ui .button-primary:hover {
            background: #FFD700;
            border-color: #FFD700;
            color: #333;
        }
    </style>
    <?php
}
add_action('login_enqueue_scripts', 'prospergenics_login_styles');

/**
 * Change login logo URL
 */
function prospergenics_login_logo_url() {
    return home_url();
}
add_filter('login_headerurl', 'prospergenics_login_logo_url');

/**
 * Change login logo title
 */
function prospergenics_login_logo_url_title() {
    return get_bloginfo('name');
}
add_filter('login_headertitle', 'prospergenics_login_logo_url_title');

/**
 * Add custom post states
 */
function prospergenics_add_post_state($post_states, $post) {
    if (get_option('page_on_front') == $post->ID) {
        $post_states['prospergenics_front_page'] = __('Prospergenics Front Page');
    }
    return $post_states;
}
add_filter('display_post_states', 'prospergenics_add_post_state', 10, 2);

/**
 * Customize the admin footer text
 */
function prospergenics_admin_footer_text() {
    echo 'Thank you for using the Prospergenics theme! Built with ❤️ for empowering communities.';
}
add_filter('admin_footer_text', 'prospergenics_admin_footer_text');

/**
 * Add theme support for Gutenberg
 */
function prospergenics_gutenberg_support() {
    // Add support for editor styles
    add_theme_support('editor-styles');
    
    // Add support for responsive embeds
    add_theme_support('responsive-embeds');
    
    // Add support for wide and full alignment
    add_theme_support('align-wide');
    
    // Add custom color palette
    add_theme_support('editor-color-palette', array(
        array(
            'name'  => __('Primary Green', 'prospergenics'),
            'slug'  => 'primary-green',
            'color' => '#2E8B57',
        ),
        array(
            'name'  => __('Accent Gold', 'prospergenics'),
            'slug'  => 'accent-gold',
            'color' => '#FFD700',
        ),
        array(
            'name'  => __('Accent Orange', 'prospergenics'),
            'slug'  => 'accent-orange',
            'color' => '#FFA500',
        ),
        array(
            'name'  => __('Secondary Blue', 'prospergenics'),
            'slug'  => 'secondary-blue',
            'color' => '#87CEEB',
        ),
        array(
            'name'  => __('Dark Text', 'prospergenics'),
            'slug'  => 'dark-text',
            'color' => '#333333',
        ),
        array(
            'name'  => __('Medium Text', 'prospergenics'),
            'slug'  => 'medium-text',
            'color' => '#666666',
        ),
    ));
    
    // Add custom font sizes
    add_theme_support('editor-font-sizes', array(
        array(
            'name' => __('Small', 'prospergenics'),
            'size' => 14,
            'slug' => 'small'
        ),
        array(
            'name' => __('Normal', 'prospergenics'),
            'size' => 18,
            'slug' => 'normal'
        ),
        array(
            'name' => __('Large', 'prospergenics'),
            'size' => 24,
            'slug' => 'large'
        ),
        array(
            'name' => __('Extra Large', 'prospergenics'),
            'size' => 32,
            'slug' => 'extra-large'
        ),
    ));
}
add_action('after_setup_theme', 'prospergenics_gutenberg_support');

/**
 * Enqueue block editor styles
 */
function prospergenics_block_editor_styles() {
    wp_enqueue_style('prospergenics-block-editor-styles', get_template_directory_uri() . '/block-editor-style.css', array(), '1.0.0');
}
add_action('enqueue_block_editor_assets', 'prospergenics_block_editor_styles');


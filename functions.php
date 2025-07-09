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

// Defer parsing of JavaScript (fixed - no more stray quote and defer only if not admin, not core and only scripts that are not excluded)
function prospergenics_defer_parsing_of_js($url) {
    if (is_admin()) return $url;
    if (false === strpos($url, '.js')) return $url;
    if (strpos($url, 'jquery.js') !== false) return $url;
    if (strpos($url, 'jquery-migrate') !== false) return $url;
    if (strpos($url, 'customize-controls') !== false) return $url;
    if (strpos($url, 'customize-preview') !== false) return $url;
    if (strpos($url, 'wp-') !== false) return $url;
    if (strpos($url, 'admin-bar') !== false) return $url;
    if (strpos($url, 'editor') !== false) return $url;
    if (strpos($url, 'block') !== false) return $url;
    if (strpos($url, 'tinymce') !== false) return $url;
    if (strpos($url, 'load-scripts.php') !== false) return $url;
    if (strpos($url, 'heartbeat') !== false) return $url;
    if (strpos($url, 'media') !== false) return $url;
    if (strpos($url, 'underscore') !== false) return $url;
    if (strpos($url, 'backbone') !== false) return $url;
    if (strpos($url, 'wp-util') !== false) return $url;
    // If it already contains 'defer', don't double apply
    if (strpos($url, "defer=") !== false) return $url;
    return str_replace('.js', '.js" defer="defer', $url);
}
add_filter('script_loader_tag', 'prospergenics_defer_parsing_of_js', 11, 1);

// Create contact form submissions table on theme activation
function prospergenics_create_contact_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'prospergenics_contact_submissions';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(100) NOT NULL,
        email varchar(100) NOT NULL,
        message text NOT NULL,
        ip_address varchar(45),
        user_agent text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
add_action('after_switch_theme', 'prospergenics_create_contact_table');

// Also create table on init if it doesn't exist
function prospergenics_ensure_contact_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'prospergenics_contact_submissions';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        prospergenics_create_contact_table();
    }
}
add_action('init', 'prospergenics_ensure_contact_table');

// Contact form handler
add_action('admin_post_nopriv_prospergenics_send_contact_form', 'prospergenics_send_contact_form');
add_action('admin_post_prospergenics_send_contact_form', 'prospergenics_send_contact_form');
function prospergenics_send_contact_form() {
    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'prospergenics_contact_form')) {
        wp_die('Security check failed.');
    }
    
    $name = sanitize_text_field($_POST['cf_name'] ?? '');
    $email = sanitize_email($_POST['cf_email'] ?? '');
    $phone = sanitize_text_field($_POST['cf_phone'] ?? '');
    $subject = sanitize_text_field($_POST['cf_subject'] ?? '');
    $message = sanitize_textarea_field($_POST['cf_message'] ?? '');
    
    if (!$name || !$email || !$message) {
        wp_die('Please fill in all required fields.');
    }
    
    // Store in database
    global $wpdb;
    $table_name = $wpdb->prefix . 'prospergenics_contact_submissions';
    
    // Prepare the full message with additional fields
    $full_message = $message;
    if ($subject) {
        $full_message = "Subject: $subject\n\n" . $full_message;
    }
    if ($phone) {
        $full_message = $full_message . "\n\nPhone: $phone";
    }
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'name' => $name,
            'email' => $email,
            'message' => $full_message,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ),
        array('%s', '%s', '%s', '%s', '%s')
    );
    
    if ($result === false) {
        wp_die('Error saving contact form submission.');
    }
    
    // Send email notification
    $to = get_option('admin_email');
    $email_subject = 'New Contact Form Submission';
    if ($subject) {
        $email_subject .= ' - ' . $subject;
    }
    
    $body = "Name: $name\nEmail: $email\n";
    if ($phone) {
        $body .= "Phone: $phone\n";
    }
    if ($subject) {
        $body .= "Subject: $subject\n";
    }
    $body .= "\nMessage:\n$message";
    
    $headers = ['Reply-To: ' . $email];
    wp_mail($to, $email_subject, $body, $headers);
    
    wp_redirect(home_url('/?contact=success'));
    exit;
}

// Add admin menu for contact submissions
function prospergenics_contact_admin_menu() {
    add_menu_page(
        'Contact Submissions',
        'Contact Submissions',
        'manage_options',
        'prospergenics-contact-submissions',
        'prospergenics_contact_submissions_page',
        'dashicons-email-alt',
        30
    );
}
add_action('admin_menu', 'prospergenics_contact_admin_menu');

// Admin page for viewing contact submissions
function prospergenics_contact_submissions_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'prospergenics_contact_submissions';
    
    // Handle deletion
    if (isset($_POST['delete_submission']) && isset($_POST['submission_id'])) {
        $submission_id = intval($_POST['submission_id']);
        $wpdb->delete($table_name, array('id' => $submission_id), array('%d'));
        echo '<div class="notice notice-success"><p>Submission deleted successfully.</p></div>';
    }
    
    // Get submissions
    $submissions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC");
    
    ?>
    <div class="wrap">
        <h1>Contact Form Submissions</h1>
        
        <?php if (empty($submissions)): ?>
            <p>No contact form submissions yet.</p>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Message</th>
                        <th>IP Address</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($submissions as $submission): ?>
                        <tr>
                            <td><?php echo esc_html($submission->id); ?></td>
                            <td><?php echo esc_html($submission->name); ?></td>
                            <td><?php echo esc_html($submission->email); ?></td>
                            <td><?php echo esc_html(wp_trim_words($submission->message, 20)); ?></td>
                            <td><?php echo esc_html($submission->ip_address); ?></td>
                            <td><?php echo esc_html(date('Y-m-d H:i:s', strtotime($submission->created_at))); ?></td>
                            <td>
                                <form method="post" style="display:inline;">
                                    <input type="hidden" name="submission_id" value="<?php echo esc_attr($submission->id); ?>">
                                    <button type="submit" name="delete_submission" class="button button-small" onclick="return confirm('Are you sure you want to delete this submission?')">Delete</button>
                                </form>
                                <button type="button" class="button button-small" onclick="showMessage('<?php echo esc_js($submission->message); ?>')">View Full Message</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <script>
    function showMessage(message) {
        alert('Full Message:\n\n' + message);
    }
    </script>
    <?php
}


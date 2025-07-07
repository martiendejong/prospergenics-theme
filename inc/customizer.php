<?php
/**
 * Prospergenics Theme Customizer
 */

/**
 * Add postMessage support for site title and description for the Theme Customizer
 */
function prospergenics_customize_register($wp_customize) {
    $wp_customize->get_setting('blogname')->transport         = 'postMessage';
    $wp_customize->get_setting('blogdescription')->transport  = 'postMessage';
    $wp_customize->get_setting('header_textcolor')->transport = 'postMessage';

    if (isset($wp_customize->selective_refresh)) {
        $wp_customize->selective_refresh->add_partial('blogname', array(
            'selector'        => '.site-title a',
            'render_callback' => 'prospergenics_customize_partial_blogname',
        ));
        $wp_customize->selective_refresh->add_partial('blogdescription', array(
            'selector'        => '.site-description',
            'render_callback' => 'prospergenics_customize_partial_blogdescription',
        ));
    }

    // Hero Section
    $wp_customize->add_section('prospergenics_hero_section', array(
        'title'    => __('Hero Section', 'prospergenics'),
        'priority' => 30,
    ));

    // Hero Title
    $wp_customize->add_setting('prospergenics_hero_title', array(
        'default'           => 'Building Prosperity Together',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_hero_title', array(
        'label'    => __('Hero Title', 'prospergenics'),
        'section'  => 'prospergenics_hero_section',
        'type'     => 'text',
    ));

    // Hero Subtitle
    $wp_customize->add_setting('prospergenics_hero_subtitle', array(
        'default'           => 'Empowering Futures, Cultivating Growth',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_hero_subtitle', array(
        'label'    => __('Hero Subtitle', 'prospergenics'),
        'section'  => 'prospergenics_hero_section',
        'type'     => 'text',
    ));

    // Hero Description
    $wp_customize->add_setting('prospergenics_hero_description', array(
        'default'           => 'Join our diverse community—where learning, teamwork, and growth lead to real success.',
        'sanitize_callback' => 'sanitize_textarea_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_hero_description', array(
        'label'    => __('Hero Description', 'prospergenics'),
        'section'  => 'prospergenics_hero_section',
        'type'     => 'textarea',
    ));

    // Hero Background Image
    $wp_customize->add_setting('prospergenics_hero_background', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ));

    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'prospergenics_hero_background', array(
        'label'    => __('Hero Background Image', 'prospergenics'),
        'section'  => 'prospergenics_hero_section',
        'settings' => 'prospergenics_hero_background',
    )));

    // Primary CTA Text
    $wp_customize->add_setting('prospergenics_primary_cta_text', array(
        'default'           => 'Join the Movement',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_primary_cta_text', array(
        'label'    => __('Primary CTA Text', 'prospergenics'),
        'section'  => 'prospergenics_hero_section',
        'type'     => 'text',
    ));

    // Primary CTA URL
    $wp_customize->add_setting('prospergenics_primary_cta_url', array(
        'default'           => '#join',
        'sanitize_callback' => 'esc_url_raw',
    ));

    $wp_customize->add_control('prospergenics_primary_cta_url', array(
        'label'    => __('Primary CTA URL', 'prospergenics'),
        'section'  => 'prospergenics_hero_section',
        'type'     => 'url',
    ));

    // Secondary CTA Text
    $wp_customize->add_setting('prospergenics_secondary_cta_text', array(
        'default'           => 'Explore Our Programs',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_secondary_cta_text', array(
        'label'    => __('Secondary CTA Text', 'prospergenics'),
        'section'  => 'prospergenics_hero_section',
        'type'     => 'text',
    ));

    // Secondary CTA URL
    $wp_customize->add_setting('prospergenics_secondary_cta_url', array(
        'default'           => '#programs',
        'sanitize_callback' => 'esc_url_raw',
    ));

    $wp_customize->add_control('prospergenics_secondary_cta_url', array(
        'label'    => __('Secondary CTA URL', 'prospergenics'),
        'section'  => 'prospergenics_hero_section',
        'type'     => 'url',
    ));

    // Colors Section
    $wp_customize->add_section('prospergenics_colors', array(
        'title'    => __('Theme Colors', 'prospergenics'),
        'priority' => 40,
    ));

    // Primary Color
    $wp_customize->add_setting('prospergenics_primary_color', array(
        'default'           => '#2E8B57',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'prospergenics_primary_color', array(
        'label'    => __('Primary Color', 'prospergenics'),
        'section'  => 'prospergenics_colors',
        'settings' => 'prospergenics_primary_color',
    )));

    // Accent Color
    $wp_customize->add_setting('prospergenics_accent_color', array(
        'default'           => '#FFD700',
        'sanitize_callback' => 'sanitize_hex_color',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control(new WP_Customize_Color_Control($wp_customize, 'prospergenics_accent_color', array(
        'label'    => __('Accent Color', 'prospergenics'),
        'section'  => 'prospergenics_colors',
        'settings' => 'prospergenics_accent_color',
    )));

    // Impact Section
    $wp_customize->add_section('prospergenics_impact', array(
        'title'    => __('Impact Section', 'prospergenics'),
        'priority' => 50,
    ));

    // Stat 1 Number
    $wp_customize->add_setting('prospergenics_stat1_number', array(
        'default'           => '500+',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_stat1_number', array(
        'label'    => __('Stat 1 Number', 'prospergenics'),
        'section'  => 'prospergenics_impact',
        'type'     => 'text',
    ));

    // Stat 1 Label
    $wp_customize->add_setting('prospergenics_stat1_label', array(
        'default'           => 'Individuals Trained',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_stat1_label', array(
        'label'    => __('Stat 1 Label', 'prospergenics'),
        'section'  => 'prospergenics_impact',
        'type'     => 'text',
    ));

    // Stat 2 Number
    $wp_customize->add_setting('prospergenics_stat2_number', array(
        'default'           => '25+',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_stat2_number', array(
        'label'    => __('Stat 2 Number', 'prospergenics'),
        'section'  => 'prospergenics_impact',
        'type'     => 'text',
    ));

    // Stat 2 Label
    $wp_customize->add_setting('prospergenics_stat2_label', array(
        'default'           => 'Communities Impacted',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_stat2_label', array(
        'label'    => __('Stat 2 Label', 'prospergenics'),
        'section'  => 'prospergenics_impact',
        'type'     => 'text',
    ));

    // Stat 3 Number
    $wp_customize->add_setting('prospergenics_stat3_number', array(
        'default'           => '100+',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_stat3_number', array(
        'label'    => __('Stat 3 Number', 'prospergenics'),
        'section'  => 'prospergenics_impact',
        'type'     => 'text',
    ));

    // Stat 3 Label
    $wp_customize->add_setting('prospergenics_stat3_label', array(
        'default'           => 'Successful Projects',
        'sanitize_callback' => 'sanitize_text_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_stat3_label', array(
        'label'    => __('Stat 3 Label', 'prospergenics'),
        'section'  => 'prospergenics_impact',
        'type'     => 'text',
    ));

    // Footer Section
    $wp_customize->add_section('prospergenics_footer', array(
        'title'    => __('Footer', 'prospergenics'),
        'priority' => 60,
    ));

    // Footer Text
    $wp_customize->add_setting('prospergenics_footer_text', array(
        'default'           => 'Building prosperity together through authentic collaboration and shared skills.',
        'sanitize_callback' => 'sanitize_textarea_field',
        'transport'         => 'postMessage',
    ));

    $wp_customize->add_control('prospergenics_footer_text', array(
        'label'    => __('Footer Description', 'prospergenics'),
        'section'  => 'prospergenics_footer',
        'type'     => 'textarea',
    ));
}
add_action('customize_register', 'prospergenics_customize_register');

/**
 * Render the site title for the selective refresh partial
 */
function prospergenics_customize_partial_blogname() {
    bloginfo('name');
}

/**
 * Render the site tagline for the selective refresh partial
 */
function prospergenics_customize_partial_blogdescription() {
    bloginfo('description');
}

/**
 * Binds JS handlers to make Theme Customizer preview reload changes asynchronously
 */
function prospergenics_customize_preview_js() {
    // Ensure 'wp-util' and 'jquery' and 'customize-preview' are dependencies
    wp_enqueue_script(
        'prospergenics-customizer',
        get_template_directory_uri() . '/js/customizer.js',
        array('jquery', 'customize-preview', 'wp-util'),
        '1.0.1',
        true
    );
}
add_action('customize_preview_init', 'prospergenics_customize_preview_js');

/**
 * Output custom CSS for customizer settings
 */
function prospergenics_customizer_css() {
    $primary_color = get_theme_mod('prospergenics_primary_color', '#2E8B57');
    $accent_color = get_theme_mod('prospergenics_accent_color', '#FFD700');
    $hero_background = get_theme_mod('prospergenics_hero_background', '');

    ?>
    <style type="text/css">
        :root {
            --primary-green: <?php echo esc_attr($primary_color); ?>;
            --accent-gold: <?php echo esc_attr($accent_color); ?>;
        }
        
        <?php if ($hero_background) : ?>
        .hero-section {
            background-image: linear-gradient(135deg, rgba(46, 139, 87, 0.8) 0%, rgba(30, 95, 63, 0.8) 100%), url(<?php echo esc_url($hero_background); ?>);
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }
        <?php endif; ?>
    </style>
    <?php
}
add_action('wp_head', 'prospergenics_customizer_css');


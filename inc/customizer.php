<?php
/**
 * Prospergenics Theme Customizer
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

    // Homepage Sections Panel
    $wp_customize->add_panel('prospergenics_homepage_panel', array(
        'title'       => __('Homepage Sections', 'prospergenics'),
        'priority'    => 35,
        'description' => __('Homepage About, Programs, and CTA content', 'prospergenics'),
    ));

    // About Section
    $wp_customize->add_section('prospergenics_about_section', array(
        'title'    => __('About Section', 'prospergenics'),
        'panel'    => 'prospergenics_homepage_panel',
        'priority' => 1,
    ));
    $wp_customize->add_setting('prospergenics_about_title', array(
        'default'           => 'About Prospergenics',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_setting('prospergenics_about_text', array(
        'default'           => 'Prospergenics is a grassroots community where people from all backgrounds come together to learn, grow, and support each other on the journey to prosperity. We believe that prosperity is not reserved for a select few—it\'s something anyone can learn, develop, and share. Powered by an active team in Kenya and the Netherlands, Prospergenics is built on real connection and practical learning. Our members collaborate in real projects, share knowledge, and empower each other. Whether you are starting your career, looking to improve your skills, or want to give back—at Prospergenics, you are not alone.',
        'sanitize_callback' => 'sanitize_textarea_field',
    ));
    $wp_customize->add_control('prospergenics_about_title', array(
        'label'    => __('About Section Title', 'prospergenics'),
        'section'  => 'prospergenics_about_section',
        'type'     => 'text',
    ));
    $wp_customize->add_control('prospergenics_about_text', array(
        'label'    => __('About Section Text', 'prospergenics'),
        'section'  => 'prospergenics_about_section',
        'type'     => 'textarea',
    ));
    $wp_customize->add_setting('prospergenics_about_image', array(
        'default'           => '',
        'sanitize_callback' => 'esc_url_raw',
    ));
    $wp_customize->add_control(new WP_Customize_Image_Control($wp_customize, 'prospergenics_about_image', array(
        'label'    => __('About Section Image', 'prospergenics'),
        'section'  => 'prospergenics_about_section',
        'settings' => 'prospergenics_about_image',
    )));

    // Programs Section
    $wp_customize->add_section('prospergenics_programs_section', array(
        'title'    => __('Programs Section', 'prospergenics'),
        'panel'    => 'prospergenics_homepage_panel',
        'priority' => 2,
    ));
    $wp_customize->add_setting('prospergenics_programs_title', array(
        'default'           => 'Our Programs',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_setting('prospergenics_programs_desc', array(
        'default'           => '',
        'sanitize_callback' => 'sanitize_textarea_field',
    ));
    $wp_customize->add_control('prospergenics_programs_title', array(
        'label'    => __('Programs Section Title', 'prospergenics'),
        'section'  => 'prospergenics_programs_section',
        'type'     => 'text',
    ));
    $wp_customize->add_control('prospergenics_programs_desc', array(
        'label'    => __('Programs Section Description', 'prospergenics'),
        'section'  => 'prospergenics_programs_section',
        'type'     => 'textarea',
    ));
    // Allow editing of each of the three cards (you can increase/decrease number if changed)
    for ($i = 1; $i <= 3; $i++) {
        $wp_customize->add_setting("prospergenics_program{$i}_icon", array(
            'default'           => ($i == 1 ? '💻' : ($i == 2 ? '🌱' : '📈')),
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_setting("prospergenics_program{$i}_title", array(
            'default'           => ($i == 1 ? 'AI & Technology Training' : ($i == 2 ? 'Community Development' : 'Entrepreneurship Support')),
            'sanitize_callback' => 'sanitize_text_field',
        ));
        $wp_customize->add_setting("prospergenics_program{$i}_desc", array(
            'default'           => ($i == 1 ? 'Learn cutting-edge AI and software development skills that open doors to new opportunities and economic empowerment.' : ($i == 2 ? 'Participate in real projects that create lasting impact in communities while building valuable skills and connections.' : 'Get guidance, resources, and mentorship to turn your ideas into sustainable businesses that benefit your community.')),
            'sanitize_callback' => 'sanitize_textarea_field',
        ));
        $wp_customize->add_setting("prospergenics_program{$i}_link", array(
            'default'           => '#',
            'sanitize_callback' => 'esc_url_raw',
        ));
        $wp_customize->add_control("prospergenics_program{$i}_icon", array(
            'label'    => __('Program ' . $i . ' Icon', 'prospergenics'),
            'section'  => 'prospergenics_programs_section',
            'type'     => 'text',
        ));
        $wp_customize->add_control("prospergenics_program{$i}_title", array(
            'label'    => __('Program ' . $i . ' Title', 'prospergenics'),
            'section'  => 'prospergenics_programs_section',
            'type'     => 'text',
        ));
        $wp_customize->add_control("prospergenics_program{$i}_desc", array(
            'label'    => __('Program ' . $i . ' Description', 'prospergenics'),
            'section'  => 'prospergenics_programs_section',
            'type'     => 'textarea',
        ));
        $wp_customize->add_control("prospergenics_program{$i}_link", array(
            'label'    => __('Program ' . $i . ' Link', 'prospergenics'),
            'section'  => 'prospergenics_programs_section',
            'type'     => 'url',
        ));
    }

    // Final CTA Section
    $wp_customize->add_section('prospergenics_final_cta_section', array(
        'title'    => __('Final CTA Section', 'prospergenics'),
        'panel'    => 'prospergenics_homepage_panel',
        'priority' => 3,
    ));
    $wp_customize->add_setting('prospergenics_final_cta_title', array(
        'default'           => 'Start Your Journey to Prosperity Today',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_setting('prospergenics_final_cta_text', array(
        'default'           => 'Join thousands of individuals who are building a better future for themselves and their communities.',
        'sanitize_callback' => 'sanitize_textarea_field',
    ));
    $wp_customize->add_control('prospergenics_final_cta_title', array(
        'label'    => __('Final CTA Title', 'prospergenics'),
        'section'  => 'prospergenics_final_cta_section',
        'type'     => 'text',
    ));
    $wp_customize->add_control('prospergenics_final_cta_text', array(
        'label'    => __('Final CTA Text', 'prospergenics'),
        'section'  => 'prospergenics_final_cta_section',
        'type'     => 'textarea',
    ));
    $wp_customize->add_setting('prospergenics_final_cta_primary', array(
        'default'           => 'Join the Community',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_setting('prospergenics_final_cta_primary_link', array(
        'default'           => '#',
        'sanitize_callback' => 'esc_url_raw',
    ));
    $wp_customize->add_setting('prospergenics_final_cta_secondary', array(
        'default'           => 'Browse Free Trainings',
        'sanitize_callback' => 'sanitize_text_field',
    ));
    $wp_customize->add_setting('prospergenics_final_cta_secondary_link', array(
        'default'           => '#',
        'sanitize_callback' => 'esc_url_raw',
    ));
    $wp_customize->add_control('prospergenics_final_cta_primary', array(
        'label'    => __('Primary CTA Button Text', 'prospergenics'),
        'section'  => 'prospergenics_final_cta_section',
        'type'     => 'text',
    ));
    $wp_customize->add_control('prospergenics_final_cta_primary_link', array(
        'label'    => __('Primary CTA Link', 'prospergenics'),
        'section'  => 'prospergenics_final_cta_section',
        'type'     => 'url',
    ));
    $wp_customize->add_control('prospergenics_final_cta_secondary', array(
        'label'    => __('Secondary CTA Button Text', 'prospergenics'),
        'section'  => 'prospergenics_final_cta_section',
        'type'     => 'text',
    ));
    $wp_customize->add_control('prospergenics_final_cta_secondary_link', array(
        'label'    => __('Secondary CTA Link', 'prospergenics'),
        'section'  => 'prospergenics_final_cta_section',
        'type'     => 'url',
    ));

}
add_action('customize_register', 'prospergenics_customize_register');

function prospergenics_customize_partial_blogname() {
    bloginfo('name');
}
function prospergenics_customize_partial_blogdescription() {
    bloginfo('description');
}
function prospergenics_customize_preview_js() {
    wp_enqueue_script(
        'prospergenics-customizer',
        get_template_directory_uri() . '/js/customizer.js',
        array('jquery', 'customize-preview', 'wp-util'),
        '1.0.1',
        true
    );
}
add_action('customize_preview_init', 'prospergenics_customize_preview_js');
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

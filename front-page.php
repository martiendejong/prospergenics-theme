<?php
/**
 * Front Page Template - Parallax Homepage
 *
 * @package Prospergenics
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main" role="main">

    <!-- Hero Section -->
    <section class="parallax-section hero-section" style="background-image: url('<?php echo esc_url( get_theme_mod( 'prospergenics_hero_bg', get_template_directory_uri() . '/images/hero-bg.jpg' ) ); ?>'); background-position: center 10% !important;" aria-label="<?php esc_attr_e( 'Hero Section', 'prospergenics' ); ?>">
        <div class="parallax-content">
            <h1><?php echo esc_html( get_theme_mod( 'prospergenics_hero_headline', __( 'Building Prosperity Together', 'prospergenics' ) ) ); ?></h1>
            <p class="tagline"><?php echo esc_html( get_theme_mod( 'prospergenics_hero_tagline', __( 'Empowering Futures, Cultivating Growth', 'prospergenics' ) ) ); ?></p>
            <p><?php echo esc_html( get_theme_mod( 'prospergenics_hero_description', __( 'Join our diverse community where learning, teamwork, and growth lead to real success.', 'prospergenics' ) ) ); ?></p>
            <a href="#about" class="cta-button"><?php esc_html_e( 'Learn More', 'prospergenics' ); ?></a>
            <a href="#contact" class="cta-button secondary"><?php esc_html_e( 'Get Involved', 'prospergenics' ); ?></a>
        </div>
    </section>

    <!-- Core Values Section -->
    <section id="values" class="content-section">
        <div class="section-header">
            <h2><?php esc_html_e( 'Our Core Values', 'prospergenics' ); ?></h2>
            <p><?php esc_html_e( 'The principles that guide everything we do', 'prospergenics' ); ?></p>
        </div>

        <div class="cards-grid">
            <div class="card">
                <div class="icon" aria-hidden="true">👂</div>
                <h3><?php esc_html_e( 'We Listen and Act', 'prospergenics' ); ?></h3>
                <p><?php esc_html_e( 'We respond quickly to member needs, ensuring everyone feels heard and supported in their journey.', 'prospergenics' ); ?></p>
            </div>

            <div class="card">
                <div class="icon" aria-hidden="true">✓</div>
                <h3><?php esc_html_e( 'Quality You Can Trust', 'prospergenics' ); ?></h3>
                <p><?php esc_html_e( 'Practical learning and tangible results that make a real difference in your life and career.', 'prospergenics' ); ?></p>
            </div>

            <div class="card">
                <div class="icon" aria-hidden="true">🤝</div>
                <h3><?php esc_html_e( 'Support in Every Step', 'prospergenics' ); ?></h3>
                <p><?php esc_html_e( 'Our community has your back, providing guidance, resources, and encouragement throughout your journey.', 'prospergenics' ); ?></p>
            </div>
        </div>
    </section>

    <!-- About Section - Parallax -->
    <section id="about" class="parallax-section about-parallax" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/images/about-bg.jpg' ); ?>');" aria-label="<?php esc_attr_e( 'About Us', 'prospergenics' ); ?>">
        <div class="parallax-content">
            <h2><?php esc_html_e( 'Who We Are', 'prospergenics' ); ?></h2>
            <p class="tagline"><?php esc_html_e( 'A Grassroots Community Building Real Success', 'prospergenics' ); ?></p>
        </div>
    </section>

    <!-- About Content Section -->
    <section class="content-section alt-bg">
        <div class="about-content">
            <p><?php esc_html_e( 'Prospergenics is a grassroots community of individuals from diverse backgrounds who have come together with a shared vision: to build prosperity through learning, collaboration, and mutual support.', 'prospergenics' ); ?></p>

            <p><?php esc_html_e( 'We are not a traditional organization with top-down structures. Instead, we are a network of peers who believe in the power of collective growth. Our members come from Kenya, the Netherlands, and beyond, bringing unique perspectives, skills, and experiences to the table.', 'prospergenics' ); ?></p>

            <p><?php esc_html_e( 'What unites us is our commitment to practical action. We focus on tangible outcomes: building AI skills, creating sustainable businesses, and supporting each other through challenges. Every member contributes to our collective knowledge and every success story inspires the next.', 'prospergenics' ); ?></p>

            <p><?php esc_html_e( 'This is not about creating dependency on a single leader or organization. It is about empowering each member to become a leader in their own right, equipped with the tools, knowledge, and confidence to create lasting change in their communities.', 'prospergenics' ); ?></p>
        </div>
    </section>

    <!-- Team Section -->
    <section class="content-section">
        <div class="section-header">
            <h2><?php esc_html_e( 'Our Team', 'prospergenics' ); ?></h2>
            <p><?php esc_html_e( 'The core team building Prospergenics together', 'prospergenics' ); ?></p>
        </div>

        <div class="team-grid">
            <?php
            // Core team 2024-2025: Lessy (first), Frank, Diko, Sandra, Sonia, Timothy, Toperian
            $core_team = array(18, 17, 19, 16, 37, 38, 41);
            foreach ($core_team as $member_id) {
                $post = get_post($member_id);
                $thumbnail_url = get_the_post_thumbnail_url($member_id, 'medium');
                if ($post && $thumbnail_url) {
                    $first_name = explode(' ', $post->post_title)[0];
                    ?>
                    <div class="team-member card">
                        <div class="team-photo" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');"></div>
                        <h3><?php echo esc_html($post->post_title); ?></h3>
                        <p><?php echo esc_html(wp_trim_words($post->post_content, 25)); ?></p>
                        <a href="<?php echo get_permalink($member_id); ?>" class="learn-more">Meet <?php echo esc_html($first_name); ?> →</a>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </section>

    <!-- Coaches Section -->
    <section class="content-section alt-bg">
        <div class="section-header">
            <h2><?php esc_html_e( 'Coaches', 'prospergenics' ); ?></h2>
            <p><?php esc_html_e( 'Experienced mentors guiding the next generation', 'prospergenics' ); ?></p>
        </div>

        <div class="team-grid">
            <?php
            $coaches = array(20); // Martien
            foreach ($coaches as $member_id) {
                $post = get_post($member_id);
                $thumbnail_url = get_the_post_thumbnail_url($member_id, 'medium');
                if ($post && $thumbnail_url) {
                    $first_name = explode(' ', $post->post_title)[0];
                    ?>
                    <div class="team-member card">
                        <div class="team-photo" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');"></div>
                        <h3><?php echo esc_html($post->post_title); ?></h3>
                        <p><?php echo esc_html(wp_trim_words($post->post_content, 25)); ?></p>
                        <a href="<?php echo get_permalink($member_id); ?>" class="learn-more">Meet <?php echo esc_html($first_name); ?> →</a>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </section>

    <!-- Community Section -->
    <section class="content-section">
        <div class="section-header">
            <h2><?php esc_html_e( 'Community', 'prospergenics' ); ?></h2>
            <p><?php esc_html_e( 'Members learning and growing with Prospergenics', 'prospergenics' ); ?></p>
        </div>

        <div class="team-grid">
            <?php
            // Community members: Farid, Sofy, Natumi, Maxwell, Faith
            $community_members = array(45, 46, 47, 48, 49);
            foreach ($community_members as $member_id) {
                $post = get_post($member_id);
                $thumbnail_url = get_the_post_thumbnail_url($member_id, 'medium');
                if ($post && $thumbnail_url) {
                    $first_name = explode(' ', $post->post_title)[0];
                    ?>
                    <div class="team-member card">
                        <div class="team-photo" style="background-image: url('<?php echo esc_url($thumbnail_url); ?>');"></div>
                        <h3><?php echo esc_html($post->post_title); ?></h3>
                        <p><?php echo esc_html(wp_trim_words($post->post_content, 25)); ?></p>
                        <a href="<?php echo get_permalink($member_id); ?>" class="learn-more">Meet <?php echo esc_html($first_name); ?> →</a>
                    </div>
                    <?php
                }
            }

            // Show placeholder if no community members yet
            if (empty($community_members)) {
                ?>
                <p style="text-align: center; width: 100%; color: #666;">
                    <?php esc_html_e( 'Our community is growing! More members coming soon.', 'prospergenics' ); ?>
                </p>
                <?php
            }
            ?>
        </div>
    </section>

    <!-- Programs Section - Parallax -->
    <section id="programs" class="parallax-section programs-parallax" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/images/programs-bg.jpg' ); ?>');" aria-label="<?php esc_attr_e( 'Our Programs', 'prospergenics' ); ?>">
        <div class="parallax-content">
            <h2><?php esc_html_e( 'Our Programs', 'prospergenics' ); ?></h2>
            <p class="tagline"><?php esc_html_e( 'Practical Learning for Real-World Impact', 'prospergenics' ); ?></p>
        </div>
    </section>

    <!-- Programs Content Section -->
    <section class="content-section">
        <div class="programs-grid">
            <div class="program-card">
                <div class="program-image" style="background: linear-gradient(135deg, #2E8B57, #3da968);">
                    <!-- Placeholder for program image -->
                </div>
                <div class="program-content">
                    <h3><?php esc_html_e( 'AI & Technology Training', 'prospergenics' ); ?></h3>
                    <p><?php esc_html_e( 'Master cutting-edge AI tools and technologies through hands-on projects. Learn from real-world applications and build skills that matter in today\'s job market.', 'prospergenics' ); ?></p>
                    <a href="<?php echo esc_url( home_url( '/programs/ai-training' ) ); ?>" class="learn-more">
                        <?php esc_html_e( 'Learn More', 'prospergenics' ); ?> →
                    </a>
                </div>
            </div>

            <div class="program-card">
                <div class="program-image" style="background: linear-gradient(135deg, #b45309, #d97706);">
                    <!-- Placeholder for program image -->
                </div>
                <div class="program-content">
                    <h3><?php esc_html_e( 'Community Development', 'prospergenics' ); ?></h3>
                    <p><?php esc_html_e( 'Build strong, sustainable communities through collaborative projects. Learn leadership, project management, and community organizing skills.', 'prospergenics' ); ?></p>
                    <a href="<?php echo esc_url( home_url( '/programs/community-development' ) ); ?>" class="learn-more">
                        <?php esc_html_e( 'Learn More', 'prospergenics' ); ?> →
                    </a>
                </div>
            </div>

            <div class="program-card">
                <div class="program-image" style="background: linear-gradient(135deg, #2E8B57, #b45309);">
                    <!-- Placeholder for program image -->
                </div>
                <div class="program-content">
                    <h3><?php esc_html_e( 'Entrepreneurship Support', 'prospergenics' ); ?></h3>
                    <p><?php esc_html_e( 'Turn your ideas into sustainable businesses. Get mentorship, resources, and a supportive network to help you succeed.', 'prospergenics' ); ?></p>
                    <a href="<?php echo esc_url( home_url( '/programs/entrepreneurship' ) ); ?>" class="learn-more">
                        <?php esc_html_e( 'Learn More', 'prospergenics' ); ?> →
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="content-section alt-bg">
        <div class="section-header">
            <h2><?php esc_html_e( 'Our Impact', 'prospergenics' ); ?></h2>
            <p><?php esc_html_e( 'Real numbers, real results', 'prospergenics' ); ?></p>
        </div>

        <div class="stats-grid">
            <div class="stat-item">
                <div class="stat-number">100+</div>
                <div class="stat-label"><?php esc_html_e( 'Active Members', 'prospergenics' ); ?></div>
            </div>

            <div class="stat-item">
                <div class="stat-number">6</div>
                <div class="stat-label"><?php esc_html_e( 'Core Programs', 'prospergenics' ); ?></div>
            </div>

            <div class="stat-item">
                <div class="stat-number">2</div>
                <div class="stat-label"><?php esc_html_e( 'Countries', 'prospergenics' ); ?></div>
            </div>
        </div>
    </section>

    <!-- Contact Section - Parallax -->
    <section id="contact" class="parallax-section contact-parallax" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/images/contact-bg.jpg' ); ?>');" aria-label="<?php esc_attr_e( 'Contact Us', 'prospergenics' ); ?>">
        <div class="parallax-content">
            <h2><?php esc_html_e( 'Get Involved', 'prospergenics' ); ?></h2>
            <p class="tagline"><?php esc_html_e( 'Join our community and start your journey today', 'prospergenics' ); ?></p>
        </div>
    </section>

    <!-- Contact Form Section -->
    <section class="content-section">
        <div class="contact-form">
            <?php
            // Check if Contact Form 7 is active
            if ( function_exists( 'wpcf7_contact_form' ) ) {
                // Display Contact Form 7 form (shortcode should be added via admin)
                echo do_shortcode( '[contact-form-7 id="1" title="Contact form"]' );
            } else {
                // Fallback to basic HTML form
                ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="prospergenics-contact-form">
                    <input type="hidden" name="action" value="prospergenics_contact_form">
                    <?php wp_nonce_field( 'prospergenics_contact_form_nonce', 'contact_form_nonce' ); ?>

                    <div class="form-group">
                        <label for="contact-name"><?php esc_html_e( 'Name', 'prospergenics' ); ?> *</label>
                        <input type="text" id="contact-name" name="contact_name" required>
                    </div>

                    <div class="form-group">
                        <label for="contact-email"><?php esc_html_e( 'Email', 'prospergenics' ); ?> *</label>
                        <input type="email" id="contact-email" name="contact_email" required>
                    </div>

                    <div class="form-group">
                        <label for="contact-phone"><?php esc_html_e( 'Phone', 'prospergenics' ); ?></label>
                        <input type="tel" id="contact-phone" name="contact_phone">
                    </div>

                    <div class="form-group">
                        <label for="contact-subject"><?php esc_html_e( 'Subject', 'prospergenics' ); ?> *</label>
                        <input type="text" id="contact-subject" name="contact_subject" required>
                    </div>

                    <div class="form-group">
                        <label for="contact-message"><?php esc_html_e( 'Message', 'prospergenics' ); ?> *</label>
                        <textarea id="contact-message" name="contact_message" required></textarea>
                    </div>

                    <button type="submit" class="submit-button">
                        <?php esc_html_e( 'Send Message', 'prospergenics' ); ?>
                    </button>
                </form>
                <?php
            }
            ?>
        </div>
    </section>

</main>

<?php get_footer(); ?>

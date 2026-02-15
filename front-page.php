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
    <section class="parallax-section hero-section" style="background-image: url('<?php echo esc_url( get_theme_mod( 'prospergenics_hero_bg', get_template_directory_uri() . '/images/hero-bg.jpg' ) ); ?>');" aria-label="<?php esc_attr_e( 'Hero Section', 'prospergenics' ); ?>">
        <div class="parallax-content">
            <h1><?php echo esc_html( get_theme_mod( 'prospergenics_hero_headline', __( 'Building Prosperity Together', 'prospergenics' ) ) ); ?></h1>
            <p class="tagline"><?php echo esc_html( get_theme_mod( 'prospergenics_hero_tagline', __( 'Empowering Futures, Cultivating Growth', 'prospergenics' ) ) ); ?></p>
            <p><?php echo esc_html( get_theme_mod( 'prospergenics_hero_description', __( 'Join our diverse community where learning, teamwork, and growth lead to real success.', 'prospergenics' ) ) ); ?></p>
            <a href="#about" class="cta-button"><?php esc_html_e( 'Learn More', 'prospergenics' ); ?></a>
            <a href="#contact" class="cta-button secondary"><?php esc_html_e( 'Get Involved', 'prospergenics' ); ?></a>
        </div>
    </section>

    <!-- What is Prospergenics Section -->
    <section id="about-intro" class="content-section">
        <div class="section-header">
            <h2><?php esc_html_e( 'What is Prospergenics?', 'prospergenics' ); ?></h2>
        </div>

        <div class="intro-content" style="max-width: 800px; margin: 0 auto; text-align: center; font-size: 1.1rem; line-height: 1.8;">
            <p><?php esc_html_e( 'Prospergenics is a community where members and coaches support each other to realize their potential and create opportunities together. Participants receive training and guidance in entrepreneurship, enabling them to learn while earning income at the same time.', 'prospergenics' ); ?></p>

            <p><?php esc_html_e( 'We believe in practical action over theory. Our members develop real skills, build actual businesses, and achieve tangible results through mutual support and experienced mentorship.', 'prospergenics' ); ?></p>
        </div>
    </section>

    <!-- What We Do Section - Parallax -->
    <section id="about" class="parallax-section about-parallax" style="background-image: url('<?php echo esc_url( get_template_directory_uri() . '/images/about-bg.jpg' ); ?>');" aria-label="<?php esc_attr_e( 'What We Do', 'prospergenics' ); ?>">
        <div class="parallax-content">
            <h2><?php esc_html_e( 'What We Do', 'prospergenics' ); ?></h2>
            <p class="tagline"><?php esc_html_e( 'Practical Programs for Real Impact', 'prospergenics' ); ?></p>
        </div>
    </section>

    <!-- Programs Overview Section -->
    <section class="content-section alt-bg">
        <div class="programs-overview-grid">
            <?php
            $programs_query = new WP_Query( array(
                'post_type' => 'program',
                'posts_per_page' => -1,
                'orderby' => 'menu_order',
                'order' => 'ASC',
            ) );

            if ( $programs_query->have_posts() ) :
                while ( $programs_query->have_posts() ) : $programs_query->the_post();
                    $thumbnail_url = get_the_post_thumbnail_url( get_the_ID(), 'large' );
                    $placeholder_style = $thumbnail_url ? "background-image: url('" . esc_url( $thumbnail_url ) . "');" : "background: linear-gradient(135deg, #2E8B57, #3da968);";

                    $button1_text = get_post_meta( get_the_ID(), '_program_button1_text', true );
                    $button1_url = get_post_meta( get_the_ID(), '_program_button1_url', true );
                    $button1_style = get_post_meta( get_the_ID(), '_program_button1_style', true ) ?: 'primary';

                    $button2_text = get_post_meta( get_the_ID(), '_program_button2_text', true );
                    $button2_url = get_post_meta( get_the_ID(), '_program_button2_url', true );
                    $button2_style = get_post_meta( get_the_ID(), '_program_button2_style', true ) ?: 'secondary';
                    ?>
                    <div class="program-overview-card">
                        <div class="program-overview-image" style="<?php echo $placeholder_style; ?>"></div>
                        <div class="program-overview-content">
                            <h3><?php the_title(); ?></h3>
                            <p><?php echo esc_html( get_the_content() ); ?></p>
                            <div class="program-overview-actions">
                                <?php if ( $button1_text && $button1_url ) : ?>
                                    <a href="<?php echo esc_url( $button1_url ); ?>"
                                       <?php echo ( strpos( $button1_url, 'http' ) === 0 && strpos( $button1_url, home_url() ) === false ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                                       class="program-action-link <?php echo esc_attr( $button1_style ); ?>">
                                        <?php echo esc_html( $button1_text ); ?> →
                                    </a>
                                <?php endif; ?>

                                <?php if ( $button2_text && $button2_url ) : ?>
                                    <a href="<?php echo esc_url( $button2_url ); ?>"
                                       <?php echo ( strpos( $button2_url, 'http' ) === 0 && strpos( $button2_url, home_url() ) === false ) ? 'target="_blank" rel="noopener noreferrer"' : ''; ?>
                                       class="program-action-link <?php echo esc_attr( $button2_style ); ?>">
                                        <?php echo esc_html( $button2_text ); ?> →
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
            endif;
            ?>
        </div>
    </section>

    <!-- Team Section -->
    <section id="team" class="content-section">
        <div class="section-header">
            <h2><?php esc_html_e( 'Our Team', 'prospergenics' ); ?></h2>
            <p><?php esc_html_e( 'The core team building Prospergenics together', 'prospergenics' ); ?></p>
        </div>

        <div class="team-grid">
            <?php
            $team_query = new WP_Query( array(
                'post_type' => 'team_member',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'member_type',
                        'field' => 'slug',
                        'terms' => 'team',
                    ),
                ),
            ) );

            if ( $team_query->have_posts() ) :
                while ( $team_query->have_posts() ) : $team_query->the_post();
                    $thumbnail_url = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
                    $first_name = explode( ' ', get_the_title() )[0];
                    $placeholder_style = $thumbnail_url ? "background-image: url('" . esc_url( $thumbnail_url ) . "');" : "background: linear-gradient(135deg, #2E8B57, #3da968);";
                    ?>
                    <div class="team-member card">
                        <div class="team-photo" style="<?php echo $placeholder_style; ?>"></div>
                        <h3><?php the_title(); ?></h3>
                        <p><?php echo esc_html( wp_trim_words( get_the_content(), 25 ) ); ?></p>
                        <a href="<?php the_permalink(); ?>" class="learn-more">Meet <?php echo esc_html( $first_name ); ?> →</a>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
            endif;
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
            $coaches_query = new WP_Query( array(
                'post_type' => 'team_member',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'member_type',
                        'field' => 'slug',
                        'terms' => 'coach',
                    ),
                ),
            ) );

            if ( $coaches_query->have_posts() ) :
                while ( $coaches_query->have_posts() ) : $coaches_query->the_post();
                    $thumbnail_url = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
                    $first_name = explode( ' ', get_the_title() )[0];
                    $placeholder_style = $thumbnail_url ? "background-image: url('" . esc_url( $thumbnail_url ) . "');" : "background: linear-gradient(135deg, #2E8B57, #3da968);";
                    ?>
                    <div class="team-member card">
                        <div class="team-photo" style="<?php echo $placeholder_style; ?>"></div>
                        <h3><?php the_title(); ?></h3>
                        <p><?php echo esc_html( wp_trim_words( get_the_content(), 25 ) ); ?></p>
                        <a href="<?php the_permalink(); ?>" class="learn-more">Meet <?php echo esc_html( $first_name ); ?> →</a>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
            endif;
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
            $community_query = new WP_Query( array(
                'post_type' => 'team_member',
                'posts_per_page' => -1,
                'tax_query' => array(
                    array(
                        'taxonomy' => 'member_type',
                        'field' => 'slug',
                        'terms' => 'community',
                    ),
                ),
            ) );

            if ( $community_query->have_posts() ) :
                while ( $community_query->have_posts() ) : $community_query->the_post();
                    $thumbnail_url = get_the_post_thumbnail_url( get_the_ID(), 'medium' );
                    $first_name = explode( ' ', get_the_title() )[0];
                    $placeholder_style = $thumbnail_url ? "background-image: url('" . esc_url( $thumbnail_url ) . "');" : "background: linear-gradient(135deg, #b45309, #d97706);";
                    ?>
                    <div class="team-member card">
                        <div class="team-photo" style="<?php echo $placeholder_style; ?>"></div>
                        <h3><?php the_title(); ?></h3>
                        <p><?php echo esc_html( wp_trim_words( get_the_content(), 25 ) ); ?></p>
                        <a href="<?php the_permalink(); ?>" class="learn-more">Meet <?php echo esc_html( $first_name ); ?> →</a>
                    </div>
                    <?php
                endwhile;
                wp_reset_postdata();
            else :
                ?>
                <p style="text-align: center; width: 100%; color: #666;">
                    <?php esc_html_e( 'Our community is growing! More members coming soon.', 'prospergenics' ); ?>
                </p>
                <?php
            endif;
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
                    <a href="<?php echo esc_url( home_url( '/blog' ) ); ?>" class="learn-more">
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
                    <a href="<?php echo esc_url( home_url( '/blog' ) ); ?>" class="learn-more">
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
                    <a href="#contact" class="learn-more">
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
                <div class="stat-number">15+</div>
                <div class="stat-label"><?php esc_html_e( 'Members', 'prospergenics' ); ?></div>
            </div>

            <div class="stat-item">
                <div class="stat-number">€100,000+</div>
                <div class="stat-label"><?php esc_html_e( 'Community Wealth Generated', 'prospergenics' ); ?></div>
            </div>

            <div class="stat-item">
                <div class="stat-number">25+</div>
                <div class="stat-label"><?php esc_html_e( 'Trainings Completed', 'prospergenics' ); ?></div>
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

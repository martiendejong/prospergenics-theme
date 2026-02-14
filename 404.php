<?php
/**
 * 404 Error Template
 *
 * @package Prospergenics
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main" role="main">
    <section class="content-section" style="margin-top: 100px; min-height: 60vh;">
        <div class="section-header">
            <h1><?php esc_html_e( '404 - Page Not Found', 'prospergenics' ); ?></h1>
            <p><?php esc_html_e( 'The page you are looking for might have been removed, had its name changed, or is temporarily unavailable.', 'prospergenics' ); ?></p>
        </div>

        <div style="max-width: 600px; margin: 3rem auto; text-align: center;">
            <p><?php esc_html_e( 'Try searching for what you need:', 'prospergenics' ); ?></p>
            <?php get_search_form(); ?>

            <div style="margin-top: 3rem;">
                <h3><?php esc_html_e( 'Or visit these pages:', 'prospergenics' ); ?></h3>
                <ul style="list-style: none; padding: 0; margin-top: 1rem;">
                    <li style="margin-bottom: 0.5rem;">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="cta-button">
                            <?php esc_html_e( 'Go to Homepage', 'prospergenics' ); ?>
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </section>
</main>

<?php get_footer(); ?>

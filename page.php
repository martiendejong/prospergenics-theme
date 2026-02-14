<?php
/**
 * Page Template
 *
 * @package Prospergenics
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main" role="main">
    <?php while ( have_posts() ) : the_post(); ?>
        <article id="post-<?php the_ID(); ?>" <?php post_class( 'single-post' ); ?>>
            <?php if ( has_post_thumbnail() ) : ?>
                <div class="post-featured-image">
                    <?php the_post_thumbnail( 'prospergenics-hero', array( 'class' => 'featured-image' ) ); ?>
                </div>
            <?php endif; ?>

            <header class="post-header">
                <h1><?php the_title(); ?></h1>
            </header>

            <div class="post-body">
                <?php
                the_content();

                wp_link_pages( array(
                    'before' => '<div class="page-links">' . esc_html__( 'Pages:', 'prospergenics' ),
                    'after'  => '</div>',
                ) );
                ?>
            </div>

            <?php
            // Comments (if enabled on pages)
            if ( comments_open() || get_comments_number() ) {
                comments_template();
            }
            ?>
        </article>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>

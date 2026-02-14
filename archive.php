<?php
/**
 * Archive Template
 *
 * @package Prospergenics
 * @since 1.0.0
 */

get_header();
?>

<main id="main-content" class="site-main" role="main">
    <section class="content-section" style="margin-top: 100px;">
        <div class="section-header">
            <h1><?php the_archive_title(); ?></h1>
            <?php the_archive_description( '<div class="archive-description">', '</div>' ); ?>
        </div>

        <?php if ( have_posts() ) : ?>
            <div class="blog-grid">
                <?php while ( have_posts() ) : the_post(); ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class( 'post-card' ); ?>>
                        <?php if ( has_post_thumbnail() ) : ?>
                            <a href="<?php the_permalink(); ?>" aria-label="<?php the_title_attribute(); ?>">
                                <?php the_post_thumbnail( 'prospergenics-card', array( 'class' => 'post-thumbnail' ) ); ?>
                            </a>
                        <?php endif; ?>

                        <div class="post-content">
                            <div class="post-meta">
                                <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                                    <?php echo esc_html( get_the_date() ); ?>
                                </time>
                                <span>•</span>
                                <span><?php the_category( ', ' ); ?></span>
                            </div>

                            <h2>
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_title(); ?>
                                </a>
                            </h2>

                            <div class="post-excerpt">
                                <?php the_excerpt(); ?>
                            </div>

                            <a href="<?php the_permalink(); ?>" class="read-more">
                                <?php esc_html_e( 'Read More', 'prospergenics' ); ?> →
                            </a>
                        </div>
                    </article>
                <?php endwhile; ?>
            </div>

            <?php
            the_posts_pagination( array(
                'mid_size'  => 2,
                'prev_text' => __( '← Previous', 'prospergenics' ),
                'next_text' => __( 'Next →', 'prospergenics' ),
            ) );
            ?>

        <?php else : ?>
            <div class="no-posts">
                <h2><?php esc_html_e( 'Nothing Found', 'prospergenics' ); ?></h2>
                <p><?php esc_html_e( 'No posts found in this archive.', 'prospergenics' ); ?></p>
            </div>
        <?php endif; ?>
    </section>
</main>

<?php get_footer(); ?>

<?php
/**
 * Single Post Template
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
                <div class="post-meta">
                    <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>">
                        <?php echo esc_html( get_the_date() ); ?>
                    </time>
                    <span>•</span>
                    <span><?php the_category( ', ' ); ?></span>
                    <span>•</span>
                    <span><?php the_author(); ?></span>
                </div>

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

            <?php if ( get_the_tags() ) : ?>
                <footer class="post-footer">
                    <div class="post-tags">
                        <?php the_tags( '<strong>' . esc_html__( 'Tags:', 'prospergenics' ) . '</strong> ', ', ' ); ?>
                    </div>
                </footer>
            <?php endif; ?>

            <?php
            // Post navigation
            the_post_navigation( array(
                'prev_text' => '<span class="nav-subtitle">' . esc_html__( 'Previous:', 'prospergenics' ) . '</span> <span class="nav-title">%title</span>',
                'next_text' => '<span class="nav-subtitle">' . esc_html__( 'Next:', 'prospergenics' ) . '</span> <span class="nav-title">%title</span>',
            ) );
            ?>

            <?php
            // Comments
            if ( comments_open() || get_comments_number() ) {
                comments_template();
            }
            ?>
        </article>
    <?php endwhile; ?>
</main>

<?php get_footer(); ?>

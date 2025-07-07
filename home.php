<?php
/**
 * Template file for the blog posts index (Blog Overview)
 * This file is used when Settings > Reading sets a page as Posts page.
 */
get_header(); ?>
<main id="main" class="site-main">
    <section class="blog-section">
        <div class="section-container">
            <h1 class="section-title">Blog</h1>
            <?php if (have_posts()) : ?>
                <div class="blog-grid">
                <?php while (have_posts()) : the_post(); ?>
                    <article id="post-<?php the_ID(); ?>" <?php post_class('blog-post-card'); ?> >
                        <?php if (has_post_thumbnail()) : ?>
                            <div class="blog-post-thumbnail">
                                <a href="<?php the_permalink(); ?>">
                                    <?php the_post_thumbnail('prospergenics-card'); ?>
                                </a>
                            </div>
                        <?php endif; ?>
                        <div class="blog-post-card-content">
                            <h2 class="blog-post-title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                            <div class="blog-post-meta">
                                <span class="post-date"><?php echo get_the_date(); ?></span>
                                <span class="post-author">by <?php the_author(); ?></span>
                            </div>
                            <div class="blog-post-excerpt">
                                <?php the_excerpt(); ?>
                            </div>
                            <a href="<?php the_permalink(); ?>" class="read-more-btn">Read more</a>
                        </div>
                    </article>
                <?php endwhile; ?>
                </div>
                <div class="posts-navigation">
                    <?php the_posts_navigation(); ?>
                </div>
            <?php else : ?>
                <p><?php esc_html_e('No posts found.', 'prospergenics'); ?></p>
            <?php endif; ?>
        </div>
    </section>
</main>
<?php get_footer(); ?>

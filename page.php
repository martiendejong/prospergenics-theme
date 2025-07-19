<?php
/**
 * The template for displaying all single posts
 *
 * @package Prospergenics
 */
get_header(); ?>
<main id="main" class="site-main single-post-main">
    <section class="single-blog-post-section">
        <div class="section-container section-container-single-post">
        <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
            <article id="post-<?php the_ID(); ?>" <?php post_class('single-blog-post-article'); ?> >
                <header class="single-post-header">
                    <h1 class="single-post-title"><?php the_title(); ?></h1>
                    <div class="single-post-meta">
                        <span class="single-post-date">Published: <?php echo get_the_date(); ?></span>
                        <span class="single-post-author">by <?php the_author(); ?></span>
                    </div>
                    <?php if(has_post_thumbnail()): ?>
                        <div class="single-post-thumbnail">
                            <?php the_post_thumbnail('large'); ?>
                        </div>
                    <?php endif; ?>
                </header>
                <div class="single-post-content">
                    <?php the_content(); ?>
                </div>
                <footer class="single-post-footer">
                    <div class="single-post-tags-cats">
                        <div class="post-categories">
                            <?php echo get_the_category_list(', '); ?>
                        </div>
                        <div class="post-tags">
                            <?php echo get_the_tag_list('', ', '); ?>
                        </div>
                    </div>
                </footer>
                <?php if (comments_open() || get_comments_number()) : ?>
                    <div class="single-post-comments">
                        <?php comments_template(); ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endwhile; endif; ?>
        </div>
    </section>
</main>
<?php get_footer(); ?>

<style>
.single-post-main {
    background: #f9f9f9;
    padding-top: 2.5rem;
    min-height: 80vh;
}
.section-container-single-post {
    max-width: 760px;
    margin: 0 auto;
    background: #fff;
    padding: 2.5rem 2rem 2rem 2rem;
    border-radius: 1.2rem;
    box-shadow: 0 3px 24px rgba(46,139,87,0.06);
}
.single-post-header {
    margin-bottom: 1.7rem;
}
.single-post-title {
    font-size: 2.1rem;
    font-weight: 700;
    margin: 0 0 0.5rem 0;
    color: #2E8B57;
}
.single-post-meta {
    color: #999;
    font-size: 0.97rem;
    margin-bottom: 0.8rem;
    display: flex;
    gap: 1.3rem;
}
.single-post-thumbnail img {
    width: 100%;
    max-height: 320px;
    object-fit: cover;
    border-radius: 1.1rem;
    margin-bottom: 1.4rem;
}
.single-post-content {
    font-size: 1.12rem;
    color: #232323;
    line-height: 1.62;
    margin-bottom: 2rem;
}
.single-post-tags-cats {
    margin-bottom: 1.4rem;
    font-size: 0.97rem;
    color: #555;
    display: flex;
    gap: 1.8rem;
}
.single-post-navigation {
    display: flex;
    justify-content: space-between;
    margin-bottom: 1.1rem;
    font-size: 0.96rem;
}
.single-post-navigation a {
    color: #2E8B57;
    text-decoration: underline;
    font-weight: 600;
    transition: color 0.13s;
}
.single-post-navigation a:hover {
    color: #FFD700;
}
.single-post-comments {
    margin-top: 3rem;
    background: #f8f8f8;
    padding: 1.5rem 1rem;
    border-radius: 0.75rem;
}
</style> 
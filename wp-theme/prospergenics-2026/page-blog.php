<?php
defined('ABSPATH') || exit;
// /blog/: the blog posts. On test the "Blog" page is an ordinary page (Settings → Reading has no
// posts page), so it showed only its title and none of the posts (reported 4 Nov). This template
// lists the published posts on that page whatever the Reading setting is; when the page is made
// the posts page, WordPress uses index.php instead and lists the same posts.
get_header();

$pg_blog_paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
$pg_blog       = new WP_Query(array(
    'post_type'           => 'post',
    'post_status'         => 'publish',
    'paged'               => $pg_blog_paged,
    'ignore_sticky_posts' => false,
));
?>

<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>ProsperGenics</div>
    <h1><?php echo esc_html(get_the_title() ?: 'Blog'); ?></h1>
    <p class="ph-excerpt">Stories, lessons and updates from the ProsperGenics community.</p>
  </div>
</div>

<div class="list-wrap">
  <?php if ($pg_blog->have_posts()) : ?>
    <?php while ($pg_blog->have_posts()) : $pg_blog->the_post(); ?>
      <a class="post-card" href="<?php the_permalink(); ?>">
        <h2><?php the_title(); ?></h2>
        <span class="pc-date"><?php echo esc_html(get_the_date('F j, Y')); ?></span>
        <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 32)); ?></p>
      </a>
    <?php endwhile; ?>
    <nav class="pagination" aria-label="Blog pages">
      <?php echo paginate_links(array('total' => (int) $pg_blog->max_num_pages, 'current' => $pg_blog_paged)); ?>
    </nav>
    <?php wp_reset_postdata(); ?>
  <?php else : ?>
    <p class="empty-note">No posts yet. Start from the <a href="<?= esc_url(home_url('/')) ?>">homepage</a>.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

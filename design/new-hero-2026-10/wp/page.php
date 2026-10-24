<?php
defined('ABSPATH') || exit;
// Generic page template: any WordPress page without its own page-<slug>.php.
get_header();
?>
<?php while (have_posts()) : the_post(); ?>

<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>ProsperGenics</div>
    <h1><?php the_title(); ?></h1>
    <?php if (has_excerpt()): ?>
      <p class="ph-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
    <?php endif; ?>
  </div>
</div>

<div class="post-wrap">
  <div class="post-content">
    <?php the_content(); ?>
  </div>
</div>

<?php endwhile; ?>
<?php get_footer(); ?>

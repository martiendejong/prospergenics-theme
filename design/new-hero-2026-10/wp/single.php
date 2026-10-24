<?php
defined('ABSPATH') || exit;
get_header();
?>
<?php while (have_posts()) : the_post(); ?>

<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>ProsperGenics</div>
    <h1><?php the_title(); ?></h1>
    <?php if (has_excerpt()): ?>
      <p class="ph-excerpt"><?php the_excerpt(); ?></p>
    <?php endif; ?>
    <div class="ph-meta">
      <span class="ph-date"><?php echo get_the_date('F j, Y'); ?></span>
    </div>
  </div>
</div>

<div class="post-wrap">
  <a class="post-back" href="<?= esc_url(home_url('/')) ?>">← Back to ProsperGenics</a>
  <div class="post-content">
    <?php echo apply_filters('the_content', get_the_content()); ?>
  </div>
</div>

<?php endwhile; ?>
<?php get_footer(); ?>

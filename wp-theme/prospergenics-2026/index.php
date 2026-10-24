<?php
defined('ABSPATH') || exit;
// Fallback template: the blog index, archives and search results.
// (Before 2026-10 this file redirected every request to the homepage, which turned every
// inner URL and every 404 into a soft-404 for search engines.)
get_header();

if (is_search()) {
    $heading = sprintf('Search results for “%s”', get_search_query());
    $intro   = '';
} elseif (is_home()) {
    $heading = 'Blog';
    $intro   = 'Stories, lessons and updates from the ProsperGenics community.';
} elseif (is_archive()) {
    $heading = wp_strip_all_tags(get_the_archive_title());
    $intro   = wp_strip_all_tags(get_the_archive_description());
} else {
    $heading = 'ProsperGenics';
    $intro   = '';
}
?>

<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>ProsperGenics</div>
    <h1><?php echo esc_html($heading); ?></h1>
    <?php if ($intro): ?>
      <p class="ph-excerpt"><?php echo esc_html($intro); ?></p>
    <?php endif; ?>
    <?php if (is_search()) { get_search_form(); } ?>
  </div>
</div>

<div class="list-wrap">
  <?php if (have_posts()) : ?>
    <?php while (have_posts()) : the_post(); ?>
      <a class="post-card" href="<?php the_permalink(); ?>">
        <h2><?php the_title(); ?></h2>
        <span class="pc-date"><?php echo esc_html(get_the_date('F j, Y')); ?></span>
        <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 32)); ?></p>
      </a>
    <?php endwhile; ?>
    <nav class="pagination" aria-label="Pages">
      <?php echo paginate_links(); ?>
    </nav>
  <?php else : ?>
    <p class="empty-note">Nothing found here yet. Try another search, or start from the <a href="<?= esc_url(home_url('/')) ?>">homepage</a>.</p>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

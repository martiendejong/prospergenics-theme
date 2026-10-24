<?php
defined('ABSPATH') || exit;
// Real 404 page: WordPress has already set the 404 status before this template loads.
get_header();
?>

<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Page not found</div>
    <h1>We couldn't find that page</h1>
    <p class="ph-excerpt">The link may be old, or the page may have moved. Search the site or pick a place to start.</p>
    <?php get_search_form(); ?>
  </div>
</div>

<div class="list-wrap">
  <div class="links-404">
    <a href="<?= esc_url(home_url('/')) ?>">Homepage</a>
    <a href="<?= esc_url(home_url('/about/')) ?>">About ProsperGenics</a>
    <a href="<?= esc_url(home_url('/courses/')) ?>">Courses</a>
    <a href="<?= esc_url(home_url('/members/')) ?>">Members</a>
    <a href="<?= esc_url(home_url('/blog/')) ?>">Blog</a>
  </div>
</div>

<?php get_footer(); ?>

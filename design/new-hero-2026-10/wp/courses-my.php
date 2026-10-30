<?php
defined('ABSPATH') || exit;
// /courses/my/: the learner's courses with progress, "continue" and certificates (cards #6359, #6360, #6361).
// Served by inc/courses/routing.php.
get_header();
$pgc_user = get_current_user_id();
?>

<div class="post-hero">
  <div class="post-hero-inner pgc-hero-wide">
    <div class="ph-eyebrow"><span class="dot"></span><a href="<?= esc_url(home_url('/courses/')) ?>">Courses</a></div>
    <h1>My courses</h1>
    <p class="ph-excerpt">Pick up where you left off, and download the certificates you earned.</p>
  </div>
</div>

<div class="pgc-wrap pgc-narrow">
  <?php if (!$pgc_user) : ?>
    <p class="pgc-empty">Log in to see your courses. <a class="pgc-btn pgc-btn--small" href="<?= esc_url(wp_login_url(home_url('/courses/my/'))) ?>">Log in</a></p>
  <?php else : ?>
    <?php echo prospergenics_courses_render_my_courses($pgc_user); // escaped in the helper ?>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

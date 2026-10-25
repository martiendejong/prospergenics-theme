<?php
defined('ABSPATH') || exit;
// /courses/instructor/<id>-<name>/: bio, courses taught, student count, rating placeholder (card #6362).
// Served by inc/courses/routing.php. Only users who teach (have a published course) have a page.
$pgc_user = get_userdata((int) get_query_var('pg_courses_user'));
$pgc_has  = $pgc_user ? get_posts(array('post_type' => 'pg_course', 'post_status' => 'publish', 'author' => $pgc_user->ID, 'posts_per_page' => 1, 'fields' => 'ids')) : array();
if (!$pgc_user || !$pgc_has) {
    global $wp_query;
    $wp_query->set_404();
    status_header(404);
    include get_404_template();
    return;
}
// Canonical URL: /courses/instructor/<id>-<current display name>/
$pgc_want = prospergenics_courses_instructor_url($pgc_user->ID);
$pgc_have = isset($_SERVER['REQUEST_URI']) ? (string) parse_url(wp_unslash($_SERVER['REQUEST_URI']), PHP_URL_PATH) : '';
if ((string) parse_url($pgc_want, PHP_URL_PATH) !== $pgc_have) {
    wp_safe_redirect($pgc_want, 301);
    exit;
}
$pgc_bio     = get_the_author_meta('description', $pgc_user->ID);
$pgc_profile = apply_filters('prospergenics_courses_instructor_profile_url', '', $pgc_user->ID);
get_header();
?>

<div class="post-hero">
  <div class="post-hero-inner pgc-hero-wide pgc-instructor-hero">
    <?php echo get_avatar($pgc_user->ID, 120, '', '', array('class' => 'pgc-avatar pgc-avatar--lg')); ?>
    <div>
      <div class="ph-eyebrow"><span class="dot"></span><a href="<?= esc_url(home_url('/courses/')) ?>">Courses</a> · Instructor</div>
      <h1><?= esc_html($pgc_user->display_name) ?></h1>
      <?php if ($pgc_bio) : ?><p class="ph-excerpt"><?= esc_html($pgc_bio) ?></p><?php endif; ?>
      <?php if ($pgc_profile) : ?><p class="pgc-hero-links"><a href="<?= esc_url($pgc_profile) ?>">View full profile</a></p><?php endif; ?>
    </div>
  </div>
</div>

<div class="pgc-wrap">
  <h2 class="pgc-h2">Courses taught</h2>
  <?php echo prospergenics_courses_render_instructor_panel($pgc_user->ID); // escaped in the helper ?>
</div>

<?php get_footer(); ?>

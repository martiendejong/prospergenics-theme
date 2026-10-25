<?php
defined('ABSPATH') || exit;
// /courses/: the course catalogue with category/level filters and newest/popular sort (card #6356).
get_header();

$pgc_filters = prospergenics_courses_current_filters();
$pgc_terms   = get_terms(array('taxonomy' => 'pg_course_category', 'hide_empty' => true));
$pgc_user    = get_current_user_id();
$pgc_active  = $pgc_filters['category'] || $pgc_filters['level'] || 'newest' !== $pgc_filters['sort'];
global $wp_query;
?>

<div class="post-hero">
  <div class="post-hero-inner pgc-hero-wide">
    <div class="ph-eyebrow"><span class="dot"></span>ProsperGenics courses</div>
    <h1>Courses</h1>
    <p class="ph-excerpt">Learn by doing with the ProsperGenics community. Follow a course at your own pace, track your progress and earn a certificate anyone can verify.</p>
    <?php if ($pgc_user) : ?>
      <p class="pgc-hero-links"><a href="<?= esc_url(home_url('/courses/my/')) ?>">My courses</a><?php if (prospergenics_courses_is_instructor()) : ?> · <a href="<?= esc_url(home_url('/instructor/dashboard/')) ?>">Instructor dashboard</a><?php endif; ?></p>
    <?php endif; ?>
  </div>
</div>

<div class="pgc-wrap">
  <form class="pgc-filters" method="get" action="<?= esc_url(home_url('/courses/')) ?>" role="search" aria-label="Filter courses">
    <div class="pgc-field">
      <label for="pgc-filter-category">Category</label>
      <select id="pgc-filter-category" name="category">
        <option value="">All categories</option>
        <?php if (is_array($pgc_terms)) : foreach ($pgc_terms as $t) : ?>
          <option value="<?= esc_attr($t->slug) ?>" <?php selected($pgc_filters['category'], $t->slug); ?>><?= esc_html($t->name) ?></option>
        <?php endforeach; endif; ?>
      </select>
    </div>
    <div class="pgc-field">
      <label for="pgc-filter-level">Level</label>
      <select id="pgc-filter-level" name="level">
        <option value="">All levels</option>
        <?php foreach (prospergenics_courses_levels() as $slug => $label) : ?>
          <option value="<?= esc_attr($slug) ?>" <?php selected($pgc_filters['level'], $slug); ?>><?= esc_html($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="pgc-field">
      <label for="pgc-filter-sort">Sort by</label>
      <select id="pgc-filter-sort" name="sort">
        <option value="newest" <?php selected($pgc_filters['sort'], 'newest'); ?>>Newest</option>
        <option value="popular" <?php selected($pgc_filters['sort'], 'popular'); ?>>Most popular</option>
      </select>
    </div>
    <div class="pgc-filter-actions">
      <button type="submit" class="pgc-btn">Show courses</button>
      <?php if ($pgc_active) : ?><a class="pgc-link-small" href="<?= esc_url(home_url('/courses/')) ?>">Clear filters</a><?php endif; ?>
    </div>
  </form>

  <p class="pgc-count" role="status"><?php
    $pgc_n = (int) $wp_query->found_posts;
    echo esc_html(sprintf(_n('%d course', '%d courses', $pgc_n, 'prospergenics-2026'), $pgc_n) . ($pgc_filters['category'] || $pgc_filters['level'] ? ' match your filters' : ''));
  ?></p>

  <?php if (have_posts()) : ?>
    <div class="pgc-grid">
      <?php while (have_posts()) : the_post(); ?>
        <?php echo prospergenics_courses_card(get_post(), $pgc_user); // escaped in the helper ?>
      <?php endwhile; ?>
    </div>
    <nav class="pagination" aria-label="Course pages">
      <?php echo paginate_links(array('add_args' => array_filter(array('category' => $pgc_filters['category'], 'level' => $pgc_filters['level'], 'sort' => 'newest' !== $pgc_filters['sort'] ? $pgc_filters['sort'] : '')))); ?>
    </nav>
  <?php else : ?>
    <?php if ($pgc_active) : ?>
      <p class="pgc-empty">No courses match these filters yet. <a href="<?php echo esc_url(home_url('/courses/')); ?>">Show all courses</a>.</p>
    <?php else : ?>
      <?php // a next step that isn't the value test, which itself sends people here (audit NAV-4) ?>
      <div class="pgc-empty">
        <p>The first courses open soon. Tell us what you want to learn and we'll let you know when it starts, or meet the members who already teach and build together.</p>
        <p class="pgc-actions">
          <a class="pgc-btn" href="<?php echo esc_url(home_url('/contact/?subject=' . rawurlencode('Courses and certification'))); ?>">Tell us what you want to learn</a>
          <a class="pgc-btn pgc-btn--ghost" href="<?php echo esc_url(home_url('/members/')); ?>">Meet our members</a>
        </p>
      </div>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

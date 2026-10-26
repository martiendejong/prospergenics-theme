<?php
defined('ABSPATH') || exit;
// /courses/<slug>/: hero, what you'll learn, curriculum accordion, instructor, enrol (cards #6357, #6359, #6360).
get_header();
?>
<?php while (have_posts()) : the_post();
  $pgc_course     = get_post();
  $pgc_user       = get_current_user_id();
  $pgc_instructor = get_userdata((int) $pgc_course->post_author);
  $pgc_lessons    = count(prospergenics_courses_lesson_ids($pgc_course->ID));
  $pgc_modules    = count(prospergenics_courses_curriculum($pgc_course->ID));
  $pgc_outcomes   = prospergenics_courses_parse_outcomes(get_post_meta($pgc_course->ID, '_pg_courses_outcomes', true));
  $pgc_cats       = prospergenics_courses_categories($pgc_course->ID);
  $pgc_enrolled   = $pgc_user && prospergenics_courses_is_enrolled($pgc_user, $pgc_course->ID);
?>

<div class="post-hero pgc-course-hero">
  <?php // no cover image: no media column, so the title comes first on mobile (audit UX-8) ?>
  <?php $pgc_has_cover = has_post_thumbnail($pgc_course); ?>
  <div class="post-hero-inner pgc-hero-wide pgc-hero-grid<?= $pgc_has_cover ? '' : ' pgc-hero-grid--text' ?>">
    <div class="pgc-hero-text">
      <div class="ph-eyebrow"><span class="dot"></span><a href="<?= esc_url(home_url('/courses/')) ?>">Courses</a><?php if ($pgc_cats) : ?> · <?= esc_html($pgc_cats[0]->name) ?><?php endif; ?></div>
      <h1><?php the_title(); ?></h1>
      <?php if (has_excerpt()) : ?>
        <p class="ph-excerpt"><?php echo esc_html(get_the_excerpt()); ?></p>
      <?php endif; ?>
      <ul class="pgc-hero-meta">
        <?php if ($pgc_instructor) : ?><li>By <a href="<?= esc_url(prospergenics_courses_instructor_url($pgc_instructor->ID)) ?>"><?= esc_html($pgc_instructor->display_name) ?></a></li><?php endif; ?>
        <?php if (prospergenics_courses_level($pgc_course->ID)) : $pgc_levels = prospergenics_courses_levels(); ?><li><?= esc_html($pgc_levels[prospergenics_courses_level($pgc_course->ID)]) ?></li><?php endif; ?>
        <li><?= esc_html(prospergenics_courses_lesson_count_label($pgc_lessons)) ?> in <?= esc_html(sprintf(_n('%d module', '%d modules', $pgc_modules, 'prospergenics-2026'), $pgc_modules)) ?></li>
        <li><?= esc_html(prospergenics_courses_format_price(prospergenics_courses_price($pgc_course->ID))) ?></li>
      </ul>
      <?php echo prospergenics_courses_notice_html(); ?>
      <?php echo prospergenics_courses_enrol_cta($pgc_course, $pgc_user); ?>
      <?php if ('publish' !== $pgc_course->post_status) : ?>
        <p class="pgc-preview-note">Preview: this course is not published.</p>
      <?php endif; ?>
    </div>
    <?php if ($pgc_has_cover) : ?>
    <div class="pgc-hero-media"><?php echo prospergenics_courses_thumb($pgc_course, 'large'); ?></div>
    <?php endif; ?>
  </div>
</div>

<div class="pgc-wrap pgc-course-body">
  <div class="pgc-course-main">
    <?php if ($pgc_outcomes) : ?>
      <section class="pgc-section" aria-labelledby="pgc-learn">
        <h2 id="pgc-learn">What you'll learn</h2>
        <ul class="pgc-outcomes">
          <?php foreach ($pgc_outcomes as $o) : ?><li><?php echo prospergenics_courses_icon('check'); ?><span><?= esc_html($o) ?></span></li><?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ('' !== trim($pgc_course->post_content)) : ?>
      <section class="pgc-section" aria-labelledby="pgc-about">
        <h2 id="pgc-about">About this course</h2>
        <div class="post-content"><?php the_content(); ?></div>
      </section>
    <?php endif; ?>

    <?php if ($pgc_enrolled && 100 === prospergenics_courses_progress($pgc_user, $pgc_course->ID)) { echo prospergenics_courses_next_course_html($pgc_user, $pgc_course->ID); } ?>

    <section class="pgc-section" aria-labelledby="pgc-curriculum">
      <h2 id="pgc-curriculum">Curriculum</h2>
      <p class="pgc-muted"><?= esc_html(sprintf(_n('%d module', '%d modules', $pgc_modules, 'prospergenics-2026'), $pgc_modules) . ' · ' . prospergenics_courses_lesson_count_label($pgc_lessons)) ?><?php if (!$pgc_enrolled && $pgc_lessons) : ?> · Enrol to open the lessons.<?php endif; ?></p>
      <?php echo prospergenics_courses_curriculum_html($pgc_course, $pgc_user, 0, 'accordion'); ?>
    </section>
  </div>

  <aside class="pgc-course-side" aria-label="Instructor">
    <section class="pgc-section pgc-side-card" aria-labelledby="pgc-instructor">
      <h2 id="pgc-instructor">Your instructor</h2>
      <?php echo prospergenics_courses_instructor_block((int) $pgc_course->post_author); ?>
    </section>
    <?php if ($pgc_enrolled) : ?>
      <section class="pgc-section pgc-side-card" aria-labelledby="pgc-leave">
        <h2 id="pgc-leave">Leave this course</h2>
        <p class="pgc-muted">Unenrolling removes your progress. A certificate you already earned stays valid.</p>
        <?php echo prospergenics_courses_action_form('pg_courses_unenrol', 'pg_courses_unenrol_' . $pgc_course->ID, array('course_id' => $pgc_course->ID), 'Unenrol', 'pgc-btn pgc-btn--danger pgc-btn--small', ' data-pgc-confirm="' . esc_attr('Leave this course? Your progress will be removed.') . '"'); ?>
      </section>
    <?php endif; ?>
    <?php if (current_user_can('edit_post', $pgc_course->ID)) : ?>
      <p class="pgc-links"><a href="<?= esc_url(prospergenics_courses_wizard_url($pgc_course->ID)) ?>">Edit this course</a> · <a href="<?= esc_url(home_url('/instructor/dashboard/')) ?>">Instructor dashboard</a></p>
    <?php endif; ?>
  </aside>
</div>

<?php endwhile; ?>
<?php get_footer(); ?>

<?php
defined('ABSPATH') || exit;
// /courses/<course>/lessons/<lesson>/: the lesson viewer (card #6358), gated to enrolled learners (#6359).
get_header();
?>
<?php while (have_posts()) : the_post();
  $pgc_lesson  = get_post();
  $pgc_course  = get_post(prospergenics_courses_course_of($pgc_lesson));
  $pgc_module  = get_post($pgc_lesson->post_parent);
  $pgc_user    = get_current_user_id();
  $pgc_can     = prospergenics_courses_can_view_lesson($pgc_lesson);
  $pgc_flat    = prospergenics_courses_flat_lessons($pgc_course->ID);
  $pgc_index   = 0;
  foreach ($pgc_flat as $i => $l) { if ((int) $l->ID === (int) $pgc_lesson->ID) { $pgc_index = $i; break; } }
  $pgc_prev    = $pgc_index > 0 ? $pgc_flat[$pgc_index - 1] : null;
  $pgc_next    = isset($pgc_flat[$pgc_index + 1]) ? $pgc_flat[$pgc_index + 1] : null;
  $pgc_enrolled = $pgc_user && prospergenics_courses_is_enrolled($pgc_user, $pgc_course->ID);
  $pgc_done    = $pgc_enrolled && in_array((int) $pgc_lesson->ID, prospergenics_courses_completed_ids($pgc_user, $pgc_course->ID), true);
  if ($pgc_enrolled && $pgc_can) { prospergenics_courses_touch_lesson($pgc_user, $pgc_lesson); }
?>

<div class="pgc-lesson-layout">
  <aside class="pgc-lesson-side" aria-labelledby="pgc-contents-title">
    <div class="pgc-side-head">
      <a class="pgc-side-course" href="<?= esc_url(get_permalink($pgc_course)) ?>"><?= esc_html(get_the_title($pgc_course)) ?></a>
      <?php if ($pgc_enrolled) { echo prospergenics_courses_progress_bar(prospergenics_courses_progress($pgc_user, $pgc_course->ID), 'Your progress'); } ?>
      <button type="button" class="pgc-side-toggle" aria-expanded="true" aria-controls="pgc-contents" hidden>Course contents</button>
    </div>
    <nav id="pgc-contents" class="pgc-side-nav" aria-labelledby="pgc-contents-title">
      <h2 id="pgc-contents-title" class="pgc-side-title">Course contents</h2>
      <?php echo prospergenics_courses_curriculum_html($pgc_course, $pgc_user, $pgc_lesson->ID, 'nav'); ?>
    </nav>
  </aside>

  <article class="pgc-lesson-main">
    <p class="pgc-breadcrumb"><a href="<?= esc_url(home_url('/courses/')) ?>">Courses</a> <span aria-hidden="true">/</span> <a href="<?= esc_url(get_permalink($pgc_course)) ?>"><?= esc_html(get_the_title($pgc_course)) ?></a></p>
    <p class="pgc-lesson-eyebrow"><?= esc_html(get_the_title($pgc_module)) ?> · Lesson <?= (int) $pgc_index + 1 ?> of <?= count($pgc_flat) ?></p>
    <h1 class="pgc-lesson-h1"><?php the_title(); ?></h1>
    <?php echo prospergenics_courses_notice_html(); ?>

    <?php if (!$pgc_can) : ?>
      <div class="pgc-locked">
        <p><?php echo prospergenics_courses_icon('lock'); ?> <strong>This lesson is for enrolled learners.</strong> Enrol in <a href="<?= esc_url(get_permalink($pgc_course)) ?>"><?= esc_html(get_the_title($pgc_course)) ?></a> to open it.</p>
        <?php echo prospergenics_courses_enrol_cta($pgc_course, $pgc_user); ?>
      </div>
    <?php else :
      $pgc_video = (string) get_post_meta($pgc_lesson->ID, '_pg_courses_video_url', true);
      if ($pgc_video) :
        // oEmbed (WordPress core, cached per lesson); a direct player embed if discovery fails.
        $pgc_embed = isset($GLOBALS['wp_embed']) ? $GLOBALS['wp_embed']->shortcode(array(), $pgc_video) : '';
        if (false === strpos((string) $pgc_embed, '<iframe')) { $pgc_embed = prospergenics_courses_video_iframe($pgc_video, get_the_title()); }
    ?>
        <div class="pgc-video"><?php echo $pgc_embed; // oEmbed HTML from WordPress core or our own escaped iframe ?></div>
      <?php endif; ?>
      <div class="post-content pgc-lesson-content"><?php the_content(); ?></div>

      <div class="pgc-complete">
        <?php if ($pgc_enrolled) : ?>
          <?php if ($pgc_done) : ?>
            <p class="pgc-done-note"><?php echo prospergenics_courses_icon('check'); ?> You completed this lesson.</p>
            <?php echo prospergenics_courses_action_form('pg_courses_complete', 'pg_courses_complete_' . $pgc_lesson->ID, array('lesson_id' => $pgc_lesson->ID, 'done' => 0), 'Mark as not complete', 'pgc-btn pgc-btn--ghost pgc-btn--small'); ?>
          <?php else : ?>
            <?php echo prospergenics_courses_action_form('pg_courses_complete', 'pg_courses_complete_' . $pgc_lesson->ID, array('lesson_id' => $pgc_lesson->ID, 'done' => 1, 'next' => $pgc_next ? $pgc_next->ID : 0), $pgc_next ? 'Mark as complete and continue' : 'Mark as complete'); ?>
          <?php endif; ?>
        <?php else : ?>
          <p class="pgc-muted">You are previewing this lesson as an editor. Learners mark lessons complete here.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <nav class="pgc-pager" aria-label="Lessons">
      <?php if ($pgc_prev) : ?>
        <a class="pgc-pager-link pgc-pager-prev" href="<?= esc_url(get_permalink($pgc_prev)) ?>" rel="prev"><span class="pgc-pager-dir">Previous lesson</span><span class="pgc-pager-title"><?= esc_html(get_the_title($pgc_prev)) ?></span></a>
      <?php endif; ?>
      <?php if ($pgc_next) : ?>
        <a class="pgc-pager-link pgc-pager-next" href="<?= esc_url(get_permalink($pgc_next)) ?>" rel="next"><span class="pgc-pager-dir">Next lesson</span><span class="pgc-pager-title"><?= esc_html(get_the_title($pgc_next)) ?></span></a>
      <?php else : ?>
        <a class="pgc-pager-link pgc-pager-next" href="<?= esc_url(get_permalink($pgc_course)) ?>"><span class="pgc-pager-dir">Last lesson</span><span class="pgc-pager-title">Back to the course</span></a>
      <?php endif; ?>
    </nav>

    <?php // discussion:start (card #6464) questions and replies for enrolled learners and the instructor, inc/courses/discussion.php ?>
    <?php if ($pgc_can) { do_action('prospergenics_courses_lesson_discussion', $pgc_lesson, $pgc_course); } ?>
    <?php // discussion:end ?>
  </article>
</div>

<?php endwhile; ?>
<?php get_footer(); ?>

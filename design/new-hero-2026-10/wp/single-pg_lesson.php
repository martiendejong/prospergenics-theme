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
  <script>
  // Phones: the course contents fold behind a toggle. Inline, right after the markup, so it folds
  // before the lesson is painted (no layout shift on slow connections, card #6469).
  (function () {
    var btn = document.querySelector('.pgc-side-toggle'), nav = document.getElementById('pgc-contents');
    if (!btn || !nav || !window.matchMedia) { return; }
    var mq = window.matchMedia('(max-width: 880px)');
    function setOpen(open) { btn.setAttribute('aria-expanded', open ? 'true' : 'false'); nav.hidden = !open; }
    function apply() { if (mq.matches) { btn.hidden = false; setOpen(false); } else { btn.hidden = true; nav.hidden = false; } }
    btn.addEventListener('click', function () { setOpen(btn.getAttribute('aria-expanded') !== 'true'); });
    if (mq.addEventListener) { mq.addEventListener('change', apply); }
    apply();
  })();
  </script>

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
      // Text first, heavy media only on request (card #6469, inc/courses/lite.php): the video is a
      // click-to-load facade under the text, and the notes can be saved as a small PDF.
      $pgc_video = (string) get_post_meta($pgc_lesson->ID, '_pg_courses_video_url', true);
      $pgc_video = prospergenics_courses_video_source($pgc_video) ? $pgc_video : '';
      $pgc_quiz_open = prospergenics_courses_lesson_has_quiz($pgc_lesson->ID) && !($pgc_enrolled && prospergenics_courses_quiz_passed($pgc_user, $pgc_lesson->ID));
    ?>
      <p class="pgc-lesson-tools">
        <a class="pgc-btn pgc-btn--ghost pgc-btn--small" href="<?= esc_url(prospergenics_courses_notes_url($pgc_lesson)) ?>" download>Download notes (PDF)</a>
        <?php if ($pgc_video) : ?><a class="pgc-link-small" href="#pgc-lesson-video">This lesson has a video below the text</a><?php endif; ?>
      </p>
      <div class="post-content pgc-lesson-content"><?php the_content(); ?></div>
      <?php if ($pgc_video) : ?>
        <section id="pgc-lesson-video" class="pgc-lesson-video" aria-labelledby="pgc-lesson-video-title">
          <h2 id="pgc-lesson-video-title" class="pgc-lesson-video-title">Lesson video</h2>
          <?php echo prospergenics_courses_video_facade($pgc_video, get_the_title()); // escaped inside ?>
        </section>
      <?php endif; ?>

      <?php // quiz:start (card #6461, inc/courses/quiz.php) ?>
      <?php echo prospergenics_courses_quiz_html($pgc_lesson, $pgc_user, $pgc_enrolled); // escaped inside; '' without a quiz ?>
      <?php // quiz:end ?>

      <div class="pgc-complete">
        <?php if ($pgc_enrolled) : ?>
          <?php if ($pgc_done) : ?>
            <p class="pgc-done-note"><?php echo prospergenics_courses_icon('check'); ?> You completed this lesson.</p>
            <?php echo prospergenics_courses_action_form('pg_courses_complete', 'pg_courses_complete_' . $pgc_lesson->ID, array('lesson_id' => $pgc_lesson->ID, 'done' => 0), 'Mark as not complete', 'pgc-btn pgc-btn--ghost pgc-btn--small'); ?>
          <?php elseif ($pgc_quiz_open) : ?>
            <p class="pgc-muted">Pass the quiz above to complete this lesson.</p>
          <?php else : ?>
            <?php echo prospergenics_courses_action_form('pg_courses_complete', 'pg_courses_complete_' . $pgc_lesson->ID, array('lesson_id' => $pgc_lesson->ID, 'done' => 1, 'next' => $pgc_next ? $pgc_next->ID : 0), $pgc_next ? 'Mark as complete and continue' : 'Mark as complete'); ?>
          <?php endif; ?>
        <?php else : ?>
          <p class="pgc-muted">You are previewing this lesson as an editor. Learners mark lessons complete here<?php echo $pgc_quiz_open ? ', by passing the quiz' : ''; ?>.</p>
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
  </article>
</div>

<?php endwhile; ?>
<?php get_footer(); ?>

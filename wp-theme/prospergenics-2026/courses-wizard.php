<?php
defined('ABSPATH') || exit;
// /instructor/courses/new/ and /instructor/courses/<id>/edit/: the 3-step course wizard with draft
// autosave (card #6363). Served by inc/courses/routing.php; the form is driven by
// assets/platform/courses.js against the prospergenics/v1 REST API.
$pgc_edit = (int) get_query_var('pg_courses_edit');
if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(prospergenics_courses_wizard_url($pgc_edit)));
    exit;
}
$pgc_error = '';
if (!prospergenics_courses_is_instructor()) {
    $pgc_error = 'The course wizard is for instructors. Would you like to teach a course? Send the ProsperGenics team a request below.';
    $pgc_apply = true;
    status_header(403);
} elseif ($pgc_edit && ('pg_course' !== get_post_type($pgc_edit) || 'trash' === get_post_status($pgc_edit))) {
    $pgc_error = 'This course does not exist (any more).';
    status_header(404);
} elseif ($pgc_edit && !current_user_can('edit_post', $pgc_edit)) {
    $pgc_error = 'You cannot edit this course.';
    status_header(403);
}
$pgc_terms = get_terms(array('taxonomy' => 'pg_course_category', 'hide_empty' => false));
get_header();
?>

<div class="post-hero">
  <div class="post-hero-inner pgc-hero-wide">
    <div class="ph-eyebrow"><span class="dot"></span><a href="<?= esc_url(home_url('/instructor/dashboard/')) ?>">Instructor dashboard</a></div>
    <h1><?php echo $pgc_edit ? 'Edit course' : 'Create a course'; ?></h1>
    <p class="ph-excerpt">Three steps: the basics, the curriculum, then the image and price. Drafts save automatically.</p>
  </div>
</div>

<div class="pgc-wrap pgc-narrow">
  <?php if ($pgc_error) : ?>
    <p class="pgc-empty"><?= esc_html($pgc_error) ?><?php if (empty($pgc_apply)) : ?> <a href="<?= esc_url(home_url('/instructor/dashboard/')) ?>">Back to the dashboard</a>.<?php endif; ?></p>
    <?php if (!empty($pgc_apply)) { echo prospergenics_profiles_application_box('teach', 'pgc-btn'); } // escaped inside ?>
  <?php else : ?>
  <noscript><p class="pgc-notice pgc-notice--error">The course wizard needs JavaScript. You can also create courses in <a href="<?= esc_url(admin_url('post-new.php?post_type=pg_course')) ?>">WordPress admin</a>.</p></noscript>

  <form id="pgc-wizard" class="pgc-wizard" novalidate hidden>
    <ol class="pgc-steps">
      <li><button type="button" data-goto="1" aria-current="step"><span class="pgc-step-num">1</span> Basics</button></li>
      <li><button type="button" data-goto="2"><span class="pgc-step-num">2</span> Curriculum</button></li>
      <li><button type="button" data-goto="3"><span class="pgc-step-num">3</span> Image and price</button></li>
    </ol>
    <p id="pgc-save-status" class="pgc-save-status" role="status" aria-live="polite"></p>

    <section class="pgc-step" data-step="1" aria-labelledby="pgc-step1-title">
      <h2 id="pgc-step1-title" class="pgc-h2" tabindex="-1">1. Basics</h2>
      <div class="pgc-field">
        <label for="pgc-title">Course title <span class="pgc-req">(required)</span></label>
        <input id="pgc-title" name="title" type="text" maxlength="200" required aria-describedby="pgc-title-error" />
        <p id="pgc-title-error" class="pgc-field-error" hidden>Give the course a title first.</p>
      </div>
      <div class="pgc-field">
        <label for="pgc-excerpt">Short description</label>
        <textarea id="pgc-excerpt" name="excerpt" rows="2" maxlength="600" aria-describedby="pgc-excerpt-help"></textarea>
        <p id="pgc-excerpt-help" class="pgc-help">One or two sentences, shown on the course card and at the top of the course page.</p>
      </div>
      <div class="pgc-field">
        <label for="pgc-content">About this course</label>
        <textarea id="pgc-content" name="content" rows="7" aria-describedby="pgc-content-help"></textarea>
        <p id="pgc-content-help" class="pgc-help">Basic HTML is allowed (paragraphs, lists, links, bold).</p>
      </div>
      <div class="pgc-field">
        <label for="pgc-outcomes">What learners will learn</label>
        <textarea id="pgc-outcomes" name="outcomes" rows="4" aria-describedby="pgc-outcomes-help"></textarea>
        <p id="pgc-outcomes-help" class="pgc-help">One outcome per line.</p>
      </div>
      <div class="pgc-field-row">
        <div class="pgc-field">
          <label for="pgc-category">Category</label>
          <select id="pgc-category" name="category">
            <option value="0">No category</option>
            <?php if (is_array($pgc_terms)) : foreach ($pgc_terms as $t) : ?><option value="<?= (int) $t->term_id ?>"><?= esc_html($t->name) ?></option><?php endforeach; endif; ?>
          </select>
        </div>
        <div class="pgc-field">
          <label for="pgc-level">Level</label>
          <select id="pgc-level" name="level">
            <option value="">Not set</option>
            <?php foreach (prospergenics_courses_levels() as $slug => $label) : ?><option value="<?= esc_attr($slug) ?>"><?= esc_html($label) ?></option><?php endforeach; ?>
          </select>
        </div>
      </div>
    </section>

    <section class="pgc-step" data-step="2" aria-labelledby="pgc-step2-title" hidden>
      <h2 id="pgc-step2-title" class="pgc-h2" tabindex="-1">2. Curriculum</h2>
      <p class="pgc-help">A course has modules; each module has lessons. A lesson can have a YouTube or Vimeo video and text.</p>
      <div id="pgc-modules" class="pgc-modules"></div>
      <button type="button" class="pgc-btn pgc-btn--ghost" data-action="add-module">+ Add a module</button>
    </section>

    <section class="pgc-step" data-step="3" aria-labelledby="pgc-step3-title" hidden>
      <h2 id="pgc-step3-title" class="pgc-h2" tabindex="-1">3. Image and price</h2>
      <div class="pgc-field">
        <label for="pgc-thumb">Course image</label>
        <div class="pgc-thumb-row">
          <div class="pgc-thumb-preview" id="pgc-thumb-preview" aria-hidden="true"></div>
          <div>
            <input id="pgc-thumb" type="file" accept="image/jpeg,image/png,image/webp,image/gif" aria-describedby="pgc-thumb-help" />
            <p id="pgc-thumb-help" class="pgc-help">JPEG, PNG, WebP or GIF, at most 5 MB. A wide image (16:9) works best.</p>
          </div>
        </div>
      </div>
      <div class="pgc-field pgc-field--short">
        <label for="pgc-price">Price</label>
        <input id="pgc-price" name="price" type="number" min="0" step="0.01" value="0" aria-describedby="pgc-price-help" />
        <p id="pgc-price-help" class="pgc-help">0 makes the course free. Paid enrolment opens later; a paid course can be published but not enrolled in yet.</p>
      </div>
      <div class="pgc-publish-box">
        <p id="pgc-publish-state" class="pgc-muted"></p>
        <div class="pgc-cta-row">
          <button type="button" class="pgc-btn pgc-btn--ghost" data-action="save">Save as draft</button>
          <button type="button" class="pgc-btn" data-action="publish">Publish</button>
          <a class="pgc-link-small" id="pgc-view-link" href="#" hidden>View course</a>
        </div>
      </div>
    </section>

    <div class="pgc-wizard-nav">
      <button type="button" class="pgc-btn pgc-btn--ghost" data-action="back" hidden>Back</button>
      <button type="button" class="pgc-btn" data-action="next">Next</button>
    </div>
  </form>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

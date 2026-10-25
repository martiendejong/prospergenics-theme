<?php
defined('ABSPATH') || exit;
// /instructor/dashboard/: my courses with enrolment and completion stats, edit/unpublish/delete (card #6364).
// Served by inc/courses/routing.php; needs the edit_pg_courses capability.
if (!is_user_logged_in()) {
    wp_safe_redirect(wp_login_url(home_url('/instructor/dashboard/')));
    exit;
}
$pgc_allowed = prospergenics_courses_is_instructor();
if (!$pgc_allowed) {
    status_header(403);
}
// phpcs:ignore WordPress.Security.NonceVerification -- read-only view switch
$pgc_all     = $pgc_allowed && current_user_can('edit_others_pg_courses') && isset($_GET['scope']) && 'all' === $_GET['scope'];
$pgc_courses = $pgc_allowed ? prospergenics_courses_dashboard_courses(get_current_user_id(), $pgc_all) : array();
$pgc_status_labels = array('publish' => 'Published', 'draft' => 'Draft', 'pending' => 'Pending review', 'private' => 'Private', 'future' => 'Scheduled');
get_header();
?>

<div class="post-hero">
  <div class="post-hero-inner pgc-hero-wide">
    <div class="ph-eyebrow"><span class="dot"></span>Instructor</div>
    <h1>Instructor dashboard</h1>
    <?php if ($pgc_allowed) : ?>
      <p class="ph-excerpt">Your courses, who enrolled and how many finished.</p>
      <p class="pgc-hero-actions"><a class="pgc-btn pgc-btn--light" href="<?= esc_url(prospergenics_courses_wizard_url()) ?>">Create a course</a></p>
    <?php endif; ?>
  </div>
</div>

<div class="pgc-wrap">
  <?php echo prospergenics_courses_notice_html(); ?>
  <?php if (!$pgc_allowed) : ?>
    <p class="pgc-empty">The instructor tools are for instructors. Ask the ProsperGenics team if you would like to teach a course.</p>
  <?php else : ?>
    <?php if (current_user_can('edit_others_pg_courses')) : ?>
      <p class="pgc-scope" aria-label="Which courses">
        <?php if ($pgc_all) : ?><a href="<?= esc_url(home_url('/instructor/dashboard/')) ?>">My courses</a> · <strong aria-current="page">All courses</strong>
        <?php else : ?><strong aria-current="page">My courses</strong> · <a href="<?= esc_url(add_query_arg('scope', 'all', home_url('/instructor/dashboard/'))) ?>">All courses</a><?php endif; ?>
      </p>
    <?php endif; ?>

    <?php if (!$pgc_courses) : ?>
      <p class="pgc-empty">You have no courses yet. <a href="<?= esc_url(prospergenics_courses_wizard_url()) ?>">Create your first course</a>.</p>
    <?php else :
      $pgc_tot = array('enrolled' => 0, 'completed' => 0);
      $pgc_rows = array();
      foreach ($pgc_courses as $c) {
          $s = prospergenics_courses_stats($c->ID);
          $pgc_tot['enrolled'] += $s['enrolled'];
          $pgc_tot['completed'] += $s['completed'];
          $pgc_rows[] = array($c, $s);
      }
    ?>
      <div class="pgc-stats" role="list">
        <div class="pgc-stat" role="listitem"><span class="pgc-stat-num"><?= count($pgc_courses) ?></span><span class="pgc-stat-label">courses</span></div>
        <div class="pgc-stat" role="listitem"><span class="pgc-stat-num"><?= (int) $pgc_tot['enrolled'] ?></span><span class="pgc-stat-label">enrolments</span></div>
        <div class="pgc-stat" role="listitem"><span class="pgc-stat-num"><?= (int) $pgc_tot['completed'] ?></span><span class="pgc-stat-label">completed</span></div>
        <div class="pgc-stat" role="listitem"><span class="pgc-stat-num"><?= (int) prospergenics_courses_rate($pgc_tot['completed'], $pgc_tot['enrolled']) ?>%</span><span class="pgc-stat-label">completion rate</span></div>
      </div>

      <div class="pgc-table-wrap">
        <table class="pgc-table">
          <caption class="screen-reader-text">Courses with enrolment and completion statistics</caption>
          <thead><tr><th scope="col">Course</th><th scope="col">Status</th><th scope="col">Lessons</th><th scope="col">Enrolled</th><th scope="col">Completed</th><th scope="col">Completion rate</th><th scope="col">Actions</th></tr></thead>
          <tbody>
          <?php foreach ($pgc_rows as $row) : list($c, $s) = $row; $pgc_title = get_the_title($c); ?>
            <tr>
              <th scope="row" data-label="Course"><a href="<?= esc_url(get_permalink($c)) ?>"><?= esc_html($pgc_title ?: '(untitled)') ?></a><?php if ($pgc_all && (int) $c->post_author !== get_current_user_id()) : $au = get_userdata((int) $c->post_author); ?><span class="pgc-table-sub">by <?= esc_html($au ? $au->display_name : '?') ?></span><?php endif; ?></th>
              <td data-label="Status"><span class="pgc-status pgc-status--<?= esc_attr($c->post_status) ?>"><?= esc_html(isset($pgc_status_labels[$c->post_status]) ? $pgc_status_labels[$c->post_status] : $c->post_status) ?></span></td>
              <td data-label="Lessons"><?= count(prospergenics_courses_lesson_ids($c->ID)) ?></td>
              <td data-label="Enrolled"><?= (int) $s['enrolled'] ?></td>
              <td data-label="Completed"><?= (int) $s['completed'] ?></td>
              <td data-label="Completion rate"><?= (int) $s['rate'] ?>%</td>
              <td data-label="Actions">
                <div class="pgc-actions">
                  <?php if (current_user_can('edit_post', $c->ID)) : ?>
                    <a class="pgc-btn pgc-btn--ghost pgc-btn--small" href="<?= esc_url(prospergenics_courses_wizard_url($c->ID)) ?>">Edit<span class="screen-reader-text"> <?= esc_html($pgc_title) ?></span></a>
                    <?php if ('publish' === $c->post_status) {
                        echo prospergenics_courses_action_form('pg_courses_status', 'pg_courses_status_' . $c->ID, array('course_id' => $c->ID, 'to' => 'draft'), 'Unpublish', 'pgc-btn pgc-btn--ghost pgc-btn--small', ' data-pgc-confirm="' . esc_attr('Unpublish "' . $pgc_title . '"? Learners will no longer see it.') . '"', $pgc_title);
                    } elseif (current_user_can('publish_pg_courses')) {
                        echo prospergenics_courses_action_form('pg_courses_status', 'pg_courses_status_' . $c->ID, array('course_id' => $c->ID, 'to' => 'publish'), 'Publish', 'pgc-btn pgc-btn--small', '', $pgc_title);
                    } ?>
                  <?php endif; ?>
                  <?php if (current_user_can('delete_post', $c->ID)) {
                      echo prospergenics_courses_action_form('pg_courses_status', 'pg_courses_status_' . $c->ID, array('course_id' => $c->ID, 'to' => 'trash'), 'Delete', 'pgc-btn pgc-btn--danger pgc-btn--small', ' data-pgc-confirm="' . esc_attr('Move "' . $pgc_title . '" to the trash?') . '"', $pgc_title);
                  } ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <p class="pgc-muted">Deleted courses go to the trash in WordPress admin, where they can be restored. Completion = learners who finished every lesson.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

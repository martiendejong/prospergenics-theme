<?php
defined('ABSPATH') || exit;
// /courses/reminders/stop/?u=<id>&t=<token>: the one-click stop link in every weekly learning
// reminder (card #6512). inc/courses/reminders.php checks the token and turns the reminders off
// (or back on, from the button here) before this page renders.
$pgc_r = isset($GLOBALS['pg_courses_reminders_result']) ? $GLOBALS['pg_courses_reminders_result'] : array('state' => 'invalid', 'user' => 0, 'token' => '');
if ('invalid' === $pgc_r['state']) {
    status_header(403);
}
get_header();
?>

<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Email</div>
    <h1><?php echo 'invalid' === $pgc_r['state'] ? 'This link does not work' : ('off' === $pgc_r['state'] ? 'Weekly learning reminders are off' : 'Weekly learning reminders are on'); ?></h1>
  </div>
</div>

<div class="post-wrap pgc-reminders">
  <?php if ('invalid' === $pgc_r['state']) : ?>
    <div class="pgc-verify pgc-verify--bad" role="alert">
      <p>This link is not complete or not valid, so nothing was changed. Copy the whole link from the email, or change your reminders under your <a href="<?= esc_url(home_url('/profile/settings/#pgp-email')) ?>">settings</a> (log in first).</p>
    </div>
  <?php else : ?>
    <div class="pgc-verify pgc-verify--ok" role="status">
      <p class="pgc-verify-badge"><?php echo prospergenics_courses_icon('check'); ?>
        <?php echo 'off' === $pgc_r['state'] ? 'You will not get weekly learning reminders any more.' : 'You will get a weekly reminder again when you have not opened a lesson for a week.'; ?></p>
      <p>Other emails about your courses (enrolment, certificates) still come. You can change this any time under your <a href="<?= esc_url(home_url('/profile/settings/#pgp-email')) ?>">settings</a>.</p>
    </div>
    <form class="pgc-reminders-actions" method="post" action="<?= esc_url(home_url('/courses/reminders/stop/')) ?>">
      <input type="hidden" name="u" value="<?= esc_attr((string) $pgc_r['user']) ?>" />
      <input type="hidden" name="t" value="<?= esc_attr($pgc_r['token']) ?>" />
      <input type="hidden" name="pgc_reminders" value="<?= 'off' === $pgc_r['state'] ? 'on' : 'off' ?>" />
      <button type="submit" class="pgc-btn<?= 'off' === $pgc_r['state'] ? '' : ' pgc-btn--ghost' ?>"><?= 'off' === $pgc_r['state'] ? 'Turn weekly reminders back on' : 'Turn them off again' ?></button>
      <a class="pgc-btn pgc-btn--ghost" href="<?= esc_url(home_url('/courses/my/')) ?>">My courses</a>
    </form>
  <?php endif; ?>
</div>

<?php get_footer(); ?>

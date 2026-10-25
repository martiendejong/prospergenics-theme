<?php
defined('ABSPATH') || exit;
// /courses/verify/<code>/: public check whether a ProsperGenics certificate is genuine (card #6361).
// Served by inc/courses/routing.php.
// phpcs:ignore WordPress.Security.NonceVerification -- read-only lookup form
$pgc_code = prospergenics_courses_normalize_code(get_query_var('pg_courses_code') ?: (isset($_GET['code']) ? wp_unslash($_GET['code']) : ''));
if ($pgc_code && !get_query_var('pg_courses_code')) {
    // the form posts ?code=...; send it to the canonical URL
    wp_safe_redirect(prospergenics_courses_verify_url($pgc_code));
    exit;
}
$pgc_cert = $pgc_code ? prospergenics_courses_get_certificate_by_code($pgc_code) : null;
if ($pgc_code && !$pgc_cert) {
    status_header(404);
}
get_header();
?>

<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Certificate verification</div>
    <h1><?php echo $pgc_cert ? 'Valid certificate' : ($pgc_code ? 'Certificate not found' : 'Verify a certificate'); ?></h1>
    <p class="ph-excerpt">ProsperGenics certificates carry an ID like PG-XXXX-XXXX-XXXX and a link to this page.</p>
  </div>
</div>

<div class="post-wrap">
  <?php if ($pgc_cert) : $d = prospergenics_courses_certificate_data($pgc_cert); ?>
    <div class="pgc-verify pgc-verify--ok" role="status">
      <p class="pgc-verify-badge"><?php echo prospergenics_courses_icon('check'); ?> This certificate is genuine.</p>
      <dl class="pgc-verify-list">
        <dt>Awarded to</dt><dd><?= esc_html($d['name']) ?></dd>
        <dt>Course</dt><dd><?php
          $pgc_c = get_post($d['course_id']);
          if ($pgc_c && 'publish' === $pgc_c->post_status) {
              echo '<a href="' . esc_url(get_permalink($pgc_c)) . '">' . esc_html($d['course']) . '</a>';
          } else {
              echo esc_html($d['course']);
          }
        ?></dd>
        <?php if ($d['instructor']) : ?><dt>Instructor</dt><dd><?= esc_html($d['instructor']) ?></dd><?php endif; ?>
        <dt>Completed on</dt><dd><time datetime="<?= esc_attr(gmdate('Y-m-d', $d['issued_at'])) ?>"><?= esc_html($d['date']) ?></time></dd>
        <dt>Certificate ID</dt><dd><code><?= esc_html($d['code']) ?></code></dd>
      </dl>
    </div>
  <?php elseif ($pgc_code) : ?>
    <div class="pgc-verify pgc-verify--bad" role="alert">
      <p>No certificate with the ID <code><?= esc_html($pgc_code) ?></code> exists. Check the ID for typing errors.</p>
    </div>
  <?php endif; ?>

  <form class="pgc-verify-form" method="get" action="<?= esc_url(home_url('/courses/verify/')) ?>">
    <label for="pgc-code">Certificate ID</label>
    <div class="pgc-verify-row">
      <input id="pgc-code" name="code" type="text" inputmode="text" autocomplete="off" spellcheck="false" placeholder="PG-XXXX-XXXX-XXXX" value="<?= esc_attr($pgc_code) ?>" required pattern="[A-Za-z0-9\-]{6,40}" />
      <button type="submit" class="pgc-btn">Verify</button>
    </div>
  </form>
</div>

<?php get_footer(); ?>

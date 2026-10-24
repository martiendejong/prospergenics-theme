<?php defined('ABSPATH') || exit; ?>
</main>
<footer class="pg-footer">
  <div class="pg-footer-inner">
    <div class="f-brand">
      <img src="<?= esc_url(get_template_directory_uri()) ?>/assets/logo.png" alt="" />
      <span>ProsperGenics · building prosperity together</span>
    </div>
    <nav aria-label="Footer">
      <a href="<?= esc_url(home_url('/')) ?>">Home</a>
      <a href="<?= esc_url(home_url('/about/')) ?>">About</a>
      <a href="<?= esc_url(home_url('/blog/')) ?>">Blog</a>
      <a href="<?= esc_url(home_url('/privacy-policy/')) ?>">Privacy policy</a>
      <a href="<?= esc_url(home_url('/cookie-policy/')) ?>">Cookie policy</a>
    </nav>
  </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>

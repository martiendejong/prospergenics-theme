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
      <a href="<?= esc_url(home_url('/contact/')) ?>">Contact</a>
      <a href="<?= esc_url(home_url('/privacy-policy/')) ?>">Privacy policy</a>
      <a href="<?= esc_url(home_url('/cookie-policy/')) ?>">Cookie policy</a>
    </nav>
  </div>
</footer>
<script>
// Mobile menu: toggle, close on Esc / link click / resize to desktop, focus handling.
(function () {
  var btn = document.querySelector('.nav-toggle');
  var panel = document.getElementById('mobile-menu');
  if (!btn || !panel) return;
  function setOpen(open, returnFocus) {
    btn.setAttribute('aria-expanded', open ? 'true' : 'false');
    btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    panel.hidden = !open;
    if (open) { var first = panel.querySelector('a'); if (first) first.focus(); }
    else if (returnFocus) { btn.focus(); }
  }
  btn.addEventListener('click', function () { setOpen(btn.getAttribute('aria-expanded') !== 'true'); });
  panel.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setOpen(false, true); });
  document.addEventListener('click', function (e) { if (!panel.hidden && !e.target.closest('.pg-nav')) setOpen(false); });
  window.matchMedia('(min-width: 881px)').addEventListener('change', function (e) { if (e.matches) setOpen(false); });
})();
</script>
<?php wp_footer(); ?>
</body>
</html>

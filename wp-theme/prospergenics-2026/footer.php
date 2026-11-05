<?php defined('ABSPATH') || exit; ?>
</main>
<?php
// The one site footer and the header's menu script, shared with every other page type (inc/site-chrome.php).
echo prospergenics_2026_site_footer(); // phpcs:ignore WordPress.Security.EscapeOutput -- built from escaped parts.
echo prospergenics_2026_site_chrome_script(); // phpcs:ignore WordPress.Security.EscapeOutput -- the theme's own script.
?>
<?php wp_footer(); ?>
</body>
</html>

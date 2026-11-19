<?php
defined('ABSPATH') || exit;
// Contact page (/contact/). Used automatically for a WordPress page with slug "contact";
// functions.php also serves it at /contact/ when no such page exists yet.
// The form posts to admin-post.php -> inc/contact-form-handler.php, which stores every
// enquiry before it tries to email it (see docs/CONTACT-AND-COMMENTS-OPS.md).
get_header();

$sent  = isset($_GET['contact_success']);
$error = isset($_GET['contact_error']) ? sanitize_text_field(wp_unslash($_GET['contact_error'])) : '';
// ?subject=Tools (from "Join", the courses empty state and the listings' buttons) preselects
// that subject; anything not on the list is ignored.
$subject = isset($_GET['subject']) ? sanitize_text_field(wp_unslash($_GET['subject'])) : '';
if (!in_array($subject, prospergenics_2026_contact_subjects(), true)) {
    $subject = '';
}
$intro = '';
if (have_posts()) {
    the_post();
    // strip shortcodes: the old Contact page may still hold a [contact-form-7] tag, and that
    // plugin isn't installed, so it would show as raw text above this form
    $intro = trim(strip_shortcodes(get_the_content()));
}
?>

<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Contact</div>
    <h1>Talk to ProsperGenics</h1>
    <p class="ph-excerpt">Questions about courses, our tools or working with the team? Send us a message, or reach us directly on WhatsApp.</p>
  </div>
</div>

<div class="contact-wrap" id="contact">
  <section class="contact-form-card" aria-labelledby="contact-form-title">
    <h2 id="contact-form-title">Send us a message</h2>
    <?php if ($intro): ?>
      <div class="post-content contact-intro"><?php echo apply_filters('the_content', $intro); ?></div>
    <?php endif; ?>

    <?php if ($sent): ?>
      <div class="form-notice form-notice-ok" role="status">
        <strong>Thank you, your message has arrived.</strong> We usually reply within two working days.
      </div>
    <?php elseif ($error): ?>
      <div class="form-notice form-notice-error" role="alert">
        <strong>Your message wasn't sent.</strong> <?php echo esc_html($error); ?>
      </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="contact-form" novalidate>
      <input type="hidden" name="action" value="prospergenics_contact_form" />
      <?php wp_nonce_field('prospergenics_contact_form_nonce', 'contact_form_nonce'); ?>
      <?php echo prospergenics_spam_protection_fields(); // phpcs:ignore WordPress.Security.EscapeOutput -- builds its own escaped markup. ?>

      <div class="field-row">
        <div class="field">
          <label for="contact-name">Name</label>
          <input type="text" id="contact-name" name="contact_name" autocomplete="name" required />
        </div>
        <div class="field">
          <label for="contact-email">Email</label>
          <input type="email" id="contact-email" name="contact_email" autocomplete="email" required />
        </div>
      </div>
      <div class="field-row">
        <div class="field">
          <label for="contact-phone">Phone <span class="optional">(optional)</span></label>
          <input type="tel" id="contact-phone" name="contact_phone" autocomplete="tel" />
        </div>
        <div class="field">
          <label for="contact-subject">What is it about?</label>
          <select id="contact-subject" name="contact_subject" required>
<?php foreach (prospergenics_2026_contact_subjects() as $pg_option): ?>
            <option value="<?= esc_attr($pg_option) ?>"<?php selected($pg_option, $subject); ?>><?= esc_html($pg_option) ?></option>
<?php endforeach; ?>
          </select>
        </div>
      </div>
      <div class="field">
        <label for="contact-message">Message</label>
        <textarea id="contact-message" name="contact_message" rows="6" required></textarea>
      </div>
      <p class="form-privacy">We use your details only to answer your message. <a href="<?= esc_url(home_url('/privacy-policy/')) ?>">Privacy policy</a></p>
      <button type="submit" class="form-submit">Send message</button>
    </form>
  </section>

  <aside class="contact-direct" aria-labelledby="contact-direct-title">
    <h2 id="contact-direct-title">Or reach us directly</h2>
    <a class="direct-card" href="https://wa.me/254741619743" target="_blank" rel="noopener">
      <span class="direct-label">WhatsApp</span>
      <span class="direct-value">+254 741 619743</span>
    </a>
    <a class="direct-card" href="<?= esc_url(home_url('/#value-test')) ?>">
      <span class="direct-label">Not sure where to start?</span>
      <span class="direct-value">Take the value test</span>
    </a>
    <p class="direct-note">ProsperGenics works from Kenya and the Netherlands.</p>
  </aside>
</div>

<?php get_footer(); ?>

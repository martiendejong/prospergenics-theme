<?php
defined('ABSPATH') || exit;
// Result page for /profile/verify/ (profiles track, card #6339): link reuse, expired or invalid links, "send again";
// also the confirmation at /profile/deleted/ after "Delete my account" (audit ARC-12).
$notice  = isset( $GLOBALS['pg_profiles_notice'] ) ? $GLOBALS['pg_profiles_notice'] : array( 'info', 'Open the link from the confirmation email to confirm your address.' );
$profile = ! empty( $GLOBALS['pg_profiles_notice_profile'] ) ? (int) $GLOBALS['pg_profiles_notice_profile'] : get_current_user_id();
get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Your account</div>
    <h1><?php echo esc_html( isset( $GLOBALS['pg_profiles_notice_title'] ) ? $GLOBALS['pg_profiles_notice_title'] : 'Confirm your email address' ); ?></h1>
  </div>
</div>
<div class="post-wrap pgp-narrow">
  <?php prospergenics_profiles_notice( $notice[0], $notice[1] ); ?>
  <p class="pgp-form-actions">
    <?php if ( $profile && get_userdata( $profile ) ) : ?>
      <a class="pgp-btn pgp-btn-primary" href="<?php echo esc_url( prospergenics_profiles_url( $profile ) ); ?>">Go to the profile</a>
    <?php endif; ?>
    <?php if ( 'deleted' === prospergenics_profiles_route() ) : ?>
      <a class="pgp-btn pgp-btn-primary" href="<?php echo esc_url( home_url( '/' ) ); ?>">Go to the homepage</a>
    <?php elseif ( ! is_user_logged_in() ) : ?>
      <a class="pgp-btn" href="<?php echo esc_url( wp_login_url( home_url( '/profile/' ) ) ); ?>">Log in</a>
    <?php endif; ?>
  </p>
</div>
<?php get_footer(); ?>

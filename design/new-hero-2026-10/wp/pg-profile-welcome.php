<?php
defined('ABSPATH') || exit;
// Onboarding wizard at /profile/welcome/ (profiles track, card #6340): headline + location, photo, first skill.
// Each step posts to this page and is saved (or skipped) immediately; progress is kept in user meta.
$uid   = get_current_user_id();
$state = prospergenics_profiles_onboarding( $uid );
$step  = isset( $GLOBALS['pg_profiles_step'] ) ? (int) $GLOBALS['pg_profiles_step'] : $state['step'];
$names = array( 1 => 'About you', 2 => 'Your photo', 3 => 'Your first skill' );
$photo = prospergenics_profiles_photo_url( $uid, 'medium' );
get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Welcome to ProsperGenics</div>
    <h1>Set up your profile</h1>
    <p class="ph-excerpt">Three quick steps. You can skip any of them and fill them in later.</p>
  </div>
</div>
<div class="post-wrap pgp-narrow">
  <ol class="pgp-steps" aria-label="Progress">
    <?php foreach ( $names as $n => $label ) : ?>
      <li class="<?php echo $n === $step ? 'is-current' : ( $n < $step ? 'is-done' : '' ); ?>"<?php echo $n === $step ? ' aria-current="step"' : ''; ?>>
        <span class="pgp-step-num" aria-hidden="true"><?php echo (int) $n; ?></span>
        <span><?php echo esc_html( $label ); ?><?php if ( $n < $step ) : ?><span class="screen-reader-text"> (<?php echo isset( $state['steps'][ $n ] ) && 'skipped' === $state['steps'][ $n ] ? 'skipped' : 'done'; ?>)</span><?php endif; ?></span>
      </li>
    <?php endforeach; ?>
  </ol>

  <?php if ( isset( $GLOBALS['pg_profiles_notice'] ) ) { prospergenics_profiles_notice( $GLOBALS['pg_profiles_notice'][0], $GLOBALS['pg_profiles_notice'][1] ); } ?>

  <form class="pgp-form pgp-wizard" method="post" action="<?php echo esc_url( home_url( '/profile/welcome/' ) ); ?>" enctype="multipart/form-data">
    <?php wp_nonce_field( 'pg_profiles_welcome', '_pgp_nonce' ); ?>
    <input type="hidden" name="step" value="<?php echo (int) $step; ?>" />
    <h2>Step <?php echo (int) $step; ?> of 3: <?php echo esc_html( $names[ $step ] ); ?></h2>

    <?php if ( 1 === $step ) : ?>
      <?php
      prospergenics_profiles_field( 'pgp-w-headline', 'headline', 'Headline', (string) prospergenics_profiles_meta( $uid, 'headline' ), 'text', array( 'maxlength' => 160, 'hint' => 'One line about what you do, e.g. “Solar installer and trainer in Kisumu”.' ) );
      prospergenics_profiles_field( 'pgp-w-location', 'location', 'Location (optional)', (string) prospergenics_profiles_meta( $uid, 'location' ), 'text', array( 'maxlength' => 100, 'autocomplete' => 'address-level2' ) );
      ?>
    <?php elseif ( 2 === $step ) : ?>
      <div class="pgp-wizard-photo">
        <?php if ( $photo ) : ?>
          <img class="pgp-photo" src="<?php echo esc_url( $photo ); ?>" alt="Your current photo" width="120" height="120" data-pgp-photo-preview />
        <?php else : ?>
          <img class="pgp-photo" alt="Preview of your new photo" width="120" height="120" hidden data-pgp-photo-preview />
        <?php endif; ?>
        <?php prospergenics_profiles_field( 'pgp-w-photo', 'photo', 'Choose a photo (JPEG, PNG or WebP, at least 200 × 200 pixels)', '', 'file', array( 'accept' => 'image/jpeg,image/png,image/webp,image/gif', 'data-pgp-photo-input' => '1', 'hint' => 'You can crop it to a square after choosing it.' ) ); ?>
      </div>
    <?php else : ?>
      <?php prospergenics_profiles_field( 'pgp-w-skill', 'skill', 'A skill you are good at', '', 'text', array( 'list' => 'pgp-skill-vocab', 'maxlength' => 60, 'autocomplete' => 'off', 'hint' => 'Start typing for suggestions, or enter your own.' ) ); ?>
      <?php prospergenics_profiles_skills_datalist(); ?>
    <?php endif; ?>

    <div class="pgp-form-actions">
      <button type="submit" class="pgp-btn pgp-btn-primary"><?php echo 3 === $step ? 'Save and finish' : 'Save and continue'; ?></button>
      <button type="submit" class="pgp-btn" name="pgp_skip" value="1" formnovalidate>Skip this step</button>
    </div>
  </form>
</div>

<?php prospergenics_profiles_crop_dialog(); ?>
<?php get_footer(); ?>

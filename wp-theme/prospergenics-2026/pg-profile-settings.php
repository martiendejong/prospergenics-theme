<?php
defined('ABSPATH') || exit;
// Privacy settings at /profile/settings/ (profiles track, card #6351). Plain form: works without JavaScript.
$uid     = get_current_user_id();
$privacy = prospergenics_profiles_privacy( $uid );
$levels  = prospergenics_profiles_levels();
$labels  = prospergenics_profiles_privacy_labels();
get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Your profile</div>
    <h1>Privacy settings</h1>
    <p class="ph-excerpt">Choose who can see each part of your profile. “Connections” are the members you follow.</p>
  </div>
</div>
<div class="post-wrap pgp-narrow">
  <?php if ( isset( $_GET['saved'] ) ) { prospergenics_profiles_notice( 'ok', 'Your privacy settings are saved.' ); } ?>
  <form class="pgp-form pgp-privacy" method="post" action="<?php echo esc_url( home_url( '/profile/settings/' ) ); ?>">
    <?php wp_nonce_field( 'pg_profiles_privacy', '_pgp_nonce' ); ?>
    <?php foreach ( $privacy as $section => $level ) : ?>
      <fieldset class="pgp-radio-group">
        <legend><?php echo esc_html( $labels[ $section ] ); ?></legend>
        <?php foreach ( $levels as $key => $label ) :
            $id = 'pgp-priv-' . $section . '-' . $key; ?>
          <label for="<?php echo esc_attr( $id ); ?>">
            <input type="radio" id="<?php echo esc_attr( $id ); ?>" name="privacy[<?php echo esc_attr( $section ); ?>]" value="<?php echo esc_attr( $key ); ?>"<?php checked( $level, $key ); ?> />
            <?php echo esc_html( $label ); ?>
          </label>
        <?php endforeach; ?>
        <?php if ( 'profile' === $section ) : ?>
          <p class="pgp-hint">This applies to the whole profile: a section is only shown to people who can also see the profile.</p>
        <?php endif; ?>
      </fieldset>
    <?php endforeach; ?>
    <div class="pgp-form-actions">
      <button type="submit" class="pgp-btn pgp-btn-primary">Save settings</button>
      <a class="pgp-btn" href="<?php echo esc_url( prospergenics_profiles_url( $uid, array( 'view' => 'public' ) ) ); ?>">See what visitors see</a>
      <a class="pgp-btn" href="<?php echo esc_url( prospergenics_profiles_url( $uid ) ); ?>">Back to your profile</a>
    </div>
  </form>
</div>
<?php get_footer(); ?>

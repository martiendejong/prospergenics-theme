<?php
defined('ABSPATH') || exit;
// Settings at /profile/settings/ (profiles track): privacy (card #6351), email and password (audit ARC-5),
// download or delete your data (ARC-12). Plain forms: everything works without JavaScript.
$uid      = get_current_user_id();
$user     = wp_get_current_user();
$privacy  = prospergenics_profiles_privacy( $uid );
$levels   = prospergenics_profiles_levels();
$labels   = prospergenics_profiles_privacy_labels();
$chosen   = prospergenics_profiles_privacy_chosen( $uid, 'profile' );
$verified = prospergenics_profiles_is_verified( $uid );
$err      = isset( $GLOBALS['pg_profiles_form_error'] ) ? $GLOBALS['pg_profiles_form_error'] : array( '', '' );
$saved    = isset( $_GET['saved'] ) ? sanitize_key( wp_unslash( $_GET['saved'] ) ) : '';
$deleting = isset( $_GET['delete'] ) || 'delete' === $err[0];
$can_del  = prospergenics_profiles_can_self_delete( $uid );
$form_error = function ( $form ) use ( $err ) {
    if ( $form === $err[0] ) {
        prospergenics_profiles_notice( 'error', $err[1] );
    }
};
get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Your account</div>
    <h1>Account and privacy settings</h1>
    <p class="ph-excerpt">Choose who can see each part of your profile, change your email address or password, and download or delete your data.</p>
  </div>
</div>
<div class="post-wrap pgp-narrow">

  <section class="pgp-section" id="pgp-privacy" aria-labelledby="pgp-privacy-h">
    <h2 id="pgp-privacy-h">Privacy</h2>
    <?php if ( 'privacy' === $saved || '1' === $saved ) { prospergenics_profiles_notice( 'ok', 'Your privacy settings are saved.' ); } ?>
    <p class="pgp-hint">“Logged-in members” means members who confirmed their email address. “Connections” are the members you follow.</p>
    <?php if ( ! $chosen ) : ?>
      <?php prospergenics_profiles_notice( 'info', 'private' === $privacy['profile']
          ? 'Your profile is private until you choose who can see it below.'
          : ( 'public' === $privacy['profile'] ? 'Your profile is public because you finished setting it up. You can change that below.' : 'Your profile is visible to logged-in members only until you choose “Everyone” below.' ) ); ?>
    <?php endif; ?>
    <form class="pgp-form pgp-privacy" method="post" action="<?php echo esc_url( home_url( '/profile/settings/' ) ); ?>">
      <?php wp_nonce_field( 'pg_profiles_privacy', '_pgp_nonce' ); ?>
      <input type="hidden" name="pgp_action" value="privacy" />
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
            <p class="pgp-hint">This applies to the whole profile: a section is only shown to people who can also see the profile. Search engines only list public profiles that are at least 40% complete.</p>
          <?php endif; ?>
        </fieldset>
      <?php endforeach; ?>
      <div class="pgp-form-actions">
        <button type="submit" class="pgp-btn pgp-btn-primary">Save privacy settings</button>
        <a class="pgp-btn" href="<?php echo esc_url( prospergenics_profiles_url( $uid, array( 'view' => 'public' ) ) ); ?>">See what visitors see</a>
        <a class="pgp-btn" href="<?php echo esc_url( prospergenics_profiles_url( $uid ) ); ?>">Back to your profile</a>
      </div>
    </form>
  </section>

  <section class="pgp-section" id="pgp-account" aria-labelledby="pgp-account-h">
    <h2 id="pgp-account-h">Email address and password</h2>
    <?php
    if ( 'email' === $saved ) {
        prospergenics_profiles_notice( 'ok', 'Your email address is changed. We sent a confirmation link to the new address; until you open it, following, endorsing and featured images are paused.' );
    } elseif ( 'email-mail-failed' === $saved ) {
        prospergenics_profiles_notice( 'error', 'Your email address is changed, but we could not send the confirmation link to it. Use “Send the link again” at the top of the page in a few minutes.' );
    } elseif ( 'password' === $saved ) {
        prospergenics_profiles_notice( 'ok', 'Your password is changed. You were logged out everywhere else; we sent a notice to your email address.' );
    }
    ?>
    <form class="pgp-form" method="post" action="<?php echo esc_url( home_url( '/profile/settings/#pgp-account' ) ); ?>">
      <?php wp_nonce_field( 'pg_profiles_email', '_pgp_nonce' ); ?>
      <input type="hidden" name="pgp_action" value="email" />
      <fieldset>
        <legend>Change your email address</legend>
        <p class="pgp-hint">Now: <strong><?php echo esc_html( $user->user_email ); ?></strong> (<?php echo $verified ? 'confirmed' : 'not confirmed yet'; ?>). A new address must be confirmed with the link we email to it.</p>
        <?php $form_error( 'email' ); ?>
        <?php
        prospergenics_profiles_field( 'pgp-new-email', 'email', 'New email address', 'email' === $err[0] && isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '', 'email', array( 'required' => true, 'autocomplete' => 'email', 'maxlength' => 100 ) );
        prospergenics_profiles_field( 'pgp-email-pw', 'current_password', 'Current password', '', 'password', array( 'required' => true, 'autocomplete' => 'current-password' ) );
        ?>
      </fieldset>
      <div class="pgp-form-actions"><button type="submit" class="pgp-btn pgp-btn-primary">Change email address</button></div>
    </form>

    <form class="pgp-form" method="post" action="<?php echo esc_url( home_url( '/profile/settings/#pgp-account' ) ); ?>">
      <?php wp_nonce_field( 'pg_profiles_password', '_pgp_nonce' ); ?>
      <input type="hidden" name="pgp_action" value="password" />
      <fieldset>
        <legend>Change your password</legend>
        <?php $form_error( 'password' ); ?>
        <?php
        prospergenics_profiles_field( 'pgp-pw-current', 'current_password', 'Current password', '', 'password', array( 'required' => true, 'autocomplete' => 'current-password' ) );
        prospergenics_profiles_field( 'pgp-pw-new', 'new_password', 'New password', '', 'password', array( 'required' => true, 'autocomplete' => 'new-password', 'minlength' => PROSPERGENICS_PROFILES_PASSWORD_MIN, 'hint' => sprintf( 'At least %d characters. A few unrelated words make a strong password.', PROSPERGENICS_PROFILES_PASSWORD_MIN ) ) );
        prospergenics_profiles_field( 'pgp-pw-confirm', 'confirm_password', 'New password again', '', 'password', array( 'required' => true, 'autocomplete' => 'new-password', 'minlength' => PROSPERGENICS_PROFILES_PASSWORD_MIN ) );
        ?>
      </fieldset>
      <div class="pgp-form-actions">
        <button type="submit" class="pgp-btn pgp-btn-primary">Change password</button>
        <a class="pgp-btn" href="<?php echo esc_url( wp_logout_url( home_url( '/' ) ) ); ?>">Log out</a>
      </div>
    </form>
  </section>

  <section class="pgp-section" id="pgp-data" aria-labelledby="pgp-data-h">
    <h2 id="pgp-data-h">Your data</h2>
    <p class="pgp-hint">What we keep and why is explained in our <a href="<?php echo esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/privacy-policy/' ) ); ?>">privacy policy</a>.</p>
    <form class="pgp-form" method="post" action="<?php echo esc_url( home_url( '/profile/settings/' ) ); ?>">
      <?php wp_nonce_field( 'pg_profiles_export', '_pgp_nonce' ); ?>
      <input type="hidden" name="pgp_action" value="export" />
      <p>Download everything we hold about your account: your account details, profile, connections, endorsements, notifications, claps, courses, progress and certificates, as one JSON file.</p>
      <div class="pgp-form-actions"><button type="submit" class="pgp-btn">Download my data</button></div>
    </form>

    <div class="pgp-form" id="pgp-delete">
      <h3 class="pgp-card-h">Delete my account</h3>
      <?php if ( ! $can_del ) : ?>
        <p>Your account can write or teach on ProsperGenics, so it is removed by an administrator, who can hand your articles and courses over first. Please <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact us</a>.</p>
      <?php elseif ( ! $deleting ) : ?>
        <p>This removes your account, your profile, your connections and endorsements, your claps, your course progress and your certificates (their verification links stop working). Comments you wrote stay, without your name.</p>
        <div class="pgp-form-actions"><a class="pgp-btn pgp-danger" href="<?php echo esc_url( add_query_arg( 'delete', '1', home_url( '/profile/settings/' ) ) . '#pgp-delete' ); ?>">Delete my account…</a></div>
      <?php else : ?>
        <form class="pgp-form" method="post" action="<?php echo esc_url( home_url( '/profile/settings/#pgp-delete' ) ); ?>">
          <?php wp_nonce_field( 'pg_profiles_delete', '_pgp_nonce' ); ?>
          <input type="hidden" name="pgp_action" value="delete" />
          <fieldset>
            <legend>Are you sure? This cannot be undone.</legend>
            <p>Your account, profile, connections, endorsements, claps, course progress and certificates are deleted straight away. Download your data first if you want to keep a copy.</p>
            <?php $form_error( 'delete' ); ?>
            <?php
            prospergenics_profiles_field( 'pgp-del-pw', 'current_password', 'Your password', '', 'password', array( 'required' => true, 'autocomplete' => 'current-password' ) );
            prospergenics_profiles_field( 'pgp-del-ok', 'confirm_delete', 'I understand that my account and data are deleted permanently', false, 'checkbox', array( 'required' => true ) );
            ?>
          </fieldset>
          <div class="pgp-form-actions">
            <button type="submit" class="pgp-btn pgp-btn-primary pgp-btn-danger">Delete my account permanently</button>
            <a class="pgp-btn" href="<?php echo esc_url( home_url( '/profile/settings/' ) ); ?>">Cancel</a>
          </div>
        </form>
      <?php endif; ?>
    </div>
  </section>
</div>
<?php get_footer(); ?>

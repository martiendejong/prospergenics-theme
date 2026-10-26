<?php
defined('ABSPATH') || exit;
// Member registration at /register/ (profiles track, card #6338): name, email, password.
// The POST is handled in template_redirect (inc/profiles/routes.php) so a success redirects before any output;
// a failure comes back here with $GLOBALS['pg_register_error'] = array( message, field errors, kept values ).
$err    = isset( $GLOBALS['pg_register_error'] ) ? $GLOBALS['pg_register_error'] : array( '', array(), array() );
$fields = $err[1];
$keep   = $err[2];
$open   = prospergenics_register_is_open();
$login  = wp_login_url( home_url( '/profile/' ) );
get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Join ProsperGenics</div>
    <h1>Create your member account</h1>
    <p class="ph-excerpt">One account for your profile, courses and articles. It takes a minute.</p>
  </div>
</div>
<div class="post-wrap pgp-narrow">
  <?php if ( ! $open ) : ?>
    <?php prospergenics_profiles_notice( 'info', 'Registration is closed at the moment.' ); ?>
    <p>We are opening the community step by step. Tell us what you would like to do and we will get back to you: <a href="<?php echo esc_url( prospergenics_2026_contact_url( prospergenics_2026_site_nav()['join_subject'] ) ); ?>">ask to join</a>. Already a member? <a href="<?php echo esc_url( $login ); ?>">Log in</a>.</p>
  <?php else : ?>
    <?php if ( '' !== $err[0] ) { prospergenics_profiles_notice( 'error', $err[0] ); } ?>
    <form class="pgp-form" method="post" action="<?php echo esc_url( home_url( '/register/' ) ); ?>">
      <?php wp_nonce_field( 'pg_register', '_pgr_nonce' ); ?>
      <?php echo prospergenics_spam_protection_fields(); // phpcs:ignore WordPress.Security.EscapeOutput -- markup built and escaped in spam-protection.php ?>
      <fieldset>
        <legend>Your details</legend>
        <?php
        $attrs = function ( $name, array $base ) use ( $fields ) {
            if ( isset( $fields[ $name ] ) ) {
                $base['aria-invalid'] = 'true';
            }
            return $base;
        };
        $show = function ( $name ) use ( $fields ) {
            if ( isset( $fields[ $name ] ) ) {
                echo '<p class="pgp-form-error" id="pgr-' . esc_attr( $name ) . '-error" role="alert">' . esc_html( $fields[ $name ] ) . '</p>';
            }
        };
        prospergenics_profiles_field( 'pgr-name', 'name', 'Your name', isset( $keep['name'] ) ? $keep['name'] : '', 'text', $attrs( 'name', array( 'required' => true, 'autocomplete' => 'name', 'maxlength' => PROSPERGENICS_REGISTER_NAME_MAX, 'hint' => 'As you want it shown on your profile. You can change it later.' ) ) );
        $show( 'name' );
        prospergenics_profiles_field( 'pgr-email', 'email', 'Email address', isset( $keep['email'] ) ? $keep['email'] : '', 'email', $attrs( 'email', array( 'required' => true, 'autocomplete' => 'email', 'maxlength' => PROSPERGENICS_REGISTER_EMAIL_MAX, 'hint' => 'We email you a link to confirm it. It stays private unless you choose otherwise.' ) ) );
        $show( 'email' );
        prospergenics_profiles_field( 'pgr-password', 'password', 'Password', '', 'password', $attrs( 'password', array( 'required' => true, 'autocomplete' => 'new-password', 'minlength' => PROSPERGENICS_REGISTER_PASSWORD_MIN, 'maxlength' => PROSPERGENICS_REGISTER_PASSWORD_MAX, 'hint' => sprintf( 'At least %d characters. A few unrelated words make a strong password.', PROSPERGENICS_REGISTER_PASSWORD_MIN ) ) ) );
        $show( 'password' );
        ?>
      </fieldset>
      <p class="pgp-hint">By creating an account you accept our <a href="<?php echo esc_url( get_privacy_policy_url() ? get_privacy_policy_url() : home_url( '/privacy-policy/' ) ); ?>">privacy policy</a>.</p>
      <div class="pgp-form-actions">
        <button type="submit" class="pgp-btn pgp-btn-primary">Create my account</button>
        <a class="pgp-btn" href="<?php echo esc_url( $login ); ?>">I already have an account</a>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php get_footer(); ?>

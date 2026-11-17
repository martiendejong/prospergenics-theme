<?php
/**
 * Expert listing self-service form at /profile/expert/.
 * Served by experts/self-service.php.
 */
defined( 'ABSPATH' ) || exit;

if ( ! is_user_logged_in() ) {
	wp_safe_redirect( wp_login_url( home_url( '/profile/expert/' ) ) );
	exit;
}

$user_id     = get_current_user_id();
$status      = get_user_meta( $user_id, '_pg_is_expert', true );
$saved       = isset( $_GET['saved'] ); // phpcs:ignore WordPress.Security.NonceVerification
$errors      = $saved ? array() : (array) get_transient( 'pg_expert_form_errors_' . $user_id );
if ( $errors ) {
	delete_transient( 'pg_expert_form_errors_' . $user_id );
}

$bio         = (string) get_user_meta( $user_id, '_pg_expert_bio',        true );
$title       = (string) get_user_meta( $user_id, '_pg_expert_title',      true );
$saved_cats  = (array)  json_decode( (string) get_user_meta( $user_id, '_pg_expert_categories', true ), true );
$saved_links = (array)  json_decode( (string) get_user_meta( $user_id, '_pg_expert_links',      true ), true );
$cats        = prospergenics_expert_categories();

add_filter( 'pre_get_document_title', function () {
	return 'Your Expert Listing - ProsperGenics';
} );

get_template_part( 'header' );
?>
<main id="main" class="pg-inner pg-profile">
  <div class="pf-wrap">
    <h1>Your Expert Listing</h1>

    <?php if ( '1' === $status ) : ?>
      <div class="pf-notice pf-notice--success">Your profile is <strong>listed</strong> in the <a href="<?php echo esc_url( home_url( '/experts/' ) ); ?>">experts directory</a>.</div>
    <?php elseif ( 'pending' === $status ) : ?>
      <div class="pf-notice pf-notice--info">Your listing is <strong>pending review</strong>. We will email you once it is approved.</div>
    <?php else : ?>
      <div class="pf-notice">Fill in the form below to apply to be listed in the AI experts directory.</div>
    <?php endif; ?>

    <?php if ( $saved ) : ?>
      <div class="pf-notice pf-notice--success">Saved. <?php echo '1' !== $status ? 'Your listing is under review.' : ''; ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <?php wp_nonce_field( 'pg_expert_save', '_pg_expert_nonce' ); ?>

      <div class="pf-field <?php echo isset( $errors['expert_title'] ) ? 'pf-field--error' : ''; ?>">
        <label for="expert-title">Professional title / tagline</label>
        <input id="expert-title" name="expert_title" type="text" value="<?php echo esc_attr( $title ); ?>" maxlength="120" placeholder="e.g. AI Agent Developer, ProsperGenics" />
        <?php if ( isset( $errors['expert_title'] ) ) : ?><p class="pf-error"><?php echo esc_html( $errors['expert_title'] ); ?></p><?php endif; ?>
      </div>

      <div class="pf-field <?php echo isset( $errors['expert_bio'] ) ? 'pf-field--error' : ''; ?>">
        <label for="expert-bio">Bio <span class="pf-hint">(up to 2000 characters)</span></label>
        <textarea id="expert-bio" name="expert_bio" rows="6" maxlength="2000"><?php echo esc_textarea( $bio ); ?></textarea>
        <?php if ( isset( $errors['expert_bio'] ) ) : ?><p class="pf-error"><?php echo esc_html( $errors['expert_bio'] ); ?></p><?php endif; ?>
      </div>

      <div class="pf-field <?php echo isset( $errors['expert_categories'] ) ? 'pf-field--error' : ''; ?>">
        <label>Expertise categories <span class="pf-hint">(choose all that apply)</span></label>
        <div class="pf-checkboxes">
        <?php foreach ( $cats as $cat ) : ?>
          <label class="pf-checkbox">
            <input type="checkbox" name="expert_categories[]" value="<?php echo esc_attr( $cat['slug'] ); ?>"
              <?php checked( in_array( $cat['slug'], $saved_cats, true ) ); ?> />
            <?php echo esc_html( $cat['label'] ); ?>
          </label>
        <?php endforeach; ?>
        </div>
        <?php if ( isset( $errors['expert_categories'] ) ) : ?><p class="pf-error"><?php echo esc_html( $errors['expert_categories'] ); ?></p><?php endif; ?>
      </div>

      <div class="pf-field">
        <label>Links <span class="pf-hint">(up to 5: website, LinkedIn, GitHub, etc.)</span></label>
        <?php for ( $i = 0; $i < 5; $i++ ) :
          $link = isset( $saved_links[ $i ] ) ? $saved_links[ $i ] : array(); ?>
          <div class="pf-link-row">
            <input type="text" name="expert_links[<?php echo $i; ?>][label]" value="<?php echo esc_attr( isset( $link['label'] ) ? $link['label'] : '' ); ?>" placeholder="Label (e.g. LinkedIn)" />
            <input type="url"  name="expert_links[<?php echo $i; ?>][url]"   value="<?php echo esc_url( isset( $link['url'] ) ? $link['url'] : '' ); ?>"     placeholder="https://..." />
          </div>
        <?php endfor; ?>
      </div>

      <button type="submit" class="btn btn-primary">Save listing</button>
    </form>

    <p class="pf-hint">Your listing is reviewed before it appears in the directory. After approval it will be accessible at <code>/experts/<?php echo esc_html( strtolower( wp_get_current_user()->user_login ) ); ?>/</code>.</p>
  </div>
</main>
<?php get_template_part( 'footer' ); ?>

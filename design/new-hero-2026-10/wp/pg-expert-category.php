<?php
/**
 * AI Experts Directory: category filter page at /experts/category/{slug}/.
 * Served by experts/routes.php when pg_experts_category is set.
 */
defined( 'ABSPATH' ) || exit;

$cat_slug = sanitize_key( (string) get_query_var( 'pg_experts_category' ) );
$cat      = prospergenics_expert_category( $cat_slug );

if ( ! $cat ) {
	global $wp_query;
	$wp_query->is_404 = true;
	status_header( 404 );
	get_template_part( '404' );
	exit;
}

$experts = prospergenics_experts_all( $cat_slug );

add_filter( 'pre_get_document_title', function () use ( $cat ) {
	return esc_html( $cat['label'] ) . ' Experts - ProsperGenics';
} );

get_template_part( 'header' );
?>
<main id="main" class="pg-inner">
  <section class="l-hero l-hero--experts">
    <div class="wrap">
      <p class="eyebrow"><span class="dot"></span>Experts</p>
      <h1><?php echo esc_html( $cat['label'] ); ?></h1>
      <p class="lede"><?php echo esc_html( $cat['description'] ); ?></p>
    </div>
  </section>

  <?php echo wp_kses_post( prospergenics_experts_category_tabs( $cat_slug ) ); ?>

  <section class="l-list">
    <div class="wrap">
      <?php if ( $experts ) : ?>
        <div class="grid">
          <?php foreach ( $experts as $expert ) : ?>
            <?php echo wp_kses_post( prospergenics_expert_card( $expert ) ); ?>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <p>No experts in this category yet. <a href="<?php echo esc_url( home_url( '/profile/expert/' ) ); ?>">Apply to be listed.</a></p>
      <?php endif; ?>
    </div>
  </section>
</main>
<?php get_template_part( 'footer' ); ?>

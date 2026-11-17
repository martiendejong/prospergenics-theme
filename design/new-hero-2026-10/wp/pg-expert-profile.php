<?php
/**
 * AI Experts Directory: individual expert profile at /experts/{slug}/.
 * Served by experts/routes.php when pg_expert is set.
 */
defined( 'ABSPATH' ) || exit;

$slug   = sanitize_title( (string) get_query_var( 'pg_expert' ) );
$expert = prospergenics_expert_by_slug( $slug );

if ( ! $expert ) {
	global $wp_query;
	$wp_query->is_404 = true;
	status_header( 404 );
	get_template_part( '404' );
	exit;
}

add_filter( 'pre_get_document_title', function () use ( $expert ) {
	return esc_html( $expert['name'] ) . ' - AI Expert at ProsperGenics';
} );

get_template_part( 'header' );
?>
<main id="main" class="pg-inner pg-expert-profile">
  <?php echo wp_kses_post( prospergenics_expert_profile_html( $expert ) ); ?>

  <section class="ep-back">
    <div class="wrap">
      <a href="<?php echo esc_url( home_url( '/experts/' ) ); ?>">&larr; All experts</a>
    </div>
  </section>
</main>
<?php get_template_part( 'footer' ); ?>

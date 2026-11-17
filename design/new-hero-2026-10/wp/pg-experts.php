<?php
/**
 * AI Experts Directory: main listing page at /experts/.
 * Served by experts/routes.php when pg_experts_page=directory.
 */
defined( 'ABSPATH' ) || exit;

$experts = prospergenics_experts_all();
$cats    = prospergenics_expert_categories();

add_filter( 'pre_get_document_title', function () {
	return 'AI Experts Directory - ProsperGenics';
} );

get_template_part( 'header' );
?>
<main id="main" class="pg-inner">
  <section class="l-hero l-hero--experts">
    <div class="wrap">
      <p class="eyebrow"><span class="dot"></span>Our experts</p>
      <h1>AI Experts who <span class="accent">build value.</span></h1>
      <p class="lede">Browse professionals in our community by expertise category — from AI agents to web design, training and business development.</p>
    </div>
  </section>

  <?php echo wp_kses_post( prospergenics_experts_category_tabs() ); ?>

  <section class="l-list">
    <div class="wrap">
      <?php if ( $experts ) : ?>
        <div class="grid">
          <?php foreach ( $experts as $expert ) : ?>
            <?php echo wp_kses_post( prospergenics_expert_card( $expert ) ); ?>
          <?php endforeach; ?>
        </div>
      <?php else : ?>
        <p>No experts found. <a href="<?php echo esc_url( home_url( '/profile/expert/' ) ); ?>">Apply to be listed.</a></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ( ! is_user_logged_in() ) : ?>
  <section class="ep-cta">
    <div class="wrap">
      <h2>Are you an AI professional?</h2>
      <p>Join ProsperGenics and add yourself to the directory. Your profile shows your skills, experience and projects to potential clients and collaborators.</p>
      <a class="btn btn-primary" href="<?php echo esc_url( home_url( '/join/' ) ); ?>">Join and apply</a>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php get_template_part( 'footer' ); ?>

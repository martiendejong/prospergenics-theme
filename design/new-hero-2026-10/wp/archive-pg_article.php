<?php
/**
 * Articles listing (card #6366): /articles/, and through the taxonomy-pg_*.php templates
 * /articles/category/<slug>/, /articles/tag/<slug>/ and /series/<slug>/ (card #6370).
 */
defined( 'ABSPATH' ) || exit;

$pga_series = is_tax( 'pg_series' );
if ( is_tax() ) {
    $pga_term    = get_queried_object();
    $pga_heading = $pga_term->name;
    $pga_eyebrow = is_tax( 'pg_series' ) ? 'Series' : ( is_tax( 'pg_article_tag' ) ? 'Articles tagged' : 'Articles' );
    $pga_intro   = trim( wp_strip_all_tags( term_description( $pga_term ) ) );
    if ( $pga_series ) {
        $pga_intro = trim( $pga_intro . ' ' . sprintf( _n( '%d part, in reading order.', '%d parts, in reading order.', (int) $GLOBALS['wp_query']->found_posts ), (int) $GLOBALS['wp_query']->found_posts ) );
    }
} else {
    $pga_heading = 'Articles';
    $pga_eyebrow = 'ProsperGenics';
    $pga_intro   = 'Lessons, research and stories from ProsperGenics members on creating value.';
}

get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner pga-hero-wide">
    <div class="ph-eyebrow"><span class="dot"></span><?php echo esc_html( $pga_eyebrow ); ?></div>
    <h1><?php echo esc_html( $pga_heading ); ?></h1>
    <?php if ( $pga_intro ) : ?>
      <p class="ph-excerpt"><?php echo esc_html( $pga_intro ); ?></p>
    <?php endif; ?>
    <?php if ( current_user_can( 'edit_posts' ) ) : ?>
      <p class="pga-hero-actions">
        <a class="pga-btn pga-btn-light" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pg_article' ) ); ?>">Write an article</a>
        <a class="pga-btn pga-btn-outline" href="<?php echo esc_url( prospergenics_articles_mine_url() ); ?>">My articles</a>
      </p>
    <?php endif; ?>
  </div>
</div>

<div class="pga-list">
  <?php if ( is_tax() ) : ?>
    <a class="post-back" href="<?php echo esc_url( get_post_type_archive_link( 'pg_article' ) ); ?>">← All articles</a>
  <?php endif; ?>
  <?php echo prospergenics_articles_filters(); ?>

  <?php if ( have_posts() ) : ?>
    <?php $pga_tag = $pga_series ? 'ol' : 'ul'; ?>
    <<?php echo $pga_tag; ?> class="pga-grid<?php echo $pga_series ? ' pga-grid-series' : ''; ?>">
      <?php while ( have_posts() ) : the_post(); ?>
        <li><?php echo prospergenics_articles_card( get_post() ); ?></li>
      <?php endwhile; ?>
    </<?php echo $pga_tag; ?>>
    <?php $pga_pages = paginate_links( array( 'prev_text' => '← Newer', 'next_text' => 'Older →' ) ); ?>
    <?php if ( $pga_pages ) : ?>
      <nav class="pagination" aria-label="Pages"><?php echo $pga_pages; ?></nav>
    <?php endif; ?>
  <?php else : ?>
    <p class="empty-note">No articles here yet. <a href="<?php echo esc_url( get_post_type_archive_link( 'pg_article' ) ); ?>">See all articles</a>.</p>
  <?php endif; ?>
</div>
<?php get_footer(); ?>

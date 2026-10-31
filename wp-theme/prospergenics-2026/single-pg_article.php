<?php
/**
 * One article at /articles/<slug>/ (card #6367): hero with title and cover image, author byline,
 * reading time, claps (#6369), share links, series navigation (#6370) and related articles.
 */
defined( 'ABSPATH' ) || exit;
get_header();
?>
<?php while ( have_posts() ) : the_post(); ?>
<?php
$pga_post = get_post();
$pga_cat  = prospergenics_articles_primary_category( $pga_post );
$pga_tags = get_the_terms( $pga_post, 'pg_article_tag' );
$pga_rel  = prospergenics_articles_related( $pga_post, 3 );
?>
<article class="pga-article">
  <header class="post-hero pga-article-hero">
    <div class="post-hero-inner">
      <div class="ph-eyebrow"><span class="dot"></span>
        <?php if ( $pga_cat ) : ?>
          <a class="pga-eyebrow-link" href="<?php echo esc_url( get_term_link( $pga_cat ) ); ?>"><?php echo esc_html( $pga_cat->name ); ?></a>
        <?php else : ?>
          <a class="pga-eyebrow-link" href="<?php echo esc_url( get_post_type_archive_link( 'pg_article' ) ); ?>">Articles</a>
        <?php endif; ?>
      </div>
      <h1><?php the_title(); ?></h1>
      <?php if ( has_excerpt() ) : ?>
        <p class="ph-excerpt"><?php echo esc_html( get_the_excerpt() ); ?></p>
      <?php endif; ?>
      <div class="pga-byline">
        <?php echo prospergenics_2026_avatar_html( $pga_post->post_author, 48, 'pga-avatar' ); // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in the helper ?>
        <div class="pga-byline-text">
          <span class="pgb-with-badge">
            <?php echo prospergenics_articles_author_name_html( $pga_post->post_author ); ?>
            <?php if ( function_exists( 'prospergenics_profiles_badge_html' ) ) { echo prospergenics_profiles_badge_html( $pga_post->post_author ); } // member badge (inc/profiles/badges.php), escaped in the helper ?>
          </span>
          <span class="pga-byline-facts">
            <time datetime="<?php echo esc_attr( get_the_date( 'c' ) ); ?>"><?php echo esc_html( get_the_date( 'j F Y' ) ); ?></time>
            <span aria-hidden="true">·</span>
            <span class="pga-reading"><?php echo prospergenics_articles_icon( 'clock' ); ?><?php echo esc_html( prospergenics_articles_reading_label( $pga_post ) ); ?></span>
          </span>
        </div>
      </div>
    </div>
  </header>

  <div class="post-wrap pga-article-wrap">
    <?php echo prospergenics_articles_hidden_notice( $pga_post ); // escaped in the helper (#6472) ?>
    <?php if ( has_post_thumbnail() ) : ?>
      <figure class="pga-cover">
        <?php the_post_thumbnail( 'large', array( 'alt' => '', 'fetchpriority' => 'high', 'loading' => 'eager' ) ); ?>
        <?php $pga_caption = get_the_post_thumbnail_caption(); ?>
        <?php if ( $pga_caption ) : ?>
          <figcaption><?php echo esc_html( $pga_caption ); ?></figcaption>
        <?php endif; ?>
      </figure>
    <?php endif; ?>

    <div class="post-content pga-content">
      <?php the_content(); ?>
    </div>

    <?php if ( $pga_tags && ! is_wp_error( $pga_tags ) ) : ?>
      <ul class="pga-tags" aria-label="Tags">
        <?php foreach ( $pga_tags as $pga_t ) : ?>
          <li><a href="<?php echo esc_url( get_term_link( $pga_t ) ); ?>">#<?php echo esc_html( $pga_t->name ); ?></a></li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>

    <div class="pga-actions">
      <?php echo prospergenics_articles_clap_box( $pga_post ); ?>
      <?php echo prospergenics_articles_share( $pga_post ); ?>
      <?php if ( function_exists( 'prospergenics_profiles_report_html' ) ) { echo prospergenics_profiles_report_html( 'article', $pga_post->ID ); } // #6472, escaped in the helper ?>
    </div>

    <?php echo prospergenics_articles_series_box( $pga_post ); ?>
  </div>
</article>

<?php if ( $pga_rel ) : ?>
  <section class="pga-related" aria-labelledby="pga-related-title">
    <div class="pga-related-inner">
      <h2 id="pga-related-title">Related articles</h2>
      <ul class="pga-grid">
        <?php foreach ( $pga_rel as $pga_r ) : ?>
          <li><?php echo prospergenics_articles_card( $pga_r, 'h3' ); ?></li>
        <?php endforeach; ?>
      </ul>
      <a class="pga-btn pga-btn-ghost" href="<?php echo esc_url( get_post_type_archive_link( 'pg_article' ) ); ?>">All articles</a>
    </div>
  </section>
<?php endif; ?>

<?php endwhile; ?>
<?php get_footer(); ?>

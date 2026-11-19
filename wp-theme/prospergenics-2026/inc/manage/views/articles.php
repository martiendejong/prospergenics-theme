<?php
/**
 * View: /manage/articles/ (card #6457), the articles waiting for review: the same queue as "Waiting
 * for review" on /articles/mine/ (prospergenics_articles_review_queue_args(), inc/articles/mine.php)
 * with its "Publish now" action (pg_articles_publish, which returns here), and links to the other
 * queues: Reports, Users → Requests, the instructor dashboard.
 */

defined( 'ABSPATH' ) || exit;

$pgm_paged  = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification -- paging
$pgm_query  = new WP_Query( prospergenics_articles_review_queue_args( $pgm_paged, PROSPERGENICS_MANAGE_PER_PAGE ) );
$pgm_queues = prospergenics_manage_queues();

prospergenics_manage_open( 'articles', 'Articles members sent for review, the one waiting longest first. Read, edit and publish them, or send them back from the editor.' );
?>
  <p class="pgm-count" role="status"><?php echo esc_html( sprintf( _n( '%s article waiting for review', '%s articles waiting for review', (int) $pgm_query->found_posts ), number_format_i18n( (int) $pgm_query->found_posts ) ) ); ?></p>

  <?php if ( $pgm_query->have_posts() ) : ?>
    <ul class="pgm-list">
      <?php foreach ( $pgm_query->posts as $pgm_post ) :
          $pgm_title  = wp_strip_all_tags( get_the_title( $pgm_post ) );
          $pgm_title  = '' === $pgm_title ? '(untitled)' : $pgm_title;
          $pgm_author = get_userdata( (int) $pgm_post->post_author );
          ?>
        <li class="pgm-item">
          <div class="pgm-item-main">
            <h2 class="pgm-item-title"><?php echo esc_html( $pgm_title ); ?></h2>
            <p class="pgm-facts">
              By <?php echo esc_html( $pgm_author ? $pgm_author->display_name : 'a former member' ); ?>
              <span aria-hidden="true">·</span> Last saved <time datetime="<?php echo esc_attr( get_post_modified_time( 'c', true, $pgm_post ) ); ?>"><?php echo esc_html( get_post_modified_time( 'j M Y, H:i', false, $pgm_post ) ); ?></time>
              <?php if ( function_exists( 'prospergenics_articles_reading_label' ) ) : ?><span aria-hidden="true">·</span> <?php echo esc_html( prospergenics_articles_reading_label( $pgm_post ) ); ?><?php endif; ?>
            </p>
          </div>
          <div class="pgm-item-actions">
            <?php if ( current_user_can( 'edit_post', $pgm_post->ID ) ) : ?>
              <a class="pgm-btn pgm-btn-ghost" href="<?php echo esc_url( get_edit_post_link( $pgm_post->ID, 'url' ) ); ?>">Edit<span class="screen-reader-text"> “<?php echo esc_html( $pgm_title ); ?>”</span></a>
            <?php endif; ?>
            <a class="pgm-btn pgm-btn-ghost" href="<?php echo esc_url( get_preview_post_link( $pgm_post ) ); ?>">Preview<span class="screen-reader-text"> “<?php echo esc_html( $pgm_title ); ?>”</span></a>
            <?php if ( current_user_can( 'publish_post', $pgm_post->ID ) ) : ?>
              <form class="pgm-inline" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <input type="hidden" name="action" value="pg_articles_publish" />
                <input type="hidden" name="post_id" value="<?php echo esc_attr( $pgm_post->ID ); ?>" />
                <input type="hidden" name="pg_from" value="manage" />
                <?php wp_nonce_field( 'pg_articles_publish_' . $pgm_post->ID ); ?>
                <button class="pgm-btn" type="submit">Publish now<span class="screen-reader-text"> “<?php echo esc_html( $pgm_title ); ?>”</span></button>
              </form>
            <?php endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php echo prospergenics_manage_pagination( prospergenics_manage_url( 'articles' ), $pgm_paged, (int) $pgm_query->max_num_pages ); // escaped by paginate_links ?>
  <?php else : ?>
    <p class="empty-note">No articles are waiting for review.</p>
  <?php endif; ?>

  <section class="pgm-section" aria-labelledby="pgm-more-h">
    <h2 class="pgm-h2" id="pgm-more-h">Other queues</h2>
    <?php echo prospergenics_manage_other_queues_html( $pgm_queues ); // escaped inside ?>
  </section>
<?php
prospergenics_manage_close();

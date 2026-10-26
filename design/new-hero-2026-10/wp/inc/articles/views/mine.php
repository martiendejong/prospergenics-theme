<?php
/**
 * View: "My articles" (/articles/mine/), loaded by inc/articles/mine.php for logged-in users.
 */

defined( 'ABSPATH' ) || exit;

$pga_user    = wp_get_current_user();
$pga_can     = current_user_can( 'edit_pg_articles' );
$pga_filters = prospergenics_articles_mine_filters();
$pga_filter  = prospergenics_articles_mine_filter();
$pga_paged   = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
$pga_queue   = prospergenics_articles_mine_filter_is_queue( $pga_filter );

$pga_counts = array();
$pga_claps  = 0;
if ( $pga_can ) {
    $pga_all = get_posts( array(
        'post_type'      => 'pg_article',
        'post_status'    => $pga_filters['all'][1],
        'author'         => $pga_user->ID,
        'posts_per_page' => -1,
        'fields'         => 'ids',
        'no_found_rows'  => true,
    ) );
    foreach ( $pga_all as $pga_id ) {
        $pga_status                = get_post_status( $pga_id );
        $pga_counts[ $pga_status ] = ( isset( $pga_counts[ $pga_status ] ) ? $pga_counts[ $pga_status ] : 0 ) + 1;
        $pga_claps                += prospergenics_articles_claps( $pga_id );
    }
    $pga_args = array(
        'post_type'      => 'pg_article',
        'post_status'    => $pga_filters[ $pga_filter ][1],
        'author'         => $pga_user->ID,
        'posts_per_page' => 20,
        'paged'          => $pga_paged,
        'orderby'        => 'modified',
        'order'          => 'DESC',
    );
    if ( isset( $pga_filters['review'] ) ) {
        // the review queue: everyone's pending articles, oldest submission first
        $pga_review_n = count( get_posts( array( 'post_type' => 'pg_article', 'post_status' => 'pending', 'posts_per_page' => -1, 'fields' => 'ids', 'no_found_rows' => true ) ) );
        if ( $pga_queue ) {
            unset( $pga_args['author'] );
            $pga_args['order'] = 'ASC';
        }
    }
    $pga_query = new WP_Query( $pga_args );
}
$pga_notices = array(
    'unpublished' => array( 'ok', 'The article is a draft again and no longer visible to readers.' ),
    'published'   => array( 'ok', 'The article is published.' ),
    'failed'      => array( 'error', 'That did not work. The article may already have changed; please try again.' ),
);
$pga_done = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';

get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Articles</div>
    <h1>My articles</h1>
    <p class="ph-excerpt">Your drafts, scheduled and published articles<?php echo $pga_can ? esc_html( sprintf( ', with %s %s in total.', number_format_i18n( $pga_claps ), _n( 'clap', 'claps', $pga_claps ) ) ) : '.'; ?></p>
    <?php if ( $pga_can ) : ?>
      <p class="pga-hero-actions"><a class="pga-btn pga-btn-light" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pg_article' ) ); ?>">Write a new article</a></p>
    <?php endif; ?>
  </div>
</div>

<div class="pga-mine">
  <?php if ( isset( $pga_notices[ $pga_done ] ) ) : ?>
    <p class="form-notice form-notice-<?php echo esc_attr( $pga_notices[ $pga_done ][0] ); ?>" role="status"><?php echo esc_html( $pga_notices[ $pga_done ][1] ); ?></p>
  <?php endif; ?>

  <?php if ( ! $pga_can ) : ?>
    <p class="empty-note">Your account cannot write articles yet. Tell the ProsperGenics team what you would like to write about: once they approve, you can write here and send your articles for review.</p>
    <?php echo prospergenics_profiles_application_box( 'write', 'pga-btn' ); // escaped inside ?>
  <?php else : ?>
    <nav class="pga-chips" aria-label="Filter by status">
      <?php foreach ( $pga_filters as $pga_key => $pga_f ) :
          $pga_n = 0;
          foreach ( $pga_f[1] as $pga_s ) {
              $pga_n += isset( $pga_counts[ $pga_s ] ) ? $pga_counts[ $pga_s ] : 0;
          }
          if ( prospergenics_articles_mine_filter_is_queue( $pga_key ) ) {
              $pga_n = $pga_review_n;
          }
          ?>
        <a class="pga-chip" href="<?php echo esc_url( prospergenics_articles_mine_url( 'all' === $pga_key ? array() : array( 'status' => $pga_key ) ) ); ?>"<?php echo $pga_key === $pga_filter ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $pga_f[0] ); ?> <span class="pga-chip-count">(<?php echo esc_html( number_format_i18n( $pga_n ) ); ?>)</span></a>
      <?php endforeach; ?>
    </nav>

    <?php if ( $pga_query->have_posts() ) : ?>
      <ul class="pga-mine-list">
        <?php foreach ( $pga_query->posts as $pga_post ) :
            $pga_status = $pga_post->post_status;
            $pga_title  = wp_strip_all_tags( get_the_title( $pga_post ) );
            $pga_title  = '' === $pga_title ? '(untitled)' : $pga_title;
            ?>
          <li class="pga-mine-item">
            <div class="pga-mine-main">
              <span class="pga-status pga-status-<?php echo esc_attr( $pga_status ); ?>"><?php echo esc_html( prospergenics_articles_status_label( $pga_status ) ); ?></span>
              <h2 class="pga-mine-title"><?php echo esc_html( $pga_title ); ?></h2>
              <p class="pga-mine-facts">
                <?php if ( (int) $pga_post->post_author !== (int) $pga_user->ID ) : $pga_author = get_userdata( (int) $pga_post->post_author ); ?>
                  By <?php echo esc_html( $pga_author ? $pga_author->display_name : '?' ); ?> <span aria-hidden="true">·</span>
                <?php endif; ?>
                <?php if ( 'future' === $pga_status ) : ?>
                  Publishes <time datetime="<?php echo esc_attr( get_post_time( 'c', true, $pga_post ) ); ?>"><?php echo esc_html( get_post_time( 'j M Y, H:i', false, $pga_post ) ); ?></time>
                <?php elseif ( 'publish' === $pga_status ) : ?>
                  Published <time datetime="<?php echo esc_attr( get_post_time( 'c', true, $pga_post ) ); ?>"><?php echo esc_html( get_post_time( 'j M Y', false, $pga_post ) ); ?></time>
                <?php else : ?>
                  Last saved <time datetime="<?php echo esc_attr( get_post_modified_time( 'c', true, $pga_post ) ); ?>"><?php echo esc_html( get_post_modified_time( 'j M Y, H:i', false, $pga_post ) ); ?></time>
                <?php endif; ?>
                <span aria-hidden="true">·</span> <?php echo esc_html( prospergenics_articles_reading_label( $pga_post ) ); ?>
                <span aria-hidden="true">·</span> <?php echo prospergenics_articles_claps_badge( $pga_post->ID ); ?>
              </p>
            </div>
            <div class="pga-mine-actions">
              <?php if ( current_user_can( 'edit_post', $pga_post->ID ) ) : ?>
                <a class="pga-btn" href="<?php echo esc_url( get_edit_post_link( $pga_post->ID, 'url' ) ); ?>">Edit<span class="screen-reader-text"> “<?php echo esc_html( $pga_title ); ?>”</span></a>
              <?php endif; ?>
              <?php if ( 'publish' === $pga_status ) : ?>
                <a class="pga-btn pga-btn-ghost" href="<?php echo esc_url( get_permalink( $pga_post ) ); ?>">View<span class="screen-reader-text"> “<?php echo esc_html( $pga_title ); ?>”</span></a>
              <?php else : ?>
                <a class="pga-btn pga-btn-ghost" href="<?php echo esc_url( get_preview_post_link( $pga_post ) ); ?>">Preview<span class="screen-reader-text"> “<?php echo esc_html( $pga_title ); ?>”</span></a>
              <?php endif; ?>
              <?php if ( in_array( $pga_status, array( 'publish', 'future' ), true ) && current_user_can( 'edit_post', $pga_post->ID ) ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="pg_articles_unpublish" />
                  <input type="hidden" name="post_id" value="<?php echo esc_attr( $pga_post->ID ); ?>" />
                  <?php wp_nonce_field( 'pg_articles_unpublish_' . $pga_post->ID ); ?>
                  <button class="pga-btn pga-btn-ghost" type="submit"><?php echo 'future' === $pga_status ? 'Cancel schedule' : 'Unpublish'; ?><span class="screen-reader-text"> “<?php echo esc_html( $pga_title ); ?>”</span></button>
                </form>
              <?php endif; ?>
              <?php if ( 'publish' !== $pga_status && current_user_can( 'publish_post', $pga_post->ID ) ) : ?>
                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                  <input type="hidden" name="action" value="pg_articles_publish" />
                  <input type="hidden" name="post_id" value="<?php echo esc_attr( $pga_post->ID ); ?>" />
                  <?php wp_nonce_field( 'pg_articles_publish_' . $pga_post->ID ); ?>
                  <button class="pga-btn pga-btn-ghost" type="submit">Publish now<span class="screen-reader-text"> “<?php echo esc_html( $pga_title ); ?>”</span></button>
                </form>
              <?php endif; ?>
            </div>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if ( $pga_query->max_num_pages > 1 ) : ?>
        <nav class="pagination" aria-label="Pages">
          <?php
          echo paginate_links( array(
              'base'    => esc_url_raw( add_query_arg( 'pg', '%#%', prospergenics_articles_mine_url( 'all' === $pga_filter ? array() : array( 'status' => $pga_filter ) ) ) ),
              'format'  => '',
              'current' => $pga_paged,
              'total'   => $pga_query->max_num_pages,
          ) );
          ?>
        </nav>
      <?php endif; ?>
    <?php elseif ( $pga_queue ) : ?>
      <p class="empty-note">No articles are waiting for review.</p>
    <?php else : ?>
      <p class="empty-note">Nothing here yet. <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pg_article' ) ); ?>">Write your first article</a>: it saves as a draft while you write.</p>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php
get_footer();

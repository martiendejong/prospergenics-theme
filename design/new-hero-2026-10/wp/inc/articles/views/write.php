<?php
/**
 * View: "Write an article" (/articles/write/), loaded by inc/articles/editor.php for logged-in users.
 */

defined( 'ABSPATH' ) || exit;

$pgw_id   = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;
$pgw_post = $pgw_id ? get_post( $pgw_id ) : null;
$pgw_can  = current_user_can( 'edit_pg_articles' );
if ( $pgw_id && ( ! $pgw_post || 'pg_article' !== $pgw_post->post_type || ! current_user_can( 'edit_post', $pgw_id ) ) ) {
    $pgw_post = null;
    $pgw_id   = 0;
    $pgw_bad  = true;
}
$pgw_status  = $pgw_post ? $pgw_post->post_status : 'draft';
$pgw_publish = $pgw_can && current_user_can( 'publish_pg_articles' ) && ( ! $pgw_id || current_user_can( 'publish_post', $pgw_id ) );
$pgw_cat_id  = 0;
$pgw_tags    = '';
$pgw_series  = '';
$pgw_cover   = 0;
if ( $pgw_post ) {
    $pgw_terms = wp_get_object_terms( $pgw_id, 'pg_article_category', array( 'fields' => 'ids' ) );
    $pgw_cat_id = ( ! is_wp_error( $pgw_terms ) && $pgw_terms ) ? (int) $pgw_terms[0] : 0;
    $pgw_t = wp_get_object_terms( $pgw_id, 'pg_article_tag', array( 'fields' => 'names' ) );
    $pgw_tags = is_wp_error( $pgw_t ) ? '' : implode( ', ', $pgw_t );
    $pgw_s = wp_get_object_terms( $pgw_id, 'pg_series', array( 'fields' => 'names' ) );
    $pgw_series = ( ! is_wp_error( $pgw_s ) && $pgw_s ) ? $pgw_s[0] : '';
    $pgw_cover = (int) get_post_thumbnail_id( $pgw_id );
}
$pgw_cats        = get_terms( array( 'taxonomy' => 'pg_article_category', 'hide_empty' => false ) );
$pgw_series_list = get_terms( array( 'taxonomy' => 'pg_series', 'hide_empty' => false, 'number' => 100 ) );
$pgw_cats        = is_wp_error( $pgw_cats ) ? array() : $pgw_cats;
$pgw_series_list = is_wp_error( $pgw_series_list ) ? array() : $pgw_series_list;
$pgw_title       = $pgw_post ? $pgw_post->post_title : '';
$pgw_body        = $pgw_post ? wp_kses_post( $pgw_post->post_content ) : '';
$pgw_excerpt     = $pgw_post ? $pgw_post->post_excerpt : '';

get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Articles</div>
    <h1><?php echo $pgw_post ? 'Edit article' : 'Write an article'; ?></h1>
    <p class="ph-excerpt"><?php echo $pgw_publish ? 'Write, preview and publish or schedule your article.' : 'Write and preview your article, then submit it for review. An editor publishes it.'; ?></p>
  </div>
</div>

<div class="pga-mine pgw">
  <?php if ( ! $pgw_can ) : ?>
    <p class="empty-note">Your account cannot write articles yet. <a href="<?php echo esc_url( prospergenics_articles_mine_url() ); ?>">Apply to write on My articles</a>: once the team approves, you can write here.</p>
  <?php else : ?>
    <?php if ( ! empty( $pgw_bad ) ) : ?>
      <p class="form-notice form-notice-error" role="alert">You cannot edit that article. You can start a new one below.</p>
    <?php endif; ?>
    <noscript><p class="form-notice form-notice-error">The editor needs JavaScript. Please turn it on, or <a href="<?php echo esc_url( admin_url( 'post-new.php?post_type=pg_article' ) ); ?>">use the standard editor</a>.</p></noscript>

    <form id="pgw-form" class="pgw-form" novalidate data-id="<?php echo esc_attr( $pgw_id ); ?>" data-status="<?php echo esc_attr( $pgw_status ); ?>" data-can-publish="<?php echo $pgw_publish ? '1' : '0'; ?>" data-mine="<?php echo esc_url( prospergenics_articles_mine_url() ); ?>">
      <p class="pgw-statusbar" aria-live="polite">
        <span class="pga-status pga-status-<?php echo esc_attr( $pgw_status ); ?>" id="pgw-status"><?php echo esc_html( $pgw_post ? prospergenics_articles_status_label( $pgw_status ) : 'New draft' ); ?></span>
        <span id="pgw-saved" class="pgw-saved">Not saved yet</span>
      </p>
      <p id="pgw-error" class="form-notice form-notice-error" role="alert" hidden></p>

      <div class="pgw-field">
        <label for="pgw-title">Title</label>
        <input type="text" id="pgw-title" name="title" maxlength="150" required value="<?php echo esc_attr( $pgw_title ); ?>" />
      </div>

      <div class="pgw-field">
        <span class="pgw-label" id="pgw-cover-label">Cover image</span>
        <div class="pgw-cover">
          <div id="pgw-cover-preview" class="pgw-cover-preview"<?php echo $pgw_cover ? '' : ' hidden'; ?>>
            <?php if ( $pgw_cover ) { echo wp_get_attachment_image( $pgw_cover, 'medium', false, array( 'alt' => '' ) ); } ?>
          </div>
          <input type="hidden" id="pgw-cover" name="cover" value="<?php echo esc_attr( $pgw_cover ); ?>" />
          <input type="file" id="pgw-cover-file" accept="image/jpeg,image/png,image/gif,image/webp" aria-labelledby="pgw-cover-label" aria-describedby="pgw-cover-help" />
          <button type="button" class="pga-btn pga-btn-ghost" id="pgw-cover-remove"<?php echo $pgw_cover ? '' : ' hidden'; ?>>Remove cover</button>
        </div>
        <p class="pgw-help" id="pgw-cover-help">JPG, PNG, GIF or WebP, up to 5 MB.</p>
      </div>

      <div class="pgw-field">
        <span class="pgw-label" id="pgw-body-label">Article</span>
        <div class="pgw-toolbar" role="toolbar" aria-label="Formatting" aria-controls="pgw-body">
          <button type="button" data-cmd="h2" title="Heading">H2</button>
          <button type="button" data-cmd="h3" title="Subheading">H3</button>
          <button type="button" data-cmd="p" title="Paragraph">¶</button>
          <button type="button" data-cmd="bold" title="Bold"><b>B</b></button>
          <button type="button" data-cmd="italic" title="Italic"><i>I</i></button>
          <button type="button" data-cmd="ul" title="Bulleted list">• List</button>
          <button type="button" data-cmd="ol" title="Numbered list">1. List</button>
          <button type="button" data-cmd="quote" title="Quote">“ ”</button>
          <button type="button" data-cmd="link" title="Link">Link</button>
          <button type="button" data-cmd="unlink" title="Remove link">Unlink</button>
          <button type="button" data-cmd="code" title="Inline code">&lt;/&gt;</button>
          <button type="button" data-cmd="pre" title="Code block">Code block</button>
          <button type="button" data-cmd="image" title="Insert image">Image</button>
          <input type="file" id="pgw-image-file" accept="image/jpeg,image/png,image/gif,image/webp" hidden />
        </div>
        <div id="pgw-body" class="pgw-body entry" contenteditable="true" role="textbox" aria-multiline="true" aria-labelledby="pgw-body-label"><?php echo $pgw_body; // sanitised with wp_kses_post above ?></div>
      </div>

      <div class="pgw-grid">
        <div class="pgw-field">
          <label for="pgw-category">Category</label>
          <select id="pgw-category" name="category">
            <option value="0">No category</option>
            <?php foreach ( $pgw_cats as $pgw_c ) : ?>
              <option value="<?php echo esc_attr( $pgw_c->term_id ); ?>"<?php selected( $pgw_cat_id, (int) $pgw_c->term_id ); ?>><?php echo esc_html( $pgw_c->name ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="pgw-field">
          <label for="pgw-series">Series <span class="pgw-opt">(optional)</span></label>
          <input type="text" id="pgw-series" name="series" maxlength="60" list="pgw-series-list" value="<?php echo esc_attr( $pgw_series ); ?>" />
          <datalist id="pgw-series-list">
            <?php foreach ( $pgw_series_list as $pgw_sr ) : ?>
              <option value="<?php echo esc_attr( $pgw_sr->name ); ?>"></option>
            <?php endforeach; ?>
          </datalist>
        </div>
      </div>

      <div class="pgw-field">
        <label for="pgw-tags">Tags <span class="pgw-opt">(separated by commas, up to 10)</span></label>
        <input type="text" id="pgw-tags" name="tags" maxlength="400" value="<?php echo esc_attr( $pgw_tags ); ?>" />
      </div>

      <div class="pgw-field">
        <label for="pgw-excerpt">Excerpt <span class="pgw-opt">(a short summary shown on cards and in search, up to 300 characters)</span></label>
        <textarea id="pgw-excerpt" name="excerpt" rows="3" maxlength="300"><?php echo esc_textarea( $pgw_excerpt ); ?></textarea>
      </div>

      <?php if ( $pgw_publish ) : ?>
        <div class="pgw-field">
          <label for="pgw-date">Schedule for <span class="pgw-opt">(leave empty to publish now)</span></label>
          <input type="datetime-local" id="pgw-date" name="date" />
        </div>
      <?php endif; ?>

      <div class="pgw-actions">
        <button type="button" class="pga-btn pga-btn-ghost" data-action="save">Save draft</button>
        <button type="button" class="pga-btn pga-btn-ghost" data-action="preview">Preview</button>
        <?php if ( $pgw_publish ) : ?>
          <button type="button" class="pga-btn" data-action="publish"><?php echo 'publish' === $pgw_status ? 'Update article' : 'Publish'; ?></button>
        <?php else : ?>
          <button type="button" class="pga-btn" data-action="submit"<?php echo in_array( $pgw_status, array( 'pending', 'publish', 'future' ), true ) ? ' hidden' : ''; ?>>Submit for review</button>
        <?php endif; ?>
        <a class="pga-btn pga-btn-ghost" href="<?php echo esc_url( prospergenics_articles_mine_url() ); ?>">My articles</a>
      </div>
    </form>
  <?php endif; ?>
</div>
<?php
get_footer();

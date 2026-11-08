<?php
/**
 * View: /manage/messages/ (card #6457), the contact messages (pg_contact_msg) for editors and
 * administrators: Open / Unread / Handled / Spam, and one message at ?message=<id>.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification -- read-only views; the "Open" link's nonce is checked in prospergenics_manage_mark_read_from_link()
$pgm_views = prospergenics_manage_message_views();
$pgm_view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'open';
$pgm_view  = isset( $pgm_views[ $pgm_view ] ) ? $pgm_view : 'open';
$pgm_paged = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
$pgm_one   = isset( $_GET['message'] ) ? prospergenics_manage_message( absint( $_GET['message'] ) ) : null;
// phpcs:enable
if ( $pgm_one ) {
    prospergenics_manage_mark_read_from_link( $pgm_one );
}

/** The meta of a message the screens show. */
$pgm_meta = function ( WP_Post $p ) {
    return array(
        'name'    => (string) get_post_meta( $p->ID, '_pg_name', true ),
        'email'   => (string) get_post_meta( $p->ID, '_pg_email', true ),
        'phone'   => (string) get_post_meta( $p->ID, '_pg_phone', true ),
        'mail'    => (string) get_post_meta( $p->ID, '_pg_mail_status', true ),
        'spam'    => '1' === (string) get_post_meta( $p->ID, '_pg_is_spam', true ),
        'reasons' => (string) get_post_meta( $p->ID, '_pg_spam_reasons', true ),
        'state'   => prospergenics_manage_message_state( (int) get_post_meta( $p->ID, '_pg_contact_read_at', true ), (int) get_post_meta( $p->ID, '_pg_contact_handled_at', true ) ),
    );
};
$pgm_state_labels = array( 'unread' => 'Unread', 'read' => 'Read', 'handled' => 'Handled' );

/** A message action button (nonce per action and message). */
$pgm_button = function ( $do, WP_Post $p, $back, $class = 'pgm-btn pgm-btn-ghost' ) {
    $label = prospergenics_manage_message_actions()[ $do ];
    $html  = '<form class="pgm-inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    $html .= '<input type="hidden" name="action" value="pg_manage_message" /><input type="hidden" name="do" value="' . esc_attr( $do ) . '" />';
    $html .= '<input type="hidden" name="message" value="' . (int) $p->ID . '" /><input type="hidden" name="pg_back" value="' . esc_url( $back ) . '" />';
    $html .= wp_nonce_field( prospergenics_manage_message_nonce( $do, $p->ID ), '_wpnonce', false, false );
    $html .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '<span class="screen-reader-text"> “' . esc_html( get_the_title( $p ) ) . '”</span></button></form>';
    return $html;
};

prospergenics_manage_open( 'messages', 'What people sent through the contact form. Reply by email, then mark the message handled.' );

if ( $pgm_one ) :
    $pgm_m     = $pgm_meta( $pgm_one );
    $pgm_back  = prospergenics_manage_url( 'messages', array( 'message' => $pgm_one->ID ) );
    $pgm_title = wp_strip_all_tags( get_the_title( $pgm_one ) );
    ?>
  <p><a class="post-back" href="<?php echo esc_url( prospergenics_manage_url( 'messages' ) ); ?>">← All messages</a></p>
  <article class="pgm-message" aria-labelledby="pgm-message-h">
    <p class="pgm-badges">
      <span class="pgm-badge<?php echo 'unread' === $pgm_m['state'] ? ' pgm-badge-warn' : ( 'handled' === $pgm_m['state'] ? ' pgm-badge-ok' : '' ); ?>"><?php echo esc_html( $pgm_state_labels[ $pgm_m['state'] ] ); ?></span>
      <?php if ( $pgm_m['spam'] ) : ?><span class="pgm-badge pgm-badge-bad">Spam<?php echo '' !== $pgm_m['reasons'] ? ': ' . esc_html( $pgm_m['reasons'] ) : ''; ?></span><?php endif; ?>
      <?php if ( 'failed' === $pgm_m['mail'] ) : ?><span class="pgm-badge pgm-badge-bad">Notification email failed</span><?php endif; ?>
    </p>
    <h2 class="pgm-item-title" id="pgm-message-h"><?php echo esc_html( $pgm_title ); ?></h2>
    <dl class="pgm-dl">
      <dt>From</dt><dd><?php echo esc_html( '' !== $pgm_m['name'] ? $pgm_m['name'] : '–' ); ?></dd>
      <dt>Email</dt><dd><?php if ( '' !== $pgm_m['email'] ) : ?><a href="<?php echo esc_url( 'mailto:' . $pgm_m['email'] ); ?>"><?php echo esc_html( $pgm_m['email'] ); ?></a><?php else : ?>–<?php endif; ?></dd>
      <?php if ( '' !== $pgm_m['phone'] ) : ?><dt>Phone</dt><dd><?php echo esc_html( $pgm_m['phone'] ); ?></dd><?php endif; ?>
      <dt>Received</dt><dd><time datetime="<?php echo esc_attr( get_post_time( 'c', true, $pgm_one ) ); ?>"><?php echo esc_html( get_post_time( 'j M Y, H:i', false, $pgm_one ) ); ?></time></dd>
    </dl>
    <div class="pgm-message-body"><?php echo wpautop( esc_html( $pgm_one->post_content ) ); // escaped, then paragraphs ?></div>
    <div class="pgm-item-actions">
      <?php if ( '' !== $pgm_m['email'] ) : ?>
        <a class="pgm-btn" href="<?php echo esc_url( prospergenics_manage_reply_url( $pgm_m['email'], $pgm_title ) ); ?>">Reply by email</a>
      <?php endif; ?>
      <?php
      echo $pgm_button( 'handled' === $pgm_m['state'] ? 'reopen' : 'handled', $pgm_one, $pgm_back ); // escaped inside
      echo $pgm_button( 'unread' === $pgm_m['state'] ? 'read' : 'unread', $pgm_one, $pgm_back ); // escaped inside
      ?>
    </div>
  </article>
    <?php
else :
    $pgm_query = new WP_Query( prospergenics_manage_message_query_args( $pgm_view, $pgm_paged ) );
    $pgm_list  = prospergenics_manage_url( 'messages', 'open' === $pgm_view ? array() : array( 'view' => $pgm_view ) );
    $pgm_back  = $pgm_paged > 1 ? add_query_arg( 'pg', $pgm_paged, $pgm_list ) : $pgm_list;
    ?>
  <nav class="pgm-chips" aria-label="Messages">
    <?php foreach ( $pgm_views as $pgm_key => $pgm_label ) : ?>
      <a class="pgm-chip" href="<?php echo esc_url( prospergenics_manage_url( 'messages', 'open' === $pgm_key ? array() : array( 'view' => $pgm_key ) ) ); ?>"<?php echo $pgm_key === $pgm_view ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $pgm_label ); ?><?php if ( 'unread' === $pgm_key ) : ?> <span class="pgm-chip-count">(<?php echo esc_html( number_format_i18n( prospergenics_manage_unread_message_count() ) ); ?>)</span><?php endif; ?></a>
    <?php endforeach; ?>
  </nav>

    <?php if ( $pgm_query->have_posts() ) : ?>
    <ul class="pgm-list">
      <?php foreach ( $pgm_query->posts as $pgm_p ) :
          $pgm_m     = $pgm_meta( $pgm_p );
          $pgm_title = wp_strip_all_tags( get_the_title( $pgm_p ) );
          $pgm_open  = wp_nonce_url( prospergenics_manage_url( 'messages', array( 'message' => $pgm_p->ID ) ), prospergenics_manage_message_nonce( 'read', $pgm_p->ID ) );
          ?>
        <li class="pgm-item<?php echo 'unread' === $pgm_m['state'] ? ' pgm-item-unread' : ''; ?>">
          <div class="pgm-item-main">
            <p class="pgm-badges">
              <span class="pgm-badge<?php echo 'unread' === $pgm_m['state'] ? ' pgm-badge-warn' : ( 'handled' === $pgm_m['state'] ? ' pgm-badge-ok' : '' ); ?>"><?php echo esc_html( $pgm_state_labels[ $pgm_m['state'] ] ); ?></span>
              <?php if ( 'failed' === $pgm_m['mail'] ) : ?><span class="pgm-badge pgm-badge-bad">Notification email failed</span><?php endif; ?>
            </p>
            <h2 class="pgm-item-title"><a href="<?php echo esc_url( $pgm_open ); ?>"><?php echo esc_html( $pgm_title ); ?></a></h2>
            <p class="pgm-facts">
              <?php echo esc_html( '' !== $pgm_m['name'] ? $pgm_m['name'] : 'Someone' ); ?>
              <?php if ( '' !== $pgm_m['email'] ) : ?><span aria-hidden="true">·</span> <?php echo esc_html( $pgm_m['email'] ); ?><?php endif; ?>
              <span aria-hidden="true">·</span> <time datetime="<?php echo esc_attr( get_post_time( 'c', true, $pgm_p ) ); ?>"><?php echo esc_html( get_post_time( 'j M Y, H:i', false, $pgm_p ) ); ?></time>
            </p>
            <p class="pgm-excerpt"><?php echo esc_html( wp_trim_words( wp_strip_all_tags( $pgm_p->post_content ), 30, '…' ) ); ?></p>
          </div>
          <div class="pgm-item-actions">
            <?php if ( '' !== $pgm_m['email'] ) : ?>
              <a class="pgm-btn pgm-btn-ghost" href="<?php echo esc_url( prospergenics_manage_reply_url( $pgm_m['email'], $pgm_title ) ); ?>">Reply<span class="screen-reader-text"> to “<?php echo esc_html( $pgm_title ); ?>”</span></a>
            <?php endif; ?>
            <?php echo $pgm_button( 'handled' === $pgm_m['state'] ? 'reopen' : 'handled', $pgm_p, $pgm_back ); // escaped inside ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
        <?php echo prospergenics_manage_pagination( $pgm_list, $pgm_paged, (int) $pgm_query->max_num_pages ); // escaped by paginate_links ?>
    <?php else : ?>
    <p class="empty-note"><?php echo esc_html( array( 'open' => 'No open messages. Everything is handled.', 'unread' => 'No unread messages.', 'handled' => 'No handled messages yet.', 'spam' => 'No messages were flagged as spam.' )[ $pgm_view ] ); ?></p>
    <?php endif; ?>
<?php endif; ?>
<?php
prospergenics_manage_close();

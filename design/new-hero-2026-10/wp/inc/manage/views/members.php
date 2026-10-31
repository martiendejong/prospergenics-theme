<?php
/**
 * View: /manage/members/ (card #6457), loaded by inc/manage/routes.php for staff. Search, filters,
 * paging, and the member actions (inc/manage/actions.php) for the members this user may change.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification -- a search form (GET): read-only
$pgm_search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$pgm_search  = function_exists( 'mb_substr' ) ? mb_substr( $pgm_search, 0, 100 ) : substr( $pgm_search, 0, 100 );
$pgm_filters = prospergenics_manage_member_filters();
$pgm_filter  = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all';
$pgm_filter  = isset( $pgm_filters[ $pgm_filter ] ) ? $pgm_filter : 'all';
$pgm_paged   = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
// the badge filter (inc/profiles/rank.php stores each member's tier)
$pgm_tiers   = prospergenics_profiles_badge_tiers();
$pgm_tier    = prospergenics_manage_clean_tier( isset( $_GET['tier'] ) ? sanitize_key( wp_unslash( $_GET['tier'] ) ) : '', array_keys( $pgm_tiers ) );
// phpcs:enable

if ( '' !== $pgm_tier ) {
    prospergenics_profiles_rank_backfill( 100 ); // members whose tier was never stored, so the filter finds them
}
$pgm_query = new WP_User_Query( prospergenics_manage_member_tier_args( prospergenics_manage_member_query_args( $pgm_search, $pgm_filter, $pgm_paged ), $pgm_tier ) );
$pgm_total = (int) $pgm_query->get_total();
$pgm_pages = (int) ceil( $pgm_total / PROSPERGENICS_MANAGE_PER_PAGE );
$pgm_list  = prospergenics_manage_url( 'members', array_filter( array( 's' => $pgm_search, 'filter' => 'all' === $pgm_filter ? '' : $pgm_filter, 'tier' => $pgm_tier ) ) );
$pgm_back  = $pgm_paged > 1 ? add_query_arg( 'pg', $pgm_paged, $pgm_list ) : $pgm_list;
$pgm_names = function_exists( 'wp_roles' ) ? wp_roles()->role_names : array();
$pgm_me    = get_current_user_id();

/** One action button in its own form (nonce per action and member). */
$pgm_button = function ( $do, WP_User $u, $label, $class = 'pgm-btn pgm-btn-ghost', $inner = '' ) use ( $pgm_back ) {
    $html  = '<form class="pgm-inline" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    $html .= '<input type="hidden" name="action" value="pg_manage_member" /><input type="hidden" name="do" value="' . esc_attr( $do ) . '" />';
    $html .= '<input type="hidden" name="user_id" value="' . (int) $u->ID . '" /><input type="hidden" name="pg_back" value="' . esc_url( $pgm_back ) . '" />';
    $html .= wp_nonce_field( prospergenics_manage_member_nonce( $do, $u->ID ), '_wpnonce', false, false );
    $html .= $inner;
    $html .= '<button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '<span class="screen-reader-text"> ' . esc_html( $u->display_name ) . '</span></button></form>';
    return $html;
};

prospergenics_manage_open( 'members', 'Find a member and change their role, confirm their email address, hide their profile or suspend them.' );
?>
  <form class="pgm-search" method="get" action="<?php echo esc_url( prospergenics_manage_url( 'members' ) ); ?>" role="search" aria-label="Members">
    <p class="field pgm-search-q">
      <label for="pgm-s">Name or email</label>
      <input type="search" id="pgm-s" name="s" value="<?php echo esc_attr( $pgm_search ); ?>" maxlength="100" />
    </p>
    <p class="field">
      <label for="pgm-filter">Show</label>
      <select id="pgm-filter" name="filter">
        <?php foreach ( $pgm_filters as $pgm_key => $pgm_label ) : ?>
          <option value="<?php echo esc_attr( $pgm_key ); ?>"<?php selected( $pgm_key, $pgm_filter ); ?>><?php echo esc_html( $pgm_label ); ?></option>
        <?php endforeach; ?>
      </select>
    </p>
    <p class="field">
      <label for="pgm-tier">Badge</label>
      <select id="pgm-tier" name="tier">
        <option value="">Any badge</option>
        <?php foreach ( $pgm_tiers as $pgm_key => $pgm_t ) : ?>
          <option value="<?php echo esc_attr( $pgm_key ); ?>"<?php selected( $pgm_key, $pgm_tier ); ?>><?php echo esc_html( $pgm_t['label'] ); ?></option>
        <?php endforeach; ?>
        <option value="none"<?php selected( 'none', $pgm_tier ); ?>>No badge</option>
      </select>
    </p>
    <p class="pgm-search-go"><button type="submit" class="pgm-btn">Search</button></p>
  </form>

  <p class="pgm-count" role="status"><?php
    echo esc_html( sprintf( _n( '%s member', '%s members', $pgm_total ), number_format_i18n( $pgm_total ) ) . ( '' !== $pgm_search ? sprintf( ' matching “%s”', $pgm_search ) : '' ) . ( 'all' !== $pgm_filter ? ' (' . strtolower( $pgm_filters[ $pgm_filter ] ) . ')' : '' )
        . ( '' !== $pgm_tier ? ( 'none' === $pgm_tier ? ' without a badge' : sprintf( ' with the %s badge', $pgm_tiers[ $pgm_tier ]['label'] ) ) : '' ) );
    if ( '' !== $pgm_search || 'all' !== $pgm_filter || '' !== $pgm_tier ) {
        echo ' <a href="' . esc_url( prospergenics_manage_url( 'members' ) ) . '">Show everyone</a>';
    }
  ?></p>

  <?php if ( $pgm_total ) :
      prospergenics_profiles_badges_prime( wp_list_pluck( $pgm_query->get_results(), 'ID' ) ); // the badges: one read for the page ?>
    <ul class="pgm-list pgm-list-members">
      <?php foreach ( $pgm_query->get_results() as $pgm_u ) :
          $pgm_uid       = (int) $pgm_u->ID;
          $pgm_can       = prospergenics_manage_can_manage_member( $pgm_uid );
          $pgm_verified  = prospergenics_profiles_is_verified( $pgm_uid );
          $pgm_hidden    = prospergenics_profiles_is_hidden( $pgm_uid );
          $pgm_suspended = prospergenics_profiles_is_suspended( $pgm_uid );
          $pgm_teaches   = (bool) get_user_meta( $pgm_uid, PROSPERGENICS_COURSES_INSTRUCTOR_META, true );
          $pgm_roles     = array_map( function ( $r ) use ( $pgm_names ) { return isset( $pgm_names[ $r ] ) ? translate_user_role( $pgm_names[ $r ] ) : $r; }, (array) $pgm_u->roles );
          $pgm_mail      = $pgm_verified ? null : prospergenics_profiles_mail_status( $pgm_uid, 'verify' );
          $pgm_rank      = prospergenics_profiles_rank( $pgm_uid ); // staff see the score too, never the breakdown
          $pgm_badge     = prospergenics_profiles_badge_html( $pgm_uid );
          ?>
        <li class="pgm-item" id="pgm-member-<?php echo (int) $pgm_uid; ?>">
          <div class="pgm-item-main">
            <h2 class="pgm-item-title"><?php echo esc_html( $pgm_u->display_name ); ?></h2>
            <p class="pgm-facts">
              <a href="<?php echo esc_url( 'mailto:' . $pgm_u->user_email ); ?>"><?php echo esc_html( $pgm_u->user_email ); ?></a>
              <span aria-hidden="true">·</span> <?php echo esc_html( $pgm_roles ? implode( ', ', $pgm_roles ) : 'No role' ); ?>
              <span aria-hidden="true">·</span> Joined <time datetime="<?php echo esc_attr( mysql2date( 'c', $pgm_u->user_registered ) ); ?>"><?php echo esc_html( mysql2date( 'j M Y', $pgm_u->user_registered ) ); ?></time>
            </p>
            <p class="pgm-facts pgm-rank">
              <span class="pgm-rank-h">Badge:</span>
              <?php echo '' !== $pgm_badge ? $pgm_badge : '<span class="pgm-muted">none</span>'; // escaped in the helper ?>
              <span aria-hidden="true">·</span>
              <?php echo $pgm_rank ? esc_html( sprintf( _n( '%d point', '%d points', (int) $pgm_rank['score'] ), (int) $pgm_rank['score'] ) ) : '<span class="pgm-muted">score not computed yet</span>'; ?>
            </p>
            <p class="pgm-badges">
              <?php if ( $pgm_verified ) : ?>
                <span class="pgm-badge pgm-badge-ok">Verified</span>
              <?php else : ?>
                <span class="pgm-badge pgm-badge-warn">Not verified<?php echo $pgm_mail && 'failed' === $pgm_mail['status'] ? ': last link not sent' : ''; ?></span>
              <?php endif; ?>
              <?php if ( $pgm_teaches ) : ?><span class="pgm-badge">Instructor</span><?php endif; ?>
              <?php if ( $pgm_hidden ) : ?><span class="pgm-badge pgm-badge-warn">Profile hidden</span><?php endif; ?>
              <?php if ( $pgm_suspended ) : ?><span class="pgm-badge pgm-badge-bad">Suspended</span><?php endif; ?>
            </p>
          </div>
          <div class="pgm-item-actions">
            <a class="pgm-btn pgm-btn-ghost" href="<?php echo esc_url( prospergenics_profiles_url( $pgm_uid ) ); ?>">View profile<span class="screen-reader-text"> <?php echo esc_html( $pgm_u->display_name ); ?></span></a>
            <?php if ( ! $pgm_can ) : ?>
              <p class="pgm-muted pgm-item-note"><?php echo esc_html( $pgm_uid === $pgm_me ? 'This is you: change your own account in Settings.' : 'Staff account: only administrators change it, in wp-admin.' ); ?></p>
            <?php else :
                $pgm_promote = current_user_can( 'promote_user', $pgm_uid );
                $pgm_choices = prospergenics_manage_role_choices( $pgm_promote );
                $pgm_current = (array) $pgm_u->roles;
                if ( prospergenics_manage_role_change_allowed( $pgm_current, 'subscriber', $pgm_promote ) ) :
                    $pgm_select  = '<label class="screen-reader-text" for="pgm-role-' . (int) $pgm_uid . '">Role of ' . esc_html( $pgm_u->display_name ) . '</label>';
                    $pgm_select .= '<select class="pgm-select" id="pgm-role-' . (int) $pgm_uid . '" name="role">';
                    foreach ( $pgm_choices as $pgm_role => $pgm_role_label ) {
                        $pgm_select .= '<option value="' . esc_attr( $pgm_role ) . '"' . selected( in_array( $pgm_role, $pgm_current, true ), true, false ) . '>' . esc_html( $pgm_role_label ) . '</option>';
                    }
                    $pgm_select .= '</select>';
                    echo $pgm_button( 'role', $pgm_u, 'Change role', 'pgm-btn pgm-btn-ghost', $pgm_select ); // escaped inside
                endif;
                if ( ! $pgm_verified ) {
                    echo $pgm_button( 'verify', $pgm_u, 'Mark verified' ); // escaped inside
                    echo $pgm_button( 'resend', $pgm_u, 'Resend confirmation link' ); // escaped inside
                }
                echo $pgm_button( $pgm_hidden ? 'unhide' : 'hide', $pgm_u, $pgm_hidden ? 'Show profile' : 'Hide profile' ); // escaped inside
                if ( current_user_can( PROSPERGENICS_MODERATION_CAP ) ) {
                    if ( $pgm_suspended ) {
                        echo $pgm_button( 'unsuspend', $pgm_u, 'Unsuspend' ); // escaped inside
                    } elseif ( prospergenics_profiles_can_be_suspended( $pgm_uid, $pgm_me ) ) {
                        $pgm_note = '<p class="field"><label for="pgm-note-' . (int) $pgm_uid . '">Reason (emailed to the member)</label><textarea id="pgm-note-' . (int) $pgm_uid . '" name="note" rows="2" maxlength="2000"></textarea></p>';
                        echo '<details class="pgm-suspend"><summary class="pgm-btn pgm-btn-ghost">Suspend…<span class="screen-reader-text"> ' . esc_html( $pgm_u->display_name ) . '</span></summary>';
                        echo $pgm_button( 'suspend', $pgm_u, 'Suspend now', 'pgm-btn pgm-btn-danger', $pgm_note ); // escaped inside
                        echo '<p class="pgm-muted">They cannot log in until you unsuspend them, and their profile is hidden.</p></details>';
                    }
                }
            endif; ?>
          </div>
        </li>
      <?php endforeach; ?>
    </ul>
    <?php echo prospergenics_manage_pagination( $pgm_list, $pgm_paged, $pgm_pages ); // escaped by paginate_links ?>
  <?php else : ?>
    <p class="empty-note">No members found. Try another name or email, or show everyone.</p>
  <?php endif; ?>
<?php
prospergenics_manage_close();

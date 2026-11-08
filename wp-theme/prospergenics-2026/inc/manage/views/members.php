<?php
/**
 * View: /manage/members/ (card #6457), loaded by inc/manage/routes.php for staff. Search, filters,
 * paging, and the member actions (inc/manage/actions.php) for the members this user may change.
 *
 * A compact table (one row per member; on phones one small card each) so hundreds of members stay
 * easy to scan: summary chips with counts, search on name, username or email, sort, 25/50/100 per
 * page. Everything is a GET parameter, so every list is a shareable link and works without
 * JavaScript; assets/platform/manage.js only folds each member's actions into a "Manage" button
 * and submits the search after typing stops. The actions return here with the same parameters.
 */

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.Security.NonceVerification -- a search form (GET): read-only
$pgm_search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
$pgm_search  = function_exists( 'mb_substr' ) ? mb_substr( $pgm_search, 0, 100 ) : substr( $pgm_search, 0, 100 );
$pgm_filters = prospergenics_manage_member_filters();
$pgm_filter  = isset( $_GET['filter'] ) ? sanitize_key( wp_unslash( $_GET['filter'] ) ) : 'all';
$pgm_filter  = isset( $pgm_filters[ $pgm_filter ] ) ? $pgm_filter : 'all';
$pgm_paged   = isset( $_GET['pg'] ) ? max( 1, absint( $_GET['pg'] ) ) : 1;
$pgm_sorts   = prospergenics_manage_member_sorts();
$pgm_sort    = isset( $_GET['sort'] ) ? sanitize_key( wp_unslash( $_GET['sort'] ) ) : 'newest';
$pgm_sort    = isset( $pgm_sorts[ $pgm_sort ] ) ? $pgm_sort : 'newest';
$pgm_per     = prospergenics_manage_clean_per_page( isset( $_GET['per'] ) ? absint( $_GET['per'] ) : 0 );
// the badge filter (inc/profiles/rank.php stores each member's tier)
$pgm_tiers   = prospergenics_profiles_badge_tiers();
$pgm_tier    = prospergenics_manage_clean_tier( isset( $_GET['tier'] ) ? sanitize_key( wp_unslash( $_GET['tier'] ) ) : '', array_keys( $pgm_tiers ) );
// phpcs:enable

if ( '' !== $pgm_tier ) {
    prospergenics_profiles_rank_backfill( 100 ); // members whose tier was never stored, so the filter finds them
}
$pgm_args  = function ( $paged ) use ( $pgm_search, $pgm_filter, $pgm_per, $pgm_sort, $pgm_tier ) {
    return prospergenics_manage_member_tier_args( prospergenics_manage_member_query_args( $pgm_search, $pgm_filter, $paged, $pgm_per, $pgm_sort ), $pgm_tier );
};
$pgm_query = new WP_User_Query( $pgm_args( $pgm_paged ) );
$pgm_total = (int) $pgm_query->get_total();
$pgm_pages = (int) ceil( $pgm_total / $pgm_per );
if ( $pgm_pages && $pgm_paged > $pgm_pages ) { // e.g. after unsuspending the last member of the last page
    $pgm_paged = $pgm_pages;
    $pgm_query = new WP_User_Query( $pgm_args( $pgm_paged ) );
}
$pgm_state = array( 's' => $pgm_search, 'filter' => $pgm_filter, 'tier' => $pgm_tier, 'sort' => $pgm_sort, 'per' => $pgm_per );
$pgm_list  = prospergenics_manage_url( 'members', prospergenics_manage_member_list_args( $pgm_state ) );
$pgm_back  = prospergenics_manage_url( 'members', prospergenics_manage_member_list_args( $pgm_state + array( 'pg' => $pgm_paged ) ) );
$pgm_names = function_exists( 'wp_roles' ) ? wp_roles()->role_names : array();
$pgm_me    = get_current_user_id();
$pgm_range = prospergenics_manage_page_range( $pgm_paged, $pgm_per, $pgm_total );
$pgm_chips = prospergenics_manage_member_chips();
$pgm_count = prospergenics_manage_member_chip_counts();
// the member an action just changed (actions.php redirects back with ?done=…&member=…)
// phpcs:disable WordPress.Security.NonceVerification -- display only, whitelisted values
$pgm_done  = isset( $_GET['done'], $_GET['member'] ) ? absint( $_GET['member'] ) : 0;
$pgm_dones = array( 'role' => 'Role changed', 'verified' => 'Marked verified', 'already' => 'Already verified', 'sent' => 'Link sent', 'hidden' => 'Profile hidden', 'shown' => 'Profile visible', 'suspended' => 'Suspended', 'unsuspended' => 'Suspension lifted' );
$pgm_dkey  = $pgm_done ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
$pgm_dtext = isset( $pgm_dones[ $pgm_dkey ] ) ? $pgm_dones[ $pgm_dkey ] : '';
// phpcs:enable

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

/** A select of the search form; data-default lets manage.js leave defaults out of the URL. */
$pgm_select = function ( $id, $name, $label, array $options, $current, $default ) {
    $html = '<p class="field pgm-f-' . esc_attr( $name ) . '"><label for="' . esc_attr( $id ) . '">' . esc_html( $label ) . '</label><select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-default="' . esc_attr( $default ) . '">';
    foreach ( $options as $value => $text ) {
        $html .= '<option value="' . esc_attr( $value ) . '"' . selected( (string) $value, (string) $current, false ) . '>' . esc_html( $text ) . '</option>';
    }
    return $html . '</select></p>';
};

prospergenics_manage_open( 'members', 'Find a member and change their role, confirm their email address, hide their profile or suspend them.' );
?>
  <nav class="pgm-chips" aria-label="Member groups">
    <?php foreach ( $pgm_chips as $pgm_key => $pgm_label ) : ?>
      <a class="pgm-chip" href="<?php echo esc_url( prospergenics_manage_url( 'members', prospergenics_manage_member_list_args( array( 'filter' => $pgm_key, 'sort' => $pgm_sort, 'per' => $pgm_per ) ) ) ); ?>"<?php echo $pgm_key === $pgm_filter ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $pgm_label ); ?> <span class="pgm-chip-count"><?php echo esc_html( number_format_i18n( $pgm_count[ $pgm_key ] ) ); ?></span></a>
    <?php endforeach; ?>
  </nav>

  <form class="pgm-search" method="get" action="<?php echo esc_url( prospergenics_manage_url( 'members' ) ); ?>" role="search" aria-label="Members">
    <p class="field pgm-search-q">
      <label for="pgm-s">Name, username or email</label>
      <input type="search" id="pgm-s" name="s" value="<?php echo esc_attr( $pgm_search ); ?>" maxlength="100" autocomplete="off" data-default="" />
    </p>
    <?php
    $pgm_tier_options = array( '' => 'Any badge' ) + wp_list_pluck( $pgm_tiers, 'label' ) + array( 'none' => 'No badge' );
    echo $pgm_select( 'pgm-filter', 'filter', 'Show', $pgm_filters, $pgm_filter, 'all' ); // escaped inside
    echo $pgm_select( 'pgm-tier', 'tier', 'Badge', $pgm_tier_options, $pgm_tier, '' ); // escaped inside
    echo $pgm_select( 'pgm-sort', 'sort', 'Sort', wp_list_pluck( $pgm_sorts, 0 ), $pgm_sort, 'newest' ); // escaped inside
    echo $pgm_select( 'pgm-per', 'per', 'Per page', array_combine( prospergenics_manage_member_per_page_choices(), prospergenics_manage_member_per_page_choices() ), $pgm_per, PROSPERGENICS_MANAGE_MEMBERS_PER_PAGE ); // escaped inside
    ?>
    <p class="pgm-search-go"><button type="submit" class="pgm-btn">Search</button></p>
  </form>

  <div class="pgm-summary">
    <p class="pgm-count" role="status"><?php
      if ( '' !== $pgm_search ) {
          echo esc_html( sprintf( _n( '%1$s result for “%2$s”', '%1$s results for “%2$s”', $pgm_total ), number_format_i18n( $pgm_total ), $pgm_search ) );
      } else {
          echo esc_html( sprintf( _n( '%s member', '%s members', $pgm_total ), number_format_i18n( $pgm_total ) ) );
      }
      echo esc_html( ( 'all' !== $pgm_filter ? ' · ' . $pgm_filters[ $pgm_filter ] : '' )
          . ( '' !== $pgm_tier ? ( 'none' === $pgm_tier ? ' · no badge' : sprintf( ' · %s badge', $pgm_tiers[ $pgm_tier ]['label'] ) ) : '' ) );
      if ( '' !== $pgm_search ) {
          echo ' <a class="pgm-clear" href="' . esc_url( prospergenics_manage_url( 'members', prospergenics_manage_member_list_args( array( 's' => '' ) + $pgm_state ) ) ) . '">Clear search<span class="screen-reader-text"> “' . esc_html( $pgm_search ) . '”</span></a>';
      }
      if ( 'all' !== $pgm_filter || '' !== $pgm_tier ) {
          echo ' <a href="' . esc_url( prospergenics_manage_url( 'members' ) ) . '">Show everyone</a>';
      }
    ?></p>
    <?php if ( $pgm_total ) : ?>
      <p class="pgm-range"><?php echo esc_html( sprintf( 'Showing %s–%s of %s', number_format_i18n( $pgm_range[0] ), number_format_i18n( $pgm_range[1] ), number_format_i18n( $pgm_total ) ) ); ?></p>
    <?php endif; ?>
  </div>

  <?php if ( $pgm_total ) :
      $pgm_users = $pgm_query->get_results();
      prospergenics_profiles_badges_prime( wp_list_pluck( $pgm_users, 'ID' ) ); // the badges and the meta below: one read for the page ?>
    <div class="pgm-mtable-wrap">
    <table class="pgm-mtable pgm-list-members">
      <caption class="screen-reader-text"><?php echo esc_html( sprintf( 'Members, %s. Page %d of %d.', strtolower( $pgm_sorts[ $pgm_sort ][0] ), $pgm_paged, max( 1, $pgm_pages ) ) ); ?></caption>
      <thead>
        <tr>
          <th scope="col">Member</th>
          <th scope="col" class="pgm-c-role">Role</th>
          <th scope="col" class="pgm-c-badge">Badge</th>
          <th scope="col" class="pgm-c-status">Status</th>
          <th scope="col" class="pgm-c-joined">Joined</th>
          <th scope="col" class="pgm-c-actions"><span class="screen-reader-text">Actions</span></th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ( $pgm_users as $pgm_u ) :
          $pgm_uid       = (int) $pgm_u->ID;
          $pgm_can       = prospergenics_manage_can_manage_member( $pgm_uid );
          $pgm_verified  = prospergenics_profiles_is_verified( $pgm_uid );
          $pgm_hidden    = prospergenics_profiles_is_hidden( $pgm_uid );
          $pgm_suspended = prospergenics_profiles_is_suspended( $pgm_uid );
          $pgm_teaches   = (bool) get_user_meta( $pgm_uid, PROSPERGENICS_COURSES_INSTRUCTOR_META, true );
          $pgm_roles     = array_map( function ( $r ) use ( $pgm_names ) { return isset( $pgm_names[ $r ] ) ? translate_user_role( $pgm_names[ $r ] ) : $r; }, (array) $pgm_u->roles );
          $pgm_role_text = $pgm_roles ? implode( ', ', $pgm_roles ) : 'No role';
          $pgm_mail      = $pgm_verified ? null : prospergenics_profiles_mail_status( $pgm_uid, 'verify' );
          $pgm_rank      = prospergenics_profiles_rank( $pgm_uid ); // staff see the score too, never the breakdown
          $pgm_badge     = prospergenics_profiles_badge_html( $pgm_uid );
          $pgm_photo     = prospergenics_profiles_photo_url( $pgm_uid, 'thumbnail' );
          $pgm_joined    = '<time datetime="' . esc_attr( mysql2date( 'c', $pgm_u->user_registered ) ) . '">' . esc_html( mysql2date( 'j M Y', $pgm_u->user_registered ) ) . '</time>';
          $pgm_points    = $pgm_rank ? esc_html( sprintf( _n( '%d point', '%d points', (int) $pgm_rank['score'] ), (int) $pgm_rank['score'] ) ) : '<span class="pgm-muted">score not computed yet</span>';
          // the status pills, shown in their column on wide screens and next to the name on phones
          $pgm_flags = $pgm_verified
              ? '<span class="pgm-badge pgm-badge-ok">Verified</span>'
              : '<span class="pgm-badge pgm-badge-warn">Not verified' . ( $pgm_mail && 'failed' === $pgm_mail['status'] ? ': last link not sent' : '' ) . '</span>';
          $pgm_more   = ( $pgm_hidden ? ' <span class="pgm-badge pgm-badge-warn">Profile hidden</span>' : '' ) . ( $pgm_suspended ? ' <span class="pgm-badge pgm-badge-bad">Suspended</span>' : '' );
          $pgm_short  = ( $pgm_verified ? '<span class="pgm-badge pgm-badge-ok">Verified</span>' : '<span class="pgm-badge pgm-badge-warn">Not verified</span>' ) . str_replace( 'Profile hidden', 'Hidden', $pgm_more ); // phones
          $pgm_flags .= $pgm_more;
          $pgm_panel = 'pgm-panel-' . $pgm_uid;
          ?>
        <tr class="pgm-row<?php echo $pgm_done === $pgm_uid ? ' pgm-row-done' : ''; ?>" id="pgm-member-<?php echo (int) $pgm_uid; ?>">
          <th scope="row" class="pgm-who">
            <div class="pgm-who-in">
            <?php if ( '' !== $pgm_photo ) : ?>
              <img class="pgm-avatar" src="<?php echo esc_url( $pgm_photo ); ?>" alt="" width="36" height="36" loading="lazy" decoding="async" />
            <?php else : ?>
              <span class="pgm-avatar" aria-hidden="true"><?php echo esc_html( prospergenics_profiles_initials( $pgm_u->display_name ) ); ?></span>
            <?php endif; ?>
            <span class="pgm-who-text">
              <span class="pgm-who-line"><span class="pgm-name"><?php echo esc_html( $pgm_u->display_name ); ?></span>
                <span class="pgm-m-only pgm-m-facts"><span aria-hidden="true">·</span> <?php echo esc_html( $pgm_role_text ); ?> <?php echo $pgm_short; // escaped above ?></span>
                <?php if ( $pgm_done === $pgm_uid && '' !== $pgm_dtext ) : // the notice at the top of the page, repeated where the page lands ?><span class="pgm-updated">✓ <?php echo esc_html( $pgm_dtext ); ?></span><?php endif; ?></span>
              <a class="pgm-email" href="<?php echo esc_url( 'mailto:' . $pgm_u->user_email ); ?>"><?php echo esc_html( $pgm_u->user_email ); ?></a>
            </span>
            </div>
          </th>
          <td class="pgm-c-role"><?php echo esc_html( $pgm_role_text ); ?><?php if ( $pgm_teaches ) : ?> <span class="pgm-badge">Instructor</span><?php endif; ?></td>
          <td class="pgm-c-badge"><?php echo '' !== $pgm_badge ? $pgm_badge : '<span class="pgm-muted">none</span>'; // escaped in the helper ?> <span class="pgm-points"><?php echo $pgm_points; // escaped above ?></span></td>
          <td class="pgm-c-status"><?php echo $pgm_flags; // escaped above ?></td>
          <td class="pgm-c-joined"><?php echo $pgm_joined; // escaped above ?></td>
          <td class="pgm-c-actions">
            <button type="button" class="pgm-btn pgm-btn-ghost pgm-manage-btn" aria-expanded="false" aria-controls="<?php echo esc_attr( $pgm_panel ); ?>">Manage<span class="screen-reader-text"> <?php echo esc_html( $pgm_u->display_name ); ?></span></button>
            <div class="pgm-panel" id="<?php echo esc_attr( $pgm_panel ); ?>" role="group" aria-label="<?php echo esc_attr( 'Manage ' . $pgm_u->display_name ); ?>">
              <p class="pgm-m-only pgm-panel-facts"><?php echo '' !== $pgm_badge ? $pgm_badge : 'No badge'; // escaped in the helper ?> <span aria-hidden="true">·</span> <?php echo $pgm_points; // escaped above ?> <span aria-hidden="true">·</span> Joined <?php echo $pgm_joined; // escaped above ?><?php echo $pgm_teaches ? ' <span aria-hidden="true">·</span> Instructor' : ''; ?></p>
              <a class="pgm-btn pgm-btn-ghost" href="<?php echo esc_url( prospergenics_profiles_url( $pgm_uid ) ); ?>">View profile<span class="screen-reader-text"> <?php echo esc_html( $pgm_u->display_name ); ?></span></a>
              <?php if ( ! $pgm_can ) : ?>
                <p class="pgm-muted pgm-item-note"><?php echo esc_html( $pgm_uid === $pgm_me ? 'This is you: change your own account in Settings.' : 'Staff account: only administrators change it, in wp-admin.' ); ?></p>
              <?php else :
                  $pgm_promote = current_user_can( 'promote_user', $pgm_uid );
                  $pgm_choices = prospergenics_manage_role_choices( $pgm_promote );
                  $pgm_current = (array) $pgm_u->roles;
                  if ( prospergenics_manage_role_change_allowed( $pgm_current, 'subscriber', $pgm_promote ) ) :
                      $pgm_role  = '<label class="screen-reader-text" for="pgm-role-' . (int) $pgm_uid . '">Role of ' . esc_html( $pgm_u->display_name ) . '</label>';
                      $pgm_role .= '<select class="pgm-select" id="pgm-role-' . (int) $pgm_uid . '" name="role">';
                      foreach ( $pgm_choices as $pgm_r => $pgm_r_label ) {
                          $pgm_role .= '<option value="' . esc_attr( $pgm_r ) . '"' . selected( in_array( $pgm_r, $pgm_current, true ), true, false ) . '>' . esc_html( $pgm_r_label ) . '</option>';
                      }
                      $pgm_role .= '</select>';
                      echo $pgm_button( 'role', $pgm_u, 'Change role', 'pgm-btn pgm-btn-ghost', $pgm_role ); // escaped inside
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
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    </div>
    <div class="pgm-pager">
      <p class="pgm-range"><?php echo esc_html( sprintf( 'Showing %s–%s of %s', number_format_i18n( $pgm_range[0] ), number_format_i18n( $pgm_range[1] ), number_format_i18n( $pgm_total ) ) ); ?></p>
      <?php echo prospergenics_manage_pagination( $pgm_list, $pgm_paged, $pgm_pages ); // escaped by paginate_links ?>
    </div>
  <?php else : ?>
    <p class="empty-note">No members found. Try another name, username or email, or show everyone.</p>
  <?php endif; ?>
<?php
prospergenics_manage_close();

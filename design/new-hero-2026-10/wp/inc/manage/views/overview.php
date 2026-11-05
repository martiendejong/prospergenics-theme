<?php
/**
 * View: /manage/ overview (card #6457), loaded by inc/manage/routes.php for staff.
 */

defined( 'ABSPATH' ) || exit;

$pgm_now    = time();
$pgm_week   = prospergenics_manage_activity( prospergenics_manage_since( 7, $pgm_now ) );
$pgm_month  = prospergenics_manage_activity( prospergenics_manage_since( 30, $pgm_now ) );
$pgm_queues = prospergenics_manage_queues();
$pgm_rows   = array(
    'members'      => array( 'New members', 'Accounts created', prospergenics_manage_url( 'members' ) ),
    'verified'     => array( 'Verified members', 'Email addresses confirmed', prospergenics_manage_url( 'members', array( 'filter' => 'verified' ) ) ),
    'enrolments'   => array( 'Enrolments', 'Course enrolments', '' ),
    'completions'  => array( 'Completions', 'Courses completed', '' ),
    'certificates' => array( 'Certificates', 'Certificates issued', '' ),
);
$pgm_waiting = array(
    array( 'Articles waiting for review', $pgm_queues['articles'], prospergenics_manage_can_view( 'articles' ) ? prospergenics_manage_url( 'articles' ) : '' ),
    array( 'Unread contact messages', $pgm_queues['messages'], prospergenics_manage_can_view( 'messages' ) ? prospergenics_manage_url( 'messages', array( 'view' => 'unread' ) ) : '' ),
    array( 'Open reports', $pgm_queues['reports'], current_user_can( 'pg_moderate' ) ? admin_url( 'admin.php?page=pg-moderation' ) : '' ),
);
if ( current_user_can( 'promote_users' ) ) {
    $pgm_waiting[] = array( 'Writer and instructor requests', $pgm_queues['requests'], admin_url( 'users.php?page=pg-applications' ) );
}

prospergenics_manage_open( 'overview', 'Sign-ups, enrolments and completions at a glance, and what is waiting for the team.' );
?>
  <section class="pgm-section" aria-labelledby="pgm-waiting-h">
    <h2 class="pgm-h2" id="pgm-waiting-h">Waiting for the team</h2>
    <ul class="pgm-cards">
      <?php foreach ( $pgm_waiting as $pgm_w ) : ?>
        <li class="pgm-card<?php echo $pgm_w[1] ? ' pgm-card-due' : ''; ?>">
          <span class="pgm-card-n"><?php echo esc_html( number_format_i18n( $pgm_w[1] ) ); ?></span>
          <?php if ( $pgm_w[2] ) : ?>
            <a class="pgm-card-label" href="<?php echo esc_url( $pgm_w[2] ); ?>"><?php echo esc_html( $pgm_w[0] ); ?></a>
          <?php else : ?>
            <span class="pgm-card-label"><?php echo esc_html( $pgm_w[0] ); ?></span>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </section>

  <section class="pgm-section" aria-labelledby="pgm-activity-h">
    <h2 class="pgm-h2" id="pgm-activity-h">Activity</h2>
    <div class="pgm-table-wrap">
      <table class="pgm-table">
        <caption class="screen-reader-text">Activity over the last 7 and 30 days</caption>
        <thead><tr><th scope="col">What</th><th scope="col" class="pgm-num">Last 7 days</th><th scope="col" class="pgm-num">Last 30 days</th></tr></thead>
        <tbody>
          <?php foreach ( $pgm_rows as $pgm_key => $pgm_r ) : ?>
            <tr>
              <th scope="row">
                <?php if ( $pgm_r[2] ) : ?><a href="<?php echo esc_url( $pgm_r[2] ); ?>"><?php echo esc_html( $pgm_r[0] ); ?></a><?php else : ?><?php echo esc_html( $pgm_r[0] ); ?><?php endif; ?>
                <span class="pgm-muted pgm-block"><?php echo esc_html( $pgm_r[1] ); ?></span>
              </th>
              <td class="pgm-num"><?php echo esc_html( number_format_i18n( $pgm_week[ $pgm_key ] ) ); ?></td>
              <td class="pgm-num"><?php echo esc_html( number_format_i18n( $pgm_month[ $pgm_key ] ) ); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="pgm-section" aria-labelledby="pgm-more-h">
    <h2 class="pgm-h2" id="pgm-more-h">More tools</h2>
    <?php echo prospergenics_manage_other_queues_html( $pgm_queues ); // escaped inside ?>
    <p class="pgm-muted">Everything else (settings, pages, blog posts, plugins) stays in <a href="<?php echo esc_url( admin_url() ); ?>">wp-admin</a>.</p>
  </section>
<?php
prospergenics_manage_close();

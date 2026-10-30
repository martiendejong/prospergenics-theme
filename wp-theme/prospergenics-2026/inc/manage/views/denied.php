<?php
/**
 * View: /manage/ for a logged-in user without the capability (card #6457). Sent with a 403 status
 * by inc/manage/routes.php; shows nothing of the staff area.
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Staff</div>
    <h1>Staff only</h1>
    <p class="ph-excerpt">This part of ProsperGenics is for the team that runs the site. Your account does not have access to it.</p>
  </div>
</div>
<div class="post-wrap">
  <ul class="links-404">
    <li><a href="<?php echo esc_url( home_url( '/' ) ); ?>">Homepage</a></li>
    <li><a href="<?php echo esc_url( home_url( '/courses/my/' ) ); ?>">My courses</a></li>
    <li><a href="<?php echo esc_url( home_url( '/articles/mine/' ) ); ?>">My articles</a></li>
    <li><a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a></li>
  </ul>
</div>
<?php
get_footer();

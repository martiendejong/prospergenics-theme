<?php
defined('ABSPATH') || exit;
// "Your connections" at /profile/following/ (profiles track, card #6349): who you follow, who follows you.
$uid       = get_current_user_id();
$following = prospergenics_profiles_following_ids( $uid );
$followers = prospergenics_profiles_follower_ids( $uid );
prospergenics_profiles_badges_prime( array_merge( $following, $followers ) ); // the cards' badges: one read for all
get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Your profile</div>
    <h1>Your connections</h1>
    <p class="ph-excerpt">Following someone is one-way: they don't need to accept. Members you follow count as your “connections” in privacy settings.</p>
  </div>
</div>
<div class="post-wrap pgp-narrow">
  <section class="pgp-section" aria-labelledby="pgp-following-h">
    <h2 id="pgp-following-h">You follow (<?php echo (int) count( $following ); ?>)</h2>
    <?php if ( $following ) : ?>
      <ul class="pgp-members"><?php foreach ( $following as $id ) { prospergenics_profiles_member_card( $id ); } ?></ul>
    <?php else : ?>
      <p class="pgp-empty">You don't follow anyone yet. Use the Follow button on a member's profile.</p>
    <?php endif; ?>
  </section>
  <section class="pgp-section" aria-labelledby="pgp-followers-h">
    <h2 id="pgp-followers-h">Your followers (<?php echo (int) count( $followers ); ?>)</h2>
    <?php if ( $followers ) : ?>
      <ul class="pgp-members"><?php foreach ( $followers as $id ) { prospergenics_profiles_member_card( $id ); } ?></ul>
    <?php else : ?>
      <p class="pgp-empty">No followers yet.</p>
    <?php endif; ?>
  </section>
  <p><a class="post-back" href="<?php echo esc_url( prospergenics_profiles_url( $uid ) ); ?>">← Back to your profile</a></p>
</div>
<?php get_footer(); ?>

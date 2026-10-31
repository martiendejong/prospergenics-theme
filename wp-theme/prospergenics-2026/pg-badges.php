<?php
defined('ABSPATH') || exit;
// /badges/ (profiles track): what each member badge means, how points are earned, and that only
// public, verified evidence counts. Loaded by inc/profiles/badges.php; the tiers and weights come
// from inc/profiles/rank-rules.php, so this page always shows the rules in force.
$pgb_tiers   = prospergenics_profiles_badge_tiers();
$pgb_weights = prospergenics_profiles_evidence_weights();
$pgb_uid     = get_current_user_id();
get_header();
?>
<div class="post-hero">
  <div class="post-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Member badges</div>
    <h1>Badges show evidence, not claims</h1>
    <p class="ph-excerpt">Every member badge is earned with evidence that anyone can check on the member’s profile: work that others confirmed, certificates, courses they teach. Here is what each badge means and how it is earned.</p>
  </div>
</div>

<div class="post-wrap pgb-page">
  <section class="pgb-section" aria-labelledby="pgb-tiers-h">
    <h2 id="pgb-tiers-h">The badges</h2>
    <ol class="pgb-tiers">
      <?php foreach ( $pgb_tiers as $pgb_key => $pgb_t ) : ?>
        <li class="pgb-tier" id="<?php echo esc_attr( 'tier-' . prospergenics_profiles_badge_class_key( $pgb_key ) ); ?>" tabindex="-1">
          <h3 class="pgb-tier-h"><?php echo prospergenics_profiles_badge_markup( $pgb_key, $pgb_t, 'header' ); // a <span>, escaped in the helper ?></h3>
          <?php if ( '' !== $pgb_t['means'] ) : ?><p><?php echo esc_html( $pgb_t['means'] ); ?></p><?php endif; ?>
          <ul class="pgb-needs" aria-label="<?php echo esc_attr( sprintf( 'What %s needs', $pgb_t['label'] ) ); ?>">
            <?php foreach ( prospergenics_profiles_tier_requirements( $pgb_key, $pgb_tiers, $pgb_weights ) as $pgb_need ) : ?>
              <li><?php echo esc_html( $pgb_need ); ?></li>
            <?php endforeach; ?>
          </ul>
        </li>
      <?php endforeach; ?>
    </ol>
    <p class="pgb-note">A member shows the highest badge they reach. The badges and their thresholds are a first proposal and may change as the community grows.</p>
  </section>

  <section class="pgb-section" aria-labelledby="pgb-points-h">
    <h2 id="pgb-points-h">How points are earned</h2>
    <p>Each piece of evidence on a profile is worth points. Evidence that someone else checked is worth the most. Each kind counts up to a limit, so nobody can collect points by adding the same thing over and over.</p>
    <div class="pgb-table-wrap">
      <table class="pgb-points">
        <caption class="screen-reader-text">Points per piece of evidence, and how many pieces count</caption>
        <thead>
          <tr><th scope="col">Evidence</th><th scope="col" class="pgb-num">Points each</th><th scope="col" class="pgb-num">Counts at most</th><th scope="col">Checked by others</th></tr>
        </thead>
        <tbody>
          <?php foreach ( $pgb_weights as $pgb_kind => $pgb_w ) :
              if ( empty( $pgb_w['cap'] ) ) { continue; } ?>
            <tr>
              <th scope="row"><?php echo esc_html( $pgb_w['label'] ); ?></th>
              <td class="pgb-num"><?php echo esc_html( (string) (int) $pgb_w['weight'] . ( ! empty( $pgb_w['bonus'] ) ? sprintf( ' (+%d per endorsement, up to +%d)', (int) $pgb_w['bonus'], (int) $pgb_w['bonus_max'] ) : '' ) ); ?></td>
              <td class="pgb-num"><?php echo (int) $pgb_w['cap']; ?></td>
              <td><?php echo $pgb_w['verified'] ? 'Yes' : 'No, the member’s own word'; ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="pgb-section" aria-labelledby="pgb-rules-h">
    <h2 id="pgb-rules-h">Only public, verified evidence counts</h2>
    <ul class="pgb-rules">
      <li>A badge counts only what a visitor who is not logged in can see on the profile. A section a member keeps private (for example their portfolio or their certificates) never raises their badge.</li>
      <li>A contribution counts as confirmed once the person who added the project confirms it. Disputed contributions never count.</li>
      <li>Evidence from others counts once the ProsperGenics team has reviewed and approved it. Evidence a member writes about themselves does not count.</li>
      <li>Endorsements count only from members who confirmed their email address. Revoked certificates and hidden articles do not count.</li>
      <li>Members who have not confirmed their email address, and profiles the ProsperGenics team has hidden or suspended, show no badge.</li>
      <li>Badges update when the evidence changes, and at least once a day. Only the member sees their own score and where it comes from; everyone else sees the badge.</li>
    </ul>
    <p class="pgb-cta">
      <?php if ( $pgb_uid ) : ?>
        <a class="pgb-btn" href="<?php echo esc_url( prospergenics_profiles_url( $pgb_uid ) . '#pgb-owner' ); ?>">See your score on your profile</a>
      <?php else : ?>
        <a class="pgb-btn" href="<?php echo esc_url( home_url( '/find/' ) ); ?>">Find people by skill</a>
      <?php endif; ?>
    </p>
  </section>
</div>
<?php get_footer(); ?>

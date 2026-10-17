<?php
/**
 * Team Story section, 2026 design.
 *
 * The approved Sandra/Lessy/Frank quotes (task 940, live-verified) rendered with the
 * 2026 design's tokens. It lives in a template part rather than inside the generated
 * front-page.php because that file is regenerated from the static design source by
 * design/new-hero-2026-10/port-to-theme.py, which would overwrite anything added there.
 *
 * Two constraints carried over from master and kept deliberately:
 *   - Quotes stay plain visible content. No Review/AggregateRating JSON-LD: these are
 *     employee/team-culture quotes, not independent customer reviews (task 940, and the
 *     sibling decision on martiendejong.nl task 925). Tagging them as Review schema would
 *     misrepresent insider quotes as social proof.
 *   - No <footer> element anywhere in a quote card. The SEO God plugin's script inserts
 *     its FAQ block before the page's first <footer>, which is how that FAQ ended up
 *     rendered inside the first quote card (task 926 hotfix).
 *
 * @package Prospergenics
 */

$team_story_quotes = prospergenics_get_team_story_quotes();
if ( empty( $team_story_quotes ) ) {
	return;
}
?>
  <!-- TEAM STORY · what it is like to build here, in the team's own words (task 940) -->
  <section class="tstory" id="team-story">
    <div class="voices-head">
      <h2><?php esc_html_e( 'Team Story', 'prospergenics' ); ?> <span class="accent"><?php esc_html_e( 'in our own words', 'prospergenics' ); ?></span></h2>
      <p><?php esc_html_e( 'What it is like to build with Prospergenics, from the people doing it.', 'prospergenics' ); ?></p>
    </div>
    <div class="tstory-grid">
      <?php foreach ( $team_story_quotes as $team_story_quote ) : ?>
        <blockquote class="tstory-card">
          <p class="tstory-quote"><?php echo esc_html( $team_story_quote['quote'] ); ?></p>
          <?php // Deliberately a div, not a footer -- see the file header. ?>
          <div class="tstory-attribution">
            <span class="tstory-author"><?php echo esc_html( $team_story_quote['author'] ); ?></span>
            <span class="tstory-role"><?php echo esc_html( $team_story_quote['role'] ); ?></span>
          </div>
        </blockquote>
      <?php endforeach; ?>
    </div>
  </section>

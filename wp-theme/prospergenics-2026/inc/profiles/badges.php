<?php
/**
 * Profiles track: member badges by ranking, the markup. The rules are in rank-rules.php, the
 * evidence and the cache in rank.php.
 *
 *   prospergenics_profiles_badge_html( $user_id, $context )  the one helper every surface uses:
 *       'header' on /in/<slug>/, 'compact' in lists (/find/, project contributors, bylines,
 *       instructors, /manage/), 'inline' inside another link (member cards: a <span>, no link)
 *   prospergenics_profiles_rank_owner_card( $user_id )        the owner's score, breakdown, the
 *       points to the next tier and tips, in the profile sidebar (only the owner sees it)
 *   /badges/                                                  the explainer (pg-badges.php)
 *
 * Styles: assets/platform/badges.css, on the pages that show badges (and, should a badge render
 * anywhere else, printed late in the footer).
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_PROFILES_BADGES_REWRITE_VERSION = '1';

/* ───────────────────────── the badge ───────────────────────── */

/** The explainer, optionally at one tier. */
function prospergenics_profiles_badges_url( $tier = '' ) {
    return home_url( '/badges/' ) . ( '' !== (string) $tier ? '#tier-' . prospergenics_profiles_badge_class_key( $tier ) : '' );
}

/**
 * A member's badge as HTML ('' for none: unverified, hidden, suspended, or no tier reached). Only
 * the tier is shown; the score and breakdown are the owner's (prospergenics_profiles_rank_owner_card()).
 *
 * @param string $context header|compact|inline
 */
function prospergenics_profiles_badge_html( $user_id, $context = 'compact' ) {
    $badge = prospergenics_profiles_badge_data( $user_id );
    if ( ! $badge ) {
        return '';
    }
    prospergenics_profiles_badges_enqueue();
    $tiers = prospergenics_profiles_badge_tiers();
    return prospergenics_profiles_badge_markup( $badge['tier'], $tiers[ $badge['tier'] ], $context, prospergenics_profiles_badges_url( $badge['tier'] ) );
}

/* ───────────────────────── the owner's view ───────────────────────── */

/** The owner's badge card: score, breakdown, points to the next tier, tips. Escaped here. */
function prospergenics_profiles_rank_owner_card( $user_id ) {
    $uid  = (int) $user_id;
    $rank = prospergenics_profiles_rank( $uid, true );
    if ( ! $rank ) {
        return;
    }
    prospergenics_profiles_badges_enqueue();
    $tiers   = prospergenics_profiles_badge_tiers();
    $weights = prospergenics_profiles_evidence_weights();
    $badge   = prospergenics_profiles_badge_html( $uid, 'header' );
    $next    = '' === $rank['state'] ? prospergenics_profiles_rank_next( $rank, $tiers, $weights ) : null;
    $states  = array(
        'unverified' => 'Confirm your email address to get your first badge (see the banner at the top of the page).',
        'hidden'     => 'The ProsperGenics team has hidden your profile, so no badge is shown anywhere.',
        'suspended'  => 'Your account is suspended, so no badge is shown anywhere.',
    );
    ?>
    <div class="pgp-card pgb-owner" id="pgb-owner">
      <h2 class="pgp-card-h" id="pgb-owner-h">Your badge</h2>
      <?php if ( '' !== $badge ) : ?>
        <p class="pgb-owner-badge"><?php echo $badge; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in prospergenics_profiles_badge_markup() ?></p>
      <?php elseif ( isset( $states[ $rank['state'] ] ) ) : ?>
        <p class="pgb-owner-state"><?php echo esc_html( $states[ $rank['state'] ] ); ?></p>
      <?php endif; ?>
      <p class="pgb-score"><strong><?php echo (int) $rank['score']; ?></strong> <?php echo 1 === (int) $rank['score'] ? 'point' : 'points'; ?> of public evidence</p>
      <?php if ( $next ) : ?>
        <p class="pgb-next">
          <?php if ( $next['points'] > 0 ) : ?>
            <?php echo esc_html( sprintf( '%d more %s to', $next['points'], 1 === $next['points'] ? 'point' : 'points' ) ); ?>
          <?php else : ?>
            Next:
          <?php endif; ?>
          <a href="<?php echo esc_url( prospergenics_profiles_badges_url( $next['tier'] ) ); ?>"><?php echo esc_html( $next['label'] ); ?></a>
        </p>
        <?php $goal = max( 1, (int) $tiers[ $next['tier'] ]['min_score'] ); ?>
        <div class="pgp-meter pgb-meter" role="progressbar" aria-label="<?php echo esc_attr( sprintf( 'Points towards %s', $next['label'] ) ); ?>" aria-valuemin="0" aria-valuemax="<?php echo (int) $goal; ?>" aria-valuenow="<?php echo (int) min( $goal, $rank['score'] ); ?>">
          <span style="width:<?php echo (int) round( 100 * min( 1, $rank['score'] / $goal ) ); ?>%"></span>
        </div>
      <?php elseif ( '' === $rank['state'] ) : ?>
        <p class="pgb-next">You have the highest badge.</p>
      <?php endif; ?>
      <?php if ( $rank['breakdown'] ) : ?>
        <table class="pgb-breakdown">
          <caption>Where your points come from</caption>
          <thead><tr><th scope="col">Evidence</th><th scope="col" class="pgb-num">Points</th></tr></thead>
          <tbody>
            <?php foreach ( $rank['breakdown'] as $kind => $b ) : ?>
              <tr>
                <th scope="row">
                  <?php echo esc_html( isset( $weights[ $kind ] ) ? $weights[ $kind ]['label'] : $kind ); ?>
                  <span class="pgb-count"><?php echo esc_html( $b['items'] > $b['counted'] ? sprintf( '%d of %d count (at most %d)', $b['counted'], $b['items'], $b['cap'] ) : sprintf( '%d (counts up to %d)', $b['counted'], $b['cap'] ) ); ?></span>
                </th>
                <td class="pgb-num"><?php echo (int) $b['points']; ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php else : ?>
        <p class="pgp-hint">Nothing on your profile counts yet.</p>
      <?php endif; ?>
      <?php if ( $next && $next['tips'] ) : ?>
        <h3 class="pgb-tips-h"><?php echo esc_html( sprintf( 'How to reach %s', $next['label'] ) ); ?></h3>
        <ul class="pgb-tips">
          <?php foreach ( $next['tips'] as $tip ) : ?>
            <li><?php echo esc_html( $tip ); ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="pgp-hint pgb-private-note">Only you see your score. Everyone else sees your badge. <a href="<?php echo esc_url( prospergenics_profiles_badges_url() ); ?>">How badges work</a></p>
    </div>
    <?php
}

/* ───────────────────────── /badges/ ───────────────────────── */

add_action( 'init', function () {
    add_rewrite_rule( '^badges/?$', 'index.php?pg_badges=1', 'top' );
    if ( get_option( '_pg_profiles_badges_rewrite_version' ) !== PROSPERGENICS_PROFILES_BADGES_REWRITE_VERSION ) {
        flush_rewrite_rules( false );
        update_option( '_pg_profiles_badges_rewrite_version', PROSPERGENICS_PROFILES_BADGES_REWRITE_VERSION );
    }
} );
add_action( 'after_switch_theme', function () {
    delete_option( '_pg_profiles_badges_rewrite_version' );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'pg_badges';
    return $vars;
} );

/** Whether this request is the /badges/ explainer. */
function prospergenics_profiles_badges_is_page() {
    return '1' === (string) get_query_var( 'pg_badges' );
}

// Don't run the blog query behind the page.
add_filter( 'posts_pre_query', function ( $posts, $query ) {
    if ( $query->is_main_query() && '1' === (string) $query->get( 'pg_badges' ) ) {
        $query->found_posts   = 0;
        $query->max_num_pages = 0;
        return array();
    }
    return $posts;
}, 10, 2 );

add_action( 'template_redirect', function () {
    if ( ! prospergenics_profiles_badges_is_page() ) {
        return;
    }
    global $wp_query;
    $wp_query->is_home = false;
    $wp_query->is_404  = false;
    status_header( 200 );
}, 5 );

add_filter( 'template_include', function ( $template ) {
    if ( ! prospergenics_profiles_badges_is_page() ) {
        return $template;
    }
    $found = locate_template( 'pg-badges.php' );
    return $found ? $found : $template;
}, 20 );

add_filter( 'body_class', function ( $classes ) {
    if ( prospergenics_profiles_badges_is_page() ) {
        $classes[] = 'pg-badges-page';
    }
    return $classes;
} );

add_filter( 'pre_get_document_title', function ( $title ) {
    return prospergenics_profiles_badges_is_page() ? 'Member badges: what they mean and how they are earned – ProsperGenics' : $title;
}, 20 );

add_action( 'wp_head', function () {
    if ( ! prospergenics_profiles_badges_is_page() ) {
        return;
    }
    echo '<link rel="canonical" href="' . esc_url( home_url( '/badges/' ) ) . "\" />\n";
    echo '<meta name="description" content="' . esc_attr( 'What the ProsperGenics member badges mean, how points are earned, and why only public, verified evidence counts.' ) . "\" />\n";
}, 3 );

/* ───────────────────────── styles ───────────────────────── */

/** Whether this request renders badges (their styles go in the head then). */
function prospergenics_profiles_badges_wanted() {
    $wanted = prospergenics_profiles_badges_is_page()
        || ( function_exists( 'prospergenics_profiles_route' ) && '' !== prospergenics_profiles_route() )
        || ( function_exists( 'prospergenics_search_is_page' ) && prospergenics_search_is_page() )
        || '' !== (string) get_query_var( 'pg_manage' )
        || '' !== (string) get_query_var( 'pg_courses_user' )
        || is_singular( array( 'pg_project', 'pg_article', 'pg_course' ) );
    /** Whether the badge styles load on this page (other pages that show badges can say yes). */
    return (bool) apply_filters( 'prospergenics_profiles_badges_wanted', $wanted );
}

/** Enqueue the badge styles (in the head on the pages above; late, in the footer, elsewhere). */
function prospergenics_profiles_badges_enqueue() {
    if ( wp_style_is( 'pg-badges', 'enqueued' ) || wp_style_is( 'pg-badges', 'done' ) ) {
        return;
    }
    $css = '/assets/platform/badges.css';
    $dir = get_template_directory();
    wp_enqueue_style( 'pg-badges', get_template_directory_uri() . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : null );
}

add_action( 'wp_enqueue_scripts', function () {
    if ( prospergenics_profiles_badges_wanted() ) {
        prospergenics_profiles_badges_enqueue();
    }
} );

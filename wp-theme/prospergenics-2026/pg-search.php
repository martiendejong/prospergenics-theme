<?php
defined( 'ABSPATH' ) || exit;
// Expertise search at /find/ (search track, card #6393). Loaded by inc/search/routes.php, which
// computed the search in template_redirect. Server-rendered: tabs and filters are plain links and
// a GET form, so everything works without JavaScript and every result is in the HTML.
$state = prospergenics_search_state();
$args  = $state['args'];
$r     = $state['result'];
$q     = $args['q'];
$types = prospergenics_search_types();
if ( $r['listings'] ) {
    $types['listings'] = 'Listings';
}
$counts = array( 'all' => $state['total'] );
foreach ( $types as $t => $label ) {
    $counts[ $t ] = count( $r[ $t ] );
}
$base_args = array( 'q' => $q, 'problem' => $args['problem'], 'skill' => $args['skill'], 'kind' => $args['kind'], 'verified' => $args['verified'] );
if ( '' !== $args['problem'] ) {
    $base_args['q'] = '';
}
$tab_url = function ( $type, $page = 1 ) use ( $base_args ) {
    return prospergenics_search_url( array_merge( $base_args, array( 'type' => $type, 'pg' => $page ) ) );
};
if ( function_exists( 'prospergenics_profiles_badges_prime' ) ) {
    prospergenics_profiles_badges_prime( wp_list_pluck( $r['people'], 'id' ) ); // their badges: one read for all
}
$has_projects = post_type_exists( 'pg_project' );
$filtered     = '' !== $args['skill'] || '' !== $args['kind'] || $args['verified'];
$kinds        = prospergenics_search_project_kinds();

/** One person result. */
$person_card = function ( array $p ) use ( $q ) {
    $photo = function_exists( 'prospergenics_profiles_photo_url' ) ? prospergenics_profiles_photo_url( $p['id'], 'thumbnail' ) : '';
    $why   = prospergenics_search_explanation_html( $q, $p['evidence'] ); // escaped in the helper
    ?>
    <li class="pgf-card pgf-person">
      <?php if ( $photo ) : ?>
        <img class="pgf-avatar" src="<?php echo esc_url( $photo ); ?>" alt="" width="56" height="56" loading="lazy" />
      <?php else : ?>
        <span class="pgf-avatar pgf-avatar-ph" aria-hidden="true"><?php echo esc_html( function_exists( 'prospergenics_profiles_initials' ) ? prospergenics_profiles_initials( $p['name'] ) : mb_substr( $p['name'], 0, 1 ) ); ?></span>
      <?php endif; ?>
      <div class="pgf-card-body">
        <h3 class="pgf-card-title"><a href="<?php echo esc_url( $p['url'] ); ?>"><?php echo esc_html( $p['name'] ); ?></a><?php if ( function_exists( 'prospergenics_profiles_badge_html' ) ) { echo ' ' . prospergenics_profiles_badge_html( $p['id'] ); } // member badge (inc/profiles/badges.php), escaped in the helper ?></h3>
        <?php if ( '' !== $p['headline'] ) : ?>
          <p class="pgf-sub"><?php echo esc_html( $p['headline'] ); ?></p>
        <?php endif; ?>
        <p class="pgf-why"><?php echo $why; // phpcs:ignore WordPress.Security.EscapeOutput -- escaped in prospergenics_search_explanation_html() ?></p>
        <?php if ( $p['verified'] ) : ?>
          <p class="pgf-badges"><span class="pgf-badge pgf-badge-verified"><?php echo esc_html( sprintf( _n( '%d verified piece of evidence', '%d verified pieces of evidence', (int) $p['verified'] ), (int) $p['verified'] ) ); ?></span></p>
        <?php endif; ?>
      </div>
    </li>
    <?php
};

/** One project result. */
$project_card = function ( array $p ) use ( $q, $kinds ) {
    ?>
    <li class="pgf-card">
      <div class="pgf-card-body">
        <h3 class="pgf-card-title"><a href="<?php echo esc_url( $p['url'] ); ?>"><?php echo esc_html( $p['title'] ); ?></a></h3>
        <p class="pgf-sub">
          <?php echo esc_html( isset( $kinds[ $p['kind'] ] ) ? $kinds[ $p['kind'] ] : 'Project' ); ?>
          <?php if ( '' !== $p['period'] ) : ?> · <?php echo esc_html( $p['period'] ); ?><?php endif; ?>
          · <?php echo esc_html( sprintf( _n( '%d contributor', '%d contributors', (int) $p['people'] ), (int) $p['people'] ) ); ?><?php if ( $p['confirmed'] ) : ?>, <?php echo esc_html( sprintf( '%d confirmed', (int) $p['confirmed'] ) ); ?><?php endif; ?>
        </p>
        <?php if ( '' !== $p['snippet'] ) : ?>
          <p class="pgf-snippet"><?php echo esc_html( $p['snippet'] ); ?></p>
        <?php endif; ?>
        <p class="pgf-why"><?php echo esc_html( sprintf( 'Matches ‘%s’: %s', $q, implode( '; ', $p['why'] ) ) ); ?></p>
      </div>
    </li>
    <?php
};

/** One course or article result. */
$post_card = function ( array $p, $type ) {
    ?>
    <li class="pgf-card">
      <div class="pgf-card-body">
        <h3 class="pgf-card-title"><a href="<?php echo esc_url( $p['url'] ); ?>"><?php echo esc_html( $p['title'] ); ?></a></h3>
        <p class="pgf-sub"><?php echo esc_html( 'courses' === $type ? sprintf( 'Course by %s', $p['author'] ) : sprintf( 'Article by %s · %s', $p['author'], $p['date'] ) ); ?></p>
        <?php if ( '' !== $p['snippet'] ) : ?>
          <p class="pgf-snippet"><?php echo esc_html( $p['snippet'] ); ?></p>
        <?php endif; ?>
      </div>
    </li>
    <?php
};

/** One listings (Jengo Listings seam) result. */
$listing_card = function ( array $p ) {
    ?>
    <li class="pgf-card">
      <div class="pgf-card-body">
        <h3 class="pgf-card-title"><a href="<?php echo esc_url( $p['url'] ); ?>"><?php echo esc_html( $p['title'] ); ?></a></h3>
        <?php if ( ! empty( $p['type'] ) ) : ?><p class="pgf-sub"><?php echo esc_html( $p['type'] ); ?></p><?php endif; ?>
        <?php if ( ! empty( $p['excerpt'] ) ) : ?><p class="pgf-snippet"><?php echo esc_html( $p['excerpt'] ); ?></p><?php endif; ?>
      </div>
    </li>
    <?php
};

$render_list = function ( $type, array $items ) use ( $person_card, $project_card, $post_card, $listing_card ) {
    echo '<ol class="pgf-list">';
    foreach ( $items as $it ) {
        if ( 'people' === $type ) {
            $person_card( $it );
        } elseif ( 'projects' === $type ) {
            $project_card( $it );
        } elseif ( 'listings' === $type ) {
            $listing_card( $it );
        } else {
            $post_card( $it, $type );
        }
    }
    echo '</ol>';
};

$suggestions = array();
foreach ( array_keys( $r['skills'] ) as $s ) {
    $suggestions[] = $s;
}
if ( count( $suggestions ) < 8 && function_exists( 'prospergenics_profiles_skills_vocabulary' ) ) {
    foreach ( array( 'Pricing', 'Sales', 'Android', 'Coaching', 'Fundraising', 'Bookkeeping', 'Public Speaking', 'Project Management' ) as $s ) {
        if ( count( $suggestions ) < 8 && ! in_array( $s, $suggestions, true ) ) {
            $suggestions[] = $s;
        }
    }
}

get_header();
?>
<div class="post-hero pgf-hero">
  <div class="post-hero-inner pgf-hero-inner">
    <div class="ph-eyebrow"><span class="dot"></span>Find people</div>
    <h1><?php echo '' === $q ? 'Find people by skill, project or problem' : esc_html( sprintf( 'Who knows ‘%s’?', $q ) ); ?></h1>
    <p class="ph-excerpt">Search ProsperGenics members, their projects, courses and articles. Every person comes with the evidence for why they match.</p>
    <form class="pgf-form" role="search" method="get" action="<?php echo esc_url( home_url( '/find/' ) ); ?>">
      <label class="pgf-label" for="pgf-q">Skill, project or problem</label>
      <div class="pgf-row">
        <input type="search" id="pgf-q" name="q" value="<?php echo esc_attr( '' === $args['problem'] ? $q : '' ); ?>" maxlength="100" placeholder="e.g. pricing, Android, first paying customer" autocomplete="off" />
        <button type="submit">Search</button>
      </div>
      <details class="pgf-problem"<?php echo '' !== $args['problem'] ? ' open' : ''; ?>>
        <summary>Or describe your problem</summary>
        <label class="pgf-label" for="pgf-problem">What are you stuck on?</label>
        <textarea id="pgf-problem" name="problem" rows="3" maxlength="600" aria-describedby="pgf-problem-hint"><?php echo esc_textarea( $args['problem'] ); ?></textarea>
        <p class="pgf-hint" id="pgf-problem-hint">We search with the key words of your text. It is not stored.</p>
        <button type="submit">Find people who can help</button>
      </details>
    </form>
  </div>
</div>

<div class="pgf-wrap">
<?php if ( '' === $q ) : ?>
  <section class="pgf-intro" aria-labelledby="pgf-try-h">
    <h2 id="pgf-try-h">Try a skill</h2>
    <ul class="pgf-chips">
      <?php foreach ( $suggestions as $s ) : ?>
        <li><a href="<?php echo esc_url( prospergenics_search_url( array( 'q' => mb_strtolower( $s ) ) ) ); ?>"><?php echo esc_html( $s ); ?></a></li>
      <?php endforeach; ?>
    </ul>
    <p>Or browse <a href="<?php echo esc_url( home_url( '/members/' ) ); ?>">all members</a> and <a href="<?php echo esc_url( home_url( '/experts/' ) ); ?>">our experts</a>.</p>
  </section>
<?php else : ?>
  <?php if ( '' !== $args['problem'] ) : ?>
    <p class="pgf-note">We searched for: <strong><?php echo esc_html( implode( ', ', $args['terms'] ) ); ?></strong>. <a href="<?php echo esc_url( prospergenics_search_url( array( 'q' => $q ) ) ); ?>">Search for these words only</a>.</p>
  <?php endif; ?>

  <nav class="pgf-tabs" aria-label="Result types">
    <ul>
      <?php foreach ( array_merge( array( 'all' => 'All' ), $types ) as $t => $label ) : ?>
        <li><a href="<?php echo esc_url( $tab_url( $t ) ); ?>"<?php echo $t === $args['type'] ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $label ); ?> <span class="pgf-count">(<?php echo (int) $counts[ $t ]; ?>)</span></a></li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <?php if ( in_array( $args['type'], array( 'all', 'people', 'projects' ), true ) ) : ?>
    <form class="pgf-filters" method="get" action="<?php echo esc_url( home_url( '/find/' ) ); ?>" aria-label="Filters">
      <?php if ( '' !== $args['problem'] ) : ?>
        <input type="hidden" name="problem" value="<?php echo esc_attr( $args['problem'] ); ?>" />
      <?php else : ?>
        <input type="hidden" name="q" value="<?php echo esc_attr( $q ); ?>" />
      <?php endif; ?>
      <?php if ( 'all' !== $args['type'] ) : ?>
        <input type="hidden" name="type" value="<?php echo esc_attr( $args['type'] ); ?>" />
      <?php endif; ?>
      <div class="pgf-filter">
        <label for="pgf-skill">Skill</label>
        <select id="pgf-skill" name="skill">
          <option value="">Any skill</option>
          <?php
          $skill_opts = $r['skills'];
          if ( '' !== $args['skill'] && ! isset( $skill_opts[ $args['skill'] ] ) ) {
              $skill_opts = array( $args['skill'] => 0 ) + $skill_opts;
          }
          foreach ( $skill_opts as $name => $n ) : ?>
            <option value="<?php echo esc_attr( $name ); ?>"<?php selected( mb_strtolower( $args['skill'] ), mb_strtolower( $name ) ); ?>><?php echo esc_html( $n ? sprintf( '%s (%d)', $name, $n ) : $name ); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php if ( $has_projects ) : ?>
        <div class="pgf-filter">
          <label for="pgf-kind">Project kind</label>
          <select id="pgf-kind" name="kind">
            <option value="">Any kind</option>
            <?php foreach ( $kinds as $k => $label ) : ?>
              <option value="<?php echo esc_attr( $k ); ?>"<?php selected( $args['kind'], $k ); ?>><?php echo esc_html( $label ); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
      <?php endif; ?>
      <div class="pgf-filter pgf-filter-check">
        <input type="checkbox" id="pgf-verified" name="verified" value="1"<?php checked( $args['verified'] ); ?> aria-describedby="pgf-verified-hint" />
        <label for="pgf-verified">Verified evidence only</label>
        <span class="pgf-hint" id="pgf-verified-hint">Confirmed project work, certificates, courses taught<?php echo post_type_exists( 'pg_scrutiny' ) ? ', evidence from others approved by our team' : ''; ?>.</span>
      </div>
      <div class="pgf-filter-actions">
        <button type="submit">Apply filters</button>
        <?php if ( $filtered ) : ?>
          <a href="<?php echo esc_url( prospergenics_search_url( array( 'q' => $base_args['q'], 'problem' => $args['problem'], 'type' => $args['type'] ) ) ); ?>">Clear filters</a>
        <?php endif; ?>
      </div>
    </form>
  <?php endif; ?>

  <?php if ( ! $state['total'] ) : ?>
    <div class="pgf-empty">
      <h2>No one matches ‘<?php echo esc_html( $q ); ?>’ yet</h2>
      <p>Try fewer or broader words<?php echo $filtered ? ', or clear the filters' : ''; ?>. Only members who share their profile publicly can be found here.</p>
      <ul class="pgf-chips">
        <?php foreach ( $suggestions as $s ) : ?>
          <li><a href="<?php echo esc_url( prospergenics_search_url( array( 'q' => mb_strtolower( $s ) ) ) ); ?>"><?php echo esc_html( $s ); ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php elseif ( 'all' === $args['type'] ) : ?>
    <?php foreach ( $types as $t => $label ) :
        if ( ! $r[ $t ] ) {
            continue;
        }
        $shown = array_slice( $r[ $t ], 0, 'people' === $t ? PROSPERGENICS_SEARCH_PER_PAGE / 2 : PROSPERGENICS_SEARCH_PER_GROUP ); ?>
      <section class="pgf-group" aria-labelledby="pgf-h-<?php echo esc_attr( $t ); ?>">
        <h2 id="pgf-h-<?php echo esc_attr( $t ); ?>"><?php echo esc_html( $label ); ?> <span class="pgf-count">(<?php echo (int) count( $r[ $t ] ); ?>)</span></h2>
        <?php $render_list( $t, $shown ); ?>
        <?php if ( count( $r[ $t ] ) > count( $shown ) ) : ?>
          <p class="pgf-more"><a href="<?php echo esc_url( $tab_url( $t ) ); ?>"><?php echo esc_html( sprintf( 'See all %d %s', count( $r[ $t ] ), strtolower( $label ) ) ); ?> →</a></p>
        <?php endif; ?>
      </section>
    <?php endforeach; ?>
  <?php else :
      $all   = $r[ $args['type'] ];
      $pages = (int) ceil( count( $all ) / PROSPERGENICS_SEARCH_PER_PAGE );
      $page  = min( $args['page'], max( 1, $pages ) ); ?>
    <section class="pgf-group" aria-labelledby="pgf-h-tab">
      <h2 id="pgf-h-tab"><?php echo esc_html( $types[ $args['type'] ] ); ?> <span class="pgf-count">(<?php echo (int) count( $all ); ?>)</span></h2>
      <?php if ( $all ) : ?>
        <?php $render_list( $args['type'], prospergenics_search_page( $all, $page ) ); ?>
      <?php else : ?>
        <p class="pgf-note">No <?php echo esc_html( strtolower( $types[ $args['type'] ] ) ); ?> match. <a href="<?php echo esc_url( $tab_url( 'all' ) ); ?>">See all results</a>.</p>
      <?php endif; ?>
      <?php if ( $pages > 1 ) : ?>
        <nav class="pagination" aria-label="Pages">
          <?php for ( $i = 1; $i <= $pages; $i++ ) : ?>
            <?php if ( $i === $page ) : ?>
              <span class="page-numbers current" aria-current="page"><?php echo (int) $i; ?></span>
            <?php else : ?>
              <a class="page-numbers" href="<?php echo esc_url( $tab_url( $args['type'], $i ) ); ?>"><?php echo (int) $i; ?></a>
            <?php endif; ?>
          <?php endfor; ?>
        </nav>
      <?php endif; ?>
    </section>
  <?php endif; ?>
<?php endif; ?>

  <details class="pgf-how">
    <summary>How results are ranked</summary>
    <p>People who match more of your words come first. Then the strength of the evidence counts: work on a project confirmed by its owner counts most<?php echo post_type_exists( 'pg_scrutiny' ) ? ', then evidence from others that our team approved' : ''; ?>, then certificates and courses they teach, then skills (more with endorsements), claimed project work, articles and experience. Only what a member shares publicly is searched: a section they keep private never counts, and hidden or suspended profiles never appear.</p>
  </details>
</div>
<?php get_footer(); ?>

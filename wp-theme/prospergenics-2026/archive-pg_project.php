<?php
defined('ABSPATH') || exit;
// /projects/ (projects track, card #6388): projects, products, services and employers of members,
// filtered by kind and skill. Lists only what this viewer may see (inc/projects/routes.php).
get_header();

$pgj_filters = prospergenics_projects_current_filters();
$pgj_skills  = prospergenics_projects_skill_options();
$pgj_uid     = get_current_user_id();
$pgj_active  = '' !== $pgj_filters['kind'] || '' !== $pgj_filters['skill'];
global $wp_query;
?>
<div class="pgp-root pgj-root">
  <div class="post-hero">
    <div class="post-hero-inner">
      <div class="ph-eyebrow"><span class="dot"></span>Member portfolios</div>
      <h1>Projects</h1>
      <p class="ph-excerpt">What ProsperGenics members build and run: projects, products, services and employers, with each member’s own role, contribution and the evidence behind it.</p>
    </div>
  </div>

  <div class="pgj-wrap">
    <?php prospergenics_projects_print_notice(); ?>
    <form class="pgj-filters" method="get" action="<?php echo esc_url(get_post_type_archive_link('pg_project')); ?>" role="search" aria-label="Filter projects">
      <div class="pgp-field">
        <label for="pgj-filter-kind">Kind</label>
        <select id="pgj-filter-kind" name="kind">
          <option value="">All kinds</option>
          <?php foreach (prospergenics_projects_kinds() as $pgj_k => $pgj_l) : ?>
            <option value="<?php echo esc_attr($pgj_k); ?>" <?php selected($pgj_filters['kind'], $pgj_k); ?>><?php echo esc_html($pgj_l); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="pgp-field">
        <label for="pgj-filter-skill">Skill</label>
        <select id="pgj-filter-skill" name="skill">
          <option value="">All skills</option>
          <?php foreach ($pgj_skills as $pgj_k => $pgj_l) : ?>
            <option value="<?php echo esc_attr($pgj_k); ?>" <?php selected($pgj_filters['skill'], $pgj_k); ?>><?php echo esc_html($pgj_l); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="pgj-filter-actions">
        <button type="submit" class="pgp-btn pgp-btn-primary">Show projects</button>
        <?php if ($pgj_active) : ?><a href="<?php echo esc_url(get_post_type_archive_link('pg_project')); ?>">Clear filters</a><?php endif; ?>
      </div>
    </form>

    <p class="pgj-count" role="status"><?php
      $pgj_n = (int) $wp_query->found_posts;
      echo esc_html(sprintf(1 === $pgj_n ? '%d project' : '%d projects', $pgj_n) . ($pgj_active ? ' match your filters' : ''));
    ?></p>

    <?php if (have_posts()) : ?>
      <div class="pgj-grid">
        <?php while (have_posts()) : the_post(); ?>
          <?php echo prospergenics_projects_card(get_post(), $pgj_uid); // escaped in the helper ?>
        <?php endwhile; ?>
      </div>
      <nav class="pagination" aria-label="Project pages">
        <?php echo paginate_links(array('add_args' => array_filter(array('kind' => $pgj_filters['kind'], 'skill' => $pgj_filters['skill'])))); ?>
      </nav>
    <?php elseif ($pgj_active) : ?>
      <p class="pgp-empty">No projects match these filters. <a href="<?php echo esc_url(get_post_type_archive_link('pg_project')); ?>">Show all projects</a>.</p>
    <?php else : ?>
      <p class="pgp-empty">No projects to show yet.</p>
    <?php endif; ?>

    <div class="pgp-card pgj-cta">
      <?php if ($pgj_uid && function_exists('prospergenics_profiles_url')) : ?>
        <p>Worked on something? Add it to your portfolio, or add your part to a project that is already here.</p>
        <p><a class="pgp-btn pgp-btn-primary" href="<?php echo esc_url(prospergenics_profiles_url($pgj_uid, array('edit' => 1)) . '#pgp-portfolio'); ?>">Go to my portfolio</a></p>
      <?php else : ?>
        <p>Members show their projects here, with their role and the evidence behind it.</p>
        <p><a class="pgp-btn pgp-btn-primary" href="<?php echo esc_url(wp_login_url(home_url('/profile/'))); ?>">Log in to add yours</a></p>
      <?php endif; ?>
    </div>
  </div>
</div>
<?php get_footer(); ?>

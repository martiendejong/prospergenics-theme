<?php
defined('ABSPATH') || exit;
// /projects/<slug>/ (projects track, card #6388): the project, and each contributor's role,
// contribution, evidence and status, as far as each member's portfolio privacy allows. The creator
// and staff edit the project and confirm contributions; verified members add their own part.
// Loaded only for viewers who may see the project (inc/projects/routes.php answers 404 otherwise).
$pgj_post    = get_queried_object();
$pgj_uid     = get_current_user_id();
$pgj_staff   = prospergenics_projects_is_staff($pgj_uid);
$pgj_fields  = prospergenics_projects_fields($pgj_post->ID);
$pgj_kinds   = prospergenics_projects_kinds();
$pgj_creator = (int) $pgj_post->post_author;
$pgj_all     = prospergenics_projects_contributions($pgj_post->ID);
$pgj_people  = prospergenics_projects_contributors($pgj_post->ID, $pgj_uid);
if (function_exists('prospergenics_profiles_badges_prime')) {
    prospergenics_profiles_badges_prime(wp_list_pluck($pgj_people, 'user_id')); // their badges: one read for all
}
$pgj_mine    = prospergenics_projects_find_contribution($pgj_all, $pgj_uid) >= 0;
$pgj_can_edit = prospergenics_projects_can_edit_project($pgj_uid, $pgj_creator, $pgj_staff) && ! prospergenics_projects_user_suspended($pgj_uid);
$pgj_others  = 0;
foreach ($pgj_all as $pgj_c) { $pgj_others += (int) $pgj_c['user_id'] !== $pgj_creator ? 1 : 0; }
$pgj_can_delete = prospergenics_projects_can_delete_project($pgj_uid, $pgj_creator, $pgj_staff, $pgj_others);
$pgj_editing = $pgj_can_edit && isset($_GET['edit']);
$pgj_url     = get_permalink($pgj_post);
get_header();
?>
<div class="pgp-root pgj-root">
  <div class="post-hero">
    <div class="post-hero-inner">
      <div class="ph-eyebrow"><span class="dot"></span><?php echo esc_html($pgj_kinds[$pgj_fields['kind']]); ?></div>
      <h1><?php echo esc_html(get_the_title($pgj_post)); ?></h1>
      <?php if ('' !== $pgj_fields['period'] || '' !== $pgj_fields['url']) : ?>
        <p class="ph-excerpt pgj-hero-meta">
          <?php if ('' !== $pgj_fields['period']) : ?><span><?php echo esc_html($pgj_fields['period']); ?></span><?php endif; ?>
          <?php if ('' !== $pgj_fields['url']) : ?><span><?php echo prospergenics_projects_external_link($pgj_fields['url'], (string) preg_replace('/^www\./i', '', (string) parse_url($pgj_fields['url'], PHP_URL_HOST))); // escaped in the helper ?></span><?php endif; ?>
        </p>
      <?php endif; ?>
    </div>
  </div>

  <div class="pgj-wrap">
    <p class="post-back"><a href="<?php echo esc_url(get_post_type_archive_link('pg_project')); ?>">All projects</a></p>
    <?php prospergenics_projects_print_notice(); ?>
    <?php if (prospergenics_projects_is_hidden($pgj_post->ID)) : ?>
      <div class="pgp-notice pgp-notice-info" role="status">This project is hidden by the ProsperGenics team: only its creator and staff can see it.</div>
    <?php endif; ?>

    <?php if ('' !== trim($pgj_post->post_content)) : ?>
      <section class="pgp-section" aria-labelledby="pgj-about-h">
        <h2 id="pgj-about-h">About</h2>
        <div class="pgp-about"><?php echo wpautop(esc_html($pgj_post->post_content)); ?></div>
      </section>
    <?php endif; ?>

    <?php if ($pgj_fields['skills']) : ?>
      <section class="pgp-section" aria-labelledby="pgj-skills-h">
        <h2 id="pgj-skills-h">Skills</h2>
        <ul class="pgj-skills">
          <?php foreach ($pgj_fields['skills'] as $pgj_s) : ?>
            <li><a href="<?php echo esc_url(add_query_arg('skill', rawurlencode(prospergenics_projects_skill_key($pgj_s)), get_post_type_archive_link('pg_project'))); ?>"><?php echo esc_html($pgj_s); ?><span class="screen-reader-text">: more projects with this skill</span></a></li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>

    <section class="pgp-section" id="pgj-contributors" aria-labelledby="pgj-people-h">
      <h2 id="pgj-people-h">Who contributed</h2>
      <?php if ($pgj_people) : ?>
        <ol class="pgj-list">
          <?php foreach ($pgj_people as $pgj_c) :
              $pgj_u = get_userdata($pgj_c['user_id']);
              if (!$pgj_u) { continue; }
              $pgj_purl  = prospergenics_profiles_url($pgj_u->ID);
              $pgj_photo = prospergenics_profiles_visibility_for($pgj_u->ID, $pgj_uid)['profile'] ? prospergenics_profiles_photo_url($pgj_u->ID, 'thumbnail') : '';
              $pgj_cid   = 'pgj-c-' . $pgj_post->ID . '-' . $pgj_u->ID; ?>
            <li class="pgj-item pgj-person">
              <div class="pgj-person-head">
                <?php if ($pgj_photo) : ?>
                  <img src="<?php echo esc_url($pgj_photo); ?>" alt="" width="48" height="48" loading="lazy" />
                <?php else : ?>
                  <span class="pgp-avatar-ph" aria-hidden="true"><?php echo esc_html(prospergenics_profiles_initials($pgj_u->display_name)); ?></span>
                <?php endif; ?>
                <div>
                  <h3><a href="<?php echo esc_url($pgj_purl); ?>"><?php echo esc_html($pgj_u->display_name); ?></a><?php if (function_exists('prospergenics_profiles_badge_html')) { echo ' ' . prospergenics_profiles_badge_html($pgj_u->ID); } // member badge, escaped in the helper ?><?php if ((int) $pgj_u->ID === $pgj_creator) : ?> <span class="pgj-creator">added this <?php echo esc_html(strtolower($pgj_kinds[$pgj_fields['kind']])); ?></span><?php endif; ?></h3>
                  <p class="pgj-role"><?php echo esc_html($pgj_c['role']); ?> <?php echo prospergenics_projects_status_badge($pgj_c['status']); // escaped in the helper ?></p>
                </div>
              </div>
              <?php if ('' !== $pgj_c['contribution']) : ?><div class="pgj-contribution"><?php echo wpautop(esc_html($pgj_c['contribution'])); ?></div><?php endif; ?>
              <?php echo prospergenics_projects_evidence_html($pgj_c['evidence']); // escaped in the helper ?>
              <?php
              $pgj_tools = array();
              if (prospergenics_projects_can_confirm($pgj_uid, $pgj_creator, $pgj_u->ID, $pgj_staff) && ! prospergenics_projects_user_suspended($pgj_uid)) {
                  foreach (array('confirmed' => 'Confirm', 'disputed' => 'Dispute', 'claimed' => 'Undo') as $pgj_to => $pgj_label) {
                      if ($pgj_to !== $pgj_c['status']) {
                          $pgj_tools[$pgj_to] = $pgj_label;
                      }
                  }
              }
              ?>
              <?php if ($pgj_tools || ($pgj_staff && (int) $pgj_u->ID !== $pgj_uid)) : ?>
                <div class="pgj-item-tools">
                  <?php foreach ($pgj_tools as $pgj_to => $pgj_label) : ?>
                    <?php prospergenics_projects_form_open('status', '', 'pgj-inline-form'); ?>
                      <input type="hidden" name="project_id" value="<?php echo (int) $pgj_post->ID; ?>" />
                      <input type="hidden" name="user_id" value="<?php echo (int) $pgj_u->ID; ?>" />
                      <input type="hidden" name="status" value="<?php echo esc_attr($pgj_to); ?>" />
                      <button type="submit" class="pgp-btn<?php echo 'disputed' === $pgj_to ? ' pgp-danger' : ''; ?>"><?php echo esc_html($pgj_label); ?><span class="screen-reader-text"> the contribution of <?php echo esc_html($pgj_u->display_name); ?></span></button>
                    </form>
                  <?php endforeach; ?>
                  <?php if ($pgj_staff && (int) $pgj_u->ID !== $pgj_uid) : ?>
                    <?php prospergenics_projects_form_open('leave', '', 'pgj-inline-form'); ?>
                      <input type="hidden" name="project_id" value="<?php echo (int) $pgj_post->ID; ?>" />
                      <input type="hidden" name="user_id" value="<?php echo (int) $pgj_u->ID; ?>" />
                      <button type="submit" class="pgp-link-btn pgp-danger" data-pgj-confirm="<?php echo esc_attr(sprintf('Remove the contribution of %s from this project?', $pgj_u->display_name)); ?>">Remove (staff)<span class="screen-reader-text">: the contribution of <?php echo esc_html($pgj_u->display_name); ?></span></button>
                    </form>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
              <?php if ((int) $pgj_u->ID === $pgj_uid) : ?>
                <p class="pgp-hint"><a href="<?php echo esc_url(prospergenics_profiles_url($pgj_uid, array('edit' => 1)) . '#pgj-item-' . (int) $pgj_post->ID); ?>">Edit your contribution on your profile</a></p>
              <?php elseif ($pgj_staff && prospergenics_projects_is_placeholder($pgj_u->ID)) :
                  $pgj_kept = prospergenics_projects_recall_form($pgj_uid, 'contribution-' . $pgj_post->ID . '-' . $pgj_u->ID); ?>
                <details class="pgj-details" id="<?php echo esc_attr($pgj_cid); ?>"<?php echo $pgj_kept ? ' open' : ''; ?>>
                  <summary class="pgp-link-btn">Edit (unclaimed profile)<span class="screen-reader-text">: <?php echo esc_html($pgj_u->display_name); ?></span></summary>
                  <?php prospergenics_projects_form_open('contribution'); ?>
                    <input type="hidden" name="project_id" value="<?php echo (int) $pgj_post->ID; ?>" />
                    <input type="hidden" name="user_id" value="<?php echo (int) $pgj_u->ID; ?>" />
                    <?php prospergenics_projects_contribution_fields($pgj_cid, $pgj_kept ? $pgj_kept['values'] : $pgj_c, $pgj_kept ? $pgj_kept['errors'] : array(), 'Their contribution'); ?>
                    <?php prospergenics_projects_submit('Save'); ?>
                  </form>
                </details>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ol>
      <?php else : ?>
        <p class="pgp-empty">The people who worked on this share their portfolio with a limited group only.</p>
      <?php endif; ?>
    </section>

    <section class="pgp-section pgj-scrutiny" aria-label="Questions and corrections">
      <?php
      // Card #6389 (scrutiny): "Suggest a correction / Ask a question / Review" and the approved
      // items for this project. Nothing renders until that module exists.
      do_action('prospergenics_scrutiny_actions', 'project', $pgj_post->ID);
      do_action('prospergenics_scrutiny_public', 'project', $pgj_post->ID);
      ?>
    </section>

    <?php if (! $pgj_mine) : ?>
      <section class="pgp-section" id="pgj-add" aria-labelledby="pgj-add-h">
        <h2 id="pgj-add-h">Did you work on this?</h2>
        <?php if (! $pgj_uid) : ?>
          <p><a class="pgp-btn pgp-btn-primary" href="<?php echo esc_url(wp_login_url($pgj_url . '#pgj-add')); ?>">Log in to add your contribution</a></p>
        <?php elseif (! prospergenics_projects_user_can_create($pgj_uid)) : ?>
          <p class="pgp-hint">Confirm your email address to add your contribution (see the banner at the top of the page).</p>
        <?php else :
            $pgj_kept = prospergenics_projects_recall_form($pgj_uid, 'join-' . $pgj_post->ID); ?>
          <details class="pgj-details" id="<?php echo esc_attr('pgj-join-' . $pgj_post->ID); ?>"<?php echo $pgj_kept ? ' open' : ''; ?>>
            <summary class="pgp-btn">Add your contribution</summary>
            <?php prospergenics_projects_form_open('join'); ?>
              <input type="hidden" name="project_id" value="<?php echo (int) $pgj_post->ID; ?>" />
              <?php prospergenics_projects_contribution_fields('pgj-join-' . $pgj_post->ID, $pgj_kept ? $pgj_kept['values'] : array(), $pgj_kept ? $pgj_kept['errors'] : array()); ?>
              <p class="pgp-hint">It shows on this page and in your portfolio as “claimed” until <?php echo $pgj_creator === $pgj_uid ? 'staff confirm' : 'the person who added the project confirms'; ?> it.</p>
              <?php prospergenics_projects_submit('Add to my portfolio'); ?>
            </form>
          </details>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <?php if ($pgj_can_edit) :
        $pgj_kept = prospergenics_projects_recall_form($pgj_uid, 'project-' . $pgj_post->ID);
        $pgj_vals = $pgj_kept ? $pgj_kept['values'] : array_merge($pgj_fields, array('title' => get_the_title($pgj_post), 'description' => $pgj_post->post_content)); ?>
      <section class="pgp-section" id="pgj-manage" aria-labelledby="pgj-manage-h">
        <h2 id="pgj-manage-h"><?php echo $pgj_creator === $pgj_uid ? 'Manage your project' : 'Manage this project (staff)'; ?></h2>
        <details class="pgj-details" id="pgj-edit"<?php echo ($pgj_kept || $pgj_editing) ? ' open' : ''; ?>>
          <summary class="pgp-btn">Edit the project</summary>
          <?php prospergenics_projects_form_open('update'); ?>
            <input type="hidden" name="project_id" value="<?php echo (int) $pgj_post->ID; ?>" />
            <fieldset class="pgj-fieldset"><legend>The <?php echo esc_html(strtolower($pgj_kinds[$pgj_fields['kind']])); ?></legend>
            <?php prospergenics_projects_project_fields('pgj-edit', $pgj_vals, $pgj_kept ? $pgj_kept['errors'] : array()); ?>
            </fieldset>
            <?php prospergenics_projects_submit('Save the project'); ?>
          </form>
          <?php if (function_exists('prospergenics_profiles_skills_datalist')) { prospergenics_profiles_skills_datalist(); } ?>
        </details>
        <?php if ($pgj_staff) :
            $pgj_ph = array_filter(prospergenics_projects_placeholders(), function ($u) use ($pgj_all) { return prospergenics_projects_find_contribution($pgj_all, $u->ID) < 0; });
            if ($pgj_ph) : ?>
          <details class="pgj-details" id="pgj-placeholder-add">
            <summary class="pgp-btn">Add the contribution of an unclaimed profile</summary>
            <?php prospergenics_projects_form_open('contribution'); ?>
              <input type="hidden" name="project_id" value="<?php echo (int) $pgj_post->ID; ?>" />
              <?php prospergenics_projects_field('pgj-ph-user', 'user_id', 'Unclaimed profile', '', 'select', array('options' => array_column(array_map(function ($u) { return array($u->ID, $u->display_name . ' (' . $u->user_email . ')'); }, $pgj_ph), 1, 0))); ?>
              <?php prospergenics_projects_contribution_fields('pgj-ph', array(), array(), 'Their contribution'); ?>
              <?php prospergenics_projects_submit('Add'); ?>
            </form>
          </details>
        <?php endif; endif; ?>
        <div class="pgj-manage-row">
          <?php if ($pgj_can_delete) : ?>
            <?php prospergenics_projects_form_open('delete', '', 'pgj-inline-form'); ?>
              <input type="hidden" name="project_id" value="<?php echo (int) $pgj_post->ID; ?>" />
              <button type="submit" class="pgp-btn pgp-danger" data-pgj-confirm="<?php echo esc_attr(sprintf('Delete “%s” for good?', get_the_title($pgj_post))); ?>">Delete the project</button>
            </form>
          <?php elseif ($pgj_creator === $pgj_uid) : ?>
            <p class="pgp-hint">Other members added their contribution, so the project stays. You can remove your own contribution on your profile.</p>
          <?php endif; ?>
          <?php if ($pgj_staff) :
              $pgj_hidden = prospergenics_projects_is_hidden($pgj_post->ID); ?>
            <?php prospergenics_projects_form_open('hide', '', 'pgj-inline-form'); ?>
              <input type="hidden" name="project_id" value="<?php echo (int) $pgj_post->ID; ?>" />
              <input type="hidden" name="hidden" value="<?php echo $pgj_hidden ? '0' : '1'; ?>" />
              <button type="submit" class="pgp-btn"><?php echo $pgj_hidden ? 'Show the project again' : 'Hide the project'; ?></button>
            </form>
          <?php endif; ?>
        </div>
      </section>
    <?php endif; ?>
  </div>
</div>
<?php get_footer(); ?>

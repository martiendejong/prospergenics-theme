<?php
/**
 * Prospergenics Platform: projects track (card #6388 "Member portfolios and evidence-linked
 * expertise", and the portfolio part of #6467). Loader for this track's modules in inc/projects/.
 * See docs/PLATFORM-BUILD.md and the shared contract for #6388/#6389/#6393.
 *
 *   helpers.php  pure rules: kinds, statuses, validation (https-only evidence), the role matrix
 *   data.php     the pg_project post type, contributions, the contract API
 *                (prospergenics_projects_for_user / _contributors / _url), /wp/v2 safe schema
 *   forms.php    the manual forms (admin-post.php): add, join, edit, leave, confirm, delete, hide
 *   render.php   the "Portfolio" section on /in/<slug>/ and the project page pieces
 *   routes.php   /projects/ (filters by kind and skill), /projects/<slug>/, assets, titles
 *   seo.php      robots, canonical, sitemap (indexable projects only), schema.org
 *   privacy.php  exporter, eraser, deleted-user clean-up
 *   claim.php    staff-created placeholder profiles that the person claims by email
 *
 * Templates: archive-pg_project.php and single-pg_project.php in the theme root. Styles and the
 * confirm script: assets/platform/projects.css|js, on project pages and member profiles only.
 * Card #6389 (scrutiny) renders into the actions prospergenics_scrutiny_actions and
 * prospergenics_scrutiny_public, which the project page and every portfolio entry fire.
 */

defined( 'ABSPATH' ) || exit;

foreach ( array( 'helpers', 'data', 'forms', 'render', 'routes', 'seo', 'privacy', 'claim' ) as $prospergenics_projects_module ) {
    require __DIR__ . '/projects/' . $prospergenics_projects_module . '.php';
}
unset( $prospergenics_projects_module );

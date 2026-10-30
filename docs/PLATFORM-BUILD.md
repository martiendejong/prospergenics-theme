# Building the Prospergenics Platform features

The JengoWork board **"Prospergenics Platform"** (https://tasks.prospergenics.com/board/H4fwk0tStQ)
lists the platform features for prospergenics.com. They are built inside the `prospergenics-2026`
theme (card #6343: "keep within the theme"), in three independent tracks:

| Track | Cards | URL space |
|---|---|---|
| Articles | #6365–#6370 | `/articles/` (existing blog posts stay at `/blog/`) |
| Profiles | #6339–#6340, #6343–#6354 | `/in/{username}/` (the curated `/people/` pages stay as they are) |
| Courses | #6355–#6364 | `/courses/` (replaces the jengo-listings courses page, decided 2026-10-25) |
| Projects | #6388 (portfolios), #6467 | `/projects/`, and the "Portfolio" section on `/in/{username}/` |

Blocked for now: IAM login and registration (#6337, #6338, #6341, #6342: need jengo-iam), companies
and AI tools (#6371–#6377: need the jengo-listings repo), search (#6378–#6380: needs a decision on
SQLite FTS5 vs MySQL), and the epics #6388, #6389, #6393.

## Where code goes

Edit the design source, never the generated theme.

- `design/new-hero-2026-10/wp/inc/platform-<track>.php`: the track's loader, already required from
  `wp/functions.php`. Require your modules from here.
- `design/new-hero-2026-10/wp/inc/<track>/*.php`: the track's modules.
- `design/new-hero-2026-10/wp/<template>.php`: templates, using WordPress hierarchy names that only
  your track uses (`single-pg_article.php`, `archive-pg_course.php`, …). Use `get_header()` /
  `get_footer()` and the existing classes in `wp/header.php` (`.post-hero`, `.post-wrap`,
  `.list-wrap`, `.post-card`, …).
- `design/new-hero-2026-10/assets/platform/<track>.css` (and `.js`): enqueue from your loader, only on
  your own pages.
- `tests/test-platform-<track>*.php`: standalone PHP tests in the repo's style (stub the WordPress
  functions you call, `ok()` helper, exit non-zero on failure).

Then run `python3 design/new-hero-2026-10/build-wp-theme.py` and commit the regenerated
`wp-theme/prospergenics-2026/` too: CI fails when the generated theme is out of date.

**Do not edit shared files** (`wp/functions.php`, `wp/header.php`, `wp/footer.php`,
`wp/inc/seo.php`, `wp/inc/security.php`, `wp/inc/sitemap.php`, `site_chrome.py`, `index.html`,
`about/index.html`, other tracks' files). If your track needs a change there (a menu link, a sitemap
entry, a CSP origin), describe it in your report and it will be integrated.

## Naming

- Functions: `prospergenics_<track>_*`. Classes: `Prospergenics_<Track>_*`.
- Post types and taxonomies: `pg_` prefix (`pg_article`, `pg_course`, `pg_module`, `pg_lesson`,
  `pg_experience`, `pg_education`, `pg_skill`, `pg_series`, …).
- REST routes: namespace `prospergenics/v1`, path starting with the track (`/articles/…`,
  `/profile/…`, `/courses/…`).
- Meta and option keys: `_pg_<track>_*`.

## Rules

- Security: every REST route has a real `permission_callback`; every form and state-changing request
  checks a nonce and a capability; sanitise all input, escape all output; `$wpdb->prepare()` for any
  SQL. Prefer post meta / user meta / CPTs over custom tables for the MVP.
- Fire `do_action( 'prospergenics_platform_content_saved', $post_id, $type )` when your track's
  content is saved, so the future search index (#6378) can attach without touching your code.
- Accessibility: labelled form controls, keyboard-operable UI, visible focus, WCAG AA contrast.
- Works at 390 px and 1440 px without horizontal scrolling.

## Testing

1. `php -l` on every file you add, and every `tests/*.php` must pass.
2. Check it in a real WordPress. A local WordPress 6.8 on SQLite with the theme installed lives at
   `/tmp/claude-0/-home-user-prospergenics-theme/0e4d5320-1a3c-51a8-95d9-6a3dbb7fc7ab/scratchpad/wp/`
   (`site/` + `wp-cli.phar`). Copy `site/` to your own folder, set `WP_HOME`/`WP_SITEURL` in its
   `wp-config.php` to your port (articles 8081, profiles 8082, courses 8083), copy your built theme
   into `wp-content/themes/prospergenics-2026/`, and run `php -S localhost:<port> router.php`.
   Playwright and axe-core are available (see `scratchpad/audit/`).
3. Note what you verified in your report.

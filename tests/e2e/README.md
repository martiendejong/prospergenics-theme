# Role/permission matrix against a real WordPress

`tests/*.php` stub WordPress, so they never exercise real roles, capabilities or REST permission
callbacks. This folder does (audit ARC-18). CI runs it on every pull request as the
`wordpress-roles` job in `.github/workflows/ci.yml`.

```bash
bash tests/e2e/run.sh                         # http://localhost:8096, work dir /tmp/pg-e2e
E2E_PORT=8097 E2E_DIR=~/pg-e2e bash tests/e2e/run.sh
```

Needs `php` 8.x with `pdo_sqlite`, `node` 18+, `curl`, `unzip` and `tar`. Nothing else: no MySQL,
no Docker, no npm packages.

| File | What it does |
|---|---|
| `run.sh` | Downloads pinned WordPress 6.8.3, SQLite Database Integration 3.1.1 and WP-CLI 2.11.0 (SHA-256 checked, cached in `$E2E_CACHE`), installs WordPress on SQLite, copies in the built theme `wp-theme/prospergenics-2026` and the static `people/`, `experts/`, `community/` pages, activates the theme, seeds, starts `php -S`, runs the matrix, and fails on any PHP fatal error in the debug log. |
| `seed.php` | `wp eval-file` script: one user per role (editor = instructor, author, contributor, two verified subscribers, one unverified), a published course with one lesson, an article, a contact message and a profile entry. Prints the IDs as JSON for the matrix. |
| `role-matrix.js` | Logs in as each user (plain HTTP with a cookie jar) and checks the rules below. Exit code 1 on any failure. |
| `router.php` | Router for `php -S`. |

## Rules checked

Each rule has refusal checks and a positive control, so a broken login or an empty seed cannot make
it pass.

- Contact messages (`edit.php?post_type=pg_contact_msg` and the edit screen): refused for
  contributor, author and subscriber, and the message subject never appears; editor and admin see it.
- Profile entries list (`edit.php?post_type=pg_experience`): refused for everyone but administrators.
- `/courses/instructor/<id>/` for a subscriber, contributor or author: the page must not carry the
  person's name (title or body); the real instructor's page does.
- A subscriber (verified or not) opening `/wp-admin/index.php` is redirected (302) to `/in/<slug>/`;
  a contributor keeps the dashboard.
- `PATCH`/`DELETE prospergenics/v1/profile/experience/<id>` on another member's entry: 403 for every
  role, 401 for a visitor; the owner's own `PATCH` succeeds.
- `POST prospergenics/v1/courses/lessons/<id>/completion` before the lesson was opened: 403
  `pg_courses_not_opened`; after opening the lesson page it succeeds.
- Member privacy: a profile whose owner has not finished onboarding is not shown to visitors (a
  verified member does see it); the member's email address is on neither the `/in/<slug>/` page nor
  `prospergenics/v1/profile/users/<slug>` for a visitor, a member or an author.
- `/profile/settings/`: a visitor is sent to the login page; the email, password and delete-account
  forms are refused (403) without their nonce, and the password still works afterwards.
- `POST prospergenics/v1/courses` refused for visitor, subscriber, contributor, author; an unverified
  member cannot follow.

## Adding a rule

Add a block to `role-matrix.js` with `check(rule, who, pass, detail)`: one refusal per role that
must be kept out, and one control for the role that must get in. If it needs content, create it in
`seed.php` (look up by title first, so the seed stays idempotent) and add its ID to the JSON.

To see a rule fail, break it in the throwaway site, for example
`php $E2E_CACHE/wp-cli-2.11.0.phar --allow-root --path=$E2E_DIR/site cap add contributor edit_pg_contact_msgs`,
then run the matrix again with the site served (`E2E_BASE=http://localhost:8096 E2E_FIXTURES=$E2E_DIR/fixtures.json node tests/e2e/role-matrix.js`).
Restart `php -S` after editing theme files in the site: OPcache is on for the built-in server.

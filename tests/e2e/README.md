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
| `mu-plugin.php` | Copied into the throwaway site's `mu-plugins/` only (with `PG_E2E` set in its `wp-config.php`): `admin-ajax.php?action=pg_e2e_nonce&for=<action>` hands the logged-in user a valid nonce, so a refusal proves the capability check behind `check_admin_referer()`, not just the nonce; and `wp_mail()` succeeds without sending. Never part of the theme. |

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
- The Writer role (`pg_writer`): creates a draft article and submits it for review (controls), but
  cannot publish it (REST, or My articles' publish action with a valid nonce), cannot edit or
  unpublish someone else's article, uploads an image (control) and sees only its own uploads (REST
  media list, the media modal, Media screen), cannot edit someone else's image, and is refused the
  contact messages and Users screens.
- Users → Requests: the screen is refused for everyone without `promote_users` (editor, author,
  contributor, writer, members); approving without the nonce, or with a valid nonce but without
  `promote_users` (editor, a member, the applicant themself), is 403. Controls: the admin sees both
  open requests, approves "write" (the applicant can then open the article editor) and declines
  "teach".
- Users screen row actions (Resend, Mark verified, Hide/Show): `users.php` refused for editor, author,
  members; the handler refuses a missing nonce (even for the admin), and a valid nonce without
  `edit_users` + `edit_user` (editor, author, writer, members), including members acting on
  themselves (an unverified member marking themselves verified, a hidden member showing their own
  profile). Controls: the admin resends the link, marks the member verified (who can then follow),
  hides the profile (members no longer see it) and shows it again.

## Adding a rule

Add a block to `role-matrix.js` with `check(rule, who, pass, detail)`: one refusal per role that
must be kept out, and one control for the role that must get in. If it needs content, create it in
`seed.php` (look up by title first, so the seed stays idempotent) and add its ID to the JSON.

To see a rule fail, break it in the throwaway site, for example
`php $E2E_CACHE/wp-cli-2.11.0.phar --allow-root --path=$E2E_DIR/site cap add contributor edit_pg_contact_msgs`,
then run the matrix again with the site served (`E2E_BASE=http://localhost:8096 E2E_FIXTURES=$E2E_DIR/fixtures.json node tests/e2e/role-matrix.js`).
Restart `php -S` after editing theme files in the site: OPcache is on for the built-in server.

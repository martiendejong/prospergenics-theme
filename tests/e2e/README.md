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
- Portfolios (`inc/projects/`, card #6388): a contribution in a private portfolio is on neither the
  project page nor `/wp/v2/pg_project` (item and list, no `author` field) nor
  `prospergenics/v1/profile/users/<slug>` for a visitor, a member or an author; the member sees it
  (control). The manual forms refuse a missing nonce and visitors; with a valid nonce, editing
  someone else's contribution is refused for the project creator, an author and an editor, a
  contributor cannot confirm, edit, delete or hide the project, the creator cannot confirm their own
  contribution or delete a project others contributed to, an unverified member cannot add to a
  portfolio, and REST writes to `pg_project` are refused for subscriber and author. Controls: the
  member edits their own contribution (an http evidence link is refused), the creator confirms
  another's, staff edit the project and hide it (404, gone from REST) and show it again, a verified
  author adds a project.

- Scrutiny (card #6389, `inc/profiles/scrutiny*.php`): the Scrutiny tab of Reports, which shows the
  staff-only audit log (the submitter's email address and IP hash), is refused for visitors,
  subscribers, contributors, authors and writers (the editor sees it: control); no item and no
  submitter email in REST (`/wp/v2/pg_scrutiny`, search), feeds, `?post_type=`, `?p=` or site
  search; on the profile a member sees the published item but not the pending one. Approving with a
  valid nonce but without `pg_moderate` is 403 for every member role, and without the nonce for the
  editor; the editor then approves (control). Responding: a visitor is sent to log in, members who
  are not concerned and staff get 403, and the person concerned responds once (control).

- The staff area `/manage/` (card #6457): every screen sends a visitor to the login page (302) and is
  403 for subscriber, unverified member, writer, contributor and author, without showing member,
  message or article data; editor and admin get every screen and a "Manage" account link (members
  do not). Every member action (role, verify, resend, hide, unhide, suspend, unsuspend) and message
  action (read, handled) is refused for subscriber, writer, contributor and author with and without
  a valid nonce, and for the admin without one; the member is unchanged afterwards. Editors cannot
  act on the administrator, another editor or themselves, nor make anyone contributor, author,
  editor or administrator; nobody grants administrator or changes their own role there. Controls:
  the editor makes a member Writer and back, resends the link, marks them verified (who can then
  follow), hides and shows the profile, suspends (login refused, old cookie dead) and unsuspends;
  the admin makes a member Author and changes an editor; the editor marks a message handled, the
  admin reopens it, and opening a message from the inbox marks it read. Publishing someone else's
  pending article from the review queue is refused for contributor, author and writer even with a
  valid nonce; the editor publishes it and returns to `/manage/articles/`.

- Member badges (`inc/profiles/rank*.php`, `badges.php`): the seeded "badger" has a public profile
  and a confirmed contribution in a private portfolio. A visitor sees the Member tier next to her
  name, never Contributor, and the role is not on the page; the visitor, another member and an
  editor never see the score or breakdown (page or `prospergenics/v1/profile/users/<slug>`, whose
  `badge` is `{tier, label}` only), nor does the owner's "what visitors see" preview. Controls: the
  owner sees her score, breakdown and the "not public" tip; once she makes the portfolio public
  (the settings form, with its nonce) she is a Contributor on her profile, in REST, on the project
  page, on `/find/` and under `/manage/members/?tier=contributor` (editor); private again, Member
  again. The tier filter is 403 for subscriber, author and the member herself; a privacy change
  without the nonce is refused. The course instructor and article byline carry a badge, `/badges/`
  lists every tier and the points, the badge links to its tier there, and an unverified member
  shows none.

## Card #6393 acceptance walk-through (Playwright, not in CI)

`expertise-acceptance.js` walks the card's acceptance in a real browser, through the site's own
forms: sign in (and set the profile to "Everyone"), add project (`pg_projects_create` on the
profile), approve (staff confirm the contribution, `pg_projects_status`), publish (the project page
and `/wp/v2/pg_project` for a visitor), outsider correction (another member sends a correction about
the person and evidence about the project, `pg_scrutiny_submit`), review (staff approve both in
Reports → Scrutiny, `pg_scrutiny_decide`), search (`/find/` names the confirmed project and the
approved evidence, and every evidence link and anchor resolves), plus the staging gates (one
canonical, an ItemList, noindex off production). Precondition: both members have confirmed email
addresses (`wp user meta delete <id> _pg_profiles_verify_pending`). It needs Playwright with
Chromium, which the CI job does not install, so run it by hand:

```bash
E2E_BASE=http://localhost:8105 node tests/e2e/expertise-acceptance.js
```

Users, query and the names that must never appear are set with `E2E_MEMBER`, `E2E_OUTSIDER`,
`E2E_ADMIN`, `E2E_QUERY` and `E2E_HIDDEN` (see the file header).

## Adding a rule

Add a block to `role-matrix.js` with `check(rule, who, pass, detail)`: one refusal per role that
must be kept out, and one control for the role that must get in. If it needs content, create it in
`seed.php` (look up by title first, so the seed stays idempotent) and add its ID to the JSON.

To see a rule fail, break it in the throwaway site, for example
`php $E2E_CACHE/wp-cli-2.11.0.phar --allow-root --path=$E2E_DIR/site cap add contributor edit_pg_contact_msgs`,
then run the matrix again with the site served (`E2E_BASE=http://localhost:8096 E2E_FIXTURES=$E2E_DIR/fixtures.json node tests/e2e/role-matrix.js`).
Restart `php -S` after editing theme files in the site: OPcache is on for the built-in server.

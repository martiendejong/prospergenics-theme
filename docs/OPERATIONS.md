# Operations: backups, monitoring, alerts

What keeps test.prospergenics.com (and later prospergenics.com) recoverable and watched, what is
automated in this repository, and what the hosting (Vimexx, DirectAdmin, server web0168.zxcs.nl)
and the owner still have to provide. Card 6473, audit ARC-17 / ARC-18.

## What runs where

| What | Where | When |
|---|---|---|
| Lint, unit tests, generated-files check | `.github/workflows/ci.yml`, job `test` | every PR, pushes to `master` and `design/new-hero-2026-10` |
| Role/permission matrix against a real WordPress | `ci.yml`, job `wordpress-roles` (`tests/e2e/`) | same |
| Deploy to test (backup, upload, prune, smoke check) | `.github/workflows/deploy-test.yml` | every push to `design/new-hero-2026-10` |
| Uptime check of test | `.github/workflows/uptime.yml` (`tests/smoke/uptime-check.sh`) | every 30 minutes |
| Production deploy (gate, approval, database + uploads + theme backup, upload, smoke check) | `.github/workflows/deploy-production.yml` | by hand: *Run workflow*, then a reviewer approves |

### How a deploy reaches each site (card #7232)

| | test.prospergenics.com | prospergenics.com |
|---|---|---|
| Built from | `design/new-hero-2026-10` (`build-wp-theme.py` → `wp-theme/prospergenics-2026/`) | the same commit, once it passed on test |
| Started by | every push to `design/new-hero-2026-10` (`deploy-test.yml`), or by hand | *Run workflow* on `deploy-production.yml` (until 19 Nov: by hand from Martien's machine) |
| Checks first | the PHP and deploy tests; `deploy_guard.py` (newest branch, never older than the server) | `prod_gate.py`: confirmation, on the branch, **CI green on exactly that commit**, **already on test**, production not newer; then **a reviewer's approval** (Environment `production`) |
| Upload | `deploy-wp-theme-vimexx.py`: FTPS to web0168.zxcs.nl, `domains/test.prospergenics.com/public_html` | the same script with `--target production` → `domains/prospergenics.com/public_html` |
| Backup before | the live theme (artifact `theme-backup-<sha>`, 30 days) | **database** and **uploads** (encrypted artifacts `production-db-<sha>`, `production-uploads-<sha>`) and the live theme (`production-theme-backup-<sha>`), all 30 days |
| After | smoke check, alert issue *Deploy to test failing* | smoke check compared with the one before the upload; alert issue *Deploy to production failing* |
| Rollback | the theme artifact, `--theme-dir` (below) | the same with `--target production --confirm prospergenics.com`; database: import the dump |
| Says what runs | `/version.json` | `/version.json` (`"run"` is the workflow run; `null` = deployed by hand) |

What `/version.json` showed on 19 Nov: both sites run `f59324f` (deployed 17 Nov 03:55 and 03:57 UTC,
`"run": null`, so by hand), and CI **failed** on that commit (the site-chrome and scroll-fade sync tests;
the push-triggered *Deploy to test* run refused to upload it for the same reason). The production gate
stops exactly that.

## Deploying safely (read this before you upload anything)

On 4 Nov 2026 test.prospergenics.com was overwritten with the theme of **24 Oct**: a deploy ran by hand
from a branch that did not contain the 110 commits of `design/new-hero-2026-10`, so the platform, the
people pages and every fix since 24 Oct disappeared from the site (the database was untouched).

- **The test site is built from `design/new-hero-2026-10`.** Change it with a pull request against
  that branch; merging deploys automatically (`.github/workflows/deploy-test.yml`), with a backup first
  and a smoke check after.
- **`develop` and `master`** follow it: `develop` is brought up to date from `design/new-hero-2026-10`,
  and `master` gets it through the launch PR (#28). Start new branches from `design/new-hero-2026-10`
  (or an up-to-date `develop`), never from an older commit.
- **Deploying by hand** (`deploy-wp-theme-vimexx.py` for the Vimexx host, `deploy-wp-theme-to-test.py`
  for the IIS server) now stops before uploading when your checkout does not contain the latest
  `origin/design/new-hero-2026-10`, or when the server already runs a newer commit (`deploy_guard.py`;
  the Vimexx deploy reads the commit from its manifest, the IIS deploy from `.deployed-commit` in the
  theme folder). `--allow-older` is for a deliberate rollback only.
- **Production** goes through `.github/workflows/deploy-production.yml` (next section). The deploy script
  refuses a prospergenics.com web root unless it runs with `--target production --confirm prospergenics.com`,
  which adds the production checks, so the test command cannot reach production by accident.
- **One host at a time.** test.prospergenics.com is served by whichever origin Cloudflare points at.
  The GitHub deploy uploads to the Vimexx host; switching Cloudflare to another server (such as the
  IIS server) makes the site show that server's copy instead. Agree on the host before switching.

## Deploying to production

*Actions → Deploy to production → Run workflow*, from the branch `design/new-hero-2026-10`:

| Input | |
|---|---|
| `confirm` | type `prospergenics.com` |
| `commit` | the full SHA to deploy; empty = what test runs now (its `/version.json`) |
| `dry_run` | on by default: takes the **real** backups and logs what the upload would do, uploads nothing. Untick to deploy |
| `db_backup` | `automatic`; `taken-by-hand` only for the first deploy, while production's theme has no backup endpoint yet (below) |
| `allow_older` | a deliberate rollback to a commit older than production's |

1. **Gate** (`prod_gate.py`, no secrets): stops unless the confirmation is right, the commit is part of
   `design/new-hero-2026-10`, `ci.yml` passed on exactly that commit, test runs it (or a push-triggered
   *Deploy to test* run uploaded it and passed its smoke check), and production does not run newer work.
   The run summary lists what the reviewer is about to approve.
2. **Approval**: the `deploy` job runs in the GitHub Environment `production`; GitHub waits for a
   required reviewer, and only then releases the production secrets.
3. **Backups, before anything changes**: the database (below), `wp-content/uploads/`, both encrypted for
   `BACKUP_GPG_PUBLIC_KEY` and kept as artifacts for 30 days.
4. **Baseline smoke check** of production, then the upload (`deploy-wp-theme-vimexx.py --target production`,
   which downloads the live theme first: artifact `production-theme-backup-<sha>`), then the smoke check
   again. `tests/smoke/smoke-compare.sh` fails the run on any check that passed before the upload and fails
   after it; checks production already failed (on 19 Nov: `/contact/` → 301 to `/#contact`, `/members/` 404,
   no public `/in/` profile yet) are listed as known. A failure opens the issue *Deploy to production failing*.

**Rollback.** Theme: download `production-theme-backup-<sha>`, then
`python3 design/new-hero-2026-10/deploy-wp-theme-vimexx.py --target production --confirm prospergenics.com --no-build --theme-only --theme-dir <unzipped>/<time>/prospergenics-2026`
(by hand, with the production FTPS login). Database: decrypt the dump (`gpg -d production-db-….sql.gz.gpg > db.sql.gz`)
and import it (phpMyAdmin → *Import*, or `wp db import`). The uploads archive unpacks into `wp-content/`.

**By hand** (Martien's machine, only if GitHub is unavailable): `deploy-wp-theme-vimexx.py --target production
--confirm prospergenics.com` runs the same gate itself (through `gh` or `GH_TOKEN`), needs a clean
working tree and always backs the theme up. Take the database export by hand first (Backups → How, step 4).

**Setup, once** (repository admin): *Settings → Environments → New environment* `production` with
**Required reviewers** and *Deployment branches* = `design/new-hero-2026-10`; environment secrets
`PROD_FTP_USERNAME`, `PROD_FTP_PASSWORD` (the FTPS login that reaches `domains/prospergenics.com/`) and
`PROD_BACKUP_KEY`; repository variable `BACKUP_GPG_PUBLIC_KEY`; optional variables `PROD_FTP_ROOT`,
`PROD_FTP_HOST`, `PRODUCTION_SMOKE_MEMBER_PROFILE`. Details at the top of `deploy-production.yml`.

## Backups

### Database backup on request (cards #7232, #6473)

The deploy only reaches the host over FTPS, which cannot run `mysqldump`. The theme module
`inc/ops-backup.php` closes that gap: a POST to `/wp-json/prospergenics/v1/ops/db-backup`, signed with
`PROSPERGENICS_BACKUP_KEY`, makes WordPress write a gzipped SQL dump of the site's tables to
`<site folder>/deploy/db-backups/`, **next to** `public_html` (never web-served), and answer with its name,
size and SHA-256 only. `design/new-hero-2026-10/backup-site.py --db` then downloads it over FTPS, checks
the hash and deletes it from the host; `--uploads` downloads `wp-content/uploads/` into one archive;
`--encrypt-to <public key>` encrypts both (the workflows always do: this repository is public, so its
artifacts are visible to anyone with a GitHub account).

- Off until `wp-config.php` defines the key: `define( 'PROSPERGENICS_BACKUP_KEY', '<64 random hex characters>' );`
  (`python3 -c "import secrets; print(secrets.token_hex(32))"`), and the same value is the `PROD_BACKUP_KEY`
  secret. Without it the route does not exist.
- The signature covers the site's host, the time (5 minutes), a nonce and dry-run or not; each request
  works once; one dump per 2 minutes; the newest 3 dumps stay on the host.
- Restore: `gpg -d` the artifact, then phpMyAdmin → *Import* (or `wp db import`). Tested on 19 Nov on a
  local copy of the test site: every table dropped, the dump replayed, all 578 rows back, posts, users and
  their meta identical.
- Limits: the dump runs inside one web request, and Cloudflare cuts a request off after 100 seconds; that
  is plenty for this site's database today, not for a very large one (then use the DirectAdmin backup).
  The first production deploy has no endpoint yet (production runs an older theme): choose
  `db_backup: taken-by-hand` after a DirectAdmin/phpMyAdmin export; every deploy after it is automatic.

### What to back up

| Item | Where it lives | Covered by |
|---|---|---|
| Database (posts, courses, articles, users, profiles, contact messages, settings) | MySQL on the host | before every production deploy (artifact `production-db-<sha>`, encrypted, 30 days, once `PROSPERGENICS_BACKUP_KEY` is set); host backup for the daily copy (see below) |
| `wp-content/uploads/` (profile photos, featured media, course thumbnails) | host disk | before every production deploy (artifact `production-uploads-<sha>`, encrypted, 30 days); host backup for the daily copy |
| `wp-config.php` (DB login, salts, SMTP settings) | host disk | host backup; keep a copy in the vault |
| Theme `prospergenics-2026` | git + host | git, plus a download of the live theme before every deploy (artifact `theme-backup-<sha>`, 30 days) |
| Static pages `people/`, `experts/`, `community/`, `team/` | git + host | git (rebuilt and re-uploaded on every deploy) |
| Plugins (jengo-listings, SQLite/MySQL drop-ins, SMTP) | host disk | host backup; versions noted in the plugin list |

The database and uploads change every day once members sign up; the theme only changes with a
deploy. Losing the database loses every account, profile, enrolment and certificate.

### How (database and uploads: set up by a person at the host, card #6473)

The repository cannot do this part: the Vimexx/DirectAdmin account and Cloudflare are not
reachable from GitHub or from the agents, and there are no host credentials in the repository.
The target is **daily, 30 days kept, a copy off the host, a failure noticed, a restore tested**.

1. **Daily backups at the host.** DirectAdmin → *Create/Restore Backups* (or *Backup settings* in
   the Vimexx panel): check that the package makes an automatic backup **every day** that includes
   the **database and the files** (`public_html/`, which holds `wp-content/uploads/`), and how long
   Vimexx keeps them. If it is not daily or not 30 days, add a DirectAdmin scheduled backup:
   *Create/Restore Backups* → *Schedule* → daily at 03:15 → tick *Domains* (files) and
   *Databases* → save.
2. **A copy off the host, kept 30 days.** A backup only on the web host dies with the host or the
   account. Pick one target and set it up once:
   - DirectAdmin *Create/Restore Backups* → *Backup to FTP/remote* to a storage box (Hetzner
     Storage Box, the Jengo dev server, or another FTP/SFTP target), daily; or
   - a backup plugin (UpdraftPlus free): *Settings → UpdraftPlus Backups → Settings* → files and
     database **daily**, retain **30** of each, remote storage (Google Drive, Dropbox or S3 on the
     team account), *Email* report to the team address so a failed run is noticed.
   Delete local archives older than 30 days so the hosting quota does not fill up.
3. **Failure notice.** Whichever runs the backup must email the team address on failure
   (DirectAdmin sends the scheduled backup report to the account email; UpdraftPlus has the
   *Email* option). Check once a month that the newest set on the off-host target is less than
   24 h old and that there are 30 daily sets.
4. **Database export before a risky change** (a migration, a plugin update, a production deploy):
   DirectAdmin → *MySQL Management* → the WordPress database → *Download* (or phpMyAdmin →
   *Export* → SQL). With SSH or WP-CLI access on the host the same in one line:
   `wp db export ~/backups/db-$(date -u +%Y%m%dT%H%M%SZ).sql.gz` (or `mysqldump
   --single-transaction --default-character-set=utf8mb4 -u <user> -p <db> | gzip > …`, the login is in
   `wp-config.php`). A production deploy does this itself through the backup endpoint (above).
5. **Theme**: automatic. Every deploy downloads the live theme first
   (`deploy-wp-theme-vimexx.py`, artifact `theme-backup-<sha>` in the Actions run, 30 days). Restore:
   `python3 design/new-hero-2026-10/deploy-wp-theme-vimexx.py --no-build --theme-only --theme-dir <unzipped artifact>/<time>/prospergenics-2026`.
   With `--switch` the previous three themes are also kept on the server, outside the web root, in
   `domains/<site>/deploy/prospergenics-2026-prev-<time>/`.

### Restore test (before launch, then every quarter)

A backup that was never restored is a hope, not a backup. Do it on a separate subdomain (for
example `restore.prospergenics.com`) rather than over the test site, so a bad restore breaks nothing.

1. Take the newest off-host set: the database dump, `wp-content/uploads/` and `wp-config.php`.
2. DirectAdmin → *Subdomain Management* → create the subdomain; *MySQL Management* → a new empty
   database and user. Upload WordPress (same version as test) to the subdomain's folder, copy
   `wp-content/plugins/`, `wp-content/themes/prospergenics-2026/` and `wp-content/uploads/` from
   the backup, and the static folders `people/`, `experts/`, `community/`.
3. Import the dump: phpMyAdmin → the new database → *Import* (or `wp db import dump.sql`).
4. In the restored `wp-config.php` set the new database name, user and password, and
   `define( 'WP_HOME', 'https://restore.prospergenics.com' ); define( 'WP_SITEURL', 'https://restore.prospergenics.com' );`
   then `wp search-replace 'https://test.prospergenics.com' 'https://restore.prospergenics.com' --all-tables`
   (or the *Better Search Replace* plugin without SSH).
5. Check, and note each result:
   - log in as an administrator and as a member (`/wp-login.php`), and open `/profile/`;
   - a course page (`/courses/`, then one course and one lesson);
   - an uploaded image: a profile photo on `/in/<slug>/` or a featured image, and its
     `/wp-content/uploads/...` URL answers 200;
   - `/manage/` and wp-admin → Contact messages show the same counts as on test;
   - `bash tests/smoke/uptime-check.sh https://restore.prospergenics.com` prints four `ok` lines.
6. Write the date, the backup used, the time it took and the result in the log at the bottom of
   this file. Then delete the subdomain and its database (they hold member data).

## Monitoring and alerts

- **Uptime** (`uptime.yml`, every 30 minutes): `/`, `/courses/`, `/articles/` and `/wp-login.php` on
  test.prospergenics.com must answer 200 or 302 and show no PHP fatal error, critical-error page
  or database-connection error. Each URL gets three tries 20 s apart. Any failure fails the run
  and opens the alert issue (see "Where alerts go").
- **After every deploy** (`deploy-test.yml`): `tests/smoke/smoke-check.sh` checks the static pages,
  the platform routes (`/courses/`, `/articles/`, `/profile/` → 302 to login, the
  `prospergenics/v1/courses` JSON), a public member profile (`/in/martien-de-jong/`,
  `SMOKE_MEMBER_PROFILE`), the `/members/lessy/` → `/people/lessy/` redirect, the value test
  on the homepage and the shared site header on every page. A failed upload or smoke check opens
  the alert issue too.
- **PHP fatal errors** (`inc/ops-alerts.php`): every fatal error after the theme has loaded, on
  any page, and every failed `wp_mail()` go into a short site log. Administrators see new fatal
  errors as a red notice in wp-admin (until *Dismiss*) and the whole log under **Site errors** on
  `/manage/`. A fatal error is also **emailed, at most once an hour**, to `PROSPERGENICS_ALERT_EMAIL`
  from `wp-config.php`, else `RECOVERY_MODE_EMAIL`, else the *Administration Email Address*
  (Settings → General). The log and the email hold the message without its stack trace, the file
  and line, and the page path without its query string; passwords, keys and tokens in the message
  are masked. WordPress' own recovery mode adds its own email, but only for errors in wp-admin or
  on the login page. *Site errors* shows each error once, with how many times it happened and
  when it was last seen; entries from the alert test below are labelled *test*. Administrators
  can *Dismiss* one error once it is fixed, *Remove the test entries*, or *Clear all*.
- **Not covered by the theme**: a fatal error before the theme loads (in a plugin or in
  `wp-config.php`), a database that is down, or a host that does not answer. The uptime check
  catches the last two from outside; the PHP error log at the host has the first.

### Where alerts go

- **The alert issue.** A failing uptime check opens the GitHub issue *Uptime check failing*, a
  failed upload or post-deploy smoke check the issue *Deploy to test failing* (label `alert`,
  `tests/smoke/alert-issue.sh`, with the workflow's own token: no extra secret). Later failures
  comment on the open issue instead of opening another; the next passing run closes it. GitHub
  notifies everyone who **watches** the repository (*Watch → All activity*, or *Custom → Issues*),
  by email and in the app. To reach someone who does not watch it, add their GitHub login to the
  repository **variable** `ALERT_ASSIGNEES` (Settings → Secrets and variables → Actions →
  *Variables* → New variable, value `login1,login2`): a new alert issue is assigned to them.
- **The failed run itself.** GitHub also emails a failed run, but only to one person: for
  `uptime.yml` (a scheduled workflow) **the person who last changed the `cron:` line** (or
  re-enabled the workflow); for `deploy-test.yml` and `ci.yml`, the person whose push or PR started
  it. That person needs *Settings → Notifications → GitHub Actions → Email, "Only notify for failed
  workflows"*.
- **PHP fatal errors** go by email to the alert address above, and onto `/manage/`.
- **WhatsApp/SMS** is not built in: it needs a provider account (an external monitor such as
  UptimeRobot or Better Stack on the same four URLs, free tier, which also checks independently of
  GitHub; or Twilio/CallMeBot called from the workflow with its token as a repository secret).

Test the alert once: *Actions → Uptime test.prospergenics.com → Run workflow*, `base_url`
`https://test.prospergenics.com/no-such-site` → the run fails and the issue opens; run it again
with the default URL → the issue closes.

Limits of the GitHub schedule: it only runs from the repository's **default branch (`develop`)**,
so `uptime.yml` has to be merged there before it fires; runs can start several minutes late when
GitHub is busy; and GitHub disables schedules in a public repository after 60 days without commits.

### When an alert arrives

1. Open the site in a private window: `/`, `/courses/`, `/articles/`, `/wp-login.php`. The issue
   or email names the page and the error.
2. **Every page fails** (5xx, timeout, "Error establishing a database connection"): the host or
   the database. Check the Vimexx status page and DirectAdmin (disk quota, MySQL running); open a
   Vimexx support ticket if it is theirs. Cloudflare: check which origin test.prospergenics.com
   points at (see "One host at a time").
3. **One page or a few fail, right after a deploy**: roll back the theme from the run's
   `theme-backup-<sha>` artifact (command under "Backups → How", step 5), then fix forward with a PR.
4. **A PHP fatal error email**: `/manage/` → *Site errors* shows the file and line; the host's PHP
   error log has the full message. A fatal error in a plugin: deactivate it in wp-admin → Plugins
   (WordPress' recovery-mode link in its own email also works).
5. **Failed emails** under *Site errors*: check the SMTP plugin settings and the mailbox password;
   the contact form keeps every message in wp-admin → Contact messages either way.
6. Write one line in the alert issue about the cause and the fix before it closes.

## What the host must provide

- Daily backups of database **and** files, kept 30 days, copied off the host, with a self-service restore.
- A way to export the database from a script (SSH with WP-CLI or `mysqldump`, or a DirectAdmin API
  key) so deploys can take a database backup automatically.
- Access to the PHP error log of the site.
- FTPS with TLS session reuse (current), and permission to rename files (used by every deploy) and
  folders (used by `--switch`).
- Outgoing mail (SMTP) for WordPress' recovery-mode and contact-form emails.

## Still needs the owner

Card #6473 cannot be closed from the repository alone. In this order:

- [ ] Choose the off-host backup target (storage box, Jengo dev server, or cloud storage via
      UpdraftPlus) and the team alert address.
- [ ] Vimexx/DirectAdmin: daily backups of the database **and** the files, kept 30 days, copied
      off the host, with a failure email to the team address (steps 1-3 under "Backups → How").
- [ ] `wp-config.php` on test and production: `define( 'PROSPERGENICS_ALERT_EMAIL', '<team address>' );`
      (and `RECOVERY_MODE_EMAIL` to the same address); check that outgoing mail works (SMTP).
      Then trigger a test fatal on the test site (a temporary file `wp-content/mu-plugins/pg-fatal-test.php`
      with `<?php add_action( 'wp', function () { if ( isset( $_GET['pg_fatal_test'] ) ) { pg_no_such_function(); } } );`,
      open `/?pg_fatal_test=1`, delete the file) and confirm the email and the `/manage/` entry.
- [ ] Merge `uptime.yml` to `develop` (the default branch) so the schedule runs; watch the
      repository or set `ALERT_ASSIGNEES`; run the alert test under "Where alerts go".
- [ ] Do the first restore test and record it below.
- [ ] WhatsApp/SMS alerts, if wanted: choose a provider (external monitor, or Twilio/CallMeBot from the workflow) and add its key as a secret.
- [ ] Production deploys (card #7232): create the Environment `production` with required reviewers and
      its secrets, the `BACKUP_GPG_PUBLIC_KEY` variable, `PROSPERGENICS_BACKUP_KEY` in production's
      `wp-config.php` ("Deploying to production → Setup"); then run the workflow once with `dry_run` on.
- [ ] Check once with the test site that `--switch` works on Vimexx (folder rename over FTPS); if it
      does, it can become the default for production.

## Restore test log

| Date | Backup used | Time taken | Result | By |
|---|---|---|---|---|
| | | | | |

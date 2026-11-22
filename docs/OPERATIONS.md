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
| Uptime check of test **and production** | `.github/workflows/uptime.yml` (`tests/smoke/uptime-check.sh`) | every 30 minutes |
| Daily backup of production's database and uploads, off the host | `.github/workflows/backup-production.yml` (`backup-site.py`) | every night, 02:17 UTC |
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
| Database (posts, courses, articles, users, profiles, contact messages, settings) | MySQL on the host | **every night** (`backup-production.yml`, artifact `production-daily-db-<date>`) and before every production deploy (`production-db-<sha>`): encrypted, off the host, 30 days, once the setup below is done; the host backup as a second layer |
| `wp-content/uploads/` (profile photos, featured media, course thumbnails) | host disk | **every night** (`production-daily-uploads-<date>`; variable `BACKUP_UPLOADS` = `weekly` or `off` to do less) and before every production deploy (`production-uploads-<sha>`); the host backup as a second layer |
| `wp-config.php` (DB login, salts, SMTP settings) | host disk | host backup; keep a copy in the vault |
| Theme `prospergenics-2026` | git + host | git, plus a download of the live theme before every deploy (artifact `theme-backup-<sha>`, 30 days) |
| Static pages `people/`, `experts/`, `community/`, `team/` | git + host | git (rebuilt and re-uploaded on every deploy) |
| Plugins (jengo-listings, SQLite/MySQL drop-ins, SMTP) | host disk | host backup; versions noted in the plugin list |

The database and uploads change every day once members sign up; the theme only changes with a
deploy. Losing the database loses every account, profile, enrolment and certificate.

### How (card #6473)

The target is **daily, 30 days kept, a copy off the host, a failure noticed, a restore tested**.

**In the repository (since 19 Nov):** `.github/workflows/backup-production.yml` backs up production's
database and `wp-content/uploads/` every night with `backup-site.py` (the same as before a deploy),
encrypted for `BACKUP_GPG_PUBLIC_KEY`, as GitHub Actions artifacts kept **30 days**: off the host, on
GitHub. A failed run opens the issue *Daily backup failing* (label `alert`); the next good run closes it.
It needs, once: the Environment `production-backup` (no reviewers, deployment branches `develop` and
`design/new-hero-2026-10`) with the secrets `PROD_FTP_USERNAME`, `PROD_FTP_PASSWORD`, `PROD_BACKUP_KEY`;
the variable `BACKUP_GPG_PUBLIC_KEY`; `PROSPERGENICS_BACKUP_KEY` in production's `wp-config.php`; and a
production theme that has `inc/ops-backup.php` (the first deploy through `deploy-production.yml`). Until
then each run ends with a notice. Check once a month: *Actions → Daily backup of prospergenics.com*
shows a green run for each of the last 30 days; download one and decrypt it (`gpg -d`).

**At the host (a second layer, set up by a person):** the Vimexx/DirectAdmin account is not reachable
from GitHub or the agents. A host backup also covers `wp-config.php`, the plugins and more than 30 days.

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
  test.prospergenics.com **and prospergenics.com** must answer 200 or 302 and show no PHP fatal error,
  critical-error page or database-connection error. Each URL gets three tries 20 s apart. Any failure
  fails that site's row and opens its alert issue (see "Where alerts go"). Production is checked unless
  the repository variable `UPTIME_PRODUCTION` is `off`; `PRODUCTION_URL` changes its address. (19 Nov,
  by hand: all four pages answer 200 on production.)
- **Daily backup** (`backup-production.yml`): a failed night opens the issue *Daily backup failing*.
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

- **The alert issue.** A failing uptime check opens the GitHub issue *Uptime check failing* (test) or
  *Uptime check failing: prospergenics.com* (production), a failed upload or post-deploy smoke check
  *Deploy to test failing* or *Deploy to production failing*, a failed nightly backup *Daily backup
  failing* (label `alert`,
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

Test the alert once: *Actions → Uptime test.prospergenics.com and prospergenics.com → Run workflow*,
`base_url` `https://test.prospergenics.com/no-such-site` (only the test row runs) → the run fails and the
issue opens; run it again with `base_url` empty → the issue closes (done on 8 Nov: issue #84).

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

## Homepage speed (card #7223)

Mobile LCP (Lighthouse, Moto G, slow 4G) is the hero photo, `.hero-top-photo`, on test, production and a
local copy alike. Measured 19 Nov from a cloud machine (its proxy adds some latency): production 2.5–4.6 s,
median 3.4 s; test 2.5–3.2 s, median 3.0 s. The target is 2.5 s.

- **The theme** (since 19 Nov): phones up to 480px load `hero-top-mobile.webp`, a centre crop with the
  same framing (118 KB instead of 220 KB), each hero image is preloaded only for the widths that use it,
  the chat launcher's photo is `fetchpriority="low"` so it does not compete, the homepage's `@font-face`
  rules are inline (no render-blocking `fonts.css`), and `assets/.htaccess` gives theme files browser
  caching. On the same local copy: LCP 4.4 s → 3.6 s, FCP 2.0 s → 1.9 s.
- **The rest is the server's time to the first byte.** The homepage HTML takes 1.7–2.1 s to start
  arriving from production and 0.9–1.3 s from test, a static file 0.26 s: every anonymous visit runs
  WordPress in full, and production is slower than test (plugins, PHP settings). A page cache that answers
  before WordPress loads takes most of that away, which the theme cannot do: a caching plugin at the host
  (LiteSpeed Cache if Vimexx runs LiteSpeed, else WP Super Cache in its mod_rewrite mode), or a Cloudflare
  Cache Rule for HTML that bypasses logged-in visitors (cookies `wordpress_logged_in_*`, `wp-postpass_*`,
  `comment_author_*`) and `/wp-admin/`, `/wp-login.php`, `/wp-json/`, `/profile/`, `/manage/`, `/register/`,
  `/join/`, `?vt=`. The homepage prints no nonce or per-visitor data for a logged-out visitor, so its HTML
  can be cached as it is. Purge the cache after each deploy. (Caching inside the theme at `template_redirect`, as in the
  unmerged PR #85, still loads WordPress and every plugin first: locally that left two thirds of the PHP
  time in place.)
- Replacing an image under the same file name: visitors may keep the old one for a day, Cloudflare for 2
  hours; give a changed image a new name, or purge Cloudflare.

## What the host must provide

- Daily backups of database **and** files, kept 30 days, copied off the host, with a self-service restore.
- A way to export the database from a script (SSH with WP-CLI or `mysqldump`, or a DirectAdmin API
  key) so deploys can take a database backup automatically.
- Access to the PHP error log of the site.
- FTPS with TLS session reuse (current), and permission to rename files (used by every deploy) and
  folders (used by `--switch`).
- Outgoing mail (SMTP) for WordPress' recovery-mode and contact-form emails.

## Still needs the owner

Card #6473 cannot be closed from the repository alone. What it waits for (its "execution input"
since 8 Nov): the choice of an off-host backup target, the DirectAdmin backup schedule (Vimexx SSO,
not reachable by an agent), confirmation that the test fatal-error email reached info@martiendejong.nl,
and then the restore test. Since 19 Nov the repository offers the off-host target itself (the nightly
encrypted GitHub artifacts above), so the list is, in this order:

- [ ] Accept GitHub Actions artifacts (encrypted, 30 days) as the off-host target, or choose another
      (storage box, Jengo dev server, UpdraftPlus to cloud storage); and the team alert address.
- [ ] For the nightly backup: a GnuPG key pair for backups (the public half as `BACKUP_GPG_PUBLIC_KEY`,
      the private half only in the vault), `PROSPERGENICS_BACKUP_KEY` in production's `wp-config.php`,
      the Environment `production-backup` with its three secrets; deploy production once through
      `deploy-production.yml` so it has the backup endpoint; merge `backup-production.yml` to `develop`.
- [ ] Vimexx/DirectAdmin (second layer): daily backups of the database **and** the files, kept 30 days,
      with a failure email to the team address (steps 1-3 under "Backups → How").
- [ ] `wp-config.php` on test and production: `define( 'PROSPERGENICS_ALERT_EMAIL', '<team address>' );`
      (and `RECOVERY_MODE_EMAIL` to the same address); check that outgoing mail works (SMTP).
      Then trigger a test fatal on the test site (a temporary file `wp-content/mu-plugins/pg-fatal-test.php`
      with `<?php add_action( 'wp', function () { if ( isset( $_GET['pg_fatal_test'] ) ) { pg_no_such_function(); } } );`,
      open `/?pg_fatal_test=1`, delete the file) and confirm the email and the `/manage/` entry.
- [ ] Merge `uptime.yml` (now with production) to `develop` (the default branch) so the schedule runs;
      watch the repository or set `ALERT_ASSIGNEES`.
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

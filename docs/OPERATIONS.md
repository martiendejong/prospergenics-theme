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
| Production deploy (`master`) | by hand (Martien) | manual |

## Backups

### What to back up

| Item | Where it lives | Covered by |
|---|---|---|
| Database (posts, courses, articles, users, profiles, contact messages, settings) | MySQL on the host | **host backup, not automated here** (see below) |
| `wp-content/uploads/` (profile photos, featured media, course thumbnails) | host disk | **host backup, not automated here** |
| `wp-config.php` (DB login, salts, SMTP settings) | host disk | host backup; keep a copy in the vault |
| Theme `prospergenics-2026` | git + host | git, plus a download of the live theme before every deploy (artifact `theme-backup-<sha>`, 30 days) |
| Static pages `people/`, `experts/`, `community/`, `team/` | git + host | git (rebuilt and re-uploaded on every deploy) |
| Plugins (jengo-listings, SQLite/MySQL drop-ins, SMTP) | host disk | host backup; versions noted in the plugin list |

The database and uploads change every day once members sign up; the theme only changes with a
deploy. Losing the database loses every account, profile, enrolment and certificate.

### How

1. **Host backups (owner, at Vimexx).** In DirectAdmin, check that the package has automatic
   backups that include the **database and the files**, how often (daily) and how long they are
   kept (at least 14 days). If Vimexx only keeps a few days, add a weekly manual backup:
   DirectAdmin → *Create/Restore Backups* → tick *Domains* (files) and *Databases* → *Create
   Backup*. The archive appears in the FTP home under `backups/`; download it to the Jengo
   storage and delete it from the host.
2. **Database export before a risky change** (a migration, a plugin update, a production deploy):
   DirectAdmin → *MySQL Management* → the WordPress database → *Download* (or phpMyAdmin →
   *Export* → SQL). With SSH or WP-CLI access on the host the same in one line:
   `wp db export ~/backups/db-$(date -u +%Y%m%dT%H%M%SZ).sql.gz` (or `mysqldump
   --single-transaction --default-character-set=utf8mb4 -u <user> -p <db> | gzip > …`, the login is in
   `wp-config.php`). Once SSH/WP-CLI or a DirectAdmin API key exists, this export can be added to
   `deploy-test.yml` before the upload, with the dump kept as an artifact like the theme backup.
3. **Theme**: automatic. Every deploy downloads the live theme first
   (`deploy-wp-theme-vimexx.py`, artifact `theme-backup-<sha>` in the Actions run). Restore:
   `python3 design/new-hero-2026-10/deploy-wp-theme-vimexx.py --no-build --theme-only --theme-dir <unzipped artifact>/<time>/prospergenics-2026`.
   With `--switch` the previous three themes are also kept on the server, outside the web root, in
   `domains/<site>/deploy/prospergenics-2026-prev-<time>/`.

### Restore test (every quarter, and before launch)

A backup that was never restored is a hope, not a backup.

1. Take the newest host backup (database dump + `wp-content/uploads/` + `wp-config.php`).
2. Start a local WordPress with MySQL/MariaDB (or a fresh subdomain at the host), import the dump
   (`wp db import dump.sql`), copy `wp-content/uploads/`, the plugins and the theme.
3. `wp search-replace 'https://test.prospergenics.com' 'http://localhost:8096' --all-tables`.
4. Check: log in as an admin and as a member, open `/`, `/courses/`, a course, `/articles/`, a
   member profile `/in/<slug>/`, the contact messages in wp-admin; run
   `bash tests/smoke/smoke-check.sh http://localhost:8096` (the `/about/`-style checks need the same
   pages to exist).
5. Write the date, the backup used and the result at the bottom of this file.

## Monitoring and alerts

- **Uptime** (`uptime.yml`, every 30 minutes): `/`, `/courses/`, `/articles/` and `/wp-login.php` on
  test.prospergenics.com must answer 200 or 302 and show no PHP fatal error, critical-error page
  or database-connection error. Each URL gets three tries 20 s apart. Any failure fails the run.
- **After every deploy** (`deploy-test.yml`): `tests/smoke/smoke-check.sh` checks the static pages,
  the platform routes (`/courses/`, `/articles/`, `/profile/` → 302 to login, the
  `prospergenics/v1/courses` JSON), the `/members/lessy/` → `/people/lessy/` redirect, the value test
  on the homepage and the shared site header on every page.
- **PHP fatal errors in pages nobody checks**: WordPress' recovery mode emails the site's
  *Administration Email Address* (Settings → General) when a fatal error happens. That address must
  be a monitored inbox and outgoing mail must work (SMTP).

### Where alerts go

A failed GitHub Actions run sends an email, but **not to all repository admins**:

- for `uptime.yml` (a scheduled workflow) GitHub notifies **the person who last changed the
  `cron:` line** (or who re-enabled the workflow);
- for `deploy-test.yml` and `ci.yml`, the person whose push or PR started the run.

That person also needs *Settings → Notifications → GitHub Actions → Email, "Only notify for failed
workflows"* switched on. To reach more people, add a notification step (Slack, Telegram, WhatsApp
via a provider such as Twilio) that runs `if: failure()`, with its token as a repository secret.

Limits of the GitHub schedule: it only runs from the repository's **default branch (`develop`)**,
so `uptime.yml` has to be merged there before it fires; runs can start several minutes late when
GitHub is busy; and GitHub disables schedules in a public repository after 60 days without commits.
For launch, an external monitor (UptimeRobot, Better Stack or similar, free tier) on the same four
URLs gives independent checks and SMS/WhatsApp alerts.

## What the host must provide

- Daily backups of database **and** files, kept at least 14 days, with a self-service restore.
- A way to export the database from a script (SSH with WP-CLI or `mysqldump`, or a DirectAdmin API
  key) so deploys can take a database backup automatically.
- Access to the PHP error log of the site.
- FTPS with TLS session reuse (current), and permission to rename files (used by every deploy) and
  folders (used by `--switch`).
- Outgoing mail (SMTP) for WordPress' recovery-mode and contact-form emails.

## Still needs the owner

- [ ] Check (or switch on) the Vimexx/DirectAdmin backups: database + files, daily, retention ≥ 14 days.
- [ ] Do the first restore test and record it below.
- [ ] Decide who gets alerts; switch on GitHub Actions email for that person; merge `uptime.yml` to `develop`.
- [ ] WhatsApp/SMS alerts: choose a provider (external monitor, or Twilio/CallMeBot from the workflow) and add its key as a secret.
- [ ] Production: run the deploy through a GitHub Environment `production` with required reviewers
      instead of by hand, and add a production uptime workflow with the production URL.
- [ ] Check once with the test site that `--switch` works on Vimexx (folder rename over FTPS); if it
      does, it can become the default for production.

## Restore test log

| Date | Backup used | Result | By |
|---|---|---|---|
| | | | |

# Prospergenics 2026 redesign: completion plan

Status as of 2026-10-23. This plan builds on Codex's audit (JengoWork Company Websites board:
49 tasks; Jengo Listings: 47 tasks) and adds a code review of the `design/new-hero-2026-10`
branch, which is the theme `test.prospergenics.com` runs (`prospergenics-2026`).

**Goal:** replace the production theme on `prospergenics.com` with the 2026 design, keeping
everything the old theme earned for SEO and security, with a repeatable, tested deploy.

---

## 0. Findings that shape the plan

| # | Finding | Why it matters |
|---|---|---|
| F1 | **Two separate themes.** `master` holds the production theme (root folder). It carries all SEO and security work: title/meta overrides, Organization/Person/Course schema, OG/Twitter, hreflang, the `/kenya/` redirect, security headers, `security.txt`, and the contact handler (tasks 731–3085). `wp-theme/prospergenics-2026/` has **none of it**: its `functions.php` is 11 lines. | Activating the 2026 theme on production as it stands would silently remove all of that. This is the largest launch risk. |
| F2 | The 2026 `index.php` **redirects every non-page request to `/`**: blog posts, archives, search and 404s. | Google treats this as soft-404s and loses the blog. A real `404.php`, `archive.php` and `search.php` are needed. |
| F3 | Templates hard-code `<title>` and remove WP's title tag. Only `/about/` has a meta description and OG tags, and it is `noindex`. There's no canonical, no JSON-LD and no meta description on the homepage. | Yoast/SEO God can't control titles, and homepage snippets will be Google-generated. |
| F4 | `/people/*`, `/experts/` and `/community/` are **static HTML uploaded by FTP outside WordPress**. | They are missing from the sitemap and get no `wp_head` (no SEO plugin, no headers filter). They can't be edited in wp-admin and are easy to overwrite. |
| F5 | **The homepage has no visible navigation, logo, contact link or footer at mobile width** (rendered 390 px). `.nav-links` is hidden under 880 px with no menu button. There are no `<nav>`, `<main>` or `<footer>` landmarks. | This hurts UX/accessibility and gives crawlers a weak internal-link structure. |
| F6 | The chat launcher overlaps the "Not sure where to start?" copy on desktop and covers content on mobile. | A visible layout bug. |
| F7 | Theme assets total **22 MB**; several PNGs are 1.6–2.7 MB each. There's no `loading="lazy"`, `width`/`height`, WebP/AVIF or `srcset`. There are 3 Google Font families with 11 weights. | Poor LCP/CLS, so a weak Core Web Vitals score, especially on Kenyan mobile networks. |
| F8 | 9 of 20 homepage images have empty `alt`. | Accessibility and image SEO. |
| F9 | The WhatsApp link is still `wa.me/254XXXXXXXXX`. | A broken primary CTA. |
| F10 | The 2026 design has **no contact form**. PR #20 (store-before-mail and spam protection) targets the old theme only. | Without a form, lead capture depends on WhatsApp and the chat. |
| F11 | **There is no CI.** Deploys are manual Python FTP scripts that run from Martien's Windows machine and depend on the vault (`deploy-wp-theme-vimexx.py`). Production `functions.php` has drifted from git (SMTP block, task 797). | Deploys can't be repeated, are risky, and production isn't reproducible from git. |
| F12 | Open PRs: #4 (homepage meta, old theme), #17 (favicon, 2026), #20 (contact form, old theme), #21 (Lessy pronouns, draft). | They need merging or porting before launch. |

Codex's audit also lists these items, which still apply: the failed WP update and Site Health
warnings, inactive Akismet, the broken course button, the quiz result/email failure, missing
legal pages on the new theme, sitemap cleanup, and **301s for ~30 legacy URLs**.

---

## Phase 1: Foundation (merge the two themes and set up CI/CD)

1. **One theme, one source of truth.** Rebuild `prospergenics-2026` as a proper theme:
   - Move the inline CSS/JS from the generated templates into enqueued files (`wp_enqueue_style/script`, with versioned filenames). PR #19's root-theme port already did this and can be reused.
   - Port `inc/security-headers.php`, `inc/security-txt.php`, the legacy redirects, hreflang, the schema functions and the contact handler from `master` into the 2026 theme's `functions.php`/`inc/`.
   - Restore `add_theme_support('title-tag')` and let Yoast own titles, meta, canonicals and OG.
   - Retire either the root theme or `wp-theme/` so there is only one build path. Codex flagged this too.
2. **Bring production drift into git.** Pull live `functions.php` from `prospergenics.com`, diff it against `master`, and commit the SMTP block as an `wp-config` constant or mu-plugin (no secrets in git) along with the task-797 meta description.
3. **CI (GitHub Actions) on every PR:**
   - `php -l` on all PHP; run `tests/*.php` (the existing harness).
   - PHPCS with WordPress Coding Standards (security sniffs: escaping, nonces, sanitising).
   - HTML validation, plus Lighthouse CI against a local WP container (`wp-env`) with budgets: LCP < 2.5 s, CLS < 0.1, a11y ≥ 95, SEO = 100.
   - Playwright smoke tests at 390 px and 1440 px: no horizontal scroll, nav reachable, CTAs not covered, no console errors.
   - Secret scanning and Dependabot.
4. **CD:** a GitHub Action that deploys over FTPS/SFTP (credentials in GitHub Secrets, not the local vault):
   - Push to `design/new-hero-2026-10` → **test** (automatic).
   - Tag/release on `master` → **production** (manual approval through a GitHub Environment). Take a backup first (theme folder plus DB dump via the host or UpdraftPlus) and keep a one-click rollback to the previous release.
5. **Branch hygiene:** merge PR #17 and #21 into the design branch, port PR #20's store-before-mail and spam logic into the 2026 theme, and close PR #4 once the 2026 meta is in place.

## Phase 2: Finish design and UX

1. **Global header and footer** on every template: logo, nav (Courses, Members, Tools, Services, Partners, About, Contact), an accessible mobile menu button (`aria-expanded`, focus trap, Esc to close), and a footer with contact, socials, Privacy/Cookie/Terms links and the address.
2. **Fix the chat/WhatsApp launcher overlap** (F6): reserve bottom-right space, collapse it to an icon on mobile, and keep it from covering CTAs.
3. **Real contact page and form** (`/contact/`), using the PR #20 logic: stored before mail, honeypot, rate limit, and Akismet or Cloudflare Turnstile. Add the real WhatsApp number (F9).
4. **Visitor journeys from Codex's list:** quiz result and email delivery (value test → `/api/leadtest`), course buttons and downloads, and `/courses`, `/tools`, `/services`, `/partners`, `/members` listings. Each needs one acceptance test.
5. **Move people pages into WordPress** (F4): a `team_member` CPT or the jengo-listings plugin, with `/people/<slug>/` as a WP permalink, `Person` schema, and editing in wp-admin. Keep `/members/<slug>/` → `/people/<slug>/` 301s.
6. **Proper templates:** `404.php` that returns a real 404 status with search and links, `archive.php`, `search.php`, `single.php` and `page.php` styled in the 2026 look (F2).
7. **Accessibility (WCAG 2.1 AA):** landmarks, one `h1` per page, meaningful alt text (F8), colour-contrast check on text over photos, visible focus styles, keyboard-operable slider/tabs, and `prefers-reduced-motion` respected (already partly done).
8. **Content:** tool descriptions, member profiles and course walkthroughs (Codex item 4). Martien signs off on the copy.

## Phase 3: Performance (Core Web Vitals)

1. Convert images to **WebP/AVIF**, resize to their display size, and add `srcset`/`sizes`. Target under 200 KB for the hero and under 80 KB for cards; total page weight under 1.5 MB.
2. `loading="lazy"` and `decoding="async"` below the fold; `fetchpriority="high"` and a preload on the hero image; explicit `width`/`height` on every image (CLS).
3. Fonts: self-host and subset, cut to 2 families and 4–5 weights, `font-display: swap`, and preload the heading font.
4. Defer non-critical JS (the chat `embed.js` loads on interaction or idle).
5. Page cache (LiteSpeed Cache or WP Super Cache on Vimexx), Brotli/GZIP, long-cache headers (see `.htaccess-recommended`), and Cloudflare in front (already used on production).
6. Verify with PageSpeed Insights (mobile ≥ 90) and CrUX once launched.

## Phase 4: SEO and Google

**Technical:**
- One canonical per URL, a self-referencing hreflang (`en`, `nl` for the Dutch page), and a clean XML sitemap from Yoast (exclude test, thin and noindex pages). Add `robots.txt` with the sitemap URL.
- **Test site must stay noindex** (`Settings → Reading → Discourage`, plus HTTP basic auth or an IP allowlist). Production must have it **off**. Codex found test currently allows indexing; fix this now so the test domain doesn't compete with production.
- Structured data: `Organization` + `WebSite` (with `SearchAction`) site-wide, `Person` on profiles, `Course` on courses, `BreadcrumbList`, `FAQPage` where there's a real FAQ. No Review schema until real third-party reviews exist (existing policy, task 951).
- OG/Twitter image (1200×630) on every page, favicon set (ICO, 192/512 PNG, apple-touch-icon, `site.webmanifest`).
- **301 map for the ~30 legacy URLs** (from Codex's launch task), using the Redirection plugin or theme rules. Check with a crawl before and after launch (Screaming Frog or `wget --spider`).
- Keep `llms.txt` and the answer-page plan from `docs/listing-pages-architecture.md` as phase-5 growth work.

**Google accounts (needs Martien/owner access):**
- **Google Search Console:** verify the Domain property by DNS, submit the sitemap, and watch Coverage/CWV. After launch, use URL inspection on the key pages.
- **Google Analytics 4** (or privacy-friendly Plausible) with **Consent Mode v2** and a cookie banner (the audience includes the EU/NL, so GDPR applies). Track events: WhatsApp click, chat open, value-test complete, contact submit, course click.
- **Google Business Profile** for the Kenyan office (NAP must match the site footer and schema).
- Bing Webmaster Tools (import from GSC).

**Content/authority:** a unique title and meta per page, internal links from the homepage showcase into the listing pages, a regular blog/case-study cadence, partner backlinks (Bugatti Insights, Art Revisionist and martiendejong.nl already link out; ask for links back).

## Phase 5: Security and WordPress hardening

1. Update core, plugins and themes (fix the **failed update** Codex saw) and clear the Site Health warnings. Remove unused plugins and themes.
2. Admin: unique admin usernames, strong passwords, **2FA** (WP 2FA or Wordfence), least-privilege roles, and an audit of existing users.
3. Login protection: rate limiting/limit-login, Cloudflare WAF rules for `/wp-login.php` and `xmlrpc.php` (block it), and disable user enumeration (`/?author=`, `/wp-json/wp/v2/users`).
4. Headers (port F1): HSTS (consider `includeSubDomains; preload`), CSP (tighten from the current permissive one to the real origins: fonts.googleapis/gstatic, coaching.martiendejong.nl, listings.prospergenics.com, GA), `Referrer-Policy`, `Permissions-Policy`. Check at securityheaders.com.
5. `DISALLOW_FILE_EDIT`, no PHP execution in `/uploads/`, `wp-config.php` protected, salts rotated, and SFTP/FTPS only.
6. Forms: nonce + honeypot + Turnstile/Akismet, server-side validation, and enquiries stored privately (PR #20).
7. **Backups:** daily DB, weekly files, kept offsite for 30 days, with a test restore once.
8. **Monitoring:** uptime (UptimeRobot/BetterStack) for the site, `listings.prospergenics.com` and the CoachOS chat; SSL expiry alerts; Wordfence/Sucuri malware scan; GSC security alerts.
9. Privacy: Privacy and Cookie policy pages carried over to the new theme, a cookie consent banner, and a data-processing note for the value-test email and chat.

## Phase 6: Launch

1. Freeze content and run the full acceptance pass on test at desktop, mobile, Safari, Chrome and Firefox: every link, every form, every CTA, 404s, redirects, Lighthouse.
2. Get Martien's written approval.
3. Back up production (files + DB). Deploy through the CD pipeline, switch the theme, flush the cache and Cloudflare.
4. Post-launch checks within the first hour: the 301 crawl, sitemap fetched in GSC, test enquiry arrives (Notification = `Sent`), schema passes the Rich Results Test, headers check, no indexing of test.
5. Week 1–4: watch GSC coverage, 404 logs, CWV and analytics conversions; fix what they show.

---

## Order of work and owners

| Week | Work | Owner | Blocked on |
|---|---|---|---|
| 1 | Phase 1.1–1.5 (theme merge, drift capture, CI, CD) and test-site noindex | Dev (Lessy and Claude/Codex) | FTP/SFTP credentials for GitHub Secrets |
| 2 | Phase 2.1–2.6 (header/footer, contact, journeys, people CPT, templates) | Dev | Real WhatsApp number; contact inbox owner |
| 3 | Phase 2.7–2.8, Phase 3 (a11y, content, performance) | Dev and Martien for copy | Content sign-off |
| 3 | Phase 4 Google setup, Phase 5 hardening | Dev and Martien | GSC/GA/DNS access |
| 4 | Phase 6 launch | Martien approves | All of the above green |

## Decisions needed from Martien

1. Which theme path survives: the root-theme port (PR #19 approach) or `wp-theme/prospergenics-2026`?
2. Does the new site get a contact form? (Recommended: yes, `/contact/` with PR #20's logic.)
3. Real WhatsApp number, and who owns the enquiry inbox (JengoMail?).
4. Analytics choice (GA4 or Plausible) and the cookie-consent tool.
5. People pages: a WP CPT or the jengo-listings plugin as the single data source?
6. Access for CI: SFTP/FTPS deploy credentials as GitHub Secrets, plus GSC/DNS access.

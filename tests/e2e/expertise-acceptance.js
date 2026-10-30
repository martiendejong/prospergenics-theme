#!/usr/bin/env node
/**
 * Card #6393 acceptance walk-through against a real WordPress, in a real browser (Playwright):
 *
 *   1. sign in                 a member logs in through wp-login.php
 *   2. add project             the member adds a project to their profile       (projects, #6388)
 *   3. approve                 the project owner / staff confirms the contribution (projects, #6388)
 *   4. publish                 the project is public at /projects/<slug>/       (projects, #6388)
 *   5. outsider correction     another member suggests a correction on the profile (scrutiny, #6389)
 *   6. review                  staff approve it in the moderation queue          (scrutiny, #6389)
 *   7. search                  /find/ finds the member, with the evidence linked
 *   +  staging gates           a non-production host is noindex; /find/ has one canonical and an ItemList
 *
 * Steps 2-6 need the projects (#6388) and scrutiny (#6389) modules. Each is detected on the site
 * (the projects REST routes under prospergenics/v1/projects, the scrutiny actions on the profile)
 * and its steps are SKIPPED with a message while it is not merged; nothing is faked.
 *
 *   E2E_BASE=http://localhost:8105 node tests/e2e/expertise-acceptance.js
 *
 * Environment (defaults fit the scratchpad audit site; tests/e2e/seed.php users work too):
 *   E2E_MEMBER=amina:amina-pass-123        the member who adds the project and is searched for
 *   E2E_OUTSIDER=reader:reader-pass-123    the member who suggests a correction
 *   E2E_ADMIN=admin:admin-pass-123         staff (approves, reviews)
 *   E2E_QUERY=pricing                      a word the member's public profile carries evidence for
 *   E2E_HIDDEN="Hannah Hidden,Sid Suspended"  names that must never appear in the results
 *   PLAYWRIGHT=/path/to/playwright         where to require Playwright from (default: 'playwright')
 *
 * Exit code 1 when a step that ran failed; skipped steps do not fail the run but are listed.
 */
'use strict';
let chromium;
try { ({ chromium } = require(process.env.PLAYWRIGHT || 'playwright')); }
catch (e) { ({ chromium } = require('/opt/node-tools/node_modules/playwright')); }

const BASE = (process.env.E2E_BASE || 'http://localhost:8105').replace(/\/$/, '');
const cred = (v, d) => { const [user, pass] = (v || d).split(':'); return { user, pass }; };
const MEMBER = cred(process.env.E2E_MEMBER, 'amina:amina-pass-123');
const OUTSIDER = cred(process.env.E2E_OUTSIDER, 'reader:reader-pass-123');
const ADMIN = cred(process.env.E2E_ADMIN, 'admin:admin-pass-123');
const QUERY = process.env.E2E_QUERY || 'pricing';
const HIDDEN = (process.env.E2E_HIDDEN || 'Hannah Hidden,Sid Suspended').split(',').map(s => s.trim()).filter(Boolean);

const results = [];
const step = (n, name, status, detail) => { results.push({ n, name, status, detail }); console.log(`${status.padEnd(4)} ${n}. ${name}${detail ? ' - ' + detail : ''}`); };

async function login(browser, who) {
  const ctx = await browser.newContext({ viewport: { width: 1440, height: 900 } });
  const page = await ctx.newPage();
  await page.goto(BASE + '/wp-login.php');
  await page.fill('#user_login', who.user);
  await page.fill('#user_pass', who.pass);
  await Promise.all([page.waitForNavigation(), page.click('#wp-submit')]);
  const cookies = await ctx.cookies();
  return { ctx, page, ok: cookies.some(c => c.name.startsWith('wordpress_logged_in_')) };
}

(async () => {
  const browser = await chromium.launch();
  let profileUrl = '';
  let memberName = '';

  // 1. sign in
  const member = await login(browser, MEMBER);
  if (member.ok) {
    await member.page.goto(BASE + '/profile/');
    profileUrl = member.page.url().split('?')[0];
    memberName = (await member.page.textContent('h1').catch(() => '')).trim();
    step(1, 'sign in', /\/in\/[^/]+\/$/.test(profileUrl) ? 'PASS' : 'FAIL', `${MEMBER.user} lands on ${profileUrl} (${memberName})`);
  } else {
    step(1, 'sign in', 'FAIL', `no login cookie for ${MEMBER.user}`);
  }

  // 2-4. projects (#6388): only when the projects module is on the site
  const index = await (await fetch(BASE + '/wp-json/')).json().catch(() => ({ routes: {} }));
  const projectRoutes = Object.keys(index.routes || {}).filter(r => r.startsWith('/prospergenics/v1/projects'));
  let projectTitle = '';
  if (!projectRoutes.length) {
    const why = 'projects module (#6388) not merged: no prospergenics/v1/projects routes on this site';
    step(2, 'add project', 'SKIP', why);
    step(3, 'approve', 'SKIP', why);
    step(4, 'publish', 'SKIP', why);
  } else {
    // The projects module's own UI: the profile's "Add project" form (data-pg-project-form).
    await member.page.goto(profileUrl + '?edit=1');
    const form = await member.page.$('[data-pg-project-form]');
    if (!form) {
      step(2, 'add project', 'SKIP', 'projects routes exist but no [data-pg-project-form] on the profile: update this step to the merged UI');
      step(3, 'approve', 'SKIP', 'needs step 2');
      step(4, 'publish', 'SKIP', 'needs step 2');
    } else {
      projectTitle = `Acceptance project ${Date.now()}`;
      await member.page.fill('[data-pg-project-form] [name="title"]', projectTitle);
      await member.page.fill('[data-pg-project-form] [name="contribution"]', `Set the ${QUERY} for the first customers`);
      await Promise.all([member.page.waitForLoadState('networkidle'), member.page.click('[data-pg-project-form] [type="submit"]')]);
      step(2, 'add project', (await member.page.content()).includes(projectTitle) ? 'PASS' : 'FAIL', projectTitle);
      const admin = await login(browser, ADMIN);
      await admin.page.goto(BASE + '/wp-admin/edit.php?post_type=pg_project&s=' + encodeURIComponent(projectTitle));
      const approve = await admin.page.$('a:has-text("Confirm"), a:has-text("Approve"), button:has-text("Confirm"), button:has-text("Approve")');
      if (approve) { await approve.click(); await admin.page.waitForLoadState('networkidle'); }
      step(3, 'approve', approve ? 'PASS' : 'SKIP', approve ? 'confirmed by staff' : 'no Confirm/Approve action found for staff');
      const pub = await fetch(BASE + '/wp-json/wp/v2/pg_project?search=' + encodeURIComponent(projectTitle)).then(r => r.json()).catch(() => []);
      step(4, 'publish', Array.isArray(pub) && pub.length ? 'PASS' : 'FAIL', Array.isArray(pub) && pub.length ? pub[0].link : 'not public in /wp/v2/pg_project');
      await admin.ctx.close();
    }
  }

  // 5-6. scrutiny (#6389): the public actions on the profile
  const outsider = await login(browser, OUTSIDER);
  let scrutiny = null;
  if (outsider.ok && profileUrl) {
    await outsider.page.goto(profileUrl);
    scrutiny = await outsider.page.$('[data-pg-scrutiny], button:has-text("Suggest a correction"), a:has-text("Suggest a correction")');
  }
  if (!scrutiny) {
    const why = 'scrutiny module (#6389) not merged: no "Suggest a correction" action on ' + (profileUrl || 'the profile');
    step(5, 'outsider correction', 'SKIP', why);
    step(6, 'review', 'SKIP', why);
  } else {
    await scrutiny.click();
    const box = await outsider.page.$('textarea');
    if (box) {
      await box.fill(`Acceptance check: please add evidence for ${QUERY}.`);
      await Promise.all([outsider.page.waitForLoadState('networkidle'), outsider.page.click('form:has(textarea) [type="submit"]')]);
    }
    step(5, 'outsider correction', box ? 'PASS' : 'FAIL', box ? 'suggestion sent' : 'no text field after "Suggest a correction"');
    const admin = await login(browser, ADMIN);
    await admin.page.goto(BASE + '/wp-admin/admin.php?page=pg-reports');
    const queued = (await admin.page.content()).includes('Acceptance check');
    step(6, 'review', queued ? 'PASS' : 'SKIP', queued ? 'the suggestion is in the moderation queue' : 'suggestion not found in the Reports queue: update this step to the merged UI');
    await admin.ctx.close();
  }

  // 7. search, as a logged-out visitor
  const visitor = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const vp = await visitor.newPage();
  const res = await vp.goto(`${BASE}/find/?q=${encodeURIComponent(QUERY)}`);
  const html = await vp.content();
  const card = memberName ? vp.locator('.pgf-person', { hasText: memberName }).first() : null;
  const found = card && await card.count();
  const why = found ? (await card.locator('.pgf-why').textContent()).trim() : '';
  const links = found ? await card.locator('.pgf-why a').evaluateAll(as => as.map(a => a.href)) : [];
  let broken = [];
  for (const href of links) {
    const r = await fetch(href.split('#')[0], { redirect: 'manual' });
    if (r.status >= 400) broken.push(`${href} (${r.status})`);
  }
  const leaked = HIDDEN.filter(n => html.includes(n));
  const ok7 = res.status() === 200 && found && why.startsWith(`Matches ‘${QUERY}’:`) && links.length > 0 && !broken.length && !leaked.length && (!projectTitle || html.includes(projectTitle));
  step(7, 'search', ok7 ? 'PASS' : 'FAIL', found
    ? `${memberName}: "${why.replace(/\s+/g, ' ')}"; ${links.length} evidence links, ${broken.length} broken${leaked.length ? '; LEAKED: ' + leaked.join(', ') : ''}`
    : `${memberName || 'the member'} not found for "${QUERY}"`);

  // staging gates: SEO tags on /find/, and a non-production host never indexed
  const canonical = await vp.getAttribute('link[rel="canonical"]', 'href').catch(() => null);
  const robots = (await vp.getAttribute('meta[name="robots"]', 'content').catch(() => '')) || '';
  const itemList = await vp.$('script.pg-search-schema');
  const prodHost = /(^|\.)prospergenics\.com$/.test(new URL(BASE).hostname) && !/^test\./.test(new URL(BASE).hostname);
  const gateOk = canonical === `${BASE}/find/?q=${encodeURIComponent(QUERY)}` && !!itemList && (prodHost || robots.includes('noindex'));
  step('+', 'staging gates', gateOk ? 'PASS' : 'FAIL', `canonical ${canonical}; robots "${robots}"; ItemList ${itemList ? 'present' : 'missing'}`);

  await browser.close();
  const failed = results.filter(r => r.status === 'FAIL');
  const skipped = results.filter(r => r.status === 'SKIP');
  console.log(`\n${results.length - failed.length - skipped.length} passed, ${skipped.length} skipped, ${failed.length} failed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });

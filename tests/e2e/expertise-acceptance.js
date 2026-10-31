#!/usr/bin/env node
/**
 * Card #6393 acceptance walk-through against a real WordPress, in a real browser (Playwright),
 * through the site's own forms (no REST shortcuts, nothing faked):
 *
 *   1. sign in               the member logs in (wp-login.php) and makes their profile public
 *                            (/profile/settings/, "Everyone")
 *   2. add project           "Add a project…" on their profile (admin-post pg_projects_create, #6388)
 *   3. approve               staff confirm the member's contribution on /projects/<slug>/
 *                            (admin-post pg_projects_status, status=confirmed)
 *   4. publish               a logged-out visitor sees /projects/<slug>/ with the confirmed
 *                            contribution, and /wp/v2/pg_project lists it
 *   5. outsider correction   another member sends a correction about the member (profile) and
 *                            evidence about the project (admin-post pg_scrutiny_submit, #6389)
 *   6. review                staff approve both in wp-admin → Reports → Scrutiny
 *                            (admin-post pg_scrutiny_decide, do=approve); both are public
 *   7. search                /find/?q=<query> as a visitor: the member is found, the explanation
 *                            names the confirmed project and the approved evidence, and every
 *                            evidence link (and its #anchor) resolves
 *   +  staging gates         /find/ has one canonical and an ItemList; a non-production host is noindex
 *
 * The projects module is detected by the pg_projects_create form on the member's profile and the
 * scrutiny module by its "Suggest a correction" disclosure; while one is missing its steps are
 * SKIPPED with a message.
 *
 * Preconditions: the member and the outsider have confirmed email addresses (the magic link of
 * inc/profiles/verify.php; with wp-cli: `wp user meta delete <id> _pg_profiles_verify_pending`),
 * and the member's profile is complete enough to be indexed (two of photo, headline, experience,
 * skills, education). The scratchpad audit site's amina/reader need only the email step.
 *
 *   E2E_BASE=http://localhost:8105 node tests/e2e/expertise-acceptance.js
 *
 * Environment:
 *   E2E_MEMBER=amina:amina-pass-123        the member who adds the project and is searched for
 *   E2E_OUTSIDER=reader:reader-pass-123    the member who sends the correction and the evidence
 *   E2E_ADMIN=admin:admin-pass-123         staff (confirm, review)
 *   E2E_QUERY=pricing                      the word the walk-through's project and evidence carry
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
const RUN = Date.now().toString(36);

const results = [];
const step = (n, name, status, detail) => { results.push({ n, name, status, detail }); console.log(`${status.padEnd(4)} ${n}. ${name}${detail ? ' - ' + detail : ''}`); };
const sleep = ms => new Promise(r => setTimeout(r, ms));

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

/** Submit the form that contains `inner` (a locator) by clicking its submit button, and wait for the redirect. */
async function submitForm(page, form, button = 'button[type="submit"]') {
  await Promise.all([page.waitForNavigation(), form.locator(button).first().click()]);
  return page.url();
}

/** Send a scrutiny item through the form inside `#<wrapId>` (kind, body, no connection). */
async function sendScrutiny(page, url, wrapId, kind, body) {
  await page.goto(url);
  const wrap = page.locator('#' + wrapId);
  if (!(await wrap.count())) return { ok: false, detail: `no #${wrapId} ("Suggest a correction…") on ${url}` };
  await wrap.locator('summary').first().click();
  const form = wrap.locator('form').first();
  await form.locator(`input[name="kind"][value="${kind}"]`).check();
  await form.locator('textarea[name="body"]').fill(body);
  await form.locator('input[name="disclosure"][value="none"]').check();
  await sleep(4500); // the form's signed time: people take more than 4 seconds (inc/spam-protection.php)
  const after = await submitForm(page, form);
  return { ok: /[?&]pgs=sent\b/.test(after), detail: after.replace(BASE, '') };
}

(async () => {
  const browser = await chromium.launch();
  let profileUrl = '', memberName = '', memberId = 0, projectUrl = '', projectId = 0;
  const projectTitle = `Pricing clinic for market traders ${RUN}`;
  const evidenceText = `I attended the ${QUERY} clinic in Gikomba: every trader left with a written ${QUERY} sheet for their stock (${RUN}).`;
  const correctionText = `Correction (${RUN}): the profile should say the ${QUERY} clinic ran in 2025, not 2024.`;

  // 1. sign in, profile public
  const member = await login(browser, MEMBER);
  if (!member.ok) {
    step(1, 'sign in', 'FAIL', `no login cookie for ${MEMBER.user}`);
  } else {
    await member.page.goto(BASE + '/profile/');
    profileUrl = member.page.url().split(/[?#]/)[0];
    memberName = (await member.page.textContent('h1').catch(() => '')).trim();
    await member.page.goto(BASE + '/profile/settings/');
    const priv = member.page.locator('form:has(input[name="pgp_action"][value="privacy"])');
    await priv.locator('input[name="privacy[profile]"][value="public"]').check();
    if (await priv.locator('input[name="privacy[portfolio]"][value="public"]').count()) {
      await priv.locator('input[name="privacy[portfolio]"][value="public"]').check();
    }
    const saved = await submitForm(member.page, priv);
    step(1, 'sign in', /\/in\/[^/]+\/$/.test(profileUrl) && /saved=privacy/.test(saved) ? 'PASS' : 'FAIL', `${MEMBER.user} lands on ${profileUrl.replace(BASE, '')} (${memberName}); profile and portfolio set to Everyone`);
  }

  // 2. add project (projects #6388: the create form on the member's own profile)
  await member.page.goto(profileUrl + '?edit=1');
  const create = member.page.locator('form:has(input[name="action"][value="pg_projects_create"])');
  if (!(await create.count())) {
    const why = 'projects module (#6388) not on this site: no pg_projects_create form on ' + profileUrl.replace(BASE, '');
    step(2, 'add project', 'SKIP', why);
    step(3, 'approve', 'SKIP', why);
    step(4, 'publish', 'SKIP', why);
  } else {
    await member.page.locator('#pgj-new > summary').click();
    await create.locator('[name="title"]').fill(projectTitle);
    await create.locator('[name="kind"]').selectOption('service');
    await create.locator('[name="period"]').fill('2025');
    await create.locator('[name="description"]').fill(`A weekly clinic where market traders work out ${QUERY} for their stock.`);
    await create.locator('[name="skills"]').fill('Pricing, Coaching');
    await create.locator('[name="role"]').fill('Clinic lead');
    await create.locator('[name="contribution"]').fill(`Ran the ${QUERY} sessions and wrote the worksheet.`);
    const added = await submitForm(member.page, create);
    const link = member.page.locator(`a:has-text("${projectTitle}")`).first();
    projectUrl = (await link.count()) ? await link.getAttribute('href') : '';
    step(2, 'add project', /pg_portfolio=added/.test(added) && projectUrl ? 'PASS' : 'FAIL', `"${projectTitle}" → ${projectUrl.replace(BASE, '') || 'no link on the profile'} (${added.replace(BASE, '')})`);

    // 3. approve: staff confirm the member's contribution on the project page
    const admin = await login(browser, ADMIN);
    if (projectUrl) {
      await admin.page.goto(projectUrl);
      const confirm = admin.page.locator('form:has(input[name="action"][value="pg_projects_status"]):has(input[name="status"][value="confirmed"])').first();
      projectId = Number(await confirm.locator('input[name="project_id"]').getAttribute('value').catch(() => 0));
      memberId = Number(await confirm.locator('input[name="user_id"]').getAttribute('value').catch(() => 0));
      const done = (await confirm.count()) ? await submitForm(admin.page, confirm) : '';
      step(3, 'approve', /pg_portfolio=confirmed/.test(done) ? 'PASS' : 'FAIL', done ? `staff confirmed the contribution of user ${memberId} on project ${projectId}` : 'no Confirm button for staff');
    } else {
      step(3, 'approve', 'FAIL', 'needs the project from step 2');
    }
    await admin.ctx.close();

    // 4. publish: public for a logged-out visitor, with the confirmed contribution
    const anon = await browser.newContext();
    const ap = await anon.newPage();
    const res = projectUrl ? await ap.goto(projectUrl) : null;
    const pageOk = res && res.status() === 200 && (await ap.content()).includes(projectTitle) && (await ap.locator('.pgj-status-confirmed').count()) > 0;
    const rest = await fetch(`${BASE}/wp-json/wp/v2/pg_project?search=${encodeURIComponent(RUN)}`).then(r => r.json()).catch(() => []);
    step(4, 'publish', pageOk && Array.isArray(rest) && rest.length ? 'PASS' : 'FAIL', `${projectUrl.replace(BASE, '')}: HTTP ${res ? res.status() : '-'}, confirmed badge ${pageOk ? 'shown' : 'missing'}; /wp/v2/pg_project lists it: ${Array.isArray(rest) && rest.length ? 'yes' : 'no'}`);
    await anon.close();
  }

  // 5. outsider correction (scrutiny #6389): a correction about the member, evidence about the project
  const outsider = await login(browser, OUTSIDER);
  if (!memberId) {
    // the member's ID from their profile's scrutiny wrapper
    await outsider.page.goto(profileUrl);
    const id = await outsider.page.locator('[id^="pgs-person-"][id$="-add"]').first().getAttribute('id').catch(() => '');
    memberId = Number((id || '').replace(/\D+/g, '')) || 0;
  }
  await outsider.page.goto(profileUrl);
  const hasScrutiny = await outsider.page.locator(`[id="pgs-person-${memberId}-add"]`).count();
  let reviewIds = [];
  if (!hasScrutiny) {
    const why = 'scrutiny module (#6389) not on this site: no "Suggest a correction…" on ' + profileUrl.replace(BASE, '');
    step(5, 'outsider correction', 'SKIP', why);
    step(6, 'review', 'SKIP', why);
  } else {
    const c = await sendScrutiny(outsider.page, profileUrl, `pgs-person-${memberId}-add`, 'correction', correctionText);
    const e = projectUrl && projectId ? await sendScrutiny(outsider.page, projectUrl, `pgs-project-${projectId}-add`, 'evidence', evidenceText) : { ok: false, detail: 'no project from steps 2-4' };
    step(5, 'outsider correction', c.ok && e.ok ? 'PASS' : 'FAIL', `${OUTSIDER.user}: correction on the profile (${c.detail}); evidence on the project (${e.detail})`);

    // 6. review: staff approve both in Reports → Scrutiny
    const admin = await login(browser, ADMIN);
    const approved = [];
    for (const text of [correctionText, evidenceText]) {
      await admin.page.goto(BASE + '/wp-admin/admin.php?page=pg-moderation&tab=scrutiny&view=review');
      const f = admin.page.locator('form:has(input[name="action"][value="pg_scrutiny_decide"])')
        .filter({ has: admin.page.locator('textarea[name="body"]', { hasText: text.slice(0, 40) }) }).first();
      if (!(await f.count())) continue;
      reviewIds.push(await f.locator('input[name="item"]').getAttribute('value'));
      const done = await submitForm(admin.page, f, 'button[name="do"][value="approve"]');
      if (/pgs_done=approved/.test(done)) approved.push(text === correctionText ? 'correction' : 'evidence');
    }
    await admin.ctx.close();
    const anon = await browser.newContext();
    const ap = await anon.newPage();
    await ap.goto(profileUrl);
    const correctionPublic = (await ap.content()).includes(`Correction (${RUN})`);
    let evidencePublic = false;
    if (projectUrl) { await ap.goto(projectUrl); evidencePublic = (await ap.content()).includes(`(${RUN}).`); }
    await anon.close();
    step(6, 'review', approved.length === 2 && correctionPublic && evidencePublic ? 'PASS' : 'FAIL', `approved: ${approved.join(', ') || 'none'} (items ${reviewIds.join(', ')}); public on the profile: ${correctionPublic}, on the project: ${evidencePublic}`);
  }
  await outsider.ctx.close();

  // 7. search, as a logged-out visitor
  const visitor = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const vp = await visitor.newPage();
  const res = await vp.goto(`${BASE}/find/?q=${encodeURIComponent(QUERY)}`);
  const html = await vp.content();
  const card = memberName ? vp.locator('.pgf-person', { hasText: memberName }).first() : null;
  const found = card && await card.count();
  const why = found ? (await card.locator('.pgf-why').textContent()).trim().replace(/\s+/g, ' ') : '';
  const links = found ? await card.locator('.pgf-why a').evaluateAll(as => as.map(a => a.href)) : [];
  const broken = [];
  for (const href of links) {
    const [url, anchor] = href.split('#');
    const r = await fetch(url, { redirect: 'manual' });
    const body = r.status < 400 && anchor ? await r.text() : '';
    if (r.status >= 400) broken.push(`${href} (${r.status})`);
    else if (anchor && !new RegExp(`id=["']${anchor}["']`).test(body)) broken.push(`${href} (no #${anchor} on the page)`);
  }
  const leaked = HIDDEN.filter(n => html.includes(n));
  const wantProject = projectUrl ? why.includes(`contributed to ${projectTitle} (confirmed)`) : true;
  const wantEvidence = reviewIds.length ? /evidence from others: .* \(approved\)/.test(why) : true;
  const rank = found ? await vp.locator('.pgf-person').evaluateAll((els, name) => els.findIndex(e => e.textContent.includes(name)) + 1, memberName) : 0;
  const ok7 = res.status() === 200 && found && why.startsWith(`Matches ‘${QUERY}’:`) && wantProject && wantEvidence && links.length > 0 && !broken.length && !leaked.length;
  step(7, 'search', ok7 ? 'PASS' : 'FAIL', found
    ? `${memberName} is #${rank}: "${why}"; ${links.length} evidence links, ${broken.length ? 'broken: ' + broken.join(', ') : 'all resolve'}${leaked.length ? '; LEAKED: ' + leaked.join(', ') : ''}`
    : `${memberName || 'the member'} not found for "${QUERY}"`);

  // staging gates: SEO tags on /find/, and a non-production host never indexed
  const canonical = await vp.getAttribute('link[rel="canonical"]', 'href').catch(() => null);
  const robots = (await vp.getAttribute('meta[name="robots"]', 'content').catch(() => '')) || '';
  const itemList = await vp.$('script.pg-search-schema');
  const host = new URL(BASE).hostname;
  const prodHost = /(^|\.)prospergenics\.com$/.test(host) && !/^test\./.test(host);
  const gateOk = canonical === `${BASE}/find/?q=${encodeURIComponent(QUERY)}` && !!itemList && (prodHost || robots.includes('noindex'));
  step('+', 'staging gates', gateOk ? 'PASS' : 'FAIL', `canonical ${canonical}; robots "${robots}"; ItemList ${itemList ? 'present' : 'missing'}`);

  await browser.close();
  const failed = results.filter(r => r.status === 'FAIL');
  const skipped = results.filter(r => r.status === 'SKIP');
  console.log(`\n${results.length - failed.length - skipped.length} passed, ${skipped.length} skipped, ${failed.length} failed`);
  process.exit(failed.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(1); });

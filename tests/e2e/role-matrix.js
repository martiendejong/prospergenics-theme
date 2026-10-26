#!/usr/bin/env node
/**
 * Role/permission matrix against a real WordPress (audit ARC-18). Plain HTTP with a cookie jar
 * per user, no dependencies (Node >= 18). Started by tests/e2e/run.sh, which boots the site and
 * writes the fixtures (user IDs, course, lesson, ...) from tests/e2e/seed.php.
 *
 *   E2E_BASE=http://localhost:8096 E2E_FIXTURES=/tmp/pg-e2e/fixtures.json node tests/e2e/role-matrix.js
 *
 * Every rule has a refusal check ("must not be allowed") and, where it makes sense, a positive
 * control (the role that should get in does), so a broken login or an empty seed cannot make the
 * matrix pass by accident. Exit code 1 on any failed check.
 */
'use strict';
const fs = require('fs');

const BASE = (process.env.E2E_BASE || 'http://localhost:8096').replace(/\/$/, '');
const FX = JSON.parse(fs.readFileSync(process.env.E2E_FIXTURES || '/tmp/pg-e2e/fixtures.json', 'utf8'));
const NS = '/wp-json/prospergenics/v1';

/* ── a tiny browser: cookies + manual redirects ── */
class Session {
  constructor(label) { this.label = label; this.jar = new Map(); this.nonce = ''; }
  cookieHeader() { return [...this.jar].map(([k, v]) => `${k}=${v}`).join('; '); }
  store(res) {
    const list = typeof res.headers.getSetCookie === 'function' ? res.headers.getSetCookie() : [];
    for (const c of list) {
      const [pair] = c.split(';');
      const i = pair.indexOf('=');
      const k = pair.slice(0, i).trim(), v = pair.slice(i + 1).trim();
      if (/expires=Thu, 01[- ]Jan[- ]1970/i.test(c) || v === 'deleted' || v === '') this.jar.delete(k); else this.jar.set(k, v);
    }
  }
  async request(method, path, { body, json, follow = false, headers = {} } = {}) {
    let url = path.startsWith('http') ? path : BASE + path;
    const chain = [];
    for (let hop = 0; hop < 8; hop++) {
      const h = Object.assign({ Cookie: this.cookieHeader() }, headers);
      let payload;
      if (json !== undefined) { h['Content-Type'] = 'application/json'; payload = JSON.stringify(json); }
      else if (body !== undefined) { h['Content-Type'] = 'application/x-www-form-urlencoded'; payload = new URLSearchParams(body).toString(); }
      if (this.nonce && url.includes('/wp-json/')) h['X-WP-Nonce'] = this.nonce;
      const res = await fetch(url, { method, headers: h, body: payload, redirect: 'manual' });
      this.store(res);
      const text = await res.text();
      const location = res.headers.get('location');
      chain.push({ status: res.status, url, location });
      if (follow && location && res.status >= 300 && res.status < 400) {
        url = new URL(location, url).toString(); method = 'GET'; body = json = undefined;
        continue;
      }
      let data = null; try { data = JSON.parse(text); } catch (e) { /* html */ }
      return { status: res.status, location, text, data, chain, finalUrl: url };
    }
    throw new Error(`too many redirects for ${path}`);
  }
  get(path, opts) { return this.request('GET', path, opts); }
  async login(user, pass) {
    await this.get('/wp-login.php'); // sets wordpress_test_cookie
    const r = await this.request('POST', '/wp-login.php', { body: { log: user, pwd: pass, rememberme: 'forever', 'wp-submit': 'Log In', testcookie: '1', redirect_to: BASE + '/' } });
    if (![...this.jar.keys()].some(k => k.startsWith('wordpress_logged_in_'))) throw new Error(`login failed for ${user} (status ${r.status})`);
    const n = await this.get('/wp-admin/admin-ajax.php?action=rest-nonce');
    this.nonce = n.status === 200 ? n.text.trim() : '';
    return this;
  }
}

/* ── results ── */
const results = [];
function check(rule, who, pass, detail) {
  results.push({ rule, who, pass: !!pass, detail });
}
const title = html => { const m = /<title[^>]*>([\s\S]*?)<\/title>/i.exec(html || ''); return m ? m[1].trim() : ''; };
const pathOf = u => { try { return new URL(u, BASE).pathname; } catch (e) { return u || ''; } };
// wp-admin refused: WordPress' 403 "Sorry, you are not allowed", or a redirect away from wp-admin.
function refused(r) {
  if (r.status === 403) return true;
  if (r.status >= 300 && r.status < 400 && r.location && !pathOf(r.location).startsWith('/wp-admin/')) return true;
  return /Sorry, you are not allowed/i.test(r.text);
}

(async () => {
  const U = FX.users, PW = FX.password;
  const visitor = new Session('visitor');
  const s = {};
  for (const key of ['admin', 'editor', 'author', 'contributor', 'subscriber', 'subscriber2', 'unverified']) {
    s[key] = await new Session(key).login(U[key].login, PW);
  }

  /* 1. Contact messages (names, emails, phone numbers): editors and admins only. */
  const contactList = '/wp-admin/edit.php?post_type=pg_contact_msg';
  for (const who of ['contributor', 'author', 'subscriber']) {
    const r = await s[who].get(contactList);
    check('contact messages screen refused', who, refused(r) && !r.text.includes(FX.contact_title), `status ${r.status}${r.location ? ' -> ' + pathOf(r.location) : ''}`);
    const e = await s[who].get(`/wp-admin/post.php?post=${FX.contact_id}&action=edit`);
    check('contact message edit screen refused', who, refused(e) && !e.text.includes(FX.contact_title), `status ${e.status}`);
  }
  for (const who of ['editor', 'admin']) {
    const r = await s[who].get(contactList);
    check('contact messages screen allowed (control)', who, r.status === 200 && r.text.includes(FX.contact_title), `status ${r.status}`);
  }

  /* 2. Profile entries list (every member's entries, private ones too): administrators only. */
  const entriesList = '/wp-admin/edit.php?post_type=pg_experience';
  for (const who of ['contributor', 'author', 'editor', 'subscriber']) {
    const r = await s[who].get(entriesList);
    check('pg_experience list refused', who, refused(r) && !r.text.includes(FX.entry_title), `status ${r.status}${r.location ? ' -> ' + pathOf(r.location) : ''}`);
  }
  {
    const r = await s.admin.get(entriesList);
    check('pg_experience list allowed (control)', 'admin', r.status === 200 && r.text.includes(FX.entry_title), `status ${r.status}`);
  }

  /* 3. /courses/instructor/<id>/ must not name people who do not teach. */
  for (const who of ['subscriber', 'contributor', 'author']) {
    const r = await visitor.get(`/courses/instructor/${U[who].id}/`, { follow: true });
    const t = title(r.text);
    check('instructor page does not leak a non-instructor', who, !t.includes(U[who].name) && !r.text.includes(U[who].name), `status ${r.status}, title "${t}"`);
  }
  {
    const r = await visitor.get(`/courses/instructor/${U.editor.id}/`, { follow: true });
    check('instructor page names the instructor (control)', 'editor', r.status === 200 && title(r.text).includes(U.editor.name), `status ${r.status}, title "${title(r.text)}"`);
  }

  /* 4. Members who open wp-admin land on their own profile. */
  for (const who of ['subscriber', 'unverified']) {
    const r = await s[who].get('/wp-admin/index.php');
    const want = `/in/${U[who].slug}/`;
    check('subscriber wp-admin redirected to /in/<slug>/', who, r.status === 302 && pathOf(r.location) === want, `status ${r.status} -> ${r.location ? pathOf(r.location) : '(none)'}, want ${want}`);
  }
  {
    const r = await s.contributor.get('/wp-admin/index.php');
    check('staff keep the dashboard (control)', 'contributor', r.status === 200, `status ${r.status}`);
  }

  /* 5. REST: nobody writes another member's profile. */
  const entryPath = `${NS}/profile/experience/${FX.entry}`;
  for (const who of ['subscriber', 'contributor', 'author', 'editor']) {
    const p = await s[who].request('PATCH', entryPath, { json: { title: `Hijacked by ${who}` } });
    check('REST profile write for another user is 403', who, p.status === 403, `PATCH ${p.status} ${p.data && p.data.code || ''}`);
    const d = await s[who].request('DELETE', entryPath);
    check('REST profile delete for another user is 403', who, d.status === 403, `DELETE ${d.status} ${d.data && d.data.code || ''}`);
  }
  {
    const p = await visitor.request('PATCH', entryPath, { json: { title: 'Hijacked by visitor' } });
    check('REST profile write as visitor is refused', 'visitor', p.status === 401 || p.status === 403, `PATCH ${p.status}`);
    const own = await s.subscriber2.request('PATCH', entryPath, { json: { title: FX.entry_title, company: 'E2E Ltd', start: '2020-01', current: true } });
    check('REST profile write by the owner (control)', 'subscriber2', own.status === 200, `PATCH ${own.status} ${own.data && own.data.code || ''}`);
  }

  /* 6. Completing a lesson needs it opened first. */
  {
    const en = await s.subscriber.request('POST', `${NS}/courses/${FX.course}/enrolment`);
    check('enrol in the course (setup)', 'subscriber', en.status === 200, `POST enrolment ${en.status} ${en.data && en.data.code || ''}`);
    const c = await s.subscriber.request('POST', `${NS}/courses/lessons/${FX.lesson}/completion`);
    check('completion of an unopened lesson is 403', 'subscriber', c.status === 403 && c.data && c.data.code === 'pg_courses_not_opened', `POST completion ${c.status} ${c.data && c.data.code || ''}`);

    await s.subscriber2.request('POST', `${NS}/courses/${FX.course}/enrolment`);
    const open = await s.subscriber2.get(FX.lesson_url, { follow: true });
    const done = await s.subscriber2.request('POST', `${NS}/courses/lessons/${FX.lesson}/completion`);
    check('completion after opening the lesson (control)', 'subscriber2', open.status === 200 && done.status === 200, `lesson page ${open.status}, POST completion ${done.status} ${done.data && done.data.code || ''}`);
  }

  /* 7. Member privacy (ARC-10, ARC-11): email private by default, profiles not public before onboarding. */
  {
    const other = U.subscriber2, email = `${other.login}@example.test`;
    const pv = await visitor.get(`/in/${other.slug}/`, { follow: true });
    check('profile not public before onboarding', 'visitor', !pv.text.includes(other.name) && !pv.text.includes(email), `status ${pv.status}, title "${title(pv.text)}"`);
    const pm = await s.subscriber.get(`/in/${other.slug}/`, { follow: true });
    check('member profile visible to a verified member (control)', 'subscriber', pm.status === 200 && pm.text.includes(other.name), `status ${pm.status}`);
    check('member email not on the profile page', 'subscriber', !pm.text.includes(email), `status ${pm.status}`);
    for (const who of ['visitor', 'subscriber', 'author']) {
      const sess = who === 'visitor' ? visitor : s[who];
      const r = await sess.get(`${NS}/profile/users/${other.slug}`);
      check('REST profile hides the member email', who, !r.text.includes(email) && !(r.data && r.data.email), `GET ${r.status}`);
    }
  }

  /* 8. /profile/settings/: members only, every form needs its nonce. */
  {
    const r = await visitor.get('/profile/settings/');
    check('settings need a login', 'visitor', r.status === 302 && /wp-login\.php/.test(r.location || ''), `status ${r.status} -> ${r.location ? pathOf(r.location) : '(none)'}`);
    for (const [action, fields] of [['email', { email: 'taken-over@example.test', current_password: PW }], ['password', { current_password: PW, new_password: 'Hijack-pass-999', confirm_password: 'Hijack-pass-999' }], ['delete', { current_password: PW, confirm: '1' }]]) {
      const p = await s.subscriber.request('POST', '/profile/settings/', { body: Object.assign({ pgp_action: action }, fields) });
      check(`settings "${action}" form refused without nonce`, 'subscriber', p.status === 403, `POST ${p.status}`);
    }
    const again = await new Session('subscriber-again').login(U.subscriber.login, PW).then(() => true, () => false);
    check('password unchanged after the refused forms', 'subscriber', again, again ? 'login still works' : 'login failed');
  }

  /* 9. A few more writes that must stay closed. */
  for (const who of ['visitor', 'subscriber', 'contributor', 'author']) {
    const sess = who === 'visitor' ? visitor : s[who];
    const r = await sess.request('POST', `${NS}/courses`, { json: { title: `E2E course by ${who}`, status: 'draft' } });
    check('REST create course refused', who, r.status === 401 || r.status === 403, `POST /courses ${r.status} ${r.data && r.data.code || ''}`);
  }
  {
    const r = await s.unverified.request('POST', `${NS}/profile/users/${U.editor.slug}/follow`);
    check('unverified member cannot follow', 'unverified', r.status === 403, `POST follow ${r.status} ${r.data && r.data.code || ''}`);
  }

  /* 10. Registration at /register/ (card #6338): the form checks, nobody is created by a refused post,
        WordPress' own registration URL leads here, and a good post makes a plain, logged-in member. */
  {
    const hidden = html => {
      const f = {};
      for (const n of ['_pgr_nonce', 'pg_form_time', 'pg_form_token']) {
        const m = new RegExp(`name="${n}" value="([^"]*)"`).exec(html);
        f[n] = m ? m[1] : '';
      }
      return f;
    };
    const wait = ms => new Promise(res => setTimeout(res, ms));
    const loggedIn = sess => [...sess.jar.keys()].some(k => k.startsWith('wordpress_logged_in_'));
    const reg = new Session('registrant');
    const page = await reg.get('/register/');
    check('register page: form for visitors', 'visitor', page.status === 200 && /name="password"/.test(page.text) && /autocomplete="new-password"/.test(page.text) && /noindex/.test(page.text), `GET ${page.status}`);
    const core = await new Session('core-register').get('/wp-login.php?action=register');
    check('wp-login.php?action=register leads to /register/', 'visitor', core.status === 302 && pathOf(core.location) === '/register/', `status ${core.status} -> ${core.location ? pathOf(core.location) : '(none)'}`);
    const member = await s.subscriber.get('/register/');
    check('register page sends members to their profile', 'subscriber', member.status === 302 && pathOf(member.location) === '/profile/', `status ${member.status} -> ${member.location ? pathOf(member.location) : '(none)'}`);

    await wait(2500); // the form refuses a post in under two seconds
    const f = hidden(page.text);
    const good = { name: 'Rita Registrant', email: 'rita.registrant@example.test', password: 'blue lanterns drift home' };
    const tries = [
      ['without the nonce', Object.assign({}, f, good, { _pgr_nonce: '' }), /expired/i],
      ['with a forged form token', Object.assign({}, f, good, { pg_form_token: 'forged' }), /Something went wrong/i],
      ['with the honeypot filled', Object.assign({}, f, good, { pg_website_url: 'http://spam.example' }), /Something went wrong/i],
      ['with a weak password', Object.assign({}, f, good, { password: 'password123' }), /most common/i],
      ['with a bad email', Object.assign({}, f, good, { email: 'not-an-email' }), /valid email/i],
      ['with an email that is taken', Object.assign({}, f, good, { email: U.subscriber.login + '@example.test' }), /already an account/i],
    ];
    for (const [label, body, expect] of tries) {
      const r = await reg.request('POST', '/register/', { body });
      check(`register ${label} is refused`, 'visitor', r.status === 200 && expect.test(r.text) && !loggedIn(reg) && !/name="password"[^>]*value="[^"]+"/.test(r.text), `POST ${r.status}${loggedIn(reg) ? ' LOGGED IN' : ''}`);
    }

    const ok = await reg.request('POST', '/register/', { body: Object.assign({}, f, good) });
    check('register with good details logs in and goes to the wizard', 'visitor', ok.status === 302 && pathOf(ok.location) === '/profile/welcome/' && loggedIn(reg), `POST ${ok.status} -> ${ok.location ? pathOf(ok.location) : '(none)'}`);
    const welcome = await reg.get('/profile/welcome/');
    check('the new member sees the onboarding wizard and the verification banner', 'new member', welcome.status === 200 && /Set up your profile/.test(welcome.text) && /Please confirm your email address/.test(welcome.text), `GET ${welcome.status}`);
    const own = await reg.get('/profile/');
    check('the new member has a profile page', 'new member', own.status === 302 && /^\/in\/rita-registrant[^/]*\/$/.test(pathOf(own.location)), `status ${own.status} -> ${own.location ? pathOf(own.location) : '(none)'}`);
    const admin = await reg.get('/wp-admin/edit.php?post_type=pg_contact_msg');
    check('the new member is a plain member (no editor screens)', 'new member', refused(admin) && !admin.text.includes(FX.contact_title), `status ${admin.status}`);
    const again = await new Session('rita-login').login('rita-registrant', good.password).then(() => 'ok', () => 'no');
    const byEmail = again === 'ok' ? 'ok' : await new Session('rita-login-email').login(good.email, good.password).then(() => 'ok', () => 'no');
    check('the new member can log in again with the chosen password', 'new member', byEmail === 'ok', byEmail === 'ok' ? 'login works' : 'login failed');
    const twice = await new Session('registrant-2').get('/register/');
    const f2 = hidden(twice.text);
    await wait(2500);
    const dup = await new Session('registrant-2').request('POST', '/register/', { body: Object.assign({}, f2, good) });
    check('registering the same email again is refused', 'visitor', dup.status === 200 && /already an account/i.test(dup.text), `POST ${dup.status}`);
  }

  /* report */
  const w = Math.max(...results.map(r => r.rule.length)) + 2;
  for (const r of results) console.log(`${r.pass ? 'PASS' : 'FAIL'}  ${r.rule.padEnd(w)}${r.who.padEnd(13)}${r.detail}`);
  const failed = results.filter(r => !r.pass);
  console.log(`\n${results.length - failed.length}/${results.length} checks passed`);
  if (failed.length) {
    for (const r of failed) console.log(`::error::role matrix: ${r.rule} (${r.who}): ${r.detail}`);
    process.exit(1);
  }
})().catch(e => { console.error(e); process.exit(1); });

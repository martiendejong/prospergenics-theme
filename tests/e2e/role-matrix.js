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
  async request(method, path, { body, json, raw, follow = false, headers = {} } = {}) {
    let url = path.startsWith('http') ? path : BASE + path;
    const chain = [];
    for (let hop = 0; hop < 8; hop++) {
      const h = Object.assign({ Cookie: this.cookieHeader() }, headers);
      let payload;
      if (json !== undefined) { h['Content-Type'] = 'application/json'; payload = JSON.stringify(json); }
      else if (body !== undefined) { h['Content-Type'] = 'application/x-www-form-urlencoded'; payload = new URLSearchParams(body).toString(); }
      else if (raw !== undefined) { payload = raw; } // a file upload: the caller sets Content-Type
      if (this.nonce && url.includes('/wp-json/')) h['X-WP-Nonce'] = this.nonce;
      const res = await fetch(url, { method, headers: h, body: payload, redirect: 'manual' });
      this.store(res);
      const text = await res.text();
      const location = res.headers.get('location');
      chain.push({ status: res.status, url, location });
      if (follow && location && res.status >= 300 && res.status < 400) {
        url = new URL(location, url).toString(); method = 'GET'; body = json = raw = undefined;
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
// A valid nonce for this user (tests/e2e/mu-plugin.php, throwaway site only): with it a refusal
// proves the capability check behind check_admin_referer(), not just the nonce.
async function mint(sess, action) {
  const r = await sess.get(`/wp-admin/admin-ajax.php?action=pg_e2e_nonce&for=${encodeURIComponent(action)}`);
  return r.status === 200 && /^[0-9a-f]{10}$/.test(r.text.trim()) ? r.text.trim() : '';
}
const ids = r => (Array.isArray(r.data) ? r.data : (r.data && Array.isArray(r.data.data) ? r.data.data : [])).map(m => m.id);
const decode = h => h.replace(/&#0?38;|&amp;/g, '&');
const PNG = Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==', 'base64');

(async () => {
  const U = FX.users, PW = FX.password;
  const visitor = new Session('visitor');
  const s = {};
  for (const key of ['admin', 'editor', 'author', 'contributor', 'subscriber', 'subscriber2', 'unverified', 'unverified2', 'writer', 'applicant']) {
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

  /* 10. pg_writer (inc/articles/roles.php): writes and submits articles, uploads own images; nothing else. */
  {
    const W = s.writer;
    const draft = await W.request('POST', '/wp-json/wp/v2/pg_article', { json: { title: 'E2E writer draft', content: 'Written by the writer.', status: 'draft' } });
    const mine = draft.data && draft.data.id;
    check('writer creates a draft article (control)', 'writer', draft.status === 201 && mine, `POST pg_article ${draft.status} ${draft.data && draft.data.code || ''}`);
    const submit = await W.request('POST', `/wp-json/wp/v2/pg_article/${mine}`, { json: { status: 'pending' } });
    check('writer submits it for review (control)', 'writer', submit.status === 200 && submit.data.status === 'pending', `status ${submit.status} -> ${submit.data && submit.data.status}`);
    const pub = await W.request('POST', `/wp-json/wp/v2/pg_article/${mine}`, { json: { status: 'publish' } });
    check('writer cannot publish over REST', 'writer', pub.status === 403, `POST status=publish ${pub.status} ${pub.data && pub.data.code || ''}`);
    const pubNew = await W.request('POST', '/wp-json/wp/v2/pg_article', { json: { title: 'E2E writer straight to publish', status: 'publish' } });
    check('writer cannot create a published article', 'writer', pubNew.status === 403, `POST ${pubNew.status} ${pubNew.data && pubNew.data.code || ''}`);
    const pn = await mint(W, `pg_articles_publish_${mine}`);
    const pubAction = await W.request('POST', '/wp-admin/admin-post.php', { body: { action: 'pg_articles_publish', post_id: String(mine), _wpnonce: pn } });
    check('writer cannot publish from My articles, even with a valid nonce', 'writer', pn && pubAction.status === 403, `POST ${pubAction.status}`);
    const still = await s.admin.get(`/wp-json/wp/v2/pg_article/${mine}?context=edit`);
    check('the writer\'s article is still pending review', 'admin', still.status === 200 && still.data.status === 'pending', `status ${still.data && still.data.status}`);

    const other = await W.request('POST', `/wp-json/wp/v2/pg_article/${FX.article}`, { json: { title: 'Hijacked by writer' } });
    check('writer cannot edit someone else\'s article (REST)', 'writer', other.status === 403, `POST ${other.status} ${other.data && other.data.code || ''}`);
    const otherScreen = await W.get(`/wp-admin/post.php?post=${FX.article}&action=edit`);
    check('writer cannot open someone else\'s article in the editor', 'writer', refused(otherScreen), `status ${otherScreen.status}`);
    const un = await mint(W, `pg_articles_unpublish_${FX.article}`);
    const unAction = await W.request('POST', '/wp-admin/admin-post.php', { body: { action: 'pg_articles_unpublish', post_id: String(FX.article), _wpnonce: un } });
    check('writer cannot unpublish someone else\'s article, even with a valid nonce', 'writer', un && unAction.status === 403, `POST ${unAction.status}`);

    const up = await W.request('POST', '/wp-json/wp/v2/media', { raw: PNG, headers: { 'Content-Type': 'image/png', 'Content-Disposition': 'attachment; filename="e2e-writer.png"' } });
    const img = up.data && up.data.id;
    check('writer uploads an image (control)', 'writer', up.status === 201 && img, `POST media ${up.status} ${up.data && up.data.code || ''}`);
    const lib = await W.get('/wp-json/wp/v2/media?per_page=100');
    check('writer\'s media library (REST): own image only', 'writer', lib.status === 200 && ids(lib).includes(img) && !ids(lib).includes(FX.media), `GET ${lib.status}, ids ${ids(lib).join(',')}`);
    const modal = await W.request('POST', '/wp-admin/admin-ajax.php', { body: { action: 'query-attachments', 'query[posts_per_page]': '100' } });
    check('writer\'s media modal: own image only', 'writer', modal.data && modal.data.success && ids(modal).includes(img) && !ids(modal).includes(FX.media), `ids ${ids(modal).join(',')}`);
    const screen = await W.get('/wp-admin/upload.php?mode=list');
    check('writer\'s Media screen: not the admin\'s image', 'writer', screen.status === 200 && !screen.text.includes(FX.media_title), `status ${screen.status}`);
    const alt = await W.request('POST', `/wp-json/wp/v2/media/${FX.media}`, { json: { alt_text: 'Hijacked by writer' } });
    check('writer cannot edit someone else\'s image', 'writer', alt.status === 403, `POST ${alt.status} ${alt.data && alt.data.code || ''}`);
    const adminLib = await s.admin.get('/wp-json/wp/v2/media?per_page=100');
    check('the admin sees every image (control)', 'admin', ids(adminLib).includes(FX.media) && ids(adminLib).includes(img), `ids ${ids(adminLib).join(',')}`);

    const cm = await W.get(contactList);
    check('contact messages screen refused', 'writer', refused(cm) && !cm.text.includes(FX.contact_title), `status ${cm.status}${cm.location ? ' -> ' + pathOf(cm.location) : ''}`);
    const users = await W.get('/wp-admin/users.php');
    check('Users screen refused', 'writer', refused(users) && !users.text.includes(U.subscriber2.name), `status ${users.status}`);
  }

  /* 11. Users → Requests (inc/profiles/applications.php): promote_users only, nonce + edit_user. */
  {
    const A = U.applicant, reqScreen = '/wp-admin/users.php?page=pg-applications';
    for (const who of ['editor', 'author', 'contributor', 'writer', 'subscriber', 'applicant']) {
      const r = await s[who].get(reqScreen);
      check('Requests screen refused', who, refused(r) && !r.text.includes('E2E: I would like to'), `status ${r.status}${r.location ? ' -> ' + pathOf(r.location) : ''}`);
    }
    const screen = await s.admin.get(reqScreen);
    check('Requests screen lists the open requests (control)', 'admin', screen.status === 200 && screen.text.includes(A.name) && screen.text.includes('E2E: I would like to write') && screen.text.includes('E2E: I would like to teach'), `status ${screen.status}`);
    const decide = (sess, type, decision, extra = {}) => sess.request('POST', '/wp-admin/admin-post.php', { body: Object.assign({ action: 'pg_profiles_decide', user_id: String(A.id), type, decision }, extra) });
    for (const who of ['editor', 'subscriber', 'applicant']) {
      const plain = await decide(s[who], 'write', 'approve');
      check('approving a request without the nonce is refused', who, plain.status === 403, `POST ${plain.status}`);
      const n = await mint(s[who], `pg_profiles_decide_${A.id}_write`);
      const r = await decide(s[who], 'write', 'approve', { _wpnonce: n });
      check('approving a request with a valid nonce but no promote_users is refused', who, n && r.status === 403, `POST ${r.status}`);
    }
    const before = await s.applicant.get('/wp-admin/post-new.php?post_type=pg_article');
    check('the applicant cannot write yet', 'applicant', refused(before), `status ${before.status}${before.location ? ' -> ' + pathOf(before.location) : ''}`);

    const nonceOf = (html, type) => { const m = new RegExp(`name="user_id" value="${A.id}" />\\s*<input type="hidden" name="type" value="${type}" />[\\s\\S]*?name="_wpnonce" value="([0-9a-f]+)"`).exec(html); return m ? m[1] : ''; };
    const ok = await decide(s.admin, 'write', 'approve', { _wpnonce: nonceOf(screen.text, 'write') });
    check('the admin approves "write" (control)', 'admin', ok.status === 302 && /pg_done=approved/.test(ok.location || ''), `POST ${ok.status} -> ${ok.location || ''}`);
    const after = await s.applicant.get('/wp-admin/post-new.php?post_type=pg_article');
    check('approved: the applicant can write now (pg_writer)', 'applicant', after.status === 200, `status ${after.status}`);
    const no = await decide(s.admin, 'teach', 'decline', { _wpnonce: nonceOf(screen.text, 'teach'), note: 'E2E: not yet' });
    check('the admin declines "teach" (control)', 'admin', no.status === 302 && /pg_done=declined/.test(no.location || ''), `POST ${no.status} -> ${no.location || ''}`);
    const dash = await s.applicant.get('/instructor/dashboard/');
    check('declined: the applicant has no instructor tools', 'applicant', !/Create your first course|Courses I teach/.test(dash.text), `status ${dash.status}`);
  }

  /* 12. Users screen row actions (inc/profiles/users-admin.php): nonce + edit_user for that member. */
  {
    const T = U.unverified2;
    const act = (sess, d, uid, nonce) => sess.get(`/wp-admin/admin-post.php?action=pg_profiles_user&do=${d}&user_id=${uid}${nonce ? `&_wpnonce=${nonce}` : ''}`);
    for (const who of ['editor', 'author', 'subscriber']) {
      const r = await s[who].get('/wp-admin/users.php');
      check('Users screen refused', who, refused(r) && !r.text.includes(T.name), `status ${r.status}${r.location ? ' -> ' + pathOf(r.location) : ''}`);
    }
    for (const who of ['admin', 'editor']) {
      const r = await act(s[who], 'verify', T.id);
      check('"Mark verified" without the nonce is refused', who, r.status === 403, `GET ${r.status}`);
    }
    for (const [who, d, target] of [['editor', 'verify', T], ['author', 'hide', U.subscriber2], ['subscriber', 'hide', U.subscriber2], ['writer', 'resend', T]]) {
      const n = await mint(s[who], `pg_profiles_user_${d}_${target.id}`);
      const r = await act(s[who], d, target.id, n);
      check(`"${d}" on another member with a valid nonce but no edit_user is refused`, who, n && r.status === 403, `GET ${r.status}`);
    }
    // on themselves: edit_user is true for your own account, so the handler must ask for more
    {
      const n = await mint(s.unverified, `pg_profiles_user_verify_${U.unverified.id}`);
      const r = await act(s.unverified, 'verify', U.unverified.id, n);
      const f = await s.unverified.request('POST', `${NS}/profile/users/${U.editor.slug}/follow`);
      check('an unverified member cannot mark themselves verified', 'unverified', n && r.status === 403 && f.status === 403, `GET ${r.status}, then follow ${f.status}`);
    }

    const list = await s.admin.get('/wp-admin/users.php');
    const link = d => { const m = new RegExp(`href="([^"]*action=pg_profiles_user(?:&#0?38;|&amp;|&)do=${d}(?:&#0?38;|&amp;|&)user_id=${T.id}(?:&#0?38;|&amp;|&)_wpnonce=[0-9a-f]+)"`).exec(list.text); return m ? decode(m[1]) : ''; };
    check('the Users screen offers Resend / Mark verified / Hide for the member (control)', 'admin', list.status === 200 && link('resend') && link('verify') && link('hide'), `status ${list.status}`);
    const resend = await s.admin.get(link('resend'));
    check('the admin resends the confirmation link (control)', 'admin', resend.status === 302 && /pg_user_done=sent/.test(resend.location || ''), `GET ${resend.status} -> ${resend.location || ''}`);
    const beforeF = await s.unverified2.request('POST', `${NS}/profile/users/${U.editor.slug}/follow`);
    const verify = await s.admin.get(link('verify'));
    const afterF = await s.unverified2.request('POST', `${NS}/profile/users/${U.editor.slug}/follow`);
    check('the admin marks the member verified (control)', 'admin', verify.status === 302 && /pg_user_done=verified/.test(verify.location || '') && beforeF.status === 403 && afterF.status === 200, `GET ${verify.status}, follow before ${beforeF.status}, after ${afterF.status}`);

    const seenBefore = await s.subscriber.get(`/in/${T.slug}/`, { follow: true });
    const hide = await s.admin.get(link('hide'));
    const seen = await s.subscriber.get(`/in/${T.slug}/`, { follow: true });
    check('the admin hides the profile (control)', 'admin', seenBefore.text.includes(T.name) && hide.status === 302 && /pg_user_done=hidden/.test(hide.location || '') && !seen.text.includes(T.name), `member saw it before: ${seenBefore.text.includes(T.name)}, GET ${hide.status}, after: ${seen.text.includes(T.name)}`);
    const n = await mint(s.unverified2, `pg_profiles_user_unhide_${T.id}`);
    const self = await act(s.unverified2, 'unhide', T.id, n);
    const list2 = await s.admin.get('/wp-admin/users.php');
    const m = new RegExp(`href="([^"]*action=pg_profiles_user(?:&#0?38;|&amp;|&)do=unhide(?:&#0?38;|&amp;|&)user_id=${T.id}(?:&#0?38;|&amp;|&)_wpnonce=[0-9a-f]+)"`).exec(list2.text);
    check('a hidden member cannot show their own profile again', 'unverified2', n && self.status === 403 && m, `GET ${self.status}, still hidden: ${!!m}`);
    const show = m ? await s.admin.get(decode(m[1])) : { status: 0 };
    check('the admin shows the profile again (control)', 'admin', show.status === 302 && /pg_user_done=shown/.test(show.location || ''), `GET ${show.status} -> ${show.location || ''}`);
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

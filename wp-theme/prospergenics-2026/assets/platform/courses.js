/*
 * Prospergenics courses track (cards #6355-#6364): small progressive enhancements on the course
 * pages, and the instructor course wizard (#6363). Everything except the wizard works without
 * this script.
 */
(function () {
  'use strict';

  // Confirm destructive form actions (unenrol, unpublish, delete).
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var msg = form && form.getAttribute && form.getAttribute('data-pgc-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });

  // Lesson page: collapse the course contents on small screens behind a toggle.
  (function () {
    var btn = document.querySelector('.pgc-side-toggle');
    var nav = document.getElementById('pgc-contents');
    if (!btn || !nav) { return; }
    var mq = window.matchMedia('(max-width: 880px)');
    function setOpen(open) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      nav.hidden = !open;
    }
    function apply() {
      if (mq.matches) { btn.hidden = false; setOpen(false); }
      else { btn.hidden = true; nav.hidden = false; }
    }
    btn.addEventListener('click', function () { setOpen(btn.getAttribute('aria-expanded') !== 'true'); });
    if (mq.addEventListener) { mq.addEventListener('change', apply); }
    apply();
  })();

  /* ------------------------------------------------------------------ course wizard */
  var cfg = window.pgCoursesWizard;
  var form = document.getElementById('pgc-wizard');
  if (!cfg || !form) { return; }
  form.hidden = false;

  var keySeq = 0;
  function newKey(prefix) { keySeq += 1; return prefix + Date.now().toString(36) + keySeq; }
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  var c = cfg.course;
  var state = {
    id: c ? c.id : 0,
    status: c ? c.status : 'draft',
    link: c ? c.link : '',
    thumbnail: c && c.thumbnail ? c.thumbnail : null,
    modules: c && c.modules ? c.modules.map(function (m) {
      return {
        key: newKey('m'), id: m.id, title: m.title,
        lessons: (m.lessons || []).map(function (l) {
          return { key: newKey('l'), id: l.id, title: l.title, video_url: l.video_url || '', content: l.content_raw || '' };
        })
      };
    }) : []
  };
  var fields = {
    title: $('#pgc-title'), excerpt: $('#pgc-excerpt'), content: $('#pgc-content'),
    outcomes: $('#pgc-outcomes'), category: $('#pgc-category'), level: $('#pgc-level'), price: $('#pgc-price')
  };
  if (c) {
    fields.title.value = c.title || '';
    fields.excerpt.value = c.excerpt || '';
    fields.content.value = (c.content && c.content.raw) || '';
    fields.outcomes.value = c.outcomes_raw || '';
    fields.category.value = c.categories && c.categories[0] ? String(c.categories[0].id) : '0';
    fields.level.value = c.level || '';
    fields.price.value = c.price || 0;
  }
  if (!state.modules.length) {
    state.modules.push({ key: newKey('m'), id: 0, title: '', lessons: [{ key: newKey('l'), id: 0, title: '', video_url: '', content: '' }] });
  }

  var statusEl = $('#pgc-save-status');
  function say(text) { statusEl.textContent = text; }

  /* --- REST helper */
  function api(method, path, body) {
    var opts = { method: method, credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce } };
    if (body instanceof FormData) { opts.body = body; }
    else if (body) { opts.headers['Content-Type'] = 'application/json'; opts.body = JSON.stringify(body); }
    return fetch(cfg.root + path, opts).then(function (r) {
      return r.json().catch(function () { return {}; }).then(function (data) {
        if (!r.ok) { throw new Error(data && data.message ? data.message : 'HTTP ' + r.status); }
        return data;
      });
    });
  }

  /* --- video URL check (the server keeps only YouTube/Vimeo URLs) */
  function videoOk(url) {
    if (!url) { return true; }
    return /^https?:\/\/(www\.|m\.)?(youtube\.com\/(watch\?|embed\/|shorts\/|live\/)|youtu\.be\/|youtube-nocookie\.com\/embed\/|(player\.)?vimeo\.com\/)/i.test(url.trim());
  }

  /* --- curriculum editor */
  var modulesEl = $('#pgc-modules');
  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : String(s); return d.innerHTML; }

  function renderModules(focusSel) {
    var html = '';
    state.modules.forEach(function (m, mi) {
      var mn = mi + 1;
      html += '<fieldset class="pgc-module-edit" data-m="' + mi + '"><legend>Module ' + mn + '</legend>' +
        '<div class="pgc-field"><label for="' + m.key + '-title">Module ' + mn + ' title</label>' +
        '<input id="' + m.key + '-title" type="text" maxlength="200" data-field="mtitle" value="' + esc(m.title) + '" /></div>' +
        '<div class="pgc-lessons-edit">';
      m.lessons.forEach(function (l, li) {
        var ln = mn + '.' + (li + 1);
        var bad = !videoOk(l.video_url);
        html += '<fieldset class="pgc-lesson-edit" data-l="' + li + '"><legend>Lesson ' + ln + '</legend>' +
          '<div class="pgc-field"><label for="' + l.key + '-title">Lesson ' + ln + ' title</label>' +
          '<input id="' + l.key + '-title" type="text" maxlength="200" data-field="ltitle" value="' + esc(l.title) + '" /></div>' +
          '<div class="pgc-field"><label for="' + l.key + '-video">Video URL (YouTube or Vimeo, optional)</label>' +
          '<input id="' + l.key + '-video" type="url" inputmode="url" data-field="lvideo" value="' + esc(l.video_url) + '" aria-describedby="' + l.key + '-video-error"' + (bad ? ' aria-invalid="true"' : '') + ' />' +
          '<p id="' + l.key + '-video-error" class="pgc-field-error"' + (bad ? '' : ' hidden') + '>Use a YouTube or Vimeo link; other links are not saved.</p></div>' +
          '<div class="pgc-field"><label for="' + l.key + '-content">Lesson ' + ln + ' text</label>' +
          '<textarea id="' + l.key + '-content" rows="5" data-field="lcontent">' + esc(l.content) + '</textarea></div>' +
          '<div class="pgc-item-actions">' +
          '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="lesson-up"' + (li === 0 ? ' disabled' : '') + '>Move up<span class="screen-reader-text"> lesson ' + ln + '</span></button>' +
          '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="lesson-down"' + (li === m.lessons.length - 1 ? ' disabled' : '') + '>Move down<span class="screen-reader-text"> lesson ' + ln + '</span></button>' +
          '<button type="button" class="pgc-btn pgc-btn--danger pgc-btn--small" data-action="lesson-remove">Remove<span class="screen-reader-text"> lesson ' + ln + '</span></button>' +
          '</div></fieldset>';
      });
      html += '</div><div class="pgc-item-actions">' +
        '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="add-lesson">+ Add a lesson<span class="screen-reader-text"> to module ' + mn + '</span></button>' +
        '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="module-up"' + (mi === 0 ? ' disabled' : '') + '>Move module up<span class="screen-reader-text"> (module ' + mn + ')</span></button>' +
        '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="module-down"' + (mi === state.modules.length - 1 ? ' disabled' : '') + '>Move module down<span class="screen-reader-text"> (module ' + mn + ')</span></button>' +
        '<button type="button" class="pgc-btn pgc-btn--danger pgc-btn--small" data-action="module-remove">Remove module<span class="screen-reader-text"> ' + mn + '</span></button>' +
        '</div></fieldset>';
    });
    modulesEl.innerHTML = html || '<p class="pgc-help">No modules yet.</p>';
    if (focusSel) { var f = $(focusSel); if (f) { f.focus(); } }
  }

  function locate(el) {
    var mEl = el.closest('[data-m]');
    var lEl = el.closest('[data-l]');
    return { mi: mEl ? +mEl.getAttribute('data-m') : -1, li: lEl ? +lEl.getAttribute('data-l') : -1 };
  }
  function swap(arr, i, j) { var t = arr[i]; arr[i] = arr[j]; arr[j] = t; }

  modulesEl.addEventListener('input', function (e) {
    var t = e.target, p = locate(t), field = t.getAttribute('data-field');
    if (p.mi < 0 || !field) { return; }
    var m = state.modules[p.mi];
    if (field === 'mtitle') { m.title = t.value; }
    else {
      var l = m.lessons[p.li];
      if (field === 'ltitle') { l.title = t.value; }
      if (field === 'lcontent') { l.content = t.value; }
      if (field === 'lvideo') {
        l.video_url = t.value;
        var ok = videoOk(t.value);
        t.toggleAttribute('aria-invalid', !ok);
        var err = document.getElementById(t.getAttribute('aria-describedby'));
        if (err) { err.hidden = ok; }
      }
    }
    changed();
  });

  form.addEventListener('click', function (e) {
    var b = e.target.closest('button[data-action], button[data-goto]');
    if (!b || !form.contains(b)) { return; }
    var a = b.getAttribute('data-action');
    if (b.hasAttribute('data-goto')) { go(+b.getAttribute('data-goto')); return; }
    var p = locate(b), m = state.modules[p.mi];
    switch (a) {
      case 'add-module':
        state.modules.push({ key: newKey('m'), id: 0, title: '', lessons: [{ key: newKey('l'), id: 0, title: '', video_url: '', content: '' }] });
        renderModules('#' + state.modules[state.modules.length - 1].key + '-title'); changed(); break;
      case 'add-lesson':
        m.lessons.push({ key: newKey('l'), id: 0, title: '', video_url: '', content: '' });
        renderModules('#' + m.lessons[m.lessons.length - 1].key + '-title'); changed(); break;
      case 'lesson-up': case 'lesson-down':
        var to = a === 'lesson-up' ? p.li - 1 : p.li + 1;
        if (to < 0 || to >= m.lessons.length) { return; }
        swap(m.lessons, p.li, to);
        renderModules('[data-m="' + p.mi + '"] [data-l="' + to + '"] [data-action="' + a + '"]:not([disabled]), [data-m="' + p.mi + '"] [data-l="' + to + '"] input');
        changed(); break;
      case 'lesson-remove':
        if ((m.lessons[p.li].title || m.lessons[p.li].content) && !window.confirm('Remove this lesson?')) { return; }
        m.lessons.splice(p.li, 1);
        renderModules('[data-m="' + p.mi + '"] [data-action="add-lesson"]'); changed(); say('Lesson removed.'); break;
      case 'module-up': case 'module-down':
        var mt = a === 'module-up' ? p.mi - 1 : p.mi + 1;
        if (mt < 0 || mt >= state.modules.length) { return; }
        swap(state.modules, p.mi, mt);
        renderModules('[data-m="' + mt + '"] input'); changed(); break;
      case 'module-remove':
        if (!window.confirm('Remove this module and its lessons?')) { return; }
        state.modules.splice(p.mi, 1);
        renderModules('[data-action="add-module"]'); changed(); say('Module removed.'); break;
      case 'next': if (validateStep(current)) { go(current + 1); } break;
      case 'back': go(current - 1); break;
      case 'save': save(state.status === 'publish' ? 'publish' : 'draft', true); break;
      case 'publish': save(state.status === 'publish' ? 'draft' : 'publish', true); break;
    }
  });

  /* --- steps */
  var current = 1;
  function validateStep(n) {
    if (n === 1 && !fields.title.value.trim()) {
      fields.title.setAttribute('aria-invalid', 'true');
      $('#pgc-title-error').hidden = false;
      fields.title.focus();
      return false;
    }
    fields.title.removeAttribute('aria-invalid');
    $('#pgc-title-error').hidden = true;
    return true;
  }
  function go(n, noFocus) {
    if (n < 1 || n > 3) { return; }
    if (n > 1 && !validateStep(1)) { n = 1; }
    current = n;
    $all('.pgc-step', form).forEach(function (s) { s.hidden = +s.getAttribute('data-step') !== n; });
    $all('[data-goto]', form).forEach(function (b) {
      if (+b.getAttribute('data-goto') === n) { b.setAttribute('aria-current', 'step'); } else { b.removeAttribute('aria-current'); }
    });
    $('[data-action="back"]', form).hidden = n === 1;
    $('[data-action="next"]', form).hidden = n === 3;
    var h = $('.pgc-step[data-step="' + n + '"] h2', form);
    if (h && !noFocus) { h.focus(); }
    if (dirty && autosave()) { save(); }
  }

  /* --- saving */
  var dirty = false, saving = null, again = false, timer = null;
  function autosave() { return state.status !== 'publish'; }
  function changed() {
    dirty = true;
    clearTimeout(timer);
    if (autosave()) { timer = setTimeout(function () { save(); }, 1500); }
    else { say('Unsaved changes. This course is live: press "Save changes" to update it.'); }
  }
  Object.keys(fields).forEach(function (k) { fields[k].addEventListener('input', changed); fields[k].addEventListener('change', changed); });

  function coursePayload() {
    return {
      title: fields.title.value.trim(),
      excerpt: fields.excerpt.value,
      content: fields.content.value,
      outcomes: fields.outcomes.value,
      category: parseInt(fields.category.value, 10) || 0,
      level: fields.level.value,
      price: Math.max(0, parseFloat(fields.price.value) || 0)
    };
  }
  function curriculumPayload() {
    return {
      modules: state.modules.map(function (m) {
        return { key: m.key, id: m.id || 0, title: m.title, lessons: m.lessons.map(function (l) {
          return { key: l.key, id: l.id || 0, title: l.title, video_url: videoOk(l.video_url) ? l.video_url : '', content: l.content };
        }) };
      })
    };
  }
  function applyIds(res) {
    (res.modules || []).forEach(function (rm) {
      state.modules.forEach(function (m) {
        if (m.key === rm.key) {
          m.id = rm.id;
          (rm.lessons || []).forEach(function (rl) { m.lessons.forEach(function (l) { if (l.key === rl.key) { l.id = rl.id; } }); });
        }
      });
    });
  }
  function time() { var d = new Date(); return ('0' + d.getHours()).slice(-2) + ':' + ('0' + d.getMinutes()).slice(-2); }

  /**
   * Save the course and its curriculum. status: 'draft' | 'publish' | undefined (keep).
   * Saves are serialised: a change during a save triggers one more save afterwards.
   */
  function save(status, explicit) {
    if (saving) { again = true; return saving; }
    var body = coursePayload();
    if (!body.title) {
      if (explicit) { go(1); validateStep(1); }
      say('Add a title to save your draft.');
      return Promise.resolve();
    }
    if (status) { body.status = status; }
    clearTimeout(timer);
    dirty = false;
    say('Saving…');
    var first = !state.id;
    saving = (first ? api('POST', '', body) : api('PUT', '/' + state.id, body))
      .then(function (course) {
        state.id = course.id; state.status = course.status; state.link = course.link;
        if (first && window.history && history.replaceState) { history.replaceState(null, '', cfg.editBase + state.id + '/edit/'); }
        return api('PUT', '/' + state.id + '/curriculum', curriculumPayload());
      })
      .then(function (res) {
        applyIds(res);
        updatePublishUi();
        say(state.status === 'publish'
          ? (explicit && status === 'publish' ? 'Published at ' + time() + '.' : 'Changes saved at ' + time() + '.')
          : (explicit && status === 'draft' ? 'Saved as draft at ' + time() + '.' : 'Draft saved automatically at ' + time() + '.'));
      })
      .catch(function (err) { dirty = true; say('Could not save: ' + err.message); })
      .then(function () {
        saving = null;
        if (again) { again = false; if (dirty && autosave()) { save(); } }
      });
    return saving;
  }

  function updatePublishUi() {
    var live = state.status === 'publish';
    $('[data-action="save"]', form).textContent = live ? 'Save changes' : 'Save as draft';
    var pub = $('[data-action="publish"]', form);
    pub.textContent = live ? 'Unpublish' : 'Publish';
    pub.hidden = !live && !cfg.canPublish;
    $('#pgc-publish-state').textContent = !state.id ? 'Not saved yet.' : (live ? 'This course is published.' : 'This course is a draft: only you and editors can see it.');
    var view = $('#pgc-view-link');
    if (state.link) { view.href = state.link; view.hidden = false; view.textContent = live ? 'View course' : 'Preview course'; }
  }

  /* --- thumbnail */
  var thumbInput = $('#pgc-thumb'), preview = $('#pgc-thumb-preview');
  function showThumb() { preview.innerHTML = state.thumbnail && state.thumbnail.url ? '<img src="' + esc(state.thumbnail.url) + '" alt="" />' : ''; }
  thumbInput.addEventListener('change', function () {
    var file = thumbInput.files && thumbInput.files[0];
    if (!file) { return; }
    if (file.size > 5 * 1024 * 1024) { say('The image must be 5 MB or smaller.'); thumbInput.value = ''; return; }
    var ready = state.id ? Promise.resolve() : save();
    ready.then(function () {
      if (!state.id) { return; }
      var fd = new FormData();
      fd.append('file', file);
      say('Uploading the image…');
      return api('POST', '/' + state.id + '/thumbnail', fd).then(function (res) {
        state.thumbnail = res; showThumb(); say('Image uploaded.');
      });
    }).catch(function (err) { say('Could not upload the image: ' + err.message); });
  });

  window.addEventListener('beforeunload', function (e) {
    if (dirty || saving) { e.preventDefault(); e.returnValue = ''; }
  });

  renderModules();
  showThumb();
  updatePublishUi();
  go(1, true);
  if (state.id) { say(state.status === 'publish' ? 'Editing a published course.' : 'Editing a draft. Changes save automatically.'); }
})();

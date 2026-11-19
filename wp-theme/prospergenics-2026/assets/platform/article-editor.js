/* Prospergenics Platform: the front-end article editor (/articles/write/, card #6459).
   A contenteditable body with a small toolbar, autosave every 30 s while there are unsaved
   changes, a warning before leaving with unsaved changes, image upload (max 5 MB) and the
   save / preview / submit / publish buttons. All rules are enforced again on the server. */
(function () {
  'use strict';
  var cfg = window.pgWrite || {};
  var form = document.getElementById('pgw-form');
  if (!form || !cfg.saveUrl) return;

  var body = document.getElementById('pgw-body');
  var title = document.getElementById('pgw-title');
  var errBox = document.getElementById('pgw-error');
  var savedEl = document.getElementById('pgw-saved');
  var statusEl = document.getElementById('pgw-status');
  var coverInput = document.getElementById('pgw-cover');
  var coverFile = document.getElementById('pgw-cover-file');
  var coverPrev = document.getElementById('pgw-cover-preview');
  var coverRemove = document.getElementById('pgw-cover-remove');
  var imageFile = document.getElementById('pgw-image-file');
  var canPublish = form.getAttribute('data-can-publish') === '1';
  var postId = parseInt(form.getAttribute('data-id'), 10) || 0;
  var status = form.getAttribute('data-status') || 'draft';
  var maxImage = parseInt(cfg.maxImage, 10) || 5242880;
  var dirty = false, saving = false, leaving = false, queued = null;
  var range = null; // last selection inside the body, for toolbar clicks that steal focus

  function showError(msg) { errBox.textContent = msg || ''; errBox.hidden = !msg; }
  function markDirty() { dirty = true; savedEl.textContent = 'Unsaved changes'; }
  function field(name) { return form.elements[name]; }

  function setStatus(s, label) {
    status = s;
    form.setAttribute('data-status', s);
    statusEl.className = 'pga-status pga-status-' + s;
    statusEl.textContent = label;
    var submit = form.querySelector('[data-action="submit"]');
    if (submit) submit.hidden = ['pending', 'publish', 'future'].indexOf(s) !== -1;
  }

  // ---- selection helpers -------------------------------------------------
  document.addEventListener('selectionchange', function () {
    var sel = window.getSelection();
    if (sel.rangeCount && body.contains(sel.anchorNode)) range = sel.getRangeAt(0).cloneRange();
  });
  function restoreRange() {
    body.focus();
    if (range) { var sel = window.getSelection(); sel.removeAllRanges(); sel.addRange(range); }
  }
  function exec(cmd, val) { restoreRange(); document.execCommand(cmd, false, val || null); markDirty(); }
  function esc(s) { var d = document.createElement('div'); d.textContent = s; return d.innerHTML; }
  function safeUrl(u) { return /^(https?:\/\/|mailto:|\/)/i.test(u) ? u : ''; }

  // ---- toolbar -----------------------------------------------------------
  form.querySelector('.pgw-toolbar').addEventListener('mousedown', function (e) {
    if (e.target.closest('button')) e.preventDefault(); // keep the selection in the text
  });
  form.querySelector('.pgw-toolbar').addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-cmd]');
    if (!btn) return;
    var cmd = btn.getAttribute('data-cmd');
    switch (cmd) {
      case 'h2': case 'h3': case 'p': exec('formatBlock', cmd); break;
      case 'bold': case 'italic': exec(cmd); break;
      case 'ul': exec('insertUnorderedList'); break;
      case 'ol': exec('insertOrderedList'); break;
      case 'quote': exec('formatBlock', 'blockquote'); break;
      case 'pre': exec('formatBlock', 'pre'); break;
      case 'unlink': exec('unlink'); break;
      case 'code':
        restoreRange();
        var text = window.getSelection().toString();
        if (text) { document.execCommand('insertHTML', false, '<code>' + esc(text) + '</code>'); markDirty(); }
        break;
      case 'link':
        var url = window.prompt('Link address (https://...)', 'https://');
        url = url ? safeUrl(url.trim()) : '';
        if (url) exec('createLink', url); else if (url !== null) showError('Links must start with https://, http:// or mailto:');
        break;
      case 'image': imageFile.click(); break;
    }
  });

  // paste as plain text: pasted pages bring styles and scripts we do not want
  body.addEventListener('paste', function (e) {
    var t = (e.clipboardData || window.clipboardData);
    if (!t) return;
    e.preventDefault();
    document.execCommand('insertText', false, t.getData('text/plain'));
  });
  body.addEventListener('input', markDirty);
  ['pgw-title', 'pgw-category', 'pgw-series', 'pgw-tags', 'pgw-excerpt', 'pgw-date'].forEach(function (id) {
    var el = document.getElementById(id);
    if (el) el.addEventListener('input', markDirty);
  });

  // ---- uploads -----------------------------------------------------------
  function upload(file) {
    if (!file) return Promise.reject(new Error('No file'));
    if (!/^image\/(jpeg|png|gif|webp)$/.test(file.type)) return Promise.reject(new Error('Only JPG, PNG, GIF or WebP images can be uploaded.'));
    if (file.size > maxImage) return Promise.reject(new Error('The image is larger than 5 MB. Please choose a smaller one.'));
    var fd = new FormData();
    fd.append('file', file);
    return fetch(cfg.uploadUrl, { method: 'POST', credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce }, body: fd })
      .then(function (r) { return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Upload failed.'); return j; }); });
  }

  coverFile.addEventListener('change', function () {
    showError('');
    upload(coverFile.files[0]).then(function (j) {
      coverInput.value = j.id;
      coverPrev.innerHTML = '';
      var img = document.createElement('img');
      img.src = j.thumb || j.url; img.alt = '';
      coverPrev.appendChild(img);
      coverPrev.hidden = false; coverRemove.hidden = false;
      markDirty();
    }, function (err) { showError(err.message); });
    coverFile.value = '';
  });
  coverRemove.addEventListener('click', function () {
    coverInput.value = '0'; coverPrev.innerHTML = ''; coverPrev.hidden = true; coverRemove.hidden = true; markDirty();
  });
  imageFile.addEventListener('change', function () {
    showError('');
    var f = imageFile.files[0];
    upload(f).then(function (j) {
      var alt = window.prompt('Describe the image for people who cannot see it (alt text)', '') || '';
      restoreRange();
      document.execCommand('insertHTML', false, '<img src="' + esc(j.url).replace(/"/g, '&quot;') + '" alt="' + esc(alt).replace(/"/g, '&quot;') + '" />');
      markDirty();
    }, function (err) { showError(err.message); });
    imageFile.value = '';
  });

  // ---- saving ------------------------------------------------------------
  function payload(action) {
    var dateEl = field('date');
    var date = dateEl && dateEl.value && status !== 'publish' ? dateEl.value : '';
    if (action === 'publish' && date) action = 'schedule';
    return {
      id: postId, action: action,
      title: title.value, content: body.innerHTML, excerpt: field('excerpt').value,
      category: field('category').value, tags: field('tags').value, series: field('series').value,
      cover: coverInput.value, date: date
    };
  }

  function save(action, silent) {
    if (saving) { queued = [action, silent]; return Promise.resolve(null); }
    saving = true; showError('');
    if (!silent) savedEl.textContent = 'Saving…';
    return fetch(cfg.saveUrl, {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify(payload(action))
    }).then(function (r) {
      return r.json().then(function (j) { if (!r.ok) throw new Error(j.message || 'Saving failed.'); return j; });
    }).then(function (j) {
      saving = false;
      postId = j.id; form.setAttribute('data-id', j.id);
      try { if (window.history && history.replaceState) history.replaceState(null, '', j.edit_url); } catch (e) { /* the address bar is cosmetic */ }
      setStatus(j.status, j.status_label);
      dirty = false;
      savedEl.textContent = (silent ? 'Autosaved at ' : 'Saved at ') + j.saved_at;
      if (j.redirect && action !== 'save') { leaving = true; window.location.href = j.redirect; }
      var q = queued; queued = null;
      if (q && dirty) save(q[0], q[1]);
      return j;
    }, function (err) {
      saving = false; queued = null;
      savedEl.textContent = dirty ? 'Unsaved changes' : savedEl.textContent;
      showError(err.message);
      return null;
    });
  }

  form.addEventListener('click', function (e) {
    var btn = e.target.closest('button[data-action]');
    if (!btn) return;
    var action = btn.getAttribute('data-action');
    if (action === 'preview') {
      var w = window.open('', '_blank');
      save('save', false).then(function (j) { if (j && w) w.location.href = j.preview; else if (w) w.close(); });
      return;
    }
    if (action === 'publish' && !canPublish) return;
    save(action, false);
  });
  form.addEventListener('submit', function (e) { e.preventDefault(); });

  // autosave every 30 s: only while there are changes, and not for live (published) articles,
  // where saving changes what readers see; those use "Update article"
  setInterval(function () {
    if (dirty && !saving && status !== 'publish' && (title.value.trim() || body.textContent.trim())) save('save', true);
  }, parseInt(cfg.autosaveMs, 10) || 30000);

  window.addEventListener('beforeunload', function (e) {
    if (dirty && !leaving) { e.preventDefault(); e.returnValue = ''; return ''; }
  });
})();

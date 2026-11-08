/* Prospergenics Platform – member profiles: inline editing, follow, endorse, photo crop, featured.
 * Vanilla JS, no framework (card #6354). Talks to prospergenics/v1/profile/… with the wp_rest nonce.
 * After a save, the changed sections are re-rendered by the server: the page is fetched again and
 * the [data-pgp-region] elements are swapped in, so the markup has one source (the PHP template).
 */
(function () {
  'use strict';
  var cfg = window.pgProfiles || {};
  var root = document.querySelector('[data-pgp-root]');

  /* ───────── REST ───────── */
  function api(method, path, body) {
    var opts = { method: method, credentials: 'same-origin', headers: { 'X-WP-Nonce': cfg.nonce || '' } };
    if (body instanceof FormData) {
      opts.body = body;
    } else if (body !== undefined) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(body);
    }
    return fetch(cfg.rest + path, opts).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (json) {
        if (!res.ok) {
          var err = new Error((json && json.message) || 'Something went wrong. Please try again.');
          err.fields = json && json.data && json.data.fields;
          throw err;
        }
        return json;
      });
    }, function () {
      throw new Error('Could not reach the server. Check your connection and try again.');
    });
  }

  function say(el, text) {
    if (!el) return;
    el.textContent = '';
    window.clearTimeout(el._pgpTimer);
    window.setTimeout(function () { el.textContent = text; }, 30);
    // the toast clears itself; the copy-link status stays
    if (el.hasAttribute('data-pgp-status')) el._pgpTimer = window.setTimeout(function () { el.textContent = ''; }, 5000);
  }
  var heroStatus = function () { return document.querySelector('[data-pgp-status]'); };

  /* ───────── dirty tracking + unsaved-changes warning ───────── */
  var dirty = new Set();
  document.addEventListener('input', function (e) {
    var form = e.target.closest && e.target.closest('form[data-pgp-form], form[data-pgp-featured-add]');
    if (form && !e.target.hasAttribute('data-pgp-autosave')) dirty.add(form);
    if (e.target.hasAttribute && e.target.hasAttribute('data-pgp-autosave')) dirty.add(e.target);
  });
  window.addEventListener('beforeunload', function (e) {
    dirty.forEach(function (el) { if (!document.contains(el)) dirty.delete(el); });
    if (dirty.size) { e.preventDefault(); e.returnValue = ''; }
  });

  /* ───────── re-render regions from the server ───────── */
  function refresh(names, focusRegion) {
    var url = new URL(window.location.href);
    if (root && root.classList.contains('is-editing')) url.searchParams.set('edit', '1');
    url.searchParams.delete('verified'); url.searchParams.delete('welcome');
    return fetch(url.toString(), { credentials: 'same-origin', headers: { 'X-Requested-With': 'fetch' } })
      .then(function (r) { return r.text(); })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        names.forEach(function (name) {
          var fresh = doc.querySelector('[data-pgp-region="' + name + '"]');
          var old = document.querySelector('[data-pgp-region="' + name + '"]');
          if (fresh && old) {
            old.replaceWith(document.importNode(fresh, true));
          } else if (fresh && !old) {
            // a section that was not shown before (e.g. first entry): reload to place it correctly
            window.location.reload();
          } else if (!fresh && old) {
            old.remove();
          }
        });
        if (focusRegion) {
          var h = document.querySelector('[data-pgp-region="' + focusRegion + '"] h2');
          if (h) { h.setAttribute('tabindex', '-1'); h.focus(); }
        }
      });
  }
  function regionsOf(el) {
    var r = (el.getAttribute('data-refresh') || '').split(/\s+/).filter(Boolean);
    return r.length ? r : ['hero', 'sidebar'];
  }

  /* ───────── edit mode toggle ───────── */
  function setEditing(on) {
    if (!root) return;
    root.classList.toggle('is-editing', on);
    var btn = document.querySelector('[data-pgp-edit-toggle]');
    if (btn) {
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
      var l = btn.querySelector('[data-pgp-label]');
      if (l) l.textContent = on ? 'Done editing' : 'Edit profile';
    }
    var url = new URL(window.location.href);
    if (on) url.searchParams.set('edit', '1'); else url.searchParams.delete('edit');
    history.replaceState(null, '', url.toString());
  }

  /* ───────── forms ───────── */
  function formData(form) {
    var out = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.type === 'file' || el.type === 'submit' || el.type === 'button') return;
      if (el.type === 'checkbox') out[el.name] = el.checked;
      else if (el.type === 'radio') { if (el.checked) out[el.name] = el.value; }
      else out[el.name] = el.value;
    });
    return out;
  }
  function showErrors(form, err) {
    var box = form.querySelector('.pgp-form-error');
    form.querySelectorAll('[aria-invalid]').forEach(function (el) { el.removeAttribute('aria-invalid'); });
    if (err && err.fields) {
      Object.keys(err.fields).forEach(function (k) {
        var el = form.querySelector('[name="' + k + '"]');
        if (el) el.setAttribute('aria-invalid', 'true');
      });
    }
    if (box) { box.hidden = !err; box.textContent = err ? err.message : ''; }
    if (err) {
      var first = form.querySelector('[aria-invalid="true"]');
      if (first) first.focus();
    }
  }
  function resetForm(form) {
    form.reset();
    showErrors(form, null);
    dirty.delete(form);
    syncCurrent(form);
  }
  function closeInline(form) {
    if (form.hasAttribute('data-pgp-keep-open')) return;
    form.hidden = true;
    var opener = document.querySelector('[data-pgp-open="' + form.id + '"]');
    var view = opener && opener.getAttribute('data-pgp-hide') && document.getElementById(opener.getAttribute('data-pgp-hide'));
    if (view) view.classList.remove('is-hidden');
    if (opener) { opener.setAttribute('aria-expanded', 'false'); opener.hidden = false; opener.focus(); }
  }
  // "I currently work here" disables the end month
  function syncCurrent(form) {
    var cur = form.querySelector('[data-pgp-current]');
    var end = form.querySelector('[data-pgp-end]');
    if (cur && end) { end.disabled = cur.checked; if (cur.checked) end.value = ''; }
  }
  document.addEventListener('change', function (e) {
    if (e.target.matches && e.target.matches('[data-pgp-current]')) syncCurrent(e.target.form);
  });
  document.querySelectorAll('form[data-pgp-form]').forEach(syncCurrent);

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.matches('form[data-pgp-featured-add]')) { e.preventDefault(); addFeatured(form); return; }
    if (!form.matches('form[data-pgp-form]')) return;
    e.preventDefault();
    var submit = form.querySelector('[type=submit]');
    if (submit) submit.disabled = true;
    api(form.getAttribute('data-method') || 'POST', form.getAttribute('data-endpoint'), formData(form))
      .then(function () {
        dirty.delete(form);
        form.querySelectorAll('[data-pgp-autosave]').forEach(function (el) { el.defaultValue = el.value; dirty.delete(el); });
        if (form.hasAttribute('data-pgp-keep-open') && form.getAttribute('data-method') === 'POST') form.reset();
        var regions = regionsOf(form);
        say(heroStatus(), 'Saved.');
        return refresh(regions, form.hasAttribute('data-pgp-keep-open') ? null : regions[0]).then(function () {
          if (form.hasAttribute('data-pgp-keep-open')) {
            var again = document.querySelector('form[data-endpoint="' + form.getAttribute('data-endpoint') + '"] input:not([type=hidden])');
            if (again && form.getAttribute('data-endpoint') === 'skills') again.focus();
          }
        });
      })
      .catch(function (err) { showErrors(form, err); })
      .then(function () { if (submit && document.contains(submit)) submit.disabled = false; });
  });

  /* autosave single-line fields on blur (card #6354) */
  document.addEventListener('focusout', function (e) {
    var el = e.target;
    if (!el.hasAttribute || !el.hasAttribute('data-pgp-autosave') || el.value === el.defaultValue) return;
    var form = el.form;
    var body = {}; body[el.name] = el.value;
    var status = heroStatus();
    api('PUT', 'me', body).then(function () {
      el.defaultValue = el.value;
      el.removeAttribute('aria-invalid');
      dirty.delete(el);
      showErrors(form, null);
      say(status, 'Saved.');
      refresh(['hero', 'sidebar']);
    }).catch(function (err) { showErrors(form, err); });
  });

  /* ───────── clicks ───────── */
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('button, a') : null;
    if (!t) return;

    if (t.hasAttribute('data-pgp-edit-toggle')) {
      if (root.classList.contains('is-editing') && dirty.size && !window.confirm('You have unsaved changes. Stop editing anyway?')) return;
      setEditing(!root.classList.contains('is-editing'));
      return;
    }
    if (t.hasAttribute('data-pgp-goto')) {
      e.preventDefault();
      setEditing(true);
      var target = document.querySelector(t.getAttribute('data-pgp-goto'));
      if (target) {
        target.scrollIntoView({ block: 'start' });
        var f = target.querySelector('input:not([type=hidden]), button, [tabindex]');
        if (f) f.focus({ preventScroll: true });
      }
      return;
    }
    if (t.hasAttribute('data-pgp-open')) {
      var form = document.getElementById(t.getAttribute('data-pgp-open'));
      if (!form) return;
      form.hidden = false;
      t.setAttribute('aria-expanded', 'true');
      var hide = t.getAttribute('data-pgp-hide');
      if (hide) document.getElementById(hide).classList.add('is-hidden'); else t.hidden = true;
      syncCurrent(form);
      var first = form.querySelector('input:not([type=hidden]), select, textarea');
      if (first) first.focus();
      return;
    }
    if (t.hasAttribute('data-pgp-cancel')) {
      var cf = t.closest('form');
      if (cf.hasAttribute('data-pgp-featured-add')) { cf.reset(); dirty.delete(cf); cf.hidden = true; var o = document.querySelector('[data-pgp-open="' + cf.id + '"]'); if (o) { o.hidden = false; o.setAttribute('aria-expanded', 'false'); o.focus(); } return; }
      resetForm(cf);
      closeInline(cf);
      return;
    }
    if (t.hasAttribute('data-pgp-delete')) {
      if (t.getAttribute('data-confirm') && !window.confirm(t.getAttribute('data-confirm'))) return;
      t.disabled = true;
      api('DELETE', t.getAttribute('data-pgp-delete')).then(function () {
        say(heroStatus(), 'Deleted.');
        var regions = regionsOf(t);
        return refresh(regions, regions[0]);
      }).catch(function (err) { t.disabled = false; window.alert(err.message); });
      return;
    }
    if (t.hasAttribute('data-pgp-follow')) {
      var on = t.getAttribute('aria-pressed') !== 'true';
      t.disabled = true;
      api(on ? 'POST' : 'DELETE', 'users/' + t.getAttribute('data-pgp-follow') + '/follow').then(function (r) {
        t.setAttribute('aria-pressed', r.following ? 'true' : 'false');
        t.querySelector('[data-pgp-label]').textContent = r.following ? 'Following' : 'Follow';
        var c = document.querySelector('[data-pgp-followers]');
        if (c) c.textContent = r.followers;
        say(heroStatus(), r.following ? 'You now follow this member.' : 'You no longer follow this member.');
      }).catch(function (err) { say(heroStatus(), err.message); })
        .then(function () { t.disabled = false; });
      return;
    }
    if (t.hasAttribute('data-pgp-endorse')) {
      var id = t.getAttribute('data-pgp-endorse');
      var endorse = t.getAttribute('aria-pressed') !== 'true';
      t.disabled = true;
      api(endorse ? 'POST' : 'DELETE', 'skills/' + id + '/endorse').then(function (r) {
        t.setAttribute('aria-pressed', r.endorsed_by_me ? 'true' : 'false');
        t.querySelector('[data-pgp-label]').textContent = r.endorsed_by_me ? 'Endorsed' : 'Endorse';
        document.querySelectorAll('[data-pgp-endorse-count="' + id + '"]').forEach(function (c) {
          c.textContent = r.endorsements + (r.endorsements === 1 ? ' endorsement' : ' endorsements');
        });
        say(heroStatus(), r.endorsed_by_me ? 'Endorsement added.' : 'Endorsement withdrawn.');
      }).catch(function (err) { say(heroStatus(), err.message); })
        .then(function () { t.disabled = false; });
      return;
    }
    if (t.hasAttribute('data-pgp-pin')) {
      var pin = t.getAttribute('aria-pressed') !== 'true';
      api('PUT', 'skills/' + t.getAttribute('data-pgp-pin'), { pinned: pin })
        .then(function () { say(heroStatus(), pin ? 'Skill pinned.' : 'Skill unpinned.'); return refresh(['skills'], 'skills'); })
        .catch(function (err) { say(heroStatus(), err.message); });
      return;
    }
    if (t.hasAttribute('data-pgp-copy')) {
      var url = t.getAttribute('data-pgp-copy');
      var status = document.querySelector('[data-pgp-copy-status]');
      var done = function () { say(status, 'Link copied.'); };
      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url).then(done, fallbackCopy);
      } else { fallbackCopy(); }
      function fallbackCopy() {
        var input = document.getElementById('pgp-url');
        input.select();
        try { document.execCommand('copy'); done(); } catch (x) { say(status, 'Select the link and copy it.'); }
      }
      return;
    }
    if (t.hasAttribute('data-pgp-notes-read')) {
      api('POST', 'notifications/read').then(function () { refresh(['notes']); });
      return;
    }
    if (t.hasAttribute('data-pgp-feat-move') || t.hasAttribute('data-pgp-feat-remove')) {
      var li = t.closest('[data-pgp-item]');
      var list = li.parentNode;
      if (t.hasAttribute('data-pgp-feat-remove')) {
        if (!window.confirm('Remove this featured item?')) return;
        li.remove();
      } else if (t.getAttribute('data-pgp-feat-move') === '-1' && li.previousElementSibling) {
        list.insertBefore(li, li.previousElementSibling);
      } else if (t.getAttribute('data-pgp-feat-move') === '1' && li.nextElementSibling) {
        list.insertBefore(li.nextElementSibling, li);
      }
      saveFeatured(list);
    }
  });

  /* ───────── featured (card #6352) ───────── */
  function featuredItems(list) {
    if (!list) return [];
    return Array.prototype.map.call(list.querySelectorAll('[data-pgp-item]'), function (li) {
      return JSON.parse(li.getAttribute('data-pgp-item'));
    });
  }
  function saveFeatured(list, extra) {
    var items = featuredItems(list);
    if (extra) items.push(extra);
    return api('PUT', 'featured', { items: items }).then(function () {
      say(heroStatus(), 'Featured section saved.');
      return refresh(['featured', 'sidebar'], 'featured');
    }).catch(function (err) { say(heroStatus(), err.message); throw err; });
  }
  function addFeatured(form) {
    var type = form.querySelector('[data-pgp-feat-type]').value;
    var url = form.querySelector('[data-pgp-feat-url]').value.trim();
    var title = form.querySelector('[name="title"]').value.trim();
    var file = form.querySelector('[data-pgp-feat-file]');
    var list = document.querySelector('[data-pgp-featured-list]');
    var submit = form.querySelector('[type=submit]');
    submit.disabled = true;
    var step;
    if (type === 'media') {
      if (!file.files.length) { showErrors(form, new Error('Choose an image to upload.')); submit.disabled = false; return; }
      if (!title) { showErrors(form, Object.assign(new Error('Enter a title for the image.'), { fields: { title: 1 } })); submit.disabled = false; return; }
      var fd = new FormData(); fd.append('media', file.files[0]);
      step = api('POST', 'featured/media', fd).then(function (r) {
        return { type: 'media', url: r.url, title: title, image: r.image, attachment_id: r.attachment_id };
      });
    } else {
      if (!/^https?:\/\//i.test(url)) { showErrors(form, Object.assign(new Error('Enter a full link starting with https://'), { fields: { url: 1 } })); submit.disabled = false; return; }
      step = Promise.resolve({ type: type, url: url, title: title, image: '' });
    }
    step.then(function (item) { dirty.delete(form); return saveFeatured(list, item); })
      .catch(function (err) { showErrors(form, err); })
      .then(function () { if (document.contains(submit)) submit.disabled = false; });
  }
  // show the URL or the file field depending on the type
  function syncFeatType(form) {
    if (!form) return;
    var media = form.querySelector('[data-pgp-feat-type]').value === 'media';
    form.querySelector('[data-pgp-feat-url]').closest('.pgp-field').hidden = media;
    form.querySelector('[data-pgp-feat-file]').closest('.pgp-field').hidden = !media;
  }
  document.addEventListener('change', function (e) {
    if (e.target.matches && e.target.matches('[data-pgp-feat-type]')) syncFeatType(e.target.form);
  });
  syncFeatType(document.querySelector('form[data-pgp-featured-add]'));

  // drag to reorder (mouse/touch); the Move up/down buttons do the same from the keyboard
  var dragged = null;
  document.addEventListener('dragstart', function (e) {
    var li = e.target.closest && e.target.closest('[data-pgp-item][draggable="true"]');
    if (!li || !root.classList.contains('is-editing')) { if (li) e.preventDefault(); return; }
    dragged = li; li.classList.add('is-dragging');
    e.dataTransfer.effectAllowed = 'move';
    try { e.dataTransfer.setData('text/plain', ''); } catch (x) { /* old browsers */ }
  });
  document.addEventListener('dragover', function (e) {
    var li = dragged && e.target.closest && e.target.closest('[data-pgp-item]');
    if (!li || li === dragged || li.parentNode !== dragged.parentNode) return;
    e.preventDefault();
    document.querySelectorAll('.pgp-feat.is-drop').forEach(function (x) { x.classList.remove('is-drop'); });
    li.classList.add('is-drop');
  });
  document.addEventListener('drop', function (e) {
    var li = dragged && e.target.closest && e.target.closest('[data-pgp-item]');
    if (!li || li === dragged) return;
    e.preventDefault();
    var list = li.parentNode;
    var items = Array.prototype.slice.call(list.children);
    if (items.indexOf(dragged) < items.indexOf(li)) list.insertBefore(dragged, li.nextSibling); else list.insertBefore(dragged, li);
    saveFeatured(list);
  });
  document.addEventListener('dragend', function () {
    if (dragged) dragged.classList.remove('is-dragging');
    document.querySelectorAll('.pgp-feat.is-drop').forEach(function (x) { x.classList.remove('is-drop'); });
    dragged = null;
  });

  /* ───────── photo: square crop in the browser (card #6344) ───────── */
  var crop = { img: null, zoom: 1, x: 0, y: 0, input: null, name: 'photo.jpg' };
  var dialog = document.getElementById('pgp-crop');
  var canvas = dialog && dialog.querySelector('[data-pgp-crop-canvas]');
  var zoomEl = dialog && dialog.querySelector('[data-pgp-crop-zoom]');
  var cropErr = dialog && dialog.querySelector('[data-pgp-crop-error]');

  function baseScale() { return canvas.width / Math.min(crop.img.naturalWidth, crop.img.naturalHeight); }
  function clamp() {
    var s = baseScale() * crop.zoom;
    var w = crop.img.naturalWidth * s, h = crop.img.naturalHeight * s;
    crop.x = Math.min(0, Math.max(canvas.width - w, crop.x));
    crop.y = Math.min(0, Math.max(canvas.height - h, crop.y));
  }
  function draw() {
    if (!crop.img) return;
    clamp();
    var ctx = canvas.getContext('2d');
    var s = baseScale() * crop.zoom;
    ctx.fillStyle = '#111'; ctx.fillRect(0, 0, canvas.width, canvas.height);
    ctx.drawImage(crop.img, crop.x, crop.y, crop.img.naturalWidth * s, crop.img.naturalHeight * s);
    // round guide: the photo shows as a circle on the profile
    ctx.save();
    ctx.fillStyle = 'rgba(0,0,0,.45)';
    ctx.beginPath(); ctx.rect(0, 0, canvas.width, canvas.height);
    ctx.arc(canvas.width / 2, canvas.height / 2, canvas.width / 2 - 2, 0, Math.PI * 2, true);
    ctx.fill('evenodd');
    ctx.restore();
  }
  function zoomTo(z) {
    var old = baseScale() * crop.zoom;
    crop.zoom = Math.max(1, Math.min(4, z));
    var s = baseScale() * crop.zoom, c = canvas.width / 2;
    crop.x = c - (c - crop.x) * s / old;
    crop.y = c - (c - crop.y) * s / old;
    draw();
  }
  function openCrop(input) {
    var file = input.files && input.files[0];
    if (!file) return;
    if (!/^image\/(jpeg|png|webp|gif)$/.test(file.type)) { window.alert('Choose a JPEG, PNG, WebP or GIF image.'); input.value = ''; return; }
    if (file.size > 15 * 1024 * 1024) { window.alert('This image is too large.'); input.value = ''; return; }
    var img = new Image();
    img.onload = function () {
      if (img.naturalWidth < 200 || img.naturalHeight < 200) {
        window.alert('The photo must be at least 200 × 200 pixels.');
        input.value = ''; return;
      }
      crop.img = img; crop.input = input; crop.zoom = 1; crop.name = (file.name || 'photo').replace(/\.[^.]+$/, '') + '.jpg';
      var s = baseScale();
      crop.x = (canvas.width - img.naturalWidth * s) / 2;
      crop.y = (canvas.height - img.naturalHeight * s) / 2;
      zoomEl.value = '1';
      cropErr.hidden = true;
      draw();
      if (dialog.showModal) dialog.showModal(); else dialog.setAttribute('open', '');
      canvas.focus();
    };
    img.onerror = function () { window.alert('This image could not be read.'); input.value = ''; };
    img.src = URL.createObjectURL(file);
  }
  function closeCrop(keepFile) {
    if (dialog.close) dialog.close(); else dialog.removeAttribute('open');
    if (!keepFile && crop.input) crop.input.value = '';
    if (crop.input) {
      var label = document.querySelector('label[for="' + crop.input.id + '"]');
      (label && label.offsetParent ? label : crop.input).focus();
    }
  }
  function cropToBlob() {
    var s = baseScale() * crop.zoom;
    var side = canvas.width / s;               // source pixels in the square
    var sx = -crop.x / s, sy = -crop.y / s;
    if (side < 200) return Promise.reject(new Error('Zoomed in too far: the crop must be at least 200 × 200 pixels of the original. Zoom out a little.'));
    var outSide = Math.min(800, Math.round(side));
    var out = document.createElement('canvas');
    out.width = out.height = outSide;
    out.getContext('2d').drawImage(crop.img, sx, sy, side, side, 0, 0, outSide, outSide);
    return new Promise(function (resolve, reject) {
      out.toBlob(function (b) { b ? resolve(b) : reject(new Error('Could not crop this image.')); }, 'image/jpeg', 0.9);
    });
  }
  if (dialog) {
    var drag = null;
    canvas.addEventListener('pointerdown', function (e) { drag = { x: e.clientX, y: e.clientY }; canvas.setPointerCapture(e.pointerId); });
    canvas.addEventListener('pointermove', function (e) {
      if (!drag) return;
      var k = canvas.width / canvas.getBoundingClientRect().width;
      crop.x += (e.clientX - drag.x) * k; crop.y += (e.clientY - drag.y) * k;
      drag = { x: e.clientX, y: e.clientY }; draw();
    });
    canvas.addEventListener('pointerup', function () { drag = null; });
    canvas.addEventListener('keydown', function (e) {
      var step = e.shiftKey ? 40 : 10;
      var moves = { ArrowLeft: [step, 0], ArrowRight: [-step, 0], ArrowUp: [0, step], ArrowDown: [0, -step] };
      if (moves[e.key]) { e.preventDefault(); crop.x += moves[e.key][0]; crop.y += moves[e.key][1]; draw(); }
      if (e.key === '+' || e.key === '=') { zoomTo(crop.zoom + 0.1); zoomEl.value = crop.zoom; }
      if (e.key === '-') { zoomTo(crop.zoom - 0.1); zoomEl.value = crop.zoom; }
    });
    canvas.addEventListener('wheel', function (e) { e.preventDefault(); zoomTo(crop.zoom - e.deltaY * 0.002); zoomEl.value = crop.zoom; }, { passive: false });
    zoomEl.addEventListener('input', function () { zoomTo(parseFloat(zoomEl.value)); });
    dialog.addEventListener('cancel', function () { closeCrop(false); });
    dialog.querySelector('[data-pgp-crop-cancel]').addEventListener('click', function () { closeCrop(false); });
    dialog.querySelector('[data-pgp-crop-ok]').addEventListener('click', function () {
      var okBtn = this;
      cropToBlob().then(function (blob) {
        var input = crop.input;
        if (input.hasAttribute('data-pgp-upload')) {
          okBtn.disabled = true;
          var fd = new FormData(); fd.append('photo', blob, crop.name);
          return api('POST', 'photo', fd).then(function () {
            input.value = '';
            closeCrop(true);
            say(heroStatus(), 'Photo saved.');
            return refresh(['hero', 'sidebar']);
          }).then(function () { var b = document.querySelector('label[for="pgp-photo-input"]'); if (b) b.focus(); },
            function (err) { cropErr.hidden = false; cropErr.textContent = err.message; })
            .then(function () { okBtn.disabled = false; });
        }
        // wizard: put the cropped file into the form's file input; the form submits it
        var dt = new DataTransfer();
        dt.items.add(new File([blob], crop.name, { type: 'image/jpeg' }));
        input.files = dt.files;
        var prev = document.querySelector('[data-pgp-photo-preview]');
        if (prev) { prev.src = URL.createObjectURL(blob); prev.hidden = false; prev.alt = 'Preview of your new photo'; }
        closeCrop(true);
      }).catch(function (err) { cropErr.hidden = false; cropErr.textContent = err.message; });
    });
    document.addEventListener('change', function (e) {
      if (e.target.matches && e.target.matches('[data-pgp-photo-input]')) openCrop(e.target);
    });
  }
})();

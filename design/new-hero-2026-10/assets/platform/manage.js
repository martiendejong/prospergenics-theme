/* Prospergenics staff area, /manage/members/ (card #6457, inc/manage/views/members.php). Loaded in
   the <head> so the page is drawn compact from the start. Without JavaScript every member's actions
   stay visible and the search form works with its button; with it:
   - each member's actions fold into a "Manage" disclosure button (aria-expanded; Esc closes it and
     puts the focus back on the button; a click elsewhere closes it; one open at a time);
   - the search is submitted after typing stops, and the search box keeps the focus after the reload;
   - the form leaves default values out of the URL, so links stay short. */
(function () {
  'use strict';
  var root = document.documentElement;
  root.className += (root.className ? ' ' : '') + 'pgm-js';

  function ready(fn) {
    if (document.readyState !== 'loading') { fn(); } else { document.addEventListener('DOMContentLoaded', fn); }
  }

  ready(function () {
    var open = null; // the button of the open panel

    // the table header sticks under the site header (sticky too): measure it, the CSS adds the admin bar
    var header = document.querySelector('.site-header');
    if (header && /sticky|fixed/.test(getComputedStyle(header).position)) {
      root.style.setProperty('--pgm-sticky-top', Math.round(header.getBoundingClientRect().height) + 'px');
    }

    function panelOf(btn) { return document.getElementById(btn.getAttribute('aria-controls')); }

    function close(focus) {
      if (!open) { return; }
      var btn = open;
      open = null;
      btn.setAttribute('aria-expanded', 'false');
      var p = panelOf(btn);
      if (p) { p.classList.remove('is-open'); }
      btn.closest('tr') && btn.closest('tr').classList.remove('pgm-row-open');
      if (focus) { btn.focus(); }
    }

    function show(btn) {
      close(false);
      var p = panelOf(btn);
      if (!p) { return; }
      open = btn;
      btn.setAttribute('aria-expanded', 'true');
      p.classList.add('is-open');
      btn.closest('tr') && btn.closest('tr').classList.add('pgm-row-open');
    }

    Array.prototype.forEach.call(document.querySelectorAll('.pgm-manage-btn'), function (btn) {
      btn.addEventListener('click', function () {
        if (open === btn) { close(false); } else { show(btn); }
      });
    });

    document.addEventListener('keydown', function (e) {
      if (open && (e.key === 'Escape' || e.key === 'Esc')) {
        e.preventDefault();
        close(true);
      }
    });

    document.addEventListener('click', function (e) {
      if (!open) { return; }
      var p = panelOf(open);
      if (open.contains(e.target) || (p && p.contains(e.target))) { return; }
      close(false);
    });

    // the search form: submit after typing stops; leave defaults out of the URL
    var q = document.getElementById('pgm-s');
    var form = q && q.form;
    if (!form) { return; }
    form.addEventListener('submit', function () {
      Array.prototype.forEach.call(form.querySelectorAll('[data-default]'), function (el) {
        if (el.value.trim() === el.getAttribute('data-default')) { el.disabled = true; }
      });
      // re-enable on back/forward cache restores
      setTimeout(function () {
        Array.prototype.forEach.call(form.querySelectorAll('[data-default]'), function (el) { el.disabled = false; });
      }, 0);
    });

    var initial = q.value.trim();
    var timer = null;
    q.addEventListener('input', function () {
      clearTimeout(timer);
      timer = setTimeout(function () {
        var v = q.value.trim();
        if (v === initial || v.length === 1) { return; } // one letter finds too much; wait for more
        try { sessionStorage.setItem('pgm-refocus', '1'); } catch (err) { /* private mode: no refocus */ }
        if (form.requestSubmit) { form.requestSubmit(); } else { form.submit(); }
      }, 600);
    });

    var refocus = false;
    try { refocus = sessionStorage.getItem('pgm-refocus') === '1'; sessionStorage.removeItem('pgm-refocus'); } catch (err) { /* ignore */ }
    if (refocus) {
      q.focus();
      var n = q.value.length;
      try { q.setSelectionRange(n, n); } catch (err) { /* type=search may refuse */ }
    }
  });
})();

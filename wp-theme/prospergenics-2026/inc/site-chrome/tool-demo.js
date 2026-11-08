/* "See it work" on the tool pages (/tools/<slug>/, card #7218). Printed by the theme's takeover
 * (inc/site-chrome.php, inc/tool-demos.php) after the site footer, only on a page that shows a demo.
 *   - The real screenshots become a click-through: one frame of their own ratio, a guide cursor that
 *     glides from the previous point to this step's point (a ripple for "click", a blinking caret for
 *     "type", no cursor for "look"), Back / Next and step dots, then "Step n of N" and the caption in a
 *     polite live region, and ←/→ (Home/End) while the frame has focus. Nothing plays by itself; "Play" walks
 *     through the steps every 3.5 s and any other click or key stops it.
 *   - With a screen recording as well, "Step through" and "Watch" become tabs (ARIA tabs pattern).
 *   - prefers-reduced-motion: the cursor jumps instead of gliding, no ripple, no blinking.
 * Markup is built with createElement / textContent only. Without JavaScript the screenshots stand one
 * under another with their captions, and the video below them. */
(function () {
  var section = document.querySelector('[data-pg-demo]');
  if (!section || section.hasAttribute('data-pg-demo-ready')) return;
  section.setAttribute('data-pg-demo-ready', '');

  var motion = window.matchMedia ? window.matchMedia('(prefers-reduced-motion: reduce)') : null;
  function reduced() { return !!(motion && motion.matches); }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text) e.textContent = text; return e; }
  function button(text, cls) { var b = el('button', cls, text); b.type = 'button'; return b; }
  var tool = section.getAttribute('data-tool-name') || 'the tool';
  var GLIDE = 900, EVERY = 3500;

  var stopPlay = function () {};

  // ── the click-through ──────────────────────────────────────────────────────────────────────
  var frame = section.querySelector('.pg-demo-frame');
  var items = frame ? [].slice.call(frame.querySelectorAll('.pg-demo-step')) : [];
  if (items.length) {
    section.classList.add('pg-demo--js');
    var n = items.length, cur = -1, placed = false, timer = null, arriveT = null;

    if (n > 1) {
      frame.setAttribute('tabindex', '0');
      frame.setAttribute('role', 'group');
      frame.setAttribute('aria-label', 'Screenshots of ' + tool + '. Use the left and right arrow keys to step through them.');
    }

    // the guide cursor: an arrow whose tip is the point, with the click ripple and typing caret
    var guide = el('span', 'pg-demo-guide is-hidden');
    guide.setAttribute('aria-hidden', 'true');
    var NS = 'http://www.w3.org/2000/svg';
    var svg = document.createElementNS(NS, 'svg');
    svg.setAttribute('viewBox', '0 0 24 34');
    svg.setAttribute('width', '24');
    svg.setAttribute('height', '34');
    svg.setAttribute('focusable', 'false');
    var path = document.createElementNS(NS, 'path');
    path.setAttribute('d', 'M1.5 1.5 L1.5 27 L8 21 L12.5 31.5 L17 29.5 L12.6 19.4 L21.5 19.4 Z');
    path.setAttribute('fill', '#fff');
    path.setAttribute('stroke', '#0b2016');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('stroke-linejoin', 'round');
    svg.appendChild(path);
    var pointer = el('span', 'pg-demo-pointer');
    pointer.appendChild(svg);
    guide.appendChild(el('span', 'pg-demo-ripple'));
    guide.appendChild(el('span', 'pg-demo-caret'));
    guide.appendChild(pointer);
    frame.appendChild(guide);

    // caption, count and controls under the frame
    var bar = el('div', 'pg-demo-bar');
    var live = el('p', 'pg-demo-caption');
    live.setAttribute('aria-live', 'polite');
    var count = el('span', 'pg-demo-count'), text = el('span', 'pg-demo-text');
    live.appendChild(count);
    live.appendChild(text);
    var controls = el('div', 'pg-demo-controls');
    var back = button('Back', 'pg-demo-btn pg-demo-back');
    var next = button('Next', 'pg-demo-btn pg-demo-btn--primary pg-demo-next');
    var dots = el('div', 'pg-demo-dots');
    dots.setAttribute('role', 'group');
    dots.setAttribute('aria-label', 'Steps');
    var dotEls = items.map(function (li, i) {
      var d = button('', 'pg-demo-dot');
      d.setAttribute('aria-label', 'Step ' + (i + 1));
      d.addEventListener('click', function () { stopPlay(); show(i); });
      dots.appendChild(d);
      return d;
    });
    var play = button('Play', 'pg-demo-btn pg-demo-play');
    if (n > 1) {
      controls.appendChild(back);
      controls.appendChild(dots);
      controls.appendChild(next);
      controls.appendChild(play);
      bar.appendChild(controls); // above the caption, so the buttons stay put when a caption is longer
    }
    bar.appendChild(live);

    function at(li, k) { var v = parseFloat(li.getAttribute(k)); return isNaN(v) ? null : Math.max(0, Math.min(100, v)); }

    function moveGuide(li, glide) {
      var action = li.getAttribute('data-action'), x = at(li, 'data-x'), y = at(li, 'data-y');
      clearTimeout(arriveT);
      guide.classList.remove('is-click', 'is-type');
      if (action === 'look' || x === null || y === null) { guide.classList.add('is-hidden'); return; }
      var jump = !glide || !placed || reduced();
      if (jump) guide.classList.add('no-glide');
      guide.style.left = x + '%';
      guide.style.top = y + '%';
      guide.classList.remove('is-hidden');
      if (jump) { void guide.offsetWidth; guide.classList.remove('no-glide'); }
      placed = true;
      arriveT = setTimeout(function () {
        void guide.offsetWidth; // restart the ripple when the same step is shown again
        if (action === 'click' && !reduced()) guide.classList.add('is-click');
        if (action === 'type') guide.classList.add('is-type');
      }, jump ? 0 : GLIDE);
    }

    function show(i) {
      i = Math.max(0, Math.min(n - 1, i));
      if (i === cur) return;
      var glide = cur >= 0;
      cur = i;
      items.forEach(function (li, k) { li.classList.toggle('is-current', k === i); });
      var cap = items[i].querySelector('.pg-demo-text');
      count.textContent = 'Step ' + (i + 1) + ' of ' + n;
      text.textContent = cap ? cap.textContent : '';
      back.setAttribute('aria-disabled', i === 0 ? 'true' : 'false');
      next.setAttribute('aria-disabled', i === n - 1 ? 'true' : 'false');
      dotEls.forEach(function (d, k) { if (k === i) d.setAttribute('aria-current', 'step'); else d.removeAttribute('aria-current'); });
      moveGuide(items[i], glide);
    }

    stopPlay = function () {
      if (!timer) return;
      clearInterval(timer);
      timer = null;
      play.textContent = 'Play';
    };
    play.addEventListener('click', function () {
      if (timer) { stopPlay(); return; }
      if (cur === n - 1) show(0);
      play.textContent = 'Stop';
      timer = setInterval(function () {
        show(cur + 1);
        if (cur === n - 1) stopPlay();
      }, EVERY);
    });
    back.addEventListener('click', function () { stopPlay(); show(cur - 1); });
    next.addEventListener('click', function () { stopPlay(); show(cur + 1); });
    frame.addEventListener('pointerdown', stopPlay);
    frame.addEventListener('keydown', function (e) {
      var to = e.key === 'ArrowLeft' ? cur - 1 : e.key === 'ArrowRight' ? cur + 1 : e.key === 'Home' ? 0 : e.key === 'End' ? n - 1 : null;
      if (to === null || e.altKey || e.ctrlKey || e.metaKey) return;
      e.preventDefault();
      stopPlay();
      show(to);
    });
    show(0);
    // in the page only now, with the first caption in it, so screen readers announce changes, not the start
    frame.parentNode.insertBefore(bar, frame.nextSibling);
  }

  // ── "Step through" / "Watch" tabs ──────────────────────────────────────────────────────────
  var tablist = section.querySelector('.pg-demo-tabs');
  var tabs = tablist ? [].slice.call(tablist.querySelectorAll('[role="tab"]')) : [];
  var panels = tabs.map(function (t) { return document.getElementById(t.getAttribute('aria-controls')); });
  if (tabs.length > 1 && panels.every(Boolean)) {
    section.classList.add('pg-demo--tabs');
    panels.forEach(function (p, i) { p.setAttribute('role', 'tabpanel'); p.setAttribute('aria-labelledby', tabs[i].id); });
    var select = function (i, focus) {
      stopPlay();
      tabs.forEach(function (t, k) {
        t.setAttribute('aria-selected', k === i ? 'true' : 'false');
        t.tabIndex = k === i ? 0 : -1;
        panels[k].hidden = k !== i;
        if (k !== i) { var v = panels[k].querySelector('video'); if (v && !v.paused) v.pause(); }
      });
      if (focus) tabs[i].focus();
    };
    tabs.forEach(function (t, i) {
      t.addEventListener('click', function () { select(i, false); });
      t.addEventListener('keydown', function (e) {
        var k = tabs.length;
        var to = e.key === 'ArrowRight' ? (i + 1) % k : e.key === 'ArrowLeft' ? (i - 1 + k) % k : e.key === 'Home' ? 0 : e.key === 'End' ? k - 1 : null;
        if (to === null) return;
        e.preventDefault();
        select(to, true);
      });
    });
    tablist.hidden = false;
    select(0, false);
  }
})();

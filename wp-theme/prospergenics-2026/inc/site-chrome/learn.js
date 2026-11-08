/* Course lesson pages of the jengo-listings plugin (/courses/<slug>/learn/), card #7144.
 * Printed by the theme's takeover (inc/site-chrome.php) after the plugin's own script, which has
 * already given every <h2> an id and filled the table of contents. This script:
 *   - adds "Your coding agent" / "Your computer" pickers that show only the matching install steps
 *     (.tool-section[data-tool], .os-section[data-os]); the operating system is detected, both
 *     choices are remembered;
 *   - splits the lesson into its sessions (the plugin separates them with <hr>) and shows one at a
 *     time, with Previous / Next and "Session 3 of 9"; each session has its own link (#session-3),
 *     back and forward work, and the last session opened is remembered;
 *   - rebuilds the table of contents as the list of sessions, the current one with its headings.
 * Without JavaScript, or on a page of another shape, the plugin's full page stays as it is. */
(function () {
  var body = document.getElementById('learnBody');
  var toc = document.getElementById('tocLinks');
  if (!body || !toc || body.hasAttribute('data-pg-sessions')) return;
  body.setAttribute('data-pg-sessions', '');

  var NAMES = { claude: 'Claude Code', codex: 'Codex', opencode: 'OpenCode', cursor: 'Cursor', windsurf: 'Windsurf',
                windows: 'Windows', mac: 'macOS', linux: 'Linux' };
  function name(key) { return NAMES[key] || key.charAt(0).toUpperCase() + key.slice(1); }
  function load(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
  function save(k, v) { try { window.localStorage.setItem(k, v); } catch (e) { /* private mode */ } }
  function words(s) { return (s || '').split(/\s+/).filter(Boolean); }
  function el(tag, cls, text) { var e = document.createElement(tag); if (cls) e.className = cls; if (text) e.textContent = text; return e; }
  function isContent(n) { return n.nodeType === 1 || (n.nodeType === 3 && n.textContent.trim() !== ''); }
  function shown(node) { for (var n = node; n && n !== body; n = n.parentElement) { if (n.hidden) return false; } return true; }

  var course = location.pathname.replace(/\/+$/, '');
  var toolEls = [].slice.call(body.querySelectorAll('.tool-section[data-tool]'));
  var osEls = [].slice.call(body.querySelectorAll('.os-section[data-os]'));
  var tools = [], oses = [];
  toolEls.forEach(function (s) { words(s.getAttribute('data-tool')).forEach(function (t) { if (tools.indexOf(t) < 0) tools.push(t); }); });
  osEls.forEach(function (s) { words(s.getAttribute('data-os')).forEach(function (o) { if (oses.indexOf(o) < 0) oses.push(o); }); });

  var ua = navigator.userAgent || '';
  var guessOs = /Windows/i.test(ua) ? 'windows' : /Mac|iPhone|iPad/i.test(ua) ? 'mac' : /Linux|Android|CrOS/i.test(ua) ? 'linux' : '';
  var tool = load('pg-learn-tool'); if (tools.indexOf(tool) < 0) tool = tools[0] || '';
  var os = load('pg-learn-os'); if (oses.indexOf(os) < 0) os = oses.indexOf(guessOs) >= 0 ? guessOs : (oses[0] || '');

  function applyChoice() {
    toolEls.forEach(function (s) { s.hidden = words(s.getAttribute('data-tool')).indexOf(tool) < 0; });
    osEls.forEach(function (s) { s.hidden = words(s.getAttribute('data-os')).indexOf(os) < 0; });
  }

  // ── the picker ──
  var pick = null;
  function select(label, options, value, onChange) {
    var l = el('label', '', label), s = document.createElement('select');
    options.forEach(function (o) { var opt = el('option', '', name(o)); opt.value = o; opt.selected = o === value; s.appendChild(opt); });
    s.addEventListener('change', function () { onChange(s.value); });
    l.appendChild(s);
    return l;
  }
  if (tools.length > 1 || oses.length > 1) {
    pick = el('div', 'pg-learn-pick');
    pick.setAttribute('role', 'group');
    pick.setAttribute('aria-label', 'Show the steps for');
    pick.appendChild(el('p', '', 'The steps differ per coding agent and computer. Pick yours and only those steps are shown.'));
    if (tools.length > 1) pick.appendChild(select('Your coding agent', tools, tool, function (v) { tool = v; save('pg-learn-tool', v); applyChoice(); refresh(); }));
    if (oses.length > 1) pick.appendChild(select('Your computer', oses, os, function (v) { os = v; save('pg-learn-os', v); applyChoice(); refresh(); }));
  }
  applyChoice();

  // ── the lessons' own playground code, stranded as text (card #7146) ──
  // Every lesson of the HTML, CSS, JavaScript and Python courses ends with the <style> and <script> of
  // the [5818] playground; the tags are stripped before the plugin prints the lesson, so the code shows
  // as text, with a few elements made from the HTML inside its strings. One run per lesson, directly in
  // the lesson: from the text that opens with its header comment up to and including the text that
  // ends with its last line. A run without that end is left alone (it is not the shape we know).
  [].slice.call(body.childNodes).forEach(function (n) {
    if (n.nodeType !== 3 || !n.parentNode || n.textContent.replace(/^\s+/, '').indexOf('/* Lesson playground') !== 0) return;
    var run = [n], x = n.nextSibling, closed = false;
    for (var k = 0; x && k < 60; k++, x = x.nextSibling) {
      run.push(x);
      if (x.nodeType === 3 && /else\s*\{\s*scan\(document\);\s*\}\s*\}\)\(\);/.test(x.textContent)) { closed = true; break; }
      if (x.nodeType === 1 && /^(HR|H1|H2|SECTION)$/.test(x.tagName)) break;
    }
    if (closed) run.forEach(function (r) { if (r.parentNode) r.parentNode.removeChild(r); });
  });

  // ── the course's own ClickUp / Jira switch ──
  // Its <style> and <script> reach the page as plain text (the tags are stripped before the plugin
  // prints the course), so the code shows up as a paragraph and the buttons do nothing. Drop that
  // stray text (only bare text directly in the lesson that reads like CSS or JavaScript) and wire the
  // buttons here; the styles for them are in learn.css.
  [].slice.call(body.childNodes).forEach(function (n) {
    if (n.nodeType === 3 && n.textContent.trim().length > 40 && /[{};]/.test(n.textContent) &&
        /(^|\s)(\.[\w-]+\s*\{|\(function\s*\(|var\s+\w+\s*=|document\.)/.test(n.textContent)) {
      n.parentNode.removeChild(n);
    }
  });
  var tmBtns = [].slice.call(body.querySelectorAll('.tm-btn[data-tm]'));
  var tmSects = [].slice.call(body.querySelectorAll('.tm-section[data-tm]'));
  function setTm(key) {
    tmSects.forEach(function (s) { var on = s.getAttribute('data-tm') === key; s.classList.toggle('tm-active', on); s.hidden = !on; });
    tmBtns.forEach(function (b) { var on = b.getAttribute('data-tm') === key; b.classList.toggle('active', on); b.setAttribute('aria-pressed', on ? 'true' : 'false'); });
  }
  if (tmBtns.length && tmSects.length) {
    var tm = load('jl-tm');
    if (!tmBtns.some(function (b) { return b.getAttribute('data-tm') === tm; })) tm = tmBtns[0].getAttribute('data-tm');
    tmBtns.forEach(function (b) {
      b.type = 'button';
      b.addEventListener('click', function () { save('jl-tm', b.getAttribute('data-tm')); setTm(b.getAttribute('data-tm')); refresh(); });
    });
    setTm(tm);
  }

  // The "Try it yourself" editors (playground.js, printed just before this script).
  if (window.__pgPlayground) window.__pgPlayground.scan(body);

  // ── sessions: the plugin separates them with <hr> ──
  var foot = body.querySelector(':scope > .learn-foot');
  var groups = [[]];
  [].slice.call(body.childNodes).forEach(function (c) {
    if (c === foot) return;
    if (c.nodeType === 1 && c.tagName === 'HR') { if (groups[groups.length - 1].some(isContent)) groups.push([]); c.parentNode.removeChild(c); return; }
    groups[groups.length - 1].push(c);
  });
  if (!groups[groups.length - 1].some(isContent)) { var rest = groups.pop(); if (groups.length) groups[groups.length - 1] = groups[groups.length - 1].concat(rest); else groups.push(rest); }

  var sessions = groups.map(function (nodes, i) {
    var sec = el('section', 'pg-session');
    sec.id = 'session-' + (i + 1);
    sec.tabIndex = -1;
    body.insertBefore(sec, foot);
    nodes.forEach(function (n) { sec.appendChild(n); });
    return sec;
  });
  if (pick) body.insertBefore(pick, sessions[0] || foot);

  // A part is named by its <h1> (HTML, CSS and JavaScript courses), else by its first heading that is
  // not one of the lesson template's fixed headings ("In simple terms", "Deep dive", ... in the Python
  // course; "Introduction" in AI Software Lifecycle), without a leading "1." or "Step 1:".
  // Courses built from one lesson template (every part has an <h1>, or every part opens with the same
  // heading) count lessons; the others count sessions.
  var TEMPLATE = /^(introduction|in simple terms|the simple version|deep dive|what's really happening|try it yourself|try more tags|challenge|summary|what you learned|recap|key takeaways)$/i;
  function heads(sec, sel) { return [].slice.call(sec.querySelectorAll(sel)).filter(function (x) { return !x.closest('.tool-section, .os-section'); }); }
  function firstH1(sec) { var h = heads(sec, 'h1')[0]; return h ? h.textContent.trim() : ''; }
  function title(sec, n) {
    if (firstH1(sec)) return firstH1(sec);
    var hs = heads(sec, 'h2, h3');
    for (var i = 0; i < hs.length; i++) {
      var t = hs[i].textContent.replace(/\s+/g, ' ').trim().replace(/^(step\s+)?\d+\s*[.:·)-]\s*/i, '');
      if (t && !TEMPLATE.test(t)) return t;
    }
    if (sec.querySelector('.tool-section')) return 'Set up your coding agent';
    return unit + ' ' + n;
  }
  function opener(sec) { var h = sec.querySelector('h1, h2'); return h ? h.textContent.trim().toLowerCase() : ''; }
  var unit = sessions.length > 1 && (sessions.every(function (s) { return firstH1(s) !== ''; }) ||
    sessions.every(function (s) { return opener(s) !== '' && opener(s) === opener(sessions[0]); })) ? 'Lesson' : 'Session';
  var titles = sessions.map(function (s, i) { return title(s, i + 1); });
  var total = sessions.length;

  sessions.forEach(function (sec, i) {
    if (total < 2) return; // a course without sessions: no "Session 1 of 1", no pager
    var head = el('p', 'pg-session-head');
    head.appendChild(document.createTextNode(unit + ' ' + (i + 1) + ' '));
    head.appendChild(el('span', '', 'of ' + total));
    sec.insertBefore(head, sec.firstChild);
    sec.setAttribute('aria-label', unit + ' ' + (i + 1) + ': ' + titles[i]);
    var pager = el('nav', 'pg-session-pager');
    pager.setAttribute('aria-label', unit + 's');
    function link(cls, href, small, big) { var a = el('a', cls); a.href = href; a.appendChild(el('small', '', small)); a.appendChild(el('b', '', big)); pager.appendChild(a); }
    if (i > 0) link('pg-prev', '#session-' + i, '← Previous ' + unit.toLowerCase(), titles[i - 1]);
    if (i < total - 1) link('pg-next', '#session-' + (i + 2), 'Next: ' + unit.toLowerCase() + ' ' + (i + 2), titles[i + 1] + ' →');
    else {
      var back = document.querySelector('.learn-toc .toc-back');
      if (back) link('pg-next', back.getAttribute('href'), 'You reached the end', 'Back to the course overview →');
    }
    sec.appendChild(pager);
  });

  // ── table of contents ──
  // On a phone the list sits above the lesson: show only the current session, with a button to open
  // the whole list (learn.css hides the others below 900px unless the list is open).
  var current = 0;
  var tocBox = document.getElementById('learnToc') || toc.parentNode;
  var more = null;
  if (total > 1) {
    more = el('button', 'pg-toc-more');
    more.type = 'button';
    more.setAttribute('aria-expanded', 'false');
    more.setAttribute('aria-controls', 'tocLinks');
    more.addEventListener('click', function () {
      var open = !tocBox.classList.contains('pg-toc-open');
      tocBox.classList.toggle('pg-toc-open', open);
      more.setAttribute('aria-expanded', open ? 'true' : 'false');
      more.textContent = open ? 'Show less' : 'All ' + total + ' ' + unit.toLowerCase() + 's';
    });
    more.textContent = 'All ' + total + ' ' + unit.toLowerCase() + 's';
    toc.parentNode.insertBefore(more, toc.nextSibling);
  }
  function refresh() {
    while (toc.firstChild) toc.removeChild(toc.firstChild);
    if (total < 2) { // one session: just its headings, without the hidden agents' and computers' ones
      [].slice.call(body.querySelectorAll('h2')).filter(shown).forEach(function (h) { var a = el('a', '', h.textContent.trim()); a.href = '#' + h.id; toc.appendChild(a); });
      if (pick) pick.hidden = false;
      return;
    }
    sessions.forEach(function (sec, i) {
      var a = el('a', 'pg-toc-session');
      a.href = '#session-' + (i + 1);
      a.appendChild(el('small', '', unit + ' ' + (i + 1)));
      a.appendChild(document.createTextNode(titles[i]));
      if (i === current) a.setAttribute('aria-current', 'true');
      toc.appendChild(a);
      if (i !== current) return;
      var heads = [].slice.call(sec.querySelectorAll('h2')).filter(shown);
      if (heads.length < 2) return;
      var sub = el('div', 'pg-toc-sub');
      heads.forEach(function (h) { var s = el('a', '', h.textContent.trim()); s.href = '#' + h.id; sub.appendChild(s); });
      toc.appendChild(sub);
    });
    if (pick) pick.hidden = !sessions[current] || !sessions[current].querySelector('.tool-section, .os-section');
  }

  function show(i, focus) {
    current = Math.max(0, Math.min(total - 1, i));
    sessions.forEach(function (s, n) { s.hidden = n !== current; });
    save('pg-learn-session:' + course, String(current + 1));
    refresh();
    if (more && tocBox.classList.contains('pg-toc-open')) more.click(); // close the phone list after choosing
    if (focus) {
      var top = (pick && !pick.hidden ? pick : sessions[current]).getBoundingClientRect().top + window.pageYOffset - 96;
      window.scrollTo(0, Math.max(0, top));
      try { sessions[current].focus({ preventScroll: true }); } catch (e) { sessions[current].focus(); }
    }
  }

  function route(focus, first) {
    var id = decodeURIComponent(location.hash.slice(1));
    var m = /^session-(\d+)$/.exec(id);
    if (m) return show(+m[1] - 1, focus);
    var target = id ? document.getElementById(id) : null;
    var sec = target && target.closest ? target.closest('.pg-session') : null;
    if (sec && body.contains(sec)) {
      show(sessions.indexOf(sec), false);
      target.scrollIntoView();
      return;
    }
    // A link to something outside the lesson (the skip link's #main, #7227): the browser moves
    // there; the open session stays.
    if (target && !first) return;
    // No session in the link: on arrival, the session last opened; after Back to the bare page, the first.
    var last = first ? parseInt(load('pg-learn-session:' + course), 10) : 0;
    show(last > 0 ? last - 1 : 0, focus);
  }

  if (!total) return;
  window.addEventListener('hashchange', function () { route(true); });
  route(false, true);
})();

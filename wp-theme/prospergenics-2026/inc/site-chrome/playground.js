/* Lesson playground for the jengo-listings course lessons (card #7146), a theme copy of the playground of
 * card [5818]. That one is embedded in every lesson of the HTML, CSS, JavaScript and Python courses, but
 * the <style> and <script> tags are stripped before the plugin prints the lesson, so its code shows up as
 * text and none of the ~340 "Try it yourself" editors run. This is the same playground with the tags
 * inside its strings put back. learn.js removes the stranded text and calls scan(); if the original
 * playground runs again one day (fixed at the source), it defines window.__pgPlayground first and this
 * copy steps aside.
 *
 * A block is the markup the lessons already carry: .editor-container[data-lang] > .editor-panel
 * (.panel-header with .btn-run / .btn-reset, a <textarea> with the starter code) + .preview-panel.
 * No data-lang means html (the HTML and CSS courses). The code runs in a sandboxed iframe
 * (sandbox="allow-scripts", no allow-same-origin): it cannot read the page, its cookies or storage.
 * JavaScript mirrors console.* into the output; Python runs on Skulpt from jsDelivr, as before.
 *
 * Slow and costly connections (card #6469): a course page holds every lesson, so nothing renders on load.
 *   - An HTML/CSS result renders when its lesson is open and the box comes near the screen
 *     (IntersectionObserver), or when the learner presses Run / Ctrl+Enter. A JavaScript or Python
 *     result only on Run. Until then the Result panel is a placeholder of the same size, no iframe.
 *   - Rendered by itself, an example gets a Content-Security-Policy that blocks every request (images,
 *     fonts, frames, media, scripts from elsewhere), so it can never load anything on its own.
 *   - An example that loads content from other sites (YouTube, maps, Wikipedia, picsum, fonts, an mp3)
 *     shows "This example loads content from <host> – tap to load" instead; the tap, or Run, renders
 *     it in full.
 *   - When its lesson closes (learn.js calls refresh()), a box's iframe is removed: a video in it
 *     stops, and the next visit starts from the placeholder again (the learner's code stays).
 * Plain ES5: the file is inlined into the page without a build step. */
(function () {
  'use strict';
  if (window.__pgPlayground) { return; }

  // Built by concatenation, so that email obfuscation never takes the versioned package name for an address.
  var SKULPT_BASE = 'https://cdn.jsdelivr.net/npm/skulpt' + '@' + '1.2.0/dist/';
  var SKULPT = [SKULPT_BASE + 'skulpt.min.js', SKULPT_BASE + 'skulpt-stdlib.js'];
  var AUTO_RUN_DELAY = 400;
  var NEAR = '200px 0px'; // render a result a little before it scrolls into view
  var LANGS = { html: 1, css: 1, javascript: 1, python: 1 };
  var END = '</' + 'script>';
  // For a result that renders by itself: inline styles and scripts only, no request of any kind.
  var LOCKED = "default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline' 'unsafe-eval'; img-src data: blob:; media-src data: blob:; font-src data:";

  // Source text for a script that runs inside the generated document. JSON.stringify output is safe in
  // a script block except for a closing script tag, an HTML comment opener and the two line separators,
  // so escape every "<" and U+2028/2029.
  function js(value) {
    return JSON.stringify(value).replace(/</g, '\\u003c').replace(/\u2028/g, '\\u2028').replace(/\u2029/g, '\\u2029');
  }

  var OUTPUT_STYLE =
    'body{font:13px/1.45 "Cascadia Code","Fira Code",ui-monospace,monospace;padding:12px;margin:0;background:#1a1a1a;color:#d4d4d4}' +
    '.line{padding:1px 0;white-space:pre-wrap;word-break:break-word}.warn{color:#e5c07b}.error{color:#e06c75}.info{color:#61afef}.hint{color:#8a8a8a;font-style:italic}';

  function page(head, body) {
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>' + OUTPUT_STYLE + '</style>' + head + '</head><body>' + body + '</body></html>';
  }

  function javascriptDoc(code) {
    return page('', '<div id="out"></div><script>' +
      '(function(){var out=document.getElementById("out");' +
      'function show(args,cls){var d=document.createElement("div");d.className="line"+(cls?" "+cls:"");' +
      'd.textContent=Array.prototype.map.call(args,function(a){if(a===null)return "null";if(a===undefined)return "undefined";' +
      'if(typeof a==="object"){try{return JSON.stringify(a,null,2)}catch(e){return String(a)}}return String(a)}).join(" ");out.appendChild(d)}' +
      'console.log=function(){show(arguments,"")};console.info=function(){show(arguments,"info")};' +
      'console.warn=function(){show(arguments,"warn")};console.error=function(){show(arguments,"error")};' +
      'window.alert=function(m){show(["alert: "+m],"info")};' +
      'window.addEventListener("error",function(e){show([e.message],"error")});' +
      'window.addEventListener("unhandledrejection",function(e){show(["Unhandled rejection: "+(e.reason&&e.reason.message||e.reason)],"error")});' +
      'try{(0,eval)(' + js(code) + ')}catch(e){show([String(e)],"error")}})();' + END);
  }

  // A few lessons (Python 34-36: files, io, json) use parts of Python that Skulpt doesn't have. That error
  // ("NotImplementedError: io is not yet implemented in Skulpt", "NameError: name 'FileNotFoundError' is
  // not defined", "ImportError: No module named json") becomes a short note; any other error shows as it is.
  // It runs inside the output frame, where it is passed in as source text.
  function skulptMissing(message) {
    var m = /^NotImplementedError: ([\w.]+) is not yet implemented in Skulpt/.exec(message) ||
      /^ImportError: No module named ['"]?([\w.]+)/.exec(message) ||
      /^NameError: name '(\w+(?:Error|Exception|Warning))' is not defined/.exec(message);
    return m ? m[1] : '';
  }
  var SKULPT_MISSING_NOTE = ['This example uses a Python feature the in-browser runner doesn\'t have (', '). Copy it and run it on your computer.'];

  function pythonDoc(code) {
    return page('', '<div id="out"><div class="line hint">Running...</div></div>' +
      '<script src="' + SKULPT[0] + '">' + END + '<script src="' + SKULPT[1] + '">' + END + '<script>' +
      '(function(){var out=document.getElementById("out");' +
      'function line(t,cls){var d=document.createElement("div");d.className="line"+(cls?" "+cls:"");d.textContent=t;out.appendChild(d)}' +
      'if(typeof Sk==="undefined"){out.innerHTML="";line("Could not load the Python runtime. Check your connection and press Run again.","error");return}' +
      'out.innerHTML="";' +
      'function read(x){if(Sk.builtinFiles===undefined||Sk.builtinFiles.files[x]===undefined)throw "File not found: \'"+x+"\'";return Sk.builtinFiles.files[x]}' +
      'Sk.configure({output:function(t){var parts=t.split("\\n");parts.forEach(function(p,i){if(i<parts.length-1||p!=="")line(p)})},' +
      'read:read,__future__:Sk.python3,inputfun:function(){return ""}});' +
      'var missing=' + String(skulptMissing) + ',note=' + js(SKULPT_MISSING_NOTE) + ';' +
      'Sk.misceval.asyncToPromise(function(){return Sk.importMainWithBody("<stdin>",false,' + js(code) + ',true)})' +
      '.catch(function(e){var t=String(e),f=missing(t);if(f){line(note[0]+f+note[1],"info")}else{line(t,"error")}})})();' + END);
  }

  // locked: the policy that blocks every request goes first, before anything in the example can load
  // (after a leading <!DOCTYPE>, so a full page keeps its standards mode).
  function htmlDoc(code, locked) {
    var csp = locked ? '<meta http-equiv="Content-Security-Policy" content="' + LOCKED + '">' : '';
    if (/<!doctype|<html[\s>]/i.test(code)) {
      var d = /<!doctype[^>]*>/i.exec(code);
      if (d && /^\s*(?:<!--[\s\S]*?-->\s*)*$/.test(code.slice(0, d.index))) {
        return code.slice(0, d.index + d[0].length) + csp + code.slice(d.index + d[0].length);
      }
      return csp + code;
    }
    return '<!DOCTYPE html><html><head><meta charset="utf-8">' + csp + '<style>body{font-family:Arial,sans-serif;padding:16px}</style></head><body>' + code + '</body></html>';
  }

  function buildDoc(lang, code, locked) {
    if (lang === 'javascript') { return javascriptDoc(code); }
    if (lang === 'python') { return pythonDoc(code); }
    return htmlDoc(code, locked);
  }

  // The other sites an HTML/CSS example loads from when it renders: absolute (and //host) URLs in the
  // attributes that fetch (src, srcset, poster, data, background, <link>/<base> href, a refresh <meta>),
  // in CSS url() and @import, and http(s) URLs in its scripts. Links and forms only go out when clicked.
  function externalHosts(code) {
    var hosts = [], m, a;
    function add(url, bare) {
      var h = (bare ? /^\s*(?:https?:)?\/\/([^\/\s'"?#:>)\\]+)/i : /^\s*https?:\/\/([^\/\s'"?#:>)\\]+)/i).exec(url);
      if (h && hosts.indexOf(h[1].toLowerCase()) < 0) { hosts.push(h[1].toLowerCase()); }
    }
    var tag = /<([a-z][\w-]*)((?:[^>"']|"[^"]*"|'[^']*')*)>/gi;
    while ((m = tag.exec(code))) {
      var name = m[1].toLowerCase(), attr = /([\w:-]+)\s*=\s*(?:"([^"]*)"|'([^']*)'|([^\s"'>]+))/g;
      while ((a = attr.exec(m[2]))) {
        var k = a[1].toLowerCase(), v = a[2] !== undefined ? a[2] : a[3] !== undefined ? a[3] : a[4];
        if (/^(src|poster|data|background|xlink:href)$/.test(k) || (k === 'href' && /^(link|base|image|use|feimage)$/.test(name))) { add(v, true); }
        else if (k === 'srcset' || k === 'imagesrcset') { v.split(',').forEach(function (s) { add(s, true); }); }
        else if (k === 'content' && name === 'meta' && /^\s*\d*\s*[;,]\s*url\s*=/i.test(v)) { add(v.replace(/^[^=]*=\s*['"]?/, ''), true); }
      }
    }
    var css = /url\(\s*['"]?([^'")\s]+)|@import\s+['"]([^'"]+)/gi;
    while ((m = css.exec(code))) { add(m[1] || m[2], true); }
    var script = /<script\b[^>]*>([\s\S]*?)<\/script/gi, url;
    while ((m = script.exec(code))) {
      url = /https?:\/\/[^\s'"`)]+/gi;
      while ((a = url.exec(m[1]))) { add(a[0], false); }
    }
    return hosts;
  }

  function sites(hosts) {
    if (hosts.length < 3) { return hosts.join(' and '); }
    return hosts.slice(0, 2).join(', ') + ' and ' + (hosts.length - 2) + ' more';
  }

  function langOf(box) {
    var l = (box.getAttribute('data-lang') || 'html').toLowerCase();
    return Object.prototype.hasOwnProperty.call(LANGS, l) ? l : 'html';
  }

  function element(tag, className, text) {
    var e = document.createElement(tag);
    if (className) { e.className = className; }
    if (text) { e.textContent = text; }
    return e;
  }

  function ensure(parent, selector, make) {
    var el = parent.querySelector(selector);
    if (!el) { el = make(); parent.appendChild(el); }
    return el;
  }

  // Shown: laid out on the page, i.e. not inside a closed lesson or a hidden agent/OS section.
  function shown(el) { return el.getClientRects().length > 0; }

  var boxes = [];
  var observer = 'IntersectionObserver' in window ? new IntersectionObserver(function (entries) {
    entries.forEach(function (e) {
      var c = e.target.__pg;
      if (!c) { return; }
      if (e.isIntersecting) { c.near(); }
      else if (!shown(e.target)) { c.close(); } // its lesson closed (scrolling away keeps the result)
    });
  }, { rootMargin: NEAR }) : null;

  function setup(box) {
    if (box.getAttribute('data-pg-ready')) { return; }
    var lang = langOf(box);
    var editor = box.querySelector('.editor-panel');
    var textarea = box.querySelector('textarea');
    if (!editor || !textarea) { return; }
    box.setAttribute('data-pg-ready', '1');
    box.setAttribute('data-pg-lang', lang);
    var label = lang === 'html' || lang === 'css' ? 'Result' : 'Output';

    var preview = ensure(box, '.preview-panel', function () {
      var p = element('div', 'preview-panel');
      var h = element('div', 'panel-header');
      h.appendChild(element('span', '', label));
      p.appendChild(h);
      return p;
    });
    var stale = preview.querySelector('iframe');
    if (stale) { stale.parentNode.removeChild(stale); }
    textarea.setAttribute('spellcheck', 'false');
    textarea.setAttribute('aria-label', 'Code editor');

    var buttons = ensure(editor.querySelector('.panel-header') || editor, '.editor-buttons', function () { return element('div', 'editor-buttons'); });
    var run = buttons.querySelector('.btn-run') || buttons.appendChild(element('button', 'btn-run', 'Run'));
    var reset = buttons.querySelector('.btn-reset') || buttons.appendChild(element('button', 'btn-reset', 'Reset'));
    run.type = 'button';
    reset.type = 'button';

    var initial = textarea.value;
    var live = lang === 'html' || lang === 'css';
    var timer = null;
    var frame = null;   // only while a result is on screen
    var asked = false;  // the learner pressed Run or tapped to load: the example renders in full
    var stage = element('div', 'pg-stage');
    preview.appendChild(stage);

    // The Result panel without a frame: empty until it renders, the Run hint, or the tap-to-load button.
    function placeholder(content) {
      if (frame) { frame.parentNode.removeChild(frame); frame = null; }
      while (stage.firstChild) { stage.removeChild(stage.firstChild); }
      if (content) { stage.appendChild(content); }
      if (!stage.parentNode) { preview.appendChild(stage); }
    }
    function idle() {
      placeholder(live ? null : element('p', 'pg-stage-hint', 'Press Run (or Ctrl+Enter) to see the output.'));
    }
    function gate(hosts) {
      var b = element('button', 'pg-embed-gate', 'This example loads content from ' + sites(hosts) + ' \u2013 tap to load');
      b.type = 'button';
      b.addEventListener('click', function () { render(true); if (frame) { frame.focus(); } });
      placeholder(b);
    }

    // full: the learner asked (Run, Ctrl+Enter, tap to load); otherwise it renders by itself, locked.
    function render(full) {
      if (timer) { clearTimeout(timer); timer = null; }
      if (full) { asked = true; }
      var code = textarea.value;
      if (live && !asked) {
        var hosts = externalHosts(code);
        if (hosts.length) { gate(hosts); return; }
      }
      if (!frame) {
        frame = document.createElement('iframe');
        frame.setAttribute('sandbox', 'allow-scripts');
        frame.setAttribute('title', label);
        frame.setAttribute('loading', 'lazy');
        frame.setAttribute('referrerpolicy', 'no-referrer');
      }
      if (stage.parentNode) { stage.parentNode.removeChild(stage); }
      if (frame.parentNode !== preview) { preview.appendChild(frame); }
      frame.srcdoc = buildDoc(lang, code, live && !asked);
    }

    box.__pg = {
      // near the screen in an open lesson: an HTML/CSS result renders by itself, once
      near: function () { if (live && !frame && !stage.firstChild) { render(false); } },
      // its lesson closed: drop the frame (and the learner's earlier "load it")
      close: function () {
        if (timer) { clearTimeout(timer); timer = null; }
        if (frame || asked) { asked = false; idle(); }
      }
    };

    run.addEventListener('click', function () { render(true); });
    reset.addEventListener('click', function () {
      textarea.value = initial;
      if (live) { render(false); } else { idle(); }
    });
    textarea.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); render(true); }
    });
    if (live) {
      textarea.addEventListener('input', function () {
        if (timer) { clearTimeout(timer); }
        timer = setTimeout(function () { if (shown(box)) { render(false); } }, AUTO_RUN_DELAY);
      });
    }
    idle();
    boxes.push(box);
    if (observer) { observer.observe(box); }
  }

  // After a lesson opens or closes (learn.js): drop the results that are no longer shown. Without
  // IntersectionObserver, render the ones that are.
  function refresh(root) {
    var mine = boxes.filter(function (b) { return !root || root.contains(b); });
    var on = mine.map(shown); // all reads first (one layout), then the changes
    mine.forEach(function (b, i) { if (!on[i]) { b.__pg.close(); } else if (!observer) { b.__pg.near(); } });
  }

  function scan(root) {
    var found = (root || document).querySelectorAll('.editor-container');
    for (var i = 0; i < found.length; i++) { setup(found[i]); }
    if (!observer) { setTimeout(function () { refresh(root); }, 0); } // after learn.js has closed the other lessons
  }

  window.__pgPlayground = { scan: scan, refresh: refresh, buildDoc: buildDoc, externalHosts: externalHosts, skulptMissing: skulptMissing, theme: true };
})();

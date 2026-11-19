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
 * Plain ES5: the file is inlined into the page without a build step. */
(function () {
  'use strict';
  if (window.__pgPlayground) { return; }

  // Built by concatenation, so that email obfuscation never takes the versioned package name for an address.
  var SKULPT_BASE = 'https://cdn.jsdelivr.net/npm/skulpt' + '@' + '1.2.0/dist/';
  var SKULPT = [SKULPT_BASE + 'skulpt.min.js', SKULPT_BASE + 'skulpt-stdlib.js'];
  var AUTO_RUN_DELAY = 400;
  var LANGS = { html: 1, css: 1, javascript: 1, python: 1 };
  var END = '</' + 'script>';

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

  function htmlDoc(code) {
    if (/<!doctype|<html[\s>]/i.test(code)) { return code; }
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><style>body{font-family:Arial,sans-serif;padding:16px}</style></head><body>' + code + '</body></html>';
  }

  function emptyDoc() {
    return page('', '<div class="line hint">Press Run (or Ctrl+Enter) to see the output.</div>');
  }

  function buildDoc(lang, code) {
    if (lang === 'javascript') { return javascriptDoc(code); }
    if (lang === 'python') { return pythonDoc(code); }
    return htmlDoc(code);
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
    var frame = preview.querySelector('iframe');
    if (!frame) { frame = document.createElement('iframe'); preview.appendChild(frame); }
    frame.setAttribute('sandbox', 'allow-scripts');
    frame.setAttribute('title', label);
    frame.setAttribute('loading', 'lazy');
    frame.setAttribute('referrerpolicy', 'no-referrer');
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

    function render() {
      if (timer) { clearTimeout(timer); timer = null; }
      frame.srcdoc = buildDoc(lang, textarea.value);
    }

    run.addEventListener('click', render);
    reset.addEventListener('click', function () {
      textarea.value = initial;
      if (live) { render(); } else { frame.srcdoc = emptyDoc(); }
    });
    textarea.addEventListener('keydown', function (e) {
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') { e.preventDefault(); render(); }
    });
    if (live) {
      textarea.addEventListener('input', function () {
        if (timer) { clearTimeout(timer); }
        timer = setTimeout(render, AUTO_RUN_DELAY);
      });
      render();
    } else {
      frame.srcdoc = emptyDoc();
    }
  }

  function scan(root) {
    var boxes = (root || document).querySelectorAll('.editor-container');
    for (var i = 0; i < boxes.length; i++) { setup(boxes[i]); }
  }

  window.__pgPlayground = { scan: scan, buildDoc: buildDoc, skulptMissing: skulptMissing, theme: true };
})();

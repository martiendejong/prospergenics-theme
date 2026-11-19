/*
 * Prospergenics courses track: the instructor course wizard (card #6363) with the lesson quiz
 * editor (card #6461). Loaded on /instructor/courses/new/ and /instructor/courses/<id>/edit/ only
 * (routing.php), after window.pgCoursesWizard; the other course pages only need courses.js.
 */
(function () {
  'use strict';

  /* ------------------------------------------------------------------ course wizard */
  var cfg = window.pgCoursesWizard;
  var form = document.getElementById('pgc-wizard');
  if (!cfg || !form) { return; }
  form.hidden = false;

  var keySeq = 0;
  function newKey(prefix) { keySeq += 1; return prefix + Date.now().toString(36) + keySeq; }
  function $(sel, root) { return (root || document).querySelector(sel); }
  function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  /* --- lesson quizzes (card #6461): editor state <-> the {pass_mark, questions} the server stores */
  function newQuestion(type) {
    return { key: newKey('q'), id: '', type: type || 'single', prompt: '', explanation: '', answers: '',
      options: [{ key: newKey('o'), text: '', correct: false }, { key: newKey('o'), text: '', correct: false }] };
  }
  function emptyQuiz() { return { open: false, pass_mark: cfg.passMark || 70, questions: [] }; }
  function quizFromServer(q) {
    var out = emptyQuiz();
    if (!q || !q.questions) { return out; }
    out.pass_mark = q.pass_mark || out.pass_mark;
    out.questions = q.questions.map(function (x) {
      var item = { key: newKey('q'), id: x.id, type: x.type, prompt: x.prompt || '', explanation: x.explanation || '', answers: (x.answers || []).join('\n'), options: [] };
      item.options = (x.options || []).map(function (t, i) { return { key: newKey('o'), text: t, correct: (x.correct || []).indexOf(i) !== -1 }; });
      while (item.options.length < 2) { item.options.push({ key: newKey('o'), text: '', correct: false }); }
      return item;
    });
    return out;
  }
  function quizPayload(q) {
    return {
      pass_mark: Math.min(100, Math.max(1, parseInt(q.pass_mark, 10) || cfg.passMark || 70)),
      questions: q.questions.map(function (x) {
        var out = { id: x.id || x.key, type: x.type, prompt: x.prompt, explanation: x.explanation };
        if (x.type === 'short') { out.answers = x.answers.split('\n'); }
        else { out.options = x.options.map(function (o) { return { text: o.text, correct: !!o.correct }; }); }
        return out;
      })
    };
  }
  /** Why a question is not shown to learners yet ('' when it is complete). Mirrors quiz.php. */
  function questionProblem(x) {
    if (!x.prompt.trim()) { return 'Write the question.'; }
    if (x.type === 'short') { return x.answers.trim() ? '' : 'Add at least one accepted answer.'; }
    var filled = x.options.filter(function (o) { return o.text.trim(); });
    if (filled.length < 2) { return 'Add at least two answer options.'; }
    return filled.some(function (o) { return o.correct; }) ? '' : 'Mark the correct answer.';
  }

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
          return { key: newKey('l'), id: l.id, title: l.title, video_url: l.video_url || '', content: l.content_raw || '', quiz: quizFromServer(l.quiz) };
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
    state.modules.push({ key: newKey('m'), id: 0, title: '', lessons: [{ key: newKey('l'), id: 0, title: '', video_url: '', content: '', quiz: emptyQuiz() }] });
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
    url = url.trim();
    if (/^https?:\/\/(www\.|m\.)?(youtube\.com\/(watch\?|embed\/|shorts\/|live\/)|youtu\.be\/|youtube-nocookie\.com\/embed\/|(player\.)?vimeo\.com\/)/i.test(url)) { return true; }
    // or a video file uploaded to this site (card #6469)
    try {
      var u = new URL(url);
      return /^https?:$/.test(u.protocol) && u.hostname.toLowerCase() === String(cfg.siteHost || location.hostname).toLowerCase() && /\.(mp4|m4v|webm|ogv)$/i.test(u.pathname);
    } catch (err) { return false; }
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
          '<div class="pgc-field"><label for="' + l.key + '-video">Video URL (YouTube, Vimeo or a video file from this site, optional)</label>' +
          '<input id="' + l.key + '-video" type="url" inputmode="url" data-field="lvideo" value="' + esc(l.video_url) + '" aria-describedby="' + l.key + '-video-error"' + (bad ? ' aria-invalid="true"' : '') + ' />' +
          '<p id="' + l.key + '-video-error" class="pgc-field-error"' + (bad ? '' : ' hidden') + '>Use a YouTube or Vimeo link, or a video file (MP4, WebM) uploaded to this site; other links are not saved.</p></div>' +
          '<div class="pgc-field"><label for="' + l.key + '-content">Lesson ' + ln + ' text</label>' +
          '<textarea id="' + l.key + '-content" rows="5" data-field="lcontent">' + esc(l.content) + '</textarea></div>' +
          quizEditorHtml(l, ln) +
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

  function quizEditorHtml(l, ln) {
    var q = l.quiz, k = l.key, n = q.questions.length;
    var html = '<details class="pgc-quiz-edit" data-quiz' + (q.open ? ' open' : '') + '><summary>Quiz for lesson ' + ln + ' (optional): ' +
      (n ? n + (n === 1 ? ' question' : ' questions') : 'none yet') + '</summary>' +
      '<p class="pgc-help">With a quiz, learners complete this lesson by passing it instead of pressing "Mark as complete". They see whether each answer was right, with your explanation, and can try again.</p>' +
      '<div class="pgc-field pgc-field--short"><label for="' + k + '-pass">Pass mark (%)</label>' +
      '<input id="' + k + '-pass" type="number" min="1" max="100" step="1" inputmode="numeric" data-field="qpass" value="' + esc(q.pass_mark) + '" /></div>';
    q.questions.forEach(function (x, qi) {
      var qk = x.key, qn = qi + 1, problem = questionProblem(x);
      html += '<fieldset class="pgc-quiz-q-edit" data-q="' + qi + '"><legend>Question ' + qn + '</legend>' +
        '<div class="pgc-field"><label for="' + qk + '-type">Question ' + qn + ' type</label><select id="' + qk + '-type" data-field="qtype">';
      Object.keys(cfg.quizTypes || {}).forEach(function (t) {
        html += '<option value="' + esc(t) + '"' + (t === x.type ? ' selected' : '') + '>' + esc(cfg.quizTypes[t]) + '</option>';
      });
      html += '</select></div>' +
        '<div class="pgc-field"><label for="' + qk + '-prompt">Question ' + qn + '</label><textarea id="' + qk + '-prompt" rows="2" maxlength="600" data-field="qprompt">' + esc(x.prompt) + '</textarea></div>';
      if (x.type === 'short') {
        html += '<div class="pgc-field"><label for="' + qk + '-answers">Accepted answers, one per line</label>' +
          '<textarea id="' + qk + '-answers" rows="3" data-field="qanswers" aria-describedby="' + qk + '-answers-help">' + esc(x.answers) + '</textarea>' +
          '<p id="' + qk + '-answers-help" class="pgc-help">An answer counts when it matches one of these exactly, ignoring capital letters and spaces.</p></div>';
      } else {
        var multi = x.type === 'multiple';
        html += '<fieldset class="pgc-quiz-opts-edit"><legend>Answer options: mark ' + (multi ? 'every correct one' : 'the correct one') + '</legend>';
        x.options.forEach(function (o, oi) {
          var on = oi + 1;
          html += '<div class="pgc-quiz-opt-edit" data-o="' + oi + '">' +
            '<span class="pgc-quiz-opt-correct"><input type="' + (multi ? 'checkbox' : 'radio') + '" id="' + o.key + '-c" name="' + qk + '-correct" data-field="ocorrect"' + (o.correct ? ' checked' : '') + ' />' +
            '<label for="' + o.key + '-c">Correct<span class="screen-reader-text">: option ' + on + ' of question ' + qn + '</span></label></span>' +
            '<label class="screen-reader-text" for="' + o.key + '-t">Option ' + on + ' of question ' + qn + '</label>' +
            '<input id="' + o.key + '-t" type="text" maxlength="300" data-field="otext" value="' + esc(o.text) + '" placeholder="Option ' + on + '" />' +
            '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="option-remove"' + (x.options.length <= 2 ? ' disabled' : '') + '>Remove<span class="screen-reader-text"> option ' + on + ' of question ' + qn + '</span></button>' +
            '</div>';
        });
        html += '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="option-add"' + (x.options.length >= 10 ? ' disabled' : '') + '>+ Add an option<span class="screen-reader-text"> to question ' + qn + '</span></button></fieldset>';
      }
      html += '<div class="pgc-field"><label for="' + qk + '-expl">Explanation, shown after answering (optional)</label>' +
        '<textarea id="' + qk + '-expl" rows="2" maxlength="1200" data-field="qexpl">' + esc(x.explanation) + '</textarea></div>' +
        '<p class="pgc-field-error" data-q-warn' + (problem ? '' : ' hidden') + '>Learners do not see this question yet: <span>' + esc(problem) + '</span></p>' +
        '<div class="pgc-item-actions">' +
        '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="question-up"' + (qi === 0 ? ' disabled' : '') + '>Move up<span class="screen-reader-text"> question ' + qn + '</span></button>' +
        '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="question-down"' + (qi === n - 1 ? ' disabled' : '') + '>Move down<span class="screen-reader-text"> question ' + qn + '</span></button>' +
        '<button type="button" class="pgc-btn pgc-btn--danger pgc-btn--small" data-action="question-remove">Remove<span class="screen-reader-text"> question ' + qn + '</span></button>' +
        '</div></fieldset>';
    });
    html += '<button type="button" class="pgc-btn pgc-btn--ghost pgc-btn--small" data-action="question-add">+ Add a question<span class="screen-reader-text"> to lesson ' + ln + '</span></button></details>';
    return html;
  }

  function locate(el) {
    var mEl = el.closest('[data-m]');
    var lEl = el.closest('[data-l]');
    var qEl = el.closest('[data-q]');
    var oEl = el.closest('[data-o]');
    return { mi: mEl ? +mEl.getAttribute('data-m') : -1, li: lEl ? +lEl.getAttribute('data-l') : -1,
      qi: qEl ? +qEl.getAttribute('data-q') : -1, oi: oEl ? +oEl.getAttribute('data-o') : -1 };
  }
  function swap(arr, i, j) { var t = arr[i]; arr[i] = arr[j]; arr[j] = t; }

  function quizInput(t, l, p, field) {
    var q = l.quiz, x = q.questions[p.qi];
    if (field === 'qpass') { q.pass_mark = t.value; return; }
    if (!x) { return; }
    if (field === 'qprompt') { x.prompt = t.value; }
    if (field === 'qexpl') { x.explanation = t.value; }
    if (field === 'qanswers') { x.answers = t.value; }
    if (field === 'otext') { x.options[p.oi].text = t.value; }
    if (field === 'ocorrect') {
      if (x.type === 'single') { x.options.forEach(function (o, i) { o.correct = i === p.oi; }); }
      else { x.options[p.oi].correct = t.checked; }
    }
    if (field === 'qtype') {
      x.type = t.value;
      if (x.type === 'single') { var seen = false; x.options.forEach(function (o) { o.correct = o.correct && !seen; seen = seen || o.correct; }); }
      while (x.type !== 'short' && x.options.length < 2) { x.options.push({ key: newKey('o'), text: '', correct: false }); }
      renderModules('#' + x.key + '-type');
      return;
    }
    var warn = t.closest('[data-q]').querySelector('[data-q-warn]');
    var problem = questionProblem(x);
    warn.hidden = !problem;
    warn.querySelector('span').textContent = problem;
  }

  // <details> open/closed is kept across re-renders ('toggle' does not bubble: capture it).
  modulesEl.addEventListener('toggle', function (e) {
    if (!e.target.hasAttribute || !e.target.hasAttribute('data-quiz')) { return; }
    var p = locate(e.target), l = state.modules[p.mi] && state.modules[p.mi].lessons[p.li];
    if (l) { l.quiz.open = e.target.open; }
  }, true);

  modulesEl.addEventListener('input', function (e) {
    var t = e.target, p = locate(t), field = t.getAttribute('data-field');
    if (p.mi < 0 || !field) { return; }
    var m = state.modules[p.mi];
    if (field === 'mtitle') { m.title = t.value; }
    else {
      var l = m.lessons[p.li];
      if (field === 'ltitle') { l.title = t.value; }
      if (field === 'lcontent') { l.content = t.value; }
      if (/^(q|o)/.test(field)) { quizInput(t, l, p, field); changed(); return; }
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
        state.modules.push({ key: newKey('m'), id: 0, title: '', lessons: [{ key: newKey('l'), id: 0, title: '', video_url: '', content: '', quiz: emptyQuiz() }] });
        renderModules('#' + state.modules[state.modules.length - 1].key + '-title'); changed(); break;
      case 'add-lesson':
        m.lessons.push({ key: newKey('l'), id: 0, title: '', video_url: '', content: '', quiz: emptyQuiz() });
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
      case 'question-add':
        var lq = m.lessons[p.li].quiz;
        lq.open = true;
        lq.questions.push(newQuestion('single'));
        renderModules('#' + lq.questions[lq.questions.length - 1].key + '-type'); changed(); break;
      case 'question-remove':
        var lr = m.lessons[p.li].quiz;
        if (lr.questions[p.qi].prompt && !window.confirm('Remove this question?')) { return; }
        lr.questions.splice(p.qi, 1);
        renderModules('[data-m="' + p.mi + '"] [data-l="' + p.li + '"] [data-action="question-add"]'); changed(); say('Question removed.'); break;
      case 'question-up': case 'question-down':
        var lm = m.lessons[p.li].quiz, qt = a === 'question-up' ? p.qi - 1 : p.qi + 1;
        if (qt < 0 || qt >= lm.questions.length) { return; }
        swap(lm.questions, p.qi, qt);
        renderModules('#' + lm.questions[qt].key + '-type'); changed(); break;
      case 'option-add':
        var xo = m.lessons[p.li].quiz.questions[p.qi];
        if (xo.options.length >= 10) { return; }
        xo.options.push({ key: newKey('o'), text: '', correct: false });
        renderModules('#' + xo.options[xo.options.length - 1].key + '-t'); changed(); break;
      case 'option-remove':
        var xr = m.lessons[p.li].quiz.questions[p.qi];
        if (xr.options.length <= 2) { return; }
        xr.options.splice(p.oi, 1);
        renderModules('#' + xr.options[Math.max(0, p.oi - 1)].key + '-t'); changed(); say('Option removed.'); break;
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
          return { key: l.key, id: l.id || 0, title: l.title, video_url: videoOk(l.video_url) ? l.video_url : '', content: l.content, quiz: quizPayload(l.quiz) };
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

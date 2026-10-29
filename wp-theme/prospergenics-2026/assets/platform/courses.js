/*
 * Prospergenics courses track (cards #6355-#6364): small progressive enhancements on the course
 * pages. Everything works without this script; the instructor course wizard (#6363) has its own,
 * courses-wizard.js, loaded on the wizard only so lesson pages stay light (#6469).
 */
(function () {
  'use strict';

  // Confirm destructive form actions (unenrol, unpublish, delete).
  document.addEventListener('submit', function (e) {
    var form = e.target;
    var msg = form && form.getAttribute && form.getAttribute('data-pgc-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });

  // (The lesson page's course-contents toggle is inline in single-pg_lesson.php, so it folds
  // before the first paint.)

  // Lesson videos (card #6469): click-to-load facades. Nothing from YouTube/Vimeo (or the video
  // file) loads until the learner presses play, and the player does not autoplay. Without this
  // script the play link opens the video on its own page instead.
  document.addEventListener('click', function (e) {
    var link = e.target.closest && e.target.closest('[data-pgc-play]');
    var box = link && link.closest('.pgc-facade-box');
    if (!box) { return; }
    var src = box.getAttribute('data-pgc-src');
    if (!/^https?:\/\//i.test(src || '')) { return; }
    e.preventDefault();
    var el;
    if (box.getAttribute('data-pgc-kind') === 'video') {
      el = document.createElement('video');
      el.controls = true;
      el.preload = 'metadata';
      el.setAttribute('playsinline', '');
      var source = document.createElement('source');
      source.src = src;
      if (box.getAttribute('data-pgc-type')) { source.type = box.getAttribute('data-pgc-type'); }
      el.appendChild(source);
    } else {
      el = document.createElement('iframe');
      el.src = src;
      el.setAttribute('allow', 'encrypted-media; picture-in-picture; fullscreen');
      el.setAttribute('allowfullscreen', '');
      el.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    }
    el.title = 'Video: ' + (box.getAttribute('data-pgc-title') || 'lesson video');
    el.setAttribute('tabindex', '-1');
    box.textContent = '';
    box.appendChild(el);
    box.classList.add('is-loaded');
    el.focus();
  });
})();

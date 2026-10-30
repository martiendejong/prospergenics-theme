/* Prospergenics Platform – portfolios and projects. The forms work without this script; it only
   asks "are you sure?" before a removal or deletion (buttons with data-pgj-confirm). */
(function () {
  'use strict';
  document.addEventListener('submit', function (e) {
    var btn = e.submitter || (e.target && e.target.querySelector('[data-pgj-confirm]'));
    var msg = btn && btn.getAttribute && btn.getAttribute('data-pgj-confirm');
    if (msg && !window.confirm(msg)) e.preventDefault();
  });
})();

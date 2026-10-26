// Site header (wp/inc/site-chrome/site-chrome.js, shared by every page type): the mobile menu and
// the account menu (toggle; close on Esc, link click, outside click and resize), and on the
// homepage (.site-header--autohide) hidden until the visitor scrolls past the hero and back up.
(function () {
  var header = document.querySelector('.site-header');
  if (!header) return;
  function menu(btn, panel, labels) {
    if (!btn || !panel) return null;
    function setOpen(open, returnFocus) {
      btn.setAttribute('aria-expanded', open ? 'true' : 'false');
      if (labels) btn.setAttribute('aria-label', open ? labels[1] : labels[0]);
      panel.hidden = !open;
      if (open) { header.classList.remove('is-hidden'); var first = panel.querySelector('a'); if (first) first.focus(); }
      else if (returnFocus) { btn.focus(); }
    }
    btn.addEventListener('click', function () { setOpen(btn.getAttribute('aria-expanded') !== 'true'); });
    panel.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setOpen(false, true); });
    document.addEventListener('click', function (e) { if (!panel.hidden && !btn.contains(e.target) && !panel.contains(e.target)) setOpen(false); });
    return setOpen;
  }
  var mobile = menu(header.querySelector('.nav-toggle'), document.getElementById('mobile-menu'), ['Open menu', 'Close menu']);
  var account = menu(header.querySelector('.nav-account-toggle'), document.getElementById('account-menu'), null);
  var wide = window.matchMedia('(min-width: 1181px)');
  function onWidth() {
    if (wide.matches && mobile) mobile(false);
    if (!wide.matches && account) account(false);
  }
  if (wide.addEventListener) wide.addEventListener('change', onWidth); else if (wide.addListener) wide.addListener(onWidth);

  if (!header.classList.contains('site-header--autohide')) return;
  // Homepage: the hero already shows the menu links, so the header stays hidden until the visitor
  // has scrolled past the hero and scrolls back up (Martien, 26 Oct). Scrolling down or back into
  // the hero hides it again. An open menu or keyboard focus inside it always keeps it visible.
  var hero = document.querySelector('main > section.hero');
  var lastY = window.scrollY || 0, ticking = false;
  function keyboardFocusInside() {
    try { return !!header.querySelector(':focus-visible'); } catch (e) { return false; }
  }
  function pastHero(y) {
    return hero ? y > hero.getBoundingClientRect().bottom + y - header.offsetHeight : y > header.offsetHeight;
  }
  function update() {
    ticking = false;
    var y = window.scrollY || 0;
    if (header.querySelector('[aria-expanded="true"]') || keyboardFocusInside()) {
      header.classList.remove('is-hidden');
    } else if (!pastHero(y) || y > lastY + 2) {
      header.classList.add('is-hidden');
    } else if (y < lastY - 2) {
      header.classList.remove('is-hidden');
    }
    lastY = y;
  }
  window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
  header.addEventListener('focusin', function () { header.classList.remove('is-hidden'); });
})();

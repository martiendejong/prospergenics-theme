/* Prospergenics Platform: articles. Copy-link button and the clap button (card #6369).
   Clicks are counted locally and sent as one request after a short pause, so a burst of claps
   costs one request; the server answer (total, your claps) is what the page shows afterwards. */
(function () {
  'use strict';
  var cfg = window.pgArticles || {};

  // Copy link: shown only when the Clipboard API is available.
  document.querySelectorAll('.pga-copy').forEach(function (btn) {
    if (!navigator.clipboard || !window.isSecureContext) return;
    btn.hidden = false;
    var status = btn.parentNode.querySelector('.pga-copy-status');
    btn.addEventListener('click', function () {
      navigator.clipboard.writeText(btn.getAttribute('data-url')).then(function () {
        if (status) { status.textContent = 'Link copied'; setTimeout(function () { status.textContent = ''; }, 3000); }
      }, function () {
        if (status) status.textContent = 'Copying failed: copy the address from the address bar';
      });
    });
  });

  var box = document.querySelector('.pga-clap');
  var btn = box && box.querySelector('button.pga-clap-btn');
  if (!btn || !cfg.restUrl || !cfg.nonce) return;

  var max = parseInt(cfg.max, 10) || 50;
  var mine = parseInt(box.getAttribute('data-mine'), 10) || 0;
  var countEl = box.querySelector('.pga-clap-count');
  var help = box.querySelector('.pga-clap-help');
  var mineEl = box.querySelector('.pga-clap-mine');
  var live = box.querySelector('.pga-clap-status');
  var total = parseInt((countEl.textContent || '0').replace(/[^\d]/g, ''), 10) || 0;
  var pending = 0, timer = null, sending = false;

  function fmt(n) { return n.toLocaleString(); }
  function render() {
    countEl.textContent = fmt(total + pending);
    var given = mine + pending;
    mineEl.textContent = given >= max
      ? 'You gave the maximum of ' + max + ' claps. Thank you!'
      : 'You gave ' + given + ' of ' + max + ' claps.';
    btn.disabled = given >= max;
  }
  function flush() {
    timer = null;
    if (!pending || sending) return;
    var n = pending, settled = false;
    sending = true;
    fetch(cfg.restUrl + box.getAttribute('data-article') + '/claps', {
      method: 'POST',
      credentials: 'same-origin',
      keepalive: true,
      headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': cfg.nonce },
      body: JSON.stringify({ count: n })
    }).then(function (r) {
      return r.json().then(function (data) { return { ok: r.ok, data: data }; });
    }).then(function (res) {
      pending -= n; settled = true;
      if (!res.ok) throw new Error(res.data && res.data.message ? res.data.message : 'Clapping failed.');
      total = res.data.claps;
      mine = res.data.mine;
      help.classList.remove('is-error');
      render();
      live.textContent = 'Clapped. ' + fmt(total) + ' claps in total.';
    }).catch(function (err) {
      if (!settled) pending -= n; // the claps in this request did not count
      render();
      help.classList.add('is-error');
      mineEl.textContent = err.message || 'Clapping failed. Please try again.';
      live.textContent = mineEl.textContent;
    }).then(function () {
      sending = false;
      if (pending) timer = setTimeout(flush, 400);
    });
  }

  btn.addEventListener('click', function () {
    if (mine + pending >= max) return;
    pending++;
    render();
    btn.classList.remove('is-bumped');
    void btn.offsetWidth; // restart the animation
    btn.classList.add('is-bumped');
    clearTimeout(timer);
    timer = setTimeout(flush, 700);
  });
  window.addEventListener('pagehide', function () { if (pending && !sending) flush(); });
})();

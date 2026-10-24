#!/usr/bin/env python3
"""
Shared site header (with the mobile menu) and footer for the static design pages that are
not the homepage: about/index.html and the generated people/, experts/ and community/ pages.

The homepage (index.html) carries the same header and footer markup inline, and the
WordPress inner pages use wp/header.php + wp/footer.php; tests/test-2026-navigation.php
checks all of them offer the same menu.

    python site_chrome.py      # rewrites the marked blocks in about/index.html

people/build-profiles.py imports this module, so rebuilding the profiles picks it up too.
Everything here is self-contained (literal colours, rules scoped under .site-header /
.site-footer) so it renders the same on pages whose own stylesheets define different tokens
or reuse class names like .brand and .nav-links.
"""
import os
import re

HERE = os.path.dirname(os.path.abspath(__file__))

# (label, href). Hrefs starting with "/" are WordPress routes; "{root}" is replaced by the
# relative path back to the design root, so the static pages also work when opened locally.
NAV = [
    ("Courses", "/courses"),
    ("Members", "/members"),
    ("Tools", "/tools"),
    ("Services", "/services"),
    ("Partners", "/partners"),
    ("About", "{root}about/"),
]

CSS = """
  /* ── site header + mobile menu (site_chrome.py) ── */
  .site-header { position: relative; z-index: 30; max-width: 1500px; margin: 0 auto; display: flex; align-items: center; gap: 28px; padding: 18px clamp(20px, 5vw, 72px); }
  .site-header .brand { display: flex; align-items: center; gap: 10px; text-decoration: none; margin-right: auto; }
  .site-header .brand img { height: 40px; width: auto; display: block; }
  .site-header .brand-text { font-family: 'Sora', sans-serif; font-weight: 800; font-size: 20px; color: #0f3d28; letter-spacing: -.02em; }
  .site-header .nav-links { display: flex; align-items: center; gap: 30px; }
  .site-header .nav-links a { font-size: 15px; font-weight: 500; color: #0f3d28; text-decoration: none; opacity: .8; transition: opacity .2s; box-shadow: none; padding: 0; }
  .site-header .nav-links a:hover { opacity: 1; }
  .site-header .nav-links a[aria-current="page"] { opacity: 1; font-weight: 600; box-shadow: inset 0 -2px 0 #34c759; padding-bottom: 3px; }
  .site-header .nav-cta { font-family: 'Sora', sans-serif; font-weight: 600; font-size: 14px; padding: 10px 18px; border-radius: 999px; border: 1.5px solid #1a7a46; color: #0f3d28; background: transparent; text-decoration: none; white-space: nowrap; transition: all .2s; }
  .site-header .nav-cta:hover { background: #1a7a46; color: #fff; }
  .site-header .nav-toggle { display: none; width: 44px; height: 44px; border-radius: 12px; border: 1.5px solid rgba(15,61,40,.12); background: #fff; cursor: pointer; align-items: center; justify-content: center; -webkit-tap-highlight-color: transparent; }
  .site-header .nav-toggle[aria-expanded="true"] { background: #eaf5ee; border-color: #86e0a6; }
  .site-header .nav-toggle-bars, .site-header .nav-toggle-bars::before, .site-header .nav-toggle-bars::after { display: block; width: 20px; height: 2px; border-radius: 2px; background: #0f3d28; transition: transform .2s cubic-bezier(.22,.61,.36,1), opacity .2s; }
  .site-header .nav-toggle-bars { position: relative; }
  .site-header .nav-toggle-bars::before, .site-header .nav-toggle-bars::after { content: ""; position: absolute; left: 0; }
  .site-header .nav-toggle-bars::before { top: -6px; }
  .site-header .nav-toggle-bars::after { top: 6px; }
  .site-header .nav-toggle[aria-expanded="true"] .nav-toggle-bars { background: transparent; }
  .site-header .nav-toggle[aria-expanded="true"] .nav-toggle-bars::before { transform: translateY(6px) rotate(45deg); }
  .site-header .nav-toggle[aria-expanded="true"] .nav-toggle-bars::after { transform: translateY(-6px) rotate(-45deg); }
  .site-header .nav-panel { position: absolute; top: 100%; left: 12px; right: 12px; background: #fff; border: 1px solid rgba(15,61,40,.12); border-radius: 18px; box-shadow: 0 24px 60px -20px rgba(11,32,22,.35); padding: 10px; }
  .site-header .nav-panel nav { display: grid; }
  .site-header .nav-panel a { display: flex; align-items: center; min-height: 48px; padding: 0 14px; border-radius: 12px; font-size: 17px; font-weight: 500; color: #0f3d28; text-decoration: none; }
  .site-header .nav-panel a:hover, .site-header .nav-panel a:focus-visible { background: #eaf5ee; }
  .site-header .nav-panel .nav-panel-cta { margin-top: 6px; justify-content: center; background: #1a7a46; color: #fff; font-family: 'Sora', sans-serif; font-weight: 600; }
  .site-header .nav-panel .nav-panel-cta:hover { background: #0f3d28; }
  @media (max-width: 880px) {
    .site-header .nav-links, .site-header > .nav-cta { display: none; }
    .site-header .nav-toggle { display: inline-flex; }
    .site-header { padding: 12px 16px; }
    .site-header .brand img { height: 36px; }
  }
  @media (min-width: 881px) { .site-header .nav-panel { display: none !important; } }

  /* ── site footer (site_chrome.py) ── */
  .site-footer { background: #0f3d28; color: rgba(255,255,255,.78); margin-top: clamp(48px, 7vw, 96px); border: 0; }
  .site-footer .site-footer-inner { max-width: 1500px; margin: 0 auto; padding: clamp(40px, 5vw, 64px) clamp(20px, 5vw, 72px) 28px; display: grid; grid-template-columns: minmax(0, 1.4fr) repeat(3, minmax(0, 1fr)); gap: 32px 40px; }
  .site-footer .f-brand { display: grid; gap: 14px; align-content: start; }
  .site-footer .f-brand img { height: 44px; width: auto; background: #f4faf6; border-radius: 10px; padding: 4px; }
  .site-footer .f-brand p { font-size: 15px; line-height: 1.6; max-width: 34ch; color: rgba(255,255,255,.78); margin: 0; text-shadow: none; }
  .site-footer h2 { font-family: 'Sora', sans-serif !important; font-size: 13px; font-weight: 700; letter-spacing: .1em; text-transform: uppercase; color: #86e0a6; margin: 0 0 14px; text-shadow: none; }
  .site-footer ul { list-style: none; display: grid; gap: 10px; margin: 0; padding: 0; }
  .site-footer a { color: #fff; text-decoration: none; font-size: 15px; }
  .site-footer a:hover { text-decoration: underline; }
  .site-footer .site-footer-base { max-width: 1500px; margin: 0 auto; padding: 18px clamp(20px, 5vw, 72px) 28px; border-top: 1px solid rgba(255,255,255,.14); display: flex; flex-wrap: wrap; gap: 10px 28px; font-size: 13.5px; }
  .site-footer .site-footer-base nav { display: flex; flex-wrap: wrap; gap: 8px 20px; }
  .site-footer .site-footer-base a { font-size: 13.5px; color: rgba(255,255,255,.85); }
  @media (max-width: 880px) {
    .site-footer .site-footer-inner { grid-template-columns: repeat(2, minmax(0, 1fr)); }
    .site-footer .f-brand { grid-column: 1 / -1; }
  }
"""

JS = """<script>
    // Mobile menu: toggle, close on Esc / link click / outside click / resize to desktop.
    (function () {
      var btn = document.querySelector('.site-header .nav-toggle');
      var panel = document.getElementById('mobile-menu');
      if (!btn || !panel) return;
      function setOpen(open, returnFocus) {
        btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        btn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
        panel.hidden = !open;
        if (open) { var first = panel.querySelector('a'); if (first) first.focus(); }
        else if (returnFocus) { btn.focus(); }
      }
      btn.addEventListener('click', function () { setOpen(btn.getAttribute('aria-expanded') !== 'true'); });
      panel.addEventListener('click', function (e) { if (e.target.closest('a')) setOpen(false); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && !panel.hidden) setOpen(false, true); });
      document.addEventListener('click', function (e) { if (!panel.hidden && !e.target.closest('.site-header')) setOpen(false); });
      window.matchMedia('(min-width: 881px)').addEventListener('change', function (e) { if (e.matches) setOpen(false); });
    })();
    // Scroll top-fade: gradient mask — the top 15 % of the viewport is a fade zone.
    // Non-hero: viewport-relative formula. Hero children: scroll-relative (no mask at scrollY=0).
    (function () {
      if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;
      var items = Array.prototype.slice.call(document.querySelectorAll('main > section:not(.hero) > *, main > section.hero > .hero-bottom'));
      var heroItems = Array.prototype.slice.call(document.querySelectorAll('main > section.hero > .hero-top > .copy > .copy-inner'));
      if (!items.length && !heroItems.length) return;
      var ticking = false;
      function update() {
        var zone = window.innerHeight * 0.15;
        var scrollY = window.scrollY || 0;
        items.forEach(function (el) {
          var t = el.getBoundingClientRect().top;
          if (t >= zone) {
            el.style.maskImage = ''; el.style.webkitMaskImage = '';
          } else {
            var g = 'linear-gradient(to bottom,transparent ' + (-t) + 'px,#000 ' + (zone - t) + 'px)';
            el.style.maskImage = g; el.style.webkitMaskImage = g;
          }
        });
        heroItems.forEach(function (el) {
          if (scrollY <= 0) {
            el.style.maskImage = ''; el.style.webkitMaskImage = '';
          } else {
            var fd = Math.min(scrollY, zone);
            var g = 'linear-gradient(to bottom,transparent 0px,#000 ' + fd + 'px)';
            el.style.maskImage = g; el.style.webkitMaskImage = g;
          }
        });
        ticking = false;
      }
      window.addEventListener('scroll', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
      window.addEventListener('resize', function () { if (!ticking) { ticking = true; requestAnimationFrame(update); } }, { passive: true });
    })();
  </script>"""


def _links(root, current, indent):
    out = []
    for label, href in NAV:
        cur = ' aria-current="page"' if label == current else ""
        out.append(f'{indent}<a href="{href.format(root=root)}"{cur}>{label}</a>')
    return "\n".join(out)


def header(root, current=None):
    """root: relative path back to the design root ("../" or "../../")."""
    return f"""<header class="site-header">
    <a class="brand" href="{root}" aria-label="ProsperGenics home">
      <img src="{root}assets/logo.png" alt="" width="42" height="40" />
      <span class="brand-text">ProsperGenics</span>
    </a>
    <nav class="nav-links" aria-label="Main">
{_links(root, current, "      ")}
    </nav>
    <a class="nav-cta" href="{root}#value-test">Take the test</a>
    <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">
      <span class="nav-toggle-bars" aria-hidden="true"></span>
    </button>
    <div class="nav-panel" id="mobile-menu" hidden>
      <nav aria-label="Mobile">
{_links(root, current, "        ")}
        <a class="nav-panel-cta" href="{root}#value-test">Take the test</a>
      </nav>
    </div>
  </header>"""


def footer(root):
    return f"""<footer class="site-footer">
    <div class="site-footer-inner">
      <div class="f-brand">
        <img src="{root}assets/logo.png" alt="ProsperGenics" width="46" height="44" />
        <p>A community in Kenya and the Netherlands researching and practising how value is created.</p>
      </div>
      <div>
        <h2>Explore</h2>
        <ul>
          <li><a href="/courses">Courses</a></li>
          <li><a href="/tools">Tools</a></li>
          <li><a href="/services">Services</a></li>
          <li><a href="/partners">Partners</a></li>
        </ul>
      </div>
      <div>
        <h2>Community</h2>
        <ul>
          <li><a href="{root}about/">About us</a></li>
          <li><a href="{root}experts/">Our experts</a></li>
          <li><a href="{root}community/">Community stories</a></li>
          <li><a href="/blog/">Blog</a></li>
        </ul>
      </div>
      <div>
        <h2>Get in touch</h2>
        <ul>
          <li><a href="/contact/">Contact us</a></li>
          <li><a href="https://wa.me/254741619743" target="_blank" rel="noopener">WhatsApp +254 741 619743</a></li>
          <li><a href="{root}#value-test">Take the value test</a></li>
        </ul>
      </div>
    </div>
    <div class="site-footer-base">
      <span>© ProsperGenics</span>
      <nav aria-label="Legal">
        <a href="/privacy-policy/">Privacy policy</a>
        <a href="/cookie-policy/">Cookie policy</a>
      </nav>
    </div>
  </footer>"""


def _replace_block(text, name, content, css=False):
    start, end = (f"/* site-chrome:{name} */", f"/* /site-chrome:{name} */") if css else (f"<!-- site-chrome:{name} -->", f"<!-- /site-chrome:{name} -->")
    pattern = re.compile(re.escape(start) + r".*?" + re.escape(end), re.S)
    if not pattern.search(text):
        raise SystemExit(f"marker {start} not found")
    return pattern.sub(lambda _m: f"{start}\n{content}\n  {end}", text, count=1)


def update_about():
    path = os.path.join(HERE, "about", "index.html")
    with open(path, encoding="utf-8") as f:
        html = f.read()
    html = _replace_block(html, "css", CSS, css=True)
    html = _replace_block(html, "header", "  " + header("../", current="About"))
    html = _replace_block(html, "footer", "  " + footer("../"))
    html = _replace_block(html, "js", "  " + JS)
    with open(path, "w", encoding="utf-8") as f:
        f.write(html)
    print("updated about/index.html")


if __name__ == "__main__":
    update_about()

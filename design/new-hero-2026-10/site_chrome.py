#!/usr/bin/env python3
"""
The site header (with the mobile menu) and footer for the static design pages: index.html,
about/index.html and the generated people/, experts/ and community/ pages.

There is ONE definition of the menu and the chrome, in wp/inc/site-chrome/:
  nav.json         the main menu, the call to action, the footer columns and legal links
  site-chrome.css  header + footer styles (scoped under .site-header / .site-footer)
  site-chrome.js   mobile menu, account menu, homepage hide-on-scroll
WordPress renders the same files through wp/inc/site-chrome.php (inner pages, front-page.php,
page-about.php and the jengo-listings pages); this module renders them for the static pages.
tests/test-2026-navigation.php checks that every page type shows the same menu and markup.

    python site_chrome.py      # rewrites the marked blocks in index.html and about/index.html

people/build-profiles.py imports this module, so rebuilding the profiles picks it up too.
The static pages can't know who is logged in, so they show the visitor links (Log in, Join);
in WordPress (homepage, About, every inner page) the header comes from PHP and knows.
"""
import json
import os
import re
from urllib.parse import quote

HERE = os.path.dirname(os.path.abspath(__file__))
CHROME = os.path.join(HERE, "wp", "inc", "site-chrome")


def _read(name):
    with open(os.path.join(CHROME, name), encoding="utf-8") as f:
        return f.read()


SITE = json.loads(_read("nav.json"))
# (label, path) of the main menu, e.g. ("Courses", "/courses/")
NAV = [(item["label"], item["path"]) for item in SITE["main"]]

# Pages that live in this design folder: linked relative to it, so they also work when the
# static pages are opened locally. Every other path is a WordPress route.
_LOCAL = ("about/", "experts/", "community/")

CSS = "\n" + _read("site-chrome.css")

# The header's script for pages that have their own scroll effects (index.html).
MENU_JS = "<script>\n" + _read("site-chrome.js") + "  </script>"

# Static pages: the header's script plus the scroll top-fade.
JS = """<script>
""" + _read("site-chrome.js") + """
    // scroll-fade:start (kept identical in index.html and site_chrome.py; tests/test-2026-scroll-fade.php)
    // Scroll top-fade: content fades out only in a thin band at the top of the viewport, i.e. the part
    // the reader has already scrolled past. Mask only: no greyscale or scaling of whole sections, never
    // on forms, the chat or whatever has focus, and off under prefers-reduced-motion.
    (function () {
      var reduce = window.matchMedia('(prefers-reduced-motion: reduce)');
      var items = Array.prototype.slice.call(document.querySelectorAll(
        'main > section:not(.hero) > *, main > section.hero > .hero-bottom, main > section.hero > .hero-top > .copy > .copy-inner'));
      if (!items.length) return;
      var skip = 'form, .launcher, .co-embed-root, [data-no-fade]';
      var ticking = false;
      function clear(el) { el.style.maskImage = ''; el.style.webkitMaskImage = ''; }
      function update() {
        ticking = false;
        var band = Math.min(window.innerHeight * 0.12, 96);
        var off = reduce.matches || window.scrollY < 40;
        var focused = document.activeElement;
        items.forEach(function (el) {
          var t = el.getBoundingClientRect().top;
          if (off || t >= 0 || el.matches(skip) || el.querySelector(skip) ||
              (focused && focused !== document.body && el.contains(focused))) { clear(el); return; }
          var g = 'linear-gradient(to bottom,transparent ' + (-t) + 'px,#000 ' + (band - t) + 'px)';
          el.style.maskImage = g; el.style.webkitMaskImage = g;
        });
      }
      function request() { if (!ticking) { ticking = true; requestAnimationFrame(update); } }
      window.addEventListener('scroll', request, { passive: true });
      window.addEventListener('resize', request, { passive: true });
      document.addEventListener('focusin', request);
      if (reduce.addEventListener) reduce.addEventListener('change', request);
      update();
    })();
    // scroll-fade:end
  </script>"""


def _href(path, root):
    """Site-root path from nav.json -> href on a static page whose design root is `root`."""
    if path == "/":
        return root or "./"
    if path.startswith("/#"):
        return root + path[1:]
    if path.lstrip("/") in _LOCAL:
        return root + path.lstrip("/")
    return path


def _item_href(item, root):
    return item["url"] if "url" in item else _href(item["path"], root)


def _esc(text):
    return text.replace("&", "&amp;").replace('"', "&quot;").replace("<", "&lt;").replace(">", "&gt;")


def _links(root, current, indent):
    out = []
    for label, path in NAV:
        cur = ' aria-current="page"' if label == current else ""
        out.append(f'{indent}<a href="{_esc(_href(path, root))}"{cur}>{_esc(label)}</a>')
    return "\n".join(out)


def _account():
    """Visitor links (static pages can't see a login): Log in and Join (contact form)."""
    join = "/contact/?subject=" + quote(SITE["join_subject"])
    return [("login", "Log in", "/wp-login.php"), ("join", "Join", join)]


def header(root, current=None, autohide=False):
    """root: relative path back to the design root ("", "../" or "../../")."""
    cls = "site-header site-header--autohide" if autohide else "site-header"
    cta = SITE["cta"]
    cta_href = _esc(_href(cta["path"], root))
    desk = "\n".join(
        f'    <a class="{"nav-account-link nav-account-join" if key == "join" else "nav-account-link"}" href="{_esc(href)}">{label}</a>'
        for key, label, href in _account())
    panel = "\n".join(f'        <a href="{_esc(href)}">{label}</a>' for _key, label, href in _account())
    return f"""<header class="{cls}">
  <a class="brand" href="{_href("/", root)}" aria-label="ProsperGenics home">
    <img src="{root}assets/logo.png" alt="" width="42" height="40" />
    <span class="brand-text">ProsperGenics</span>
  </a>
  <nav class="nav-links" aria-label="Main">
{_links(root, current, "    ")}
  </nav>
  <div class="nav-account">
{desk}
  </div>
  <a class="nav-cta" href="{cta_href}">{_esc(cta["label"])}</a>
  <button class="nav-toggle" type="button" aria-expanded="false" aria-controls="mobile-menu" aria-label="Open menu">
    <span class="nav-toggle-bars" aria-hidden="true"></span>
  </button>
  <div class="nav-panel" id="mobile-menu" hidden>
    <nav aria-label="Mobile">
{_links(root, current, "      ")}
      <div class="nav-panel-account">
{panel}
      </div>
      <a class="nav-panel-cta" href="{cta_href}">{_esc(cta["label"])}</a>
    </nav>
  </div>
</header>"""


def footer(root):
    cols = []
    for col in SITE["footer"]:
        items = []
        for item in col["links"]:
            ext = ' target="_blank" rel="noopener"' if "url" in item else ""
            items.append(f'        <li><a href="{_esc(_item_href(item, root))}"{ext}>{_esc(item["label"])}</a></li>')
        cols.append(f"    <div>\n      <h2>{_esc(col['title'])}</h2>\n      <ul>\n" + "\n".join(items) + "\n      </ul>\n    </div>")
    legal = "\n".join(f'      <a href="{_esc(_item_href(item, root))}">{_esc(item["label"])}</a>' for item in SITE["legal"])
    return f"""<footer class="site-footer">
  <div class="site-footer-inner">
    <div class="f-brand">
      <img src="{root}assets/logo.png" alt="ProsperGenics" width="46" height="44" />
      <p>A community in Kenya and the Netherlands researching and practising how value is created.</p>
    </div>
""" + "\n".join(cols) + f"""
  </div>
  <div class="site-footer-base">
    <span>© ProsperGenics</span>
    <nav aria-label="Legal">
{legal}
    </nav>
  </div>
</footer>"""


def _replace_block(text, name, content, css=False):
    start, end = (f"/* site-chrome:{name} */", f"/* /site-chrome:{name} */") if css else (f"<!-- site-chrome:{name} -->", f"<!-- /site-chrome:{name} -->")
    pattern = re.compile(re.escape(start) + r".*?" + re.escape(end), re.S)
    if not pattern.search(text):
        raise SystemExit(f"marker {start} not found")
    return pattern.sub(lambda _m: f"{start}\n{content}\n  {end}", text, count=1)


def _update(rel, header_html, footer_html, js):
    path = os.path.join(HERE, rel)
    with open(path, encoding="utf-8") as f:
        html = f.read()
    html = _replace_block(html, "css", CSS, css=True)
    html = _replace_block(html, "header", header_html)
    html = _replace_block(html, "footer", footer_html)
    html = _replace_block(html, "js", "  " + js)
    with open(path, "w", encoding="utf-8") as f:
        f.write(html)
    print(f"updated {rel}")


def update_about():
    _update(os.path.join("about", "index.html"), header("../", current="About"), footer("../"), JS)


def update_index():
    _update("index.html", header("", autohide=True), footer(""), MENU_JS)


if __name__ == "__main__":
    update_index()
    update_about()

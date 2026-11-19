"""
The homepage "Our services" cards (card #7219), from the one copy of the content and styles:

  wp/inc/services/services.json   the four services: name, promise, three points, example image + label
  wp/inc/services/services.css    the card styles

site_chrome.py calls update(html) for index.html: it rewrites the blocks between
<!-- services:cards --> and <!-- /services:cards --> (markup) and /* services:css */ and
/* /services:css */ (styles). WordPress renders the same cards on /services/ from the same files
(wp/inc/services.php, prospergenics_2026_services_cards()); tests/test-2026-services.php checks
that this markup and the PHP markup are byte-identical for the homepage's URLs.
"""
import json
import os
import re
from urllib.parse import quote

HERE = os.path.dirname(os.path.abspath(__file__))
SRC = os.path.join(HERE, "wp", "inc", "services")


def _read(name):
    with open(os.path.join(SRC, name), encoding="utf-8") as f:
        return f.read()


DATA = json.loads(_read("services.json"))
CSS = "\n" + _read("services.css")


def _esc(text):
    """Same output as WordPress's esc_html()/esc_attr() for plain text."""
    return (text.replace("&", "&amp;").replace("<", "&lt;").replace(">", "&gt;")
            .replace('"', "&quot;").replace("'", "&#039;"))


def card(s, home, assets, heading="h3"):
    """One card. home: site root without a trailing slash ("" on the static homepage);
    assets: the assets URL without a trailing slash. Mirrors prospergenics_2026_services_card()."""
    img = s.get("image")
    if img:
        media = ('        <figure class="svc-media"><img src="%s/%s" width="%d" height="%d" alt="" loading="lazy" decoding="async" style="object-position:%s" />'
                 '<figcaption class="svc-ex">%s</figcaption></figure>\n') % (
            _esc(assets), _esc(img["src"]), int(img["width"]), int(img["height"]), _esc(img["position"]), _esc(img["label"]))
    else:
        media = '        <div class="svc-media svc-media--brand" aria-hidden="true">%s</div>\n' % s["panel_svg"]
    points = "".join("<li>%s</li>" % _esc(p) for p in s["points"])
    return ('      <article class="svc-card">\n' + media
            + '        <div class="svc-body">\n'
            + '          <%s class="svc-name">%s</%s>\n' % (heading, _esc(s["name"]), heading)
            + '          <p class="svc-promise">%s</p>\n' % _esc(s["promise"])
            + '          <ul class="svc-points">%s</ul>\n' % points
            + '          <a class="svc-more" href="%s/services/%s/">%s <span aria-hidden="true">→</span></a>\n' % (_esc(home), _esc(s["slug"]), _esc(s["link"]))
            + '        </div>\n'
            + '      </article>')


def cards(home, assets, contact_url, heading="h3"):
    """The grid of all four cards and the "Discuss your project" row.
    Mirrors prospergenics_2026_services_cards()."""
    cta = DATA["cta"]
    return ('    <div class="svc-grid">\n'
            + "\n".join(card(s, home, assets, heading) for s in DATA["services"])
            + '\n    </div>\n'
            + '    <div class="svc-cta"><a class="svc-cta-btn" href="%s">%s <span aria-hidden="true">→</span></a><p class="svc-cta-note">%s</p></div>'
            % (_esc(contact_url), _esc(cta["label"]), _esc(cta["note"])))


def contact_url(subject, home=""):
    """/contact/?subject=..., encoded as prospergenics_2026_contact_url() does (rawurlencode)."""
    return "%s/contact/?subject=%s" % (home, quote(subject, safe="-_.~"))


def _replace(text, start, end, content, indent):
    pattern = re.compile(re.escape(start) + r".*?" + re.escape(end), re.S)
    if not pattern.search(text):
        raise SystemExit(f"marker {start} not found")
    return pattern.sub(lambda _m: f"{start}\n{content}\n{indent}{end}", text, count=1)


def update(html):
    """index.html with the services styles and cards rewritten from services.json/.css."""
    html = _replace(html, "/* services:css */", "/* /services:css */", CSS, "  ")
    return _replace(html, "<!-- services:cards -->", "<!-- /services:cards -->",
                    cards("", "assets", contact_url(DATA["cta"]["subject"])), "    ")

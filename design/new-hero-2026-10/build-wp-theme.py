#!/usr/bin/env python3
"""
Generate the "Prospergenics 2026" WordPress theme from the static design in THIS folder.

    python build-wp-theme.py

Output: ../../wp-theme/prospergenics-2026/
  front-page.php   <- index.html        (homepage, markup/CSS/JS preserved 1:1)
  page-about.php   <- about/index.html  (used automatically for the page with slug "about")
  *.php            <- wp/*.php          (hand-written WP templates + functions.php, copied verbatim)
  assets/          <- assets/
  style.css                            (theme header; everything else is in wp/)

Personal profile pages (people/<slug>/index.html) and the two list pages
(experts/, community/) are static and deploy straight into public_html/ - see
deploy-wp-theme-vimexx.py; they are not theme templates.

The conversion only rewrites asset URLs to the theme directory, inserts the
mandatory wp_head()/wp_footer() hooks and swaps the static site header/footer blocks for
the PHP-rendered ones (inc/site-chrome.php, same nav.json/CSS/JS). Everything else stays byte-identical so
the interactive front-end (slider, parallax, scroll effects) keeps working as-is.
Re-run after every design change, then deploy with deploy-wp-theme-to-test.py.
"""
import os, re, shutil

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.normpath(os.path.join(HERE, "..", "..", "wp-theme", "prospergenics-2026"))
TPL = "<?php echo esc_url( get_template_directory_uri() ); ?>"
HOME = "<?php echo esc_url( home_url( '/' ) ); ?>"


def convert(html, asset_prefix):
    # WP hooks, with the original markup untouched otherwise
    html = html.replace('<html lang="en">', '<html <?php language_attributes(); ?>>', 1)
    html = html.replace('<html lang="en" class="no-js">',
                        '<html class="no-js" <?php language_attributes(); ?>>', 1)
    html = html.replace("</head>", "<?php wp_head(); ?>\n</head>", 1)
    # wp_body_open right after <body>, as in header.php (plugins hook in there; ARC-19)
    # (the tag on its own line: "<body>" also appears inside a CSS comment)
    html, found = re.subn(r"^<body>$", "<body>\n<?php wp_body_open(); ?>", html, count=1, flags=re.M)
    if not found:
        raise SystemExit("<body> line not found")
    html = html.replace("</body>", "<?php wp_footer(); ?>\n</body>", 1)
    # asset URLs -> theme directory (src/href/CSS url()/JS string literals)
    html = html.replace("https://test.prospergenics.com/assets/", f"{TPL}/assets/")
    html = html.replace(f"'{asset_prefix}assets/", f"'{TPL}/assets/")
    html = html.replace(f'"{asset_prefix}assets/', f'"{TPL}/assets/')
    html = html.replace(f"url('{asset_prefix}assets/", f"url('{TPL}/assets/")
    return html


def php_chrome(html, header_args):
    """The static header/footer blocks (site_chrome.py) -> the same chrome rendered by
    inc/site-chrome.php, so WordPress pages know who is logged in (account links, admin bar)."""
    def swap(text, name, php):
        pattern = re.compile(r"<!-- site-chrome:%s -->.*?<!-- /site-chrome:%s -->" % (name, name), re.S)
        if not pattern.search(text):
            raise SystemExit(f"site-chrome:{name} block not found")
        return pattern.sub(lambda _m: f"<!-- site-chrome:{name} (inc/site-chrome.php) -->\n  {php}", text, count=1)
    # after the header (so the skip link stays the first Tab stop): member notices such as the
    # email-verification banner, the same hook header.php fires (ARC-19)
    html = swap(html, "header", f"<?php echo prospergenics_2026_site_header( {header_args} ); // phpcs:ignore WordPress.Security.EscapeOutput ?>\n  <?php do_action( 'prospergenics_2026_after_site_header' ); ?>")
    return swap(html, "footer", "<?php echo prospergenics_2026_site_footer(); // phpcs:ignore WordPress.Security.EscapeOutput ?>")


def build():
    if os.path.isdir(OUT):
        shutil.rmtree(OUT)
    os.makedirs(OUT)

    # homepage
    with open(os.path.join(HERE, "index.html"), encoding="utf-8") as f:
        home = php_chrome(convert(f.read(), ""), "array( 'autohide' => true )")
    home = home.replace('href="about/"', f'href="{HOME}about/"')
    home = home.replace('href="people/', f'href="{HOME}people/')
    home = home.replace('href="experts/"', f'href="{HOME}experts/"')
    home = home.replace('href="community/"', f'href="{HOME}community/"')
    with open(os.path.join(OUT, "front-page.php"), "w", encoding="utf-8") as f:
        f.write("<?php /* Front page - generated from design/new-hero-2026-10/index.html by build-wp-theme.py; edit the source, not this file. */ ?>\n" + home)

    # about page (template auto-applies to the page with slug "about")
    with open(os.path.join(HERE, "about", "index.html"), encoding="utf-8") as f:
        about = php_chrome(convert(f.read(), "../"), "array( 'current' => 'About' )")
    about = about.replace('href="../"', f'href="{HOME}"')
    about = about.replace('href="./"', f'href="{HOME}about/"')
    about = about.replace('href="../#', f'href="{HOME}#')
    with open(os.path.join(OUT, "page-about.php"), "w", encoding="utf-8") as f:
        f.write("<?php /* About page - generated from design/new-hero-2026-10/about/index.html by build-wp-theme.py; edit the source, not this file. */ ?>\n" + about)

    shutil.copytree(os.path.join(HERE, "assets"), os.path.join(OUT, "assets"))

    # hand-written WP templates (e.g. single.php for posts/courses): copied verbatim
    wp_src = os.path.join(HERE, "wp")
    if os.path.isdir(wp_src):
        for fn in os.listdir(wp_src):
            if fn.endswith(".php"):
                shutil.copy(os.path.join(wp_src, fn), os.path.join(OUT, fn))
        # theme modules required by functions.php (security, SEO, contact form, ...)
        if os.path.isdir(os.path.join(wp_src, "inc")):
            shutil.copytree(os.path.join(wp_src, "inc"), os.path.join(OUT, "inc"))

    with open(os.path.join(OUT, "style.css"), "w", encoding="utf-8") as f:
        f.write("""/*
Theme Name: Prospergenics 2026
Theme URI: https://prospergenics.com
Author: Prospergenics Team
Description: 2026 redesign - interactive hero with showcase slider, parallax and scroll effects, plus the About narrative page. All page CSS/JS lives inline in the templates (generated from design/new-hero-2026-10 in the prospergenics-theme repo).
Version: 1.0.0
Requires PHP: 7.4
License: GNU General Public License v2 or later
Text Domain: prospergenics-2026
*/
""")

    # functions.php, index.php, page.php, 404.php, header.php, footer.php, searchform.php
    # and single.php all live in wp/ and are copied verbatim above.

    n = sum(len(fs) for _, _, fs in os.walk(OUT))
    print(f"built {OUT} ({n} files)")


if __name__ == "__main__":
    build()

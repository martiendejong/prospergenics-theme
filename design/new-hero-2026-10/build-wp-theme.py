#!/usr/bin/env python3
"""
Generate the "Prospergenics 2026" WordPress theme from the static design in THIS folder.

    python build-wp-theme.py

Output: ../../wp-theme/prospergenics-2026/
  front-page.php   <- index.html        (homepage, markup/CSS/JS preserved 1:1)
  page-about.php   <- about/index.html  (used automatically for the page with slug "about")
  assets/          <- assets/
  style.css, functions.php, index.php   (minimal WP plumbing)

The conversion only rewrites asset URLs to the theme directory and inserts the
mandatory wp_head()/wp_footer() hooks. Everything else stays byte-identical so
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
    html = html.replace('<html lang="en">', '<html lang="en" <?php language_attributes(); ?>>', 1)
    html = html.replace('<html lang="en" class="no-js">',
                        '<html class="no-js" <?php language_attributes(); ?>>', 1)
    html = html.replace("</head>", "<?php wp_head(); ?>\n</head>", 1)
    html = html.replace("</body>", "<?php wp_footer(); ?>\n</body>", 1)
    # asset URLs -> theme directory (src/href/CSS url()/JS string literals)
    html = html.replace("https://test.prospergenics.com/assets/", f"{TPL}/assets/")
    html = html.replace(f"'{asset_prefix}assets/", f"'{TPL}/assets/")
    html = html.replace(f'"{asset_prefix}assets/', f'"{TPL}/assets/')
    html = html.replace(f"url('{asset_prefix}assets/", f"url('{TPL}/assets/")
    return html


def build():
    if os.path.isdir(OUT):
        shutil.rmtree(OUT)
    os.makedirs(OUT)

    # homepage
    with open(os.path.join(HERE, "index.html"), encoding="utf-8") as f:
        home = convert(f.read(), "")
    home = home.replace('href="about/"', f'href="{HOME}about/"')
    with open(os.path.join(OUT, "front-page.php"), "w", encoding="utf-8") as f:
        f.write("<?php /* Front page - generated from design/new-hero-2026-10/index.html by build-wp-theme.py; edit the source, not this file. */ ?>\n" + home)

    # about page (template auto-applies to the page with slug "about")
    with open(os.path.join(HERE, "about", "index.html"), encoding="utf-8") as f:
        about = convert(f.read(), "../")
    about = about.replace('href="../"', f'href="{HOME}"')
    about = about.replace('href="./"', f'href="{HOME}about/"')
    about = about.replace('href="../#', f'href="{HOME}#')
    with open(os.path.join(OUT, "page-about.php"), "w", encoding="utf-8") as f:
        f.write("<?php /* About page - generated from design/new-hero-2026-10/about/index.html by build-wp-theme.py; edit the source, not this file. */ ?>\n" + about)

    shutil.copytree(os.path.join(HERE, "assets"), os.path.join(OUT, "assets"))

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

    with open(os.path.join(OUT, "functions.php"), "w", encoding="utf-8") as f:
        f.write("""<?php
/**
 * Prospergenics 2026 - intentionally minimal.
 * Templates are self-contained (inline CSS/JS) to preserve the interactive
 * front-end exactly as designed; nothing is enqueued here.
 */
add_action( 'after_setup_theme', function () {
    add_theme_support( 'automatic-feed-links' );
} );
// The templates carry their own <title>; keep WP/plugins from printing a second one.
remove_action( 'wp_head', '_wp_render_title_tag', 1 );
""")

    with open(os.path.join(OUT, "index.php"), "w", encoding="utf-8") as f:
        f.write("""<?php
/* Fallback for any non-page request: send visitors to the front page. */
if ( ! is_front_page() ) {
    wp_safe_redirect( home_url( '/' ) );
    exit;
}
get_template_part( 'front-page' );
""")

    n = sum(len(fs) for _, _, fs in os.walk(OUT))
    print(f"built {OUT} ({n} files)")


if __name__ == "__main__":
    build()

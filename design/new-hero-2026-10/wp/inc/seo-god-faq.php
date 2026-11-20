<?php
/**
 * The SEO God plugin's FAQ block in the theme's look (card #8077).
 *
 * The plugin (not in this repo) prints <div class="seo-god-faq-section"> (heading, categories,
 * details/summary questions, disclaimer) in wp_footer, plus a script that moves it in front of the
 * first <footer>; on the homepage it lands between <main> and footer.site-footer, as a direct child
 * of <body>. Its own stylesheet, faq-display.css (handle 'seo-god-faq-display'), gives it no width,
 * side padding or theme type, so on prospergenics.com it ran edge to edge.
 *
 * The theme prints inc/site-chrome/seo-god-faq.css right after that stylesheet, wherever and
 * whenever the plugin's stylesheet goes out (head or footer; homepage, About, inner pages,
 * jengo-listings pages), so a page without the FAQ gets nothing extra. CSS only: the plugin's
 * markup and its FAQPage structured data are left exactly as the plugin makes them.
 */

defined( 'ABSPATH' ) || exit;

/** The plugin's handle for faq-display.css (the <link> has id="seo-god-faq-display-css"). */
const PROSPERGENICS_2026_SEO_GOD_FAQ_HANDLE = 'seo-god-faq-display';

/**
 * Whether a stylesheet is the plugin's faq-display.css (pure): its handle, or, should the plugin
 * rename the handle, its file in the plugin's folder (.css or .min.css, any query string).
 */
function prospergenics_2026_seo_god_faq_is_stylesheet( $handle, $href ) {
    if ( PROSPERGENICS_2026_SEO_GOD_FAQ_HANDLE === $handle ) {
        return true;
    }
    $path = (string) parse_url( (string) $href, PHP_URL_PATH );
    return 1 === preg_match( '#/plugins/seo-god/(?:[a-z0-9_-]+/)*faq-display(?:\.min)?\.css$#i', $path );
}

/** The theme's styles for the block (inc/site-chrome/seo-god-faq.css). */
function prospergenics_2026_seo_god_faq_css() {
    return (string) file_get_contents( __DIR__ . '/site-chrome/seo-god-faq.css' );
}

/**
 * The plugin's stylesheet tag followed by the theme's styles (pure). Nothing is added for empty
 * styles or to a tag that already carries them.
 */
function prospergenics_2026_seo_god_faq_style_tag( $tag, $css ) {
    if ( '' === trim( (string) $css ) || false !== strpos( (string) $tag, 'id="prospergenics-seo-god-faq-css"' ) ) {
        return $tag;
    }
    return $tag . '<style id="prospergenics-seo-god-faq-css">' . "\n" . $css . "</style>\n";
}

add_filter( 'style_loader_tag', function ( $tag, $handle, $href ) {
    static $printed = false;
    if ( $printed || is_admin() || ! prospergenics_2026_seo_god_faq_is_stylesheet( $handle, $href ) ) {
        return $tag;
    }
    $printed = true;
    return prospergenics_2026_seo_god_faq_style_tag( $tag, prospergenics_2026_seo_god_faq_css() );
}, 10, 3 );

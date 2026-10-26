<?php
/**
 * Articles: search and sharing tags (card #6365, "SEO meta, og:* auto-filled from content").
 *
 * inc/seo.php (shared) prints description/Open Graph/Twitter tags for every singular page when
 * no SEO plugin is active, but always with og:type "website" for anything that is not a blog
 * post, and with no article:* tags. For a single article this module takes over instead of
 * adding a second set: it tells inc/seo.php an SEO layer is handling the page (through that
 * file's own 'prospergenics_2026_seo_plugin_active' filter) and prints one complete set:
 *
 *   description / og:description  the excerpt, else the start of the content (155 chars)
 *   og:title                      the article title
 *   og:image                      the cover image, else the first image in the content, else
 *                                 the site's default sharing image
 *   og:type article, article:published_time, article:modified_time, article:section (category),
 *   article:tag (tags), twitter:* and an Article JSON-LD block.
 *
 * The article list pages (/articles/, categories, tags, series) get a description and sharing
 * tags too; inc/seo.php prints nothing for non-singular pages, so nothing is doubled there. They
 * also get a canonical (WordPress prints one for singular pages only): the unfiltered listing,
 * page number kept, so /articles/?sort=trending points at /articles/; sorted views are noindex
 * (audit ARC-22).
 * When a real SEO plugin (Yoast, Rank Math, ...) is active this module prints nothing at all.
 */

defined( 'ABSPATH' ) || exit;

function prospergenics_articles_seo_takeover( $active ) {
    return $active || is_singular( 'pg_article' );
}
add_filter( 'prospergenics_2026_seo_plugin_active', 'prospergenics_articles_seo_takeover', 99 );

/** Whether a real SEO plugin is active (the shared check, without this module's takeover). */
function prospergenics_articles_other_seo_active() {
    if ( ! function_exists( 'prospergenics_2026_seo_plugin_active' ) ) {
        return false;
    }
    remove_filter( 'prospergenics_2026_seo_plugin_active', 'prospergenics_articles_seo_takeover', 99 );
    $active = prospergenics_2026_seo_plugin_active();
    add_filter( 'prospergenics_2026_seo_plugin_active', 'prospergenics_articles_seo_takeover', 99 );
    return $active;
}

/** Plain-text summary, using the shared helper when it is loaded. */
function prospergenics_articles_summary( $text, $max = 155 ) {
    if ( function_exists( 'prospergenics_2026_summarize' ) ) {
        return prospergenics_2026_summarize( $text, $max );
    }
    $text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $text ) ) );
    return mb_strlen( $text ) > $max ? rtrim( mb_substr( $text, 0, $max - 1 ) ) . '…' : $text;
}

/** The first image URL in some HTML, made absolute against $home; '' when there is none. */
function prospergenics_articles_first_image( $html, $home ) {
    if ( ! preg_match( '/<img\b[^>]*?\bsrc\s*=\s*["\']([^"\']+)["\']/i', (string) $html, $m ) ) {
        return '';
    }
    $src = html_entity_decode( $m[1], ENT_QUOTES, 'UTF-8' );
    if ( preg_match( '#^https?://#i', $src ) ) {
        return $src;
    }
    if ( 0 === strpos( $src, '/' ) && 0 !== strpos( $src, '//' ) ) {
        return rtrim( $home, '/' ) . $src;
    }
    return '';
}

/** The sharing image for an article. */
function prospergenics_articles_share_image( $post ) {
    if ( has_post_thumbnail( $post ) ) {
        return (string) get_the_post_thumbnail_url( $post, 'large' );
    }
    $img = prospergenics_articles_first_image( $post->post_content, home_url( '/' ) );
    return $img ? $img : get_template_directory_uri() . '/assets/og-image.jpg';
}

/** Collected tag values for the current article page, or an empty array. */
function prospergenics_articles_current_meta() {
    if ( is_singular( 'pg_article' ) ) {
        $post = get_queried_object();
        $tags = get_the_terms( $post, 'pg_article_tag' );
        $cat  = prospergenics_articles_primary_category( $post );
        return array(
            'title'       => get_the_title( $post ),
            'description' => prospergenics_articles_summary( has_excerpt( $post ) ? get_the_excerpt( $post ) : $post->post_content ),
            'image'       => prospergenics_articles_share_image( $post ),
            'url'         => get_permalink( $post ),
            'type'        => 'article',
            'published'   => get_post_time( 'c', true, $post ),
            'modified'    => get_post_modified_time( 'c', true, $post ),
            'section'     => $cat ? $cat->name : '',
            'tags'        => ( $tags && ! is_wp_error( $tags ) ) ? wp_list_pluck( $tags, 'name' ) : array(),
        );
    }
    if ( is_post_type_archive( 'pg_article' ) || is_tax( array( 'pg_article_category', 'pg_article_tag', 'pg_series' ) ) ) {
        $term = is_tax() ? get_queried_object() : null;
        if ( $term ) {
            $desc = trim( wp_strip_all_tags( term_description( $term ) ) );
            $desc = $desc ? $desc : sprintf( 'Articles in “%s” from the ProsperGenics community.', $term->name );
            $url  = get_term_link( $term );
            $name = $term->name;
        } else {
            $desc = 'Articles by ProsperGenics members on creating value: lessons, research and stories from Kenya and the Netherlands.';
            $url  = get_post_type_archive_link( 'pg_article' );
            $name = 'Articles';
        }
        return array(
            'title'       => $name,
            'description' => prospergenics_articles_summary( $desc ),
            'image'       => get_template_directory_uri() . '/assets/og-image.jpg',
            'url'         => is_wp_error( $url ) ? home_url( '/articles/' ) : $url,
            'type'        => 'website',
        );
    }
    return array();
}

/**
 * The meta tags for a set of values, as [attribute, name, content] rows.
 *
 * @return array<int,array{0:string,1:string,2:string}>
 */
function prospergenics_articles_meta_tags( array $m ) {
    $title = $m['title'] . ' — ProsperGenics';
    $rows  = array(
        array( 'name', 'description', $m['description'] ),
        array( 'property', 'og:type', $m['type'] ),
        array( 'property', 'og:site_name', 'ProsperGenics' ),
        array( 'property', 'og:title', $title ),
        array( 'property', 'og:description', $m['description'] ),
        array( 'property', 'og:url', $m['url'] ),
        array( 'property', 'og:image', $m['image'] ),
    );
    if ( 'article' === $m['type'] ) {
        $rows[] = array( 'property', 'article:published_time', $m['published'] );
        $rows[] = array( 'property', 'article:modified_time', $m['modified'] );
        if ( ! empty( $m['section'] ) ) {
            $rows[] = array( 'property', 'article:section', $m['section'] );
        }
        foreach ( (array) $m['tags'] as $tag ) {
            $rows[] = array( 'property', 'article:tag', $tag );
        }
    }
    $rows[] = array( 'name', 'twitter:card', 'summary_large_image' );
    $rows[] = array( 'name', 'twitter:title', $title );
    $rows[] = array( 'name', 'twitter:description', $m['description'] );
    $rows[] = array( 'name', 'twitter:image', $m['image'] );
    return array_values( array_filter( $rows, function ( $r ) { return '' !== (string) $r[2]; } ) );
}

/** Article structured data (schema.org) for a single article. */
function prospergenics_articles_schema( $post, array $m ) {
    $author = get_userdata( $post->post_author );
    $words  = prospergenics_articles_word_count( $post->post_content );
    $data   = array(
        '@context'         => 'https://schema.org',
        '@type'            => 'Article',
        'headline'         => wp_strip_all_tags( get_the_title( $post ) ),
        'description'      => $m['description'],
        'image'            => array( $m['image'] ),
        'datePublished'    => $m['published'],
        'dateModified'     => $m['modified'],
        'mainEntityOfPage' => $m['url'],
        'wordCount'        => $words,
        'timeRequired'     => 'PT' . prospergenics_articles_minutes_for( $words ) . 'M',
        'publisher'        => array(
            '@type' => 'Organization',
            'name'  => 'ProsperGenics',
            'url'   => home_url( '/' ),
            'logo'  => get_template_directory_uri() . '/assets/logo.png',
        ),
    );
    if ( $author ) {
        $data['author'] = array( '@type' => 'Person', 'name' => $author->display_name );
        $url = prospergenics_articles_author_url( $author->ID );
        if ( $url ) {
            $data['author']['url'] = $url;
        }
    }
    if ( ! empty( $m['section'] ) ) {
        $data['articleSection'] = $m['section'];
    }
    if ( ! empty( $m['tags'] ) ) {
        $data['keywords'] = implode( ', ', $m['tags'] );
    }
    return $data;
}

add_action( 'wp_head', function () {
    if ( prospergenics_articles_other_seo_active() ) {
        return;
    }
    $m = prospergenics_articles_current_meta();
    if ( ! $m || '' === $m['description'] ) {
        return;
    }
    echo "\n";
    foreach ( prospergenics_articles_meta_tags( $m ) as $t ) {
        printf( '<meta %s="%s" content="%s" />' . "\n", $t[0], esc_attr( $t[1] ), esc_attr( $t[2] ) );
    }
    if ( 'article' === $m['type'] ) {
        echo '<script type="application/ld+json" class="prospergenics-articles-schema">'
            . wp_json_encode( prospergenics_articles_schema( get_queried_object(), $m ), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG )
            . '</script>' . "\n";
    }
}, 3 );

/** Whether this is an article list page (/articles/, a category, tag or series). */
function prospergenics_articles_is_listing() {
    return is_post_type_archive( 'pg_article' ) || is_tax( array( 'pg_article_category', 'pg_article_tag', 'pg_series' ) );
}

/** Canonical URL of the current article list page: no sort or other query string, page kept. */
function prospergenics_articles_listing_canonical() {
    if ( ! prospergenics_articles_is_listing() ) {
        return '';
    }
    $base = is_tax() ? get_term_link( get_queried_object() ) : get_post_type_archive_link( 'pg_article' );
    if ( ! $base || is_wp_error( $base ) ) {
        return '';
    }
    $paged = max( 1, (int) get_query_var( 'paged' ) );
    return function_exists( 'prospergenics_2026_paged_url' ) ? prospergenics_2026_paged_url( $base, $paged ) : $base;
}

add_action( 'wp_head', function () {
    if ( prospergenics_articles_other_seo_active() ) {
        return;
    }
    $canonical = prospergenics_articles_listing_canonical();
    if ( '' !== $canonical ) {
        echo '<link rel="canonical" href="' . esc_url( $canonical ) . '" />' . "\n";
    }
}, 3 );

// A sorted view lists the same articles as the canonical listing: keep it out of the index.
add_filter( 'wp_robots', function ( $robots ) {
    if ( prospergenics_articles_is_listing() && 'newest' !== prospergenics_articles_sort() ) {
        $robots['noindex'] = true;
    }
    return $robots;
}, 20 );

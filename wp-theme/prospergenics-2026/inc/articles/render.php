<?php
/**
 * Articles: markup shared by the article templates. Every value is escaped here.
 */

defined( 'ABSPATH' ) || exit;

/** Inline icons (stroke icons drawn with currentColor; decorative, hidden from screen readers). */
function prospergenics_articles_icon( $name ) {
    $paths = array(
        'clap'     => '<path d="M18 11V6a2 2 0 0 0-4 0"/><path d="M14 10V4a2 2 0 0 0-4 0v2"/><path d="M10 10.5V6a2 2 0 0 0-4 0v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-6-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/>',
        'clock'    => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'linkedin' => '<rect x="3" y="3" width="18" height="18" rx="3"/><path d="M8 10v7M8 7v.01M12 17v-4a2 2 0 0 1 4 0v4M12 10v7"/>',
        'x'        => '<path d="M4 4l16 16M20 4L4 20"/>',
        'link'     => '<path d="M10 14a4 4 0 0 0 5.66 0l3-3a4 4 0 0 0-5.66-5.66l-1 1"/><path d="M14 10a4 4 0 0 0-5.66 0l-3 3a4 4 0 0 0 5.66 5.66l1-1"/>',
        'prev'     => '<path d="M15 18l-6-6 6-6"/>',
        'next'     => '<path d="M9 18l6-6-6-6"/>',
    );
    if ( ! isset( $paths[ $name ] ) ) {
        return '';
    }
    return '<svg class="pga-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $paths[ $name ] . '</svg>';
}

/** "12 claps" for screen readers, "12" visible. */
function prospergenics_articles_claps_badge( $post_id ) {
    $n = prospergenics_articles_claps( $post_id );
    return '<span class="pga-claps">' . prospergenics_articles_icon( 'clap' )
        . '<span aria-hidden="true">' . esc_html( number_format_i18n( $n ) ) . '</span>'
        . '<span class="screen-reader-text">' . esc_html( sprintf( _n( '%s clap', '%s claps', $n ), number_format_i18n( $n ) ) ) . '</span></span>';
}

/** The author's name, linked to their profile when the profiles track provides one. */
function prospergenics_articles_author_name_html( $user_id ) {
    $user = get_userdata( $user_id );
    $name = $user ? $user->display_name : 'ProsperGenics';
    $url  = $user ? prospergenics_articles_author_url( $user_id ) : '';
    return $url
        ? '<a class="pga-author-name" href="' . esc_url( $url ) . '">' . esc_html( $name ) . '</a>'
        : '<span class="pga-author-name">' . esc_html( $name ) . '</span>';
}

/**
 * An article card for the listings and "related articles".
 *
 * @param WP_Post $post
 * @param string  $heading h2 on listings, h3 under a section heading.
 */
function prospergenics_articles_card( $post, $heading = 'h2' ) {
    $post    = get_post( $post );
    $heading = in_array( $heading, array( 'h2', 'h3' ), true ) ? $heading : 'h2';
    $cat     = prospergenics_articles_primary_category( $post );
    $user    = get_userdata( $post->post_author );
    ob_start();
    ?>
    <article class="pga-card">
      <div class="pga-card-media">
        <?php if ( has_post_thumbnail( $post ) ) : ?>
          <?php echo get_the_post_thumbnail( $post, 'medium_large', array( 'alt' => '', 'loading' => 'lazy', 'sizes' => '(max-width: 700px) 100vw, 400px' ) ); ?>
        <?php else : ?>
          <span class="pga-card-placeholder" aria-hidden="true"><?php echo esc_html( mb_strtoupper( mb_substr( wp_strip_all_tags( get_the_title( $post ) ), 0, 1 ) ) ); ?></span>
        <?php endif; ?>
      </div>
      <div class="pga-card-body">
        <?php if ( $cat ) : ?>
          <span class="pga-card-cat"><?php echo esc_html( $cat->name ); ?></span>
        <?php endif; ?>
        <<?php echo $heading; ?> class="pga-card-title"><a href="<?php echo esc_url( get_permalink( $post ) ); ?>"><?php echo esc_html( wp_strip_all_tags( get_the_title( $post ) ) ); ?></a></<?php echo $heading; ?>>
        <p class="pga-card-excerpt"><?php echo esc_html( wp_trim_words( get_the_excerpt( $post ), 22 ) ); ?></p>
        <div class="pga-card-meta">
          <span class="pga-card-author">
            <?php echo get_avatar( $post->post_author, 28, '', '', array( 'class' => 'pga-avatar', 'alt' => '', 'loading' => 'lazy' ) ); ?>
            <span><?php echo esc_html( $user ? $user->display_name : 'ProsperGenics' ); ?></span>
          </span>
          <span class="pga-card-facts">
            <time datetime="<?php echo esc_attr( get_the_date( 'c', $post ) ); ?>"><?php echo esc_html( get_the_date( 'j M Y', $post ) ); ?></time>
            <span class="pga-sep" aria-hidden="true">·</span>
            <span><?php echo esc_html( prospergenics_articles_reading_label( $post ) ); ?></span>
            <span class="pga-sep" aria-hidden="true">·</span>
            <?php echo prospergenics_articles_claps_badge( $post->ID ); ?>
          </span>
        </div>
      </div>
    </article>
    <?php
    return ob_get_clean();
}

/** Share links: LinkedIn and X open their share pages; "Copy link" needs JavaScript. */
function prospergenics_articles_share( $post ) {
    $url   = get_permalink( $post );
    $title = wp_strip_all_tags( get_the_title( $post ) );
    $li    = 'https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode( $url );
    $x     = 'https://twitter.com/intent/tweet?url=' . rawurlencode( $url ) . '&text=' . rawurlencode( $title );
    ob_start();
    ?>
    <div class="pga-share" role="group" aria-label="Share this article">
      <span class="pga-share-label" aria-hidden="true">Share</span>
      <a class="pga-share-btn" href="<?php echo esc_url( $li ); ?>" target="_blank" rel="noopener noreferrer"><?php echo prospergenics_articles_icon( 'linkedin' ); ?><span>LinkedIn</span><span class="screen-reader-text"> (opens in a new tab)</span></a>
      <a class="pga-share-btn" href="<?php echo esc_url( $x ); ?>" target="_blank" rel="noopener noreferrer"><?php echo prospergenics_articles_icon( 'x' ); ?><span>X / Twitter</span><span class="screen-reader-text"> (opens in a new tab)</span></a>
      <button class="pga-share-btn pga-copy" type="button" data-url="<?php echo esc_url( $url ); ?>" hidden><?php echo prospergenics_articles_icon( 'link' ); ?><span>Copy link</span></button>
      <span class="pga-copy-status" role="status" aria-live="polite"></span>
    </div>
    <?php
    return ob_get_clean();
}

/** The clap button (logged-in readers), a login link (visitors) or the count (the author). */
function prospergenics_articles_clap_box( $post ) {
    $post    = get_post( $post );
    $total   = prospergenics_articles_claps( $post->ID );
    $user_id = get_current_user_id();
    $mine    = prospergenics_articles_user_claps( $post->ID, $user_id );
    $max     = PROSPERGENICS_ARTICLES_MAX_CLAPS;
    ob_start();
    ?>
    <div class="pga-clap" data-article="<?php echo esc_attr( $post->ID ); ?>" data-mine="<?php echo esc_attr( $mine ); ?>">
      <?php if ( ! $user_id ) : ?>
        <a class="pga-clap-btn" href="<?php echo esc_url( wp_login_url( get_permalink( $post ) ) ); ?>"><?php echo prospergenics_articles_icon( 'clap' ); ?><span>Log in to clap</span></a>
      <?php elseif ( (int) $post->post_author === $user_id ) : ?>
        <span class="pga-clap-note">Claps for your article</span>
      <?php else : ?>
        <button class="pga-clap-btn" type="button" aria-describedby="pga-clap-help"<?php echo $mine >= $max ? ' disabled' : ''; ?>><?php echo prospergenics_articles_icon( 'clap' ); ?><span>Clap</span></button>
      <?php endif; ?>
      <span class="pga-clap-total"><strong class="pga-clap-count"><?php echo esc_html( number_format_i18n( $total ) ); ?></strong> <?php echo esc_html( _n( 'clap', 'claps', $total ) ); ?></span>
      <?php if ( $user_id && (int) $post->post_author !== $user_id ) : ?>
        <span class="pga-clap-help" id="pga-clap-help">
          <span class="pga-clap-mine"><?php echo esc_html( $mine >= $max ? sprintf( 'You gave the maximum of %d claps. Thank you!', $max ) : sprintf( 'You gave %1$d of %2$d claps.', $mine, $max ) ); ?></span>
        </span>
        <span class="pga-clap-status screen-reader-text" role="status" aria-live="polite"></span>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

/** Series navigation under an article: part x of y, all parts, previous/next. */
function prospergenics_articles_series_box( $post ) {
    $nav = prospergenics_articles_series_nav( $post );
    if ( ! $nav ) {
        return '';
    }
    $parts = get_posts( array(
        'post_type'      => 'pg_article',
        'post_status'    => 'publish',
        'posts_per_page' => 100,
        'orderby'        => 'date',
        'order'          => 'ASC',
        'no_found_rows'  => true,
        'tax_query'      => array( array( 'taxonomy' => 'pg_series', 'terms' => array( $nav['term']->term_id ) ) ),
    ) );
    $url = is_wp_error( $nav['url'] ) ? '' : $nav['url'];
    ob_start();
    ?>
    <nav class="pga-series" aria-labelledby="pga-series-title">
      <p class="pga-series-kicker">Part <?php echo esc_html( $nav['index'] ); ?> of <?php echo esc_html( $nav['total'] ); ?> in the series</p>
      <h2 class="pga-series-title" id="pga-series-title"><?php echo esc_html( $nav['term']->name ); ?></h2>
      <ol class="pga-series-list">
        <?php foreach ( $parts as $p ) : ?>
          <li>
            <?php if ( $p->ID === get_post( $post )->ID ) : ?>
              <span aria-current="page"><?php echo esc_html( wp_strip_all_tags( get_the_title( $p ) ) ); ?></span>
            <?php else : ?>
              <a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( wp_strip_all_tags( get_the_title( $p ) ) ); ?></a>
            <?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ol>
      <div class="pga-series-nav">
        <?php if ( $nav['prev'] ) : ?>
          <a class="pga-series-prev" href="<?php echo esc_url( get_permalink( $nav['prev'] ) ); ?>" rel="prev"><?php echo prospergenics_articles_icon( 'prev' ); ?><span><span class="pga-series-dir">Previous part</span> <?php echo esc_html( wp_strip_all_tags( get_the_title( $nav['prev'] ) ) ); ?></span></a>
        <?php endif; ?>
        <?php if ( $nav['next'] ) : ?>
          <a class="pga-series-next" href="<?php echo esc_url( get_permalink( $nav['next'] ) ); ?>" rel="next"><span><span class="pga-series-dir">Next part</span> <?php echo esc_html( wp_strip_all_tags( get_the_title( $nav['next'] ) ) ); ?></span><?php echo prospergenics_articles_icon( 'next' ); ?></a>
        <?php endif; ?>
      </div>
      <?php if ( $url ) : ?>
        <a class="pga-series-all" href="<?php echo esc_url( $url ); ?>">Series overview</a>
      <?php endif; ?>
    </nav>
    <?php
    return ob_get_clean();
}

/** Category filter and sort links for the listing pages. */
function prospergenics_articles_filters() {
    $sort    = prospergenics_articles_sort();
    $current = is_tax( 'pg_article_category' ) ? get_queried_object_id() : 0;
    $cats    = get_terms( array( 'taxonomy' => 'pg_article_category', 'hide_empty' => true, 'parent' => 0 ) );
    $with    = function ( $url ) use ( $sort ) {
        return 'trending' === $sort ? add_query_arg( 'sort', 'trending', $url ) : $url;
    };
    $here = is_tax() ? get_term_link( get_queried_object() ) : get_post_type_archive_link( 'pg_article' );
    ob_start();
    ?>
    <div class="pga-filters">
      <?php if ( ! is_tax( array( 'pg_article_tag', 'pg_series' ) ) && $cats && ! is_wp_error( $cats ) ) : ?>
        <nav class="pga-chips" aria-label="Filter by category">
          <a class="pga-chip" href="<?php echo esc_url( $with( get_post_type_archive_link( 'pg_article' ) ) ); ?>"<?php echo $current ? '' : ' aria-current="page"'; ?>>All</a>
          <?php foreach ( $cats as $c ) : ?>
            <a class="pga-chip" href="<?php echo esc_url( $with( get_term_link( $c ) ) ); ?>"<?php echo $current === $c->term_id ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $c->name ); ?></a>
          <?php endforeach; ?>
        </nav>
      <?php endif; ?>
      <?php if ( ! is_tax( 'pg_series' ) && ! is_wp_error( $here ) ) : ?>
        <nav class="pga-sort" aria-label="Sort articles">
          <a href="<?php echo esc_url( remove_query_arg( 'sort', $here ) ); ?>"<?php echo 'newest' === $sort ? ' aria-current="true"' : ''; ?>>Newest</a>
          <a href="<?php echo esc_url( add_query_arg( 'sort', 'trending', $here ) ); ?>"<?php echo 'trending' === $sort ? ' aria-current="true"' : ''; ?>>Trending</a>
        </nav>
      <?php endif; ?>
    </div>
    <?php
    return ob_get_clean();
}

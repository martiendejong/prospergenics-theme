<?php
/**
 * Search Form Template
 *
 * @package Prospergenics
 * @since 1.0.0
 */
?>

<form role="search" method="get" class="search-form" action="<?php echo esc_url( home_url( '/' ) ); ?>">
    <label for="search-field" class="screen-reader-text">
        <?php esc_html_e( 'Search for:', 'prospergenics' ); ?>
    </label>
    <div class="search-field-wrapper">
        <input type="search"
               id="search-field"
               class="search-field"
               placeholder="<?php echo esc_attr__( 'Search...', 'prospergenics' ); ?>"
               value="<?php echo get_search_query(); ?>"
               name="s"
               required />
        <button type="submit" class="search-submit">
            <span class="screen-reader-text"><?php esc_html_e( 'Search', 'prospergenics' ); ?></span>
            <span aria-hidden="true">🔍</span>
        </button>
    </div>
</form>

<?php defined('ABSPATH') || exit; ?>
<form role="search" method="get" class="pg-search" action="<?= esc_url(home_url('/')) ?>">
  <label class="screen-reader-text" for="pg-search-field">Search the site</label>
  <input type="search" id="pg-search-field" name="s" value="<?php echo esc_attr(get_search_query()); ?>" placeholder="Search ProsperGenics" />
  <button type="submit">Search</button>
</form>

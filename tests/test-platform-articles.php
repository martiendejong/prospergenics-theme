<?php
/**
 * Standalone test for the Prospergenics Platform articles track (JengoWork #6365-#6370):
 * wp-theme/prospergenics-2026/inc/platform-articles.php and inc/articles/.
 *
 * Checks the logic that does not need a database:
 *   - reading time from the word count (#6365),
 *   - the 50-claps-per-reader cap, the per-day tally behind "trending" and the rate limit (#6369),
 *   - previous/next in a series (#6370),
 *   - the sharing tags: og:type article, article:* tags, image fallback to the content (#6365),
 * and the wiring: hooks, REST permission callback, nonces and capabilities on the My articles
 * actions (#6368), the content-saved action, templates and assets in the generated theme.
 *
 * Run with: php tests/test-platform-articles.php
 */

$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ───────────────────────────── WordPress stubs ─────────────────────────────
define( 'ABSPATH', __DIR__ . '/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['_hooks'] = array();
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][ $hook ][] = $cb; return true; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['_hooks'][ $hook ][] = $cb; return true; }
function remove_filter( $hook, $cb, $prio = 10 ) { return true; }
function apply_filters( $hook, $value ) { return $value; }
function wp_strip_all_tags( $s ) { return trim( strip_tags( $s ) ); }

require $theme . '/inc/platform-articles.php';

// ───────────────────────────── Reading time ─────────────────────────────
echo "Reading time (#6365)\n";
ok( 0 === prospergenics_articles_word_count( '' ), 'empty content has 0 words' );
ok( 4 === prospergenics_articles_word_count( '<!-- wp:paragraph --><p>Hello <strong>value</strong> creators, today!</p><!-- /wp:paragraph -->' ), 'block comments and tags are not words; 4 words' );
ok( 3 === prospergenics_articles_word_count( '<p>one</p><p>two</p><p>three</p>' ), 'paragraphs do not run words together' );
ok( 2 === prospergenics_articles_word_count( "<p>don't well-known</p>" ), 'apostrophes and hyphens stay inside a word' );
ok( 2 === prospergenics_articles_word_count( '<p>Jambo</p>[gallery ids="1,2"]<script>var a = 1;</script><p>dunia</p>' ), 'shortcodes and scripts are not counted' );
ok( 3 === prospergenics_articles_word_count( '<p>Habari ya asubuhi</p>' ), 'Swahili' );
ok( 1 === prospergenics_articles_minutes_for( 0 ), 'minimum reading time is 1 minute' );
ok( 1 === prospergenics_articles_minutes_for( 200 ), '200 words = 1 min' );
ok( 2 === prospergenics_articles_minutes_for( 201 ), '201 words = 2 min (rounded up)' );
ok( 5 === prospergenics_articles_minutes_for( 913 ), '913 words = 5 min' );
ok( 200 === PROSPERGENICS_ARTICLES_WPM, 'reads at 200 words a minute' );
ok( in_array( 'mine', prospergenics_articles_reserved_slugs(), true ) && in_array( 'category', prospergenics_articles_reserved_slugs(), true ), 'article slugs cannot be "mine" or "category" (sub-paths of /articles/)' );

// ───────────────────────────── Claps ─────────────────────────────
echo "Claps (#6369)\n";
ok( 50 === PROSPERGENICS_ARTICLES_MAX_CLAPS, 'cap is 50 claps per reader per article' );
ok( 1 === prospergenics_articles_clap_grant( 0, 1 ), 'first clap is granted' );
ok( 50 === prospergenics_articles_clap_grant( 0, 50 ), '50 at once is granted' );
ok( 2 === prospergenics_articles_clap_grant( 48, 10 ), '48 + 10 -> only 2 granted' );
ok( 0 === prospergenics_articles_clap_grant( 50, 1 ), 'nothing more after 50' );
ok( 0 === prospergenics_articles_clap_grant( 60, 1 ), 'never negative' );
ok( 0 === prospergenics_articles_clap_grant( 0, -5 ), 'negative requests grant nothing' );

$days = prospergenics_articles_add_to_days( array(), '2026-10-25', 3 );
$days = prospergenics_articles_add_to_days( $days, '2026-10-25', 2 );
ok( array( '2026-10-25' => 5 ) === $days, 'claps on the same day add up' );
$days = prospergenics_articles_add_to_days( array( '2026-09-01' => 40, '2026-09-26' => 7, '2026-09-25' => 9, 'junk' => 3 ), '2026-10-25', 1 );
ok( array( '2026-09-26' => 7, '2026-10-25' => 1 ) === $days, 'days outside the 30-day window (and junk keys) are dropped' );
ok( 8 === prospergenics_articles_trend_score( $days ), 'trend score = claps in the last 30 days' );
ok( 0 === prospergenics_articles_trend_score( prospergenics_articles_prune_days( array( '2026-01-01' => 9 ), '2026-10-25' ) ), 'old claps decay to 0' );
ok( array() === prospergenics_articles_add_to_days( 'not an array', '2026-10-25', 0 ), 'corrupt meta is treated as empty' );

$state = false;
$allowed = 0;
for ( $i = 0; $i < 40; $i++ ) {
	$r = prospergenics_articles_rate_hit( $state, 1000 + $i );
	$state = $r['state'];
	$allowed += $r['allowed'] ? 1 : 0;
}
ok( 30 === $allowed, '30 requests a minute are allowed, the rest refused' );
$r = prospergenics_articles_rate_hit( $state, 1000 + 61 );
ok( $r['allowed'] && 1 === $r['state']['hits'], 'a new minute starts a new window' );

// Storage (audit ARC-21): the claps table and the running total, against a fake $wpdb that
// understands the queries claps.php sends (with MySQL's ON DUPLICATE KEY UPDATE semantics).
echo "Claps storage: {prefix}pg_claps + one total meta (ARC-21)\n";
$GLOBALS['_meta'] = array();
function get_post_meta( $id, $k = '', $single = false ) { return isset( $GLOBALS['_meta'][ $id ][ $k ] ) ? $GLOBALS['_meta'][ $id ][ $k ] : ''; }
function update_post_meta( $id, $k, $v ) { $GLOBALS['_meta'][ $id ][ $k ] = $v; return true; }
function wp_cache_delete( $k, $g = '' ) { return true; }
function current_time( $type, $gmt = false ) { return gmdate( 'Y-m-d H:i:s' ); }
class Fake_Claps_WPDB {
	public $prefix = 'wp_', $postmeta = 'wp_postmeta', $rows = array(), $log = array();
	public function prepare( $q, ...$args ) {
		$args = ( 1 === count( $args ) && is_array( $args[0] ) ) ? $args[0] : $args;
		$i    = 0;
		return preg_replace_callback( '/%[ds]/', function ( $m ) use ( $args, &$i ) {
			$v = $args[ $i++ ];
			return '%d' === $m[0] ? (string) (int) $v : "'" . addslashes( $v ) . "'";
		}, $q );
	}
	public function query( $q ) {
		$this->log[] = $q;
		if ( preg_match( '/^INSERT INTO wp_pg_claps .* VALUES \((\d+), (\d+), (\d+), \'[^\']*\'\) ON DUPLICATE KEY UPDATE n = LEAST\((\d+), n \+ VALUES\(n\)\)/', $q, $m ) ) {
			$k = $m[1] . ':' . $m[2];
			$this->rows[ $k ] = array( 'user_id' => (int) $m[1], 'post_id' => (int) $m[2], 'n' => isset( $this->rows[ $k ] ) ? min( (int) $m[4], $this->rows[ $k ]['n'] + (int) $m[3] ) : (int) $m[3] );
			return 1;
		}
		if ( preg_match( "/^UPDATE wp_postmeta SET meta_value = CAST\\(meta_value AS UNSIGNED\\) \\+ (\\d+) WHERE post_id = (\\d+) AND meta_key = '_pg_articles_claps'/", $q, $m ) ) {
			if ( ! isset( $GLOBALS['_meta'][ (int) $m[2] ]['_pg_articles_claps'] ) ) {
				return 0;
			}
			$GLOBALS['_meta'][ (int) $m[2] ]['_pg_articles_claps'] += (int) $m[1];
			return 1;
		}
		return false;
	}
	private function where( $q, $col ) { return preg_match( "/$col = (\\d+)/", $q, $m ) ? (int) $m[1] : null; }
	public function get_var( $q ) {
		$this->log[] = $q;
		$u = $this->where( $q, 'user_id' );
		$p = $this->where( $q, 'post_id' );
		if ( 0 === strpos( $q, 'SELECT n FROM wp_pg_claps' ) ) {
			return isset( $this->rows[ "$u:$p" ] ) ? (string) $this->rows[ "$u:$p" ]['n'] : null;
		}
		if ( 0 === strpos( $q, 'SELECT COALESCE(SUM(n), 0) FROM wp_pg_claps' ) ) {
			return (string) array_sum( array_column( array_filter( $this->rows, function ( $r ) use ( $p ) { return $r['post_id'] === $p; } ), 'n' ) );
		}
		return null;
	}
	public function get_results( $q ) {
		$this->log[] = $q;
		$u = $this->where( $q, 'user_id' );
		return array_map( function ( $r ) { return (object) $r; }, array_values( array_filter( $this->rows, function ( $r ) use ( $u ) { return $r['user_id'] === $u; } ) ) );
	}
	public function delete( $table, $where ) {
		$this->log[] = "DELETE $table " . json_encode( $where );
		$col = key( $where );
		$this->rows = array_filter( $this->rows, function ( $r ) use ( $col, $where ) { return $r[ $col ] !== (int) $where[ $col ]; } );
		return 1;
	}
}
$GLOBALS['wpdb'] = $wpdb = new Fake_Claps_WPDB();
ok( 'wp_pg_claps' === prospergenics_articles_claps_table(), 'the table is {prefix}pg_claps' );
$r = prospergenics_articles_add_claps( 7, 3, 30 );
ok( array( 'granted' => 30, 'mine' => 30, 'claps' => 30 ) === $r, 'first claps: a row in the table, the total created by a recount (no total stored yet)' );
$r = prospergenics_articles_add_claps( 7, 3, 30 );
ok( array( 'granted' => 20, 'mine' => 50, 'claps' => 50 ) === $r, 'the cap holds: 30 + 30 grants 20' );
ok( 0 === prospergenics_articles_add_claps( 7, 3, 1 )['granted'] && 50 === prospergenics_articles_user_claps( 7, 3 ), 'nothing more after 50' );
$wpdb->log = array();
$r = prospergenics_articles_add_claps( 7, 4, 5 );
ok( 55 === $r['claps'] && 55 === (int) get_post_meta( 7, '_pg_articles_claps', true ), 'a second reader raises the total to 55' );
$sql = implode( "\n", $wpdb->log );
ok( false !== strpos( $sql, 'ON DUPLICATE KEY UPDATE n = LEAST(50, n + VALUES(n))' ) && false !== strpos( $sql, 'meta_value = CAST(meta_value AS UNSIGNED) + 5' ), 'one atomic upsert and one n = n + 5 on the total' );
ok( false === strpos( $sql, 'LIKE' ) && false === strpos( $sql, 'SUM(' ), 'a clap runs no LIKE scan and no recount' );
ok( 0 === prospergenics_articles_user_claps( 7, 0 ), 'a visitor (user 0) has no claps' );
prospergenics_articles_add_claps( 8, 3, 2 );
ok( array( 7 => 50, 8 => 2 ) === prospergenics_articles_claps_by_user( 3 ), 'claps_by_user lists article => claps for the exporter' );
ok( 2 === prospergenics_articles_erase_claps( 3 ) && array() === prospergenics_articles_claps_by_user( 3 ), 'erasing removes the reader\'s rows' );
ok( 5 === prospergenics_articles_claps( 7 ) && 0 === prospergenics_articles_claps( 8 ), 'and the totals are recounted from what is left' );
$csrc = file_get_contents( $theme . '/inc/articles/claps.php' );
ok( false !== strpos( $csrc, "dbDelta( \"CREATE TABLE {\$table} (" ) && false !== strpos( $csrc, 'PRIMARY KEY  (user_id,post_id)' ) && false !== strpos( $csrc, 'KEY post_id (post_id)' ), 'dbDelta creates the table with (user_id, post_id) as key and an index on post_id' );
ok( false !== strpos( $csrc, "get_option( 'prospergenics_articles_claps_db' ) >= PROSPERGENICS_ARTICLES_CLAPS_DB" ) && false !== strpos( $csrc, "add_action( 'init', 'prospergenics_articles_claps_install', 5 )" ), 'the install runs once per schema version, early on init' );
ok( false !== strpos( $csrc, 'ON DUPLICATE KEY UPDATE n = GREATEST(n, VALUES(n))' ) && false !== strpos( $csrc, 'DELETE FROM {$wpdb->postmeta} WHERE meta_id IN (' ), 'the migration upserts the old per-reader meta in batches and deletes what it moved' );
ok( false === strpos( $csrc, "PROSPERGENICS_ARTICLES_BY_PREFIX . (int) \$user_id" ), 'no per-reader post meta is written or read any more' );
ok( false !== strpos( $csrc, "add_action( 'deleted_user', 'prospergenics_articles_erase_claps' )" ) && false !== strpos( $csrc, "add_action( 'deleted_post'," ), 'deleting a user or an article removes their rows' );

// ───────────────────────────── Series ─────────────────────────────
echo "Series (#6370)\n";
$p = prospergenics_articles_series_position( array( 5, 9, 12 ), 9 );
ok( array( 'index' => 2, 'total' => 3, 'prev' => 5, 'next' => 12 ) === $p, 'middle part: part 2 of 3 with previous and next' );
$p = prospergenics_articles_series_position( array( '5', '9', '12' ), 5 );
ok( 1 === $p['index'] && 0 === $p['prev'] && 9 === $p['next'], 'first part has no previous (string IDs work)' );
$p = prospergenics_articles_series_position( array( 5, 9, 12 ), 12 );
ok( 3 === $p['index'] && 0 === $p['next'], 'last part has no next' );
ok( null === prospergenics_articles_series_position( array( 5, 9 ), 7 ), 'an unpublished part is not placed' );

// ───────────────────────────── SEO ─────────────────────────────
echo "Sharing tags (#6365)\n";
ok( 'https://cdn.example.com/a.jpg' === prospergenics_articles_first_image( '<p>x</p><figure><img class="a" src="https://cdn.example.com/a.jpg" alt=""></figure><img src="https://b.jpg">', 'https://prospergenics.com/' ), 'first image in the content' );
ok( 'https://prospergenics.com/wp-content/uploads/x.webp' === prospergenics_articles_first_image( "<img src='/wp-content/uploads/x.webp'>", 'https://prospergenics.com/' ), 'root-relative image made absolute' );
ok( '' === prospergenics_articles_first_image( '<img src="data:image/png;base64,AAA">', 'https://prospergenics.com/' ), 'data: images are not used' );
ok( '' === prospergenics_articles_first_image( '<p>no images</p>', 'https://prospergenics.com/' ), 'no image -> empty (falls back to the cover or the site image)' );

$rows = prospergenics_articles_meta_tags( array(
	'title' => 'Why value beats volume', 'description' => 'Most teams measure output.', 'image' => 'https://p.com/i.jpg',
	'url' => 'https://p.com/articles/why/', 'type' => 'article', 'published' => '2026-10-05T20:12:40+00:00',
	'modified' => '2026-10-06T08:00:00+00:00', 'section' => 'Value creation', 'tags' => array( 'kenya', 'strategy' ),
) );
$map = array();
foreach ( $rows as $r ) { $map[ $r[1] ][] = $r[2]; }
ok( array( 'article' ) === $map['og:type'], 'og:type is article' );
ok( array( 'Why value beats volume — ProsperGenics' ) === $map['og:title'], 'og:title from the title' );
ok( array( 'Most teams measure output.' ) === $map['description'] && $map['description'] === $map['og:description'], 'description = og:description' );
ok( array( '2026-10-05T20:12:40+00:00' ) === $map['article:published_time'], 'article:published_time' );
ok( array( 'Value creation' ) === $map['article:section'], 'article:section = category' );
ok( array( 'kenya', 'strategy' ) === $map['article:tag'], 'one article:tag per tag' );
ok( isset( $map['twitter:card'], $map['twitter:image'], $map['og:image'], $map['og:url'] ), 'twitter card, og:image and og:url present' );
$names = array_column( $rows, 1 );
ok( count( $names ) === count( array_unique( array_diff( $names, array( 'article:tag' ) ) ) ) + count( $map['article:tag'] ), 'no tag printed twice' );
$list = prospergenics_articles_meta_tags( array( 'title' => 'Articles', 'description' => 'd', 'image' => 'i', 'url' => 'u', 'type' => 'website' ) );
ok( ! in_array( 'article:published_time', array_column( $list, 1 ), true ), 'list pages get no article:* tags' );

// ───────────────────────────── Wiring ─────────────────────────────
echo "Wiring\n";
$src = '';
foreach ( glob( $theme . '/inc/articles/*.php' ) as $f ) { $src .= file_get_contents( $f ); }
$src .= file_get_contents( $theme . '/inc/platform-articles.php' );
ok( false !== strpos( file_get_contents( $theme . '/functions.php' ), "/inc/platform-articles.php'" ), 'functions.php loads the articles loader' );
foreach ( array( 'init', 'pre_get_posts', 'rest_api_init', 'wp_after_insert_post', 'wp_head', 'wp_enqueue_scripts', 'template_redirect', 'admin_post_pg_articles_unpublish', 'admin_post_pg_articles_publish', 'block_editor_settings_all', 'prospergenics_2026_seo_plugin_active', 'wp_unique_post_slug_is_bad_flat_slug' ) as $hook ) {
	ok( isset( $GLOBALS['_hooks'][ $hook ] ), "hooks into $hook" );
}
ok( false === strpos( $src, '__return_true' ), 'no REST route is public (no __return_true permission callback)' );
ok( false !== strpos( $src, "'permission_callback' => 'prospergenics_articles_clap_permission'" ), 'the clap route has a permission callback' );
ok( false !== strpos( $src, "do_action( 'prospergenics_platform_content_saved', \$post_id, 'article' )" ), 'fires prospergenics_platform_content_saved with type article' );
ok( false !== strpos( $src, "'has_archive'     => 'articles'" ) && false !== strpos( $src, "register_post_type( 'pg_article'" ), 'pg_article with its archive at /articles/' );
ok( false !== strpos( $src, "'show_in_rest'    => true" ), 'pg_article uses the block editor (show_in_rest)' );
foreach ( array( 'pg_article_category', 'pg_article_tag', 'pg_series' ) as $tax ) {
	ok( false !== strpos( $src, "register_taxonomy( '$tax'" ), "registers $tax" );
}
ok( false !== strpos( $src, "\$settings['autosaveInterval'] = 30" ), 'autosave every 30 seconds (#6368)' );
ok( false !== strpos( $src, "check_admin_referer( \$action . '_' . \$post_id )" ) && 2 === substr_count( $src, "prospergenics_articles_action_post( 'pg_articles_" ), 'both My articles actions check a per-post nonce' );
ok( false !== strpos( $src, "current_user_can( \$cap, \$post_id )" ), 'actions check a per-post capability' );
ok( false !== strpos( $src, "prospergenics_articles_action_post( 'pg_articles_unpublish', 'edit_post' )" ) && false !== strpos( $src, "prospergenics_articles_action_post( 'pg_articles_publish', array( 'edit_post', 'publish_post' ) )" ), 'unpublish needs edit_post, publish needs edit_post and publish_post (an author cannot publish someone else\'s pending article)' );
ok( false !== strpos( $src, '$wpdb->prepare(' ) && substr_count( $src, '$wpdb->query(' ) === substr_count( $src, '$wpdb->query( $wpdb->prepare(' ), 'SQL only through $wpdb->prepare()' );
ok( false !== strpos( $src, "add_filter( 'wp_robots', 'wp_robots_no_robots' )" ), 'My articles is noindex' );
foreach ( array( 'single-pg_article.php', 'archive-pg_article.php', 'taxonomy-pg_article_category.php', 'taxonomy-pg_article_tag.php', 'taxonomy-pg_series.php', 'inc/articles/views/mine.php', 'assets/platform/articles.css', 'assets/platform/articles.js' ) as $f ) {
	ok( file_exists( $theme . '/' . $f ), "$f is in the generated theme" );
}
foreach ( array( 'single-pg_article.php', 'archive-pg_article.php', 'inc/articles/views/mine.php' ) as $tpl ) {
	$t = file_get_contents( $theme . '/' . $tpl );
	ok( false !== strpos( $t, 'get_header()' ) && false !== strpos( $t, 'get_footer()' ), "$tpl uses the shared header and footer" );
}
$js = file_get_contents( $theme . '/assets/platform/articles.js' );
ok( false !== strpos( $js, "'X-WP-Nonce': cfg.nonce" ), 'the clap button sends the REST nonce' );
ok( false === strpos( $js, 'innerHTML' ), 'the script never writes HTML' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

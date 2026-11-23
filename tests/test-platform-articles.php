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
function update_post_meta( $id, $k, $v ) { $GLOBALS['_meta'][ $id ][ $k ] = is_string( $v ) ? stripslashes( $v ) : $v; return true; } // unslashes strings, as WordPress does
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
foreach ( array( 'init', 'pre_get_posts', 'rest_api_init', 'wp_after_insert_post', 'wp_head', 'wp_enqueue_scripts', 'template_redirect', 'admin_post_pg_articles_unpublish', 'admin_post_pg_articles_publish', 'admin_post_pg_articles_send_back', 'transition_post_status', 'block_editor_settings_all', 'prospergenics_2026_seo_plugin_active', 'wp_unique_post_slug_is_bad_flat_slug' ) as $hook ) {
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
ok( false !== strpos( $src, "check_admin_referer( \$action . '_' . \$post_id )" ) && 3 === substr_count( $src, "prospergenics_articles_action_post( 'pg_articles_" ), 'the three My articles actions (unpublish, publish, send back) check a per-post nonce' );
ok( false !== strpos( $src, "current_user_can( \$cap, \$post_id )" ), 'actions check a per-post capability' );
ok( false !== strpos( $src, "prospergenics_articles_action_post( 'pg_articles_unpublish', 'edit_post' )" ) && false !== strpos( $src, "prospergenics_articles_action_post( 'pg_articles_publish', array( 'edit_post', 'publish_post' ) )" ), 'unpublish needs edit_post, publish needs edit_post and publish_post (an author cannot publish someone else\'s pending article)' );
ok( false !== strpos( $src, "prospergenics_articles_action_post( 'pg_articles_send_back', array( 'edit_post', 'edit_others_pg_articles' ) )" ), 'send back needs edit_post and edit_others_pg_articles (staff who review, #6457)' );
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

// ───────────────────────────── Send back with a note (#6457) ─────────────────────────────
// The admin-post handler run for real against stubs: wp_die() and wp_safe_redirect() throw, so the
// code after them (exit) never runs; wp_update_post() fires the review loop's status transition as
// core's transition_post_status does.
echo "Send back with a note (#6457)\n";
class WP_Post {
	public $ID, $post_type, $post_status, $post_author, $post_title;
	function __construct( $id, $type, $status, $author, $title ) { $this->ID = $id; $this->post_type = $type; $this->post_status = $status; $this->post_author = $author; $this->post_title = $title; }
}
class WP_Error { function __construct( $c = '', $m = '' ) {} }
class PG_Died extends Exception {}
class PG_Redirect extends Exception {}
function is_wp_error( $x ) { return $x instanceof WP_Error; }
function wp_die( $m = '', $code = 500 ) { throw new PG_Died( (string) $m, (int) $code ); }
function wp_safe_redirect( $url ) { throw new PG_Redirect( $url ); }
function check_admin_referer( $action ) { if ( $GLOBALS['_nonce'] !== $action ) { throw new PG_Died( 'The link you followed has expired.', 403 ); } return 1; }
function current_user_can( $cap, ...$args ) { return ! empty( $GLOBALS['_can'][ $cap ] ); }
function user_can( $id, $cap, ...$args ) { return false; }
function get_users( $args ) { return array(); }
function get_current_user_id() { return 1; }
function get_post( $id ) { return isset( $GLOBALS['_posts'][ (int) $id ] ) ? $GLOBALS['_posts'][ (int) $id ] : null; }
function get_userdata( $id ) {
	$u = array( 1 => array( 'Lessy', 'lessy@example.test' ), 2 => array( 'Amina Wanjiru', 'amina@example.test' ) );
	return isset( $u[ $id ] ) ? (object) array( 'ID' => $id, 'display_name' => $u[ $id ][0], 'user_email' => $u[ $id ][1] ) : false;
}
function delete_post_meta( $id, $k ) { unset( $GLOBALS['_meta'][ $id ][ $k ] ); return true; }
function wp_slash( $v ) { return is_string( $v ) ? addslashes( $v ) : $v; }
function wp_unslash( $v ) { return $v; }
function absint( $n ) { return abs( (int) $n ); }
function sanitize_key( $k ) { return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $k ) ); }
function sanitize_textarea_field( $s ) { // core's _sanitize_text_fields( $s, true ), the parts that matter here
	$s = (string) $s;
	if ( false !== strpos( $s, '<' ) ) {
		$s = strip_tags( preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $s ) );
	}
	return trim( $s );
}
function wp_update_post( $a, $wp_error = false ) {
	if ( ! empty( $GLOBALS['_update_fails'] ) ) {
		return new WP_Error( 'db', 'Could not update the post in the database.' );
	}
	$p   = $GLOBALS['_posts'][ $a['ID'] ];
	$old = $p->post_status;
	$p->post_status = $a['post_status'];
	prospergenics_articles_review_transition( $p->post_status, $old, $p ); // what transition_post_status runs
	return $p->ID;
}
function wp_is_post_revision( $p ) { return false; }
function wp_specialchars_decode( $s ) { return htmlspecialchars_decode( (string) $s, ENT_QUOTES ); }
function prospergenics_profiles_send_mail( $to, $subject, $body, $type, $uid = 0 ) { $GLOBALS['_mails'][] = compact( 'to', 'subject', 'body', 'type' ); return true; }
function home_url( $p = '' ) { return 'https://pg.test' . $p; }
function admin_url( $p = '' ) { return 'https://pg.test/wp-admin/' . $p; }
function add_query_arg( $args, $url ) { return $args ? $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . http_build_query( $args ) : $url; }
function get_permalink( $p ) { return 'https://pg.test/articles/x/'; }
function get_post_time( $f, $gmt = false, $p = null ) { return '20 November 2026, 10:00'; }
function get_the_title( $p ) { return $p->post_title; }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_attr( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_url( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES ); }
function esc_html__( $s ) { return $s; }
function wp_nonce_field( $action, $name = '_wpnonce', $referer = true, $echo = true ) { $f = '<input type="hidden" name="' . $name . '" value="nonce:' . $action . '" />'; if ( $echo ) { echo $f; } return $f; }

$GLOBALS['_mails'] = array();
$reset = function ( $status = 'pending', $type = 'pg_article' ) {
	$GLOBALS['_posts'][96] = new WP_Post( 96, $type, $status, 2, 'How we price a first prototype' );
	$GLOBALS['_meta'][96]  = array();
	$GLOBALS['_mails']     = array();
	$GLOBALS['_can']       = array( 'edit_post' => true, 'edit_others_pg_articles' => true ); // an editor
	$GLOBALS['_update_fails'] = false;
};
$send = function ( array $post, $nonce = 'pg_articles_send_back_96' ) {
	$_POST = $post + array( 'action' => 'pg_articles_send_back', 'post_id' => '96' );
	$GLOBALS['_nonce'] = $nonce;
	try {
		$GLOBALS['_hooks']['admin_post_pg_articles_send_back'][0]();
		return array( 'nothing', '' );
	} catch ( PG_Died $e ) {
		return array( 'died', $e->getCode() );
	} catch ( PG_Redirect $e ) {
		return array( 'redirect', $e->getMessage() );
	}
};
$untouched = function () {
	return 'pending' === $GLOBALS['_posts'][96]->post_status && array() === $GLOBALS['_meta'][96] && array() === $GLOBALS['_mails'];
};
ok( in_array( 'prospergenics_articles_review_transition', $GLOBALS['_hooks']['transition_post_status'], true ), 'the review mails hang on transition_post_status' );
$note = "Please add a price example for a two-week prototype.\r\nAnd a source for the 30% figure (C:\\data\\prices.xlsx).";

$reset();
ok( array( 'died', 403 ) === $send( array( 'note' => $note ), 'nothing' ) && $untouched(), 'without a valid nonce: 403, nothing changes' );
$reset();
ok( array( 'died', 403 ) === $send( array( 'note' => $note ), 'pg_articles_send_back_97' ) && $untouched(), 'the nonce of another article does not work: 403' );
$reset();
$GLOBALS['_can'] = array( 'edit_post' => true ); // an author on their own pending article
ok( array( 'died', 403 ) === $send( array( 'note' => $note ) ) && $untouched(), 'edit_post without edit_others_pg_articles (an author): 403, nothing changes' );
$reset();
$GLOBALS['_can'] = array( 'edit_others_pg_articles' => true );
ok( array( 'died', 403 ) === $send( array( 'note' => $note ) ) && $untouched(), 'without edit_post for this article: 403' );
$reset( 'pending', 'post' );
ok( array( 'died', 403 ) === $send( array( 'note' => $note ) ), 'only articles (pg_article), not other posts: 403' );
$reset();
$_POST = array( 'post_id' => '4242' );
$GLOBALS['_nonce'] = 'pg_articles_send_back_4242';
try { $GLOBALS['_hooks']['admin_post_pg_articles_send_back'][0](); $r = 'nothing'; } catch ( PG_Died $e ) { $r = $e->getCode(); } catch ( PG_Redirect $e ) { $r = 'redirect'; }
ok( 403 === $r, 'an article that does not exist: 403' );

foreach ( array( 'no note' => array(), 'an empty note' => array( 'note' => '' ), 'only spaces and line breaks' => array( 'note' => "  \r\n \n\t " ), 'only markup' => array( 'note' => '<b></b><script>alert(1)</script>' ) ) as $label => $post ) {
	$reset();
	$r = $send( $post );
	ok( array( 'redirect', 'https://pg.test/articles/mine/?status=review&done=note_missing&article=96' ) === $r && $untouched(), "$label: refused, back to the form with the error (done=note_missing, article=96); still pending, no meta, no mail" );
}

$reset( 'draft' );
ok( array( 'redirect', 'https://pg.test/articles/mine/?status=review&done=not_pending' ) === $send( array( 'note' => $note ) ) && 'draft' === $GLOBALS['_posts'][96]->post_status && array() === $GLOBALS['_meta'][96], 'an article no longer pending (already sent back or published): not_pending, nothing changes' );

$reset();
$r = $send( array( 'note' => $note ) );
ok( array( 'redirect', 'https://pg.test/articles/mine/?status=review&done=sent_back' ) === $r, 'a note: back to the review queue with done=sent_back' );
ok( 'draft' === $GLOBALS['_posts'][96]->post_status, 'the article moves from pending to draft' );
$saved = prospergenics_articles_note( 96 );
ok( "Please add a price example for a two-week prototype.\nAnd a source for the 30% figure (C:\\data\\prices.xlsx)." === $saved['note'], 'the note is saved in _pg_articles_review_note (line breaks as \n; backslashes kept through update_post_meta\'s unslash)' );
ok( 1 === $saved['by'] && abs( time() - $saved['at'] ) < 5, 'with who sent it back and when (_by, _at)' );
ok( 1 === count( $GLOBALS['_mails'] ) && 'amina@example.test' === $GLOBALS['_mails'][0]['to'] && 'article_returned' === $GLOBALS['_mails'][0]['type'], 'one mail, to the author (the "returned" mail of the review loop)' );
$m = $GLOBALS['_mails'][0];
ok( 'Your article is back in your drafts: How we price a first prototype' === $m['subject'], 'subject: back in your drafts' );
ok( false !== strpos( $m['body'], "Lessy sent \"How we price a first prototype\" back to your drafts, so it is not published yet. What to change:\n\n> Please add a price example for a two-week prototype.\n> And a source for the 30% figure (C:\\data\\prices.xlsx).\n\nMake the changes" ), 'the mail names the reviewer and quotes the note, every line with "> "' );
ok( false !== strpos( $m['body'], 'Edit it: https://pg.test/wp-admin/post.php?post=96&action=edit' ) && false === strpos( $m['body'], "\r" ), 'with the edit link; no \r in the plain-text body' );

$reset();
$send( array( 'note' => "<script>alert('x')</script><b>Fix</b> the intro\n\n\n\n\nand the ending\x07" ) );
$m = $GLOBALS['_mails'][0];
ok( "Fix the intro\n\nand the ending" === prospergenics_articles_note( 96 )['note'], 'markup and control characters are removed from the note before it is saved; blank lines are kept to one' );
ok( false === strpos( $m['body'], '<' ) && false === strpos( $m['body'], 'alert' ) && false === strpos( $m['body'], "\x07" ) && false !== strpos( $m['body'], "> Fix the intro\n> \n> and the ending\n" ), 'the mail is plain-text safe: no markup, no control characters' );
$reset();
$send( array( 'note' => str_repeat( 'é', 2500 ) ) );
ok( 2000 === mb_strlen( prospergenics_articles_note( 96 )['note'] ) && 2000 === PROSPERGENICS_ARTICLES_NOTE_MAX, 'a note is cut at 2000 characters (multibyte safe)' );
$reset();
$GLOBALS['_update_fails'] = true;
ok( array( 'redirect', 'https://pg.test/articles/mine/?status=review&done=failed' ) === $send( array( 'note' => $note ) ) && 'pending' === $GLOBALS['_posts'][96]->post_status && null === prospergenics_articles_note( 96 ), 'if the status cannot be changed: failed, and the note is not left behind' );

echo "Send back: the note's life, the mails, the form\n";
$reset();
$send( array( 'note' => $note ) );
$post = $GLOBALS['_posts'][96];
prospergenics_articles_review_transition( 'draft', 'draft', $post ); // saving the draft
ok( null !== prospergenics_articles_note( 96 ), 'the note stays while the author works on the draft' );
$GLOBALS['_mails'] = array();
$post->post_status = 'pending';
prospergenics_articles_review_transition( 'pending', 'draft', $post ); // submitted again
ok( null === prospergenics_articles_note( 96 ), 'submitting it again clears the note' );
$post->post_status = 'draft';
prospergenics_articles_review_transition( 'draft', 'pending', $post ); // sent back in wp-admin, without a note
ok( 1 === count( $GLOBALS['_mails'] ) && false !== strpos( $GLOBALS['_mails'][0]['body'], 'An editor moved "How we price a first prototype" back to your drafts' ) && false === strpos( $GLOBALS['_mails'][0]['body'], '> ' ), 'a later return without a note (wp-admin) mails no old note' );
foreach ( array( 'publish', 'future' ) as $st ) {
	$reset();
	$send( array( 'note' => $note ) );
	prospergenics_articles_review_transition( $st, 'draft', $GLOBALS['_posts'][96] );
	ok( null === prospergenics_articles_note( 96 ), "publishing the draft ($st) clears the note too" );
}
$mail = prospergenics_articles_review_mail( 'returned', 'T', 'Amina', array( 'edit' => 'E', 'mine' => 'M' ) );
ok( "Hi Amina,\n\nAn editor moved \"T\" back to your drafts, so it is not published yet. Have a look, make changes and submit it for review again when it is ready.\n\nEdit it: E\nYour articles: M\n" === $mail['body'], 'without a note the "returned" mail is the one it was' );
$mail = prospergenics_articles_review_mail( 'returned', 'T', 'Amina', array( 'edit' => 'E', 'mine' => 'M' ), "Shorter title\r\nplease", '' );
ok( false !== strpos( $mail['body'], "An editor sent \"T\" back to your drafts, so it is not published yet. What to change:\n\n> Shorter title\n> please\n\n" ), 'with a note and no known reviewer: "An editor sent…"' );
ok( '' === prospergenics_articles_review_mail_note( " \n " ) && '> a' === prospergenics_articles_review_mail_note( 'a' ), 'the quoted note: empty stays empty' );

$reset();
ok( prospergenics_articles_can_send_back( $GLOBALS['_posts'][96] ), 'the form is offered to staff for a pending article' );
$GLOBALS['_can'] = array( 'edit_post' => true );
ok( ! prospergenics_articles_can_send_back( $GLOBALS['_posts'][96] ), 'not to an author (no edit_others_pg_articles)' );
$reset( 'draft' );
ok( ! prospergenics_articles_can_send_back( $GLOBALS['_posts'][96] ), 'not for an article that is not pending' );

$reset();
$GLOBALS['_posts'][96]->post_title = 'Tom & Jerry';
$html = prospergenics_articles_send_back_form( $GLOBALS['_posts'][96], 'pgm', 'manage' );
ok( 0 === strpos( $html, '<details class="pgm-sendback"><summary class="pgm-btn pgm-btn-ghost">Send back…<span class="screen-reader-text"> “Tom &amp; Jerry”</span></summary>' ), 'a closed disclosure; its button names the article for screen readers (escaped)' );
ok( false !== strpos( $html, '<input type="hidden" name="action" value="pg_articles_send_back" /><input type="hidden" name="post_id" value="96" /><input type="hidden" name="pg_from" value="manage" /><input type="hidden" name="_wpnonce" value="nonce:pg_articles_send_back_96" />' ), 'the action, the article, the way back and a nonce for this article' );
ok( false !== strpos( $html, '<label for="pgm-note-96">What should the author change?</label>' ) && 1 === preg_match( '#<textarea id="pgm-note-96" name="note" rows="4" maxlength="2000" required aria-describedby="pgm-note-96-hint"></textarea>#', $html ), 'a real label for a required textarea, described by the hint' );
ok( false !== strpos( $html, 'id="pgm-note-96-hint">Required. Amina Wanjiru gets this note by email and finds the article back in their drafts.</span>' ) && false === strpos( $html, 'aria-invalid' ), 'the hint says who gets the note; no error yet' );
ok( false !== strpos( $html, '<button type="submit" class="pgm-btn">Send back to Amina Wanjiru<span class="screen-reader-text"> “Tom &amp; Jerry”</span></button>' ), 'the submit button says what happens' );
$html = prospergenics_articles_send_back_form( $GLOBALS['_posts'][96], 'pga', '', true );
ok( 0 === strpos( $html, '<details class="pga-sendback" open>' ) && false === strpos( $html, 'pg_from' ), 'after a refused note: open (My articles: no pg_from, it returns to /articles/mine/)' );
ok( 1 === preg_match( '#<textarea id="pga-note-96" [^>]*required aria-describedby="pga-note-96-hint pga-note-96-err" aria-invalid="true" autofocus></textarea><span class="pga-field-error" id="pga-note-96-err">Write what the author should change: an article is only sent back with a note.</span>#', $html ), 'the textarea is invalid, tied to the error text and focused' );

$reset();
function prospergenics_manage_url( $view = 'overview', $args = array() ) { return add_query_arg( $args, 'https://pg.test/manage/' . $view . '/' ); }
ok( array( 'redirect', 'https://pg.test/manage/articles/?done=note_missing&article=96' ) === $send( array( 'pg_from' => 'manage', 'note' => ' ' ) ), 'from /manage/articles/: a refused note returns there, with the notice and the article only' );
ok( array( 'redirect', 'https://pg.test/manage/articles/?done=sent_back' ) === $send( array( 'pg_from' => 'manage', 'note' => $note ) ), 'and a sent-back article too (no /articles/mine/ filter)' );
$reset();
ok( array( 'redirect', 'https://pg.test/articles/mine/?status=review&done=sent_back' ) === $send( array( 'pg_from' => 'https://evil.example/', 'note' => $note ) ), 'any other pg_from returns to /articles/mine/ (no open redirect)' );
$_POST = array();

$views = file_get_contents( $theme . '/inc/articles/views/mine.php' );
ok( false !== strpos( $views, "\$pga_queue && prospergenics_articles_can_send_back( \$pga_post )" ) && false !== strpos( $views, "prospergenics_articles_send_back_form( \$pga_post, 'pga', '', (int) \$pga_post->ID === \$pga_noted )" ), '/articles/mine/: Send back in "Waiting for review", for staff' );
ok( false !== strpos( $views, "'draft' === \$pga_status ? prospergenics_articles_note( \$pga_post->ID ) : null" ) && false !== strpos( $views, "nl2br( esc_html( \$pga_note['note'] ) )" ), '/articles/mine/: the author sees the note on that draft, escaped' );
ok( false !== strpos( file_get_contents( $theme . '/assets/platform/articles.css' ), '.pga-sendback>summary' ) && false !== strpos( file_get_contents( $theme . '/assets/platform/manage.css' ), '.pgm-sendback>summary' ), 'both stylesheets style the form' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

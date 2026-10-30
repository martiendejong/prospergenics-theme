<?php
/**
 * Standalone tests for the community features of the Prospergenics Platform:
 *   - #6464 questions and discussion under lessons (inc/courses/discussion.php): body cleaning and
 *     length limits, the honeypot / form-time check, reply order (accepted answer first), the
 *     hooks that keep pg_question comments out of REST, feeds and the core comment form, nonces
 *     on every handler, privacy exporter/eraser, the one marked hook in the lesson template;
 *   - #6472 reporting and moderation (inc/profiles/moderation.php, inc/articles/hidden.php):
 *     report keys, who may report, the actions per type, the status a report gets, which article
 *     queries leave hidden articles out, the capability, suspension hooks, nonces + capability
 *     checks on the queue handlers, privacy exporter/eraser.
 *
 * Run with: php tests/test-platform-community.php
 */

$root  = dirname( __DIR__ );
$theme = $root . '/wp-theme/prospergenics-2026';

$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; }
	else         { echo "  FAIL  $msg\n"; $failures++; }
}

// ────────────────────── minimal WordPress stubs ──────────────────────
define( 'ABSPATH', sys_get_temp_dir() . '/pg-community-test/' );
define( 'DAY_IN_SECONDS', 86400 );
define( 'HOUR_IN_SECONDS', 3600 );
$GLOBALS['hooks'] = array();
function add_action( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function add_filter( $hook, $cb, $prio = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][] = $cb; }
function apply_filters( $hook, $value ) { return $value; }
function wp_hash( $s ) { return hash_hmac( 'md5', (string) $s, 'test-salt' ); }
function esc_html( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); }
// A small wp_kses(): allowed tags only, and of their attributes only the allowed ones.
function wp_kses( $html, $allowed ) {
	$html = preg_replace( '#<(script|style)[^>]*>.*?</\1>#is', '', (string) $html );
	$html = strip_tags( $html, '<' . implode( '><', array_keys( $allowed ) ) . '>' );
	return preg_replace_callback( '#<([a-z]+)([^>]*)>#i', function ( $m ) use ( $allowed ) {
		$tag   = strtolower( $m[1] );
		$attrs = '';
		if ( preg_match_all( '#([a-z-]+)="([^"]*)"#i', $m[2], $am, PREG_SET_ORDER ) ) {
			foreach ( $am as $a ) {
				if ( ! empty( $allowed[ $tag ][ strtolower( $a[1] ) ] ) ) {
					$attrs .= ' ' . strtolower( $a[1] ) . '="' . $a[2] . '"';
				}
			}
		}
		return '<' . $tag . $attrs . '>';
	}, $html );
}
class WP_Error {
	public $code;
	public function __construct( $code = '', $msg = '', $data = null ) { $this->code = $code; }
	public function get_error_code() { return $this->code; }
}

foreach ( array( 'inc/spam-protection.php', 'inc/courses/helpers.php', 'inc/courses/discussion.php', 'inc/articles/hidden.php', 'inc/profiles/moderation.php' ) as $f ) {
	ok( file_exists( $theme . '/' . $f ), "$f is in the generated theme" );
	require $theme . '/' . $f;
}

echo "\nLoaders\n";
ok( false !== strpos( file_get_contents( $theme . '/inc/platform-courses.php' ), "'discussion'" ), 'the courses loader requires discussion.php' );
ok( false !== strpos( file_get_contents( $theme . '/inc/platform-articles.php' ), "/articles/hidden.php'" ), 'the articles loader requires hidden.php' );
ok( false !== strpos( file_get_contents( $theme . '/inc/platform-profiles.php' ), "'moderation.php'" ), 'the profiles loader requires moderation.php' );

echo "\n#6464 discussion: cleaning and limits\n";
$c = prospergenics_courses_discussion_clean( 'Hi <script>alert(1)</script><b onclick="x()">bold</b> <a href="https://example.com" onclick="x()">link</a> <strong>yes</strong>', true );
ok( '' === $c['error'], 'a normal question is accepted' );
ok( false === strpos( $c['body'], '<script' ) && false === strpos( $c['body'], 'onclick' ) && false === strpos( $c['body'], '<b>' ), 'scripts, event handlers and unlisted tags are removed' );
ok( false !== strpos( $c['body'], '<a href="https://example.com">' ) && false !== strpos( $c['body'], '<strong>' ), 'links and simple emphasis stay' );
ok( 'short' === prospergenics_courses_discussion_clean( 'Help?', true )['error'], 'a question needs 10 characters' );
ok( '' === prospergenics_courses_discussion_clean( 'Thanks', false )['error'], 'a short reply is fine' );
ok( 'short' === prospergenics_courses_discussion_clean( '<p> </p>', false )['error'], 'an empty reply (only markup) is refused' );
ok( 'long' === prospergenics_courses_discussion_clean( str_repeat( 'a', 4001 ), true )['error'], 'more than 4,000 characters is refused' );
ok( '' === prospergenics_courses_discussion_clean( str_repeat( 'a', 4000 ), true )['error'], 'exactly 4,000 characters is fine' );
ok( 'short' === prospergenics_courses_discussion_clean( '<em></em><em></em><em></em><em></em>', true )['error'], 'markup does not count towards the length' );

echo "\n#6464 discussion: bot signals\n";
$now  = 1800000000;
$form = function ( $t ) { return array( 'pg_form_time' => $t, 'pg_form_token' => wp_hash( (string) $t ) ); };
ok( '' === prospergenics_courses_discussion_bot_reason( $form( $now - 30 ), $now ), 'a form filled in 30 seconds passes' );
ok( 'fast' === prospergenics_courses_discussion_bot_reason( $form( $now - 1 ), $now ), 'a form posted within 4 seconds is refused' );
ok( 'stale' === prospergenics_courses_discussion_bot_reason( $form( $now - 2 * DAY_IN_SECONDS ), $now ), 'a form older than a day is refused' );
ok( 'token' === prospergenics_courses_discussion_bot_reason( array( 'pg_form_time' => $now - 30, 'pg_form_token' => 'forged' ), $now ), 'a forged form time is refused' );
ok( 'token' === prospergenics_courses_discussion_bot_reason( array(), $now ), 'a post without the form fields is refused' );
ok( 'honeypot' === prospergenics_courses_discussion_bot_reason( $form( $now - 30 ) + array( PROSPERGENICS_HONEYPOT_FIELD => 'http://spam' ), $now ), 'a filled honeypot is refused' );

echo "\n#6464 discussion: reply order\n";
$r = function ( $id, $date ) { return (object) array( 'comment_ID' => $id, 'comment_date_gmt' => $date ); };
$ordered = prospergenics_courses_discussion_order_replies( array( $r( 5, '2026-01-03 10:00:00' ), $r( 3, '2026-01-01 10:00:00' ), $r( 4, '2026-01-02 10:00:00' ) ), 4 );
ok( array( 4, 3, 5 ) === array_map( function ( $x ) { return (int) $x->comment_ID; }, $ordered ), 'the accepted answer comes first, then oldest first' );
$ordered = prospergenics_courses_discussion_order_replies( array( $r( 5, '2026-01-03 10:00:00' ), $r( 3, '2026-01-01 10:00:00' ) ), 0 );
ok( array( 3, 5 ) === array_map( function ( $x ) { return (int) $x->comment_ID; }, $ordered ), 'without an accepted answer: oldest first' );

echo "\n#6464 discussion: kept out of everything else\n";
foreach ( array( 'pre_get_comments', 'comment_feed_where', 'rest_prepare_comment', 'comments_open', 'pings_open', 'prospergenics_courses_lesson_discussion', 'prospergenics_courses_discussion_posted', 'delete_user' ) as $h ) {
	ok( ! empty( $GLOBALS['hooks'][ $h ] ), "hooked: $h" );
}
$d = file_get_contents( $theme . '/inc/courses/discussion.php' );
ok( false !== strpos( $d, "'pg_courses_discussion' => true" ) && false !== strpos( $d, "type__not_in" ), 'every comment query but the module\'s own leaves pg_question out' );
foreach ( array( 'pg_courses_discuss_', 'pg_courses_discuss_accept_', 'pg_courses_discuss_delete_' ) as $n ) {
	ok( false !== strpos( $d, "check_admin_referer( '$n'" ), "handler checks the nonce {$n}…" );
}
ok( false !== strpos( $d, 'prospergenics_courses_discussion_can_access( $lesson_id, $user_id )' ) && false !== strpos( $d, "user_can( (int) \$user_id, 'edit_post', (int) \$course_id )" ), 'posting needs access to the lesson; accepting needs edit rights on the course' );
ok( false !== strpos( $d, "\$exporters['prospergenics-discussion']" ) && false !== strpos( $d, "\$erasers['prospergenics-discussion']" ), 'an exporter and an eraser are registered' );
ok( false !== strpos( $d, 'prospergenics_profiles_send_mail(' ), 'mail goes through the platform mail helper' );
$tpl = file_get_contents( $theme . '/single-pg_lesson.php' );
ok( 1 === substr_count( $tpl, 'discussion:start' ) && 1 === substr_count( $tpl, "do_action('prospergenics_courses_lesson_discussion'" ), 'the lesson template has one marked discussion hook' );
ok( false !== strpos( $tpl, "if (\$pgc_can) { do_action('prospergenics_courses_lesson_discussion'" ), 'the hook only runs for people who may open the lesson' );

echo "\n#6472 reports: keys, reasons, who may report\n";
ok( 'article:12' === prospergenics_profiles_report_key( 'article', 12 ), 'report key' );
ok( '' === prospergenics_profiles_report_key( 'user', 12 ) && '' === prospergenics_profiles_report_key( 'article', 0 ), 'no key for unknown types or id 0' );
ok( array( 'question', 7 ) === prospergenics_profiles_report_parse_key( 'question:7' ), 'parse a key' );
foreach ( array( 'article:0', 'article:12x', 'user:3', 'article:-1', '', 'profile:1:2' ) as $bad ) {
	ok( null === prospergenics_profiles_report_parse_key( $bad ), "refuse key '$bad'" );
}
ok( isset( prospergenics_profiles_report_reasons()['spam'], prospergenics_profiles_report_reasons()['other'] ), 'reasons include spam and something else' );
$all = array( 'logged_in' => true, 'verified' => true, 'suspended' => false, 'exists' => true, 'visible' => true, 'own' => false, 'already' => false, 'rate_ok' => true );
ok( '' === prospergenics_profiles_report_block( $all ), 'a verified member may report what they can see' );
ok( 'login' === prospergenics_profiles_report_block( array( 'logged_in' => false ) + $all ), 'visitors may not' );
ok( 'unverified' === prospergenics_profiles_report_block( array( 'verified' => false ) + $all ), 'unverified members may not' );
ok( 'unverified' === prospergenics_profiles_report_block( array( 'suspended' => true ) + $all ), 'suspended members may not' );
ok( 'not_found' === prospergenics_profiles_report_block( array( 'visible' => false ) + $all ), 'nobody reports what they cannot see' );
ok( 'own' === prospergenics_profiles_report_block( array( 'own' => true ) + $all ), 'nobody reports their own content' );
ok( 'already' === prospergenics_profiles_report_block( array( 'already' => true ) + $all ), 'once per item per member' );
ok( 'rate' === prospergenics_profiles_report_block( array( 'rate_ok' => false ) + $all ), 'rate limited' );

echo "\n#6472 queue actions\n";
ok( array( 'hide', 'remove', 'dismiss', 'warn', 'suspend' ) === array_keys( prospergenics_profiles_moderation_actions( 'article' ) ), 'articles: hide, remove, dismiss, warn, suspend' );
ok( array( 'hide', 'remove', 'dismiss', 'warn', 'suspend' ) === array_keys( prospergenics_profiles_moderation_actions( 'question' ) ), 'discussion posts: the same' );
ok( ! isset( prospergenics_profiles_moderation_actions( 'profile' )['remove'] ), 'profiles cannot be removed (hide or suspend instead)' );
ok( 'dismissed' === prospergenics_profiles_report_status_after( 'dismiss' ) && 'actioned' === prospergenics_profiles_report_status_after( 'hide' ), 'report status after an action' );

echo "\n#6472 hidden articles: which queries leave them out\n";
$f = array( 'is_admin' => false, 'is_ajax' => false, 'is_staff' => false, 'is_main_singular' => false, 'is_tax' => false, 'is_search' => false, 'author' => 0, 'user' => 0 );
ok( prospergenics_articles_hide_applies( array( 'pg_article' ), $f ), 'article listings, feeds, REST, sitemap' );
ok( prospergenics_articles_hide_applies( array( 'any' ), $f ), "post_type 'any'" );
ok( prospergenics_articles_hide_applies( array(), array( 'is_tax' => true ) + $f ), 'category, tag and series archives' );
ok( prospergenics_articles_hide_applies( array( '' ), array( 'is_search' => true ) + $f ), 'site search' );
ok( ! prospergenics_articles_hide_applies( array( 'post' ), $f ), 'blog posts are not touched' );
ok( ! prospergenics_articles_hide_applies( array(), $f ), 'a plain blog query is not touched' );
ok( ! prospergenics_articles_hide_applies( array( 'pg_article' ), array( 'is_staff' => true ) + $f ), 'staff see everything' );
ok( ! prospergenics_articles_hide_applies( array( 'pg_article' ), array( 'author' => 5, 'user' => 5 ) + $f ), "the author's own lists keep them" );
ok( prospergenics_articles_hide_applies( array( 'pg_article' ), array( 'author' => 5, 'user' => 6 ) + $f ), "someone else's author list does not" );
ok( ! prospergenics_articles_hide_applies( array( 'pg_article' ), array( 'is_main_singular' => true ) + $f ), 'the single page is decided in template_redirect (author and staff see it)' );
ok( ! prospergenics_articles_hide_applies( array( 'pg_article' ), array( 'is_admin' => true ) + $f ), 'wp-admin lists are left alone' );
ok( prospergenics_articles_hide_applies( array( 'pg_article' ), array( 'is_admin' => true, 'is_ajax' => true ) + $f ), 'admin-ajax requests are not wp-admin lists' );
$h = file_get_contents( $theme . '/inc/articles/hidden.php' );
ok( false !== strpos( $h, "add_action( 'pre_get_posts'" ) && false !== strpos( $h, "'compare' => 'NOT EXISTS'" ), 'hidden articles are excluded with a meta query in pre_get_posts' );
ok( false !== strpos( $h, "'rest_prepare_pg_article'" ) && false !== strpos( $h, 'set_404()' ), 'a single REST read and the page itself are 404s' );

echo "\n#6472 capability, suspension, handlers, privacy\n";
$cap = $GLOBALS['hooks']['user_has_cap'][0];
ok( ! empty( $cap( array( 'moderate_comments' => true, 'edit_others_posts' => true ) )['pg_moderate'] ), 'editors and administrators get pg_moderate' );
ok( empty( $cap( array( 'moderate_comments' => true ) )['pg_moderate'] ) && empty( $cap( array( 'edit_posts' => true ) )['pg_moderate'] ), 'authors, contributors and subscribers do not' );
ok( ! empty( $GLOBALS['hooks']['authenticate'] ) && ! empty( $GLOBALS['hooks']['determine_current_user'] ), 'suspension blocks password login and existing sessions' );
$m = file_get_contents( $theme . '/inc/profiles/moderation.php' );
ok( false !== strpos( $m, "check_admin_referer( 'pg_profiles_report_' . \$key )" ), 'reporting checks a nonce per item' );
ok( false !== strpos( $m, "check_admin_referer( 'pg_profiles_moderate_' . \$key )" ) && false !== strpos( $m, "check_admin_referer( 'pg_profiles_unsuspend_' . \$user_id )" ), 'queue actions check a nonce per item / member' );
ok( 3 <= substr_count( $m, 'current_user_can( PROSPERGENICS_MODERATION_CAP )' ), 'queue page and handlers check pg_moderate' );
ok( false !== strpos( $m, "user_can( \$user_id, PROSPERGENICS_MODERATION_CAP ) && ! user_can( \$user_id, 'edit_users' )" ), 'staff cannot be suspended' );
ok( false !== strpos( $m, "WP_Session_Tokens::get_instance( (int) \$user_id )->destroy_all()" ), 'suspending ends the sessions' );
ok( false !== strpos( $m, "'_pg_profiles_suspended_hid'" ), 'unsuspending only shows a profile again when the suspension hid it' );
ok( false !== strpos( $m, "\$exporters['prospergenics-moderation']" ) && false !== strpos( $m, "\$erasers['prospergenics-moderation']" ), 'an exporter and an eraser are registered' );
ok( false !== strpos( $m, "'items_retained' => \$suspended" ), 'erasing data keeps a suspension' );
$users = file_get_contents( $theme . '/inc/profiles/data.php' );
ok( false !== strpos( $users, "user_can( (int) \$viewer_id, 'pg_moderate' )" ), 'moderators see hidden profiles' );
foreach ( array( 'single-pg_article.php' => "'article'", 'pg-profile.php' => "'profile'" ) as $t => $type ) {
	ok( false !== strpos( file_get_contents( $theme . '/' . $t ), "prospergenics_profiles_report_html( $type" ), "$t shows the Report control" );
}
ok( file_exists( $theme . '/assets/platform/community.css' ), 'assets/platform/community.css shipped' );

echo "\n" . ( $failures ? "$failures FAILED\n" : "All community tests passed\n" );
exit( $failures ? 1 : 0 );

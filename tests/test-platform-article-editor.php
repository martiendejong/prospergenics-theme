<?php
/**
 * Standalone test for the front-end article editor (JengoWork #6459):
 * wp-theme/prospergenics-2026/inc/articles/editor.php, views/write.php, assets/platform/article-editor.js.
 *
 * Checks the rules that do not need a database (status per role, 5 MB image limit, schedule date,
 * tag cleaning) and the wiring (capability + nonce on the REST routes, wp_kses_post on the body,
 * autosave every 30 s, unsaved-changes warning, links from My articles).
 *
 * Run with: php tests/test-platform-article-editor.php
 */
$theme = dirname( __DIR__ ) . '/wp-theme/prospergenics-2026';
$failures = 0;
function ok( $cond, $msg ) {
	global $failures;
	if ( $cond ) { echo "  PASS  $msg\n"; } else { echo "  FAIL  $msg\n"; $failures++; }
}

define( 'ABSPATH', __DIR__ . '/' );
function add_action( $h, $cb, $p = 10, $a = 1 ) { return true; }
function add_filter( $h, $cb, $p = 10, $a = 1 ) { return true; }
function sanitize_text_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function sanitize_textarea_field( $s ) { return trim( strip_tags( (string) $s ) ); }
function wp_kses_post( $s ) { return preg_replace( '#<script\b.*?</script>#is', '', (string) $s ); }
require $theme . '/inc/articles/editor.php';

echo "Status per action and role\n";
ok( 'draft' === prospergenics_articles_editor_target_status( 'save', 'draft', false ), 'saving a new article keeps it a draft' );
ok( 'pending' === prospergenics_articles_editor_target_status( 'save', 'pending', false ), 'saving a pending article keeps it pending' );
ok( 'pending' === prospergenics_articles_editor_target_status( 'submit', 'draft', false ), 'a contributor submitting goes to pending review' );
ok( 'publish' === prospergenics_articles_editor_target_status( 'submit', 'publish', true ), 'submit never un-publishes a live article' );
ok( false === prospergenics_articles_editor_target_status( 'publish', 'draft', false ), 'a contributor cannot publish' );
ok( false === prospergenics_articles_editor_target_status( 'schedule', 'draft', false ), 'a contributor cannot schedule' );
ok( 'publish' === prospergenics_articles_editor_target_status( 'publish', 'pending', true ), 'an author can publish' );
ok( 'future' === prospergenics_articles_editor_target_status( 'schedule', 'draft', true ), 'an author can schedule' );
ok( false === prospergenics_articles_editor_target_status( 'bogus', 'draft', true ), 'unknown actions are refused' );

echo "Images\n";
ok( '' === prospergenics_articles_editor_image_error( 1024, 'a.jpg' ), 'a small jpg is fine' );
ok( '' === prospergenics_articles_editor_image_error( 5242880, 'A.PNG' ), 'exactly 5 MB is fine' );
ok( '' !== prospergenics_articles_editor_image_error( 5242881, 'a.jpg' ), 'over 5 MB is refused' );
ok( '' !== prospergenics_articles_editor_image_error( 0, 'a.jpg' ), 'an empty file is refused' );
ok( '' !== prospergenics_articles_editor_image_error( 100, 'a.svg' ), 'svg is refused' );
ok( '' !== prospergenics_articles_editor_image_error( 100, 'a.php' ), 'php is refused' );
ok( '' !== prospergenics_articles_editor_image_error( 100, 'a.pdf' ), 'pdf is refused (images only)' );
ok( 5242880 === PROSPERGENICS_ARTICLES_EDITOR_MAX_IMAGE, 'the limit constant is 5 MB' );

echo "Cleaning\n";
ok( false === strpos( prospergenics_articles_editor_clean_content( '<p>Hi</p><script>alert(1)</script>' ), 'script' ), 'scripts are removed from the body' );
ok( 300 === strlen( prospergenics_articles_editor_clean_excerpt( str_repeat( 'a', 500 ) ) ), 'the excerpt is cut at 300' );
ok( array( 'one', 'Two' ) === prospergenics_articles_editor_clean_tags( ' one, Two,,ONE ' ), 'tags are trimmed and unique' );
ok( 10 === count( prospergenics_articles_editor_clean_tags( 'a,b,c,d,e,f,g,h,i,j,k,l' ) ), 'at most 10 tags' );
ok( '2026-12-01 09:30:00' === prospergenics_articles_editor_parse_date( '2026-12-01T09:30' ), 'a schedule date is parsed' );
ok( '' === prospergenics_articles_editor_parse_date( '2026-02-31T09:30' ), 'an impossible date is refused' );
ok( '' === prospergenics_articles_editor_parse_date( 'tomorrow' ), 'free text is refused' );
ok( 30000 === PROSPERGENICS_ARTICLES_EDITOR_AUTOSAVE_MS, 'autosave every 30 seconds' );

echo "Wiring\n";
$php  = file_get_contents( $theme . '/inc/articles/editor.php' );
$js   = file_get_contents( $theme . '/assets/platform/article-editor.js' );
$view = file_get_contents( $theme . '/inc/articles/views/write.php' );
$mine = file_get_contents( $theme . '/inc/articles/views/mine.php' );
$content = file_get_contents( $theme . '/inc/articles/content.php' );
ok( 2 === substr_count( $php, "'permission_callback' => 'prospergenics_articles_editor_permission'" ), 'both routes check the capability' );
ok( false !== strpos( $php, "current_user_can( 'edit_post', \$id )" ), 'editing an existing article needs edit_post' );
ok( false !== strpos( $php, 'return wp_kses_post( (string) $html )' ) && false !== strpos( $php, 'prospergenics_articles_editor_clean_content( $req->get_param' ), 'the body goes through wp_kses_post before saving' );
ok( false !== strpos( $php, "wp_create_nonce( 'wp_rest' )" ) && false !== strpos( $js, 'X-WP-Nonce' ), 'requests carry the REST nonce' );
ok( false !== strpos( $php, 'wp_check_filetype_and_ext' ) && false !== strpos( $php, 'getimagesize' ), 'uploads are checked by content, not only the name' );
ok( false !== strpos( $js, 'beforeunload' ), 'leaving with unsaved changes is warned about' );
ok( false !== strpos( $js, 'setInterval' ) && false !== strpos( $js, 'autosaveMs' ), 'the script autosaves on an interval' );
ok( false !== strpos( $js, 'maxImage' ), 'the script checks the image size before uploading' );
ok( false !== strpos( $view, 'Submit for review' ) && false !== strpos( $view, 'Publish' ), 'the page offers submit for review and publish' );
ok( false !== strpos( $view, 'publish_pg_articles' ), 'publish buttons only for who may publish' );
ok( false !== strpos( $mine, 'prospergenics_articles_write_url' ) && false === strpos( $mine, 'post-new.php' ), 'My articles links to the front-end editor' );
ok( 1 === preg_match( "/function prospergenics_articles_reserved_slugs\(\) \{\s*return array\([^)]*'write'/", $content ), '"write" cannot be an article slug' );

echo $failures ? "\n$failures FAILED\n" : "\nAll assertions passed.\n";
exit( $failures ? 1 : 0 );

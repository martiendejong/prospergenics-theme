<?php
/**
 * Courses track: lessons on slow and expensive connections (card #6469).
 *
 * Many learners use costly mobile data, so a lesson page sends its text first and nothing heavy
 * until asked for:
 *   - Videos (the lesson's video URL, YouTube/Vimeo embeds and [video] files in the lesson text)
 *     become a click-to-load facade: a small poster served from this site, a play link and a
 *     note that playing uses data. No iframe, player script or video byte loads until the
 *     learner taps play, and the player never autoplays. Without JavaScript the play link opens
 *     the video on YouTube/Vimeo (or the file) instead.
 *   - Posters: YouTube/Vimeo thumbnails are fetched once in the background (WP-Cron) and kept in
 *     uploads/pg-courses-posters/, so the page never contacts YouTube or Vimeo before play.
 *     Until a poster exists the facade shows the branded pattern (no bytes).
 *   - Images in the lesson text are lazy-loaded, decoded asynchronously and get width/height
 *     when they come from the media library (no layout shift).
 *   - Lessons without blocks do not load the block library CSS or the global styles (about
 *     125 KB uncompressed); the wizard script only loads on the wizard (routing.php).
 *   - The notes PDF (notes.php) lets learners keep the lesson offline.
 */

defined( 'ABSPATH' ) || exit;

/** Upload sub-folder for the cached video posters. */
const PROSPERGENICS_COURSES_POSTER_DIR = 'pg-courses-posters';

/**
 * Embed, page and label for a video source (from prospergenics_courses_video_source()).
 *
 * @return array{kind:string,src:string,href:string,label:string,type:string}
 */
function prospergenics_courses_video_player( array $v ) {
	if ( 'youtube' === $v['provider'] ) {
		return array( 'kind' => 'iframe', 'src' => 'https://www.youtube-nocookie.com/embed/' . rawurlencode( $v['id'] ), 'href' => 'https://www.youtube.com/watch?v=' . rawurlencode( $v['id'] ), 'label' => 'YouTube', 'type' => '' );
	}
	if ( 'vimeo' === $v['provider'] ) {
		return array( 'kind' => 'iframe', 'src' => 'https://player.vimeo.com/video/' . rawurlencode( $v['id'] ) . '?dnt=1', 'href' => 'https://vimeo.com/' . rawurlencode( $v['id'] ), 'label' => 'Vimeo', 'type' => '' );
	}
	return array( 'kind' => 'video', 'src' => $v['id'], 'href' => $v['id'], 'label' => 'file', 'type' => isset( $v['type'] ) ? $v['type'] : '' );
}

/**
 * The click-to-load facade for a video.
 *
 * @param array       $v      Video source (provider + id), see prospergenics_courses_video_source().
 * @param string      $title  Lesson title (accessible name of the player).
 * @param array|null  $poster [url, width, height] or null for the branded pattern.
 * @param string      $size   Human-readable file size for self-hosted files ('' when unknown).
 */
function prospergenics_courses_video_facade_html( array $v, $title, $poster = null, $size = '' ) {
	$p    = prospergenics_courses_video_player( $v );
	$esc  = function ( $s ) { return htmlspecialchars( (string) $s, ENT_QUOTES, 'UTF-8' ); };
	$note = 'file' === $p['label']
		? 'Video file' . ( '' !== $size ? ' (' . $size . ')' : '' ) . '. Nothing downloads until you press play.'
		: 'Video on ' . $p['label'] . '. Nothing loads until you press play; playing streams from ' . $p['label'] . ' and uses data.';
	$html  = '<figure class="pgc-facade">';
	$html .= '<div class="pgc-video pgc-facade-box" data-pgc-kind="' . $esc( $p['kind'] ) . '" data-pgc-src="' . $esc( $p['src'] ) . '" data-pgc-type="' . $esc( $p['type'] ) . '" data-pgc-title="' . $esc( $title ) . '">';
	if ( $poster ) {
		$html .= '<img class="pgc-facade-poster" src="' . $esc( $poster[0] ) . '" width="' . (int) $poster[1] . '" height="' . (int) $poster[2] . '" alt="" loading="lazy" decoding="async" />';
	} else {
		$html .= '<span class="pgc-facade-poster pg-pattern pg-pattern--2" aria-hidden="true"></span>';
	}
	$html .= '<a class="pgc-facade-play" href="' . $esc( $p['href'] ) . '" data-pgc-play rel="noopener">'
		. '<svg viewBox="0 0 48 48" width="48" height="48" aria-hidden="true" focusable="false"><circle cx="24" cy="24" r="23" fill="currentColor"/><path d="M19 15v18l15-9z" fill="#fff"/></svg>'
		. '<span class="pgc-facade-label">Play video<span class="screen-reader-text">: ' . $esc( $title ) . '</span></span></a>';
	$html .= '</div><figcaption class="pgc-facade-note">' . $esc( $note ) . '</figcaption></figure>';
	return $html;
}

/** Facade for a video URL, with its cached poster and (for files on this site) its size. */
function prospergenics_courses_video_facade( $url, $title ) {
	$v = prospergenics_courses_video_source( $url );
	if ( ! $v ) {
		return '';
	}
	$size = '';
	if ( 'file' === $v['provider'] ) {
		$att  = attachment_url_to_postid( $v['id'] );
		$file = $att ? get_attached_file( $att ) : '';
		$size = $file && is_readable( $file ) ? size_format( filesize( $file ), 0 ) : '';
	}
	return prospergenics_courses_video_facade_html( $v, $title, prospergenics_courses_video_poster( $v ), $size );
}

/** A lesson's own video URL (meta), or '' when it has none (or no longer a valid one). */
function prospergenics_courses_lesson_video_link( $lesson ) {
	$url = (string) get_post_meta( is_object( $lesson ) ? $lesson->ID : (int) $lesson, '_pg_courses_video_url', true );
	$v   = prospergenics_courses_video_source( $url );
	return $v ? prospergenics_courses_video_player( $v )['href'] : '';
}

/* ------------------------------------------------------------------ posters (cached locally) */

/** Where a provider's poster is (or would be) stored: [path, url]. */
function prospergenics_courses_poster_file( array $v ) {
	$up   = wp_upload_dir( null, false );
	$name = PROSPERGENICS_COURSES_POSTER_DIR . '/' . $v['provider'] . '-' . preg_replace( '/[^A-Za-z0-9_-]/', '', $v['id'] ) . '.jpg';
	return array( trailingslashit( $up['basedir'] ) . $name, trailingslashit( $up['baseurl'] ) . $name );
}

/**
 * Poster for a video: [url, width, height], or null (the facade then shows the pattern).
 * YouTube/Vimeo posters are fetched in the background the first time they are needed.
 */
function prospergenics_courses_video_poster( array $v ) {
	if ( 'file' === $v['provider'] ) {
		$att   = attachment_url_to_postid( $v['id'] );
		$thumb = $att ? get_post_thumbnail_id( $att ) : 0;
		$src   = $thumb ? wp_get_attachment_image_src( $thumb, 'medium_large' ) : false;
		return $src ? array( $src[0], (int) $src[1], (int) $src[2] ) : null;
	}
	list( $path, $url ) = prospergenics_courses_poster_file( $v );
	if ( is_readable( $path ) ) {
		$dim = @getimagesize( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a broken file just means no poster
		return $dim ? array( $url . '?v=' . filemtime( $path ), (int) $dim[0], (int) $dim[1] ) : null;
	}
	$args = array( $v['provider'], $v['id'] );
	if ( ! get_transient( 'pg_courses_poster_fail_' . md5( implode( ':', $args ) ) ) && ! wp_next_scheduled( 'prospergenics_courses_fetch_poster', $args ) ) {
		wp_schedule_single_event( time(), 'prospergenics_courses_fetch_poster', $args );
	}
	return null;
}

/** Fetch and store a YouTube/Vimeo poster (WP-Cron). Failures are not retried for a day. */
function prospergenics_courses_fetch_poster( $provider, $id ) {
	$v = array( 'provider' => (string) $provider, 'id' => (string) $id );
	if ( ! in_array( $v['provider'], array( 'youtube', 'vimeo' ), true ) || ! preg_match( '/^[A-Za-z0-9_-]{4,20}$/', $v['id'] ) ) {
		return false;
	}
	$fail = function () use ( $v ) {
		set_transient( 'pg_courses_poster_fail_' . md5( $v['provider'] . ':' . $v['id'] ), 1, DAY_IN_SECONDS );
		return false;
	};
	if ( 'youtube' === $v['provider'] ) {
		$src = 'https://i.ytimg.com/vi/' . rawurlencode( $v['id'] ) . '/mqdefault.jpg'; // 320×180, ~10 KB
	} else {
		$res  = wp_safe_remote_get( 'https://vimeo.com/api/oembed.json?width=480&url=' . rawurlencode( 'https://vimeo.com/' . $v['id'] ), array( 'timeout' => 8 ) );
		$data = is_wp_error( $res ) ? null : json_decode( wp_remote_retrieve_body( $res ), true );
		$src  = is_array( $data ) && ! empty( $data['thumbnail_url'] ) ? (string) $data['thumbnail_url'] : '';
		if ( ! preg_match( '#^https://i\.vimeocdn\.com/#', $src ) ) {
			return $fail();
		}
	}
	$res  = wp_safe_remote_get( $src, array( 'timeout' => 8, 'limit_response_size' => 300 * KB_IN_BYTES ) );
	$body = is_wp_error( $res ) || 200 !== (int) wp_remote_retrieve_response_code( $res ) ? '' : wp_remote_retrieve_body( $res );
	$dim  = '' !== $body ? @getimagesizefromstring( $body ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors
	if ( ! $dim || IMAGETYPE_JPEG !== $dim[2] || $dim[0] > 1280 ) {
		return $fail();
	}
	list( $path ) = prospergenics_courses_poster_file( $v );
	if ( ! wp_mkdir_p( dirname( $path ) ) || false === file_put_contents( $path, $body ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
		return $fail();
	}
	return true;
}
add_action( 'prospergenics_courses_fetch_poster', 'prospergenics_courses_fetch_poster', 10, 2 );

// Saving a lesson with a video queues its poster, so the first learner already gets it.
add_action( 'save_post_pg_lesson', function ( $post_id, $post ) {
	if ( wp_is_post_revision( $post_id ) || 'auto-draft' === $post->post_status ) {
		return;
	}
	$v = prospergenics_courses_video_source( (string) get_post_meta( $post_id, '_pg_courses_video_url', true ) );
	if ( $v && 'file' !== $v['provider'] ) {
		prospergenics_courses_video_poster( $v );
	}
}, 30, 2 );
// The wizard saves the video URL after the post: queue the poster then too.
$prospergenics_courses_queue_poster = function ( $meta_id, $post_id, $key, $value ) {
	if ( '_pg_courses_video_url' === $key && 'pg_lesson' === get_post_type( $post_id ) ) {
		$v = prospergenics_courses_video_source( (string) $value );
		if ( $v && 'file' !== $v['provider'] ) {
			prospergenics_courses_video_poster( $v );
		}
	}
};
add_action( 'added_post_meta', $prospergenics_courses_queue_poster, 10, 4 );
add_action( 'updated_post_meta', $prospergenics_courses_queue_poster, 10, 4 );
unset( $prospergenics_courses_queue_poster );

/* ------------------------------------------------------------------ the lesson text */

/**
 * Make lesson HTML light: YouTube/Vimeo iframes become facades, <video> elements preload
 * nothing, images are lazy and async. $dims( $src ) may return [width, height] for an image.
 */
function prospergenics_courses_lite_html( $html, $title, $dims = null ) {
	$html = (string) $html;
	// Embedded players (oEmbed or pasted iframes).
	$html = preg_replace_callback( '#<iframe\b[^>]*?\ssrc=(["\'])(.*?)\1[^>]*>\s*</iframe>#is', function ( $m ) use ( $title ) {
		$v = prospergenics_courses_parse_video_url( html_entity_decode( $m[2], ENT_QUOTES, 'UTF-8' ) );
		if ( ! $v ) {
			return $m[0];
		}
		$poster = function_exists( 'wp_upload_dir' ) ? prospergenics_courses_video_poster( $v ) : null;
		return prospergenics_courses_video_facade_html( $v, $title, $poster );
	}, $html );
	// Raw <video> elements: no preloading.
	$html = preg_replace_callback( '#<video\b[^>]*>#i', function ( $m ) {
		$tag = preg_replace( '#\s(preload|autoplay)(=(["\']).*?\3|=\S+)?(?=[\s>/])#i', '', $m[0] );
		return preg_replace( '#^<video\b#i', '<video preload="none"', $tag );
	}, $html );
	// Images: lazy, async, and sized when we know the size.
	$html = preg_replace_callback( '#<img\b[^>]*>#i', function ( $m ) use ( $dims ) {
		$tag = $m[0];
		if ( false !== strpos( $tag, 'pgc-facade-poster' ) ) {
			return $tag;
		}
		$add = '';
		if ( ! preg_match( '#\sloading=#i', $tag ) ) {
			$add .= ' loading="lazy"';
		}
		if ( ! preg_match( '#\sdecoding=#i', $tag ) ) {
			$add .= ' decoding="async"';
		}
		if ( $dims && ( ! preg_match( '#\swidth=#i', $tag ) || ! preg_match( '#\sheight=#i', $tag ) ) && preg_match( '#\ssrc=(["\'])(.*?)\1#i', $tag, $s ) ) {
			$wh = call_user_func( $dims, html_entity_decode( $s[2], ENT_QUOTES, 'UTF-8' ) );
			if ( $wh ) {
				$add .= ' width="' . (int) $wh[0] . '" height="' . (int) $wh[1] . '"';
			}
		}
		return '' === $add ? $tag : preg_replace( '#^<img\b#i', '<img' . $add, $tag );
	}, $html );
	return $html;
}

/** [width, height] of a media library image by URL (any generated size), or null. */
function prospergenics_courses_image_dims( $src ) {
	$id = attachment_url_to_postid( preg_replace( '/-\d+x\d+(?=\.[a-z]{3,4}$)/i', '', $src ) );
	if ( ! $id ) {
		return null;
	}
	$meta = wp_get_attachment_metadata( $id );
	if ( ! is_array( $meta ) || empty( $meta['width'] ) ) {
		return null;
	}
	$file = wp_basename( (string) parse_url( $src, PHP_URL_PATH ) );
	if ( ! empty( $meta['sizes'] ) ) {
		foreach ( $meta['sizes'] as $size ) {
			if ( isset( $size['file'] ) && $size['file'] === $file ) {
				return array( (int) $size['width'], (int) $size['height'] );
			}
		}
	}
	return array( (int) $meta['width'], (int) $meta['height'] );
}

/** Whether the_content is being rendered for the lesson page itself (not a feed, REST or the notes). */
function prospergenics_courses_is_lesson_render() {
	return empty( $GLOBALS['prospergenics_courses_rendering_notes'] ) && ! is_admin() && is_singular( 'pg_lesson' ) && in_the_loop();
}

add_filter( 'the_content', function ( $html ) {
	return prospergenics_courses_is_lesson_render() ? prospergenics_courses_lite_html( $html, get_the_title(), 'prospergenics_courses_image_dims' ) : $html;
}, 20 );

// [video] shortcodes in a lesson: the facade instead of the MediaElement player (~100 KB of script).
add_filter( 'wp_video_shortcode_override', function ( $html, $attr ) {
	if ( ! prospergenics_courses_is_lesson_render() ) {
		return $html;
	}
	$src = '';
	foreach ( array( 'src', 'mp4', 'm4v', 'webm', 'ogv' ) as $k ) {
		if ( ! empty( $attr[ $k ] ) ) {
			$src = $attr[ $k ];
			break;
		}
	}
	$v = prospergenics_courses_video_source( $src );
	if ( ! $v && preg_match( '#^https://#i', $src ) ) {
		// written by an editor into the lesson text: any https file
		$v = array( 'provider' => 'file', 'id' => esc_url_raw( $src ), 'type' => '' );
	}
	return $v ? prospergenics_courses_video_facade_html( $v, get_the_title(), null ) : $html;
}, 10, 2 );

// Lessons without blocks do not need the block library CSS or the global styles.
add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_singular( 'pg_lesson' ) || has_blocks( get_queried_object() ) ) {
		return;
	}
	foreach ( array( 'wp-block-library', 'wp-block-library-theme', 'global-styles', 'classic-theme-styles' ) as $handle ) {
		wp_dequeue_style( $handle );
	}
}, 100 );

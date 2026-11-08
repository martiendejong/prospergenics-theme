<?php
/**
 * "See it work" on the tool pages (/tools/<slug>/, card #7218).
 *
 * Lesiamon wants visitors to see a tool working. Only real captures of the real tool are allowed, so
 * the theme shows nothing it made itself: Martien and Jengo deliver a folder per tool,
 *     assets/tool-demos/<slug>/demo.json  + step-N.webp screenshots and/or demo.webm/.mp4, poster, demo.vtt
 * in the shape of the card's spec ("Tool demo files"), and the tool page picks it up with no code change.
 *
 *   - prospergenics_2026_tool_demo_load()      reads the folder (the only part that touches the disk),
 *   - prospergenics_2026_tool_demo_validate()  checks the manifest strictly (pure): plain file names
 *     that exist in the folder, numbers that are numbers, a cursor inside its image, capped text;
 *     a broken step is left out, and a manifest with nothing usable left gives no demo at all,
 *   - prospergenics_2026_tool_demo_html()      renders the section (pure, everything escaped),
 *   - prospergenics_2026_listings_add_tool_demo() puts it in the plugin page, before "← All tools"
 *     (called from the takeover in inc/site-chrome.php, which prints the section's CSS and script).
 * No folder, or nothing valid in it: the page stays exactly as the plugin made it.
 *
 * CoachOS runs this site's chat, so /tools/coachos/ also gets "Try it live": the same CoachOS embed as
 * the homepage (inc/coach-context.php), opened by a button on the page instead of the homepage orb.
 */

defined( 'ABSPATH' ) || exit;

/** The trigger selector of the "Try it live" button (embed.js data-trigger). */
const PROSPERGENICS_2026_TRY_LIVE_TRIGGER = '.pg-coach-try';

/** Limits from the spec, plus caps for the text the page shows. */
function prospergenics_2026_tool_demo_limits() {
    return array(
        'steps'       => 6,           // step-1 … step-6
        'caption'     => 120,         // characters
        'alt'         => 250,
        'title'       => 80,
        'source'      => 140,
        'image_bytes' => 250 * 1024,  // screenshots and the poster
        'video_bytes' => 4 * 1024 * 1024,
        'vtt_bytes'   => 64 * 1024,
        'json_bytes'  => 64 * 1024,
        'max_side'    => 4000,        // pixels
    );
}

/** The tool slug of a /tools/<slug>/ path, or '' for any other page (pure). */
function prospergenics_2026_tool_demo_slug_for_path( $path ) {
    $path = '/' . trim( (string) $path, '/' ) . '/';
    return preg_match( '#^/tools/([a-z0-9][a-z0-9-]{0,62})/$#', $path, $m ) ? $m[1] : '';
}

/** Whether $name is a plain file name as the spec allows: lowercase letters, digits, - and . only (no path, no URL). */
function prospergenics_2026_tool_demo_is_file_name( $name ) {
    return is_string( $name ) && strlen( $name ) <= 64 && 1 === preg_match( '/^[a-z0-9][a-z0-9.-]*$/D', $name );
}

/**
 * One line of manifest text for the page (pure): strings only, valid UTF-8, no control characters,
 * whitespace collapsed, at most $max characters (cut at a word, with "…"). Escaped when printed.
 */
function prospergenics_2026_tool_demo_text( $value, $max ) {
    if ( ! is_string( $value ) ) {
        return '';
    }
    $text = preg_replace( '/[\x00-\x1F\x7F\x{2028}\x{2029}]+/u', ' ', $value );
    if ( null === $text ) {
        return ''; // not valid UTF-8
    }
    $text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
    if ( mb_strlen( $text, 'UTF-8' ) <= $max ) {
        return $text;
    }
    $cut  = mb_substr( $text, 0, $max - 1, 'UTF-8' );
    $word = (string) preg_replace( '/\s+\S*$/u', '', $cut ); // end at a whole word, unless that loses too much
    if ( mb_strlen( $word, 'UTF-8' ) > $max / 2 ) {
        $cut = $word;
    }
    return rtrim( $cut, " ,.;:-" ) . '…';
}

/** A JSON number (not a numeric string, not true/false). */
function prospergenics_2026_tool_demo_is_number( $v ) {
    return ( is_int( $v ) || is_float( $v ) ) && is_finite( (float) $v );
}

/**
 * Check one file named in the manifest against what is in the folder (pure).
 *
 * @param mixed $name  The manifest value.
 * @param array $exts  Allowed extensions.
 * @param int   $bytes Largest allowed size.
 * @param array $files What the folder holds: name => array( 'bytes' => int, 'width' => ?int, 'height' => ?int ).
 * @return string '' when fine, else what is wrong.
 */
function prospergenics_2026_tool_demo_file_problem( $name, array $exts, $bytes, array $files ) {
    if ( ! prospergenics_2026_tool_demo_is_file_name( $name ) ) {
        return 'is not a plain file name (lowercase letters, digits, - and . only; no paths or URLs)';
    }
    if ( ! in_array( strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ), $exts, true ) ) {
        return 'must be a .' . implode( ' or .', $exts ) . ' file';
    }
    if ( ! isset( $files[ $name ] ) ) {
        return 'is not in the folder';
    }
    if ( $files[ $name ]['bytes'] > $bytes ) {
        return 'is larger than ' . round( $bytes / 1024 ) . ' KB';
    }
    return '';
}

/**
 * Validate a decoded demo.json strictly (pure).
 *
 * @param mixed  $data  json_decode( demo.json, true ).
 * @param string $slug  The folder (tool) slug; the manifest's "tool" must say the same.
 * @param array  $files What the folder holds: name => array( 'bytes' => int, 'width' => ?int, 'height' => ?int )
 *                      (width/height for images that could be read, else null).
 * @return array{demo:?array,errors:string[]} demo is null when nothing usable is left.
 */
function prospergenics_2026_tool_demo_validate( $data, $slug, array $files ) {
    $lim    = prospergenics_2026_tool_demo_limits();
    $errors = array();
    $images = array( 'webp', 'png', 'jpg', 'jpeg' );
    $none   = function ( $why ) use ( &$errors ) {
        $errors[] = $why;
        return array( 'demo' => null, 'errors' => $errors );
    };

    if ( ! is_array( $data ) || ( $data && array_keys( $data ) === range( 0, count( $data ) - 1 ) ) ) {
        return $none( 'demo.json is not a JSON object' );
    }
    if ( ! isset( $data['tool'] ) || $data['tool'] !== $slug ) {
        return $none( '"tool" must be "' . $slug . '", the name of the folder' );
    }

    $steps = array();
    if ( isset( $data['steps'] ) ) {
        if ( ! is_array( $data['steps'] ) || ( $data['steps'] && array_keys( $data['steps'] ) !== range( 0, count( $data['steps'] ) - 1 ) ) ) {
            $errors[] = '"steps" must be a list';
            $data['steps'] = array();
        }
        foreach ( $data['steps'] as $i => $s ) {
            $n = 'step ' . ( $i + 1 );
            if ( count( $steps ) >= $lim['steps'] ) {
                $errors[] = 'more than ' . $lim['steps'] . ' steps: ' . $n . ' and later are left out';
                break;
            }
            if ( ! is_array( $s ) ) {
                $errors[] = "$n is not an object; left out";
                continue;
            }
            $img     = isset( $s['image'] ) ? $s['image'] : null;
            $problem = prospergenics_2026_tool_demo_file_problem( $img, $images, $lim['image_bytes'], $files );
            if ( '' !== $problem ) {
                $errors[] = "$n: the image $problem; left out";
                continue;
            }
            $w = isset( $s['width'] ) ? $s['width'] : null;
            $h = isset( $s['height'] ) ? $s['height'] : null;
            if ( ! is_int( $w ) || ! is_int( $h ) || $w < 1 || $h < 1 || $w > $lim['max_side'] || $h > $lim['max_side'] ) {
                $errors[] = "$n: width and height must be whole numbers of pixels; left out";
                continue;
            }
            if ( $files[ $img ]['width'] !== $w || $files[ $img ]['height'] !== $h ) {
                $errors[] = "$n: width and height do not match the image ({$files[ $img ]['width']}×{$files[ $img ]['height']}); left out";
                continue;
            }
            if ( $steps && ( $w !== $steps[0]['width'] || $h !== $steps[0]['height'] ) ) {
                $errors[] = "$n: not the same size as the first step; left out";
                continue;
            }
            $alt     = prospergenics_2026_tool_demo_text( isset( $s['alt'] ) ? $s['alt'] : null, $lim['alt'] );
            $caption = prospergenics_2026_tool_demo_text( isset( $s['caption'] ) ? $s['caption'] : null, $lim['caption'] );
            if ( '' === $alt || '' === $caption ) {
                $errors[] = "$n: needs an alt text and a caption; left out";
                continue;
            }
            $action = isset( $s['action'] ) ? $s['action'] : null;
            if ( null !== $action && ! in_array( $action, array( 'click', 'type', 'look' ), true ) ) {
                $errors[] = "$n: action must be click, type or look; left out";
                continue;
            }
            $x = null;
            $y = null;
            if ( array_key_exists( 'cursor', $s ) ) {
                $c = $s['cursor'];
                if ( ! is_array( $c ) || ! isset( $c['x'], $c['y'] ) || ! prospergenics_2026_tool_demo_is_number( $c['x'] ) || ! prospergenics_2026_tool_demo_is_number( $c['y'] ) ) {
                    $errors[] = "$n: the cursor needs numbers x and y; left out";
                    continue;
                }
                if ( $c['x'] < 0 || $c['y'] < 0 || $c['x'] > $w || $c['y'] > $h ) {
                    $errors[] = "$n: the cursor ({$c['x']}, {$c['y']}) is outside the {$w}×{$h} image; left out";
                    continue;
                }
                $x = (float) $c['x'];
                $y = (float) $c['y'];
            }
            if ( null === $action ) {
                $action = null === $x ? 'look' : 'click';
            }
            if ( 'look' !== $action && null === $x ) {
                $errors[] = "$n: a $action step needs a cursor; left out";
                continue;
            }
            if ( 'look' === $action ) {
                $x = null; // "look" shows the screen only, no cursor
                $y = null;
            }
            $steps[] = array( 'image' => $img, 'width' => $w, 'height' => $h, 'alt' => $alt, 'caption' => $caption, 'action' => $action, 'x' => $x, 'y' => $y );
        }
    }

    $video = null;
    if ( isset( $data['video'] ) ) {
        $v    = is_array( $data['video'] ) ? $data['video'] : array();
        $bad  = array();
        $srcs = array();
        foreach ( array( 'webm', 'mp4' ) as $k ) {
            if ( isset( $v[ $k ] ) ) {
                $p = prospergenics_2026_tool_demo_file_problem( $v[ $k ], array( $k ), $lim['video_bytes'], $files );
                if ( '' === $p ) {
                    $srcs[ $k ] = $v[ $k ];
                } else {
                    $errors[] = "video: the $k file $p; left out";
                }
            }
        }
        if ( ! $srcs ) {
            $bad[] = 'it needs a usable webm or mp4 file';
        }
        $poster = isset( $v['poster'] ) ? $v['poster'] : null;
        $p      = prospergenics_2026_tool_demo_file_problem( $poster, $images, $lim['image_bytes'], $files );
        if ( '' !== $p ) {
            $bad[] = "the poster $p";
        } elseif ( empty( $files[ $poster ]['width'] ) || empty( $files[ $poster ]['height'] ) ) {
            $bad[] = 'the poster could not be read as an image';
        }
        $vtt = isset( $v['captions'] ) ? $v['captions'] : null;
        $p   = prospergenics_2026_tool_demo_file_problem( $vtt, array( 'vtt' ), $lim['vtt_bytes'], $files );
        if ( '' !== $p ) {
            $bad[] = "the captions $p";
        }
        if ( $bad ) {
            $errors[] = 'video left out: ' . implode( '; ', $bad );
        } else {
            $video = array(
                'webm'     => isset( $srcs['webm'] ) ? $srcs['webm'] : '',
                'mp4'      => isset( $srcs['mp4'] ) ? $srcs['mp4'] : '',
                'poster'   => $poster,
                'captions' => $vtt,
                'width'    => $files[ $poster ]['width'],
                'height'   => $files[ $poster ]['height'],
            );
        }
    }

    if ( ! $steps && ! $video ) {
        return $none( 'nothing usable: no valid steps and no valid video' );
    }
    $captured = isset( $data['captured'] ) && is_string( $data['captured'] ) && preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/D', $data['captured'], $d ) && checkdate( (int) $d[2], (int) $d[3], (int) $d[1] ) ? $data['captured'] : '';
    return array(
        'demo'   => array(
            'tool'     => $slug,
            'title'    => prospergenics_2026_tool_demo_text( isset( $data['title'] ) ? $data['title'] : null, $lim['title'] ),
            'source'   => prospergenics_2026_tool_demo_text( isset( $data['source'] ) ? $data['source'] : null, $lim['source'] ),
            'captured' => $captured,
            'steps'    => $steps,
            'video'    => $video,
        ),
        'errors' => $errors,
    );
}

/**
 * What a demo folder holds: name => bytes and, for images, their real width and height.
 * Only plain files with allowed names; symlinks and anything else are ignored.
 */
function prospergenics_2026_tool_demo_scan( $dir ) {
    $files = array();
    foreach ( is_dir( $dir ) ? (array) scandir( $dir ) : array() as $name ) {
        $file = $dir . '/' . $name;
        if ( ! prospergenics_2026_tool_demo_is_file_name( $name ) || is_link( $file ) || ! is_file( $file ) ) {
            continue;
        }
        $size = in_array( strtolower( (string) pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'webp', 'png', 'jpg', 'jpeg' ), true ) ? @getimagesize( $file ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors -- a broken image just has no size
        $files[ $name ] = array(
            'bytes'  => (int) filesize( $file ),
            'width'  => $size ? (int) $size[0] : null,
            'height' => $size ? (int) $size[1] : null,
        );
    }
    return $files;
}

/**
 * Read and validate a tool's demo folder: the demo (with 'base', the folder URL), or null when there
 * is no folder or nothing valid in it. With WP_DEBUG on, what was left out is logged.
 */
function prospergenics_2026_tool_demo_load( $slug ) {
    if ( ! preg_match( '/^[a-z0-9][a-z0-9-]{0,62}$/D', (string) $slug ) ) {
        return null;
    }
    $dir  = get_template_directory() . '/assets/tool-demos/' . $slug;
    $json = $dir . '/demo.json';
    if ( ! is_file( $json ) || is_link( $json ) || filesize( $json ) > prospergenics_2026_tool_demo_limits()['json_bytes'] ) {
        return null;
    }
    $result = prospergenics_2026_tool_demo_validate( json_decode( (string) file_get_contents( $json ), true ), $slug, prospergenics_2026_tool_demo_scan( $dir ) );
    if ( $result['errors'] && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
        error_log( 'tool-demos/' . $slug . ': ' . implode( ' | ', $result['errors'] ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions
    }
    if ( ! $result['demo'] ) {
        return null;
    }
    $result['demo']['base'] = rtrim( get_template_directory_uri(), '/' ) . '/assets/tool-demos/' . $slug . '/';
    return $result['demo'];
}

/** The section's styles (inc/site-chrome/tool-demo.css), printed only on a page that shows a demo or "Try it live". */
function prospergenics_2026_tool_demo_css() {
    return (string) file_get_contents( __DIR__ . '/site-chrome/tool-demo.css' );
}

/** The click-through and tabs script, printed only on a page that shows a demo. */
function prospergenics_2026_tool_demo_script() {
    return '<script id="prospergenics-tool-demo-js">' . "\n" . file_get_contents( __DIR__ . '/site-chrome/tool-demo.js' ) . "</script>\n";
}

/** A percentage with at most 3 decimals, for the cursor position (pure). */
function prospergenics_2026_tool_demo_pct( $v, $of ) {
    return rtrim( rtrim( number_format( 100 * $v / $of, 3, '.', '' ), '0' ), '.' );
}

/**
 * The "See it work" section (pure; every value escaped).
 *
 * Without JavaScript the screenshots stand one under another with their captions, and the video
 * below them. tool-demo.js turns the screenshots into one fixed-ratio frame with a guide cursor,
 * Back / Next / Play and step dots, and, when there are both, the two blocks into tabs.
 *
 * @param array  $demo      From prospergenics_2026_tool_demo_load() (validated, with 'base').
 * @param string $tool_name The tool's name as the page shows it.
 */
function prospergenics_2026_tool_demo_html( array $demo, $tool_name ) {
    $tool   = (string) $tool_name;
    $base   = isset( $demo['base'] ) ? (string) $demo['base'] : '';
    $steps  = isset( $demo['steps'] ) ? (array) $demo['steps'] : array();
    $video  = isset( $demo['video'] ) && is_array( $demo['video'] ) ? $demo['video'] : null;
    $both   = $steps && $video;
    $url    = function ( $name ) use ( $base ) { return esc_url( $base . rawurlencode( (string) $name ) ); };
    $label  = $both ? 'Real screenshots and a screen recording of ' . $tool : ( $steps ? 'Real screenshots of ' . $tool : 'Screen recording of ' . $tool );
    $source = isset( $demo['source'] ) ? (string) $demo['source'] : '';

    $h  = '<section class="pg-demo" id="see-it-work" aria-labelledby="pg-demo-h" data-pg-demo data-tool-name="' . esc_attr( $tool ) . '">' . "\n";
    $h .= '  <h2 class="pg-demo-h" id="pg-demo-h">See it work</h2>' . "\n";
    $h .= '  <p class="pg-demo-label"><span class="dot" aria-hidden="true"></span>' . esc_html( $label ) . '</p>' . "\n";
    if ( ! empty( $demo['title'] ) ) {
        $h .= '  <p class="pg-demo-title">' . esc_html( $demo['title'] ) . '</p>' . "\n";
    }
    if ( '' !== $source && strtolower( $source ) !== strtolower( $label ) ) {
        $h .= '  <p class="pg-demo-source">' . esc_html( $source ) . '</p>' . "\n";
    }
    if ( $both ) {
        $h .= '  <div class="pg-demo-tabs" role="tablist" aria-label="' . esc_attr( 'Ways to see ' . $tool ) . '" hidden>'
            . '<button type="button" role="tab" id="pg-demo-tab-steps" aria-controls="pg-demo-panel-steps" aria-selected="true">Step through</button>'
            . '<button type="button" role="tab" id="pg-demo-tab-video" aria-controls="pg-demo-panel-video" aria-selected="false" tabindex="-1">Watch</button>'
            . '</div>' . "\n";
    }

    if ( $steps ) {
        $n  = count( $steps );
        $h .= '  <div class="pg-demo-panel" id="pg-demo-panel-steps">' . "\n";
        if ( $both ) {
            $h .= '    <h3 class="pg-demo-ph">Step through</h3>' . "\n";
        }
        $h .= '    <div class="pg-demo-frame" style="--pg-demo-ratio: ' . (int) $steps[0]['width'] . ' / ' . (int) $steps[0]['height'] . '">' . "\n";
        $h .= '      <ol class="pg-demo-steps">' . "\n";
        foreach ( $steps as $i => $s ) {
            $pos = null === $s['x'] ? '' : ' data-x="' . esc_attr( prospergenics_2026_tool_demo_pct( $s['x'], $s['width'] ) ) . '" data-y="' . esc_attr( prospergenics_2026_tool_demo_pct( $s['y'], $s['height'] ) ) . '"';
            $h .= '        <li class="pg-demo-step' . ( 0 === $i ? ' is-current' : '' ) . '" data-action="' . esc_attr( $s['action'] ) . '"' . $pos . '>'
                . '<figure>'
                . '<img src="' . $url( $s['image'] ) . '" width="' . (int) $s['width'] . '" height="' . (int) $s['height'] . '" alt="' . esc_attr( $s['alt'] ) . '" loading="lazy" decoding="async" />'
                . '<figcaption><span class="pg-demo-n">Step ' . ( $i + 1 ) . ' of ' . $n . '</span> <span class="pg-demo-text">' . esc_html( $s['caption'] ) . '</span></figcaption>'
                . '</figure></li>' . "\n";
        }
        $h .= '      </ol>' . "\n" . '    </div>' . "\n" . '  </div>' . "\n";
    }

    if ( $video ) {
        $h .= '  <div class="pg-demo-panel" id="pg-demo-panel-video">' . "\n";
        if ( $both ) {
            $h .= '    <h3 class="pg-demo-ph">Watch</h3>' . "\n";
        }
        $h .= '    <div class="pg-demo-video">'
            . '<video controls preload="none" playsinline poster="' . $url( $video['poster'] ) . '" width="' . (int) $video['width'] . '" height="' . (int) $video['height'] . '" aria-label="' . esc_attr( 'Screen recording of ' . $tool ) . '">'
            . ( '' !== $video['webm'] ? '<source src="' . $url( $video['webm'] ) . '" type="video/webm" />' : '' )
            . ( '' !== $video['mp4'] ? '<source src="' . $url( $video['mp4'] ) . '" type="video/mp4" />' : '' )
            . '<track kind="captions" src="' . $url( $video['captions'] ) . '" srclang="en" label="English" default />'
            . '</video></div>' . "\n";
        $h .= '  </div>' . "\n";
    }
    return $h . '</section>' . "\n";
}

/**
 * Tools that run on this very site, so a visitor can try the real thing here: slug => texts.
 * CoachOS runs the site's chat (the homepage orb).
 */
function prospergenics_2026_tool_try_live_tools() {
    return (array) apply_filters( 'prospergenics_2026_tool_try_live', array(
        'coachos' => array( 'text' => "This site's chat runs on CoachOS. Ask it anything about ProsperGenics.", 'button' => 'Try it live' ),
    ) );
}

/**
 * "Try it live" for a tool page (pure apart from the filter): a link to the real chat page, and the
 * CoachOS embed with that link as its trigger. Once embed.js has loaded, a click opens the chat panel
 * on this page (embed.js listens for clicks on data-trigger and cancels the link); before that, or
 * when the script is blocked, the link opens the same chat full page. '' for other tools.
 *
 * @param string $slug  Tool slug.
 * @param array  $attrs CoachOS embed settings (prospergenics_2026_coach_embed_attrs()).
 * @param string $embed The embed markup (prospergenics_2026_coach_embed() with the same trigger).
 */
function prospergenics_2026_tool_try_live_html( $slug, array $attrs, $embed ) {
    $tools = prospergenics_2026_tool_try_live_tools();
    $href  = function_exists( 'prospergenics_2026_coach_widget_url' ) ? prospergenics_2026_coach_widget_url( $attrs ) : '';
    if ( ! isset( $tools[ $slug ] ) || '' === $href || '' === (string) $embed ) {
        return '';
    }
    $t = $tools[ $slug ];
    return '<div class="pg-try-live">'
        . '<p class="pg-try-text">' . esc_html( $t['text'] ) . '</p>'
        . '<a class="pg-try-btn pg-coach-try" href="' . esc_url( $href ) . '">' . esc_html( $t['button'] ) . '</a>'
        . '</div>' . "\n" . $embed;
}

/**
 * What the takeover needs for a tool page (reads the demo folder, builds the CoachOS embed): keys
 * tool_demo, tool_demo_css, tool_demo_script, tool_try_live. Empty for every other page.
 */
function prospergenics_2026_tool_demo_chrome( $path ) {
    $slug = prospergenics_2026_tool_demo_slug_for_path( $path );
    if ( '' === $slug ) {
        return array();
    }
    $try = '';
    $all = prospergenics_2026_tool_try_live_tools();
    if ( isset( $all[ $slug ] ) && function_exists( 'prospergenics_2026_coach_embed' ) ) {
        $over = array( 'data-trigger' => PROSPERGENICS_2026_TRY_LIVE_TRIGGER );
        $try  = prospergenics_2026_tool_try_live_html( $slug, prospergenics_2026_coach_embed_attrs( $over ), prospergenics_2026_coach_embed( $over ) );
    }
    $demo = prospergenics_2026_tool_demo_load( $slug );
    if ( ! $demo && '' === $try ) {
        return array();
    }
    return array(
        'tool_demo'        => $demo,
        'tool_demo_css'    => prospergenics_2026_tool_demo_css(),
        'tool_demo_script' => $demo ? prospergenics_2026_tool_demo_script() : '',
        'tool_try_live'    => $try,
    );
}

/**
 * Put "See it work" (and "Try it live") in a buffered tool page, right before the plugin's
 * "← All tools" link at the end of the main column (pure). Only on /tools/<slug>/ and only when that
 * link is there exactly once; otherwise the page is returned as it was.
 *
 * @param string $html   The page.
 * @param string $path   Request path.
 * @param array  $chrome tool_demo (validated demo or null), tool_demo_css, tool_demo_script, tool_try_live.
 * @return array{0:string,1:string} The page, and the script to print after the footer ('' if none).
 */
function prospergenics_2026_listings_add_tool_demo( $html, $path, array $chrome ) {
    $slug = prospergenics_2026_tool_demo_slug_for_path( $path );
    $demo = isset( $chrome['tool_demo'] ) && is_array( $chrome['tool_demo'] ) && isset( $chrome['tool_demo']['tool'] ) && $slug === $chrome['tool_demo']['tool'] ? $chrome['tool_demo'] : null;
    $try  = isset( $chrome['tool_try_live'] ) ? (string) $chrome['tool_try_live'] : '';
    if ( '' === $slug || ( ! $demo && '' === $try ) ) {
        return array( $html, '' );
    }
    $re = '#<div class="dir-back">\s*<a href="[^"]*/tools/">← All tools</a>\s*</div>#u';
    if ( 1 !== preg_match_all( $re, (string) $html, $m ) ) {
        return array( $html, '' );
    }
    // The tool's name as the page's banner shows it, e.g. "Jengo Meet".
    $name = preg_match( '#<header class="dir-hero[^"]*">.*?<h1>(.*?)</h1>#s', $html, $t ) ? trim( html_entity_decode( wp_strip_all_tags( $t[1] ), ENT_QUOTES, 'UTF-8' ) ) : '';
    if ( '' === $name ) {
        $name = ucwords( str_replace( '-', ' ', $slug ) );
    }
    $css   = isset( $chrome['tool_demo_css'] ) ? (string) $chrome['tool_demo_css'] : '';
    $block = ( '' !== trim( $css ) ? '<style id="prospergenics-tool-demo-css">' . "\n" . $css . "</style>\n" : '' )
        . ( $demo ? prospergenics_2026_tool_demo_html( $demo, $name ) : '' )
        . $try;
    $at = strpos( $html, $m[0][0] );
    return array( substr_replace( $html, $block . "\n      ", $at, 0 ), $demo && isset( $chrome['tool_demo_script'] ) ? (string) $chrome['tool_demo_script'] : '' );
}

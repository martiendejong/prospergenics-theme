<?php
/**
 * Staff area (/manage/, card #6457): URLs, who gets in, the templates and the stylesheet.
 *
 *   /manage/            views/overview.php
 *   /manage/members/    views/members.php
 *   /manage/messages/   views/messages.php (?message=<id> shows one message)
 *   /manage/articles/   views/articles.php
 *
 * Visitors are sent to the login page (302) and come back here; logged-in users without the
 * capability get a 403 page in the site's design (views/denied.php). Never cached, never indexed.
 */

defined( 'ABSPATH' ) || exit;

const PROSPERGENICS_MANAGE_REWRITE_VERSION = '1';

add_action( 'init', function () {
    add_rewrite_rule( '^manage/?$', 'index.php?pg_manage=overview', 'top' );
    add_rewrite_rule( '^manage/(members|messages|articles)/?$', 'index.php?pg_manage=$matches[1]', 'top' );
    // flush once when the rules change (a cheap option read on every request otherwise)
    if ( get_option( '_pg_manage_rewrite_version' ) !== PROSPERGENICS_MANAGE_REWRITE_VERSION ) {
        flush_rewrite_rules( false );
        update_option( '_pg_manage_rewrite_version', PROSPERGENICS_MANAGE_REWRITE_VERSION );
    }
} );
add_action( 'after_switch_theme', function () {
    delete_option( '_pg_manage_rewrite_version' );
} );

add_filter( 'query_vars', function ( $vars ) {
    $vars[] = 'pg_manage';
    return $vars;
} );

/** The screens, key => [menu label, page title]. */
function prospergenics_manage_views() {
    return array(
        'overview' => array( 'Overview', 'Manage' ),
        'members'  => array( 'Members', 'Members' ),
        'messages' => array( 'Contact messages', 'Contact messages' ),
        'articles' => array( 'Articles for review', 'Articles for review' ),
    );
}

/** Which /manage/ screen this request is, or ''. */
function prospergenics_manage_view() {
    $view = (string) get_query_var( 'pg_manage' );
    return isset( prospergenics_manage_views()[ $view ] ) ? $view : '';
}

/** URL of a screen, with optional query arguments. */
function prospergenics_manage_url( $view = 'overview', $args = array() ) {
    $url = home_url( 'overview' === $view ? '/manage/' : '/manage/' . $view . '/' );
    return $args ? add_query_arg( $args, $url ) : $url;
}

/**
 * Whether the current user may open a screen: pg_manage for all of them; the contact inbox also
 * needs the contact-message capability and the review queue edit_others_pg_articles (editors and
 * administrators have both).
 */
function prospergenics_manage_can_view( $view ) {
    if ( ! prospergenics_manage_can_access() ) {
        return false;
    }
    if ( 'messages' === $view ) {
        return current_user_can( 'edit_others_pg_contact_msgs' );
    }
    if ( 'articles' === $view ) {
        return current_user_can( 'edit_others_pg_articles' );
    }
    return true;
}

// Don't run the blog query behind these pages.
add_filter( 'posts_pre_query', function ( $posts, $query ) {
    if ( $query->is_main_query() && '' !== (string) $query->get( 'pg_manage' ) ) {
        $query->found_posts   = 0;
        $query->max_num_pages = 0;
        return array();
    }
    return $posts;
}, 10, 2 );

add_action( 'template_redirect', function () {
    $view = prospergenics_manage_view();
    if ( '' === $view ) {
        return;
    }
    global $wp_query;
    $wp_query->is_home = false;
    $wp_query->is_404  = false;
    nocache_headers();
    add_filter( 'wp_robots', 'wp_robots_no_robots' );

    if ( ! is_user_logged_in() ) {
        wp_safe_redirect( wp_login_url( home_url( add_query_arg( array() ) ) ) );
        exit;
    }
    $title = prospergenics_manage_views()[ $view ][1];
    if ( ! prospergenics_manage_can_view( $view ) ) {
        status_header( 403 );
        add_filter( 'pre_get_document_title', function () { return 'Staff only – ProsperGenics'; } );
        include __DIR__ . '/views/denied.php';
        exit;
    }
    status_header( 200 );
    add_filter( 'pre_get_document_title', function () use ( $title, $view ) {
        return ( 'overview' === $view ? 'Manage' : $title . ' – Manage' ) . ' – ProsperGenics';
    } );
    include __DIR__ . '/views/' . $view . '.php';
    exit;
}, 5 );

add_action( 'wp_enqueue_scripts', function () {
    if ( '' === prospergenics_manage_view() ) {
        return;
    }
    $css = '/assets/platform/manage.css';
    $dir = get_template_directory();
    wp_enqueue_style( 'pg-manage', get_template_directory_uri() . $css, array(), file_exists( $dir . $css ) ? (string) filemtime( $dir . $css ) : PROSPERGENICS_MANAGE_VERSION );
} );

/* ───────────────────────── markup shared by the screens ───────────────────────── */

/** The notice for a ?done= result of an action, or ''. */
function prospergenics_manage_notice_html() {
    // phpcs:ignore WordPress.Security.NonceVerification -- display only, whitelisted values
    $done = isset( $_GET['done'] ) ? sanitize_key( wp_unslash( $_GET['done'] ) ) : '';
    $user = isset( $_GET['member'] ) ? get_userdata( absint( $_GET['member'] ) ) : null;
    $name = $user ? $user->display_name : 'the member';
    $msgs = array(
        'role'           => array( 'ok', sprintf( 'The role of %s is changed.', $name ) ),
        'verified'       => array( 'ok', sprintf( 'The email address of %s is marked as confirmed.', $name ) ),
        'already'        => array( 'ok', sprintf( 'The email address of %s was already confirmed.', $name ) ),
        'sent'           => array( 'ok', sprintf( 'A new confirmation link was emailed to %s.', $name ) ),
        'mail_failed'    => array( 'error', sprintf( 'The confirmation link for %s could not be sent. Check the site’s mail setup.', $name ) ),
        'hidden'         => array( 'ok', sprintf( 'The profile of %s is hidden: only they and staff can see it, and it is left out of search engines.', $name ) ),
        'shown'          => array( 'ok', sprintf( 'The profile of %s is visible again (as their privacy settings allow).', $name ) ),
        'suspended'      => array( 'ok', sprintf( '%s is suspended: they cannot log in and their profile is hidden.', $name ) ),
        'unsuspended'    => array( 'ok', sprintf( 'The suspension of %s is lifted: they can log in again.', $name ) ),
        'cannot_suspend' => array( 'error', 'This member cannot be suspended (staff, or your own account).' ),
        'msg_read'       => array( 'ok', 'The message is marked as read.' ),
        'msg_unread'     => array( 'ok', 'The message is marked as unread.' ),
        'msg_handled'    => array( 'ok', 'The message is marked as handled.' ),
        'msg_reopen'     => array( 'ok', 'The message is open again.' ),
        'published'      => array( 'ok', 'The article is published.' ),
        'failed'         => array( 'error', 'That did not work. It may already have changed; please try again.' ),
    );
    if ( ! isset( $msgs[ $done ] ) ) {
        return '';
    }
    return '<p class="form-notice form-notice-' . esc_attr( $msgs[ $done ][0] ) . '" role="' . ( 'error' === $msgs[ $done ][0] ? 'alert' : 'status' ) . '">' . esc_html( $msgs[ $done ][1] ) . '</p>';
}

/**
 * Start a screen: the site header, the hero and the section tabs.
 *
 * @param string $view  The screen key.
 * @param string $intro One sentence under the title.
 */
function prospergenics_manage_open( $view, $intro ) {
    get_header();
    $views = prospergenics_manage_views();
    ?>
<div class="post-hero">
  <div class="post-hero-inner pgm-hero">
    <div class="ph-eyebrow"><span class="dot"></span>Staff</div>
    <h1><?php echo esc_html( $views[ $view ][1] ); ?></h1>
    <p class="ph-excerpt"><?php echo esc_html( $intro ); ?></p>
  </div>
</div>
<div class="pgm-wrap">
  <nav class="pgm-tabs" aria-label="Manage">
    <?php foreach ( $views as $key => $v ) :
        if ( ! prospergenics_manage_can_view( $key ) ) {
            continue;
        } ?>
    <a class="pgm-tab" href="<?php echo esc_url( prospergenics_manage_url( $key ) ); ?>"<?php echo $key === $view ? ' aria-current="page"' : ''; ?>><?php echo esc_html( $v[0] ); ?></a>
    <?php endforeach; ?>
    <a class="pgm-tab pgm-tab-out" href="<?php echo esc_url( admin_url() ); ?>">wp-admin</a>
  </nav>
  <?php echo prospergenics_manage_notice_html(); // escaped inside ?>
    <?php
}

/** End a screen. */
function prospergenics_manage_close() {
    echo "</div>\n";
    get_footer();
}

/**
 * The other queues staff work through, with what is waiting: Reports (pg_moderate), Users →
 * Requests (promote_users) and the instructor dashboard. Only the links the user may open.
 *
 * @return array<int,array{label:string,url:string,count:int|null,note:string}>
 */
function prospergenics_manage_other_queues( array $queues ) {
    $out = array();
    if ( defined( 'PROSPERGENICS_MODERATION_CAP' ) && current_user_can( PROSPERGENICS_MODERATION_CAP ) ) {
        $out[] = array( 'label' => 'Reports', 'url' => admin_url( 'admin.php?page=pg-moderation' ), 'count' => (int) $queues['reports'], 'note' => _n( 'open report', 'open reports', (int) $queues['reports'] ) );
    }
    if ( current_user_can( 'promote_users' ) ) {
        $out[] = array( 'label' => 'Users → Requests', 'url' => admin_url( 'users.php?page=pg-applications' ), 'count' => (int) $queues['requests'], 'note' => _n( 'open writer or instructor request', 'open writer and instructor requests', (int) $queues['requests'] ) );
    }
    if ( function_exists( 'prospergenics_courses_is_instructor' ) && prospergenics_courses_is_instructor() ) {
        $out[] = array( 'label' => 'Instructor dashboard', 'url' => home_url( '/instructor/dashboard/' ), 'count' => null, 'note' => 'courses, learners and completions' );
    }
    return $out;
}

/** The links of prospergenics_manage_other_queues() as a list. */
function prospergenics_manage_other_queues_html( array $queues ) {
    $links = prospergenics_manage_other_queues( $queues );
    if ( ! $links ) {
        return '';
    }
    $html = '<ul class="pgm-links">';
    foreach ( $links as $l ) {
        $html .= '<li><a href="' . esc_url( $l['url'] ) . '">' . esc_html( $l['label'] ) . '</a> <span class="pgm-muted">'
            . esc_html( null === $l['count'] ? $l['note'] : sprintf( '%s %s', number_format_i18n( $l['count'] ), $l['note'] ) ) . '</span></li>';
    }
    return $html . '</ul>';
}

/** Paging links for a list on a screen. */
function prospergenics_manage_pagination( $base_url, $current, $total ) {
    if ( $total < 2 ) {
        return '';
    }
    $links = paginate_links( array(
        'base'    => esc_url_raw( add_query_arg( 'pg', '%#%', $base_url ) ),
        'format'  => '',
        'current' => max( 1, (int) $current ),
        'total'   => (int) $total,
    ) );
    return $links ? '<nav class="pagination" aria-label="Pages">' . $links . '</nav>' : '';
}

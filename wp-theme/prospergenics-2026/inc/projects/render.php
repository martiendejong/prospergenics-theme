<?php
/**
 * Projects track: markup shared by the profile's "Portfolio" section and the project templates.
 * Everything is escaped here. Evidence and project links are https only (helpers.php) and open in
 * a new tab with rel="nofollow ugc noopener".
 */

defined( 'ABSPATH' ) || exit;

/** A status badge. */
function prospergenics_projects_status_badge( $status ) {
    $labels = array( 'claimed' => 'Claimed', 'confirmed' => 'Confirmed', 'disputed' => 'Disputed' );
    $titles = prospergenics_projects_statuses();
    if ( ! isset( $labels[ $status ] ) ) {
        return '';
    }
    return sprintf(
        '<span class="pgj-status pgj-status-%1$s" title="%2$s">%3$s<span class="screen-reader-text">: %2$s</span></span>',
        esc_attr( $status ),
        esc_attr( $titles[ $status ] ),
        esc_html( $labels[ $status ] )
    );
}

/** An external link (https, new tab, no referrer credit). */
function prospergenics_projects_external_link( $url, $label ) {
    return sprintf(
        '<a href="%s" rel="nofollow ugc noopener" target="_blank">%s<span class="screen-reader-text"> (opens in a new tab)</span></a>',
        esc_url( $url ),
        esc_html( $label )
    );
}

/** The evidence list of a contribution. */
function prospergenics_projects_evidence_html( array $evidence ) {
    if ( ! $evidence ) {
        return '';
    }
    $items = '';
    foreach ( $evidence as $e ) {
        $items .= '<li>' . prospergenics_projects_external_link( $e['url'], $e['label'] ) . '</li>';
    }
    return '<div class="pgj-evidence"><span class="pgj-evidence-h">Evidence</span><ul>' . $items . '</ul></div>';
}

/** Hidden fields every form carries: action, nonce, the page to come back to. */
function prospergenics_projects_form_open( $action, $id = '', $class = 'pgp-form pgj-form' ) {
    printf( '<form class="%s" method="post" action="%s"%s>', esc_attr( $class ), esc_url( admin_url( 'admin-post.php' ) ), '' !== $id ? ' id="' . esc_attr( $id ) . '"' : '' );
    echo '<input type="hidden" name="action" value="' . esc_attr( 'pg_projects_' . $action ) . '" />';
    wp_nonce_field( 'pg_projects_' . $action, '_pgj_nonce', false );
    $back = home_url( add_query_arg( array() ) );
    echo '<input type="hidden" name="back" value="' . esc_attr( remove_query_arg( array( 'pg_portfolio', 'pg_project_q' ), $back ) ) . '" />';
}

/** A field with its label, hint and error (aria-invalid + aria-describedby). */
function prospergenics_projects_field( $id, $name, $label, $value, $type = 'text', array $attrs = array(), $error = '' ) {
    $extra = '';
    foreach ( $attrs as $k => $v ) {
        if ( in_array( $k, array( 'options', 'hint' ), true ) ) {
            continue;
        }
        $extra .= true === $v ? ' ' . esc_attr( $k ) : sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
    }
    $described = array();
    if ( ! empty( $attrs['hint'] ) ) {
        $described[] = $id . '-hint';
    }
    if ( '' !== $error ) {
        $described[] = $id . '-err';
        $extra      .= ' aria-invalid="true"';
    }
    if ( $described ) {
        $extra .= ' aria-describedby="' . esc_attr( implode( ' ', $described ) ) . '"';
    }
    echo '<div class="pgp-field pgp-field-' . esc_attr( $type ) . '">';
    printf( '<label for="%s">%s</label>', esc_attr( $id ), esc_html( $label ) );
    if ( 'textarea' === $type ) {
        printf( '<textarea id="%s" name="%s" rows="4"%s>%s</textarea>', esc_attr( $id ), esc_attr( $name ), $extra, esc_textarea( (string) $value ) );
    } elseif ( 'select' === $type ) {
        printf( '<select id="%s" name="%s"%s>', esc_attr( $id ), esc_attr( $name ), $extra );
        foreach ( (array) $attrs['options'] as $k => $l ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( (string) $value, (string) $k, false ), esc_html( $l ) );
        }
        echo '</select>';
    } else {
        printf( '<input type="%s" id="%s" name="%s" value="%s"%s />', esc_attr( $type ), esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ), $extra );
    }
    if ( ! empty( $attrs['hint'] ) ) {
        printf( '<span class="pgp-hint" id="%s-hint">%s</span>', esc_attr( $id ), esc_html( $attrs['hint'] ) );
    }
    if ( '' !== $error ) {
        printf( '<span class="pgj-field-error" id="%s-err">%s</span>', esc_attr( $id ), esc_html( $error ) );
    }
    echo '</div>';
}

/** The project fields (name, kind, link, period, description, skills). */
function prospergenics_projects_project_fields( $p, array $v, array $err ) {
    $g = function ( $k ) use ( $v ) {
        return isset( $v[ $k ] ) ? ( is_array( $v[ $k ] ) ? implode( ', ', $v[ $k ] ) : $v[ $k ] ) : '';
    };
    $e = function ( $k ) use ( $err ) {
        return isset( $err[ $k ] ) ? $err[ $k ] : '';
    };
    echo '<div class="pgp-grid2">';
    prospergenics_projects_field( $p . '-title', 'title', 'Name', $g( 'title' ), 'text', array( 'required' => true, 'maxlength' => 120 ), $e( 'title' ) );
    prospergenics_projects_field( $p . '-kind', 'kind', 'This is a', '' !== $g( 'kind' ) ? $g( 'kind' ) : 'project', 'select', array( 'options' => prospergenics_projects_kinds() ), $e( 'kind' ) );
    prospergenics_projects_field( $p . '-url', 'url', 'Link (optional)', $g( 'url' ), 'url', array( 'maxlength' => 2000, 'placeholder' => 'https://', 'pattern' => 'https://.+', 'hint' => 'The website of the project, product or employer. Must start with https://.' ), $e( 'url' ) );
    prospergenics_projects_field( $p . '-period', 'period', 'Period (optional)', $g( 'period' ), 'text', array( 'maxlength' => 40, 'hint' => 'For example 2024–2026, or Since 2023.' ), $e( 'period' ) );
    echo '</div>';
    prospergenics_projects_field( $p . '-desc', 'description', 'Description (optional)', $g( 'description' ), 'textarea', array( 'maxlength' => 4000 ), $e( 'description' ) );
    prospergenics_projects_field( $p . '-skills', 'skills', 'Skills used (optional)', $g( 'skills' ), 'text', array( 'maxlength' => 700, 'list' => 'pgp-skill-vocab', 'hint' => 'Separate skills with commas, at most 10.' ), $e( 'skills' ) );
}

/** The contribution fields (role, what you did, evidence rows). */
function prospergenics_projects_contribution_fields( $p, array $v, array $err, $legend = 'Your contribution' ) {
    $e = function ( $k ) use ( $err ) {
        return isset( $err[ $k ] ) ? $err[ $k ] : '';
    };
    echo '<fieldset class="pgj-fieldset"><legend>' . esc_html( $legend ) . '</legend>';
    prospergenics_projects_field( $p . '-role', 'role', 'Role', isset( $v['role'] ) ? $v['role'] : '', 'text', array( 'required' => true, 'maxlength' => 120, 'hint' => 'For example Lead installer, Co-founder, Volunteer trainer.' ), $e( 'role' ) );
    prospergenics_projects_field( $p . '-contribution', 'contribution', 'What you contributed', isset( $v['contribution'] ) ? $v['contribution'] : '', 'textarea', array( 'required' => true, 'maxlength' => 2000 ), $e( 'contribution' ) );
    $rows = isset( $v['evidence'] ) && is_array( $v['evidence'] ) ? array_values( $v['evidence'] ) : array();
    $n    = min( PROSPERGENICS_PROJECTS_MAX_EVIDENCE, max( 3, count( $rows ) + 1 ) );
    echo '<fieldset class="pgj-evidence-rows"' . ( '' !== $e( 'evidence' ) ? ' aria-describedby="' . esc_attr( $p . '-ev-err' ) . '"' : '' ) . '><legend>Evidence links (optional)</legend>';
    echo '<p class="pgp-hint">Links that show your work: photos, a report, an article, a shop page. Only addresses starting with https:// are accepted.</p>';
    if ( '' !== $e( 'evidence' ) ) {
        echo '<p class="pgj-field-error" id="' . esc_attr( $p . '-ev-err' ) . '">' . esc_html( $e( 'evidence' ) ) . '</p>';
    }
    for ( $i = 0; $i < $n; $i++ ) {
        $row = isset( $rows[ $i ] ) ? $rows[ $i ] : array( 'label' => '', 'url' => '' );
        echo '<div class="pgj-evidence-row">';
        printf( '<div class="pgp-field"><label for="%1$s">Link %2$d: what it shows</label><input type="text" id="%1$s" name="evidence_label[]" value="%3$s" maxlength="120" /></div>', esc_attr( $p . '-evl-' . $i ), $i + 1, esc_attr( $row['label'] ) );
        printf( '<div class="pgp-field"><label for="%1$s">Link %2$d: address</label><input type="url" id="%1$s" name="evidence_url[]" value="%3$s" maxlength="2000" placeholder="https://" pattern="https://.+" /></div>', esc_attr( $p . '-evu-' . $i ), $i + 1, esc_attr( $row['url'] ) );
        echo '</div>';
    }
    echo '</fieldset></fieldset>';
}

/** Submit row. */
function prospergenics_projects_submit( $label, $class = 'pgp-btn pgp-btn-primary' ) {
    echo '<div class="pgp-form-actions"><button type="submit" class="' . esc_attr( $class ) . '">' . esc_html( $label ) . '</button></div>';
}

/** A notice for ?pg_portfolio= (once per page). */
function prospergenics_projects_print_notice() {
    static $done = false;
    $n = prospergenics_projects_current_notice();
    if ( $done || ! $n ) {
        return;
    }
    $done = true;
    printf( '<div class="pgp-notice pgp-notice-%s pgj-notice" role="%s">%s</div>', esc_attr( $n[0] ), 'error' === $n[0] ? 'alert' : 'status', esc_html( $n[1] ) );
}

/**
 * The "Portfolio" section on /in/<slug>/ (called from pg-profile.php). Visitors see the entries the
 * member's privacy settings allow; the member sees the forms in edit mode.
 *
 * @param WP_User $owner
 * @param array   $data      prospergenics_profiles_public_data()
 * @param int     $viewer_id
 */
function prospergenics_projects_profile_section( $owner, array $data, $viewer_id ) {
    $is_owner = ! empty( $data['is_owner'] );
    if ( empty( $data['visible']['portfolio'] ) ) {
        return;
    }
    $items = isset( $data['portfolio'] ) && is_array( $data['portfolio'] ) ? $data['portfolio'] : array();
    if ( ! $items && ! $is_owner ) {
        return;
    }
    $can_add = $is_owner && prospergenics_projects_user_can_create( $owner->ID );
    ?>
    <section class="pgp-section pgj-portfolio" id="pgp-portfolio" aria-labelledby="pgp-portfolio-h">
      <div class="pgp-section-head"><h2 id="pgp-portfolio-h" tabindex="-1">Portfolio</h2><?php if ( function_exists( 'prospergenics_profiles_badge' ) ) { prospergenics_profiles_badge( $data, 'portfolio' ); } ?></div>
      <?php if ( $is_owner ) { prospergenics_projects_print_notice(); } ?>
      <?php if ( $items ) : ?>
        <ol class="pgj-list">
          <?php foreach ( $items as $e ) { prospergenics_projects_portfolio_item( $e, $owner, $is_owner ); } ?>
        </ol>
      <?php else : ?>
        <p class="pgp-empty">Show what you worked on: projects, products, services and employers, with your role and links that prove it.</p>
      <?php endif; ?>
      <?php if ( $is_owner ) : ?>
        <div class="pgp-edit-only pgj-owner-tools">
          <?php if ( ! $can_add ) : ?>
            <p class="pgp-hint">Confirm your email address to add projects to your portfolio (see the banner at the top of the page).</p>
          <?php else :
              $kept = prospergenics_projects_recall_form( $owner->ID, 'create' ); ?>
            <details class="pgj-details" id="pgj-new"<?php echo $kept ? ' open' : ''; ?>>
              <summary class="pgp-btn">Add a project, product, service or employer</summary>
              <?php prospergenics_projects_form_open( 'create' ); ?>
                <fieldset class="pgj-fieldset"><legend>The project, product, service or employer</legend>
                <?php prospergenics_projects_project_fields( 'pgj-new', $kept ? $kept['values'] : array(), $kept ? $kept['errors'] : array() ); ?>
                </fieldset>
                <?php prospergenics_projects_contribution_fields( 'pgj-new', $kept ? $kept['values'] : array(), $kept ? $kept['errors'] : array() ); ?>
                <?php prospergenics_projects_submit( 'Add to my portfolio' ); ?>
              </form>
              <?php if ( function_exists( 'prospergenics_profiles_skills_datalist' ) ) { prospergenics_profiles_skills_datalist(); } ?>
            </details>
            <?php prospergenics_projects_join_search( $owner ); ?>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </section>
    <?php
}

/** One entry of the portfolio list on a profile. */
function prospergenics_projects_portfolio_item( array $e, $owner, $is_owner ) {
    $kinds = prospergenics_projects_kinds();
    $pid   = (int) $e['project_id'];
    $hid   = 'pgj-item-' . $pid;
    ?>
    <li class="pgj-item" id="<?php echo esc_attr( $hid ); ?>">
      <p class="pgj-kind"><?php echo esc_html( $kinds[ $e['kind'] ] ); ?><?php if ( '' !== $e['period'] ) : ?> · <?php echo esc_html( $e['period'] ); ?><?php endif; ?></p>
      <h3><a href="<?php echo esc_url( $e['url'] ); ?>"><?php echo esc_html( $e['title'] ); ?></a></h3>
      <p class="pgj-role"><?php echo esc_html( $e['role'] ); ?> <?php echo prospergenics_projects_status_badge( $e['status'] ); // escaped in the helper ?></p>
      <?php if ( '' !== $e['contribution'] ) : ?><div class="pgj-contribution"><?php echo wpautop( esc_html( $e['contribution'] ) ); ?></div><?php endif; ?>
      <?php echo prospergenics_projects_evidence_html( $e['evidence'] ); // escaped in the helper ?>
      <?php if ( '' !== $e['link'] ) : ?><p class="pgj-link"><?php echo prospergenics_projects_external_link( $e['link'], (string) preg_replace( '/^www\./i', '', (string) parse_url( $e['link'], PHP_URL_HOST ) ) ); // escaped in the helper ?></p><?php endif; ?>
      <div class="pgj-scrutiny">
        <?php
        // Card #6389 (scrutiny) renders its public UI here once its module exists; until then nothing.
        do_action( 'prospergenics_scrutiny_actions', 'project', $pid );
        do_action( 'prospergenics_scrutiny_public', 'project', $pid );
        ?>
      </div>
      <?php if ( $is_owner ) :
          $kept = prospergenics_projects_recall_form( $owner->ID, 'contribution-' . $pid . '-' . $owner->ID );
          $form_id = 'pgj-c-' . $pid . '-' . (int) $owner->ID; ?>
        <div class="pgp-edit-only pgj-item-tools">
          <details class="pgj-details" id="<?php echo esc_attr( $form_id ); ?>"<?php echo $kept ? ' open' : ''; ?>>
            <summary class="pgp-link-btn">Edit your contribution<span class="screen-reader-text"> to <?php echo esc_html( $e['title'] ); ?></span></summary>
            <?php prospergenics_projects_form_open( 'contribution' ); ?>
              <input type="hidden" name="project_id" value="<?php echo (int) $pid; ?>" />
              <?php prospergenics_projects_contribution_fields( $form_id, $kept ? $kept['values'] : $e, $kept ? $kept['errors'] : array() ); ?>
              <?php if ( 'claimed' !== $e['status'] ) : ?><p class="pgp-hint">If you change it, it shows as claimed again until the project owner confirms it.</p><?php endif; ?>
              <?php prospergenics_projects_submit( 'Save contribution' ); ?>
            </form>
          </details>
          <?php if ( ! empty( $e['is_creator'] ) ) : ?>
            <a class="pgp-link-btn" href="<?php echo esc_url( add_query_arg( 'edit', '1', $e['url'] ) . '#pgj-edit' ); ?>">Edit the project<span class="screen-reader-text"> <?php echo esc_html( $e['title'] ); ?></span></a>
          <?php endif; ?>
          <?php prospergenics_projects_form_open( 'leave', '', 'pgj-inline-form' ); ?>
            <input type="hidden" name="project_id" value="<?php echo (int) $pid; ?>" />
            <button type="submit" class="pgp-link-btn pgp-danger" data-pgj-confirm="<?php echo esc_attr( sprintf( 'Remove “%s” from your portfolio?', $e['title'] ) ); ?>">Remove from my portfolio<span class="screen-reader-text">: <?php echo esc_html( $e['title'] ); ?></span></button>
          </form>
        </div>
      <?php endif; ?>
    </li>
    <?php
}

/** "Join an existing project": a GET search on the profile, then a contribution form per result. */
function prospergenics_projects_join_search( $owner ) {
    $q       = isset( $_GET['pg_project_q'] ) ? prospergenics_projects_clip( sanitize_text_field( wp_unslash( $_GET['pg_project_q'] ) ), 80 ) : '';
    $results = '' !== $q ? prospergenics_projects_search( $q, $owner->ID ) : array();
    $kinds   = prospergenics_projects_kinds();
    $open    = '' !== $q;
    foreach ( $results as $r ) {
        $open = $open || (bool) prospergenics_projects_recall_form( $owner->ID, 'join-' . $r->ID );
    }
    ?>
    <details class="pgj-details" id="pgj-join"<?php echo $open ? ' open' : ''; ?>>
      <summary class="pgp-btn">Add your part to an existing project</summary>
      <form class="pgp-form pgj-search" method="get" action="<?php echo esc_url( prospergenics_profiles_url( $owner->ID ) ); ?>#pgj-join" role="search" aria-label="Find a project">
        <input type="hidden" name="edit" value="1" />
        <div class="pgp-field">
          <label for="pgj-q">Find a project, product, service or employer</label>
          <input type="search" id="pgj-q" name="pg_project_q" value="<?php echo esc_attr( $q ); ?>" maxlength="80" />
        </div>
        <div class="pgp-form-actions"><button type="submit" class="pgp-btn">Search</button></div>
      </form>
      <?php if ( '' !== $q ) : ?>
        <p class="pgj-count" role="status"><?php echo esc_html( $results ? sprintf( '%d found for “%s”', count( $results ), $q ) : sprintf( 'Nothing found for “%s” that you are not already part of. Add it as a new project instead.', $q ) ); ?></p>
        <?php if ( $results ) : ?>
          <ul class="pgj-results">
            <?php foreach ( $results as $r ) :
                $f    = prospergenics_projects_fields( $r->ID );
                $kept = prospergenics_projects_recall_form( $owner->ID, 'join-' . $r->ID ); ?>
              <li id="<?php echo esc_attr( 'pgj-join-' . $r->ID ); ?>">
                <p class="pgj-kind"><?php echo esc_html( $kinds[ $f['kind'] ] . ( '' !== $f['period'] ? ' · ' . $f['period'] : '' ) ); ?></p>
                <h3><a href="<?php echo esc_url( get_permalink( $r ) ); ?>"><?php echo esc_html( get_the_title( $r ) ); ?></a></h3>
                <details class="pgj-details"<?php echo $kept ? ' open' : ''; ?>>
                  <summary class="pgp-link-btn">Add my contribution<span class="screen-reader-text"> to <?php echo esc_html( get_the_title( $r ) ); ?></span></summary>
                  <?php prospergenics_projects_form_open( 'join' ); ?>
                    <input type="hidden" name="project_id" value="<?php echo (int) $r->ID; ?>" />
                    <?php prospergenics_projects_contribution_fields( 'pgj-join-' . $r->ID, $kept ? $kept['values'] : array(), $kept ? $kept['errors'] : array() ); ?>
                    <?php prospergenics_projects_submit( 'Add to my portfolio' ); ?>
                  </form>
                </details>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      <?php endif; ?>
    </details>
    <?php
}

/** A project card for /projects/. */
function prospergenics_projects_card( WP_Post $p, $viewer_id ) {
    $f       = prospergenics_projects_fields( $p->ID );
    $kinds   = prospergenics_projects_kinds();
    $people  = prospergenics_projects_contributors( $p->ID, $viewer_id );
    $excerpt = wp_trim_words( wp_strip_all_tags( $p->post_content ), 28 );
    ob_start();
    ?>
    <article class="pgj-card">
      <p class="pgj-kind"><?php echo esc_html( $kinds[ $f['kind'] ] ); ?><?php if ( '' !== $f['period'] ) : ?> · <?php echo esc_html( $f['period'] ); ?><?php endif; ?></p>
      <h2><a href="<?php echo esc_url( get_permalink( $p ) ); ?>"><?php echo esc_html( get_the_title( $p ) ); ?></a></h2>
      <?php if ( '' !== $excerpt ) : ?><p class="pgj-excerpt"><?php echo esc_html( $excerpt ); ?></p><?php endif; ?>
      <?php if ( $f['skills'] ) : ?>
        <ul class="pgj-skills" aria-label="Skills">
          <?php foreach ( array_slice( $f['skills'], 0, 5 ) as $s ) : ?><li><?php echo esc_html( $s ); ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
      <p class="pgj-people"><?php echo esc_html( sprintf( 1 === count( $people ) ? '%d contributor' : '%d contributors', count( $people ) ) ); ?></p>
    </article>
    <?php
    return (string) ob_get_clean();
}

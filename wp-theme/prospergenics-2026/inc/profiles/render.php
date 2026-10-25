<?php
/**
 * Profiles track: markup pieces shared by the profile templates. Everything is escaped here;
 * the input is the array from prospergenics_profiles_public_data().
 */

defined( 'ABSPATH' ) || exit;

/** Initials for the photo placeholder. */
function prospergenics_profiles_initials( $name ) {
    $parts = preg_split( '/\s+/u', trim( (string) $name ) );
    $out   = '';
    foreach ( array_slice( array_filter( $parts ), 0, 2 ) as $p ) {
        $out .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $p, 0, 1 ) ) : strtoupper( substr( $p, 0, 1 ) );
    }
    return '' === $out ? '?' : $out;
}

/** "Visible to: …" badge for the owner. */
function prospergenics_profiles_badge( array $data, $section ) {
    if ( empty( $data['is_owner'] ) || empty( $data['privacy'][ $section ] ) ) {
        return;
    }
    $levels = prospergenics_profiles_levels();
    printf(
        '<a class="pgp-badge pgp-owner-only" href="%s">Visible to: %s<span class="screen-reader-text"> (change in privacy settings)</span></a>',
        esc_url( home_url( '/profile/settings/' ) ),
        esc_html( $levels[ $data['privacy'][ $section ] ] )
    );
}

/** A labelled form field. $type: text|textarea|month|number|url|checkbox|select|file. */
function prospergenics_profiles_field( $id, $name, $label, $value = '', $type = 'text', array $attrs = array() ) {
    $extra = '';
    foreach ( $attrs as $k => $v ) {
        if ( 'options' === $k || 'hint' === $k ) {
            continue;
        }
        $extra .= true === $v ? ' ' . esc_attr( $k ) : sprintf( ' %s="%s"', esc_attr( $k ), esc_attr( $v ) );
    }
    $hint_id = ! empty( $attrs['hint'] ) ? $id . '-hint' : '';
    if ( $hint_id ) {
        $extra .= ' aria-describedby="' . esc_attr( $hint_id ) . '"';
    }
    echo '<div class="pgp-field pgp-field-' . esc_attr( $type ) . '">';
    if ( 'checkbox' === $type ) {
        printf( '<label for="%1$s"><input type="checkbox" id="%1$s" name="%2$s" value="1"%3$s%4$s /> %5$s</label>', esc_attr( $id ), esc_attr( $name ), $value ? ' checked' : '', $extra, esc_html( $label ) );
    } else {
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
    }
    if ( $hint_id ) {
        printf( '<span class="pgp-hint" id="%s">%s</span>', esc_attr( $hint_id ), esc_html( $attrs['hint'] ) );
    }
    echo '</div>';
}

/** Save / Cancel row plus the form's error region. */
function prospergenics_profiles_form_actions( $save = 'Save' ) {
    echo '<p class="pgp-form-error" role="alert" hidden></p>';
    echo '<div class="pgp-form-actions"><button type="submit" class="pgp-btn pgp-btn-primary">' . esc_html( $save ) . '</button>';
    echo '<button type="button" class="pgp-btn" data-pgp-cancel>Cancel</button></div>';
}

/** Inline form for an experience entry ($e null = new). */
function prospergenics_profiles_experience_form( $e = null ) {
    $id     = $e ? (int) $e['id'] : 0;
    $p      = 'pgp-exp-' . ( $id ? $id : 'new' );
    $method = $id ? 'PUT' : 'POST';
    $path   = $id ? 'experience/' . $id : 'experience';
    printf( '<form class="pgp-form" id="%s-form" data-pgp-form data-endpoint="%s" data-method="%s" data-refresh="experience sidebar" %s>', esc_attr( $p ), esc_attr( $path ), esc_attr( $method ), 'hidden' );
    echo '<fieldset><legend>' . ( $id ? 'Edit experience' : 'Add experience' ) . '</legend>';
    echo '<div class="pgp-grid2">';
    prospergenics_profiles_field( $p . '-title', 'title', 'Job title', $e ? $e['title'] : '', 'text', array( 'required' => true, 'maxlength' => 120, 'autocomplete' => 'organization-title' ) );
    prospergenics_profiles_field( $p . '-company', 'company', 'Company or organisation', $e ? $e['company'] : '', 'text', array( 'required' => true, 'maxlength' => 120, 'autocomplete' => 'organization' ) );
    prospergenics_profiles_field( $p . '-start', 'start', 'Start month', $e ? $e['start'] : '', 'month', array( 'required' => true, 'placeholder' => 'YYYY-MM', 'pattern' => '\d{4}-\d{2}', 'hint' => 'Year and month, e.g. 2023-04' ) );
    prospergenics_profiles_field( $p . '-end', 'end', 'End month', $e ? $e['end'] : '', 'month', array( 'placeholder' => 'YYYY-MM', 'pattern' => '\d{4}-\d{2}', 'data-pgp-end' => '1' ) );
    echo '</div>';
    prospergenics_profiles_field( $p . '-current', 'current', 'I currently work here', $e ? $e['current'] : false, 'checkbox', array( 'data-pgp-current' => '1' ) );
    prospergenics_profiles_field( $p . '-desc', 'description', 'Description (optional)', $e ? $e['description'] : '', 'textarea', array( 'maxlength' => 2000 ) );
    echo '</fieldset>';
    prospergenics_profiles_form_actions();
    echo '</form>';
}

/** Inline form for an education entry. */
function prospergenics_profiles_education_form( $e = null ) {
    $id     = $e ? (int) $e['id'] : 0;
    $p      = 'pgp-edu-' . ( $id ? $id : 'new' );
    $method = $id ? 'PUT' : 'POST';
    $path   = $id ? 'education/' . $id : 'education';
    $max    = (int) gmdate( 'Y' ) + 10;
    printf( '<form class="pgp-form" id="%s-form" data-pgp-form data-endpoint="%s" data-method="%s" data-refresh="education sidebar" hidden>', esc_attr( $p ), esc_attr( $path ), esc_attr( $method ) );
    echo '<fieldset><legend>' . ( $id ? 'Edit education' : 'Add education' ) . '</legend>';
    prospergenics_profiles_field( $p . '-inst', 'institution', 'School or institution', $e ? $e['institution'] : '', 'text', array( 'required' => true, 'maxlength' => 150 ) );
    echo '<div class="pgp-grid2">';
    prospergenics_profiles_field( $p . '-degree', 'degree', 'Degree or certificate (optional)', $e ? $e['degree'] : '', 'text', array( 'maxlength' => 120 ) );
    prospergenics_profiles_field( $p . '-field', 'field', 'Field of study (optional)', $e ? $e['field'] : '', 'text', array( 'maxlength' => 120 ) );
    prospergenics_profiles_field( $p . '-start', 'start_year', 'Start year (optional)', $e && $e['start_year'] ? $e['start_year'] : '', 'number', array( 'min' => 1940, 'max' => $max, 'inputmode' => 'numeric' ) );
    prospergenics_profiles_field( $p . '-end', 'end_year', 'End year (or expected)', $e && $e['end_year'] ? $e['end_year'] : '', 'number', array( 'min' => 1940, 'max' => $max, 'inputmode' => 'numeric' ) );
    echo '</div></fieldset>';
    prospergenics_profiles_form_actions();
    echo '</form>';
}

/** Date range text of an experience entry. */
function prospergenics_profiles_experience_dates( array $e ) {
    $start = prospergenics_profiles_format_month( $e['start'] );
    $end   = $e['current'] ? 'Present' : prospergenics_profiles_format_month( $e['end'] );
    return trim( $start . ' – ' . $end, ' –' );
}

/** The <datalist> with the skills vocabulary (card #6347 autocomplete). */
function prospergenics_profiles_skills_datalist( $id = 'pgp-skill-vocab' ) {
    echo '<datalist id="' . esc_attr( $id ) . '">';
    foreach ( prospergenics_profiles_skills_vocabulary() as $s ) {
        echo '<option value="' . esc_attr( $s ) . '"></option>';
    }
    echo '</datalist>';
}

/** One skill chip (view + owner/visitor controls). */
function prospergenics_profiles_skill_item( array $s, array $data, $viewer_id ) {
    $count = (int) $s['endorsements'];
    echo '<li class="pgp-skill">';
    echo '<span class="pgp-skill-name">' . esc_html( $s['name'] ) . '</span>';
    if ( $s['pinned'] ) {
        echo '<span class="pgp-pin-mark" title="Pinned"><span aria-hidden="true">★</span><span class="screen-reader-text">Pinned</span></span>';
    }
    printf(
        '<span class="pgp-skill-count" data-pgp-endorse-count="%d">%s</span>',
        (int) $s['id'],
        esc_html( sprintf( 1 === $count ? '%d endorsement' : '%d endorsements', $count ) )
    );
    if ( ! empty( $data['is_owner'] ) ) {
        echo '<span class="pgp-skill-tools pgp-edit-only">';
        printf(
            '<button type="button" class="pgp-link-btn" data-pgp-pin="%d" aria-pressed="%s">%s<span class="screen-reader-text"> %s</span></button>',
            (int) $s['id'],
            $s['pinned'] ? 'true' : 'false',
            $s['pinned'] ? 'Unpin' : 'Pin to top',
            esc_html( $s['name'] )
        );
        printf(
            '<button type="button" class="pgp-link-btn pgp-danger" data-pgp-delete="skills/%d" data-refresh="skills sidebar" data-confirm="%s">Remove<span class="screen-reader-text"> %s</span></button>',
            (int) $s['id'],
            esc_attr( sprintf( 'Remove the skill “%s”?', $s['name'] ) ),
            esc_html( $s['name'] )
        );
        echo '</span>';
    } elseif ( $viewer_id ) {
        printf(
            '<button type="button" class="pgp-endorse" data-pgp-endorse="%d" aria-pressed="%s"><span data-pgp-label>%s</span><span class="screen-reader-text"> %s</span></button>',
            (int) $s['id'],
            $s['endorsed_by_me'] ? 'true' : 'false',
            $s['endorsed_by_me'] ? 'Endorsed' : 'Endorse',
            esc_html( $s['name'] )
        );
    }
    echo '</li>';
}

/** A notice box. $kind ok|error|info. */
function prospergenics_profiles_notice( $kind, $message ) {
    $role = 'error' === $kind ? 'alert' : 'status';
    printf( '<div class="pgp-notice pgp-notice-%s" role="%s">%s</div>', esc_attr( $kind ), $role, esc_html( $message ) );
}

/** Small card linking to a member (for connection lists). */
function prospergenics_profiles_member_card( $user_id ) {
    $u = get_userdata( (int) $user_id );
    if ( ! $u ) {
        return;
    }
    $vis   = prospergenics_profiles_visibility_for( $u->ID, get_current_user_id() );
    $photo = $vis['profile'] ? prospergenics_profiles_photo_url( $u->ID, 'thumbnail' ) : '';
    $head  = $vis['profile'] ? (string) prospergenics_profiles_meta( $u->ID, 'headline' ) : '';
    echo '<li><a class="pgp-member" href="' . esc_url( prospergenics_profiles_url( $u->ID ) ) . '">';
    if ( $photo ) {
        echo '<img src="' . esc_url( $photo ) . '" alt="" width="48" height="48" loading="lazy" />';
    } else {
        echo '<span class="pgp-avatar-ph" aria-hidden="true">' . esc_html( $vis['profile'] ? prospergenics_profiles_initials( $u->display_name ) : '?' ) . '</span>';
    }
    // a member whose profile is hidden from this viewer stays anonymous here too
    echo '<span><strong>' . esc_html( $vis['profile'] ? $u->display_name : 'A member with a private profile' ) . '</strong>';
    if ( '' !== $head ) {
        echo '<span class="pgp-member-head">' . esc_html( $head ) . '</span>';
    }
    echo '</span></a></li>';
}

/** The square-crop dialog used by the photo upload (profiles.js drives it). */
function prospergenics_profiles_crop_dialog() {
    ?>
<dialog class="pgp-crop" id="pgp-crop" aria-labelledby="pgp-crop-h">
  <form method="dialog" class="pgp-crop-inner">
    <h2 id="pgp-crop-h">Crop your photo</h2>
    <p class="pgp-hint" id="pgp-crop-hint">Drag the photo (or use the arrow keys) to position it in the square; use the slider to zoom.</p>
    <canvas width="320" height="320" tabindex="0" aria-describedby="pgp-crop-hint" aria-label="Photo crop area" data-pgp-crop-canvas></canvas>
    <div class="pgp-field"><label for="pgp-crop-zoom">Zoom</label><input type="range" id="pgp-crop-zoom" min="1" max="4" step="0.01" value="1" data-pgp-crop-zoom /></div>
    <p class="pgp-form-error" role="alert" hidden data-pgp-crop-error></p>
    <div class="pgp-form-actions">
      <button type="button" class="pgp-btn pgp-btn-primary" data-pgp-crop-ok>Use photo</button>
      <button type="button" class="pgp-btn" data-pgp-crop-cancel>Cancel</button>
    </div>
  </form>
</dialog>
    <?php
}

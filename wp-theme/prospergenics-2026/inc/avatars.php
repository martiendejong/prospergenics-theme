<?php
/**
 * One avatar for a person everywhere (audit UX-17): their profile photo, or the same initials
 * placeholder the profile page shows. Never a Gravatar: nothing is requested from gravatar.com.
 *
 *   - prospergenics_2026_avatar_html() is used by the course, instructor and article templates,
 *   - the pre_get_avatar filter covers whatever else asks WordPress for an avatar on the front end
 *     (admin bar, comments, plugins): it answers with the photo or an initials image, so
 *     get_avatar() never falls through to a Gravatar URL.
 *
 * The photo and initials come from inc/profiles (prospergenics_profiles_photo_url(),
 * prospergenics_profiles_initials()) when that module is loaded; a photo shows only when the
 * profile is public or the viewer is its owner, as on the profile page itself.
 */

defined( 'ABSPATH' ) || exit;

/** Initials for a name ('Amina Wanjiru' -> 'AW'), the profiles module's rule when it is loaded. */
function prospergenics_2026_avatar_initials( $name ) {
    if ( function_exists( 'prospergenics_profiles_initials' ) ) {
        return prospergenics_profiles_initials( $name );
    }
    $out = '';
    foreach ( array_slice( array_filter( preg_split( '/\s+/u', trim( (string) $name ) ) ), 0, 2 ) as $part ) {
        $out .= function_exists( 'mb_substr' ) ? mb_strtoupper( mb_substr( $part, 0, 1 ) ) : strtoupper( substr( $part, 0, 1 ) );
    }
    return '' === $out ? '?' : $out;
}

/** The member's profile photo URL when this viewer may see it, else ''. */
function prospergenics_2026_avatar_photo_url( $user_id, $size ) {
    $user_id = (int) $user_id;
    if ( ! $user_id || ! function_exists( 'prospergenics_profiles_photo_url' ) ) {
        return '';
    }
    if ( function_exists( 'prospergenics_profiles_privacy' ) && get_current_user_id() !== $user_id ) {
        $privacy = prospergenics_profiles_privacy( $user_id );
        if ( isset( $privacy['profile'] ) && 'public' !== $privacy['profile'] ) {
            return '';
        }
    }
    return (string) prospergenics_profiles_photo_url( $user_id, $size > 150 ? 'medium' : 'thumbnail' );
}

/**
 * Avatar markup: <img> with the profile photo, or a <span> with the initials (decorative: the
 * name is always printed next to it).
 *
 * @param int    $user_id
 * @param int    $size  CSS pixel size, used for the image request and width/height.
 * @param string $class Classes for the element; the placeholder adds pg-avatar-ph.
 * @return string HTML
 */
function prospergenics_2026_avatar_html( $user_id, $size, $class = '' ) {
    $user  = get_userdata( (int) $user_id );
    $name  = $user ? $user->display_name : '';
    $photo = prospergenics_2026_avatar_photo_url( $user_id, $size );
    if ( $photo ) {
        return '<img class="' . esc_attr( $class ) . '" src="' . esc_url( $photo ) . '" alt="" width="' . (int) $size . '" height="' . (int) $size . '" loading="lazy" decoding="async" />';
    }
    return '<span class="' . esc_attr( trim( $class . ' pg-avatar-ph' ) ) . '" aria-hidden="true">' . esc_html( prospergenics_2026_avatar_initials( $name ) ) . '</span>';
}

/** An initials image (SVG data URI) in the placeholder colours, for get_avatar() callers. */
function prospergenics_2026_avatar_initials_url( $name ) {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><rect width="100" height="100" fill="#d9f5e3"/>'
        . '<text x="50" y="50" dy=".35em" text-anchor="middle" font-family="Gabarito,Inter,Arial,sans-serif" font-weight="800" font-size="40" fill="#0f3d28">'
        . htmlspecialchars( prospergenics_2026_avatar_initials( $name ), ENT_QUOTES | ENT_XML1, 'UTF-8' ) . '</text></svg>';
    return 'data:image/svg+xml;base64,' . base64_encode( $svg );
}

/** User ID and display name for whatever get_avatar() was given (ID, WP_User, WP_Post, WP_Comment, email). */
function prospergenics_2026_avatar_subject( $id_or_email ) {
    if ( $id_or_email instanceof WP_Comment ) {
        $user = $id_or_email->user_id ? get_userdata( (int) $id_or_email->user_id ) : null;
        return array( $user ? (int) $user->ID : 0, $user ? (string) $user->display_name : (string) $id_or_email->comment_author );
    }
    $user = null;
    if ( is_numeric( $id_or_email ) ) {
        $user = get_userdata( (int) $id_or_email );
    } elseif ( $id_or_email instanceof WP_User ) {
        $user = $id_or_email;
    } elseif ( $id_or_email instanceof WP_Post ) {
        $user = get_userdata( (int) $id_or_email->post_author );
    } elseif ( is_string( $id_or_email ) && is_email( $id_or_email ) ) {
        $user = get_user_by( 'email', $id_or_email );
    }
    return array( $user ? (int) $user->ID : 0, $user ? (string) $user->display_name : '' );
}

/*
 * get_avatar() on the front end. Built here rather than through pre_get_avatar_data because
 * get_avatar() passes the URL through esc_url(), which drops a data: URI.
 */
add_filter( 'pre_get_avatar', function ( $avatar, $id_or_email, $args ) {
    if ( null !== $avatar || is_admin() ) {
        return $avatar;
    }
    list( $user_id, $name ) = prospergenics_2026_avatar_subject( $id_or_email );
    $size   = isset( $args['size'] ) ? (int) $args['size'] : 96;
    $photo  = prospergenics_2026_avatar_photo_url( $user_id, $size );
    $class  = array_merge( array( 'avatar', 'avatar-' . $size, 'photo' ), ! empty( $args['class'] ) ? (array) $args['class'] : array() );
    $width  = ! empty( $args['width'] ) ? (int) $args['width'] : $size;
    $height = ! empty( $args['height'] ) ? (int) $args['height'] : $size;
    $src    = $photo ? esc_url( $photo ) : esc_attr( prospergenics_2026_avatar_initials_url( $name ) );
    $lazy   = ! empty( $args['loading'] ) ? ' loading="' . esc_attr( $args['loading'] ) . '"' : '';
    $extra  = ! empty( $args['extra_attr'] ) ? ' ' . $args['extra_attr'] : '';
    return '<img alt="' . esc_attr( isset( $args['alt'] ) ? $args['alt'] : '' ) . '" src="' . $src . '" class="' . esc_attr( implode( ' ', $class ) ) . '" height="' . $height . '" width="' . $width . '"' . $lazy . ' decoding="async"' . $extra . ' />';
}, 10, 3 );

<?php
/**
 * Expert directory markup helpers (task #4891).
 */

defined( 'ABSPATH' ) || exit;

/** One expert card as HTML. */
function prospergenics_expert_card( array $expert ) {
	$url  = esc_url( $expert['url'] );
	$name = esc_html( $expert['name'] );
	$role = esc_html( $expert['role'] );
	$slug = $expert['slug'];

	if ( ! empty( $expert['img'] ) ) {
		$src   = esc_url( get_template_directory_uri() . '/assets/' . $expert['img'] );
		$pos   = esc_attr( $expert['pos'] );
		$media = '<div class="pc-media"><img src="' . $src . '" alt="' . $name . '" style="object-position:' . $pos . '" loading="lazy" /></div>';
	} else {
		$pat   = abs( crc32( $slug ) ) % 3 + 1;
		$media = '<div class="pc-media pc-nophoto"><span class="pg-pattern pg-pattern--' . $pat . '" aria-hidden="true"></span></div>';
	}

	$chips = '';
	foreach ( array_slice( $expert['expertises'], 0, 3 ) as $e ) {
		$chips .= '<span class="chip">' . esc_html( is_array( $e ) ? ( isset( $e['name'] ) ? $e['name'] : '' ) : (string) $e ) . '</span>';
	}

	$quote = ! empty( $expert['quote'] ) ? '<p class="pc-line">' . esc_html( $expert['quote'] ) . '</p>' : '';

	return '<a class="pcard" href="' . $url . '">'
		. $media
		. '<div class="pc-body">'
		. '<h2>' . $name . '</h2>'
		. '<div class="pc-role">' . $role . '</div>'
		. $quote
		. ( $chips ? '<div class="chips">' . $chips . '</div>' : '' )
		. '</div>'
		. '</a>';
}

/** Category navigation tabs. */
function prospergenics_experts_category_tabs( $active_category = '' ) {
	$cats = prospergenics_expert_categories();
	$html = '<nav class="experts-cats" aria-label="Browse by category">';
	$html .= '<a class="ecat' . ( '' === $active_category ? ' ecat-active' : '' ) . '" href="' . esc_url( home_url( '/experts/' ) ) . '">All</a>';
	foreach ( $cats as $cat ) {
		$active = $cat['slug'] === $active_category ? ' ecat-active' : '';
		$html  .= '<a class="ecat' . $active . '" href="' . esc_url( home_url( '/experts/category/' . $cat['slug'] . '/' ) ) . '">' . esc_html( $cat['label'] ) . '</a>';
	}
	$html .= '</nav>';
	return $html;
}

/** Full expert profile body HTML. */
function prospergenics_expert_profile_html( array $expert ) {
	$name = esc_html( $expert['name'] );
	$role = esc_html( $expert['role'] );
	$slug = $expert['slug'];

	if ( ! empty( $expert['img'] ) ) {
		$src      = esc_url( get_template_directory_uri() . '/assets/' . $expert['img'] );
		$pos      = esc_attr( $expert['pos'] );
		$portrait = '<img src="' . $src . '" alt="' . $name . '" style="object-position:' . $pos . '" />';
	} else {
		$pat      = abs( crc32( $slug ) ) % 3 + 1;
		$portrait = '<span class="pg-pattern pg-pattern--' . $pat . '" aria-hidden="true"></span>';
	}

	$bio = ! empty( $expert['bio'] ) ? '<p>' . nl2br( esc_html( $expert['bio'] ) ) . '</p>' : '';
	if ( ! $bio && ! empty( $expert['quote'] ) ) {
		$bio = '<p>' . esc_html( $expert['quote'] ) . '</p>';
	}

	$skills_html = '';
	if ( ! empty( $expert['expertises'] ) ) {
		$skills_html = '<section class="ep-skills"><h2>Expertise</h2><div class="chips">';
		foreach ( $expert['expertises'] as $e ) {
			$label        = is_array( $e ) ? ( isset( $e['name'] ) ? $e['name'] : '' ) : (string) $e;
			$skills_html .= '<span class="chip">' . esc_html( $label ) . '</span>';
		}
		$skills_html .= '</div></section>';
	}

	$links_html = '';
	if ( ! empty( $expert['links'] ) ) {
		$links_html = '<section class="ep-links"><h2>Links</h2><ul>';
		foreach ( (array) $expert['links'] as $link ) {
			if ( empty( $link['url'] ) ) {
				continue;
			}
			$label       = ! empty( $link['label'] ) ? esc_html( $link['label'] ) : esc_html( $link['url'] );
			$links_html .= '<li><a href="' . esc_url( $link['url'] ) . '" rel="noopener noreferrer">' . $label . '</a></li>';
		}
		$links_html .= '</ul></section>';
	}

	return '<div class="ep-wrap">'
		. '<div class="ep-portrait">' . $portrait . '</div>'
		. '<div class="ep-body">'
		. '<h1>' . $name . '</h1>'
		. '<div class="ep-role">' . $role . '</div>'
		. ( $bio ? '<section class="ep-bio">' . $bio . '</section>' : '' )
		. $skills_html
		. $links_html
		. '</div>'
		. '</div>';
}

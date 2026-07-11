<?php
/**
 * [emcp_menu] shortcode — render a WordPress nav menu into custom HTML.
 *
 * Lets an agent-built custom header (e.g. an Elementor Canvas page assembled
 * from an HTML / shortcode widget) embed a LIVE menu: edit the menu in WP or
 * via the menu-write tool and every header updates automatically, no re-render.
 *
 * Separable from the MCP tools by design — safe to drop if a tools-only build
 * is preferred; the menu-read "render" operation covers the agent side.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Front-end [emcp_menu] shortcode.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Nav_Menu_Shortcode {

	/**
	 * Registers the shortcode.
	 *
	 * @since 3.3.0
	 */
	public static function register(): void {
		add_shortcode( 'emcp_menu', array( __CLASS__, 'render' ) );
	}

	/**
	 * Renders [emcp_menu location="…" | id="…" | slug="…" depth="…" class="…"
	 * container="nav|div|false" container_class="…" menu_id="…"].
	 *
	 * @since 3.3.0
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public static function render( $atts ) {
		$atts = shortcode_atts(
			array(
				'id'              => '',
				'slug'            => '',
				'location'        => '',
				'depth'           => 0,
				'class'           => '',
				'container'       => 'nav',
				'container_class' => '',
				'menu_id'         => '',
			),
			$atts,
			'emcp_menu'
		);

		$args = array(
			'echo'        => false,
			'fallback_cb' => false,
		);

		if ( '' !== $atts['location'] ) {
			$args['theme_location'] = sanitize_key( $atts['location'] );
		} elseif ( '' !== $atts['id'] ) {
			$args['menu'] = absint( $atts['id'] );
		} elseif ( '' !== $atts['slug'] ) {
			$args['menu'] = sanitize_title( $atts['slug'] );
		} else {
			return '';
		}

		$args['depth']     = (int) $atts['depth'];
		$container         = strtolower( trim( (string) $atts['container'] ) );
		$args['container'] = ( '' === $container || 'false' === $container || '0' === $container || 'none' === $container ) ? false : sanitize_key( $container );

		if ( '' !== $atts['container_class'] ) {
			$args['container_class'] = self::sanitize_classes( $atts['container_class'] );
		}
		if ( '' !== $atts['class'] ) {
			$args['menu_class'] = self::sanitize_classes( $atts['class'] );
		}
		if ( '' !== $atts['menu_id'] ) {
			$args['menu_id'] = sanitize_html_class( (string) $atts['menu_id'] );
		}

		$html = wp_nav_menu( $args );
		return is_string( $html ) ? $html : '';
	}

	/**
	 * Sanitizes a space-separated class list.
	 *
	 * @since 3.3.0
	 * @param string $value Raw class string.
	 * @return string
	 */
	private static function sanitize_classes( $value ) {
		$parts = preg_split( '/\s+/', trim( (string) $value ) );
		$clean = array();
		foreach ( (array) $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}
			$class = sanitize_html_class( $part );
			if ( '' !== $class ) {
				$clean[] = $class;
			}
		}
		return implode( ' ', $clean );
	}
}

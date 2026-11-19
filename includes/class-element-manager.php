<?php
/**
 * Elementor's Element Manager, as seen by the widget tools (#188).
 *
 * Elementor does not register a widget switched off in Elementor > Element
 * Manager (`elementor/widgets/is_widget_enabled`), so an element of that type is
 * stored but never rendered. The tools read the same list to report disabled
 * widgets and refuse to add one.
 *
 * @package EMCP_Tools
 * @since   3.22.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Element_Manager {

	/** Element names switched off in Element Manager. */
	public static function disabled(): array {
		$list = class_exists( '\Elementor\Modules\ElementManager\Options' )
			? \Elementor\Modules\ElementManager\Options::get_disabled_elements()
			: get_option( 'elementor_disabled_elements', array() );
		return array_values( array_filter( array_map( 'strval', (array) $list ), 'strlen' ) );
	}

	public static function is_disabled( string $name ): bool {
		return '' !== $name && in_array( $name, self::disabled(), true );
	}

	/** WP_Error for a disabled element, or null when it may be added. */
	public static function refusal( string $name ): ?\WP_Error {
		if ( ! self::is_disabled( $name ) ) {
			return null;
		}
		return new \WP_Error(
			'element_disabled',
			sprintf(
				/* translators: %s: element name */
				__( '"%s" is disabled in Elementor > Element Manager, so Elementor would not render it. Enable it there, or build with a different widget.', 'emcp-tools' ),
				$name
			),
			array( 'status' => 400 )
		);
	}
}

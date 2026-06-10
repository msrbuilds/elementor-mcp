<?php
/**
 * Auto-generates JSON Schema from Elementor widget control definitions.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates JSON Schema for widget settings based on Elementor's control registry.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Schema_Generator {

	/**
	 * Generates a JSON Schema for a widget type's settings.
	 *
	 * @since 1.0.0
	 *
	 * @param string $widget_type The widget type name (e.g. 'heading', 'button').
	 * @return array|\WP_Error JSON Schema array on success, WP_Error if widget not found.
	 */
	public function generate( string $widget_type ) {
		$widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
		$widget          = $widgets_manager->get_widget_types( $widget_type );

		if ( ! $widget ) {
			return new \WP_Error(
				'widget_not_found',
				sprintf(
					/* translators: %s: widget type name */
					__( 'Widget type "%s" not found.', 'elementor-mcp' ),
					$widget_type
				)
			);
		}

		$controls   = $this->get_full_controls( $widget );
		$properties = array();

		if ( is_array( $controls ) ) {
			foreach ( $controls as $control_id => $control ) {
				$control_type = $control['type'] ?? '';

				if ( Elementor_MCP_Control_Mapper::should_skip( $control_type ) ) {
					continue;
				}

				$schema_fragment = Elementor_MCP_Control_Mapper::map( $control );
				if ( ! empty( $schema_fragment ) ) {
					$properties[ $control_id ] = $schema_fragment;
				}
			}
		}

		return array(
			'type'        => 'object',
			'description' => sprintf(
				/* translators: %s: widget title */
				__( 'Settings for the %s widget.', 'elementor-mcp' ),
				$widget->get_title()
			),
			'properties'  => $properties,
		);
	}

	/**
	 * Returns a widget's COMPLETE control set, including the style controls
	 * (typography, colours, alignment, shadows…) that Elementor's "Optimized
	 * Control Loading" strips from get_controls() outside the editor.
	 *
	 * Elementor stores those controls separately and only merges them back when
	 * Performance::is_use_style_controls() is true — the same supported toggle
	 * its own CSS generator (core/files/css/base.php) uses. Without this, the
	 * schema is incomplete in non-editor contexts (notably the WP-CLI/stdio MCP
	 * bridge and any non-REST execution), so agents can't discover styling
	 * controls and settings validation can't recognise them.
	 *
	 * @since 2.2.0
	 *
	 * @param object $widget The Elementor widget instance.
	 * @return array The full controls array.
	 */
	private function get_full_controls( $widget ): array {
		$perf = '\Elementor\Core\Frontend\Performance';

		// Older Elementor without the Performance toggle: nothing to do.
		if ( ! class_exists( $perf ) || ! method_exists( $perf, 'set_use_style_controls' ) ) {
			$controls = $widget->get_controls();
			return is_array( $controls ) ? $controls : array();
		}

		$previous = method_exists( $perf, 'is_use_style_controls' ) ? $perf::is_use_style_controls() : false;
		$perf::set_use_style_controls( true );

		try {
			$controls = $widget->get_controls();
		} finally {
			// Always restore so we don't change CSS generation / rendering for
			// the rest of the request.
			$perf::set_use_style_controls( $previous );
		}

		return is_array( $controls ) ? $controls : array();
	}

	/**
	 * Generates schemas for all registered widgets.
	 *
	 * @since 1.0.0
	 *
	 * @return array Associative array of widget_type => JSON Schema.
	 */
	public function generate_all(): array {
		$widgets_manager = \Elementor\Plugin::$instance->widgets_manager;
		$widgets         = $widgets_manager->get_widget_types();
		$schemas         = array();

		foreach ( $widgets as $name => $widget ) {
			$schema = $this->generate( $name );
			if ( ! is_wp_error( $schema ) ) {
				$schemas[ $name ] = $schema;
			}
		}

		return $schemas;
	}
}

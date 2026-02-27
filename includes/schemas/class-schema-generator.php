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

		$controls   = $widget->get_controls();
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

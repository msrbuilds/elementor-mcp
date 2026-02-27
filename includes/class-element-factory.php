<?php
/**
 * Factory for building valid Elementor element JSON structures.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds properly structured Elementor element arrays.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Element_Factory {

	/**
	 * Creates a container element.
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The container settings.
	 * @param array $children Child elements array.
	 * @return array The container element structure.
	 */
	public function create_container( array $settings = array(), array $children = array() ): array {
		$defaults = array(
			'container_type' => 'flex',
			'content_width'  => 'boxed',
		);

		return array(
			'id'       => Elementor_MCP_Id_Generator::generate(),
			'elType'   => 'container',
			'isInner'  => false,
			'settings' => array_merge( $defaults, $settings ),
			'elements' => $children,
		);
	}

	/**
	 * Creates a widget element.
	 *
	 * @since 1.0.0
	 *
	 * @param string $widget_type The widget type name (e.g. 'heading', 'button').
	 * @param array  $settings    The widget settings.
	 * @return array The widget element structure.
	 */
	public function create_widget( string $widget_type, array $settings = array() ): array {
		return array(
			'id'         => Elementor_MCP_Id_Generator::generate(),
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'isInner'    => false,
			'settings'   => $settings,
			'elements'   => array(),
		);
	}

	/**
	 * Creates a section element (legacy layout).
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The section settings.
	 * @param array $columns  Child column elements.
	 * @return array The section element structure.
	 */
	public function create_section( array $settings = array(), array $columns = array() ): array {
		return array(
			'id'       => Elementor_MCP_Id_Generator::generate(),
			'elType'   => 'section',
			'isInner'  => false,
			'settings' => $settings,
			'elements' => $columns,
		);
	}

	/**
	 * Creates a column element (legacy layout).
	 *
	 * @since 1.0.0
	 *
	 * @param array $settings The column settings.
	 * @param array $widgets  Child widget elements.
	 * @return array The column element structure.
	 */
	public function create_column( array $settings = array(), array $widgets = array() ): array {
		$defaults = array(
			'_column_size' => 100,
		);

		return array(
			'id'       => Elementor_MCP_Id_Generator::generate(),
			'elType'   => 'column',
			'isInner'  => false,
			'settings' => array_merge( $defaults, $settings ),
			'elements' => $widgets,
		);
	}
}

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

		$merged = array_merge( $defaults, $settings );

		$is_grid   = ( 'grid' === ( $merged['container_type'] ?? 'flex' ) );
		$direction = $merged['flex_direction'] ?? '';
		$is_row    = ( 'row' === $direction || 'row-reverse' === $direction );

		// Auto-center alignment for flex column containers so widgets like
		// headings, icons, and text are centered on the page. Row
		// containers rely on Elementor's default flex behavior.
		// Grid containers handle alignment via grid_justify_items/grid_align_items.
		if ( ! $is_grid && ! $is_row && ! isset( $settings['align_items'] ) ) {
			$merged['align_items'] = 'center';
		}

		return array(
			'id'         => Elementor_MCP_Id_Generator::generate(),
			'elType'     => 'container',
			'widgetType' => null,
			'isInner'    => false,
			'settings'   => $merged,
			'elements'   => $children,
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
			'id'         => Elementor_MCP_Id_Generator::generate(),
			'elType'     => 'section',
			'widgetType' => null,
			'isInner'    => false,
			'settings'   => $settings,
			'elements'   => $columns,
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
			'id'         => Elementor_MCP_Id_Generator::generate(),
			'elType'     => 'column',
			'widgetType' => null,
			'isInner'    => false,
			'settings'   => array_merge( $defaults, $settings ),
			'elements'   => $widgets,
		);
	}

	// =========================================================================
	// Atomic elements (Elementor 4.0+)
	// =========================================================================

	/**
	 * Creates an atomic widget element (Elementor 4.0+).
	 *
	 * Atomic widgets use the same elType=widget structure but with $$type-wrapped
	 * settings and additional top-level keys (styles, interactions).
	 *
	 * @since 1.5.0
	 *
	 * @param string $widget_type The atomic widget type (e.g. 'e-heading', 'e-button').
	 * @param array  $settings    The widget settings (already $$type-wrapped).
	 * @return array The atomic widget element structure.
	 */
	public function create_atomic_widget( string $widget_type, array $settings = array() ): array {
		if ( ! isset( $settings['classes'] ) ) {
			$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
		}

		return array(
			'id'              => Elementor_MCP_Id_Generator::generate(),
			'elType'          => 'widget',
			'widgetType'      => $widget_type,
			'isInner'         => false,
			'settings'        => $settings,
			'elements'        => array(),
			'styles'          => array(),
			'interactions'    => array(),
			'editor_settings' => array(),
			'version'         => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
		);
	}

	/**
	 * Creates an atomic flexbox container (Elementor 4.0+).
	 *
	 * @since 1.5.0
	 *
	 * @param array  $settings    Container settings ($$type-wrapped props).
	 * @param array  $children    Child elements.
	 * @param array  $style_props Flat layout params to convert into a local style class.
	 * @return array The flexbox element structure.
	 */
	public function create_flexbox( array $settings = array(), array $children = array(), array $style_props = array() ): array {
		$id = Elementor_MCP_Id_Generator::generate();

		if ( ! isset( $settings['tag'] ) ) {
			$settings['tag'] = Elementor_MCP_Atomic_Props::string( 'div' );
		}
		if ( ! isset( $settings['classes'] ) ) {
			$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
		}

		$element = array(
			'id'              => $id,
			'elType'          => 'e-flexbox',
			'settings'        => $settings,
			'elements'        => $children,
			'isInner'         => false,
			'styles'          => array(),
			'interactions'    => array(),
			'editor_settings' => array(),
			'version'         => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
		);

		// Build and apply flex layout styles if provided.
		$flex_css = Elementor_MCP_Atomic_Styles::build_flex_props( $style_props );
		$common_css = Elementor_MCP_Atomic_Styles::build_common_props( $style_props );
		$all_css = array_merge( $flex_css, $common_css );

		if ( ! empty( $all_css ) ) {
			$style = Elementor_MCP_Atomic_Styles::create_local_class( $id, $all_css );
			Elementor_MCP_Atomic_Styles::apply_to_element( $element, $style['class_id'], $style['style_def'] );
		}

		return $element;
	}

	/**
	 * Creates an atomic div-block container (Elementor 4.0+).
	 *
	 * @since 1.5.0
	 *
	 * @param array $settings    Container settings ($$type-wrapped props).
	 * @param array $children    Child elements.
	 * @param array $style_props Flat style params to convert into a local style class.
	 * @return array The div-block element structure.
	 */
	public function create_div_block( array $settings = array(), array $children = array(), array $style_props = array() ): array {
		$id = Elementor_MCP_Id_Generator::generate();

		if ( ! isset( $settings['tag'] ) ) {
			$settings['tag'] = Elementor_MCP_Atomic_Props::string( 'div' );
		}
		if ( ! isset( $settings['classes'] ) ) {
			$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
		}

		$element = array(
			'id'              => $id,
			'elType'          => 'e-div-block',
			'settings'        => $settings,
			'elements'        => $children,
			'isInner'         => false,
			'styles'          => array(),
			'interactions'    => array(),
			'editor_settings' => array(),
			'version'         => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
		);

		$common_css = Elementor_MCP_Atomic_Styles::build_common_props( $style_props );

		if ( ! empty( $common_css ) ) {
			$style = Elementor_MCP_Atomic_Styles::create_local_class( $id, $common_css );
			Elementor_MCP_Atomic_Styles::apply_to_element( $element, $style['class_id'], $style['style_def'] );
		}

		return $element;
	}
}

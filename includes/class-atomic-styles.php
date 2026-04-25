<?php
/**
 * Atomic element style builder.
 *
 * Builds local style class structures for Elementor 4.0 atomic elements.
 * In v4, visual styling (flex layout, spacing, colors, typography) is stored
 * in a `styles` map on each element, referenced via class IDs in settings.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds local style classes for atomic elements.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Atomic_Styles {

	/**
	 * Creates a local style class structure for an element.
	 *
	 * @param string $element_id The element's ID.
	 * @param array  $props      CSS properties as $$type-wrapped values.
	 * @param string $breakpoint The responsive breakpoint (desktop, tablet, mobile).
	 * @param string $state      The CSS state (null, hover, focus, active).
	 * @return array { class_id: string, style_def: array } ready to merge into element.
	 */
	public static function create_local_class(
		string $element_id,
		array $props,
		string $breakpoint = 'desktop',
		?string $state = null
	): array {
		$class_id = 'e-' . $element_id . '-' . substr( bin2hex( random_bytes( 4 ) ), 0, 7 );

		$style_def = array(
			'id'       => $class_id,
			'label'    => 'local',
			'type'     => 'class',
			'variants' => array(
				array(
					'meta'       => array(
						'breakpoint' => $breakpoint,
						'state'      => $state,
					),
					'props'      => $props,
					'custom_css' => null,
				),
			),
		);

		return array(
			'class_id'  => $class_id,
			'style_def' => $style_def,
		);
	}

	/**
	 * Builds flexbox layout style props from AI-friendly parameters.
	 *
	 * Accepts plain values and returns $$type-wrapped CSS properties
	 * using CSS property names (kebab-case).
	 *
	 * @param array $params Flat layout parameters from AI agent input.
	 * @return array CSS props in $$type format (e.g., flex-direction, justify-content, etc.)
	 */
	public static function build_flex_props( array $params ): array {
		$props = array();

		$string_mappings = array(
			'direction'       => 'flex-direction',
			'flex_direction'  => 'flex-direction',
			'justify'         => 'justify-content',
			'justify_content' => 'justify-content',
			'align'           => 'align-items',
			'align_items'     => 'align-items',
			'wrap'            => 'flex-wrap',
			'flex_wrap'       => 'flex-wrap',
		);

		foreach ( $string_mappings as $input_key => $css_prop ) {
			if ( isset( $params[ $input_key ] ) && '' !== $params[ $input_key ] ) {
				$props[ $css_prop ] = Elementor_MCP_Atomic_Props::string( (string) $params[ $input_key ] );
			}
		}

		if ( isset( $params['gap'] ) ) {
			$unit = $params['gap_unit'] ?? 'px';
			$props['gap'] = Elementor_MCP_Atomic_Props::size( (float) $params['gap'], $unit );
		}

		if ( isset( $params['row_gap'] ) ) {
			$unit = $params['row_gap_unit'] ?? 'px';
			$props['row-gap'] = Elementor_MCP_Atomic_Props::size( (float) $params['row_gap'], $unit );
		}

		if ( isset( $params['column_gap'] ) ) {
			$unit = $params['column_gap_unit'] ?? 'px';
			$props['column-gap'] = Elementor_MCP_Atomic_Props::size( (float) $params['column_gap'], $unit );
		}

		return $props;
	}

	/**
	 * Builds common style props (padding, margin, background, etc.) from AI input.
	 *
	 * @param array $params Flat style parameters.
	 * @return array CSS props in $$type format.
	 */
	public static function build_common_props( array $params ): array {
		$props = array();

		$size_mappings = array(
			'padding_top'    => 'padding-block-start',
			'padding_right'  => 'padding-inline-end',
			'padding_bottom' => 'padding-block-end',
			'padding_left'   => 'padding-inline-start',
			'margin_top'     => 'margin-block-start',
			'margin_bottom'  => 'margin-block-end',
			'width'          => 'width',
			'min_height'     => 'min-height',
			'border_radius'  => 'border-radius',
		);

		foreach ( $size_mappings as $input_key => $css_prop ) {
			if ( isset( $params[ $input_key ] ) ) {
				$unit = $params[ $input_key . '_unit' ] ?? 'px';
				$props[ $css_prop ] = Elementor_MCP_Atomic_Props::size(
					(float) $params[ $input_key ],
					$unit
				);
			}
		}

		if ( isset( $params['padding'] ) ) {
			$unit = $params['padding_unit'] ?? 'px';
			$size_val = Elementor_MCP_Atomic_Props::size( (float) $params['padding'], $unit );
			$props['padding-block-start']  = $size_val;
			$props['padding-block-end']    = $size_val;
			$props['padding-inline-start'] = $size_val;
			$props['padding-inline-end']   = $size_val;
		}

		if ( isset( $params['background_color'] ) ) {
			$props['background-color'] = Elementor_MCP_Atomic_Props::string( $params['background_color'] );
		}

		if ( isset( $params['color'] ) ) {
			$props['color'] = Elementor_MCP_Atomic_Props::string( $params['color'] );
		}

		return $props;
	}

	/**
	 * Applies a local style class to an element structure.
	 *
	 * Adds the class to settings.classes and the style definition to the styles map.
	 *
	 * @param array  $element  The element array (passed by reference).
	 * @param string $class_id The style class ID.
	 * @param array  $style_def The style definition array.
	 */
	public static function apply_to_element( array &$element, string $class_id, array $style_def ): void {
		// Add class reference to settings.
		if ( ! isset( $element['settings']['classes'] ) ) {
			$element['settings']['classes'] = Elementor_MCP_Atomic_Props::classes( array() );
		}
		$element['settings']['classes']['value'][] = $class_id;

		// Add style definition to styles map.
		if ( ! isset( $element['styles'] ) ) {
			$element['styles'] = array();
		}
		$element['styles'][ $class_id ] = $style_def;
	}
}

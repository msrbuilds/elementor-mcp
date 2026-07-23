<?php
/**
 * Atomic element style builder.
 *
 * Builds local style class structures for Elementor 4.0 atomic elements.
 * In v4, visual styling (flex layout, spacing, colors, typography) is stored
 * in a `styles` map on each element, referenced via class IDs in settings.
 *
 * @package EMCP_Tools
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
class EMCP_Tools_Atomic_Styles {

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
		$class_id = self::mint_class_id( $element_id );

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
	 * Mints a fresh local style-class ID bound to a given element ID.
	 *
	 * v4 local classes are named `e-<element_id>-<hash>` and are meant to belong
	 * to a single element, so the ID must embed the owning element's ID.
	 *
	 * @param string $element_id The owning element's ID.
	 * @return string A unique local class ID.
	 */
	public static function mint_class_id( string $element_id ): string {
		return 'e-' . $element_id . '-' . substr( bin2hex( random_bytes( 4 ) ), 0, 7 );
	}

	/**
	 * Re-mints an element's local style classes in place.
	 *
	 * When an element is duplicated with a fresh `id`, its v4 local style classes
	 * (`e-<oldid>-<hash>`) still embed the SOURCE id and remain shared with the
	 * source — so a later styles-map write bleeds across both, and the editor's
	 * Style Origin popover shows doubled entries (issue #97). This regenerates the
	 * `styles` map keys (and each style def's `id`) against the element's current
	 * id, and repoints `settings.classes.value` from the old IDs to the new ones.
	 * Only classes defined in this element's own `styles` map are remapped; global
	 * classes (`g-…`) referenced in `settings.classes` are left untouched.
	 *
	 * @param array $element The element array (modified by reference). Must already
	 *                       carry its new `id`.
	 */
	public static function remap_local_classes( array &$element ): void {
		if ( empty( $element['styles'] ) || ! is_array( $element['styles'] ) ) {
			return;
		}

		$new_id = isset( $element['id'] ) ? (string) $element['id'] : '';
		if ( '' === $new_id ) {
			return;
		}

		$map        = array();
		$new_styles = array();
		foreach ( $element['styles'] as $old_class_id => $style_def ) {
			$new_class_id                  = self::mint_class_id( $new_id );
			$map[ (string) $old_class_id ] = $new_class_id;

			if ( is_array( $style_def ) ) {
				$style_def['id'] = $new_class_id;
			}
			$new_styles[ $new_class_id ] = $style_def;
		}
		$element['styles'] = $new_styles;

		// Repoint the element's own local-class references; leave globals alone.
		if ( isset( $element['settings']['classes']['value'] ) && is_array( $element['settings']['classes']['value'] ) ) {
			$element['settings']['classes']['value'] = array_values( array_map(
				static function ( $cid ) use ( $map ) {
					return $map[ (string) $cid ] ?? $cid;
				},
				$element['settings']['classes']['value']
			) );
		}
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
				$props[ $css_prop ] = EMCP_Tools_Atomic_Props::string( (string) $params[ $input_key ] );
			}
		}

		if ( isset( $params['gap'] ) ) {
			$unit = $params['gap_unit'] ?? 'px';
			$props['gap'] = EMCP_Tools_Atomic_Props::size( (float) $params['gap'], $unit );
		}

		if ( isset( $params['row_gap'] ) ) {
			$unit = $params['row_gap_unit'] ?? 'px';
			$props['row-gap'] = EMCP_Tools_Atomic_Props::size( (float) $params['row_gap'], $unit );
		}

		if ( isset( $params['column_gap'] ) ) {
			$unit = $params['column_gap_unit'] ?? 'px';
			$props['column-gap'] = EMCP_Tools_Atomic_Props::size( (float) $params['column_gap'], $unit );
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

		// These are real Elementor style props that take a plain size value.
		$size_mappings = array(
			'width'         => 'width',
			'min_height'    => 'min-height',
			'border_radius' => 'border-radius',
		);

		foreach ( $size_mappings as $input_key => $css_prop ) {
			if ( isset( $params[ $input_key ] ) ) {
				$unit = $params[ $input_key . '_unit' ] ?? 'px';
				$props[ $css_prop ] = EMCP_Tools_Atomic_Props::size(
					(float) $params[ $input_key ],
					$unit
				);
			}
		}

		// Padding and margin are `dimensions` shorthands. Elementor has no
		// per-side `padding-block-start` style prop, so building those keys
		// individually is silently discarded on save. A single `padding` value
		// sets all four sides; the *_top/_right/_bottom/_left inputs set them
		// per side. `padding` shorthand overrides any per-side padding input.
		$padding = self::build_dimensions(
			$params,
			'padding',
			array(
				'block-start'  => 'padding_top',
				'block-end'    => 'padding_bottom',
				'inline-start' => 'padding_left',
				'inline-end'   => 'padding_right',
			)
		);
		if ( null !== $padding ) {
			$props['padding'] = $padding;
		}

		$margin = self::build_dimensions(
			$params,
			'margin',
			array(
				'block-start'  => 'margin_top',
				'block-end'    => 'margin_bottom',
				'inline-start' => 'margin_left',
				'inline-end'   => 'margin_right',
			)
		);
		if ( null !== $margin ) {
			$props['margin'] = $margin;
		}

		// Elementor stores background as a `background` prop with a color field,
		// not a `background-color` prop, and the color must be a color prop.
		if ( isset( $params['background_color'] ) ) {
			$props['background'] = EMCP_Tools_Atomic_Props::background_color( (string) $params['background_color'] );
		}

		// The `color` style prop is a Color_Prop_Type: it needs a color
		// envelope, not a string one.
		if ( isset( $params['color'] ) ) {
			$props['color'] = EMCP_Tools_Atomic_Props::color( (string) $params['color'] );
		}

		return $props;
	}

	/**
	 * Builds a `padding`/`margin` dimensions prop from AI input.
	 *
	 * A single shorthand value (`padding`) sets all four sides; per-side inputs
	 * (`padding_top`, …) fill individual sides. The shorthand wins if both are
	 * present. Returns null when neither is supplied.
	 *
	 * @since 3.6.2
	 *
	 * @param array  $params     Raw input params.
	 * @param string $shorthand  Input key for the all-sides value, e.g. 'padding'.
	 * @param array  $side_map   Map of dimension side => input key.
	 * @return array|null Typed dimensions prop, or null when nothing was set.
	 */
	private static function build_dimensions( array $params, string $shorthand, array $side_map ): ?array {
		if ( isset( $params[ $shorthand ] ) ) {
			$unit = $params[ $shorthand . '_unit' ] ?? 'px';
			$val  = EMCP_Tools_Atomic_Props::size( (float) $params[ $shorthand ], $unit );

			return EMCP_Tools_Atomic_Props::dimensions(
				array(
					'block-start'  => $val,
					'block-end'    => $val,
					'inline-start' => $val,
					'inline-end'   => $val,
				)
			);
		}

		$sides = array();
		foreach ( $side_map as $dim_side => $input_key ) {
			if ( isset( $params[ $input_key ] ) ) {
				$unit               = $params[ $input_key . '_unit' ] ?? 'px';
				$sides[ $dim_side ] = EMCP_Tools_Atomic_Props::size( (float) $params[ $input_key ], $unit );
			}
		}

		return empty( $sides ) ? null : EMCP_Tools_Atomic_Props::dimensions( $sides );
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
			$element['settings']['classes'] = EMCP_Tools_Atomic_Props::classes( array() );
		}
		$element['settings']['classes']['value'][] = $class_id;

		// Add style definition to styles map.
		if ( ! isset( $element['styles'] ) ) {
			$element['styles'] = array();
		}
		$element['styles'][ $class_id ] = $style_def;
	}
}

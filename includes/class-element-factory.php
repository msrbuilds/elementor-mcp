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
			'content_width'  => 'full',
		);

		$merged = array_merge( $defaults, self::normalize_settings( $settings ) );

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
			'settings'   => self::normalize_settings( $settings ),
			'elements'   => array(),
		);
	}

	/**
	 * Normalises raw settings before saving to Elementor.
	 *
	 * Fixes the most common AI-agent mistakes that silently break rendering:
	 * - Auto-adds required companion keys (background_background, border_border, etc.)
	 * - Converts bare numeric dimension values to {size, unit} objects
	 * - Renames alias keys to their correct Elementor names
	 *
	 * Safe to call multiple times (idempotent).
	 *
	 * @since 2.1.0
	 *
	 * @param array $settings Raw settings from the AI tool call.
	 * @return array Normalised settings ready for Elementor.
	 */
	public static function normalize_settings( array $settings ): array {

		// ── 1. Key aliases ────────────────────────────────────────────────────
		// justify_content → flex_justify_content
		if ( isset( $settings['justify_content'] ) && ! isset( $settings['flex_justify_content'] ) ) {
			$settings['flex_justify_content'] = $settings['justify_content'];
			unset( $settings['justify_content'] );
		}
		// align_content → flex_align_content
		if ( isset( $settings['align_content'] ) && ! isset( $settings['flex_align_content'] ) ) {
			$settings['flex_align_content'] = $settings['align_content'];
			unset( $settings['align_content'] );
		}
		// gap shorthand → column_gap + row_gap (Elementor uses separate keys)
		if ( isset( $settings['gap'] ) && ! isset( $settings['column_gap'] ) && ! isset( $settings['row_gap'] ) ) {
			$gap_value               = $settings['gap'];
			$settings['column_gap']  = $gap_value;
			$settings['row_gap']     = $gap_value;
			unset( $settings['gap'] );
		}

		// ── 2. Background companion ───────────────────────────────────────────
		// background_color / background_image require background_background=classic
		if (
			( isset( $settings['background_color'] ) || isset( $settings['background_image'] ) ) &&
			! isset( $settings['background_background'] )
		) {
			$settings['background_background'] = 'classic';
		}
		// Gradient type requires background_background=gradient
		if ( isset( $settings['background_gradient_type'] ) && ! isset( $settings['background_background'] ) ) {
			$settings['background_background'] = 'gradient';
		}

		// ── 3. Border companion ───────────────────────────────────────────────
		// border_color or border_width require border_border=solid
		if (
			( isset( $settings['border_color'] ) || isset( $settings['border_width'] ) ) &&
			! isset( $settings['border_border'] )
		) {
			$settings['border_border'] = 'solid';
		}

		// ── 4. Box-shadow companion ───────────────────────────────────────────
		if ( isset( $settings['box_shadow_box_shadow'] ) && ! isset( $settings['box_shadow_box_shadow_type'] ) ) {
			$settings['box_shadow_box_shadow_type'] = 'yes';
		}
		// Hover box shadow
		if ( isset( $settings['_box_shadow_hover_box_shadow'] ) && ! isset( $settings['_box_shadow_hover_box_shadow_type'] ) ) {
			$settings['_box_shadow_hover_box_shadow_type'] = 'yes';
		}

		// ── 5. Text-shadow companion ──────────────────────────────────────────
		if ( isset( $settings['text_shadow_text_shadow'] ) && ! isset( $settings['text_shadow_text_shadow_type'] ) ) {
			$settings['text_shadow_text_shadow_type'] = 'yes';
		}
		// Title text shadow (heading widget)
		if ( isset( $settings['title_text_shadow_text_shadow'] ) && ! isset( $settings['title_text_shadow_text_shadow_type'] ) ) {
			$settings['title_text_shadow_text_shadow_type'] = 'yes';
		}

		// ── 5b. Text-stroke companion ─────────────────────────────────────────
		if (
			( isset( $settings['text_stroke_stroke_width'] ) || isset( $settings['text_stroke_stroke_color'] ) ) &&
			! isset( $settings['text_stroke_text_stroke'] )
		) {
			$settings['text_stroke_text_stroke'] = 'yes';
		}

		// ── 5c. Hover background companion ────────────────────────────────────
		if (
			( isset( $settings['background_hover_color'] ) || isset( $settings['hover_background_color'] ) ) &&
			! isset( $settings['background_hover_background'] )
		) {
			$settings['background_hover_background'] = 'classic';
		}
		// Hover gradient
		if ( isset( $settings['background_hover_gradient_type'] ) && ! isset( $settings['background_hover_background'] ) ) {
			$settings['background_hover_background'] = 'gradient';
		}

		// ── 5d. Hover border companion ────────────────────────────────────────
		if (
			( isset( $settings['hover_border_color'] ) || isset( $settings['border_hover_color'] ) ) &&
			! isset( $settings['border_hover_border'] )
		) {
			$settings['border_hover_border'] = 'solid';
		}

		// ── 5e. Background overlay companion ──────────────────────────────────
		if (
			( isset( $settings['background_overlay_color'] ) || isset( $settings['background_overlay_image'] ) ) &&
			! isset( $settings['background_overlay_background'] )
		) {
			$settings['background_overlay_background'] = 'classic';
		}
		if ( isset( $settings['background_overlay_gradient_type'] ) && ! isset( $settings['background_overlay_background'] ) ) {
			$settings['background_overlay_background'] = 'gradient';
		}

		// ── 6. Typography companion ───────────────────────────────────────────
		$typography_trigger_keys = array(
			'typography_font_family',
			'typography_font_size',
			'typography_font_weight',
			'typography_font_style',
			'typography_text_decoration',
			'typography_text_transform',
			'typography_line_height',
			'typography_letter_spacing',
			'typography_word_spacing',
		);
		foreach ( $typography_trigger_keys as $tkey ) {
			if ( isset( $settings[ $tkey ] ) && ! isset( $settings['typography_typography'] ) ) {
				$settings['typography_typography'] = 'custom';
				break;
			}
		}

		// ── 7. Dimension normalisation (bare numbers → {size, unit}) ──────────
		$dimension_keys = array(
			'padding',
			'margin',
			'border_width',
			'border_radius',
			'min_height',
			'width',
			'height',
			'max_width',
			'column_gap',
			'row_gap',
			'space',
		);
		foreach ( $dimension_keys as $dkey ) {
			if ( isset( $settings[ $dkey ] ) && is_numeric( $settings[ $dkey ] ) ) {
				$settings[ $dkey ] = array(
					'size' => (float) $settings[ $dkey ],
					'unit' => 'px',
				);
			}
		}

		// Typography font size
		if ( isset( $settings['typography_font_size'] ) && is_numeric( $settings['typography_font_size'] ) ) {
			$settings['typography_font_size'] = array(
				'size' => (float) $settings['typography_font_size'],
				'unit' => 'px',
			);
		}

		// Typography letter / word spacing
		foreach ( array( 'typography_letter_spacing', 'typography_word_spacing' ) as $spkey ) {
			if ( isset( $settings[ $spkey ] ) && is_numeric( $settings[ $spkey ] ) ) {
				$settings[ $spkey ] = array(
					'size' => (float) $settings[ $spkey ],
					'unit' => 'px',
				);
			}
		}

		// Typography line height
		if ( isset( $settings['typography_line_height'] ) && is_numeric( $settings['typography_line_height'] ) ) {
			$settings['typography_line_height'] = array(
				'size' => (float) $settings['typography_line_height'],
				'unit' => 'em',
			);
		}

		// Opacity (0-100 range or 0-1 float — normalise to 0-100 integer as Elementor expects)
		if ( isset( $settings['opacity'] ) ) {
			$opacity = (float) $settings['opacity'];
			if ( $opacity <= 1.0 && $opacity >= 0 ) {
				$settings['opacity'] = (int) round( $opacity * 100 );
			}
		}

		return $settings;
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
}

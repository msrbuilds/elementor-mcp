<?php
/**
 * Factory for building valid Elementor element JSON structures.
 *
 * @package EMCP_Tools
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
class EMCP_Tools_Element_Factory {

	/**
	 * Classic dimension keys the partial-side guard applies to, including the
	 * `_` advanced-tab prefix and responsive/state suffixes (`padding_tablet`).
	 */
	private const GUARDED_DIMENSION_KEY = '/^_?(?:margin|padding|border_radius|border_width)(?:_[a-z0-9_]+)?$/';

	/** The four sides a classic dimension value needs for Elementor to emit CSS. */
	private const FOUR_SIDES = array( 'top', 'right', 'bottom', 'left' );

	/**
	 * Clean incoming classic settings before they are written, and explain what
	 * was done. Every MCP write path that takes free-form element settings runs
	 * its settings through here.
	 *
	 * Elementor's CSS generator drops the WHOLE rule for an element and
	 * breakpoint when one side of a dimension value is blank, valid sides
	 * included (#134, #151). So a margin/padding/border_radius/border_width
	 * value (any prefix or suffix) with one to three blank or missing sides is
	 * never written as sent:
	 *
	 *  - Update ($stored is the element's current settings): the blank sides are
	 *    filled from the stored value of the same key when that value has all
	 *    four sides and the same unit (missing = px). Otherwise the key is left
	 *    out of the write, so the stored value stays exactly as it was.
	 *    Element settings and page settings both use this path.
	 *  - Create ($stored is null): the key is left out.
	 *
	 * All four sides blank means "unset" and passes through, as do complete
	 * values and atomic `$$type` values. The caller's `isLinked` is kept.
	 * Grid defaults are intentionally left to Elementor (#135); creating a grid
	 * without grid_rows_grid only adds a warning.
	 *
	 * @since 3.18.0
	 *
	 * @param array      $settings Incoming settings.
	 * @param array|null $stored   Current settings of the element being updated,
	 *                             or null when the element is being created.
	 * @return array{settings: array, warnings: string[]}
	 */
	public static function guard_settings( array $settings, ?array $stored = null ): array {
		$warnings = array();
		$creating = null === $stored;

		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) || ! preg_match( self::GUARDED_DIMENSION_KEY, $key ) || ! is_array( $value ) || isset( $value['$$type'] ) ) {
				continue;
			}
			$blank = self::blank_sides( $value );
			if ( 0 === count( $blank ) || 4 === count( $blank ) ) {
				continue;
			}
			$sides = implode( ', ', $blank );

			$saved    = $creating ? null : ( $stored[ $key ] ?? null );
			$complete = is_array( $saved ) && ! isset( $saved['$$type'] ) && 0 === count( self::blank_sides( $saved ) );

			// Filling across units would change what the saved sides mean. A
			// missing unit is Elementor's default, px, on either side.
			$unit       = self::dimension_unit( $value );
			$saved_unit = $complete ? self::dimension_unit( $saved ) : '';
			if ( $complete && $unit !== $saved_unit ) {
				unset( $settings[ $key ] );
				$warnings[] = sprintf( '%s had blank sides (%s) and a different unit from the saved value (%s vs %s); it was not written and the saved value is unchanged. Supply all four sides (use 0 where intended).', $key, $sides, $unit, $saved_unit );
				continue;
			}

			if ( $complete ) {
				foreach ( $blank as $side ) {
					$value[ $side ] = $saved[ $side ];
				}
				if ( ! isset( $value['unit'] ) && isset( $saved['unit'] ) ) {
					$value['unit'] = $saved['unit'];
				}
				$settings[ $key ] = $value;
				$warnings[]       = sprintf( '%s had blank sides (%s): filled from the saved value.', $key, $sides );
				continue;
			}

			unset( $settings[ $key ] );
			$warnings[] = $creating
				? sprintf( '%s had blank sides (%s) and was not written, because Elementor drops the whole CSS rule when a side is blank. Supply all four sides (use 0 where intended).', $key, $sides )
				: sprintf( '%s had blank sides (%s) and was not written; the saved value is unchanged. Supply all four sides (use 0 where intended).', $key, $sides );
		}

		if ( $creating && 'grid' === ( $settings['container_type'] ?? '' ) && ! isset( $settings['grid_rows_grid'] ) ) {
			$warnings[] = 'Elementor defaults grid_rows_grid to 2 rows. For a single-row grid, explicitly set grid_rows_grid: {"unit":"fr","size":1} inside settings.';
		}

		return array(
			'settings' => $settings,
			'warnings' => $warnings,
		);
	}

	/**
	 * Warnings guard_settings() would report for these settings.
	 *
	 * Kept for callers that only need the messages; write paths must use
	 * guard_settings() and write the settings it returns.
	 *
	 * @param array $settings Incoming settings.
	 * @param bool  $creating True for a new element (no stored value to fill from).
	 * @return string[]
	 */
	public static function settings_warnings( array $settings, bool $creating = false ): array {
		return self::guard_settings( $settings, $creating ? null : array() )['warnings'];
	}

	/**
	 * Unit of a classic dimension value; missing or blank means px.
	 *
	 * @param array $value Dimension value.
	 * @return string
	 */
	private static function dimension_unit( array $value ): string {
		$unit = isset( $value['unit'] ) && is_string( $value['unit'] ) ? $value['unit'] : '';
		return '' === $unit ? 'px' : $unit;
	}

	/**
	 * Sides of a classic dimension value that are missing or blank.
	 *
	 * @param array $value Dimension value.
	 * @return string[]
	 */
	private static function blank_sides( array $value ): array {
		$blank = array();
		foreach ( self::FOUR_SIDES as $side ) {
			if ( ! isset( $value[ $side ] ) || '' === $value[ $side ] ) {
				$blank[] = $side;
			}
		}
		return $blank;
	}

	/**
	 * Sub-keys of a classic `dimensions` / `gaps` control. Elementor's editor
	 * always serialises them as strings ("40", not 40). The CSS is the same
	 * either way, but the editor's Layout panel hydrates strictly and shows 0
	 * for a numeric side (#146).
	 */
	private const DIMENSION_SIDES = array( 'top', 'right', 'bottom', 'left', 'column', 'row' );

	/**
	 * Cast numeric dimension sides to the string form the editor expects.
	 *
	 * Only arrays that look like a classic dimension value are touched: they
	 * carry `unit` plus at least one side key and no `$$type` wrapper (atomic
	 * props have their own typed shape). Slider `size` values stay numeric,
	 * matching the editor. Nested arrays (repeaters) are walked too.
	 *
	 * @since 3.17.1
	 *
	 * @param array $settings Element settings.
	 * @return array
	 */
	public static function normalize_dimension_settings( array $settings ): array {
		foreach ( $settings as $key => $value ) {
			if ( ! is_array( $value ) || isset( $value['$$type'] ) ) {
				continue;
			}
			if ( self::looks_like_dimensions( $value ) ) {
				foreach ( self::DIMENSION_SIDES as $side ) {
					if ( isset( $value[ $side ] ) && ( is_int( $value[ $side ] ) || is_float( $value[ $side ] ) ) ) {
						$value[ $side ] = (string) $value[ $side ];
					}
				}
				$settings[ $key ] = $value;
				continue;
			}
			$settings[ $key ] = self::normalize_dimension_settings( $value );
		}
		return $settings;
	}

	/**
	 * Apply normalize_dimension_settings() to every element in a tree.
	 *
	 * @since 3.17.1
	 *
	 * @param array $elements Element tree.
	 * @return array
	 */
	public static function normalize_dimension_tree( array $elements ): array {
		foreach ( $elements as &$element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( ! empty( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$element['settings'] = self::normalize_dimension_settings( $element['settings'] );
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::normalize_dimension_tree( $element['elements'] );
			}
		}
		unset( $element );
		return $elements;
	}

	private static function looks_like_dimensions( array $value ): bool {
		if ( ! array_key_exists( 'unit', $value ) ) {
			return false;
		}
		foreach ( self::DIMENSION_SIDES as $side ) {
			if ( array_key_exists( $side, $value ) ) {
				return true;
			}
		}
		return false;
	}

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
		$settings = self::normalize_container_settings( $settings );

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
		if ( ! $is_grid && ! $is_row && ! isset( $settings['flex_align_items'] ) ) {
			$merged['flex_align_items'] = 'center';
		}

		return array(
			'id'         => EMCP_Tools_Id_Generator::generate(),
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
			'id'         => EMCP_Tools_Id_Generator::generate(),
			'elType'     => 'widget',
			'widgetType' => $widget_type,
			'isInner'    => false,
			'settings'   => self::normalize_background_settings( $settings ),
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
			'id'         => EMCP_Tools_Id_Generator::generate(),
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
			'id'         => EMCP_Tools_Id_Generator::generate(),
			'elType'     => 'column',
			'widgetType' => null,
			'isInner'    => false,
			'settings'   => array_merge( $defaults, $settings ),
			'elements'   => $widgets,
		);
	}

	/**
	 * Map of MCP-shorthand container keys to the keys Elementor's flex group
	 * actually reads. Without this remap, settings like `justify_content` and
	 * `align_items` are persisted under names Elementor's CSS generator never
	 * looks at, so the corresponding `--justify-content` / `--align-items`
	 * custom properties never get emitted and the container renders with
	 * default alignment on the front-end (issue #32).
	 *
	 * @since 1.4.4
	 *
	 * @var array<string, string>
	 */
	private const CONTAINER_KEY_ALIASES = array(
		'justify_content' => 'flex_justify_content',
		'align_items'     => 'flex_align_items',
		'align_content'   => 'flex_align_content',
	);

	/**
	 * Rewrites the unprefixed flex shorthand keys (`justify_content`,
	 * `align_items`, `align_content`) to the prefixed keys that Elementor's
	 * container schema reads.
	 *
	 * Caller-supplied prefixed keys win over the aliased shorthand if both
	 * are provided in the same payload.
	 *
	 * @since 1.4.4
	 *
	 * @param array $settings Raw container settings.
	 * @return array Settings with shorthand keys remapped.
	 */
	public static function normalize_container_settings( array $settings ): array {
		foreach ( self::CONTAINER_KEY_ALIASES as $shorthand => $flex_key ) {
			if ( ! array_key_exists( $shorthand, $settings ) ) {
				continue;
			}

			if ( ! array_key_exists( $flex_key, $settings ) ) {
				$settings[ $flex_key ] = $settings[ $shorthand ];
			}

			unset( $settings[ $shorthand ] );
		}

		return self::normalize_background_settings( $settings );
	}

	/**
	 * Coerces the intuitive-but-wrong background shorthand that agents (weak
	 * local models especially) routinely emit into the flat keys Elementor's
	 * background group control actually reads. Three fixes:
	 *
	 *  1. A nested `background` group — `background => { background_image, size,
	 *     ... }` — is flattened to top-level `background_*` keys. Elementor has
	 *     no `background` group setting, so a nested object is silently dropped.
	 *  2. `background_image` given as an array of `{ id, url }` objects (the
	 *     model mirrors media-repeater shape) is unwrapped to the single
	 *     `{ id, url }` object the control expects.
	 *  3. When an image or colour is present but the `background_background`
	 *     activator is missing, it is set to `classic` — without the activator
	 *     Elementor never renders the background at all.
	 *
	 * Idempotent and non-destructive: caller-supplied flat keys always win over
	 * anything lifted out of the nested group, and settings with no background
	 * keys pass through untouched.
	 *
	 * @since 3.5.1
	 *
	 * @param array $settings Raw element settings.
	 * @return array Settings with background shorthand normalized.
	 */
	public static function normalize_background_settings( array $settings ): array {
		// 1. Flatten a nested `background` group into top-level keys.
		if ( isset( $settings['background'] ) && is_array( $settings['background'] ) ) {
			$nested = $settings['background'];
			// Only treat it as a group if its keys look like background_* controls
			// (guards against an element that legitimately stores something else
			// under `background`, which Elementor does not).
			$looks_like_group = false;
			foreach ( $nested as $k => $v ) {
				if ( is_string( $k ) && 0 === strpos( $k, 'background' ) ) {
					$looks_like_group = true;
					break;
				}
			}
			if ( $looks_like_group ) {
				unset( $settings['background'] );
				foreach ( $nested as $k => $v ) {
					if ( ! array_key_exists( $k, $settings ) ) {
						$settings[ $k ] = $v;
					}
				}
			}
		}

		// 2. Unwrap a `background_image` array-of-objects to a single object.
		if ( isset( $settings['background_image'] ) && is_array( $settings['background_image'] )
			&& isset( $settings['background_image'][0] ) && is_array( $settings['background_image'][0] ) ) {
			$settings['background_image'] = $settings['background_image'][0];
		}

		// 3. Inject the `classic` activator when a background exists without one.
		$has_bg = ( ! empty( $settings['background_image'] ) || ! empty( $settings['background_color'] ) );
		if ( $has_bg && empty( $settings['background_background'] ) ) {
			$settings['background_background'] = 'classic';
		}

		return $settings;
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
			$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
		}

		return array(
			'id'              => EMCP_Tools_Id_Generator::generate(),
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
		$id = EMCP_Tools_Id_Generator::generate();

		if ( ! isset( $settings['tag'] ) ) {
			$settings['tag'] = EMCP_Tools_Atomic_Props::string( 'div' );
		}
		if ( ! isset( $settings['classes'] ) ) {
			$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
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
		$flex_css = EMCP_Tools_Atomic_Styles::build_flex_props( $style_props );
		$common_css = EMCP_Tools_Atomic_Styles::build_common_props( $style_props );
		$all_css = array_merge( $flex_css, $common_css );

		if ( ! empty( $all_css ) ) {
			$style = EMCP_Tools_Atomic_Styles::create_local_class( $id, $all_css );
			EMCP_Tools_Atomic_Styles::apply_to_element( $element, $style['class_id'], $style['style_def'] );
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
		$id = EMCP_Tools_Id_Generator::generate();

		if ( ! isset( $settings['tag'] ) ) {
			$settings['tag'] = EMCP_Tools_Atomic_Props::string( 'div' );
		}
		if ( ! isset( $settings['classes'] ) ) {
			$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
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

		$common_css = EMCP_Tools_Atomic_Styles::build_common_props( $style_props );

		if ( ! empty( $common_css ) ) {
			$style = EMCP_Tools_Atomic_Styles::create_local_class( $id, $common_css );
			EMCP_Tools_Atomic_Styles::apply_to_element( $element, $style['class_id'], $style['style_def'] );
		}

		return $element;
	}
}

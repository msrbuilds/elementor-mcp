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

	/** The four box sides of a classic `dimensions` control. */
	private const SIDES = array( 'top', 'right', 'bottom', 'left' );

	/** Extra sub-keys of a classic `gaps` control (column / row gap). */
	private const GAP_SIDES = array( 'column', 'row' );

	/**
	 * Keys guarded even when the caller left out `unit`: the classic box
	 * controls with the `_` advanced-tab prefix and any responsive or state
	 * suffix (`padding_tablet`, `_margin_mobile`, `border_radius_hover`).
	 * Any other key is guarded by value shape (see is_guarded_dimension()),
	 * which covers prefixed widget controls such as `button_text_padding`.
	 */
	private const CLASSIC_BOX_KEY = '/^_?(?:margin|padding|border_radius|border_width)(?:_[a-z0-9_]+)?$/';

	/** Legacy Section margin is `allowed_dimensions => 'vertical'`. */
	private const SECTION_MARGIN_KEY = '/^margin(?:_[a-z0-9_]+)?$/';

	/**
	 * Clean incoming classic settings before they are written, and explain what
	 * was done. Every MCP write path that takes caller settings runs them
	 * through here (or through guard_tree() for a whole element tree).
	 *
	 * Elementor's CSS generator drops the WHOLE rule for an element and
	 * breakpoint when one side of a dimension value is blank, valid sides
	 * included, and a blank side is never inherited (#134, #151). So a classic
	 * dimension value with some, but not all, of its required sides blank is
	 * never written as sent:
	 *
	 *  - Update ($stored is the current settings of the element or page): the
	 *    blank sides are filled from the stored value of the same key when that
	 *    value is complete and has the same unit (missing or blank = px).
	 *    Otherwise the key is left out, so the stored value stays as it was.
	 *  - Create ($stored is null): the key is left out, unless $preset holds a
	 *    complete value for it to fill from (the full_bleed preset).
	 *
	 * Required sides follow the control's `allowed_dimensions` ('vertical',
	 * 'horizontal' or a side list), read from the live control when Elementor
	 * is loaded; the legacy Section margin ('vertical') is known without it.
	 * All required sides blank means "unset" and passes through, as do atomic
	 * `$$type` values and gap values. A blank unit on a written value becomes
	 * px. Grid defaults are left to Elementor (#135); creating a grid without
	 * grid_rows_grid only adds a warning.
	 *
	 * @since 3.18.0
	 *
	 * @param array      $settings Incoming settings.
	 * @param array|null $stored   Current settings of the element or page being
	 *                             updated, or null when creating.
	 * @param array      $context  Optional: `elType` and `widgetType`, to read the
	 *                             control's allowed_dimensions. Any other key is
	 *                             ignored, so a whole element can be passed.
	 * @param array|null $preset   Create mode only: settings to fill blank
	 *                             sides from (the full_bleed preset). Never
	 *                             taken from caller data.
	 * @param string     $preset_label Name of $preset in warnings.
	 * @return array{settings: array, warnings: string[]}
	 */
	public static function guard_settings( array $settings, ?array $stored = null, array $context = array(), ?array $preset = null, string $preset_label = 'preset' ): array {
		$out = self::guard_dimensions( $settings, $stored, self::control_context( $context ), $preset, $preset_label );

		if ( null === $stored && 'grid' === ( $out['settings']['container_type'] ?? '' ) && ! isset( $out['settings']['grid_rows_grid'] ) ) {
			$out['warnings'][] = 'Elementor defaults grid_rows_grid to 2 rows. For a single-row grid, explicitly set grid_rows_grid: {"unit":"fr","size":1} inside settings.';
		}

		return $out;
	}

	/**
	 * Run guard_settings() in create mode over every element of a caller tree
	 * (import-template, create-page content). Warnings carry the element id.
	 *
	 * @since 3.18.0
	 *
	 * @param array $elements Element tree.
	 * @return array{elements: array, warnings: string[]}
	 */
	public static function guard_tree( array $elements ): array {
		$warnings = array();
		foreach ( $elements as $index => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			if ( ! empty( $element['settings'] ) && is_array( $element['settings'] ) ) {
				$out                 = self::guard_dimensions( $element['settings'], null, self::control_context( $element ) );
				$element['settings'] = $out['settings'];
				$id                  = isset( $element['id'] ) && is_scalar( $element['id'] ) ? (string) $element['id'] : '';
				foreach ( $out['warnings'] as $warning ) {
					$warnings[] = '' === $id ? $warning : $id . ': ' . $warning;
				}
			}
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$child               = self::guard_tree( $element['elements'] );
				$element['elements'] = $child['elements'];
				$warnings            = array_merge( $warnings, $child['warnings'] );
			}
			$elements[ $index ] = $element;
		}
		return array(
			'elements' => $elements,
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
	 * The dimension part of guard_settings(), without the grid warning.
	 *
	 * @param array      $settings     Incoming settings.
	 * @param array|null $stored       Stored settings, or null when creating.
	 * @param array      $context      `elType` / `widgetType` only.
	 * @param array|null $preset       Create mode fill source, or null.
	 * @param string     $preset_label Name of $preset in warnings.
	 * @return array{settings: array, warnings: string[]}
	 */
	private static function guard_dimensions( array $settings, ?array $stored, array $context, ?array $preset = null, string $preset_label = 'preset' ): array {
		$warnings = array();
		$creating = null === $stored;
		$preset   = $creating && is_array( $preset ) ? $preset : array();
		$label    = $creating ? $preset_label : 'saved value';

		foreach ( $settings as $key => $value ) {
			if ( ! is_string( $key ) || ! is_array( $value ) || ! self::is_guarded_dimension( $key, $value ) ) {
				continue;
			}
			$required = self::required_sides( $key, $context );
			$blank    = self::blank_sides( $value, $required );
			if ( count( $blank ) === count( $required ) ) {
				continue; // Every required side blank: "unset", written as sent.
			}
			if ( 0 === count( $blank ) ) {
				$settings[ $key ] = self::with_resolved_unit( $value );
				continue;
			}
			$sides  = implode( ', ', $blank );
			$supply = 4 === count( $required ) ? 'all four sides' : implode( ' and ', $required );

			$saved    = $creating ? ( $preset[ $key ] ?? null ) : ( $stored[ $key ] ?? null );
			$has_save = is_array( $saved ) && ! isset( $saved['$$type'] );
			$complete = $has_save && 0 === count( self::blank_sides( $saved, $required ) );

			// Filling across units would change what the saved sides mean. A
			// missing or blank unit is Elementor's default, px, on either side.
			$unit = self::dimension_unit( $value );
			if ( $complete && self::dimension_unit( $saved ) !== $unit ) {
				unset( $settings[ $key ] );
				$warnings[] = sprintf(
					'%s had blank sides (%s) and a different unit from the %s (%s vs %s); it was not written%s. Supply %s (use 0 where intended).',
					$key,
					$sides,
					$label,
					$unit,
					self::dimension_unit( $saved ),
					$creating ? '' : ' and the saved value is unchanged',
					$supply
				);
				continue;
			}

			if ( $complete ) {
				foreach ( $blank as $side ) {
					$value[ $side ] = $saved[ $side ];
				}
				$value = self::with_resolved_unit( $value );
				if ( ! array_key_exists( 'isLinked', $value ) && array_key_exists( 'isLinked', $saved ) ) {
					$value['isLinked'] = $saved['isLinked'];
				}
				$filled = array();
				foreach ( $required as $side ) {
					$filled[] = (string) $value[ $side ];
				}
				if ( count( array_unique( $filled ) ) > 1 ) {
					$value['isLinked'] = false;
				}
				$settings[ $key ] = $value;
				$warnings[]       = sprintf( '%s had blank sides (%s): filled from the %s.', $key, $sides, $label );
				continue;
			}

			unset( $settings[ $key ] );
			if ( $creating ) {
				$warnings[] = sprintf( '%s had blank sides (%s) and was not written, because Elementor drops the whole CSS rule when a side is blank. Supply %s (use 0 where intended).', $key, $sides, $supply );
			} elseif ( $has_save ) {
				$warnings[] = sprintf( '%s had blank sides (%s) and was not written; the saved value is unchanged. Supply %s (use 0 where intended).', $key, $sides, $supply );
			} else {
				$warnings[] = sprintf( '%s had blank sides (%s) and no saved value to fill from; it was not written. Supply %s (use 0 where intended).', $key, $sides, $supply );
			}
		}

		return array(
			'settings' => $settings,
			'warnings' => $warnings,
		);
	}

	/**
	 * Whether a setting is a classic box dimension value the guard applies to:
	 * no `$$type` wrapper, no slider `size`, at least one box side, and either a
	 * `unit` key or a classic box key name. Gap values (column / row only) have
	 * no box side, so they are never guarded.
	 *
	 * @param string $key   Setting key.
	 * @param array  $value Setting value.
	 * @return bool
	 */
	private static function is_guarded_dimension( string $key, array $value ): bool {
		if ( isset( $value['$$type'] ) || array_key_exists( 'size', $value ) || ! self::has_any_side( $value, self::SIDES ) ) {
			return false;
		}
		return array_key_exists( 'unit', $value ) || 1 === preg_match( self::CLASSIC_BOX_KEY, $key );
	}

	/**
	 * Sides a control needs for Elementor to emit its CSS.
	 *
	 * @param string $key     Setting key.
	 * @param array  $context `elType` / `widgetType` of the element, if known.
	 * @return string[]
	 */
	private static function required_sides( string $key, array $context ): array {
		$allowed = self::control_allowed_dimensions( $key, $context );
		if ( null === $allowed && 'section' === ( $context['elType'] ?? '' ) && preg_match( self::SECTION_MARGIN_KEY, $key ) ) {
			$allowed = 'vertical';
		}
		if ( 'vertical' === $allowed ) {
			return array( 'top', 'bottom' );
		}
		if ( 'horizontal' === $allowed ) {
			return array( 'right', 'left' );
		}
		if ( is_array( $allowed ) ) {
			$sides = array_values( array_intersect( self::SIDES, $allowed ) );
			if ( count( $sides ) > 0 ) {
				return $sides;
			}
		}
		return self::SIDES;
	}

	/**
	 * The part of a context (often a whole element) the guard may read:
	 * `elType` and `widgetType`. Everything else, including any `preset` key
	 * in caller or imported data, is dropped.
	 *
	 * @param array $context Context or element.
	 * @return array{elType?: string, widgetType?: string}
	 */
	private static function control_context( array $context ): array {
		$out = array();
		foreach ( array( 'elType', 'widgetType' ) as $key ) {
			if ( isset( $context[ $key ] ) && is_string( $context[ $key ] ) ) {
				$out[ $key ] = $context[ $key ];
			}
		}
		return $out;
	}

	/** Breakpoint suffixes used when Elementor's breakpoint list is unavailable. */
	private const FALLBACK_BREAKPOINTS = array( 'tablet', 'mobile', 'laptop', 'widescreen', 'tablet_extra', 'mobile_extra' );

	/**
	 * The base key of a responsive setting (`margin_tablet` -> `margin`), or ''
	 * when the key has no known breakpoint suffix.
	 *
	 * @param string $key Setting key.
	 * @return string
	 */
	private static function responsive_base_key( string $key ): string {
		$breakpoints = array();
		try {
			$manager = \Elementor\Plugin::$instance->breakpoints ?? null;
			if ( is_object( $manager ) && method_exists( $manager, 'get_active_breakpoints' ) ) {
				$breakpoints = array_keys( (array) $manager->get_active_breakpoints() );
			}
		} catch ( \Throwable $e ) {
			$breakpoints = array();
		}
		if ( empty( $breakpoints ) ) {
			$breakpoints = self::FALLBACK_BREAKPOINTS;
		}
		// Longest first, so `_tablet_extra` wins over a shorter match.
		usort(
			$breakpoints,
			static function ( $a, $b ) {
				return strlen( (string) $b ) - strlen( (string) $a );
			}
		);
		foreach ( $breakpoints as $breakpoint ) {
			$suffix = '_' . $breakpoint;
			if ( strlen( $key ) > strlen( $suffix ) && substr( $key, -strlen( $suffix ) ) === $suffix ) {
				return substr( $key, 0, -strlen( $suffix ) );
			}
		}
		return '';
	}

	/**
	 * `allowed_dimensions` of the live Elementor control for this key, or null
	 * when Elementor, the element type or the control is not available.
	 *
	 * Outside CSS generation Elementor registers one control per responsive
	 * setting, so `margin_tablet` has no control of its own there; when the
	 * direct lookup finds nothing, the base control (`margin`) is used.
	 *
	 * @param string $key     Setting key.
	 * @param array  $context `elType` / `widgetType`.
	 * @return string|array|null
	 */
	private static function control_allowed_dimensions( string $key, array $context ) {
		$el_type = (string) ( $context['elType'] ?? '' );
		if ( '' === $el_type || ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance ) ) {
			return null;
		}
		try {
			$plugin = \Elementor\Plugin::$instance;
			$stack  = null;
			if ( 'widget' === $el_type ) {
				$type    = (string) ( $context['widgetType'] ?? '' );
				$manager = $plugin->widgets_manager ?? null;
				if ( '' !== $type && is_object( $manager ) && method_exists( $manager, 'get_widget_types' ) ) {
					$stack = $manager->get_widget_types( $type );
				}
			} else {
				$manager = $plugin->elements_manager ?? null;
				if ( is_object( $manager ) && method_exists( $manager, 'get_element_types' ) ) {
					$stack = $manager->get_element_types( $el_type );
				}
			}
			if ( ! is_object( $stack ) || ! method_exists( $stack, 'get_controls' ) ) {
				return null;
			}
			$control = $stack->get_controls( $key );
			if ( ! is_array( $control ) ) {
				$base    = self::responsive_base_key( $key );
				$control = '' === $base ? null : $stack->get_controls( $base );
			}
			if ( ! is_array( $control ) ) {
				return null;
			}
			return $control['allowed_dimensions'] ?? 'all';
		} catch ( \Throwable $e ) {
			return null;
		}
	}

	/**
	 * Unit of a classic dimension value; missing or blank means px.
	 *
	 * @param array $value Dimension value.
	 * @return string
	 */
	private static function dimension_unit( array $value ): string {
		$unit = isset( $value['unit'] ) && is_string( $value['unit'] ) ? trim( $value['unit'] ) : '';
		return '' === $unit ? 'px' : $unit;
	}

	/**
	 * The value with a missing or blank unit set to px; Elementor emits no
	 * valid CSS for a blank unit.
	 *
	 * @param array $value Dimension value.
	 * @return array
	 */
	private static function with_resolved_unit( array $value ): array {
		$value['unit'] = self::dimension_unit( $value );
		return $value;
	}

	/**
	 * Sides that are missing or blank: null, false, an array, or a string
	 * that is empty after trim(). 0 and "0" are values.
	 *
	 * @param array    $value Dimension value.
	 * @param string[] $sides Sides to check.
	 * @return string[]
	 */
	private static function blank_sides( array $value, array $sides ): array {
		$blank = array();
		foreach ( $sides as $side ) {
			$v = $value[ $side ] ?? null;
			if ( null === $v || false === $v || is_array( $v ) || ( is_string( $v ) && '' === trim( $v ) ) ) {
				$blank[] = $side;
			}
		}
		return $blank;
	}

	/**
	 * Whether any of these side keys is present.
	 *
	 * @param array    $value Value to check.
	 * @param string[] $sides Side keys.
	 * @return bool
	 */
	private static function has_any_side( array $value, array $sides ): bool {
		foreach ( $sides as $side ) {
			if ( array_key_exists( $side, $value ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Cast numeric dimension sides to the string form the editor expects.
	 * Elementor's editor always serialises `dimensions` / `gaps` sub-keys as
	 * strings ("40", not 40). The CSS is the same either way, but the editor's
	 * Layout panel hydrates strictly and shows 0 for a numeric side (#146).
	 *
	 * Only arrays that look like a classic dimension or gap value are touched:
	 * they carry `unit` plus at least one side key and no `$$type` wrapper
	 * (atomic props have their own typed shape). Slider `size` values stay
	 * numeric, matching the editor. Nested arrays (repeaters) are walked too.
	 *
	 * @since 3.17.1
	 *
	 * @param array $settings Element settings.
	 * @return array
	 */
	public static function normalize_dimension_settings( array $settings ): array {
		$all_sides = array_merge( self::SIDES, self::GAP_SIDES );
		foreach ( $settings as $key => $value ) {
			if ( ! is_array( $value ) || isset( $value['$$type'] ) ) {
				continue;
			}
			if ( array_key_exists( 'unit', $value ) && self::has_any_side( $value, $all_sides ) ) {
				foreach ( $all_sides as $side ) {
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

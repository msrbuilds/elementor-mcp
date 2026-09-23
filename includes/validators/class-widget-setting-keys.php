<?php
/**
 * Unknown-key check for classic Elementor widget settings.
 *
 * Elementor stores any settings key it is given, but only keys that match a
 * registered control ever reach the CSS or the render. A misspelled key
 * (`object_fit` for the image widget's `object-fit`) is saved and silently
 * does nothing (#152). This class names such keys so the widget tools can
 * return a warning. It never refuses a write.
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds settings keys that are not registered controls of a widget.
 *
 * @since 3.18.0
 */
class EMCP_Tools_Widget_Setting_Keys {

	/**
	 * Suffixes Elementor adds to a responsive control, one per breakpoint.
	 * Longest first so `_tablet_extra` is stripped before `_tablet` is tried.
	 *
	 * @var string[]
	 */
	const RESPONSIVE_SUFFIXES = array( '_mobile_extra', '_tablet_extra', '_widescreen', '_laptop', '_tablet', '_mobile' );

	/**
	 * Keys Elementor reserves on every element (dynamic tags and global
	 * colors/fonts), never registered as controls.
	 *
	 * @var string[]
	 */
	const RESERVED_KEYS = array( '__dynamic__', '__globals__', '__fa4_migrated' );

	/**
	 * Keys the update tools route to the element root, not into settings.
	 *
	 * @var string[]
	 */
	const ROOT_KEYS = array( 'styles', 'editor_settings' );

	/**
	 * Prefixes that commonly separate a guessed key from the real control:
	 * Pro post skins (`classic_columns`), the query group (`query_orderby`)
	 * and style-section names (`style_hotspot_color`). A leading underscore
	 * (`_margin`) needs no entry: the hyphen/underscore rule already finds it.
	 *
	 * @var string[]
	 */
	const KNOWN_PREFIXES = array( 'classic_', 'cards_', 'full_content_', 'query_', 'posts_', 'style_' );

	/**
	 * Skin ids whose controls are prefixed with `{skin}_` (Pro posts and archive posts).
	 *
	 * @var string[]
	 */
	const SKINS = array( 'classic', 'cards', 'full_content' );

	/**
	 * Per-request cache of control names by widget type.
	 *
	 * @var array<string,array<string,bool>|null>
	 */
	private static $cache = array();

	/**
	 * Warnings for the settings keys that are not controls of a widget type.
	 *
	 * Returns an empty list when the widget cannot be inspected (Elementor
	 * absent, unknown type, or a v4 atomic widget, whose props are not
	 * controls).
	 *
	 * @since 3.18.0
	 *
	 * @param string $widget_type Elementor widget type.
	 * @param array  $settings    Settings the caller sent.
	 * @param array  $stored      Settings already stored on the element (update tools),
	 *                            read for the active `_skin`.
	 * @return string[]
	 */
	public static function warnings( string $widget_type, array $settings, array $stored = array() ): array {
		$settings = array_diff_key( $settings, array_flip( self::ROOT_KEYS ) );
		if ( '' === $widget_type || empty( $settings ) ) {
			return array();
		}
		$known = self::known_controls( $widget_type );
		if ( empty( $known ) ) {
			return array();
		}
		$skin = (string) ( $settings['_skin'] ?? $stored['_skin'] ?? '' );
		return self::messages( $widget_type, self::unknown_keys( array_keys( $settings ), $known, $skin ) );
	}

	/**
	 * Warnings for an element in a page tree: empty unless it is a widget.
	 *
	 * @since 3.18.0
	 *
	 * @param array $element  The stored element (elType, widgetType, settings).
	 * @param array $settings Settings the caller sent for it.
	 * @return string[]
	 */
	public static function for_element( array $element, array $settings ): array {
		if ( 'widget' !== ( $element['elType'] ?? '' ) ) {
			return array();
		}
		return self::warnings( (string) ( $element['widgetType'] ?? '' ), $settings, (array) ( $element['settings'] ?? array() ) );
	}

	/**
	 * One warning line per unknown key. Pure.
	 *
	 * @since 3.18.0
	 *
	 * @param string                    $widget_type Widget type, for the message.
	 * @param array<string,string|null> $unknown     Unknown key => suggestion or null.
	 * @return string[]
	 */
	public static function messages( string $widget_type, array $unknown ): array {
		$warnings = array();
		foreach ( $unknown as $key => $suggestion ) {
			$message = sprintf( '"%1$s" is not a registered control of the %2$s widget and is likely ignored.', $key, $widget_type );
			$message .= null !== $suggestion
				? sprintf( ' Did you mean "%s"?', $suggestion )
				: ' See get-widget-schema (full: true) for the control names.';
			$warnings[] = $message;
		}
		return $warnings;
	}

	/**
	 * The unknown keys among $keys, each mapped to a suggested real control
	 * or null. Pure.
	 *
	 * @since 3.18.0
	 *
	 * @param array               $keys  Settings keys.
	 * @param array<string,mixed> $known Control names as keys.
	 * @param string              $skin  Active skin id, if any (for skin-prefixed suggestions).
	 * @return array<string,string|null>
	 */
	public static function unknown_keys( array $keys, array $known, string $skin = '' ): array {
		$unknown = array();
		foreach ( $keys as $key ) {
			$key = (string) $key;
			if ( self::is_known( $key, $known ) ) {
				continue;
			}
			$unknown[ $key ] = self::suggest( $key, $known, $skin );
		}
		return $unknown;
	}

	/**
	 * Whether a key is a control, a reserved key, or a responsive variant of
	 * a control. Pure.
	 *
	 * Group controls (typography, border, background, box shadow) register
	 * each field as its own control, so `typography_font_size` is matched
	 * directly and `typography_font_size_tablet` through the suffix rule.
	 *
	 * @since 3.18.0
	 *
	 * @param string              $key   Settings key.
	 * @param array<string,mixed> $known Control names as keys.
	 * @return bool
	 */
	public static function is_known( string $key, array $known ): bool {
		if ( isset( $known[ $key ] ) || in_array( $key, self::RESERVED_KEYS, true ) ) {
			return true;
		}
		$base = self::strip_responsive_suffix( $key );
		return null !== $base && isset( $known[ $base ] );
	}

	/**
	 * The key without its breakpoint suffix, or null when it has none. Pure.
	 *
	 * @since 3.18.0
	 *
	 * @param string $key Settings key.
	 * @return string|null
	 */
	public static function strip_responsive_suffix( string $key ): ?string {
		foreach ( self::RESPONSIVE_SUFFIXES as $suffix ) {
			if ( strlen( $key ) > strlen( $suffix ) && substr( $key, -strlen( $suffix ) ) === $suffix ) {
				return substr( $key, 0, -strlen( $suffix ) );
			}
		}
		return null;
	}

	/**
	 * A real control the caller probably meant, or null. Pure.
	 *
	 * Tried in order: a name that differs only by `-` versus `_`; the same
	 * name with a known prefix added or removed; the same words in another
	 * order (`button_hover_background_color` for `button_background_hover_color`).
	 * A breakpoint suffix on the key is carried over to the suggestion.
	 *
	 * @since 3.18.0
	 *
	 * @param string              $key   Unknown settings key.
	 * @param array<string,mixed> $known Control names as keys.
	 * @param string              $skin  Active skin id (`classic`, `cards`, `full_content`);
	 *                                   its prefix is tried before the other skins'.
	 * @return string|null
	 */
	public static function suggest( string $key, array $known, string $skin = '' ): ?string {
		$suffix = '';
		$base   = self::strip_responsive_suffix( $key );
		if ( null !== $base ) {
			$suffix = substr( $key, strlen( $base ) );
			$key    = $base;
		}

		if ( in_array( $skin, self::SKINS, true ) && isset( $known[ $skin . '_' . $key ] ) ) {
			return $skin . '_' . $key . $suffix;
		}

		$flat = self::flatten( $key );
		foreach ( array_keys( $known ) as $control ) {
			if ( self::flatten( (string) $control ) === $flat ) {
				return $control . $suffix;
			}
		}

		foreach ( self::KNOWN_PREFIXES as $prefix ) {
			if ( isset( $known[ $prefix . $key ] ) ) {
				return $prefix . $key . $suffix;
			}
			if ( strlen( $key ) > strlen( $prefix ) && 0 === strpos( $key, $prefix ) && isset( $known[ substr( $key, strlen( $prefix ) ) ] ) ) {
				return substr( $key, strlen( $prefix ) ) . $suffix;
			}
		}

		$words = self::word_bag( $key );
		if ( count( $words ) > 1 ) {
			foreach ( array_keys( $known ) as $control ) {
				if ( self::word_bag( (string) $control ) === $words ) {
					return $control . $suffix;
				}
			}
		}

		return null;
	}

	/**
	 * Control names of a widget type, with Elementor's style controls
	 * included. Null when the widget cannot be inspected.
	 *
	 * Outside the editor and REST, Elementor keeps style-tab controls in a
	 * separate list that `get_controls()` only merges while style controls
	 * are switched on, so the flag is set for the call and restored after.
	 *
	 * @since 3.18.0
	 *
	 * @param string $widget_type Elementor widget type.
	 * @return array<string,bool>|null
	 */
	public static function known_controls( string $widget_type ): ?array {
		if ( array_key_exists( $widget_type, self::$cache ) ) {
			return self::$cache[ $widget_type ];
		}
		// Not cached: Elementor may simply not be ready yet.
		if ( ! class_exists( '\Elementor\Plugin' ) || empty( \Elementor\Plugin::$instance->widgets_manager ) ) {
			return null;
		}
		self::$cache[ $widget_type ] = null;

		$widget = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
		// Atomic widgets have props, not controls. Without Elementor Pro, the
		// Pro widget names resolve to promotion placeholders whose controls are
		// not the real widget's, so neither can be checked.
		if ( ! $widget || method_exists( $widget, 'get_props_schema' ) || 0 === strpos( get_class( $widget ), 'Elementor\\Modules\\Promotions\\' ) ) {
			return null;
		}

		$performance = '\Elementor\Core\Frontend\Performance';
		$can_toggle  = class_exists( $performance ) && method_exists( $performance, 'set_use_style_controls' );
		$previous    = $can_toggle && method_exists( $performance, 'is_use_style_controls' ) ? $performance::is_use_style_controls() : false;
		try {
			if ( $can_toggle ) {
				$performance::set_use_style_controls( true );
			}
			$controls = $widget->get_controls();
		} catch ( \Throwable $e ) {
			$controls = array();
		} finally {
			if ( $can_toggle ) {
				$performance::set_use_style_controls( $previous );
			}
		}

		if ( ! is_array( $controls ) || empty( $controls ) ) {
			return null;
		}
		self::$cache[ $widget_type ] = array_fill_keys( array_map( 'strval', array_keys( $controls ) ), true );
		return self::$cache[ $widget_type ];
	}

	/**
	 * Clears the per-request control cache (tests).
	 *
	 * @since 3.18.0
	 */
	public static function reset_cache(): void {
		self::$cache = array();
	}

	/**
	 * Lower-case key with every `-` and `_` removed.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	private static function flatten( string $key ): string {
		return strtolower( str_replace( array( '-', '_' ), '', $key ) );
	}

	/**
	 * Sorted list of the key's words (split on `-` and `_`).
	 *
	 * @param string $key Key.
	 * @return string[]
	 */
	private static function word_bag( string $key ): array {
		$words = array_values( array_filter( preg_split( '/[-_]+/', strtolower( $key ) ), 'strlen' ) );
		sort( $words );
		return $words;
	}
}

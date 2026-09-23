<?php
/**
 * Elementor dynamic tags backed by the Themer dynamic sources.
 *
 * Elementor's dynamic-tag engine ships in FREE Elementor: Tag, Data_Tag and
 * Manager all live in the free plugin, and free registers no tags of its own.
 * Registering here therefore gives free Elementor users a working dynamic
 * picker on every dynamic-capable control, which is otherwise a Pro feature.
 *
 * The concrete tag class extends an Elementor base class, so it is defined in a
 * separate file required only inside the registration callback, where Elementor
 * is guaranteed to have loaded.
 *
 * @package EMCP_Tools
 * @since   3.13.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.13.0
 */
class EMCP_Tools_Themer_Elementor_Tags {

	const GROUP = 'emcp-themer';

	/** Hook Elementor's registration point. No-op when Elementor is absent. */
	public static function init(): void {
		add_action( 'elementor/dynamic_tags/register', array( __CLASS__, 'register' ) );
	}

	/**
	 * Elementor tag name for a source key.
	 *
	 * @param string $key Source key.
	 * @return string
	 */
	public static function tag_name( string $key ): string {
		return 'emcp-' . $key;
	}

	/**
	 * Elementor control categories a value type may fill.
	 *
	 * An empty array means the source is not offered anywhere, which is how
	 * markup sources are kept out of a heading's picker.
	 *
	 * @param string $type Value type.
	 * @return string[]
	 */
	public static function categories_for( string $type ): array {
		switch ( $type ) {
			case 'text':
			case 'date':
				return array( 'text', 'number' );
			case 'url':
				return array( 'url' );
			case 'image':
				return array( 'image' );
		}
		return array();
	}

	/**
	 * Whether a value type is served by an Elementor data tag.
	 *
	 * Image and URL sources fill controls that read a VALUE (the media control's
	 * `{id, url}`, the URL control's `url` property), so they must be data
	 * tags. Text and date stay render tags.
	 *
	 * @since 3.18.0
	 * @param string $type Value type.
	 * @return bool
	 */
	public static function is_data_type( string $type ): bool {
		return in_array( $type, array( 'image', 'url' ), true );
	}

	/**
	 * The value an Elementor data tag hands its control, for a source key.
	 *
	 * Image: `array( 'id' => attachment id or '', 'url' => url )`, the shape
	 * Elementor's media control and its CSS generator read. An avatar has no
	 * attachment, so its id is '', as in Elementor Pro's own tag.
	 * URL: the raw URL string (Elementor escapes it where it prints it).
	 * Any other type: ''.
	 *
	 * Pure over EMCP_Tools_Themer_Dynamic::value(), so it is unit-testable
	 * without Elementor loaded.
	 *
	 * @since 3.18.0
	 * @param string $key  Source key.
	 * @param array  $args Source args (the tag's settings).
	 * @return array|string
	 */
	public static function data_value( string $key, array $args = array() ) {
		$v = EMCP_Tools_Themer_Dynamic::value( $key, $args );

		if ( 'image' === $v['type'] ) {
			$img = is_array( $v['value'] ) ? $v['value'] : array();
			$id  = isset( $img['id'] ) ? (int) $img['id'] : 0;
			return array(
				'id'  => $id > 0 ? $id : '',
				'url' => isset( $img['url'] ) && is_string( $img['url'] ) ? $img['url'] : '',
			);
		}
		if ( 'url' === $v['type'] ) {
			return is_string( $v['value'] ) ? esc_url_raw( $v['value'] ) : '';
		}
		return '';
	}

	/**
	 * Substitute a data tag's Fallback setting when its value is empty.
	 *
	 * The fallback arrives as whatever was saved: a MEDIA value `{id, url}` or
	 * URL value `{url, ...}` from the current controls, or a plain string from
	 * the text Fallback the former render tags offered. All three work.
	 *
	 * @since 3.18.0
	 * @param array|string $value    What data_value() returned.
	 * @param mixed        $fallback The saved fallback.
	 * @param string       $type     Source value type (image|url).
	 * @return array|string
	 */
	public static function apply_data_fallback( $value, $fallback, string $type ) {
		$fb_url = '';
		$fb_id  = 0;
		if ( is_array( $fallback ) ) {
			$fb_url = isset( $fallback['url'] ) && is_string( $fallback['url'] ) ? trim( $fallback['url'] ) : '';
			$fb_id  = isset( $fallback['id'] ) ? (int) $fallback['id'] : 0;
		} elseif ( is_string( $fallback ) ) {
			$fb_url = trim( $fallback );
		}

		if ( 'image' === $type ) {
			$empty = ! is_array( $value ) || '' === (string) ( $value['url'] ?? '' );
			if ( ! $empty || '' === $fb_url ) {
				return $value;
			}
			return array(
				'id'  => $fb_id > 0 ? $fb_id : '',
				'url' => esc_url_raw( $fb_url ),
			);
		}
		if ( 'url' === $type ) {
			if ( ( is_string( $value ) && '' !== $value ) || '' === $fb_url ) {
				return $value;
			}
			return esc_url_raw( $fb_url );
		}
		return $value;
	}

	/**
	 * Source keys that become tags.
	 *
	 * @return string[]
	 */
	public static function taggable_keys(): array {
		$out = array();
		foreach ( EMCP_Tools_Themer_Dynamic_Catalog::bindable() as $key => $def ) {
			if ( self::categories_for( $def['type'] ) ) {
				$out[] = (string) $key;
			}
		}
		return $out;
	}

	/**
	 * Register the group and one tag per bindable source.
	 *
	 * @param object $manager Elementor dynamic tags manager.
	 */
	public static function register( $manager ): void {
		if ( ! is_object( $manager ) || ! method_exists( $manager, 'register' ) ) {
			return;
		}
		if ( method_exists( $manager, 'register_group' ) ) {
			$manager->register_group( self::GROUP, array( 'title' => __( 'EMCP Themer', 'emcp-tools' ) ) );
		}
		require_once __DIR__ . '/class-themer-elementor-tag-classes.php';
		if ( ! class_exists( 'EMCP_Tools_Themer_Elementor_Tag' ) ) {
			return;
		}
		foreach ( self::taggable_keys() as $key ) {
			// Each source needs its OWN class: Elementor stores only the class
			// name and rebuilds the tag from it, so a shared class cannot tell
			// which source it was and the front end fatals on render.
			$class = EMCP_Tools_Themer_Elementor_Tag::class_for( $key );
			if ( '' === $class ) {
				// A catalogued source with no tag class. Skip rather than
				// register something that cannot be rebuilt.
				if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
					// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
					error_log( '[EMCP Tools] dynamic source "' . $key . '" has no Elementor tag class; not registered.' );
				}
				continue;
			}
			$manager->register( new $class() );
		}
	}
}

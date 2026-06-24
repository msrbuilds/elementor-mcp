<?php
/**
 * Widget catalog accessor — single source of truth for curated widget metadata.
 *
 * Merges three data partials (free, pro, woo) into one keyed map and serves
 * read queries (by tier, by search, single lookup). The catalog is plain data;
 * the MCP widget tools (list-widgets, get-widget-schema, add-free-widget,
 * add-pro-widget) serve it instead of carrying 62 fat schemas of their own.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read API over the widget catalog data partials.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Widget_Catalog {

	/**
	 * Merged catalog cache (keyed by widget_type).
	 *
	 * @var array<string,array>|null
	 */
	private static $catalog = null;

	/**
	 * Returns the full merged catalog, keyed by widget_type.
	 *
	 * @since 3.0.0
	 *
	 * @return array<string,array>
	 */
	public static function get(): array {
		if ( null === self::$catalog ) {
			$free = (array) require __DIR__ . '/catalog-free.php';
			$pro  = (array) require __DIR__ . '/catalog-pro.php';
			$woo  = (array) require __DIR__ . '/catalog-woo.php';
			self::$catalog = array_merge( $free, $pro, $woo );
		}
		return self::$catalog;
	}

	/**
	 * Returns a single widget's catalog entry, or null if not cataloged.
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Widget type.
	 * @return array|null
	 */
	public static function get_widget( string $type ): ?array {
		$catalog = self::get();
		return $catalog[ $type ] ?? null;
	}

	/**
	 * Returns all cataloged widget types.
	 *
	 * @since 3.0.0
	 *
	 * @return string[]
	 */
	public static function all_types(): array {
		return array_keys( self::get() );
	}

	/**
	 * Returns the catalog filtered to a single tier ('free' | 'pro' | 'woo').
	 *
	 * @since 3.0.0
	 *
	 * @param string $tier Tier slug.
	 * @return array<string,array>
	 */
	public static function by_tier( string $tier ): array {
		return array_filter(
			self::get(),
			static function ( $entry ) use ( $tier ) {
				return ( $entry['tier'] ?? 'free' ) === $tier;
			}
		);
	}

	/**
	 * Returns the tier of a widget ('free' default if uncataloged).
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Widget type.
	 * @return string
	 */
	public static function tier_of( string $type ): string {
		$entry = self::get_widget( $type );
		return $entry['tier'] ?? 'free';
	}

	/**
	 * Whether a widget is in the Pro or Woo tier (i.e. needs Elementor Pro).
	 *
	 * @since 3.0.0
	 *
	 * @param string $type Widget type.
	 * @return bool
	 */
	public static function is_pro( string $type ): bool {
		$tier = self::tier_of( $type );
		return 'pro' === $tier || 'woo' === $tier;
	}

	/**
	 * Intent search across type, title, use_case, and keywords (case-insensitive
	 * substring). Returns the matching subset of the catalog, keyed by type.
	 *
	 * @since 3.0.0
	 *
	 * @param string $query Search query.
	 * @return array<string,array>
	 */
	public static function search( string $query ): array {
		$query = strtolower( trim( $query ) );
		if ( '' === $query ) {
			return self::get();
		}
		return array_filter(
			self::get(),
			static function ( $entry, $type ) use ( $query ) {
				$haystack = strtolower(
					$type . ' '
					. ( $entry['title'] ?? '' ) . ' '
					. ( $entry['use_case'] ?? '' ) . ' '
					. implode( ' ', (array) ( $entry['keywords'] ?? array() ) )
				);
				return false !== strpos( $haystack, $query );
			},
			ARRAY_FILTER_USE_BOTH
		);
	}

	/**
	 * Clears the in-memory cache (test seam).
	 *
	 * @since 3.0.0
	 */
	public static function flush_cache(): void {
		self::$catalog = null;
	}
}

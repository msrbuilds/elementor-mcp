<?php
/**
 * Free Brand Kits — the 10 bundled, no-license-required brand kits.
 *
 * This is the free-tier counterpart to Elementor_MCP_Pro_Brand_Kits. Where the
 * Pro service fetches 50+ kits from emcp.msrbuilds.com behind a license, this
 * one reads a small curated set shipped inside the plugin
 * (`assets/brand-kits/free-brand-kits.json`) and is available to everyone — the
 * same model as the 5 bundled sample prompts.
 *
 * It only PROVIDES the kit data; applying still routes through the shared
 * Elementor_MCP_System_Kit_Writer (and backups through
 * Elementor_MCP_Kit_Backup_Store), exactly like the Pro path.
 *
 * Previews use the pre-rendered, font-outlined SVGs shipped alongside the JSON
 * in `assets/brand-kits/{slug}.svg`; their URLs are injected at read time since
 * the JSON can't know the plugin URL.
 *
 * @package Elementor_MCP
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bundled free brand kits service.
 *
 * @since 1.9.0
 */
class Elementor_MCP_Free_Brand_Kits {

	/**
	 * In-memory cache of the parsed bundle for the current request.
	 *
	 * @var array|null
	 */
	private static $bundle = null;

	/**
	 * Path to the bundled kits JSON, relative to the plugin dir.
	 *
	 * @var string
	 */
	const DATA_PATH = 'assets/brand-kits/free-brand-kits.json';

	/**
	 * Get the bundled free kits in the same shape as the Pro bundle:
	 *   [ 'categories' => [ [ 'slug', 'label', 'kits' => [ {kit}, ... ] ] ] ]
	 *
	 * Each kit's `thumbnail_url` (and `preview.thumbnail_url`) is filled in with
	 * the plugin URL of its local SVG preview when that file exists; otherwise
	 * the view falls back to the kit's color swatches.
	 *
	 * Unlike the Pro service this never returns a WP_Error — the data is bundled,
	 * so it's always available. Returns an empty bundle if the file is missing.
	 *
	 * @since 1.9.0
	 *
	 * @return array
	 */
	public static function get_bundle(): array {
		if ( is_array( self::$bundle ) ) {
			return self::$bundle;
		}

		$file = ELEMENTOR_MCP_DIR . self::DATA_PATH;
		if ( ! is_readable( $file ) ) {
			self::$bundle = array( 'categories' => array() );
			return self::$bundle;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Reading a local plugin file.
		$decoded = json_decode( (string) file_get_contents( $file ), true );
		if ( ! is_array( $decoded ) || empty( $decoded['categories'] ) || ! is_array( $decoded['categories'] ) ) {
			self::$bundle = array( 'categories' => array() );
			return self::$bundle;
		}

		$assets_url = ELEMENTOR_MCP_URL . 'assets/brand-kits/';
		$assets_dir = ELEMENTOR_MCP_DIR . 'assets/brand-kits/';

		foreach ( $decoded['categories'] as &$category ) {
			if ( empty( $category['kits'] ) || ! is_array( $category['kits'] ) ) {
				continue;
			}
			foreach ( $category['kits'] as &$kit ) {
				$slug = isset( $kit['slug'] ) ? (string) $kit['slug'] : '';
				if ( '' === $slug ) {
					continue;
				}
				if ( is_file( $assets_dir . $slug . '.svg' ) ) {
					$thumb               = $assets_url . $slug . '.svg';
					$kit['thumbnail_url'] = $thumb;
					if ( ! isset( $kit['preview'] ) || ! is_array( $kit['preview'] ) ) {
						$kit['preview'] = array();
					}
					$kit['preview']['thumbnail_url'] = $thumb;
				}
			}
			unset( $kit );
		}
		unset( $category );

		self::$bundle = $decoded;
		return self::$bundle;
	}

	/**
	 * Find a bundled kit by slug (optionally scoped to a category).
	 *
	 * @since 1.9.0
	 *
	 * @param string $kit_slug      The kit slug.
	 * @param string $category_slug Optional category slug to disambiguate.
	 * @return array|null
	 */
	public static function find_kit( string $kit_slug, string $category_slug = '' ): ?array {
		foreach ( self::get_bundle()['categories'] as $category ) {
			if ( '' !== $category_slug && ( $category['slug'] ?? '' ) !== $category_slug ) {
				continue;
			}
			foreach ( $category['kits'] ?? array() as $kit ) {
				if ( ( $kit['slug'] ?? '' ) === $kit_slug ) {
					return $kit;
				}
			}
		}
		return null;
	}

	/**
	 * Total number of bundled free kits, for the admin stats bar.
	 *
	 * @since 1.9.0
	 *
	 * @return int
	 */
	public static function count_kits(): int {
		$total = 0;
		foreach ( self::get_bundle()['categories'] as $category ) {
			if ( ! empty( $category['kits'] ) && is_array( $category['kits'] ) ) {
				$total += count( $category['kits'] );
			}
		}
		return $total;
	}
}

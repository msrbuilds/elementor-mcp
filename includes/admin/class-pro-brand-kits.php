<?php
/**
 * Fetches and caches the premium Brand Kits library from emcp.msrbuilds.com,
 * and orchestrates applying a kit to the active Elementor kit.
 *
 * Mirror of class-pro-templates.php for brand kits: same auth flow, same 24h
 * transient cache, different endpoint and bundle shape. `apply_kit()` is a thin
 * orchestrator that routes ALL writes through Elementor_MCP_System_Kit_Writer
 * (§ 4.2.1) — it never calls the abilities.
 *
 * See docs/BRAND_KITS_PLAN.md §§ 2.3, 3.2, 4.2.
 *
 * @package Elementor_MCP
 * @since   1.8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Premium brand kits service.
 *
 * @since 1.8.0
 */
class Elementor_MCP_Pro_Brand_Kits {

	/**
	 * Transient key for the cached bundle.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'elementor_mcp_pro_brand_kits_bundle';

	/**
	 * Transient TTL in seconds. 24 hours.
	 *
	 * @var int
	 */
	const CACHE_TTL = 86400;

	/**
	 * Default endpoint. Filterable via `elementor_mcp_pro_brand_kits_endpoint`.
	 *
	 * @var string
	 */
	const DEFAULT_ENDPOINT = 'https://emcp.msrbuilds.com/api/emcp/brand-kits.json';

	/**
	 * Whether the current site can access premium brand kits.
	 *
	 * @since 1.8.0
	 *
	 * @return bool
	 */
	public static function user_has_access(): bool {
		if ( ! function_exists( 'emcp_pro_fs' ) ) {
			return false;
		}
		return emcp_pro_fs()->can_use_premium_code();
	}

	/**
	 * Get the brand kits bundle. Returns the cached copy when available.
	 *
	 * Bundle shape:
	 *   [
	 *     'fetched_at' => int,
	 *     'categories' => [
	 *       [ 'slug' => 'corporate', 'label' => 'Corporate & Tech', 'kits' => [ {kit}, ... ] ],
	 *     ],
	 *   ]
	 *
	 * @since 1.8.0
	 *
	 * @param bool $force_refresh Bypass the local cache.
	 * @return array|WP_Error
	 */
	public static function get_bundle( bool $force_refresh = false ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required to access premium brand kits.', 'elementor-mcp' ) );
		}

		if ( ! $force_refresh ) {
			$cached = get_transient( self::CACHE_KEY );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}

		return self::fetch_remote_bundle();
	}

	/**
	 * Find a specific kit by category + slug, or by slug alone (first match).
	 *
	 * @since 1.8.0
	 *
	 * @param string $kit_slug      The kit slug.
	 * @param string $category_slug Optional category slug to disambiguate.
	 * @return array|null
	 */
	public static function find_kit( string $kit_slug, string $category_slug = '' ): ?array {
		$bundle = self::get_bundle();
		if ( is_wp_error( $bundle ) || ! is_array( $bundle ) ) {
			return null;
		}

		foreach ( $bundle['categories'] ?? array() as $category ) {
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
	 * Apply a kit (already resolved from the bundle) to the active Elementor
	 * kit. Thin orchestrator over the shared writer service.
	 *
	 * @since 1.8.0
	 *
	 * @param array $kit A kit entry from the bundle (see § 3.1 shape).
	 * @return array|WP_Error Summary of what was applied.
	 */
	public static function apply_kit( array $kit ) {
		if ( ! class_exists( 'Elementor_MCP_System_Kit_Writer' ) ) {
			return new WP_Error( 'no_writer', __( 'The kit writer service is unavailable.', 'elementor-mcp' ) );
		}

		// Delegate to the neutral writer orchestrator (capability-gated per
		// write). Applying is a free feature as of 1.9.0; the Pro value is the
		// larger remote library + the MCP brand-kit tools, gated elsewhere.
		return Elementor_MCP_System_Kit_Writer::apply_kit( $kit );
	}

	/**
	 * Hit the remote endpoint, validate the response, store it in cache.
	 *
	 * @since 1.8.0
	 *
	 * @return array|WP_Error
	 */
	private static function fetch_remote_bundle() {
		$license_key = self::get_license_key();
		$license_id  = self::get_license_id();
		if ( '' === $license_key || '' === $license_id ) {
			return new WP_Error( 'no_license_key', __( 'No active EMCP Tools Pro license was found on this site.', 'elementor-mcp' ) );
		}

		$endpoint = apply_filters( 'elementor_mcp_pro_brand_kits_endpoint', self::DEFAULT_ENDPOINT );

		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept'                => 'application/json',
					'Authorization'         => 'Bearer ' . $license_key,
					'X-EMCP-License-Id'     => $license_id,
					'X-EMCP-Site'           => home_url(),
					'X-EMCP-Plugin-Version' => ELEMENTOR_MCP_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 403 === $code ) {
			return new WP_Error(
				'forbidden',
				__( 'Premium Brand Kits are unavailable on this site. Make sure your EMCP Tools Pro license is active and this site is on its activated-sites list. Contact support if the issue persists.', 'elementor-mcp' )
			);
		}

		if ( 429 === $code ) {
			return new WP_Error(
				'rate_limited',
				__( 'Brand Kits endpoint is rate-limiting this site. Try again in a few minutes.', 'elementor-mcp' )
			);
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'remote_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Brand Kits endpoint returned HTTP %d. Please try again later or contact support.', 'elementor-mcp' ),
					$code
				)
			);
		}

		$body   = wp_remote_retrieve_body( $response );
		$bundle = json_decode( $body, true );

		if ( ! is_array( $bundle ) || ! isset( $bundle['categories'] ) || ! is_array( $bundle['categories'] ) ) {
			return new WP_Error( 'invalid_payload', __( 'Brand Kits endpoint returned an unexpected payload.', 'elementor-mcp' ) );
		}

		$bundle['fetched_at'] = time();
		set_transient( self::CACHE_KEY, $bundle, self::CACHE_TTL );

		return $bundle;
	}

	/**
	 * Total kit count across the cached bundle, for the admin stats bar.
	 *
	 * @since 1.8.0
	 *
	 * @return int
	 */
	public static function count_cached_kits(): int {
		$bundle = get_transient( self::CACHE_KEY );
		if ( ! is_array( $bundle ) || empty( $bundle['categories'] ) ) {
			return 0;
		}
		$total = 0;
		foreach ( $bundle['categories'] as $category ) {
			if ( ! empty( $category['kits'] ) && is_array( $category['kits'] ) ) {
				$total += count( $category['kits'] );
			}
		}
		return $total;
	}

	/**
	 * Returns the active license key from Freemius, or empty string.
	 *
	 * @since 1.8.0
	 *
	 * @return string
	 */
	private static function get_license_key(): string {
		if ( ! function_exists( 'emcp_pro_fs' ) ) {
			return '';
		}
		$license = emcp_pro_fs()->_get_license();
		if ( ! $license || empty( $license->secret_key ) ) {
			return '';
		}
		return (string) $license->secret_key;
	}

	/**
	 * Returns the active license's numeric Freemius ID, or empty string.
	 *
	 * @since 1.8.0
	 *
	 * @return string
	 */
	private static function get_license_id(): string {
		if ( ! function_exists( 'emcp_pro_fs' ) ) {
			return '';
		}
		$license = emcp_pro_fs()->_get_license();
		if ( ! $license || empty( $license->id ) ) {
			return '';
		}
		return (string) $license->id;
	}

	/**
	 * Clear the cached bundle.
	 *
	 * @since 1.8.0
	 */
	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}

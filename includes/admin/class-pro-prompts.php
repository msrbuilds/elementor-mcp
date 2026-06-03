<?php
/**
 * Fetches and caches the premium prompts library from emcp.msrbuilds.com.
 *
 * The prompt content lives on the server, not in the plugin zip. The plugin
 * sends the active Freemius license key via Authorization header to a
 * license-gated endpoint; the server validates with Freemius's API
 * (including the site-binding check) and returns the full bundle, which
 * the plugin caches for 24 hours.
 *
 * @package EMCP_Tools
 * @since   1.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Premium prompts service.
 *
 * @since 1.7.0
 */
class EMCP_Tools_Pro_Prompts {

	/**
	 * Transient key for the cached manifest + content bundle.
	 *
	 * @var string
	 */
	const CACHE_KEY = 'emcp_tools_pro_prompts_bundle';

	/**
	 * Transient TTL in seconds. 24 hours.
	 *
	 * @var int
	 */
	const CACHE_TTL = 86400;

	/**
	 * Default endpoint that serves the prompts bundle. Filterable via
	 * `emcp_tools_pro_prompts_endpoint` for staging / local testing.
	 *
	 * @var string
	 */
	const DEFAULT_ENDPOINT = 'https://emcp.msrbuilds.com/api/emcp/prompts.json';

	/**
	 * Whether the current site can access premium prompts.
	 *
	 * @since 1.7.0
	 *
	 * @return bool
	 */
	public static function user_has_access(): bool {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return false;
		}
		return emcp_tools_fs()->can_use_premium_code();
	}

	/**
	 * Get the prompts bundle. Returns the cached copy when available.
	 *
	 * Bundle structure:
	 * [
	 *   'fetched_at' => int (unix timestamp),
	 *   'categories' => [
	 *     [
	 *       'slug'    => 'food-dining',
	 *       'label'   => 'Food & Dining',
	 *       'prompts' => [
	 *         [
	 *           'slug'        => 'bakery',
	 *           'title'       => 'Bakery',
	 *           'description' => 'Warm, charming landing page for an artisan bakery.',
	 *           'content'     => '<full markdown body>',
	 *         ],
	 *         ...
	 *       ],
	 *     ],
	 *     ...
	 *   ],
	 * ]
	 *
	 * @since 1.7.0
	 *
	 * @param bool $force_refresh Bypass the local cache.
	 * @return array|WP_Error Bundle on success, WP_Error on failure.
	 */
	public static function get_bundle( bool $force_refresh = false ) {
		if ( ! self::user_has_access() ) {
			return new WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required to access premium prompts.', 'emcp-tools' ) );
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
	 * Hit the remote endpoint, validate the response, store it in the
	 * local cache.
	 *
	 * @since 1.7.0
	 *
	 * @return array|WP_Error
	 */
	private static function fetch_remote_bundle() {
		$license_key = self::get_license_key();
		$license_id  = self::get_license_id();
		if ( '' === $license_key || '' === $license_id ) {
			return new WP_Error( 'no_license_key', __( 'No active EMCP Tools Pro license was found on this site.', 'emcp-tools' ) );
		}

		$endpoint = apply_filters( 'emcp_tools_pro_prompts_endpoint', self::DEFAULT_ENDPOINT );

		// Auth + site/version metadata travel in headers, never the URL. License
		// keys are credentials — query strings get logged by every proxy in the
		// chain (server access log, Dokploy log, intermediate CDN, browser
		// history). Authorization headers don't.
		//
		// X-EMCP-License-Id carries the numeric Freemius license ID. The
		// server uses it to route to the correct Freemius API endpoint
		// (/v1/plugins/{plugin_id}/licenses/{license_id}.json) and passes
		// the Authorization-header license key as a verification query
		// parameter. Freemius's API does NOT accept the license key as the
		// path segment — it expects the integer ID there.
		$response = wp_remote_get(
			$endpoint,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept'                => 'application/json',
					'Authorization'         => 'Bearer ' . $license_key,
					'X-EMCP-License-Id'     => $license_id,
					'X-EMCP-Site'           => home_url(),
					'X-EMCP-Plugin-Version' => EMCP_TOOLS_VERSION,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		// The server returns a uniform 403 for every auth failure (bad license,
		// site not activated, expired, etc.) to avoid leaking which condition
		// triggered the rejection. Don't try to be clever inferring the cause
		// from the response — show a single message and let the support flow
		// handle diagnosis.
		if ( 403 === $code ) {
			return new WP_Error(
				'forbidden',
				__( 'Premium Prompts are unavailable on this site. Make sure your EMCP Tools Pro license is active and this site is on its activated-sites list. Contact support if the issue persists.', 'emcp-tools' )
			);
		}

		if ( 429 === $code ) {
			return new WP_Error(
				'rate_limited',
				__( 'Premium Prompts endpoint is rate-limiting this site. Try again in a few minutes.', 'emcp-tools' )
			);
		}

		if ( 200 !== $code ) {
			return new WP_Error(
				'remote_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Prompts endpoint returned HTTP %d. Please try again later or contact support.', 'emcp-tools' ),
					$code
				)
			);
		}

		$body   = wp_remote_retrieve_body( $response );
		$bundle = json_decode( $body, true );

		if ( ! is_array( $bundle ) || empty( $bundle['categories'] ) || ! is_array( $bundle['categories'] ) ) {
			return new WP_Error( 'invalid_payload', __( 'Prompts endpoint returned an unexpected payload.', 'emcp-tools' ) );
		}

		$bundle['fetched_at'] = time();
		set_transient( self::CACHE_KEY, $bundle, self::CACHE_TTL );

		return $bundle;
	}

	/**
	 * Returns the active license key from Freemius, or empty string.
	 *
	 * @since 1.7.0
	 *
	 * @return string
	 */
	private static function get_license_key(): string {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return '';
		}
		$license = emcp_tools_fs()->_get_license();
		if ( ! $license || empty( $license->secret_key ) ) {
			return '';
		}
		return (string) $license->secret_key;
	}

	/**
	 * Returns the active license's numeric Freemius ID, or empty string.
	 * The server uses this to route the Freemius API check to the
	 * correct license endpoint (id in path, key in query string).
	 *
	 * @since 1.7.0
	 *
	 * @return string
	 */
	private static function get_license_id(): string {
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return '';
		}
		$license = emcp_tools_fs()->_get_license();
		if ( ! $license || empty( $license->id ) ) {
			return '';
		}
		return (string) $license->id;
	}

	/**
	 * Clear the cached bundle. Useful from a "Sync now" button or after
	 * the user activates / changes a license.
	 *
	 * @since 1.7.0
	 */
	public static function flush_cache(): void {
		delete_transient( self::CACHE_KEY );
	}
}

<?php
/**
 * HTTP client for the Unsplash API.
 *
 * Wraps WordPress HTTP API calls to Unsplash's photo search. Unlike the old
 * Openverse client, Unsplash requires an Access Key (free — register an app at
 * https://unsplash.com/developers). The key is read from the
 * `EMCP_TOOLS_UNSPLASH_ACCESS_KEY` constant, else the `emcp_tools_unsplash_access_key`
 * option (set on EMCP Tools → Connection).
 *
 * Results are normalized to the same shape the stock-image abilities consumed
 * from Openverse, so the tools' output stays stable.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unsplash API client.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Unsplash_Client {

	const API_BASE = 'https://api.unsplash.com';
	const TIMEOUT  = 15;
	const OPTION   = 'emcp_tools_unsplash_access_key';

	/**
	 * The configured Unsplash Access Key (constant wins over the option).
	 *
	 * @since 3.1.0
	 * @return string
	 */
	public static function access_key(): string {
		if ( defined( 'EMCP_TOOLS_UNSPLASH_ACCESS_KEY' ) && '' !== (string) EMCP_TOOLS_UNSPLASH_ACCESS_KEY ) {
			return (string) EMCP_TOOLS_UNSPLASH_ACCESS_KEY;
		}
		return EMCP_Tools_Secret::decrypt_if_needed( (string) get_option( self::OPTION, '' ) );
	}

	/**
	 * @since 3.1.0
	 * @return bool
	 */
	public static function has_key(): bool {
		return '' !== trim( self::access_key() );
	}

	/**
	 * Search Unsplash photos.
	 *
	 * @since 3.1.0
	 * @param array $params {
	 *     @type string $q            Search query (required).
	 *     @type int    $page         Page number (default 1).
	 *     @type int    $page_size    Results per page (default 5, max 30).
	 *     @type string $aspect_ratio wide|tall|square → Unsplash orientation.
	 *     @type string $color        Optional Unsplash color filter.
	 *     @type string $order_by     relevant|latest (default relevant).
	 * }
	 * @return array|\WP_Error Normalized `{ total, total_pages, results[] }` or error.
	 */
	public function search_images( array $params ) {
		if ( empty( $params['q'] ) ) {
			return new \WP_Error( 'missing_query', __( 'The search query parameter is required.', 'emcp-tools' ) );
		}
		if ( ! self::has_key() ) {
			return new \WP_Error(
				'no_api_key',
				__( 'No Unsplash Access Key is configured. Add one on EMCP Tools → Connection (get a free key at https://unsplash.com/developers).', 'emcp-tools' )
			);
		}

		$query_args = array(
			'query'    => sanitize_text_field( $params['q'] ),
			'per_page' => min( max( absint( $params['page_size'] ?? 5 ), 1 ), 30 ),
			'page'     => max( absint( $params['page'] ?? 1 ), 1 ),
		);

		$orientation = self::orientation( isset( $params['aspect_ratio'] ) ? (string) $params['aspect_ratio'] : '' );
		if ( '' !== $orientation ) {
			$query_args['orientation'] = $orientation;
		}
		if ( ! empty( $params['color'] ) ) {
			$query_args['color'] = sanitize_key( $params['color'] );
		}
		$order = isset( $params['order_by'] ) ? sanitize_key( $params['order_by'] ) : '';
		if ( 'latest' === $order ) {
			$query_args['order_by'] = 'latest';
		}

		$url      = add_query_arg( $query_args, self::API_BASE . '/search/photos' );
		$response = $this->request( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$results = array();
		if ( ! empty( $response['results'] ) && is_array( $response['results'] ) ) {
			foreach ( $response['results'] as $photo ) {
				$results[] = self::normalize_photo( $photo );
			}
		}

		return array(
			'total'       => intval( $response['total'] ?? count( $results ) ),
			'total_pages' => intval( $response['total_pages'] ?? 1 ),
			'results'     => $results,
		);
	}

	/**
	 * Fire Unsplash's download-tracking endpoint for a chosen photo. Required by the
	 * Unsplash API guidelines whenever a photo is downloaded/used. Best-effort — the
	 * result is ignored; failures never block the sideload.
	 *
	 * @since 3.1.0
	 * @param string $download_location The photo's `links.download_location` URL.
	 */
	public function trigger_download( string $download_location ): void {
		$download_location = esc_url_raw( $download_location );
		if ( '' === $download_location || 0 !== strpos( $download_location, self::API_BASE ) ) {
			return; // Only ever call Unsplash's own endpoint.
		}
		wp_remote_get(
			$download_location,
			array(
				'timeout' => self::TIMEOUT,
				'headers' => array( 'Authorization' => 'Client-ID ' . self::access_key() ),
			)
		);
	}

	/**
	 * Map our aspect_ratio vocabulary to Unsplash's `orientation`.
	 *
	 * @since 3.1.0
	 * @param string $aspect_ratio wide|tall|square (anything else → '').
	 * @return string landscape|portrait|squarish|''
	 */
	private static function orientation( string $aspect_ratio ): string {
		switch ( sanitize_key( $aspect_ratio ) ) {
			case 'wide':
				return 'landscape';
			case 'tall':
				return 'portrait';
			case 'square':
				return 'squarish';
			default:
				return '';
		}
	}

	/**
	 * Normalize an Unsplash photo to the stock-image tools' stable field shape.
	 *
	 * @since 3.1.0
	 * @param array $p Raw Unsplash photo.
	 * @return array
	 */
	private static function normalize_photo( array $p ): array {
		$urls   = isset( $p['urls'] ) && is_array( $p['urls'] ) ? $p['urls'] : array();
		$links  = isset( $p['links'] ) && is_array( $p['links'] ) ? $p['links'] : array();
		$user   = isset( $p['user'] ) && is_array( $p['user'] ) ? $p['user'] : array();
		$u_link = isset( $user['links']['html'] ) ? (string) $user['links']['html'] : '';
		$name   = isset( $user['name'] ) ? (string) $user['name'] : '';
		$ref    = '?utm_source=emcp_tools&utm_medium=referral'; // Unsplash attribution guideline.

		return array(
			'id'                  => isset( $p['id'] ) ? (string) $p['id'] : '',
			'title'               => (string) ( $p['description'] ?? $p['alt_description'] ?? '' ),
			// `url` is what gets sideloaded — 'regular' (~1080w) is the web-appropriate default.
			'url'                 => (string) ( $urls['regular'] ?? $urls['full'] ?? $urls['raw'] ?? '' ),
			'thumbnail'           => (string) ( $urls['small'] ?? $urls['thumb'] ?? '' ),
			'width'               => intval( $p['width'] ?? 0 ),
			'height'              => intval( $p['height'] ?? 0 ),
			'creator'             => $name,
			'creator_url'         => '' !== $u_link ? $u_link . $ref : '',
			'license'             => 'Unsplash License',
			'license_url'         => 'https://unsplash.com/license',
			'attribution'         => '' !== $name ? sprintf( 'Photo by %s on Unsplash', $name ) : 'Photo on Unsplash',
			'source'              => 'unsplash',
			'foreign_landing_url' => isset( $links['html'] ) ? (string) $links['html'] : '',
			// Not surfaced in the tool output schema; used internally to fire the
			// Unsplash download trigger when the photo is sideloaded.
			'download_location'   => isset( $links['download_location'] ) ? (string) $links['download_location'] : '',
		);
	}

	/**
	 * GET the Unsplash API with the configured key; decode + map errors.
	 *
	 * @since 3.1.0
	 * @param string $url Full request URL.
	 * @return array|\WP_Error
	 */
	private function request( string $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => 'Elementor-MCP/' . EMCP_TOOLS_VERSION . ' (WordPress/' . get_bloginfo( 'version' ) . ')',
				'headers'    => array(
					'Accept-Version' => 'v1',
					'Authorization'  => 'Client-ID ' . self::access_key(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Unsplash API request failed: %s', 'emcp-tools' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( 401 === $code ) {
			return new \WP_Error( 'invalid_key', __( 'Unsplash rejected the Access Key. Check it on EMCP Tools → Connection.', 'emcp-tools' ) );
		}
		if ( 403 === $code ) {
			return new \WP_Error( 'rate_limited', __( 'Unsplash rate limit reached (demo apps allow 50 requests/hour). Try again later or request production access.', 'emcp-tools' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Unsplash API returned HTTP %d.', 'emcp-tools' ),
					$code
				)
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'json_parse_error', __( 'Failed to parse the Unsplash API response.', 'emcp-tools' ) );
		}
		return $data;
	}
}

<?php
/**
 * HTTP client for the Pexels API.
 *
 * One of the three stock-photo providers behind the stock-image tools (with
 * Unsplash + Pixabay). Requires a free API key from https://www.pexels.com/api/,
 * read from the `EMCP_TOOLS_PEXELS_API_KEY` constant else the
 * `emcp_tools_pexels_api_key` option (EMCP Tools → Connection).
 *
 * Results are normalized to the shared stock-image field shape.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pexels API client.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Pexels_Client {

	const API_BASE = 'https://api.pexels.com/v1';
	const TIMEOUT  = 15;
	const OPTION   = 'emcp_tools_pexels_api_key';

	/**
	 * @since 3.1.0
	 * @return string
	 */
	public static function access_key(): string {
		if ( defined( 'EMCP_TOOLS_PEXELS_API_KEY' ) && '' !== (string) EMCP_TOOLS_PEXELS_API_KEY ) {
			return (string) EMCP_TOOLS_PEXELS_API_KEY;
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
	 * Search Pexels photos.
	 *
	 * @since 3.1.0
	 * @param array $params See EMCP_Tools_Unsplash_Client::search_images().
	 * @return array|\WP_Error Normalized `{ total, total_pages, results[] }` or error.
	 */
	public function search_images( array $params ) {
		if ( empty( $params['q'] ) ) {
			return new \WP_Error( 'missing_query', __( 'The search query parameter is required.', 'emcp-tools' ) );
		}
		if ( ! self::has_key() ) {
			return new \WP_Error( 'no_api_key', __( 'No Pexels API key is configured. Add one on EMCP Tools → Connection.', 'emcp-tools' ) );
		}

		$per_page   = min( max( absint( $params['page_size'] ?? 5 ), 1 ), 80 );
		$query_args = array(
			'query'    => sanitize_text_field( $params['q'] ),
			'per_page' => $per_page,
			'page'     => max( absint( $params['page'] ?? 1 ), 1 ),
		);
		$orientation = self::orientation( isset( $params['aspect_ratio'] ) ? (string) $params['aspect_ratio'] : '' );
		if ( '' !== $orientation ) {
			$query_args['orientation'] = $orientation;
		}

		$url      = add_query_arg( $query_args, self::API_BASE . '/search' );
		$response = $this->request( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$results = array();
		if ( ! empty( $response['photos'] ) && is_array( $response['photos'] ) ) {
			foreach ( $response['photos'] as $photo ) {
				$results[] = self::normalize_photo( $photo );
			}
		}

		$total    = intval( $response['total_results'] ?? count( $results ) );
		$per_page = max( 1, intval( $response['per_page'] ?? $per_page ) );

		return array(
			'total'       => $total,
			'total_pages' => (int) ceil( $total / $per_page ),
			'results'     => $results,
		);
	}

	/**
	 * Pexels has no download-tracking endpoint. No-op (kept for a uniform client API).
	 *
	 * @since 3.1.0
	 * @param string $download_location Unused.
	 */
	public function trigger_download( string $download_location ): void {}

	/**
	 * @since 3.1.0
	 * @param string $aspect_ratio wide|tall|square.
	 * @return string landscape|portrait|square|''
	 */
	private static function orientation( string $aspect_ratio ): string {
		switch ( sanitize_key( $aspect_ratio ) ) {
			case 'wide':
				return 'landscape';
			case 'tall':
				return 'portrait';
			case 'square':
				return 'square';
			default:
				return '';
		}
	}

	/**
	 * @since 3.1.0
	 * @param array $p Raw Pexels photo.
	 * @return array
	 */
	private static function normalize_photo( array $p ): array {
		$src  = isset( $p['src'] ) && is_array( $p['src'] ) ? $p['src'] : array();
		$name = isset( $p['photographer'] ) ? (string) $p['photographer'] : '';

		return array(
			'id'                  => isset( $p['id'] ) ? (string) $p['id'] : '',
			'title'               => isset( $p['alt'] ) ? (string) $p['alt'] : '',
			'url'                 => (string) ( $src['large2x'] ?? $src['large'] ?? $src['original'] ?? '' ),
			'thumbnail'           => (string) ( $src['medium'] ?? $src['small'] ?? $src['tiny'] ?? '' ),
			'width'               => intval( $p['width'] ?? 0 ),
			'height'              => intval( $p['height'] ?? 0 ),
			'creator'             => $name,
			'creator_url'         => isset( $p['photographer_url'] ) ? (string) $p['photographer_url'] : '',
			'license'             => 'Pexels License',
			'license_url'         => 'https://www.pexels.com/license/',
			'attribution'         => '' !== $name ? sprintf( 'Photo by %s on Pexels', $name ) : 'Photo on Pexels',
			'source'              => 'pexels',
			'foreign_landing_url' => isset( $p['url'] ) ? (string) $p['url'] : '',
			'download_location'   => '',
		);
	}

	/**
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
					'Accept'        => 'application/json',
					'Authorization' => self::access_key(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Pexels API request failed: %s', 'emcp-tools' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'invalid_key', __( 'Pexels rejected the API key. Check it on EMCP Tools → Connection.', 'emcp-tools' ) );
		}
		if ( 429 === $code ) {
			return new \WP_Error( 'rate_limited', __( 'Pexels rate limit reached (200 requests/hour on the free tier). Try again later.', 'emcp-tools' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Pexels API returned HTTP %d.', 'emcp-tools' ),
					$code
				)
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'json_parse_error', __( 'Failed to parse the Pexels API response.', 'emcp-tools' ) );
		}
		return $data;
	}
}

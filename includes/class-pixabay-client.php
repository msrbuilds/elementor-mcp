<?php
/**
 * HTTP client for the Pixabay API.
 *
 * One of the three stock-photo providers behind the stock-image tools (with
 * Unsplash + Pexels). Requires a free API key from https://pixabay.com/api/docs/,
 * read from the `EMCP_TOOLS_PIXABAY_API_KEY` constant else the
 * `emcp_tools_pixabay_api_key` option (EMCP Tools → Connection).
 *
 * Results are normalized to the shared stock-image field shape. Pixabay's terms
 * require downloading/caching images rather than hotlinking — the sideload step
 * satisfies that.
 *
 * @package EMCP_Tools
 * @since   3.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pixabay API client.
 *
 * @since 3.1.0
 */
class EMCP_Tools_Pixabay_Client {

	const API_BASE = 'https://pixabay.com/api/';
	const TIMEOUT  = 15;
	const OPTION   = 'emcp_tools_pixabay_api_key';

	/**
	 * @since 3.1.0
	 * @return string
	 */
	public static function access_key(): string {
		if ( defined( 'EMCP_TOOLS_PIXABAY_API_KEY' ) && '' !== (string) EMCP_TOOLS_PIXABAY_API_KEY ) {
			return (string) EMCP_TOOLS_PIXABAY_API_KEY;
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
	 * Search Pixabay photos.
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
			return new \WP_Error( 'no_api_key', __( 'No Pixabay API key is configured. Add one on EMCP Tools → Connection.', 'emcp-tools' ) );
		}

		// Pixabay requires per_page between 3 and 200.
		$per_page   = min( max( absint( $params['page_size'] ?? 5 ), 3 ), 200 );
		$query_args = array(
			'key'        => self::access_key(),
			'q'          => mb_substr( sanitize_text_field( $params['q'] ), 0, 100 ),
			'image_type' => 'photo',
			'safesearch' => 'true',
			'per_page'   => $per_page,
			'page'       => max( absint( $params['page'] ?? 1 ), 1 ),
		);
		$orientation = self::orientation( isset( $params['aspect_ratio'] ) ? (string) $params['aspect_ratio'] : '' );
		if ( '' !== $orientation ) {
			$query_args['orientation'] = $orientation;
		}

		$url      = add_query_arg( $query_args, self::API_BASE );
		$response = $this->request( $url );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$results = array();
		if ( ! empty( $response['hits'] ) && is_array( $response['hits'] ) ) {
			foreach ( $response['hits'] as $hit ) {
				$results[] = self::normalize_photo( $hit );
			}
		}

		$total = intval( $response['totalHits'] ?? count( $results ) );

		return array(
			'total'       => $total,
			'total_pages' => (int) ceil( $total / max( 1, $per_page ) ),
			'results'     => $results,
		);
	}

	/**
	 * Pixabay has no download-tracking endpoint. No-op (uniform client API).
	 *
	 * @since 3.1.0
	 * @param string $download_location Unused.
	 */
	public function trigger_download( string $download_location ): void {}

	/**
	 * @since 3.1.0
	 * @param string $aspect_ratio wide|tall|square.
	 * @return string horizontal|vertical|'' (Pixabay has no square orientation).
	 */
	private static function orientation( string $aspect_ratio ): string {
		switch ( sanitize_key( $aspect_ratio ) ) {
			case 'wide':
				return 'horizontal';
			case 'tall':
				return 'vertical';
			default:
				return '';
		}
	}

	/**
	 * @since 3.1.0
	 * @param array $h Raw Pixabay hit.
	 * @return array
	 */
	private static function normalize_photo( array $h ): array {
		$user    = isset( $h['user'] ) ? (string) $h['user'] : '';
		$user_id = isset( $h['user_id'] ) ? (int) $h['user_id'] : 0;
		$tags    = isset( $h['tags'] ) ? (string) $h['tags'] : '';

		return array(
			'id'                  => isset( $h['id'] ) ? (string) $h['id'] : '',
			'title'               => $tags,
			'url'                 => (string) ( $h['largeImageURL'] ?? $h['webformatURL'] ?? '' ),
			'thumbnail'           => (string) ( $h['webformatURL'] ?? $h['previewURL'] ?? '' ),
			'width'               => intval( $h['imageWidth'] ?? 0 ),
			'height'              => intval( $h['imageHeight'] ?? 0 ),
			'creator'             => $user,
			'creator_url'         => ( '' !== $user && $user_id ) ? sprintf( 'https://pixabay.com/users/%s-%d/', rawurlencode( $user ), $user_id ) : '',
			'license'             => 'Pixabay Content License',
			'license_url'         => 'https://pixabay.com/service/license-summary/',
			'attribution'         => '' !== $user ? sprintf( 'Image by %s on Pixabay', $user ) : 'Image on Pixabay',
			'source'              => 'pixabay',
			'foreign_landing_url' => isset( $h['pageURL'] ) ? (string) $h['pageURL'] : '',
			'download_location'   => '',
		);
	}

	/**
	 * @since 3.1.0
	 * @param string $url Full request URL (includes the key).
	 * @return array|\WP_Error
	 */
	private function request( string $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => 'Elementor-MCP/' . EMCP_TOOLS_VERSION . ' (WordPress/' . get_bloginfo( 'version' ) . ')',
				'headers'    => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Pixabay API request failed: %s', 'emcp-tools' ),
					$response->get_error_message()
				)
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( 400 === $code || 401 === $code ) {
			// Pixabay returns 400 for a bad/missing key or invalid params.
			return new \WP_Error( 'invalid_key', __( 'Pixabay rejected the request — check the API key on EMCP Tools → Connection.', 'emcp-tools' ) );
		}
		if ( 429 === $code ) {
			return new \WP_Error( 'rate_limited', __( 'Pixabay rate limit reached (100 requests/minute). Try again shortly.', 'emcp-tools' ) );
		}
		if ( $code < 200 || $code >= 300 ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Pixabay API returned HTTP %d.', 'emcp-tools' ),
					$code
				)
			);
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'json_parse_error', __( 'Failed to parse the Pixabay API response.', 'emcp-tools' ) );
		}
		return $data;
	}
}

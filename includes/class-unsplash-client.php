<?php
/**
 * HTTP client for the Unsplash API.
 *
 * Wraps WordPress HTTP API calls to the Unsplash image search service.
 * Unsplash provides high-resolution, free-to-use photos.
 *
 * @package Elementor_MCP
 * @since   2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Unsplash API client.
 *
 * @since 2.6.0
 */
class Elementor_MCP_Unsplash_Client {

	/**
	 * Unsplash API base URL.
	 *
	 * @var string
	 */
	const API_BASE = 'https://api.unsplash.com';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	const TIMEOUT = 15;

	/**
	 * The API key (Access Key / Client-ID).
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @since 2.6.0
	 *
	 * @param string $api_key The Unsplash Access Key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Searches for images on Unsplash.
	 *
	 * @since 2.6.0
	 *
	 * @param array $params {
	 *     Search parameters.
	 *
	 *     @type string $q            Search query (required).
	 *     @type int    $page         Page number (default 1).
	 *     @type int    $page_size    Results per page (default 5, max 20).
	 *     @type string $aspect_ratio Aspect ratio (wide, tall, square).
	 *     @type string $color        Color filter (black_and_white, black, white, yellow, etc.).
	 * }
	 * @return array|\WP_Error Parsed API response in unified format or WP_Error on failure.
	 */
	public function search_images( array $params ) {
		if ( empty( $params['q'] ) ) {
			return new \WP_Error(
				'missing_query',
				__( 'The search query parameter is required.', 'elementor-mcp' )
			);
		}

		if ( empty( $this->api_key ) ) {
			return new \WP_Error(
				'missing_api_key',
				__( 'Unsplash API key is not configured. Please add it in Settings → MCP Tools → Settings.', 'elementor-mcp' )
			);
		}

		$per_page = min( absint( $params['page_size'] ?? 5 ), 20 );

		$query_args = array(
			'query'    => sanitize_text_field( $params['q'] ),
			'per_page' => $per_page,
			'page'     => max( absint( $params['page'] ?? 1 ), 1 ),
		);

		// Map aspect_ratio to orientation.
		if ( ! empty( $params['aspect_ratio'] ) ) {
			$orientation_map = array(
				'wide'   => 'landscape',
				'tall'   => 'portrait',
				'square' => 'squarish',
			);
			$ar = strtolower( $params['aspect_ratio'] );
			if ( isset( $orientation_map[ $ar ] ) ) {
				$query_args['orientation'] = $orientation_map[ $ar ];
			}
		}

		if ( ! empty( $params['color'] ) ) {
			$query_args['color'] = sanitize_text_field( $params['color'] );
		}

		$url = add_query_arg( $query_args, self::API_BASE . '/search/photos' );

		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Map Unsplash response to unified format.
		$results = array();
		if ( ! empty( $response['results'] ) && is_array( $response['results'] ) ) {
			foreach ( $response['results'] as $photo ) {
				$results[] = array(
					'id'                  => $photo['id'] ?? '',
					'title'               => $photo['description'] ?? $photo['alt_description'] ?? 'Unsplash Photo',
					'url'                 => $photo['urls']['full'] ?? $photo['urls']['regular'] ?? '',
					'thumbnail'           => $photo['urls']['small'] ?? $photo['urls']['thumb'] ?? '',
					'width'               => intval( $photo['width'] ?? 0 ),
					'height'              => intval( $photo['height'] ?? 0 ),
					'creator'             => $photo['user']['name'] ?? '',
					'creator_url'         => $photo['user']['links']['html'] ?? '',
					'license'             => 'unsplash',
					'license_url'         => 'https://unsplash.com/license',
					'attribution'         => sprintf( 'Photo by %s on Unsplash', $photo['user']['name'] ?? 'Unknown' ),
					'source'              => 'unsplash',
					'foreign_landing_url' => $photo['links']['html'] ?? '',
				);
			}
		}

		return array(
			'result_count' => intval( $response['total'] ?? 0 ),
			'page'         => max( absint( $params['page'] ?? 1 ), 1 ),
			'page_count'   => intval( $response['total_pages'] ?? 0 ),
			'results'      => $results,
		);
	}

	/**
	 * Makes an HTTP GET request to the Unsplash API.
	 *
	 * @since 2.6.0
	 *
	 * @param string $url The full request URL.
	 * @return array|\WP_Error Decoded JSON response or WP_Error on failure.
	 */
	private function make_request( string $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => self::TIMEOUT,
				'user-agent' => 'Elementor-MCP/' . ELEMENTOR_MCP_VERSION . ' (WordPress/' . get_bloginfo( 'version' ) . ')',
				'headers'    => array(
					'Accept'        => 'application/json',
					'Authorization' => 'Client-ID ' . $this->api_key,
					'Accept-Version' => 'v1',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Unsplash API request failed: %s', 'elementor-mcp' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 403 === $status_code ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Unsplash API rate limit reached (50 requests/hour). Please wait before making more requests.', 'elementor-mcp' )
			);
		}

		if ( 401 === $status_code ) {
			return new \WP_Error(
				'auth_error',
				__( 'Unsplash API key is invalid or unauthorized. Please check your Access Key in Settings.', 'elementor-mcp' )
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Unsplash API returned HTTP %d.', 'elementor-mcp' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new \WP_Error(
				'json_parse_error',
				__( 'Failed to parse Unsplash API response.', 'elementor-mcp' )
			);
		}

		return $data;
	}
}

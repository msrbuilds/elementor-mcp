<?php
/**
 * HTTP client for the Pexels API.
 *
 * Wraps WordPress HTTP API calls to the Pexels image search service.
 * Pexels provides high-quality, free stock photos with a generous API.
 *
 * @package Elementor_MCP
 * @since   2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pexels API client.
 *
 * @since 2.6.0
 */
class Elementor_MCP_Pexels_Client {

	/**
	 * Pexels API base URL.
	 *
	 * @var string
	 */
	const API_BASE = 'https://api.pexels.com/v1';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	const TIMEOUT = 15;

	/**
	 * The API key.
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Constructor.
	 *
	 * @since 2.6.0
	 *
	 * @param string $api_key The Pexels API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Searches for images on Pexels.
	 *
	 * @since 2.6.0
	 *
	 * @param array $params {
	 *     Search parameters.
	 *
	 *     @type string $q            Search query (required).
	 *     @type int    $page         Page number (default 1).
	 *     @type int    $page_size    Results per page (default 5, max 20).
	 *     @type string $orientation  Orientation filter (landscape, portrait, square).
	 *     @type string $size         Size filter (large, medium, small).
	 *     @type string $color        Color filter (hex without #).
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
				__( 'Pexels API key is not configured. Please add it in Settings → MCP Tools → Settings.', 'elementor-mcp' )
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
				'square' => 'square',
			);
			$ar = strtolower( $params['aspect_ratio'] );
			if ( isset( $orientation_map[ $ar ] ) ) {
				$query_args['orientation'] = $orientation_map[ $ar ];
			}
		}

		if ( ! empty( $params['size'] ) ) {
			$query_args['size'] = sanitize_text_field( $params['size'] );
		}

		if ( ! empty( $params['color'] ) ) {
			$query_args['color'] = sanitize_text_field( $params['color'] );
		}

		$url = add_query_arg( $query_args, self::API_BASE . '/search' );

		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Map Pexels response to unified format.
		$results = array();
		if ( ! empty( $response['photos'] ) && is_array( $response['photos'] ) ) {
			foreach ( $response['photos'] as $photo ) {
				$results[] = array(
					'id'                  => strval( $photo['id'] ?? '' ),
					'title'               => $photo['alt'] ?? ( $photo['photographer'] . ' photo' ),
					'url'                 => $photo['src']['original'] ?? '',
					'thumbnail'           => $photo['src']['medium'] ?? $photo['src']['small'] ?? '',
					'width'               => intval( $photo['width'] ?? 0 ),
					'height'              => intval( $photo['height'] ?? 0 ),
					'creator'             => $photo['photographer'] ?? '',
					'creator_url'         => $photo['photographer_url'] ?? '',
					'license'             => 'pexels',
					'license_url'         => 'https://www.pexels.com/license/',
					'attribution'         => sprintf( 'Photo by %s on Pexels', $photo['photographer'] ?? 'Unknown' ),
					'source'              => 'pexels',
					'foreign_landing_url' => $photo['url'] ?? '',
				);
			}
		}

		$total_results = intval( $response['total_results'] ?? 0 );
		$page_count    = $per_page > 0 ? ceil( $total_results / $per_page ) : 0;

		return array(
			'result_count' => $total_results,
			'page'         => intval( $response['page'] ?? 1 ),
			'page_count'   => intval( $page_count ),
			'results'      => $results,
		);
	}

	/**
	 * Makes an HTTP GET request to the Pexels API.
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
					'Authorization' => $this->api_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Pexels API request failed: %s', 'elementor-mcp' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 429 === $status_code ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Pexels API rate limit reached (200 requests/hour). Please wait before making more requests.', 'elementor-mcp' )
			);
		}

		if ( 401 === $status_code || 403 === $status_code ) {
			return new \WP_Error(
				'auth_error',
				__( 'Pexels API key is invalid or unauthorized. Please check your API key in Settings.', 'elementor-mcp' )
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Pexels API returned HTTP %d.', 'elementor-mcp' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new \WP_Error(
				'json_parse_error',
				__( 'Failed to parse Pexels API response.', 'elementor-mcp' )
			);
		}

		return $data;
	}
}

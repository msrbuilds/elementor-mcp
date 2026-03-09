<?php
/**
 * HTTP client for the Pixabay API.
 *
 * Wraps WordPress HTTP API calls to the Pixabay image search service.
 * Pixabay provides free stock photos, illustrations, and vector graphics.
 *
 * @package Elementor_MCP
 * @since   2.6.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Pixabay API client.
 *
 * @since 2.6.0
 */
class Elementor_MCP_Pixabay_Client {

	/**
	 * Pixabay API base URL.
	 *
	 * @var string
	 */
	const API_BASE = 'https://pixabay.com/api/';

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
	 * @param string $api_key The Pixabay API key.
	 */
	public function __construct( string $api_key ) {
		$this->api_key = $api_key;
	}

	/**
	 * Searches for images on Pixabay.
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
	 *     @type string $category     Category (backgrounds, fashion, nature, science, etc.).
	 *     @type string $image_type   Image type (photo, illustration, vector).
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
				__( 'Pixabay API key is not configured. Please add it in Settings → MCP Tools → Settings.', 'elementor-mcp' )
			);
		}

		$per_page = min( absint( $params['page_size'] ?? 5 ), 20 );

		$query_args = array(
			'key'      => $this->api_key,
			'q'        => sanitize_text_field( $params['q'] ),
			'per_page' => $per_page,
			'page'     => max( absint( $params['page'] ?? 1 ), 1 ),
			'safesearch' => 'true',
		);

		// Map aspect_ratio to orientation.
		if ( ! empty( $params['aspect_ratio'] ) ) {
			$orientation_map = array(
				'wide'   => 'horizontal',
				'tall'   => 'vertical',
			);
			$ar = strtolower( $params['aspect_ratio'] );
			if ( isset( $orientation_map[ $ar ] ) ) {
				$query_args['orientation'] = $orientation_map[ $ar ];
			}
		}

		if ( ! empty( $params['category'] ) ) {
			$query_args['category'] = sanitize_text_field( $params['category'] );
		}

		$image_type = sanitize_text_field( $params['image_type'] ?? 'photo' );
		$query_args['image_type'] = $image_type;

		$url = add_query_arg( $query_args, self::API_BASE );

		$response = $this->make_request( $url );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Map Pixabay response to unified format.
		$results = array();
		if ( ! empty( $response['hits'] ) && is_array( $response['hits'] ) ) {
			foreach ( $response['hits'] as $hit ) {
				$results[] = array(
					'id'                  => strval( $hit['id'] ?? '' ),
					'title'               => ! empty( $hit['tags'] ) ? ucfirst( $hit['tags'] ) : 'Pixabay Image',
					'url'                 => $hit['largeImageURL'] ?? $hit['webformatURL'] ?? '',
					'thumbnail'           => $hit['previewURL'] ?? $hit['webformatURL'] ?? '',
					'width'               => intval( $hit['imageWidth'] ?? 0 ),
					'height'              => intval( $hit['imageHeight'] ?? 0 ),
					'creator'             => $hit['user'] ?? '',
					'creator_url'         => ! empty( $hit['user_id'] ) ? 'https://pixabay.com/users/' . $hit['user_id'] . '/' : '',
					'license'             => 'pixabay',
					'license_url'         => 'https://pixabay.com/service/license-summary/',
					'attribution'         => sprintf( 'Image by %s on Pixabay', $hit['user'] ?? 'Unknown' ),
					'source'              => 'pixabay',
					'foreign_landing_url' => $hit['pageURL'] ?? '',
				);
			}
		}

		$total_results = intval( $response['totalHits'] ?? 0 );
		$page_count    = $per_page > 0 ? ceil( $total_results / $per_page ) : 0;

		return array(
			'result_count' => $total_results,
			'page'         => max( absint( $params['page'] ?? 1 ), 1 ),
			'page_count'   => intval( $page_count ),
			'results'      => $results,
		);
	}

	/**
	 * Makes an HTTP GET request to the Pixabay API.
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
					'Accept' => 'application/json',
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_request_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Pixabay API request failed: %s', 'elementor-mcp' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 429 === $status_code ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Pixabay API rate limit reached (100 requests/minute). Please wait before making more requests.', 'elementor-mcp' )
			);
		}

		if ( 401 === $status_code || 403 === $status_code ) {
			return new \WP_Error(
				'auth_error',
				__( 'Pixabay API key is invalid or unauthorized. Please check your API key in Settings.', 'elementor-mcp' )
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Pixabay API returned HTTP %d.', 'elementor-mcp' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new \WP_Error(
				'json_parse_error',
				__( 'Failed to parse Pixabay API response.', 'elementor-mcp' )
			);
		}

		return $data;
	}
}

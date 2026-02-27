<?php
/**
 * HTTP client for the Openverse API.
 *
 * Wraps WordPress HTTP API calls to the Openverse image search service.
 * Openverse is WordPress.org's open-source media search engine with
 * Creative Commons and public domain content.
 *
 * @package Elementor_MCP
 * @since   1.1.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Openverse API client.
 *
 * @since 1.1.0
 */
class Elementor_MCP_Openverse_Client {

	/**
	 * Openverse API base URL.
	 *
	 * @var string
	 */
	const API_BASE = 'https://api.openverse.org/v1';

	/**
	 * HTTP request timeout in seconds.
	 *
	 * @var int
	 */
	const TIMEOUT = 15;

	/**
	 * Searches for images on Openverse.
	 *
	 * @since 1.1.0
	 *
	 * @param array $params {
	 *     Search parameters.
	 *
	 *     @type string $q            Search query (required).
	 *     @type int    $page         Page number (default 1).
	 *     @type int    $page_size    Results per page (default 5, max 20).
	 *     @type string $license      License filter (by, by-sa, by-nc, cc0, pdm).
	 *     @type string $source       Source filter (flickr, wikimedia, etc.).
	 *     @type string $aspect_ratio Aspect ratio (tall, wide, square).
	 *     @type string $size         Size filter (small, medium, large).
	 *     @type string $category     Category (photograph, illustration, digitized_artwork).
	 * }
	 * @return array|\WP_Error Parsed API response or WP_Error on failure.
	 */
	public function search_images( array $params ) {
		if ( empty( $params['q'] ) ) {
			return new \WP_Error(
				'missing_query',
				__( 'The search query parameter is required.', 'elementor-mcp' )
			);
		}

		$query_args = array(
			'q'         => sanitize_text_field( $params['q'] ),
			'page_size' => min( absint( $params['page_size'] ?? 5 ), 20 ),
			'page'      => max( absint( $params['page'] ?? 1 ), 1 ),
		);

		// Optional filters.
		$optional_filters = array( 'license', 'source', 'aspect_ratio', 'size', 'category' );
		foreach ( $optional_filters as $filter ) {
			if ( ! empty( $params[ $filter ] ) ) {
				$query_args[ $filter ] = sanitize_text_field( $params[ $filter ] );
			}
		}

		// Exclude mature content.
		$query_args['mature'] = 'false';

		$url = add_query_arg( $query_args, self::API_BASE . '/images/' );

		return $this->make_request( $url );
	}

	/**
	 * Gets details for a specific image by ID.
	 *
	 * @since 1.1.0
	 *
	 * @param string $image_id The Openverse image UUID.
	 * @return array|\WP_Error Parsed image data or WP_Error on failure.
	 */
	public function get_image( string $image_id ) {
		$url = self::API_BASE . '/images/' . sanitize_text_field( $image_id ) . '/';

		return $this->make_request( $url );
	}

	/**
	 * Makes an HTTP GET request to the Openverse API.
	 *
	 * @since 1.1.0
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
					__( 'Openverse API request failed: %s', 'elementor-mcp' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 429 === $status_code ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Openverse API rate limit reached. Anonymous access allows 100 requests/day and 5 requests/hour. Please wait before making more requests.', 'elementor-mcp' )
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Openverse API returned HTTP %d.', 'elementor-mcp' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( null === $data ) {
			return new \WP_Error(
				'json_parse_error',
				__( 'Failed to parse Openverse API response.', 'elementor-mcp' )
			);
		}

		return $data;
	}
}

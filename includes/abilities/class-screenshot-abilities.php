<?php
/**
 * Screenshot MCP abilities.
 *
 * Provides tools for taking screenshots of URLs and WordPress pages
 * via an external Screenshot SaaS API.
 *
 * @package Elementor_MCP
 * @since   2.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers screenshot-related MCP tools.
 *
 * @since 2.5.0
 */
class Elementor_MCP_Screenshot_Abilities {

	/**
	 * Default Screenshot SaaS API URL (public instance).
	 */
	const DEFAULT_API_URL = 'https://ssdone.site';

	/**
	 * Default Screenshot SaaS API key (public demo key).
	 */
	const DEFAULT_API_KEY = 'ss_live_406a6cf3c6634d698fa83a7efee7ed5d';

	/**
	 * Register all screenshot abilities.
	 *
	 * @return string[] Registered ability names.
	 */
	public function register(): array {
		$names = array();

		// Use configured values or fall back to built-in defaults.
		$api_url = get_option( 'elementor_mcp_screenshot_api_url', self::DEFAULT_API_URL );
		$api_key = get_option( 'elementor_mcp_screenshot_api_key', self::DEFAULT_API_KEY );

		if ( empty( $api_url ) || empty( $api_key ) ) {
			return $names;
		}

		// take-screenshot — generic URL screenshot.
		$name = 'elementor-mcp/take-screenshot';
		wp_register_ability(
			$name,
			array(
				'label'       => __( 'Take Screenshot', 'elementor-mcp' ),
				'description' => __( 'Takes a screenshot of any URL using the Screenshot SaaS API. Returns the screenshot image URL. Use this to visually verify page sections during the build process.', 'elementor-mcp' ),
				'category'    => 'elementor-mcp',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'url'       => array(
							'type'        => 'string',
							'description' => __( 'The URL to screenshot (required).', 'elementor-mcp' ),
						),
						'width'     => array(
							'type'        => 'integer',
							'description' => __( 'Viewport width in px. Default: 1920.', 'elementor-mcp' ),
						),
						'height'    => array(
							'type'        => 'integer',
							'description' => __( 'Viewport height in px. Default: 1080.', 'elementor-mcp' ),
						),
						'full_page' => array(
							'type'        => 'boolean',
							'description' => __( 'Capture full page height. Default: true.', 'elementor-mcp' ),
						),
						'format'    => array(
							'type'        => 'string',
							'enum'        => array( 'png', 'jpeg', 'webp' ),
							'description' => __( 'Image format. Default: png.', 'elementor-mcp' ),
						),
						'delay'     => array(
							'type'        => 'integer',
							'description' => __( 'Wait time in ms before capture. Default: 1000.', 'elementor-mcp' ),
						),
					),
					'required' => array( 'url' ),
				),
				'execute_callback'    => array( $this, 'handle_take_screenshot' ),
				'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
			)
		);
		$names[] = $name;

		// get-page-screenshot — screenshot a WordPress page by post_id.
		$name = 'elementor-mcp/get-page-screenshot';
		wp_register_ability(
			$name,
			array(
				'label'       => __( 'Get Page Screenshot', 'elementor-mcp' ),
				'description' => __( 'Takes a full-page screenshot of a WordPress page by post ID. Automatically resolves the permalink. Use this after building each section to visually verify the result.', 'elementor-mcp' ),
				'category'    => 'elementor-mcp',
				'input_schema' => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array(
							'type'        => 'integer',
							'description' => __( 'The WordPress post/page ID to screenshot (required).', 'elementor-mcp' ),
						),
						'width'     => array(
							'type'        => 'integer',
							'description' => __( 'Viewport width in px. Default: 1920.', 'elementor-mcp' ),
						),
						'full_page' => array(
							'type'        => 'boolean',
							'description' => __( 'Capture full page height. Default: true.', 'elementor-mcp' ),
						),
					),
					'required' => array( 'post_id' ),
				),
				'execute_callback'    => array( $this, 'handle_get_page_screenshot' ),
				'permission_callback' => function () { return current_user_can( 'edit_posts' ); },
			)
		);
		$names[] = $name;

		return $names;
	}

	/**
	 * Handle the take-screenshot tool call.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function handle_take_screenshot( array $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'forbidden', __( 'Permission denied.', 'elementor-mcp' ) );
		}

		$url = isset( $args['url'] ) ? esc_url_raw( $args['url'] ) : '';
		if ( empty( $url ) ) {
			return new \WP_Error( 'missing_url', __( 'The url parameter is required.', 'elementor-mcp' ) );
		}

		$width     = isset( $args['width'] ) ? absint( $args['width'] ) : 1920;
		$height    = isset( $args['height'] ) ? absint( $args['height'] ) : 1080;
		$full_page = isset( $args['full_page'] ) ? (bool) $args['full_page'] : true;
		$format    = isset( $args['format'] ) && in_array( $args['format'], array( 'png', 'jpeg', 'webp' ), true ) ? $args['format'] : 'png';
		$delay     = isset( $args['delay'] ) ? absint( $args['delay'] ) : 1000;

		return $this->call_screenshot_api( $url, $width, $height, $full_page, $format, $delay );
	}

	/**
	 * Handle the get-page-screenshot tool call.
	 *
	 * @param array $args Tool arguments.
	 * @return array|WP_Error
	 */
	public function handle_get_page_screenshot( array $args ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'forbidden', __( 'Permission denied.', 'elementor-mcp' ) );
		}

		$post_id = isset( $args['post_id'] ) ? absint( $args['post_id'] ) : 0;
		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'elementor-mcp' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'not_found', __( 'Post not found.', 'elementor-mcp' ) );
		}

		// Get the permalink. For drafts, use preview link.
		if ( 'publish' === $post->post_status ) {
			$url = get_permalink( $post_id );
		} else {
			$url = get_preview_post_link( $post_id );
		}

		if ( empty( $url ) ) {
			return new \WP_Error( 'no_url', __( 'Could not determine the page URL.', 'elementor-mcp' ) );
		}

		$width     = isset( $args['width'] ) ? absint( $args['width'] ) : 1920;
		$full_page = isset( $args['full_page'] ) ? (bool) $args['full_page'] : true;

		$result = $this->call_screenshot_api( $url, $width, 1080, $full_page, 'png', 1500 );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$result['post_id']    = $post_id;
		$result['post_title'] = $post->post_title;
		$result['page_url']   = $url;

		return $result;
	}

	/**
	 * Call the Screenshot SaaS API.
	 *
	 * @param string $url       The URL to capture.
	 * @param int    $width     Viewport width.
	 * @param int    $height    Viewport height.
	 * @param bool   $full_page Whether to capture full page.
	 * @param string $format    Image format.
	 * @param int    $delay     Delay in ms.
	 * @return array|WP_Error
	 */
	private function call_screenshot_api( string $url, int $width, int $height, bool $full_page, string $format, int $delay ) {
		$api_url = rtrim( get_option( 'elementor_mcp_screenshot_api_url', self::DEFAULT_API_URL ), '/' );
		$api_key = get_option( 'elementor_mcp_screenshot_api_key', self::DEFAULT_API_KEY );

		if ( empty( $api_url ) || empty( $api_key ) ) {
			return new \WP_Error(
				'not_configured',
				__( 'Screenshot API is not configured. Go to Settings > MCP Tools for Elementor > Settings tab to set up your API key and server URL.', 'elementor-mcp' )
			);
		}

		$query_args = array(
			'url'       => $url,
			'width'     => $width,
			'height'    => $height,
			'full_page' => $full_page ? 'true' : 'false',
			'format'    => $format,
			'delay'     => $delay,
			'output'    => 'json',
		);

		$request_url = $api_url . '/api/screenshot?' . http_build_query( $query_args );

		$response = wp_remote_get(
			$request_url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Screenshot API request failed: %s', 'elementor-mcp' ),
					$response->get_error_message()
				)
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( 200 !== $code ) {
			$error_data = json_decode( $body, true );
			$message    = isset( $error_data['message'] ) ? $error_data['message'] : "HTTP $code";
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Screenshot API returned error: %s', 'elementor-mcp' ),
					$message
				)
			);
		}

		$data = json_decode( $body, true );

		if ( empty( $data['success'] ) ) {
			return new \WP_Error( 'api_error', __( 'Screenshot API returned unsuccessful response.', 'elementor-mcp' ) );
		}

		$screenshot_data = $data['data'];

		return array(
			'success'        => true,
			'screenshot_url' => isset( $screenshot_data['url'] ) ? $screenshot_data['url'] : null,
			'image_base64'   => isset( $screenshot_data['image'] ) ? $screenshot_data['image'] : null,
			'width'          => isset( $screenshot_data['width'] ) ? $screenshot_data['width'] : $width,
			'height'         => isset( $screenshot_data['height'] ) ? $screenshot_data['height'] : $height,
			'format'         => $format,
			'file_size'      => isset( $screenshot_data['file_size'] ) ? $screenshot_data['file_size'] : null,
			'took_ms'        => isset( $screenshot_data['took_ms'] ) ? $screenshot_data['took_ms'] : null,
			'captured_url'   => $url,
		);
	}
}

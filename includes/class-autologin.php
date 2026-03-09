<?php
/**
 * Auto-login endpoint for authenticated preview access.
 *
 * Provides a REST endpoint that accepts Basic Auth credentials (via header
 * or query parameter), validates them, sets a WordPress login cookie, and
 * redirects to the requested preview URL.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles the auto-login REST endpoint.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Autologin {

	/**
	 * Initializes the auto-login endpoint.
	 *
	 * @since 1.5.0
	 */
	public function init(): void {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Registers REST routes.
	 *
	 * @since 1.5.0
	 */
	public function register_routes(): void {
		register_rest_route(
			'mcp/v1',
			'/autologin',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'handle_autologin' ),
				'permission_callback' => '__return_true', // Auth handled in callback.
				'args'                => array(
					'redirect_to' => array(
						'type'              => 'string',
						'sanitize_callback' => 'esc_url_raw',
						'default'           => home_url( '/' ),
					),
					'token' => array(
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
						'default'           => '',
						'description'       => 'Base64-encoded Basic Auth credentials (username:password). Used when Authorization header is not available (e.g., iframe navigation).',
					),
				),
			)
		);
	}

	/**
	 * Handles the auto-login request.
	 *
	 * Accepts Basic Auth via Authorization header or via ?token= query param.
	 * Sets a WordPress login cookie and redirects to the target URL.
	 *
	 * @since 1.5.0
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function handle_autologin( \WP_REST_Request $request ): \WP_REST_Response|\WP_Error {
		$credentials = '';

		// Try Authorization header first.
		$auth_header = $request->get_header( 'Authorization' );
		if ( ! empty( $auth_header ) && stripos( $auth_header, 'Basic ' ) === 0 ) {
			$credentials = base64_decode( substr( $auth_header, 6 ) );
		}

		// Fallback: try ?token= query parameter (base64 encoded username:password).
		if ( empty( $credentials ) ) {
			$token = $request->get_param( 'token' );
			if ( ! empty( $token ) ) {
				$credentials = base64_decode( $token );
			}
		}

		if ( empty( $credentials ) || strpos( $credentials, ':' ) === false ) {
			return new \WP_Error(
				'missing_auth',
				__( 'Authentication credentials required via Authorization header or token parameter.', 'elementor-mcp' ),
				array( 'status' => 401 )
			);
		}

		list( $username, $password ) = explode( ':', $credentials, 2 );

		// Authenticate the user.
		$user = wp_authenticate( $username, $password );

		if ( is_wp_error( $user ) ) {
			return new \WP_Error(
				'auth_failed',
				__( 'Authentication failed.', 'elementor-mcp' ),
				array( 'status' => 401 )
			);
		}

		// Set the auth cookie so subsequent requests in this browser are authenticated.
		wp_set_auth_cookie( $user->ID, true );
		wp_set_current_user( $user->ID );

		// Redirect to target URL.
		$redirect_to = $request->get_param( 'redirect_to' );

		// Security: only allow redirects to the same domain.
		$site_host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$redirect_host = wp_parse_url( $redirect_to, PHP_URL_HOST );

		if ( $redirect_host && $redirect_host !== $site_host ) {
			$redirect_to = home_url( '/' );
		}

		// Return redirect response.
		$response = new \WP_REST_Response( null, 302 );
		$response->header( 'Location', $redirect_to );
		$response->header( 'Cache-Control', 'no-cache, no-store, must-revalidate' );

		return $response;
	}
}

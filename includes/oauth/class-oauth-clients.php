<?php
/**
 * Dynamic Client Registration (RFC 7591) — MCP clients self-register and get a
 * public `client_id` (no secret; they use PKCE). Open registration, as the spec
 * expects, but the redirect URIs are validated (https, or http loopback only).
 *
 * @package EMCP_Tools
 * @since   3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The `/register` endpoint + registration validation.
 *
 * @since 3.4.1
 */
class EMCP_Tools_OAuth_Clients {

	/**
	 * Register the REST route.
	 */
	public static function register_routes(): void {
		register_rest_route(
			EMCP_Tools_OAuth_Server::REST_NAMESPACE,
			'/register',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_register' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Handle a Dynamic Client Registration request.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_register( $request ) {
		$body = $request->get_json_params();
		if ( ! is_array( $body ) ) {
			$body = $request->get_params();
		}

		$result = self::validate_registration( is_array( $body ) ? $body : array() );
		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'error'             => 'invalid_client_metadata',
					'error_description' => $result->get_error_message(),
				),
				400
			);
		}

		$client = EMCP_Tools_OAuth_Store::create_client(
			$result['client_name'],
			$result['redirect_uris'],
			get_current_user_id()
		);

		return new WP_REST_Response(
			array(
				'client_id'                  => $client['client_id'],
				'client_name'                => $client['client_name'],
				'redirect_uris'              => $client['redirect_uris'],
				'token_endpoint_auth_method' => 'none',
				'grant_types'                => array( 'authorization_code', 'refresh_token' ),
				'response_types'             => array( 'code' ),
				'client_id_issued_at'        => time(),
			),
			201
		);
	}

	/**
	 * Validate + normalize a registration body.
	 *
	 * @param array $body Request body.
	 * @return array{client_name:string,redirect_uris:string[]}|WP_Error
	 */
	public static function validate_registration( array $body ) {
		$uris = $body['redirect_uris'] ?? null;
		if ( ! is_array( $uris ) || array() === $uris ) {
			return new WP_Error( 'invalid_redirect_uri', 'redirect_uris is required and must be a non-empty array.' );
		}

		$clean = array();
		foreach ( $uris as $uri ) {
			if ( ! is_string( $uri ) || ! self::is_allowed_redirect_uri( $uri ) ) {
				return new WP_Error( 'invalid_redirect_uri', 'Each redirect_uri must be an absolute https URL (or an http loopback address).' );
			}
			$clean[] = $uri;
		}

		$name = ( isset( $body['client_name'] ) && is_string( $body['client_name'] ) && '' !== trim( $body['client_name'] ) )
			? trim( $body['client_name'] )
			: 'MCP Client';

		return array(
			'client_name'   => $name,
			'redirect_uris' => array_values( array_unique( $clean ) ),
		);
	}

	/**
	 * Whether a redirect URI is allowed: absolute https, or http on a loopback
	 * host. No fragment component (RFC 6749 §3.1.2).
	 *
	 * @param string $uri Candidate URI.
	 * @return bool
	 */
	public static function is_allowed_redirect_uri( string $uri ): bool {
		$p = parse_url( $uri );
		if ( ! is_array( $p ) || empty( $p['scheme'] ) || empty( $p['host'] ) || isset( $p['fragment'] ) ) {
			return false;
		}
		$scheme = strtolower( $p['scheme'] );
		$host   = strtolower( trim( $p['host'], '[]' ) );
		if ( 'https' === $scheme ) {
			return true;
		}
		return 'http' === $scheme && in_array( $host, array( '127.0.0.1', '::1', 'localhost' ), true );
	}
}

<?php
/**
 * The `/token` and `/revoke` endpoints — exchange an authorization code (with
 * PKCE) for an access + refresh token, rotate refresh tokens, and revoke.
 *
 * Public clients only: there is no client secret; the authorization code is
 * bound to a PKCE challenge at `/authorize` and verified here.
 *
 * @package EMCP_Tools
 * @since   3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Token + revocation endpoints.
 *
 * @since 3.4.1
 */
class EMCP_Tools_OAuth_Token {

	const ACCESS_TTL  = 3600;    // 1 hour
	const REFRESH_TTL = 2592000; // 30 days

	/**
	 * Register the REST routes.
	 */
	public static function register_routes(): void {
		register_rest_route(
			EMCP_Tools_OAuth_Server::REST_NAMESPACE,
			'/token',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_token' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			EMCP_Tools_OAuth_Server::REST_NAMESPACE,
			'/revoke',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'handle_revoke' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Token endpoint dispatcher.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_token( $request ) {
		$p          = $request->get_body_params();
		$grant_type = (string) ( $p['grant_type'] ?? '' );

		if ( 'authorization_code' === $grant_type ) {
			return self::exchange_code( $p );
		}
		if ( 'refresh_token' === $grant_type ) {
			return self::refresh( $p );
		}
		return self::error( 'unsupported_grant_type', 'Unsupported grant_type.' );
	}

	/**
	 * Exchange an authorization code for tokens.
	 *
	 * @param array $p Request body params.
	 * @return WP_REST_Response
	 */
	private static function exchange_code( array $p ) {
		$code         = (string) ( $p['code'] ?? '' );
		$client_id    = (string) ( $p['client_id'] ?? '' );
		$redirect_uri = (string) ( $p['redirect_uri'] ?? '' );
		$verifier     = (string) ( $p['code_verifier'] ?? '' );

		$payload = ( '' === $code ) ? null : EMCP_Tools_OAuth_Store::consume_code( $code );
		$check   = self::validate_code_exchange( $payload, $client_id, $redirect_uri, $verifier );
		if ( is_wp_error( $check ) ) {
			return self::error( $check->get_error_code(), $check->get_error_message() );
		}

		$pair = self::issue_pair( $client_id, (int) $payload['user_id'], (string) $payload['scopes'] );
		return new WP_REST_Response(
			self::token_response( $pair['access'], $pair['refresh'], self::ACCESS_TTL, (string) $payload['scopes'] ),
			200
		);
	}

	/**
	 * Rotate a refresh token, issuing a fresh access + refresh pair and revoking
	 * the presented one (and its bound access token).
	 *
	 * @param array $p Request body params.
	 * @return WP_REST_Response
	 */
	private static function refresh( array $p ) {
		$refresh_token = (string) ( $p['refresh_token'] ?? '' );
		$client_id     = (string) ( $p['client_id'] ?? '' );

		$row = ( '' === $refresh_token ) ? null : EMCP_Tools_OAuth_Store::find_token( $refresh_token, 'refresh' );
		if ( null === $row || ! hash_equals( (string) $row['client_id'], $client_id ) ) {
			return self::error( 'invalid_grant', 'Refresh token is invalid or expired.' );
		}

		// Rotate: revoke the old refresh (+ its access), issue a new pair.
		EMCP_Tools_OAuth_Store::revoke_token( (int) $row['id'] );
		$pair = self::issue_pair( $client_id, (int) $row['user_id'], (string) $row['scopes'] );

		return new WP_REST_Response(
			self::token_response( $pair['access'], $pair['refresh'], self::ACCESS_TTL, (string) $row['scopes'] ),
			200
		);
	}

	/**
	 * Revoke a token (RFC 7009). Always returns 200, even for unknown tokens.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response
	 */
	public static function handle_revoke( $request ) {
		$token = (string) ( $request->get_body_params()['token'] ?? '' );
		if ( '' !== $token ) {
			foreach ( array( 'access', 'refresh' ) as $type ) {
				$row = EMCP_Tools_OAuth_Store::find_token( $token, $type );
				if ( null !== $row ) {
					EMCP_Tools_OAuth_Store::revoke_token( (int) $row['id'] );
					break;
				}
			}
		}
		return new WP_REST_Response( array( 'revoked' => true ), 200 );
	}

	// ---------------------------------------------------------------------
	// Pure helpers (unit-tested)
	// ---------------------------------------------------------------------

	/**
	 * Validate an authorization-code exchange against the stored code payload.
	 *
	 * @param array|null $payload      Stored code payload (null if unknown/used).
	 * @param string     $client_id    Presented client id.
	 * @param string     $redirect_uri Presented redirect URI.
	 * @param string     $verifier     PKCE code_verifier.
	 * @return true|WP_Error
	 */
	public static function validate_code_exchange( ?array $payload, string $client_id, string $redirect_uri, string $verifier ) {
		if ( null === $payload ) {
			return new WP_Error( 'invalid_grant', 'Authorization code is invalid or expired.' );
		}
		if ( ! hash_equals( (string) ( $payload['client_id'] ?? '' ), $client_id ) ) {
			return new WP_Error( 'invalid_grant', 'Client mismatch for this authorization code.' );
		}
		if ( ! hash_equals( (string) ( $payload['redirect_uri'] ?? '' ), $redirect_uri ) ) {
			return new WP_Error( 'invalid_grant', 'redirect_uri does not match the authorization request.' );
		}
		if ( ! EMCP_Tools_OAuth_Util::verify_pkce( $verifier, (string) ( $payload['code_challenge'] ?? '' ), 'S256' ) ) {
			return new WP_Error( 'invalid_grant', 'PKCE verification failed.' );
		}
		return true;
	}

	/**
	 * Build the RFC 6749 token response body.
	 *
	 * @param string $access_token  Access token.
	 * @param string $refresh_token Refresh token.
	 * @param int    $expires_in    Access-token lifetime (seconds).
	 * @param string $scope         Granted scope.
	 * @return array
	 */
	public static function token_response( string $access_token, string $refresh_token, int $expires_in, string $scope ): array {
		return array(
			'access_token'  => $access_token,
			'token_type'    => 'Bearer',
			'expires_in'    => $expires_in,
			'refresh_token' => $refresh_token,
			'scope'         => $scope,
		);
	}

	// ---------------------------------------------------------------------
	// Internal
	// ---------------------------------------------------------------------

	/**
	 * Issue a refresh token and a bound access token.
	 *
	 * @param string $client_id Client id.
	 * @param int    $user_id   User the tokens act as.
	 * @param string $scope     Granted scope.
	 * @return array{access:string,refresh:string}
	 */
	private static function issue_pair( string $client_id, int $user_id, string $scope ): array {
		$refresh = EMCP_Tools_OAuth_Store::issue_token( 'refresh', $client_id, $user_id, $scope, self::REFRESH_TTL );
		$access  = EMCP_Tools_OAuth_Store::issue_token( 'access', $client_id, $user_id, $scope, self::ACCESS_TTL, (int) $refresh['id'] );
		return array( 'access' => $access['token'], 'refresh' => $refresh['token'] );
	}

	/**
	 * A JSON OAuth error response.
	 *
	 * @param string $code   OAuth error code.
	 * @param string $desc   Human description.
	 * @param int    $status HTTP status.
	 * @return WP_REST_Response
	 */
	private static function error( string $code, string $desc, int $status = 400 ) {
		return new WP_REST_Response(
			array(
				'error'             => $code,
				'error_description' => $desc,
			),
			$status
		);
	}
}

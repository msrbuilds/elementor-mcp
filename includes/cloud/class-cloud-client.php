<?php
/**
 * Authenticated EMCP Cloud REST client (bearer + auto-refresh).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Cloud_Client {
	const LEEWAY = 60; // refresh this many seconds before expiry.

	/**
	 * A valid access token, refreshing if near/after expiry. '' if unavailable.
	 *
	 * @return string
	 */
	public static function valid_access_token(): string {
		$c = EMCP_Tools_Cloud::get_connection();
		if ( empty( $c['access_token'] ) ) {
			return '';
		}
		if ( (int) ( $c['access_expires_at'] ?? 0 ) - self::LEEWAY <= time() ) {
			if ( ! EMCP_Tools_Cloud_Connect::refresh() ) {
				return '';
			}
			$c = EMCP_Tools_Cloud::get_connection();
		}
		return (string) ( $c['access_token'] ?? '' );
	}

	/**
	 * Authenticated Cloud API call (real verb, JSON, bearer). Astro's form-CSRF
	 * guard exempts JSON, so no Origin header is needed here (unlike the token
	 * endpoint). Returns the decoded JSON, or a WP_Error.
	 *
	 * @param string     $method HTTP method.
	 * @param string     $path   Path beginning with '/'.
	 * @param array|null $body   JSON body for writes; null for GET/DELETE.
	 * @return array|\WP_Error
	 */
	private static function authed( string $method, string $path, ?array $body ) {
		$token = self::valid_access_token();
		if ( '' === $token ) {
			return new \WP_Error( 'not_connected', __( 'This site is not connected to EMCP Cloud.', 'emcp-tools' ) );
		}
		$args = array( 'headers' => array( 'Authorization' => 'Bearer ' . $token, 'Accept' => 'application/json' ) );
		if ( null !== $body ) {
			$args['headers']['Content-Type'] = 'application/json';
			$args['body']                    = wp_json_encode( $body );
		}
		$res = EMCP_Tools_Cloud_Http::request( $method, EMCP_Tools_Cloud::base_url() . $path, $args );
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		if ( (int) $res['code'] < 200 || (int) $res['code'] >= 300 ) {
			return new \WP_Error( 'cloud_http_' . $res['code'], (string) ( $res['json']['error'] ?? 'cloud_error' ), array( 'status' => $res['code'] ) );
		}
		return $res['json'];
	}

	/**
	 * @param string $path Path.
	 * @return array|\WP_Error
	 */
	public static function get( string $path ) {
		return self::authed( 'GET', $path, null );
	}

	/**
	 * @param string $path Path.
	 * @param array  $body JSON body.
	 * @return array|\WP_Error
	 */
	public static function put( string $path, array $body ) {
		return self::authed( 'PUT', $path, $body );
	}

	/**
	 * @param string $path Path.
	 * @return array|\WP_Error
	 */
	public static function delete( string $path ) {
		return self::authed( 'DELETE', $path, null );
	}

	/**
	 * Upload the site's gateway credential to EMCP Cloud so the hosted gateway
	 * can redeem it. The Cloud-side route is a Phase 2 concern; this is purely
	 * the plugin-side client call.
	 *
	 * @param string $client_id     The gateway OAuth client id (from EMCP_Tools_Gateway_Credential).
	 * @param string $refresh_token The gateway-scoped refresh token (plaintext; sent once).
	 * @return bool True on success.
	 */
	public static function put_gateway_credential( string $client_id, string $refresh_token ): bool {
		return ! is_wp_error( self::put_gateway_credential_result( $client_id, $refresh_token ) );
	}

	/**
	 * Upload a Gateway credential while preserving transport failure details.
	 *
	 * @param string $client_id     Gateway OAuth client id.
	 * @param string $refresh_token Plaintext refresh token.
	 * @return array|\WP_Error
	 */
	public static function put_gateway_credential_result( string $client_id, string $refresh_token ) {
		$body = array(
			'client_id'      => $client_id,
			'refresh_token'  => $refresh_token,
			'site_uuid'      => EMCP_Tools_Cloud::site_uuid(),
			'token_endpoint' => (string) ( EMCP_Tools_OAuth_Metadata::authorization_server_document()['token_endpoint'] ?? '' ),
		);
		return self::put( '/api/cloud/v1/gateway/credential', $body );
	}

	/**
	 * Delete the site's gateway credential from EMCP Cloud.
	 *
	 * @return bool True on success.
	 */
	public static function delete_gateway_credential(): bool {
		$res = self::delete( '/api/cloud/v1/gateway/credential?site_uuid=' . rawurlencode( EMCP_Tools_Cloud::site_uuid() ) );
		return ! is_wp_error( $res );
	}

	/**
	 * Back-compat generic request (used by the sync layer + MCP tools).
	 *
	 * @param string $method HTTP method.
	 * @param string $path   Path.
	 * @param array  $body   JSON body for writes.
	 * @return array|\WP_Error
	 */
	public static function request( string $method, string $path, array $body = array() ) {
		return self::authed( strtoupper( $method ), $path, empty( $body ) ? null : $body );
	}
}

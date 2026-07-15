<?php
/**
 * OAuth discovery metadata — the two documents MCP clients fetch to bootstrap
 * the flow: Protected Resource Metadata (RFC 9728) and Authorization Server
 * Metadata (RFC 8414). Both are served at the site root under `/.well-known/`.
 *
 * @package EMCP_Tools
 * @since   3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds + serves the OAuth discovery documents.
 *
 * @since 3.4.1
 */
class EMCP_Tools_OAuth_Metadata {

	const PATH_PROTECTED_RESOURCE = '/.well-known/oauth-protected-resource';
	const PATH_AUTH_SERVER        = '/.well-known/oauth-authorization-server';

	/**
	 * Wire the root-level well-known request interception.
	 */
	public static function init(): void {
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
	}

	/**
	 * The issuer identifier (the site's home URL, no trailing slash).
	 *
	 * @return string
	 */
	public static function issuer(): string {
		return rtrim( (string) home_url(), '/' );
	}

	/**
	 * The protected resource identifier — the MCP server endpoint clients call.
	 *
	 * @return string
	 */
	public static function resource(): string {
		return rest_url( 'mcp/emcp-tools-server' );
	}

	/**
	 * Protected Resource Metadata document (RFC 9728).
	 *
	 * @return array
	 */
	public static function protected_resource_document(): array {
		return array(
			'resource'                 => self::resource(),
			'authorization_servers'    => array( self::issuer() ),
			'bearer_methods_supported' => array( 'header' ),
			'scopes_supported'         => array( EMCP_Tools_OAuth_Server::SCOPE ),
			'resource_documentation'   => 'https://emcptools.com/docs/',
		);
	}

	/**
	 * Authorization Server Metadata document (RFC 8414).
	 *
	 * @return array
	 */
	public static function authorization_server_document(): array {
		$base = EMCP_Tools_OAuth_Server::base_url();
		return array(
			'issuer'                                => self::issuer(),
			'authorization_endpoint'                => $base . '/authorize',
			'token_endpoint'                        => $base . '/token',
			'registration_endpoint'                 => $base . '/register',
			'revocation_endpoint'                   => $base . '/revoke',
			'scopes_supported'                      => array( EMCP_Tools_OAuth_Server::SCOPE ),
			'response_types_supported'              => array( 'code' ),
			'grant_types_supported'                 => array( 'authorization_code', 'refresh_token' ),
			'code_challenge_methods_supported'      => array( 'S256' ),
			'token_endpoint_auth_methods_supported' => array( 'none' ),
		);
	}

	/**
	 * Serve a well-known document when the request path matches, then exit.
	 * No-op for any other request.
	 *
	 * @param WP $wp Current WordPress environment (unused).
	 */
	public static function maybe_serve( $wp = null ): void {
		if ( ! EMCP_Tools_OAuth_Server::is_enabled() ) {
			return;
		}
		$path = self::request_path();
		if ( self::PATH_PROTECTED_RESOURCE === $path ) {
			self::emit( self::protected_resource_document() );
		}
		if ( self::PATH_AUTH_SERVER === $path ) {
			self::emit( self::authorization_server_document() );
		}
	}

	/**
	 * The current request path (no query string, no trailing slash except root).
	 *
	 * @return string
	 */
	private static function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( '/' !== $path ) {
			$path = untrailingslashit( $path );
		}
		return $path;
	}

	/**
	 * Emit a JSON document with permissive CORS (public discovery) and exit.
	 *
	 * @param array $doc Document.
	 */
	private static function emit( array $doc ): void {
		if ( ! headers_sent() ) {
			header( 'Content-Type: application/json; charset=utf-8' );
			header( 'Access-Control-Allow-Origin: *' );
			header( 'Cache-Control: public, max-age=3600' );
		}
		echo wp_json_encode( $doc );
		exit;
	}
}

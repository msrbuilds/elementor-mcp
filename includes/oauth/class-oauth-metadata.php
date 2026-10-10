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
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/** Register edge-friendly aliases for hosts that intercept `/.well-known/`. */
	public static function register_routes(): void {
		register_rest_route(
			EMCP_Tools_OAuth_Server::REST_NAMESPACE,
			'/protected-resource',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_protected_resource' ),
				'permission_callback' => '__return_true',
			)
		);
		register_rest_route(
			EMCP_Tools_OAuth_Server::REST_NAMESPACE,
			'/authorization-server',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'rest_authorization_server' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/** Edge-friendly protected-resource metadata URL used in the 401 challenge. */
	public static function protected_resource_url(): string {
		return EMCP_Tools_OAuth_Server::base_url() . '/protected-resource';
	}

	/** Edge rewrite target for RFC 8414 authorization-server metadata. */
	public static function authorization_server_url(): string {
		return EMCP_Tools_OAuth_Server::base_url() . '/authorization-server';
	}

	/** REST callback for protected-resource metadata. */
	public static function rest_protected_resource() {
		return self::rest_response( self::protected_resource_document() );
	}

	/** REST callback for authorization-server metadata. */
	public static function rest_authorization_server() {
		return self::rest_response( self::authorization_server_document() );
	}

	/** Build a public, cacheable metadata response. */
	private static function rest_response( array $document ) {
		$response = new WP_REST_Response( $document, 200 );
		$response->header( 'Access-Control-Allow-Origin', '*' );
		$response->header( 'Cache-Control', 'public, max-age=3600' );
		return $response;
	}

	/**
	 * The issuer identifier (the site's home URL, no trailing slash).
	 *
	 * @return string
	 */
	public static function issuer(): string {
		// The reachable public base (rest_url-derived / admin-overridable), NOT
		// home_url() — a host that pins the Site Address to a not-yet-live domain
		// would otherwise advertise an unreachable issuer and break OAuth
		// discovery. Matches resource()/base_url(), which already use rest_url().
		if ( class_exists( 'EMCP_Tools_Site_Context' ) ) {
			return EMCP_Tools_Site_Context::public_base_url();
		}
		return rtrim( (string) home_url(), '/' );
	}

	/**
	 * The protected resource identifier — the MCP server endpoint clients call.
	 *
	 * @return string
	 */
	public static function resource(): string {
		// Route through the reachable public base so an admin Server URL override
		// governs the whole OAuth flow (resource + token + issuer), not just the
		// issuer — otherwise a host that differs from rest_url() would produce an
		// inconsistent discovery document.
		if ( class_exists( 'EMCP_Tools_Site_Context' ) ) {
			return EMCP_Tools_Site_Context::rest_endpoint( 'mcp/emcp-tools-server' );
		}
		return rest_url( 'mcp/emcp-tools-server' );
	}

	/**
	 * Normalize an absolute OAuth resource URI for comparison. Scheme and host
	 * are case-insensitive; the path and query remain resource-defining.
	 *
	 * @param string $uri Candidate resource URI.
	 * @return string Empty when the URI is not a usable absolute HTTP(S) URI.
	 */
	public static function normalize_resource_uri( string $uri ): string {
		$parts = wp_parse_url( trim( $uri ) );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) || isset( $parts['fragment'] ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return '';
		}
		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return '';
		}
		$out = $scheme . '://' . strtolower( (string) $parts['host'] );
		if ( isset( $parts['port'] ) ) {
			$out .= ':' . (int) $parts['port'];
		}
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '';
		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
		}
		$out .= $path;
		if ( isset( $parts['query'] ) && '' !== (string) $parts['query'] ) {
			$out .= '?' . $parts['query'];
		}
		return $out;
	}

	/** Whether a presented resource identifies this MCP server. */
	public static function resource_matches( string $resource ): bool {
		return self::identifier_matches( $resource, self::resource() );
	}

	/**
	 * Compare metadata identifiers after URI-safe normalization.
	 *
	 * Used by the public discovery diagnostic for both the protected resource and
	 * issuer. A 200 response with the right key is not sufficient: another plugin
	 * can own the shared well-known route and return a valid document describing
	 * a different MCP server.
	 */
	public static function identifier_matches( string $presented, string $expected ): bool {
		$presented = self::normalize_resource_uri( $presented );
		$expected  = self::normalize_resource_uri( $expected );
		return '' !== $presented && '' !== $expected && hash_equals( $expected, $presented );
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
			'authorization_endpoint'                => EMCP_Tools_OAuth_Authorize::endpoint_url(),
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

		// Match both the root well-known path and the resource-scoped variant
		// clients build by appending OUR resource path, e.g.
		// /.well-known/oauth-protected-resource/wp-json/mcp/emcp-tools-server
		// (RFC 9728 §3.1). Exact-match-only 404s the request real MCP clients
		// actually make, which silently breaks OAuth discovery. Any OTHER suffix
		// belongs to another authorization server on this host (a second MCP
		// plugin with a path-based issuer, RFC 8414 §3.1) and must fall through
		// so that plugin can answer; claiming it hands the client a document
		// whose issuer does not match and the flow dies before consent.
		if ( self::path_matches( $path, self::PATH_PROTECTED_RESOURCE ) ) {
			self::emit( self::protected_resource_document() );
		}
		if ( self::path_matches( $path, self::PATH_AUTH_SERVER ) ) {
			self::emit( self::authorization_server_document() );
		}
	}

	/**
	 * Whether a request path is the given well-known path or the resource-scoped
	 * variant of it for EMCP's OWN resource (the well-known path followed by
	 * one of own_resource_paths()). A well-known path followed by any other
	 * resource path is another server's discovery request and is not matched.
	 *
	 * @param string $path      Request path (site-root-relative, no trailing slash).
	 * @param string $wellknown Base well-known path.
	 * @return bool
	 */
	public static function path_matches( string $path, string $wellknown ): bool {
		if ( $path === $wellknown ) {
			return true;
		}
		if ( 0 !== strpos( $path, $wellknown . '/' ) ) {
			return false;
		}
		$suffix = untrailingslashit( substr( $path, strlen( $wellknown ) ) );
		return in_array( $suffix, self::own_resource_paths(), true );
	}

	/**
	 * The resource path suffixes that identify EMCP's own MCP endpoint in a
	 * resource-scoped well-known request: the path of resource() as advertised,
	 * plus its site-root-relative form for subdirectory installs, where the
	 * request arrives home-path-stripped (see request_path()).
	 *
	 * @since 3.17.1
	 *
	 * @return string[] Paths starting with "/", no trailing slash.
	 */
	public static function own_resource_paths(): array {
		$resource = (string) wp_parse_url( self::resource(), PHP_URL_PATH );
		$resource = '/' . ltrim( untrailingslashit( $resource ), '/' );
		$paths    = array( $resource, self::normalize_request_path( $resource ) );
		return array_values( array_unique( array_filter( $paths, static function ( $p ) {
			return '' !== $p && '/' !== $p;
		} ) ) );
	}

	/**
	 * The current request path (no query string, no trailing slash except root).
	 *
	 * @return string
	 */
	private static function request_path(): string {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) && is_string( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );

		// Subdirectory installs (e.g. WordPress at /gpt-build/) receive the request
		// as `/gpt-build/.well-known/oauth-protected-resource`, but the well-known
		// paths we match against are site-root-relative (`/.well-known/…`). Strip
		// the home path prefix so the match works there too — otherwise the
		// metadata document we advertise in WWW-Authenticate 404s and OAuth
		// discovery (ChatGPT, Claude, any RFC 9728 client) dead-ends. No-op on a
		// root install where the home path is empty.
		return self::normalize_request_path( $path );
	}

	/**
	 * Normalize a front-controller request path for root and subdirectory sites.
	 * Shared by discovery and the browser authorization endpoint.
	 *
	 * @param string $path Raw URL path.
	 * @return string Site-root-relative path without a trailing slash.
	 */
	public static function normalize_request_path( string $path ): string {
		$path = self::strip_home_path( $path );
		return '/' === $path ? $path : untrailingslashit( $path );
	}

	/**
	 * Remove the WordPress home-URL path prefix (the subdirectory a site is
	 * installed under) from a request path. `/gpt-build/.well-known/x` → `/.well-known/x`;
	 * a root install (`home` path `''` or `/`) returns the path unchanged.
	 *
	 * @since 3.12.2
	 *
	 * @param string $path Request path.
	 * @return string
	 */
	public static function strip_home_path( string $path ): string {
		$home = function_exists( 'home_url' ) ? (string) wp_parse_url( home_url(), PHP_URL_PATH ) : '';
		$home = untrailingslashit( (string) $home );
		if ( '' === $home || '/' === $home ) {
			return $path;
		}
		if ( 0 === strpos( $path, $home . '/' ) || $path === $home ) {
			$stripped = substr( $path, strlen( $home ) );
			return '' === $stripped ? '/' : $stripped;
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

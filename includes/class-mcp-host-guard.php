<?php
/**
 * MCP host-mismatch guard.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Refuses MCP requests whose Host header does not match this site's home host,
 * so a connector still pointed at an old (renamed/temporary) domain fails
 * loudly with an actionable error instead of a generic "tool execution error".
 *
 * See bug report Issue 2 (a connector kept a stale hostname after a site URL
 * change; tool listing still worked but every execution failed opaquely).
 */
class EMCP_Tools_MCP_Host_Guard {

	/**
	 * Normalise a host for comparison: lowercase, strip trailing port and a
	 * leading "www.".
	 *
	 * @param string $host Raw host.
	 * @return string Normalised host.
	 */
	private static function norm( string $host ): string {
		$host = strtolower( trim( $host ) );
		$host = (string) preg_replace( '/:\d+$/', '', $host );   // strip :443
		$host = (string) preg_replace( '/^www\./', '', $host );  // strip www.
		return $host;
	}

	/**
	 * Pure host comparison. An empty request host is treated as a match so a
	 * missing Host header can never brick the endpoint (fail-open).
	 *
	 * @param string $request_host Incoming request host.
	 * @param string $home_host    This site's home_url() host.
	 * @return bool True when the hosts match (or the request host is empty).
	 */
	public static function host_matches( string $request_host, string $home_host ): bool {
		if ( '' !== $request_host && ! preg_match( '/\A(?:[a-z0-9.-]+|\[[a-f0-9:]+\])(?::[0-9]{1,5})?\z/i', $request_host ) ) {
			return false;
		}
		$req = self::norm( $request_host );
		if ( '' === $req ) {
			return true;
		}
		return $req === self::norm( $home_host );
	}

	/** Whether a REST route is (under) the EMCP MCP server route. */
	public static function is_mcp_route( string $route ): bool {
		return 0 === strpos( $route, '/mcp/emcp-tools-server' );
	}

	/** Whether a REST route is (under) the EMCP OAuth namespace. */
	public static function is_oauth_route( string $route ): bool {
		return 0 === strpos( $route, '/emcp-tools/oauth/' );
	}

	/**
	 * Guarantee a clean JSON body on our MCP + OAuth routes by forcing PHP's
	 * display_errors OFF for the request. A stray notice/warning printed into the
	 * response — e.g. WordPress's own `_doing_it_wrong` HTML for a not-yet-
	 * registered ability, or any deprecation notice from another plugin — corrupts
	 * the JSON-RPC / OAuth JSON, and the client drops the connection ("OAuth
	 * connection terminated"). This runs regardless of WP_DEBUG_DISPLAY; errors
	 * still go to the log. rest_pre_dispatch fires before the route callback and
	 * response serialization, so this covers where such output would land.
	 *
	 * @param string $route Requested REST route.
	 * @return void
	 */
	private static function harden_json_output( string $route ): void {
		if ( self::is_mcp_route( $route ) || self::is_oauth_route( $route ) ) {
			// phpcs:ignore WordPress.PHP.IniSet.display_errors_Disallowed -- deliberately silencing on-screen errors so they cannot corrupt this JSON response; they still log.
			@ini_set( 'display_errors', '0' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
	}

	/**
	 * rest_pre_serve_request filter — tell LiteSpeed / proxies not to cache or
	 * buffer MCP responses (the leading suspect for intermittent "connector isn't
	 * responding" on shared LiteSpeed hosting).
	 *
	 * @param bool  $served  Whether the request has been served.
	 * @param mixed $result  Response (unused).
	 * @param mixed $request WP_REST_Request.
	 * @param mixed $server  REST server (unused).
	 * @return bool Unmodified $served.
	 */
	public static function no_store_headers( $served, $result, $request, $server ) {
		if ( is_object( $request ) && method_exists( $request, 'get_route' ) && self::is_mcp_route( (string) $request->get_route() ) && ! headers_sent() ) {
			header( 'Cache-Control: no-store, no-cache, must-revalidate, max-age=0' );
			header( 'X-LiteSpeed-Cache-Control: no-cache' );
			header( 'X-Accel-Buffering: no' );
		}
		return $served;
	}

	/**
	 * rest_pre_dispatch filter — acts only on the MCP server route.
	 *
	 * @param mixed  $result  Dispatch result (passed through untouched on match).
	 * @param mixed  $server  REST server (unused).
	 * @param mixed  $request WP_REST_Request.
	 * @return mixed The original result, or a WP_Error on host mismatch.
	 */
	public static function guard( $result, $server, $request ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_route' ) ) {
			return $result;
		}
		$route = (string) $request->get_route();
		// Keep our JSON responses clean on both MCP and OAuth routes.
		self::harden_json_output( $route );
		if ( ! self::is_mcp_route( $route ) ) {
			return $result;
		}
		/**
		 * Filter: disable the MCP host guard (e.g. for unusual reverse-proxy setups).
		 *
		 * @param bool $enabled Default true.
		 */
		if ( ! apply_filters( 'emcp_tools_mcp_host_guard_enabled', true ) ) {
			return $result;
		}

		$req_host = $request->get_header( 'host' );
		if ( ! is_string( $req_host ) || '' === $req_host ) {
			$req_host = isset( $_SERVER['HTTP_HOST'] ) && is_string( $_SERVER['HTTP_HOST'] ) ? wp_unslash( $_SERVER['HTTP_HOST'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- host_matches strictly validates the complete host before use.
		}
		$expected = class_exists( 'EMCP_Tools_Site_Context' )
			? EMCP_Tools_Site_Context::public_host()
			: (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( self::host_matches( $req_host, $expected ) ) {
			return $result;
		}
		$endpoint = class_exists( 'EMCP_Tools_Site_Context' )
			? EMCP_Tools_Site_Context::mcp_endpoint()
			: home_url( '/wp-json/mcp/emcp-tools-server' );

		return new WP_Error(
			'emcp_host_mismatch',
			sprintf(
				/* translators: %s: the correct MCP endpoint URL. */
				__( 'Site URL mismatch: this connector is pointed at an old domain. The MCP endpoint now lives at %s — reconnect using that URL.', 'emcp-tools' ),
				esc_url_raw( $endpoint )
			),
			array( 'status' => 421 ) // 421 Misdirected Request.
		);
	}
}

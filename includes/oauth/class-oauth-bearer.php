<?php
/**
 * Bearer-token authentication for the MCP transport. Wired in as the server's
 * `transport_permission_callback`: it authenticates OAuth access tokens by
 * resolving them to the WordPress user they were issued for, and falls through
 * to WordPress's normal auth (Application Password / cookie) when no Bearer
 * token is present — so both connection methods coexist.
 *
 * Also emits the `WWW-Authenticate` challenge on unauthorized MCP responses so
 * clients can discover the OAuth flow (RFC 9728 §5.1).
 *
 * @package EMCP_Tools
 * @since   3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bearer validation + the 401 discovery challenge.
 *
 * @since 3.4.1
 */
class EMCP_Tools_OAuth_Bearer {

	/**
	 * The EMCP OAuth client id that authenticated this request ('' when none).
	 *
	 * @var string
	 */
	private static $authenticated_client_id = '';

	/**
	 * The EMCP OAuth client that authenticated this request, for the request
	 * context (spec 9.2).
	 */
	public static function authenticated_client_id(): string {
		return self::$authenticated_client_id;
	}

	/**
	 * Transport permission callback. Returns bool (fail-closed).
	 *
	 * @param WP_REST_Request $request The MCP request.
	 * @return bool
	 */
	public static function permission_callback( $request ): bool {
		$token = self::bearer_token( $request );

		if ( '' !== $token ) {
			// Clean at validation time: an OAuth-authenticated MCP request is the
			// natural, high-frequency point to purge expired tokens/orphans. Kept
			// throttled so it is not a DELETE per request; this keeps the tables
			// tidy on active sites within minutes, not waiting on a daily WP-Cron.
			EMCP_Tools_OAuth_Store::gc_throttled();
			$row = EMCP_Tools_OAuth_Store::find_token( $token, 'access' );
			if ( null !== $row && EMCP_Tools_OAuth_Metadata::resource_matches( (string) ( $row['resource'] ?? EMCP_Tools_OAuth_Metadata::resource() ) ) ) {
				wp_set_current_user( (int) $row['user_id'] );
				self::$authenticated_client_id = (string) ( $row['client_id'] ?? '' );
				return true;
			}
			return false; // Bearer present but invalid/expired → 401.
		}

		// No Bearer token: preserve the adapter's default behaviour so
		// Application-Password / cookie auth continues to work.
		$cap = apply_filters( 'mcp_adapter_default_transport_permission_user_capability', 'read', $request );
		if ( ! is_string( $cap ) || '' === $cap ) {
			$cap = 'read';
		}
		return current_user_can( $cap );
	}

	/**
	 * Extract the Bearer token from a request (or the raw Authorization header).
	 *
	 * @param WP_REST_Request|null $request Request.
	 * @return string Raw token, or '' when absent.
	 */
	public static function bearer_token( $request = null ): string {
		$header = '';
		if ( is_object( $request ) && method_exists( $request, 'get_header' ) ) {
			$header = (string) $request->get_header( 'authorization' );
		}
		if ( '' === $header && isset( $_SERVER['HTTP_AUTHORIZATION'] ) && is_string( $_SERVER['HTTP_AUTHORIZATION'] ) ) {
			return self::parse_bearer( wp_unslash( $_SERVER['HTTP_AUTHORIZATION'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict credential grammar below rejects malformed bytes without altering credentials.
		}
		if ( '' === $header && isset( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) && is_string( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ) {
			return self::parse_bearer( wp_unslash( $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Strict credential grammar below rejects malformed bytes without altering credentials.
		}
		return self::parse_bearer( $header );
	}

	/**
	 * Parse a Bearer token out of an Authorization header value.
	 *
	 * @param string $header Header value.
	 * @return string Token, or '' if the header is not a Bearer credential.
	 */
	public static function parse_bearer( string $header ): string {
		if ( preg_match( '/\A[ \t]*Bearer[ \t]+([A-Za-z0-9\-._~+\/]+=*)[ \t]*\z/i', $header, $m ) ) {
			return $m[1];
		}
		return '';
	}

	/**
	 * Build the OAuth challenge advertised by the MCP endpoint.
	 *
	 * Keep this in one testable helper: the metadata URL must remain specific to
	 * EMCP. Advertising the shared bare `/.well-known/oauth-protected-resource`
	 * path lets another OAuth/MCP plugin on the same site answer with metadata for
	 * a different resource before EMCP ever sees the request.
	 */
	public static function challenge_value(): string {
		return sprintf(
			'Bearer resource_metadata="%s", scope="%s"',
			esc_url_raw( EMCP_Tools_OAuth_Metadata::protected_resource_url() ),
			EMCP_Tools_OAuth_Server::SCOPE
		);
	}

	/**
	 * Add the `WWW-Authenticate` challenge to unauthorized responses on the MCP
	 * route, pointing clients at the protected-resource metadata.
	 *
	 * Hooked on `rest_post_dispatch`.
	 *
	 * @param WP_HTTP_Response $response Response.
	 * @param WP_REST_Server   $server   Server (unused).
	 * @param WP_REST_Request  $request  Request.
	 * @return WP_HTTP_Response
	 */
	public static function maybe_challenge( $response, $server, $request ) {
		if ( ! is_object( $response ) || ! is_object( $request ) ) {
			return $response;
		}
		$status = (int) $response->get_status();
		if ( 401 !== $status && 403 !== $status ) {
			return $response;
		}
		if ( false === strpos( (string) $request->get_route(), 'mcp/emcp-tools-server' ) ) {
			return $response;
		}
		$response->header( 'WWW-Authenticate', self::challenge_value() );
		return $response;
	}
}

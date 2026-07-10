<?php
/**
 * URL-based authentication for the EMCP MCP server endpoint.
 *
 * Some hosts (Apache/Plesk/LiteSpeed/IIS configs — see the Connection tab's
 * "Got 401 Unauthorized?" troubleshooting) strip the Authorization header
 * before it reaches PHP, which breaks the standard Basic-auth Application
 * Password flow entirely. This class adds an additional, opt-in authentication
 * method: the same "username:application_password" credentials, Base64-encoded
 * and passed as a URL query parameter, so a client can authenticate even when
 * the Authorization header never arrives. It only ever validates Application
 * Passwords (via the same core function core's Basic-auth handler uses) — a
 * regular account password will never work here.
 *
 * Off by default: credentials in a URL can end up in server access logs,
 * browser history, or a Referer header, so the admin must opt in on the
 * Connection tab.
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authenticates MCP server requests using Base64 "user:app_password"
 * credentials supplied via a URL query parameter.
 *
 * @since 3.2.0
 */
class EMCP_Tools_Url_Auth {

	/**
	 * Option name for the opt-in toggle (Connection tab).
	 *
	 * @since 3.2.0
	 */
	const OPTION_ENABLED = 'emcp_tools_url_auth_enabled';

	/**
	 * Query parameter name carrying the Base64-encoded credentials. Accepts
	 * either a bare Base64 token or a "Basic <token>" value (mirrors the
	 * Authorization header format, so the same generated value works in both
	 * places).
	 *
	 * @since 3.2.0
	 */
	const PARAM = 'emcp_auth';

	/**
	 * Registers the authentication filter.
	 *
	 * @since 3.2.0
	 */
	public function init(): void {
		// Priority 15: after plugins that populate PHP_AUTH_* from other means,
		// before nothing in particular — we only act when no user is resolved
		// yet and defer entirely otherwise.
		add_filter( 'determine_current_user', array( $this, 'authenticate' ), 15 );
	}

	/**
	 * Whether URL-based authentication is enabled.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return '1' === (string) get_option( self::OPTION_ENABLED, '0' );
	}

	/**
	 * `determine_current_user` callback. Resolves a WordPress user ID from
	 * Base64 "username:application_password" credentials in the request's
	 * `emcp_auth` query parameter, scoped to the EMCP MCP server route.
	 *
	 * @since 3.2.0
	 *
	 * @param int|bool $user Existing resolved user (from an earlier filter), or false/empty.
	 * @return int|bool The authenticated user ID, or the passed-through value.
	 */
	public function authenticate( $user ) {
		// Don't override an already-resolved user (cookie auth, Basic auth, etc.).
		if ( ! empty( $user ) ) {
			return $user;
		}

		if ( ! self::is_enabled() ) {
			return $user;
		}

		if ( ! $this->is_mcp_server_request() ) {
			return $user;
		}

		$credentials = $this->extract_credentials();
		if ( null === $credentials ) {
			return $user;
		}

		list( $username, $password ) = $credentials;
		if ( '' === $username || '' === $password ) {
			return $user;
		}

		if ( ! function_exists( 'wp_authenticate_application_password' ) ) {
			return $user;
		}

		$authenticated = wp_authenticate_application_password( null, $username, $password );

		if ( $authenticated instanceof WP_User ) {
			return $authenticated->ID;
		}

		return $user;
	}

	/**
	 * Whether the current request targets the EMCP MCP server route. Matches
	 * both pretty-permalink (`/wp-json/mcp/emcp-tools-server`) and plain
	 * (`?rest_route=/mcp/emcp-tools-server`) request URIs.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	private function is_mcp_server_request(): bool {
		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- read-only substring match, not used as output or a filesystem/SQL value.
		$uri = (string) $_SERVER['REQUEST_URI'];

		return false !== strpos( $uri, 'mcp/emcp-tools-server' );
	}

	/**
	 * Extracts and decodes the `username`/`application password` pair from the
	 * `emcp_auth` query parameter.
	 *
	 * @since 3.2.0
	 *
	 * @return array{0:string,1:string}|null Two-element [username, password] array, or null if absent/malformed.
	 */
	private function extract_credentials(): ?array {
		if ( empty( $_GET[ self::PARAM ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- an auth credential, not a nonce-protected action.
			return null;
		}

		$raw = sanitize_text_field( wp_unslash( $_GET[ self::PARAM ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw = trim( (string) preg_replace( '/^Basic\s+/i', '', trim( $raw ) ) );

		if ( '' === $raw ) {
			return null;
		}

		$decoded = base64_decode( $raw, true );
		if ( false === $decoded || false === strpos( $decoded, ':' ) ) {
			return null;
		}

		$parts = explode( ':', $decoded, 2 );

		return array( (string) $parts[0], (string) $parts[1] );
	}
}

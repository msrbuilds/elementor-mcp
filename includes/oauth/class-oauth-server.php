<?php
/**
 * OAuth server orchestrator — owns the enable/availability gate, installs the
 * storage on init, and (from Phase 2 onward) registers the discovery, DCR,
 * authorize, token and bearer routes.
 *
 * OAuth sign-in is a free, core connectivity feature. It is available only over
 * HTTPS (localhost exempt) and, by default, enabled wherever it is available.
 *
 * @package EMCP_Tools
 * @since   3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Coordinates the EMCP OAuth 2.1 authorization server.
 *
 * @since 3.4.1
 */
class EMCP_Tools_OAuth_Server {

	const OPTION_ENABLED = 'emcp_tools_oauth_enabled';
	const REST_NAMESPACE = 'emcp-tools/oauth/v1';
	const SCOPE          = 'mcp';

	/**
	 * Wire hooks. Called from the bootstrap.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'on_init' ), 20 );
	}

	/**
	 * On init: install storage when the feature is available. Route
	 * registration is added by later phases.
	 */
	public static function on_init(): void {
		if ( ! self::is_available() ) {
			return;
		}
		EMCP_Tools_OAuth_Store::maybe_install();

		if ( ! self::is_enabled() ) {
			return;
		}
		// Discovery documents at the site root.
		EMCP_Tools_OAuth_Metadata::init();
		// REST routes for the OAuth namespace (register / authorize / token …).
		add_action( 'rest_api_init', array( 'EMCP_Tools_OAuth_Clients', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'EMCP_Tools_OAuth_Authorize', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'EMCP_Tools_OAuth_Token', 'register_routes' ) );
		// Emit the WWW-Authenticate discovery challenge on unauthorized MCP responses.
		add_filter( 'rest_post_dispatch', array( 'EMCP_Tools_OAuth_Bearer', 'maybe_challenge' ), 10, 3 );
	}

	/**
	 * Whether OAuth sign-in is both available and switched on. This is the gate
	 * every runtime path (routes, bearer auth, 401 challenge, admin UI) checks.
	 *
	 * @return bool
	 */
	public static function is_enabled(): bool {
		return self::is_available() && self::option_enabled();
	}

	/**
	 * Whether OAuth sign-in can run at all on this site (HTTPS, or local dev).
	 * Filterable via `emcp_tools_oauth_available` for edge hosting.
	 *
	 * @return bool
	 */
	public static function is_available(): bool {
		return (bool) apply_filters( 'emcp_tools_oauth_available', self::https_ok() );
	}

	/**
	 * Whether the admin toggle is on. Defaults to ON wherever the feature is
	 * available (per the "on when HTTPS" decision); an explicit stored value
	 * ('0'/'1') wins.
	 *
	 * @return bool
	 */
	public static function option_enabled(): bool {
		$stored = get_option( self::OPTION_ENABLED, null );
		if ( null === $stored || '' === $stored ) {
			return self::https_ok();
		}
		return in_array( $stored, array( '1', 1, true ), true );
	}

	/**
	 * True over HTTPS, or on a local development host (where http is fine).
	 *
	 * @return bool
	 */
	public static function https_ok(): bool {
		if ( function_exists( 'is_ssl' ) && is_ssl() ) {
			return true;
		}
		if ( 0 === strpos( (string) home_url(), 'https://' ) ) {
			return true;
		}
		$host = isset( $_SERVER['HTTP_HOST'] ) ? strtolower( (string) wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';
		return self::is_local_host( $host );
	}

	/**
	 * The absolute base URL for the OAuth REST namespace.
	 *
	 * @return string
	 */
	public static function base_url(): string {
		return rest_url( self::REST_NAMESPACE );
	}

	/**
	 * Whether a host is a local development host (loopback / *.test / *.local).
	 *
	 * @param string $host Host (may include a :port).
	 * @return bool
	 */
	private static function is_local_host( string $host ): bool {
		$host = (string) preg_replace( '/:\d+$/', '', $host );
		if ( in_array( $host, array( 'localhost', '127.0.0.1', '::1' ), true ) ) {
			return true;
		}
		return (bool) preg_match( '/\.(test|local|localhost)$/', $host );
	}
}

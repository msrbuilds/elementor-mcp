<?php
/**
 * Who is making the current request (spec 9.2): client label, session and
 * credential tag, resolved once per request, plus the per-request flags the MCP
 * log needs. Transport-level: the MCP log calls for_event() with the router's
 * observability tags; the REST fallback uses the HTTP branch directly.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-request identity and flags for the MCP log.
 */
final class EMCP_Tools_Request_Context {

	/**
	 * Whether an mcp.request row was written for this HTTP request.
	 *
	 * @var bool
	 */
	private static $logged = false;

	/**
	 * '' or 'not_recorded'.
	 *
	 * @var string
	 */
	private static $ledger = '';

	/**
	 * Stdio session => setup id (positive results only).
	 *
	 * @var array<string,string>
	 */
	private static $cli_setups = array();

	/**
	 * Cached HTTP auth for this request.
	 *
	 * @var array|null
	 */
	private static $http_auth = null;

	/**
	 * Client and session of the AI Chat tool call being executed (spec 9.2).
	 *
	 * @var array{client:string, session:string}
	 */
	private static $chat = array(
		'client'  => '',
		'session' => '',
	);

	/** Start of a new HTTP request. */
	public static function reset(): void {
		self::$logged    = false;
		self::$ledger    = '';
		self::$http_auth = null;
		self::$chat      = array(
			'client'  => '',
			'session' => '',
		);
	}

	/** Tests: also forget the per-process stdio cache. */
	public static function reset_for_tests(): void {
		self::reset();
		self::$cli_setups = array();
	}

	/** An mcp.request row was written for this request. */
	public static function mark_logged(): void {
		self::$logged = true;
	}

	/** Whether an mcp.request row was written for this request. */
	public static function was_logged(): bool {
		return self::$logged;
	}

	/** A change made in this request could not be added to History (spec 9.1). */
	public static function flag_ledger_not_recorded(): void {
		self::$ledger = 'not_recorded';
	}

	/** The ledger flag for the row being written; cleared once read. */
	public static function take_ledger(): string {
		$flag         = self::$ledger;
		self::$ledger = '';
		return $flag;
	}

	/**
	 * Record who runs the AI Chat tool call in progress ('' and '' clear it).
	 *
	 * @param string $client  Label: AI Chat, AI Chat (Elementor) or AI Chat (Gutenberg).
	 * @param string $session chat-{user_id}-{key}, or '' when the call has no group.
	 */
	public static function set_chat( string $client, string $session ): void {
		self::$chat = array(
			'client'  => $client,
			'session' => $session,
		);
	}

	/**
	 * The AI Chat call in progress; empty strings outside one.
	 *
	 * @return array{client:string, session:string}
	 */
	public static function chat(): array {
		return self::$chat;
	}

	/**
	 * Client and session to stamp on a change recorded now (spec 9.2): the AI
	 * Chat call in progress, this WP-CLI process, the MCP HTTP credential with
	 * its Mcp-Session-Id, or Admin for wp-admin and admin REST.
	 *
	 * @return array{client:string, session:string}
	 */
	public static function current(): array {
		if ( '' !== self::$chat['client'] ) {
			return self::$chat;
		}
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			return array(
				'client'  => 'WP-CLI',
				'session' => self::cli_session(),
			);
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- only matched, never output.
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$sid = isset( $_SERVER['HTTP_MCP_SESSION_ID'] ) ? EMCP_Tools_Change_Codec::text( sanitize_text_field( wp_unslash( $_SERVER['HTTP_MCP_SESSION_ID'] ) ), 100 ) : '';
		if ( '' !== $sid || false !== strpos( $uri, '/mcp/emcp-tools-server' ) ) {
			$cred = self::http_credential( self::http_auth() );
			return array(
				'client'  => EMCP_Tools_Change_Codec::text( $cred['client'], 100 ),
				'session' => $sid,
			);
		}
		if ( ( function_exists( 'is_admin' ) && is_admin() ) || false !== strpos( $uri, '/emcp-tools/v1/admin/' ) ) {
			return array(
				'client'  => 'Admin',
				'session' => '',
			);
		}
		return array(
			'client'  => '',
			'session' => '',
		);
	}

	/** A stable id for this WP-CLI process: pid plus process start time. */
	public static function cli_session(): string {
		$start = isset( $_SERVER['REQUEST_TIME_FLOAT'] ) ? (string) $_SERVER['REQUEST_TIME_FLOAT'] : '';
		return 'cli-' . substr( md5( getmypid() . '|' . $start ), 0, 16 );
	}

	/**
	 * Credential tag and client label for an HTTP request.
	 *
	 * @param array $auth oauth_client_id, oauth_client_name, app_uuid, app_name.
	 * @return array{credential:string, client:string}
	 */
	public static function http_credential( array $auth ): array {
		if ( ! empty( $auth['oauth_client_id'] ) ) {
			return array(
				'credential' => 'oauth:' . $auth['oauth_client_id'],
				'client'     => '' !== (string) ( $auth['oauth_client_name'] ?? '' ) ? (string) $auth['oauth_client_name'] : __( 'OAuth app', 'emcp-tools' ),
			);
		}
		if ( ! empty( $auth['app_uuid'] ) ) {
			return array(
				'credential' => 'app:' . $auth['app_uuid'],
				'client'     => '' !== (string) ( $auth['app_name'] ?? '' ) ? (string) $auth['app_name'] : __( 'Application password', 'emcp-tools' ),
			);
		}
		return array(
			'credential' => '',
			'client'     => 'HTTP',
		);
	}

	/** Collect this request's authentication from WordPress (cached). */
	public static function http_auth(): array {
		if ( null !== self::$http_auth ) {
			return self::$http_auth;
		}
		$auth      = array();
		$client_id = class_exists( 'EMCP_Tools_OAuth_Bearer' ) ? EMCP_Tools_OAuth_Bearer::authenticated_client_id() : '';
		if ( '' !== $client_id ) {
			$client                    = class_exists( 'EMCP_Tools_OAuth_Store' ) ? EMCP_Tools_OAuth_Store::get_client( $client_id ) : null;
			$auth['oauth_client_id']   = $client_id;
			$auth['oauth_client_name'] = is_array( $client ) ? (string) ( $client['client_name'] ?? '' ) : '';
		} elseif ( function_exists( 'rest_get_authenticated_app_password' ) ) {
			$uuid = rest_get_authenticated_app_password();
			if ( is_string( $uuid ) && '' !== $uuid ) {
				$auth['app_uuid'] = $uuid;
				if ( class_exists( 'WP_Application_Passwords' ) ) {
					$item             = WP_Application_Passwords::get_user_application_password( get_current_user_id(), $uuid );
					$auth['app_name'] = is_array( $item ) ? (string) ( $item['name'] ?? '' ) : '';
				}
			}
		}
		self::$http_auth = $auth;
		return $auth;
	}

	/**
	 * The setup a stdio process belongs to, found by its EMCP_SETUP token on
	 * any initialize (a process started just before the setup opened is still
	 * tagged at its next handshake). Only positive results are cached.
	 *
	 * @param string      $method  JSON-RPC method.
	 * @param string      $session Stdio session.
	 * @param string|null $token   Token; null reads the EMCP_SETUP environment variable.
	 */
	public static function stdio_setup( string $method, string $session, ?string $token = null ): string {
		if ( isset( self::$cli_setups[ $session ] ) ) {
			return self::$cli_setups[ $session ];
		}
		if ( 'initialize' !== $method || ! class_exists( 'EMCP_Tools_Connection_Setup' ) ) {
			return '';
		}
		$token = $token ?? (string) getenv( 'EMCP_SETUP' );
		$found = EMCP_Tools_Connection_Setup::find_by_token( $token );
		if ( null === $found ) {
			return '';
		}
		EMCP_Tools_Connection_Setup::tag( (int) $found['user_id'], 'cli_session', $session );
		self::$cli_setups[ $session ] = (string) $found['record']['setup_id'];
		return self::$cli_setups[ $session ];
	}

	/**
	 * Client, session, credential and setup for a router observability event.
	 *
	 * @param array $tags mcp.request tags (transport, method, session_id, new_session_id).
	 * @return array{client:string, session:string, credential:string, setup:string}
	 */
	public static function for_event( array $tags ): array {
		if ( 'stdio' === strtolower( (string) ( $tags['transport'] ?? '' ) ) ) {
			$session = self::cli_session();
			return array(
				'client'     => 'WP-CLI',
				'session'    => $session,
				'credential' => 'cli:' . $session,
				'setup'      => self::stdio_setup( (string) ( $tags['method'] ?? '' ), $session ),
			);
		}
		$session = (string) ( $tags['new_session_id'] ?? '' );
		if ( '' === $session ) {
			$session = (string) ( $tags['session_id'] ?? '' );
		}
		$cred = self::http_credential( self::http_auth() );
		return array(
			'client'     => $cred['client'],
			'session'    => $session,
			'credential' => $cred['credential'],
			'setup'      => '',
		);
	}
}

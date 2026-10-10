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
	/** Hard bounds on a public (unauthenticated) registration. */
	const MAX_CLIENT_NAME     = 191;  // matches the stored column width.
	const MAX_REDIRECT_URIS   = 10;   // native clients register 1-3 in practice.
	const MAX_REDIRECT_URI_LEN = 512; // per URI.
	const MAX_METADATA_BYTES  = 4096; // aggregate name + URIs.
	/** Registrations allowed per IP inside REGISTER_WINDOW. */
	const REGISTER_MAX    = 20;
	const REGISTER_WINDOW = 900;
	/** Ceiling on never-authorized client rows (address-rotation proof). */
	const MAX_UNAUTHORIZED = 500;
	/**
	 * Schemes that must never be accepted as a redirect target. Native apps
	 * legitimately use private-use schemes (RFC 8252 7.1), so the scheme rule
	 * stays permissive, but browser-executable, local-file, and opaque schemes
	 * have no place in a redirect and are refused outright.
	 */
	/** Registered IANA schemes that are transports or messaging, not app callbacks. */
	const RESERVED_SCHEMES = array(
		'ftp', 'ftps', 'sftp', 'tftp', 'mailto', 'tel', 'sms', 'fax', 'callto',
		'telnet', 'ssh', 'rsync', 'ldap', 'ldaps', 'news', 'nntp', 'gopher',
		'ws', 'wss', 'irc', 'ircs', 'smb', 'nfs', 'rtsp', 'rtmp', 'mms',
		'magnet', 'bitcoin', 'webcal', 'feed', 'imap', 'pop', 'smtp', 'dns',
	);
	const DENIED_SCHEMES = array( 'javascript', 'data', 'vbscript', 'file', 'blob', 'about', 'jar', 'view-source', 'chrome', 'chrome-extension', 'resource', 'filesystem' );

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
		if ( self::register_rate_limited() ) {
			return new WP_REST_Response(
				array( 'error' => 'temporarily_unavailable', 'error_description' => 'Too many registration requests. Try again later.' ),
				429
			);
		}
		// Address-rotation proof ceiling: the per-caller throttle above can be
		// sidestepped by changing source address, this cannot.
		if ( self::unauthorized_at_capacity() ) {
			return new WP_REST_Response(
				array( 'error' => 'temporarily_unavailable', 'error_description' => 'Registration is temporarily closed. Try again later.' ),
				503
			);
		}
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
	 * Simple per-IP registration throttle. Public DCR must stay open (clients
	 * self-register before any user exists), so this bounds abuse rather than
	 * requiring auth. Behind a trusted proxy the forwarded client IP is used.
	 *
	 * @return bool True when the caller is over budget.
	 */
	public static function client_ip(): string {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) && is_string( $_SERVER['REMOTE_ADDR'] ) ? filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ), FILTER_VALIDATE_IP ) : false;
		$remote = false === $remote ? '' : $remote;
		/**
		 * Reverse proxies whose forwarded client-IP headers may be believed.
		 *
		 * EMPTY BY DEFAULT and deliberately so: this endpoint is unauthenticated,
		 * so trusting CF-Connecting-IP / X-Forwarded-For without checking the peer
		 * lets any direct caller mint a fresh throttle bucket per request simply by
		 * varying a header. Only add the addresses of proxies you actually run.
		 *
		 * @param string[] $proxies Trusted proxy IPs (REMOTE_ADDR values).
		 */
		$trusted = (array) apply_filters( 'emcp_tools_trusted_proxies', array() );
		if ( '' !== $remote && in_array( $remote, $trusted, true ) ) {
			foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR' ) as $key ) {
				if ( empty( $_SERVER[ $key ] ) || ! is_string( $_SERVER[ $key ] ) ) {
					continue;
				}
				$raw   = wp_unslash( $_SERVER[ $key ] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validate IP below without transforming malformed header bytes into a valid address.
				if ( preg_match( '/[\x00-\x1f\x7f]/', $raw ) ) {
					continue;
				}
				$first = trim( explode( ',', $raw )[0] );
				if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
					return $first;
				}
			}
		}
		return '' !== $remote ? $remote : 'unknown';
	}

	/**
	 * Per-caller registration throttle.
	 *
	 * Best-effort only: the transient counter is read-modify-write, so a burst of
	 * simultaneous requests can slip a few past the cap. It exists to slow
	 * table growth, and unauthorized_at_capacity() is the hard bound that an
	 * attacker cannot sidestep by changing address.
	 *
	 * @return bool True when the caller is over budget.
	 */
	public static function register_rate_limited(): bool {
		$ip = self::client_ip();
		/** Allow a deployment to tune or disable the throttle. */
		$max = (int) apply_filters( 'emcp_tools_oauth_register_max', self::REGISTER_MAX );
		if ( $max <= 0 ) {
			return false;
		}
		$key = 'emcp_dcr_' . md5( $ip );
		$n   = (int) get_transient( $key );
		if ( $n >= $max ) {
			return true;
		}
		set_transient( $key, $n + 1, self::REGISTER_WINDOW );
		return false;
	}

	/**
	 * Hard ceiling on clients that registered but never completed authorization.
	 *
	 * This is the control that actually bounds table growth: unlike a per-IP
	 * throttle it cannot be bypassed by rotating source address or forwarded
	 * headers. Authorized clients are never counted, so real installs are
	 * unaffected, and gc() prunes stale never-authorized rows.
	 *
	 * @return bool True when no further anonymous registrations are accepted.
	 */
	public static function unauthorized_at_capacity(): bool {
		$max = (int) apply_filters( 'emcp_tools_oauth_max_unauthorized_clients', self::MAX_UNAUTHORIZED );
		if ( $max <= 0 ) {
			return false;
		}
		global $wpdb;
		$table = EMCP_Tools_OAuth_Store::clients_table();
		$n     = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE authorized_at = 0', $table ) ); // phpcs:ignore WordPress.DB
		return $n >= $max;
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
		// Registration is public by design (RFC 7591), so bound what one caller can
		// persist BEFORE any database work: unbounded names/URI arrays let an
		// unauthenticated caller grow the table faster than cleanup reclaims it.
		if ( count( $uris ) > self::MAX_REDIRECT_URIS ) {
			return new WP_Error( 'invalid_redirect_uri', 'Too many redirect_uris.' );
		}

		$clean = array();
		foreach ( $uris as $uri ) {
			if ( ! is_string( $uri ) || strlen( $uri ) > self::MAX_REDIRECT_URI_LEN || ! self::is_allowed_redirect_uri( $uri ) ) {
				return new WP_Error( 'invalid_redirect_uri', 'Each redirect_uri must be an absolute https URL (or an http loopback address).' );
			}
			$clean[] = $uri;
		}

		$name = ( isset( $body['client_name'] ) && is_string( $body['client_name'] ) && '' !== trim( $body['client_name'] ) )
			? trim( $body['client_name'] )
			: 'MCP Client';
		if ( strlen( $name ) > self::MAX_CLIENT_NAME ) {
			return new WP_Error( 'invalid_client_metadata', 'client_name is too long.' );
		}
		$clean = array_values( array_unique( $clean ) );
		if ( strlen( $name ) + strlen( (string) wp_json_encode( $clean ) ) > self::MAX_METADATA_BYTES ) {
			return new WP_Error( 'invalid_client_metadata', 'Client metadata is too large.' );
		}

		return array(
			'client_name'   => $name,
			'redirect_uris' => $clean,
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
		$p = wp_parse_url( $uri );
		if ( ! is_array( $p ) || empty( $p['scheme'] ) || isset( $p['fragment'] ) ) {
			return false;
		}
		$scheme = strtolower( (string) $p['scheme'] );
		// Must be a syntactically valid URI scheme (RFC 3986).
		if ( ! preg_match( '/^[a-z][a-z0-9+.\-]*$/', $scheme ) ) {
			return false;
		}
		// Never accept a browser-executable / local-file / opaque scheme.
		if ( in_array( $scheme, self::DENIED_SCHEMES, true ) ) {
			return false;
		}
		// Plaintext http is allowed only for loopback (RFC 8252 §7.3).
		if ( 'http' === $scheme ) {
			$host = isset( $p['host'] ) ? strtolower( trim( $p['host'], '[]' ) ) : '';
			return in_array( $host, array( '127.0.0.1', '::1', 'localhost' ), true );
		}
		// https must be a real absolute URL. "https:relative" parses as scheme
		// https with no host and would otherwise be accepted.
		if ( 'https' === $scheme ) {
			return ! empty( $p['host'] );
		}
		/**
		 * Strict allowlist of redirect schemes.
		 *
		 * Return a non-empty array to accept ONLY those schemes. Left empty by
		 * default because native MCP clients register single-word private-use
		 * schemes (vscode://, cursor://, …) that cannot be enumerated ahead of
		 * time; those are accepted unless reserved. Deployments that know their
		 * client set should pin it here.
		 *
		 * @param string[] $schemes Allowed schemes, lowercase.
		 */
		$allowlist = array_map( 'strtolower', (array) apply_filters( 'emcp_tools_oauth_redirect_scheme_allowlist', array() ) );
		if ( $allowlist ) {
			return in_array( $scheme, $allowlist, true );
		}
		// Registered IANA transport / messaging schemes are never app callbacks.
		if ( in_array( $scheme, self::RESERVED_SCHEMES, true ) ) {
			return false;
		}
		// Remaining private-use / custom app schemes (RFC 8252 §7.1).
		return true;
	}
}

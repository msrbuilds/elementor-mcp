<?php
/**
 * The `/authorize` endpoint — validates the authorization request, gates it
 * behind a WordPress login + administrator consent, and (on approval) issues a
 * single-use, PKCE-bound authorization code before redirecting back to the
 * client.
 *
 * Client/redirect-URI validation happens before anything is echoed or
 * redirected, so a bad client can never be used as an open redirect. All other
 * errors are returned to the (validated) redirect URI per RFC 6749 §4.1.2.1.
 *
 * @package EMCP_Tools
 * @since   3.4.1
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Authorization endpoint + consent screen.
 *
 * @since 3.4.1
 */
class EMCP_Tools_OAuth_Authorize {

	const NONCE_ACTION = 'emcp_oauth_consent';

	/**
	 * The browser-facing authorize path. This is served as a normal front-end
	 * request (NOT a REST route) so WordPress cookie auth applies — a REST
	 * endpoint would require a nonce the client's browser navigation can't
	 * provide, and cookie sessions would never be recognized.
	 */
	const PATH = '/emcp-oauth/authorize';

	/**
	 * Wire the root-level request interception for the authorize endpoint.
	 */
	public static function init(): void {
		add_action( 'parse_request', array( __CLASS__, 'maybe_serve' ), 0 );
	}

	/**
	 * The absolute authorize endpoint URL (advertised in the AS metadata).
	 *
	 * @return string
	 */
	public static function endpoint_url(): string {
		return home_url( self::PATH );
	}

	/**
	 * Serve the authorize endpoint when the request path matches; no-op
	 * otherwise. Dispatches GET (render consent) vs POST (record decision).
	 *
	 * @param WP $wp Current environment (unused).
	 */
	public static function maybe_serve( $wp = null ): void {
		$uri  = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( '/' !== $path ) {
			$path = rtrim( $path, '/' );
		}
		if ( self::PATH !== $path ) {
			return;
		}
		if ( ! EMCP_Tools_OAuth_Server::is_enabled() ) {
			self::error_page( __( 'OAuth sign-in is not enabled on this site.', 'emcp-tools' ) );
		}

		if ( 'POST' === strtoupper( (string) ( $_SERVER['REQUEST_METHOD'] ?? 'GET' ) ) ) {
			self::handle_post();
		} else {
			self::handle_get();
		}
	}

	/**
	 * Read request params from a superglobal (unslashed, string values only).
	 * Values are validated / escaped downstream.
	 *
	 * @param array $src $_GET or $_POST.
	 * @return array<string,string>
	 */
	private static function request_params( array $src ): array {
		$out = array();
		foreach ( $src as $k => $v ) {
			if ( is_string( $v ) ) {
				$out[ (string) $k ] = (string) wp_unslash( $v );
			}
		}
		return $out;
	}

	/**
	 * The capability required to approve a connection (filterable).
	 *
	 * @return string
	 */
	public static function required_cap(): string {
		return (string) apply_filters( 'emcp_tools_oauth_authorize_cap', 'manage_options' );
	}

	// ---------------------------------------------------------------------
	// GET — validate + login-gate + render consent
	// ---------------------------------------------------------------------

	private static function handle_get(): void {
		$params       = self::request_params( $_GET ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- public authorization endpoint; params validated below, no state mutation on GET.
		$client_id    = (string) ( $params['client_id'] ?? '' );
		$redirect_uri = (string) ( $params['redirect_uri'] ?? '' );
		$client       = self::lookup_client( $client_id );

		// Client + redirect must be valid before we trust redirect_uri as a target.
		if ( '' === $client_id || null === $client || '' === $redirect_uri || ! self::redirect_registered( $client, $redirect_uri ) ) {
			self::error_page( __( 'Invalid client or redirect URI for this connection request.', 'emcp-tools' ) );
		}

		$state = (string) ( $params['state'] ?? '' );
		$valid = self::validate_params( $params, $client );
		if ( is_wp_error( $valid ) ) {
			self::redirect_error( $redirect_uri, $valid->get_error_code(), $state );
		}

		if ( ! is_user_logged_in() ) {
			wp_redirect( wp_login_url( self::current_url() ) );
			exit;
		}
		if ( ! current_user_can( self::required_cap() ) ) {
			self::error_page( __( 'Only administrators can authorize an MCP connection on this site.', 'emcp-tools' ) );
		}

		echo self::render_consent( array_merge( $valid, array( 'client_name' => $client['client_name'] ) ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- render_consent escapes.
		exit;
	}

	// ---------------------------------------------------------------------
	// POST — record the approve/deny decision
	// ---------------------------------------------------------------------

	private static function handle_post(): void {
		if ( ! is_user_logged_in() || ! current_user_can( self::required_cap() ) ) {
			self::error_page( __( 'You are not allowed to authorize this connection.', 'emcp-tools' ) );
		}

		$p     = self::request_params( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce verified immediately below.
		$nonce = (string) ( $p['_emcp_oauth_nonce'] ?? '' );
		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			self::error_page( __( 'Security check failed. Please start the connection again.', 'emcp-tools' ) );
		}

		$client_id    = (string) ( $p['client_id'] ?? '' );
		$redirect_uri = (string) ( $p['redirect_uri'] ?? '' );
		$client       = self::lookup_client( $client_id );
		if ( null === $client || ! self::redirect_registered( $client, $redirect_uri ) ) {
			self::error_page( __( 'Invalid client or redirect URI for this connection request.', 'emcp-tools' ) );
		}

		$state = (string) ( $p['state'] ?? '' );
		if ( 'approve' !== ( $p['action'] ?? '' ) ) {
			self::redirect_error( $redirect_uri, 'access_denied', $state );
		}

		$challenge = (string) ( $p['code_challenge'] ?? '' );
		if ( '' === $challenge ) {
			self::redirect_error( $redirect_uri, 'invalid_request', $state );
		}

		$code = EMCP_Tools_OAuth_Store::issue_code(
			array(
				'client_id'      => $client['client_id'],
				'user_id'        => get_current_user_id(),
				'redirect_uri'   => $redirect_uri,
				'code_challenge' => $challenge,
				'scopes'         => (string) ( $p['scope'] ?? EMCP_Tools_OAuth_Server::SCOPE ),
			)
		);

		wp_redirect( self::build_redirect( $redirect_uri, array( 'code' => $code, 'state' => $state ) ) );
		exit;
	}

	// ---------------------------------------------------------------------
	// Pure helpers (unit-tested)
	// ---------------------------------------------------------------------

	/**
	 * Validate the non-client authorization parameters.
	 *
	 * @param array      $params Request params.
	 * @param array|null $client The resolved client (null if unknown).
	 * @return array|WP_Error Normalized params, or an error whose code is a valid
	 *                        OAuth error slug (safe to return to redirect_uri).
	 */
	public static function validate_params( array $params, ?array $client ) {
		if ( 'code' !== ( $params['response_type'] ?? '' ) ) {
			return new WP_Error( 'unsupported_response_type', 'Only response_type=code is supported.' );
		}
		if ( null === $client ) {
			return new WP_Error( 'invalid_request', 'Unknown client.' );
		}
		if ( 'S256' !== ( $params['code_challenge_method'] ?? '' ) || '' === (string) ( $params['code_challenge'] ?? '' ) ) {
			return new WP_Error( 'invalid_request', 'PKCE with S256 is required.' );
		}
		return array(
			'client_id'      => (string) ( $params['client_id'] ?? '' ),
			'redirect_uri'   => (string) ( $params['redirect_uri'] ?? '' ),
			'code_challenge' => (string) $params['code_challenge'],
			'state'          => (string) ( $params['state'] ?? '' ),
			'scope'          => (string) ( $params['scope'] ?? EMCP_Tools_OAuth_Server::SCOPE ),
		);
	}

	/**
	 * Whether a redirect URI is registered for the client.
	 *
	 * @param array  $client       Client with a `redirect_uris` array.
	 * @param string $redirect_uri Candidate.
	 * @return bool
	 */
	public static function redirect_registered( array $client, string $redirect_uri ): bool {
		foreach ( (array) ( $client['redirect_uris'] ?? array() ) as $registered ) {
			if ( is_string( $registered ) && EMCP_Tools_OAuth_Util::redirect_uri_matches( $registered, $redirect_uri ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Append query args to a redirect URI (handles existing query strings).
	 *
	 * @param string $redirect_uri Base URI.
	 * @param array  $args         Args (empty values are dropped).
	 * @return string
	 */
	public static function build_redirect( string $redirect_uri, array $args ): string {
		$pairs = array();
		foreach ( $args as $k => $v ) {
			if ( '' !== (string) $v ) {
				$pairs[] = rawurlencode( (string) $k ) . '=' . rawurlencode( (string) $v );
			}
		}
		if ( empty( $pairs ) ) {
			return $redirect_uri;
		}
		$sep = ( false === strpos( $redirect_uri, '?' ) ) ? '?' : '&';
		return $redirect_uri . $sep . implode( '&', $pairs );
	}

	/**
	 * Render the consent screen HTML.
	 *
	 * @param array $ctx { client_id, client_name, redirect_uri, code_challenge, state, scope }.
	 * @return string
	 */
	public static function render_consent( array $ctx ): string {
		$user       = wp_get_current_user();
		$site       = get_bloginfo( 'name' );
		$client     = (string) ( $ctx['client_name'] ?? 'An MCP client' );
		$nonce      = wp_create_nonce( self::NONCE_ACTION );
		$deny_label = __( 'Deny', 'emcp-tools' );

		$hidden = '';
		foreach ( array( 'client_id', 'redirect_uri', 'code_challenge', 'state', 'scope' ) as $k ) {
			$hidden .= '<input type="hidden" name="' . esc_attr( $k ) . '" value="' . esc_attr( (string) ( $ctx[ $k ] ?? '' ) ) . '" />';
		}
		$hidden .= '<input type="hidden" name="_emcp_oauth_nonce" value="' . esc_attr( $nonce ) . '" />';

		$action = esc_url( self::endpoint_url() );

		return '<!doctype html><html><head><meta charset="utf-8" />'
			. '<meta name="viewport" content="width=device-width, initial-scale=1" />'
			. '<meta name="robots" content="noindex" />'
			. '<title>' . esc_html__( 'Authorize connection', 'emcp-tools' ) . '</title>'
			. '<style>'
			. 'body{margin:0;background:#f5f6fa;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Helvetica,Arial,sans-serif;color:#0a0a14}'
			. '.wrap{max-width:460px;margin:8vh auto;padding:0 20px}'
			. '.card{background:#fff;border:1px solid #0a0a141a;border-radius:16px;padding:32px}'
			. '.eyebrow{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#4338ca;font-weight:700;margin-bottom:14px}'
			. 'h1{font-size:22px;line-height:1.25;margin:0 0 14px}'
			. 'p{font-size:15px;line-height:1.6;color:#3a3b52;margin:0 0 14px}'
			. '.who{background:#f5f6fa;border:1px solid #0a0a1410;border-radius:10px;padding:12px 14px;font-size:14px;margin:0 0 20px}'
			. '.who b{color:#0a0a14}'
			. '.warn{font-size:13px;color:#71748b;margin:0 0 22px}'
			. '.row{display:flex;gap:10px}'
			. 'button{flex:1;padding:12px 16px;border-radius:10px;font-size:15px;font-weight:600;cursor:pointer;border:1px solid transparent}'
			. '.approve{background:#4f46e5;color:#fff}'
			. '.deny{background:#fff;border-color:#0a0a1428;color:#3a3b52}'
			. '</style></head><body><div class="wrap"><div class="card">'
			. '<div class="eyebrow">' . esc_html__( 'Authorize MCP connection', 'emcp-tools' ) . '</div>'
			. '<h1>' . sprintf(
				/* translators: 1: client name, 2: site name */
				esc_html__( '%1$s wants to connect to %2$s', 'emcp-tools' ),
				'<b>' . esc_html( $client ) . '</b>',
				esc_html( $site )
			) . '</h1>'
			. '<p>' . esc_html__( 'It will connect as your WordPress account and can do anything you can through the MCP tools you have enabled.', 'emcp-tools' ) . '</p>'
			. '<div class="who">' . sprintf(
				/* translators: 1: display name, 2: user login */
				esc_html__( 'Signed in as %1$s (%2$s)', 'emcp-tools' ),
				'<b>' . esc_html( $user->display_name ) . '</b>',
				esc_html( $user->user_login )
			) . '</div>'
			. '<p class="warn">' . esc_html__( 'Only approve connections you started yourself. You can revoke access anytime from EMCP Tools → Connection.', 'emcp-tools' ) . '</p>'
			. '<form method="post" action="' . $action . '">' . $hidden
			. '<div class="row">'
			. '<button class="deny" type="submit" name="action" value="deny">' . esc_html( $deny_label ) . '</button>'
			. '<button class="approve" type="submit" name="action" value="approve">' . esc_html__( 'Approve', 'emcp-tools' ) . '</button>'
			. '</div></form></div></div></body></html>';
	}

	// ---------------------------------------------------------------------
	// Internal
	// ---------------------------------------------------------------------

	/**
	 * @param string $client_id Client id.
	 * @return array|null
	 */
	private static function lookup_client( string $client_id ): ?array {
		return '' === $client_id ? null : EMCP_Tools_OAuth_Store::get_client( $client_id );
	}

	/**
	 * Redirect back to the client with an OAuth error, then exit.
	 *
	 * @param string $redirect_uri Validated redirect URI.
	 * @param string $error        OAuth error code.
	 * @param string $state        Opaque state to echo back.
	 */
	private static function redirect_error( string $redirect_uri, string $error, string $state ): void {
		wp_redirect( self::build_redirect( $redirect_uri, array( 'error' => $error, 'state' => $state ) ) );
		exit;
	}

	/**
	 * Output a minimal HTML error page (used when there is no safe redirect
	 * target), then exit.
	 *
	 * @param string $message Message.
	 */
	private static function error_page( string $message ): void {
		if ( ! headers_sent() ) {
			status_header( 400 );
			header( 'Content-Type: text/html; charset=utf-8' );
		}
		echo '<!doctype html><meta charset="utf-8" /><title>' . esc_html__( 'Connection error', 'emcp-tools' ) . '</title>'
			. '<div style="max-width:460px;margin:12vh auto;font-family:sans-serif;text-align:center;color:#0a0a14">'
			. '<h1 style="font-size:20px">' . esc_html__( 'Connection error', 'emcp-tools' ) . '</h1>'
			. '<p style="color:#3a3b52">' . esc_html( $message ) . '</p></div>';
		exit;
	}

	/**
	 * The absolute URL of the current request (for the login return).
	 *
	 * @return string
	 */
	private static function current_url(): string {
		$scheme = ( function_exists( 'is_ssl' ) && is_ssl() ) ? 'https' : 'http';
		$host   = isset( $_SERVER['HTTP_HOST'] ) ? (string) wp_unslash( $_SERVER['HTTP_HOST'] ) : '';
		$uri    = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		return esc_url_raw( $scheme . '://' . $host . $uri );
	}
}

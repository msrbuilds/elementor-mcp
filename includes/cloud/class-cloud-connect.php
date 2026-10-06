<?php
/**
 * EMCP Cloud OAuth client: DCR -> authorize -> callback -> token -> refresh -> revoke.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Cloud_Connect {
	const ACTION_CONNECT    = 'emcp_tools_cloud_connect';
	const ACTION_CALLBACK   = 'emcp_tools_cloud_callback';
	const ACTION_DISCONNECT = 'emcp_tools_cloud_disconnect';
	const ACTION_REISSUE    = 'emcp_tools_cloud_gateway_reissue';
	const ACTION_GATEWAY_OFF = 'emcp_tools_cloud_gateway_off';
	const ACTION_SEPARATE = 'emcp_tools_cloud_separate';
	const PENDING_TRANSIENT = 'emcp_tools_cloud_pending';
	// Treat the access token as expired this many seconds early (matches the
	// client's own leeway) when deciding whether a concurrent request already
	// refreshed it.
	const REFRESH_LEEWAY = 60;
	// Seconds a waiting request blocks on the refresh mutex before giving up and
	// proceeding best-effort. Kept short so an admin page never stalls.
	const REFRESH_LOCK_WAIT = 5;

	/**
	 * Register the admin-post handlers (called from the module's register()).
	 *
	 * @return void
	 */
	public static function init(): void {
		add_action( 'admin_post_' . self::ACTION_SEPARATE, array( __CLASS__, 'handle_separate' ) );
		add_action( 'admin_post_' . self::ACTION_CONNECT, array( __CLASS__, 'handle_connect' ) );
		add_action( 'admin_post_' . self::ACTION_CALLBACK, array( __CLASS__, 'handle_callback' ) );
		add_action( 'admin_post_' . self::ACTION_DISCONNECT, array( __CLASS__, 'handle_disconnect' ) );
		add_action( 'admin_post_' . self::ACTION_REISSUE, array( __CLASS__, 'handle_gateway_reissue' ) );
		add_action( 'admin_post_' . self::ACTION_GATEWAY_OFF, array( __CLASS__, 'handle_gateway_off' ) );
	}

	/**
	 * @return string The admin-post callback the provider redirects back to.
	 */
	public static function redirect_uri(): string {
		return admin_url( 'admin-post.php?action=' . self::ACTION_CALLBACK );
	}

	/**
	 * @return string Origin header value for token/refresh/revoke (the website origin).
	 */
	private static function origin(): string {
		return EMCP_Tools_Cloud::base_url();
	}

	/**
	 * Dynamic Client Registration: create a public PKCE client.
	 *
	 * @return array{client_id:string,registration_proof:string}|\WP_Error The registration, or an error.
	 */
	public static function register_client() {
		$res = EMCP_Tools_Cloud_Http::post_json(
			EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/register',
			array(
				'redirect_uris'              => array( self::redirect_uri() ),
				'token_endpoint_auth_method' => 'none',
				'client_name'                => (string) get_bloginfo( 'name' ),
				'scope'                      => EMCP_Tools_Cloud::SCOPES,
			)
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$id    = (string) ( $res['json']['client_id'] ?? '' );
		$proof = (string) ( $res['json']['emcp_site_registration_proof'] ?? '' );
		$code = (int) $res['code'];
		if ( ( 200 !== $code && 201 !== $code ) || '' === $id ) {
			return new \WP_Error( 'dcr_failed', __( 'Could not register this site with EMCP Cloud.', 'emcp-tools' ) );
		}
		return array(
			'client_id'         => $id,
			'registration_proof' => $proof,
		);
	}

	/**
	 * Build the browser authorize URL (PKCE + state carrying site identity).
	 *
	 * @param string $client_id Registered client id.
	 * @param string $verifier  PKCE verifier (challenge derived here).
	 * @param string $csrf      Opaque CSRF token embedded in state.
	 * @param string $registration_proof Optional server-issued DCR ownership proof.
	 * @return string
	 */
	public static function authorize_url( string $client_id, string $verifier, string $csrf, string $registration_proof = '', string $expected_workspace = '' ): string {
		$state_payload = array(
			'site_uuid' => EMCP_Tools_Cloud::site_uuid(),
			'name'      => (string) get_bloginfo( 'name' ),
			// This is the only side that knows about an administrator's Server URL
			// override and WordPress subdirectory, so send the callback base directly.
			'base'      => class_exists( 'EMCP_Tools_Site_Context' )
				? EMCP_Tools_Site_Context::public_base_url()
				: rtrim( (string) home_url(), '/' ),
			'csrf'      => $csrf,
		);
		if ( '' !== $registration_proof ) {
			$state_payload['registration_proof'] = $registration_proof;
		}
		if ( '' !== $expected_workspace ) {
			$state_payload['expected_workspace'] = $expected_workspace;
		}
		$state = EMCP_Tools_OAuth_Util::base64url_encode( (string) wp_json_encode( $state_payload ) );
		$params = array(
			'response_type'         => 'code',
			'client_id'             => $client_id,
			'redirect_uri'          => self::redirect_uri(),
			'scope'                 => EMCP_Tools_Cloud::SCOPES,
			'state'                 => $state,
			'code_challenge'        => EMCP_Tools_OAuth_Util::code_challenge_s256( $verifier ),
			'code_challenge_method' => 'S256',
		);
		return EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/authorize?' . http_build_query( $params );
	}

	/**
	 * Exchange an authorization code for tokens (form-encoded + Origin). On
	 * success, saves the connection bundle and returns it.
	 *
	 * @param string $code      Authorization code.
	 * @param string $verifier  PKCE verifier.
	 * @param string $client_id Client id.
	 * @return array|\WP_Error
	 */
	public static function exchange_code( string $code, string $verifier, string $client_id, string $expected_workspace = '' ) {
		$res = EMCP_Tools_Cloud_Http::post_form(
			EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/token',
			array(
				'grant_type'    => 'authorization_code',
				'code'          => $code,
				'redirect_uri'  => self::redirect_uri(),
				'client_id'     => $client_id,
				'code_verifier' => $verifier,
				'emcp_expected_workspace' => $expected_workspace,
			),
			array( 'Origin' => self::origin() )
		);
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		$j = $res['json'];
		if ( 200 !== (int) $res['code'] || empty( $j['access_token'] ) ) {
			return new \WP_Error( 'token_failed', __( 'EMCP Cloud rejected the connection.', 'emcp-tools' ) );
		}
		$bundle = array(
			'access_token'      => (string) $j['access_token'],
			'refresh_token'     => (string) ( $j['refresh_token'] ?? '' ),
			'access_expires_at' => time() + (int) ( $j['expires_in'] ?? 3600 ),
			'client_id'         => $client_id,
			'connected_at'      => time(),
		);
		$email = self::fetch_account_email( $bundle['access_token'] );
		if ( '' !== $email ) {
			$bundle['account_email'] = $email;
		}
		EMCP_Tools_Cloud::save_connection( $bundle );
		return $bundle;
	}

	/**
	 * The account email from the Cloud's userinfo endpoint. Best effort: a
	 * failure never blocks connecting, and the screen then shows no email.
	 *
	 * @param string $access_token A fresh access token.
	 * @return string Sanitized email, or ''.
	 */
	public static function fetch_account_email( string $access_token ): string {
		$res = EMCP_Tools_Cloud_Http::request(
			'GET',
			EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/userinfo',
			array( 'headers' => array( 'Authorization' => 'Bearer ' . $access_token ) )
		);
		if ( is_wp_error( $res ) || 200 !== (int) $res['code'] ) {
			return '';
		}
		$email = (string) ( $res['json']['email'] ?? '' );
		return is_email( $email ) ? (string) sanitize_email( $email ) : '';
	}

	/**
	 * Refresh the stored access token.
	 *
	 * The Cloud provider (Better Auth) ROTATES the refresh token on every
	 * refresh — each success mints a new refresh token and invalidates the old
	 * one. Two concurrent WordPress requests (a second admin tab, a heartbeat,
	 * an MCP call) that both see the access token expired would each POST the
	 * same refresh token; the first wins and rotates it, the second is rejected
	 * with invalid_grant. Naively that second request would flip the connection
	 * to "unhealthy" and overwrite the freshly-rotated token with the dead one,
	 * which is exactly what surfaces as a spurious "Reconnect needed".
	 *
	 * Guards, in order: (1) a best-effort DB mutex serialises refreshes; (2) a
	 * double-check re-reads the bundle after the lock and bails if another
	 * request already refreshed; (3) an auth rejection that coincides with a
	 * concurrent rotation is treated as success and never clobbers the good
	 * bundle; (4) network/5xx blips are transient and do NOT mark unhealthy.
	 *
	 * @return bool
	 */
	public static function refresh(): bool {
		$c = EMCP_Tools_Cloud::get_connection();
		if ( empty( $c['refresh_token'] ) || empty( $c['client_id'] ) ) {
			return false;
		}

		$lock_key = 'emcp_cloud_refresh_' . substr( md5( (string) $c['client_id'] ), 0, 24 );
		$locked   = self::db_lock( $lock_key, self::REFRESH_LOCK_WAIT );

		// Double-checked locking: a request we waited behind may have already
		// refreshed. Re-read and short-circuit when the token is fresh again.
		$c = EMCP_Tools_Cloud::get_connection();
		if ( empty( $c['refresh_token'] ) || empty( $c['client_id'] ) ) {
			self::db_unlock( $lock_key, $locked );
			return false;
		}
		if ( self::access_token_fresh( $c ) ) {
			self::db_unlock( $lock_key, $locked );
			return true;
		}

		// Serialization failed: another request holds the refresh lock and is
		// still in-flight past our wait. Do NOT present our refresh token
		// concurrently — the loser reuses a token the provider (Better Auth) has
		// just rotated out, and reuse of a rotated refresh token trips its theft
		// detection, which invalidates the ENTIRE token family and hard-forces a
		// "Reconnect needed". Bail transiently instead: the in-flight winner will
		// refresh, and the next request short-circuits on the fresh token.
		// GET_LOCK auto-frees if the winner's connection dies, so this can't wedge.
		if ( ! $locked ) {
			return false;
		}

		$used_rt = (string) $c['refresh_token'];
		if ( (int) ( $c['refresh_retry_at'] ?? 0 ) > time() ) {
			self::db_unlock( $lock_key, $locked );
			return false;
		}
		$res     = EMCP_Tools_Cloud_Http::post_form(
			EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/token',
			array(
				'grant_type'    => 'refresh_token',
				'refresh_token' => $used_rt,
				'client_id'     => (string) $c['client_id'],
			),
			array( 'Origin' => self::origin() )
		);

		// Transient failure (no response or a server-side 5xx): leave the
		// connection untouched so the next request retries. Marking it unhealthy
		// on a blip is a false "Reconnect needed".
		if ( is_wp_error( $res ) || in_array( (int) $res['code'], array( 408, 429 ), true ) || (int) $res['code'] >= 500 ) {
			$fresh = EMCP_Tools_Cloud::get_connection();
			// Do not overwrite a reconnect or a concurrent successful rotation.
			if ( ( $fresh['refresh_token'] ?? '' ) === $used_rt && ( $fresh['client_id'] ?? '' ) === $c['client_id'] ) {
				$retry = is_wp_error( $res ) ? '' : trim( (string) ( $res['retry_after'] ?? '' ) );
				$is_date = preg_match( '/^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} GMT$/', $retry );
				$delay = ctype_digit( $retry ) ? (float) $retry : ( $is_date ? ( ( strtotime( $retry ) ?: 0 ) - time() ) : 0 );
				$fresh['refresh_retry_at'] = time() + ( $delay > 0 ? (int) min( 3600, max( 1, $delay ) ) : 30 );
				EMCP_Tools_Cloud::save_connection( $fresh );
			}
			self::db_unlock( $lock_key, $locked );
			return false;
		}

		if ( 200 !== (int) $res['code'] || empty( $res['json']['access_token'] ) ) {
			// Auth rejection. If a concurrent request already rotated the token
			// (stored RT changed) or the access token is fresh again, this is
			// just the loser of a race — succeed without touching the bundle.
			$fresh = EMCP_Tools_Cloud::get_connection();
			$won   = ( ! empty( $fresh['refresh_token'] ) && (string) $fresh['refresh_token'] !== $used_rt )
				|| self::access_token_fresh( $fresh );
			if ( $won ) {
				self::db_unlock( $lock_key, $locked );
				return true;
			}
			$fresh['unhealthy'] = true;
			EMCP_Tools_Cloud::save_connection( $fresh );
			self::db_unlock( $lock_key, $locked );
			return false;
		}

		// Success. Merge onto the freshest stored bundle so we never drop a
		// concurrent write of an unrelated field.
		$j                         = $res['json'];
		$save                      = EMCP_Tools_Cloud::get_connection();
		$save['access_token']      = (string) $j['access_token'];
		$save['refresh_token']     = (string) ( $j['refresh_token'] ?? ( $save['refresh_token'] ?? $used_rt ) );
		$save['access_expires_at'] = time() + (int) ( $j['expires_in'] ?? 3600 );
		unset( $save['unhealthy'], $save['refresh_retry_at'] );
		EMCP_Tools_Cloud::save_connection( $save );
		self::db_unlock( $lock_key, $locked );
		return true;
	}

	/**
	 * @param array $c Connection bundle.
	 * @return bool Whether the bundle's access token is still valid beyond the leeway.
	 */
	private static function access_token_fresh( array $c ): bool {
		return ! empty( $c['access_token'] )
			&& (int) ( $c['access_expires_at'] ?? 0 ) - self::REFRESH_LEEWAY > time();
	}

	/**
	 * Best-effort cross-request mutex via MySQL GET_LOCK (per-connection; auto
	 * released if the request dies). Degrades to a no-op where $wpdb is absent
	 * (unit tests) — the double-check + anti-clobber guards still hold.
	 *
	 * @param string $key     Lock name (<= 64 chars for MySQL).
	 * @param int    $timeout Seconds to wait for the lock.
	 * @return bool Whether the lock was actually acquired.
	 */
	private static function db_lock( string $key, int $timeout ): bool {
		global $wpdb;
		// No DB to serialize on (unit tests / single-process CLI): there is no
		// concurrency to guard, so treat the lock as acquired and let the refresh
		// proceed. In real WordPress $wpdb is always present, so this branch never
		// weakens the cross-request mutex — a genuine GET_LOCK timeout still
		// returns false and makes refresh() bail without reusing a rotated token.
		if ( ! is_object( $wpdb ) || ! method_exists( $wpdb, 'get_var' ) || ! method_exists( $wpdb, 'prepare' ) ) {
			return true;
		}
		return '1' === (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $key, $timeout ) );
	}

	/**
	 * @param string $key    Lock name.
	 * @param bool   $locked Whether db_lock() actually acquired it.
	 * @return void
	 */
	private static function db_unlock( string $key, bool $locked ): void {
		global $wpdb;
		if ( $locked && is_object( $wpdb ) && method_exists( $wpdb, 'query' ) && method_exists( $wpdb, 'prepare' ) ) {
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $key ) );
		}
	}

	/**
	 * Best-effort remote revoke of the refresh token.
	 *
	 * @return void
	 */
	public static function revoke_remote(): void {
		$c = EMCP_Tools_Cloud::get_connection();
		if ( empty( $c['refresh_token'] ) ) {
			return;
		}
		EMCP_Tools_Cloud_Http::post_form(
			EMCP_Tools_Cloud::base_url() . '/api/auth/oauth2/revoke',
			array(
				'token'     => (string) $c['refresh_token'],
				'client_id' => (string) ( $c['client_id'] ?? '' ),
			),
			array( 'Origin' => self::origin() )
		);
	}

	// ── Admin-post handlers ──────────────────────────────────────────────────

	private static function guard_cap(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'emcp-tools' ), '', array( 'response' => 403 ) );
		}
	}

	private static function back( string $flag ): void {
		wp_safe_redirect( admin_url( 'admin.php?page=emcp-tools-connection&' . $flag . '#emcp-conn-main' ) );
		exit;
	}

	/**
	 * Outbound: DCR, then redirect the browser to authorize. Nonce-protected.
	 *
	 * @return void
	 */
	public static function handle_connect(): void {
		self::guard_cap();
		check_admin_referer( self::ACTION_CONNECT );
		if ( EMCP_Tools_Cloud::identity_conflict() ) {
			self::back( 'cloud_error=site_identity_conflict' );
		}
		$registration = self::register_client();
		if ( is_wp_error( $registration ) ) {
			self::back( 'cloud_error=dcr' );
		}
		$client_id          = (string) $registration['client_id'];
		$registration_proof = (string) $registration['registration_proof'];
		$verifier           = EMCP_Tools_OAuth_Util::generate_code_verifier();
		$csrf               = EMCP_Tools_OAuth_Util::generate_token();
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- nonce already checked by check_admin_referer() above.
		$gateway_optin = isset( $_POST['emcp_gateway_optin'] );
		set_transient(
			self::PENDING_TRANSIENT,
			self::pending_record( $verifier, $csrf, $client_id, $registration_proof, $gateway_optin ),
			600
		);
		// The authorize URL is on the Cloud host, not this site. wp_safe_redirect()
		// blocks off-site hosts (falling back to wp-admin), so allow the Cloud host
		// for this one deliberate redirect to our own service.
		$cloud_host = wp_parse_url( EMCP_Tools_Cloud::base_url(), PHP_URL_HOST );
		if ( $cloud_host ) {
			add_filter(
				'allowed_redirect_hosts',
				static function ( $hosts ) use ( $cloud_host ) {
					$hosts[] = $cloud_host;
					return $hosts;
				}
			);
		}
		wp_safe_redirect( self::authorize_url( $client_id, $verifier, $csrf, $registration_proof ) );
		exit;
	}

	/**
	 * Inbound provider redirect: validate STATE (not a nonce), exchange the code.
	 *
	 * @return void
	 */
	public static function handle_callback(): void {
		self::guard_cap();
		if ( EMCP_Tools_Cloud::identity_conflict() ) {
			self::back( 'cloud_error=site_identity_conflict' );
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- provider redirect; validated by state below.
		$code     = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : '';
		$state_in = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended
		$pending = get_transient( self::PENDING_TRANSIENT );
		// CLI approval belongs to the initiating administrator and installation.
		// Keep a valid request intact when another admin follows the URL.
		if ( is_array( $pending ) && isset( $pending['onboarding_user'] ) &&
			( (int) $pending['onboarding_user'] !== get_current_user_id() ||
			(string) $pending['onboarding_site_uuid'] !== (string) get_option( EMCP_Tools_Cloud::OPTION_SITE_UUID, '' ) ||
			(int) $pending['onboarding_expires'] <= time() ) ) {
			self::back( 'cloud_error=state' );
		}
		if ( ! is_array( $pending ) || '' === $code ) {
			self::back( 'cloud_error=state' );
		}
		if ( isset( $pending['installation_base'] ) && $pending['installation_base'] !== EMCP_Tools_Cloud::installation_base() ) {
			self::back( 'cloud_error=site_identity_conflict' );
		}
		$decoded = json_decode( EMCP_Tools_OAuth_Util::base64url_decode( $state_in ), true );
		$csrf    = is_array( $decoded ) ? (string) ( $decoded['csrf'] ?? '' ) : '';
		if ( ! EMCP_Tools_OAuth_Util::secure_equals( (string) $pending['csrf'], $csrf ) ) {
			self::back( 'cloud_error=state' );
		}
		if ( isset( $pending['onboarding_workspace'] ) && ( $decoded['expected_workspace'] ?? '' ) !== $pending['onboarding_workspace'] ) {
			self::back( 'cloud_error=state' );
		}
		delete_transient( self::PENDING_TRANSIENT );
		$bundle = self::exchange_code( $code, (string) $pending['verifier'], (string) $pending['client_id'], (string) ( $pending['onboarding_workspace'] ?? '' ) );
		if ( ! is_wp_error( $bundle ) && ! isset( $pending['onboarding_user'] ) && class_exists( 'EMCP_Tools_Gateway_Credential' ) ) {
			// Best-effort: the Cloud connection has already succeeded above, so a
			// gateway provisioning failure here must never turn into a user-facing
			// error, it just leaves the gateway un-provisioned for this site.
			$action = self::gateway_action( $pending );
			if ( 'provision' === $action ) {
				EMCP_Tools_Gateway_Credential::provision( get_current_user_id() );
			} elseif ( 'deprovision' === $action ) {
				EMCP_Tools_Gateway_Credential::deprovision();
			}
		}
		self::back( is_wp_error( $bundle ) ? 'cloud_error=token' : 'cloud_connected=1' );
	}

	/**
	 * Build the pending-connect record stored between handle_connect() and
	 * handle_callback(). Captures whether the site already held a gateway
	 * credential so the callback can tell "reconnect of a gateway site" from
	 * "first connect without the gateway" (#148).
	 *
	 * @param string $verifier           PKCE verifier.
	 * @param string $csrf               State CSRF token.
	 * @param string $client_id          DCR client id.
	 * @param string $registration_proof DCR registration proof.
	 * @param bool   $gateway_optin      Whether the gateway box was ticked.
	 * @return array<string,mixed>
	 */
	public static function pending_record( string $verifier, string $csrf, string $client_id, string $registration_proof, bool $gateway_optin ): array {
		$was_provisioned = class_exists( 'EMCP_Tools_Gateway_Credential' )
			&& (bool) get_option( EMCP_Tools_Gateway_Credential::OPTION_FLAG, 0 );
		return array(
			'installation_base' => EMCP_Tools_Cloud::installation_base(),
			'verifier'        => $verifier,
			'csrf'            => $csrf,
			'client_id'       => $client_id,
			'gateway'         => $gateway_optin,
			'was_provisioned' => $was_provisioned,
		);
	}

	/**
	 * What the callback should do about the gateway credential.
	 *
	 * The connect form is the only way into handle_connect() and its box is
	 * always rendered (checked, and labelled "currently enabled" once a
	 * credential exists), so the box is the truth: ticked re-issues the
	 * credential, unticked on a previously provisioned site withdraws it so
	 * Cloud and the site agree instead of Cloud holding a dead token (#148).
	 *
	 * @param array $pending The pending-connect record.
	 * @return string 'provision' | 'deprovision' | 'none'
	 */
	public static function gateway_action( array $pending ): string {
		if ( ! empty( $pending['gateway'] ) ) {
			return 'provision';
		}
		if ( ! empty( $pending['was_provisioned'] ) ) {
			return 'deprovision';
		}
		return 'none';
	}

	/**
	 * Re-issue the gateway credential without a reconnect. Covers the cases
	 * where the local token died behind Cloud's back: revoked under Users >
	 * Authorized Apps, OAuth tables recreated, site restored or migrated.
	 *
	 * @param int $user_id The user the credential acts as.
	 * @return true|\WP_Error
	 */
	public static function reissue_gateway( int $user_id ) {
		if ( ! EMCP_Tools_Cloud::is_connected() ) {
			return new \WP_Error( 'not_connected', __( 'Connect this site to EMCP Cloud first.', 'emcp-tools' ) );
		}
		if ( ! class_exists( 'EMCP_Tools_Gateway_Credential' ) ) {
			return new \WP_Error( 'gateway_unavailable', __( 'The gateway credential helper is not loaded.', 'emcp-tools' ) );
		}
		if ( ! EMCP_Tools_Gateway_Credential::provision( $user_id ) ) {
			return new \WP_Error( 'provision_failed', __( 'EMCP Cloud did not accept the new gateway credential.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Admin-post handler for the "Re-issue gateway credential" button. Nonce-protected.
	 *
	 * @return void
	 */
	public static function handle_gateway_reissue(): void {
		self::guard_cap();
		check_admin_referer( self::ACTION_REISSUE );
		$result = self::reissue_gateway( get_current_user_id() );
		self::back( is_wp_error( $result ) ? 'cloud_gateway=' . $result->get_error_code() : 'cloud_gateway=reissued' );
	}

	/**
	 * Admin-post handler for switching gateway access off. Nonce-protected.
	 *
	 * @return void
	 */
	public static function handle_gateway_off(): void {
		self::guard_cap();
		check_admin_referer( self::ACTION_GATEWAY_OFF );
		if ( EMCP_Tools_Cloud::identity_conflict() ) {
			self::back( 'cloud_error=site_identity_conflict' );
		}
		if ( class_exists( 'EMCP_Tools_Gateway_Credential' ) ) {
			EMCP_Tools_Gateway_Credential::deprovision();
		}
		self::back( 'cloud_gateway=disabled' );
	}

	/**
	 * Disconnect: revoke remotely + clear local. Nonce-protected.
	 *
	 * @return void
	 */
	public static function handle_disconnect(): void {
		self::guard_cap();
		check_admin_referer( self::ACTION_DISCONNECT );
		if ( EMCP_Tools_Cloud::identity_conflict() ) {
			self::back( 'cloud_error=site_identity_conflict' );
		}
		if ( class_exists( 'EMCP_Tools_Gateway_Credential' ) ) {
			EMCP_Tools_Gateway_Credential::deprovision(); // Cloud delete needs the live connection → before clear_connection().
		}
		self::revoke_remote();
		EMCP_Tools_Cloud::clear_connection();
		self::back( 'cloud_disconnected=1' );
	}

	/**
	 * @return string Nonce'd connect button URL.
	 */
	public static function connect_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_CONNECT ), self::ACTION_CONNECT );
	}

	public static function handle_separate(): void {
		self::guard_cap();
		check_admin_referer( self::ACTION_SEPARATE );
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || '1' !== ( $_POST['confirm_separate'] ?? '' ) ) {
			wp_die( esc_html__( 'Confirm that this is a separate site before continuing.', 'emcp-tools' ), '', array( 'response' => 400 ) );
		}
		EMCP_Tools_Cloud::separate_identity();
		self::back( 'cloud_separated=1' );
	}

	/**
	 * @return string Nonce'd re-issue-gateway button URL.
	 */
	public static function reissue_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_REISSUE ), self::ACTION_REISSUE );
	}

	/**
	 * @return string Nonce'd switch-gateway-off URL.
	 */
	public static function disable_gateway_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_GATEWAY_OFF ), self::ACTION_GATEWAY_OFF );
	}

	/**
	 * @return string Nonce'd disconnect button URL.
	 */
	public static function disconnect_url(): string {
		return wp_nonce_url( admin_url( 'admin-post.php?action=' . self::ACTION_DISCONNECT ), self::ACTION_DISCONNECT );
	}
}

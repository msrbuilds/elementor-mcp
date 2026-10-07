<?php
/**
 * Stable, pre-registered "EMCP Gateway" OAuth client.
 *
 * Phase 1 of the hosted multi-site gateway: each site can self-issue a
 * revocable refresh token against its OWN OAuth server, bound to a single,
 * idempotently-provisioned client — so repeat provisioning never grows the
 * clients table with dead rows. Reuses the existing OAuth persistence layer
 * (EMCP_Tools_OAuth_Store); no second client registry or token table.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Gateway_Credential {
	const CLIENT_NAME  = 'EMCP Gateway';
	const SCOPE        = 'gateway'; // provenance/revocation label.
	const REFRESH_TTL  = 315360000; // 10y — effectively non-expiring; store computes expires_at = now+ttl (no 0-sentinel), and each gateway refresh rotates a fresh token resetting the clock. 0 would expire immediately (find_token filters expires_at > now).
	const OPTION_FLAG  = 'emcp_tools_gateway_provisioned';

	/** @return string[] Stable registration redirect URIs. */
	private static function registration_uris(): array {
		return array( EMCP_Tools_Cloud::base_url() . '/gateway/callback' );
	}

	/**
	 * The gateway client's id if it already exists, else '' — a non-creating
	 * lookup (unlike ensure_client(), never registers a new client). Used by
	 * the teardown paths so a disconnect/revoke can never re-provision.
	 *
	 * @return string
	 */
	private static function existing_client_id(): string {
		$uris = self::registration_uris();
		$c    = EMCP_Tools_OAuth_Store::find_client_by_registration( self::CLIENT_NAME, $uris );
		return ( $c && ! empty( $c['client_id'] ) ) ? (string) $c['client_id'] : '';
	}

	/** Whether a registered client id is the stable Gateway client. */
	public static function is_gateway_client( string $client_id ): bool {
		$gateway_id = self::existing_client_id();
		return '' !== $client_id && '' !== $gateway_id && hash_equals( $gateway_id, $client_id );
	}

	/**
	 * Ensure the stable "EMCP Gateway" OAuth client exists and return its
	 * client_id. Idempotent: reuses an existing registration (matched by
	 * name + redirect URIs) instead of minting a new client every call.
	 *
	 * @return string The gateway client's client_id ('' on failure).
	 */
	public static function ensure_client(): string {
		$id = self::existing_client_id();
		if ( '' !== $id ) {
			return $id;
		}

		$uris = self::registration_uris();
		if ( ! EMCP_Tools_OAuth_Store::acquire_client_registration_lock( self::CLIENT_NAME, $uris ) ) {
			return '';
		}

		try {
			// Another first-time provisioner may have inserted the stable client
			// while this request waited for the registration lock.
			$id = self::existing_client_id();
			if ( '' !== $id ) {
				return $id;
			}
			$client = EMCP_Tools_OAuth_Store::create_client( self::CLIENT_NAME, $uris, 0 );
			return (string) ( $client['client_id'] ?? '' );
		} finally {
			EMCP_Tools_OAuth_Store::release_client_registration_lock( self::CLIENT_NAME, $uris );
		}
	}

	/**
	 * Self-issue a gateway-scoped refresh token bound to $user_id.
	 * The plaintext refresh token is returned once — the caller must upload it and not persist it.
	 *
	 * @param int $user_id User the token acts as.
	 * @return array{client_id:string,refresh_token:string,token_id:int}
	 */
	public static function issue_for_user( int $user_id ): array {
		$client_id = self::ensure_client();
		if ( '' === $client_id ) {
			return array(
				'client_id'     => '',
				'refresh_token' => '',
				'token_id'      => 0,
			);
		}
		$tok       = EMCP_Tools_OAuth_Store::issue_token( 'refresh', $client_id, $user_id, self::SCOPE, self::REFRESH_TTL );
		return array(
			'client_id'     => $client_id,
			'refresh_token' => (string) ( $tok['token'] ?? '' ),
			'token_id'      => (int) ( $tok['id'] ?? 0 ),
		);
	}

	/**
	 * Issue a gateway credential for $user_id and upload it to Cloud. Best-effort:
	 * on upload failure, revokes the just-issued token so no orphan lingers, and does
	 * not set the provisioned marker.
	 *
	 * @param int $user_id User the gateway acts as.
	 * @return bool True when a credential is live in Cloud.
	 */
	public static function provision( int $user_id ): bool {
		$client_id = self::ensure_client();
		if ( '' === $client_id || ! EMCP_Tools_OAuth_Store::acquire_client_token_lock( $client_id ) ) {
			return false;
		}

		try {
			// Client deletion uses the same lock. Recheck after waiting so a
			// deleted registration can never receive a new live token.
			if ( null === EMCP_Tools_OAuth_Store::get_client( $client_id ) || ! self::is_gateway_client( $client_id ) ) {
				return false;
			}
			$tok = EMCP_Tools_OAuth_Store::issue_token( 'refresh', $client_id, $user_id, self::SCOPE, self::REFRESH_TTL );
			if ( empty( $tok['token'] ) || empty( $tok['id'] ) ) {
				return false;
			}
			$upload = EMCP_Tools_Cloud_Client::put_gateway_credential_result( $client_id, (string) $tok['token'] );
			if ( is_wp_error( $upload ) ) {
				$error_code = (string) $upload->get_error_code();
				// A received HTTP rejection or a request that was never attempted
				// cannot have committed. A transport failure may be a lost response
				// after Cloud committed, so retain both generations for a safe retry.
				if ( 'not_connected' === $error_code || 0 === strpos( $error_code, 'cloud_http_' ) ) {
					EMCP_Tools_OAuth_Store::revoke_token( (int) $tok['id'] );
				}
				return false;
			}
			if ( ! EMCP_Tools_OAuth_Store::revoke_client_tokens_except( $client_id, (int) $tok['id'] ) ) {
				EMCP_Tools_OAuth_Store::revoke_token( (int) $tok['id'] );
				EMCP_Tools_Cloud_Client::delete_gateway_credential();
				delete_option( self::OPTION_FLAG );
				return false;
			}
			update_option( self::OPTION_FLAG, 1, false );
			return true;
		} finally {
			EMCP_Tools_OAuth_Store::release_client_token_lock( $client_id );
		}
	}

	/**
	 * Full local + Cloud teardown of the gateway credential. The local revoke
	 * always runs (offline-proof kill switch); the Cloud delete is
	 * best-effort (it needs a live Cloud access token, which may already be
	 * gone by the time this runs). Idempotent — safe to call repeatedly.
	 *
	 * @return void
	 */
	public static function deprovision(): void {
		$uris = self::registration_uris();
		if ( ! EMCP_Tools_OAuth_Store::acquire_client_registration_lock( self::CLIENT_NAME, $uris ) ) {
			return;
		}
		try {
			$client_id = self::existing_client_id();
			if ( '' === $client_id ) {
				if ( class_exists( 'EMCP_Tools_Cloud_Client' ) ) {
					EMCP_Tools_Cloud_Client::delete_gateway_credential();
				}
				delete_option( self::OPTION_FLAG );
				return;
			}
			self::revoke_registered_client_with_registration_lock( $client_id, false );
		} finally {
			EMCP_Tools_OAuth_Store::release_client_registration_lock( self::CLIENT_NAME, $uris );
		}
	}

	/**
	 * Remove credentials from this database only, preserving the source Cloud binding.
	 *
	 * @return bool Whether the local credential is absent.
	 */
	public static function clear_local(): bool {
		$uris = self::registration_uris();
		if ( ! EMCP_Tools_OAuth_Store::acquire_client_registration_lock( self::CLIENT_NAME, $uris ) ) {
			return false;
		}
		try {
			$client_id = self::existing_client_id();
			if ( '' === $client_id ) {
				delete_option( self::OPTION_FLAG );
				return true;
			}
			if ( ! EMCP_Tools_OAuth_Store::acquire_client_token_lock( $client_id ) ) {
				return false;
			}
			try {
				if ( false === EMCP_Tools_OAuth_Store::revoke_client_locked( $client_id ) ) {
					return false;
				}
				delete_option( self::OPTION_FLAG );
				return true;
			} finally {
				EMCP_Tools_OAuth_Store::release_client_token_lock( $client_id );
			}
		} finally {
			EMCP_Tools_OAuth_Store::release_client_registration_lock( self::CLIENT_NAME, $uris );
		}
	}

	/**
	 * Revoke or delete the Gateway client while serializing the Cloud cleanup
	 * with provisioning and refresh rotation.
	 *
	 * @param string $client_id          Gateway client id captured before deletion.
	 * @param bool   $delete_registration Whether to remove the client row too.
	 * @return bool Whether the local mutation completed.
	 */
	public static function revoke_registered_client( string $client_id, bool $delete_registration ): bool {
		$uris = self::registration_uris();
		if ( ! EMCP_Tools_OAuth_Store::acquire_client_registration_lock( self::CLIENT_NAME, $uris ) ) {
			return false;
		}
		try {
			return self::revoke_registered_client_with_registration_lock( $client_id, $delete_registration );
		} finally {
			EMCP_Tools_OAuth_Store::release_client_registration_lock( self::CLIENT_NAME, $uris );
		}
	}

	/**
	 * Gateway teardown body while the registration identity lock is held.
	 *
	 * @param string $client_id          Gateway client id captured before deletion.
	 * @param bool   $delete_registration Whether to remove the client row too.
	 * @return bool Whether the local mutation completed.
	 */
	private static function revoke_registered_client_with_registration_lock( string $client_id, bool $delete_registration ): bool {
		if ( ! self::is_gateway_client( $client_id ) || ! EMCP_Tools_OAuth_Store::acquire_client_token_lock( $client_id ) ) {
			return false;
		}
		try {
			if ( $delete_registration ) {
				if ( ! EMCP_Tools_OAuth_Store::delete_client_locked( $client_id ) ) {
					return false;
				}
			} else {
				if ( false === EMCP_Tools_OAuth_Store::revoke_client_locked( $client_id ) ) {
					return false;
				}
			}
			if ( class_exists( 'EMCP_Tools_Cloud_Client' ) ) {
				EMCP_Tools_Cloud_Client::delete_gateway_credential();
			}
			delete_option( self::OPTION_FLAG );
			return true;
		} finally {
			EMCP_Tools_OAuth_Store::release_client_token_lock( $client_id );
		}
	}

	/**
	 * Back-compatible Gateway revoke hook. The full local and Cloud mutation is
	 * serialized here so callers cannot race a replacement provision.
	 *
	 * @param string $client_id The client_id about to be revoked.
	 * @return void
	 */
	public static function handle_client_revoked( string $client_id ): void {
		self::revoke_registered_client( $client_id, false );
	}
}

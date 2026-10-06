<?php
/** Operator-run, resumable Cloud onboarding. @package EMCP_Tools */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Cloud_Onboarding {
	/**
	 * Inspect, prepare Cloud approval, or resume Gateway onboarding.
	 *
	 * ## OPTIONS
	 *
	 * [--phase=<phase>]
	 * : preflight (default), prepare, enroll, or resume.
	 * [--grant-env=<name>]
	 * : Environment variable containing a short-lived Cloud enrollment grant. Never pass the secret as an argument.
	 * [--workspace=<id>]
	 * : Expected Cloud workspace ID from Account > Connected Sites.
	 * [--gateway]
	 * : Explicitly authorize enabling Gateway as the --user administrator.
	 * [--dry-run]
	 * : Local preflight only. No HTTP calls or writes.
	 *
	 * ## EXAMPLES
	 *
	 *     wp emcp cloud onboard --user=admin --dry-run
	 *     wp emcp cloud onboard --user=admin --phase=prepare --workspace=WORKSPACE
	 *     wp emcp cloud onboard --user=admin --phase=resume --workspace=WORKSPACE --gateway
	 */
	public static function command( $args, $assoc_args ): void {
		$phase = isset( $assoc_args['dry-run'] ) ? 'preflight' : (string) ( $assoc_args['phase'] ?? 'preflight' );
		$grant_env = (string) ( $assoc_args['grant-env'] ?? 'EMCP_ENROLLMENT_GRANT' );
		$grant = 'enroll' === $phase && preg_match( '/^[A-Z_][A-Z0-9_]*$/D', $grant_env ) ? (string) getenv( $grant_env ) : '';
		$result = self::run( $phase, (string) ( $assoc_args['workspace'] ?? '' ), isset( $assoc_args['gateway'] ), $grant );
		WP_CLI::line( wp_json_encode( $result ) );
		if ( 'blocked' === $result['state'] || 'retry' === $result['state'] ) { WP_CLI::halt( 1 ); }
	}

	public static function preflight(): array {
		$license = 'unavailable';
		if ( function_exists( 'emcp_tools_fs' ) ) {
			$fs = emcp_tools_fs();
			$license = $fs && $fs->can_use_premium_code() ? 'premium_access_available' : 'premium_access_unavailable';
		}
		return array(
			'contract_version' => 1, 'state' => 'ready', 'site_url' => EMCP_Tools_Cloud::installation_base(),
			'site_uuid' => (string) get_option( EMCP_Tools_Cloud::OPTION_SITE_UUID, '' ),
			'plugin_version' => defined( 'EMCP_TOOLS_VERSION' ) ? EMCP_TOOLS_VERSION : '',
			'license' => $license, 'cloud_connected' => EMCP_Tools_Cloud::is_connected(),
			'gateway_local' => (bool) get_option( EMCP_Tools_Gateway_Credential::OPTION_FLAG, false ),
			'identity_conflict' => EMCP_Tools_Cloud::identity_conflict(), 'health' => 'not_checked',
		);
	}

	public static function run( string $phase, string $workspace, bool $gateway, string $grant = '' ): array {
		if ( ! current_user_can( 'manage_options' ) || ! get_current_user_id() ) {
			return array( 'contract_version' => 1, 'state' => 'blocked', 'reason' => 'administrator_required' );
		}
		$result = self::preflight();
		if ( is_multisite() ) { return self::stop( $result, 'multisite_not_supported' ); }
		if ( $result['identity_conflict'] ) { return self::stop( $result, 'site_identity_conflict' ); }
		if ( 'preflight' === $phase ) { return $result; }
		if ( ! in_array( $phase, array( 'prepare', 'resume', 'enroll' ), true ) || ! preg_match( '/^[a-zA-Z0-9_-]{1,200}$/D', $workspace ) ) {
			return self::stop( $result, 'invalid_request' );
		}
		if ( 'https' !== wp_parse_url( EMCP_Tools_Cloud::base_url(), PHP_URL_SCHEME ) ||
			'https' !== wp_parse_url( EMCP_Tools_Cloud::installation_base(), PHP_URL_SCHEME ) ) {
			return self::stop( $result, 'https_required' );
		}
		global $wpdb;
		// A connection-scoped DB lock releases on process loss, with no stale lease takeover.
		$lock = 'emcp_onboard_' . substr( hash( 'sha256', $wpdb->prefix ), 0, 40 );
		if ( '1' !== (string) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $lock ) ) ) {
			return self::stop( $result, 'onboarding_busy', 'retry' );
		}
		try {
			if ( 'enroll' === $phase ) { return self::enroll( $result, $workspace, $gateway, $grant ); }
			return self::advance( $result, $phase, $workspace, $gateway );
		} catch ( \Throwable $error ) {
			// Never print a stack trace containing the enrollment grant argument.
			return self::stop( $result, 'onboarding_unavailable', 'retry' );
		} finally {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock ) );
		}
	}

	/** Enrollment credentials stay in memory and never enter CLI output or options. */
	private static function enroll( array $result, string $workspace, bool $gateway, string $grant ): array {
		if ( $result['cloud_connected'] ) {
			return self::advance( $result, $gateway ? 'resume' : 'prepare', $workspace, $gateway );
		}
		if ( ! preg_match( '/^emcp_enroll_[A-Za-z0-9_-]{43}$/D', $grant ) ) { return self::stop( $result, 'enrollment_grant_required' ); }
		$capability = EMCP_Tools_Cloud_Http::request( 'GET', EMCP_Tools_Cloud::base_url() . '/api/emcp/onboarding', array() );
		if ( is_wp_error( $capability ) || 200 !== (int) $capability['code'] || true !== ( $capability['json']['enrollment_grants'] ?? false ) ) {
			return self::stop( $result, 'cloud_enrollment_unavailable' );
		}
		$prepared = self::advance( $result, 'prepare', $workspace, false );
		// Do not return an authorization URL containing the registration proof.
		unset( $prepared['authorization_url'] );
		if ( 'awaiting_approval' !== $prepared['state'] ) { return $prepared; }
		$pending = get_transient( EMCP_Tools_Cloud_Connect::PENDING_TRANSIENT );
		$response = EMCP_Tools_Cloud_Http::request( 'POST', EMCP_Tools_Cloud::base_url() . '/api/emcp/enroll', array(
			'redirection' => 0,
			'headers' => array( 'Content-Type' => 'application/json', 'Authorization' => 'Bearer ' . $grant ),
			'body' => wp_json_encode( array(
				'workspace' => $workspace, 'siteUuid' => $pending['onboarding_site_uuid'], 'clientId' => $pending['client_id'],
				'redirectUri' => EMCP_Tools_Cloud_Connect::redirect_uri(), 'registrationProof' => $pending['registration_proof'],
				'challenge' => EMCP_Tools_OAuth_Util::code_challenge_s256( $pending['verifier'] ),
				'name' => substr( get_bloginfo( 'name' ), 0, 200 ), 'gateway' => $gateway,
			) ),
		) );
		if ( is_wp_error( $response ) || 408 === (int) $response['code'] || 429 === (int) $response['code'] || (int) $response['code'] >= 500 ) {
			return self::stop( $result, 'enrollment_unavailable', 'retry' );
		}
		$body = $response['json'];
		if ( 200 !== (int) $response['code'] || empty( $body['code'] ) || ( $body['workspace_id'] ?? '' ) !== $workspace ||
			( $gateway && true !== ( $body['gateway_allowed'] ?? false ) ) ) {
			return self::stop( $result, 'enrollment_rejected' );
		}
		$bundle = EMCP_Tools_Cloud_Connect::exchange_code( (string) $body['code'], $pending['verifier'], $pending['client_id'], $workspace );
		// A lost exchange response may already have consumed the code. A fresh DCR
		// proof on the next attempt reconnects the same UUID without another slot.
		delete_transient( EMCP_Tools_Cloud_Connect::PENDING_TRANSIENT );
		if ( is_wp_error( $bundle ) ) { return self::stop( $result, 'enrollment_exchange_failed', 'retry' ); }
		return self::advance( self::preflight(), $gateway ? 'resume' : 'prepare', $workspace, $gateway );
	}

	private static function stop( array $result, string $reason, string $state = 'blocked' ): array {
		return array_merge( $result, array( 'state' => $state, 'reason' => $reason ) );
	}

	private static function advance( array $result, string $phase, string $workspace, bool $gateway ): array {
		// Reuse the site's actual OAuth and Gateway state as the recovery journal.
		// Never reconnect an existing installation or replace its identity automatically.
		if ( $result['cloud_connected'] ) {
			$status = self::status( false );
			if ( is_wp_error( $status ) ) { return self::stop( $result, 'cloud_status_unavailable', 'retry' ); }
			if ( ( $status['workspace_id'] ?? '' ) !== $workspace ) { return self::stop( $result, 'workspace_mismatch' ); }
			if ( ! self::same_site( $status ) ) { return self::stop( $result, 'site_binding_mismatch' ); }
			$result['cloud_bound'] = true;
			$result['gateway_uploaded'] = ! empty( $status['gateway_uploaded'] );
			if ( 'prepare' === $phase ) { return array_merge( $result, array( 'state' => 'connected' ) ); }
			if ( empty( $status['gateway_allowed'] ) ) { return self::stop( $result, 'gateway_requires_paid' ); }
			if ( ! $result['gateway_uploaded'] ) {
				if ( ! $gateway ) { return self::stop( $result, 'gateway_consent_required' ); }
				if ( ! EMCP_Tools_Gateway_Credential::provision( get_current_user_id() ) ) {
					return self::stop( $result, 'gateway_upload_failed', 'retry' );
				}
				$result['gateway_uploaded'] = true;
				$result['gateway_local'] = true;
			}
			$status = self::status( true );
			if ( is_wp_error( $status ) ) { return self::stop( $result, 'health_unavailable', 'retry' ); }
			if ( ( $status['workspace_id'] ?? '' ) !== $workspace ) { return self::stop( $result, 'workspace_mismatch' ); }
			if ( ! self::same_site( $status ) ) { return self::stop( $result, 'site_binding_mismatch' ); }
			$result['health'] = (string) ( $status['health']['status'] ?? 'not_checked' );
			$result['state'] = 'available' === $result['health'] ? 'complete' : 'retry';
			if ( 'complete' !== $result['state'] ) { $result['reason'] = 'health_not_available'; }
			return $result;
		}
		if ( 'resume' === $phase ) { return self::stop( $result, 'cloud_approval_required', 'awaiting_approval' ); }
		$capability = EMCP_Tools_Cloud_Http::request( 'GET', EMCP_Tools_Cloud::base_url() . '/api/emcp/onboarding', array() );
		if ( is_wp_error( $capability ) || 200 !== (int) $capability['code'] ||
			1 !== ( $capability['json']['version'] ?? null ) || true !== ( $capability['json']['expected_workspace'] ?? false ) ) {
			return self::stop( $result, 'cloud_upgrade_required' );
		}
		$pending = get_transient( EMCP_Tools_Cloud_Connect::PENDING_TRANSIENT );
		if ( is_array( $pending ) ) {
			if ( ( $pending['onboarding_workspace'] ?? '' ) !== $workspace ||
				(int) ( $pending['onboarding_user'] ?? 0 ) !== get_current_user_id() ||
				( $pending['installation_base'] ?? '' ) !== EMCP_Tools_Cloud::installation_base() ||
				( $pending['onboarding_site_uuid'] ?? '' ) !== (string) get_option( EMCP_Tools_Cloud::OPTION_SITE_UUID, '' ) ||
				(int) ( $pending['onboarding_expires'] ?? 0 ) <= time() ) {
				return self::stop( $result, 'another_approval_pending' );
			}
		} else {
			$registration = EMCP_Tools_Cloud_Connect::register_client();
			if ( is_wp_error( $registration ) ) { return self::stop( $result, 'registration_failed', 'retry' ); }
			$pending = EMCP_Tools_Cloud_Connect::pending_record(
				EMCP_Tools_OAuth_Util::generate_code_verifier(), EMCP_Tools_OAuth_Util::generate_token(),
				$registration['client_id'], $registration['registration_proof'], false
			);
			$pending['onboarding_workspace'] = $workspace;
			$pending['onboarding_user'] = get_current_user_id();
			$pending['onboarding_site_uuid'] = EMCP_Tools_Cloud::site_uuid();
			$pending['onboarding_expires'] = time() + 600;
			$pending['registration_proof'] = $registration['registration_proof'];
			if ( ! set_transient( EMCP_Tools_Cloud_Connect::PENDING_TRANSIENT, $pending, 600 ) ) {
				return self::stop( $result, 'pending_save_failed', 'retry' );
			}
		}
		$result['site_uuid'] = $pending['onboarding_site_uuid'];
		$result['state'] = 'awaiting_approval';
		$result['expires_at'] = gmdate( 'c', $pending['onboarding_expires'] );
		$result['authorization_url'] = EMCP_Tools_Cloud_Connect::authorize_url(
			$pending['client_id'], $pending['verifier'], $pending['csrf'], $pending['registration_proof'], $workspace
		);
		return $result;
	}

	private static function status( bool $probe ) {
		return EMCP_Tools_Cloud_Client::get( '/api/cloud/v1/onboarding/status?site_uuid=' .
			rawurlencode( (string) get_option( EMCP_Tools_Cloud::OPTION_SITE_UUID, '' ) ) . ( $probe ? '&probe=1' : '' ) );
	}

	private static function same_site( array $status ): bool {
		$base = class_exists( 'EMCP_Tools_Site_Context' ) ? EMCP_Tools_Site_Context::public_base_url() : EMCP_Tools_Cloud::installation_base();
		return 1 === ( $status['version'] ?? null ) && true === ( $status['cloud_bound'] ?? false ) &&
			( $status['site_uuid'] ?? '' ) === (string) get_option( EMCP_Tools_Cloud::OPTION_SITE_UUID, '' ) &&
			rtrim( (string) ( $status['origin_url'] ?? '' ), '/' ) === rtrim( $base, '/' );
	}
}

<?php
/**
 * Local management policy with atomic, versioned option updates.
 *
 * This option is deliberately excluded from Cloud settings sync.
 *
 * @package EMCP_Tools
 */
if ( ! defined( 'ABSPATH' ) ) { exit; }

final class EMCP_Tools_Management_Policy {

	const OPTION = 'emcp_tools_management_policy';

	/** wp-config override: comma-separated IDs or an array (allowlist), or 'all'. */
	const CONSTANT = 'EMCP_TOOLS_MANAGEMENT_ADMINS';

	/** @var EMCP_Tools_Lease_Store|null Storage seam used by isolated tests. */
	private static $store;

	/** Uncached storage avoids retaining a revoked manager in an options cache. */
	private static function store(): EMCP_Tools_Lease_Store {
		return self::$store ?? new EMCP_Tools_Lease_Options_Store();
	}

	/** Read the exact row, distinguishing an absent policy from a database fault. */
	private static function raw(): ?string {
		$store = self::store();
		$raw   = $store->get( self::OPTION );
		if ( $store instanceof EMCP_Tools_Lease_Options_Store ) {
			global $wpdb;
			if ( $wpdb->last_error ) { throw new RuntimeException( 'storage_error' ); }
		}
		return $raw;
	}

	/** Validate stored data strictly; malformed policies never grant access. */
	public static function decode( ?string $raw ): array {
		if ( null === $raw ) { return array( 'version' => 0, 'mode' => 'all_admins', 'users' => array() ); }
		$policy = json_decode( $raw, true, 8, JSON_THROW_ON_ERROR );
		if ( ! is_array( $policy ) || count( $policy ) !== 3 || array_diff( array_keys( $policy ), array( 'version', 'mode', 'users' ) )
			|| ! is_int( $policy['version'] ?? null ) || $policy['version'] < 1
			|| ! in_array( $policy['mode'] ?? null, array( 'all_admins', 'allowlist' ), true )
			|| ! is_array( $policy['users'] ?? null ) || array_values( $policy['users'] ) !== $policy['users'] || count( $policy['users'] ) > 100 ) {
			throw new RuntimeException( 'invalid_policy' );
		}
		foreach ( $policy['users'] as $id ) {
			if ( ! is_int( $id ) || $id < 1 ) { throw new RuntimeException( 'invalid_policy' ); }
		}
		if ( count( array_unique( $policy['users'] ) ) !== count( $policy['users'] )
			|| ( 'all_admins' === $policy['mode'] && $policy['users'] )
			|| ( 'allowlist' === $policy['mode'] && ! $policy['users'] ) ) {
			throw new RuntimeException( 'invalid_policy' );
		}
		return $policy;
	}

	/** @return array|WP_Error Current local policy, without exposing storage diagnostics. */
	public static function read() {
		if ( self::locked_by_config() ) { return self::config_policy( constant( self::CONSTANT ) ); }
		try { return self::decode( self::raw() ); }
		catch ( Throwable $error ) {
			return new WP_Error( 'emcp_management_policy_unavailable', __( 'Management access could not be verified. Use the documented host recovery command.', 'emcp-tools' ), array( 'status' => 503 ) );
		}
	}

	/** True when wp-config.php defines the policy, which then overrides the stored one. */
	public static function locked_by_config(): bool {
		return defined( self::CONSTANT );
	}

	/**
	 * Read the wp-config.php value strictly. An invalid value never grants
	 * access: management stays closed until the constant is corrected.
	 *
	 * @param mixed $value Constant value.
	 * @return array|WP_Error
	 */
	public static function config_policy( $value ) {
		if ( is_string( $value ) && 'all' === strtolower( trim( $value ) ) ) {
			return array( 'version' => 0, 'mode' => 'all_admins', 'users' => array(), 'source' => 'config' );
		}
		if ( is_array( $value ) && $value && array_values( $value ) === $value && ! array_filter( $value, static fn( $id ) => ! is_int( $id ) ) ) {
			$value = implode( ',', $value );
		}
		$users = is_string( $value ) ? self::parse_users( trim( $value ) ) : null;
		if ( ! is_array( $users ) ) {
			return new WP_Error( 'emcp_management_config_invalid', __( 'EMCP_TOOLS_MANAGEMENT_ADMINS in wp-config.php must be comma-separated administrator IDs, an array of IDs, or "all". Management access stays closed until it is corrected.', 'emcp-tools' ), array( 'status' => 503 ) );
		}
		$users = array_values( array_unique( $users ) );
		sort( $users, SORT_NUMERIC );
		return array( 'version' => 0, 'mode' => 'allowlist', 'users' => $users, 'source' => 'config' );
	}

	/** User membership is checked in addition to their current WordPress capabilities. */
	public static function allows( array $policy, int $user_id ): bool {
		return 'all_admins' === $policy['mode'] || ( $user_id > 0 && in_array( $user_id, $policy['users'], true ) );
	}

	/** Parse explicit IDs without coercing malformed input into another account. */
	public static function parse_users( string $text ) {
		$parts = explode( ',', $text );
		$users = array();
		if ( count( $parts ) <= 100 && strlen( $text ) <= 2000 ) {
			foreach ( $parts as $part ) {
				$part = trim( $part );
				$id = filter_var( $part, FILTER_VALIDATE_INT );
				if ( false === $id || $id < 1 || (string) $id !== $part ) { break; }
				$users[] = $id;
			}
			if ( count( $users ) === count( $parts ) ) { return $users; }
		}
		return new WP_Error( 'emcp_management_invalid_user', __( 'Supply comma-separated positive administrator IDs.', 'emcp-tools' ), array( 'status' => 400 ) );
	}

	/** WP-CLI sets REQUEST_METHOD=GET for compatibility; verify the runtime instead. */
	public static function is_host_cli(): bool {
		return 'cli' === PHP_SAPI && defined( 'WP_CLI' ) && WP_CLI && ! ( defined( 'REST_REQUEST' ) && REST_REQUEST ) && ! ( defined( 'DOING_AJAX' ) && DOING_AJAX );
	}

	/**
	 * Save by compare-and-swap against the freshly read policy and actor's access.
	 * Recovery requires host WP-CLI, never an HTTP/MCP request pretending to be CLI.
	 *
	 * @return array|WP_Error
	 */
	public static function save( string $mode, array $users, int $expected_version, int $actor, bool $recovery = false ) {
		if ( self::locked_by_config() ) {
			return new WP_Error( 'emcp_management_policy_locked', __( 'Management access is set by EMCP_TOOLS_MANAGEMENT_ADMINS in wp-config.php. Change or remove it there.', 'emcp-tools' ), array( 'status' => 409 ) );
		}
		if ( $actor < 1 || ! get_userdata( $actor ) || ! user_can( $actor, 'manage_options' ) ) {
			return new WP_Error( 'emcp_management_forbidden', __( 'Administrator access is required.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		if ( $recovery && ! self::is_host_cli() ) {
			return new WP_Error( 'emcp_management_forbidden', __( 'Recovery requires host WP-CLI access.', 'emcp-tools' ), array( 'status' => 403 ) );
		}
		if ( ! in_array( $mode, array( 'all_admins', 'allowlist' ), true ) || $expected_version < 0 || count( $users ) > 100 || array_values( $users ) !== $users ) {
			return new WP_Error( 'emcp_management_invalid_policy', __( 'Select a valid management policy.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		foreach ( $users as $id ) {
			if ( ! is_int( $id ) || $id < 1 || ! get_userdata( $id ) || ! user_can( $id, 'manage_options' ) ) {
				return new WP_Error( 'emcp_management_invalid_user', __( 'Every selected account must be a current site administrator.', 'emcp-tools' ), array( 'status' => 400 ) );
			}
		}
		$users = array_values( array_unique( $users ) );
		sort( $users, SORT_NUMERIC );
		if ( ( 'all_admins' === $mode && $users ) || ( 'allowlist' === $mode && ( ! $users || ! in_array( $actor, $users, true ) ) ) ) {
			return new WP_Error( 'emcp_management_lockout', __( 'Keep your current administrator account in the selected managers.', 'emcp-tools' ), array( 'status' => 400 ) );
		}
		try {
			$raw = self::raw();
			try { $current = self::decode( $raw ); }
			catch ( Throwable $error ) {
				if ( ! $recovery || 0 !== $expected_version ) { throw $error; }
				$current = array( 'version' => 0, 'mode' => 'all_admins', 'users' => array() );
			}
			if ( ! $recovery && ! self::allows( $current, $actor ) ) {
				return new WP_Error( 'emcp_management_forbidden', __( 'Your management access has changed.', 'emcp-tools' ), array( 'status' => 403 ) );
			}
			if ( $current['version'] !== $expected_version ) {
				return new WP_Error( 'emcp_management_policy_changed', __( 'The policy changed. Reload before saving.', 'emcp-tools' ), array( 'status' => 409 ) );
			}
			$next = array( 'version' => $current['version'] + 1, 'mode' => $mode, 'users' => $users );
			$value = wp_json_encode( $next );
			$store = self::store();
			$saved = null === $raw ? $store->insert( self::OPTION, $value ) : $store->swap( self::OPTION, $raw, $value );
			if ( $store instanceof EMCP_Tools_Lease_Options_Store ) {
				global $wpdb;
				if ( $wpdb->last_error ) { throw new RuntimeException( 'storage_error' ); }
			}
			if ( ! $saved ) {
				return new WP_Error( 'emcp_management_policy_changed', __( 'The policy could not be saved. Reload before retrying.', 'emcp-tools' ), array( 'status' => 409 ) );
			}
			return $next;
		} catch ( Throwable $error ) {
			return new WP_Error( 'emcp_management_policy_unavailable', __( 'The policy could not be saved. Use the documented host recovery command if necessary.', 'emcp-tools' ), array( 'status' => 503 ) );
		}
	}
}

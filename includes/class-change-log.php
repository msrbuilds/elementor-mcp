<?php
/**
 * Unified change ledger + rollback dispatcher (AI-safe transactions).
 *
 * A single recorder every write site calls, storing lightweight rollback-capable
 * entries in one capped option, plus a rollback dispatcher that undoes an entry
 * via the right mechanism (re-save prior Elementor data, restore/delete a file
 * backup, or inverse a $wpdb write from a before-image).
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The change ledger.
 *
 * @since 3.4.0
 */
class EMCP_Tools_Change_Log {

	const OPTION    = 'emcp_tools_changelog';
	const MAX_COUNT = 200;
	const MAX_BYTES = 1048576; // ~1 MB.

	/**
	 * When true, record() is a no-op. Set during rollback so the rollback's own
	 * write (e.g. re-saving Elementor data) does not create a spurious entry.
	 *
	 * @var bool
	 */
	public static $suppress = false;

	/**
	 * Append an entry. Returns its id, or '' when suppressed.
	 *
	 * @param array $entry { domain, action, target?, summary?, rollback? }.
	 * @return string
	 */
	public static function record( array $entry ): string {
		if ( self::$suppress ) {
			return '';
		}
		$id    = self::uid();
		$entry = array_merge(
			array(
				'target'   => '',
				'summary'  => '',
				'rollback' => null,
			),
			$entry,
			array(
				'id'          => $id,
				'ts'          => time(),
				'user_id'     => function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0,
				'user_login'  => self::current_login(),
				'rolled_back' => false,
			)
		);
		$log   = self::all();
		$log[] = $entry;
		update_option( self::OPTION, self::cap( $log ), false );
		return $id;
	}

	/**
	 * All entries, oldest-first.
	 *
	 * @return array[]
	 */
	public static function all(): array {
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? array_values( $log ) : array();
	}

	/**
	 * Fetch an entry by id.
	 *
	 * @param string $id Entry id.
	 * @return array|null
	 */
	public static function get( string $id ): ?array {
		foreach ( self::all() as $e ) {
			if ( isset( $e['id'] ) && $e['id'] === $id ) {
				return $e;
			}
		}
		return null;
	}

	/**
	 * Flag an entry as rolled back.
	 *
	 * @param string $id Entry id.
	 */
	public static function mark_rolled_back( string $id ): void {
		$log = self::all();
		foreach ( $log as &$e ) {
			if ( isset( $e['id'] ) && $e['id'] === $id ) {
				$e['rolled_back'] = true;
			}
		}
		unset( $e );
		update_option( self::OPTION, $log, false );
	}

	/**
	 * Enforce count + size caps by dropping the oldest entries.
	 *
	 * @param array $log Entries.
	 * @return array
	 */
	private static function cap( array $log ): array {
		if ( count( $log ) > self::MAX_COUNT ) {
			$log = array_slice( $log, -self::MAX_COUNT );
		}
		while ( count( $log ) > 1 && strlen( (string) wp_json_encode( $log ) ) > self::MAX_BYTES ) {
			array_shift( $log );
		}
		return array_values( $log );
	}

	/**
	 * A short unique id.
	 *
	 * @return string
	 */
	private static function uid(): string {
		return substr( md5( uniqid( '', true ) ), 0, 12 );
	}

	/**
	 * Current user login (best-effort).
	 *
	 * @return string
	 */
	private static function current_login(): string {
		if ( function_exists( 'wp_get_current_user' ) ) {
			$u = wp_get_current_user();
			if ( $u && isset( $u->user_login ) ) {
				return (string) $u->user_login;
			}
		}
		return '';
	}
}

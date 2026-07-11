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
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The change ledger.
 *
 * @since 3.3.0
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
	 * Undo an entry by id, dispatching on its rollback type. Marks the entry
	 * rolled_back and records a compensating entry.
	 *
	 * @param string $id Entry id.
	 * @return array|WP_Error
	 */
	public static function rollback( string $id ) {
		$entry = self::get( $id );
		if ( null === $entry ) {
			return new WP_Error( 'not_found', __( 'Change not found.', 'emcp-tools' ) );
		}
		if ( ! empty( $entry['rolled_back'] ) ) {
			return new WP_Error( 'already_rolled_back', __( 'This change has already been rolled back.', 'emcp-tools' ) );
		}
		$rb = ( isset( $entry['rollback'] ) && is_array( $entry['rollback'] ) ) ? $entry['rollback'] : null;
		if ( null === $rb ) {
			return new WP_Error( 'not_reversible', __( 'This change is not reversible.', 'emcp-tools' ) );
		}

		self::$suppress = true;
		try {
			$result = self::apply_rollback( $rb );
		} catch ( \Throwable $e ) {
			$result = new WP_Error( 'rollback_failed', $e->getMessage() );
		} finally {
			self::$suppress = false;
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		self::mark_rolled_back( $id );
		$comp = self::record( array(
			'domain'   => $entry['domain'] ?? '',
			'action'   => 'rollback',
			'target'   => $entry['target'] ?? '',
			'summary'  => 'Rolled back: ' . ( $entry['summary'] ?? $id ),
			'rollback' => null,
		) );
		return array(
			'rolled_back'  => $id,
			'compensating' => $comp,
		);
	}

	/**
	 * Dispatch a rollback by type.
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function apply_rollback( array $rb ) {
		switch ( $rb['type'] ?? '' ) {
			case 'elementor-data':
				return self::rollback_elementor( $rb );
			case 'file-backup':
				return self::rollback_file_restore( $rb );
			case 'file-create':
				return self::rollback_file_delete( $rb );
			case 'db-before-image':
				return self::rollback_db( $rb );
			default:
				return new WP_Error( 'unknown_rollback', __( 'Unknown rollback type.', 'emcp-tools' ) );
		}
	}

	/**
	 * Restore a page's prior Elementor data.
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function rollback_elementor( array $rb ) {
		$post_id = (int) ( $rb['post_id'] ?? 0 );
		$before  = ( isset( $rb['before'] ) && is_array( $rb['before'] ) ) ? $rb['before'] : array();
		if ( $post_id <= 0 || ! class_exists( 'EMCP_Tools_Data' ) ) {
			return new WP_Error( 'rollback_failed', __( 'Cannot restore this page.', 'emcp-tools' ) );
		}
		$data = new EMCP_Tools_Data();
		$res  = $data->save_page_data( $post_id, $before );
		return is_wp_error( $res ) ? $res : true;
	}

	/**
	 * Restore a file from its backup (ABSPATH-confined).
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function rollback_file_restore( array $rb ) {
		$target = (string) ( $rb['target_path'] ?? '' );
		$backup = (string) ( $rb['backup_path'] ?? '' );
		if ( '' === $target || '' === $backup || ! is_file( $backup ) ) {
			return new WP_Error( 'rollback_failed', __( 'Backup is unavailable.', 'emcp-tools' ) );
		}
		$safe = self::guard_target( $target );
		if ( is_wp_error( $safe ) ) {
			return $safe;
		}
		return copy( $backup, $safe ) ? true : new WP_Error( 'rollback_failed', __( 'Could not restore the file.', 'emcp-tools' ) );
	}

	/**
	 * Delete a file that a recorded write created (ABSPATH-confined).
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function rollback_file_delete( array $rb ) {
		$target = (string) ( $rb['target_path'] ?? '' );
		if ( '' === $target || ! is_file( $target ) ) {
			return true; // Already gone — nothing to undo.
		}
		$safe = self::guard_target( $target );
		if ( is_wp_error( $safe ) ) {
			return $safe;
		}
		return @unlink( $safe ) ? true : new WP_Error( 'rollback_failed', __( 'Could not delete the created file.', 'emcp-tools' ) );
	}

	/**
	 * Inverse a database write from its before-image.
	 *
	 * @param array $rb Rollback ref.
	 * @return true|WP_Error
	 */
	private static function rollback_db( array $rb ) {
		global $wpdb;
		$table = (string) ( $rb['table'] ?? '' );
		$op    = (string) ( $rb['op'] ?? '' );
		if ( '' === $table ) {
			return new WP_Error( 'rollback_failed', __( 'Missing table.', 'emcp-tools' ) );
		}
		$keys = (array) ( $rb['key_cols'] ?? array() );
		switch ( $op ) {
			case 'update':
				foreach ( (array) ( $rb['before_rows'] ?? array() ) as $row ) {
					$where = array();
					foreach ( $keys as $c ) {
						if ( is_array( $row ) && array_key_exists( $c, $row ) ) {
							$where[ $c ] = $row[ $c ];
						}
					}
					$wpdb->update( $table, $row, $where );
				}
				return true;
			case 'delete':
				foreach ( (array) ( $rb['before_rows'] ?? array() ) as $row ) {
					$wpdb->insert( $table, $row );
				}
				return true;
			case 'insert':
				$wpdb->delete( $table, (array) ( $rb['inserted_key'] ?? array() ) );
				return true;
			default:
				return new WP_Error( 'rollback_failed', __( 'Unknown DB operation.', 'emcp-tools' ) );
		}
	}

	/**
	 * Confine a filesystem target to ABSPATH via the shared guard.
	 *
	 * @param string $target Absolute path.
	 * @return string|WP_Error Canonical path or error.
	 */
	private static function guard_target( string $target ) {
		if ( class_exists( 'EMCP_Tools_Filesystem_Guard' ) ) {
			return EMCP_Tools_Filesystem_Guard::resolve_path( $target );
		}
		return $target;
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

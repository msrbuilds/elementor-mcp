<?php
/**
 * Database transaction for the change log (3.19.0): START TRANSACTION, COMMIT and ROLLBACK on
 * the shared $wpdb connection, so a write, its History entry and an undo commit together.
 * Supported only while the History table is authoritative and both History tables use a
 * transactional engine: the option store goes through the object cache, which a ROLLBACK
 * cannot put back.
 *
 * @package EMCP_Tools
 * @since   3.19.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @since 3.19.0 */
class EMCP_Tools_Change_WPDB_Transaction {

	/** @var bool|null Engine check, once per process. */
	private static $engines_ok = null;

	public function supported(): bool {
		if ( ! EMCP_Tools_Change_Log::store()->is_table() ) {
			return false;
		}
		if ( null === self::$engines_ok ) {
			global $wpdb;
			$engines = $wpdb->get_col(
				$wpdb->prepare(
					'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN (%s, %s)',
					EMCP_Tools_Change_Names::table(),
					$wpdb->prefix . 'emcp_change_blobs'
				)
			);
			$ok = 2 === count( (array) $engines );
			foreach ( (array) $engines as $engine ) {
				$ok = $ok && 'innodb' === strtolower( (string) $engine );
			}
			self::$engines_ok = $ok;
		}
		return self::$engines_ok;
	}

	public function begin(): bool {
		global $wpdb;
		return false !== $wpdb->query( 'START TRANSACTION' );
	}

	public function commit(): bool {
		global $wpdb;
		$wpdb->last_error = '';
		return false !== $wpdb->query( 'COMMIT' ) && '' === (string) $wpdb->last_error;
	}

	public function rollback(): void {
		global $wpdb;
		$wpdb->query( 'ROLLBACK' );
	}
}

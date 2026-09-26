<?php
/**
 * Every name the change ledger stores under (spec 9.1). A namespace suffix
 * lets the live acceptance run beside the real ledger without touching it.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ledger storage names.
 */
final class EMCP_Tools_Change_Names {

	/** @var string */
	private static $ns = '';

	/**
	 * Use a namespace ('' for the real ledger).
	 *
	 * @param string $ns Letters and digits only.
	 */
	public static function use_namespace( string $ns ): void {
		self::$ns = (string) preg_replace( '/[^a-z0-9]/', '', strtolower( $ns ) );
	}

	public static function ns(): string {
		return self::$ns;
	}

	private static function suffix(): string {
		return '' === self::$ns ? '' : '_' . self::$ns;
	}

	private static function prefix(): string {
		global $wpdb;
		return ( isset( $wpdb ) && is_object( $wpdb ) && isset( $wpdb->prefix ) ) ? (string) $wpdb->prefix : 'wp_';
	}

	public static function table(): string {
		return self::prefix() . 'emcp_changes' . self::suffix();
	}

	public static function option(): string {
		return 'emcp_tools_changelog' . self::suffix();
	}

	public static function flag(): string {
		return 'emcp_tools_changes_store' . self::suffix();
	}

	public static function cutover(): string {
		return 'emcp_tools_changes_cutover' . self::suffix();
	}

	public static function recovery(): string {
		return 'emcp_tools_changelog_premigration' . self::suffix();
	}

	public static function recovery_ts(): string {
		return 'emcp_tools_changelog_premigration_ts' . self::suffix();
	}

	public static function cutover_error(): string {
		return 'emcp_tools_changes_cutover_error' . self::suffix();
	}

	public static function db_version(): string {
		return 'emcp_tools_changes_db_version' . self::suffix();
	}

	public static function unrecorded(): string {
		return 'emcp_tools_changes_unrecorded' . self::suffix();
	}

	public static function fallback(): string {
		return 'emcp_tools_changes_fallback' . self::suffix();
	}

	public static function retention(): string {
		return 'emcp_tools_changes_retention_days' . self::suffix();
	}

	public static function reconnect_notice(): string {
		return 'emcp_tools_changes_reconnect_notice' . self::suffix();
	}

	public static function lock(): string {
		return self::prefix() . 'emcp_changes_cutover' . self::suffix();
	}

	public static function lease(): string {
		return 'changes-migration' . self::suffix();
	}
}

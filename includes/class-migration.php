<?php
/**
 * Legacy data migration + coexistence guard for the elementor-mcp → emcp-tools
 * rename (v2.0).
 *
 * Loaded by the bootstrap file *before* constants/Freemius so the guard can
 * decide whether to boot at all when the old plugin is still active.
 *
 * @package EMCP_Tools
 * @since   2.1.0 (extracted from the bootstrap file; behavior since 2.0.0)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles the elementor-mcp → emcp-tools rename: detecting the still-active
 * legacy plugin and snapshotting its persisted settings into the new keys.
 *
 * @since 2.1.0
 */
class EMCP_Tools_Migration {

	/**
	 * The legacy plugin's basename.
	 */
	const LEGACY_PLUGIN = 'elementor-mcp/elementor-mcp.php';

	/**
	 * Whether the legacy `elementor-mcp` plugin is still active (single-site or
	 * network). While it is, this plugin must not boot (it would double-register
	 * the same MCP abilities/server and share data).
	 *
	 * @since 2.1.0 (since 2.0.0 as emcp_tools_legacy_plugin_active)
	 *
	 * @return bool
	 */
	public static function is_legacy_plugin_active(): bool {
		$active = (array) get_option( 'active_plugins', array() );
		if ( in_array( self::LEGACY_PLUGIN, $active, true ) ) {
			return true;
		}
		$network = (array) get_site_option( 'active_sitewide_plugins', array() );
		return isset( $network[ self::LEGACY_PLUGIN ] );
	}

	/**
	 * Copies persisted data from the legacy `elementor_mcp_*` keys to the new
	 * `emcp_tools_*` keys. Each option is seeded ONLY when the new key has never
	 * been set, so a returning user's saved settings are never clobbered on boot
	 * by the lingering legacy options. User-meta dismissal flags are renamed once
	 * (gated by a marker option).
	 *
	 * @since 2.1.0 (since 2.0.0 as emcp_tools_migrate_legacy_data)
	 */
	public static function migrate(): void {
		$option_map = array(
			'elementor_mcp_disabled_tools'   => 'emcp_tools_disabled_tools',
			'elementor_mcp_low_tool_mode'    => 'emcp_tools_low_tool_mode',
			'elementor_mcp_defaults_applied' => 'emcp_tools_defaults_applied',
			'elementor_mcp_server_enabled'   => 'emcp_tools_server_enabled',
		);
		$sentinel = '__emcp_tools_missing__';
		foreach ( $option_map as $old => $new ) {
			// CRITICAL: only seed the new key from the legacy one when the new key
			// has NEVER been set. The legacy elementor_mcp_* options linger in the
			// DB after the rename, and unconditionally copying them on every boot
			// would clobber the user's current settings (tool toggles, Low-tools
			// mode) right after they save — making every save appear to do nothing.
			if ( $sentinel !== get_option( $new, $sentinel ) ) {
				continue;
			}
			$value = get_option( $old, $sentinel );
			if ( $sentinel !== $value ) {
				update_option( $new, $value );
			}
		}

		// User-meta dismissal flags — rename the meta_key once (gated), so the
		// upgrade/community banners don't re-appear after the move.
		if ( ! get_option( 'emcp_tools_legacy_meta_migrated' ) ) {
			global $wpdb;
			$meta_map = array(
				'elementor_mcp_upgrade_notice_dismissed'   => 'emcp_tools_upgrade_notice_dismissed',
				'elementor_mcp_community_notice_dismissed' => 'emcp_tools_community_notice_dismissed',
			);
			foreach ( $meta_map as $old => $new ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery
				$wpdb->update( $wpdb->usermeta, array( 'meta_key' => $new ), array( 'meta_key' => $old ) ); // phpcs:ignore WordPress.DB.SlowDBQuery
			}
			update_option( 'emcp_tools_legacy_meta_migrated', 1 );
		}

		self::migrate_ability_slug_namespace();
	}

	/**
	 * One-time remap of the stored disabled-tools slugs from the old MCP ability
	 * namespace (`elementor-mcp/…`) to the new one (`emcp-tools/…`), introduced in
	 * v3.0.0 alongside the broader move beyond Elementor.
	 *
	 * The `emcp_tools_disabled_tools` option stores ability NAMES, which changed
	 * prefix in 3.0.0. Without this remap the runtime filter (`array_diff` of the
	 * registered names against the stored disabled list) would stop matching, so
	 * every previously-disabled tool — including the Pro tools that ship
	 * disabled-by-default — would silently switch back on. Gated by a marker so it
	 * runs exactly once; idempotent thereafter.
	 *
	 * @since 3.0.0
	 */
	private static function migrate_ability_slug_namespace(): void {
		if ( get_option( 'emcp_tools_slug_namespace_migrated' ) ) {
			return;
		}

		$disabled = get_option( 'emcp_tools_disabled_tools', array() );
		if ( is_array( $disabled ) && ! empty( $disabled ) ) {
			$remapped = array();
			foreach ( $disabled as $slug ) {
				$slug = (string) $slug;
				if ( 0 === strpos( $slug, 'elementor-mcp/' ) ) {
					$slug = 'emcp-tools/' . substr( $slug, strlen( 'elementor-mcp/' ) );
				}
				$remapped[] = $slug;
			}
			$remapped = array_values( array_unique( $remapped ) );
			if ( $remapped !== $disabled ) {
				update_option( 'emcp_tools_disabled_tools', $remapped );
			}
		}

		update_option( 'emcp_tools_slug_namespace_migrated', 1 );
	}
}

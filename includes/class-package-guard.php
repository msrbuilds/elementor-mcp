<?php
/**
 * Shared safety helper for the Plugins & Themes MCP tools.
 *
 * Centralizes the guardrails that protect a site from an AI agent (or a buggy
 * caller) breaking it: a protected-plugin list (never disable/delete EMCP Tools,
 * Elementor, or Elementor Pro), active-target checks, a direct-filesystem gate
 * (so a headless MCP request never hangs on an FTP-credential prompt), on-demand
 * loading of the wp-admin upgrader includes (absent on REST/WP-CLI requests),
 * and a quiet upgrader skin so installer output is captured, not echoed.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Static guard utilities shared by the plugin + theme ability groups.
 *
 * @since 3.0.0
 */
class EMCP_Tools_Package_Guard {

	/**
	 * Plugin files that must never be deactivated or deleted via MCP.
	 *
	 * EMCP Tools itself (disabling it kills the MCP server mid-session) and
	 * Elementor / Elementor Pro (EMCP's hard dependency). The EMCP basename is
	 * self-resolved from the constant, never hardcoded.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	public static function protected_plugin_files(): array {
		$files = array( 'elementor/elementor.php', 'elementor-pro/elementor-pro.php' );
		if ( defined( 'EMCP_TOOLS_BASENAME' ) ) {
			$files[] = EMCP_TOOLS_BASENAME;
		}
		/**
		 * Filter the MCP-protected plugin list.
		 *
		 * @since 3.0.0
		 * @param string[] $files Protected plugin basenames.
		 */
		return array_values( array_unique( (array) apply_filters( 'emcp_tools_protected_plugins', $files ) ) );
	}

	/**
	 * Whether a plugin file is protected from deactivate/delete.
	 *
	 * @since 3.0.0
	 * @param string $file Plugin basename (e.g. "elementor/elementor.php").
	 * @return bool
	 */
	public static function is_protected_plugin( string $file ): bool {
		return in_array( $file, self::protected_plugin_files(), true );
	}

	/**
	 * Whether a plugin is currently active (site or network).
	 *
	 * @since 3.0.0
	 * @param string $file Plugin basename.
	 * @return bool
	 */
	public static function is_active_plugin( string $file ): bool {
		if ( function_exists( 'is_plugin_active' ) && is_plugin_active( $file ) ) {
			return true;
		}
		return function_exists( 'is_plugin_active_for_network' ) && is_plugin_active_for_network( $file );
	}

	/**
	 * The active stylesheet plus its template (parent), both protected from delete.
	 *
	 * @since 3.0.0
	 * @return string[]
	 */
	public static function active_theme_stylesheets(): array {
		$out = array();
		if ( function_exists( 'get_stylesheet' ) ) {
			$out[] = (string) get_stylesheet();
		}
		if ( function_exists( 'get_template' ) ) {
			$out[] = (string) get_template();
		}
		return array_values( array_unique( array_filter( $out ) ) );
	}

	/**
	 * Ensure the filesystem is directly writable before any install/update/delete.
	 *
	 * Returns true when WP can write without credentials; otherwise a WP_Error,
	 * so the tool fails cleanly instead of triggering an interactive FTP prompt
	 * that would hang a headless MCP request.
	 *
	 * @since 3.0.0
	 * @return true|\WP_Error
	 */
	public static function filesystem_ready() {
		self::load_upgrader_deps();
		$method = function_exists( 'get_filesystem_method' ) ? get_filesystem_method() : 'direct';
		if ( 'direct' !== $method ) {
			return new \WP_Error(
				'filesystem_unavailable',
				__( 'The WordPress filesystem is not directly writable on this host (it needs FTP/SSH credentials), so plugin/theme install, update, and delete cannot run over MCP. Use SFTP or set the FS_METHOD/credentials in wp-config.php.', 'emcp-tools' )
			);
		}
		if ( function_exists( 'WP_Filesystem' ) && ! WP_Filesystem() ) {
			return new \WP_Error( 'filesystem_unavailable', __( 'Could not initialise the WordPress filesystem.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Load the wp-admin upgrader/plugin/theme includes on demand.
	 *
	 * These live under wp-admin/includes and are NOT loaded on the REST/WP-CLI
	 * requests the MCP server runs in. Guarded so each file loads at most once.
	 *
	 * @since 3.0.0
	 */
	public static function load_upgrader_deps(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			return;
		}
		$includes = array(
			'wp-admin/includes/plugin.php',
			'wp-admin/includes/plugin-install.php',
			'wp-admin/includes/theme.php',
			'wp-admin/includes/theme-install.php',
			'wp-admin/includes/file.php',
			'wp-admin/includes/misc.php',
			'wp-admin/includes/update.php',
			'wp-admin/includes/class-wp-upgrader.php',
		);
		foreach ( $includes as $rel ) {
			$path = ABSPATH . $rel;
			if ( is_readable( $path ) ) {
				require_once $path;
			}
		}
	}

	/**
	 * A quiet upgrader skin that captures messages instead of echoing HTML.
	 *
	 * @since 3.0.0
	 * @return object|null Automatic_Upgrader_Skin instance, or null if unavailable.
	 */
	public static function make_skin() {
		self::load_upgrader_deps();
		if ( class_exists( '\Automatic_Upgrader_Skin' ) ) {
			return new \Automatic_Upgrader_Skin();
		}
		return null;
	}

	/**
	 * Pull captured messages off an upgrader skin, normalized to strings.
	 *
	 * @since 3.0.0
	 * @param object|null $skin
	 * @return string[]
	 */
	public static function skin_messages( $skin ): array {
		if ( $skin && method_exists( $skin, 'get_upgrade_messages' ) ) {
			return array_map( 'wp_strip_all_tags', (array) $skin->get_upgrade_messages() );
		}
		return array();
	}
}

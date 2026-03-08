<?php
/**
 * Dependency manager for MCP Tools for Elementor.
 *
 * Handles automatic installation and activation of the MCP Adapter plugin
 * when it is not present. Uses WordPress's built-in Plugin_Upgrader to
 * download and install from the WordPress.org plugin repository.
 *
 * @package Elementor_MCP
 * @since   1.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages plugin dependencies with auto-install capabilities.
 *
 * @since 1.4.0
 */
class Elementor_MCP_Dependency_Manager {

	/**
	 * MCP Adapter plugin file path relative to the plugins directory.
	 *
	 * @var string
	 */
	const MCP_ADAPTER_PLUGIN = 'mcp-adapter/mcp-adapter.php';

	/**
	 * WordPress.org plugin slug for the MCP Adapter.
	 *
	 * @var string
	 */
	const MCP_ADAPTER_SLUG = 'mcp-adapter';

	/**
	 * Transient key for one-time admin notices.
	 *
	 * @var string
	 */
	const NOTICE_TRANSIENT = 'elementor_mcp_dependency_notice';

	/**
	 * Checks if the MCP Adapter is available and attempts to resolve if not.
	 *
	 * This method handles three scenarios:
	 * 1. Adapter is active — returns true immediately.
	 * 2. Adapter is installed but inactive — activates it.
	 * 3. Adapter is not installed — installs and activates it.
	 *
	 * @since 1.4.0
	 *
	 * @return bool True if the MCP Adapter is available after this call.
	 */
	public static function ensure_mcp_adapter(): bool {
		// Already loaded — nothing to do.
		if ( class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			return true;
		}

		// Only attempt auto-install in admin context with proper permissions.
		if ( ! is_admin() || ! current_user_can( 'install_plugins' ) ) {
			return false;
		}

		// Don't run during AJAX requests to avoid side effects.
		if ( wp_doing_ajax() ) {
			return false;
		}

		// Check if the plugin file exists (installed but inactive).
		$installed = self::is_adapter_installed();

		if ( $installed ) {
			return self::activate_adapter();
		}

		// Not installed — attempt to install and activate.
		return self::install_and_activate_adapter();
	}

	/**
	 * Checks if the MCP Adapter plugin is installed (regardless of activation).
	 *
	 * @since 1.4.0
	 *
	 * @return bool True if the plugin files exist.
	 */
	private static function is_adapter_installed(): bool {
		$plugin_file = WP_PLUGIN_DIR . '/' . self::MCP_ADAPTER_PLUGIN;
		return file_exists( $plugin_file );
	}

	/**
	 * Activates an already-installed MCP Adapter plugin.
	 *
	 * @since 1.4.0
	 *
	 * @return bool True on success.
	 */
	private static function activate_adapter(): bool {
		$result = activate_plugin( self::MCP_ADAPTER_PLUGIN );

		if ( is_wp_error( $result ) ) {
			self::set_notice(
				'error',
				sprintf(
					/* translators: %s: error message */
					__( 'MCP Tools for Elementor: Failed to activate MCP Adapter — %s', 'elementor-mcp' ),
					$result->get_error_message()
				)
			);
			return false;
		}

		self::set_notice(
			'success',
			__( 'MCP Tools for Elementor: MCP Adapter has been automatically activated.', 'elementor-mcp' )
		);

		return true;
	}

	/**
	 * Installs the MCP Adapter from WordPress.org and activates it.
	 *
	 * Uses the WordPress Plugin_Upgrader class for a clean, standard install.
	 *
	 * @since 1.4.0
	 *
	 * @return bool True on success.
	 */
	private static function install_and_activate_adapter(): bool {
		// Load required WordPress admin files for plugin installation.
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';

		// Get download link from WordPress.org plugin API.
		$api = plugins_api( 'plugin_information', array(
			'slug'   => self::MCP_ADAPTER_SLUG,
			'fields' => array( 'download_link' => true ),
		) );

		if ( is_wp_error( $api ) ) {
			self::set_notice(
				'error',
				sprintf(
					/* translators: %s: error message */
					__( 'MCP Tools for Elementor: Could not fetch MCP Adapter info from WordPress.org — %s. Please install it manually.', 'elementor-mcp' ),
					$api->get_error_message()
				)
			);
			return false;
		}

		// Use a silent skin to avoid output during installation.
		$skin      = new WP_Ajax_Upgrader_Skin();
		$upgrader  = new Plugin_Upgrader( $skin );
		$installed = $upgrader->install( $api->download_link );

		if ( true !== $installed || is_wp_error( $installed ) ) {
			$error_message = is_wp_error( $installed )
				? $installed->get_error_message()
				: __( 'Unknown error during installation.', 'elementor-mcp' );

			self::set_notice(
				'error',
				sprintf(
					/* translators: %s: error message */
					__( 'MCP Tools for Elementor: Failed to install MCP Adapter — %s. Please install it manually from Plugins → Add New.', 'elementor-mcp' ),
					$error_message
				)
			);
			return false;
		}

		// Activate the freshly installed plugin.
		$activated = activate_plugin( self::MCP_ADAPTER_PLUGIN );

		if ( is_wp_error( $activated ) ) {
			self::set_notice(
				'warning',
				__( 'MCP Tools for Elementor: MCP Adapter was installed but could not be activated automatically. Please activate it manually from the Plugins page.', 'elementor-mcp' )
			);
			return false;
		}

		self::set_notice(
			'success',
			__( 'MCP Tools for Elementor: MCP Adapter has been automatically installed and activated. You\'re all set!', 'elementor-mcp' )
		);

		return true;
	}

	/**
	 * Stores a one-time admin notice in a transient.
	 *
	 * @since 1.4.0
	 *
	 * @param string $type    Notice type: 'success', 'warning', 'error', 'info'.
	 * @param string $message The notice message.
	 */
	private static function set_notice( string $type, string $message ): void {
		set_transient( self::NOTICE_TRANSIENT, array(
			'type'    => $type,
			'message' => $message,
		), 60 );
	}

	/**
	 * Displays and clears the one-time admin notice, if any.
	 *
	 * Hooked to `admin_notices`.
	 *
	 * @since 1.4.0
	 */
	public static function display_notice(): void {
		$notice = get_transient( self::NOTICE_TRANSIENT );

		if ( empty( $notice ) || ! is_array( $notice ) ) {
			return;
		}

		delete_transient( self::NOTICE_TRANSIENT );

		$type    = in_array( $notice['type'], array( 'success', 'warning', 'error', 'info' ), true )
			? $notice['type']
			: 'info';
		$message = wp_kses_post( $notice['message'] );

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			esc_attr( $type ),
			$message
		);
	}
}

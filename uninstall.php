<?php
/**
 * Uninstall handler for Elementor MCP.
 *
 * Cleans up all plugin data when the plugin is uninstalled
 * through the WordPress admin.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin options.
delete_option( 'elementor_mcp_disabled_tools' );
delete_option( 'elementor_mcp_openverse_api_key' );

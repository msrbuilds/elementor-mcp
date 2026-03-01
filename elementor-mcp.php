<?php
/**
 * Plugin Name:       Elementor MCP
 * Plugin URI:        https://developer.suspended.suspended/elementor-mcp
 * Description:       Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.
 * Version:           1.2.3
 * Requires at least: 6.8
 * Tested up to:      6.8
 * Requires PHP:      7.4
 * Author:            developer
 * Author URI:        https://developer.suspended.suspended
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elementor-mcp
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'ELEMENTOR_MCP_VERSION', '1.2.3' );
define( 'ELEMENTOR_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELEMENTOR_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'ELEMENTOR_MCP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Checks that all required dependencies are available.
 *
 * @since 1.0.0
 *
 * @return bool True if all dependencies are met.
 */
function elementor_mcp_check_dependencies(): bool {
	$missing = array();

	// Elementor must be active.
	if ( ! did_action( 'elementor/loaded' ) ) {
		$missing[] = 'Elementor';
	}

	// MCP Adapter must be active.
	if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
		$missing[] = 'WordPress MCP Adapter';
	}

	// WordPress Abilities API must be available.
	if ( ! function_exists( 'wp_register_ability' ) ) {
		$missing[] = 'WordPress Abilities API (requires WordPress 6.9+)';
	}

	if ( ! empty( $missing ) ) {
		add_action( 'admin_notices', function () use ( $missing ) {
			$list = implode( ', ', $missing );
			printf(
				'<div class="notice notice-error"><p>%s</p></div>',
				sprintf(
					/* translators: %s: comma-separated list of missing dependencies */
					esc_html__( 'Elementor MCP requires the following to be installed and active: %s', 'elementor-mcp' ),
					'<strong>' . esc_html( $list ) . '</strong>'
				)
			);
		} );

		return false;
	}

	return true;
}

/**
 * Initializes the plugin.
 *
 * Hooked to `plugins_loaded` at priority 20 to ensure Elementor and
 * other dependencies are loaded first.
 *
 * @since 1.0.0
 */
function elementor_mcp_init(): void {
	if ( ! elementor_mcp_check_dependencies() ) {
		return;
	}

	// Load class files.
	require_once ELEMENTOR_MCP_DIR . 'includes/class-id-generator.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-elementor-data.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-element-factory.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/schemas/class-control-mapper.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/schemas/class-schema-generator.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/validators/class-element-validator.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/validators/class-settings-validator.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-query-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-page-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-layout-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-widget-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-template-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-global-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-composite-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-openverse-client.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-stock-image-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-svg-icon-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-ability-registrar.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-plugin.php';

	// Admin.
	if ( is_admin() ) {
		require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-admin.php';
	}

	// Boot the plugin.
	Elementor_MCP_Plugin::instance();
}
add_action( 'plugins_loaded', 'elementor_mcp_init', 20 );

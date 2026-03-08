<?php
/**
 * Plugin Name:       MCP Tools for Elementor
 * Plugin URI:        https://github.com/msrbuilds/elementor-mcpelementor-mcp
 * Description:       Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.
 * Version:           1.5.0
 * Requires at least: 6.7
 * Tested up to:      6.9
 * Requires PHP:      7.4
 * Author:            Mian Shahzad Raza
 * Author URI:        https://msrbuilds.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       elementor-mcp
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'ELEMENTOR_MCP_VERSION', '1.5.0' );
define( 'ELEMENTOR_MCP_DIR', plugin_dir_path( __FILE__ ) );
define( 'ELEMENTOR_MCP_URL', plugin_dir_url( __FILE__ ) );
define( 'ELEMENTOR_MCP_BASENAME', plugin_basename( __FILE__ ) );

// Load the dependency manager early for auto-install capabilities.
require_once ELEMENTOR_MCP_DIR . 'includes/class-dependency-manager.php';

// Always hook the one-time notice display so install/activate messages show up.
add_action( 'admin_notices', array( 'Elementor_MCP_Dependency_Manager', 'display_notice' ) );

// Hook auto-install to admin_init where user authentication is fully ready.
// This runs BEFORE plugins_loaded on the NEXT page load after the redirect.
add_action( 'admin_init', 'elementor_mcp_maybe_install_adapter' );

/**
 * Attempts to auto-install/activate the MCP Adapter on admin_init.
 *
 * Runs on admin_init because current_user_can() requires the user session
 * to be fully established, which isn't guaranteed during plugins_loaded.
 *
 * @since 1.4.0
 */
function elementor_mcp_maybe_install_adapter(): void {
	// Only act if the adapter is not loaded.
	if ( class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
		return;
	}

	$resolved = Elementor_MCP_Dependency_Manager::ensure_mcp_adapter();

	if ( $resolved ) {
		// Adapter was just installed/activated. Redirect so WordPress
		// fully loads the newly-activated plugin on the next request.
		wp_safe_redirect( add_query_arg( 'elementor-mcp-adapter-installed', '1' ) );
		exit;
	}
}

/**
 * Checks that all required dependencies are available.
 *
 * @since 1.0.0
 * @since 1.4.0 Added auto-install for MCP Adapter.
 *
 * @return bool True if all dependencies are met.
 */
function elementor_mcp_check_dependencies(): bool {
	$missing = array();

	// Elementor must be active.
	if ( ! did_action( 'elementor/loaded' ) ) {
		$missing[] = 'Elementor';
	}

	// MCP Adapter must be active (auto-install is handled by admin_init hook).
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
					esc_html__( 'MCP Tools for Elementor requires the following to be installed and active: %s', 'elementor-mcp' ),
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
	require_once ELEMENTOR_MCP_DIR . 'includes/class-pexels-client.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-pixabay-client.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-unsplash-client.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-stock-image-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-svg-icon-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-custom-code-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-screenshot-abilities.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/abilities/class-ability-registrar.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-mcp-instructions.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-plugin.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-autologin.php';
	require_once ELEMENTOR_MCP_DIR . 'includes/class-connection-tokens.php';

	// Admin.
	if ( is_admin() ) {
		require_once ELEMENTOR_MCP_DIR . 'includes/admin/class-admin.php';
	}

	// Boot the plugin.
	Elementor_MCP_Plugin::instance();

	// Register auto-login endpoint for authenticated preview access.
	$autologin = new Elementor_MCP_Autologin();
	$autologin->init();

	// Register plugin-managed Bearer token authentication.
	$connection_tokens = new Elementor_MCP_Connection_Tokens();
	$connection_tokens->init();
}
add_action( 'plugins_loaded', 'elementor_mcp_init', 20 );

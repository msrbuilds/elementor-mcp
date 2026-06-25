<?php
/**
 * Plugin Name:       EMCP Tools
 * Plugin URI:        https://github.com/msrbuilds/elementor-mcp
 * Description:       Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.
 * Version:           3.0.0
 * Requires at least: 6.9
 * Tested up to:      6.9
 * Requires PHP:      8.2
 * Author:            Mian Shahzad Raza
 * Author URI:        https://msrbuilds.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       emcp-tools
 * Domain Path:       /languages
 *
 * This file is the bootstrap ONLY: plugin header, the legacy-rename guard,
 * constants, the Freemius SDK helper, the uninstall hook, and the entry point
 * that hands off to EMCP_Tools_Bootstrap. All feature logic lives in classes
 * under includes/.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Legacy coexistence guard.
 *
 * This plugin was renamed from the `elementor-mcp` folder/slug to `emcp-tools`.
 * On an existing site the old `elementor-mcp/elementor-mcp.php` plugin may still
 * be active alongside this one during the transition. All PHP symbols were
 * re-prefixed (EMCP_Tools_* / emcp_tools_*) so the two can coexist without
 * "cannot redeclare" fatals — but they would still both register the same MCP
 * abilities/server and share data. So while the old plugin is active we do NOT
 * boot: we snapshot its settings (admin only) and show a notice, then bail
 * before defining constants, initializing Freemius, or registering anything.
 */
require_once __DIR__ . '/includes/class-migration.php';

if ( EMCP_Tools_Migration::is_legacy_plugin_active() ) {
	// Snapshot the old plugin's settings into the new keys WHILE it's still
	// installed — once the user deletes it, its uninstall hook wipes them.
	if ( is_admin() ) {
		EMCP_Tools_Migration::migrate();
	}
	add_action(
		'admin_notices',
		function () {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			echo '<div class="notice notice-warning"><p>';
			echo wp_kses(
				__( '<strong>EMCP Tools:</strong> The previous &#8220;MCP Tools for Elementor&#8221; plugin (folder <code>elementor-mcp</code>) is still active. EMCP Tools has replaced it &mdash; please <strong>deactivate and delete</strong> the old plugin to finish the upgrade. Your settings and license carry over automatically. EMCP Tools stays paused until then.', 'emcp-tools' ),
				array(
					'strong' => array(),
					'code'   => array(),
				)
			);
			echo '</p></div>';
		}
	);
	// Bail before booting anything else (no constants, no Freemius, no abilities).
	return;
}

// Plugin constants.
define( 'EMCP_TOOLS_VERSION', '3.0.0' );
define( 'EMCP_TOOLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EMCP_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'EMCP_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

if ( ! function_exists( 'emcp_tools_fs' ) ) {
	// Create a helper function for easy SDK access.
	function emcp_tools_fs() {
		global $emcp_tools_fs;

		if ( ! isset( $emcp_tools_fs ) ) {
			// Activate multisite network integration.
			if ( ! defined( 'WP_FS__PRODUCT_30577_MULTISITE' ) ) {
				define( 'WP_FS__PRODUCT_30577_MULTISITE', true );
			}

			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/includes/vendors/fremius/start.php';

			// The premium build ships a `.emcp-pro` marker file at the plugin
			// root; the free build does not. Freemius needs is_premium=true on
			// the premium build so it shows the LICENSE-ACTIVATION flow (gated
			// on is_premium()) instead of the free connect/opt-in screen. With
			// it hardcoded false, the premium zip behaved like the free version
			// and never offered license activation.
			$emcp_tools_is_premium = file_exists( dirname( __FILE__ ) . '/.emcp-pro' );

			$emcp_tools_fs = fs_dynamic_init( array(
				'id'                  => '30577',
				'slug'                => 'emcp-tools',
				'premium_slug'        => 'emcp-pro',
				'type'                => 'plugin',
				'public_key'          => 'pk_2b2a026d5c27655581635abcd4556',
				'is_premium'          => $emcp_tools_is_premium,
				'premium_suffix'      => 'Pro',
				'has_premium_version' => true,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				'is_org_compliant'    => false,
				'has_affiliation'     => 'selected',
				'menu'                => array(
					'slug'           => 'emcp-tools',
					'support'        => false,
				),
			) );
		}

		return $emcp_tools_fs;
	}

	// Init Freemius.
	emcp_tools_fs();
	// Signal that SDK was initiated.
	do_action( 'emcp_tools_fs_loaded' );
}

// Uninstall cleanup runs via Freemius's after_uninstall action (uninstall.php
// was removed in v1.6.1 — Freemius rejects builds containing it).
require_once EMCP_TOOLS_DIR . 'includes/class-uninstaller.php';
if ( function_exists( 'emcp_tools_fs' ) ) {
	emcp_tools_fs()->add_action( 'after_uninstall', array( 'EMCP_Tools_Uninstaller', 'run' ) );
}

/**
 * Canonical "Upgrade to Pro" URL — the external pricing page on the EMCP Tools
 * website. Used by every upgrade CTA in the plugin admin so users land on the
 * public pricing page (with full plan comparison + FAQ) rather than Freemius's
 * bundled in-admin pricing iframe.
 *
 * @since 1.7.1
 *
 * @return string
 */
function emcp_tools_upgrade_url(): string {
	return 'https://emcp.msrbuilds.com/pricing';
}

// Hand off to the bootstrap (loads classes + wires hooks) once dependencies
// like Elementor are available.
require_once EMCP_TOOLS_DIR . 'includes/class-bootstrap.php';
add_action( 'plugins_loaded', array( 'EMCP_Tools_Bootstrap', 'boot' ), 20 );

<?php
/**
 * Plugin Name:       EMCP Tools
 * Plugin URI:        https://github.com/msrbuilds/elementor-mcp
 * Description:       Extends the WordPress MCP Adapter to expose Elementor data, widgets, and page design tools as MCP tools for AI agents.
 * Version:           3.18.0
 * Requires at least: 6.9
 * Tested up to:      7.1
 * Requires PHP:      8.1
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
 * Free ⇄ Premium single-instance guard.
 *
 * The free build (folder `emcp-tools`) and the premium build (folder `emcp-pro`)
 * are the SAME plugin — identical `emcp-tools.php` + `includes/`, differing only
 * by the `pro/*` overlay and the `.emcp-pro` marker. WordPress treats the two
 * folders as separate plugins, so `require_once` does NOT dedupe across them:
 * activating the second copy while the first is active redeclares every class
 * (starting at includes/class-migration.php below) → a fatal error.
 *
 * This guard runs BEFORE any require, so it can never redeclare. Premium always
 * wins: the free copy yields to an active premium sibling (and shows a notice),
 * and the premium copy retires an active free sibling. A last-resort redeclare
 * net covers the one request (a premium activation) where the free copy has
 * already booted.
 */
if ( ! function_exists( 'emcp_tools_retire_sibling' ) ) {
	/**
	 * Deactivate a sibling EMCP Tools build on the next admin_init.
	 *
	 * @param string $basename Plugin basename to deactivate.
	 */
	function emcp_tools_retire_sibling( $basename ) {
		add_action(
			'admin_init',
			function () use ( $basename ) {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				if ( ! function_exists( 'deactivate_plugins' ) ) {
					require_once ABSPATH . 'wp-admin/includes/plugin.php';
				}
				if ( is_plugin_active( $basename ) ) {
					deactivate_plugins( $basename );
					set_transient( 'emcp_tools_free_retired', 1, 60 );
				}
			}
		);
	}
}

$emcp_tools_is_premium_build = file_exists( __DIR__ . '/.emcp-pro' );
$emcp_tools_free_basename    = 'emcp-tools/emcp-tools.php';
$emcp_tools_premium_basename = 'emcp-pro/emcp-tools.php';

// Last-resort redeclare net: another EMCP Tools copy already booted this
// request. If THIS is premium and the free copy booted first, retire free so
// the next load runs premium alone.
if ( defined( 'EMCP_TOOLS_VERSION' ) ) {
	if ( $emcp_tools_is_premium_build ) {
		emcp_tools_retire_sibling( $emcp_tools_free_basename );
	}
	return;
}

// The free build yields to an active premium sibling (bail before defining or
// requiring anything). A persistent notice tells the admin to remove the free copy.
if ( ! $emcp_tools_is_premium_build ) {
	$emcp_tools_premium_active = in_array( $emcp_tools_premium_basename, (array) get_option( 'active_plugins', array() ), true )
		|| ( is_multisite() && array_key_exists( $emcp_tools_premium_basename, (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
	if ( $emcp_tools_premium_active ) {
		add_action(
			'admin_notices',
			function () {
				if ( ! current_user_can( 'activate_plugins' ) ) {
					return;
				}
				echo '<div class="notice notice-warning"><p>';
				echo wp_kses(
					__( '<strong>EMCP Tools Pro is active.</strong> The free version of EMCP Tools stays paused to avoid a conflict &mdash; you can safely <strong>deactivate and delete</strong> it. Everything is handled by Pro.', 'emcp-tools' ),
					array( 'strong' => array() )
				);
				echo '</p></div>';
			}
		);
		return;
	}
}

// The premium build retires an active free sibling.
if ( $emcp_tools_is_premium_build ) {
	emcp_tools_retire_sibling( $emcp_tools_free_basename );
	add_action(
		'admin_notices',
		function () {
			if ( get_transient( 'emcp_tools_free_retired' ) && current_user_can( 'activate_plugins' ) ) {
				delete_transient( 'emcp_tools_free_retired' );
				echo '<div class="notice notice-info is-dismissible"><p>';
				echo esc_html__( 'EMCP Tools: the free version was deactivated because the Pro version is active.', 'emcp-tools' );
				echo '</p></div>';
			}
		}
	);
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
define( 'EMCP_TOOLS_VERSION', '3.18.0' );
define( 'EMCP_TOOLS_DIR', plugin_dir_path( __FILE__ ) );
define( 'EMCP_TOOLS_URL', plugin_dir_url( __FILE__ ) );
define( 'EMCP_TOOLS_BASENAME', plugin_basename( __FILE__ ) );

// Claim the WP\MCP namespace for our bundled MCP Adapter copy, at file-load —
// BEFORE any other plugin can autoload an adapter class. Other plugins bundle
// the adapter behind their own autoloaders (Rank Math SEO, …) and PHP allows
// only one class of a given name per request, so without this the namespace
// can shear across two adapter versions and the MCP session dies mid-request
// ("Session terminated", -32600). Lazy: registers a resolver, loads nothing.
require_once EMCP_TOOLS_DIR . 'includes/class-mcp-adapter-bootstrap.php';
EMCP_Tools_Adapter_Bootstrap::preload_bundled_namespace();

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

	// Trim the Freemius-injected submenu items we surface elsewhere: Contact Us
	// (Help & Support lives in the plugin header), Affiliation (shown in the
	// header next to Changelog), and Pricing for licensed Pro users (free users
	// keep the upgrade link). Freemius keeps the underlying pages reachable by
	// URL, so the header links still work.
	emcp_tools_fs()->add_filter(
		'is_submenu_visible',
		function ( $is_visible, $menu_id ) {
			if ( 'contact' === $menu_id || 'affiliation' === $menu_id ) {
				return false;
			}
			if ( 'pricing' === $menu_id && emcp_tools_fs()->can_use_premium_code() ) {
				return false;
			}
			return $is_visible;
		},
		10,
		2
	);
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
 * MUST be function_exists-guarded: an unconditional top-level function is
 * *early-bound at compile time*, so PHP would try to declare it while merely
 * compiling this file — before the single-instance guard above can `return`.
 * When the free (emcp-tools) and premium (emcp-pro) folders are both present,
 * compiling the second copy would then redeclare it and fatal, bypassing the
 * runtime guard entirely. The function_exists wrapper makes it a runtime
 * (conditional) declaration the guard's early `return` skips.
 *
 * @since 1.7.1
 *
 * @return string
 */
if ( ! function_exists( 'emcp_tools_upgrade_url' ) ) {
	function emcp_tools_upgrade_url(): string {
		return 'https://emcptools.com/pricing';
	}
}

// Hand off to the bootstrap (loads classes + wires hooks) once dependencies
// like Elementor are available.
require_once EMCP_TOOLS_DIR . 'includes/class-bootstrap.php';
add_action( 'plugins_loaded', array( 'EMCP_Tools_Bootstrap', 'boot' ), 20 );

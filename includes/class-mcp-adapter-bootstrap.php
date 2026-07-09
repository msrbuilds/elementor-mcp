<?php
/**
 * Bundled MCP Adapter bootstrap.
 *
 * The plugin ships a copy of the WordPress MCP Adapter (`wordpress/mcp-adapter`)
 * under vendor/ so users never have to install it as a separate plugin. The
 * Abilities API is core in WordPress 6.9+/7.0, but core does NOT expose
 * abilities over MCP — the adapter is what creates the `/wp-json/mcp/...`
 * server endpoint. Bundling it makes EMCP self-contained.
 *
 * The adapter is loaded through the Automattic Jetpack Autoloader
 * (vendor/autoload_packages.php). When several active plugins each bundle the
 * same adapter (WooCommerce, Automattic MCP, …), the Jetpack Autoloader
 * coordinates via shared globals and registers only the HIGHEST version of
 * each class process-wide — so there is never a "class already declared"
 * fatal or a version clash, regardless of plugin load order.
 *
 * "Loadable" is not "booted": a plugin can autoload the WP\MCP classes without
 * instantiating the adapter (WooCommerce does this unless its MCP feature flag
 * is on). If nobody boots it, `mcp_adapter_init` never fires and our server
 * route is never created. So ensure() also boots the adapter itself
 * (idempotent) rather than assuming an external owner did.
 *
 * @package EMCP_Tools
 * @since   1.7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads/boots the MCP Adapter — bundled copy or already-active external one.
 *
 * @since 1.7.4
 */
final class EMCP_Tools_Adapter_Bootstrap {

	/**
	 * Version of the bundled adapter (keep in sync with the copied source).
	 */
	const BUNDLED_VERSION = '0.5.0';

	/**
	 * Where the adapter came from: 'external', 'bundled', or 'none'.
	 *
	 * @var string
	 */
	private static $source = 'none';

	/**
	 * Ensures the MCP Adapter is available, booting the bundled copy if needed.
	 *
	 * Safe to call once, early in plugin init (before the dependency check).
	 *
	 * @since 1.7.4
	 */
	public static function ensure(): void {
		// Load the adapter through the Jetpack Autoloader. It arbitrates the
		// highest version across every active plugin that bundles the adapter,
		// so requiring our copy is safe even when WooCommerce/others ship it too.
		$autoloader = EMCP_TOOLS_DIR . 'vendor/autoload_packages.php';
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) && is_readable( $autoloader ) ) {
			require_once $autoloader;
		}

		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			self::$source = 'none';
			return;
		}

		// External vs bundled is informational only now (the Jetpack Autoloader
		// picks the winning copy regardless of who required it first).
		self::$source = 'bundled';

		// Boot the adapter (idempotent singleton). This is what fires
		// mcp_adapter_init. It is a no-op if an external owner already booted it,
		// and the safety net when the classes were merely autoloaded but never
		// instantiated (e.g. WooCommerce with its MCP feature flag off). Booting
		// only fires the init action; it does not register any other plugin's
		// tools — those are added on that hook by each plugin's own gated code.
		if ( class_exists( '\WP\MCP\Plugin' ) ) {
			\WP\MCP\Plugin::instance();
		} elseif ( method_exists( '\WP\MCP\Core\McpAdapter', 'instance' ) ) {
			// Fallback: a copy that exposes McpAdapter but not the Plugin wrapper.
			\WP\MCP\Core\McpAdapter::instance();
		}
	}

	/**
	 * Whether the MCP Adapter core class is available (from either source).
	 *
	 * @since 1.7.4
	 *
	 * @return bool
	 */
	public static function is_loaded(): bool {
		return class_exists( '\WP\MCP\Core\McpAdapter' );
	}

	/**
	 * Where the adapter was loaded from: 'external', 'bundled', or 'none'.
	 *
	 * @since 1.7.4
	 *
	 * @return string
	 */
	public static function source(): string {
		return self::$source;
	}
}

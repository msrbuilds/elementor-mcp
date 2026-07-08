<?php
/**
 * Bundled MCP Adapter bootstrap.
 *
 * The plugin ships a copy of the WordPress MCP Adapter (`wordpress/mcp-adapter`)
 * under includes/vendors/mcp-adapter/ so users never have to install it as a
 * separate plugin. The Abilities API is core in WordPress 6.9+/7.0, but core
 * does NOT expose abilities over MCP — the adapter is still what creates the
 * `/wp-json/mcp/...` server endpoint. Bundling it makes EMCP self-contained.
 *
 * If a standalone MCP Adapter plugin is already active, we defer to it (its
 * classes are loaded first at plugin-include time, before this runs on
 * plugins_loaded) and do nothing — so there's never a double-load or version
 * clash. We only boot the bundled copy when nothing else has.
 *
 * Only the adapter's `includes/` source is bundled (its 441K Composer
 * `vendor/` is entirely dev tooling — the package has zero runtime deps), so
 * we register a minimal PSR-4 autoloader for the `WP\MCP\` namespace rather
 * than loading the adapter's Composer autoloader.
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
	const BUNDLED_VERSION = '0.4.1';

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
		// A copy of the MCP Adapter is already loadable. That can be a standalone
		// MCP Adapter plugin (which boots it) OR another plugin that merely bundles
		// and autoloads the WP\MCP classes without booting a server. WooCommerce
		// 10.5+ does the latter: it registers the WP\MCP autoloader (so the
		// class_exists() check below is true) but only instantiates the adapter
		// when its experimental "MCP Integration" feature flag is enabled, which is
		// off by default. "Loadable" is not "booted": if nobody boots the adapter,
		// mcp_adapter_init never fires, our MCP server route is never created, and
		// the endpoint 404s. So ensure the adapter is booted instead of deferring
		// to a no-op.
		if ( class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			self::$source = 'external';

			// Idempotent singleton: a no-op if the external owner already booted
			// the adapter, and the safety net when it only autoloaded the classes
			// (e.g. WooCommerce with its MCP feature flag off). This only fires
			// mcp_adapter_init; it does not register any other plugin's tools —
			// those are added on that hook by each plugin's own (gated) code.
			if ( class_exists( '\WP\MCP\Plugin' ) ) {
				\WP\MCP\Plugin::instance();
			}

			return;
		}

		$base = EMCP_TOOLS_DIR . 'includes/vendors/mcp-adapter/includes/';
		if ( ! is_dir( $base ) ) {
			self::$source = 'none';
			return;
		}

		// Minimal PSR-4 autoloader for the bundled adapter's WP\MCP\ namespace.
		spl_autoload_register(
			static function ( $class ) use ( $base ) {
				$prefix = 'WP\\MCP\\';
				if ( 0 !== strpos( $class, $prefix ) ) {
					return;
				}
				$relative = substr( $class, strlen( $prefix ) );
				$file     = $base . str_replace( '\\', '/', $relative ) . '.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
		);

		// The standalone plugin defines these in its bootstrap; replicate for
		// parity. WP_MCP_AUTOLOAD=false stops the adapter's own Autoloader from
		// looking for a Composer vendor/autoload.php we intentionally didn't ship.
		if ( ! defined( 'WP_MCP_DIR' ) ) {
			define( 'WP_MCP_DIR', EMCP_TOOLS_DIR . 'includes/vendors/mcp-adapter/' );
		}
		if ( ! defined( 'WP_MCP_VERSION' ) ) {
			define( 'WP_MCP_VERSION', self::BUNDLED_VERSION );
		}
		if ( ! defined( 'WP_MCP_AUTOLOAD' ) ) {
			define( 'WP_MCP_AUTOLOAD', false );
		}

		// Boot the adapter exactly as its standalone plugin would: Plugin::instance()
		// wires McpAdapter onto rest_api_init / init, which fires mcp_adapter_init.
		if ( class_exists( '\WP\MCP\Plugin' ) ) {
			\WP\MCP\Plugin::instance();
			self::$source = self::is_loaded() ? 'bundled' : 'none';
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

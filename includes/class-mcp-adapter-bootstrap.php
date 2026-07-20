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
 * The adapter's Composer dependencies load through the Automattic Jetpack
 * Autoloader (vendor/autoload_packages.php). When several active plugins each
 * bundle the same adapter (WooCommerce, Automattic MCP, …), the Jetpack
 * Autoloader coordinates via shared globals and registers only the HIGHEST
 * version of each class process-wide.
 *
 * That arbitration only covers plugins that USE the Jetpack Autoloader. A
 * plugin bundling the adapter behind its own plain autoloader (Rank Math SEO,
 * for one) never joins it — and PHP can only ever have ONE class of a given
 * name loaded per request, so the two copies race per class. The observed
 * failure (#99): McpAdapter/McpServer resolve from our v0.5.0 (loaded eagerly
 * at boot) while HttpTransport/SessionManager/RequestRouter/
 * HttpSessionValidator/McpErrorFactory resolve from the other plugin's v0.4.1
 * (loaded lazily mid-request, where its autoloader wins the race). The result
 * is a SHEARED namespace — a half-and-half runtime that fails with
 * "McpServerError: Session terminated" (JSON-RPC -32600).
 *
 * Checking `class_exists( McpAdapter )` cannot detect this: one class loading
 * from our copy says nothing about where the transport/session/handler/error
 * classes will come from later. So we stop relying on load-order luck and
 * register a PREPENDED resolver that serves the ENTIRE `WP\MCP\` namespace
 * from our bundled copy. Registered at plugin-file load — before any other
 * plugin can reference an adapter class — it makes one version authoritative
 * for the whole request. Consistency beats version-maximisation here: a
 * coherent v0.5.0 works, a sheared 0.4.1/0.5.0 mix cannot.
 *
 * Define EMCP_TOOLS_NO_ADAPTER_PRELOAD in wp-config.php to opt out.
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
	 * Root namespace of the MCP Adapter, with trailing separator.
	 */
	const ADAPTER_NAMESPACE = 'WP\\MCP\\';

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
		// Make our bundled copy authoritative for the whole WP\MCP namespace
		// before anything can autoload an adapter class (see the class docblock).
		self::preload_bundled_namespace();

		// Load the Jetpack Autoloader for the adapter's own Composer
		// dependencies (php-mcp-schema, …). Requiring it prepends the Jetpack
		// autoloader, so re-assert our resolver afterwards to keep it
		// front-most and the WP\MCP namespace single-version.
		$autoloader = EMCP_TOOLS_DIR . 'vendor/autoload_packages.php';
		if ( is_readable( $autoloader ) ) {
			require_once $autoloader;
			self::preload_bundled_namespace();
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
	 * Absolute path to the bundled adapter's class root (PSR-4 base).
	 *
	 * `WP\MCP\Transport\HttpTransport` maps to `…/includes/Transport/HttpTransport.php`.
	 *
	 * @since 3.5.1
	 *
	 * @return string Path with a trailing slash.
	 */
	private static function bundled_dir(): string {
		return EMCP_TOOLS_DIR . 'vendor/wordpress/mcp-adapter/includes/';
	}

	/**
	 * Autoloads any `WP\MCP\*` class from OUR bundled adapter copy.
	 *
	 * PHP only ever calls an autoloader for a class that is not yet declared,
	 * so this can never redeclare a class another plugin already loaded — it
	 * simply makes our copy the one that answers first when registered at the
	 * front of the stack.
	 *
	 * @since 3.5.1
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function autoload_bundled( string $class ): void {
		$prefix_len = strlen( self::ADAPTER_NAMESPACE );
		if ( 0 !== strncmp( $class, self::ADAPTER_NAMESPACE, $prefix_len ) ) {
			return;
		}

		$relative = str_replace( '\\', '/', substr( $class, $prefix_len ) );
		$file     = self::bundled_dir() . $relative . '.php';

		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Registers (or re-asserts) our WP\MCP resolver at the FRONT of the
	 * autoload stack.
	 *
	 * Safe and cheap to call repeatedly: it unregisters first, so calling it
	 * again after another autoloader has prepended itself restores our
	 * front-most position. Nothing is eagerly loaded — resolution stays lazy.
	 *
	 * @since 3.5.1
	 */
	public static function preload_bundled_namespace(): void {
		if ( defined( 'EMCP_TOOLS_NO_ADAPTER_PRELOAD' ) && EMCP_TOOLS_NO_ADAPTER_PRELOAD ) {
			return;
		}

		if ( ! is_dir( self::bundled_dir() ) ) {
			return; // No bundled copy to serve (e.g. a stripped install).
		}

		$callback = array( __CLASS__, 'autoload_bundled' );
		spl_autoload_unregister( $callback );
		spl_autoload_register( $callback, true, true ); // throw, prepend.
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
	 * Reports which file each critical adapter class actually resolved from.
	 *
	 * Diagnostic for the mixed-version shear described in the class docblock:
	 * every entry should sit under this plugin's vendor directory. Mirrors the
	 * PHP-Reflection check used to diagnose #99.
	 *
	 * @since 3.5.1
	 *
	 * @return array{consistent:bool,classes:array<string,string>} Map of class => file
	 *               ('' when not loaded), plus whether all loaded ones are ours.
	 */
	public static function loaded_class_report(): array {
		$critical = array(
			'\WP\MCP\Core\McpAdapter',
			'\WP\MCP\Core\McpServer',
			'\WP\MCP\Transport\HttpTransport',
			'\WP\MCP\Transport\Infrastructure\SessionManager',
			'\WP\MCP\Transport\Infrastructure\RequestRouter',
			'\WP\MCP\Transport\Infrastructure\HttpSessionValidator',
			'\WP\MCP\Infrastructure\ErrorHandling\McpErrorFactory',
		);

		$ours       = wp_normalize_path( self::bundled_dir() );
		$consistent = true;
		$classes    = array();

		foreach ( $critical as $class ) {
			if ( ! class_exists( $class, false ) && ! interface_exists( $class, false ) ) {
				$classes[ $class ] = '';
				continue;
			}

			try {
				$file = ( new \ReflectionClass( $class ) )->getFileName();
			} catch ( \Throwable $e ) {
				$file = '';
			}

			$file              = is_string( $file ) ? wp_normalize_path( $file ) : '';
			$classes[ $class ] = $file;

			if ( '' !== $file && 0 !== strpos( $file, $ours ) ) {
				$consistent = false;
			}
		}

		return array(
			'consistent' => $consistent,
			'classes'    => $classes,
		);
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

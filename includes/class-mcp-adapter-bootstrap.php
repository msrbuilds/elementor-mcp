<?php
/**
 * MCP Adapter bootstrap: the official plugin first, EMCP's copy as a fallback.
 *
 * The Abilities API is core in WordPress 6.9+, but core does not expose
 * abilities over MCP: the MCP Adapter creates the `/wp-json/mcp/...` server
 * endpoint. Since 0.7 the adapter is a standalone wordpress.org plugin (slug
 * `mcp-adapter`) and its migration guide deprecates bundling. EMCP still ships
 * a copy, kept current, as a permanent fallback (user decision 2026-10-29), so
 * no site depends on a second plugin:
 *
 * - Official plugin active: EMCP serves the whole `WP\MCP\` namespace from that
 *   plugin's files. Its "another version is already loaded" check then finds
 *   its own classes, and plugins that need 0.7.x get it.
 * - Otherwise: EMCP serves the namespace from its bundled copy, as it always
 *   did. The Connection screen offers the official plugin as an option
 *   (EMCP_Tools_Adapter_Installer); a notice appears only when no adapter
 *   loads, the official plugin is too old or another plugin's copy is in use.
 *
 * Why a resolver at all. PHP can load only one class of a given name per
 * request. A plugin that bundles an old adapter behind its own plain
 * autoloader (Rank Math SEO, Amelia) can answer for some WP\MCP classes while
 * another copy answers for the rest, and that half-and-half runtime fails
 * ("McpServerError: Session terminated", #99). So a PREPENDED resolver,
 * registered at EMCP's file load, serves the whole namespace from one copy.
 *
 * "Loadable" is not "booted": a plugin can autoload the WP\MCP classes without
 * instantiating the adapter (WooCommerce does this unless its MCP feature flag
 * is on). If nobody boots it, `mcp_adapter_init` never fires and our server
 * route is never created. So ensure() boots it (idempotent). It runs on
 * plugins_loaded, after the official plugin has booted itself at file load;
 * booting earlier would make the adapter's autoloader redefine WP_MCP_DIR.
 *
 * Define EMCP_TOOLS_NO_ADAPTER_PRELOAD in wp-config.php to skip the resolver.
 *
 * @package EMCP_Tools
 * @since   1.7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Finds, serves and boots the MCP Adapter.
 *
 * @since 1.7.4
 */
final class EMCP_Tools_Adapter_Bootstrap {

	/** The adapter's wordpress.org slug. */
	const SLUG = 'mcp-adapter';

	/** The official plugin's basename when installed from wordpress.org. */
	const PLUGIN_FILE = 'mcp-adapter/mcp-adapter.php';

	/**
	 * The oldest official plugin EMCP serves from. An older one is left alone
	 * (EMCP's bundled copy serves instead) and the banner asks to update it.
	 */
	const MIN_VERSION = '0.6.1';

	/**
	 * Version of the bundled copy at the last update; bundled_version() reads
	 * the copied source itself.
	 */
	const BUNDLED_VERSION = '0.7.2';

	/** Root namespace of the MCP Adapter, with trailing separator. */
	const ADAPTER_NAMESPACE = 'WP\\MCP\\';

	/**
	 * Where the adapter came from: 'official', 'bundled' (EMCP's fallback
	 * copy), 'external' (another plugin's copy), or 'none'.
	 *
	 * @var string
	 */
	private static $source = 'none';

	/**
	 * The class root the resolver serves from (official or bundled), '' for
	 * none; null until resolved.
	 *
	 * @var string|null
	 */
	private static $dir = null;

	/** @var array|null find_standalone() for the active plugins, cached. */
	private static $official = null;

	/**
	 * Loads EMCP's Jetpack Autoloader (svg-sanitize and the fallback adapter's
	 * schema package) and boots the adapter.
	 *
	 * Runs on plugins_loaded, after every plugin file has loaded.
	 *
	 * @since 1.7.4
	 */
	public static function ensure(): void {
		self::preload_namespace();

		// EMCP's own Composer packages (svg-sanitize). Requiring it prepends the
		// Jetpack autoloader, so re-assert our resolver to keep it front-most.
		$autoloader = EMCP_TOOLS_DIR . 'vendor/autoload_packages.php';
		if ( is_readable( $autoloader ) ) {
			require_once $autoloader;
			self::preload_namespace();
		}

		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			self::$source = 'none';
			return;
		}

		if ( ! self::core_source()['ours'] ) {
			self::$source = 'external';
		} else {
			self::$source = self::uses_official() ? 'official' : 'bundled';
		}

		// Idempotent: a no-op when the official plugin (or another owner)
		// already booted it, and the safety net when another plugin merely
		// autoloaded the classes (WooCommerce with its MCP flag off). Booting
		// only fires mcp_adapter_init; it registers no other plugin's tools.
		if ( class_exists( '\WP\MCP\Plugin' ) ) {
			\WP\MCP\Plugin::instance();
		} elseif ( method_exists( '\WP\MCP\Core\McpAdapter', 'instance' ) ) {
			\WP\MCP\Core\McpAdapter::instance();
		}
	}

	/**
	 * The official plugin among active plugin basenames.
	 *
	 * A basename counts when its main file is `mcp-adapter.php` inside a
	 * plugin folder (a zip upload may name the folder `mcp-adapter-0.7.1`),
	 * the folder holds the adapter's classes, and its Composer autoloader is
	 * there (a source checkout without `composer install` cannot load the
	 * schema package the classes need).
	 *
	 * @since 3.21.0
	 *
	 * @param string[] $active      Active plugin basenames.
	 * @param string   $plugins_dir WP_PLUGIN_DIR.
	 * @return array{plugin?: string, dir?: string, version?: string} Empty when none.
	 */
	public static function find_standalone( array $active, string $plugins_dir ): array {
		$plugins_dir = rtrim( str_replace( '\\', '/', $plugins_dir ), '/' );
		foreach ( $active as $basename ) {
			$basename = str_replace( '\\', '/', (string) $basename );
			$parts    = explode( '/', $basename );
			if ( 2 !== count( $parts ) || 'mcp-adapter.php' !== $parts[1] || '' === $parts[0] || '.' === $parts[0][0] ) {
				continue;
			}
			$root = $plugins_dir . '/' . $parts[0];
			$dir  = $root . '/includes/';
			if ( ! is_readable( $dir . 'Core/McpAdapter.php' ) || ! is_readable( $root . '/vendor/autoload_packages.php' ) ) {
				continue;
			}
			return array(
				'plugin'  => $parts[0],
				'dir'     => $dir,
				'version' => self::version_of( $dir ),
			);
		}
		return array();
	}

	/**
	 * Active plugin basenames, network-activated ones included.
	 *
	 * @since 3.21.0
	 *
	 * @return string[]
	 */
	private static function active_basenames(): array {
		$active = (array) get_option( 'active_plugins', array() );
		if ( function_exists( 'is_multisite' ) && is_multisite() ) {
			$active = array_merge( $active, array_keys( (array) get_site_option( 'active_sitewide_plugins', array() ) ) );
		}
		return array_values( array_filter( $active, 'is_string' ) );
	}

	/**
	 * The active official plugin (see find_standalone()), cached per request.
	 *
	 * @since 3.21.0
	 *
	 * @return array{plugin?: string, dir?: string, version?: string}
	 */
	public static function official(): array {
		if ( null === self::$official ) {
			$plugins      = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : dirname( EMCP_TOOLS_DIR );
			self::$official = self::find_standalone( self::active_basenames(), $plugins );
		}
		return self::$official;
	}

	/**
	 * The adapter version declared in a class root's Core/McpAdapter.php.
	 *
	 * @since 3.21.0
	 *
	 * @param string $dir Class root with a trailing slash.
	 * @return string '' when unreadable.
	 */
	public static function version_of( string $dir ): string {
		$src = @file_get_contents( $dir . 'Core/McpAdapter.php' ); // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions
		return is_string( $src ) && preg_match( "/const\\s+VERSION\\s*=\\s*'([^']+)'/", $src, $m ) ? $m[1] : '';
	}

	/**
	 * Whether the official plugin is active and new enough to serve from.
	 *
	 * @since 3.21.0
	 */
	public static function uses_official(): bool {
		$official = self::official();
		return ! empty( $official['dir'] ) && self::qualifies( (string) $official['version'] );
	}

	/**
	 * Whether an official plugin version is new enough to serve from.
	 *
	 * @since 3.21.0
	 *
	 * @param string $version Its McpAdapter::VERSION ('' when unreadable).
	 */
	public static function qualifies( string $version ): bool {
		return '' !== $version && version_compare( $version, self::MIN_VERSION, '>=' );
	}

	/**
	 * The class root the resolver serves from: the official plugin's when it
	 * qualifies, else EMCP's bundled copy, else ''.
	 *
	 * @since 3.21.0
	 *
	 * @return string Path with a trailing slash, or ''.
	 */
	public static function adapter_dir(): string {
		if ( null === self::$dir ) {
			if ( self::uses_official() ) {
				self::$dir = (string) self::official()['dir'];
			} else {
				self::$dir = is_dir( self::bundled_dir() ) ? self::bundled_dir() : '';
			}
		}
		return self::$dir;
	}

	/**
	 * EMCP's bundled fallback copy's class root.
	 *
	 * @since 3.5.1
	 *
	 * @return string Path with a trailing slash.
	 */
	public static function bundled_dir(): string {
		return EMCP_TOOLS_DIR . 'vendor/wordpress/mcp-adapter/includes/';
	}

	/**
	 * The bundled copy's version, read from its McpAdapter::VERSION.
	 *
	 * @since 3.19.0
	 */
	public static function bundled_version(): string {
		$version = self::version_of( self::bundled_dir() );
		return '' !== $version ? $version : self::BUNDLED_VERSION;
	}

	/**
	 * Test seam: serve the namespace from a given class root (null = detect).
	 *
	 * @since 3.21.0
	 *
	 * @param string|null $dir Class root with a trailing slash.
	 */
	public static function use_adapter_dir( ?string $dir ): void {
		self::$dir      = $dir;
		self::$official = null;
	}

	/**
	 * Autoloads a `WP\MCP\*` class from the chosen copy (adapter_dir()).
	 *
	 * PHP calls an autoloader only for a class that is not declared yet, so
	 * this can never redeclare a class another plugin already loaded.
	 *
	 * @since 3.5.1
	 *
	 * @param string $class Fully-qualified class name.
	 */
	public static function autoload_adapter( string $class ): void {
		$prefix_len = strlen( self::ADAPTER_NAMESPACE );
		if ( 0 !== strncmp( $class, self::ADAPTER_NAMESPACE, $prefix_len ) ) {
			return;
		}
		$dir = self::adapter_dir();
		if ( '' === $dir ) {
			return;
		}
		// The adapter compares McpAdapter::DIR with its own directory using
		// DIRECTORY_SEPARATOR, so require the file by a native path.
		$file = str_replace( array( '/', '\\' ), DIRECTORY_SEPARATOR, $dir . substr( $class, $prefix_len ) . '.php' );
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}

	/**
	 * Registers (or re-asserts) the resolver at the FRONT of the autoload stack.
	 *
	 * Cheap to call repeatedly; nothing is loaded eagerly.
	 *
	 * @since 3.5.1
	 */
	public static function preload_namespace(): void {
		if ( defined( 'EMCP_TOOLS_NO_ADAPTER_PRELOAD' ) && EMCP_TOOLS_NO_ADAPTER_PRELOAD ) {
			return;
		}
		if ( '' === self::adapter_dir() ) {
			return;
		}
		$callback = array( __CLASS__, 'autoload_adapter' );
		spl_autoload_unregister( $callback );
		spl_autoload_register( $callback, true, true ); // throw, prepend.
	}

	/**
	 * Whether the MCP Adapter core class is available (from any copy).
	 *
	 * @since 1.7.4
	 *
	 * @return bool
	 */
	public static function is_loaded(): bool {
		return class_exists( '\WP\MCP\Core\McpAdapter' );
	}

	/**
	 * What the site needs to do about the adapter.
	 *
	 * @since 3.21.0
	 *
	 * @return array{state: string, installed: bool, version: string, plugin: string, fallback: bool}
	 *   state: 'ok' (official plugin active), 'old' (official plugin older than
	 *   MIN_VERSION), 'inactive' (installed, not active), 'missing' (not
	 *   installed). `plugin` names another plugin's copy when that is the one
	 *   loaded; `fallback` is true while EMCP's bundled copy serves.
	 */
	public static function status(): array {
		$official  = self::official();
		$core      = self::core_source();
		$other     = ( ! $core['ours'] && '' !== $core['plugin'] ) ? $core['plugin'] : '';
		$fallback  = ! self::uses_official() && '' !== self::adapter_dir();
		$installed = '' !== self::installed_file();
		if ( ! empty( $official ) ) {
			$version = (string) $official['version'];
			return array(
				'state'     => self::qualifies( $version ) ? 'ok' : 'old',
				'installed' => true,
				'version'   => $version,
				'plugin'    => $other,
				'fallback'  => $fallback,
			);
		}
		return array(
			'state'     => $installed ? 'inactive' : 'missing',
			'installed' => $installed,
			'version'   => (string) $core['version'],
			'plugin'    => $other,
			'fallback'  => $fallback,
		);
	}

	/**
	 * The basename of an installed (active or not) official plugin, or ''.
	 *
	 * @since 3.21.0
	 */
	public static function installed_file(): string {
		$plugins = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : dirname( EMCP_TOOLS_DIR );
		return is_readable( rtrim( $plugins, '/\\' ) . '/' . self::PLUGIN_FILE ) ? self::PLUGIN_FILE : '';
	}

	/**
	 * Reports which file each critical adapter class resolved from.
	 *
	 * Diagnostic for the mixed-copy shear described in the class docblock: with
	 * one copy chosen, every entry should sit under its folder.
	 *
	 * @since 3.5.1
	 *
	 * @return array{consistent:bool,classes:array<string,string>}
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

		$ours       = wp_normalize_path( self::adapter_dir() );
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
			if ( '' !== $file && ( '' === $ours || 0 !== stripos( $file, $ours ) ) ) {
				$consistent = false;
			}
		}

		return array(
			'consistent' => $consistent,
			'classes'    => $classes,
		);
	}

	/**
	 * Where the adapter was loaded from: 'official', 'bundled', 'external', or 'none'.
	 *
	 * @since 1.7.4
	 *
	 * @return string
	 */
	public static function source(): string {
		return self::$source;
	}

	/**
	 * Which copy of McpAdapter a file is: the plugin folder it sits in and
	 * whether it is the copy EMCP chose.
	 *
	 * @since 3.19.0
	 *
	 * @param string $file        The class file ('' when unknown).
	 * @param string $plugins_dir WP_PLUGIN_DIR.
	 * @param string $ours_dir    The class root EMCP chose (adapter_dir()).
	 * @param string $version     The loaded McpAdapter::VERSION.
	 * @return array{file: string, version: string, ours: bool, plugin: string}
	 */
	public static function source_from_file( string $file, string $plugins_dir, string $ours_dir, string $version ): array {
		$norm    = static fn( string $p ) => str_replace( '\\', '/', $p );
		$file    = $norm( $file );
		$plugins = rtrim( $norm( $plugins_dir ), '/' ) . '/';
		$ours    = $norm( $ours_dir );
		$plugin  = '';
		$rel     = $file;
		if ( '' !== $file && 0 === stripos( $file, $plugins ) ) {
			$rel    = substr( $file, strlen( $plugins ) );
			$plugin = (string) strtok( $rel, '/' );
		}
		return array(
			'file'    => $rel,
			'version' => $version,
			'ours'    => '' !== $file && '' !== $ours && 0 === stripos( $file, $ours ),
			'plugin'  => $plugin,
		);
	}

	/**
	 * Where the loaded McpAdapter class came from.
	 *
	 * @since 3.19.0
	 *
	 * @return array{file: string, version: string, ours: bool, plugin: string}
	 */
	public static function core_source(): array {
		$class = '\WP\MCP\Core\McpAdapter';
		$file  = '';
		$ver   = '';
		if ( class_exists( $class, false ) ) {
			try {
				$file = (string) ( new \ReflectionClass( $class ) )->getFileName();
			} catch ( \Throwable $e ) {
				$file = '';
			}
			$ver = defined( $class . '::VERSION' ) ? (string) constant( $class . '::VERSION' ) : '';
		}
		$plugins = defined( 'WP_PLUGIN_DIR' ) ? WP_PLUGIN_DIR : dirname( EMCP_TOOLS_DIR );
		return self::source_from_file( $file, $plugins, self::adapter_dir(), $ver );
	}
}

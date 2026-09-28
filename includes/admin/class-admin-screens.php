<?php
/**
 * React screen registry, boot payload and per-screen enqueue (spec 5.2).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registry of React admin screens.
 */
final class EMCP_Tools_Admin_Screens {

	/** Pro-only tabs and the Pro file that renders them; missing file means locked (spec 8.25). */
	const LOCKED_TABS = array(
		'ai-chat'         => 'includes/admin/data/class-admin-ai-chat-data.php',
		'skills'          => 'includes/admin/rest/class-admin-rest-skills.php',
		'skills:custom'   => 'includes/admin/data/class-admin-custom-skills-data.php',
		'memory'          => 'includes/admin/rest/class-admin-rest-memory.php',
		'migrate'         => 'includes/admin/rest/class-admin-rest-backup.php',
		'templates'       => 'includes/admin/rest/class-admin-rest-templates.php',
		// Sandbox child views (tab:view). Widgets and Blocks own no Pro PHP file,
		// so their built Pro bundle is what a free build lacks.
		'widgets:widgets' => 'assets/admin/build-pro/screen-sandbox-widgets.asset.php',
		'widgets:blocks'  => 'assets/admin/build-pro/screen-sandbox-blocks.asset.php',
		'widgets:export'  => 'includes/admin/rest/class-admin-rest-sandbox-export.php',
	);

	/** Pro-only tabs that also need an active licence; unlicensed means locked (spec 8.25). */
	const LICENCE_TABS = array( 'ai-chat', 'templates', 'skills', 'skills:custom', 'memory', 'migrate', 'widgets:widgets', 'widgets:blocks', 'widgets:export' );

	/** Warn (under WP_DEBUG) when a screen's boot data grows past this (spec 5.2). */
	const MAX_PAYLOAD_BYTES = 153600;

	/**
	 * Registered screens.
	 *
	 * @var array<string,array>
	 */
	private static $screens = array();

	/**
	 * Register a screen.
	 *
	 * @param string $id   Screen id.
	 * @param array  $args { script, root ('free'|'pro'), boot callable, tabs string[] }.
	 */
	public static function register( string $id, array $args ): void {
		self::$screens[ $id ] = array_merge(
			array(
				'script' => '',
				'root'   => 'free',
				'boot'    => '__return_empty_array',
				'tabs'    => array(),
				'when'    => null,
				'enqueue' => null,
			),
			$args
		);
	}

	/**
	 * A registered screen, or null.
	 *
	 * @param string $id Screen id.
	 */
	public static function get( string $id ): ?array {
		return self::$screens[ $id ] ?? null;
	}

	/**
	 * Forget every screen (tests only).
	 */
	public static function reset_for_tests(): void {
		self::$screens = array();
	}

	/**
	 * Script handle of a screen.
	 *
	 * @param string $id Screen id.
	 */
	public static function handle( string $id ): string {
		return 'emcp-screen-' . $id;
	}

	/**
	 * Which React screen renders a tab (or a tab's ?view= child), or null for a legacy view.
	 *
	 * @param string        $tab             Tab id.
	 * @param callable|null $pro_view_exists ( string $rel ): bool, injectable for tests.
	 * @param string        $view            The ?view= child, '' for none.
	 */
	public static function screen_for_tab( string $tab, ?callable $pro_view_exists = null, string $view = '' ): ?string {
		$exists = $pro_view_exists ?? static function ( string $rel ): bool {
			return class_exists( 'EMCP_Tools_Pro_Loader' ) && '' !== EMCP_Tools_Pro_Loader::path( $rel );
		};
		$key = self::route_key( $tab, $view );
		if ( $key !== $tab ) {
			$screen = self::resolve( $key, $exists );
			if ( false !== $screen ) {
				return $screen;
			}
		}
		$screen = self::resolve( $tab, $exists );
		return false === $screen ? null : $screen;
	}

	/**
	 * `tab:view` when the registry knows that key, else the tab.
	 *
	 * @param string $tab  Tab id.
	 * @param string $view View.
	 */
	public static function route_key( string $tab, string $view ): string {
		if ( '' === $view ) {
			return $tab;
		}
		$key   = $tab . ':' . $view;
		$known = isset( self::LOCKED_TABS[ $key ] ) || in_array( $key, self::LICENCE_TABS, true ) || null !== self::registered_for( $key );
		return $known ? $key : $tab;
	}

	/** The ?view= of the current admin request, sanitised. */
	public static function current_view(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only routing.
		return isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : '';
	}

	/**
	 * Run a screen's own enqueue callable (for example the snippet code editor).
	 *
	 * @param string $id Screen id.
	 */
	public static function run_enqueue_hook( string $id ): void {
		$screen = self::get( $id );
		if ( $screen && is_callable( $screen['enqueue'] ) ) {
			call_user_func( $screen['enqueue'] );
		}
	}

	/**
	 * Whether a React screen is registered for a route key.
	 *
	 * @param string $key Tab or tab:view.
	 */
	public static function is_registered( string $key ): bool {
		return null !== self::registered_for( $key );
	}

	/**
	 * The screen registered for a route key, or null.
	 *
	 * @param string $key Tab or tab:view.
	 */
	private static function registered_for( string $key ): ?string {
		foreach ( self::$screens as $id => $screen ) {
			if ( in_array( $key, (array) $screen['tabs'], true ) ) {
				return $id;
			}
		}
		return null;
	}

	/**
	 * Resolve one route key.
	 *
	 * @param string   $key    Tab or tab:view.
	 * @param callable $exists Pro file check.
	 * @return string|null|false Screen id, null for a legacy view, false when the
	 *                           screen's condition sends the request back to its tab.
	 */
	private static function resolve( string $key, callable $exists ) {
		$id = self::registered_for( $key );
		if ( null !== $id && is_callable( self::$screens[ $id ]['when'] ) && ! call_user_func( self::$screens[ $id ]['when'] ) ) {
			return false;
		}
		if ( isset( self::LOCKED_TABS[ $key ] ) && ! $exists( self::LOCKED_TABS[ $key ] ) ) {
			return 'locked';
		}
		if ( in_array( $key, self::LICENCE_TABS, true ) && ! ( function_exists( 'emcp_tools_fs' ) && emcp_tools_fs()->can_use_premium_code() ) ) {
			return 'locked';
		}
		return $id;
	}

	/**
	 * The screen's boot payload: common data plus the screen's own data.
	 *
	 * @param string $id      Screen id.
	 * @param array  $common  Common payload (version, tier, user, site, flags).
	 * @param array  $context Request context passed to the boot callable.
	 */
	public static function boot_payload( string $id, array $common, array $context = array() ): array {
		$screen = self::get( $id );
		$data   = $screen ? call_user_func( $screen['boot'], $context ) : array();
		return array_merge(
			$common,
			array(
				'screen' => $id,
				'data'   => $data,
			)
		);
	}

	/**
	 * The inline script that assigns window.emcpBoot.
	 *
	 * @param array $payload Boot payload.
	 */
	public static function boot_script( array $payload ): string {
		$json = wp_json_encode( $payload, JSON_HEX_TAG | JSON_HEX_AMP );
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && is_string( $json ) && strlen( $json ) > self::MAX_PAYLOAD_BYTES ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'EMCP Tools: boot payload for "%s" is %d bytes; fetch large data after mount.', $payload['screen'] ?? '?', strlen( $json ) ) );
		}
		return 'window.emcpBoot = ' . ( is_string( $json ) ? $json : '{}' ) . ';';
	}

	/**
	 * Register and enqueue a screen's bundle with its boot payload.
	 *
	 * @param string $id      Screen id.
	 * @param array  $common  Common payload.
	 * @param array  $context Request context.
	 */
	public static function enqueue( string $id, array $common, array $context = array() ): bool {
		$screen = self::get( $id );
		if ( ! $screen || '' === $screen['script'] ) {
			return false;
		}
		$paths = self::paths( $screen );
		if ( ! $paths || '' === $paths['asset'] || ! is_readable( $paths['asset'] ) ) {
			return false;
		}
		$asset   = include $paths['asset'];
		$version = (string) ( $asset['version'] ?? EMCP_TOOLS_VERSION );
		$deps    = array_values( array_unique( array_merge( (array) ( $asset['dependencies'] ?? array() ), array( 'emcp-admin-ui' ) ) ) );
		$handle  = self::handle( $id );
		self::run_enqueue_hook( $id );
		wp_register_script( $handle, $paths['url'], $deps, $version, true );
		wp_add_inline_script( $handle, self::boot_script( self::boot_payload( $id, $common, $context ) ), 'before' );
		wp_set_script_translations( $handle, 'emcp-tools' );
		wp_enqueue_script( $handle );
		if ( '' !== $paths['css'] && is_readable( $paths['css'] ) ) {
			wp_enqueue_style( $handle, $paths['css_url'], array( 'emcp-admin-ui' ), $version );
		}
		return true;
	}

	/**
	 * File paths and URLs of a screen's build output.
	 *
	 * @param array $screen Registered screen.
	 * @return array{asset:string,url:string,css:string,css_url:string}|null
	 */
	private static function paths( array $screen ): ?array {
		$base = $screen['script'];
		if ( 'pro' === $screen['root'] ) {
			if ( ! class_exists( 'EMCP_Tools_Pro_Loader' ) ) {
				return null;
			}
			$rel = 'assets/admin/build-pro/' . $base;
			return array(
				'asset'   => EMCP_Tools_Pro_Loader::path( $rel . '.asset.php' ),
				'url'     => EMCP_Tools_Pro_Loader::url( $rel . '.js' ),
				'css'     => EMCP_Tools_Pro_Loader::path( $rel . '.css' ),
				'css_url' => EMCP_Tools_Pro_Loader::url( $rel . '.css' ),
			);
		}
		return array(
			'asset'   => EMCP_TOOLS_DIR . 'assets/admin/build/' . $base . '.asset.php',
			'url'     => EMCP_TOOLS_URL . 'assets/admin/build/' . $base . '.js',
			'css'     => EMCP_TOOLS_DIR . 'assets/admin/build/' . $base . '.css',
			'css_url' => EMCP_TOOLS_URL . 'assets/admin/build/' . $base . '.css',
		);
	}
}

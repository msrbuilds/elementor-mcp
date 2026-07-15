<?php
/**
 * Loads Pro-tier units only when the private `pro/` overlay is present.
 *
 * The free plugin ships without any Pro file. Every Pro require + every Pro
 * instantiation/hook-wire goes through here, each guarded by file_exists /
 * class_exists, so the free plugin runs with zero Pro references and no fatals.
 * The runtime can_use_premium_code() license gate remains inside each Pro unit.
 *
 * Dual-root path resolution lets the same code serve two layouts:
 *   1. EMCP_TOOLS_DIR . $rel          — premium BUILD (pro/* overlaid onto plugin paths)
 *   2. EMCP_TOOLS_DIR . 'pro/' . $rel — DEV checkout (private submodule at pro/)
 * so developers edit files in pro/ directly with no in-place copies or sync.
 *
 * @package EMCP_Tools
 */

defined( 'ABSPATH' ) || exit;

final class EMCP_Tools_Pro_Loader {

	/** Pro class files, in load order. Relative to the Pro root. */
	private const FILES = array(
		'includes/class-color-contrast.php',
		'includes/class-content-extractor.php',
		'includes/class-seo-meta.php',
		'includes/class-widget-generator.php',
		'includes/abilities/class-system-kit-abilities.php',
		'includes/abilities/class-widget-builder-abilities.php',
		'includes/abilities/class-seo-abilities.php',
		'includes/abilities/class-a11y-abilities.php',
		'includes/class-skill-catalog.php',
		'includes/abilities/class-skill-abilities.php',
		'includes/class-page-snapshot-pro.php',
		'includes/admin/class-pro-brand-kits.php',
		'includes/ai-chat/class-key-crypto.php',
		'includes/ai-chat/class-ai-providers.php',
		'includes/ai-chat/class-ai-chat-provider.php',
		'includes/ai-chat/class-ai-chat-store.php',
		'includes/ai-chat/class-ai-chat-tool-groups.php',
		'includes/ai-chat/class-ai-chat-prompt.php',
		'includes/ai-chat/class-ai-chat-web-fetch.php',
		'includes/ai-chat/class-ai-chat-controller.php',
		'includes/themer/class-themer-pro-matchers.php',
		'includes/themer/class-themer-pro-conditions.php',
		'includes/themer/class-themer-pro.php',
	);

	/** Pro admin class files, in load order. Relative to the Pro root. */
	private const ADMIN_FILES = array(
		'includes/ai-chat/class-ai-chat-page.php',
		'includes/ai-chat/class-elementor-editor.php',
		'includes/ai-chat/class-gutenberg-editor.php',
		'includes/admin/class-pro-prompts.php',
		'includes/admin/class-pro-templates.php',
		'includes/admin/class-pro-usage.php',
		'includes/admin/class-pro-ajax.php',
		'includes/admin/class-pro-skills.php',
	);

	/**
	 * Resolve a Pro-relative path against the two candidate roots (build root
	 * first so an overlaid premium build wins).
	 *
	 * @param string $rel Path relative to the Pro root.
	 * @return string Absolute path, or '' when the file exists in neither root.
	 */
	public static function path( string $rel ): string {
		foreach ( array( EMCP_TOOLS_DIR . $rel, EMCP_TOOLS_DIR . 'pro/' . $rel ) as $candidate ) {
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}
		return '';
	}

	/**
	 * URL twin of path() — for enqueuing Pro assets (ai-chat.js/css).
	 * Uses EMCP_TOOLS_URL (emcp-tools.php); there is no EMCP_TOOLS_FILE constant.
	 *
	 * @param string $rel Path relative to the Pro root.
	 * @return string Asset URL, or '' when the asset exists in neither root.
	 */
	public static function url( string $rel ): string {
		if ( is_readable( EMCP_TOOLS_DIR . $rel ) ) {
			return EMCP_TOOLS_URL . $rel;
		}
		if ( is_readable( EMCP_TOOLS_DIR . 'pro/' . $rel ) ) {
			return EMCP_TOOLS_URL . 'pro/' . $rel;
		}
		return '';
	}

	/**
	 * Cache-busting version for a Pro asset: its modification time.
	 *
	 * Keyed on the file, not on EMCP_TOOLS_VERSION, so an edited asset is picked
	 * up without a version bump. Previously this fell back to the plugin version
	 * unless WP_DEBUG was on, which meant an unreleased JS/CSS change was served
	 * from the browser cache on any non-debug install — including a dev site.
	 *
	 * On a released install the mtime is the install/extract time and still
	 * changes on every update, so this is also correct in production.
	 *
	 * @since 3.2.0
	 * @param string $rel Path relative to the Pro root.
	 * @return string Version string.
	 */
	public static function asset_version( string $rel ): string {
		$path = self::path( $rel );
		if ( '' !== $path ) {
			$mtime = @filemtime( $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Falls back below.
			if ( $mtime ) {
				return (string) $mtime;
			}
		}
		return EMCP_TOOLS_VERSION;
	}

	/** True when the private Pro overlay is present (either root). */
	public static function is_present(): bool {
		return '' !== self::path( 'includes/ai-chat/class-ai-chat-controller.php' );
	}

	/** require_once each Pro runtime file that exists (dual-root). */
	public static function load_runtime(): void {
		foreach ( self::FILES as $rel ) {
			$path = self::path( $rel );
			if ( '' !== $path ) {
				require_once $path;
			}
		}
	}

	/** require_once each Pro admin file that exists (dual-root). */
	public static function load_admin(): void {
		foreach ( self::ADMIN_FILES as $rel ) {
			$path = self::path( $rel );
			if ( '' !== $path ) {
				require_once $path;
			}
		}
	}

	/** Wire Pro runtime hooks, each guarded by class_exists. */
	public static function wire_runtime_hooks(): void {
		// AI Chat runtime wiring now lives in EMCP_Tools_AI_Chat_Module::register(),
		// booted by the modules registry only when the module is active.

		// EMCP Themer Pro power-ups: attach granular matchers, priority ranking,
		// unlimited quota, and granular selectors to the free seams (license-gated).
		if ( class_exists( 'EMCP_Tools_Themer_Pro' ) ) {
			EMCP_Tools_Themer_Pro::init();
		}

		// Agent-facing skills (read-side): hook the discovery-context catalog.
		if ( class_exists( 'EMCP_Tools_Skill_Catalog' ) ) {
			EMCP_Tools_Skill_Catalog::init();
		}

		// Page-snapshot Pro sections (a11y + deep seo) attach to the free seam.
		if ( class_exists( 'EMCP_Tools_Page_Snapshot_Pro' ) ) {
			EMCP_Tools_Page_Snapshot_Pro::init();
		}
	}

	/** Wire Pro admin hooks, each guarded by class_exists. */
	public static function wire_admin_hooks(): void {
		// AI Chat admin page + Elementor editor are wired by the AI Chat module.
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return;
		}
		if ( class_exists( 'EMCP_Tools_Pro_Ajax' ) ) {
			EMCP_Tools_Pro_Ajax::register();
		}
		if ( class_exists( 'EMCP_Tools_Pro_Prompts' ) && method_exists( 'EMCP_Tools_Pro_Prompts', 'register_download' ) ) {
			EMCP_Tools_Pro_Prompts::register_download();
		}
		if ( class_exists( 'EMCP_Tools_Pro_Skills' ) ) {
			( new EMCP_Tools_Pro_Skills() )->init();
		}
	}

	/**
	 * Let Pro register its modules into the shared registry. No-op today (no Pro
	 * modules yet); the private overlay will instantiate + register them here,
	 * each guarded by class_exists, when the first Pro module lands.
	 *
	 * @param EMCP_Tools_Modules_Registry $registry The shared registry.
	 */
	public static function register_modules( EMCP_Tools_Modules_Registry $registry ): void {
		$path = self::path( 'includes/modules/class-ai-chat-module.php' );
		if ( '' !== $path ) {
			require_once $path;
			if ( class_exists( 'EMCP_Tools_AI_Chat_Module' ) ) {
				$registry->register( new EMCP_Tools_AI_Chat_Module() );
			}
		}
	}
}

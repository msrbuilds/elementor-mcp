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
		'includes/admin/class-pro-brand-kits.php',
		'includes/ai-chat/class-key-crypto.php',
		'includes/ai-chat/class-ai-providers.php',
		'includes/ai-chat/class-ai-chat-provider.php',
		'includes/ai-chat/class-ai-chat-store.php',
		'includes/ai-chat/class-ai-chat-controller.php',
	);

	/** Pro admin class files, in load order. Relative to the Pro root. */
	private const ADMIN_FILES = array(
		'includes/ai-chat/class-ai-chat-page.php',
		'includes/admin/class-pro-prompts.php',
		'includes/admin/class-pro-templates.php',
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
		if ( class_exists( 'EMCP_Tools_AI_Chat_Controller' ) ) {
			( new EMCP_Tools_AI_Chat_Controller() )->register_hooks();
		}
		if ( class_exists( 'EMCP_Tools_AI_Chat_Provider' ) ) {
			add_action(
				EMCP_Tools_AI_Chat_Provider::REFRESH_HOOK,
				array( 'EMCP_Tools_AI_Chat_Provider', 'cron_refresh' )
			);
		}
		if ( class_exists( 'EMCP_Tools_AI_Chat_Store' ) ) {
			add_action( 'init', array( 'EMCP_Tools_AI_Chat_Store', 'register_post_type' ) );
		}
	}

	/** Wire Pro admin hooks, each guarded by class_exists (mirrors the old bootstrap order). */
	public static function wire_admin_hooks(): void {
		if ( class_exists( 'EMCP_Tools_AI_Chat_Page' ) ) {
			( new EMCP_Tools_AI_Chat_Page() )->init();
		}
		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return;
		}
		if ( class_exists( 'EMCP_Tools_Pro_Ajax' ) ) {
			EMCP_Tools_Pro_Ajax::register();
		}
		if ( class_exists( 'EMCP_Tools_Pro_Skills' ) ) {
			( new EMCP_Tools_Pro_Skills() )->init();
		}
	}
}

<?php
/**
 * Plugin bootstrap: dependency check, class loading, and hook wiring.
 *
 * Hooked to `plugins_loaded` (priority 20) by the main plugin file. Everything
 * here is orchestration — loading class files and wiring them together — not
 * feature logic, which lives in the loaded classes.
 *
 * @package EMCP_Tools
 * @since   2.1.0 (extracted from emcp_tools_init)
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Loads and wires the plugin.
 *
 * @since 2.1.0
 */
class EMCP_Tools_Bootstrap {

	/**
	 * Boots the plugin: runs the fallback migration, makes the MCP Adapter
	 * available, checks dependencies, then loads classes and wires hooks.
	 *
	 * @since 2.1.0 (since 1.0.0 as emcp_tools_init)
	 */
	public static function boot(): void {
		// Fallback legacy-data migration (the primary snapshot happens in the
		// legacy guard while the old plugin is still present). Idempotent.
		EMCP_Tools_Migration::migrate();

		// Make the MCP Adapter available (active standalone plugin, else our
		// bundled copy) BEFORE the dependency check, so the adapter is never a
		// "go install this" blocker. The Abilities API is core in WP 6.9+/7.0.
		require_once EMCP_TOOLS_DIR . 'includes/class-mcp-adapter-bootstrap.php';
		EMCP_Tools_Adapter_Bootstrap::ensure();

		if ( ! self::check_dependencies() ) {
			return;
		}

		self::load_classes();
		self::wire_hooks();

		if ( is_admin() ) {
			self::load_admin();
		}

		// Boot the plugin singleton.
		EMCP_Tools_Plugin::instance();
	}

	/**
	 * Loads all class files (core, data, abilities, features). Self-guarded
	 * feature groups (Pro / atomic) are loaded unconditionally; they no-op on
	 * registration when their gate isn't met.
	 *
	 * @since 2.1.0
	 */
	private static function load_classes(): void {
		// Schema compatibility + the emcp_tools_register_ability() entry point
		// must load before any ability group registers.
		require_once EMCP_TOOLS_DIR . 'includes/class-schema-compat.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-id-generator.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-url-guard.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-elementor-data.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-element-factory.php';
		require_once EMCP_TOOLS_DIR . 'includes/schemas/class-control-mapper.php';
		require_once EMCP_TOOLS_DIR . 'includes/schemas/class-schema-generator.php';
		require_once EMCP_TOOLS_DIR . 'includes/validators/class-element-validator.php';
		require_once EMCP_TOOLS_DIR . 'includes/validators/class-settings-validator.php';
		// Widget catalog — source of truth for the 5 catalog-backed widget tools.
		require_once EMCP_TOOLS_DIR . 'includes/widgets/class-widget-catalog.php';
		// SEO / A11y toolkit shared helpers (used by the Pro audit abilities).
		require_once EMCP_TOOLS_DIR . 'includes/class-color-contrast.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-content-extractor.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-seo-meta.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-query-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-page-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-layout-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-widget-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-template-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-global-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-composite-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-openverse-client.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-stock-image-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-media-library-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-content-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-svg-icon-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-custom-code-abilities.php';
		// Brand Kits (Pro). The writer + backup store + fetcher + abilities load
		// unconditionally (no admin dependency) so the MCP REST/CLI/proxy surface
		// can reach them; every write method is independently Pro-gated.
		require_once EMCP_TOOLS_DIR . 'includes/class-system-kit-writer.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-kit-backup-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-free-brand-kits.php';
		require_once EMCP_TOOLS_DIR . 'includes/admin/class-pro-brand-kits.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-system-kit-abilities.php';
		// Widget Builder (Pro) — sandboxed AI-generated Elementor widgets. The
		// generator/store/loader load unconditionally so the MCP surface can reach
		// them; every write + the loader itself are independently Pro-gated.
		require_once EMCP_TOOLS_DIR . 'includes/class-widget-generator.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-widget-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-widget-loader.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-widget-builder-abilities.php';
		// PHP Code Snippets (Sandbox) — free, capability-gated. AI can author +
		// validate drafts via MCP; only an admin can activate. The loader runs
		// ACTIVE snippets (hash-verified, fatal-isolated).
		require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-validator.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-loader.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-php-snippet-abilities.php';
		// SEO toolkit abilities (Pro only; self-guards on license at registration).
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-seo-abilities.php';
		// Accessibility toolkit abilities (Pro only; self-guards on license).
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-a11y-abilities.php';
		// Atomic elements support (Elementor 4.0+).
		require_once EMCP_TOOLS_DIR . 'includes/class-atomic-props.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-atomic-styles.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-atomic-widget-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-atomic-layout-abilities.php';
		// Global Classes (Class Manager) reader — self-gates on Elementor 4.0+.
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-global-classes-abilities.php';
		// Background library refresh.
		require_once EMCP_TOOLS_DIR . 'includes/class-library-refresher.php';

		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-ability-registrar.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-plugin.php';
	}

	/**
	 * Registers the non-admin runtime hooks shared by all contexts (post types,
	 * sandbox loaders, background library refresh).
	 *
	 * @since 2.1.0
	 */
	private static function wire_hooks(): void {
		add_action( 'init', array( 'EMCP_Tools_Kit_Backup_Store', 'register_post_type' ) );
		add_action( 'init', array( 'EMCP_Tools_Widget_Store', 'register_post_type' ) );
		( new EMCP_Tools_Widget_Loader() )->register_hooks();
		add_action( 'init', array( 'EMCP_Tools_PHP_Snippet_Store', 'register_post_type' ) );
		( new EMCP_Tools_PHP_Snippet_Loader() )->register_hooks();
		// Background refresh of the Pro Prompts / Brand Kits libraries — registered
		// unconditionally (cron runs in a non-admin context) so an expired 24h
		// cache self-heals without the user clicking "Sync Library".
		EMCP_Tools_Library_Refresher::register();
	}

	/**
	 * Loads admin-only classes and wires the Pro library admin-ajax handlers.
	 *
	 * @since 2.1.0
	 */
	private static function load_admin(): void {
		require_once EMCP_TOOLS_DIR . 'includes/admin/class-admin.php';

		if ( ! function_exists( 'emcp_tools_fs' ) ) {
			return;
		}

		require_once EMCP_TOOLS_DIR . 'includes/admin/class-pro-prompts.php';
		require_once EMCP_TOOLS_DIR . 'includes/admin/class-pro-templates.php';
		require_once EMCP_TOOLS_DIR . 'includes/admin/class-pro-ajax.php';
		EMCP_Tools_Pro_Ajax::register();

		require_once EMCP_TOOLS_DIR . 'includes/admin/class-pro-skills.php';
		( new EMCP_Tools_Pro_Skills() )->init();

		require_once EMCP_TOOLS_DIR . 'includes/admin/class-upgrade-notice.php';
		( new EMCP_Tools_Upgrade_Notice() )->init();

		// Facebook community banner — only renders once the upgrade banner is out
		// of the way (Pro users, or free users who dismissed it), so we never
		// stack two banners on the dashboard.
		require_once EMCP_TOOLS_DIR . 'includes/admin/class-community-notice.php';
		( new EMCP_Tools_Community_Notice() )->init();
	}

	/**
	 * Checks that all required dependencies are available, queuing an admin
	 * notice listing anything missing.
	 *
	 * @since 2.1.0 (since 1.0.0 as emcp_tools_check_dependencies)
	 *
	 * @return bool True if all dependencies are met.
	 */
	private static function check_dependencies(): bool {
		// PHP 8.2+ is required. Elementor 4.0+ uses 8.1+ features that silently
		// fail on older PHP (writes no-op, _elementor_data never persists).
		// WordPress only enforces Requires PHP at activation, not on every load —
		// so we re-check here to surface a clear admin notice if the host
		// downgraded PHP after the plugin was already installed.
		if ( version_compare( PHP_VERSION, '8.2', '<' ) ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return;
					}
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						sprintf(
							/* translators: %s: current PHP version */
							esc_html__( 'EMCP Tools requires PHP 8.2 or higher. Your server is running PHP %s — please upgrade PHP to avoid silent Elementor write failures.', 'emcp-tools' ),
							esc_html( PHP_VERSION )
						)
					);
				}
			);
			return false;
		}

		$missing = array();

		// Elementor must be active.
		if ( ! did_action( 'elementor/loaded' ) ) {
			$missing[] = 'Elementor';
		}

		// WordPress Abilities API must be available. Core in WordPress 6.9+ (and
		// 7.0); only missing on older WordPress, which the plugin doesn't support.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			$missing[] = 'WordPress Abilities API (requires WordPress 6.9+)';
		}

		// MCP Adapter: bundled with the plugin (EMCP_Tools_Adapter_Bootstrap::ensure()
		// ran above). Only fails if the bundled source is missing/corrupt — a
		// broken build, not a user action.
		if ( ! class_exists( '\WP\MCP\Core\McpAdapter' ) ) {
			$missing[] = 'WordPress MCP Adapter (bundled — reinstall the plugin if this persists)';
		}

		if ( ! empty( $missing ) ) {
			add_action(
				'admin_notices',
				function () use ( $missing ) {
					$list = implode( ', ', $missing );
					printf(
						'<div class="notice notice-error"><p>%s</p></div>',
						sprintf(
							/* translators: %s: comma-separated list of missing dependencies */
							esc_html__( 'MCP Tools for Elementor requires the following to be installed and active: %s', 'emcp-tools' ),
							'<strong>' . esc_html( $list ) . '</strong>'
						)
					);
				}
			);

			return false;
		}

		return true;
	}
}

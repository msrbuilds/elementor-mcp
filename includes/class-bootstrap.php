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
	 * Whether Elementor is loaded/active in this request.
	 *
	 * Single source of truth for the optional-Elementor gate: the tool registrar,
	 * the admin Tools page, and the Brand Kits / Templates tabs all read this.
	 *
	 * @since 3.0.0
	 *
	 * @return bool
	 */
	public static function elementor_active(): bool {
		return (bool) did_action( 'elementor/loaded' );
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
		require_once EMCP_TOOLS_DIR . 'includes/class-site-context.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-elementor-data.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-element-factory.php';
		require_once EMCP_TOOLS_DIR . 'includes/schemas/class-control-mapper.php';
		require_once EMCP_TOOLS_DIR . 'includes/schemas/class-schema-generator.php';
		require_once EMCP_TOOLS_DIR . 'includes/validators/class-element-validator.php';
		require_once EMCP_TOOLS_DIR . 'includes/validators/class-settings-validator.php';
		// Widget catalog — source of truth for the 5 catalog-backed widget tools.
		require_once EMCP_TOOLS_DIR . 'includes/widgets/class-widget-catalog.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-query-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-page-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-layout-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-widget-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-template-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-global-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-composite-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-secret.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-unsplash-client.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-pexels-client.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-pixabay-client.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-stock-image-providers.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-stock-image-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-media-library-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-image-resize-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-block-tree.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-gutenberg-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-page-snapshot.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-snapshot-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-change-log.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-transaction-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-search-ranker.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-search-index.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-search-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-admin-bar.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-github-updater.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-content-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-dispatcher-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-settings-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-package-guard.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-plugin-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-theme-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-user-abilities.php';
		// ACF tools (field values + field group discovery/authoring; writes off by default).
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-acf-abilities.php';
		// Performance Analyzer (v3.0.0) — read-only server/WP/page audit.
		require_once EMCP_TOOLS_DIR . 'includes/performance/class-performance-finding.php';
		require_once EMCP_TOOLS_DIR . 'includes/performance/class-performance-server-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/performance/class-performance-page-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/performance/class-performance-analyzer.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-performance-abilities.php';
		// Filesystem tools (read/scan + write/edit/delete; writes off by default).
		require_once EMCP_TOOLS_DIR . 'includes/class-filesystem-guard.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-filesystem-abilities.php';
		// Database tools (read-only query + structured writes; writes off by default).
		require_once EMCP_TOOLS_DIR . 'includes/class-database-guard.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-database-abilities.php';
		// Security & Malware Scanner (v3.0.0) — read-only multi-audit scan.
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-finding.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-malware-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-integrity-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-hardening-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-software-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-scanner.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-security-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-svg-icon-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-custom-code-abilities.php';
		// Brand Kits. The free writer + backup store + free-kit fetcher load
		// unconditionally so the MCP REST/CLI/proxy surface can reach them. The
		// Pro brand-kit admin + system-kit abilities live in the private Pro
		// overlay and are loaded by EMCP_Tools_Pro_Loader below.
		require_once EMCP_TOOLS_DIR . 'includes/class-system-kit-writer.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-kit-backup-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-free-brand-kits.php';
		// Widget Builder infra (free base). The store + loader load unconditionally
		// so the MCP surface + CPT registration can reach them; the generator +
		// builder abilities ship in the Pro overlay (loaded via Pro_Loader).
		require_once EMCP_TOOLS_DIR . 'includes/class-widget-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-widget-loader.php';
		// PHP Code Snippets (Sandbox) — free, capability-gated. AI can author +
		// validate drafts via MCP; only an admin can activate. The loader runs
		// ACTIVE snippets (hash-verified, fatal-isolated).
		require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-validator.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-loader.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-php-snippet-abilities.php';
		// Atomic elements support (Elementor 4.0+).
		require_once EMCP_TOOLS_DIR . 'includes/class-atomic-props.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-atomic-styles.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-atomic-widget-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-atomic-layout-abilities.php';
		// Global Classes (Class Manager) reader — self-gates on Elementor 4.0+.
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-global-classes-abilities.php';
		// Background library refresh.
		require_once EMCP_TOOLS_DIR . 'includes/class-library-refresher.php';
		// Modules framework (free) + built-in modules. The registry boots active
		// modules on `init`; each module self-gates on its options + availability.
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-module.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-modules-registry.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/image-optimization/class-webp-generator.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/image-optimization/class-image-optimizer.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/image-optimization/class-webp-rewriter.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/image-optimization/class-bulk-optimizer.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/image-optimization/class-image-resizer.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/image-optimization/class-image-optimization-module.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-prompts-module.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-brand-kits-module.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-templates-module.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-agent-skills-module.php';
		// EMCP Themer (free): builder-agnostic theme builder engine + module + MCP tools.
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-matcher-registry.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-conditions.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-context.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-index.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-resolver.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-condition-schema.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-cpt.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-content-renderer.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-theme-adapters.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-render-controller.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-dynamic.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/blocks/class-themer-blocks.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/widgets/class-themer-widgets.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-metabox.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/php/class-themer-php-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/php/class-themer-php.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/php/class-themer-php-renderer.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-themer-php-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/php/class-themer-php-admin.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-themer-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-themer-module.php';
		// Pro-tier units (SEO/a11y helpers + abilities, widget generator + builder
		// abilities, system-kit abilities, Pro brand kits, AI Chat). These ship in
		// the private Pro overlay (pro/) and are absent from the free build; the
		// loader require_once's each only when present, so the free plugin runs
		// with zero Pro references. Each unit still self-gates on license.
		require_once EMCP_TOOLS_DIR . 'includes/class-pro-loader.php';
		EMCP_Tools_Pro_Loader::load_runtime();

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
		// Content search index: install-on-init + incremental re-index on save/delete.
		EMCP_Tools_Search_Index::init();
		add_action( 'init', array( 'EMCP_Tools_Kit_Backup_Store', 'register_post_type' ) );
		add_action( 'init', array( 'EMCP_Tools_Widget_Store', 'register_post_type' ) );
		( new EMCP_Tools_Widget_Loader() )->register_hooks();
		add_action( 'init', array( 'EMCP_Tools_PHP_Snippet_Store', 'register_post_type' ) );
		( new EMCP_Tools_PHP_Snippet_Loader() )->register_hooks();
		// Background refresh of the Pro Prompts / Brand Kits libraries — registered
		// unconditionally (cron runs in a non-admin context) so an expired 24h
		// cache self-heals without the user clicking "Sync Library".
		EMCP_Tools_Library_Refresher::register();
		// Modules: register built-ins + Pro modules, seed defaults once, boot the
		// active ones on `init` (after registration, before most feature hooks).
		$emcp_modules = EMCP_Tools_Modules_Registry::instance();
		$emcp_modules->register( new EMCP_Tools_Image_Optimization_Module() );
		$emcp_modules->register( new EMCP_Tools_Prompts_Module() );
		$emcp_modules->register( new EMCP_Tools_Brand_Kits_Module() );
		$emcp_modules->register( new EMCP_Tools_Templates_Module() );
		$emcp_modules->register( new EMCP_Tools_Themer_Module() );
		$emcp_modules->register( new EMCP_Tools_Agent_Skills_Module() );
		EMCP_Tools_Pro_Loader::register_modules( $emcp_modules );
		do_action( 'emcp_tools_register_modules', $emcp_modules );
		$emcp_modules->apply_defaults();
		add_action( 'init', array( $emcp_modules, 'boot_active' ), 5 );
		// AI Chat (Pro): REST routes + weekly model-list refresh cron + saved-
		// conversation CPT. No-op in the free build (classes absent).
		EMCP_Tools_Pro_Loader::wire_runtime_hooks();

		// Admin-bar MCP status + exposure toggle (front-end + wp-admin; the class
		// self-gates on capability + is_admin_bar_showing()).
		( new EMCP_Tools_Admin_Bar() )->init();

		// Free-tier updates from GitHub releases (self-disables on premium builds,
		// where Freemius owns updates).
		( new EMCP_Tools_GitHub_Updater() )->init();
	}

	/**
	 * Loads admin-only classes and wires the Pro library admin-ajax handlers.
	 *
	 * @since 2.1.0
	 */
	private static function load_admin(): void {
		require_once EMCP_TOOLS_DIR . 'includes/admin/class-admin.php';
		require_once EMCP_TOOLS_DIR . 'includes/admin/class-mcpb-builder.php';

		// Pro admin units (AI Chat page assets + Pro prompts/templates/skills +
		// Pro-library ajax). Loaded from the private Pro overlay when present;
		// no-op in the free build. wire_admin_hooks() replicates the previous
		// order: AI-chat page init, then the emcp_tools_fs()-gated Pro handlers.
		EMCP_Tools_Pro_Loader::load_admin();
		EMCP_Tools_Pro_Loader::wire_admin_hooks();

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
		// PHP 8.1+ is required. Elementor 4.0+ uses 8.1+ features that silently
		// fail on older PHP (writes no-op, _elementor_data never persists).
		// WordPress only enforces Requires PHP at activation, not on every load —
		// so we re-check here to surface a clear admin notice if the host
		// downgraded PHP after the plugin was already installed.
		if ( version_compare( PHP_VERSION, '8.1', '<' ) ) {
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
							esc_html__( 'EMCP Tools requires PHP 8.1 or higher. Your server is running PHP %s — please upgrade PHP to avoid silent Elementor write failures.', 'emcp-tools' ),
							esc_html( PHP_VERSION )
						)
					);
				}
			);
			return false;
		}

		$missing = array();

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

		// Elementor is OPTIONAL. When absent, the plugin still loads and every
		// beyond-Elementor tool works; only the Elementor tool family + the
		// Elementor admin areas are unavailable. Surface a non-blocking warning.
		if ( ! self::elementor_active() && is_admin() ) {
			add_action(
				'admin_notices',
				function () {
					if ( ! current_user_can( 'manage_options' ) ) {
						return;
					}
					$install = self_admin_url( 'plugin-install.php?s=Elementor&tab=search&type=term' );
					printf(
						'<div class="notice notice-warning"><p>%s</p><p><a class="button button-secondary" href="%s">%s</a></p></div>',
						esc_html__( 'EMCP Tools is active. Install and activate Elementor to enable the Elementor page-building tools (widgets, layout, templates, brand kits). All other tools — WordPress content, plugins & themes, users, media, performance, security, filesystem, and database — work without it.', 'emcp-tools' ),
						esc_url( $install ),
						esc_html__( 'Install Elementor', 'emcp-tools' )
					);
				}
			);
		}

		return true;
	}
}

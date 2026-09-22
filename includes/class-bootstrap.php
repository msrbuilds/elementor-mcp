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

		// Relocate the sandbox from the legacy uploads/emcp-widgets location to
		// wp-content/emcp-sandbox once. Runs here (plugins_loaded) so it completes
		// before the artifact loaders fire on `init`. Idempotent, option-gated.
		EMCP_Tools_Sandbox_Paths::maybe_migrate();

		self::wire_hooks();

		// The MCP tool surface (~80 ability classes + infra) is only used to
		// register/run abilities: an MCP REST call, the admin Tools screen, WP-CLI,
		// or cron. Load it eagerly for those requests; a plain front-end page view
		// skips it entirely (saving ~10 MB/request). register_abilities() /
		// register_mcp_server() also call load_mcp_surface() as a lazy fallback, so
		// a request type the gate misses still works the moment the Abilities API
		// fires.
		if ( self::needs_mcp_surface() ) {
			self::load_mcp_surface();
		}

		if ( is_admin() ) {
			self::load_admin();
		}

		// Boot the plugin singleton.
		EMCP_Tools_Plugin::instance();
	}

	/**
	 * Whether this request needs the MCP tool surface loaded up front: the admin,
	 * a REST request (the MCP endpoint + the plugin's own routes live there),
	 * WP-CLI (the mcp-adapter stdio bridge + our CLI tools), or cron (background
	 * jobs / library refresh). A plain front-end page view needs none of it.
	 *
	 * @since 3.12.2
	 *
	 * @return bool
	 */
	public static function needs_mcp_surface(): bool {
		return is_admin()
			|| ( defined( 'WP_CLI' ) && WP_CLI )
			|| ( function_exists( 'wp_doing_cron' ) && wp_doing_cron() )
			|| self::is_rest_request();
	}

	/**
	 * Best-effort early detection of a REST request. `REST_REQUEST` is not defined
	 * until `parse_request` (after plugins_loaded), so at boot time we fall back to
	 * the request URI: the REST base (`/wp-json/`) or the plain-permalink
	 * `?rest_route=` form. Deliberately broad — any REST request loads the full
	 * surface, so a custom MCP route is never missed.
	 *
	 * @since 3.12.2
	 *
	 * @return bool
	 */
	public static function is_rest_request(): bool {
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return true;
		}
		if ( isset( $_GET['rest_route'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return true;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		if ( '' === $uri ) {
			return false;
		}
		$prefix = function_exists( 'rest_get_url_prefix' ) ? rest_get_url_prefix() : 'wp-json';
		return false !== strpos( $uri, '/' . $prefix . '/' ) || false !== strpos( $uri, '/wp-json/' );
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
		require_once EMCP_TOOLS_DIR . 'includes/class-page-builders.php';
		// Schema compatibility + the emcp_tools_register_ability() entry point
		// must load before any ability group registers.
		require_once EMCP_TOOLS_DIR . 'includes/class-schema-compat.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-id-generator.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-url-guard.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-frontend-page-fetcher.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-site-context.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-elementor-data.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-element-factory.php';
		require_once EMCP_TOOLS_DIR . 'includes/schemas/class-control-mapper.php';
		require_once EMCP_TOOLS_DIR . 'includes/schemas/class-schema-generator.php';
		require_once EMCP_TOOLS_DIR . 'includes/validators/class-element-validator.php';
		require_once EMCP_TOOLS_DIR . 'includes/validators/class-settings-validator.php';
		// Widget catalog — source of truth for the 5 catalog-backed widget tools.
		require_once EMCP_TOOLS_DIR . 'includes/class-secret.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-remote-keys.php';
		require_once EMCP_TOOLS_DIR . 'includes/oauth/class-oauth-util.php';
		require_once EMCP_TOOLS_DIR . 'includes/cloud/class-cloud.php';
		require_once EMCP_TOOLS_DIR . 'includes/cloud/class-cloud-http.php';
		require_once EMCP_TOOLS_DIR . 'includes/cloud/class-cloud-connect.php';
		require_once EMCP_TOOLS_DIR . 'includes/cloud/class-cloud-client.php';
		require_once EMCP_TOOLS_DIR . 'includes/cloud/class-gateway-credential.php';
		require_once EMCP_TOOLS_DIR . 'includes/cloud/class-cloud-sync.php';
		require_once EMCP_TOOLS_DIR . 'includes/cloud/class-settings-sync.php';
		require_once EMCP_TOOLS_DIR . 'includes/oauth/class-oauth-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/oauth/class-oauth-metadata.php';
		require_once EMCP_TOOLS_DIR . 'includes/oauth/class-oauth-clients.php';
		require_once EMCP_TOOLS_DIR . 'includes/oauth/class-oauth-authorize.php';
		require_once EMCP_TOOLS_DIR . 'includes/oauth/class-oauth-token.php';
		require_once EMCP_TOOLS_DIR . 'includes/oauth/class-oauth-bearer.php';
		require_once EMCP_TOOLS_DIR . 'includes/oauth/class-oauth-server.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-change-log.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-change-blobs.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-change-recorder.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-structured-import.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-search-ranker.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-search-index.php';
		require_once EMCP_TOOLS_DIR . 'includes/redirects/class-redirect-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/redirects/class-redirect-handler.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-content-mirror.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-admin-bar.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-github-updater.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-notifications.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-nav-menu-shortcode.php';
		// Editor-side auto-repair for AI-inserted static-save blocks (Kadence).
		require_once EMCP_TOOLS_DIR . 'includes/class-block-repair.php';
		add_action( 'init', array( 'EMCP_Tools_Nav_Menu_Shortcode', 'register' ) );
		// ACF tools (field values + field group discovery/authoring; writes off by default).
		// Meta Box tools (field values + field group discovery; writes off by default).

		// Themes domain: the child-theme builder + the dispatcher base (must load
		// before its subclasses) + the integrations.
		// Forms-tab integrations — abstract base + CF7 (free). Pro form adapters
		// (WPForms/Gravity/Fluent/Ninja/Formidable) load via EMCP_Tools_Pro_Loader.
		// SEO plugin integrations — abstract base + Slim SEO (free). The 6 Pro SEO
		// adapters (Yoast/RankMath/AIOSEO/SeoPress/SEOFramework/SureRank) load via
		// EMCP_Tools_Pro_Loader.
		// Performance Analyzer (v3.0.0) — read-only server/WP/page audit.
		// Filesystem tools (read/scan + write/edit/delete; writes off by default).
		// Database tools (read-only query + structured writes; writes off by default).
		// WP-CLI tools (run + background jobs; disabled-by-default, manage_options).
		// Security & Malware Scanner (v3.0.0) — read-only multi-audit scan.
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
		// Central sandbox storage location (wp-content/emcp-sandbox). Every store
		// resolves paths through this, so it must load before them.
		require_once EMCP_TOOLS_DIR . 'includes/sandbox/class-sandbox-paths.php';
		// Paged listing shared by all three artifact stores. Loaded alongside
		// them, not with the admin, because the stores name it in a default.
		require_once EMCP_TOOLS_DIR . 'includes/sandbox/class-sandbox-list-query.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-widget-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-widget-loader.php';
		// Sandbox Bundle — portable cloud-ready format for blocks/widgets/snippets.
		require_once EMCP_TOOLS_DIR . 'includes/sandbox/class-sandbox-bundle.php';
		require_once EMCP_TOOLS_DIR . 'includes/sandbox/interface-sandbox-artifact.php';
		require_once EMCP_TOOLS_DIR . 'includes/sandbox/class-sandbox-store.php';
		// Bundle adapters — present the Widget Builder + PHP Snippet stores as the
		// same cloud-ready artifact surface (EMCP_Tools_Sandbox_Artifact) as the
		// block store, without touching either store's internals.
		require_once EMCP_TOOLS_DIR . 'includes/sandbox/class-widget-bundle-adapter.php';
		require_once EMCP_TOOLS_DIR . 'includes/sandbox/class-snippet-bundle-adapter.php';
		// Sandbox Cloud abilities — export/import any sandbox artifact (block/
		// widget/snippet) as a portable bundle over the cloud contract. Free tree;
		// registration is wired by the ability registrar (a later task).
		// PHP Code Snippets (Sandbox) — free, capability-gated. AI can author +
		// validate drafts via MCP; only an admin can activate. The loader runs
		// ACTIVE snippets (hash-verified, fatal-isolated).
		require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-validator.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-php-snippet-loader.php';
		// Atomic elements support (Elementor 4.0+).
		require_once EMCP_TOOLS_DIR . 'includes/class-atomic-props.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-atomic-styles.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-atomic-widget-map.php';
		// Global Classes (Class Manager) reader — self-gates on Elementor 4.0+.
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
		require_once EMCP_TOOLS_DIR . 'includes/modules/svg-support/class-svg-sanitizer.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/svg-support/class-svg-support-module.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-cloud-module.php';
		// EMCP Themer (free): builder-agnostic theme builder engine + module + MCP tools.
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-matcher-registry.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-conditions.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-context.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-index.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-resolver.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-condition-schema.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-cpt.php';
		// Loop Items: per-item context, query builder, renderer (3.18.0).
		require_once EMCP_TOOLS_DIR . 'includes/themer/loop/class-themer-loop-context.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/loop/class-themer-loop-query.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/loop/class-themer-loop-renderer.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/loop/class-themer-loop-config.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-content-renderer.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-theme-adapters.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-render-controller.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-hfe-conflict.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-betheme-conflict.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-base.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-archive-posts.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-post-info.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-sitemap.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-search-form.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-post-comments.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-post-navigation.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-author-box.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-loop-base.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-loop-grid.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/elements/class-themer-element-loop-carousel.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/loop/class-themer-loop-assets.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/loop/class-themer-loop-options.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/loop/class-themer-loop-widget-map.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/loop/class-themer-loop-rest.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/dynamic/class-themer-dynamic-catalog.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-dynamic.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/dynamic/class-themer-elementor-tags.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/dynamic/class-themer-block-bindings.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/dynamic/class-themer-dynamic-compiler.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-themer-dynamic-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/blocks/class-themer-blocks.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/widgets/class-themer-widgets.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/class-themer-metabox.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/php/class-themer-php-store.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/php/class-themer-php.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/php/class-themer-php-renderer.php';
		require_once EMCP_TOOLS_DIR . 'includes/themer/php/class-themer-php-admin.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-themer-module.php';
		require_once EMCP_TOOLS_DIR . 'includes/modules/class-redirect-module.php';
		// Pro-tier units (SEO/a11y helpers + abilities, widget generator + builder
		// abilities, system-kit abilities, Pro brand kits, AI Chat). These ship in
		// the private Pro overlay (pro/) and are absent from the free build; the
		// loader require_once's each only when present, so the free plugin runs
		// with zero Pro references. Each unit still self-gates on license.
		// Integration BASE classes stay runtime: the Pro form/SEO/theme adapters
		// (loaded eagerly by Pro_Loader::load_runtime below) extend them, and their
		// free subclasses in the deferred MCP surface do too. Bases must precede both.
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-theme-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-block-pack-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/forms/class-form-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/seo/class-seo-integration.php';
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
	/** @var bool Whether the MCP tool surface (ability classes + infra) is loaded. */
	private static $mcp_surface_loaded = false;

	/**
	 * Loads the MCP tool surface: every ability class plus its exclusive infra
	 * (schema catalogs, guards, audits, integrations, stock clients). This is the
	 * heaviest part of the plugin (~80 files) and is only needed to register or run
	 * abilities — an MCP request, the admin Tools screen, WP-CLI, or cron. A plain
	 * front-end page view never touches it, so deferring these requires off the
	 * boot path keeps the per-request footprint low (memory, #128MB-hosts).
	 *
	 * Idempotent + called from three places: eagerly in boot() when the request
	 * needs the surface, and defensively at the top of register_abilities() /
	 * register_mcp_server() so the classes are always present the moment the
	 * Abilities API lazily fires, even on a request type not covered by the gate.
	 *
	 * @since 3.12.2
	 */
	public static function load_mcp_surface(): void {
		if ( self::$mcp_surface_loaded ) {
			return;
		}
		self::$mcp_surface_loaded = true;

		require_once EMCP_TOOLS_DIR . 'includes/widgets/class-widget-catalog.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-query-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-page-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-layout-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-widget-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-template-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-global-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-composite-abilities.php';
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
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-transaction-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-search-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-redirect-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-content-mirror-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-content-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-dispatcher-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-settings-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-package-guard.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-plugin-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-theme-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-user-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-nav-menu-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-acf-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-metabox-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-child-theme-builder.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-active-theme-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-astra-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/blocks-catalog/class-spectra-catalog.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-spectra-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-kadence-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/blocks-catalog/class-kadence-blocks-catalog.php';
		require_once EMCP_TOOLS_DIR . 'includes/blocks-catalog/class-kadence-pattern-library.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-kadence-blocks-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/forms/class-cf7-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/seo/class-slimseo-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/seo/class-visibility-integration.php';
		require_once EMCP_TOOLS_DIR . 'includes/performance/class-performance-finding.php';
		require_once EMCP_TOOLS_DIR . 'includes/performance/class-performance-server-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/performance/class-performance-page-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/performance/class-performance-analyzer.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-performance-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-filesystem-guard.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-filesystem-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/sql/class-sql-lexer.php';
		require_once EMCP_TOOLS_DIR . 'includes/sql/class-sql-policy.php';
		require_once EMCP_TOOLS_DIR . 'includes/class-database-guard.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-database-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/wpcli/class-wpcli-validator.php';
		require_once EMCP_TOOLS_DIR . 'includes/wpcli/class-wpcli-runner.php';
		require_once EMCP_TOOLS_DIR . 'includes/wpcli/class-wpcli-jobs.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-wpcli-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-finding.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-malware-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-integrity-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-hardening-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-software-audit.php';
		require_once EMCP_TOOLS_DIR . 'includes/security/class-security-scanner.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-security-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-svg-icon-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-custom-code-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-sandbox-cloud-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-cloud-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-php-snippet-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-atomic-widget-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-atomic-layout-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-global-classes-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-global-classes-write-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-global-variables-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-themer-php-abilities.php';
		require_once EMCP_TOOLS_DIR . 'includes/abilities/class-themer-abilities.php';

		// Pro-tier ability classes (SEO/a11y, widget builder, system-kit, migrate,
		// memory) defer here too once the Pro loader splits its file list; until
		// then they load with the Pro runtime. method_exists-guarded so this is a
		// no-op on builds without the deferred Pro surface.
		if ( class_exists( 'EMCP_Tools_Pro_Loader' ) && method_exists( 'EMCP_Tools_Pro_Loader', 'load_mcp_surface' ) ) {
			EMCP_Tools_Pro_Loader::load_mcp_surface();
		}
	}

	private static function wire_hooks(): void {
		// structuredContent must be a JSON object; the adapter assigns a tool's
		// return value to it verbatim, so a list result makes strict clients
		// reject the response. Our own abilities are normalized at registration,
		// but this catches everything else the server surfaces, including the
		// three core/* abilities we do not register ourselves. Normalization is
		// idempotent, so running on both paths is harmless.
		add_filter(
			'mcp_adapter_tool_call_result',
			array( 'EMCP_Tools_Schema_Compat', 'normalize_result' ),
			99
		);

		// Invalidate Elementor's rendered-element cache on any _elementor_data
		// write, so MCP-created/edited pages never serve a stale empty render (#111).
		EMCP_Tools_Data::init();
		// Content search index: install-on-init + incremental re-index on save/delete.
		EMCP_Tools_Search_Index::init();
		EMCP_Tools_Change_Blobs::init();
		// The Redirect Manager (store table install + front-end 301/302 handler) is
		// booted by EMCP_Tools_Redirect_Module::register() only when the module is
		// active — a true kill switch from the Modules tab.
		// OAuth sign-in: install storage on init (routes wired in later phases).
		EMCP_Tools_OAuth_Server::init();
		// Content mirror: auto-export-on-save (gated by its option) + delete cleanup.
		EMCP_Tools_Content_Mirror::init();
		// Auto-repair flagged posts' AI-inserted blocks in the block editor.
		EMCP_Tools_Block_Repair::init();
		add_action( 'init', array( 'EMCP_Tools_Kit_Backup_Store', 'register_post_type' ) );
		add_action( 'init', array( 'EMCP_Tools_Widget_Store', 'register_post_type' ) );
		( new EMCP_Tools_Widget_Loader() )->register_hooks();
		add_action( 'init', array( 'EMCP_Tools_PHP_Snippet_Store', 'register_post_type' ) );
		( new EMCP_Tools_PHP_Snippet_Loader() )->register_hooks();
		// Block Builder (Pro overlay). Registers the emcp_block CPT + Gutenberg
		// block-category/init hooks only when the Pro class is present; the
		// loader self-gates on license internally.
		if ( class_exists( 'EMCP_Tools_Block_Store' ) ) {
			add_action( 'init', array( 'EMCP_Tools_Block_Store', 'register_post_type' ) );
			( new EMCP_Tools_Block_Loader() )->register_hooks();
		}
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
		$emcp_modules->register( new EMCP_Tools_Redirect_Module() );
		$emcp_modules->register( new EMCP_Tools_Agent_Skills_Module() );
		$emcp_modules->register( new EMCP_Tools_SVG_Support_Module() );
		$emcp_modules->register( new EMCP_Tools_Cloud_Module() );
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
		require_once EMCP_TOOLS_DIR . 'includes/admin/class-admin-pager.php';
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
							esc_html__( 'EMCP Tools requires PHP 8.1 or higher. Your server is running PHP %s, please upgrade PHP to avoid silent Elementor write failures.', 'emcp-tools' ),
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
			$missing[] = 'WordPress MCP Adapter (bundled, reinstall the plugin if this persists)';
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
		// Elementor admin areas are unavailable. Builder availability is shown
		// on the Page Builders screen instead of an installation notice.

		return true;
	}
}

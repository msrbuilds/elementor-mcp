<?php
/**
 * Main plugin orchestrator.
 *
 * Singleton that initializes all components, registers hooks for the
 * Abilities API and MCP Adapter, and coordinates the plugin lifecycle.
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Plugin orchestrator singleton.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * The data access layer.
	 *
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * The element factory.
	 *
	 * @var EMCP_Tools_Element_Factory
	 */
	private $factory;

	/**
	 * The schema generator.
	 *
	 * @var EMCP_Tools_Schema_Generator
	 */
	private $schema_generator;

	/**
	 * The ability registrar.
	 *
	 * @var EMCP_Tools_Ability_Registrar
	 */
	private $registrar;

	/**
	 * The admin settings page handler.
	 *
	 * @var EMCP_Tools_Admin|null
	 */
	private $admin = null;

	/**
	 * Registered ability names (populated after registration).
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Gets the singleton instance.
	 *
	 * @since 1.0.0
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
			self::$instance->init();
		}

		return self::$instance;
	}

	/**
	 * Private constructor to enforce singleton.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {}

	/**
	 * Initializes the plugin components and hooks.
	 *
	 * @since 1.0.0
	 */
	private function init(): void {
		// Instantiate core components.
		$this->data             = new EMCP_Tools_Data();
		$this->factory          = new EMCP_Tools_Element_Factory();
		$this->schema_generator = new EMCP_Tools_Schema_Generator();
		$validator              = new EMCP_Tools_Settings_Validator( $this->schema_generator );
		$this->registrar        = new EMCP_Tools_Ability_Registrar( $this->data, $this->factory, $this->schema_generator, $validator );

		// Admin settings page.
		if ( is_admin() && class_exists( 'EMCP_Tools_Admin' ) ) {
			$this->admin = new EMCP_Tools_Admin();
			$this->admin->init();
		}

		// Register hooks.
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_category' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );

		// The Abilities API is lazy-loaded: wp_abilities_api_init fires on first
		// wp_get_ability() call. The default MCP server's tool registration triggers
		// this during mcp_adapter_init at priority 10. We hook at priority 20 so
		// the Abilities API is initialized and our abilities are registered by then.
		add_action( 'mcp_adapter_init', array( $this, 'register_mcp_server' ), 20 );

		// Apply the disabled-tools option from the admin settings page on every
		// request. The admin class is only loaded in is_admin() context, so the
		// MCP REST endpoint would otherwise never see this filter and would
		// expose every registered tool regardless of what the user disabled.
		add_filter( 'emcp_tools_ability_names', array( $this, 'filter_disabled_tools' ) );
	}

	/**
	 * Removes tools the user disabled from the registered ability names list.
	 *
	 * @since 1.6.0
	 *
	 * @param string[] $names The registered ability names.
	 * @return string[] Ability names with disabled tools removed.
	 */
	public function filter_disabled_tools( array $names ): array {
		// Low-tools mode is an OVERRIDE preset, not an addition: expose exactly
		// the curated essentials (intersected with what's actually registered),
		// regardless of the per-tool toggles. The toggles stay stored and resume
		// the moment low-tools mode is turned off — which is why turning it on
		// always yields the essentials even if every tool was previously disabled.
		if ( '1' === (string) get_option( 'emcp_tools_low_tool_mode', '0' ) ) {
			return array_values( array_intersect( $names, self::get_essential_tool_slugs() ) );
		}

		$disabled = get_option( 'emcp_tools_disabled_tools', array() );
		if ( ! is_array( $disabled ) || empty( $disabled ) ) {
			return $names;
		}

		return array_values( array_diff( $names, $disabled ) );
	}

	/**
	 * Curated list of essential tool slugs kept active in low-tools mode.
	 *
	 * Trimmed to fit under a 60-tool budget while preserving every category
	 * an AI agent needs to build a page from scratch: discovery, page CRUD,
	 * layout, the catalog-backed widget tools (add-free-widget / add-pro-widget /
	 * update-widget), globals, templates, stock images, custom code, and (when
	 * Elementor 4.0+ is active) the atomic universal + container tools.
	 *
	 * @since 1.6.0
	 *
	 * @return string[]
	 */
	public static function get_essential_tool_slugs(): array {
		return array(
			// Query / discovery (7).
			'emcp-tools/list-widgets',
			'emcp-tools/get-widget-schema',
			'emcp-tools/get-page-structure',
			'emcp-tools/get-element-settings',
			'emcp-tools/list-pages',
			'emcp-tools/list-templates',
			'emcp-tools/get-global-settings',

			// Page CRUD (5).
			'emcp-tools/create-page',
			'emcp-tools/update-page-settings',
			'emcp-tools/delete-page-content',
			'emcp-tools/import-template',
			'emcp-tools/export-page',

			// Layout / structure (10).
			'emcp-tools/add-container',
			'emcp-tools/move-element',
			'emcp-tools/remove-element',
			'emcp-tools/duplicate-element',
			'emcp-tools/update-container',
			'emcp-tools/get-container-schema',
			'emcp-tools/find-element',
			'emcp-tools/update-element',
			'emcp-tools/batch-update',
			'emcp-tools/reorder-elements',

			// Widget tools — catalog-backed (3 insert/update; discovery already
			// listed in the Query block above).
			'emcp-tools/add-free-widget',
			'emcp-tools/add-pro-widget',
			'emcp-tools/update-widget',

			// Templates (2).
			'emcp-tools/save-as-template',
			'emcp-tools/apply-template',

			// Globals (2).
			'emcp-tools/update-global-colors',
			'emcp-tools/update-global-typography',

			// Composite (1).
			'emcp-tools/build-page',

			// Stock images (3).
			'emcp-tools/search-images',
			'emcp-tools/sideload-image',
			'emcp-tools/add-stock-image',

			// SVG icons (1).
			'emcp-tools/upload-svg-icon',

			// Custom code (4).
			'emcp-tools/add-custom-css',
			'emcp-tools/add-custom-js',
			'emcp-tools/add-code-snippet',
			'emcp-tools/list-code-snippets',

			// Atomic essentials (5) — only registered when Elementor 4.0+.
			'emcp-tools/detect-elementor-version',
			'emcp-tools/add-atomic-widget',
			'emcp-tools/update-atomic-widget',
			'emcp-tools/add-flexbox',
			'emcp-tools/add-div-block',

			// WordPress content (8) — general post/page/CPT management.
			'emcp-tools/list-post-types',
			'emcp-tools/list-taxonomies',
			'emcp-tools/create-post',
			'emcp-tools/get-post',
			'emcp-tools/update-post',
			'emcp-tools/list-posts',
			'emcp-tools/delete-post',
			'emcp-tools/set-post-terms',
		);
	}

	/**
	 * Option name for the "Activate Abilities API for EMCP" server gate.
	 *
	 * @since 1.7.4
	 * @var string
	 */
	const OPTION_SERVER_ENABLED = 'emcp_tools_server_enabled';

	/**
	 * Whether the MCP server should be exposed. On by default; the Connection
	 * tab toggle writes '0' to switch it off.
	 *
	 * @since 1.7.4
	 *
	 * @return bool
	 */
	public static function is_server_enabled(): bool {
		return '1' === (string) get_option( self::OPTION_SERVER_ENABLED, '1' );
	}

	/**
	 * Registers the ability category.
	 *
	 * Called during `wp_abilities_api_categories_init`.
	 *
	 * @since 1.0.0
	 */
	public function register_category(): void {
		wp_register_ability_category(
			'emcp-tools',
			array(
				'label'       => __( 'MCP Tools for Elementor', 'emcp-tools' ),
				'description' => __( 'Tools for reading and manipulating Elementor page designs via MCP.', 'emcp-tools' ),
			)
		);
	}

	/**
	 * Registers all abilities with the WordPress Abilities API.
	 *
	 * Called during `wp_abilities_api_init`.
	 *
	 * @since 1.0.0
	 */
	public function register_abilities(): void {
		$this->ability_names = $this->registrar->register_all();
	}

	/**
	 * Registers the MCP server with the MCP Adapter.
	 *
	 * Called during `mcp_adapter_init`.
	 *
	 * @since 1.0.0
	 *
	 * @param \WP\MCP\Core\McpAdapter $mcp_adapter The MCP adapter instance.
	 */
	public function register_mcp_server( $mcp_adapter ): void {
		// "Activate Abilities API for EMCP" gate (Connection tab). On by default;
		// when switched off, the abilities stay registered in core but no MCP
		// server endpoint is created — nothing is exposed to AI agents.
		if ( ! self::is_server_enabled() ) {
			return;
		}

		if ( empty( $this->ability_names ) ) {
			return;
		}

		// Also expose WordPress core's read-only context abilities (site/user/
		// environment info) on our server — registered by core, free to surface.
		$tools = $this->ability_names;
		foreach ( array( 'core/get-site-info', 'core/get-user-info', 'core/get-environment-info' ) as $emcp_core_ability ) {
			if ( function_exists( 'wp_get_ability' ) && wp_get_ability( $emcp_core_ability ) && ! in_array( $emcp_core_ability, $tools, true ) ) {
				$tools[] = $emcp_core_ability;
			}
		}

		$mcp_adapter->create_server(
			'emcp-tools-server',                                   // server_id
			'mcp',                                                    // route_namespace
			'emcp-tools-server',                                   // route
			__( 'MCP Tools for Elementor Server', 'emcp-tools' ),            // server_name
			__( 'Exposes Elementor data and design tools as MCP tools for AI agents.', 'emcp-tools' ), // description
			'v' . EMCP_TOOLS_VERSION,                              // version
			array( \WP\MCP\Transport\HttpTransport::class ),          // transports
			null,                                                     // error_handler (use default)
			null,                                                     // observability_handler
			$tools,                                                   // tools
			array(),                                                  // resources
			array(),                                                  // prompts
			null                                                      // transport_permission_callback
		);
	}

	/**
	 * Gets the data access layer instance.
	 *
	 * @since 1.0.0
	 *
	 * @return EMCP_Tools_Data
	 */
	public function get_data(): EMCP_Tools_Data {
		return $this->data;
	}

	/**
	 * Gets the element factory instance.
	 *
	 * @since 1.0.0
	 *
	 * @return EMCP_Tools_Element_Factory
	 */
	public function get_factory(): EMCP_Tools_Element_Factory {
		return $this->factory;
	}

	/**
	 * Gets the schema generator instance.
	 *
	 * @since 1.0.0
	 *
	 * @return EMCP_Tools_Schema_Generator
	 */
	public function get_schema_generator(): EMCP_Tools_Schema_Generator {
		return $this->schema_generator;
	}

	/**
	 * Prevents cloning.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}
}

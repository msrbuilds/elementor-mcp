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
	 * layout, universal widget add/update, a small set of common widget
	 * shortcuts, globals, templates, stock images, custom code, and (when
	 * Elementor 4.0+ is active) the atomic universal + container tools.
	 *
	 * @since 1.6.0
	 *
	 * @return string[]
	 */
	public static function get_essential_tool_slugs(): array {
		return array(
			// Query / discovery (7).
			'elementor-mcp/list-widgets',
			'elementor-mcp/get-widget-schema',
			'elementor-mcp/get-page-structure',
			'elementor-mcp/get-element-settings',
			'elementor-mcp/list-pages',
			'elementor-mcp/list-templates',
			'elementor-mcp/get-global-settings',

			// Page CRUD (5).
			'elementor-mcp/create-page',
			'elementor-mcp/update-page-settings',
			'elementor-mcp/delete-page-content',
			'elementor-mcp/import-template',
			'elementor-mcp/export-page',

			// Layout / structure (10).
			'elementor-mcp/add-container',
			'elementor-mcp/move-element',
			'elementor-mcp/remove-element',
			'elementor-mcp/duplicate-element',
			'elementor-mcp/update-container',
			'elementor-mcp/get-container-schema',
			'elementor-mcp/find-element',
			'elementor-mcp/update-element',
			'elementor-mcp/batch-update',
			'elementor-mcp/reorder-elements',

			// Universal widget add/update (2).
			'elementor-mcp/add-widget',
			'elementor-mcp/update-widget',

			// Most-used core widget shortcuts (9).
			'elementor-mcp/add-heading',
			'elementor-mcp/add-text-editor',
			'elementor-mcp/add-image',
			'elementor-mcp/add-button',
			'elementor-mcp/add-icon',
			'elementor-mcp/add-spacer',
			'elementor-mcp/add-divider',
			'elementor-mcp/add-icon-box',
			'elementor-mcp/add-html',

			// Templates (2).
			'elementor-mcp/save-as-template',
			'elementor-mcp/apply-template',

			// Globals (2).
			'elementor-mcp/update-global-colors',
			'elementor-mcp/update-global-typography',

			// Composite (1).
			'elementor-mcp/build-page',

			// Stock images (3).
			'elementor-mcp/search-images',
			'elementor-mcp/sideload-image',
			'elementor-mcp/add-stock-image',

			// SVG icons (1).
			'elementor-mcp/upload-svg-icon',

			// Custom code (4).
			'elementor-mcp/add-custom-css',
			'elementor-mcp/add-custom-js',
			'elementor-mcp/add-code-snippet',
			'elementor-mcp/list-code-snippets',

			// Atomic essentials (5) — only registered when Elementor 4.0+.
			'elementor-mcp/detect-elementor-version',
			'elementor-mcp/add-atomic-widget',
			'elementor-mcp/update-atomic-widget',
			'elementor-mcp/add-flexbox',
			'elementor-mcp/add-div-block',
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

		$mcp_adapter->create_server(
			'elementor-mcp-server',                                   // server_id
			'mcp',                                                    // route_namespace
			'elementor-mcp-server',                                   // route
			__( 'MCP Tools for Elementor Server', 'emcp-tools' ),            // server_name
			__( 'Exposes Elementor data and design tools as MCP tools for AI agents.', 'emcp-tools' ), // description
			'v' . EMCP_TOOLS_VERSION,                              // version
			array( \WP\MCP\Transport\HttpTransport::class ),          // transports
			null,                                                     // error_handler (use default)
			null,                                                     // observability_handler
			$this->ability_names,                                     // tools
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

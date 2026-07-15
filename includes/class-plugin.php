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
		$disabled = get_option( 'emcp_tools_disabled_tools', array() );
		if ( ! is_array( $disabled ) || empty( $disabled ) ) {
			return $names;
		}

		return array_values( array_diff( $names, $disabled ) );
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
	 * Option: "compact tool mode" (the meta-tool dispatcher). Default OFF.
	 *
	 * @var string
	 */
	const OPTION_DISPATCHER_MODE = 'emcp_tools_dispatcher_mode';

	/**
	 * Whether "compact tool mode" (the meta-tool dispatcher) is on. Default OFF —
	 * when on, the server surfaces the 3 dispatcher tools instead of every
	 * individual tool.
	 *
	 * @since 3.2.0
	 *
	 * @return bool
	 */
	public static function is_dispatcher_mode(): bool {
		return '1' === (string) get_option( self::OPTION_DISPATCHER_MODE, '0' );
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
		$this->ability_names = $this->registrar->register_all( EMCP_Tools_Bootstrap::elementor_active() );
	}

	/**
	 * Returns the active (post-filter) ability names — the exact set exposed to
	 * the MCP server, with user-disabled tools and Pro-disabled-by-default
	 * already removed. Used by the AI Chat
	 * /execute-ability and /abilities endpoints so the chat can never run a tool
	 * the admin disabled. Triggers the lazy Abilities API init if it hasn't run.
	 *
	 * @since 3.1.0
	 *
	 * @return string[]
	 */
	public function get_active_ability_names(): array {
		if ( empty( $this->ability_names ) && function_exists( 'wp_get_ability' ) ) {
			// Any known ability triggers wp_abilities_api_init → register_abilities().
			wp_get_ability( 'emcp-tools/list-pages' );
		}
		return is_array( $this->ability_names ) ? $this->ability_names : array();
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

		// Compact tool mode: surface only the 3 dispatcher tools instead of every
		// individual ability (the rest stay registered and reachable via call-tool).
		if ( self::is_dispatcher_mode() ) {
			$tools = EMCP_Tools_Dispatcher_Abilities::NAMES;
		} else {
			$tools = $this->ability_names;
		}

		// Also expose WordPress core's read-only context abilities (site/user/
		// environment info) on our server — registered by core, free to surface.
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
			EMCP_Tools_Site_Context::compose_instructions( EMCP_Tools_Site_Context::default_base() . "\n\n" . EMCP_Tools_Site_Context::environment_summary() ), // description (base + env + site context)
			'v' . EMCP_TOOLS_VERSION,                              // version
			array( \WP\MCP\Transport\HttpTransport::class ),          // transports
			null,                                                     // error_handler (use default)
			null,                                                     // observability_handler
			$tools,                                                   // tools
			array(),                                                  // resources
			array(),                                                  // prompts
			// OAuth bearer auth when enabled (falls through to App Password); else adapter default.
			( class_exists( 'EMCP_Tools_OAuth_Server' ) && EMCP_Tools_OAuth_Server::is_enabled() )
				? array( 'EMCP_Tools_OAuth_Bearer', 'permission_callback' )
				: null                                                // transport_permission_callback
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

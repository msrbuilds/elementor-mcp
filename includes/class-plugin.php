<?php
/**
 * Main plugin orchestrator.
 *
 * Singleton that initializes all components, registers hooks for the
 * Abilities API and MCP Adapter, and coordinates the plugin lifecycle.
 *
 * @package Elementor_MCP
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
class Elementor_MCP_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * The data access layer.
	 *
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * The element factory.
	 *
	 * @var Elementor_MCP_Element_Factory
	 */
	private $factory;

	/**
	 * The schema generator.
	 *
	 * @var Elementor_MCP_Schema_Generator
	 */
	private $schema_generator;

	/**
	 * The ability registrar.
	 *
	 * @var Elementor_MCP_Ability_Registrar
	 */
	private $registrar;

	/**
	 * The admin settings page handler.
	 *
	 * @var Elementor_MCP_Admin|null
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
		$this->data             = new Elementor_MCP_Data();
		$this->factory          = new Elementor_MCP_Element_Factory();
		$this->schema_generator = new Elementor_MCP_Schema_Generator();
		$validator              = new Elementor_MCP_Settings_Validator( $this->schema_generator );
		$this->registrar        = new Elementor_MCP_Ability_Registrar( $this->data, $this->factory, $this->schema_generator, $validator );

		// Admin settings page.
		if ( is_admin() && class_exists( 'Elementor_MCP_Admin' ) ) {
			$this->admin = new Elementor_MCP_Admin();
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
			'elementor-mcp',
			array(
				'label'       => __( 'Elementor MCP', 'elementor-mcp' ),
				'description' => __( 'Tools for reading and manipulating Elementor page designs via MCP.', 'elementor-mcp' ),
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
		if ( empty( $this->ability_names ) ) {
			return;
		}

		$mcp_adapter->create_server(
			'elementor-mcp-server',                                   // server_id
			'mcp',                                                    // route_namespace
			'elementor-mcp-server',                                   // route
			__( 'Elementor MCP Server', 'elementor-mcp' ),            // server_name
			__( 'Exposes Elementor data and design tools as MCP tools for AI agents.', 'elementor-mcp' ), // description
			'v' . ELEMENTOR_MCP_VERSION,                              // version
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
	 * @return Elementor_MCP_Data
	 */
	public function get_data(): Elementor_MCP_Data {
		return $this->data;
	}

	/**
	 * Gets the element factory instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Elementor_MCP_Element_Factory
	 */
	public function get_factory(): Elementor_MCP_Element_Factory {
		return $this->factory;
	}

	/**
	 * Gets the schema generator instance.
	 *
	 * @since 1.0.0
	 *
	 * @return Elementor_MCP_Schema_Generator
	 */
	public function get_schema_generator(): Elementor_MCP_Schema_Generator {
		return $this->schema_generator;
	}

	/**
	 * Prevents cloning.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {}
}

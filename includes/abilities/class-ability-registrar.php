<?php
/**
 * Registers all Elementor MCP abilities with the WordPress Abilities API.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Central registrar that coordinates registration of all ability groups.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Ability_Registrar {

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
	 * The settings validator.
	 *
	 * @var Elementor_MCP_Settings_Validator
	 */
	private $validator;

	/**
	 * All registered ability names.
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Elementor_MCP_Data               $data             The data access layer.
	 * @param Elementor_MCP_Element_Factory    $factory          The element factory.
	 * @param Elementor_MCP_Schema_Generator   $schema_generator The schema generator.
	 * @param Elementor_MCP_Settings_Validator $validator        The settings validator.
	 */
	public function __construct(
		Elementor_MCP_Data $data,
		Elementor_MCP_Element_Factory $factory,
		Elementor_MCP_Schema_Generator $schema_generator,
		Elementor_MCP_Settings_Validator $validator
	) {
		$this->data             = $data;
		$this->factory          = $factory;
		$this->schema_generator = $schema_generator;
		$this->validator        = $validator;
	}

	/**
	 * Registers all abilities across all phases.
	 *
	 * Must be called during the `wp_abilities_api_init` action.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of registered ability names.
	 */
	public function register_all(): array {
		// Phase 1: Query/discovery abilities (P0 — read-only).
		$query = new Elementor_MCP_Query_Abilities( $this->data, $this->schema_generator );
		$query->register();
		$this->ability_names = array_merge( $this->ability_names, $query->get_ability_names() );

		// Phase 2: Page CRUD abilities (P1).
		$pages = new Elementor_MCP_Page_Abilities( $this->data, $this->factory );
		$pages->register();
		$this->ability_names = array_merge( $this->ability_names, $pages->get_ability_names() );

		// Phase 2: Layout/container abilities (P1).
		$layout = new Elementor_MCP_Layout_Abilities( $this->data, $this->factory );
		$layout->register();
		$this->ability_names = array_merge( $this->ability_names, $layout->get_ability_names() );

		// Phase 3: Widget abilities — universal + convenience (P1/P2).
		$widgets = new Elementor_MCP_Widget_Abilities( $this->data, $this->factory, $this->schema_generator, $this->validator );
		$widgets->register();
		$this->ability_names = array_merge( $this->ability_names, $widgets->get_ability_names() );

		// Phase 4: Template abilities (P2).
		$templates = new Elementor_MCP_Template_Abilities( $this->data, $this->factory );
		$templates->register();
		$this->ability_names = array_merge( $this->ability_names, $templates->get_ability_names() );

		// Phase 4: Global settings abilities (P2).
		$globals = new Elementor_MCP_Global_Abilities( $this->data );
		$globals->register();
		$this->ability_names = array_merge( $this->ability_names, $globals->get_ability_names() );

		// Phase 5: Composite abilities (P2).
		$composite = new Elementor_MCP_Composite_Abilities( $this->data, $this->factory );
		$composite->register();
		$this->ability_names = array_merge( $this->ability_names, $composite->get_ability_names() );

		// Stock image abilities (search, sideload, add).
		$stock_images = new Elementor_MCP_Stock_Image_Abilities( $this->data, $this->factory );
		$stock_images->register();
		$this->ability_names = array_merge( $this->ability_names, $stock_images->get_ability_names() );

		// SVG icon abilities (upload SVG for use as Elementor icons).
		$svg_icons = new Elementor_MCP_Svg_Icon_Abilities( $this->data, $this->factory );
		$svg_icons->register();
		$this->ability_names = array_merge( $this->ability_names, $svg_icons->get_ability_names() );

		/**
		 * Filters the registered ability names.
		 *
		 * Allows other plugins to add or modify ability names.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $ability_names The registered ability names.
		 */
		$this->ability_names = apply_filters( 'elementor_mcp_ability_names', $this->ability_names );

		return $this->ability_names;
	}

	/**
	 * Gets the list of registered ability names.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of ability names.
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}
}

<?php
/**
 * Registers all MCP Tools for Elementor abilities with the WordPress Abilities API.
 *
 * @package EMCP_Tools
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
class EMCP_Tools_Ability_Registrar {

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
	 * The settings validator.
	 *
	 * @var EMCP_Tools_Settings_Validator
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
	 * @param EMCP_Tools_Data               $data             The data access layer.
	 * @param EMCP_Tools_Element_Factory    $factory          The element factory.
	 * @param EMCP_Tools_Schema_Generator   $schema_generator The schema generator.
	 * @param EMCP_Tools_Settings_Validator $validator        The settings validator.
	 */
	public function __construct(
		EMCP_Tools_Data $data,
		EMCP_Tools_Element_Factory $factory,
		EMCP_Tools_Schema_Generator $schema_generator,
		EMCP_Tools_Settings_Validator $validator
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
		$query = new EMCP_Tools_Query_Abilities( $this->data, $this->schema_generator );
		$query->register();
		$this->ability_names = array_merge( $this->ability_names, $query->get_ability_names() );

		// Phase 2: Page CRUD abilities (P1).
		$pages = new EMCP_Tools_Page_Abilities( $this->data, $this->factory );
		$pages->register();
		$this->ability_names = array_merge( $this->ability_names, $pages->get_ability_names() );

		// Phase 2: Layout/container abilities (P1).
		$layout = new EMCP_Tools_Layout_Abilities( $this->data, $this->factory );
		$layout->register();
		$this->ability_names = array_merge( $this->ability_names, $layout->get_ability_names() );

		// Phase 3: Widget abilities — universal + convenience (P1/P2).
		$widgets = new EMCP_Tools_Widget_Abilities( $this->data, $this->factory, $this->schema_generator, $this->validator );
		$widgets->register();
		$this->ability_names = array_merge( $this->ability_names, $widgets->get_ability_names() );

		// Phase 4: Template abilities (P2).
		$templates = new EMCP_Tools_Template_Abilities( $this->data, $this->factory );
		$templates->register();
		$this->ability_names = array_merge( $this->ability_names, $templates->get_ability_names() );

		// Phase 4: Global settings abilities (P2).
		$globals = new EMCP_Tools_Global_Abilities( $this->data );
		$globals->register();
		$this->ability_names = array_merge( $this->ability_names, $globals->get_ability_names() );

		// Phase 5: Composite abilities (P2).
		$composite = new EMCP_Tools_Composite_Abilities( $this->data, $this->factory );
		$composite->register();
		$this->ability_names = array_merge( $this->ability_names, $composite->get_ability_names() );

		// Stock image abilities (search, sideload, add).
		$stock_images = new EMCP_Tools_Stock_Image_Abilities( $this->data, $this->factory );
		$stock_images->register();
		$this->ability_names = array_merge( $this->ability_names, $stock_images->get_ability_names() );

		// Media Library query ability (list/search the site's own uploads).
		$media_library = new EMCP_Tools_Media_Library_Abilities( $this->data );
		$media_library->register();
		$this->ability_names = array_merge( $this->ability_names, $media_library->get_ability_names() );

		// WordPress Content abilities (posts/pages/CPT CRUD + taxonomy + meta).
		// Unconditional — pure WordPress, always available.
		$content = new EMCP_Tools_Content_Abilities();
		$content->register();
		$this->ability_names = array_merge( $this->ability_names, $content->get_ability_names() );

		// SVG icon abilities (upload SVG for use as Elementor icons).
		$svg_icons = new EMCP_Tools_Svg_Icon_Abilities( $this->data, $this->factory );
		$svg_icons->register();
		$this->ability_names = array_merge( $this->ability_names, $svg_icons->get_ability_names() );

		// Custom code abilities (CSS, JS, code snippets).
		$custom_code = new EMCP_Tools_Custom_Code_Abilities( $this->data, $this->factory );
		$custom_code->register();
		$this->ability_names = array_merge( $this->ability_names, $custom_code->get_ability_names() );

		// Atomic widget abilities (Elementor 4.0+). Self-guards on version check.
		$atomic_widgets = new EMCP_Tools_Atomic_Widget_Abilities( $this->data, $this->factory );
		$atomic_widgets->register();
		$this->ability_names = array_merge( $this->ability_names, $atomic_widgets->get_ability_names() );

		// Atomic layout abilities (Elementor 4.0+). Includes detect-elementor-version.
		$atomic_layout = new EMCP_Tools_Atomic_Layout_Abilities( $this->data, $this->factory );
		$atomic_layout->register();
		$this->ability_names = array_merge( $this->ability_names, $atomic_layout->get_ability_names() );

		// Global Classes (Class Manager) reader. Self-gates on Elementor 4.0+ —
		// register()/get_ability_names() are no-ops when unavailable.
		if ( class_exists( 'EMCP_Tools_Global_Classes_Abilities' ) ) {
			$global_classes = new EMCP_Tools_Global_Classes_Abilities();
			$global_classes->register();
			$this->ability_names = array_merge( $this->ability_names, $global_classes->get_ability_names() );
		}

		// Brand kit / system-kit abilities (Pro only). Self-guards on Pro access:
		// register() is a no-op and get_ability_names() returns [] for free sites,
		// so the four tools never enter the MCP surface without a license.
		if ( class_exists( 'EMCP_Tools_System_Kit_Abilities' ) ) {
			$brand_kits = new EMCP_Tools_System_Kit_Abilities();
			$brand_kits->register();
			$this->ability_names = array_merge( $this->ability_names, $brand_kits->get_ability_names() );
		}

		// SEO toolkit abilities (Pro only). Self-guards on Pro access exactly
		// like the brand-kit group — register()/get_ability_names() are no-ops
		// without a license, so the tools never enter the MCP surface.
		if ( class_exists( 'EMCP_Tools_Seo_Abilities' ) ) {
			$seo = new EMCP_Tools_Seo_Abilities( $this->data );
			$seo->register();
			$this->ability_names = array_merge( $this->ability_names, $seo->get_ability_names() );
		}

		// Accessibility toolkit abilities (Pro only). Same self-guard.
		if ( class_exists( 'EMCP_Tools_A11y_Abilities' ) ) {
			$a11y = new EMCP_Tools_A11y_Abilities( $this->data );
			$a11y->register();
			$this->ability_names = array_merge( $this->ability_names, $a11y->get_ability_names() );
		}

		// Widget Builder abilities (Pro only). Self-guards on Pro access —
		// register()/get_ability_names() are no-ops without a license.
		if ( class_exists( 'EMCP_Tools_Widget_Builder_Abilities' ) ) {
			$widget_builder = new EMCP_Tools_Widget_Builder_Abilities();
			$widget_builder->register();
			$this->ability_names = array_merge( $this->ability_names, $widget_builder->get_ability_names() );
		}

		// PHP Snippet abilities (Sandbox) — free, capability-gated. AI authors +
		// validates drafts; activation is admin-only (no activate tool here).
		if ( class_exists( 'EMCP_Tools_PHP_Snippet_Abilities' ) ) {
			$php_snippets = new EMCP_Tools_PHP_Snippet_Abilities();
			$php_snippets->register();
			$this->ability_names = array_merge( $this->ability_names, $php_snippets->get_ability_names() );
		}

		/**
		 * Filters the registered ability names.
		 *
		 * Allows other plugins to add or modify ability names.
		 *
		 * @since 1.0.0
		 *
		 * @param string[] $ability_names The registered ability names.
		 */
		$this->ability_names = apply_filters( 'emcp_tools_ability_names', $this->ability_names );

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

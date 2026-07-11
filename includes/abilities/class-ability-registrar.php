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
	 * @param bool $elementor_active Whether Elementor is active. When false, only
	 *                               pure-WordPress tool groups are registered.
	 *                               Default true (preserves prior behavior for any
	 *                               caller that does not pass the flag).
	 *
	 * @return string[] Array of registered ability names.
	 */
	public function register_all( bool $elementor_active = true ): array {
		// ---- Always-on: pure-WordPress tool groups (no Elementor needed) ----

		// Media Library query ability (list/search the site's own uploads).
		$media_library = new EMCP_Tools_Media_Library_Abilities( $this->data );
		$media_library->register();
		$this->ability_names = array_merge( $this->ability_names, $media_library->get_ability_names() );

		// resize-media — only when the Image Optimization module is active (it reuses
		// that module's backup + compress + WebP machinery).
		if ( class_exists( 'EMCP_Tools_Image_Resize_Abilities' )
			&& class_exists( 'EMCP_Tools_Image_Optimization_Module' )
			&& EMCP_Tools_Image_Optimization_Module::module_is_active() ) {
			$resize = new EMCP_Tools_Image_Resize_Abilities();
			$resize->register();
			$this->ability_names = array_merge( $this->ability_names, $resize->get_ability_names() );
		}

		// WordPress Content abilities (posts/pages/CPT CRUD + taxonomy + meta).
		$content = new EMCP_Tools_Content_Abilities();
		$content->register();
		$this->ability_names = array_merge( $this->ability_names, $content->get_ability_names() );

		// Gutenberg block abilities (discover blocks/patterns + incremental block-tree edits).
		$gutenberg = new EMCP_Tools_Gutenberg_Abilities();
		$gutenberg->register();
		$this->ability_names = array_merge( $this->ability_names, $gutenberg->get_ability_names() );

		// Page Snapshot — always-on normalized page digest (read foundation).
		$snapshot = new EMCP_Tools_Snapshot_Abilities( $this->data );
		$snapshot->register();
		$this->ability_names = array_merge( $this->ability_names, $snapshot->get_ability_names() );

		// Compact tool mode dispatcher (list-tools/get-tool-schema/call-tool).
		// Registered ALWAYS so wp_get_ability() resolves them, but deliberately
		// NOT added to $this->ability_names — register_mcp_server() surfaces them
		// only when dispatcher mode is on (otherwise they'd double the surface).
		$dispatcher = new EMCP_Tools_Dispatcher_Abilities();
		$dispatcher->register();

		// EMCP Themer MCP tools — only when the (free) Themer module is active.
		// The module boots on init:5, after this runs, so gate on the option directly.
		if ( class_exists( 'EMCP_Tools_Themer_Abilities' )
			&& class_exists( 'EMCP_Tools_Themer_Module' )
			&& EMCP_Tools_Themer_Module::is_enabled() ) {
			$themer = new EMCP_Tools_Themer_Abilities();
			$themer->register();
			$this->ability_names = array_merge( $this->ability_names, $themer->get_ability_names() );
		}

		// EMCP Themer PHP-Template MCP tools — only when the feature toggle is on
		// (its own option, independent of the base Themer tools above).
		if ( class_exists( 'EMCP_Tools_Themer_PHP_Abilities' )
			&& class_exists( 'EMCP_Tools_Themer_PHP' )
			&& EMCP_Tools_Themer_PHP::enabled() ) {
			$themer_php = new EMCP_Tools_Themer_PHP_Abilities();
			$themer_php->register();
			$this->ability_names = array_merge( $this->ability_names, $themer_php->get_ability_names() );
		}

		// WordPress Settings abilities (curated site-settings read/update).
		$settings = new EMCP_Tools_Settings_Abilities();
		$settings->register();
		$this->ability_names = array_merge( $this->ability_names, $settings->get_ability_names() );

		// WordPress Plugins & Themes abilities.
		$plugins = new EMCP_Tools_Plugin_Abilities();
		$plugins->register();
		$this->ability_names = array_merge( $this->ability_names, $plugins->get_ability_names() );

		$themes = new EMCP_Tools_Theme_Abilities();
		$themes->register();
		$this->ability_names = array_merge( $this->ability_names, $themes->get_ability_names() );

		// WordPress Users abilities.
		$users = new EMCP_Tools_User_Abilities();
		$users->register();
		$this->ability_names = array_merge( $this->ability_names, $users->get_ability_names() );

		// ACF abilities — only when Advanced Custom Fields (free or Pro) is active.
		if ( class_exists( 'EMCP_Tools_ACF_Abilities' ) && EMCP_Tools_ACF_Abilities::acf_active() ) {
			$acf = new EMCP_Tools_ACF_Abilities();
			$acf->register();
			$this->ability_names = array_merge( $this->ability_names, $acf->get_ability_names() );
		}

		// Performance Analyzer (read-only).
		$performance = new EMCP_Tools_Performance_Abilities();
		$performance->register();
		$this->ability_names = array_merge( $this->ability_names, $performance->get_ability_names() );

		// Filesystem abilities (writes disabled-by-default).
		$filesystem = new EMCP_Tools_Filesystem_Abilities();
		$filesystem->register();
		$this->ability_names = array_merge( $this->ability_names, $filesystem->get_ability_names() );

		// Database abilities (writes disabled-by-default).
		$database = new EMCP_Tools_Database_Abilities();
		$database->register();
		$this->ability_names = array_merge( $this->ability_names, $database->get_ability_names() );

		// Security & Malware Scanner (read-only).
		$security = new EMCP_Tools_Security_Abilities();
		$security->register();
		$this->ability_names = array_merge( $this->ability_names, $security->get_ability_names() );

		// PHP Snippet abilities (Sandbox) — free, capability-gated, no Elementor.
		if ( class_exists( 'EMCP_Tools_PHP_Snippet_Abilities' ) ) {
			$php_snippets = new EMCP_Tools_PHP_Snippet_Abilities();
			$php_snippets->register();
			$this->ability_names = array_merge( $this->ability_names, $php_snippets->get_ability_names() );
		}

		// ---- Elementor-dependent groups: only when Elementor is active ----
		if ( $elementor_active ) {
			// P0 query/discovery.
			$query = new EMCP_Tools_Query_Abilities( $this->data, $this->schema_generator );
			$query->register();
			$this->ability_names = array_merge( $this->ability_names, $query->get_ability_names() );

			// P1 page CRUD.
			$pages = new EMCP_Tools_Page_Abilities( $this->data, $this->factory );
			$pages->register();
			$this->ability_names = array_merge( $this->ability_names, $pages->get_ability_names() );

			// P1 layout/container.
			$layout = new EMCP_Tools_Layout_Abilities( $this->data, $this->factory );
			$layout->register();
			$this->ability_names = array_merge( $this->ability_names, $layout->get_ability_names() );

			// Widgets (catalog-backed).
			$widgets = new EMCP_Tools_Widget_Abilities( $this->data, $this->factory, $this->schema_generator, $this->validator );
			$widgets->register();
			$this->ability_names = array_merge( $this->ability_names, $widgets->get_ability_names() );

			// Templates.
			$templates = new EMCP_Tools_Template_Abilities( $this->data, $this->factory );
			$templates->register();
			$this->ability_names = array_merge( $this->ability_names, $templates->get_ability_names() );

			// Global settings.
			$globals = new EMCP_Tools_Global_Abilities( $this->data );
			$globals->register();
			$this->ability_names = array_merge( $this->ability_names, $globals->get_ability_names() );

			// Composite build-page.
			$composite = new EMCP_Tools_Composite_Abilities( $this->data, $this->factory );
			$composite->register();
			$this->ability_names = array_merge( $this->ability_names, $composite->get_ability_names() );

			// Stock images.
			$stock_images = new EMCP_Tools_Stock_Image_Abilities( $this->data, $this->factory );
			$stock_images->register();
			$this->ability_names = array_merge( $this->ability_names, $stock_images->get_ability_names() );

			// SVG icons.
			$svg_icons = new EMCP_Tools_Svg_Icon_Abilities( $this->data, $this->factory );
			$svg_icons->register();
			$this->ability_names = array_merge( $this->ability_names, $svg_icons->get_ability_names() );

			// Custom code (CSS, JS, snippets).
			$custom_code = new EMCP_Tools_Custom_Code_Abilities( $this->data, $this->factory );
			$custom_code->register();
			$this->ability_names = array_merge( $this->ability_names, $custom_code->get_ability_names() );

			// Atomic widgets (Elementor 4.0+; self-guards on version).
			$atomic_widgets = new EMCP_Tools_Atomic_Widget_Abilities( $this->data, $this->factory );
			$atomic_widgets->register();
			$this->ability_names = array_merge( $this->ability_names, $atomic_widgets->get_ability_names() );

			// Atomic layout (Elementor 4.0+; includes detect-elementor-version).
			$atomic_layout = new EMCP_Tools_Atomic_Layout_Abilities( $this->data, $this->factory );
			$atomic_layout->register();
			$this->ability_names = array_merge( $this->ability_names, $atomic_layout->get_ability_names() );

			// Global Classes reader — self-gates on Elementor 4.0+.
			if ( class_exists( 'EMCP_Tools_Global_Classes_Abilities' ) ) {
				$global_classes = new EMCP_Tools_Global_Classes_Abilities();
				$global_classes->register();
				$this->ability_names = array_merge( $this->ability_names, $global_classes->get_ability_names() );
			}

			// Brand kit / system-kit (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_System_Kit_Abilities' ) ) {
				$brand_kits = new EMCP_Tools_System_Kit_Abilities();
				$brand_kits->register();
				$this->ability_names = array_merge( $this->ability_names, $brand_kits->get_ability_names() );
			}

			// SEO toolkit (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_Seo_Abilities' ) ) {
				$seo = new EMCP_Tools_Seo_Abilities( $this->data );
				$seo->register();
				$this->ability_names = array_merge( $this->ability_names, $seo->get_ability_names() );
			}

			// Accessibility toolkit (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_A11y_Abilities' ) ) {
				$a11y = new EMCP_Tools_A11y_Abilities( $this->data );
				$a11y->register();
				$this->ability_names = array_merge( $this->ability_names, $a11y->get_ability_names() );
			}

			// Widget Builder (Pro; self-guards on license).
			if ( class_exists( 'EMCP_Tools_Widget_Builder_Abilities' ) ) {
				$widget_builder = new EMCP_Tools_Widget_Builder_Abilities();
				$widget_builder->register();
				$this->ability_names = array_merge( $this->ability_names, $widget_builder->get_ability_names() );
			}
		}

		// Skills read-side (Pro; self-guards on license). Not Elementor-dependent,
		// so it registers regardless of whether Elementor is active — but gated by
		// the Agent Skills module so the admin can switch the runtime exposure off.
		if ( class_exists( 'EMCP_Tools_Skill_Abilities' )
			&& class_exists( 'EMCP_Tools_Agent_Skills_Module' )
			&& EMCP_Tools_Agent_Skills_Module::is_enabled() ) {
			$skills = new EMCP_Tools_Skill_Abilities();
			$skills->register();
			$this->ability_names = array_merge( $this->ability_names, $skills->get_ability_names() );
		}

		/**
		 * Filters the registered ability names.
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

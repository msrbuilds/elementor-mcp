<?php
/**
 * Widget MCP abilities for Elementor.
 *
 * Registers two catalog-backed insert tools — add-free-widget and
 * add-pro-widget (the latter only when Elementor Pro is active) — which
 * validate the requested widget's tier against EMCP_Tools_Widget_Catalog,
 * merge catalog defaults, and delegate to the shared execute_add_widget
 * engine. Also registers the universal update-widget tool.
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the widget abilities.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Widget_Abilities {

	/**
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * @var EMCP_Tools_Element_Factory
	 */
	private $factory;

	/**
	 * @var EMCP_Tools_Schema_Generator
	 */
	private $schema_generator;

	/**
	 * @var EMCP_Tools_Settings_Validator
	 */
	private $validator;

	/**
	 * Tracked ability names.
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
	 * Returns the ability names registered by this class.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Registers all widget abilities.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		// Universal/engine tools.
		$this->register_add_free_widget();
		$this->register_update_widget();

		// Pro insert — only when Elementor Pro is active (natural tier gate).
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$this->register_add_pro_widget();
		}
	}

	/**
	 * Permission check for widget editing.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $input The input data.
	 * @return bool
	 */
	public function check_edit_permission( $input = null ): bool {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return false;
		}

		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return false;
		}

		return true;
	}

	// =========================================================================
	// Catalog-backed insert tools: add-free-widget / add-pro-widget
	// =========================================================================

	/**
	 * Shared input schema for the catalog insert tools.
	 *
	 * @return array
	 */
	private function catalog_insert_input_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'post_id'     => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'emcp-tools' ) ),
				'parent_id'   => array( 'type' => 'string', 'description' => __( 'Parent container element ID.', 'emcp-tools' ) ),
				'position'    => array( 'type' => 'integer', 'description' => __( 'Insert position. -1 = append.', 'emcp-tools' ) ),
				'widget_type' => array( 'type' => 'string', 'description' => __( 'Widget type from list-widgets. Use get-widget-schema for its curated params.', 'emcp-tools' ) ),
				'settings'    => array( 'type' => 'object', 'description' => __( 'Widget settings (see get-widget-schema). Any valid Elementor control passes through.', 'emcp-tools' ) ),
			),
			'required'   => array( 'post_id', 'parent_id', 'widget_type' ),
		);
	}

	/**
	 * Registers the add-free-widget catalog insert tool.
	 *
	 * @since 3.0.0
	 */
	private function register_add_free_widget(): void {
		$this->ability_names[] = 'emcp-tools/add-free-widget';
		emcp_tools_register_ability(
			'emcp-tools/add-free-widget',
			array(
				'label'               => __( 'Add Widget', 'emcp-tools' ),
				'description'         => __( 'Adds any free/core Elementor widget to a container. Discover types with list-widgets and their settings with get-widget-schema. Catalog defaults are merged automatically.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_free_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => $this->catalog_insert_input_schema(),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id'  => array( 'type' => 'string' ),
						'widget_type' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Registers the add-pro-widget catalog insert tool (Pro-gated).
	 *
	 * @since 3.0.0
	 */
	private function register_add_pro_widget(): void {
		$this->ability_names[] = 'emcp-tools/add-pro-widget';
		emcp_tools_register_ability(
			'emcp-tools/add-pro-widget',
			array(
				'label'               => __( 'Add Pro Widget', 'emcp-tools' ),
				'description'         => __( 'Adds an Elementor Pro (or WooCommerce) widget to a container. Discover types with list-widgets (tier:pro) and settings with get-widget-schema. Only available when Elementor Pro is active.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_pro_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => $this->catalog_insert_input_schema(),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id'  => array( 'type' => 'string' ),
						'widget_type' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the add-free-widget ability (free-tier insert).
	 *
	 * @since 3.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_free_widget( $input ) {
		return $this->insert_catalog_widget( $input, 'free' );
	}

	/**
	 * Executes the add-pro-widget ability (Pro/Woo-tier insert).
	 *
	 * @since 3.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_pro_widget( $input ) {
		return $this->insert_catalog_widget( $input, 'pro' );
	}

	/**
	 * Validates the requested widget against the catalog tier, merges catalog
	 * defaults, then delegates to the shared insert engine.
	 *
	 * @param array  $input         Tool input.
	 * @param string $expected_tier 'free' = free only; 'pro' = pro OR woo.
	 * @return array|\WP_Error
	 */
	private function insert_catalog_widget( $input, string $expected_tier ) {
		$widget_type = sanitize_text_field( $input['widget_type'] ?? '' );
		if ( '' === $widget_type ) {
			return new \WP_Error( 'missing_params', __( 'widget_type is required.', 'emcp-tools' ) );
		}

		$entry  = EMCP_Tools_Widget_Catalog::get_widget( $widget_type );
		$is_pro = EMCP_Tools_Widget_Catalog::is_pro( $widget_type );

		// Tier gate. 'free' tool: reject Pro/Woo types. 'pro' tool: reject free types.
		if ( 'free' === $expected_tier && $is_pro ) {
			return new \WP_Error( 'wrong_tier', __( 'That is a Pro widget — use add-pro-widget.', 'emcp-tools' ) );
		}
		if ( 'pro' === $expected_tier && null !== $entry && ! $is_pro ) {
			return new \WP_Error( 'wrong_tier', __( 'That is a free widget — use add-free-widget.', 'emcp-tools' ) );
		}

		// Merge catalog defaults under the caller's settings (caller wins).
		$settings = is_array( $input['settings'] ?? null ) ? $input['settings'] : array();
		if ( null !== $entry && ! empty( $entry['defaults'] ) ) {
			$settings = array_merge( $entry['defaults'], $settings );
		}

		return $this->execute_add_widget(
			array(
				'post_id'     => $input['post_id'] ?? 0,
				'parent_id'   => $input['parent_id'] ?? '',
				'position'    => $input['position'] ?? -1,
				'widget_type' => $widget_type,
				'settings'    => $settings,
			)
		);
	}

	/**
	 * Executes the add-widget ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_widget( $input ) {
		$post_id     = absint( $input['post_id'] ?? 0 );
		$parent_id   = sanitize_text_field( $input['parent_id'] ?? '' );
		$position    = intval( $input['position'] ?? -1 );
		$widget_type = sanitize_text_field( $input['widget_type'] ?? '' );
		$settings    = $input['settings'] ?? array();

		if ( ! $post_id || empty( $parent_id ) || empty( $widget_type ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, parent_id, and widget_type are required.', 'emcp-tools' ) );
		}

		// Validate widget type exists.
		$widget_instance = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
		if ( ! $widget_instance ) {
			return new \WP_Error(
				'invalid_widget_type',
				/* translators: %s: widget type name */
				sprintf( __( 'Widget type "%s" not found.', 'emcp-tools' ), $widget_type )
			);
		}

		// Validate settings if provided.
		if ( ! empty( $settings ) ) {
			$valid = $this->validator->validate( $widget_type, $settings );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$widget = $this->factory->create_widget( $widget_type, $settings );

		$inserted = $this->data->insert_element( $page_data, $parent_id, $widget, $position );

		if ( ! $inserted ) {
			return new \WP_Error( 'parent_not_found', __( 'Parent container not found.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'element_id'  => $widget['id'],
			'widget_type' => $widget_type,
		);
	}

	// =========================================================================
	// Universal: update-widget
	// =========================================================================

	private function register_update_widget(): void {
		$this->ability_names[] = 'emcp-tools/update-widget';

		emcp_tools_register_ability(
			'emcp-tools/update-widget',
			array(
				'label'               => __( 'Update Widget', 'emcp-tools' ),
				'description'         => __( 'Updates settings on an existing widget. Settings are merged (partial update).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'The widget element ID.', 'emcp-tools' ),
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => __( 'Partial settings to merge.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'element_id', 'settings' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'element_id' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the update-widget ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_update_widget( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $input['element_id'] ?? '' );
		$settings   = $input['settings'] ?? array();

		if ( ! $post_id || empty( $element_id ) || empty( $settings ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, element_id, and settings are required.', 'emcp-tools' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Find the widget to validate its type.
		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'emcp-tools' ) );
		}

		if ( ( $element['elType'] ?? '' ) !== 'widget' ) {
			return new \WP_Error( 'not_a_widget', __( 'Target element is not a widget.', 'emcp-tools' ) );
		}

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update widget settings.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'    => true,
			'element_id' => $element_id,
		);
	}

}

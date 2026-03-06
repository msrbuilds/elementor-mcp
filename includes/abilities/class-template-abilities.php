<?php
/**
 * Template MCP abilities for Elementor.
 *
 * Registers 2 tools for saving and applying Elementor templates.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the template abilities.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Template_Abilities {

	/**
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * @var Elementor_MCP_Element_Factory
	 */
	private $factory;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Elementor_MCP_Data            $data    The data access layer.
	 * @param Elementor_MCP_Element_Factory $factory The element factory.
	 */
	public function __construct( Elementor_MCP_Data $data, Elementor_MCP_Element_Factory $factory ) {
		$this->data    = $data;
		$this->factory = $factory;
	}

	/**
	 * Returns the ability names registered by this class.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		$names = array(
			'elementor-mcp/save-as-template',
			'elementor-mcp/apply-template',
		);

		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$names[] = 'elementor-mcp/create-theme-template';
			$names[] = 'elementor-mcp/set-template-conditions';
			$names[] = 'elementor-mcp/list-dynamic-tags';
			$names[] = 'elementor-mcp/set-dynamic-tag';
			$names[] = 'elementor-mcp/create-popup';
			$names[] = 'elementor-mcp/set-popup-settings';
		}

		return $names;
	}

	/**
	 * Registers all template abilities.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		$this->register_save_as_template();
		$this->register_apply_template();

		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$this->register_create_theme_template();
			$this->register_set_template_conditions();
			$this->register_list_dynamic_tags();
			$this->register_set_dynamic_tag();
			$this->register_create_popup();
			$this->register_set_popup_settings();
		}
	}

	/**
	 * Permission check for template operations.
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

	// -------------------------------------------------------------------------
	// save-as-template
	// -------------------------------------------------------------------------

	private function register_save_as_template(): void {
		wp_register_ability(
			'elementor-mcp/save-as-template',
			array(
				'label'               => __( 'Save As Template', 'elementor-mcp' ),
				'description'         => __( 'Saves a page or a specific element as a reusable Elementor template.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_save_as_template' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => __( 'The source post/page ID.', 'elementor-mcp' ),
						),
						'element_id'    => array(
							'type'        => 'string',
							'description' => __( 'Specific element ID to save. Omit to save the entire page.', 'elementor-mcp' ),
						),
						'title'         => array(
							'type'        => 'string',
							'description' => __( 'Template title.', 'elementor-mcp' ),
						),
						'template_type' => array(
							'type'        => 'string',
							'enum'        => array( 'page', 'section', 'container' ),
							'description' => __( 'Template type. Default: page.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'title' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'template_id' => array( 'type' => 'integer' ),
						'title'       => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the save-as-template ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_save_as_template( $input ) {
		$post_id       = absint( $input['post_id'] ?? 0 );
		$element_id    = sanitize_text_field( $input['element_id'] ?? '' );
		$title         = sanitize_text_field( $input['title'] ?? '' );
		$template_type = sanitize_key( $input['template_type'] ?? 'page' );

		if ( ! $post_id || empty( $title ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id and title are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Get the elements to save.
		if ( ! empty( $element_id ) ) {
			$element = $this->data->find_element_by_id( $page_data, $element_id );
			if ( null === $element ) {
				return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
			}
			$elements_data = array( $element );
		} else {
			$elements_data = $page_data;
		}

		// Create the template post in Elementor's library CPT.
		$template_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => 'elementor_library',
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => $template_type,
				),
			),
			true
		);

		if ( is_wp_error( $template_id ) ) {
			return $template_id;
		}

		// Set the template type taxonomy.
		wp_set_object_terms( $template_id, $template_type, 'elementor_library_type' );

		// Save the element data to the template.
		$save_result = $this->data->save_page_data( $template_id, $elements_data );

		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		return array(
			'template_id' => $template_id,
			'title'       => $title,
		);
	}

	// -------------------------------------------------------------------------
	// apply-template
	// -------------------------------------------------------------------------

	private function register_apply_template(): void {
		wp_register_ability(
			'elementor-mcp/apply-template',
			array(
				'label'               => __( 'Apply Template', 'elementor-mcp' ),
				'description'         => __( 'Applies a saved Elementor template to a page at a given position, inserting its elements with fresh IDs.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_apply_template' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array(
							'type'        => 'integer',
							'description' => __( 'The target post/page ID.', 'elementor-mcp' ),
						),
						'template_id' => array(
							'type'        => 'integer',
							'description' => __( 'The template post ID to apply.', 'elementor-mcp' ),
						),
						'parent_id'   => array(
							'type'        => 'string',
							'description' => __( 'Parent container ID. Empty for top-level.', 'elementor-mcp' ),
						),
						'position'    => array(
							'type'        => 'integer',
							'description' => __( 'Insert position. -1 = append.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'template_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'elements_added' => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the apply-template ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_apply_template( $input ) {
		$post_id     = absint( $input['post_id'] ?? 0 );
		$template_id = absint( $input['template_id'] ?? 0 );
		$parent_id   = sanitize_text_field( $input['parent_id'] ?? '' );
		$position    = intval( $input['position'] ?? -1 );

		if ( ! $post_id || ! $template_id ) {
			return new \WP_Error( 'missing_params', __( 'post_id and template_id are required.', 'elementor-mcp' ) );
		}

		// Get the template elements.
		$template_data = $this->data->get_page_data( $template_id );

		if ( is_wp_error( $template_data ) ) {
			return $template_data;
		}

		if ( empty( $template_data ) ) {
			return new \WP_Error( 'empty_template', __( 'Template has no elements.', 'elementor-mcp' ) );
		}

		// Get the target page data.
		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Reassign IDs to prevent collisions.
		$template_data = $this->data->reassign_ids( $template_data );
		$count         = $this->data->count_elements( $template_data );

		// Insert template elements.
		if ( ! empty( $parent_id ) ) {
			// Insert each template element into the parent.
			foreach ( $template_data as $i => $element ) {
				$pos      = ( $position >= 0 ) ? $position + $i : -1;
				$inserted = $this->data->insert_element( $page_data, $parent_id, $element, $pos );

				if ( ! $inserted ) {
					return new \WP_Error(
						'parent_not_found',
						sprintf(
							/* translators: %s: parent element ID */
							__( 'Parent element "%s" not found.', 'elementor-mcp' ),
							$parent_id
						)
					);
				}
			}
		} else {
			// Top-level insertion.
			if ( $position < 0 || $position >= count( $page_data ) ) {
				$page_data = array_merge( $page_data, $template_data );
			} else {
				array_splice( $page_data, $position, 0, $template_data );
			}
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'        => true,
			'elements_added' => $count,
		);
	}

	// ── Phase 6: Theme Builder Template Tools ─────────────────────────

	private function register_create_theme_template(): void {
		wp_register_ability(
			'elementor-mcp/create-theme-template',
			array(
				'label'               => __( 'Create Theme Template', 'elementor-mcp' ),
				'description'         => __( 'Creates a new Elementor Pro theme builder template (header, footer, single, archive, 404, etc.).', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_create_theme_template' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'         => array(
							'type'        => 'string',
							'description' => __( 'Template title.', 'elementor-mcp' ),
						),
						'template_type' => array(
							'type'        => 'string',
							'enum'        => array( 'header', 'footer', 'single', 'single-post', 'single-page', 'archive', 'search-results', 'error-404', 'loop-item' ),
							'description' => __( 'Theme template type.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'title', 'template_type' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer' ),
						'title'    => array( 'type' => 'string' ),
						'edit_url' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function execute_create_theme_template( $input ) {
		$title         = sanitize_text_field( $input['title'] ?? '' );
		$template_type = sanitize_key( $input['template_type'] ?? '' );

		if ( empty( $title ) || empty( $template_type ) ) {
			return new \WP_Error( 'missing_params', __( 'title and template_type are required.', 'elementor-mcp' ) );
		}

		// Create the template post.
		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => 'elementor_library',
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => $template_type,
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_object_terms( $post_id, $template_type, 'elementor_library_type' );

		// Initialize with empty Elementor data.
		$this->data->save_page_data( $post_id, array() );

		return array(
			'post_id'  => $post_id,
			'title'    => $title,
			'edit_url' => admin_url( "post.php?post={$post_id}&action=elementor" ),
		);
	}

	private function register_set_template_conditions(): void {
		wp_register_ability(
			'elementor-mcp/set-template-conditions',
			array(
				'label'               => __( 'Set Template Conditions', 'elementor-mcp' ),
				'description'         => __( 'Sets display conditions for a theme builder template (e.g., Entire Site, specific pages, post types).', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_set_template_conditions' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The template post ID.', 'elementor-mcp' ),
						),
						'conditions' => array(
							'type'        => 'array',
							'description' => __( 'Array of condition rules. Each is an array like ["include", "general"] for Entire Site, or ["include", "singular", "post"] for all posts.', 'elementor-mcp' ),
							'items'       => array(
								'type'  => 'array',
								'items' => array( 'type' => 'string' ),
							),
						),
					),
					'required'   => array( 'post_id', 'conditions' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function execute_set_template_conditions( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$conditions = $input['conditions'] ?? array();

		if ( ! $post_id || empty( $conditions ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id and conditions are required.', 'elementor-mcp' ) );
		}

		// Elementor Pro stores conditions in the meta key '_elementor_conditions'.
		$formatted = array();
		foreach ( $conditions as $condition ) {
			if ( is_array( $condition ) ) {
				$formatted[] = implode( '/', $condition );
			} elseif ( is_string( $condition ) ) {
				$formatted[] = $condition;
			}
		}

		update_post_meta( $post_id, '_elementor_conditions', $formatted );

		// Clear Elementor Pro's conditions cache if available.
		if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
			delete_option( 'elementor_pro_theme_builder_conditions' );
		}

		return array( 'success' => true );
	}

	// ── Phase 6: Dynamic Tags ─────────────────────────────────────────

	private function register_list_dynamic_tags(): void {
		wp_register_ability(
			'elementor-mcp/list-dynamic-tags',
			array(
				'label'               => __( 'List Dynamic Tags', 'elementor-mcp' ),
				'description'         => __( 'Lists all available Elementor Pro dynamic tags with their names, groups, and categories.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_list_dynamic_tags' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'group' => array(
							'type'        => 'string',
							'description' => __( 'Filter by tag group (e.g., "post", "site", "author", "media", "action", "woocommerce"). Omit for all.', 'elementor-mcp' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'tags'  => array( 'type' => 'array' ),
						'count' => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function execute_list_dynamic_tags( $input ) {
		$filter_group = sanitize_text_field( $input['group'] ?? '' );

		$dynamic_tags_manager = \Elementor\Plugin::instance()->dynamic_tags;
		if ( ! $dynamic_tags_manager ) {
			return new \WP_Error( 'no_dynamic_tags', __( 'Dynamic tags manager not available.', 'elementor-mcp' ) );
		}

		$tags_info = $dynamic_tags_manager->get_tags();
		$tags      = array();

		foreach ( $tags_info as $tag_name => $tag_info ) {
			if ( ! is_array( $tag_info ) || empty( $tag_info['instance'] ) ) {
				continue;
			}

			$tag_instance = $tag_info['instance'];
			$group        = method_exists( $tag_instance, 'get_group' ) ? $tag_instance->get_group() : '';

			if ( ! empty( $filter_group ) && $group !== $filter_group ) {
				continue;
			}

			$tags[] = array(
				'name'       => $tag_name,
				'title'      => method_exists( $tag_instance, 'get_title' ) ? $tag_instance->get_title() : $tag_name,
				'group'      => $group,
				'categories' => method_exists( $tag_instance, 'get_categories' ) ? $tag_instance->get_categories() : array(),
			);
		}

		return array(
			'tags'  => $tags,
			'count' => count( $tags ),
		);
	}

	private function register_set_dynamic_tag(): void {
		wp_register_ability(
			'elementor-mcp/set-dynamic-tag',
			array(
				'label'               => __( 'Set Dynamic Tag', 'elementor-mcp' ),
				'description'         => __( 'Sets a dynamic tag on a specific setting of an element. This makes the setting value dynamic (e.g., title becomes post title).', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_set_dynamic_tag' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array(
							'type'        => 'integer',
							'description' => __( 'The page/post ID.', 'elementor-mcp' ),
						),
						'element_id'  => array(
							'type'        => 'string',
							'description' => __( 'The element ID to modify.', 'elementor-mcp' ),
						),
						'setting_key' => array(
							'type'        => 'string',
							'description' => __( 'The setting key to make dynamic (e.g., "title", "url", "image").', 'elementor-mcp' ),
						),
						'tag_name'    => array(
							'type'        => 'string',
							'description' => __( 'The dynamic tag name (e.g., "post-title", "site-title", "post-featured-image").', 'elementor-mcp' ),
						),
						'tag_settings' => array(
							'type'        => 'object',
							'description' => __( 'Optional settings for the dynamic tag.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'element_id', 'setting_key', 'tag_name' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function execute_set_dynamic_tag( $input ) {
		$post_id      = absint( $input['post_id'] ?? 0 );
		$element_id   = sanitize_text_field( $input['element_id'] ?? '' );
		$setting_key  = sanitize_text_field( $input['setting_key'] ?? '' );
		$tag_name     = sanitize_text_field( $input['tag_name'] ?? '' );
		$tag_settings = $input['tag_settings'] ?? array();

		if ( ! $post_id || empty( $element_id ) || empty( $setting_key ) || empty( $tag_name ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, element_id, setting_key, and tag_name are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Find the element to read its current __dynamic__ settings.
		$element = $this->data->find_element_by_id( $page_data, $element_id );
		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
		}

		// Build the dynamic tag value in Elementor's format.
		$tag_id  = wp_rand( 1000000, 9999999 );
		$encoded = '[elementor-tag id="' . $tag_id . '" name="' . $tag_name . '" settings="' . urlencode( wp_json_encode( $tag_settings, JSON_FORCE_OBJECT ) ) . '"]';

		// Merge with existing __dynamic__ settings.
		$dynamic = $element['settings']['__dynamic__'] ?? array();
		$dynamic[ $setting_key ] = $encoded;

		// Use update_element_settings to write back (operates by reference on $page_data).
		$updated = $this->data->update_element_settings( $page_data, $element_id, array( '__dynamic__' => $dynamic ) );
		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update element settings.', 'elementor-mcp' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'success' => true );
	}

	// ── Phase 6: Popup Builder ────────────────────────────────────────

	private function register_create_popup(): void {
		wp_register_ability(
			'elementor-mcp/create-popup',
			array(
				'label'               => __( 'Create Popup', 'elementor-mcp' ),
				'description'         => __( 'Creates a new Elementor Pro popup template.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_create_popup' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title' => array(
							'type'        => 'string',
							'description' => __( 'Popup title.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'title' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array( 'type' => 'integer' ),
						'title'    => array( 'type' => 'string' ),
						'edit_url' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function execute_create_popup( $input ) {
		$title = sanitize_text_field( $input['title'] ?? '' );

		if ( empty( $title ) ) {
			return new \WP_Error( 'missing_params', __( 'title is required.', 'elementor-mcp' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => 'elementor_library',
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => 'popup',
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		wp_set_object_terms( $post_id, 'popup', 'elementor_library_type' );
		$this->data->save_page_data( $post_id, array() );

		return array(
			'post_id'  => $post_id,
			'title'    => $title,
			'edit_url' => admin_url( "post.php?post={$post_id}&action=elementor" ),
		);
	}

	private function register_set_popup_settings(): void {
		wp_register_ability(
			'elementor-mcp/set-popup-settings',
			array(
				'label'               => __( 'Set Popup Settings', 'elementor-mcp' ),
				'description'         => __( 'Configures popup triggers, timing, and display conditions for an Elementor Pro popup.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_set_popup_settings' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The popup post ID.', 'elementor-mcp' ),
						),
						'triggers'   => array(
							'type'        => 'object',
							'description' => __( 'Trigger settings: { "on_page_load": {"enabled": true, "delay": 3}, "on_scroll": {"enabled": true, "direction": "down", "offset": 50}, "on_click": {"enabled": true, "times": 1}, "on_exit_intent": {"enabled": true}, "on_inactivity": {"enabled": true, "time": 30} }.', 'elementor-mcp' ),
						),
						'conditions' => array(
							'type'        => 'array',
							'description' => __( 'Display conditions, same format as set-template-conditions.', 'elementor-mcp' ),
							'items'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						),
						'timing'     => array(
							'type'        => 'object',
							'description' => __( 'Timing rules: { "devices": ["desktop","tablet","mobile"], "show_after_x_page_views": 0, "show_after_x_sessions": 0, "show_up_to_x_times": 0, "url_contains": "", "url_not_contains": "" }.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function execute_set_popup_settings( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$triggers   = $input['triggers'] ?? null;
		$conditions = $input['conditions'] ?? null;
		$timing     = $input['timing'] ?? null;

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_params', __( 'post_id is required.', 'elementor-mcp' ) );
		}

		// Elementor Pro stores popup settings in post meta.
		if ( null !== $triggers ) {
			update_post_meta( $post_id, '_elementor_popup_triggers', $triggers );
		}

		if ( null !== $conditions ) {
			$formatted = array();
			foreach ( $conditions as $condition ) {
				if ( is_array( $condition ) ) {
					$formatted[] = implode( '/', $condition );
				} elseif ( is_string( $condition ) ) {
					$formatted[] = $condition;
				}
			}
			update_post_meta( $post_id, '_elementor_conditions', $formatted );

			if ( class_exists( '\ElementorPro\Modules\ThemeBuilder\Module' ) ) {
				delete_option( 'elementor_pro_theme_builder_conditions' );
			}
		}

		if ( null !== $timing ) {
			update_post_meta( $post_id, '_elementor_popup_timing', $timing );
		}

		return array( 'success' => true );
	}
}

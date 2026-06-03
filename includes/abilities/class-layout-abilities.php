<?php
/**
 * Layout/container MCP abilities for Elementor.
 *
 * Registers 4 tools for adding containers, moving, removing,
 * and duplicating elements within Elementor page trees.
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the layout abilities.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Layout_Abilities {

	/**
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * @var EMCP_Tools_Element_Factory
	 */
	private $factory;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param EMCP_Tools_Data            $data    The data access layer.
	 * @param EMCP_Tools_Element_Factory $factory The element factory.
	 */
	public function __construct( EMCP_Tools_Data $data, EMCP_Tools_Element_Factory $factory ) {
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
		return array(
			'elementor-mcp/add-container',
			'elementor-mcp/update-container',
			'elementor-mcp/update-element',
			'elementor-mcp/batch-update',
			'elementor-mcp/reorder-elements',
			'elementor-mcp/move-element',
			'elementor-mcp/remove-element',
			'elementor-mcp/duplicate-element',
		);
	}

	/**
	 * Registers all layout abilities.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		$this->register_add_container();
		$this->register_update_container();
		$this->register_update_element();
		$this->register_batch_update();
		$this->register_reorder_elements();
		$this->register_move_element();
		$this->register_remove_element();
		$this->register_duplicate_element();
	}

	/**
	 * Permission check for element editing.
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
	// add-container
	// -------------------------------------------------------------------------

	private function register_add_container(): void {
		emcp_tools_register_ability(
			'elementor-mcp/add-container',
			array(
				'label'               => __( 'Add Container', 'emcp-tools' ),
				'description'         => __( 'Adds a container to a page. Supports both flex (default) and grid layouts via container_type. Omit parent_id for top-level, or provide a parent container ID for nesting. Flex tips: Use flex_direction=row for side-by-side children, flex_wrap=wrap for wrapping, flex_justify_content for main-axis alignment (e.g. space-between, center), flex_align_items for cross-axis alignment. (The shorthand justify_content / align_items are also accepted and remapped to flex_justify_content / flex_align_items.) Grid tips: Set container_type=grid with grid_columns_grid, grid_rows_grid, grid_gaps. Background: set background_background=classic and background_color=#hex. Border: set border_border=solid, border_width, border_color. Also supports min_height, overflow, html_tag, padding, margin, position, z_index, animation.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_container' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'parent_id' => array(
							'type'        => 'string',
							'description' => __( 'Parent container ID for nesting. Omit for top-level.', 'emcp-tools' ),
						),
						'position'  => array(
							'type'        => 'integer',
							'description' => __( 'Insert position. -1 = append (default).', 'emcp-tools' ),
						),
						'settings'  => array(
							'type'        => 'object',
							'description' => __( 'Container settings: flex_direction, flex_wrap, flex_justify_content, flex_align_items, gap, content_width, padding, margin, background, border, etc. (Unprefixed justify_content / align_items / align_content are accepted and remapped to the flex_-prefixed keys.)', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id' => array( 'type' => 'string' ),
						'post_id'    => array( 'type' => 'integer' ),
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
	 * Executes the add-container ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_container( $input ) {
		$post_id   = absint( $input['post_id'] ?? 0 );
		$parent_id = sanitize_text_field( $input['parent_id'] ?? '' );
		$position  = intval( $input['position'] ?? -1 );
		$settings  = $input['settings'] ?? array();

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// When nesting inside a parent, mark as inner container.
		$container = $this->factory->create_container( $settings );
		if ( ! empty( $parent_id ) ) {
			$container['isInner'] = true;
		}

		$inserted = $this->data->insert_element( $page_data, $parent_id, $container, $position );

		if ( ! $inserted ) {
			return new \WP_Error(
				'parent_not_found',
				sprintf(
					/* translators: %s: parent element ID */
					__( 'Parent element "%s" not found.', 'emcp-tools' ),
					$parent_id
				)
			);
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'element_id' => $container['id'],
			'post_id'    => $post_id,
		);
	}

	// -------------------------------------------------------------------------
	// update-container
	// -------------------------------------------------------------------------

	private function register_update_container(): void {
		emcp_tools_register_ability(
			'elementor-mcp/update-container',
			array(
				'label'               => __( 'Update Container', 'emcp-tools' ),
				'description'         => __( 'Updates settings on an existing container. Settings are merged (partial update). Supports all container controls: flex_direction, flex_justify_content, flex_align_items, flex_wrap, flex_align_content, gap, content_width, min_height, overflow, html_tag, container_type, grid controls, background (set background_background=classic first), border (set border_border=solid first), border_radius, box_shadow, padding, margin, position, z_index, animation, shape dividers, etc. (The unprefixed justify_content / align_items / align_content are accepted and remapped to the flex_-prefixed keys.)', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_container' ),
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
							'description' => __( 'The container element ID.', 'emcp-tools' ),
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => __( 'Partial settings to merge into the container.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'element_id', 'settings' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
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
	 * Executes the update-container ability.
	 *
	 * @since 1.1.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_update_container( $input ) {
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

		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'emcp-tools' ) );
		}

		if ( 'container' !== ( $element['elType'] ?? '' ) ) {
			return new \WP_Error( 'not_container', __( 'Element is not a container. Use update-widget for widgets.', 'emcp-tools' ) );
		}

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update container settings.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'success' => true );
	}

	// -------------------------------------------------------------------------
	// update-element (universal — works for both containers and widgets)
	// -------------------------------------------------------------------------

	private function register_update_element(): void {
		emcp_tools_register_ability(
			'elementor-mcp/update-element',
			array(
				'label'               => __( 'Update Element', 'emcp-tools' ),
				'description'         => __( 'Updates settings on any element (container or widget). Settings are merged (partial update). Works for all element types — no need to know if the target is a container or widget.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_element' ),
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
							'description' => __( 'The element ID (container or widget).', 'emcp-tools' ),
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => __( 'Partial settings to merge into the element.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'element_id', 'settings' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'element_id'  => array( 'type' => 'string' ),
						'element_type' => array( 'type' => 'string' ),
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

	public function execute_update_element( $input ) {
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

		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'emcp-tools' ) );
		}

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update element settings.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'      => true,
			'element_id'   => $element_id,
			'element_type' => $element['elType'] ?? 'unknown',
		);
	}

	// -------------------------------------------------------------------------
	// batch-update
	// -------------------------------------------------------------------------

	private function register_batch_update(): void {
		emcp_tools_register_ability(
			'elementor-mcp/batch-update',
			array(
				'label'               => __( 'Batch Update Elements', 'emcp-tools' ),
				'description'         => __( 'Updates multiple elements in a single save operation. Each operation specifies an element_id and settings to merge. Much more efficient than calling update-element multiple times.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_batch_update' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'operations' => array(
							'type'        => 'array',
							'description' => __( 'Array of update operations.', 'emcp-tools' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'element_id' => array( 'type' => 'string', 'description' => __( 'Element ID to update.', 'emcp-tools' ) ),
									'settings'   => array( 'type' => 'object', 'description' => __( 'Settings to merge.', 'emcp-tools' ) ),
								),
								'required'   => array( 'element_id', 'settings' ),
							),
						),
					),
					'required'   => array( 'post_id', 'operations' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'updated'  => array( 'type' => 'integer' ),
						'failed'   => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
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

	public function execute_batch_update( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$operations = $input['operations'] ?? array();

		if ( ! $post_id || empty( $operations ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id and operations are required.', 'emcp-tools' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$updated_count = 0;
		$failed        = array();

		foreach ( $operations as $op ) {
			$eid      = sanitize_text_field( $op['element_id'] ?? '' );
			$settings = $op['settings'] ?? array();

			if ( empty( $eid ) || empty( $settings ) ) {
				$failed[] = array( 'element_id' => $eid, 'reason' => 'missing element_id or settings' );
				continue;
			}

			$element = $this->data->find_element_by_id( $page_data, $eid );

			if ( null === $element ) {
				$failed[] = array( 'element_id' => $eid, 'reason' => 'element not found' );
				continue;
			}

			$ok = $this->data->update_element_settings( $page_data, $eid, $settings );

			if ( $ok ) {
				$updated_count++;
			} else {
				$failed[] = array( 'element_id' => $eid, 'reason' => 'update failed' );
			}
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => empty( $failed ),
			'updated' => $updated_count,
			'failed'  => $failed,
		);
	}

	// -------------------------------------------------------------------------
	// reorder-elements
	// -------------------------------------------------------------------------

	private function register_reorder_elements(): void {
		emcp_tools_register_ability(
			'elementor-mcp/reorder-elements',
			array(
				'label'               => __( 'Reorder Elements', 'emcp-tools' ),
				'description'         => __( 'Reorders the children of a container by providing an ordered array of element IDs. All IDs must be direct children of the specified container.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_reorder_elements' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'container_id' => array(
							'type'        => 'string',
							'description' => __( 'The parent container element ID.', 'emcp-tools' ),
						),
						'element_ids'  => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Ordered array of child element IDs in the desired order.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'container_id', 'element_ids' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
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

	public function execute_reorder_elements( $input ) {
		$post_id      = absint( $input['post_id'] ?? 0 );
		$container_id = sanitize_text_field( $input['container_id'] ?? '' );
		$element_ids  = $input['element_ids'] ?? array();

		if ( ! $post_id || empty( $container_id ) || empty( $element_ids ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, container_id, and element_ids are required.', 'emcp-tools' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$container = $this->data->find_element_by_id( $page_data, $container_id );

		if ( null === $container ) {
			return new \WP_Error( 'element_not_found', __( 'Container not found.', 'emcp-tools' ) );
		}

		if ( 'container' !== ( $container['elType'] ?? '' ) ) {
			return new \WP_Error( 'not_container', __( 'Element is not a container.', 'emcp-tools' ) );
		}

		$children = $container['elements'] ?? array();

		// Build lookup of children by ID.
		$children_by_id = array();
		foreach ( $children as $child ) {
			$children_by_id[ $child['id'] ] = $child;
		}

		// Validate all IDs are actual children.
		foreach ( $element_ids as $eid ) {
			if ( ! isset( $children_by_id[ $eid ] ) ) {
				return new \WP_Error(
					'invalid_element_id',
					sprintf( __( 'Element "%s" is not a direct child of the container.', 'emcp-tools' ), $eid )
				);
			}
		}

		// Build reordered children array.
		$reordered = array();
		foreach ( $element_ids as $eid ) {
			$reordered[] = $children_by_id[ $eid ];
			unset( $children_by_id[ $eid ] );
		}

		// Append any children not in the provided list (preserve them at end).
		foreach ( $children_by_id as $remaining ) {
			$reordered[] = $remaining;
		}

		// Apply reorder.
		$applied = $this->reorder_children( $page_data, $container_id, $reordered );

		if ( ! $applied ) {
			return new \WP_Error( 'reorder_failed', __( 'Failed to reorder elements.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'success' => true );
	}

	/**
	 * Recursively finds a container and replaces its children array.
	 *
	 * @param array  &$data         The page data tree (by reference).
	 * @param string $container_id  The container element ID.
	 * @param array  $new_children  The reordered children array.
	 * @return bool
	 */
	private function reorder_children( array &$data, string $container_id, array $new_children ): bool {
		foreach ( $data as &$item ) {
			if ( isset( $item['id'] ) && $item['id'] === $container_id ) {
				$item['elements'] = $new_children;
				return true;
			}

			if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
				if ( $this->reorder_children( $item['elements'], $container_id, $new_children ) ) {
					return true;
				}
			}
		}

		return false;
	}

	// -------------------------------------------------------------------------
	// move-element
	// -------------------------------------------------------------------------

	private function register_move_element(): void {
		emcp_tools_register_ability(
			'elementor-mcp/move-element',
			array(
				'label'               => __( 'Move Element', 'emcp-tools' ),
				'description'         => __( 'Moves an element to a new parent container and/or position within the page tree.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_move_element' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'          => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'element_id'       => array(
							'type'        => 'string',
							'description' => __( 'The element ID to move.', 'emcp-tools' ),
						),
						'target_parent_id' => array(
							'type'        => 'string',
							'description' => __( 'Target parent container ID. Empty string for top-level.', 'emcp-tools' ),
						),
						'position'         => array(
							'type'        => 'integer',
							'description' => __( 'Position within target parent. -1 = append.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'element_id', 'target_parent_id', 'position' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
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
	 * Executes the move-element ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_move_element( $input ) {
		$post_id          = absint( $input['post_id'] ?? 0 );
		$element_id       = sanitize_text_field( $input['element_id'] ?? '' );
		$target_parent_id = sanitize_text_field( $input['target_parent_id'] ?? '' );
		$position         = intval( $input['position'] ?? -1 );

		if ( ! $post_id || empty( $element_id ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id and element_id are required.', 'emcp-tools' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Find the element first.
		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'emcp-tools' ) );
		}

		// Remove from current position.
		$removed = $this->data->remove_element( $page_data, $element_id );

		if ( ! $removed ) {
			return new \WP_Error( 'remove_failed', __( 'Failed to remove element from current position.', 'emcp-tools' ) );
		}

		// Insert at new position.
		$inserted = $this->data->insert_element( $page_data, $target_parent_id, $element, $position );

		if ( ! $inserted ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to insert element at target position.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'success' => true );
	}

	// -------------------------------------------------------------------------
	// remove-element
	// -------------------------------------------------------------------------

	private function register_remove_element(): void {
		emcp_tools_register_ability(
			'elementor-mcp/remove-element',
			array(
				'label'               => __( 'Remove Element', 'emcp-tools' ),
				'description'         => __( 'Removes an element and all its children from a page.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_remove_element' ),
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
							'description' => __( 'The element ID to remove.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'element_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the remove-element ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_remove_element( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $input['element_id'] ?? '' );

		if ( ! $post_id || empty( $element_id ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id and element_id are required.', 'emcp-tools' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$removed = $this->data->remove_element( $page_data, $element_id );

		if ( ! $removed ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'success' => true );
	}

	// -------------------------------------------------------------------------
	// duplicate-element
	// -------------------------------------------------------------------------

	private function register_duplicate_element(): void {
		emcp_tools_register_ability(
			'elementor-mcp/duplicate-element',
			array(
				'label'               => __( 'Duplicate Element', 'emcp-tools' ),
				'description'         => __( 'Duplicates an element (including all children) with fresh IDs. The duplicate is placed immediately after the original.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_duplicate_element' ),
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
							'description' => __( 'The element ID to duplicate.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'element_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'new_element_id' => array( 'type' => 'string' ),
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
	 * Executes the duplicate-element ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_duplicate_element( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $input['element_id'] ?? '' );

		if ( ! $post_id || empty( $element_id ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id and element_id are required.', 'emcp-tools' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'emcp-tools' ) );
		}

		// Deep-clone and reassign all IDs.
		$clone = $this->data->reassign_element_ids( $element );

		// Find parent and insert after original.
		$inserted = $this->insert_after( $page_data, $element_id, $clone );

		if ( ! $inserted ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to insert duplicate.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'new_element_id' => $clone['id'] );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Inserts an element immediately after a target element in the tree.
	 *
	 * @param array  &$data     The page data tree (by reference).
	 * @param string $target_id The element ID to insert after.
	 * @param array  $element   The element to insert.
	 * @return bool True if inserted successfully.
	 */
	private function insert_after( array &$data, string $target_id, array $element ): bool {
		foreach ( $data as $index => &$item ) {
			if ( isset( $item['id'] ) && $item['id'] === $target_id ) {
				array_splice( $data, $index + 1, 0, array( $element ) );
				return true;
			}

			if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
				if ( $this->insert_after( $item['elements'], $target_id, $element ) ) {
					return true;
				}
			}
		}

		return false;
	}
}

<?php
/**
 * Layout/container MCP abilities for Elementor.
 *
 * Registers 4 tools for adding containers, moving, removing,
 * and duplicating elements within Elementor page trees.
 *
 * @package Elementor_MCP
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
class Elementor_MCP_Layout_Abilities {

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
		return array(
			'elementor-mcp/add-container',
			'elementor-mcp/update-container',
			'elementor-mcp/update-element',
			'elementor-mcp/batch-update',
			'elementor-mcp/reorder-elements',
			'elementor-mcp/move-element',
			'elementor-mcp/remove-element',
			'elementor-mcp/duplicate-element',
			'elementor-mcp/set-responsive-settings',
			'elementor-mcp/set-design-tokens',
			'elementor-mcp/set-page-canvas',
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
		$this->register_set_responsive_settings();
		$this->register_set_design_tokens();
		$this->register_set_page_canvas();
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
		wp_register_ability(
			'elementor-mcp/add-container',
			array(
				'label'               => __( 'Add Container', 'elementor-mcp' ),
				'description'         => __( 'Adds a container to a page. Settings are auto-normalised: companion keys are added automatically (background_background, border_border, typography_typography, box_shadow_box_shadow_type), bare numbers are converted to {size,unit} objects, and justify_content is aliased to flex_justify_content. Flex: flex_direction=row|column, flex_justify_content=center|flex-start|flex-end|space-between|space-around, align_items=center|flex-start|flex-end, column_gap={size,unit}, row_gap={size,unit}. Grid: container_type=grid, grid_columns_grid, grid_rows_grid. Background classic: background_color=#hex OR background_image={url,id} + background_size=cover|contain|auto + background_position="center center". Background gradient: background_background=gradient + background_color=#start + background_color_b=#end + background_gradient_type=linear|radial + background_gradient_angle={size,unit}. Background overlay: background_overlay_background=classic + background_overlay_color=#hex + background_overlay_opacity={size,unit}. Hover background: background_hover_background=classic + background_hover_color=#hex. Border: border_border=solid|dashed|dotted + border_color=#hex + border_width={top,right,bottom,left,unit}. Hover border: border_hover_border=solid + border_hover_color=#hex. Border radius: border_radius={top,right,bottom,left,unit}. Box shadow: box_shadow_box_shadow_type=yes + box_shadow_box_shadow={horizontal,vertical,blur,spread,color}. Spacing: padding={top,right,bottom,left,unit}, margin={top,right,bottom,left,unit}. Other: min_height={size,unit}, html_tag=section|div|header|footer, overflow=hidden, z_index=integer, content_width=full|boxed, opacity=0-100. Responsive: append _tablet or _mobile to most settings (e.g. padding_tablet, flex_direction_mobile, content_width_tablet).', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_add_container' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'parent_id' => array(
							'type'        => 'string',
							'description' => __( 'Parent container ID for nesting. Omit for top-level.', 'elementor-mcp' ),
						),
						'position'  => array(
							'type'        => 'integer',
							'description' => __( 'Insert position. -1 = append (default).', 'elementor-mcp' ),
						),
						'settings'  => array(
							'type'        => 'object',
							'description' => __( 'Container settings: flex_direction, flex_wrap, justify_content, align_items, gap, content_width, padding, margin, background, border, etc.', 'elementor-mcp' ),
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
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'elementor-mcp' ) );
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
					__( 'Parent element "%s" not found.', 'elementor-mcp' ),
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
		wp_register_ability(
			'elementor-mcp/update-container',
			array(
				'label'               => __( 'Update Container', 'elementor-mcp' ),
				'description'         => __( 'Updates settings on an existing container. Settings are merged (partial update) and auto-normalised: companion keys added, bare numbers converted to {size,unit}, justify_content aliased to flex_justify_content. Supports all container controls: flex_direction, flex_justify_content, align_items, flex_wrap, content_width, min_height, overflow, html_tag, container_type, grid controls, background_background + background_color/background_image, border_border + border_color + border_width, border_radius, box_shadow_box_shadow_type + box_shadow_box_shadow, padding, margin, position, z_index, animation, shape dividers, etc.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_update_container' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'The container element ID.', 'elementor-mcp' ),
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => __( 'Partial settings to merge into the container.', 'elementor-mcp' ),
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
		$settings   = Elementor_MCP_Element_Factory::normalize_settings( $input['settings'] ?? array() );

		if ( ! $post_id || empty( $element_id ) || empty( $settings ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, element_id, and settings are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
		}

		if ( 'container' !== ( $element['elType'] ?? '' ) ) {
			return new \WP_Error( 'not_container', __( 'Element is not a container. Use update-widget for widgets.', 'elementor-mcp' ) );
		}

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update container settings.', 'elementor-mcp' ) );
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
		wp_register_ability(
			'elementor-mcp/update-element',
			array(
				'label'               => __( 'Update Element', 'elementor-mcp' ),
				'description'         => __( 'Updates settings on any element (container or widget). Settings are merged (partial update) and auto-normalised: companion keys added automatically (background_background=classic when background_color set, border_border=solid when border_color set, typography_typography=custom when font settings set, box_shadow_box_shadow_type=yes when shadow set), bare numbers converted to {size,unit} objects, justify_content aliased to flex_justify_content. Works for all element types.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_update_element' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'The element ID (container or widget).', 'elementor-mcp' ),
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => __( 'Partial settings to merge into the element.', 'elementor-mcp' ),
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
		$settings   = Elementor_MCP_Element_Factory::normalize_settings( $input['settings'] ?? array() );

		if ( ! $post_id || empty( $element_id ) || empty( $settings ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, element_id, and settings are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
		}

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update element settings.', 'elementor-mcp' ) );
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
		wp_register_ability(
			'elementor-mcp/batch-update',
			array(
				'label'               => __( 'Batch Update Elements', 'elementor-mcp' ),
				'description'         => __( 'Updates multiple elements in a single save operation. Each operation specifies an element_id and settings to merge. Much more efficient than calling update-element multiple times.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_batch_update' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'operations' => array(
							'type'        => 'array',
							'description' => __( 'Array of update operations.', 'elementor-mcp' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'element_id' => array( 'type' => 'string', 'description' => __( 'Element ID to update.', 'elementor-mcp' ) ),
									'settings'   => array( 'type' => 'object', 'description' => __( 'Settings to merge.', 'elementor-mcp' ) ),
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
			return new \WP_Error( 'missing_params', __( 'post_id and operations are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$updated_count = 0;
		$failed        = array();

		foreach ( $operations as $op ) {
			$eid      = sanitize_text_field( $op['element_id'] ?? '' );
			$settings = Elementor_MCP_Element_Factory::normalize_settings( $op['settings'] ?? array() );

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
		wp_register_ability(
			'elementor-mcp/reorder-elements',
			array(
				'label'               => __( 'Reorder Elements', 'elementor-mcp' ),
				'description'         => __( 'Reorders the children of a container by providing an ordered array of element IDs. All IDs must be direct children of the specified container.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_reorder_elements' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'      => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'container_id' => array(
							'type'        => 'string',
							'description' => __( 'The parent container element ID.', 'elementor-mcp' ),
						),
						'element_ids'  => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Ordered array of child element IDs in the desired order.', 'elementor-mcp' ),
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
			return new \WP_Error( 'missing_params', __( 'post_id, container_id, and element_ids are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$container = $this->data->find_element_by_id( $page_data, $container_id );

		if ( null === $container ) {
			return new \WP_Error( 'element_not_found', __( 'Container not found.', 'elementor-mcp' ) );
		}

		if ( 'container' !== ( $container['elType'] ?? '' ) ) {
			return new \WP_Error( 'not_container', __( 'Element is not a container.', 'elementor-mcp' ) );
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
					sprintf( __( 'Element "%s" is not a direct child of the container.', 'elementor-mcp' ), $eid )
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
			return new \WP_Error( 'reorder_failed', __( 'Failed to reorder elements.', 'elementor-mcp' ) );
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
		wp_register_ability(
			'elementor-mcp/move-element',
			array(
				'label'               => __( 'Move Element', 'elementor-mcp' ),
				'description'         => __( 'Moves an element to a new parent container and/or position within the page tree.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_move_element' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'          => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'element_id'       => array(
							'type'        => 'string',
							'description' => __( 'The element ID to move.', 'elementor-mcp' ),
						),
						'target_parent_id' => array(
							'type'        => 'string',
							'description' => __( 'Target parent container ID. Empty string for top-level.', 'elementor-mcp' ),
						),
						'position'         => array(
							'type'        => 'integer',
							'description' => __( 'Position within target parent. -1 = append.', 'elementor-mcp' ),
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
			return new \WP_Error( 'missing_params', __( 'post_id and element_id are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Find the element first.
		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
		}

		// Remove from current position.
		$removed = $this->data->remove_element( $page_data, $element_id );

		if ( ! $removed ) {
			return new \WP_Error( 'remove_failed', __( 'Failed to remove element from current position.', 'elementor-mcp' ) );
		}

		// Insert at new position.
		$inserted = $this->data->insert_element( $page_data, $target_parent_id, $element, $position );

		if ( ! $inserted ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to insert element at target position.', 'elementor-mcp' ) );
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
		wp_register_ability(
			'elementor-mcp/remove-element',
			array(
				'label'               => __( 'Remove Element', 'elementor-mcp' ),
				'description'         => __( 'Removes an element and all its children from a page.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_remove_element' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'The element ID to remove.', 'elementor-mcp' ),
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
			return new \WP_Error( 'missing_params', __( 'post_id and element_id are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$removed = $this->data->remove_element( $page_data, $element_id );

		if ( ! $removed ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
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
		wp_register_ability(
			'elementor-mcp/duplicate-element',
			array(
				'label'               => __( 'Duplicate Element', 'elementor-mcp' ),
				'description'         => __( 'Duplicates an element (including all children) with fresh IDs. The duplicate is placed immediately after the original.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_duplicate_element' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'The element ID to duplicate.', 'elementor-mcp' ),
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
			return new \WP_Error( 'missing_params', __( 'post_id and element_id are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
		}

		// Deep-clone and reassign all IDs.
		$clone = $this->data->reassign_element_ids( $element );

		// Find parent and insert after original.
		$inserted = $this->insert_after( $page_data, $element_id, $clone );

		if ( ! $inserted ) {
			return new \WP_Error( 'insert_failed', __( 'Failed to insert duplicate.', 'elementor-mcp' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'new_element_id' => $clone['id'] );
	}

	// -------------------------------------------------------------------------
	// set-responsive-settings
	// -------------------------------------------------------------------------

	/**
	 * Registers the set-responsive-settings ability.
	 *
	 * @since 2.2.0
	 */
	private function register_set_responsive_settings(): void {
		wp_register_ability(
			'elementor-mcp/set-responsive-settings',
			array(
				'label'               => __( 'Set Responsive Settings', 'elementor-mcp' ),
				'description'         => __( 'Sets responsive settings for an element across all breakpoints in a single call. Instead of manually appending _tablet and _mobile suffixes, pass settings grouped by breakpoint. The tool auto-generates the correct suffixed keys. Example: {"desktop": {"padding": {"top":"40","right":"40","bottom":"40","left":"40","unit":"px"}, "flex_direction": "row"}, "tablet": {"padding": {"top":"20","right":"20","bottom":"20","left":"20","unit":"px"}, "flex_direction": "column"}, "mobile": {"padding": {"top":"10","right":"10","bottom":"10","left":"10","unit":"px"}}}. Desktop keys are stored as-is, tablet keys get _tablet suffix, mobile keys get _mobile suffix. Settings are normalized and merged. Combine with any widget or container setting key: typography, backgrounds, margins, sizes, flex layout, etc.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_set_responsive_settings' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'The element ID (container or widget).', 'elementor-mcp' ),
						),
						'desktop'    => array(
							'type'        => 'object',
							'description' => __( 'Settings for desktop (stored without suffix).', 'elementor-mcp' ),
						),
						'tablet'     => array(
							'type'        => 'object',
							'description' => __( 'Settings for tablet (auto-suffixed with _tablet).', 'elementor-mcp' ),
						),
						'mobile'     => array(
							'type'        => 'object',
							'description' => __( 'Settings for mobile (auto-suffixed with _mobile).', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'element_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'keys_set'    => array( 'type' => 'integer' ),
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
	 * Executes the set-responsive-settings ability.
	 *
	 * Takes desktop/tablet/mobile grouped settings and flattens them into
	 * Elementor's suffixed key format, then applies them in a single save.
	 *
	 * @since 2.2.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_set_responsive_settings( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $input['element_id'] ?? '' );

		if ( ! $post_id || empty( $element_id ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id and element_id are required.', 'elementor-mcp' ) );
		}

		$desktop_settings = $input['desktop'] ?? array();
		$tablet_settings  = $input['tablet'] ?? array();
		$mobile_settings  = $input['mobile'] ?? array();

		if ( empty( $desktop_settings ) && empty( $tablet_settings ) && empty( $mobile_settings ) ) {
			return new \WP_Error( 'no_settings', __( 'At least one of desktop, tablet, or mobile settings is required.', 'elementor-mcp' ) );
		}

		// Flatten breakpoint-grouped settings into suffixed keys.
		$merged = array();

		// Desktop: keys stored as-is (no suffix).
		foreach ( $desktop_settings as $key => $value ) {
			$merged[ $key ] = $value;
		}

		// Tablet: keys get _tablet suffix.
		foreach ( $tablet_settings as $key => $value ) {
			$merged[ $key . '_tablet' ] = $value;
		}

		// Mobile: keys get _mobile suffix.
		foreach ( $mobile_settings as $key => $value ) {
			$merged[ $key . '_mobile' ] = $value;
		}

		// Normalize the merged settings (adds companion keys, converts numbers, etc).
		$merged = Elementor_MCP_Element_Factory::normalize_settings( $merged );

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
		}

		$updated = $this->data->update_element_settings( $page_data, $element_id, $merged );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update element settings.', 'elementor-mcp' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'  => true,
			'keys_set' => count( $merged ),
		);
	}

	// -------------------------------------------------------------------------
	// set-design-tokens
	// -------------------------------------------------------------------------

	/**
	 * Registers the set-design-tokens ability.
	 *
	 * @since 2.3.0
	 */
	private function register_set_design_tokens(): void {
		wp_register_ability(
			'elementor-mcp/set-design-tokens',
			array(
				'label'               => __( 'Set Design Tokens', 'elementor-mcp' ),
				'description'         => __(
					'Injects CSS custom properties (design tokens) into a page as :root variables. '
					. 'Use this to define a consistent design system: colors, fonts, spacing, shadows, radii, etc. '
					. 'All elements on the page can reference these tokens via var(--token-name). '
					. 'Pass tokens as a flat object: { "color-primary": "#6C63FF", "font-heading": "Inter", "spacing-lg": "2rem" }. '
					. 'Token names will be prefixed with "--" automatically (do NOT include the "--" prefix). '
					. 'You can also pass a "media_queries" object for responsive tokens: '
					. '{ "(max-width: 768px)": { "spacing-lg": "1rem" } }. '
					. 'Set replace=true to overwrite previously injected design tokens on the same page.',
					'elementor-mcp'
				),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_set_design_tokens' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'parent_id'     => array(
							'type'        => 'string',
							'description' => __( 'Parent container element ID to insert the tokens widget into.', 'elementor-mcp' ),
						),
						'tokens'        => array(
							'type'        => 'object',
							'description' => __(
								'Flat object of token name → value. Example: { "color-primary": "#6C63FF", "font-body": "Inter, sans-serif", "radius-md": "8px", "shadow-card": "0 4px 20px rgba(0,0,0,0.08)" }. '
								. 'Do NOT include the "--" prefix — it is added automatically.',
								'elementor-mcp'
							),
						),
						'media_queries' => array(
							'type'        => 'object',
							'description' => __(
								'Optional responsive overrides. Keys are media queries, values are token objects. '
								. 'Example: { "(max-width: 768px)": { "spacing-lg": "1rem", "font-size-hero": "2rem" } }.',
								'elementor-mcp'
							),
						),
						'replace'       => array(
							'type'        => 'boolean',
							'description' => __( 'If true, replaces any previously injected design tokens on this page. Default: false (appends).', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'parent_id', 'tokens' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id'  => array( 'type' => 'string' ),
						'post_id'     => array( 'type' => 'integer' ),
						'token_count' => array( 'type' => 'integer' ),
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
	 * Executes the set-design-tokens ability.
	 *
	 * Converts a flat token name→value map into a :root CSS block and
	 * injects it into the page via an invisible HTML widget.
	 *
	 * @since 2.3.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_set_design_tokens( $input ) {
		$post_id       = absint( $input['post_id'] ?? 0 );
		$parent_id     = sanitize_text_field( $input['parent_id'] ?? '' );
		$tokens        = $input['tokens'] ?? array();
		$media_queries = $input['media_queries'] ?? array();
		$replace       = ! empty( $input['replace'] );

		if ( ! $post_id || empty( $parent_id ) || empty( $tokens ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, parent_id, and tokens are required.', 'elementor-mcp' ) );
		}

		// Build :root block from tokens.
		$css_lines = array();
		foreach ( $tokens as $name => $value ) {
			$safe_name = preg_replace( '/[^a-zA-Z0-9_-]/', '', $name );
			$css_lines[] = "\t--" . $safe_name . ': ' . $value . ';';
		}

		$css = ":root {\n" . implode( "\n", $css_lines ) . "\n}";

		// Add responsive media query overrides.
		if ( ! empty( $media_queries ) && is_array( $media_queries ) ) {
			foreach ( $media_queries as $query => $mq_tokens ) {
				if ( ! is_array( $mq_tokens ) || empty( $mq_tokens ) ) {
					continue;
				}
				$mq_lines = array();
				foreach ( $mq_tokens as $name => $value ) {
					$safe_name  = preg_replace( '/[^a-zA-Z0-9_-]/', '', $name );
					$mq_lines[] = "\t\t--" . $safe_name . ': ' . $value . ';';
				}
				$css .= "\n@media " . $query . " {\n\t:root {\n" . implode( "\n", $mq_lines ) . "\n\t}\n}";
			}
		}

		$html_content = "<style>\n/* Design Tokens — Elementor MCP */\n" . $css . "\n</style>";

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// If replace, find and remove any existing design-token widgets.
		if ( $replace ) {
			$this->remove_design_token_widgets( $page_data );
		}

		$widget = $this->factory->create_widget(
			'html',
			array(
				'html'              => $html_content,
				'_css_classes'      => 'emcp-design-tokens',
				'_element_width'    => '',
			)
		);

		$inserted = $this->data->insert_element( $page_data, $parent_id, $widget, 0 );

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

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'element_id'  => $widget['id'],
			'post_id'     => $post_id,
			'token_count' => count( $tokens ),
		);
	}

	/**
	 * Recursively removes widgets with the design-tokens CSS class.
	 *
	 * @param array &$data The page data tree.
	 */
	private function remove_design_token_widgets( array &$data ): void {
		foreach ( $data as $index => &$item ) {
			// Check if this is a design-token widget.
			if (
				isset( $item['widgetType'] ) &&
				'html' === $item['widgetType'] &&
				isset( $item['settings']['_css_classes'] ) &&
				false !== strpos( $item['settings']['_css_classes'], 'emcp-design-tokens' )
			) {
				array_splice( $data, $index, 1 );
				// Re-call since indices shifted.
				$this->remove_design_token_widgets( $data );
				return;
			}

			if ( ! empty( $item['elements'] ) && is_array( $item['elements'] ) ) {
				$this->remove_design_token_widgets( $item['elements'] );
			}
		}
	}

	// -------------------------------------------------------------------------
	// set-page-canvas
	// -------------------------------------------------------------------------

	/**
	 * Registers the set-page-canvas ability.
	 *
	 * @since 2.3.0
	 */
	private function register_set_page_canvas(): void {
		wp_register_ability(
			'elementor-mcp/set-page-canvas',
			array(
				'label'               => __( 'Set Page Canvas', 'elementor-mcp' ),
				'description'         => __(
					'Sets page-level canvas settings: layout template, content width, page background, body classes, '
					. 'and title display. This controls the overall page frame before any containers/widgets are added. '
					. 'Common templates: "default" (theme layout), "elementor_header_footer" (Elementor + theme header/footer), '
					. '"elementor_canvas" (blank canvas, no header/footer). '
					. 'All parameters are optional — only pass the ones you want to change.',
					'elementor-mcp'
				),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_set_page_canvas' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'              => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						// Layout.
						'template'             => array(
							'type'        => 'string',
							'enum'        => array( 'default', 'elementor_header_footer', 'elementor_canvas' ),
							'description' => __( 'Page template. "elementor_canvas" = blank, "elementor_header_footer" = with theme header/footer, "default" = full theme layout.', 'elementor-mcp' ),
						),
						'content_width'        => array(
							'type'        => 'object',
							'description' => __( 'Override content width: { "size": 1140, "unit": "px" }. Leave empty to use theme default.', 'elementor-mcp' ),
						),
						'content_layout'       => array(
							'type'        => 'string',
							'enum'        => array( '', 'boxed', 'full_width' ),
							'description' => __( 'Content area: "boxed" (constrained), "full_width" (edge to edge), "" (default).', 'elementor-mcp' ),
						),
						// Page background.
						'background_background'          => array(
							'type'        => 'string',
							'enum'        => array( 'classic', 'gradient', 'none' ),
							'description' => __( 'Page background type.', 'elementor-mcp' ),
						),
						'background_color'               => array(
							'type'        => 'string',
							'description' => __( 'Page background color (#hex).', 'elementor-mcp' ),
						),
						'background_image'               => array(
							'type'        => 'object',
							'description' => __( 'Background image: { "url": "https://...", "id": "" }.', 'elementor-mcp' ),
						),
						'background_position'            => array(
							'type'        => 'string',
							'description' => __( 'Background position: center center, top left, etc.', 'elementor-mcp' ),
						),
						'background_size'                => array(
							'type'        => 'string',
							'enum'        => array( 'cover', 'contain', 'auto' ),
							'description' => __( 'Background size.', 'elementor-mcp' ),
						),
						'background_repeat'              => array(
							'type'        => 'string',
							'enum'        => array( 'no-repeat', 'repeat', 'repeat-x', 'repeat-y' ),
							'description' => __( 'Background repeat.', 'elementor-mcp' ),
						),
						// Gradient.
						'background_color_b'             => array(
							'type'        => 'string',
							'description' => __( 'Gradient second color (#hex).', 'elementor-mcp' ),
						),
						'background_gradient_type'       => array(
							'type'        => 'string',
							'enum'        => array( 'linear', 'radial' ),
							'description' => __( 'Gradient type.', 'elementor-mcp' ),
						),
						'background_gradient_angle'      => array(
							'type'        => 'object',
							'description' => __( 'Gradient angle: { "size": 180, "unit": "deg" }.', 'elementor-mcp' ),
						),
						// Padding.
						'padding'                        => array(
							'type'        => 'object',
							'description' => __( 'Page body padding: { "top": 0, "right": 0, "bottom": 0, "left": 0, "unit": "px" }.', 'elementor-mcp' ),
						),
						// Title display.
						'hide_title'                     => array(
							'type'        => 'string',
							'enum'        => array( 'yes', '' ),
							'description' => __( '"yes" to hide the page title, "" to show it.', 'elementor-mcp' ),
						),
						// Page status.
						'post_status'                    => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'private', 'pending' ),
							'description' => __( 'Page publish status.', 'elementor-mcp' ),
						),
						// Custom body class.
						'body_class'                     => array(
							'type'        => 'string',
							'description' => __( 'Additional CSS class(es) to add to the <body> tag.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'post_id'      => array( 'type' => 'integer' ),
						'settings_set' => array( 'type' => 'integer' ),
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
	 * Executes the set-page-canvas ability.
	 *
	 * Merges the provided settings into the page's Elementor settings.
	 * Also handles the template setting which requires a separate WordPress
	 * post meta update.
	 *
	 * @since 2.3.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_set_page_canvas( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'elementor-mcp' ) );
		}

		// Extract template separately — it's stored as WP post meta, not Elementor settings.
		$template = null;
		if ( isset( $input['template'] ) ) {
			$template = sanitize_text_field( $input['template'] );
			unset( $input['template'] );
		}

		// Collect Elementor page settings (everything except post_id and template).
		$settings = array();
		$ignored  = array( 'post_id' );

		foreach ( $input as $key => $value ) {
			if ( in_array( $key, $ignored, true ) ) {
				continue;
			}
			$settings[ $key ] = $value;
		}

		// Apply page template via WordPress meta.
		if ( null !== $template ) {
			update_post_meta( $post_id, '_wp_page_template', $template );
		}

		// Apply Elementor page settings.
		if ( ! empty( $settings ) ) {
			$result = $this->data->save_page_settings( $post_id, $settings );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		$total_set = count( $settings ) + ( null !== $template ? 1 : 0 );

		return array(
			'success'      => true,
			'post_id'      => $post_id,
			'settings_set' => $total_set,
		);
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

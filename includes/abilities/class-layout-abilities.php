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
		wp_register_ability(
			'elementor-mcp/add-container',
			array(
				'label'               => __( 'Add Container', 'elementor-mcp' ),
				'description'         => __( 'Adds a flexbox container to a page. Omit parent_id for top-level, or provide a parent container ID for nesting. Layout tips: Use flex_direction=row for side-by-side children. For multi-column layouts, set each child to content_width=full with a percentage width (e.g. {size:50,unit:"%"} for 2 columns). NEVER set flex_wrap or _flex_size — these cause layout overflow. Background colors: set background_background=classic and background_color=#hex.', 'elementor-mcp' ),
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

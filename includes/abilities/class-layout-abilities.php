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
	 * Whether an elType is a container the layout tools operate on. Accepts the
	 * legacy `container` plus the Elementor 4.0+ atomic containers `e-flexbox`
	 * and `e-div-block` (see issue #104 / same class as #72).
	 *
	 * @param string $el_type The element's elType.
	 * @return bool
	 */
	private static function is_container_type( string $el_type ): bool {
		return in_array( $el_type, array( 'container', 'e-flexbox', 'e-div-block' ), true );
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
			'emcp-tools/add-container',
			'emcp-tools/update-container',
			'emcp-tools/update-element',
			'emcp-tools/batch-update',
			'emcp-tools/set-element-label',
			'emcp-tools/reorder-elements',
			'emcp-tools/move-element',
			'emcp-tools/remove-element',
			'emcp-tools/duplicate-element',
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
		$this->register_set_element_label();
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
			'emcp-tools/add-container',
			array(
				'label'               => __( 'Add Container', 'emcp-tools' ),
				'description'         => __( 'Adds a container to a page. Supports both flex (default) and grid layouts via settings.container_type. Omit parent_id for top-level, or provide a parent container ID for nesting. Flex tips: Use flex_direction=row for side-by-side children, flex_wrap=wrap for wrapping, flex_justify_content for main-axis alignment, flex_align_items for cross-axis alignment. The shorthand justify_content / align_items are also accepted. Grid tips: Set container_type=grid with grid_columns_grid, grid_rows_grid, grid_gaps inside settings. Elementor defaults to 2 rows; for a single row explicitly set grid_rows_grid: {"unit":"fr","size":1}. Background: set background_background=classic and background_color=#hex. Border: set border_border=solid, border_width, border_color. Supply all four sides for margin/padding/border dimensions: one blank side may suppress the entire CSS rule. Partial dimensions are saved unchanged with warnings. Also supports min_height, overflow, html_tag, position, z_index, animation.', 'emcp-tools' ),
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
						'full_bleed' => array(
							'type'        => 'boolean',
							'description' => __( 'When true, seed an edge-to-edge full-bleed container (full content width, 100% width, zero padding, zero flex/gap, column + stretch). Use for the top-level container on Canvas-template pages so headers/footers and full-width sections have no white strips. Any explicit `settings` you pass still override the preset.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id' => array( 'type' => 'string' ),
						'post_id'    => array( 'type' => 'integer' ),
						'warnings'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
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

		// full_bleed preset: a top-level edge-to-edge container. On Canvas-template
		// pages (the natural template for AI-built full pages) the boxed defaults
		// leave white strips around headers/footers and edge-to-edge sections
		// (#83). The preset seeds the known-good recipe; any explicit `settings`
		// the caller passes still win, so it's a starting point, not a lock.
		if ( ! empty( $input['full_bleed'] ) ) {
			$settings = array_merge( self::full_bleed_preset(), (array) $settings );
		}

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
		}

		// A `container` element only renders when Elementor's Flexbox Container
		// experiment is active. On long-lived installs where it is OFF, Elementor
		// silently skips the element on render, leaving an empty page with no error
		// (#111). Refuse up front with an actionable message instead of writing an
		// unrenderable document. Atomic sites (e-flexbox) are unaffected — those go
		// through add-flexbox, which registers only when atomic is supported.
		if ( ! EMCP_Tools_Atomic_Props::is_container_supported() ) {
			return new \WP_Error(
				'container_unsupported',
				__( 'This site has Elementor\'s Flexbox Container experiment disabled, so a container element would be stored but render empty. Enable Elementor → Settings → Features → "Flexbox Container" (or Atomic Elements and use the add-flexbox / atomic tools) before building pages via MCP. Editing existing pages is unaffected.', 'emcp-tools' )
			);
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Partial classic dimensions are left out of a new element (#151).
		$guard_context = array( 'elType' => 'container' );
		if ( ! empty( $input['full_bleed'] ) ) {
			// A partial side the caller sent over the preset is filled from it.
			$guard_context['preset']       = self::full_bleed_preset();
			$guard_context['preset_label'] = 'full_bleed preset';
		}
		$guard    = EMCP_Tools_Element_Factory::guard_settings( (array) $settings, null, $guard_context );
		$settings = $guard['settings'];

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
			'warnings'   => $guard['warnings'],
		);
	}

	/**
	 * The full-bleed container recipe: full content width, 100% width, zero
	 * padding, zero flex/gap, column direction, stretch alignment. This is the
	 * known-good top-level container for Canvas-template pages, where Elementor's
	 * boxed defaults otherwise leave white strips (#83).
	 *
	 * @since 3.2.1
	 * @return array<string,mixed>
	 */
	public static function full_bleed_preset(): array {
		return array(
			'content_width'    => 'full',
			'flex_direction'   => 'column',
			'flex_align_items' => 'stretch',
			'width'            => array( 'unit' => '%', 'size' => 100 ),
			'padding'          => array( 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => true ),
			'gap'              => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0' ),
			'flex_gap'         => array( 'unit' => 'px', 'size' => 0, 'column' => '0', 'row' => '0' ),
		);
	}

	// -------------------------------------------------------------------------
	// update-container
	// -------------------------------------------------------------------------

	private function register_update_container(): void {
		emcp_tools_register_ability(
			'emcp-tools/update-container',
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
						'warnings' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
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

		if ( ! self::is_container_type( $element['elType'] ?? '' ) ) {
			return new \WP_Error( 'not_container', __( 'Element is not a container. Use update-widget for widgets.', 'emcp-tools' ) );
		}

		// Partial classic dimensions: fill from the stored value or skip (#151).
		$guard    = EMCP_Tools_Element_Factory::guard_settings( (array) $settings, (array) ( $element['settings'] ?? array() ), $element );
		$settings = $guard['settings'];

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update container settings.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'success' => true, 'warnings' => $guard['warnings'] );
	}

	// -------------------------------------------------------------------------
	// update-element (universal — works for both containers and widgets)
	// -------------------------------------------------------------------------

	private function register_update_element(): void {
		emcp_tools_register_ability(
			'emcp-tools/update-element',
			array(
				'label'               => __( 'Update Element', 'emcp-tools' ),
				'description'         => __( 'Updates settings on any element (container or widget). Settings are merged (partial update). Works for all element types, no need to know if the target is a container or widget. For v4 atomic elements you may also include a `styles` map (the element\'s local CSS classes) and/or `editor_settings` (e.g. `{ "title": "Hero" }` for the Navigator label) in the settings object, these are routed to the element root automatically. Use set-element-label for just the Navigator name.', 'emcp-tools' ),
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
						'warnings' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
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

		// Partial classic dimensions: fill from the stored value or skip (#151).
		$guard    = EMCP_Tools_Element_Factory::guard_settings( (array) $settings, (array) ( $element['settings'] ?? array() ), $element );
		$settings = $guard['settings'];

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update element settings.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Widget keys that are not controls are saved but do nothing (#152).
		// `styles` and `editor_settings` are routed to the element root, not settings.
		$key_warnings = array();
		if ( 'widget' === ( $element['elType'] ?? '' ) ) {
			$key_warnings = EMCP_Tools_Widget_Setting_Keys::warnings(
				(string) ( $element['widgetType'] ?? '' ),
				array_diff_key( (array) $settings, array_flip( array( 'styles', 'editor_settings' ) ) )
			);
		}

		return array(
			'success'      => true,
			'element_id'   => $element_id,
			'element_type' => $element['elType'] ?? 'unknown',
			'warnings'     => array_merge( $guard['warnings'], $key_warnings ),
		);
	}

	// -------------------------------------------------------------------------
	// batch-update
	// -------------------------------------------------------------------------

	private function register_batch_update(): void {
		emcp_tools_register_ability(
			'emcp-tools/batch-update',
			array(
				'label'               => __( 'Batch Update Elements', 'emcp-tools' ),
				'description'         => __( 'Updates multiple elements in a single save operation. Each operation specifies an element_id and settings to merge. Much more efficient than calling update-element multiple times. As with update-element, a per-operation settings object may include a `styles` map and/or `editor_settings` for v4 atomic elements, these are routed to the element root automatically.', 'emcp-tools' ),
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
						'warnings' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
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
		$warnings      = array();

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

			// Partial classic dimensions: fill from the stored value or skip (#151).
			$guard    = EMCP_Tools_Element_Factory::guard_settings( (array) $settings, (array) ( $element['settings'] ?? array() ), $element );
			$settings = $guard['settings'];

			$ok = $this->data->update_element_settings( $page_data, $eid, $settings );

			if ( $ok ) {
				$updated_count++;
				foreach ( $guard['warnings'] as $warning ) {
					$warnings[] = $eid . ': ' . $warning;
				}
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
			'warnings' => $warnings,
		);
	}

	// -------------------------------------------------------------------------
	// set-element-label (type-aware Navigator label)
	// -------------------------------------------------------------------------

	private function register_set_element_label(): void {
		emcp_tools_register_ability(
			'emcp-tools/set-element-label',
			array(
				'label'               => __( 'Set Element Label', 'emcp-tools' ),
				'description'         => __( 'Sets and verifies an element\'s Navigator label. Classic elements use settings._title; atomic elements use root editor_settings.title. The same type-aware mapping is applied by update-element and batch-update when passed editor_settings.title.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_set_element_label' ),
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
							'description' => __( 'The element ID to label.', 'emcp-tools' ),
						),
						'title'      => array(
							'type'        => 'string',
							'description' => __( 'The Navigator label to set.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'element_id', 'title' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'element_id' => array( 'type' => 'string' ),
						'title'      => array( 'type' => 'string' ),
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

	public function execute_set_element_label( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $input['element_id'] ?? '' );
		$title      = sanitize_text_field( $input['title'] ?? '' );

		if ( ! $post_id || empty( $element_id ) || '' === $title ) {
			return new \WP_Error( 'missing_params', __( 'post_id, element_id, and title are required.', 'emcp-tools' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		if ( null === $this->data->find_element_by_id( $page_data, $element_id ) ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'emcp-tools' ) );
		}

		// The data layer maps the label to the element type's native storage.
		$updated = $this->data->update_element_settings(
			$page_data,
			$element_id,
			array( 'editor_settings' => array( 'title' => $title ) )
		);

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to set element label.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$saved = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$element = $this->data->find_element_by_id( $saved, $element_id );
		$stored_title = $element && EMCP_Tools_Data::is_atomic_element( $element )
			? ( $element['editor_settings']['title'] ?? null )
			: ( $element['settings']['_title'] ?? null );
		if ( $stored_title !== $title ) {
			return new \WP_Error( 'label_not_persisted', __( 'Elementor did not persist the requested Navigator label. Read back the page before retrying.', 'emcp-tools' ) );
		}

		return array(
			'success'    => true,
			'element_id' => $element_id,
			'title'      => $title,
		);
	}

	// -------------------------------------------------------------------------
	// reorder-elements
	// -------------------------------------------------------------------------

	private function register_reorder_elements(): void {
		emcp_tools_register_ability(
			'emcp-tools/reorder-elements',
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

		if ( ! self::is_container_type( $container['elType'] ?? '' ) ) {
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
			'emcp-tools/move-element',
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
			'emcp-tools/remove-element',
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
			'emcp-tools/duplicate-element',
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

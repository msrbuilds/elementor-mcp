<?php
/**
 * Page CRUD MCP abilities for Elementor.
 *
 * Registers 5 tools for creating, updating, clearing, importing,
 * and exporting Elementor pages.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the page CRUD abilities.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Page_Abilities {

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
			'elementor-mcp/create-page',
			'elementor-mcp/update-page-settings',
			'elementor-mcp/delete-page-content',
			'elementor-mcp/import-template',
			'elementor-mcp/export-page',
			'elementor-mcp/validate-page',
		);
	}

	/**
	 * Registers all page abilities.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		$this->register_create_page();
		$this->register_update_page_settings();
		$this->register_delete_page_content();
		$this->register_import_template();
		$this->register_export_page();
		$this->register_validate_page();
	}

	/**
	 * Permission check for page creation.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	public function check_create_permission(): bool {
		return current_user_can( 'publish_pages' ) || current_user_can( 'edit_pages' );
	}

	/**
	 * Permission check for page editing.
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

	/**
	 * Permission check for destructive page content deletion.
	 *
	 * Requires both edit and delete capabilities since this operation
	 * is destructive and removes all Elementor content from the page.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $input The input data.
	 * @return bool
	 */
	public function check_delete_permission( $input = null ): bool {
		if ( ! current_user_can( 'edit_posts' ) || ! current_user_can( 'delete_posts' ) ) {
			return false;
		}

		$post_id = absint( $input['post_id'] ?? 0 );
		if ( $post_id ) {
			if ( ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'delete_post', $post_id ) ) {
				return false;
			}
		}

		return true;
	}

	// -------------------------------------------------------------------------
	// create-page
	// -------------------------------------------------------------------------

	private function register_create_page(): void {
		wp_register_ability(
			'elementor-mcp/create-page',
			array(
				'label'               => __( 'Create Elementor Page', 'elementor-mcp' ),
				'description'         => __( 'Creates a new WordPress page with Elementor enabled. Optionally provide initial element content.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_create_page' ),
				'permission_callback' => array( $this, 'check_create_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array(
							'type'        => 'string',
							'description' => __( 'Page title.', 'elementor-mcp' ),
						),
						'status'    => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish' ),
							'description' => __( 'Post status. Default: draft.', 'elementor-mcp' ),
						),
						'post_type' => array(
							'type'        => 'string',
							'enum'        => array( 'page', 'post' ),
							'description' => __( 'Post type. Default: page.', 'elementor-mcp' ),
						),
						'template'  => array(
							'type'        => 'string',
							'description' => __( 'Elementor template slug.', 'elementor-mcp' ),
						),
						'content'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'object' ),
							'description' => __( 'Initial element tree.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'title' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array( 'type' => 'integer' ),
						'title'       => array( 'type' => 'string' ),
						'edit_url'    => array( 'type' => 'string' ),
						'preview_url' => array( 'type' => 'string' ),
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
	 * Executes the create-page ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_create_page( $input ) {
		$title     = sanitize_text_field( $input['title'] ?? '' );
		$status    = sanitize_key( $input['status'] ?? 'draft' );
		$post_type = sanitize_key( $input['post_type'] ?? 'page' );

		if ( empty( $title ) ) {
			return new \WP_Error( 'missing_title', __( 'The title parameter is required.', 'elementor-mcp' ) );
		}

		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => $status,
				'post_type'   => $post_type,
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => 'wp-' . $post_type,
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set page template if provided.
		if ( ! empty( $input['template'] ) ) {
			update_post_meta( $post_id, '_wp_page_template', sanitize_text_field( $input['template'] ) );
		}

		// Save initial content if provided.
		if ( ! empty( $input['content'] ) && is_array( $input['content'] ) ) {
			$save_result = $this->data->save_page_data( $post_id, $input['content'] );
		} else {
			// Save empty Elementor data to initialize.
			$save_result = $this->data->save_page_data( $post_id, array() );
		}

		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}

		$edit_url    = admin_url( 'post.php?post=' . $post_id . '&action=elementor' );
		$preview_url = get_permalink( $post_id );

		return array(
			'post_id'     => $post_id,
			'title'       => $title,
			'edit_url'    => $edit_url,
			'preview_url' => $preview_url ? $preview_url : '',
		);
	}

	// -------------------------------------------------------------------------
	// update-page-settings
	// -------------------------------------------------------------------------

	private function register_update_page_settings(): void {
		wp_register_ability(
			'elementor-mcp/update-page-settings',
			array(
				'label'               => __( 'Update Page Settings', 'elementor-mcp' ),
				'description'         => __( 'Updates page-level Elementor settings such as background, padding, custom CSS, and layout options.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_update_page_settings' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'settings' => array(
							'type'        => 'object',
							'description' => __( 'Page settings object.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'settings' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'post_id' => array( 'type' => 'integer' ),
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

	public function execute_update_page_settings( $input ) {
		$post_id  = absint( $input['post_id'] ?? 0 );
		$settings = $input['settings'] ?? array();

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'elementor-mcp' ) );
		}

		$result = $this->data->save_page_settings( $post_id, $settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'post_id' => $post_id,
		);
	}

	// -------------------------------------------------------------------------
	// delete-page-content
	// -------------------------------------------------------------------------

	private function register_delete_page_content(): void {
		wp_register_ability(
			'elementor-mcp/delete-page-content',
			array(
				'label'               => __( 'Delete Page Content', 'elementor-mcp' ),
				'description'         => __( 'Clears all Elementor content from a page, resetting it to blank while keeping the page itself.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_delete_page_content' ),
				'permission_callback' => array( $this, 'check_delete_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
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

	public function execute_delete_page_content( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'elementor-mcp' ) );
		}

		$result = $this->data->save_page_data( $post_id, array() );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array( 'success' => true );
	}

	// -------------------------------------------------------------------------
	// import-template
	// -------------------------------------------------------------------------

	private function register_import_template(): void {
		wp_register_ability(
			'elementor-mcp/import-template',
			array(
				'label'               => __( 'Import Template', 'elementor-mcp' ),
				'description'         => __( 'Imports a JSON template structure into a page at an optional position.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_import_template' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'template_json' => array(
							'type'        => 'array',
							'description' => __( 'Elementor JSON element structure to import.', 'elementor-mcp' ),
						),
						'position'      => array(
							'type'        => 'integer',
							'description' => __( 'Insert position. -1 = append.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'template_json' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'elements_count' => array( 'type' => 'integer' ),
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

	public function execute_import_template( $input ) {
		$post_id       = absint( $input['post_id'] ?? 0 );
		$template_json = $input['template_json'] ?? array();
		$position      = intval( $input['position'] ?? -1 );

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'elementor-mcp' ) );
		}

		if ( empty( $template_json ) ) {
			return new \WP_Error( 'missing_template', __( 'The template_json parameter is required.', 'elementor-mcp' ) );
		}

		$data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		// Assign new IDs to all imported elements.
		$template_json = $this->data->reassign_ids( $template_json );
		$count         = $this->data->count_elements( $template_json );

		// Insert at position.
		if ( $position < 0 || $position >= count( $data ) ) {
			$data = array_merge( $data, $template_json );
		} else {
			array_splice( $data, $position, 0, $template_json );
		}

		$result = $this->data->save_page_data( $post_id, $data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'        => true,
			'elements_count' => $count,
		);
	}

	// -------------------------------------------------------------------------
	// export-page
	// -------------------------------------------------------------------------

	private function register_export_page(): void {
		wp_register_ability(
			'elementor-mcp/export-page',
			array(
				'label'               => __( 'Export Page', 'elementor-mcp' ),
				'description'         => __( 'Exports a page\'s full Elementor data as a JSON structure that can be imported elsewhere.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_export_page' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'json' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	public function execute_export_page( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'elementor-mcp' ) );
		}

		$data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array( 'json' => $data );
	}

	// -------------------------------------------------------------------------
	// validate-page
	// -------------------------------------------------------------------------

	/**
	 * Registers the validate-page ability.
	 *
	 * @since 2.4.0
	 */
	private function register_validate_page(): void {
		wp_register_ability(
			'elementor-mcp/validate-page',
			array(
				'label'               => __( 'Validate Page', 'elementor-mcp' ),
				'description'         => __(
					'Scans an Elementor page for common build errors and returns a list of warnings. '
					. 'Checks for: missing background companion keys, typography without custom enabled, '
					. 'empty containers, widgets with no content, borders without type, and placeholder image URLs. '
					. 'Run this after building a page to catch issues before publishing.',
					'elementor-mcp'
				),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_validate_page' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID to validate.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'valid'         => array( 'type' => 'boolean' ),
						'warning_count' => array( 'type' => 'integer' ),
						'warnings'      => array( 'type' => 'array' ),
						'element_count' => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the validate-page ability.
	 *
	 * Recursively scans the page element tree and reports common issues.
	 *
	 * @since 2.4.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_validate_page( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$warnings      = array();
		$element_count = 0;

		$this->validate_elements( $page_data, $warnings, $element_count );

		return array(
			'valid'         => empty( $warnings ),
			'warning_count' => count( $warnings ),
			'warnings'      => $warnings,
			'element_count' => $element_count,
		);
	}

	/**
	 * Recursively validates elements in the page tree.
	 *
	 * @param array $elements      The element tree.
	 * @param array &$warnings     Warnings collector.
	 * @param int   &$element_count Element counter.
	 * @param int   $depth          Current nesting depth.
	 */
	private function validate_elements( array $elements, array &$warnings, int &$element_count, int $depth = 0 ): void {
		foreach ( $elements as $element ) {
			$element_count++;

			$id       = $element['id'] ?? 'unknown';
			$type     = $element['elType'] ?? $element['type'] ?? 'unknown';
			$settings = $element['settings'] ?? array();
			$children = $element['elements'] ?? array();

			$widget_type = $element['widgetType'] ?? '';
			$label       = $widget_type ? $widget_type : $type;

			// 1. Empty container check.
			if ( 'container' === $type && empty( $children ) ) {
				$warnings[] = array(
					'element_id' => $id,
					'type'       => $label,
					'severity'   => 'warning',
					'message'    => 'Empty container — has no child elements.',
					'fix'        => 'Add child widgets/containers or remove this empty container.',
				);
			}

			// 2. Background color without background_background.
			if (
				! empty( $settings['background_color'] ) &&
				empty( $settings['background_background'] )
			) {
				$warnings[] = array(
					'element_id' => $id,
					'type'       => $label,
					'severity'   => 'error',
					'message'    => 'background_color is set but background_background is missing — color will NOT render.',
					'fix'        => 'Add background_background="classic" to this element.',
				);
			}

			// 3. Typography font_size without typography_typography=custom.
			if (
				( ! empty( $settings['typography_font_size'] ) || ! empty( $settings['typography_font_weight'] ) || ! empty( $settings['typography_font_family'] ) ) &&
				( empty( $settings['typography_typography'] ) || 'custom' !== $settings['typography_typography'] )
			) {
				$warnings[] = array(
					'element_id' => $id,
					'type'       => $label,
					'severity'   => 'error',
					'message'    => 'Typography settings set without typography_typography="custom" — font styles will be IGNORED.',
					'fix'        => 'Add typography_typography="custom" to enable the typography group.',
				);
			}

			// 4. Border color without border type.
			if (
				! empty( $settings['border_color'] ) &&
				empty( $settings['border_border'] )
			) {
				$warnings[] = array(
					'element_id' => $id,
					'type'       => $label,
					'severity'   => 'error',
					'message'    => 'border_color is set but border_border is missing — no border will render.',
					'fix'        => 'Add border_border="solid" (or dotted/dashed/double).',
				);
			}

			// 5. Box shadow without type enabled.
			if (
				! empty( $settings['box_shadow_box_shadow'] ) &&
				empty( $settings['box_shadow_box_shadow_type'] )
			) {
				$warnings[] = array(
					'element_id' => $id,
					'type'       => $label,
					'severity'   => 'error',
					'message'    => 'box_shadow_box_shadow is set but box_shadow_box_shadow_type is missing — shadow will NOT render.',
					'fix'        => 'Add box_shadow_box_shadow_type="yes".',
				);
			}

			// 6. Empty heading widget.
			if ( 'heading' === $widget_type && empty( $settings['title'] ) ) {
				$warnings[] = array(
					'element_id' => $id,
					'type'       => $label,
					'severity'   => 'warning',
					'message'    => 'Heading widget has no title text.',
					'fix'        => 'Set the title setting.',
				);
			}

			// 7. Empty button widget.
			if ( 'button' === $widget_type && empty( $settings['text'] ) ) {
				$warnings[] = array(
					'element_id' => $id,
					'type'       => $label,
					'severity'   => 'warning',
					'message'    => 'Button widget has no text.',
					'fix'        => 'Set the text setting.',
				);
			}

			// 8. Image widget with placeholder or empty URL.
			if ( 'image' === $widget_type ) {
				$img_url = $settings['image']['url'] ?? '';
				if ( empty( $img_url ) ) {
					$warnings[] = array(
						'element_id' => $id,
						'type'       => $label,
						'severity'   => 'warning',
						'message'    => 'Image widget has no image URL.',
						'fix'        => 'Set image={"url": "...", "id": 123}.',
					);
				} elseif (
					false !== strpos( $img_url, 'placeholder' ) ||
					false !== strpos( $img_url, 'via.placeholder' ) ||
					false !== strpos( $img_url, 'dummyimage' ) ||
					false !== strpos( $img_url, 'picsum.photos' )
				) {
					$warnings[] = array(
						'element_id' => $id,
						'type'       => $label,
						'severity'   => 'warning',
						'message'    => 'Image uses a placeholder URL: ' . $img_url,
						'fix'        => 'Use search-images + sideload-image to get a real image.',
					);
				}
			}

			// 9. Text editor with empty content.
			if ( 'text-editor' === $widget_type ) {
				$editor_content = trim( wp_strip_all_tags( $settings['editor'] ?? '' ) );
				if ( empty( $editor_content ) ) {
					$warnings[] = array(
						'element_id' => $id,
						'type'       => $label,
						'severity'   => 'warning',
						'message'    => 'Text editor widget has empty content.',
						'fix'        => 'Set editor="<p>Your content</p>".',
					);
				}
			}

			// 10. Bare number values for dimension settings (should be {size, unit}).
			$dimension_keys = array( 'padding', 'margin', 'border_radius', 'border_width', 'min_height', 'typography_font_size' );
			foreach ( $dimension_keys as $key ) {
				if ( isset( $settings[ $key ] ) && is_numeric( $settings[ $key ] ) ) {
					$warnings[] = array(
						'element_id' => $id,
						'type'       => $label,
						'severity'   => 'error',
						'message'    => $key . ' is a bare number (' . $settings[ $key ] . ') — must be an object.',
						'fix'        => 'Use {"size": ' . $settings[ $key ] . ', "unit": "px"} format.',
					);
				}
			}

			// Recurse into children.
			if ( ! empty( $children ) && is_array( $children ) ) {
				$this->validate_elements( $children, $warnings, $element_count, $depth + 1 );
			}
		}
	}

}

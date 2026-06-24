<?php
/**
 * Page CRUD MCP abilities for Elementor.
 *
 * Registers 5 tools for creating, updating, clearing, importing,
 * and exporting Elementor pages.
 *
 * @package EMCP_Tools
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
class EMCP_Tools_Page_Abilities {

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
			'emcp-tools/create-page',
			'emcp-tools/update-page-settings',
			'emcp-tools/delete-page-content',
			'emcp-tools/import-template',
			'emcp-tools/export-page',
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
		emcp_tools_register_ability(
			'emcp-tools/create-page',
			array(
				'label'               => __( 'Create Elementor Page', 'emcp-tools' ),
				'description'         => __( 'Creates a new WordPress page with Elementor enabled. Optionally provide initial element content.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_create_page' ),
				'permission_callback' => array( $this, 'check_create_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'     => array(
							'type'        => 'string',
							'description' => __( 'Page title.', 'emcp-tools' ),
						),
						'status'    => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish' ),
							'description' => __( 'Post status. Default: draft.', 'emcp-tools' ),
						),
						'post_type' => array(
							'type'        => 'string',
							'enum'        => array( 'page', 'post' ),
							'description' => __( 'Post type. Default: page.', 'emcp-tools' ),
						),
						'template'  => array(
							'type'        => 'string',
							'description' => __( 'Elementor template slug.', 'emcp-tools' ),
						),
						'content'   => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'object' ),
							'description' => __( 'Initial element tree.', 'emcp-tools' ),
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
			return new \WP_Error( 'missing_title', __( 'The title parameter is required.', 'emcp-tools' ) );
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
		emcp_tools_register_ability(
			'emcp-tools/update-page-settings',
			array(
				'label'               => __( 'Update Page Settings', 'emcp-tools' ),
				'description'         => __( 'Updates page-level Elementor settings such as background, padding, custom CSS, and layout options.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_page_settings' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'  => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'settings' => array(
							'type'        => 'object',
							'description' => __( 'Page settings object.', 'emcp-tools' ),
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
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
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
		emcp_tools_register_ability(
			'emcp-tools/delete-page-content',
			array(
				'label'               => __( 'Delete Page Content', 'emcp-tools' ),
				'description'         => __( 'Clears all Elementor content from a page, resetting it to blank while keeping the page itself.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_page_content' ),
				'permission_callback' => array( $this, 'check_delete_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
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
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
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
		emcp_tools_register_ability(
			'emcp-tools/import-template',
			array(
				'label'               => __( 'Import Template', 'emcp-tools' ),
				'description'         => __( 'Imports a JSON template structure into a page at an optional position.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_import_template' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'template_json' => array(
							'type'        => 'array',
							'description' => __( 'Elementor JSON element structure to import.', 'emcp-tools' ),
							'items'       => array(
								'type' => 'object',
							),
						),
						'position'      => array(
							'type'        => 'integer',
							'description' => __( 'Insert position. -1 = append.', 'emcp-tools' ),
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
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
		}

		if ( empty( $template_json ) ) {
			return new \WP_Error( 'missing_template', __( 'The template_json parameter is required.', 'emcp-tools' ) );
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
		emcp_tools_register_ability(
			'emcp-tools/export-page',
			array(
				'label'               => __( 'Export Page', 'emcp-tools' ),
				'description'         => __( 'Exports a page\'s full Elementor data as a JSON structure that can be imported elsewhere.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_export_page' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
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
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
		}

		$data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return array( 'json' => $data );
	}

}

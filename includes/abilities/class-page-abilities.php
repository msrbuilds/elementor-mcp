<?php
/**
 * Page CRUD MCP abilities for Elementor.
 *
 * Registers tools for creating, updating, clearing, importing,
 * exporting Elementor pages and regenerating their caches.
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
			'emcp-tools/regenerate-css',
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
		$this->register_regenerate_css();
	}

	private function register_regenerate_css(): void {
		emcp_tools_register_ability( 'emcp-tools/regenerate-css', array(
			'label' => __( 'Regenerate CSS & Data', 'emcp-tools' ),
			'description' => __( 'Invalidates generated Elementor CSS and render/asset caches, which rebuild on the next frontend request. Default scope=page requires post_id and permission to edit that post. Site-wide scope=site requires manage_options and confirm:true. Does not change page content or clear third-party/CDN caches.', 'emcp-tools' ),
			'category' => 'emcp-tools',
			'execute_callback' => array( $this, 'execute_regenerate_css' ),
			'permission_callback' => array( $this, 'check_regenerate_permission' ),
			'input_schema' => array(
				'type' => 'object',
				'properties' => array(
					'scope' => array( 'type' => 'string', 'enum' => array( 'page', 'site' ), 'default' => 'page' ),
					'post_id' => array( 'type' => 'integer', 'minimum' => 1 ),
					'confirm' => array( 'type' => 'boolean', 'default' => false ),
				),
			),
			'output_schema' => array( 'type' => 'object', 'properties' => array(
				'success' => array( 'type' => 'boolean' ),
				'scope' => array( 'type' => 'string' ),
				'post_id' => array( 'type' => 'integer' ),
				'regeneration' => array( 'type' => 'string' ),
			) ),
			'meta' => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
		) );
	}

	public function check_regenerate_permission( $input = null ): bool {
		$scope = $input['scope'] ?? 'page';
		if ( 'site' === $scope ) {
			return current_user_can( 'manage_options' );
		}
		return 'page' === $scope && ! empty( $input['post_id'] ) && $this->check_edit_permission( $input );
	}

	public function execute_regenerate_css( $input ) {
		$scope = $input['scope'] ?? 'page';
		$post_id = $input['post_id'] ?? 0;
		if ( ! in_array( $scope, array( 'page', 'site' ), true ) || ( 'page' === $scope && ( ! is_int( $post_id ) || $post_id < 1 ) ) || ( 'site' === $scope && isset( $input['post_id'] ) ) ) {
			return new \WP_Error( 'invalid_scope', __( 'Use scope=page with a positive post_id, or scope=site without post_id.', 'emcp-tools' ) );
		}
		if ( ! $this->check_regenerate_permission( $input ) ) {
			return new \WP_Error( 'forbidden', __( 'You cannot regenerate Elementor caches for this scope.', 'emcp-tools' ) );
		}
		if ( 'site' === $scope && true !== ( $input['confirm'] ?? false ) ) {
			return new \WP_Error( 'confirmation_required', __( 'Site-wide regeneration requires confirm:true.', 'emcp-tools' ) );
		}
		if ( 'page' === $scope && ! get_post( $post_id ) ) {
			return new \WP_Error( 'post_not_found', __( 'The requested post does not exist.', 'emcp-tools' ) );
		}
		try {
			$result = $this->clear_elementor_cache( 'page' === $scope ? $post_id : 0 );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'cache_regeneration_failed', __( 'Elementor could not clear its generated caches. Check server logs and filesystem permissions.', 'emcp-tools' ) );
		}
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return array( 'success' => true, 'scope' => $scope, 'post_id' => 'page' === $scope ? $post_id : 0, 'regeneration' => 'on_next_frontend_request' );
	}

	/** Official Elementor cache APIs; isolated for tests without filesystem writes. */
	protected function clear_elementor_cache( int $post_id ) {
		if ( ! class_exists( '\Elementor\Plugin' ) || ! isset( \Elementor\Plugin::$instance->files_manager ) ) {
			return new \WP_Error( 'elementor_unavailable', __( 'Elementor cache management is unavailable.', 'emcp-tools' ) );
		}
		if ( 0 === $post_id ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		} else {
			\Elementor\Core\Files\CSS\Post::create( $post_id )->delete();
			delete_post_meta( $post_id, \Elementor\Core\Base\Document::CACHE_META_KEY );
			delete_post_meta( $post_id, \Elementor\Core\Base\Elements_Iteration_Actions\Assets::ASSETS_META_KEY );
			// Atomic styles have their own versioned cache. Scope the official
			// clear hook to this post (both frontend and preview), never global.
			do_action( 'elementor/atomic-widgets/styles/clear', array( 'local', $post_id ) );
		}
		return true;
	}

	/**
	 * Permission check for page creation.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $input The input data.
	 * @return bool
	 */
	public function check_create_permission( $input = null ): bool {
		$post_type = sanitize_key( $input['post_type'] ?? 'page' );
		$status    = sanitize_key( $input['status'] ?? 'draft' );
		if ( ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
			return false;
		}
		return true === EMCP_Tools_Post_Authorization::authorize_create( $post_type, $status ?: 'draft' );
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
						'change_id'   => array( 'type' => 'string' ),
						'warnings'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
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
		if ( ! in_array( $post_type, array( 'page', 'post' ), true ) ) {
			return new \WP_Error( 'invalid_post_type', __( 'post_type must be page or post.', 'emcp-tools' ) );
		}
		if ( ! in_array( $status, array( 'draft', 'publish' ), true ) ) {
			return new \WP_Error( 'invalid_status', __( 'status must be draft or publish.', 'emcp-tools' ) );
		}
		$authorization = EMCP_Tools_Post_Authorization::authorize_create( $post_type, $status );
		if ( is_wp_error( $authorization ) ) {
			return $authorization;
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

		// Initialization belongs to the creation event; it is not a separate
		// page edit whose undo would merely empty the newly created page.
		$has_history = class_exists( 'EMCP_Tools_Change_Recorder' );
		$was_suppressed = $has_history && EMCP_Tools_Change_Log::$suppress;
		if ( $has_history ) {
			EMCP_Tools_Change_Log::$suppress = true;
		}
		try {
			$content = ! empty( $input['content'] ) && is_array( $input['content'] ) ? $input['content'] : array();
			// Partial classic dimensions in caller content are left out (#151).
			if ( ! empty( $content ) ) {
				$content_guard = EMCP_Tools_Element_Factory::guard_tree( $content );
				$content       = $content_guard['elements'];
			}
			$save_result = $this->data->save_page_data( $post_id, $content );
		} catch ( \Throwable $error ) {
			$save_result = new \WP_Error( 'initialization_failed', $error->getMessage() );
		} finally {
			if ( $has_history ) {
				EMCP_Tools_Change_Log::$suppress = $was_suppressed;
			}
		}
		$change_id = $has_history ? EMCP_Tools_Change_Recorder::record_resource_create( $post_id, 'elementor', 'create-page' ) : '';
		if ( $has_history && ! $was_suppressed && '' === $change_id ) {
			return new \WP_Error( 'history_record_failed', __( 'The page was created, but History could not save its creation. Inspect the page before retrying.', 'emcp-tools' ), array( 'post_id' => $post_id ) );
		}

		if ( is_wp_error( $save_result ) ) {
			return $save_result;
		}
		if ( true !== $save_result ) {
			return new \WP_Error( 'initialization_failed', __( 'The page was created, but Elementor initialization failed.', 'emcp-tools' ), array( 'post_id' => $post_id, 'change_id' => $change_id ) );
		}

		$edit_url    = admin_url( 'post.php?post=' . $post_id . '&action=elementor' );
		$preview_url = get_permalink( $post_id );

		return array(
			'post_id'     => $post_id,
			'title'       => $title,
			'edit_url'    => $edit_url,
			'preview_url' => $preview_url ? $preview_url : '',
			'change_id'   => $change_id,
			'warnings'    => $content_guard['warnings'] ?? array(),
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
						'success'  => array( 'type' => 'boolean' ),
						'post_id'  => array( 'type' => 'integer' ),
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

	public function execute_update_page_settings( $input ) {
		$post_id  = absint( $input['post_id'] ?? 0 );
		$settings = $input['settings'] ?? array();

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
		}

		// Partial classic dimensions: fill from the stored page settings or skip (#151).
		$stored   = get_post_meta( $post_id, '_elementor_page_settings', true );
		$guard    = EMCP_Tools_Element_Factory::guard_settings( (array) $settings, is_array( $stored ) ? $stored : array() );
		$settings = $guard['settings'];

		$result = $this->data->save_page_settings( $post_id, $settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'  => true,
			'post_id'  => $post_id,
			'warnings' => $guard['warnings'],
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
						'warnings'       => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
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

		// Partial classic dimensions in the imported tree are left out (#151).
		$tree_guard    = EMCP_Tools_Element_Factory::guard_tree( (array) $template_json );
		$template_json = $tree_guard['elements'];

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
			'warnings'       => $tree_guard['warnings'],
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

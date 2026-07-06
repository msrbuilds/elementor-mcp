<?php
/**
 * EMCP Themer MCP abilities — 8 tools to build + wire theme templates.
 *
 * Reads (list/get/list-condition-targets/resolve) need edit_posts; writes
 * (create/update/set-conditions/delete) need edit_post on the template. Granular
 * condition selectors are validated against the registered selector set, so
 * without the Pro overlay they are simply not accepted (no Pro logic in free).
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.2.0
 */
class EMCP_Tools_Themer_Abilities {

	/** @var string[] */
	private $ability_names = array();

	const POST_TYPE = 'emcp_theme_template';

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/** Register all eight abilities. */
	public function register(): void {
		$this->register_create();
		$this->register_list();
		$this->register_get();
		$this->register_update();
		$this->register_set_conditions();
		$this->register_delete();
		$this->register_resolve();
		$this->register_list_condition_targets();
	}

	// ---- permissions -------------------------------------------------------

	/** @param array|null $input @return bool */
	public function check_read_permission( $input = null ): bool {
		return current_user_can( 'edit_posts' );
	}

	/** @param array|null $input @return bool */
	public function check_write_permission( $input = null ): bool {
		$id = absint( $input['template_id'] ?? 0 );
		if ( $id ) {
			return current_user_can( 'edit_post', $id );
		}
		return current_user_can( 'publish_pages' ) || current_user_can( 'edit_pages' );
	}

	// ---- helpers -----------------------------------------------------------

	/**
	 * Read a template's conditions meta as an array.
	 *
	 * @param int $id Template id.
	 * @return array
	 */
	private function conditions_of( int $id ): array {
		$raw = get_post_meta( $id, '_emcp_themer_conditions', true );
		if ( is_array( $raw ) ) {
			return $raw;
		}
		$decoded = is_string( $raw ) && '' !== $raw ? json_decode( $raw, true ) : array();
		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * A compact summary row for one template.
	 *
	 * @param int $id Template id.
	 * @return array
	 */
	private function summary( int $id ): array {
		$post = get_post( $id );
		return array(
			'template_id' => $id,
			'title'       => $post ? (string) $post->post_title : '',
			'type'        => (string) get_post_meta( $id, '_emcp_themer_type', true ),
			'status'      => $post ? (string) $post->post_status : '',
			'conditions'  => $this->conditions_of( $id ),
			'edit_url'    => get_edit_post_link( $id, 'raw' ),
		);
	}

	/**
	 * The set of selector keys currently valid (free + any Pro-registered).
	 *
	 * @return string[]
	 */
	private function valid_selectors(): array {
		/**
		 * Filters the selector option list surfaced to clients + used for validation.
		 *
		 * @param string[] $selectors Selector keys.
		 */
		return (array) apply_filters(
			'emcp_themer_selectors',
			array( 'entire-site', 'all-singular', 'all-archives', 'front-page', 'post-type', 'post-type-archive', 'tax-archive' )
		);
	}

	// ---- list --------------------------------------------------------------

	private function register_list(): void {
		$this->ability_names[] = 'emcp-tools/list-theme-templates';
		emcp_tools_register_ability(
			'emcp-tools/list-theme-templates',
			array(
				'label'               => __( 'List Theme Templates', 'emcp-tools' ),
				'description'         => __( 'Lists EMCP Themer templates (header/footer/single/archive/search/404) with their type + display conditions.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type' => array( 'type' => 'string', 'description' => __( 'Only this template type.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object', 'properties' => array( 'templates' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ) ) ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/** @param array $input @return array */
	public function execute_list( $input ): array {
		$type = isset( $input['type'] ) ? (string) $input['type'] : '';
		$args = array(
			'post_type'      => self::POST_TYPE,
			'post_status'    => array( 'publish', 'draft' ),
			'posts_per_page' => 200,
			'fields'         => 'ids',
			'no_found_rows'  => true,
		);
		if ( '' !== $type ) {
			$args['meta_key']   = '_emcp_themer_type';
			$args['meta_value'] = $type;
		}
		$q    = new WP_Query( $args );
		$rows = array();
		foreach ( $q->posts as $id ) {
			$rows[] = $this->summary( (int) $id );
		}
		return array( 'templates' => $rows );
	}

	// ---- get ---------------------------------------------------------------

	private function register_get(): void {
		$this->ability_names[] = 'emcp-tools/get-theme-template';
		emcp_tools_register_ability(
			'emcp-tools/get-theme-template',
			array(
				'label'               => __( 'Get Theme Template', 'emcp-tools' ),
				'description'         => __( 'Returns one Themer template: type, display conditions, builder, and content.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'template_id' => array( 'type' => 'integer' ) ),
					'required'   => array( 'template_id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/** @param array $input @return array */
	public function execute_get( $input ): array {
		$id   = absint( $input['template_id'] ?? 0 );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return array( 'error' => __( 'Template not found.', 'emcp-tools' ) );
		}
		return array(
			'template_id' => $id,
			'title'       => (string) $post->post_title,
			'type'        => (string) get_post_meta( $id, '_emcp_themer_type', true ),
			'conditions'  => $this->conditions_of( $id ),
			'builder'     => EMCP_Tools_Themer_Content_Renderer::detect_builder( $id ),
			'content'     => (string) $post->post_content,
		);
	}

	// ---- list-condition-targets -------------------------------------------

	private function register_list_condition_targets(): void {
		$this->ability_names[] = 'emcp-tools/list-condition-targets';
		emcp_tools_register_ability(
			'emcp-tools/list-condition-targets',
			array(
				'label'               => __( 'List Condition Targets', 'emcp-tools' ),
				'description'         => __( 'Discovery for set-template-conditions: available selector keys, public post types, and taxonomies to target.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_condition_targets' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array( 'type' => 'object', 'properties' => array() ),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	/** @param array $input @return array */
	public function execute_list_condition_targets( $input ): array {
		$post_types = array();
		foreach ( get_post_types( array( 'public' => true ), 'objects' ) as $pt ) {
			$post_types[] = array( 'slug' => $pt->name, 'label' => $pt->label );
		}
		$taxes = array();
		foreach ( get_taxonomies( array( 'public' => true ), 'objects' ) as $tx ) {
			$taxes[] = array( 'slug' => $tx->name, 'label' => $tx->label, 'object_types' => (array) $tx->object_type );
		}
		return array(
			'selectors'  => array_values( $this->valid_selectors() ),
			'post_types' => $post_types,
			'taxonomies' => $taxes,
		);
	}

	// ---- write registrations (execs implemented in Task 4.2) --------------

	private function register_create(): void {
		$this->ability_names[] = 'emcp-tools/create-theme-template';
		emcp_tools_register_ability(
			'emcp-tools/create-theme-template',
			array(
				'label'               => __( 'Create Theme Template', 'emcp-tools' ),
				'description'         => __( 'Creates a Themer template of a type (header|footer|single|archive|search|404) with optional initial content + scope. Enforces the free 1-per-type quota. Build its content afterward with the Gutenberg/Elementor tools using the returned template_id.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_create' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'type'    => array( 'type' => 'string', 'enum' => EMCP_Tools_Themer_CPT::TYPES ),
						'title'   => array( 'type' => 'string' ),
						'content' => array( 'type' => 'string', 'description' => __( 'Optional initial content (block or classic markup).', 'emcp-tools' ) ),
						'scope'   => array( 'type' => 'string', 'description' => __( 'A broad selector key (e.g. entire-site, all-singular, post-type:page).', 'emcp-tools' ) ),
					),
					'required'   => array( 'type' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	private function register_update(): void {
		$this->ability_names[] = 'emcp-tools/update-theme-template';
		emcp_tools_register_ability(
			'emcp-tools/update-theme-template',
			array(
				'label'               => __( 'Update Theme Template', 'emcp-tools' ),
				'description'         => __( 'Updates a Themer template\'s title and/or content.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'template_id' => array( 'type' => 'integer' ),
						'title'       => array( 'type' => 'string' ),
						'content'     => array( 'type' => 'string' ),
					),
					'required'   => array( 'template_id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	private function register_set_conditions(): void {
		$this->ability_names[] = 'emcp-tools/set-template-conditions';
		emcp_tools_register_ability(
			'emcp-tools/set-template-conditions',
			array(
				'label'               => __( 'Set Template Conditions', 'emcp-tools' ),
				'description'         => __( 'Sets a template\'s include/exclude display rules + priority. Granular selectors (per-ID/per-term/per-author/exclude/priority) require EMCP Pro; free accepts broad scope selectors. Use list-condition-targets for valid selectors.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_set_conditions' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'template_id' => array( 'type' => 'integer' ),
						'include'     => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						'exclude'     => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						'priority'    => array( 'type' => 'integer' ),
					),
					'required'   => array( 'template_id', 'include' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}

	private function register_delete(): void {
		$this->ability_names[] = 'emcp-tools/delete-theme-template';
		emcp_tools_register_ability(
			'emcp-tools/delete-theme-template',
			array(
				'label'               => __( 'Delete Theme Template', 'emcp-tools' ),
				'description'         => __( 'Deletes a Themer template (trashes by default; force:true permanently deletes). Destructive.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'template_id' => array( 'type' => 'integer' ),
						'force'       => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'template_id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => true, 'idempotent' => false ), 'show_in_rest' => true ),
			)
		);
	}

	private function register_resolve(): void {
		$this->ability_names[] = 'emcp-tools/resolve-template';
		emcp_tools_register_ability(
			'emcp-tools/resolve-template',
			array(
				'label'               => __( 'Resolve Template', 'emcp-tools' ),
				'description'         => __( 'Given a post_id (single) or a query descriptor, returns which templates fill the header/body/footer slots — so you can verify your conditions wired correctly.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_resolve' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array( 'type' => 'integer', 'description' => __( 'Resolve as if viewing this singular post.', 'emcp-tools' ) ),
						'context' => array( 'type' => 'string', 'enum' => array( 'front-page', 'search', '404' ), 'description' => __( 'Resolve a non-singular context.', 'emcp-tools' ) ),
					),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);
	}
}

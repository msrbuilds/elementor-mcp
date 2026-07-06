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

	// ---- create ------------------------------------------------------------

	/** @param array $input @return array */
	public function execute_create( $input ): array {
		$type = isset( $input['type'] ) ? (string) $input['type'] : '';
		if ( ! in_array( $type, EMCP_Tools_Themer_CPT::TYPES, true ) ) {
			return array( 'error' => __( 'Invalid template type.', 'emcp-tools' ) );
		}
		$existing = EMCP_Tools_Themer_CPT::count_of_type( $type );
		if ( ! EMCP_Tools_Themer_CPT::can_create( $type, $existing ) ) {
			return array(
				'error' => sprintf(
					/* translators: %s: template type */
					__( 'Free quota reached: only one %s template is allowed. Upgrade to EMCP Pro for unlimited templates per type.', 'emcp-tools' ),
					$type
				),
			);
		}

		$title   = isset( $input['title'] ) && '' !== $input['title'] ? sanitize_text_field( (string) $input['title'] ) : ucfirst( $type ) . ' Template';
		$post_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'publish',
				'post_title'   => $title,
				'post_content' => isset( $input['content'] ) ? (string) $input['content'] : '',
				'post_author'  => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $post_id ) ) {
			return array( 'error' => $post_id->get_error_message() );
		}
		$post_id = (int) $post_id;
		update_post_meta( $post_id, '_emcp_themer_type', $type );

		// Seed a broad scope for header/footer/single/archive; search/404 need no rule.
		$scope = isset( $input['scope'] ) ? (string) $input['scope'] : '';
		if ( '' === $scope && in_array( $type, array( 'header', 'footer' ), true ) ) {
			$scope = 'entire-site';
		} elseif ( '' === $scope && 'single' === $type ) {
			$scope = 'all-singular';
		} elseif ( '' === $scope && 'archive' === $type ) {
			$scope = 'all-archives';
		}
		$conditions = array( 'include' => array(), 'exclude' => array(), 'priority' => 0 );
		if ( '' !== $scope ) {
			$err = $this->validate_selector( $scope );
			if ( ! $err ) {
				$conditions['include'] = array( array( 'object' => $scope ) );
			}
			// Scope invalid on free: keep the template but leave it unconditioned.
		}
		update_post_meta( $post_id, '_emcp_themer_conditions', $conditions );
		EMCP_Tools_Themer_Index::rebuild();

		return array( 'template_id' => $post_id, 'type' => $type, 'edit_url' => get_edit_post_link( $post_id, 'raw' ) );
	}

	/**
	 * Validate a selector key against the registered set. Returns an error string
	 * or '' when valid. (`post-type:page` validates on the `post-type` key.)
	 *
	 * @param string $object Selector object string.
	 * @return string
	 */
	private function validate_selector( string $object ): string {
		$key   = false === strpos( $object, ':' ) ? $object : substr( $object, 0, strpos( $object, ':' ) );
		$valid = $this->valid_selectors();
		if ( ! in_array( $key, $valid, true ) ) {
			return sprintf(
				/* translators: %s: selector key */
				__( 'Selector "%s" is not available. Granular selectors require EMCP Pro; see list-condition-targets for valid keys.', 'emcp-tools' ),
				$key
			);
		}
		return '';
	}

	// ---- update ------------------------------------------------------------

	/** @param array $input @return array */
	public function execute_update( $input ): array {
		$id   = absint( $input['template_id'] ?? 0 );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return array( 'error' => __( 'Template not found.', 'emcp-tools' ) );
		}
		$data = array( 'ID' => $id );
		if ( isset( $input['title'] ) ) {
			$data['post_title'] = sanitize_text_field( (string) $input['title'] );
		}
		if ( isset( $input['content'] ) ) {
			$data['post_content'] = wp_slash( (string) $input['content'] );
		}
		wp_update_post( $data, true );
		return array( 'success' => true, 'template_id' => $id );
	}

	// ---- set-conditions ----------------------------------------------------

	/** @param array $input @return array */
	public function execute_set_conditions( $input ): array {
		$id   = absint( $input['template_id'] ?? 0 );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return array( 'error' => __( 'Template not found.', 'emcp-tools' ) );
		}
		$include = isset( $input['include'] ) && is_array( $input['include'] ) ? $input['include'] : array();
		$exclude = isset( $input['exclude'] ) && is_array( $input['exclude'] ) ? $input['exclude'] : array();

		// Exclude rules are Pro; reject on free (no exclude selectors registered means
		// they'd never evaluate — fail loudly instead of silently no-op'ing).
		if ( $exclude && ! $this->pro_conditions_available() ) {
			return array( 'error' => __( 'Exclude rules require EMCP Pro.', 'emcp-tools' ) );
		}

		foreach ( array_merge( $include, $exclude ) as $rule ) {
			$object = is_array( $rule ) ? (string) ( $rule['object'] ?? '' ) : '';
			$err    = $this->validate_selector( $object );
			if ( $err ) {
				return array( 'error' => $err );
			}
		}

		$priority = isset( $input['priority'] ) ? (int) $input['priority'] : 0;
		if ( 0 !== $priority && ! $this->pro_conditions_available() ) {
			$priority = 0; // priority is a Pro tie-break; ignore silently on free.
		}

		$conditions = array(
			'include'  => array_values( $include ),
			'exclude'  => array_values( $exclude ),
			'priority' => $priority,
		);
		update_post_meta( $id, '_emcp_themer_conditions', $conditions );
		EMCP_Tools_Themer_Index::rebuild();

		return array( 'success' => true, 'template_id' => $id, 'conditions' => $conditions );
	}

	/**
	 * Whether the Pro condition layer is present (a granular selector is registered).
	 *
	 * @return bool
	 */
	private function pro_conditions_available(): bool {
		return in_array( 'post', $this->valid_selectors(), true );
	}

	// ---- delete ------------------------------------------------------------

	/** @param array $input @return array */
	public function execute_delete( $input ): array {
		$id   = absint( $input['template_id'] ?? 0 );
		$post = $id ? get_post( $id ) : null;
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return array( 'error' => __( 'Template not found.', 'emcp-tools' ) );
		}
		$force = ! empty( $input['force'] );
		$res   = wp_delete_post( $id, $force );
		if ( ! $res ) {
			return array( 'error' => __( 'Delete failed.', 'emcp-tools' ) );
		}
		EMCP_Tools_Themer_Index::rebuild();
		return array( 'success' => true, 'template_id' => $id, 'forced' => $force );
	}

	// ---- resolve -----------------------------------------------------------

	/** @param array $input @return array */
	public function execute_resolve( $input ): array {
		$ctx  = $this->resolve_context( $input );
		$reg  = EMCP_Tools_Themer_Matcher_Registry::fresh();
		$rank = apply_filters(
			'emcp_themer_rank',
			static function ( array $row ): int {
				return 0;
			}
		);
		$slots = EMCP_Tools_Themer_Resolver::resolve( EMCP_Tools_Themer_Index::get(), $ctx, $reg, $rank );
		return array( 'slots' => $slots, 'context' => $ctx );
	}

	/**
	 * Build a resolution context from resolve() input.
	 *
	 * @param array $input Ability input.
	 * @return array
	 */
	private function resolve_context( array $input ): array {
		if ( ! empty( $input['post_id'] ) ) {
			$id    = absint( $input['post_id'] );
			$post  = get_post( $id );
			$parts = array( 'is_singular' => true, 'post_id' => $id );
			if ( $post ) {
				$parts['post_type'] = $post->post_type;
				$parts['author_id'] = (int) $post->post_author;
			}
			return EMCP_Tools_Themer_Context::from_parts( $parts );
		}
		$context = isset( $input['context'] ) ? (string) $input['context'] : '';
		if ( 'search' === $context ) {
			return EMCP_Tools_Themer_Context::from_parts( array( 'is_search' => true, 'is_archive' => true ) );
		}
		if ( '404' === $context ) {
			return EMCP_Tools_Themer_Context::from_parts( array( 'is_404' => true ) );
		}
		if ( 'front-page' === $context ) {
			return EMCP_Tools_Themer_Context::from_parts( array( 'is_front_page' => true, 'is_singular' => true ) );
		}
		return EMCP_Tools_Themer_Context::from_parts( array() );
	}
}

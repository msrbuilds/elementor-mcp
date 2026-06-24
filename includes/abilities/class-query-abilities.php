<?php
/**
 * Read-only query/discovery MCP abilities for Elementor.
 *
 * Registers 7 read-only tools that let AI agents discover widgets,
 * inspect page structures, and read Elementor data.
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the 7 read-only query abilities.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Query_Abilities {

	/**
	 * The data access layer.
	 *
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * The schema generator.
	 *
	 * @var EMCP_Tools_Schema_Generator
	 */
	private $schema_generator;

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param EMCP_Tools_Data             $data             The data access layer.
	 * @param EMCP_Tools_Schema_Generator $schema_generator The schema generator.
	 */
	public function __construct( EMCP_Tools_Data $data, EMCP_Tools_Schema_Generator $schema_generator ) {
		$this->data             = $data;
		$this->schema_generator = $schema_generator;
	}

	/**
	 * Returns the ability names registered by this class.
	 *
	 * @since 1.0.0
	 *
	 * @return string[] Array of ability names.
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/list-widgets',
			'emcp-tools/get-widget-schema',
			'emcp-tools/get-container-schema',
			'emcp-tools/get-page-structure',
			'emcp-tools/get-element-settings',
			'emcp-tools/find-element',
			'emcp-tools/list-pages',
			'emcp-tools/list-templates',
			'emcp-tools/get-global-settings',
		);
	}

	/**
	 * Registers all query abilities with the WordPress Abilities API.
	 *
	 * Must be called during the `wp_abilities_api_init` action.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		$this->register_list_widgets();
		$this->register_get_widget_schema();
		$this->register_get_container_schema();
		$this->register_get_page_structure();
		$this->register_get_element_settings();
		$this->register_find_element();
		$this->register_list_pages();
		$this->register_list_templates();
		$this->register_get_global_settings();
	}

	/**
	 * Shared permission callback for read-only tools.
	 *
	 * @since 1.0.0
	 *
	 * @return bool Whether the current user can use read tools.
	 */
	public function check_read_permission(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Registers the list-widgets ability.
	 *
	 * @since 1.0.0
	 */
	private function register_list_widgets(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-widgets',
			array(
				'label'               => __( 'List Elementor Widgets', 'emcp-tools' ),
				'description'         => __( 'Lists Elementor widgets from the curated catalog as a compact index (type, title, tier, one-line use-case, param names). Filter by tier (free/pro/woo), category, or search by intent. Step 1 of discover → get-widget-schema → add-free-widget/add-pro-widget.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_widgets' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'tier'     => array(
							'type'        => 'string',
							'enum'        => array( 'all', 'free', 'pro', 'woo' ),
							'description' => __( 'Filter by tier. Default: all.', 'emcp-tools' ),
						),
						'category' => array(
							'type'        => 'string',
							'description' => __( 'Filter by widget category.', 'emcp-tools' ),
						),
						'search'   => array(
							'type'        => 'string',
							'description' => __( 'Match by intent across title, use-case, and keywords (e.g. "pricing table").', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'widgets' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'type'        => array( 'type' => 'string' ),
									'title'       => array( 'type' => 'string' ),
									'tier'        => array( 'type' => 'string' ),
									'category'    => array( 'type' => 'string' ),
									'use_case'    => array( 'type' => 'string' ),
									'param_names' => array(
										'type'  => 'array',
										'items' => array( 'type' => 'string' ),
									),
									'requires'    => array( 'type' => array( 'string', 'null' ) ),
								),
							),
						),
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
	 * Executes the list-widgets ability.
	 *
	 * Catalog-backed: returns a compact index built from the curated widget
	 * catalog (no dependency on a live Elementor widget registry). Each row
	 * carries type, title, tier, category, a one-line use-case, and the widget's
	 * param names. Supports `tier` (all|free|pro|woo), `category`, and `search`
	 * (intent match across title/use-case/keywords) filters.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $input The input parameters.
	 * @return array The compact widgets index.
	 */
	public function execute_list_widgets( $input = null ): array {
		$tier     = isset( $input['tier'] ) ? sanitize_key( $input['tier'] ) : 'all';
		$category = isset( $input['category'] ) ? sanitize_text_field( $input['category'] ) : '';
		$search   = isset( $input['search'] ) ? sanitize_text_field( $input['search'] ) : '';

		$catalog = '' !== $search
			? EMCP_Tools_Widget_Catalog::search( $search )
			: EMCP_Tools_Widget_Catalog::get();

		$rows = array();
		foreach ( $catalog as $type => $entry ) {
			$entry_tier = $entry['tier'] ?? 'free';
			if ( 'all' !== $tier && $entry_tier !== $tier ) {
				continue;
			}
			if ( '' !== $category && ( $entry['category'] ?? '' ) !== $category ) {
				continue;
			}
			$rows[] = array(
				'type'        => $type,
				'title'       => $entry['title'] ?? $type,
				'tier'        => $entry_tier,
				'category'    => $entry['category'] ?? '',
				'use_case'    => $entry['use_case'] ?? '',
				'param_names' => array_keys( $entry['params'] ?? array() ),
				'requires'    => $entry['requires'] ?? null,
			);
		}

		return array( 'widgets' => $rows );
	}

	/**
	 * Registers the get-widget-schema ability.
	 *
	 * @since 1.0.0
	 */
	private function register_get_widget_schema(): void {
		emcp_tools_register_ability(
			'emcp-tools/get-widget-schema',
			array(
				'label'               => __( 'Get Widget Schema', 'emcp-tools' ),
				'description'         => __( 'Returns curated parameters (params, required, defaults) for a widget type by default. Pass types[] for a batch lookup ({widgets:[...]}), or full:true for the raw auto-generated control schema.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_widget_schema' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'widget_type' => array(
							'type'        => 'string',
							'description' => __( 'A single widget type, e.g. "heading".', 'emcp-tools' ),
						),
						'types'       => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Batch: several widget types in one call. Returns {widgets:[...]}.', 'emcp-tools' ),
						),
						'full'        => array(
							'type'        => 'boolean',
							'description' => __( 'Return the full auto-generated control schema instead of the curated params. Default: false.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'widget_type' => array( 'type' => 'string' ),
						'tier'        => array( 'type' => 'string' ),
						'use_case'    => array( 'type' => 'string' ),
						'params'      => array( 'type' => 'object' ),
						'required'    => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'defaults'    => array( 'type' => 'object' ),
						'schema'      => array( 'type' => 'object' ),
						'widgets'     => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
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
	 * Executes the get-widget-schema ability.
	 *
	 * By default returns curated catalog data (params, required, defaults) for the
	 * requested widget(s) — no live Elementor dependency. Pass `types[]` to batch
	 * several widgets in one call ({widgets:[...]}). Pass `full:true` for the raw
	 * auto-generated control schema, which needs a live widget.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error The widget schema(s) or WP_Error.
	 */
	public function execute_get_widget_schema( $input ) {
		$full  = ! empty( $input['full'] );
		$types = array();

		if ( ! empty( $input['types'] ) && is_array( $input['types'] ) ) {
			$types = array_map( 'sanitize_text_field', $input['types'] );
		} elseif ( ! empty( $input['widget_type'] ) ) {
			$types = array( sanitize_text_field( $input['widget_type'] ) );
		}

		if ( empty( $types ) ) {
			return new \WP_Error( 'missing_widget_type', __( 'Provide widget_type or types[].', 'emcp-tools' ) );
		}

		$build = function ( $type ) use ( $full ) {
			$entry = EMCP_Tools_Widget_Catalog::get_widget( $type );

			if ( $full ) {
				// Escape hatch: full auto-generated control schema (needs a live widget).
				$schema = $this->schema_generator->generate( $type );
				return array(
					'widget_type' => $type,
					'tier'        => EMCP_Tools_Widget_Catalog::tier_of( $type ),
					'use_case'    => $entry['use_case'] ?? '',
					'schema'      => is_wp_error( $schema ) ? array() : $schema,
				);
			}

			if ( null === $entry ) {
				return array(
					'widget_type' => $type,
					'error'       => __( 'Not in the curated catalog. Retry with full:true for the raw control schema.', 'emcp-tools' ),
				);
			}

			return array(
				'widget_type' => $type,
				'tier'        => $entry['tier'] ?? 'free',
				'use_case'    => $entry['use_case'] ?? '',
				'params'      => $entry['params'] ?? array(),
				'required'    => $entry['required'] ?? array(),
				'defaults'    => $entry['defaults'] ?? array(),
			);
		};

		// Batch shape when `types[]` was provided (even if it held one entry).
		$is_batch = ! empty( $input['types'] ) && is_array( $input['types'] );
		if ( $is_batch ) {
			$widgets = array();
			foreach ( $types as $t ) {
				$widgets[] = $build( $t );
			}
			return array( 'widgets' => $widgets );
		}

		return $build( $types[0] );
	}

	// -------------------------------------------------------------------------
	// get-container-schema
	// -------------------------------------------------------------------------

	private function register_get_container_schema(): void {
		emcp_tools_register_ability(
			'emcp-tools/get-container-schema',
			array(
				'label'               => __( 'Get Container Schema', 'emcp-tools' ),
				'description'         => __( 'Returns JSON Schema for all container controls (flex + grid), including flex_direction, justify_content, align_items, flex_wrap, gap, content_width, min_height, container_type, grid controls, background, border, padding, and more.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_container_schema' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'schema' => array( 'type' => 'object' ),
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

	public function execute_get_container_schema( $input ) {
		// Get a temporary container element to introspect its controls.
		$document = \Elementor\Plugin::$instance->documents->get_current();

		// Create a temporary container element to get its controls.
		$element_type = \Elementor\Plugin::$instance->elements_manager->get_element_types( 'container' );

		if ( ! $element_type ) {
			return new \WP_Error( 'container_not_found', __( 'Container element type not available.', 'emcp-tools' ) );
		}

		$controls = $element_type->get_controls();
		$schema   = array(
			'type'       => 'object',
			'description' => 'Settings for the Container element.',
			'properties' => array(),
		);

		foreach ( $controls as $control_id => $control ) {
			$prop = array(
				'type' => $this->map_control_type( $control['type'] ?? 'text' ),
			);

			if ( ! empty( $control['label'] ) ) {
				$prop['description'] = $control['label'];
			}

			if ( isset( $control['default'] ) ) {
				$prop['default'] = $control['default'];
			}

			if ( ! empty( $control['options'] ) && is_array( $control['options'] ) ) {
				$enum = array_values(
					array_filter(
						array_keys( $control['options'] ),
						function ( $value ) {
							return '' !== $value;
						}
					)
				);
				if ( ! empty( $enum ) ) {
					$prop['enum'] = $enum;
				}
			}

			$schema['properties'][ $control_id ] = $prop;
		}

		return array( 'schema' => $schema );
	}

	/**
	 * Maps Elementor control types to JSON Schema types.
	 *
	 * @param string $control_type The Elementor control type.
	 * @return string The JSON Schema type.
	 */
	private function map_control_type( string $control_type ): string {
		$type_map = array(
			'text'       => 'string',
			'textarea'   => 'string',
			'wysiwyg'    => 'string',
			'code'       => 'string',
			'url'        => 'object',
			'media'      => 'object',
			'color'      => 'string',
			'select'     => 'string',
			'select2'    => 'string',
			'choose'     => 'string',
			'font'       => 'string',
			'switcher'   => 'string',
			'number'     => 'number',
			'slider'     => 'object',
			'dimensions' => 'object',
			'image_dimensions' => 'object',
			'repeater'   => 'array',
			'gallery'    => 'array',
			'icons'      => 'object',
			'icon'       => 'string',
			'hidden'     => 'string',
			'heading'    => 'string',
			'raw_html'   => 'string',
			'popover_toggle' => 'string',
		);

		return $type_map[ $control_type ] ?? 'string';
	}

	/**
	 * Registers the get-page-structure ability.
	 *
	 * @since 1.0.0
	 */
	private function register_get_page_structure(): void {
		emcp_tools_register_ability(
			'emcp-tools/get-page-structure',
			array(
				'label'               => __( 'Get Page Structure', 'emcp-tools' ),
				'description'         => __( 'Returns the element tree for an Elementor page, showing all containers, widgets, and their nesting structure. Each element includes its ID, type, widget type (for widgets), and child elements.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_page_structure' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id' => array(
							'type'        => 'integer',
							'description' => __( 'The WordPress post/page ID.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'   => array( 'type' => 'integer' ),
						'title'     => array( 'type' => 'string' ),
						'type'      => array( 'type' => 'string' ),
						'structure' => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
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
	 * Executes the get-page-structure ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error The page structure or WP_Error.
	 */
	public function execute_get_page_structure( $input ) {
		$post_id = absint( $input['post_id'] ?? 0 );

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_Error( 'post_not_found', __( 'Post not found.', 'emcp-tools' ) );
		}

		$data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$doc_type = $this->data->get_document_type( $post_id );

		return array(
			'post_id'   => $post_id,
			'title'     => $post->post_title,
			'type'      => is_wp_error( $doc_type ) ? '' : $doc_type,
			'structure' => $this->simplify_structure( $data ),
		);
	}

	/**
	 * Simplifies the element tree for readability.
	 *
	 * Strips heavy settings data and returns a lightweight tree showing
	 * element IDs, types, and nesting.
	 *
	 * @since 1.0.0
	 *
	 * @param array $elements The raw elements array.
	 * @return array Simplified element tree.
	 */
	private function simplify_structure( array $elements ): array {
		$result = array();

		foreach ( $elements as $element ) {
			$item = array(
				'id'     => $element['id'] ?? '',
				'elType' => $element['elType'] ?? '',
			);

			if ( ! empty( $element['widgetType'] ) ) {
				$item['widgetType'] = $element['widgetType'];
			}

			// Include key settings for context.
			if ( ! empty( $element['settings'] ) ) {
				$key_settings = $this->extract_key_settings( $element );
				if ( ! empty( $key_settings ) ) {
					$item['settings_summary'] = $key_settings;
				}
			}

			if ( ! empty( $element['elements'] ) ) {
				$item['elements'] = $this->simplify_structure( $element['elements'] );
			}

			$result[] = $item;
		}

		return $result;
	}

	/**
	 * Extracts a few key settings for a summary view.
	 *
	 * @since 1.0.0
	 *
	 * @param array $element The element array.
	 * @return array Key settings for summary.
	 */
	private function extract_key_settings( array $element ): array {
		$settings = $element['settings'] ?? array();
		$summary  = array();

		// Widget-specific key settings.
		$key_fields = array( 'title', 'editor', 'text', 'image', 'link', 'html', 'header_size' );
		foreach ( $key_fields as $field ) {
			if ( isset( $settings[ $field ] ) && '' !== $settings[ $field ] ) {
				$value = $settings[ $field ];
				// Truncate long strings.
				if ( is_string( $value ) && strlen( $value ) > 100 ) {
					$value = substr( $value, 0, 100 ) . '...';
				}
				$summary[ $field ] = $value;
			}
		}

		// Container layout settings.
		if ( 'container' === ( $element['elType'] ?? '' ) ) {
			foreach ( array( 'flex_direction', 'content_width', 'container_type' ) as $field ) {
				if ( isset( $settings[ $field ] ) && '' !== $settings[ $field ] ) {
					$summary[ $field ] = $settings[ $field ];
				}
			}
		}

		return $summary;
	}

	/**
	 * Registers the get-element-settings ability.
	 *
	 * @since 1.0.0
	 */
	private function register_get_element_settings(): void {
		emcp_tools_register_ability(
			'emcp-tools/get-element-settings',
			array(
				'label'               => __( 'Get Element Settings', 'emcp-tools' ),
				'description'         => __( 'Returns the current settings for a specific element on a page. Provide the post ID and element ID to retrieve all control values for that element.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_element_settings' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The WordPress post/page ID.', 'emcp-tools' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'The Elementor element ID.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'element_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id' => array( 'type' => 'string' ),
						'elType'     => array( 'type' => 'string' ),
						'widgetType' => array( 'type' => 'string' ),
						'settings'   => array( 'type' => 'object' ),
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
	 * Executes the get-element-settings ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error The element settings or WP_Error.
	 */
	public function execute_get_element_settings( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $input['element_id'] ?? '' );

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
		}

		if ( empty( $element_id ) ) {
			return new \WP_Error( 'missing_element_id', __( 'The element_id parameter is required.', 'emcp-tools' ) );
		}

		$data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$element = $this->data->find_element_by_id( $data, $element_id );

		if ( null === $element ) {
			return new \WP_Error(
				'element_not_found',
				sprintf(
					/* translators: %s: element ID */
					__( 'Element "%s" not found on this page.', 'emcp-tools' ),
					$element_id
				)
			);
		}

		return array(
			'element_id' => $element['id'],
			'elType'     => $element['elType'] ?? '',
			'widgetType' => $element['widgetType'] ?? '',
			'settings'   => $element['settings'] ?? array(),
		);
	}

	// -------------------------------------------------------------------------
	// find-element
	// -------------------------------------------------------------------------

	private function register_find_element(): void {
		emcp_tools_register_ability(
			'emcp-tools/find-element',
			array(
				'label'               => __( 'Find Element', 'emcp-tools' ),
				'description'         => __( 'Searches elements on a page by type, widget type, or settings content. Returns matching element IDs, types, and a settings preview.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_find_element' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID to search.', 'emcp-tools' ),
						),
						'widget_type'   => array(
							'type'        => 'string',
							'description' => __( 'Filter by widget type (e.g. "heading", "button"). Leave empty for all.', 'emcp-tools' ),
						),
						'element_type'  => array(
							'type'        => 'string',
							'enum'        => array( 'container', 'widget' ),
							'description' => __( 'Filter by element type.', 'emcp-tools' ),
						),
						'search_text'   => array(
							'type'        => 'string',
							'description' => __( 'Search for text content in settings values (case-insensitive).', 'emcp-tools' ),
						),
						'setting_key'   => array(
							'type'        => 'string',
							'description' => __( 'Filter by setting key existence (e.g. "title_color").', 'emcp-tools' ),
						),
						'setting_value' => array(
							'type'        => 'string',
							'description' => __( 'Filter by setting value (requires setting_key).', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'matches' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'element_id'  => array( 'type' => 'string' ),
									'elType'      => array( 'type' => 'string' ),
									'widgetType'  => array( 'type' => 'string' ),
									'settings_preview' => array( 'type' => 'object' ),
								),
							),
						),
						'count'   => array( 'type' => 'integer' ),
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

	public function execute_find_element( $input ) {
		$post_id       = absint( $input['post_id'] ?? 0 );
		$widget_type   = sanitize_text_field( $input['widget_type'] ?? '' );
		$element_type  = sanitize_text_field( $input['element_type'] ?? '' );
		$search_text   = $input['search_text'] ?? '';
		$setting_key   = sanitize_text_field( $input['setting_key'] ?? '' );
		$setting_value = $input['setting_value'] ?? null;

		if ( ! $post_id ) {
			return new \WP_Error( 'missing_post_id', __( 'The post_id parameter is required.', 'emcp-tools' ) );
		}

		$data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		$matches = array();
		$this->search_elements( $data, $widget_type, $element_type, $search_text, $setting_key, $setting_value, $matches );

		return array(
			'matches' => $matches,
			'count'   => count( $matches ),
		);
	}

	/**
	 * Recursively searches the element tree for matching elements.
	 *
	 * @param array  $elements      The elements to search.
	 * @param string $widget_type   Widget type filter.
	 * @param string $element_type  Element type filter.
	 * @param string $search_text   Text to search in settings values.
	 * @param string $setting_key   Setting key filter.
	 * @param mixed  $setting_value Setting value filter.
	 * @param array  &$matches      Results array (by reference).
	 */
	private function search_elements( array $elements, string $widget_type, string $element_type, string $search_text, string $setting_key, $setting_value, array &$matches ): void {
		foreach ( $elements as $element ) {
			$el_type    = $element['elType'] ?? '';
			$wt         = $element['widgetType'] ?? '';
			$settings   = $element['settings'] ?? array();
			$is_match   = true;

			// Filter by element type.
			if ( $element_type && $el_type !== $element_type ) {
				$is_match = false;
			}

			// Filter by widget type.
			if ( $is_match && $widget_type && $wt !== $widget_type ) {
				$is_match = false;
			}

			// Filter by setting key.
			if ( $is_match && $setting_key ) {
				if ( ! array_key_exists( $setting_key, $settings ) ) {
					$is_match = false;
				} elseif ( null !== $setting_value && (string) ( $settings[ $setting_key ] ?? '' ) !== (string) $setting_value ) {
					$is_match = false;
				}
			}

			// Filter by search text in settings values.
			if ( $is_match && $search_text ) {
				$found = false;
				$search_lower = strtolower( $search_text );
				foreach ( $settings as $val ) {
					if ( is_string( $val ) && str_contains( strtolower( $val ), $search_lower ) ) {
						$found = true;
						break;
					}
				}
				if ( ! $found ) {
					$is_match = false;
				}
			}

			if ( $is_match ) {
				// Build a preview of key settings (first 5 string values).
				$preview = array();
				$count   = 0;
				foreach ( $settings as $k => $v ) {
					if ( is_string( $v ) && '' !== $v && $count < 5 ) {
						$preview[ $k ] = strlen( $v ) > 100 ? substr( $v, 0, 100 ) . '...' : $v;
						$count++;
					}
				}

				$matches[] = array(
					'element_id'       => $element['id'] ?? '',
					'elType'           => $el_type,
					'widgetType'       => $wt,
					'settings_preview' => $preview,
				);
			}

			// Recurse into children.
			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$this->search_elements( $element['elements'], $widget_type, $element_type, $search_text, $setting_key, $setting_value, $matches );
			}
		}
	}

	/**
	 * Registers the list-pages ability.
	 *
	 * @since 1.0.0
	 */
	private function register_list_pages(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-pages',
			array(
				'label'               => __( 'List Elementor Pages', 'emcp-tools' ),
				'description'         => __( 'Returns all WordPress pages and posts that are built with Elementor. Optionally filter by post type and status.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_pages' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_type' => array(
							'type'        => 'string',
							'description' => __( 'Filter by post type (e.g. "page", "post"). Default: any.', 'emcp-tools' ),
						),
						'status'    => array(
							'type'        => 'string',
							'description' => __( 'Filter by post status (e.g. "publish", "draft"). Default: any.', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'pages' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'post_id'  => array( 'type' => 'integer' ),
									'title'    => array( 'type' => 'string' ),
									'type'     => array( 'type' => 'string' ),
									'status'   => array( 'type' => 'string' ),
									'modified' => array( 'type' => 'string' ),
								),
							),
						),
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
	 * Executes the list-pages ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $input The input parameters.
	 * @return array The pages list.
	 */
	public function execute_list_pages( $input = null ): array {
		$post_type = sanitize_text_field( $input['post_type'] ?? '' );
		$status    = sanitize_text_field( $input['status'] ?? '' );

		$query_args = array(
			'post_type'      => ! empty( $post_type ) ? $post_type : array( 'page', 'post' ),
			'post_status'    => ! empty( $status ) ? $status : 'any',
			'posts_per_page' => 100,
			// No pagination total needed — skip the SQL_CALC_FOUND_ROWS query (F-018).
			'no_found_rows'  => true,
			'meta_query'     => array(
				array(
					'key'   => '_elementor_edit_mode',
					'value' => 'builder',
				),
			),
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		$query = new \WP_Query( $query_args );
		$pages = array();

		foreach ( $query->posts as $post ) {
			$pages[] = array(
				'post_id'  => $post->ID,
				'title'    => $post->post_title,
				'type'     => $post->post_type,
				'status'   => $post->post_status,
				'modified' => $post->post_modified,
			);
		}

		return array( 'pages' => $pages );
	}

	/**
	 * Registers the list-templates ability.
	 *
	 * @since 1.0.0
	 */
	private function register_list_templates(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-templates',
			array(
				'label'               => __( 'List Elementor Templates', 'emcp-tools' ),
				'description'         => __( 'Returns all saved Elementor templates from the template library. Optionally filter by template type (page, section, container).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_templates' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'template_type' => array(
							'type'        => 'string',
							'description' => __( 'Filter by template type (e.g. "page", "section", "container").', 'emcp-tools' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'templates' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'    => array( 'type' => 'integer' ),
									'title' => array( 'type' => 'string' ),
									'type'  => array( 'type' => 'string' ),
									'date'  => array( 'type' => 'string' ),
								),
							),
						),
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
	 * Executes the list-templates ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $input The input parameters.
	 * @return array The templates list.
	 */
	public function execute_list_templates( $input = null ): array {
		$template_type = sanitize_text_field( $input['template_type'] ?? '' );

		$query_args = array(
			'post_type'      => 'elementor_library',
			'post_status'    => 'publish',
			'posts_per_page' => 100,
			// No pagination total needed — skip the SQL_CALC_FOUND_ROWS query (F-018).
			'no_found_rows'  => true,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( ! empty( $template_type ) ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => '_elementor_template_type',
					'value' => $template_type,
				),
			);
		}

		$query     = new \WP_Query( $query_args );
		$templates = array();

		foreach ( $query->posts as $post ) {
			$templates[] = array(
				'id'    => $post->ID,
				'title' => $post->post_title,
				'type'  => get_post_meta( $post->ID, '_elementor_template_type', true ),
				'date'  => $post->post_date,
			);
		}

		return array( 'templates' => $templates );
	}

	/**
	 * Registers the get-global-settings ability.
	 *
	 * @since 1.0.0
	 */
	private function register_get_global_settings(): void {
		emcp_tools_register_ability(
			'emcp-tools/get-global-settings',
			array(
				'label'               => __( 'Get Global Settings', 'emcp-tools' ),
				'description'         => __( 'Returns the active Elementor kit/global settings including colors, typography, spacing, and breakpoints. These are the site-wide design tokens used across all pages.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_global_settings' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'colors'      => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						'typography'  => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						'settings'    => array( 'type' => 'object' ),
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
	 * Executes the get-global-settings ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array|null $input The input parameters (unused).
	 * @return array|\WP_Error The global settings or WP_Error.
	 */
	public function execute_get_global_settings( $input = null ) {
		$kits_manager = \Elementor\Plugin::$instance->kits_manager;
		$kit          = $kits_manager->get_active_kit();

		if ( ! $kit ) {
			return new \WP_Error( 'kit_not_found', __( 'Active Elementor kit not found.', 'emcp-tools' ) );
		}

		$settings = $kit->get_settings();

		// Extract commonly useful global settings.
		$colors     = $settings['system_colors'] ?? $settings['custom_colors'] ?? array();
		$typography = $settings['system_typography'] ?? $settings['custom_typography'] ?? array();

		return array(
			'colors'     => $colors,
			'typography' => $typography,
			'settings'   => $settings,
		);
	}
}

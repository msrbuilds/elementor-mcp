<?php
/**
 * Composite/high-level MCP abilities for Elementor.
 *
 * Registers the build-page tool that creates a complete page from
 * a declarative structure in a single call.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the composite abilities.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Composite_Abilities {

	/**
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * @var Elementor_MCP_Element_Factory
	 */
	private $factory;

	/**
	 * Counter for elements created during build-page execution.
	 *
	 * @var int
	 */
	private $elements_created = 0;

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
			'elementor-mcp/build-page',
		);
	}

	/**
	 * Registers all composite abilities.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		$this->register_build_page();
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

	// -------------------------------------------------------------------------
	// build-page
	// -------------------------------------------------------------------------

	private function register_build_page(): void {
		wp_register_ability(
			'elementor-mcp/build-page',
			array(
				'label'               => __( 'Build Page', 'elementor-mcp' ),
				'description'         => __( 'Creates a complete Elementor page from a declarative structure in a single call. Supports nested containers and any widget types. IMPORTANT LAYOUT RULES: (1) For side-by-side columns, use a parent container with flex_direction=row — children are auto-set to content_width=full with equal percentage widths (e.g. 2 children = 50%, 3 = 33.33%). (2) NEVER set flex_wrap or _flex_size in settings — these cause layout overflow. The tool handles layout automatically. (3) Background colors: set background_background=classic and background_color=#hex on containers. (4) Background images: set background_background=classic, background_image={url,id}, background_size=cover. (5) Background overlay: background_overlay_background=classic, background_overlay_color=#hex, background_overlay_opacity={size:0.7,unit:px}. (6) Text alignment: text_align on text/heading widgets. (7) Use search-images and sideload-image tools to get real images before building.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_build_page' ),
				'permission_callback' => array( $this, 'check_create_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'         => array(
							'type'        => 'string',
							'description' => __( 'Page title.', 'elementor-mcp' ),
						),
						'status'        => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish' ),
							'description' => __( 'Post status. Default: draft.', 'elementor-mcp' ),
						),
						'post_type'     => array(
							'type'        => 'string',
							'enum'        => array( 'page', 'post' ),
							'description' => __( 'Post type. Default: page.', 'elementor-mcp' ),
						),
						'page_settings' => array(
							'type'        => 'object',
							'description' => __( 'Page-level Elementor settings (background, padding, etc.).', 'elementor-mcp' ),
						),
						'structure'     => array(
							'type'        => 'array',
							'description' => __( 'Declarative element tree. Each item has type (container|widget), settings, and optionally children (for containers) or widget_type (for widgets).', 'elementor-mcp' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'type'        => array(
										'type' => 'string',
										'enum' => array( 'container', 'widget' ),
									),
									'widget_type' => array( 'type' => 'string' ),
									'settings'    => array( 'type' => 'object' ),
									'children'    => array( 'type' => 'array' ),
								),
								'required' => array( 'type' ),
							),
						),
					),
					'required'   => array( 'title', 'structure' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'          => array( 'type' => 'integer' ),
						'title'            => array( 'type' => 'string' ),
						'edit_url'         => array( 'type' => 'string' ),
						'preview_url'      => array( 'type' => 'string' ),
						'elements_created' => array( 'type' => 'integer' ),
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
	 * Executes the build-page ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_build_page( $input ) {
		$title         = sanitize_text_field( $input['title'] ?? '' );
		$status        = sanitize_key( $input['status'] ?? 'draft' );
		$post_type     = sanitize_key( $input['post_type'] ?? 'page' );
		$page_settings = $input['page_settings'] ?? array();
		$structure     = $input['structure'] ?? array();

		if ( empty( $title ) ) {
			return new \WP_Error( 'missing_title', __( 'The title parameter is required.', 'elementor-mcp' ) );
		}

		if ( empty( $structure ) || ! is_array( $structure ) ) {
			return new \WP_Error( 'missing_structure', __( 'The structure parameter is required and must be an array.', 'elementor-mcp' ) );
		}

		// 1. Create the WordPress post.
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

		// 2. Build the Elementor element tree from the declarative structure.
		$this->elements_created = 0;
		$elements               = $this->build_elements( $structure );

		// 3. Save the element data.
		$result = $this->data->save_page_data( $post_id, $elements );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// 4. Save page settings if provided.
		if ( ! empty( $page_settings ) ) {
			$this->data->save_page_settings( $post_id, $page_settings );
		}

		$edit_url    = admin_url( 'post.php?post=' . $post_id . '&action=elementor' );
		$preview_url = get_permalink( $post_id );

		return array(
			'post_id'          => $post_id,
			'title'            => $title,
			'edit_url'         => $edit_url,
			'preview_url'      => $preview_url ? $preview_url : '',
			'elements_created' => $this->elements_created,
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Recursively builds Elementor elements from the declarative structure.
	 *
	 * When a parent container uses flex_direction=row and has multiple
	 * children, this method auto-sets each child container to
	 * content_width=full with an equal percentage width (e.g. 25% for
	 * 4 children). This matches Elementor's native column layout
	 * pattern. No flex_wrap or _flex_size overrides are applied.
	 *
	 * @param array  $items            The declarative structure items.
	 * @param bool   $is_inner         Whether these are nested (inner) containers.
	 * @param string $parent_direction The parent container's flex_direction.
	 * @return array The Elementor element tree.
	 */
	private function build_elements( array $items, bool $is_inner = false, string $parent_direction = '' ): array {
		$elements  = array();
		$is_in_row = ( 'row' === $parent_direction || 'row-reverse' === $parent_direction );

		// Calculate equal width percentage for row children.
		$child_count = count( $items );
		if ( $is_in_row && $child_count > 1 ) {
			$equal_width = round( 100 / $child_count, 2 );
		}

		foreach ( $items as $item ) {
			$type = $item['type'] ?? '';

			if ( 'container' === $type ) {
				$settings = $item['settings'] ?? array();
				$children = $item['children'] ?? array();

				// Determine this container's direction for its children.
				$direction = $settings['flex_direction'] ?? '';

				// Inner containers inside a row parent need content_width=full
				// with a percentage width so they act as proper columns.
				if ( $is_in_row && $child_count > 1 ) {
					$has_width = isset( $settings['width'] )
						|| isset( $settings['_flex_size'] )
						|| isset( $settings['_flex_grow'] );
					if ( ! $has_width ) {
						$settings['content_width'] = 'full';
						$settings['width']         = array(
							'size' => $equal_width,
							'unit' => '%',
						);
					}
				}

				// Recursively build children with this container's direction.
				$child_elements = $this->build_elements( $children, true, $direction );

				$container = $this->factory->create_container( $settings, $child_elements );

				if ( $is_inner ) {
					$container['isInner'] = true;
				}

				$this->elements_created++;
				$elements[] = $container;

			} elseif ( 'widget' === $type ) {
				$widget_type = $item['widget_type'] ?? '';
				$settings    = $item['settings'] ?? array();

				if ( ! empty( $widget_type ) ) {
					$widget = $this->factory->create_widget( $widget_type, $settings );
					$this->elements_created++;
					$elements[] = $widget;
				}
			}
		}

		return $elements;
	}

}

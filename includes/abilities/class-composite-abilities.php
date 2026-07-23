<?php
/**
 * Composite/high-level MCP abilities for Elementor.
 *
 * Registers the build-page tool that creates a complete page from
 * a declarative structure in a single call.
 *
 * @package EMCP_Tools
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
class EMCP_Tools_Composite_Abilities {

	/**
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * @var EMCP_Tools_Element_Factory
	 */
	private $factory;

	/**
	 * Counter for elements created during build-page execution.
	 *
	 * @var int
	 */
	private $elements_created = 0;

	/**
	 * Non-fatal notes from the last build — shorthand coercions and skipped
	 * nodes — so build-page never reports a silent partial success.
	 *
	 * @var string[]
	 */
	private $warnings = array();

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
			'emcp-tools/build-page',
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
		emcp_tools_register_ability(
			'emcp-tools/build-page',
			array(
				'label'               => __( 'Build Page', 'emcp-tools' ),
				'description'         => __( 'Creates a complete Elementor page from a declarative structure in a single call. Supports nested containers and any widget types. IMPORTANT LAYOUT RULES: (1) For side-by-side columns, use a parent container with flex_direction=row, children are auto-set to content_width=full with equal percentage widths (e.g. 2 children = 50%, 3 = 33.33%). (2) NEVER set flex_wrap or _flex_size in settings, these cause layout overflow. The tool handles layout automatically. (3) Background colors: set background_background=classic and background_color=#hex on containers. (4) Background images: set background_background=classic, background_image={url,id}, background_size=cover. (5) Background overlay: background_overlay_background=classic, background_overlay_color=#hex, background_overlay_opacity={size:0.7,unit:px}. (6) Text alignment: text_align on text/heading widgets. (7) Use search-images and sideload-image tools to get real images before building.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_build_page' ),
				'permission_callback' => array( $this, 'check_create_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'         => array(
							'type'        => 'string',
							'description' => __( 'Page title.', 'emcp-tools' ),
						),
						'status'        => array(
							'type'        => 'string',
							'enum'        => array( 'draft', 'publish' ),
							'description' => __( 'Post status. Default: draft.', 'emcp-tools' ),
						),
						'post_type'     => array(
							'type'        => 'string',
							'enum'        => array( 'page', 'post' ),
							'description' => __( 'Post type. Default: page.', 'emcp-tools' ),
						),
						'page_settings' => array(
							'type'        => 'object',
							'description' => __( 'Page-level Elementor settings (background, padding, etc.).', 'emcp-tools' ),
						),
						'structure'     => array(
							'type'        => 'array',
							'description' => __( 'Declarative element tree. Each item is type:"container" (with children) or type:"widget" (with widget_type + settings). Shorthand is accepted and coerced: type:"heading" is read as a heading widget, and any node with children is treated as a container, but the response lists these coercions under "warnings", so prefer the explicit shape. Every widget needs a widget_type; a widget with none is skipped and reported.', 'emcp-tools' ),
							'items'       => array(
								'type'       => 'object',
								'properties' => array(
									'type'        => array(
										'type'        => 'string',
										'description' => __( 'Preferably "container" or "widget". A widget name (e.g. "heading") is accepted as shorthand and coerced, with a note in "warnings".', 'emcp-tools' ),
									),
									'widget_type' => array( 'type' => 'string' ),
									'settings'    => array( 'type' => 'object' ),
									'children'    => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
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
						'warnings'         => array(
							'type'        => 'array',
							'description' => __( 'Non-fatal notes: nodes that were coerced from shorthand or skipped. If present, some elements did not land exactly as written, fix and rebuild or patch with the layout/widget tools.', 'emcp-tools' ),
							'items'       => array( 'type' => 'string' ),
						),
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
			return new \WP_Error( 'missing_title', __( 'The title parameter is required.', 'emcp-tools' ) );
		}

		if ( empty( $structure ) || ! is_array( $structure ) ) {
			return new \WP_Error( 'missing_structure', __( 'The structure parameter is required and must be an array.', 'emcp-tools' ) );
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
		$this->warnings         = array();
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

		$out = array(
			'post_id'          => $post_id,
			'title'            => $title,
			'edit_url'         => $edit_url,
			'preview_url'      => $preview_url ? $preview_url : '',
			'elements_created' => $this->elements_created,
		);
		// Surface coercions/skips so the caller learns a node didn't land as
		// written, instead of a silent partial success (cf. the empty-column case).
		if ( ! empty( $this->warnings ) ) {
			$out['warnings'] = array_values( array_unique( $this->warnings ) );
		}
		return $out;
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
	 * Widgets that are direct children of a row parent are automatically
	 * wrapped in a column container with the same equal percentage width.
	 * Elementor's flex model requires a container as the flex item — a
	 * widget placed directly in a row container has no flex-basis and
	 * will not form a proper grid column.
	 *
	 * @param array  $items            The declarative structure items.
	 * @param bool   $is_inner         Whether these are nested (inner) containers.
	 * @param string $parent_direction The parent container's flex_direction.
	 * @return array The Elementor element tree.
	 */
	/**
	 * Normalises a shorthand node into the canonical {type, widget_type} shape.
	 *
	 * Weaker models routinely write `{ "type": "heading", ... }` instead of
	 * `{ "type": "widget", "widget_type": "heading" }`, or give a container a
	 * `type` other than "container" while still supplying `children`. Rather than
	 * silently dropping those (which is how a request "succeeds" yet renders empty
	 * columns), coerce the obvious intent and note it in the warnings.
	 *
	 * @param array $item One declarative node.
	 * @return array The node with a canonical `type` (and `widget_type` for widgets).
	 */
	private function normalize_node( array $item ): array {
		$type = isset( $item['type'] ) ? (string) $item['type'] : '';

		if ( 'container' === $type || 'widget' === $type ) {
			return $item;
		}

		$has_children = isset( $item['children'] ) && is_array( $item['children'] ) && ! empty( $item['children'] );

		// Anything carrying children is a container regardless of its label.
		if ( $has_children ) {
			if ( '' !== $type ) {
				$this->warnings[] = sprintf( 'Treated node type "%s" as a container because it has children.', $type );
			}
			$item['type'] = 'container';
			return $item;
		}

		// A non-container/widget type with no children is a widget shorthand:
		// the type IS the widget type (e.g. "heading", "button", "image").
		if ( '' !== $type ) {
			if ( empty( $item['widget_type'] ) ) {
				$item['widget_type'] = $type;
				$this->warnings[]    = sprintf( 'Interpreted "type":"%1$s" as a "%1$s" widget, prefer {"type":"widget","widget_type":"%1$s"}.', $type );
			} else {
				$this->warnings[] = sprintf( 'Node had type "%1$s" and widget_type "%2$s"; used widget_type "%2$s".', $type, (string) $item['widget_type'] );
			}
			$item['type'] = 'widget';
		}

		return $item;
	}

	private function build_elements( array $items, bool $is_inner = false, string $parent_direction = '' ): array {
		$elements  = array();
		$is_in_row = ( 'row' === $parent_direction || 'row-reverse' === $parent_direction );

		// Calculate equal width percentage for row children.
		$child_count = count( $items );
		if ( $is_in_row && $child_count > 1 ) {
			$equal_width = round( 100 / $child_count, 2 );
		}

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			$item = $this->normalize_node( $item );
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

				if ( empty( $widget_type ) ) {
					$this->warnings[] = 'Skipped a widget with no widget_type, give each widget a widget_type (e.g. "heading", "button", "image").';
				} else {
					$widget = $this->build_widget( $widget_type, $settings );
					$this->elements_created++;

					// Widgets placed directly inside a row container must be
					// wrapped in a column container. Elementor's flexbox model
					// requires a container as the flex item — a bare widget has
					// no flex-basis and will not form a proper grid column; it
					// just stretches to fill the row instead.
					if ( $is_in_row && $child_count > 1 ) {
						$col_settings = array(
							'content_width' => 'full',
							'flex_direction' => 'column',
							'width'         => array(
								'size' => $equal_width,
								'unit' => '%',
							),
						);
						$col            = $this->factory->create_container( $col_settings, array( $widget ) );
						$col['isInner'] = true;
						$this->elements_created++;
						$elements[] = $col;
					} else {
						$elements[] = $widget;
					}
				}
			}
		}

		return $elements;
	}

	/**
	 * Builds a widget element for build-page, atomic-aware.
	 *
	 * For an Elementor 4.0+ atomic widget type this routes the node's settings
	 * through the SAME convenience mapping the add-atomic-* tools use
	 * (EMCP_Tools_Atomic_Widget_Map), so friendly params like `content`,
	 * `image_url`/`alt` and `video_url` become the typed props Elementor stores
	 * instead of being discarded. Any style params on the node (padding,
	 * background_color, …) are applied as a local class, exactly as the
	 * individual atomic tools do. Everything else falls back to the legacy
	 * raw-settings widget.
	 *
	 * @since 3.6.2
	 *
	 * @param string $widget_type Widget type.
	 * @param array  $settings    Node settings (convenience params for atomic widgets).
	 * @return array Widget element structure.
	 */
	private function build_widget( string $widget_type, array $settings ): array {
		if (
			class_exists( 'EMCP_Tools_Atomic_Widget_Map' )
			&& EMCP_Tools_Atomic_Widget_Map::is_atomic( $widget_type )
		) {
			$mapped  = EMCP_Tools_Atomic_Widget_Map::settings( $widget_type, $settings );
			$element = $this->factory->create_atomic_widget( $widget_type, $mapped );

			// Style params (padding, background_color, min_height, …) become a
			// local class, mirroring the add-atomic-* tools.
			if ( class_exists( 'EMCP_Tools_Atomic_Styles' ) ) {
				$common = EMCP_Tools_Atomic_Styles::build_common_props( $settings );
				if ( ! empty( $common ) ) {
					$style = EMCP_Tools_Atomic_Styles::create_local_class( $element['id'], $common );
					EMCP_Tools_Atomic_Styles::apply_to_element( $element, $style['class_id'], $style['style_def'] );
				}
			}

			return $element;
		}

		return $this->factory->create_widget( $widget_type, $settings );
	}

}

<?php
/**
 * Widget MCP abilities for Elementor.
 *
 * Registers the universal add-widget/update-widget tools plus convenience
 * shortcut tools for common widgets (heading, text, image, button, etc.).
 * Pro widget tools register only when Elementor Pro is active.
 *
 * @package EMCP_Tools
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the widget abilities.
 *
 * @since 1.0.0
 */
class EMCP_Tools_Widget_Abilities {

	/**
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * @var EMCP_Tools_Element_Factory
	 */
	private $factory;

	/**
	 * @var EMCP_Tools_Schema_Generator
	 */
	private $schema_generator;

	/**
	 * @var EMCP_Tools_Settings_Validator
	 */
	private $validator;

	/**
	 * Tracked ability names.
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param EMCP_Tools_Data               $data             The data access layer.
	 * @param EMCP_Tools_Element_Factory    $factory          The element factory.
	 * @param EMCP_Tools_Schema_Generator   $schema_generator The schema generator.
	 * @param EMCP_Tools_Settings_Validator $validator        The settings validator.
	 */
	public function __construct(
		EMCP_Tools_Data $data,
		EMCP_Tools_Element_Factory $factory,
		EMCP_Tools_Schema_Generator $schema_generator,
		EMCP_Tools_Settings_Validator $validator
	) {
		$this->data             = $data;
		$this->factory          = $factory;
		$this->schema_generator = $schema_generator;
		$this->validator        = $validator;
	}

	/**
	 * Returns the ability names registered by this class.
	 *
	 * @since 1.0.0
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Registers all widget abilities.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		// Universal tools.
		$this->register_add_widget();
		$this->register_update_widget();

		// Core widget convenience tools.
		$this->register_add_heading();
		$this->register_add_text_editor();
		$this->register_add_image();
		$this->register_add_button();
		$this->register_add_video();
		$this->register_add_icon();
		$this->register_add_spacer();
		$this->register_add_divider();
		$this->register_add_icon_box();

		// Extended core widget convenience tools.
		$this->register_add_accordion();
		$this->register_add_alert();
		$this->register_add_counter();
		$this->register_add_google_maps();
		$this->register_add_icon_list();
		$this->register_add_image_box();
		$this->register_add_image_carousel();
		$this->register_add_progress();
		$this->register_add_social_icons();
		$this->register_add_star_rating();
		$this->register_add_tabs();
		$this->register_add_testimonial();
		$this->register_add_toggle();
		$this->register_add_html();
		$this->register_add_menu_anchor();
		$this->register_add_shortcode();
		$this->register_add_rating();
		$this->register_add_text_path();

		// Pro widget convenience tools (only if Pro is active).
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$this->register_add_form();
			$this->register_add_posts_grid();
			$this->register_add_countdown();
			$this->register_add_price_table();
			$this->register_add_flip_box();
			$this->register_add_animated_headline();
			$this->register_add_call_to_action();
			$this->register_add_slides();
			$this->register_add_testimonial_carousel();
			$this->register_add_price_list();
			$this->register_add_gallery();
			$this->register_add_share_buttons();
			$this->register_add_table_of_contents();
			$this->register_add_blockquote();
			$this->register_add_lottie();
			$this->register_add_hotspot();
			$this->register_add_nav_menu();
			$this->register_add_loop_grid();
			$this->register_add_loop_carousel();
			$this->register_add_media_carousel();
			$this->register_add_nested_tabs();
			$this->register_add_nested_accordion();
			$this->register_add_portfolio();
			$this->register_add_author_box();
			$this->register_add_login();
			$this->register_add_code_highlight();
			$this->register_add_reviews();
			$this->register_add_off_canvas();
			$this->register_add_progress_tracker();
			$this->register_add_search();

			// WooCommerce widget convenience tools (only if WooCommerce is active).
			if ( class_exists( 'WooCommerce' ) ) {
				$this->register_add_wc_products();
				$this->register_add_wc_add_to_cart();
				$this->register_add_wc_cart();
				$this->register_add_wc_checkout();
				$this->register_add_wc_menu_cart();
			}
		}
	}

	/**
	 * Permission check for widget editing.
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

	// =========================================================================
	// Universal: add-widget
	// =========================================================================

	private function register_add_widget(): void {
		$this->ability_names[] = 'elementor-mcp/add-widget';

		emcp_tools_register_ability(
			'elementor-mcp/add-widget',
			array(
				'label'               => __( 'Add Widget', 'emcp-tools' ),
				'description'         => __( 'Adds any Elementor widget to a container. Use get-widget-schema to discover the available settings for each widget type.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'parent_id'   => array(
							'type'        => 'string',
							'description' => __( 'Parent container element ID.', 'emcp-tools' ),
						),
						'position'    => array(
							'type'        => 'integer',
							'description' => __( 'Insert position. -1 = append.', 'emcp-tools' ),
						),
						'widget_type' => array(
							'type'        => 'string',
							'description' => __( 'The widget type name (e.g. "heading", "button", "image").', 'emcp-tools' ),
						),
						'settings'    => array(
							'type'        => 'object',
							'description' => __( 'Widget-specific settings.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'parent_id', 'widget_type' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id'  => array( 'type' => 'string' ),
						'widget_type' => array( 'type' => 'string' ),
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
	 * Executes the add-widget ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_widget( $input ) {
		$post_id     = absint( $input['post_id'] ?? 0 );
		$parent_id   = sanitize_text_field( $input['parent_id'] ?? '' );
		$position    = intval( $input['position'] ?? -1 );
		$widget_type = sanitize_text_field( $input['widget_type'] ?? '' );
		$settings    = $input['settings'] ?? array();

		if ( ! $post_id || empty( $parent_id ) || empty( $widget_type ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, parent_id, and widget_type are required.', 'emcp-tools' ) );
		}

		// Validate widget type exists.
		$widget_instance = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
		if ( ! $widget_instance ) {
			return new \WP_Error(
				'invalid_widget_type',
				/* translators: %s: widget type name */
				sprintf( __( 'Widget type "%s" not found.', 'emcp-tools' ), $widget_type )
			);
		}

		// Validate settings if provided.
		if ( ! empty( $settings ) ) {
			$valid = $this->validator->validate( $widget_type, $settings );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$widget = $this->factory->create_widget( $widget_type, $settings );

		$inserted = $this->data->insert_element( $page_data, $parent_id, $widget, $position );

		if ( ! $inserted ) {
			return new \WP_Error( 'parent_not_found', __( 'Parent container not found.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'element_id'  => $widget['id'],
			'widget_type' => $widget_type,
		);
	}

	// =========================================================================
	// Universal: update-widget
	// =========================================================================

	private function register_update_widget(): void {
		$this->ability_names[] = 'elementor-mcp/update-widget';

		emcp_tools_register_ability(
			'elementor-mcp/update-widget',
			array(
				'label'               => __( 'Update Widget', 'emcp-tools' ),
				'description'         => __( 'Updates settings on an existing widget. Settings are merged (partial update).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_widget' ),
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
							'description' => __( 'The widget element ID.', 'emcp-tools' ),
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => __( 'Partial settings to merge.', 'emcp-tools' ),
						),
					),
					'required'   => array( 'post_id', 'element_id', 'settings' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'    => array( 'type' => 'boolean' ),
						'element_id' => array( 'type' => 'string' ),
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
	 * Executes the update-widget ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_update_widget( $input ) {
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

		// Find the widget to validate its type.
		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'emcp-tools' ) );
		}

		if ( ( $element['elType'] ?? '' ) !== 'widget' ) {
			return new \WP_Error( 'not_a_widget', __( 'Target element is not a widget.', 'emcp-tools' ) );
		}

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update widget settings.', 'emcp-tools' ) );
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'    => true,
			'element_id' => $element_id,
		);
	}

	// =========================================================================
	// Convenience tool helper
	// =========================================================================

	/**
	 * Registers a convenience widget tool and adds it to ability_names.
	 *
	 * @param string $name        Ability name suffix (e.g. 'add-heading').
	 * @param string $label       Human label.
	 * @param string $description Tool description.
	 * @param array  $extra_props Extra input schema properties beyond post_id/parent_id/position.
	 * @param array  $required    Required property names (post_id and parent_id always added).
	 * @param string $widget_type The Elementor widget type name.
	 * @param array  $defaults    Default settings for this widget type.
	 */
	private function register_convenience_tool(
		string $name,
		string $label,
		string $description,
		array $extra_props,
		array $required,
		string $widget_type,
		array $defaults = array()
	): void {
		$full_name             = 'elementor-mcp/' . $name;
		$this->ability_names[] = $full_name;

		$base_props = array(
			'post_id'   => array(
				'type'        => 'integer',
				'description' => __( 'Page ID.', 'emcp-tools' ),
			),
			'parent_id' => array(
				'type'        => 'string',
				'description' => __( 'Parent container ID.', 'emcp-tools' ),
			),
			'position'  => array(
				'type'        => 'integer',
				'description' => __( 'Insert position; -1 appends.', 'emcp-tools' ),
			),
		);

		$all_required = array_unique( array_merge( array( 'post_id', 'parent_id' ), $required ) );

		// Token optimization (Curated Slim): publish only the core params
		// (content + primary layout + colours) in the schema. Deep style-group
		// controls (typography, shadows, borders, spacing, CSS filters) are
		// dropped from the published schema but STILL work at execution —
		// execute_convenience_tool passes through ANY input key — and stay fully
		// discoverable via get-widget-schema. This roughly halves the per-tool
		// token footprint without removing any capability.
		$schema_props = self::slim_convenience_props( $extra_props, $required, 5 );
		if ( count( $schema_props ) < count( $extra_props ) ) {
			$description .= ' ' . __( 'All other settings pass through; get-widget-schema for the full list.', 'emcp-tools' );
		}

		emcp_tools_register_ability(
			$full_name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => 'emcp-tools',
				'execute_callback'    => function ( $input ) use ( $widget_type, $extra_props, $defaults ) {
					return $this->execute_convenience_tool( $input, $widget_type, array_keys( $extra_props ), $defaults );
				},
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array_merge( $base_props, $schema_props ),
					'required'   => $all_required,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id' => array( 'type' => 'string' ),
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
	 * Curated Slim: drop deep style-group controls from a convenience tool's
	 * published input schema.
	 *
	 * Keeps content, primary layout, and colour params; removes typography,
	 * shadow, stroke, border, spacing, and CSS-filter group controls (the
	 * token-heavy long tail). Dropped params still apply at execution via the
	 * pass-through in execute_convenience_tool, and the full set stays available
	 * from get-widget-schema. Required params are always kept.
	 *
	 * @since 2.2.0
	 *
	 * @param array $props Full extra_props for the tool.
	 * @param array $keep  Param names to keep regardless (the tool's required list).
	 * @param int   $max   Cap on the number of published params (0 = no cap). The
	 *                     first $max in content-first order are kept; required
	 *                     params are always kept even beyond the cap.
	 * @return array Slimmed properties (insertion order preserved).
	 */
	private static function slim_convenience_props( array $props, array $keep = array(), int $max = 0 ): array {
		$advanced = array( 'typography', 'box_shadow', 'text_shadow', 'text_stroke', 'css_filters', 'border', 'padding', 'margin' );
		$out      = array();
		foreach ( $props as $key => $def ) {
			if ( in_array( $key, $keep, true ) ) {
				$out[ $key ] = $def;
				continue;
			}
			$is_advanced = false;
			foreach ( $advanced as $needle ) {
				if ( false !== strpos( (string) $key, $needle ) ) {
					$is_advanced = true;
					break;
				}
			}
			if ( ! $is_advanced ) {
				$out[ $key ] = $def;
			}
		}

		// Cap the published param count: keep the first $max (content/primary
		// params come first in each definition) plus any required param.
		if ( $max > 0 && count( $out ) > $max ) {
			$capped = array();
			foreach ( $out as $key => $def ) {
				if ( count( $capped ) < $max || in_array( $key, $keep, true ) ) {
					$capped[ $key ] = $def;
				}
			}
			$out = $capped;
		}

		return $out;
	}

	/**
	 * Shared execution for convenience tools.
	 *
	 * Extracts the widget-specific settings keys from input and delegates to add-widget logic.
	 *
	 * @param array  $input        The input parameters.
	 * @param string $widget_type  The Elementor widget type.
	 * @param array  $setting_keys Setting keys to extract from input.
	 * @param array  $defaults     Default settings.
	 * @return array|\WP_Error
	 */
	private function execute_convenience_tool( $input, string $widget_type, array $setting_keys, array $defaults ) {
		$settings = $defaults;

		// Keys that are tool params, not widget settings.
		$non_setting_keys = array( 'post_id', 'parent_id', 'position' );

		// Pass through all input keys that aren't base tool params.
		// This allows group controls (typography_*), responsive suffixes
		// (_mobile, _tablet), and common advanced controls (_margin, etc.)
		// to flow through without being explicitly listed in extra_props.
		foreach ( $input as $key => $value ) {
			if ( in_array( $key, $non_setting_keys, true ) ) {
				continue;
			}
			$settings[ $key ] = $value;
		}

		return $this->execute_add_widget(
			array(
				'post_id'     => $input['post_id'] ?? 0,
				'parent_id'   => $input['parent_id'] ?? '',
				'position'    => $input['position'] ?? -1,
				'widget_type' => $widget_type,
				'settings'    => $settings,
			)
		);
	}

	// =========================================================================
	// Core convenience tools
	// =========================================================================

	private function register_add_heading(): void {
		$this->register_convenience_tool(
			'add-heading',
			__( 'Add Heading', 'emcp-tools' ),
			__( 'Adds a heading widget. Supports full typography (set typography_typography=custom first), text stroke, text shadow, blend mode, hover color. Also accepts responsive suffixes (align_tablet, align_mobile) and common advanced controls (_margin, _padding, _background_*, _border_*, etc).', 'emcp-tools' ),
			array(
				'title'                       => array( 'type' => 'string', 'description' => __( 'Heading text.', 'emcp-tools' ) ),
				'header_size'                 => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'HTML tag. Default: h2.', 'emcp-tools' ) ),
				'size'                        => array( 'type' => 'string', 'enum' => array( 'default', 'small', 'medium', 'large', 'xl', 'xxl' ), 'description' => __( 'Elementor size preset.', 'emcp-tools' ) ),
				'align'                       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Text alignment. Responsive: align_tablet, align_mobile.', 'emcp-tools' ) ),
				'title_color'                 => array( 'type' => 'string', 'description' => __( 'Heading color (hex/rgba).', 'emcp-tools' ) ),
				'title_hover_color'           => array( 'type' => 'string', 'description' => __( 'Heading hover color (hex/rgba).', 'emcp-tools' ) ),
				'link'                        => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'emcp-tools' ) ),
				'blend_mode'                  => array( 'type' => 'string', 'enum' => array( '', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'saturation', 'color', 'difference', 'exclusion', 'hue', 'luminosity' ), 'description' => __( 'CSS blend mode.', 'emcp-tools' ) ),
				// Typography group — set typography_typography=custom to activate.
				'typography_typography'        => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable typography controls.', 'emcp-tools' ) ),
				'typography_font_family'       => array( 'type' => 'string', 'description' => __( 'Font family (e.g. "Roboto", "Montserrat").', 'emcp-tools' ) ),
				'typography_font_size'         => array( 'type' => 'object', 'description' => __( 'Font size: {size, unit}. Units: px, em, rem, vw.', 'emcp-tools' ) ),
				'typography_font_weight'       => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Font weight.', 'emcp-tools' ) ),
				'typography_text_transform'    => array( 'type' => 'string', 'enum' => array( '', 'uppercase', 'lowercase', 'capitalize', 'none' ), 'description' => __( 'Text transform.', 'emcp-tools' ) ),
				'typography_font_style'        => array( 'type' => 'string', 'enum' => array( '', 'normal', 'italic', 'oblique' ), 'description' => __( 'Font style.', 'emcp-tools' ) ),
				'typography_text_decoration'   => array( 'type' => 'string', 'enum' => array( '', 'none', 'underline', 'overline', 'line-through' ), 'description' => __( 'Text decoration.', 'emcp-tools' ) ),
				'typography_line_height'       => array( 'type' => 'object', 'description' => __( 'Line height: {size, unit}. Units: px, em.', 'emcp-tools' ) ),
				'typography_letter_spacing'    => array( 'type' => 'object', 'description' => __( 'Letter spacing: {size, unit}. Units: px, em.', 'emcp-tools' ) ),
				'typography_word_spacing'      => array( 'type' => 'object', 'description' => __( 'Word spacing: {size, unit}.', 'emcp-tools' ) ),
				// Text stroke — set text_stroke_text_stroke=yes to activate.
				'text_stroke_text_stroke'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable text stroke.', 'emcp-tools' ) ),
				'text_stroke_stroke_width'     => array( 'type' => 'object', 'description' => __( 'Stroke width: {size, unit}.', 'emcp-tools' ) ),
				'text_stroke_stroke_color'     => array( 'type' => 'string', 'description' => __( 'Stroke color (hex/rgba).', 'emcp-tools' ) ),
				// Text shadow.
				'title_text_shadow_text_shadow' => array( 'type' => 'object', 'description' => __( 'Text shadow: {horizontal, vertical, blur, color}.', 'emcp-tools' ) ),
			),
			array( 'title' ),
			'heading',
			array( 'header_size' => 'h2' )
		);
	}

	private function register_add_text_editor(): void {
		$this->register_convenience_tool(
			'add-text-editor',
			__( 'Add Text Editor', 'emcp-tools' ),
			__( 'Adds a rich text editor widget. Supports typography (set typography_typography=custom), drop cap, text columns, and text color. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'editor'     => array( 'type' => 'string', 'description' => __( 'HTML content.', 'emcp-tools' ) ),
				'align'      => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Text alignment. Responsive: align_tablet, align_mobile.', 'emcp-tools' ) ),
				'text_color' => array( 'type' => 'string', 'description' => __( 'Text color (hex/rgba).', 'emcp-tools' ) ),
				// Drop cap.
				'drop_cap'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable drop cap on first letter.', 'emcp-tools' ) ),
				// Text columns.
				'column_gap' => array( 'type' => 'object', 'description' => __( 'Column gap: {size, unit}. Works with text_columns.', 'emcp-tools' ) ),
				'text_columns' => array( 'type' => 'string', 'description' => __( 'Number of text columns (1-10).', 'emcp-tools' ) ),
				// Typography group.
				'typography_typography'     => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable typography controls.', 'emcp-tools' ) ),
				'typography_font_family'    => array( 'type' => 'string', 'description' => __( 'Font family.', 'emcp-tools' ) ),
				'typography_font_size'      => array( 'type' => 'object', 'description' => __( 'Font size: {size, unit}.', 'emcp-tools' ) ),
				'typography_font_weight'    => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Font weight.', 'emcp-tools' ) ),
				'typography_text_transform' => array( 'type' => 'string', 'enum' => array( '', 'uppercase', 'lowercase', 'capitalize', 'none' ), 'description' => __( 'Text transform.', 'emcp-tools' ) ),
				'typography_line_height'    => array( 'type' => 'object', 'description' => __( 'Line height: {size, unit}.', 'emcp-tools' ) ),
				'typography_letter_spacing' => array( 'type' => 'object', 'description' => __( 'Letter spacing: {size, unit}.', 'emcp-tools' ) ),
			),
			array( 'editor' ),
			'text-editor'
		);
	}

	private function register_add_image(): void {
		$this->register_convenience_tool(
			'add-image',
			__( 'Add Image', 'emcp-tools' ),
			__( 'Adds an image widget. Supports width, max-width, opacity, border, border-radius, box shadow, CSS filters (brightness, contrast, saturation, hue), and hover effects. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'image'          => array( 'type' => 'object', 'description' => __( 'Image object with url (required) and optional id.', 'emcp-tools' ) ),
				'image_size'     => array( 'type' => 'string', 'enum' => array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), 'description' => __( 'Image size preset.', 'emcp-tools' ) ),
				'align'          => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Image alignment. Responsive: align_tablet, align_mobile.', 'emcp-tools' ) ),
				'caption_source' => array( 'type' => 'string', 'enum' => array( 'none', 'attachment', 'custom' ), 'description' => __( 'Caption source.', 'emcp-tools' ) ),
				'caption'        => array( 'type' => 'string', 'description' => __( 'Custom caption text.', 'emcp-tools' ) ),
				'link_to'        => array( 'type' => 'string', 'enum' => array( 'none', 'file', 'custom' ), 'description' => __( 'Link behavior.', 'emcp-tools' ) ),
				'link'           => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'emcp-tools' ) ),
				// Sizing.
				'width'          => array( 'type' => 'object', 'description' => __( 'Image width: {size, unit}. Units: px, %, vw.', 'emcp-tools' ) ),
				'max_width'      => array( 'type' => 'object', 'description' => __( 'Max width: {size, unit}.', 'emcp-tools' ) ),
				'height'         => array( 'type' => 'object', 'description' => __( 'Image height: {size, unit}.', 'emcp-tools' ) ),
				'object_fit'     => array( 'type' => 'string', 'enum' => array( '', 'fill', 'cover', 'contain' ), 'description' => __( 'Object fit when height is set.', 'emcp-tools' ) ),
				// Style.
				'opacity'        => array( 'type' => 'object', 'description' => __( 'Image opacity: {size, unit}. 0-1 range.', 'emcp-tools' ) ),
				'hover_animation' => array( 'type' => 'string', 'description' => __( 'Hover animation (grow, shrink, pulse, push, etc).', 'emcp-tools' ) ),
				'hover_opacity'  => array( 'type' => 'object', 'description' => __( 'Hover opacity: {size, unit}. 0-1 range.', 'emcp-tools' ) ),
				// CSS Filters.
				'css_filters_css_filter' => array( 'type' => 'string', 'enum' => array( 'custom', '' ), 'description' => __( 'Set to "custom" to enable CSS filter controls.', 'emcp-tools' ) ),
				'css_filters_blur'       => array( 'type' => 'object', 'description' => __( 'Blur: {size, unit}. px.', 'emcp-tools' ) ),
				'css_filters_brightness' => array( 'type' => 'object', 'description' => __( 'Brightness: {size, unit}. 0-200%.', 'emcp-tools' ) ),
				'css_filters_contrast'   => array( 'type' => 'object', 'description' => __( 'Contrast: {size, unit}. 0-200%.', 'emcp-tools' ) ),
				'css_filters_saturate'   => array( 'type' => 'object', 'description' => __( 'Saturation: {size, unit}. 0-200%.', 'emcp-tools' ) ),
				'css_filters_hue'        => array( 'type' => 'object', 'description' => __( 'Hue rotation: {size, unit}. 0-360deg.', 'emcp-tools' ) ),
				// Border.
				'image_border_border'    => array( 'type' => 'string', 'enum' => array( '', 'solid', 'double', 'dotted', 'dashed', 'groove' ), 'description' => __( 'Border style.', 'emcp-tools' ) ),
				'image_border_width'     => array( 'type' => 'object', 'description' => __( 'Border width: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'image_border_color'     => array( 'type' => 'string', 'description' => __( 'Border color.', 'emcp-tools' ) ),
				'image_border_radius'    => array( 'type' => 'object', 'description' => __( 'Border radius: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				// Box shadow.
				'image_box_shadow_box_shadow_type' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable box shadow.', 'emcp-tools' ) ),
				'image_box_shadow_box_shadow'      => array( 'type' => 'object', 'description' => __( 'Box shadow: {horizontal, vertical, blur, spread, color}.', 'emcp-tools' ) ),
			),
			array( 'image' ),
			'image'
		);
	}

	private function register_add_button(): void {
		$this->register_convenience_tool(
			'add-button',
			__( 'Add Button', 'emcp-tools' ),
			__( 'Adds a button widget. Supports typography (set typography_typography=custom), border, background, hover colors, box shadow, and text shadow. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'text'          => array( 'type' => 'string', 'description' => __( 'Button text.', 'emcp-tools' ) ),
				'link'          => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'emcp-tools' ) ),
				'size'          => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size.', 'emcp-tools' ) ),
				'button_type'   => array( 'type' => 'string', 'enum' => array( '', 'info', 'success', 'warning', 'danger' ), 'description' => __( 'Button style type.', 'emcp-tools' ) ),
				'align'         => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Button alignment. Responsive: align_tablet, align_mobile.', 'emcp-tools' ) ),
				'selected_icon' => array( 'type' => 'object', 'description' => __( 'Icon object with value and library.', 'emcp-tools' ) ),
				'icon_align'    => array( 'type' => 'string', 'enum' => array( 'row', 'row-reverse' ), 'description' => __( 'Icon position.', 'emcp-tools' ) ),
				'icon_indent'   => array( 'type' => 'object', 'description' => __( 'Icon spacing: {size, unit}.', 'emcp-tools' ) ),
				// Colors.
				'button_text_color'       => array( 'type' => 'string', 'description' => __( 'Text color (hex/rgba).', 'emcp-tools' ) ),
				'background_color'        => array( 'type' => 'string', 'description' => __( 'Background color (hex/rgba).', 'emcp-tools' ) ),
				// Hover colors.
				'hover_color'             => array( 'type' => 'string', 'description' => __( 'Hover text color.', 'emcp-tools' ) ),
				'button_background_hover_color' => array( 'type' => 'string', 'description' => __( 'Hover background color.', 'emcp-tools' ) ),
				'hover_animation'         => array( 'type' => 'string', 'description' => __( 'Hover animation (e.g. grow, shrink, pulse, push).', 'emcp-tools' ) ),
				// Border.
				'border_border'           => array( 'type' => 'string', 'enum' => array( '', 'solid', 'double', 'dotted', 'dashed', 'groove' ), 'description' => __( 'Border style.', 'emcp-tools' ) ),
				'border_width'            => array( 'type' => 'object', 'description' => __( 'Border width: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'border_color'            => array( 'type' => 'string', 'description' => __( 'Border color.', 'emcp-tools' ) ),
				'border_radius'           => array( 'type' => 'object', 'description' => __( 'Border radius: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				// Box shadow.
				'button_box_shadow_box_shadow_type' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable box shadow.', 'emcp-tools' ) ),
				'button_box_shadow_box_shadow'      => array( 'type' => 'object', 'description' => __( 'Box shadow: {horizontal, vertical, blur, spread, color}.', 'emcp-tools' ) ),
				// Typography group.
				'typography_typography'    => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable typography controls.', 'emcp-tools' ) ),
				'typography_font_family'   => array( 'type' => 'string', 'description' => __( 'Font family.', 'emcp-tools' ) ),
				'typography_font_size'     => array( 'type' => 'object', 'description' => __( 'Font size: {size, unit}.', 'emcp-tools' ) ),
				'typography_font_weight'   => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Font weight.', 'emcp-tools' ) ),
				'typography_text_transform' => array( 'type' => 'string', 'enum' => array( '', 'uppercase', 'lowercase', 'capitalize', 'none' ), 'description' => __( 'Text transform.', 'emcp-tools' ) ),
				'typography_letter_spacing' => array( 'type' => 'object', 'description' => __( 'Letter spacing: {size, unit}.', 'emcp-tools' ) ),
				// Text shadow.
				'text_shadow_text_shadow'  => array( 'type' => 'object', 'description' => __( 'Text shadow: {horizontal, vertical, blur, color}.', 'emcp-tools' ) ),
				// Padding.
				'button_padding'          => array( 'type' => 'object', 'description' => __( 'Button padding: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
			),
			array( 'text' ),
			'button',
			array( 'text' => 'Click here', 'size' => 'sm' )
		);
	}

	private function register_add_video(): void {
		$this->register_convenience_tool(
			'add-video',
			__( 'Add Video', 'emcp-tools' ),
			__( 'Adds a video widget. Supports YouTube, Vimeo, Dailymotion, self-hosted. Options: start/end time, lazy load, privacy mode, image overlay, play icon. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'video_type'     => array( 'type' => 'string', 'enum' => array( 'youtube', 'vimeo', 'dailymotion', 'hosted' ), 'description' => __( 'Video source type.', 'emcp-tools' ) ),
				'youtube_url'    => array( 'type' => 'string', 'description' => __( 'YouTube URL.', 'emcp-tools' ) ),
				'vimeo_url'      => array( 'type' => 'string', 'description' => __( 'Vimeo URL.', 'emcp-tools' ) ),
				'dailymotion_url' => array( 'type' => 'string', 'description' => __( 'Dailymotion URL.', 'emcp-tools' ) ),
				'insert_url'     => array( 'type' => 'object', 'description' => __( 'Self-hosted video URL object: {url}.', 'emcp-tools' ) ),
				'autoplay'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay on load.', 'emcp-tools' ) ),
				'mute'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Mute audio.', 'emcp-tools' ) ),
				'loop'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Loop video.', 'emcp-tools' ) ),
				'controls'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show player controls.', 'emcp-tools' ) ),
				'start'          => array( 'type' => 'integer', 'description' => __( 'Start time in seconds.', 'emcp-tools' ) ),
				'end'            => array( 'type' => 'integer', 'description' => __( 'End time in seconds.', 'emcp-tools' ) ),
				'yt_privacy'     => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'YouTube privacy-enhanced mode.', 'emcp-tools' ) ),
				'lazy_load'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Lazy load the video.', 'emcp-tools' ) ),
				'rel'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show related videos at end (YouTube).', 'emcp-tools' ) ),
				'modestbranding' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Modest branding (YouTube).', 'emcp-tools' ) ),
				// Image overlay.
				'show_image_overlay' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show image overlay (poster).', 'emcp-tools' ) ),
				'image_overlay'      => array( 'type' => 'object', 'description' => __( 'Overlay image: {url, id}.', 'emcp-tools' ) ),
				'show_play_icon'     => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show play icon on overlay.', 'emcp-tools' ) ),
				// Aspect ratio.
				'aspect_ratio'       => array( 'type' => 'string', 'enum' => array( '169', '219', '43', '32', '11', '916' ), 'description' => __( 'Video aspect ratio. Values: 169=16:9, 219=21:9, 43=4:3, 32=3:2, 11=1:1, 916=9:16.', 'emcp-tools' ) ),
			),
			array(),
			'video',
			array( 'video_type' => 'youtube' )
		);
	}

	private function register_add_icon(): void {
		$this->register_convenience_tool(
			'add-icon',
			__( 'Add Icon', 'emcp-tools' ),
			__( 'Adds an icon widget. Supports Font Awesome and SVG icons, view modes (default/stacked/framed), hover colors, rotate, padding, border radius, and hover animation. For SVG, first use upload-svg-icon.', 'emcp-tools' ),
			array(
				'selected_icon'    => array( 'type' => 'object', 'description' => __( 'Icon object. Font Awesome: { "value": "fas fa-star", "library": "fa-solid" }. SVG: { "value": { "id": 123, "url": "..." }, "library": "svg" }. Libraries: fa-solid, fa-regular, fa-brands.', 'emcp-tools' ) ),
				'view'             => array( 'type' => 'string', 'enum' => array( 'default', 'stacked', 'framed' ), 'description' => __( 'Icon view mode.', 'emcp-tools' ) ),
				'shape'            => array( 'type' => 'string', 'enum' => array( 'circle', 'square' ), 'description' => __( 'Icon shape (for stacked/framed).', 'emcp-tools' ) ),
				'primary_color'    => array( 'type' => 'string', 'description' => __( 'Primary/icon color (hex/rgba).', 'emcp-tools' ) ),
				'secondary_color'  => array( 'type' => 'string', 'description' => __( 'Secondary/background color for stacked/framed (hex/rgba).', 'emcp-tools' ) ),
				'hover_primary_color'   => array( 'type' => 'string', 'description' => __( 'Hover icon color.', 'emcp-tools' ) ),
				'hover_secondary_color' => array( 'type' => 'string', 'description' => __( 'Hover background color for stacked/framed.', 'emcp-tools' ) ),
				'hover_animation'  => array( 'type' => 'string', 'description' => __( 'Hover animation (grow, shrink, pulse, push, etc).', 'emcp-tools' ) ),
				'size'             => array( 'type' => 'object', 'description' => __( 'Icon size: {size, unit}.', 'emcp-tools' ) ),
				'icon_padding'     => array( 'type' => 'object', 'description' => __( 'Icon padding: {size, unit}. For stacked/framed.', 'emcp-tools' ) ),
				'rotate'           => array( 'type' => 'object', 'description' => __( 'Icon rotation: {size, unit}. Degrees.', 'emcp-tools' ) ),
				'border_width'     => array( 'type' => 'object', 'description' => __( 'Border width for framed view: {size, unit}.', 'emcp-tools' ) ),
				'border_radius'    => array( 'type' => 'object', 'description' => __( 'Border radius: {size, unit}.', 'emcp-tools' ) ),
				'link'             => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'emcp-tools' ) ),
				'align'            => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Icon alignment. Responsive: align_tablet, align_mobile.', 'emcp-tools' ) ),
			),
			array(),
			'icon',
			array( 'selected_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ) )
		);
	}

	private function register_add_spacer(): void {
		$this->register_convenience_tool(
			'add-spacer',
			__( 'Add Spacer', 'emcp-tools' ),
			__( 'Adds a spacer widget for vertical spacing between elements.', 'emcp-tools' ),
			array(
				'space' => array( 'type' => 'object', 'description' => __( 'Spacer height: { "size": 50, "unit": "px" }.', 'emcp-tools' ) ),
			),
			array(),
			'spacer',
			array( 'space' => array( 'size' => 50, 'unit' => 'px' ) )
		);
	}

	private function register_add_divider(): void {
		$this->register_convenience_tool(
			'add-divider',
			__( 'Add Divider', 'emcp-tools' ),
			__( 'Adds a horizontal divider/separator widget with style, weight, color, and width options.', 'emcp-tools' ),
			array(
				'style'  => array( 'type' => 'string', 'enum' => array( 'solid', 'dashed', 'dotted', 'double' ), 'description' => __( 'Divider line style.', 'emcp-tools' ) ),
				'weight' => array( 'type' => 'object', 'description' => __( 'Line weight: { "size": 1, "unit": "px" }.', 'emcp-tools' ) ),
				'color'  => array( 'type' => 'string', 'description' => __( 'Divider color (hex).', 'emcp-tools' ) ),
				'width'  => array( 'type' => 'object', 'description' => __( 'Divider width: { "size": 100, "unit": "%" }.', 'emcp-tools' ) ),
				'gap'    => array( 'type' => 'object', 'description' => __( 'Gap above/below: { "size": 15, "unit": "px" }.', 'emcp-tools' ) ),
			),
			array(),
			'divider',
			array( 'style' => 'solid' )
		);
	}

	private function register_add_icon_box(): void {
		$this->register_convenience_tool(
			'add-icon-box',
			__( 'Add Icon Box', 'emcp-tools' ),
			__( 'Adds an icon box widget. Supports icon position (top/left/right), title typography (set title_typography_typography=custom), description typography, icon spacing, hover colors, and hover animation. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'selected_icon'    => array( 'type' => 'object', 'description' => __( 'Icon object. Font Awesome: { "value": "fas fa-star", "library": "fa-solid" }. SVG: { "value": { "id": 123, "url": "..." }, "library": "svg" }.', 'emcp-tools' ) ),
				'title_text'       => array( 'type' => 'string', 'description' => __( 'Box title.', 'emcp-tools' ) ),
				'description_text' => array( 'type' => 'string', 'description' => __( 'Box description.', 'emcp-tools' ) ),
				'view'             => array( 'type' => 'string', 'enum' => array( 'default', 'stacked', 'framed' ), 'description' => __( 'Icon view mode.', 'emcp-tools' ) ),
				'shape'            => array( 'type' => 'string', 'enum' => array( 'circle', 'square' ), 'description' => __( 'Icon shape.', 'emcp-tools' ) ),
				'position'         => array( 'type' => 'string', 'enum' => array( 'top', 'left', 'right' ), 'description' => __( 'Icon position relative to content.', 'emcp-tools' ) ),
				'title_size'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: h3.', 'emcp-tools' ) ),
				'link'             => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'emcp-tools' ) ),
				// Colors.
				'title_color'      => array( 'type' => 'string', 'description' => __( 'Title color (hex/rgba).', 'emcp-tools' ) ),
				'description_color' => array( 'type' => 'string', 'description' => __( 'Description color (hex/rgba).', 'emcp-tools' ) ),
				'primary_color'    => array( 'type' => 'string', 'description' => __( 'Icon primary color.', 'emcp-tools' ) ),
				'secondary_color'  => array( 'type' => 'string', 'description' => __( 'Icon secondary/background color.', 'emcp-tools' ) ),
				// Hover.
				'hover_primary_color'   => array( 'type' => 'string', 'description' => __( 'Hover icon color.', 'emcp-tools' ) ),
				'hover_secondary_color' => array( 'type' => 'string', 'description' => __( 'Hover icon background color.', 'emcp-tools' ) ),
				'hover_animation'       => array( 'type' => 'string', 'description' => __( 'Hover animation.', 'emcp-tools' ) ),
				// Spacing.
				'icon_space'       => array( 'type' => 'object', 'description' => __( 'Space between icon and content: {size, unit}.', 'emcp-tools' ) ),
				'icon_size'        => array( 'type' => 'object', 'description' => __( 'Icon size: {size, unit}.', 'emcp-tools' ) ),
				'title_bottom_space' => array( 'type' => 'object', 'description' => __( 'Space below title: {size, unit}.', 'emcp-tools' ) ),
				// Title typography.
				'title_typography_typography'     => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable title typography.', 'emcp-tools' ) ),
				'title_typography_font_family'    => array( 'type' => 'string', 'description' => __( 'Title font family.', 'emcp-tools' ) ),
				'title_typography_font_size'      => array( 'type' => 'object', 'description' => __( 'Title font size: {size, unit}.', 'emcp-tools' ) ),
				'title_typography_font_weight'    => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Title font weight.', 'emcp-tools' ) ),
				// Description typography.
				'description_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable description typography.', 'emcp-tools' ) ),
				'description_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Description font family.', 'emcp-tools' ) ),
				'description_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Description font size: {size, unit}.', 'emcp-tools' ) ),
			),
			array( 'title_text' ),
			'icon-box',
			array(
				'selected_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ),
			)
		);
	}

	// =========================================================================
	// Extended core convenience tools
	// =========================================================================

	private function register_add_accordion(): void {
		$this->register_convenience_tool(
			'add-accordion',
			__( 'Add Accordion', 'emcp-tools' ),
			__( 'Adds an accordion widget. Supports title/content colors, background, border, typography (set title_typography_typography=custom), spacing, icon color, and FAQ schema. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'tabs'                 => array(
					'type'        => 'array',
					'description' => __( 'Array of accordion items with tab_title and tab_content.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'tab_title'   => array( 'type' => 'string' ),
							'tab_content' => array( 'type' => 'string' ),
						),
					),
				),
				'selected_icon'        => array( 'type' => 'object', 'description' => __( 'Icon when collapsed. Default: fas fa-plus.', 'emcp-tools' ) ),
				'selected_active_icon' => array( 'type' => 'object', 'description' => __( 'Icon when expanded. Default: fas fa-minus.', 'emcp-tools' ) ),
				'title_html_tag'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), 'description' => __( 'Title HTML tag. Default: div.', 'emcp-tools' ) ),
				'faq_schema'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable FAQ schema markup.', 'emcp-tools' ) ),
				// Style - Title.
				'title_color'          => array( 'type' => 'string', 'description' => __( 'Title text color.', 'emcp-tools' ) ),
				'title_background'     => array( 'type' => 'string', 'description' => __( 'Title background color.', 'emcp-tools' ) ),
				'tab_active_color'     => array( 'type' => 'string', 'description' => __( 'Active title text color.', 'emcp-tools' ) ),
				'tab_active_background' => array( 'type' => 'string', 'description' => __( 'Active title background color.', 'emcp-tools' ) ),
				'title_padding'        => array( 'type' => 'object', 'description' => __( 'Title padding: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				// Style - Icon.
				'icon_color'           => array( 'type' => 'string', 'description' => __( 'Icon color.', 'emcp-tools' ) ),
				'icon_active_color'    => array( 'type' => 'string', 'description' => __( 'Active icon color.', 'emcp-tools' ) ),
				'icon_space'           => array( 'type' => 'object', 'description' => __( 'Space between icon and title: {size, unit}.', 'emcp-tools' ) ),
				// Style - Content.
				'content_color'        => array( 'type' => 'string', 'description' => __( 'Content text color.', 'emcp-tools' ) ),
				'content_background_color' => array( 'type' => 'string', 'description' => __( 'Content background color.', 'emcp-tools' ) ),
				'content_padding'      => array( 'type' => 'object', 'description' => __( 'Content padding: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				// Border.
				'border_width'         => array( 'type' => 'object', 'description' => __( 'Item border width: {size, unit}.', 'emcp-tools' ) ),
				'border_color'         => array( 'type' => 'string', 'description' => __( 'Item border color.', 'emcp-tools' ) ),
				// Title typography.
				'title_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable title typography.', 'emcp-tools' ) ),
				'title_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Title font family.', 'emcp-tools' ) ),
				'title_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Title font size: {size, unit}.', 'emcp-tools' ) ),
				'title_typography_font_weight' => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Title font weight.', 'emcp-tools' ) ),
				// Content typography.
				'content_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable content typography.', 'emcp-tools' ) ),
				'content_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Content font family.', 'emcp-tools' ) ),
				'content_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Content font size: {size, unit}.', 'emcp-tools' ) ),
			),
			array( 'tabs' ),
			'accordion',
			array( 'title_html_tag' => 'div' )
		);
	}

	private function register_add_alert(): void {
		$this->register_convenience_tool(
			'add-alert',
			__( 'Add Alert', 'emcp-tools' ),
			__( 'Adds an alert/notice widget with type, title, and description.', 'emcp-tools' ),
			array(
				'alert_type'        => array( 'type' => 'string', 'enum' => array( 'info', 'success', 'warning', 'danger' ), 'description' => __( 'Alert type. Default: info.', 'emcp-tools' ) ),
				'alert_title'       => array( 'type' => 'string', 'description' => __( 'Alert title.', 'emcp-tools' ) ),
				'alert_description' => array( 'type' => 'string', 'description' => __( 'Alert description/content.', 'emcp-tools' ) ),
				'show_dismiss'      => array( 'type' => 'string', 'enum' => array( 'show', '' ), 'description' => __( 'Show dismiss button. Default: show.', 'emcp-tools' ) ),
			),
			array( 'alert_title' ),
			'alert',
			array( 'alert_type' => 'info', 'show_dismiss' => 'show' )
		);
	}

	private function register_add_counter(): void {
		$this->register_convenience_tool(
			'add-counter',
			__( 'Add Counter', 'emcp-tools' ),
			__( 'Adds an animated counter widget that counts up to a number.', 'emcp-tools' ),
			array(
				'starting_number'    => array( 'type' => 'integer', 'description' => __( 'Start value. Default: 0.', 'emcp-tools' ) ),
				'ending_number'      => array( 'type' => 'integer', 'description' => __( 'End value. Default: 100.', 'emcp-tools' ) ),
				'prefix'             => array( 'type' => 'string', 'description' => __( 'Text before number (e.g. "$").', 'emcp-tools' ) ),
				'suffix'             => array( 'type' => 'string', 'description' => __( 'Text after number (e.g. "%", "+").', 'emcp-tools' ) ),
				'duration'           => array( 'type' => 'integer', 'description' => __( 'Animation duration in ms. Default: 2000.', 'emcp-tools' ) ),
				'thousand_separator' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show thousand separators.', 'emcp-tools' ) ),
				'title'              => array( 'type' => 'string', 'description' => __( 'Counter label/title.', 'emcp-tools' ) ),
			),
			array( 'ending_number' ),
			'counter',
			array( 'starting_number' => 0, 'ending_number' => 100, 'duration' => 2000 )
		);
	}

	private function register_add_google_maps(): void {
		$this->register_convenience_tool(
			'add-google-maps',
			__( 'Add Google Maps', 'emcp-tools' ),
			__( 'Adds an embedded Google Maps widget with address, zoom, and height.', 'emcp-tools' ),
			array(
				'address' => array( 'type' => 'string', 'description' => __( 'Location address or search query.', 'emcp-tools' ) ),
				'zoom'    => array( 'type' => 'object', 'description' => __( 'Zoom level: { "size": 10, "unit": "px" }. Range 1-20.', 'emcp-tools' ) ),
				'height'  => array( 'type' => 'object', 'description' => __( 'Map height: { "size": 300, "unit": "px" }.', 'emcp-tools' ) ),
			),
			array( 'address' ),
			'google_maps',
			array( 'zoom' => array( 'size' => 10, 'unit' => 'px' ) )
		);
	}

	private function register_add_icon_list(): void {
		$this->register_convenience_tool(
			'add-icon-list',
			__( 'Add Icon List', 'emcp-tools' ),
			__( 'Adds a list widget with icons and text. Great for feature lists, checklists, and contact info.', 'emcp-tools' ),
			array(
				'icon_list' => array(
					'type'        => 'array',
					'description' => __( 'Array of list items with text, selected_icon, and optional link.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'text'          => array( 'type' => 'string' ),
							'selected_icon' => array( 'type' => 'object' ),
							'link'          => array( 'type' => 'object' ),
						),
					),
				),
				'view'      => array( 'type' => 'string', 'enum' => array( 'traditional', 'inline' ), 'description' => __( 'Layout: traditional (vertical) or inline. Default: traditional.', 'emcp-tools' ) ),
			),
			array( 'icon_list' ),
			'icon-list',
			array( 'view' => 'traditional' )
		);
	}

	private function register_add_image_box(): void {
		$this->register_convenience_tool(
			'add-image-box',
			__( 'Add Image Box', 'emcp-tools' ),
			__( 'Adds an image box widget with image, title, and description. Great for service cards.', 'emcp-tools' ),
			array(
				'image'            => array( 'type' => 'object', 'description' => __( 'Image object with url and optional id.', 'emcp-tools' ) ),
				'title_text'       => array( 'type' => 'string', 'description' => __( 'Box title.', 'emcp-tools' ) ),
				'description_text' => array( 'type' => 'string', 'description' => __( 'Box description.', 'emcp-tools' ) ),
				'link'             => array( 'type' => 'object', 'description' => __( 'Link object with url key.', 'emcp-tools' ) ),
				'title_size'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: h3.', 'emcp-tools' ) ),
			),
			array( 'title_text' ),
			'image-box',
			array( 'title_size' => 'h3' )
		);
	}

	private function register_add_image_carousel(): void {
		$this->register_convenience_tool(
			'add-image-carousel',
			__( 'Add Image Carousel', 'emcp-tools' ),
			__( 'Adds a rotating image carousel/slider widget.', 'emcp-tools' ),
			array(
				'carousel'       => array(
					'type'        => 'array',
					'description' => __( 'Array of image objects with url and optional id.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'url' => array( 'type' => 'string' ),
							'id'  => array( 'type' => 'integer' ),
						),
					),
				),
				'slides_to_show' => array( 'type' => 'string', 'enum' => array( '1', '2', '3', '4', '5', '6', '7', '8', '9', '10' ), 'description' => __( 'Number of slides visible.', 'emcp-tools' ) ),
				'navigation'     => array( 'type' => 'string', 'enum' => array( 'both', 'arrows', 'dots', 'none' ), 'description' => __( 'Navigation type. Default: both.', 'emcp-tools' ) ),
				'autoplay'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay slides. Default: yes.', 'emcp-tools' ) ),
				'autoplay_speed' => array( 'type' => 'integer', 'description' => __( 'Autoplay interval in ms. Default: 5000.', 'emcp-tools' ) ),
				'infinite'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Infinite loop. Default: yes.', 'emcp-tools' ) ),
			),
			array( 'carousel' ),
			'image-carousel',
			array( 'navigation' => 'both', 'autoplay' => 'yes', 'infinite' => 'yes', 'autoplay_speed' => 5000 )
		);
	}

	private function register_add_progress(): void {
		$this->register_convenience_tool(
			'add-progress',
			__( 'Add Progress Bar', 'emcp-tools' ),
			__( 'Adds an animated progress bar widget with label and percentage.', 'emcp-tools' ),
			array(
				'title'              => array( 'type' => 'string', 'description' => __( 'Progress bar label.', 'emcp-tools' ) ),
				'progress_type'      => array( 'type' => 'string', 'enum' => array( '', 'info', 'success', 'warning', 'danger' ), 'description' => __( 'Color preset type.', 'emcp-tools' ) ),
				'percent'            => array( 'type' => 'object', 'description' => __( 'Progress percentage: { "size": 50, "unit": "%" }.', 'emcp-tools' ) ),
				'display_percentage' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show percentage value. Default: yes.', 'emcp-tools' ) ),
				'inner_text'         => array( 'type' => 'string', 'description' => __( 'Text inside the progress bar.', 'emcp-tools' ) ),
			),
			array(),
			'progress',
			array( 'percent' => array( 'size' => 50, 'unit' => '%' ), 'display_percentage' => 'yes' )
		);
	}

	private function register_add_social_icons(): void {
		$this->register_convenience_tool(
			'add-social-icons',
			__( 'Add Social Icons', 'emcp-tools' ),
			__( 'Adds social media icon links. Great for headers and footers.', 'emcp-tools' ),
			array(
				'social_icon_list' => array(
					'type'        => 'array',
					'description' => __( 'Array of social items with social_icon and link.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'social_icon' => array( 'type' => 'object', 'description' => __( 'Icon: { "value": "fab fa-facebook", "library": "fa-brands" }.', 'emcp-tools' ) ),
							'link'        => array( 'type' => 'object', 'description' => __( 'URL object with url key.', 'emcp-tools' ) ),
						),
					),
				),
				'shape'            => array( 'type' => 'string', 'enum' => array( 'rounded', 'square', 'circle' ), 'description' => __( 'Icon shape. Default: rounded.', 'emcp-tools' ) ),
				'columns'          => array( 'type' => 'integer', 'description' => __( 'Grid columns. 0 = auto.', 'emcp-tools' ) ),
				'align'            => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Alignment. Default: center.', 'emcp-tools' ) ),
			),
			array( 'social_icon_list' ),
			'social-icons',
			array( 'shape' => 'rounded' )
		);
	}

	private function register_add_star_rating(): void {
		$this->register_convenience_tool(
			'add-star-rating',
			__( 'Add Star Rating', 'emcp-tools' ),
			__( 'Adds a star rating display widget.', 'emcp-tools' ),
			array(
				'rating_scale' => array( 'type' => 'string', 'enum' => array( '5', '10' ), 'description' => __( 'Rating scale. Default: 5.', 'emcp-tools' ) ),
				'rating'       => array( 'type' => 'object', 'description' => __( 'Rating value: { "size": 5, "unit": "px" }. Step: 0.1.', 'emcp-tools' ) ),
				'star_style'   => array( 'type' => 'string', 'enum' => array( 'star_fontawesome', 'star_unicode' ), 'description' => __( 'Star icon style.', 'emcp-tools' ) ),
				'title'        => array( 'type' => 'string', 'description' => __( 'Optional rating title.', 'emcp-tools' ) ),
			),
			array(),
			'star-rating',
			array( 'rating_scale' => '5', 'rating' => array( 'size' => 5, 'unit' => 'px' ) )
		);
	}

	private function register_add_tabs(): void {
		$this->register_convenience_tool(
			'add-tabs',
			__( 'Add Tabs', 'emcp-tools' ),
			__( 'Adds a tabbed content widget with horizontal or vertical layout.', 'emcp-tools' ),
			array(
				'tabs' => array(
					'type'        => 'array',
					'description' => __( 'Array of tab items with tab_title and tab_content.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'tab_title'   => array( 'type' => 'string' ),
							'tab_content' => array( 'type' => 'string' ),
						),
					),
				),
				'type' => array( 'type' => 'string', 'enum' => array( 'horizontal', 'vertical' ), 'description' => __( 'Tab layout direction. Default: horizontal.', 'emcp-tools' ) ),
			),
			array( 'tabs' ),
			'tabs',
			array( 'type' => 'horizontal' )
		);
	}

	private function register_add_testimonial(): void {
		$this->register_convenience_tool(
			'add-testimonial',
			__( 'Add Testimonial', 'emcp-tools' ),
			__( 'Adds a testimonial widget with quote, author name, job title, and image.', 'emcp-tools' ),
			array(
				'testimonial_content'        => array( 'type' => 'string', 'description' => __( 'Testimonial/quote text.', 'emcp-tools' ) ),
				'testimonial_image'          => array( 'type' => 'object', 'description' => __( 'Author image object with url and optional id.', 'emcp-tools' ) ),
				'testimonial_name'           => array( 'type' => 'string', 'description' => __( 'Author name.', 'emcp-tools' ) ),
				'testimonial_job'            => array( 'type' => 'string', 'description' => __( 'Author job title/role.', 'emcp-tools' ) ),
				'testimonial_image_position' => array( 'type' => 'string', 'enum' => array( 'aside', 'top' ), 'description' => __( 'Image position. Default: aside.', 'emcp-tools' ) ),
			),
			array( 'testimonial_content', 'testimonial_name' ),
			'testimonial',
			array( 'testimonial_image_position' => 'aside' )
		);
	}

	private function register_add_toggle(): void {
		$this->register_convenience_tool(
			'add-toggle',
			__( 'Add Toggle', 'emcp-tools' ),
			__( 'Adds a toggle widget (multiple items can be open). Supports title/content colors, background, border, typography (set title_typography_typography=custom), spacing, and icon color. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'tabs'                 => array(
					'type'        => 'array',
					'description' => __( 'Array of toggle items with tab_title and tab_content.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'tab_title'   => array( 'type' => 'string' ),
							'tab_content' => array( 'type' => 'string' ),
						),
					),
				),
				'selected_icon'        => array( 'type' => 'object', 'description' => __( 'Icon when collapsed.', 'emcp-tools' ) ),
				'selected_active_icon' => array( 'type' => 'object', 'description' => __( 'Icon when expanded.', 'emcp-tools' ) ),
				'title_html_tag'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), 'description' => __( 'Title HTML tag. Default: div.', 'emcp-tools' ) ),
				// Style - Title.
				'title_color'          => array( 'type' => 'string', 'description' => __( 'Title text color.', 'emcp-tools' ) ),
				'title_background'     => array( 'type' => 'string', 'description' => __( 'Title background color.', 'emcp-tools' ) ),
				'tab_active_color'     => array( 'type' => 'string', 'description' => __( 'Active title text color.', 'emcp-tools' ) ),
				'tab_active_background' => array( 'type' => 'string', 'description' => __( 'Active title background color.', 'emcp-tools' ) ),
				'title_padding'        => array( 'type' => 'object', 'description' => __( 'Title padding: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				// Style - Icon.
				'icon_color'           => array( 'type' => 'string', 'description' => __( 'Icon color.', 'emcp-tools' ) ),
				'icon_active_color'    => array( 'type' => 'string', 'description' => __( 'Active icon color.', 'emcp-tools' ) ),
				'icon_space'           => array( 'type' => 'object', 'description' => __( 'Space between icon and title: {size, unit}.', 'emcp-tools' ) ),
				// Style - Content.
				'content_color'        => array( 'type' => 'string', 'description' => __( 'Content text color.', 'emcp-tools' ) ),
				'content_background_color' => array( 'type' => 'string', 'description' => __( 'Content background color.', 'emcp-tools' ) ),
				'content_padding'      => array( 'type' => 'object', 'description' => __( 'Content padding: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				// Border.
				'border_width'         => array( 'type' => 'object', 'description' => __( 'Item border width: {size, unit}.', 'emcp-tools' ) ),
				'border_color'         => array( 'type' => 'string', 'description' => __( 'Item border color.', 'emcp-tools' ) ),
				// Title typography.
				'title_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable title typography.', 'emcp-tools' ) ),
				'title_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Title font family.', 'emcp-tools' ) ),
				'title_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Title font size: {size, unit}.', 'emcp-tools' ) ),
				'title_typography_font_weight' => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Title font weight.', 'emcp-tools' ) ),
				// Content typography.
				'content_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable content typography.', 'emcp-tools' ) ),
				'content_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Content font family.', 'emcp-tools' ) ),
				'content_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Content font size: {size, unit}.', 'emcp-tools' ) ),
			),
			array( 'tabs' ),
			'toggle',
			array( 'title_html_tag' => 'div' )
		);
	}

	private function register_add_html(): void {
		$this->register_convenience_tool(
			'add-html',
			__( 'Add HTML', 'emcp-tools' ),
			__( 'Adds a custom HTML code widget.', 'emcp-tools' ),
			array(
				'html' => array( 'type' => 'string', 'description' => __( 'Custom HTML/code content.', 'emcp-tools' ) ),
			),
			array( 'html' ),
			'html'
		);
	}

	// =========================================================================
	// Pro convenience tools (only when ELEMENTOR_PRO_VERSION is defined)
	// =========================================================================

	private function register_add_form(): void {
		$this->register_convenience_tool(
			'add-form',
			__( 'Add Form (Pro)', 'emcp-tools' ),
			__( 'Adds an Elementor Pro form. Supports field types (text, email, textarea, url, tel, select, radio, checkbox, number, date, time, upload, acceptance, password, html, hidden, step), submit button styling, submit actions (email, redirect, webhook, mailchimp, drip, activecampaign, getresponse, convertkit, mailerlite, slack), email settings, redirect, and success/error messages. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'form_name'     => array( 'type' => 'string', 'description' => __( 'Form name.', 'emcp-tools' ) ),
				'form_fields'   => array(
					'type'        => 'array',
					'description' => __( 'Array of field definitions.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'field_type'    => array( 'type' => 'string', 'enum' => array( 'text', 'email', 'textarea', 'url', 'tel', 'select', 'radio', 'checkbox', 'number', 'date', 'time', 'upload', 'acceptance', 'password', 'html', 'hidden', 'step' ) ),
							'field_label'   => array( 'type' => 'string' ),
							'placeholder'   => array( 'type' => 'string' ),
							'required'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ) ),
							'width'         => array( 'type' => 'string', 'enum' => array( '100', '80', '75', '66', '50', '33', '25', '20' ) ),
							'field_options' => array( 'type' => 'string' ),
							'field_value'   => array( 'type' => 'string' ),
							'field_html'    => array( 'type' => 'string' ),
							'allow_multiple_upload' => array( 'type' => 'string', 'enum' => array( 'yes', '' ) ),
							'file_sizes'    => array( 'type' => 'integer' ),
							'file_types'    => array( 'type' => 'string' ),
							'acceptance_text' => array( 'type' => 'string' ),
							'checked_by_default' => array( 'type' => 'string', 'enum' => array( 'yes', '' ) ),
						),
					),
				),
				// Submit button.
				'button_text'   => array( 'type' => 'string', 'description' => __( 'Submit button text.', 'emcp-tools' ) ),
				'button_size'   => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Submit button size.', 'emcp-tools' ) ),
				'button_width'  => array( 'type' => 'string', 'enum' => array( '', '100' ), 'description' => __( 'Full-width button. Set to "100" for full width.', 'emcp-tools' ) ),
				'button_align'  => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'stretch' ), 'description' => __( 'Button alignment.', 'emcp-tools' ) ),
				'selected_button_icon' => array( 'type' => 'object', 'description' => __( 'Button icon: {value, library}.', 'emcp-tools' ) ),
				'button_icon_align'    => array( 'type' => 'string', 'enum' => array( 'left', 'right' ), 'description' => __( 'Button icon position.', 'emcp-tools' ) ),
				// Submit actions.
				'submit_actions' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Actions after submit: ["email","redirect","webhook"]. Default: ["email"].', 'emcp-tools' ) ),
				// Email settings.
				'email_to'      => array( 'type' => 'string', 'description' => __( 'Email recipient.', 'emcp-tools' ) ),
				'email_subject' => array( 'type' => 'string', 'description' => __( 'Email subject.', 'emcp-tools' ) ),
				'email_from'    => array( 'type' => 'string', 'description' => __( 'Email from address.', 'emcp-tools' ) ),
				'email_from_name' => array( 'type' => 'string', 'description' => __( 'Email from name.', 'emcp-tools' ) ),
				'email_reply_to'  => array( 'type' => 'string', 'description' => __( 'Reply-to email (use field shortcode like [field id="email"]).', 'emcp-tools' ) ),
				'email_content_type' => array( 'type' => 'string', 'enum' => array( 'html', 'plain' ), 'description' => __( 'Email content type. Default: html.', 'emcp-tools' ) ),
				// Redirect.
				'redirect_to'   => array( 'type' => 'string', 'description' => __( 'Redirect URL after submit (requires "redirect" in submit_actions).', 'emcp-tools' ) ),
				// Webhook.
				'webhooks'      => array( 'type' => 'string', 'description' => __( 'Webhook URL (requires "webhook" in submit_actions).', 'emcp-tools' ) ),
				// Messages.
				'success_message' => array( 'type' => 'string', 'description' => __( 'Success message after submit.', 'emcp-tools' ) ),
				'error_message'   => array( 'type' => 'string', 'description' => __( 'Error message on failure.', 'emcp-tools' ) ),
				'required_field_message' => array( 'type' => 'string', 'description' => __( 'Required field validation message.', 'emcp-tools' ) ),
				// Style.
				'input_size'    => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Input field size.', 'emcp-tools' ) ),
				'show_labels'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show field labels. Default: yes.', 'emcp-tools' ) ),
				'mark_required' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show asterisk on required fields. Default: yes.', 'emcp-tools' ) ),
				// Button colors.
				'button_background_color'       => array( 'type' => 'string', 'description' => __( 'Button background color.', 'emcp-tools' ) ),
				'button_text_color'             => array( 'type' => 'string', 'description' => __( 'Button text color.', 'emcp-tools' ) ),
				'button_hover_background_color' => array( 'type' => 'string', 'description' => __( 'Button hover background color.', 'emcp-tools' ) ),
				'button_hover_color'            => array( 'type' => 'string', 'description' => __( 'Button hover text color.', 'emcp-tools' ) ),
				// Button typography.
				'button_typography_typography'   => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable button typography.', 'emcp-tools' ) ),
				'button_typography_font_family'  => array( 'type' => 'string', 'description' => __( 'Button font family.', 'emcp-tools' ) ),
				'button_typography_font_size'    => array( 'type' => 'object', 'description' => __( 'Button font size: {size, unit}.', 'emcp-tools' ) ),
				'button_typography_font_weight'  => array( 'type' => 'string', 'description' => __( 'Button font weight.', 'emcp-tools' ) ),
			),
			array( 'form_name' ),
			'form',
			array( 'button_text' => 'Send', 'submit_actions' => array( 'email' ) )
		);
	}

	private function register_add_posts_grid(): void {
		$this->register_convenience_tool(
			'add-posts-grid',
			__( 'Add Posts Grid (Pro)', 'emcp-tools' ),
			__( 'Adds an Elementor Pro posts grid widget to display a grid of posts.', 'emcp-tools' ),
			array(
				'posts_post_type' => array( 'type' => 'string', 'enum' => array( 'post', 'page', 'any' ), 'description' => __( 'Post type to query.', 'emcp-tools' ) ),
				'posts_per_page'  => array( 'type' => 'integer', 'description' => __( 'Number of posts to show.', 'emcp-tools' ) ),
				'columns'         => array( 'type' => 'integer', 'description' => __( 'Number of grid columns.', 'emcp-tools' ) ),
				'pagination_type' => array( 'type' => 'string', 'enum' => array( '', 'numbers', 'prev_next', 'numbers_and_prev_next', 'load_more_on_click' ), 'description' => __( 'Pagination type.', 'emcp-tools' ) ),
			),
			array(),
			'posts',
			array( 'posts_post_type' => 'post', 'posts_per_page' => 6, 'columns' => 3 )
		);
	}

	private function register_add_countdown(): void {
		$this->register_convenience_tool(
			'add-countdown',
			__( 'Add Countdown (Pro)', 'emcp-tools' ),
			__( 'Adds a countdown timer. Supports due_date or evergreen mode, custom labels, expire actions (hide/redirect/message), and digit/label colors and typography. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'countdown_type'         => array( 'type' => 'string', 'enum' => array( 'due_date', 'evergreen' ), 'description' => __( 'Countdown mode.', 'emcp-tools' ) ),
				'due_date'               => array( 'type' => 'string', 'description' => __( 'Due date in Y-m-d H:i format.', 'emcp-tools' ) ),
				// Evergreen.
				'evergreen_counter_hours'   => array( 'type' => 'integer', 'description' => __( 'Evergreen hours.', 'emcp-tools' ) ),
				'evergreen_counter_minutes' => array( 'type' => 'integer', 'description' => __( 'Evergreen minutes.', 'emcp-tools' ) ),
				// Visibility.
				'show_days'              => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show days.', 'emcp-tools' ) ),
				'show_hours'             => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show hours.', 'emcp-tools' ) ),
				'show_minutes'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show minutes.', 'emcp-tools' ) ),
				'show_seconds'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show seconds.', 'emcp-tools' ) ),
				// Labels.
				'show_labels'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show labels. Default: yes.', 'emcp-tools' ) ),
				'custom_labels'          => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Use custom label text.', 'emcp-tools' ) ),
				'label_days'             => array( 'type' => 'string', 'description' => __( 'Custom days label.', 'emcp-tools' ) ),
				'label_hours'            => array( 'type' => 'string', 'description' => __( 'Custom hours label.', 'emcp-tools' ) ),
				'label_minutes'          => array( 'type' => 'string', 'description' => __( 'Custom minutes label.', 'emcp-tools' ) ),
				'label_seconds'          => array( 'type' => 'string', 'description' => __( 'Custom seconds label.', 'emcp-tools' ) ),
				// Expire actions.
				'expire_actions'         => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Actions on expiry: ["hide","redirect","message"].', 'emcp-tools' ) ),
				'message_after_expire'   => array( 'type' => 'string', 'description' => __( 'Message to show after expire.', 'emcp-tools' ) ),
				'expire_redirect_url'    => array( 'type' => 'string', 'description' => __( 'Redirect URL after expire.', 'emcp-tools' ) ),
				// Style - Digits.
				'digits_color'           => array( 'type' => 'string', 'description' => __( 'Digit text color.', 'emcp-tools' ) ),
				'digits_background_color' => array( 'type' => 'string', 'description' => __( 'Digit background color.', 'emcp-tools' ) ),
				// Style - Labels.
				'label_color'            => array( 'type' => 'string', 'description' => __( 'Label text color.', 'emcp-tools' ) ),
				// Typography.
				'digits_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for digit typography.', 'emcp-tools' ) ),
				'digits_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Digit font family.', 'emcp-tools' ) ),
				'digits_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Digit font size: {size, unit}.', 'emcp-tools' ) ),
				'label_typography_typography'   => array( 'type' => 'string', 'description' => __( 'Set to "custom" for label typography.', 'emcp-tools' ) ),
				'label_typography_font_family'  => array( 'type' => 'string', 'description' => __( 'Label font family.', 'emcp-tools' ) ),
				'label_typography_font_size'    => array( 'type' => 'object', 'description' => __( 'Label font size: {size, unit}.', 'emcp-tools' ) ),
			),
			array(),
			'countdown',
			array(
				'countdown_type' => 'due_date',
				'show_days'      => 'yes',
				'show_hours'     => 'yes',
				'show_minutes'   => 'yes',
				'show_seconds'   => 'yes',
				'show_labels'    => 'yes',
			)
		);
	}

	private function register_add_price_table(): void {
		$this->register_convenience_tool(
			'add-price-table',
			__( 'Add Price Table (Pro)', 'emcp-tools' ),
			__( 'Adds a pricing table. Supports 16 currency symbols, sale pricing, ribbon, footer info, button CSS ID, feature icons, and style controls (header/pricing/features/footer/button/ribbon colors and typography). Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'heading'                => array( 'type' => 'string', 'description' => __( 'Plan name/heading.', 'emcp-tools' ) ),
				'sub_heading'            => array( 'type' => 'string', 'description' => __( 'Sub-heading text.', 'emcp-tools' ) ),
				'currency_symbol'        => array( 'type' => 'string', 'enum' => array( 'dollar', 'euro', 'baht', 'franc', 'krona', 'lira', 'peseta', 'peso', 'pound', 'real', 'ruble', 'rupee', 'indian_rupee', 'shekel', 'won', 'yen', 'custom' ), 'description' => __( 'Currency symbol preset.', 'emcp-tools' ) ),
				'currency_symbol_custom' => array( 'type' => 'string', 'description' => __( 'Custom currency symbol (when currency_symbol=custom).', 'emcp-tools' ) ),
				'price'                  => array( 'type' => 'string', 'description' => __( 'Price amount.', 'emcp-tools' ) ),
				'currency_format'        => array( 'type' => 'string', 'enum' => array( '', ',', '.' ), 'description' => __( 'Price format: comma or dot separator.', 'emcp-tools' ) ),
				'period'                 => array( 'type' => 'string', 'description' => __( 'Billing period (e.g. "/month").', 'emcp-tools' ) ),
				// Sale.
				'sale'                   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable sale pricing.', 'emcp-tools' ) ),
				'original_price'         => array( 'type' => 'string', 'description' => __( 'Original price (shown crossed out when sale=yes).', 'emcp-tools' ) ),
				// Features.
				'features_list'          => array(
					'type'        => 'array',
					'description' => __( 'Feature list. Each item: {item_text, selected_item_icon, item_icon_color}.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'item_text'          => array( 'type' => 'string' ),
							'selected_item_icon' => array( 'type' => 'object' ),
							'item_icon_color'    => array( 'type' => 'string' ),
						),
					),
				),
				// Button.
				'button_text'            => array( 'type' => 'string', 'description' => __( 'CTA button text.', 'emcp-tools' ) ),
				'link'                   => array( 'type' => 'object', 'description' => __( 'Button link: {url, is_external, nofollow}.', 'emcp-tools' ) ),
				'button_css_id'          => array( 'type' => 'string', 'description' => __( 'Button CSS ID for tracking.', 'emcp-tools' ) ),
				'button_size'            => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size.', 'emcp-tools' ) ),
				// Footer.
				'footer_additional_info' => array( 'type' => 'string', 'description' => __( 'Footer text below button (e.g. "30-day money back").', 'emcp-tools' ) ),
				// Ribbon.
				'show_ribbon'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show ribbon/badge.', 'emcp-tools' ) ),
				'ribbon_title'           => array( 'type' => 'string', 'description' => __( 'Ribbon text (e.g. "Popular", "Best Value").', 'emcp-tools' ) ),
				'ribbon_horizontal_position' => array( 'type' => 'string', 'enum' => array( 'left', 'right' ), 'description' => __( 'Ribbon position.', 'emcp-tools' ) ),
				// Style - Header.
				'header_bg_color'        => array( 'type' => 'string', 'description' => __( 'Header background color.', 'emcp-tools' ) ),
				'heading_color'          => array( 'type' => 'string', 'description' => __( 'Heading text color.', 'emcp-tools' ) ),
				'sub_heading_color'      => array( 'type' => 'string', 'description' => __( 'Sub-heading text color.', 'emcp-tools' ) ),
				// Style - Pricing.
				'pricing_element_bg_color' => array( 'type' => 'string', 'description' => __( 'Pricing area background color.', 'emcp-tools' ) ),
				'price_color'            => array( 'type' => 'string', 'description' => __( 'Price text color.', 'emcp-tools' ) ),
				// Style - Button.
				'button_background_color'       => array( 'type' => 'string', 'description' => __( 'Button background color.', 'emcp-tools' ) ),
				'button_text_color'             => array( 'type' => 'string', 'description' => __( 'Button text color.', 'emcp-tools' ) ),
				'button_hover_background_color' => array( 'type' => 'string', 'description' => __( 'Button hover background color.', 'emcp-tools' ) ),
				'button_hover_color'            => array( 'type' => 'string', 'description' => __( 'Button hover text color.', 'emcp-tools' ) ),
				// Style - Ribbon.
				'ribbon_bg_color'        => array( 'type' => 'string', 'description' => __( 'Ribbon background color.', 'emcp-tools' ) ),
				'ribbon_text_color'      => array( 'type' => 'string', 'description' => __( 'Ribbon text color.', 'emcp-tools' ) ),
			),
			array( 'heading', 'price' ),
			'price-table',
			array( 'currency_symbol' => 'dollar', 'button_text' => 'Get Started' )
		);
	}

	private function register_add_flip_box(): void {
		$this->register_convenience_tool(
			'add-flip-box',
			__( 'Add Flip Box (Pro)', 'emcp-tools' ),
			__( 'Adds a flip box with front/back sides. Supports icon/image graphics, flip effects (flip/slide/push/zoom/fade), height, front/back background colors, title/description colors and typography. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'title_text_a'       => array( 'type' => 'string', 'description' => __( 'Front side title.', 'emcp-tools' ) ),
				'description_text_a' => array( 'type' => 'string', 'description' => __( 'Front side description.', 'emcp-tools' ) ),
				'title_text_b'       => array( 'type' => 'string', 'description' => __( 'Back side title.', 'emcp-tools' ) ),
				'description_text_b' => array( 'type' => 'string', 'description' => __( 'Back side description.', 'emcp-tools' ) ),
				'graphic_element'    => array( 'type' => 'string', 'enum' => array( 'none', 'image', 'icon' ), 'description' => __( 'Front graphic type.', 'emcp-tools' ) ),
				'selected_icon'      => array( 'type' => 'object', 'description' => __( 'Front icon: {value, library}.', 'emcp-tools' ) ),
				'image'              => array( 'type' => 'object', 'description' => __( 'Front image: {url, id}.', 'emcp-tools' ) ),
				'graphic_element_b'  => array( 'type' => 'string', 'enum' => array( 'none', 'image', 'icon' ), 'description' => __( 'Back graphic type.', 'emcp-tools' ) ),
				'selected_icon_b'    => array( 'type' => 'object', 'description' => __( 'Back icon: {value, library}.', 'emcp-tools' ) ),
				'button_text'        => array( 'type' => 'string', 'description' => __( 'Back button text.', 'emcp-tools' ) ),
				'link'               => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'emcp-tools' ) ),
				'flip_effect'        => array( 'type' => 'string', 'enum' => array( 'flip', 'slide', 'push', 'zoom-in', 'zoom-out', 'fade' ), 'description' => __( 'Flip animation.', 'emcp-tools' ) ),
				'flip_direction'     => array( 'type' => 'string', 'enum' => array( 'left', 'right', 'up', 'down' ), 'description' => __( 'Flip direction.', 'emcp-tools' ) ),
				'flip_3d'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable 3D depth effect.', 'emcp-tools' ) ),
				// Height.
				'height'             => array( 'type' => 'object', 'description' => __( 'Box height: {size, unit}.', 'emcp-tools' ) ),
				'border_radius'      => array( 'type' => 'object', 'description' => __( 'Border radius: {size, unit}.', 'emcp-tools' ) ),
				// Front style.
				'background_color_a' => array( 'type' => 'string', 'description' => __( 'Front background color.', 'emcp-tools' ) ),
				'title_color_a'      => array( 'type' => 'string', 'description' => __( 'Front title color.', 'emcp-tools' ) ),
				'description_color_a' => array( 'type' => 'string', 'description' => __( 'Front description color.', 'emcp-tools' ) ),
				'icon_color_a'       => array( 'type' => 'string', 'description' => __( 'Front icon color.', 'emcp-tools' ) ),
				// Back style.
				'background_color_b' => array( 'type' => 'string', 'description' => __( 'Back background color.', 'emcp-tools' ) ),
				'title_color_b'      => array( 'type' => 'string', 'description' => __( 'Back title color.', 'emcp-tools' ) ),
				'description_color_b' => array( 'type' => 'string', 'description' => __( 'Back description color.', 'emcp-tools' ) ),
				// Button style.
				'button_background_color' => array( 'type' => 'string', 'description' => __( 'Back button background color.', 'emcp-tools' ) ),
				'button_color'       => array( 'type' => 'string', 'description' => __( 'Back button text color.', 'emcp-tools' ) ),
				'button_size'        => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size.', 'emcp-tools' ) ),
			),
			array( 'title_text_a' ),
			'flip-box',
			array( 'flip_effect' => 'flip', 'flip_direction' => 'left' )
		);
	}

	private function register_add_animated_headline(): void {
		$this->register_convenience_tool(
			'add-animated-headline',
			__( 'Add Animated Headline (Pro)', 'emcp-tools' ),
			__( 'Adds an Elementor Pro animated headline with highlight or rotating text effects.', 'emcp-tools' ),
			array(
				'headline_style'   => array( 'type' => 'string', 'enum' => array( 'highlight', 'rotate' ), 'description' => __( 'Headline animation style.', 'emcp-tools' ) ),
				'animation_type'   => array( 'type' => 'string', 'enum' => array( 'typing', 'clip', 'flip', 'swirl', 'blinds', 'drop-in', 'wave', 'slide', 'slide-down' ), 'description' => __( 'Rotation animation type.', 'emcp-tools' ) ),
				'marker'           => array( 'type' => 'string', 'enum' => array( 'circle', 'curly', 'underline', 'double', 'double_underline', 'underline_zigzag', 'diagonal', 'strikethrough', 'x' ), 'description' => __( 'Highlight marker style.', 'emcp-tools' ) ),
				'before_text'      => array( 'type' => 'string', 'description' => __( 'Text before animated portion.', 'emcp-tools' ) ),
				'highlighted_text' => array( 'type' => 'string', 'description' => __( 'Highlighted text (for highlight style).', 'emcp-tools' ) ),
				'rotating_text'    => array( 'type' => 'string', 'description' => __( 'Line-separated rotating text entries.', 'emcp-tools' ) ),
				'after_text'       => array( 'type' => 'string', 'description' => __( 'Text after animated portion.', 'emcp-tools' ) ),
				'tag'              => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'description' => __( 'HTML heading tag.', 'emcp-tools' ) ),
			),
			array(),
			'animated-headline',
			array( 'headline_style' => 'highlight', 'tag' => 'h3' )
		);
	}

	private function register_add_call_to_action(): void {
		$this->register_convenience_tool(
			'add-call-to-action',
			__( 'Add Call to Action (Pro)', 'emcp-tools' ),
			__( 'Adds a call-to-action widget with title, description, button, and optional graphic/ribbon.', 'emcp-tools' ),
			array(
				'title'           => array( 'type' => 'string', 'description' => __( 'CTA heading text.', 'emcp-tools' ) ),
				'description'     => array( 'type' => 'string', 'description' => __( 'CTA description text.', 'emcp-tools' ) ),
				'button'          => array( 'type' => 'string', 'description' => __( 'Button text. Default: Click Here.', 'emcp-tools' ) ),
				'link'            => array( 'type' => 'object', 'description' => __( 'Button link object with url key.', 'emcp-tools' ) ),
				'graphic_element' => array( 'type' => 'string', 'enum' => array( 'none', 'image', 'icon' ), 'description' => __( 'Graphic type.', 'emcp-tools' ) ),
				'graphic_image'   => array( 'type' => 'object', 'description' => __( 'Image object with url and optional id.', 'emcp-tools' ) ),
				'selected_icon'   => array( 'type' => 'object', 'description' => __( 'Icon object with value and library.', 'emcp-tools' ) ),
				'ribbon_title'    => array( 'type' => 'string', 'description' => __( 'Optional ribbon/badge text.', 'emcp-tools' ) ),
				'title_tag'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: h2.', 'emcp-tools' ) ),
			),
			array( 'title' ),
			'call-to-action',
			array( 'title_tag' => 'h2', 'button' => 'Click Here' )
		);
	}

	private function register_add_slides(): void {
		$this->register_convenience_tool(
			'add-slides',
			__( 'Add Slides (Pro)', 'emcp-tools' ),
			__( 'Adds a full-width slides/slider. Supports heading, description, button per slide, background image/color/overlay, Ken Burns, content animation, height, navigation, autoplay, colors, typography. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'slides'           => array(
					'type'        => 'array',
					'description' => __( 'Array of slide items.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'heading'                 => array( 'type' => 'string' ),
							'description'             => array( 'type' => 'string' ),
							'button_text'             => array( 'type' => 'string' ),
							'link'                    => array( 'type' => 'object' ),
							'background_color'        => array( 'type' => 'string' ),
							'background_image'        => array( 'type' => 'object' ),
							'background_overlay'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ) ),
							'background_overlay_color' => array( 'type' => 'string' ),
							'background_ken_burns'    => array( 'type' => 'string', 'enum' => array( 'yes', '' ) ),
							'zoom_direction'          => array( 'type' => 'string', 'enum' => array( 'in', 'out' ) ),
							'content_animation'       => array( 'type' => 'string', 'description' => __( 'Content entrance animation (e.g. fadeInUp, zoomIn).', 'emcp-tools' ) ),
							'custom_css_class'        => array( 'type' => 'string' ),
							'horizontal_position'     => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ) ),
							'vertical_position'       => array( 'type' => 'string', 'enum' => array( 'top', 'middle', 'bottom' ) ),
							'text_align'              => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ) ),
						),
					),
				),
				// Slider options.
				'navigation'       => array( 'type' => 'string', 'enum' => array( 'both', 'arrows', 'dots', 'none' ), 'description' => __( 'Navigation type.', 'emcp-tools' ) ),
				'autoplay'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay. Default: yes.', 'emcp-tools' ) ),
				'autoplay_speed'   => array( 'type' => 'integer', 'description' => __( 'Autoplay interval in ms. Default: 5000.', 'emcp-tools' ) ),
				'pause_on_hover'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Pause autoplay on hover.', 'emcp-tools' ) ),
				'pause_on_interaction' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Pause autoplay on interaction.', 'emcp-tools' ) ),
				'infinite'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Infinite loop. Default: yes.', 'emcp-tools' ) ),
				'transition'       => array( 'type' => 'string', 'enum' => array( 'slide', 'fade' ), 'description' => __( 'Transition effect.', 'emcp-tools' ) ),
				'transition_speed' => array( 'type' => 'integer', 'description' => __( 'Transition speed in ms.', 'emcp-tools' ) ),
				// Slider layout.
				'slides_height'    => array( 'type' => 'object', 'description' => __( 'Slider height: {size, unit}. Responsive.', 'emcp-tools' ) ),
				'content_max_width' => array( 'type' => 'object', 'description' => __( 'Content max width percentage: {size, unit}.', 'emcp-tools' ) ),
				'slides_padding'   => array( 'type' => 'object', 'description' => __( 'Content padding: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'slides_horizontal_position' => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Default horizontal position.', 'emcp-tools' ) ),
				'slides_vertical_position'   => array( 'type' => 'string', 'enum' => array( 'top', 'middle', 'bottom' ), 'description' => __( 'Default vertical position.', 'emcp-tools' ) ),
				'slides_text_align' => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Default text alignment.', 'emcp-tools' ) ),
				// Style - Heading.
				'heading_spacing'  => array( 'type' => 'object', 'description' => __( 'Heading bottom spacing: {size, unit}.', 'emcp-tools' ) ),
				'heading_color'    => array( 'type' => 'string', 'description' => __( 'Heading text color.', 'emcp-tools' ) ),
				'heading_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for heading typography.', 'emcp-tools' ) ),
				'heading_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Heading font family.', 'emcp-tools' ) ),
				'heading_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Heading font size: {size, unit}.', 'emcp-tools' ) ),
				'heading_typography_font_weight' => array( 'type' => 'string', 'description' => __( 'Heading font weight.', 'emcp-tools' ) ),
				// Style - Description.
				'description_spacing' => array( 'type' => 'object', 'description' => __( 'Description bottom spacing: {size, unit}.', 'emcp-tools' ) ),
				'description_color' => array( 'type' => 'string', 'description' => __( 'Description text color.', 'emcp-tools' ) ),
				'description_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for description typography.', 'emcp-tools' ) ),
				'description_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Description font family.', 'emcp-tools' ) ),
				'description_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Description font size: {size, unit}.', 'emcp-tools' ) ),
				// Style - Button.
				'button_size'      => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size.', 'emcp-tools' ) ),
				'button_color'     => array( 'type' => 'string', 'description' => __( 'Button text color.', 'emcp-tools' ) ),
				'button_background_color' => array( 'type' => 'string', 'description' => __( 'Button background color.', 'emcp-tools' ) ),
				'button_border_width' => array( 'type' => 'integer', 'description' => __( 'Button border width in px.', 'emcp-tools' ) ),
				'button_border_color' => array( 'type' => 'string', 'description' => __( 'Button border color.', 'emcp-tools' ) ),
				'button_border_radius' => array( 'type' => 'object', 'description' => __( 'Button border radius: {size, unit}.', 'emcp-tools' ) ),
				'button_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for button typography.', 'emcp-tools' ) ),
				'button_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Button font family.', 'emcp-tools' ) ),
				'button_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Button font size: {size, unit}.', 'emcp-tools' ) ),
				// Style - Navigation.
				'arrows_size'      => array( 'type' => 'object', 'description' => __( 'Arrow size: {size, unit}.', 'emcp-tools' ) ),
				'arrows_color'     => array( 'type' => 'string', 'description' => __( 'Arrow color.', 'emcp-tools' ) ),
				'dots_size'        => array( 'type' => 'object', 'description' => __( 'Dot size: {size, unit}.', 'emcp-tools' ) ),
				'dots_color'       => array( 'type' => 'string', 'description' => __( 'Dot color.', 'emcp-tools' ) ),
			),
			array( 'slides' ),
			'slides',
			array( 'autoplay' => 'yes', 'autoplay_speed' => 5000, 'infinite' => 'yes' )
		);
	}

	private function register_add_testimonial_carousel(): void {
		$this->register_convenience_tool(
			'add-testimonial-carousel',
			__( 'Add Testimonial Carousel (Pro)', 'emcp-tools' ),
			__( 'Adds a testimonial carousel. Supports skins (default/bubble), layouts, navigation (arrows/dots), slide spacing, background/text/border colors, image size, content gap, and name/title/content typography. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'slides'          => array(
					'type'        => 'array',
					'description' => __( 'Array of testimonial items with content, image, name, and title.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'content' => array( 'type' => 'string' ),
							'image'   => array( 'type' => 'object' ),
							'name'    => array( 'type' => 'string' ),
							'title'   => array( 'type' => 'string' ),
						),
					),
				),
				'skin'            => array( 'type' => 'string', 'enum' => array( 'default', 'bubble' ), 'description' => __( 'Skin variant. Default: default.', 'emcp-tools' ) ),
				'layout'          => array( 'type' => 'string', 'enum' => array( 'image_inline', 'image_stacked', 'image_above', 'image_left', 'image_right' ), 'description' => __( 'Layout mode.', 'emcp-tools' ) ),
				'alignment'       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Content alignment.', 'emcp-tools' ) ),
				'slides_per_view' => array( 'type' => 'string', 'enum' => array( '1', '2', '3', '4' ), 'description' => __( 'Slides visible at once.', 'emcp-tools' ) ),
				'autoplay'        => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay. Default: yes.', 'emcp-tools' ) ),
				'autoplay_speed'  => array( 'type' => 'integer', 'description' => __( 'Autoplay interval in ms.', 'emcp-tools' ) ),
				// Navigation.
				'navigation'      => array( 'type' => 'string', 'enum' => array( 'both', 'arrows', 'dots', 'none' ), 'description' => __( 'Navigation type.', 'emcp-tools' ) ),
				'infinite'        => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Infinite loop.', 'emcp-tools' ) ),
				'speed'           => array( 'type' => 'integer', 'description' => __( 'Transition speed in ms.', 'emcp-tools' ) ),
				// Slide spacing.
				'space_between'   => array( 'type' => 'object', 'description' => __( 'Space between slides: {size, unit}.', 'emcp-tools' ) ),
				// Style - Slide.
				'slide_background_color' => array( 'type' => 'string', 'description' => __( 'Slide background color.', 'emcp-tools' ) ),
				'slide_padding'   => array( 'type' => 'object', 'description' => __( 'Slide padding: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'slide_border_radius' => array( 'type' => 'object', 'description' => __( 'Slide border radius: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'slide_border_border' => array( 'type' => 'string', 'enum' => array( '', 'solid', 'double', 'dotted', 'dashed' ), 'description' => __( 'Slide border style.', 'emcp-tools' ) ),
				'slide_border_width'  => array( 'type' => 'object', 'description' => __( 'Slide border width.', 'emcp-tools' ) ),
				'slide_border_color'  => array( 'type' => 'string', 'description' => __( 'Slide border color.', 'emcp-tools' ) ),
				// Style - Content.
				'content_color'   => array( 'type' => 'string', 'description' => __( 'Content/quote text color.', 'emcp-tools' ) ),
				'name_color'      => array( 'type' => 'string', 'description' => __( 'Author name color.', 'emcp-tools' ) ),
				'title_color'     => array( 'type' => 'string', 'description' => __( 'Author title/role color.', 'emcp-tools' ) ),
				// Style - Image.
				'image_size'      => array( 'type' => 'object', 'description' => __( 'Author image size: {size, unit}.', 'emcp-tools' ) ),
				'image_gap'       => array( 'type' => 'object', 'description' => __( 'Gap between image and text: {size, unit}.', 'emcp-tools' ) ),
				'image_border_radius' => array( 'type' => 'object', 'description' => __( 'Image border radius: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				// Typography.
				'content_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for content typography.', 'emcp-tools' ) ),
				'content_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Content font family.', 'emcp-tools' ) ),
				'content_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Content font size: {size, unit}.', 'emcp-tools' ) ),
				'name_typography_typography'     => array( 'type' => 'string', 'description' => __( 'Set to "custom" for name typography.', 'emcp-tools' ) ),
				'name_typography_font_family'    => array( 'type' => 'string', 'description' => __( 'Name font family.', 'emcp-tools' ) ),
				'name_typography_font_size'      => array( 'type' => 'object', 'description' => __( 'Name font size: {size, unit}.', 'emcp-tools' ) ),
				'name_typography_font_weight'    => array( 'type' => 'string', 'description' => __( 'Name font weight.', 'emcp-tools' ) ),
			),
			array( 'slides' ),
			'testimonial-carousel',
			array( 'skin' => 'default', 'layout' => 'image_inline', 'slides_per_view' => '1', 'autoplay' => 'yes' )
		);
	}

	private function register_add_price_list(): void {
		$this->register_convenience_tool(
			'add-price-list',
			__( 'Add Price List (Pro)', 'emcp-tools' ),
			__( 'Adds a price list widget for menus, services, or product lists with title, price, and description.', 'emcp-tools' ),
			array(
				'price_list' => array(
					'type'        => 'array',
					'description' => __( 'Array of list items with title, price, item_description, image, and link.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'title'            => array( 'type' => 'string' ),
							'price'            => array( 'type' => 'string' ),
							'item_description' => array( 'type' => 'string' ),
							'image'            => array( 'type' => 'object' ),
							'link'             => array( 'type' => 'object' ),
						),
					),
				),
				'title_tag'  => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: span.', 'emcp-tools' ) ),
			),
			array( 'price_list' ),
			'price-list',
			array( 'title_tag' => 'span' )
		);
	}

	private function register_add_gallery(): void {
		$this->register_convenience_tool(
			'add-gallery',
			__( 'Add Gallery (Pro)', 'emcp-tools' ),
			__( 'Adds an advanced gallery. Supports grid/justified/masonry layouts, multiple galleries with filtering, aspect ratio, overlay effects, lightbox, lazy load, image border/radius, and hover opacity/CSS filters. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'gallery'        => array(
					'type'        => 'array',
					'description' => __( 'Array of image objects with id and url.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'  => array( 'type' => 'integer' ),
							'url' => array( 'type' => 'string' ),
						),
					),
				),
				'gallery_layout' => array( 'type' => 'string', 'enum' => array( 'grid', 'justified', 'masonry' ), 'description' => __( 'Gallery layout. Default: grid.', 'emcp-tools' ) ),
				'columns'        => array( 'type' => 'integer', 'description' => __( 'Number of columns. Default: 4. Responsive: columns_tablet, columns_mobile.', 'emcp-tools' ) ),
				'gap'            => array( 'type' => 'object', 'description' => __( 'Gap between items: {size, unit}.', 'emcp-tools' ) ),
				'link_to'        => array( 'type' => 'string', 'enum' => array( 'file', 'custom', 'none' ), 'description' => __( 'Link behavior.', 'emcp-tools' ) ),
				// Multi-gallery / filtering.
				'gallery_type'   => array( 'type' => 'string', 'enum' => array( 'single', 'multiple' ), 'description' => __( 'Single or multiple galleries (with filter bar).', 'emcp-tools' ) ),
				'galleries'      => array(
					'type'        => 'array',
					'description' => __( 'For gallery_type=multiple: array of {gallery_title, gallery (array of images)}.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'gallery_title' => array( 'type' => 'string' ),
							'gallery'       => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						),
					),
				),
				// Layout options.
				'aspect_ratio'   => array( 'type' => 'string', 'enum' => array( '1:1', '3:2', '4:3', '9:16', '16:9', '21:9' ), 'description' => __( 'Image aspect ratio (grid layout).', 'emcp-tools' ) ),
				'ideal_row_height' => array( 'type' => 'object', 'description' => __( 'Ideal row height for justified layout: {size, unit}.', 'emcp-tools' ) ),
				'order_by'       => array( 'type' => 'string', 'enum' => array( '', 'random' ), 'description' => __( 'Image order: default or random.', 'emcp-tools' ) ),
				'lazyload'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Lazy load images.', 'emcp-tools' ) ),
				// Overlay.
				'overlay_background' => array( 'type' => 'string', 'description' => __( 'Overlay background color on hover.', 'emcp-tools' ) ),
				'content_hover_animation' => array( 'type' => 'string', 'description' => __( 'Overlay content hover animation.', 'emcp-tools' ) ),
				// Lightbox.
				'open_lightbox'  => array( 'type' => 'string', 'enum' => array( 'default', 'yes', 'no' ), 'description' => __( 'Open in lightbox.', 'emcp-tools' ) ),
				// Image style.
				'image_border_radius' => array( 'type' => 'object', 'description' => __( 'Image border radius: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'image_border_border' => array( 'type' => 'string', 'enum' => array( '', 'solid', 'double', 'dotted', 'dashed' ), 'description' => __( 'Image border style.', 'emcp-tools' ) ),
				'image_border_width'  => array( 'type' => 'object', 'description' => __( 'Image border width: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'image_border_color'  => array( 'type' => 'string', 'description' => __( 'Image border color.', 'emcp-tools' ) ),
			),
			array( 'gallery' ),
			'gallery',
			array( 'gallery_layout' => 'grid', 'columns' => 4 )
		);
	}

	private function register_add_share_buttons(): void {
		$this->register_convenience_tool(
			'add-share-buttons',
			__( 'Add Share Buttons (Pro)', 'emcp-tools' ),
			__( 'Adds social share buttons for sharing the current page.', 'emcp-tools' ),
			array(
				'share_buttons' => array(
					'type'        => 'array',
					'description' => __( 'Array of share buttons with button (network name) and optional text.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'button' => array( 'type' => 'string', 'description' => __( 'Network: facebook, twitter, linkedin, pinterest, reddit, etc.', 'emcp-tools' ) ),
							'text'   => array( 'type' => 'string' ),
						),
					),
				),
				'view'          => array( 'type' => 'string', 'enum' => array( 'icon-text', 'icon', 'text' ), 'description' => __( 'Display mode. Default: icon-text.', 'emcp-tools' ) ),
				'skin'          => array( 'type' => 'string', 'enum' => array( 'gradient', 'minimal', 'framed', 'boxed', 'flat' ), 'description' => __( 'Button skin/style.', 'emcp-tools' ) ),
				'shape'         => array( 'type' => 'string', 'enum' => array( 'square', 'rounded', 'circle' ), 'description' => __( 'Button shape. Default: square.', 'emcp-tools' ) ),
				'columns'       => array( 'type' => 'integer', 'description' => __( 'Number of columns.', 'emcp-tools' ) ),
			),
			array( 'share_buttons' ),
			'share-buttons',
			array( 'view' => 'icon-text', 'shape' => 'square' )
		);
	}

	private function register_add_table_of_contents(): void {
		$this->register_convenience_tool(
			'add-table-of-contents',
			__( 'Add Table of Contents (Pro)', 'emcp-tools' ),
			__( 'Adds an auto-generated table of contents widget based on page headings.', 'emcp-tools' ),
			array(
				'title'             => array( 'type' => 'string', 'description' => __( 'TOC title. Default: Table of Contents.', 'emcp-tools' ) ),
				'headings_by_tags'  => array( 'type' => 'array', 'items' => array( 'type' => 'string' ), 'description' => __( 'Which heading tags to include (e.g. ["h2", "h3"]).', 'emcp-tools' ) ),
				'marker_view'       => array( 'type' => 'string', 'enum' => array( 'numbers', 'bullets', 'none' ), 'description' => __( 'Marker style. Default: numbers.', 'emcp-tools' ) ),
				'hierarchical_view' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Hierarchical display. Default: yes.', 'emcp-tools' ) ),
			),
			array(),
			'table-of-contents',
			array( 'title' => 'Table of Contents', 'marker_view' => 'numbers', 'hierarchical_view' => 'yes' )
		);
	}

	private function register_add_blockquote(): void {
		$this->register_convenience_tool(
			'add-blockquote',
			__( 'Add Blockquote (Pro)', 'emcp-tools' ),
			__( 'Adds a styled blockquote widget with quote text, author, tweet button, colors, border, typography. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'blockquote_content' => array( 'type' => 'string', 'description' => __( 'Quote/blockquote text.', 'emcp-tools' ) ),
				'author_name'        => array( 'type' => 'string', 'description' => __( 'Author/attribution name.', 'emcp-tools' ) ),
				'blockquote_skin'    => array( 'type' => 'string', 'enum' => array( 'border', 'quotation', 'boxed', 'clean' ), 'description' => __( 'Skin variant. Default: border.', 'emcp-tools' ) ),
				'alignment'          => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Text alignment.', 'emcp-tools' ) ),
				// Tweet button.
				'tweet_button'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show tweet button.', 'emcp-tools' ) ),
				'tweet_button_view'  => array( 'type' => 'string', 'enum' => array( 'icon-text', 'icon', 'text' ), 'description' => __( 'Tweet button display mode.', 'emcp-tools' ) ),
				'tweet_button_skin'  => array( 'type' => 'string', 'enum' => array( 'classic', 'bubble', 'link' ), 'description' => __( 'Tweet button style.', 'emcp-tools' ) ),
				'tweet_button_label' => array( 'type' => 'string', 'description' => __( 'Custom tweet button label.', 'emcp-tools' ) ),
				'url_type'           => array( 'type' => 'string', 'enum' => array( 'current_page', 'custom' ), 'description' => __( 'URL to share: current page or custom.', 'emcp-tools' ) ),
				'url'                => array( 'type' => 'string', 'description' => __( 'Custom URL to share (when url_type=custom).', 'emcp-tools' ) ),
				'user_name'          => array( 'type' => 'string', 'description' => __( 'Twitter @username for "via" attribution.', 'emcp-tools' ) ),
				// Style - Quote.
				'content_text_color' => array( 'type' => 'string', 'description' => __( 'Quote text color.', 'emcp-tools' ) ),
				'content_gap'        => array( 'type' => 'object', 'description' => __( 'Gap between quote and author: {size, unit}.', 'emcp-tools' ) ),
				'content_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for quote typography.', 'emcp-tools' ) ),
				'content_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Quote font family.', 'emcp-tools' ) ),
				'content_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Quote font size: {size, unit}.', 'emcp-tools' ) ),
				// Style - Author.
				'author_text_color'  => array( 'type' => 'string', 'description' => __( 'Author name color.', 'emcp-tools' ) ),
				'author_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for author typography.', 'emcp-tools' ) ),
				'author_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Author font family.', 'emcp-tools' ) ),
				'author_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Author font size: {size, unit}.', 'emcp-tools' ) ),
				// Style - Border/Quotation mark.
				'border_color'       => array( 'type' => 'string', 'description' => __( 'Border color (border skin) or quotation mark color (quotation skin).', 'emcp-tools' ) ),
				'border_width'       => array( 'type' => 'object', 'description' => __( 'Border width: {size, unit}.', 'emcp-tools' ) ),
				'border_gap'         => array( 'type' => 'object', 'description' => __( 'Gap between border and content: {size, unit}.', 'emcp-tools' ) ),
				'quote_size'         => array( 'type' => 'object', 'description' => __( 'Quotation mark size (quotation skin): {size, unit}.', 'emcp-tools' ) ),
				// Style - Box (boxed skin).
				'box_color'          => array( 'type' => 'string', 'description' => __( 'Box background color (boxed skin).', 'emcp-tools' ) ),
				// Tweet button style.
				'button_color'       => array( 'type' => 'string', 'description' => __( 'Tweet button text/icon color.', 'emcp-tools' ) ),
				'button_text_color'  => array( 'type' => 'string', 'description' => __( 'Tweet button background color.', 'emcp-tools' ) ),
			),
			array( 'blockquote_content' ),
			'blockquote',
			array( 'blockquote_skin' => 'border' )
		);
	}

	private function register_add_lottie(): void {
		$this->register_convenience_tool(
			'add-lottie',
			__( 'Add Lottie Animation (Pro)', 'emcp-tools' ),
			__( 'Adds a Lottie animation widget. Supports triggers, loop, speed, renderer, sizing, link, viewport settings, opacity, CSS filters. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'source'              => array( 'type' => 'string', 'enum' => array( 'media_file', 'external_url' ), 'description' => __( 'Source type. Default: external_url.', 'emcp-tools' ) ),
				'source_external_url' => array( 'type' => 'string', 'description' => __( 'External Lottie JSON URL.', 'emcp-tools' ) ),
				'source_json'         => array( 'type' => 'object', 'description' => __( 'Media library file: {url, id}.', 'emcp-tools' ) ),
				// Playback.
				'trigger'             => array( 'type' => 'string', 'enum' => array( 'arriving_to_viewport', 'on_click', 'on_hover', 'bind_to_scroll', 'none' ), 'description' => __( 'Animation trigger. Default: arriving_to_viewport.', 'emcp-tools' ) ),
				'loop'                => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Loop animation. Default: yes.', 'emcp-tools' ) ),
				'number_of_times'     => array( 'type' => 'integer', 'description' => __( 'Loop count (0 = infinite).', 'emcp-tools' ) ),
				'play_speed'          => array( 'type' => 'object', 'description' => __( 'Playback speed: {size, unit}.', 'emcp-tools' ) ),
				'start_point'         => array( 'type' => 'object', 'description' => __( 'Animation start point (0-100): {size, unit}.', 'emcp-tools' ) ),
				'end_point'           => array( 'type' => 'object', 'description' => __( 'Animation end point (0-100): {size, unit}.', 'emcp-tools' ) ),
				'reverse_animation'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Play animation in reverse.', 'emcp-tools' ) ),
				'renderer'            => array( 'type' => 'string', 'enum' => array( 'svg', 'canvas' ), 'description' => __( 'Render method. Default: svg.', 'emcp-tools' ) ),
				'lazyload'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Lazy load animation.', 'emcp-tools' ) ),
				// Link.
				'link_to'             => array( 'type' => 'string', 'enum' => array( 'none', 'custom' ), 'description' => __( 'Link type.', 'emcp-tools' ) ),
				'custom_link'         => array( 'type' => 'object', 'description' => __( 'Link object: {url, is_external, nofollow}.', 'emcp-tools' ) ),
				// Viewport trigger settings.
				'viewport_start'      => array( 'type' => 'string', 'description' => __( 'Viewport offset start (e.g. "bottom").', 'emcp-tools' ) ),
				'viewport_end'        => array( 'type' => 'string', 'description' => __( 'Viewport offset end.', 'emcp-tools' ) ),
				// Caption.
				'caption_source'      => array( 'type' => 'string', 'enum' => array( 'none', 'title', 'caption', 'custom' ), 'description' => __( 'Caption source.', 'emcp-tools' ) ),
				'caption'             => array( 'type' => 'string', 'description' => __( 'Custom caption text.', 'emcp-tools' ) ),
				// Style.
				'align'               => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Alignment.', 'emcp-tools' ) ),
				'width'               => array( 'type' => 'object', 'description' => __( 'Width: {size, unit}.', 'emcp-tools' ) ),
				'opacity'             => array( 'type' => 'object', 'description' => __( 'Opacity (0-1): {size, unit}.', 'emcp-tools' ) ),
				'css_filters_css_filter' => array( 'type' => 'string', 'description' => __( 'Set to "custom" for CSS filters.', 'emcp-tools' ) ),
				'css_filters_blur'    => array( 'type' => 'object', 'description' => __( 'Blur filter: {size, unit}.', 'emcp-tools' ) ),
				'css_filters_brightness' => array( 'type' => 'object', 'description' => __( 'Brightness filter: {size, unit}.', 'emcp-tools' ) ),
				'css_filters_contrast' => array( 'type' => 'object', 'description' => __( 'Contrast filter: {size, unit}.', 'emcp-tools' ) ),
				'css_filters_saturate' => array( 'type' => 'object', 'description' => __( 'Saturate filter: {size, unit}.', 'emcp-tools' ) ),
				'opacity_hover'       => array( 'type' => 'object', 'description' => __( 'Hover opacity: {size, unit}.', 'emcp-tools' ) ),
			),
			array(),
			'lottie',
			array( 'source' => 'external_url', 'trigger' => 'arriving_to_viewport', 'loop' => 'yes', 'renderer' => 'svg' )
		);
	}

	private function register_add_hotspot(): void {
		$this->register_convenience_tool(
			'add-hotspot',
			__( 'Add Hotspot (Pro)', 'emcp-tools' ),
			__( 'Adds an image hotspot widget with clickable/hoverable points. Supports tooltip settings, animations, hotspot sizing/colors, image width. Accepts responsive suffixes and advanced controls.', 'emcp-tools' ),
			array(
				'image'   => array( 'type' => 'object', 'description' => __( 'Background image object with url and optional id.', 'emcp-tools' ) ),
				'hotspot' => array(
					'type'        => 'array',
					'description' => __( 'Array of hotspot items.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'hotspot_label'           => array( 'type' => 'string' ),
							'hotspot_link'            => array( 'type' => 'object', 'description' => __( '{url, is_external, nofollow}.', 'emcp-tools' ) ),
							'hotspot_icon'            => array( 'type' => 'object', 'description' => __( 'Icon: {value, library}.', 'emcp-tools' ) ),
							'hotspot_icon_position'   => array( 'type' => 'string', 'enum' => array( 'before', 'after' ) ),
							'hotspot_horizontal'      => array( 'type' => 'string', 'enum' => array( 'left', 'right' ) ),
							'hotspot_offset_x'        => array( 'type' => 'object', 'description' => __( 'Horizontal offset %: {size, unit}.', 'emcp-tools' ) ),
							'hotspot_vertical'        => array( 'type' => 'string', 'enum' => array( 'top', 'bottom' ) ),
							'hotspot_offset_y'        => array( 'type' => 'object', 'description' => __( 'Vertical offset %: {size, unit}.', 'emcp-tools' ) ),
							'hotspot_tooltip_content' => array( 'type' => 'string' ),
							'hotspot_custom_size'     => array( 'type' => 'string', 'enum' => array( 'yes', '' ) ),
							'hotspot_width'           => array( 'type' => 'object' ),
							'hotspot_height'          => array( 'type' => 'object' ),
						),
					),
				),
				// Image.
				'image_size'          => array( 'type' => 'string', 'description' => __( 'Image size (e.g. full, large, medium).', 'emcp-tools' ) ),
				'image_custom_dimension' => array( 'type' => 'object', 'description' => __( 'Custom image dimensions: {width, height}.', 'emcp-tools' ) ),
				// Tooltip settings.
				'tooltip_trigger'     => array( 'type' => 'string', 'enum' => array( 'mouseenter', 'click', 'none' ), 'description' => __( 'Tooltip trigger event. Default: mouseenter.', 'emcp-tools' ) ),
				'tooltip_position'    => array( 'type' => 'string', 'enum' => array( 'top', 'bottom', 'left', 'right' ), 'description' => __( 'Default tooltip position.', 'emcp-tools' ) ),
				'tooltip_animation'   => array( 'type' => 'string', 'enum' => array( 'e--animation-fadeIn', 'e--animation-zoomIn', 'e--animation-slideInUp', 'e--animation-slideInDown', 'e--animation-slideInLeft', 'e--animation-slideInRight' ), 'description' => __( 'Tooltip entrance animation.', 'emcp-tools' ) ),
				'tooltip_animation_duration' => array( 'type' => 'object', 'description' => __( 'Tooltip animation duration: {size, unit}.', 'emcp-tools' ) ),
				// Hotspot animation.
				'hotspot_animation'   => array( 'type' => 'string', 'enum' => array( 'none', 'soft-beat', 'expand', 'shadow' ), 'description' => __( 'Hotspot point animation.', 'emcp-tools' ) ),
				'hotspot_sequenced_animation' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Staggered animation sequence.', 'emcp-tools' ) ),
				// Style - Image.
				'image_width'         => array( 'type' => 'object', 'description' => __( 'Image width: {size, unit}.', 'emcp-tools' ) ),
				'image_opacity'       => array( 'type' => 'object', 'description' => __( 'Image opacity (0-1): {size, unit}.', 'emcp-tools' ) ),
				'image_border_radius' => array( 'type' => 'object', 'description' => __( 'Image border radius: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				// Style - Hotspot.
				'hotspot_color'       => array( 'type' => 'string', 'description' => __( 'Hotspot label/icon color.', 'emcp-tools' ) ),
				'hotspot_background_color' => array( 'type' => 'string', 'description' => __( 'Hotspot background color.', 'emcp-tools' ) ),
				'hotspot_size'        => array( 'type' => 'object', 'description' => __( 'Hotspot point size: {size, unit}.', 'emcp-tools' ) ),
				'hotspot_padding'     => array( 'type' => 'object', 'description' => __( 'Hotspot padding: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'hotspot_border_radius' => array( 'type' => 'object', 'description' => __( 'Hotspot border radius: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'hotspot_box_shadow_box_shadow_type' => array( 'type' => 'string', 'description' => __( 'Set to "yes" for hotspot box shadow.', 'emcp-tools' ) ),
				'hotspot_box_shadow_box_shadow' => array( 'type' => 'object', 'description' => __( 'Hotspot box shadow: {horizontal, vertical, blur, spread, color}.', 'emcp-tools' ) ),
				// Style - Tooltip.
				'tooltip_text_color'  => array( 'type' => 'string', 'description' => __( 'Tooltip text color.', 'emcp-tools' ) ),
				'tooltip_background_color' => array( 'type' => 'string', 'description' => __( 'Tooltip background color.', 'emcp-tools' ) ),
				'tooltip_border_radius' => array( 'type' => 'object', 'description' => __( 'Tooltip border radius: {size, unit}.', 'emcp-tools' ) ),
				'tooltip_padding'     => array( 'type' => 'object', 'description' => __( 'Tooltip padding: {top, right, bottom, left, unit, isLinked}.', 'emcp-tools' ) ),
				'tooltip_width'       => array( 'type' => 'object', 'description' => __( 'Tooltip width: {size, unit}.', 'emcp-tools' ) ),
				'tooltip_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for tooltip typography.', 'emcp-tools' ) ),
				'tooltip_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Tooltip font family.', 'emcp-tools' ) ),
				'tooltip_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Tooltip font size: {size, unit}.', 'emcp-tools' ) ),
			),
			array( 'image', 'hotspot' ),
			'hotspot',
			array( 'tooltip_trigger' => 'mouseenter', 'tooltip_position' => 'top' )
		);
	}

	// ── Phase 5: Missing Widget Convenience Tools ─────────────────────

	private function register_add_menu_anchor(): void {
		$this->register_convenience_tool(
			'add-menu-anchor',
			__( 'Add Menu Anchor', 'emcp-tools' ),
			__( 'Adds a menu anchor for one-page navigation.', 'emcp-tools' ),
			array(
				'anchor' => array( 'type' => 'string', 'description' => __( 'The anchor ID (used in menu links as #id).', 'emcp-tools' ) ),
			),
			array( 'anchor' ),
			'menu-anchor',
			array()
		);
	}

	private function register_add_shortcode(): void {
		$this->register_convenience_tool(
			'add-shortcode',
			__( 'Add Shortcode', 'emcp-tools' ),
			__( 'Adds a WordPress shortcode widget.', 'emcp-tools' ),
			array(
				'shortcode' => array( 'type' => 'string', 'description' => __( 'The shortcode to render, e.g. [contact-form-7 id="123"].', 'emcp-tools' ) ),
			),
			array( 'shortcode' ),
			'shortcode',
			array()
		);
	}

	private function register_add_rating(): void {
		$this->register_convenience_tool(
			'add-rating',
			__( 'Add Rating', 'emcp-tools' ),
			__( 'Adds a star/icon rating widget.', 'emcp-tools' ),
			array(
				'rating_scale'        => array( 'type' => 'object', 'description' => __( 'Rating scale: { "size": 5, "unit": "px" }. Default 5.', 'emcp-tools' ) ),
				'rating_value'        => array( 'type' => 'number', 'description' => __( 'Rating value (e.g. 4.5).', 'emcp-tools' ) ),
				'rating_icon'         => array( 'type' => 'object', 'description' => __( 'Icon object, e.g. { "value": "eicon-star", "library": "eicons" }.', 'emcp-tools' ) ),
				'icon_alignment'      => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end' ), 'description' => __( 'Icon alignment.', 'emcp-tools' ) ),
				'icon_size'           => array( 'type' => 'object', 'description' => __( 'Icon size: { "size": 24, "unit": "px" }.', 'emcp-tools' ) ),
				'icon_gap'            => array( 'type' => 'object', 'description' => __( 'Space between icons: { "size": 5, "unit": "px" }.', 'emcp-tools' ) ),
				'icon_color'          => array( 'type' => 'string', 'description' => __( 'Marked icon color (hex).', 'emcp-tools' ) ),
				'icon_unmarked_color' => array( 'type' => 'string', 'description' => __( 'Unmarked icon color (hex).', 'emcp-tools' ) ),
			),
			array(),
			'rating',
			array( 'rating_value' => 5 )
		);
	}

	private function register_add_text_path(): void {
		$this->register_convenience_tool(
			'add-text-path',
			__( 'Add Text Path', 'emcp-tools' ),
			__( 'Adds curved/path text widget.', 'emcp-tools' ),
			array(
				'text'                => array( 'type' => 'string', 'description' => __( 'The text content.', 'emcp-tools' ) ),
				'path'                => array( 'type' => 'string', 'enum' => array( 'wave', 'arc', 'circle', 'line', 'oval', 'spiral', 'custom' ), 'description' => __( 'Path shape type. Default: wave.', 'emcp-tools' ) ),
				'custom_path'         => array( 'type' => 'object', 'description' => __( 'Custom SVG path object (when path=custom).', 'emcp-tools' ) ),
				'link'                => array( 'type' => 'object', 'description' => __( 'Link object: { "url": "...", "is_external": true }.', 'emcp-tools' ) ),
				'align'               => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Text alignment.', 'emcp-tools' ) ),
				'text_path_direction' => array( 'type' => 'string', 'enum' => array( '', 'rtl', 'ltr' ), 'description' => __( 'Text direction.', 'emcp-tools' ) ),
				'show_path'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show the SVG path line.', 'emcp-tools' ) ),
				'size'                => array( 'type' => 'object', 'description' => __( 'Path size: { "size": 500, "unit": "px" }.', 'emcp-tools' ) ),
				'rotation'            => array( 'type' => 'object', 'description' => __( 'Rotation: { "size": 0, "unit": "px" }.', 'emcp-tools' ) ),
				'start_point'         => array( 'type' => 'object', 'description' => __( 'Starting point (%): { "size": 0, "unit": "px" }.', 'emcp-tools' ) ),
				'text_color_normal'   => array( 'type' => 'string', 'description' => __( 'Text color (hex).', 'emcp-tools' ) ),
				'text_color_hover'    => array( 'type' => 'string', 'description' => __( 'Text hover color (hex).', 'emcp-tools' ) ),
				'stroke_color_normal' => array( 'type' => 'string', 'description' => __( 'Path stroke color (hex).', 'emcp-tools' ) ),
				'stroke_width_normal' => array( 'type' => 'object', 'description' => __( 'Path stroke width: { "size": 1, "unit": "px" }.', 'emcp-tools' ) ),
				'text_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "yes" for custom typography.', 'emcp-tools' ) ),
				'text_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Font family name.', 'emcp-tools' ) ),
				'text_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Font size: { "size": 20, "unit": "px" }.', 'emcp-tools' ) ),
				'text_typography_font_weight' => array( 'type' => 'string', 'description' => __( 'Font weight (100-900, normal, bold).', 'emcp-tools' ) ),
			),
			array(),
			'text-path',
			array( 'text' => 'Add Your Curvy Text Here', 'path' => 'wave' )
		);
	}

	private function register_add_nav_menu(): void {
		$this->register_convenience_tool(
			'add-nav-menu',
			__( 'Add Navigation Menu', 'emcp-tools' ),
			__( 'Adds a WordPress navigation menu widget (Pro).', 'emcp-tools' ),
			array(
				'menu_name'     => array( 'type' => 'string', 'description' => __( 'Menu name (as registered in WP Menus).', 'emcp-tools' ) ),
				'layout'        => array( 'type' => 'string', 'enum' => array( 'horizontal', 'vertical', 'dropdown' ), 'description' => __( 'Menu layout. Default: horizontal.', 'emcp-tools' ) ),
				'align_items'   => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'justify' ), 'description' => __( 'Menu alignment.', 'emcp-tools' ) ),
				'pointer'       => array( 'type' => 'string', 'enum' => array( 'none', 'underline', 'overline', 'double-line', 'framed', 'background', 'text' ), 'description' => __( 'Hover pointer style. Default: underline.', 'emcp-tools' ) ),
				'animation_line' => array( 'type' => 'string', 'enum' => array( 'fade', 'slide', 'grow', 'drop-in', 'drop-out', 'none' ), 'description' => __( 'Line pointer animation.', 'emcp-tools' ) ),
				'dropdown'      => array( 'type' => 'string', 'enum' => array( 'mobile', 'tablet', 'none' ), 'description' => __( 'Breakpoint for dropdown toggle. Default: tablet.', 'emcp-tools' ) ),
				'full_width'    => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Full width dropdown.', 'emcp-tools' ) ),
				'text_align'    => array( 'type' => 'string', 'enum' => array( 'aside', 'center' ), 'description' => __( 'Dropdown text alignment.', 'emcp-tools' ) ),
				'toggle'        => array( 'type' => 'string', 'enum' => array( '', 'burger' ), 'description' => __( 'Toggle button type.', 'emcp-tools' ) ),
				'toggle_align'  => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Toggle button alignment.', 'emcp-tools' ) ),
				'color_menu_item'                => array( 'type' => 'string', 'description' => __( 'Menu text color (hex).', 'emcp-tools' ) ),
				'color_menu_item_hover'          => array( 'type' => 'string', 'description' => __( 'Menu hover text color (hex).', 'emcp-tools' ) ),
				'pointer_color_menu_item_hover'  => array( 'type' => 'string', 'description' => __( 'Pointer hover color (hex).', 'emcp-tools' ) ),
				'color_menu_item_active'         => array( 'type' => 'string', 'description' => __( 'Active item text color (hex).', 'emcp-tools' ) ),
				'pointer_color_menu_item_active' => array( 'type' => 'string', 'description' => __( 'Active item pointer color (hex).', 'emcp-tools' ) ),
				'padding_horizontal_menu_item'   => array( 'type' => 'object', 'description' => __( 'Horizontal padding: { "size": 20, "unit": "px" }.', 'emcp-tools' ) ),
				'padding_vertical_menu_item'     => array( 'type' => 'object', 'description' => __( 'Vertical padding: { "size": 15, "unit": "px" }.', 'emcp-tools' ) ),
				'menu_space_between'             => array( 'type' => 'object', 'description' => __( 'Space between items: { "size": 10, "unit": "px" }.', 'emcp-tools' ) ),
				'menu_typography_typography'      => array( 'type' => 'string', 'description' => __( 'Set to "yes" for custom typography.', 'emcp-tools' ) ),
				'menu_typography_font_family'     => array( 'type' => 'string', 'description' => __( 'Font family name.', 'emcp-tools' ) ),
				'menu_typography_font_size'       => array( 'type' => 'object', 'description' => __( 'Font size: { "size": 16, "unit": "px" }.', 'emcp-tools' ) ),
				'menu_typography_font_weight'     => array( 'type' => 'string', 'description' => __( 'Font weight.', 'emcp-tools' ) ),
			),
			array(),
			'nav-menu',
			array( 'layout' => 'horizontal', 'pointer' => 'underline' )
		);
	}

	private function register_add_loop_grid(): void {
		$this->register_convenience_tool(
			'add-loop-grid',
			__( 'Add Loop Grid', 'emcp-tools' ),
			__( 'Adds a loop grid widget that displays posts/pages/CPTs using a loop template (Pro).', 'emcp-tools' ),
			array(
				'_skin'                => array( 'type' => 'string', 'enum' => array( 'post', 'post_taxonomy' ), 'description' => __( 'Template type. Default: post.', 'emcp-tools' ) ),
				'template_id'          => array( 'type' => 'string', 'description' => __( 'Loop template ID.', 'emcp-tools' ) ),
				'columns'              => array( 'type' => 'number', 'description' => __( 'Number of columns. Default: 3.', 'emcp-tools' ) ),
				'columns_tablet'       => array( 'type' => 'number', 'description' => __( 'Columns on tablet. Default: 2.', 'emcp-tools' ) ),
				'columns_mobile'       => array( 'type' => 'number', 'description' => __( 'Columns on mobile. Default: 1.', 'emcp-tools' ) ),
				'posts_per_page'       => array( 'type' => 'number', 'description' => __( 'Items per page. Default: 6.', 'emcp-tools' ) ),
				'masonry'              => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable masonry layout.', 'emcp-tools' ) ),
				'equal_height'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Equal height items.', 'emcp-tools' ) ),
				'post_query_post_type' => array( 'type' => 'string', 'enum' => array( 'post', 'page', 'by_id', 'current_query', 'related' ), 'description' => __( 'Query source. Default: post.', 'emcp-tools' ) ),
				'post_query_include'   => array( 'type' => 'string', 'enum' => array( 'terms', 'authors' ), 'description' => __( 'Include by terms or authors.', 'emcp-tools' ) ),
				'post_query_exclude'   => array( 'type' => 'string', 'enum' => array( 'current_post', 'manual_selection', 'terms', 'authors' ), 'description' => __( 'Exclude criteria.', 'emcp-tools' ) ),
				'post_query_orderby'   => array( 'type' => 'string', 'enum' => array( 'post_date', 'post_title', 'menu_order', 'modified', 'comment_count', 'rand' ), 'description' => __( 'Order by field. Default: post_date.', 'emcp-tools' ) ),
				'post_query_order'     => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'description' => __( 'Sort order. Default: desc.', 'emcp-tools' ) ),
				'post_query_offset'    => array( 'type' => 'number', 'description' => __( 'Query offset.', 'emcp-tools' ) ),
			),
			array(),
			'loop-grid',
			array( 'columns' => 3, 'posts_per_page' => 6 )
		);
	}

	private function register_add_loop_carousel(): void {
		$this->register_convenience_tool(
			'add-loop-carousel',
			__( 'Add Loop Carousel', 'emcp-tools' ),
			__( 'Adds a loop carousel widget that displays posts in a carousel using a loop template (Pro).', 'emcp-tools' ),
			array(
				'_skin'                => array( 'type' => 'string', 'enum' => array( 'post', 'post_taxonomy' ), 'description' => __( 'Template type. Default: post.', 'emcp-tools' ) ),
				'template_id'          => array( 'type' => 'string', 'description' => __( 'Loop template ID.', 'emcp-tools' ) ),
				'posts_per_page'       => array( 'type' => 'number', 'description' => __( 'Number of slides. Default: 6.', 'emcp-tools' ) ),
				'slides_to_show'       => array( 'type' => 'string', 'enum' => array( '', '1', '2', '3', '4', '5', '6', '7', '8' ), 'description' => __( 'Slides on display. Default: 3.', 'emcp-tools' ) ),
				'slides_to_show_tablet' => array( 'type' => 'string', 'description' => __( 'Slides on tablet. Default: 2.', 'emcp-tools' ) ),
				'slides_to_show_mobile' => array( 'type' => 'string', 'description' => __( 'Slides on mobile. Default: 1.', 'emcp-tools' ) ),
				'slides_to_scroll'     => array( 'type' => 'string', 'description' => __( 'Slides to scroll per step. Default: 1.', 'emcp-tools' ) ),
				'equal_height'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Equal height slides. Default: yes.', 'emcp-tools' ) ),
				'post_query_post_type' => array( 'type' => 'string', 'enum' => array( 'post', 'page', 'by_id', 'current_query', 'related' ), 'description' => __( 'Query source. Default: post.', 'emcp-tools' ) ),
				'post_query_orderby'   => array( 'type' => 'string', 'enum' => array( 'post_date', 'post_title', 'menu_order', 'modified', 'comment_count', 'rand' ), 'description' => __( 'Order by. Default: post_date.', 'emcp-tools' ) ),
				'post_query_order'     => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'description' => __( 'Sort order. Default: desc.', 'emcp-tools' ) ),
			),
			array(),
			'loop-carousel',
			array( 'posts_per_page' => 6, 'slides_to_show' => '3', 'equal_height' => 'yes' )
		);
	}

	private function register_add_media_carousel(): void {
		$this->register_convenience_tool(
			'add-media-carousel',
			__( 'Add Media Carousel', 'emcp-tools' ),
			__( 'Adds a media carousel widget for images/video with multiple skins (Pro).', 'emcp-tools' ),
			array(
				'skin'           => array( 'type' => 'string', 'enum' => array( 'carousel', 'slideshow', 'coverflow' ), 'description' => __( 'Carousel skin. Default: carousel.', 'emcp-tools' ) ),
				'slides'         => array(
					'type'        => 'array',
					'description' => __( 'Array of slide items with image/video.', 'emcp-tools' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'image' => array( 'type' => 'object', 'description' => __( '{ "url": "...", "id": 0 }', 'emcp-tools' ) ),
							'type'  => array( 'type' => 'string', 'description' => __( 'Slide type: image or video.', 'emcp-tools' ) ),
						),
					),
				),
				'effect'           => array( 'type' => 'string', 'enum' => array( 'slide', 'fade', 'cube' ), 'description' => __( 'Transition effect. Default: slide.', 'emcp-tools' ) ),
				'slides_per_view'  => array( 'type' => 'string', 'description' => __( 'Slides visible at once.', 'emcp-tools' ) ),
				'slides_to_scroll' => array( 'type' => 'string', 'description' => __( 'Slides to scroll per step.', 'emcp-tools' ) ),
				'height'           => array( 'type' => 'object', 'description' => __( 'Carousel height: { "size": 400, "unit": "px" }.', 'emcp-tools' ) ),
				'width'            => array( 'type' => 'object', 'description' => __( 'Carousel width: { "size": 100, "unit": "%" }.', 'emcp-tools' ) ),
				'show_arrows'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show navigation arrows. Default: yes.', 'emcp-tools' ) ),
				'pagination'       => array( 'type' => 'string', 'enum' => array( '', 'bullets', 'fraction', 'progressbar' ), 'description' => __( 'Pagination type. Default: bullets.', 'emcp-tools' ) ),
				'speed'            => array( 'type' => 'number', 'description' => __( 'Transition duration ms. Default: 500.', 'emcp-tools' ) ),
				'autoplay'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable autoplay. Default: yes.', 'emcp-tools' ) ),
				'autoplay_speed'   => array( 'type' => 'number', 'description' => __( 'Autoplay speed ms. Default: 5000.', 'emcp-tools' ) ),
				'loop'             => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Infinite loop. Default: yes.', 'emcp-tools' ) ),
				'pause_on_hover'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Pause on hover. Default: yes.', 'emcp-tools' ) ),
				'overlay'          => array( 'type' => 'string', 'enum' => array( '', 'text', 'icon' ), 'description' => __( 'Overlay type on hover.', 'emcp-tools' ) ),
				'caption'          => array( 'type' => 'string', 'enum' => array( 'title', 'caption', 'description' ), 'description' => __( 'Caption source. Default: title.', 'emcp-tools' ) ),
				'image_size_size'  => array( 'type' => 'string', 'enum' => array( 'thumbnail', 'medium', 'medium_large', 'large', 'full', 'custom' ), 'description' => __( 'Image resolution. Default: full.', 'emcp-tools' ) ),
				'image_fit'        => array( 'type' => 'string', 'enum' => array( '', 'contain', 'auto' ), 'description' => __( 'Image fit mode.', 'emcp-tools' ) ),
				'centered_slides'  => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Center the active slide.', 'emcp-tools' ) ),
				'slide_background_color' => array( 'type' => 'string', 'description' => __( 'Slide background color (hex).', 'emcp-tools' ) ),
				'slide_border_radius'    => array( 'type' => 'object', 'description' => __( 'Slide border radius.', 'emcp-tools' ) ),
				'arrows_size'      => array( 'type' => 'object', 'description' => __( 'Arrow size: { "size": 20, "unit": "px" }.', 'emcp-tools' ) ),
				'arrows_color'     => array( 'type' => 'string', 'description' => __( 'Arrow color (hex).', 'emcp-tools' ) ),
				'space_between'    => array( 'type' => 'object', 'description' => __( 'Space between slides: { "size": 10, "unit": "px" }.', 'emcp-tools' ) ),
			),
			array(),
			'media-carousel',
			array( 'skin' => 'carousel', 'autoplay' => 'yes', 'loop' => 'yes' )
		);
	}

	private function register_add_nested_tabs(): void {
		$this->register_convenience_tool(
			'add-nested-tabs',
			__( 'Add Nested Tabs', 'emcp-tools' ),
			__( 'Adds a modern nested tabs widget where each tab content is a container (Pro). Tab content can be populated by adding child elements to the tab containers after creation.', 'emcp-tools' ),
			array(
				'tabs_direction'          => array( 'type' => 'string', 'enum' => array( 'block-start', 'block-end', 'inline-end', 'inline-start' ), 'description' => __( 'Tab direction. block-start=top, block-end=bottom, inline-start=left, inline-end=right.', 'emcp-tools' ) ),
				'tabs_justify_horizontal' => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'stretch' ), 'description' => __( 'Horizontal tab justify.', 'emcp-tools' ) ),
				'tabs_justify_vertical'   => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'stretch' ), 'description' => __( 'Vertical tab justify.', 'emcp-tools' ) ),
				'tabs_width'              => array( 'type' => 'object', 'description' => __( 'Tab width: { "size": 200, "unit": "px" }.', 'emcp-tools' ) ),
				'title_alignment'         => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end' ), 'description' => __( 'Title alignment within tab.', 'emcp-tools' ) ),
				'horizontal_scroll'       => array( 'type' => 'string', 'enum' => array( 'disable', 'enable' ), 'description' => __( 'Enable horizontal scroll for tabs. Default: disable.', 'emcp-tools' ) ),
				'breakpoint_selector'     => array( 'type' => 'string', 'enum' => array( 'none', 'mobile', 'tablet' ), 'description' => __( 'Breakpoint for accordion mode. Default: mobile.', 'emcp-tools' ) ),
				'tabs_title_space_between' => array( 'type' => 'object', 'description' => __( 'Gap between tabs: { "size": 0, "unit": "px" }.', 'emcp-tools' ) ),
				'tabs_title_spacing'       => array( 'type' => 'object', 'description' => __( 'Distance from content: { "size": 0, "unit": "px" }.', 'emcp-tools' ) ),
				'tabs_title_background_color_background' => array( 'type' => 'string', 'enum' => array( 'classic', 'gradient' ), 'description' => __( 'Tab background type.', 'emcp-tools' ) ),
				'tabs_title_background_color_color'      => array( 'type' => 'string', 'description' => __( 'Tab background color (hex).', 'emcp-tools' ) ),
				'tabs_title_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "yes" for custom tab typography.', 'emcp-tools' ) ),
				'tabs_title_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Tab font family.', 'emcp-tools' ) ),
				'tabs_title_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Tab font size: { "size": 16, "unit": "px" }.', 'emcp-tools' ) ),
				'tabs_title_typography_font_weight' => array( 'type' => 'string', 'description' => __( 'Tab font weight.', 'emcp-tools' ) ),
			),
			array(),
			'nested-tabs',
			array()
		);
	}

	private function register_add_nested_accordion(): void {
		$this->register_convenience_tool(
			'add-nested-accordion',
			__( 'Add Nested Accordion', 'emcp-tools' ),
			__( 'Adds a modern nested accordion widget where each item content is a container (Pro). Item content can be populated by adding child elements to the item containers after creation.', 'emcp-tools' ),
			array(
				'accordion_item_title_position_horizontal' => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'stretch' ), 'description' => __( 'Title position.', 'emcp-tools' ) ),
				'accordion_item_title_icon_position'       => array( 'type' => 'string', 'enum' => array( 'start', 'end' ), 'description' => __( 'Icon position. Default: end.', 'emcp-tools' ) ),
				'accordion_item_title_icon'                => array( 'type' => 'object', 'description' => __( 'Expand icon object.', 'emcp-tools' ) ),
				'accordion_item_title_icon_active'         => array( 'type' => 'object', 'description' => __( 'Collapse icon object.', 'emcp-tools' ) ),
				'title_tag'             => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: div.', 'emcp-tools' ) ),
				'faq_schema'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable FAQ Schema markup.', 'emcp-tools' ) ),
				'default_state'         => array( 'type' => 'string', 'enum' => array( 'expanded', 'all_collapsed' ), 'description' => __( 'Default state. Default: expanded (first item open).', 'emcp-tools' ) ),
				'max_items_expended'    => array( 'type' => 'string', 'enum' => array( 'one', 'multiple' ), 'description' => __( 'Max items expanded at once. Default: one.', 'emcp-tools' ) ),
				'n_accordion_animation_duration' => array( 'type' => 'object', 'description' => __( 'Animation duration: { "size": 400, "unit": "ms" }.', 'emcp-tools' ) ),
				'accordion_item_title_space_between'          => array( 'type' => 'object', 'description' => __( 'Space between items: { "size": 0, "unit": "px" }.', 'emcp-tools' ) ),
				'accordion_item_title_distance_from_content'  => array( 'type' => 'object', 'description' => __( 'Distance from content: { "size": 0, "unit": "px" }.', 'emcp-tools' ) ),
				'accordion_border_normal_border' => array( 'type' => 'string', 'enum' => array( '', 'none', 'solid', 'double', 'dotted', 'dashed', 'groove' ), 'description' => __( 'Border type.', 'emcp-tools' ) ),
				'accordion_border_normal_color'  => array( 'type' => 'string', 'description' => __( 'Border color (hex).', 'emcp-tools' ) ),
				'accordion_border_normal_width'  => array( 'type' => 'object', 'description' => __( 'Border width.', 'emcp-tools' ) ),
				'accordion_background_normal_background' => array( 'type' => 'string', 'enum' => array( 'classic', 'gradient' ), 'description' => __( 'Background type.', 'emcp-tools' ) ),
				'accordion_background_normal_color'      => array( 'type' => 'string', 'description' => __( 'Background color (hex).', 'emcp-tools' ) ),
			),
			array(),
			'nested-accordion',
			array( 'default_state' => 'expanded', 'max_items_expended' => 'one' )
		);
	}

	private function register_add_portfolio(): void {
		$this->register_convenience_tool(
			'add-portfolio',
			__( 'Add Portfolio (Pro)', 'emcp-tools' ),
			__( 'Adds an Elementor Pro portfolio widget to display a filterable grid of posts or custom post types.', 'emcp-tools' ),
			array(
				'columns'             => array( 'type' => 'integer', 'description' => __( 'Number of columns. Default: 3.', 'emcp-tools' ) ),
				'posts_per_page'      => array( 'type' => 'integer', 'description' => __( 'Number of posts to display. Default: 6.', 'emcp-tools' ) ),
				'show_filter_bar'     => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show category filter bar. Default: no.', 'emcp-tools' ) ),
				'masonry'             => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Enable masonry layout. Default: no.', 'emcp-tools' ) ),
				'show_title'          => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show post title overlay. Default: yes.', 'emcp-tools' ) ),
				'title_tag'           => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'HTML tag for the title. Default: h3.', 'emcp-tools' ) ),
				'thumbnail_size_size' => array( 'type' => 'string', 'enum' => array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), 'description' => __( 'Image size. Default: medium.', 'emcp-tools' ) ),
			),
			array(),
			'portfolio',
			array(
				'columns'             => 3,
				'posts_per_page'      => 6,
				'show_filter_bar'     => 'no',
				'masonry'             => 'no',
				'show_title'          => 'yes',
				'title_tag'           => 'h3',
				'thumbnail_size_size' => 'medium',
			)
		);
	}

	private function register_add_author_box(): void {
		$this->register_convenience_tool(
			'add-author-box',
			__( 'Add Author Box (Pro)', 'emcp-tools' ),
			__( 'Adds an Elementor Pro author box widget displaying the post author\'s avatar, name, bio, and a link to their posts.', 'emcp-tools' ),
			array(
				'show_avatar'     => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show author avatar. Default: yes.', 'emcp-tools' ) ),
				'avatar_size'     => array( 'type' => 'integer', 'description' => __( 'Avatar size in pixels. Default: 96.', 'emcp-tools' ) ),
				'show_name'       => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show author name. Default: yes.', 'emcp-tools' ) ),
				'author_name_tag' => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'HTML tag for the author name. Default: h3.', 'emcp-tools' ) ),
				'show_biography'  => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show author biography. Default: yes.', 'emcp-tools' ) ),
				'show_link'       => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show link button to author posts. Default: yes.', 'emcp-tools' ) ),
				'link_text'       => array( 'type' => 'string', 'description' => __( 'Button label for the author posts link. Default: More Posts.', 'emcp-tools' ) ),
				'alignment'       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Content alignment. Default: left.', 'emcp-tools' ) ),
			),
			array(),
			'author-box',
			array(
				'show_avatar'     => 'yes',
				'avatar_size'     => 96,
				'show_name'       => 'yes',
				'author_name_tag' => 'h3',
				'show_biography'  => 'yes',
				'show_link'       => 'yes',
				'link_text'       => 'More Posts',
				'alignment'       => 'left',
			)
		);
	}

	private function register_add_login(): void {
		$this->register_convenience_tool(
			'add-login',
			__( 'Add Login (Pro)', 'emcp-tools' ),
			__( 'Adds an Elementor Pro login form widget with configurable fields, labels, remember me, lost password link, and post-login redirect.', 'emcp-tools' ),
			array(
				'show_labels'          => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show field labels. Default: yes.', 'emcp-tools' ) ),
				'show_remember_me'     => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show "Remember Me" checkbox. Default: yes.', 'emcp-tools' ) ),
				'show_lost_password'   => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show "Lost Password" link. Default: yes.', 'emcp-tools' ) ),
				'button_text'          => array( 'type' => 'string', 'description' => __( 'Submit button label. Default: Log In.', 'emcp-tools' ) ),
				'button_size'          => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size preset. Default: sm.', 'emcp-tools' ) ),
				'redirect_after_login' => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Enable custom redirect after login. Default: no.', 'emcp-tools' ) ),
				'redirect_url'         => array( 'type' => 'string', 'description' => __( 'URL to redirect to after login (requires redirect_after_login=yes).', 'emcp-tools' ) ),
				'align'                => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Button alignment. Default: left.', 'emcp-tools' ) ),
			),
			array(),
			'login',
			array(
				'show_labels'          => 'yes',
				'show_remember_me'     => 'yes',
				'show_lost_password'   => 'yes',
				'button_text'          => 'Log In',
				'button_size'          => 'sm',
				'redirect_after_login' => 'no',
				'redirect_url'         => '',
				'align'                => 'left',
			)
		);
	}

	private function register_add_code_highlight(): void {
		$this->register_convenience_tool(
			'add-code-highlight',
			__( 'Add Code Highlight (Pro)', 'emcp-tools' ),
			__( 'Adds a syntax-highlighted code block widget.', 'emcp-tools' ),
			array(
				'code'              => array( 'type' => 'string', 'description' => __( 'The code content to display.', 'emcp-tools' ) ),
				'language'          => array( 'type' => 'string', 'enum' => array( 'php', 'javascript', 'css', 'html', 'python', 'bash' ), 'description' => __( 'Syntax language. Default: php.', 'emcp-tools' ) ),
				'theme'             => array( 'type' => 'string', 'enum' => array( 'default', 'dark', 'funky', 'okaidia', 'twilight', 'coy' ), 'description' => __( 'Color theme. Default: default.', 'emcp-tools' ) ),
				'line_numbers'      => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show line numbers. Default: yes.', 'emcp-tools' ) ),
				'copy_to_clipboard' => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show copy-to-clipboard button. Default: yes.', 'emcp-tools' ) ),
			),
			array(),
			'code-highlight',
			array(
				'code'              => '',
				'language'          => 'php',
				'theme'             => 'default',
				'line_numbers'      => 'yes',
				'copy_to_clipboard' => 'yes',
			)
		);
	}

	private function register_add_reviews(): void {
		$this->register_convenience_tool(
			'add-reviews',
			__( 'Add Reviews (Pro)', 'emcp-tools' ),
			__( 'Adds a reviews/testimonials carousel widget.', 'emcp-tools' ),
			array(
				'slides_per_view' => array( 'type' => 'integer', 'description' => __( 'Number of slides visible at once. Default: 3.', 'emcp-tools' ) ),
				'autoplay'        => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Enable autoplay. Default: no.', 'emcp-tools' ) ),
				'autoplay_speed'  => array( 'type' => 'integer', 'description' => __( 'Autoplay speed in milliseconds. Default: 3000.', 'emcp-tools' ) ),
				'loop'            => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Enable infinite loop. Default: yes.', 'emcp-tools' ) ),
				'show_arrows'     => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show navigation arrows. Default: yes.', 'emcp-tools' ) ),
				'pause_on_hover'  => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Pause autoplay on hover. Default: yes.', 'emcp-tools' ) ),
			),
			array(),
			'reviews',
			array(
				'slides_per_view' => 3,
				'autoplay'        => 'no',
				'autoplay_speed'  => 3000,
				'loop'            => 'yes',
				'show_arrows'     => 'yes',
				'pause_on_hover'  => 'yes',
			)
		);
	}

	private function register_add_off_canvas(): void {
		$this->register_convenience_tool(
			'add-off-canvas',
			__( 'Add Off-Canvas (Pro)', 'emcp-tools' ),
			__( 'Adds an off-canvas panel widget.', 'emcp-tools' ),
			array(
				'horizontal_position' => array( 'type' => 'string', 'enum' => array( 'left', 'right' ), 'description' => __( 'Panel side. Default: left.', 'emcp-tools' ) ),
				'vertical_position'   => array( 'type' => 'string', 'enum' => array( 'top', 'center', 'bottom' ), 'description' => __( 'Vertical alignment. Default: center.', 'emcp-tools' ) ),
				'width'               => array( 'type' => 'integer', 'description' => __( 'Panel width in pixels. Default: 300.', 'emcp-tools' ) ),
				'entrance_animation'  => array( 'type' => 'string', 'enum' => array( 'none', 'slideInLeft', 'slideInRight', 'fadeIn' ), 'description' => __( 'Open animation. Default: slideInLeft.', 'emcp-tools' ) ),
				'has_overlay'         => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Show background overlay. Default: yes.', 'emcp-tools' ) ),
				'prevent_scroll'      => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Lock body scroll when open. Default: yes.', 'emcp-tools' ) ),
			),
			array(),
			'off-canvas',
			array(
				'horizontal_position' => 'left',
				'vertical_position'   => 'center',
				'width'               => 300,
				'entrance_animation'  => 'slideInLeft',
				'has_overlay'         => 'yes',
				'prevent_scroll'      => 'yes',
			)
		);
	}

	private function register_add_progress_tracker(): void {
		$this->register_convenience_tool(
			'add-progress-tracker',
			__( 'Add Progress Tracker (Pro)', 'emcp-tools' ),
			__( 'Adds a scroll progress tracker widget.', 'emcp-tools' ),
			array(
				'type'          => array( 'type' => 'string', 'enum' => array( 'horizontal', 'circular' ), 'description' => __( 'Tracker style. Default: horizontal.', 'emcp-tools' ) ),
				'relative_to'   => array( 'type' => 'string', 'enum' => array( 'page', 'element' ), 'description' => __( 'What to track. Default: page.', 'emcp-tools' ) ),
				'align'         => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Alignment. Default: left.', 'emcp-tools' ) ),
				'circular_size' => array( 'type' => 'integer', 'description' => __( 'Circle diameter in pixels (circular type). Default: 200.', 'emcp-tools' ) ),
				'circular_width' => array( 'type' => 'integer', 'description' => __( 'Circle stroke width in pixels (circular type). Default: 8.', 'emcp-tools' ) ),
			),
			array(),
			'progress-tracker',
			array(
				'type'           => 'horizontal',
				'relative_to'    => 'page',
				'align'          => 'left',
				'circular_size'  => 200,
				'circular_width' => 8,
			)
		);
	}

	private function register_add_search(): void {
		$this->register_convenience_tool(
			'add-search',
			__( 'Add Search (Pro)', 'emcp-tools' ),
			__( 'Adds a search widget with live results support.', 'emcp-tools' ),
			array(
				'search_input_placeholder_text' => array( 'type' => 'string', 'description' => __( 'Placeholder text for the search input. Default: Search...', 'emcp-tools' ) ),
				'submit_trigger'                => array( 'type' => 'string', 'enum' => array( 'button', 'auto' ), 'description' => __( 'Search trigger method. Default: button.', 'emcp-tools' ) ),
				'submit_button_text'            => array( 'type' => 'string', 'description' => __( 'Submit button label. Default: Search.', 'emcp-tools' ) ),
				'live_results'                  => array( 'type' => 'string', 'enum' => array( 'yes', 'no' ), 'description' => __( 'Enable live search results dropdown. Default: no.', 'emcp-tools' ) ),
				'number_of_items'               => array( 'type' => 'integer', 'description' => __( 'Max results to show. Default: 5.', 'emcp-tools' ) ),
				'search_query_post_type'        => array( 'type' => 'string', 'description' => __( 'Post type to search. Default: any.', 'emcp-tools' ) ),
			),
			array(),
			'search',
			array(
				'search_input_placeholder_text' => 'Search...',
				'submit_trigger'                => 'button',
				'submit_button_text'            => 'Search',
				'live_results'                  => 'no',
				'number_of_items'               => 5,
				'search_query_post_type'        => 'any',

			)
		);
	}

	// ── Phase 6: WooCommerce Widget Convenience Tools ─────────────────

	private function register_add_wc_products(): void {
		$this->register_convenience_tool(
			'add-wc-products',
			__( 'Add WooCommerce Products', 'emcp-tools' ),
			__( 'Adds a WooCommerce products grid widget (Pro + WooCommerce).', 'emcp-tools' ),
			array(
				'columns'        => array( 'type' => 'number', 'description' => __( 'Number of columns. Default: 4.', 'emcp-tools' ) ),
				'rows'           => array( 'type' => 'number', 'description' => __( 'Number of rows. Default: 1.', 'emcp-tools' ) ),
				'paginate'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show pagination.', 'emcp-tools' ) ),
				'orderby'        => array( 'type' => 'string', 'enum' => array( 'date', 'title', 'price', 'popularity', 'rating', 'rand', 'menu_order' ), 'description' => __( 'Order by. Default: date.', 'emcp-tools' ) ),
				'order'          => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'description' => __( 'Sort order. Default: desc.', 'emcp-tools' ) ),
				'query_post_type' => array( 'type' => 'string', 'enum' => array( 'product', 'current_query', 'by_id', 'related' ), 'description' => __( 'Query source. Default: product.', 'emcp-tools' ) ),
				'show_result_count' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show result count.', 'emcp-tools' ) ),
				'allow_order'    => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Allow ordering.', 'emcp-tools' ) ),
			),
			array(),
			'woocommerce-products',
			array( 'columns' => 4, 'rows' => 1 )
		);
	}

	private function register_add_wc_add_to_cart(): void {
		$this->register_convenience_tool(
			'add-wc-add-to-cart',
			__( 'Add WooCommerce Add to Cart', 'emcp-tools' ),
			__( 'Adds a WooCommerce add-to-cart button widget (Pro + WooCommerce).', 'emcp-tools' ),
			array(
				'product_id'  => array( 'type' => 'integer', 'description' => __( 'Product ID to link to.', 'emcp-tools' ) ),
				'show_quantity' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show quantity input.', 'emcp-tools' ) ),
				'quantity'    => array( 'type' => 'number', 'description' => __( 'Default quantity.', 'emcp-tools' ) ),
				'view'        => array( 'type' => 'string', 'enum' => array( '', 'stacked', 'inline' ), 'description' => __( 'Layout view.', 'emcp-tools' ) ),
			),
			array(),
			'wc-add-to-cart',
			array()
		);
	}

	private function register_add_wc_cart(): void {
		$this->register_convenience_tool(
			'add-wc-cart',
			__( 'Add WooCommerce Cart', 'emcp-tools' ),
			__( 'Adds the WooCommerce cart page widget (Pro + WooCommerce).', 'emcp-tools' ),
			array(),
			array(),
			'woocommerce-cart',
			array()
		);
	}

	private function register_add_wc_checkout(): void {
		$this->register_convenience_tool(
			'add-wc-checkout',
			__( 'Add WooCommerce Checkout', 'emcp-tools' ),
			__( 'Adds the WooCommerce checkout page widget (Pro + WooCommerce).', 'emcp-tools' ),
			array(),
			array(),
			'woocommerce-checkout-page',
			array()
		);
	}

	private function register_add_wc_menu_cart(): void {
		$this->register_convenience_tool(
			'add-wc-menu-cart',
			__( 'Add WooCommerce Menu Cart', 'emcp-tools' ),
			__( 'Adds a mini cart icon for the menu (Pro + WooCommerce).', 'emcp-tools' ),
			array(
				'icon'            => array( 'type' => 'object', 'description' => __( 'Cart icon object.', 'emcp-tools' ) ),
				'items_indicator' => array( 'type' => 'string', 'enum' => array( 'none', 'bubble', 'plain' ), 'description' => __( 'Items indicator style.', 'emcp-tools' ) ),
				'hide_empty_indicator' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Hide when cart is empty.', 'emcp-tools' ) ),
				'alignment'       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Alignment.', 'emcp-tools' ) ),
			),
			array(),
			'woocommerce-menu-cart',
			array()
		);
	}
}

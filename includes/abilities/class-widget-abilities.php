<?php
/**
 * Widget MCP abilities for Elementor.
 *
 * Registers the universal add-widget/update-widget tools plus convenience
 * shortcut tools for common widgets (heading, text, image, button, etc.).
 * Pro widget tools register only when Elementor Pro is active.
 *
 * @package Elementor_MCP
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
class Elementor_MCP_Widget_Abilities {

	/**
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * @var Elementor_MCP_Element_Factory
	 */
	private $factory;

	/**
	 * @var Elementor_MCP_Schema_Generator
	 */
	private $schema_generator;

	/**
	 * @var Elementor_MCP_Settings_Validator
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
	 * @param Elementor_MCP_Data               $data             The data access layer.
	 * @param Elementor_MCP_Element_Factory    $factory          The element factory.
	 * @param Elementor_MCP_Schema_Generator   $schema_generator The schema generator.
	 * @param Elementor_MCP_Settings_Validator $validator        The settings validator.
	 */
	public function __construct(
		Elementor_MCP_Data $data,
		Elementor_MCP_Element_Factory $factory,
		Elementor_MCP_Schema_Generator $schema_generator,
		Elementor_MCP_Settings_Validator $validator
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

		wp_register_ability(
			'elementor-mcp/add-widget',
			array(
				'label'               => __( 'Add Widget', 'elementor-mcp' ),
				'description'         => __( 'Adds any Elementor widget to a container. Use get-widget-schema to discover the available settings for each widget type.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_add_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'parent_id'   => array(
							'type'        => 'string',
							'description' => __( 'Parent container element ID.', 'elementor-mcp' ),
						),
						'position'    => array(
							'type'        => 'integer',
							'description' => __( 'Insert position. -1 = append.', 'elementor-mcp' ),
						),
						'widget_type' => array(
							'type'        => 'string',
							'description' => __( 'The widget type name (e.g. "heading", "button", "image").', 'elementor-mcp' ),
						),
						'settings'    => array(
							'type'        => 'object',
							'description' => __( 'Widget-specific settings.', 'elementor-mcp' ),
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
			return new \WP_Error( 'missing_params', __( 'post_id, parent_id, and widget_type are required.', 'elementor-mcp' ) );
		}

		// Validate widget type exists.
		$widget_instance = \Elementor\Plugin::$instance->widgets_manager->get_widget_types( $widget_type );
		if ( ! $widget_instance ) {
			return new \WP_Error(
				'invalid_widget_type',
				/* translators: %s: widget type name */
				sprintf( __( 'Widget type "%s" not found.', 'elementor-mcp' ), $widget_type )
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
			return new \WP_Error( 'parent_not_found', __( 'Parent container not found.', 'elementor-mcp' ) );
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

		wp_register_ability(
			'elementor-mcp/update-widget',
			array(
				'label'               => __( 'Update Widget', 'elementor-mcp' ),
				'description'         => __( 'Updates settings on an existing widget. Settings are merged (partial update).', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_update_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'element_id' => array(
							'type'        => 'string',
							'description' => __( 'The widget element ID.', 'elementor-mcp' ),
						),
						'settings'   => array(
							'type'        => 'object',
							'description' => __( 'Partial settings to merge.', 'elementor-mcp' ),
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
			return new \WP_Error( 'missing_params', __( 'post_id, element_id, and settings are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Find the widget to validate its type.
		$element = $this->data->find_element_by_id( $page_data, $element_id );

		if ( null === $element ) {
			return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
		}

		if ( ( $element['elType'] ?? '' ) !== 'widget' ) {
			return new \WP_Error( 'not_a_widget', __( 'Target element is not a widget.', 'elementor-mcp' ) );
		}

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );

		if ( ! $updated ) {
			return new \WP_Error( 'update_failed', __( 'Failed to update widget settings.', 'elementor-mcp' ) );
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
				'description' => __( 'The post/page ID.', 'elementor-mcp' ),
			),
			'parent_id' => array(
				'type'        => 'string',
				'description' => __( 'Parent container element ID.', 'elementor-mcp' ),
			),
			'position'  => array(
				'type'        => 'integer',
				'description' => __( 'Insert position. -1 = append.', 'elementor-mcp' ),
			),
		);

		$all_required = array_unique( array_merge( array( 'post_id', 'parent_id' ), $required ) );

		wp_register_ability(
			$full_name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => 'elementor-mcp',
				'execute_callback'    => function ( $input ) use ( $widget_type, $extra_props, $defaults ) {
					return $this->execute_convenience_tool( $input, $widget_type, array_keys( $extra_props ), $defaults );
				},
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array_merge( $base_props, $extra_props ),
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
			__( 'Add Heading', 'elementor-mcp' ),
			__( 'Adds a heading widget. Supports full typography (set typography_typography=custom first), text stroke, text shadow, blend mode, hover color. Also accepts responsive suffixes (align_tablet, align_mobile) and common advanced controls (_margin, _padding, _background_*, _border_*, etc).', 'elementor-mcp' ),
			array(
				'title'                       => array( 'type' => 'string', 'description' => __( 'Heading text.', 'elementor-mcp' ) ),
				'header_size'                 => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'HTML tag. Default: h2.', 'elementor-mcp' ) ),
				'size'                        => array( 'type' => 'string', 'enum' => array( 'default', 'small', 'medium', 'large', 'xl', 'xxl' ), 'description' => __( 'Elementor size preset.', 'elementor-mcp' ) ),
				'align'                       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Text alignment. Responsive: align_tablet, align_mobile.', 'elementor-mcp' ) ),
				'title_color'                 => array( 'type' => 'string', 'description' => __( 'Heading color (hex/rgba).', 'elementor-mcp' ) ),
				'title_hover_color'           => array( 'type' => 'string', 'description' => __( 'Heading hover color (hex/rgba).', 'elementor-mcp' ) ),
				'link'                        => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'elementor-mcp' ) ),
				'blend_mode'                  => array( 'type' => 'string', 'enum' => array( '', 'multiply', 'screen', 'overlay', 'darken', 'lighten', 'color-dodge', 'saturation', 'color', 'difference', 'exclusion', 'hue', 'luminosity' ), 'description' => __( 'CSS blend mode.', 'elementor-mcp' ) ),
				// Typography group — set typography_typography=custom to activate.
				'typography_typography'        => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable typography controls.', 'elementor-mcp' ) ),
				'typography_font_family'       => array( 'type' => 'string', 'description' => __( 'Font family (e.g. "Roboto", "Montserrat").', 'elementor-mcp' ) ),
				'typography_font_size'         => array( 'type' => 'object', 'description' => __( 'Font size: {size, unit}. Units: px, em, rem, vw.', 'elementor-mcp' ) ),
				'typography_font_weight'       => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Font weight.', 'elementor-mcp' ) ),
				'typography_text_transform'    => array( 'type' => 'string', 'enum' => array( '', 'uppercase', 'lowercase', 'capitalize', 'none' ), 'description' => __( 'Text transform.', 'elementor-mcp' ) ),
				'typography_font_style'        => array( 'type' => 'string', 'enum' => array( '', 'normal', 'italic', 'oblique' ), 'description' => __( 'Font style.', 'elementor-mcp' ) ),
				'typography_text_decoration'   => array( 'type' => 'string', 'enum' => array( '', 'none', 'underline', 'overline', 'line-through' ), 'description' => __( 'Text decoration.', 'elementor-mcp' ) ),
				'typography_line_height'       => array( 'type' => 'object', 'description' => __( 'Line height: {size, unit}. Units: px, em.', 'elementor-mcp' ) ),
				'typography_letter_spacing'    => array( 'type' => 'object', 'description' => __( 'Letter spacing: {size, unit}. Units: px, em.', 'elementor-mcp' ) ),
				'typography_word_spacing'      => array( 'type' => 'object', 'description' => __( 'Word spacing: {size, unit}.', 'elementor-mcp' ) ),
				// Text stroke — set text_stroke_text_stroke=yes to activate.
				'text_stroke_text_stroke'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable text stroke.', 'elementor-mcp' ) ),
				'text_stroke_stroke_width'     => array( 'type' => 'object', 'description' => __( 'Stroke width: {size, unit}.', 'elementor-mcp' ) ),
				'text_stroke_stroke_color'     => array( 'type' => 'string', 'description' => __( 'Stroke color (hex/rgba).', 'elementor-mcp' ) ),
				// Text shadow.
				'title_text_shadow_text_shadow' => array( 'type' => 'object', 'description' => __( 'Text shadow: {horizontal, vertical, blur, color}.', 'elementor-mcp' ) ),
			),
			array( 'title' ),
			'heading',
			array( 'header_size' => 'h2' )
		);
	}

	private function register_add_text_editor(): void {
		$this->register_convenience_tool(
			'add-text-editor',
			__( 'Add Text Editor', 'elementor-mcp' ),
			__( 'Adds a rich text editor widget. Supports typography (set typography_typography=custom), drop cap, text columns, and text color. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'editor'     => array( 'type' => 'string', 'description' => __( 'HTML content.', 'elementor-mcp' ) ),
				'align'      => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Text alignment. Responsive: align_tablet, align_mobile.', 'elementor-mcp' ) ),
				'text_color' => array( 'type' => 'string', 'description' => __( 'Text color (hex/rgba).', 'elementor-mcp' ) ),
				// Drop cap.
				'drop_cap'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable drop cap on first letter.', 'elementor-mcp' ) ),
				// Text columns.
				'column_gap' => array( 'type' => 'object', 'description' => __( 'Column gap: {size, unit}. Works with text_columns.', 'elementor-mcp' ) ),
				'text_columns' => array( 'type' => 'string', 'description' => __( 'Number of text columns (1-10).', 'elementor-mcp' ) ),
				// Typography group.
				'typography_typography'     => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable typography controls.', 'elementor-mcp' ) ),
				'typography_font_family'    => array( 'type' => 'string', 'description' => __( 'Font family.', 'elementor-mcp' ) ),
				'typography_font_size'      => array( 'type' => 'object', 'description' => __( 'Font size: {size, unit}.', 'elementor-mcp' ) ),
				'typography_font_weight'    => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Font weight.', 'elementor-mcp' ) ),
				'typography_text_transform' => array( 'type' => 'string', 'enum' => array( '', 'uppercase', 'lowercase', 'capitalize', 'none' ), 'description' => __( 'Text transform.', 'elementor-mcp' ) ),
				'typography_line_height'    => array( 'type' => 'object', 'description' => __( 'Line height: {size, unit}.', 'elementor-mcp' ) ),
				'typography_letter_spacing' => array( 'type' => 'object', 'description' => __( 'Letter spacing: {size, unit}.', 'elementor-mcp' ) ),
			),
			array( 'editor' ),
			'text-editor'
		);
	}

	private function register_add_image(): void {
		$this->register_convenience_tool(
			'add-image',
			__( 'Add Image', 'elementor-mcp' ),
			__( 'Adds an image widget. Supports width, max-width, opacity, border, border-radius, box shadow, CSS filters (brightness, contrast, saturation, hue), and hover effects. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'image'          => array( 'type' => 'object', 'description' => __( 'Image object with url (required) and optional id.', 'elementor-mcp' ) ),
				'image_size'     => array( 'type' => 'string', 'enum' => array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), 'description' => __( 'Image size preset.', 'elementor-mcp' ) ),
				'align'          => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Image alignment. Responsive: align_tablet, align_mobile.', 'elementor-mcp' ) ),
				'caption_source' => array( 'type' => 'string', 'enum' => array( 'none', 'attachment', 'custom' ), 'description' => __( 'Caption source.', 'elementor-mcp' ) ),
				'caption'        => array( 'type' => 'string', 'description' => __( 'Custom caption text.', 'elementor-mcp' ) ),
				'link_to'        => array( 'type' => 'string', 'enum' => array( 'none', 'file', 'custom' ), 'description' => __( 'Link behavior.', 'elementor-mcp' ) ),
				'link'           => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'elementor-mcp' ) ),
				// Sizing.
				'width'          => array( 'type' => 'object', 'description' => __( 'Image width: {size, unit}. Units: px, %, vw.', 'elementor-mcp' ) ),
				'max_width'      => array( 'type' => 'object', 'description' => __( 'Max width: {size, unit}.', 'elementor-mcp' ) ),
				'height'         => array( 'type' => 'object', 'description' => __( 'Image height: {size, unit}.', 'elementor-mcp' ) ),
				'object_fit'     => array( 'type' => 'string', 'enum' => array( '', 'fill', 'cover', 'contain' ), 'description' => __( 'Object fit when height is set.', 'elementor-mcp' ) ),
				// Style.
				'opacity'        => array( 'type' => 'object', 'description' => __( 'Image opacity: {size, unit}. 0-1 range.', 'elementor-mcp' ) ),
				'hover_animation' => array( 'type' => 'string', 'description' => __( 'Hover animation (grow, shrink, pulse, push, etc).', 'elementor-mcp' ) ),
				'hover_opacity'  => array( 'type' => 'object', 'description' => __( 'Hover opacity: {size, unit}. 0-1 range.', 'elementor-mcp' ) ),
				// CSS Filters.
				'css_filters_css_filter' => array( 'type' => 'string', 'enum' => array( 'custom', '' ), 'description' => __( 'Set to "custom" to enable CSS filter controls.', 'elementor-mcp' ) ),
				'css_filters_blur'       => array( 'type' => 'object', 'description' => __( 'Blur: {size, unit}. px.', 'elementor-mcp' ) ),
				'css_filters_brightness' => array( 'type' => 'object', 'description' => __( 'Brightness: {size, unit}. 0-200%.', 'elementor-mcp' ) ),
				'css_filters_contrast'   => array( 'type' => 'object', 'description' => __( 'Contrast: {size, unit}. 0-200%.', 'elementor-mcp' ) ),
				'css_filters_saturate'   => array( 'type' => 'object', 'description' => __( 'Saturation: {size, unit}. 0-200%.', 'elementor-mcp' ) ),
				'css_filters_hue'        => array( 'type' => 'object', 'description' => __( 'Hue rotation: {size, unit}. 0-360deg.', 'elementor-mcp' ) ),
				// Border.
				'image_border_border'    => array( 'type' => 'string', 'enum' => array( '', 'solid', 'double', 'dotted', 'dashed', 'groove' ), 'description' => __( 'Border style.', 'elementor-mcp' ) ),
				'image_border_width'     => array( 'type' => 'object', 'description' => __( 'Border width: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'image_border_color'     => array( 'type' => 'string', 'description' => __( 'Border color.', 'elementor-mcp' ) ),
				'image_border_radius'    => array( 'type' => 'object', 'description' => __( 'Border radius: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				// Box shadow.
				'image_box_shadow_box_shadow_type' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable box shadow.', 'elementor-mcp' ) ),
				'image_box_shadow_box_shadow'      => array( 'type' => 'object', 'description' => __( 'Box shadow: {horizontal, vertical, blur, spread, color}.', 'elementor-mcp' ) ),
			),
			array( 'image' ),
			'image'
		);
	}

	private function register_add_button(): void {
		$this->register_convenience_tool(
			'add-button',
			__( 'Add Button', 'elementor-mcp' ),
			__( 'Adds a button widget. Supports typography (set typography_typography=custom), border, background, hover colors, box shadow, and text shadow. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'text'          => array( 'type' => 'string', 'description' => __( 'Button text.', 'elementor-mcp' ) ),
				'link'          => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'elementor-mcp' ) ),
				'size'          => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size.', 'elementor-mcp' ) ),
				'button_type'   => array( 'type' => 'string', 'enum' => array( '', 'info', 'success', 'warning', 'danger' ), 'description' => __( 'Button style type.', 'elementor-mcp' ) ),
				'align'         => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Button alignment. Responsive: align_tablet, align_mobile.', 'elementor-mcp' ) ),
				'selected_icon' => array( 'type' => 'object', 'description' => __( 'Icon object with value and library.', 'elementor-mcp' ) ),
				'icon_align'    => array( 'type' => 'string', 'enum' => array( 'row', 'row-reverse' ), 'description' => __( 'Icon position.', 'elementor-mcp' ) ),
				'icon_indent'   => array( 'type' => 'object', 'description' => __( 'Icon spacing: {size, unit}.', 'elementor-mcp' ) ),
				// Colors.
				'button_text_color'       => array( 'type' => 'string', 'description' => __( 'Text color (hex/rgba).', 'elementor-mcp' ) ),
				'background_color'        => array( 'type' => 'string', 'description' => __( 'Background color (hex/rgba).', 'elementor-mcp' ) ),
				// Hover colors.
				'hover_color'             => array( 'type' => 'string', 'description' => __( 'Hover text color.', 'elementor-mcp' ) ),
				'button_background_hover_color' => array( 'type' => 'string', 'description' => __( 'Hover background color.', 'elementor-mcp' ) ),
				'hover_animation'         => array( 'type' => 'string', 'description' => __( 'Hover animation (e.g. grow, shrink, pulse, push).', 'elementor-mcp' ) ),
				// Border.
				'border_border'           => array( 'type' => 'string', 'enum' => array( '', 'solid', 'double', 'dotted', 'dashed', 'groove' ), 'description' => __( 'Border style.', 'elementor-mcp' ) ),
				'border_width'            => array( 'type' => 'object', 'description' => __( 'Border width: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'border_color'            => array( 'type' => 'string', 'description' => __( 'Border color.', 'elementor-mcp' ) ),
				'border_radius'           => array( 'type' => 'object', 'description' => __( 'Border radius: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				// Box shadow.
				'button_box_shadow_box_shadow_type' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable box shadow.', 'elementor-mcp' ) ),
				'button_box_shadow_box_shadow'      => array( 'type' => 'object', 'description' => __( 'Box shadow: {horizontal, vertical, blur, spread, color}.', 'elementor-mcp' ) ),
				// Typography group.
				'typography_typography'    => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable typography controls.', 'elementor-mcp' ) ),
				'typography_font_family'   => array( 'type' => 'string', 'description' => __( 'Font family.', 'elementor-mcp' ) ),
				'typography_font_size'     => array( 'type' => 'object', 'description' => __( 'Font size: {size, unit}.', 'elementor-mcp' ) ),
				'typography_font_weight'   => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Font weight.', 'elementor-mcp' ) ),
				'typography_text_transform' => array( 'type' => 'string', 'enum' => array( '', 'uppercase', 'lowercase', 'capitalize', 'none' ), 'description' => __( 'Text transform.', 'elementor-mcp' ) ),
				'typography_letter_spacing' => array( 'type' => 'object', 'description' => __( 'Letter spacing: {size, unit}.', 'elementor-mcp' ) ),
				// Text shadow.
				'text_shadow_text_shadow'  => array( 'type' => 'object', 'description' => __( 'Text shadow: {horizontal, vertical, blur, color}.', 'elementor-mcp' ) ),
				// Padding.
				'button_padding'          => array( 'type' => 'object', 'description' => __( 'Button padding: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
			),
			array( 'text' ),
			'button',
			array( 'text' => 'Click here', 'size' => 'sm' )
		);
	}

	private function register_add_video(): void {
		$this->register_convenience_tool(
			'add-video',
			__( 'Add Video', 'elementor-mcp' ),
			__( 'Adds a video widget. Supports YouTube, Vimeo, Dailymotion, self-hosted. Options: start/end time, lazy load, privacy mode, image overlay, play icon. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'video_type'     => array( 'type' => 'string', 'enum' => array( 'youtube', 'vimeo', 'dailymotion', 'hosted' ), 'description' => __( 'Video source type.', 'elementor-mcp' ) ),
				'youtube_url'    => array( 'type' => 'string', 'description' => __( 'YouTube URL.', 'elementor-mcp' ) ),
				'vimeo_url'      => array( 'type' => 'string', 'description' => __( 'Vimeo URL.', 'elementor-mcp' ) ),
				'dailymotion_url' => array( 'type' => 'string', 'description' => __( 'Dailymotion URL.', 'elementor-mcp' ) ),
				'insert_url'     => array( 'type' => 'object', 'description' => __( 'Self-hosted video URL object: {url}.', 'elementor-mcp' ) ),
				'autoplay'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay on load.', 'elementor-mcp' ) ),
				'mute'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Mute audio.', 'elementor-mcp' ) ),
				'loop'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Loop video.', 'elementor-mcp' ) ),
				'controls'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show player controls.', 'elementor-mcp' ) ),
				'start'          => array( 'type' => 'integer', 'description' => __( 'Start time in seconds.', 'elementor-mcp' ) ),
				'end'            => array( 'type' => 'integer', 'description' => __( 'End time in seconds.', 'elementor-mcp' ) ),
				'yt_privacy'     => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'YouTube privacy-enhanced mode.', 'elementor-mcp' ) ),
				'lazy_load'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Lazy load the video.', 'elementor-mcp' ) ),
				'rel'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show related videos at end (YouTube).', 'elementor-mcp' ) ),
				'modestbranding' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Modest branding (YouTube).', 'elementor-mcp' ) ),
				// Image overlay.
				'show_image_overlay' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show image overlay (poster).', 'elementor-mcp' ) ),
				'image_overlay'      => array( 'type' => 'object', 'description' => __( 'Overlay image: {url, id}.', 'elementor-mcp' ) ),
				'show_play_icon'     => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show play icon on overlay.', 'elementor-mcp' ) ),
				// Aspect ratio.
				'aspect_ratio'       => array( 'type' => 'string', 'enum' => array( '169', '219', '43', '32', '11', '916' ), 'description' => __( 'Video aspect ratio. Values: 169=16:9, 219=21:9, 43=4:3, 32=3:2, 11=1:1, 916=9:16.', 'elementor-mcp' ) ),
			),
			array(),
			'video',
			array( 'video_type' => 'youtube' )
		);
	}

	private function register_add_icon(): void {
		$this->register_convenience_tool(
			'add-icon',
			__( 'Add Icon', 'elementor-mcp' ),
			__( 'Adds an icon widget. Supports Font Awesome and SVG icons, view modes (default/stacked/framed), hover colors, rotate, padding, border radius, and hover animation. For SVG, first use upload-svg-icon.', 'elementor-mcp' ),
			array(
				'selected_icon'    => array( 'type' => 'object', 'description' => __( 'Icon object. Font Awesome: { "value": "fas fa-star", "library": "fa-solid" }. SVG: { "value": { "id": 123, "url": "..." }, "library": "svg" }. Libraries: fa-solid, fa-regular, fa-brands.', 'elementor-mcp' ) ),
				'view'             => array( 'type' => 'string', 'enum' => array( 'default', 'stacked', 'framed' ), 'description' => __( 'Icon view mode.', 'elementor-mcp' ) ),
				'shape'            => array( 'type' => 'string', 'enum' => array( 'circle', 'square' ), 'description' => __( 'Icon shape (for stacked/framed).', 'elementor-mcp' ) ),
				'primary_color'    => array( 'type' => 'string', 'description' => __( 'Primary/icon color (hex/rgba).', 'elementor-mcp' ) ),
				'secondary_color'  => array( 'type' => 'string', 'description' => __( 'Secondary/background color for stacked/framed (hex/rgba).', 'elementor-mcp' ) ),
				'hover_primary_color'   => array( 'type' => 'string', 'description' => __( 'Hover icon color.', 'elementor-mcp' ) ),
				'hover_secondary_color' => array( 'type' => 'string', 'description' => __( 'Hover background color for stacked/framed.', 'elementor-mcp' ) ),
				'hover_animation'  => array( 'type' => 'string', 'description' => __( 'Hover animation (grow, shrink, pulse, push, etc).', 'elementor-mcp' ) ),
				'size'             => array( 'type' => 'object', 'description' => __( 'Icon size: {size, unit}.', 'elementor-mcp' ) ),
				'icon_padding'     => array( 'type' => 'object', 'description' => __( 'Icon padding: {size, unit}. For stacked/framed.', 'elementor-mcp' ) ),
				'rotate'           => array( 'type' => 'object', 'description' => __( 'Icon rotation: {size, unit}. Degrees.', 'elementor-mcp' ) ),
				'border_width'     => array( 'type' => 'object', 'description' => __( 'Border width for framed view: {size, unit}.', 'elementor-mcp' ) ),
				'border_radius'    => array( 'type' => 'object', 'description' => __( 'Border radius: {size, unit}.', 'elementor-mcp' ) ),
				'link'             => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'elementor-mcp' ) ),
				'align'            => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Icon alignment. Responsive: align_tablet, align_mobile.', 'elementor-mcp' ) ),
			),
			array(),
			'icon',
			array( 'selected_icon' => array( 'value' => 'fas fa-star', 'library' => 'fa-solid' ) )
		);
	}

	private function register_add_spacer(): void {
		$this->register_convenience_tool(
			'add-spacer',
			__( 'Add Spacer', 'elementor-mcp' ),
			__( 'Adds a spacer widget for vertical spacing between elements.', 'elementor-mcp' ),
			array(
				'space' => array( 'type' => 'object', 'description' => __( 'Spacer height: { "size": 50, "unit": "px" }.', 'elementor-mcp' ) ),
			),
			array(),
			'spacer',
			array( 'space' => array( 'size' => 50, 'unit' => 'px' ) )
		);
	}

	private function register_add_divider(): void {
		$this->register_convenience_tool(
			'add-divider',
			__( 'Add Divider', 'elementor-mcp' ),
			__( 'Adds a horizontal divider/separator widget with style, weight, color, and width options.', 'elementor-mcp' ),
			array(
				'style'  => array( 'type' => 'string', 'enum' => array( 'solid', 'dashed', 'dotted', 'double' ), 'description' => __( 'Divider line style.', 'elementor-mcp' ) ),
				'weight' => array( 'type' => 'object', 'description' => __( 'Line weight: { "size": 1, "unit": "px" }.', 'elementor-mcp' ) ),
				'color'  => array( 'type' => 'string', 'description' => __( 'Divider color (hex).', 'elementor-mcp' ) ),
				'width'  => array( 'type' => 'object', 'description' => __( 'Divider width: { "size": 100, "unit": "%" }.', 'elementor-mcp' ) ),
				'gap'    => array( 'type' => 'object', 'description' => __( 'Gap above/below: { "size": 15, "unit": "px" }.', 'elementor-mcp' ) ),
			),
			array(),
			'divider',
			array( 'style' => 'solid' )
		);
	}

	private function register_add_icon_box(): void {
		$this->register_convenience_tool(
			'add-icon-box',
			__( 'Add Icon Box', 'elementor-mcp' ),
			__( 'Adds an icon box widget. Supports icon position (top/left/right), title typography (set title_typography_typography=custom), description typography, icon spacing, hover colors, and hover animation. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'selected_icon'    => array( 'type' => 'object', 'description' => __( 'Icon object. Font Awesome: { "value": "fas fa-star", "library": "fa-solid" }. SVG: { "value": { "id": 123, "url": "..." }, "library": "svg" }.', 'elementor-mcp' ) ),
				'title_text'       => array( 'type' => 'string', 'description' => __( 'Box title.', 'elementor-mcp' ) ),
				'description_text' => array( 'type' => 'string', 'description' => __( 'Box description.', 'elementor-mcp' ) ),
				'view'             => array( 'type' => 'string', 'enum' => array( 'default', 'stacked', 'framed' ), 'description' => __( 'Icon view mode.', 'elementor-mcp' ) ),
				'shape'            => array( 'type' => 'string', 'enum' => array( 'circle', 'square' ), 'description' => __( 'Icon shape.', 'elementor-mcp' ) ),
				'position'         => array( 'type' => 'string', 'enum' => array( 'top', 'left', 'right' ), 'description' => __( 'Icon position relative to content.', 'elementor-mcp' ) ),
				'title_size'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: h3.', 'elementor-mcp' ) ),
				'link'             => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'elementor-mcp' ) ),
				// Colors.
				'title_color'      => array( 'type' => 'string', 'description' => __( 'Title color (hex/rgba).', 'elementor-mcp' ) ),
				'description_color' => array( 'type' => 'string', 'description' => __( 'Description color (hex/rgba).', 'elementor-mcp' ) ),
				'primary_color'    => array( 'type' => 'string', 'description' => __( 'Icon primary color.', 'elementor-mcp' ) ),
				'secondary_color'  => array( 'type' => 'string', 'description' => __( 'Icon secondary/background color.', 'elementor-mcp' ) ),
				// Hover.
				'hover_primary_color'   => array( 'type' => 'string', 'description' => __( 'Hover icon color.', 'elementor-mcp' ) ),
				'hover_secondary_color' => array( 'type' => 'string', 'description' => __( 'Hover icon background color.', 'elementor-mcp' ) ),
				'hover_animation'       => array( 'type' => 'string', 'description' => __( 'Hover animation.', 'elementor-mcp' ) ),
				// Spacing.
				'icon_space'       => array( 'type' => 'object', 'description' => __( 'Space between icon and content: {size, unit}.', 'elementor-mcp' ) ),
				'icon_size'        => array( 'type' => 'object', 'description' => __( 'Icon size: {size, unit}.', 'elementor-mcp' ) ),
				'title_bottom_space' => array( 'type' => 'object', 'description' => __( 'Space below title: {size, unit}.', 'elementor-mcp' ) ),
				// Title typography.
				'title_typography_typography'     => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable title typography.', 'elementor-mcp' ) ),
				'title_typography_font_family'    => array( 'type' => 'string', 'description' => __( 'Title font family.', 'elementor-mcp' ) ),
				'title_typography_font_size'      => array( 'type' => 'object', 'description' => __( 'Title font size: {size, unit}.', 'elementor-mcp' ) ),
				'title_typography_font_weight'    => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Title font weight.', 'elementor-mcp' ) ),
				// Description typography.
				'description_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable description typography.', 'elementor-mcp' ) ),
				'description_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Description font family.', 'elementor-mcp' ) ),
				'description_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Description font size: {size, unit}.', 'elementor-mcp' ) ),
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
			__( 'Add Accordion', 'elementor-mcp' ),
			__( 'Adds an accordion widget. Supports title/content colors, background, border, typography (set title_typography_typography=custom), spacing, icon color, and FAQ schema. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'tabs'                 => array(
					'type'        => 'array',
					'description' => __( 'Array of accordion items with tab_title and tab_content.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'tab_title'   => array( 'type' => 'string' ),
							'tab_content' => array( 'type' => 'string' ),
						),
					),
				),
				'selected_icon'        => array( 'type' => 'object', 'description' => __( 'Icon when collapsed. Default: fas fa-plus.', 'elementor-mcp' ) ),
				'selected_active_icon' => array( 'type' => 'object', 'description' => __( 'Icon when expanded. Default: fas fa-minus.', 'elementor-mcp' ) ),
				'title_html_tag'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), 'description' => __( 'Title HTML tag. Default: div.', 'elementor-mcp' ) ),
				'faq_schema'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable FAQ schema markup.', 'elementor-mcp' ) ),
				// Style - Title.
				'title_color'          => array( 'type' => 'string', 'description' => __( 'Title text color.', 'elementor-mcp' ) ),
				'title_background'     => array( 'type' => 'string', 'description' => __( 'Title background color.', 'elementor-mcp' ) ),
				'tab_active_color'     => array( 'type' => 'string', 'description' => __( 'Active title text color.', 'elementor-mcp' ) ),
				'tab_active_background' => array( 'type' => 'string', 'description' => __( 'Active title background color.', 'elementor-mcp' ) ),
				'title_padding'        => array( 'type' => 'object', 'description' => __( 'Title padding: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				// Style - Icon.
				'icon_color'           => array( 'type' => 'string', 'description' => __( 'Icon color.', 'elementor-mcp' ) ),
				'icon_active_color'    => array( 'type' => 'string', 'description' => __( 'Active icon color.', 'elementor-mcp' ) ),
				'icon_space'           => array( 'type' => 'object', 'description' => __( 'Space between icon and title: {size, unit}.', 'elementor-mcp' ) ),
				// Style - Content.
				'content_color'        => array( 'type' => 'string', 'description' => __( 'Content text color.', 'elementor-mcp' ) ),
				'content_background_color' => array( 'type' => 'string', 'description' => __( 'Content background color.', 'elementor-mcp' ) ),
				'content_padding'      => array( 'type' => 'object', 'description' => __( 'Content padding: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				// Border.
				'border_width'         => array( 'type' => 'object', 'description' => __( 'Item border width: {size, unit}.', 'elementor-mcp' ) ),
				'border_color'         => array( 'type' => 'string', 'description' => __( 'Item border color.', 'elementor-mcp' ) ),
				// Title typography.
				'title_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable title typography.', 'elementor-mcp' ) ),
				'title_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Title font family.', 'elementor-mcp' ) ),
				'title_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Title font size: {size, unit}.', 'elementor-mcp' ) ),
				'title_typography_font_weight' => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Title font weight.', 'elementor-mcp' ) ),
				// Content typography.
				'content_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable content typography.', 'elementor-mcp' ) ),
				'content_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Content font family.', 'elementor-mcp' ) ),
				'content_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Content font size: {size, unit}.', 'elementor-mcp' ) ),
			),
			array( 'tabs' ),
			'accordion',
			array( 'title_html_tag' => 'div' )
		);
	}

	private function register_add_alert(): void {
		$this->register_convenience_tool(
			'add-alert',
			__( 'Add Alert', 'elementor-mcp' ),
			__( 'Adds an alert/notice widget with type, title, and description.', 'elementor-mcp' ),
			array(
				'alert_type'        => array( 'type' => 'string', 'enum' => array( 'info', 'success', 'warning', 'danger' ), 'description' => __( 'Alert type. Default: info.', 'elementor-mcp' ) ),
				'alert_title'       => array( 'type' => 'string', 'description' => __( 'Alert title.', 'elementor-mcp' ) ),
				'alert_description' => array( 'type' => 'string', 'description' => __( 'Alert description/content.', 'elementor-mcp' ) ),
				'show_dismiss'      => array( 'type' => 'string', 'enum' => array( 'show', '' ), 'description' => __( 'Show dismiss button. Default: show.', 'elementor-mcp' ) ),
			),
			array( 'alert_title' ),
			'alert',
			array( 'alert_type' => 'info', 'show_dismiss' => 'show' )
		);
	}

	private function register_add_counter(): void {
		$this->register_convenience_tool(
			'add-counter',
			__( 'Add Counter', 'elementor-mcp' ),
			__( 'Adds an animated counter widget that counts up to a number.', 'elementor-mcp' ),
			array(
				'starting_number'    => array( 'type' => 'integer', 'description' => __( 'Start value. Default: 0.', 'elementor-mcp' ) ),
				'ending_number'      => array( 'type' => 'integer', 'description' => __( 'End value. Default: 100.', 'elementor-mcp' ) ),
				'prefix'             => array( 'type' => 'string', 'description' => __( 'Text before number (e.g. "$").', 'elementor-mcp' ) ),
				'suffix'             => array( 'type' => 'string', 'description' => __( 'Text after number (e.g. "%", "+").', 'elementor-mcp' ) ),
				'duration'           => array( 'type' => 'integer', 'description' => __( 'Animation duration in ms. Default: 2000.', 'elementor-mcp' ) ),
				'thousand_separator' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show thousand separators.', 'elementor-mcp' ) ),
				'title'              => array( 'type' => 'string', 'description' => __( 'Counter label/title.', 'elementor-mcp' ) ),
			),
			array( 'ending_number' ),
			'counter',
			array( 'starting_number' => 0, 'ending_number' => 100, 'duration' => 2000 )
		);
	}

	private function register_add_google_maps(): void {
		$this->register_convenience_tool(
			'add-google-maps',
			__( 'Add Google Maps', 'elementor-mcp' ),
			__( 'Adds an embedded Google Maps widget with address, zoom, and height.', 'elementor-mcp' ),
			array(
				'address' => array( 'type' => 'string', 'description' => __( 'Location address or search query.', 'elementor-mcp' ) ),
				'zoom'    => array( 'type' => 'object', 'description' => __( 'Zoom level: { "size": 10, "unit": "px" }. Range 1-20.', 'elementor-mcp' ) ),
				'height'  => array( 'type' => 'object', 'description' => __( 'Map height: { "size": 300, "unit": "px" }.', 'elementor-mcp' ) ),
			),
			array( 'address' ),
			'google_maps',
			array( 'zoom' => array( 'size' => 10, 'unit' => 'px' ) )
		);
	}

	private function register_add_icon_list(): void {
		$this->register_convenience_tool(
			'add-icon-list',
			__( 'Add Icon List', 'elementor-mcp' ),
			__( 'Adds a list widget with icons and text. Great for feature lists, checklists, and contact info.', 'elementor-mcp' ),
			array(
				'icon_list' => array(
					'type'        => 'array',
					'description' => __( 'Array of list items with text, selected_icon, and optional link.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'text'          => array( 'type' => 'string' ),
							'selected_icon' => array( 'type' => 'object' ),
							'link'          => array( 'type' => 'object' ),
						),
					),
				),
				'view'      => array( 'type' => 'string', 'enum' => array( 'traditional', 'inline' ), 'description' => __( 'Layout: traditional (vertical) or inline. Default: traditional.', 'elementor-mcp' ) ),
			),
			array( 'icon_list' ),
			'icon-list',
			array( 'view' => 'traditional' )
		);
	}

	private function register_add_image_box(): void {
		$this->register_convenience_tool(
			'add-image-box',
			__( 'Add Image Box', 'elementor-mcp' ),
			__( 'Adds an image box widget with image, title, and description. Great for service cards.', 'elementor-mcp' ),
			array(
				'image'            => array( 'type' => 'object', 'description' => __( 'Image object with url and optional id.', 'elementor-mcp' ) ),
				'title_text'       => array( 'type' => 'string', 'description' => __( 'Box title.', 'elementor-mcp' ) ),
				'description_text' => array( 'type' => 'string', 'description' => __( 'Box description.', 'elementor-mcp' ) ),
				'link'             => array( 'type' => 'object', 'description' => __( 'Link object with url key.', 'elementor-mcp' ) ),
				'title_size'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: h3.', 'elementor-mcp' ) ),
			),
			array( 'title_text' ),
			'image-box',
			array( 'title_size' => 'h3' )
		);
	}

	private function register_add_image_carousel(): void {
		$this->register_convenience_tool(
			'add-image-carousel',
			__( 'Add Image Carousel', 'elementor-mcp' ),
			__( 'Adds a rotating image carousel/slider widget.', 'elementor-mcp' ),
			array(
				'carousel'       => array(
					'type'        => 'array',
					'description' => __( 'Array of image objects with url and optional id.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'url' => array( 'type' => 'string' ),
							'id'  => array( 'type' => 'integer' ),
						),
					),
				),
				'slides_to_show' => array( 'type' => 'string', 'enum' => array( '1', '2', '3', '4', '5', '6', '7', '8', '9', '10' ), 'description' => __( 'Number of slides visible.', 'elementor-mcp' ) ),
				'navigation'     => array( 'type' => 'string', 'enum' => array( 'both', 'arrows', 'dots', 'none' ), 'description' => __( 'Navigation type. Default: both.', 'elementor-mcp' ) ),
				'autoplay'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay slides. Default: yes.', 'elementor-mcp' ) ),
				'autoplay_speed' => array( 'type' => 'integer', 'description' => __( 'Autoplay interval in ms. Default: 5000.', 'elementor-mcp' ) ),
				'infinite'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Infinite loop. Default: yes.', 'elementor-mcp' ) ),
			),
			array( 'carousel' ),
			'image-carousel',
			array( 'navigation' => 'both', 'autoplay' => 'yes', 'infinite' => 'yes', 'autoplay_speed' => 5000 )
		);
	}

	private function register_add_progress(): void {
		$this->register_convenience_tool(
			'add-progress',
			__( 'Add Progress Bar', 'elementor-mcp' ),
			__( 'Adds an animated progress bar widget with label and percentage.', 'elementor-mcp' ),
			array(
				'title'              => array( 'type' => 'string', 'description' => __( 'Progress bar label.', 'elementor-mcp' ) ),
				'progress_type'      => array( 'type' => 'string', 'enum' => array( '', 'info', 'success', 'warning', 'danger' ), 'description' => __( 'Color preset type.', 'elementor-mcp' ) ),
				'percent'            => array( 'type' => 'object', 'description' => __( 'Progress percentage: { "size": 50, "unit": "%" }.', 'elementor-mcp' ) ),
				'display_percentage' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show percentage value. Default: yes.', 'elementor-mcp' ) ),
				'inner_text'         => array( 'type' => 'string', 'description' => __( 'Text inside the progress bar.', 'elementor-mcp' ) ),
			),
			array(),
			'progress',
			array( 'percent' => array( 'size' => 50, 'unit' => '%' ), 'display_percentage' => 'yes' )
		);
	}

	private function register_add_social_icons(): void {
		$this->register_convenience_tool(
			'add-social-icons',
			__( 'Add Social Icons', 'elementor-mcp' ),
			__( 'Adds social media icon links. Great for headers and footers.', 'elementor-mcp' ),
			array(
				'social_icon_list' => array(
					'type'        => 'array',
					'description' => __( 'Array of social items with social_icon and link.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'social_icon' => array( 'type' => 'object', 'description' => __( 'Icon: { "value": "fab fa-facebook", "library": "fa-brands" }.', 'elementor-mcp' ) ),
							'link'        => array( 'type' => 'object', 'description' => __( 'URL object with url key.', 'elementor-mcp' ) ),
						),
					),
				),
				'shape'            => array( 'type' => 'string', 'enum' => array( 'rounded', 'square', 'circle' ), 'description' => __( 'Icon shape. Default: rounded.', 'elementor-mcp' ) ),
				'columns'          => array( 'type' => 'integer', 'description' => __( 'Grid columns. 0 = auto.', 'elementor-mcp' ) ),
				'align'            => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Alignment. Default: center.', 'elementor-mcp' ) ),
			),
			array( 'social_icon_list' ),
			'social-icons',
			array( 'shape' => 'rounded' )
		);
	}

	private function register_add_star_rating(): void {
		$this->register_convenience_tool(
			'add-star-rating',
			__( 'Add Star Rating', 'elementor-mcp' ),
			__( 'Adds a star rating display widget.', 'elementor-mcp' ),
			array(
				'rating_scale' => array( 'type' => 'string', 'enum' => array( '5', '10' ), 'description' => __( 'Rating scale. Default: 5.', 'elementor-mcp' ) ),
				'rating'       => array( 'type' => 'object', 'description' => __( 'Rating value: { "size": 5, "unit": "px" }. Step: 0.1.', 'elementor-mcp' ) ),
				'star_style'   => array( 'type' => 'string', 'enum' => array( 'star_fontawesome', 'star_unicode' ), 'description' => __( 'Star icon style.', 'elementor-mcp' ) ),
				'title'        => array( 'type' => 'string', 'description' => __( 'Optional rating title.', 'elementor-mcp' ) ),
			),
			array(),
			'star-rating',
			array( 'rating_scale' => '5', 'rating' => array( 'size' => 5, 'unit' => 'px' ) )
		);
	}

	private function register_add_tabs(): void {
		$this->register_convenience_tool(
			'add-tabs',
			__( 'Add Tabs', 'elementor-mcp' ),
			__( 'Adds a tabbed content widget with horizontal or vertical layout.', 'elementor-mcp' ),
			array(
				'tabs' => array(
					'type'        => 'array',
					'description' => __( 'Array of tab items with tab_title and tab_content.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'tab_title'   => array( 'type' => 'string' ),
							'tab_content' => array( 'type' => 'string' ),
						),
					),
				),
				'type' => array( 'type' => 'string', 'enum' => array( 'horizontal', 'vertical' ), 'description' => __( 'Tab layout direction. Default: horizontal.', 'elementor-mcp' ) ),
			),
			array( 'tabs' ),
			'tabs',
			array( 'type' => 'horizontal' )
		);
	}

	private function register_add_testimonial(): void {
		$this->register_convenience_tool(
			'add-testimonial',
			__( 'Add Testimonial', 'elementor-mcp' ),
			__( 'Adds a testimonial widget with quote, author name, job title, and image.', 'elementor-mcp' ),
			array(
				'testimonial_content'        => array( 'type' => 'string', 'description' => __( 'Testimonial/quote text.', 'elementor-mcp' ) ),
				'testimonial_image'          => array( 'type' => 'object', 'description' => __( 'Author image object with url and optional id.', 'elementor-mcp' ) ),
				'testimonial_name'           => array( 'type' => 'string', 'description' => __( 'Author name.', 'elementor-mcp' ) ),
				'testimonial_job'            => array( 'type' => 'string', 'description' => __( 'Author job title/role.', 'elementor-mcp' ) ),
				'testimonial_image_position' => array( 'type' => 'string', 'enum' => array( 'aside', 'top' ), 'description' => __( 'Image position. Default: aside.', 'elementor-mcp' ) ),
			),
			array( 'testimonial_content', 'testimonial_name' ),
			'testimonial',
			array( 'testimonial_image_position' => 'aside' )
		);
	}

	private function register_add_toggle(): void {
		$this->register_convenience_tool(
			'add-toggle',
			__( 'Add Toggle', 'elementor-mcp' ),
			__( 'Adds a toggle widget (multiple items can be open). Supports title/content colors, background, border, typography (set title_typography_typography=custom), spacing, and icon color. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'tabs'                 => array(
					'type'        => 'array',
					'description' => __( 'Array of toggle items with tab_title and tab_content.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'tab_title'   => array( 'type' => 'string' ),
							'tab_content' => array( 'type' => 'string' ),
						),
					),
				),
				'selected_icon'        => array( 'type' => 'object', 'description' => __( 'Icon when collapsed.', 'elementor-mcp' ) ),
				'selected_active_icon' => array( 'type' => 'object', 'description' => __( 'Icon when expanded.', 'elementor-mcp' ) ),
				'title_html_tag'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div' ), 'description' => __( 'Title HTML tag. Default: div.', 'elementor-mcp' ) ),
				// Style - Title.
				'title_color'          => array( 'type' => 'string', 'description' => __( 'Title text color.', 'elementor-mcp' ) ),
				'title_background'     => array( 'type' => 'string', 'description' => __( 'Title background color.', 'elementor-mcp' ) ),
				'tab_active_color'     => array( 'type' => 'string', 'description' => __( 'Active title text color.', 'elementor-mcp' ) ),
				'tab_active_background' => array( 'type' => 'string', 'description' => __( 'Active title background color.', 'elementor-mcp' ) ),
				'title_padding'        => array( 'type' => 'object', 'description' => __( 'Title padding: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				// Style - Icon.
				'icon_color'           => array( 'type' => 'string', 'description' => __( 'Icon color.', 'elementor-mcp' ) ),
				'icon_active_color'    => array( 'type' => 'string', 'description' => __( 'Active icon color.', 'elementor-mcp' ) ),
				'icon_space'           => array( 'type' => 'object', 'description' => __( 'Space between icon and title: {size, unit}.', 'elementor-mcp' ) ),
				// Style - Content.
				'content_color'        => array( 'type' => 'string', 'description' => __( 'Content text color.', 'elementor-mcp' ) ),
				'content_background_color' => array( 'type' => 'string', 'description' => __( 'Content background color.', 'elementor-mcp' ) ),
				'content_padding'      => array( 'type' => 'object', 'description' => __( 'Content padding: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				// Border.
				'border_width'         => array( 'type' => 'object', 'description' => __( 'Item border width: {size, unit}.', 'elementor-mcp' ) ),
				'border_color'         => array( 'type' => 'string', 'description' => __( 'Item border color.', 'elementor-mcp' ) ),
				// Title typography.
				'title_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable title typography.', 'elementor-mcp' ) ),
				'title_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Title font family.', 'elementor-mcp' ) ),
				'title_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Title font size: {size, unit}.', 'elementor-mcp' ) ),
				'title_typography_font_weight' => array( 'type' => 'string', 'enum' => array( '100', '200', '300', '400', '500', '600', '700', '800', '900', 'normal', 'bold' ), 'description' => __( 'Title font weight.', 'elementor-mcp' ) ),
				// Content typography.
				'content_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable content typography.', 'elementor-mcp' ) ),
				'content_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Content font family.', 'elementor-mcp' ) ),
				'content_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Content font size: {size, unit}.', 'elementor-mcp' ) ),
			),
			array( 'tabs' ),
			'toggle',
			array( 'title_html_tag' => 'div' )
		);
	}

	private function register_add_html(): void {
		$this->register_convenience_tool(
			'add-html',
			__( 'Add HTML', 'elementor-mcp' ),
			__( 'Adds a custom HTML code widget.', 'elementor-mcp' ),
			array(
				'html' => array( 'type' => 'string', 'description' => __( 'Custom HTML/code content.', 'elementor-mcp' ) ),
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
			__( 'Add Form (Pro)', 'elementor-mcp' ),
			__( 'Adds an Elementor Pro form. Supports field types (text, email, textarea, url, tel, select, radio, checkbox, number, date, time, upload, acceptance, password, html, hidden, step), submit button styling, submit actions (email, redirect, webhook, mailchimp, drip, activecampaign, getresponse, convertkit, mailerlite, slack), email settings, redirect, and success/error messages. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'form_name'     => array( 'type' => 'string', 'description' => __( 'Form name.', 'elementor-mcp' ) ),
				'form_fields'   => array(
					'type'        => 'array',
					'description' => __( 'Array of field definitions.', 'elementor-mcp' ),
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
				'button_text'   => array( 'type' => 'string', 'description' => __( 'Submit button text.', 'elementor-mcp' ) ),
				'button_size'   => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Submit button size.', 'elementor-mcp' ) ),
				'button_width'  => array( 'type' => 'string', 'enum' => array( '', '100' ), 'description' => __( 'Full-width button. Set to "100" for full width.', 'elementor-mcp' ) ),
				'button_align'  => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'stretch' ), 'description' => __( 'Button alignment.', 'elementor-mcp' ) ),
				'selected_button_icon' => array( 'type' => 'object', 'description' => __( 'Button icon: {value, library}.', 'elementor-mcp' ) ),
				'button_icon_align'    => array( 'type' => 'string', 'enum' => array( 'left', 'right' ), 'description' => __( 'Button icon position.', 'elementor-mcp' ) ),
				// Submit actions.
				'submit_actions' => array( 'type' => 'array', 'description' => __( 'Actions after submit: ["email","redirect","webhook"]. Default: ["email"].', 'elementor-mcp' ) ),
				// Email settings.
				'email_to'      => array( 'type' => 'string', 'description' => __( 'Email recipient.', 'elementor-mcp' ) ),
				'email_subject' => array( 'type' => 'string', 'description' => __( 'Email subject.', 'elementor-mcp' ) ),
				'email_from'    => array( 'type' => 'string', 'description' => __( 'Email from address.', 'elementor-mcp' ) ),
				'email_from_name' => array( 'type' => 'string', 'description' => __( 'Email from name.', 'elementor-mcp' ) ),
				'email_reply_to'  => array( 'type' => 'string', 'description' => __( 'Reply-to email (use field shortcode like [field id="email"]).', 'elementor-mcp' ) ),
				'email_content_type' => array( 'type' => 'string', 'enum' => array( 'html', 'plain' ), 'description' => __( 'Email content type. Default: html.', 'elementor-mcp' ) ),
				// Redirect.
				'redirect_to'   => array( 'type' => 'string', 'description' => __( 'Redirect URL after submit (requires "redirect" in submit_actions).', 'elementor-mcp' ) ),
				// Webhook.
				'webhooks'      => array( 'type' => 'string', 'description' => __( 'Webhook URL (requires "webhook" in submit_actions).', 'elementor-mcp' ) ),
				// Messages.
				'success_message' => array( 'type' => 'string', 'description' => __( 'Success message after submit.', 'elementor-mcp' ) ),
				'error_message'   => array( 'type' => 'string', 'description' => __( 'Error message on failure.', 'elementor-mcp' ) ),
				'required_field_message' => array( 'type' => 'string', 'description' => __( 'Required field validation message.', 'elementor-mcp' ) ),
				// Style.
				'input_size'    => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Input field size.', 'elementor-mcp' ) ),
				'show_labels'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show field labels. Default: yes.', 'elementor-mcp' ) ),
				'mark_required' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show asterisk on required fields. Default: yes.', 'elementor-mcp' ) ),
				// Button colors.
				'button_background_color'       => array( 'type' => 'string', 'description' => __( 'Button background color.', 'elementor-mcp' ) ),
				'button_text_color'             => array( 'type' => 'string', 'description' => __( 'Button text color.', 'elementor-mcp' ) ),
				'button_hover_background_color' => array( 'type' => 'string', 'description' => __( 'Button hover background color.', 'elementor-mcp' ) ),
				'button_hover_color'            => array( 'type' => 'string', 'description' => __( 'Button hover text color.', 'elementor-mcp' ) ),
				// Button typography.
				'button_typography_typography'   => array( 'type' => 'string', 'description' => __( 'Set to "custom" to enable button typography.', 'elementor-mcp' ) ),
				'button_typography_font_family'  => array( 'type' => 'string', 'description' => __( 'Button font family.', 'elementor-mcp' ) ),
				'button_typography_font_size'    => array( 'type' => 'object', 'description' => __( 'Button font size: {size, unit}.', 'elementor-mcp' ) ),
				'button_typography_font_weight'  => array( 'type' => 'string', 'description' => __( 'Button font weight.', 'elementor-mcp' ) ),
			),
			array( 'form_name' ),
			'form',
			array( 'button_text' => 'Send', 'submit_actions' => array( 'email' ) )
		);
	}

	private function register_add_posts_grid(): void {
		$this->register_convenience_tool(
			'add-posts-grid',
			__( 'Add Posts Grid (Pro)', 'elementor-mcp' ),
			__( 'Adds an Elementor Pro posts grid widget to display a grid of posts.', 'elementor-mcp' ),
			array(
				'posts_post_type' => array( 'type' => 'string', 'enum' => array( 'post', 'page', 'any' ), 'description' => __( 'Post type to query.', 'elementor-mcp' ) ),
				'posts_per_page'  => array( 'type' => 'integer', 'description' => __( 'Number of posts to show.', 'elementor-mcp' ) ),
				'columns'         => array( 'type' => 'integer', 'description' => __( 'Number of grid columns.', 'elementor-mcp' ) ),
				'pagination_type' => array( 'type' => 'string', 'enum' => array( '', 'numbers', 'prev_next', 'numbers_and_prev_next', 'load_more_on_click' ), 'description' => __( 'Pagination type.', 'elementor-mcp' ) ),
			),
			array(),
			'posts',
			array( 'posts_post_type' => 'post', 'posts_per_page' => 6, 'columns' => 3 )
		);
	}

	private function register_add_countdown(): void {
		$this->register_convenience_tool(
			'add-countdown',
			__( 'Add Countdown (Pro)', 'elementor-mcp' ),
			__( 'Adds a countdown timer. Supports due_date or evergreen mode, custom labels, expire actions (hide/redirect/message), and digit/label colors and typography. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'countdown_type'         => array( 'type' => 'string', 'enum' => array( 'due_date', 'evergreen' ), 'description' => __( 'Countdown mode.', 'elementor-mcp' ) ),
				'due_date'               => array( 'type' => 'string', 'description' => __( 'Due date in Y-m-d H:i format.', 'elementor-mcp' ) ),
				// Evergreen.
				'evergreen_counter_hours'   => array( 'type' => 'integer', 'description' => __( 'Evergreen hours.', 'elementor-mcp' ) ),
				'evergreen_counter_minutes' => array( 'type' => 'integer', 'description' => __( 'Evergreen minutes.', 'elementor-mcp' ) ),
				// Visibility.
				'show_days'              => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show days.', 'elementor-mcp' ) ),
				'show_hours'             => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show hours.', 'elementor-mcp' ) ),
				'show_minutes'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show minutes.', 'elementor-mcp' ) ),
				'show_seconds'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show seconds.', 'elementor-mcp' ) ),
				// Labels.
				'show_labels'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show labels. Default: yes.', 'elementor-mcp' ) ),
				'custom_labels'          => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Use custom label text.', 'elementor-mcp' ) ),
				'label_days'             => array( 'type' => 'string', 'description' => __( 'Custom days label.', 'elementor-mcp' ) ),
				'label_hours'            => array( 'type' => 'string', 'description' => __( 'Custom hours label.', 'elementor-mcp' ) ),
				'label_minutes'          => array( 'type' => 'string', 'description' => __( 'Custom minutes label.', 'elementor-mcp' ) ),
				'label_seconds'          => array( 'type' => 'string', 'description' => __( 'Custom seconds label.', 'elementor-mcp' ) ),
				// Expire actions.
				'expire_actions'         => array( 'type' => 'array', 'description' => __( 'Actions on expiry: ["hide","redirect","message"].', 'elementor-mcp' ) ),
				'message_after_expire'   => array( 'type' => 'string', 'description' => __( 'Message to show after expire.', 'elementor-mcp' ) ),
				'expire_redirect_url'    => array( 'type' => 'string', 'description' => __( 'Redirect URL after expire.', 'elementor-mcp' ) ),
				// Style - Digits.
				'digits_color'           => array( 'type' => 'string', 'description' => __( 'Digit text color.', 'elementor-mcp' ) ),
				'digits_background_color' => array( 'type' => 'string', 'description' => __( 'Digit background color.', 'elementor-mcp' ) ),
				// Style - Labels.
				'label_color'            => array( 'type' => 'string', 'description' => __( 'Label text color.', 'elementor-mcp' ) ),
				// Typography.
				'digits_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for digit typography.', 'elementor-mcp' ) ),
				'digits_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Digit font family.', 'elementor-mcp' ) ),
				'digits_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Digit font size: {size, unit}.', 'elementor-mcp' ) ),
				'label_typography_typography'   => array( 'type' => 'string', 'description' => __( 'Set to "custom" for label typography.', 'elementor-mcp' ) ),
				'label_typography_font_family'  => array( 'type' => 'string', 'description' => __( 'Label font family.', 'elementor-mcp' ) ),
				'label_typography_font_size'    => array( 'type' => 'object', 'description' => __( 'Label font size: {size, unit}.', 'elementor-mcp' ) ),
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
			__( 'Add Price Table (Pro)', 'elementor-mcp' ),
			__( 'Adds a pricing table. Supports 16 currency symbols, sale pricing, ribbon, footer info, button CSS ID, feature icons, and style controls (header/pricing/features/footer/button/ribbon colors and typography). Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'heading'                => array( 'type' => 'string', 'description' => __( 'Plan name/heading.', 'elementor-mcp' ) ),
				'sub_heading'            => array( 'type' => 'string', 'description' => __( 'Sub-heading text.', 'elementor-mcp' ) ),
				'currency_symbol'        => array( 'type' => 'string', 'enum' => array( 'dollar', 'euro', 'baht', 'franc', 'krona', 'lira', 'peseta', 'peso', 'pound', 'real', 'ruble', 'rupee', 'indian_rupee', 'shekel', 'won', 'yen', 'custom' ), 'description' => __( 'Currency symbol preset.', 'elementor-mcp' ) ),
				'currency_symbol_custom' => array( 'type' => 'string', 'description' => __( 'Custom currency symbol (when currency_symbol=custom).', 'elementor-mcp' ) ),
				'price'                  => array( 'type' => 'string', 'description' => __( 'Price amount.', 'elementor-mcp' ) ),
				'currency_format'        => array( 'type' => 'string', 'enum' => array( '', ',', '.' ), 'description' => __( 'Price format: comma or dot separator.', 'elementor-mcp' ) ),
				'period'                 => array( 'type' => 'string', 'description' => __( 'Billing period (e.g. "/month").', 'elementor-mcp' ) ),
				// Sale.
				'sale'                   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable sale pricing.', 'elementor-mcp' ) ),
				'original_price'         => array( 'type' => 'string', 'description' => __( 'Original price (shown crossed out when sale=yes).', 'elementor-mcp' ) ),
				// Features.
				'features_list'          => array(
					'type'        => 'array',
					'description' => __( 'Feature list. Each item: {item_text, selected_item_icon, item_icon_color}.', 'elementor-mcp' ),
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
				'button_text'            => array( 'type' => 'string', 'description' => __( 'CTA button text.', 'elementor-mcp' ) ),
				'link'                   => array( 'type' => 'object', 'description' => __( 'Button link: {url, is_external, nofollow}.', 'elementor-mcp' ) ),
				'button_css_id'          => array( 'type' => 'string', 'description' => __( 'Button CSS ID for tracking.', 'elementor-mcp' ) ),
				'button_size'            => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size.', 'elementor-mcp' ) ),
				// Footer.
				'footer_additional_info' => array( 'type' => 'string', 'description' => __( 'Footer text below button (e.g. "30-day money back").', 'elementor-mcp' ) ),
				// Ribbon.
				'show_ribbon'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show ribbon/badge.', 'elementor-mcp' ) ),
				'ribbon_title'           => array( 'type' => 'string', 'description' => __( 'Ribbon text (e.g. "Popular", "Best Value").', 'elementor-mcp' ) ),
				'ribbon_horizontal_position' => array( 'type' => 'string', 'enum' => array( 'left', 'right' ), 'description' => __( 'Ribbon position.', 'elementor-mcp' ) ),
				// Style - Header.
				'header_bg_color'        => array( 'type' => 'string', 'description' => __( 'Header background color.', 'elementor-mcp' ) ),
				'heading_color'          => array( 'type' => 'string', 'description' => __( 'Heading text color.', 'elementor-mcp' ) ),
				'sub_heading_color'      => array( 'type' => 'string', 'description' => __( 'Sub-heading text color.', 'elementor-mcp' ) ),
				// Style - Pricing.
				'pricing_element_bg_color' => array( 'type' => 'string', 'description' => __( 'Pricing area background color.', 'elementor-mcp' ) ),
				'price_color'            => array( 'type' => 'string', 'description' => __( 'Price text color.', 'elementor-mcp' ) ),
				// Style - Button.
				'button_background_color'       => array( 'type' => 'string', 'description' => __( 'Button background color.', 'elementor-mcp' ) ),
				'button_text_color'             => array( 'type' => 'string', 'description' => __( 'Button text color.', 'elementor-mcp' ) ),
				'button_hover_background_color' => array( 'type' => 'string', 'description' => __( 'Button hover background color.', 'elementor-mcp' ) ),
				'button_hover_color'            => array( 'type' => 'string', 'description' => __( 'Button hover text color.', 'elementor-mcp' ) ),
				// Style - Ribbon.
				'ribbon_bg_color'        => array( 'type' => 'string', 'description' => __( 'Ribbon background color.', 'elementor-mcp' ) ),
				'ribbon_text_color'      => array( 'type' => 'string', 'description' => __( 'Ribbon text color.', 'elementor-mcp' ) ),
			),
			array( 'heading', 'price' ),
			'price-table',
			array( 'currency_symbol' => 'dollar', 'button_text' => 'Get Started' )
		);
	}

	private function register_add_flip_box(): void {
		$this->register_convenience_tool(
			'add-flip-box',
			__( 'Add Flip Box (Pro)', 'elementor-mcp' ),
			__( 'Adds a flip box with front/back sides. Supports icon/image graphics, flip effects (flip/slide/push/zoom/fade), height, front/back background colors, title/description colors and typography. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'title_text_a'       => array( 'type' => 'string', 'description' => __( 'Front side title.', 'elementor-mcp' ) ),
				'description_text_a' => array( 'type' => 'string', 'description' => __( 'Front side description.', 'elementor-mcp' ) ),
				'title_text_b'       => array( 'type' => 'string', 'description' => __( 'Back side title.', 'elementor-mcp' ) ),
				'description_text_b' => array( 'type' => 'string', 'description' => __( 'Back side description.', 'elementor-mcp' ) ),
				'graphic_element'    => array( 'type' => 'string', 'enum' => array( 'none', 'image', 'icon' ), 'description' => __( 'Front graphic type.', 'elementor-mcp' ) ),
				'selected_icon'      => array( 'type' => 'object', 'description' => __( 'Front icon: {value, library}.', 'elementor-mcp' ) ),
				'image'              => array( 'type' => 'object', 'description' => __( 'Front image: {url, id}.', 'elementor-mcp' ) ),
				'graphic_element_b'  => array( 'type' => 'string', 'enum' => array( 'none', 'image', 'icon' ), 'description' => __( 'Back graphic type.', 'elementor-mcp' ) ),
				'selected_icon_b'    => array( 'type' => 'object', 'description' => __( 'Back icon: {value, library}.', 'elementor-mcp' ) ),
				'button_text'        => array( 'type' => 'string', 'description' => __( 'Back button text.', 'elementor-mcp' ) ),
				'link'               => array( 'type' => 'object', 'description' => __( 'Link: {url, is_external, nofollow}.', 'elementor-mcp' ) ),
				'flip_effect'        => array( 'type' => 'string', 'enum' => array( 'flip', 'slide', 'push', 'zoom-in', 'zoom-out', 'fade' ), 'description' => __( 'Flip animation.', 'elementor-mcp' ) ),
				'flip_direction'     => array( 'type' => 'string', 'enum' => array( 'left', 'right', 'up', 'down' ), 'description' => __( 'Flip direction.', 'elementor-mcp' ) ),
				'flip_3d'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable 3D depth effect.', 'elementor-mcp' ) ),
				// Height.
				'height'             => array( 'type' => 'object', 'description' => __( 'Box height: {size, unit}.', 'elementor-mcp' ) ),
				'border_radius'      => array( 'type' => 'object', 'description' => __( 'Border radius: {size, unit}.', 'elementor-mcp' ) ),
				// Front style.
				'background_color_a' => array( 'type' => 'string', 'description' => __( 'Front background color.', 'elementor-mcp' ) ),
				'title_color_a'      => array( 'type' => 'string', 'description' => __( 'Front title color.', 'elementor-mcp' ) ),
				'description_color_a' => array( 'type' => 'string', 'description' => __( 'Front description color.', 'elementor-mcp' ) ),
				'icon_color_a'       => array( 'type' => 'string', 'description' => __( 'Front icon color.', 'elementor-mcp' ) ),
				// Back style.
				'background_color_b' => array( 'type' => 'string', 'description' => __( 'Back background color.', 'elementor-mcp' ) ),
				'title_color_b'      => array( 'type' => 'string', 'description' => __( 'Back title color.', 'elementor-mcp' ) ),
				'description_color_b' => array( 'type' => 'string', 'description' => __( 'Back description color.', 'elementor-mcp' ) ),
				// Button style.
				'button_background_color' => array( 'type' => 'string', 'description' => __( 'Back button background color.', 'elementor-mcp' ) ),
				'button_color'       => array( 'type' => 'string', 'description' => __( 'Back button text color.', 'elementor-mcp' ) ),
				'button_size'        => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size.', 'elementor-mcp' ) ),
			),
			array( 'title_text_a' ),
			'flip-box',
			array( 'flip_effect' => 'flip', 'flip_direction' => 'left' )
		);
	}

	private function register_add_animated_headline(): void {
		$this->register_convenience_tool(
			'add-animated-headline',
			__( 'Add Animated Headline (Pro)', 'elementor-mcp' ),
			__( 'Adds an Elementor Pro animated headline with highlight or rotating text effects.', 'elementor-mcp' ),
			array(
				'headline_style'   => array( 'type' => 'string', 'enum' => array( 'highlight', 'rotate' ), 'description' => __( 'Headline animation style.', 'elementor-mcp' ) ),
				'animation_type'   => array( 'type' => 'string', 'enum' => array( 'typing', 'clip', 'flip', 'swirl', 'blinds', 'drop-in', 'wave', 'slide', 'slide-down' ), 'description' => __( 'Rotation animation type.', 'elementor-mcp' ) ),
				'marker'           => array( 'type' => 'string', 'enum' => array( 'circle', 'curly', 'underline', 'double', 'double_underline', 'underline_zigzag', 'diagonal', 'strikethrough', 'x' ), 'description' => __( 'Highlight marker style.', 'elementor-mcp' ) ),
				'before_text'      => array( 'type' => 'string', 'description' => __( 'Text before animated portion.', 'elementor-mcp' ) ),
				'highlighted_text' => array( 'type' => 'string', 'description' => __( 'Highlighted text (for highlight style).', 'elementor-mcp' ) ),
				'rotating_text'    => array( 'type' => 'string', 'description' => __( 'Line-separated rotating text entries.', 'elementor-mcp' ) ),
				'after_text'       => array( 'type' => 'string', 'description' => __( 'Text after animated portion.', 'elementor-mcp' ) ),
				'tag'              => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'description' => __( 'HTML heading tag.', 'elementor-mcp' ) ),
			),
			array(),
			'animated-headline',
			array( 'headline_style' => 'highlight', 'tag' => 'h3' )
		);
	}

	private function register_add_call_to_action(): void {
		$this->register_convenience_tool(
			'add-call-to-action',
			__( 'Add Call to Action (Pro)', 'elementor-mcp' ),
			__( 'Adds a call-to-action widget with title, description, button, and optional graphic/ribbon.', 'elementor-mcp' ),
			array(
				'title'           => array( 'type' => 'string', 'description' => __( 'CTA heading text.', 'elementor-mcp' ) ),
				'description'     => array( 'type' => 'string', 'description' => __( 'CTA description text.', 'elementor-mcp' ) ),
				'button'          => array( 'type' => 'string', 'description' => __( 'Button text. Default: Click Here.', 'elementor-mcp' ) ),
				'link'            => array( 'type' => 'object', 'description' => __( 'Button link object with url key.', 'elementor-mcp' ) ),
				'graphic_element' => array( 'type' => 'string', 'enum' => array( 'none', 'image', 'icon' ), 'description' => __( 'Graphic type.', 'elementor-mcp' ) ),
				'graphic_image'   => array( 'type' => 'object', 'description' => __( 'Image object with url and optional id.', 'elementor-mcp' ) ),
				'selected_icon'   => array( 'type' => 'object', 'description' => __( 'Icon object with value and library.', 'elementor-mcp' ) ),
				'ribbon_title'    => array( 'type' => 'string', 'description' => __( 'Optional ribbon/badge text.', 'elementor-mcp' ) ),
				'title_tag'       => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: h2.', 'elementor-mcp' ) ),
			),
			array( 'title' ),
			'call-to-action',
			array( 'title_tag' => 'h2', 'button' => 'Click Here' )
		);
	}

	private function register_add_slides(): void {
		$this->register_convenience_tool(
			'add-slides',
			__( 'Add Slides (Pro)', 'elementor-mcp' ),
			__( 'Adds a full-width slides/slider. Supports heading, description, button per slide, background image/color/overlay, Ken Burns, content animation, height, navigation, autoplay, colors, typography. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'slides'           => array(
					'type'        => 'array',
					'description' => __( 'Array of slide items.', 'elementor-mcp' ),
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
							'content_animation'       => array( 'type' => 'string', 'description' => __( 'Content entrance animation (e.g. fadeInUp, zoomIn).', 'elementor-mcp' ) ),
							'custom_css_class'        => array( 'type' => 'string' ),
							'horizontal_position'     => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ) ),
							'vertical_position'       => array( 'type' => 'string', 'enum' => array( 'top', 'middle', 'bottom' ) ),
							'text_align'              => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ) ),
						),
					),
				),
				// Slider options.
				'navigation'       => array( 'type' => 'string', 'enum' => array( 'both', 'arrows', 'dots', 'none' ), 'description' => __( 'Navigation type.', 'elementor-mcp' ) ),
				'autoplay'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay. Default: yes.', 'elementor-mcp' ) ),
				'autoplay_speed'   => array( 'type' => 'integer', 'description' => __( 'Autoplay interval in ms. Default: 5000.', 'elementor-mcp' ) ),
				'pause_on_hover'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Pause autoplay on hover.', 'elementor-mcp' ) ),
				'pause_on_interaction' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Pause autoplay on interaction.', 'elementor-mcp' ) ),
				'infinite'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Infinite loop. Default: yes.', 'elementor-mcp' ) ),
				'transition'       => array( 'type' => 'string', 'enum' => array( 'slide', 'fade' ), 'description' => __( 'Transition effect.', 'elementor-mcp' ) ),
				'transition_speed' => array( 'type' => 'integer', 'description' => __( 'Transition speed in ms.', 'elementor-mcp' ) ),
				// Slider layout.
				'slides_height'    => array( 'type' => 'object', 'description' => __( 'Slider height: {size, unit}. Responsive.', 'elementor-mcp' ) ),
				'content_max_width' => array( 'type' => 'object', 'description' => __( 'Content max width percentage: {size, unit}.', 'elementor-mcp' ) ),
				'slides_padding'   => array( 'type' => 'object', 'description' => __( 'Content padding: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'slides_horizontal_position' => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Default horizontal position.', 'elementor-mcp' ) ),
				'slides_vertical_position'   => array( 'type' => 'string', 'enum' => array( 'top', 'middle', 'bottom' ), 'description' => __( 'Default vertical position.', 'elementor-mcp' ) ),
				'slides_text_align' => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Default text alignment.', 'elementor-mcp' ) ),
				// Style - Heading.
				'heading_spacing'  => array( 'type' => 'object', 'description' => __( 'Heading bottom spacing: {size, unit}.', 'elementor-mcp' ) ),
				'heading_color'    => array( 'type' => 'string', 'description' => __( 'Heading text color.', 'elementor-mcp' ) ),
				'heading_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for heading typography.', 'elementor-mcp' ) ),
				'heading_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Heading font family.', 'elementor-mcp' ) ),
				'heading_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Heading font size: {size, unit}.', 'elementor-mcp' ) ),
				'heading_typography_font_weight' => array( 'type' => 'string', 'description' => __( 'Heading font weight.', 'elementor-mcp' ) ),
				// Style - Description.
				'description_spacing' => array( 'type' => 'object', 'description' => __( 'Description bottom spacing: {size, unit}.', 'elementor-mcp' ) ),
				'description_color' => array( 'type' => 'string', 'description' => __( 'Description text color.', 'elementor-mcp' ) ),
				'description_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for description typography.', 'elementor-mcp' ) ),
				'description_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Description font family.', 'elementor-mcp' ) ),
				'description_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Description font size: {size, unit}.', 'elementor-mcp' ) ),
				// Style - Button.
				'button_size'      => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size.', 'elementor-mcp' ) ),
				'button_color'     => array( 'type' => 'string', 'description' => __( 'Button text color.', 'elementor-mcp' ) ),
				'button_background_color' => array( 'type' => 'string', 'description' => __( 'Button background color.', 'elementor-mcp' ) ),
				'button_border_width' => array( 'type' => 'integer', 'description' => __( 'Button border width in px.', 'elementor-mcp' ) ),
				'button_border_color' => array( 'type' => 'string', 'description' => __( 'Button border color.', 'elementor-mcp' ) ),
				'button_border_radius' => array( 'type' => 'object', 'description' => __( 'Button border radius: {size, unit}.', 'elementor-mcp' ) ),
				'button_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for button typography.', 'elementor-mcp' ) ),
				'button_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Button font family.', 'elementor-mcp' ) ),
				'button_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Button font size: {size, unit}.', 'elementor-mcp' ) ),
				// Style - Navigation.
				'arrows_size'      => array( 'type' => 'object', 'description' => __( 'Arrow size: {size, unit}.', 'elementor-mcp' ) ),
				'arrows_color'     => array( 'type' => 'string', 'description' => __( 'Arrow color.', 'elementor-mcp' ) ),
				'dots_size'        => array( 'type' => 'object', 'description' => __( 'Dot size: {size, unit}.', 'elementor-mcp' ) ),
				'dots_color'       => array( 'type' => 'string', 'description' => __( 'Dot color.', 'elementor-mcp' ) ),
			),
			array( 'slides' ),
			'slides',
			array( 'autoplay' => 'yes', 'autoplay_speed' => 5000, 'infinite' => 'yes' )
		);
	}

	private function register_add_testimonial_carousel(): void {
		$this->register_convenience_tool(
			'add-testimonial-carousel',
			__( 'Add Testimonial Carousel (Pro)', 'elementor-mcp' ),
			__( 'Adds a testimonial carousel. Supports skins (default/bubble), layouts, navigation (arrows/dots), slide spacing, background/text/border colors, image size, content gap, and name/title/content typography. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'slides'          => array(
					'type'        => 'array',
					'description' => __( 'Array of testimonial items with content, image, name, and title.', 'elementor-mcp' ),
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
				'skin'            => array( 'type' => 'string', 'enum' => array( 'default', 'bubble' ), 'description' => __( 'Skin variant. Default: default.', 'elementor-mcp' ) ),
				'layout'          => array( 'type' => 'string', 'enum' => array( 'image_inline', 'image_stacked', 'image_above', 'image_left', 'image_right' ), 'description' => __( 'Layout mode.', 'elementor-mcp' ) ),
				'alignment'       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Content alignment.', 'elementor-mcp' ) ),
				'slides_per_view' => array( 'type' => 'string', 'enum' => array( '1', '2', '3', '4' ), 'description' => __( 'Slides visible at once.', 'elementor-mcp' ) ),
				'autoplay'        => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay. Default: yes.', 'elementor-mcp' ) ),
				'autoplay_speed'  => array( 'type' => 'integer', 'description' => __( 'Autoplay interval in ms.', 'elementor-mcp' ) ),
				// Navigation.
				'navigation'      => array( 'type' => 'string', 'enum' => array( 'both', 'arrows', 'dots', 'none' ), 'description' => __( 'Navigation type.', 'elementor-mcp' ) ),
				'infinite'        => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Infinite loop.', 'elementor-mcp' ) ),
				'speed'           => array( 'type' => 'integer', 'description' => __( 'Transition speed in ms.', 'elementor-mcp' ) ),
				// Slide spacing.
				'space_between'   => array( 'type' => 'object', 'description' => __( 'Space between slides: {size, unit}.', 'elementor-mcp' ) ),
				// Style - Slide.
				'slide_background_color' => array( 'type' => 'string', 'description' => __( 'Slide background color.', 'elementor-mcp' ) ),
				'slide_padding'   => array( 'type' => 'object', 'description' => __( 'Slide padding: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'slide_border_radius' => array( 'type' => 'object', 'description' => __( 'Slide border radius: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'slide_border_border' => array( 'type' => 'string', 'enum' => array( '', 'solid', 'double', 'dotted', 'dashed' ), 'description' => __( 'Slide border style.', 'elementor-mcp' ) ),
				'slide_border_width'  => array( 'type' => 'object', 'description' => __( 'Slide border width.', 'elementor-mcp' ) ),
				'slide_border_color'  => array( 'type' => 'string', 'description' => __( 'Slide border color.', 'elementor-mcp' ) ),
				// Style - Content.
				'content_color'   => array( 'type' => 'string', 'description' => __( 'Content/quote text color.', 'elementor-mcp' ) ),
				'name_color'      => array( 'type' => 'string', 'description' => __( 'Author name color.', 'elementor-mcp' ) ),
				'title_color'     => array( 'type' => 'string', 'description' => __( 'Author title/role color.', 'elementor-mcp' ) ),
				// Style - Image.
				'image_size'      => array( 'type' => 'object', 'description' => __( 'Author image size: {size, unit}.', 'elementor-mcp' ) ),
				'image_gap'       => array( 'type' => 'object', 'description' => __( 'Gap between image and text: {size, unit}.', 'elementor-mcp' ) ),
				'image_border_radius' => array( 'type' => 'object', 'description' => __( 'Image border radius: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				// Typography.
				'content_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for content typography.', 'elementor-mcp' ) ),
				'content_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Content font family.', 'elementor-mcp' ) ),
				'content_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Content font size: {size, unit}.', 'elementor-mcp' ) ),
				'name_typography_typography'     => array( 'type' => 'string', 'description' => __( 'Set to "custom" for name typography.', 'elementor-mcp' ) ),
				'name_typography_font_family'    => array( 'type' => 'string', 'description' => __( 'Name font family.', 'elementor-mcp' ) ),
				'name_typography_font_size'      => array( 'type' => 'object', 'description' => __( 'Name font size: {size, unit}.', 'elementor-mcp' ) ),
				'name_typography_font_weight'    => array( 'type' => 'string', 'description' => __( 'Name font weight.', 'elementor-mcp' ) ),
			),
			array( 'slides' ),
			'testimonial-carousel',
			array( 'skin' => 'default', 'layout' => 'image_inline', 'slides_per_view' => '1', 'autoplay' => 'yes' )
		);
	}

	private function register_add_price_list(): void {
		$this->register_convenience_tool(
			'add-price-list',
			__( 'Add Price List (Pro)', 'elementor-mcp' ),
			__( 'Adds a price list widget for menus, services, or product lists with title, price, and description.', 'elementor-mcp' ),
			array(
				'price_list' => array(
					'type'        => 'array',
					'description' => __( 'Array of list items with title, price, item_description, image, and link.', 'elementor-mcp' ),
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
				'title_tag'  => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: span.', 'elementor-mcp' ) ),
			),
			array( 'price_list' ),
			'price-list',
			array( 'title_tag' => 'span' )
		);
	}

	private function register_add_gallery(): void {
		$this->register_convenience_tool(
			'add-gallery',
			__( 'Add Gallery (Pro)', 'elementor-mcp' ),
			__( 'Adds an advanced gallery. Supports grid/justified/masonry layouts, multiple galleries with filtering, aspect ratio, overlay effects, lightbox, lazy load, image border/radius, and hover opacity/CSS filters. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'gallery'        => array(
					'type'        => 'array',
					'description' => __( 'Array of image objects with id and url.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'  => array( 'type' => 'integer' ),
							'url' => array( 'type' => 'string' ),
						),
					),
				),
				'gallery_layout' => array( 'type' => 'string', 'enum' => array( 'grid', 'justified', 'masonry' ), 'description' => __( 'Gallery layout. Default: grid.', 'elementor-mcp' ) ),
				'columns'        => array( 'type' => 'integer', 'description' => __( 'Number of columns. Default: 4. Responsive: columns_tablet, columns_mobile.', 'elementor-mcp' ) ),
				'gap'            => array( 'type' => 'object', 'description' => __( 'Gap between items: {size, unit}.', 'elementor-mcp' ) ),
				'link_to'        => array( 'type' => 'string', 'enum' => array( 'file', 'custom', 'none' ), 'description' => __( 'Link behavior.', 'elementor-mcp' ) ),
				// Multi-gallery / filtering.
				'gallery_type'   => array( 'type' => 'string', 'enum' => array( 'single', 'multiple' ), 'description' => __( 'Single or multiple galleries (with filter bar).', 'elementor-mcp' ) ),
				'galleries'      => array(
					'type'        => 'array',
					'description' => __( 'For gallery_type=multiple: array of {gallery_title, gallery (array of images)}.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'gallery_title' => array( 'type' => 'string' ),
							'gallery'       => array( 'type' => 'array' ),
						),
					),
				),
				// Layout options.
				'aspect_ratio'   => array( 'type' => 'string', 'enum' => array( '1:1', '3:2', '4:3', '9:16', '16:9', '21:9' ), 'description' => __( 'Image aspect ratio (grid layout).', 'elementor-mcp' ) ),
				'ideal_row_height' => array( 'type' => 'object', 'description' => __( 'Ideal row height for justified layout: {size, unit}.', 'elementor-mcp' ) ),
				'order_by'       => array( 'type' => 'string', 'enum' => array( '', 'random' ), 'description' => __( 'Image order: default or random.', 'elementor-mcp' ) ),
				'lazyload'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Lazy load images.', 'elementor-mcp' ) ),
				// Overlay.
				'overlay_background' => array( 'type' => 'string', 'description' => __( 'Overlay background color on hover.', 'elementor-mcp' ) ),
				'content_hover_animation' => array( 'type' => 'string', 'description' => __( 'Overlay content hover animation.', 'elementor-mcp' ) ),
				// Lightbox.
				'open_lightbox'  => array( 'type' => 'string', 'enum' => array( 'default', 'yes', 'no' ), 'description' => __( 'Open in lightbox.', 'elementor-mcp' ) ),
				// Image style.
				'image_border_radius' => array( 'type' => 'object', 'description' => __( 'Image border radius: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'image_border_border' => array( 'type' => 'string', 'enum' => array( '', 'solid', 'double', 'dotted', 'dashed' ), 'description' => __( 'Image border style.', 'elementor-mcp' ) ),
				'image_border_width'  => array( 'type' => 'object', 'description' => __( 'Image border width: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'image_border_color'  => array( 'type' => 'string', 'description' => __( 'Image border color.', 'elementor-mcp' ) ),
			),
			array( 'gallery' ),
			'gallery',
			array( 'gallery_layout' => 'grid', 'columns' => 4 )
		);
	}

	private function register_add_share_buttons(): void {
		$this->register_convenience_tool(
			'add-share-buttons',
			__( 'Add Share Buttons (Pro)', 'elementor-mcp' ),
			__( 'Adds social share buttons for sharing the current page.', 'elementor-mcp' ),
			array(
				'share_buttons' => array(
					'type'        => 'array',
					'description' => __( 'Array of share buttons with button (network name) and optional text.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'button' => array( 'type' => 'string', 'description' => __( 'Network: facebook, twitter, linkedin, pinterest, reddit, etc.', 'elementor-mcp' ) ),
							'text'   => array( 'type' => 'string' ),
						),
					),
				),
				'view'          => array( 'type' => 'string', 'enum' => array( 'icon-text', 'icon', 'text' ), 'description' => __( 'Display mode. Default: icon-text.', 'elementor-mcp' ) ),
				'skin'          => array( 'type' => 'string', 'enum' => array( 'gradient', 'minimal', 'framed', 'boxed', 'flat' ), 'description' => __( 'Button skin/style.', 'elementor-mcp' ) ),
				'shape'         => array( 'type' => 'string', 'enum' => array( 'square', 'rounded', 'circle' ), 'description' => __( 'Button shape. Default: square.', 'elementor-mcp' ) ),
				'columns'       => array( 'type' => 'integer', 'description' => __( 'Number of columns.', 'elementor-mcp' ) ),
			),
			array( 'share_buttons' ),
			'share-buttons',
			array( 'view' => 'icon-text', 'shape' => 'square' )
		);
	}

	private function register_add_table_of_contents(): void {
		$this->register_convenience_tool(
			'add-table-of-contents',
			__( 'Add Table of Contents (Pro)', 'elementor-mcp' ),
			__( 'Adds an auto-generated table of contents widget based on page headings.', 'elementor-mcp' ),
			array(
				'title'             => array( 'type' => 'string', 'description' => __( 'TOC title. Default: Table of Contents.', 'elementor-mcp' ) ),
				'headings_by_tags'  => array( 'type' => 'array', 'description' => __( 'Which heading tags to include (e.g. ["h2", "h3"]).', 'elementor-mcp' ) ),
				'marker_view'       => array( 'type' => 'string', 'enum' => array( 'numbers', 'bullets', 'none' ), 'description' => __( 'Marker style. Default: numbers.', 'elementor-mcp' ) ),
				'hierarchical_view' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Hierarchical display. Default: yes.', 'elementor-mcp' ) ),
			),
			array(),
			'table-of-contents',
			array( 'title' => 'Table of Contents', 'marker_view' => 'numbers', 'hierarchical_view' => 'yes' )
		);
	}

	private function register_add_blockquote(): void {
		$this->register_convenience_tool(
			'add-blockquote',
			__( 'Add Blockquote (Pro)', 'elementor-mcp' ),
			__( 'Adds a styled blockquote widget with quote text, author, tweet button, colors, border, typography. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'blockquote_content' => array( 'type' => 'string', 'description' => __( 'Quote/blockquote text.', 'elementor-mcp' ) ),
				'author_name'        => array( 'type' => 'string', 'description' => __( 'Author/attribution name.', 'elementor-mcp' ) ),
				'blockquote_skin'    => array( 'type' => 'string', 'enum' => array( 'border', 'quotation', 'boxed', 'clean' ), 'description' => __( 'Skin variant. Default: border.', 'elementor-mcp' ) ),
				'alignment'          => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Text alignment.', 'elementor-mcp' ) ),
				// Tweet button.
				'tweet_button'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show tweet button.', 'elementor-mcp' ) ),
				'tweet_button_view'  => array( 'type' => 'string', 'enum' => array( 'icon-text', 'icon', 'text' ), 'description' => __( 'Tweet button display mode.', 'elementor-mcp' ) ),
				'tweet_button_skin'  => array( 'type' => 'string', 'enum' => array( 'classic', 'bubble', 'link' ), 'description' => __( 'Tweet button style.', 'elementor-mcp' ) ),
				'tweet_button_label' => array( 'type' => 'string', 'description' => __( 'Custom tweet button label.', 'elementor-mcp' ) ),
				'url_type'           => array( 'type' => 'string', 'enum' => array( 'current_page', 'custom' ), 'description' => __( 'URL to share: current page or custom.', 'elementor-mcp' ) ),
				'url'                => array( 'type' => 'string', 'description' => __( 'Custom URL to share (when url_type=custom).', 'elementor-mcp' ) ),
				'user_name'          => array( 'type' => 'string', 'description' => __( 'Twitter @username for "via" attribution.', 'elementor-mcp' ) ),
				// Style - Quote.
				'content_text_color' => array( 'type' => 'string', 'description' => __( 'Quote text color.', 'elementor-mcp' ) ),
				'content_gap'        => array( 'type' => 'object', 'description' => __( 'Gap between quote and author: {size, unit}.', 'elementor-mcp' ) ),
				'content_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for quote typography.', 'elementor-mcp' ) ),
				'content_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Quote font family.', 'elementor-mcp' ) ),
				'content_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Quote font size: {size, unit}.', 'elementor-mcp' ) ),
				// Style - Author.
				'author_text_color'  => array( 'type' => 'string', 'description' => __( 'Author name color.', 'elementor-mcp' ) ),
				'author_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for author typography.', 'elementor-mcp' ) ),
				'author_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Author font family.', 'elementor-mcp' ) ),
				'author_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Author font size: {size, unit}.', 'elementor-mcp' ) ),
				// Style - Border/Quotation mark.
				'border_color'       => array( 'type' => 'string', 'description' => __( 'Border color (border skin) or quotation mark color (quotation skin).', 'elementor-mcp' ) ),
				'border_width'       => array( 'type' => 'object', 'description' => __( 'Border width: {size, unit}.', 'elementor-mcp' ) ),
				'border_gap'         => array( 'type' => 'object', 'description' => __( 'Gap between border and content: {size, unit}.', 'elementor-mcp' ) ),
				'quote_size'         => array( 'type' => 'object', 'description' => __( 'Quotation mark size (quotation skin): {size, unit}.', 'elementor-mcp' ) ),
				// Style - Box (boxed skin).
				'box_color'          => array( 'type' => 'string', 'description' => __( 'Box background color (boxed skin).', 'elementor-mcp' ) ),
				// Tweet button style.
				'button_color'       => array( 'type' => 'string', 'description' => __( 'Tweet button text/icon color.', 'elementor-mcp' ) ),
				'button_text_color'  => array( 'type' => 'string', 'description' => __( 'Tweet button background color.', 'elementor-mcp' ) ),
			),
			array( 'blockquote_content' ),
			'blockquote',
			array( 'blockquote_skin' => 'border' )
		);
	}

	private function register_add_lottie(): void {
		$this->register_convenience_tool(
			'add-lottie',
			__( 'Add Lottie Animation (Pro)', 'elementor-mcp' ),
			__( 'Adds a Lottie animation widget. Supports triggers, loop, speed, renderer, sizing, link, viewport settings, opacity, CSS filters. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'source'              => array( 'type' => 'string', 'enum' => array( 'media_file', 'external_url' ), 'description' => __( 'Source type. Default: external_url.', 'elementor-mcp' ) ),
				'source_external_url' => array( 'type' => 'string', 'description' => __( 'External Lottie JSON URL.', 'elementor-mcp' ) ),
				'source_json'         => array( 'type' => 'object', 'description' => __( 'Media library file: {url, id}.', 'elementor-mcp' ) ),
				// Playback.
				'trigger'             => array( 'type' => 'string', 'enum' => array( 'arriving_to_viewport', 'on_click', 'on_hover', 'bind_to_scroll', 'none' ), 'description' => __( 'Animation trigger. Default: arriving_to_viewport.', 'elementor-mcp' ) ),
				'loop'                => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Loop animation. Default: yes.', 'elementor-mcp' ) ),
				'number_of_times'     => array( 'type' => 'integer', 'description' => __( 'Loop count (0 = infinite).', 'elementor-mcp' ) ),
				'play_speed'          => array( 'type' => 'object', 'description' => __( 'Playback speed: {size, unit}.', 'elementor-mcp' ) ),
				'start_point'         => array( 'type' => 'object', 'description' => __( 'Animation start point (0-100): {size, unit}.', 'elementor-mcp' ) ),
				'end_point'           => array( 'type' => 'object', 'description' => __( 'Animation end point (0-100): {size, unit}.', 'elementor-mcp' ) ),
				'reverse_animation'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Play animation in reverse.', 'elementor-mcp' ) ),
				'renderer'            => array( 'type' => 'string', 'enum' => array( 'svg', 'canvas' ), 'description' => __( 'Render method. Default: svg.', 'elementor-mcp' ) ),
				'lazyload'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Lazy load animation.', 'elementor-mcp' ) ),
				// Link.
				'link_to'             => array( 'type' => 'string', 'enum' => array( 'none', 'custom' ), 'description' => __( 'Link type.', 'elementor-mcp' ) ),
				'custom_link'         => array( 'type' => 'object', 'description' => __( 'Link object: {url, is_external, nofollow}.', 'elementor-mcp' ) ),
				// Viewport trigger settings.
				'viewport_start'      => array( 'type' => 'string', 'description' => __( 'Viewport offset start (e.g. "bottom").', 'elementor-mcp' ) ),
				'viewport_end'        => array( 'type' => 'string', 'description' => __( 'Viewport offset end.', 'elementor-mcp' ) ),
				// Caption.
				'caption_source'      => array( 'type' => 'string', 'enum' => array( 'none', 'title', 'caption', 'custom' ), 'description' => __( 'Caption source.', 'elementor-mcp' ) ),
				'caption'             => array( 'type' => 'string', 'description' => __( 'Custom caption text.', 'elementor-mcp' ) ),
				// Style.
				'align'               => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Alignment.', 'elementor-mcp' ) ),
				'width'               => array( 'type' => 'object', 'description' => __( 'Width: {size, unit}.', 'elementor-mcp' ) ),
				'opacity'             => array( 'type' => 'object', 'description' => __( 'Opacity (0-1): {size, unit}.', 'elementor-mcp' ) ),
				'css_filters_css_filter' => array( 'type' => 'string', 'description' => __( 'Set to "custom" for CSS filters.', 'elementor-mcp' ) ),
				'css_filters_blur'    => array( 'type' => 'object', 'description' => __( 'Blur filter: {size, unit}.', 'elementor-mcp' ) ),
				'css_filters_brightness' => array( 'type' => 'object', 'description' => __( 'Brightness filter: {size, unit}.', 'elementor-mcp' ) ),
				'css_filters_contrast' => array( 'type' => 'object', 'description' => __( 'Contrast filter: {size, unit}.', 'elementor-mcp' ) ),
				'css_filters_saturate' => array( 'type' => 'object', 'description' => __( 'Saturate filter: {size, unit}.', 'elementor-mcp' ) ),
				'opacity_hover'       => array( 'type' => 'object', 'description' => __( 'Hover opacity: {size, unit}.', 'elementor-mcp' ) ),
			),
			array(),
			'lottie',
			array( 'source' => 'external_url', 'trigger' => 'arriving_to_viewport', 'loop' => 'yes', 'renderer' => 'svg' )
		);
	}

	private function register_add_hotspot(): void {
		$this->register_convenience_tool(
			'add-hotspot',
			__( 'Add Hotspot (Pro)', 'elementor-mcp' ),
			__( 'Adds an image hotspot widget with clickable/hoverable points. Supports tooltip settings, animations, hotspot sizing/colors, image width. Accepts responsive suffixes and advanced controls.', 'elementor-mcp' ),
			array(
				'image'   => array( 'type' => 'object', 'description' => __( 'Background image object with url and optional id.', 'elementor-mcp' ) ),
				'hotspot' => array(
					'type'        => 'array',
					'description' => __( 'Array of hotspot items.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'hotspot_label'           => array( 'type' => 'string' ),
							'hotspot_link'            => array( 'type' => 'object', 'description' => __( '{url, is_external, nofollow}.', 'elementor-mcp' ) ),
							'hotspot_icon'            => array( 'type' => 'object', 'description' => __( 'Icon: {value, library}.', 'elementor-mcp' ) ),
							'hotspot_icon_position'   => array( 'type' => 'string', 'enum' => array( 'before', 'after' ) ),
							'hotspot_horizontal'      => array( 'type' => 'string', 'enum' => array( 'left', 'right' ) ),
							'hotspot_offset_x'        => array( 'type' => 'object', 'description' => __( 'Horizontal offset %: {size, unit}.', 'elementor-mcp' ) ),
							'hotspot_vertical'        => array( 'type' => 'string', 'enum' => array( 'top', 'bottom' ) ),
							'hotspot_offset_y'        => array( 'type' => 'object', 'description' => __( 'Vertical offset %: {size, unit}.', 'elementor-mcp' ) ),
							'hotspot_tooltip_content' => array( 'type' => 'string' ),
							'hotspot_custom_size'     => array( 'type' => 'string', 'enum' => array( 'yes', '' ) ),
							'hotspot_width'           => array( 'type' => 'object' ),
							'hotspot_height'          => array( 'type' => 'object' ),
						),
					),
				),
				// Image.
				'image_size'          => array( 'type' => 'string', 'description' => __( 'Image size (e.g. full, large, medium).', 'elementor-mcp' ) ),
				'image_custom_dimension' => array( 'type' => 'object', 'description' => __( 'Custom image dimensions: {width, height}.', 'elementor-mcp' ) ),
				// Tooltip settings.
				'tooltip_trigger'     => array( 'type' => 'string', 'enum' => array( 'mouseenter', 'click', 'none' ), 'description' => __( 'Tooltip trigger event. Default: mouseenter.', 'elementor-mcp' ) ),
				'tooltip_position'    => array( 'type' => 'string', 'enum' => array( 'top', 'bottom', 'left', 'right' ), 'description' => __( 'Default tooltip position.', 'elementor-mcp' ) ),
				'tooltip_animation'   => array( 'type' => 'string', 'enum' => array( 'e--animation-fadeIn', 'e--animation-zoomIn', 'e--animation-slideInUp', 'e--animation-slideInDown', 'e--animation-slideInLeft', 'e--animation-slideInRight' ), 'description' => __( 'Tooltip entrance animation.', 'elementor-mcp' ) ),
				'tooltip_animation_duration' => array( 'type' => 'object', 'description' => __( 'Tooltip animation duration: {size, unit}.', 'elementor-mcp' ) ),
				// Hotspot animation.
				'hotspot_animation'   => array( 'type' => 'string', 'enum' => array( 'none', 'soft-beat', 'expand', 'shadow' ), 'description' => __( 'Hotspot point animation.', 'elementor-mcp' ) ),
				'hotspot_sequenced_animation' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Staggered animation sequence.', 'elementor-mcp' ) ),
				// Style - Image.
				'image_width'         => array( 'type' => 'object', 'description' => __( 'Image width: {size, unit}.', 'elementor-mcp' ) ),
				'image_opacity'       => array( 'type' => 'object', 'description' => __( 'Image opacity (0-1): {size, unit}.', 'elementor-mcp' ) ),
				'image_border_radius' => array( 'type' => 'object', 'description' => __( 'Image border radius: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				// Style - Hotspot.
				'hotspot_color'       => array( 'type' => 'string', 'description' => __( 'Hotspot label/icon color.', 'elementor-mcp' ) ),
				'hotspot_background_color' => array( 'type' => 'string', 'description' => __( 'Hotspot background color.', 'elementor-mcp' ) ),
				'hotspot_size'        => array( 'type' => 'object', 'description' => __( 'Hotspot point size: {size, unit}.', 'elementor-mcp' ) ),
				'hotspot_padding'     => array( 'type' => 'object', 'description' => __( 'Hotspot padding: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'hotspot_border_radius' => array( 'type' => 'object', 'description' => __( 'Hotspot border radius: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'hotspot_box_shadow_box_shadow_type' => array( 'type' => 'string', 'description' => __( 'Set to "yes" for hotspot box shadow.', 'elementor-mcp' ) ),
				'hotspot_box_shadow_box_shadow' => array( 'type' => 'object', 'description' => __( 'Hotspot box shadow: {horizontal, vertical, blur, spread, color}.', 'elementor-mcp' ) ),
				// Style - Tooltip.
				'tooltip_text_color'  => array( 'type' => 'string', 'description' => __( 'Tooltip text color.', 'elementor-mcp' ) ),
				'tooltip_background_color' => array( 'type' => 'string', 'description' => __( 'Tooltip background color.', 'elementor-mcp' ) ),
				'tooltip_border_radius' => array( 'type' => 'object', 'description' => __( 'Tooltip border radius: {size, unit}.', 'elementor-mcp' ) ),
				'tooltip_padding'     => array( 'type' => 'object', 'description' => __( 'Tooltip padding: {top, right, bottom, left, unit, isLinked}.', 'elementor-mcp' ) ),
				'tooltip_width'       => array( 'type' => 'object', 'description' => __( 'Tooltip width: {size, unit}.', 'elementor-mcp' ) ),
				'tooltip_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "custom" for tooltip typography.', 'elementor-mcp' ) ),
				'tooltip_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Tooltip font family.', 'elementor-mcp' ) ),
				'tooltip_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Tooltip font size: {size, unit}.', 'elementor-mcp' ) ),
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
			__( 'Add Menu Anchor', 'elementor-mcp' ),
			__( 'Adds a menu anchor for one-page navigation.', 'elementor-mcp' ),
			array(
				'anchor' => array( 'type' => 'string', 'description' => __( 'The anchor ID (used in menu links as #id).', 'elementor-mcp' ) ),
			),
			array( 'anchor' ),
			'menu-anchor',
			array()
		);
	}

	private function register_add_shortcode(): void {
		$this->register_convenience_tool(
			'add-shortcode',
			__( 'Add Shortcode', 'elementor-mcp' ),
			__( 'Adds a WordPress shortcode widget.', 'elementor-mcp' ),
			array(
				'shortcode' => array( 'type' => 'string', 'description' => __( 'The shortcode to render, e.g. [contact-form-7 id="123"].', 'elementor-mcp' ) ),
			),
			array( 'shortcode' ),
			'shortcode',
			array()
		);
	}

	private function register_add_rating(): void {
		$this->register_convenience_tool(
			'add-rating',
			__( 'Add Rating', 'elementor-mcp' ),
			__( 'Adds a star/icon rating widget.', 'elementor-mcp' ),
			array(
				'rating_scale'        => array( 'type' => 'object', 'description' => __( 'Rating scale: { "size": 5, "unit": "px" }. Default 5.', 'elementor-mcp' ) ),
				'rating_value'        => array( 'type' => 'number', 'description' => __( 'Rating value (e.g. 4.5).', 'elementor-mcp' ) ),
				'rating_icon'         => array( 'type' => 'object', 'description' => __( 'Icon object, e.g. { "value": "eicon-star", "library": "eicons" }.', 'elementor-mcp' ) ),
				'icon_alignment'      => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end' ), 'description' => __( 'Icon alignment.', 'elementor-mcp' ) ),
				'icon_size'           => array( 'type' => 'object', 'description' => __( 'Icon size: { "size": 24, "unit": "px" }.', 'elementor-mcp' ) ),
				'icon_gap'            => array( 'type' => 'object', 'description' => __( 'Space between icons: { "size": 5, "unit": "px" }.', 'elementor-mcp' ) ),
				'icon_color'          => array( 'type' => 'string', 'description' => __( 'Marked icon color (hex).', 'elementor-mcp' ) ),
				'icon_unmarked_color' => array( 'type' => 'string', 'description' => __( 'Unmarked icon color (hex).', 'elementor-mcp' ) ),
			),
			array(),
			'rating',
			array( 'rating_value' => 5 )
		);
	}

	private function register_add_text_path(): void {
		$this->register_convenience_tool(
			'add-text-path',
			__( 'Add Text Path', 'elementor-mcp' ),
			__( 'Adds curved/path text widget.', 'elementor-mcp' ),
			array(
				'text'                => array( 'type' => 'string', 'description' => __( 'The text content.', 'elementor-mcp' ) ),
				'path'                => array( 'type' => 'string', 'enum' => array( 'wave', 'arc', 'circle', 'line', 'oval', 'spiral', 'custom' ), 'description' => __( 'Path shape type. Default: wave.', 'elementor-mcp' ) ),
				'custom_path'         => array( 'type' => 'object', 'description' => __( 'Custom SVG path object (when path=custom).', 'elementor-mcp' ) ),
				'link'                => array( 'type' => 'object', 'description' => __( 'Link object: { "url": "...", "is_external": true }.', 'elementor-mcp' ) ),
				'align'               => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Text alignment.', 'elementor-mcp' ) ),
				'text_path_direction' => array( 'type' => 'string', 'enum' => array( '', 'rtl', 'ltr' ), 'description' => __( 'Text direction.', 'elementor-mcp' ) ),
				'show_path'           => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show the SVG path line.', 'elementor-mcp' ) ),
				'size'                => array( 'type' => 'object', 'description' => __( 'Path size: { "size": 500, "unit": "px" }.', 'elementor-mcp' ) ),
				'rotation'            => array( 'type' => 'object', 'description' => __( 'Rotation: { "size": 0, "unit": "px" }.', 'elementor-mcp' ) ),
				'start_point'         => array( 'type' => 'object', 'description' => __( 'Starting point (%): { "size": 0, "unit": "px" }.', 'elementor-mcp' ) ),
				'text_color_normal'   => array( 'type' => 'string', 'description' => __( 'Text color (hex).', 'elementor-mcp' ) ),
				'text_color_hover'    => array( 'type' => 'string', 'description' => __( 'Text hover color (hex).', 'elementor-mcp' ) ),
				'stroke_color_normal' => array( 'type' => 'string', 'description' => __( 'Path stroke color (hex).', 'elementor-mcp' ) ),
				'stroke_width_normal' => array( 'type' => 'object', 'description' => __( 'Path stroke width: { "size": 1, "unit": "px" }.', 'elementor-mcp' ) ),
				'text_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "yes" for custom typography.', 'elementor-mcp' ) ),
				'text_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Font family name.', 'elementor-mcp' ) ),
				'text_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Font size: { "size": 20, "unit": "px" }.', 'elementor-mcp' ) ),
				'text_typography_font_weight' => array( 'type' => 'string', 'description' => __( 'Font weight (100-900, normal, bold).', 'elementor-mcp' ) ),
			),
			array(),
			'text-path',
			array( 'text' => 'Add Your Curvy Text Here', 'path' => 'wave' )
		);
	}

	private function register_add_nav_menu(): void {
		$this->register_convenience_tool(
			'add-nav-menu',
			__( 'Add Navigation Menu', 'elementor-mcp' ),
			__( 'Adds a WordPress navigation menu widget (Pro).', 'elementor-mcp' ),
			array(
				'menu_name'     => array( 'type' => 'string', 'description' => __( 'Menu name (as registered in WP Menus).', 'elementor-mcp' ) ),
				'layout'        => array( 'type' => 'string', 'enum' => array( 'horizontal', 'vertical', 'dropdown' ), 'description' => __( 'Menu layout. Default: horizontal.', 'elementor-mcp' ) ),
				'align_items'   => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'justify' ), 'description' => __( 'Menu alignment.', 'elementor-mcp' ) ),
				'pointer'       => array( 'type' => 'string', 'enum' => array( 'none', 'underline', 'overline', 'double-line', 'framed', 'background', 'text' ), 'description' => __( 'Hover pointer style. Default: underline.', 'elementor-mcp' ) ),
				'animation_line' => array( 'type' => 'string', 'enum' => array( 'fade', 'slide', 'grow', 'drop-in', 'drop-out', 'none' ), 'description' => __( 'Line pointer animation.', 'elementor-mcp' ) ),
				'dropdown'      => array( 'type' => 'string', 'enum' => array( 'mobile', 'tablet', 'none' ), 'description' => __( 'Breakpoint for dropdown toggle. Default: tablet.', 'elementor-mcp' ) ),
				'full_width'    => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Full width dropdown.', 'elementor-mcp' ) ),
				'text_align'    => array( 'type' => 'string', 'enum' => array( 'aside', 'center' ), 'description' => __( 'Dropdown text alignment.', 'elementor-mcp' ) ),
				'toggle'        => array( 'type' => 'string', 'enum' => array( '', 'burger' ), 'description' => __( 'Toggle button type.', 'elementor-mcp' ) ),
				'toggle_align'  => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Toggle button alignment.', 'elementor-mcp' ) ),
				'color_menu_item'                => array( 'type' => 'string', 'description' => __( 'Menu text color (hex).', 'elementor-mcp' ) ),
				'color_menu_item_hover'          => array( 'type' => 'string', 'description' => __( 'Menu hover text color (hex).', 'elementor-mcp' ) ),
				'pointer_color_menu_item_hover'  => array( 'type' => 'string', 'description' => __( 'Pointer hover color (hex).', 'elementor-mcp' ) ),
				'color_menu_item_active'         => array( 'type' => 'string', 'description' => __( 'Active item text color (hex).', 'elementor-mcp' ) ),
				'pointer_color_menu_item_active' => array( 'type' => 'string', 'description' => __( 'Active item pointer color (hex).', 'elementor-mcp' ) ),
				'padding_horizontal_menu_item'   => array( 'type' => 'object', 'description' => __( 'Horizontal padding: { "size": 20, "unit": "px" }.', 'elementor-mcp' ) ),
				'padding_vertical_menu_item'     => array( 'type' => 'object', 'description' => __( 'Vertical padding: { "size": 15, "unit": "px" }.', 'elementor-mcp' ) ),
				'menu_space_between'             => array( 'type' => 'object', 'description' => __( 'Space between items: { "size": 10, "unit": "px" }.', 'elementor-mcp' ) ),
				'menu_typography_typography'      => array( 'type' => 'string', 'description' => __( 'Set to "yes" for custom typography.', 'elementor-mcp' ) ),
				'menu_typography_font_family'     => array( 'type' => 'string', 'description' => __( 'Font family name.', 'elementor-mcp' ) ),
				'menu_typography_font_size'       => array( 'type' => 'object', 'description' => __( 'Font size: { "size": 16, "unit": "px" }.', 'elementor-mcp' ) ),
				'menu_typography_font_weight'     => array( 'type' => 'string', 'description' => __( 'Font weight.', 'elementor-mcp' ) ),
			),
			array(),
			'nav-menu',
			array( 'layout' => 'horizontal', 'pointer' => 'underline' )
		);
	}

	private function register_add_loop_grid(): void {
		$this->register_convenience_tool(
			'add-loop-grid',
			__( 'Add Loop Grid', 'elementor-mcp' ),
			__( 'Adds a loop grid widget that displays posts/pages/CPTs using a loop template (Pro).', 'elementor-mcp' ),
			array(
				'_skin'                => array( 'type' => 'string', 'enum' => array( 'post', 'post_taxonomy' ), 'description' => __( 'Template type. Default: post.', 'elementor-mcp' ) ),
				'template_id'          => array( 'type' => 'string', 'description' => __( 'Loop template ID.', 'elementor-mcp' ) ),
				'columns'              => array( 'type' => 'number', 'description' => __( 'Number of columns. Default: 3.', 'elementor-mcp' ) ),
				'columns_tablet'       => array( 'type' => 'number', 'description' => __( 'Columns on tablet. Default: 2.', 'elementor-mcp' ) ),
				'columns_mobile'       => array( 'type' => 'number', 'description' => __( 'Columns on mobile. Default: 1.', 'elementor-mcp' ) ),
				'posts_per_page'       => array( 'type' => 'number', 'description' => __( 'Items per page. Default: 6.', 'elementor-mcp' ) ),
				'masonry'              => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable masonry layout.', 'elementor-mcp' ) ),
				'equal_height'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Equal height items.', 'elementor-mcp' ) ),
				'post_query_post_type' => array( 'type' => 'string', 'enum' => array( 'post', 'page', 'by_id', 'current_query', 'related' ), 'description' => __( 'Query source. Default: post.', 'elementor-mcp' ) ),
				'post_query_include'   => array( 'type' => 'string', 'enum' => array( 'terms', 'authors' ), 'description' => __( 'Include by terms or authors.', 'elementor-mcp' ) ),
				'post_query_exclude'   => array( 'type' => 'string', 'enum' => array( 'current_post', 'manual_selection', 'terms', 'authors' ), 'description' => __( 'Exclude criteria.', 'elementor-mcp' ) ),
				'post_query_orderby'   => array( 'type' => 'string', 'enum' => array( 'post_date', 'post_title', 'menu_order', 'modified', 'comment_count', 'rand' ), 'description' => __( 'Order by field. Default: post_date.', 'elementor-mcp' ) ),
				'post_query_order'     => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'description' => __( 'Sort order. Default: desc.', 'elementor-mcp' ) ),
				'post_query_offset'    => array( 'type' => 'number', 'description' => __( 'Query offset.', 'elementor-mcp' ) ),
			),
			array(),
			'loop-grid',
			array( 'columns' => 3, 'posts_per_page' => 6 )
		);
	}

	private function register_add_loop_carousel(): void {
		$this->register_convenience_tool(
			'add-loop-carousel',
			__( 'Add Loop Carousel', 'elementor-mcp' ),
			__( 'Adds a loop carousel widget that displays posts in a carousel using a loop template (Pro).', 'elementor-mcp' ),
			array(
				'_skin'                => array( 'type' => 'string', 'enum' => array( 'post', 'post_taxonomy' ), 'description' => __( 'Template type. Default: post.', 'elementor-mcp' ) ),
				'template_id'          => array( 'type' => 'string', 'description' => __( 'Loop template ID.', 'elementor-mcp' ) ),
				'posts_per_page'       => array( 'type' => 'number', 'description' => __( 'Number of slides. Default: 6.', 'elementor-mcp' ) ),
				'slides_to_show'       => array( 'type' => 'string', 'enum' => array( '', '1', '2', '3', '4', '5', '6', '7', '8' ), 'description' => __( 'Slides on display. Default: 3.', 'elementor-mcp' ) ),
				'slides_to_show_tablet' => array( 'type' => 'string', 'description' => __( 'Slides on tablet. Default: 2.', 'elementor-mcp' ) ),
				'slides_to_show_mobile' => array( 'type' => 'string', 'description' => __( 'Slides on mobile. Default: 1.', 'elementor-mcp' ) ),
				'slides_to_scroll'     => array( 'type' => 'string', 'description' => __( 'Slides to scroll per step. Default: 1.', 'elementor-mcp' ) ),
				'equal_height'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Equal height slides. Default: yes.', 'elementor-mcp' ) ),
				'post_query_post_type' => array( 'type' => 'string', 'enum' => array( 'post', 'page', 'by_id', 'current_query', 'related' ), 'description' => __( 'Query source. Default: post.', 'elementor-mcp' ) ),
				'post_query_orderby'   => array( 'type' => 'string', 'enum' => array( 'post_date', 'post_title', 'menu_order', 'modified', 'comment_count', 'rand' ), 'description' => __( 'Order by. Default: post_date.', 'elementor-mcp' ) ),
				'post_query_order'     => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'description' => __( 'Sort order. Default: desc.', 'elementor-mcp' ) ),
			),
			array(),
			'loop-carousel',
			array( 'posts_per_page' => 6, 'slides_to_show' => '3', 'equal_height' => 'yes' )
		);
	}

	private function register_add_media_carousel(): void {
		$this->register_convenience_tool(
			'add-media-carousel',
			__( 'Add Media Carousel', 'elementor-mcp' ),
			__( 'Adds a media carousel widget for images/video with multiple skins (Pro).', 'elementor-mcp' ),
			array(
				'skin'           => array( 'type' => 'string', 'enum' => array( 'carousel', 'slideshow', 'coverflow' ), 'description' => __( 'Carousel skin. Default: carousel.', 'elementor-mcp' ) ),
				'slides'         => array(
					'type'        => 'array',
					'description' => __( 'Array of slide items with image/video.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'image' => array( 'type' => 'object', 'description' => __( '{ "url": "...", "id": 0 }', 'elementor-mcp' ) ),
							'type'  => array( 'type' => 'string', 'description' => __( 'Slide type: image or video.', 'elementor-mcp' ) ),
						),
					),
				),
				'effect'           => array( 'type' => 'string', 'enum' => array( 'slide', 'fade', 'cube' ), 'description' => __( 'Transition effect. Default: slide.', 'elementor-mcp' ) ),
				'slides_per_view'  => array( 'type' => 'string', 'description' => __( 'Slides visible at once.', 'elementor-mcp' ) ),
				'slides_to_scroll' => array( 'type' => 'string', 'description' => __( 'Slides to scroll per step.', 'elementor-mcp' ) ),
				'height'           => array( 'type' => 'object', 'description' => __( 'Carousel height: { "size": 400, "unit": "px" }.', 'elementor-mcp' ) ),
				'width'            => array( 'type' => 'object', 'description' => __( 'Carousel width: { "size": 100, "unit": "%" }.', 'elementor-mcp' ) ),
				'show_arrows'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show navigation arrows. Default: yes.', 'elementor-mcp' ) ),
				'pagination'       => array( 'type' => 'string', 'enum' => array( '', 'bullets', 'fraction', 'progressbar' ), 'description' => __( 'Pagination type. Default: bullets.', 'elementor-mcp' ) ),
				'speed'            => array( 'type' => 'number', 'description' => __( 'Transition duration ms. Default: 500.', 'elementor-mcp' ) ),
				'autoplay'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable autoplay. Default: yes.', 'elementor-mcp' ) ),
				'autoplay_speed'   => array( 'type' => 'number', 'description' => __( 'Autoplay speed ms. Default: 5000.', 'elementor-mcp' ) ),
				'loop'             => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Infinite loop. Default: yes.', 'elementor-mcp' ) ),
				'pause_on_hover'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Pause on hover. Default: yes.', 'elementor-mcp' ) ),
				'overlay'          => array( 'type' => 'string', 'enum' => array( '', 'text', 'icon' ), 'description' => __( 'Overlay type on hover.', 'elementor-mcp' ) ),
				'caption'          => array( 'type' => 'string', 'enum' => array( 'title', 'caption', 'description' ), 'description' => __( 'Caption source. Default: title.', 'elementor-mcp' ) ),
				'image_size_size'  => array( 'type' => 'string', 'enum' => array( 'thumbnail', 'medium', 'medium_large', 'large', 'full', 'custom' ), 'description' => __( 'Image resolution. Default: full.', 'elementor-mcp' ) ),
				'image_fit'        => array( 'type' => 'string', 'enum' => array( '', 'contain', 'auto' ), 'description' => __( 'Image fit mode.', 'elementor-mcp' ) ),
				'centered_slides'  => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Center the active slide.', 'elementor-mcp' ) ),
				'slide_background_color' => array( 'type' => 'string', 'description' => __( 'Slide background color (hex).', 'elementor-mcp' ) ),
				'slide_border_radius'    => array( 'type' => 'object', 'description' => __( 'Slide border radius.', 'elementor-mcp' ) ),
				'arrows_size'      => array( 'type' => 'object', 'description' => __( 'Arrow size: { "size": 20, "unit": "px" }.', 'elementor-mcp' ) ),
				'arrows_color'     => array( 'type' => 'string', 'description' => __( 'Arrow color (hex).', 'elementor-mcp' ) ),
				'space_between'    => array( 'type' => 'object', 'description' => __( 'Space between slides: { "size": 10, "unit": "px" }.', 'elementor-mcp' ) ),
			),
			array(),
			'media-carousel',
			array( 'skin' => 'carousel', 'autoplay' => 'yes', 'loop' => 'yes' )
		);
	}

	private function register_add_nested_tabs(): void {
		$this->register_convenience_tool(
			'add-nested-tabs',
			__( 'Add Nested Tabs', 'elementor-mcp' ),
			__( 'Adds a modern nested tabs widget where each tab content is a container (Pro). Tab content can be populated by adding child elements to the tab containers after creation.', 'elementor-mcp' ),
			array(
				'tabs_direction'          => array( 'type' => 'string', 'enum' => array( 'block-start', 'block-end', 'inline-end', 'inline-start' ), 'description' => __( 'Tab direction. block-start=top, block-end=bottom, inline-start=left, inline-end=right.', 'elementor-mcp' ) ),
				'tabs_justify_horizontal' => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'stretch' ), 'description' => __( 'Horizontal tab justify.', 'elementor-mcp' ) ),
				'tabs_justify_vertical'   => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'stretch' ), 'description' => __( 'Vertical tab justify.', 'elementor-mcp' ) ),
				'tabs_width'              => array( 'type' => 'object', 'description' => __( 'Tab width: { "size": 200, "unit": "px" }.', 'elementor-mcp' ) ),
				'title_alignment'         => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end' ), 'description' => __( 'Title alignment within tab.', 'elementor-mcp' ) ),
				'horizontal_scroll'       => array( 'type' => 'string', 'enum' => array( 'disable', 'enable' ), 'description' => __( 'Enable horizontal scroll for tabs. Default: disable.', 'elementor-mcp' ) ),
				'breakpoint_selector'     => array( 'type' => 'string', 'enum' => array( 'none', 'mobile', 'tablet' ), 'description' => __( 'Breakpoint for accordion mode. Default: mobile.', 'elementor-mcp' ) ),
				'tabs_title_space_between' => array( 'type' => 'object', 'description' => __( 'Gap between tabs: { "size": 0, "unit": "px" }.', 'elementor-mcp' ) ),
				'tabs_title_spacing'       => array( 'type' => 'object', 'description' => __( 'Distance from content: { "size": 0, "unit": "px" }.', 'elementor-mcp' ) ),
				'tabs_title_background_color_background' => array( 'type' => 'string', 'enum' => array( 'classic', 'gradient' ), 'description' => __( 'Tab background type.', 'elementor-mcp' ) ),
				'tabs_title_background_color_color'      => array( 'type' => 'string', 'description' => __( 'Tab background color (hex).', 'elementor-mcp' ) ),
				'tabs_title_typography_typography'  => array( 'type' => 'string', 'description' => __( 'Set to "yes" for custom tab typography.', 'elementor-mcp' ) ),
				'tabs_title_typography_font_family' => array( 'type' => 'string', 'description' => __( 'Tab font family.', 'elementor-mcp' ) ),
				'tabs_title_typography_font_size'   => array( 'type' => 'object', 'description' => __( 'Tab font size: { "size": 16, "unit": "px" }.', 'elementor-mcp' ) ),
				'tabs_title_typography_font_weight' => array( 'type' => 'string', 'description' => __( 'Tab font weight.', 'elementor-mcp' ) ),
			),
			array(),
			'nested-tabs',
			array()
		);
	}

	private function register_add_nested_accordion(): void {
		$this->register_convenience_tool(
			'add-nested-accordion',
			__( 'Add Nested Accordion', 'elementor-mcp' ),
			__( 'Adds a modern nested accordion widget where each item content is a container (Pro). Item content can be populated by adding child elements to the item containers after creation.', 'elementor-mcp' ),
			array(
				'accordion_item_title_position_horizontal' => array( 'type' => 'string', 'enum' => array( 'start', 'center', 'end', 'stretch' ), 'description' => __( 'Title position.', 'elementor-mcp' ) ),
				'accordion_item_title_icon_position'       => array( 'type' => 'string', 'enum' => array( 'start', 'end' ), 'description' => __( 'Icon position. Default: end.', 'elementor-mcp' ) ),
				'accordion_item_title_icon'                => array( 'type' => 'object', 'description' => __( 'Expand icon object.', 'elementor-mcp' ) ),
				'accordion_item_title_icon_active'         => array( 'type' => 'object', 'description' => __( 'Collapse icon object.', 'elementor-mcp' ) ),
				'title_tag'             => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'div', 'span', 'p' ), 'description' => __( 'Title HTML tag. Default: div.', 'elementor-mcp' ) ),
				'faq_schema'            => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Enable FAQ Schema markup.', 'elementor-mcp' ) ),
				'default_state'         => array( 'type' => 'string', 'enum' => array( 'expanded', 'all_collapsed' ), 'description' => __( 'Default state. Default: expanded (first item open).', 'elementor-mcp' ) ),
				'max_items_expended'    => array( 'type' => 'string', 'enum' => array( 'one', 'multiple' ), 'description' => __( 'Max items expanded at once. Default: one.', 'elementor-mcp' ) ),
				'n_accordion_animation_duration' => array( 'type' => 'object', 'description' => __( 'Animation duration: { "size": 400, "unit": "ms" }.', 'elementor-mcp' ) ),
				'accordion_item_title_space_between'          => array( 'type' => 'object', 'description' => __( 'Space between items: { "size": 0, "unit": "px" }.', 'elementor-mcp' ) ),
				'accordion_item_title_distance_from_content'  => array( 'type' => 'object', 'description' => __( 'Distance from content: { "size": 0, "unit": "px" }.', 'elementor-mcp' ) ),
				'accordion_border_normal_border' => array( 'type' => 'string', 'enum' => array( '', 'none', 'solid', 'double', 'dotted', 'dashed', 'groove' ), 'description' => __( 'Border type.', 'elementor-mcp' ) ),
				'accordion_border_normal_color'  => array( 'type' => 'string', 'description' => __( 'Border color (hex).', 'elementor-mcp' ) ),
				'accordion_border_normal_width'  => array( 'type' => 'object', 'description' => __( 'Border width.', 'elementor-mcp' ) ),
				'accordion_background_normal_background' => array( 'type' => 'string', 'enum' => array( 'classic', 'gradient' ), 'description' => __( 'Background type.', 'elementor-mcp' ) ),
				'accordion_background_normal_color'      => array( 'type' => 'string', 'description' => __( 'Background color (hex).', 'elementor-mcp' ) ),
			),
			array(),
			'nested-accordion',
			array( 'default_state' => 'expanded', 'max_items_expended' => 'one' )
		);
	}

	// ── Phase 6: WooCommerce Widget Convenience Tools ─────────────────

	private function register_add_wc_products(): void {
		$this->register_convenience_tool(
			'add-wc-products',
			__( 'Add WooCommerce Products', 'elementor-mcp' ),
			__( 'Adds a WooCommerce products grid widget (Pro + WooCommerce).', 'elementor-mcp' ),
			array(
				'columns'        => array( 'type' => 'number', 'description' => __( 'Number of columns. Default: 4.', 'elementor-mcp' ) ),
				'rows'           => array( 'type' => 'number', 'description' => __( 'Number of rows. Default: 1.', 'elementor-mcp' ) ),
				'paginate'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show pagination.', 'elementor-mcp' ) ),
				'orderby'        => array( 'type' => 'string', 'enum' => array( 'date', 'title', 'price', 'popularity', 'rating', 'rand', 'menu_order' ), 'description' => __( 'Order by. Default: date.', 'elementor-mcp' ) ),
				'order'          => array( 'type' => 'string', 'enum' => array( 'asc', 'desc' ), 'description' => __( 'Sort order. Default: desc.', 'elementor-mcp' ) ),
				'query_post_type' => array( 'type' => 'string', 'enum' => array( 'product', 'current_query', 'by_id', 'related' ), 'description' => __( 'Query source. Default: product.', 'elementor-mcp' ) ),
				'show_result_count' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show result count.', 'elementor-mcp' ) ),
				'allow_order'    => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Allow ordering.', 'elementor-mcp' ) ),
			),
			array(),
			'woocommerce-products',
			array( 'columns' => 4, 'rows' => 1 )
		);
	}

	private function register_add_wc_add_to_cart(): void {
		$this->register_convenience_tool(
			'add-wc-add-to-cart',
			__( 'Add WooCommerce Add to Cart', 'elementor-mcp' ),
			__( 'Adds a WooCommerce add-to-cart button widget (Pro + WooCommerce).', 'elementor-mcp' ),
			array(
				'product_id'  => array( 'type' => 'integer', 'description' => __( 'Product ID to link to.', 'elementor-mcp' ) ),
				'show_quantity' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show quantity input.', 'elementor-mcp' ) ),
				'quantity'    => array( 'type' => 'number', 'description' => __( 'Default quantity.', 'elementor-mcp' ) ),
				'view'        => array( 'type' => 'string', 'enum' => array( '', 'stacked', 'inline' ), 'description' => __( 'Layout view.', 'elementor-mcp' ) ),
			),
			array(),
			'wc-add-to-cart',
			array()
		);
	}

	private function register_add_wc_cart(): void {
		$this->register_convenience_tool(
			'add-wc-cart',
			__( 'Add WooCommerce Cart', 'elementor-mcp' ),
			__( 'Adds the WooCommerce cart page widget (Pro + WooCommerce).', 'elementor-mcp' ),
			array(),
			array(),
			'woocommerce-cart',
			array()
		);
	}

	private function register_add_wc_checkout(): void {
		$this->register_convenience_tool(
			'add-wc-checkout',
			__( 'Add WooCommerce Checkout', 'elementor-mcp' ),
			__( 'Adds the WooCommerce checkout page widget (Pro + WooCommerce).', 'elementor-mcp' ),
			array(),
			array(),
			'woocommerce-checkout-page',
			array()
		);
	}

	private function register_add_wc_menu_cart(): void {
		$this->register_convenience_tool(
			'add-wc-menu-cart',
			__( 'Add WooCommerce Menu Cart', 'elementor-mcp' ),
			__( 'Adds a mini cart icon for the menu (Pro + WooCommerce).', 'elementor-mcp' ),
			array(
				'icon'            => array( 'type' => 'object', 'description' => __( 'Cart icon object.', 'elementor-mcp' ) ),
				'items_indicator' => array( 'type' => 'string', 'enum' => array( 'none', 'bubble', 'plain' ), 'description' => __( 'Items indicator style.', 'elementor-mcp' ) ),
				'hide_empty_indicator' => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Hide when cart is empty.', 'elementor-mcp' ) ),
				'alignment'       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Alignment.', 'elementor-mcp' ) ),
			),
			array(),
			'woocommerce-menu-cart',
			array()
		);
	}
}

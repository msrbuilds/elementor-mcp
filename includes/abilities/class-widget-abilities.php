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

		foreach ( $setting_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$settings[ $key ] = $input[ $key ];
			}
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
			__( 'Adds a heading widget with title, size, alignment, and color options.', 'elementor-mcp' ),
			array(
				'title'       => array( 'type' => 'string', 'description' => __( 'Heading text.', 'elementor-mcp' ) ),
				'header_size' => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'description' => __( 'HTML heading tag. Default: h2.', 'elementor-mcp' ) ),
				'size'        => array( 'type' => 'string', 'enum' => array( 'default', 'small', 'medium', 'large', 'xl', 'xxl' ), 'description' => __( 'Elementor size preset.', 'elementor-mcp' ) ),
				'align'       => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Text alignment.', 'elementor-mcp' ) ),
				'title_color' => array( 'type' => 'string', 'description' => __( 'Heading color (hex).', 'elementor-mcp' ) ),
				'link'        => array( 'type' => 'object', 'description' => __( 'Link object with url key.', 'elementor-mcp' ) ),
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
			__( 'Adds a rich text editor widget with HTML content.', 'elementor-mcp' ),
			array(
				'editor'     => array( 'type' => 'string', 'description' => __( 'HTML content.', 'elementor-mcp' ) ),
				'align'      => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Text alignment.', 'elementor-mcp' ) ),
				'text_color' => array( 'type' => 'string', 'description' => __( 'Text color (hex).', 'elementor-mcp' ) ),
			),
			array( 'editor' ),
			'text-editor'
		);
	}

	private function register_add_image(): void {
		$this->register_convenience_tool(
			'add-image',
			__( 'Add Image', 'elementor-mcp' ),
			__( 'Adds an image widget with source, size, alignment, caption, and link options.', 'elementor-mcp' ),
			array(
				'image'          => array( 'type' => 'object', 'description' => __( 'Image object with url (required) and optional id.', 'elementor-mcp' ) ),
				'image_size'     => array( 'type' => 'string', 'enum' => array( 'thumbnail', 'medium', 'medium_large', 'large', 'full' ), 'description' => __( 'Image size preset.', 'elementor-mcp' ) ),
				'align'          => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Image alignment.', 'elementor-mcp' ) ),
				'caption_source' => array( 'type' => 'string', 'enum' => array( 'none', 'attachment', 'custom' ), 'description' => __( 'Caption source.', 'elementor-mcp' ) ),
				'caption'        => array( 'type' => 'string', 'description' => __( 'Custom caption text.', 'elementor-mcp' ) ),
				'link_to'        => array( 'type' => 'string', 'enum' => array( 'none', 'file', 'custom' ), 'description' => __( 'Link behavior.', 'elementor-mcp' ) ),
				'link'           => array( 'type' => 'object', 'description' => __( 'Link object with url key.', 'elementor-mcp' ) ),
			),
			array( 'image' ),
			'image'
		);
	}

	private function register_add_button(): void {
		$this->register_convenience_tool(
			'add-button',
			__( 'Add Button', 'elementor-mcp' ),
			__( 'Adds a button widget with text, link, size, type, alignment, and icon options.', 'elementor-mcp' ),
			array(
				'text'          => array( 'type' => 'string', 'description' => __( 'Button text.', 'elementor-mcp' ) ),
				'link'          => array( 'type' => 'object', 'description' => __( 'Link object with url key.', 'elementor-mcp' ) ),
				'size'          => array( 'type' => 'string', 'enum' => array( 'xs', 'sm', 'md', 'lg', 'xl' ), 'description' => __( 'Button size.', 'elementor-mcp' ) ),
				'button_type'   => array( 'type' => 'string', 'enum' => array( '', 'info', 'success', 'warning', 'danger' ), 'description' => __( 'Button style type.', 'elementor-mcp' ) ),
				'align'         => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right', 'justify' ), 'description' => __( 'Button alignment.', 'elementor-mcp' ) ),
				'selected_icon' => array( 'type' => 'object', 'description' => __( 'Icon object with value and library.', 'elementor-mcp' ) ),
				'icon_align'    => array( 'type' => 'string', 'enum' => array( 'row', 'row-reverse' ), 'description' => __( 'Icon position.', 'elementor-mcp' ) ),
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
			__( 'Adds a video widget with support for YouTube, Vimeo, Dailymotion, and self-hosted HTML5 video.', 'elementor-mcp' ),
			array(
				'video_type'  => array( 'type' => 'string', 'enum' => array( 'youtube', 'vimeo', 'dailymotion', 'hosted' ), 'description' => __( 'Video source type.', 'elementor-mcp' ) ),
				'youtube_url' => array( 'type' => 'string', 'description' => __( 'YouTube URL.', 'elementor-mcp' ) ),
				'vimeo_url'   => array( 'type' => 'string', 'description' => __( 'Vimeo URL.', 'elementor-mcp' ) ),
				'autoplay'    => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay on load.', 'elementor-mcp' ) ),
				'mute'        => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Mute audio.', 'elementor-mcp' ) ),
				'loop'        => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Loop video.', 'elementor-mcp' ) ),
				'controls'    => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show player controls.', 'elementor-mcp' ) ),
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
			__( 'Adds an icon widget. Supports Font Awesome icons and custom SVG icons. For SVG icons, first use the upload-svg-icon tool to upload the SVG and get the icon_object, then pass it as selected_icon.', 'elementor-mcp' ),
			array(
				'selected_icon' => array( 'type' => 'object', 'description' => __( 'Icon object. Font Awesome: { "value": "fas fa-star", "library": "fa-solid" }. SVG (from upload-svg-icon): { "value": { "id": 123, "url": "https://..." }, "library": "svg" }. Libraries: fa-solid, fa-regular, fa-brands.', 'elementor-mcp' ) ),
				'view'          => array( 'type' => 'string', 'enum' => array( 'default', 'stacked', 'framed' ), 'description' => __( 'Icon view mode.', 'elementor-mcp' ) ),
				'shape'         => array( 'type' => 'string', 'enum' => array( 'circle', 'square' ), 'description' => __( 'Icon shape (for stacked/framed).', 'elementor-mcp' ) ),
				'primary_color' => array( 'type' => 'string', 'description' => __( 'Primary color (hex).', 'elementor-mcp' ) ),
				'size'          => array( 'type' => 'object', 'description' => __( 'Icon size: { "size": 50, "unit": "px" }.', 'elementor-mcp' ) ),
				'link'          => array( 'type' => 'object', 'description' => __( 'Link object with url key.', 'elementor-mcp' ) ),
				'align'         => array( 'type' => 'string', 'enum' => array( 'left', 'center', 'right' ), 'description' => __( 'Icon alignment.', 'elementor-mcp' ) ),
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
			__( 'Adds an icon box widget combining an icon, title, and description. Supports Font Awesome and SVG icons. For SVG, first use upload-svg-icon to get the icon_object.', 'elementor-mcp' ),
			array(
				'selected_icon'  => array( 'type' => 'object', 'description' => __( 'Icon object. Font Awesome: { "value": "fas fa-star", "library": "fa-solid" }. SVG (from upload-svg-icon): { "value": { "id": 123, "url": "https://..." }, "library": "svg" }. Libraries: fa-solid, fa-regular, fa-brands.', 'elementor-mcp' ) ),
				'title_text'     => array( 'type' => 'string', 'description' => __( 'Box title.', 'elementor-mcp' ) ),
				'description_text' => array( 'type' => 'string', 'description' => __( 'Box description.', 'elementor-mcp' ) ),
				'view'           => array( 'type' => 'string', 'enum' => array( 'default', 'stacked', 'framed' ), 'description' => __( 'Icon view mode.', 'elementor-mcp' ) ),
				'shape'          => array( 'type' => 'string', 'enum' => array( 'circle', 'square' ), 'description' => __( 'Icon shape.', 'elementor-mcp' ) ),
				'link'           => array( 'type' => 'object', 'description' => __( 'Link object with url key.', 'elementor-mcp' ) ),
				'title_color'    => array( 'type' => 'string', 'description' => __( 'Title color (hex).', 'elementor-mcp' ) ),
				'primary_color'  => array( 'type' => 'string', 'description' => __( 'Icon primary color (hex).', 'elementor-mcp' ) ),
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
			__( 'Adds an accordion widget with collapsible sections. Each tab has a title and content.', 'elementor-mcp' ),
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
			__( 'Adds a toggle/expandable content widget. Similar to accordion but multiple items can be open.', 'elementor-mcp' ),
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
			__( 'Adds an Elementor Pro form widget with customizable fields, button, and email action.', 'elementor-mcp' ),
			array(
				'form_name'     => array( 'type' => 'string', 'description' => __( 'Form name.', 'elementor-mcp' ) ),
				'form_fields'   => array(
					'type'        => 'array',
					'description' => __( 'Array of field definitions.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'field_type'    => array( 'type' => 'string', 'enum' => array( 'text', 'email', 'textarea', 'url', 'tel', 'select', 'radio', 'checkbox', 'number', 'date', 'hidden' ) ),
							'field_label'   => array( 'type' => 'string' ),
							'placeholder'   => array( 'type' => 'string' ),
							'required'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ) ),
							'width'         => array( 'type' => 'string', 'enum' => array( '100', '80', '75', '66', '50', '33', '25' ) ),
							'field_options' => array( 'type' => 'string' ),
						),
					),
				),
				'button_text'   => array( 'type' => 'string', 'description' => __( 'Submit button text.', 'elementor-mcp' ) ),
				'email_to'      => array( 'type' => 'string', 'description' => __( 'Email recipient.', 'elementor-mcp' ) ),
				'email_subject' => array( 'type' => 'string', 'description' => __( 'Email subject.', 'elementor-mcp' ) ),
			),
			array( 'form_name' ),
			'form',
			array( 'button_text' => 'Send' )
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
			__( 'Adds an Elementor Pro countdown timer widget.', 'elementor-mcp' ),
			array(
				'countdown_type' => array( 'type' => 'string', 'enum' => array( 'due_date', 'evergreen' ), 'description' => __( 'Countdown mode.', 'elementor-mcp' ) ),
				'due_date'       => array( 'type' => 'string', 'description' => __( 'Due date in Y-m-d H:i format.', 'elementor-mcp' ) ),
				'show_days'      => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show days.', 'elementor-mcp' ) ),
				'show_hours'     => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show hours.', 'elementor-mcp' ) ),
				'show_minutes'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show minutes.', 'elementor-mcp' ) ),
				'show_seconds'   => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show seconds.', 'elementor-mcp' ) ),
			),
			array(),
			'countdown',
			array(
				'countdown_type' => 'due_date',
				'show_days'      => 'yes',
				'show_hours'     => 'yes',
				'show_minutes'   => 'yes',
				'show_seconds'   => 'yes',
			)
		);
	}

	private function register_add_price_table(): void {
		$this->register_convenience_tool(
			'add-price-table',
			__( 'Add Price Table (Pro)', 'elementor-mcp' ),
			__( 'Adds an Elementor Pro price table widget for pricing page layouts.', 'elementor-mcp' ),
			array(
				'heading'         => array( 'type' => 'string', 'description' => __( 'Plan name/heading.', 'elementor-mcp' ) ),
				'sub_heading'     => array( 'type' => 'string', 'description' => __( 'Sub-heading text.', 'elementor-mcp' ) ),
				'currency_symbol' => array( 'type' => 'string', 'enum' => array( 'dollar', 'euro', 'pound', 'yen', 'custom' ), 'description' => __( 'Currency symbol preset.', 'elementor-mcp' ) ),
				'price'           => array( 'type' => 'string', 'description' => __( 'Price amount.', 'elementor-mcp' ) ),
				'period'          => array( 'type' => 'string', 'description' => __( 'Billing period (e.g. "/month").', 'elementor-mcp' ) ),
				'features_list'   => array( 'type' => 'array', 'description' => __( 'Feature list array.', 'elementor-mcp' ) ),
				'button_text'     => array( 'type' => 'string', 'description' => __( 'CTA button text.', 'elementor-mcp' ) ),
				'link'            => array( 'type' => 'object', 'description' => __( 'Button link object with url key.', 'elementor-mcp' ) ),
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
			__( 'Adds an Elementor Pro flip box with front/back sides, icon, and animation effects.', 'elementor-mcp' ),
			array(
				'title_text_a'       => array( 'type' => 'string', 'description' => __( 'Front side title.', 'elementor-mcp' ) ),
				'description_text_a' => array( 'type' => 'string', 'description' => __( 'Front side description.', 'elementor-mcp' ) ),
				'title_text_b'       => array( 'type' => 'string', 'description' => __( 'Back side title.', 'elementor-mcp' ) ),
				'description_text_b' => array( 'type' => 'string', 'description' => __( 'Back side description.', 'elementor-mcp' ) ),
				'graphic_element'    => array( 'type' => 'string', 'enum' => array( 'none', 'image', 'icon' ), 'description' => __( 'Front graphic type.', 'elementor-mcp' ) ),
				'selected_icon'      => array( 'type' => 'object', 'description' => __( 'Icon object.', 'elementor-mcp' ) ),
				'button_text'        => array( 'type' => 'string', 'description' => __( 'Back button text.', 'elementor-mcp' ) ),
				'link'               => array( 'type' => 'object', 'description' => __( 'Link object with url key.', 'elementor-mcp' ) ),
				'flip_effect'        => array( 'type' => 'string', 'enum' => array( 'flip', 'slide', 'push', 'zoom-in', 'zoom-out', 'fade' ), 'description' => __( 'Flip animation effect.', 'elementor-mcp' ) ),
				'flip_direction'     => array( 'type' => 'string', 'enum' => array( 'left', 'right', 'up', 'down' ), 'description' => __( 'Flip direction.', 'elementor-mcp' ) ),
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
			__( 'Adds a full-width slides/slider widget with heading, description, button, and background per slide.', 'elementor-mcp' ),
			array(
				'slides'           => array(
					'type'        => 'array',
					'description' => __( 'Array of slide items.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'heading'          => array( 'type' => 'string' ),
							'description'      => array( 'type' => 'string' ),
							'button_text'      => array( 'type' => 'string' ),
							'link'             => array( 'type' => 'object' ),
							'background_color' => array( 'type' => 'string' ),
							'background_image' => array( 'type' => 'object' ),
						),
					),
				),
				'navigation'       => array( 'type' => 'string', 'enum' => array( 'both', 'arrows', 'dots', 'none' ), 'description' => __( 'Navigation type.', 'elementor-mcp' ) ),
				'autoplay'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay. Default: yes.', 'elementor-mcp' ) ),
				'autoplay_speed'   => array( 'type' => 'integer', 'description' => __( 'Autoplay interval in ms. Default: 5000.', 'elementor-mcp' ) ),
				'infinite'         => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Infinite loop. Default: yes.', 'elementor-mcp' ) ),
				'transition'       => array( 'type' => 'string', 'enum' => array( 'slide', 'fade' ), 'description' => __( 'Transition effect.', 'elementor-mcp' ) ),
				'transition_speed' => array( 'type' => 'integer', 'description' => __( 'Transition speed in ms.', 'elementor-mcp' ) ),
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
			__( 'Adds a testimonial carousel/slider widget with multiple testimonials.', 'elementor-mcp' ),
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
				'slides_per_view' => array( 'type' => 'string', 'enum' => array( '1', '2', '3', '4' ), 'description' => __( 'Slides visible at once.', 'elementor-mcp' ) ),
				'autoplay'        => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Autoplay. Default: yes.', 'elementor-mcp' ) ),
				'autoplay_speed'  => array( 'type' => 'integer', 'description' => __( 'Autoplay interval in ms.', 'elementor-mcp' ) ),
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
			__( 'Adds an advanced gallery widget with grid, justified, or masonry layout.', 'elementor-mcp' ),
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
				'columns'        => array( 'type' => 'integer', 'description' => __( 'Number of columns. Default: 4.', 'elementor-mcp' ) ),
				'gap'            => array( 'type' => 'object', 'description' => __( 'Gap between items: { "size": 10, "unit": "px" }.', 'elementor-mcp' ) ),
				'link_to'        => array( 'type' => 'string', 'enum' => array( 'file', 'custom', 'none' ), 'description' => __( 'Link behavior.', 'elementor-mcp' ) ),
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
			__( 'Adds a styled blockquote widget with quote text, author, and optional tweet button.', 'elementor-mcp' ),
			array(
				'blockquote_content' => array( 'type' => 'string', 'description' => __( 'Quote/blockquote text.', 'elementor-mcp' ) ),
				'author_name'        => array( 'type' => 'string', 'description' => __( 'Author/attribution name.', 'elementor-mcp' ) ),
				'blockquote_skin'    => array( 'type' => 'string', 'enum' => array( 'border', 'quotation', 'boxed', 'clean' ), 'description' => __( 'Skin variant. Default: border.', 'elementor-mcp' ) ),
				'tweet_button'       => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Show tweet button.', 'elementor-mcp' ) ),
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
			__( 'Adds a Lottie animation widget from an external URL or media file.', 'elementor-mcp' ),
			array(
				'source'              => array( 'type' => 'string', 'enum' => array( 'media_file', 'external_url' ), 'description' => __( 'Source type. Default: external_url.', 'elementor-mcp' ) ),
				'source_external_url' => array( 'type' => 'string', 'description' => __( 'External Lottie JSON URL.', 'elementor-mcp' ) ),
				'trigger'             => array( 'type' => 'string', 'enum' => array( 'arriving_to_viewport', 'on_click', 'on_hover', 'bind_to_scroll', 'none' ), 'description' => __( 'Animation trigger. Default: arriving_to_viewport.', 'elementor-mcp' ) ),
				'loop'                => array( 'type' => 'string', 'enum' => array( 'yes', '' ), 'description' => __( 'Loop animation. Default: yes.', 'elementor-mcp' ) ),
				'play_speed'          => array( 'type' => 'object', 'description' => __( 'Playback speed: { "size": 1, "unit": "px" }.', 'elementor-mcp' ) ),
			),
			array(),
			'lottie',
			array( 'source' => 'external_url', 'trigger' => 'arriving_to_viewport', 'loop' => 'yes' )
		);
	}

	private function register_add_hotspot(): void {
		$this->register_convenience_tool(
			'add-hotspot',
			__( 'Add Hotspot (Pro)', 'elementor-mcp' ),
			__( 'Adds an image hotspot widget with clickable/hoverable points on an image.', 'elementor-mcp' ),
			array(
				'image'   => array( 'type' => 'object', 'description' => __( 'Background image object with url and optional id.', 'elementor-mcp' ) ),
				'hotspot' => array(
					'type'        => 'array',
					'description' => __( 'Array of hotspot items.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'hotspot_label'           => array( 'type' => 'string' ),
							'hotspot_link'            => array( 'type' => 'object' ),
							'hotspot_icon'            => array( 'type' => 'object' ),
							'hotspot_horizontal'      => array( 'type' => 'string', 'description' => __( 'left or right.', 'elementor-mcp' ) ),
							'hotspot_offset_x'        => array( 'type' => 'object' ),
							'hotspot_vertical'        => array( 'type' => 'string', 'description' => __( 'top or bottom.', 'elementor-mcp' ) ),
							'hotspot_offset_y'        => array( 'type' => 'object' ),
							'hotspot_tooltip_content' => array( 'type' => 'string' ),
						),
					),
				),
			),
			array( 'image', 'hotspot' ),
			'hotspot'
		);
	}
}

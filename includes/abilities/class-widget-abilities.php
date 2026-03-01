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

		// Pro widget convenience tools (only if Pro is active).
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$this->register_add_form();
			$this->register_add_posts_grid();
			$this->register_add_countdown();
			$this->register_add_price_table();
			$this->register_add_flip_box();
			$this->register_add_animated_headline();
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
}

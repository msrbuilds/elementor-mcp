<?php
/**
 * Atomic widget MCP abilities for Elementor 4.0+.
 *
 * Registers universal add/update tools plus convenience shortcut tools
 * for atomic widgets (e-heading, e-paragraph, e-button, e-image, etc.).
 * Only registers when Elementor >= 4.0 is active.
 *
 * @package Elementor_MCP
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements atomic widget abilities.
 *
 * @since 1.5.0
 */
class Elementor_MCP_Atomic_Widget_Abilities {

	/** @var Elementor_MCP_Data */
	private $data;

	/** @var Elementor_MCP_Element_Factory */
	private $factory;

	/** @var string[] */
	private $ability_names = array();

	/**
	 * @param Elementor_MCP_Data            $data    The data access layer.
	 * @param Elementor_MCP_Element_Factory $factory The element factory.
	 */
	public function __construct( Elementor_MCP_Data $data, Elementor_MCP_Element_Factory $factory ) {
		$this->data    = $data;
		$this->factory = $factory;
	}

	/** @return string[] */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Registers all atomic widget abilities.
	 *
	 * Skips registration entirely if Elementor < 4.0.
	 */
	public function register(): void {
		if ( ! Elementor_MCP_Atomic_Props::is_atomic_supported() ) {
			return;
		}

		$this->register_add_atomic_widget();
		$this->register_update_atomic_widget();
		$this->register_add_atomic_heading();
		$this->register_add_atomic_paragraph();
		$this->register_add_atomic_button();
		$this->register_add_atomic_image();
		$this->register_add_atomic_svg();
		$this->register_add_atomic_youtube();
		$this->register_add_atomic_video();
		$this->register_add_atomic_divider();
	}

	// =========================================================================
	// Permission check
	// =========================================================================

	/**
	 * @param array $input Input parameters.
	 * @return true|\WP_Error
	 */
	public function check_edit_permission( $input ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to edit posts.', 'elementor-mcp' ) );
		}

		$post_id = $input['post_id'] ?? 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'elementor-mcp' ) );
		}

		return true;
	}

	// =========================================================================
	// Universal tools
	// =========================================================================

	private function register_add_atomic_widget(): void {
		$name                  = 'elementor-mcp/add-atomic-widget';
		$this->ability_names[] = $name;

		elementor_mcp_register_ability(
			$name,
			array(
				'label'               => __( 'Add Atomic Widget', 'elementor-mcp' ),
				'description'         => __( 'Adds any Elementor 4.0+ atomic widget to a container. Settings must use the $$type prop format. For simpler usage, prefer the convenience tools (add-atomic-heading, etc.).', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_add_atomic_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'elementor-mcp' ) ),
						'parent_id'   => array( 'type' => 'string', 'description' => __( 'Parent container element ID.', 'elementor-mcp' ) ),
						'position'    => array( 'type' => 'integer', 'description' => __( 'Insert position. -1 = append.', 'elementor-mcp' ) ),
						'widget_type' => array( 'type' => 'string', 'description' => __( 'Atomic widget type name (e.g. e-heading, e-button).', 'elementor-mcp' ) ),
						'settings'    => array( 'type' => 'object', 'description' => __( 'Widget settings with $$type-wrapped values.', 'elementor-mcp' ) ),
					),
					'required'   => array( 'post_id', 'parent_id', 'widget_type' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'element_id' => array( 'type' => 'string' ) ),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_atomic_widget( $input ) {
		$post_id     = absint( $input['post_id'] ?? 0 );
		$parent_id   = sanitize_text_field( $input['parent_id'] ?? '' );
		$position    = (int) ( $input['position'] ?? -1 );
		$widget_type = sanitize_text_field( $input['widget_type'] ?? '' );
		$settings    = $input['settings'] ?? array();

		if ( empty( $widget_type ) ) {
			return new \WP_Error( 'missing_widget_type', __( 'widget_type is required.', 'elementor-mcp' ) );
		}

		$element = $this->factory->create_atomic_widget( $widget_type, $settings );

		$page_data = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$inserted = $this->data->insert_element( $page_data, $parent_id, $element, $position );
		if ( is_wp_error( $inserted ) ) {
			return $inserted;
		}

		$save = $this->data->save_page_data( $post_id, $inserted );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		return array( 'element_id' => $element['id'] );
	}

	private function register_update_atomic_widget(): void {
		$name                  = 'elementor-mcp/update-atomic-widget';
		$this->ability_names[] = $name;

		elementor_mcp_register_ability(
			$name,
			array(
				'label'               => __( 'Update Atomic Widget', 'elementor-mcp' ),
				'description'         => __( 'Updates settings on an existing Elementor 4.0+ atomic widget. Performs a partial merge — only provided keys are changed.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_update_atomic_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'elementor-mcp' ) ),
						'element_id' => array( 'type' => 'string', 'description' => __( 'The element ID to update.', 'elementor-mcp' ) ),
						'settings'   => array( 'type' => 'object', 'description' => __( 'Partial settings to merge ($$type-wrapped values).', 'elementor-mcp' ) ),
					),
					'required'   => array( 'post_id', 'element_id', 'settings' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'success' => array( 'type' => 'boolean' ) ),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_update_atomic_widget( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $input['element_id'] ?? '' );
		$settings   = $input['settings'] ?? array();

		$page_data = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );
		if ( is_wp_error( $updated ) ) {
			return $updated;
		}

		$save = $this->data->save_page_data( $post_id, $updated );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		return array( 'success' => true );
	}

	// =========================================================================
	// Convenience tools
	// =========================================================================

	/**
	 * Shared registration for atomic convenience tools.
	 *
	 * @param string   $name         Tool name without prefix.
	 * @param string   $label        Human-readable label.
	 * @param string   $description  Tool description.
	 * @param array    $extra_props  Additional JSON Schema properties.
	 * @param array    $required     Additional required fields.
	 * @param string   $widget_type  The atomic widget type (e.g. 'e-heading').
	 * @param callable $settings_fn  Builds $$type settings from flat input.
	 */
	private function register_atomic_convenience(
		string $name,
		string $label,
		string $description,
		array $extra_props,
		array $required,
		string $widget_type,
		callable $settings_fn
	): void {
		$full_name             = 'elementor-mcp/' . $name;
		$this->ability_names[] = $full_name;

		$base_props = array(
			'post_id'   => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'elementor-mcp' ) ),
			'parent_id' => array( 'type' => 'string', 'description' => __( 'Parent container element ID (e-flexbox or e-div-block).', 'elementor-mcp' ) ),
			'position'  => array( 'type' => 'integer', 'description' => __( 'Insert position. -1 = append.', 'elementor-mcp' ) ),
		);

		$all_required = array_unique( array_merge( array( 'post_id', 'parent_id' ), $required ) );

		elementor_mcp_register_ability(
			$full_name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => 'elementor-mcp',
				'execute_callback'    => function ( $input ) use ( $widget_type, $settings_fn ) {
					$settings = $settings_fn( $input );
					$element  = $this->factory->create_atomic_widget( $widget_type, $settings );

					// Apply styles if style params are present.
					$common_css = Elementor_MCP_Atomic_Styles::build_common_props( $input );
					if ( ! empty( $common_css ) ) {
						$style = Elementor_MCP_Atomic_Styles::create_local_class( $element['id'], $common_css );
						Elementor_MCP_Atomic_Styles::apply_to_element( $element, $style['class_id'], $style['style_def'] );
					}

					$post_id   = absint( $input['post_id'] ?? 0 );
					$parent_id = sanitize_text_field( $input['parent_id'] ?? '' );
					$position  = (int) ( $input['position'] ?? -1 );

					$page_data = $this->data->get_page_data( $post_id );
					if ( is_wp_error( $page_data ) ) {
						return $page_data;
					}

					$inserted = $this->data->insert_element( $page_data, $parent_id, $element, $position );
					if ( is_wp_error( $inserted ) ) {
						return $inserted;
					}

					$save = $this->data->save_page_data( $post_id, $inserted );
					if ( is_wp_error( $save ) ) {
						return $save;
					}

					return array( 'element_id' => $element['id'] );
				},
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array_merge( $base_props, $extra_props ),
					'required'   => $all_required,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array( 'element_id' => array( 'type' => 'string' ) ),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	// -------------------------------------------------------------------------

	private function register_add_atomic_heading(): void {
		$this->register_atomic_convenience(
			'add-atomic-heading',
			__( 'Add Atomic Heading', 'elementor-mcp' ),
			__( 'Adds an Elementor 4.0 atomic heading element. Accepts plain text and tag; $$type wrapping is handled automatically.', 'elementor-mcp' ),
			array(
				'title'  => array( 'type' => 'string', 'description' => __( 'Heading text content.', 'elementor-mcp' ) ),
				'tag'    => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'description' => __( 'HTML tag. Default: h2.', 'elementor-mcp' ) ),
				'link'   => array( 'type' => 'string', 'description' => __( 'Optional URL to link the heading.', 'elementor-mcp' ) ),
				'css_id' => array( 'type' => 'string', 'description' => __( 'Optional CSS ID for the element.', 'elementor-mcp' ) ),
			),
			array(),
			'e-heading',
			function ( $input ) {
				$settings = array();
				$settings['title'] = Elementor_MCP_Atomic_Props::html( sanitize_text_field( $input['title'] ?? 'Heading' ) );
				$settings['tag']   = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['tag'] ?? 'h2' ) );

				if ( ! empty( $input['link'] ) ) {
					$settings['link'] = Elementor_MCP_Atomic_Props::link( esc_url_raw( $input['link'] ) );
				}
				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_paragraph(): void {
		$this->register_atomic_convenience(
			'add-atomic-paragraph',
			__( 'Add Atomic Paragraph', 'elementor-mcp' ),
			__( 'Adds an Elementor 4.0 atomic paragraph element.', 'elementor-mcp' ),
			array(
				'content' => array( 'type' => 'string', 'description' => __( 'Paragraph text content.', 'elementor-mcp' ) ),
				'link'    => array( 'type' => 'string', 'description' => __( 'Optional URL to link the paragraph.', 'elementor-mcp' ) ),
				'css_id'  => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'elementor-mcp' ) ),
			),
			array(),
			'e-paragraph',
			function ( $input ) {
				$settings = array();
				$settings['text'] = Elementor_MCP_Atomic_Props::html( sanitize_text_field( $input['content'] ?? 'Paragraph text' ) );

				if ( ! empty( $input['link'] ) ) {
					$settings['link'] = Elementor_MCP_Atomic_Props::link( esc_url_raw( $input['link'] ) );
				}
				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_button(): void {
		$this->register_atomic_convenience(
			'add-atomic-button',
			__( 'Add Atomic Button', 'elementor-mcp' ),
			__( 'Adds an Elementor 4.0 atomic button element.', 'elementor-mcp' ),
			array(
				'text'         => array( 'type' => 'string', 'description' => __( 'Button label text.', 'elementor-mcp' ) ),
				'link'         => array( 'type' => 'string', 'description' => __( 'Button URL.', 'elementor-mcp' ) ),
				'target_blank' => array( 'type' => 'boolean', 'description' => __( 'Open in new tab.', 'elementor-mcp' ) ),
				'css_id'       => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'elementor-mcp' ) ),
			),
			array(),
			'e-button',
			function ( $input ) {
				$settings = array();
				$settings['text'] = Elementor_MCP_Atomic_Props::html( sanitize_text_field( $input['text'] ?? 'Click Here' ) );

				if ( ! empty( $input['link'] ) ) {
					$target_blank = ! empty( $input['target_blank'] );
					$settings['link'] = Elementor_MCP_Atomic_Props::link( esc_url_raw( $input['link'] ), $target_blank );
				}
				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_image(): void {
		$this->register_atomic_convenience(
			'add-atomic-image',
			__( 'Add Atomic Image', 'elementor-mcp' ),
			__( 'Adds an Elementor 4.0 atomic image element. Provide either image_id (from media library) or image_url.', 'elementor-mcp' ),
			array(
				'image_id'  => array( 'type' => 'integer', 'description' => __( 'WordPress media library attachment ID.', 'elementor-mcp' ) ),
				'image_url' => array( 'type' => 'string', 'description' => __( 'Image URL (if not using media library).', 'elementor-mcp' ) ),
				'alt'       => array( 'type' => 'string', 'description' => __( 'Alt text for the image.', 'elementor-mcp' ) ),
				'link'      => array( 'type' => 'string', 'description' => __( 'Optional link URL.', 'elementor-mcp' ) ),
				'css_id'    => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'elementor-mcp' ) ),
			),
			array(),
			'e-image',
			function ( $input ) {
				$settings = array();

				$image_id  = absint( $input['image_id'] ?? 0 );
				$image_url = esc_url_raw( $input['image_url'] ?? '' );

				if ( $image_id ) {
					$url = wp_get_attachment_url( $image_id );
					$settings['image'] = Elementor_MCP_Atomic_Props::image( $image_id, $url ?: '' );
				} elseif ( $image_url ) {
					$settings['image'] = Elementor_MCP_Atomic_Props::image( 0, $image_url );
				}

				if ( ! empty( $input['alt'] ) ) {
					$settings['alt'] = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['alt'] ) );
				}
				if ( ! empty( $input['link'] ) ) {
					$settings['link'] = Elementor_MCP_Atomic_Props::link( esc_url_raw( $input['link'] ) );
				}
				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_svg(): void {
		$this->register_atomic_convenience(
			'add-atomic-svg',
			__( 'Add Atomic SVG', 'elementor-mcp' ),
			__( 'Adds an Elementor 4.0 atomic SVG element.', 'elementor-mcp' ),
			array(
				'svg_id'  => array( 'type' => 'integer', 'description' => __( 'WordPress media library SVG attachment ID.', 'elementor-mcp' ) ),
				'svg_url' => array( 'type' => 'string', 'description' => __( 'SVG URL (if not using media library).', 'elementor-mcp' ) ),
				'css_id'  => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'elementor-mcp' ) ),
			),
			array(),
			'e-svg',
			function ( $input ) {
				$settings = array();

				$svg_id  = absint( $input['svg_id'] ?? 0 );
				$svg_url = esc_url_raw( $input['svg_url'] ?? '' );

				if ( $svg_id ) {
					$url = wp_get_attachment_url( $svg_id );
					$settings['svg'] = Elementor_MCP_Atomic_Props::image( $svg_id, $url ?: '' );
				} elseif ( $svg_url ) {
					$settings['svg'] = Elementor_MCP_Atomic_Props::image( 0, $svg_url );
				}

				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_youtube(): void {
		$this->register_atomic_convenience(
			'add-atomic-youtube',
			__( 'Add Atomic YouTube', 'elementor-mcp' ),
			__( 'Adds an Elementor 4.0 atomic YouTube video element.', 'elementor-mcp' ),
			array(
				'video_url' => array( 'type' => 'string', 'description' => __( 'YouTube video URL.', 'elementor-mcp' ) ),
				'css_id'    => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'elementor-mcp' ) ),
			),
			array( 'video_url' ),
			'e-youtube',
			function ( $input ) {
				$settings = array();
				$settings['url'] = Elementor_MCP_Atomic_Props::url( esc_url_raw( $input['video_url'] ?? '' ) );

				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_video(): void {
		$this->register_atomic_convenience(
			'add-atomic-video',
			__( 'Add Atomic Video', 'elementor-mcp' ),
			__( 'Adds an Elementor 4.0 atomic self-hosted video element.', 'elementor-mcp' ),
			array(
				'video_url' => array( 'type' => 'string', 'description' => __( 'Self-hosted video URL.', 'elementor-mcp' ) ),
				'video_id'  => array( 'type' => 'integer', 'description' => __( 'Media library video attachment ID.', 'elementor-mcp' ) ),
				'css_id'    => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'elementor-mcp' ) ),
			),
			array(),
			'e-self-hosted-video',
			function ( $input ) {
				$settings = array();

				$video_id  = absint( $input['video_id'] ?? 0 );
				$video_url = esc_url_raw( $input['video_url'] ?? '' );

				if ( $video_id ) {
					$url = wp_get_attachment_url( $video_id );
					$settings['source'] = Elementor_MCP_Atomic_Props::url( $url ?: '' );
				} elseif ( $video_url ) {
					$settings['source'] = Elementor_MCP_Atomic_Props::url( $video_url );
				}

				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_divider(): void {
		$this->register_atomic_convenience(
			'add-atomic-divider',
			__( 'Add Atomic Divider', 'elementor-mcp' ),
			__( 'Adds an Elementor 4.0 atomic divider element.', 'elementor-mcp' ),
			array(
				'css_id' => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'elementor-mcp' ) ),
			),
			array(),
			'e-divider',
			function ( $input ) {
				$settings = array();

				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = Elementor_MCP_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = Elementor_MCP_Atomic_Props::classes();
				return $settings;
			}
		);
	}
}

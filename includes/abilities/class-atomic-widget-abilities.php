<?php
/**
 * Atomic widget MCP abilities for Elementor 4.0+.
 *
 * Registers universal add/update tools plus convenience shortcut tools
 * for atomic widgets (e-heading, e-paragraph, e-button, e-image, etc.).
 * Only registers when Elementor >= 4.0 is active.
 *
 * @package EMCP_Tools
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
class EMCP_Tools_Atomic_Widget_Abilities {

	/** @var EMCP_Tools_Data */
	private $data;

	/** @var EMCP_Tools_Element_Factory */
	private $factory;

	/** @var string[] */
	private $ability_names = array();

	/**
	 * @param EMCP_Tools_Data            $data    The data access layer.
	 * @param EMCP_Tools_Element_Factory $factory The element factory.
	 */
	public function __construct( EMCP_Tools_Data $data, EMCP_Tools_Element_Factory $factory ) {
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
		if ( ! EMCP_Tools_Atomic_Props::is_atomic_supported() ) {
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
			return new \WP_Error( 'forbidden', __( 'You do not have permission to edit posts.', 'emcp-tools' ) );
		}

		$post_id = $input['post_id'] ?? 0;
		if ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) {
			return new \WP_Error( 'forbidden', __( 'You do not have permission to edit this post.', 'emcp-tools' ) );
		}

		return true;
	}

	// =========================================================================
	// Universal tools
	// =========================================================================

	private function register_add_atomic_widget(): void {
		$name                  = 'emcp-tools/add-atomic-widget';
		$this->ability_names[] = $name;

		emcp_tools_register_ability(
			$name,
			array(
				'label'               => __( 'Add Atomic Widget', 'emcp-tools' ),
				'description'         => __( 'Adds any Elementor 4.0+ atomic widget to a container. Settings must use the $$type prop format. For simpler usage, prefer the convenience tools (add-atomic-heading, etc.).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_atomic_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'emcp-tools' ) ),
						'parent_id'   => array( 'type' => 'string', 'description' => __( 'Parent container element ID.', 'emcp-tools' ) ),
						'position'    => array( 'type' => 'integer', 'description' => __( 'Insert position. -1 = append.', 'emcp-tools' ) ),
						'widget_type' => array( 'type' => 'string', 'description' => __( 'Atomic widget type name (e.g. e-heading, e-button).', 'emcp-tools' ) ),
						'settings'    => array( 'type' => 'object', 'description' => __( 'Widget settings with $$type-wrapped values.', 'emcp-tools' ) ),
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
			return new \WP_Error( 'missing_widget_type', __( 'widget_type is required.', 'emcp-tools' ) );
		}

		$element = $this->factory->create_atomic_widget( $widget_type, $settings );

		$page_data = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// insert_element() mutates $page_data by reference and returns a bool;
		// save the modified $page_data, never the bool (issue #36).
		$ok = $this->data->insert_element( $page_data, $parent_id, $element, $position );
		if ( ! $ok ) {
			return new \WP_Error( 'not_found', "Parent element '{$parent_id}' not found in page {$post_id}." );
		}

		$save = $this->data->save_page_data( $post_id, $page_data );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		return array( 'element_id' => $element['id'] );
	}

	private function register_update_atomic_widget(): void {
		$name                  = 'emcp-tools/update-atomic-widget';
		$this->ability_names[] = $name;

		emcp_tools_register_ability(
			$name,
			array(
				'label'               => __( 'Update Atomic Widget', 'emcp-tools' ),
				'description'         => __( 'Updates settings on an existing Elementor 4.0+ atomic widget. Performs a partial merge — only provided keys are changed.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_atomic_widget' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'    => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'emcp-tools' ) ),
						'element_id' => array( 'type' => 'string', 'description' => __( 'The element ID to update.', 'emcp-tools' ) ),
						'settings'   => array( 'type' => 'object', 'description' => __( 'Partial settings to merge ($$type-wrapped values).', 'emcp-tools' ) ),
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

		// update_element_settings() mutates $page_data by reference and returns a
		// bool; save the modified $page_data, never the bool (issue #36).
		$updated = $this->data->update_element_settings( $page_data, $element_id, $settings );
		if ( ! $updated ) {
			return new \WP_Error( 'not_found', "Element '{$element_id}' not found in page {$post_id}." );
		}

		$save = $this->data->save_page_data( $post_id, $page_data );
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
		$full_name             = 'emcp-tools/' . $name;
		$this->ability_names[] = $full_name;

		$base_props = array(
			'post_id'   => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'emcp-tools' ) ),
			'parent_id' => array( 'type' => 'string', 'description' => __( 'Parent container element ID (e-flexbox or e-div-block).', 'emcp-tools' ) ),
			'position'  => array( 'type' => 'integer', 'description' => __( 'Insert position. -1 = append.', 'emcp-tools' ) ),
		);

		$all_required = array_unique( array_merge( array( 'post_id', 'parent_id' ), $required ) );

		emcp_tools_register_ability(
			$full_name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => 'emcp-tools',
				'execute_callback'    => function ( $input ) use ( $widget_type, $settings_fn ) {
					$settings = $settings_fn( $input );
					$element  = $this->factory->create_atomic_widget( $widget_type, $settings );

					// Apply styles if style params are present.
					$common_css = EMCP_Tools_Atomic_Styles::build_common_props( $input );
					if ( ! empty( $common_css ) ) {
						$style = EMCP_Tools_Atomic_Styles::create_local_class( $element['id'], $common_css );
						EMCP_Tools_Atomic_Styles::apply_to_element( $element, $style['class_id'], $style['style_def'] );
					}

					$post_id   = absint( $input['post_id'] ?? 0 );
					$parent_id = sanitize_text_field( $input['parent_id'] ?? '' );
					$position  = (int) ( $input['position'] ?? -1 );

					$page_data = $this->data->get_page_data( $post_id );
					if ( is_wp_error( $page_data ) ) {
						return $page_data;
					}

					$ok = $this->data->insert_element( $page_data, $parent_id, $element, $position );
					if ( ! $ok ) {
						return new \WP_Error( 'not_found', "Parent element '{$parent_id}' not found in page {$post_id}." );
					}

					$save = $this->data->save_page_data( $post_id, $page_data );
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
			__( 'Add Atomic Heading', 'emcp-tools' ),
			__( 'Adds an Elementor 4.0 atomic heading element. Accepts plain text and tag; $$type wrapping is handled automatically.', 'emcp-tools' ),
			array(
				'title'  => array( 'type' => 'string', 'description' => __( 'Heading text content.', 'emcp-tools' ) ),
				'tag'    => array( 'type' => 'string', 'enum' => array( 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ), 'description' => __( 'HTML tag. Default: h2.', 'emcp-tools' ) ),
				'link'   => array( 'type' => 'string', 'description' => __( 'Optional URL to link the heading.', 'emcp-tools' ) ),
				'css_id' => array( 'type' => 'string', 'description' => __( 'Optional CSS ID for the element.', 'emcp-tools' ) ),
			),
			array(),
			'e-heading',
			function ( $input ) {
				$settings = array();
				$settings['title'] = EMCP_Tools_Atomic_Props::html( sanitize_text_field( $input['title'] ?? 'Heading' ) );
				$settings['tag']   = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['tag'] ?? 'h2' ) );

				if ( ! empty( $input['link'] ) ) {
					$settings['link'] = EMCP_Tools_Atomic_Props::link( esc_url_raw( $input['link'] ) );
				}
				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_paragraph(): void {
		$this->register_atomic_convenience(
			'add-atomic-paragraph',
			__( 'Add Atomic Paragraph', 'emcp-tools' ),
			__( 'Adds an Elementor 4.0 atomic paragraph element.', 'emcp-tools' ),
			array(
				'content' => array( 'type' => 'string', 'description' => __( 'Paragraph text content.', 'emcp-tools' ) ),
				'link'    => array( 'type' => 'string', 'description' => __( 'Optional URL to link the paragraph.', 'emcp-tools' ) ),
				'css_id'  => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'emcp-tools' ) ),
			),
			array(),
			'e-paragraph',
			function ( $input ) {
				$settings = array();
				// The e-paragraph widget's content prop is named `paragraph`
				// (Html_V3), not `text` — see Elementor atomic-paragraph.php
				// define_props_schema() / render ($settings['paragraph']). Writing
				// `text` silently dropped the content (issue #56).
				$settings['paragraph'] = EMCP_Tools_Atomic_Props::html( sanitize_text_field( $input['content'] ?? 'Paragraph text' ) );

				if ( ! empty( $input['link'] ) ) {
					$settings['link'] = EMCP_Tools_Atomic_Props::link( esc_url_raw( $input['link'] ) );
				}
				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_button(): void {
		$this->register_atomic_convenience(
			'add-atomic-button',
			__( 'Add Atomic Button', 'emcp-tools' ),
			__( 'Adds an Elementor 4.0 atomic button element.', 'emcp-tools' ),
			array(
				'text'         => array( 'type' => 'string', 'description' => __( 'Button label text.', 'emcp-tools' ) ),
				'link'         => array( 'type' => 'string', 'description' => __( 'Button URL.', 'emcp-tools' ) ),
				'target_blank' => array( 'type' => 'boolean', 'description' => __( 'Open in new tab.', 'emcp-tools' ) ),
				'css_id'       => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'emcp-tools' ) ),
			),
			array(),
			'e-button',
			function ( $input ) {
				$settings = array();
				$settings['text'] = EMCP_Tools_Atomic_Props::html( sanitize_text_field( $input['text'] ?? 'Click Here' ) );

				if ( ! empty( $input['link'] ) ) {
					$target_blank = ! empty( $input['target_blank'] );
					$settings['link'] = EMCP_Tools_Atomic_Props::link( esc_url_raw( $input['link'] ), $target_blank );
				}
				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_image(): void {
		$this->register_atomic_convenience(
			'add-atomic-image',
			__( 'Add Atomic Image', 'emcp-tools' ),
			__( 'Adds an Elementor 4.0 atomic image element. Provide either image_id (from media library) or image_url.', 'emcp-tools' ),
			array(
				'image_id'  => array( 'type' => 'integer', 'description' => __( 'WordPress media library attachment ID.', 'emcp-tools' ) ),
				'image_url' => array( 'type' => 'string', 'description' => __( 'Image URL (if not using media library).', 'emcp-tools' ) ),
				'alt'       => array( 'type' => 'string', 'description' => __( 'Alt text for the image.', 'emcp-tools' ) ),
				'link'      => array( 'type' => 'string', 'description' => __( 'Optional link URL.', 'emcp-tools' ) ),
				'css_id'    => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'emcp-tools' ) ),
			),
			array(),
			'e-image',
			function ( $input ) {
				$settings = array();

				$image_id  = absint( $input['image_id'] ?? 0 );
				$image_url = esc_url_raw( $input['image_url'] ?? '' );

				if ( $image_id ) {
					$url = wp_get_attachment_url( $image_id );
					$settings['image'] = EMCP_Tools_Atomic_Props::image( $image_id, $url ?: '' );
				} elseif ( $image_url ) {
					$settings['image'] = EMCP_Tools_Atomic_Props::image( 0, $image_url );
				}

				if ( ! empty( $input['alt'] ) ) {
					$settings['alt'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['alt'] ) );
				}
				if ( ! empty( $input['link'] ) ) {
					$settings['link'] = EMCP_Tools_Atomic_Props::link( esc_url_raw( $input['link'] ) );
				}
				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_svg(): void {
		$this->register_atomic_convenience(
			'add-atomic-svg',
			__( 'Add Atomic SVG', 'emcp-tools' ),
			__( 'Adds an Elementor 4.0 atomic SVG element.', 'emcp-tools' ),
			array(
				'svg_id'  => array( 'type' => 'integer', 'description' => __( 'WordPress media library SVG attachment ID.', 'emcp-tools' ) ),
				'svg_url' => array( 'type' => 'string', 'description' => __( 'SVG URL (if not using media library).', 'emcp-tools' ) ),
				'css_id'  => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'emcp-tools' ) ),
			),
			array(),
			'e-svg',
			function ( $input ) {
				$settings = array();

				$svg_id  = absint( $input['svg_id'] ?? 0 );
				$svg_url = esc_url_raw( $input['svg_url'] ?? '' );

				if ( $svg_id ) {
					$url = wp_get_attachment_url( $svg_id );
					$settings['svg'] = EMCP_Tools_Atomic_Props::image( $svg_id, $url ?: '' );
				} elseif ( $svg_url ) {
					$settings['svg'] = EMCP_Tools_Atomic_Props::image( 0, $svg_url );
				}

				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_youtube(): void {
		$this->register_atomic_convenience(
			'add-atomic-youtube',
			__( 'Add Atomic YouTube', 'emcp-tools' ),
			__( 'Adds an Elementor 4.0 atomic YouTube video element.', 'emcp-tools' ),
			array(
				'video_url' => array( 'type' => 'string', 'description' => __( 'YouTube video URL.', 'emcp-tools' ) ),
				'css_id'    => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'emcp-tools' ) ),
			),
			array( 'video_url' ),
			'e-youtube',
			function ( $input ) {
				$settings = array();
				// e-youtube's video prop is `source` (a String prop), not `url`
				// (issue #56 class).
				$settings['source'] = EMCP_Tools_Atomic_Props::string( esc_url_raw( $input['video_url'] ?? '' ) );

				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_video(): void {
		$this->register_atomic_convenience(
			'add-atomic-video',
			__( 'Add Atomic Video', 'emcp-tools' ),
			__( 'Adds an Elementor 4.0 atomic self-hosted video element.', 'emcp-tools' ),
			array(
				'video_url' => array( 'type' => 'string', 'description' => __( 'Self-hosted video URL.', 'emcp-tools' ) ),
				'video_id'  => array( 'type' => 'integer', 'description' => __( 'Media library video attachment ID.', 'emcp-tools' ) ),
				'css_id'    => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'emcp-tools' ) ),
			),
			array(),
			'e-self-hosted-video',
			function ( $input ) {
				$settings = array();

				$video_id  = absint( $input['video_id'] ?? 0 );
				$video_url = esc_url_raw( $input['video_url'] ?? '' );

				if ( $video_id ) {
					$url = wp_get_attachment_url( $video_id );
					$settings['source'] = EMCP_Tools_Atomic_Props::url( $url ?: '' );
				} elseif ( $video_url ) {
					$settings['source'] = EMCP_Tools_Atomic_Props::url( $video_url );
				}

				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
				return $settings;
			}
		);
	}

	private function register_add_atomic_divider(): void {
		$this->register_atomic_convenience(
			'add-atomic-divider',
			__( 'Add Atomic Divider', 'emcp-tools' ),
			__( 'Adds an Elementor 4.0 atomic divider element.', 'emcp-tools' ),
			array(
				'css_id' => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'emcp-tools' ) ),
			),
			array(),
			'e-divider',
			function ( $input ) {
				$settings = array();

				if ( ! empty( $input['css_id'] ) ) {
					$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
				}

				$settings['classes'] = EMCP_Tools_Atomic_Props::classes();
				return $settings;
			}
		);
	}
}

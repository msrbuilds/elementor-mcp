<?php
/**
 * Atomic layout container MCP abilities for Elementor 4.0+.
 *
 * Registers tools for creating flexbox and div-block containers.
 * Only registers when Elementor >= 4.0 is active.
 *
 * @package EMCP_Tools
 * @since   1.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements atomic layout abilities.
 *
 * @since 1.5.0
 */
class EMCP_Tools_Atomic_Layout_Abilities {

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
	 * Registers all atomic layout abilities.
	 *
	 * Skips registration if Elementor < 4.0.
	 */
	public function register(): void {
		if ( ! EMCP_Tools_Atomic_Props::is_atomic_supported() ) {
			return;
		}

		$this->register_add_flexbox();
		$this->register_add_div_block();
		$this->register_detect_elementor_version();
	}

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
	// Flexbox
	// =========================================================================

	private function register_add_flexbox(): void {
		$name                  = 'emcp-tools/add-flexbox';
		$this->ability_names[] = $name;

		emcp_tools_register_ability(
			$name,
			array(
				'label'               => __( 'Add Flexbox', 'emcp-tools' ),
				'description'         => __( 'Adds an Elementor 4.0 flexbox container. Layout properties (direction, justify, align, gap) are applied as local styles automatically. Use this instead of add-container for Elementor 4.0+ sites.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_flexbox' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'         => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'emcp-tools' ) ),
						'parent_id'       => array( 'type' => 'string', 'description' => __( 'Parent element ID. Empty for top-level.', 'emcp-tools' ) ),
						'position'        => array( 'type' => 'integer', 'description' => __( 'Insert position. -1 = append.', 'emcp-tools' ) ),
						'tag'             => array( 'type' => 'string', 'enum' => array( 'div', 'header', 'section', 'article', 'aside', 'footer' ), 'description' => __( 'HTML tag. Default: div.', 'emcp-tools' ) ),
						'direction'       => array( 'type' => 'string', 'enum' => array( 'row', 'column', 'row-reverse', 'column-reverse' ), 'description' => __( 'Flex direction. Default: column.', 'emcp-tools' ) ),
						'justify'         => array( 'type' => 'string', 'enum' => array( 'flex-start', 'center', 'flex-end', 'space-between', 'space-around', 'space-evenly' ), 'description' => __( 'Justify content.', 'emcp-tools' ) ),
						'align'           => array( 'type' => 'string', 'enum' => array( 'flex-start', 'center', 'flex-end', 'stretch', 'baseline' ), 'description' => __( 'Align items.', 'emcp-tools' ) ),
						'gap'             => array( 'type' => 'number', 'description' => __( 'Gap between children (px by default).', 'emcp-tools' ) ),
						'gap_unit'        => array( 'type' => 'string', 'enum' => array( 'px', 'em', 'rem', '%', 'vw' ), 'description' => __( 'Gap unit. Default: px.', 'emcp-tools' ) ),
						'wrap'            => array( 'type' => 'string', 'enum' => array( 'nowrap', 'wrap', 'wrap-reverse' ), 'description' => __( 'Flex wrap.', 'emcp-tools' ) ),
						'css_id'          => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'emcp-tools' ) ),
						'padding'         => array( 'type' => 'number', 'description' => __( 'Padding on all sides (px by default).', 'emcp-tools' ) ),
						'background_color' => array( 'type' => 'string', 'description' => __( 'Background color (hex/rgba).', 'emcp-tools' ) ),
						'min_height'      => array( 'type' => 'number', 'description' => __( 'Minimum height (px by default).', 'emcp-tools' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id' => array( 'type' => 'string' ),
						'post_id'    => array( 'type' => 'integer' ),
					),
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
	public function execute_add_flexbox( $input ) {
		$post_id   = absint( $input['post_id'] ?? 0 );
		$parent_id = sanitize_text_field( $input['parent_id'] ?? '' );
		$position  = (int) ( $input['position'] ?? -1 );

		$settings = array();

		if ( ! empty( $input['tag'] ) ) {
			$settings['tag'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['tag'] ) );
		}
		if ( ! empty( $input['css_id'] ) ) {
			$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
		}

		// Style props extracted from input.
		$style_params = array();
		$style_keys   = array( 'direction', 'flex_direction', 'justify', 'justify_content', 'align', 'align_items', 'wrap', 'flex_wrap', 'gap', 'gap_unit', 'row_gap', 'column_gap', 'padding', 'padding_unit', 'padding_top', 'padding_right', 'padding_bottom', 'padding_left', 'margin_top', 'margin_bottom', 'background_color', 'color', 'min_height', 'width', 'border_radius' );

		foreach ( $style_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$style_params[ $key ] = $input[ $key ];
			}
		}

		$element = $this->factory->create_flexbox( $settings, array(), $style_params );

		$page_data = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		if ( ! empty( $parent_id ) ) {
			$ok = $this->data->insert_element( $page_data, $parent_id, $element, $position );
			if ( ! $ok ) {
				return new \WP_Error( 'not_found', "Parent element '{$parent_id}' not found in page {$post_id}." );
			}
		} else {
			// Top-level element.
			if ( -1 === $position || $position >= count( $page_data ) ) {
				$page_data[] = $element;
			} else {
				array_splice( $page_data, max( 0, $position ), 0, array( $element ) );
			}
		}

		$save = $this->data->save_page_data( $post_id, $page_data );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		return array(
			'element_id' => $element['id'],
			'post_id'    => $post_id,
		);
	}

	// =========================================================================
	// Div Block
	// =========================================================================

	private function register_add_div_block(): void {
		$name                  = 'emcp-tools/add-div-block';
		$this->ability_names[] = $name;

		emcp_tools_register_ability(
			$name,
			array(
				'label'               => __( 'Add Div Block', 'emcp-tools' ),
				'description'         => __( 'Adds an Elementor 4.0 div-block container (block flow layout). Use for non-flex containers.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_div_block' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'          => array( 'type' => 'integer', 'description' => __( 'The post/page ID.', 'emcp-tools' ) ),
						'parent_id'        => array( 'type' => 'string', 'description' => __( 'Parent element ID. Empty for top-level.', 'emcp-tools' ) ),
						'position'         => array( 'type' => 'integer', 'description' => __( 'Insert position. -1 = append.', 'emcp-tools' ) ),
						'tag'              => array( 'type' => 'string', 'enum' => array( 'div', 'header', 'section', 'article', 'aside', 'footer' ), 'description' => __( 'HTML tag. Default: div.', 'emcp-tools' ) ),
						'css_id'           => array( 'type' => 'string', 'description' => __( 'Optional CSS ID.', 'emcp-tools' ) ),
						'padding'          => array( 'type' => 'number', 'description' => __( 'Padding on all sides (px by default).', 'emcp-tools' ) ),
						'background_color' => array( 'type' => 'string', 'description' => __( 'Background color (hex/rgba).', 'emcp-tools' ) ),
					),
					'required'   => array( 'post_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id' => array( 'type' => 'string' ),
						'post_id'    => array( 'type' => 'integer' ),
					),
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
	public function execute_add_div_block( $input ) {
		$post_id   = absint( $input['post_id'] ?? 0 );
		$parent_id = sanitize_text_field( $input['parent_id'] ?? '' );
		$position  = (int) ( $input['position'] ?? -1 );

		$settings = array();

		if ( ! empty( $input['tag'] ) ) {
			$settings['tag'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['tag'] ) );
		}
		if ( ! empty( $input['css_id'] ) ) {
			$settings['_cssid'] = EMCP_Tools_Atomic_Props::string( sanitize_text_field( $input['css_id'] ) );
		}

		$style_params = array();
		$style_keys   = array( 'padding', 'padding_unit', 'padding_top', 'padding_right', 'padding_bottom', 'padding_left', 'margin_top', 'margin_bottom', 'background_color', 'color', 'min_height', 'width', 'border_radius' );

		foreach ( $style_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$style_params[ $key ] = $input[ $key ];
			}
		}

		$element = $this->factory->create_div_block( $settings, array(), $style_params );

		$page_data = $this->data->get_page_data( $post_id );
		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// insert_element() mutates $page_data by reference and returns a bool;
		// save the modified $page_data, never the bool (issue #36).
		if ( ! empty( $parent_id ) ) {
			$ok = $this->data->insert_element( $page_data, $parent_id, $element, $position );
			if ( ! $ok ) {
				return new \WP_Error( 'not_found', "Parent element '{$parent_id}' not found in page {$post_id}." );
			}
		} elseif ( -1 === $position || $position >= count( $page_data ) ) {
			$page_data[] = $element;
		} else {
			array_splice( $page_data, max( 0, $position ), 0, array( $element ) );
		}

		$save = $this->data->save_page_data( $post_id, $page_data );
		if ( is_wp_error( $save ) ) {
			return $save;
		}

		return array(
			'element_id' => $element['id'],
			'post_id'    => $post_id,
		);
	}

	// =========================================================================
	// Detect version (always registers, even on < 4.0)
	// =========================================================================

	private function register_detect_elementor_version(): void {
		$name                  = 'emcp-tools/detect-elementor-version';
		$this->ability_names[] = $name;

		emcp_tools_register_ability(
			$name,
			array(
				'label'               => __( 'Detect Elementor Version', 'emcp-tools' ),
				'description'         => __( 'Returns the Elementor version and whether atomic elements (v4.0+) are supported. Call this first to decide whether to use legacy tools (add-free-widget, add-container) or atomic tools (add-atomic-heading, add-flexbox).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => function () {
					$core_version = defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : 'unknown';
					$pro_version  = defined( 'ELEMENTOR_PRO_VERSION' ) ? ELEMENTOR_PRO_VERSION : null;

					return array(
						'elementor_version'     => $core_version,
						'elementor_pro_version' => $pro_version,
						'supports_atomic'       => EMCP_Tools_Atomic_Props::is_atomic_supported(),
						'recommended_mode'      => EMCP_Tools_Atomic_Props::is_atomic_supported() ? 'atomic' : 'legacy',
					);
				},
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' ) ? true : new \WP_Error( 'forbidden', __( 'Insufficient permissions.', 'emcp-tools' ) );
				},
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => new \stdClass(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'elementor_version'     => array( 'type' => 'string' ),
						'elementor_pro_version' => array( 'type' => 'string' ),
						'supports_atomic'       => array( 'type' => 'boolean' ),
						'recommended_mode'      => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}
}

<?php
/**
 * Template MCP abilities for Elementor.
 *
 * Registers 2 tools for saving and applying Elementor templates.
 *
 * @package Elementor_MCP
 * @since   1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the template abilities.
 *
 * @since 1.0.0
 */
class Elementor_MCP_Template_Abilities {

	/**
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * @var Elementor_MCP_Element_Factory
	 */
	private $factory;

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
			'elementor-mcp/save-as-template',
			'elementor-mcp/apply-template',
		);
	}

	/**
	 * Registers all template abilities.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		$this->register_save_as_template();
		$this->register_apply_template();
	}

	/**
	 * Permission check for template operations.
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

	// -------------------------------------------------------------------------
	// save-as-template
	// -------------------------------------------------------------------------

	private function register_save_as_template(): void {
		wp_register_ability(
			'elementor-mcp/save-as-template',
			array(
				'label'               => __( 'Save As Template', 'elementor-mcp' ),
				'description'         => __( 'Saves a page or a specific element as a reusable Elementor template.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_save_as_template' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'       => array(
							'type'        => 'integer',
							'description' => __( 'The source post/page ID.', 'elementor-mcp' ),
						),
						'element_id'    => array(
							'type'        => 'string',
							'description' => __( 'Specific element ID to save. Omit to save the entire page.', 'elementor-mcp' ),
						),
						'title'         => array(
							'type'        => 'string',
							'description' => __( 'Template title.', 'elementor-mcp' ),
						),
						'template_type' => array(
							'type'        => 'string',
							'enum'        => array( 'page', 'section', 'container' ),
							'description' => __( 'Template type. Default: page.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'title' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'template_id' => array( 'type' => 'integer' ),
						'title'       => array( 'type' => 'string' ),
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
	 * Executes the save-as-template ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_save_as_template( $input ) {
		$post_id       = absint( $input['post_id'] ?? 0 );
		$element_id    = sanitize_text_field( $input['element_id'] ?? '' );
		$title         = sanitize_text_field( $input['title'] ?? '' );
		$template_type = sanitize_key( $input['template_type'] ?? 'page' );

		if ( ! $post_id || empty( $title ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id and title are required.', 'elementor-mcp' ) );
		}

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Get the elements to save.
		if ( ! empty( $element_id ) ) {
			$element = $this->data->find_element_by_id( $page_data, $element_id );
			if ( null === $element ) {
				return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
			}
			$elements_data = array( $element );
		} else {
			$elements_data = $page_data;
		}

		// Create the template post in Elementor's library CPT.
		$template_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_status' => 'publish',
				'post_type'   => 'elementor_library',
				'meta_input'  => array(
					'_elementor_edit_mode'     => 'builder',
					'_elementor_template_type' => $template_type,
				),
			),
			true
		);

		if ( is_wp_error( $template_id ) ) {
			return $template_id;
		}

		// Set the template type taxonomy.
		wp_set_object_terms( $template_id, $template_type, 'elementor_library_type' );

		// Save the element data to the template.
		$this->data->save_page_data( $template_id, $elements_data );

		return array(
			'template_id' => $template_id,
			'title'       => $title,
		);
	}

	// -------------------------------------------------------------------------
	// apply-template
	// -------------------------------------------------------------------------

	private function register_apply_template(): void {
		wp_register_ability(
			'elementor-mcp/apply-template',
			array(
				'label'               => __( 'Apply Template', 'elementor-mcp' ),
				'description'         => __( 'Applies a saved Elementor template to a page at a given position, inserting its elements with fresh IDs.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_apply_template' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'     => array(
							'type'        => 'integer',
							'description' => __( 'The target post/page ID.', 'elementor-mcp' ),
						),
						'template_id' => array(
							'type'        => 'integer',
							'description' => __( 'The template post ID to apply.', 'elementor-mcp' ),
						),
						'parent_id'   => array(
							'type'        => 'string',
							'description' => __( 'Parent container ID. Empty for top-level.', 'elementor-mcp' ),
						),
						'position'    => array(
							'type'        => 'integer',
							'description' => __( 'Insert position. -1 = append.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'template_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'elements_added' => array( 'type' => 'integer' ),
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
	 * Executes the apply-template ability.
	 *
	 * @since 1.0.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_apply_template( $input ) {
		$post_id     = absint( $input['post_id'] ?? 0 );
		$template_id = absint( $input['template_id'] ?? 0 );
		$parent_id   = sanitize_text_field( $input['parent_id'] ?? '' );
		$position    = intval( $input['position'] ?? -1 );

		if ( ! $post_id || ! $template_id ) {
			return new \WP_Error( 'missing_params', __( 'post_id and template_id are required.', 'elementor-mcp' ) );
		}

		// Get the template elements.
		$template_data = $this->data->get_page_data( $template_id );

		if ( is_wp_error( $template_data ) ) {
			return $template_data;
		}

		if ( empty( $template_data ) ) {
			return new \WP_Error( 'empty_template', __( 'Template has no elements.', 'elementor-mcp' ) );
		}

		// Get the target page data.
		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		// Reassign IDs to prevent collisions.
		$template_data = $this->data->reassign_ids( $template_data );
		$count         = $this->data->count_elements( $template_data );

		// Insert template elements.
		if ( ! empty( $parent_id ) ) {
			// Insert each template element into the parent.
			foreach ( $template_data as $i => $element ) {
				$pos = ( $position >= 0 ) ? $position + $i : -1;
				$this->data->insert_element( $page_data, $parent_id, $element, $pos );
			}
		} else {
			// Top-level insertion.
			if ( $position < 0 || $position >= count( $page_data ) ) {
				$page_data = array_merge( $page_data, $template_data );
			} else {
				array_splice( $page_data, $position, 0, $template_data );
			}
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success'        => true,
			'elements_added' => $count,
		);
	}

}

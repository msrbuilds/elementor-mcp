<?php
/**
 * Custom code MCP abilities for Elementor.
 *
 * Registers tools for adding custom CSS, JavaScript, and site-wide
 * code snippets via the MCP Tools for Elementor server.
 *
 * @package Elementor_MCP
 * @since   1.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the custom code abilities.
 *
 * @since 1.3.0
 */
class Elementor_MCP_Custom_Code_Abilities {

	/**
	 * @var Elementor_MCP_Data
	 */
	private $data;

	/**
	 * @var Elementor_MCP_Element_Factory
	 */
	private $factory;

	/**
	 * Dynamically built list of ability names.
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
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
	 * @since 1.3.0
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return $this->ability_names;
	}

	/**
	 * Registers all custom code abilities.
	 *
	 * @since 1.3.0
	 */
	public function register(): void {
		// Custom JS works with free Elementor (uses HTML widget).
		$this->register_add_custom_js();

		// Pro-only tools require Elementor Pro.
		if ( defined( 'ELEMENTOR_PRO_VERSION' ) ) {
			$this->register_add_custom_css();
			$this->register_add_code_snippet();
			$this->register_list_code_snippets();
		}
	}

	// -------------------------------------------------------------------------
	// Permission callbacks
	// -------------------------------------------------------------------------

	/**
	 * Permission check for page/element editing.
	 *
	 * @since 1.3.0
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

	/**
	 * Permission check for creating site-wide code snippets.
	 *
	 * @since 1.3.0
	 *
	 * @return bool
	 */
	public function check_snippet_permission(): bool {
		return current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	/**
	 * Permission check for listing snippets (read-only).
	 *
	 * @since 1.3.0
	 *
	 * @return bool
	 */
	public function check_manage_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// -------------------------------------------------------------------------
	// add-custom-css (Pro only)
	// -------------------------------------------------------------------------

	/**
	 * Registers the add-custom-css ability.
	 *
	 * @since 1.3.0
	 */
	private function register_add_custom_css(): void {
		$this->ability_names[] = 'elementor-mcp/add-custom-css';

		elementor_mcp_register_ability(
			'elementor-mcp/add-custom-css',
			array(
				'label'               => __( 'Add Custom CSS', 'elementor-mcp' ),
				'description'         => __( 'Adds custom CSS to a specific element or to the entire page. Requires Elementor Pro. For element-level CSS, use the keyword "selector" as a placeholder for the element\'s CSS wrapper (e.g. "selector .heading { color: red; }" or "selector:hover { transform: scale(1.05); }"). For page-level CSS, omit element_id. Appends to existing CSS by default; set replace=true to overwrite.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_add_custom_css' ),
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
							'description' => __( 'Optional element ID to apply CSS to. If omitted, CSS is applied at the page level.', 'elementor-mcp' ),
						),
						'css'        => array(
							'type'        => 'string',
							'description' => __( 'CSS rules to add. Use "selector" as the element wrapper placeholder for element-level CSS.', 'elementor-mcp' ),
						),
						'replace'    => array(
							'type'        => 'boolean',
							'description' => __( 'If true, replaces existing custom CSS instead of appending. Default: false.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'css' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'target'  => array( 'type' => 'string' ),
						'css'     => array( 'type' => 'string' ),
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
	 * Executes the add-custom-css ability.
	 *
	 * @since 1.3.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_custom_css( $input ) {
		$post_id    = absint( $input['post_id'] ?? 0 );
		$element_id = sanitize_text_field( $input['element_id'] ?? '' );
		$css        = $input['css'] ?? '';
		$replace    = ! empty( $input['replace'] );

		if ( ! $post_id || empty( $css ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id and css are required.', 'elementor-mcp' ) );
		}

		// Basic sanitization: strip PHP tags and script tags.
		$css = preg_replace( '/<\?(=|php)(.+?)\?>/is', '', $css );
		$css = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $css );

		if ( ! empty( $element_id ) ) {
			// Element-level custom CSS.
			$page_data = $this->data->get_page_data( $post_id );

			if ( is_wp_error( $page_data ) ) {
				return $page_data;
			}

			$element = $this->data->find_element_by_id( $page_data, $element_id );

			if ( null === $element ) {
				return new \WP_Error( 'element_not_found', __( 'Element not found.', 'elementor-mcp' ) );
			}

			$existing_css = $element['settings']['custom_css'] ?? '';
			$new_css      = $replace ? $css : trim( $existing_css . "\n" . $css );

			$updated = $this->data->update_element_settings(
				$page_data,
				$element_id,
				array( 'custom_css' => $new_css )
			);

			if ( ! $updated ) {
				return new \WP_Error( 'update_failed', __( 'Failed to update element settings.', 'elementor-mcp' ) );
			}

			$result = $this->data->save_page_data( $post_id, $page_data );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return array(
				'success' => true,
				'target'  => 'element:' . $element_id,
				'css'     => $new_css,
			);
		}

		// Page-level custom CSS.
		$page_settings = $this->data->get_page_settings( $post_id );

		if ( is_wp_error( $page_settings ) ) {
			return $page_settings;
		}

		$existing_css = $page_settings['custom_css'] ?? '';
		$new_css      = $replace ? $css : trim( $existing_css . "\n" . $css );

		$result = $this->data->save_page_settings(
			$post_id,
			array( 'custom_css' => $new_css )
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'success' => true,
			'target'  => 'page:' . $post_id,
			'css'     => $new_css,
		);
	}

	// -------------------------------------------------------------------------
	// add-custom-js (Free — uses HTML widget)
	// -------------------------------------------------------------------------

	/**
	 * Registers the add-custom-js ability.
	 *
	 * @since 1.3.0
	 */
	private function register_add_custom_js(): void {
		$this->ability_names[] = 'elementor-mcp/add-custom-js';

		elementor_mcp_register_ability(
			'elementor-mcp/add-custom-js',
			array(
				'label'               => __( 'Add Custom JavaScript', 'elementor-mcp' ),
				'description'         => __( 'Adds a custom JavaScript snippet to a page by inserting an HTML widget containing a <script> tag. Works with free Elementor (no Pro required). The JS code is automatically wrapped in <script> tags — do NOT include them yourself. Use wrap_dom_ready=true to wrap in a DOMContentLoaded listener. For site-wide JS, use add-code-snippet instead (requires Pro).', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_add_custom_js' ),
				'permission_callback' => array( $this, 'check_edit_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'elementor-mcp' ),
						),
						'parent_id'      => array(
							'type'        => 'string',
							'description' => __( 'Parent container element ID.', 'elementor-mcp' ),
						),
						'js'             => array(
							'type'        => 'string',
							'description' => __( 'JavaScript code to inject. Do NOT include <script> tags — they are added automatically.', 'elementor-mcp' ),
						),
						'position'       => array(
							'type'        => 'integer',
							'description' => __( 'Insert position within parent. -1 = append (default).', 'elementor-mcp' ),
						),
						'wrap_dom_ready' => array(
							'type'        => 'boolean',
							'description' => __( 'Wrap the code in a DOMContentLoaded listener. Default: false.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'post_id', 'parent_id', 'js' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'element_id' => array( 'type' => 'string' ),
						'post_id'    => array( 'type' => 'integer' ),
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
	 * Executes the add-custom-js ability.
	 *
	 * @since 1.3.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_custom_js( $input ) {
		$post_id        = absint( $input['post_id'] ?? 0 );
		$parent_id      = sanitize_text_field( $input['parent_id'] ?? '' );
		$js             = $input['js'] ?? '';
		$position       = intval( $input['position'] ?? -1 );
		$wrap_dom_ready = ! empty( $input['wrap_dom_ready'] );

		if ( ! $post_id || empty( $parent_id ) || empty( $js ) ) {
			return new \WP_Error( 'missing_params', __( 'post_id, parent_id, and js are required.', 'elementor-mcp' ) );
		}

		// Strip any existing script tags the caller may have included.
		$js = preg_replace( '/<\/?script[^>]*>/i', '', $js );

		// Optionally wrap in DOMContentLoaded.
		if ( $wrap_dom_ready ) {
			$js = "document.addEventListener('DOMContentLoaded', function() {\n" . $js . "\n});";
		}

		$html_content = "<script>\n" . $js . "\n</script>";

		$page_data = $this->data->get_page_data( $post_id );

		if ( is_wp_error( $page_data ) ) {
			return $page_data;
		}

		$widget = $this->factory->create_widget( 'html', array( 'html' => $html_content ) );

		$inserted = $this->data->insert_element( $page_data, $parent_id, $widget, $position );

		if ( ! $inserted ) {
			return new \WP_Error(
				'parent_not_found',
				sprintf(
					/* translators: %s: parent element ID */
					__( 'Parent element "%s" not found.', 'elementor-mcp' ),
					$parent_id
				)
			);
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return array(
			'element_id' => $widget['id'],
			'post_id'    => $post_id,
		);
	}

	// -------------------------------------------------------------------------
	// add-code-snippet (Pro only)
	// -------------------------------------------------------------------------

	/**
	 * Registers the add-code-snippet ability.
	 *
	 * @since 1.3.0
	 */
	private function register_add_code_snippet(): void {
		$this->ability_names[] = 'elementor-mcp/add-code-snippet';

		elementor_mcp_register_ability(
			'elementor-mcp/add-code-snippet',
			array(
				'label'               => __( 'Add Code Snippet', 'elementor-mcp' ),
				'description'         => __( 'Creates a site-wide Custom Code snippet using Elementor Pro. Injects CSS or JavaScript into the <head>, after <body> open, or before </body> close on ALL pages. Use this for analytics scripts, site-wide CSS overrides, meta tags, or tracking pixels. Requires Elementor Pro and manage_options capability.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_add_code_snippet' ),
				'permission_callback' => array( $this, 'check_snippet_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'         => array(
							'type'        => 'string',
							'description' => __( 'Descriptive title for the snippet (e.g. "Google Analytics", "Global CSS overrides").', 'elementor-mcp' ),
						),
						'code'          => array(
							'type'        => 'string',
							'description' => __( 'The full code to inject. Include <script>, <style>, or <meta> tags as needed.', 'elementor-mcp' ),
						),
						'location'      => array(
							'type'        => 'string',
							'enum'        => array( 'head', 'body_start', 'body_end' ),
							'description' => __( 'Where to inject: "head" = <head> tag, "body_start" = after <body>, "body_end" = before </body>. Default: head.', 'elementor-mcp' ),
						),
						'priority'      => array(
							'type'        => 'integer',
							'description' => __( 'Load order priority (1-10, lower = earlier). Default: 1.', 'elementor-mcp' ),
						),
						'status'        => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft' ),
							'description' => __( 'Post status. "publish" = active immediately. "draft" = saved but not active. Default: publish.', 'elementor-mcp' ),
						),
						'ensure_jquery' => array(
							'type'        => 'boolean',
							'description' => __( 'If true, ensures jQuery is loaded before this snippet runs. Default: false.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'title', 'code' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'snippet_id' => array( 'type' => 'integer' ),
						'title'      => array( 'type' => 'string' ),
						'location'   => array( 'type' => 'string' ),
						'priority'   => array( 'type' => 'integer' ),
						'status'     => array( 'type' => 'string' ),
						'edit_url'   => array( 'type' => 'string' ),
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
	 * Executes the add-code-snippet ability.
	 *
	 * @since 1.3.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_add_code_snippet( $input ) {
		$title         = sanitize_text_field( $input['title'] ?? '' );
		$code          = $input['code'] ?? '';
		$location_key  = sanitize_key( $input['location'] ?? 'head' );
		$priority      = absint( $input['priority'] ?? 1 );
		$status        = sanitize_key( $input['status'] ?? 'publish' );
		$ensure_jquery = ! empty( $input['ensure_jquery'] );

		if ( empty( $title ) || empty( $code ) ) {
			return new \WP_Error( 'missing_params', __( 'title and code are required.', 'elementor-mcp' ) );
		}

		// Map user-friendly location names to Elementor's internal values.
		$location_map = array(
			'head'       => 'elementor_head',
			'body_start' => 'elementor_body_start',
			'body_end'   => 'elementor_body_end',
		);

		$elementor_location = $location_map[ $location_key ] ?? 'elementor_head';

		// Clamp priority to 1-10.
		$priority = max( 1, min( 10, $priority ) );

		// Validate status.
		if ( ! in_array( $status, array( 'publish', 'draft' ), true ) ) {
			$status = 'publish';
		}

		// Create the elementor_snippet CPT post.
		$post_id = wp_insert_post(
			array(
				'post_title'  => $title,
				'post_type'   => 'elementor_snippet',
				'post_status' => $status,
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Set the custom code meta fields (matching Elementor Pro's Custom Code module).
		update_post_meta( $post_id, '_elementor_location', $elementor_location );
		update_post_meta( $post_id, '_elementor_priority', $priority );
		update_post_meta( $post_id, '_elementor_code', $code );
		update_post_meta( $post_id, '_elementor_template_type', 'code_snippet' );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

		// Set ensure_jquery extra option if requested.
		if ( $ensure_jquery ) {
			update_post_meta( $post_id, '_elementor_extra_options', array( 'ensure_jquery' ) );
		}

		$edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

		return array(
			'snippet_id' => $post_id,
			'title'      => $title,
			'location'   => $location_key,
			'priority'   => $priority,
			'status'     => $status,
			'edit_url'   => $edit_url,
		);
	}

	// -------------------------------------------------------------------------
	// list-code-snippets (Pro only)
	// -------------------------------------------------------------------------

	/**
	 * Registers the list-code-snippets ability.
	 *
	 * @since 1.3.0
	 */
	private function register_list_code_snippets(): void {
		$this->ability_names[] = 'elementor-mcp/list-code-snippets';

		elementor_mcp_register_ability(
			'elementor-mcp/list-code-snippets',
			array(
				'label'               => __( 'List Code Snippets', 'elementor-mcp' ),
				'description'         => __( 'Lists all existing Elementor Pro Custom Code snippets with their titles, locations, priorities, and statuses. Requires Elementor Pro.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_list_code_snippets' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'location' => array(
							'type'        => 'string',
							'enum'        => array( 'head', 'body_start', 'body_end' ),
							'description' => __( 'Optional filter by location.', 'elementor-mcp' ),
						),
						'status'   => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'any' ),
							'description' => __( 'Filter by post status. Default: any.', 'elementor-mcp' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'snippets' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'       => array( 'type' => 'integer' ),
									'title'    => array( 'type' => 'string' ),
									'location' => array( 'type' => 'string' ),
									'priority' => array( 'type' => 'integer' ),
									'status'   => array( 'type' => 'string' ),
									'code'     => array( 'type' => 'string' ),
									'edit_url' => array( 'type' => 'string' ),
								),
							),
						),
						'count'    => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the list-code-snippets ability.
	 *
	 * @since 1.3.0
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_list_code_snippets( $input ) {
		$location_filter = sanitize_key( $input['location'] ?? '' );
		$status_filter   = sanitize_key( $input['status'] ?? 'any' );

		$location_map = array(
			'head'       => 'elementor_head',
			'body_start' => 'elementor_body_start',
			'body_end'   => 'elementor_body_end',
		);

		$location_labels = array(
			'elementor_head'       => 'head',
			'elementor_body_start' => 'body_start',
			'elementor_body_end'   => 'body_end',
		);

		$query_args = array(
			'post_type'      => 'elementor_snippet',
			'posts_per_page' => 100,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( 'any' !== $status_filter && ! empty( $status_filter ) ) {
			$query_args['post_status'] = $status_filter;
		} else {
			$query_args['post_status'] = array( 'publish', 'draft' );
		}

		if ( ! empty( $location_filter ) && isset( $location_map[ $location_filter ] ) ) {
			$query_args['meta_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				array(
					'key'   => '_elementor_location',
					'value' => $location_map[ $location_filter ],
				),
			);
		}

		$posts    = get_posts( $query_args );
		$snippets = array();

		foreach ( $posts as $post ) {
			$raw_location = get_post_meta( $post->ID, '_elementor_location', true );
			$priority     = absint( get_post_meta( $post->ID, '_elementor_priority', true ) );
			$code         = get_post_meta( $post->ID, '_elementor_code', true );

			$snippets[] = array(
				'id'       => $post->ID,
				'title'    => $post->post_title,
				'location' => $location_labels[ $raw_location ] ?? $raw_location,
				'priority' => $priority ? $priority : 1,
				'status'   => $post->post_status,
				'code'     => $code ? $code : '',
				'edit_url' => admin_url( 'post.php?post=' . $post->ID . '&action=edit' ),
			);
		}

		return array(
			'snippets' => $snippets,
			'count'    => count( $snippets ),
		);
	}
}

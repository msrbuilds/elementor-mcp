<?php
/**
 * Custom code MCP abilities for Elementor.
 *
 * Registers tools for adding custom CSS, JavaScript, and site-wide
 * code snippets via the MCP Tools for WordPress & Page Builders server.
 *
 * @package EMCP_Tools
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
class EMCP_Tools_Custom_Code_Abilities {

	/**
	 * @var EMCP_Tools_Data
	 */
	private $data;

	/**
	 * @var EMCP_Tools_Element_Factory
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
			$this->register_update_code_snippet();
			$this->register_delete_code_snippet();
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
	 * Permission check for injecting raw JavaScript into a page.
	 *
	 * Requires the same per-post edit rights as other page edits PLUS the
	 * unfiltered_html capability, since this tool injects an executable
	 * <script> tag that WordPress would otherwise strip from a user who lacks
	 * unfiltered_html (e.g. non-super-admins on multisite).
	 *
	 * @since 1.9.1
	 *
	 * @param array|null $input The input data.
	 * @return bool
	 */
	public function check_js_permission( $input = null ): bool {
		return $this->check_edit_permission( $input ) && current_user_can( 'unfiltered_html' );
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
		$this->ability_names[] = 'emcp-tools/add-custom-css';

		emcp_tools_register_ability(
			'emcp-tools/add-custom-css',
			array(
				'label'               => __( 'Add Custom CSS', 'emcp-tools' ),
				'description'         => __( 'Adds custom CSS to a specific element or to the entire page. Requires Elementor Pro. For element-level CSS, use the keyword "selector" as a placeholder for the element\'s CSS wrapper (e.g. "selector .heading { color: red; }" or "selector:hover { transform: scale(1.05); }"). For page-level CSS, omit element_id. Appends to existing CSS by default; set replace=true to overwrite.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_custom_css' ),
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
							'description' => __( 'Optional element ID to apply CSS to. If omitted, CSS is applied at the page level.', 'emcp-tools' ),
						),
						'css'        => array(
							'type'        => 'string',
							'description' => __( 'CSS rules to add. Use "selector" as the element wrapper placeholder for element-level CSS.', 'emcp-tools' ),
						),
						'replace'    => array(
							'type'        => 'boolean',
							'description' => __( 'If true, replaces existing custom CSS instead of appending. Default: false.', 'emcp-tools' ),
						),
						'clear_cache' => self::cache_flag(),
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
			return new \WP_Error( 'missing_params', __( 'post_id and css are required.', 'emcp-tools' ) );
		}

		// Basic sanitization: strip PHP tags and script tags.
		$css = preg_replace( '/<\?(=|php)(.+?)\?>/is', '', $css );
		$css = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $css );

		// Neutralize </style>. This CSS is emitted inside a <style> block, which
		// the HTML parser treats as raw text — the ONLY way to break out into
		// live HTML (an XSS vector, e.g. </style><img onerror=...>) is the literal
		// </style> end tag; a bare "<", ">" or even "<img>" is inert without it.
		// We loop so that removing one match can't reconstruct another (e.g.
		// "</sty</stylele>" -> "</style>"). This is bypass-proof and, crucially,
		// preserves ALL valid CSS — child/sibling combinators (> ~ +), media
		// range queries (< >), and content strings. (F-004)
		$previous = null;
		while ( $previous !== $css ) {
			$previous = $css;
			$css      = preg_replace( '#</\s*style#i', '', $css );
		}

		if ( ! empty( $element_id ) ) {
			// Element-level custom CSS.
			$page_data = $this->data->get_page_data( $post_id );

			if ( is_wp_error( $page_data ) ) {
				return $page_data;
			}

			$element = $this->data->find_element_by_id( $page_data, $element_id );

			if ( null === $element ) {
				return new \WP_Error( 'element_not_found', __( 'Element not found.', 'emcp-tools' ) );
			}

			$existing_css = $element['settings']['custom_css'] ?? '';
			$new_css      = $replace ? $css : trim( $existing_css . "\n" . $css );

			$updated = $this->data->update_element_settings(
				$page_data,
				$element_id,
				array( 'custom_css' => $new_css )
			);

			if ( ! $updated ) {
				return new \WP_Error( 'update_failed', __( 'Failed to update element settings.', 'emcp-tools' ) );
			}

			$result = $this->data->save_page_data( $post_id, $page_data );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return self::with_cache( $input, array(
				'success' => true,
				'target'  => 'element:' . $element_id,
				'css'     => $new_css,
			) );
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

		return self::with_cache( $input, array(
			'success' => true,
			'target'  => 'page:' . $post_id,
			'css'     => $new_css,
		) );
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
		$this->ability_names[] = 'emcp-tools/add-custom-js';

		emcp_tools_register_ability(
			'emcp-tools/add-custom-js',
			array(
				'label'               => __( 'Add Custom JavaScript', 'emcp-tools' ),
				'description'         => __( 'Adds a custom JavaScript snippet to a page by inserting an HTML widget containing a <script> tag. Works with free Elementor (no Pro required). The JS code is automatically wrapped in <script> tags, do NOT include them yourself. Use wrap_dom_ready=true to wrap in a DOMContentLoaded listener. For site-wide JS, use add-code-snippet instead (requires Pro).', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_custom_js' ),
				'permission_callback' => array( $this, 'check_js_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'post_id'        => array(
							'type'        => 'integer',
							'description' => __( 'The post/page ID.', 'emcp-tools' ),
						),
						'parent_id'      => array(
							'type'        => 'string',
							'description' => __( 'Parent container element ID.', 'emcp-tools' ),
						),
						'js'             => array(
							'type'        => 'string',
							'description' => __( 'JavaScript code to inject. Do NOT include <script> tags, they are added automatically.', 'emcp-tools' ),
						),
						'position'       => array(
							'type'        => 'integer',
							'description' => __( 'Insert position within parent. -1 = append (default).', 'emcp-tools' ),
						),
						'wrap_dom_ready' => array(
							'type'        => 'boolean',
							'description' => __( 'Wrap the code in a DOMContentLoaded listener. Default: false.', 'emcp-tools' ),
						),
						'clear_cache' => self::cache_flag(),
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
			return new \WP_Error( 'missing_params', __( 'post_id, parent_id, and js are required.', 'emcp-tools' ) );
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
					__( 'Parent element "%s" not found.', 'emcp-tools' ),
					$parent_id
				)
			);
		}

		$result = $this->data->save_page_data( $post_id, $page_data );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return self::with_cache( $input, array(
			'element_id' => $widget['id'],
			'post_id'    => $post_id,
		) );
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
		$this->ability_names[] = 'emcp-tools/add-code-snippet';

		emcp_tools_register_ability(
			'emcp-tools/add-code-snippet',
			array(
				'label'               => __( 'Add Code Snippet', 'emcp-tools' ),
				'description'         => __( 'Creates a site-wide Custom Code snippet using Elementor Pro. Injects CSS or JavaScript into the <head>, after <body> open, or before </body> close on ALL pages. Use this for analytics scripts, site-wide CSS overrides, meta tags, or tracking pixels. Requires Elementor Pro and manage_options capability.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_add_code_snippet' ),
				'permission_callback' => array( $this, 'check_snippet_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'title'         => array(
							'type'        => 'string',
							'description' => __( 'Descriptive title for the snippet (e.g. "Google Analytics", "Global CSS overrides").', 'emcp-tools' ),
						),
						'code'          => array(
							'type'        => 'string',
							'description' => __( 'The full code to inject. Include <script>, <style>, or <meta> tags as needed.', 'emcp-tools' ),
						),
						'location'      => array(
							'type'        => 'string',
							'enum'        => array( 'head', 'body_start', 'body_end' ),
							'description' => __( 'Where to inject: "head" = <head> tag, "body_start" = after <body>, "body_end" = before </body>. Default: head.', 'emcp-tools' ),
						),
						'priority'      => array(
							'type'        => 'integer',
							'description' => __( 'Load order priority (1-10, lower = earlier). Default: 1.', 'emcp-tools' ),
						),
						'status'        => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft' ),
							'description' => __( 'Post status. "publish" = active immediately. "draft" = saved but not active. Default: publish.', 'emcp-tools' ),
						),
						'ensure_jquery' => array(
							'type'        => 'boolean',
							'description' => __( 'If true, ensures jQuery is loaded before this snippet runs. Default: false.', 'emcp-tools' ),
						),
						'clear_cache' => self::cache_flag(),
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
			return new \WP_Error( 'missing_params', __( 'title and code are required.', 'emcp-tools' ) );
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
		$stored = self::store_code( $post_id, $code );
		update_post_meta( $post_id, '_elementor_template_type', 'code_snippet' );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

		// Set ensure_jquery extra option if requested.
		if ( $ensure_jquery ) {
			update_post_meta( $post_id, '_elementor_extra_options', array( 'ensure_jquery' ) );
		}

		if ( is_wp_error( $stored ) ) {
			// Never leave a site-wide snippet live with code other than what was sent.
			wp_update_post( array( 'ID' => $post_id, 'post_status' => 'draft' ) );
			$stored->add_data( array( 'snippet_id' => $post_id ) );
			return $stored;
		}

		$edit_url = admin_url( 'post.php?post=' . $post_id . '&action=edit' );

		return self::with_cache( $input, array(
			'snippet_id' => $post_id,
			'title'      => $title,
			'location'   => $location_key,
			'priority'   => $priority,
			'status'     => $status,
			'edit_url'   => $edit_url,
		) );
	}

	/**
	 * The clear_cache input property (#201).
	 *
	 * @return array
	 */
	private static function cache_flag(): array {
		return class_exists( 'EMCP_Tools_Cache_Abilities' ) ? EMCP_Tools_Cache_Abilities::flag_schema() : array( 'type' => 'boolean' );
	}

	/**
	 * Clear caches after a successful write when the input asked for it (#201).
	 *
	 * @param array $input  Tool input.
	 * @param array $result Tool result.
	 * @return array
	 */
	private static function with_cache( array $input, array $result ): array {
		return class_exists( 'EMCP_Tools_Cache_Abilities' ) ? EMCP_Tools_Cache_Abilities::after_write( $input, $result ) : $result;
	}

	/**
	 * Store a snippet's code exactly as sent and read it back (#198).
	 *
	 * update_post_meta() unslashes its value, so code is slashed first; without
	 * that, regex escapes, "\n" in strings and escaped quotes were silently lost.
	 *
	 * @since 3.22.1
	 *
	 * @param int    $post_id Snippet post id.
	 * @param string $code    Code as sent.
	 * @return true|\WP_Error
	 */
	public static function store_code( int $post_id, string $code ) {
		update_post_meta( $post_id, '_elementor_code', wp_slash( $code ) );
		if ( function_exists( 'wp_cache_delete' ) ) {
			wp_cache_delete( $post_id, 'post_meta' );
		}
		$saved = get_post_meta( $post_id, '_elementor_code', true );
		if ( $saved !== $code ) {
			return new \WP_Error(
				'code_not_saved',
				__( 'The snippet code was not stored exactly as sent, so the snippet was saved as a draft. Nothing runs on the site until the code matches.', 'emcp-tools' )
			);
		}
		return true;
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
		$this->ability_names[] = 'emcp-tools/list-code-snippets';

		emcp_tools_register_ability(
			'emcp-tools/list-code-snippets',
			array(
				'label'               => __( 'List Code Snippets', 'emcp-tools' ),
				'description'         => __( 'Lists all existing Elementor Pro Custom Code snippets with their titles, locations, priorities, and statuses. Requires Elementor Pro.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_code_snippets' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'location' => array(
							'type'        => 'string',
							'enum'        => array( 'head', 'body_start', 'body_end' ),
							'description' => __( 'Optional filter by location.', 'emcp-tools' ),
						),
						'status'   => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'any' ),
							'description' => __( 'Filter by post status. Default: any.', 'emcp-tools' ),
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

	// -------------------------------------------------------------------------
	// update-code-snippet / delete-code-snippet (Pro only, #197)
	// -------------------------------------------------------------------------

	/** Tool locations to Elementor's stored values. */
	const SNIPPET_LOCATIONS = array(
		'head'       => 'elementor_head',
		'body_start' => 'elementor_body_start',
		'body_end'   => 'elementor_body_end',
	);

	/**
	 * A snippet post by id, or an error naming why it cannot be used.
	 *
	 * @param mixed $id Snippet id.
	 * @return \WP_Post|\WP_Error
	 */
	private static function snippet_post( $id ) {
		$post = get_post( absint( $id ) );
		if ( ! $post || 'elementor_snippet' !== $post->post_type ) {
			return new \WP_Error( 'not_found', __( 'Code snippet not found. Use list-code-snippets for ids.', 'emcp-tools' ) );
		}
		return $post;
	}

	/** Rebuild Elementor Pro's conditions cache, which decides where snippets print. */
	private static function refresh_snippet_conditions(): void {
		if ( ! class_exists( '\ElementorPro\Plugin' ) ) {
			return;
		}
		$theme_builder = \ElementorPro\Plugin::instance()->modules_manager->get_modules( 'theme-builder' );
		if ( $theme_builder && method_exists( $theme_builder, 'get_conditions_manager' ) ) {
			$theme_builder->get_conditions_manager()->get_cache()->regenerate();
		}
	}

	/**
	 * Registers the update-code-snippet ability.
	 *
	 * @since 3.22.1
	 */
	private function register_update_code_snippet(): void {
		$this->ability_names[] = 'emcp-tools/update-code-snippet';

		emcp_tools_register_ability(
			'emcp-tools/update-code-snippet',
			array(
				'label'               => __( 'Update Code Snippet', 'emcp-tools' ),
				'description'         => __( 'Changes an existing Elementor Pro Custom Code snippet by snippet_id: code, title, location (head, body_start, body_end), priority (1-10), status (publish = active, draft = inactive) and ensure_jquery. Only the fields you send change. Code is stored exactly as sent and read back. Undo from History. Requires Elementor Pro.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_update_code_snippet' ),
				'permission_callback' => array( $this, 'check_snippet_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'snippet_id'    => array( 'type' => 'integer', 'description' => __( 'Snippet id from list-code-snippets.', 'emcp-tools' ) ),
						'title'         => array( 'type' => 'string' ),
						'code'          => array( 'type' => 'string', 'description' => __( 'The full code. Include <script>, <style> or <meta> tags as needed.', 'emcp-tools' ) ),
						'location'      => array( 'type' => 'string', 'enum' => array( 'head', 'body_start', 'body_end' ) ),
						'priority'      => array( 'type' => 'integer', 'description' => __( '1-10, lower runs earlier.', 'emcp-tools' ) ),
						'status'        => array( 'type' => 'string', 'enum' => array( 'publish', 'draft' ), 'description' => __( 'publish = active on the site, draft = inactive.', 'emcp-tools' ) ),
						'ensure_jquery' => array( 'type' => 'boolean' ),
						'clear_cache' => self::cache_flag(),
					),
					'required'   => array( 'snippet_id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
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
	 * Executes the update-code-snippet ability.
	 *
	 * @since 3.22.1
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_update_code_snippet( $input ) {
		$post = self::snippet_post( $input['snippet_id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		if ( 'trash' === $post->post_status ) {
			return new \WP_Error( 'snippet_trashed', __( 'This snippet is in the trash. Undo the delete from History first.', 'emcp-tools' ) );
		}
		$id = (int) $post->ID;

		$fields = array();
		if ( array_key_exists( 'title', $input ) ) {
			$title = sanitize_text_field( (string) $input['title'] );
			if ( '' === $title ) {
				return new \WP_Error( 'invalid_title', __( 'title cannot be empty.', 'emcp-tools' ) );
			}
			$fields['post_title'] = $title;
		}
		if ( array_key_exists( 'status', $input ) ) {
			if ( ! in_array( $input['status'], array( 'publish', 'draft' ), true ) ) {
				return new \WP_Error( 'invalid_status', __( 'status must be publish or draft.', 'emcp-tools' ) );
			}
			$fields['post_status'] = $input['status'];
		}

		$meta = array();
		if ( array_key_exists( 'code', $input ) ) {
			if ( '' === (string) $input['code'] ) {
				return new \WP_Error( 'invalid_code', __( 'code cannot be empty. Use delete-code-snippet to remove a snippet.', 'emcp-tools' ) );
			}
			$meta['_elementor_code'] = (string) $input['code'];
		}
		if ( array_key_exists( 'location', $input ) ) {
			if ( ! isset( self::SNIPPET_LOCATIONS[ $input['location'] ] ) ) {
				return new \WP_Error( 'invalid_location', __( 'location must be head, body_start or body_end.', 'emcp-tools' ) );
			}
			$meta['_elementor_location'] = self::SNIPPET_LOCATIONS[ $input['location'] ];
		}
		if ( array_key_exists( 'priority', $input ) ) {
			$meta['_elementor_priority'] = max( 1, min( 10, absint( $input['priority'] ) ) );
		}
		if ( array_key_exists( 'ensure_jquery', $input ) ) {
			$meta['_elementor_extra_options'] = ! empty( $input['ensure_jquery'] ) ? array( 'ensure_jquery' ) : array();
		}
		if ( empty( $fields ) && empty( $meta ) ) {
			return new \WP_Error( 'nothing_to_update', __( 'Send at least one of title, code, location, priority, status or ensure_jquery.', 'emcp-tools' ) );
		}

		$before = array( 'fields' => array(), 'meta' => array() );
		foreach ( $fields as $key => $unused ) {
			$before['fields'][ $key ] = (string) $post->$key;
		}
		foreach ( $meta as $key => $unused ) {
			$before['meta'][ $key ] = metadata_exists( 'post', $id, $key ) ? get_post_meta( $id, $key, true ) : '__DELETE__';
		}

		if ( ! empty( $fields ) ) {
			$res = wp_update_post( wp_slash( array( 'ID' => $id ) + $fields ), true );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
		}
		foreach ( $meta as $key => $value ) {
			if ( '_elementor_code' === $key ) {
				$stored = self::store_code( $id, $value );
				if ( is_wp_error( $stored ) ) {
					$this->put_back_snippet( $id, $before );
					return new \WP_Error( 'code_not_saved', __( 'The code was not stored exactly as sent, so the snippet was put back as it was.', 'emcp-tools' ) );
				}
				continue;
			}
			update_post_meta( $id, $key, wp_slash( $value ) );
		}
		self::refresh_snippet_conditions();

		$change_id = '';
		if ( class_exists( 'EMCP_Tools_Change_Recorder' ) ) {
			/* translators: %s: snippet title. */
			$summary   = sprintf( __( 'Updated code snippet "%s"', 'emcp-tools' ), get_the_title( $id ) );
			$change_id = EMCP_Tools_Change_Recorder::record_post_fields( $id, $before, $summary, get_the_title( $id ), 'code', 'update-code-snippet' );
		}

		$fresh     = get_post( $id );
		$locations = array_flip( self::SNIPPET_LOCATIONS );
		return self::with_cache( $input, array(
			'snippet_id'    => $id,
			'title'         => (string) $fresh->post_title,
			'status'        => (string) $fresh->post_status,
			'location'      => $locations[ (string) get_post_meta( $id, '_elementor_location', true ) ] ?? 'head',
			'priority'      => max( 1, absint( get_post_meta( $id, '_elementor_priority', true ) ) ),
			'ensure_jquery' => in_array( 'ensure_jquery', (array) get_post_meta( $id, '_elementor_extra_options', true ), true ),
			'updated'       => array_values( array_merge( array_keys( $fields ), array_keys( $meta ) ) ),
			'change_id'     => (string) $change_id,
		) );
	}

	/**
	 * Put a snippet back to its before-image after a failed update.
	 *
	 * @param int   $id     Snippet id.
	 * @param array $before Before-image.
	 */
	private function put_back_snippet( int $id, array $before ): void {
		if ( ! empty( $before['fields'] ) ) {
			wp_update_post( wp_slash( array( 'ID' => $id ) + $before['fields'] ) );
		}
		foreach ( $before['meta'] as $key => $value ) {
			if ( '__DELETE__' === $value ) {
				delete_post_meta( $id, $key );
			} else {
				update_post_meta( $id, $key, wp_slash( $value ) );
			}
		}
	}

	/**
	 * Registers the delete-code-snippet ability.
	 *
	 * @since 3.22.1
	 */
	private function register_delete_code_snippet(): void {
		$this->ability_names[] = 'emcp-tools/delete-code-snippet';

		emcp_tools_register_ability(
			'emcp-tools/delete-code-snippet',
			array(
				'label'               => __( 'Delete Code Snippet', 'emcp-tools' ),
				'description'         => __( 'Moves an Elementor Pro Custom Code snippet to the trash by snippet_id, so it stops running; undo from History brings it back with its status. Deletes it permanently only with permanent: true and confirm: true (also undoable from History). Disabled by default. Requires Elementor Pro.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_delete_code_snippet' ),
				'permission_callback' => array( $this, 'check_snippet_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'snippet_id' => array( 'type' => 'integer', 'description' => __( 'Snippet id from list-code-snippets.', 'emcp-tools' ) ),
						'permanent'  => array( 'type' => 'boolean', 'description' => __( 'Delete permanently instead of trashing. Needs confirm: true.', 'emcp-tools' ) ),
						'confirm'    => array( 'type' => 'boolean' ),
					),
					'required'   => array( 'snippet_id' ),
				),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes the delete-code-snippet ability.
	 *
	 * @since 3.22.1
	 *
	 * @param array $input The input parameters.
	 * @return array|\WP_Error
	 */
	public function execute_delete_code_snippet( $input ) {
		$post = self::snippet_post( $input['snippet_id'] ?? 0 );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$id        = (int) $post->ID;
		$permanent = ! empty( $input['permanent'] );
		$title     = (string) $post->post_title;
		$recorder  = class_exists( 'EMCP_Tools_Change_Recorder' );

		if ( $permanent ) {
			if ( true !== ( $input['confirm'] ?? null ) ) {
				return new \WP_Error( 'confirm_required', __( 'Permanent delete needs confirm: true.', 'emcp-tools' ) );
			}
			$snapshot = $recorder ? EMCP_Tools_Change_Recorder::snapshot_post( $id ) : array();
			if ( ! wp_delete_post( $id, true ) ) {
				return new \WP_Error( 'delete_failed', __( 'The snippet could not be deleted.', 'emcp-tools' ) );
			}
			self::refresh_snippet_conditions();
			/* translators: %s: snippet title. */
			$change_id = $recorder ? EMCP_Tools_Change_Recorder::record_post_delete( $id, $snapshot, true, sprintf( __( 'Deleted code snippet "%s"', 'emcp-tools' ), $title ), $title ) : '';
			return array( 'snippet_id' => $id, 'deleted' => true, 'trashed' => false, 'change_id' => (string) $change_id );
		}

		if ( 'trash' === $post->post_status ) {
			return new \WP_Error( 'already_trashed', __( 'This snippet is already in the trash.', 'emcp-tools' ) );
		}
		if ( ! wp_trash_post( $id ) ) {
			return new \WP_Error( 'delete_failed', __( 'The snippet could not be moved to the trash.', 'emcp-tools' ) );
		}
		self::refresh_snippet_conditions();
		/* translators: %s: snippet title. */
		$change_id = $recorder ? EMCP_Tools_Change_Recorder::record_post_delete( $id, array(), false, sprintf( __( 'Trashed code snippet "%s"', 'emcp-tools' ), $title ), $title ) : '';
		return array( 'snippet_id' => $id, 'deleted' => false, 'trashed' => true, 'change_id' => (string) $change_id );
	}
}

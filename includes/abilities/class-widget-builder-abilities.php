<?php
/**
 * Widget Builder MCP abilities — let AI agents generate custom Elementor widgets.
 *
 * Eight Pro-gated tools. The agent submits a structured spec; the plugin-owned
 * generator (never the agent) compiles it into a Widget_Base subclass stored in
 * an isolated uploads sandbox and loaded into Elementor. See:
 *   - Elementor_MCP_Widget_Generator (spec → PHP)
 *   - Elementor_MCP_Widget_Store     (CPT + sandbox + manifest)
 *   - Elementor_MCP_Widget_Loader    (manifest-driven safe load)
 *
 * All tools self-guard on Pro access (get_ability_names()/register() are no-ops
 * without a license) so they never enter the MCP surface on free sites.
 *
 * @package Elementor_MCP
 * @since   1.9.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and implements the widget-builder abilities.
 *
 * @since 1.9.0
 */
class Elementor_MCP_Widget_Builder_Abilities {

	/**
	 * Registered ability names.
	 *
	 * @var string[]
	 */
	private $ability_names = array();

	/**
	 * Whether these tools should register/run (Pro gate).
	 *
	 * @since 1.9.0
	 *
	 * @return bool
	 */
	private function has_access(): bool {
		return function_exists( 'emcp_pro_fs' ) && emcp_pro_fs()->can_use_premium_code();
	}

	/**
	 * Ability names (empty on free sites).
	 *
	 * @since 1.9.0
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		if ( ! $this->has_access() ) {
			return array();
		}
		return array(
			'elementor-mcp/list-control-types',
			'elementor-mcp/validate-widget-spec',
			'elementor-mcp/create-custom-widget',
			'elementor-mcp/update-custom-widget',
			'elementor-mcp/get-custom-widget',
			'elementor-mcp/list-custom-widgets',
			'elementor-mcp/set-widget-status',
			'elementor-mcp/delete-custom-widget',
		);
	}

	/**
	 * Registers all abilities (Pro only).
	 *
	 * @since 1.9.0
	 */
	public function register(): void {
		if ( ! $this->has_access() ) {
			return;
		}
		$this->register_list_control_types();
		$this->register_validate_widget_spec();
		$this->register_create_custom_widget();
		$this->register_update_custom_widget();
		$this->register_get_custom_widget();
		$this->register_list_custom_widgets();
		$this->register_set_widget_status();
		$this->register_delete_custom_widget();
	}

	// -------------------------------------------------------------------------
	// Permissions
	// -------------------------------------------------------------------------

	/**
	 * Read permission (discovery/validation).
	 *
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return $this->has_access() && current_user_can( 'edit_posts' );
	}

	/**
	 * Manage permission (list/get).
	 *
	 * @return bool
	 */
	public function check_manage_permission(): bool {
		return $this->has_access() && current_user_can( 'manage_options' );
	}

	/**
	 * Write permission (create/update/activate). Mirrors the code-snippet bar:
	 * manage_options + unfiltered_html.
	 *
	 * @return bool
	 */
	public function check_write_permission(): bool {
		return $this->has_access() && current_user_can( 'manage_options' ) && current_user_can( 'unfiltered_html' );
	}

	// -------------------------------------------------------------------------
	// Shared schema fragments
	// -------------------------------------------------------------------------

	/**
	 * JSON-schema fragment describing a widget spec.
	 *
	 * @return array
	 */
	private function spec_schema(): array {
		return array(
			'type'        => 'object',
			'description' => __( 'Widget definition. Call list-control-types first for the supported control types and template syntax.', 'elementor-mcp' ),
			'properties'  => array(
				'meta'          => array(
					'type'       => 'object',
					'properties' => array(
						'title'    => array(
							'type'        => 'string',
							'description' => __( 'Widget display title (required).', 'elementor-mcp' ),
						),
						'name'     => array(
							'type'        => 'string',
							'description' => __( 'Optional machine-name hint (a unique name is assigned automatically).', 'elementor-mcp' ),
						),
						'icon'     => array(
							'type'        => 'string',
							'description' => __( 'Elementor icon class, e.g. eicon-price-table.', 'elementor-mcp' ),
						),
						'keywords' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Search keywords for the widget panel.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'title' ),
				),
				'sections'      => array(
					'type'        => 'array',
					'description' => __( 'Control sections, grouped into Content/Style/Advanced tabs.', 'elementor-mcp' ),
					'items'       => array(
						'type'       => 'object',
						'properties' => array(
							'id'       => array( 'type' => 'string' ),
							'label'    => array( 'type' => 'string' ),
							'tab'      => array(
								'type'        => 'string',
								'enum'        => array( 'content', 'style', 'advanced' ),
								'description' => __( 'Which panel tab the section appears under.', 'elementor-mcp' ),
							),
							'controls' => array(
								'type'        => 'array',
								'description' => __( 'Controls in this section. Each: { name, type, label, default?, options?, fields? }. See list-control-types.', 'elementor-mcp' ),
								'items'       => array( 'type' => 'object' ),
							),
						),
						'required'   => array( 'id', 'controls' ),
					),
				),
				'html_template' => array(
					'type'        => 'string',
					'description' => __( 'HTML using {{control_name}} placeholders, {{#if name}}…{{/if}}, and {{#each repeater}}…{{/each}}. Values are escaped automatically by control type.', 'elementor-mcp' ),
				),
				'styles'        => array(
					'type'        => 'string',
					'description' => __( 'Optional base CSS for the widget, served as its own style.css and enqueued only when the widget is on the page. Scope rules with your own class names (e.g. .my-card). Prefer control "selectors" for buyer-editable styles.', 'elementor-mcp' ),
				),
				'scripts'       => array(
					'type'        => 'string',
					'description' => __( 'Optional front-end JavaScript, served as its own script.js (jQuery available) and enqueued only when the widget is on the page. Runs once per page load — scope DOM queries to your class names and guard against multiple instances.', 'elementor-mcp' ),
				),
			),
			'required'    => array( 'meta', 'sections', 'html_template' ),
		);
	}

	/**
	 * Output-schema fragment for a widget summary.
	 *
	 * @return array
	 */
	private function summary_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'widget_id'   => array( 'type' => 'integer' ),
				'title'       => array( 'type' => 'string' ),
				'widget_name' => array( 'type' => 'string' ),
				'class_name'  => array( 'type' => 'string' ),
				'status'      => array( 'type' => 'string' ),
				'last_error'  => array( 'type' => 'string' ),
				'updated'     => array( 'type' => 'string' ),
			),
		);
	}

	// -------------------------------------------------------------------------
	// list-control-types
	// -------------------------------------------------------------------------

	/**
	 * Registers list-control-types.
	 *
	 * @since 1.9.0
	 */
	private function register_list_control_types(): void {
		$this->ability_names[] = 'elementor-mcp/list-control-types';
		elementor_mcp_register_ability(
			'elementor-mcp/list-control-types',
			array(
				'label'               => __( 'List Widget Control Types', 'elementor-mcp' ),
				'description'         => __( 'Returns the control types and template syntax supported by the widget builder, so you can construct a valid widget spec.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_list_control_types' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'control_types'   => array( 'type' => 'array' ),
						'template_syntax' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes list-control-types.
	 *
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_list_control_types( $input ) {
		if ( ! $this->has_access() ) {
			return $this->no_license();
		}
		$out = array();
		foreach ( Elementor_MCP_Widget_Generator::control_types() as $type => $meta ) {
			$out[] = array(
				'type'        => $type,
				'has_value'   => (bool) $meta['value'],
				'group'       => $meta['group'],
				'description' => $meta['desc'],
			);
		}
		return array(
			'control_types'   => $out,
			'template_syntax' => __( 'Use {{control_name}} to output a control value (auto-escaped by type), {{#if name}}…{{/if}} for conditionals (switcher value is "yes"), and {{#each repeater_name}}…{{/each}} to loop a repeater (reference its fields by name inside). Style controls with "selectors": { "{{WRAPPER}} .my-el": "color: {{VALUE}};" }.', 'elementor-mcp' ),
		);
	}

	// -------------------------------------------------------------------------
	// validate-widget-spec
	// -------------------------------------------------------------------------

	/**
	 * Registers validate-widget-spec.
	 *
	 * @since 1.9.0
	 */
	private function register_validate_widget_spec(): void {
		$this->ability_names[] = 'elementor-mcp/validate-widget-spec';
		elementor_mcp_register_ability(
			'elementor-mcp/validate-widget-spec',
			array(
				'label'               => __( 'Validate Widget Spec', 'elementor-mcp' ),
				'description'         => __( 'Validates a widget spec and dry-runs the code generator without saving anything. Returns whether it is valid and any error.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_validate_widget_spec' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array( 'spec' => $this->spec_schema() ),
					'required'   => array( 'spec' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'valid' => array( 'type' => 'boolean' ),
						'error' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes validate-widget-spec.
	 *
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_validate_widget_spec( $input ) {
		if ( ! $this->has_access() ) {
			return $this->no_license();
		}
		$spec = isset( $input['spec'] ) && is_array( $input['spec'] ) ? $input['spec'] : array();

		$valid = Elementor_MCP_Widget_Generator::validate_spec( $spec );
		if ( is_wp_error( $valid ) ) {
			return array( 'valid' => false, 'error' => $valid->get_error_message() );
		}
		// Dry-run the generator (token lint) without persisting.
		$php = Elementor_MCP_Widget_Generator::generate( $spec, 'EMCP_Widget_Preview', 'emcp_custom_preview' );
		if ( is_wp_error( $php ) ) {
			return array( 'valid' => false, 'error' => $php->get_error_message() );
		}
		return array( 'valid' => true, 'error' => '' );
	}

	// -------------------------------------------------------------------------
	// create-custom-widget
	// -------------------------------------------------------------------------

	/**
	 * Registers create-custom-widget.
	 *
	 * @since 1.9.0
	 */
	private function register_create_custom_widget(): void {
		$this->ability_names[] = 'elementor-mcp/create-custom-widget';
		elementor_mcp_register_ability(
			'elementor-mcp/create-custom-widget',
			array(
				'label'               => __( 'Create Custom Widget', 'elementor-mcp' ),
				'description'         => __( 'Generates a custom Elementor widget from a spec, stores it in an isolated sandbox, and activates it so it appears in the Elementor panel under "Custom (EMCP)". Use the returned widget_name with add-widget to place it on a page.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_create_custom_widget' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'spec'     => $this->spec_schema(),
						'activate' => array(
							'type'        => 'boolean',
							'description' => __( 'Activate immediately (default true). If false, the widget is created inactive.', 'elementor-mcp' ),
						),
					),
					'required'   => array( 'spec' ),
				),
				'output_schema'       => $this->summary_schema(),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes create-custom-widget.
	 *
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_create_custom_widget( $input ) {
		if ( ! $this->has_access() ) {
			return $this->no_license();
		}
		$spec = isset( $input['spec'] ) && is_array( $input['spec'] ) ? $input['spec'] : array();
		$activate = ! isset( $input['activate'] ) || ! empty( $input['activate'] );
		return Elementor_MCP_Widget_Store::create( $spec, $activate );
	}

	// -------------------------------------------------------------------------
	// update-custom-widget
	// -------------------------------------------------------------------------

	/**
	 * Registers update-custom-widget.
	 *
	 * @since 1.9.0
	 */
	private function register_update_custom_widget(): void {
		$this->ability_names[] = 'elementor-mcp/update-custom-widget';
		elementor_mcp_register_ability(
			'elementor-mcp/update-custom-widget',
			array(
				'label'               => __( 'Update Custom Widget', 'elementor-mcp' ),
				'description'         => __( 'Replaces a custom widget\'s spec and regenerates its code in place.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_update_custom_widget' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'widget_id' => array(
							'type'        => 'integer',
							'description' => __( 'The widget post ID returned by create/list.', 'elementor-mcp' ),
						),
						'spec'      => $this->spec_schema(),
					),
					'required'   => array( 'widget_id', 'spec' ),
				),
				'output_schema'       => $this->summary_schema(),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes update-custom-widget.
	 *
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_update_custom_widget( $input ) {
		if ( ! $this->has_access() ) {
			return $this->no_license();
		}
		$widget_id = absint( $input['widget_id'] ?? 0 );
		$spec      = isset( $input['spec'] ) && is_array( $input['spec'] ) ? $input['spec'] : array();
		if ( ! $widget_id ) {
			return new WP_Error( 'missing_params', __( 'widget_id is required.', 'elementor-mcp' ) );
		}
		return Elementor_MCP_Widget_Store::update( $widget_id, $spec );
	}

	// -------------------------------------------------------------------------
	// get-custom-widget
	// -------------------------------------------------------------------------

	/**
	 * Registers get-custom-widget.
	 *
	 * @since 1.9.0
	 */
	private function register_get_custom_widget(): void {
		$this->ability_names[] = 'elementor-mcp/get-custom-widget';
		elementor_mcp_register_ability(
			'elementor-mcp/get-custom-widget',
			array(
				'label'               => __( 'Get Custom Widget', 'elementor-mcp' ),
				'description'         => __( 'Returns a custom widget\'s spec, generated PHP, status, and any last error.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_get_custom_widget' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'widget_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'widget_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'widget'        => $this->summary_schema(),
						'spec'          => array( 'type' => 'object' ),
						'generated_php' => array( 'type' => 'string' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes get-custom-widget.
	 *
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_get_custom_widget( $input ) {
		if ( ! $this->has_access() ) {
			return $this->no_license();
		}
		$widget_id = absint( $input['widget_id'] ?? 0 );
		$summary   = Elementor_MCP_Widget_Store::summary( $widget_id );
		if ( is_wp_error( $summary ) ) {
			return $summary;
		}
		return array(
			'widget'        => $summary,
			'spec'          => Elementor_MCP_Widget_Store::get_spec( $widget_id ) ?? array(),
			'generated_php' => Elementor_MCP_Widget_Store::get_php( $widget_id ),
		);
	}

	// -------------------------------------------------------------------------
	// list-custom-widgets
	// -------------------------------------------------------------------------

	/**
	 * Registers list-custom-widgets.
	 *
	 * @since 1.9.0
	 */
	private function register_list_custom_widgets(): void {
		$this->ability_names[] = 'elementor-mcp/list-custom-widgets';
		elementor_mcp_register_ability(
			'elementor-mcp/list-custom-widgets',
			array(
				'label'               => __( 'List Custom Widgets', 'elementor-mcp' ),
				'description'         => __( 'Lists all custom widgets generated by the widget builder, with their status.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_list_custom_widgets' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'active', 'draft', 'any' ),
							'description' => __( 'Filter by status. Default: any.', 'elementor-mcp' ),
						),
					),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'widgets' => array( 'type' => 'array', 'items' => $this->summary_schema() ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes list-custom-widgets.
	 *
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_list_custom_widgets( $input ) {
		if ( ! $this->has_access() ) {
			return $this->no_license();
		}
		$status = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : 'any';
		if ( ! in_array( $status, array( 'active', 'draft', 'any' ), true ) ) {
			$status = 'any';
		}
		return array( 'widgets' => Elementor_MCP_Widget_Store::list_widgets( $status ) );
	}

	// -------------------------------------------------------------------------
	// set-widget-status
	// -------------------------------------------------------------------------

	/**
	 * Registers set-widget-status.
	 *
	 * @since 1.9.0
	 */
	private function register_set_widget_status(): void {
		$this->ability_names[] = 'elementor-mcp/set-widget-status';
		elementor_mcp_register_ability(
			'elementor-mcp/set-widget-status',
			array(
				'label'               => __( 'Set Widget Status', 'elementor-mcp' ),
				'description'         => __( 'Activates (loads into Elementor) or deactivates a custom widget.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_set_widget_status' ),
				'permission_callback' => array( $this, 'check_write_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'widget_id' => array( 'type' => 'integer' ),
						'status'    => array(
							'type' => 'string',
							'enum' => array( 'active', 'draft' ),
						),
					),
					'required'   => array( 'widget_id', 'status' ),
				),
				'output_schema'       => $this->summary_schema(),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes set-widget-status.
	 *
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_set_widget_status( $input ) {
		if ( ! $this->has_access() ) {
			return $this->no_license();
		}
		$widget_id = absint( $input['widget_id'] ?? 0 );
		$status    = isset( $input['status'] ) ? sanitize_key( $input['status'] ) : '';
		if ( ! $widget_id || ! in_array( $status, array( 'active', 'draft' ), true ) ) {
			return new WP_Error( 'missing_params', __( 'widget_id and a valid status are required.', 'elementor-mcp' ) );
		}
		return Elementor_MCP_Widget_Store::set_status( $widget_id, $status );
	}

	// -------------------------------------------------------------------------
	// delete-custom-widget
	// -------------------------------------------------------------------------

	/**
	 * Registers delete-custom-widget.
	 *
	 * @since 1.9.0
	 */
	private function register_delete_custom_widget(): void {
		$this->ability_names[] = 'elementor-mcp/delete-custom-widget';
		elementor_mcp_register_ability(
			'elementor-mcp/delete-custom-widget',
			array(
				'label'               => __( 'Delete Custom Widget', 'elementor-mcp' ),
				'description'         => __( 'Permanently deletes a custom widget: its record and its sandbox file. Destructive.', 'elementor-mcp' ),
				'category'            => 'elementor-mcp',
				'execute_callback'    => array( $this, 'execute_delete_custom_widget' ),
				'permission_callback' => array( $this, 'check_manage_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'widget_id' => array( 'type' => 'integer' ),
					),
					'required'   => array( 'widget_id' ),
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'widget_id' => array( 'type' => 'integer' ),
					),
				),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => true, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Executes delete-custom-widget.
	 *
	 * @param array $input Tool input.
	 * @return array|WP_Error
	 */
	public function execute_delete_custom_widget( $input ) {
		if ( ! $this->has_access() ) {
			return $this->no_license();
		}
		$widget_id = absint( $input['widget_id'] ?? 0 );
		if ( ! $widget_id ) {
			return new WP_Error( 'missing_params', __( 'widget_id is required.', 'elementor-mcp' ) );
		}
		return Elementor_MCP_Widget_Store::delete( $widget_id );
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Standard no-license error.
	 *
	 * @return WP_Error
	 */
	private function no_license(): WP_Error {
		return new WP_Error( 'no_license', __( 'A valid EMCP Tools Pro license is required to use the widget builder.', 'elementor-mcp' ) );
	}
}

<?php
/**
 * Compact tool mode — the meta-tool dispatcher.
 *
 * When dispatcher mode is on (EMCP_Tools_Plugin::is_dispatcher_mode()), the MCP
 * server surfaces only these three tools instead of every individual ability:
 *   - list-tools       : compact catalog of the enabled tools
 *   - get-tool-schema  : full input schema for named tools
 *   - call-tool        : run a tool by name, enforcing the TARGET's own gate
 * They route to the existing abilities via wp_get_ability(); call-tool never
 * bypasses a target's permission_callback.
 *
 * @package EMCP_Tools
 * @since   3.2.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_Dispatcher_Abilities {

	const NAMES = array(
		'emcp-tools/list-tools',
		'emcp-tools/get-tool-schema',
		'emcp-tools/call-tool',
	);

	/**
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return self::NAMES;
	}

	/**
	 * The enabled ability-name set (after the emcp_tools_ability_names filter).
	 * Seam for testing.
	 *
	 * @return string[]
	 */
	protected function active_names(): array {
		$names = array();
		if ( class_exists( 'EMCP_Tools_Plugin' ) && method_exists( 'EMCP_Tools_Plugin', 'instance' ) ) {
			$names = EMCP_Tools_Plugin::instance()->get_active_ability_names();
		}
		// Fold in the same WordPress core context abilities the server surfaces
		// directly in full mode, so they stay listable + callable via call-tool
		// in compact mode (where the top-level surface is just the 3 meta-tools).
		if ( function_exists( 'wp_get_ability' ) ) {
			foreach ( array( 'core/get-site-info', 'core/get-user-info', 'core/get-environment-info' ) as $core ) {
				if ( wp_get_ability( $core ) && ! in_array( $core, $names, true ) ) {
					$names[] = $core;
				}
			}
		}
		return $names;
	}

	/**
	 * Resolve a tool name to a WP_Ability (or null). Seam for testing.
	 *
	 * @param string $name
	 * @return object|null
	 */
	protected function resolve_ability( string $name ) {
		return function_exists( 'wp_get_ability' ) ? wp_get_ability( $name ) : null;
	}

	/**
	 * @param object $ability
	 * @return bool
	 */
	private function is_destructive( $ability ): bool {
		$meta = method_exists( $ability, 'get_meta' ) ? (array) $ability->get_meta() : array();
		return ! empty( $meta['annotations']['destructive'] );
	}

	/**
	 * True only when the ability declares readonly => true. The hosted Gateway
	 * reads this to let read-only team members use compact sites, so anything
	 * short of a strict true counts as a write.
	 *
	 * @param object $ability
	 * @return bool
	 */
	private function is_readonly( $ability ): bool {
		$meta = method_exists( $ability, 'get_meta' ) ? (array) $ability->get_meta() : array();
		return true === ( $meta['annotations']['readonly'] ?? null );
	}

	/**
	 * list-tools: compact catalog of enabled tools (+ discovery context).
	 *
	 * @param array $input { search?: string, category?: string }
	 * @return array
	 */
	public function execute_list_tools( $input ) {
		$search   = isset( $input['search'] ) ? (string) $input['search'] : '';
		$category = isset( $input['category'] ) ? (string) $input['category'] : '';
		$tools    = array();

		foreach ( $this->active_names() as $name ) {
			$ability = $this->resolve_ability( $name );
			if ( ! $ability ) {
				continue;
			}
			$desc = (string) $ability->get_description();
			$cat  = (string) $ability->get_category();
			if ( '' !== $category && $cat !== $category ) {
				continue;
			}
			if ( '' !== $search && false === stripos( $name . ' ' . $desc, $search ) ) {
				continue;
			}
			$tools[] = array(
				'name'        => $name,
				'description' => $desc,
				'category'    => $cat,
				'destructive' => $this->is_destructive( $ability ),
				'readonly'    => $this->is_readonly( $ability ),
			);
		}

		return array(
			'tools'   => $tools,
			'context' => class_exists( 'EMCP_Tools_Site_Context' ) ? EMCP_Tools_Site_Context::environment_summary() : '',
		);
	}

	/**
	 * get-tool-schema: input schema for named tools (batch).
	 *
	 * @param array $input { names: string[] }
	 * @return array
	 */
	public function execute_get_tool_schema( $input ) {
		$names       = array_values( (array) ( $input['names'] ?? array() ) );
		$enabled     = array_flip( $this->active_names() );
		$schemas     = array();
		$unavailable = array();

		foreach ( $names as $name ) {
			$name    = (string) $name;
			$ability = isset( $enabled[ $name ] ) ? $this->resolve_ability( $name ) : null;
			if ( ! $ability ) {
				$unavailable[] = $name;
				continue;
			}
			$schemas[ $name ] = array(
				'description' => (string) $ability->get_description(),
				'inputSchema' => (array) $ability->get_input_schema(),
				'readonly'    => $this->is_readonly( $ability ),
			);
		}

		return array( 'schemas' => $schemas, 'unavailable' => $unavailable );
	}

	/**
	 * call-tool: run a tool by name. Enforces the TARGET ability's own
	 * check_permissions() — the real security gate.
	 *
	 * @param array $input { name: string, arguments?: object }
	 * @return mixed|\WP_Error
	 */
	public function execute_call_tool( $input ) {
		$name = (string) ( $input['name'] ?? '' );
		$args = (array) ( $input['arguments'] ?? array() );

		if ( '' === $name || ! in_array( $name, $this->active_names(), true ) ) {
			return new \WP_Error( 'tool_unavailable', __( 'Unknown or disabled tool.', 'emcp-tools' ) );
		}
		$ability = $this->resolve_ability( $name );
		if ( ! $ability ) {
			return new \WP_Error( 'tool_unavailable', __( 'Tool is not registered.', 'emcp-tools' ) );
		}

		$perm = $ability->check_permissions( $args );
		if ( is_wp_error( $perm ) || ! $perm ) {
			return new \WP_Error( 'forbidden', __( 'You are not allowed to run this tool.', 'emcp-tools' ) );
		}

		if ( method_exists( $ability, 'validate_input' ) ) {
			$valid = $ability->validate_input( $args );
			if ( is_wp_error( $valid ) ) {
				return $valid;
			}
		}

		return $ability->execute( $args );
	}

	/**
	 * Register the three dispatcher abilities. Always registered (so the registry
	 * resolves them); only surfaced on the server when dispatcher mode is on.
	 */
	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-tools',
			array(
				'label'               => __( 'List Tools', 'emcp-tools' ),
				'description'         => __( 'Compact catalog of the available EMCP tools (name, description, category). Filter with search or category. Step 1 of compact tool mode: list-tools -> get-tool-schema -> call-tool. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list_tools' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'search'   => array( 'type' => 'string', 'description' => __( 'Filter by substring of the tool name or description.', 'emcp-tools' ) ),
						'category' => array( 'type' => 'string', 'description' => __( 'Filter by tool category.', 'emcp-tools' ) ),
					),
				),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/get-tool-schema',
			array(
				'label'               => __( 'Get Tool Schema', 'emcp-tools' ),
				'description'         => __( 'Return the full input schema for one or more tools by name. Step 2 of compact tool mode. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get_tool_schema' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'names' ),
					'properties' => array(
						'names' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'description' => __( 'Tool names to fetch schemas for (e.g. ["emcp-tools/list-widgets"]).', 'emcp-tools' ),
						),
					),
				),
				'meta'                => array( 'annotations' => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ), 'show_in_rest' => true ),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/call-tool',
			array(
				'label'               => __( 'Call Tool', 'emcp-tools' ),
				'description'         => __( 'Run a tool by name with its arguments. Step 3 of compact tool mode. Enforces the target tool\'s own permissions; check the destructive flag from list-tools before calling.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_call_tool' ),
				'permission_callback' => array( $this, 'check_read_permission' ),
				'input_schema'        => array(
					'type'       => 'object',
					'required'   => array( 'name' ),
					'properties' => array(
						'name'      => array( 'type' => 'string', 'description' => __( 'The tool name to run (from list-tools).', 'emcp-tools' ) ),
						'arguments' => array( 'type' => 'object', 'description' => __( 'The arguments object for the tool (shape from get-tool-schema).', 'emcp-tools' ) ),
					),
				),
				'meta'                => array( 'annotations' => array( 'readonly' => false, 'destructive' => false ), 'show_in_rest' => true ),
			)
		);
	}

	/**
	 * Permission for the dispatcher tools themselves — metadata/routing only; the
	 * real gate is the target tool's own permission at call time.
	 *
	 * @return bool
	 */
	public function check_read_permission(): bool {
		return current_user_can( 'edit_posts' );
	}
}

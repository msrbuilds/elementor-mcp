<?php
/**
 * Abstract base for SEO plugin integrations.
 *
 * Each integration exposes two MCP tools — one Read, one Write — that dispatch
 * to internal operations resolved by name (the ACF/Forms two-dispatcher
 * pattern). Concrete adapters implement id()/label()/is_active()/operations()
 * plus their run callables; this base owns registration, dispatch, the discovery
 * catalog, per-operation permission, confirm-gating, and the plugin-inactive
 * guard.
 *
 * SEO integrations read and write the SEO metadata (title, description,
 * canonical, robots, social, focus keyword, and — where supported — redirects
 * and schema) that each SEO plugin stores. This is distinct from the Pro SEO &
 * Accessibility toolkit (audit-page-seo / generate-meta-tags), which analyses
 * and generates rather than reading/writing a plugin's stored data.
 *
 * @package EMCP_Tools
 * @since   3.5.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two-dispatcher base for an SEO plugin integration.
 *
 * @since 3.5.0
 */
abstract class EMCP_Tools_SEO_Integration {

	/**
	 * Short integration id, used to build the tool names (`<id>-read`/`-write`).
	 *
	 * @return string
	 */
	abstract public function id(): string;

	/**
	 * Human label for the integration.
	 *
	 * @return string
	 */
	abstract public function label(): string;

	/**
	 * Whether the underlying SEO plugin is active.
	 *
	 * @return bool
	 */
	abstract public function is_active(): bool;

	/**
	 * Operation map: name => {
	 *   mode:    'read'|'write',
	 *   run:     callable(array $args):mixed,
	 *   perm:    callable():bool,
	 *   desc:    string,
	 *   confirm: bool (optional; requires arguments.confirm===true)
	 * }.
	 *
	 * @return array<string,array>
	 */
	abstract protected function operations(): array;

	/**
	 * Whether this integration should register at all.
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return $this->is_active();
	}

	/**
	 * @return string The read tool ability name.
	 */
	final public function read_tool(): string {
		return 'emcp-tools/' . $this->id() . '-read';
	}

	/**
	 * @return string The write tool ability name.
	 */
	final public function write_tool(): string {
		return 'emcp-tools/' . $this->id() . '-write';
	}

	/**
	 * @return string[] Both dispatcher tool names.
	 */
	final public function get_ability_names(): array {
		return array( $this->read_tool(), $this->write_tool() );
	}

	/**
	 * Register the read + write dispatcher tools.
	 */
	public function register(): void {
		emcp_tools_register_ability(
			$this->read_tool(),
			array(
				'label'               => $this->label() . ' Read',
				'description'         => $this->read_description(),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'run_read' ),
				'permission_callback' => array( $this, 'can_read' ),
				'input_schema'        => $this->dispatch_schema(),
				'meta'                => array(
					'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
					'show_in_rest' => true,
				),
			)
		);
		emcp_tools_register_ability(
			$this->write_tool(),
			array(
				'label'               => $this->label() . ' Write',
				'description'         => $this->write_description(),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'run_write' ),
				'permission_callback' => array( $this, 'can_write' ),
				'input_schema'        => $this->dispatch_schema(),
				'meta'                => array(
					'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * Read dispatcher callback.
	 *
	 * @param mixed $input Tool input.
	 * @return mixed
	 */
	public function run_read( $input ) {
		return $this->dispatch( 'read', $input );
	}

	/**
	 * Write dispatcher callback.
	 *
	 * @param mixed $input Tool input.
	 * @return mixed
	 */
	public function run_write( $input ) {
		return $this->dispatch( 'write', $input );
	}

	/**
	 * Coarse read gate; individual operations re-check their own capability.
	 *
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Coarse write gate; individual operations re-check their own capability.
	 *
	 * @return bool
	 */
	public function can_write(): bool {
		return current_user_can( 'edit_posts' );
	}

	/**
	 * Resolve + run an operation, or return the discovery catalog when no
	 * operation is given. Each op runs its own permission check; confirm-gated
	 * ops require arguments.confirm===true.
	 *
	 * @param string $mode  'read' or 'write'.
	 * @param mixed  $input Tool input ({ operation, arguments }).
	 * @return mixed
	 */
	private function dispatch( string $mode, $input ) {
		$input     = is_array( $input ) ? $input : array();
		$operation = isset( $input['operation'] ) ? str_replace( '_', '-', sanitize_key( (string) $input['operation'] ) ) : '';
		$ops       = $this->operations();

		if ( '' === $operation ) {
			return $this->catalog( $mode, $ops );
		}

		if ( ! $this->is_active() ) {
			return new WP_Error(
				'plugin_inactive',
				sprintf(
					/* translators: %s: plugin label */
					__( 'Install and activate %s to use this tool.', 'emcp-tools' ),
					$this->label()
				),
				array( 'status' => 409 )
			);
		}

		if ( ! isset( $ops[ $operation ] ) || $ops[ $operation ]['mode'] !== $mode ) {
			return new WP_Error(
				'unknown_operation',
				sprintf(
					/* translators: 1: mode (read/write), 2: operation name */
					__( 'Unknown %1$s operation: %2$s. Call the tool with no operation to list them.', 'emcp-tools' ),
					$mode,
					$operation
				),
				array( 'status' => 404 )
			);
		}

		$op   = $ops[ $operation ];
		$args = ( isset( $input['arguments'] ) && is_array( $input['arguments'] ) ) ? $input['arguments'] : array();

		if ( ! call_user_func( $op['perm'] ) ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have permission for this operation.', 'emcp-tools' ),
				array( 'status' => 403 )
			);
		}

		if ( ! empty( $op['confirm'] ) && ( ! isset( $args['confirm'] ) || true !== $args['confirm'] ) ) {
			return new WP_Error(
				'confirmation_required',
				__( 'This operation is irreversible. Pass confirm:true in arguments to proceed.', 'emcp-tools' ),
				array( 'status' => 400 )
			);
		}
		unset( $args['confirm'] );

		return call_user_func( $op['run'], $args );
	}

	/**
	 * The discovery catalog for a mode.
	 *
	 * @param string $mode Mode.
	 * @param array  $ops  Operation map.
	 * @return array{mode:string,operations:array<int,array{operation:string,description:string,confirm:bool}>}
	 */
	private function catalog( string $mode, array $ops ): array {
		$out = array();
		foreach ( $ops as $name => $op ) {
			if ( $op['mode'] === $mode ) {
				$out[] = array(
					'operation'   => $name,
					'description' => $op['desc'],
					'confirm'     => ! empty( $op['confirm'] ),
				);
			}
		}
		return array(
			'mode'       => $mode,
			'operations' => $out,
		);
	}

	/**
	 * The shared { operation, arguments } input schema.
	 *
	 * @return array
	 */
	private function dispatch_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'operation' => array(
					'type'        => 'string',
					'description' => __( 'Operation name. Omit to list the available operations.', 'emcp-tools' ),
				),
				'arguments' => array(
					'type'        => 'object',
					'description' => __( 'Arguments for the operation.', 'emcp-tools' ),
				),
			),
		);
	}

	/**
	 * @return string
	 */
	protected function read_description(): string {
		return $this->label() . ' — read operations (post/term SEO, settings). Call with no operation to list them.';
	}

	/**
	 * @return string
	 */
	protected function write_description(): string {
		return $this->label() . ' — write operations (post/term SEO, settings). Call with no operation to list them.';
	}
}

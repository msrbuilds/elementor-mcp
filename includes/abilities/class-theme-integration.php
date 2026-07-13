<?php
/**
 * Abstract base for Themes-tab integrations.
 *
 * Each integration exposes exactly two MCP tools — one Read, one Write — that
 * dispatch to internal operations resolved by name (the ACF two-dispatcher
 * pattern). Concrete integrations implement id()/label()/is_available()/
 * operations() plus their execute_* methods; this base owns registration,
 * dispatch, the discovery catalog, and per-operation permission delegation.
 *
 * @package EMCP_Tools
 * @since   3.4.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two-dispatcher base for a theme integration.
 */
abstract class EMCP_Tools_Theme_Integration {

	/**
	 * Short integration id, used to build the tool names (`<id>-read`/`-write`).
	 *
	 * @return string
	 */
	abstract public function id(): string;

	/**
	 * Human label for the integration (admin cards + tool labels).
	 *
	 * @return string
	 */
	abstract public function label(): string;

	/**
	 * Whether this integration should register (e.g. its framework is active).
	 *
	 * @return bool
	 */
	abstract public function is_available(): bool;

	/**
	 * The operation map: name => { mode: 'read'|'write', run: callable,
	 * perm: callable():bool, desc: string }.
	 *
	 * @return array<string,array{mode:string,run:callable,perm:callable,desc:string}>
	 */
	abstract protected function operations(): array;

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
	 * Tool-level gate (the MCP permission_callback). Individual operations
	 * re-check their own capability in dispatch(); this is the coarse gate so a
	 * disabled tool never dispatches.
	 *
	 * @return bool
	 */
	public function can_read(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * @return bool
	 */
	public function can_write(): bool {
		return current_user_can( 'edit_theme_options' );
	}

	/**
	 * Resolve + run an operation, or return the discovery catalog when no
	 * operation is given. Each op runs its own permission check (no escalation).
	 *
	 * @param string $mode  'read' or 'write'.
	 * @param mixed  $input Tool input ({ operation, arguments }).
	 * @return mixed
	 */
	private function dispatch( string $mode, $input ) {
		$input     = is_array( $input ) ? $input : array();
		$operation = isset( $input['operation'] ) ? (string) $input['operation'] : '';
		$ops       = $this->operations();

		if ( '' === $operation ) {
			return $this->catalog( $mode, $ops );
		}
		if ( ! isset( $ops[ $operation ] ) || $ops[ $operation ]['mode'] !== $mode ) {
			return new WP_Error(
				'unknown_operation',
				sprintf(
					/* translators: 1: mode (read/write), 2: operation name. */
					__( 'Unknown %1$s operation: %2$s', 'emcp-tools' ),
					$mode,
					$operation
				),
				array( 'status' => 404 )
			);
		}

		$op = $ops[ $operation ];
		if ( ! call_user_func( $op['perm'] ) ) {
			return new WP_Error(
				'forbidden',
				__( 'You do not have permission for this operation.', 'emcp-tools' ),
				array( 'status' => 403 )
			);
		}

		$args = ( isset( $input['arguments'] ) && is_array( $input['arguments'] ) ) ? $input['arguments'] : array();
		return call_user_func( $op['run'], $args );
	}

	/**
	 * The discovery catalog for a mode: op name + description.
	 *
	 * @param string $mode Mode.
	 * @param array  $ops  Operation map.
	 * @return array{mode:string,operations:array<int,array{operation:string,description:string}>}
	 */
	private function catalog( string $mode, array $ops ): array {
		$out = array();
		foreach ( $ops as $name => $op ) {
			if ( $op['mode'] === $mode ) {
				$out[] = array(
					'operation'   => $name,
					'description' => $op['desc'],
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
		return $this->label() . ' — read operations. Call with no operation to list them.';
	}

	/**
	 * @return string
	 */
	protected function write_description(): string {
		return $this->label() . ' — write operations. Call with no operation to list them.';
	}
}

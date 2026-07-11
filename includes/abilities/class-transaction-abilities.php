<?php
/**
 * AI-safe transaction MCP abilities: the change ledger + rollback.
 *
 * @package EMCP_Tools
 * @since   3.3.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers list-changes / get-change / rollback-change.
 *
 * @since 3.3.0
 */
class EMCP_Tools_Transaction_Abilities {

	/**
	 * Ability names.
	 *
	 * @return string[]
	 */
	public function get_ability_names(): array {
		return array(
			'emcp-tools/list-changes',
			'emcp-tools/get-change',
			'emcp-tools/rollback-change',
		);
	}

	/**
	 * Capability check (the ledger spans admin-grade fs/db targets).
	 *
	 * @return bool
	 */
	public function check_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Register the three abilities.
	 */
	public function register(): void {
		emcp_tools_register_ability(
			'emcp-tools/list-changes',
			array(
				'label'               => __( 'List Changes', 'emcp-tools' ),
				'description'         => __( 'Lists recent AI-made changes (Elementor edits, filesystem writes, database writes) recorded in the change ledger, newest first — each with a summary, whether it is reversible, and whether it has already been rolled back. Filter by domain (elementor/filesystem/database), rolled_back, or reversible. Use get-change for full detail and rollback-change to undo one. Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_list' ),
				'permission_callback' => array( $this, 'check_manage' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'domain'      => array( 'type' => 'string', 'enum' => array( 'elementor', 'filesystem', 'database' ), 'description' => __( 'Filter by domain.', 'emcp-tools' ) ),
						'rolled_back' => array( 'type' => 'boolean', 'description' => __( 'Only entries with this rolled-back state.', 'emcp-tools' ) ),
						'reversible'  => array( 'type' => 'boolean', 'description' => __( 'Only entries that are (or are not) reversible.', 'emcp-tools' ) ),
						'limit'       => array( 'type' => 'integer', 'description' => __( 'Max entries (default 50).', 'emcp-tools' ) ),
					),
				),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/get-change',
			array(
				'label'               => __( 'Get Change', 'emcp-tools' ),
				'description'         => __( 'Returns the full detail of one change-ledger entry by id, including its rollback reference (the before-image / backup pointer). Read-only.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_get' ),
				'permission_callback' => array( $this, 'check_manage' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'string', 'description' => __( 'Change id.', 'emcp-tools' ) ),
					),
					'required'   => array( 'id' ),
				),
			)
		);

		emcp_tools_register_ability(
			'emcp-tools/rollback-change',
			array(
				'label'               => __( 'Roll Back Change', 'emcp-tools' ),
				'description'         => __( 'Undoes one recorded change by id — restores a page\'s prior Elementor data, restores/removes a file from its backup, or inverses a database write from its before-image. Marks the entry rolled back (no double-rollback) and records a compensating entry. Only reverts changes EMCP itself recorded.', 'emcp-tools' ),
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, 'execute_rollback' ),
				'permission_callback' => array( $this, 'check_manage' ),
				'input_schema'        => array(
					'type'       => 'object',
					'properties' => array(
						'id' => array( 'type' => 'string', 'description' => __( 'Change id to roll back.', 'emcp-tools' ) ),
					),
					'required'   => array( 'id' ),
				),
			)
		);
	}

	/**
	 * list-changes.
	 *
	 * @param array $input Input.
	 * @return array
	 */
	public function execute_list( $input ) {
		$entries = array_reverse( EMCP_Tools_Change_Log::all() ); // newest first.
		$domain  = isset( $input['domain'] ) ? (string) $input['domain'] : '';
		$limit   = isset( $input['limit'] ) ? max( 1, (int) $input['limit'] ) : 50;

		$out = array();
		foreach ( $entries as $e ) {
			if ( '' !== $domain && ( $e['domain'] ?? '' ) !== $domain ) {
				continue;
			}
			$reversible = ! empty( $e['rollback'] ) && empty( $e['rolled_back'] );
			if ( isset( $input['rolled_back'] ) && (bool) $input['rolled_back'] !== ! empty( $e['rolled_back'] ) ) {
				continue;
			}
			if ( isset( $input['reversible'] ) && (bool) $input['reversible'] !== $reversible ) {
				continue;
			}
			$out[] = array(
				'id'          => $e['id'] ?? '',
				'ts'          => $e['ts'] ?? 0,
				'user_login'  => $e['user_login'] ?? '',
				'domain'      => $e['domain'] ?? '',
				'action'      => $e['action'] ?? '',
				'target'      => $e['target'] ?? '',
				'summary'     => $e['summary'] ?? '',
				'rolled_back' => ! empty( $e['rolled_back'] ),
				'reversible'  => $reversible,
				'rollback'    => self::light_rollback( $e['rollback'] ?? null ),
			);
			if ( count( $out ) >= $limit ) {
				break;
			}
		}

		return array(
			'changes' => $out,
			'total'   => count( EMCP_Tools_Change_Log::all() ),
		);
	}

	/**
	 * get-change.
	 *
	 * @param array $input Input.
	 * @return array|WP_Error
	 */
	public function execute_get( $input ) {
		$id    = isset( $input['id'] ) ? (string) $input['id'] : '';
		$entry = EMCP_Tools_Change_Log::get( $id );
		if ( null === $entry ) {
			return new WP_Error( 'not_found', __( 'Change not found.', 'emcp-tools' ) );
		}
		return $entry;
	}

	/**
	 * rollback-change.
	 *
	 * @param array $input Input.
	 * @return array|WP_Error
	 */
	public function execute_rollback( $input ) {
		$id = isset( $input['id'] ) ? (string) $input['id'] : '';
		return EMCP_Tools_Change_Log::rollback( $id );
	}

	/**
	 * Strip heavy payloads (before_rows/before) from a rollback ref for list output.
	 *
	 * @param array|null $rollback Rollback ref.
	 * @return array|null
	 */
	private static function light_rollback( $rollback ) {
		if ( ! is_array( $rollback ) ) {
			return null;
		}
		unset( $rollback['before_rows'], $rollback['before'], $rollback['inserted_key'] );
		return $rollback;
	}
}

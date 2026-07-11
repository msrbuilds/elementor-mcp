<?php
/**
 * Database MCP tools: list-tables/describe-table/query (read; enabled) +
 * insert-row/update-rows/delete-rows (structured, parameterized writes;
 * disabled-by-default). Reads via validated read-only SQL; writes via
 * $wpdb->insert/update/delete with forced WHERE, protected tables, before-image
 * snapshots, confirm-on-delete, and an audit log.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.0.0
 */
class EMCP_Tools_Database_Abilities {

	/** @var string[] */
	private $ability_names = array();

	public function get_ability_names(): array {
		return $this->ability_names;
	}

	public function register(): void {
		$this->register_list_tables();
		$this->register_describe_table();
		$this->register_query();
		$this->register_insert_row();
		$this->register_update_rows();
		$this->register_delete_rows();
	}

	/** All database tools require manage_options. */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	private function ability( string $name, string $label, string $description, string $exec, array $props, array $required, bool $readonly ): void {
		$this->ability_names[] = $name;
		emcp_tools_register_ability(
			$name,
			array(
				'label'               => $label,
				'description'         => $description,
				'category'            => 'emcp-tools',
				'execute_callback'    => array( $this, $exec ),
				'permission_callback' => array( $this, 'check_permission' ),
				'input_schema'        => array( 'type' => 'object', 'properties' => $props, 'required' => $required ),
				'output_schema'       => array( 'type' => 'object' ),
				'meta'                => array( 'annotations' => array( 'readonly' => $readonly, 'destructive' => ! $readonly ), 'show_in_rest' => true ),
			)
		);
	}

	// ---- reads ---------------------------------------------------------

	private function register_list_tables(): void {
		$this->ability( 'emcp-tools/list-tables', __( 'List Tables', 'emcp-tools' ), __( 'List database tables with estimated row counts and sizes.', 'emcp-tools' ), 'execute_list_tables', array(), array(), true );
	}
	public function execute_list_tables( $input ) {
		global $wpdb;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT table_name AS n, table_rows AS r, (data_length + index_length) AS sz FROM information_schema.TABLES WHERE table_schema = %s ORDER BY n ASC',
				DB_NAME
			),
			ARRAY_A
		);
		$out = array();
		foreach ( (array) $rows as $row ) {
			$out[] = array( 'table' => (string) $row['n'], 'rows' => (int) $row['r'], 'size_bytes' => (int) $row['sz'] );
		}
		return array( 'tables' => $out );
	}

	private function register_describe_table(): void {
		$this->ability( 'emcp-tools/describe-table', __( 'Describe Table', 'emcp-tools' ), __( 'Return the columns, types, and keys of a table.', 'emcp-tools' ), 'execute_describe_table', array( 'table' => array( 'type' => 'string' ) ), array( 'table' ), true );
	}
	public function execute_describe_table( $input ) {
		$table = EMCP_Tools_Database_Guard::valid_table( (string) ( $input['table'] ?? '' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		global $wpdb;
		$cols = $wpdb->get_results( 'DESCRIBE `' . str_replace( '`', '', $table ) . '`', ARRAY_A );
		return array( 'table' => $table, 'columns' => is_array( $cols ) ? $cols : array() );
	}

	private function register_query(): void {
		$this->ability( 'emcp-tools/query', __( 'Query (read-only)', 'emcp-tools' ), __( 'Run a read-only SQL query (SELECT/SHOW/DESCRIBE/EXPLAIN). Writes/DDL and file-access SQL are rejected. Results are capped.', 'emcp-tools' ), 'execute_query', array( 'sql' => array( 'type' => 'string' ), 'limit' => array( 'type' => 'integer' ) ), array( 'sql' ), true );
	}
	public function execute_query( $input ) {
		$sql = (string) ( $input['sql'] ?? '' );
		$ro  = EMCP_Tools_Database_Guard::is_read_only_sql( $sql );
		if ( is_wp_error( $ro ) ) {
			return $ro;
		}
		global $wpdb;
		$limit = isset( $input['limit'] ) ? (int) $input['limit'] : EMCP_Tools_Database_Guard::MAX_ROWS;
		$limit = min( EMCP_Tools_Database_Guard::MAX_ROWS, max( 1, $limit ) );
		$rows  = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB -- validated read-only; admin-authored.
		if ( null === $rows ) {
			return new \WP_Error( 'query_failed', $wpdb->last_error ? $wpdb->last_error : __( 'Query failed.', 'emcp-tools' ) );
		}
		$truncated = count( $rows ) > $limit;
		if ( $truncated ) {
			$rows = array_slice( $rows, 0, $limit );
		}
		return array( 'rows' => $rows, 'row_count' => count( $rows ), 'truncated' => $truncated );
	}

	// ---- writes (disabled by default) ---------------------------------

	private function register_insert_row(): void {
		$this->ability( 'emcp-tools/insert-row', __( 'Insert Row', 'emcp-tools' ), __( 'Insert a row into a table (parameterized). Refuses protected tables. Disabled by default.', 'emcp-tools' ), 'execute_insert_row', array( 'table' => array( 'type' => 'string' ), 'data' => array( 'type' => 'object' ) ), array( 'table', 'data' ), false );
	}
	public function execute_insert_row( $input ) {
		$data = (array) ( $input['data'] ?? array() );
		if ( empty( $data ) ) {
			return new \WP_Error( 'no_data', __( 'A non-empty data object is required.', 'emcp-tools' ) );
		}
		$table = EMCP_Tools_Database_Guard::valid_table( (string) ( $input['table'] ?? '' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		if ( EMCP_Tools_Database_Guard::is_protected( $table ) ) {
			return new \WP_Error( 'protected_table', __( 'Writes to this table are not allowed.', 'emcp-tools' ) );
		}
		global $wpdb;
		$ok = $wpdb->insert( $table, $data );
		if ( false === $ok ) {
			return new \WP_Error( 'insert_failed', $wpdb->last_error ? $wpdb->last_error : __( 'Insert failed.', 'emcp-tools' ) );
		}
		EMCP_Tools_Database_Guard::log( 'insert', $table, (int) $ok );
		if ( class_exists( 'EMCP_Tools_Change_Log' ) ) {
			EMCP_Tools_Change_Log::record( array(
				'domain'   => 'database',
				'action'   => 'insert',
				'target'   => $table,
				'summary'  => 'Inserted a row into ' . $table,
				'rollback' => array( 'type' => 'db-before-image', 'op' => 'insert', 'table' => $table, 'inserted_key' => $data ),
			) );
		}
		return array( 'table' => $table, 'insert_id' => (int) $wpdb->insert_id, 'affected' => (int) $ok );
	}

	private function register_update_rows(): void {
		$this->ability( 'emcp-tools/update-rows', __( 'Update Rows', 'emcp-tools' ), __( 'Update rows matching an equality WHERE (required, non-empty). Parameterized; before-image snapshot; refuses protected tables. Disabled by default.', 'emcp-tools' ), 'execute_update_rows', array( 'table' => array( 'type' => 'string' ), 'data' => array( 'type' => 'object' ), 'where' => array( 'type' => 'object' ) ), array( 'table', 'data', 'where' ), false );
	}
	public function execute_update_rows( $input ) {
		$data  = (array) ( $input['data'] ?? array() );
		$where = (array) ( $input['where'] ?? array() );
		if ( empty( $data ) ) {
			return new \WP_Error( 'no_data', __( 'A non-empty data object is required.', 'emcp-tools' ) );
		}
		if ( empty( $where ) ) {
			return new \WP_Error( 'where_required', __( 'A non-empty where object is required for update.', 'emcp-tools' ) );
		}
		$table = EMCP_Tools_Database_Guard::valid_table( (string) ( $input['table'] ?? '' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		if ( EMCP_Tools_Database_Guard::is_protected( $table ) ) {
			return new \WP_Error( 'protected_table', __( 'Writes to this table are not allowed.', 'emcp-tools' ) );
		}
		$before = EMCP_Tools_Database_Guard::before_image( $table, $where );
		global $wpdb;
		$affected = $wpdb->update( $table, $data, $where );
		if ( false === $affected ) {
			return new \WP_Error( 'update_failed', $wpdb->last_error ? $wpdb->last_error : __( 'Update failed.', 'emcp-tools' ) );
		}
		EMCP_Tools_Database_Guard::log( 'update', $table, (int) $affected, $before );
		if ( class_exists( 'EMCP_Tools_Change_Log' ) ) {
			EMCP_Tools_Change_Log::record( array(
				'domain'   => 'database',
				'action'   => 'update',
				'target'   => $table,
				'summary'  => sprintf( 'Updated %d row(s) in %s', (int) $affected, $table ),
				'rollback' => array( 'type' => 'db-before-image', 'op' => 'update', 'table' => $table, 'key_cols' => array_keys( $where ), 'before_rows' => $before ),
			) );
		}
		return array( 'table' => $table, 'affected' => (int) $affected, 'before_image_rows' => count( $before ) );
	}

	private function register_delete_rows(): void {
		$this->ability( 'emcp-tools/delete-rows', __( 'Delete Rows', 'emcp-tools' ), __( 'Delete rows matching an equality WHERE (required). Needs confirm:true; before-image snapshot; refuses protected tables. Disabled by default.', 'emcp-tools' ), 'execute_delete_rows', array( 'table' => array( 'type' => 'string' ), 'where' => array( 'type' => 'object' ), 'confirm' => array( 'type' => 'boolean' ) ), array( 'table', 'where' ), false );
	}
	public function execute_delete_rows( $input ) {
		if ( empty( $input['confirm'] ) || true !== $input['confirm'] ) {
			return new \WP_Error( 'confirm_required', __( 'Deleting rows requires confirm:true.', 'emcp-tools' ) );
		}
		$where = (array) ( $input['where'] ?? array() );
		if ( empty( $where ) ) {
			return new \WP_Error( 'where_required', __( 'A non-empty where object is required for delete.', 'emcp-tools' ) );
		}
		$table = EMCP_Tools_Database_Guard::valid_table( (string) ( $input['table'] ?? '' ) );
		if ( is_wp_error( $table ) ) {
			return $table;
		}
		if ( EMCP_Tools_Database_Guard::is_protected( $table ) ) {
			return new \WP_Error( 'protected_table', __( 'Writes to this table are not allowed.', 'emcp-tools' ) );
		}
		$before = EMCP_Tools_Database_Guard::before_image( $table, $where );
		global $wpdb;
		$affected = $wpdb->delete( $table, $where );
		if ( false === $affected ) {
			return new \WP_Error( 'delete_failed', $wpdb->last_error ? $wpdb->last_error : __( 'Delete failed.', 'emcp-tools' ) );
		}
		EMCP_Tools_Database_Guard::log( 'delete', $table, (int) $affected, $before );
		if ( class_exists( 'EMCP_Tools_Change_Log' ) ) {
			EMCP_Tools_Change_Log::record( array(
				'domain'   => 'database',
				'action'   => 'delete',
				'target'   => $table,
				'summary'  => sprintf( 'Deleted %d row(s) from %s', (int) $affected, $table ),
				'rollback' => array( 'type' => 'db-before-image', 'op' => 'delete', 'table' => $table, 'key_cols' => array_keys( $where ), 'before_rows' => $before ),
			) );
		}
		return array( 'table' => $table, 'affected' => (int) $affected, 'before_image_rows' => count( $before ) );
	}
}

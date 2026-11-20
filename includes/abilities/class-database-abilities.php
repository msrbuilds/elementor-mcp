<?php
/**
 * Database MCP tools: list-tables/describe-table/query (read; enabled) +
 * insert-row/update-rows/delete-rows (structured, parameterized writes;
 * disabled-by-default). Reads via validated read-only SQL; writes via
 * $wpdb->insert/update/delete with forced WHERE, protected tables and
 * confirm-on-delete, each run through EMCP_Tools_DB_Change so History can undo it.
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
		// Read-path secret guard: the user tables hold password hashes, session
		// tokens, and activation keys. Refuse raw reads that touch them and point
		// the agent at the dedicated, redacting user tools.
		// Cross-schema secrets. The protected-table list covers the WordPress
		// user tables only, so mysql.user (server account password hashes) and
		// the metadata schemas would otherwise be readable straight through.
		if ( EMCP_Tools_Database_Guard::references_system_schema( $sql ) ) {
			return new \WP_Error(
				'protected_read',
				__( 'Reading the server system schemas (mysql, information_schema, performance_schema, sys) is not allowed.', 'emcp-tools' )
			);
		}
		if ( EMCP_Tools_Database_Guard::query_touches_protected( $sql ) ) {
			return new \WP_Error(
				'protected_read',
				__( 'Reading the user tables (passwords, session tokens, activation keys) via raw SQL is not allowed. Use the list-users / get-user tools instead.', 'emcp-tools' )
			);
		}
		global $wpdb;
		$limit = isset( $input['limit'] ) ? (int) $input['limit'] : EMCP_Tools_Database_Guard::MAX_ROWS;
		$limit = min( EMCP_Tools_Database_Guard::MAX_ROWS, max( 1, $limit ) );

		// Bound the work in the DATABASE, not just the response. Previously the
		// full result set was fetched into PHP and sliced afterwards, so a wide
		// query could exhaust memory and run unbounded before the limit applied.
		$bounded = EMCP_Tools_Database_Guard::bound_sql( $sql, $limit + 1 ); // +1 detects truncation.
		if ( is_wp_error( $bounded ) ) {
			return $bounded;
		}

		// Server-side statement timeout, restored in the finally block below.
		$restore_timeout = EMCP_Tools_Database_Guard::apply_statement_timeout( $wpdb );
		try {
			$rows = $wpdb->get_results( $bounded, ARRAY_A ); // phpcs:ignore WordPress.DB -- validated read-only; admin-authored.
		} finally {
			if ( is_callable( $restore_timeout ) ) {
				$restore_timeout();
			}
		}
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
		$this->ability( 'emcp-tools/insert-row', __( 'Insert Row', 'emcp-tools' ), __( 'Insert a row into a table (parameterized). Refuses protected tables. Undoable from History when the table has a primary key and is InnoDB (the answer says undoable, or why not). Disabled by default.', 'emcp-tools' ), 'execute_insert_row', array( 'table' => array( 'type' => 'string' ), 'data' => array( 'type' => 'object' ) ), array( 'table', 'data' ), false );
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
		$unknown = EMCP_Tools_Database_Guard::unknown_column( $table, array_keys( $data ) );
		if ( $unknown ) {
			return $unknown;
		}
		$rows = EMCP_Tools_DB_Rows::driver();
		$out  = EMCP_Tools_DB_Change::write(
			array(
				'table' => $table,
				'op'    => 'insert',
				'data'  => $data,
				'run'   => static function () use ( $rows, $table, $data ) {
					return $rows->insert( $table, $data );
				},
				'entry' => array(
					'domain'  => 'database',
					'action'  => 'insert',
					'target'  => $table,
					'summary' => 'Inserted a row into ' . $table,
				),
			)
		);
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		return array( 'table' => $table, 'insert_id' => (int) ( $out['insert_id'] ?? 0 ), 'affected' => (int) $out['result'] ) + self::undo_fields( $out );
	}

	/**
	 * Whether History can undo the write, and why not.
	 *
	 * @param array $out EMCP_Tools_DB_Change::write() result.
	 * @return array
	 */
	private static function undo_fields( array $out ): array {
		$fields = array( 'undoable' => (bool) $out['undoable'], 'change_id' => (string) $out['change_id'] );
		if ( ! $out['undoable'] ) {
			$fields['not_undoable'] = (string) $out['not_undoable'];
		}
		return $fields;
	}

	private function register_update_rows(): void {
		$this->ability( 'emcp-tools/update-rows', __( 'Update Rows', 'emcp-tools' ), __( 'Update rows matching an equality WHERE (required, non-empty). Parameterized; refuses protected tables. Undoable from History when the table has a primary key, is InnoDB and the update leaves primary key values alone (the answer says undoable, or why not). Refused with too_many_rows when it would change more than 500 rows. To change part of a large text value without sending it back, pass edits instead of (or beside) data: { column: [ { find, replace, expect } ] } applies in order to each matched row\'s current value; the call is refused unless find occurs exactly expect times (default 1), and if_md5 { column: md5 } refuses a value that changed since you read it. The answer lists each edited value\'s length and MD5 before and after. Disabled by default.', 'emcp-tools' ), 'execute_update_rows', array( 'table' => array( 'type' => 'string' ), 'data' => array( 'type' => 'object', 'description' => __( 'New column values. Optional when edits is given.', 'emcp-tools' ) ), 'where' => array( 'type' => 'object' ), 'edits' => array( 'type' => 'object', 'description' => __( 'Find-and-replace edits per column, applied in order to the current value: { "column": [ { "find": "old", "replace": "new", "expect": 1 } ] }.', 'emcp-tools' ), 'additionalProperties' => array( 'type' => 'array', 'items' => array( 'type' => 'object', 'properties' => array( 'find' => array( 'type' => 'string' ), 'replace' => array( 'type' => 'string' ), 'expect' => array( 'type' => 'integer', 'minimum' => 1 ) ), 'required' => array( 'find', 'replace' ) ) ) ), 'if_md5' => array( 'type' => 'object', 'description' => __( 'Optional { "column": "md5 of the value you read" }; refuses the write if that value changed.', 'emcp-tools' ), 'additionalProperties' => array( 'type' => 'string' ) ) ), array( 'table', 'where' ), false );
	}
	public function execute_update_rows( $input ) {
		$data  = (array) ( $input['data'] ?? array() );
		$where = (array) ( $input['where'] ?? array() );
		$edits = self::parse_edits( $input['edits'] ?? null, $input['if_md5'] ?? null );
		if ( is_wp_error( $edits ) ) {
			return $edits;
		}
		if ( empty( $data ) && empty( $edits['columns'] ) ) {
			return new \WP_Error( 'no_data', __( 'A non-empty data object, or edits, is required.', 'emcp-tools' ) );
		}
		if ( array_intersect( array_keys( $data ), array_keys( $edits['columns'] ) ) ) {
			return new \WP_Error( 'edit_overlap', __( 'A column cannot be in both data and edits.', 'emcp-tools' ) );
		}
		if ( ! empty( $edits['columns'] ) ) {
			return $this->update_rows_with_edits( $input, $data, $where, $edits );
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
		$unknown = EMCP_Tools_Database_Guard::unknown_column( $table, array_merge( array_keys( $data ), array_keys( $where ) ) );
		if ( $unknown ) {
			return $unknown;
		}
		$rows = EMCP_Tools_DB_Rows::driver();
		$out  = EMCP_Tools_DB_Change::write(
			array(
				'table' => $table,
				'op'    => 'update',
				'where' => $where,
				'data'  => $data,
				'run'   => static function () use ( $rows, $table, $data, $where ) {
					return $rows->update( $table, $data, $where );
				},
				'entry' => array(
					'domain'  => 'database',
					'action'  => 'update',
					'target'  => $table,
					'summary' => static function ( $affected ) use ( $table ) {
						return sprintf( 'Updated %d row(s) in %s', (int) $affected, $table );
					},
				),
			)
		);
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		return array( 'table' => $table, 'affected' => (int) $out['result'], 'before_image_rows' => (int) $out['rows'] ) + self::undo_fields( $out );
	}

	/**
	 * Validate `edits` ({ column: [ { find, replace, expect? } ] }) and `if_md5`
	 * ({ column: md5 }). Returns { columns, md5 } or a WP_Error (#191).
	 *
	 * @param mixed $raw_edits Edits input.
	 * @param mixed $raw_md5   if_md5 input.
	 * @return array|\WP_Error
	 */
	private static function parse_edits( $raw_edits, $raw_md5 ) {
		$out = array( 'columns' => array(), 'md5' => array() );
		if ( null === $raw_edits || array() === $raw_edits ) {
			return $out;
		}
		$bad = static fn( string $why ) => new \WP_Error( 'invalid_edit', $why, array( 'status' => 400 ) );
		if ( ! is_array( $raw_edits ) ) {
			return $bad( __( 'edits must be an object of column names to lists of { find, replace, expect }.', 'emcp-tools' ) );
		}
		foreach ( $raw_edits as $col => $list ) {
			if ( ! is_string( $col ) || '' === $col || ! is_array( $list ) || array() === $list || array_values( $list ) !== $list ) {
				return $bad( __( 'Each edits entry must be a column name with a non-empty list of edits.', 'emcp-tools' ) );
			}
			foreach ( $list as $edit ) {
				$find    = is_array( $edit ) ? ( $edit['find'] ?? null ) : null;
				$replace = is_array( $edit ) ? ( $edit['replace'] ?? null ) : null;
				$expect  = is_array( $edit ) ? ( $edit['expect'] ?? 1 ) : null;
				if ( ! is_string( $find ) || '' === $find || ! is_string( $replace ) || ! is_int( $expect ) || $expect < 1 ) {
					return $bad( __( 'Each edit needs a non-empty find string, a replace string, and an optional expect count of at least 1.', 'emcp-tools' ) );
				}
				$out['columns'][ $col ][] = array( 'find' => $find, 'replace' => $replace, 'expect' => $expect );
			}
		}
		foreach ( (array) ( $raw_md5 ?? array() ) as $col => $md5 ) {
			if ( ! isset( $out['columns'][ $col ] ) || ! is_string( $md5 ) || ! preg_match( '/^[a-f0-9]{32}$/i', $md5 ) ) {
				return $bad( __( 'if_md5 must map edited columns to 32-character MD5 hashes.', 'emcp-tools' ) );
			}
			$out['md5'][ $col ] = strtolower( $md5 );
		}
		return $out;
	}

	/**
	 * update-rows with server-side find-and-replace edits (#191). The edits run on
	 * the locked current value of each matched row inside the History write, so a
	 * one-line change never resends a whole field.
	 */
	private function update_rows_with_edits( array $input, array $data, array $where, array $edits ) {
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
		$unknown = EMCP_Tools_Database_Guard::unknown_column( $table, array_merge( array_keys( $data ), array_keys( $edits['columns'] ), array_keys( $where ) ) );
		if ( $unknown ) {
			return $unknown;
		}
		$rows   = EMCP_Tools_DB_Rows::driver();
		$edited = array();
		$out    = EMCP_Tools_DB_Change::write(
			array(
				'table' => $table,
				'op'    => 'update',
				'where' => $where,
				'data'  => $data + array_fill_keys( array_keys( $edits['columns'] ), '' ),
				'run'   => static function () use ( $rows, $table, $data, $where, $edits, &$edited ) {
					$pk      = $rows->primary_key( $table );
					$matched = $rows->select( $table, $where, EMCP_Tools_DB_Change::CAP + 1, true );
					if ( empty( $pk ) && count( $matched ) > 1 ) {
						return new \WP_Error( 'no_primary_key', __( 'Edits on a table without a primary key need a where that matches one row.', 'emcp-tools' ) );
					}
					// Work out every row's new values before writing any of them.
					$plan = array();
					foreach ( $matched as $row ) {
						$key = empty( $pk ) ? $where : array_intersect_key( $row, array_flip( $pk ) );
						$set = $data;
						foreach ( $edits['columns'] as $col => $list ) {
							$value = $row[ $col ] ?? null;
							if ( ! is_string( $value ) ) {
								/* translators: %s: column name. */
								return new \WP_Error( 'not_text', sprintf( __( 'Column %s holds no text in a matched row, so edits cannot apply.', 'emcp-tools' ), $col ), array( 'status' => 400, 'key' => $key ) );
							}
							if ( isset( $edits['md5'][ $col ] ) && md5( $value ) !== $edits['md5'][ $col ] ) {
								/* translators: %s: column name. */
								return new \WP_Error( 'value_changed', sprintf( __( 'Column %s changed since it was read (its MD5 no longer matches if_md5); nothing was written.', 'emcp-tools' ), $col ), array( 'status' => 409, 'key' => $key, 'md5' => md5( $value ) ) );
							}
							$new = $value;
							foreach ( $list as $i => $edit ) {
								$found = substr_count( $new, $edit['find'] );
								if ( $found !== $edit['expect'] ) {
									/* translators: 1: edit number, 2: column, 3: times found, 4: times expected. */
									return new \WP_Error( 'edit_count_mismatch', sprintf( __( 'Edit %1$d on column %2$s: find occurs %3$d time(s), expected %4$d. Nothing was written.', 'emcp-tools' ), $i + 1, $col, $found, $edit['expect'] ), array( 'status' => 409, 'key' => $key, 'column' => $col, 'edit' => $i, 'found' => $found, 'expect' => $edit['expect'] ) );
								}
								$new = str_replace( $edit['find'], $edit['replace'], $new );
							}
							$set[ $col ] = $new;
							$edited[]    = array(
								'key'    => $key,
								'column' => $col,
								'before' => array( 'length' => strlen( $value ), 'md5' => md5( $value ) ),
								'after'  => array( 'length' => strlen( $new ), 'md5' => md5( $new ) ),
							);
						}
						$plan[] = array( $set, $key );
					}
					$affected = 0;
					foreach ( $plan as [ $set, $key ] ) {
						$n = $rows->update( $table, $set, $key );
						if ( false === $n ) {
							return false;
						}
						$affected += (int) $n;
					}
					return $affected;
				},
				'entry' => array(
					'domain'  => 'database',
					'action'  => 'update',
					'target'  => $table,
					'summary' => static function ( $affected ) use ( $table ) {
						return sprintf( 'Edited %d row(s) in %s', (int) $affected, $table );
					},
				),
			)
		);
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		return array(
			'table'             => $table,
			'affected'          => (int) $out['result'],
			'before_image_rows' => (int) $out['rows'],
			'edited'            => $edited,
		) + self::undo_fields( $out );
	}

	private function register_delete_rows(): void {
		$this->ability( 'emcp-tools/delete-rows', __( 'Delete Rows', 'emcp-tools' ), __( 'Delete rows matching an equality WHERE (required). Needs confirm:true; refuses protected tables. Undoable from History when the table has a primary key and is InnoDB (rows come back under their original keys). Refused with too_many_rows when it would delete more than 500 rows. Disabled by default.', 'emcp-tools' ), 'execute_delete_rows', array( 'table' => array( 'type' => 'string' ), 'where' => array( 'type' => 'object' ), 'confirm' => array( 'type' => 'boolean' ) ), array( 'table', 'where' ), false );
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
		$unknown = EMCP_Tools_Database_Guard::unknown_column( $table, array_keys( $where ) );
		if ( $unknown ) {
			return $unknown;
		}
		$rows = EMCP_Tools_DB_Rows::driver();
		$out  = EMCP_Tools_DB_Change::write(
			array(
				'table' => $table,
				'op'    => 'delete',
				'where' => $where,
				'run'   => static function () use ( $rows, $table, $where ) {
					return $rows->delete( $table, $where );
				},
				'entry' => array(
					'domain'  => 'database',
					'action'  => 'delete',
					'target'  => $table,
					'summary' => static function ( $affected ) use ( $table ) {
						return sprintf( 'Deleted %d row(s) from %s', (int) $affected, $table );
					},
				),
			)
		);
		if ( is_wp_error( $out ) ) {
			return $out;
		}
		return array( 'table' => $table, 'affected' => (int) $out['result'], 'before_image_rows' => (int) $out['rows'] ) + self::undo_fields( $out );
	}
}

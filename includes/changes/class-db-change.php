<?php
/**
 * Undoable database writes (3.20.0, History type `db-rows`).
 *
 * write() runs a write in one History transaction: it locks the rows the write touches
 * (SELECT ... FOR UPDATE), runs it, reads every touched row back by primary key and records
 * each row's before-image and a fingerprint of its after-state, so the write and its History
 * entry commit together. rollback() locks the same keys, refuses when a row no longer matches
 * its fingerprint (unless forced), puts every row back by key and checks the result.
 *
 * A write History cannot undo safely still runs, recorded as irreversible with the reason: a
 * table without a primary key, a non-transactional engine, an update that changes primary key
 * values, or no History transaction. A write that would touch more than CAP rows is refused.
 * The pre-3.17 `db-before-image` entries stay not undoable (no row identities).
 *
 * @package EMCP_Tools
 * @since   3.20.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** @since 3.20.0 */
class EMCP_Tools_DB_Change {

	const TYPE = 'db-rows';

	/** Most rows one write may touch. */
	const CAP = 500;

	/**
	 * Run and record a write.
	 *
	 * @param array $args {
	 *     @type string   $table   Validated table name.
	 *     @type string   $op      insert | update | delete | custom.
	 *     @type array    $where   Equality WHERE of the rows the write touches (empty for insert).
	 *     @type array    $data    Values written (insert and update).
	 *     @type callable $run     The write: returns rows affected, null when unknown, false or a WP_Error.
	 *     @type array    $entry   History entry: domain, action, target, summary (a string, or a
	 *                             callable taking the rows affected).
	 *     @type string   $cap     Capability needed to undo (default manage_options).
	 *     @type int      $post_id Post the rows belong to; undo then also needs edit_post on it.
	 * }
	 * @return array|WP_Error { result, undoable, not_undoable?, rows, change_id, insert_id }
	 *                        A write that matched no rows records nothing: change_id is ''.
	 *                        insert_id is read straight after the write, before History's own
	 *                        insert on the same connection moves $wpdb->insert_id (#167).
	 */
	public static function write( array $args ) {
		$table  = (string) $args['table'];
		$reason = self::not_undoable_reason( $table, $args );
		if ( '' === $reason ) {
			$out = EMCP_Tools_Change_Log::transaction(
				static function () use ( $args ) {
					return self::write_locked( $args );
				}
			);
			if ( ! ( is_wp_error( $out ) && 'not_transactional' === $out->get_error_code() ) ) {
				return $out;
			}
			$reason = __( 'History could not run this write in a database transaction (its table was not ready), so it was made without an undo record.', 'emcp-tools' );
		}

		$result = self::run( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$insert_id = self::insert_id_after( $args );
		if ( self::matched_nothing( $args, $result ) ) {
			return self::nothing_changed( $insert_id );
		}
		$id = EMCP_Tools_Change_Log::record(
			self::entry(
				$args,
				$result,
				array(
					'type'   => 'irreversible',
					'reason' => $reason,
				)
			)
		);
		return array(
			'result'       => $result,
			'undoable'     => false,
			'not_undoable' => $reason,
			'rows'         => is_int( $result ) ? $result : 0,
			'change_id'    => $id,
			'insert_id'    => $insert_id,
		);
	}

	/**
	 * The new row's auto-increment id, read before anything else writes on the connection.
	 *
	 * @param array $args write() arguments.
	 */
	private static function insert_id_after( array $args ): int {
		return 'insert' === ( $args['op'] ?? '' ) ? EMCP_Tools_DB_Rows::driver()->insert_id() : 0;
	}

	/**
	 * Whether an update or delete reported no rows changed.
	 *
	 * @param array    $args   write() arguments.
	 * @param int|null $result Rows affected.
	 */
	private static function matched_nothing( array $args, $result ): bool {
		return 'insert' !== ( $args['op'] ?? '' ) && 0 === $result;
	}

	/**
	 * The answer for a write that changed nothing: no History entry, nothing to undo.
	 *
	 * @param int $insert_id Insert id (0 unless an insert).
	 */
	private static function nothing_changed( int $insert_id = 0 ): array {
		return array(
			'result'       => 0,
			'undoable'     => false,
			'not_undoable' => __( 'The write matched no rows, so nothing changed and History recorded nothing.', 'emcp-tools' ),
			'rows'         => 0,
			'change_id'    => '',
			'insert_id'    => $insert_id,
		);
	}

	/**
	 * Why History cannot undo this write, or '' when it can.
	 *
	 * @param string $table Table.
	 * @param array  $args  write() arguments.
	 */
	private static function not_undoable_reason( string $table, array $args ): string {
		$rows = EMCP_Tools_DB_Rows::driver();
		$pk   = $rows->primary_key( $table );
		if ( empty( $pk ) ) {
			return __( 'This table has no primary key, so History cannot tell its rows apart to undo the change.', 'emcp-tools' );
		}
		if ( ! $rows->transactional( $table ) ) {
			return __( 'This table does not support transactions (it is not InnoDB), so History cannot lock its rows to undo the change safely.', 'emcp-tools' );
		}
		$written = array_map( 'strtolower', array_keys( (array) ( $args['data'] ?? array() ) ) );
		$pk_lc   = array_map( 'strtolower', $pk );
		if ( 'update' === ( $args['op'] ?? '' ) && array_intersect( $written, $pk_lc ) ) {
			return __( 'This update changes primary key values, so History cannot find the rows again to undo it.', 'emcp-tools' );
		}
		if ( 'insert' === ( $args['op'] ?? '' ) && count( $pk ) > 1 && array_diff( $pk_lc, $written ) ) {
			return __( 'History cannot identify the inserted row, because not every primary key column was given.', 'emcp-tools' );
		}
		return '';
	}

	/**
	 * The write inside the History transaction.
	 *
	 * @param array $args write() arguments.
	 * @return array|WP_Error
	 */
	private static function write_locked( array $args ) {
		$rows  = EMCP_Tools_DB_Rows::driver();
		$table = (string) $args['table'];
		$op    = (string) ( $args['op'] ?? '' );
		$where = (array) ( $args['where'] ?? array() );
		$pk    = $rows->primary_key( $table );

		$before = array();
		if ( ! empty( $where ) ) {
			$before = $rows->select( $table, $where, self::CAP + 1, true );
			if ( count( $before ) > self::CAP ) {
				/* translators: %d: the row limit. */
				return new WP_Error( 'too_many_rows', sprintf( __( 'This write would change more than %d rows, more than History can keep to undo it. Narrow the where so it matches fewer rows; nothing was changed.', 'emcp-tools' ), self::CAP ), array( 'status' => 400 ) );
			}
		}

		$result = self::run( $args );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$insert_id = self::insert_id_after( $args );
		if ( is_int( $result ) && 'insert' !== $op && $result > count( $before ) ) {
			return new WP_Error( 'rows_changed', __( 'The write changed rows History had not locked, so it was cancelled and nothing changed. Try again.', 'emcp-tools' ) );
		}

		$keys = array();
		foreach ( $before as $row ) {
			$keys[] = self::key_of( $row, $pk );
		}
		if ( 'insert' === $op ) {
			$key = self::inserted_key( (array) ( $args['data'] ?? array() ), $pk, $insert_id );
			if ( null === $key ) {
				return new WP_Error( 'row_unidentified', __( 'History could not identify the inserted row, so the insert was cancelled and nothing changed.', 'emcp-tools' ) );
			}
			$keys[] = $key;
		}
		if ( 'custom' === $op && ! empty( $where ) ) {
			foreach ( $rows->select( $table, $where, self::CAP + 1, true ) as $row ) {
				$keys[] = self::key_of( $row, $pk );
			}
		}

		$seen        = array();
		$ref_keys    = array();
		$before_rows = array();
		$after       = array();
		foreach ( $keys as $key ) {
			$sig = serialize( $key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- an identity, never unserialized.
			if ( isset( $seen[ $sig ] ) ) {
				continue;
			}
			$seen[ $sig ] = true;
			$was          = self::find( $before, $key );
			$now          = $rows->select( $table, $key, 1, true )[0] ?? null;
			if ( null === $was && null === $now ) {
				continue;
			}
			$ref_keys[]    = $key;
			$before_rows[] = $was;
			$after[]       = self::hash_row( $now, (array) ( $args['volatile'] ?? array() ) );
		}
		if ( empty( $ref_keys ) && 'insert' !== $op ) {
			return self::nothing_changed();
		}
		if ( count( $ref_keys ) > self::CAP ) {
			/* translators: %d: the row limit. */
			return new WP_Error( 'too_many_rows', sprintf( __( 'This write would change more than %d rows, more than History can keep to undo it. Nothing was changed.', 'emcp-tools' ), self::CAP ), array( 'status' => 400 ) );
		}

		$rb = array(
			'type'          => self::TYPE,
			'transactional' => true,
			'table'         => $table,
			'pk'            => $pk,
			'keys'          => $ref_keys,
			'after'         => $after,
			'cap'           => (string) ( $args['cap'] ?? 'manage_options' ),
		);
		if ( ! empty( $args['volatile'] ) ) {
			// Columns that change on their own (a visit counter): left out of the
			// fingerprint and kept as they are when the row is put back.
			$rb['volatile'] = array_values( array_map( 'strval', (array) $args['volatile'] ) );
		}
		if ( ! empty( $args['post_id'] ) ) {
			$rb['post_id'] = (int) $args['post_id'];
		}
		$rb = class_exists( 'EMCP_Tools_Change_Recorder' )
			? EMCP_Tools_Change_Recorder::attach_before( $rb, array( 'before_rows' => $before_rows ) )
			: array_merge( $rb, array( 'before_rows' => $before_rows ) );
		$id = EMCP_Tools_Change_Log::record( self::entry( $args, $result, $rb ) );
		if ( '' === $id ) {
			return new WP_Error( 'not_recorded', __( 'History could not record this write, so it was cancelled and nothing changed. Try again.', 'emcp-tools' ) );
		}
		return array(
			'result'    => $result,
			'undoable'  => true,
			'rows'      => count( $ref_keys ),
			'change_id' => $id,
			'insert_id' => $insert_id,
		);
	}

	/**
	 * Undo a recorded write, inside the change log's transaction.
	 *
	 * @param array $rb    Rollback ref, before-image resolved.
	 * @param bool  $force Overwrite rows changed since the write.
	 * @return true|WP_Error
	 */
	public static function rollback( array $rb, bool $force ) {
		$rows   = EMCP_Tools_DB_Rows::driver();
		$table  = (string) ( $rb['table'] ?? '' );
		$keys   = (array) ( $rb['keys'] ?? array() );
		$after  = (array) ( $rb['after'] ?? array() );
		$before   = (array) ( $rb['before_rows'] ?? array() );
		$volatile = (array) ( $rb['volatile'] ?? array() );
		if ( '' === $table || count( $keys ) !== count( $after ) || count( $keys ) !== count( $before ) ) {
			return new WP_Error( 'snapshot_incomplete', __( 'This database snapshot is incomplete and cannot restore the change.', 'emcp-tools' ) );
		}

		$current = array();
		foreach ( $keys as $i => $key ) {
			$current[ $i ] = $rows->select( $table, (array) $key, 1, true )[0] ?? null;
			if ( ! $force && ! hash_equals( (string) $after[ $i ], self::hash_row( $current[ $i ], $volatile ) ) ) {
				return new WP_Error( 'conflict', __( 'A row this change wrote has changed since (or another row now uses its key). Roll back anyway with force to overwrite the newer state.', 'emcp-tools' ) );
			}
		}

		foreach ( $keys as $i => $key ) {
			$key = (array) $key;
			$was = is_array( $before[ $i ] ) ? $before[ $i ] : null;
			if ( null === $was ) {
				$ok = null === $current[ $i ] ? 0 : $rows->delete( $table, $key );
			} elseif ( null === $current[ $i ] ) {
				$ok = $rows->insert( $table, $was );
			} else {
				$ok = $rows->update( $table, array_diff_key( $was, array_flip( $volatile ) ), $key );
			}
			if ( false === $ok ) {
				$why = $rows->last_error();
				return new WP_Error( 'rollback_failed', '' !== $why ? $why : __( 'The database refused to put a row back.', 'emcp-tools' ) );
			}
		}

		foreach ( $keys as $i => $key ) {
			$now = $rows->select( $table, (array) $key, 1, true )[0] ?? null;
			if ( ! hash_equals( self::hash_row( is_array( $before[ $i ] ) ? $before[ $i ] : null, $volatile ), self::hash_row( $now, $volatile ) ) ) {
				return new WP_Error( 'rollback_failed', __( 'A row did not read back as it was before the change, so the undo was cancelled and nothing changed.', 'emcp-tools' ) );
			}
		}
		/**
		 * Rows of a table were put back by an undo, behind the owning plugin's back: a plugin
		 * that caches those rows in memory clears its cache here.
		 *
		 * @since 3.20.0
		 *
		 * @param string $table Table name.
		 * @param array  $rb    Rollback ref.
		 */
		do_action( 'emcp_tools_db_rows_restored', $table, $rb );
		return true;
	}

	/**
	 * Fingerprint of a row as the database returns it ('' for no row).
	 *
	 * @param array|null $row Row.
	 */
	public static function hash_row( ?array $row, array $volatile = array() ): string {
		if ( null === $row ) {
			return '';
		}
		$skip = array_flip( array_map( 'strtolower', $volatile ) );
		$norm = array();
		foreach ( $row as $col => $val ) {
			if ( isset( $skip[ strtolower( (string) $col ) ] ) ) {
				continue;
			}
			$norm[ strtolower( (string) $col ) ] = null === $val ? null : (string) $val;
		}
		ksort( $norm );
		return hash( 'sha256', serialize( $norm ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- a fingerprint, never unserialized.
	}

	/**
	 * Run the write; false becomes a WP_Error.
	 *
	 * @param array $args write() arguments.
	 * @return int|null|WP_Error
	 */
	private static function run( array $args ) {
		$result = ( $args['run'] )();
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		if ( false === $result ) {
			$why = EMCP_Tools_DB_Rows::driver()->last_error();
			return new WP_Error( 'write_failed', '' !== $why ? $why : __( 'The database refused the write.', 'emcp-tools' ) );
		}
		return is_int( $result ) ? $result : null;
	}

	private static function entry( array $args, $result, array $rb ): array {
		$entry   = (array) ( $args['entry'] ?? array() );
		$summary = $entry['summary'] ?? '';
		if ( is_callable( $summary ) ) {
			$entry['summary'] = (string) $summary( is_int( $result ) ? $result : null );
		}
		$entry['rollback'] = $rb;
		return $entry;
	}

	/**
	 * The primary key values of a row, under the key's own column names.
	 *
	 * @param array    $row Row.
	 * @param string[] $pk  Key columns.
	 */
	private static function key_of( array $row, array $pk ): array {
		$lc  = array_change_key_case( $row, CASE_LOWER );
		$key = array();
		foreach ( $pk as $col ) {
			$key[ $col ] = $lc[ strtolower( $col ) ] ?? null;
		}
		return $key;
	}

	/**
	 * The new row's key: from the values given, else the auto-increment id.
	 *
	 * @param array    $data      Values inserted.
	 * @param string[] $pk        Key columns.
	 * @param int      $insert_id Auto-increment id.
	 * @return array|null
	 */
	private static function inserted_key( array $data, array $pk, int $insert_id ): ?array {
		$lc  = array_change_key_case( $data, CASE_LOWER );
		$key = array();
		foreach ( $pk as $col ) {
			if ( array_key_exists( strtolower( $col ), $lc ) && null !== $lc[ strtolower( $col ) ] ) {
				$key[ $col ] = (string) $lc[ strtolower( $col ) ];
			} elseif ( 1 === count( $pk ) && $insert_id > 0 ) {
				$key[ $col ] = (string) $insert_id;
			} else {
				return null;
			}
		}
		return $key;
	}

	private static function find( array $rows, array $key ): ?array {
		foreach ( $rows as $row ) {
			$match = true;
			$lc    = array_change_key_case( $row, CASE_LOWER );
			foreach ( $key as $col => $val ) {
				if ( (string) ( $lc[ strtolower( (string) $col ) ] ?? '' ) !== (string) $val ) {
					$match = false;
					break;
				}
			}
			if ( $match ) {
				return $row;
			}
		}
		return null;
	}
}

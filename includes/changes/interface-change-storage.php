<?php
/**
 * Every database and option touch of the change ledger (spec 9.1). The
 * production implementation is EMCP_Tools_Change_WPDB_Storage; unit tests use
 * an in-memory double with controllable lock and crash behaviour.
 *
 * Rows are arrays with the keys seq, id, ts, ts_us, domain, action, target,
 * summary, rollback (array|null), user_id, user_login, client, session,
 * rolled_back (bool), rolled_back_at (int|null).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change ledger storage.
 */
interface EMCP_Tools_Change_Storage {

	/** The store flag, read uncached: 'option' (default) or 'table'. */
	public function flag(): string;

	/** Write the store flag. */
	public function set_flag( string $v ): void;

	/** Uncached read of a ledger option, null when absent. */
	public function get_meta( string $option );

	/** Write a ledger option (not autoloaded). */
	public function set_meta( string $option, $value ): void;

	/** Delete a ledger option. */
	public function delete_meta( string $option ): void;

	/** Take the named lock: true acquired, false busy, null unavailable. */
	public function lock( int $timeout ): ?bool;

	/** Release the named lock. */
	public function unlock(): void;

	/** The stored serialized ledger option, uncached, null when absent. */
	public function option_raw(): ?string;

	/** The rows of a raw option value. */
	public function option_rows( ?string $raw ): array;

	/** Write the ledger option (add when absent). */
	public function option_write( array $rows ): bool;

	/** Compare-and-swap the ledger option against a raw value. */
	public function option_cas( ?string $old_raw, array $rows ): bool;

	/** Delete the ledger option. */
	public function option_remove(): void;

	/** Whether the ledger table exists. */
	public function table_exists(): bool;

	/** Create the ledger table; false when it could not be created. */
	public function table_create(): bool;

	/** Empty the table and reset its AUTO_INCREMENT. */
	public function table_truncate(): bool;

	/** Insert rows in one statement, in order. */
	public function table_insert_many( array $rows ): bool;

	/** Insert one row; its new seq, or null. */
	public function table_insert( array $row ): ?int;

	/** One row by id. */
	public function table_find( string $id ): ?array;

	/** Update rolled_back / rolled_back_at of one row. */
	public function table_update( string $id, array $fields ): bool;

	/** Delete one row; the deleted row, or null. */
	public function table_delete( string $id ): ?array;

	/** Delete every row with seq <= $seq; the number deleted. */
	public function table_delete_upto( int $seq ): int;

	/** Rows matching EMCP_Tools_Change_Memory_Filter arguments. */
	public function table_select( array $args ): array;

	/** Count of rows matching the arguments. */
	public function table_count( array $args = array() ): int;

	/**
	 * Row counts per ts range in one read, ranges ascending.
	 *
	 * @param array $bounds [ [ since, until ], ... ] inclusive.
	 * @param array $args   Filter arguments.
	 * @return array<int, array{0: int, 1: int}> [ all, rolled back ] per range.
	 */
	public function table_buckets( array $bounds, array $args ): array;

	/** Distinct non-empty clients, sorted, at most 50. */
	public function table_clients(): array;

	/** Every id in seq order. */
	public function table_ids(): array;

	/** Oldest rows with ts before $ts. */
	public function table_older_than( int $ts, int $limit ): array;

	/** Stamped session groups, newest last_seq first. */
	public function table_stamped_sessions( array $args ): array;

	/** Sleep (tests record instead). */
	public function sleep( float $seconds ): void;
}

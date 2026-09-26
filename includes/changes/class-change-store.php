<?php
/**
 * The change ledger's writer protocol (spec 9.1 steps 1 to 3 and 8). The flag
 * decides the store. The option store is only ever touched under the named
 * lock or, where MySQL has no named locks, by compare-and-swap. Contention
 * never queues, and destructive actions refuse during a live cutover.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change store.
 */
final class EMCP_Tools_Change_Store {

	const MAX_COUNT = 500;
	const MAX_BYTES = 2097152;
	const WAIT      = 5;
	const BACKOFF   = array( 1.0, 2.0, 4.0, 8.0 );
	const CAS_TRIES = 5;

	/** @var EMCP_Tools_Change_Storage */
	private $s;

	/** @var EMCP_Tools_Lease|null */
	private $lease;

	/** @var int Lock depth in this process (re-entrant in PHP, whatever MySQL does). */
	private $depth = 0;

	/**
	 * @param EMCP_Tools_Change_Storage $s     Storage.
	 * @param EMCP_Tools_Lease|null     $lease Lease (the options-table one by default).
	 */
	public function __construct( EMCP_Tools_Change_Storage $s, ?EMCP_Tools_Lease $lease = null ) {
		$this->s     = $s;
		$this->lease = $lease;
	}

	public function storage(): EMCP_Tools_Change_Storage {
		return $this->s;
	}

	public function lease(): EMCP_Tools_Lease {
		if ( null === $this->lease ) {
			$this->lease = new EMCP_Tools_Lease();
		}
		return $this->lease;
	}

	/** Whether the table is authoritative right now. */
	public function is_table(): bool {
		return 'table' === $this->s->flag() && $this->s->table_exists();
	}

	private function busy(): WP_Error {
		return new WP_Error( 'history_busy', __( 'History is busy, try again in a moment.', 'emcp-tools' ), array( 'status' => 409 ) );
	}

	/**
	 * 409 history_upgrading while a live migration lease exists and the option
	 * is still authoritative (decided from the lease, so a crashed migration
	 * never leaves History stuck).
	 */
	public function guard_destructive(): ?WP_Error {
		if ( 'option' === $this->s->flag() && $this->lease()->active( EMCP_Tools_Change_Names::lease() ) ) {
			return new WP_Error( 'history_upgrading', __( 'History is being upgraded, try again in a moment.', 'emcp-tools' ), array( 'status' => 409 ) );
		}
		return null;
	}

	/**
	 * Take the named lock: true, false (busy) or null (unavailable).
	 *
	 * @param int $timeout Seconds.
	 */
	private function acquire( int $timeout ): ?bool {
		if ( $this->depth > 0 ) {
			++$this->depth;
			return true;
		}
		$got = $this->s->lock( $timeout );
		if ( true === $got ) {
			$this->depth = 1;
		}
		return $got;
	}

	private function release(): void {
		if ( $this->depth > 1 ) {
			--$this->depth;
			return;
		}
		if ( 1 === $this->depth ) {
			$this->depth = 0;
			$this->s->unlock();
		}
	}

	/**
	 * Run $fn( bool $table, bool $cas ) holding the lock while the option store
	 * is authoritative. The flag is re-read inside the lock: when the cutover
	 * published meanwhile, $fn runs against the table. Without named locks $fn
	 * runs unlocked and must use compare-and-swap.
	 *
	 * @param callable $fn      Work.
	 * @param bool     $patient Retry with backoff (record) instead of one wait.
	 * @return mixed $fn's value, or a 409 history_busy WP_Error.
	 */
	public function with_lock( callable $fn, bool $patient = false ) {
		if ( 0 === $this->depth && $this->is_table() ) {
			return $fn( true, false );
		}
		$got = $this->acquire( self::WAIT );
		if ( false === $got && $patient ) {
			foreach ( self::BACKOFF as $seconds ) {
				$this->s->sleep( $seconds );
				$got = $this->acquire( 0 );
				if ( false !== $got ) {
					break;
				}
			}
		}
		if ( null === $got ) {
			return $fn( $this->is_table(), true );
		}
		if ( false === $got ) {
			return $this->busy();
		}
		try {
			return $fn( $this->is_table(), false );
		} finally {
			$this->release();
		}
	}

	/**
	 * Apply $change( array $rows ): ?array to a fresh read of the option. Under
	 * the lock the list is re-read uncached and written; without named locks
	 * the write is a compare-and-swap, retried on collision.
	 *
	 * @param callable $change Returns the new list, or null for "nothing to write".
	 * @param bool     $cas    Use compare-and-swap.
	 * @return bool|null True written, false failed, null nothing to write.
	 */
	private function mutate_option( callable $change, bool $cas ): ?bool {
		$tries = $cas ? self::CAS_TRIES : 1;
		for ( $i = 0; $i < $tries; $i++ ) {
			$raw  = $this->s->option_raw();
			$rows = $change( $this->s->option_rows( $raw ) );
			if ( null === $rows ) {
				return null;
			}
			$ok = $cas ? $this->s->option_cas( $raw, $rows ) : $this->s->option_write( $rows );
			if ( $ok ) {
				return true;
			}
			if ( $cas && $i < $tries - 1 ) {
				$this->s->sleep( wp_rand( 20, 120 ) / 1000 );
			}
		}
		return false;
	}

	/**
	 * Option-store caps (500 rows, about 2 MB).
	 *
	 * @param array $rows Rows, oldest first.
	 * @return array{0:array,1:array} Kept rows and dropped rows.
	 */
	public static function cap( array $rows ): array {
		$rows    = array_values( $rows );
		$dropped = array();
		if ( count( $rows ) > self::MAX_COUNT ) {
			$dropped = array_slice( $rows, 0, count( $rows ) - self::MAX_COUNT );
			$rows    = array_slice( $rows, -self::MAX_COUNT );
		}
		while ( count( $rows ) > 1 && strlen( (string) wp_json_encode( $rows ) ) > self::MAX_BYTES ) {
			$dropped[] = array_shift( $rows );
		}
		return array( array_values( $rows ), $dropped );
	}

	/**
	 * Store a new row.
	 *
	 * @param array $row     Complete row (without seq).
	 * @param array $dropped Receives rows the option cap evicted.
	 * @return string Its id, or '' when it could not be recorded.
	 */
	public function add( array $row, array &$dropped = array() ): string {
		$result = $this->with_lock(
			function ( bool $table, bool $cas ) use ( $row, &$dropped ) {
				if ( $table ) {
					return null !== $this->s->table_insert( $row );
				}
				return true === $this->mutate_option(
					static function ( array $rows ) use ( $row, &$dropped ) {
						$rows[]                 = $row;
						list( $rows, $dropped ) = EMCP_Tools_Change_Store::cap( $rows );
						return $rows;
					},
					$cas
				);
			},
			true
		);
		return true === $result ? (string) $row['id'] : '';
	}

	/** Option rows as ledger rows, with a positional seq. */
	private function option_list(): array {
		$out = array();
		foreach ( $this->s->option_rows( $this->s->option_raw() ) as $i => $r ) {
			$out[] = EMCP_Tools_Change_Codec::normalize( (array) $r, $i + 1 );
		}
		return $out;
	}

	public function find( string $id ): ?array {
		if ( $this->is_table() ) {
			return $this->s->table_find( $id );
		}
		foreach ( $this->option_list() as $r ) {
			if ( $r['id'] === $id ) {
				return $r;
			}
		}
		return null;
	}

	/**
	 * Update rolled_back / rolled_back_at.
	 *
	 * @param string $id     Row id.
	 * @param array  $fields Fields.
	 * @return bool|WP_Error
	 */
	public function update( string $id, array $fields ) {
		return $this->with_lock(
			function ( bool $table, bool $cas ) use ( $id, $fields ) {
				if ( $table ) {
					return null !== $this->s->table_find( $id ) && $this->s->table_update( $id, $fields );
				}
				return true === $this->mutate_option(
					static function ( array $rows ) use ( $id, $fields ) {
						$found = false;
						foreach ( $rows as &$r ) {
							if ( isset( $r['id'] ) && $r['id'] === $id ) {
								$r     = array_merge( $r, $fields );
								$found = true;
							}
						}
						unset( $r );
						return $found ? $rows : null;
					},
					$cas
				);
			}
		);
	}

	/**
	 * Delete one row.
	 *
	 * @param string $id Row id.
	 * @return array|null|WP_Error The removed row, null when absent.
	 */
	public function remove( string $id ) {
		$guard = $this->guard_destructive();
		if ( $guard ) {
			return $guard;
		}
		return $this->with_lock(
			function ( bool $table, bool $cas ) use ( $id ) {
				if ( $table ) {
					return $this->s->table_delete( $id );
				}
				$removed = null;
				$ok      = $this->mutate_option(
					static function ( array $rows ) use ( $id, &$removed ) {
						foreach ( $rows as $i => $r ) {
							if ( isset( $r['id'] ) && $r['id'] === $id ) {
								$removed = $r;
								unset( $rows[ $i ] );
								return array_values( $rows );
							}
						}
						return null;
					},
					$cas
				);
				return true === $ok ? $removed : null;
			}
		);
	}

	/** Rows per Clear History batch in the table. */
	const CLEAR_BATCH = 500;

	/**
	 * Delete every row. The table is cleared in batches, oldest first, so a
	 * large History is never loaded into memory at once.
	 *
	 * @param callable $on_removed Receives each batch of removed rows (for their blobs).
	 * @return int|WP_Error Rows removed.
	 */
	public function remove_all( callable $on_removed ) {
		$guard = $this->guard_destructive();
		if ( $guard ) {
			return $guard;
		}
		return $this->with_lock(
			function ( bool $table, bool $cas ) use ( $on_removed ) {
				if ( $table ) {
					$n = 0;
					do {
						$rows = $this->s->table_select(
							array(
								'order' => 'asc',
								'limit' => self::CLEAR_BATCH,
							)
						);
						if ( ! $rows ) {
							break;
						}
						$n += $this->s->table_delete_upto( (int) $rows[ count( $rows ) - 1 ]['seq'] );
						$on_removed( $rows );
					} while ( count( $rows ) === self::CLEAR_BATCH );
					return $n;
				}
				$removed = array();
				$ok      = $this->mutate_option(
					static function ( array $rows ) use ( &$removed ) {
						$removed = $rows;
						return $rows ? array() : null;
					},
					$cas
				);
				if ( true !== $ok ) {
					return 0;
				}
				$on_removed( $removed );
				return count( $removed );
			}
		);
	}

	/**
	 * Rows matching EMCP_Tools_Change_Memory_Filter arguments.
	 *
	 * @param array $args Arguments.
	 */
	public function select( array $args ): array {
		if ( $this->is_table() ) {
			return $this->s->table_select( $args );
		}
		return ( new EMCP_Tools_Change_Memory_Filter( $this->option_list() ) )->select( $args );
	}

	/**
	 * @param array $args Arguments.
	 */
	public function count( array $args = array() ): int {
		if ( $this->is_table() ) {
			return $this->s->table_count( $args );
		}
		return ( new EMCP_Tools_Change_Memory_Filter( $this->option_list() ) )->count( $args );
	}
}

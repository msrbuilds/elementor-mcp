<?php
/**
 * In-memory change storage for unit tests. The option half goes through the
 * bootstrap's get_option/update_option (so existing ledger tests keep seeding
 * $GLOBALS['_wp_options']); the table half is an array. Lock and CAS
 * behaviour are controllable, and $on_event lets a test interleave.
 *
 * @package EMCP_Tools\Tests
 */

/**
 * Lease store in memory (the production one needs $wpdb).
 */
final class EMCP_Tools_Change_Memory_Lease_Store implements EMCP_Tools_Lease_Store {

	/** @var array */
	public $data = array();

	public function get( string $key ): ?string {
		return $this->data[ $key ] ?? null;
	}

	public function insert( string $key, string $value ): bool {
		if ( isset( $this->data[ $key ] ) ) {
			return false;
		}
		$this->data[ $key ] = $value;
		return true;
	}

	public function swap( string $key, string $old, string $new ): bool {
		if ( ( $this->data[ $key ] ?? null ) !== $old ) {
			return false;
		}
		$this->data[ $key ] = $new;
		return true;
	}

	public function remove( string $key, string $old ): bool {
		if ( ( $this->data[ $key ] ?? null ) !== $old ) {
			return false;
		}
		unset( $this->data[ $key ] );
		return true;
	}
}

/**
 * Change storage in memory.
 */
final class EMCP_Tools_Change_Memory_Storage implements EMCP_Tools_Change_Storage {

	/** @var array seq => row */
	public $rows = array();
	/** @var int */
	public $next_seq = 1;
	/** @var bool */
	public $exists = false;
	/** @var bool Can the table be created (false simulates no CREATE). */
	public $can_create = true;
	/** @var bool Do multi-row inserts succeed. */
	public $can_insert = true;
	/** @var bool Does TRUNCATE succeed (it needs the DROP privilege). */
	public $can_truncate = true;
	/** @var string free | held | unavailable */
	public $lock_mode = 'free';
	/** @var int Lock attempts that still report busy before it frees (-1: always busy while held). */
	public $busy_for = -1;
	/** @var int Lock depth in this "connection". */
	public $depth = 0;
	/** @var int CAS attempts that collide before one succeeds. */
	public $cas_collisions = 0;
	/** @var float[] Sleeps requested. */
	public $slept = array();
	/** @var callable|null Called with ( $event, $storage ). */
	public $on_event = null;

	/**
	 * A lease over an in-memory store, with an optional clock.
	 *
	 * @param callable|null $clock Clock.
	 */
	public static function lease( ?callable $clock = null ): EMCP_Tools_Lease {
		return new EMCP_Tools_Lease( new EMCP_Tools_Change_Memory_Lease_Store(), $clock );
	}

	private function event( string $e ): void {
		if ( $this->on_event ) {
			( $this->on_event )( $e, $this );
		}
	}

	public function flag(): string {
		return 'table' === get_option( EMCP_Tools_Change_Names::flag() ) ? 'table' : 'option';
	}

	public function set_flag( string $v ): void {
		update_option( EMCP_Tools_Change_Names::flag(), $v );
	}

	public function get_meta( string $option ) {
		return get_option( $option, null );
	}

	public function set_meta( string $option, $value ): void {
		update_option( $option, $value );
	}

	public function delete_meta( string $option ): void {
		delete_option( $option );
	}

	public function lock( int $timeout ): ?bool {
		$this->event( 'lock' );
		if ( 'unavailable' === $this->lock_mode ) {
			return null;
		}
		if ( 'held' === $this->lock_mode ) {
			if ( 0 !== $this->busy_for ) {
				if ( $this->busy_for > 0 ) {
					--$this->busy_for;
				}
				return false;
			}
			$this->lock_mode = 'free';
		}
		++$this->depth;
		return true;
	}

	public function unlock(): void {
		$this->depth = max( 0, $this->depth - 1 );
		$this->event( 'unlock' );
	}

	public function option_raw(): ?string {
		$v = get_option( EMCP_Tools_Change_Names::option(), null );
		return null === $v ? null : serialize( $v ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
	}

	public function option_rows( ?string $raw ): array {
		$v = null === $raw ? array() : unserialize( $raw ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions
		return is_array( $v ) ? array_values( $v ) : array();
	}

	public function option_write( array $rows ): bool {
		$this->event( 'option_write' );
		return update_option( EMCP_Tools_Change_Names::option(), array_values( $rows ) );
	}

	public function option_cas( ?string $old_raw, array $rows ): bool {
		if ( $this->cas_collisions > 0 ) {
			--$this->cas_collisions;
			return false;
		}
		if ( $this->option_raw() !== $old_raw ) {
			return false;
		}
		return update_option( EMCP_Tools_Change_Names::option(), array_values( $rows ) );
	}

	public function option_remove(): void {
		delete_option( EMCP_Tools_Change_Names::option() );
	}

	public function table_exists(): bool {
		return $this->exists;
	}

	public function table_create(): bool {
		if ( ! $this->can_create ) {
			return false;
		}
		$this->exists = true;
		return true;
	}

	public function table_truncate(): bool {
		if ( ! $this->can_truncate ) {
			return false;
		}
		$this->rows     = array();
		$this->next_seq = 1;
		$this->event( 'truncate' );
		return true;
	}

	public function table_insert_many( array $rows ): bool {
		if ( ! $this->can_insert ) {
			return false;
		}
		foreach ( $rows as $r ) {
			if ( null === $this->table_insert( (array) $r ) ) {
				return false;
			}
		}
		$this->event( 'insert_many' );
		return true;
	}

	public function table_insert( array $row ): ?int {
		$db = EMCP_Tools_Change_Codec::row_to_db( $row );
		foreach ( $this->rows as $r ) {
			if ( $r['id'] === $db['id'] ) {
				return null; // UNIQUE id.
			}
		}
		$seq                = $this->next_seq++;
		$this->rows[ $seq ] = EMCP_Tools_Change_Codec::row_from_db( $db + array( 'seq' => $seq ) );
		return $seq;
	}

	public function table_find( string $id ): ?array {
		foreach ( $this->rows as $r ) {
			if ( $r['id'] === $id ) {
				return $r;
			}
		}
		return null;
	}

	public function table_update( string $id, array $fields ): bool {
		foreach ( $this->rows as $seq => $r ) {
			if ( $r['id'] === $id ) {
				$this->rows[ $seq ] = array_merge( $r, array_intersect_key( $fields, array_flip( array( 'rolled_back', 'rolled_back_at' ) ) ) );
				return true;
			}
		}
		return false;
	}

	public function table_delete( string $id ): ?array {
		foreach ( $this->rows as $seq => $r ) {
			if ( $r['id'] === $id ) {
				unset( $this->rows[ $seq ] );
				return $r;
			}
		}
		return null;
	}

	public function table_delete_upto( int $seq ): int {
		$n = 0;
		foreach ( array_keys( $this->rows ) as $k ) {
			if ( $k <= $seq ) {
				unset( $this->rows[ $k ] );
				++$n;
			}
		}
		$this->event( 'delete_upto' );
		return $n;
	}

	public function table_select( array $args ): array {
		return ( new EMCP_Tools_Change_Memory_Filter( $this->rows ) )->select( $args );
	}

	public function table_count( array $args = array() ): int {
		return ( new EMCP_Tools_Change_Memory_Filter( $this->rows ) )->count( $args );
	}

	public function table_clients(): array {
		$out = array();
		foreach ( $this->rows as $r ) {
			if ( '' !== $r['client'] ) {
				$out[ $r['client'] ] = true;
			}
		}
		$out = array_keys( $out );
		sort( $out, SORT_STRING );
		return array_slice( $out, 0, 50 );
	}

	public function table_ids(): array {
		ksort( $this->rows );
		return array_values( array_map( static fn( $r ) => $r['id'], $this->rows ) );
	}

	public function table_older_than( int $ts, int $limit ): array {
		return $this->table_select( array( 'until' => $ts - 1, 'order' => 'asc', 'limit' => $limit ) );
	}

	public function table_stamped_sessions( array $args ): array {
		$this->event( 'stamped_sessions' );
		ksort( $this->rows );
		$groups = array();
		foreach ( $this->rows as $r ) {
			if ( '' === $r['session'] || ( isset( $args['client'] ) && $r['client'] !== $args['client'] ) || ( isset( $args['user_id'] ) && $r['user_id'] !== (int) $args['user_id'] ) ) {
				continue;
			}
			$k = $r['session'] . "\0" . $r['client'] . "\0" . $r['user_id'];
			$g = $groups[ $k ] ?? array(
				'session'    => $r['session'],
				'client'     => $r['client'],
				'user_id'    => $r['user_id'],
				'user_login' => $r['user_login'],
				'first_seq'  => PHP_INT_MAX,
				'last_seq'   => 0,
				'first_ts'   => PHP_INT_MAX,
				'last_ts'    => 0,
				'count'      => 0,
				'open'       => 0,
			);
			$g['first_seq'] = min( $g['first_seq'], $r['seq'] );
			$g['last_seq']  = max( $g['last_seq'], $r['seq'] );
			$g['first_ts']  = min( $g['first_ts'], $r['ts'] );
			$g['last_ts']   = max( $g['last_ts'], $r['ts'] );
			++$g['count'];
			$g['open']    += $r['rolled_back'] ? 0 : 1;
			$groups[ $k ]  = $g;
		}
		$groups = array_values( array_filter( $groups, static fn( $g ) => ! isset( $args['before_seq'] ) || $g['last_seq'] < (int) $args['before_seq'] ) );
		usort( $groups, static fn( $x, $y ) => $y['last_seq'] <=> $x['last_seq'] );
		return array_slice( $groups, 0, max( 1, (int) ( $args['limit'] ?? 50 ) ) );
	}

	public function sleep( float $seconds ): void {
		$this->slept[] = $seconds;
		$this->event( 'sleep' );
	}
}

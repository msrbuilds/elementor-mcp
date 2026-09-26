<?php
/**
 * Shared, renewable, owner-checked lease (spec 9.6).
 *
 * A lease is one row `emcp_tools_lease_{name}` whose value is "{owner}|{expires}".
 * Every change is atomic: a free lease is created with an insert that fails when
 * the row exists, and every other change is a compare-and-swap on the exact
 * value that was read, so two contenders can never both win.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Storage with atomic create and compare-and-swap.
 */
interface EMCP_Tools_Lease_Store {
	public function get( string $key ): ?string;
	public function insert( string $key, string $value ): bool;
	public function swap( string $key, string $old, string $new ): bool;
	public function remove( string $key, string $old ): bool;
}

/**
 * Lease store on the options table, bypassing the options cache.
 */
final class EMCP_Tools_Lease_Options_Store implements EMCP_Tools_Lease_Store {

	public function get( string $key ): ?string {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- uncached read is the point.
		$value = $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key ) );
		return null === $value ? null : (string) $value;
	}

	public function insert( string $key, string $value ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')", $key, $value ) );
		$affected = (int) $wpdb->rows_affected;
		$this->forget( $key );
		return 1 === $affected;
	}

	public function swap( string $key, string $old, string $new ): bool {
		global $wpdb;
		if ( $old === $new ) {
			// MySQL counts changed rows, so an identical write reports 0; the
			// swap succeeds exactly when the row still holds the expected value.
			return $this->get( $key ) === $old;
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $new, $key, $old ) );
		$affected = (int) $wpdb->rows_affected;
		$this->forget( $key );
		return 1 === $affected;
	}

	public function remove( string $key, string $old ): bool {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name = %s AND option_value = %s", $key, $old ) );
		$affected = (int) $wpdb->rows_affected;
		$this->forget( $key );
		return 1 === $affected;
	}

	private function forget( string $key ): void {
		wp_cache_delete( $key, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
	}
}

/**
 * The lease API.
 */
final class EMCP_Tools_Lease {

	const PREFIX = 'emcp_tools_lease_';

	/** @var EMCP_Tools_Lease_Store */
	private $store;

	/** @var callable */
	private $clock;

	public function __construct( ?EMCP_Tools_Lease_Store $store = null, ?callable $clock = null ) {
		$this->store = $store ?? new EMCP_Tools_Lease_Options_Store();
		$this->clock = $clock ?? 'time';
	}

	/**
	 * Take the lease if it is free, expired, or already ours.
	 */
	public function acquire( string $name, string $owner, int $ttl ): bool {
		if ( ! $this->valid( $owner, $ttl ) ) {
			return false;
		}
		$key   = self::PREFIX . $name;
		$value = $this->encode( $owner, $this->now() + $ttl );
		if ( $this->store->insert( $key, $value ) ) {
			return true;
		}
		$current = $this->store->get( $key );
		if ( null === $current ) {
			return $this->store->insert( $key, $value );
		}
		list( $holder, $expires ) = $this->decode( $current );
		if ( $holder === $owner || $expires <= $this->now() ) {
			return $this->store->swap( $key, $current, $value );
		}
		return false;
	}

	/**
	 * Extend the lease; only its current owner can.
	 */
	public function renew( string $name, string $owner, int $ttl ): bool {
		if ( ! $this->valid( $owner, $ttl ) ) {
			return false;
		}
		$key     = self::PREFIX . $name;
		$current = $this->store->get( $key );
		if ( null === $current ) {
			return false;
		}
		list( $holder ) = $this->decode( $current );
		if ( $holder !== $owner ) {
			return false;
		}
		return $this->store->swap( $key, $current, $this->encode( $owner, $this->now() + $ttl ) );
	}

	/**
	 * Give the lease up; only its current owner can.
	 */
	public function release( string $name, string $owner ): bool {
		$key     = self::PREFIX . $name;
		$current = $this->store->get( $key );
		if ( null === $current ) {
			return false;
		}
		list( $holder ) = $this->decode( $current );
		return $holder === $owner && $this->store->remove( $key, $current );
	}

	/**
	 * Whether `$owner` holds an unexpired lease.
	 */
	public function holds( string $name, string $owner ): bool {
		$current = $this->store->get( self::PREFIX . $name );
		if ( null === $current ) {
			return false;
		}
		list( $holder, $expires ) = $this->decode( $current );
		return $holder === $owner && $expires > $this->now();
	}

	/**
	 * Whether anyone holds an unexpired lease (a crashed owner's expired lease does not count).
	 */
	public function active( string $name ): bool {
		$current = $this->store->get( self::PREFIX . $name );
		if ( null === $current ) {
			return false;
		}
		list( , $expires ) = $this->decode( $current );
		return $expires > $this->now();
	}

	private function valid( string $owner, int $ttl ): bool {
		return '' !== $owner && false === strpbrk( $owner, "\r\n" ) && $ttl > 0;
	}

	private function now(): int {
		return (int) call_user_func( $this->clock );
	}

	private function encode( string $owner, int $expires ): string {
		return $owner . '|' . $expires;
	}

	/** @return array{0:string,1:int} */
	private function decode( string $value ): array {
		$at = strrpos( $value, '|' );
		if ( false === $at ) {
			return array( '', 0 );
		}
		return array( substr( $value, 0, $at ), (int) substr( $value, $at + 1 ) );
	}
}

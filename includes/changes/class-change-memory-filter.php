<?php
/**
 * Filters, orders and limits ledger rows held in PHP (the option store, and
 * the test double) exactly like EMCP_Tools_Change_WPDB_Storage::table_select().
 *
 * Arguments: domain, client, session ('' = unstamped only), user_id,
 * rolled_back (bool|null), search (summary or target), since, until (ts),
 * domains (only these), domains_not (none of these),
 * seq_min, seq_max, before_seq, order ('desc' default | 'asc'), limit (50).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * In-memory ledger filter.
 */
final class EMCP_Tools_Change_Memory_Filter {

	/** @var array */
	private $rows;

	/**
	 * @param array $rows Ledger rows with seq.
	 */
	public function __construct( array $rows ) {
		$this->rows = array_values( $rows );
	}

	/**
	 * @param array $r Row.
	 * @param array $a Arguments.
	 */
	public static function matches( array $r, array $a ): bool {
		foreach ( array( 'domain', 'client', 'session' ) as $k ) {
			if ( isset( $a[ $k ] ) && (string) $r[ $k ] !== (string) $a[ $k ] ) {
				return false;
			}
		}
		if ( isset( $a['user_id'] ) && (int) $r['user_id'] !== (int) $a['user_id'] ) {
			return false;
		}
		if ( isset( $a['domains'] ) && ! in_array( (string) $r['domain'], (array) $a['domains'], true ) ) {
			return false;
		}
		if ( isset( $a['domains_not'] ) && in_array( (string) $r['domain'], (array) $a['domains_not'], true ) ) {
			return false;
		}
		if ( isset( $a['rolled_back'] ) && (bool) $r['rolled_back'] !== (bool) $a['rolled_back'] ) {
			return false;
		}
		if ( ! empty( $a['search'] ) && false === stripos( $r['summary'] . ' ' . $r['target'], (string) $a['search'] ) ) {
			return false;
		}
		if ( isset( $a['since'] ) && $r['ts'] < (int) $a['since'] ) {
			return false;
		}
		if ( isset( $a['until'] ) && $r['ts'] > (int) $a['until'] ) {
			return false;
		}
		if ( isset( $a['seq_min'] ) && $r['seq'] < (int) $a['seq_min'] ) {
			return false;
		}
		if ( isset( $a['seq_max'] ) && $r['seq'] > (int) $a['seq_max'] ) {
			return false;
		}
		if ( isset( $a['before_seq'] ) && $r['seq'] >= (int) $a['before_seq'] ) {
			return false;
		}
		return true;
	}

	/**
	 * @param array $args Arguments.
	 */
	public function select( array $args ): array {
		$rows = array_values( array_filter( $this->rows, static fn( $r ) => self::matches( $r, $args ) ) );
		$asc  = isset( $args['order'] ) && 'asc' === $args['order'];
		usort( $rows, static fn( $x, $y ) => $asc ? $x['seq'] <=> $y['seq'] : $y['seq'] <=> $x['seq'] );
		return array_slice( $rows, 0, max( 1, (int) ( $args['limit'] ?? 50 ) ) );
	}

	/**
	 * @param array $args Arguments (order and limit ignored).
	 */
	public function count( array $args = array() ): int {
		return count( array_filter( $this->rows, static fn( $r ) => self::matches( $r, $args ) ) );
	}
}

<?php
/**
 * Sessions over the ledger (spec 9.1): the stamped session id, or, for rows
 * without one, runs by the same client and user with no gap over 30 minutes.
 * Stamped groups come from SQL; unstamped windows are built in PHP.
 *
 * Keys: s:{session} for a stamped session; w:{first_ts}-{last_ts}:{user_id}:{rawurlencode(client)}
 * for an unstamped window (timestamps, not seq: the option store's seq is a
 * position that shifts on every delete or eviction, and windows of one client
 * and user are more than 30 minutes apart, so the bounds name exactly one
 * window); c:{id} for a change that is its own group (spec 9.2: an Admin
 * action, or an AI Chat call without a conversation key).
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change sessions.
 */
final class EMCP_Tools_Change_Sessions {

	const GAP = 1800;

	/** Newest unstamped rows scanned for windows (older windows are not listed). */
	const WINDOW_SCAN = 5000;

	/** @var EMCP_Tools_Change_Store */
	private $store;

	public function __construct( EMCP_Tools_Change_Store $store ) {
		$this->store = $store;
	}

	/**
	 * A page of sessions, newest last_seq first.
	 *
	 * @param array $args limit (default 20, max 100), cursor (a last_seq), client, user_id.
	 * @return array{items: array, next_cursor: ?int}
	 */
	public function page( array $args ): array {
		$limit  = max( 1, min( 100, (int) ( $args['limit'] ?? 20 ) ) );
		$filter = array_intersect_key( $args, array_flip( array( 'client', 'user_id' ) ) );
		$before = empty( $args['cursor'] ) ? null : (int) $args['cursor'];
		$items  = array_merge( $this->stamped( $filter, $before, $limit + 1 ), $this->windows( $filter, $before ) );
		usort(
			$items,
			static function ( $a, $b ) {
				return $b['last_seq'] <=> $a['last_seq'];
			}
		);
		$more  = count( $items ) > $limit;
		$items = array_slice( $items, 0, $limit );
		foreach ( $items as &$it ) {
			$it['title'] = $this->title( $it['key'] );
		}
		unset( $it );
		return array(
			'items'       => $items,
			'next_cursor' => ( $more && $items ) ? (int) $items[ count( $items ) - 1 ]['last_seq'] : null,
		);
	}

	private function stamped( array $filter, ?int $before, int $limit ): array {
		if ( $this->store->is_table() ) {
			$args = $filter + array( 'limit' => $limit );
			if ( null !== $before ) {
				$args['before_seq'] = $before;
			}
			$groups = $this->store->storage()->table_stamped_sessions( $args );
		} else {
			$groups = array();
			foreach ( $this->store->select( $filter + array( 'order' => 'asc', 'limit' => PHP_INT_MAX ) ) as $r ) {
				if ( '' === $r['session'] ) {
					continue;
				}
				$k            = $r['session'] . "\0" . $r['client'] . "\0" . $r['user_id'];
				$groups[ $k ] = self::grow( $groups[ $k ] ?? null, $r );
			}
			$groups = array_values(
				array_filter(
					$groups,
					static function ( $g ) use ( $before ) {
						return null === $before || $g['last_seq'] < $before;
					}
				)
			);
		}
		$out = array();
		foreach ( $groups as $g ) {
			$out[] = $g + array( 'key' => 's:' . $g['session'] );
		}
		return $out;
	}

	/**
	 * Unstamped windows. They are built from the newest rows without the
	 * cursor, then filtered, so a window is never split across pages.
	 */
	private function windows( array $filter, ?int $before ): array {
		$rows = array_reverse(
			$this->store->select(
				$filter + array(
					'session' => '',
					'order'   => 'desc',
					'limit'   => self::WINDOW_SCAN,
				)
			)
		);
		$open = array();
		$done = array();
		$out = array();
		foreach ( $rows as $r ) {
			if ( self::is_single( $r ) ) {
				$g = self::grow( null, $r );
				if ( null === $before || $g['last_seq'] < $before ) {
					$out[] = $g + array( 'key' => 'c:' . $r['id'] );
				}
				continue;
			}
			$k = $r['client'] . "\0" . $r['user_id'];
			if ( isset( $open[ $k ] ) && $r['ts'] - $open[ $k ]['last_ts'] <= self::GAP ) {
				$open[ $k ] = self::grow( $open[ $k ], $r );
				continue;
			}
			if ( isset( $open[ $k ] ) ) {
				$done[] = $open[ $k ];
			}
			$open[ $k ] = self::grow( null, $r );
		}
		foreach ( array_merge( $done, array_values( $open ) ) as $g ) {
			if ( null !== $before && $g['last_seq'] >= $before ) {
				continue;
			}
			$out[] = $g + array( 'key' => sprintf( 'w:%d-%d:%d:%s', $g['first_ts'], $g['last_ts'], $g['user_id'], rawurlencode( $g['client'] ) ) );
		}
		return $out;
	}

	/**
	 * Spec 9.2: an Admin action, and an AI Chat call without a conversation
	 * key, are each their own group.
	 *
	 * @param array $r Unstamped row.
	 */
	private static function is_single( array $r ): bool {
		return 'Admin' === $r['client'] || 0 === strpos( $r['client'], 'AI Chat' );
	}

	/**
	 * @param array|null $g Group so far.
	 * @param array      $r Row.
	 */
	private static function grow( ?array $g, array $r ): array {
		if ( null === $g ) {
			$g = array(
				'session'    => $r['session'],
				'client'     => $r['client'],
				'user_id'    => $r['user_id'],
				'user_login' => $r['user_login'],
				'first_seq'  => $r['seq'],
				'last_seq'   => $r['seq'],
				'first_ts'   => $r['ts'],
				'last_ts'    => $r['ts'],
				'count'      => 0,
				'open'       => 0,
			);
		}
		$g['first_seq'] = min( $g['first_seq'], $r['seq'] );
		$g['last_seq']  = max( $g['last_seq'], $r['seq'] );
		$g['first_ts']  = min( $g['first_ts'], $r['ts'] );
		$g['last_ts']   = max( $g['last_ts'], $r['ts'] );
		++$g['count'];
		$g['open'] += $r['rolled_back'] ? 0 : 1;
		return $g;
	}

	/** The first change's summary, or its tool when it has none. */
	private function title( string $key ): string {
		$first = $this->rows_for( $key, 'asc', 1 );
		if ( ! $first ) {
			return '';
		}
		return '' !== $first[0]['summary'] ? $first[0]['summary'] : $first[0]['action'];
	}

	/**
	 * Rows of a session key, or null for a malformed key.
	 *
	 * @param string $key   Session key.
	 * @param string $order asc | desc.
	 * @param int    $limit Max rows.
	 * @param array  $extra Extra EMCP_Tools_Change_Memory_Filter arguments (search, since,
	 *                      until, domains, domains_not, before_seq); time bounds intersect the key's.
	 */
	public function rows_for( string $key, string $order = 'desc', int $limit = 1000, array $extra = array() ): ?array {
		$base = array(
			'order' => $order,
			'limit' => $limit,
		);
		if ( 0 === strpos( $key, 's:' ) && strlen( $key ) > 2 ) {
			return $this->store->select( array( 'session' => substr( $key, 2 ) ) + $extra + $base );
		}
		if ( preg_match( '/^w:(\d+)-(\d+):(\d+):(.*)$/', $key, $m ) ) {
			$args          = array(
				'session' => '',
				'client'  => rawurldecode( $m[4] ),
				'user_id' => (int) $m[3],
			) + $extra + $base;
			$args['since'] = max( (int) $m[1], (int) ( $extra['since'] ?? 0 ) );
			$args['until'] = isset( $extra['until'] ) ? min( (int) $m[2], (int) $extra['until'] ) : (int) $m[2];
			return $this->store->select( $args );
		}
		if ( 0 === strpos( $key, 'c:' ) && strlen( $key ) > 2 ) {
			$row = $this->store->find( substr( $key, 2 ) );
			if ( ! $row ) {
				return null;
			}
			$ok = ( new EMCP_Tools_Change_Memory_Filter( array( $row ) ) )->count( $extra );
			return $ok ? array( $row ) : array();
		}
		return null;
	}
}

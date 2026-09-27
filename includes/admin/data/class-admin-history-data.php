<?php
/**
 * History screen payload (spec 8.19): sessions with their rows, filtered by
 * search, kind, client and date; totals, retention, clients seen and the
 * History banners of spec 9.1.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * History data.
 */
final class EMCP_Tools_Admin_History_Data {

	const PAGE       = 20;
	const MAX        = 100;
	const ROWS       = 200;
	const SCAN       = 100; // Sessions read per scan page.
	const SCAN_PAGES = 5;
	const KINDS      = array( 'content', 'design', 'settings' );
	const RANGES     = array(
		'24h' => 86400,
		'7d'  => 604800,
		'14d' => 1209600,
		'30d' => 2592000,
	);

	/**
	 * Sanitized filters.
	 *
	 * @param array $a Raw arguments (search, kind, client, range, from, to, before, limit).
	 */
	public static function filters( array $a ): array {
		$range = ( isset( $a['range'] ) && is_string( $a['range'] ) && isset( self::RANGES[ $a['range'] ] ) ) ? $a['range'] : '';
		$kind  = ( isset( $a['kind'] ) && in_array( $a['kind'], self::KINDS, true ) ) ? (string) $a['kind'] : '';
		return array(
			'search' => mb_substr( sanitize_text_field( (string) ( $a['search'] ?? '' ) ), 0, 100 ),
			'kind'   => $kind,
			'client' => mb_substr( sanitize_text_field( (string) ( $a['client'] ?? '' ) ), 0, 100 ),
			'range'  => $range,
			'from'   => '' !== $range ? time() - self::RANGES[ $range ] : max( 0, (int) ( $a['from'] ?? 0 ) ),
			'to'     => '' !== $range ? 0 : max( 0, (int) ( $a['to'] ?? 0 ) ),
			'cursor' => max( 0, (int) ( $a['before'] ?? 0 ) ),
			'limit'  => max( 1, min( self::MAX, (int) ( $a['limit'] ?? self::PAGE ) ) ),
		);
	}

	/**
	 * @param array $args Raw arguments.
	 */
	public function payload( array $args = array() ): array {
		$f                         = self::filters( $args );
		list( $sessions, $cursor ) = $this->sessions( $f );
		return array(
			'sessions'         => $sessions,
			'nextCursor'       => $cursor,
			'totals'           => array(
				'changes'    => EMCP_Tools_Change_Log::count(),
				'rolledBack' => EMCP_Tools_Change_Log::count( array( 'rolled_back' => true ) ),
			),
			'retention'        => EMCP_Tools_Change_Retention::days(),
			'retentionOptions' => EMCP_Tools_Change_Retention::ALLOWED,
			'clients'          => EMCP_Tools_Change_Log::clients(),
			'banners'          => self::banners(),
			'filters'          => array(
				'search' => $f['search'],
				'kind'   => $f['kind'],
				'client' => $f['client'],
				'range'  => $f['range'],
			),
		);
	}

	/**
	 * Up to $f['limit'] sessions with at least one matching row, and the cursor to continue from.
	 *
	 * @param array $f Filters.
	 * @return array{0: array, 1: ?int}
	 */
	private function sessions( array $f ): array {
		$helper = new EMCP_Tools_Change_Sessions( EMCP_Tools_Change_Log::store() );
		$out    = array();
		$cursor = $f['cursor'] ? $f['cursor'] : null;
		$pages  = 0;
		$extra  = self::row_filters( $f );
		do {
			$args = array( 'limit' => self::SCAN );
			if ( null !== $cursor ) {
				$args['cursor'] = $cursor;
			}
			if ( '' !== $f['client'] ) {
				$args['client'] = $f['client'];
			}
			$page = EMCP_Tools_Change_Log::sessions( $args );
			foreach ( $page['items'] as $s ) {
				if ( $f['from'] && $s['last_ts'] < $f['from'] ) {
					return array( $out, null ); // Everything further back is older still.
				}
				if ( $f['to'] && $s['first_ts'] > $f['to'] ) {
					continue;
				}
				// Filters run in the row query, before the row cap.
				$rows = (array) $helper->rows_for( $s['key'], 'desc', self::ROWS + 1, $extra );
				if ( $rows ) {
					$out[] = self::session( $s, $rows );
				}
				if ( count( $out ) >= $f['limit'] ) {
					return array( $out, (int) $s['last_seq'] );
				}
			}
			$cursor = $page['next_cursor'];
			++$pages;
		} while ( null !== $cursor && $pages < self::SCAN_PAGES );
		return array( $out, $cursor );
	}

	/**
	 * The row query arguments for the filters (EMCP_Tools_Change_Memory_Filter).
	 *
	 * @param array $f Filters.
	 */
	private static function row_filters( array $f ): array {
		$out = array();
		if ( '' !== $f['search'] ) {
			$out['search'] = $f['search'];
		}
		if ( $f['from'] ) {
			$out['since'] = $f['from'];
		}
		if ( $f['to'] ) {
			$out['until'] = $f['to'];
		}
		if ( 'settings' === $f['kind'] ) {
			$out['domains_not'] = array_merge( ...array_values( EMCP_Tools_Change_Log::KINDS ) );
		} elseif ( '' !== $f['kind'] ) {
			$out['domains'] = EMCP_Tools_Change_Log::KINDS[ $f['kind'] ];
		}
		return $out;
	}

	/**
	 * More rows of one session, older than $before_seq (the per-session "Show more").
	 *
	 * @param string $key        Session key.
	 * @param int    $before_seq Seq of the oldest row shown.
	 * @param array  $args       Raw filters.
	 * @return array{rows: array, more: bool}
	 */
	public function rows( string $key, int $before_seq, array $args ): array {
		$extra = self::row_filters( self::filters( $args ) );
		if ( $before_seq > 0 ) {
			$extra['before_seq'] = $before_seq;
		}
		$rows = (array) ( new EMCP_Tools_Change_Sessions( EMCP_Tools_Change_Log::store() ) )->rows_for( $key, 'desc', self::ROWS + 1, $extra );
		return array(
			'rows' => array_map( array( __CLASS__, 'row' ), array_slice( $rows, 0, self::ROWS ) ),
			'more' => count( $rows ) > self::ROWS,
		);
	}

	private static function session( array $s, array $rows ): array {
		$more = count( $rows ) > self::ROWS;
		$rows = array_map( array( __CLASS__, 'row' ), array_slice( $rows, 0, self::ROWS ) );
		return array(
			'key'       => $s['key'],
			'title'     => (string) $s['title'],
			'client'    => (string) $s['client'],
			'userLogin' => (string) $s['user_login'],
			'start'     => (int) $s['first_ts'],
			'end'       => (int) $s['last_ts'],
			'count'     => (int) $s['count'],
			'shown'     => count( $rows ),
			'open'      => (int) $s['open'],
			'undoable'  => count( array_filter( $rows, static fn( $r ) => $r['reversible'] ) ),
			'more'      => $more,
			'rows'      => $rows,
		);
	}

	/**
	 * @param array $r Ledger row.
	 */
	public static function row( array $r ): array {
		$blocker = EMCP_Tools_Change_Log::rollback_blocker( $r );
		return array(
			'id'          => $r['id'],
			'seq'         => (int) $r['seq'],
			'type'        => EMCP_Tools_Change_Log::type_of( $r ),
			'kind'        => EMCP_Tools_Change_Log::kind_of( $r['domain'] ),
			'title'       => '' !== $r['summary'] ? $r['summary'] : $r['action'],
			'description' => $r['target'],
			'tool'        => $r['action'],
			'time'        => (int) $r['ts'],
			'rolledBack'  => (bool) $r['rolled_back'],
			'diffable'    => EMCP_Tools_Change_Diff::supports( $r['rollback'] ),
			'reversible'  => ! is_wp_error( $blocker ),
			'reason'      => is_wp_error( $blocker ) ? $blocker->get_error_message() : '',
		);
	}

	/** Banners of spec 9.1 from EMCP_Tools_Change_Notices::state(). */
	public static function banners(): array {
		$state = EMCP_Tools_Change_Notices::state();
		$out   = array();
		if ( $state['unrecorded'] > 0 ) {
			$out[] = array(
				'id'          => 'unrecorded',
				'tone'        => 'warning',
				'title'       => __( 'Some changes are missing from History', 'emcp-tools' ),
				'body'        => sprintf(
					/* translators: %d: number of changes. */
					_n( "%d change couldn't be added to History while it was being upgraded; it can't be undone from here.", "%d changes couldn't be added to History while it was being upgraded; they can't be undone from here.", $state['unrecorded'], 'emcp-tools' ),
					$state['unrecorded']
				),
				'dismissible' => true,
				'action'      => null,
			);
		}
		if ( $state['stray'] ) {
			$out[] = array(
				'id'          => 'stray',
				'tone'        => 'warning',
				'title'       => __( 'Reconnect your AI client', 'emcp-tools' ),
				'body'        => __( "An AI connection is still running the previous version of EMCP Tools. Its changes aren't being recorded in History and can't be undone. Reconnect your AI client (restart it, or reload its MCP server) to fix this.", 'emcp-tools' ),
				'dismissible' => true,
				'action'      => array(
					'label' => __( 'Open MCP Log', 'emcp-tools' ),
					'url'   => admin_url( 'admin.php?page=emcp-tools-mcp-log' ),
				),
			);
		}
		if ( '' !== $state['fallback'] ) {
			$out[] = array(
				'id'          => 'fallback',
				'tone'        => 'info',
				'title'       => __( 'History is using its compatibility store', 'emcp-tools' ),
				'body'        => 'no_create' === $state['fallback']
					? __( "This site's database does not allow creating tables, so History keeps the last 500 changes in an option. Sessions, undo and retention still work.", 'emcp-tools' )
					: __( "This site's database does not support named locks, so History keeps the last 500 changes in an option. Sessions, undo and retention still work.", 'emcp-tools' ),
				'dismissible' => false,
				'action'      => null,
			);
		}
		if ( '' !== $state['cutover_error'] ) {
			$out[] = array(
				'id'          => 'cutover_error',
				'tone'        => 'danger',
				'title'       => __( 'History could not finish its upgrade', 'emcp-tools' ),
				'body'        => __( 'Copying History to its own table did not complete. It will try again automatically; until then History keeps working from its previous store.', 'emcp-tools' ),
				'dismissible' => false,
				'action'      => null,
			);
		}
		return $out;
	}
}

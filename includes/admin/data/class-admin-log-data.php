<?php
/**
 * MCP Log screen data (spec 8.22).
 *
 * @package EMCP_Tools
 * @since   3.18.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * MCP Log data.
 */
final class EMCP_Tools_Admin_Log_Data {

	const PER_PAGE = 50;

	/**
	 * One row as the screen shows it. The full error only under WP_DEBUG.
	 *
	 * @param array $r     Filtered row (with `_pos`).
	 * @param bool  $debug WP_DEBUG.
	 * @return array
	 */
	public static function row( array $r, bool $debug ): array {
		return array(
			'key'        => (int) ( $r['ts'] ?? 0 ) . '-' . (int) ( $r['_pos'] ?? 0 ),
			'ts'         => (int) ( $r['ts'] ?? 0 ),
			'method'     => (string) ( $r['method'] ?? '' ),
			'tool'       => (string) ( $r['tool'] ?? '' ),
			'status'     => 'error' === ( $r['status'] ?? '' ) ? 'error' : 'success',
			'ms'         => (int) ( $r['ms'] ?? 0 ),
			'reqId'      => (string) ( $r['req_id'] ?? '' ),
			'client'     => (string) ( $r['client'] ?? '' ),
			'session'    => (string) ( $r['session'] ?? '' ),
			'credential' => (string) ( $r['credential'] ?? '' ),
			'stage'      => (string) ( $r['stage'] ?? '' ),
			'reason'     => (string) ( $r['failure_reason'] ?? '' ),
			'error'      => $debug ? (string) ( $r['error'] ?? '' ) : '',
			'ledger'     => (string) ( $r['ledger'] ?? '' ),
		);
	}

	/**
	 * The screen payload for a status, search and page.
	 *
	 * @param array $args { status, search, page }.
	 * @return array
	 */
	public function payload( array $args = array() ): array {
		$rows   = EMCP_Tools_MCP_Request_Log::all();
		$status = in_array( $args['status'] ?? '', array( 'success', 'error' ), true ) ? (string) $args['status'] : 'all';
		$search = sanitize_text_field( (string) ( $args['search'] ?? '' ) );
		$hits   = EMCP_Tools_MCP_Request_Log::filter( $rows, array( 'status' => $status, 'search' => $search ) );
		$total  = count( $hits );
		$pages  = max( 1, (int) ceil( $total / self::PER_PAGE ) );
		$page   = (int) ( $args['page'] ?? 1 );
		$page   = $page < 1 || $page > $pages ? 1 : $page;
		$debug  = EMCP_Tools_MCP_Request_Log::debug_enabled();
		$stats  = EMCP_Tools_MCP_Request_Log::stats( $rows );
		return array(
			'rows'      => array_map(
				static function ( $r ) use ( $debug ) {
					return self::row( $r, $debug );
				},
				array_slice( $hits, ( $page - 1 ) * self::PER_PAGE, self::PER_PAGE )
			),
			'total'     => $total,
			'page'      => $page,
			'pages'     => $pages,
			'status'    => $status,
			'search'    => $search,
			'stats'     => array(
				'requests' => $stats['count'],
				'errors'   => $stats['errors'],
				'medianMs' => $stats['median_ms'],
				'slowest'  => $stats['slowest'],
				'since'    => $stats['since'],
			),
			'debug'     => $debug,
			'timezone'  => function_exists( 'wp_timezone_string' ) ? wp_timezone_string() : 'UTC',
			// Fetched with the X-WP-Nonce header: the wp_rest nonce in a URL would land in access logs.
			'exportPath' => '/emcp-tools/v1/admin/log/export.csv',
		);
	}
}

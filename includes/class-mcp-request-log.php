<?php
/**
 * MCP request log: a capped, option-backed ring buffer of recent MCP requests,
 * one row per JSON-RPC request (spec 9.2).
 *
 * Rows are written by EMCP_Tools_MCP_Observability (every routed request, on
 * both transports) and by EMCP_Tools_MCP_Log_Recorder (HTTP requests rejected
 * before routing). They carry who made the request (client, session,
 * credential), a normalized status and, on errors, a short failure reason.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class EMCP_Tools_MCP_Request_Log {

	const OPTION     = 'emcp_tools_mcp_request_log';
	const MAX_COUNT  = 500;
	const REASON_MAX = 300;
	const FIELD_MAX  = 100;

	/** Test seam: when non-null, overrides the WP_DEBUG check. */
	public static $debug_override = null;

	/** Whether to keep the underlying error message (only under WP_DEBUG). */
	public static function debug_enabled(): bool {
		if ( null !== self::$debug_override ) {
			return (bool) self::$debug_override;
		}
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}

	/**
	 * `success` or `error` from any status the callers use: the router's
	 * success/error, the old recorder's "ok", or an HTTP status code.
	 *
	 * @param mixed $status Raw status.
	 */
	public static function normalize_status( $status ): string {
		$s = strtolower( trim( (string) $status ) );
		if ( 'success' === $s || 'ok' === $s ) {
			return 'success';
		}
		if ( '' !== $s && ctype_digit( $s ) ) {
			$code = (int) $s;
			return ( $code >= 200 && $code < 400 ) ? 'success' : 'error';
		}
		return 'error';
	}

	/**
	 * Append a request row (newest last).
	 *
	 * @param array $entry method, tool, status, ms, req_id, client, session,
	 *                     credential, setup, stage, failure_reason, ledger, error.
	 */
	public static function record( array $entry ): void {
		$status = self::normalize_status( $entry['status'] ?? '' );
		$row    = array(
			'ts'             => time(),
			'method'         => self::field( $entry['method'] ?? '' ),
			'tool'           => self::field( $entry['tool'] ?? '' ),
			'status'         => $status,
			'ms'             => (int) round( (float) ( $entry['ms'] ?? 0 ) ),
			'req_id'         => self::field( $entry['req_id'] ?? '' ),
			'client'         => self::field( $entry['client'] ?? '' ),
			'session'        => self::field( $entry['session'] ?? '' ),
			'credential'     => self::field( $entry['credential'] ?? '' ),
			'setup'          => self::field( $entry['setup'] ?? '' ),
			'stage'          => 'error' === $status ? self::field( $entry['stage'] ?? '' ) : '',
			'failure_reason' => 'error' === $status ? self::reason( $entry['failure_reason'] ?? '' ) : '',
			'ledger'         => self::field( $entry['ledger'] ?? '' ),
		);
		if ( self::debug_enabled() && ! empty( $entry['error'] ) ) {
			$row['error'] = (string) $entry['error'];
		}

		$log   = self::all();
		$log[] = $row;
		if ( count( $log ) > self::MAX_COUNT ) {
			$log = array_slice( $log, -self::MAX_COUNT );
		}
		update_option( self::OPTION, $log, false );
	}

	/**
	 * All rows, oldest first. Read fresh: a long-running WP-CLI stdio process
	 * would otherwise keep its first copy in the object cache and overwrite
	 * rows other processes wrote (and undo a Clear log).
	 */
	public static function all(): array {
		wp_cache_delete( self::OPTION, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? array_values( $log ) : array();
	}

	/**
	 * Rows newest first, filtered by status and a case-insensitive search.
	 * Each carries `_pos`, its index in the stored (oldest-first) log.
	 *
	 * @param array $rows Stored rows, oldest first.
	 * @param array $args { status: all|success|error, search }.
	 * @return array
	 */
	public static function filter( array $rows, array $args ): array {
		$status = in_array( $args['status'] ?? '', array( 'success', 'error' ), true ) ? $args['status'] : 'all';
		$needle = strtolower( trim( (string) ( $args['search'] ?? '' ) ) );
		// The full error is searchable only when it may be shown: a match on
		// hidden text would reveal it one guess at a time.
		$debug = self::debug_enabled();
		$out   = array();
		for ( $i = count( $rows ) - 1; $i >= 0; $i-- ) {
			$r = $rows[ $i ];
			if ( ! is_array( $r ) || ( 'all' !== $status && ( $r['status'] ?? '' ) !== $status ) ) {
				continue;
			}
			if ( '' !== $needle ) {
				$hay = strtolower( implode( ' ', array( $r['method'] ?? '', $r['tool'] ?? '', $r['client'] ?? '', $r['req_id'] ?? '', $r['failure_reason'] ?? '', $debug ? ( $r['error'] ?? '' ) : '' ) ) );
				if ( false === strpos( $hay, $needle ) ) {
					continue;
				}
			}
			$out[] = $r + array( '_pos' => $i );
		}
		return $out;
	}

	/**
	 * Stats over the stored window (spec 9.2).
	 *
	 * @param array|null $rows Rows (default: the stored log).
	 * @return array{count:int, errors:int, median_ms:int, slowest:?array, since:int}
	 */
	public static function stats( ?array $rows = null ): array {
		$rows = null === $rows ? self::all() : $rows;
		$ms   = array();
		$err  = 0;
		$slow = null;
		foreach ( $rows as $r ) {
			$d    = (int) ( $r['ms'] ?? 0 );
			$ms[] = $d;
			if ( 'error' === ( $r['status'] ?? '' ) ) {
				++$err;
			}
			if ( null === $slow || $d > $slow['ms'] ) {
				$slow = array(
					'ms'   => $d,
					'tool' => '' !== (string) ( $r['tool'] ?? '' ) ? (string) $r['tool'] : (string) ( $r['method'] ?? '' ),
				);
			}
		}
		sort( $ms );
		$n   = count( $ms );
		$mid = intdiv( $n, 2 );
		return array(
			'count'     => $n,
			'errors'    => $err,
			'median_ms' => 0 === $n ? 0 : ( 1 === $n % 2 ? $ms[ $mid ] : (int) round( ( $ms[ $mid - 1 ] + $ms[ $mid ] ) / 2 ) ),
			'slowest'   => $slow,
			'since'     => $n ? (int) ( $rows[0]['ts'] ?? 0 ) : 0,
		);
	}

	/**
	 * CSV of the filtered rows, newest first. The error column exists only
	 * under WP_DEBUG; cells that a spreadsheet would run as a formula are
	 * prefixed with an apostrophe.
	 *
	 * @param array      $filters { status, search }.
	 * @param array|null $rows    Rows (default: the stored log).
	 * @return string
	 */
	public static function export_csv( array $filters, ?array $rows = null ): string {
		$debug = self::debug_enabled();
		$cols  = array( 'time_utc', 'method', 'tool', 'status', 'ms', 'request_id', 'client', 'session', 'credential', 'stage', 'failure_reason' );
		if ( $debug ) {
			$cols[] = 'error';
		}
		$fh = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		fputcsv( $fh, $cols, ',', '"', '' );
		foreach ( self::filter( null === $rows ? self::all() : $rows, $filters ) as $r ) {
			$line = array(
				gmdate( 'Y-m-d H:i:s', (int) ( $r['ts'] ?? 0 ) ),
				$r['method'] ?? '',
				$r['tool'] ?? '',
				$r['status'] ?? '',
				(int) ( $r['ms'] ?? 0 ),
				$r['req_id'] ?? '',
				$r['client'] ?? '',
				$r['session'] ?? '',
				$r['credential'] ?? '',
				$r['stage'] ?? '',
				$r['failure_reason'] ?? '',
			);
			if ( $debug ) {
				$line[] = $r['error'] ?? '';
			}
			fputcsv( $fh, array_map( array( __CLASS__, 'csv_cell' ), $line ), ',', '"', '' );
		}
		rewind( $fh );
		$csv = (string) stream_get_contents( $fh );
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return $csv;
	}

	/**
	 * A CSV cell a spreadsheet will not evaluate.
	 *
	 * @param mixed $v Value.
	 * @return string
	 */
	private static function csv_cell( $v ): string {
		// Locales whose list separator is `;` split an unquoted cell there, so a
		// formula after a `;` inside the text (the method is caller-controlled)
		// is neutralised too.
		$s = (string) preg_replace( '/;(\s*)([=+\-@\t\r])/', ";$1'$2", (string) $v );
		return ( '' !== $s && false !== strpos( "=+-@\t\r", $s[0] ) ) ? "'" . $s : $s;
	}

	/** Clear the log. */
	public static function clear(): void {
		update_option( self::OPTION, array(), false );
	}

	/**
	 * A short scalar field.
	 *
	 * @param mixed $value Value.
	 */
	private static function field( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		return substr( (string) $value, 0, self::FIELD_MAX );
	}

	/**
	 * Plain text, at most REASON_MAX characters.
	 *
	 * @param mixed $value Raw reason.
	 */
	private static function reason( $value ): string {
		if ( ! is_scalar( $value ) ) {
			return '';
		}
		$text = function_exists( 'wp_strip_all_tags' ) ? wp_strip_all_tags( (string) $value ) : strip_tags( (string) $value );
		$text = trim( (string) preg_replace( '/\s+/', ' ', $text ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $text, 0, self::REASON_MAX ) : substr( $text, 0, self::REASON_MAX );
	}
}

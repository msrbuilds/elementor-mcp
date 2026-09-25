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

	/** All rows, oldest first. */
	public static function all(): array {
		$log = get_option( self::OPTION, array() );
		return is_array( $log ) ? array_values( $log ) : array();
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

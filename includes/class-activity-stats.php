<?php
/**
 * Daily MCP activity counts for the Dashboard (spec 9.3). Incremented at the
 * same point the MCP log records a request.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Per-day calls, errors and tool counts.
 */
final class EMCP_Tools_Activity_Stats {

	const OPTION    = 'emcp_tools_activity_daily';
	const DAYS      = 90;
	const TOP_TOOLS = 50;
	const TOP_RANGE = 10;
	const DAY       = 86400;

	/**
	 * Count one request.
	 *
	 * @param string   $tool  Tool name for tools/call, '' otherwise.
	 * @param bool     $error Whether it failed.
	 * @param int|null $now   Timestamp (tests).
	 */
	public static function record( string $tool, bool $error, ?int $now = null ): void {
		$now   = $now ?? time();
		$key   = self::day_key( $now );
		$stats = self::load();
		$day   = $stats[ $key ] ?? array(
			'calls'  => 0,
			'errors' => 0,
			'tools'  => array(),
		);

		++$day['calls'];
		if ( $error ) {
			++$day['errors'];
		}
		if ( '' !== $tool ) {
			$tool                  = substr( $tool, 0, 100 );
			$day['tools'][ $tool ] = (int) ( $day['tools'][ $tool ] ?? 0 ) + 1;
			if ( count( $day['tools'] ) > self::TOP_TOOLS ) {
				arsort( $day['tools'] );
				$day['tools'] = array_slice( $day['tools'], 0, self::TOP_TOOLS, true );
			}
		}
		$stats[ $key ] = $day;

		$oldest = self::day_key( $now - ( self::DAYS - 1 ) * self::DAY );
		foreach ( array_keys( $stats ) as $date ) {
			if ( $date < $oldest ) {
				unset( $stats[ $date ] );
			}
		}
		update_option( self::OPTION, $stats, false );
	}

	/**
	 * Per-day series (oldest first, zero-filled) and the top tools in the range.
	 *
	 * @param int      $days Number of days ending today.
	 * @param int|null $now  Timestamp (tests).
	 * @return array{days: array<int, array{date:string, calls:int, errors:int}>, tools: array<string,int>}
	 */
	public static function range( int $days, ?int $now = null ): array {
		$now   = $now ?? time();
		$days  = max( 1, min( self::DAYS, $days ) );
		$stats = self::load();
		$out   = array();
		$tools = array();
		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$key   = self::day_key( $now - $i * self::DAY );
			$day   = $stats[ $key ] ?? array();
			$out[] = array(
				'date'   => $key,
				'calls'  => (int) ( $day['calls'] ?? 0 ),
				'errors' => (int) ( $day['errors'] ?? 0 ),
			);
			foreach ( (array) ( $day['tools'] ?? array() ) as $slug => $count ) {
				$tools[ $slug ] = ( $tools[ $slug ] ?? 0 ) + (int) $count;
			}
		}
		arsort( $tools );
		return array(
			'days'  => $out,
			'tools' => array_slice( $tools, 0, self::TOP_RANGE, true ),
		);
	}

	/**
	 * Site-local date key.
	 *
	 * @param int $ts Timestamp.
	 */
	public static function day_key( int $ts ): string {
		return function_exists( 'wp_date' ) ? (string) wp_date( 'Y-m-d', $ts ) : gmdate( 'Y-m-d', $ts );
	}

	/**
	 * Stored stats.
	 *
	 * @return array<string,array>
	 */
	private static function load(): array {
		$stats = get_option( self::OPTION, array() );
		return is_array( $stats ) ? $stats : array();
	}
}

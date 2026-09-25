<?php
/**
 * Connection step 4 (spec 9.5): does a request-log row belong to the user's
 * open setup? Only successful rows from the expected credential, or from the
 * stdio session tagged with this setup, complete it; nothing else does.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class EMCP_Tools_Connection_First_Call {

	const MAX_FAILURES = 3;

	/**
	 * @param array $record Setup record (EMCP_Tools_Connection_Setup).
	 * @param array $row    Request-log row.
	 */
	public static function belongs( array $record, array $row ): bool {
		if ( (int) ( $row['ts'] ?? 0 ) < (int) ( $record['since'] ?? PHP_INT_MAX ) ) {
			return false;
		}
		$credential = (string) ( $row['credential'] ?? '' );
		$expect     = (string) ( $record['expect'] ?? '' );
		switch ( (string) ( $record['method'] ?? '' ) ) {
			case 'app':
				return '' !== $expect && hash_equals( $expect, $credential );
			case 'oauth':
				if ( '' === $expect && ! empty( $record['tags']['oauth_client'] ) ) {
					$expect = 'oauth:' . $record['tags']['oauth_client'];
				}
				return '' !== $expect && hash_equals( $expect, $credential );
			case 'cli':
				return '' !== (string) ( $record['setup_id'] ?? '' )
					&& (string) ( $row['setup'] ?? '' ) === (string) $record['setup_id']
					&& 0 === strpos( $credential, 'cli:' );
		}
		return false;
	}

	/**
	 * @param array   $record Setup record.
	 * @param array[] $rows   Request-log rows, oldest first.
	 */
	public static function check( array $record, array $rows ): array {
		$failures = array();
		foreach ( $rows as $row ) {
			if ( ! self::belongs( $record, $row ) ) {
				continue;
			}
			if ( 'success' === EMCP_Tools_MCP_Request_Log::normalize_status( $row['status'] ?? '' ) ) {
				return array(
					'matched' => true,
					'method'  => (string) ( $row['method'] ?? '' ),
					'tool'    => (string) ( $row['tool'] ?? '' ),
					'client'  => (string) ( $row['client'] ?? '' ),
					'time'    => (int) ( $row['ts'] ?? 0 ),
				);
			}
			$failures[] = array(
				'method'         => (string) ( $row['method'] ?? '' ),
				'failure_reason' => (string) ( $row['failure_reason'] ?? '' ),
				'time'           => (int) ( $row['ts'] ?? 0 ),
			);
		}
		return array(
			'waiting'       => true,
			'seen_failures' => array_slice( $failures, -self::MAX_FAILURES ),
		);
	}
}

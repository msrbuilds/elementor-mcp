<?php
/**
 * Row and rollback encoding between the ledger's PHP rows and table columns.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change ledger codec.
 */
final class EMCP_Tools_Change_Codec {

	/**
	 * Rollback payload to JSON. A payload that does not survive a round trip
	 * (non-UTF-8 bytes in a before-image) becomes a blocked partial snapshot,
	 * so undo is refused instead of run on a mangled payload.
	 *
	 * @param array|null $rb Rollback ref.
	 */
	public static function encode_rollback( $rb ): ?string {
		if ( ! is_array( $rb ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode -- strict: no silent UTF-8 repair.
		$json = json_encode( $rb, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false !== $json && json_decode( $json, true ) === $rb ) {
			return $json;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode
		return (string) json_encode(
			array(
				'type'           => (string) ( $rb['type'] ?? '' ),
				'partial'        => true,
				'encoding_error' => true,
			)
		);
	}

	/**
	 * @param string|null $json Stored JSON.
	 */
	public static function decode_rollback( ?string $json ): ?array {
		if ( null === $json || '' === $json ) {
			return null;
		}
		$rb = json_decode( $json, true );
		return is_array( $rb ) ? $rb : null;
	}

	/**
	 * A ledger row as table columns (no seq: the table assigns it).
	 *
	 * @param array $row Row.
	 */
	public static function row_to_db( array $row ): array {
		$ts = (int) ( $row['ts'] ?? 0 );
		return array(
			'id'             => (string) ( $row['id'] ?? '' ),
			'ts'             => $ts,
			'ts_us'          => (int) ( $row['ts_us'] ?? $ts * 1000000 ),
			'domain'         => substr( (string) ( $row['domain'] ?? '' ), 0, 64 ),
			'action'         => substr( (string) ( $row['action'] ?? '' ), 0, 64 ),
			'target'         => substr( (string) ( $row['target'] ?? '' ), 0, 191 ),
			'summary'        => (string) ( $row['summary'] ?? '' ),
			'rollback'       => self::encode_rollback( $row['rollback'] ?? null ),
			'user_id'        => (int) ( $row['user_id'] ?? 0 ),
			'user_login'     => substr( (string) ( $row['user_login'] ?? '' ), 0, 60 ),
			'client'         => substr( (string) ( $row['client'] ?? '' ), 0, 100 ),
			'session'        => substr( (string) ( $row['session'] ?? '' ), 0, 100 ),
			'rolled_back'    => empty( $row['rolled_back'] ) ? 0 : 1,
			'rolled_back_at' => isset( $row['rolled_back_at'] ) && '' !== $row['rolled_back_at'] ? (int) $row['rolled_back_at'] : null,
		);
	}

	/**
	 * Table columns (strings from $wpdb) as a ledger row.
	 *
	 * @param array $db Columns.
	 */
	public static function row_from_db( array $db ): array {
		return array(
			'seq'            => (int) ( $db['seq'] ?? 0 ),
			'id'             => (string) ( $db['id'] ?? '' ),
			'ts'             => (int) ( $db['ts'] ?? 0 ),
			'ts_us'          => (int) ( $db['ts_us'] ?? 0 ),
			'domain'         => (string) ( $db['domain'] ?? '' ),
			'action'         => (string) ( $db['action'] ?? '' ),
			'target'         => (string) ( $db['target'] ?? '' ),
			'summary'        => (string) ( $db['summary'] ?? '' ),
			'rollback'       => self::decode_rollback( isset( $db['rollback'] ) ? (string) $db['rollback'] : null ),
			'user_id'        => (int) ( $db['user_id'] ?? 0 ),
			'user_login'     => (string) ( $db['user_login'] ?? '' ),
			'client'         => (string) ( $db['client'] ?? '' ),
			'session'        => (string) ( $db['session'] ?? '' ),
			'rolled_back'    => ! empty( $db['rolled_back'] ),
			'rolled_back_at' => ( isset( $db['rolled_back_at'] ) && '' !== (string) $db['rolled_back_at'] ) ? (int) $db['rolled_back_at'] : null,
		);
	}
}

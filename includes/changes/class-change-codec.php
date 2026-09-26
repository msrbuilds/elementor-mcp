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
	 * Rollback payload to JSON, exactly: a payload plain JSON would change
	 * (objects, binary bytes) goes in a serialized envelope instead.
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
		// JSON cannot hold this payload exactly (an object, binary bytes, INF):
		// keep its PHP serialization, hex-encoded (scanner-safe), inside a JSON
		// envelope, so a change that was reversible in the option store stays
		// reversible in the table.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.json_encode_json_encode, WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize
		return (string) json_encode( array( 'type' => (string) ( $rb['type'] ?? '' ), self::ENVELOPE => bin2hex( serialize( $rb ) ) ) );
	}

	/** Envelope key for payloads JSON cannot hold exactly. */
	const ENVELOPE = '__php';

	/**
	 * @param string|null $json Stored JSON.
	 */
	public static function decode_rollback( ?string $json ): ?array {
		if ( null === $json || '' === $json ) {
			return null;
		}
		$rb = json_decode( $json, true );
		if ( is_array( $rb ) && isset( $rb[ self::ENVELOPE ] ) && is_string( $rb[ self::ENVELOPE ] ) ) {
			// Only stdClass is revived; any other object stays an incomplete class.
			$hex = $rb[ self::ENVELOPE ];
			// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize
			$rb = ( '' !== $hex && ctype_xdigit( $hex ) ) ? @unserialize( (string) hex2bin( $hex ), array( 'allowed_classes' => array( 'stdClass' ) ) ) : null;
		}
		return is_array( $rb ) ? $rb : null;
	}

	/**
	 * Text for a column: valid UTF-8, cut by characters (column lengths are
	 * characters), because $wpdb refuses a query with invalid text.
	 *
	 * @param mixed    $s   Value.
	 * @param int|null $max Characters, null for no limit.
	 */
	public static function text( $s, ?int $max = null ): string {
		$s = (string) $s;
		if ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $s, 'UTF-8' ) ) {
			$s = function_exists( 'mb_scrub' ) ? mb_scrub( $s, 'UTF-8' ) : (string) mb_convert_encoding( $s, 'UTF-8', 'UTF-8' );
		}
		if ( null === $max ) {
			return $s;
		}
		return function_exists( 'mb_substr' ) ? mb_substr( $s, 0, $max, 'UTF-8' ) : substr( $s, 0, $max );
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
			'domain'         => self::text( $row['domain'] ?? '', 64 ),
			'action'         => self::text( $row['action'] ?? '', 64 ),
			'target'         => self::text( $row['target'] ?? '', 191 ),
			'summary'        => self::text( $row['summary'] ?? '' ),
			'rollback'       => self::encode_rollback( $row['rollback'] ?? null ),
			'user_id'        => (int) ( $row['user_id'] ?? 0 ),
			'user_login'     => self::text( $row['user_login'] ?? '', 60 ),
			'client'         => self::text( $row['client'] ?? '', 100 ),
			'session'        => self::text( $row['session'] ?? '', 100 ),
			'rolled_back'    => empty( $row['rolled_back'] ) ? 0 : 1,
			'rolled_back_at' => isset( $row['rolled_back_at'] ) && '' !== $row['rolled_back_at'] ? (int) $row['rolled_back_at'] : null,
		);
	}

	/**
	 * An option-store row with every key a table row has. The rollback payload
	 * is kept as stored: the option is PHP-serialized, so it never goes
	 * through JSON.
	 *
	 * @param array $row Stored row.
	 * @param int   $seq Position in the option list (1-based).
	 */
	public static function normalize( array $row, int $seq ): array {
		$out             = self::row_from_db( array_merge( self::row_to_db( array_diff_key( $row, array( 'rollback' => 1 ) ) ), array( 'seq' => $seq ) ) );
		$out['rollback'] = isset( $row['rollback'] ) && is_array( $row['rollback'] ) ? $row['rollback'] : null;
		return $out;
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

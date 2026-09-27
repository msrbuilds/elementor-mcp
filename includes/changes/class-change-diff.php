<?php
/**
 * Before/after preview of one ledger change (spec 9.1 Diff): the stored
 * before-image beside the target's current value, as text or JSON. The
 * screen renders the line diff; nothing here writes.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Change diff.
 */
final class EMCP_Tools_Change_Diff {

	const MAX_BYTES = 262144;
	const TYPES     = array( 'elementor-data', 'post-fields', 'meta-before-image', 'option', 'file-backup', 'file-create' );
	const NOT_SET   = '(not set)';

	/**
	 * @param array|null $rollback Rollback payload.
	 */
	public static function supports( ?array $rollback ): bool {
		return is_array( $rollback ) && in_array( (string) ( $rollback['type'] ?? '' ), self::TYPES, true );
	}

	/**
	 * @param string $id Ledger entry id.
	 * @return array{kind:string, before:string, after:string, reason?:string}
	 */
	public static function for_entry( string $id ): array {
		$entry = EMCP_Tools_Change_Log::get( $id );
		if ( ! $entry ) {
			return self::none( 'not_found' );
		}
		$rb = $entry['rollback'] ?? null;
		if ( ! self::supports( $rb ) ) {
			return self::none( 'unsupported' );
		}
		if ( ! empty( $rb['blob_id'] ) ) {
			$heavy = class_exists( 'EMCP_Tools_Change_Blobs' ) ? EMCP_Tools_Change_Blobs::get( (string) $rb['blob_id'] ) : null;
			if ( ! is_array( $heavy ) ) {
				return self::none( 'missing' );
			}
			$rb = array_merge( $rb, $heavy );
		}
		switch ( $rb['type'] ) {
			case 'elementor-data':
				return self::json( $rb['before'] ?? array(), self::elementor_now( (int) ( $rb['post_id'] ?? 0 ) ) );
			case 'option':
				return self::options( (array) ( $rb['values'] ?? array() ) );
			case 'post-fields':
				return self::post_fields( (int) ( $rb['post_id'] ?? 0 ), (array) ( $rb['before'] ?? array() ) );
			case 'meta-before-image':
				return self::meta( (string) ( $rb['object'] ?? 'post' ), (int) ( $rb['id'] ?? 0 ), (array) ( $rb['before'] ?? array() ) );
			case 'file-backup':
				return self::files( (string) ( $rb['backup_path'] ?? '' ), (string) ( $rb['target_path'] ?? '' ) );
			default: // file-create: nothing existed before.
				return self::files( '', (string) ( $rb['target_path'] ?? '' ) );
		}
	}

	private static function none( string $reason ): array {
		return array(
			'kind'   => 'none',
			'before' => '',
			'after'  => '',
			'reason' => $reason,
		);
	}

	private static function pretty( $value ): string {
		return (string) wp_json_encode( $value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
	}

	/** A text or JSON result, or none when either side is too large. */
	private static function result( string $kind, string $before, string $after ): array {
		if ( strlen( $before ) > self::MAX_BYTES || strlen( $after ) > self::MAX_BYTES ) {
			return self::none( 'too_large' );
		}
		return array(
			'kind'   => $kind,
			'before' => $before,
			'after'  => $after,
		);
	}

	private static function json( $before, $after ): array {
		return self::result( 'json', self::pretty( $before ), self::pretty( $after ) );
	}

	private static function elementor_now( int $post_id ) {
		$raw = get_post_meta( $post_id, '_elementor_data', true );
		if ( is_string( $raw ) ) {
			$decoded = json_decode( $raw, true );
			return is_array( $decoded ) ? $decoded : $raw;
		}
		return $raw;
	}

	private static function options( array $values ): array {
		$before = array();
		$after  = array();
		foreach ( $values as $name => $value ) {
			$before[ $name ] = '__ABSENT__' === $value ? self::NOT_SET : $value;
			$now             = get_option( (string) $name, '__ABSENT__' );
			$after[ $name ]  = '__ABSENT__' === $now ? self::NOT_SET : $now;
		}
		return self::json( $before, $after );
	}

	/** Strings as they are, anything else as pretty JSON. */
	private static function text_of( $value ): string {
		return is_string( $value ) ? $value : self::pretty( $value );
	}

	private static function section( string $title, $value ): string {
		return '## ' . $title . "\n" . self::text_of( $value ) . "\n\n";
	}

	private static function post_fields( int $post_id, array $before ): array {
		$post = get_post( $post_id );
		$now  = is_object( $post ) ? (array) $post : array();
		$b    = '';
		$a    = '';
		foreach ( (array) ( $before['fields'] ?? array() ) as $field => $value ) {
			$b .= self::section( (string) $field, $value );
			$a .= self::section( (string) $field, $now[ $field ] ?? '' );
		}
		foreach ( (array) ( $before['meta'] ?? array() ) as $key => $value ) {
			$b .= self::section( 'meta: ' . $key, '__DELETE__' === $value ? self::NOT_SET : $value );
			$a .= self::section( 'meta: ' . $key, self::meta_now( 'post', $post_id, (string) $key ) );
		}
		foreach ( (array) ( $before['meta_rows'] ?? array() ) as $key => $values ) {
			$now_rows = get_post_meta( $post_id, (string) $key, false );
			$b       .= self::section( 'meta: ' . $key, self::rows_text( (array) $values ) );
			$a       .= self::section( 'meta: ' . $key, self::rows_text( is_array( $now_rows ) ? $now_rows : array() ) );
		}
		foreach ( (array) ( $before['terms'] ?? array() ) as $tax => $ids ) {
			$now_ids = function_exists( 'wp_get_object_terms' ) ? wp_get_object_terms( $post_id, (string) $tax, array( 'fields' => 'ids' ) ) : array();
			$now_ids = is_array( $now_ids ) ? array_map( 'intval', $now_ids ) : array();
			sort( $now_ids );
			$was = array_map( 'intval', (array) $ids );
			sort( $was );
			$b .= self::section( 'terms: ' . $tax, implode( ', ', $was ) );
			$a .= self::section( 'terms: ' . $tax, implode( ', ', $now_ids ) );
		}
		return self::result( 'text', rtrim( $b ) . "\n", rtrim( $a ) . "\n" );
	}

	/** A meta key's stored rows: one value as itself, several as a list, none as not set. */
	private static function rows_text( array $rows ) {
		if ( ! $rows ) {
			return self::NOT_SET;
		}
		return 1 === count( $rows ) ? reset( $rows ) : array_values( $rows );
	}

	private static function meta_now( string $object, int $id, string $key ) {
		$fn = 'get_' . $object . '_meta';
		if ( ! in_array( $object, array( 'post', 'term', 'user', 'comment' ), true ) || ! function_exists( $fn ) ) {
			return self::NOT_SET;
		}
		$all = $fn( $id, $key, false );
		return ( is_array( $all ) && $all ) ? $all[0] : self::NOT_SET;
	}

	private static function meta( string $object, int $id, array $before ): array {
		$b = '';
		$a = '';
		foreach ( $before as $key => $value ) {
			$b .= self::section( (string) $key, ( '' === $value || array() === $value || '__DELETE__' === $value ) ? self::NOT_SET : $value );
			$a .= self::section( (string) $key, self::meta_now( $object, $id, (string) $key ) );
		}
		return self::result( 'text', rtrim( $b ) . "\n", rtrim( $a ) . "\n" );
	}

	/**
	 * A file's text ('' when it does not exist), or why it cannot be shown.
	 *
	 * @param string $path File path from the ledger.
	 * @return array{0:string, 1:string} Text and reason ('' when readable).
	 */
	private static function read( string $path ): array {
		if ( '' === $path ) {
			return array( '', '' );
		}
		$real = realpath( $path );
		$root = realpath( ABSPATH );
		if ( false === $real || false === $root ) {
			return array( '', '' );
		}
		$real = wp_normalize_path( $real );
		$root = trailingslashit( wp_normalize_path( $root ) );
		if ( 0 !== strpos( $real, $root ) ) {
			return array( '', 'outside' );
		}
		if ( ! is_file( $real ) ) {
			return array( '', '' );
		}
		if ( filesize( $real ) > self::MAX_BYTES ) {
			return array( '', 'too_large' );
		}
		$text = (string) file_get_contents( $real ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file inside ABSPATH.
		if ( false !== strpos( $text, "\0" ) || ( function_exists( 'mb_check_encoding' ) && ! mb_check_encoding( $text, 'UTF-8' ) ) ) {
			return array( '', 'binary' );
		}
		return array( $text, '' );
	}

	private static function files( string $before_path, string $after_path ): array {
		if ( '' !== $before_path && ! file_exists( $before_path ) ) {
			return self::none( 'missing' ); // The backup is gone; showing the file as new would mislead.
		}
		list( $before, $why_b ) = self::read( $before_path );
		list( $after, $why_a )  = self::read( $after_path );
		$why                    = '' !== $why_b ? $why_b : $why_a;
		if ( '' !== $why ) {
			return self::none( $why );
		}
		return self::result( 'text', $before, $after );
	}
}

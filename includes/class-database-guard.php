<?php
/**
 * Database safety guard: validates read-only SQL for the `query` tool,
 * validates/protects table names for structured writes, captures before-image
 * snapshots, and audits. is_read_only_sql() is the safety boundary for the
 * flexible read path.
 *
 * @package EMCP_Tools
 * @since   3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * @since 3.0.0
 */
class EMCP_Tools_Database_Guard {

	const MAX_ROWS         = 1000;
	const BEFORE_IMAGE_CAP = 500;
	const AUDIT_OPTION     = 'emcp_tools_db_audit_log';
	const AUDIT_MAX        = 100;

	/**
	 * Pure: normalize SQL for safe keyword scanning — replace every comment with
	 * a space and every string / backtick-identifier literal with an empty
	 * placeholder, so keywords cannot hide inside comments, strings, or quoted
	 * identifiers. Does NOT special-case /*! (the caller rejects those first).
	 *
	 * @param string $sql
	 * @return string
	 */
	public static function normalize_sql( string $sql ): string {
		$out = '';
		$len = strlen( $sql );
		$i   = 0;
		while ( $i < $len ) {
			$c   = $sql[ $i ];
			$two = substr( $sql, $i, 2 );
			if ( '--' === $two || '#' === $c ) {
				$nl  = strpos( $sql, "\n", $i );
				$i   = ( false === $nl ) ? $len : $nl + 1;
				$out .= ' ';
				continue;
			}
			if ( '/*' === $two ) {
				$end = strpos( $sql, '*/', $i + 2 );
				$i   = ( false === $end ) ? $len : $end + 2;
				$out .= ' ';
				continue;
			}
			if ( "'" === $c || '"' === $c ) {
				$q = $c;
				$i++;
				while ( $i < $len ) {
					if ( '\\' === $sql[ $i ] ) { $i += 2; continue; }
					if ( $sql[ $i ] === $q ) {
						if ( $i + 1 < $len && $sql[ $i + 1 ] === $q ) { $i += 2; continue; }
						$i++;
						break;
					}
					$i++;
				}
				$out .= "''";
				continue;
			}
			if ( '`' === $c ) {
				$i++;
				while ( $i < $len && '`' !== $sql[ $i ] ) { $i++; }
				$i++;
				$out .= '``';
				continue;
			}
			$out .= $c;
			$i++;
		}
		return $out;
	}

	/**
	 * Validate that $sql is a single read-only statement. Pure (no DB).
	 *
	 * @param string $sql
	 * @return true|\WP_Error
	 */
	public static function is_read_only_sql( string $sql ) {
		// MySQL executes the body of /*! ... */ executable comments, so we cannot
		// safely strip-and-trust. Refuse any SQL containing the marker.
		if ( false !== strpos( $sql, '/*!' ) ) {
			return new \WP_Error( 'executable_comment', __( 'MySQL executable comments (/*! ... */) are not allowed.', 'emcp-tools' ) );
		}
		$norm = trim( self::normalize_sql( $sql ) );
		if ( '' === $norm ) {
			return new \WP_Error( 'empty_sql', __( 'Empty query.', 'emcp-tools' ) );
		}
		// Multi-statement: any ';' that isn't the sole trailing character.
		$no_trailing = rtrim( $norm, "; \t\r\n" );
		if ( false !== strpos( $no_trailing, ';' ) ) {
			return new \WP_Error( 'multi_statement', __( 'Multiple SQL statements are not allowed.', 'emcp-tools' ) );
		}
		// File-access vectors (comments already normalized to spaces). Note: no
		// trailing \b — the load_file(...) branch ends in '(', and '(' followed
		// by another non-word char has no word boundary, so a trailing \b would
		// (incorrectly) let LOAD_FILE through.
		if ( preg_match( '/\b(into\s+outfile|into\s+dumpfile|load_file\s*\(|load\s+data\b)/i', $norm ) ) {
			return new \WP_Error( 'file_access_blocked', __( 'File-access SQL (OUTFILE/DUMPFILE/LOAD_FILE/LOAD DATA) is not allowed.', 'emcp-tools' ) );
		}
		// First keyword must be read-only.
		if ( ! preg_match( '/^([a-z]+)/i', $norm, $m ) ) {
			return new \WP_Error( 'not_read_only', __( 'Only read-only queries are allowed.', 'emcp-tools' ) );
		}
		$kw      = strtoupper( $m[1] );
		$allowed = array( 'SELECT', 'SHOW', 'DESCRIBE', 'DESC', 'EXPLAIN', 'WITH' );
		if ( ! in_array( $kw, $allowed, true ) ) {
			return new \WP_Error(
				'not_read_only',
				/* translators: %s: SQL keyword */
				sprintf( __( 'Only read-only queries are allowed (got %s).', 'emcp-tools' ), $kw )
			);
		}
		// Whole-statement write/DDL denylist (literals/comments already stripped,
		// so these match only real keyword tokens, not strings or identifiers).
		if ( preg_match( '/\b(INSERT|UPDATE|DELETE|REPLACE|MERGE|DROP|TRUNCATE|ALTER|CREATE|RENAME|GRANT|REVOKE|HANDLER|CALL|LOCK|UNLOCK|PREPARE|EXECUTE|INTO)\b/i', $norm ) ) {
			return new \WP_Error( 'not_read_only', __( 'The query contains a write or unsafe keyword.', 'emcp-tools' ) );
		}
		return true;
	}

	/**
	 * Resolve a table name against the live table list (table names cannot be
	 * parameterized). Returns the exact real name, or WP_Error.
	 *
	 * @param string $table
	 * @return string|\WP_Error
	 */
	public static function valid_table( string $table ) {
		global $wpdb;
		$table = trim( $table );
		if ( '' === $table ) {
			return new \WP_Error( 'unknown_table', __( 'A table name is required.', 'emcp-tools' ) );
		}
		$tables = (array) $wpdb->get_col( 'SHOW TABLES' );
		foreach ( $tables as $t ) {
			if ( strtolower( (string) $t ) === strtolower( $table ) ) {
				return (string) $t;
			}
		}
		return new \WP_Error( 'unknown_table', __( 'Unknown table.', 'emcp-tools' ) );
	}

	/**
	 * Pure: is $table in the protected list (case-insensitive)?
	 *
	 * @param string   $table
	 * @param string[] $protected
	 * @return bool
	 */
	public static function table_is_protected( string $table, array $protected ): bool {
		$t = strtolower( $table );
		foreach ( $protected as $p ) {
			if ( strtolower( (string) $p ) === $t ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Whether writes to $table are refused (users/usermeta by default).
	 *
	 * @param string $table
	 * @return bool
	 */
	public static function is_protected( string $table ): bool {
		global $wpdb;
		$protected = apply_filters( 'emcp_tools_db_protected_tables', array( $wpdb->users, $wpdb->usermeta ) );
		return self::table_is_protected( $table, (array) $protected );
	}

	/**
	 * Capture the rows an equality-AND WHERE will affect, before update/delete.
	 *
	 * @param string $table A validated real table name.
	 * @param array  $where col => value (equality AND).
	 * @return array
	 */
	public static function before_image( string $table, array $where ): array {
		global $wpdb;
		if ( empty( $where ) ) {
			return array();
		}
		$cond = array();
		$vals = array();
		foreach ( $where as $col => $val ) {
			$cond[] = '`' . str_replace( '`', '', (string) $col ) . '` = %s';
			$vals[] = $val;
		}
		$sql  = "SELECT * FROM `" . str_replace( '`', '', $table ) . "` WHERE " . implode( ' AND ', $cond ) . ' LIMIT ' . (int) self::BEFORE_IMAGE_CAP;
		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $vals ), ARRAY_A );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Append a write to the capped audit log.
	 *
	 * @param string $op
	 * @param string $table
	 * @param int    $affected
	 * @param array  $before
	 * @return void
	 */
	public static function log( string $op, string $table, int $affected, array $before = array() ): void {
		$log = get_option( self::AUDIT_OPTION, array() );
		if ( ! is_array( $log ) ) {
			$log = array();
		}
		$log[] = array(
			'op'       => $op,
			'table'    => $table,
			'affected' => $affected,
			'before'   => $before,
			'user'     => get_current_user_id(),
			'time'     => time(),
		);
		if ( count( $log ) > self::AUDIT_MAX ) {
			$log = array_slice( $log, -self::AUDIT_MAX );
		}
		update_option( self::AUDIT_OPTION, $log, false );
	}
}

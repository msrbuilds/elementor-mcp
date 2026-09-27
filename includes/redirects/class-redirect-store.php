<?php
/**
 * Redirect store — the {prefix}emcp_redirects table plus CRUD, path
 * normalization, loop guarding, target resolution, and the ledger-rollback
 * applier for the `redirect-row` change type.
 *
 * Follows the EMCP_Tools_Search_Index storage pattern (version-gated dbDelta,
 * a DB_VERSION const + option, maybe_install() on init). The pure methods
 * (normalize_path/would_loop/resolve_target and create()'s validation) are
 * DB-free so they unit-test without a database; persistence is exercised by the
 * live smoke test.
 *
 * @package EMCP_Tools
 * @since   3.11.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect store.
 *
 * @since 3.11.0
 */
class EMCP_Tools_Redirect_Store {

	const DB_VERSION         = 2;
	const DB_VERSION_OPTION  = 'emcp_tools_redirects_db_version';
	const UPGRADE_LOG_OPTION = 'emcp_tools_redirects_upgrade_log';
	const MAX_SOURCE_LEN     = 191;

	/**
	 * The redirects table name.
	 *
	 * @return string
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'emcp_redirects';
	}

	/**
	 * Register the install hook.
	 */
	public static function init(): void {
		add_action( 'init', array( __CLASS__, 'maybe_install' ), 20 );
	}

	/**
	 * Create/upgrade the table when the stored version is behind.
	 */
	public static function maybe_install(): void {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) >= self::DB_VERSION ) {
			return;
		}
		global $wpdb;
		$table  = self::table();
		$exists = $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
		if ( ! $exists ) {
			if ( ! self::create_table() ) {
				return;
			}
		} elseif ( ! self::upgrade() ) {
			return; // Stay on version 1; the handler keeps path-only matching.
		}
		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );
	}

	/**
	 * Create the schema 2 table (fresh installs).
	 *
	 * @return bool Whether dbDelta ran.
	 */
	private static function create_table(): bool {
		if ( ! function_exists( 'dbDelta' ) ) {
			$upgrade = ABSPATH . 'wp-admin/includes/upgrade.php';
			if ( is_readable( $upgrade ) ) {
				require_once $upgrade;
			}
		}
		if ( function_exists( 'dbDelta' ) ) {
			global $wpdb;
			$table   = self::table();
			$charset = method_exists( $wpdb, 'get_charset_collate' ) ? $wpdb->get_charset_collate() : '';
			$sql     = "CREATE TABLE {$table} (
				id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
				source_path VARCHAR(191) NOT NULL,
				source_query VARCHAR(191) NOT NULL DEFAULT '',
				source_key CHAR(40) NOT NULL DEFAULT '',
				target TEXT NOT NULL,
				target_post_id BIGINT UNSIGNED NULL,
				status_code SMALLINT NOT NULL DEFAULT 301,
				match_type VARCHAR(20) NOT NULL DEFAULT 'exact',
				ignore_query TINYINT(1) NOT NULL DEFAULT 1,
				enabled TINYINT(1) NOT NULL DEFAULT 1,
				hits BIGINT UNSIGNED NOT NULL DEFAULT 0,
				last_hit DATETIME NULL,
				notes TEXT NULL,
				created_at DATETIME NOT NULL,
				updated_at DATETIME NOT NULL,
				PRIMARY KEY (id),
				UNIQUE KEY source_key (source_key),
				KEY source_path (source_path),
				KEY enabled_idx (enabled)
			) {$charset};";
			dbDelta( $sql );
			return true;
		}
		return false;
	}

	/**
	 * Steps still needed to reach schema 2 from a table state (pure). The two
	 * backfills are idempotent and always run.
	 *
	 * @param array $state { has_query_col, has_key_col, has_old_unique, has_key_unique }.
	 * @return string[]
	 */
	public static function upgrade_plan( array $state ): array {
		$steps = array();
		if ( empty( $state['has_query_col'] ) ) {
			$steps[] = 'add_query_col';
		}
		if ( empty( $state['has_key_col'] ) ) {
			$steps[] = 'add_key_col';
		}
		$steps[] = 'backfill_keys';
		$steps[] = 'path_rules';
		if ( ! empty( $state['has_old_unique'] ) ) {
			$steps[] = 'drop_old_unique';
		}
		if ( empty( $state['has_key_unique'] ) ) {
			$steps[] = 'add_key_unique';
		}
		return $steps;
	}

	/**
	 * Schema 1 to 2 (spec 9.8), resumable: each step checks its own target
	 * state, so a run cut short picks up where it stopped. dbDelta cannot drop
	 * an index or swap a unique key, hence the guarded ALTERs.
	 *
	 * @return bool Whether the table is now on schema 2.
	 */
	private static function upgrade(): bool {
		global $wpdb;
		$t     = self::table();
		$cols  = (array) $wpdb->get_col( "SHOW COLUMNS FROM {$t}" ); // phpcs:ignore WordPress.DB
		$keys  = (array) $wpdb->get_col( "SHOW INDEX FROM {$t}", 2 ); // phpcs:ignore WordPress.DB -- column 2 is Key_name.
		$state = array(
			'has_query_col'  => in_array( 'source_query', $cols, true ),
			'has_key_col'    => in_array( 'source_key', $cols, true ),
			'has_old_unique' => in_array( 'source_unique', $keys, true ),
			'has_key_unique' => in_array( 'source_key', $keys, true ),
		);
		$changed = 0;
		foreach ( self::upgrade_plan( $state ) as $step ) {
			switch ( $step ) {
				case 'add_query_col':
					$ok = false !== $wpdb->query( "ALTER TABLE {$t} ADD COLUMN source_query VARCHAR(191) NOT NULL DEFAULT '' AFTER source_path" ); // phpcs:ignore WordPress.DB
					break;
				case 'add_key_col':
					$ok = false !== $wpdb->query( "ALTER TABLE {$t} ADD COLUMN source_key CHAR(40) NOT NULL DEFAULT '' AFTER source_query" ); // phpcs:ignore WordPress.DB
					break;
				case 'backfill_keys':
					$ok = false !== $wpdb->query( "UPDATE {$t} SET source_key = SHA1( CONCAT( source_path, '?', source_query ) ) WHERE source_key <> SHA1( CONCAT( source_path, '?', source_query ) )" ); // phpcs:ignore WordPress.DB
					break;
				case 'path_rules':
					$n       = $wpdb->query( "UPDATE {$t} SET ignore_query = 1 WHERE source_query = '' AND ignore_query <> 1" ); // phpcs:ignore WordPress.DB
					$ok      = false !== $n;
					$changed = (int) $n;
					break;
				case 'drop_old_unique':
					$ok = false !== $wpdb->query( "ALTER TABLE {$t} DROP INDEX source_unique, ADD INDEX source_path (source_path)" ); // phpcs:ignore WordPress.DB
					break;
				default: // add_key_unique.
					$ok = false !== $wpdb->query( "ALTER TABLE {$t} ADD UNIQUE KEY source_key (source_key)" ); // phpcs:ignore WordPress.DB
			}
			if ( ! $ok ) {
				return false;
			}
		}
		update_option(
			self::UPGRADE_LOG_OPTION,
			array(
				'ts'         => time(),
				'path_rules' => $changed,
			),
			false
		);
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( 'EMCP Tools: redirects upgraded to schema 2; %d rule(s) set to match regardless of query string, as they already behaved.', $changed ) );
		}
		return true;
	}

	// ---------------------------------------------------------------------
	// Pure helpers (DB-free)
	// ---------------------------------------------------------------------

	/**
	 * Normalize a URL or path to a comparable, home-relative source path:
	 * scheme+host stripped, home-path prefix removed (subdirectory installs),
	 * decoded, lowercased, duplicate slashes collapsed, single leading slash,
	 * trailing slash + query dropped. Root stays '/'.
	 *
	 * @param string $url_or_path A full URL or a path.
	 * @return string
	 */
	public static function normalize_path( string $url_or_path ): string {
		$s = trim( $url_or_path );
		if ( '' === $s ) {
			return '/';
		}
		$parts = function_exists( 'wp_parse_url' ) ? wp_parse_url( $s ) : parse_url( $s ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
		$path  = ( is_array( $parts ) && isset( $parts['path'] ) ) ? (string) $parts['path'] : $s;
		if ( function_exists( 'home_url' ) ) {
			$home = parse_url( home_url( '/' ), PHP_URL_PATH ); // phpcs:ignore WordPress.WP.AlternativeFunctions.parse_url_parse_url
			if ( $home && '/' !== $home && 0 === strpos( $path, $home ) ) {
				$path = substr( $path, strlen( rtrim( $home, '/' ) ) );
			}
		}
		$path = rawurldecode( $path );
		$path = strtolower( $path );
		$path = preg_replace( '#/+#', '/', $path );
		$path = '/' . ltrim( (string) $path, '/' );
		$path = rtrim( $path, '/' );
		return '' === $path ? '/' : $path;
	}

	/**
	 * Normalized query of a URL, a path with a query, or a bare query string
	 * (spec 9.8): parameters sorted by key then value, empty values and bare
	 * keys kept, re-encoded with rawurlencode, case kept, fragment dropped.
	 *
	 * @param string $url_or_query URL, path with query, or query string.
	 * @return string
	 */
	public static function normalize_query( string $url_or_query ): string {
		$s   = trim( $url_or_query );
		$pos = strpos( $s, '?' );
		if ( false !== $pos ) {
			$q = substr( $s, $pos + 1 );
		} elseif ( '' !== $s && '/' !== $s[0] && false === strpos( $s, '://' ) && ( false !== strpos( $s, '=' ) || false !== strpos( $s, '&' ) ) ) {
			$q = $s;
		} else {
			$q = '';
		}
		$q = explode( '#', $q, 2 )[0];
		if ( '' === $q ) {
			return '';
		}
		$pairs = array();
		foreach ( explode( '&', $q ) as $part ) {
			if ( '' === $part ) {
				continue;
			}
			$kv      = explode( '=', $part, 2 );
			$pairs[] = array(
				rawurlencode( urldecode( $kv[0] ) ),
				array_key_exists( 1, $kv ) ? rawurlencode( urldecode( $kv[1] ) ) : null,
			);
		}
		usort(
			$pairs,
			static function ( $a, $b ) {
				return array( $a[0], (string) $a[1] ) <=> array( $b[0], (string) $b[1] );
			}
		);
		return implode(
			'&',
			array_map(
				static function ( $p ) {
					return null === $p[1] ? $p[0] : $p[0] . '=' . $p[1];
				},
				$pairs
			)
		);
	}

	/**
	 * Path and normalized query of a From value.
	 *
	 * @param string $source URL or path, optionally with a query.
	 * @return array{path: string, query: string}
	 */
	public static function split_source( string $source ): array {
		return array(
			'path'  => self::normalize_path( $source ),
			'query' => self::normalize_query( $source ),
		);
	}

	/**
	 * Unique key of a rule: sha1( path . '?' . query ).
	 *
	 * @param string $path  Normalized path.
	 * @param string $query Normalized query ('' for a path rule).
	 * @return string
	 */
	public static function key_for( string $path, string $query ): string {
		return sha1( $path . '?' . $query );
	}

	/**
	 * True when the source and target normalize to the same path (a self-loop).
	 *
	 * @param string $source Source path.
	 * @param string $target Target URL/path.
	 * @return bool
	 */
	public static function would_loop( string $source, string $target ): bool {
		return self::normalize_path( $source ) === self::normalize_path( $target );
	}

	/**
	 * True when a rule would send a matching request to itself: a path rule
	 * when the target has the same path; a query rule only when the target has
	 * the same path and the same query.
	 *
	 * @param string $source Normalized source path.
	 * @param string $query  Normalized rule query ('' for a path rule).
	 * @param string $target Target URL/path.
	 * @return bool
	 */
	public static function rule_loops( string $source, string $query, string $target ): bool {
		if ( ! self::would_loop( $source, $target ) ) {
			return false;
		}
		return '' === $query || self::normalize_query( $target ) === $query;
	}

	/**
	 * The rule that answers a request (spec 9.8): an enabled query rule for the
	 * exact path and query first, then an enabled path rule.
	 *
	 * @param array|null $query_row Row keyed on path and query.
	 * @param array|null $path_row  Row keyed on the path alone.
	 * @return array|null
	 */
	public static function pick( ?array $query_row, ?array $path_row ): ?array {
		if ( $query_row && ! empty( $query_row['enabled'] ) && 0 === (int) $query_row['ignore_query'] ) {
			return $query_row;
		}
		if ( $path_row && ! empty( $path_row['enabled'] ) && 1 === (int) $path_row['ignore_query'] ) {
			return $path_row;
		}
		return null;
	}

	/**
	 * Where a matched rule sends the visitor: a path rule forwards the
	 * request's query to a query-less target; a query rule forwards nothing.
	 * A target with its own query is used as written.
	 *
	 * @param array  $row           Matched rule.
	 * @param string $target        Resolved target URL.
	 * @param string $request_query Raw request query string.
	 * @return string
	 */
	public static function target_for( array $row, string $target, string $request_query ): string {
		if ( 1 === (int) $row['ignore_query'] && '' !== $request_query && false === strpos( $target, '?' ) ) {
			return $target . '?' . $request_query;
		}
		return $target;
	}

	/**
	 * Warn when a source path resolves to an existing published post (so the
	 * redirect would take over a live page).
	 *
	 * @param string $source_path Normalized source path.
	 * @return string Warning text ('' when no shadow).
	 */
	public static function shadow_warning( string $source_path ): string {
		if ( ! function_exists( 'url_to_postid' ) || ! function_exists( 'home_url' ) ) {
			return '';
		}
		$post_id = url_to_postid( home_url( $source_path ) );
		if ( $post_id && 'publish' === get_post_status( $post_id ) ) {
			return sprintf(
				/* translators: %d: post ID. */
				__( 'Heads up: this source path currently resolves to live published post #%d. The redirect will now take over that URL.', 'emcp-tools' ),
				(int) $post_id
			);
		}
		return '';
	}

	/**
	 * Resolve a row's effective target URL. A target_post_id resolves to the
	 * current permalink (so it survives the target's own slug changes); an empty
	 * result (post gone) means the redirect is inactive.
	 *
	 * @param array $row Redirect row.
	 * @return string
	 */
	public static function resolve_target( array $row ): string {
		$post_id = (int) ( $row['target_post_id'] ?? 0 );
		if ( $post_id > 0 ) {
			$link = function_exists( 'get_permalink' ) ? get_permalink( $post_id ) : '';
			return is_string( $link ) ? $link : '';
		}
		return (string) ( $row['target'] ?? '' );
	}

	// ---------------------------------------------------------------------
	// CRUD
	// ---------------------------------------------------------------------

	/**
	 * Create a redirect. Returns the created row or a WP_Error.
	 *
	 * @param array $data { source, target|target_post_id, status_code, ignore_query, enabled, notes }.
	 * @return array|WP_Error
	 */
	public static function create( array $data ) {
		$split        = self::split_source( (string) ( $data['source'] ?? '' ) );
		$source       = $split['path'];
		$ignore_query = ! array_key_exists( 'ignore_query', $data ) || ! empty( $data['ignore_query'] );
		$query        = $ignore_query ? '' : $split['query'];
		if ( ! $ignore_query && '' === $query ) {
			return self::query_required();
		}
		if ( strlen( $query ) > self::MAX_SOURCE_LEN ) {
			return new \WP_Error( 'source_too_long', __( 'The query string exceeds 191 characters.', 'emcp-tools' ) );
		}
		if ( '/' === $source || '' === $source ) {
			return new \WP_Error( 'invalid_source', __( 'A non-empty source path is required.', 'emcp-tools' ) );
		}
		if ( strlen( $source ) > self::MAX_SOURCE_LEN ) {
			return new \WP_Error( 'source_too_long', __( 'Source path exceeds 191 characters.', 'emcp-tools' ) );
		}
		$post_id = isset( $data['target_post_id'] ) ? absint( $data['target_post_id'] ) : 0;
		$target  = isset( $data['target'] ) ? trim( (string) $data['target'] ) : '';
		if ( $post_id && '' !== $target ) {
			return new \WP_Error( 'ambiguous_target', __( 'Provide either target or target_post_id, not both.', 'emcp-tools' ) );
		}
		if ( ! $post_id && '' === $target ) {
			return new \WP_Error( 'missing_target', __( 'A target URL or target_post_id is required.', 'emcp-tools' ) );
		}
		if ( '' !== $target && self::rule_loops( $source, $query, $target ) ) {
			return new \WP_Error( 'redirect_loop', __( 'A redirect cannot point to itself.', 'emcp-tools' ) );
		}
		if ( self::find_by_key( $source, $query ) ) {
			return new \WP_Error( 'duplicate_source', __( 'A redirect for this source already exists.', 'emcp-tools' ) );
		}
		$now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'source_path'    => $source,
				'source_query'   => $query,
				'source_key'     => self::key_for( $source, $query ),
				'target'         => $post_id ? '' : $target,
				'target_post_id' => $post_id ? $post_id : null,
				'status_code'    => self::clamp_code( $data['status_code'] ?? 301 ),
				'match_type'     => 'exact',
				'ignore_query'   => $ignore_query ? 1 : 0,
				'enabled'        => ( isset( $data['enabled'] ) && ! $data['enabled'] ) ? 0 : 1,
				'hits'           => 0,
				'notes'          => isset( $data['notes'] ) ? (string) $data['notes'] : null,
				'created_at'     => $now,
				'updated_at'     => $now,
			),
			array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s' )
		);
		return self::get( (int) $wpdb->insert_id );
	}

	/**
	 * Update a redirect by id. Only supplied keys change. Returns the fresh row
	 * or a WP_Error.
	 *
	 * @param int   $id   Redirect id.
	 * @param array $data Partial fields.
	 * @return array|WP_Error
	 */
	public static function update( int $id, array $data ) {
		$row = self::get( $id );
		if ( ! $row ) {
			return new \WP_Error( 'not_found', __( 'Redirect not found.', 'emcp-tools' ) );
		}
		$set     = array();
		$formats = array();
		if ( array_key_exists( 'source', $data ) || array_key_exists( 'ignore_query', $data ) ) {
			$split        = array_key_exists( 'source', $data )
				? self::split_source( (string) $data['source'] )
				: array(
					'path'  => (string) $row['source_path'],
					'query' => (string) ( $row['source_query'] ?? '' ),
				);
			$ignore_query = array_key_exists( 'ignore_query', $data ) ? ! empty( $data['ignore_query'] ) : 1 === (int) $row['ignore_query'];
			$source       = $split['path'];
			$query        = $ignore_query ? '' : $split['query'];
			if ( '/' === $source || strlen( $source ) > self::MAX_SOURCE_LEN || strlen( $query ) > self::MAX_SOURCE_LEN ) {
				return new \WP_Error( 'invalid_source', __( 'Invalid source path.', 'emcp-tools' ) );
			}
			if ( ! $ignore_query && '' === $query ) {
				return self::query_required();
			}
			$dupe = self::find_by_key( $source, $query );
			if ( $dupe && (int) $dupe['id'] !== $id ) {
				return new \WP_Error( 'duplicate_source', __( 'Another redirect already uses this source.', 'emcp-tools' ) );
			}
			$set['source_path']  = $source;
			$set['source_query'] = $query;
			$set['source_key']   = self::key_for( $source, $query );
			$set['ignore_query'] = $ignore_query ? 1 : 0;
			array_push( $formats, '%s', '%s', '%s', '%d' );
		}
		if ( array_key_exists( 'target', $data ) ) {
			$set['target']         = trim( (string) $data['target'] );
			$set['target_post_id'] = null;
			$formats[]             = '%s';
			$formats[]             = '%d';
		} elseif ( array_key_exists( 'target_post_id', $data ) ) {
			$set['target_post_id'] = absint( $data['target_post_id'] );
			$set['target']         = '';
			$formats[]             = '%d';
			$formats[]             = '%s';
		}
		if ( array_key_exists( 'status_code', $data ) ) {
			$set['status_code'] = self::clamp_code( $data['status_code'] );
			$formats[]          = '%d';
		}
		if ( array_key_exists( 'enabled', $data ) ) {
			$set['enabled'] = empty( $data['enabled'] ) ? 0 : 1;
			$formats[]      = '%d';
		}
		if ( array_key_exists( 'notes', $data ) ) {
			$set['notes'] = (string) $data['notes'];
			$formats[]    = '%s';
		}
		$effective_source = $set['source_path'] ?? $row['source_path'];
		$effective_query  = $set['source_query'] ?? (string) ( $row['source_query'] ?? '' );
		$effective_target = array_key_exists( 'target', $set ) ? $set['target'] : $row['target'];
		if ( '' !== (string) $effective_target && self::rule_loops( (string) $effective_source, $effective_query, (string) $effective_target ) ) {
			return new \WP_Error( 'redirect_loop', __( 'A redirect cannot point to itself.', 'emcp-tools' ) );
		}
		if ( empty( $set ) ) {
			return $row;
		}
		$set['updated_at'] = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		$formats[]         = '%s';
		global $wpdb;
		$wpdb->update( self::table(), $set, array( 'id' => $id ), $formats, array( '%d' ) );
		return self::get( $id );
	}

	/**
	 * Delete a redirect by id.
	 *
	 * @param int $id Redirect id.
	 * @return bool
	 */
	public static function delete( int $id ): bool {
		global $wpdb;
		return (bool) $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
	}

	/**
	 * Get one row by id.
	 *
	 * @param int $id Redirect id.
	 * @return array|null
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $row ? self::cast( $row ) : null;
	}

	/**
	 * Get one row by normalized source path (the hot-path lookup).
	 *
	 * @param string $path Already-normalized source path.
	 * @return array|null
	 */
	public static function find_by_source( string $path ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE source_path = %s LIMIT 1', $path ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $row ? self::cast( $row ) : null;
	}

	/**
	 * Get one rule by path and normalized query ('' for the path rule).
	 *
	 * @param string $path  Normalized path.
	 * @param string $query Normalized query.
	 * @return array|null
	 */
	public static function find_by_key( string $path, string $query ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE source_key = %s LIMIT 1', self::key_for( $path, $query ) ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $row ? self::cast( $row ) : null;
	}

	/**
	 * The rule that answers a request for a normalized path and query. On a
	 * table still on schema 1 (an upgrade that failed) only path rules exist.
	 *
	 * @param string $path  Normalized request path.
	 * @param string $query Normalized request query.
	 * @return array|null
	 */
	public static function find_for_request( string $path, string $query ): ?array {
		if ( (int) get_option( self::DB_VERSION_OPTION, 0 ) < 2 ) {
			$row = self::find_by_source( $path );
			return $row && ! empty( $row['enabled'] ) ? $row : null;
		}
		$query_row = '' !== $query ? self::find_by_key( $path, $query ) : null;
		return self::pick( $query_row, self::find_by_key( $path, '' ) );
	}

	/**
	 * List rows with optional filters.
	 *
	 * @param array $filters { enabled:bool, search:string, limit:int, offset:int }.
	 * @return array
	 */
	public static function all( array $filters = array() ): array {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( array_key_exists( 'enabled', $filters ) && null !== $filters['enabled'] ) {
			$where[]  = 'enabled = %d';
			$params[] = $filters['enabled'] ? 1 : 0;
		}
		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $filters['search'] ) . '%';
			$where[]  = '(source_path LIKE %s OR source_query LIKE %s OR target LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$limit  = max( 1, min( 500, (int) ( $filters['limit'] ?? 100 ) ) );
		$offset = max( 0, (int) ( $filters['offset'] ?? 0 ) );
		$sql    = 'SELECT * FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where ) . ' ORDER BY updated_at DESC LIMIT %d OFFSET %d';
		$params[] = $limit;
		$params[] = $offset;
		$rows   = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return is_array( $rows ) ? array_map( array( __CLASS__, 'cast' ), $rows ) : array();
	}

	/**
	 * Count rows matching the same filters as all() (minus pagination).
	 *
	 * @param array $filters { enabled:bool, search:string }.
	 * @return int
	 */
	public static function count( array $filters = array() ): int {
		global $wpdb;
		$where  = array( '1=1' );
		$params = array();
		if ( array_key_exists( 'enabled', $filters ) && null !== $filters['enabled'] ) {
			$where[]  = 'enabled = %d';
			$params[] = $filters['enabled'] ? 1 : 0;
		}
		if ( ! empty( $filters['search'] ) ) {
			$like     = '%' . $wpdb->esc_like( (string) $filters['search'] ) . '%';
			$where[]  = '(source_path LIKE %s OR source_query LIKE %s OR target LIKE %s)';
			$params[] = $like;
			$params[] = $like;
			$params[] = $like;
		}
		$sql = 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE ' . implode( ' AND ', $where );
		if ( $params ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Bump the hit counter + last-hit timestamp for a matched redirect.
	 *
	 * @param int $id Redirect id.
	 */
	public static function record_hit( int $id ): void {
		global $wpdb;
		$now = function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' );
		$wpdb->query( $wpdb->prepare( 'UPDATE ' . self::table() . ' SET hits = hits + 1, last_hit = %s WHERE id = %d', $now, $id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery
	}

	// ---------------------------------------------------------------------
	// Ledger rollback applier (redirect-row type)
	// ---------------------------------------------------------------------

	/**
	 * Apply a `redirect-row` rollback. Shapes:
	 *  - create → before:{ id }        → delete the row.
	 *  - update → before:{ row:{...} } → restore the prior row values.
	 *  - delete → before:{ row:{...} } → re-insert the prior row (id preserved).
	 *
	 * @param array $rb Rollback ref.
	 * @return bool
	 */
	public static function rollback( array $rb ): bool {
		$action = (string) ( $rb['action'] ?? '' );
		$before = isset( $rb['before'] ) && is_array( $rb['before'] ) ? $rb['before'] : array();
		if ( 'create' === $action ) {
			$id = (int) ( $before['id'] ?? 0 );
			return $id > 0 ? self::delete( $id ) : false;
		}
		$row = isset( $before['row'] ) && is_array( $before['row'] ) ? $before['row'] : array();
		if ( empty( $row['id'] ) ) {
			return false;
		}
		global $wpdb;
		if ( 'delete' === $action ) {
			return (bool) $wpdb->insert( self::table(), self::row_for_write( $row ), self::write_formats() );
		}
		if ( 'update' === $action ) {
			$write = self::row_for_write( $row );
			unset( $write['id'] );
			return (bool) $wpdb->update( self::table(), $write, array( 'id' => (int) $row['id'] ), self::write_formats_no_id(), array( '%d' ) );
		}
		return false;
	}

	// ---------------------------------------------------------------------
	// Internals
	// ---------------------------------------------------------------------

	/**
	 * The error for a query rule whose source has no query.
	 *
	 * @return WP_Error
	 */
	private static function query_required(): \WP_Error {
		return new \WP_Error( 'query_required', __( 'To match one query string only, include it in the source, for example /page?ref=ad.', 'emcp-tools' ) );
	}

	/**
	 * Clamp a status code to the supported 301/302 set (default 301).
	 *
	 * @param mixed $code Raw code.
	 * @return int
	 */
	private static function clamp_code( $code ): int {
		return in_array( (int) $code, array( 301, 302 ), true ) ? (int) $code : 301;
	}

	/**
	 * Cast a raw DB row to typed values.
	 *
	 * @param array $row Raw row.
	 * @return array
	 */
	private static function cast( array $row ): array {
		$row['id']             = (int) ( $row['id'] ?? 0 );
		$row['target_post_id'] = isset( $row['target_post_id'] ) ? (int) $row['target_post_id'] : 0;
		$row['status_code']    = (int) ( $row['status_code'] ?? 301 );
		$row['ignore_query']   = (int) ( $row['ignore_query'] ?? 1 );
		$row['source_query']   = (string) ( $row['source_query'] ?? '' );
		$row['enabled']        = (int) ( $row['enabled'] ?? 1 );
		$row['hits']           = (int) ( $row['hits'] ?? 0 );
		return $row;
	}

	/**
	 * The writable column subset of a stored row (for restore/re-insert).
	 *
	 * @param array $row Prior row.
	 * @return array
	 */
	public static function row_for_write( array $row ): array {
		return array(
			'id'             => (int) $row['id'],
			'source_path'    => (string) ( $row['source_path'] ?? '' ),
			'source_query'   => (string) ( $row['source_query'] ?? '' ),
			'source_key'     => self::key_for( (string) ( $row['source_path'] ?? '' ), (string) ( $row['source_query'] ?? '' ) ),
			'target'         => (string) ( $row['target'] ?? '' ),
			'target_post_id' => ! empty( $row['target_post_id'] ) ? (int) $row['target_post_id'] : null,
			'status_code'    => self::clamp_code( $row['status_code'] ?? 301 ),
			'match_type'     => (string) ( $row['match_type'] ?? 'exact' ),
			'ignore_query'   => (int) ( $row['ignore_query'] ?? 1 ),
			'enabled'        => (int) ( $row['enabled'] ?? 1 ),
			'hits'           => (int) ( $row['hits'] ?? 0 ),
			'last_hit'       => isset( $row['last_hit'] ) ? $row['last_hit'] : null,
			'notes'          => isset( $row['notes'] ) ? (string) $row['notes'] : null,
			'created_at'     => (string) ( $row['created_at'] ?? ( function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ) ) ),
			'updated_at'     => (string) ( $row['updated_at'] ?? ( function_exists( 'current_time' ) ? current_time( 'mysql' ) : gmdate( 'Y-m-d H:i:s' ) ) ),
		);
	}

	/**
	 * Column formats for a full row write (id first), matching row_for_write().
	 *
	 * @return string[]
	 */
	public static function write_formats(): array {
		return array( '%d', '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' );
	}

	/**
	 * Column formats for an update (id excluded from the SET list).
	 *
	 * @return string[]
	 */
	private static function write_formats_no_id(): array {
		return array( '%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s' );
	}
}

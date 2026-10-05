<?php
/**
 * The change ledger's database and option I/O on $wpdb (spec 9.1). Every read
 * is uncached: a long-running WP-CLI MCP process would otherwise act on
 * values it cached hours ago.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQL.NotPrepared -- table names come from EMCP_Tools_Change_Names; values are prepared.

/**
 * $wpdb change storage.
 */
final class EMCP_Tools_Change_WPDB_Storage implements EMCP_Tools_Change_Storage {

	const DB_VERSION = 1;

	/** Column order for inserts. */
	const COLUMNS = array( 'id', 'ts', 'ts_us', 'domain', 'action', 'target', 'summary', 'rollback', 'user_id', 'user_login', 'client', 'session', 'rolled_back', 'rolled_back_at' );

	/** Placeholder per column (NULL-able columns are written literally when null). */
	const FORMATS = array( '%s', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d' );

	/** @var bool Whether this connection holds the named lock. */
	private $locked = false;

	private function db() {
		global $wpdb;
		return $wpdb;
	}

	private function table(): string {
		return EMCP_Tools_Change_Names::table();
	}

	private function read_option( string $name ): ?string {
		$db = $this->db();
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'notoptions', 'options' );
		$value = $db->get_var( $db->prepare( "SELECT option_value FROM {$db->options} WHERE option_name = %s LIMIT 1", $name ) );
		return null === $value ? null : (string) $value;
	}

	public function flag(): string {
		return 'table' === $this->read_option( EMCP_Tools_Change_Names::flag() ) ? 'table' : 'option';
	}

	public function set_flag( string $v ): void {
		update_option( EMCP_Tools_Change_Names::flag(), 'table' === $v ? 'table' : 'option', false );
	}

	public function get_meta( string $option ) {
		$raw = $this->read_option( $option );
		return null === $raw ? null : maybe_unserialize( $raw );
	}

	public function set_meta( string $option, $value ): void {
		update_option( $option, $value, false );
	}

	public function delete_meta( string $option ): void {
		delete_option( $option );
	}

	public function lock( int $timeout ): ?bool {
		$db = $this->db();
		$r  = $db->get_var( $db->prepare( 'SELECT GET_LOCK( %s, %d )', EMCP_Tools_Change_Names::lock(), $timeout ) );
		if ( null === $r ) {
			return null;
		}
		$this->locked = '1' === (string) $r;
		return $this->locked;
	}

	public function unlock(): void {
		if ( $this->locked ) {
			$db = $this->db();
			$db->get_var( $db->prepare( 'SELECT RELEASE_LOCK( %s )', EMCP_Tools_Change_Names::lock() ) );
			$this->locked = false;
		}
	}

	public function option_raw(): ?string {
		return $this->read_option( EMCP_Tools_Change_Names::option() );
	}

	public function option_rows( ?string $raw ): array {
		$v = null === $raw ? array() : maybe_unserialize( $raw );
		return is_array( $v ) ? array_values( $v ) : array();
	}

	public function option_write( array $rows ): bool {
		$name = EMCP_Tools_Change_Names::option();
		if ( null === $this->read_option( $name ) ) {
			return add_option( $name, array_values( $rows ), '', false );
		}
		$ok = update_option( $name, array_values( $rows ), false );
		// update_option() returns false for an identical value; that is not a failure.
		return $ok || $this->read_option( $name ) === maybe_serialize( array_values( $rows ) );
	}

	public function option_cas( ?string $old_raw, array $rows ): bool {
		$name = EMCP_Tools_Change_Names::option();
		if ( null === $old_raw ) {
			return add_option( $name, array_values( $rows ), '', false );
		}
		$new = maybe_serialize( array_values( $rows ) );
		if ( $new === $old_raw ) {
			// MySQL counts changed rows: an identical write reports 0.
			return $this->read_option( $name ) === $old_raw;
		}
		$db = $this->db();
		$db->query( $db->prepare( "UPDATE {$db->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", $new, $name, $old_raw ) );
		$ok = 1 === (int) $db->rows_affected;
		wp_cache_delete( $name, 'options' );
		wp_cache_delete( 'alloptions', 'options' );
		return $ok;
	}

	public function option_remove(): void {
		delete_option( EMCP_Tools_Change_Names::option() );
	}

	public function table_exists(): bool {
		$db    = $this->db();
		$table = $this->table();
		return $table === $db->get_var( $db->prepare( 'SHOW TABLES LIKE %s', $db->esc_like( $table ) ) );
	}

	public function table_create(): bool {
		if ( ! function_exists( 'dbDelta' ) && is_readable( ABSPATH . 'wp-admin/includes/upgrade.php' ) ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		}
		if ( ! function_exists( 'dbDelta' ) ) {
			return false;
		}
		$db      = $this->db();
		$table   = $this->table();
		$charset = $db->get_charset_collate();
		$sql     = "CREATE TABLE {$table} (
			seq bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			id varchar(32) NOT NULL,
			ts int(10) unsigned NOT NULL DEFAULT 0,
			ts_us bigint(20) unsigned NOT NULL DEFAULT 0,
			domain varchar(64) NOT NULL DEFAULT '',
			action varchar(64) NOT NULL DEFAULT '',
			target varchar(191) NOT NULL DEFAULT '',
			summary text NULL,
			rollback longtext NULL,
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			user_login varchar(60) NOT NULL DEFAULT '',
			client varchar(100) NOT NULL DEFAULT '',
			session varchar(100) NOT NULL DEFAULT '',
			rolled_back tinyint(1) NOT NULL DEFAULT 0,
			rolled_back_at int(10) unsigned NULL,
			PRIMARY KEY  (seq),
			UNIQUE KEY id (id),
			KEY ts (ts),
			KEY target (target),
			KEY client (client),
			KEY session_ts (session,ts)
		) {$charset};";
		$suppress = $db->suppress_errors( true );
		dbDelta( $sql );
		$db->suppress_errors( $suppress );
		if ( ! $this->table_exists() ) {
			return false;
		}
		update_option( EMCP_Tools_Change_Names::db_version(), self::DB_VERSION, false );
		return true;
	}

	public function table_truncate(): bool {
		return false !== $this->db()->query( 'TRUNCATE TABLE ' . $this->table() );
	}

	/**
	 * One VALUES tuple and its values; NULL columns are written literally,
	 * because $wpdb->prepare() would turn null into ''.
	 *
	 * @param array $row Row.
	 * @return array{0:string,1:array}
	 */
	private function tuple( array $row ): array {
		$c      = EMCP_Tools_Change_Codec::row_to_db( $row );
		$parts  = array();
		$values = array();
		foreach ( self::COLUMNS as $i => $col ) {
			if ( null === $c[ $col ] ) {
				$parts[] = 'NULL';
				continue;
			}
			$parts[]  = self::FORMATS[ $i ];
			$values[] = $c[ $col ];
		}
		return array( '(' . implode( ',', $parts ) . ')', $values );
	}

	public function table_insert_many( array $rows ): bool {
		if ( ! $rows ) {
			return true;
		}
		$db     = $this->db();
		$tuples = array();
		$values = array();
		foreach ( $rows as $row ) {
			list( $t, $v ) = $this->tuple( (array) $row );
			$tuples[]      = $t;
			$values        = array_merge( $values, $v );
		}
		$sql = 'INSERT INTO ' . $this->table() . ' (' . implode( ',', self::COLUMNS ) . ') VALUES ' . implode( ',', $tuples );
		return false !== $db->query( $values ? $db->prepare( $sql, $values ) : $sql );
	}

	public function table_insert( array $row ): ?int {
		$db             = $this->db();
		list( $t, $v )  = $this->tuple( $row );
		$sql            = 'INSERT INTO ' . $this->table() . ' (' . implode( ',', self::COLUMNS ) . ') VALUES ' . $t;
		$ok             = $db->query( $db->prepare( $sql, $v ) );
		return ( false === $ok || ! $db->insert_id ) ? null : (int) $db->insert_id;
	}

	public function table_find( string $id ): ?array {
		$db  = $this->db();
		$row = $db->get_row( $db->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %s', $id ), ARRAY_A );
		return is_array( $row ) ? EMCP_Tools_Change_Codec::row_from_db( $row ) : null;
	}

	public function table_update( string $id, array $fields ): bool {
		$db   = $this->db();
		$sets = array();
		$vals = array();
		if ( array_key_exists( 'rolled_back', $fields ) ) {
			$sets[] = 'rolled_back = %d';
			$vals[] = empty( $fields['rolled_back'] ) ? 0 : 1;
		}
		if ( array_key_exists( 'rolled_back_at', $fields ) ) {
			if ( null === $fields['rolled_back_at'] ) {
				$sets[] = 'rolled_back_at = NULL';
			} else {
				$sets[] = 'rolled_back_at = %d';
				$vals[] = (int) $fields['rolled_back_at'];
			}
		}
		if ( ! $sets ) {
			return false;
		}
		$vals[] = $id;
		return false !== $db->query( $db->prepare( 'UPDATE ' . $this->table() . ' SET ' . implode( ', ', $sets ) . ' WHERE id = %s', $vals ) );
	}

	public function table_delete( string $id ): ?array {
		$row = $this->table_find( $id );
		if ( null === $row ) {
			return null;
		}
		$db = $this->db();
		$n  = $db->query( $db->prepare( 'DELETE FROM ' . $this->table() . ' WHERE id = %s', $id ) );
		return $n ? $row : null;
	}

	public function table_delete_upto( int $seq ): int {
		$db = $this->db();
		return (int) $db->query( $db->prepare( 'DELETE FROM ' . $this->table() . ' WHERE seq <= %d', $seq ) );
	}

	public function table_replace_rollback( string $id, ?array $rollback ): bool {
		$db  = $this->db();
		$enc = EMCP_Tools_Change_Codec::encode_rollback( $rollback );
		if ( null === $enc ) {
			return false !== $db->query( $db->prepare( 'UPDATE ' . $this->table() . ' SET rollback = NULL WHERE id = %s', $id ) );
		}
		return false !== $db->query( $db->prepare( 'UPDATE ' . $this->table() . ' SET rollback = %s WHERE id = %s', $enc, $id ) );
	}

	/**
	 * WHERE clause and values, mirroring EMCP_Tools_Change_Memory_Filter.
	 *
	 * @param array $a Arguments.
	 * @return array{0:string,1:array}
	 */
	private function where( array $a ): array {
		$db    = $this->db();
		$where = array( '1=1' );
		$vals  = array();
		foreach ( array( 'domain', 'client', 'session' ) as $k ) {
			if ( isset( $a[ $k ] ) ) {
				$where[] = "{$k} = %s";
				$vals[]  = (string) $a[ $k ];
			}
		}
		if ( isset( $a['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$vals[]  = (int) $a['user_id'];
		}
		foreach ( array( 'domains' => 'IN', 'domains_not' => 'NOT IN' ) as $k => $op ) {
			if ( isset( $a[ $k ] ) ) {
				$list = array_values( array_map( 'strval', (array) $a[ $k ] ) );
				if ( ! $list ) {
					$where[] = 'IN' === $op ? '1=0' : '1=1';
					continue;
				}
				$where[] = "domain {$op} (" . implode( ',', array_fill( 0, count( $list ), '%s' ) ) . ')';
				$vals    = array_merge( $vals, $list );
			}
		}
		if ( ! empty( $a['no_audit'] ) ) {
			$where[] = "NOT ( action = 'rollback' AND ( rollback IS NULL OR rollback = '' ) )";
		}
		if ( isset( $a['rolled_back'] ) ) {
			$where[] = 'rolled_back = %d';
			$vals[]  = $a['rolled_back'] ? 1 : 0;
		}
		if ( ! empty( $a['search'] ) ) {
			$like    = '%' . $db->esc_like( (string) $a['search'] ) . '%';
			$where[] = '(summary LIKE %s OR target LIKE %s)';
			$vals[]  = $like;
			$vals[]  = $like;
		}
		$ranges = array(
			'since'      => 'ts >= %d',
			'until'      => 'ts <= %d',
			'seq_min'    => 'seq >= %d',
			'seq_max'    => 'seq <= %d',
			'before_seq' => 'seq < %d',
		);
		foreach ( $ranges as $k => $clause ) {
			if ( isset( $a[ $k ] ) ) {
				$where[] = $clause;
				$vals[]  = (int) $a[ $k ];
			}
		}
		return array( implode( ' AND ', $where ), $vals );
	}

	public function table_select( array $args ): array {
		$db                   = $this->db();
		list( $where, $vals ) = $this->where( $args );
		$order                = ( isset( $args['order'] ) && 'asc' === $args['order'] ) ? 'ASC' : 'DESC';
		$limit                = max( 1, min( 1000000, (int) ( $args['limit'] ?? 50 ) ) );
		$sql                  = 'SELECT * FROM ' . $this->table() . " WHERE {$where} ORDER BY seq {$order} LIMIT {$limit}";
		$rows                 = $db->get_results( $vals ? $db->prepare( $sql, $vals ) : $sql, ARRAY_A );
		return array_map( array( 'EMCP_Tools_Change_Codec', 'row_from_db' ), is_array( $rows ) ? $rows : array() );
	}

	public function table_buckets( array $bounds, array $args ): array {
		if ( ! $bounds ) {
			return array();
		}
		$db                   = $this->db();
		list( $where, $vals ) = $this->where( $args );
		$cols                 = array();
		$cvals                = array();
		foreach ( array_values( $bounds ) as $i => $b ) {
			$cols[]  = "SUM( CASE WHEN ts BETWEEN %d AND %d THEN 1 ELSE 0 END ) AS a{$i}";
			$cols[]  = "SUM( CASE WHEN ts BETWEEN %d AND %d AND rolled_back = 1 THEN 1 ELSE 0 END ) AS r{$i}";
			$cvals[] = (int) $b[0];
			$cvals[] = (int) $b[1];
			$cvals[] = (int) $b[0];
			$cvals[] = (int) $b[1];
		}
		$first = reset( $bounds );
		$last  = end( $bounds );
		$sql   = 'SELECT ' . implode( ', ', $cols ) . ' FROM ' . $this->table() . " WHERE {$where} AND ts BETWEEN %d AND %d";
		$row   = $db->get_row( $db->prepare( $sql, array_merge( $cvals, $vals, array( (int) $first[0], (int) $last[1] ) ) ), ARRAY_A );
		$out   = array();
		foreach ( array_keys( array_values( $bounds ) ) as $i ) {
			$out[] = array( (int) ( $row[ 'a' . $i ] ?? 0 ), (int) ( $row[ 'r' . $i ] ?? 0 ) );
		}
		return $out;
	}

	public function table_count( array $args = array() ): int {
		$db                   = $this->db();
		list( $where, $vals ) = $this->where( $args );
		$sql                  = 'SELECT COUNT(*) FROM ' . $this->table() . " WHERE {$where}";
		return (int) $db->get_var( $vals ? $db->prepare( $sql, $vals ) : $sql );
	}

	public function table_clients(): array {
		return array_map( 'strval', (array) $this->db()->get_col( 'SELECT DISTINCT client FROM ' . $this->table() . " WHERE client <> '' ORDER BY client ASC LIMIT 50" ) );
	}

	public function table_ids(): array {
		return array_map( 'strval', (array) $this->db()->get_col( 'SELECT id FROM ' . $this->table() . ' ORDER BY seq ASC' ) );
	}

	public function table_older_than( int $ts, int $limit ): array {
		return $this->table_select( array( 'until' => $ts - 1, 'order' => 'asc', 'limit' => $limit ) );
	}

	public function table_stamped_sessions( array $args ): array {
		$db    = $this->db();
		$where = array( "session <> ''" );
		$vals  = array();
		if ( isset( $args['client'] ) ) {
			$where[] = 'client = %s';
			$vals[]  = (string) $args['client'];
		}
		if ( isset( $args['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$vals[]  = (int) $args['user_id'];
		}
		$having = isset( $args['before_seq'] ) ? ' HAVING last_seq < ' . (int) $args['before_seq'] : '';
		$limit  = max( 1, min( 500, (int) ( $args['limit'] ?? 50 ) ) );
		$sql    = 'SELECT session, client, user_id, MAX(user_login) AS user_login, MIN(seq) AS first_seq, MAX(seq) AS last_seq, MIN(ts) AS first_ts, MAX(ts) AS last_ts, COUNT(*) AS count, SUM(rolled_back = 0) AS open FROM '
			. $this->table() . ' WHERE ' . implode( ' AND ', $where ) . ' GROUP BY session, client, user_id' . $having . " ORDER BY last_seq DESC LIMIT {$limit}";
		$rows   = $db->get_results( $vals ? $db->prepare( $sql, $vals ) : $sql, ARRAY_A );
		$out    = array();
		foreach ( is_array( $rows ) ? $rows : array() as $r ) {
			$out[] = array(
				'session'    => (string) $r['session'],
				'client'     => (string) $r['client'],
				'user_id'    => (int) $r['user_id'],
				'user_login' => (string) $r['user_login'],
				'first_seq'  => (int) $r['first_seq'],
				'last_seq'   => (int) $r['last_seq'],
				'first_ts'   => (int) $r['first_ts'],
				'last_ts'    => (int) $r['last_ts'],
				'count'      => (int) $r['count'],
				'open'       => (int) $r['open'],
			);
		}
		return $out;
	}

	public function sleep( float $seconds ): void {
		usleep( (int) round( $seconds * 1000000 ) );
	}
}
// phpcs:enable

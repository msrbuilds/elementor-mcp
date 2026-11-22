<?php
/** Self-contained recovery engine. Copied outside plugins before any update. No WordPress dependency for restore. */
// Runs inside WordPress (preparation) or as the copy inside a job folder (recovery), never as a direct request to the plugin.
if ( ! defined( 'ABSPATH' ) && 0 !== strpos( basename( __DIR__ ), '.emcp-update-' ) ) { exit; }
class EMCP_Update_Runtime {
	const GUARD = "<?php exit; ?>\n";
	const TERMINAL = array( 'completed', 'rolled_back', 'failed' );
	/** Work per request, so each tick ends well inside common host time limits. */
	public static $tick_bytes = 33554432;
	public static $tick_rows = 5000;
	public static $tick_seconds = 5;
	public static $scan_entries = 2000;
	public static $inspection_seconds = 3;
	const CHUNK_BYTES = 1048576;
	const ROW_BYTES = 8388608;

	/**
	 * The line prepended to wp-config.php for the window. When the job folder is missing
	 * (deleted or quarantined) it still answers with the maintenance page, never a fatal.
	 */
	public static function gate_line( $dir ) {
		$gate = var_export( $dir . '/gate.php', true );
		return '<?php /* EMCP_UPDATE_GATE */ if ( is_file( ' . $gate . ' ) ) { require ' . $gate . '; } else { http_response_code( 503 ); header( \'Retry-After: 120\' ); header( \'Cache-Control: no-store\' ); header( \'Content-Type: text/html; charset=utf-8\' ); exit( \'' . self::MAINTENANCE . '\' ); } ?>';
	}
	const MAINTENANCE = '<!doctype html><html lang="en"><title>Maintenance</title><h1>Scheduled maintenance</h1><p>This website is installing verified updates. Please try again shortly.</p></html>';

	/**
	 * Prefixes of other WordPress installs whose tables also start with ours (wp_shop_ under
	 * wp_). Their tables would be snapshotted and rolled back with this site, so a shared
	 * prefix refuses the job. Verify core columns too: plugins can have their own
	 * options/posts history tables (for example Hostinger AI version snapshots).
	 * Without a schema reader, keep the conservative name-only behavior.
	 */
	public static function other_installs( array $tables, $prefix, ?callable $columns = null ) {
		$set = array_flip( $tables ); $found = array();
		foreach ( $tables as $table ) {
			if ( ! str_starts_with( $table, $prefix ) || ! str_ends_with( $table, 'options' ) ) { continue; }
			$other = substr( $table, 0, -7 );
			if ( strlen( $other ) <= strlen( $prefix ) || ! isset( $set[ $other . 'posts' ] ) ) { continue; }
			if ( $columns ) {
				$options = $columns( $table ); $posts = $columns( $other . 'posts' );
				// Failed or empty metadata must not silently disable cross-site protection.
				if ( ! is_array( $options ) || ! $options || ! is_array( $posts ) || ! $posts ) { throw new RuntimeException( 'database_schema_unavailable' ); }
				$options = array_map( 'strtolower', $options ); $posts = array_map( 'strtolower', $posts );
				if ( array_diff( array( 'option_id', 'option_name', 'option_value', 'autoload' ), $options ) || array_diff( array( 'id', 'post_author', 'post_content', 'post_title', 'post_status', 'post_type' ), $posts ) ) { continue; }
			}
			$found[] = $other;
		}
		sort( $found );
		return array_values( array_unique( $found ) );
	}

	/**
	 * Temporary name for an atomic write. Only a .php target (a guarded journal or
	 * snapshot, or a real PHP file) gets a .php temporary; anything else, such as an
	 * upload that holds PHP code, keeps a .tmp name the web server will not execute.
	 */
	public static function temp_path( $path ) {
		return $path . '.' . bin2hex( random_bytes( 6 ) ) . ( str_ends_with( strtolower( $path ), '.php' ) ? '.php' : '.tmp' );
	}
	public static function atomic( $path, $bytes ) {
		$temp = self::temp_path( $path );
		if ( file_put_contents( $temp, $bytes, LOCK_EX ) !== strlen( $bytes ) ) { @unlink( $temp ); throw new RuntimeException( 'storage_error' ); }
		@chmod( $temp, is_file( $path ) ? ( fileperms( $path ) & 0777 ) : 0600 );
		if ( ! rename( $temp, $path ) ) { @unlink( $temp ); throw new RuntimeException( 'storage_error' ); }
		if ( function_exists( 'opcache_invalidate' ) ) { opcache_invalidate( $path, true ); }
	}
	public static function write( $path, $value ) { self::atomic( $path, self::GUARD . json_encode( $value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES ) ); }
	public static function read( $path ) {
		$bytes = file_get_contents( $path );
		if ( false === $bytes || ! str_starts_with( $bytes, self::GUARD ) ) { throw new RuntimeException( 'invalid_journal' ); }
		return json_decode( substr( $bytes, strlen( self::GUARD ) ), true, 512, JSON_THROW_ON_ERROR );
	}
	/** Database values travel as hex: JSON-safe for binary bytes, and no decode-and-write shape for host malware scanners. */
	public static function encode_row( array $row ): array {
		return array_map( static fn( $v ) => null === $v ? null : bin2hex( (string) $v ), $row );
	}
	public static function decode_row( array $row ): array {
		return array_map( static function ( $v ) { if ( null === $v ) { return null; } $bytes = is_string( $v ) && 0 === strlen( $v ) % 2 && ctype_xdigit( $v . '0' ) ? hex2bin( $v ) : false; if ( false === $bytes ) { throw new RuntimeException( 'snapshot_corrupt' ); } return $bytes; }, $row );
	}
	public static function receipt( $state ) {
		$receipt = array_intersect_key( $state, array_flip( array( 'job', 'phase', 'group', 'code', 'snapshot' ) ) );
		$p = array( 'elapsed_seconds' => max( 0, time() - ( $state['phase_started_at'] ?? time() ) ) );
		if ( 'draining' === $state['phase'] ) { $p['drain_remaining_seconds'] = max( 0, ( $state['drain_until'] ?? time() ) - time() ); }
		if ( in_array( $state['phase'], array( 'snapshot', 'restoring' ), true ) ) {
			$p['stage'] = isset( $state['scan'] ) || ! isset( $state['files'] ) ? 'scanning' : 'files';
			$p['files_scanned'] = count( $state['files'] ?? array() );
			if ( ! isset( $state['scan'] ) && isset( $state['tables'] ) ) {
				$p['files_total'] = count( $state['files'] );
				$p['files_done'] = min( $p['files_total'], (int) ( 'restoring' === $state['phase'] ? ( $state['restore_step'] ?? 0 ) : ( $state['file_index'] ?? 0 ) ) );
				$p['tables_total'] = count( $state['tables'] );
				$p['tables_done'] = min( $p['tables_total'], (int) ( 'restoring' === $state['phase'] ? ( $state['restore_db'] ?? 0 ) : ( $state['table_index'] ?? 0 ) ) );
				if ( $p['files_done'] === $p['files_total'] ) { $p['stage'] = 'database'; }
			}
			if ( 'snapshot' === $state['phase'] && isset( $state['snapshot_bytes_total'] ) ) {
				$p['bytes_total'] = $state['snapshot_bytes_total']; $p['bytes_done'] = $state['snapshot_bytes_done'] ?? 0;
				$p['files_reused'] = $state['files_reused'] ?? 0;
			}
		}
		$receipt['progress'] = $p;
		return $receipt;
	}
	private static function save( &$state ) {
		$checkpoint = $state['phase'] . ':' . $state['group'];
		if ( ( $state['progress_phase'] ?? '' ) !== $checkpoint ) { $state['progress_phase'] = $checkpoint; $state['phase_started_at'] = time(); }
		self::write( $state['dir'] . '/state.php', $state );
	}

	public static function serve( $dir ) {
		ini_set( 'display_errors', '0' );
		// A client timeout must not stop a tick halfway; each tick is bounded by its own budget.
		ignore_user_abort( true );
		if ( function_exists( 'set_time_limit' ) ) { @set_time_limit( 300 ); }
		header( 'Content-Type: application/json' );
		header( 'Cache-Control: no-store' );
		$lock = null;
		try {
			$state = self::read( $dir . '/state.php' );
			$auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
			if ( ! preg_match( '/^Bearer ([a-f0-9]{64})$/D', $auth, $matches ) || ! hash_equals( $state['token_hash'], hash( 'sha256', $matches[1] ) ) ) { http_response_code( 403 ); echo '{"error":"forbidden"}'; return; }
			if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || (int) ( $_SERVER['CONTENT_LENGTH'] ?? 0 ) > 4096 ) { http_response_code( 400 ); echo '{"error":"invalid_request"}'; return; }
			$input = json_decode( file_get_contents( 'php://input', false, null, 0, 4097 ), true, 16, JSON_THROW_ON_ERROR );
			$lock = fopen( $state['root'] . '.emcp-update-lock.php', 'c+' );
			if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) { http_response_code( 409 ); echo '{"error":"busy"}'; return; }
			$state = self::read( $dir . '/state.php' );
			if ( ( $input['job'] ?? '' ) !== $state['job'] ) { throw new RuntimeException( 'wrong_job' ); }
			self::advance( $state, $input );
			echo json_encode( self::receipt( $state ), JSON_THROW_ON_ERROR );
		} catch ( Throwable $error ) {
			http_response_code( 500 ); echo '{"error":"recovery_request_failed"}';
		} finally { if ( is_resource( $lock ) ) { flock( $lock, LOCK_UN ); fclose( $lock ); } }
	}

	/** Phase/group compare-and-swap makes delayed approvals unable to approve a later checkpoint. */
	public static function advance( &$s, $input ) {
		$action = $input['action'] ?? '';
		if ( in_array( $s['phase'], self::TERMINAL, true ) ) { self::cleanup( $s ); return; }
		if ( 'status' === $action ) { return; }
		if ( ! in_array( $action, array( 'tick', 'approve', 'rollback', 'release' ), true ) ) { throw new RuntimeException( 'invalid_action' ); }
		if ( ( $input['phase'] ?? '' ) !== $s['phase'] || ( $input['group'] ?? -1 ) !== $s['group'] ) { return; }
		try {
			if ( time() > $s['deadline'] && ! in_array( $s['phase'], array( 'restoring', 'verify_restore', 'manual_recovery' ), true ) ) { $action = 'rollback'; $s['code'] = 'deadline_exceeded'; }
			if ( 'rollback' === $action ) {
				$s['code'] = $s['code'] ?? 'health_failed_or_cancelled';
				if ( empty( $s['snapshot_ready'] ) ) { self::release( $s, 'failed' ); return; }
				self::begin_restore( $s );
				self::save( $s ); return;
			}
			if ( 'release' === $action && 'verify_restore' === $s['phase'] ) { self::release( $s, 'rolled_back' ); return; }
			if ( 'approve' === $action ) {
				if ( 'baseline' === $s['phase'] ) { $s['phase'] = 'draining'; $s['drain_until'] = time() + 120; }
				elseif ( 'verify' === $s['phase'] ) {
					// Only immutable, verified file snapshots may be reused. Databases are always fresh.
					$s['previous_files'] = array();
					foreach ( $s['files'] ?? array() as $i => $file ) { $s['previous_files'][ $file['path'] ] = array( 'hash' => $file['hash'], 'index' => $i ); }
					$s['previous_snapshot'] = $s['snapshot'] ?? null;
					$s['group']++;
					if ( $s['group'] >= count( $s['groups'] ) ) { self::release( $s, 'completed' ); return; }
					unset( $s['snapshot_ready'], $s['snapshot'], $s['files'], $s['tables'], $s['file_index'], $s['table_index'], $s['table_snapshot'], $s['table_hashes'], $s['restore_validation'], $s['restore_validated'], $s['restore_reset'], $s['item_index'], $s['restore_pruned'], $s['scan'], $s['snapshot_bytes_total'], $s['snapshot_bytes_done'], $s['files_reused'] );
					$s['phase'] = 'snapshot';
				}
				self::save( $s ); return;
			}
			if ( 'tick' !== $action ) { return; }
			switch ( $s['phase'] ) {
				case 'prepared':
					$config = self::read( $s['dir'] . '/original-config.php' );
					$gated = self::gate_line( $s['dir'] ) . $config;
					$current = file_get_contents( $s['root'] . 'wp-config.php' );
					if ( $current !== $gated && ! hash_equals( $s['config_hash'], hash( 'sha256', $current ) ) ) { throw new RuntimeException( 'config_changed' ); }
					if ( $current !== $gated ) { self::atomic( $s['root'] . 'wp-config.php', $gated ); }
					// Apache can serve these HTML files without ever reaching wp-config.
					// Install the PHP gate first, then remove static cache hits before baseline.
					self::page_caches( $s['root'], true );
					$s['phase'] = 'baseline'; break;
				case 'draining':
					if ( time() >= $s['drain_until'] ) {
						// An old pre-gate request may have finished writing a cache during drain.
						self::page_caches( $s['root'], true ); $s['phase'] = 'snapshot';
					}
					break;
				case 'snapshot': self::snapshot( $s ); break;
				case 'applying': self::apply( $s ); break;
				case 'updating': // Previous PHP process stopped with an uncertain write. Never replay it.
					$s['code'] = 'update_interrupted'; self::begin_restore( $s ); break;
				case 'restoring': self::restore( $s ); break;
			}
			self::save( $s );
		} catch ( Throwable $error ) {
			$s['code'] = preg_match( '/^[a-z_]+$/D', $error->getMessage() ) ? $error->getMessage() : 'operation_failed';
			$s['diagnostic'] = array( 'class' => get_class( $error ), 'file' => basename( $error->getFile() ), 'line' => $error->getLine(), 'errno' => (int) $error->getCode() );
			if ( 'restoring' === $s['phase'] ) { $s['phase'] = 'manual_recovery'; self::save( $s ); }
			elseif ( ! empty( $s['snapshot_ready'] ) ) { self::begin_restore( $s ); self::save( $s ); }
			else { self::release( $s, 'failed' ); }
		}
	}
	private static function begin_restore( &$s ) {
		$s['phase'] = 'restoring'; $s['restore_step'] = 0; $s['restore_db'] = 0;
		unset( $s['restore_pruned'], $s['restore_swept'], $s['restore_created'], $s['restore_validation'], $s['restore_validated'], $s['restore_reset'] );
	}

	private static function release( &$s, $phase ) {
		$config = self::read( $s['dir'] . '/original-config.php' );
		$current = file_get_contents( $s['root'] . 'wp-config.php' );
		if ( $current !== $config && $current !== self::gate_line( $s['dir'] ) . $config ) { $s['phase'] = 'manual_recovery'; $s['code'] = 'config_conflict'; self::save( $s ); return; }
		// An interrupted later snapshot retains the already approved groups.
		if ( in_array( $phase, array( 'completed', 'rolled_back' ), true ) || $s['group'] > 0 ) {
			try { self::page_caches( $s['root'], true ); }
			catch ( Throwable $error ) { $s['phase'] = 'manual_recovery'; $s['code'] = 'page_cache_cleanup_failed'; self::save( $s ); return; }
		}
		if ( isset( $s['recovery_probe'] ) ) {
			try {
				$db = self::db( $s );
				try { self::recovery_privileges( $db, $s['db']['name'] ); self::remove_probe( $s, $db ); }
				finally { $db->close(); }
			}
			catch ( Throwable $error ) { $s['phase'] = 'manual_recovery'; $s['code'] = 'database_probe_cleanup_failed'; self::save( $s ); return; }
		}
		self::atomic( $s['root'] . 'wp-config.php', $config );
		$s['phase'] = $phase;
		self::save( $s );
		self::cleanup( $s );
	}

	/** Whether the window has changed nothing outside the job folder yet: no update has run, so reopening needs no restore. */
	private static function untouched( $s ) {
		if ( in_array( $s['phase'], array( 'baseline', 'draining' ), true ) ) { return true; }
		if ( 0 !== $s['group'] || ! empty( $s['core_migration'] ) ) { return false; }
		return 'snapshot' === $s['phase'] || ( 'applying' === $s['phase'] && 0 === ( $s['item_index'] ?? 0 ) );
	}

	/**
	 * Called by the gate on every blocked request. Past the deadline, a window that changed
	 * nothing reopens the site by itself, so a silent Cloud cannot leave it in maintenance.
	 * Anything later stays closed until Cloud restores it. Returns the current journal.
	 */
	public static function expire( $dir ) {
		$s = self::read( $dir . '/state.php' );
		if ( time() <= $s['deadline'] || ! self::untouched( $s ) ) { return $s; }
		$lock = @fopen( $s['root'] . '.emcp-update-lock.php', 'c+' );
		if ( ! $lock ) { return $s; }
		try {
			if ( ! flock( $lock, LOCK_EX | LOCK_NB ) ) { return $s; }
			$s = self::read( $dir . '/state.php' );
			if ( time() > $s['deadline'] && self::untouched( $s ) ) { $s['code'] = 'deadline_exceeded'; self::release( $s, 'failed' ); }
			return $s;
		} finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
	}

	/**
	 * A finished job (wp-config already restored) keeps only its authenticated receipt: the
	 * database credentials, original config, download offers and snapshots are removed.
	 */
	public static function cleanup( &$s ) {
		if ( ! in_array( $s['phase'], self::TERMINAL, true ) || ! empty( $s['cleaned'] ) ) { return; }
		$base = rtrim( str_replace( '\\', '/', $s['dir'] ), '/' ) . '/';
		foreach ( glob( $base . 'snapshot-*', GLOB_NOSORT ) ?: array() as $path ) { self::remove_tree( $path, $base ); }
		foreach ( array( 'original-config.php', 'gate.php' ) as $file ) { if ( is_file( $base . $file ) ) { unlink( $base . $file ); } }
		foreach ( $s['groups'] ?? array() as $g => $group ) { foreach ( $group as $i => $item ) { unset( $s['groups'][ $g ][ $i ]['_source'] ); } }
		unset( $s['db'], $s['files'], $s['tables'], $s['table_hashes'], $s['table_snapshot'], $s['restore_validation'], $s['restore_validated'], $s['restore_reset'], $s['previous_files'], $s['previous_snapshot'], $s['scan'] );
		$s['cleaned'] = true;
		self::save( $s );
	}
	/** Removes a whole job folder (uninstall), refusing anything that is not one. */
	public static function remove_job( $dir ) {
		$dir = rtrim( str_replace( '\\', '/', $dir ), '/' );
		if ( 0 !== strpos( basename( $dir ), '.emcp-update-' ) || is_link( $dir ) || ! is_dir( $dir ) ) { throw new RuntimeException( 'not_a_job_folder' ); }
		self::remove_tree( $dir, dirname( $dir ) . '/' );
	}
	private static function remove_tree( $path, $base ) {
		$path = str_replace( '\\', '/', $path );
		if ( ! str_starts_with( $path, $base ) || strlen( $path ) <= strlen( $base ) ) { throw new RuntimeException( 'cleanup_outside_job' ); }
		if ( is_dir( $path ) && ! is_link( $path ) ) {
			foreach ( scandir( $path ) as $entry ) { if ( '.' !== $entry && '..' !== $entry ) { self::remove_tree( $path . '/' . $entry, $base ); } }
			rmdir( $path ); return;
		}
		unlink( $path );
	}

	private static function db( $s ) {
		mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
		$host = $s['db']['host']; $port = 3306; $socket = null;
		if ( preg_match( '/^([^:]+):([0-9]+)$/D', $host, $m ) ) { $host = $m[1]; $port = (int) $m[2]; }
		elseif ( preg_match( '#^([^:]+):(/.+)$#D', $host, $m ) ) { $host = $m[1]; $socket = $m[2]; }
		$db = new mysqli( $host, $s['db']['user'], $s['db']['password'], $s['db']['name'], $port, $socket );
		$db->set_charset( 'utf8mb4' );
		$db->query( "SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'" );
		$db->query( 'SET SESSION sql_quote_show_create = 1' );
		self::show_invisible_keys( $db );
		return $db;
	}
	/** A server may hide generated invisible keys from SHOW metadata by default. */
	private static function show_invisible_keys( $db ) {
		if ( false === stripos( $db->server_info, 'MariaDB' ) && $db->server_version >= 80030 ) {
			$db->query( 'SET SESSION show_gipk_in_create_table_and_information_schema = ON' );
		}
	}
	private static function ident( $name ) {
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/D', $name ) ) { throw new RuntimeException( 'unsupported_table_name' ); }
		return '`' . $name . '`';
	}
	private static function tables( $db, $prefix ) {
		$names = array();
		foreach ( $db->query( 'SHOW TABLES' ) as $row ) { $names[] = reset( $row ); }
		if ( self::other_installs( $names, $prefix, static function ( $table ) use ( $db ) {
			return array_column( $db->query( 'SHOW COLUMNS FROM ' . self::ident( $table ) )->fetch_all( MYSQLI_ASSOC ), 'Field' );
		} ) ) { throw new RuntimeException( 'shared_database_prefix' ); }
		$tables = array();
		foreach ( $db->query( 'SHOW TABLE STATUS' ) as $row ) {
			if ( ! str_starts_with( $row['Name'], $prefix ) ) { continue; }
			if ( 'InnoDB' !== $row['Engine'] ) { throw new RuntimeException( 'innodb_required' ); }
			self::ident( $row['Name'] );
			$tables[] = $row['Name'];
		}
		sort( $tables );
		if ( ! $tables || count( $tables ) > 300 ) { throw new RuntimeException( 'unsupported_database' ); }
		foreach ( $db->query( 'SHOW TRIGGERS' ) as $row ) { if ( str_starts_with( $row['Table'], $prefix ) ) { throw new RuntimeException( 'database_triggers_not_supported' ); } }
		return $tables;
	}
	/** Same database checks at inspection and snapshot time. No readiness-time writes. */
	public static function preflight_database( $connection ) {
		$db = self::db( array( 'db' => $connection ) );
		try {
			$grants = self::effective_grants( $db );
			self::recovery_privileges( $db, $connection['name'], '', $grants );
			$deadline = microtime( true ) + self::$inspection_seconds;
			$bytes = 0;
			foreach ( self::tables( $db, $connection['prefix'] ) as $name ) {
				$plan = self::table_plan( $db, $name, $deadline );
				self::recovery_privileges( $db, $connection['name'], $plan['create'], $grants );
				$bytes += $plan['bytes'];
			}
			return $bytes;
		} finally { $db->close(); }
	}
	/** Snapshot reads alone cannot prove that the account can restore the database. */
	private static function effective_grants( $db ) {
		$read = static function ( $sql ) use ( $db ) { $rows = array(); foreach ( $db->query( $sql ) as $row ) { $rows[] = reset( $row ); } return $rows; };
		$grants = $read( 'SHOW GRANTS' );
		if ( false !== stripos( $db->server_info, 'MariaDB' ) ) {
			if ( $db->server_version >= 100005 ) {
				foreach ( $read( 'SELECT ROLE_NAME FROM information_schema.ENABLED_ROLES' ) as $role ) {
					// No enabled role reads as one NULL row, not an empty result.
					if ( ! is_string( $role ) || '' === $role ) { continue; }
					$grants = array_merge( $grants, $read( 'SHOW GRANTS FOR `' . str_replace( '`', '``', $role ) . '`' ) );
				}
			}
		} elseif ( $db->server_version >= 80000 ) {
			$roles = $read( 'SELECT CURRENT_ROLE()' )[0];
			if ( 'NONE' !== $roles ) {
				// CURRENT_ROLE is SQL account syntax, not an arbitrary SQL fragment.
				$account = '`(?:``|[^`])+`@`(?:``|[^`])+`';
				if ( ! preg_match( '/^' . $account . '(?:\s*,\s*' . $account . ')*$/D', $roles ) ) { throw new RuntimeException( 'database_roles_unavailable' ); }
				$grants = $read( 'SHOW GRANTS FOR CURRENT_USER USING ' . $roles );
			}
		}
		return $grants;
	}
	private static function recovery_privileges( $db, $name, $create = '', $grants = null ) {
		$grants = $grants ?? self::effective_grants( $db );
		self::require_privileges( $db, $name, array( 'SELECT', 'INSERT', 'CREATE', 'DROP', 'TRIGGER' ), $grants );
		// SHOW CREATE emits one constraint per line. Qualifiers identify external parents.
		preg_match_all( '/^\s*(?:CONSTRAINT `(?:``|[^`])+` )?FOREIGN KEY .* REFERENCES (?:(`(?:``|[^`])+`)\.)?`(?:``|[^`])+`/m', $create, $parents );
		foreach ( array_unique( $parents[1] ) as $schema ) {
			$parent = $schema ? str_replace( '``', '`', substr( $schema, 1, -1 ) ) : $name;
			self::require_privileges( $db, $parent, array( 'REFERENCES' ), $grants );
		}
	}
	private static function require_privileges( $db, $name, $required, $grants ) {
		$granted = array(); $revoked = array(); $scoped = array(); $ranks = array();
		$literal_scopes = null; $fold_case = false;
		foreach ( $grants as $grant ) {
			if ( ! preg_match( '/^(GRANT|REVOKE) (.+) ON (\*|`(?:``|[^`])+`)\.\* (?:TO|FROM) (.+?)(?: WITH GRANT OPTION)?$/i', $grant, $match ) ) { continue; }
			$privileges = 'ALL PRIVILEGES' === strtoupper( $match[2] ) ? $required : explode( ', ', strtoupper( $match[2] ) );
			$privileges = array_intersect( $required, $privileges );
			// Even a row without a required privilege can mask a broader schema grant.
			if ( ! $privileges && ( '*' === $match[3] || 'REVOKE' === strtoupper( $match[1] ) ) ) { continue; }
			if ( '*' !== $match[3] && 'GRANT' === strtoupper( $match[1] ) && ! array_diff( $required, array_keys( $granted ) ) ) { continue; }
			if ( '*' !== $match[3] ) {
				$pattern = str_replace( '``', '`', substr( $match[3], 1, -1 ) );
				if ( null === $literal_scopes ) {
					$literal_scopes = false;
					if ( false === stripos( $db->server_info, 'MariaDB' ) && $db->server_version >= 80016 ) {
						$settings = $db->query( 'SELECT @@partial_revokes, @@lower_case_table_names' )->fetch_row();
						$literal_scopes = (bool) (int) $settings[0]; $fold_case = 0 !== (int) $settings[1];
					} else {
						$fold_case = 0 !== (int) $db->query( 'SELECT @@lower_case_table_names' )->fetch_row()[0];
					}
				}
				if ( $literal_scopes ) {
					// partial_revokes makes even unescaped _ and % literal in schema grants.
					$scope = strtr( $pattern, array( '\\_' => '_', '\\%' => '%' ) );
					$matches = $fold_case ? 0 === strcasecmp( $name, $scope ) : $name === $scope;
				} else {
					// Connection collations must not make case-sensitive schemas (or accents) equal.
					$sql = $fold_case ? 'SELECT BINARY LOWER(?) LIKE BINARY LOWER(?)' : 'SELECT BINARY ? LIKE BINARY ?';
					$statement = $db->prepare( $sql ); $statement->bind_param( 'ss', $name, $pattern );
					$statement->execute(); $statement->bind_result( $matches ); $statement->fetch(); $statement->close();
				}
				if ( ! $matches ) { continue; }
				if ( 'GRANT' === strtoupper( $match[1] ) ) {
					$key = $literal_scopes ? $scope : $pattern;
					if ( $fold_case ) { $key = strtolower( $key ); }
					$account = $match[4];
					$scoped[ $account ][ $key ] = array_unique( array_merge( $scoped[ $account ][ $key ] ?? array(), $privileges ) );
					$ranks[ $account ][ $key ] = $literal_scopes ? 128 : ( false === stripos( $db->server_info, 'MariaDB' ) ? self::grant_specificity( $pattern ) : 0 );
					continue;
				}
			}
			foreach ( $privileges as $privilege ) {
				if ( 'REVOKE' === strtoupper( $match[1] ) ) { $revoked[ $privilege ] = true; }
				else { $granted[ $privilege ] = true; }
			}
		}
		// MySQL uses one matching schema row, not their union. SHOW GRANTS does not
		// establish wildcard tie order: require rights present in every possible winner.
		// Separate enabled roles remain additive, as do global grants above.
		foreach ( $scoped as $account => $patterns ) {
			$best = max( $ranks[ $account ] );
			$common = $required;
			foreach ( $patterns as $key => $privileges ) {
				if ( $ranks[ $account ][ $key ] === $best ) { $common = array_intersect( $common, $privileges ); }
			}
			foreach ( $common as $privilege ) { $granted[ $privilege ] = true; }
		}
		if ( array_diff( $required, array_keys( array_diff_key( $granted, $revoked ) ) ) ) { throw new RuntimeException( 'database_recovery_privileges_required' ); }
	}
	/** MySQL orders exact scopes first, then the position of the first unescaped wildcard. */
	private static function grant_specificity( $pattern ) {
		if ( '%' === $pattern ) { return 1; }
		for ( $i = 0, $length = strlen( $pattern ); $i < $length; $i++ ) {
			if ( '\\' === $pattern[ $i ] && $i + 1 < $length ) { $i++; continue; }
			if ( '%' === $pattern[ $i ] || '_' === $pattern[ $i ] ) { return min( $i + 2, 127 ); }
		}
		return '' === $pattern ? 0 : 128;
	}
	/** A server-enforced bound also covers time spent scanning before the first row. */
	private static function bounded_select( $db, $sql, $seconds, $mode = MYSQLI_STORE_RESULT ) {
		$ms = (int) floor( $seconds * 1000 );
		if ( $ms < 1 ) { throw new RuntimeException( 'database_inspection_time_limit' ); }
		if ( false !== stripos( $db->server_info, 'MariaDB' ) && $db->server_version >= 100100 ) {
			$sql = 'SET STATEMENT max_statement_time=' . ( $ms / 1000 ) . ' FOR ' . $sql;
		} elseif ( false === stripos( $db->server_info, 'MariaDB' ) && $db->server_version >= 50708 ) {
			$sql = preg_replace( '/^SELECT /', 'SELECT /*+ MAX_EXECUTION_TIME(' . $ms . ') */ ', $sql, 1 );
		} else { throw new RuntimeException( 'database_query_timeout_not_supported' ); }
		try { return $db->query( $sql, $mode ); }
		catch ( mysqli_sql_exception $error ) {
			if ( in_array( $error->getCode(), array( 1317, 1969, 3024 ), true ) ) { throw new RuntimeException( 'database_inspection_time_limit', 0, $error ); }
			throw $error;
		}
	}
	private static function table_plan( $db, $name, $deadline = null ) {
		$deadline = $deadline ?? microtime( true ) + min( self::$inspection_seconds, self::$tick_seconds );
		$table = self::ident( $name );
		$create = $db->query( 'SHOW CREATE TABLE ' . $table )->fetch_row()[1];
		if ( preg_match( '/GENERATED ALWAYS|GENERATED\s+.*(?:VIRTUAL|STORED)/i', $create ) ) { throw new RuntimeException( 'generated_columns_not_supported' ); }
		$fields = $db->query( 'SHOW FULL COLUMNS FROM ' . $table )->fetch_all( MYSQLI_ASSOC );
		$columns = array_column( $fields, 'Field' ); $types = array_column( $fields, 'Type', 'Field' );
		$collations = array_column( $fields, 'Collation', 'Field' );
		$lengths = array(); $overhead = 2;
		foreach ( $columns as $column ) {
			$value = self::snapshot_column( $column, $types[ $column ] );
			// mysqli returns textual values in the connection's utf8mb4 charset;
			// their encoded size can exceed the bytes stored in a latin1 table.
			if ( ! empty( $collations[ $column ] ) ) { $value = 'CONVERT(' . $value . ' USING utf8mb4)'; }
			$lengths[] = 'COALESCE(OCTET_LENGTH(' . $value . '),0)*2';
			$overhead += strlen( json_encode( $column ) ) + 8;
		}
		// Hex plus JSON overhead, bounded per row instead of per table. This scan also
		// gives a conservative disk budget before entering maintenance.
		$length = '(' . implode( '+', $lengths ) . '+' . $overhead . ')';
		$size = self::bounded_select( $db, 'SELECT COALESCE(MAX(' . $length . '),0), COALESCE(SUM(' . $length . '),0) FROM ' . $table, $deadline - microtime( true ) )->fetch_row();
		$packet = (int) $db->query( 'SELECT @@max_allowed_packet' )->fetch_row()[0];
		if ( (int) $size[0] > self::ROW_BYTES || (int) $size[0] * 2 + 4096 > $packet ) { throw new RuntimeException( 'database_row_too_large' ); }
		$keys = array();
		foreach ( $db->query( 'SHOW INDEX FROM ' . $table ) as $index ) {
			if ( 'PRIMARY' === $index['Key_name'] ) { $keys[(int) $index['Seq_in_index']] = $index['Column_name']; }
		}
		ksort( $keys );
		// Floating-point and BIT keys use the fully ordered fallback, avoiding
		// lossy numeric conversions in a resume predicate.
		foreach ( $keys as $key ) { if ( preg_match( '/^(float|double|real|bit|enum|set)\b/i', $types[ $key ] ) ) { $keys = array(); break; } }
		$numeric_columns = array_keys( array_filter( $types, static fn( $type ) => preg_match( '/^(bit|enum|set)\(/i', $type ) ) );
		return array( 'create' => $create, 'columns' => $columns, 'types' => $types, 'collations' => $collations, 'numeric_columns' => $numeric_columns, 'keys' => array_values( $keys ), 'max_row' => (int) $size[0], 'max_packet' => $packet, 'bytes' => (int) $size[1] );
	}
	/** Use the same lossless SQL representation for sizing, ordering and snapshot rows. */
	private static function snapshot_column( $column, $type ) {
		$name = self::ident( $column );
		// ENUM labels and SET lists can alias distinct ordinals/bitmasks.
		if ( preg_match( '/^(bit|enum|set)\(/i', $type ) ) { return 'CAST(' . $name . ' AS UNSIGNED)'; }
		// FLOAT's direct text output is shortened. Adding a double zero exposes
		// the stored value with double precision, without PHP numeric conversion.
		if ( preg_match( '/^float\b/i', $type ) ) { return '(' . $name . '+0e0)'; }
		return $name;
	}
	public static function check_space( $path, $bytes ) {
		$free = disk_free_space( $path );
		if ( false === $free || $free < $bytes * 2 + 134217728 ) { throw new RuntimeException( 'insufficient_disk_space' ); }
	}
	/** Purge only the two supported plugins' regenerable page caches, never configuration. */
	public static function page_caches( $root, $purge = false ) {
		self::cache_adapter( $root );
		foreach ( array( 'wp-content/cache/wp-rocket', 'wp-content/cache/tw_optimize/page_cache' ) as $relative ) {
			$path = rtrim( $root, '/\\' );
			foreach ( explode( '/', $relative ) as $part ) {
				$path .= '/' . $part;
				if ( is_link( $path ) ) { throw new RuntimeException( 'page_cache_symlink_not_supported' ); }
			}
			if ( ! file_exists( $path ) ) { continue; }
			if ( ! is_dir( $path ) || ! is_writable( $path ) ) { throw new RuntimeException( 'page_cache_not_writable' ); }
			$walk = static function ( $directory ) use ( &$walk, $purge ) {
				foreach ( new DirectoryIterator( $directory ) as $entry ) {
					if ( $entry->isDot() ) { continue; }
					if ( $entry->isLink() ) { throw new RuntimeException( 'page_cache_symlink_not_supported' ); }
					if ( ! is_writable( $entry->getPathname() ) ) { throw new RuntimeException( 'page_cache_not_writable' ); }
					if ( $entry->isDir() ) { $walk( $entry->getPathname() ); }
					if ( $purge && ! ( $entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() ) ) ) { throw new RuntimeException( 'page_cache_cleanup_failed' ); }
				}
			};
			$walk( $path );
		}
	}
	/** Only the native default layouts whose caches we can purge are supported. Never execute the drop-in here. */
	public static function cache_adapter( $root ) {
		$file = rtrim( $root, '/\\' ) . '/wp-content/advanced-cache.php';
		if ( is_link( $file ) ) { throw new RuntimeException( 'page_cache_symlink_not_supported' ); }
		if ( ! is_file( $file ) ) { return null; }
		$source = file_get_contents( $file );
		if ( preg_match( '/define\(\s*[\'\"]WP_ROCKET_ADVANCED_CACHE[\'\"]\s*,\s*true\s*\)/', $source ) && preg_match( '/\$rocket_cache_path\s*=\s*\'((?:\\\\.|[^\'\\\\])*)\'\s*;/', $source, $match ) ) {
			$path = str_replace( array( "\\'", "\\\\" ), array( "'", "\\" ), $match[1] );
			if ( rtrim( str_replace( '\\', '/', $path ), '/' ) === rtrim( str_replace( '\\', '/', $root ), '/' ) . '/wp-content/cache/wp-rocket' ) { return 'wp-rocket'; }
		}
		if ( preg_match( '/define\(\s*[\'\"]TENWEB_SO_ADVANCED_CACHE[\'\"]\s*,\s*true\s*\)/', $source ) && preg_match( '#define\(\s*[\'\"]TENWEB_SO_PAGE_CACHE_DIR[\'\"]\s*,\s*WP_CONTENT_DIR\s*\.\s*[\'\"]/cache/tw_optimize/page_cache/[\'\"]\s*\)#', $source ) ) {
			// wp-config can define this before the native drop-in. Its define then
			// leaves that override in effect, even though the source looks default.
			if ( defined( 'TENWEB_SO_PAGE_CACHE_DIR' ) ) {
				$path = constant( 'TENWEB_SO_PAGE_CACHE_DIR' );
				if ( ! is_string( $path ) ) { throw new RuntimeException( 'page_cache_adapter_not_supported' ); }
				if ( '\\' === DIRECTORY_SEPARATOR ) { $path = str_replace( '\\', '/', $path ); }
				$expected = rtrim( str_replace( '\\', '/', $root ), '/' ) . '/wp-content/cache/tw_optimize/page_cache/';
				// The plugin concatenates the host directly, so the final separator is required.
				if ( ! str_ends_with( $path, '/' ) || rtrim( $path, '/' ) !== rtrim( $expected, '/' ) ) { throw new RuntimeException( 'page_cache_adapter_not_supported' ); }
			}
			return '10web';
		}
		throw new RuntimeException( 'page_cache_adapter_not_supported' );
	}
	/**
	 * Whether a path (relative to the root) is something WordPress updates write: the
	 * root's own files, wp-admin, wp-includes and wp-content, except uploads, caches and
	 * EMCP backup archives. Other applications under the root are never read or deleted.
	 */
	public static function in_scope( $relative, $is_dir ) {
		$relative = trim( str_replace( '\\', '/', $relative ), '/' );
		// WP Launcher's provisioning marker is not WordPress state. Preserve it in
		// both snapshot and prune; never broadly exclude hidden or read-only files.
		if ( '.wp-launcher-ready' === $relative && ! $is_dir ) { return false; }
		if ( false === strpos( $relative, '/' ) && ! $is_dir ) { return true; }
		if ( ! in_array( explode( '/', $relative )[0], array( 'wp-admin', 'wp-includes', 'wp-content' ), true ) ) { return false; }
		foreach ( array( 'wp-content/uploads', 'wp-content/cache', 'wp-content/emcp-backups' ) as $excluded ) {
			if ( $relative === $excluded || str_starts_with( $relative, $excluded . '/' ) ) { return false; }
		}
		// Backups moved to wp-content/emcp-backups-{random} in 3.20.0.
		if ( 1 === preg_match( '#^wp-content/emcp-backups-[a-f0-9]+(/|$)#', $relative ) ) { return false; }
		// Filesystem tool backups moved to wp-content/emcp-fs-backups-{hex} in 3.22.1.
		if ( 1 === preg_match( '#^wp-content/emcp-fs-backups-[a-f0-9]+(/|$)#', $relative ) ) { return false; }
		return true;
	}
	/** Up to 20 in-scope paths the last scan could not read or write, so the refusal can name them. */
	public static $last_unwritable = array();

	/**
	 * Walks what a job would snapshot (no hashing) before the window starts, so a file or
	 * folder the job cannot write is refused up front instead of after maintenance began.
	 */
	public static function preflight_files( $root ) {
		$files = self::files( array( 'root' => rtrim( str_replace( '\\', '/', $root ), '/' ) . '/' ), false, false );
		$bytes = 0;
		foreach ( $files as $file ) { $bytes += filesize( $root . '/' . $file['path'] ); }
		return $bytes;
	}
	private static function files( $s, $check_space = true, $hash = true ) {
		$files = array(); $bytes = 0;
		self::$last_unwritable = array();
		$scan = function ( $dir ) use ( &$scan, &$files, &$bytes, $s, $hash ) {
			foreach ( new DirectoryIterator( $dir ) as $entry ) {
				if ( $entry->isDot() || str_starts_with( $entry->getFilename(), '.emcp-update-' ) ) { continue; }
				$path = $entry->getPathname();
				$relative = str_replace( '\\', '/', substr( $path, strlen( $s['root'] ) ) );
				if ( '.wp-launcher-ready' === $relative && $entry->isLink() ) { throw new RuntimeException( 'symlinks_not_supported' ); }
				if ( ! self::in_scope( $relative, $entry->isDir() ) ) { continue; }
				if ( $entry->isLink() ) { throw new RuntimeException( 'symlinks_not_supported' ); }
				if ( $entry->isDir() ) {
					// Restore creates and deletes files here, so the folder itself must be writable.
					if ( ! is_writable( $path ) && count( self::$last_unwritable ) < 20 ) { self::$last_unwritable[] = $relative . '/'; }
					$scan( $path ); continue;
				}
				if ( 'wp-config.php' === $relative || '.maintenance' === $relative ) { continue; }
				if ( ! $entry->isFile() || ! is_readable( $path ) || ! is_writable( $path ) ) {
					if ( count( self::$last_unwritable ) < 20 ) { self::$last_unwritable[] = $relative; }
					continue;
				}
				$bytes += $entry->getSize();
				if ( count( $files ) >= 50000 || $bytes > 2147483648 || $entry->getSize() > 67108864 ) { throw new RuntimeException( 'snapshot_size_limit' ); }
				$files[] = array( 'path' => $relative, 'mode' => fileperms( $path ) & 0777, 'hash' => $hash ? hash_file( 'sha256', $path ) : '' );
			}
		};
		$scan( rtrim( $s['root'], '/\\' ) );
		if ( self::$last_unwritable ) { throw new RuntimeException( 'files_not_writable' ); }
		if ( $check_space && disk_free_space( $s['dir'] ) < $bytes * 2 + 134217728 ) { throw new RuntimeException( 'insufficient_disk_space' ); }
		return $files;
	}
	/** Persist directory cursors between ticks. Never expose names or paths in public progress. */
	private static function scan_snapshot( &$s ) {
		if ( ! isset( $s['files'] ) ) { $s['files'] = array(); $s['scan'] = array( 'pending' => array( '' ), 'current' => null, 'offset' => 0, 'bytes' => 0 ); }
		$start = microtime( true ); $entries = 0;
		while ( $entries < self::$scan_entries && microtime( true ) - $start < self::$tick_seconds ) {
			if ( null === $s['scan']['current'] ) {
				if ( ! $s['scan']['pending'] ) {
					if ( disk_free_space( $s['dir'] ) < $s['scan']['bytes'] * 2 + 134217728 ) { throw new RuntimeException( 'insufficient_disk_space' ); }
					$s['snapshot_bytes_total'] = $s['scan']['bytes']; unset( $s['scan'] ); return;
				}
				$s['scan']['current'] = array_pop( $s['scan']['pending'] ); $s['scan']['offset'] = 0;
			}
			$relative_dir = $s['scan']['current']; $directory = $s['root'] . $relative_dir;
			if ( is_link( rtrim( $directory, '/' ) ) ) { throw new RuntimeException( 'symlinks_not_supported' ); }
			$iterator = new DirectoryIterator( $directory ); $iterator->seek( $s['scan']['offset'] );
			while ( $iterator->valid() && $entries < self::$scan_entries && microtime( true ) - $start < self::$tick_seconds ) {
				$entry = $iterator->getFileInfo(); $s['scan']['offset']++; $iterator->next(); $entries++;
				$name = $entry->getFilename();
				if ( '.' === $name || '..' === $name || str_starts_with( $name, '.emcp-update-' ) ) { continue; }
				$relative = $relative_dir . $name; $path = $s['root'] . $relative;
				if ( '.wp-launcher-ready' === $relative && $entry->isLink() ) { throw new RuntimeException( 'symlinks_not_supported' ); }
				if ( ! self::in_scope( $relative, $entry->isDir() ) ) { continue; }
				if ( $entry->isLink() ) { throw new RuntimeException( 'symlinks_not_supported' ); }
				if ( $entry->isDir() ) {
					if ( ! is_writable( $path ) ) { throw new RuntimeException( 'files_not_writable' ); }
					$s['scan']['pending'][] = $relative . '/'; continue;
				}
				if ( 'wp-config.php' === $relative || '.maintenance' === $relative ) { continue; }
				if ( ! $entry->isFile() || ! is_readable( $path ) || ! is_writable( $path ) ) { throw new RuntimeException( 'files_not_writable' ); }
				$size = $entry->getSize(); $s['scan']['bytes'] += $size;
				if ( count( $s['files'] ) >= 50000 || $s['scan']['bytes'] > 2147483648 || $size > 67108864 ) { throw new RuntimeException( 'snapshot_size_limit' ); }
				$hash = hash_file( 'sha256', $path );
				if ( false === $hash ) { throw new RuntimeException( 'files_not_writable' ); }
				$s['files'][] = array( 'path' => $relative, 'mode' => fileperms( $path ) & 0777, 'hash' => $hash );
			}
			if ( ! $iterator->valid() ) { $s['scan']['current'] = null; }
		}
	}
	private static function snapshot( &$s ) {
		$dir = $s['dir'] . '/snapshot-' . $s['group'];
		if ( ! isset( $s['files'] ) || isset( $s['scan'] ) ) { self::scan_snapshot( $s ); if ( isset( $s['scan'] ) ) { return; } }
		if ( ! isset( $s['tables'] ) ) {
			$db = self::db( $s );
			try {
				self::recovery_privileges( $db, $s['db']['name'] );
				self::recovery_probe( $s, $db );
				$s['tables'] = self::tables( $db, $s['db']['prefix'] );
			} finally { $db->close(); }
			if ( ! is_dir( $dir ) && ! mkdir( $dir, 0700 ) ) { throw new RuntimeException( 'storage_error' ); }
			$s['file_index'] = 0; $s['table_index'] = 0; $s['table_hashes'] = array();
			$s['snapshot'] = 'snapshot-' . $s['group'];
			return;
		}
		$budget = 0; $start = microtime( true );
		for ( ; $s['file_index'] < count( $s['files'] ) && $budget < self::$tick_bytes && microtime( true ) - $start < self::$tick_seconds; $s['file_index']++ ) {
			$i = $s['file_index']; $file = $s['files'][ $i ];
			$bytes = file_get_contents( $s['root'] . $file['path'] );
			if ( false === $bytes || ! hash_equals( $file['hash'], hash( 'sha256', $bytes ) ) ) { throw new RuntimeException( 'site_changed_during_snapshot' ); }
			$target = $dir . '/file-' . $i . '.php'; $reused = false;
			$previous = $s['previous_files'][ $file['path'] ] ?? null;
			if ( function_exists( 'link' ) && $previous && hash_equals( $previous['hash'], $file['hash'] ) && preg_match( '/^snapshot-[0-9]+$/D', $s['previous_snapshot'] ?? '' ) ) {
				$old = $s['dir'] . '/' . $s['previous_snapshot'] . '/file-' . (int) $previous['index'] . '.php';
				// A hard link shares only a guarded snapshot, never a live WordPress file.
				if ( ! is_link( $old ) && is_file( $old ) && hash_equals( hash( 'sha256', self::GUARD . $bytes ), hash_file( 'sha256', $old ) ) && ! file_exists( $target ) ) { $reused = @link( $old, $target ); }
			}
			if ( ! $reused ) { self::atomic( $target, self::GUARD . $bytes ); }
			$s['files_reused'] = ( $s['files_reused'] ?? 0 ) + ( $reused ? 1 : 0 );
			$s['snapshot_bytes_done'] = ( $s['snapshot_bytes_done'] ?? 0 ) + strlen( $bytes );
			$budget += strlen( $bytes ) + 4096;
		}
		if ( $s['file_index'] < count( $s['files'] ) ) { return; }
		// Shared hosts can limit connection bursts. Reuse one connection for this batch.
		$db = null;
		try {
		while ( $s['table_index'] < count( $s['tables'] ) && $budget < self::$tick_bytes && microtime( true ) - $start < self::$tick_seconds ) {
			if ( null === $db ) { $db = self::db( $s ); }
			$budget += self::snapshot_table_chunk( $s, $db, $dir );
		}
		} finally { if ( null !== $db ) { $db->close(); } }
		if ( $s['table_index'] < count( $s['tables'] ) ) { return; }
		$s['snapshot_ready'] = true; $s['phase'] = 'applying'; $s['item_index'] = 0;
	}
	/** Persist ownership before DDL. Every statement can be replayed after a killed process. */
	private static function recovery_probe( &$s, $db ) {
		if ( ! isset( $s['recovery_probe'] ) ) {
			$owner = bin2hex( random_bytes( 16 ) );
			$s['recovery_probe'] = array( 'name' => substr( $s['db']['prefix'], 0, 30 ) . 'emcp_probe_' . substr( $owner, 0, 16 ), 'owner' => $owner, 'stage' => 'reserved' );
			self::save( $s );
		}
		$p = $s['recovery_probe']; $table = self::ident( $p['name'] );
		$exists = self::owned_probe( $s, $db );
		if ( 'reserved' === $p['stage'] ) {
			if ( ! $exists ) { $db->query( 'CREATE TABLE ' . $table . " (id INT NOT NULL PRIMARY KEY, value VARBINARY(64) NULL) ENGINE=InnoDB COMMENT='emcp:" . $p['owner'] . "'" ); }
			$s['recovery_probe']['stage'] = 'created'; self::save( $s ); $p = $s['recovery_probe']; $exists = true;
		}
		if ( 'created' === $p['stage'] ) {
			if ( ! $exists ) { throw new RuntimeException( 'database_probe_missing' ); }
			$db->query( 'INSERT IGNORE INTO ' . $table . " VALUES (0,'recovery')" );
			if ( 'recovery' !== $db->query( 'SELECT value FROM ' . $table . ' WHERE id=0' )->fetch_row()[0] ) { throw new RuntimeException( 'database_probe_failed' ); }
			$s['recovery_probe']['stage'] = 'inserted'; self::save( $s );
		}
		self::remove_probe( $s, $db );
	}
	private static function owned_probe( $s, $db ) {
		$p = $s['recovery_probe']; self::ident( $p['name'] );
		if ( ! preg_match( '/^[a-f0-9]{32}$/D', $p['owner'] ) ) { throw new RuntimeException( 'database_probe_owner_mismatch' ); }
		$row = $db->query( "SHOW TABLE STATUS WHERE Name='" . $db->real_escape_string( $p['name'] ) . "'" )->fetch_assoc();
		if ( ! $row ) { return false; }
		if ( 'emcp:' . $p['owner'] !== $row['Comment'] || 'InnoDB' !== $row['Engine'] ) { throw new RuntimeException( 'database_probe_owner_mismatch' ); }
		return true;
	}
	private static function remove_probe( &$s, $db ) {
		if ( self::owned_probe( $s, $db ) ) { $db->query( 'DROP TABLE ' . self::ident( $s['recovery_probe']['name'] ) ); }
		unset( $s['recovery_probe'] ); self::save( $s );
	}
	/** Atomic chunks precede their cursor save. A killed tick overwrites its orphan chunk. */
	private static function snapshot_table_chunk( &$s, $db, $dir ) {
		$i = $s['table_index']; $table = self::ident( $s['tables'][ $i ] );
		if ( ! isset( $s['table_snapshot'] ) ) {
			$plan = self::table_plan( $db, $s['tables'][ $i ] );
			self::recovery_privileges( $db, $s['db']['name'], $plan['create'] );
			self::check_space( $dir, $plan['bytes'] );
			$s['table_snapshot'] = $plan + array( 'rows' => 0, 'chunks' => array(), 'last' => null );
		}
		$part = &$s['table_snapshot']; $where = ''; $offset = '';
		if ( $part['keys'] ) {
			$order = implode( ',', array_map( array( self::class, 'ident' ), $part['keys'] ) );
			if ( null !== $part['last'] ) {
				$values = array();
				foreach ( $part['keys'] as $k => $key ) {
					$value = "X'" . $part['last'][ $k ] . "'"; $type = $part['types'][ $key ];
					if ( preg_match( '/^(tinyint|smallint|mediumint|int|bigint)\b/i', $type ) ) { $value = 'CAST(CONVERT(' . $value . ' USING utf8mb4) AS DECIMAL(65,0))'; }
					elseif ( preg_match( '/^(decimal\([0-9]+,[0-9]+\))/i', $type, $match ) ) { $value = 'CAST(CONVERT(' . $value . ' USING utf8mb4) AS ' . $match[1] . ')'; }
					elseif ( ! preg_match( '/binary|blob/i', $type ) ) {
						$value = 'CONVERT(' . $value . ' USING utf8mb4)';
						$collation = $part['collations'][ $key ] ?? null;
						if ( $collation ) {
							self::ident( $collation ); $charset = explode( '_', $collation )[0];
							$value = 'CONVERT(' . $value . ' USING ' . $charset . ') COLLATE ' . $collation;
						}
					}
					$values[] = $value;
				}
				$where = ' WHERE (' . $order . ') > (' . implode( ',', $values ) . ')';
			}
		} else {
			// Without a unique key, sort every value byte-for-byte. Tied rows are
			// identical, so LIMIT/OFFSET preserves duplicate multiplicity on retry.
			$order = implode( ',', array_map( static function ( $column ) use ( $part ) {
				$value = self::snapshot_column( $column, $part['types'][ $column ] );
				return 'BINARY ' . $value . ',SHA2(' . $value . ',256)';
			}, $part['columns'] ) );
			$offset = ' OFFSET ' . (int) $part['rows'];
		}
		$limit = max( 1, min( 1000, self::$tick_rows, intdiv( self::CHUNK_BYTES, max( 1, $part['max_row'] ) ) ) );
		// SELECT * omits invisible columns, including MySQL's generated invisible PK.
		$columns = implode( ',', array_map( static function ( $column ) use ( $part ) {
			$name = self::ident( $column );
			$value = self::snapshot_column( $column, $part['types'][ $column ] );
			return $value === $name ? $name : $value . ' AS ' . $name;
		}, $part['columns'] ) );
		$result = $db->query( 'SELECT ' . $columns . ' FROM ' . $table . $where . ' ORDER BY ' . $order . ' LIMIT ' . $limit . $offset, MYSQLI_USE_RESULT );
		$rows = array(); $bytes = 0; $start = microtime( true ); $last = null;
		try {
			while ( $row = $result->fetch_assoc() ) {
				$row = self::encode_row( $row ); $size = strlen( json_encode( $row, JSON_THROW_ON_ERROR ) );
				if ( $size > self::ROW_BYTES || $size * 2 + 4096 > $part['max_packet'] ) { throw new RuntimeException( 'database_row_too_large' ); }
				$rows[] = $row; $bytes += $size;
				$last = array_map( static fn( $key ) => $row[ $key ], $part['keys'] );
				if ( $bytes >= min( self::CHUNK_BYTES, self::$tick_bytes ) || microtime( true ) - $start >= self::$tick_seconds ) { break; }
			}
		} finally { $result->free(); }
		if ( $rows ) {
			$path = $dir . '/table-' . $i . '-chunk-' . count( $part['chunks'] ) . '.php';
			self::check_space( $dir, $bytes );
			self::write( $path, $rows );
			$part['chunks'][] = array( 'rows' => count( $rows ), 'hash' => hash_file( 'sha256', $path ) );
			$part['rows'] += count( $rows ); $part['last'] = $last;
			self::save( $s );
		} else {
			$path = $dir . '/table-' . $i . '.php';
			self::write( $path, array( 'format' => 2, 'create' => $part['create'], 'rows' => $part['rows'], 'chunks' => $part['chunks'], 'numeric_columns' => $part['numeric_columns'] ?? array() ) );
			$s['table_hashes'][ $i ] = hash_file( 'sha256', $path );
			$s['table_index']++; unset( $s['table_snapshot'] );
			self::save( $s );
		}
		return $bytes + 4096;
	}

	private static function apply( &$s ) {
		if ( ! empty( $s['core_migration'] ) ) {
			$s['phase'] = 'updating'; self::save( $s );
			self::boot( $s );
			if ( $GLOBALS['wp_version'] !== $s['core_migration'] ) { throw new RuntimeException( 'core_version_mismatch' ); }
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			wp_upgrade();
			if ( (int) get_option( 'db_version' ) !== (int) $GLOBALS['wp_db_version'] ) { throw new RuntimeException( 'core_migration_failed' ); }
			unset( $s['core_migration'] ); $s['phase'] = 'applying'; return;
		}
		if ( $s['item_index'] >= count( $s['groups'][ $s['group'] ] ) ) { $s['phase'] = 'verify'; return; }
		$item = $s['groups'][ $s['group'] ][ $s['item_index'] ];
		$s['phase'] = 'updating'; self::save( $s );
		// WordPress deliberately skips regular plugins and themes while installing.
		// This allows the second half of a dependency group to update after the first half would fatal.
		self::boot( $s );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		if ( 'direct' !== get_filesystem_method() ) { throw new RuntimeException( 'direct_filesystem_required' ); }
		$skin = new Automatic_Upgrader_Skin(); $offer = $item['_source']['offer']; $key = $item['_source']['key'];
		$active_before = get_option( 'active_plugins', array() );
		$stylesheet_before = get_option( 'stylesheet' ); $template_before = get_option( 'template' );
		ob_start();
		try {
			if ( 'plugin' === $item['kind'] ) {
				$data = get_plugin_data( WP_PLUGIN_DIR . '/' . $key, false, false );
				if ( $data['Version'] !== $item['from'] ) { throw new RuntimeException( 'version_changed' ); }
				add_filter( 'pre_site_transient_update_plugins', static fn() => (object) array( 'response' => array( $key => (object) $offer ) ) );
				$result = ( new Plugin_Upgrader( $skin ) )->upgrade( $key, array( 'clear_update_cache' => false ) );
				$after = get_plugin_data( WP_PLUGIN_DIR . '/' . $key, false, false )['Version'];
			} elseif ( 'theme' === $item['kind'] ) {
				if ( wp_get_theme( $key )->get( 'Version' ) !== $item['from'] ) { throw new RuntimeException( 'version_changed' ); }
				add_filter( 'pre_site_transient_update_themes', static fn() => (object) array( 'response' => array( $key => $offer ) ) );
				$result = ( new Theme_Upgrader( $skin ) )->upgrade( $key, array( 'clear_update_cache' => false ) );
				wp_clean_themes_cache( false ); $after = wp_get_theme( $key )->get( 'Version' );
			} else {
				if ( $GLOBALS['wp_version'] !== $item['from'] ) { throw new RuntimeException( 'version_changed' ); }
				$offer['packages'] = (object) $offer['packages'];
				$result = ( new Core_Upgrader( $skin ) )->upgrade( (object) $offer );
				$s['core_migration'] = $item['to'];
				$after = $item['to']; // Disk version checked by a fresh process before health verification.
			}
			if ( is_wp_error( $result ) ) { throw new RuntimeException( 'updater_' . substr( preg_replace( '/[^a-z_]/', '', (string) $result->get_error_code() ), 0, 60 ) ); }
			if ( ! $result || $after !== $item['to'] ) { throw new RuntimeException( 'update_failed' ); }
			// The native upgrader can silently deactivate a broken plugin. Restore the original
			// activation list so the following fresh web requests actually exercise the update.
			update_option( 'active_plugins', $active_before );
			if ( get_option( 'active_plugins' ) !== $active_before || get_option( 'stylesheet' ) !== $stylesheet_before || get_option( 'template' ) !== $template_before ) { throw new RuntimeException( 'activation_changed' ); }
		} finally { ob_end_clean(); }
		if ( function_exists( 'opcache_reset' ) ) { opcache_reset(); }
		$s['item_index']++; $s['phase'] = 'applying';
	}
	private static function boot( $s ) {
		define( 'EMCP_UPDATE_INTERNAL', true ); define( 'WP_INSTALLING', true ); define( 'DISABLE_WP_CRON', true );
		global $wpdb, $table_prefix, $wp_version, $wp_db_version, $wp_local_package, $wp_query, $wp_the_query, $wp_rewrite, $wp, $wp_widget_factory, $wp_roles, $wp_locale, $wp_locale_switcher;
		require $s['root'] . 'wp-load.php';
	}

	private static function restore( &$s ) {
		$dir = $s['dir'] . '/' . $s['snapshot'];
		if ( empty( $s['restore_pruned'] ) ) {
			self::prune( $s );
			$s['restore_pruned'] = true; return;
		}
		$budget = 0;
		for ( ; $s['restore_step'] < count( $s['files'] ) && $budget < self::$tick_bytes; $s['restore_step']++ ) {
			$i = $s['restore_step']; $file = $s['files'][ $i ];
			$bytes = file_get_contents( $dir . '/file-' . $i . '.php' );
			if ( false === $bytes || ! str_starts_with( $bytes, self::GUARD ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
			$bytes = substr( $bytes, strlen( self::GUARD ) );
			if ( ! hash_equals( $file['hash'], hash( 'sha256', $bytes ) ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
			$target = $s['root'] . $file['path'];
			if ( ! is_dir( dirname( $target ) ) && ! mkdir( dirname( $target ), 0755, true ) ) { throw new RuntimeException( 'restore_directory_failed' ); }
			self::atomic( $target, $bytes ); chmod( $target, $file['mode'] );
			$budget += strlen( $bytes ) + 4096;
		}
		if ( $s['restore_step'] < count( $s['files'] ) ) { return; }
		if ( empty( $s['restore_swept'] ) ) {
			// A write killed mid-request can leave a temporary copy behind; sweep again once every file is back.
			self::prune( $s );
			$s['restore_swept'] = true; self::save( $s );
		}
		$db = self::db( $s ); $db->query( 'SET FOREIGN_KEY_CHECKS=0' );
		try {
			if ( ! self::reset_restore_schema( $s, $db, $dir ) ) { return; }
			if ( $s['restore_db'] < count( $s['tables'] ) ) {
				$i = $s['restore_db']; $backup = self::table_backup( $s, $dir, $i ); $table = self::ident( $s['tables'][ $i ] );
				$chunked = 2 === ( $backup['format'] ?? 1 );
				if ( ( $s['restore_created'] ?? -1 ) !== $i ) {
					// Baseline triggers are refused at snapshot time. Any trigger here was
					// introduced after that baseline and DROP TABLE must roll it back too.
					self::recovery_privileges( $db, $s['db']['name'], $backup['create'] );
					$db->query( 'DROP TABLE IF EXISTS ' . $table ); $db->query( $backup['create'] );
					$s['restore_created'] = $i; self::save( $s );
				}
				// Each INSERT commits atomically (InnoDB, autocommit) and only this job writes the
				// recreated table, so its row count is exactly how far an interrupted restore got.
				$done = (int) $db->query( 'SELECT COUNT(*) FROM ' . $table )->fetch_row()[0];
				$total = $chunked ? $backup['rows'] : count( $backup['rows'] );
				if ( $done > $total ) { throw new RuntimeException( 'restore_count_mismatch' ); }
				$base = 0; $data = $chunked ? array() : $backup['rows'];
				if ( $chunked && $done < $total ) {
					foreach ( $backup['chunks'] as $chunk => $info ) {
						if ( $done < $base + $info['rows'] ) {
							$path = $dir . '/table-' . $i . '-chunk-' . $chunk . '.php';
							$data = self::read_table_chunk( $path, $info ); break;
						}
						$base += $info['rows'];
					}
				}
				$limit = min( 1048576, intdiv( (int) $db->query( 'SELECT @@max_allowed_packet' )->fetch_row()[0], 2 ) );
				$types = array_column( $db->query( 'SHOW FULL COLUMNS FROM ' . $table )->fetch_all( MYSQLI_ASSOC ), 'Type', 'Field' );
				$batch = array(); $batch_bytes = 0; $columns = null; $rows = 0; $bytes = 0;
				$flush = static function () use ( $db, $table, &$batch, &$batch_bytes, &$columns ) {
					if ( $batch ) { $db->query( 'INSERT INTO ' . $table . ' (' . $columns . ') VALUES ' . implode( ',', $batch ) ); }
					$batch = array(); $batch_bytes = 0;
				};
				$start = microtime( true );
				for ( $r = $done - $base; $r < count( $data ) && $rows < self::$tick_rows && $bytes < self::$tick_bytes && microtime( true ) - $start < self::$tick_seconds; $r++ ) {
					$row = $data[ $r ];
					$cols = implode( ',', array_map( array( self::class, 'ident' ), array_keys( $row ) ) );
					$values = array();
					foreach ( self::restore_row( $row, $backup['numeric_columns'] ?? array() ) as $column => $value ) {
						if ( null === $value ) { $values[] = 'NULL'; }
						elseif ( in_array( $column, $backup['numeric_columns'] ?? array(), true ) || preg_match( '/^bit\(/i', $types[ $column ] ?? '' ) ) {
							$values[] = self::unsigned_value( $value );
						} else { $values[] = "'" . $db->real_escape_string( $value ) . "'"; }
					}
					$values = '(' . implode( ',', $values ) . ')';
					if ( $batch && ( $cols !== $columns || $batch_bytes + strlen( $values ) > $limit ) ) { $flush(); }
					$columns = $cols; $batch[] = $values; $batch_bytes += strlen( $values ) + 1;
					$rows++; $bytes += strlen( $values );
				}
				$flush();
				if ( $base + $r >= $total ) { $s['restore_db']++; unset( $s['restore_created'], $s['restore_validation'], $s['restore_validated'] ); }
				return;
			}
			foreach ( $db->query( 'SHOW TABLE STATUS' ) as $row ) {
				$table = $row['Name'];
				if ( str_starts_with( $table, $s['db']['prefix'] ) && ! in_array( $table, $s['tables'], true ) ) {
					$db->query( ( null === $row['Engine'] ? 'DROP VIEW ' : 'DROP TABLE ' ) . self::ident( $table ) );
				}
			}
		} finally { $db->close(); }
		if ( function_exists( 'opcache_reset' ) ) { opcache_reset(); }
		if ( is_file( $s['root'] . '.maintenance' ) ) { unlink( $s['root'] . '.maintenance' ); }
		$s['phase'] = 'verify_restore';
	}
	private static function table_backup( $s, $dir, $i ) {
		$path = $dir . '/table-' . $i . '.php';
		if ( ! is_file( $path ) || ! hash_equals( $s['table_hashes'][ $i ], hash_file( 'sha256', $path ) ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
		$backup = self::read( $path );
		if ( ! is_array( $backup ) || ! isset( $backup['create'], $backup['rows'] ) || ! is_string( $backup['create'] ) || ( isset( $backup['format'] ) && 2 !== $backup['format'] ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
		if ( isset( $backup['numeric_columns'] ) && ( ! is_array( $backup['numeric_columns'] ) || count( array_filter( $backup['numeric_columns'], 'is_string' ) ) !== count( $backup['numeric_columns'] ) ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
		return $backup;
	}
	private static function unsigned_value( $value ) {
		if ( ! is_string( $value ) || ! preg_match( '/^(0|[1-9][0-9]{0,19})$/D', $value ) || ( 20 === strlen( $value ) && strcmp( $value, '18446744073709551615' ) > 0 ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
		return $value;
	}
	private static function restore_row( $row, $numeric_columns ) {
		$decoded = self::decode_row( $row );
		foreach ( $numeric_columns as $column ) {
			if ( ! array_key_exists( $column, $decoded ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
			if ( null !== $decoded[ $column ] ) { self::unsigned_value( $decoded[ $column ] ); }
		}
		return $decoded;
	}
	/** Check all snapshots before any database DROP; then clear the scoped FK namespace. */
	private static function reset_restore_schema( &$s, $db, $dir ) {
		if ( ! empty( $s['restore_reset']['done'] ) ) { return true; }
		$s['restore_reset'] = $s['restore_reset'] ?? array( 'checked' => 0, 'pending' => null, 'cursor' => 0, 'done' => false );
		$reset = &$s['restore_reset'];
		if ( $reset['checked'] < count( $s['tables'] ) ) {
			$i = $reset['checked']; $backup = self::table_backup( $s, $dir, $i );
			if ( 2 === ( $backup['format'] ?? 1 ) ) {
				if ( ! self::validate_table_chunks( $s, $backup, $dir, $i ) ) { return false; }
			} else {
				if ( ! is_array( $backup['rows'] ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
				foreach ( $backup['rows'] as $row ) { self::restore_row( $row, $backup['numeric_columns'] ?? array() ); }
			}
			self::recovery_privileges( $db, $s['db']['name'], $backup['create'] );
			self::restore_constraint_scope( $s, $db, $backup['create'] );
			$reset['checked']++; unset( $s['restore_validation'], $s['restore_validated'] ); self::save( $s );
			return false;
		}
		self::recovery_privileges( $db, $s['db']['name'] );
		if ( null === $reset['pending'] ) {
			$reset['pending'] = array();
			foreach ( $db->query( 'SHOW TABLE STATUS' ) as $row ) {
				if ( ! str_starts_with( $row['Name'], $s['db']['prefix'] ) ) { continue; }
				self::ident( $row['Name'] );
				$reset['pending'][] = array( 'name' => $row['Name'], 'view' => null === $row['Engine'] );
			}
			// This durable list is never rediscovered after any restored INSERTs.
			self::save( $s );
		}
		$start = microtime( true ); $count = 0;
		while ( $reset['cursor'] < count( $reset['pending'] ) && $count < self::$tick_rows && microtime( true ) - $start < self::$tick_seconds ) {
			$item = $reset['pending'][ $reset['cursor'] ];
			$db->query( ( $item['view'] ? 'DROP VIEW IF EXISTS ' : 'DROP TABLE IF EXISTS ' ) . self::ident( $item['name'] ) );
			$reset['cursor']++; $count++; self::save( $s );
		}
		if ( $reset['cursor'] < count( $reset['pending'] ) ) { return false; }
		$reset['done'] = true; self::save( $s );
		return true;
	}
	/** An out-of-scope owner cannot be removed safely, even if it collides with baseline DDL. */
	private static function restore_constraint_scope( $s, $db, $create ) {
		preg_match_all( '/^\s*CONSTRAINT `((?:``|[^`])+)` FOREIGN KEY /m', $create, $matches );
		if ( ! $matches[1] ) { return; }
		$schema = $s['db']['name'];
		$statement = $db->prepare( 'SELECT TABLE_NAME FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=? AND CONSTRAINT_NAME=?' );
		try {
			foreach ( $matches[1] as $name ) {
				$name = str_replace( '``', '`', $name ); $statement->bind_param( 'ss', $schema, $name );
				$statement->execute(); $statement->bind_result( $owner );
				while ( $statement->fetch() ) {
					if ( ! str_starts_with( $owner, $s['db']['prefix'] ) ) { throw new RuntimeException( 'database_recovery_constraint_outside_scope' ); }
				}
				$statement->free_result();
			}
		} finally { $statement->close(); }
	}
	private static function read_table_chunk( $path, $info ) {
		if ( ! is_file( $path ) || ! hash_equals( $info['hash'], hash_file( 'sha256', $path ) ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
		$rows = self::read( $path );
		if ( ! is_array( $rows ) || count( $rows ) !== $info['rows'] ) { throw new RuntimeException( 'snapshot_corrupt' ); }
		return $rows;
	}
	/** Verify every chunk before dropping the live table, in bounded, restartable ticks. */
	private static function validate_table_chunks( &$s, $backup, $dir, $i ) {
		if ( ! is_int( $backup['rows'] ) || $backup['rows'] < 0 || ! is_array( $backup['chunks'] ) || array_sum( array_column( $backup['chunks'], 'rows' ) ) !== $backup['rows'] ) { throw new RuntimeException( 'snapshot_corrupt' ); }
		if ( ( $s['restore_validated'] ?? -1 ) === $i ) { return true; }
		$cursor = $s['restore_validation'] ?? 0; $bytes = 0; $start = microtime( true );
		for ( ; $cursor < count( $backup['chunks'] ) && $bytes < self::$tick_bytes && microtime( true ) - $start < self::$tick_seconds; $cursor++ ) {
			$path = $dir . '/table-' . $i . '-chunk-' . $cursor . '.php';
			$rows = self::read_table_chunk( $path, $backup['chunks'][ $cursor ] );
			foreach ( $rows as $row ) { self::restore_row( $row, $backup['numeric_columns'] ?? array() ); }
			$bytes += filesize( $path );
		}
		$s['restore_validation'] = $cursor;
		if ( $cursor === count( $backup['chunks'] ) ) { $s['restore_validated'] = $i; }
		self::save( $s );
		return ( $s['restore_validated'] ?? -1 ) === $i;
	}
	/** Deletes every in-scope file the snapshot does not know, re-enumerated beneath the fixed root (symlinks fail closed). */
	private static function prune( $s ) {
		$known = array_fill_keys( array_column( $s['files'], 'path' ), true );
		foreach ( self::files( $s, false, false ) as $file ) {
			if ( ! isset( $known[ $file['path'] ] ) && ! unlink( $s['root'] . $file['path'] ) ) { throw new RuntimeException( 'restore_delete_failed' ); }
		}
	}
}

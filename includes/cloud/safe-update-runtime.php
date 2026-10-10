<?php
/** Self-contained recovery engine. Copied outside plugins before any update. No WordPress dependency for restore. */
class EMCP_Update_Runtime {
	const GUARD = "<?php exit; ?>\n";
	const TERMINAL = array( 'completed', 'rolled_back', 'failed' );

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
		return array_intersect_key( $state, array_flip( array( 'job', 'phase', 'group', 'code', 'snapshot' ) ) );
	}
	private static function save( &$state ) { self::write( $state['dir'] . '/state.php', $state ); }

	public static function serve( $dir ) {
		ini_set( 'display_errors', '0' );
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
		if ( 'status' === $action || in_array( $s['phase'], self::TERMINAL, true ) ) { return; }
		if ( ! in_array( $action, array( 'tick', 'approve', 'rollback', 'release' ), true ) ) { throw new RuntimeException( 'invalid_action' ); }
		if ( ( $input['phase'] ?? '' ) !== $s['phase'] || ( $input['group'] ?? -1 ) !== $s['group'] ) { return; }
		try {
			if ( time() > $s['deadline'] && ! in_array( $s['phase'], array( 'restoring', 'verify_restore', 'manual_recovery' ), true ) ) { $action = 'rollback'; $s['code'] = 'deadline_exceeded'; }
			if ( 'rollback' === $action ) {
				$s['code'] = $s['code'] ?? 'health_failed_or_cancelled';
				if ( empty( $s['snapshot_ready'] ) ) { self::release( $s, 'failed' ); return; }
				$s['phase'] = 'restoring'; $s['restore_step'] = 0; $s['restore_db'] = 0;
				unset( $s['restore_pruned'] );
				self::save( $s ); return;
			}
			if ( 'release' === $action && 'verify_restore' === $s['phase'] ) { self::release( $s, 'rolled_back' ); return; }
			if ( 'approve' === $action ) {
				if ( 'baseline' === $s['phase'] ) { $s['phase'] = 'draining'; $s['drain_until'] = time() + 120; }
				elseif ( 'verify' === $s['phase'] ) {
					$s['group']++;
					if ( $s['group'] >= count( $s['groups'] ) ) { self::release( $s, 'completed' ); return; }
					unset( $s['snapshot_ready'], $s['snapshot'], $s['files'], $s['tables'], $s['file_index'], $s['table_index'], $s['item_index'], $s['restore_pruned'] );
					$s['phase'] = 'snapshot';
				}
				self::save( $s ); return;
			}
			if ( 'tick' !== $action ) { return; }
			switch ( $s['phase'] ) {
				case 'prepared':
					$config = self::read( $s['dir'] . '/original-config.php' );
					if ( ! hash_equals( $s['config_hash'], hash_file( 'sha256', $s['root'] . 'wp-config.php' ) ) ) { throw new RuntimeException( 'config_changed' ); }
					$guard = '<?php /* EMCP_UPDATE_GATE */ require ' . var_export( $s['dir'] . '/gate.php', true ) . '; ?>';
					self::atomic( $s['root'] . 'wp-config.php', $guard . $config );
					$s['phase'] = 'baseline'; break;
				case 'draining':
					if ( time() >= $s['drain_until'] ) { $s['phase'] = 'snapshot'; }
					break;
				case 'snapshot': self::snapshot( $s ); break;
				case 'applying': self::apply( $s ); break;
				case 'updating': // Previous PHP process stopped with an uncertain write. Never replay it.
					$s['code'] = 'update_interrupted'; $s['phase'] = 'restoring'; $s['restore_step'] = 0; $s['restore_db'] = 0; break;
				case 'restoring': self::restore( $s ); break;
			}
			self::save( $s );
		} catch ( Throwable $error ) {
			$s['code'] = preg_match( '/^[a-z_]+$/D', $error->getMessage() ) ? $error->getMessage() : 'operation_failed';
			$s['diagnostic'] = array( 'class' => get_class( $error ), 'file' => basename( $error->getFile() ), 'line' => $error->getLine() );
			if ( 'restoring' === $s['phase'] ) { $s['phase'] = 'manual_recovery'; self::save( $s ); }
			elseif ( ! empty( $s['snapshot_ready'] ) ) { $s['phase'] = 'restoring'; $s['restore_step'] = 0; $s['restore_db'] = 0; self::save( $s ); }
			else { self::release( $s, 'failed' ); }
		}
	}

	private static function release( &$s, $phase ) {
		$config = self::read( $s['dir'] . '/original-config.php' );
		$current = file_get_contents( $s['root'] . 'wp-config.php' );
		$guard = '<?php /* EMCP_UPDATE_GATE */ require ' . var_export( $s['dir'] . '/gate.php', true ) . '; ?>';
		if ( $current !== $config && $current !== $guard . $config ) { $s['phase'] = 'manual_recovery'; $s['code'] = 'config_conflict'; self::save( $s ); return; }
		self::atomic( $s['root'] . 'wp-config.php', $config );
		$s['phase'] = $phase;
		self::save( $s );
	}

	private static function db( $s ) {
		mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
		$host = $s['db']['host']; $port = 3306; $socket = null;
		if ( preg_match( '/^([^:]+):([0-9]+)$/D', $host, $m ) ) { $host = $m[1]; $port = (int) $m[2]; }
		elseif ( preg_match( '#^([^:]+):(/.+)$#D', $host, $m ) ) { $host = $m[1]; $socket = $m[2]; }
		$db = new mysqli( $host, $s['db']['user'], $s['db']['password'], $s['db']['name'], $port, $socket );
		$db->set_charset( 'utf8mb4' );
		$db->query( "SET SESSION sql_mode = 'NO_AUTO_VALUE_ON_ZERO'" );
		return $db;
	}
	private static function ident( $name ) {
		if ( ! preg_match( '/^[a-zA-Z0-9_]+$/D', $name ) ) { throw new RuntimeException( 'unsupported_table_name' ); }
		return '`' . $name . '`';
	}
	private static function tables( $db, $prefix ) {
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
	/**
	 * Whether a path (relative to the root) is something WordPress updates write: the
	 * root's own files, wp-admin, wp-includes and wp-content, except uploads, caches and
	 * EMCP backup archives. Other applications under the root are never read or deleted.
	 */
	public static function in_scope( $relative, $is_dir ) {
		$relative = trim( str_replace( '\\', '/', $relative ), '/' );
		if ( false === strpos( $relative, '/' ) && ! $is_dir ) { return true; }
		if ( ! in_array( explode( '/', $relative )[0], array( 'wp-admin', 'wp-includes', 'wp-content' ), true ) ) { return false; }
		foreach ( array( 'wp-content/uploads', 'wp-content/cache', 'wp-content/emcp-backups' ) as $excluded ) {
			if ( $relative === $excluded || str_starts_with( $relative, $excluded . '/' ) ) { return false; }
		}
		return true;
	}
	private static function files( $s, $check_space = true ) {
		$files = array(); $bytes = 0;
		$scan = function ( $dir ) use ( &$scan, &$files, &$bytes, $s ) {
			foreach ( new DirectoryIterator( $dir ) as $entry ) {
				if ( $entry->isDot() || str_starts_with( $entry->getFilename(), '.emcp-update-' ) ) { continue; }
				$path = $entry->getPathname();
				$relative = str_replace( '\\', '/', substr( $path, strlen( $s['root'] ) ) );
				if ( ! self::in_scope( $relative, $entry->isDir() ) ) { continue; }
				if ( $entry->isLink() ) { throw new RuntimeException( 'symlinks_not_supported' ); }
				if ( $entry->isDir() ) { $scan( $path ); continue; }
				if ( 'wp-config.php' === $relative || '.maintenance' === $relative ) { continue; }
				if ( ! $entry->isFile() || ! is_readable( $path ) || ! is_writable( $path ) ) { throw new RuntimeException( 'files_not_writable' ); }
				$bytes += $entry->getSize();
				if ( count( $files ) >= 50000 || $bytes > 2147483648 || $entry->getSize() > 67108864 ) { throw new RuntimeException( 'snapshot_size_limit' ); }
				$files[] = array( 'path' => $relative, 'mode' => fileperms( $path ) & 0777, 'hash' => hash_file( 'sha256', $path ) );
			}
		};
		$scan( rtrim( $s['root'], '/\\' ) );
		if ( $check_space && disk_free_space( $s['dir'] ) < $bytes * 2 + 134217728 ) { throw new RuntimeException( 'insufficient_disk_space' ); }
		return $files;
	}
	private static function snapshot( &$s ) {
		$dir = $s['dir'] . '/snapshot-' . $s['group'];
		if ( ! isset( $s['files'] ) ) {
			$s['files'] = self::files( $s );
			$db = self::db( $s );
			// Exercise restoration DDL before trusting a read-only database export.
			$canary = self::ident( $s['db']['prefix'] . 'emcp_recovery_probe_' . str_replace( '-', '', substr( $s['job'], 0, 13 ) ) );
			$db->query( 'CREATE TABLE ' . $canary . ' (id INT NOT NULL PRIMARY KEY, value VARBINARY(64) NULL) ENGINE=InnoDB' );
			$db->query( 'INSERT INTO ' . $canary . " VALUES (0,'recovery')" );
			$db->query( 'DROP TABLE ' . $canary );
			$s['tables'] = self::tables( $db, $s['db']['prefix'] ); $db->close();
			if ( ! is_dir( $dir ) && ! mkdir( $dir, 0700 ) ) { throw new RuntimeException( 'storage_error' ); }
			$s['file_index'] = 0; $s['table_index'] = 0; $s['table_hashes'] = array();
			$s['snapshot'] = 'snapshot-' . $s['group'];
			return;
		}
		$end = min( count( $s['files'] ), $s['file_index'] + 20 );
		for ( ; $s['file_index'] < $end; $s['file_index']++ ) {
			$i = $s['file_index']; $file = $s['files'][ $i ];
			$bytes = file_get_contents( $s['root'] . $file['path'] );
			if ( false === $bytes || ! hash_equals( $file['hash'], hash( 'sha256', $bytes ) ) ) { throw new RuntimeException( 'site_changed_during_snapshot' ); }
			self::atomic( $dir . '/file-' . $i . '.php', self::GUARD . $bytes );
		}
		if ( $s['file_index'] < count( $s['files'] ) ) { return; }
		if ( $s['table_index'] < count( $s['tables'] ) ) {
			$i = $s['table_index']; $db = self::db( $s ); $table = self::ident( $s['tables'][ $i ] );
			$create = $db->query( 'SHOW CREATE TABLE ' . $table )->fetch_row()[1];
			if ( preg_match( '/GENERATED ALWAYS|GENERATED\s+.*(?:VIRTUAL|STORED)/i', $create ) ) { throw new RuntimeException( 'generated_columns_not_supported' ); }
			$max_packet = (int) $db->query( 'SELECT @@max_allowed_packet' )->fetch_row()[0];
			$rows = array(); $bytes = 0;
			$result = $db->query( 'SELECT * FROM ' . $table, MYSQLI_USE_RESULT );
			foreach ( $result->fetch_fields() as $field ) { self::ident( $field->name ); }
			while ( $row = $result->fetch_assoc() ) {
				$row = self::encode_row( $row );
				$row_bytes = strlen( json_encode( $row ) );
				if ( $row_bytes * 2 + 4096 > $max_packet ) { throw new RuntimeException( 'database_row_too_large' ); }
				$bytes += $row_bytes;
				if ( $bytes > 20971520 || count( $rows ) >= 50000 ) { throw new RuntimeException( 'table_size_limit' ); }
				$rows[] = $row;
			}
			$result->free(); $db->close();
			self::write( $dir . '/table-' . $i . '.php', array( 'create' => $create, 'rows' => $rows ) );
			$s['table_hashes'][ $i ] = hash_file( 'sha256', $dir . '/table-' . $i . '.php' );
			$s['table_index']++; return;
		}
		$s['snapshot_ready'] = true; $s['phase'] = 'applying'; $s['item_index'] = 0;
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
			// All candidates are re-enumerated beneath the fixed root; symlinks fail closed.
			$known = array_fill_keys( array_column( $s['files'], 'path' ), true );
			foreach ( self::files( $s, false ) as $file ) {
				if ( ! isset( $known[ $file['path'] ] ) && ! unlink( $s['root'] . $file['path'] ) ) { throw new RuntimeException( 'restore_delete_failed' ); }
			}
			$s['restore_pruned'] = true; return;
		}
		$end = min( count( $s['files'] ), $s['restore_step'] + 20 );
		for ( ; $s['restore_step'] < $end; $s['restore_step']++ ) {
			$i = $s['restore_step']; $file = $s['files'][ $i ];
			$bytes = file_get_contents( $dir . '/file-' . $i . '.php' );
			if ( false === $bytes || ! str_starts_with( $bytes, self::GUARD ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
			$bytes = substr( $bytes, strlen( self::GUARD ) );
			if ( ! hash_equals( $file['hash'], hash( 'sha256', $bytes ) ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
			$target = $s['root'] . $file['path'];
			if ( ! is_dir( dirname( $target ) ) && ! mkdir( dirname( $target ), 0755, true ) ) { throw new RuntimeException( 'restore_directory_failed' ); }
			self::atomic( $target, $bytes ); chmod( $target, $file['mode'] );
		}
		if ( $s['restore_step'] < count( $s['files'] ) ) { return; }
		$db = self::db( $s ); $db->query( 'SET FOREIGN_KEY_CHECKS=0' );
		try {
			if ( $s['restore_db'] < count( $s['tables'] ) ) {
				$i = $s['restore_db']; $path = $dir . '/table-' . $i . '.php';
				if ( ! hash_equals( $s['table_hashes'][ $i ], hash_file( 'sha256', $path ) ) ) { throw new RuntimeException( 'snapshot_corrupt' ); }
				$backup = self::read( $path ); $table = self::ident( $s['tables'][ $i ] );
				// Retrying an interrupted table always starts from its immutable before-image.
				$db->query( 'DROP TABLE IF EXISTS ' . $table ); $db->query( $backup['create'] );
				foreach ( $backup['rows'] as $row ) {
					$columns = implode( ',', array_map( array( self::class, 'ident' ), array_keys( $row ) ) );
					$values = implode( ',', array_map( static fn( $v ) => null === $v ? 'NULL' : "'" . $db->real_escape_string( $v ) . "'", self::decode_row( $row ) ) );
					$db->query( 'INSERT INTO ' . $table . ' (' . $columns . ') VALUES (' . $values . ')' );
				}
				$s['restore_db']++; return;
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
}

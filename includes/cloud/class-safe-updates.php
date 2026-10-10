<?php
/** Plugin-side enrollment for Cloud safe updates. Recovery runs without WordPress. */
if ( ! defined( 'ABSPATH' ) ) { exit; }

class EMCP_Tools_Safe_Updates {

	public static function inventory() {
		require_once ABSPATH . 'wp-admin/includes/update.php';
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		wp_update_plugins();
		wp_update_themes();
		wp_version_check();
		$items = array();
		$offers = array();
		$plugins = get_plugins();
		$slugs = array();
		foreach ( $plugins as $file => $plugin ) { $slugs[ dirname( $file ) ] = 'plugin:' . $file; }
		foreach ( (array) ( get_site_transient( 'update_plugins' )->response ?? array() ) as $file => $offer ) {
			if ( ! isset( $plugins[ $file ] ) || empty( $offer->package ) ) { continue; }
			$id = 'plugin:' . $file;
			$dependencies = array();
			foreach ( array_filter( array_map( 'trim', explode( ',', $plugins[ $file ]['RequiresPlugins'] ?? '' ) ) ) as $slug ) {
				if ( isset( $slugs[ $slug ] ) ) { $dependencies[] = $slugs[ $slug ]; }
			}
			$items[] = array( 'id' => $id, 'kind' => 'plugin', 'name' => wp_strip_all_tags( $plugins[ $file ]['Name'] ), 'from' => $plugins[ $file ]['Version'], 'to' => $offer->new_version, 'depends_on' => $dependencies );
			$offers[ $id ] = array( 'key' => $file, 'offer' => (array) $offer );
		}
		foreach ( (array) ( get_site_transient( 'update_themes' )->response ?? array() ) as $slug => $offer ) {
			$theme = wp_get_theme( $slug );
			if ( ! $theme->exists() || empty( $offer['package'] ) ) { continue; }
			$id = 'theme:' . $slug;
			$items[] = array( 'id' => $id, 'kind' => 'theme', 'name' => wp_strip_all_tags( $theme->get( 'Name' ) ), 'from' => $theme->get( 'Version' ), 'to' => $offer['new_version'], 'depends_on' => $theme->parent() ? array( 'theme:' . $theme->get_template() ) : array() );
			$offers[ $id ] = array( 'key' => $slug, 'offer' => $offer );
		}
		foreach ( get_core_updates() ?: array() as $offer ) {
			if ( 'upgrade' !== $offer->response ) { continue; }
			$items[] = array( 'id' => 'core:wordpress', 'kind' => 'core', 'name' => 'WordPress', 'from' => $GLOBALS['wp_version'], 'to' => $offer->current, 'depends_on' => array() );
			$offers['core:wordpress'] = array( 'key' => 'wordpress', 'offer' => (array) $offer );
			break;
		}
		usort( $items, static fn( $a, $b ) => strcmp( $a['id'], $b['id'] ) );
		$blockers = self::blockers();
		$result = array( 'site_uuid' => EMCP_Tools_Cloud::site_uuid(), 'fingerprint' => hash( 'sha256', wp_json_encode( $items ) ), 'supported' => ! $blockers, 'blockers' => $blockers, 'items' => $items, '_offers' => $offers );
		// Name the files the job could not write, so the owner knows what to fix.
		if ( in_array( 'files_not_writable', $blockers, true ) ) { $result['unwritable'] = EMCP_Update_Runtime::$last_unwritable; }
		return $result;
	}

	private static function blockers() {
		$codes = array();
		if ( is_multisite() ) { $codes[] = 'multisite_not_supported'; }
		if ( ! is_ssl() ) { $codes[] = 'https_required'; }
		if ( untrailingslashit( home_url() ) !== untrailingslashit( site_url() ) ) { $codes[] = 'separate_home_path_not_supported'; }
		if ( wp_normalize_path( WP_CONTENT_DIR ) !== wp_normalize_path( ABSPATH . 'wp-content' ) || wp_normalize_path( WP_PLUGIN_DIR ) !== wp_normalize_path( ABSPATH . 'wp-content/plugins' ) ) { $codes[] = 'custom_content_path'; }
		if ( ! is_writable( ABSPATH ) || ! is_writable( ABSPATH . 'wp-config.php' ) || is_link( ABSPATH . 'wp-config.php' ) ) { $codes[] = 'recovery_files_not_writable'; }
		if ( ! extension_loaded( 'mysqli' ) ) { $codes[] = 'mysqli_required'; }
		global $wpdb;
		require_once __DIR__ . '/safe-update-runtime.php';
		$tables = $wpdb->get_col( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $wpdb->prefix ) . '%' ) );
		// Another install whose prefix extends ours (wp_shop_ under wp_) would be rolled back with this site.
		if ( EMCP_Update_Runtime::other_installs( (array) $tables, $wpdb->prefix ) ) { $codes[] = 'shared_database_prefix'; }
		try {
			EMCP_Update_Runtime::preflight_files( ABSPATH );
		} catch ( RuntimeException $error ) {
			$codes[] = preg_match( '/^[a-z_]+$/D', $error->getMessage() ) ? $error->getMessage() : 'files_not_supported';
		}
		// MU plugins run after the wp-config gate and are covered by the file snapshot.
		// Recovery never boots WordPress, so even a fatal in an MU plugin is recoverable.
		if ( defined( 'WPMU_PLUGIN_DIR' ) && wp_normalize_path( WPMU_PLUGIN_DIR ) !== wp_normalize_path( ABSPATH . 'wp-content/mu-plugins' ) ) { $codes[] = 'custom_mu_plugin_path'; }
		foreach ( array_keys( get_dropins() ) as $dropin ) { $codes[] = 'dropin_requires_host_recovery:' . $dropin; }
		if ( wp_using_ext_object_cache() ) { $codes[] = 'external_object_cache_requires_host_recovery'; }
		if ( defined( 'DISALLOW_FILE_MODS' ) && DISALLOW_FILE_MODS ) { $codes[] = 'file_modifications_disabled'; }
		if ( defined( 'FS_METHOD' ) && 'direct' !== FS_METHOD ) { $codes[] = 'direct_filesystem_required'; }
		if ( is_file( ABSPATH . '.maintenance' ) || (int) get_option( 'auto_updater.lock' ) > time() - 900 || (int) get_option( 'core_updater.lock' ) > time() - 900 ) { $codes[] = 'another_update_in_progress'; }
		return $codes;
	}

	/**
	 * Uninstall: removes finished job folders and, when none is still recovering, the lock.
	 * A job that is active or needs manual recovery keeps everything, gate included.
	 */
	public static function uninstall_cleanup() {
		require_once __DIR__ . '/safe-update-runtime.php';
		$active = false;
		foreach ( glob( ABSPATH . '.emcp-update-*', GLOB_ONLYDIR ) ?: array() as $dir ) {
			try {
				$state = EMCP_Update_Runtime::read( $dir . '/state.php' );
				if ( in_array( $state['phase'], EMCP_Update_Runtime::TERMINAL, true ) ) { EMCP_Update_Runtime::remove_job( $dir ); } else { $active = true; }
			} catch ( Throwable $error ) {
				$active = true; // Unreadable: leave it for a human.
			}
		}
		if ( ! $active && is_file( ABSPATH . '.emcp-update-lock.php' ) ) { unlink( ABSPATH . '.emcp-update-lock.php' ); }
	}

	public static function execute( $input ) {
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'update_plugins' ) || ! current_user_can( 'update_themes' ) || ! current_user_can( 'update_core' ) ) { return array( 'error' => 'forbidden' ); }
		try {
			if ( ! is_array( $input ) || ! in_array( $input['action'] ?? '', array( 'inspect', 'prepare' ), true ) ) { throw new RuntimeException( 'invalid_input' ); }
			if ( 'inspect' === $input['action'] ) { $result = self::inventory(); unset( $result['_offers'] ); return $result; }
			$job = $input['job'] ?? '';
			$token = $input['token'] ?? '';
			if ( ! is_string( $job ) || ! preg_match( '/^[a-f0-9-]{36}$/D', $job ) || ! is_string( $token ) || ! preg_match( '/^[a-f0-9]{64}$/D', $token ) || true !== ( $input['maintenance_accepted'] ?? false ) || ( $input['site_uuid'] ?? '' ) !== EMCP_Tools_Cloud::site_uuid() ) { throw new RuntimeException( 'invalid_input' ); }
			$dir = ABSPATH . '.emcp-update-' . $job;
			require_once __DIR__ . '/safe-update-runtime.php';
			if ( is_file( $dir . '/state.php' ) ) {
				$state = EMCP_Update_Runtime::read( $dir . '/state.php' );
				if ( ! hash_equals( $state['token_hash'], hash( 'sha256', $token ) ) ) { throw new RuntimeException( 'idempotency_conflict' ); }
				return EMCP_Update_Runtime::receipt( $state );
			}
			$inventory = self::inventory();
			if ( ! $inventory['supported'] || ( $input['fingerprint'] ?? '' ) !== $inventory['fingerprint'] ) { throw new RuntimeException( 'preflight_changed_or_unsupported' ); }
			$groups = $input['groups'] ?? array();
			if ( ! is_array( $groups ) || ! count( $groups ) || count( $groups ) > 100 ) { throw new RuntimeException( 'invalid_groups' ); }
			$seen = array();
			foreach ( $groups as &$group ) {
				if ( ! is_array( $group ) || ! count( $group ) || count( $group ) > 100 ) { throw new RuntimeException( 'invalid_groups' ); }
				foreach ( $group as &$item ) {
					$id = is_array( $item ) ? ( $item['id'] ?? '' ) : '';
					$found = array_values( array_filter( $inventory['items'], static fn( $candidate ) => $candidate['id'] === $id ) );
					if ( ! $found || isset( $seen[ $id ] ) || $found[0] !== $item ) { throw new RuntimeException( 'invalid_selection' ); }
					$seen[ $id ] = true;
					$item['_source'] = $inventory['_offers'][ $id ];
				}
				unset( $item );
			}
			unset( $group );
			$paths = array_values( array_unique( array_merge( array( '/', '/wp-login.php' ), $input['paths'] ?? array() ) ) );
			if ( count( $paths ) > 7 ) { throw new RuntimeException( 'invalid_paths' ); }
			foreach ( $paths as $path ) { if ( ! is_string( $path ) || ! preg_match( '#^/(?!/)[a-zA-Z0-9_./-]*$#D', $path ) || str_contains( $path, '..' ) || preg_match( '/wp-(admin|cron|json|activate|signup)|xmlrpc\.php/i', $path ) ) { throw new RuntimeException( 'invalid_paths' ); } }
			$lock = fopen( ABSPATH . '.emcp-update-lock.php', 'c+' );
			if ( ! $lock || ! flock( $lock, LOCK_EX | LOCK_NB ) ) { throw new RuntimeException( 'site_busy' ); }
			try {
				$config = file_get_contents( ABSPATH . 'wp-config.php' );
				if ( str_contains( $config, 'EMCP_UPDATE_GATE' ) ) { throw new RuntimeException( 'existing_recovery_requires_cleanup' ); }
				foreach ( glob( ABSPATH . '.emcp-update-*/state.php' ) ?: array() as $prior ) {
					$prior_state = EMCP_Update_Runtime::read( $prior );
					if ( ! in_array( $prior_state['phase'], EMCP_Update_Runtime::TERMINAL, true ) ) { throw new RuntimeException( 'existing_recovery_requires_cleanup' ); }
					// Jobs finished by an older runtime may still hold credentials and snapshots.
					EMCP_Update_Runtime::cleanup( $prior_state );
				}
				// The web server must traverse this directory to reach runner.php. Journals
				// remain owner-only PHP-guarded files; snapshot subdirectories are private.
				if ( ! mkdir( $dir, 0755 ) ) { throw new RuntimeException( 'storage_error' ); }
				foreach ( array( 'safe-update-runtime.php' => 'runtime.php', 'safe-update-gate.php' => 'gate.php' ) as $source => $dest ) {
					if ( ! copy( __DIR__ . '/' . $source, $dir . '/' . $dest ) ) { throw new RuntimeException( 'storage_error' ); }
				}
				global $wpdb;
				$state = array( 'job' => $job, 'token_hash' => hash( 'sha256', $token ), 'root' => ABSPATH, 'dir' => $dir, 'phase' => 'prepared', 'group' => 0, 'groups' => $groups, 'paths' => $paths, 'base_path' => rtrim( wp_parse_url( site_url(), PHP_URL_PATH ) ?: '', '/' ), 'deadline' => time() + 3600, 'db' => array( 'host' => DB_HOST, 'user' => DB_USER, 'password' => DB_PASSWORD, 'name' => DB_NAME, 'prefix' => $wpdb->prefix ), 'config_hash' => hash( 'sha256', $config ) );
				EMCP_Update_Runtime::write( $dir . '/original-config.php', $config );
				EMCP_Update_Runtime::write( $dir . '/state.php', $state );
				$runner = "<?php\nrequire __DIR__ . '/runtime.php';\nEMCP_Update_Runtime::serve(__DIR__);\n";
				if ( file_put_contents( $dir . '/runner.php', $runner ) !== strlen( $runner ) ) { throw new RuntimeException( 'storage_error' ); }
				return EMCP_Update_Runtime::receipt( $state );
			} finally { flock( $lock, LOCK_UN ); fclose( $lock ); }
		} catch ( Throwable $error ) {
			return array( 'error' => preg_match( '/^[a-z_]+$/D', $error->getMessage() ) ? $error->getMessage() : 'prepare_failed' );
		}
	}
}

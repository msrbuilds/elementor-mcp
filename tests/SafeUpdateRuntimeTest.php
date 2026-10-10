<?php
use PHPUnit\Framework\TestCase;
require_once dirname( __DIR__ ) . '/includes/cloud/safe-update-runtime.php';

final class SafeUpdateRuntimeTest extends TestCase {
	private string $root;
	private array $state;
	protected function setUp(): void {
		$this->root = str_replace( '\\', '/', sys_get_temp_dir() ) . '/emcp-safe-unit-' . bin2hex( random_bytes( 6 ) ) . '/';
		mkdir( $this->root, 0700, true ); mkdir( $this->root . '.emcp-update-test', 0700 );
		file_put_contents( $this->root . 'wp-config.php', '<?php /* original */' );
		$this->state = array( 'job' => 'test', 'root' => $this->root, 'dir' => $this->root . '.emcp-update-test', 'phase' => 'prepared', 'group' => 0, 'groups' => array( array( array( 'id' => 'plugin:a' ) ), array( array( 'id' => 'plugin:b' ) ) ), 'deadline' => time() + 600, 'config_hash' => hash_file( 'sha256', $this->root . 'wp-config.php' ) );
		EMCP_Update_Runtime::write( $this->state['dir'] . '/original-config.php', '<?php /* original */' );
		EMCP_Update_Runtime::write( $this->state['dir'] . '/state.php', $this->state );
	}
	protected function tearDown(): void {
		EMCP_Update_Runtime::$tick_bytes = 33554432; EMCP_Update_Runtime::$tick_rows = 5000;
		$it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $this->root, FilesystemIterator::SKIP_DOTS ), RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $it as $entry ) { $entry->isDir() ? rmdir( $entry->getPathname() ) : unlink( $entry->getPathname() ); }
		rmdir( $this->root );
	}
	private function command( string $action ): void {
		EMCP_Update_Runtime::advance( $this->state, array( 'action' => $action, 'phase' => $this->state['phase'], 'group' => $this->state['group'] ) );
	}
	public function test_guarded_journal_round_trip_and_receipt_does_not_expose_secrets(): void {
		$this->state['db'] = array( 'password' => 'private' );
		EMCP_Update_Runtime::write( $this->state['dir'] . '/state.php', $this->state );
		$this->assertStringStartsWith( '<?php exit; ?>', file_get_contents( $this->state['dir'] . '/state.php' ) );
		$this->assertSame( $this->state, EMCP_Update_Runtime::read( $this->state['dir'] . '/state.php' ) );
		$this->assertArrayNotHasKey( 'db', EMCP_Update_Runtime::receipt( $this->state ) );
	}
	public function test_preparation_does_not_change_config_until_authenticated_tick(): void {
		$this->assertStringNotContainsString( 'EMCP_UPDATE_GATE', file_get_contents( $this->root . 'wp-config.php' ) );
		$this->command( 'tick' );
		$this->assertSame( 'baseline', $this->state['phase'] );
		$this->assertStringContainsString( 'EMCP_UPDATE_GATE', file_get_contents( $this->root . 'wp-config.php' ) );
		$this->command( 'rollback' );
		$this->assertSame( 'failed', $this->state['phase'] );
		$this->assertSame( '<?php /* original */', file_get_contents( $this->root . 'wp-config.php' ) );
	}
	public function test_changed_config_before_start_fails_closed_without_overwriting_it(): void {
		file_put_contents( $this->root . 'wp-config.php', '<?php /* changed elsewhere */' );
		$this->command( 'tick' );
		$this->assertSame( 'manual_recovery', $this->state['phase'] );
		$this->assertSame( '<?php /* changed elsewhere */', file_get_contents( $this->root . 'wp-config.php' ) );
	}
	public function test_baseline_approval_waits_for_old_requests_to_drain(): void {
		$this->command( 'tick' ); $this->command( 'approve' );
		$this->assertSame( 'draining', $this->state['phase'] );
		$this->command( 'tick' ); $this->assertSame( 'draining', $this->state['phase'] );
		$this->state['drain_until'] = time() - 1; $this->command( 'tick' );
		$this->assertSame( 'snapshot', $this->state['phase'] );
	}
	public function test_stale_approval_cannot_advance_a_later_group(): void {
		$this->state['phase'] = 'verify';
		$input = array( 'action' => 'approve', 'phase' => 'verify', 'group' => 0 );
		EMCP_Update_Runtime::advance( $this->state, $input );
		$this->assertSame( 1, $this->state['group'] );
		$this->state['phase'] = 'verify';
		EMCP_Update_Runtime::advance( $this->state, $input );
		$this->assertSame( 'verify', $this->state['phase'] ); $this->assertSame( 1, $this->state['group'] );
	}
	public function test_interrupted_updater_is_never_replayed(): void {
		$this->state['phase'] = 'updating'; $this->state['snapshot_ready'] = true;
		$this->command( 'tick' );
		$this->assertSame( 'restoring', $this->state['phase'] );
		$this->assertSame( 'update_interrupted', $this->state['code'] );
	}
	public function test_deadline_causes_recovery_instead_of_another_update(): void {
		$this->state['phase'] = 'applying'; $this->state['snapshot_ready'] = true; $this->state['deadline'] = time() - 1;
		$this->command( 'tick' ); $this->assertSame( 'restoring', $this->state['phase'] );
	}
	public function test_restore_is_not_reported_successful_until_explicit_health_confirmation(): void {
		$this->command( 'tick' ); $this->state['phase'] = 'verify_restore';
		$this->command( 'tick' ); $this->assertSame( 'verify_restore', $this->state['phase'] );
		$this->command( 'release' ); $this->assertSame( 'rolled_back', $this->state['phase'] );
		$this->assertSame( '<?php /* original */', file_get_contents( $this->root . 'wp-config.php' ) );
	}

	/**
	 * Host malware scanners flag decoding next to file writes (issue #100, the 3.14
	 * restore engine), so the recovery runtime stores database values as hex.
	 */
	public function test_recovery_files_carry_no_base64_decoding(): void {
		foreach ( array( 'safe-update-runtime.php', 'safe-update-gate.php', 'class-safe-updates.php' ) as $file ) {
			$this->assertStringNotContainsString( 'base64_', (string) file_get_contents( dirname( __DIR__ ) . '/includes/cloud/' . $file ), $file );
		}
		$row = array( 'a' => 'bytes' . chr( 0 ) . chr( 255 ) . "'\"", 'b' => null, 'c' => '' );
		$this->assertSame( $row, EMCP_Update_Runtime::decode_row( EMCP_Update_Runtime::encode_row( $row ) ) );
	}

	/** A restored non-PHP file (an upload holding PHP code) must never sit under a .php name, even briefly. */
	public function test_temporary_copies_keep_a_non_executable_name(): void {
		$this->assertStringEndsWith( '.tmp', EMCP_Update_Runtime::temp_path( '/site/wp-content/uploads/photo.jpg' ) );
		$this->assertStringEndsWith( '.tmp', EMCP_Update_Runtime::temp_path( '/site/.htaccess' ) );
		$this->assertStringEndsWith( '.php', EMCP_Update_Runtime::temp_path( '/site/.emcp-update-x/state.php' ) );
		$this->assertNotSame( EMCP_Update_Runtime::temp_path( '/site/a.php' ), EMCP_Update_Runtime::temp_path( '/site/a.php' ) );
	}

	/** Snapshots and restore deletions stay inside what updates write; other apps and user files are never touched. */
	public function test_snapshot_scope_excludes_other_apps_uploads_and_backups(): void {
		foreach ( array( 'index.php', 'wp-admin/a.php', 'wp-includes/b.php', 'wp-content/plugins/p/p.php', 'wp-content/themes/t/style.css', 'wp-content/languages/x.mo', 'shop/app.php', 'wp-content/uploads/2026/photo.jpg', 'wp-content/cache/page.html', 'wp-content/emcp-backups/site.emcp' ) as $file ) {
			if ( ! is_dir( dirname( $this->root . $file ) ) ) { mkdir( dirname( $this->root . $file ), 0700, true ); }
			file_put_contents( $this->root . $file, 'x' );
		}
		$files = ( new ReflectionMethod( EMCP_Update_Runtime::class, 'files' ) )->invoke( null, $this->state, false );
		$paths = array_column( $files, 'path' );
		sort( $paths );
		$this->assertSame( array( 'index.php', 'wp-admin/a.php', 'wp-content/languages/x.mo', 'wp-content/plugins/p/p.php', 'wp-content/themes/t/style.css', 'wp-includes/b.php' ), $paths );
	}

	/** Files the job could not snapshot or restore are found before the maintenance window starts, and named. */
	public function test_preflight_names_files_the_job_cannot_write(): void {
		mkdir( $this->root . 'wp-includes', 0700 );
		file_put_contents( $this->root . 'wp-includes/locked.php', 'x' );
		file_put_contents( $this->root . 'wp-includes/open.php', 'x' );
		chmod( $this->root . 'wp-includes/locked.php', 0444 );
		try {
			EMCP_Update_Runtime::preflight_files( $this->root );
			$this->fail( 'expected files_not_writable' );
		} catch ( RuntimeException $e ) {
			$this->assertSame( 'files_not_writable', $e->getMessage() );
			$this->assertSame( array( 'wp-includes/locked.php' ), EMCP_Update_Runtime::$last_unwritable );
		} finally {
			chmod( $this->root . 'wp-includes/locked.php', 0644 );
		}
		chmod( $this->root . 'wp-includes/locked.php', 0644 );
		EMCP_Update_Runtime::preflight_files( $this->root );
		$this->assertSame( array(), EMCP_Update_Runtime::$last_unwritable );
	}

	public function test_host_marker_is_preserved_while_mu_plugins_remain_in_snapshot_and_prune_scope(): void {
		mkdir( $this->root . 'wp-content/mu-plugins', 0700, true );
		$mu = 'wp-content/mu-plugins/host.php';
		file_put_contents( $this->root . $mu, '<?php /* host integration */' );
		file_put_contents( $this->root . '.wp-launcher-ready', 'host-owned' );
		chmod( $this->root . '.wp-launcher-ready', 0444 );
		try {
			EMCP_Update_Runtime::preflight_files( $this->root );
			$files = ( new ReflectionMethod( EMCP_Update_Runtime::class, 'files' ) )->invoke( null, $this->state, false );
			$this->assertSame( array( $mu ), array_column( $files, 'path' ) );
			$this->state['files'] = $files;
			file_put_contents( $this->root . 'wp-content/mu-plugins/new.php', '<?php throw new Exception("broken");' );
			( new ReflectionMethod( EMCP_Update_Runtime::class, 'prune' ) )->invoke( null, $this->state );
			$this->assertFileDoesNotExist( $this->root . 'wp-content/mu-plugins/new.php' );
			$this->assertSame( 'host-owned', file_get_contents( $this->root . '.wp-launcher-ready' ) );
			$this->assertFileExists( $this->root . $mu );
		} finally { chmod( $this->root . '.wp-launcher-ready', 0644 ); }
		$this->assertTrue( EMCP_Update_Runtime::in_scope( '.htaccess', false ) );
		$this->assertTrue( EMCP_Update_Runtime::in_scope( '.user.ini', false ) );
		$this->assertTrue( EMCP_Update_Runtime::in_scope( 'wp-content/plugins/x/.wp-launcher-ready', false ) );
	}

	public function test_gate_blocks_before_a_broken_mu_plugin_can_execute(): void {
		$this->state['phase'] = 'restoring';
		$this->install_gate( '<?php throw new Exception("MU plugin loaded");' );
		$out = $this->visit();
		$this->assertStringContainsString( 'Scheduled maintenance', $out );
		$this->assertStringNotContainsString( 'MU plugin loaded', $out );
	}

	/** Real file + MySQL snapshot, then fresh-process recovery while MU PHP cannot boot. */
	public function test_standalone_rollback_restores_broken_mu_plugin_and_database_preserving_host_marker(): void {
		$conf = getenv( 'EMCP_SAFE_UPDATE_TEST_DB' );
		if ( ! $conf || ! extension_loaded( 'mysqli' ) ) { $this->markTestSkipped( 'EMCP_SAFE_UPDATE_TEST_DB not set.' ); }
		list( $host, $user, $pass ) = array_pad( explode( '|', $conf ), 3, '' );
		$name = 'emcp_su_' . bin2hex( random_bytes( 4 ) );
		mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
		$admin = new mysqli( $host, $user, $pass );
		$admin->query( 'CREATE DATABASE `' . $name . '`' );
		try {
			$admin->select_db( $name );
			$admin->query( 'CREATE TABLE wp_options (id INT PRIMARY KEY, value TEXT) ENGINE=InnoDB' );
			$admin->query( "INSERT INTO wp_options VALUES (1,'before')" );
			mkdir( $this->root . 'wp-content/mu-plugins', 0700, true );
			$mu = $this->root . 'wp-content/mu-plugins/host.php';
			$original = '<?php echo "HOST HEALTHY";';
			file_put_contents( $mu, $original );
			file_put_contents( $this->root . '.wp-launcher-ready', 'host-owned' );
			chmod( $this->root . '.wp-launcher-ready', 0444 );
			$this->state['db'] = array( 'host' => $host, 'user' => $user, 'password' => $pass, 'name' => $name, 'prefix' => 'wp_' );
			$this->state['phase'] = 'snapshot';
			$this->install_gate();
			for ( $i = 0; $i < 10 && 'snapshot' === $this->state['phase']; $i++ ) { $this->command( 'tick' ); }
			$this->assertSame( 'applying', $this->state['phase'], $this->state['code'] ?? '' );
			$this->assertTrue( $this->state['snapshot_ready'] );
			file_put_contents( $mu, '<?php throw new Exception("broken host integration");' );
			file_put_contents( $this->root . 'wp-content/mu-plugins/added.php', '<?php die("bad");' );
			$admin->query( "UPDATE wp_options SET value='after'" );
			$this->command( 'rollback' );
			$script = $this->state['dir'] . '/step.php';
			file_put_contents( $script, '<?php require __DIR__ . "/runtime.php"; $s = EMCP_Update_Runtime::read(__DIR__ . "/state.php"); EMCP_Update_Runtime::advance($s, array("action" => "tick", "phase" => $s["phase"], "group" => $s["group"])); echo $s["phase"];' );
			for ( $i = 0; $i < 10; $i++ ) {
				$out = $this->php( $script );
				$this->assertContains( $out, array( 'restoring', 'verify_restore' ) );
				if ( 'verify_restore' === $out ) { break; }
			}
			$this->assertSame( 'verify_restore', $out );
			$this->assertSame( $original, file_get_contents( $mu ) );
			$this->assertSame( 'HOST HEALTHY', $this->php( $mu ) );
			$this->assertFileDoesNotExist( $this->root . 'wp-content/mu-plugins/added.php' );
			$this->assertSame( 'before', $admin->query( 'SELECT value FROM wp_options' )->fetch_row()[0] );
			$this->assertSame( 'host-owned', file_get_contents( $this->root . '.wp-launcher-ready' ) );
			$this->state = EMCP_Update_Runtime::read( $this->state['dir'] . '/state.php' );
			$this->command( 'release' );
			$this->assertSame( 'rolled_back', $this->state['phase'] );
		} finally {
			chmod( $this->root . '.wp-launcher-ready', 0644 );
			$admin->query( 'DROP DATABASE `' . $name . '`' ); $admin->close();
		}
	}

	/** A second WordPress install whose prefix extends ours (wp_shop_ under wp_) must never be snapshotted or restored. */
	public function test_other_installs_sharing_the_prefix_are_detected(): void {
		$tables = array( 'wp_options', 'wp_posts', 'wp_wc_orders', 'wp_actionscheduler_actions', 'wp_shop_options', 'wp_shop_posts', 'wp_shop_usermeta', 'wp_forms_options' );
		$this->assertSame( array( 'wp_shop_' ), EMCP_Update_Runtime::other_installs( $tables, 'wp_' ) );
		$this->assertSame( array(), EMCP_Update_Runtime::other_installs( array( 'wp_options', 'wp_posts', 'wp_wc_orders', 'wp_forms_options' ), 'wp_' ) );
		$this->assertSame( array(), EMCP_Update_Runtime::other_installs( array( 'wp_options', 'wp_posts', 'wp_shop_options', 'wp_shop_posts' ), 'wp_shop_' ) );
	}

	private function install_gate( string $original = "<?php echo 'SITE';" ): void {
		copy( dirname( __DIR__ ) . '/includes/cloud/safe-update-runtime.php', $this->state['dir'] . '/runtime.php' );
		copy( dirname( __DIR__ ) . '/includes/cloud/safe-update-gate.php', $this->state['dir'] . '/gate.php' );
		EMCP_Update_Runtime::write( $this->state['dir'] . '/original-config.php', $original );
		file_put_contents( $this->root . 'wp-config.php', EMCP_Update_Runtime::gate_line( $this->state['dir'] ) . $original );
		EMCP_Update_Runtime::write( $this->state['dir'] . '/state.php', $this->state );
	}
	/** A visitor request: like wp-load.php, the entry script includes wp-config.php. */
	private function visit(): string {
		file_put_contents( $this->root . 'index.php', '<?php require __DIR__ . "/wp-config.php";' );
		return $this->php( $this->root . 'index.php' );
	}
	private function php( string $script ): string {
		return (string) shell_exec( escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( $script ) . ' 2>&1' );
	}

	/** Cloud going silent before any update ran must not leave the site in maintenance past the deadline. */
	public function test_gate_reopens_an_expired_window_when_nothing_was_changed(): void {
		foreach ( array( array( 'baseline', 0 ), array( 'draining', 0 ), array( 'snapshot', 0 ) ) as list( $phase, $group ) ) {
			$this->state['phase'] = $phase; $this->state['group'] = $group; $this->state['deadline'] = time() - 1;
			$this->install_gate();
			$this->assertSame( 'SITE', $this->visit(), $phase );
			$state = EMCP_Update_Runtime::read( $this->state['dir'] . '/state.php' );
			$this->assertSame( array( 'failed', 'deadline_exceeded' ), array( $state['phase'], $state['code'] ), $phase );
			$this->assertSame( "<?php echo 'SITE';", file_get_contents( $this->root . 'wp-config.php' ), $phase );
		}
	}

	/** Once an update may have run, only an explicit restore can reopen the site. */
	public function test_gate_keeps_maintenance_after_updates_may_have_run(): void {
		foreach ( array( array( 'verify', 0 ), array( 'updating', 0 ), array( 'snapshot', 1 ), array( 'restoring', 0 ), array( 'verify_restore', 0 ), array( 'manual_recovery', 0 ) ) as list( $phase, $group ) ) {
			$this->state['phase'] = $phase; $this->state['group'] = $group; $this->state['deadline'] = time() - 1;
			$this->install_gate();
			$this->assertStringContainsString( 'Scheduled maintenance', $this->visit(), $phase );
			$this->assertSame( $phase, EMCP_Update_Runtime::read( $this->state['dir'] . '/state.php' )['phase'], $phase );
		}
	}

	/** A deleted or quarantined job folder shows the maintenance page instead of a fatal error on every request. */
	public function test_missing_job_folder_shows_maintenance_instead_of_a_fatal(): void {
		file_put_contents( $this->root . 'wp-config.php', EMCP_Update_Runtime::gate_line( $this->root . '.emcp-update-gone' ) . "<?php echo 'SITE';" );
		$out = $this->visit();
		$this->assertStringContainsString( 'Scheduled maintenance', $out );
		$this->assertStringNotContainsString( 'SITE', $out );
		$this->assertStringNotContainsString( 'Fatal', $out );
	}

	/** The plugin's own copies refuse direct requests; only the copies inside a job folder run. */
	public function test_source_files_refuse_direct_requests(): void {
		$this->assertSame( '', $this->php( dirname( __DIR__ ) . '/includes/cloud/safe-update-gate.php' ) );
		file_put_contents( $this->root . 'probe.php', '<?php require ' . var_export( dirname( __DIR__ ) . '/includes/cloud/safe-update-runtime.php', true ) . '; echo "LOADED";' );
		$this->assertSame( '', $this->php( $this->root . 'probe.php' ) );
	}

	/** A finished job keeps only an authenticated receipt: no credentials, original config, offers or snapshots. */
	public function test_finished_job_keeps_only_a_receipt(): void {
		foreach ( array( array( 'verify', 'approve', 'completed' ), array( 'verify_restore', 'release', 'rolled_back' ) ) as list( $phase, $action, $terminal ) ) {
			$this->state['phase'] = $phase; $this->state['group'] = 1; $this->state['snapshot'] = 'snapshot-1';
			$this->state['db'] = array( 'password' => 'private' );
			$this->state['groups'][1][0]['_source'] = array( 'key' => 'b/b.php', 'offer' => array( 'package' => 'https://example.com/b.zip?key=secret' ) );
			$this->install_gate();
			mkdir( $this->state['dir'] . '/snapshot-1', 0700 ); file_put_contents( $this->state['dir'] . '/snapshot-1/file-0.php', 'x' );
			$this->command( $action );
			$this->assertSame( $terminal, $this->state['phase'] );
			$stored = EMCP_Update_Runtime::read( $this->state['dir'] . '/state.php' );
			$this->assertSame( $terminal, $stored['phase'] );
			$this->assertArrayNotHasKey( 'db', $stored );
			$this->assertStringNotContainsString( 'secret', json_encode( $stored ) );
			$this->assertFileDoesNotExist( $this->state['dir'] . '/snapshot-1' );
			$this->assertFileDoesNotExist( $this->state['dir'] . '/original-config.php' );
			$this->assertFileDoesNotExist( $this->state['dir'] . '/gate.php' );
			$this->assertSame( "<?php echo 'SITE';", file_get_contents( $this->root . 'wp-config.php' ) );
			unset( $stored['cleaned'] );
			$this->state = $stored + array( 'groups' => array( array( array( 'id' => 'plugin:a' ) ), array( array( 'id' => 'plugin:b' ) ) ) );
		}
	}

	/** File restore and snapshot work stay within a per-request byte budget. */
	public function test_file_restore_is_bounded_per_request(): void {
		$this->state['phase'] = 'restoring'; $this->state['snapshot'] = 'snapshot-0'; $this->state['restore_step'] = 0; $this->state['restore_db'] = 0;
		$this->state['restore_pruned'] = true; $this->state['tables'] = array();
		mkdir( $this->state['dir'] . '/snapshot-0', 0700 );
		foreach ( array( 'a', 'b', 'c' ) as $i => $name ) {
			$this->state['files'][] = array( 'path' => $name . '.php', 'mode' => 0644, 'hash' => hash( 'sha256', $name ) );
			file_put_contents( $this->state['dir'] . '/snapshot-0/file-' . $i . '.php', EMCP_Update_Runtime::GUARD . $name );
		}
		EMCP_Update_Runtime::$tick_bytes = 1;
		$this->command( 'tick' );
		$this->assertSame( 1, $this->state['restore_step'] );
		$this->assertFileDoesNotExist( $this->root . 'b.php' );
	}

	/** Files left by an interrupted write (such as a temporary copy) are swept before the database is restored. */
	public function test_restore_sweeps_files_left_by_interrupted_writes(): void {
		$this->state['phase'] = 'restoring'; $this->state['snapshot'] = 'snapshot-0'; $this->state['restore_step'] = 0; $this->state['restore_db'] = 0;
		$this->state['restore_pruned'] = true; $this->state['files'] = array(); $this->state['tables'] = array();
		mkdir( $this->state['dir'] . '/snapshot-0', 0700 );
		mkdir( $this->root . 'wp-content/plugins/p', 0700, true );
		file_put_contents( $this->root . 'wp-content/plugins/p/p.php.0123456789ab.php', 'leftover' );
		$this->state['db'] = array( 'host' => '127.0.0.1:1', 'user' => 'x', 'password' => '', 'name' => 'x', 'prefix' => 'wp_' );
		$this->command( 'tick' );
		$this->assertFileDoesNotExist( $this->root . 'wp-content/plugins/p/p.php.0123456789ab.php' );
	}

	/**
	 * A restore killed between a committed insert and the journal save resumes from the
	 * rows actually present, never duplicating or skipping any. Needs a scratch MySQL server:
	 * EMCP_SAFE_UPDATE_TEST_DB="host|user|password".
	 */
	public function test_interrupted_table_restore_resumes_exactly(): void {
		$conf = getenv( 'EMCP_SAFE_UPDATE_TEST_DB' );
		if ( ! $conf || ! extension_loaded( 'mysqli' ) ) { $this->markTestSkipped( 'EMCP_SAFE_UPDATE_TEST_DB not set.' ); }
		list( $host, $user, $pass ) = array_pad( explode( '|', $conf ), 3, '' );
		$name = 'emcp_su_' . bin2hex( random_bytes( 4 ) );
		mysqli_report( MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT );
		$admin = new mysqli( $host, $user, $pass );
		$admin->query( 'CREATE DATABASE `' . $name . '`' );
		try {
			$create = 'CREATE TABLE `wp_t` (`id` bigint NOT NULL AUTO_INCREMENT, `v` longblob, PRIMARY KEY (`id`)) ENGINE=InnoDB';
			$rows = array();
			for ( $i = 0; $i < 25; $i++ ) { $rows[] = EMCP_Update_Runtime::encode_row( array( 'id' => (string) $i, 'v' => 0 === $i % 5 ? null : random_bytes( 40 ) . "'\\" ) ); }
			$this->state['db'] = array( 'host' => $host, 'user' => $user, 'password' => $pass, 'name' => $name, 'prefix' => 'wp_' );
			$this->state['phase'] = 'restoring'; $this->state['snapshot'] = 'snapshot-0'; $this->state['restore_step'] = 0; $this->state['restore_db'] = 0;
			$this->state['restore_pruned'] = true; $this->state['restore_swept'] = true; $this->state['files'] = array(); $this->state['tables'] = array( 'wp_t' );
			mkdir( $this->state['dir'] . '/snapshot-0', 0700 );
			EMCP_Update_Runtime::write( $this->state['dir'] . '/snapshot-0/table-0.php', array( 'create' => $create, 'rows' => $rows ) );
			$this->state['table_hashes'] = array( hash_file( 'sha256', $this->state['dir'] . '/snapshot-0/table-0.php' ) );
			EMCP_Update_Runtime::$tick_rows = 7;
			$this->command( 'tick' );
			$saved = $this->state;
			$this->command( 'tick' ); // Committed, then "killed": the journal never records it.
			$this->state = $saved;
			for ( $n = 0; $n < 10 && 'restoring' === $this->state['phase']; $n++ ) { $this->command( 'tick' ); }
			$this->assertSame( 'verify_restore', $this->state['phase'] );
			$db = new mysqli( $host, $user, $pass, $name );
			$got = array();
			foreach ( $db->query( 'SELECT * FROM `wp_t` ORDER BY id' ) as $row ) { $got[] = EMCP_Update_Runtime::encode_row( $row ); }
			$this->assertSame( $rows, $got );
			$db->close();
		} finally {
			$admin->query( 'DROP DATABASE `' . $name . '`' );
			$admin->close();
		}
	}
}

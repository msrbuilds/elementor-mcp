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
}

<?php
/**
 * Unit tests for F-015: plugin uninstall cleanup.
 *
 * Architecture note (updated for v1.6.1+ / v2.0 rename)
 * ----------------------------------------------------
 * `uninstall.php` was REMOVED in v1.6.1 — Freemius rejects builds that contain
 * it. Cleanup now runs via the Freemius `after_uninstall` action wired in the
 * bootstrap to EMCP_Tools_Uninstaller::run() (includes/class-uninstaller.php).
 *
 * The uninstaller removes plugin-OWNED data (options, transients, dismissal
 * user-meta) and the generated executable PHP for custom widgets + PHP snippets
 * (which must never survive uninstall). It intentionally does NOT delete user
 * PAGE content (_elementor_data) or brand-kit backups — that is the user's data
 * and is treated as recoverable, not orphaned.
 *
 * @package EMCP_Tools\Tests\Security
 * @since   1.0.0
 */

namespace EMCP_Tools\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers EMCP_Tools_Uninstaller
 */
class F015UninstallTest extends TestCase {

	/** @var string Absolute path to the uninstaller class. */
	private string $uninstaller_file;

	/** @var string Source of the uninstaller class. */
	private string $uninstaller_src;

	protected function setUp(): void {
		parent::setUp();
		$this->uninstaller_file = dirname( __DIR__, 3 ) . '/includes/class-uninstaller.php';
		$this->uninstaller_src  = file_exists( $this->uninstaller_file )
			? file_get_contents( $this->uninstaller_file )
			: '';
	}

	/**
	 * @test
	 * uninstall.php must NOT exist — Freemius rejects builds containing it.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_php_is_absent(): void {
		$this->assertFileDoesNotExist(
			dirname( __DIR__, 3 ) . '/uninstall.php',
			'uninstall.php must NOT exist — Freemius rejects it (removed in v1.6.1). ' .
			'Cleanup runs via EMCP_Tools_Uninstaller on the Freemius after_uninstall hook.'
		);
	}

	/**
	 * @test
	 * The uninstaller class + run() entry point must exist.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstaller_class_and_entry_point_exist(): void {
		$this->assertFileExists( $this->uninstaller_file, 'includes/class-uninstaller.php must exist.' );
		$this->assertStringContainsString( 'class EMCP_Tools_Uninstaller', $this->uninstaller_src );
		$this->assertStringContainsString( 'function run', $this->uninstaller_src );
	}

	/**
	 * @test
	 * The uninstaller must delete plugin-owned options via delete_option().
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstaller_deletes_plugin_options(): void {
		$this->assertStringContainsString( 'delete_option', $this->uninstaller_src );
		$this->assertStringContainsString( 'emcp_tools_disabled_tools', $this->uninstaller_src );
	}

	/**
	 * @test
	 * Generated executable PHP (custom widgets + PHP snippets) must be removed —
	 * it must never survive an uninstall.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstaller_removes_generated_executable_php(): void {
		$this->assertStringContainsString(
			'uninstall_cleanup',
			$this->uninstaller_src,
			'F-015: the uninstaller must call the widget/snippet store uninstall_cleanup() ' .
			'so generated PHP in uploads is deleted.'
		);
	}

	/**
	 * @test
	 * User PAGE content must be PRESERVED on uninstall — deleting all
	 * _elementor_data would destroy the user's pages.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstaller_preserves_user_page_content(): void {
		$this->assertStringNotContainsString(
			"delete_post_meta_by_key( '_elementor_data'",
			$this->uninstaller_src,
			'Uninstall must NOT delete _elementor_data — that is user page content, not plugin data.'
		);
	}
}

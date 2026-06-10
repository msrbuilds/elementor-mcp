<?php
/**
 * Unit tests for F-015: plugin uninstall cleanup.
 *
 * Architecture note (updated for v1.6.1+)
 * ---------------------------------------
 * `uninstall.php` was REMOVED in v1.6.1 — Freemius rejects builds that contain
 * it. Cleanup now runs via the Freemius `after_uninstall` action wired in the
 * main plugin file to elementor_mcp_after_uninstall() (elementor-mcp.php).
 *
 * The cleanup removes plugin-OWNED data (options, transients, dismissal
 * user-meta) and the generated executable PHP for custom widgets + PHP snippets
 * (which must never survive uninstall, via Elementor_MCP_Widget_Store::uninstall_cleanup()).
 * It intentionally does NOT delete user PAGE content (_elementor_data) or
 * brand-kit backups — that is the user's data and is treated as recoverable,
 * not orphaned.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers elementor_mcp_after_uninstall
 */
class F015UninstallTest extends TestCase {

	/** @var string Absolute path to the main plugin file (hosts the cleanup callback). */
	private string $cleanup_file;

	/** @var string Source of the main plugin file. */
	private string $cleanup_src;

	protected function setUp(): void {
		parent::setUp();
		$this->cleanup_file = dirname( __DIR__, 3 ) . '/elementor-mcp.php';
		$this->cleanup_src  = file_exists( $this->cleanup_file )
			? file_get_contents( $this->cleanup_file )
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
			'Cleanup runs via elementor_mcp_after_uninstall() on the Freemius after_uninstall hook.'
		);
	}

	/**
	 * @test
	 * The uninstall cleanup callback must exist.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_cleanup_callback_exists(): void {
		$this->assertFileExists( $this->cleanup_file, 'elementor-mcp.php must exist at the plugin root.' );
		$this->assertStringContainsString( 'function elementor_mcp_after_uninstall', $this->cleanup_src );
	}

	/**
	 * @test
	 * The cleanup must delete plugin-owned options via delete_option().
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_deletes_plugin_options(): void {
		$this->assertStringContainsString( 'delete_option', $this->cleanup_src );
		$this->assertStringContainsString( 'elementor_mcp_disabled_tools', $this->cleanup_src );
	}

	/**
	 * @test
	 * Generated executable PHP (custom widgets + PHP snippets) must be removed —
	 * it must never survive an uninstall.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_removes_generated_executable_php(): void {
		$this->assertStringContainsString(
			'uninstall_cleanup',
			$this->cleanup_src,
			'F-015: the uninstall cleanup must call the widget/snippet store uninstall_cleanup() ' .
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
	public function test_uninstall_preserves_user_page_content(): void {
		$this->assertStringNotContainsString(
			"delete_post_meta_by_key( '_elementor_data'",
			$this->cleanup_src,
			'Uninstall must NOT delete _elementor_data — that is user page content, not plugin data.'
		);
	}
}

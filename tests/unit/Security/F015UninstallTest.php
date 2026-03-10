<?php
/**
 * Unit tests for F-015: Incomplete cleanup on plugin uninstall.
 *
 * Finding:   F-015 (Low)
 * File:      uninstall.php:17–18
 *
 * Vulnerability description
 * -------------------------
 * uninstall.php deletes only 2 plugin options:
 *   delete_option( 'elementor_mcp_settings' );
 *   delete_option( 'elementor_mcp_enabled_tools' );
 *
 * It does NOT clean up:
 *   - elementor_library CPT posts created by MCP tools
 *   - elementor_snippet CPT posts (Custom Code snippets)
 *   - SVG media attachments uploaded by add-svg-icon / add-stock-image
 *   - _elementor_data, _elementor_css post meta written by all write tools
 *   - elementor_mcp_* post meta keys
 *
 * Impact: After uninstallation, these orphaned database records remain,
 * wasting storage and potentially exposing sensitive page content.
 *
 * TDD contract
 * ------------
 *   BEFORE the fix → uninstall.php source does not contain the cleanup calls.
 *   AFTER  the fix → all required cleanup calls are present.
 *
 * Fix: Add WP_Query loops to delete plugin-created posts by CPT;
 *      add delete_post_meta_by_key() for plugin-specific meta keys.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers uninstall.php
 */
class F015UninstallTest extends TestCase {

	/** @var string Absolute path to uninstall.php. */
	private string $uninstall_file;

	/** @var string Source content of uninstall.php. */
	private string $uninstall_src;

	protected function setUp(): void {
		parent::setUp();
		$this->uninstall_file = dirname( __DIR__, 3 ) . '/uninstall.php';
		if ( file_exists( $this->uninstall_file ) ) {
			$this->uninstall_src = file_get_contents( $this->uninstall_file );
		} else {
			$this->uninstall_src = '';
		}
	}

	// -------------------------------------------------------------------------
	// Tests: uninstall.php existence and basic structure
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * uninstall.php must exist at the plugin root.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_php_exists(): void {
		$this->assertFileExists(
			$this->uninstall_file,
			'F-015: uninstall.php must exist. WordPress executes it on plugin deletion.'
		);
	}

	/**
	 * @test
	 * uninstall.php must guard against direct execution via WP_UNINSTALL_PLUGIN check.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_php_has_direct_execution_guard(): void {
		if ( ! file_exists( $this->uninstall_file ) ) {
			$this->markTestSkipped( 'uninstall.php not found.' );
		}

		$this->assertMatchesRegularExpression(
			'/WP_UNINSTALL_PLUGIN/',
			$this->uninstall_src,
			'uninstall.php must check WP_UNINSTALL_PLUGIN to prevent direct execution.'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: required cleanup calls must be present (FAIL before fix)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-015 — uninstall.php must clean up elementor_library CPT posts.
	 *
	 * The create-page and add-widget tools create elementor_library posts.
	 * These must be deleted on uninstall.
	 *
	 * This test FAILS before the fix (no WP_Query/cleanup for CPT posts).
	 * After the fix it PASSES.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_cleans_elementor_library_posts(): void {
		if ( ! file_exists( $this->uninstall_file ) ) {
			$this->markTestSkipped( 'uninstall.php not found.' );
		}

		// The fix must use WP_Query or $wpdb to fetch and delete elementor_library posts.
		$has_library_cleanup = (bool) preg_match(
			'/elementor_library/',
			$this->uninstall_src
		);

		$this->assertTrue(
			$has_library_cleanup,
			'F-015: uninstall.php must delete elementor_library CPT posts created by ' .
			'MCP tools. Fix: add WP_Query loop deleting all elementor_library posts, ' .
			'or use $wpdb->delete() on wp_posts WHERE post_type = "elementor_library".'
		);
	}

	/**
	 * @test
	 * F-015 — uninstall.php must remove plugin-specific post meta keys.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_calls_delete_post_meta_by_key(): void {
		if ( ! file_exists( $this->uninstall_file ) ) {
			$this->markTestSkipped( 'uninstall.php not found.' );
		}

		$this->assertStringContainsString(
			'delete_post_meta_by_key',
			$this->uninstall_src,
			'F-015: uninstall.php must call delete_post_meta_by_key() to remove ' .
			'plugin-specific post meta (_elementor_data, _elementor_css, etc.) ' .
			'written by MCP tool operations.'
		);
	}

	/**
	 * @test
	 * F-015 — uninstall.php deletes both known plugin options.
	 *
	 * This verifies the options that DO exist in the current uninstall.php.
	 * These should pass both before and after the fix.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_deletes_plugin_options(): void {
		if ( ! file_exists( $this->uninstall_file ) ) {
			$this->markTestSkipped( 'uninstall.php not found.' );
		}

		$this->assertStringContainsString(
			'elementor_mcp_settings',
			$this->uninstall_src,
			'uninstall.php must delete the elementor_mcp_settings option.'
		);

		$this->assertStringContainsString(
			'elementor_mcp_enabled_tools',
			$this->uninstall_src,
			'uninstall.php must delete the elementor_mcp_enabled_tools option.'
		);
	}

	/**
	 * @test
	 * F-015 — delete_option calls are present for plugin options.
	 *
	 * @group security
	 * @group f-015
	 */
	public function test_uninstall_uses_delete_option(): void {
		if ( ! file_exists( $this->uninstall_file ) ) {
			$this->markTestSkipped( 'uninstall.php not found.' );
		}

		$this->assertStringContainsString(
			'delete_option',
			$this->uninstall_src,
			'uninstall.php must use delete_option() to remove plugin options.'
		);
	}
}

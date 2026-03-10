<?php
/**
 * Unit tests for F-019 and F-020: admin class security issues.
 *
 * Findings:
 *   F-019 (Low) — Admin tool list is hardcoded and drifts from ability registry
 *   F-020 (Low) — Absolute server filesystem path exposed to admin JavaScript
 *
 * File:      includes/admin/class-admin.php:157 (F-020), :326–849 (F-019)
 *
 * Vulnerability descriptions
 * --------------------------
 * F-019: get_all_tools() in class-admin.php is a manually-maintained PHP array
 * listing ~96 tools. It is NOT derived from the ability registry. As of the
 * audit, 10 tools documented in the admin UI are absent from CLAUDE.md, and
 * the "92 tools" count is wrong. Every release creates a drift risk where the
 * admin UI shows tools that don't exist or hides tools that do.
 *
 * F-020: wp_localize_script() in class-admin.php includes:
 *   ELEMENTOR_MCP_DIR . 'bin' . DIRECTORY_SEPARATOR . 'mcp-proxy.mjs'
 * The full server filesystem path (e.g. /var/www/html/wp-content/plugins/...)
 * is emitted as a JavaScript string in the admin page HTML, visible to any
 * logged-in admin. This aids server reconnaissance.
 *
 * TDD contract
 * ------------
 *   F-019 BEFORE fix → hardcoded array in source; no dynamic registry derivation.
 *   F-019 AFTER fix  → tool list is derived from registry at render time, or
 *                       a CI check compares the two.
 *
 *   F-020 BEFORE fix → full ELEMENTOR_MCP_DIR path present in localize_script data.
 *   F-020 AFTER fix  → only filename (not full path) localized to JS.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_Admin
 */
class F019F020AdminTest extends TestCase {

	/** @var string Absolute path to class-admin.php. */
	private string $admin_file;

	/** @var string Source content. */
	private string $admin_src;

	protected function setUp(): void {
		parent::setUp();
		$this->admin_file = dirname( __DIR__, 3 ) . '/includes/admin/class-admin.php';
		if ( file_exists( $this->admin_file ) ) {
			$this->admin_src = file_get_contents( $this->admin_file );
		} else {
			$this->admin_src = '';
		}
	}

	// =========================================================================
	// F-020: Full server path exposed to admin JavaScript
	// =========================================================================

	/**
	 * @test
	 * F-020 — class-admin.php must not expose ELEMENTOR_MCP_DIR in localized JS data.
	 *
	 * The full filesystem path should never be sent to the browser.
	 * Only the filename ('mcp-proxy.mjs') should be localized.
	 *
	 * This test FAILS before the fix (ELEMENTOR_MCP_DIR is concatenated in the
	 * wp_localize_script data). After the fix it PASSES.
	 *
	 * @group security
	 * @group f-020
	 */
	public function test_localized_script_does_not_use_elementor_mcp_dir_for_proxy_path(): void {
		if ( ! file_exists( $this->admin_file ) ) {
			$this->markTestSkipped( 'class-admin.php not found.' );
		}

		// The bug: ELEMENTOR_MCP_DIR concatenated with the proxy filename
		// in the wp_localize_script data array.
		$has_full_path_in_localize = (bool) preg_match(
			'/wp_localize_script[^;]*ELEMENTOR_MCP_DIR[^;]*mcp-proxy\.mjs/s',
			$this->admin_src
		);

		$this->assertFalse(
			$has_full_path_in_localize,
			'F-020: wp_localize_script must not include ELEMENTOR_MCP_DIR in the proxy path ' .
			'localized to JavaScript. Doing so exposes the full server filesystem path ' .
			'(e.g. /var/www/html/wp-content/plugins/elementor-mcp/bin/mcp-proxy.mjs) to the browser. ' .
			'Fix: localize only the filename ("mcp-proxy.mjs") and construct the full path server-side.'
		);
	}

	/**
	 * @test
	 * F-020 — ELEMENTOR_MCP_DIR must not appear inside any wp_localize_script call.
	 *
	 * @group security
	 * @group f-020
	 */
	public function test_elementor_mcp_dir_not_passed_to_localize_script(): void {
		if ( ! file_exists( $this->admin_file ) ) {
			$this->markTestSkipped( 'class-admin.php not found.' );
		}

		// Count how many wp_localize_script calls reference ELEMENTOR_MCP_DIR.
		$matches = [];
		preg_match_all(
			'/wp_localize_script\s*\([^;]*ELEMENTOR_MCP_DIR[^;]*/s',
			$this->admin_src,
			$matches
		);

		$this->assertCount(
			0,
			$matches[0],
			'F-020: ELEMENTOR_MCP_DIR must not be used inside wp_localize_script(). ' .
			'Found ' . count( $matches[0] ) . ' occurrence(s) at class-admin.php:157. ' .
			'Fix: expose only the filename, not the full server path.'
		);
	}

	/**
	 * @test
	 * F-020 — PHP constant concatenation for paths is safe when NOT localized.
	 *
	 * This verifies that ELEMENTOR_MCP_DIR may still be used for server-side
	 * file operations — only its exposure to JavaScript is the bug.
	 *
	 * @group security
	 * @group f-020
	 */
	public function test_elementor_mcp_dir_constant_is_defined(): void {
		// In the bootstrap, ELEMENTOR_MCP_DIR is defined as the plugin root.
		$this->assertTrue(
			defined( 'ELEMENTOR_MCP_DIR' ),
			'ELEMENTOR_MCP_DIR must be defined (used for server-side path construction).'
		);

		$dir = ELEMENTOR_MCP_DIR;
		$this->assertIsString( $dir, 'ELEMENTOR_MCP_DIR must be a string.' );
		$this->assertNotEmpty( $dir, 'ELEMENTOR_MCP_DIR must not be empty.' );
	}

	// =========================================================================
	// F-019: Hardcoded tool list drifts from ability registry
	// =========================================================================

	/**
	 * @test
	 * F-019 — class-admin.php contains get_all_tools() or equivalent.
	 *
	 * Confirms the method is present so we can test its implementation.
	 *
	 * @group security
	 * @group f-019
	 */
	public function test_get_all_tools_method_exists_in_admin(): void {
		if ( ! file_exists( $this->admin_file ) ) {
			$this->markTestSkipped( 'class-admin.php not found.' );
		}

		$this->assertMatchesRegularExpression(
			'/function\s+get_all_tools/',
			$this->admin_src,
			'F-019: class-admin.php must contain get_all_tools() method.'
		);
	}

	/**
	 * @test
	 * F-019 — get_all_tools() must NOT be a hardcoded static array.
	 *
	 * A hardcoded array means it cannot stay in sync with the registry.
	 * The fix is to derive the list from registered abilities dynamically.
	 *
	 * This test FAILS before the fix (hardcoded return array detected).
	 * After the fix it PASSES.
	 *
	 * @group security
	 * @group f-019
	 */
	public function test_get_all_tools_is_not_a_static_hardcoded_array(): void {
		if ( ! file_exists( $this->admin_file ) ) {
			$this->markTestSkipped( 'class-admin.php not found.' );
		}

		// Detect a hardcoded return statement with a very large array (>20 entries).
		// We look for a long chain of array entries inside the get_all_tools function.
		// The function spans lines 326–849, which is ~520 lines — indicative of a huge hardcoded array.

		// Extract the approximate size of get_all_tools.
		$start = strpos( $this->admin_src, 'function get_all_tools' );
		if ( $start === false ) {
			$this->markTestSkipped( 'get_all_tools() not found in class-admin.php.' );
		}

		// Find the function body (rough approximation via line count).
		$after_function = substr( $this->admin_src, $start );
		$line_count = substr_count( substr( $after_function, 0, 20000 ), "\n" );

		// A function >100 lines containing only a static array is suspect.
		// After the fix, it should call a registry method instead.
		$has_registry_call = (bool) preg_match(
			'/\$this\->.*abilities|wp_register_ability|ability_registrar|registry/i',
			substr( $after_function, 0, 20000 )
		);

		$this->assertTrue(
			$has_registry_call,
			'F-019: get_all_tools() must derive the tool list from the registered abilities ' .
			'(via the ability registry or a comparable dynamic source) rather than a ' .
			'hardcoded static array. The current hardcoded list (' .
			$line_count . ' lines) drifts on every release. ' .
			'Fix: replace with a call to the ability registrar or add a CI comparison check.'
		);
	}
}

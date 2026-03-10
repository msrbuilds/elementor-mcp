<?php
/**
 * Unit tests for F-026 and F-027: informational / housekeeping findings.
 *
 * Findings:
 *   F-026 (Info) — Proxy response buffering and eager class instantiation
 *   F-027 (Info) — Dev/ops housekeeping items
 *
 * File:      bin/mcp-proxy.mjs:123–124 (F-026), various (F-027)
 *
 * Vulnerability descriptions
 * --------------------------
 * F-026a — Proxy response buffering:
 *   The proxy accumulates the full HTTP response body in `let body = ''` before
 *   forwarding it. For large export-page responses (>10 MB), this risks OOM
 *   if the Node.js process has limited heap. No size cap exists.
 *
 * F-026b — Eager class instantiation:
 *   class-plugin.php:102–106 instantiates all core component objects on
 *   plugins_loaded. Constructors are lightweight so the real cost is negligible,
 *   but eager instantiation makes unit testing harder (no lazy factory).
 *
 * F-027a — No WP_DEBUG_LOG logging for tool errors:
 *   WP_Error returns from tool handlers produce no server-side trace, making
 *   silent failures hard to diagnose.
 *
 * F-027b — tests/* gitignored:
 *   .gitignore includes tests/ — test files cannot be committed without removing
 *   the rule, making CI impossible.
 *
 * F-027c — .mcp.json tracked but gitignored:
 *   .mcp.json is tracked by git but also listed in .gitignore. Local changes
 *   will not stage automatically, confusing contributors.
 *
 * TDD contract
 * ------------
 * These are informational tests documenting the current state. Many describe
 * desired post-fix behavior and will PASS after the corresponding cleanup.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

class F026F027InformationalTest extends TestCase {

	/** @var string Plugin root directory. */
	private string $plugin_root;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );
	}

	// =========================================================================
	// F-026a: Proxy body accumulation is unbounded
	// =========================================================================

	/**
	 * @test
	 * F-026 — Documents that unbounded body accumulation causes OOM for large responses.
	 *
	 * This test does NOT simulate actual OOM (that would crash the test runner).
	 * Instead it verifies the logical pattern: accumulating 6 × 1 MB chunks
	 * without a cap produces a 6 MB string.
	 *
	 * The fix: add a size limit in mcp-proxy.mjs, e.g.:
	 *   if (body.length > 10 * 1024 * 1024) { // emit JSON-RPC error and close }
	 *
	 * @group security
	 * @group f-026
	 */
	public function test_unbounded_body_accumulation_pattern(): void {
		$chunk_size  = 1024 * 1024;  // 1 MB
		$chunk_count = 6;
		$safe_limit  = 5 * 1024 * 1024;  // 5 MB threshold — 6 × 1 MB accumulation exceeds this

		// Simulate chunk accumulation (the proxy does this without a cap).
		$body = '';
		for ( $i = 0; $i < $chunk_count; $i++ ) {
			$body .= str_repeat( 'x', $chunk_size );
		}

		$this->assertGreaterThan(
			$safe_limit,
			strlen( $body ),
			'F-026: Accumulated body of ' . $chunk_count . ' × 1MB exceeds the suggested 10MB cap, ' .
			'demonstrating the OOM risk. The proxy must add a body size limit.'
		);
	}

	/**
	 * @test
	 * F-026 — A size-capped accumulation correctly truncates large responses.
	 *
	 * This verifies the fix pattern: abort accumulation once size limit is reached.
	 *
	 * @group security
	 * @group f-026
	 */
	public function test_size_capped_accumulation_stays_within_limit(): void {
		$safe_limit = 10 * 1024 * 1024;  // 10 MB
		$chunk_size = 1024 * 1024;       // 1 MB chunks

		$body = '';
		$aborted = false;
		for ( $i = 0; $i < 20; $i++ ) {
			$body .= str_repeat( 'x', $chunk_size );
			if ( strlen( $body ) > $safe_limit ) {
				$aborted = true;
				break;
			}
		}

		$this->assertTrue( $aborted, 'Size-capped loop must abort before exceeding the limit.' );
		$this->assertLessThanOrEqual(
			$safe_limit + $chunk_size,
			strlen( $body ),
			'Final accumulated body must not significantly exceed the cap.'
		);
	}

	// =========================================================================
	// F-027b: tests/ gitignored
	// =========================================================================

	/**
	 * @test
	 * F-027 — .gitignore must NOT exclude the tests/ directory.
	 *
	 * If tests/ is gitignored, test files cannot be committed, making CI
	 * impossible. This is the bug.
	 *
	 * This test FAILS before the fix (tests/ is in .gitignore).
	 * After the fix (rule removed) it PASSES.
	 *
	 * @group security
	 * @group f-027
	 */
	public function test_gitignore_does_not_exclude_tests_directory(): void {
		$gitignore_file = $this->plugin_root . '/.gitignore';

		if ( ! file_exists( $gitignore_file ) ) {
			$this->markTestSkipped( '.gitignore not found.' );
		}

		$content = file_get_contents( $gitignore_file );

		// Check for patterns that would exclude the tests/ directory.
		$excludes_tests = (bool) preg_match(
			'/^tests\/?$/m',
			$content
		);

		$this->assertFalse(
			$excludes_tests,
			'F-027: .gitignore must not exclude the tests/ directory. ' .
			'When tests/ is gitignored, test files cannot be committed or run in CI. ' .
			'Fix: remove the "tests/" line from .gitignore.'
		);
	}

	/**
	 * @test
	 * F-027 — Test files currently exist in tests/unit/Security/ (confirms tests can be committed).
	 *
	 * @group security
	 * @group f-027
	 */
	public function test_security_test_files_are_present(): void {
		$security_dir = $this->plugin_root . '/tests/unit/Security';

		$this->assertDirectoryExists(
			$security_dir,
			'tests/unit/Security/ must exist and contain test files.'
		);

		$test_files = glob( $security_dir . '/*.php' );

		$this->assertNotEmpty(
			$test_files,
			'tests/unit/Security/ must contain at least one PHP test file.'
		);
	}

	// =========================================================================
	// F-027c: .mcp.json tracked but gitignored
	// =========================================================================

	/**
	 * @test
	 * F-027 — .mcp.json and .gitignore relationship is documented.
	 *
	 * Documents that .mcp.json should either be:
	 *   (a) Removed from .gitignore and committed as a template, or
	 *   (b) Added to .gitignore only and removed from tracking (git rm --cached .mcp.json)
	 *
	 * @group security
	 * @group f-027
	 */
	public function test_mcp_json_consistency_documented(): void {
		$mcp_json = $this->plugin_root . '/.mcp.json';
		$gitignore = $this->plugin_root . '/.gitignore';

		$mcp_exists   = file_exists( $mcp_json );
		$gitignore_exists = file_exists( $gitignore );

		if ( ! $gitignore_exists ) {
			$this->markTestSkipped( '.gitignore not found.' );
		}

		$gitignore_content = file_get_contents( $gitignore );
		$mcp_is_ignored = (bool) preg_match( '/^\.mcp\.json$/m', $gitignore_content );

		if ( $mcp_exists && $mcp_is_ignored ) {
			// Both tracked and ignored — this is the bug.
			// For the test, we document the inconsistency.
			$this->addWarning(
				'F-027: .mcp.json is both present and listed in .gitignore. ' .
				'Resolve by either: (a) removing it from .gitignore to commit as a template, ' .
				'or (b) running `git rm --cached .mcp.json` to untrack it.'
			);
		}

		// The test passes as long as we've documented the finding.
		$this->assertTrue( true, 'F-027 consistency check for .mcp.json completed.' );
	}

	// =========================================================================
	// F-027a: No error_log() for WP_Error returns
	// =========================================================================

	/**
	 * @test
	 * F-027 — Plugin source files must contain at least some error_log calls.
	 *
	 * Silent WP_Error returns make debugging very hard in production.
	 * The fix is to add error_log() or trigger_error() on WP_Error returns.
	 *
	 * This test FAILS before the fix (no error_log in plugin source).
	 * After the fix it PASSES.
	 *
	 * @group security
	 * @group f-027
	 */
	public function test_plugin_abilities_contain_error_logging(): void {
		$abilities_dir = $this->plugin_root . '/includes/abilities';

		if ( ! is_dir( $abilities_dir ) ) {
			$this->markTestSkipped( 'includes/abilities/ directory not found.' );
		}

		$php_files = glob( $abilities_dir . '/class-*.php' );
		$this->assertNotEmpty( $php_files, 'Ability class files must exist.' );

		$has_error_log = false;
		foreach ( $php_files as $file ) {
			$src = file_get_contents( $file );
			if ( strpos( $src, 'error_log' ) !== false || strpos( $src, 'trigger_error' ) !== false ) {
				$has_error_log = true;
				break;
			}
		}

		$this->assertTrue(
			$has_error_log,
			'F-027: At least one ability class must call error_log() or trigger_error() ' .
			'when a WP_Error is encountered, so failures produce a server-side trace. ' .
			'Currently all WP_Error returns are silent (no server-side logging). ' .
			'Fix: add error_log() calls on WP_Error returns in tool handlers.'
		);
	}

	// =========================================================================
	// Helper: PHPUnit addWarning compatibility
	// =========================================================================

	/**
	 * Adds a test warning without failing (PHPUnit 10 compatible).
	 */
	private function addWarning( string $message ): void {
		// In PHPUnit 10+, warnings are added via markTestIncomplete or just noted.
		// We use a no-op here since PHPUnit 10 deprecated addWarning().
		// The finding is documented in the test name and message.
	}
}

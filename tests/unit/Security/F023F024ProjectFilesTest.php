<?php
/**
 * Unit tests for F-023 and F-024: Documentation inaccuracies and missing project files.
 *
 * Findings:
 *   F-023 (Low) — CLAUDE.md documentation inaccuracies
 *   F-024 (Low) — Missing CONTRIBUTING.md; composer.lock untracked
 *
 * Vulnerability descriptions
 * --------------------------
 * F-023: CLAUDE.md contains several inaccuracies:
 *   (a) Claims "92 tools" — actual count is ≥96 with Pro, ≥101 with Pro+WC
 *   (b) Layout section lists 4 tools; code registers 10 (missing 6)
 *   (c) PHP 7.4 and WP 6.7 minimum version claims are wrong (see F-006, F-007)
 *   (d) 5 WooCommerce tools are undocumented
 *
 * F-024: No CONTRIBUTING.md exists, meaning contributors have no guidance on:
 *   - Setup, test-running, or workflow
 *   - PHPUnit version requirements
 *   - Coding standards
 *
 * TDD contract
 * ------------
 *   F-023 BEFORE fix → CLAUDE.md contains wrong version claims.
 *   F-023 AFTER fix  → CLAUDE.md accurately states PHP 8.0+ and WP 6.9+.
 *
 *   F-024 BEFORE fix → CONTRIBUTING.md does not exist.
 *   F-024 AFTER fix  → CONTRIBUTING.md exists with setup instructions.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

class F023F024ProjectFilesTest extends TestCase {

	/** @var string Plugin root directory. */
	private string $plugin_root;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_root = dirname( __DIR__, 3 );
	}

	// =========================================================================
	// F-023: CLAUDE.md inaccuracies
	// =========================================================================

	/**
	 * @test
	 * F-023 — CLAUDE.md must not claim PHP 7.4 minimum.
	 *
	 * CLAUDE.md is used to orient AI assistants working on the plugin.
	 * Incorrect version information leads to testing on wrong PHP versions
	 * and incorrect dependency decisions.
	 *
	 * This test FAILS while CLAUDE.md still says "7.4".
	 * After the fix it PASSES.
	 *
	 * @group security
	 * @group f-023
	 */
	public function test_claude_md_does_not_claim_php_74_minimum(): void {
		$claude_file = $this->plugin_root . '/CLAUDE.md';

		if ( ! file_exists( $claude_file ) ) {
			$this->markTestSkipped( 'CLAUDE.md not found.' );
		}

		$content = file_get_contents( $claude_file );

		$this->assertDoesNotMatchRegularExpression(
			'/PHP\s*>=?\s*7\.4|Requires PHP:\s*7\.4|PHP 7\.4\+/i',
			$content,
			'F-023: CLAUDE.md must not reference PHP 7.4 as the minimum requirement. ' .
			'The plugin uses PHP 8.0+ functions (str_contains etc.) — see F-006. ' .
			'Fix: update CLAUDE.md to state "PHP >= 8.0".'
		);
	}

	/**
	 * @test
	 * F-023 — CLAUDE.md must not claim WordPress 6.7 minimum.
	 *
	 * WP 6.7 does not have wp_register_ability() — the plugin is non-functional on it.
	 *
	 * @group security
	 * @group f-023
	 */
	public function test_claude_md_does_not_claim_wp_67_minimum(): void {
		$claude_file = $this->plugin_root . '/CLAUDE.md';

		if ( ! file_exists( $claude_file ) ) {
			$this->markTestSkipped( 'CLAUDE.md not found.' );
		}

		$content = file_get_contents( $claude_file );

		$this->assertDoesNotMatchRegularExpression(
			'/WordPress\s*>=?\s*6\.[0-8]\b|Requires at least:\s*6\.[0-8]/i',
			$content,
			'F-023: CLAUDE.md must not reference WordPress 6.7 or 6.8 as the minimum. ' .
			'wp_register_ability() requires WP 6.9+ — see F-007. ' .
			'Fix: update CLAUDE.md to state "WordPress >= 6.9".'
		);
	}

	/**
	 * @test
	 * F-023 — CLAUDE.md exists at the plugin root.
	 *
	 * @group security
	 * @group f-023
	 */
	public function test_claude_md_exists(): void {
		$this->assertFileExists(
			$this->plugin_root . '/CLAUDE.md',
			'CLAUDE.md must exist at the plugin root.'
		);
	}

	/**
	 * @test
	 * F-023 — CLAUDE.md must acknowledge that the tool count exceeds 92.
	 *
	 * The admin UI and documentation should not advertise a lower tool count
	 * than is actually registered, as this creates false expectations for users.
	 *
	 * This test FAILS before the fix (CLAUDE.md still says "92 tools").
	 * After the fix it PASSES.
	 *
	 * @group security
	 * @group f-023
	 */
	public function test_claude_md_does_not_claim_exactly_92_tools_as_total(): void {
		$claude_file = $this->plugin_root . '/CLAUDE.md';

		if ( ! file_exists( $claude_file ) ) {
			$this->markTestSkipped( 'CLAUDE.md not found.' );
		}

		$content = file_get_contents( $claude_file );

		// After the fix, the count should be updated to 96+ or expressed as a range.
		// We check that the inaccurate claim "92 MCP tools" is gone.
		// (The fix could say "96+ tools", "92 core + Pro tools", etc.)
		$has_outdated_92_claim = (bool) preg_match(
			'/\b92\s+MCP\s+tools\b/i',
			$content
		);

		$this->assertFalse(
			$has_outdated_92_claim,
			'F-023: CLAUDE.md must not claim exactly "92 MCP tools" — the actual count ' .
			'is ≥96 with Elementor Pro active. Fix: update the tool count or express it ' .
			'as "92+ tools" / "96 tools with Pro" etc.'
		);
	}

	// =========================================================================
	// F-024: Missing CONTRIBUTING.md
	// =========================================================================

	/**
	 * @test
	 * F-024 — CONTRIBUTING.md must exist at the plugin root.
	 *
	 * Without CONTRIBUTING.md, contributors have no guidance on:
	 * - How to install dependencies (composer install, PHPUnit)
	 * - How to run tests (phpunit --group security)
	 * - Coding standards (WPCS via PHPCS)
	 * - PR workflow and review process
	 *
	 * This test FAILS before the fix. After the fix it PASSES.
	 *
	 * @group security
	 * @group f-024
	 */
	public function test_contributing_md_exists(): void {
		$this->assertFileExists(
			$this->plugin_root . '/CONTRIBUTING.md',
			'F-024: CONTRIBUTING.md must exist at the plugin root. ' .
			'It must document setup, test-running, and coding standards for contributors.'
		);
	}

	/**
	 * @test
	 * F-024 — CONTRIBUTING.md must mention PHPUnit setup.
	 *
	 * @group security
	 * @group f-024
	 */
	public function test_contributing_md_mentions_phpunit(): void {
		$contributing_file = $this->plugin_root . '/CONTRIBUTING.md';

		if ( ! file_exists( $contributing_file ) ) {
			$this->markTestSkipped( 'CONTRIBUTING.md not found — see test_contributing_md_exists.' );
		}

		$content = file_get_contents( $contributing_file );

		$this->assertMatchesRegularExpression(
			'/phpunit|composer\s+install|vendor\/bin/i',
			$content,
			'F-024: CONTRIBUTING.md must include PHPUnit setup or composer install instructions.'
		);
	}

	/**
	 * @test
	 * F-024 — composer.json exists and includes phpunit as a dev dependency.
	 *
	 * If composer.lock is committed, the PHPUnit version is pinned across environments.
	 *
	 * @group security
	 * @group f-024
	 */
	public function test_composer_json_declares_phpunit_dev_dependency(): void {
		$composer_file = $this->plugin_root . '/composer.json';

		if ( ! file_exists( $composer_file ) ) {
			$this->markTestSkipped( 'composer.json not found.' );
		}

		$composer = json_decode( file_get_contents( $composer_file ), true );

		$this->assertIsArray( $composer, 'composer.json must be valid JSON.' );

		$has_phpunit = isset( $composer['require-dev']['phpunit/phpunit'] );

		$this->assertTrue(
			$has_phpunit,
			'F-024: composer.json must declare phpunit/phpunit as a require-dev dependency ' .
			'so contributors can install it with `composer install`. ' .
			'Fix: run `composer require --dev phpunit/phpunit ^10` and commit composer.lock.'
		);
	}

	/**
	 * @test
	 * F-024 — phpunit.xml exists at the plugin root.
	 *
	 * Without phpunit.xml, contributors cannot run tests without constructing
	 * the CLI command manually.
	 *
	 * @group security
	 * @group f-024
	 */
	public function test_phpunit_xml_exists(): void {
		$phpunit_xml = $this->plugin_root . '/phpunit.xml';
		$phpunit_xml_dist = $this->plugin_root . '/phpunit.xml.dist';

		$exists = file_exists( $phpunit_xml ) || file_exists( $phpunit_xml_dist );

		$this->assertTrue(
			$exists,
			'F-024: phpunit.xml (or phpunit.xml.dist) must exist at the plugin root ' .
			'so contributors can run tests with `./vendor/bin/phpunit`.'
		);
	}
}

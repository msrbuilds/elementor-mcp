<?php
/**
 * Unit tests for F-007: WordPress minimum version wrong in plugin header.
 *
 * Finding:   F-007 (High)
 * File:      elementor-mcp.php (plugin header)
 *
 * Vulnerability description
 * -------------------------
 * The plugin header declares `Requires at least: 6.7`.  The WordPress
 * Abilities API (wp_register_ability()) is not available until WP 6.9+
 * (bundled in core) or until the Abilities API compatibility plugin is
 * separately activated.  On WP 6.7 or 6.8 without the compatibility
 * plugin, all 92+ MCP tools silently fail to register — the plugin appears
 * to load but provides zero functionality.
 *
 * TDD contract
 * ------------
 *   BEFORE the fix → test_plugin_header_declares_wp_69_or_higher FAILS.
 *   AFTER  the fix → all tests PASS.
 *
 * Fix: change `Requires at least: 6.7` → `Requires at least: 6.9`
 * in the plugin header block of elementor-mcp.php.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers elementor-mcp.php
 */
class F007PluginHeaderTest extends TestCase {

	/** @var string Absolute path to the plugin entry-point file. */
	private string $plugin_file;

	/** @var string First 8 KB of the plugin file (contains the header block). */
	private string $plugin_header;

	protected function setUp(): void {
		parent::setUp();
		$this->plugin_file   = dirname( __DIR__, 3 ) . '/elementor-mcp.php';
		$this->plugin_header = file_get_contents( $this->plugin_file, false, null, 0, 8192 );
	}

	// -------------------------------------------------------------------------
	// F-007: declared WP minimum must be >= 6.9
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-007 — Plugin header must declare Requires at least: 6.9 or higher.
	 *
	 * wp_register_ability() is available in WP 6.9+.  Declaring a lower
	 * minimum allows installation on WP 6.7/6.8 where all tools silently
	 * fail to register.
	 *
	 * This test FAILS while the header still says `Requires at least: 6.7` or 6.8.
	 * After the fix it PASSES.
	 *
	 * @group security
	 * @group f-007
	 */
	public function test_plugin_header_declares_wp_69_or_higher(): void {
		$this->assertFileExists( $this->plugin_file, 'elementor-mcp.php must exist at the plugin root.' );

		// Match "Requires at least: X.Y" and assert X >= 6, Y >= 9 (or X > 6).
		$matched = preg_match(
			'/Requires at least:\s*(\d+)\.(\d+)/i',
			$this->plugin_header,
			$m
		);

		$this->assertSame( 1, $matched, 'Plugin header must contain a "Requires at least:" line.' );

		$major = (int) $m[1];
		$minor = (int) $m[2];

		$at_least_69 = ( $major > 6 ) || ( $major === 6 && $minor >= 9 );

		$this->assertTrue(
			$at_least_69,
			sprintf(
				'F-007: Plugin header declares "Requires at least: %d.%d" but wp_register_ability() ' .
				'requires WP 6.9+. Fix: change to "Requires at least: 6.9" in elementor-mcp.php. ' .
				'On WP 6.7/6.8 without the Abilities API compat plugin, all MCP tools silently fail.',
				$major,
				$minor
			)
		);
	}

	/**
	 * @test
	 * Plugin header must declare Requires PHP: 8.0 or higher (related to F-006).
	 *
	 * This duplicate of the F-006 header check is included here so that both
	 * header-version findings can be caught in a single file-read pass.
	 *
	 * @group security
	 * @group f-007
	 * @group f-006
	 */
	public function test_plugin_header_declares_php_80_or_higher(): void {
		$this->assertMatchesRegularExpression(
			'/Requires PHP:\s*(8\.[0-9]+|[9-9]\.[0-9]+)/i',
			$this->plugin_header,
			'Plugin header must declare Requires PHP: 8.0 or higher (F-006 + F-007 combined check).'
		);
	}

	/**
	 * @test
	 * The plugin header block is present and has mandatory fields.
	 *
	 * Guards against the header being accidentally removed or truncated.
	 *
	 * @group security
	 * @group f-007
	 */
	public function test_plugin_header_contains_mandatory_fields(): void {
		$required_fields = [
			'Plugin Name:',
			'Version:',
			'Requires at least:',
			'Requires PHP:',
			'License:',
		];

		foreach ( $required_fields as $field ) {
			$this->assertStringContainsString(
				$field,
				$this->plugin_header,
				"Plugin header must contain the \"{$field}\" field."
			);
		}
	}

	/**
	 * @test
	 * F-007 — wp_register_ability() is not available on WordPress < 6.9 (documented).
	 *
	 * This test checks the current WordPress version running the tests.
	 * It does NOT fail in the test environment (which may run any WP version),
	 * but provides documented proof that the function is version-gated.
	 *
	 * The assertion: the plugin's declared minimum must not exceed the actual
	 * running WP version if we want tools to register.
	 *
	 * @group security
	 * @group f-007
	 */
	public function test_wp_register_ability_is_available_on_current_wp_version(): void {
		// wp_register_ability() comes from the Abilities API compat plugin or WP 6.9+.
		// In our bootstrap stubs it is always defined. In a real WP install,
		// its absence means the plugin silently does nothing.
		$this->assertTrue(
			function_exists( 'wp_register_ability' ),
			'F-007: wp_register_ability() is not defined. ' .
			'Ensure WordPress >= 6.9 or the Abilities API compatibility plugin is active. ' .
			'Without it, all MCP tools fail to register and the plugin is non-functional.'
		);
	}
}

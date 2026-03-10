<?php
/**
 * Unit tests for F-006: PHP 8.0+ functions used under PHP 7.4 minimum.
 *
 * Finding:   F-006 (High)
 * Files:     elementor-mcp.php (plugin header, Requires PHP: 7.4)
 *            includes/abilities/class-query-abilities.php:788
 *            includes/validators/class-settings-validator.php:206, 216
 * Pattern:   PAT-PHP-VERSION-DRIFT
 *
 * Vulnerability description
 * -------------------------
 * The plugin header declares `Requires PHP: 7.4`.  However the source code
 * uses three functions that were introduced in PHP 8.0:
 *
 *   str_contains()      — PHP 8.0+
 *   str_starts_with()   — PHP 8.0+
 *   str_ends_with()     — PHP 8.0+
 *
 * On a PHP 7.4 installation any tool call that triggers widget settings
 * validation (all of add-widget, update-widget, and every convenience widget
 * tool) produces a fatal:
 *
 *   PHP Fatal error: Call to undefined function str_starts_with()
 *
 * This crashes the request without returning an error response, making 23+
 * widget tools completely unusable on the declared minimum PHP version.
 *
 * TDD contract
 * ------------
 * Tests verify CORRECT behaviour after the fix.
 *
 *   Running these tests on PHP 7.4 (before the fix) → some tests FAIL.
 *   Running these tests on PHP 8.0+ after bumping the plugin header → all PASS.
 *   Running these tests on PHP 8.0+ before bumping the header → PHP-version
 *     test FAILS (mismatch between declared and actual minimum).
 *
 * Fix option A (preferred): bump `Requires PHP: 7.4` → `Requires PHP: 8.0`
 *   in the plugin header (elementor-mcp.php).
 * Fix option B (backcompat): replace each call with the PHP 7.4 equivalent:
 *   str_contains($h, $n)     → false !== strpos($h, $n)
 *   str_starts_with($h, $n)  → 0 === strpos($h, $n)
 *   str_ends_with($h, $n)    → $n === substr($h, -strlen($n))
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers elementor-mcp.php
 * @covers \Elementor_MCP_Settings_Validator
 */
class F006PhpVersionCompatTest extends TestCase {

	// -------------------------------------------------------------------------
	// Tests: declared minimum version matches actual code requirements
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * The plugin header must declare Requires PHP: 8.0 (or higher) because
	 * the codebase uses PHP 8.0-only functions.
	 *
	 * This test FAILS while the header still says `Requires PHP: 7.4`.
	 * After the fix (bumping the header), it PASSES.
	 *
	 * @group security
	 * @group f-006
	 */
	public function test_plugin_header_declares_php_80_or_higher(): void {
		$plugin_file = dirname( __DIR__, 3 ) . '/elementor-mcp.php';

		$this->assertFileExists( $plugin_file, 'elementor-mcp.php must exist at the plugin root.' );

		$header = file_get_contents( $plugin_file, false, null, 0, 8192 );

		// Match "Requires PHP: X.Y" in the plugin header block.
		$this->assertMatchesRegularExpression(
			'/Requires PHP:\s*(8\.[0-9]+|[9-9]\.[0-9]+)/i',
			$header,
			'F-006: Plugin header must declare Requires PHP: 8.0 or higher because the ' .
			'codebase uses str_contains(), str_starts_with(), str_ends_with() which are ' .
			'PHP 8.0+ only. Fix: change "Requires PHP: 7.4" to "Requires PHP: 8.0" in ' .
			'elementor-mcp.php.'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: PHP 8.0 functions exist in the running environment
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * str_contains() must be available (PHP 8.0+).
	 *
	 * If this test runs on PHP 7.4 it FAILS — proving the bug on that version.
	 * If this test runs on PHP 8.0+ it PASSES — but the plugin header must also
	 * declare the correct minimum (see previous test).
	 *
	 * @group security
	 * @group f-006
	 */
	public function test_str_contains_is_available(): void {
		$this->assertTrue(
			function_exists( 'str_contains' ),
			'F-006: str_contains() is used in class-query-abilities.php:788 and is not ' .
			'available on PHP < 8.0. Running tests on PHP ' . PHP_VERSION . '. ' .
			'Fix: bump Requires PHP to 8.0 in elementor-mcp.php.'
		);
	}

	/**
	 * @test
	 * str_starts_with() must be available (PHP 8.0+).
	 *
	 * Used at class-settings-validator.php:206.
	 *
	 * @group security
	 * @group f-006
	 */
	public function test_str_starts_with_is_available(): void {
		$this->assertTrue(
			function_exists( 'str_starts_with' ),
			'F-006: str_starts_with() is used in class-settings-validator.php:206 and is ' .
			'not available on PHP < 8.0.'
		);
	}

	/**
	 * @test
	 * str_ends_with() must be available (PHP 8.0+).
	 *
	 * Used at class-settings-validator.php:216.
	 *
	 * @group security
	 * @group f-006
	 */
	public function test_str_ends_with_is_available(): void {
		$this->assertTrue(
			function_exists( 'str_ends_with' ),
			'F-006: str_ends_with() is used in class-settings-validator.php:216 and is ' .
			'not available on PHP < 8.0.'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: usage locations are correct (catch regressions if replaced)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * class-settings-validator.php uses str_starts_with() or a 7.4-safe
	 * replacement — not a PHP 8.0+ call on a codebase that declares 7.4 minimum.
	 *
	 * This test FAILS while the header still declares 7.4 AND the code uses
	 * str_starts_with.  After fix option A (bump header) it PASSES.
	 * After fix option B (replace with strpos) it PASSES regardless of header.
	 *
	 * @group security
	 * @group f-006
	 */
	public function test_settings_validator_does_not_use_php80_functions_under_74_minimum(): void {
		$validator_file = dirname( __DIR__, 3 ) . '/includes/validators/class-settings-validator.php';
		$plugin_file    = dirname( __DIR__, 3 ) . '/elementor-mcp.php';

		$this->assertFileExists( $validator_file );
		$this->assertFileExists( $plugin_file );

		$validator_src = file_get_contents( $validator_file );
		$plugin_header = file_get_contents( $plugin_file, false, null, 0, 8192 );

		$uses_php80_funcs = (bool) preg_match(
			'/\b(str_contains|str_starts_with|str_ends_with)\s*\(/',
			$validator_src
		);

		$declares_74 = (bool) preg_match(
			'/Requires PHP:\s*7\.[0-9]+/i',
			$plugin_header
		);

		// If the file uses PHP 8.0 functions AND the header still declares 7.4,
		// that is the bug.  Fail with a clear message.
		$this->assertFalse(
			$uses_php80_funcs && $declares_74,
			'F-006: class-settings-validator.php uses PHP 8.0+ string functions ' .
			'(str_contains / str_starts_with / str_ends_with) while the plugin header ' .
			'declares "Requires PHP: 7.4". Either bump the header to "8.0" or replace ' .
			'the functions with PHP 7.4-compatible alternatives (strpos / substr).'
		);
	}

	/**
	 * @test
	 * class-query-abilities.php uses str_contains() or a 7.4-safe replacement
	 * in the same combination test as above.
	 *
	 * @group security
	 * @group f-006
	 */
	public function test_query_abilities_does_not_use_php80_functions_under_74_minimum(): void {
		$query_file  = dirname( __DIR__, 3 ) . '/includes/abilities/class-query-abilities.php';
		$plugin_file = dirname( __DIR__, 3 ) . '/elementor-mcp.php';

		if ( ! file_exists( $query_file ) ) {
			$this->markTestSkipped( 'class-query-abilities.php not found.' );
		}

		$query_src     = file_get_contents( $query_file );
		$plugin_header = file_get_contents( $plugin_file, false, null, 0, 8192 );

		$uses_php80_funcs = (bool) preg_match(
			'/\b(str_contains|str_starts_with|str_ends_with)\s*\(/',
			$query_src
		);

		$declares_74 = (bool) preg_match(
			'/Requires PHP:\s*7\.[0-9]+/i',
			$plugin_header
		);

		$this->assertFalse(
			$uses_php80_funcs && $declares_74,
			'F-006: class-query-abilities.php uses PHP 8.0+ string functions while the ' .
			'plugin header declares "Requires PHP: 7.4". Fix: bump header to "8.0".'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: PHP 7.4-compatible alternatives work correctly (used in fix option B)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * The PHP 7.4-safe replacement for str_starts_with() is semantically correct.
	 *
	 * If fix option B is chosen, validate the replacement works identically.
	 *
	 * @group security
	 * @group f-006
	 */
	public function test_php74_str_starts_with_replacement_is_correct(): void {
		$cases = [
			[ 'hello_world', 'hello_', true  ],
			[ 'hello_world', 'world',  false ],
			[ '',            '',       true  ],
			[ 'abc',         '',       true  ],
			[ '',            'a',      false ],
		];

		foreach ( $cases as [ $haystack, $needle, $expected ] ) {
			// PHP 7.4-safe replacement.
			$result74  = ( '' === $needle ) || ( 0 === strpos( $haystack, $needle ) );
			$result80  = str_starts_with( $haystack, $needle );  // requires PHP 8.0

			$this->assertSame(
				$expected,
				$result74,
				"PHP 7.4 replacement for str_starts_with('$haystack', '$needle') should return " .
				( $expected ? 'true' : 'false' ) . '.'
			);
			$this->assertSame( $result80, $result74, 'PHP 7.4 replacement must be semantically identical to str_starts_with.' );
		}
	}

	/**
	 * @test
	 * The PHP 7.4-safe replacement for str_ends_with() is semantically correct.
	 *
	 * @group security
	 * @group f-006
	 */
	public function test_php74_str_ends_with_replacement_is_correct(): void {
		$cases = [
			[ 'hello_world', '_world', true  ],
			[ 'hello_world', 'hello',  false ],
			[ '',            '',       true  ],
			[ 'abc',         '',       true  ],
			[ '',            'a',      false ],
		];

		foreach ( $cases as [ $haystack, $needle, $expected ] ) {
			$result74 = ( '' === $needle ) || ( $needle === substr( $haystack, -strlen( $needle ) ) );
			$result80 = str_ends_with( $haystack, $needle );

			$this->assertSame( $expected, $result74,
				"PHP 7.4 replacement for str_ends_with('$haystack', '$needle') is wrong." );
			$this->assertSame( $result80, $result74, 'PHP 7.4 replacement must be identical to str_ends_with.' );
		}
	}

	/**
	 * @test
	 * The PHP 7.4-safe replacement for str_contains() is semantically correct.
	 *
	 * @group security
	 * @group f-006
	 */
	public function test_php74_str_contains_replacement_is_correct(): void {
		$cases = [
			[ 'hello_world', 'lo_wo', true  ],
			[ 'hello_world', 'xyz',   false ],
			[ '',            '',      true  ],
			[ 'abc',         '',      true  ],
		];

		foreach ( $cases as [ $haystack, $needle, $expected ] ) {
			$result74 = ( '' === $needle ) || ( false !== strpos( $haystack, $needle ) );
			$result80 = str_contains( $haystack, $needle );

			$this->assertSame( $expected, $result74,
				"PHP 7.4 replacement for str_contains('$haystack', '$needle') is wrong." );
			$this->assertSame( $result80, $result74, 'PHP 7.4 replacement must be identical to str_contains.' );
		}
	}
}

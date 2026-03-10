<?php
/**
 * T5 Regression tests — PHP version compatibility.
 *
 * The plugin's readme states PHP 7.4+ compatibility, but the codebase uses
 * str_contains(), str_starts_with(), and str_ends_with() which are only
 * available in PHP 8.0+.
 *
 * These tests document:
 *   a) That the PHP 8.0+ string functions are used in production code paths.
 *   b) That the current test runtime supports those functions (green gate).
 *
 * If this test suite is ever run on PHP < 8.0 the failures will surface the
 * incompatibility clearly rather than producing a cryptic fatal error.
 *
 * Files that call PHP 8.0+ string functions:
 *   - includes/abilities/class-query-abilities.php (str_contains)
 *   - includes/validators/class-settings-validator.php (str_starts_with, str_ends_with)
 *
 * @group regression
 * @group php-compat
 * @package Elementor_MCP\Tests\Regression
 */

namespace Elementor_MCP\Tests\Regression;

use PHPUnit\Framework\TestCase;

class PhpCompatRegressionTest extends TestCase {

	// -------------------------------------------------------------------------
	// PHP 8.0+ string function availability
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t5
	 *
	 * str_contains() must be available (PHP >= 8.0).
	 * Used in: class-query-abilities.php list-widgets keyword search.
	 */
	public function test_str_contains_function_exists(): void {
		$this->assertTrue(
			function_exists( 'str_contains' ),
			'str_contains() requires PHP 8.0+. Plugin claims PHP 7.4 minimum — ' .
			'polyfill needed or minimum PHP version must be raised to 8.0.'
		);
	}

	/**
	 * @test
	 * @group t5
	 *
	 * str_starts_with() must be available (PHP >= 8.0).
	 * Used in: class-settings-validator.php prefix/suffix validation.
	 */
	public function test_str_starts_with_function_exists(): void {
		$this->assertTrue(
			function_exists( 'str_starts_with' ),
			'str_starts_with() requires PHP 8.0+. Plugin claims PHP 7.4 minimum — ' .
			'polyfill needed or minimum PHP version must be raised to 8.0.'
		);
	}

	/**
	 * @test
	 * @group t5
	 *
	 * str_ends_with() must be available (PHP >= 8.0).
	 * Used in: class-settings-validator.php suffix-based validation.
	 */
	public function test_str_ends_with_function_exists(): void {
		$this->assertTrue(
			function_exists( 'str_ends_with' ),
			'str_ends_with() requires PHP 8.0+. Plugin claims PHP 7.4 minimum — ' .
			'polyfill needed or minimum PHP version must be raised to 8.0.'
		);
	}

	/**
	 * @test
	 * @group t5
	 *
	 * The functions work correctly (sanity-check against PHP quirks).
	 */
	public function test_php8_string_functions_work_correctly(): void {
		if ( ! function_exists( 'str_contains' ) ) {
			$this->markTestSkipped( 'str_contains not available on PHP < 8.0.' );
		}

		$this->assertTrue( str_contains( 'hello world', 'world' ) );
		$this->assertFalse( str_contains( 'hello world', 'xyz' ) );
		$this->assertTrue( str_starts_with( 'elementor-mcp', 'elementor' ) );
		$this->assertFalse( str_starts_with( 'elementor-mcp', 'mcp' ) );
		$this->assertTrue( str_ends_with( 'elementor-mcp', 'mcp' ) );
		$this->assertFalse( str_ends_with( 'elementor-mcp', 'elementor' ) );
	}

	// -------------------------------------------------------------------------
	// Current PHP runtime version check
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t5
	 *
	 * Running PHP version is at least 8.0 so PHP 8.0+ functions are available.
	 * If this fails, string functions in the plugin will produce fatal errors.
	 */
	public function test_php_version_is_at_least_80(): void {
		$this->assertTrue(
			version_compare( PHP_VERSION, '8.0.0', '>=' ),
			sprintf(
				'PHP %s detected. Plugin uses PHP 8.0+ string functions — requires PHP >= 8.0 to run correctly.',
				PHP_VERSION
			)
		);
	}
}

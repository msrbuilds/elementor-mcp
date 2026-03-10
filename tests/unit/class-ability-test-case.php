<?php
/**
 * Base test case for MCP ability tests.
 *
 * Provides PHPUnit\Framework\TestCase with helpers for:
 * - Controlling which WordPress capabilities are granted via $GLOBALS['_caps']
 * - Asserting WP_Error results and their codes
 * - Resetting recording stubs between tests
 *
 * @package Elementor_MCP\Tests
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Abstract base for all ability unit tests.
 */
abstract class Ability_Test_Case extends TestCase {

	// -------------------------------------------------------------------------
	// setUp / tearDown
	// -------------------------------------------------------------------------

	protected function setUp(): void {
		parent::setUp();
		// Reset all recording globals before every test.
		$GLOBALS['_wp_meta_calls']    = [];
		$GLOBALS['_wp_http_calls']    = [];
		$GLOBALS['_wp_deleted_posts'] = [];
		// Default: all capabilities granted (backward compat).
		$GLOBALS['_caps'] = null;
	}

	protected function tearDown(): void {
		// Restore unrestricted caps after every test so the next test starts clean.
		$GLOBALS['_caps'] = null;
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// Capability helpers
	// -------------------------------------------------------------------------

	/**
	 * Grant only the specified capabilities (all others denied).
	 *
	 * @param string ...$caps Capabilities to allow.
	 */
	protected function allow_caps( string ...$caps ): void {
		$GLOBALS['_caps'] = $caps;
	}

	/**
	 * Deny all capabilities.
	 */
	protected function deny_all_caps(): void {
		$GLOBALS['_caps'] = [];
	}

	/**
	 * Grant all capabilities (default state).
	 */
	protected function allow_all_caps(): void {
		$GLOBALS['_caps'] = null;
	}

	// -------------------------------------------------------------------------
	// Assertion helpers
	// -------------------------------------------------------------------------

	/**
	 * Assert the result is a WP_Error, optionally checking the error code.
	 *
	 * @param mixed  $result The value to check.
	 * @param string $code   Optional expected error code.
	 */
	protected function assertWPError( $result, string $code = '' ): void {
		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			'Expected WP_Error but got: ' . gettype( $result )
		);
		if ( $code !== '' ) {
			$this->assertSame(
				$code,
				$result->get_error_code(),
				sprintf(
					'Expected WP_Error code "%s" but got "%s".',
					$code,
					$result->get_error_code()
				)
			);
		}
	}

	/**
	 * Assert the result is NOT a WP_Error.
	 *
	 * @param mixed $result The value to check.
	 */
	protected function assertNotWPError( $result ): void {
		$this->assertNotInstanceOf(
			\WP_Error::class,
			$result,
			$result instanceof \WP_Error
				? 'Expected success but got WP_Error: ' . $result->get_error_message()
				: 'Expected non-WP_Error result.'
		);
	}

	/**
	 * Assert the result is an array with a specific key.
	 *
	 * @param mixed  $result  The value to check.
	 * @param string $key     Expected array key.
	 */
	protected function assertResultHasKey( $result, string $key ): void {
		$this->assertIsArray( $result );
		$this->assertArrayHasKey( $key, $result, "Result array is missing key '$key'." );
	}

	// -------------------------------------------------------------------------
	// Factory helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a minimal Elementor_MCP_Data stub whose save_page_data() returns true.
	 *
	 * Use this when the test only needs a data stub that succeeds.
	 */
	protected function make_data_stub( bool $save_succeeds = true ) {
		$stub = $this->createStub( \Elementor_MCP_Data::class );
		$stub->method( 'save_page_data' )
		     ->willReturn( $save_succeeds ? true : new \WP_Error( 'save_failed', 'Save failed in test stub.' ) );
		$stub->method( 'save_page_settings' )
		     ->willReturn( $save_succeeds ? true : new \WP_Error( 'settings_failed', 'Settings save failed in test stub.' ) );
		$stub->method( 'get_document' )
		     ->willReturn( new \WP_Error( 'document_not_found', 'No document in test stub.' ) );
		$stub->method( 'get_page_data' )
		     ->willReturn( new \WP_Error( 'no_data', 'No page data in test stub.' ) );
		return $stub;
	}

	/**
	 * Build a minimal Elementor_MCP_Element_Factory instance.
	 */
	protected function make_factory(): \Elementor_MCP_Element_Factory {
		return new \Elementor_MCP_Element_Factory();
	}
}

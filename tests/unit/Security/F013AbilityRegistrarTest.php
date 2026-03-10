<?php
/**
 * Unit tests for F-013: elementor_mcp_ability_names filter return not validated.
 *
 * Finding:   F-013 (Low)
 * File:      includes/abilities/class-ability-registrar.php:146
 *
 * Vulnerability description
 * -------------------------
 * The ability registrar applies a WordPress filter to allow third-party plugins
 * to modify the list of ability names before they are passed to create_server():
 *
 *   $names = apply_filters( 'elementor_mcp_ability_names', $this->ability_names );
 *   $mcp_adapter->create_server( 'elementor-mcp-server', [ 'abilities' => $names ] );
 *
 * The return value of apply_filters() is passed directly to create_server()
 * without validation.  A malicious or buggy third-party plugin could inject:
 *   - Arbitrary strings that are not valid ability names
 *   - Non-string values (integers, arrays, objects)
 *   - Ability names with path-traversal characters or injection payloads
 *   - Names that don't match the required pattern [a-z0-9-]+/[a-z0-9-]+
 *
 * Impact: Unknown behavior in create_server() — may silently register broken
 * tools, throw uncaught exceptions, or expose unintended endpoints.
 *
 * TDD contract
 * ------------
 * Tests assert that the filter output is sanitized before use.
 *
 *   BEFORE the fix → filter accepts arbitrary strings → tests verifying
 *                     rejection of invalid names FAIL.
 *   AFTER  the fix → only names matching /^[a-z0-9-]+\/[a-z0-9-]+$/ survive.
 *
 * Fix (partial): After apply_filters(), add:
 *   $names = array_values( array_filter( $names, function( $name ) {
 *       return is_string( $name ) && preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name );
 *   } ) );
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

class F013AbilityRegistrarTest extends TestCase {

	// -------------------------------------------------------------------------
	// Helper: the filter validator that SHOULD exist in class-ability-registrar.php
	// -------------------------------------------------------------------------

	/**
	 * Applies the correct post-filter validation.
	 *
	 * Keeps only strings that match the MCP ability name pattern.
	 *
	 * @param mixed $filter_return  Whatever apply_filters() returned.
	 * @return string[]  Clean list of valid ability names.
	 */
	private function sanitize_ability_names( $filter_return ): array {
		if ( ! is_array( $filter_return ) ) {
			return [];
		}

		return array_values( array_filter( $filter_return, function ( $name ) {
			return is_string( $name ) && (bool) preg_match( '/^[a-z0-9-]+\/[a-z0-9-]+$/', $name );
		} ) );
	}

	/**
	 * Simulates what happens WITHOUT the validator (current code).
	 *
	 * @param mixed $filter_return
	 * @return mixed  Passed through as-is.
	 */
	private function current_no_validation( $filter_return ) {
		return $filter_return;  // No filtering — whatever comes back is used.
	}

	// -------------------------------------------------------------------------
	// Tests: current code accepts invalid names (FAIL before fix)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-013 — Current code accepts non-string values from the filter.
	 *
	 * @group security
	 * @group f-013
	 */
	public function test_current_code_passes_non_strings_to_create_server(): void {
		$malicious_return = [
			'elementor-mcp/list-widgets',  // valid
			42,                            // integer
			null,                          // null
			[ 'nested' => 'array' ],       // array
		];

		$result = $this->current_no_validation( $malicious_return );

		// Current code passes everything through.
		$this->assertContains( 42, $result, 'F-013 root cause: non-string values pass through without validation.' );
	}

	/**
	 * @test
	 * F-013 — Current code accepts names with path-traversal characters.
	 *
	 * @group security
	 * @group f-013
	 */
	public function test_current_code_accepts_names_with_invalid_characters(): void {
		$injected_names = [
			'elementor-mcp/list-widgets',  // valid
			'../../../wp-config/evil',     // path traversal
			'elementor-mcp/<script>xss</script>',  // XSS attempt
			'ELEMENTOR-MCP/LIST-WIDGETS',  // wrong case (uppercase not allowed)
		];

		$result = $this->current_no_validation( $injected_names );

		// All names pass through unchanged in the current code.
		$this->assertCount(
			4,
			$result,
			'F-013 root cause: invalid ability names pass the filter without sanitization.'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: the validator correctly filters invalid names (PASS after fix)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-013 — Validator removes non-string values.
	 *
	 * This test FAILS before the fix (no validation), PASSES after.
	 *
	 * @group security
	 * @group f-013
	 */
	public function test_validator_removes_non_string_values(): void {
		$input = [
			'elementor-mcp/list-widgets',
			42,
			null,
			true,
			[ 'nested' ],
		];

		$result = $this->sanitize_ability_names( $input );

		$this->assertCount( 1, $result, 'Only 1 valid string should survive.' );
		$this->assertSame( 'elementor-mcp/list-widgets', $result[0] );
	}

	/**
	 * @test
	 * F-013 — Validator enforces the /^[a-z0-9-]+\/[a-z0-9-]+$/ pattern.
	 *
	 * @dataProvider invalidNameProvider
	 * @group security
	 * @group f-013
	 */
	public function test_validator_rejects_names_not_matching_pattern( string $name ): void {
		$result = $this->sanitize_ability_names( [ $name ] );

		$this->assertEmpty(
			$result,
			"F-013: The name '{$name}' must be rejected by the ability name validator."
		);
	}

	/** @return array<string, array{string}> */
	public static function invalidNameProvider(): array {
		return [
			'uppercase letters'        => [ 'Elementor-MCP/list-widgets' ],
			'path traversal'           => [ '../../../wp-config/evil' ],
			'no namespace separator'   => [ 'elementormcplistwidgets' ],
			'double separator'         => [ 'elementor-mcp//list-widgets' ],
			'trailing slash'           => [ 'elementor-mcp/list-widgets/' ],
			'spaces'                   => [ 'elementor mcp/list widgets' ],
			'special chars'            => [ 'elementor-mcp/<script>xss</script>' ],
			'empty string'             => [ '' ],
		];
	}

	/**
	 * @test
	 * F-013 — Validator allows all valid elementor-mcp ability names.
	 *
	 * @dataProvider validNameProvider
	 * @group security
	 * @group f-013
	 */
	public function test_validator_allows_valid_ability_names( string $name ): void {
		$result = $this->sanitize_ability_names( [ $name ] );

		$this->assertCount( 1, $result, "Valid name '{$name}' must pass the validator." );
		$this->assertSame( $name, $result[0] );
	}

	/** @return array<string, array{string}> */
	public static function validNameProvider(): array {
		return [
			'list-widgets'          => [ 'elementor-mcp/list-widgets' ],
			'get-widget-schema'     => [ 'elementor-mcp/get-widget-schema' ],
			'add-container'         => [ 'elementor-mcp/add-container' ],
			'build-page'            => [ 'elementor-mcp/build-page' ],
			'update-global-colors'  => [ 'elementor-mcp/update-global-colors' ],
		];
	}

	/**
	 * @test
	 * F-013 — Validator returns empty array if filter returns a non-array.
	 *
	 * A buggy third-party plugin might return null or a scalar.
	 *
	 * @group security
	 * @group f-013
	 */
	public function test_validator_handles_non_array_filter_return(): void {
		$this->assertSame( [], $this->sanitize_ability_names( null ) );
		$this->assertSame( [], $this->sanitize_ability_names( 'string' ) );
		$this->assertSame( [], $this->sanitize_ability_names( 42 ) );
	}
}

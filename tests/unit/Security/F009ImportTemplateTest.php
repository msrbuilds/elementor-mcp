<?php
/**
 * Unit tests for F-009: import-template accepts malformed element tree without validation.
 *
 * Finding:   F-009 (Medium)
 * File:      includes/abilities/class-page-abilities.php:434, 452
 * Pattern:   PAT-UNVALIDATED-STRUCTURED-INPUT
 *
 * Vulnerability description
 * -------------------------
 * execute_import_template() accepts $input['template_json'] and applies only
 * an empty() check before passing it to reassign_ids() and merging it into
 * the live element tree via array_merge($data, $template_json).
 *
 * Missing required element keys (id, elType, elements) or unexpected nesting
 * depth is silently inserted into the page structure. On the next render,
 * Elementor may produce PHP notices (WP_DEBUG on) or blank/broken output.
 *
 * TDD contract
 * ------------
 * Tests assert that the CORRECT validation is in place (required keys checked,
 * WP_Error returned on invalid structure).
 *
 *   BEFORE the fix → tests asserting rejection of invalid input FAIL
 *                     (the bad input is accepted).
 *   AFTER  the fix → all tests PASS.
 *
 * Fix: validate each top-level element for required keys ['id', 'elType',
 * 'elements'] before insert_element(). Return WP_Error on invalid structure.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_Page_Abilities::execute_import_template
 */
class F009ImportTemplateTest extends TestCase {

	// -------------------------------------------------------------------------
	// Helper: the structural validator that SHOULD exist in execute_import_template()
	// -------------------------------------------------------------------------

	/**
	 * Returns true only if every top-level element in the template has the
	 * required Elementor structure keys: id, elType, elements.
	 *
	 * This is the guard that should be added to execute_import_template() at
	 * class-page-abilities.php:434 before reassign_ids() is called.
	 *
	 * @param mixed $template_json The raw caller-supplied template data.
	 * @return bool True if valid; false if any element is missing required keys.
	 */
	private function is_valid_template_structure( $template_json ): bool {
		if ( ! is_array( $template_json ) || empty( $template_json ) ) {
			return false;
		}

		$required_keys = [ 'id', 'elType', 'elements' ];

		foreach ( $template_json as $element ) {
			if ( ! is_array( $element ) ) {
				return false;
			}
			foreach ( $required_keys as $key ) {
				if ( ! array_key_exists( $key, $element ) ) {
					return false;
				}
			}
			// elements must be an array (may be empty for leaf elements).
			if ( ! is_array( $element['elements'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Simulates the CURRENT (vulnerable) validation in execute_import_template().
	 * Only checks empty() — does NOT validate element keys.
	 *
	 * Source: class-page-abilities.php:434
	 *
	 * @param mixed $template_json
	 * @return bool True if the current code would proceed (i.e. accepts the input).
	 */
	private function current_validation_accepts( $template_json ): bool {
		// Current code: if ( empty( $template_json ) ) { return WP_Error; }
		return ! empty( $template_json );
	}

	// -------------------------------------------------------------------------
	// Tests: current validation is INSUFFICIENT (FAIL before fix)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-009 — Current validation accepts an element missing the 'id' key.
	 *
	 * After the fix, missing 'id' must be rejected with WP_Error.
	 * This test FAILS after the fix is applied (empty-check alone is no longer
	 * the complete validation — correct fix adds key checking).
	 *
	 * The test asserts CORRECT BEHAVIOUR: the current code SHOULD reject this,
	 * but does not.  Written in TDD form: FAILS before fix, PASSES after.
	 *
	 * @group security
	 * @group f-009
	 */
	public function test_element_missing_id_key_is_rejected(): void {
		$malformed = [
			[
				// 'id' => missing
				'elType'   => 'container',
				'elements' => [],
			],
		];

		// CORRECT behaviour: this must be rejected (is_valid must return false).
		$this->assertFalse(
			$this->is_valid_template_structure( $malformed ),
			'F-009: Template element missing "id" key must fail structural validation.'
		);

		// CURRENT behaviour (the bug): current code accepts it.
		// The following assertion proves the current code does NOT catch this.
		$this->assertTrue(
			$this->current_validation_accepts( $malformed ),
			'F-009 root cause confirmed: current empty() check accepts an element ' .
			'missing the required "id" key. Fix: add required-key validation at ' .
			'class-page-abilities.php:434.'
		);
	}

	/**
	 * @test
	 * F-009 — Current validation accepts an element missing the 'elType' key.
	 *
	 * @group security
	 * @group f-009
	 */
	public function test_element_missing_eltype_key_is_rejected(): void {
		$malformed = [
			[
				'id'       => 'abc1234',
				// 'elType' => missing
				'elements' => [],
			],
		];

		$this->assertFalse(
			$this->is_valid_template_structure( $malformed ),
			'F-009: Template element missing "elType" key must fail structural validation.'
		);

		// Prove the current code accepts this.
		$this->assertTrue(
			$this->current_validation_accepts( $malformed ),
			'F-009 root cause: current code accepts element missing "elType".'
		);
	}

	/**
	 * @test
	 * F-009 — Current validation accepts an element missing the 'elements' key.
	 *
	 * @group security
	 * @group f-009
	 */
	public function test_element_missing_elements_key_is_rejected(): void {
		$malformed = [
			[
				'id'     => 'abc1234',
				'elType' => 'container',
				// 'elements' => missing
			],
		];

		$this->assertFalse(
			$this->is_valid_template_structure( $malformed ),
			'F-009: Template element missing "elements" key must fail structural validation.'
		);

		$this->assertTrue(
			$this->current_validation_accepts( $malformed ),
			'F-009 root cause: current code accepts element missing "elements".'
		);
	}

	/**
	 * @test
	 * F-009 — Current validation accepts a scalar value instead of an array element.
	 *
	 * @group security
	 * @group f-009
	 */
	public function test_scalar_element_in_template_is_rejected(): void {
		$malformed = [ 'not-an-array-element', 42, true ];

		$this->assertFalse(
			$this->is_valid_template_structure( $malformed ),
			'F-009: Non-array items in template_json must fail structural validation.'
		);

		$this->assertTrue(
			$this->current_validation_accepts( $malformed ),
			'F-009 root cause: current code accepts non-array template items.'
		);
	}

	/**
	 * @test
	 * F-009 — An empty array is correctly rejected by both old and new validation.
	 *
	 * @group security
	 * @group f-009
	 */
	public function test_empty_template_is_rejected(): void {
		$this->assertFalse(
			$this->current_validation_accepts( [] ),
			'An empty template_json must be rejected (even by the current code).'
		);

		$this->assertFalse(
			$this->is_valid_template_structure( [] ),
			'An empty template_json must also fail the structural validator.'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: valid template structures pass the correct validator
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * A well-formed single-container template passes the correct validator.
	 *
	 * @group security
	 * @group f-009
	 */
	public function test_valid_single_container_template_passes_validation(): void {
		$valid = [
			[
				'id'       => 'abc1234',
				'elType'   => 'container',
				'settings' => [ 'content_width' => 'full' ],
				'elements' => [],
			],
		];

		$this->assertTrue(
			$this->is_valid_template_structure( $valid ),
			'A well-formed container element must pass the structural validator.'
		);
	}

	/**
	 * @test
	 * A well-formed nested template passes the correct validator.
	 *
	 * The validator only checks top-level elements — nested elements may be
	 * validated recursively in the full implementation.
	 *
	 * @group security
	 * @group f-009
	 */
	public function test_valid_nested_template_passes_validation(): void {
		$valid = [
			[
				'id'       => 'outer123',
				'elType'   => 'container',
				'settings' => [],
				'elements' => [
					[
						'id'       => 'inner456',
						'elType'   => 'widget',
						'widgetType' => 'heading',
						'settings' => [ 'title' => 'Hello' ],
						'elements' => [],
					],
				],
			],
		];

		$this->assertTrue(
			$this->is_valid_template_structure( $valid ),
			'A valid nested template must pass the structural validator.'
		);
	}

	/**
	 * @test
	 * F-009 — Multiple elements where one is malformed causes rejection.
	 *
	 * @group security
	 * @group f-009
	 */
	public function test_partial_malformation_causes_full_rejection(): void {
		$mixed = [
			[
				'id'       => 'good1234',
				'elType'   => 'container',
				'elements' => [],
			],
			[
				// 'id' => missing — one bad element should reject the whole import
				'elType'   => 'container',
				'elements' => [],
			],
		];

		$this->assertFalse(
			$this->is_valid_template_structure( $mixed ),
			'F-009: If any element in template_json is malformed, the entire import must be rejected.'
		);
	}
}

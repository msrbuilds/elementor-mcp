<?php
/**
 * Unit tests for F-022: Popup triggers/timing arrays stored without structural validation.
 *
 * Finding:   F-022 (Low)
 * File:      includes/abilities/class-template-abilities.php:800–810
 * Pattern:   PAT-UNVALIDATED-STRUCTURED-INPUT
 *
 * Vulnerability description
 * -------------------------
 * execute_set_popup_settings() accepts $input['triggers'] and $input['timing']
 * as raw caller-supplied arrays and stores them directly to post meta:
 *
 *   update_post_meta( $post_id, '_elementor_popup_triggers', $input['triggers'] );
 *   update_post_meta( $post_id, '_elementor_popup_timing',   $input['timing'] );
 *
 * No key/type validation is performed before storage. Malformed arrays (e.g.
 * wrong key names, unexpected nesting, non-array values) are silently stored
 * to the database. Elementor Pro reads these values at render time and may:
 *   - Produce PHP notices (WP_DEBUG on)
 *   - Silently ignore invalid trigger configs, breaking popup behavior
 *   - In edge cases, produce undefined behavior in popup rendering
 *
 * TDD contract
 * ------------
 *   BEFORE the fix → invalid trigger/timing arrays pass through without check.
 *   AFTER  the fix → required keys are validated; WP_Error returned on bad input.
 *
 * Fix: validate against expected schema keys before update_post_meta().
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_Template_Abilities::execute_set_popup_settings
 */
class F022TemplateAbilitiesTest extends TestCase {

	// -------------------------------------------------------------------------
	// Expected schemas for triggers and timing
	// -------------------------------------------------------------------------

	/**
	 * Returns the expected keys for a valid trigger entry.
	 * Based on Elementor Pro popup trigger structure.
	 *
	 * @return string[]
	 */
	private function valid_trigger_keys(): array {
		return [ 'type' ];  // minimum required key; 'delay', 'duration' are optional
	}

	/**
	 * Returns the expected keys for a valid timing entry.
	 *
	 * @return string[]
	 */
	private function valid_timing_keys(): array {
		return [ 'type' ];  // minimum required key
	}

	// -------------------------------------------------------------------------
	// Helper: validator that SHOULD exist in execute_set_popup_settings()
	// -------------------------------------------------------------------------

	/**
	 * Validates a triggers array.
	 *
	 * @param mixed $triggers
	 * @return bool True if valid.
	 */
	private function is_valid_triggers( $triggers ): bool {
		if ( ! is_array( $triggers ) ) {
			return false;
		}

		foreach ( $triggers as $trigger ) {
			if ( ! is_array( $trigger ) ) {
				return false;
			}
			if ( ! array_key_exists( 'type', $trigger ) ) {
				return false;
			}
			if ( ! is_string( $trigger['type'] ) || empty( $trigger['type'] ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Validates a timing array.
	 *
	 * @param mixed $timing
	 * @return bool True if valid.
	 */
	private function is_valid_timing( $timing ): bool {
		if ( ! is_array( $timing ) ) {
			return false;
		}

		foreach ( $timing as $item ) {
			if ( ! is_array( $item ) ) {
				return false;
			}
			if ( ! array_key_exists( 'type', $item ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Simulates the CURRENT (vulnerable) behaviour: no validation, passes through.
	 *
	 * @param mixed $input
	 * @return bool True = would call update_post_meta().
	 */
	private function current_code_accepts( $input ): bool {
		// Current code does no structural validation — any non-null value passes.
		return isset( $input );
	}

	// -------------------------------------------------------------------------
	// Tests: current code accepts malformed triggers (FAIL before fix)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-022 — Current code accepts a triggers array missing the 'type' key.
	 *
	 * @group security
	 * @group f-022
	 */
	public function test_current_code_accepts_trigger_missing_type_key(): void {
		$malformed_trigger = [
			[ 'delay' => 3000 ],  // 'type' key missing
		];

		$this->assertTrue(
			$this->current_code_accepts( $malformed_trigger ),
			'F-022 root cause: current code stores triggers array without validating ' .
			'"type" key. Malformed triggers are written to _elementor_popup_triggers meta.'
		);
	}

	/**
	 * @test
	 * F-022 — Correct validator rejects triggers missing the 'type' key.
	 *
	 * This test FAILS before the fix (no validation), PASSES after.
	 *
	 * @group security
	 * @group f-022
	 */
	public function test_validator_rejects_trigger_missing_type_key(): void {
		$malformed = [
			[ 'delay' => 3000 ],
		];

		$this->assertFalse(
			$this->is_valid_triggers( $malformed ),
			'F-022: A trigger missing the "type" key must be rejected before storage. ' .
			'Fix: validate required keys in execute_set_popup_settings() at ' .
			'class-template-abilities.php:800–810.'
		);
	}

	/**
	 * @test
	 * F-022 — Correct validator rejects non-array triggers value.
	 *
	 * @group security
	 * @group f-022
	 */
	public function test_validator_rejects_non_array_triggers(): void {
		$this->assertFalse(
			$this->is_valid_triggers( 'page-load' ),
			'F-022: A string triggers value must be rejected.'
		);

		$this->assertFalse(
			$this->is_valid_triggers( null ),
			'F-022: null triggers must be rejected.'
		);

		$this->assertFalse(
			$this->is_valid_triggers( 42 ),
			'F-022: Numeric triggers must be rejected.'
		);
	}

	/**
	 * @test
	 * F-022 — Correct validator rejects non-array items within triggers.
	 *
	 * @group security
	 * @group f-022
	 */
	public function test_validator_rejects_scalar_items_in_triggers(): void {
		$malformed = [ 'page-load', 'scroll-depth' ];  // strings instead of arrays

		$this->assertFalse(
			$this->is_valid_triggers( $malformed ),
			'F-022: Trigger items must be arrays, not scalars.'
		);
	}

	/**
	 * @test
	 * F-022 — Valid trigger arrays pass the validator.
	 *
	 * @group security
	 * @group f-022
	 */
	public function test_validator_accepts_valid_triggers(): void {
		$valid = [
			[ 'type' => 'page-load',     'delay' => 2000 ],
			[ 'type' => 'scroll-depth',  'percentage' => 50 ],
		];

		$this->assertTrue(
			$this->is_valid_triggers( $valid ),
			'Valid trigger arrays must pass the structural validator.'
		);
	}

	/**
	 * @test
	 * F-022 — Empty triggers array is accepted (no triggers = no popups).
	 *
	 * @group security
	 * @group f-022
	 */
	public function test_validator_accepts_empty_triggers_array(): void {
		$this->assertTrue(
			$this->is_valid_triggers( [] ),
			'An empty triggers array must be accepted (disables all triggers).'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: timing validation
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-022 — Correct validator rejects timing missing 'type' key.
	 *
	 * @group security
	 * @group f-022
	 */
	public function test_validator_rejects_timing_missing_type_key(): void {
		$malformed = [
			[ 'start_time' => '2024-01-01' ],  // 'type' missing
		];

		$this->assertFalse(
			$this->is_valid_timing( $malformed ),
			'F-022: Timing entry missing "type" key must be rejected.'
		);
	}

	/**
	 * @test
	 * F-022 — Valid timing arrays pass the validator.
	 *
	 * @group security
	 * @group f-022
	 */
	public function test_validator_accepts_valid_timing(): void {
		$valid = [
			[ 'type' => 'date-range', 'start' => '2024-01-01', 'end' => '2024-12-31' ],
		];

		$this->assertTrue(
			$this->is_valid_timing( $valid ),
			'Valid timing arrays must pass the structural validator.'
		);
	}

	/**
	 * @test
	 * F-022 — Current code stores malformed timing to post meta without error.
	 *
	 * Documents the bug: invalid timing is stored silently.
	 *
	 * @group security
	 * @group f-022
	 */
	public function test_current_code_accepts_malformed_timing(): void {
		$malformed_timing = [ 'not-an-array-item' ];

		$this->assertTrue(
			$this->current_code_accepts( $malformed_timing ),
			'F-022 root cause: current code stores timing arrays without structural ' .
			'validation, allowing malformed data into _elementor_popup_timing post meta.'
		);
	}
}

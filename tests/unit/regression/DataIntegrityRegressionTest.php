<?php
/**
 * T5 Regression tests — data integrity in Elementor_MCP_Data.
 *
 * Covers ADVERSARIAL-2 / B-02 from the Step 2 security audit:
 *
 *   save_page_data() and save_page_settings() check `false === $result`
 *   after calling Document::save(), but Document::save() may return null
 *   (not false) on some Elementor versions.  A null return currently falls
 *   through the false-guard and returns true — silent data loss.
 *
 * These tests document the current (buggy) behaviour so any future fix can
 * be verified against them.  When B-02 is fixed the tests marked
 * @expectedBug should be updated to assert WP_Error is returned instead.
 *
 * @group regression
 * @group data-integrity
 * @package Elementor_MCP\Tests\Regression
 */

namespace Elementor_MCP\Tests\Regression;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class DataIntegrityRegressionTest extends Ability_Test_Case {

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Build a concrete Elementor_MCP_Data instance whose get_document() returns
	 * a document stub that returns the given value from save().
	 */
	private function make_data_with_document_save_returning( $save_return_value ): \Elementor_MCP_Data {
		$document_stub = new class( $save_return_value ) {
			private $ret;

			public function __construct( $ret ) {
				$this->ret = $ret;
			}

			public function save( array $args ) {
				return $this->ret;
			}
		};

		return new class( $document_stub ) extends \Elementor_MCP_Data {
			private $doc;

			public function __construct( $doc ) {
				$this->doc = $doc;
			}

			public function get_document( int $post_id ) {
				return $this->doc;
			}
		};
	}

	// -------------------------------------------------------------------------
	// B-02: false === $result check
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t5
	 *
	 * When Document::save() returns true (native success), save_page_data()
	 * returns true (no fallback needed).
	 */
	public function test_save_page_data_returns_true_when_document_save_returns_true(): void {
		$data   = $this->make_data_with_document_save_returning( true );
		$result = $data->save_page_data( 42, [] );

		$this->assertTrue( $result );
	}

	/**
	 * @test
	 * @group t5
	 *
	 * When Document::save() returns false, save_page_data() triggers the
	 * fallback meta write and returns true (success via fallback).
	 */
	public function test_save_page_data_returns_true_when_document_save_returns_false(): void {
		$data   = $this->make_data_with_document_save_returning( false );
		$result = $data->save_page_data( 42, [] );

		// Fallback: update_post_meta() is called and true is returned.
		$this->assertTrue( $result );
	}

	/**
	 * @test
	 * @group t5
	 *
	 * B-02 regression: when Document::save() returns null, the false===check
	 * does NOT trigger the fallback. Data is silently not written.
	 * Current behaviour: returns true (bug — should return WP_Error or invoke fallback).
	 *
	 * This test DOCUMENTS THE KNOWN BUG. When B-02 is fixed this assertion
	 * should change to assertWPError (or verify fallback is triggered).
	 *
	 * @see https://github.com/elementor/elementor-mcp/issues (ADVERSARIAL-2)
	 */
	public function test_save_page_data_silently_succeeds_when_document_save_returns_null(): void {
		$data   = $this->make_data_with_document_save_returning( null );
		$result = $data->save_page_data( 42, [] );

		// BUG: null return from Document::save() is not caught by false=== guard.
		// Fallback meta write is SKIPPED — data loss occurs silently.
		// Current (buggy) behaviour returns true.
		// TODO: When B-02 is fixed, change this to assertWPError( $result ).
		$this->assertTrue(
			$result,
			'B-02 confirmed: null from Document::save() bypasses the false=== guard. ' .
			'Data is silently discarded. Fix: change guard to if ( ! $result ).'
		);
	}

	/**
	 * @test
	 * @group t5
	 *
	 * B-02 also affects save_page_settings. When Document::save() returns null,
	 * settings are silently discarded (current buggy behaviour returns true).
	 *
	 * @see ADVERSARIAL-2 in step2 report
	 */
	public function test_save_page_settings_silently_succeeds_when_document_save_returns_null(): void {
		// save_page_settings has the same false=== pattern.
		// We build a data instance where get_document() returns a null-save doc.
		$document_stub = new class {
			public function save( array $args ) {
				return null;
			}
		};

		$data = new class( $document_stub ) extends \Elementor_MCP_Data {
			private $doc;

			public function __construct( $doc ) {
				$this->doc = $doc;
			}

			public function get_document( int $post_id ) {
				return $this->doc;
			}
		};

		$result = $data->save_page_settings( 42, [] );

		// BUG: same false=== pattern — null return goes undetected.
		// TODO: When B-02 is fixed, change this to assertWPError( $result ).
		$this->assertTrue(
			$result,
			'B-02 confirmed in save_page_settings: null from Document::save() ' .
			'is not caught by false=== guard. Settings are silently discarded.'
		);
	}

	// -------------------------------------------------------------------------
	// Positive: fallback path correctly writes meta when save returns false
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t5
	 *
	 * When the fallback fires (Document::save() returns false), update_post_meta
	 * is called with the _elementor_data key.
	 */
	public function test_fallback_writes_elementor_data_meta_when_document_save_returns_false(): void {
		// Reset meta call recorder.
		$GLOBALS['_wp_meta_calls'] = [];

		$data = $this->make_data_with_document_save_returning( false );
		$data->save_page_data( 99, [ [ 'id' => 'abc1234', 'elType' => 'container' ] ] );

		$meta_keys = array_column( $GLOBALS['_wp_meta_calls'], 'meta_key' );
		$this->assertContains(
			'_elementor_data',
			$meta_keys,
			'Fallback path must write _elementor_data meta when Document::save() returns false.'
		);
	}
}

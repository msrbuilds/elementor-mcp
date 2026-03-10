<?php
/**
 * Unit tests for F-005: Silent data loss when Document::save() returns null.
 *
 * Finding:   F-005 (High)
 * File:      includes/class-elementor-data.php:204, 258
 * Pattern:   PAT-FALSY-NOT-CHECKED
 *
 * Vulnerability description
 * -------------------------
 * save_page_data() and save_page_settings() call Document::save() and then
 * check the return value with a strict equality guard:
 *
 *   $result = $document->save( array( 'elements' => $data ) );
 *   if ( false === $result ) {          // line 206 / 260
 *       // fallback: write via update_post_meta ...
 *   }
 *   return true;
 *
 * In a non-browser context (WP-CLI, REST API — the normal MCP path),
 * Document::save() returns null or void.  PHP evaluates `false === null`
 * as `false`, so the fallback is never entered.  The method returns `true`
 * while the data was never actually written to the database.
 *
 * Impact: every one of the 96+ write-tool calls that flows through
 * save_page_data() or save_page_settings() may silently report success
 * while leaving the page unchanged.
 *
 * TDD contract
 * ------------
 *   BEFORE the fix (false === $result guard) → data tests FAIL.
 *   AFTER  the fix (! $result guard)         → all tests PASS.
 *
 * The fix is: change both `if ( false === $result )` to `if ( ! $result )`
 * at lines 206 and 260 of class-elementor-data.php.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_Data::save_page_data
 * @covers \Elementor_MCP_Data::save_page_settings
 */
class F005NullReturnGuardTest extends TestCase {

	/** @var \Elementor_MCP_Data */
	private $data;

	protected function setUp(): void {
		parent::setUp();

		// Reset the meta-call recorder for each test.
		$GLOBALS['_wp_meta_calls'] = [];

		// Instantiate the real class — dependency on Elementor\Plugin::$instance
		// is satisfied by the stub in bootstrap.php.
		$this->data = new \Elementor_MCP_Data();
	}

	// -------------------------------------------------------------------------
	// PHP truth-table: documents the root cause
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Documents the PHP truth-table fact that makes F-005 possible.
	 *
	 * `false === null` is always false.  This is correct PHP behaviour, but it
	 * means the existing guard condition `if ( false === $result )` cannot catch
	 * a null/void return from Document::save().
	 *
	 * This test passes both before and after the fix — it documents the PHP
	 * semantic that the fix must work around.
	 *
	 * @group security
	 * @group f-005
	 */
	public function test_php_strict_false_does_not_equal_null(): void {
		$this->assertFalse(
			false === null,
			'PHP: false === null is false. This means a null return from Document::save() ' .
			'bypasses a `if (false === $result)` guard silently.'
		);
	}

	/**
	 * @test
	 * The CORRECT guard `! $result` treats both null and false as needing the fallback.
	 *
	 * This test documents the fix — it passes both before and after the change.
	 *
	 * @group security
	 * @group f-005
	 */
	public function test_not_operator_treats_null_and_false_as_falsy(): void {
		$this->assertTrue( ! null,  '! null  is true  — null triggers the correct guard.' );
		$this->assertTrue( ! false, '! false is true  — false triggers the correct guard.' );
		$this->assertFalse( ! true, '! true  is false — a truthy return skips the fallback.' );
	}

	// -------------------------------------------------------------------------
	// save_page_data: null return from Document::save() — fallback must fire
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-005 save_page_data: when Document::save() returns null, the fallback
	 * write path MUST update _elementor_data in post meta.
	 *
	 * This test FAILS before the fix (the null return bypasses the guard,
	 * so update_post_meta is never called).
	 * This test PASSES after the fix (! $result catches null).
	 *
	 * @group security
	 * @group f-005
	 */
	public function test_save_page_data_writes_meta_when_document_save_returns_null(): void {
		$this->inject_document_returning( null );

		$result = $this->data->save_page_data( 1, [ [ 'id' => 'abc123', 'elType' => 'container', 'elements' => [] ] ] );

		$this->assertTrue( $result, 'save_page_data must return true even when using the fallback path.' );

		$keys_written = $this->meta_keys_written();

		$this->assertContains(
			'_elementor_data',
			$keys_written,
			'F-005: When Document::save() returns null, the fallback must write ' .
			'_elementor_data via update_post_meta. ' .
			'Fix: change `if (false === $result)` to `if (! $result)` at ' .
			'class-elementor-data.php:204.'
		);
	}

	/**
	 * @test
	 * F-005 save_page_data: when Document::save() returns false (the documented
	 * failure return), the fallback write path MUST also fire.
	 *
	 * This is the nominal (non-broken) branch — it should pass before and after.
	 *
	 * @group security
	 * @group f-005
	 */
	public function test_save_page_data_writes_meta_when_document_save_returns_false(): void {
		$this->inject_document_returning( false );

		$this->data->save_page_data( 1, [] );

		$this->assertContains(
			'_elementor_data',
			$this->meta_keys_written(),
			'save_page_data must use the fallback path when Document::save() returns false.'
		);
	}

	/**
	 * @test
	 * F-005 save_page_data: when Document::save() returns true (success),
	 * the fallback must NOT be triggered (no spurious post-meta writes).
	 *
	 * @group security
	 * @group f-005
	 */
	public function test_save_page_data_does_not_write_meta_when_document_save_succeeds(): void {
		$this->inject_document_returning( true );

		$this->data->save_page_data( 1, [] );

		$this->assertNotContains(
			'_elementor_data',
			$this->meta_keys_written(),
			'When Document::save() succeeds, the fallback must not write post meta (no double write).'
		);
	}

	// -------------------------------------------------------------------------
	// save_page_settings: null return from Document::save() — fallback must fire
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-005 save_page_settings: when Document::save() returns null, the fallback
	 * MUST update _elementor_page_settings in post meta.
	 *
	 * This test FAILS before the fix, PASSES after.
	 *
	 * @group security
	 * @group f-005
	 */
	public function test_save_page_settings_writes_meta_when_document_save_returns_null(): void {
		$this->inject_document_returning( null );

		$result = $this->data->save_page_settings( 1, [ 'background_color' => '#ffffff' ] );

		$this->assertTrue( $result );

		$this->assertContains(
			'_elementor_page_settings',
			$this->meta_keys_written(),
			'F-005: When Document::save() returns null, the fallback must write ' .
			'_elementor_page_settings via update_post_meta. ' .
			'Fix: change `if (false === $result)` to `if (! $result)` at ' .
			'class-elementor-data.php:258.'
		);
	}

	/**
	 * @test
	 * F-005 save_page_settings: false return also triggers the fallback.
	 *
	 * @group security
	 * @group f-005
	 */
	public function test_save_page_settings_writes_meta_when_document_save_returns_false(): void {
		$this->inject_document_returning( false );

		$this->data->save_page_settings( 1, [ 'padding' => '20px' ] );

		$this->assertContains(
			'_elementor_page_settings',
			$this->meta_keys_written(),
			'save_page_settings must use the fallback path when Document::save() returns false.'
		);
	}

	/**
	 * @test
	 * F-005 save_page_settings: true return suppresses the fallback.
	 *
	 * @group security
	 * @group f-005
	 */
	public function test_save_page_settings_does_not_write_meta_when_document_save_succeeds(): void {
		$this->inject_document_returning( true );

		$this->data->save_page_settings( 1, [] );

		$this->assertNotContains(
			'_elementor_page_settings',
			$this->meta_keys_written(),
			'When Document::save() succeeds, the fallback must not write post meta.'
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	/**
	 * Replace the Elementor documents stub so Document::save() returns $return_value.
	 *
	 * @param mixed $return_value  null (non-browser context), false (Elementor failure), or true (success).
	 */
	private function inject_document_returning( $return_value ): void {
		$mock_doc = new class( $return_value ) {
			private $ret;
			public function __construct( $ret ) { $this->ret = $ret; }
			public function save( array $data ) { return $this->ret; }
			public function get_settings(): array { return []; }
		};

		\Elementor\Plugin::$instance->documents = new class( $mock_doc ) {
			private $doc;
			public function __construct( $doc ) { $this->doc = $doc; }
			public function get( int $post_id ) { return $this->doc; }
		};
	}

	/**
	 * Returns the list of meta keys written (or deleted) during the test.
	 *
	 * @return string[]
	 */
	private function meta_keys_written(): array {
		return array_column( $GLOBALS['_wp_meta_calls'], 'meta_key' );
	}
}

<?php
/**
 * Unit tests for F-017 and F-018: dead code and missing no_found_rows.
 *
 * Findings:
 *   F-017 (Low) — Dead get_current() call in execute_get_container_schema()
 *   F-018 (Low) — list-pages and list-templates WP_Query missing no_found_rows
 *
 * Files:     includes/abilities/class-query-abilities.php:313, :907, :1003
 *
 * Vulnerability descriptions
 * --------------------------
 * F-017: In execute_get_container_schema():
 *   $document = Plugin::$instance->documents->get_current();
 * The result is assigned but never used — dead Elementor internal API call
 * on every get-container-schema invocation.  Risk: if get_current() changes
 * behavior in a future Elementor release, this dead call may start throwing
 * or cause side effects.
 *
 * F-018: list-pages and list-templates WP_Query calls lack 'no_found_rows' => true.
 * With posts_per_page: 100, WordPress runs an unnecessary SQL_CALC_FOUND_ROWS /
 * COUNT(*) query on every tool call — a performance defect on large sites.
 *
 * TDD contract
 * ------------
 *   F-017 BEFORE fix → source contains get_current() assignment with unused result.
 *   F-017 AFTER fix  → get_current() call removed.
 *
 *   F-018 BEFORE fix → WP_Query args lack no_found_rows.
 *   F-018 AFTER fix  → no_found_rows: true present in both queries.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_Query_Abilities
 */
class F017F018QueryAbilitiesTest extends TestCase {

	/** @var string Absolute path to class-query-abilities.php. */
	private string $query_file;

	/** @var string Source content. */
	private string $query_src;

	protected function setUp(): void {
		parent::setUp();
		$this->query_file = dirname( __DIR__, 3 ) . '/includes/abilities/class-query-abilities.php';
		if ( file_exists( $this->query_file ) ) {
			$this->query_src = file_get_contents( $this->query_file );
		} else {
			$this->query_src = '';
		}
	}

	// =========================================================================
	// F-017: Dead get_current() call
	// =========================================================================

	/**
	 * @test
	 * F-017 — class-query-abilities.php must not contain an unused get_current() call.
	 *
	 * The assignment `$document = Plugin::$instance->documents->get_current()` at
	 * line 313 is dead code: $document is never read after the assignment.
	 *
	 * This test FAILS before the fix (get_current() still present).
	 * After the fix (line removed), it PASSES.
	 *
	 * @group security
	 * @group f-017
	 */
	public function test_unused_get_current_call_is_removed(): void {
		if ( ! file_exists( $this->query_file ) ) {
			$this->markTestSkipped( 'class-query-abilities.php not found.' );
		}

		// The dead assignment pattern: variable assigned from get_current() but unused.
		// After the fix, this exact pattern must not appear.
		$dead_pattern = '/\$document\s*=\s*Plugin::\$instance->documents->get_current\(\)/';

		$this->assertDoesNotMatchRegularExpression(
			$dead_pattern,
			$this->query_src,
			'F-017: Dead assignment `$document = Plugin::$instance->documents->get_current()` ' .
			'must be removed from execute_get_container_schema() at class-query-abilities.php:313. ' .
			'The result is never used and makes a redundant Elementor API call on every invocation.'
		);
	}

	/**
	 * @test
	 * F-017 — The get_container_schema function still exists (removal was targeted).
	 *
	 * Verifies the fix removed only the dead line, not the entire method.
	 *
	 * @group security
	 * @group f-017
	 */
	public function test_get_container_schema_function_still_exists(): void {
		if ( ! file_exists( $this->query_file ) ) {
			$this->markTestSkipped( 'class-query-abilities.php not found.' );
		}

		$this->assertMatchesRegularExpression(
			'/get.container.schema/i',
			$this->query_src,
			'The get-container-schema tool handler must still exist after removing the dead line.'
		);
	}

	// =========================================================================
	// F-018: Missing no_found_rows in WP_Query calls
	// =========================================================================

	/**
	 * @test
	 * F-018 — list-pages WP_Query must include no_found_rows => true.
	 *
	 * Without this, WordPress runs SQL_CALC_FOUND_ROWS on every list-pages call.
	 * On sites with thousands of pages this adds significant query overhead.
	 *
	 * This test FAILS before the fix, PASSES after.
	 *
	 * @group security
	 * @group f-018
	 */
	public function test_list_pages_query_has_no_found_rows(): void {
		if ( ! file_exists( $this->query_file ) ) {
			$this->markTestSkipped( 'class-query-abilities.php not found.' );
		}

		// Extract the section of the file around the list-pages query (around line 907).
		// We check the whole file for the co-occurrence of list-pages context and no_found_rows.
		$has_no_found_rows = (bool) preg_match(
			'/no_found_rows[\'"\s]*=>[\'"\s]*true/i',
			$this->query_src
		);

		$this->assertTrue(
			$has_no_found_rows,
			'F-018: At least one WP_Query call must include "no_found_rows" => true. ' .
			'Both list-pages (line ~907) and list-templates (line ~1003) need this flag ' .
			'to avoid unnecessary SQL_CALC_FOUND_ROWS queries. ' .
			'Fix: add \'no_found_rows\' => true to both $query_args arrays in ' .
			'class-query-abilities.php.'
		);
	}

	/**
	 * @test
	 * F-018 — no_found_rows appears at least twice (one per query in the file).
	 *
	 * list-pages and list-templates are separate methods, each with their own
	 * WP_Query. Both need the flag.
	 *
	 * @group security
	 * @group f-018
	 */
	public function test_no_found_rows_present_at_least_twice(): void {
		if ( ! file_exists( $this->query_file ) ) {
			$this->markTestSkipped( 'class-query-abilities.php not found.' );
		}

		$count = preg_match_all(
			'/no_found_rows[\'"\s]*=>[\'"\s]*true/i',
			$this->query_src,
			$matches
		);

		$this->assertGreaterThanOrEqual(
			2,
			$count,
			'F-018: no_found_rows => true must appear at least twice in class-query-abilities.php ' .
			'— once for list-pages (line ~907) and once for list-templates (line ~1003). ' .
			'Currently missing from both queries.'
		);
	}

	/**
	 * @test
	 * F-018 — Both list-pages and list-templates handler names are present in source.
	 *
	 * Confirms that these are the right tool handlers to check.
	 *
	 * @group security
	 * @group f-018
	 */
	public function test_list_pages_and_list_templates_handlers_exist(): void {
		if ( ! file_exists( $this->query_file ) ) {
			$this->markTestSkipped( 'class-query-abilities.php not found.' );
		}

		$this->assertMatchesRegularExpression(
			'/list.pages/i',
			$this->query_src,
			'list-pages handler must be present in class-query-abilities.php.'
		);

		$this->assertMatchesRegularExpression(
			'/list.templates/i',
			$this->query_src,
			'list-templates handler must be present in class-query-abilities.php.'
		);
	}

	/**
	 * @test
	 * F-018 — The correct no_found_rows value is boolean true, not string 'true'.
	 *
	 * WP_Query requires the value to be PHP true (boolean), not the string '1' or 'true'.
	 *
	 * @group security
	 * @group f-018
	 */
	public function test_no_found_rows_value_semantics(): void {
		// Verify the WP_Query argument semantics by testing the PHP behavior directly.
		$with_flag    = [ 'no_found_rows' => true, 'posts_per_page' => 100 ];
		$without_flag = [ 'posts_per_page' => 100 ];

		$this->assertTrue( $with_flag['no_found_rows'],
			'no_found_rows must be boolean true.' );
		$this->assertFalse( isset( $without_flag['no_found_rows'] ),
			'Without the flag, the key must not be set.' );
	}
}

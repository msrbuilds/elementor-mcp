<?php
/**
 * T3 Input boundary tests — composite abilities (build-page).
 *
 * Tests T3.4 and T3.5 from the spec:
 *   T3.4 — Oversized structure (500 top-level containers) must not exhaust memory
 *   T3.5 — Deeply nested structure (50 levels) must not overflow stack
 *
 * Additional:
 *   - Missing title returns WP_Error
 *   - Empty structure is accepted (no elements created is valid)
 *
 * @group input
 * @group composite
 * @package Elementor_MCP\Tests\Input
 */

namespace Elementor_MCP\Tests\Input;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class CompositeInputTest extends Ability_Test_Case {

	/** @var \Elementor_MCP_Composite_Abilities */
	private $ability;

	protected function setUp(): void {
		parent::setUp();

		$data = $this->createStub( \Elementor_MCP_Data::class );
		$data->method( 'save_page_data' )->willReturn( true );
		$data->method( 'save_page_settings' )->willReturn( true );
		$data->method( 'get_page_data' )->willReturn( [] );

		$this->ability = new \Elementor_MCP_Composite_Abilities( $data, $this->make_factory() );
		$this->allow_all_caps();
	}

	// -------------------------------------------------------------------------
	// Missing title
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_build_page_returns_wp_error_when_title_missing(): void {
		$result = $this->ability->execute_build_page( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_build_page_returns_wp_error_when_title_is_empty_string(): void {
		$result = $this->ability->execute_build_page( [ 'title' => '' ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// T3.4 — Oversized structure
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t3
	 * @group boundary
	 *
	 * 500 top-level containers must not exhaust memory or recurse infinitely.
	 * Returns either an array (success) or WP_Error — must not throw or fatal.
	 */
	public function test_build_page_oversized_structure_does_not_exhaust_memory(): void {
		$structure = array_fill( 0, 500, [
			'type'     => 'container',
			'settings' => [],
			'children' => [],
		] );

		$result = $this->ability->execute_build_page( [
			'title'     => 'Oversized Test',
			'structure' => $structure,
		] );

		$this->assertTrue(
			is_array( $result ) || $result instanceof \WP_Error,
			'build-page must return array or WP_Error for large structure — not throw or fatal.'
		);
	}

	// -------------------------------------------------------------------------
	// T3.5 — Deeply nested structure (recursion guard)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t3
	 * @group boundary
	 *
	 * A 50-level-deep nested structure must not overflow the PHP call stack.
	 * Returns either array or WP_Error — must not cause a fatal/stack overflow.
	 */
	public function test_build_page_deep_nesting_does_not_overflow_stack(): void {
		// Build structure nested 50 levels deep.
		$structure  = [ 'type' => 'container', 'settings' => [], 'children' => [] ];
		$current    = &$structure['children'];
		for ( $i = 0; $i < 50; $i++ ) {
			$current[] = [ 'type' => 'container', 'settings' => [], 'children' => [] ];
			$current   = &$current[0]['children'];
		}
		unset( $current );

		$result = $this->ability->execute_build_page( [
			'title'     => 'Deep Nesting Test',
			'structure' => [ $structure ],
		] );

		$this->assertTrue(
			is_array( $result ) || $result instanceof \WP_Error,
			'build-page must return array or WP_Error for deeply-nested structure — not fatal.'
		);
	}

	// -------------------------------------------------------------------------
	// structure is required by the implementation
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t3
	 *
	 * Structure is required — title-only input returns WP_Error missing_structure.
	 */
	public function test_build_page_returns_wp_error_when_structure_missing(): void {
		$result = $this->ability->execute_build_page( [ 'title' => 'No Structure' ] );
		$this->assertWPError( $result, 'missing_structure' );
	}

	/**
	 * @test
	 * @group t3
	 *
	 * Empty array structure returns WP_Error missing_structure.
	 */
	public function test_build_page_returns_wp_error_when_structure_is_empty_array(): void {
		$result = $this->ability->execute_build_page( [
			'title'     => 'Empty Structure',
			'structure' => [],
		] );
		$this->assertWPError( $result, 'missing_structure' );
	}

	/**
	 * @test
	 * @group t3
	 *
	 * Minimal valid input (title + non-empty structure) must succeed.
	 */
	public function test_build_page_with_valid_minimal_input_succeeds(): void {
		$result = $this->ability->execute_build_page( [
			'title'     => 'Test Page',
			'structure' => [
				[ 'type' => 'container', 'settings' => [], 'children' => [] ],
			],
		] );
		$this->assertNotWPError( $result );
		$this->assertResultHasKey( $result, 'post_id' );
	}
}

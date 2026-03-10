<?php
/**
 * T4 Functional tests — build-page happy-path output shape.
 *
 * Verifies that execute_build_page() returns the correct structure when
 * all inputs are valid: post_id, title, edit_url, preview_url, and
 * elements_created are all present with correct types/values.
 *
 * @group functional
 * @group composite
 * @package Elementor_MCP\Tests\Functional
 */

namespace Elementor_MCP\Tests\Functional;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class CompositeFunctionalTest extends Ability_Test_Case {

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

	private function minimal_input( array $overrides = [] ): array {
		return array_merge(
			[
				'title'     => 'Test Page',
				'structure' => [
					[ 'type' => 'container', 'settings' => [], 'children' => [] ],
				],
			],
			$overrides
		);
	}

	// -------------------------------------------------------------------------
	// build-page: output shape
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t4
	 *
	 * A valid build-page call returns an array with all required keys.
	 */
	public function test_build_page_returns_array_with_all_required_keys(): void {
		$result = $this->ability->execute_build_page( $this->minimal_input() );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertResultHasKey( $result, 'post_id' );
		$this->assertResultHasKey( $result, 'title' );
		$this->assertResultHasKey( $result, 'edit_url' );
		$this->assertResultHasKey( $result, 'preview_url' );
		$this->assertResultHasKey( $result, 'elements_created' );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * post_id is a positive integer.
	 */
	public function test_build_page_post_id_is_positive_integer(): void {
		$result = $this->ability->execute_build_page( $this->minimal_input() );

		$this->assertNotWPError( $result );
		$this->assertIsInt( $result['post_id'] );
		$this->assertGreaterThan( 0, $result['post_id'] );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * elements_created equals the number of containers in the structure.
	 */
	public function test_build_page_elements_created_counts_containers(): void {
		$structure = [
			[ 'type' => 'container', 'settings' => [], 'children' => [] ],
			[ 'type' => 'container', 'settings' => [], 'children' => [] ],
			[ 'type' => 'container', 'settings' => [], 'children' => [] ],
		];

		$result = $this->ability->execute_build_page( $this->minimal_input( [ 'structure' => $structure ] ) );

		$this->assertNotWPError( $result );
		$this->assertIsInt( $result['elements_created'] );
		// At minimum 3 top-level containers were created.
		$this->assertGreaterThanOrEqual( 3, $result['elements_created'] );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * Returned title matches input title.
	 */
	public function test_build_page_title_matches_input(): void {
		$result = $this->ability->execute_build_page( $this->minimal_input( [ 'title' => 'My Landing Page' ] ) );

		$this->assertNotWPError( $result );
		$this->assertSame( 'My Landing Page', $result['title'] );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * edit_url contains the post_id.
	 */
	public function test_build_page_edit_url_contains_post_id(): void {
		$result = $this->ability->execute_build_page( $this->minimal_input() );

		$this->assertNotWPError( $result );
		$this->assertStringContainsString( (string) $result['post_id'], $result['edit_url'] );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * Nested containers in structure are also counted in elements_created.
	 */
	public function test_build_page_counts_nested_containers(): void {
		$structure = [
			[
				'type'     => 'container',
				'settings' => [],
				'children' => [
					[ 'type' => 'container', 'settings' => [], 'children' => [] ],
					[ 'type' => 'container', 'settings' => [], 'children' => [] ],
				],
			],
		];

		$result = $this->ability->execute_build_page( $this->minimal_input( [ 'structure' => $structure ] ) );

		$this->assertNotWPError( $result );
		// 1 parent + 2 children = at least 3.
		$this->assertGreaterThanOrEqual( 3, $result['elements_created'] );
	}
}

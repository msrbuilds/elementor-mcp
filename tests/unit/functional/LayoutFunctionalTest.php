<?php
/**
 * T4 Functional tests — add-container happy-path output shape.
 *
 * Verifies that execute_add_container() returns the correct structure
 * when all inputs are valid and the data layer succeeds.
 *
 * The data stub is configured so get_page_data() returns an empty array
 * (valid existing page) and save_page_data() returns true.  A real
 * Elementor_MCP_Data::insert_element() is called (it mutates the array),
 * so the data stub does NOT stub insert_element — that method is on the
 * real data class and is not called through the stub.
 *
 * Because insert_element() is called on the real data object in the
 * ability, we inject a partial data stub: all methods stubbed except
 * insert_element (which is a real method).  We use a spy-style stub with
 * a real implementation injected via the class under test.
 *
 * To simplify, we inject a concrete Elementor_MCP_Data stub that always
 * succeeds by returning [] for get_page_data and true for save_page_data,
 * and we rely on the fact that insert_element on an empty $page_data
 * always appends the container at position 0 in the top level.
 *
 * @group functional
 * @group layout
 * @package Elementor_MCP\Tests\Functional
 */

namespace Elementor_MCP\Tests\Functional;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class LayoutFunctionalTest extends Ability_Test_Case {

	/** @var \Elementor_MCP_Layout_Abilities */
	private $ability;

	protected function setUp(): void {
		parent::setUp();

		// Data stub: page data returns empty array (valid empty page),
		// save returns true. insert_element is inherited from the real class
		// but since we're stubbing the full class we use a forwarding stub.
		$data = $this->make_data_stub_with_empty_page();

		$this->ability = new \Elementor_MCP_Layout_Abilities( $data, $this->make_factory() );
		$this->allow_all_caps();
	}

	/**
	 * Build a data stub where get_page_data returns [] and save returns true.
	 *
	 * insert_element is NOT a method on Elementor_MCP_Data's public interface
	 * used by Layout Abilities — the ability calls $this->data->insert_element().
	 * We need a real implementation for that.  We create a lightweight anonymous
	 * class that extends Elementor_MCP_Data to override only the I/O methods.
	 */
	private function make_data_stub_with_empty_page(): \Elementor_MCP_Data {
		return new class extends \Elementor_MCP_Data {
			public function __construct() {} // skip parent constructor

			public function get_page_data( int $post_id ): array {
				return []; // empty page — valid, no WP_Error
			}

			public function save_page_data( int $post_id, array $data ): bool {
				return true;
			}

			public function save_page_settings( int $post_id, array $settings ) {
				return true;
			}

			public function get_document( int $post_id ) {
				return new \WP_Error( 'document_not_found', 'No document.' );
			}
		};
	}

	// -------------------------------------------------------------------------
	// add-container: output shape
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t4
	 *
	 * A valid add-container call returns an array with element_id and post_id.
	 */
	public function test_add_container_returns_array_with_element_id_and_post_id(): void {
		$result = $this->ability->execute_add_container( [ 'post_id' => 42 ] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertResultHasKey( $result, 'element_id' );
		$this->assertResultHasKey( $result, 'post_id' );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * post_id in result matches input post_id.
	 */
	public function test_add_container_returned_post_id_matches_input(): void {
		$result = $this->ability->execute_add_container( [ 'post_id' => 99 ] );

		$this->assertNotWPError( $result );
		$this->assertSame( 99, $result['post_id'] );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * element_id is a non-empty string (7-char hex ID from Elementor_MCP_Id_Generator).
	 */
	public function test_add_container_element_id_is_non_empty_string(): void {
		$result = $this->ability->execute_add_container( [ 'post_id' => 10 ] );

		$this->assertNotWPError( $result );
		$this->assertIsString( $result['element_id'] );
		$this->assertNotEmpty( $result['element_id'] );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * Two add-container calls produce different element_ids (IDs are unique).
	 */
	public function test_add_container_produces_unique_element_ids(): void {
		$result1 = $this->ability->execute_add_container( [ 'post_id' => 10 ] );
		$result2 = $this->ability->execute_add_container( [ 'post_id' => 10 ] );

		$this->assertNotWPError( $result1 );
		$this->assertNotWPError( $result2 );
		$this->assertNotSame( $result1['element_id'], $result2['element_id'] );
	}
}

<?php
/**
 * T3 Input boundary tests — layout abilities.
 *
 * Verifies WP_Error returned for missing/invalid inputs across all 8 layout tools.
 *
 * @group input
 * @group layout
 * @package Elementor_MCP\Tests\Input
 */

namespace Elementor_MCP\Tests\Input;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class LayoutInputTest extends Ability_Test_Case {

	/** @var \Elementor_MCP_Layout_Abilities */
	private $ability;

	protected function setUp(): void {
		parent::setUp();

		$data = $this->createStub( \Elementor_MCP_Data::class );
		$data->method( 'get_page_data' )
		     ->willReturn( new \WP_Error( 'no_data', 'No page data in test stub.' ) );
		$data->method( 'save_page_data' )->willReturn( true );

		$this->ability = new \Elementor_MCP_Layout_Abilities( $data, $this->make_factory() );
		$this->allow_all_caps();
	}

	// -------------------------------------------------------------------------
	// add-container
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_add_container_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_add_container( [] );
		$this->assertWPError( $result, 'missing_post_id' );
	}

	/** @test @group t3 */
	public function test_add_container_returns_wp_error_when_post_id_zero(): void {
		$result = $this->ability->execute_add_container( [ 'post_id' => 0 ] );
		$this->assertWPError( $result, 'missing_post_id' );
	}

	/** @test @group t3 */
	public function test_add_container_returns_wp_error_for_invalid_post(): void {
		// Data stub returns WP_Error for get_page_data → propagates as WP_Error.
		$result = $this->ability->execute_add_container( [ 'post_id' => 999 ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// update-container
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_update_container_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_update_container( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_update_container_returns_wp_error_when_element_id_missing(): void {
		$result = $this->ability->execute_update_container( [ 'post_id' => 1 ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// update-element
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_update_element_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_update_element( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_update_element_returns_wp_error_when_element_id_missing(): void {
		$result = $this->ability->execute_update_element( [ 'post_id' => 1 ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// batch-update
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_batch_update_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_batch_update( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_batch_update_returns_wp_error_when_updates_missing(): void {
		$result = $this->ability->execute_batch_update( [ 'post_id' => 1 ] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_batch_update_returns_wp_error_when_updates_is_empty_array(): void {
		$result = $this->ability->execute_batch_update( [ 'post_id' => 1, 'updates' => [] ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// reorder-elements
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_reorder_elements_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_reorder_elements( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_reorder_elements_returns_wp_error_when_element_ids_missing(): void {
		$result = $this->ability->execute_reorder_elements( [ 'post_id' => 1 ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// move-element
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_move_element_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_move_element( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_move_element_returns_wp_error_when_element_id_missing(): void {
		$result = $this->ability->execute_move_element( [ 'post_id' => 1 ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// remove-element
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_remove_element_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_remove_element( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_remove_element_returns_wp_error_when_element_id_missing(): void {
		$result = $this->ability->execute_remove_element( [ 'post_id' => 1 ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// duplicate-element
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_duplicate_element_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_duplicate_element( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_duplicate_element_returns_wp_error_when_element_id_missing(): void {
		$result = $this->ability->execute_duplicate_element( [ 'post_id' => 1 ] );
		$this->assertWPError( $result );
	}
}

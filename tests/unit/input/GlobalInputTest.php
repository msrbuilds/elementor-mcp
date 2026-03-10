<?php
/**
 * T3 Input boundary tests — global abilities.
 *
 * Verifies that update-global-colors and update-global-typography return
 * WP_Error when required parameters are missing or empty.
 *
 * Note: tests beyond the missing-parameter guard would require a live
 * Elementor kit — those scenarios are covered by functional/integration tests.
 *
 * @group input
 * @group global
 * @package Elementor_MCP\Tests\Input
 */

namespace Elementor_MCP\Tests\Input;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class GlobalInputTest extends Ability_Test_Case {

	/** @var \Elementor_MCP_Global_Abilities */
	private $ability;

	protected function setUp(): void {
		parent::setUp();
		// Global abilities access Plugin::$instance->kits_manager (stubbed in bootstrap).
		$data          = $this->createStub( \Elementor_MCP_Data::class );
		$this->ability = new \Elementor_MCP_Global_Abilities( $data );
		$this->allow_all_caps();
	}

	// -------------------------------------------------------------------------
	// update-global-colors: input validation happens before kit access
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_update_global_colors_returns_wp_error_when_colors_missing(): void {
		$result = $this->ability->execute_update_global_colors( [] );
		$this->assertWPError( $result, 'missing_colors' );
	}

	/** @test @group t3 */
	public function test_update_global_colors_returns_wp_error_when_colors_is_empty_array(): void {
		$result = $this->ability->execute_update_global_colors( [ 'colors' => [] ] );
		$this->assertWPError( $result, 'missing_colors' );
	}

	/**
	 * @test
	 * @group t3
	 *
	 * When colors are valid (non-empty), the code proceeds to kit access which
	 * returns WP_Error (kit_not_found) because the test stub kits_manager
	 * returns null from get_active_kit().
	 */
	public function test_update_global_colors_returns_wp_error_when_kit_not_found(): void {
		$result = $this->ability->execute_update_global_colors( [
			'colors' => [
				[ '_id' => 'primary', 'title' => 'Primary', 'color' => '#FF0000' ],
			],
		] );
		$this->assertWPError( $result, 'kit_not_found' );
	}

	// -------------------------------------------------------------------------
	// update-global-typography: input validation before kit access
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_update_global_typography_returns_wp_error_when_typography_missing(): void {
		$result = $this->ability->execute_update_global_typography( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_update_global_typography_returns_wp_error_when_typography_is_empty_array(): void {
		$result = $this->ability->execute_update_global_typography( [ 'typography' => [] ] );
		$this->assertWPError( $result );
	}
}

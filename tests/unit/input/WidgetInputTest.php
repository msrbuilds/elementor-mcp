<?php
/**
 * T3 Input boundary tests — widget abilities.
 *
 * Tests that add-widget and update-widget return WP_Error for missing/invalid inputs.
 *
 * @group input
 * @group widget
 * @package Elementor_MCP\Tests\Input
 */

namespace Elementor_MCP\Tests\Input;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class WidgetInputTest extends Ability_Test_Case {

	/** @var \Elementor_MCP_Widget_Abilities */
	private $ability;

	protected function setUp(): void {
		parent::setUp();

		$data = $this->createStub( \Elementor_MCP_Data::class );
		$data->method( 'get_page_data' )
		     ->willReturn( new \WP_Error( 'no_data', 'No page data.' ) );
		$data->method( 'save_page_data' )->willReturn( true );

		$schema    = $this->createStub( \Elementor_MCP_Schema_Generator::class );
		$validator = $this->createStub( \Elementor_MCP_Settings_Validator::class );
		$validator->method( 'validate' )->willReturn( true );

		$this->ability = new \Elementor_MCP_Widget_Abilities(
			$data, $this->make_factory(), $schema, $validator
		);
		$this->allow_all_caps();
	}

	// -------------------------------------------------------------------------
	// add-widget: missing required params (post_id, parent_id, widget_type)
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_add_widget_returns_wp_error_when_all_params_missing(): void {
		$result = $this->ability->execute_add_widget( [] );
		$this->assertWPError( $result, 'missing_params' );
	}

	/** @test @group t3 */
	public function test_add_widget_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_add_widget( [
			'parent_id'   => 'abc123',
			'widget_type' => 'heading',
		] );
		$this->assertWPError( $result, 'missing_params' );
	}

	/** @test @group t3 */
	public function test_add_widget_returns_wp_error_when_parent_id_missing(): void {
		$result = $this->ability->execute_add_widget( [
			'post_id'     => 1,
			'widget_type' => 'heading',
		] );
		$this->assertWPError( $result, 'missing_params' );
	}

	/** @test @group t3 */
	public function test_add_widget_returns_wp_error_when_widget_type_missing(): void {
		$result = $this->ability->execute_add_widget( [
			'post_id'   => 1,
			'parent_id' => 'abc123',
		] );
		$this->assertWPError( $result, 'missing_params' );
	}

	/**
	 * @test
	 * @group t3
	 *
	 * Widgets_manager returns null → invalid_widget_type error.
	 * This confirms the widget type validation runs before the DB write.
	 */
	public function test_add_widget_returns_wp_error_for_unknown_widget_type(): void {
		// Plugin stub's widgets_manager::get_widget_types() always returns null.
		$result = $this->ability->execute_add_widget( [
			'post_id'     => 1,
			'parent_id'   => 'abc123',
			'widget_type' => 'nonexistent-widget-type',
		] );
		$this->assertWPError( $result, 'invalid_widget_type' );
	}

	// -------------------------------------------------------------------------
	// update-widget: missing required params
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_update_widget_returns_wp_error_when_all_params_missing(): void {
		$result = $this->ability->execute_update_widget( [] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_update_widget_returns_wp_error_when_element_id_missing(): void {
		$result = $this->ability->execute_update_widget( [
			'post_id'  => 1,
			'settings' => [],
		] );
		$this->assertWPError( $result );
	}

	/** @test @group t3 */
	public function test_update_widget_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_update_widget( [
			'element_id' => 'abc123',
			'settings'   => [],
		] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// T3.5 — boundary: post_id = 0 treated as missing
	// -------------------------------------------------------------------------

	/** @test @group t3 */
	public function test_add_widget_treats_zero_post_id_as_missing(): void {
		$result = $this->ability->execute_add_widget( [
			'post_id'     => 0,
			'parent_id'   => 'abc123',
			'widget_type' => 'heading',
		] );
		$this->assertWPError( $result, 'missing_params' );
	}
}

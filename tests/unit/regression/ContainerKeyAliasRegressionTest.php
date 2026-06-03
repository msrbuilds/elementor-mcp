<?php
/**
 * Regression test — issue #32.
 *
 * The MCP tool descriptions accept `justify_content`, `align_items`, and
 * `align_content` as container settings, but Elementor's container schema
 * reads these under the prefixed keys `flex_justify_content`,
 * `flex_align_items`, and `flex_align_content`. Without the remap the
 * values are persisted but Elementor's CSS generator never emits the
 * corresponding `--justify-content` / `--align-items` custom properties,
 * so the container renders with default alignment.
 *
 * These tests pin the remap behaviour so future refactors don't reopen
 * the bug.
 *
 * @group regression
 * @group issue-32
 * @package EMCP_Tools\Tests\Regression
 */

namespace EMCP_Tools\Tests\Regression;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ContainerKeyAliasRegressionTest extends Ability_Test_Case {

	// -------------------------------------------------------------------------
	// normalize_container_settings — the helper itself
	// -------------------------------------------------------------------------

	/** @test */
	public function test_normalize_remaps_unprefixed_flex_keys(): void {
		$out = \EMCP_Tools_Element_Factory::normalize_container_settings( [
			'justify_content' => 'space-between',
			'align_items'     => 'center',
			'align_content'   => 'flex-start',
		] );

		$this->assertSame( 'space-between', $out['flex_justify_content'] );
		$this->assertSame( 'center', $out['flex_align_items'] );
		$this->assertSame( 'flex-start', $out['flex_align_content'] );
		$this->assertArrayNotHasKey( 'justify_content', $out );
		$this->assertArrayNotHasKey( 'align_items', $out );
		$this->assertArrayNotHasKey( 'align_content', $out );
	}

	/** @test */
	public function test_normalize_preserves_already_prefixed_keys(): void {
		$out = \EMCP_Tools_Element_Factory::normalize_container_settings( [
			'flex_justify_content' => 'space-between',
			'flex_align_items'     => 'center',
		] );

		$this->assertSame( 'space-between', $out['flex_justify_content'] );
		$this->assertSame( 'center', $out['flex_align_items'] );
	}

	/** @test */
	public function test_normalize_prefers_prefixed_when_both_are_set(): void {
		$out = \EMCP_Tools_Element_Factory::normalize_container_settings( [
			'justify_content'      => 'flex-start',
			'flex_justify_content' => 'space-between',
		] );

		$this->assertSame( 'space-between', $out['flex_justify_content'] );
		$this->assertArrayNotHasKey( 'justify_content', $out );
	}

	/** @test */
	public function test_normalize_passes_unrelated_keys_through(): void {
		$out = \EMCP_Tools_Element_Factory::normalize_container_settings( [
			'flex_direction' => 'row',
			'flex_wrap'      => 'nowrap',
			'gap'            => [ 'size' => 20, 'unit' => 'px' ],
			'padding'        => [ 'top' => '10', 'right' => '10', 'bottom' => '10', 'left' => '10', 'unit' => 'px' ],
		] );

		$this->assertSame( 'row', $out['flex_direction'] );
		$this->assertSame( 'nowrap', $out['flex_wrap'] );
		$this->assertSame( 20, $out['gap']['size'] );
		$this->assertSame( '10', $out['padding']['top'] );
	}

	// -------------------------------------------------------------------------
	// create_container — settings written into the element
	// -------------------------------------------------------------------------

	/** @test */
	public function test_create_container_remaps_user_supplied_shortcuts(): void {
		$factory   = $this->make_factory();
		$container = $factory->create_container( [
			'flex_direction'  => 'row',
			'justify_content' => 'space-between',
			'align_items'     => 'center',
		] );

		$this->assertSame( 'space-between', $container['settings']['flex_justify_content'] );
		$this->assertSame( 'center', $container['settings']['flex_align_items'] );
		$this->assertArrayNotHasKey( 'justify_content', $container['settings'] );
		$this->assertArrayNotHasKey( 'align_items', $container['settings'] );
	}

	/** @test */
	public function test_create_container_default_auto_center_uses_prefixed_key(): void {
		$factory = $this->make_factory();

		// Flex-column container with no flex_align_items provided — factory auto-centers.
		$container = $factory->create_container( [] );

		$this->assertSame( 'center', $container['settings']['flex_align_items'] );
		$this->assertArrayNotHasKey( 'align_items', $container['settings'] );
	}

	/** @test */
	public function test_create_container_user_supplied_align_items_wins_over_default(): void {
		$factory = $this->make_factory();

		// User passes shorthand — should be remapped, NOT overridden by the auto-center default.
		$container = $factory->create_container( [
			'align_items' => 'flex-end',
		] );

		$this->assertSame( 'flex-end', $container['settings']['flex_align_items'] );
	}

	// -------------------------------------------------------------------------
	// update_element_settings — partial-merge update path
	// -------------------------------------------------------------------------

	/** @test */
	public function test_update_element_settings_remaps_for_containers(): void {
		$data = new \EMCP_Tools_Data();

		$tree = [
			[
				'id'       => 'abc1234',
				'elType'   => 'container',
				'settings' => [ 'flex_direction' => 'row' ],
				'elements' => [],
			],
		];

		$ok = $data->update_element_settings( $tree, 'abc1234', [
			'justify_content' => 'space-between',
			'align_items'     => 'center',
		] );

		$this->assertTrue( $ok );
		$this->assertSame( 'space-between', $tree[0]['settings']['flex_justify_content'] );
		$this->assertSame( 'center', $tree[0]['settings']['flex_align_items'] );
		$this->assertArrayNotHasKey( 'justify_content', $tree[0]['settings'] );
		$this->assertArrayNotHasKey( 'align_items', $tree[0]['settings'] );
	}

	/** @test */
	public function test_update_element_settings_does_not_remap_for_widgets(): void {
		// Widgets like the Pro nav-menu legitimately use `align_items` as a
		// widget-level setting — the container key alias must not affect them.
		$data = new \EMCP_Tools_Data();

		$tree = [
			[
				'id'         => 'def5678',
				'elType'     => 'widget',
				'widgetType' => 'nav-menu',
				'settings'   => [],
				'elements'   => [],
			],
		];

		$ok = $data->update_element_settings( $tree, 'def5678', [
			'align_items' => 'center',
		] );

		$this->assertTrue( $ok );
		$this->assertSame( 'center', $tree[0]['settings']['align_items'] );
		$this->assertArrayNotHasKey( 'flex_align_items', $tree[0]['settings'] );
	}
}

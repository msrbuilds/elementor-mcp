<?php
/**
 * Catalog-backed widget tool registration + gating tests.
 * @group widgets
 * @package EMCP_Tools\Tests\Widgets
 */
namespace EMCP_Tools\Tests\Widgets;

require_once dirname(__DIR__) . '/class-ability-test-case.php';
require_once dirname(__DIR__, 2) . '/../includes/widgets/class-widget-catalog.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class CatalogToolsTest extends Ability_Test_Case {
	private \EMCP_Tools_Widget_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$data      = $this->createStub( \EMCP_Tools_Data::class );
		$factory   = $this->make_factory();
		$schema    = $this->createStub( \EMCP_Tools_Schema_Generator::class );
		$validator = $this->createStub( \EMCP_Tools_Settings_Validator::class );
		$this->ability = new \EMCP_Tools_Widget_Abilities( $data, $factory, $schema, $validator );
		$this->ability->register();
	}

	/** @test */
	public function test_registers_the_catalog_tools(): void {
		$names = $this->ability->get_ability_names();
		$this->assertContains( 'emcp-tools/add-free-widget', $names );
		$this->assertContains( 'emcp-tools/update-widget', $names );
	}

	/** @test */
	public function test_does_not_register_old_convenience_tools(): void {
		$names = $this->ability->get_ability_names();
		foreach ( array( 'emcp-tools/add-heading', 'emcp-tools/add-button', 'emcp-tools/add-form', 'emcp-tools/add-widget' ) as $gone ) {
			$this->assertNotContains( $gone, $names, "$gone must be removed in v3.0.0" );
		}
	}

	/** @test */
	public function test_add_pro_widget_not_registered_without_pro(): void {
		$this->assertFalse( defined( 'ELEMENTOR_PRO_VERSION' ) );
		$this->assertNotContains( 'emcp-tools/add-pro-widget', $this->ability->get_ability_names() );
	}

	/** @test */
	public function test_add_free_widget_rejects_pro_type(): void {
		$this->allow_caps( 'edit_posts' );
		$result = $this->ability->execute_add_free_widget( array(
			'post_id' => 1, 'parent_id' => 'abc1234', 'widget_type' => 'form', 'settings' => array(),
		) );
		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'wrong_tier', $result->get_error_code() );
	}

	/** @test */
	public function test_add_free_widget_rejects_unknown_type(): void {
		$this->allow_caps( 'edit_posts' );
		$result = $this->ability->execute_add_free_widget( array(
			'post_id' => 1, 'parent_id' => 'abc1234', 'widget_type' => 'totally-fake', 'settings' => array(),
		) );
		$this->assertInstanceOf( \WP_Error::class, $result );
	}

	/** @test */
	public function test_list_widgets_compact_from_catalog(): void {
		$query = new \EMCP_Tools_Query_Abilities(
			$this->createStub( \EMCP_Tools_Data::class ),
			$this->createStub( \EMCP_Tools_Schema_Generator::class )
		);
		$out = $query->execute_list_widgets( array( 'tier' => 'free', 'search' => 'headline' ) );
		$this->assertArrayHasKey( 'widgets', $out );
		$types = array_column( $out['widgets'], 'type' );
		$this->assertContains( 'heading', $types );
		$this->assertNotContains( 'form', $types, 'tier:free must exclude Pro widgets' );
		// Compact shape: use_case + param_names present.
		$idx     = array_search( 'heading', $types, true );
		$heading = $out['widgets'][ $idx ];
		$this->assertArrayHasKey( 'use_case', $heading );
		$this->assertArrayHasKey( 'param_names', $heading );
		$this->assertIsArray( $heading['param_names'] );
	}

	/** @test */
	public function test_list_widgets_pro_filter(): void {
		$query = new \EMCP_Tools_Query_Abilities(
			$this->createStub( \EMCP_Tools_Data::class ),
			$this->createStub( \EMCP_Tools_Schema_Generator::class )
		);
		$out   = $query->execute_list_widgets( array( 'tier' => 'pro' ) );
		$types = array_column( $out['widgets'], 'type' );
		$this->assertContains( 'form', $types );
		$this->assertNotContains( 'heading', $types );
	}

	/** @test */
	public function test_get_widget_schema_curated_default(): void {
		$query = new \EMCP_Tools_Query_Abilities(
			$this->createStub( \EMCP_Tools_Data::class ),
			$this->createStub( \EMCP_Tools_Schema_Generator::class )
		);
		$out = $query->execute_get_widget_schema( array( 'widget_type' => 'heading' ) );
		$this->assertArrayHasKey( 'widget_type', $out );
		$this->assertSame( 'heading', $out['widget_type'] );
		$this->assertArrayHasKey( 'params', $out, 'curated mode returns catalog params' );
		$this->assertArrayHasKey( 'title', $out['params'] );
	}

	/** @test */
	public function test_get_widget_schema_batch(): void {
		$query = new \EMCP_Tools_Query_Abilities(
			$this->createStub( \EMCP_Tools_Data::class ),
			$this->createStub( \EMCP_Tools_Schema_Generator::class )
		);
		$out = $query->execute_get_widget_schema( array( 'types' => array( 'heading', 'button' ) ) );
		$this->assertArrayHasKey( 'widgets', $out );
		$returned = array_column( $out['widgets'], 'widget_type' );
		$this->assertContains( 'heading', $returned );
		$this->assertContains( 'button', $returned );
	}

	/** @test */
	public function test_get_widget_schema_missing_input_errors(): void {
		$query = new \EMCP_Tools_Query_Abilities(
			$this->createStub( \EMCP_Tools_Data::class ),
			$this->createStub( \EMCP_Tools_Schema_Generator::class )
		);
		$out = $query->execute_get_widget_schema( array() );
		$this->assertInstanceOf( \WP_Error::class, $out );
	}
}

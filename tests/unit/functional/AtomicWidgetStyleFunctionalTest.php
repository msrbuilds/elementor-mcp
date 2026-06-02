<?php
/**
 * Functional — atomic widgets carry a styles map when style props are passed.
 *
 * @group functional
 * @group atomic
 * @package Elementor_MCP\Tests\Functional
 */

namespace Elementor_MCP\Tests\Functional;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class AtomicWidgetStyleFunctionalTest extends Ability_Test_Case {

	public function test_factory_widget_without_style_props_has_empty_styles(): void {
		$el = $this->make_factory()->create_atomic_widget( 'e-heading', array() );
		$this->assertSame( array(), $el['styles'] );
	}

	public function test_factory_widget_with_style_props_populates_styles_and_classes(): void {
		$el = $this->make_factory()->create_atomic_widget(
			'e-heading',
			array(),
			array( 'color' => '#112233', 'font_size' => 40 )
		);
		$this->assertNotEmpty( $el['styles'], 'styles map should be populated' );
		// apply_to_element adds the local class id to settings.classes.
		$this->assertArrayHasKey( 'classes', $el['settings'] );
		$this->assertNotEmpty( $el['settings']['classes']['value'] );
	}

	public function test_factory_typography_only_props_still_style_the_widget(): void {
		// font_size alone (no color/spacing) must produce a styles map — proves
		// typography is wired, not just the pre-existing common props.
		$el = $this->make_factory()->create_atomic_widget( 'e-heading', array(), array( 'font_size' => 48 ) );
		$this->assertNotEmpty( $el['styles'] );
	}

	/**
	 * Bare add-atomic-widget (the public executor) must apply flat style props
	 * from input — covers the end-to-end ability path that the convenience
	 * closures share.
	 */
	private function ability(): \Elementor_MCP_Atomic_Widget_Abilities {
		$data = new class extends \Elementor_MCP_Data {
			public function __construct() {}
			public function get_page_data( int $post_id ): array { return array(); }
			public function save_page_data( int $post_id, array $data ): bool {
				$GLOBALS['_saved_page'] = $data; // capture for assertions
				return true;
			}
		};
		return new \Elementor_MCP_Atomic_Widget_Abilities( $data, $this->make_factory() );
	}

	public function test_add_atomic_widget_applies_flat_style_props(): void {
		$GLOBALS['_saved_page'] = null;
		$res = $this->ability()->execute_add_atomic_widget( array(
			'post_id'     => 7,
			'parent_id'   => '', // top-level append
			'widget_type' => 'e-heading',
			'settings'    => array(),
			'color'       => '#0a0a0a',
			'font_size'   => 40,
		) );
		$this->assertNotWPError( $res );
		$this->assertNotEmpty( $GLOBALS['_saved_page'][0]['styles'], 'saved widget should carry a styles map' );
	}

	public function test_add_atomic_widget_without_style_props_has_no_styles(): void {
		$GLOBALS['_saved_page'] = null;
		$res = $this->ability()->execute_add_atomic_widget( array(
			'post_id'     => 7,
			'parent_id'   => '',
			'widget_type' => 'e-heading',
			'settings'    => array(),
		) );
		$this->assertNotWPError( $res );
		$this->assertSame( array(), $GLOBALS['_saved_page'][0]['styles'], 'no style props -> empty styles (backward compat)' );
	}
}

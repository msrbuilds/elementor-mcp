<?php
/**
 * Regression — atomic save path must pass the page-data array, not a bool.
 *
 * Three atomic ability methods built the element, mutated the page-data array
 * by reference via a bool-returning helper, then passed that bool to
 * save_page_data():
 *
 *   - execute_add_atomic_widget()    -> insert_element() returns bool
 *   - execute_update_atomic_widget() -> update_element_settings() returns bool
 *   - execute_add_flexbox/div_block() -> insert_element() returns bool in the
 *                                        nested-parent branch
 *
 * save_page_data() is typed `array $data`, so passing the bool raised a
 * TypeError and broke the tool. The top-level add-flexbox path happened to set
 * the var to the array, which is why a top-level flexbox "worked" while a
 * nested one (non-empty parent_id) blew up.
 *
 * Each test drives the nested branch (a real parent element exists in the page)
 * so the helper returns a bool, and asserts the call returns the normal result
 * array instead of throwing. The data stub's `array $data` parameter is the
 * guard: a bool would TypeError before any assertion runs.
 *
 * @group regression
 * @group atomic
 * @package Elementor_MCP\Tests\Regression
 */

namespace Elementor_MCP\Tests\Regression;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class AtomicSaveRegressionTest extends Ability_Test_Case {

	/**
	 * Data stub whose page already contains a parent flexbox with one child
	 * widget, so nested inserts/updates resolve and the by-ref helpers return a
	 * bool. save_page_data() keeps the real `array $data` type hint.
	 */
	private function make_nested_page_stub(): \Elementor_MCP_Data {
		return new class extends \Elementor_MCP_Data {
			public function __construct() {} // skip parent constructor

			public function get_page_data( int $post_id ): array {
				return array(
					array(
						'id'       => 'parent01',
						'elType'   => 'e-flexbox',
						'settings' => array(),
						'elements' => array(
							array(
								'id'         => 'child01',
								'elType'     => 'widget',
								'widgetType' => 'e-heading',
								'settings'   => array(),
								'elements'   => array(),
							),
						),
					),
				);
			}

			public function save_page_data( int $post_id, array $data ): bool {
				return true; // a bool arg from the ability would TypeError here
			}
		};
	}

	public function test_add_atomic_widget_into_parent_saves_array_not_bool(): void {
		$ability = new \Elementor_MCP_Atomic_Widget_Abilities( $this->make_nested_page_stub(), $this->make_factory() );

		$result = $ability->execute_add_atomic_widget( array(
			'post_id'     => 42,
			'parent_id'   => 'parent01',
			'widget_type' => 'e-heading',
			'settings'    => array(),
		) );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertResultHasKey( $result, 'element_id' );
	}

	public function test_update_atomic_widget_saves_array_not_bool(): void {
		$ability = new \Elementor_MCP_Atomic_Widget_Abilities( $this->make_nested_page_stub(), $this->make_factory() );

		$result = $ability->execute_update_atomic_widget( array(
			'post_id'    => 42,
			'element_id' => 'child01',
			'settings'   => array( 'title' => array( '$$type' => 'string', 'value' => 'Hi' ) ),
		) );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
	}

	public function test_add_flexbox_into_parent_saves_array_not_bool(): void {
		$ability = new \Elementor_MCP_Atomic_Layout_Abilities( $this->make_nested_page_stub(), $this->make_factory() );

		$result = $ability->execute_add_flexbox( array(
			'post_id'   => 42,
			'parent_id' => 'parent01', // nested -> insert_element() returns bool
		) );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertResultHasKey( $result, 'element_id' );
	}
}

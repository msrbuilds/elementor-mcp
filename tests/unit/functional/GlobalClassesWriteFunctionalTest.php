<?php
/**
 * Functional — Global Classes write tools (create/update/delete/apply) drive a
 * fake in-memory Global_Classes_Repository (declared in bootstrap.php) end to
 * end, and apply rejects a non-atomic element with schema-in-error.
 *
 * @group functional
 * @group global-classes
 * @package Elementor_MCP\Tests\Functional
 */

namespace Elementor_MCP\Tests\Functional;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;
use Elementor\Modules\GlobalClasses\Global_Classes_Repository;

class GlobalClassesWriteFunctionalTest extends Ability_Test_Case {

	private \Elementor_MCP_Global_Classes_Write_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		Global_Classes_Repository::__reset( array(), array() );
		$data          = $this->createStub( \Elementor_MCP_Data::class );
		$this->ability = new \Elementor_MCP_Global_Classes_Write_Abilities( $data );
	}

	// -------------------------------------------------------------------------
	// A data stub backed by an in-memory page for the apply() path.
	// -------------------------------------------------------------------------
	private function ability_with_page( array $page ): \Elementor_MCP_Global_Classes_Write_Abilities {
		$data = new class( $page ) extends \Elementor_MCP_Data {
			private array $page;
			public function __construct( array $page ) {
				$this->page = $page;
			}
			public function get_page_data( int $post_id ) {
				return $this->page;
			}
			public function save_page_data( int $post_id, array $data ) {
				$GLOBALS['_saved_page'] = $data;
				return true;
			}
		};
		return new \Elementor_MCP_Global_Classes_Write_Abilities( $data );
	}

	// =========================================================================
	// create
	// =========================================================================

	public function test_create_adds_entry_and_returns_g_id(): void {
		$res = $this->ability->execute_create( array(
			'label'  => 'card-base',
			'styles' => array( 'color' => '#111111', 'padding' => 24 ),
		) );

		$this->assertNotWPError( $res );
		$this->assertResultHasKey( $res, 'id' );
		$this->assertMatchesRegularExpression( '/^g-[0-9a-f]{7}$/', $res['id'], 'id must be g-<7hex>' );
		$this->assertTrue( $res['created'] );

		// Entry landed in the repo store, in items + order.
		$id = $res['id'];
		$this->assertArrayHasKey( $id, Global_Classes_Repository::$store_items );
		$this->assertContains( $id, Global_Classes_Repository::$store_order );

		$entry = Global_Classes_Repository::$store_items[ $id ];
		$this->assertSame( 'class', $entry['type'] );
		$this->assertSame( 'card-base', $entry['label'] );

		// Base variant carries wrapped ($$type) props.
		$base = $entry['variants'][0];
		$this->assertSame( 'desktop', $base['meta']['breakpoint'], 'base variant stored with string desktop breakpoint' );
		$this->assertNull( $base['meta']['state'] );
		$this->assertSame( 'color', $base['props']['color']['$$type'] );
		$this->assertSame( '#111111', $base['props']['color']['value'] );
		$this->assertSame( 'size', $base['props']['padding']['$$type'] );
		$this->assertSame( 24.0, $base['props']['padding']['value']['size'] );
	}

	public function test_create_with_extra_variant(): void {
		$res = $this->ability->execute_create( array(
			'label'    => 'hero',
			'styles'   => array( 'color' => '#000000' ),
			'variants' => array(
				array( 'breakpoint' => 'tablet', 'state' => null, 'styles' => array( 'color' => '#222222' ) ),
			),
		) );
		$this->assertNotWPError( $res );
		$entry = Global_Classes_Repository::$store_items[ $res['id'] ];
		$this->assertCount( 2, $entry['variants'] );
		$this->assertSame( 'tablet', $entry['variants'][1]['meta']['breakpoint'] );
	}

	public function test_create_wraps_numeric_props_as_number(): void {
		$res = $this->ability->execute_create( array(
			'label'  => 'stack',
			'styles' => array( 'z-index' => 5, 'order' => '3' ),
		) );
		$this->assertNotWPError( $res );
		$base = Global_Classes_Repository::$store_items[ $res['id'] ]['variants'][0]['props'];
		$this->assertSame( 'number', $base['z-index']['$$type'] );
		$this->assertSame( 5, $base['z-index']['value'] );
		$this->assertSame( 'number', $base['order']['$$type'] );
		$this->assertSame( 3, $base['order']['value'] );
	}

	public function test_create_rejects_at_class_limit(): void {
		// Seed the repository at Elementor's 100-class cap.
		$items = array();
		$order = array();
		for ( $i = 0; $i < 100; $i++ ) {
			$id           = 'g-lim' . $i;
			$items[ $id ] = array( 'id' => $id, 'type' => 'class', 'label' => 'c' . $i, 'variants' => array() );
			$order[]      = $id;
		}
		Global_Classes_Repository::__reset( $items, $order );

		$res = $this->ability->execute_create( array(
			'label'  => 'one-too-many',
			'styles' => array( 'color' => '#000000' ),
		) );
		$this->assertWPError( $res, 'class_limit_reached' );
		$this->assertCount( 100, Global_Classes_Repository::$store_items, 'nothing written past the cap' );
	}

	public function test_writes_use_the_touched_item_api_and_preserve_others(): void {
		// An unrelated pre-existing class that must never appear in the touched set
		// (on real Elementor, touching it would clear its preview draft).
		Global_Classes_Repository::__reset(
			array( 'g-other01' => array( 'id' => 'g-other01', 'type' => 'class', 'label' => 'keep', 'variants' => array() ) ),
			array( 'g-other01' )
		);

		// create → added:[newid], modified:[], deleted:[]
		$res = $this->ability->execute_create( array( 'label' => 'new', 'styles' => array( 'color' => '#111111' ) ) );
		$this->assertNotWPError( $res );
		$new_id = $res['id'];
		$this->assertSame( array( $new_id ), Global_Classes_Repository::$last_changes['added'] );
		$this->assertSame( array(), Global_Classes_Repository::$last_changes['modified'] );
		$this->assertSame( array( $new_id ), array_keys( Global_Classes_Repository::$last_touched ), 'only the new class is touched' );
		$this->assertArrayHasKey( 'g-other01', Global_Classes_Repository::$store_items, 'unrelated class preserved' );

		// update → modified:[newid]
		$this->ability->execute_update( array( 'class_id' => $new_id, 'styles' => array( 'color' => '#222222' ) ) );
		$this->assertSame( array( $new_id ), Global_Classes_Repository::$last_changes['modified'] );
		$this->assertSame( array( $new_id ), array_keys( Global_Classes_Repository::$last_touched ) );

		// delete → deleted:[newid], nothing touched
		$this->ability->execute_delete( array( 'class_id' => $new_id ) );
		$this->assertSame( array( $new_id ), Global_Classes_Repository::$last_changes['deleted'] );
		$this->assertSame( array(), Global_Classes_Repository::$last_touched, 'delete touches no item data' );
		$this->assertArrayHasKey( 'g-other01', Global_Classes_Repository::$store_items, 'unrelated class still preserved' );
	}

	public function test_create_requires_label(): void {
		$res = $this->ability->execute_create( array( 'styles' => array( 'color' => '#111' ) ) );
		$this->assertWPError( $res, 'missing_label' );
	}

	// =========================================================================
	// update
	// =========================================================================

	private function seed_class( string $id ): void {
		Global_Classes_Repository::__reset(
			array(
				$id => array(
					'id'       => $id,
					'type'     => 'class',
					'label'    => 'original',
					'variants' => array(
						array(
							'meta'       => array( 'breakpoint' => null, 'state' => null ),
							'props'      => array( 'color' => array( '$$type' => 'color', 'value' => '#aaaaaa' ) ),
							'custom_css' => null,
						),
						array(
							'meta'       => array( 'breakpoint' => 'tablet', 'state' => null ),
							'props'      => array( 'color' => array( '$$type' => 'color', 'value' => '#bbbbbb' ) ),
							'custom_css' => null,
						),
					),
				),
			),
			array( $id )
		);
	}

	public function test_update_replaces_base_variant_preserving_others(): void {
		$id = 'g-abc1234';
		$this->seed_class( $id );

		$res = $this->ability->execute_update( array(
			'class_id' => $id,
			'styles'   => array( 'color' => '#ff0000' ),
		) );

		$this->assertNotWPError( $res );
		$this->assertTrue( $res['updated'] );
		$this->assertSame( $id, $res['id'], 'id must be preserved' );

		$variants = Global_Classes_Repository::$store_items[ $id ]['variants'];
		$this->assertCount( 2, $variants, 'tablet variant must be preserved' );

		// Base (breakpoint null|desktop) replaced — rebuilt variants store the
		// base as the string 'desktop'.
		$base = null;
		$tablet = null;
		foreach ( $variants as $v ) {
			if ( null === $v['meta']['breakpoint'] || 'desktop' === $v['meta']['breakpoint'] ) {
				$base = $v;
			} elseif ( 'tablet' === $v['meta']['breakpoint'] ) {
				$tablet = $v;
			}
		}
		$this->assertSame( '#ff0000', $base['props']['color']['value'], 'base variant replaced' );
		$this->assertSame( '#bbbbbb', $tablet['props']['color']['value'], 'tablet variant untouched' );
	}

	public function test_update_patches_label(): void {
		$id = 'g-abc1234';
		$this->seed_class( $id );
		$res = $this->ability->execute_update( array( 'class_id' => $id, 'label' => 'renamed' ) );
		$this->assertNotWPError( $res );
		$this->assertSame( 'renamed', Global_Classes_Repository::$store_items[ $id ]['label'] );
	}

	public function test_update_unknown_class_errors(): void {
		Global_Classes_Repository::__reset( array(), array() );
		$res = $this->ability->execute_update( array( 'class_id' => 'g-missing', 'label' => 'x' ) );
		$this->assertWPError( $res, 'class_not_found' );
	}

	// =========================================================================
	// delete
	// =========================================================================

	public function test_delete_removes_class(): void {
		$id = 'g-del1234';
		$this->seed_class( $id );
		$res = $this->ability->execute_delete( array( 'class_id' => $id ) );
		$this->assertNotWPError( $res );
		$this->assertTrue( $res['deleted'] );
		$this->assertArrayNotHasKey( $id, Global_Classes_Repository::$store_items );
		$this->assertNotContains( $id, Global_Classes_Repository::$store_order );
	}

	public function test_delete_unknown_class_errors(): void {
		Global_Classes_Repository::__reset( array(), array() );
		$res = $this->ability->execute_delete( array( 'class_id' => 'g-nope' ) );
		$this->assertWPError( $res, 'class_not_found' );
	}

	// =========================================================================
	// apply
	// =========================================================================

	public function test_apply_rejects_non_atomic_element_with_schema_in_error(): void {
		$id = 'g-app1234';
		$this->seed_class( $id );

		$page = array(
			array(
				'id'         => 'legacy1',
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'Hello', 'header_size' => 'h2' ),
			),
		);
		$ability = $this->ability_with_page( $page );

		$res = $ability->execute_apply( array(
			'class_id'   => $id,
			'post_id'    => 7,
			'element_id' => 'legacy1',
		) );

		$this->assertWPError( $res, 'not_atomic' );
		// Schema-in-error: the widget type and its setting keys are embedded.
		$msg = $res->get_error_message();
		$this->assertStringContainsString( 'heading', $msg );
		$data = $res->get_error_data();
		$this->assertIsArray( $data );
		$this->assertContains( 'title', $data['setting_keys'] );
		$this->assertFalse( $data['has_classes'] );
	}

	public function test_apply_appends_class_to_atomic_element(): void {
		$id = 'g-app5678';
		$this->seed_class( $id );

		$page = array(
			array(
				'id'         => 'atom1',
				'elType'     => 'widget',
				'widgetType' => 'e-heading',
				'settings'   => array( 'classes' => array( '$$type' => 'classes', 'value' => array() ) ),
				'styles'     => array(),
			),
		);
		$ability = $this->ability_with_page( $page );
		$GLOBALS['_saved_page'] = null;

		$res = $ability->execute_apply( array(
			'class_id'   => $id,
			'post_id'    => 7,
			'element_id' => 'atom1',
		) );

		$this->assertNotWPError( $res );
		$this->assertTrue( $res['applied'] );
		$this->assertFalse( $res['already_present'] );
		$saved = $GLOBALS['_saved_page'][0]['settings']['classes']['value'];
		$this->assertContains( $id, $saved );
	}

	public function test_apply_is_idempotent_noop_when_already_present(): void {
		$id = 'g-app9999';
		$this->seed_class( $id );

		$page = array(
			array(
				'id'         => 'atom2',
				'elType'     => 'widget',
				'widgetType' => 'e-heading',
				'settings'   => array( 'classes' => array( '$$type' => 'classes', 'value' => array( $id ) ) ),
				'styles'     => array(),
			),
		);
		$ability = $this->ability_with_page( $page );

		$res = $ability->execute_apply( array(
			'class_id'   => $id,
			'post_id'    => 7,
			'element_id' => 'atom2',
		) );

		$this->assertNotWPError( $res );
		$this->assertTrue( $res['already_present'] );
	}

	public function test_apply_unknown_class_errors(): void {
		Global_Classes_Repository::__reset( array(), array() );
		$page    = array( array( 'id' => 'x', 'elType' => 'widget', 'widgetType' => 'e-heading', 'settings' => array() ) );
		$ability = $this->ability_with_page( $page );
		$res     = $ability->execute_apply( array( 'class_id' => 'g-missing', 'post_id' => 7, 'element_id' => 'x' ) );
		$this->assertWPError( $res, 'class_not_found' );
	}
}

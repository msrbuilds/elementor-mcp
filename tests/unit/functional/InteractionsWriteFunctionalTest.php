<?php
/**
 * Functional — Interactions write tools (list/add/edit/delete) drive an in-memory
 * Elementor_MCP_Data stub backed by a page whose atomic element carries a
 * top-level `interactions` field, end to end.
 *
 * @group functional
 * @group interactions
 * @package Elementor_MCP\Tests\Functional
 */

namespace Elementor_MCP\Tests\Functional;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class InteractionsWriteFunctionalTest extends Ability_Test_Case {

	protected function setUp(): void {
		parent::setUp();
		// is_available() requires the e_interactions experiment + atomic support.
		$GLOBALS['_active_experiments'] = array( 'e_interactions', 'e_atomic_elements' );
		unset( $GLOBALS['_saved_page'], $GLOBALS['_has_pro'] );
	}

	protected function tearDown(): void {
		$GLOBALS['_active_experiments'] = array();
		unset( $GLOBALS['_saved_page'], $GLOBALS['_has_pro'] );
		parent::tearDown();
	}

	// -------------------------------------------------------------------------
	// A data stub backed by an in-memory page. When $canonicalize is true it
	// emulates Elementor's Parser::assign_interaction_ids() on save (temp → canonical).
	// -------------------------------------------------------------------------
	private function ability_with_page( array $page, bool $canonicalize = false ): \Elementor_MCP_Interactions_Write_Abilities {
		$data = new class( $page, $canonicalize ) extends \Elementor_MCP_Data {
			public array $page;
			private bool $canon;
			private int $seq = 0;
			public function __construct( array $page, bool $canon ) {
				$this->page  = $page;
				$this->canon = $canon;
			}
			public function get_page_data( int $post_id ) {
				return $this->page;
			}
			public function save_page_data( int $post_id, array $data ) {
				if ( $this->canon ) {
					$data = $this->assign_ids( $data, $post_id );
				}
				$this->page             = $data;
				$GLOBALS['_saved_page'] = $data;
				return true;
			}
			private function assign_ids( array $data, int $post_id ): array {
				foreach ( $data as &$el ) {
					if ( ! empty( $el['interactions'] ) && is_string( $el['interactions'] ) ) {
						$decoded = json_decode( $el['interactions'], true );
						if ( is_array( $decoded ) && isset( $decoded['items'] ) && is_array( $decoded['items'] ) ) {
							foreach ( $decoded['items'] as &$item ) {
								$iid = $item['value']['interaction_id']['value'] ?? '';
								if ( is_string( $iid ) && 0 === strpos( $iid, 'temp-' ) ) {
									$item['value']['interaction_id']['value'] = $post_id . '-' . ( $el['id'] ?? 'x' ) . '-' . dechex( ++$this->seq ) . 'aa';
								}
							}
							unset( $item );
							$el['interactions'] = json_encode( $decoded );
						}
					}
					if ( ! empty( $el['elements'] ) && is_array( $el['elements'] ) ) {
						$el['elements'] = $this->assign_ids( $el['elements'], $post_id );
					}
				}
				unset( $el );
				return $data;
			}
		};
		return new \Elementor_MCP_Interactions_Write_Abilities( $data );
	}

	public function test_add_returns_new_canonical_id_when_element_has_prior_temp_ids(): void {
		// Element already carries a temp-id interaction (from an earlier raw-meta
		// fallback). A canonicalizing save would rewrite BOTH the old and new temp
		// ids — the returned id must be the NEW interaction's canonical id, usable
		// for a follow-up edit/delete (not a stale/duplicate id).
		$prior = wp_json_encode( array(
			'version' => 1,
			'items'   => array( array(
				'$$type' => 'interaction-item',
				'value'  => array(
					'interaction_id' => array( '$$type' => 'string', 'value' => 'temp-oldone' ),
					'trigger'        => array( '$$type' => 'string', 'value' => 'load' ),
				),
			) ),
		) );
		$ability = $this->ability_with_page( $this->atomic_page( $prior ), true );

		$res = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'fade' ) );
		$this->assertNotWPError( $res );
		$new_id = $res['interaction_id'];
		$this->assertStringStartsWith( '7-atom1-', $new_id, 'a canonical id, not a temp id' );

		// The returned id must actually exist on the element (usable for edit/delete).
		$saved   = $GLOBALS['_saved_page'][0]['interactions'];
		$decoded = json_decode( $saved, true );
		$ids     = array_map( static fn( $it ) => $it['value']['interaction_id']['value'], $decoded['items'] );
		$this->assertContains( $new_id, $ids );
		$this->assertCount( 2, $decoded['items'] );
		// And a follow-up edit by that id succeeds.
		$edit = $ability->execute_edit( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => $new_id, 'effect' => 'scale' ) );
		$this->assertNotWPError( $edit );
	}

	public function test_add_rejects_custom_effect(): void {
		$GLOBALS['_has_pro'] = true;
		$ability = $this->ability_with_page( $this->atomic_page() );
		$res     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'custom' ) );
		$this->assertWPError( $res );
		unset( $GLOBALS['_has_pro'] );
	}

	public function test_edit_preserves_id_on_bare_item(): void {
		// A tolerated "bare" item: interaction_id at the top level, no `value` wrapper.
		$bare = wp_json_encode( array(
			'version' => 1,
			'items'   => array( array( 'interaction_id' => array( '$$type' => 'string', 'value' => '7-atom1-bare01' ) ) ),
		) );
		$ability = $this->ability_with_page( $this->atomic_page( $bare ) );
		$res     = $ability->execute_edit( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => '7-atom1-bare01', 'effect' => 'slide' ) );
		$this->assertNotWPError( $res );

		$decoded = json_decode( $GLOBALS['_saved_page'][0]['interactions'], true );
		$this->assertSame( '7-atom1-bare01', $decoded['items'][0]['value']['interaction_id']['value'], 'id preserved through patch' );
	}

	public function test_write_refreshes_the_interactions_postmeta_cache(): void {
		// After a save (incl. the raw-meta fallback that skips document/after_save),
		// the interactions cache must be rebuilt from the just-written page.
		unset( $GLOBALS['_ix_cache'] );
		$ability = $this->ability_with_page( $this->atomic_page() );
		$res     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'fade' ) );
		$this->assertNotWPError( $res );
		$this->assertArrayHasKey( '_ix_cache', $GLOBALS, 'interactions cache refreshed' );
		$this->assertSame( 7, $GLOBALS['_ix_cache']['post_id'] );
		// Rebuilt from the document-shaped payload keyed by `elements`.
		$this->assertArrayHasKey( 'elements', $GLOBALS['_ix_cache']['data'] );
		unset( $GLOBALS['_ix_cache'] );
	}

	public function test_edit_temp_id_returns_canonical_id_on_native_save(): void {
		$temp = wp_json_encode( array(
			'version' => 1,
			'items'   => array( array(
				'$$type' => 'interaction-item',
				'value'  => array(
					'interaction_id' => array( '$$type' => 'string', 'value' => 'temp-abc' ),
					'trigger'        => array( '$$type' => 'string', 'value' => 'load' ),
				),
			) ),
		) );
		$ability = $this->ability_with_page( $this->atomic_page( $temp ), true ); // canonicalizing save
		$res     = $ability->execute_edit( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => 'temp-abc', 'effect' => 'slide' ) );
		$this->assertNotWPError( $res );
		$this->assertStringStartsWith( '7-atom1-', $res['interaction_id'], 'canonical id surfaced, not the temp id' );
		$this->assertStringNotContainsString( 'temp-', $res['interaction_id'] );
	}

	public function test_cache_refresh_uses_post_save_canonical_data(): void {
		// With a canonicalizing save, the cache must be rebuilt from the SAVED
		// (canonical) page, not the pre-save temp-id data.
		unset( $GLOBALS['_ix_cache'] );
		$ability = $this->ability_with_page( $this->atomic_page(), true );
		$ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'fade' ) );
		$json = $GLOBALS['_ix_cache']['data']['elements'][0]['interactions'];
		$this->assertStringNotContainsString( 'temp-', $json, 'cache holds the canonical, not the temp, id' );
		unset( $GLOBALS['_ix_cache'] );
	}

	public function test_add_rejects_beyond_the_five_interaction_cap(): void {
		$items = array();
		for ( $i = 0; $i < 5; $i++ ) {
			$items[] = array(
				'$$type' => 'interaction-item',
				'value'  => array( 'interaction_id' => array( '$$type' => 'string', 'value' => "7-atom1-$i" ) ),
			);
		}
		$full    = wp_json_encode( array( 'version' => 1, 'items' => $items ) );
		$ability = $this->ability_with_page( $this->atomic_page( $full ) );
		$res     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'fade' ) );
		$this->assertWPError( $res, 'interaction_limit_reached' );
	}

	private function atomic_page( $interactions = null ): array {
		$el = array(
			'id'         => 'atom1',
			'elType'     => 'widget',
			'widgetType' => 'e-heading',
			'settings'   => array( 'classes' => array( '$$type' => 'classes', 'value' => array() ) ),
			'styles'     => array(),
		);
		if ( null !== $interactions ) {
			$el['interactions'] = $interactions;
		}
		return array( $el );
	}

	// =========================================================================
	// add
	// =========================================================================

	public function test_add_appends_well_formed_interaction_item_tree_with_temp_id(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );

		$res = $ability->execute_add( array(
			'post_id'    => 7,
			'element_id' => 'atom1',
			'trigger'    => 'scrollIn',
			'effect'     => 'slide',
			'type'       => 'out',
			'direction'  => 'left',
			'duration_ms' => 400,
			'delay_ms'   => 100,
			'easing'     => 'easeIn',
		) );

		$this->assertNotWPError( $res );
		$this->assertTrue( $res['added'] );
		// Non-canonicalizing stub → the temp id persists and is returned.
		$this->assertStringStartsWith( 'temp-', $res['interaction_id'] );

		// Inspect the saved tree: top-level `interactions` JSON string with the
		// { version:1, items:[…] } wrapper and the atomic $$type keys.
		$raw = $GLOBALS['_saved_page'][0]['interactions'];
		$this->assertIsString( $raw, 'interactions is stored as a JSON string' );
		$decoded = json_decode( $raw, true );
		$this->assertSame( 1, $decoded['version'] );
		$this->assertCount( 1, $decoded['items'] );

		$item = $decoded['items'][0];
		$this->assertSame( 'interaction-item', $item['$$type'] );
		$v = $item['value'];
		$this->assertStringStartsWith( 'temp-', $v['interaction_id']['value'] );
		$this->assertSame( 'string', $v['interaction_id']['$$type'] );
		$this->assertSame( 'scrollIn', $v['trigger']['value'] );

		$anim = $v['animation'];
		$this->assertSame( 'animation-preset-props', $anim['$$type'] );
		$this->assertSame( 'slide', $anim['value']['effect']['value'] );
		$this->assertSame( 'out', $anim['value']['type']['value'] );
		$this->assertSame( 'left', $anim['value']['direction']['value'] );

		$timing = $anim['value']['timing_config'];
		$this->assertSame( 'timing-config', $timing['$$type'] );
		$this->assertSame( 'size', $timing['value']['duration']['$$type'] );
		$this->assertSame( 400, $timing['value']['duration']['value']['size'] );
		$this->assertSame( 'ms', $timing['value']['duration']['value']['unit'] );
		$this->assertSame( 100, $timing['value']['delay']['value']['size'] );

		$config = $anim['value']['config'];
		$this->assertSame( 'config-v2', $config['$$type'] );
		$this->assertSame( 'easeIn', $config['value']['easing']['value'] );
	}

	public function test_add_defaults_are_applied(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$res     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$this->assertNotWPError( $res );

		$list = $ability->execute_list( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$row  = $list['interactions'][0];
		$this->assertSame( 'load', $row['trigger'] );
		$this->assertSame( 'fade', $row['effect'] );
		$this->assertSame( 'in', $row['type'] );
		$this->assertSame( '', $row['direction'] );
		$this->assertSame( 600, $row['duration_ms'] );
		$this->assertSame( 0, $row['delay_ms'] );
		$this->assertSame( 'easeIn', $row['easing'] );
	}

	public function test_add_surfaces_canonical_id_after_save(): void {
		// Canonicalizing stub emulates Parser::assign_interaction_ids() on save.
		$ability = $this->ability_with_page( $this->atomic_page(), true );
		$res     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$this->assertNotWPError( $res );
		$this->assertStringStartsWith( '7-atom1-', $res['interaction_id'], 'temp id resolved to canonical on re-read' );
		$this->assertStringNotContainsString( 'temp-', $res['interaction_id'] );
	}

	public function test_add_rejects_non_atomic_element_with_schema_in_error(): void {
		$page = array(
			array(
				'id'         => 'legacy1',
				'elType'     => 'widget',
				'widgetType' => 'heading',
				'settings'   => array( 'title' => 'Hello', 'header_size' => 'h2' ),
			),
		);
		$ability = $this->ability_with_page( $page );
		$res     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'legacy1' ) );

		$this->assertWPError( $res, 'not_atomic' );
		$this->assertStringContainsString( 'heading', $res->get_error_message() );
		$data = $res->get_error_data();
		$this->assertIsArray( $data );
		$this->assertContains( 'title', $data['setting_keys'] );
	}

	public function test_add_rejects_missing_element(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$res     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'nope' ) );
		$this->assertWPError( $res, 'element_not_found' );
	}

	// =========================================================================
	// Pro gating
	// =========================================================================

	public function test_add_rejects_pro_trigger_on_non_pro(): void {
		$GLOBALS['_has_pro'] = false;
		$ability             = $this->ability_with_page( $this->atomic_page() );
		// scrollOut/hover are supported Pro triggers — rejected on Free.
		$this->assertWPError( $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'trigger' => 'hover' ) ), 'requires_pro' );
		$this->assertWPError( $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'trigger' => 'scrollOut' ) ), 'requires_pro' );
	}

	public function test_add_rejects_pro_easing_on_non_pro(): void {
		$GLOBALS['_has_pro'] = false;
		$ability             = $this->ability_with_page( $this->atomic_page() );
		$res                 = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'easing' => 'backInOut' ) );
		$this->assertWPError( $res, 'requires_pro' );
	}

	public function test_add_allows_pro_trigger_with_pro(): void {
		$GLOBALS['_has_pro'] = true;
		$ability             = $this->ability_with_page( $this->atomic_page() );
		$res                 = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'trigger' => 'hover', 'easing' => 'linear' ) );
		$this->assertNotWPError( $res );
	}

	public function test_add_rejects_unknown_effect(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$res     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'explode' ) );
		$this->assertWPError( $res, 'invalid_effect' );
	}

	public function test_add_rejects_unknown_direction(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$res     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'direction' => 'sideways' ) );
		$this->assertWPError( $res, 'invalid_direction' );
	}

	// =========================================================================
	// list
	// =========================================================================

	public function test_list_unwraps_items_to_ergonomic_fields(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'trigger' => 'scrollIn', 'effect' => 'scale', 'duration_ms' => 250 ) );
		$ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'fade' ) );

		$res = $ability->execute_list( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$this->assertNotWPError( $res );
		$this->assertSame( 2, $res['count'] );
		$effects = array_column( $res['interactions'], 'effect' );
		sort( $effects );
		$this->assertSame( array( 'fade', 'scale' ), $effects );

		$scale = null;
		foreach ( $res['interactions'] as $row ) {
			if ( 'scale' === $row['effect'] ) {
				$scale = $row;
			}
		}
		$this->assertSame( 'scrollIn', $scale['trigger'] );
		$this->assertSame( 250, $scale['duration_ms'] );
		$this->assertNotEmpty( $scale['interaction_id'] );
	}

	public function test_list_empty_when_no_interactions(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$res     = $ability->execute_list( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$this->assertNotWPError( $res );
		$this->assertSame( 0, $res['count'] );
	}

	public function test_list_tolerates_wrapped_items_array_shape(): void {
		// Elementor's decode also tolerates { items: { $$type:'array', value:[…] } }.
		$item        = array(
			'$$type' => 'interaction-item',
			'value'  => array(
				'interaction_id' => array( '$$type' => 'string', 'value' => 'canon-1' ),
				'trigger'        => array( '$$type' => 'string', 'value' => 'load' ),
				'animation'      => array(
					'$$type' => 'animation-preset-props',
					'value'  => array(
						'effect'        => array( '$$type' => 'string', 'value' => 'fade' ),
						'type'          => array( '$$type' => 'string', 'value' => 'in' ),
						'direction'     => array( '$$type' => 'string', 'value' => '' ),
						'timing_config' => array( '$$type' => 'timing-config', 'value' => array(
							'duration' => array( '$$type' => 'size', 'value' => array( 'size' => 600, 'unit' => 'ms' ) ),
							'delay'    => array( '$$type' => 'size', 'value' => array( 'size' => 0, 'unit' => 'ms' ) ),
						) ),
						'config'        => array( '$$type' => 'config-v2', 'value' => array(
							'easing' => array( '$$type' => 'string', 'value' => 'easeIn' ),
						) ),
					),
				),
			),
		);
		$raw     = json_encode( array( 'version' => 1, 'items' => array( '$$type' => 'array', 'value' => array( $item ) ) ) );
		$ability = $this->ability_with_page( $this->atomic_page( $raw ) );

		$res = $ability->execute_list( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$this->assertNotWPError( $res );
		$this->assertSame( 1, $res['count'] );
		$this->assertSame( 'canon-1', $res['interactions'][0]['interaction_id'] );
		$this->assertSame( 'fade', $res['interactions'][0]['effect'] );
	}

	// =========================================================================
	// edit
	// =========================================================================

	public function test_edit_patches_by_id_and_preserves_others_and_id(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$add     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'trigger' => 'load', 'effect' => 'fade', 'duration_ms' => 600, 'delay_ms' => 50 ) );
		$id      = $add['interaction_id'];

		$res = $ability->execute_edit( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => $id, 'effect' => 'slide', 'direction' => 'top' ) );
		$this->assertNotWPError( $res );
		$this->assertTrue( $res['updated'] );
		$this->assertSame( $id, $res['interaction_id'], 'id preserved through edit' );

		$row = $ability->execute_list( array( 'post_id' => 7, 'element_id' => 'atom1' ) )['interactions'][0];
		$this->assertSame( $id, $row['interaction_id'] );
		$this->assertSame( 'slide', $row['effect'], 'changed field patched' );
		$this->assertSame( 'top', $row['direction'], 'changed field patched' );
		// Untouched fields preserved.
		$this->assertSame( 'load', $row['trigger'] );
		$this->assertSame( 600, $row['duration_ms'] );
		$this->assertSame( 50, $row['delay_ms'] );
	}

	public function test_edit_only_touches_the_addressed_item(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$a       = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'fade' ) );
		$b       = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'scale' ) );

		$ability->execute_edit( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => $b['interaction_id'], 'effect' => 'slide' ) );

		$list = $ability->execute_list( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$by   = array();
		foreach ( $list['interactions'] as $row ) {
			$by[ $row['interaction_id'] ] = $row['effect'];
		}
		$this->assertSame( 'fade', $by[ $a['interaction_id'] ], 'sibling untouched' );
		$this->assertSame( 'slide', $by[ $b['interaction_id'] ] );
	}

	public function test_edit_requires_a_field(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$add     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$res     = $ability->execute_edit( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => $add['interaction_id'] ) );
		$this->assertWPError( $res, 'nothing_to_update' );
	}

	public function test_edit_missing_id_is_not_found(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$res = $ability->execute_edit( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => 'does-not-exist', 'effect' => 'slide' ) );
		$this->assertWPError( $res, 'not_found' );
	}

	public function test_edit_rejects_pro_value_on_non_pro(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$add     = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$GLOBALS['_has_pro'] = false;
		$res = $ability->execute_edit( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => $add['interaction_id'], 'trigger' => 'click' ) );
		$this->assertWPError( $res, 'requires_pro' );
	}

	// =========================================================================
	// delete
	// =========================================================================

	public function test_delete_removes_by_id(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$a       = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'fade' ) );
		$b       = $ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1', 'effect' => 'scale' ) );

		$res = $ability->execute_delete( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => $a['interaction_id'] ) );
		$this->assertNotWPError( $res );
		$this->assertTrue( $res['deleted'] );
		$this->assertSame( $a['interaction_id'], $res['interaction_id'] );

		$list = $ability->execute_list( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$this->assertSame( 1, $list['count'] );
		$this->assertSame( $b['interaction_id'], $list['interactions'][0]['interaction_id'] );
	}

	public function test_delete_missing_id_is_not_found(): void {
		$ability = $this->ability_with_page( $this->atomic_page() );
		$ability->execute_add( array( 'post_id' => 7, 'element_id' => 'atom1' ) );
		$res = $ability->execute_delete( array( 'post_id' => 7, 'element_id' => 'atom1', 'interaction_id' => 'ghost' ) );
		$this->assertWPError( $res, 'not_found' );
	}
}

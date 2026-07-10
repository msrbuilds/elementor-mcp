<?php
/**
 * Unit tests for EMCP_Tools_ACF_Abilities.
 *
 * Runs against the standalone stub harness in tests/bootstrap.php:
 *
 *     vendor/bin/phpunit -c tests/phpunit.xml
 *
 * @package EMCP_Tools
 */

use PHPUnit\Framework\TestCase;

class AcfAbilitiesTest extends TestCase {

	/** @var EMCP_Tools_ACF_Abilities */
	private $abilities;

	protected function setUp(): void {
		emcp_test_reset();
		$this->abilities = new EMCP_Tools_ACF_Abilities();
	}

	/**
	 * Fixture: one post (ID 10) with a field group containing a text field, a
	 * repeater (text + image sub-fields), and a flexible content field with a
	 * "hero" layout.
	 */
	private function seed_post_with_group(): void {
		$GLOBALS['emcp_test']['posts'][10] = new WP_Post( array( 'ID' => 10, 'post_title' => 'Sample' ) );

		$group = array( 'key' => 'group_demo', 'ID' => 55, 'title' => 'Demo Group', 'active' => true );
		$GLOBALS['emcp_test']['field_groups'][]        = $group;
		$GLOBALS['emcp_test']['groups_for_post'][10]   = array( $group );
		$GLOBALS['emcp_test']['group_fields']['group_demo'] = array(
			array( 'key' => 'field_headline', 'name' => 'headline', 'label' => 'Headline', 'type' => 'text' ),
			array(
				'key'        => 'field_items',
				'name'       => 'items',
				'label'      => 'Items',
				'type'       => 'repeater',
				'sub_fields' => array(
					array( 'key' => 'field_item_text', 'name' => 'item_text', 'label' => 'Item Text', 'type' => 'text' ),
					array( 'key' => 'field_item_img', 'name' => 'item_img', 'label' => 'Item Image', 'type' => 'image' ),
				),
			),
			array(
				'key'     => 'field_sections',
				'name'    => 'sections',
				'label'   => 'Sections',
				'type'    => 'flexible_content',
				'layouts' => array(
					array( 'key' => 'layout_hero', 'name' => 'hero', 'label' => 'Hero', 'sub_fields' => array(
						array( 'key' => 'field_hero_title', 'name' => 'hero_title', 'label' => 'Hero Title', 'type' => 'text' ),
					) ),
				),
			),
		);
	}

	// -------------------------------------------------------------------
	// Registration
	// -------------------------------------------------------------------

	public function test_registers_the_seven_acf_tools(): void {
		$this->abilities->register();

		$expected = array(
			'emcp-tools/list-acf-field-groups',
			'emcp-tools/get-acf-field-group',
			'emcp-tools/list-acf-options-pages',
			'emcp-tools/get-acf-fields',
			'emcp-tools/update-acf-fields',
			'emcp-tools/create-acf-field-group',
			'emcp-tools/update-acf-field-group',
		);
		$this->assertSame( $expected, $this->abilities->get_ability_names() );
		foreach ( $expected as $name ) {
			$this->assertArrayHasKey( $name, $GLOBALS['emcp_test']['abilities'] );
		}
	}

	public function test_read_tools_are_annotated_readonly_and_writes_are_not(): void {
		$this->abilities->register();
		$abilities = $GLOBALS['emcp_test']['abilities'];

		foreach ( array( 'list-acf-field-groups', 'get-acf-field-group', 'list-acf-options-pages', 'get-acf-fields' ) as $slug ) {
			$this->assertTrue( $abilities[ 'emcp-tools/' . $slug ]['meta']['annotations']['readonly'], $slug );
		}
		foreach ( array( 'update-acf-fields', 'create-acf-field-group', 'update-acf-field-group' ) as $slug ) {
			$this->assertFalse( $abilities[ 'emcp-tools/' . $slug ]['meta']['annotations']['readonly'], $slug );
		}
	}

	// -------------------------------------------------------------------
	// Permissions
	// -------------------------------------------------------------------

	public function test_options_target_requires_manage_options(): void {
		$GLOBALS['emcp_test']['caps'] = array( 'edit_posts' ); // no manage_options
		$this->assertFalse( $this->abilities->check_fields_permission( array( 'options_page' => 'options' ) ) );

		$GLOBALS['emcp_test']['caps'] = array( 'manage_options' );
		$this->assertTrue( $this->abilities->check_fields_permission( array( 'options_page' => 'options' ) ) );
	}

	public function test_post_target_requires_edit_post_on_that_post(): void {
		$GLOBALS['emcp_test']['caps']          = array( 'edit_posts' );
		$GLOBALS['emcp_test']['post_caps'][10] = false;
		$this->assertFalse( $this->abilities->check_fields_permission( array( 'post_id' => 10 ) ) );

		$GLOBALS['emcp_test']['post_caps'][10] = true;
		$this->assertTrue( $this->abilities->check_fields_permission( array( 'post_id' => 10 ) ) );
	}

	public function test_field_group_authoring_requires_manage_options(): void {
		$GLOBALS['emcp_test']['caps'] = array( 'edit_posts' );
		$this->assertFalse( $this->abilities->check_manage_permission() );

		$GLOBALS['emcp_test']['caps'] = array( 'manage_options' );
		$this->assertTrue( $this->abilities->check_manage_permission() );
	}

	// -------------------------------------------------------------------
	// Target validation
	// -------------------------------------------------------------------

	public function test_update_fields_rejects_both_targets(): void {
		$this->seed_post_with_group();
		$result = $this->abilities->execute_update_fields( array( 'post_id' => 10, 'options_page' => 'options', 'fields' => array( 'headline' => 'x' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_target', $result->get_error_code() );
	}

	public function test_update_fields_rejects_missing_target(): void {
		$result = $this->abilities->execute_update_fields( array( 'fields' => array( 'headline' => 'x' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_target', $result->get_error_code() );
	}

	public function test_options_target_requires_pro(): void {
		$GLOBALS['emcp_test']['acf_pro'] = false;
		$result = $this->abilities->execute_update_fields( array( 'options_page' => 'options', 'fields' => array( 'x' => 'y' ) ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acf_pro_required', $result->get_error_code() );
	}

	// -------------------------------------------------------------------
	// update-acf-fields
	// -------------------------------------------------------------------

	public function test_update_writes_by_field_key_not_name(): void {
		$this->seed_post_with_group();
		$result = $this->abilities->execute_update_fields( array(
			'post_id' => 10,
			'fields'  => array( 'headline' => 'Hello World' ),
		) );

		$this->assertIsArray( $result );
		$this->assertSame( array( 'headline' ), $result['updated'] );
		$this->assertCount( 1, $GLOBALS['emcp_test']['update_field_calls'] );
		$this->assertSame( array( 'field_headline', 'Hello World', 10 ), $GLOBALS['emcp_test']['update_field_calls'][0] );
	}

	public function test_update_accepts_field_keys_directly(): void {
		$this->seed_post_with_group();
		$GLOBALS['emcp_test']['fields_by_key']['field_headline'] = $GLOBALS['emcp_test']['group_fields']['group_demo'][0];

		$result = $this->abilities->execute_update_fields( array(
			'post_id' => 10,
			'fields'  => array( 'field_headline' => 'Via key' ),
		) );

		$this->assertSame( array( 'headline' ), $result['updated'] );
		$this->assertSame( 'field_headline', $GLOBALS['emcp_test']['update_field_calls'][0][0] );
	}

	public function test_update_skips_unknown_fields_with_reason(): void {
		$this->seed_post_with_group();
		$result = $this->abilities->execute_update_fields( array(
			'post_id' => 10,
			'fields'  => array( 'nope' => 'x' ),
		) );

		$this->assertSame( array(), $result['updated'] );
		$this->assertSame( array( array( 'field' => 'nope', 'reason' => 'field_not_found' ) ), $result['skipped'] );
		$this->assertCount( 0, $GLOBALS['emcp_test']['update_field_calls'] );
	}

	public function test_update_repeater_rows_pass_through_to_acf(): void {
		$this->seed_post_with_group();
		$rows = array(
			array( 'item_text' => 'Row 1', 'item_img' => 42 ),
			array( 'item_text' => 'Row 2', 'item_img' => 43 ),
		);
		$result = $this->abilities->execute_update_fields( array(
			'post_id' => 10,
			'fields'  => array( 'items' => $rows ),
		) );

		$this->assertSame( array( 'items' ), $result['updated'] );
		$this->assertSame( array( 'field_items', $rows, 10 ), $GLOBALS['emcp_test']['update_field_calls'][0] );
	}

	public function test_update_pro_field_rejected_on_free_acf(): void {
		$this->seed_post_with_group();
		$GLOBALS['emcp_test']['acf_pro'] = false;

		$result = $this->abilities->execute_update_fields( array(
			'post_id' => 10,
			'fields'  => array( 'items' => array() ),
		) );

		$this->assertSame( array( array( 'field' => 'items', 'reason' => 'acf_pro_required' ) ), $result['skipped'] );
		$this->assertCount( 0, $GLOBALS['emcp_test']['update_field_calls'] );
	}

	public function test_flexible_rows_require_known_layout(): void {
		$this->seed_post_with_group();

		$missing = $this->abilities->execute_update_fields( array(
			'post_id' => 10,
			'fields'  => array( 'sections' => array( array( 'hero_title' => 'No layout key' ) ) ),
		) );
		$this->assertSame( 'missing_acf_fc_layout', $missing['skipped'][0]['reason'] );

		$unknown = $this->abilities->execute_update_fields( array(
			'post_id' => 10,
			'fields'  => array( 'sections' => array( array( 'acf_fc_layout' => 'bogus' ) ) ),
		) );
		$this->assertSame( 'unknown_layout', $unknown['skipped'][0]['reason'] );

		$valid = $this->abilities->execute_update_fields( array(
			'post_id' => 10,
			'fields'  => array( 'sections' => array( array( 'acf_fc_layout' => 'hero', 'hero_title' => 'Hi' ) ) ),
		) );
		$this->assertSame( array( 'sections' ), $valid['updated'] );
	}

	// -------------------------------------------------------------------
	// get-acf-fields
	// -------------------------------------------------------------------

	public function test_get_fields_returns_null_for_unsaved_fields(): void {
		$this->seed_post_with_group();
		$GLOBALS['emcp_test']['values']['10'] = array( 'field_headline' => 'Stored' );

		$result = $this->abilities->execute_get_fields( array( 'post_id' => 10 ) );

		$this->assertSame( 'Stored', $result['fields']['headline'] );
		$this->assertNull( $result['fields']['items'] );
		$this->assertNull( $result['fields']['sections'] );
	}

	public function test_get_fields_normalizes_wp_post_and_image_arrays(): void {
		$this->seed_post_with_group();
		$GLOBALS['emcp_test']['values']['10'] = array(
			'field_headline' => new WP_Post( array( 'ID' => 7, 'post_title' => 'Linked', 'post_type' => 'page' ) ),
			'field_items'    => array( array( 'item_img' => array( 'ID' => 42, 'url' => 'http://example.test/i.jpg', 'mime_type' => 'image/jpeg', 'alt' => 'Alt' ) ) ),
		);

		$result = $this->abilities->execute_get_fields( array( 'post_id' => 10 ) );

		$this->assertSame( array( 'id' => 7, 'title' => 'Linked', 'post_type' => 'page', 'url' => 'http://example.test/?p=7' ), $result['fields']['headline'] );
		$this->assertSame( array( 'id' => 42, 'url' => 'http://example.test/i.jpg', 'alt' => 'Alt', 'mime' => 'image/jpeg' ), $result['fields']['items'][0]['item_img'] );
	}

	public function test_get_fields_filter_limits_output(): void {
		$this->seed_post_with_group();
		$result = $this->abilities->execute_get_fields( array( 'post_id' => 10, 'fields' => array( 'headline' ) ) );
		$this->assertSame( array( 'headline' ), array_keys( $result['fields'] ) );
	}

	// -------------------------------------------------------------------
	// Field group discovery
	// -------------------------------------------------------------------

	public function test_get_field_group_formats_recursive_tree(): void {
		$this->seed_post_with_group();
		$result = $this->abilities->execute_get_field_group( array( 'key' => 'group_demo' ) );

		$this->assertSame( 'group_demo', $result['key'] );
		$this->assertCount( 3, $result['fields'] );
		$this->assertSame( 'item_text', $result['fields'][1]['sub_fields'][0]['name'] );
		$this->assertSame( 'hero', $result['fields'][2]['layouts'][0]['name'] );
		$this->assertSame( 'hero_title', $result['fields'][2]['layouts'][0]['sub_fields'][0]['name'] );
	}

	public function test_list_field_groups_filters_inactive_by_default(): void {
		$GLOBALS['emcp_test']['field_groups'] = array(
			array( 'key' => 'group_a', 'ID' => 1, 'title' => 'Active', 'active' => true ),
			array( 'key' => 'group_b', 'ID' => 2, 'title' => 'Inactive', 'active' => false ),
		);

		$result = $this->abilities->execute_list_field_groups( array() );
		$this->assertSame( 1, $result['total'] );
		$this->assertSame( 'group_a', $result['groups'][0]['key'] );

		$all = $this->abilities->execute_list_field_groups( array( 'active_only' => false ) );
		$this->assertSame( 2, $all['total'] );
	}

	// -------------------------------------------------------------------
	// create-acf-field-group
	// -------------------------------------------------------------------

	public function test_create_group_assigns_keys_and_imports(): void {
		$result = $this->abilities->execute_create_field_group( array(
			'title'  => 'New Group',
			'fields' => array(
				array( 'label' => 'Headline', 'name' => 'headline', 'type' => 'text' ),
				array(
					'label'      => 'Items',
					'name'       => 'items',
					'type'       => 'repeater',
					'sub_fields' => array(
						array( 'label' => 'Text', 'name' => 'item_text', 'type' => 'text' ),
					),
				),
			),
		) );

		$this->assertIsArray( $result );
		$this->assertCount( 1, $GLOBALS['emcp_test']['imported_groups'] );
		$imported = $GLOBALS['emcp_test']['imported_groups'][0];

		$this->assertStringStartsWith( 'group_', $imported['key'] );
		$this->assertStringStartsWith( 'field_', $imported['fields'][0]['key'] );
		$this->assertStringStartsWith( 'field_', $imported['fields'][1]['sub_fields'][0]['key'] );
		// Default location applied.
		$this->assertSame( 'post_type', $imported['location'][0][0]['param'] );
		$this->assertSame( (int) $imported['ID'], $result['id'] );
	}

	public function test_create_group_rejects_pro_types_on_free_acf(): void {
		$GLOBALS['emcp_test']['acf_pro'] = false;
		$result = $this->abilities->execute_create_field_group( array(
			'title'  => 'New Group',
			'fields' => array( array( 'label' => 'Items', 'name' => 'items', 'type' => 'repeater' ) ),
		) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acf_pro_required', $result->get_error_code() );
		$this->assertCount( 0, $GLOBALS['emcp_test']['imported_groups'] );
	}

	public function test_create_group_rejects_unknown_field_type(): void {
		$result = $this->abilities->execute_create_field_group( array(
			'title'  => 'New Group',
			'fields' => array( array( 'label' => 'X', 'name' => 'x', 'type' => 'unknown_type' ) ),
		) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_field', $result->get_error_code() );
	}

	public function test_create_group_validates_location_rules(): void {
		$result = $this->abilities->execute_create_field_group( array(
			'title'    => 'New Group',
			'fields'   => array( array( 'label' => 'X', 'name' => 'x', 'type' => 'text' ) ),
			'location' => array( array( array( 'param' => 'post_type', 'operator' => '>=', 'value' => 'post' ) ) ),
		) );

		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'invalid_location', $result->get_error_code() );
	}

	// -------------------------------------------------------------------
	// update-acf-field-group
	// -------------------------------------------------------------------

	public function test_update_group_refuses_local_groups(): void {
		$GLOBALS['emcp_test']['field_groups'][] = array( 'key' => 'group_json', 'ID' => 9, 'title' => 'From JSON', 'local' => 'json' );

		$result = $this->abilities->execute_update_field_group( array( 'key' => 'group_json', 'title' => 'Renamed' ) );
		$this->assertInstanceOf( WP_Error::class, $result );
		$this->assertSame( 'acf_local_group', $result->get_error_code() );
	}

	public function test_update_group_refuses_field_renames_and_type_changes(): void {
		$GLOBALS['emcp_test']['field_groups'][] = array( 'key' => 'group_db', 'ID' => 12, 'title' => 'DB Group' );

		foreach ( array( 'name', 'type' ) as $immutable ) {
			$result = $this->abilities->execute_update_field_group( array(
				'key'           => 'group_db',
				'update_fields' => array( array( 'key' => 'field_x', $immutable => 'changed' ) ),
			) );
			$this->assertInstanceOf( WP_Error::class, $result );
			$this->assertSame( 'immutable_field_setting', $result->get_error_code() );
		}
	}

	public function test_update_group_appends_fields_and_updates_settings(): void {
		$GLOBALS['emcp_test']['field_groups'][]                = array( 'key' => 'group_db', 'ID' => 12, 'title' => 'DB Group' );
		$GLOBALS['emcp_test']['fields_by_key']['field_headline'] = array( 'key' => 'field_headline', 'name' => 'headline', 'label' => 'Old', 'type' => 'text' );

		$result = $this->abilities->execute_update_field_group( array(
			'key'           => 'group_db',
			'title'         => 'Renamed Group',
			'add_fields'    => array( array( 'label' => 'Extra', 'name' => 'extra', 'type' => 'text' ) ),
			'update_fields' => array( array( 'key' => 'field_headline', 'label' => 'New Label', 'required' => true ) ),
		) );

		$this->assertSame( array( 'title' ), $result['updated'] );
		$this->assertSame( 'extra', $result['fields_added'][0]['name'] );
		$this->assertSame( array( 'field_headline' ), $result['fields_updated'] );

		// The appended field was parented to the group and the setting change kept name/type intact.
		$added = $GLOBALS['emcp_test']['updated_fields'][0];
		$this->assertSame( 12, $added['parent'] );
		$edited = $GLOBALS['emcp_test']['updated_fields'][1];
		$this->assertSame( 'New Label', $edited['label'] );
		$this->assertSame( 'headline', $edited['name'] );
		$this->assertSame( 'text', $edited['type'] );
	}

	// -------------------------------------------------------------------
	// Options pages
	// -------------------------------------------------------------------

	public function test_list_options_pages_returns_pages(): void {
		$GLOBALS['emcp_test']['options_pages'] = array(
			array( 'menu_slug' => 'site-settings', 'page_title' => 'Site Settings', 'post_id' => 'options', 'parent_slug' => '' ),
		);

		$result = $this->abilities->execute_list_options_pages( array() );
		$this->assertTrue( $result['pro'] );
		$this->assertSame( 'site-settings', $result['pages'][0]['menu_slug'] );
	}

	public function test_options_first_write_by_name_resolves_via_located_group(): void {
		// A group located on an options page whose field has NO stored value yet:
		// the name must still resolve to the key through the group index.
		$GLOBALS['emcp_test']['field_groups'][] = array(
			'key'      => 'group_opts',
			'ID'       => 30,
			'title'    => 'Options Group',
			'active'   => true,
			'location' => array( array( array( 'param' => 'options_page', 'operator' => '==', 'value' => 'site-settings' ) ) ),
		);
		$GLOBALS['emcp_test']['group_fields']['group_opts'] = array(
			array( 'key' => 'field_footer', 'name' => 'footer_text', 'label' => 'Footer', 'type' => 'text' ),
		);

		$result = $this->abilities->execute_update_fields( array(
			'options_page' => 'options',
			'fields'       => array( 'footer_text' => 'First write' ),
		) );

		$this->assertSame( array( 'footer_text' ), $result['updated'] );
		$this->assertSame( array( 'field_footer', 'First write', 'options' ), $GLOBALS['emcp_test']['update_field_calls'][0] );
	}

	public function test_options_target_reads_via_field_objects(): void {
		$GLOBALS['emcp_test']['field_objects']['options'] = array(
			'footer_text' => array( 'key' => 'field_footer', 'name' => 'footer_text', 'label' => 'Footer', 'type' => 'text' ),
		);
		$GLOBALS['emcp_test']['values']['options'] = array( 'field_footer' => '© 2026' );

		$result = $this->abilities->execute_get_fields( array( 'options_page' => 'options' ) );
		$this->assertSame( '© 2026', $result['fields']['footer_text'] );
	}
}

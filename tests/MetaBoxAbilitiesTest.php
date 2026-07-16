<?php
use PHPUnit\Framework\TestCase;

final class MetaBoxAbilitiesTest extends TestCase {
    private EMCP_Tools_Meta_Box_Abilities $mb;

    protected function setUp(): void {
        emcp_test_reset();
        $GLOBALS['emcp_test']['caps'] = array( 'edit_posts', 'manage_options' );
        $this->mb = new EMCP_Tools_Meta_Box_Abilities();
    }

    public function test_metabox_active_true_when_rwmb_ver_defined(): void {
        $this->assertTrue( EMCP_Tools_Meta_Box_Abilities::metabox_active() );
    }

    public function test_register_adds_two_dispatchers(): void {
        $this->mb->register();
        $names = $this->mb->get_ability_names();
        $this->assertContains( 'emcp-tools/metabox-read', $names );
        $this->assertContains( 'emcp-tools/metabox-write', $names );
    }

    public function test_read_dispatcher_no_operation_returns_catalog(): void {
        $out = $this->mb->run_metabox_read( array() );
        $this->assertSame( 'read', $out['mode'] );
        $ops = array_column( $out['operations'], 'operation' );
        $this->assertEqualsCanonicalizing(
            array( 'list-field-groups', 'get-field-group', 'get-fields' ), $ops
        );
    }

    public function test_write_dispatcher_no_operation_returns_catalog(): void {
        $out = $this->mb->run_metabox_write( array() );
        $this->assertSame( 'write', $out['mode'] );
        $this->assertSame( array( 'update-fields' ), array_column( $out['operations'], 'operation' ) );
    }

    public function test_unknown_operation_returns_wp_error(): void {
        $out = $this->mb->run_metabox_read( array( 'operation' => 'nope' ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'unknown_operation', $out->get_error_code() );
    }

    public function test_wrong_mode_operation_rejected(): void {
        // update-fields is a write op; calling it via the read dispatcher must fail.
        $out = $this->mb->run_metabox_read( array( 'operation' => 'update-fields' ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'unknown_operation', $out->get_error_code() );
    }

    private function seed_boxes(): void {
        $GLOBALS['emcp_test']['metabox']['boxes'] = array(
            new EMCP_Test_MB( array(
                'id'         => 'event_details',
                'title'      => 'Event Details',
                'post_types' => array( 'event' ),
                'fields'     => array(
                    array( 'id' => 'event_date', 'name' => 'Event Date', 'type' => 'date' ),
                    array( 'id' => 'venue', 'name' => 'Venue', 'type' => 'text', 'std' => 'TBD' ),
                    array( 'id' => 'lineup', 'name' => 'Lineup', 'type' => 'group', 'fields' => array(
                        array( 'id' => 'act', 'name' => 'Act', 'type' => 'text' ),
                    ) ),
                ),
            ), 'post' ),
            new EMCP_Test_MB( array(
                'id' => 'seo_meta', 'title' => 'SEO', 'post_types' => array( 'post', 'page' ),
                'fields' => array( array( 'id' => 'seo_title', 'name' => 'SEO Title', 'type' => 'text' ) ),
            ), 'post' ),
        );
    }

    public function test_list_field_groups_returns_rows(): void {
        $this->seed_boxes();
        $out = $this->mb->run_metabox_read( array( 'operation' => 'list-field-groups' ) );
        $this->assertSame( 2, $out['total'] );
        $ids = array_column( $out['field_groups'], 'id' );
        $this->assertEqualsCanonicalizing( array( 'event_details', 'seo_meta' ), $ids );
        $event = array_values( array_filter( $out['field_groups'], fn( $g ) => 'event_details' === $g['id'] ) )[0];
        $this->assertSame( 'Event Details', $event['title'] );
        $this->assertSame( 'post', $event['object_type'] );
        $this->assertSame( array( 'event' ), $event['post_types'] );
        $this->assertSame( 3, $event['field_count'] );
    }

    public function test_list_field_groups_search_filter(): void {
        $this->seed_boxes();
        $out = $this->mb->run_metabox_read( array( 'operation' => 'list-field-groups', 'arguments' => array( 'search' => 'seo' ) ) );
        $this->assertSame( 1, $out['total'] );
        $this->assertSame( 'seo_meta', $out['field_groups'][0]['id'] );
    }

    public function test_get_field_group_returns_fields_with_nesting(): void {
        $this->seed_boxes();
        $out = $this->mb->run_metabox_read( array( 'operation' => 'get-field-group', 'arguments' => array( 'id' => 'event_details' ) ) );
        $this->assertSame( 'event_details', $out['id'] );
        $this->assertCount( 3, $out['fields'] );
        $venue = $out['fields'][1];
        $this->assertSame( 'venue', $venue['id'] );
        $this->assertSame( 'Venue', $venue['label'] );   // MB `name` -> our `label`
        $this->assertSame( 'text', $venue['type'] );
        $this->assertSame( 'TBD', $venue['std'] );
        $group = $out['fields'][2];
        $this->assertArrayHasKey( 'fields', $group );
        $this->assertSame( 'act', $group['fields'][0]['id'] );
    }

    public function test_get_field_group_unknown_id_errors(): void {
        $this->seed_boxes();
        $out = $this->mb->run_metabox_read( array( 'operation' => 'get-field-group', 'arguments' => array( 'id' => 'nope' ) ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'group_not_found', $out->get_error_code() );
    }

    public function test_get_fields_reads_values_for_post(): void {
        $this->seed_boxes();
        $GLOBALS['emcp_test']['posts'][10] = (object) array( 'ID' => 10, 'post_type' => 'event' );
        $GLOBALS['emcp_test']['metabox']['values']['post']['10'] = array(
            'event_date' => '2026-08-01', 'venue' => 'The Roxy',
        );
        $out = $this->mb->run_metabox_read( array( 'operation' => 'get-fields', 'arguments' => array( 'post_id' => 10 ) ) );
        $this->assertSame( array( 'object_type' => 'post', 'object_id' => 10 ), $out['target'] );
        $this->assertSame( '2026-08-01', $out['fields']['event_date'] );
        $this->assertSame( 'The Roxy', $out['fields']['venue'] );
        // seo_meta applies to post/page, not to 'event' -> its field is absent.
        $this->assertArrayNotHasKey( 'seo_title', $out['fields'] );
    }

    public function test_get_fields_filter_subset(): void {
        $this->seed_boxes();
        $GLOBALS['emcp_test']['posts'][10] = (object) array( 'ID' => 10, 'post_type' => 'event' );
        $GLOBALS['emcp_test']['metabox']['values']['post']['10'] = array( 'event_date' => 'x', 'venue' => 'y' );
        $out = $this->mb->run_metabox_read( array( 'operation' => 'get-fields', 'arguments' => array( 'post_id' => 10, 'fields' => array( 'venue' ) ) ) );
        $this->assertSame( array( 'venue' => 'y' ), $out['fields'] );
    }

    public function test_get_fields_missing_target_errors(): void {
        $out = $this->mb->run_metabox_read( array( 'operation' => 'get-fields', 'arguments' => array() ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'invalid_target', $out->get_error_code() );
    }

    public function test_get_fields_unknown_post_errors(): void {
        $out = $this->mb->run_metabox_read( array( 'operation' => 'get-fields', 'arguments' => array( 'post_id' => 999 ) ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'post_not_found', $out->get_error_code() );
    }

    public function test_get_fields_normalizes_image_value(): void {
        $this->seed_boxes();
        $GLOBALS['emcp_test']['posts'][10] = (object) array( 'ID' => 10, 'post_type' => 'event' );
        $GLOBALS['emcp_test']['metabox']['values']['post']['10'] = array(
            'venue' => array( 'ID' => 55, 'url' => 'http://x/p.jpg', 'full_url' => 'http://x/p.jpg', 'alt' => 'hall', 'title' => 'Hall', 'width' => 800 ),
        );
        $out = $this->mb->run_metabox_read( array( 'operation' => 'get-fields', 'arguments' => array( 'post_id' => 10, 'fields' => array( 'venue' ) ) ) );
        $this->assertSame( array( 'id' => 55, 'url' => 'http://x/p.jpg', 'alt' => 'hall', 'title' => 'Hall' ), $out['fields']['venue'] );
    }

    public function test_update_fields_writes_and_rereads(): void {
        $this->seed_boxes();
        $GLOBALS['emcp_test']['posts'][10] = (object) array( 'ID' => 10, 'post_type' => 'event' );
        $out = $this->mb->run_metabox_write( array(
            'operation' => 'update-fields',
            'arguments' => array( 'post_id' => 10, 'fields' => array( 'venue' => 'Wembley' ) ),
        ) );
        $this->assertSame( array( 'venue' ), $out['updated'] );
        $this->assertSame( array(), $out['skipped'] );
        $this->assertSame( 'Wembley', $out['values']['venue'] );
        // Confirm it actually persisted to the fixture store.
        $this->assertSame( 'Wembley', $GLOBALS['emcp_test']['metabox']['values']['post']['10']['venue'] );
    }

    public function test_update_fields_skips_unknown_field(): void {
        $this->seed_boxes();
        $GLOBALS['emcp_test']['posts'][10] = (object) array( 'ID' => 10, 'post_type' => 'event' );
        $out = $this->mb->run_metabox_write( array(
            'operation' => 'update-fields',
            'arguments' => array( 'post_id' => 10, 'fields' => array( 'not_a_field' => 'x' ) ),
        ) );
        $this->assertSame( array(), $out['updated'] );
        $this->assertSame( array( array( 'field' => 'not_a_field', 'reason' => 'field_not_found' ) ), $out['skipped'] );
    }

    public function test_update_fields_requires_fields_map(): void {
        $this->seed_boxes();
        $GLOBALS['emcp_test']['posts'][10] = (object) array( 'ID' => 10, 'post_type' => 'event' );
        $out = $this->mb->run_metabox_write( array( 'operation' => 'update-fields', 'arguments' => array( 'post_id' => 10 ) ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'missing_params', $out->get_error_code() );
    }

    public function test_get_fields_forbidden_via_post_id_when_no_edit_post_cap(): void {
        $this->seed_boxes();
        $GLOBALS['emcp_test']['posts'][10]          = (object) array( 'ID' => 10, 'post_type' => 'event' );
        $GLOBALS['emcp_test']['post_caps'][10]       = false; // edit_posts yes, edit_post(10) no.
        $out = $this->mb->run_metabox_read( array( 'operation' => 'get-fields', 'arguments' => array( 'post_id' => 10 ) ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'forbidden', $out->get_error_code() );
    }

    public function test_get_fields_forbidden_via_object_type_post_when_no_edit_post_cap(): void {
        // Regression for I-1: object_type=post + object_id must be gated identically to post_id.
        $this->seed_boxes();
        $GLOBALS['emcp_test']['posts'][10]          = (object) array( 'ID' => 10, 'post_type' => 'event' );
        $GLOBALS['emcp_test']['post_caps'][10]       = false; // edit_posts yes, edit_post(10) no.
        $out = $this->mb->run_metabox_read( array(
            'operation' => 'get-fields',
            'arguments' => array( 'object_type' => 'post', 'object_id' => 10 ),
        ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'forbidden', $out->get_error_code() );
    }

    public function test_update_fields_forbidden_via_object_type_post_when_no_edit_post_cap(): void {
        // Same hole on the write path.
        $this->seed_boxes();
        $GLOBALS['emcp_test']['posts'][10]          = (object) array( 'ID' => 10, 'post_type' => 'event' );
        $GLOBALS['emcp_test']['post_caps'][10]       = false; // edit_posts yes, edit_post(10) no.
        $out = $this->mb->run_metabox_write( array(
            'operation' => 'update-fields',
            'arguments' => array( 'object_type' => 'post', 'object_id' => 10, 'fields' => array( 'venue' => 'Wembley' ) ),
        ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'forbidden', $out->get_error_code() );
    }

    public function test_get_fields_object_type_post_unknown_id_returns_post_not_found(): void {
        // Regression for the carried 404 gap: object_type=post + object_id must 404 just like post_id.
        $out = $this->mb->run_metabox_read( array(
            'operation' => 'get-fields',
            'arguments' => array( 'object_type' => 'post', 'object_id' => 999 ),
        ) );
        $this->assertInstanceOf( WP_Error::class, $out );
        $this->assertSame( 'post_not_found', $out->get_error_code() );
    }

    public function test_no_delete_operation_exists(): void {
        foreach ( array( 'delete-fields', 'delete-field-group', 'remove-fields' ) as $op ) {
            $out = $this->mb->run_metabox_write( array( 'operation' => $op ) );
            $this->assertInstanceOf( WP_Error::class, $out, "op $op must not exist" );
        }
    }
}

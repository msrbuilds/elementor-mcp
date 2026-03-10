<?php
/**
 * T3 Input boundary tests — page abilities.
 *
 * Tests that every tool returns WP_Error (not PHP fatal, not null, not array)
 * when given invalid, missing, or boundary inputs.
 *
 * Covers:
 *   create-page  — missing title
 *   update-page-settings — missing post_id (no post-existence check by design)
 *   delete-page-content  — missing post_id, invalid post_id
 *   import-template      — missing post_id, missing template_json, empty template
 *   export-page          — missing post_id, invalid post_id
 *
 * @group input
 * @group page
 * @package Elementor_MCP\Tests\Input
 */

namespace Elementor_MCP\Tests\Input;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class PageInputTest extends Ability_Test_Case {

	/** @var \Elementor_MCP_Page_Abilities */
	private $ability;

	protected function setUp(): void {
		parent::setUp();

		// Stub data so Document::get() returns WP_Error (simulates invalid post_id).
		$data = $this->createStub( \Elementor_MCP_Data::class );
		$data->method( 'get_document' )
		     ->willReturn( new \WP_Error( 'document_not_found', 'No document.' ) );
		$data->method( 'save_page_data' )->willReturn( true );
		$data->method( 'save_page_settings' )->willReturn( true );
		$data->method( 'get_page_data' )
		     ->willReturn( new \WP_Error( 'no_data', 'No data.' ) );

		$factory       = $this->make_factory();
		$this->ability = new \Elementor_MCP_Page_Abilities( $data, $factory );

		// Grant full caps so permission checks don't interfere with input tests.
		$this->allow_all_caps();
	}

	// -------------------------------------------------------------------------
	// create-page: missing title
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t3
	 */
	public function test_create_page_returns_wp_error_when_title_missing(): void {
		$result = $this->ability->execute_create_page( [] );
		$this->assertWPError( $result, 'missing_title' );
	}

	/**
	 * @test
	 * @group t3
	 */
	public function test_create_page_returns_wp_error_when_title_is_empty_string(): void {
		$result = $this->ability->execute_create_page( [ 'title' => '' ] );
		$this->assertWPError( $result, 'missing_title' );
	}

	/**
	 * @test
	 * @group t3
	 */
	public function test_create_page_returns_wp_error_when_title_is_whitespace(): void {
		$result = $this->ability->execute_create_page( [ 'title' => '   ' ] );
		$this->assertWPError( $result, 'missing_title' );
	}

	// -------------------------------------------------------------------------
	// update-page-settings: missing / invalid post_id
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t3
	 */
	public function test_update_page_settings_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_update_page_settings( [ 'settings' => [] ] );
		$this->assertWPError( $result );
	}

	/**
	 * @test
	 * @group t3
	 *
	 * Note: update-page-settings does NOT validate post existence — it calls
	 * save_page_settings() directly. An arbitrary post_id succeeds when the
	 * data layer succeeds. This documents the current behaviour (by design).
	 */
	public function test_update_page_settings_succeeds_with_any_post_id_when_data_save_succeeds(): void {
		$result = $this->ability->execute_update_page_settings( [ 'post_id' => 999999, 'settings' => [] ] );
		$this->assertNotWPError( $result );
	}

	// -------------------------------------------------------------------------
	// delete-page-content: missing / invalid post_id
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t3
	 */
	public function test_delete_page_content_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_delete_page_content( [] );
		$this->assertWPError( $result );
	}

	/**
	 * @test
	 * @group t3
	 */
	public function test_delete_page_content_returns_wp_error_when_post_id_zero(): void {
		$result = $this->ability->execute_delete_page_content( [ 'post_id' => 0 ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// import-template: missing post_id, missing/empty template_json
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t3
	 */
	public function test_import_template_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_import_template( [ 'template_json' => [ [] ] ] );
		$this->assertWPError( $result );
	}

	/**
	 * @test
	 * @group t3
	 */
	public function test_import_template_returns_wp_error_when_template_json_missing(): void {
		$result = $this->ability->execute_import_template( [ 'post_id' => 1 ] );
		$this->assertWPError( $result );
	}

	/**
	 * @test
	 * @group t3
	 */
	public function test_import_template_returns_wp_error_when_template_json_is_empty_array(): void {
		$result = $this->ability->execute_import_template( [ 'post_id' => 1, 'template_json' => [] ] );
		$this->assertWPError( $result );
	}

	// -------------------------------------------------------------------------
	// export-page: missing / invalid post_id
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t3
	 */
	public function test_export_page_returns_wp_error_when_post_id_missing(): void {
		$result = $this->ability->execute_export_page( [] );
		$this->assertWPError( $result );
	}

	/**
	 * @test
	 * @group t3
	 */
	public function test_export_page_returns_wp_error_when_post_id_invalid(): void {
		// Data stub's get_page_data returns WP_Error.
		$result = $this->ability->execute_export_page( [ 'post_id' => 999999 ] );
		$this->assertWPError( $result );
	}
}

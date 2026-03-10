<?php
/**
 * T4 Functional tests — create-page happy-path output shape.
 *
 * Verifies that execute_create_page() returns an array with the expected
 * keys and correct values when all inputs are valid.
 *
 * @group functional
 * @group page
 * @package Elementor_MCP\Tests\Functional
 */

namespace Elementor_MCP\Tests\Functional;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class PageFunctionalTest extends Ability_Test_Case {

	/** @var \Elementor_MCP_Page_Abilities */
	private $ability;

	protected function setUp(): void {
		parent::setUp();

		$data = $this->createStub( \Elementor_MCP_Data::class );
		$data->method( 'save_page_data' )->willReturn( true );
		$data->method( 'save_page_settings' )->willReturn( true );
		$data->method( 'get_document' )
		     ->willReturn( new \WP_Error( 'document_not_found', 'No document.' ) );
		$data->method( 'get_page_data' )
		     ->willReturn( new \WP_Error( 'no_data', 'No data.' ) );

		$this->ability = new \Elementor_MCP_Page_Abilities( $data, $this->make_factory() );
		$this->allow_all_caps();
	}

	// -------------------------------------------------------------------------
	// create-page: output shape
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * @group t4
	 *
	 * A valid create-page call returns an array with post_id, title,
	 * edit_url, and preview_url keys.
	 */
	public function test_create_page_returns_array_with_expected_keys(): void {
		$result = $this->ability->execute_create_page( [ 'title' => 'My Test Page' ] );

		$this->assertNotWPError( $result );
		$this->assertIsArray( $result );
		$this->assertResultHasKey( $result, 'post_id' );
		$this->assertResultHasKey( $result, 'title' );
		$this->assertResultHasKey( $result, 'edit_url' );
		$this->assertResultHasKey( $result, 'preview_url' );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * post_id is a positive integer.
	 */
	public function test_create_page_post_id_is_positive_integer(): void {
		$result = $this->ability->execute_create_page( [ 'title' => 'My Test Page' ] );

		$this->assertNotWPError( $result );
		$this->assertIsInt( $result['post_id'] );
		$this->assertGreaterThan( 0, $result['post_id'] );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * Returned title matches the input title (after sanitization).
	 */
	public function test_create_page_returned_title_matches_input(): void {
		$result = $this->ability->execute_create_page( [ 'title' => 'Hello World' ] );

		$this->assertNotWPError( $result );
		$this->assertSame( 'Hello World', $result['title'] );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * edit_url contains the post_id.
	 */
	public function test_create_page_edit_url_contains_post_id(): void {
		$result = $this->ability->execute_create_page( [ 'title' => 'URL Test' ] );

		$this->assertNotWPError( $result );
		$this->assertStringContainsString( (string) $result['post_id'], $result['edit_url'] );
	}

	/**
	 * @test
	 * @group t4
	 *
	 * Two sequential calls return different post_ids (IDs are unique).
	 */
	public function test_create_page_sequential_calls_return_different_post_ids(): void {
		$result1 = $this->ability->execute_create_page( [ 'title' => 'Page One' ] );
		$result2 = $this->ability->execute_create_page( [ 'title' => 'Page Two' ] );

		$this->assertNotWPError( $result1 );
		$this->assertNotWPError( $result2 );
		$this->assertNotSame( $result1['post_id'], $result2['post_id'] );
	}
}

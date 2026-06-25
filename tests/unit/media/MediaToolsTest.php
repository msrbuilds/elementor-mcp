<?php
/**
 * Execute-path tests for the Media Library write/detail tools.
 * @group media
 * @package EMCP_Tools\Tests\Media
 */
namespace EMCP_Tools\Tests\Media;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class MediaToolsTest extends Ability_Test_Case {
	private \EMCP_Tools_Media_Library_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_posts'] = array(
			77 => (object) array( 'ID' => 77, 'post_type' => 'attachment', 'post_title' => 'Sunset', 'post_name' => 'sunset', 'post_excerpt' => 'A caption', 'post_content' => 'A description', 'post_mime_type' => 'image/jpeg', 'post_date' => '2026-01-01 00:00:00', 'post_author' => 1, 'post_parent' => 0, 'post_status' => 'inherit' ),
			88 => (object) array( 'ID' => 88, 'post_type' => 'page', 'post_title' => 'Not media', 'post_status' => 'publish' ),
		);
		$GLOBALS['_wp_attachment_meta'] = array( 77 => array( 'width' => 1200, 'height' => 800, 'file' => '2026/01/sunset.jpg', 'filesize' => 24000, 'sizes' => array( 'thumbnail' => array( 'file' => 'sunset-150x150.jpg', 'width' => 150, 'height' => 150 ) ) ) );
		$GLOBALS['_wp_attachment_src']  = array( 77 => array( 'full' => array( 'http://x/sunset.jpg', 1200, 800, false ), 'thumbnail' => array( 'http://x/sunset-150x150.jpg', 150, 150, true ) ) );
		$GLOBALS['_wp_attachment_url']  = array( 77 => 'http://x/sunset.jpg' );
		$GLOBALS['_wp_deleted_attachments'] = array();
		$this->ability = new \EMCP_Tools_Media_Library_Abilities( $this->make_data_stub() );
		$this->ability->register();
	}

	/** @test */
	public function test_registers_four_tools(): void {
		$names = $this->ability->get_ability_names();
		foreach ( array( 'list-media', 'get-media', 'update-media', 'delete-media' ) as $slug ) {
			$this->assertContains( 'emcp-tools/' . $slug, $names );
		}
		$this->assertCount( 4, $names );
	}

	/** @test */
	public function test_get_media_returns_detail_and_sizes(): void {
		$out = $this->ability->execute_get_media( array( 'id' => 77 ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 77, $out['id'] );
		$this->assertSame( 'Sunset', $out['title'] );
		$this->assertSame( 'A caption', $out['caption'] );
		$this->assertSame( 'A description', $out['description'] );
		$this->assertSame( 1200, $out['width'] );
		$this->assertArrayHasKey( 'thumbnail', $out['sizes'] );
		$this->assertSame( 150, $out['sizes']['thumbnail']['width'] );
	}

	/** @test */
	public function test_get_media_rejects_non_attachment(): void {
		$this->assertWPError( $this->ability->execute_get_media( array( 'id' => 88 ) ), 'not_an_attachment' );
	}

	/** @test */
	public function test_get_media_requires_id(): void {
		$this->assertWPError( $this->ability->execute_get_media( array() ), 'missing_params' );
	}

	/** @test */
	public function test_update_media_writes_only_passed_fields(): void {
		$out = $this->ability->execute_update_media( array( 'id' => 77, 'alt' => 'Sunset over the sea', 'title' => 'Sunset HQ' ) );
		$this->assertNotWPError( $out );
		$this->assertContains( 'alt', $out['updated'] );
		$this->assertContains( 'title', $out['updated'] );
		$this->assertNotContains( 'caption', $out['updated'] );
		// alt written via _wp_attachment_image_alt meta.
		$altCalls = array_filter( $GLOBALS['_wp_meta_calls'], fn( $c ) => ( $c['meta_key'] ?? '' ) === '_wp_attachment_image_alt' && ( $c['post_id'] ?? 0 ) === 77 );
		$this->assertNotEmpty( $altCalls );
	}

	/** @test */
	public function test_update_media_rejects_non_attachment(): void {
		$this->assertWPError( $this->ability->execute_update_media( array( 'id' => 88, 'alt' => 'x' ) ), 'not_an_attachment' );
	}

	/** @test */
	public function test_update_media_requires_id(): void {
		$this->assertWPError( $this->ability->execute_update_media( array( 'alt' => 'x' ) ), 'missing_params' );
	}

	/** @test */
	public function test_delete_media_requires_confirm(): void {
		$out = $this->ability->execute_delete_media( array( 'id' => 77 ) );
		$this->assertWPError( $out, 'confirmation_required' );
		$this->assertSame( array(), $GLOBALS['_wp_deleted_attachments'] );
	}

	/** @test */
	public function test_delete_media_deletes_with_confirm_and_force(): void {
		$out = $this->ability->execute_delete_media( array( 'id' => 77, 'confirm' => true, 'force' => true ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['success'] );
		$this->assertSame( 'deleted', $out['deleted'] );
		$this->assertSame( array( array( 'id' => 77, 'force' => true ) ), $GLOBALS['_wp_deleted_attachments'] );
	}

	/** @test */
	public function test_delete_media_rejects_non_attachment(): void {
		$this->assertWPError( $this->ability->execute_delete_media( array( 'id' => 88, 'confirm' => true ) ), 'not_an_attachment' );
	}

	/** @test */
	public function test_delete_media_requires_id(): void {
		$this->assertWPError( $this->ability->execute_delete_media( array( 'confirm' => true ) ), 'missing_params' );
	}
}

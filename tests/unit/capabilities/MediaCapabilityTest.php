<?php
/**
 * Capability gating for the Media Library write/detail tools.
 * @group capabilities
 * @group media
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class MediaCapabilityTest extends Ability_Test_Case {
	private \EMCP_Tools_Media_Library_Abilities $a;

	protected function setUp(): void {
		parent::setUp();
		$this->a = new \EMCP_Tools_Media_Library_Abilities( $this->make_data_stub() );
		$this->a->register();
	}

	/** @test */
	public function test_read_requires_edit_posts(): void {
		$this->deny_all_caps();
		$this->assertFalse( $this->a->check_read_permission() );
		$this->allow_caps( 'edit_posts' );
		$this->assertTrue( $this->a->check_read_permission() );
	}

	/** @test */
	public function test_edit_requires_edit_post_on_id(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->a->check_edit_permission( array( 'id' => 77 ) ) );
		$this->allow_caps( 'edit_posts', 'edit_post' );
		$this->assertTrue( $this->a->check_edit_permission( array( 'id' => 77 ) ) );
	}

	/** @test */
	public function test_delete_requires_delete_post_on_id(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->a->check_delete_permission( array( 'id' => 77 ) ) );
		$this->allow_caps( 'delete_post' );
		$this->assertTrue( $this->a->check_delete_permission( array( 'id' => 77 ) ) );
	}
}

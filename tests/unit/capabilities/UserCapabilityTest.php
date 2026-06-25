<?php
/**
 * Capability gating for the Users tools.
 * @group capabilities
 * @group users
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class UserCapabilityTest extends Ability_Test_Case {
	private \EMCP_Tools_User_Abilities $a;

	protected function setUp(): void {
		parent::setUp();
		$this->a = new \EMCP_Tools_User_Abilities();
		$this->a->register();
	}

	/** @test */
	public function test_reads_require_list_users(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->a->can_list() );
		$this->allow_caps( 'list_users' );
		$this->assertTrue( $this->a->can_list() );
	}

	/** @test */
	public function test_create_requires_create_users(): void {
		$this->allow_caps( 'list_users' );
		$this->assertFalse( $this->a->can_create() );
		$this->allow_caps( 'create_users' );
		$this->assertTrue( $this->a->can_create() );
	}

	/** @test */
	public function test_update_requires_edit_users(): void {
		$this->allow_caps( 'list_users' );
		$this->assertFalse( $this->a->can_edit() );
		$this->allow_caps( 'edit_users' );
		$this->assertTrue( $this->a->can_edit() );
	}
}

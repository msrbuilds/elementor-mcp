<?php
/**
 * Capability gating for the Themes tools.
 * @group capabilities
 * @group packages
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ThemeCapabilityTest extends Ability_Test_Case {
	private \EMCP_Tools_Theme_Abilities $a;

	protected function setUp(): void {
		parent::setUp();
		$this->a = new \EMCP_Tools_Theme_Abilities();
		$this->a->register();
	}

	/** @test */
	public function test_list_requires_switch_themes(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->a->can_list() );
		$this->allow_caps( 'switch_themes' );
		$this->assertTrue( $this->a->can_list() );
	}

	/** @test */
	public function test_install_requires_install_themes(): void {
		$this->allow_caps( 'switch_themes' );
		$this->assertFalse( $this->a->can_install() );
		$this->allow_caps( 'install_themes' );
		$this->assertTrue( $this->a->can_install() );
	}

	/** @test */
	public function test_update_requires_update_themes(): void {
		$this->allow_caps( 'switch_themes' );
		$this->assertFalse( $this->a->can_update() );
		$this->allow_caps( 'update_themes' );
		$this->assertTrue( $this->a->can_update() );
	}

	/** @test */
	public function test_delete_requires_delete_themes(): void {
		$this->allow_caps( 'switch_themes' );
		$this->assertFalse( $this->a->can_delete() );
		$this->allow_caps( 'delete_themes' );
		$this->assertTrue( $this->a->can_delete() );
	}
}

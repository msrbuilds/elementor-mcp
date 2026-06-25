<?php
/**
 * Capability gating for the WordPress Settings tools.
 * @group capabilities
 * @group settings
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class SettingsCapabilityTest extends Ability_Test_Case {
	private \EMCP_Tools_Settings_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$this->ability = new \EMCP_Tools_Settings_Abilities();
		$this->ability->register();
	}

	/** @test */
	public function test_registers_both_tools(): void {
		$names = $this->ability->get_ability_names();
		$this->assertContains( 'emcp-tools/get-settings', $names );
		$this->assertContains( 'emcp-tools/update-settings', $names );
		$this->assertCount( 2, $names );
	}

	/** @test */
	public function test_denied_without_manage_options(): void {
		$this->allow_caps( 'edit_posts' );
		$this->assertFalse( $this->ability->check_manage_permission() );
	}

	/** @test */
	public function test_allowed_with_manage_options(): void {
		$this->allow_caps( 'manage_options' );
		$this->assertTrue( $this->ability->check_manage_permission() );
	}
}

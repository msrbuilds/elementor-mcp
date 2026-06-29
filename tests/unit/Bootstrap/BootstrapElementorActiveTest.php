<?php
/**
 * @group bootstrap
 * @package EMCP_Tools\Tests
 */
namespace EMCP_Tools\Tests;

use PHPUnit\Framework\TestCase;

class BootstrapElementorActiveTest extends TestCase {

	protected function setUp(): void {
		$GLOBALS['_did_actions'] = array();
	}

	/** @test */
	public function elementor_active_is_false_when_loaded_action_never_fired(): void {
		$this->assertFalse( \EMCP_Tools_Bootstrap::elementor_active() );
	}

	/** @test */
	public function elementor_active_is_true_after_loaded_action(): void {
		$GLOBALS['_did_actions']['elementor/loaded'] = 1;
		$this->assertTrue( \EMCP_Tools_Bootstrap::elementor_active() );
	}
}

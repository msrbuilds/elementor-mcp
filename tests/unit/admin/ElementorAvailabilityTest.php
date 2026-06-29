<?php
/**
 * @group admin
 * @package EMCP_Tools\Tests\Admin
 */
namespace EMCP_Tools\Tests\Admin;

use PHPUnit\Framework\TestCase;

class ElementorAvailabilityTest extends TestCase {

	/** @test */
	public function is_elementor_category_defaults_missing_platform_to_elementor(): void {
		$this->assertTrue( \EMCP_Tools_Admin::is_elementor_category( array( 'tools' => array() ) ) );
		$this->assertTrue( \EMCP_Tools_Admin::is_elementor_category( array( 'platform' => 'elementor', 'tools' => array() ) ) );
		$this->assertFalse( \EMCP_Tools_Admin::is_elementor_category( array( 'platform' => 'wordpress', 'tools' => array() ) ) );
	}

	/** @test */
	public function filter_out_elementor_drops_only_elementor_platform_categories(): void {
		$cats = array(
			'query'       => array( 'platform' => 'elementor', 'tools' => array() ),
			'content'     => array( 'platform' => 'wordpress', 'tools' => array() ),
			'no_platform' => array( 'tools' => array() ),
			'security'    => array( 'platform' => 'wordpress', 'tools' => array() ),
		);
		$kept = \EMCP_Tools_Admin::filter_out_elementor( $cats );
		$this->assertSame( array( 'content', 'security' ), array_keys( $kept ) );
	}
}

<?php
/**
 * @group security-scanner
 * @package Elementor_MCP\Tests\SecurityScanner
 */
namespace Elementor_MCP\Tests\SecurityScanner;

use PHPUnit\Framework\TestCase;

class SecurityAbilitiesTest extends TestCase {

	/** @test */
	public function register_collects_the_ability_name(): void {
		$abilities = new \Elementor_MCP_Security_Abilities();
		$abilities->register();
		$this->assertSame( array( 'elementor-mcp/scan-security' ), $abilities->get_ability_names() );
	}

	/** @test */
	public function permission_is_manage_options(): void {
		// Stub current_user_can() returns true by default; assert it is wired.
		$this->assertTrue( ( new \Elementor_MCP_Security_Abilities() )->check_permission() );
	}

	/** @test */
	public function execute_returns_a_report_shape(): void {
		// Inject a scanner double so no filesystem/HTTP is touched.
		$scanner = new class extends \Elementor_MCP_Security_Scanner {
			public function scan( array $input ): array {
				return array( 'summary' => array( 'score' => 100, 'grade' => 'A', 'counts' => array() ), 'sections' => array(), 'scan_meta' => array(), 'top_recommendations' => array() );
			}
		};
		$abilities = new \Elementor_MCP_Security_Abilities( $scanner );
		$report    = $abilities->execute_scan_security( array() );
		$this->assertSame( 'A', $report['summary']['grade'] );
	}
}

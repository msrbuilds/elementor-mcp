<?php
/**
 * @group security
 * @package EMCP_Tools\Tests\Security
 */
namespace EMCP_Tools\Tests\Security;

use PHPUnit\Framework\TestCase;

class SoftwareAuditTest extends TestCase {

	private function audit(): \EMCP_Tools_Security_Software_Audit {
		return new \EMCP_Tools_Security_Software_Audit();
	}

	private function ids( array $findings ): array {
		return array_map( static fn( $f ) => $f['id'], $findings );
	}

	/** @test */
	public function core_update_available_is_warning(): void {
		$this->assertSame( 'warning', $this->audit()->evaluate_core_update( true, '6.4', '6.9' )['status'] );
		$this->assertSame( 'pass', $this->audit()->evaluate_core_update( false, '6.9', '6.9' )['status'] );
	}

	/** @test */
	public function outdated_components_each_warn(): void {
		$updates  = array(
			array( 'name' => 'Foo', 'current' => '1.0', 'new' => '2.0' ),
			array( 'name' => 'Bar', 'current' => '3.1', 'new' => '3.2' ),
		);
		$findings = $this->audit()->evaluate_updates( $updates, 'plugin' );
		$this->assertCount( 2, $findings );
		$this->assertSame( 'warning', $findings[0]['status'] );
		$this->assertSame( 'software', $findings[0]['category'] );
	}

	/** @test */
	public function no_updates_yields_no_findings(): void {
		$this->assertSame( array(), $this->audit()->evaluate_updates( array(), 'plugin' ) );
	}

	/** @test */
	public function abandoned_plugins_each_warn(): void {
		$findings = $this->audit()->evaluate_abandoned( array( 'old-plugin', 'dead-plugin' ) );
		$this->assertSame( array( 'software_abandoned', 'software_abandoned' ), $this->ids( $findings ) );
		$this->assertSame( 'warning', $findings[0]['status'] );
	}

	/** @test */
	public function inactive_components_are_info(): void {
		$this->assertSame( 'info', $this->audit()->evaluate_inactive( 3 )['status'] );
		$this->assertSame( 'pass', $this->audit()->evaluate_inactive( 0 )['status'] );
	}
}

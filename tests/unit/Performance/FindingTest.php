<?php
/**
 * @group performance
 * @package EMCP_Tools\Tests\Performance
 */
namespace EMCP_Tools\Tests\Performance;

use PHPUnit\Framework\TestCase;

class FindingTest extends TestCase {

	/** @test */
	public function make_builds_a_uniform_finding_array(): void {
		$f = \EMCP_Tools_Performance_Finding::make(
			'php_version', 'server', 'PHP version', 'warning', '8.1.0',
			'PHP 8.1 is approaching end of life.', 'Upgrade to PHP 8.2 or newer.'
		);
		$this->assertSame( 'php_version', $f['id'] );
		$this->assertSame( 'server', $f['category'] );
		$this->assertSame( 'PHP version', $f['label'] );
		$this->assertSame( 'warning', $f['status'] );
		$this->assertSame( '8.1.0', $f['value'] );
		$this->assertSame( 'PHP 8.1 is approaching end of life.', $f['message'] );
		$this->assertSame( 'Upgrade to PHP 8.2 or newer.', $f['recommendation'] );
	}

	/** @test */
	public function recommendation_defaults_to_empty_string(): void {
		$f = \EMCP_Tools_Performance_Finding::make( 'x', 'server', 'X', 'pass', 1, 'Good.' );
		$this->assertSame( '', $f['recommendation'] );
		$this->assertSame( 1, $f['value'] );
	}
}

<?php
/**
 * @group security
 * @package EMCP_Tools\Tests\Security
 */
namespace EMCP_Tools\Tests\Security;

use PHPUnit\Framework\TestCase;

class SecurityFindingTest extends TestCase {

	/** @test */
	public function make_returns_the_canonical_finding_array(): void {
		$f = \EMCP_Tools_Security_Finding::make(
			'integrity_modified', 'integrity', 'Modified core file', 'critical',
			'wp-load.php', 'Checksum mismatch.', 'Reinstall core.'
		);
		$this->assertSame( 'integrity_modified', $f['id'] );
		$this->assertSame( 'integrity', $f['category'] );
		$this->assertSame( 'Modified core file', $f['label'] );
		$this->assertSame( 'critical', $f['status'] );
		$this->assertSame( 'wp-load.php', $f['value'] );
		$this->assertSame( 'Checksum mismatch.', $f['message'] );
		$this->assertSame( 'Reinstall core.', $f['recommendation'] );
	}

	/** @test */
	public function recommendation_defaults_to_empty(): void {
		$f = \EMCP_Tools_Security_Finding::make( 'x', 'hardening', 'X', 'pass', true, 'ok' );
		$this->assertSame( '', $f['recommendation'] );
	}
}

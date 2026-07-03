<?php
/**
 * @group security-scanner
 * @package Elementor_MCP\Tests\SecurityScanner
 */
namespace Elementor_MCP\Tests\SecurityScanner;

use PHPUnit\Framework\TestCase;

class IntegrityAuditTest extends TestCase {

	private function statuses( array $findings ): array {
		$out = array();
		foreach ( $findings as $f ) {
			$out[ $f['value'] ] = $f['status'];
		}
		return $out;
	}

	/** @test */
	public function matching_files_produce_no_findings(): void {
		$checksums = array( 'wp-load.php' => 'aaa', 'index.php' => 'bbb' );
		$hasher    = static fn( $path ) => array( 'wp-load.php' => 'aaa', 'index.php' => 'bbb' )[ $path ] ?? null;
		$this->assertSame( array(), ( new \Elementor_MCP_Security_Integrity_Audit() )->diff( $checksums, $hasher ) );
	}

	/** @test */
	public function modified_file_is_critical_and_missing_is_warning(): void {
		$checksums = array( 'wp-load.php' => 'aaa', 'gone.php' => 'bbb' );
		$hasher    = static fn( $path ) => 'wp-load.php' === $path ? 'CHANGED' : null;
		$findings  = ( new \Elementor_MCP_Security_Integrity_Audit() )->diff( $checksums, $hasher );
		$map       = $this->statuses( $findings );
		$this->assertSame( 'critical', $map['wp-load.php'] );
		$this->assertSame( 'warning', $map['gone.php'] );
	}

	/** @test */
	public function checksum_comparison_is_case_insensitive(): void {
		$checksums = array( 'wp-load.php' => 'ABCDEF' );
		$hasher    = static fn( $path ) => 'abcdef';
		$this->assertSame( array(), ( new \Elementor_MCP_Security_Integrity_Audit() )->diff( $checksums, $hasher ) );
	}
}

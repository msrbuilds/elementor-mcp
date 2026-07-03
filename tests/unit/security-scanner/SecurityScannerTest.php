<?php
/**
 * @group security-scanner
 * @package Elementor_MCP\Tests\SecurityScanner
 */
namespace Elementor_MCP\Tests\SecurityScanner;

use PHPUnit\Framework\TestCase;

class SecurityScannerTest extends TestCase {

	private function scanner(): \Elementor_MCP_Security_Scanner {
		return new \Elementor_MCP_Security_Scanner();
	}

	private function finding( string $category, string $status, string $rec = 'fix it', string $label = 'L' ): array {
		return \Elementor_MCP_Security_Finding::make( 'id', $category, $label, $status, null, 'm', $rec );
	}

	/** @test */
	public function resolve_checks_defaults_to_all_four_in_canonical_order(): void {
		$this->assertSame( array( 'malware', 'integrity', 'hardening', 'software' ), $this->scanner()->resolve_checks( null ) );
		$this->assertSame( array( 'malware', 'integrity', 'hardening', 'software' ), $this->scanner()->resolve_checks( array() ) );
	}

	/** @test */
	public function resolve_checks_filters_to_valid_subset_in_canonical_order(): void {
		$this->assertSame( array( 'integrity', 'hardening' ), $this->scanner()->resolve_checks( array( 'hardening', 'integrity', 'bogus' ) ) );
	}

	/** @test */
	public function resolve_checks_falls_back_to_all_when_no_valid_values(): void {
		$this->assertSame( array( 'malware', 'integrity', 'hardening', 'software' ), $this->scanner()->resolve_checks( array( 'bogus', 'nope' ) ) );
	}

	/** @test */
	public function score_subtracts_weighted_penalties_and_bands_grade(): void {
		$findings = array(
			$this->finding( 'hardening', 'critical' ), // -20
			$this->finding( 'software', 'warning' ),   // -5
			$this->finding( 'integrity', 'pass' ),     // 0
		);
		$summary = $this->scanner()->summarize( $findings );
		$this->assertSame( 75, $summary['score'] );
		$this->assertSame( 'C', $summary['grade'] );
		$this->assertSame( 1, $summary['counts']['critical'] );
		$this->assertSame( 1, $summary['counts']['warning'] );
		$this->assertSame( 1, $summary['counts']['pass'] );
	}

	/** @test */
	public function per_category_critical_penalty_is_capped(): void {
		// 5 malware criticals would be -100, but the per-category cap is -60.
		$findings = array_fill( 0, 5, $this->finding( 'malware', 'critical' ) );
		$summary  = $this->scanner()->summarize( $findings );
		$this->assertSame( 40, $summary['score'] ); // 100 - 60
	}

	/** @test */
	public function score_clamps_at_zero(): void {
		$findings = array_merge(
			array_fill( 0, 5, $this->finding( 'malware', 'critical' ) ),    // -60 (capped)
			array_fill( 0, 5, $this->finding( 'integrity', 'critical' ) ),  // -60 (capped)
			array_fill( 0, 5, $this->finding( 'hardening', 'critical' ) )   // -60 (capped) => -180 total
		);
		$this->assertSame( 0, $this->scanner()->summarize( $findings )['score'] );
	}

	/** @test */
	public function top_recommendations_rank_critical_before_warning(): void {
		$findings = array(
			$this->finding( 'software', 'warning', 'warn-rec', 'W' ),
			$this->finding( 'malware', 'critical', 'crit-rec', 'C' ),
		);
		$recs = $this->scanner()->summarize( $findings )['top_recommendations'];
		$this->assertStringContainsString( 'crit-rec', $recs[0] );
		$this->assertStringContainsString( 'warn-rec', $recs[1] );
	}

	/** @test */
	public function group_by_category_buckets_findings(): void {
		$sections = $this->scanner()->group_by_category( array(
			$this->finding( 'malware', 'critical' ),
			$this->finding( 'hardening', 'warning' ),
		) );
		$this->assertCount( 1, $sections['malware'] );
		$this->assertCount( 1, $sections['hardening'] );
		$this->assertSame( array(), $sections['integrity'] );
		$this->assertSame( array(), $sections['software'] );
	}
}

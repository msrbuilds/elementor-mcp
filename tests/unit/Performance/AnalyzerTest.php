<?php
/**
 * @group performance
 * @package EMCP_Tools\Tests\Performance
 */
namespace EMCP_Tools\Tests\Performance;

use PHPUnit\Framework\TestCase;

class AnalyzerTest extends TestCase {

	private \EMCP_Tools_Performance_Analyzer $analyzer;

	protected function setUp(): void {
		$this->analyzer = new \EMCP_Tools_Performance_Analyzer();
	}

	private function f( string $status, string $rec = '' ): array {
		return \EMCP_Tools_Performance_Finding::make( 'x', 'server', 'X', $status, 1, 'm', $rec );
	}

	/** @test */
	public function summarize_counts_and_scores(): void {
		$findings = array(
			$this->f( 'critical', 'fix me' ),
			$this->f( 'warning', 'maybe' ),
			$this->f( 'warning', 'maybe2' ),
			$this->f( 'pass' ),
			$this->f( 'info' ),
		);
		$s = $this->analyzer->summarize( $findings );
		$this->assertSame( 1, $s['counts']['critical'] );
		$this->assertSame( 2, $s['counts']['warning'] );
		$this->assertSame( 1, $s['counts']['pass'] );
		$this->assertSame( 1, $s['counts']['info'] );
		// 100 - (1*15) - (2*4) = 77 → grade C.
		$this->assertSame( 77, $s['score'] );
		$this->assertSame( 'C', $s['grade'] );
	}

	/** @test */
	public function score_clamps_at_zero(): void {
		$findings = array_fill( 0, 10, $this->f( 'critical', 'x' ) );
		$s        = $this->analyzer->summarize( $findings );
		$this->assertSame( 0, $s['score'] );
		$this->assertSame( 'F', $s['grade'] );
	}

	/** @test */
	public function perfect_score_is_grade_a(): void {
		$s = $this->analyzer->summarize( array( $this->f( 'pass' ), $this->f( 'info' ) ) );
		$this->assertSame( 100, $s['score'] );
		$this->assertSame( 'A', $s['grade'] );
	}

	/** @test */
	public function top_recommendations_rank_critical_first(): void {
		$findings = array(
			$this->f( 'warning', 'warn rec' ),
			$this->f( 'critical', 'crit rec' ),
			$this->f( 'pass' ),
		);
		$s = $this->analyzer->summarize( $findings );
		$this->assertNotEmpty( $s['top_recommendations'] );
		$this->assertStringContainsString( 'crit rec', $s['top_recommendations'][0] );
	}

	/** @test */
	public function same_host_guard(): void {
		$this->assertTrue( $this->analyzer->validate_same_host( 'https://example.com/page', 'example.com' ) );
		$this->assertFalse( $this->analyzer->validate_same_host( 'https://evil.test/page', 'example.com' ) );
	}
}

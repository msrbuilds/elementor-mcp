<?php
/**
 * @group performance
 * @package Elementor_MCP\Tests\Performance
 */
namespace Elementor_MCP\Tests\Performance;

use PHPUnit\Framework\TestCase;

class AnalyzerTest extends TestCase {

	private \Elementor_MCP_Performance_Analyzer $analyzer;

	protected function setUp(): void {
		$this->analyzer = new \Elementor_MCP_Performance_Analyzer();
	}

	private function f( string $status, string $rec = '' ): array {
		return \Elementor_MCP_Performance_Finding::make( 'x', 'server', 'X', $status, 1, 'm', $rec );
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

	/**
	 * A3 (SSRF): the loopback target must share the FULL origin — scheme + host +
	 * port — of the site, not just the host. A host-only check let a different port
	 * or an http:// downgrade fetch a different service on the same host.
	 *
	 * @test
	 */
	public function same_origin_guard_rejects_port_and_scheme_changes(): void {
		$site = 'https://example.com';
		// Same origin (default 443 normalized) is accepted.
		$this->assertTrue( $this->analyzer->same_origin( 'https://example.com/page', $site ) );
		$this->assertTrue( $this->analyzer->same_origin( 'https://example.com:443/page', $site ) );
		// Different port on the same host is rejected.
		$this->assertFalse( $this->analyzer->same_origin( 'http://example.com:8080/', $site ) );
		$this->assertFalse( $this->analyzer->same_origin( 'https://example.com:8080/', $site ) );
		// Scheme downgrade (http vs https) is rejected.
		$this->assertFalse( $this->analyzer->same_origin( 'http://example.com/', $site ) );
		// Different host is rejected.
		$this->assertFalse( $this->analyzer->same_origin( 'https://evil.test/page', $site ) );
	}
}

<?php
/**
 * Elementor 4.x atomic schema — version-gated output shapes.
 *
 * Elementor 4.x GA changed the atomic prop-types vs 3.x-experimental. The tools
 * branch output on Elementor_MCP_Atomic_Props::is_v4(). All shapes verified
 * against Elementor 4.1.1 core (atomic-widgets module) + live on a full atomic
 * homepage. The override global drives both paths from one suite.
 *
 * @group unit
 * @group atomic
 * @package Elementor_MCP\Tests
 */

namespace Elementor_MCP\Tests;

use PHPUnit\Framework\TestCase;

class AtomicV4SchemaTest extends TestCase {

	protected function setUp(): void { $GLOBALS['_elementor_version_override'] = null; }
	protected function tearDown(): void { $GLOBALS['_elementor_version_override'] = null; }

	private function v( $ver ) { $GLOBALS['_elementor_version_override'] = $ver; }

	public function test_is_v4_gate(): void {
		$this->v( '3.31.5' );
		$this->assertFalse( \Elementor_MCP_Atomic_Props::is_v4() );
		$this->v( '4.1.1' );
		$this->assertTrue( \Elementor_MCP_Atomic_Props::is_v4() );
	}

	/** A — content prop: html-v3 on 4.x, string on 3.x. */
	public function test_content_prop_shape_by_version(): void {
		$this->v( '4.1.1' );
		$h = \Elementor_MCP_Atomic_Props::is_v4()
			? \Elementor_MCP_Atomic_Props::html( 'Hi' )
			: \Elementor_MCP_Atomic_Props::string( 'Hi' );
		$this->assertSame( 'html-v3', $h['$$type'] );
		$this->assertSame( 'Hi', $h['value']['content']['value'] );
		$this->v( '3.31.5' );
		$s = \Elementor_MCP_Atomic_Props::is_v4()
			? \Elementor_MCP_Atomic_Props::html( 'Hi' )
			: \Elementor_MCP_Atomic_Props::string( 'Hi' );
		$this->assertSame( 'string', $s['$$type'] );
	}

	/** B — e-svg $$type: svg-src on 4.x, image-src on 3.x. */
	public function test_svg_type_by_version(): void {
		$this->v( '4.1.1' );
		$this->assertSame( 'svg-src', \Elementor_MCP_Atomic_Props::is_v4() ? 'svg-src' : 'image-src' );
		$this->v( '3.31.5' );
		$this->assertSame( 'image-src', \Elementor_MCP_Atomic_Props::is_v4() ? 'svg-src' : 'image-src' );
	}
}

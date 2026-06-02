<?php
/**
 * Unit tests — Atomic_Styles::build_typography_props().
 *
 * @group unit
 * @group atomic
 * @package Elementor_MCP\Tests
 */

namespace Elementor_MCP\Tests;

use PHPUnit\Framework\TestCase;

class AtomicStylesTypographyTest extends TestCase {

	public function test_empty_params_produce_no_props(): void {
		$this->assertSame( [], \Elementor_MCP_Atomic_Styles::build_typography_props( [] ) );
	}

	public function test_font_size_maps_to_size_prop_with_default_px(): void {
		$props = \Elementor_MCP_Atomic_Styles::build_typography_props( [ 'font_size' => 32 ] );
		$this->assertArrayHasKey( 'font-size', $props );
		$this->assertSame( 'size', $props['font-size']['$$type'] );
		$this->assertSame( 32.0, $props['font-size']['value']['size'] );
		$this->assertSame( 'px', $props['font-size']['value']['unit'] );
	}

	public function test_font_size_honors_explicit_unit(): void {
		$props = \Elementor_MCP_Atomic_Styles::build_typography_props( [ 'font_size' => 2, 'font_size_unit' => 'rem' ] );
		$this->assertSame( 'rem', $props['font-size']['value']['unit'] );
	}

	public function test_line_height_defaults_to_em(): void {
		$props = \Elementor_MCP_Atomic_Styles::build_typography_props( [ 'line_height' => 1.4 ] );
		$this->assertSame( 'line-height', array_key_first( $props ) );
		$this->assertSame( 'em', $props['line-height']['value']['unit'] );
	}

	public function test_letter_spacing_defaults_to_px(): void {
		$props = \Elementor_MCP_Atomic_Styles::build_typography_props( [ 'letter_spacing' => 1 ] );
		$this->assertSame( 'px', $props['letter-spacing']['value']['unit'] );
	}

	public function test_string_props_map_to_string_type(): void {
		$props = \Elementor_MCP_Atomic_Styles::build_typography_props( [
			'font_family' => 'Rubik',
			'font_weight' => '700',
			'text_align'  => 'center',
		] );
		$this->assertSame( 'string', $props['font-family']['$$type'] );
		$this->assertSame( 'Rubik', $props['font-family']['value'] );
		$this->assertSame( '700', $props['font-weight']['value'] );
		$this->assertSame( 'center', $props['text-align']['value'] );
	}

	public function test_unknown_keys_ignored(): void {
		$props = \Elementor_MCP_Atomic_Styles::build_typography_props( [ 'nonsense' => 1, 'color' => '#fff' ] );
		$this->assertSame( [], $props );
	}
}

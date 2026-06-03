<?php
/**
 * Unit tests for EMCP_Tools_Color_Contrast (WCAG math).
 *
 * @group seo
 * @group a11y
 * @package EMCP_Tools\Tests\Seo
 */

namespace EMCP_Tools\Tests\Seo;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;
use EMCP_Tools_Color_Contrast;

class ColorContrastTest extends Ability_Test_Case {

	// ---- hex_to_rgb ---------------------------------------------------------

	public function test_hex_to_rgb_six_digit(): void {
		$this->assertSame( array( 255, 255, 255 ), EMCP_Tools_Color_Contrast::hex_to_rgb( '#FFFFFF' ) );
		$this->assertSame( array( 0, 0, 0 ), EMCP_Tools_Color_Contrast::hex_to_rgb( '#000000' ) );
		$this->assertSame( array( 99, 102, 241 ), EMCP_Tools_Color_Contrast::hex_to_rgb( '#6366F1' ) );
	}

	public function test_hex_to_rgb_three_digit_and_no_hash(): void {
		$this->assertSame( array( 255, 255, 255 ), EMCP_Tools_Color_Contrast::hex_to_rgb( 'fff' ) );
		$this->assertSame( array( 17, 34, 51 ), EMCP_Tools_Color_Contrast::hex_to_rgb( '#123' ) );
	}

	public function test_hex_to_rgb_eight_digit_ignores_alpha(): void {
		// #FFFFFF1F — alpha byte ignored, opaque white returned.
		$this->assertSame( array( 255, 255, 255 ), EMCP_Tools_Color_Contrast::hex_to_rgb( '#FFFFFF1F' ) );
	}

	public function test_hex_to_rgb_invalid_returns_null(): void {
		$this->assertNull( EMCP_Tools_Color_Contrast::hex_to_rgb( 'not-a-color' ) );
		$this->assertNull( EMCP_Tools_Color_Contrast::hex_to_rgb( '#GGG' ) );
		$this->assertNull( EMCP_Tools_Color_Contrast::hex_to_rgb( '#FFFF' ) ); // 4 hex digits not allowed
	}

	// ---- relative_luminance -------------------------------------------------

	public function test_relative_luminance_bounds(): void {
		$this->assertEqualsWithDelta( 1.0, EMCP_Tools_Color_Contrast::relative_luminance( array( 255, 255, 255 ) ), 0.0001 );
		$this->assertEqualsWithDelta( 0.0, EMCP_Tools_Color_Contrast::relative_luminance( array( 0, 0, 0 ) ), 0.0001 );
	}

	// ---- contrast_ratio -----------------------------------------------------

	public function test_contrast_ratio_black_white_is_21(): void {
		$this->assertEqualsWithDelta( 21.0, EMCP_Tools_Color_Contrast::contrast_ratio( '#000000', '#FFFFFF' ), 0.01 );
		// Order-independent.
		$this->assertEqualsWithDelta( 21.0, EMCP_Tools_Color_Contrast::contrast_ratio( '#FFFFFF', '#000000' ), 0.01 );
	}

	public function test_contrast_ratio_identical_is_one(): void {
		$this->assertEqualsWithDelta( 1.0, EMCP_Tools_Color_Contrast::contrast_ratio( '#6366F1', '#6366F1' ), 0.0001 );
	}

	public function test_contrast_ratio_invalid_returns_null(): void {
		$this->assertNull( EMCP_Tools_Color_Contrast::contrast_ratio( '#FFFFFF', 'bogus' ) );
	}

	// ---- passes -------------------------------------------------------------

	public function test_passes_thresholds(): void {
		$this->assertTrue( EMCP_Tools_Color_Contrast::passes( 4.5 ) );          // AA normal boundary
		$this->assertFalse( EMCP_Tools_Color_Contrast::passes( 4.49 ) );
		$this->assertTrue( EMCP_Tools_Color_Contrast::passes( 3.0, true ) );    // AA large boundary
		$this->assertFalse( EMCP_Tools_Color_Contrast::passes( 2.99, true ) );
		$this->assertTrue( EMCP_Tools_Color_Contrast::passes( 7.0, false, 'AAA' ) );
		$this->assertFalse( EMCP_Tools_Color_Contrast::passes( 6.9, false, 'AAA' ) );
	}

	// ---- suggest_adjusted ---------------------------------------------------

	public function test_suggest_adjusted_returns_passing_color_on_light_bg(): void {
		// #999999 on white fails AA (~2.85:1). The suggestion should darken it.
		$start = EMCP_Tools_Color_Contrast::contrast_ratio( '#999999', '#FFFFFF' );
		$this->assertLessThan( 4.5, $start );

		$adjusted = EMCP_Tools_Color_Contrast::suggest_adjusted( '#999999', '#FFFFFF' );
		$this->assertNotNull( $adjusted );
		$this->assertGreaterThanOrEqual(
			4.5,
			EMCP_Tools_Color_Contrast::contrast_ratio( $adjusted, '#FFFFFF' )
		);
	}

	public function test_suggest_adjusted_returns_original_when_already_passing(): void {
		// Black on white already passes — returns the (normalized) original.
		$this->assertSame( '#000000', EMCP_Tools_Color_Contrast::suggest_adjusted( '#000000', '#FFFFFF' ) );
	}

	public function test_suggest_adjusted_lightens_on_dark_bg(): void {
		// Dark grey text on near-black bg fails; suggestion should lighten toward white.
		$adjusted = EMCP_Tools_Color_Contrast::suggest_adjusted( '#333333', '#111111' );
		$this->assertNotNull( $adjusted );
		$this->assertGreaterThanOrEqual(
			4.5,
			EMCP_Tools_Color_Contrast::contrast_ratio( $adjusted, '#111111' )
		);
	}
}

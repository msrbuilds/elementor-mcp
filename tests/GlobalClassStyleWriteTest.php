<?php
/**
 * Public unit tests for Global Class write validation (#125) and friendly
 * size parsing (#126). The write tools used to report success while Elementor
 * dropped unknown props, and `(float)` turned `100%` into `100px`.
 *
 * @package EMCP_Tools
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $thing Value to test.
	 * @return bool
	 */
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	/**
	 * Minimal WP_Error stand-in for the public test harness.
	 */
	class WP_Error {
		/**
		 * @var string
		 */
		private $code;

		/**
		 * @var string
		 */
		private $message;

		/**
		 * @param string $code    Error code.
		 * @param string $message Message.
		 * @param mixed  $data    Optional data.
		 */
		public function __construct( $code = '', $message = '', $data = '' ) {
			$this->code    = (string) $code;
			$this->message = (string) $message;
		}

		/**
		 * @return string
		 */
		public function get_error_code() {
			return $this->code;
		}

		/**
		 * @return string
		 */
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( '__' ) ) {
	/**
	 * @param string $text Text.
	 * @return string
	 */
	function __( $text ) {
		return $text;
	}
}

require_once dirname( __DIR__ ) . '/includes/class-atomic-props.php';
require_once dirname( __DIR__ ) . '/includes/class-atomic-styles.php';
require_once dirname( __DIR__ ) . '/includes/abilities/class-global-classes-write-abilities.php';

class GlobalClassStyleWriteTest extends \PHPUnit\Framework\TestCase {

	public function test_parses_percentage_string_as_percent_unit() {
		$parsed = EMCP_Tools_Atomic_Props::parse_size_input( '100%' );
		$this->assertSame( 100.0, $parsed['size'] );
		$this->assertSame( '%', $parsed['unit'] );
	}

	public function test_parses_bare_number_as_px() {
		$parsed = EMCP_Tools_Atomic_Props::parse_size_input( 16 );
		$this->assertSame( 16, $parsed['size'] );
		$this->assertSame( 'px', $parsed['unit'] );
	}

	public function test_parses_clamp_as_custom_unit() {
		$parsed = EMCP_Tools_Atomic_Props::parse_size_input( 'clamp(56px,8vw,120px)', 'custom' );
		$this->assertSame( 'clamp(56px,8vw,120px)', $parsed['size'] );
		$this->assertSame( 'custom', $parsed['unit'] );
	}

	public function test_detects_clamp_without_explicit_custom_unit() {
		$parsed = EMCP_Tools_Atomic_Props::parse_size_input( 'clamp(56px, 8vw, 120px)' );
		$this->assertSame( 'custom', $parsed['unit'] );
	}

	public function test_rejects_unparseable_size() {
		$this->assertNull( EMCP_Tools_Atomic_Props::parse_size_input( 'auto' ) );
	}

	public function test_friendly_width_percent_is_not_forced_to_px() {
		$props = EMCP_Tools_Atomic_Styles::build_common_props( array( 'width' => '100%' ) );
		$this->assertFalse( is_wp_error( $props ) );
		$this->assertSame( '%', $props['width']['value']['unit'] );
		$this->assertSame( 100.0, $props['width']['value']['size'] );
	}

	public function test_friendly_custom_padding_keeps_clamp() {
		$props = EMCP_Tools_Atomic_Styles::build_common_props(
			array(
				'padding_top'      => 'clamp(56px,8vw,120px)',
				'padding_top_unit' => 'custom',
			)
		);
		$this->assertFalse( is_wp_error( $props ) );
		$this->assertSame( 'custom', $props['padding']['value']['block-start']['value']['unit'] );
		$this->assertSame( 'clamp(56px,8vw,120px)', $props['padding']['value']['block-start']['value']['size'] );
	}

	public function test_rejects_longhand_padding_and_whitespace() {
		$writer   = new EMCP_Tools_Global_Classes_Write_Abilities();
		$rejected = $writer->rejected_style_props(
			array(
				'color'        => array( '$$type' => 'color', 'value' => 'red' ),
				'padding-top'  => array( '$$type' => 'size', 'value' => array( 'size' => 5, 'unit' => 'px' ) ),
				'white-space'  => array( '$$type' => 'string', 'value' => 'nowrap' ),
			)
		);
		$this->assertArrayHasKey( 'padding-top', $rejected );
		$this->assertArrayHasKey( 'white-space', $rejected );
		$this->assertArrayNotHasKey( 'color', $rejected );
		$this->assertStringContainsString( 'padding', $rejected['padding-top'] );
	}
}

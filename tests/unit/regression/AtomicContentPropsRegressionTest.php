<?php
/**
 * Regression — atomic content props use the core-correct shapes.
 *
 * Locks the contract the convenience closures (add-atomic-heading / -paragraph /
 * -button / -svg) must satisfy. Bugs this guards against:
 *  - title/text wrapped with html() → core String_Prop_Type rejects it → blank text.
 *  - e-paragraph using the `text` key → core key is `paragraph` → default placeholder.
 *  - e-svg using ::image() (id+url) → Image_Src_Prop_Type accepts ONE key → placeholder.
 *
 * The convenience closures aren't directly invokable in unit tests
 * (wp_register_ability is a no-op stub), so these assert the exact settings shapes
 * the closures build, round-tripped through the element factory.
 *
 * @group unit
 * @group atomic
 * @group regression
 * @package Elementor_MCP\Tests
 */

namespace Elementor_MCP\Tests;

use PHPUnit\Framework\TestCase;

class AtomicContentPropsRegressionTest extends TestCase {

	private function factory(): \Elementor_MCP_Element_Factory {
		return new \Elementor_MCP_Element_Factory();
	}

	public function test_heading_title_is_string_prop_not_html(): void {
		$el = $this->factory()->create_atomic_widget( 'e-heading', array(
			'title' => \Elementor_MCP_Atomic_Props::string( 'Hello' ),
			'tag'   => \Elementor_MCP_Atomic_Props::string( 'h2' ),
		) );
		$this->assertSame( 'string', $el['settings']['title']['$$type'], 'e-heading title must be a String_Prop_Type' );
		$this->assertSame( 'Hello', $el['settings']['title']['value'] );
	}

	public function test_html_helper_is_not_string_typed(): void {
		// Documents WHY title/text must use string(): html() is a different $$type
		// that the core String_Prop_Type schema rejects (→ blank render).
		$html = \Elementor_MCP_Atomic_Props::html( 'Hello' );
		$this->assertNotSame( 'string', $html['$$type'], 'html() is not string-typed; do not use it for String props' );
	}

	public function test_paragraph_uses_paragraph_key_not_text(): void {
		$el = $this->factory()->create_atomic_widget( 'e-paragraph', array(
			'paragraph' => \Elementor_MCP_Atomic_Props::string( 'Body' ),
		) );
		$this->assertArrayHasKey( 'paragraph', $el['settings'], 'core e-paragraph key is `paragraph`' );
		$this->assertArrayNotHasKey( 'text', $el['settings'], '`text` is the wrong key → default placeholder' );
		$this->assertSame( 'string', $el['settings']['paragraph']['$$type'] );
	}

	public function test_button_text_is_string_prop(): void {
		$el = $this->factory()->create_atomic_widget( 'e-button', array(
			'text' => \Elementor_MCP_Atomic_Props::string( 'Click' ),
		) );
		$this->assertSame( 'string', $el['settings']['text']['$$type'] );
		$this->assertSame( 'Click', $el['settings']['text']['value'] );
	}

	public function test_youtube_uses_source_key_not_url(): void {
		// #56: the e-youtube video prop is `source` (a String prop), not `url`.
		// add-atomic-youtube previously wrote `url` → core ignored it → no video.
		$el = $this->factory()->create_atomic_widget( 'e-youtube', array(
			'source' => \Elementor_MCP_Atomic_Props::string( 'https://youtu.be/abc123' ),
		) );
		$this->assertArrayHasKey( 'source', $el['settings'], 'core e-youtube key is `source`' );
		$this->assertArrayNotHasKey( 'url', $el['settings'], '`url` is the wrong key → no video' );
		$this->assertSame( 'string', $el['settings']['source']['$$type'], 'e-youtube source is a String_Prop_Type' );
		$this->assertSame( 'https://youtu.be/abc123', $el['settings']['source']['value'] );
	}

	public function test_svg_uses_image_src_with_single_url_key(): void {
		$svg = array(
			'$$type' => 'image-src',
			'value'  => array( 'url' => array( '$$type' => 'url', 'value' => 'https://x/test.svg' ) ),
		);
		$el = $this->factory()->create_atomic_widget( 'e-svg', array( 'svg' => $svg ) );
		$this->assertSame( 'image-src', $el['settings']['svg']['$$type'], 'e-svg uses Image_Src_Prop_Type' );
		$this->assertSame( array( 'url' ), array_keys( $el['settings']['svg']['value'] ), 'Image_Src validates to a SINGLE key' );
		$this->assertSame( 'https://x/test.svg', $el['settings']['svg']['value']['url']['value'] );
	}
}

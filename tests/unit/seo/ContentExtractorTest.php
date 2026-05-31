<?php
/**
 * Unit tests for Elementor_MCP_Content_Extractor.
 *
 * @group seo
 * @group a11y
 * @package Elementor_MCP\Tests\Seo
 */

namespace Elementor_MCP\Tests\Seo;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;
use Elementor_MCP_Content_Extractor;

class ContentExtractorTest extends Ability_Test_Case {

	/**
	 * A representative page tree exercising every extractor branch.
	 *
	 * @return array
	 */
	private function fixture_tree(): array {
		return array(
			array(
				'id'         => 'cont1',
				'elType'     => 'container',
				'widgetType' => null,
				'settings'   => array( 'background_color' => '#FFFFFF' ),
				'elements'   => array(
					array(
						'id'         => 'h1id',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array(
							'title'       => 'Welcome Home',
							'header_size' => 'h1',
							'title_color' => '#111111',
						),
					),
					array(
						'id'         => 'eyebrow',
						'elType'     => 'widget',
						'widgetType' => 'heading',
						'settings'   => array( 'title' => 'EYEBROW', 'header_size' => 'div' ),
					),
					array(
						'id'         => 'txt1',
						'elType'     => 'widget',
						'widgetType' => 'text-editor',
						'settings'   => array(
							'editor' => '<p>Hello there, visit our <a href="/about">about page</a> or an <a href="https://other.example/x">external site</a>.</p>',
						),
					),
					array(
						'id'         => 'imgnoalt',
						'elType'     => 'widget',
						'widgetType' => 'image',
						'settings'   => array( 'image' => array( 'id' => 42, 'url' => 'http://example.com/a.jpg' ) ),
					),
					array(
						'id'         => 'imgalt',
						'elType'     => 'widget',
						'widgetType' => 'image',
						'settings'   => array( 'image' => array( 'id' => 7, 'url' => 'http://example.com/b.jpg', 'alt' => 'A described image' ) ),
					),
					array(
						'id'         => 'btn1',
						'elType'     => 'widget',
						'widgetType' => 'button',
						'settings'   => array( 'text' => 'Contact us', 'link' => array( 'url' => 'https://example.com/contact' ) ),
					),
					array(
						'id'         => 'form1',
						'elType'     => 'widget',
						'widgetType' => 'form',
						'settings'   => array(
							'form_fields' => array(
								array( 'field_label' => 'Email', 'field_type' => 'email', '_id' => 'email' ),
								array( 'field_label' => '', 'field_type' => 'text', '_id' => 'name' ),
							),
						),
					),
					array(
						'id'         => 'html1',
						'elType'     => 'widget',
						'widgetType' => 'html',
						'settings'   => array(
							'html' => '<h2>Our Services</h2><img src="x.png" alt="Logo"><img src="y.png">',
						),
					),
					array(
						'id'         => 'atomich',
						'elType'     => 'widget',
						'widgetType' => 'e-heading',
						'settings'   => array(
							'title' => array( '$$type' => 'string', 'value' => 'Atomic Title' ),
							'tag'   => array( '$$type' => 'string', 'value' => 'h3' ),
						),
					),
				),
			),
		);
	}

	public function test_headings_levels_and_text(): void {
		$r = Elementor_MCP_Content_Extractor::extract( $this->fixture_tree(), 'example.com' );

		$by_text = array();
		foreach ( $r['headings'] as $h ) {
			$by_text[ $h['text'] ] = $h['level'];
		}

		$this->assertSame( 1, $by_text['Welcome Home'] ?? null );
		$this->assertSame( 2, $by_text['Our Services'] ?? null );   // from HTML widget markup
		$this->assertSame( 3, $by_text['Atomic Title'] ?? null );   // atomic e-heading
		// header_size "div" is NOT a heading.
		$this->assertArrayNotHasKey( 'EYEBROW', $by_text );
	}

	public function test_eyebrow_div_becomes_text_block(): void {
		$r     = Elementor_MCP_Content_Extractor::extract( $this->fixture_tree() );
		$texts = array_column( $r['text_blocks'], 'text' );
		$this->assertContains( 'EYEBROW', $texts );
	}

	public function test_images_and_alt_resolution(): void {
		$r = Elementor_MCP_Content_Extractor::extract( $this->fixture_tree() );

		$by_id = array();
		foreach ( $r['images'] as $img ) {
			$by_id[ $img['element_id'] ][] = $img;
		}

		// Widget-level alt override is captured.
		$this->assertSame( 'A described image', $by_id['imgalt'][0]['alt'] );
		// No alt + stubbed get_post_meta('') → empty alt (a fail the audit will flag).
		$this->assertSame( '', $by_id['imgnoalt'][0]['alt'] );
		// HTML widget: one img with alt, one without.
		$html_alts = array_column( $by_id['html1'], 'alt' );
		$this->assertContains( 'Logo', $html_alts );
		$this->assertContains( '', $html_alts );
	}

	public function test_links_internal_vs_external(): void {
		$r = Elementor_MCP_Content_Extractor::extract( $this->fixture_tree(), 'example.com' );

		$by_url = array();
		foreach ( $r['links'] as $l ) {
			$by_url[ $l['url'] ] = $l['internal'];
		}

		$this->assertTrue( $by_url['/about'] ?? null );                       // root-relative → internal
		$this->assertFalse( $by_url['https://other.example/x'] ?? null );      // different host → external
		$this->assertTrue( $by_url['https://example.com/contact'] ?? null );   // same host → internal
	}

	public function test_form_fields(): void {
		$r = Elementor_MCP_Content_Extractor::extract( $this->fixture_tree() );
		$this->assertCount( 2, $r['form_fields'] );
		$labels = array_column( $r['form_fields'], 'label' );
		$this->assertContains( 'Email', $labels );
		$this->assertContains( '', $labels ); // unlabeled field → a11y audit will flag
	}

	public function test_word_count_positive(): void {
		$r = Elementor_MCP_Content_Extractor::extract( $this->fixture_tree() );
		$this->assertGreaterThan( 5, $r['word_count'] );
	}

	public function test_text_style_context_resolves_ancestor_background(): void {
		$r = Elementor_MCP_Content_Extractor::extract( $this->fixture_tree() );

		$ctx = null;
		foreach ( $r['text_style_contexts'] as $c ) {
			if ( 'h1id' === $c['element_id'] ) {
				$ctx = $c;
				break;
			}
		}
		$this->assertNotNull( $ctx );
		$this->assertSame( '#111111', $ctx['color'] );
		$this->assertSame( '#FFFFFF', $ctx['background'] );      // inherited from container
		$this->assertSame( 'ancestor', $ctx['background_source'] );
	}

	public function test_empty_tree_is_safe(): void {
		$r = Elementor_MCP_Content_Extractor::extract( array() );
		$this->assertSame( 0, $r['word_count'] );
		$this->assertSame( array(), $r['headings'] );
		$this->assertSame( array(), $r['images'] );
	}
}

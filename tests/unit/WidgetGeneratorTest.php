<?php
/**
 * Unit tests for Elementor_MCP_Widget_Generator.
 *
 * Covers spec validation (rejections + acceptance), that generated code is
 * parseable PHP, and that the template compiler escapes each control type
 * correctly. The generator is the only component that emits PHP, so its
 * correctness is the security boundary for the widget builder.
 *
 * @package Elementor_MCP\Tests
 * @since   1.9.0
 */

namespace Elementor_MCP\Tests;

use PHPUnit\Framework\TestCase;

/**
 * @covers \Elementor_MCP_Widget_Generator
 */
final class WidgetGeneratorTest extends TestCase {

	public static function setUpBeforeClass(): void {
		require_once ELEMENTOR_MCP_DIR . 'includes/class-widget-generator.php';
	}

	/**
	 * A minimal valid spec used across tests.
	 *
	 * @return array
	 */
	private function valid_spec(): array {
		return array(
			'meta'          => array( 'title' => 'Pricing Card', 'icon' => 'eicon-price-table' ),
			'sections'      => array(
				array(
					'id'       => 'content',
					'label'    => 'Content',
					'tab'      => 'content',
					'controls' => array(
						array( 'name' => 'heading', 'type' => 'text', 'label' => 'Heading', 'default' => 'Plan' ),
						array( 'name' => 'desc', 'type' => 'wysiwyg', 'label' => 'Description' ),
						array( 'name' => 'cta', 'type' => 'url', 'label' => 'Button' ),
						array( 'name' => 'pic', 'type' => 'image', 'label' => 'Image' ),
						array( 'name' => 'ic', 'type' => 'icon', 'label' => 'Icon' ),
						array( 'name' => 'show', 'type' => 'switcher', 'label' => 'Show' ),
						array(
							'name'   => 'features',
							'type'   => 'repeater',
							'label'  => 'Features',
							'fields' => array(
								array( 'name' => 'feature', 'type' => 'text', 'label' => 'Feature' ),
							),
						),
					),
				),
				array(
					'id'       => 'style',
					'label'    => 'Style',
					'tab'      => 'style',
					'controls' => array(
						array( 'name' => 'accent', 'type' => 'color', 'label' => 'Accent' ),
					),
				),
			),
			'html_template' => '<div class="card" style="color: {{accent}}"><h3>{{heading}}</h3>'
				. '{{#if show}}<span>New</span>{{/if}}<div>{{desc}}</div>{{ic}}'
				. '<img src="{{pic}}"><a href="{{cta}}">Go</a>'
				. '<ul>{{#each features}}<li>{{feature}}</li>{{/each}}</ul></div>',
		);
	}

	public function test_control_types_exposes_core_set(): void {
		$types = \Elementor_MCP_Widget_Generator::control_types();
		foreach ( array( 'text', 'textarea', 'wysiwyg', 'number', 'url', 'select', 'switcher', 'color', 'media', 'icon', 'repeater' ) as $t ) {
			$this->assertArrayHasKey( $t, $types, "missing control type $t" );
		}
	}

	public function test_validate_rejects_missing_title(): void {
		$spec = $this->valid_spec();
		unset( $spec['meta']['title'] );
		$this->assertWpError( \Elementor_MCP_Widget_Generator::validate_spec( $spec ) );
	}

	public function test_validate_rejects_no_sections(): void {
		$this->assertWpError( \Elementor_MCP_Widget_Generator::validate_spec( array( 'meta' => array( 'title' => 'X' ), 'sections' => array(), 'html_template' => '<p>x</p>' ) ) );
	}

	public function test_validate_rejects_unknown_control_type(): void {
		$spec = $this->valid_spec();
		$spec['sections'][0]['controls'][0]['type'] = 'banana';
		$this->assertWpError( \Elementor_MCP_Widget_Generator::validate_spec( $spec ) );
	}

	public function test_validate_rejects_select_without_options(): void {
		$spec = $this->valid_spec();
		$spec['sections'][0]['controls'][] = array( 'name' => 'pick', 'type' => 'select', 'label' => 'Pick' );
		$this->assertWpError( \Elementor_MCP_Widget_Generator::validate_spec( $spec ) );
	}

	public function test_validate_rejects_missing_template(): void {
		$spec = $this->valid_spec();
		unset( $spec['html_template'] );
		$this->assertWpError( \Elementor_MCP_Widget_Generator::validate_spec( $spec ) );
	}

	public function test_validate_rejects_duplicate_control_names(): void {
		$spec = $this->valid_spec();
		$spec['sections'][1]['controls'][] = array( 'name' => 'heading', 'type' => 'text', 'label' => 'Dup' );
		$this->assertWpError( \Elementor_MCP_Widget_Generator::validate_spec( $spec ) );
	}

	public function test_validate_rejects_nested_repeater(): void {
		$spec = $this->valid_spec();
		$spec['sections'][0]['controls'][6]['fields'][] = array(
			'name'   => 'inner',
			'type'   => 'repeater',
			'label'  => 'Inner',
			'fields' => array( array( 'name' => 'x', 'type' => 'text', 'label' => 'X' ) ),
		);
		$this->assertWpError( \Elementor_MCP_Widget_Generator::validate_spec( $spec ) );
	}

	public function test_validate_accepts_valid_spec(): void {
		$this->assertTrue( \Elementor_MCP_Widget_Generator::validate_spec( $this->valid_spec() ) );
	}

	public function test_generate_produces_parseable_widget_class(): void {
		$php = \Elementor_MCP_Widget_Generator::generate( $this->valid_spec(), 'EMCP_Widget_99', 'emcp_custom_99' );
		$this->assertIsString( $php, is_object( $php ) ? 'generate returned WP_Error' : '' );
		$this->assertStringContainsString( 'class EMCP_Widget_99 extends \Elementor\Widget_Base', $php );
		$this->assertStringContainsString( "return 'emcp_custom_99';", $php );

		// Must parse as real PHP.
		$threw = false;
		try {
			token_get_all( $php, TOKEN_PARSE );
		} catch ( \ParseError $e ) {
			$threw = true;
		}
		$this->assertFalse( $threw, 'generated PHP failed TOKEN_PARSE' );
	}

	public function test_render_escapes_by_control_type(): void {
		$php = \Elementor_MCP_Widget_Generator::generate( $this->valid_spec(), 'EMCP_Widget_1', 'emcp_custom_1' );
		$this->assertIsString( $php );

		// text in HTML content → esc_html
		$this->assertStringContainsString( "esc_html( \$settings['heading'] ?? '' )", $php );
		// color inside style="…" attribute → esc_attr
		$this->assertStringContainsString( "esc_attr( \$settings['accent'] ?? '' )", $php );
		// wysiwyg → wp_kses_post
		$this->assertStringContainsString( "wp_kses_post( \$settings['desc'] ?? '' )", $php );
		// url + media → esc_url on the ['url'] subkey
		$this->assertStringContainsString( "esc_url( \$settings['cta']['url'] ?? '' )", $php );
		$this->assertStringContainsString( "esc_url( \$settings['pic']['url'] ?? '' )", $php );
		// icon → render_icon
		$this->assertStringContainsString( 'Icons_Manager::render_icon( $settings[\'ic\']', $php );
		// switcher conditional
		$this->assertStringContainsString( "'yes' === \$settings['show']", $php );
		// repeater loop + field access on the row var
		$this->assertStringContainsString( "foreach ( \$settings['features'] as \$emcp_item )", $php );
		$this->assertStringContainsString( "esc_html( \$emcp_item['feature'] ?? '' )", $php );
	}

	public function test_slider_default_is_an_array_not_a_scalar(): void {
		// Regression: a scalar slider default (e.g. '10') makes Elementor
		// array_merge() its array default with a string and fatal, crashing the
		// whole editor panel. Slider defaults must be {size, unit} arrays.
		$spec = array(
			'meta'          => array( 'title' => 'Gap' ),
			'sections'      => array(
				array(
					'id'       => 'style',
					'tab'      => 'style',
					'controls' => array(
						array( 'name' => 'gap', 'type' => 'slider', 'label' => 'Gap', 'default' => 10, 'min' => 0, 'max' => 80, 'step' => 1 ),
					),
				),
			),
			'html_template' => '<div>x</div>',
		);
		$php = \Elementor_MCP_Widget_Generator::generate( $spec, 'EMCP_Widget_3', 'emcp_custom_3' );
		$this->assertIsString( $php );
		$this->assertStringContainsString( "'default' => array( 'size' => 10, 'unit' => 'px' )", $php );
		$this->assertStringContainsString( "'range' => array( 'px' => array( 'min' => 0, 'max' => 80, 'step' => 1 ) )", $php );
		$this->assertStringContainsString( "'size_units' => array( 'px' )", $php );
		// Must NOT emit a scalar slider default.
		$this->assertStringNotContainsString( "'default' => '10'", $php );
	}

	public function test_choose_options_render_with_icons(): void {
		// A choose control renders icon buttons; each option must carry an icon
		// or the buttons render empty. Spec icons win; alignment values get
		// sensible eicon defaults.
		$spec = array(
			'meta'          => array( 'title' => 'Align' ),
			'sections'      => array(
				array(
					'id'       => 'content',
					'tab'      => 'content',
					'controls' => array(
						array(
							'name'    => 'align',
							'type'    => 'choose',
							'label'   => 'Alignment',
							'options' => array(
								array( 'value' => 'flex-start', 'label' => 'Left' ),
								array( 'value' => 'center', 'label' => 'Center', 'icon' => 'eicon-h-align-center' ),
								array( 'value' => 'flex-end', 'label' => 'Right' ),
							),
						),
					),
				),
			),
			'html_template' => '<div>x</div>',
		);
		$php = \Elementor_MCP_Widget_Generator::generate( $spec, 'EMCP_Widget_4', 'emcp_custom_4' );
		$this->assertIsString( $php );
		// Default heuristic eicons for alignment values.
		$this->assertStringContainsString( "'flex-start' => array( 'title' => 'Left', 'icon' => 'eicon-text-align-left' )", $php );
		$this->assertStringContainsString( "'flex-end' => array( 'title' => 'Right', 'icon' => 'eicon-text-align-right' )", $php );
		// A spec-supplied icon is honored.
		$this->assertStringContainsString( "'center' => array( 'title' => 'Center', 'icon' => 'eicon-h-align-center' )", $php );
		$this->assertStringContainsString( "'toggle' => true", $php );
	}

	public function test_asset_depends_emitted_only_when_handles_passed(): void {
		$spec = $this->valid_spec();

		// No handles → no depends methods.
		$plain = \Elementor_MCP_Widget_Generator::generate( $spec, 'EMCP_Widget_5', 'emcp_custom_5' );
		$this->assertIsString( $plain );
		$this->assertStringNotContainsString( 'get_style_depends', $plain );
		$this->assertStringNotContainsString( 'get_script_depends', $plain );

		// Handles passed → depends methods returning those handles.
		$withAssets = \Elementor_MCP_Widget_Generator::generate(
			$spec,
			'EMCP_Widget_6',
			'emcp_custom_6',
			array( 'style_handle' => 'emcp-widget-6-style', 'script_handle' => 'emcp-widget-6-script' )
		);
		$this->assertIsString( $withAssets );
		$this->assertStringContainsString( "public function get_style_depends() { return array( 'emcp-widget-6-style' ); }", $withAssets );
		$this->assertStringContainsString( "public function get_script_depends() { return array( 'emcp-widget-6-script' ); }", $withAssets );
	}

	public function test_group_controls_emit_scoped_add_group_control(): void {
		$spec = array(
			'meta'          => array( 'title' => 'G' ),
			'sections'      => array(
				array(
					'id'       => 's',
					'tab'      => 'style',
					'controls' => array(
						array( 'name' => 'typo', 'type' => 'typography', 'label' => 'Typo', 'selector' => '.x h3' ),
						array( 'name' => 'bg', 'type' => 'background', 'label' => 'BG', 'selector' => '.x', 'types' => array( 'classic', 'gradient' ) ),
					),
				),
			),
			'html_template' => '<div class="x"><h3>t</h3></div>',
		);
		$php = \Elementor_MCP_Widget_Generator::generate( $spec, 'EMCP_Widget_7', 'emcp_custom_7' );
		$this->assertIsString( $php );
		$this->assertStringContainsString( "add_group_control( \\Elementor\\Group_Control_Typography::get_type(), array( 'name' => 'typo', 'label' => 'Typo', 'selector' => '{{WRAPPER}} .x h3' )", $php );
		$this->assertStringContainsString( 'Group_Control_Background::get_type()', $php );
		$this->assertStringContainsString( "'types' => array( 'classic', 'gradient' )", $php );
	}

	public function test_group_control_requires_selector(): void {
		$spec = array(
			'meta'          => array( 'title' => 'G' ),
			'sections'      => array( array( 'id' => 's', 'tab' => 'style', 'controls' => array( array( 'name' => 'typo', 'type' => 'typography', 'label' => 'Typo' ) ) ) ),
			'html_template' => '<div>x</div>',
		);
		$this->assertWpError( \Elementor_MCP_Widget_Generator::validate_spec( $spec ) );
	}

	public function test_condition_and_select2_multiple(): void {
		$spec = array(
			'meta'          => array( 'title' => 'C' ),
			'sections'      => array(
				array(
					'id'       => 's',
					'tab'      => 'content',
					'controls' => array(
						array( 'name' => 'show', 'type' => 'switcher', 'label' => 'Show' ),
						array( 'name' => 'tags', 'type' => 'select2', 'label' => 'Tags', 'multiple' => true, 'options' => array( array( 'value' => 'a', 'label' => 'A' ) ), 'condition' => array( 'show' => 'yes' ) ),
					),
				),
			),
			'html_template' => '<div>x</div>',
		);
		$php = \Elementor_MCP_Widget_Generator::generate( $spec, 'EMCP_Widget_8', 'emcp_custom_8' );
		$this->assertIsString( $php );
		$this->assertStringContainsString( "'multiple' => true", $php );
		$this->assertStringContainsString( "'condition' => array( 'show' => 'yes' )", $php );
	}

	public function test_gallery_loops_with_each_and_escapes_url(): void {
		$spec = array(
			'meta'          => array( 'title' => 'Gal' ),
			'sections'      => array( array( 'id' => 's', 'tab' => 'content', 'controls' => array( array( 'name' => 'imgs', 'type' => 'gallery', 'label' => 'Images' ) ) ) ),
			'html_template' => '<div>{{#each imgs}}<img src="{{url}}" data-id="{{id}}">{{/each}}</div>',
		);
		$php = \Elementor_MCP_Widget_Generator::generate( $spec, 'EMCP_Widget_9', 'emcp_custom_9' );
		$this->assertIsString( $php );
		$this->assertStringContainsString( "foreach ( \$settings['imgs'] as \$emcp_item )", $php );
		$this->assertStringContainsString( "esc_url( \$emcp_item['url'] ?? '' )", $php );
	}

	public function test_generate_rejects_invalid_icon_to_safe_default(): void {
		$spec               = $this->valid_spec();
		$spec['meta']['icon'] = 'javascript:alert(1)';
		$php                = \Elementor_MCP_Widget_Generator::generate( $spec, 'EMCP_Widget_2', 'emcp_custom_2' );
		$this->assertIsString( $php );
		$this->assertStringContainsString( "return 'eicon-code';", $php );
	}

	/**
	 * Asserts a value is a WP_Error.
	 *
	 * @param mixed $thing The value.
	 */
	private function assertWpError( $thing ): void {
		$this->assertTrue( is_wp_error( $thing ), 'expected WP_Error, got ' . gettype( $thing ) );
	}
}

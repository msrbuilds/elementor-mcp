<?php
/**
 * Unit tests for the A11y toolkit pure analysis helper.
 *
 * @group a11y
 * @package Elementor_MCP\Tests\Seo
 */

namespace Elementor_MCP\Tests\Seo;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;
use Elementor_MCP_A11y_Abilities;

class A11yToolkitTest extends Ability_Test_Case {

	/** Base extracted fixture with everything passing. */
	private function clean_extract(): array {
		return array(
			'headings'            => array(
				array( 'level' => 1, 'text' => 'Welcome', 'element_id' => 'a' ),
				array( 'level' => 2, 'text' => 'About', 'element_id' => 'b' ),
			),
			'text_blocks'         => array(),
			'images'              => array(
				array( 'element_id' => 'i1', 'attachment_id' => 1, 'url' => 'x.jpg', 'alt' => 'Described' ),
			),
			'links'               => array(
				array( 'url' => '/about', 'text' => 'About our practice', 'internal' => true, 'element_id' => 'b' ),
			),
			'form_fields'         => array(
				array( 'label' => 'Email', 'type' => 'email', 'element_id' => 'f' ),
			),
			'text_style_contexts' => array(
				array( 'element_id' => 'a', 'color' => '#111111', 'background' => '#FFFFFF', 'background_source' => 'element' ),
			),
		);
	}

	public function test_clean_page_passes_everything(): void {
		$report = Elementor_MCP_A11y_Abilities::build_a11y_report( $this->clean_extract() );
		$status = array_column( $report['checks'], 'status', 'id' );

		$this->assertSame( 'pass', $status['color_contrast'] );
		$this->assertSame( 'pass', $status['image_alts'] );
		$this->assertSame( 'pass', $status['heading_hierarchy'] );
		$this->assertSame( 'pass', $status['link_text_quality'] );
		$this->assertSame( 'pass', $status['form_label_coverage'] );
		$this->assertSame( 100, $report['score'] );
		$this->assertSame( 0, $report['summary']['failures'] );
	}

	public function test_low_contrast_pair_fails(): void {
		$ex = $this->clean_extract();
		$ex['text_style_contexts'] = array(
			array( 'element_id' => 'a', 'color' => '#999999', 'background' => '#FFFFFF', 'background_source' => 'element' ), // ~2.85:1
		);
		$report = Elementor_MCP_A11y_Abilities::build_a11y_report( $ex );
		$status = array_column( $report['checks'], 'status', 'id' );
		$this->assertSame( 'fail', $status['color_contrast'] );
	}

	public function test_unresolved_background_is_inconclusive_not_failure(): void {
		$ex = $this->clean_extract();
		$ex['text_style_contexts'] = array(
			array( 'element_id' => 'a', 'color' => '#777777', 'background' => null, 'background_source' => 'none' ),
		);
		$report = Elementor_MCP_A11y_Abilities::build_a11y_report( $ex );
		$status = array_column( $report['checks'], 'status', 'id' );

		$this->assertSame( 'inconclusive', $status['color_contrast'] );
		// Inconclusive is excluded from scoring → the other (passing) checks keep the score at 100.
		$this->assertSame( 100, $report['score'] );
		$this->assertSame( 1, $report['summary']['inconclusive'] );
	}

	public function test_missing_alt_fails(): void {
		$ex             = $this->clean_extract();
		$ex['images'][] = array( 'element_id' => 'i2', 'attachment_id' => 2, 'url' => 'y.jpg', 'alt' => '' );
		$report         = Elementor_MCP_A11y_Abilities::build_a11y_report( $ex );
		$status         = array_column( $report['checks'], 'status', 'id' );
		$this->assertSame( 'fail', $status['image_alts'] );
	}

	public function test_generic_and_empty_link_text_warns(): void {
		$ex          = $this->clean_extract();
		$ex['links'] = array(
			array( 'url' => '/a', 'text' => 'click here', 'internal' => true, 'element_id' => 'x' ),
			array( 'url' => '/b', 'text' => '', 'internal' => true, 'element_id' => 'y' ),
		);
		$report = Elementor_MCP_A11y_Abilities::build_a11y_report( $ex );
		$status = array_column( $report['checks'], 'status', 'id' );
		$this->assertSame( 'warn', $status['link_text_quality'] );
	}

	public function test_unlabeled_form_field_fails(): void {
		$ex                = $this->clean_extract();
		$ex['form_fields'] = array(
			array( 'label' => 'Email', 'type' => 'email', 'element_id' => 'f' ),
			array( 'label' => '', 'type' => 'text', 'element_id' => 'g' ),
		);
		$report = Elementor_MCP_A11y_Abilities::build_a11y_report( $ex );
		$status = array_column( $report['checks'], 'status', 'id' );
		$this->assertSame( 'fail', $status['form_label_coverage'] );
	}

	public function test_skipped_heading_warns(): void {
		$ex             = $this->clean_extract();
		$ex['headings'] = array(
			array( 'level' => 1, 'text' => 'Title', 'element_id' => 'a' ),
			array( 'level' => 4, 'text' => 'Jumped', 'element_id' => 'b' ),
		);
		$report = Elementor_MCP_A11y_Abilities::build_a11y_report( $ex );
		$status = array_column( $report['checks'], 'status', 'id' );
		$this->assertSame( 'warn', $status['heading_hierarchy'] );
	}

	public function test_form_check_absent_when_no_form(): void {
		$ex                = $this->clean_extract();
		$ex['form_fields'] = array();
		$report            = Elementor_MCP_A11y_Abilities::build_a11y_report( $ex );
		$ids               = array_column( $report['checks'], 'id' );
		$this->assertNotContains( 'form_label_coverage', $ids );
	}

	// ---- propose_contrast_fixes ---------------------------------------------

	public function test_propose_contrast_fixes_for_failing_pair(): void {
		$ex = array(
			'text_style_contexts' => array(
				array( 'element_id' => 'a', 'color' => '#999999', 'color_key' => 'title_color', 'background' => '#FFFFFF', 'background_source' => 'element' ),
			),
		);
		$fixes = Elementor_MCP_A11y_Abilities::propose_contrast_fixes( $ex, null );
		$this->assertCount( 1, $fixes );
		$this->assertSame( 'a', $fixes[0]['element_id'] );
		$this->assertSame( 'title_color', $fixes[0]['color_key'] );
		$this->assertGreaterThanOrEqual( 4.5, $fixes[0]['new_ratio'] );
		$this->assertLessThan( 4.5, $fixes[0]['old_ratio'] );
	}

	public function test_propose_contrast_fixes_skips_passing_and_inconclusive(): void {
		$ex = array(
			'text_style_contexts' => array(
				array( 'element_id' => 'pass', 'color' => '#111111', 'color_key' => 'color', 'background' => '#FFFFFF', 'background_source' => 'element' ),
				array( 'element_id' => 'incon', 'color' => '#777777', 'color_key' => 'color', 'background' => null, 'background_source' => 'none' ),
			),
		);
		$this->assertSame( array(), Elementor_MCP_A11y_Abilities::propose_contrast_fixes( $ex, null ) );
	}

	public function test_propose_contrast_fixes_element_filter(): void {
		$ex = array(
			'text_style_contexts' => array(
				array( 'element_id' => 'a', 'color' => '#999999', 'color_key' => 'color', 'background' => '#FFFFFF', 'background_source' => 'element' ),
				array( 'element_id' => 'b', 'color' => '#888888', 'color_key' => 'color', 'background' => '#FFFFFF', 'background_source' => 'element' ),
			),
		);
		$fixes = Elementor_MCP_A11y_Abilities::propose_contrast_fixes( $ex, 'b' );
		$this->assertCount( 1, $fixes );
		$this->assertSame( 'b', $fixes[0]['element_id'] );
	}

	// ---- alt_from_filename / propose_alt_texts ------------------------------

	public function test_alt_from_filename_descriptive(): void {
		$this->assertSame( 'Summer sale banner', Elementor_MCP_A11y_Abilities::alt_from_filename( 'https://x.com/wp-content/uploads/summer-sale-banner-1024x768.jpg' ) );
		$this->assertSame( 'Our team', Elementor_MCP_A11y_Abilities::alt_from_filename( '/uploads/our-team-photo.png' ) );
	}

	public function test_alt_from_filename_non_descriptive_returns_empty(): void {
		$this->assertSame( '', Elementor_MCP_A11y_Abilities::alt_from_filename( '/uploads/IMG_1234.jpg' ) );
		$this->assertSame( '', Elementor_MCP_A11y_Abilities::alt_from_filename( '/uploads/photo.jpg' ) );
		$this->assertSame( '', Elementor_MCP_A11y_Abilities::alt_from_filename( '/uploads/20230101.png' ) );
	}

	public function test_propose_alt_texts_sources_and_skips(): void {
		$ex = array(
			'images' => array(
				array( 'element_id' => 'has', 'attachment_id' => 1, 'url' => 'a.jpg', 'alt' => 'Already set', 'context_heading' => 'X' ),
				array( 'element_id' => 'fn', 'attachment_id' => 2, 'url' => '/up/red-tractor.jpg', 'alt' => '', 'context_heading' => 'Farm Equipment' ),
				array( 'element_id' => 'ctx', 'attachment_id' => 3, 'url' => '/up/IMG_9001.jpg', 'alt' => '', 'context_heading' => 'Our Story' ),
				array( 'element_id' => 'html', 'attachment_id' => 0, 'url' => '/up/DSC_1.jpg', 'alt' => '', 'context_heading' => '' ),
			),
		);
		$p     = Elementor_MCP_A11y_Abilities::propose_alt_texts( $ex, 'Acme Farms' );
		$by_id = array();
		foreach ( $p as $row ) {
			$by_id[ $row['element_id'] ] = $row;
		}

		$this->assertArrayNotHasKey( 'has', $by_id );                       // already has alt → skipped
		$this->assertSame( 'Red tractor', $by_id['fn']['proposed_alt'] );    // from filename
		$this->assertSame( 'filename', $by_id['fn']['source'] );
		$this->assertTrue( $by_id['fn']['writable'] );
		$this->assertSame( 'Our Story', $by_id['ctx']['proposed_alt'] );     // filename non-descriptive → heading
		$this->assertSame( 'heading', $by_id['ctx']['source'] );
		$this->assertSame( 'Acme Farms', $by_id['html']['proposed_alt'] );   // no filename/heading → page title
		$this->assertFalse( $by_id['html']['writable'] );                    // attachment_id 0 → not auto-writable
	}
}

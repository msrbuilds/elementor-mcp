<?php
/**
 * Unit tests for the SEO toolkit pure analysis helpers + Seo_Meta.
 *
 * @group seo
 * @package EMCP_Tools\Tests\Seo
 */

namespace EMCP_Tools\Tests\Seo;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;
use EMCP_Tools_Seo_Abilities;
use EMCP_Tools_Seo_Meta;

class SeoToolkitTest extends Ability_Test_Case {

	/** A clean, well-optimized extracted-content fixture. */
	private function good_extract(): array {
		$body = str_repeat( 'Quality dental care for the whole family in our modern clinic. ', 12 ); // ~300+ words
		return array(
			'headings'            => array(
				array( 'level' => 1, 'text' => 'Family Dentist in Springfield', 'element_id' => 'a' ),
				array( 'level' => 2, 'text' => 'Our Services', 'element_id' => 'b' ),
			),
			'text_blocks'         => array(
				array( 'text' => $body, 'element_id' => 'c' ),
			),
			'images'              => array(
				array( 'element_id' => 'd', 'attachment_id' => 1, 'url' => 'x.jpg', 'alt' => 'Smiling patient' ),
			),
			'links'               => array(
				array( 'url' => '/services', 'text' => 'Services', 'internal' => true, 'element_id' => 'b' ),
			),
			'form_fields'         => array(),
			'text_style_contexts' => array(),
			'word_count'          => 320,
		);
	}

	private function good_seo(): array {
		return array(
			'source'            => 'yoast',
			'title'             => 'Family Dentist in Springfield | Bright Smiles Dental',  // ~52 chars
			'description'       => 'Gentle, affordable family dentistry in Springfield. Same-day emergency visits, Invisalign, and friendly care for every age. Book your visit today.', // ~140
			'canonical'         => 'https://example.com/family-dentist/',
			'focus_keyword'     => 'family dentist',
			'title_is_template' => false,
		);
	}

	// ---- build_seo_report ---------------------------------------------------

	public function test_good_page_scores_high_and_passes_core_checks(): void {
		$report = EMCP_Tools_Seo_Abilities::build_seo_report( $this->good_extract(), $this->good_seo(), 'family dentist' );

		$this->assertGreaterThanOrEqual( 90, $report['score'] );
		$status = array_column( $report['checks'], 'status', 'id' );
		$this->assertSame( 'pass', $status['h1_present'] );
		$this->assertSame( 'pass', $status['image_alts'] );
		$this->assertSame( 'pass', $status['internal_links'] );
		$this->assertSame( 'pass', $status['word_count'] );
		$this->assertSame( 'pass', $status['keyword_usage'] );
		$this->assertSame( 0, $report['summary']['failures'] );
	}

	public function test_missing_h1_and_alts_are_flagged(): void {
		$ex = $this->good_extract();
		$ex['headings'] = array( array( 'level' => 2, 'text' => 'No H1 here', 'element_id' => 'b' ) ); // 0 H1
		$ex['images'][] = array( 'element_id' => 'e', 'attachment_id' => 2, 'url' => 'y.jpg', 'alt' => '' ); // missing alt

		$report = EMCP_Tools_Seo_Abilities::build_seo_report( $ex, $this->good_seo(), '' );
		$status = array_column( $report['checks'], 'status', 'id' );

		$this->assertSame( 'fail', $status['h1_present'] );
		$this->assertSame( 'fail', $status['image_alts'] );
		$this->assertGreaterThanOrEqual( 2, $report['summary']['failures'] );
	}

	public function test_skipped_heading_level_warns(): void {
		$ex             = $this->good_extract();
		$ex['headings'] = array(
			array( 'level' => 1, 'text' => 'Title', 'element_id' => 'a' ),
			array( 'level' => 3, 'text' => 'Jumped', 'element_id' => 'b' ), // H1 -> H3 skip
		);
		$report = EMCP_Tools_Seo_Abilities::build_seo_report( $ex, $this->good_seo(), '' );
		$status = array_column( $report['checks'], 'status', 'id' );
		$this->assertSame( 'warn', $status['heading_hierarchy'] );
	}

	public function test_empty_meta_description_fails(): void {
		$seo                = $this->good_seo();
		$seo['description'] = '';
		$report             = EMCP_Tools_Seo_Abilities::build_seo_report( $this->good_extract(), $seo, '' );
		$status             = array_column( $report['checks'], 'status', 'id' );
		$this->assertSame( 'fail', $status['meta_description'] );
	}

	// ---- rank_keywords ------------------------------------------------------

	public function test_rank_keywords_excludes_stopwords_and_counts(): void {
		$ex = array(
			'headings'    => array( array( 'level' => 1, 'text' => 'Coffee Coffee Coffee', 'element_id' => 'a' ) ),
			'text_blocks' => array( array( 'text' => 'The the the beans beans roast', 'element_id' => 'b' ) ),
		);
		$r     = EMCP_Tools_Seo_Abilities::rank_keywords( $ex, 10 );
		$terms = array_column( $r['keywords'], 'count', 'term' );

		$this->assertArrayHasKey( 'coffee', $terms );
		$this->assertSame( 3, $terms['coffee'] );
		$this->assertArrayNotHasKey( 'the', $terms ); // stop-word filtered
		$this->assertGreaterThan( 0, $r['total_words'] );
	}

	public function test_rank_keywords_surfaces_recurring_bigrams(): void {
		$ex = array(
			'headings'    => array(),
			'text_blocks' => array( array( 'text' => 'family dentist springfield family dentist springfield', 'element_id' => 'b' ) ),
		);
		$r       = EMCP_Tools_Seo_Abilities::rank_keywords( $ex, 10 );
		$bigrams = array_column( $r['bigrams'], 'count', 'term' );
		$this->assertArrayHasKey( 'family dentist', $bigrams );
		$this->assertGreaterThanOrEqual( 2, $bigrams['family dentist'] );
	}

	// ---- propose_meta -------------------------------------------------------

	public function test_propose_meta_builds_title_with_site_name_within_limit(): void {
		$r = EMCP_Tools_Seo_Abilities::propose_meta( $this->good_extract(), $this->good_seo(), '', 'Bright Smiles' );
		$this->assertStringContainsString( 'Family Dentist in Springfield', $r['proposed_title'] );
		$this->assertLessThanOrEqual( 60, $r['title_length'] );
		$this->assertLessThanOrEqual( 155, $r['description_length'] );
	}

	public function test_propose_meta_front_loads_target_keyword(): void {
		$ex = $this->good_extract();
		$ex['text_blocks'] = array( array( 'text' => 'We offer gentle care and modern equipment for every patient visiting us.', 'element_id' => 'c' ) );
		$r  = EMCP_Tools_Seo_Abilities::propose_meta( $ex, $this->good_seo(), 'Emergency Dentist', '' );
		$this->assertStringStartsWith( 'Emergency Dentist', $r['proposed_description'] );
	}

	// ---- build_jsonld -------------------------------------------------------

	public function test_jsonld_auto_with_business_is_localbusiness(): void {
		$r = EMCP_Tools_Seo_Abilities::build_jsonld(
			'auto',
			$this->good_extract(),
			$this->good_seo(),
			array( 'name' => 'Bright Smiles', 'phone' => '555-1234', 'locality' => 'Springfield' ),
			array(),
			'https://example.com/family-dentist/'
		);
		$this->assertSame( 'LocalBusiness', $r['detected_type'] );
		$decoded = json_decode( $r['jsonld'], true );
		$this->assertSame( 'LocalBusiness', $decoded['@type'] );
		$this->assertSame( '555-1234', $decoded['telephone'] );
		$this->assertSame( 'Springfield', $decoded['address']['addressLocality'] );
	}

	public function test_jsonld_auto_with_faqs_is_faqpage(): void {
		$r = EMCP_Tools_Seo_Abilities::build_jsonld(
			'auto',
			$this->good_extract(),
			$this->good_seo(),
			array(),
			array( array( 'question' => 'Do you take insurance?', 'answer' => 'Yes, most major plans.' ) ),
			''
		);
		$this->assertSame( 'FAQPage', $r['detected_type'] );
		$decoded = json_decode( $r['jsonld'], true );
		$this->assertSame( 'Question', $decoded['mainEntity'][0]['@type'] );
	}

	public function test_jsonld_auto_default_is_article(): void {
		$r       = EMCP_Tools_Seo_Abilities::build_jsonld( 'auto', $this->good_extract(), $this->good_seo(), array(), array(), 'https://example.com/x/' );
		$this->assertSame( 'Article', $r['detected_type'] );
		$decoded = json_decode( $r['jsonld'], true );
		$this->assertSame( 'Family Dentist in Springfield', $decoded['headline'] );
	}

	// ---- Seo_Meta -----------------------------------------------------------

	public function test_seo_meta_falls_back_to_core_in_test_env(): void {
		// No SEO plugin constants defined + stubbed get_post_meta('') → core source.
		$meta = EMCP_Tools_Seo_Meta::get( 123 );
		$this->assertSame( 'core', $meta['source'] );
		$this->assertSame( 'Test Post', $meta['title'] ); // from get_the_title stub
	}

	public function test_seo_meta_write_noops_without_seo_plugin(): void {
		// No Yoast/Rank Math constants or existing meta → nothing persisted.
		$r = EMCP_Tools_Seo_Meta::write( 123, 'A Title', 'A description.' );
		$this->assertFalse( $r['written'] );
		$this->assertSame( 'none', $r['source'] );
		$this->assertSame( array(), $r['fields'] );
		// And no meta-write was recorded by the stub.
		$keys = array_column( $GLOBALS['_wp_meta_calls'], 'meta_key' );
		$this->assertNotContains( '_yoast_wpseo_title', $keys );
		$this->assertNotContains( 'rank_math_title', $keys );
	}
}

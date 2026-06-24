<?php
/**
 * Widget catalog accessor tests.
 * @group widgets
 * @package EMCP_Tools\Tests\Widgets
 */
namespace EMCP_Tools\Tests\Widgets;

require_once dirname(__DIR__) . '/class-ability-test-case.php';
require_once dirname(__DIR__, 2) . '/../includes/widgets/class-widget-catalog.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class WidgetCatalogTest extends Ability_Test_Case {

	/** @test */
	public function test_get_returns_keyed_array(): void {
		$catalog = \EMCP_Tools_Widget_Catalog::get();
		$this->assertIsArray($catalog);
		$this->assertArrayHasKey('heading', $catalog, 'heading must be cataloged');
	}

	/** @test */
	public function test_get_widget_returns_entry_with_required_keys(): void {
		$heading = \EMCP_Tools_Widget_Catalog::get_widget('heading');
		$this->assertIsArray($heading);
		foreach (['tier', 'title', 'category', 'use_case', 'keywords', 'params'] as $key) {
			$this->assertArrayHasKey($key, $heading, "heading entry must have '$key'");
		}
	}

	/** @test */
	public function test_get_widget_unknown_returns_null(): void {
		$this->assertNull(\EMCP_Tools_Widget_Catalog::get_widget('no-such-widget'));
	}

	/** @test */
	public function test_by_tier_filters(): void {
		$free = \EMCP_Tools_Widget_Catalog::by_tier('free');
		$this->assertArrayHasKey('heading', $free);
		$this->assertArrayNotHasKey('form', $free, 'form is Pro, not free');
	}

	/** @test */
	public function test_is_pro(): void {
		$this->assertFalse(\EMCP_Tools_Widget_Catalog::is_pro('heading'));
		$this->assertTrue(\EMCP_Tools_Widget_Catalog::is_pro('form'));
	}

	/** @test */
	public function test_search_matches_use_case_and_keywords(): void {
		$hits = \EMCP_Tools_Widget_Catalog::search('headline');
		$this->assertArrayHasKey('heading', $hits, 'search "headline" should match heading via keywords/use_case');
	}
}

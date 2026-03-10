<?php
/**
 * T2 Capability tests — composite abilities (1 tool).
 * check_create_permission() → publish_pages OR edit_pages.
 * @group capabilities
 * @group composite
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class CompositeCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Composite_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data    = $this->createStub(\Elementor_MCP_Data::class);
        $factory = $this->make_factory();
        $this->ability = new \Elementor_MCP_Composite_Abilities($data, $factory);
    }

    // check_create_permission() — denied
    /** @test @group t2 */
    public function test_create_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_create_permission());
    }

    /** @test @group t2 */
    public function test_create_permission_denied_with_edit_posts_only(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_create_permission());
    }

    // check_create_permission() — accepted
    /** @test @group t2 */
    public function test_create_permission_accepted_with_publish_pages(): void {
        $this->allow_caps('publish_pages');
        $this->assertTrue($this->ability->check_create_permission());
    }

    /** @test @group t2 */
    public function test_create_permission_accepted_with_edit_pages(): void {
        $this->allow_caps('edit_pages');
        $this->assertTrue($this->ability->check_create_permission());
    }

    // T0.3 tool registry
    /** @test @group t0 */
    public function test_ability_names_contains_build_page_and_count_is_one(): void {
        $names = $this->ability->get_ability_names();
        $this->assertContains('elementor-mcp/build-page', $names, 'Missing tool: build-page');
        $this->assertCount(1, $names, 'Composite class must register exactly 1 tool.');
    }
}

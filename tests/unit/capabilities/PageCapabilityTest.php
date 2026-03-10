<?php
/**
 * T2 Capability tests — page abilities (5 tools).
 * check_create_permission() → publish_pages OR edit_pages.
 * check_edit_permission()   → edit_posts (+ edit_post when post_id given).
 * check_delete_permission() → edit_posts AND delete_posts (+ edit_post + delete_post when post_id given).
 * @group capabilities
 * @group page
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class PageCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Page_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data    = $this->createStub(\Elementor_MCP_Data::class);
        $factory = $this->make_factory();
        $this->ability = new \Elementor_MCP_Page_Abilities($data, $factory);
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

    // check_edit_permission() — denied
    /** @test @group t2 */
    public function test_edit_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_edit_permission());
    }

    /** @test @group t2 */
    public function test_edit_permission_denied_with_manage_options_only(): void {
        $this->allow_caps('manage_options');
        $this->assertFalse($this->ability->check_edit_permission());
    }

    // check_edit_permission() — accepted
    /** @test @group t2 */
    public function test_edit_permission_accepted_with_edit_posts_no_post_id(): void {
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_edit_permission());
    }

    /** @test @group t2 */
    public function test_edit_permission_denied_with_edit_posts_but_no_edit_post_for_post_id(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_edit_permission(['post_id' => 42]));
    }

    // check_delete_permission() — denied
    /** @test @group t2 */
    public function test_delete_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_delete_permission());
    }

    /** @test @group t2 */
    public function test_delete_permission_denied_with_edit_posts_only(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_delete_permission());
    }

    /** @test @group t2 */
    public function test_delete_permission_denied_with_delete_posts_only(): void {
        $this->allow_caps('delete_posts');
        $this->assertFalse($this->ability->check_delete_permission());
    }

    // check_delete_permission() — accepted
    /** @test @group t2 */
    public function test_delete_permission_accepted_with_both_caps_and_post_specific_caps(): void {
        $this->allow_caps('edit_posts', 'delete_posts', 'edit_post', 'delete_post');
        $this->assertTrue($this->ability->check_delete_permission(['post_id' => 42]));
    }

    // T0.3 tool registry
    /** @test @group t0 */
    public function test_ability_names_contains_all_five_page_tools(): void {
        $names = $this->ability->get_ability_names();
        $expected = [
            'elementor-mcp/create-page',
            'elementor-mcp/update-page-settings',
            'elementor-mcp/delete-page-content',
            'elementor-mcp/import-template',
            'elementor-mcp/export-page',
        ];
        foreach ($expected as $tool) {
            $this->assertContains($tool, $names, "Missing page tool: $tool");
        }
        $this->assertCount(5, $names, 'Page class must register exactly 5 tools.');
    }
}

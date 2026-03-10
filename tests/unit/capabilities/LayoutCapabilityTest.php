<?php
/**
 * T2 Capability tests — layout abilities (8 tools).
 * check_edit_permission() → edit_posts (+ edit_post when post_id given).
 * @group capabilities
 * @group layout
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class LayoutCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Layout_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data    = $this->createStub(\Elementor_MCP_Data::class);
        $factory = $this->make_factory();
        $this->ability = new \Elementor_MCP_Layout_Abilities($data, $factory);
    }

    // check_edit_permission() — denied
    /** @test @group t2 */
    public function test_edit_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_edit_permission());
    }

    /** @test @group t2 */
    public function test_edit_permission_denied_with_upload_files(): void {
        $this->allow_caps('upload_files');
        $this->assertFalse($this->ability->check_edit_permission());
    }

    // check_edit_permission() — accepted
    /** @test @group t2 */
    public function test_edit_permission_accepted_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_edit_permission());
    }

    // post_id path
    /** @test @group t2 */
    public function test_edit_permission_denied_with_edit_posts_and_post_id_but_no_edit_post(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_edit_permission(['post_id' => 42]));
    }

    /** @test @group t2 */
    public function test_edit_permission_accepted_with_edit_posts_and_edit_post_for_post_id(): void {
        $this->allow_caps('edit_posts', 'edit_post');
        $this->assertTrue($this->ability->check_edit_permission(['post_id' => 42]));
    }

    // T0.3 tool registry
    /** @test @group t0 */
    public function test_ability_names_contains_all_eight_layout_tools(): void {
        $names = $this->ability->get_ability_names();
        $expected = [
            'elementor-mcp/add-container',
            'elementor-mcp/update-container',
            'elementor-mcp/update-element',
            'elementor-mcp/batch-update',
            'elementor-mcp/reorder-elements',
            'elementor-mcp/move-element',
            'elementor-mcp/remove-element',
            'elementor-mcp/duplicate-element',
        ];
        foreach ($expected as $tool) {
            $this->assertContains($tool, $names, "Missing layout tool: $tool");
        }
        $this->assertCount(8, $names, 'Layout class must register exactly 8 tools.');
    }
}

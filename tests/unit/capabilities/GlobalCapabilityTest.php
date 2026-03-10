<?php
/**
 * T2 Capability tests — global abilities (2 tools).
 * check_manage_permission() → requires manage_options.
 * @group capabilities
 * @group global
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class GlobalCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Global_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data = $this->createStub(\Elementor_MCP_Data::class);
        $this->ability = new \Elementor_MCP_Global_Abilities($data);
    }

    // check_manage_permission() — denied
    /** @test @group t2 */
    public function test_manage_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_manage_permission());
    }

    /** @test @group t2 */
    public function test_manage_permission_denied_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_manage_permission());
    }

    /** @test @group t2 */
    public function test_manage_permission_denied_with_upload_files(): void {
        $this->allow_caps('upload_files');
        $this->assertFalse($this->ability->check_manage_permission());
    }

    // check_manage_permission() — accepted
    /** @test @group t2 */
    public function test_manage_permission_accepted_with_manage_options(): void {
        $this->allow_caps('manage_options');
        $this->assertTrue($this->ability->check_manage_permission());
    }

    // T0.3 tool registry
    /** @test @group t0 */
    public function test_ability_names_contains_both_global_tools(): void {
        $names = $this->ability->get_ability_names();
        $this->assertContains('elementor-mcp/update-global-colors', $names, 'Missing tool: update-global-colors');
        $this->assertContains('elementor-mcp/update-global-typography', $names, 'Missing tool: update-global-typography');
        $this->assertCount(2, $names, 'Global class must register exactly 2 tools.');
    }
}

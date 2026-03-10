<?php
/**
 * T2 Capability tests — template abilities.
 * check_edit_permission() → edit_posts.
 * Pro template tools only registered when ELEMENTOR_PRO_VERSION defined.
 * Core tools (save-as-template, apply-template) always registered.
 * @group capabilities
 * @group template
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class TemplateCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Template_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data    = $this->createStub(\Elementor_MCP_Data::class);
        $factory = $this->make_factory();
        $this->ability = new \Elementor_MCP_Template_Abilities($data, $factory);
    }

    // check_edit_permission() — denied
    /** @test @group t2 */
    public function test_edit_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_edit_permission());
    }

    /** @test @group t2 */
    public function test_edit_permission_denied_with_manage_options(): void {
        $this->allow_caps('manage_options');
        $this->assertFalse($this->ability->check_edit_permission());
    }

    // check_edit_permission() — accepted
    /** @test @group t2 */
    public function test_edit_permission_accepted_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_edit_permission());
    }

    // Pro template tool gating
    /** @test @group t0 */
    public function test_pro_template_tools_not_registered_when_elementor_pro_not_defined(): void {
        $this->assertFalse(defined('ELEMENTOR_PRO_VERSION'), 'ELEMENTOR_PRO_VERSION must not be defined in test environment.');
        $names = $this->ability->get_ability_names();
        $pro_tools = [
            'elementor-mcp/create-theme-template',
            'elementor-mcp/set-template-conditions',
            'elementor-mcp/list-dynamic-tags',
            'elementor-mcp/set-dynamic-tag',
            'elementor-mcp/create-popup',
            'elementor-mcp/set-popup-settings',
        ];
        foreach ($pro_tools as $tool) {
            $this->assertNotContains($tool, $names, "Pro template tool should not be registered without ELEMENTOR_PRO_VERSION: $tool");
        }
    }

    // Core template tools always present
    /** @test @group t0 */
    public function test_core_template_tools_always_registered(): void {
        $names = $this->ability->get_ability_names();
        $this->assertContains('elementor-mcp/save-as-template', $names, 'Missing core tool: save-as-template');
        $this->assertContains('elementor-mcp/apply-template', $names, 'Missing core tool: apply-template');
    }
}

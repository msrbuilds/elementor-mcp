<?php
/**
 * T2 Capability tests — query abilities (9 read-only tools).
 * All 9 tools use check_read_permission() → requires edit_posts.
 * @group capabilities
 * @group query
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class QueryCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Query_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data   = $this->createStub(\Elementor_MCP_Data::class);
        $schema = $this->createStub(\Elementor_MCP_Schema_Generator::class);
        $this->ability = new \Elementor_MCP_Query_Abilities($data, $schema);
    }

    // T2.1 denied
    /** @test @group t2 */
    public function test_read_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_read_permission());
    }

    /** @test @group t2 */
    public function test_read_permission_denied_with_manage_options(): void {
        $this->allow_caps('manage_options');
        $this->assertFalse($this->ability->check_read_permission());
    }

    // T2.2 accepted
    /** @test @group t2 */
    public function test_read_permission_accepted_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_read_permission());
    }

    // T0.3 tool registry
    /** @test @group t0 */
    public function test_ability_names_contains_all_nine_query_tools(): void {
        $names = $this->ability->get_ability_names();
        $expected = [
            'elementor-mcp/list-widgets',
            'elementor-mcp/get-widget-schema',
            'elementor-mcp/get-container-schema',
            'elementor-mcp/get-page-structure',
            'elementor-mcp/get-element-settings',
            'elementor-mcp/find-element',
            'elementor-mcp/list-pages',
            'elementor-mcp/list-templates',
            'elementor-mcp/get-global-settings',
        ];
        foreach ($expected as $tool) {
            $this->assertContains($tool, $names, "Missing query tool: $tool");
        }
        $this->assertCount(9, $names, 'Query class must register exactly 9 tools.');
    }
}

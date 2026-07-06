<?php
/**
 * T2 Capability tests — global classes WRITE abilities (4 tools).
 * check_write_permission() -> manage_options.
 * @group capabilities
 * @group global-classes
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

// Stub Elementor's Global Classes repository so is_available() is true in the
// unit env (Elementor isn't loaded). The class only needs to exist for the
// capability/registry tests; its methods are not exercised here.
if (!class_exists('\\Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository')) {
    eval('namespace Elementor\\Modules\\GlobalClasses; class Global_Classes_Repository {}');
}

class GlobalClassesWriteCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Global_Classes_Write_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data          = $this->createStub(\Elementor_MCP_Data::class);
        $this->ability = new \Elementor_MCP_Global_Classes_Write_Abilities($data);
    }

    // check_write_permission() — denied without manage_options
    /** @test @group t2 */
    public function test_write_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_write_permission());
    }

    // edit_posts alone is NOT enough — writes require manage_options
    /** @test @group t2 */
    public function test_write_permission_denied_with_only_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_write_permission());
    }

    // check_write_permission() — accepted with manage_options
    /** @test @group t2 */
    public function test_write_permission_accepted_with_manage_options(): void {
        $this->allow_caps('manage_options');
        $this->assertTrue($this->ability->check_write_permission());
    }

    // T0.3 tool registry — exactly the 4 write tools
    /** @test @group t0 */
    public function test_ability_names_are_the_four_write_tools(): void {
        $names = $this->ability->get_ability_names();
        $this->assertContains('elementor-mcp/create-global-class', $names);
        $this->assertContains('elementor-mcp/update-global-class', $names);
        $this->assertContains('elementor-mcp/delete-global-class', $names);
        $this->assertContains('elementor-mcp/apply-global-class', $names);
        $this->assertCount(4, $names, 'Global Classes write class must register exactly 4 tools.');
    }
}

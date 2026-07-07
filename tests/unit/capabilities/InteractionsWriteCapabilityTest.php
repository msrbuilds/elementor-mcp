<?php
/**
 * T2 Capability tests — Interactions WRITE abilities (4 tools).
 * check_write_permission() -> manage_options; check_read_permission() -> edit_posts.
 * @group capabilities
 * @group interactions
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class InteractionsWriteCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Interactions_Write_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        // is_available()/get_ability_names() require the e_interactions experiment
        // AND atomic support (e_atomic_elements).
        $GLOBALS['_active_experiments'] = array('e_interactions', 'e_atomic_elements');
        $data          = $this->createStub(\Elementor_MCP_Data::class);
        $this->ability = new \Elementor_MCP_Interactions_Write_Abilities($data);
    }

    protected function tearDown(): void {
        $GLOBALS['_active_experiments'] = array();
        parent::tearDown();
    }

    /** @test @group t2 */
    public function test_write_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertWPError($this->ability->check_write_permission(array()));
    }

    /** @test @group t2 */
    public function test_write_permission_denied_with_only_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertWPError($this->ability->check_write_permission(array()));
    }

    /** @test @group t2 */
    public function test_write_permission_accepted_with_manage_options(): void {
        $this->allow_caps('manage_options');
        $this->assertTrue($this->ability->check_write_permission(array()));
    }

    /** @test @group t2 */
    public function test_read_permission_accepted_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_read_permission(array()));
    }

    /** @test @group t2 */
    public function test_read_permission_denied_for_uneditable_post(): void {
        // edit_posts globally, but no edit_post cap for the requested page.
        $GLOBALS['_caps'] = array('edit_posts');
        $this->assertWPError($this->ability->check_read_permission(array('post_id' => 999)));
    }

    /** @test @group t0 */
    public function test_not_available_when_e_interactions_experiment_off(): void {
        // Atomic supported but the Interactions experiment is disabled → no tools.
        $GLOBALS['_active_experiments'] = array('e_atomic_elements');
        $this->assertFalse(\Elementor_MCP_Interactions_Write_Abilities::is_available());
        $this->assertCount(0, $this->ability->get_ability_names());
    }

    /** @test @group t0 */
    public function test_not_available_when_atomic_unsupported(): void {
        // Interactions experiment on but atomic support off → no tools (a write
        // would land while the runtime never reads the interactions field).
        $GLOBALS['_active_experiments'] = array('e_interactions');
        $this->assertFalse(\Elementor_MCP_Interactions_Write_Abilities::is_available());
        $this->assertCount(0, $this->ability->get_ability_names());
    }

    /** @test @group t0 */
    public function test_ability_names_are_the_four_tools(): void {
        $names = $this->ability->get_ability_names();
        $this->assertContains('elementor-mcp/list-interactions', $names);
        $this->assertContains('elementor-mcp/add-interaction', $names);
        $this->assertContains('elementor-mcp/edit-interaction', $names);
        $this->assertContains('elementor-mcp/delete-interaction', $names);
        $this->assertCount(4, $names, 'Interactions write class must register exactly 4 tools.');
    }
}

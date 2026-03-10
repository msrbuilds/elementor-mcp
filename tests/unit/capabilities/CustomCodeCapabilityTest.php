<?php
/**
 * T2 Capability tests — custom code abilities.
 * check_edit_permission()    → edit_posts (+ edit_post when post_id given).
 * check_snippet_permission() → manage_options AND unfiltered_html.
 * check_manage_permission()  → manage_options.
 * @group capabilities
 * @group custom-code
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class CustomCodeCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Custom_Code_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data    = $this->createStub(\Elementor_MCP_Data::class);
        $factory = $this->createStub(\Elementor_MCP_Element_Factory::class);
        $this->ability = new \Elementor_MCP_Custom_Code_Abilities($data, $factory);
        // register() populates $ability_names; wp_register_ability() is a no-op stub.
        $this->ability->register();
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

    // check_edit_permission() — post_id path denied
    /** @test @group t2 */
    public function test_edit_permission_denied_with_edit_posts_and_post_id_but_no_edit_post(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_edit_permission(['post_id' => 99]));
    }

    // check_snippet_permission() — denied
    /** @test @group t2 */
    public function test_snippet_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_snippet_permission());
    }

    /** @test @group t2 */
    public function test_snippet_permission_denied_with_manage_options_only(): void {
        $this->allow_caps('manage_options');
        $this->assertFalse($this->ability->check_snippet_permission());
    }

    /** @test @group t2 */
    public function test_snippet_permission_denied_with_unfiltered_html_only(): void {
        $this->allow_caps('unfiltered_html');
        $this->assertFalse($this->ability->check_snippet_permission());
    }

    // check_snippet_permission() — accepted
    /** @test @group t2 */
    public function test_snippet_permission_accepted_with_manage_options_and_unfiltered_html(): void {
        $this->allow_caps('manage_options', 'unfiltered_html');
        $this->assertTrue($this->ability->check_snippet_permission());
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

    // check_manage_permission() — accepted
    /** @test @group t2 */
    public function test_manage_permission_accepted_with_manage_options(): void {
        $this->allow_caps('manage_options');
        $this->assertTrue($this->ability->check_manage_permission());
    }

    // T0.3 tool registry
    /** @test @group t0 */
    public function test_ability_names_returns_non_empty_array_without_fatal(): void {
        $names = $this->ability->get_ability_names();
        $this->assertIsArray($names);
        $this->assertNotEmpty($names, 'Custom code class must register at least one tool.');
    }
}

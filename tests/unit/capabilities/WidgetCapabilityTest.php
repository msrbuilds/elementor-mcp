<?php
/**
 * T2 Capability tests — widget abilities.
 * check_edit_permission() → edit_posts.
 * Surface (v3.0.0): add-free-widget + update-widget always; add-pro-widget only
 * when ELEMENTOR_PRO_VERSION is defined. WooCommerce widgets are catalog entries
 * (tier 'woo') reached via add-pro-widget, not separate tool registrations.
 * @group capabilities
 * @group widget
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class WidgetCapabilityTest extends Ability_Test_Case {
    private \EMCP_Tools_Widget_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data      = $this->createStub(\EMCP_Tools_Data::class);
        $factory   = $this->make_factory();
        $schema    = $this->createStub(\EMCP_Tools_Schema_Generator::class);
        $validator = $this->createStub(\EMCP_Tools_Settings_Validator::class);
        $this->ability = new \EMCP_Tools_Widget_Abilities($data, $factory, $schema, $validator);
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

    // Pro insert-tool gating (add-pro-widget registers only with Elementor Pro)
    /** @test @group t0 */
    public function test_pro_insert_tool_gated_on_elementor_pro(): void {
        $this->assertFalse(defined('ELEMENTOR_PRO_VERSION'), 'ELEMENTOR_PRO_VERSION must not be defined in test environment.');
        $names = $this->ability->get_ability_names();
        $this->assertNotContains('emcp-tools/add-pro-widget', $names, 'add-pro-widget must be gated on Elementor Pro');
        $this->assertContains('emcp-tools/add-free-widget', $names);
    }

    // Core tools always present
    /** @test @group t0 */
    public function test_core_tools_always_registered(): void {
        $names = $this->ability->get_ability_names();
        foreach (['emcp-tools/add-free-widget', 'emcp-tools/update-widget'] as $tool) {
            $this->assertContains($tool, $names, "Core widget tool must always be registered: $tool");
        }
    }

    /** @test @group t0 */
    public function test_old_convenience_tools_removed(): void {
        $names = $this->ability->get_ability_names();
        foreach (['emcp-tools/add-widget', 'emcp-tools/add-heading', 'emcp-tools/add-button', 'emcp-tools/add-form'] as $gone) {
            $this->assertNotContains($gone, $names, "Removed in v3.0.0: $gone");
        }
    }
}

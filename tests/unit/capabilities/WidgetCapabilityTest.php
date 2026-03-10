<?php
/**
 * T2 Capability tests — widget abilities.
 * check_edit_permission() → edit_posts.
 * Pro tools only registered when ELEMENTOR_PRO_VERSION defined.
 * WC tools only registered when WooCommerce class exists.
 * @group capabilities
 * @group widget
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class WidgetCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Widget_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data      = $this->createStub(\Elementor_MCP_Data::class);
        $factory   = $this->make_factory();
        $schema    = $this->createStub(\Elementor_MCP_Schema_Generator::class);
        $validator = $this->createStub(\Elementor_MCP_Settings_Validator::class);
        $this->ability = new \Elementor_MCP_Widget_Abilities($data, $factory, $schema, $validator);
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

    // Pro tool gating
    /** @test @group t0 */
    public function test_pro_tools_not_registered_when_elementor_pro_not_defined(): void {
        $this->assertFalse(defined('ELEMENTOR_PRO_VERSION'), 'ELEMENTOR_PRO_VERSION must not be defined in test environment.');
        $names = $this->ability->get_ability_names();
        $pro_tools = [
            'elementor-mcp/add-form',
            'elementor-mcp/add-posts-grid',
            'elementor-mcp/add-countdown',
            'elementor-mcp/add-price-table',
            'elementor-mcp/add-flip-box',
            'elementor-mcp/add-animated-headline',
            'elementor-mcp/add-call-to-action',
            'elementor-mcp/add-slides',
            'elementor-mcp/add-testimonial-carousel',
            'elementor-mcp/add-price-list',
            'elementor-mcp/add-gallery',
            'elementor-mcp/add-share-buttons',
            'elementor-mcp/add-table-of-contents',
            'elementor-mcp/add-blockquote',
            'elementor-mcp/add-lottie',
            'elementor-mcp/add-hotspot',
        ];
        foreach ($pro_tools as $tool) {
            $this->assertNotContains($tool, $names, "Pro tool should not be registered without ELEMENTOR_PRO_VERSION: $tool");
        }
    }

    // WooCommerce tool gating
    /** @test @group t0 */
    public function test_wc_tools_not_registered_when_woocommerce_not_exists(): void {
        $this->assertFalse(class_exists('WooCommerce'), 'WooCommerce class must not exist in test environment.');
        $names = $this->ability->get_ability_names();
        $wc_tools = [
            'elementor-mcp/add-wc-products',
            'elementor-mcp/add-wc-add-to-cart',
            'elementor-mcp/add-wc-cart',
            'elementor-mcp/add-wc-checkout',
            'elementor-mcp/add-wc-menu-cart',
        ];
        foreach ($wc_tools as $tool) {
            $this->assertNotContains($tool, $names, "WC tool should not be registered without WooCommerce: $tool");
        }
    }

    // Core tools always present
    /** @test @group t0 */
    public function test_core_tools_always_registered(): void {
        $names = $this->ability->get_ability_names();
        $core_tools = [
            'elementor-mcp/add-widget',
            'elementor-mcp/update-widget',
            'elementor-mcp/add-heading',
            'elementor-mcp/add-text-editor',
            'elementor-mcp/add-image',
            'elementor-mcp/add-button',
            'elementor-mcp/add-html',
            'elementor-mcp/add-spacer',
            'elementor-mcp/add-divider',
        ];
        foreach ($core_tools as $tool) {
            $this->assertContains($tool, $names, "Core widget tool must always be registered: $tool");
        }
    }
}

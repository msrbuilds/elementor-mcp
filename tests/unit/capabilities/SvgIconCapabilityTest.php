<?php
/**
 * T2 Capability tests — SVG icon abilities (1 tool).
 * check_upload_permission() → requires upload_files.
 * @group capabilities
 * @group svg-icon
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class SvgIconCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Svg_Icon_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data    = $this->createStub(\Elementor_MCP_Data::class);
        $factory = $this->make_factory();
        $this->ability = new \Elementor_MCP_Svg_Icon_Abilities($data, $factory);
    }

    // check_upload_permission() — denied
    /** @test @group t2 */
    public function test_upload_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_upload_permission());
    }

    /** @test @group t2 */
    public function test_upload_permission_denied_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_upload_permission());
    }

    // check_upload_permission() — accepted
    /** @test @group t2 */
    public function test_upload_permission_accepted_with_upload_files(): void {
        $this->allow_caps('upload_files');
        $this->assertTrue($this->ability->check_upload_permission());
    }

    // T0.3 tool registry
    /** @test @group t0 */
    public function test_ability_names_contains_upload_svg_icon_and_count_is_one(): void {
        $names = $this->ability->get_ability_names();
        $this->assertContains('elementor-mcp/upload-svg-icon', $names, 'Missing tool: upload-svg-icon');
        $this->assertCount(1, $names, 'SVG icon class must register exactly 1 tool.');
    }
}

<?php
/**
 * T2 Capability tests — stock image abilities (3 tools).
 * check_read_permission()     → edit_posts.
 * check_upload_permission()   → upload_files.
 * check_combined_permission() → edit_posts AND upload_files (+ edit_post when post_id given).
 * @group capabilities
 * @group stock-image
 * @package Elementor_MCP\Tests\Capabilities
 */
namespace Elementor_MCP\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use Elementor_MCP\Tests\Ability_Test_Case;

class StockImageCapabilityTest extends Ability_Test_Case {
    private \Elementor_MCP_Stock_Image_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $data    = $this->createStub(\Elementor_MCP_Data::class);
        $factory = $this->make_factory();
        $this->ability = new \Elementor_MCP_Stock_Image_Abilities($data, $factory);
    }

    // check_read_permission() — denied
    /** @test @group t2 */
    public function test_read_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_read_permission());
    }

    /** @test @group t2 */
    public function test_read_permission_denied_with_upload_files(): void {
        $this->allow_caps('upload_files');
        $this->assertFalse($this->ability->check_read_permission());
    }

    // check_read_permission() — accepted
    /** @test @group t2 */
    public function test_read_permission_accepted_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_read_permission());
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

    // check_combined_permission() — denied
    /** @test @group t2 */
    public function test_combined_permission_denied_with_no_caps(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_combined_permission());
    }

    /** @test @group t2 */
    public function test_combined_permission_denied_with_edit_posts_only(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_combined_permission());
    }

    /** @test @group t2 */
    public function test_combined_permission_denied_with_upload_files_only(): void {
        $this->allow_caps('upload_files');
        $this->assertFalse($this->ability->check_combined_permission());
    }

    // check_combined_permission() — accepted without post_id
    /** @test @group t2 */
    public function test_combined_permission_accepted_with_edit_posts_and_upload_files_no_post_id(): void {
        $this->allow_caps('edit_posts', 'upload_files');
        $this->assertTrue($this->ability->check_combined_permission());
    }

    // check_combined_permission() — post_id path denied
    /** @test @group t2 */
    public function test_combined_permission_denied_with_post_id_when_edit_post_not_granted(): void {
        $this->allow_caps('edit_posts', 'upload_files');
        $this->assertFalse($this->ability->check_combined_permission(['post_id' => 42]));
    }

    // check_combined_permission() — post_id path accepted
    /** @test @group t2 */
    public function test_combined_permission_accepted_with_all_caps_and_post_id(): void {
        $this->allow_caps('edit_posts', 'upload_files', 'edit_post');
        $this->assertTrue($this->ability->check_combined_permission(['post_id' => 42]));
    }

    // T0.3 tool registry
    /** @test @group t0 */
    public function test_ability_names_count_is_three(): void {
        $names = $this->ability->get_ability_names();
        $expected = [
            'elementor-mcp/search-images',
            'elementor-mcp/sideload-image',
            'elementor-mcp/add-stock-image',
        ];
        foreach ($expected as $tool) {
            $this->assertContains($tool, $names, "Missing stock image tool: $tool");
        }
        $this->assertCount(3, $names, 'Stock image class must register exactly 3 tools.');
    }
}

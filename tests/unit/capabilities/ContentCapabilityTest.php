<?php
/**
 * Capability gating for the WordPress Content tools.
 * @group capabilities
 * @group content
 * @package EMCP_Tools\Tests\Capabilities
 */
namespace EMCP_Tools\Tests\Capabilities;

require_once dirname(__DIR__) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ContentCapabilityTest extends Ability_Test_Case {
    private \EMCP_Tools_Content_Abilities $ability;

    protected function setUp(): void {
        parent::setUp();
        $this->ability = new \EMCP_Tools_Content_Abilities();
        $this->ability->register();
    }

    /** @test */
    public function test_read_denied_without_edit_posts(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_read_permission());
    }

    /** @test */
    public function test_read_allowed_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_read_permission());
    }

    /** @test */
    public function test_create_allowed_with_edit_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_create_permission());
    }

    /** @test */
    public function test_create_denied_without_edit_posts(): void {
        $this->deny_all_caps();
        $this->assertFalse($this->ability->check_create_permission());
    }

    /** @test */
    public function test_edit_denied_on_unowned_post(): void {
        // edit_posts yes, but per-post edit_post no.
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_edit_permission(['post_id' => 42]));
    }

    /** @test */
    public function test_edit_allowed_with_both_caps(): void {
        $this->allow_caps('edit_posts', 'edit_post');
        $this->assertTrue($this->ability->check_edit_permission(['post_id' => 42]));
    }

    /** @test */
    public function test_edit_allowed_without_post_id(): void {
        // No post_id → only the generic edit_posts is required.
        $this->allow_caps('edit_posts');
        $this->assertTrue($this->ability->check_edit_permission([]));
    }

    /** @test */
    public function test_delete_denied_without_delete_posts(): void {
        $this->allow_caps('edit_posts');
        $this->assertFalse($this->ability->check_delete_permission(['post_id' => 42]));
    }

    /** @test */
    public function test_delete_allowed_with_both_caps(): void {
        $this->allow_caps('delete_posts', 'delete_post');
        $this->assertTrue($this->ability->check_delete_permission(['post_id' => 42]));
    }
}

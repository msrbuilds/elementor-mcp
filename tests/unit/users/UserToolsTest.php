<?php
/**
 * Execute-path tests for the WordPress Users tools.
 * @group users
 * @package EMCP_Tools\Tests\Users
 */
namespace EMCP_Tools\Tests\Users;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class UserToolsTest extends Ability_Test_Case {
	private \EMCP_Tools_User_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_users'] = array(
			9 => (object) array( 'ID' => 9, 'user_login' => 'jane', 'display_name' => 'Jane Doe', 'user_email' => 'jane@example.com', 'roles' => array( 'author' ), 'first_name' => 'Jane', 'last_name' => 'Doe', 'nickname' => 'jane', 'user_url' => 'https://jane.example', 'description' => 'Writer', 'user_registered' => '2026-01-01 00:00:00' ),
			1 => (object) array( 'ID' => 1, 'user_login' => 'admin', 'display_name' => 'Admin', 'user_email' => 'admin@example.com', 'roles' => array( 'administrator' ), 'user_registered' => '2025-01-01 00:00:00' ),
		);
		$GLOBALS['_wp_user_caps'] = array( 1 => array( 'manage_options', 'edit_users', 'promote_users', 'delete_users' ), 9 => array( 'edit_posts', 'publish_posts' ) );
		$GLOBALS['_wp_user_query_result'] = array( $GLOBALS['_wp_users'][9], $GLOBALS['_wp_users'][1] );
		$GLOBALS['_wp_user_query_total']  = 2;
		$GLOBALS['_wp_inserted_users']    = array();
		$GLOBALS['_wp_updated_users']     = array();
		$GLOBALS['_wp_new_user_notifications'] = array();
		$this->ability = new \EMCP_Tools_User_Abilities();
		$this->ability->register();
	}

	/** @test */
	public function test_registers_four_tools(): void {
		$names = $this->ability->get_ability_names();
		foreach ( array( 'list-users', 'get-user', 'create-user', 'update-user' ) as $slug ) {
			$this->assertContains( 'emcp-tools/' . $slug, $names );
		}
		$this->assertCount( 4, $names );
	}

	/** @test */
	public function test_list_users_rows(): void {
		$out = $this->ability->execute_list_users( array() );
		$this->assertResultHasKey( $out, 'users' );
		$rows = array();
		foreach ( $out['users'] as $r ) { $rows[ $r['id'] ] = $r; }
		$this->assertSame( 'jane', $rows[9]['username'] );
		$this->assertSame( 'jane@example.com', $rows[9]['email'] );
		$this->assertContains( 'author', $rows[9]['roles'] );
		$this->assertSame( 2, $out['total'] );
	}

	/** @test */
	public function test_list_users_never_leaks_secrets(): void {
		$out  = $this->ability->execute_list_users( array() );
		$json = json_encode( $out );
		$this->assertStringNotContainsStringIgnoringCase( 'user_pass', $json );
		$this->assertStringNotContainsStringIgnoringCase( 'password', $json );
	}

	/** @test */
	public function test_get_user_detail_and_is_admin_flag(): void {
		$jane = $this->ability->execute_get_user( array( 'id' => 9 ) );
		$this->assertNotWPError( $jane );
		$this->assertSame( 'Jane Doe', $jane['display_name'] );
		$this->assertFalse( $jane['is_admin'] );
		$admin = $this->ability->execute_get_user( array( 'id' => 1 ) );
		$this->assertTrue( $admin['is_admin'] );
	}

	/** @test */
	public function test_get_user_not_found(): void {
		$this->assertWPError( $this->ability->execute_get_user( array( 'id' => 999 ) ), 'user_not_found' );
	}

	/** @test */
	public function test_get_user_requires_id(): void {
		$this->assertWPError( $this->ability->execute_get_user( array() ), 'missing_params' );
	}

	/** @test */
	public function test_create_user_makes_subscriber_and_emails_no_password_returned(): void {
		$GLOBALS['_wp_next_user_id'] = 555;
		$out = $this->ability->execute_create_user( array( 'username' => 'newbie', 'email' => 'newbie@example.com' ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 555, $out['id'] );
		$this->assertSame( 'subscriber', $out['role'] );
		$this->assertArrayNotHasKey( 'password', $out );
		$this->assertArrayNotHasKey( 'generated_password', $out );
		$this->assertStringNotContainsStringIgnoringCase( 'GENERATED-PASSWORD', json_encode( $out ) );
		// notification sent.
		$this->assertNotEmpty( $GLOBALS['_wp_new_user_notifications'] );
		// a password was generated and passed to wp_insert_user.
		$this->assertArrayHasKey( 'user_pass', $GLOBALS['_wp_inserted_users'][0] );
	}

	/** @test */
	public function test_create_user_rejects_admin_role(): void {
		$out = $this->ability->execute_create_user( array( 'username' => 'evil', 'email' => 'evil@example.com', 'role' => 'administrator' ) );
		$this->assertWPError( $out, 'forbidden_role' );
		$this->assertSame( array(), $GLOBALS['_wp_inserted_users'] );
	}

	/** @test */
	public function test_create_user_rejects_unknown_role(): void {
		$this->assertWPError( $this->ability->execute_create_user( array( 'username' => 'x', 'email' => 'x@example.com', 'role' => 'wizard' ) ), 'forbidden_role' );
	}

	/** @test */
	public function test_create_user_requires_username_and_email(): void {
		$this->assertWPError( $this->ability->execute_create_user( array( 'email' => 'a@b.com' ) ), 'missing_params' );
		$this->assertWPError( $this->ability->execute_create_user( array( 'username' => 'a' ) ), 'missing_params' );
	}

	/** @test */
	public function test_create_user_surfaces_insert_error(): void {
		$GLOBALS['_wp_insert_user_error'] = 'username exists';
		$this->assertWPError( $this->ability->execute_create_user( array( 'username' => 'dup', 'email' => 'dup@example.com' ) ), 'insert_failed' );
	}

	/** @test */
	public function test_update_user_edits_non_admin_profile(): void {
		$out = $this->ability->execute_update_user( array( 'id' => 9, 'display_name' => 'Jane Q. Doe', 'description' => 'Senior writer' ) );
		$this->assertNotWPError( $out );
		$this->assertContains( 'display_name', $out['updated'] );
		$this->assertContains( 'description', $out['updated'] );
		$arr = $GLOBALS['_wp_updated_users'][0];
		$this->assertSame( 9, (int) $arr['ID'] );
		$this->assertSame( 'Jane Q. Doe', $arr['display_name'] );
	}

	/** @test */
	public function test_update_user_refuses_admin_target(): void {
		$out = $this->ability->execute_update_user( array( 'id' => 1, 'description' => 'hacked' ) );
		$this->assertWPError( $out, 'protected_user' );
		$this->assertSame( array(), $GLOBALS['_wp_updated_users'] );
	}

	/** @test */
	public function test_update_user_ignores_role_and_password(): void {
		$out = $this->ability->execute_update_user( array( 'id' => 9, 'role' => 'administrator', 'password' => 'x', 'user_pass' => 'x', 'display_name' => 'Jane' ) );
		$this->assertNotWPError( $out );
		$arr = $GLOBALS['_wp_updated_users'][0];
		$this->assertArrayNotHasKey( 'role', $arr );
		$this->assertArrayNotHasKey( 'user_pass', $arr );
		$this->assertArrayNotHasKey( 'password', $arr );
	}

	/** @test */
	public function test_update_user_not_found(): void {
		$this->assertWPError( $this->ability->execute_update_user( array( 'id' => 999, 'display_name' => 'x' ) ), 'user_not_found' );
	}

	/** @test */
	public function test_update_user_requires_id(): void {
		$this->assertWPError( $this->ability->execute_update_user( array( 'display_name' => 'x' ) ), 'missing_params' );
	}
}

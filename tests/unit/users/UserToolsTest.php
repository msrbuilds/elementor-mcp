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
		foreach ( array( 'list-users', 'get-user' ) as $slug ) {
			$this->assertContains( 'emcp-tools/' . $slug, $names );
		}
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
}

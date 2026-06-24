<?php
/**
 * Execute-path + guard tests for the Plugins tools.
 * @group packages
 * @package EMCP_Tools\Tests\Packages
 */
namespace EMCP_Tools\Tests\Packages;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class PluginToolsTest extends Ability_Test_Case {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_plugins'] = array(
			'akismet/akismet.php'           => array( 'Name' => 'Akismet', 'Version' => '5.0', 'Author' => 'Automattic' ),
			'elementor/elementor.php'       => array( 'Name' => 'Elementor', 'Version' => '4.1', 'Author' => 'Elementor' ),
			'elementor-mcp/emcp-tools.php'  => array( 'Name' => 'EMCP Tools', 'Version' => '3.0.0', 'Author' => 'MSR' ),
			'hello-dolly/hello.php'         => array( 'Name' => 'Hello Dolly', 'Version' => '1.7', 'Author' => 'Matt' ),
		);
		$GLOBALS['_wp_active_plugins']     = array( 'elementor/elementor.php', 'elementor-mcp/emcp-tools.php', 'akismet/akismet.php' );
		$GLOBALS['_wp_fs_method']          = 'direct';
		$GLOBALS['_wp_deactivated_plugins'] = array();
		$GLOBALS['_wp_deleted_plugins']     = array();
		$GLOBALS['_wp_installed_packages']  = array();
		$GLOBALS['_wp_upgraded']            = array();
		if ( ! defined( 'EMCP_TOOLS_BASENAME' ) ) {
			define( 'EMCP_TOOLS_BASENAME', 'elementor-mcp/emcp-tools.php' );
		}
	}

	/** @test */
	public function test_guard_protects_emcp_and_elementor(): void {
		$this->assertTrue( \EMCP_Tools_Package_Guard::is_protected_plugin( 'elementor-mcp/emcp-tools.php' ) );
		$this->assertTrue( \EMCP_Tools_Package_Guard::is_protected_plugin( 'elementor/elementor.php' ) );
		$this->assertTrue( \EMCP_Tools_Package_Guard::is_protected_plugin( 'elementor-pro/elementor-pro.php' ) );
		$this->assertFalse( \EMCP_Tools_Package_Guard::is_protected_plugin( 'akismet/akismet.php' ) );
	}

	/** @test */
	public function test_guard_filesystem_ready_ok_when_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'direct';
		$this->assertTrue( \EMCP_Tools_Package_Guard::filesystem_ready() );
	}

	/** @test */
	public function test_guard_filesystem_error_when_not_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'ftpext';
		$this->assertWPError( \EMCP_Tools_Package_Guard::filesystem_ready(), 'filesystem_unavailable' );
	}

	private function plugins(): \EMCP_Tools_Plugin_Abilities {
		$a = new \EMCP_Tools_Plugin_Abilities();
		$a->register();
		return $a;
	}

	/** @test */
	public function test_registers_seven_tools(): void {
		$names = $this->plugins()->get_ability_names();
		foreach ( array( 'list-plugins', 'search-plugins', 'install-plugin', 'activate-plugin', 'deactivate-plugin', 'update-plugin', 'delete-plugin' ) as $slug ) {
			$this->assertContains( 'emcp-tools/' . $slug, $names );
		}
		$this->assertCount( 7, $names );
	}

	/** @test */
	public function test_list_plugins_rows_and_flags(): void {
		$GLOBALS['_wp_site_transients']['update_plugins'] = (object) array(
			'response' => array( 'hello-dolly/hello.php' => (object) array( 'new_version' => '1.8' ) ),
		);
		$out  = $this->plugins()->execute_list_plugins( array() );
		$this->assertResultHasKey( $out, 'plugins' );
		$rows = array();
		foreach ( $out['plugins'] as $r ) { $rows[ $r['file'] ] = $r; }
		$this->assertTrue( $rows['elementor/elementor.php']['is_protected'] );
		$this->assertTrue( $rows['elementor/elementor.php']['active'] );
		$this->assertFalse( $rows['hello-dolly/hello.php']['active'] );
		$this->assertTrue( $rows['hello-dolly/hello.php']['update_available'] );
		$this->assertSame( '1.8', $rows['hello-dolly/hello.php']['new_version'] );
		$this->assertFalse( $rows['akismet/akismet.php']['is_protected'] );
	}

	/** @test */
	public function test_search_plugins_returns_rows(): void {
		$GLOBALS['_wp_plugins_api_query'] = array(
			(object) array( 'slug' => 'contact-form-7', 'name' => 'Contact Form 7', 'version' => '5.9', 'rating' => 90, 'num_ratings' => 200, 'requires' => '6.0', 'tested' => '6.9', 'short_description' => 'Just a CF.' ),
		);
		$out = $this->plugins()->execute_search_plugins( array( 'search' => 'contact form' ) );
		$this->assertResultHasKey( $out, 'results' );
		$this->assertSame( 'contact-form-7', $out['results'][0]['slug'] );
	}

	/** @test */
	public function test_search_plugins_requires_query(): void {
		$this->assertWPError( $this->plugins()->execute_search_plugins( array() ), 'missing_params' );
	}

	/** @test */
	public function test_install_plugin_installs_and_optionally_activates(): void {
		$GLOBALS['_wp_upgrader_plugin_info'] = 'contact-form-7/wp-contact-form-7.php';
		$out = $this->plugins()->execute_install_plugin( array( 'slug' => 'contact-form-7', 'activate' => true ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['installed'] );
		$this->assertTrue( $out['activated'] );
		$this->assertSame( 'contact-form-7/wp-contact-form-7.php', $out['file'] );
		$this->assertNotEmpty( $GLOBALS['_wp_installed_packages'] );
	}

	/** @test */
	public function test_install_plugin_requires_slug(): void {
		$this->assertWPError( $this->plugins()->execute_install_plugin( array() ), 'missing_params' );
	}

	/** @test */
	public function test_install_plugin_surfaces_api_error(): void {
		$GLOBALS['_wp_plugins_api_error'] = 'no such plugin';
		$this->assertWPError( $this->plugins()->execute_install_plugin( array( 'slug' => 'nope-xyz' ) ), 'plugins_api_failed' );
	}

	/** @test */
	public function test_install_plugin_blocked_when_filesystem_not_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'ftpext';
		$this->assertWPError( $this->plugins()->execute_install_plugin( array( 'slug' => 'contact-form-7' ) ), 'filesystem_unavailable' );
	}

	/** @test */
	public function test_deactivate_refuses_protected(): void {
		$this->assertWPError( $this->plugins()->execute_deactivate_plugin( array( 'plugin' => 'elementor/elementor.php' ) ), 'protected_plugin' );
		$this->assertWPError( $this->plugins()->execute_deactivate_plugin( array( 'plugin' => 'elementor-mcp/emcp-tools.php' ) ), 'protected_plugin' );
		$this->assertSame( array(), $GLOBALS['_wp_deactivated_plugins'] );
	}

	/** @test */
	public function test_deactivate_allows_normal_plugin(): void {
		$out = $this->plugins()->execute_deactivate_plugin( array( 'plugin' => 'akismet/akismet.php' ) );
		$this->assertNotWPError( $out );
		$this->assertContains( 'akismet/akismet.php', $GLOBALS['_wp_deactivated_plugins'] );
	}

	/** @test */
	public function test_activate_unknown_plugin_errors(): void {
		$this->assertWPError( $this->plugins()->execute_activate_plugin( array( 'plugin' => 'ghost/ghost.php' ) ), 'plugin_not_found' );
	}

	/** @test */
	public function test_delete_refuses_active_plugin(): void {
		$this->assertWPError( $this->plugins()->execute_delete_plugin( array( 'plugin' => 'akismet/akismet.php' ) ), 'plugin_active' );
	}

	/** @test */
	public function test_delete_refuses_protected_plugin(): void {
		$this->assertWPError( $this->plugins()->execute_delete_plugin( array( 'plugin' => 'elementor/elementor.php' ) ), 'protected_plugin' );
	}

	/** @test */
	public function test_delete_removes_inactive_plugin(): void {
		$out = $this->plugins()->execute_delete_plugin( array( 'plugin' => 'hello-dolly/hello.php' ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['deleted'] );
		$this->assertContains( 'hello-dolly/hello.php', $GLOBALS['_wp_deleted_plugins'] );
	}

	/** @test */
	public function test_update_reports_up_to_date(): void {
		$GLOBALS['_wp_site_transients']['update_plugins'] = (object) array( 'response' => array() );
		$out = $this->plugins()->execute_update_plugin( array( 'plugin' => 'akismet/akismet.php' ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['up_to_date'] );
	}

	/** @test */
	public function test_update_runs_when_update_available(): void {
		$GLOBALS['_wp_site_transients']['update_plugins'] = (object) array(
			'response' => array( 'hello-dolly/hello.php' => (object) array( 'new_version' => '1.8' ) ),
		);
		$out = $this->plugins()->execute_update_plugin( array( 'plugin' => 'hello-dolly/hello.php' ) );
		$this->assertNotWPError( $out );
		$this->assertFalse( $out['up_to_date'] );
		$this->assertContains( 'hello-dolly/hello.php', $GLOBALS['_wp_upgraded'] );
	}
}

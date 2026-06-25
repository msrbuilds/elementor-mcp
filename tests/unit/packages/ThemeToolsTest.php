<?php
/**
 * Execute-path tests for the Themes tools.
 * @group packages
 * @package EMCP_Tools\Tests\Packages
 */
namespace EMCP_Tools\Tests\Packages;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class ThemeToolsTest extends Ability_Test_Case {
	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_themes'] = array(
			'twentytwentyfour' => new \WP_Theme( 'twentytwentyfour', array( 'Name' => 'Twenty Twenty-Four', 'Version' => '1.2' ) ),
			'astra'            => new \WP_Theme( 'astra', array( 'Name' => 'Astra', 'Version' => '4.6' ) ),
			'astra-child'      => new \WP_Theme( 'astra-child', array( 'Name' => 'Astra Child', 'Version' => '1.0', 'Template' => 'astra' ) ),
		);
		$GLOBALS['_wp_active_stylesheet'] = 'astra-child';
		$GLOBALS['_wp_active_template']   = 'astra';
		$GLOBALS['_wp_fs_method']         = 'direct';
		$GLOBALS['_wp_deleted_themes']    = array();
		$GLOBALS['_wp_installed_packages'] = array();
		$GLOBALS['_wp_upgraded']          = array();
		if ( ! defined( 'EMCP_TOOLS_BASENAME' ) ) {
			define( 'EMCP_TOOLS_BASENAME', 'elementor-mcp/emcp-tools.php' );
		}
	}

	private function themes(): \EMCP_Tools_Theme_Abilities {
		$a = new \EMCP_Tools_Theme_Abilities();
		$a->register();
		return $a;
	}

	/** @test */
	public function test_registers_six_tools(): void {
		$names = $this->themes()->get_ability_names();
		foreach ( array( 'list-themes', 'search-themes', 'install-theme', 'switch-theme', 'update-theme', 'delete-theme' ) as $slug ) {
			$this->assertContains( 'emcp-tools/' . $slug, $names );
		}
		$this->assertCount( 6, $names );
	}

	/** @test */
	public function test_list_themes_marks_active_and_parent(): void {
		$out  = $this->themes()->execute_list_themes( array() );
		$this->assertResultHasKey( $out, 'themes' );
		$rows = array();
		foreach ( $out['themes'] as $r ) { $rows[ $r['stylesheet'] ] = $r; }
		$this->assertTrue( $rows['astra-child']['is_active'] );
		$this->assertSame( 'astra', $rows['astra-child']['parent'] );
		$this->assertFalse( $rows['astra']['is_active'] );
	}

	/** @test */
	public function test_search_themes_returns_rows(): void {
		$GLOBALS['_wp_themes_api_query'] = array(
			(object) array( 'slug' => 'hello-elementor', 'name' => 'Hello Elementor', 'version' => '3.0', 'rating' => 98, 'requires' => '6.0' ),
		);
		$out = $this->themes()->execute_search_themes( array( 'search' => 'elementor' ) );
		$this->assertSame( 'hello-elementor', $out['results'][0]['slug'] );
	}

	/** @test */
	public function test_search_themes_requires_query(): void {
		$this->assertWPError( $this->themes()->execute_search_themes( array() ), 'missing_params' );
	}

	/** @test */
	public function test_install_theme_installs_and_optionally_activates(): void {
		$GLOBALS['_wp_upgrader_theme_info'] = 'hello-elementor';
		$out = $this->themes()->execute_install_theme( array( 'slug' => 'hello-elementor', 'activate' => true ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['installed'] );
		$this->assertTrue( $out['activated'] );
		$this->assertSame( 'hello-elementor', $GLOBALS['_wp_switched_theme'] );
	}

	/** @test */
	public function test_install_theme_requires_slug(): void {
		$this->assertWPError( $this->themes()->execute_install_theme( array() ), 'missing_params' );
	}

	/** @test */
	public function test_install_theme_blocked_when_filesystem_not_direct(): void {
		$GLOBALS['_wp_fs_method'] = 'ftpext';
		$this->assertWPError( $this->themes()->execute_install_theme( array( 'slug' => 'astra' ) ), 'filesystem_unavailable' );
	}

	/** @test */
	public function test_switch_theme_activates_installed(): void {
		$out = $this->themes()->execute_switch_theme( array( 'stylesheet' => 'twentytwentyfour' ) );
		$this->assertNotWPError( $out );
		$this->assertSame( 'twentytwentyfour', $GLOBALS['_wp_switched_theme'] );
	}

	/** @test */
	public function test_switch_theme_unknown_errors(): void {
		$this->assertWPError( $this->themes()->execute_switch_theme( array( 'stylesheet' => 'ghost' ) ), 'theme_not_found' );
	}

	/** @test */
	public function test_delete_theme_refuses_active(): void {
		$this->assertWPError( $this->themes()->execute_delete_theme( array( 'stylesheet' => 'astra-child' ) ), 'theme_active' );
	}

	/** @test */
	public function test_delete_theme_refuses_active_parent(): void {
		$this->assertWPError( $this->themes()->execute_delete_theme( array( 'stylesheet' => 'astra' ) ), 'theme_active' );
	}

	/** @test */
	public function test_delete_theme_removes_inactive(): void {
		$out = $this->themes()->execute_delete_theme( array( 'stylesheet' => 'twentytwentyfour' ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['deleted'] );
		$this->assertContains( 'twentytwentyfour', $GLOBALS['_wp_deleted_themes'] );
	}

	/** @test */
	public function test_update_theme_reports_up_to_date(): void {
		$GLOBALS['_wp_site_transients']['update_themes'] = (object) array( 'response' => array() );
		$out = $this->themes()->execute_update_theme( array( 'stylesheet' => 'astra' ) );
		$this->assertNotWPError( $out );
		$this->assertTrue( $out['up_to_date'] );
	}

	/** @test */
	public function test_update_theme_runs_when_available(): void {
		$GLOBALS['_wp_site_transients']['update_themes'] = (object) array(
			'response' => array( 'astra' => array( 'new_version' => '4.7' ) ),
		);
		$out = $this->themes()->execute_update_theme( array( 'stylesheet' => 'astra' ) );
		$this->assertNotWPError( $out );
		$this->assertFalse( $out['up_to_date'] );
		$this->assertContains( 'astra', $GLOBALS['_wp_upgraded'] );
	}
}

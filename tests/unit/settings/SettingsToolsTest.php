<?php
/**
 * Execute-path tests for the WordPress Settings tools.
 * @group settings
 * @package EMCP_Tools\Tests\Settings
 */
namespace EMCP_Tools\Tests\Settings;

require_once dirname( __DIR__ ) . '/class-ability-test-case.php';

use EMCP_Tools\Tests\Ability_Test_Case;

class SettingsToolsTest extends Ability_Test_Case {
	private \EMCP_Tools_Settings_Abilities $ability;

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_wp_options']         = array(
			'blogname'            => 'My Site',
			'blogdescription'     => 'Just another site',
			'admin_email'         => 'admin@example.com',
			'posts_per_page'      => '12',
			'blog_public'         => '1',
			'show_on_front'       => 'posts',
			'permalink_structure' => '/%postname%/',
		);
		$GLOBALS['_wp_options_updates'] = array();
		$GLOBALS['_wp_flush_calls']     = array();
		$this->ability = new \EMCP_Tools_Settings_Abilities();
		$this->ability->register();
	}

	private function find( array $out, string $key ): ?array {
		foreach ( $out['settings'] as $row ) {
			if ( $row['key'] === $key ) {
				return $row;
			}
		}
		return null;
	}

	/** @test */
	public function test_get_all_returns_rows_with_metadata(): void {
		$out = $this->ability->execute_get_settings( array() );
		$this->assertResultHasKey( $out, 'settings' );
		$row = $this->find( $out, 'blogname' );
		$this->assertNotNull( $row );
		$this->assertSame( 'general', $row['group'] );
		$this->assertSame( 'string', $row['type'] );
		$this->assertSame( 'My Site', $row['value'] );
		$this->assertTrue( $row['writable'] );
	}

	/** @test */
	public function test_get_coerces_int_and_bool(): void {
		$out  = $this->ability->execute_get_settings( array() );
		$ppp  = $this->find( $out, 'posts_per_page' );
		$pub  = $this->find( $out, 'blog_public' );
		$this->assertSame( 12, $ppp['value'] );      // int, not "12"
		$this->assertTrue( $pub['value'] );          // bool, not "1"
	}

	/** @test */
	public function test_enum_row_carries_options(): void {
		$out = $this->ability->execute_get_settings( array( 'keys' => array( 'show_on_front' ) ) );
		$this->assertCount( 1, $out['settings'] );
		$this->assertSame( array( 'posts', 'page' ), $out['settings'][0]['options'] );
	}

	/** @test */
	public function test_admin_email_is_read_only(): void {
		$out = $this->ability->execute_get_settings( array( 'keys' => array( 'admin_email' ) ) );
		$this->assertFalse( $out['settings'][0]['writable'] );
		$this->assertSame( 'admin@example.com', $out['settings'][0]['value'] );
	}

	/** @test */
	public function test_group_filter_only_returns_that_screen(): void {
		$out    = $this->ability->execute_get_settings( array( 'group' => 'permalinks' ) );
		$groups = array_unique( array_column( $out['settings'], 'group' ) );
		$this->assertSame( array( 'permalinks' ), $groups );
	}

	/** @test */
	public function test_keys_filter_ignores_non_allowlisted(): void {
		$out  = $this->ability->execute_get_settings( array( 'keys' => array( 'blogname', 'siteurl', 'not_a_setting' ) ) );
		$keys = array_column( $out['settings'], 'key' );
		$this->assertSame( array( 'blogname' ), $keys );
	}

	/** @test */
	public function test_update_writes_allowlisted_key(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'blogname' => 'Renamed' ) ) );
		$this->assertSame( 'Renamed', $out['updated']['blogname'] );
		$this->assertFalse( $out['rewrite_flushed'] );
		$this->assertSame( 'Renamed', get_option( 'blogname' ) );
	}

	/** @test */
	public function test_update_skips_non_allowlisted_key(): void {
		$out     = $this->ability->execute_update_settings( array( 'settings' => array( 'siteurl' => 'http://evil' ) ) );
		$reasons = array_column( $out['skipped'], 'key' );
		$this->assertContains( 'siteurl', $reasons );
		$this->assertArrayNotHasKey( 'siteurl', $out['updated'] );
		$this->assertSame( array(), $GLOBALS['_wp_options_updates'] );
	}

	/** @test */
	public function test_update_skips_read_only_admin_email(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'admin_email' => 'new@example.com' ) ) );
		$skipped = array();
		foreach ( $out['skipped'] as $s ) {
			$skipped[ $s['key'] ] = $s['reason'];
		}
		$this->assertArrayHasKey( 'admin_email', $skipped );
		$this->assertSame( 'read-only', $skipped['admin_email'] );
		$this->assertSame( 'admin@example.com', get_option( 'admin_email' ) );
	}

	/** @test */
	public function test_update_skips_invalid_enum(): void {
		$out     = $this->ability->execute_update_settings( array( 'settings' => array( 'show_on_front' => 'banana' ) ) );
		$skipped = array_column( $out['skipped'], 'key' );
		$this->assertContains( 'show_on_front', $skipped );
		$this->assertArrayNotHasKey( 'show_on_front', $out['updated'] );
	}

	/** @test */
	public function test_update_clamps_int_to_range(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'posts_per_page' => 5000 ) ) );
		$this->assertSame( 100, $out['updated']['posts_per_page'] ); // clamped to max
	}

	/** @test */
	public function test_update_coerces_bool(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'blog_public' => false ) ) );
		$this->assertSame( '', get_option( 'blog_public' ) ); // bool false → ''
		$this->assertSame( false, $out['updated']['blog_public'] );
	}

	/** @test */
	public function test_permalink_change_sets_flush_flag(): void {
		$out = $this->ability->execute_update_settings( array( 'settings' => array( 'permalink_structure' => '/blog/%postname%/' ) ) );
		$this->assertTrue( $out['rewrite_flushed'] );
		$this->assertNotEmpty( $GLOBALS['_wp_flush_calls'] );
	}

	/** @test */
	public function test_partial_failure_applies_valid_subset(): void {
		$out = $this->ability->execute_update_settings( array(
			'settings' => array(
				'blogname'      => 'Good',
				'siteurl'       => 'http://evil',  // skipped
				'show_on_front' => 'banana',       // skipped
			),
		) );
		$this->assertSame( 'Good', $out['updated']['blogname'] );
		$this->assertCount( 2, $out['skipped'] );
	}

	/** @test */
	public function test_empty_map_returns_error(): void {
		$this->assertWPError( $this->ability->execute_update_settings( array( 'settings' => array() ) ), 'missing_params' );
		$this->assertWPError( $this->ability->execute_update_settings( array() ), 'missing_params' );
	}
}

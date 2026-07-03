<?php
/**
 * @group performance
 * @package Elementor_MCP\Tests\Performance
 */
namespace Elementor_MCP\Tests\Performance;

use PHPUnit\Framework\TestCase;

class ServerAuditTest extends TestCase {

	private \Elementor_MCP_Performance_Server_Audit $audit;

	protected function setUp(): void {
		$this->audit = new \Elementor_MCP_Performance_Server_Audit();
	}

	/** @test */
	public function php_version_bands(): void {
		$this->assertSame( 'pass',     $this->audit->evaluate_php_version( '8.2.0' )['status'] );
		$this->assertSame( 'pass',     $this->audit->evaluate_php_version( '8.3.1' )['status'] );
		$this->assertSame( 'warning',  $this->audit->evaluate_php_version( '8.1.27' )['status'] );
		$this->assertSame( 'critical', $this->audit->evaluate_php_version( '7.4.33' )['status'] );
	}

	/** @test */
	public function memory_limit_warns_under_128m(): void {
		$this->assertSame( 'pass',    $this->audit->evaluate_memory_limit( '256M' )['status'] );
		$this->assertSame( 'pass',    $this->audit->evaluate_memory_limit( '128M' )['status'] );
		$this->assertSame( 'warning', $this->audit->evaluate_memory_limit( '64M' )['status'] );
		// -1 means unlimited → pass.
		$this->assertSame( 'pass',    $this->audit->evaluate_memory_limit( '-1' )['status'] );
	}

	/** @test */
	public function autoload_size_bands(): void {
		$this->assertSame( 'pass',     $this->audit->evaluate_autoload_size( 200 * 1024, array() )['status'] );
		$this->assertSame( 'warning',  $this->audit->evaluate_autoload_size( 1500 * 1024, array() )['status'] );
		$this->assertSame( 'critical', $this->audit->evaluate_autoload_size( 4 * 1024 * 1024, array() )['status'] );
	}

	/** @test */
	public function opcache_and_object_cache(): void {
		$this->assertSame( 'pass',    $this->audit->evaluate_opcache( true )['status'] );
		$this->assertSame( 'warning', $this->audit->evaluate_opcache( false )['status'] );
		$this->assertSame( 'pass',    $this->audit->evaluate_object_cache( true )['status'] );
		$this->assertSame( 'warning', $this->audit->evaluate_object_cache( false )['status'] );
	}

	/** @test */
	public function plugin_count_and_revisions(): void {
		$this->assertSame( 'info',    $this->audit->evaluate_plugin_count( 12 )['status'] );
		$this->assertSame( 'warning', $this->audit->evaluate_plugin_count( 55 )['status'] );
		$this->assertSame( 'info',    $this->audit->evaluate_revisions( 40 )['status'] );
		$this->assertSame( 'warning', $this->audit->evaluate_revisions( 5000 )['status'] );
	}

	/** @test */
	public function cron_backlog_warns_when_overdue(): void {
		$this->assertSame( 'pass',    $this->audit->evaluate_cron_backlog( 0 )['status'] );
		$this->assertSame( 'warning', $this->audit->evaluate_cron_backlog( 7 )['status'] );
	}

	/** @test */
	public function wp_debug_warns_only_in_production(): void {
		$this->assertSame( 'warning', $this->audit->evaluate_wp_debug( true, 'production' )['status'] );
		$this->assertSame( 'info',    $this->audit->evaluate_wp_debug( true, 'local' )['status'] );
		$this->assertSame( 'pass',    $this->audit->evaluate_wp_debug( false, 'production' )['status'] );
	}

	/**
	 * A1: WordPress 6.6+ stores autoloaded options under 'auto-on' too (Core's
	 * wp_autoload_values_to_autoload() = yes,on,auto-on,auto). The autoload-size
	 * queries must count all four, or large 'auto-on' rows are silently excluded
	 * from the total → false "pass".
	 *
	 * @test
	 */
	public function autoload_query_counts_the_wp66_auto_on_value(): void {
		$where = $this->audit->autoload_where_clause();
		$this->assertStringContainsString( "'auto-on'", $where );
		foreach ( array( "'yes'", "'on'", "'auto-on'", "'auto'" ) as $value ) {
			$this->assertStringContainsString( $value, $where );
		}
	}
}

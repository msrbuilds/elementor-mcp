<?php
/**
 * Part 5c final-review fixes: pre-upgrade undo rows, target validation,
 * writes while the schema upgrade is pending, and the upgrade retry backoff.
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/redirects/class-redirect-store.php';

class RedirectReviewFixesTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		emcp_test_reset();
	}

	public function test_row_for_write_turns_a_pre_upgrade_row_into_a_path_rule(): void {
		$old = EMCP_Tools_Redirect_Store::row_for_write( array( 'id' => 5, 'source_path' => '/old', 'ignore_query' => 0, 'target' => '/new' ) );
		$this->assertSame( 1, $old['ignore_query'], 'a row without a query can only be a path rule' );
		$this->assertNotNull( EMCP_Tools_Redirect_Store::pick( null, $old + array( 'enabled' => 1 ) ) );
		$q = EMCP_Tools_Redirect_Store::row_for_write( array( 'id' => 6, 'source_path' => '/old', 'source_query' => 'ref=ad', 'ignore_query' => 0, 'target' => '/new' ) );
		$this->assertSame( 0, $q['ignore_query'] );
	}

	public function test_targets_must_be_root_relative_or_http_urls(): void {
		foreach ( array( '/new', '/', 'https://example.com/x', 'http://example.com', 'HTTPS://EXAMPLE.COM/A' ) as $ok ) {
			$this->assertTrue( EMCP_Tools_Redirect_Store::is_valid_target( $ok ), $ok );
		}
		foreach ( array( 'About us', 'new-page', 'http://About%20us', 'javascript:alert(1)', '//evil.test/x', '', 'ftp://example.com/a', 'https://' ) as $bad ) {
			$this->assertFalse( EMCP_Tools_Redirect_Store::is_valid_target( $bad ), $bad );
		}
	}

	public function test_create_refuses_a_bare_title_target(): void {
		$r = EMCP_Tools_Redirect_Store::create( array( 'source' => '/old', 'target' => 'About us' ) );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'invalid_target', $r->get_error_code() );
	}

	public function test_writes_wait_for_the_schema_upgrade(): void {
		$GLOBALS['emcp_test']['options'][ EMCP_Tools_Redirect_Store::DB_VERSION_OPTION ] = 1;
		$r = EMCP_Tools_Redirect_Store::create( array( 'source' => '/old', 'target' => '/new' ) );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'redirects_upgrading', $r->get_error_code() );
		$r = EMCP_Tools_Redirect_Store::update( 3, array( 'enabled' => false ) );
		$this->assertSame( 'redirects_upgrading', $r->get_error_code() );
	}

	public function test_a_failed_upgrade_backs_off_for_an_hour(): void {
		$this->assertTrue( EMCP_Tools_Redirect_Store::should_try_upgrade( 0, 1000 ) );
		$this->assertFalse( EMCP_Tools_Redirect_Store::should_try_upgrade( 1000, 1000 + 3599 ) );
		$this->assertTrue( EMCP_Tools_Redirect_Store::should_try_upgrade( 1000, 1000 + 3600 ) );
	}
}

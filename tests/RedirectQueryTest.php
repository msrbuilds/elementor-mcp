<?php
/**
 * Redirect query matching, part 1: normalization, keys, upgrade plan (spec 9.8).
 *
 * @package EMCP_Tools
 */

require_once dirname( __DIR__ ) . '/includes/redirects/class-redirect-store.php';

class RedirectQueryTest extends \PHPUnit\Framework\TestCase {

	protected function setUp(): void {
		emcp_test_reset();
	}

	public function test_normalize_query_sorts_and_keeps_empty_values(): void {
		$this->assertSame( 'a=1&b=2', EMCP_Tools_Redirect_Store::normalize_query( '/page?b=2&a=1' ) );
		$this->assertSame( 'a=1&b=2', EMCP_Tools_Redirect_Store::normalize_query( 'a=1&b=2' ) );
		$this->assertSame( 'a=&b', EMCP_Tools_Redirect_Store::normalize_query( '?b&a=' ) );
		$this->assertSame( 'q=hello%20world', EMCP_Tools_Redirect_Store::normalize_query( '?q=hello+world' ) );
		$this->assertSame( 'q=hello%20world', EMCP_Tools_Redirect_Store::normalize_query( '?q=hello%20world' ) );
		$this->assertSame( 'Ref=Ad', EMCP_Tools_Redirect_Store::normalize_query( '?Ref=Ad' ), 'case is kept' );
		$this->assertSame( '', EMCP_Tools_Redirect_Store::normalize_query( '/page' ) );
		$this->assertSame( '', EMCP_Tools_Redirect_Store::normalize_query( '/page?' ) );
		$this->assertSame( 'a=1&a=2', EMCP_Tools_Redirect_Store::normalize_query( '?a=2&a=1' ) );
		$this->assertSame( 'a=1', EMCP_Tools_Redirect_Store::normalize_query( '/page?a=1#frag' ) );
	}

	public function test_split_source_and_key(): void {
		$this->assertSame( array( 'path' => '/page', 'query' => 'ref=ad' ), EMCP_Tools_Redirect_Store::split_source( 'https://example.com/Page/?ref=ad' ) );
		$this->assertSame( sha1( '/page?ref=ad' ), EMCP_Tools_Redirect_Store::key_for( '/page', 'ref=ad' ) );
		$this->assertSame( sha1( '/page?' ), EMCP_Tools_Redirect_Store::key_for( '/page', '' ) );
	}

	public function test_query_rule_needs_a_query(): void {
		$r = EMCP_Tools_Redirect_Store::create( array( 'source' => '/page', 'target' => '/other', 'ignore_query' => false ) );
		$this->assertInstanceOf( WP_Error::class, $r );
		$this->assertSame( 'query_required', $r->get_error_code() );
	}

	public function test_upgrade_plan_runs_only_the_missing_steps(): void {
		$fresh_v1 = array( 'has_query_col' => false, 'has_key_col' => false, 'has_old_unique' => true, 'has_key_unique' => false );
		$this->assertSame( array( 'add_query_col', 'add_key_col', 'backfill_keys', 'path_rules', 'drop_old_unique', 'add_key_unique' ), EMCP_Tools_Redirect_Store::upgrade_plan( $fresh_v1 ) );
		$half = array( 'has_query_col' => true, 'has_key_col' => true, 'has_old_unique' => false, 'has_key_unique' => false );
		$this->assertSame( array( 'backfill_keys', 'path_rules', 'add_key_unique' ), EMCP_Tools_Redirect_Store::upgrade_plan( $half ) );
		$done = array( 'has_query_col' => true, 'has_key_col' => true, 'has_old_unique' => false, 'has_key_unique' => true );
		$this->assertSame( array( 'backfill_keys', 'path_rules' ), EMCP_Tools_Redirect_Store::upgrade_plan( $done ), 'backfills are idempotent and always run' );
	}

	public function test_row_for_write_carries_the_query_columns(): void {
		$old = EMCP_Tools_Redirect_Store::row_for_write( array( 'id' => 5, 'source_path' => '/old', 'target' => '/new' ) );
		$this->assertSame( '', $old['source_query'] );
		$this->assertSame( sha1( '/old?' ), $old['source_key'] );
		$q = EMCP_Tools_Redirect_Store::row_for_write( array( 'id' => 6, 'source_path' => '/old', 'source_query' => 'ref=ad', 'ignore_query' => 0, 'target' => '/new' ) );
		$this->assertSame( sha1( '/old?ref=ad' ), $q['source_key'] );
		$this->assertCount( count( $q ), EMCP_Tools_Redirect_Store::write_formats(), 'one format per written column' );
	}
}

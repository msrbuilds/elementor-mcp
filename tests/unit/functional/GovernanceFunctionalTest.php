<?php
/**
 * Functional — SiteAgent governance bridge: destructive page writes are
 * snapshotted before execution and rolled back on failure.
 *
 * @group functional
 * @group governance
 * @package Elementor_MCP\Tests\Functional
 */

namespace Elementor_MCP\Tests\Functional;

use PHPUnit\Framework\TestCase;

class GovernanceFunctionalTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$GLOBALS['_aura_snap'] = array(
			'fail_snapshot'  => false,
			'snapshot_calls' => array(),
			'restore_calls'  => array(),
			'seq'            => 0,
		);
	}

	/** A destructive ability with a page id return payload. */
	private function destructive_args( $callback ): array {
		return array(
			'label'            => 'Delete page content',
			'execute_callback' => $callback,
			'meta'             => array(
				'annotations' => array(
					'readonly'    => false,
					'destructive' => true,
					'idempotent'  => true,
				),
			),
		);
	}

	// --- wrap_ability decision logic ---------------------------------------

	public function test_destructive_ability_is_wrapped(): void {
		$original = static function ( $input ) {
			return array( 'ok' => true );
		};
		$args    = $this->destructive_args( $original );
		$wrapped = \Elementor_MCP_Governance::wrap_ability( 'elementor-mcp/delete-page-content', $args );

		$this->assertNotSame( $original, $wrapped['execute_callback'], 'Destructive callback must be decorated.' );
		$this->assertIsCallable( $wrapped['execute_callback'] );
	}

	public function test_non_destructive_ability_is_not_wrapped(): void {
		$original                                 = static function ( $input ) {
			return array( 'ok' => true ); };
		$args                                     = $this->destructive_args( $original );
		$args['meta']['annotations']['destructive'] = false;

		$wrapped = \Elementor_MCP_Governance::wrap_ability( 'elementor-mcp/export-page', $args );
		$this->assertSame( $original, $wrapped['execute_callback'], 'Read/non-destructive tools stay untouched.' );
	}

	public function test_ability_without_callback_is_not_wrapped(): void {
		$args = $this->destructive_args( 'not-callable-string-#' );
		unset( $args['execute_callback'] );
		$wrapped = \Elementor_MCP_Governance::wrap_ability( 'elementor-mcp/x', $args );
		$this->assertArrayNotHasKey( 'execute_callback', $wrapped );
	}

	// --- run_governed behaviour --------------------------------------------

	public function test_snapshots_page_meta_before_a_successful_write(): void {
		$result = \Elementor_MCP_Governance::run_governed(
			'elementor-mcp/delete-page-content',
			static function ( $input ) {
				return array( 'deleted' => true ); },
			array( 'post_id' => 55 )
		);

		$this->assertSame( array( 'deleted' => true ), $result );
		$this->assertCount( 1, $GLOBALS['_aura_snap']['snapshot_calls'] );
		$this->assertSame( 55, $GLOBALS['_aura_snap']['snapshot_calls'][0]['post_id'] );
		$this->assertSame(
			\Elementor_MCP_Governance::PAGE_META_KEYS,
			$GLOBALS['_aura_snap']['snapshot_calls'][0]['keys']
		);
		$this->assertCount( 0, $GLOBALS['_aura_snap']['restore_calls'], 'Success must not roll back.' );
	}

	public function test_writes_without_post_id_pass_through_ungoverned(): void {
		$called = false;
		$result = \Elementor_MCP_Governance::run_governed(
			'elementor-mcp/create-variable',
			static function ( $input ) use ( &$called ) {
				$called = true;
				return array( 'created' => true );
			},
			array( 'label' => 'brand' ) // no post_id
		);

		$this->assertTrue( $called );
		$this->assertSame( array( 'created' => true ), $result );
		$this->assertCount( 0, $GLOBALS['_aura_snap']['snapshot_calls'], 'No post → nothing to snapshot.' );
	}

	public function test_snapshot_failure_denies_the_write(): void {
		$GLOBALS['_aura_snap']['fail_snapshot'] = true;
		$called                                 = false;

		$result = \Elementor_MCP_Governance::run_governed(
			'elementor-mcp/delete-page-content',
			static function ( $input ) use ( &$called ) {
				$called = true;
				return array( 'deleted' => true );
			},
			array( 'post_id' => 55 )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'governance_snapshot_failed', $result->get_error_code() );
		$this->assertFalse( $called, 'The write must NOT run when there is no rollback point.' );
		$this->assertCount( 0, $GLOBALS['_aura_snap']['restore_calls'] );
	}

	public function test_failed_write_is_rolled_back(): void {
		$error  = new \WP_Error( 'save_rejected', 'Elementor rejected the data.' );
		$result = \Elementor_MCP_Governance::run_governed(
			'elementor-mcp/update-element',
			static function ( $input ) use ( $error ) {
				return $error; },
			array( 'post_id' => 77 )
		);

		$this->assertSame( $error, $result, 'The original failure is returned unchanged.' );
		$this->assertSame( array( 'snap_stub_1' ), $GLOBALS['_aura_snap']['restore_calls'] );
	}

	public function test_thrown_write_is_rolled_back(): void {
		$result = \Elementor_MCP_Governance::run_governed(
			'elementor-mcp/update-element',
			static function ( $input ) {
				throw new \RuntimeException( 'boom' ); },
			array( 'post_id' => 88 )
		);

		$this->assertInstanceOf( \WP_Error::class, $result );
		$this->assertSame( 'governance_write_threw', $result->get_error_code() );
		$this->assertStringContainsString( 'boom', $result->get_error_message() );
		$this->assertSame( array( 'snap_stub_1' ), $GLOBALS['_aura_snap']['restore_calls'] );
	}
}

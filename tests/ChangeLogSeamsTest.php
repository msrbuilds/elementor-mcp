<?php
/**
 * Free History seams used by Pro rollback types: irreversible entries carry their own reason,
 * unknown rollback types are offered to filters (with the caller's force flag), FunnelKit
 * domains count as content. Each scenario runs in its own process (fixtures/change-log-seams.php).
 *
 * @package EMCP_Tools\Tests
 */

use PHPUnit\Framework\TestCase;

final class ChangeLogSeamsTest extends TestCase {

	/** @dataProvider scenarios */
	public function test_change_log_seam( string $scenario ): void {
		$process = proc_open(
			array( PHP_BINARY, __DIR__ . '/fixtures/change-log-seams.php', $scenario ),
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);
		$this->assertIsResource( $process );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output . $error );
		$this->assertSame( "PASS\n", $output, $error );
	}

	public static function scenarios(): array {
		return array(
			'an irreversible entry reports its own reason' => array( 'irreversible-reason' ),
			'unknown types are offered to the filters'     => array( 'unknown-type-filters' ),
			'an unhandled unknown type is still an error'  => array( 'unknown-type-unhandled' ),
			'FunnelKit domains are content'                => array( 'funnelkit-kinds' ),
		);
	}
}

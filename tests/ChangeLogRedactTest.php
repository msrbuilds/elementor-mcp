<?php
/**
 * EMCP_Tools_Change_Log::redact(): an integration removes a person's data from its own History
 * entries (inline payloads and blobs) in either store. Each scenario runs in its own process
 * (fixtures/change-log-redact.php).
 *
 * @package EMCP_Tools\Tests
 */

use PHPUnit\Framework\TestCase;

final class ChangeLogRedactTest extends TestCase {

	/** @dataProvider scenarios */
	public function test_redact( string $scenario ): void {
		$process = proc_open(
			array( PHP_BINARY, __DIR__ . '/fixtures/change-log-redact.php', $scenario ),
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
		$out = array();
		foreach ( array( 'option', 'table' ) as $store ) {
			foreach ( array( 'walk', 'strip', 'rewrite', 'keep', 'race', 'match' ) as $case ) {
				$out[ "$store store: $case" ] = array( "$store:$case" );
			}
		}
		return $out;
	}
}

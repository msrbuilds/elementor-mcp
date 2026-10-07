<?php
use PHPUnit\Framework\TestCase;

final class ManagementAccessTest extends TestCase {
	/** @dataProvider scenarios */
	public function test_policy_and_transport_boundaries( string $scenario ): void {
		$process = proc_open( array( PHP_BINARY, __DIR__ . '/fixtures/management-access.php', $scenario ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] ); fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output . $error );
		$this->assertSame( "PASS\n", $output, $error );
	}

	public static function scenarios(): array {
		return array_map( static fn( $name ) => array( $name ), array( 'default', 'membership', 'validation', 'concurrency', 'malformed', 'storage', 'admin-requests', 'rest', 'recovery', 'http-recovery', 'form', 'sync', 'config', 'config-array', 'config-all', 'config-invalid' ) );
	}
}

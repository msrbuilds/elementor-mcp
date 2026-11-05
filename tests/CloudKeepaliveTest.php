<?php
use PHPUnit\Framework\TestCase;

final class CloudKeepaliveTest extends TestCase {
	public function test_keepalive_rules(): void {
		$process = proc_open( array( PHP_BINARY, __DIR__ . '/fixtures/cloud-keepalive.php' ),
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output . $error );
		$this->assertStringContainsString( 'PASS', $output );
	}
}

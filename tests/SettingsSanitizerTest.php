<?php
use PHPUnit\Framework\TestCase;

final class SettingsSanitizerTest extends TestCase {
	public function test_post_data_cannot_override_programmatic_defaults(): void {
		$process = proc_open(
			array( PHP_BINARY, __DIR__ . '/fixtures/settings-sanitizer.php' ),
			array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ),
			$pipes
		);
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output . $error );
		$this->assertSame( "PASS\n", $output, $error );
	}
}

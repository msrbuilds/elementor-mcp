<?php
use PHPUnit\Framework\TestCase;

/**
 * A plugin that registers a menu item with a null capability makes core call
 * every map_meta_cap filter with $cap = null. A strict string type there threw
 * a TypeError on every wp-admin page for non-administrators (#174).
 */
final class NullCapabilityTest extends TestCase {
	public function test_map_meta_cap_callbacks_accept_non_string_capabilities(): void {
		$process = proc_open( array( PHP_BINARY, __DIR__ . '/fixtures/null-capability.php' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] ); $error = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] ); fclose( $pipes[2] );
		$this->assertSame( 0, proc_close( $process ), $output . $error );
		$this->assertSame( "PASS\n", $output, $error );
	}
}

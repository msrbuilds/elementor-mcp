<?php
/**
 * #182: the PHP snippet store reads its own meta from the database, so a
 * persistent object cache that keeps an empty meta array for a new snippet
 * cannot blank create, update, get or list answers. Runs in its own process
 * (fixtures/php-snippet-stale-cache.php) because it stubs WordPress.
 *
 * @package EMCP_Tools\Tests
 */

use PHPUnit\Framework\TestCase;

final class PhpSnippetStaleCacheTest extends TestCase {

	public function test_store_ignores_a_stale_empty_meta_cache(): void {
		$process = proc_open(
			array( PHP_BINARY, __DIR__ . '/fixtures/php-snippet-stale-cache.php' ),
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
}

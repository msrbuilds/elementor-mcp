<?php
/**
 * The uninstaller runs without EMCP's bootstrap (WordPress loads the plugin in
 * uninstall mode and Freemius fires after_uninstall), so it must load every class
 * its cleanup steps need. It used to load the widget store without the sandbox
 * paths class the store calls, so every real uninstall fataled at the widget step
 * and nothing after it ran: snippets, OAuth tables, Pro data, AI Chat keys.
 */
use PHPUnit\Framework\TestCase;

final class UninstallerTest extends TestCase {
	public function test_the_uninstaller_runs_every_step_on_its_own(): void {
		$process = proc_open( array( PHP_BINARY, __DIR__ . '/fixtures/uninstall.php' ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		fclose( $pipes[0] );
		$output = stream_get_contents( $pipes[1] );
		$error  = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] );
		fclose( $pipes[2] );
		$code = proc_close( $process );
		$this->assertSame( "PASS\n", $output, $error );
		$this->assertSame( 0, $code, $output . $error );
	}
}

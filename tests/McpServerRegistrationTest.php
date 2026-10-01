<?php
use PHPUnit\Framework\TestCase;

final class McpServerRegistrationTest extends TestCase {
	private function run_fixture( string $scenario ): array {
		$command = escapeshellarg( PHP_BINARY ) . ' ' . escapeshellarg( __DIR__ . '/fixtures/mcp-registration.php' ) . ' ' . escapeshellarg( $scenario );
		exec( $command, $output, $exit );
		$this->assertSame( 0, $exit, implode( "\n", $output ) );
		$result = json_decode( implode( "\n", $output ), true );
		$this->assertIsArray( $result );
		return $result;
	}

	public function test_registers_without_another_server_initializing_abilities(): void {
		$result = $this->run_fixture( 'lazy' );
		$this->assertTrue( $result['initialized'] );
		$this->assertSame( array( array( 'id' => 'emcp-tools-server', 'tools' => array( 'emcp-tools/list-pages' ) ) ), $result['servers'] );
	}

	public function test_preloaded_registry_keeps_the_same_tools(): void {
		$result = $this->run_fixture( 'preloaded' );
		$this->assertSame( array( 'emcp-tools/list-pages' ), $result['servers'][0]['tools'] );
	}

	public function test_disabled_server_does_not_initialize_or_register(): void {
		$result = $this->run_fixture( 'disabled' );
		$this->assertSame( 0, $result['lookups'] );
		$this->assertSame( array(), $result['servers'] );
	}

	public function test_empty_registry_still_creates_no_server(): void {
		$result = $this->run_fixture( 'empty' );
		$this->assertTrue( $result['initialized'] );
		$this->assertSame( array(), $result['servers'] );
	}

	public function test_compact_mode_still_exposes_only_dispatchers(): void {
		$result = $this->run_fixture( 'compact' );
		$this->assertTrue( $result['initialized'] );
		$this->assertSame( array( 'emcp-tools/list-tools', 'emcp-tools/get-tool-schema', 'emcp-tools/call-tool' ), $result['servers'][0]['tools'] );
	}
}
